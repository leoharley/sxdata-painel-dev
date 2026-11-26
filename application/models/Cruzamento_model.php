<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Cruzamento.php';

/**
 * Variáveis e registros para o cruzamento (frente J), nos dois formatos:
 * - simples: perguntas de escolha, números (em faixas) e datas (por mês);
 * - estruturado: perguntas do domicílio ou do morador (unidade escolhida),
 *   inclusive calculados (renda, per capita), temas e categoria do entrevistado.
 * Mais as variáveis da coleta: mês, entrevistador, local e ponto de campo.
 *
 * Variáveis "identificadoras" (entrevistador, local, ponto) ficam fora para o
 * perfil cliente; texto livre, CPF, telefone e foto nunca entram.
 */
class Cruzamento_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    public function disponivel() {
        return $this->db->table_exists('cruzamentos_salvos');
    }

    public function questionario($qid) {
        return $this->db->select("q.id, q.title, q.project_id, COALESCE(q.formato, 'simples') AS formato, p.name AS projeto", FALSE)
            ->from('questionnaires q')->join('projects p', 'p.id = q.project_id', 'left')
            ->where('q.id', (int) $qid)->get()->row();
    }

    /** Questionários com respostas, dos projetos informados (NULL = todos). */
    public function questionarios($projetos = NULL) {
        $this->db->select("q.id, q.title, COALESCE(q.formato, 'simples') AS formato, p.name AS projeto, COUNT(fr.id) AS n", FALSE)
            ->from('questionnaires q')->join('projects p', 'p.id = q.project_id', 'left')
            ->join('form_responses fr', 'fr.questionnaire_id = q.id')
            ->group_by('q.id, q.title, q.formato, p.name')->order_by('p.name')->order_by('q.title');
        if ($projetos !== NULL) {
            if (!$projetos) {
                return [];
            }
            $this->db->where_in('q.project_id', $projetos);
        }
        return $this->db->get()->result();
    }

    // ------------------------------------------------------------
    // Variáveis
    // ------------------------------------------------------------

    /** @return array id => [id, rotulo, tipo (categoria|numero|data), opcoes, identificador, nivel, grupo] */
    public function variaveis($q, $unidade = 'domicilio', $cliente = FALSE) {
        $v = [];
        if ($q->formato === 'estruturado') {
            $this->load->model('Estruturado_model', 'estr');
            $ver = $this->estr->versao($q->id);
            $def = $ver ? $ver->definicao : ['etapas' => []];
            foreach ($def['etapas'] as $e) {
                $nivel = $e['nivel'] ?? ($e['tipo'] === 'moradores' ? 'moradores' : 'domicilio');
                if (($e['tipo'] ?? '') !== 'perguntas' || !in_array($nivel, ['domicilio', 'morador'], TRUE)) {
                    continue;
                }
                if ($nivel === 'morador' && $unidade !== 'morador') {
                    continue;
                }
                foreach ($e['perguntas'] as $p) {
                    $tipo = $this->tipo_estruturado($p);
                    if (!$tipo) {
                        continue;
                    }
                    $op = [];
                    foreach ($p['opcoes'] ?? [] as $o) {
                        $op[(string) $o['valor']] = $o['texto'];
                    }
                    $txt = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('[NOME]', 'o morador', $p['texto'] ?? ''))));
                    $v[$p['codigo']] = ['id' => $p['codigo'], 'rotulo' => (($p['numero'] ?? '') !== '' ? $p['numero'] . '. ' : '') . mb_substr($txt, 0, 110),
                        'tipo' => $tipo, 'opcoes' => $op, 'identificador' => FALSE, 'nivel' => $nivel,
                        'grupo' => $nivel === 'morador' ? 'Morador' : 'Domicílio'];
                }
            }
        } else {
            $perguntas = $this->db->select('id, question_text, question_type, order_index')->order_by('order_index')->order_by('id')
                ->get_where('questions', ['questionnaire_id' => $q->id])->result();
            $ids = array_map(function ($p) { return (int) $p->id; }, $perguntas);
            $ops = [];
            if ($ids) {
                foreach ($this->db->where_in('question_id', $ids)->order_by('order_index')->get('question_options')->result() as $o) {
                    $ops[(int) $o->question_id][(string) ($o->option_value !== NULL && $o->option_value !== '' ? $o->option_value : $o->option_text)] = $o->option_text;
                }
            }
            $n = 0;
            foreach ($perguntas as $p) {
                $n++;
                $tipo = in_array($p->question_type, ['radio', 'checkbox', 'select', 'dropdown'], TRUE) ? 'categoria'
                    : ($p->question_type === 'number' ? 'numero' : ($p->question_type === 'date' ? 'data' : NULL));
                if (!$tipo) {
                    continue;
                }
                $v['q' . $p->id] = ['id' => 'q' . $p->id, 'rotulo' => $n . '. ' . mb_substr(trim(strip_tags($p->question_text)), 0, 110),
                    'tipo' => $tipo, 'opcoes' => $ops[(int) $p->id] ?? [], 'identificador' => FALSE, 'nivel' => 'domicilio', 'grupo' => 'Perguntas',
                    'multipla' => $p->question_type === 'checkbox'];
            }
        }
        $meta = [
            '_mes' => ['Mês da entrevista', 'data', FALSE],
            '_aplicador' => ['Entrevistador', 'categoria', TRUE],
            '_local' => ['Local informado', 'categoria', TRUE],
            '_ponto' => ['Ponto de campo', 'categoria', TRUE],
        ];
        if ($q->formato === 'estruturado' && $this->db->table_exists('entrevistados')) {
            $meta['_categoria'] = ['Categoria do entrevistado', 'categoria', FALSE];
        }
        foreach ($meta as $id => $m) {
            $v[$id] = ['id' => $id, 'rotulo' => $m[0], 'tipo' => $m[1], 'opcoes' => [], 'identificador' => $m[2], 'nivel' => 'domicilio', 'grupo' => 'Coleta'];
        }
        if ($cliente) {
            $v = array_filter($v, function ($x) { return !$x['identificador']; });
        }
        return $v;
    }

    private function tipo_estruturado($p) {
        $t = $p['tipo'] ?? '';
        if (in_array($t, ['unica', 'multipla'], TRUE)) {
            return 'categoria';
        }
        if (in_array($t, ['inteiro', 'decimal', 'moeda'], TRUE)) {
            return 'numero';
        }
        if ($t === 'calculado') {
            return in_array($p['formato'] ?? 'auto', ['moeda', 'decimal', 'inteiro', 'auto', 'duracao'], TRUE) ? 'numero' : NULL;
        }
        if ($t === 'data') {
            return 'data';
        }
        return NULL;
    }

    // ------------------------------------------------------------
    // Registros
    // ------------------------------------------------------------

    /**
     * Monta o cruzamento.
     * @param array $c linha, coluna (opcional), medida (numérica, opcional), unidade, filtros (de, ate, aplicador, var, valor), faixas
     */
    public function calcular($q, array $c, $cliente = FALSE, $k_minimo = 0) {
        $unidade = ($q->formato === 'estruturado' && ($c['unidade'] ?? '') === 'morador') ? 'morador' : 'domicilio';
        $vars = $this->variaveis($q, $unidade, $cliente);
        $vl = $vars[$c['linha'] ?? ''] ?? NULL;
        $vc = !empty($c['coluna']) ? ($vars[$c['coluna']] ?? NULL) : NULL;
        $vm = !empty($c['medida']) ? ($vars[$c['medida']] ?? NULL) : NULL;
        $vf = !empty($c['filtros']['var']) ? ($vars[$c['filtros']['var']] ?? NULL) : NULL;
        if (!$vl || (!empty($c['coluna']) && !$vc) || ($vm && $vm['tipo'] !== 'numero')) {
            throw new InvalidArgumentException('Escolha as variáveis do cruzamento.');
        }
        $usadas = array_filter([$vl, $vc, $vm, $vf]);
        $base = $this->unidades($q, $unidade, $c['filtros'] ?? []);
        $valores = $this->valores($q, $unidade, $usadas, array_keys($base));

        $faixas = [];
        foreach ([$vl, $vc] as $v) {
            if ($v && $v['tipo'] === 'numero') {
                $todos = [];
                foreach ($base as $u => $info) {
                    $x = $valores[$u][$v['id']] ?? NULL;
                    if (is_numeric($x)) { $todos[] = $x; }
                }
                $faixas[$v['id']] = Cruzamento::faixas($todos, max(2, min(10, (int) ($c['faixas'] ?? 5))));
            }
        }
        $cat = function ($v, $u, $info) use ($valores, $faixas) {
            if (!$v) {
                return ['Total'];
            }
            $x = $this->valor_meta($v['id'], $info) ?? ($valores[$u][$v['id']] ?? NULL);
            if ($v['tipo'] === 'numero') {
                if ($x === 'NS/NR') { return ['NS/NR']; }
                $f = Cruzamento::faixa_de($x, $faixas[$v['id']] ?? []);
                return $f === NULL ? [] : [$f];
            }
            if ($v['tipo'] === 'data') {
                $t = is_string($x) ? strtotime($x) : FALSE;
                return $t ? [date('Y-m', $t)] : [];
            }
            return is_array($x) ? $x : ($x === NULL ? [] : [(string) $x]);
        };
        $registros = [];
        $fv = (string) ($c['filtros']['valor'] ?? '');
        foreach ($base as $u => $info) {
            if ($vf && $fv !== '' && !in_array($fv, $cat($vf, $u, $info), TRUE)) {
                continue;
            }
            $m = $vm ? ($valores[$u][$vm['id']] ?? NULL) : NULL;
            $registros[] = ['l' => $cat($vl, $u, $info), 'c' => $cat($vc, $u, $info), 'm' => is_numeric($m) ? $m : NULL];
        }
        $ordem = function ($v) use ($faixas) {
            if (!$v) { return []; }
            if ($v['tipo'] === 'numero') { return array_merge(array_column($faixas[$v['id']] ?? [], 2), ['NS/NR']); }
            return array_merge(array_keys($v['opcoes']), ['NS/NR']);
        };
        $rot = function ($v) {
            if (!$v) { return []; }
            $r = $v['opcoes'];
            if ($v['tipo'] === 'data') {
                $meses = ['01' => 'jan', '02' => 'fev', '03' => 'mar', '04' => 'abr', '05' => 'mai', '06' => 'jun', '07' => 'jul', '08' => 'ago', '09' => 'set', '10' => 'out', '11' => 'nov', '12' => 'dez'];
                for ($a = 2020; $a <= 2035; $a++) { foreach ($meses as $k => $n) { $r["$a-$k"] = "$n/$a"; } }
            }
            return $r;
        };
        $res = Cruzamento::tabela($registros, [
            'ordem_l' => $ordem($vl), 'ordem_c' => $ordem($vc), 'rotulos_l' => $rot($vl), 'rotulos_c' => $rot($vc),
            'incluir_vazios' => !empty($c['incluir_vazios']), 'k_minimo' => $k_minimo, 'sem_coluna' => !$vc,
        ]);
        $res['variaveis'] = ['linha' => $vl['rotulo'], 'coluna' => $vc ? $vc['rotulo'] : NULL, 'medida' => $vm ? $vm['rotulo'] : NULL,
            'filtro' => $vf ? $vf['rotulo'] . ' = ' . ($vf['opcoes'][$fv] ?? $fv) : NULL];
        $res['unidade'] = $unidade;
        $res['base'] = count($base);
        return $res;
    }

    /** Unidades de análise (entrevista ou morador) com os dados da coleta. */
    private function unidades($q, $unidade, array $f) {
        $this->db->select("fr.id, fr.completed_at, fr.location_name, u.full_name AS aplicador, fr.field_point_id", FALSE)
            ->from('form_responses fr')->join('users u', 'u.id = fr.applied_by', 'left')->where('fr.questionnaire_id', (int) $q->id);
        if (!empty($f['de']) && strtotime($f['de'])) {
            $this->db->where('fr.completed_at >=', date('Y-m-d 00:00:00', strtotime($f['de'])));
        }
        if (!empty($f['ate']) && strtotime($f['ate'])) {
            $this->db->where('fr.completed_at <=', date('Y-m-d 23:59:59', strtotime($f['ate'])));
        }
        if (!empty($f['aplicador'])) {
            $this->db->where('fr.applied_by', (int) $f['aplicador']);
        }
        $frs = $this->db->get()->result_array();
        $pontos = [];
        if ($this->db->table_exists('field_points')) {
            $ids = array_values(array_filter(array_column($frs, 'field_point_id')));
            if ($ids) {
                foreach ($this->db->select('id, nome')->where_in('id', array_unique($ids))->get('field_points')->result() as $p) {
                    $pontos[(int) $p->id] = $p->nome;
                }
            }
        }
        $cats = [];
        if ($q->formato === 'estruturado' && $this->db->table_exists('entrevistados')) {
            foreach ($this->db->query('SELECT fr.id, e.categoria FROM form_responses fr JOIN entrevistados e ON e.id = fr.entrevistado_id WHERE fr.questionnaire_id = ?', [(int) $q->id])->result() as $r) {
                $cats[(int) $r->id] = $r->categoria;
            }
        }
        $base = [];
        foreach ($frs as $fr) {
            $info = ['fr' => (int) $fr['id'], 'mes' => $fr['completed_at'], 'aplicador' => $fr['aplicador'], 'local' => $fr['location_name'],
                'ponto' => $pontos[(int) $fr['field_point_id']] ?? NULL, 'categoria' => $cats[(int) $fr['id']] ?? NULL];
            $base['f' . $fr['id']] = $info;
        }
        if ($unidade === 'morador') {
            $por_fr = $base;
            $base = [];
            if ($por_fr) {
                $ids = array_map(function ($x) { return $x['fr']; }, $por_fr);
                foreach (array_chunk($ids, 5000) as $lote) {
                    foreach ($this->db->select('id, form_response_id')->where_in('form_response_id', $lote)->get('moradores')->result() as $m) {
                        $base['m' . $m->id] = $por_fr['f' . $m->form_response_id] + ['morador' => (int) $m->id];
                    }
                }
            }
        }
        return $base;
    }

    private function valor_meta($id, $info) {
        switch ($id) {
            case '_mes': return $info['mes'];
            case '_aplicador': return $info['aplicador'] ?: NULL;
            case '_local': return $info['local'] ?: NULL;
            case '_ponto': return $info['ponto'] ?: NULL;
            case '_categoria': return $info['categoria'] ?: NULL;
        }
        return NULL;
    }

    /** valor[unidade][variável] só das variáveis usadas. */
    private function valores($q, $unidade, array $usadas, array $unidades) {
        $out = [];
        $ids = array_values(array_filter(array_map(function ($v) { return $v['id'][0] === '_' ? NULL : $v['id']; }, $usadas)));
        if (!$ids || !$unidades) {
            return $out;
        }
        if ($q->formato !== 'estruturado') {
            $qids = array_map(function ($x) { return (int) substr($x, 1); }, $ids);
            $sql = 'SELECT r.form_response_id, r.question_id, r.response_text, r.response_number, r.response_date, r.selected_options
                      FROM question_responses r JOIN form_responses fr ON fr.id = r.form_response_id
                     WHERE fr.questionnaire_id = ? AND r.question_id IN (' . implode(',', $qids) . ')';
            foreach ($this->db->query($sql, [(int) $q->id])->result_array() as $r) {
                $v = NULL;
                $s = $r['selected_options'];
                if ($s !== NULL && $s !== '' && $s !== '[]' && $s !== 'null') {
                    $d = json_decode($s, TRUE);
                    $v = is_array($d) ? array_values(array_filter(array_map('strval', $d), 'strlen')) : $s;
                    if (is_array($v) && count($v) === 1) { $v = $v[0]; }
                } elseif ($r['response_number'] !== NULL && $r['response_number'] !== '') {
                    $v = (float) $r['response_number'];
                } elseif ($r['response_date']) {
                    $v = $r['response_date'];
                } elseif (trim((string) $r['response_text']) !== '') {
                    $v = trim($r['response_text']);
                    if (is_numeric(str_replace(',', '.', $v))) { $v = (float) str_replace(',', '.', $v); }
                }
                $out['f' . $r['form_response_id']]['q' . $r['question_id']] = $v;
            }
            return $out;
        }
        // Estruturado: domicílio (morador_id nulo) e morador
        $niveis = [];
        foreach ($usadas as $v) {
            $niveis[$v['id']] = $v['nivel'];
        }
        $marcas = implode(',', array_map([$this->db, 'escape'], $ids));
        $sql = "SELECT r.form_response_id, r.morador_id, r.codigo, r.valor, r.valor_numero, r.nsnr
                  FROM respostas_estruturadas r JOIN form_responses fr ON fr.id = r.form_response_id
                 WHERE fr.questionnaire_id = ? AND r.repeticao_id IS NULL AND r.codigo IN ($marcas)";
        $dom = [];
        $mor = [];
        foreach ($this->db->query($sql, [(int) $q->id])->result_array() as $r) {
            if ($r['nsnr'] === 't') {
                $v = 'NS/NR';
            } else {
                $v = json_decode((string) $r['valor'], TRUE);
                if ($r['valor_numero'] !== NULL && !is_array($v)) { $v = (float) $r['valor_numero']; }
                if (is_array($v) && isset($v['valor'])) { $v = $v['valor']; }
            }
            if ($r['morador_id']) {
                $mor[(int) $r['morador_id']][$r['codigo']] = $v;
            } else {
                $dom[(int) $r['form_response_id']][$r['codigo']] = $v;
            }
        }
        if ($unidade === 'morador') {
            $mapa = [];
            foreach ($unidades as $u) {
                $mid = (int) substr($u, 1);
                $mapa[$mid] = TRUE;
            }
            // domicílio de cada morador
            $ids_m = array_keys($mapa);
            foreach (array_chunk($ids_m, 5000) as $lote) {
                foreach ($this->db->select('id, form_response_id')->where_in('id', $lote)->get('moradores')->result() as $m) {
                    $out['m' . $m->id] = ($dom[(int) $m->form_response_id] ?? []) + ($mor[(int) $m->id] ?? []);
                }
            }
            return $out;
        }
        foreach ($dom as $fr => $vals) {
            $out['f' . $fr] = $vals;
        }
        return $out;
    }

    // ------------------------------------------------------------
    // Análises salvas
    // ------------------------------------------------------------

    public function salvas($qid = NULL, $so_liberadas = FALSE, $projetos = NULL) {
        $this->db->select('c.*, q.title AS questionario, u.full_name AS autor')->from('cruzamentos_salvos c')
            ->join('questionnaires q', 'q.id = c.questionnaire_id')->join('users u', 'u.id = c.criado_por', 'left');
        if ($qid) {
            $this->db->where('c.questionnaire_id', (int) $qid);
        }
        if ($so_liberadas) {
            $this->db->where('c.liberado_cliente', TRUE);
        }
        if ($projetos !== NULL) {
            if (!$projetos) { return []; }
            $this->db->where_in('q.project_id', $projetos);
        }
        return $this->db->order_by('c.atualizado_em', 'DESC')->get()->result();
    }

    public function salva($id) {
        return $this->db->get_where('cruzamentos_salvos', ['id' => (int) $id])->row();
    }

    public function salvar($id, $qid, $titulo, array $config, $interpretacao, $por, $ia_log_id = NULL) {
        $d = ['questionnaire_id' => (int) $qid, 'titulo' => mb_substr(trim($titulo), 0, 200), 'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
            'interpretacao' => $interpretacao !== '' ? $interpretacao : NULL, 'atualizado_em' => date('Y-m-d H:i:s')];
        if ($ia_log_id) { $d['ia_log_id'] = (int) $ia_log_id; }
        if ($id) {
            $this->db->where('id', (int) $id)->update('cruzamentos_salvos', $d);
            return (int) $id;
        }
        $this->db->insert('cruzamentos_salvos', $d + ['criado_por' => $por ? (int) $por : NULL]);
        return (int) $this->db->insert_id();
    }

    public function liberar($id, $sim) {
        return $this->db->where('id', (int) $id)->update('cruzamentos_salvos', ['liberado_cliente' => (bool) $sim]);
    }

    public function excluir($id) {
        return $this->db->where('id', (int) $id)->delete('cruzamentos_salvos');
    }
}
