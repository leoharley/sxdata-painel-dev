<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Estrutura_envio.php';

/**
 * API dos formulários estruturados para o app (docs/FORMULARIOS_ESTRUTURADOS.md).
 *
 *   GET  api/estruturado/formularios   formulários publicados e ativos do aplicador, com a definição
 *   POST api/estruturado/enviar        grava a entrevista inteira (domicílio + moradores) de uma vez
 *
 * Como em api/forms/submit, o id devolvido é a prova de gravação: só depois
 * dele a entrevista sai da fila do aparelho. Reenvio do mesmo client_id não
 * duplica; edição mais nova (edited_at) substitui a anterior por inteiro.
 * Inconsistência (obrigatória vazia, regra, cálculo divergente) não recusa o
 * envio: grava e põe na fila de revisão do painel.
 */
class Estruturado extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('api_token');
        $this->load->model('Estruturado_model', 'estr');
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        if ($this->input->method() === 'options') {
            exit();
        }
    }

    public function formularios() {
        if ($this->input->method() !== 'get') {
            return $this->responder(405, ['success' => FALSE, 'message' => 'Method not allowed']);
        }
        $user_id = $this->autenticar();
        if (!$user_id || !$this->migracao_ok()) {
            return;
        }
        $lista = $this->estr->formularios_para_app($user_id);
        $this->responder(200, ['success' => TRUE, 'data' => $lista, 'total' => count($lista), 'schema' => 1]);
    }

    public function enviar() {
        if ($this->input->method() !== 'post') {
            return $this->responder(405, ['success' => FALSE, 'message' => 'Method not allowed']);
        }
        $user_id = $this->autenticar();
        if (!$user_id || !$this->migracao_ok()) {
            return;
        }
        $raw = file_get_contents('php://input');
        $declarado = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        if ($raw === '' && $declarado > 0) {
            return $this->responder(413, ['success' => FALSE, 'limit_exceeded' => TRUE,
                'message' => 'Envio maior que o limite do servidor (post_max_size=' . ini_get('post_max_size') . ').']);
        }
        $json = json_decode($raw, TRUE);
        list($dados, $erros) = Estrutura_envio::normalizar($json);
        if ($erros) {
            return $this->responder(400, ['success' => FALSE, 'json_error' => TRUE, 'message' => implode(' ', $erros), 'errors' => $erros]);
        }
        $q = $this->estr->obter($dados['questionnaire_id']);
        if (!$q) {
            return $this->responder(404, ['success' => FALSE, 'message' => 'Formulário estruturado não encontrado.']);
        }

        try {
            $r = $this->estr->gravar_entrevista($dados, $user_id);
        } catch (InvalidArgumentException $e) {
            return $this->responder(422, ['success' => FALSE, 'message' => $e->getMessage()]);
        } catch (Exception $e) {
            log_message('error', 'estruturado/enviar - ' . $e->getMessage());
            return $this->responder(500, ['success' => FALSE, 'message' => 'Erro ao gravar a entrevista. Reenvie.']);
        }

        $conf = $r['conferencia'];
        $fotos = [];
        foreach (array_merge([$conf['respostas_domicilio']], array_values($conf['respostas_moradores'])) as $resps) {
            foreach ($resps as $resp) {
                $arq = Estrutura_envio::arquivo(is_string($resp['valor'] ?? NULL) ? $resp['valor'] : NULL);
                if ($arq && preg_match('/\.(jpe?g|png|webp|heic)$/i', $arq)) { $fotos[] = $arq; }
            }
        }
        $n_resp = count($conf['respostas_domicilio']);
        foreach ($conf['respostas_moradores'] as $rm) { $n_resp += count($rm); }
        $n_calc = count($conf['calculados']['domicilio']);
        foreach ($conf['calculados']['moradores'] as $cm) { $n_calc += count($cm); }

        $this->responder(200, [
            'success' => TRUE,
            'id' => $r['id'],
            'form_response_id' => $r['id'],
            'data' => ['id' => $r['id']],
            'duplicated' => $r['duplicado'],
            'edited' => $r['editado'],
            // Confirmação por parte: o app confere antes de liberar a entrevista
            'partes' => [
                'respostas' => $n_resp,
                'moradores' => count($dados['moradores']),
                'calculados' => $n_calc,
            ],
            'missing_media' => $this->estr->midias_faltando($r['id'], $fotos),
            'revisao' => $conf['revisao'],
            'erros' => $conf['erros'],
            'alertas' => $conf['alertas'],
            'message' => $r['duplicado'] ? 'Entrevista já recebida.' : 'Entrevista gravada.',
        ]);
    }

    private function autenticar() {
        $user_id = $this->api_token->user_id_from_request();
        if (!$user_id) {
            $this->responder(401, ['success' => FALSE, 'message' => 'Invalid token']);
            return FALSE;
        }
        return (int) $user_id;
    }

    private function migracao_ok() {
        if ($this->estr->disponivel()) {
            return TRUE;
        }
        $this->responder(503, ['success' => FALSE, 'message' => 'Formulários estruturados indisponíveis: rode sql/estruturado_migration.sql no servidor.']);
        return FALSE;
    }

    private function responder($status, $dados) {
        $this->output->set_status_header($status);
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    }
}
