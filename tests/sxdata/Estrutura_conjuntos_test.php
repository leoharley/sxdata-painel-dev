<?php

use PHPUnit\Framework\TestCase;

/**
 * Frente L: conjuntos de perguntas (@usar), grupos repetidos (@repetir),
 * abertura por resposta (se na etapa), por tema e por entrevistado.
 */
class Estrutura_conjuntos_test extends TestCase {

    private static function compilar($texto, $biblioteca = []) {
        $resolver = function ($codigo) use ($biblioteca) {
            return isset($biblioteca[$codigo]) ? ['id' => 1, 'codigo' => $codigo, 'versao' => 3, 'nome' => ucfirst($codigo), 'fonte' => $biblioteca[$codigo]] : NULL;
        };
        return Estrutura_compilador::compilar($texto, $resolver);
    }

    private static function r($v) { return ['valor' => $v]; }

    public function test_grupo_repetido_por_quantidade_e_soma() {
        $texto = "@domicilio A\n1. Quantos negócios? [inteiro]\n@repetir negocios Negócios\n  por: q1\n  max: 5\n  rotulo: Negócio {n}\n"
            . "2. Nome do negócio [texto]\n3. Faturamento [moeda nsnr]\n@domicilio B\n4. Total [calculado formato=moeda]\n  formula: soma_grupo(\"negocios\", q3)\n";
        list($def, $erros) = self::compilar($texto);
        $this->assertSame([], $erros);
        $this->assertSame(['por' => 'q1', 'max' => 5, 'rotulo' => 'Negócio {n}'], $def['etapas'][1]['repetir']);
        $m = new Estrutura_motor($def);
        $grupos = ['negocios' => [
            ['client_id' => 'a', 'indice' => 1, 'respostas' => ['q2' => self::r('Bar'), 'q3' => self::r(1000)]],
            ['client_id' => 'b', 'indice' => 2, 'respostas' => ['q2' => self::r('Salão'), 'q3' => ['nsnr' => TRUE]]],
        ]];
        $r = $m->avaliar(['q1' => self::r(3)], [], $grupos);
        $this->assertSame(['valor' => 1000.0, 'incompleto' => TRUE], $r['calculados']['domicilio']['q4']);
        $this->assertTrue($r['visiveis']['grupos']['negocios']['a']['q2']);
        $this->assertContains('"Negócios": a questão 1 informa 3, mas há 2 preenchido(s).', array_column($r['erros'], 'mensagem'));
        $this->assertSame('Negócio 2', Estrutura_motor::rotulo_instancia($def['etapas'][1], 2));
    }

    public function test_grupo_nao_e_visto_fora_sem_agregacao() {
        list(, $erros) = self::compilar("@repetir g G\n1. X [inteiro]\n@domicilio D\n2. Y [calculado]\n  formula: q1 + 1\n");
        $this->assertStringContainsString('use soma_grupo()', $erros[0]['mensagem']);
    }

    public function test_etapa_aberta_por_resposta() {
        $texto = "@domicilio A\n1. Possui atividade comercial? [unica]\n  - Sim\n  - Não\n@domicilio Atividade econômica\n  se: q1 == \"Sim\"\n2. Qual atividade? [texto]\n3. Faturamento [moeda]\n";
        list($def, $erros) = self::compilar($texto);
        $this->assertSame([], $erros);
        $this->assertSame('(q1 == "sim")', $def['etapas'][1]['se']);
        $m = new Estrutura_motor($def);
        $nao = $m->avaliar(['q1' => self::r('nao')]);
        $this->assertFalse($nao['visiveis']['domicilio']['q2']);
        $this->assertFalse($nao['visiveis']['domicilio']['q3']);
        $this->assertSame([], $nao['erros'], 'oculta não é obrigatória');
        $sim = $m->avaliar(['q1' => self::r('sim')]);
        $this->assertTrue($sim['visiveis']['domicilio']['q3']);
    }

    public function test_temas_viram_pergunta_e_abrem_etapas() {
        $texto = "# temas: Reassentamento, Saúde\n@domicilio Geral\n1. Povoado [texto]\n@domicilio Moradia\n  tema: Reassentamento\n2. Benfeitorias [texto]\n@domicilio Saúde\n  tema: Saúde\n3. Posto mais perto [texto]\n";
        list($def, $erros) = self::compilar($texto);
        $this->assertSame([], $erros);
        $this->assertSame('temas', $def['etapas'][0]['perguntas'][0]['codigo']);
        $this->assertSame(['reassentamento', 'saude'], array_column($def['etapas'][0]['perguntas'][0]['opcoes'], 'valor'));
        $m = new Estrutura_motor($def);
        $r = $m->avaliar(['temas' => self::r(['saude'])]);
        $this->assertFalse($r['visiveis']['domicilio']['q2']);
        $this->assertTrue($r['visiveis']['domicilio']['q3']);
        list(, $erros) = self::compilar("@domicilio X\n  tema: Saúde\n1. A [texto]\n");
        $this->assertStringContainsString('# temas:', $erros[0]['mensagem']);
    }

    public function test_entrevistado_categoria_e_preenchimento() {
        $texto = "# entrevistado: opcional\n@domicilio Geral\n1. Nome do entrevistado [texto]\n  preencher: ent_nome\n"
            . "@domicilio Comércio\n  categoria: Comerciante\n2. Ponto próprio? [unica]\n  - Sim\n  - Não\n"
            . "@domicilio Liderança\n  entrevistado: L01, L02\n3. Associação [texto]\n4. Saudação [mensagem]\n  se: ent_localidade == \"Itapicuru\"\n";
        list($def, $erros) = self::compilar($texto);
        $this->assertSame([], $erros);
        $this->assertSame(['modo' => 'opcional'], $def['entrevistado']);
        $this->assertSame('ent_nome', $def['etapas'][0]['perguntas'][0]['preencher']);
        $m = new Estrutura_motor($def);
        $r = $m->avaliar([], [], [], ['entrevistado' => ['codigo' => 'L02', 'nome' => 'Zé', 'categoria' => 'comerciante', 'localidade' => 'Itapicuru']]);
        $this->assertTrue($r['visiveis']['domicilio']['q2']);
        $this->assertTrue($r['visiveis']['domicilio']['q3']);
        $this->assertTrue($r['visiveis']['domicilio']['q4']);
        $r = $m->avaliar([], [], [], ['entrevistado' => ['codigo' => 'X9', 'categoria' => 'chefe_de_familia']]);
        $this->assertFalse($r['visiveis']['domicilio']['q2']);
        $this->assertFalse($r['visiveis']['domicilio']['q3']);
        $r = $m->avaliar([]);
        $this->assertFalse($r['visiveis']['domicilio']['q2'], 'sem entrevistado escolhido, etapa por categoria fica fechada');
        list(, $erros) = self::compilar("@domicilio X\n  categoria: Comerciante\n1. A [texto]\n");
        $this->assertStringContainsString('# entrevistado:', $erros[0]['mensagem']);
    }

    public function test_usar_conjunto_da_biblioteca() {
        $bib = [
            'composicao' => "@repetir pessoas Composição do domicílio\n  rotulo: Morador {n}\n10. Nome [texto]\n11. Idade [inteiro]\n",
            'renda' => "@domicilio Renda\n20. Renda total [moeda]\n",
            'quebrado' => "@domicilio X\n1. Q [unica]\n  - Só uma\n",
        ];
        $texto = "@domicilio A\n1. Quantas pessoas? [inteiro]\n@usar composicao\n  por: q1\n@usar renda Renda da família\n  se: q1 > 0\n";
        list($def, $erros) = self::compilar($texto, $bib);
        $this->assertSame([], $erros);
        $this->assertSame('grupo', $def['etapas'][1]['nivel']);
        $this->assertEquals(['codigo' => 'composicao', 'id' => 1, 'versao' => 3, 'nome' => 'Composicao'],
            array_intersect_key($def['etapas'][1]['conjunto'], array_flip(['codigo', 'id', 'versao', 'nome'])));
        $this->assertSame('q1', $def['etapas'][1]['repetir']['por']);
        $this->assertSame('Renda da família', $def['etapas'][2]['titulo']);
        $this->assertSame('(q1 > 0)', $def['etapas'][2]['se']);

        list(, $erros) = self::compilar("@domicilio A\n1. X [texto]\n@usar inexistente\n", $bib);
        $this->assertStringContainsString('não existe na biblioteca', $erros[0]['mensagem']);
        list(, $erros) = self::compilar("@domicilio A\n1. X [texto]\n@usar quebrado\n", $bib);
        $this->assertStringContainsString('Conjunto "quebrado", linha 2', $erros[0]['mensagem']);
        list(, $erros) = self::compilar("@domicilio A\n20. X [texto]\n@usar renda\n", $bib);
        $this->assertStringContainsString('Código repetido: q20', $erros[0]['mensagem']);
    }
}
