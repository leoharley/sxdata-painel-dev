<?php

use PHPUnit\Framework\TestCase;

class Estrutura_envio_test extends TestCase {

    private static function def() {
        list($def) = Estrutura_compilador::compilar(file_get_contents(__DIR__ . '/../../docs/formularios/jmc_formulario_geral.txt'));
        return $def;
    }

    public function test_normaliza_lista_ou_mapa_e_ignora_lixo() {
        list($d, $erros) = Estrutura_envio::normalizar([
            'client_id' => 'abc', 'questionnaire_id' => '7',
            'respostas' => [['codigo' => 'q1', 'valor' => 'sim'], ['codigo' => 'DROP TABLE', 'valor' => 1], 'lixo'],
            'moradores' => [
                ['client_id' => 'm1', 'nome' => '  Ana   Maria ', 'respostas' => ['q18' => ['valor' => 'mulher']]],
                ['nome' => 'Sem id'],
                ['client_id' => 'm1', 'nome' => 'Repetido'],
            ],
            'photo_path' => '/storage/emulated/0/DCIM/foto 1.jpg',
            'latitude' => '-11.18', 'longitude' => 0,
        ]);
        $this->assertSame([], $erros);
        $this->assertSame(['q1' => ['valor' => 'sim']], $d['respostas']);
        $this->assertSame('Ana Maria', $d['moradores'][0]['nome']);
        $this->assertSame('abc-m2', $d['moradores'][1]['client_id']);
        $this->assertSame('abc-m3', $d['moradores'][2]['client_id'], 'client_id repetido ganha um próprio');
        $this->assertNull($d['photo_path'], 'nome com espaço não é arquivo do servidor');
        $this->assertSame(-11.18, $d['latitude']);
        $this->assertNull($d['longitude']);
    }

    public function test_corpo_sem_identificacao_e_recusado() {
        list($d, $erros) = Estrutura_envio::normalizar(['respostas' => []]);
        $this->assertNull($d);
        $this->assertCount(2, $erros);
    }

    public function test_conferir_descarta_oculta_e_aponta_divergencia() {
        list($d) = Estrutura_envio::normalizar([
            'client_id' => 'x', 'questionnaire_id' => 1,
            'respostas' => ['q100' => ['valor' => ''], 'q101' => ['valor' => 50], 'q102' => ['valor' => 300],
                'q196' => ['valor' => ['lat' => -11.2, 'lng' => -40.5]], 'q197' => ['valor' => 'fachada_1.jpg']],
            'moradores' => [['client_id' => 'a', 'nome' => 'Ana', 'respostas' => [
                'q19' => ['valor' => 'chefe'], 'q63' => ['valor' => 'sim'], 'q64' => ['valor' => 1000]]]],
            'calculados' => ['domicilio' => ['q109' => ['valor' => 999, 'incompleto' => FALSE]]],
        ]);
        $c = Estrutura_envio::conferir(self::def(), $d);
        $this->assertArrayNotHasKey('q101', $c['respostas_domicilio'], 'q101 oculta (q100 vazia) não é gravada');
        $this->assertSame(1300.0, $c['calculados']['domicilio']['q109']['valor']);
        $this->assertSame('pendente', $c['revisao']);
        $this->assertSame('q109', $c['divergencias'][0]['codigo']);
        $this->assertSame('fachada_1.jpg', $c['foto']);
        $this->assertSame([-11.2, -40.5], $c['coordenadas']);
        $this->assertArrayHasKey('q64', $c['respostas_moradores']['a']);
    }

    public function test_rotulo_resposta() {
        $def = self::def();
        $m = new Estrutura_motor($def);
        $this->assertSame('Sim: 2', Estrutura_motor::rotulo_resposta($m->pergunta('q133'), ['valor' => 'sim', 'complementos' => ['sim' => '2']]));
        $this->assertSame('NS/NR', Estrutura_motor::rotulo_resposta($m->pergunta('q53'), ['nsnr' => TRUE]));
        $this->assertSame('R$ 1.234,50', Estrutura_motor::rotulo_resposta($m->pergunta('q109'), ['valor' => 1234.5]));
        $this->assertSame(1234.5, Estrutura_motor::rotulo_resposta($m->pergunta('q109'), ['valor' => 1234.5], TRUE));
        $this->assertSame('1 h 15 min', Estrutura_motor::rotulo_resposta($m->pergunta('q195'), ['valor' => 75]));
        $this->assertSame('25/11/2026', Estrutura_motor::rotulo_resposta($m->pergunta('q5'), ['valor' => '2026-11-25']));
    }
}
