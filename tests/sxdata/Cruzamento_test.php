<?php

use PHPUnit\Framework\TestCase;

/** Frente J: tabela de contingência e qui-quadrado contra valores de referência. */
class Cruzamento_test extends TestCase {

    /** Valores críticos clássicos: P(X² >= x) = 0,05 / 0,01. */
    public function test_valor_p_de_referencia() {
        $casos = [[3.841459, 1, 0.05], [6.634897, 1, 0.01], [5.991465, 2, 0.05], [7.814728, 3, 0.05], [11.070498, 5, 0.05],
            [18.307038, 10, 0.05], [43.772972, 30, 0.05], [124.342113, 100, 0.05], [0.454936, 1, 0.5], [10.0, 3, 0.018566]];
        foreach ($casos as $c) {
            $this->assertEqualsWithDelta($c[2], Cruzamento::p_qui($c[0], $c[1]), 2e-6, "x={$c[0]} gl={$c[1]}");
        }
        $this->assertSame(1.0, Cruzamento::p_qui(0, 4));
        $this->assertEqualsWithDelta(log(120), Cruzamento::ln_gama(6), 1e-10);
        $this->assertEqualsWithDelta(0.5 * log(M_PI), Cruzamento::ln_gama(0.5), 1e-10);
    }

    public function test_qui_quadrado_2x2() {
        // N=100; esperados 12, 18, 28, 42
        $q = Cruzamento::qui_quadrado([[10, 20], [30, 40]]);
        $this->assertEqualsWithDelta(0.7937, $q['qui2'], 1e-4);
        $this->assertSame(1, $q['gl']);
        $this->assertEqualsWithDelta(0.37296, $q['p'], 1e-4);
        $this->assertEqualsWithDelta(sqrt(0.79365 / 100), $q['v_cramer'], 1e-4);
        $this->assertFalse($q['significativo']);
        $this->assertNull($q['aviso']);
    }

    public function test_qui_quadrado_associacao_forte_e_aviso() {
        $q = Cruzamento::qui_quadrado([[50, 10, 5], [10, 40, 15], [5, 10, 55]]);
        $this->assertTrue($q['significativo']);
        $this->assertLessThan(1e-20, $q['p']);
        $this->assertSame(4, $q['gl']);
        $pequena = Cruzamento::qui_quadrado([[3, 1], [1, 4]]);
        $this->assertStringContainsString('Base pequena', $pequena['aviso']);
        $this->assertNull(Cruzamento::qui_quadrado([[5, 5]]), 'uma linha só');
        $this->assertSame(1, Cruzamento::qui_quadrado([[5, 0, 3], [2, 0, 9]])['gl'], 'coluna zerada não conta');
    }

    private static function regs() {
        $r = [];
        foreach ([['sim', 'urbana', 1000], ['sim', 'urbana', 2000], ['sim', 'rural', 500], ['nao', 'rural', 300], ['nao', 'rural', 100], ['nao', 'urbana', NULL], [NULL, 'rural', 50]] as $x) {
            $r[] = ['l' => $x[0], 'c' => $x[1], 'm' => $x[2]];
        }
        return $r;
    }

    public function test_tabela_percentuais_media_mediana() {
        $t = Cruzamento::tabela(self::regs(), ['ordem_l' => ['sim', 'nao'], 'rotulos_l' => ['sim' => 'Sim', 'nao' => 'Não'], 'ordem_c' => ['urbana', 'rural']]);
        $this->assertSame(['Sim', 'Não'], $t['linhas']);
        $this->assertSame(['urbana', 'rural'], $t['colunas']);
        $this->assertSame([[2, 1], [1, 2]], $t['contagem']);
        $this->assertSame([3, 3], $t['total_linha']);
        $this->assertSame(6, $t['total']);
        $this->assertSame(6, $t['n'], 'sem resposta fica fora');
        $this->assertSame([66.7, 33.3], $t['pct_linha'][0]);
        $this->assertSame([66.7, 33.3], $t['pct_coluna'][0]);
        $this->assertSame(16.7, $t['pct_total'][1][0]);
        $this->assertSame([1500.0, 500.0], $t['media'][0]);
        $this->assertSame([NULL, 200.0], $t['media'][1], 'célula sem medida');
        $this->assertSame(200.0, $t['mediana'][1][1]);
        $this->assertNotNull($t['qui']);

        $v = Cruzamento::tabela(self::regs(), ['incluir_vazios' => TRUE]);
        $this->assertSame(Cruzamento::SEM_RESPOSTA, end($v['linhas']), 'sem resposta por último');
        $this->assertSame(7, $v['n']);
    }

    public function test_multipla_escolha_conta_mencoes_e_sem_qui() {
        $t = Cruzamento::tabela([['l' => ['saude', 'escola'], 'c' => 'a'], ['l' => ['saude'], 'c' => 'b'], ['l' => 'escola', 'c' => 'b']], ['ordem_l' => ['saude', 'escola']]);
        $this->assertTrue($t['multipla']);
        $this->assertSame(3, $t['n']);
        $this->assertSame(4, $t['total']);
        $this->assertNull($t['qui']);
    }

    public function test_supressao_de_celulas_pequenas() {
        $regs = [];
        for ($i = 0; $i < 20; $i++) { $regs[] = ['l' => 'a', 'c' => 'x']; }
        for ($i = 0; $i < 3; $i++) { $regs[] = ['l' => 'b', 'c' => 'x']; }
        for ($i = 0; $i < 8; $i++) { $regs[] = ['l' => 'b', 'c' => 'y']; }
        $t = Cruzamento::tabela($regs, ['k_minimo' => 5]);
        $this->assertNull($t['contagem'][1][0], 'célula com 3 oculta');
        $this->assertNull($t['pct_linha'][1][0], 'e o percentual dela também');
        $this->assertSame(20, $t['contagem'][0][0]);
        $this->assertSame(0, $t['contagem'][0][1], 'zero não é suprimido');
        $this->assertGreaterThanOrEqual(1, $t['suprimidas']);
    }

    public function test_sem_coluna_e_faixas() {
        $t = Cruzamento::tabela(self::regs(), ['sem_coluna' => TRUE]);
        $this->assertSame(['Total'], $t['colunas']);
        $this->assertNull($t['qui']);
        $f = Cruzamento::faixas([0, 150, 999, 1800, 4200], 5);
        $this->assertSame('0 a 999', $f[0][2]);
        $this->assertSame('0 a 999', Cruzamento::faixa_de(999, $f));
        $this->assertSame('1.000 a 1.999', Cruzamento::faixa_de(1000, $f));
        $this->assertNotNull(Cruzamento::faixa_de(4200, $f), 'o máximo entra na última faixa');
        $this->assertNull(Cruzamento::faixa_de('x', $f));
        $this->assertCount(1, Cruzamento::faixas([7, 7, 7]));
    }
}
