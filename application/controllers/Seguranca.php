<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controle e segurança (frente I): fila de revisão, auditoria, lixeira,
 * erros do sistema, aparelhos e versão. Rotas em controle/... (config/routes.php).
 *
 * Quem vê o quê:
 * - administrador: tudo;
 * - supervisor: fila de revisão, aparelhos, erros e versão;
 * - cliente: nada daqui.
 */
class Seguranca extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        if (!$this->session->userdata('admin_logged_in')) {
            redirect('auth/login');
        }
        exigir_equipe();
        $this->load->model('Seguranca_model', 'seg');
        $this->load->model('Lixeira_model', 'lixeira');
        $this->load->model('User_model');
    }

    public function index() {
        $this->tela('index', [
            'title' => 'Controle e segurança - SXData',
            'pronto' => $this->seg->disponivel(),
            'contadores' => $this->seg->disponivel() ? $this->seg->contadores() : [],
            'versao' => $this->versao_atual(),
        ]);
    }

    // ---------------- Fila de revisão ----------------

    public function revisao() {
        $this->exigir_migracao();
        $status = $this->input->get('status') === 'revisada' ? 'revisada' : 'pendente';
        $qid = (int) $this->input->get('q');
        $this->tela('revisao', [
            'title' => 'Fila de revisão - SXData',
            'status' => $status, 'qid' => $qid,
            'lista' => $this->seg->revisao($status, $qid ?: NULL),
            'questionarios' => $this->seg->questionarios_na_revisao(),
        ]);
    }

    public function revisar($id) {
        $this->so_post();
        $nota = trim((string) $this->input->post('nota'));
        if ($this->input->post('reabrir')) {
            $this->seg->reabrir($id);
            auditar('revisao', 'entrevista', $id, 'Voltou para a fila de revisão');
            $this->session->set_flashdata('success', 'Entrevista #' . (int) $id . ' voltou para a fila.');
        } else {
            $this->seg->revisar($id, $this->eu(), mb_substr($nota, 0, 1000));
            auditar('revisao', 'entrevista', $id, 'Marcada como revisada' . ($nota !== '' ? ': ' . $nota : ''));
            $this->session->set_flashdata('success', 'Entrevista #' . (int) $id . ' marcada como revisada.');
        }
        redirect($this->voltar('controle/revisao'));
    }

    // ---------------- Auditoria (administrador) ----------------

    public function auditoria() {
        $this->so_admin();
        $this->exigir_migracao();
        $f = $this->filtros();
        $pagina = max(1, (int) $this->input->get('p'));
        list($linhas, $total) = $this->seg->auditoria($f, $pagina);
        $this->tela('auditoria', [
            'title' => 'Trilha de auditoria - SXData',
            'f' => $f, 'linhas' => $linhas, 'total' => $total, 'pagina' => $pagina, 'por_pagina' => 50,
            'usuarios' => $this->db->select('id, full_name, username')->order_by('full_name')->get('users')->result(),
            'acoes' => $this->seg->acoes_auditadas(), 'entidades' => $this->seg->entidades_auditadas(),
        ]);
    }

    public function auditoria_csv() {
        $this->so_admin();
        $this->exigir_migracao();
        $f = $this->filtros();
        auditar('exportar', 'auditoria', NULL, 'CSV da trilha de auditoria', NULL, $f);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="auditoria_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['id', 'data', 'usuario', 'perfil', 'acao', 'entidade', 'id_entidade', 'descricao', 'antes', 'depois', 'ip', 'navegador'], ';');
        $ultimo = NULL;
        do {
            $lote = $this->seg->auditoria_lote($f, $ultimo);
            foreach ($lote as $l) {
                fputcsv($out, [$l['id'], $l['criado_em'], $l['username'], $l['papel'], $l['acao'], $l['entidade'], $l['entidade_id'],
                    $l['descricao'], $l['antes'], $l['depois'], $l['ip'], $l['user_agent']], ';');
                $ultimo = $l['id'];
            }
        } while (count($lote) === 2000);
        fclose($out);
        exit;
    }

    // ---------------- Lixeira (administrador) ----------------

    public function lixeira() {
        $this->so_admin();
        $this->exigir_migracao();
        $tipo = $this->input->get('tipo');
        $tipo = in_array($tipo, ['entrevista', 'morador', 'questionario', 'projeto'], TRUE) ? $tipo : NULL;
        $this->tela('lixeira', [
            'title' => 'Lixeira - SXData',
            'tipo' => $tipo,
            'itens' => $this->lixeira->listar($tipo),
            'usuarios' => $this->User_model->excluidos(),
        ]);
    }

    public function excluir_entrevista($id) {
        $this->so_admin();
        $this->so_post();
        $this->executar(function () use ($id) {
            $this->lixeira->excluir_entrevista($id, $this->eu(), trim((string) $this->input->post('motivo')));
            return 'Entrevista #' . (int) $id . ' enviada para a lixeira. Ela pode ser restaurada em Controle > Lixeira.';
        }, 'responses');
    }

    public function excluir_morador($id) {
        $this->so_admin();
        $this->so_post();
        $m = $this->db->select('form_response_id')->get_where('moradores', ['id' => (int) $id])->row();
        $this->executar(function () use ($id) {
            $this->lixeira->excluir_morador($id, $this->eu(), trim((string) $this->input->post('motivo')));
            return 'Morador enviado para a lixeira.';
        }, $m ? 'formularios-estruturados/entrevista/' . (int) $m->form_response_id : 'controle/lixeira');
    }

    public function restaurar($id) {
        $this->so_admin();
        $this->so_post();
        $this->executar(function () use ($id) {
            $item = $this->lixeira->restaurar($id, $this->eu());
            return 'Restaurado: ' . $item->descricao;
        }, 'controle/lixeira');
    }

    public function purgar($id) {
        $this->so_admin();
        $this->so_post();
        $this->lixeira->purgar($id);
        $this->session->set_flashdata('success', 'Item removido de vez da lixeira.');
        redirect('controle/lixeira');
    }

    public function excluir_usuario($id) {
        $this->so_admin();
        $this->so_post();
        $id = (int) $id;
        $u = $this->User_model->get_by_id($id);
        if (!$u || $id === $this->eu()) {
            $this->session->set_flashdata('error', $u ? 'Você não pode excluir o seu próprio usuário.' : 'Usuário não encontrado.');
            redirect('users');
        }
        $this->User_model->excluir_logico($id, $this->eu());
        $this->db->where('user_id', $id)->delete('user_tokens'); // o app desse usuário deixa de enviar
        auditar('excluir', 'usuario', $id, 'Usuário ' . $u->username . ' (' . $u->full_name . ') enviado para a lixeira');
        $this->session->set_flashdata('success', 'Usuário ' . html_escape($u->username) . ' excluído. As entrevistas dele continuam no sistema; ele pode ser restaurado em Controle > Lixeira.');
        redirect('users');
    }

    public function restaurar_usuario($id) {
        $this->so_admin();
        $this->so_post();
        $this->User_model->restaurar($id);
        $u = $this->User_model->get_by_id($id);
        auditar('restaurar', 'usuario', $id, 'Usuário ' . ($u ? $u->username : $id) . ' restaurado (inativo)');
        $this->session->set_flashdata('success', 'Usuário restaurado. Ele volta inativo: ative em Usuários quando quiser.');
        redirect('controle/lixeira');
    }

    public function desbloquear_usuario($id) {
        $this->so_admin();
        $this->so_post();
        $this->User_model->desbloquear($id);
        auditar('desbloquear', 'usuario', $id, 'Bloqueio por tentativas removido');
        $this->session->set_flashdata('success', 'Usuário desbloqueado.');
        redirect('users');
    }

    public function remover_2fa($id) {
        $this->so_admin();
        $this->so_post();
        $this->User_model->salvar_totp($id, NULL, FALSE, NULL);
        auditar('2fa_removido', 'usuario', $id, 'Verificação em duas etapas removida pelo administrador (celular perdido ou trocado)');
        $this->session->set_flashdata('success', 'Verificação em duas etapas removida. O usuário pode ativar de novo em Minha conta.');
        redirect('users');
    }

    // ---------------- Erros ----------------

    public function erros() {
        $this->exigir_migracao();
        $abertos = $this->input->get('resolvidos') ? FALSE : TRUE;
        $origem = $this->input->get('origem');
        $origem = in_array($origem, ['painel', 'api', 'app', 'cli'], TRUE) ? $origem : NULL;
        $this->tela('erros', [
            'title' => 'Erros do sistema - SXData',
            'abertos' => $abertos, 'origem' => $origem,
            'lista' => $this->seg->erros($abertos, $origem),
        ]);
    }

    public function resolver_erro($id) {
        $this->so_post();
        if ((int) $id === 0) {
            $this->seg->resolver_todos($this->input->post('origem') ?: NULL, $this->eu());
            $this->session->set_flashdata('success', 'Todos os erros abertos foram marcados como resolvidos.');
        } else {
            $this->seg->resolver_erro($id, $this->eu());
            $this->session->set_flashdata('success', 'Erro marcado como resolvido. Se acontecer de novo, ele volta para a lista.');
        }
        redirect($this->voltar('controle/erros'));
    }

    // ---------------- Aparelhos ----------------

    public function aparelhos() {
        $this->exigir_migracao();
        $this->tela('aparelhos', [
            'title' => 'Aparelhos em campo - SXData',
            'lista' => $this->seg->aparelhos(),
        ]);
    }

    // ---------------- Versão ----------------

    public function versao() {
        $arq = FCPATH . 'docs/NOTAS_VERSAO.md';
        $this->tela('versao', [
            'title' => 'Versão e novidades - SXData',
            'versao' => $this->versao_atual(),
            'notas' => is_file($arq) ? file_get_contents($arq) : '',
        ]);
    }

    // ---------------- Apoio ----------------

    private function versao_atual() {
        $this->config->load('versao', TRUE, TRUE);
        return $this->config->item('versao', 'versao') ?: ['numero' => '-', 'data' => '-'];
    }

    private function filtros() {
        $g = $this->input->get();
        return [
            'user_id' => (int) ($g['user_id'] ?? 0),
            'acao' => preg_replace('/[^a-z0-9_]/', '', (string) ($g['acao'] ?? '')),
            'entidade' => preg_replace('/[^a-z0-9_]/', '', (string) ($g['entidade'] ?? '')),
            'de' => (string) ($g['de'] ?? ''),
            'ate' => (string) ($g['ate'] ?? ''),
            'busca' => mb_substr(trim((string) ($g['busca'] ?? '')), 0, 100),
        ];
    }

    private function executar($fn, $destino) {
        try {
            $this->session->set_flashdata('success', $fn());
        } catch (Exception $e) {
            $this->session->set_flashdata('error', html_escape($e->getMessage()));
        }
        redirect($this->voltar($destino));
    }

    private function voltar($padrao) {
        $v = (string) $this->input->post('voltar');
        return ($v !== '' && preg_match('#^[a-z0-9/_\-?=&.%]+$#i', $v) && strpos($v, '//') === FALSE) ? $v : $padrao;
    }

    private function eu() {
        return (int) $this->session->userdata('admin_id');
    }

    private function so_admin() {
        if ($this->session->userdata('admin_role') !== 'administrador') {
            $this->session->set_flashdata('error', 'Somente administradores.');
            redirect('controle');
        }
    }

    private function so_post() {
        if ($this->input->method() !== 'post') {
            show_error('Use o botão da tela.', 405);
        }
    }

    private function exigir_migracao() {
        if (!$this->seg->disponivel()) {
            $this->session->set_flashdata('error', 'Rode sql/robustez_migration.sql no servidor para usar esta tela.');
            redirect('dashboard');
        }
    }

    private function tela($view, $dados) {
        $this->load->view('admin/header', $dados);
        $this->load->view('admin/seguranca/' . $view, $dados);
        $this->load->view('admin/footer');
    }
}
