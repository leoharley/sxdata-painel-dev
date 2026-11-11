<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Temas do filtro do mapa integrado e resumo dos indicadores de um tema.
 *
 * Temas = eixos de relatorio_catalogo.php, com dois agrupamentos:
 *   demografia = caracterizacao + territorio_populacao
 *   saude      = saude + saude_aps + saude_mac
 *
 * Regra pura (sem banco): tests/sxdata/Mapa_temas_test.php.
 */
class Mapa_temas {

    const GRUPOS = [
        'demografia' => ['titulo' => 'Demografia', 'icone' => 'fa-users', 'eixos' => ['caracterizacao', 'territorio_populacao']],
        'saude'      => ['titulo' => 'Saúde', 'icone' => 'fa-heartbeat', 'eixos' => ['saude', 'saude_aps', 'saude_mac']],
    ];

    /** Limite do texto qualitativo no painel lateral (o completo fica no relatório). */
    const TEXTO_MAX = 600;

    /**
     * @param array $eixos relatorio_eixos do catálogo
     * @return array chave => ['titulo', 'icone', 'eixos' => [...]], na ordem do catálogo
     */
    public static function temas(array $eixos) {
        $temas = [];
        foreach ($eixos as $chave => $eixo) {
            $grupo = NULL;
            foreach (self::GRUPOS as $g => $def) {
                if (in_array($chave, $def['eixos'], TRUE)) { $grupo = $g; break; }
            }
            if ($grupo !== NULL) {
                if (!isset($temas[$grupo])) {
                    $temas[$grupo] = self::GRUPOS[$grupo];
                    $temas[$grupo]['eixos'] = [];
                }
                $temas[$grupo]['eixos'][] = $chave;
            } else {
                $temas[$chave] = ['titulo' => $eixo['titulo'], 'icone' => $eixo['icone'] ?? 'fa-file', 'eixos' => [$chave]];
            }
        }
        return $temas;
    }

    /**
     * Indicadores do tema, com o valor mais recente do município.
     *
     * - Só abrangência "municipio" (o mapa mostra o município).
     * - Sem categoria: o período mais recente com valor.
     * - Com categoria (ex.: estabelecimentos por nível): as categorias do
     *   período mais recente, em `categorias`.
     * - Texto: valor_texto (cortado em TEXTO_MAX).
     * - Na ordem do catálogo; indicador sem dado não aparece.
     *
     * @param array $linhas      linhas de report_indicators (objetos ou arrays)
     * @param array $eixos_tema  eixos do tema escolhido
     * @param array $catalogo    relatorio_indicadores
     * @return array[] [indicador, rotulo, eixo, tipo, formato, unidade, valor, valor_texto, periodo, fonte, categorias]
     */
    public static function resumo(array $linhas, array $eixos_tema, array $catalogo) {
        $por_indicador = [];
        foreach ($linhas as $l) {
            $l = (array) $l;
            $chave = (string) ($l['indicador'] ?? '');
            if (!isset($catalogo[$chave]) || !in_array($catalogo[$chave]['eixo'] ?? '', $eixos_tema, TRUE)) {
                continue;
            }
            if (($l['abrangencia'] ?? '') !== 'municipio') {
                continue;
            }
            $por_indicador[$chave][] = $l;
        }

        $out = [];
        foreach ($catalogo as $chave => $def) {
            if (empty($por_indicador[$chave])) {
                continue;
            }
            $item = self::item($chave, $def, $por_indicador[$chave]);
            if ($item !== NULL) {
                $out[] = $item;
            }
        }
        return $out;
    }

    private static function item($chave, array $def, array $linhas) {
        $texto = ($def['tipo'] ?? 'numero') === 'texto';

        // descarta linhas vazias
        $linhas = array_values(array_filter($linhas, function ($l) use ($texto) {
            return $texto ? trim((string) ($l['valor_texto'] ?? '')) !== ''
                          : ($l['valor'] ?? NULL) !== NULL && $l['valor'] !== '';
        }));
        if (!$linhas) {
            return NULL;
        }

        $sem_categoria = array_values(array_filter($linhas, function ($l) { return (string) ($l['categoria'] ?? '') === ''; }));
        $base = $sem_categoria ?: $linhas;

        $periodo = self::periodo_mais_recente($base);
        $do_periodo = array_values(array_filter($base, function ($l) use ($periodo) { return (string) ($l['periodo'] ?? '') === $periodo; }));
        $primeira = $do_periodo[0];

        $item = [
            'indicador'   => $chave,
            'rotulo'      => $def['rotulo'] ?? $chave,
            'eixo'        => $def['eixo'] ?? '',
            'tipo'        => $texto ? 'texto' : 'numero',
            'formato'     => $def['formato'] ?? 'decimal2',
            'unidade'     => $def['unidade'] ?? '',
            'valor'       => NULL,
            'valor_texto' => NULL,
            'periodo'     => $periodo,
            'fonte'       => (string) ($primeira['fonte'] ?? '') ?: ($def['fonte'] ?? ''),
            'categorias'  => [],
        ];

        if ($texto) {
            $t = trim((string) $primeira['valor_texto']);
            $item['valor_texto'] = mb_strlen($t) > self::TEXTO_MAX ? rtrim(mb_substr($t, 0, self::TEXTO_MAX)) . '…' : $t;
        } elseif ($sem_categoria) {
            $item['valor'] = (float) $primeira['valor'];
        } else {
            foreach ($do_periodo as $l) {
                $item['categorias'][] = ['categoria' => (string) $l['categoria'], 'valor' => (float) $l['valor']];
            }
        }
        return $item;
    }

    /** Maior período: anos numéricos primeiro pelo valor; rótulos ('' ou texto) por ordem. */
    public static function periodo_mais_recente(array $linhas) {
        $periodos = array_unique(array_map(function ($l) { return (string) ($l['periodo'] ?? ''); }, $linhas));
        usort($periodos, function ($a, $b) {
            $na = preg_match('/^\d{4}/', $a) ? (int) substr($a, 0, 4) : -1;
            $nb = preg_match('/^\d{4}/', $b) ? (int) substr($b, 0, 4) : -1;
            return $na === $nb ? strcmp($a, $b) : $na - $nb;
        });
        return (string) end($periodos);
    }
}
