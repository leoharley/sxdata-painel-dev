<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pontos de campo do mapa integrado (sql/field_points_migration.sql).
 *
 * Usado pela API do app (api/points) e pelo painel (mapa-integrado).
 */
class Field_point_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->library('ponto_campo');
    }

    /** A migração já rodou? */
    public function disponivel() {
        return $this->db->table_exists('field_points');
    }

    /** As colunas de vínculo em form_responses existem? (api/forms/submit funciona sem elas) */
    public function vinculo_disponivel() {
        static $ok = NULL;
        if ($ok === NULL) {
            $ok = $this->db->field_exists('field_point_client_id', 'form_responses');
        }
        return $ok;
    }

    // ------------------------------------------------------------
    // Acesso
    // ------------------------------------------------------------

    /** @return int[] projetos a que o usuário tem acesso no app (ver Ponto_campo::projetos_acessiveis) */
    public function projetos_do_usuario($user_id) {
        $user = $this->db->select('role')->get_where('users', ['id' => (int) $user_id])->row();
        if (!$user) {
            return [];
        }

        $todos = [];
        $questionarios = [];
        if (in_array($user->role, ['administrador', 'supervisor'], TRUE)) {
            $todos = array_column($this->db->select('id')->get('projects')->result_array(), 'id');
        } else {
            $this->load->model('Questionnaire_model');
            $this->db->select('q.id, q.project_id, q.aplicadores');
            $this->db->from('questionnaires q');
            $this->db->join('projects p', 'q.project_id = p.id');
            $this->db->where('q.status', 'active');
            $this->db->where_not_in('p.status', ['completed', 'cancelled']);
            foreach ($this->db->get()->result() as $q) {
                if ($this->Questionnaire_model->is_aplicador_allowed($q, $user_id)) {
                    $questionarios[] = $q;
                }
            }
        }

        return Ponto_campo::projetos_acessiveis($user->role, $questionarios, $todos);
    }

    // ------------------------------------------------------------
    // Gravação
    // ------------------------------------------------------------

    public function por_client_id($client_id) {
        return $this->db->get_where('field_points', ['client_id' => (string) $client_id])->row();
    }

    public function obter($id) {
        $this->db->select('fp.*, p.name AS projeto_nome, m.nome AS municipio_nome, m.uf, u.full_name AS criado_por_nome');
        $this->db->from('field_points fp');
        $this->db->join('projects p', 'p.id = fp.project_id', 'left');
        $this->db->join('ibge_municipios m', 'm.codigo = fp.codigo_ibge', 'left');
        $this->db->join('users u', 'u.id = fp.criado_por', 'left');
        $this->db->where('fp.id', (int) $id);
        return $this->db->get()->row();
    }

    public function municipio_existe($codigo) {
        return $this->db->where('codigo', (int) $codigo)->count_all_results('ibge_municipios') > 0;
    }

    /**
     * Cria ou atualiza o ponto pelo client_id e vincula as coletas que
     * esperavam por ele. Tudo numa transação.
     *
     * @param array $dados saída de Ponto_campo::validar + dentro_municipio
     * @return array ['id' => int, 'novo' => bool, 'coletas_vinculadas' => int]
     */
    public function salvar(array $dados, $user_id) {
        $agora = date('Y-m-d H:i:s');
        $this->db->trans_begin();
        try {
            $existente = $this->db->query('SELECT id FROM field_points WHERE client_id = ? FOR UPDATE',
                [$dados['client_id']])->row();

            if ($existente) {
                $id = (int) $existente->id;
                $campos = $dados;
                unset($campos['client_id']);
                $campos['atualizado_em'] = $agora;
                $this->db->where('id', $id)->update('field_points', $campos);
                $novo = FALSE;
            } else {
                $dados['criado_por'] = (int) $user_id ?: NULL;
                $dados['criado_em'] = $agora;
                $dados['atualizado_em'] = $agora;
                // Envio simultâneo do mesmo ponto: o UNIQUE de client_id decide
                $this->db->query(
                    'INSERT INTO field_points (' . implode(', ', array_keys($dados)) . ') VALUES ('
                    . implode(', ', array_fill(0, count($dados), '?')) . ') ON CONFLICT (client_id) DO NOTHING',
                    array_values($dados));
                $novo = $this->db->affected_rows() > 0;
                $row = $this->por_client_id($dados['client_id']);
                if (!$row) {
                    throw new Exception('Não foi possível gravar o ponto.');
                }
                $id = (int) $row->id;
            }

            $vinculadas = $this->vincular_pendentes($id, $dados['client_id']);

            if ($this->db->trans_status() === FALSE) {
                throw new Exception('Falha na transação.');
            }
            $this->db->trans_commit();
            return ['id' => $id, 'novo' => $novo, 'coletas_vinculadas' => $vinculadas];
        } catch (Exception $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    /** Coletas que chegaram antes do ponto passam a apontar para ele. @return int quantas */
    public function vincular_pendentes($id, $client_id) {
        if (!$this->vinculo_disponivel()) {
            return 0;
        }
        $this->db->where('field_point_client_id', (string) $client_id);
        $this->db->where('field_point_id IS NULL', NULL, FALSE);
        $this->db->update('form_responses', ['field_point_id' => (int) $id]);
        return (int) $this->db->affected_rows();
    }

    // ------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------

    /**
     * Pontos dos projetos informados, opcionalmente de um município, com o
     * número de coletas vinculadas.
     *
     * @param int[]    $project_ids vazio = nenhum ponto
     * @param int|null $codigo_ibge
     */
    public function listar(array $project_ids, $codigo_ibge = NULL, $limite = 5000) {
        if (!$project_ids) {
            return [];
        }
        $coletas = $this->vinculo_disponivel()
            ? '(SELECT COUNT(*) FROM form_responses fr WHERE fr.field_point_id = fp.id)'
            : '0';

        $this->db->select("fp.id, fp.client_id, fp.project_id, p.name AS projeto_nome, fp.codigo_ibge,
                           m.nome AS municipio_nome, m.uf, fp.nome, fp.tipo, fp.latitude, fp.longitude,
                           fp.origem_coord, fp.dentro_municipio, fp.criado_por, u.full_name AS criado_por_nome,
                           fp.criado_em, fp.atualizado_em, $coletas AS coletas", FALSE);
        $this->db->from('field_points fp');
        $this->db->join('projects p', 'p.id = fp.project_id', 'left');
        $this->db->join('ibge_municipios m', 'm.codigo = fp.codigo_ibge', 'left');
        $this->db->join('users u', 'u.id = fp.criado_por', 'left');
        $this->db->where_in('fp.project_id', array_map('intval', $project_ids));
        if ($codigo_ibge) {
            $this->db->where('fp.codigo_ibge', (int) $codigo_ibge);
        }
        $this->db->order_by('fp.nome', 'ASC');
        $this->db->limit((int) $limite);

        return array_map([$this, 'normalizar'], $this->db->get()->result());
    }

    /** Tipos do PostgreSQL ('t'/'f', NUMERIC em texto) para JSON. */
    public function normalizar($p) {
        $p->id = (int) $p->id;
        $p->project_id = (int) $p->project_id;
        $p->codigo_ibge = (int) $p->codigo_ibge;
        $p->latitude = $p->latitude === NULL ? NULL : (float) $p->latitude;
        $p->longitude = $p->longitude === NULL ? NULL : (float) $p->longitude;
        $p->dentro_municipio = $p->dentro_municipio === NULL ? NULL
            : in_array($p->dentro_municipio, [TRUE, 't', 'true', '1', 1], TRUE);
        if (isset($p->coletas)) $p->coletas = (int) $p->coletas;
        if (isset($p->criado_por)) $p->criado_por = $p->criado_por === NULL ? NULL : (int) $p->criado_por;
        return $p;
    }
}
