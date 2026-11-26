<?php

use PHPUnit\Framework\TestCase;

/** Frente I: TOTP, política de senha e conferência do formato simples. */
class Robustez_test extends TestCase {

    // ---------------- TOTP (RFC 6238, apêndice B, SHA-1) ----------------

    public function test_totp_vetores_da_rfc() {
        $chave = '12345678901234567890';
        $casos = [59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037'];
        foreach ($casos as $t => $esperado) {
            $this->assertSame($esperado, Totp::codigo($chave, intdiv($t, 30), 8, TRUE), "T=$t");
        }
    }

    public function test_base32_ida_e_volta() {
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', Totp::base32_encode('12345678901234567890'));
        $this->assertSame('12345678901234567890', Totp::base32_decode('gezd gnbv gy3t qojq gezd gnbv gy3t qojq'));
        $s = Totp::gerar_segredo();
        $this->assertSame(32, strlen($s));
        $this->assertSame(20, strlen(Totp::base32_decode($s)));
        $this->assertFalse(Totp::base32_decode('1!'));
    }

    public function test_verificar_aceita_um_passo_de_folga_e_impede_reuso() {
        $s = Totp::gerar_segredo();
        $agora = 1700000000;
        $passo = Totp::passo_atual($agora);
        $this->assertSame($passo, Totp::verificar($s, Totp::codigo($s, $passo), NULL, $agora));
        $this->assertSame($passo - 1, Totp::verificar($s, Totp::codigo($s, $passo - 1), NULL, $agora), 'relógio 30 s atrasado');
        $this->assertSame($passo + 1, Totp::verificar($s, substr_replace(Totp::codigo($s, $passo + 1), ' ', 3, 0), NULL, $agora), 'aceita espaço no meio');
        $this->assertFalse(Totp::verificar($s, Totp::codigo($s, $passo - 3), NULL, $agora), 'código velho');
        $this->assertFalse(Totp::verificar($s, Totp::codigo($s, $passo), $passo, $agora), 'mesmo código não vale duas vezes');
        $this->assertFalse(Totp::verificar($s, '12345', NULL, $agora));
        $this->assertFalse(Totp::verificar($s, 'abcdef', NULL, $agora));
    }

    public function test_uri_do_autenticador() {
        $u = Totp::uri('ABC', 'maria silva');
        $this->assertStringStartsWith('otpauth://totp/SXData:maria%20silva?secret=ABC&issuer=SXData', $u);
        $this->assertStringContainsString('period=30', $u);
    }

    // ---------------- Política de senha ----------------

    public function test_politica_de_senha() {
        $this->assertSame([], Politica_senha::validar('Campo2026x', 'joao', 'João Pereira'));
        $this->assertNotEmpty(Politica_senha::validar('abc12'));
        $this->assertNotEmpty(Politica_senha::validar('somenteletras'));
        $this->assertNotEmpty(Politica_senha::validar('1234567890'));
        $this->assertNotEmpty(Politica_senha::validar('senha123'));
        $this->assertNotEmpty(Politica_senha::validar('joao2026xy', 'joao'));
        $this->assertNotEmpty(Politica_senha::validar('pereira2026', 'jp', 'João Pereira'));
        for ($i = 0; $i < 30; $i++) {
            $g = Politica_senha::gerar();
            $this->assertSame([], Politica_senha::validar($g), "senha gerada $g");
        }
    }

    // ---------------- Conferência do formato simples ----------------

    private static function perguntas() {
        return [
            10 => ['texto' => 'Tem filhos?', 'tipo' => 'radio', 'obrigatoria' => TRUE, 'logica' => NULL, 'opcoes' => [['sim', 'Sim'], ['nao', 'Não']]],
            11 => ['texto' => 'Quantos?', 'tipo' => 'number', 'obrigatoria' => TRUE,
                   'logica' => '{"visibility":{"operator":"AND","conditions":[{"question":"10","operator":"equals","value":"Sim"}]},"required":null}', 'opcoes' => []],
            12 => ['texto' => 'Serviços usados', 'tipo' => 'checkbox', 'obrigatoria' => FALSE, 'logica' => NULL, 'opcoes' => [['saude', 'Saúde'], ['escola', 'Escola']]],
            13 => ['texto' => 'E-mail', 'tipo' => 'email', 'obrigatoria' => FALSE, 'logica' => NULL, 'opcoes' => []],
            14 => ['texto' => 'Observação', 'tipo' => 'textarea', 'obrigatoria' => FALSE,
                   'logica' => '{"visibility":{"conditions":[{"question":"10","operator":"esquisito","value":"x"}]},"required":null}', 'opcoes' => []],
        ];
    }

    public function test_envio_correto_nao_vai_para_revisao() {
        $r = [
            ['question_id' => 10, 'selected_options' => ['sim']],
            ['question_id' => 11, 'response_number' => 3],
            ['question_id' => 12, 'selected_options' => '["saude","Escola"]'],
            ['question_id' => 13, 'response_text' => 'a@b.com'],
        ];
        $this->assertSame([], Validacao_simples::conferir(self::perguntas(), $r));
        $r[3]['response_text'] = 'Não possui';
        $this->assertSame([], Validacao_simples::conferir(self::perguntas(), $r), 'anotação de não informado no e-mail não é erro');
    }

    public function test_condicao_com_texto_da_opcao_e_valor_do_app() {
        // O editor grava "Sim" na condição; o app manda "sim": a 11 está visível e é obrigatória
        $e = Validacao_simples::conferir(self::perguntas(), [['question_id' => 10, 'selected_options' => ['sim']]]);
        $this->assertCount(1, $e);
        $this->assertStringContainsString('Obrigatória sem resposta: "Quantos?"', $e[0]);
        // Respondeu "Não": a 11 fica oculta e não é cobrada
        $this->assertSame([], Validacao_simples::conferir(self::perguntas(), [['question_id' => 10, 'response_text' => 'Não']]));
    }

    public function test_aponta_inconsistencias() {
        $e = Validacao_simples::conferir(self::perguntas(), [
            ['question_id' => 10, 'selected_options' => ['talvez']],
            ['question_id' => 11, 'response_text' => 'três'],
            ['question_id' => 12, 'selected_options' => ['praia']],
            ['question_id' => 13, 'response_text' => 'fulano@gmail. com'],
            ['question_id' => 99, 'response_text' => 'x'],
            ['question_id' => 13, 'response_text' => 'a@b.com'],
        ]);
        $texto = implode("\n", $e);
        $this->assertStringContainsString('a opção "talvez" não existe', $texto);
        $this->assertStringContainsString('"três" não é um número', $texto);
        $this->assertStringContainsString('a opção "praia" não existe', $texto);
        $this->assertStringContainsString('e-mail inválido', $texto);
        $this->assertStringContainsString('pergunta 99, que não é deste questionário', $texto);
        $this->assertStringContainsString('respondida duas vezes', $texto);
    }

    public function test_obrigatoria_sem_condicao_e_operador_desconhecido() {
        $e = Validacao_simples::conferir(self::perguntas(), []);
        $this->assertSame(['Obrigatória sem resposta: "Tem filhos?".'], $e, 'a 11 depende da 10 (vazia): oculta; a 14 tem operador desconhecido: não cobra');
        $p = self::perguntas();
        $p[14]['obrigatoria'] = TRUE;
        $this->assertCount(1, Validacao_simples::conferir($p, []), 'na dúvida (operador desconhecido), não aponta');
    }

    public function test_valor_da_resposta() {
        $this->assertSame(['a', 'b'], Validacao_simples::valor(['selected_options' => '["a"," b "]']));
        $this->assertSame('x', Validacao_simples::valor(['selected_options' => '[]', 'response_text' => ' x ']));
        $this->assertSame(0, Validacao_simples::valor(['response_number' => 0]));
        $this->assertNull(Validacao_simples::valor(['response_text' => '  ']));
    }
}
