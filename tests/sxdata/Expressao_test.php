<?php

use PHPUnit\Framework\TestCase;

/**
 * Casos compartilhados com o motor do app (tests/fixtures/expressoes.json).
 */
class Expressao_test extends TestCase {

    private static function casos() {
        return json_decode(file_get_contents(__DIR__ . '/../fixtures/expressoes.json'), TRUE);
    }

    private static function valor($v) {
        return (is_array($v) && !empty($v['nsnr'])) ? Expressao::NSNR : $v;
    }

    public static function avaliar($expr, array $valores, array $moradores = []) {
        $resolver = function ($nome) use ($valores) {
            return array_key_exists($nome, $valores) ? self::valor($valores[$nome]) : NULL;
        };
        $lista = function () use ($moradores) {
            $r = [];
            foreach ($moradores as $m) {
                $r[] = function ($nome) use ($m) {
                    return array_key_exists($nome, $m) ? self::valor($m[$nome]) : NULL;
                };
            }
            return $r;
        };
        $e = new Expressao($resolver, $lista);
        return $e->avaliar($expr);
    }

    public function test_casos_compartilhados() {
        foreach (self::casos()['casos'] as $c) {
            $r = self::avaliar($c['expr'], $c['valores'] ?? [], $c['moradores'] ?? []);
            $esperado = $c['esperado'];
            $msg = 'Expressão: ' . $c['expr'];
            if (is_float($esperado) || is_int($esperado)) {
                $this->assertEqualsWithDelta($esperado, $r['valor'], 0.0001, $msg);
            } else {
                $this->assertSame($esperado, $r['valor'], $msg);
            }
            if (array_key_exists('incompleto', $c)) {
                $this->assertSame($c['incompleto'], $r['incompleto'], $msg . ' (incompleto)');
            }
        }
    }

    public function test_expressoes_invalidas() {
        foreach (self::casos()['invalidas'] as $expr) {
            list($ok, $erro) = Expressao::validar($expr);
            $this->assertFalse($ok, "Deveria ser inválida: $expr");
            $this->assertNotEmpty($erro);
        }
    }

    public function test_referencias() {
        $r = Expressao::referencias('q1 + soma_moradores(r, q2 > 1)');
        sort($r);
        $this->assertSame(['q1', 'q2', 'r'], $r);
    }

    public function test_texto_reconstroi_expressao_equivalente() {
        $original = 'nao (q1 em ["a", "b"] e q2 == "sim") ou soma(q3, 2) > 10';
        $texto = Expressao::texto(Expressao::compilar($original));
        foreach ([['q1' => 'a', 'q2' => 'sim', 'q3' => 9], ['q1' => 'c', 'q3' => 1]] as $v) {
            $this->assertSame(self::avaliar($original, $v)['valor'], self::avaliar($texto, $v)['valor']);
        }
    }
}
