<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Ia_provedores.php';
require_once APPPATH . 'libraries/Anonimizador.php';

/**
 * Ai_service - chamadas de IA do painel e da API do app.
 *
 * Frente K (sql/ia_provedores_migration.sql):
 * - provedor por função: OpenAI ou Anthropic (Claude), com modelo de
 *   alternativa quando o principal falhar (Configurações de IA);
 * - antes de qualquer envio, CPF, CNPJ, telefone, e-mail e nomes conhecidos
 *   saem do texto (Anonimizador), e o log da IA guarda o texto já limpo;
 * - IA desligada por projeto e limite mensal de gasto, com alerta;
 * - preço de cada modelo na tabela ia_modelos (não no código);
 * - chaves cifradas no banco (ou no .env, como antes);
 * - cada execução registra provedor, modelo usado e quem pediu.
 *
 * Retry com espera crescente, timeout e log de cada execução continuam.
 * A transcrição de áudio continua no Whisper (OpenAI).
 * Sem a migração, funciona como antes (só OpenAI, chave do .env).
 */
class Ai_service {

    private $CI;
    private $api_key;
    private $base_url = 'https://api.openai.com/v1';
    private $default_timeout = 30;
    private $colunas_log = NULL;

    // Usado só sem a tabela ia_modelos. US$ por 1K tokens.
    private $token_costs = array(
        'gpt-4o' => array('input' => 0.0025, 'output' => 0.01),
        'gpt-4o-mini' => array('input' => 0.00015, 'output' => 0.0006),
        'gpt-4-turbo' => array('input' => 0.01, 'output' => 0.03),
        'gpt-3.5-turbo' => array('input' => 0.0005, 'output' => 0.0015),
        'whisper-1' => array('input' => 0.006, 'output' => 0),
    );

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->model('Ai_model');
        $this->CI->load->model('Ia_model');
        $this->api_key = $this->CI->Ia_model->chave('openai');
    }

    /** Alguma chave configurada (OpenAI ou Anthropic). */
    public function is_configured() {
        return $this->provedor_configurado('openai') || $this->provedor_configurado('anthropic');
    }

    public function provedor_configurado($provedor) {
        return $this->CI->Ia_model->chave($provedor) !== '';
    }

    /** Testa a conexão com um provedor (padrão: os que tiverem chave). */
    public function test_connection($provedor = NULL) {
        $lista = $provedor ? array($provedor) : array_values(array_filter(array('openai', 'anthropic'), array($this, 'provedor_configurado')));
        if (!$lista) {
            return array('success' => false, 'message' => 'Nenhuma chave de API configurada. Cadastre em Inteligência Artificial > Provedores e custos.', 'details' => null);
        }
        $msgs = array();
        $ok = true;
        $det = array();
        foreach ($lista as $p) {
            $chave = $this->CI->Ia_model->chave($p);
            if ($chave === '') {
                $ok = false;
                $msgs[] = Ia_provedores::nome($p) . ': sem chave.';
                continue;
            }
            $modelo = $p === 'anthropic' ? 'claude-haiku-5-5' : 'gpt-4o-mini';
            list($url, $cab, $corpo) = Ia_provedores::requisicao($p, $modelo, array(array('role' => 'user', 'content' => 'Responda apenas: ok')), array('max_tokens' => 20), $chave);
            if ($p === 'anthropic') {
                $corpo['max_tokens'] = 64;
            }
            try {
                $resp = $this->http($url, $cab, $corpo, 30);
                $r = Ia_provedores::resposta($p, $resp['http_code'], $resp['body']);
                $ok = $ok && $r['ok'];
                $msgs[] = Ia_provedores::nome($p) . ': ' . ($r['ok'] ? 'conexão ok (' . $resp['duration_ms'] . ' ms).' : 'erro - ' . $r['erro']);
                $det[$p] = array('ok' => $r['ok'], 'response_time_ms' => $resp['duration_ms']);
            } catch (Exception $e) {
                $ok = false;
                $msgs[] = Ia_provedores::nome($p) . ': ' . $e->getMessage();
            }
        }
        return array('success' => $ok, 'message' => implode(' ', $msgs), 'details' => $det);
    }

    /**
     * Executa um pedido de texto na função configurada ($feature_key).
     *
     * Opções: model, provider, max_tokens, temperature, esforco, timeout,
     * retries, prompt_id, resource_type, resource_id, project_id e nomes
     * (nomes de pessoas a retirar do texto).
     */
    public function chat_completion($feature_key, $messages, $options = array()) {
        $setting = $this->CI->Ai_model->get_setting($feature_key);
        if (!$setting || !in_array($setting->is_enabled, array(true, 't', 1, '1'), true)) {
            return $this->error_response("Funcionalidade '{$feature_key}' não está habilitada.");
        }
        $model = isset($options['model']) ? $options['model'] : $setting->model;
        // O provedor sai do modelo: um modelo "claude-*" só existe na Anthropic
        $provider = Ia_provedores::provedor_do_modelo($model);
        $params = json_decode((string) (isset($setting->custom_params) ? $setting->custom_params : ''), true);
        $params = is_array($params) ? $params : array();
        $op = array(
            'temperature' => isset($options['temperature']) ? $options['temperature'] : (float) $setting->temperature,
            'max_tokens' => isset($options['max_tokens']) ? $options['max_tokens'] : (int) $setting->max_tokens,
            'esforco' => isset($options['esforco']) ? $options['esforco'] : (isset($params['effort']) ? $params['effort'] : null),
        );
        $timeout = isset($options['timeout']) ? $options['timeout'] : (int) $setting->timeout_seconds;
        $retries = isset($options['retries']) ? $options['retries'] : (int) $setting->retry_attempts;
        $alternativa = null;
        if (empty($options['model']) && !empty($setting->fallback_model) && $setting->fallback_model !== $model) {
            $alternativa = array(Ia_provedores::provedor_do_modelo($setting->fallback_model), $setting->fallback_model);
        }
        return $this->executar($feature_key, $provider, $model, $messages, $op, $timeout, $retries, $options, $alternativa);
    }

    /**
     * Execução direta num modelo (tela de comparação). Passa pelas mesmas
     * regras: projeto, limite, anonimização e log.
     */
    public function executar_modelo($model, $messages, $options = array()) {
        $op = array(
            'max_tokens' => isset($options['max_tokens']) ? $options['max_tokens'] : 2000,
            'temperature' => isset($options['temperature']) ? $options['temperature'] : 0.4,
            'esforco' => isset($options['esforco']) ? $options['esforco'] : null,
        );
        $feature = isset($options['feature_key']) ? $options['feature_key'] : 'comparacao';
        return $this->executar($feature, Ia_provedores::provedor_do_modelo($model), $model, $messages, $op,
            isset($options['timeout']) ? $options['timeout'] : 60, 0, $options, null);
    }

    private function executar($feature_key, $provider, $model, $messages, $op, $timeout, $retries, $options, $alternativa) {
        $ia = $this->CI->Ia_model;

        // Projeto com IA desligada
        $projeto = isset($options['project_id']) ? (int) $options['project_id']
            : $ia->projeto_do_recurso(isset($options['resource_type']) ? $options['resource_type'] : null, isset($options['resource_id']) ? $options['resource_id'] : null);
        if ($projeto && $ia->ia_desligada($projeto)) {
            return $this->error_response('A inteligência artificial está desligada para este projeto.');
        }

        // Limite mensal
        $cfg = $ia->disponivel() ? $ia->config() : array('limite' => 0, 'alerta' => 80, 'anonimizar' => true);
        $gasto = $cfg['limite'] > 0 ? $ia->gasto_do_mes()['total'] : 0;
        if ($cfg['limite'] > 0 && $gasto >= $cfg['limite']) {
            return $this->error_response(sprintf('Limite mensal de gasto com IA atingido (US$ %.2f de US$ %.2f). Um administrador pode aumentar o limite em Inteligência Artificial > Provedores e custos.', $gasto, $cfg['limite']));
        }

        // Dados pessoais fora do texto (e do log)
        $anonimizar = $cfg['anonimizar'];
        $nomes = isset($options['nomes']) ? (array) $options['nomes'] : array();
        if ($anonimizar) {
            $nomes = array_merge($nomes, $ia->nomes_do_recurso(isset($options['resource_type']) ? $options['resource_type'] : null, isset($options['resource_id']) ? $options['resource_id'] : null));
        }
        $envio = $anonimizar ? Anonimizador::mensagens($messages, $nomes) : $messages;
        $opcoes_log = $options;
        unset($opcoes_log['nomes']);

        $log = array(
            'feature_key' => $feature_key,
            'model_used' => $model,
            'prompt_id' => isset($options['prompt_id']) ? $options['prompt_id'] : null,
            'input_data' => json_encode(array('messages' => $envio, 'options' => $opcoes_log), JSON_UNESCAPED_UNICODE),
            'status' => 'processing',
            'executed_by' => isset($this->CI->session) ? $this->CI->session->userdata('admin_id') : null,
            'resource_type' => isset($options['resource_type']) ? $options['resource_type'] : null,
            'resource_id' => isset($options['resource_id']) ? $options['resource_id'] : null,
        );
        if ($this->tem_coluna_log('provider')) {
            $log['provider'] = $provider;
            $log['project_id'] = $projeto ?: null;
            $log['anonimizado'] = (bool) $anonimizar;
        }
        $log_id = $this->CI->Ai_model->log_execution($log);

        $start_time = microtime(true);
        $tentativas = array(array($provider, $model));
        if ($alternativa) {
            $tentativas[] = $alternativa;
        }
        $erros = array();
        foreach ($tentativas as $i => $t) {
            list($prov, $mod) = $t;
            $r = $this->chamar($prov, $mod, $envio, $op, $timeout, $i === 0 ? $retries : 0);
            if (!$r['ok']) {
                $erros[] = Ia_provedores::nome($prov) . ' ' . $mod . ': ' . $r['erro'];
                continue;
            }
            $duration_ms = (int) ((microtime(true) - $start_time) * 1000);
            $usado = $r['modelo'] ?: $mod;
            $cost = $this->calculate_cost($usado, $r['tokens_entrada'], $r['tokens_saida']);
            $upd = array(
                'output_data' => json_encode($r['bruto'], JSON_UNESCAPED_UNICODE),
                'tokens_input' => $r['tokens_entrada'],
                'tokens_output' => $r['tokens_saida'],
                'cost_usd' => $cost,
                'duration_ms' => $duration_ms,
                'status' => 'success',
                'model_used' => mb_substr($usado, 0, 100),
            );
            if ($this->tem_coluna_log('provider')) {
                $upd['provider'] = $prov;
                if ($i > 0) {
                    $upd['fallback_de'] = mb_substr($model . ' (' . $erros[0] . ')', 0, 140);
                } elseif ($usado !== $mod) {
                    $upd['fallback_de'] = mb_substr($mod . ' (alternativa da própria Anthropic após recusa)', 0, 140);
                }
            }
            $this->CI->Ai_model->update_execution_log($log_id, $upd);
            $this->alerta_de_gasto($cfg, $gasto + $cost);
            return array(
                'success' => true,
                'content' => $r['texto'],
                'tokens_input' => $r['tokens_entrada'],
                'tokens_output' => $r['tokens_saida'],
                'cost_usd' => $cost,
                'duration_ms' => $duration_ms,
                'model' => $usado,
                'provider' => $prov,
                'used_fallback' => $i > 0,
                'warning' => $r['aviso'],
                'log_id' => $log_id,
            );
        }

        $duration_ms = (int) ((microtime(true) - $start_time) * 1000);
        $last_error = implode(' | ', $erros);
        $this->CI->Ai_model->update_execution_log($log_id, array(
            'status' => 'error',
            'error_message' => $last_error,
            'duration_ms' => $duration_ms,
        ));
        return $this->error_response($last_error, $log_id);
    }

    /** Uma chamada num provedor, com novas tentativas e espera crescente. */
    private function chamar($prov, $mod, $mensagens, $op, $timeout, $retries) {
        $chave = $this->CI->Ia_model->chave($prov);
        if ($chave === '') {
            return array('ok' => false, 'erro' => 'chave de API não configurada');
        }
        list($url, $cab, $corpo) = Ia_provedores::requisicao($prov, $mod, $mensagens, $op, $chave);
        if ($prov === 'anthropic') {
            // O raciocínio leva mais tempo; o Cloudflare corta a página em 100 s
            $timeout = min(max((int) $timeout, 90), 95);
        }
        $r = array('ok' => false, 'erro' => 'sem resposta');
        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            if ($attempt > 0) {
                usleep(pow(2, $attempt - 1) * 1000000);
            }
            try {
                $resp = $this->http($url, $cab, $corpo, $timeout ?: $this->default_timeout);
                $r = Ia_provedores::resposta($prov, $resp['http_code'], $resp['body']);
            } catch (Exception $e) {
                $r = array('ok' => false, 'erro' => $e->getMessage(), 'repetir' => true);
            }
            if ($r['ok'] || empty($r['repetir'])) {
                break;
            }
        }
        return $r;
    }

    private function alerta_de_gasto($cfg, $total) {
        if ($cfg['limite'] <= 0 || $cfg['alerta'] <= 0) {
            return;
        }
        $pct = $total / $cfg['limite'] * 100;
        if ($pct >= $cfg['alerta']) {
            require_once APPPATH . 'libraries/Monitor_erros.php';
            // Uma linha por mês em Controle > Erros (a "linha" é o mês)
            Monitor_erros::registrar('painel', 'aviso', sprintf('Gasto com IA no mês passou de %d%% do limite (US$ %.2f de US$ %.2f).', (int) $cfg['alerta'], $total, $cfg['limite']), 'ia/limite-mensal', (int) date('Ym'));
        }
    }

    private function tem_coluna_log($c) {
        if ($this->colunas_log === NULL) {
            $this->colunas_log = $this->CI->db->list_fields('ai_execution_logs');
        }
        return in_array($c, $this->colunas_log, true);
    }

    /**
     * Transcreve áudio usando Whisper
     */
    public function transcribe_audio($file_path, $options = array()) {
        $setting = $this->CI->Ai_model->get_setting('transcription');

        if (!$setting || !$setting->is_enabled) {
            return $this->error_response("Transcrição de áudio não está habilitada.");
        }

        if (!file_exists($file_path)) {
            return $this->error_response("Arquivo de áudio não encontrado: {$file_path}");
        }

        $language = isset($options['language']) ? $options['language'] : 'pt';
        $model = 'whisper-1';
        $timeout = (int) $setting->timeout_seconds;

        $log_id = $this->CI->Ai_model->log_execution(array(
            'feature_key' => 'transcription',
            'model_used' => $model,
            'input_data' => json_encode(array('file' => basename($file_path), 'language' => $language)),
            'status' => 'processing',
            'executed_by' => isset($this->CI->session) ? $this->CI->session->userdata('admin_id') : null,
            'resource_type' => isset($options['resource_type']) ? $options['resource_type'] : 'audio',
            'resource_id' => isset($options['resource_id']) ? $options['resource_id'] : null,
        ));

        $start_time = microtime(true);

        try {
            $ch = curl_init();

            $post_data = array(
                'file' => new CURLFile($file_path),
                'model' => $model,
                'language' => $language,
                'response_format' => 'verbose_json',
            );

            curl_setopt_array($ch, array(
                CURLOPT_URL => $this->base_url . '/audio/transcriptions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post_data,
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $this->api_key,
                ),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
            ));

            $body = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $duration_ms = (int) ((microtime(true) - $start_time) * 1000);

            if (curl_errno($ch)) {
                throw new Exception('cURL Error: ' . curl_error($ch));
            }
            curl_close($ch);

            if ($http_code === 200) {
                $result = json_decode($body, true);
                $duration_audio = $result['duration'] ?? 0;
                $cost = ($duration_audio / 60) * 0.006;

                $this->CI->Ai_model->update_execution_log($log_id, array(
                    'output_data' => json_encode($result),
                    'cost_usd' => $cost,
                    'duration_ms' => $duration_ms,
                    'status' => 'success',
                ));

                return array(
                    'success' => true,
                    'text' => $result['text'] ?? '',
                    'language' => $result['language'] ?? $language,
                    'duration' => $duration_audio,
                    'segments' => $result['segments'] ?? array(),
                    'cost_usd' => $cost,
                    'duration_ms' => $duration_ms,
                    'log_id' => $log_id,
                );
            }

            $error = json_decode($body, true);
            $error_msg = $error['error']['message'] ?? "HTTP {$http_code}";

            $this->CI->Ai_model->update_execution_log($log_id, array(
                'status' => 'error',
                'error_message' => $error_msg,
                'duration_ms' => $duration_ms,
            ));

            return $this->error_response($error_msg, $log_id);

        } catch (Exception $e) {
            $duration_ms = (int) ((microtime(true) - $start_time) * 1000);
            $this->CI->Ai_model->update_execution_log($log_id, array(
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'duration_ms' => $duration_ms,
            ));
            return $this->error_response($e->getMessage(), $log_id);
        }
    }

    /**
     * Executa chat completion e tenta parsear JSON da resposta
     */
    public function chat_completion_json($feature_key, $messages, $options = array()) {
        $result = $this->chat_completion($feature_key, $messages, $options);

        if (!$result['success']) {
            return $result;
        }

        $content = $result['content'];

        // Tentar extrair JSON do conteúdo (pode vir com markdown ```json ... ```)
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $content, $matches)) {
            $content = trim($matches[1]);
        }

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback: procurar o primeiro [ ou { (o Claude às vezes escreve uma frase antes)
            $content = trim($content);
            $ini = strcspn($content, '[{');
            if ($ini < strlen($content)) {
                $parsed = json_decode(substr($content, $ini), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $fim = max(strrpos($content, ']'), strrpos($content, '}'));
                    if ($fim !== false && $fim > $ini) {
                        $parsed = json_decode(substr($content, $ini, $fim - $ini + 1), true);
                    }
                }
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                $result['parsed'] = null;
                $result['parse_error'] = 'Resposta da IA não é um JSON válido: ' . json_last_error_msg();
                return $result;
            }
        }

        $result['parsed'] = $parsed;
        return $result;
    }

    // ============================================================
    // MÉTODOS PRIVADOS
    // ============================================================

    /** POST JSON. */
    private function http($url, $headers, $payload, $timeout) {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => (int) $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ));
        $start = microtime(true);
        $body = curl_exec($ch);
        $duration_ms = (int) ((microtime(true) - $start) * 1000);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Falha de conexão: ' . $error);
        }
        curl_close($ch);
        return array('http_code' => (int) $http_code, 'body' => $body, 'duration_ms' => $duration_ms);
    }

    /** Custo pela tabela ia_modelos (US$ por milhão); sem ela, pela tabela antiga. */
    private function calculate_cost($model, $tokens_input, $tokens_output) {
        $preco = $this->CI->Ia_model->preco($model);
        if ($preco) {
            return Ia_provedores::custo($preco, $tokens_input, $tokens_output);
        }
        if (!isset($this->token_costs[$model])) {
            return 0;
        }
        $costs = $this->token_costs[$model];
        return (($tokens_input / 1000) * $costs['input']) + (($tokens_output / 1000) * $costs['output']);
    }

    /**
     * Resposta de erro padronizada
     */
    private function error_response($message, $log_id = null) {
        return array(
            'success' => false,
            'error' => $message,
            'log_id' => $log_id,
        );
    }
}
