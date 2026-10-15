<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Api_token - token de autenticacao do app de coleta.
 *
 * Formato: v2.<payload base64url>.<assinatura HMAC-SHA256 base64url>
 *
 * O token anterior era apenas base64(json), sem assinatura: qualquer pessoa
 * conseguia montar um token com o user_id de um administrador. Tokens nesse
 * formato antigo sao recusados; o aplicador faz login uma vez e recebe um
 * token novo.
 *
 * Validade de 28 dias, com renovacao deslizante em GET /api/auth/verify: o app
 * de coleta trabalha offline e so sincroniza de vez em quando, e um prazo
 * curto (era 24h) fazia a sincronizacao falhar ate o aplicador relogar.
 */
class Api_token {

    const TTL_SECONDS = 2419200; // 28 dias
    const PREFIX = 'v2';

    /** @var CI_Controller */
    private $CI;

    /** @var string|null */
    private $secret = null;

    /** @var array user_id => bool, para nao consultar o banco duas vezes na mesma requisicao */
    private $active_cache = array();

    public function __construct() {
        $this->CI =& get_instance();
    }

    /**
     * Emite um token novo com prazo cheio.
     */
    public function issue($user_id) {
        $now = time();
        $payload = json_encode(array(
            'user_id'    => (int) $user_id,
            'issued_at'  => $now,
            'expires_at' => $now + self::TTL_SECONDS,
        ));

        $body = self::PREFIX . '.' . $this->b64url_encode($payload);

        return $body . '.' . $this->b64url_encode($this->sign($body));
    }

    /**
     * Valida o token e devolve o user_id, ou FALSE.
     *
     * Recusa: formato antigo, assinatura invalida, prazo vencido e usuario
     * inexistente ou desativado.
     */
    public function verify($token) {
        if (!is_string($token) || $token === '') {
            return FALSE;
        }

        $parts = explode('.', trim($token));
        if (count($parts) !== 3 || $parts[0] !== self::PREFIX) {
            return FALSE;
        }

        $body = $parts[0] . '.' . $parts[1];
        $signature = $this->b64url_decode($parts[2]);

        if ($signature === FALSE || !hash_equals($this->sign($body), $signature)) {
            return FALSE;
        }

        $payload = json_decode((string) $this->b64url_decode($parts[1]), TRUE);

        if (!is_array($payload)
            || empty($payload['user_id'])
            || empty($payload['expires_at'])
            || time() > (int) $payload['expires_at']) {
            return FALSE;
        }

        $user_id = (int) $payload['user_id'];

        return $this->is_active_user($user_id) ? $user_id : FALSE;
    }

    /**
     * Le "Authorization: Bearer <token>" da requisicao atual e valida.
     */
    public function user_id_from_request() {
        // Sem filtro XSS: ele pode alterar caracteres do token
        $header = $this->CI->input->get_request_header('Authorization', FALSE);

        // Alguns servidores (CGI/FastCGI) nao repassam o cabecalho Authorization
        if (!$header && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (!$header || stripos($header, 'Bearer ') !== 0) {
            return FALSE;
        }

        return $this->verify(substr($header, 7));
    }

    /**
     * Assinatura HMAC de um texto qualquer, com o mesmo segredo dos tokens.
     * Usada pelo formulário público para garantir que o envio veio da
     * página gerada pelo servidor.
     */
    public function signature($data) {
        return $this->b64url_encode($this->sign((string) $data));
    }

    // ------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------

    private function is_active_user($user_id) {
        if (!isset($this->active_cache[$user_id])) {
            // Audios e Photos nao carregam model nenhum: sem isto $this->CI->db
            // e nulo e todo upload com token valido morre com erro 500
            if (!isset($this->CI->db)) {
                $this->CI->load->database();
            }

            $row = $this->CI->db->select('is_active')
                                ->where('id', $user_id)
                                ->get('users')
                                ->row();

            $this->active_cache[$user_id] = $row && $this->truthy($row->is_active);
        }

        return $this->active_cache[$user_id];
    }

    /**
     * PostgreSQL devolve booleanos como 't'/'f'.
     */
    private function truthy($value) {
        return $value === TRUE || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }

    private function sign($data) {
        return hash_hmac('sha256', $data, $this->secret(), TRUE);
    }

    /**
     * Segredo da assinatura, nesta ordem:
     *   1. variavel de ambiente API_TOKEN_SECRET (.env)
     *   2. segredo gerado automaticamente e guardado em application/cache
     *
     * A opcao 2 dispensa configuracao: o arquivo fica fora do git (cache/ e
     * ignorado), entao sobrevive aos deploys. Se ele for apagado, os tokens
     * em uso deixam de valer e os aplicadores precisam logar de novo.
     */
    private function secret() {
        if ($this->secret !== NULL) {
            return $this->secret;
        }

        $env = getenv('API_TOKEN_SECRET');
        if (is_string($env) && strlen($env) >= 32) {
            return $this->secret = $env;
        }

        $file = APPPATH . 'cache/api_token_secret.php';

        if (is_file($file)) {
            $stored = include $file;
            if (is_string($stored) && strlen($stored) >= 32) {
                return $this->secret = $stored;
            }
        }

        $generated = bin2hex(random_bytes(32));
        $content = "<?php defined('BASEPATH') OR exit('No direct script access allowed');\n"
                 . "return '" . $generated . "';\n";

        if (@file_put_contents($file, $content, LOCK_EX) === FALSE) {
            log_message('error', 'Api_token: nao foi possivel gravar ' . $file
                . '. Defina API_TOKEN_SECRET no .env (64 caracteres aleatorios).');
            show_error('Configuracao de autenticacao indisponivel.', 500);
        }

        @chmod($file, 0600);

        // Outra requisicao pode ter gravado ao mesmo tempo: vale o que ficou no disco
        $stored = include $file;

        return $this->secret = (is_string($stored) && strlen($stored) >= 32) ? $stored : $generated;
    }

    private function b64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function b64url_decode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), TRUE);
    }
}
