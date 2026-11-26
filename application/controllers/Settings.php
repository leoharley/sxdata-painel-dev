<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Settings extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        $this->load->model('Settings_model');
        $this->check_auth();
    }

    public function index() {
        $data['title'] = 'Configurações - SXData';
        $data['settings'] = $this->Settings_model->get_all_settings();
        $data['system_info'] = $this->get_system_info();
        
        $this->load->view('admin/header', $data);
        $this->load->view('admin/settings/index', $data);
        $this->load->view('admin/footer');
    }

    public function update() {
        $settings = $this->input->post('settings');

        if ($settings) {
            // Caixa desmarcada não vem no POST: sem isto, nunca dava para desligar
            foreach (['require_https', 'enable_2fa', 'auto_backup_photos', 'email_notifications',
                      'notify_new_responses', 'notify_sync_errors', 'auto_retry_failed'] as $caixa) {
                if (!isset($settings[$caixa])) {
                    $settings[$caixa] = '0';
                }
            }
            $antes_cfg = $this->Settings_model->get_all_settings();
            list($a, $d) = auditoria_diferenca(array_intersect_key($antes_cfg, $settings) + array_fill_keys(array_keys($settings), NULL), $settings);
            if ($d) {
                auditar('config', 'configuracao', NULL, 'Configurações alteradas: ' . implode(', ', array_keys($d)), $a, $d);
            }
            foreach ($settings as $key => $value) {
                // Converter MB para bytes para max_file_size
                if ($key === 'max_file_size') {
                    $value = $value * 1048576;
                }
                
                $this->Settings_model->update_setting($key, $value);
            }
            
            $this->session->set_flashdata('success', 'Configurações atualizadas com sucesso!');
        }
        
        redirect('settings');
    }

    public function auto_save() {
        $settings = $this->input->post('settings');
        $success = true;
        
        if ($settings) {
            foreach ($settings as $key => $value) {
                if (!$this->Settings_model->update_setting($key, $value)) {
                    $success = false;
                }
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
    }

    public function backup_database() {
        // Implementar backup do PostgreSQL
        $backup_file = 'sxdata_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backup_path = FCPATH . 'backups/' . $backup_file;
        
        // Criar diretório se não existir
        if (!is_dir(FCPATH . 'backups/')) {
            mkdir(FCPATH . 'backups/', 0755, true);
        }
        
        $db_config = $this->db;
        $command = "pg_dump -h {$db_config->hostname} -U {$db_config->username} -d {$db_config->database} > {$backup_path}";
        
        exec($command, $output, $return_code);
        
        if ($return_code === 0) {
            // Forçar download
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $backup_file . '"');
            header('Content-Length: ' . filesize($backup_path));
            
            readfile($backup_path);
            unlink($backup_path); // Remover arquivo temporário
            exit;
        } else {
            $this->session->set_flashdata('error', 'Erro ao criar backup do banco de dados.');
            redirect('settings');
        }
    }

    private function get_system_info() {
        return array(
            'version' => '1.0.0',
            'uptime' => $this->get_uptime(),
            'db_version' => $this->db->version(),
            'disk_usage' => $this->get_disk_usage(),
            'last_backup' => $this->Settings_model->get_setting('last_backup')
        );
    }

    private function get_uptime() {
        if (function_exists('shell_exec')) {
            $uptime = shell_exec('uptime');
            if ($uptime) {
                return trim($uptime);
            }
        }
        return 'N/A';
    }

    private function get_disk_usage() {
        $total = disk_total_space('.');
        $free = disk_free_space('.');
        $used = $total - $free;
        
        return round($used / 1024 / 1024 / 1024, 2) . ' GB / ' . round($total / 1024 / 1024 / 1024, 2) . ' GB';
    }

    private function check_auth() {
        if (!$this->session->userdata('admin_logged_in')) {
            redirect('auth/login');
        }
        
        // Apenas administradores podem acessar configurações
        if ($this->session->userdata('admin_role') !== 'administrador') {
            show_error('Acesso negado', 403);
        }
    }
}