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
        $this->assertCount(5, $geral['etapas']);
        $this->assertSame('moradores', $geral['etapas'][2]['tipo']);
        $this->assertSame('q13', $geral['etapas'][2]['respondente']);
        $this->assertSame('q16', $geral['etapas'][2]['declarado']);
        $this->assertSame('morador', $geral['etapas'][3]['nivel']);
        $total = 0;
        foreach ($geral['etapas'] as $e) { $total += count($e['perguntas'] ?? []); }
        $this->assertSame(201 - 1 + 1, $total, 'Geral: 200 questões (a 18 é a lista de moradores) + a variável idade');
        $this->assertCount(137, $negocios['etapas'][0]['perguntas']);
    }

    public function test_textos_das_opcoes_viram_codigos_nas_condicoes() {
        $def = self::def('jmc_formulario_geral');
        $this->assertSame('(q28 == "nao")', self::pergunta($def, 'q29')['exibir_se']);
        $this->assertSame('(q20 em ["chefe", "conjuge_companheiro_a"])', self::pergunta($def, 'q21')['exibir_se']);
        $this->assertSame('(q58 comeca "sim")', self::pergunta($def, 'q59')['exibir_se']);
        $this->assertStringContainsString('fora_de ["pensionista", "empregado_a_domestico_a", "parente_do_a_empregado_a_domestico_a"]',
            self::pergunta($def, 'q110')['formula']);
    }

    public function test_complementos_e_exclusivas_detectados() {
        $def = self::def('jmc_formulario_geral');
        $o = self::pergunta($def, 'q134')['opcoes'][0];
        $this->assertSame('sim', $o['valor']);
        $this->assertSame(['tipo' => 'inteiro', 'rotulo' => 'Quantas'], $o['complemento']);
        $outros = self::pergunta($def, 'q24')['opcoes'][4];
        $this->assertSame('outra', $outros['valor']);
        $this->assertSame('texto', $outros['complemento']['tipo']);
        $nenhum = array_values(array_filter(self::pergunta($def, 'q34')['opcoes'], function ($o) { return $o['valor'] === 'nenhum'; }))[0];
        $this->assertTrue($nenhum['exclusiva']);
        $this->assertSame('q144', self::pergunta($def, 'q145')['opcoes_de']);
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
                'q19' => self::r('mulher'), 'q20' => self::r($posicao), 'q22' => self::r($nasc),
                'q49' => self::r('nao'), 'q61' => self::r('nao'), 'q64' => self::r('nao'), 'q66' => self::r('nao'),
            ], $extra)];
        };
        $moradores = [
            $morador('m1', 1, 'Ana', 'chefe', '1980-03-10', [
                'q49' => self::r('sim_trabalhou'),
                'q50' => self::r(['militar_ou_servidor_a_publico_a_estatutario_a']),
                'q54' => self::r(2000), 'q55' => self::r('sim'), 'q56' => self::r(500)]),
            $morador('m2', 2, 'Bia', 'conjuge_companheiro_a', '1950-01-01', [
                'q64' => self::r('sim'), 'q65' => $nsnr_aposentadoria ? self::nsnr() : self::r(1500)]),
            $morador('m3', 3, 'Caio', 'pensionista', '1990-07-07', [
                'q49' => self::r('sim_trabalhou'), 'q50' => self::r(['conta_propria_trabalha_no_proprio_negocio_sem_empregados']),
                'q54' => self::r(3000)]),
        ];
        $dom = ['q2' => self::r('2026-11-25'), 'q16' => self::r(3), 'q103' => self::r(100), 'q104' => self::r(0),
            'q105' => self::r(0), 'q107' => self::r('')];
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        return $m->avaliar($dom, $moradores);
    }

    public function test_renda_total_e_per_capita_excluem_pensionista() {
        $r = $this->cenario_renda(FALSE);
        // Ana 2000 + 500, Bia 1500, Caio (pensionista) fora, + ajuda 100
        $this->assertSame(['valor' => 4100.0, 'incompleto' => FALSE], $r['calculados']['domicilio']['q110']);
        $this->assertSame(['valor' => 2050.0, 'incompleto' => FALSE], $r['calculados']['domicilio']['q111']);
    }

    public function test_nsnr_marca_renda_incompleta_sem_virar_zero_silencioso() {
        $r = $this->cenario_renda(TRUE);
        $this->assertTrue($r['calculados']['domicilio']['q110']['incompleto']);
        $this->assertTrue($r['calculados']['domicilio']['q111']['incompleto'], 'a per capita herda a sinalização');
        $this->assertSame(2600.0, $r['calculados']['domicilio']['q110']['valor']);
    }

    public function test_visibilidade_por_morador() {
        $r = $this->cenario_renda(FALSE);
        $v = $r['visiveis']['moradores'];
        $this->assertTrue($v['m1']['q21'], 'CPF do chefe');
        $this->assertTrue($v['m2']['q21'], 'CPF do cônjuge');
        $this->assertFalse($v['m3']['q21'], 'sem CPF para pensionista');
        $this->assertFalse($v['m2']['q54'], 'quem não trabalha não tem rendimento do trabalho');
        $this->assertTrue($v['m1']['q56']);
        $this->assertTrue($v['m1']['q26'], '46 anos: sabe ler e escrever');
    }

    public function test_idade_calculada_controla_pergunta() {
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        $r = $m->avaliar(['q2' => self::r('2026-11-25')], [
            ['client_id' => 'c', 'ordem' => 1, 'nome' => 'Davi', 'respostas' => ['q20' => self::r('chefe'), 'q22' => self::r('2022-01-01'),
                'q49' => self::r('sim_trabalhou')]],
        ]);
        $this->assertFalse($r['visiveis']['moradores']['c']['q26'], '4 anos: não pergunta leitura');
        $this->assertSame(4, $r['calculados']['moradores']['c']['idade']['valor']);
        $msgs = array_column($r['alertas'], 'mensagem');
        $this->assertContains('Morador com menos de 14 anos: confira a resposta da questão 49.', $msgs);
    }

    public function test_regras_da_lista_de_moradores() {
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        $sem_chefe = $m->avaliar(['q16' => self::r(2)], [
            ['client_id' => 'a', 'ordem' => 1, 'nome' => 'A', 'respostas' => ['q20' => self::r('filho_a')]],
        ]);
        $msgs = array_column($sem_chefe['erros'], 'mensagem');
        $this->assertContains('Um dos moradores deve ser identificado como Chefe (questão 20).', $msgs);
        $alertas = array_column($sem_chefe['alertas'], 'mensagem');
        $this->assertContains('Foram informadas 2 pessoas na questão 16, mas a lista tem 1 morador(es).', $alertas);

        $dois = $m->avaliar([], [
            ['client_id' => 'a', 'ordem' => 1, 'nome' => 'A', 'respostas' => ['q20' => self::r('chefe')]],
            ['client_id' => 'b', 'ordem' => 2, 'nome' => 'B', 'respostas' => ['q20' => self::r('chefe')]],
        ]);
        $this->assertContains('Mais de um morador foi identificado como Chefe (questão 20).', array_column($dois['erros'], 'mensagem'));

        $vazio = $m->avaliar([], []);
        $this->assertContains('Cadastre ao menos um morador.', array_column($vazio['erros'], 'mensagem'));
    }

    public function test_opcoes_filtradas_e_exclusivas() {
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        $r = $m->avaliar(['q144' => self::r(['cisterna', 'rio_corrego']), 'q145' => self::r('rede_de_abastecimento_embasa'),
            'q34' => self::r(['nenhum', 'artesanato'])], []);
        $ok = array_column($r['opcoes']['domicilio']['q145'], 'valor');
        $this->assertSame(['cisterna', 'rio_corrego', 'ns_nr'], $ok);
        $erros = array_column(array_filter($r['erros'], function ($e) { return $e['codigo'] === 'q145'; }), 'mensagem');
        $this->assertSame(['Questão 145: opção inválida.'], $erros);
    }

    public function test_complemento_obrigatorio_e_obrigatoriedade() {
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        $r = $m->avaliar(['q134' => self::r('sim'), 'q135' => ['valor' => 'sim', 'complementos' => ['sim' => '2']]], []);
        $por = [];
        foreach ($r['erros'] as $e) { $por[$e['codigo']][] = $e['mensagem']; }
        $this->assertSame(['Questão 134: preencha "Quantas" da opção "Sim. Quantas:".'], $por['q134']);
        $this->assertArrayNotHasKey('q135', $por);
        $this->assertSame(['Questão 6 é obrigatória.'], $por['q6']);
        $this->assertArrayNotHasKey('q101', $por, 'questão opcional');
        $this->assertArrayNotHasKey('q102', $por, 'oculta: 101 em branco');
    }

    public function test_alerta_de_pequeno_negocio() {
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        $r = $m->avaliar(['q124' => self::r('sim_de_algum_morador_do_domicilio')], []);
        $this->assertTrue($r['visiveis']['domicilio']['q125']);
        $this->assertStringStartsWith('Imóvel com negócio', array_column($r['alertas'], 'mensagem')[0] ?? '');
    }

    public function test_duracao_calculada() {
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        $r = $m->avaliar(['q3' => self::r('08:50'), 'q191' => self::r('10:05')], []);
        $this->assertSame(['valor' => 75, 'incompleto' => FALSE], $r['calculados']['domicilio']['q192']);
    }

    /** Ajustes de 27/11/2026: recusa na abertura, 50 em múltipla escolha e até 5 fatores na 182. */
    public function test_recusa_na_abertura_fecha_o_resto_e_permite_finalizar() {
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        $abordagem = ['q1' => self::r('itapicuru'), 'q2' => self::r('2026-11-28'), 'q3' => self::r('09:00'), 'q4' => self::r('IMV-01')];
        $r = $m->avaliar($abordagem + ['q5' => self::r('recusa')], []);
        $this->assertSame(['q1', 'q2', 'q3', 'q4', 'q5'], array_keys(array_filter($r['visiveis']['domicilio'])));
        $this->assertSame([], $r['erros'], 'sem moradores e sem as demais questões');
        $aceita = $m->avaliar($abordagem + ['q5' => self::r('morador_a_disponivel_para_a_entrevista')], []);
        $this->assertTrue($aceita['visiveis']['domicilio']['q6']);
        $this->assertContains('Cadastre ao menos um morador.', array_column($aceita['erros'], 'mensagem'));
    }

    public function test_posicao_na_ocupacao_em_multipla_escolha() {
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        $aux = 'trabalhador_a_familiar_auxiliar_ajuda_membro_do_domicilio_se';
        $cp = 'conta_propria_trabalha_no_proprio_negocio_sem_empregados';
        $vis = function (array $q50) use ($m) {
            $r = $m->avaliar(['q2' => self::r('2026-11-28')], [['client_id' => 'a', 'ordem' => 1, 'nome' => 'A',
                'respostas' => ['q20' => self::r('chefe'), 'q49' => self::r('sim_trabalhou'), 'q50' => self::r($q50)]]]);
            return [$r['visiveis']['moradores']['a']['q51'], $r['visiveis']['moradores']['a']['q54'],
                array_column(array_filter($r['erros'], function ($e) { return $e['codigo'] === 'q50'; }), 'mensagem')];
        };
        $this->assertSame([FALSE, FALSE, []], $vis([$aux]), 'só familiar auxiliar: sem CNPJ e sem rendimento');
        $this->assertSame([TRUE, TRUE, []], $vis([$aux, $cp]));
        $this->assertSame([TRUE, TRUE, []], $vis(['empregado_a_do_setor_privado_sem_carteira_de_trabalho_assina', $cp]));
        $this->assertSame(['Questão 50: "NS/NR" não pode ser marcada junto com outras opções.'], $vis(['ns_nr', $cp])[2]);
    }

    public function test_bloco_de_perspectivas_futuras() {
        $m = new Estrutura_motor(self::def('jmc_formulario_geral'));
        $base = ['q5' => self::r('morador_a_disponivel_para_a_entrevista')];
        $d = function (array $resp) use ($m, $base) { return $m->avaliar($base + $resp, [])['visiveis']['domicilio']; };
        $this->assertFalse($d(['q179' => self::r('nunca_pensei_sobre_isso')])['q180']);
        $this->assertFalse($d(['q179' => self::r('na_nao_reside_na_comunidade')])['q180']);
        $this->assertTrue($d(['q179' => self::r('tenho_algumas_preocupacoes_com_essa_proximidade')])['q180']);
        $sim = $d(['q181' => self::r('sim_e_ja_consideramos_outros_locais'), 'q184' => self::r('sim')]);
        $this->assertTrue($sim['q182'] && $sim['q183'] && $sim['q184'] && $sim['q185']);
        $talvez = $d(['q181' => self::r('nao_mas_considerariamos_essa_possibilidade')]);
        $this->assertTrue($talvez['q182'] && $talvez['q183']);
        $this->assertFalse($talvez['q184']);
        $fica = $d(['q181' => self::r('nao_preferimos_permanecer_onde_vivemos_atualmente')]);
        $this->assertFalse($fica['q182'] || $fica['q183'] || $fica['q184']);

        $ops = array_column($m->pergunta('q182')['opcoes'], 'valor');
        $erros = function (array $marcadas) use ($m, $base) {
            $r = $m->avaliar($base + ['q181' => self::r('sim_e_ja_consideramos_outros_locais'), 'q182' => self::r($marcadas)], []);
            return array_column(array_filter($r['erros'], function ($e) { return $e['codigo'] === 'q182'; }), 'mensagem');
        };
        $this->assertSame([], $erros(array_slice($ops, 0, 5)));
        $this->assertSame(['Questão 182: marque no máximo 5 opções.'], $erros(array_slice($ops, 0, 6)));
    }

    public function test_lista_de_moradores_com_se_e_max_so_na_multipla() {
        $texto = "@domicilio A\n1. Aceita? [unica]\n  - Sim\n  - Não\n@moradores 2. Moradores\n  se: q1 == \"Sim\"\n@morador B\n  se: q1 == \"Sim\"\n3. Idade [inteiro]\n";
        list($def, $erros) = Estrutura_compilador::compilar($texto);
        $this->assertSame([], $erros);
        $this->assertSame('(q1 == "sim")', $def['etapas'][1]['se']);
        $m = new Estrutura_motor($def);
        $this->assertSame([], $m->avaliar(['q1' => self::r('nao')], [])['erros']);
        $this->assertContains('Cadastre ao menos um morador.', array_column($m->avaliar(['q1' => self::r('sim')], [])['erros'], 'mensagem'));
        list(, $erros) = Estrutura_compilador::compilar("@domicilio A\n1. X [unica max=2]\n  - Sim\n  - Não\n");
        $this->assertStringContainsString('max=N só vale para [multipla]', $erros[0]['mensagem']);
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
