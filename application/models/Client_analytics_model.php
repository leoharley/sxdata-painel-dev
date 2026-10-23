<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Client_analytics_model
 *
 * Estatisticas do painel do cliente calculadas DIRETO do banco.
 *
 * Nao depende da OpenAI nem de nenhuma geracao manual: os numeros sao
 * lidos das respostas no momento em que a pagina e aberta, entao o
 * cliente acompanha a coleta em tempo real.
 *
 * As analises de IA (ai_statistical_analyses) continuam existindo e
 * aparecem como secao adicional quando o administrador as gerar.
 */
class Client_analytics_model extends CI_Model {

    public function __construct() {
        parent::__construct();
    }

    /**
     * Projetos aos quais um cliente tem acesso.
     */
    public function get_client_project_ids($user_id) {
        $rows = $this->db->select('project_id')
                         ->where('user_id', (int) $user_id)
                         ->get('project_clients')
                         ->result();

        return array_map(function ($r) { return (int) $r->project_id; }, $rows);
    }

    /**
     * Questionarios dos projetos informados.
     *
     * NAO filtra por status: um questionario pausado/inativo que ja foi
     * aplicado continua tendo resultados que interessam ao cliente.
     */
    public function get_questionnaires_for_projects($project_ids) {
        if (empty($project_ids)) {
            return array();
        }

        $this->db->select('q.id, q.title, q.description, q.status, q.project_id, p.name as project_name');
        $this->db->from('questionnaires q');
        $this->db->join('projects p', 'q.project_id = p.id', 'left');
        $this->db->where_in('q.project_id', $project_ids);
        $this->db->order_by('q.title', 'ASC');

        return $this->db->get()->result();
    }

    /**
     * Numeros gerais da coleta de um questionario.
     */
    public function get_overview($questionnaire_id, $filtros = array()) {
        $this->db->select('
            COUNT(fr.id) as total_responses,
            COUNT(DISTINCT fr.applied_by) as total_applicators,
            COUNT(DISTINCT fr.location_name) as total_locations,
            MIN(fr.completed_at) as first_response,
            MAX(fr.completed_at) as last_response
        ');
        $this->db->from('form_responses fr');
        $this->db->where('fr.questionnaire_id', (int) $questionnaire_id);
        $this->db->where('fr.completed_at IS NOT NULL', NULL, FALSE);
        $this->_aplicar_periodo($filtros);

        $row = $this->db->get()->row();

        return array(
            'total_responses'   => $row ? (int) $row->total_responses : 0,
            'total_applicators' => $row ? (int) $row->total_applicators : 0,
            'total_locations'   => $row ? (int) $row->total_locations : 0,
            'first_response'    => $row ? $row->first_response : null,
            'last_response'     => $row ? $row->last_response : null,
        );
    }

    /**
     * Respostas por dia, para o grafico de evolucao da coleta.
     */
    public function get_responses_by_day($questionnaire_id, $days = 30) {
        $this->db->select('DATE(fr.completed_at) as dia, COUNT(fr.id) as total');
        $this->db->from('form_responses fr');
        $this->db->where('fr.questionnaire_id', (int) $questionnaire_id);
        $this->db->where('fr.completed_at IS NOT NULL', NULL, FALSE);
        $this->db->where('fr.completed_at >=', date('Y-m-d', strtotime('-' . (int) $days . ' days')));
        $this->db->group_by('DATE(fr.completed_at)');
        $this->db->order_by('dia', 'ASC');

        $rows = $this->db->get()->result();

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'dia'   => $r->dia,
                'total' => (int) $r->total,
            );
        }

        return $out;
    }

    /**
     * Cobertura por local.
     */
    public function get_coverage_by_location($questionnaire_id, $limit = 15) {
        $this->db->select('COALESCE(NULLIF(TRIM(fr.location_name), \'\'), \'Nao informado\') as rotulo,
                           COUNT(fr.id) as total', FALSE);
        $this->db->from('form_responses fr');
        $this->db->where('fr.questionnaire_id', (int) $questionnaire_id);
        $this->db->where('fr.completed_at IS NOT NULL', NULL, FALSE);
        $this->db->group_by('COALESCE(NULLIF(TRIM(fr.location_name), \'\'), \'Nao informado\')', FALSE);
        $this->db->order_by('total', 'DESC');
        $this->db->limit((int) $limit);

        return $this->_to_label_rows($this->db->get()->result());
    }

    /**
     * Cobertura por aplicador.
     */
    public function get_coverage_by_applicator($questionnaire_id, $limit = 15) {
        // Coletas por QR Code não têm aplicador: aparecem agrupadas com nome próprio
        $rotulo = $this->db->field_exists('origin', 'form_responses')
            ? "CASE WHEN fr.origin = 'qrcode' THEN 'Coleta pública (QR Code)' ELSE COALESCE(u.full_name, 'Não identificado') END"
            : "COALESCE(u.full_name, 'Não identificado')";

        $this->db->select($rotulo . ' as rotulo, COUNT(fr.id) as total', FALSE);
        $this->db->from('form_responses fr');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');
        $this->db->where('fr.questionnaire_id', (int) $questionnaire_id);
        $this->db->where('fr.completed_at IS NOT NULL', NULL, FALSE);
        $this->db->group_by($rotulo, FALSE);
        $this->db->order_by('total', 'DESC');
        $this->db->limit((int) $limit);

        return $this->_to_label_rows($this->db->get()->result());
    }

    /**
     * Distribuicao das respostas de cada pergunta fechada
     * (radio, checkbox, select) e resumo das numericas.
     */
    public function get_question_breakdown($questionnaire_id, $filtros = array()) {
        $this->db->select('id, question_text, question_type, order_index');
        $this->db->from('questions');
        $this->db->where('questionnaire_id', (int) $questionnaire_id);
        $this->db->order_by('order_index', 'ASC');

        $questions = $this->db->get()->result();

        $out = array();

        foreach ($questions as $question) {
            $tipo = $question->question_type;

            if (in_array($tipo, array('radio', 'select', 'checkbox'))) {
                $dados = $this->_options_distribution($question->id, $filtros);
            } elseif ($tipo === 'number') {
                $dados = $this->_number_summary($question->id, $filtros);
            } else {
                continue; // texto/data entram no bloco de respostas abertas
            }

            if (empty($dados['linhas']) && empty($dados['resumo'])) {
                continue; // pergunta ainda sem resposta
            }

            $out[] = array(
                'question_id'   => (int) $question->id,
                'question_text' => $question->question_text,
                'question_type' => $tipo,
                'total'         => $dados['total'],
                'linhas'        => $dados['linhas'],
                'resumo'        => $dados['resumo'],
            );
        }

        return $out;
    }

    /**
     * Respostas abertas (texto), das mais recentes para as mais antigas.
     */
    public function get_open_answers($questionnaire_id, $limit = 50, $filtros = array()) {
        $this->db->select('q.id as question_id, q.question_text, qr.response_text, fr.completed_at');
        $this->db->from('question_responses qr');
        $this->db->join('questions q', 'qr.question_id = q.id', 'inner');
        $this->db->join('form_responses fr', 'qr.form_response_id = fr.id', 'inner');
        $this->db->where('q.questionnaire_id', (int) $questionnaire_id);
        $this->db->where_in('q.question_type', array('text', 'textarea'));
        $this->db->where('fr.completed_at IS NOT NULL', NULL, FALSE);
        $this->db->where('TRIM(COALESCE(qr.response_text, \'\')) <> \'\'', NULL, FALSE);
        $this->_aplicar_periodo($filtros);
        $this->db->order_by('fr.completed_at', 'DESC');
        $this->db->limit((int) $limit);

        $rows = $this->db->get()->result();

        $agrupado = array();
        foreach ($rows as $r) {
            $qid = (int) $r->question_id;
            if (!isset($agrupado[$qid])) {
                $agrupado[$qid] = array(
                    'question_text' => $r->question_text,
                    'respostas'     => array(),
                );
            }
            $agrupado[$qid]['respostas'][] = array(
                'texto' => $r->response_text,
                'data'  => $r->completed_at,
            );
        }

        return array_values($agrupado);
    }

    // ------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------

    /**
     * Distribuicao de opcoes para radio/select/checkbox.
     *
     * selected_options guarda JSON, entao a contagem e feita em PHP.
     */
    private function _options_distribution($question_id, $filtros = array()) {
        $this->db->select('qr.selected_options, qr.response_text');
        $this->db->from('question_responses qr');
        $this->db->join('form_responses fr', 'qr.form_response_id = fr.id', 'inner');
        $this->db->where('qr.question_id', (int) $question_id);
        $this->db->where('fr.completed_at IS NOT NULL', NULL, FALSE);
        $this->_aplicar_periodo($filtros);

        $rows = $this->db->get()->result();

        // O app grava o VALOR da opção ("muito_satisfeito(a)"), e versões
        // diferentes gravaram a mesma opção de jeitos diferentes
        // ("muito_satisfeitoa"). A contagem é pelo TEXTO da opção, na ordem
        // do questionário; o que não casar com nenhuma opção aparece legível.
        $opcoes = $this->db->select('option_text, option_value')
                           ->where('question_id', (int) $question_id)
                           ->order_by('order_index', 'ASC')
                           ->get('question_options')
                           ->result();

        $rotulo_de = array();
        $contagem = array();
        foreach ($opcoes as $o) {
            $texto = trim((string) $o->option_text);
            if ($texto === '') {
                continue;
            }
            $contagem[$texto] = 0;
            $rotulo_de[$this->_normalizar($texto)] = $texto;
            if ($o->option_value !== NULL && trim((string) $o->option_value) !== '') {
                $rotulo_de[$this->_normalizar($o->option_value)] = $texto;
            }
        }

        $total_respondentes = 0;

        foreach ($rows as $r) {
            $valores = $this->_extract_values($r);

            if (empty($valores)) {
                continue;
            }

            $total_respondentes++;

            foreach ($valores as $v) {
                $v = trim((string) $v);
                if ($v === '') {
                    continue;
                }
                $chave = $this->_normalizar($v);
                $rotulo = isset($rotulo_de[$chave]) ? $rotulo_de[$chave] : $this->_legivel($v);
                if (!isset($contagem[$rotulo])) {
                    $contagem[$rotulo] = 0;
                }
                $contagem[$rotulo]++;
            }
        }

        if ($total_respondentes === 0) {
            return array('total' => 0, 'linhas' => array(), 'resumo' => array());
        }

        $linhas = array();
        foreach ($contagem as $rotulo => $qtd) {
            $linhas[] = array(
                'rotulo'     => $rotulo,
                'total'      => $qtd,
                'percentual' => $total_respondentes > 0
                                ? round(($qtd / $total_respondentes) * 100, 1)
                                : 0,
            );
        }

        return array(
            'total'  => $total_respondentes,
            'linhas' => $linhas,
            'resumo' => array(),
        );
    }

    /**
     * Le as opcoes marcadas de um registro, aceitando os formatos
     * que o app pode ter gravado (JSON array, string simples, ou texto).
     */
    private function _extract_values($row) {
        $bruto = $row->selected_options;

        if ($bruto !== NULL && trim((string) $bruto) !== '') {
            $decodificado = json_decode($bruto, true);

            if (is_array($decodificado)) {
                return $decodificado;
            }

            return array($bruto);
        }

        if ($row->response_text !== NULL && trim((string) $row->response_text) !== '') {
            return array($row->response_text);
        }

        return array();
    }

    /**
     * Resumo estatistico de perguntas numericas.
     */
    private function _number_summary($question_id, $filtros = array()) {
        $this->db->select('
            COUNT(qr.response_number) as total,
            AVG(qr.response_number)   as media,
            MIN(qr.response_number)   as minimo,
            MAX(qr.response_number)   as maximo
        ');
        $this->db->from('question_responses qr');
        $this->db->join('form_responses fr', 'qr.form_response_id = fr.id', 'inner');
        $this->db->where('qr.question_id', (int) $question_id);
        $this->db->where('qr.response_number IS NOT NULL', NULL, FALSE);
        $this->db->where('fr.completed_at IS NOT NULL', NULL, FALSE);
        $this->_aplicar_periodo($filtros);

        $row = $this->db->get()->row();

        if (!$row || (int) $row->total === 0) {
            return array('total' => 0, 'linhas' => array(), 'resumo' => array());
        }

        return array(
            'total'  => (int) $row->total,
            'linhas' => array(),
            'resumo' => array(
                'media'  => round((float) $row->media, 2),
                'minimo' => (float) $row->minimo,
                'maximo' => (float) $row->maximo,
            ),
        );
    }

    /**
     * Período opcional (date_from / date_to, inclusivos) sobre a data de
     * conclusão da entrevista. Sem filtros, nada muda.
     */
    private function _aplicar_periodo($filtros) {
        if (!empty($filtros['date_from'])) {
            $this->db->where('fr.completed_at >=', date('Y-m-d', strtotime($filtros['date_from'])));
        }
        if (!empty($filtros['date_to'])) {
            $this->db->where('fr.completed_at <', date('Y-m-d', strtotime($filtros['date_to'] . ' +1 day')));
        }
    }

    /**
     * Chave de comparação: minúsculas, sem acento e só letras e números.
     * "Muito satisfeito(a)", "muito_satisfeito(a)" e "muito_satisfeitoa"
     * viram a mesma chave.
     */
    private function _normalizar($texto) {
        $texto = mb_strtolower(trim((string) $texto), 'UTF-8');
        $texto = strtr($texto, array(
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
        ));
        return preg_replace('/[^a-z0-9]/', '', $texto);
    }

    /**
     * Valor que não casou com nenhuma opção, em forma legível:
     * "nao_sei_responder" vira "Nao sei responder".
     */
    private function _legivel($valor) {
        $texto = trim(str_replace('_', ' ', (string) $valor));
        return $texto === '' ? $texto : mb_strtoupper(mb_substr($texto, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($texto, 1, NULL, 'UTF-8');
    }

    /**
     * Converte linhas "rotulo/total" acrescentando o percentual.
     */
    private function _to_label_rows($rows) {
        $total_geral = 0;
        foreach ($rows as $r) {
            $total_geral += (int) $r->total;
        }

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'rotulo'     => $r->rotulo,
                'total'      => (int) $r->total,
                'percentual' => $total_geral > 0
                                ? round(((int) $r->total / $total_geral) * 100, 1)
                                : 0,
            );
        }

        return $out;
    }
}
