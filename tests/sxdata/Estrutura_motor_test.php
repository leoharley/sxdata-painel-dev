<?php

use PHPUnit\Framework\TestCase;

/**
 * Compilação dos formulários da JMC e regras do motor em cenários reais.
 */
class Estrutura_motor_test extends TestCase {

    private static $defs = [];

    private static function def($nome) {
        if (!isset(self::$defs[$nome])) {
            $texto = file_get_contents(__DIR__ . '/../../docs/formularios/' . $nome . '.txt');
            list($def, $erros) = Estrutura_compilador::compilar($texto);
            if ($erros) {
                throw new RuntimeException("$nome não compila: " . json_encode($erros, JSON_UNESCAPED_UNICODE));
            }
            self::$defs[$nome] = $def;
        }
        return self::$defs[$nome];
    }

    private static function r($v) { return ['valor' => $v]; }
    private static function nsnr() { return ['nsnr' => TRUE]; }

    /** Pergunta compilada pelo código. */
    private static function pergunta(array $def, $codigo) {
        foreach ($def['etapas'] as $e) {
            foreach ($e['perguntas'] ?? [] as $p) {
                if ($p['codigo'] === $codigo) { return $p; }
            }
        }
        return NULL;
    }

    // ------------------------------------------------------------------
    // Compilação
    // ------------------------------------------------------------------

    public function test_formularios_da_jmc_compilam() {
        $geral = self::def('jmc_formulario_geral');
        $negocios = self::def('jmc_pequenos_negocios');
        $this->assertCount(4, $geral['etapas']);
        $this->assertSame('moradores', $geral['etapas'][1]['tipo']);
        $this->assertSame('q12', $geral['etapas'][1]['respondente']);
        $this->assertSame('q15', $geral['etapas'][1]['declarado']);
        $this->assertSame('morador', $geral['etapas'][2]['nivel']);
        $total = 0;
        foreach ($geral['etapas'] as $e) { $total += count($e['perguntas'] ?? []); }
        $this->assertSame(197 - 1 + 1, $total, 'Geral: 196 questões (a 17 é a lista de moradores) + a variável idade');
        $this->assertCount(137, $negocios['etapas'][0]['perguntas']);
    }

    public function test_textos_das_opcoes_viram_codigos_nas_condicoes() {
        $def = self::def('jmc_formulario_geral');
        $this->assertSame('(q27 == "nao")', self::pergunta($def, 'q28')['exibir_se']);
        $this->assertSame('(q19 em ["chefe", "conjuge_companheiro_a"])', self::pergunta($def, 'q20')['exibir_se']);
        $this->assertSame('(q57 comeca "sim")', self::pergunta($def, 'q58')['exibir_se']);
        $this->assertStringContainsString('fora_de ["pensionista", "empregado_a_domestico_a", "parente_do_a_empregado_a_domestico_a"]',
            self::pergunta($def, 'q109')['formula']);
    }

    public function test_complementos_e_exclusivas_detectados() {
        $def = self::def('jmc_formulario_geral');
        $o = self::pergunta($def, 'q133')['opcoes'][0];
        $this->assertSame('sim', $o['valor']);
        $this->assertSame(['tipo' => 'inteiro', 'rotulo' => 'Quantas'], $o['complemento']);
        $outros = self::pergunta($def, 'q23')['opcoes'][4];
        $this->assertSame('outra', $outros['valor']);
        $this->assertSame('texto', $outros['complemento']['tipo']);
        $nenhum = array_values(array_filter(self::pergunta($def, 'q33')['opcoes'], function ($o) { return $o['valor'] === 'nenhum'; }))[0];
        $this->assertTrue($nenhum['exclusiva']);
        $this->assertSame('q143', self::pergunta($def, 'q144')['opcoes_de']);
    }

    public function test_erros_de_compilacao_apontam_a_linha() {
        $texto = "@domicilio X\n1. Pergunta [unica]\n  - Sim\n  - Não\n2. Outra [texto]\n  se: q1 == \"Talvez\"\n3. Mais [texto]\n  se: q99 == 1\n";
        list($def, $erros) = Estrutura_compilador::compilar($texto);
        $this->assertNull($def);
        $this->assertSame(6, $erros[0]['linha']);
        $this->assertStringContainsString('não tem a opção "Talvez"', $erros[0]['mensagem']);
        $texto = "@domicilio X\n1. Pergunta [unica]\n  - Sim\n  - Não\n2. Outra [texto]\n  se: q99 == 1\n";
        list(, $erros) = Estrutura_compilador::compilar($texto);
        $this->assertStringContainsString('"q99", que não existe', $erros[0]['mensagem']);
    }

    public function test_domicilio_so_ve_morador_por_agregacao() {
        $texto = "@domicilio A\n1. X [inteiro]\n@moradores 2. Moradores\n@morador B\n3. Renda [moeda]\n@domicilio C\n4. Total [calculado]\n  formula: q3 + q1\n";
        list(, $erros) = Estrutura_compilador::compilar($texto);
        $this->assertStringContainsString('use soma_moradores()', $erros[0]['mensagem']);
    }

    // ------------------------------------------------------------------
    // Motor: Formulário Geral
    // ------------------------------------------------------------------

    /** Domicílio típico: chefe com renda, cônjuge com aposentadoria NS/NR, pensionista com renda. */
    private function cenario_renda($nsnr_aposentadoria) {
        $morador = function ($cid, $ordem, $nome, $posicao, $nasc, array $extra) {
            return ['client_id' => $cid, 'ordem' => $ordem, 'nome' => $nome, 'respostas' => array_merge([
                'q18' => self::r('mulher'), 'q19' => self::r($posicao), 'q21' => self::r($nasc),
                'q48' => self::r('nao'), 'q60' => self::r('nao'), 'q63' => self::r('nao'), 'q65' => self::r('nao'),
            ], $extra)];
        };
        $moradores = [
            $morador('m1', 1, 'Ana', 'chefe', '1980-03-10', [
                'q48' => self::r('sim_trabalhou'),
                'q49' => self::r('militar_ou_servidor_a_publico_a_estatutario_a'),
                'q53' => self::r(2000), 'q54' => self::r('sim'), 'q55' => self::r(500)]),
            $morador('m2', 2, 'Bia', 'conjuge_companheiro_a', '1950-01-01', [
                'q63' => self::r('sim'), 'q64' => $nsnr_aposentadoria ? self::nsnr() : self::r(1500)]),
            $morador('m3', 3, 'Caio', 'pensionista', '1990-07-07', [
                'q48' => self::r('sim_trabalhou'), 'q49' => self::r('conta_propria_trabalha_no_proprio_negocio_sem_empregados'),
                'q53' => self::r(3000)]),
        ];
        $dom = ['q5' => self::r('2026-11-25'), 'q15' => self::r(3), 'q102' => self::r(100), 'q103' => self::r(0),
            'q104' => self::r(0), 'q106' => self::r('')];
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        return $m->avaliar($dom, $moradores);
    }

    public function test_renda_total_e_per_capita_excluem_pensionista() {
        $r = $this->cenario_renda(FALSE);
        // Ana 2000 + 500, Bia 1500, Caio (pensionista) fora, + ajuda 100
        $this->assertSame(['valor' => 4100.0, 'incompleto' => FALSE], $r['calculados']['domicilio']['q109']);
        $this->assertSame(['valor' => 2050.0, 'incompleto' => FALSE], $r['calculados']['domicilio']['q110']);
    }

    public function test_nsnr_marca_renda_incompleta_sem_virar_zero_silencioso() {
        $r = $this->cenario_renda(TRUE);
        $this->assertTrue($r['calculados']['domicilio']['q109']['incompleto']);
        $this->assertTrue($r['calculados']['domicilio']['q110']['incompleto'], 'a per capita herda a sinalização');
        $this->assertSame(2600.0, $r['calculados']['domicilio']['q109']['valor']);
    }

    public function test_visibilidade_por_morador() {
        $r = $this->cenario_renda(FALSE);
        $v = $r['visiveis']['moradores'];
        $this->assertTrue($v['m1']['q20'], 'CPF do chefe');
        $this->assertTrue($v['m2']['q20'], 'CPF do cônjuge');
        $this->assertFalse($v['m3']['q20'], 'sem CPF para pensionista');
        $this->assertFalse($v['m2']['q53'], 'quem não trabalha não tem rendimento do trabalho');
        $this->assertTrue($v['m1']['q55']);
        $this->assertTrue($v['m1']['q25'], '46 anos: sabe ler e escrever');
    }

    public function test_idade_calculada_controla_pergunta() {
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        $r = $m->avaliar(['q5' => self::r('2026-11-25')], [
            ['client_id' => 'c', 'ordem' => 1, 'nome' => 'Davi', 'respostas' => ['q19' => self::r('chefe'), 'q21' => self::r('2022-01-01'),
                'q48' => self::r('sim_trabalhou')]],
        ]);
        $this->assertFalse($r['visiveis']['moradores']['c']['q25'], '4 anos: não pergunta leitura');
        $this->assertSame(4, $r['calculados']['moradores']['c']['idade']['valor']);
        $msgs = array_column($r['alertas'], 'mensagem');
        $this->assertContains('Morador com menos de 14 anos: confira a resposta da questão 48.', $msgs);
    }

    public function test_regras_da_lista_de_moradores() {
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        $sem_chefe = $m->avaliar(['q15' => self::r(2)], [
            ['client_id' => 'a', 'ordem' => 1, 'nome' => 'A', 'respostas' => ['q19' => self::r('filho_a')]],
        ]);
        $msgs = array_column($sem_chefe['erros'], 'mensagem');
        $this->assertContains('Um dos moradores deve ser identificado como Chefe (questão 19).', $msgs);
        $alertas = array_column($sem_chefe['alertas'], 'mensagem');
        $this->assertContains('Foram informadas 2 pessoas na questão 15, mas a lista tem 1 morador(es).', $alertas);

        $dois = $m->avaliar([], [
            ['client_id' => 'a', 'ordem' => 1, 'nome' => 'A', 'respostas' => ['q19' => self::r('chefe')]],
            ['client_id' => 'b', 'ordem' => 2, 'nome' => 'B', 'respostas' => ['q19' => self::r('chefe')]],
        ]);
        $this->assertContains('Mais de um morador foi identificado como Chefe (questão 19).', array_column($dois['erros'], 'mensagem'));

        $vazio = $m->avaliar([], []);
        $this->assertContains('Cadastre ao menos um morador.', array_column($vazio['erros'], 'mensagem'));
    }

    public function test_opcoes_filtradas_e_exclusivas() {
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        $r = $m->avaliar(['q143' => self::r(['cisterna', 'rio_corrego']), 'q144' => self::r('rede_de_abastecimento_embasa'),
            'q33' => self::r(['nenhum', 'artesanato'])], []);
        $ok = array_column($r['opcoes']['domicilio']['q144'], 'valor');
        $this->assertSame(['cisterna', 'rio_corrego', 'ns_nr'], $ok);
        $erros = array_column(array_filter($r['erros'], function ($e) { return $e['codigo'] === 'q144'; }), 'mensagem');
        $this->assertSame(['Questão 144: opção inválida.'], $erros);
    }

    public function test_complemento_obrigatorio_e_obrigatoriedade() {
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        $r = $m->avaliar(['q133' => self::r('sim'), 'q134' => ['valor' => 'sim', 'complementos' => ['sim' => '2']]], []);
        $por = [];
        foreach ($r['erros'] as $e) { $por[$e['codigo']][] = $e['mensagem']; }
        $this->assertSame(['Questão 133: preencha "Quantas" da opção "Sim. Quantas:".'], $por['q133']);
        $this->assertArrayNotHasKey('q134', $por);
        $this->assertSame(['Questão 1 é obrigatória.'], $por['q1']);
        $this->assertArrayNotHasKey('q100', $por, 'questão opcional');
        $this->assertArrayNotHasKey('q101', $por, 'oculta: 100 em branco');
    }

    public function test_alerta_de_pequeno_negocio() {
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        $r = $m->avaliar(['q123' => self::r('sim_de_algum_morador_do_domicilio')], []);
        $this->assertTrue($r['visiveis']['domicilio']['q124']);
        $this->assertStringStartsWith('Imóvel com negócio', array_column($r['alertas'], 'mensagem')[0] ?? '');
    }

    public function test_duracao_calculada() {
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        $r = $m->avaliar(['q6' => self::r('08:50'), 'q194' => self::r('10:05')], []);
        $this->assertSame(['valor' => 75, 'incompleto' => FALSE], $r['calculados']['domicilio']['q195']);
    }

    // ------------------------------------------------------------------
    // Motor: Pequenos Negócios
    // ------------------------------------------------------------------

    public function test_pulo_para_a_situacao_final() {
        $m = new Estrutura_motor(self::def('jmc_pequenos_negocios'));
        $r = $m->avaliar(['q6' => self::r('negocio_fechado_ou_parado')], []);
        $v = $r['visiveis']['domicilio'];
        $this->assertTrue($v['q6']);
        $this->assertFalse($v['q7']);
        $this->assertFalse($v['q120']);
        $this->assertTrue($v['q121']);
        $this->assertTrue($v['q136']);
        $this->assertFalse($v['q137'], 'sem autorização de foto registrada');
        $this->assertContains('A visita não resultou em entrevista (questões 6 ou 8): marque "Não realizada".',
            array_column($r['alertas'], 'mensagem'));
    }

    public function test_recusa_pula_e_aceite_segue() {
        $m = new Estrutura_motor(self::def('jmc_pequenos_negocios'));
        $loc = 'negocio_localizado_com_pessoa_disponivel_para_a_entrevista';
        $recusa = $m->avaliar(['q6' => self::r($loc), 'q8' => self::r('nao')], []);
        $this->assertFalse($recusa['visiveis']['domicilio']['q9']);
        $this->assertTrue($recusa['visiveis']['domicilio']['q121']);
        $aceite = $m->avaliar(['q6' => self::r($loc), 'q8' => self::r('sim'), 'q9' => self::r('sim')], []);
        $this->assertTrue($aceite['visiveis']['domicilio']['q9']);
        $this->assertTrue($aceite['visiveis']['domicilio']['q137']);
    }

    public function test_bloco_d_nao_repete_o_formulario_geral() {
        $m = new Estrutura_motor(self::def('jmc_pequenos_negocios'));
        $loc = 'negocio_localizado_com_pessoa_disponivel_para_a_entrevista';
        $base = ['q6' => self::r($loc), 'q8' => self::r('sim'), 'q23' => self::r('sim')];
        $em_casa = $m->avaliar($base + ['q24' => self::r('sim'), 'q49' => self::r('dentro_da_propria_casa')], []);
        $this->assertFalse($em_casa['visiveis']['domicilio']['q92']);
        $this->assertFalse($em_casa['visiveis']['domicilio']['q102']);
        $this->assertFalse($em_casa['visiveis']['domicilio']['q26'], 'perfil do dono já está no Formulário Geral');
        $ponto = $m->avaliar($base + ['q24' => self::r('sim'), 'q49' => self::r('em_ponto_comercial_separado_da_casa'),
            'q92' => self::r('alugado')], []);
        $this->assertTrue($ponto['visiveis']['domicilio']['q92']);
        $this->assertTrue($ponto['visiveis']['domicilio']['q93']);
        $this->assertTrue($ponto['visiveis']['domicilio']['q102']);
        $fora = $m->avaliar(['q6' => self::r($loc), 'q8' => self::r('sim'), 'q23' => self::r('nao')], []);
        $this->assertTrue($fora['visiveis']['domicilio']['q26'], 'dono de fora: pergunta o perfil');
        $this->assertFalse($fora['visiveis']['domicilio']['q25']);
    }

    public function test_nsnr_abre_pergunta_de_faixa() {
        $m = new Estrutura_motor(self::def('jmc_pequenos_negocios'));
        $ok = ['q6' => self::r('negocio_localizado_com_pessoa_disponivel_para_a_entrevista'), 'q8' => self::r('sim')];
        $r = $m->avaliar($ok + ['q82' => self::nsnr(), 'q68' => self::r(1500)], []);
        $this->assertTrue($r['visiveis']['domicilio']['q83']);
        $this->assertTrue($r['visiveis']['domicilio']['q69']);
        $r = $m->avaliar($ok + ['q82' => self::r(800), 'q68' => self::nsnr()], []);
        $this->assertFalse($r['visiveis']['domicilio']['q83']);
        $this->assertFalse($r['visiveis']['domicilio']['q69']);
    }
}
