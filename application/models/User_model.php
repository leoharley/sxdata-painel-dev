<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    public function get_all() {
        if ($this->robustez()) {
            $this->db->where('deleted_at IS NULL', NULL, FALSE);
        }
        $this->db->order_by('created_at', 'DESC');
        return $this->db->get('users')->result();
    }

    public function get_by_id($id) {
        return $this->db->get_where('users', array('id' => $id))->row();
    }

    public function get_by_role($role) {
        $this->db->where('role', $role);
        $this->db->where('is_active', TRUE);
        $this->db->order_by('full_name', 'ASC');
        return $this->db->get('users')->result();
    }

    public function get_aplicadores() {
        $this->db->where('role', 'aplicador');
        $this->db->where('is_active', TRUE);
        $this->db->order_by('full_name', 'ASC');
        return $this->db->get('users')->result();
    }

    /**
     * Usuarios que podem APLICAR questionarios pelo app.
     *
     * Inclui supervisores e administradores, porque a API de coleta
     * (api/Responses.php) aceita respostas desses perfis. Usar apenas
     * get_aplicadores() aqui impedia que eles fossem atribuidos a um
     * questionario, mesmo podendo aplica-lo.
     */
    public function get_aplicadores_para_atribuicao() {
        $this->db->where_in('role', array('aplicador', 'supervisor', 'administrador'));
        $this->db->where('is_active', TRUE);
        $this->db->order_by('full_name', 'ASC');
        return $this->db->get('users')->result();
    }

    public function authenticate($username, $password) {
        $user = $this->db->get_where('users', array(
            'username' => $username,
            'is_active' => TRUE
        ))->row();
        if ($user && !empty($user->deleted_at)) {
            return FALSE;
        }

        if ($user && password_verify($password, $user->password_hash)) {
            // Atualizar último login
            $this->update_last_login($user->id);
            return $user;
        }
        return FALSE;
    }

    public function get_all_with_stats() {
        $this->db->select('
            u.*,
            COALESCE(COUNT(fr.id), 0) as total_responses,
            MAX(fr.completed_at) as last_response_date
        ');
        $this->db->from('users u');
        $this->db->join('form_responses fr', 'u.id = fr.applied_by', 'left');
        if ($this->robustez()) {
            $this->db->where('u.deleted_at IS NULL', NULL, FALSE);
        }
        $this->db->group_by('u.id, u.full_name, u.username, u.email, u.role, u.is_active, u.created_at, u.updated_at, u.password_hash');
        $this->db->order_by('u.created_at', 'DESC');
        
        return $this->db->get()->result();
    }

    public function create($data) {
        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        unset($data['password']);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->db->insert('users', $data) ? $this->db->insert_id() : FALSE;
    }

    public function update($id, $data) {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->where('id', $id);
        return $this->db->update('users', $data);
    }

    public function delete($id) {
        // Exclusão física apaga o "quem aplicou" das entrevistas (FK SET NULL).
        // Com a migração da frente I, vira exclusão lógica, com restauração.
        if ($this->robustez()) {
            return $this->excluir_logico($id, NULL);
        }
        $this->db->where('id', $id);
        return $this->db->delete('users');
    }

    // ------------------------------------------------------------
    // Frente I: bloqueio, 2FA e exclusão lógica (sql/robustez_migration.sql)
    // ------------------------------------------------------------

    /** As colunas novas de users existem? */
    public function robustez() {
        static $tem = NULL;
        if ($tem === NULL) {
            $tem = $this->db->field_exists('deleted_at', 'users') && $this->db->field_exists('totp_ativo', 'users');
        }
        return $tem;
    }

    public function por_username($username) {
        return $this->db->get_where('users', ['username' => (string) $username])->row();
    }

    /** Conta a falha; ao chegar no limite, bloqueia por $minutos. Devolve [falhas, bloqueado_ate]. */
    public function registrar_falha($id, $limite, $minutos = 15) {
        $u = $this->db->select('falhas_login')->get_where('users', ['id' => (int) $id])->row();
        $falhas = ($u ? (int) $u->falhas_login : 0) + 1;
        $dados = ['falhas_login' => $falhas];
        $ate = NULL;
        if ($falhas >= $limite) {
            $ate = date('Y-m-d H:i:s', time() + $minutos * 60);
            $dados['bloqueado_ate'] = $ate;
            $dados['falhas_login'] = 0;
        }
        $this->db->where('id', (int) $id)->update('users', $dados);
        return [$falhas, $ate];
    }

    public function limpar_falhas($id) {
        $this->db->where('id', (int) $id)->update('users', ['falhas_login' => 0, 'bloqueado_ate' => NULL]);
    }

    public function desbloquear($id) {
        $this->limpar_falhas($id);
    }

    public function excluir_logico($id, $por) {
        return $this->db->where('id', (int) $id)->update('users', [
            'deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $por ? (int) $por : NULL,
            'is_active' => FALSE, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Volta para a lista, mas inativo: o administrador decide quando reativar. */
    public function restaurar($id) {
        return $this->db->where('id', (int) $id)->update('users', ['deleted_at' => NULL, 'deleted_by' => NULL, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function excluidos() {
        if (!$this->robustez()) {
            return [];
        }
        return $this->db->select('u.id, u.username, u.full_name, u.role, u.deleted_at, d.full_name AS excluido_por')
            ->from('users u')->join('users d', 'd.id = u.deleted_by', 'left')
            ->where('u.deleted_at IS NOT NULL', NULL, FALSE)->order_by('u.deleted_at', 'DESC')->get()->result();
    }

    public function salvar_totp($id, $segredo, $ativo, $passo = NULL) {
        return $this->db->where('id', (int) $id)->update('users', [
            'totp_secret' => $segredo, 'totp_ativo' => (bool) $ativo, 'totp_ultimo_passo' => $passo,
        ]);
    }

    public function usar_passo_totp($id, $passo) {
        $this->db->where('id', (int) $id)->update('users', ['totp_ultimo_passo' => (int) $passo]);
    }

    public function count_all() {
        return $this->db->count_all('users');
    }

    public function get_stats() {
        $stats = array(
            'total' => 0,
            'aplicadores' => 0,
            'supervisores' => 0,
            'administradores' => 0,
            'clientes' => 0,
            'ativos' => 0,
            'inativos' => 0
        );
        
        // Total geral
        $stats['total'] = $this->db->count_all('users');
        
        // Total por role
        $this->db->select('role, COUNT(*) as count');
        $this->db->group_by('role');
        $roles = $this->db->get('users')->result();
        
        foreach ($roles as $role) {
            switch ($role->role) {
                case 'aplicador':
                    $stats['aplicadores'] = $role->count;
                    break;
                case 'supervisor':
                    $stats['supervisores'] = $role->count;
                    break;
                case 'administrador':
                    $stats['administradores'] = $role->count;
                    break;
                case 'cliente':
                    $stats['clientes'] = $role->count;
                    break;
            }
        }
        
        // Total ativos/inativos
        $this->db->select('is_active, COUNT(*) as count');
        $this->db->group_by('is_active');
        $status_counts = $this->db->get('users')->result();
        
        foreach ($status_counts as $status) {
            if ($status->is_active == 1) {
                $stats['ativos'] = $status->count;
            } else {
                $stats['inativos'] = $status->count;
            }
        }
        
        return $stats;
    }

    public function update_last_login($user_id) {
        $data = array(
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        // Se houver um campo last_login na tabela, descomente a linha abaixo
        // $data['last_login'] = date('Y-m-d H:i:s');
        
        $this->db->where('id', $user_id);
        return $this->db->update('users', $data);
    }

    public function is_username_available($username, $exclude_id = null) {
        $this->db->where('username', $username);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        return $this->db->count_all_results('users') == 0;
    }

    public function is_email_available($email, $exclude_id = null) {
        $this->db->where('email', $email);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        return $this->db->count_all_results('users') == 0;
    }
}