<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CNES - estabelecimentos de saúde do município.
 *
 * Fonte: API de dados abertos do Ministério da Saúde
 * (https://apidadosabertos.saude.gov.br/cnes/estabelecimentos). Roda no
 * servidor porque a API não libera CORS para o navegador.
 *
 * Indicadores:
 *   estabelecimentos_saude   total de estabelecimentos ativos
 *   estabelecimentos_nivel   por nível de atenção (tipo de unidade do CNES)
 *   ubs_quantidade           postos e centros de saúde/UBS (tipos 01 e 02)
 *   estabelecimentos_mac     atenção especializada + hospitalar
 *
 * A API devolve 20 estabelecimentos por página; as páginas são buscadas em
 * paralelo, em blocos.
 */
class Conector_cnes extends Relatorio_conector {

    const URL = 'https://apidadosabertos.saude.gov.br/cnes/estabelecimentos';
    const POR_PAGINA = 20;
    const PARALELAS = 20;
    const MAX_PAGINAS = 2500;   // 50 mil estabelecimentos ativos (São Paulo tem mais de 20 mil)
    const TEMPO_TOTAL = 240;    // segundos

    /** Tipos de unidade do CNES por nível de atenção. Os não listados são gestão, vigilância e apoio. */
    const NIVEIS = [
        'Atenção básica' => [1, 2, 32, 40, 71, 72, 74],
        'Média complexidade (ambulatorial e urgência)' => [4, 20, 21, 22, 36, 39, 42, 61, 69, 70, 73, 77, 78, 79],
        'Atenção hospitalar' => [5, 7, 15, 62],
    ];
    const OUTROS = 'Gestão, vigilância e apoio';
    const UBS = [1, 2];

    public function chave()  { return 'cnes'; }
    public function rotulo() { return 'CNES - Estabelecimentos de saúde (Ministério da Saúde)'; }
    public function eixos()  { return ['saude', 'saude_aps', 'saude_mac']; }

    public function executar($relatorio) {
        $codigo6 = substr(preg_replace('/\D/', '', (string) $relatorio->codigo_ibge), 0, 6);
        if (strlen($codigo6) !== 6) {
            throw new Exception('Código IBGE inválido: ' . $relatorio->codigo_ibge);
        }

        // Municípios grandes passam de 30 s (São Paulo: ~1.000 páginas)
        @set_time_limit(self::TEMPO_TOTAL + 60);
        $estabelecimentos = $this->buscar_todos($codigo6);
        $contagem = self::contar($estabelecimentos);

        $periodo = date('Y');
        $extra = ['fonte' => 'CNES / DATASUS (dados abertos do Ministério da Saúde)',
                  'periodo_fonte' => 'consulta em ' . date('d/m/Y')];

        $linhas = [
            $this->linha('estabelecimentos_saude', 'municipio', $periodo, $contagem['total'], $extra),
            $this->linha('ubs_quantidade', 'municipio', $periodo, $contagem['ubs'], $extra),
            $this->linha('estabelecimentos_mac', 'municipio', $periodo, $contagem['mac'], $extra),
        ];
        foreach ($contagem['niveis'] as $nivel => $n) {
            if ($n > 0) {
                $linhas[] = $this->linha('estabelecimentos_nivel', 'municipio', $periodo, $n,
                    $extra + ['categoria' => $nivel]);
            }
        }
        return $linhas;
    }

    /**
     * Conta os estabelecimentos ativos por nível de atenção. Sem rede: testável isoladamente.
     *
     * @param array $estabelecimentos itens da API (arrays com codigo_tipo_unidade)
     * @return array ['total' => int, 'ubs' => int, 'mac' => int, 'niveis' => [nível => int]]
     */
    public static function contar(array $estabelecimentos) {
        $niveis = array_fill_keys(array_merge(array_keys(self::NIVEIS), [self::OUTROS]), 0);
        $total = $ubs = 0;
        $vistos = [];

        foreach ($estabelecimentos as $e) {
            if (!empty($e['codigo_motivo_desabilitacao_estabelecimento'])) {
                continue; // desativado
            }
            $cnes = $e['codigo_cnes'] ?? null;
            if ($cnes !== null) {
                if (isset($vistos[$cnes])) continue; // páginas podem se sobrepor se a base mudar durante a consulta
                $vistos[$cnes] = true;
            }
            $tipo = (int) ($e['codigo_tipo_unidade'] ?? 0);
            $total++;
            if (in_array($tipo, self::UBS, true)) $ubs++;

            $nivel = self::OUTROS;
            foreach (self::NIVEIS as $nome => $tipos) {
                if (in_array($tipo, $tipos, true)) { $nivel = $nome; break; }
            }
            $niveis[$nivel]++;
        }

        $mac = $niveis['Média complexidade (ambulatorial e urgência)'] + $niveis['Atenção hospitalar'];
        return ['total' => $total, 'ubs' => $ubs, 'mac' => $mac, 'niveis' => $niveis];
    }

    private function buscar_todos($codigo6) {
        if (!function_exists('curl_multi_init')) {
            throw new Exception('A extensão cURL do PHP não está disponível no servidor.');
        }
        $inicio = time();
        $todos = [];
        $pagina = 0;

        while ($pagina < self::MAX_PAGINAS) {
            if (time() - $inicio > self::TEMPO_TOTAL) {
                throw new Exception('O CNES demorou mais de ' . self::TEMPO_TOTAL . ' s para responder; tente de novo mais tarde.');
            }
            $bloco = $this->buscar_bloco($codigo6, $pagina, self::PARALELAS);
            $fim = false;
            foreach ($bloco as $itens) {
                foreach ($itens as $e) {
                    // só o que a contagem usa: municípios grandes têm dezenas de milhares de registros
                    $todos[] = [
                        'codigo_cnes' => $e['codigo_cnes'] ?? null,
                        'codigo_tipo_unidade' => $e['codigo_tipo_unidade'] ?? null,
                        'codigo_motivo_desabilitacao_estabelecimento' => $e['codigo_motivo_desabilitacao_estabelecimento'] ?? null,
                    ];
                }
                if (count($itens) < self::POR_PAGINA) $fim = true;
            }
            if ($fim) return $todos;
            $pagina += self::PARALELAS;
        }
        throw new Exception('O município tem mais de ' . (self::MAX_PAGINAS * self::POR_PAGINA)
            . ' estabelecimentos no CNES; a contagem precisa rodar em segundo plano.');
    }

    /** Busca $quantas páginas a partir de $primeira, em paralelo. @return array[] itens por página, em ordem */
    private function buscar_bloco($codigo6, $primeira, $quantas) {
        $mh = curl_multi_init();
        $handles = [];
        for ($i = 0; $i < $quantas; $i++) {
            $url = self::URL . '?' . http_build_query([
                'codigo_municipio' => $codigo6,
                'status' => 1, // só ativos
                'limit'  => self::POR_PAGINA,
                'offset' => ($primeira + $i) * self::POR_PAGINA,
            ]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 40,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_USERAGENT      => 'SXData-Relatorios/1.0',
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$i] = $ch;
        }

        do {
            $status = curl_multi_exec($mh, $ativos);
            if ($ativos) curl_multi_select($mh, 1.0);
        } while ($ativos && $status === CURLM_OK);

        $paginas = [];
        $erro = null;
        foreach ($handles as $i => $ch) {
            $corpo = curl_multi_getcontent($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $falha = curl_error($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if ($http !== 200) {
                // nova tentativa, sozinha, antes de desistir
                list($http, $corpo, $falha) = $this->buscar_uma($codigo6, $primeira + $i);
            }
            if ($http !== 200) {
                $erro = $falha !== '' ? 'CNES indisponível (' . $falha . ')' : 'CNES indisponível (HTTP ' . $http . ')';
                break;
            }
            $json = json_decode($corpo, true);
            if (!is_array($json) || !isset($json['estabelecimentos']) || !is_array($json['estabelecimentos'])) {
                $erro = 'Resposta inesperada do CNES';
                break;
            }
            $paginas[$i] = $json['estabelecimentos'];
            unset($json, $corpo);
        }
        curl_multi_close($mh);

        if ($erro !== null) throw new Exception($erro);
        ksort($paginas);
        return $paginas;
    }

    private function buscar_uma($codigo6, $pagina) {
        sleep(2);
        $ch = curl_init(self::URL . '?' . http_build_query([
            'codigo_municipio' => $codigo6, 'status' => 1, 'limit' => self::POR_PAGINA, 'offset' => $pagina * self::POR_PAGINA,
        ]));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 10,
                                CURLOPT_USERAGENT => 'SXData-Relatorios/1.0']);
        $corpo = curl_exec($ch);
        $r = [(int) curl_getinfo($ch, CURLINFO_HTTP_CODE), (string) $corpo, curl_error($ch)];
        curl_close($ch);
        return $r;
    }
}
