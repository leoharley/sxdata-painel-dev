<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class System extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->output->set_content_type('application/json');
    }

    /**
     * Health check endpoint
     * GET /api/health
     */
    public function health() {
        try {
            // Verificar conexão com banco de dados
            $db_status = $this->db->conn_id ? 'connected' : 'disconnected';
            
            // Verificar se tabelas principais existem
            $tables_check = [];
            $required_tables = ['users', 'questionnaires', 'form_responses', 'questions'];
            
            foreach ($required_tables as $table) {
                $tables_check[$table] = $this->db->table_exists($table);
            }
            
            // Status geral
            $all_tables_ok = !in_array(false, $tables_check);
            $status = ($db_status === 'connected' && $all_tables_ok) ? 'healthy' : 'unhealthy';
            
            $response = [
                'status' => $status,
                'timestamp' => date('Y-m-d H:i:s'),
                'database' => $db_status,
                'tables' => $tables_check,
                'version' => '1.0.0',
                'environment' => ENVIRONMENT
            ];
            
            $status_code = ($status === 'healthy') ? 200 : 503;
            
            $this->output
                ->set_status_header($status_code)
                ->set_output(json_encode($response, JSON_UNESCAPED_UNICODE));
                
        } catch (Exception $e) {
            $this->output
                ->set_status_header(503)
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'Health check failed',
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * POST api/system/erro (com token): o app informa um erro dele.
     * {mensagem, nivel?, tela?, app_versao?, plataforma?, device_id?, detalhes?}
     * Fica em Controle > Erros do sistema, agrupado pela mesma mensagem e tela.
     */
    public function erro() {
        $uid = $this->autenticado();
        if (!$uid) {
            return;
        }
        $j = $this->corpo();
        $msg = isset($j['mensagem']) && is_scalar($j['mensagem']) ? trim((string) $j['mensagem']) : '';
        if ($msg === '') {
            return $this->json(400, ['success' => FALSE, 'message' => 'mensagem é obrigatória']);
        }
        require_once APPPATH . 'libraries/Monitor_erros.php';
        $ctx = [];
        foreach (['app_versao', 'plataforma', 'device_id', 'detalhes', 'pilha'] as $k) {
            if (isset($j[$k])) {
                $ctx[$k] = is_scalar($j[$k]) ? mb_substr((string) $j[$k], 0, 4000) : $j[$k];
            }
        }
        $tela = isset($j['tela']) && is_scalar($j['tela']) ? mb_substr((string) $j['tela'], 0, 200) : NULL;
        $nivel = isset($j['nivel']) && in_array($j['nivel'], ['erro', 'aviso', 'fatal'], TRUE) ? $j['nivel'] : 'erro';
        $ok = Monitor_erros::registrar('app', $nivel, mb_substr($msg, 0, 2000), $tela, NULL, $ctx ?: NULL, $uid, $tela);
        $this->json(200, ['success' => TRUE, 'registrado' => $ok]);
    }

    /**
     * POST api/system/dispositivo (com token): situação do aparelho.
     * {device_id, app_versao?, plataforma?, pendentes, pendentes_midias?, rascunhos?, ultimo_envio_em?}
     * Aparece em Controle > Aparelhos em campo.
     */
    public function dispositivo() {
        $uid = $this->autenticado();
        if (!$uid) {
            return;
        }
        $j = $this->corpo();
        $dev = isset($j['device_id']) && is_scalar($j['device_id']) ? preg_replace('/[^A-Za-z0-9._:\-]/', '', (string) $j['device_id']) : '';
        if ($dev === '' || strlen($dev) > 100) {
            return $this->json(400, ['success' => FALSE, 'message' => 'device_id é obrigatório']);
        }
        $int = function ($k) use ($j) { return isset($j[$k]) && is_numeric($j[$k]) ? max(0, min(1000000, (int) $j[$k])) : 0; };
        $txt = function ($k, $n) use ($j) { return isset($j[$k]) && is_scalar($j[$k]) && trim((string) $j[$k]) !== '' ? mb_substr(trim((string) $j[$k]), 0, $n) : NULL; };
        $envio = $txt('ultimo_envio_em', 40);
        $dados = [
            'device_id' => $dev, 'app_versao' => $txt('app_versao', 40), 'plataforma' => $txt('plataforma', 40),
            'pendentes' => $int('pendentes'), 'pendentes_midias' => $int('pendentes_midias'), 'rascunhos' => $int('rascunhos'),
            'ultimo_envio_em' => $envio && strtotime($envio) ? date('Y-m-d H:i:s', strtotime($envio)) : NULL,
        ];
        $this->load->model('Seguranca_model', 'seg');
        if (!$this->db->table_exists('dispositivos')) {
            return $this->json(503, ['success' => FALSE, 'message' => 'Rode sql/robustez_migration.sql no servidor.']);
        }
        $id = $this->seg->registrar_aparelho($uid, $dados, $this->input->ip_address());
        $this->json(200, ['success' => TRUE, 'id' => $id, 'servidor_em' => date('Y-m-d H:i:s')]);
    }

    private function autenticado() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        if ($this->input->method() === 'options') {
            exit();
        }
        if ($this->input->method() !== 'post') {
            $this->json(405, ['success' => FALSE, 'message' => 'Method not allowed']);
            return FALSE;
        }
        $this->load->library('api_token');
        $uid = $this->api_token->user_id_from_request();
        if (!$uid) {
            $this->json(401, ['success' => FALSE, 'message' => 'Invalid token']);
            return FALSE;
        }
        return (int) $uid;
    }

    private function corpo() {
        $raw = file_get_contents('php://input');
        if (strlen($raw) > 65536) {
            return [];
        }
        $j = json_decode($raw, TRUE);
        return is_array($j) ? $j : [];
    }

    private function json($status, $dados) {
        $this->output->set_status_header($status)->set_output(json_encode($dados, JSON_UNESCAPED_UNICODE));
    }
}