<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Regras puras dos pontos de campo (sem banco): validação do que o app envia
 * e regra de acesso do aplicador aos projetos. Testável isoladamente
 * (tests/sxdata/Ponto_campo_test.php).
 */
class Ponto_campo {

    const TIPOS = ['comunidade', 'localidade', 'equipamento', 'outro'];
    const ORIGENS = ['gps', 'mapa'];

    /**
     * Valida e normaliza o corpo de POST api/points/submit.
     *
     * @param mixed $json corpo decodificado
     * @return array [dados normalizados, lista de erros]
     */
    public static function validar($json) {
        if (!is_array($json)) {
            return [NULL, ['Corpo da requisição inválido.']];
        }
        $erros = [];

        $client_id = self::client_id($json['client_id'] ?? NULL);
        if ($client_id === NULL) {
            $erros[] = 'client_id é obrigatório (até 64 caracteres).';
        }

        $project_id = isset($json['project_id']) && is_numeric($json['project_id']) ? (int) $json['project_id'] : 0;
        if ($project_id <= 0) {
            $erros[] = 'project_id é obrigatório.';
        }

        $codigo = preg_replace('/\D/', '', (string) ($json['codigo_ibge'] ?? ''));
        if (!preg_match('/^[1-5]\d{6}$/', $codigo)) {
            $erros[] = 'codigo_ibge deve ter 7 dígitos.';
        }

        $nome = is_string($json['nome'] ?? NULL) ? trim(preg_replace('/\s+/u', ' ', $json['nome'])) : '';
        if ($nome === '') {
            $erros[] = 'Informe o nome do ponto.';
        }
        // Erro de validação deixaria o ponto preso na fila do aparelho: o que
        // dá para corrigir aqui é corrigido (nome longo, tipo novo, coordenada ruim)
        $nome = mb_substr($nome, 0, 150);

        $tipo = is_string($json['tipo'] ?? NULL) ? strtolower(trim($json['tipo'])) : '';
        if ($tipo === '') {
            $tipo = 'comunidade';
        } elseif (!in_array($tipo, self::TIPOS, TRUE)) {
            $tipo = 'outro'; // versão mais nova do app com um tipo que o painel ainda não conhece
        }

        list($lat, $lng) = self::coordenadas($json['latitude'] ?? NULL, $json['longitude'] ?? NULL);

        $origem = is_string($json['origem_coord'] ?? NULL) ? strtolower(trim($json['origem_coord'])) : NULL;
        if ($lat === NULL || !in_array($origem, self::ORIGENS, TRUE)) {
            $origem = $lat === NULL ? NULL : 'gps';
        }

        if ($erros) {
            return [NULL, $erros];
        }

        return [[
            'client_id'    => $client_id,
            'project_id'   => $project_id,
            'codigo_ibge'  => (int) $codigo,
            'nome'         => $nome,
            'tipo'         => $tipo,
            'latitude'     => $lat,
            'longitude'    => $lng,
            'origem_coord' => $origem,
        ], []];
    }

    /**
     * Sem coordenada válida: [NULL, NULL]. Nunca 0,0 (um ponto no oceano),
     * mesma regra das coletas.
     */
    public static function coordenadas($lat, $lng) {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return [NULL, NULL];
        }
        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat == 0 && $lng == 0)) {
            return [NULL, NULL];
        }
        return [round($lat, 7), round($lng, 7)];
    }

    public static function client_id($valor) {
        if (!is_string($valor)) {
            return NULL;
        }
        $valor = trim($valor);
        return ($valor === '' || strlen($valor) > 64) ? NULL : $valor;
    }

    /**
     * Projetos a que o usuário tem acesso no app.
     *
     * Supervisor e administrador: todos ($todos). Aplicador: os projetos com
     * pelo menos um questionário ativo liberado para ele — a mesma regra de
     * GET api/questionnaires. $questionarios já vem filtrado por
     * Questionnaire_model (status ativo, projeto não concluído/cancelado,
     * aplicadores permitidos).
     *
     * @param string   $role
     * @param object[] $questionarios com ->project_id
     * @param int[]    $todos ids de todos os projetos
     * @return int[]
     */
    public static function projetos_acessiveis($role, array $questionarios, array $todos) {
        if (in_array($role, ['administrador', 'supervisor'], TRUE)) {
            $ids = array_map('intval', $todos);
        } else {
            $ids = [];
            foreach ($questionarios as $q) {
                if (!empty($q->project_id)) {
                    $ids[] = (int) $q->project_id;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        return $ids;
    }
}
