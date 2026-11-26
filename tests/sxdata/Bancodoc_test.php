<?php

use PHPUnit\Framework\TestCase;

/** Frente H: leitura da planilha do BancoDOC. */
class Bancodoc_test extends TestCase {

    public function test_cpf() {
        $this->assertTrue(Bancodoc::cpf_valido('12345678909'));
        $this->assertFalse(Bancodoc::cpf_valido('12345678900'));
        $this->assertFalse(Bancodoc::cpf_valido('11111111111'));
    }

    public function test_normaliza_linha_com_cabecalhos_variados() {
        list($d, $e) = Bancodoc::normalizar_linha(['CPF' => '123.456.789-09', 'Nome completo' => ' Ana Souza ', 'Razão Social' => 'Parceira Ltda', 'Cargo' => 'Operadora',
            'Unidade' => 'Usina Ouro Branco', 'Situação' => 'Mobilizado', 'Data de mobilização' => '05/09/2026', 'Outra' => 'x']);
        $this->assertNull($e);
        $this->assertSame(['cpf' => '12345678909', 'nome' => 'Ana Souza', 'empresa' => 'Parceira Ltda', 'funcao' => 'Operadora', 'unidade' => 'UOB',
            'situacao' => 'Mobilizado', 'mobilizado_em' => '2026-09-05'], $d);
        list($d2) = Bancodoc::normalizar_linha(['Matrícula' => 'M77', 'Nome' => 'Beto', 'Unidade' => 'Várzea do Lopes', 'Data de mobilização' => 46280]);
        $this->assertSame(['matricula' => 'M77', 'nome' => 'Beto', 'unidade' => 'MVL', 'mobilizado_em' => '2026-09-15'], $d2, 'data serial do Excel');
        $this->assertSame('sem nome', Bancodoc::normalizar_linha(['CPF' => '12345678909'])[1]);
        $this->assertSame('sem CPF nem matrícula', Bancodoc::normalizar_linha(['Nome' => 'X'])[1]);
        $this->assertStringStartsWith('CPF inválido', Bancodoc::normalizar_linha(['Nome' => 'X', 'CPF' => '123'])[1]);
    }

    public function test_conector_api_aguardando() {
        list($ok, $msg) = Bancodoc::conector('api')->enviar_participacoes([]);
        $this->assertFalse($ok);
        $this->assertStringContainsString('documentação', $msg);
        list($ok2) = Bancodoc::conector('arquivo')->enviar_participacoes([1, 2]);
        $this->assertTrue($ok2);
    }
}
