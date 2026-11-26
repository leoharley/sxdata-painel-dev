<?php

use PHPUnit\Framework\TestCase;

/** Parte I, frente D: fórmulas do PEA conferidas contra os exemplos do PEA e do formulário de avaliação. */
class Pea_motor_test extends TestCase {

    private function r() {
        return Pea_motor::regras_padrao();
    }

    public function test_is_com_denominador_participantes_e_respondentes() {
        $p6 = ['muito_satisfeito' => 12, 'satisfeito' => 6, 'indiferente' => 2];
        $this->assertSame(72.0, Pea_motor::is($p6, 25, $this->r()), '18 de 25 presentes');
        $r = $this->r();
        $r['is']['denominador'] = 'respondentes';
        $this->assertSame(90.0, Pea_motor::is($p6, 25, $r), '18 de 20 respondentes');
        $this->assertNull(Pea_motor::is([], 0, $this->r()));
        $this->assertSame(100.0, Pea_motor::is(['satisfeito' => 30], 20, $this->r()), 'teto 100 se a lista de presença vier menor');
    }

    public function test_iia_exemplos_do_formulario() {
        // a = 0,5 e c = 0,5 -> 83 (exemplo do formulário de avaliação)
        $this->assertSame(83.3, Pea_motor::iia(['a' => 5, 'c' => 5], $this->r()));
        $this->assertSame(100.0, Pea_motor::iia(['a' => 10], $this->r()), 'todos ampliaram');
        $this->assertSame(66.7, Pea_motor::iia(['c' => 10], $this->r()), 'todos passaram a conhecer');
        $this->assertSame(0.0, Pea_motor::iia(['d' => 10], $this->r()));
        // Todos em (b): −33 pela fórmula; piso em 0 (ponto 2 da seção 20)
        $this->assertSame(0.0, Pea_motor::iia(['b' => 10], $this->r()));
        $r = $this->r();
        $r['iia']['piso_zero'] = FALSE;
        $this->assertSame(-33.3, Pea_motor::iia(['b' => 10], $r));
        // Metade (a), metade (b): 33 (seção 20)
        $this->assertSame(33.3, Pea_motor::iia(['a' => 5, 'b' => 5], $this->r()));
        $this->assertNull(Pea_motor::iia([], $this->r()));
    }

    public function test_ip_pelas_faixas_do_projeto() {
        $f = ['baixa' => [10, 29], 'media' => [30, 49], 'alta' => [50, NULL]]; // Eventos Socioambientais
        $r = $this->r();
        $this->assertSame(100, Pea_motor::ip(50, $f, TRUE, $r));
        $this->assertSame(60, Pea_motor::ip(30, $f, TRUE, $r));
        $this->assertSame(30, Pea_motor::ip(10, $f, TRUE, $r));
        $this->assertSame(30, Pea_motor::ip(4, $f, TRUE, $r), 'abaixo da faixa baixa = baixa (ponto 3)');
        $this->assertSame(0, Pea_motor::ip(80, $f, FALSE, $r), 'não realizada');
        $this->assertNull(Pea_motor::ip(80, NULL, TRUE, $r), 'projeto sem faixa (Comunicados, Workshop)');
    }

    public function test_icc_e_prazo() {
        $r = $this->r();
        $this->assertTrue(Pea_motor::no_prazo([1, 2], [1, 2], $r));
        $this->assertTrue(Pea_motor::no_prazo([1, 2], [1, 1], $r), 'antes do previsto conta');
        $this->assertFalse(Pea_motor::no_prazo([1, 4], [2, 1], $r));
        $r['icc']['tolerancia_trimestres'] = 1;
        $this->assertTrue(Pea_motor::no_prazo([1, 4], [2, 1], $r), 'tolerância de um trimestre atravessa o ano');
        $this->assertFalse(Pea_motor::no_prazo([1, 2], NULL, $r));
        $this->assertSame(75.0, Pea_motor::icc(4, 3, 3));
        $this->assertSame(60.0, Pea_motor::icc(3, 5, 3), 'fichas abertas além do cronograma também são previstas');
        $this->assertNull(Pea_motor::icc(0, 0, 0));
    }

    public function test_ie_e_classificacao() {
        $r = $this->r();
        $this->assertSame(81.3, Pea_motor::ie(['icc' => 100, 'ip' => 60, 'is' => 82, 'iia' => 83.3], $r));
        $this->assertSame(80.0, Pea_motor::ie(['icc' => 100, 'ip' => NULL, 'is' => 70, 'iia' => 70], $r), 'sem IP: média dos outros três');
        $r['ie']['pesos']['icc'] = 2;
        $this->assertSame(82.0, Pea_motor::ie(['icc' => 100, 'ip' => 70, 'is' => 70, 'iia' => 70], $r), '(100·2 + 70·3) ÷ 5');
        $r = $this->r();
        $this->assertSame('Excelência', Pea_motor::classificar('ICC', 80, $r));
        $this->assertSame('Satisfatório', Pea_motor::classificar('ICC', 50, $r));
        $this->assertSame('Cumprido parcialmente (justificar ao órgão ambiental)', Pea_motor::classificar('ICC', 49.9, $r));
        $this->assertSame('Participantes insatisfeitos', Pea_motor::classificar('IS', 0, $r));
        $this->assertSame('Participantes pouco satisfeitos', Pea_motor::classificar('IS', 39, $r));
        $this->assertSame('Participantes muito satisfeitos', Pea_motor::classificar('IS', 80, $r));
        $this->assertSame('Atende às expectativas', Pea_motor::classificar('IIA', 75, $r));
        $this->assertSame('Média participação', Pea_motor::classificar('IP', 60, $r));
        $this->assertSame('Médio', Pea_motor::classificar('EIEAP', 69.9, $r));
        $this->assertSame('Muito alto', Pea_motor::classificar('EIEAP', 90, $r));
        $this->assertNull(Pea_motor::classificar('IS', NULL, $r));
        $this->assertSame('success', Pea_motor::nivel('IE', 85));
        $this->assertSame('danger', Pea_motor::nivel('ICC', 40));
    }

    public function test_is_por_item() {
        $i = Pea_motor::is_itens([2 => ['satisfeito' => 3, 'insatisfeito' => 1], 3 => []], $this->r());
        $this->assertSame([2 => 75.0, 3 => NULL], $i);
    }

    public function test_periodos_do_ciclo_agosto_a_agosto() {
        $ini = '2026-08-01';
        $this->assertSame([1, 1], Pea_motor::periodo('2026-08-15', $ini));
        $this->assertSame([1, 2], Pea_motor::periodo('2026-11-01', $ini));
        $this->assertSame([1, 4], Pea_motor::periodo('2027-07-31', $ini));
        $this->assertSame([2, 1], Pea_motor::periodo('2027-08-01', $ini));
        $this->assertSame([5, 4], Pea_motor::periodo('2031-07-10', $ini));
        $this->assertNull(Pea_motor::periodo('2026-07-31', $ini));
        $this->assertSame(['2026-11-01', '2027-01-31'], Pea_motor::intervalo(1, 2, $ini));
        $this->assertSame(['2027-08-01', '2028-07-31'], Pea_motor::intervalo(2, NULL, $ini));
    }

    public function test_arvore_eieap() {
        $nos = [
            ['codigo' => 'iea', 'pai' => NULL, 'peso' => 1],
            ['codigo' => 'percepcao', 'pai' => 'iea', 'peso' => 1],
            ['codigo' => 'comportamento', 'pai' => 'iea', 'peso' => 1],
            ['codigo' => 'conhecimento', 'pai' => 'percepcao', 'peso' => 2],
            ['codigo' => 'interesse', 'pai' => 'percepcao', 'peso' => 1],
            ['codigo' => 'atitude', 'pai' => 'comportamento', 'peso' => 1],
        ];
        $v = Pea_motor::arvore($nos, ['conhecimento' => 60, 'interesse' => 90, 'atitude' => 50]);
        $this->assertSame(70.0, $v['percepcao'], '(60·2 + 90) ÷ 3');
        $this->assertSame(50.0, $v['comportamento']);
        $this->assertSame(60.0, $v['iea']);
        $this->assertNull(Pea_motor::arvore($nos, [])['iea']);
    }

    public function test_regras_salvas_e_validacao() {
        $r = Pea_motor::regras(['is' => ['denominador' => 'respondentes'], 'ie' => ['pesos' => ['icc' => 2, 'ip' => 1, 'is' => 1, 'iia' => 1]]]);
        $this->assertSame('respondentes', $r['is']['denominador']);
        $this->assertSame(['satisfeito', 'muito_satisfeito'], $r['is']['satisfeito'], 'o resto do padrão continua');
        $this->assertSame([], Pea_motor::validar_regras($r));
        $r['iia']['divisor'] = 0;
        $r['is']['denominador'] = 'x';
        $this->assertCount(2, Pea_motor::validar_regras($r));
    }
}
