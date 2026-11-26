<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once __DIR__ . '/Estrutura_motor.php';

/**
 * Envio de uma entrevista estruturada pelo app (POST api/estruturado/enviar):
 * normaliza o corpo e confere a entrevista com o motor. Regra de ouro: nada
 * do que o entrevistador coletou é recusado por inconsistência. O que não
 * bate vai para a fila de revisão; só um corpo ilegível é recusado.
 *
 * Classe pura: tests/sxdata/Estrutura_envio_test.php.
 */
class Estrutura_envio {

    /**
     * @return array [dados normalizados ou NULL, erros de formato]
     */
    public static function normalizar($json) {
        if (!is_array($json)) {
            return [NULL, ['Corpo da requisição inválido.']];
        }
        $erros = [];
        $client_id = self::texto($json['client_id'] ?? NULL, 64);
        if ($client_id === NULL) { $erros[] = 'client_id é obrigatório.'; }
        $qid = (int) ($json['questionnaire_id'] ?? 0);
        if ($qid <= 0) { $erros[] = 'questionnaire_id é obrigatório.'; }

        $moradores = [];
        $vistos = [];
        foreach ((is_array($json['moradores'] ?? NULL) ? $json['moradores'] : []) as $i => $m) {
            if (!is_array($m)) { continue; }
            $cid = self::texto($m['client_id'] ?? NULL, 64);
            if ($cid === NULL || isset($vistos[$cid])) {
                $cid = ($client_id ?: 'x') . '-m' . ($i + 1); // sem UUID: um estável, derivado da entrevista
            }
            $vistos[$cid] = TRUE;
            $moradores[] = [
                'client_id' => $cid,
                'ordem' => isset($m['ordem']) && is_numeric($m['ordem']) ? (int) $m['ordem'] : $i + 1,
                'nome' => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($m['nome'] ?? ''))), 0, 200),
                'status' => in_array($m['status'] ?? '', ['concluido', 'em_andamento', 'nao_iniciado', 'justificado'], TRUE) ? $m['status'] : 'concluido',
                'justificativa' => self::texto($m['justificativa'] ?? NULL, 2000),
                'respostas' => self::respostas($m['respostas'] ?? []),
            ];
        }

        $dados = [
            'client_id' => $client_id,
            'questionnaire_id' => $qid,
            'versao' => isset($json['versao']) && is_numeric($json['versao']) ? (int) $json['versao'] : NULL,
            'started_at' => self::data_hora($json['started_at'] ?? NULL),
            'completed_at' => self::data_hora($json['completed_at'] ?? NULL),
            'edited_at' => self::data_hora($json['edited_at'] ?? NULL),
            'latitude' => self::coordenada($json['latitude'] ?? NULL, 90),
            'longitude' => self::coordenada($json['longitude'] ?? NULL, 180),
            'location_name' => self::texto($json['location_name'] ?? NULL, 255),
            'photo_path' => self::arquivo($json['photo_path'] ?? NULL),
            'photo_path_2' => self::arquivo($json['photo_path_2'] ?? NULL),
            'audio_path' => self::arquivo($json['audio_path'] ?? NULL),
            'consent_given' => !empty($json['consent_given']),
            'respostas' => self::respostas($json['respostas'] ?? []),
            'moradores' => $moradores,
            'grupos' => self::grupos($json['grupos'] ?? [], $client_id),
            'entrevistado_id' => isset($json['entrevistado_id']) && is_numeric($json['entrevistado_id']) && (int) $json['entrevistado_id'] > 0 ? (int) $json['entrevistado_id'] : NULL,
            'calculados_app' => is_array($json['calculados'] ?? NULL) ? $json['calculados'] : [],
        ];
        return [$erros ? NULL : $dados, $erros];
    }

    /** grupos: {codigo: [{client_id, indice, respostas}]} (@repetir). */
    public static function grupos($g, $client_id) {
        if (!is_array($g)) { return []; }
        $saida = [];
        foreach ($g as $codigo => $lista) {
            if (!is_string($codigo) || !preg_match('/^[a-z_][a-z0-9_]{0,59}$/', $codigo) || !is_array($lista)) { continue; }
            $vistos = [];
            foreach (array_values($lista) as $i => $inst) {
                if (!is_array($inst)) { continue; }
                $cid = self::texto($inst['client_id'] ?? NULL, 64);
                if ($cid === NULL || isset($vistos[$cid])) { $cid = ($client_id ?: 'x') . '-' . $codigo . ($i + 1); }
                $vistos[$cid] = TRUE;
                $saida[$codigo][] = ['client_id' => $cid,
                    'indice' => isset($inst['indice']) && is_numeric($inst['indice']) ? (int) $inst['indice'] : $i + 1,
                    'respostas' => self::respostas($inst['respostas'] ?? [])];
            }
        }
        return $saida;
    }

    /** Aceita lista [{codigo, valor, nsnr, complementos, complemento}] ou mapa {codigo: {...}}. */
    public static function respostas($r) {
        if (!is_array($r)) { return []; }
        $saida = [];
        foreach ($r as $k => $item) {
            if (!is_array($item)) { continue; }
            $codigo = is_string($k) ? $k : ($item['codigo'] ?? NULL);
            if (!is_string($codigo) || !preg_match('/^[a-z_][a-z0-9_]{0,59}$/', $codigo)) { continue; }
            $resp = [];
            if (!empty($item['nsnr'])) { $resp['nsnr'] = TRUE; }
            if (array_key_exists('valor', $item)) { $resp['valor'] = $item['valor']; }
            if (!empty($item['complementos']) && is_array($item['complementos'])) {
                $resp['complementos'] = array_map(function ($v) { return is_scalar($v) ? (string) $v : ''; }, $item['complementos']);
            }
            if (isset($item['complemento']) && is_scalar($item['complemento'])) { $resp['complemento'] = (string) $item['complemento']; }
            $saida[$codigo] = $resp;
        }
        return $saida;
    }

    /**
     * Confere a entrevista com a definição: o que gravar, calculados do
     * servidor, divergências com o app, erros e alertas.
     *
     * @return array respostas_visiveis (domicilio, moradores[cid]), calculados,
     *               erros, alertas, divergencias, revisao (NULL|'pendente'), foto, coordenadas
     */
    public static function conferir(array $definicao, array $dados, array $contexto = []) {
        $motor = new Estrutura_motor($definicao);
        $r = $motor->avaliar($dados['respostas'], $dados['moradores'], $dados['grupos'] ?? [], $contexto);

        $manter = function ($respostas, $visiveis) use ($motor) {
            $out = [];
            foreach ($respostas as $codigo => $resp) {
                $p = $motor->pergunta($codigo);
                if ($p && !empty($visiveis[$codigo]) && $p['tipo'] !== 'calculado' && Estrutura_motor::respondida($resp)) {
                    $out[$codigo] = $resp;
                }
            }
            return $out;
        };
        $dom = $manter($dados['respostas'], $r['visiveis']['domicilio']);
        $mor = [];
        foreach ($dados['moradores'] as $m) {
            $mor[$m['client_id']] = $manter($m['respostas'], $r['visiveis']['moradores'][$m['client_id']] ?? []);
        }
        $gru = [];
        foreach ($dados['grupos'] ?? [] as $g => $lista) {
            foreach ($lista as $inst) {
                $gru[$g][$inst['client_id']] = $manter($inst['respostas'], $r['visiveis']['grupos'][$g][$inst['client_id']] ?? []);
            }
        }

        // Cálculo do app x do servidor: vale o do servidor; diferença vai para revisão
        $divergencias = [];
        $app = $dados['calculados_app'];
        foreach ($r['calculados']['domicilio'] as $codigo => $c) {
            $d = self::divergencia($app['domicilio'][$codigo] ?? ($app[$codigo] ?? NULL), $c);
            if ($d) { $divergencias[] = ['codigo' => $codigo, 'morador' => NULL] + $d; }
        }
        foreach ($r['calculados']['moradores'] as $cid => $calc) {
            foreach ($calc as $codigo => $c) {
                $d = self::divergencia($app['moradores'][$cid][$codigo] ?? NULL, $c);
                if ($d) { $divergencias[] = ['codigo' => $codigo, 'morador' => $cid] + $d; }
            }
        }

        // Foto e coordenadas que são questões vão também para o cabeçalho (KMZ, mapa)
        $foto = NULL; $coord = NULL;
        foreach ($dom as $codigo => $resp) {
            $p = $motor->pergunta($codigo);
            if (($p['destino'] ?? '') === 'foto' || ($p['tipo'] === 'foto' && $foto === NULL)) {
                $foto = self::arquivo($resp['valor'] ?? NULL) ?: $foto;
            }
            if ($p['tipo'] === 'coordenadas' && is_array($resp['valor'] ?? NULL)) {
                $lat = self::coordenada($resp['valor']['lat'] ?? NULL, 90);
                $lng = self::coordenada($resp['valor']['lng'] ?? NULL, 180);
                if ($lat !== NULL && $lng !== NULL) { $coord = [$lat, $lng]; }
            }
        }

        $erros = $r['erros'];
        $alertas = $r['alertas'];
        foreach ($divergencias as $d) {
            $alertas[] = ['codigo' => $d['codigo'], 'morador' => $d['morador'],
                'mensagem' => 'Cálculo divergente entre o app (' . json_encode($d['app']) . ') e o servidor (' . json_encode($d['servidor']) . ').'];
        }
        return [
            'respostas_domicilio' => $dom,
            'respostas_moradores' => $mor,
            'respostas_grupos' => $gru,
            'calculados' => $r['calculados'],
            'erros' => $erros,
            'alertas' => $alertas,
            'divergencias' => $divergencias,
            'descartadas' => $r['descartadas'],
            'revisao' => ($erros || $divergencias) ? 'pendente' : NULL,
            'foto' => $foto,
            'coordenadas' => $coord,
        ];
    }

    private static function divergencia($app, array $servidor) {
        if ($app === NULL) { return NULL; } // app antigo ou sem cálculo: não é divergência
        $va = is_array($app) ? ($app['valor'] ?? NULL) : $app;
        $ia = is_array($app) ? !empty($app['incompleto']) : $servidor['incompleto'];
        $vs = $servidor['valor'];
        $igual = (is_numeric($va) && is_numeric($vs)) ? abs((float) $va - (float) $vs) < 0.01 : $va === $vs;
        if ($igual && $ia === $servidor['incompleto']) { return NULL; }
        return ['app' => $va, 'servidor' => $vs];
    }

    private static function texto($v, $max) {
        if (!is_scalar($v)) { return NULL; }
        $v = trim((string) $v);
        return $v === '' ? NULL : mb_substr($v, 0, $max);
    }

    /** Nome de arquivo enviado (nunca caminho do aparelho). */
    public static function arquivo($v) {
        if (!is_string($v)) { return NULL; }
        $v = trim($v);
        if ($v === '') { return NULL; }
        $base = basename(str_replace('\\', '/', $v));
        return preg_match('/^[A-Za-z0-9._-]{1,200}$/', $base) ? $base : NULL;
    }

    private static function data_hora($v) {
        if (!is_string($v) || trim($v) === '') { return NULL; }
        $ts = strtotime($v);
        return $ts === FALSE ? NULL : date('Y-m-d H:i:s', $ts);
    }

    private static function coordenada($v, $limite) {
        if (!is_numeric($v)) { return NULL; }
        $f = (float) $v;
        return (abs($f) <= $limite && $f != 0.0) ? round($f, 7) : NULL;
    }
}
