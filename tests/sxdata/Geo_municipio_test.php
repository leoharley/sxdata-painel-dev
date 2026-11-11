<?php

use PHPUnit\Framework\TestCase;

class Geo_municipio_test extends TestCase {

    /** Quadrado 0..10 com um buraco 4..6 */
    private function quadrado_com_buraco() {
        return ['type' => 'Polygon', 'coordinates' => [
            [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]],
            [[4, 4], [6, 4], [6, 6], [4, 6], [4, 4]],
        ]];
    }

    public function test_dentro_e_fora_de_um_poligono() {
        $p = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]]];
        $this->assertTrue(Geo_municipio::contem($p, 5, 5));      // lat 5, lng 5
        $this->assertFalse(Geo_municipio::contem($p, 5, 11));
        $this->assertFalse(Geo_municipio::contem($p, -1, 5));
    }

    public function test_coordenadas_na_ordem_geojson() {
        // retângulo largo: lng 0..20, lat 0..5
        $p = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [20, 0], [20, 5], [0, 5], [0, 0]]]];
        $this->assertTrue(Geo_municipio::contem($p, 2, 15));
        $this->assertFalse(Geo_municipio::contem($p, 15, 2));
    }

    public function test_borda_e_vertice_contam_como_dentro() {
        $p = $this->quadrado_com_buraco();
        $this->assertTrue(Geo_municipio::contem($p, 0, 5));       // aresta esquerda
        $this->assertTrue(Geo_municipio::contem($p, 10, 10));     // vértice
        $this->assertTrue(Geo_municipio::contem($p, 5, 10));      // aresta superior
        $this->assertTrue(Geo_municipio::contem($p, 4, 5));       // borda do buraco
        $this->assertFalse(Geo_municipio::contem($p, 5, 10.001));
    }

    public function test_buraco_fica_fora() {
        $p = $this->quadrado_com_buraco();
        $this->assertFalse(Geo_municipio::contem($p, 5, 5));
        $this->assertTrue(Geo_municipio::contem($p, 2, 2));
        $this->assertTrue(Geo_municipio::contem($p, 7, 5));
    }

    public function test_multipolygon() {
        $mp = ['type' => 'MultiPolygon', 'coordinates' => [
            [[[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]]],
            [[[10, 10], [12, 10], [12, 12], [10, 12], [10, 10]]],   // ilha
        ]];
        $this->assertTrue(Geo_municipio::contem($mp, 1, 1));
        $this->assertTrue(Geo_municipio::contem($mp, 11, 11));
        $this->assertFalse(Geo_municipio::contem($mp, 5, 5));      // entre as partes
    }

    public function test_feature_collection_e_texto_json() {
        $fc = ['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => [], 'geometry' => $this->quadrado_com_buraco()],
        ]];
        $this->assertTrue(Geo_municipio::contem(json_encode($fc), 2, 2));
        $this->assertFalse(Geo_municipio::contem($fc, 5, 5));
    }

    public function test_poligono_concavo() {
        // "U": a reentrância (lng 4..6, lat 3..10) é fora
        $u = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [10, 0], [10, 10], [6, 10], [6, 3], [4, 3], [4, 10], [0, 10], [0, 0]]]];
        $this->assertFalse(Geo_municipio::contem($u, 8, 5));
        $this->assertTrue(Geo_municipio::contem($u, 8, 2));
        $this->assertTrue(Geo_municipio::contem($u, 2, 5));
    }

    public function test_malha_invalida_devolve_null() {
        $this->assertNull(Geo_municipio::contem(null, 1, 1));
        $this->assertNull(Geo_municipio::contem('não é json', 1, 1));
        $this->assertNull(Geo_municipio::contem(['type' => 'Point', 'coordinates' => [1, 1]], 1, 1));
        $this->assertNull(Geo_municipio::contem(['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 1]]]], 1, 1));
    }

    public function test_malha_real_do_ibge_monte_do_carmo() {
        $malha = file_get_contents(__DIR__ . '/fixtures/malha_1713601.geojson');
        $this->assertTrue(Geo_municipio::contem($malha, -10.7611, -48.1114));  // sede
        $this->assertFalse(Geo_municipio::contem($malha, -10.1840, -48.3336)); // Palmas
        $this->assertFalse(Geo_municipio::contem($malha, -15.7939, -47.8828)); // Brasília
    }

    public function test_limites() {
        $this->assertSame([0.0, 0.0, 10.0, 10.0], Geo_municipio::limites($this->quadrado_com_buraco()));
        $this->assertNull(Geo_municipio::limites(null));
    }
}
