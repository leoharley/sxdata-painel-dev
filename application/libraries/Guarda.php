<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Carregada em toda requisição (config/autoload.php), antes do construtor do
 * controller. Só age em quem está logado no painel:
 *
 * - expiração por inatividade: Configurações > Timeout de Sessão (minutos);
 * - 2FA obrigatório: com "Autenticação em Duas Etapas" ligada em
 *   Configurações, administrador e supervisor sem 2FA só acessam a tela de
 *   ativação (conta/seguranca) até ativarem.
 *
 * A API do app (api/*) e as páginas públicas não passam por aqui.
 */
class Guarda {

    const LIVRES = ['auth', 'conta'];

    public function __construct() {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $CI =& get_instance();
        $dir = trim((string) $CI->router->directory, '/');
        if ($dir === 'api') {
            return;
        }
        $cookie = config_item('sess_cookie_name') ?: 'ci_session';
        if (empty($_COOKIE[$cookie])) {
            return; // sem sessão: página pública ou login
        }
        $CI->load->library('session');
        if (!$CI->session->userdata('admin_logged_in')) {
            return;
        }
        $classe = strtolower((string) $CI->router->class);
        $cfg = $this->configuracao($CI);

        $agora = time();
        $ultimo = (int) $CI->session->userdata('ultimo_acesso');
        if ($ultimo > 0 && $cfg['timeout'] > 0 && ($agora - $ultimo) > $cfg['timeout'] * 60) {
            $this->sair_por_inatividade($CI);
            return;
        }
        // Requisições automáticas (auto_save, polling) contam como atividade:
        // a página aberta e em uso não deve derrubar a sessão.
        if ($agora - $ultimo >= 30) {
            $CI->session->set_userdata('ultimo_acesso', $agora);
        }

        if ($CI->session->userdata('exigir_2fa') && !in_array($classe, self::LIVRES, TRUE)) {
            if ($CI->input->is_ajax_request()) {
                $CI->output->set_status_header(403);
                echo json_encode(['success' => FALSE, 'message' => 'Ative a verificação em duas etapas para continuar.']);
                exit;
            }
            $CI->session->set_flashdata('error', 'Ative a verificação em duas etapas para continuar usando o painel.');
            redirect('conta/seguranca');
        }
    }

    private function configuracao($CI) {
        $cache = $CI->session->userdata('_cfg_seguranca');
        if (is_array($cache) && ($cache['em'] ?? 0) > time() - 300) {
            return $cache;
        }
        $timeout = 120;
        try {
            if (!isset($CI->db) || !is_object($CI->db)) {
                $CI->load->database();
            }
            $linha = $CI->db->select('setting_value')->get_where('system_settings', ['setting_key' => 'session_timeout'])->row();
            if ($linha && is_numeric($linha->setting_value)) {
                $timeout = max(5, (int) $linha->setting_value);
            }
        } catch (Throwable $e) {
            // sem banco, fica o padrão
        }
        $cache = ['timeout' => $timeout, 'em' => time()];
        $CI->session->set_userdata('_cfg_seguranca', $cache);
        return $cache;
    }

    private function sair_por_inatividade($CI) {
        $CI->load->helper('auditoria');
        auditar('logout', 'usuario', $CI->session->userdata('admin_id'), 'Sessão expirada por inatividade');
        $CI->session->sess_destroy();
        if ($CI->input->is_ajax_request()) {
            $CI->output->set_status_header(401);
            echo json_encode(['success' => FALSE, 'expirou' => TRUE, 'message' => 'Sessão expirada por inatividade. Entre de novo.']);
            exit;
        }
        redirect('auth/login?expirou=1');
    }
}
