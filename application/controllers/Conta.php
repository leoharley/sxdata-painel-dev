<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Totp.php';
require_once APPPATH . 'libraries/Politica_senha.php';

/**
 * Minha conta: troca de senha e verificação em duas etapas (TOTP).
 * Vale para qualquer perfil logado no painel, inclusive cliente.
 */
class Conta extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        if (!$this->session->userdata('admin_logged_in')) {
            redirect('auth/login');
        }
        $this->load->model('User_model');
    }

    public function seguranca() {
        $u = $this->eu();
        $dados = [
            'title' => 'Minha conta - SXData',
            'u' => $u,
            'robustez' => $this->User_model->robustez(),
            'ativo' => $this->ativo($u->totp_ativo ?? FALSE),
            'exigido' => (bool) $this->session->userdata('exigir_2fa'),
            'politica' => Politica_senha::descricao(),
            'novo' => NULL,
        ];
        $pendente = $this->session->userdata('totp_novo');
        if (!$dados['ativo'] && $pendente) {
            $dados['novo'] = ['segredo' => $pendente, 'uri' => Totp::uri($pendente, $u->username)];
        }
        $this->load->view('admin/header', $dados);
        $this->load->view('admin/conta/seguranca', $dados);
        $this->load->view('admin/footer');
    }

    public function senha() {
        $this->so_post();
        $u = $this->eu();
        $atual = (string) $this->input->post('senha_atual');
        $nova = (string) $this->input->post('senha_nova');
        if (!password_verify($atual, $u->password_hash)) {
            auditar('senha_falha', 'usuario', $u->id, 'Senha atual incorreta na troca de senha');
            return $this->voltar('error', 'A senha atual não confere.');
        }
        if ($nova !== (string) $this->input->post('senha_confirma')) {
            return $this->voltar('error', 'A confirmação não é igual à senha nova.');
        }
        $erros = Politica_senha::validar($nova, $u->username, $u->full_name);
        if (password_verify($nova, $u->password_hash)) {
            $erros[] = 'A senha nova precisa ser diferente da atual.';
        }
        if ($erros) {
            return $this->voltar('error', implode(' ', $erros));
        }
        $dados = ['password' => $nova];
        if ($this->User_model->robustez()) {
            $dados['senha_alterada_em'] = date('Y-m-d H:i:s');
        }
        $this->User_model->update($u->id, $dados);
        auditar('senha', 'usuario', $u->id, 'Trocou a própria senha');
        $this->voltar('success', 'Senha alterada.');
    }

    /** Gera o segredo e mostra o QR Code; só vale depois de confirmar um código. */
    public function iniciar_2fa() {
        $this->so_post();
        $this->session->set_userdata('totp_novo', Totp::gerar_segredo());
        redirect('conta/seguranca');
    }

    public function confirmar_2fa() {
        $this->so_post();
        $u = $this->eu();
        $segredo = $this->session->userdata('totp_novo');
        if (!$segredo) {
            return $this->voltar('error', 'Comece de novo: clique em "Ativar".');
        }
        $passo = Totp::verificar($segredo, $this->input->post('codigo'));
        if ($passo === FALSE) {
            return $this->voltar('error', 'Código incorreto. Confira se a hora do celular está automática e tente de novo.');
        }
        $this->User_model->salvar_totp($u->id, $segredo, TRUE, $passo);
        $this->session->unset_userdata(['totp_novo', 'exigir_2fa']);
        auditar('2fa_ativado', 'usuario', $u->id, 'Ativou a verificação em duas etapas');
        $this->voltar('success', 'Verificação em duas etapas ativada. A partir do próximo login, o painel vai pedir o código do aplicativo.');
    }

    public function desativar_2fa() {
        $this->so_post();
        $u = $this->eu();
        if (!password_verify((string) $this->input->post('senha'), $u->password_hash)
            || Totp::verificar($u->totp_secret, $this->input->post('codigo'), $u->totp_ultimo_passo) === FALSE) {
            auditar('2fa_falha', 'usuario', $u->id, 'Tentativa de desativar a verificação em duas etapas');
            return $this->voltar('error', 'Senha ou código incorreto.');
        }
        $this->User_model->salvar_totp($u->id, NULL, FALSE, NULL);
        auditar('2fa_desativado', 'usuario', $u->id, 'Desativou a verificação em duas etapas');
        $this->voltar('success', 'Verificação em duas etapas desativada.');
    }

    private function eu() {
        $u = $this->User_model->get_by_id((int) $this->session->userdata('admin_id'));
        if (!$u) {
            $this->session->sess_destroy();
            redirect('auth/login');
        }
        return $u;
    }

    private function ativo($v) {
        return $v === TRUE || $v === 't' || $v === 1 || $v === '1' || $v === 'true';
    }

    private function so_post() {
        if ($this->input->method() !== 'post') {
            redirect('conta/seguranca');
        }
    }

    private function voltar($tipo, $msg) {
        $this->session->set_flashdata($tipo, html_escape($msg));
        redirect('conta/seguranca');
    }
}
