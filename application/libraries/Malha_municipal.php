<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Malha (contorno) do município, da API de malhas do IBGE, com cache em disco.
 *
 * Cache: application/cache/malhas/{codigo}.geojson, gravado na primeira
 * consulta e sem expiração (malhas mudam raramente; apagar o arquivo força
 * nova busca). A pasta application/cache fica fora do git, então o cache
 * sobrevive aos deploys.
 *
 * Qualidade "intermediaria": cerca de 5 KB por município. A "minima" tem
 * vértices espaçados demais para validar pontos perto da divisa.
 */
class Malha_municipal {

    const URL = 'https://servicodados.ibge.gov.br/api/v3/malhas/municipios/%d?formato=application/vnd.geo%%2Bjson&qualidade=intermediaria';
    const TIMEOUT = 15;

    /** @var string|null pasta do cache (testes usam outra) */
    private $pasta;

    public function __construct($params = []) {
        $this->pasta = rtrim(isset($params['pasta']) ? $params['pasta'] : APPPATH . 'cache/malhas', '/\\');
    }

    /**
     * GeoJSON do município (texto), ou NULL se não houver no cache e a busca
     * falhar (sem internet, IBGE fora do ar, código inexistente).
     *
     * @param int  $codigo código IBGE de 7 dígitos
     * @param bool $buscar FALSE = só o cache, sem rede
     */
    public function obter($codigo, $buscar = TRUE) {
        $codigo = (int) $codigo;
        if ($codigo < 1100000 || $codigo > 5399999) {
            return NULL;
        }

        $arquivo = $this->pasta . '/' . $codigo . '.geojson';
        if (is_file($arquivo)) {
            $texto = @file_get_contents($arquivo);
            if (is_string($texto) && $texto !== '') {
                return $texto;
            }
        }
        if (!$buscar) {
            return NULL;
        }

        $texto = $this->baixar(sprintf(self::URL, $codigo));
        if ($texto === NULL || !Geo_municipio::poligonos($texto)) {
            return NULL;
        }

        if (!is_dir($this->pasta)) {
            @mkdir($this->pasta, 0755, TRUE);
        }
        // grava em arquivo temporário e renomeia: outra requisição nunca lê arquivo pela metade
        $tmp = $arquivo . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $texto) !== FALSE) {
            @rename($tmp, $arquivo);
        } else {
            log_message('error', 'Malha_municipal: não foi possível gravar ' . $arquivo);
        }
        return $texto;
    }

    /**
     * Coordenada dentro do município? TRUE/FALSE, ou NULL quando não dá para
     * validar (sem coordenada ou sem malha).
     */
    public function contem($codigo, $lat, $lng, $buscar = TRUE) {
        if ($lat === NULL || $lng === NULL) {
            return NULL;
        }
        $malha = $this->obter($codigo, $buscar);
        return $malha === NULL ? NULL : Geo_municipio::contem($malha, $lat, $lng);
    }

    private function baixar($url) {
        if (!function_exists('curl_init')) {
            return NULL;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT      => 'SXData-Painel/1.0',
        ]);
        $corpo = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch);
        curl_close($ch);

        if ($http !== 200 || !is_string($corpo) || $corpo === '') {
            log_message('error', 'Malha_municipal: IBGE respondeu HTTP ' . $http . ($erro ? ' (' . $erro . ')' : '') . ' para ' . $url);
            return NULL;
        }
        return $corpo;
    }
}
