<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Lixeira (exclusão lógica com restauração), sql/robustez_migration.sql.
 *
 * Antes de apagar, guarda uma cópia de todas as linhas envolvidas (a
 * entrevista com respostas, moradores e repetições; o questionário com
 * perguntas e opções; o projeto com seus vínculos). Restaurar devolve as
 * linhas com os mesmos ids, na ordem certa, e refaz os vínculos que o banco
 * tinha zerado (FK ON DELETE SET NULL). As consultas do sistema continuam
 * iguais: o que está na lixeira simplesmente não está nas tabelas.
 */
class Lixeira_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    public function disponivel() {
        return $this->db->table_exists('lixeira');
    }

    // ------------------------------------------------------------
    // Excluir
    // ------------------------------------------------------------

    /** Exclui uma entrevista (simples ou estruturada). Devolve o id do item da lixeira. */
    public function excluir_entrevista($id, $por, $motivo = NULL) {
        $fr = $this->db->get_where('form_responses', ['id' => (int) $id])->row_array();
        if (!$fr) {
            throw new InvalidArgumentException('Entrevista não encontrada.');
        }
        $q = $this->db->select('title')->get_where('questionnaires', ['id' => (int) $fr['questionnaire_id']])->row();
        $plano = [
            // tabela => [coluna, valor] na ordem de RESTAURAÇÃO (pais antes dos filhos)
            ['form_responses', 'id'],
            ['moradores', 'form_response_id'],
            ['repeticoes', 'form_response_id'],
            ['question_responses', 'form_response_id'],
            ['respostas_estruturadas', 'form_response_id'],
            ['ai_corrections', 'form_response_id'],
            ['ai_inconsistencies', 'form_response_id'],
        ];
        $religar = [['ai_field_suggestions', 'form_response_id'], ['ai_transcriptions', 'form_response_id']];
        $descricao = 'Entrevista #' . $fr['id'] . ' — ' . ($q ? $q->title : 'questionário ' . $fr['questionnaire_id'])
            . ($fr['respondent_name'] ? ' — ' . $fr['respondent_name'] : '')
            . ($fr['completed_at'] ? ' (' . date('d/m/Y', strtotime($fr['completed_at'])) . ')' : '');
        return $this->mover('entrevista', (int) $id, $descricao, $plano, $religar, $por, $motivo, 'form_responses');
    }

    /** Exclui um morador de uma entrevista estruturada (com as respostas dele). */
    public function excluir_morador($id, $por, $motivo = NULL) {
        $m = $this->db->get_where('moradores', ['id' => (int) $id])->row_array();
        if (!$m) {
            throw new InvalidArgumentException('Morador não encontrado.');
        }
        $plano = [['moradores', 'id'], ['respostas_estruturadas', 'morador_id']];
        $descricao = 'Morador ' . $m['nome'] . ' (entrevista #' . $m['form_response_id'] . ')';
        return $this->mover('morador', (int) $id, $descricao, $plano, [], $por, $motivo, 'moradores');
    }

    /** Questionário sem respostas (com respostas, o sistema só inativa, como antes). */
    public function excluir_questionario($id, $por, $motivo = NULL) {
        $q = $this->db->get_where('questionnaires', ['id' => (int) $id])->row_array();
        if (!$q) {
            throw new InvalidArgumentException('Questionário não encontrado.');
        }
        $perguntas = array_map('intval', array_column($this->db->select('id')->get_where('questions', ['questionnaire_id' => (int) $id])->result_array(), 'id'));
        $plano = [
            ['questionnaires', 'id'],
            ['questions', 'questionnaire_id'],
            ['question_options', 'question_id', $perguntas],
            ['question_followup_tips', 'question_id', $perguntas],
            ['questionnaire_public_links', 'questionnaire_id'],
            ['estrutura_versoes', 'questionnaire_id'],
        ];
        $religar = [['ai_reports', 'questionnaire_id'], ['ai_statistical_analyses', 'questionnaire_id']];
        return $this->mover('questionario', (int) $id, 'Questionário ' . $q['title'], $plano, $religar, $por, $motivo, 'questionnaires');
    }

    /** Projeto: os questionários ficam (sem projeto), e voltam a ele na restauração. */
    public function excluir_projeto($id, $por, $motivo = NULL) {
        $p = $this->db->get_where('projects', ['id' => (int) $id])->row_array();
        if (!$p) {
            throw new InvalidArgumentException('Projeto não encontrado.');
        }
        $pontos = array_map('intval', array_column($this->db->select('id')->get_where('field_points', ['project_id' => (int) $id])->result_array(), 'id'));
        $entrevistados = $this->db->table_exists('entrevistados')
            ? array_map('intval', array_column($this->db->select('id')->get_where('entrevistados', ['project_id' => (int) $id])->result_array(), 'id')) : [];
        $plano = [
            ['projects', 'id'],
            ['project_clients', 'project_id'],
            ['field_points', 'project_id'],
            ['entrevistados', 'project_id'],
        ];
        $religar = [
            ['questionnaires', 'project_id'],
            ['municipal_reports', 'project_id'],
            ['pontos_coleta', 'project_id'],
        ];
        // Entrevistas que apontavam para os pontos/entrevistados do projeto
        $religar_ids = [];
        if ($pontos) {
            $religar_ids[] = ['form_responses', 'field_point_id', $pontos];
        }
        if ($entrevistados) {
            $religar_ids[] = ['form_responses', 'entrevistado_id', $entrevistados];
        }
        return $this->mover('projeto', (int) $id, 'Projeto ' . $p['name'], $plano, $religar, $por, $motivo, 'projects', $religar_ids);
    }

    /**
     * @param array $plano   [[tabela, coluna, valores?], ...] na ordem de restauração
     * @param array $religar [[tabela, coluna], ...] com FK SET NULL para o id principal
     * @param array $religar_ids [[tabela, coluna, [ids do pai]], ...] FK SET NULL para filhos
     */
    private function mover($tipo, $id, $descricao, $plano, $religar, $por, $motivo, $principal, $religar_ids = []) {
        if (!$this->disponivel()) {
            throw new RuntimeException('Rode sql/robustez_migration.sql no servidor.');
        }
        $linhas = [];
        $ordem = [];
        foreach ($plano as $passo) {
            list($tabela, $coluna) = $passo;
            $valores = isset($passo[2]) ? $passo[2] : [$id];
            if (!$valores || !$this->db->table_exists($tabela)) {
                continue;
            }
            $linhas[$tabela] = array_merge($linhas[$tabela] ?? [], $this->db->where_in($coluna, $valores)->get($tabela)->result_array());
            if (!in_array($tabela, $ordem, TRUE)) {
                $ordem[] = $tabela;
            }
        }
        $vinculos = [];
        foreach ($religar as $r) {
            list($tabela, $coluna) = $r;
            if (!$this->db->table_exists($tabela) || !$this->db->field_exists($coluna, $tabela)) {
                continue;
            }
            $ids = array_map('intval', array_column($this->db->select('id')->get_where($tabela, [$coluna => $id])->result_array(), 'id'));
            if ($ids) {
                $vinculos[] = ['tabela' => $tabela, 'coluna' => $coluna, 'valor' => $id, 'ids' => $ids];
            }
        }
        foreach ($religar_ids as $r) {
            list($tabela, $coluna, $pais) = $r;
            if (!$this->db->field_exists($coluna, $tabela)) {
                continue;
            }
            foreach ($this->db->select('id, ' . $coluna)->where_in($coluna, $pais)->get($tabela)->result_array() as $l) {
                $vinculos[] = ['tabela' => $tabela, 'coluna' => $coluna, 'valor' => (int) $l[$coluna], 'ids' => [(int) $l['id']]];
            }
        }

        $dados = ['versao' => 1, 'principal' => $principal, 'ordem' => $ordem, 'linhas' => $linhas, 'religar' => $vinculos];
        $this->db->trans_begin();
        try {
            $this->db->insert('lixeira', [
                'tipo' => $tipo, 'ref_id' => $id, 'descricao' => mb_substr($descricao, 0, 500),
                'dados' => json_encode($dados, JSON_UNESCAPED_UNICODE), 'motivo' => $motivo ?: NULL,
                'excluido_por' => $por ? (int) $por : NULL,
            ]);
            $lid = (int) $this->db->insert_id();
            // Apaga do filho para o pai (as cascatas cuidam do resto)
            foreach (array_reverse($ordem) as $tabela) {
                $ids = array_column($linhas[$tabela], 'id');
                if ($ids) {
                    $this->db->where_in('id', $ids)->delete($tabela);
                }
            }
            if ($this->db->trans_status() === FALSE) {
                throw new RuntimeException('Falha ao excluir.');
            }
            $this->db->trans_commit();
        } catch (Exception $e) {
            $this->db->trans_rollback();
            throw $e;
        }
        auditar('excluir', $tipo, $id, $descricao . ($motivo ? ' — motivo: ' . $motivo : ''), ['lixeira_id' => $lid, 'linhas' => array_map('count', $linhas)]);
        return $lid;
    }

    // ------------------------------------------------------------
    // Restaurar e consultar
    // ------------------------------------------------------------

    public function restaurar($lixeira_id, $por) {
        $item = $this->db->get_where('lixeira', ['id' => (int) $lixeira_id])->row();
        if (!$item || $item->restaurado_em) {
            throw new InvalidArgumentException('Item não encontrado na lixeira (ou já restaurado).');
        }
        $dados = json_decode($item->dados, TRUE);
        $this->db->trans_begin();
        try {
            if ($item->tipo === 'morador') {
                $fr = $dados['linhas']['moradores'][0]['form_response_id'] ?? 0;
                if (!$this->db->get_where('form_responses', ['id' => (int) $fr])->row()) {
                    throw new RuntimeException('A entrevista deste morador não existe mais. Restaure a entrevista antes.');
                }
            }
            $debug = $this->db->db_debug;
            $this->db->db_debug = FALSE;
            foreach ($dados['ordem'] as $tabela) {
                if (!$this->db->table_exists($tabela)) {
                    continue;
                }
                $campos = array_flip($this->db->list_fields($tabela));
                foreach ($dados['linhas'][$tabela] as $linha) {
                    $linha = array_intersect_key($linha, $campos); // coluna removida depois não quebra
                    if (!$this->db->insert($tabela, $linha)) {
                        $erro = $this->db->error();
                        $this->db->db_debug = $debug;
                        throw new RuntimeException('Não foi possível restaurar (' . $tabela . ' #' . ($linha['id'] ?? '?') . '): '
                            . (stripos($erro['message'], 'duplicate') !== FALSE || stripos($erro['message'], 'unique') !== FALSE
                                ? 'já existe um registro igual (o app pode ter reenviado esta entrevista).' : $erro['message']));
                    }
                }
            }
            $this->db->db_debug = $debug;
            foreach ($dados['religar'] as $v) {
                if ($this->db->table_exists($v['tabela'])) {
                    $this->db->where_in('id', $v['ids'])->where($v['coluna'] . ' IS NULL', NULL, FALSE)
                        ->update($v['tabela'], [$v['coluna'] => $v['valor']]);
                }
            }
            $this->db->where('id', (int) $item->id)->update('lixeira', ['restaurado_por' => $por ? (int) $por : NULL, 'restaurado_em' => date('Y-m-d H:i:s')]);
            if ($this->db->trans_status() === FALSE) {
                throw new RuntimeException('Falha ao restaurar.');
            }
            $this->db->trans_commit();
        } catch (Exception $e) {
            $this->db->trans_rollback();
            throw $e;
        }
        auditar('restaurar', $item->tipo, $item->ref_id, $item->descricao, NULL, ['lixeira_id' => (int) $item->id]);
        return $item;
    }

    /** Apaga de vez a cópia (só administrador). */
    public function purgar($lixeira_id) {
        $item = $this->db->get_where('lixeira', ['id' => (int) $lixeira_id])->row();
        if (!$item) {
            return FALSE;
        }
        $this->db->where('id', (int) $item->id)->delete('lixeira');
        auditar('purgar', $item->tipo, $item->ref_id, 'Removido de vez da lixeira: ' . $item->descricao);
        return TRUE;
    }

    public function listar($tipo = NULL, $limite = 300) {
        $this->db->select('l.id, l.tipo, l.ref_id, l.descricao, l.motivo, l.excluido_em, l.restaurado_em, e.full_name AS excluido_por_nome, r.full_name AS restaurado_por_nome')
            ->from('lixeira l')
            ->join('users e', 'e.id = l.excluido_por', 'left')
            ->join('users r', 'r.id = l.restaurado_por', 'left');
        if ($tipo) {
            $this->db->where('l.tipo', $tipo);
        }
        return $this->db->order_by('l.excluido_em', 'DESC')->limit($limite)->get()->result();
    }
}
