<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Politica_senha.php';

class Users extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        $this->load->model('User_model');
        $this->load->library('form_validation');
        $this->check_auth();
    }

    public function index() {
        $data['title'] = 'Usuários - SXData';
        $data['users'] = $this->User_model->get_all_with_stats();
        $data['stats'] = $this->User_model->get_stats();
        
        $this->load->view('admin/header', $data);
        $this->load->view('admin/users/index', $data);
        $this->load->view('admin/footer');
    }

    public function create() {
        $this->form_validation->set_rules('full_name', 'Nome Completo', 'required|max_length[100]');
        $this->form_validation->set_rules('username', 'Usuário', 'required|max_length[50]|is_unique[users.username]');
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email|is_unique[users.email]');
        $this->form_validation->set_rules('role', 'Função', 'required|in_list[aplicador,supervisor,administrador,cliente]');
        $this->form_validation->set_rules('password', 'Senha', 'required');
        $this->form_validation->set_rules('password_confirm', 'Confirmação de Senha', 'required|matches[password]');

        $erros_senha = Politica_senha::validar($this->input->post('password'), $this->input->post('username'), $this->input->post('full_name'));
        if (!$this->pode_dar_papel($this->input->post('role'))) {
            $this->session->set_flashdata('error', 'Somente um administrador pode criar outro administrador.');
            redirect('users');
        }
        if ($erros_senha) {
            $this->session->set_flashdata('error', html_escape(implode(' ', $erros_senha)));
            redirect('users');
        }

        if ($this->form_validation->run()) {
            $user_data = array(
                'full_name' => $this->input->post('full_name'),
                'username' => $this->input->post('username'),
                'email' => $this->input->post('email'),
                'role' => $this->input->post('role'),
                'password' => $this->input->post('password'),
                'is_active' => TRUE
            );

            if ($novo_id = $this->User_model->create($user_data)) {
                auditar('criar', 'usuario', $novo_id, 'Usuário ' . $user_data['username'] . ' (' . $user_data['role'] . ')', NULL, $user_data);
                $this->session->set_flashdata('success', 'Usuário criado com sucesso!');
            } else {
                $this->session->set_flashdata('error', 'Erro ao criar usuário.');
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
        }

        redirect('users');
    }

    public function get_user($id) {
        // Método para buscar dados do usuário via AJAX
        $user = $this->User_model->get_by_id($id);
        
        if ($user) {
            // Remove dados sensíveis antes de enviar
            unset($user->password_hash);
            
            $response = array(
                'success' => true,
                'user' => $user
            );
        } else {
            $response = array(
                'success' => false,
                'message' => 'Usuário não encontrado'
            );
        }
        
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }

    public function edit($id) {
        // Verificar se o usuário existe
        $existing_user = $this->User_model->get_by_id($id);
        if (!$existing_user) {
            $this->session->set_flashdata('error', 'Usuário não encontrado.');
            redirect('users');
        }

        // Configurar regras de validação
        $this->form_validation->set_rules('full_name', 'Nome Completo', 'required|max_length[100]');
        
        // Username deve ser único, mas pode ser o mesmo do usuário atual
        $username_rule = 'required|max_length[50]';
        if ($this->input->post('username') !== $existing_user->username) {
            $username_rule .= '|is_unique[users.username]';
        }
        $this->form_validation->set_rules('username', 'Usuário', $username_rule);
        
        // Email deve ser único, mas pode ser o mesmo do usuário atual
        $email_rule = 'required|valid_email';
        if ($this->input->post('email') !== $existing_user->email) {
            $email_rule .= '|is_unique[users.email]';
        }
        $this->form_validation->set_rules('email', 'Email', $email_rule);
        
        $this->form_validation->set_rules('role', 'Função', 'required|in_list[aplicador,supervisor,administrador,cliente]');
        
        // Supervisor não altera administrador nem promove alguém a administrador
        if (!$this->pode_mexer($existing_user) || !$this->pode_dar_papel($this->input->post('role'))) {
            $this->session->set_flashdata('error', 'Somente um administrador pode alterar administradores.');
            redirect('users');
        }

        // Senha é opcional na edição
        if ($this->input->post('password')) {
            $this->form_validation->set_rules('password_confirm', 'Confirmação da Nova Senha', 'matches[password]');
            $erros_senha = Politica_senha::validar($this->input->post('password'), $this->input->post('username'), $this->input->post('full_name'));
            if ($erros_senha) {
                $this->session->set_flashdata('error', html_escape(implode(' ', $erros_senha)));
                redirect('users');
            }
        }

        if ($this->form_validation->run()) {
            $user_data = array(
                'full_name' => $this->input->post('full_name'),
                'username' => $this->input->post('username'),
                'email' => $this->input->post('email'),
                'role' => $this->input->post('role')
            );

            // Adicionar senha apenas se uma nova foi fornecida
            if ($this->input->post('password')) {
                $user_data['password'] = $this->input->post('password');
            }

            if ($this->User_model->update($id, $user_data)) {
                list($antes, $depois) = auditoria_diferenca((array) $existing_user, $user_data);
                auditar('editar', 'usuario', $id, 'Usuário ' . $existing_user->username . (isset($user_data['password']) ? ' (com senha nova)' : ''), $antes, $depois);
                $this->session->set_flashdata('success', 'Usuário atualizado com sucesso!');
            } else {
                $this->session->set_flashdata('error', 'Erro ao atualizar usuário.');
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
        }

        redirect('users');
    }

    public function toggle_status($id, $status) {
        $new_status = ($status === '1' || $status === 'true' || $status === 1) ? TRUE : FALSE;
        
        $user = $this->User_model->get_by_id($id);
        if (!$user) {
            $this->session->set_flashdata('error', 'Usuário não encontrado.');
            redirect('users');
            return;
        }
        
        $current_user_id = $this->session->userdata('admin_id') ?? $this->session->userdata('user_id');
        if ($id == $current_user_id) {
            $this->session->set_flashdata('error', 'Você não pode alterar seu próprio status.');
            redirect('users');
            return;
        }
        
        if (!$this->pode_mexer($user)) {
            $this->session->set_flashdata('error', 'Somente um administrador pode alterar administradores.');
            redirect('users');
            return;
        }

        if ($this->User_model->update($id, array('is_active' => $new_status))) {
            $action = $new_status ? 'ativado' : 'desativado';
            auditar('editar', 'usuario', $id, 'Usuário ' . $user->username . ' ' . $action, ['is_active' => $user->is_active], ['is_active' => $new_status]);
            $this->session->set_flashdata('success', "Usuário {$action} com sucesso!");
        } else {
            $this->session->set_flashdata('error', 'Erro ao alterar status do usuário.');
        }

        redirect('users');
    }

    public function reset_password($id) {
        $user = $this->User_model->get_by_id($id);
        if (!$user || !$this->pode_mexer($user)) {
            $this->session->set_flashdata('error', $user ? 'Somente um administrador pode redefinir a senha de um administrador.' : 'Usuário não encontrado.');
            redirect('users');
        }
        $new_password = $this->generate_password();

        if ($this->User_model->update($id, array('password' => $new_password))) {
            auditar('senha', 'usuario', $id, 'Senha redefinida para o usuário ' . $user->username);

            // Aqui você pode implementar o envio por email
            $this->session->set_flashdata('success', "Senha redefinida! Nova senha: {$new_password}");
        } else {
            $this->session->set_flashdata('error', 'Erro ao redefinir senha.');
        }

        redirect('users');
    }

    private function generate_password($length = 10) {
        // Já cumpre a política de senha (letras e números)
        return Politica_senha::gerar($length);
    }

    /** Supervisor não mexe em administrador. */
    private function pode_mexer($user) {
        return $this->session->userdata('admin_role') === 'administrador' || $user->role !== 'administrador';
    }

    private function pode_dar_papel($papel) {
        return $this->session->userdata('admin_role') === 'administrador' || $papel !== 'administrador';
    }

    private function check_auth() {
        if (!$this->session->userdata('admin_logged_in')) {
            redirect('auth/login');
        }
        
        // Verificar se é administrador ou supervisor
        $role = $this->session->userdata('admin_role');
        if (!in_array($role, ['administrador', 'supervisor'])) {
            show_error('Acesso negado', 403);
        }
    }
}