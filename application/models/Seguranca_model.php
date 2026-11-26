<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Consultas das telas de controle (frente I): auditoria, erros, aparelhos e
 * fila de revisão. A gravação fica nos pontos de origem (auditar(),
 * Monitor_erros, api/system e api/forms/submit).
 */
class Seguranca_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    public function disponivel() {
        return $this->db->table_exists('audit_log') && $this->db->table_exists('erros_sistema');
    }

    // ---------------- Auditoria ----------------

    private function filtro_auditoria($f) {
        if (!empty($f['user_id'])) {
            $this->db->where('a.user_id', (int) $f['user_id']);
        }
        if (!empty($f['acao'])) {
            $this->db->where('a.acao', $f['acao']);
        }
        if (!empty($f['entidade'])) {
            $this->db->where('a.entidade', $f['entidade']);
        }
        if (!empty($f['de']) && strtotime($f['de'])) {
            $this->db->where('a.criado_em >=', date('Y-m-d 00:00:00', strtotime($f['de'])));
        }
        if (!empty($f['ate']) && strtotime($f['ate'])) {
            $this->db->where('a.criado_em <=', date('Y-m-d 23:59:59', strtotime($f['ate'])));
        }
        if (!empty($f['busca'])) {
            $b = '%' . $this->db->escape_like_str($f['busca']) . '%';
            $this->db->group_start()->where('a.descricao ILIKE', $b)->or_where('a.username ILIKE', $b)->or_where('a.entidade_id', $f['busca'])->or_where('a.ip', $f['busca'])->group_end();
        }
    }

    public function auditoria($f, $pagina = 1, $por_pagina = 50) {
        $this->db->from('audit_log a');
        $this->filtro_auditoria($f);
        $total = $this->db->count_all_results('', FALSE);
        $linhas = $this->db->select('a.*')->order_by('a.id', 'DESC')
            ->limit($por_pagina, max(0, ($pagina - 1) * $por_pagina))->get()->result();
        return [$linhas, $total];
    }

    /** Para o CSV: lote por lote, sem carregar tudo na memória. */
    public function auditoria_lote($f, $depois_de, $lote = 2000) {
        $this->db->from('audit_log a');
        $this->filtro_auditoria($f);
        if ($depois_de) {
            $this->db->where('a.id <', (int) $depois_de);
        }
        return $this->db->select('a.*')->order_by('a.id', 'DESC')->limit($lote)->get()->result_array();
    }

    public function acoes_auditadas() {
        return array_column($this->db->query('SELECT DISTINCT acao FROM audit_log ORDER BY 1')->result_array(), 'acao');
    }

    public function entidades_auditadas() {
        return array_column($this->db->query('SELECT DISTINCT entidade FROM audit_log WHERE entidade IS NOT NULL ORDER BY 1')->result_array(), 'entidade');
    }

    // ---------------- Erros ----------------

    public function erros($abertos = TRUE, $origem = NULL, $limite = 300) {
        $this->db->select('e.*, u.full_name AS usuario')->from('erros_sistema e')->join('users u', 'u.id = e.user_id', 'left');
        $this->db->where($abertos ? 'e.resolvido_em IS NULL' : 'e.resolvido_em IS NOT NULL', NULL, FALSE);
        if ($origem) {
            $this->db->where('e.origem', $origem);
        }
        return $this->db->order_by('e.ultimo_em', 'DESC')->limit($limite)->get()->result();
    }

    public function erro($id) {
        return $this->db->get_where('erros_sistema', ['id' => (int) $id])->row();
    }

    public function resolver_erro($id, $por) {
        return $this->db->where('id', (int) $id)->where('resolvido_em IS NULL', NULL, FALSE)
            ->update('erros_sistema', ['resolvido_em' => date('Y-m-d H:i:s'), 'resolvido_por' => (int) $por]);
    }

    public function resolver_todos($origem, $por) {
        $this->db->where('resolvido_em IS NULL', NULL, FALSE);
        if ($origem) {
            $this->db->where('origem', $origem);
        }
        return $this->db->update('erros_sistema', ['resolvido_em' => date('Y-m-d H:i:s'), 'resolvido_por' => (int) $por]);
    }

    // ---------------- Aparelhos ----------------

    public function aparelhos() {
        return $this->db->select('d.*, u.full_name, u.username')
            ->from('dispositivos d')->join('users u', 'u.id = d.user_id', 'left')
            ->order_by('d.pendentes + d.pendentes_midias', 'DESC', FALSE)->order_by('d.visto_em', 'DESC')
            ->get()->result();
    }

    /** Heartbeat do app: um registro por aparelho e usuário. */
    public function registrar_aparelho($user_id, $d, $ip) {
        $linha = [
            'device_id' => $d['device_id'], 'user_id' => (int) $user_id,
            'app_versao' => $d['app_versao'], 'plataforma' => $d['plataforma'],
            'pendentes' => $d['pendentes'], 'pendentes_midias' => $d['pendentes_midias'], 'rascunhos' => $d['rascunhos'],
            'ultimo_envio_em' => $d['ultimo_envio_em'], 'visto_em' => date('Y-m-d H:i:s'), 'ip' => mb_substr((string) $ip, 0, 64),
        ];
        $existe = $this->db->select('id')->get_where('dispositivos', ['device_id' => $d['device_id'], 'user_id' => (int) $user_id])->row();
        if ($existe) {
            unset($linha['device_id'], $linha['user_id']);
            if ($linha['ultimo_envio_em'] === NULL) {
                unset($linha['ultimo_envio_em']);
            }
            $this->db->where('id', (int) $existe->id)->update('dispositivos', $linha);
            return (int) $existe->id;
        }
        $this->db->insert('dispositivos', $linha);
        return (int) $this->db->insert_id();
    }

    // ---------------- Fila de revisão ----------------

    public function revisao($status = 'pendente', $questionnaire_id = NULL, $limite = 300) {
        $this->db->select("fr.id, fr.questionnaire_id, fr.respondent_name, fr.completed_at, fr.created_at, fr.revisao_status, fr.revisao_erros,
                           fr.revisado_em, fr.revisao_nota, q.title, COALESCE(q.formato, 'simples') AS formato, u.full_name AS aplicador, r.full_name AS revisor", FALSE)
            ->from('form_responses fr')
            ->join('questionnaires q', 'q.id = fr.questionnaire_id')
            ->join('users u', 'u.id = fr.applied_by', 'left')
            ->join('users r', 'r.id = fr.revisado_por', 'left')
            ->where('fr.revisao_status', $status);
        if ($questionnaire_id) {
            $this->db->where('fr.questionnaire_id', (int) $questionnaire_id);
        }
        return $this->db->order_by('fr.id', 'DESC')->limit($limite)->get()->result();
    }

    /**
     * Mensagens legíveis de revisao_erros. O formato simples grava uma lista
     * de textos; o estruturado grava {erros: [...], divergencias: [...]}.
     */
    public static function mensagens_revisao($json) {
        $d = is_array($json) ? $json : json_decode((string) $json, TRUE);
        if (!is_array($d)) {
            return [];
        }
        if (!isset($d['erros']) && !isset($d['divergencias'])) {
            return array_values(array_map(function ($e) { return is_array($e) ? ($e['mensagem'] ?? json_encode($e, JSON_UNESCAPED_UNICODE)) : (string) $e; }, $d));
        }
        $m = array_map('strval', $d['erros'] ?? []);
        foreach ($d['divergencias'] ?? [] as $v) {
            $fmt = function ($x) { return is_array($x) ? json_encode($x, JSON_UNESCAPED_UNICODE) : var_export($x, TRUE); };
            $m[] = 'Cálculo ' . ($v['codigo'] ?? '?') . (!empty($v['morador']) ? ' (morador ' . $v['morador'] . ')' : '')
                . ' diferente: app ' . $fmt($v['app'] ?? NULL) . ', servidor ' . $fmt($v['servidor'] ?? NULL) . '.';
        }
        return $m;
    }

    public function questionarios_na_revisao() {
        return $this->db->query("SELECT q.id, q.title, COUNT(*) AS n FROM form_responses fr JOIN questionnaires q ON q.id = fr.questionnaire_id
                                 WHERE fr.revisao_status = 'pendente' GROUP BY q.id, q.title ORDER BY q.title")->result();
    }

    public function revisar($id, $por, $nota) {
        return $this->db->where('id', (int) $id)->update('form_responses', [
            'revisao_status' => 'revisada', 'revisado_por' => (int) $por, 'revisado_em' => date('Y-m-d H:i:s'),
            'revisao_nota' => $nota !== '' ? $nota : NULL,
        ]);
    }

    public function reabrir($id) {
        return $this->db->where('id', (int) $id)->update('form_responses', ['revisao_status' => 'pendente', 'revisado_por' => NULL, 'revisado_em' => NULL]);
    }

    // ---------------- Contadores do menu ----------------

    public function contadores() {
        $c = ['revisao' => 0, 'erros' => 0, 'aparelhos' => 0];
        $debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $r = $this->db->query("SELECT COUNT(*) AS n FROM form_responses WHERE revisao_status = 'pendente'");
        $c['revisao'] = $r ? (int) $r->row()->n : 0;
        $r = $this->db->query('SELECT COUNT(*) AS n FROM erros_sistema WHERE resolvido_em IS NULL');
        $c['erros'] = $r ? (int) $r->row()->n : 0;
        $r = $this->db->query("SELECT COUNT(*) AS n FROM dispositivos WHERE pendentes + pendentes_midias > 0 AND visto_em < NOW() - INTERVAL '24 hours'");
        $c['aparelhos'] = $r ? (int) $r->row()->n : 0;
        $this->db->db_debug = $debug;
        return $c;
    }
}
