<?php

use PHPUnit\Framework\TestCase;

class Ponto_campo_test extends TestCase {

    private function base($extra = []) {
        return array_merge([
            'client_id' => '3f1c2a9e-0000-4000-8000-000000000001',
            'project_id' => 7,
            'codigo_ibge' => '1713601',
            'nome' => '  Comunidade   Serra  Azul ',
            'tipo' => 'comunidade',
            'latitude' => -10.7611,
            'longitude' => -48.1114,
            'origem_coord' => 'mapa',
        ], $extra);
    }

    public function test_ponto_valido_e_normalizado() {
        list($d, $erros) = Ponto_campo::validar($this->base());
        $this->assertSame([], $erros);
        $this->assertSame('Comunidade Serra Azul', $d['nome']);
        $this->assertSame(7, $d['project_id']);
        $this->assertSame(1713601, $d['codigo_ibge']);
        $this->assertSame('mapa', $d['origem_coord']);
        $this->assertSame(-10.7611, $d['latitude']);
    }

    public function test_projeto_e_obrigatorio() {
        foreach ([null, 0, '', 'abc'] as $v) {
            list($d, $erros) = Ponto_campo::validar($this->base(['project_id' => $v]));
            $this->assertNull($d);
            $this->assertContains('project_id é obrigatório.', $erros);
        }
    }

    public function test_campos_obrigatorios() {
        list(, $erros) = Ponto_campo::validar($this->base(['client_id' => '', 'codigo_ibge' => '171360', 'nome' => '  ']));
        $this->assertCount(3, $erros);
        list($d, $erros) = Ponto_campo::validar($this->base(['client_id' => str_repeat('a', 65)]));
        $this->assertNull($d);
        list($d, $erros) = Ponto_campo::validar('texto');
        $this->assertNull($d);
    }

    public function test_sem_coordenada_ou_coordenada_ruim_grava_sem_coordenada() {
        foreach ([[null, null], [0, 0], [91, 10], ['x', 'y'], [-10.7, null]] as $c) {
            list($d, $erros) = Ponto_campo::validar($this->base(['latitude' => $c[0], 'longitude' => $c[1]]));
            $this->assertSame([], $erros);
            $this->assertNull($d['latitude']);
            $this->assertNull($d['longitude']);
            $this->assertNull($d['origem_coord']);
        }
    }

    public function test_tipo_e_origem_desconhecidos_e_nome_longo_nao_travam_a_fila() {
        list($d, $erros) = Ponto_campo::validar($this->base([
            'tipo' => 'Quilombo', 'origem_coord' => 'satelite', 'nome' => str_repeat('á', 200)]));
        $this->assertSame([], $erros);
        $this->assertSame('outro', $d['tipo']);
        $this->assertSame('gps', $d['origem_coord']);
        $this->assertSame(150, mb_strlen($d['nome']));

        list($d) = Ponto_campo::validar($this->base(['tipo' => null]));
        $this->assertSame('comunidade', $d['tipo']);
    }

    public function test_projetos_acessiveis() {
        $q = function ($p) { return (object) ['project_id' => $p]; };
        $questionarios = [$q(3), $q(1), $q(3), $q(null)];

        $this->assertSame([1, 3], Ponto_campo::projetos_acessiveis('aplicador', $questionarios, [1, 2, 3, 4]));
        $this->assertSame([], Ponto_campo::projetos_acessiveis('aplicador', [], [1, 2]));
        $this->assertSame([1, 2, 4], Ponto_campo::projetos_acessiveis('supervisor', [], ['4', '1', '2']));
        $this->assertSame([1, 2], Ponto_campo::projetos_acessiveis('administrador', [], [2, 1]));
        $this->assertSame([], Ponto_campo::projetos_acessiveis('cliente', [], [1, 2]));
    }
}
