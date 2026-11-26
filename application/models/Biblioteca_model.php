<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Estrutura_compilador.php';

/**
 * Frente L (sql/conjuntos_migration.sql): conjuntos de perguntas reaproveitáveis,
 * temas e entrevistados pré-cadastrados.
 */
class Biblioteca_model extends CI_Model {

    private static $disponivel = NULL;

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    public function disponivel() {
        if (self::$disponivel === NULL) {
            self::$disponivel = $this->db->table_exists('conjuntos') && $this->db->table_exists('entrevistados');
        }
        return self::$disponivel;
    }

    // ------------------------------------------------------------------
    // Conjuntos
    // ------------------------------------------------------------------

    /** Resolvedor do @usar: última versão publicada do conjunto. */
    public function resolvedor() {
        if (!$this->disponivel()) { return NULL; }
        $db = $this->db;
        return function ($codigo) use ($db) {
            $r = $db->query('SELECT c.id, c.codigo, c.nome, v.versao, v.fonte FROM conjuntos c
                JOIN conjunto_versoes v ON v.conjunto_id = c.id AND v.versao = c.versao_atual
                WHERE c.codigo = ? AND c.ativo', [$codigo])->row_array();
            return $r ?: NULL;
        };
    }

    public function conjuntos() {
        return $this->db->query("SELECT c.*, u.full_name AS autor,
                (SELECT string_agg(t.nome, ', ' ORDER BY t.nome) FROM tema_conjuntos tc JOIN temas t ON t.id = tc.tema_id WHERE tc.conjunto_id = c.id) AS temas
            FROM conjuntos c LEFT JOIN users u ON u.id = c.created_by WHERE c.ativo ORDER BY c.nome")->result();
    }

    public function conjunto($id) {
        return $this->db->get_where('conjuntos', ['id' => (int) $id])->row() ?: NULL;
    }

    public function criar_conjunto($codigo, $nome, $descricao, $fonte, $user_id) {
        $this->db->insert('conjuntos', ['codigo' => $codigo, 'nome' => $nome, 'descricao' => $descricao ?: NULL,
            'fonte' => $fonte, 'created_by' => $user_id ?: NULL]);
        return (int) $this->db->insert_id();
    }

    public function salvar_conjunto($id, array $d) {
        $d['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $id)->update('conjuntos', array_intersect_key($d, array_flip(['nome', 'descricao', 'fonte', 'updated_at', 'ativo'])));
    }

    /** Valida (uma única etapa) e publica nova versão. @return [versão|NULL, erros] */
    public function publicar_conjunto($id, $user_id) {
        $c = $this->conjunto($id);
        if (!$c) { return [NULL, [['linha' => NULL, 'mensagem' => 'Conjunto não encontrado.']]]; }
        $erros = self::validar_conjunto($c->fonte);
        if ($erros) { return [NULL, $erros]; }
        $versao = (int) $c->versao_atual + 1;
        $this->db->trans_begin();
        $this->db->insert('conjunto_versoes', ['conjunto_id' => (int) $id, 'versao' => $versao, 'fonte' => $c->fonte, 'publicado_por' => $user_id ?: NULL]);
        $this->db->where('id', (int) $id)->update('conjuntos', ['versao_atual' => $versao, 'updated_at' => date('Y-m-d H:i:s')]);
        if ($this->db->trans_status() === FALSE) { $this->db->trans_rollback(); return [NULL, [['linha' => NULL, 'mensagem' => 'Falha ao gravar.']]]; }
        $this->db->trans_commit();
        return [$versao, []];
    }

    public static function validar_conjunto($fonte) {
        list($def, $erros) = Estrutura_compilador::compilar($fonte);
        if ($erros) { return $erros; }
        if (count($def['etapas']) !== 1 || $def['etapas'][0]['tipo'] !== 'perguntas') {
            return [['linha' => NULL, 'mensagem' => 'Um conjunto tem exatamente uma etapa: @domicilio, @morador ou @repetir.']];
        }
        return [];
    }

    public function versoes_conjunto($id) {
        return $this->db->query('SELECT v.versao, v.publicado_em, u.full_name AS autor FROM conjunto_versoes v
            LEFT JOIN users u ON u.id = v.publicado_por WHERE v.conjunto_id = ? ORDER BY v.versao DESC', [(int) $id])->result();
    }

    /** Formulários estruturados cujo texto usa o conjunto. */
    public function usos_conjunto($codigo) {
        return $this->db->query("SELECT id, title FROM questionnaires WHERE formato = 'estruturado'
            AND estrutura_fonte ~ ?", ['(^|\n)\s*@usar\s+' . preg_quote($codigo) . '(\s|$)'])->result();
    }

    // ------------------------------------------------------------------
    // Temas
    // ------------------------------------------------------------------

    public function temas() {
        $temas = $this->db->order_by('nome')->get('temas')->result();
        foreach ($temas as $t) {
            $t->conjuntos = $this->db->query('SELECT c.id, c.codigo, c.nome FROM tema_conjuntos tc JOIN conjuntos c ON c.id = tc.conjunto_id
                WHERE tc.tema_id = ? ORDER BY c.nome', [(int) $t->id])->result();
        }
        return $temas;
    }

    public function salvar_tema($id, $nome, $descricao, array $conjunto_ids) {
        $this->db->trans_begin();
        if ($id) {
            $this->db->where('id', (int) $id)->update('temas', ['nome' => $nome, 'descricao' => $descricao ?: NULL]);
        } else {
            $this->db->insert('temas', ['nome' => $nome, 'descricao' => $descricao ?: NULL]);
            $id = (int) $this->db->insert_id();
        }
        $this->db->where('tema_id', (int) $id)->delete('tema_conjuntos');
        foreach (array_unique(array_map('intval', $conjunto_ids)) as $cid) {
            if ($cid > 0) { $this->db->insert('tema_conjuntos', ['tema_id' => (int) $id, 'conjunto_id' => $cid]); }
        }
        $this->db->trans_commit();
        return (int) $id;
    }

    public function excluir_tema($id) {
        $this->db->where('id', (int) $id)->delete('temas');
    }

    // ------------------------------------------------------------------
    // Entrevistados
    // ------------------------------------------------------------------

    public function entrevistados($project_id, $busca = '') {
        $this->db->where('project_id', (int) $project_id)->where('ativo', 't');
        if ($busca !== '') {
            $this->db->group_start()->like('LOWER(nome)', mb_strtolower($busca))->or_like('LOWER(codigo)', mb_strtolower($busca))->group_end();
        }
        return $this->db->order_by('nome')->limit(2000)->get('entrevistados')->result();
    }

    public function entrevistado($id) {
        return $this->db->get_where('entrevistados', ['id' => (int) $id])->row() ?: NULL;
    }

    /** Dados do entrevistado como a linguagem enxerga (ent_codigo, ent_nome, ent_categoria em código...). */
    public static function contexto($e) {
        if (!$e) { return []; }
        $ctx = ['id' => (int) $e->id, 'codigo' => $e->codigo, 'nome' => $e->nome,
            'categoria' => $e->categoria !== NULL ? Estrutura_compilador::slug($e->categoria) : NULL,
            'localidade' => $e->localidade];
        foreach ((json_decode((string) $e->campos, TRUE) ?: []) as $k => $v) {
            $k = Estrutura_compilador::slug($k);
            if ($k !== '' && !isset($ctx[$k]) && is_scalar($v)) { $ctx[$k] = $v; }
        }
        return ['entrevistado' => $ctx];
    }

    /**
     * Normaliza uma linha (formulário ou planilha). Colunas fixas: codigo, nome,
     * categoria, localidade, latitude, longitude; as demais vão para "campos".
     * @return array [dados|NULL, erro|NULL]
     */
    public static function normalizar_entrevistado(array $linha) {
        $l = [];
        foreach ($linha as $k => $v) { $l[Estrutura_compilador::slug($k)] = is_scalar($v) ? trim((string) $v) : ''; }
        $codigo = mb_substr($l['codigo'] ?? '', 0, 60);
        $nome = mb_substr($l['nome'] ?? '', 0, 200);
        if ($codigo === '' || $nome === '') { return [NULL, 'codigo e nome são obrigatórios']; }
        $coord = function ($v, $lim) {
            $v = str_replace(',', '.', (string) $v);
            return is_numeric($v) && abs((float) $v) <= $lim && (float) $v != 0 ? round((float) $v, 7) : NULL;
        };
        $campos = array_diff_key($l, array_flip(['codigo', 'nome', 'categoria', 'localidade', 'latitude', 'longitude', 'id']));
        $campos = array_filter($campos, 'strlen');
        return [['codigo' => $codigo, 'nome' => $nome, 'categoria' => ($l['categoria'] ?? '') !== '' ? mb_substr($l['categoria'], 0, 120) : NULL,
            'localidade' => ($l['localidade'] ?? '') !== '' ? mb_substr($l['localidade'], 0, 200) : NULL,
            'latitude' => $coord($l['latitude'] ?? NULL, 90), 'longitude' => $coord($l['longitude'] ?? NULL, 180),
            'campos' => $campos ? json_encode($campos, JSON_UNESCAPED_UNICODE) : NULL], NULL];
    }

    /** Cria ou atualiza pelo código (no projeto). */
    public function gravar_entrevistado($project_id, array $d) {
        $ja = $this->db->get_where('entrevistados', ['project_id' => (int) $project_id, 'codigo' => $d['codigo']])->row();
        $d['ativo'] = 't';
        $d['updated_at'] = date('Y-m-d H:i:s');
        if ($ja) {
            $this->db->where('id', (int) $ja->id)->update('entrevistados', $d);
            return [(int) $ja->id, FALSE];
        }
        $this->db->insert('entrevistados', $d + ['project_id' => (int) $project_id]);
        return [(int) $this->db->insert_id(), TRUE];
    }

    public function desativar_entrevistado($id) {
        $this->db->where('id', (int) $id)->update('entrevistados', ['ativo' => 'f', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** Para o app: entrevistados dos projetos acessíveis, alterados desde a data (inclui os desativados, para remover). */
    public function para_app(array $projetos, $desde = NULL) {
        if (!$projetos) { return []; }
        $this->db->select('id, project_id, codigo, nome, categoria, localidade, latitude, longitude, campos, ativo, updated_at')
            ->where_in('project_id', array_map('intval', $projetos));
        if ($desde) { $this->db->where('updated_at >', $desde); } else { $this->db->where('ativo', 't'); }
        $lista = [];
        foreach ($this->db->order_by('updated_at')->get('entrevistados')->result() as $e) {
            $ctx = self::contexto($e)['entrevistado'];
            $lista[] = ['id' => (int) $e->id, 'project_id' => (int) $e->project_id, 'codigo' => $e->codigo, 'nome' => $e->nome,
                'categoria' => $e->categoria, 'localidade' => $e->localidade,
                'latitude' => $e->latitude !== NULL ? (float) $e->latitude : NULL, 'longitude' => $e->longitude !== NULL ? (float) $e->longitude : NULL,
                'campos' => json_decode((string) $e->campos, TRUE) ?: new stdClass(), 'contexto' => $ctx,
                'ativo' => $e->ativo === 't' || $e->ativo === TRUE, 'atualizado_em' => $e->updated_at];
        }
        return $lista;
    }
}
