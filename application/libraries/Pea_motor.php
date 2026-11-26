<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Motor de ponderação do PEA Gerdau (Parte I, frente D). PHP puro: recebe
 * contagens e parâmetros, devolve os indicadores. As regras (pesos, opções
 * que contam como "satisfeito", faixas) vêm da versão vigente em pea_regras;
 * regras_padrao() é a versão 1, conforme o PEA 2026–2031 e a seção 20 da
 * proposta (pontos 1 a 5).
 *
 *   IS  = (Satisfeito + Muito satisfeito na P6) ÷ total de participantes × 100
 *   IIA = [0,8·a + 0,6·c + 0,2·(d − 1)] ÷ 0,6 × 100   (a, c, d em proporção; piso 0)
 *   ICC = ações realizadas no trimestre previsto ÷ ações previstas × 100
 *   IP  = participantes convertidos em 30, 60 ou 100 pelas faixas do projeto
 *   IE  = (ICC + IP + IS + IIA) ÷ 4, com pesos editáveis
 */
class Pea_motor {

    public static function regras_padrao() {
        return [
            'perguntas' => ['perfil' => 1, 'is_itens' => [2, 3, 4, 5], 'is' => 6, 'iia' => 7, 'aprendizado' => 8, 'pratica' => 9, 'sugestao' => 10],
            'is' => ['satisfeito' => ['satisfeito', 'muito_satisfeito'], 'denominador' => 'participantes'],
            'iia' => ['a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'd', 'peso_a' => 0.8, 'peso_c' => 0.6, 'peso_d' => 0.2, 'divisor' => 0.6, 'piso_zero' => TRUE],
            'icc' => ['tolerancia_trimestres' => 0],
            'ip' => ['baixa' => 30, 'media' => 60, 'alta' => 100, 'abaixo_da_baixa' => 30],
            'ie' => ['pesos' => ['icc' => 1, 'ip' => 1, 'is' => 1, 'iia' => 1]],
            'faixas' => [
                'ICC' => [[NULL, 50, 'Cumprido parcialmente (justificar ao órgão ambiental)'], [50, 80, 'Satisfatório'], [80, NULL, 'Excelência']],
                'IP' => [[0, 0, 'Ação não realizada'], [30, 30, 'Baixa participação'], [60, 60, 'Média participação'], [100, 100, 'Alta participação']],
                'IS' => [[0, 0, 'Participantes insatisfeitos'], [0, 40, 'Participantes pouco satisfeitos'], [40, 80, 'Participantes satisfeitos'], [80, NULL, 'Participantes muito satisfeitos']],
                'IIA' => [[0, 0, 'Não cumpriu o objetivo'], [0, 70, 'Abaixo das expectativas'], [70, 80, 'Atende às expectativas'], [80, NULL, 'Superou as expectativas']],
                'IE' => [[0, 0, 'Insatisfatório'], [0, 40, 'Condições mínimas'], [40, 80, 'Satisfatório'], [80, NULL, 'Excelência']],
                'EIEAP' => [[NULL, 20, 'Muito baixo'], [20, 40, 'Baixo'], [40, 70, 'Médio'], [70, 90, 'Alto'], [90, NULL, 'Muito alto']],
            ],
        ];
    }

    /** Mescla regras salvas sobre o padrão (chaves novas do padrão continuam valendo). */
    public static function regras($salvas) {
        $p = self::regras_padrao();
        if (!is_array($salvas)) {
            return $p;
        }
        foreach ($salvas as $k => $v) {
            $p[$k] = (is_array($v) && isset($p[$k]) && is_array($p[$k]) && array_keys($v) !== range(0, count($v) - 1)) ? array_replace($p[$k], $v) : $v;
        }
        return $p;
    }

    // ---------------- Indicadores por ação ----------------

    /**
     * @param array $p6 contagem por valor de opção da P6 (ex.: ['satisfeito' => 10, ...])
     * @return float|null NULL quando não há base (sem participantes e sem respostas)
     */
    public static function is(array $p6, $participantes, array $r) {
        $satisf = 0;
        foreach ($r['is']['satisfeito'] as $o) {
            $satisf += (int) ($p6[$o] ?? 0);
        }
        $respondentes = array_sum(array_map('intval', $p6));
        $den = ($r['is']['denominador'] ?? 'participantes') === 'respondentes' ? $respondentes : (int) $participantes;
        if ($den <= 0) {
            return $respondentes > 0 ? round($satisf / $respondentes * 100, 1) : NULL;
        }
        return round(min(100, $satisf / $den * 100), 1);
    }

    /** % de satisfeitos em cada item (P2 a P5), sobre quem respondeu o item. */
    public static function is_itens(array $itens, array $r) {
        $out = [];
        foreach ($itens as $q => $cont) {
            $tot = array_sum(array_map('intval', $cont));
            $s = 0;
            foreach ($r['is']['satisfeito'] as $o) {
                $s += (int) ($cont[$o] ?? 0);
            }
            $out[$q] = $tot ? round($s / $tot * 100, 1) : NULL;
        }
        return $out;
    }

    /** @param array $p7 contagem por opção da P7 (a, b, c, d) */
    public static function iia(array $p7, array $r) {
        $c = $r['iia'];
        $tot = 0;
        foreach (['a', 'b', 'c', 'd'] as $k) {
            $tot += (int) ($p7[$c[$k]] ?? 0);
        }
        if ($tot <= 0) {
            return NULL;
        }
        $a = ($p7[$c['a']] ?? 0) / $tot;
        $cc = ($p7[$c['c']] ?? 0) / $tot;
        $d = ($p7[$c['d']] ?? 0) / $tot;
        $v = ($c['peso_a'] * $a + $c['peso_c'] * $cc + $c['peso_d'] * ($d - 1)) / $c['divisor'] * 100;
        if (!empty($c['piso_zero'])) {
            $v = max(0, $v);
        }
        return round(min(100, $v), 1);
    }

    /**
     * IP da ação. Não realizada = 0. Sem faixas no projeto = NULL (fora do IE).
     * Abaixo da faixa baixa = "baixa" (ponto 3 da seção 20).
     */
    public static function ip($participantes, $faixas, $realizada, array $r) {
        if (!$realizada) {
            return 0;
        }
        if (!is_array($faixas) || empty($faixas['baixa'])) {
            return NULL;
        }
        $n = (int) $participantes;
        if (!empty($faixas['alta'][0]) && $n >= $faixas['alta'][0]) {
            return $r['ip']['alta'];
        }
        if (!empty($faixas['media'][0]) && $n >= $faixas['media'][0]) {
            return $r['ip']['media'];
        }
        if ($n >= (int) $faixas['baixa'][0]) {
            return $r['ip']['baixa'];
        }
        return $n > 0 ? $r['ip']['abaixo_da_baixa'] : 0;
    }

    /** Ação realizada no prazo (trimestre previsto + tolerância)? */
    public static function no_prazo($previsto, $realizado, array $r) {
        if (!$previsto || !$realizado) {
            return FALSE;
        }
        $p = $previsto[0] * 4 + $previsto[1];
        $x = $realizado[0] * 4 + $realizado[1];
        return $x <= $p + (int) ($r['icc']['tolerancia_trimestres'] ?? 0);
    }

    /**
     * ICC de um conjunto: previstas = maior entre o cronograma e as fichas
     * abertas para o período; realizadas = fichas realizadas no prazo.
     */
    public static function icc($previstas_cronograma, $previstas_fichas, $realizadas_no_prazo) {
        $prev = max((int) $previstas_cronograma, (int) $previstas_fichas);
        if ($prev <= 0) {
            return NULL;
        }
        return round(min(100, $realizadas_no_prazo / $prev * 100), 1);
    }

    /** Média ponderada dos componentes disponíveis (NULL não entra). */
    public static function ie(array $c, array $r) {
        $soma = 0;
        $pesos = 0;
        foreach ($r['ie']['pesos'] as $k => $w) {
            if (isset($c[$k]) && $c[$k] !== NULL && (float) $w > 0) {
                $soma += $c[$k] * $w;
                $pesos += $w;
            }
        }
        return $pesos > 0 ? round($soma / $pesos, 1) : NULL;
    }

    public static function media(array $valores) {
        $v = array_values(array_filter($valores, function ($x) { return $x !== NULL; }));
        return $v ? round(array_sum($v) / count($v), 1) : NULL;
    }

    /** Classificação pela faixa de leitura do indicador. */
    public static function classificar($ind, $valor, array $r) {
        if ($valor === NULL) {
            return NULL;
        }
        $faixas = $r['faixas'][$ind] ?? [];
        $x = (float) $valor;
        // Faixa exata (de == ate) primeiro: o "0" tem nome próprio
        foreach ($faixas as $f) {
            if ($f[0] !== NULL && $f[1] !== NULL && (float) $f[0] === (float) $f[1] && $x == $f[0]) {
                return $f[2];
            }
        }
        foreach ($faixas as $f) {
            if ($f[0] !== NULL && $f[1] !== NULL && (float) $f[0] === (float) $f[1]) {
                continue;
            }
            $ok_de = $f[0] === NULL || $x >= $f[0];
            $ok_ate = $f[1] === NULL || $x < $f[1];
            if ($ok_de && $ok_ate) {
                return $f[2];
            }
        }
        return NULL;
    }

    /** Cor do cartão: verde (topo), amarelo (meio), vermelho (abaixo). */
    public static function nivel($ind, $valor) {
        if ($valor === NULL) {
            return 'secondary';
        }
        $lim = $ind === 'ICC' ? [50, 80] : ($ind === 'IP' ? [30, 60] : [40, 80]);
        return $valor >= $lim[1] ? 'success' : ($valor >= $lim[0] ? 'warning' : 'danger');
    }

    // ---------------- Períodos do ciclo ----------------

    /** [ano do ciclo (1..n), trimestre (1..4)] de uma data, com anos de agosto a agosto. */
    public static function periodo($data, $inicio_ciclo, $mes_inicio = 8) {
        $t = is_numeric($data) ? (int) $data : strtotime((string) $data);
        $i = strtotime((string) $inicio_ciclo);
        if (!$t || !$i) {
            return NULL;
        }
        $meses = ((int) date('Y', $t) - (int) date('Y', $i)) * 12 + ((int) date('n', $t) - (int) date('n', $i));
        if ($meses < 0) {
            return NULL;
        }
        return [intdiv($meses, 12) + 1, intdiv($meses % 12, 3) + 1];
    }

    /** Intervalo de datas de um ano/trimestre do ciclo (trimestre NULL = o ano inteiro). */
    public static function intervalo($ano, $trimestre, $inicio_ciclo) {
        $i = strtotime((string) $inicio_ciclo);
        $m0 = ($ano - 1) * 12 + ($trimestre ? ($trimestre - 1) * 3 : 0);
        $dur = $trimestre ? 3 : 12;
        $de = strtotime('+' . $m0 . ' months', $i);
        $ate = strtotime('-1 day', strtotime('+' . ($m0 + $dur) . ' months', $i));
        return [date('Y-m-d', $de), date('Y-m-d', $ate)];
    }

    // ---------------- Árvore EIEAP ----------------

    /**
     * Calcula a árvore: cada nó é a média ponderada dos filhos; as folhas
     * vêm de $folhas (codigo => valor 0–100).
     * @param array $nos [['codigo','pai','peso'], ...]
     * @return array codigo => valor
     */
    public static function arvore(array $nos, array $folhas) {
        $filhos = [];
        foreach ($nos as $n) {
            $filhos[$n['pai'] ?? ''][] = $n;
        }
        $val = [];
        $calc = function ($codigo) use (&$calc, &$val, $filhos, $folhas) {
            if (array_key_exists($codigo, $val)) {
                return $val[$codigo];
            }
            if (empty($filhos[$codigo])) {
                return $val[$codigo] = isset($folhas[$codigo]) && is_numeric($folhas[$codigo]) ? (float) $folhas[$codigo] : NULL;
            }
            $s = 0;
            $w = 0;
            foreach ($filhos[$codigo] as $f) {
                $v = $calc($f['codigo']);
                if ($v !== NULL) {
                    $s += $v * (float) $f['peso'];
                    $w += (float) $f['peso'];
                }
            }
            return $val[$codigo] = $w > 0 ? round($s / $w, 1) : (isset($folhas[$codigo]) ? (float) $folhas[$codigo] : NULL);
        };
        foreach ($nos as $n) {
            $calc($n['codigo']);
        }
        return $val;
    }

    /** Valida as regras antes de gravar uma versão nova. */
    public static function validar_regras(array $r) {
        $e = [];
        if (empty($r['is']['satisfeito']) || !is_array($r['is']['satisfeito'])) {
            $e[] = 'IS: informe quais opções contam como satisfeito.';
        }
        if (!in_array($r['is']['denominador'] ?? '', ['participantes', 'respondentes'], TRUE)) {
            $e[] = 'IS: o denominador deve ser "participantes" ou "respondentes".';
        }
        if (!is_numeric($r['iia']['divisor'] ?? NULL) || (float) $r['iia']['divisor'] == 0) {
            $e[] = 'IIA: o divisor não pode ser zero.';
        }
        foreach (['peso_a', 'peso_c', 'peso_d'] as $k) {
            if (!is_numeric($r['iia'][$k] ?? NULL)) {
                $e[] = 'IIA: ' . $k . ' precisa ser um número.';
            }
        }
        foreach (['icc', 'ip', 'is', 'iia'] as $k) {
            if (!is_numeric($r['ie']['pesos'][$k] ?? NULL) || $r['ie']['pesos'][$k] < 0) {
                $e[] = 'IE: o peso de ' . strtoupper($k) . ' precisa ser um número maior ou igual a zero.';
            }
        }
        if (!is_numeric($r['icc']['tolerancia_trimestres'] ?? NULL) || $r['icc']['tolerancia_trimestres'] < 0) {
            $e[] = 'ICC: a tolerância precisa ser 0 ou mais trimestres.';
        }
        return $e;
    }
}
