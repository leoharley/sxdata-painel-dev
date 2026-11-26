<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Provedores de IA (frente K): monta a requisição e lê a resposta de cada
 * API. Sem rede e sem banco, para poder testar; quem faz a chamada HTTP é o
 * Ai_service.
 *
 * - OpenAI: POST /v1/chat/completions (como sempre foi).
 * - Anthropic: POST /v1/messages, via cURL, sem SDK (o painel roda também em
 *   PHP 7.4). Versão da API 2026-10-21: não aceita temperature/top_p, o
 *   "system" vai fora das mensagens e o raciocínio (thinking) é adaptativo.
 *   Nos modelos Opus/Sonnet 5.5 vai o parâmetro "fallbacks": se a Anthropic
 *   recusar por política, ela mesma refaz no modelo recomendado.
 */
class Ia_provedores {

    const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';
    const ANTHROPIC_VERSAO = '2026-10-21';
    const ANTHROPIC_BETA_FALLBACK = 'server-side-fallback-2026-07-01';
    const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';

    /** Modelos oferecidos na tela (o preço fica na tabela ia_modelos). */
    public static function modelos() {
        return [
            'openai' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'],
            'anthropic' => ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5'],
        ];
    }

    public static function nome($provedor) {
        return ['openai' => 'OpenAI', 'anthropic' => 'Anthropic (Claude)'][$provedor] ?? $provedor;
    }

    /** Provedor pelo nome do modelo (configurações antigas não têm provedor). */
    public static function provedor_do_modelo($modelo) {
        return strpos((string) $modelo, 'claude-') === 0 ? 'anthropic' : 'openai';
    }

    /**
     * @param array $mensagens formato chat: [['role' => system|user|assistant, 'content' => texto], ...]
     * @param array $op max_tokens, temperature (só OpenAI), esforco (só Anthropic: low|medium|high)
     * @return array [url, cabecalhos, corpo]
     */
    public static function requisicao($provedor, $modelo, array $mensagens, array $op, $chave) {
        if ($provedor === 'anthropic') {
            return self::requisicao_anthropic($modelo, $mensagens, $op, $chave);
        }
        $corpo = ['model' => $modelo, 'messages' => array_values($mensagens)];
        if (isset($op['temperature'])) {
            $corpo['temperature'] = (float) $op['temperature'];
        }
        if (!empty($op['max_tokens'])) {
            $corpo['max_tokens'] = (int) $op['max_tokens'];
        }
        return [self::OPENAI_URL, ['Authorization: Bearer ' . $chave, 'Content-Type: application/json'], $corpo];
    }

    private static function requisicao_anthropic($modelo, array $mensagens, array $op, $chave) {
        $sistema = [];
        $conversa = [];
        foreach ($mensagens as $m) {
            $papel = $m['role'] ?? 'user';
            $texto = is_string($m['content'] ?? NULL) ? $m['content'] : json_encode($m['content'] ?? '', JSON_UNESCAPED_UNICODE);
            if ($papel === 'system') {
                $sistema[] = $texto;
                continue;
            }
            $papel = $papel === 'assistant' ? 'assistant' : 'user';
            // Mensagens seguidas do mesmo papel viram uma só
            $n = count($conversa);
            if ($n && $conversa[$n - 1]['role'] === $papel) {
                $conversa[$n - 1]['content'] .= "\n\n" . $texto;
            } else {
                $conversa[] = ['role' => $papel, 'content' => $texto];
            }
        }
        if (!$conversa || $conversa[0]['role'] !== 'user') {
            array_unshift($conversa, ['role' => 'user', 'content' => '(início)']);
        }
        // O raciocínio conta dentro de max_tokens: com os limites antigos
        // (1.500 a 4.000) a resposta sairia cortada.
        $max = max(16000, (int) ($op['max_tokens'] ?? 0));
        $corpo = ['model' => $modelo, 'max_tokens' => $max, 'messages' => $conversa];
        if ($sistema) {
            $corpo['system'] = implode("\n\n", $sistema);
        }
        $esforco = in_array($op['esforco'] ?? NULL, ['low', 'medium', 'high'], TRUE) ? $op['esforco'] : NULL;
        if ($esforco === NULL && $modelo !== 'claude-haiku-5-5') {
            $esforco = 'medium';
        }
        if ($esforco !== NULL) {
            $corpo['output_config'] = ['effort' => $esforco];
        }
        $cab = ['x-api-key: ' . $chave, 'anthropic-version: ' . self::ANTHROPIC_VERSAO, 'content-type: application/json'];
        if (in_array($modelo, ['claude-opus-5-5', 'claude-sonnet-5-5'], TRUE)) {
            $corpo['fallbacks'] = 'default';
            $cab[] = 'anthropic-beta: ' . self::ANTHROPIC_BETA_FALLBACK;
        }
        return [self::ANTHROPIC_URL, $cab, $corpo];
    }

    /**
     * Lê a resposta HTTP.
     * @return array ok, texto, tokens_entrada, tokens_saida, modelo, erro, repetir (vale tentar de novo), aviso
     */
    public static function resposta($provedor, $http, $corpo) {
        $j = json_decode((string) $corpo, TRUE);
        $r = ['ok' => FALSE, 'texto' => '', 'tokens_entrada' => 0, 'tokens_saida' => 0, 'modelo' => NULL, 'erro' => NULL, 'repetir' => FALSE, 'aviso' => NULL, 'bruto' => $j];
        if ((int) $http !== 200) {
            $r['erro'] = (is_array($j) ? ($j['error']['message'] ?? NULL) : NULL) ?: 'HTTP ' . (int) $http;
            // 429 (limite), 5xx e 529 (sobrecarga) valem nova tentativa; 4xx não
            $r['repetir'] = (int) $http === 429 || (int) $http >= 500 || (int) $http === 0;
            return $r;
        }
        if (!is_array($j)) {
            $r['erro'] = 'Resposta inválida do provedor.';
            $r['repetir'] = TRUE;
            return $r;
        }
        if ($provedor === 'anthropic') {
            $r['modelo'] = $j['model'] ?? NULL;
            $r['tokens_entrada'] = (int) ($j['usage']['input_tokens'] ?? 0) + (int) ($j['usage']['cache_read_input_tokens'] ?? 0)
                + (int) ($j['usage']['cache_creation_input_tokens'] ?? 0);
            $r['tokens_saida'] = (int) ($j['usage']['output_tokens'] ?? 0);
            $parada = $j['stop_reason'] ?? NULL;
            if ($parada === 'refusal') {
                $cat = $j['stop_details']['category'] ?? NULL;
                $r['erro'] = 'O modelo recusou a solicitação' . ($cat ? ' (categoria: ' . $cat . ')' : '') . '. Revise o texto ou use outro modelo.';
                return $r;
            }
            $partes = [];
            foreach ($j['content'] ?? [] as $b) {
                if (($b['type'] ?? '') === 'text') {
                    $partes[] = $b['text'];
                }
            }
            $r['texto'] = implode("\n", $partes);
            if ($parada === 'max_tokens') {
                $r['aviso'] = 'Resposta cortada pelo limite de tokens.';
            }
        } else {
            $r['modelo'] = $j['model'] ?? NULL;
            $r['tokens_entrada'] = (int) ($j['usage']['prompt_tokens'] ?? 0);
            $r['tokens_saida'] = (int) ($j['usage']['completion_tokens'] ?? 0);
            $r['texto'] = (string) ($j['choices'][0]['message']['content'] ?? '');
            if (($j['choices'][0]['finish_reason'] ?? '') === 'length') {
                $r['aviso'] = 'Resposta cortada pelo limite de tokens.';
            }
        }
        $r['ok'] = TRUE;
        return $r;
    }

    /** Custo em dólar pelo preço por milhão de tokens. */
    public static function custo($preco, $entrada, $saida) {
        if (!$preco) {
            return 0.0;
        }
        return round(($entrada / 1e6) * (float) $preco['entrada'] + ($saida / 1e6) * (float) $preco['saida'], 6);
    }

    /** Final da chave para mostrar na tela ("…a1b2"). */
    public static function mascara($chave) {
        $chave = (string) $chave;
        return $chave === '' ? '' : '…' . substr($chave, -4);
    }
}
