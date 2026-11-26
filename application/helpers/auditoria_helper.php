<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Trilha de auditoria (tabela audit_log, sql/robustez_migration.sql).
 *
 *   auditar('exportar', 'questionario', 12, 'Excel das respostas');
 *   auditar('editar', 'usuario', 5, 'Perfil alterado', $antes, $depois);
 *
 * Nunca interrompe a operação: sem a tabela, ou com erro ao gravar, só
 * registra no log do PHP. Senhas e segredos são retirados de antes/depois.
 */

if (!function_exists('auditar')) {
    function auditar($acao, $entidade = NULL, $entidade_id = NULL, $descricao = NULL, $antes = NULL, $depois = NULL, $usuario = NULL) {
        static $disponivel = NULL;
        $CI =& get_instance();
        if (!$CI) {
            return FALSE;
        }
        try {
            if (!isset($CI->db) || !is_object($CI->db)) {
                $CI->load->database();
            }
            if ($disponivel === NULL) {
                $disponivel = $CI->db->table_exists('audit_log');
            }
            if (!$disponivel) {
                return FALSE;
            }
            $sessao = isset($CI->session) ? $CI->session : NULL;
            $uid = $usuario['id'] ?? ($sessao ? $sessao->userdata('admin_id') : NULL);
            $linha = [
                'user_id'     => $uid ? (int) $uid : NULL,
                'username'    => mb_substr((string) ($usuario['username'] ?? ($sessao ? $sessao->userdata('admin_username') : '')), 0, 100) ?: NULL,
                'papel'       => $usuario['role'] ?? ($sessao ? $sessao->userdata('admin_role') : NULL),
                'acao'        => mb_substr((string) $acao, 0, 40),
                'entidade'    => $entidade !== NULL ? mb_substr((string) $entidade, 0, 40) : NULL,
                'entidade_id' => $entidade_id !== NULL ? mb_substr((string) $entidade_id, 0, 60) : NULL,
                'descricao'   => $descricao,
                'antes'       => $antes !== NULL ? json_encode(auditoria_limpar($antes), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : NULL,
                'depois'      => $depois !== NULL ? json_encode(auditoria_limpar($depois), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : NULL,
                'ip'          => mb_substr((string) $CI->input->ip_address(), 0, 64),
                'user_agent'  => mb_substr((string) $CI->input->user_agent(), 0, 300) ?: NULL,
            ];
            $debug = $CI->db->db_debug;
            $CI->db->db_debug = FALSE;
            $ok = $CI->db->insert('audit_log', $linha);
            $CI->db->db_debug = $debug;
            return (bool) $ok;
        } catch (Throwable $e) {
            log_message('error', 'auditar(' . $acao . '): ' . $e->getMessage());
            return FALSE;
        }
    }
}

if (!function_exists('auditoria_limpar')) {
    /** Tira senha, hash, token e segredo 2FA do que vai para a trilha. */
    function auditoria_limpar($dados) {
        if (is_object($dados)) {
            $dados = (array) $dados;
        }
        if (!is_array($dados)) {
            return $dados;
        }
        $fora = ['password', 'password_hash', 'senha', 'confirm_password', 'token', 'totp_secret', 'api_key', 'openai_api_key', 'anthropic_api_key', 'chave'];
        foreach ($dados as $k => $v) {
            if (is_string($k) && in_array(strtolower($k), $fora, TRUE)) {
                $dados[$k] = '***';
            } elseif (is_array($v) || is_object($v)) {
                $dados[$k] = auditoria_limpar($v);
            }
        }
        return $dados;
    }
}

if (!function_exists('contadores_controle')) {
    /**
     * Pendências para o menu (fila de revisão, erros abertos, aparelhos com
     * fila parada). Guardado na sessão por 2 minutos: não consulta o banco a
     * cada página.
     */
    function contadores_controle() {
        $CI =& get_instance();
        if (!isset($CI->session)) {
            return [];
        }
        $c = $CI->session->userdata('_contadores_controle');
        if (is_array($c) && ($c['em'] ?? 0) > time() - 120) {
            return $c;
        }
        try {
            $CI->load->model('Seguranca_model');
            $c = $CI->Seguranca_model->disponivel() ? $CI->Seguranca_model->contadores() : [];
        } catch (Throwable $e) {
            $c = [];
        }
        $c['em'] = time();
        $CI->session->set_userdata('_contadores_controle', $c);
        return $c;
    }
}

if (!function_exists('auditoria_diferenca')) {
    /** Só os campos que mudaram, para gravar antes/depois enxutos. */
    function auditoria_diferenca($antes, $depois) {
        $antes = (array) $antes;
        $depois = (array) $depois;
        $a = [];
        $d = [];
        foreach ($depois as $k => $v) {
            $velho = $antes[$k] ?? NULL;
            if ((string) (is_scalar($velho) || $velho === NULL ? $velho : json_encode($velho)) !== (string) (is_scalar($v) || $v === NULL ? $v : json_encode($v))) {
                $a[$k] = $velho;
                $d[$k] = $v;
            }
        }
        return [$a, $d];
    }
}
