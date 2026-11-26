<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Registro centralizado de erros (tabela erros_sistema).
 *
 * O mesmo erro (mesma origem, mensagem, arquivo e linha) vira uma linha só,
 * com contador de ocorrências, até alguém marcar como resolvido. Usa uma
 * conexão própria com o banco: o erro pode ter acontecido no meio de uma
 * transação que vai ser desfeita, e o registro não pode sumir junto.
 *
 * Nunca lança exceção nem interrompe a página.
 */
class Monitor_erros {

    private static $ocupado = FALSE;
    private static $conexao = NULL;
    private static $sem_tabela = FALSE;
    private static $por_requisicao = 0;

    public static function registrar($origem, $nivel, $mensagem, $arquivo = NULL, $linha = NULL, $contexto = NULL, $user_id = NULL, $url = NULL) {
        if (self::$ocupado || self::$sem_tabela || self::$por_requisicao >= 20) {
            return FALSE;
        }
        self::$ocupado = TRUE;
        self::$por_requisicao++;
        try {
            $db = self::conexao();
            if (!$db) {
                return FALSE;
            }
            $mensagem = mb_substr(trim((string) $mensagem), 0, 4000);
            if ($mensagem === '') {
                return FALSE;
            }
            $arquivo = $arquivo !== NULL ? mb_substr(str_replace([FCPATH, '\\'], ['', '/'], (string) $arquivo), 0, 300) : NULL;
            // Números e ids mudam de uma ocorrência para outra; a assinatura ignora
            $base = preg_replace('/\d+/', '#', $mensagem);
            $assinatura = sha1($origem . '|' . $base . '|' . $arquivo . '|' . (int) $linha);
            if ($user_id === NULL && function_exists('get_instance')) {
                $CI = get_instance();
                if ($CI && isset($CI->session) && is_object($CI->session)) {
                    $user_id = $CI->session->userdata('admin_id');
                }
            }
            if ($url === NULL && isset($_SERVER['REQUEST_URI'])) {
                $url = (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] . ' ' : '') . $_SERVER['REQUEST_URI'];
            }
            $ctx = $contexto !== NULL ? json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : NULL;
            $sql = 'INSERT INTO erros_sistema (origem, nivel, mensagem, arquivo, linha, url, user_id, contexto, assinatura)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON CONFLICT (assinatura) WHERE resolvido_em IS NULL
                    DO UPDATE SET ocorrencias = erros_sistema.ocorrencias + 1, ultimo_em = CURRENT_TIMESTAMP,
                                  url = EXCLUDED.url, user_id = COALESCE(EXCLUDED.user_id, erros_sistema.user_id),
                                  contexto = COALESCE(EXCLUDED.contexto, erros_sistema.contexto)';
            $ok = $db->query($sql, [mb_substr((string) $origem, 0, 20), mb_substr((string) $nivel, 0, 20), $mensagem, $arquivo,
                $linha !== NULL ? (int) $linha : NULL, $url !== NULL ? mb_substr((string) $url, 0, 500) : NULL,
                $user_id ? (int) $user_id : NULL, $ctx, $assinatura]);
            if (!$ok) {
                $e = $db->error();
                if (stripos((string) ($e['message'] ?? ''), 'erros_sistema') !== FALSE) {
                    self::$sem_tabela = TRUE; // migração ainda não rodou
                }
            }
            return (bool) $ok;
        } catch (Throwable $e) {
            return FALSE;
        } finally {
            self::$ocupado = FALSE;
        }
    }

    /** Origem da requisição atual: api, cli ou painel. */
    public static function origem() {
        if (PHP_SAPI === 'cli') {
            return 'cli';
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return preg_match('#/(index\.php/)?api/#', $uri) ? 'api' : 'painel';
    }

    private static function conexao() {
        if (self::$conexao !== NULL) {
            return self::$conexao ?: NULL;
        }
        self::$conexao = FALSE;
        if (!function_exists('get_instance')) {
            return NULL;
        }
        $CI = get_instance();
        if (!$CI || !isset($CI->load)) {
            return NULL;
        }
        $arq = defined('ENVIRONMENT') && is_file(APPPATH . 'config/' . ENVIRONMENT . '/database.php')
            ? APPPATH . 'config/' . ENVIRONMENT . '/database.php' : APPPATH . 'config/database.php';
        if (!is_file($arq)) {
            return NULL;
        }
        $db = [];
        $active_group = 'default';
        include $arq;
        if (empty($db[$active_group])) {
            return NULL;
        }
        $cfg = $db[$active_group];
        $cfg['db_debug'] = FALSE;
        $cfg['pconnect'] = FALSE;
        $conn = $CI->load->database($cfg, TRUE);
        if (!$conn || !$conn->conn_id) {
            return NULL;
        }
        self::$conexao = $conn;
        return $conn;
    }
}
