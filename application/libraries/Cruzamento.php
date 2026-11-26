<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Cruzamento de variáveis (frente J): tabela de contingência, percentuais,
 * média e mediana por célula, qui-quadrado com valor-p e V de Cramér.
 * PHP puro, sem banco: recebe os registros já montados (Cruzamento_model).
 *
 * Registro: ['l' => [categorias da linha], 'c' => [categorias da coluna], 'm' => número ou NULL]
 * Uma pergunta de múltipla escolha põe o registro em mais de uma categoria:
 * as contagens passam a ser de menções, e o qui-quadrado não se aplica.
 */
class Cruzamento {

    const SEM_RESPOSTA = '(sem resposta)';

    /**
     * @param array $registros
     * @param array $op ordem_l, ordem_c (rótulos na ordem das opções), rotulos_l, rotulos_c
     *                  (valor => texto), incluir_vazios (bool), k_minimo (int, 0 = não suprime)
     */
    public static function tabela(array $registros, array $op = []) {
        $incluir_vazio = !empty($op['incluir_vazios']);
        $cont = [];
        $medidas = [];
        $cats_l = [];
        $cats_c = [];
        $n = 0;
        $multipla = FALSE;
        $sem_coluna = empty($op['sem_coluna']) ? FALSE : TRUE;
        foreach ($registros as $r) {
            $ls = self::categorias($r['l'] ?? [], $incluir_vazio);
            $cs = $sem_coluna ? ['Total'] : self::categorias($r['c'] ?? [], $incluir_vazio);
            if (!$ls || !$cs) {
                continue;
            }
            $n++;
            if (count($ls) > 1 || count($cs) > 1) {
                $multipla = TRUE;
            }
            foreach ($ls as $l) {
                $cats_l[$l] = TRUE;
                foreach ($cs as $c) {
                    $cats_c[$c] = TRUE;
                    $cont[$l][$c] = ($cont[$l][$c] ?? 0) + 1;
                    if (isset($r['m']) && $r['m'] !== NULL && is_numeric($r['m'])) {
                        $medidas[$l][$c][] = (float) $r['m'];
                    }
                }
            }
        }
        $L = self::ordenar(array_keys($cats_l), $op['ordem_l'] ?? []);
        $C = self::ordenar(array_keys($cats_c), $op['ordem_c'] ?? []);

        $m = [];
        foreach ($L as $i => $l) {
            foreach ($C as $j => $c) {
                $m[$i][$j] = $cont[$l][$c] ?? 0;
            }
        }
        $tl = array_map('array_sum', $m ?: []);
        $tc = [];
        foreach ($C as $j => $c) {
            $tc[$j] = array_sum(array_column($m, $j));
        }
        $total = array_sum($tl);

        $res = [
            'linhas' => array_map(function ($v) use ($op) { return self::rotulo($v, $op['rotulos_l'] ?? []); }, $L),
            'colunas' => array_map(function ($v) use ($op) { return self::rotulo($v, $op['rotulos_c'] ?? []); }, $C),
            'n' => $n, 'total' => $total, 'multipla' => $multipla,
            'contagem' => $m, 'total_linha' => $tl, 'total_coluna' => $tc,
            'pct_linha' => [], 'pct_coluna' => [], 'pct_total' => [],
            'media' => NULL, 'mediana' => NULL, 'suprimidas' => 0, 'k_minimo' => (int) ($op['k_minimo'] ?? 0),
        ];
        foreach ($m as $i => $linha) {
            foreach ($linha as $j => $v) {
                $res['pct_linha'][$i][$j] = $tl[$i] ? round($v / $tl[$i] * 100, 1) : 0.0;
                $res['pct_coluna'][$i][$j] = $tc[$j] ? round($v / $tc[$j] * 100, 1) : 0.0;
                $res['pct_total'][$i][$j] = $total ? round($v / $total * 100, 1) : 0.0;
            }
        }
        if ($medidas) {
            foreach ($L as $i => $l) {
                foreach ($C as $j => $c) {
                    $vals = $medidas[$l][$c] ?? [];
                    $res['media'][$i][$j] = $vals ? round(array_sum($vals) / count($vals), 2) : NULL;
                    $res['mediana'][$i][$j] = $vals ? round(self::mediana($vals), 2) : NULL;
                }
            }
        }
        $res['qui'] = (!$multipla && count($L) > 1 && count($C) > 1) ? self::qui_quadrado($m) : NULL;
        if ($res['k_minimo'] > 0) {
            $res = self::suprimir($res, $res['k_minimo']);
        }
        return $res;
    }

    /**
     * Qui-quadrado de independência.
     * @return array qui2, gl, p, v_cramer, esperado_min, baixas_pct, aviso
     */
    public static function qui_quadrado(array $m) {
        $tl = array_map('array_sum', $m);
        $nc = count(reset($m));
        $tc = [];
        for ($j = 0; $j < $nc; $j++) {
            $tc[$j] = array_sum(array_column($m, $j));
        }
        $N = array_sum($tl);
        // Linhas/colunas zeradas não entram (esperado zero)
        $li = array_keys(array_filter($tl));
        $co = array_keys(array_filter($tc));
        $r = count($li);
        $k = count($co);
        if ($N <= 0 || $r < 2 || $k < 2) {
            return NULL;
        }
        $x2 = 0.0;
        $baixas = 0;
        $min = INF;
        foreach ($li as $i) {
            foreach ($co as $j) {
                $e = $tl[$i] * $tc[$j] / $N;
                $min = min($min, $e);
                if ($e < 5) {
                    $baixas++;
                }
                $x2 += ($m[$i][$j] - $e) ** 2 / $e;
            }
        }
        $gl = ($r - 1) * ($k - 1);
        $p = self::p_qui($x2, $gl);
        $v = sqrt($x2 / ($N * (min($r, $k) - 1)));
        $pct = $baixas / ($r * $k) * 100;
        $aviso = NULL;
        if ($N < 30) {
            $aviso = 'Base pequena (' . $N . ' casos): o teste não é confiável.';
        } elseif ($pct > 20 || $min < 1) {
            $aviso = round($pct) . '% das células têm frequência esperada menor que 5: interprete com cautela ou agrupe categorias.';
        }
        return ['qui2' => round($x2, 4), 'gl' => $gl, 'p' => $p, 'v_cramer' => round($v, 4),
            'esperado_min' => round($min, 2), 'baixas_pct' => round($pct, 1), 'aviso' => $aviso,
            'significativo' => $p < 0.05];
    }

    /** P(X² >= x) com gl graus de liberdade = Q(gl/2, x/2). */
    public static function p_qui($x, $gl) {
        if ($x <= 0) {
            return 1.0;
        }
        return max(0.0, min(1.0, self::gama_q($gl / 2, $x / 2)));
    }

    /** Função gama incompleta regularizada superior Q(a, x) (série e fração contínua). */
    public static function gama_q($a, $x) {
        if ($x < $a + 1) {
            // série para P(a, x)
            $ap = $a;
            $soma = $del = 1.0 / $a;
            for ($n = 0; $n < 1000; $n++) {
                $ap += 1;
                $del *= $x / $ap;
                $soma += $del;
                if (abs($del) < abs($soma) * 1e-15) {
                    break;
                }
            }
            return 1.0 - $soma * exp(-$x + $a * log($x) - self::ln_gama($a));
        }
        // fração contínua de Lentz para Q(a, x)
        $b = $x + 1 - $a;
        $c = 1.0 / 1e-300;
        $d = 1.0 / $b;
        $h = $d;
        for ($i = 1; $i < 1000; $i++) {
            $an = -$i * ($i - $a);
            $b += 2;
            $d = $an * $d + $b;
            if (abs($d) < 1e-300) { $d = 1e-300; }
            $c = $b + $an / $c;
            if (abs($c) < 1e-300) { $c = 1e-300; }
            $d = 1.0 / $d;
            $del = $d * $c;
            $h *= $del;
            if (abs($del - 1) < 1e-15) {
                break;
            }
        }
        return exp(-$x + $a * log($x) - self::ln_gama($a)) * $h;
    }

    /** ln Γ(x), aproximação de Lanczos (g = 7, 9 termos). */
    public static function ln_gama($x) {
        $g = [0.99999999999980993, 676.5203681218851, -1259.1392167224028, 771.32342877765313,
            -176.61502916214059, 12.507343278686905, -0.13857109526572012, 9.9843695780195716e-6, 1.5056327351493116e-7];
        if ($x < 0.5) {
            return log(M_PI / abs(sin(M_PI * $x))) - self::ln_gama(1 - $x);
        }
        $x -= 1;
        $s = $g[0];
        for ($i = 1; $i < 9; $i++) {
            $s += $g[$i] / ($x + $i);
        }
        $t = $x + 7.5;
        return 0.5 * log(2 * M_PI) + ($x + 0.5) * log($t) - $t + log($s);
    }

    public static function mediana(array $v) {
        sort($v);
        $n = count($v);
        if (!$n) {
            return NULL;
        }
        return $n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
    }

    /**
     * Esconde células com menos de k casos (e os percentuais delas), como
     * pede a frente J para o perfil cliente. A célula vira NULL; a tela
     * mostra "< k".
     */
    public static function suprimir(array $res, $k) {
        $s = 0;
        foreach ($res['contagem'] as $i => $linha) {
            foreach ($linha as $j => $v) {
                if ($v > 0 && $v < $k) {
                    $s++;
                    foreach (['contagem', 'pct_linha', 'pct_coluna', 'pct_total', 'media', 'mediana'] as $q) {
                        if (isset($res[$q][$i]) && array_key_exists($j, $res[$q][$i])) {
                            $res[$q][$i][$j] = NULL;
                        }
                    }
                }
            }
        }
        foreach (['total_linha', 'total_coluna'] as $q) {
            foreach ($res[$q] as $i => $v) {
                if ($v > 0 && $v < $k) {
                    $res[$q][$i] = NULL;
                    $s++;
                }
            }
        }
        $res['suprimidas'] = $s;
        return $res;
    }

    /** Faixas iguais para uma variável numérica: [[de, ate, rotulo], ...]. */
    public static function faixas(array $valores, $n = 5) {
        $v = array_values(array_filter($valores, 'is_numeric'));
        if (!$v) {
            return [];
        }
        $min = (float) min($v);
        $max = (float) max($v);
        if ($min == $max) {
            return [[$min, $max, self::num($min)]];
        }
        $passo = ($max - $min) / $n;
        // Passo "redondo" (1, 2, 5 × 10^k) para rótulos legíveis
        $mag = pow(10, floor(log10($passo)));
        foreach ([1, 2, 2.5, 5, 10] as $f) {
            if ($f * $mag >= $passo) {
                $passo = $f * $mag;
                break;
            }
        }
        $ini = floor($min / $passo) * $passo;
        $r = [];
        for ($a = $ini; $a <= $max; $a += $passo) {
            $b = $a + $passo;
            $r[] = [$a, $b, self::num($a) . ' a ' . self::num($b - ($passo >= 1 && floor($passo) == $passo ? 1 : 0.01))];
            if (count($r) > 20) {
                break;
            }
        }
        return $r;
    }

    public static function faixa_de($valor, array $faixas) {
        if (!is_numeric($valor) || !$faixas) {
            return NULL;
        }
        $x = (float) $valor;
        foreach ($faixas as $i => $f) {
            $ultima = $i === count($faixas) - 1;
            if ($x >= $f[0] && ($x < $f[1] || ($ultima && $x <= $f[1]))) {
                return $f[2];
            }
        }
        return NULL;
    }

    private static function num($x) {
        return floor($x) == $x ? number_format($x, 0, ',', '.') : number_format($x, 2, ',', '.');
    }

    private static function categorias($v, $incluir_vazio) {
        $lista = is_array($v) ? $v : [$v];
        $lista = array_values(array_unique(array_filter(array_map(function ($x) {
            return is_scalar($x) ? trim((string) $x) : '';
        }, $lista), 'strlen')));
        if (!$lista) {
            return $incluir_vazio ? [self::SEM_RESPOSTA] : [];
        }
        return $lista;
    }

    private static function ordenar(array $cats, array $ordem) {
        $pos = array_flip(array_map('strval', $ordem));
        usort($cats, function ($a, $b) use ($pos) {
            if ($a === self::SEM_RESPOSTA) { return 1; }
            if ($b === self::SEM_RESPOSTA) { return -1; }
            $pa = $pos[$a] ?? PHP_INT_MAX;
            $pb = $pos[$b] ?? PHP_INT_MAX;
            return $pa === $pb ? strnatcasecmp($a, $b) : $pa <=> $pb;
        });
        return array_values($cats);
    }

    private static function rotulo($v, array $rotulos) {
        return isset($rotulos[$v]) ? $rotulos[$v] : $v;
    }
}
