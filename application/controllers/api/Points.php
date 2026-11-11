<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pontos de campo do app (mapa integrado).
 *
 *   POST api/points/submit   cria/atualiza o ponto pelo client_id
 *   GET  api/points          pontos dos projetos a que o usuário tem acesso
 *
 * Como em api/forms/submit, o id devolvido é a prova de gravação: só depois
 * de recebê-lo o ponto sai da fila do aparelho. Reenvio do mesmo client_id
 * atualiza o registro, nunca duplica.
 *
 * Coletas enviadas antes do ponto (com point_client_id) ficam com
 * form_responses.field_point_client_id preenchido e field_point_id NULL; a
 * chegada do ponto preenche o field_point_id delas.
 */
class Points extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('api_token');
        $this->load->library('geo_municipio');
        $this->load->library('malha_municipal');
        $this->load->model('Field_point_model', 'pontos');
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($this->input->method() === 'options') {
            exit();
        }
    }

    /** GET api/points?codigo_ibge=&project_id= */
    public function index() {
        if ($this->input->method() !== 'get') {
            return $this->responder(405, ['success' => false, 'message' => 'Method not allowed']);
        }
        $user_id = $this->autenticar();
        if (!$user_id || !$this->migracao_ok()) {
            return;
        }

        $projetos = $this->pontos->projetos_do_usuario($user_id);

        $project_id = (int) $this->input->get('project_id');
        if ($project_id > 0) {
            if (!in_array($project_id, $projetos, TRUE)) {
                return $this->responder(403, ['success' => false, 'message' => 'Sem acesso a este projeto.']);
            }
            $projetos = [$project_id];
        }

        $codigo = preg_replace('/\D/', '', (string) $this->input->get('codigo_ibge'));
        $codigo = strlen($codigo) === 7 ? (int) $codigo : NULL;

        $lista = $this->pontos->listar($projetos, $codigo);
        $this->responder(200, ['success' => true, 'data' => $lista, 'total' => count($lista)]);
    }

    /** POST api/points/submit */
    public function submit() {
        if ($this->input->method() !== 'post') {
            return $this->responder(405, ['success' => false, 'message' => 'Method not allowed']);
        }
        $user_id = $this->autenticar();
        if (!$user_id || !$this->migracao_ok()) {
            return;
        }

        $json = json_decode(file_get_contents('php://input'), TRUE);
        list($dados, $erros) = Ponto_campo::validar($json);
        if ($erros) {
            return $this->responder(400, ['success' => false, 'message' => implode(' ', $erros), 'errors' => $erros]);
        }

        $projetos = $this->pontos->projetos_do_usuario($user_id);
        if (!in_array($dados['project_id'], $projetos, TRUE)) {
            return $this->responder(403, ['success' => false, 'message' => 'Sem acesso ao projeto ' . $dados['project_id'] . '.']);
        }

        // Ponto já gravado: quem edita precisa ter acesso ao projeto atual dele também
        $existente = $this->pontos->por_client_id($dados['client_id']);
        if ($existente && !in_array((int) $existente->project_id, $projetos, TRUE)) {
            return $this->responder(403, ['success' => false, 'message' => 'Sem acesso ao projeto deste ponto.']);
        }

        if (!$this->pontos->municipio_existe($dados['codigo_ibge'])) {
            return $this->responder(400, ['success' => false, 'message' => 'Município ' . $dados['codigo_ibge'] . ' não está na lista do IBGE (rode sql/ibge_municipios_seed.sql).']);
        }

        $dados['dentro_municipio'] = $this->malha_municipal->contem($dados['codigo_ibge'], $dados['latitude'], $dados['longitude']);

        try {
            $r = $this->pontos->salvar($dados, $user_id);
        } catch (Exception $e) {
            log_message('error', 'points/submit FALHOU - ' . $e->getMessage() . ' | client_id=' . $dados['client_id']);
            // Nunca devolver id aqui: o app mantém o ponto na fila e reenvia
            return $this->responder(500, ['success' => false, 'message' => 'Falha ao gravar o ponto: ' . $e->getMessage()]);
        }

        $aviso = NULL;
        if ($dados['dentro_municipio'] === FALSE) {
            $aviso = 'A coordenada fica fora do município informado. O ponto foi gravado; confira o município ou a posição.';
        }

        log_message('info', 'points/submit OK - id=' . $r['id'] . ' novo=' . ($r['novo'] ? 1 : 0)
            . ' coletas_vinculadas=' . $r['coletas_vinculadas'] . ' client_id=' . $dados['client_id']);

        $this->responder(200, [
            'success'            => TRUE,
            'id'                 => $r['id'],
            'point_id'           => $r['id'],
            'client_id'          => $dados['client_id'],
            'created'            => $r['novo'],
            'dentro_municipio'   => $dados['dentro_municipio'],
            'coletas_vinculadas' => $r['coletas_vinculadas'],
            'aviso'              => $aviso,
            'message'            => $r['novo'] ? 'Ponto gravado' : 'Ponto atualizado',
        ]);
    }

    // ------------------------------------------------------------

    private function autenticar() {
        $user_id = $this->api_token->user_id_from_request();
        if (!$user_id) {
            $this->responder(401, ['success' => false, 'message' => 'Invalid token']);
            return FALSE;
        }
        return (int) $user_id;
    }

    private function migracao_ok() {
        if ($this->pontos->disponivel()) {
            return TRUE;
        }
        $this->responder(503, ['success' => false, 'message' => 'Pontos de campo indisponíveis: rode sql/field_points_migration.sql no servidor.']);
        return FALSE;
    }

    private function responder($status, $dados) {
        $this->output->set_status_header($status);
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    }
}
