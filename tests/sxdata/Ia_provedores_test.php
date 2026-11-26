<?php

use PHPUnit\Framework\TestCase;

/** Frente K: provedores de IA, anonimização e cifra das chaves. */
class Ia_provedores_test extends TestCase {

    public function test_anonimiza_dados_pessoais() {
        $t = 'Maria da Silva Pereira, CPF 123.456.789-09 (ou 12345678909), tel (71) 99876-5432 e 71 3333-4444, '
            . 'e-mail maria.p@gmail.com, CNPJ 12.345.678/0001-90. Renda R$ 1.500,00 em 2026, 3 moradores, código 1234.';
        $r = Anonimizador::limpar($t, ['Maria da Silva Pereira']);
        foreach (['123.456.789-09', '12345678909', '99876-5432', '3333-4444', 'maria.p@gmail.com', '12.345.678/0001-90', 'Maria', 'Pereira'] as $x) {
            $this->assertStringNotContainsString($x, $r, $x);
        }
        foreach (['[CPF]', '[TELEFONE]', '[E-MAIL]', '[CNPJ]', '[NOME]', 'R$ 1.500,00', '2026', '3 moradores', 'código 1234'] as $x) {
            $this->assertStringContainsString($x, $r, $x);
        }
        $this->assertStringContainsString('Silva', Anonimizador::limpar('Pedro Silva', ['João Silva']), 'sobrenome comum sozinho não é apagado');
        $this->assertSame('Mariana chegou', Anonimizador::limpar('Mariana chegou', ['Maria']), 'só palavra inteira');
    }

    public function test_anonimiza_mensagens_e_muitos_nomes() {
        $nomes = [];
        for ($i = 0; $i < 600; $i++) { $nomes[] = 'Pessoa' . $i . ' Sobrenome' . $i; }
        $m = Anonimizador::mensagens([['role' => 'system', 'content' => 'x'], ['role' => 'user', 'content' => 'Pessoa599 Sobrenome599 respondeu; Pessoa3 também.']], $nomes);
        $this->assertSame('[NOME] respondeu; [NOME] também.', $m[1]['content']);
    }

    public function test_requisicao_openai_igual_a_de_antes() {
        list($url, $cab, $corpo) = Ia_provedores::requisicao('openai', 'gpt-4o', [['role' => 'system', 'content' => 's'], ['role' => 'user', 'content' => 'u']], ['temperature' => 0.3, 'max_tokens' => 1500], 'sk-x');
        $this->assertSame('https://api.openai.com/v1/chat/completions', $url);
        $this->assertContains('Authorization: Bearer sk-x', $cab);
        $this->assertSame(['model' => 'gpt-4o', 'messages' => [['role' => 'system', 'content' => 's'], ['role' => 'user', 'content' => 'u']], 'temperature' => 0.3, 'max_tokens' => 1500], $corpo);
    }

    public function test_requisicao_anthropic() {
        list($url, $cab, $corpo) = Ia_provedores::requisicao('anthropic', 'claude-opus-5-5', [
            ['role' => 'system', 'content' => 'Regras.'], ['role' => 'user', 'content' => 'a'], ['role' => 'user', 'content' => 'b'], ['role' => 'assistant', 'content' => 'c'],
        ], ['temperature' => 0.7, 'max_tokens' => 1500], 'sk-ant');
        $this->assertSame('https://api.anthropic.com/v1/messages', $url);
        $this->assertContains('x-api-key: sk-ant', $cab);
        $this->assertContains('anthropic-version: 2026-10-21', $cab);
        $this->assertContains('anthropic-beta: server-side-fallback-2026-07-01', $cab);
        $this->assertSame('Regras.', $corpo['system']);
        $this->assertSame([['role' => 'user', 'content' => "a\n\nb"], ['role' => 'assistant', 'content' => 'c']], $corpo['messages'], 'mensagens seguidas do mesmo papel juntas');
        $this->assertArrayNotHasKey('temperature', $corpo, 'a API 2026-10-21 recusa temperature');
        $this->assertSame(16000, $corpo['max_tokens'], 'o raciocínio conta no limite: mínimo de 16 mil');
        $this->assertSame('default', $corpo['fallbacks']);
        $this->assertSame(['effort' => 'medium'], $corpo['output_config']);

        list(, $cab2, $c2) = Ia_provedores::requisicao('anthropic', 'claude-haiku-5-5', [['role' => 'assistant', 'content' => 'x']], ['esforco' => 'low'], 'k');
        $this->assertArrayNotHasKey('fallbacks', $c2, 'Haiku não tem alternativa do lado do servidor');
        $this->assertNotContains('anthropic-beta: server-side-fallback-2026-07-01', $cab2);
        $this->assertSame('user', $c2['messages'][0]['role'], 'a conversa precisa começar pelo usuário');
        $this->assertSame(['effort' => 'low'], $c2['output_config']);
    }

    public function test_resposta_anthropic() {
        $ok = json_encode(['model' => 'claude-opus-5-5', 'stop_reason' => 'end_turn', 'content' => [['type' => 'thinking', 'thinking' => ''], ['type' => 'text', 'text' => 'Olá']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 20, 'cache_read_input_tokens' => 5]]);
        $r = Ia_provedores::resposta('anthropic', 200, $ok);
        $this->assertTrue($r['ok']);
        $this->assertSame('Olá', $r['texto']);
        $this->assertSame([105, 20], [$r['tokens_entrada'], $r['tokens_saida']]);

        $rec = Ia_provedores::resposta('anthropic', 200, json_encode(['stop_reason' => 'refusal', 'stop_details' => ['category' => 'cyber'], 'content' => [], 'usage' => []]));
        $this->assertFalse($rec['ok']);
        $this->assertStringContainsString('recusou', $rec['erro']);
        $this->assertFalse($rec['repetir']);

        $corte = Ia_provedores::resposta('anthropic', 200, json_encode(['stop_reason' => 'max_tokens', 'content' => [['type' => 'text', 'text' => 'parcial']], 'usage' => []]));
        $this->assertTrue($corte['ok']);
        $this->assertNotNull($corte['aviso']);

        $e529 = Ia_provedores::resposta('anthropic', 529, json_encode(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']]));
        $this->assertSame(['Overloaded', TRUE], [$e529['erro'], $e529['repetir']]);
        $e400 = Ia_provedores::resposta('anthropic', 400, json_encode(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'bad']]));
        $this->assertFalse($e400['repetir']);
        $this->assertTrue(Ia_provedores::resposta('openai', 429, '{}')['repetir']);
    }

    public function test_resposta_openai_e_custo() {
        $r = Ia_provedores::resposta('openai', 200, json_encode(['model' => 'gpt-4o', 'choices' => [['message' => ['content' => 'oi'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 500]]));
        $this->assertSame(['oi', 1000, 500], [$r['texto'], $r['tokens_entrada'], $r['tokens_saida']]);
        $this->assertEqualsWithDelta(0.0075, Ia_provedores::custo(['entrada' => 2.5, 'saida' => 10], 1000, 500), 1e-9);
        $this->assertEqualsWithDelta(14.0, Ia_provedores::custo(['entrada' => 4, 'saida' => 20], 1000000, 500000), 1e-9);
        $this->assertSame(0.0, Ia_provedores::custo(NULL, 10, 10));
        $this->assertSame('anthropic', Ia_provedores::provedor_do_modelo('claude-sonnet-5-5'));
        $this->assertSame('openai', Ia_provedores::provedor_do_modelo('gpt-4o-mini'));
        $this->assertSame('…wxyz', Ia_provedores::mascara('sk-abcdwxyz'));
    }

    public function test_cifra_das_chaves() {
        $m = str_repeat('k', 40);
        $c = Cripto_chaves::cifrar('sk-ant-segredo', $m);
        $this->assertStringStartsWith('v1:', $c);
        $this->assertStringNotContainsString('segredo', $c);
        $this->assertSame('sk-ant-segredo', Cripto_chaves::decifrar($c, $m));
        $this->assertNull(Cripto_chaves::decifrar($c, str_repeat('x', 40)), 'chave mestra trocada');
        $this->assertNull(Cripto_chaves::decifrar('lixo', $m));
        $this->assertNotSame($c, Cripto_chaves::cifrar('sk-ant-segredo', $m), 'nonce novo a cada vez');
    }
}
