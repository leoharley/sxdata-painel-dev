<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Estrutura_compilador.php';
require_once APPPATH . 'libraries/Estrutura_envio.php';

/**
 * Formulários estruturados (domicílio + moradores, campos calculados, pulos).
 * Tela própria: o editor de questionários atual não muda (docs/PLANO_EVOLUCAO_GERDAU_SRA.md).
 *
 * Rotas: formularios-estruturados/...  Restrito à equipe (administrador e supervisor).
 */
class Estruturados extends CI_Controller {

    /** Modelos oferecidos ao criar um formulário (docs/formularios). */
    const MODELOS = [
        'jmc_formulario_geral' => 'JMC - Formulário Geral (unidades familiares)',
        'jmc_pequenos_negocios' => 'JMC - Pequenos Negócios',
    ];

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        if (!$this->session->userdata('admin_logged_in')) {
            redirect('auth/login');
        }
        exigir_equipe();
        $this->load->model('Estruturado_model', 'estr');
        if (!$this->estr->disponivel()) {
            show_error('Formulários estruturados indisponíveis: rode sql/estruturado_migration.sql no servidor.', 503);
        }
    }

    // ------------------------------------------------------------------
    // Lista e criação
    // ------------------------------------------------------------------

    public function index() {
        $this->exibir('admin/estruturados/index', [
            'title' => 'Formulários estruturados',
            'formularios' => $this->estr->listar(),
        ]);
    }

    public function novo() {
        if ($this->input->method() === 'post') {
            $titulo = trim((string) $this->input->post('title'));
            if ($titulo === '') {
                $this->session->set_flashdata('error', 'Informe o título.');
                redirect('formularios-estruturados/novo');
            }
            $modelo = (string) $this->input->post('modelo');
            $fonte = isset(self::MODELOS[$modelo]) ? $this->modelo($modelo) : $this->fonte_em_branco($titulo);
            $id = $this->estr->criar([
                'title' => $titulo,
                'description' => trim((string) $this->input->post('description')) ?: NULL,
                'project_id' => (int) $this->input->post('project_id'),
                'fonte' => $fonte,
            ], (int) $this->session->userdata('admin_id'));
            $this->session->set_flashdata('success', 'Formulário criado. Revise, publique uma versão e ative para ir ao app.');
            redirect('formularios-estruturados/editar/' . $id);
        }
        $this->exibir('admin/estruturados/novo', [
            'title' => 'Novo formulário estruturado',
            'projetos' => $this->db->order_by('name')->get('projects')->result(),
            'modelos' => self::MODELOS,
        ]);
    }

    // ------------------------------------------------------------------
    // Editor
    // ------------------------------------------------------------------

    public function editar($id) {
        $q = $this->obter_ou_404($id);
        list($def, $erros, $avisos) = Estrutura_compilador::compilar((string) $q->estrutura_fonte);
        $this->exibir('admin/estruturados/editar', [
            'title' => 'Editar: ' . $q->title,
            'q' => $q,
            'versoes' => $this->estr->versoes($q->id),
            'projetos' => $this->db->order_by('name')->get('projects')->result(),
            'aplicadores' => $this->db->select('id, full_name, username')->where('role', 'aplicador')->where('is_active', 't')
                ->order_by('full_name')->get('users')->result(),
            'resultado' => $this->resumo($def, $erros, $avisos),
        ]);
    }

    /** POST: compila o texto (sem salvar) e devolve erros, avisos e o resumo. */
    public function validar() {
        list($def, $erros, $avisos) = Estrutura_compilador::compilar((string) $this->input->post('fonte'));
        $this->json($this->resumo($def, $erros, $avisos));
    }

    /** POST: salva o rascunho (mesmo com erros) e devolve a validação. */
    public function salvar($id) {
        $q = $this->obter_ou_404($id, TRUE);
        $fonte = (string) $this->input->post('fonte');
        $this->estr->salvar_fonte($q->id, $fonte);
        list($def, $erros, $avisos) = Estrutura_compilador::compilar($fonte);
        $this->json(['ok' => TRUE, 'salvo_em' => date('H:i:s')] + $this->resumo($def, $erros, $avisos));
    }

    /** POST: publica uma versão a partir do rascunho salvo. */
    public function publicar($id) {
        $q = $this->obter_ou_404($id, TRUE);
        if ($this->input->post('fonte') !== NULL) {
            $this->estr->salvar_fonte($q->id, (string) $this->input->post('fonte'));
        }
        list($versao, $erros, $avisos) = $this->estr->publicar($q->id, (int) $this->session->userdata('admin_id'));
        if ($versao === NULL) {
            return $this->json(['ok' => FALSE, 'mensagem' => 'Corrija os erros antes de publicar.', 'erros' => $erros, 'avisos' => $avisos], 422);
        }
        $this->json(['ok' => TRUE, 'versao' => $versao, 'mensagem' => "Versão $versao publicada."
            . ($q->status === 'active' ? ' Os aplicadores recebem na próxima atualização do app.' : ' Ative o formulário para enviá-lo ao app.')]);
    }

    /** POST: título, descrição, projeto, status e aplicadores. */
    public function configurar($id) {
        $q = $this->obter_ou_404($id);
        $status = (string) $this->input->post('status');
        if ($status === 'active' && !$q->estrutura_versao) {
            $this->session->set_flashdata('error', 'Publique uma versão antes de ativar o formulário.');
            redirect('formularios-estruturados/editar/' . $q->id);
        }
        $todos = $this->input->post('aplicadores_todos') === '1';
        $ids = array_values(array_filter(array_map('intval', (array) $this->input->post('aplicadores'))));
        $this->estr->atualizar_dados($q->id, [
            'title' => trim((string) $this->input->post('title')) ?: $q->title,
            'description' => trim((string) $this->input->post('description')) ?: NULL,
            'project_id' => (int) $this->input->post('project_id') ?: NULL,
            'status' => in_array($status, ['active', 'paused', 'inactive'], TRUE) ? $status : $q->status,
            'aplicadores' => $todos ? NULL : json_encode($ids),
        ]);
        $this->session->set_flashdata('success', 'Configurações salvas.');
        redirect('formularios-estruturados/editar/' . $q->id);
    }

    /** Baixa o texto (rascunho atual ou de uma versão publicada). */
    public function fonte($id, $versao = NULL) {
        $q = $this->obter_ou_404($id);
        $texto = $q->estrutura_fonte;
        if ($versao !== NULL) {
            $v = $this->estr->versao($q->id, (int) $versao);
            if (!$v) { show_404(); }
            $texto = $v->fonte;
        }
        $nome = Estrutura_compilador::slug($q->title) . ($versao !== NULL ? '_v' . (int) $versao : '_rascunho') . '.txt';
        $this->output->set_content_type('text/plain', 'utf-8')
            ->set_header('Content-Disposition: attachment; filename="' . $nome . '"')
            ->set_output($texto);
    }

    // ------------------------------------------------------------------
    // Prévia
    // ------------------------------------------------------------------

    public function previa($id) {
        $q = $this->obter_ou_404($id);
        list($def, $erros) = Estrutura_compilador::compilar((string) $q->estrutura_fonte);
        if ($erros) {
            $this->session->set_flashdata('error', 'O rascunho tem erros: corrija no editor para ver a prévia.');
            redirect('formularios-estruturados/editar/' . $q->id);
        }
        $this->exibir('admin/estruturados/previa', ['title' => 'Prévia: ' . $q->title, 'q' => $q, 'definicao' => $def]);
    }

    /** POST JSON {respostas, moradores}: resultado do motor sobre o rascunho atual. */
    public function avaliar($id) {
        $q = $this->obter_ou_404($id, TRUE);
        list($def, $erros) = Estrutura_compilador::compilar((string) $q->estrutura_fonte);
        if ($erros) { return $this->json(['ok' => FALSE, 'erros' => $erros], 422); }
        $corpo = json_decode(file_get_contents('php://input'), TRUE) ?: [];
        $moradores = [];
        foreach ((array) ($corpo['moradores'] ?? []) as $i => $m) {
            $moradores[] = ['client_id' => (string) ($m['client_id'] ?? 'm' . $i), 'ordem' => $i + 1,
                'nome' => (string) ($m['nome'] ?? ''), 'respostas' => Estrutura_envio::respostas($m['respostas'] ?? [])];
        }
        $motor = new Estrutura_motor($def);
        $this->json(['ok' => TRUE] + $motor->avaliar(Estrutura_envio::respostas($corpo['respostas'] ?? []), $moradores));
    }

    // ------------------------------------------------------------------
    // Entrevistas
    // ------------------------------------------------------------------

    public function entrevistas($id) {
        $q = $this->obter_ou_404($id);
        $this->exibir('admin/estruturados/entrevistas', [
            'title' => 'Entrevistas: ' . $q->title,
            'q' => $q,
            'entrevistas' => $this->estr->entrevistas($q->id),
        ]);
    }

    public function entrevista($fid) {
        $e = $this->estr->entrevista($fid);
        if (!$e) { show_404(); }
        $this->exibir('admin/estruturados/entrevista', ['title' => 'Entrevista #' . (int) $fid] + $e);
    }

    public function exportar($id) {
        $q = $this->obter_ou_404($id);
        $v = $this->estr->versao($q->id);
        if (!$v) {
            $this->session->set_flashdata('error', 'Formulário sem versão publicada.');
            redirect('formularios-estruturados/entrevistas/' . $q->id);
        }
        require_once FCPATH . 'vendor/autoload.php';
        $this->load->library('Estrutura_exportacao');
        $arquivo = $this->estrutura_exportacao->planilha($q, $v->definicao, $this->estr->dados_exportacao($q->id));
        $nome = Estrutura_compilador::slug($q->title) . '_' . date('Y-m-d') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nome . '"');
        header('Content-Length: ' . filesize($arquivo));
        readfile($arquivo);
        @unlink($arquivo);
        exit;
    }

    // ------------------------------------------------------------------
    // Apoio
    // ------------------------------------------------------------------

    private function obter_ou_404($id, $json = FALSE) {
        $q = $this->estr->obter((int) $id);
        if (!$q) {
            if ($json) { $this->json(['ok' => FALSE, 'mensagem' => 'Formulário não encontrado.'], 404); $this->output->_display(); exit; }
            show_404();
        }
        return $q;
    }

    private function resumo($def, $erros, $avisos) {
        $etapas = [];
        $total = 0;
        foreach (($def['etapas'] ?? []) as $e) {
            if ($e['tipo'] === 'moradores') {
                $etapas[] = ['tipo' => 'moradores', 'titulo' => 'Lista de moradores' . ($e['numero'] ? ' (questão ' . $e['numero'] . ')' : ''), 'n' => 0];
                continue;
            }
            $visiveis = array_filter($e['perguntas'], function ($p) { return empty($p['oculta']); });
            $total += count($visiveis);
            $etapas[] = ['tipo' => $e['nivel'], 'titulo' => $e['titulo'], 'n' => count($visiveis),
                'de' => $visiveis ? reset($visiveis)['numero'] : '', 'ate' => $visiveis ? end($visiveis)['numero'] : ''];
        }
        return ['valido' => !$erros, 'erros' => $erros, 'avisos' => $avisos, 'etapas' => $etapas, 'total' => $total,
            'titulo' => $def['titulo'] ?? ''];
    }

    private function modelo($nome) {
        $arq = FCPATH . 'docs/formularios/' . $nome . '.txt';
        return is_file($arq) ? file_get_contents($arq) : '';
    }

    private function fonte_em_branco($titulo) {
        return "# titulo: $titulo\n// Formato: veja a ajuda ao lado.\n\n@domicilio Identificação\n"
            . "1. Primeira pergunta do domicílio? [unica]\n  - Sim\n  - Não\n\n"
            . "@moradores 2. Qual é o nome de cada pessoa que mora no domicílio?\n\n"
            . "@morador Questões sobre cada morador\n3. Qual é a idade de [NOME]? [inteiro]\n";
    }

    private function exibir($view, $data) {
        $this->load->view('admin/header', $data);
        $this->load->view($view, $data);
        $this->load->view('admin/footer');
    }

    private function json($data, $status = 200) {
        $this->output->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
