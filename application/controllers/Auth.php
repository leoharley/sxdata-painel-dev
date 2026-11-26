<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Totp.php';

/**
 * Login do painel.
 *
 * Frente I (sql/robustez_migration.sql):
 * - bloqueio por tentativas: Configurações > Tentativas Máximas de Login
 *   (padrão 5). Ao chegar no limite, o usuário fica bloqueado 15 minutos;
 *   um administrador pode desbloquear antes, na tela de usuários;
 * - verificação em duas etapas (TOTP) para quem ativou em "Minha conta";
 * - com "Autenticação em Duas Etapas" ligada em Configurações, administrador
 *   e supervisor sem 2FA entram, mas só usam o painel depois de ativar;
 * - id de sessão novo a cada login e trilha de auditoria.
 *
 * Sem a migração, o login funciona como antes.
 */
class Auth extends CI_Controller {

    const MINUTOS_BLOQUEIO = 15;
    const VALIDADE_2FA = 300; // segundos para digitar o código
    const TENTATIVAS_2FA = 5;

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        $this->load->model('User_model');
        $this->load->model('Settings_model');
        $this->load->library('form_validation');
    }

    public function login() {
        if ($this->session->userdata('admin_logged_in')) {
            redirect($this->session->userdata('admin_role') === 'cliente' ? 'client/dashboard' : 'dashboard');
        }
        $data = [];
        if ($this->input->get('expirou')) {
            $data['info'] = 'Sua sessão expirou por inatividade. Entre de novo.';
        }

        if ($this->input->post()) {
            $this->form_validation->set_rules('username', 'Usuário', 'required');
            $this->form_validation->set_rules('password', 'Senha', 'required');

            if ($this->form_validation->run()) {
                $username = trim((string) $this->input->post('username'));
                $password = (string) $this->input->post('password');
                $robustez = $this->User_model->robustez();
                $conta = $robustez ? $this->User_model->por_username($username) : NULL;

                if ($conta && !empty($conta->bloqueado_ate) && strtotime($conta->bloqueado_ate) > time()) {
                    auditar('login_bloqueado', 'usuario', $conta->id, 'Tentativa durante o bloqueio', NULL, NULL, (array) $conta);
                    $data['error'] = 'Usuário bloqueado por excesso de tentativas até ' . date('H:i', strtotime($conta->bloqueado_ate))
                        . '. Aguarde ou peça a um administrador para desbloquear.';
                    return $this->tela($data);
                }

                $user = $this->User_model->authenticate($username, $password);

                if (!$user) {
                    $data['error'] = 'Credenciais inválidas ou sem permissão de acesso.';
                    if ($conta) {
                        list(, $ate) = $this->User_model->registrar_falha($conta->id, $this->limite_tentativas(), self::MINUTOS_BLOQUEIO);
                        if ($ate) {
                            auditar('login_bloqueado', 'usuario', $conta->id, 'Bloqueado após ' . $this->limite_tentativas() . ' tentativas', NULL, NULL, (array) $conta);
                            $data['error'] = 'Muitas tentativas sem sucesso. O usuário ficou bloqueado por ' . self::MINUTOS_BLOQUEIO . ' minutos.';
                        } else {
                            auditar('login_falha', 'usuario', $conta->id, 'Senha incorreta', NULL, NULL, (array) $conta);
                        }
                    } else {
                        auditar('login_falha', 'usuario', NULL, 'Usuário inexistente ou inativo: ' . mb_substr($username, 0, 60), NULL, NULL, ['username' => $username]);
                    }
                    return $this->tela($data);
                }

                if (!in_array($user->role, ['administrador', 'supervisor', 'cliente'], TRUE)) {
                    auditar('login_negado', 'usuario', $user->id, 'Perfil sem acesso ao painel: ' . $user->role, NULL, NULL, (array) $user);
                    $data['error'] = 'Credenciais inválidas ou sem permissão de acesso.';
                    return $this->tela($data);
                }

                if ($robustez) {
                    $this->User_model->limpar_falhas($user->id);
                }

                if ($robustez && $this->ativo($user->totp_ativo ?? FALSE) && !empty($user->totp_secret)) {
                    $this->session->sess_regenerate(TRUE);
                    $this->session->set_userdata('pre_2fa', ['id' => (int) $user->id, 'em' => time(), 'tentativas' => 0]);
                    $data['etapa_2fa'] = TRUE;
                    return $this->tela($data);
                }

                return $this->completar_login($user, FALSE);
            }
        }

        $this->tela($data);
    }

    /** Segunda etapa: código do aplicativo autenticador. */
    public function dois_fatores() {
        $pre = $this->session->userdata('pre_2fa');
        if (!is_array($pre) || (time() - (int) $pre['em']) > self::VALIDADE_2FA) {
            $this->session->unset_userdata('pre_2fa');
            return $this->tela(['error' => 'O tempo para digitar o código acabou. Entre de novo.']);
        }
        if ($this->input->method() !== 'post') {
            return $this->tela(['etapa_2fa' => TRUE]);
        }
        $user = $this->User_model->get_by_id($pre['id']);
        if (!$user || !$this->ativo($user->is_active) || !empty($user->deleted_at)) {
            $this->session->unset_userdata('pre_2fa');
            return $this->tela(['error' => 'Credenciais inválidas ou sem permissão de acesso.']);
        }
        $passo = Totp::verificar($user->totp_secret, $this->input->post('codigo'), $user->totp_ultimo_passo);
        if ($passo === FALSE) {
            $pre['tentativas']++;
            auditar('2fa_falha', 'usuario', $user->id, 'Código incorreto', NULL, NULL, (array) $user);
            if ($pre['tentativas'] >= self::TENTATIVAS_2FA) {
                $this->session->unset_userdata('pre_2fa');
                $this->User_model->registrar_falha($user->id, $this->limite_tentativas(), self::MINUTOS_BLOQUEIO);
                return $this->tela(['error' => 'Código incorreto muitas vezes. Entre de novo.']);
            }
            $this->session->set_userdata('pre_2fa', $pre);
            return $this->tela(['etapa_2fa' => TRUE, 'error' => 'Código incorreto. Confira a hora do celular e tente de novo.']);
        }
        $this->User_model->usar_passo_totp($user->id, $passo);
        $this->session->unset_userdata('pre_2fa');
        $this->completar_login($user, TRUE);
    }

    public function logout() {
        if ($this->session->userdata('admin_logged_in')) {
            auditar('logout', 'usuario', $this->session->userdata('admin_id'), 'Saiu do painel');
        }
        $this->session->sess_destroy();
        redirect('auth/login');
    }

    private function completar_login($user, $com_2fa) {
        // Id de sessão novo: impede fixação de sessão
        $this->session->sess_regenerate(TRUE);
        $this->session->set_userdata([
            'admin_logged_in' => TRUE,
            'admin_id'        => $user->id,
            'admin_username'  => $user->username,
            'admin_name'      => $user->full_name,
            'admin_role'      => $user->role,
            'ultimo_acesso'   => time(),
        ]);
        $exigir = $this->User_model->robustez()
            && in_array($user->role, ['administrador', 'supervisor'], TRUE)
            && !$this->ativo($user->totp_ativo ?? FALSE)
            && (string) $this->Settings_model->get_setting('enable_2fa') === '1';
        if ($exigir) {
            $this->session->set_userdata('exigir_2fa', TRUE);
        }
        auditar('login', 'usuario', $user->id, $com_2fa ? 'Entrou no painel (com verificação em duas etapas)' : 'Entrou no painel');
        if ($exigir) {
            $this->session->set_flashdata('error', 'A verificação em duas etapas é obrigatória para o seu perfil. Ative para continuar.');
            redirect('conta/seguranca');
        }
        redirect($user->role === 'cliente' ? 'client/dashboard' : 'dashboard');
    }

    private function limite_tentativas() {
        $v = $this->Settings_model->get_setting('max_login_attempts');
        return is_numeric($v) && (int) $v >= 3 ? (int) $v : 5;
    }

    private function ativo($v) {
        return $v === TRUE || $v === 't' || $v === 1 || $v === '1' || $v === 'true';
    }

    private function tela($data) {
        $data['title'] = 'Login - SXData Admin';
        $this->load->view('admin/login', $data);
    }
}
