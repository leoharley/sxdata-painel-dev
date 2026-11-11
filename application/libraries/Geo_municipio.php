<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Geo_municipio - "esta coordenada cai dentro do município?"
 *
 * Recebe a malha do município em GeoJSON (a API de malhas do IBGE devolve
 * FeatureCollection; aceita também Feature, Polygon e MultiPolygon) e testa
 * o ponto por ray casting. Coordenadas no padrão GeoJSON: [longitude, latitude].
 *
 * - Polígono com buracos: dentro do anel externo e fora de todos os buracos.
 * - MultiPolygon (municípios com ilhas ou partes separadas): dentro de qualquer parte.
 * - Ponto sobre a borda (com tolerância de ~1 cm) conta como dentro: a divisa
 *   é do município, e o GPS não tem essa precisão.
 *
 * Sem banco e sem rede: testável isoladamente (tests/sxdata/Geo_municipio_test.php).
 */
class Geo_municipio {

    /** Tolerância da borda, em graus (~1 cm no equador). */
    const EPS = 1e-7;

    /**
     * @param array|string $geojson malha (array decodificado ou texto JSON)
     * @param float $lat
     * @param float $lng
     * @return bool|null TRUE dentro, FALSE fora, NULL se a malha não tem polígono
     */
    public static function contem($geojson, $lat, $lng) {
        $poligonos = self::poligonos($geojson);
        if (!$poligonos) {
            return NULL;
        }

        $x = (float) $lng;
        $y = (float) $lat;
        foreach ($poligonos as $aneis) {
            if (self::no_poligono($aneis, $x, $y)) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * Retângulo envolvente [min_lng, min_lat, max_lng, max_lat], ou NULL.
     * A tela usa para enquadrar o mapa quando o Leaflet ainda não carregou a malha.
     */
    public static function limites($geojson) {
        $min_x = $min_y = INF;
        $max_x = $max_y = -INF;
        foreach (self::poligonos($geojson) as $aneis) {
            foreach ($aneis[0] as $p) { // o anel externo basta
                $min_x = min($min_x, $p[0]); $max_x = max($max_x, $p[0]);
                $min_y = min($min_y, $p[1]); $max_y = max($max_y, $p[1]);
            }
        }
        return is_finite($min_x) ? [$min_x, $min_y, $max_x, $max_y] : NULL;
    }

    /**
     * Lista de polígonos, cada um como lista de anéis (o primeiro é o externo).
     * @return array[]
     */
    public static function poligonos($geojson) {
        if (is_string($geojson)) {
            $geojson = json_decode($geojson, TRUE);
        }
        if (!is_array($geojson) || !isset($geojson['type'])) {
            return [];
        }

        switch ($geojson['type']) {
            case 'FeatureCollection':
                $todos = [];
                foreach ((array) ($geojson['features'] ?? []) as $f) {
                    $todos = array_merge($todos, self::poligonos($f));
                }
                return $todos;
            case 'Feature':
                return self::poligonos($geojson['geometry'] ?? NULL);
            case 'GeometryCollection':
                $todos = [];
                foreach ((array) ($geojson['geometries'] ?? []) as $g) {
                    $todos = array_merge($todos, self::poligonos($g));
                }
                return $todos;
            case 'Polygon':
                $p = self::aneis_validos($geojson['coordinates'] ?? []);
                return $p ? [$p] : [];
            case 'MultiPolygon':
                $todos = [];
                foreach ((array) ($geojson['coordinates'] ?? []) as $coords) {
                    $p = self::aneis_validos($coords);
                    if ($p) $todos[] = $p;
                }
                return $todos;
        }
        return [];
    }

    // ------------------------------------------------------------

    private static function no_poligono(array $aneis, $x, $y) {
        $externo = $aneis[0];
        if (self::na_borda($externo, $x, $y)) {
            return TRUE;
        }
        if (!self::no_anel($externo, $x, $y)) {
            return FALSE;
        }
        for ($i = 1, $n = count($aneis); $i < $n; $i++) {
            if (self::na_borda($aneis[$i], $x, $y)) {
                return TRUE; // borda do buraco ainda é divisa do município
            }
            if (self::no_anel($aneis[$i], $x, $y)) {
                return FALSE;
            }
        }
        return TRUE;
    }

    /** Ray casting: conta cruzamentos de um raio horizontal para a direita. */
    private static function no_anel(array $anel, $x, $y) {
        $dentro = FALSE;
        $n = count($anel);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $anel[$i][0]; $yi = $anel[$i][1];
            $xj = $anel[$j][0]; $yj = $anel[$j][1];
            if (($yi > $y) !== ($yj > $y)
                && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $dentro = !$dentro;
            }
        }
        return $dentro;
    }

    private static function na_borda(array $anel, $x, $y) {
        $n = count($anel);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            if (self::no_segmento($anel[$j], $anel[$i], $x, $y)) {
                return TRUE;
            }
        }
        return FALSE;
    }

    private static function no_segmento($a, $b, $x, $y) {
        if ($x < min($a[0], $b[0]) - self::EPS || $x > max($a[0], $b[0]) + self::EPS
            || $y < min($a[1], $b[1]) - self::EPS || $y > max($a[1], $b[1]) + self::EPS) {
            return FALSE;
        }
        $dx = $b[0] - $a[0];
        $dy = $b[1] - $a[1];
        $comp = sqrt($dx * $dx + $dy * $dy);
        if ($comp == 0) {
            return abs($x - $a[0]) <= self::EPS && abs($y - $a[1]) <= self::EPS;
        }
        // distância do ponto à reta, em graus
        return abs($dy * ($x - $a[0]) - $dx * ($y - $a[1])) / $comp <= self::EPS;
    }

    /** Converte os anéis para [[x, y], ...] em float e descarta anéis com menos de 3 vértices. */
    private static function aneis_validos($coords) {
        $aneis = [];
        foreach ((array) $coords as $anel) {
            $pontos = [];
            foreach ((array) $anel as $p) {
                if (is_array($p) && isset($p[0], $p[1]) && is_numeric($p[0]) && is_numeric($p[1])) {
                    $pontos[] = [(float) $p[0], (float) $p[1]];
                }
            }
            if (count($pontos) >= 3) {
                $aneis[] = $pontos;
            } elseif (!$aneis) {
                return NULL; // sem anel externo não há polígono
            }
        }
        return $aneis ?: NULL;
    }
}
