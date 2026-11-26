<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Pea_motor.php';

/**
 * Módulo PEA Gerdau (sql/pea_migration.sql). Estrutura do programa, fichas
 * das ações, respostas do formulário de avaliação (pelo link público de cada
 * ação), indicadores, regras versionadas, EIEAP, relatórios e a base de
 * terceiros do BancoDOC.
 */
class Pea_model extends CI_Model {

    const PUBLICOS = ['interno' => 'Interno', 'externo_comunidade' => 'Externo — comunidades', 'externo_escolar' => 'Externo — escolar'];
    const TITULO_FORMULARIO = 'PEA Gerdau — Avaliação da atividade';

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    public function disponivel() {
        static $ok = NULL;
        if ($ok === NULL) {
            $ok = $this->db->table_exists('pea_acoes') && $this->db->table_exists('pea_colaboradores');
        }
        return $ok;
    }

    public function ciclo() {
        return $this->db->order_by('ativo', 'DESC')->order_by('id', 'DESC')->get('pea_ciclos', 1)->row();
    }

    // ------------------------------------------------------------
    // Regras
    // ------------------------------------------------------------

    /** [versão, regras] vigentes. */
    public function regras() {
        $l = $this->db->order_by('versao', 'DESC')->get('pea_regras', 1)->row();
        return $l ? [(int) $l->versao, Pea_motor::regras(json_decode($l->regras, TRUE))] : [0, Pea_motor::regras_padrao()];
    }

    public function regras_versao($v) {
        $l = $this->db->get_where('pea_regras', ['versao' => (int) $v])->row();
        return $l ? Pea_motor::regras(json_decode($l->regras, TRUE)) : NULL;
    }

    public function historico_regras() {
        return $this->db->select('r.versao, r.nota, r.criado_em, u.full_name AS autor')->from('pea_regras r')
            ->join('users u', 'u.id = r.criado_por', 'left')->order_by('r.versao', 'DESC')->get()->result();
    }

    public function salvar_regras(array $regras, $nota, $por) {
        list($v) = $this->regras();
        $this->db->insert('pea_regras', ['versao' => $v + 1, 'regras' => json_encode($regras, JSON_UNESCAPED_UNICODE), 'nota' => $nota ?: NULL, 'criado_por' => $por ?: NULL]);
        return $v + 1;
    }

    // ------------------------------------------------------------
    // Carga do PEA 2026–2031
    // ------------------------------------------------------------

    /** Carrega o programa a partir do JSON (só se ainda não houver ciclo). Devolve um resumo. */
    public function carregar(array $s, $por) {
        if ($this->ciclo()) {
            throw new RuntimeException('O programa já está carregado.');
        }
        $this->db->trans_begin();
        try {
            $c = $s['ciclo'] ?? ['nome' => 'PEA Gerdau 2026–2031', 'inicio' => '2026-08-01', 'anos' => 5, 'mes_inicio' => 8];
            $this->db->insert('pea_ciclos', ['nome' => $c['nome'], 'inicio' => $c['inicio'], 'anos' => (int) $c['anos'], 'mes_inicio' => (int) $c['mes_inicio']]);
            $ciclo = (int) $this->db->insert_id();
            $un = [];
            foreach ($s['unidades'] as $u) {
                $this->db->insert('pea_unidades', ['sigla' => $u['sigla'], 'nome' => $u['nome']]);
                $un[$u['sigla']] = (int) $this->db->insert_id();
            }
            $com = [];
            foreach ($s['comunidades'] ?? [] as $x) {
                $this->db->insert('pea_comunidades', ['unidade_id' => $un[$x['unidade'] ?? ''] ?? NULL, 'nome' => $x['nome'], 'municipio' => $x['municipio'] ?? NULL, 'tipo' => $x['tipo'] ?? NULL]);
                $com[mb_strtolower($x['nome'])] = (int) $this->db->insert_id();
            }
            foreach ($s['escolas'] ?? [] as $x) {
                $this->db->insert('pea_escolas', ['unidade_id' => $un[$x['unidade'] ?? ''] ?? NULL, 'comunidade_id' => $com[mb_strtolower($x['comunidade'] ?? '')] ?? NULL,
                    'nome' => $x['nome'], 'municipio' => $x['municipio'] ?? NULL]);
            }
            $eix = [];
            foreach ($s['eixos'] as $e) {
                $this->db->insert('pea_eixos', ['nome' => $e]);
                $eix[mb_strtolower($e)] = (int) $this->db->insert_id();
            }
            $proj = [];
            foreach ($s['projetos'] as $i => $p) {
                $this->db->insert('pea_projetos', ['ciclo_id' => $ciclo, 'publico' => $p['publico'], 'eixo_id' => $eix[mb_strtolower($p['eixo'] ?? '')] ?? NULL,
                    'nome' => $p['nome'], 'descricao' => $p['descricao'] ?? NULL, 'periodicidade' => $p['periodicidade'] ?? NULL,
                    'ip_faixas' => !empty($p['ip_faixas']) ? json_encode($p['ip_faixas']) : NULL,
                    'avaliacao' => isset($p['avaliacao']) ? (bool) $p['avaliacao'] : TRUE, 'ordem' => $i + 1]);
                $proj[mb_strtolower($p['nome']) . '|' . $p['publico']] = (int) $this->db->insert_id();
                $proj[mb_strtolower($p['nome'])] = $proj[mb_strtolower($p['nome'])] ?? (int) $this->db->insert_id();
            }
            foreach ($s['linhas_tematicas'] ?? [] as $l) {
                $this->db->insert('pea_linhas', ['ciclo_id' => $ciclo, 'publico' => $l['publico'], 'linha' => mb_substr($l['linha'], 0, 250),
                    'conteudos' => json_encode($l['conteudos'] ?? [], JSON_UNESCAPED_UNICODE)]);
            }
            $n_crono = 0;
            foreach ($s['cronograma'] ?? [] as $c) {
                $pid = $proj[mb_strtolower($c['projeto']) . '|' . ($c['publico'] ?? '')] ?? ($proj[mb_strtolower($c['projeto'])] ?? NULL);
                if (!$pid || empty($c['acoes_previstas'])) {
                    continue;
                }
                $ex = $this->db->get_where('pea_cronograma', ['projeto_id' => $pid, 'ano' => (int) $c['ano_do_ciclo'], 'trimestre' => (int) $c['trimestre']])->row();
                if ($ex) {
                    $this->db->where('id', $ex->id)->update('pea_cronograma', ['previstas' => (int) $ex->previstas + (int) $c['acoes_previstas']]);
                } else {
                    $this->db->insert('pea_cronograma', ['projeto_id' => $pid, 'ano' => (int) $c['ano_do_ciclo'], 'trimestre' => (int) $c['trimestre'], 'previstas' => (int) $c['acoes_previstas']]);
                }
                $n_crono++;
            }
            // Árvore EIEAP: índices e valores informados no PEA (pesos iguais até a Gerdau enviar o método)
            $nos = [['iea', 'Indicador de Educação Ambiental', NULL]];
            $vistos = ['iea' => TRUE];
            foreach ($s['eieap'] ?? [] as $e) {
                $cod = $this->slug($e['indice']);
                if (!isset($vistos[$cod])) {
                    $vistos[$cod] = TRUE;
                    $nos[] = [$cod, $e['indice'], 'iea'];
                }
                foreach (['base' => ['linha_base_2021', 2021], 'atual' => ['valor_2025', 2025], 'meta' => ['meta_2026', 2026]] as $tipo => $k) {
                    if (isset($e[$k[0]]) && is_numeric($e[$k[0]])) {
                        $this->db->query('INSERT INTO pea_eieap_valores (no, unidade, tipo, ano, valor) VALUES (?, ?, ?, ?, ?) ON CONFLICT (no, unidade, tipo, ano) DO UPDATE SET valor = EXCLUDED.valor',
                            [$cod, $e['unidade'], $tipo, $k[1], (float) $e[$k[0]]]);
                    }
                }
            }
            foreach ($nos as $i => $n) {
                $this->db->insert('pea_eieap_nos', ['codigo' => $n[0], 'nome' => mb_substr($n[1], 0, 200), 'pai' => $n[2], 'peso' => 1, 'ordem' => $i]);
            }
            if (!$this->db->get('pea_regras', 1)->row()) {
                $this->db->insert('pea_regras', ['versao' => 1, 'regras' => json_encode(Pea_motor::regras_padrao(), JSON_UNESCAPED_UNICODE),
                    'nota' => 'Versão inicial: fórmulas do PEA 2026–2031 e propostas da seção 20 (pontos 1 a 5).', 'criado_por' => $por ?: NULL]);
            }
            $qid = $this->criar_formulario($por);
            if ($this->db->trans_status() === FALSE) {
                throw new RuntimeException('Falha ao carregar o programa.');
            }
            $this->db->trans_commit();
        } catch (Exception $e) {
            $this->db->trans_rollback();
            throw $e;
        }
        return ['ciclo' => $ciclo, 'unidades' => count($un), 'comunidades' => count($com), 'escolas' => count($s['escolas'] ?? []),
            'projetos' => count($s['projetos']), 'linhas' => count($s['linhas_tematicas'] ?? []), 'cronograma' => $n_crono, 'formulario' => $qid];
    }

    /** Formulário de 10 perguntas (questionário simples). As opções das perguntas 1 e 4 dependem do público. */
    public function criar_formulario($por) {
        $ex = $this->formulario();
        if ($ex) {
            return (int) $ex->id;
        }
        $this->db->insert('questionnaires', ['title' => self::TITULO_FORMULARIO, 'status' => 'active', 'formato' => 'simples',
            'description' => 'Formulário anônimo aplicado ao final de cada atividade do PEA (cerca de 3 minutos). Não pede nome nem CPF.',
            'estimated_time' => 3, 'created_by' => $por ?: NULL, 'aplicadores' => NULL]);
        $qid = (int) $this->db->insert_id();
        $escala = [['muito_insatisfeito', 'Muito insatisfeito(a)'], ['insatisfeito', 'Insatisfeito(a)'], ['indiferente', 'Indiferente'], ['satisfeito', 'Satisfeito(a)'], ['muito_satisfeito', 'Muito satisfeito(a)']];
        $perguntas = [
            ['Você participou desta atividade como:', 'radio', TRUE, [
                ['int_colaborador_gerdau', 'Colaborador(a) Gerdau'], ['int_colaborador_parceiro', 'Colaborador(a) de empresa parceira'], ['int_lideranca', 'Gestor(a) / liderança'],
                ['ext_morador', 'Morador(a)'], ['ext_lideranca', 'Liderança ou representante comunitário(a)'], ['ext_outro', 'Outro'],
                ['esc_estudante', 'Estudante'], ['esc_professor', 'Professor(a) ou profissional da escola'], ['esc_outro', 'Outro']]],
            ['Como você avalia o conteúdo / tema da atividade?', 'radio', TRUE, $escala],
            ['Como você avalia a forma como a atividade foi conduzida (linguagem, dinâmicas, materiais)?', 'radio', TRUE, $escala],
            ['O tema tem relação com o seu dia a dia{apoio}?', 'radio', TRUE, $escala],
            ['Como você avalia a organização (local, horário e duração)?', 'radio', TRUE, $escala],
            ['De forma geral, qual é o seu nível de satisfação com esta atividade?', 'radio', TRUE, $escala],
            ['Sobre o tema desta atividade, qual frase descreve melhor você?', 'radio', TRUE, [
                ['a', '(a) Já conhecia o tema e ampliei meu conhecimento'], ['b', '(b) Já conhecia o tema e não aprendi nada novo'],
                ['c', '(c) Não conhecia o tema e agora conheço'], ['d', '(d) Mesmo após a atividade, continuo sem conhecer o tema']]],
            ['Em poucas palavras, o que você aprendeu ou vai levar desta atividade?', 'textarea', FALSE, []],
            ['Você pretende colocar em prática algo do que viu hoje?', 'radio', TRUE, [
                ['trabalho', 'Sim, no trabalho'], ['casa_comunidade', 'Sim, em casa ou na comunidade'], ['ambos', 'Sim, nos dois'], ['talvez', 'Talvez'], ['nao', 'Não']]],
            ['Que tema ou melhoria você sugere para as próximas atividades?', 'textarea', FALSE, []],
        ];
        foreach ($perguntas as $i => $p) {
            $this->db->insert('questions', ['questionnaire_id' => $qid, 'question_text' => $p[0], 'question_type' => $p[1], 'is_required' => $p[2], 'order_index' => $i + 1]);
            $pq = (int) $this->db->insert_id();
            foreach ($p[3] as $j => $o) {
                $this->db->insert('question_options', ['question_id' => $pq, 'option_value' => $o[0], 'option_text' => $o[1], 'order_index' => $j + 1]);
            }
        }
        return $qid;
    }

    public function formulario() {
        return $this->db->order_by('id')->get_where('questionnaires', ['title' => self::TITULO_FORMULARIO], 1)->row();
    }

    /**
     * Ajusta as perguntas do formulário ao público da ação (link público):
     * opções das perguntas 1 e 4 e o texto de apoio. Usado pelo Responder.
     */
    public static function adaptar_ao_publico(array $perguntas, $publico) {
        $pref = ['interno' => 'int_', 'externo_comunidade' => 'ext_', 'externo_escolar' => 'esc_'][$publico] ?? NULL;
        $apoio = ['interno' => ' (no seu trabalho e em casa)', 'externo_comunidade' => ' (na sua comunidade)', 'externo_escolar' => ' (na escola, em casa e na comunidade)'][$publico] ?? '';
        foreach ($perguntas as $q) {
            $q->question_text = str_replace('{apoio}', $apoio, $q->question_text);
            if ($pref && !empty($q->options)) {
                $marcadas = array_filter($q->options, function ($o) { return preg_match('/^(int|ext|esc)_/', (string) $o->option_value); });
                if ($marcadas) {
                    $q->options = array_values(array_filter($q->options, function ($o) use ($pref) { return strpos((string) $o->option_value, $pref) === 0; }));
                }
            }
        }
        return $perguntas;
    }

    // ------------------------------------------------------------
    // Estrutura
    // ------------------------------------------------------------

    public function unidades() { return $this->db->order_by('id')->get('pea_unidades')->result(); }
    public function eixos() { return $this->db->order_by('id')->get('pea_eixos')->result(); }

    public function comunidades() {
        return $this->db->select('c.*, u.sigla')->from('pea_comunidades c')->join('pea_unidades u', 'u.id = c.unidade_id', 'left')
            ->order_by('u.sigla')->order_by('c.nome')->get()->result();
    }

    public function escolas() {
        return $this->db->select('e.*, u.sigla, c.nome AS comunidade')->from('pea_escolas e')->join('pea_unidades u', 'u.id = e.unidade_id', 'left')
            ->join('pea_comunidades c', 'c.id = e.comunidade_id', 'left')->order_by('u.sigla')->order_by('e.nome')->get()->result();
    }

    public function linhas($ciclo, $publico = NULL) {
        if ($publico) { $this->db->where('publico', $publico); }
        return $this->db->order_by('publico')->order_by('id')->get_where('pea_linhas', ['ciclo_id' => (int) $ciclo])->result();
    }

    public function projetos($ciclo, $publico = NULL) {
        $this->db->select('p.*, e.nome AS eixo')->from('pea_projetos p')->join('pea_eixos e', 'e.id = p.eixo_id', 'left')->where('p.ciclo_id', (int) $ciclo);
        if ($publico) { $this->db->where('p.publico', $publico); }
        return $this->db->order_by('p.ordem')->get()->result();
    }

    public function projeto($id) {
        return $this->db->get_where('pea_projetos', ['id' => (int) $id])->row();
    }

    public function salvar_projeto($id, array $d) {
        return $this->db->where('id', (int) $id)->update('pea_projetos', $d);
    }

    /** cronograma[projeto_id][ano][trimestre] = previstas */
    public function cronograma($ciclo) {
        $r = [];
        $q = $this->db->query('SELECT c.* FROM pea_cronograma c JOIN pea_projetos p ON p.id = c.projeto_id WHERE p.ciclo_id = ?', [(int) $ciclo])->result();
        foreach ($q as $c) {
            $r[(int) $c->projeto_id][(int) $c->ano][(int) $c->trimestre] = (int) $c->previstas;
        }
        return $r;
    }

    public function salvar_cronograma($projeto_id, $ano, $tri, $previstas) {
        $this->db->query('INSERT INTO pea_cronograma (projeto_id, ano, trimestre, previstas) VALUES (?, ?, ?, ?)
                          ON CONFLICT (projeto_id, ano, trimestre) DO UPDATE SET previstas = EXCLUDED.previstas',
            [(int) $projeto_id, (int) $ano, (int) $tri, max(0, (int) $previstas)]);
    }

    // ------------------------------------------------------------
    // Ações
    // ------------------------------------------------------------

    public function acoes(array $f = []) {
        $this->db->select("a.*, p.nome AS projeto, p.publico, p.ip_faixas, p.avaliacao, u.sigla AS unidade, c.nome AS comunidade, l.token,
                           (SELECT COUNT(*) FROM form_responses fr WHERE fr.public_link_id = a.public_link_id AND a.public_link_id IS NOT NULL) AS respostas", FALSE)
            ->from('pea_acoes a')->join('pea_projetos p', 'p.id = a.projeto_id')
            ->join('pea_unidades u', 'u.id = a.unidade_id', 'left')->join('pea_comunidades c', 'c.id = a.comunidade_id', 'left')
            ->join('questionnaire_public_links l', 'l.id = a.public_link_id', 'left');
        foreach (['projeto_id' => 'a.projeto_id', 'unidade_id' => 'a.unidade_id', 'comunidade_id' => 'a.comunidade_id', 'ano' => 'a.ano_previsto', 'trimestre' => 'a.trimestre_previsto', 'status' => 'a.status'] as $k => $col) {
            if (!empty($f[$k])) { $this->db->where($col, $f[$k]); }
        }
        if (!empty($f['publico'])) { $this->db->where('p.publico', $f['publico']); }
        if (!empty($f['ciclo'])) { $this->db->where('p.ciclo_id', (int) $f['ciclo']); }
        return $this->db->order_by('a.data_prevista', 'DESC')->order_by('a.id', 'DESC')->get()->result();
    }

    public function acao($id) {
        $r = $this->db->select("a.*, p.nome AS projeto, p.publico, p.ip_faixas, p.avaliacao, p.ciclo_id, u.sigla AS unidade, u.nome AS unidade_nome,
                                c.nome AS comunidade, e.nome AS escola, ln.linha, l.token, l.is_active AS link_ativo", FALSE)
            ->from('pea_acoes a')->join('pea_projetos p', 'p.id = a.projeto_id')
            ->join('pea_unidades u', 'u.id = a.unidade_id', 'left')->join('pea_comunidades c', 'c.id = a.comunidade_id', 'left')
            ->join('pea_escolas e', 'e.id = a.escola_id', 'left')->join('pea_linhas ln', 'ln.id = a.linha_id', 'left')
            ->join('questionnaire_public_links l', 'l.id = a.public_link_id', 'left')
            ->where('a.id', (int) $id)->get()->row();
        return $r;
    }

    public function salvar_acao($id, array $d, $ciclo) {
        if (!empty($d['data_prevista']) && $ciclo) {
            $p = Pea_motor::periodo($d['data_prevista'], $ciclo->inicio, (int) $ciclo->mes_inicio);
            if ($p) { list($d['ano_previsto'], $d['trimestre_previsto']) = $p; }
        }
        if ($id) {
            $this->db->where('id', (int) $id)->update('pea_acoes', $d);
            return (int) $id;
        }
        $this->db->insert('pea_acoes', $d);
        return (int) $this->db->insert_id();
    }

    /** Cria (uma vez) o link público da ação: o QR Code leva ao formulário já ligado à ação. */
    public function link_da_acao($acao, $por) {
        if (!empty($acao->public_link_id)) {
            return (int) $acao->public_link_id;
        }
        $f = $this->formulario();
        if (!$f) {
            throw new RuntimeException('Formulário de avaliação do PEA não encontrado: carregue o programa.');
        }
        $this->load->model('Public_link_model');
        $lid = (int) $this->Public_link_model->create($f->id, 'PEA: ' . mb_substr($acao->titulo, 0, 180), NULL, $por);
        $this->db->where('id', (int) $acao->id)->update('pea_acoes', ['public_link_id' => $lid]);
        return $lid;
    }

    /** Ação dona do link (formulário público adapta as perguntas ao público dela). */
    public function acao_do_link($link_id) {
        if (!$this->disponivel()) { return NULL; }
        return $this->db->select('a.id, p.publico')->from('pea_acoes a')->join('pea_projetos p', 'p.id = a.projeto_id')
            ->where('a.public_link_id', (int) $link_id)->get()->row();
    }

    /** Respostas do app (preenchimento assistido) feitas no dia da ação e ainda sem ação: liga à ação. */
    public function respostas_do_app_sem_acao($acao) {
        $f = $this->formulario();
        if (!$f || empty($acao->data_realizacao)) { return []; }
        return $this->db->query("SELECT fr.id, fr.completed_at, u.full_name AS aplicador FROM form_responses fr LEFT JOIN users u ON u.id = fr.applied_by
                                  WHERE fr.questionnaire_id = ? AND fr.public_link_id IS NULL AND fr.completed_at::date = ?::date ORDER BY fr.completed_at",
            [(int) $f->id, $acao->data_realizacao])->result();
    }

    public function vincular_respostas($acao, array $ids) {
        if (!$ids || empty($acao->public_link_id)) { return 0; }
        $f = $this->formulario();
        $this->db->where_in('id', array_map('intval', $ids))->where('questionnaire_id', (int) $f->id)->where('public_link_id IS NULL', NULL, FALSE)
            ->update('form_responses', ['public_link_id' => (int) $acao->public_link_id]);
        return $this->db->affected_rows();
    }

    /**
     * Respostas do formulário da ação, por ordem da pergunta:
     * ['contagens' => [ordem => [valor => n]], 'abertas' => [ordem => [textos]], 'respondentes' => n]
     */
    public function respostas_da_acao($acao) {
        $out = ['contagens' => [], 'abertas' => [], 'respondentes' => 0];
        if (empty($acao->public_link_id)) { return $out; }
        $out['respondentes'] = (int) $this->db->where('public_link_id', (int) $acao->public_link_id)->count_all_results('form_responses');
        $linhas = $this->db->query('SELECT q.order_index AS ordem, q.question_type AS tipo, r.selected_options, r.response_text
                                      FROM question_responses r JOIN form_responses fr ON fr.id = r.form_response_id
                                      JOIN questions q ON q.id = r.question_id WHERE fr.public_link_id = ?', [(int) $acao->public_link_id])->result();
        foreach ($linhas as $l) {
            $o = (int) $l->ordem;
            $vals = [];
            if ($l->selected_options !== NULL && $l->selected_options !== '' && $l->selected_options !== '[]') {
                $d = json_decode($l->selected_options, TRUE);
                $vals = is_array($d) ? $d : [$l->selected_options];
            } elseif (trim((string) $l->response_text) !== '') {
                if (in_array($l->tipo, ['text', 'textarea'], TRUE)) {
                    $out['abertas'][$o][] = trim($l->response_text);
                    continue;
                }
                $vals = [trim($l->response_text)];
            }
            foreach ($vals as $v) {
                $out['contagens'][$o][(string) $v] = ($out['contagens'][$o][(string) $v] ?? 0) + 1;
            }
        }
        return $out;
    }

    /** Indicadores de uma ação. */
    public function indicadores_acao($acao, $regras = NULL, $resp = NULL) {
        if ($regras === NULL) { list(, $regras) = $this->regras(); }
        $ciclo = $this->db->get_where('pea_ciclos', ['id' => (int) $acao->ciclo_id])->row();
        $resp = $resp ?: $this->respostas_da_acao($acao);
        $pq = $regras['perguntas'];
        $realizada = in_array($acao->status, ['realizada', 'encerrada'], TRUE) && !empty($acao->data_realizacao);
        $previsto = $acao->ano_previsto ? [(int) $acao->ano_previsto, (int) $acao->trimestre_previsto] : NULL;
        $real = $realizada && $ciclo ? Pea_motor::periodo($acao->data_realizacao, $ciclo->inicio, (int) $ciclo->mes_inicio) : NULL;
        $no_prazo = $realizada && Pea_motor::no_prazo($previsto, $real, $regras);
        $avaliacao = in_array($acao->avaliacao, [TRUE, 't', 1, '1'], TRUE);
        $itens = [];
        foreach ($pq['is_itens'] as $o) { $itens[$o] = $resp['contagens'][$o] ?? []; }
        $ind = [
            'icc' => $previsto ? ($no_prazo ? 100.0 : 0.0) : NULL,
            'ip' => Pea_motor::ip($acao->participantes, json_decode((string) $acao->ip_faixas, TRUE), $realizada, $regras),
            'is' => $avaliacao && $realizada ? Pea_motor::is($resp['contagens'][$pq['is']] ?? [], $acao->participantes, $regras) : NULL,
            'iia' => $avaliacao && $realizada ? Pea_motor::iia($resp['contagens'][$pq['iia']] ?? [], $regras) : NULL,
        ];
        $ind['ie'] = Pea_motor::ie($ind, $regras);
        return $ind + [
            'no_prazo' => $no_prazo, 'realizada' => $realizada, 'periodo_realizado' => $real,
            'participantes' => (int) $acao->participantes, 'respondentes' => $resp['respondentes'],
            'adesao' => (int) $acao->participantes > 0 ? round($resp['respondentes'] / $acao->participantes * 100, 1) : NULL,
            'is_itens' => Pea_motor::is_itens($itens, $regras),
            'perfil' => $resp['contagens'][$pq['perfil']] ?? [], 'pratica' => $resp['contagens'][$pq['pratica']] ?? [],
            'iia_respostas' => $resp['contagens'][$pq['iia']] ?? [], 'is_respostas' => $resp['contagens'][$pq['is']] ?? [],
            'abertas' => ['aprendizado' => $resp['abertas'][$pq['aprendizado']] ?? [], 'sugestao' => $resp['abertas'][$pq['sugestao']] ?? []],
        ];
    }

    /**
     * Indicadores agregados por projeto (e o total) num recorte.
     * @param array $f ciclo (obj), ano, trimestre, publico, unidade_id, comunidade_id, projeto_id
     */
    public function painel(array $f) {
        list($versao, $regras) = $this->regras();
        $ciclo = $f['ciclo'];
        $projetos = $this->projetos($ciclo->id, $f['publico'] ?? NULL);
        if (!empty($f['projeto_id'])) {
            $projetos = array_values(array_filter($projetos, function ($p) use ($f) { return (int) $p->id === (int) $f['projeto_id']; }));
        }
        $crono = $this->cronograma($ciclo->id);
        $acoes = $this->acoes(['ciclo' => $ciclo->id, 'publico' => $f['publico'] ?? NULL, 'unidade_id' => $f['unidade_id'] ?? NULL,
            'comunidade_id' => $f['comunidade_id'] ?? NULL, 'projeto_id' => $f['projeto_id'] ?? NULL]);
        $ano = !empty($f['ano']) ? (int) $f['ano'] : NULL;
        $tris = !empty($f['trimestres']) ? array_map('intval', (array) $f['trimestres']) : (!empty($f['trimestre']) ? [(int) $f['trimestre']] : NULL);
        $hoje = Pea_motor::periodo(date('Y-m-d'), $ciclo->inicio, (int) $ciclo->mes_inicio);
        $por_proj = [];
        $todas = [];
        $atrasadas = [];
        $trimestral = [];
        foreach ($projetos as $p) {
            $por_proj[(int) $p->id] = ['projeto' => $p, 'acoes' => [], 'prev_crono' => 0, 'prev_fichas' => 0, 'no_prazo' => 0, 'realizadas' => 0];
            foreach ($crono[(int) $p->id] ?? [] as $a => $por_tri) {
                foreach ($por_tri as $t => $n) {
                    $trimestral[$a][$t]['previstas'] = ($trimestral[$a][$t]['previstas'] ?? 0) + $n;
                    if (($ano === NULL || $a === $ano) && ($tris === NULL || in_array($t, $tris, TRUE))) {
                        $por_proj[(int) $p->id]['prev_crono'] += $n;
                    }
                }
            }
        }
        foreach ($acoes as $a) {
            $pid = (int) $a->projeto_id;
            if (!isset($por_proj[$pid])) { continue; }
            $a->ciclo_id = $ciclo->id;
            $ind = $this->indicadores_acao($a, $regras);
            if ($ind['realizada'] && $ind['periodo_realizado']) {
                list($ra, $rt) = $ind['periodo_realizado'];
                $trimestral[$ra][$rt]['realizadas'] = ($trimestral[$ra][$rt]['realizadas'] ?? 0) + 1;
            }
            $na_janela = ($ano === NULL || (int) $a->ano_previsto === $ano) && ($tris === NULL || in_array((int) $a->trimestre_previsto, $tris, TRUE));
            if (!$na_janela) { continue; }
            $por_proj[$pid]['acoes'][] = ['acao' => $a, 'ind' => $ind];
            $por_proj[$pid]['prev_fichas']++;
            if ($ind['no_prazo']) { $por_proj[$pid]['no_prazo']++; }
            if ($ind['realizada']) { $por_proj[$pid]['realizadas']++; }
            $todas[] = $ind;
            if (!$ind['realizada'] && $a->status !== 'cancelada' && $hoje && $a->ano_previsto
                && ((int) $a->ano_previsto * 4 + (int) $a->trimestre_previsto) < ($hoje[0] * 4 + $hoje[1])) {
                $atrasadas[] = $a;
            }
        }
        $linhas = [];
        $comp = ['icc' => [], 'ip' => [], 'is' => [], 'iia' => [], 'ie' => []];
        foreach ($por_proj as $pid => $x) {
            $ip = Pea_motor::media(array_column(array_column($x['acoes'], 'ind'), 'ip'));
            $is = Pea_motor::media(array_column(array_column($x['acoes'], 'ind'), 'is'));
            $iia = Pea_motor::media(array_column(array_column($x['acoes'], 'ind'), 'iia'));
            $icc = Pea_motor::icc($x['prev_crono'], $x['prev_fichas'], $x['no_prazo']);
            $ie = Pea_motor::ie(['icc' => $icc, 'ip' => $ip, 'is' => $is, 'iia' => $iia], $regras);
            $linhas[] = ['projeto' => $x['projeto'], 'previstas' => max($x['prev_crono'], $x['prev_fichas']), 'realizadas' => $x['realizadas'], 'no_prazo' => $x['no_prazo'],
                'icc' => $icc, 'ip' => $ip, 'is' => $is, 'iia' => $iia, 'ie' => $ie];
            foreach (['icc', 'ip', 'is', 'iia', 'ie'] as $k) { $comp[$k][] = ${$k}; }
        }
        $tot = [];
        foreach ($comp as $k => $v) { $tot[$k] = Pea_motor::media($v); }
        // Satisfação por item, perfil, prática e abertas no recorte
        $itens = [];
        $pratica = [];
        $perfil = [];
        $abertas = ['aprendizado' => [], 'sugestao' => []];
        $resp = 0;
        $part = 0;
        foreach ($por_proj as $x) {
            foreach ($x['acoes'] as $ac) {
                $i = $ac['ind'];
                foreach ($i['is_itens'] as $q => $v) { if ($v !== NULL) { $itens[$q][] = $v; } }
                foreach ($i['pratica'] as $k => $n) { $pratica[$k] = ($pratica[$k] ?? 0) + $n; }
                foreach ($i['perfil'] as $k => $n) { $perfil[$k] = ($perfil[$k] ?? 0) + $n; }
                foreach ($i['abertas'] as $k => $l) { foreach ($l as $t) { $abertas[$k][] = ['texto' => $t, 'projeto' => $x['projeto']->nome]; } }
                $resp += $i['respondentes'];
                $part += $i['participantes'];
            }
        }
        foreach ($itens as $q => $v) { $itens[$q] = Pea_motor::media($v); }
        ksort($trimestral);
        return ['versao_regras' => $versao, 'regras' => $regras, 'projetos' => $linhas, 'total' => $tot, 'atrasadas' => $atrasadas,
            'trimestral' => $trimestral, 'is_itens' => $itens, 'pratica' => $pratica, 'perfil' => $perfil, 'abertas' => $abertas,
            'respondentes' => $resp, 'participantes' => $part, 'n_acoes' => count($todas)];
    }

    // ------------------------------------------------------------
    // Evidências e presença
    // ------------------------------------------------------------

    public function evidencias($acao_id) {
        return $this->db->order_by('id')->get_where('pea_evidencias', ['acao_id' => (int) $acao_id])->result();
    }

    public function presencas($acao_id) {
        return $this->db->order_by('nome')->get_where('pea_presencas', ['acao_id' => (int) $acao_id])->result();
    }

    public function adicionar_presenca($acao_id, array $d) {
        $cpf = preg_replace('/\D/', '', (string) ($d['cpf'] ?? ''));
        $col = NULL;
        if ($cpf !== '' || !empty($d['matricula'])) {
            $this->db->group_start();
            if ($cpf !== '') { $this->db->where('cpf', $cpf); }
            if (!empty($d['matricula'])) { $this->db->or_where('matricula', $d['matricula']); }
            $this->db->group_end();
            $col = $this->db->get('pea_colaboradores', 1)->row();
        }
        if ($col) {
            $emp = $col->empresa_id ? $this->db->get_where('pea_empresas', ['id' => $col->empresa_id])->row() : NULL;
            $d = ['nome' => $col->nome, 'cpf' => $col->cpf, 'matricula' => $col->matricula, 'empresa' => $emp ? $emp->nome : ($d['empresa'] ?? NULL)];
        }
        if (empty($d['nome'])) {
            throw new InvalidArgumentException('Informe o nome, ou um CPF/matrícula da base do BancoDOC.');
        }
        $dup = $this->db->where('acao_id', (int) $acao_id)->group_start()->where('nome', $d['nome']);
        if ($cpf !== '') { $dup->or_where('cpf', $col ? $col->cpf : $cpf); }
        $dup->group_end();
        if ($this->db->get('pea_presencas', 1)->row()) {
            throw new InvalidArgumentException('Essa pessoa já está na lista de presença.');
        }
        $this->db->insert('pea_presencas', ['acao_id' => (int) $acao_id, 'colaborador_id' => $col ? (int) $col->id : NULL, 'nome' => mb_substr($d['nome'], 0, 200),
            'cpf' => $col ? $col->cpf : ($cpf ?: NULL), 'matricula' => $col ? $col->matricula : (($d['matricula'] ?? NULL) ?: NULL),
            'empresa' => $d['empresa'] ?? NULL, 'origem' => $col ? 'bancodoc' : 'manual']);
        return $col !== NULL;
    }

    // ------------------------------------------------------------
    // EIEAP
    // ------------------------------------------------------------

    public function eieap() {
        $nos = $this->db->order_by('ordem')->get('pea_eieap_nos')->result_array();
        $vals = [];
        foreach ($this->db->get('pea_eieap_valores')->result() as $v) {
            $vals[$v->unidade][$v->tipo][$v->ano][$v->no] = $v->valor !== NULL ? (float) $v->valor : NULL;
        }
        // Para cada unidade: último valor "atual", base e meta, e a árvore calculada sobre o atual
        $unidades = array_unique(array_merge(['UOB', 'MVL', 'MMB', 'Gerdau'], array_keys($vals)));
        $r = [];
        foreach ($unidades as $u) {
            $ultimo = function ($tipo) use ($vals, $u) {
                $anos = $vals[$u][$tipo] ?? [];
                krsort($anos);
                $o = [];
                foreach ($anos as $ano => $por_no) { foreach ($por_no as $no => $v) { if (!isset($o[$no])) { $o[$no] = ['valor' => $v, 'ano' => $ano]; } } }
                return $o;
            };
            $atual = $ultimo('atual');
            $calc = Pea_motor::arvore($nos, array_map(function ($x) { return $x['valor']; }, $atual));
            $r[$u] = ['base' => $ultimo('base'), 'meta' => $ultimo('meta'), 'atual' => $atual, 'calculado' => $calc];
        }
        return ['nos' => $nos, 'unidades' => $r];
    }

    public function salvar_eieap_valor($no, $unidade, $tipo, $ano, $valor) {
        if ($valor === '' || $valor === NULL) {
            $this->db->where(['no' => $no, 'unidade' => $unidade, 'tipo' => $tipo, 'ano' => (int) $ano])->delete('pea_eieap_valores');
            return;
        }
        $this->db->query('INSERT INTO pea_eieap_valores (no, unidade, tipo, ano, valor) VALUES (?, ?, ?, ?, ?)
                          ON CONFLICT (no, unidade, tipo, ano) DO UPDATE SET valor = EXCLUDED.valor', [$no, $unidade, $tipo, (int) $ano, (float) str_replace(',', '.', $valor)]);
    }

    public function salvar_no($codigo, $nome, $pai, $peso) {
        $codigo = $this->slug($codigo ?: $nome);
        $ex = $this->db->get_where('pea_eieap_nos', ['codigo' => $codigo])->row();
        $d = ['nome' => mb_substr($nome, 0, 200), 'pai' => $pai ?: NULL, 'peso' => max(0, (float) str_replace(',', '.', $peso))];
        if ($ex) {
            $this->db->where('id', $ex->id)->update('pea_eieap_nos', $d);
        } else {
            $this->db->insert('pea_eieap_nos', $d + ['codigo' => $codigo, 'ordem' => 100]);
        }
        return $codigo;
    }

    // ------------------------------------------------------------
    // Relatórios
    // ------------------------------------------------------------

    public function relatorios() {
        return $this->db->select('r.id, r.tipo, r.ano, r.semestre, r.titulo, r.status, r.versao, r.criado_em, r.aprovado_em, u.full_name AS autor, a.full_name AS aprovador')
            ->from('pea_relatorios r')->join('users u', 'u.id = r.criado_por', 'left')->join('users a', 'a.id = r.aprovado_por', 'left')
            ->order_by('r.id', 'DESC')->get()->result();
    }

    public function relatorio($id) {
        $r = $this->db->get_where('pea_relatorios', ['id' => (int) $id])->row();
        if ($r) {
            $r->dados = json_decode($r->dados, TRUE);
            $r->textos = json_decode((string) $r->textos, TRUE) ?: [];
            $r->eventos = $this->db->select('e.*, u.full_name')->from('pea_relatorio_eventos e')->join('users u', 'u.id = e.user_id', 'left')
                ->where('e.relatorio_id', $r->id)->order_by('e.id')->get()->result();
        }
        return $r;
    }

    public function evento($rid, $evento, $por, $comentario = NULL) {
        $this->db->insert('pea_relatorio_eventos', ['relatorio_id' => (int) $rid, 'user_id' => $por ?: NULL, 'evento' => $evento, 'comentario' => $comentario ?: NULL]);
    }

    private function slug($s) {
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower((string) $s));
        return trim(preg_replace('/[^a-z0-9]+/', '_', $s), '_') ?: 'no';
    }
}
