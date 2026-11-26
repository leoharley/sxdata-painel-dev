<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Cripto_chaves.php';
require_once APPPATH . 'libraries/Ia_provedores.php';

/**
 * Frente K (sql/ia_provedores_migration.sql): chaves cifradas, tabela de
 * preços, limite mensal de gasto e IA desligada por projeto.
 */
class Ia_model extends CI_Model {

    private static $cache_chaves = [];

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    public function disponivel() {
        static $ok = NULL;
        if ($ok === NULL) {
            $ok = $this->db->table_exists('ia_modelos') && $this->db->table_exists('ia_chaves');
        }
        return $ok;
    }

    // ---------------- Chaves ----------------

    /** Chave do provedor: a cadastrada na tela (cifrada) ou a do .env. */
    public function chave($provedor) {
        if (array_key_exists($provedor, self::$cache_chaves)) {
            return self::$cache_chaves[$provedor];
        }
        $chave = '';
        if ($this->disponivel() && Cripto_chaves::disponivel()) {
            $l = $this->db->get_where('ia_chaves', ['provedor' => $provedor])->row();
            if ($l) {
                try {
                    $chave = (string) Cripto_chaves::decifrar($l->chave_cifrada);
                } catch (Throwable $e) {
                    $chave = '';
                }
            }
        }
        if ($chave === '') {
            $env = $provedor === 'anthropic' ? 'ANTHROPIC_API_KEY' : 'OPENAI_API_KEY';
            $v = getenv($env);
            $chave = is_string($v) && $v !== '' ? $v : (string) ($_ENV[$env] ?? '');
        }
        return self::$cache_chaves[$provedor] = $chave;
    }

    /** Situação das chaves para a tela (nunca a chave em si). */
    public function situacao_chaves() {
        $r = [];
        foreach (['openai', 'anthropic'] as $p) {
            $l = $this->disponivel() ? $this->db->get_where('ia_chaves', ['provedor' => $p])->row() : NULL;
            $env = getenv($p === 'anthropic' ? 'ANTHROPIC_API_KEY' : 'OPENAI_API_KEY');
            $legivel = $l ? $this->chave_legivel($l) : FALSE;
            $r[$p] = [
                'origem' => $l ? ($legivel ? 'tela' : 'ilegivel') : ((is_string($env) && $env !== '') ? 'env' : NULL),
                'final' => $l ? $l->final : (is_string($env) && $env !== '' ? Ia_provedores::mascara($env) : NULL),
                'atualizado_em' => $l ? $l->atualizado_em : NULL,
            ];
        }
        return $r;
    }

    private function chave_legivel($l) {
        try {
            return Cripto_chaves::decifrar($l->chave_cifrada) !== NULL;
        } catch (Throwable $e) {
            return FALSE;
        }
    }

    public function salvar_chave($provedor, $chave, $por) {
        $chave = trim((string) $chave);
        $dados = ['chave_cifrada' => Cripto_chaves::cifrar($chave), 'final' => Ia_provedores::mascara($chave),
            'atualizado_por' => $por ? (int) $por : NULL, 'atualizado_em' => date('Y-m-d H:i:s')];
        if ($this->db->get_where('ia_chaves', ['provedor' => $provedor])->row()) {
            $this->db->where('provedor', $provedor)->update('ia_chaves', $dados);
        } else {
            $this->db->insert('ia_chaves', $dados + ['provedor' => $provedor]);
        }
        unset(self::$cache_chaves[$provedor]);
    }

    public function remover_chave($provedor) {
        $this->db->where('provedor', $provedor)->delete('ia_chaves');
        unset(self::$cache_chaves[$provedor]);
    }

    // ---------------- Preços ----------------

    public function modelos($so_ativos = FALSE) {
        if (!$this->disponivel()) {
            return [];
        }
        if ($so_ativos) {
            $this->db->where('ativo', TRUE);
        }
        return $this->db->order_by('provedor')->order_by('preco_entrada', 'DESC')->get('ia_modelos')->result();
    }

    public function preco($modelo) {
        static $cache = [];
        if (!array_key_exists($modelo, $cache)) {
            $l = $this->disponivel() ? $this->db->get_where('ia_modelos', ['modelo' => $modelo])->row() : NULL;
            $cache[$modelo] = $l ? ['entrada' => (float) $l->preco_entrada, 'saida' => (float) $l->preco_saida] : NULL;
        }
        return $cache[$modelo];
    }

    public function salvar_preco($id, $entrada, $saida, $ativo) {
        return $this->db->where('id', (int) $id)->update('ia_modelos', [
            'preco_entrada' => max(0, (float) $entrada), 'preco_saida' => max(0, (float) $saida),
            'ativo' => (bool) $ativo, 'atualizado_em' => date('Y-m-d H:i:s'),
        ]);
    }

    // ---------------- Limite mensal ----------------

    public function config() {
        $l = function ($k, $padrao) {
            $r = $this->db->get_where('system_settings', ['setting_key' => $k])->row();
            return $r && $r->setting_value !== '' ? $r->setting_value : $padrao;
        };
        return [
            'limite' => (float) $l('ia_limite_mensal_usd', 0),   // 0 = sem limite
            'alerta' => (int) $l('ia_alerta_pct', 80),
            'anonimizar' => (string) $l('ia_anonimizar', '1') !== '0',
        ];
    }

    public function gasto_do_mes() {
        $r = $this->db->query("SELECT COALESCE(SUM(cost_usd), 0) AS total, COUNT(*) AS n FROM ai_execution_logs
                               WHERE created_at >= date_trunc('month', CURRENT_DATE) AND status = 'success'")->row();
        return ['total' => (float) $r->total, 'n' => (int) $r->n];
    }

    public function gasto_por_modelo_no_mes() {
        $col = $this->db->field_exists('provider', 'ai_execution_logs') ? "COALESCE(provider, '')" : "''";
        return $this->db->query("SELECT $col AS provedor, model_used, COUNT(*) AS n, SUM(tokens_input) AS ent, SUM(tokens_output) AS sai, SUM(cost_usd) AS custo
                                 FROM ai_execution_logs WHERE created_at >= date_trunc('month', CURRENT_DATE) AND status = 'success'
                                 GROUP BY 1, 2 ORDER BY custo DESC")->result();
    }

    // ---------------- Projetos ----------------

    /** Projeto de onde vem o pedido (pelo recurso que o chamador informou). */
    public function projeto_do_recurso($tipo, $id) {
        $id = (int) $id;
        if ($id <= 0 || !$tipo) {
            return NULL;
        }
        switch ($tipo) {
            case 'project':
                return $id;
            case 'questionnaire':
            case 'chart_description':
            case 'statistical_analysis':
                $q = $this->db->select('project_id')->get_where('questionnaires', ['id' => $id])->row();
                return $q && $q->project_id ? (int) $q->project_id : NULL;
            case 'form_response':
            case 'transcription':
                $q = $this->db->query('SELECT q.project_id FROM form_responses fr JOIN questionnaires q ON q.id = fr.questionnaire_id WHERE fr.id = ?', [$id])->row();
                return $q && $q->project_id ? (int) $q->project_id : NULL;
            case 'question':
                $q = $this->db->query('SELECT q.project_id FROM questions p JOIN questionnaires q ON q.id = p.questionnaire_id WHERE p.id = ?', [$id])->row();
                return $q && $q->project_id ? (int) $q->project_id : NULL;
        }
        return NULL;
    }

    /**
     * Nomes de pessoas ligados ao recurso (entrevistado, moradores,
     * entrevistado pré-cadastrado), para o Anonimizador retirar do texto.
     */
    public function nomes_do_recurso($tipo, $id) {
        $id = (int) $id;
        if ($id <= 0) {
            return [];
        }
        $nomes = [];
        $add = function ($sql, $p) use (&$nomes) {
            $r = $this->db->query($sql, $p);
            if ($r) {
                foreach ($r->result_array() as $l) {
                    if (!empty($l['n'])) { $nomes[] = $l['n']; }
                }
            }
        };
        $debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        if (in_array($tipo, ['form_response', 'transcription'], TRUE)) {
            $add('SELECT respondent_name AS n FROM form_responses WHERE id = ?', [$id]);
            if ($this->db->table_exists('moradores')) {
                $add('SELECT nome AS n FROM moradores WHERE form_response_id = ?', [$id]);
            }
            if ($this->db->table_exists('entrevistados')) {
                $add('SELECT e.nome AS n FROM form_responses fr JOIN entrevistados e ON e.id = fr.entrevistado_id WHERE fr.id = ?', [$id]);
            }
        } elseif (in_array($tipo, ['questionnaire', 'chart_description', 'statistical_analysis'], TRUE)) {
            $add("SELECT DISTINCT respondent_name AS n FROM form_responses WHERE questionnaire_id = ? AND COALESCE(respondent_name, '') <> '' LIMIT 500", [$id]);
            if ($this->db->table_exists('moradores')) {
                $add('SELECT DISTINCT m.nome AS n FROM moradores m JOIN form_responses fr ON fr.id = m.form_response_id WHERE fr.questionnaire_id = ? LIMIT 1000', [$id]);
            }
        }
        $this->db->db_debug = $debug;
        return array_values(array_unique($nomes));
    }

    public function ia_desligada($project_id) {
        if (!$project_id || !$this->db->field_exists('ia_desligada', 'projects')) {
            return FALSE;
        }
        $p = $this->db->select('ia_desligada')->get_where('projects', ['id' => (int) $project_id])->row();
        return $p && in_array($p->ia_desligada, [TRUE, 't', 1, '1'], TRUE);
    }

    public function projetos() {
        $col = $this->db->field_exists('ia_desligada', 'projects') ? 'ia_desligada' : 'FALSE AS ia_desligada';
        return $this->db->select("id, name, status, $col", FALSE)->order_by('name')->get('projects')->result();
    }

    public function definir_ia_projeto($project_id, $desligada) {
        return $this->db->where('id', (int) $project_id)->update('projects', ['ia_desligada' => (bool) $desligada]);
    }
}
