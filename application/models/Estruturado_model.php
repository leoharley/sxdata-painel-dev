<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Estrutura_compilador.php';
require_once APPPATH . 'libraries/Estrutura_envio.php';

/**
 * Formulários estruturados (sql/estruturado_migration.sql): questionários com
 * formato = 'estruturado', versões publicadas, entrevistas, moradores e respostas.
 */
class Estruturado_model extends CI_Model {

    private static $disponivel = NULL;

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    /** A migração foi aplicada? */
    public function disponivel() {
        if (self::$disponivel === NULL) {
            self::$disponivel = $this->db->field_exists('formato', 'questionnaires')
                && $this->db->table_exists('respostas_estruturadas');
        }
        return self::$disponivel;
    }

    // ------------------------------------------------------------------
    // Formulários e versões
    // ------------------------------------------------------------------

    public function listar() {
        return $this->db->query(
            "SELECT q.id, q.title, q.description, q.status, q.estrutura_versao, q.updated_at, q.project_id,
                    p.name AS project_name,
                    (SELECT COUNT(*) FROM form_responses fr WHERE fr.questionnaire_id = q.id) AS entrevistas,
                    (SELECT COUNT(*) FROM form_responses fr WHERE fr.questionnaire_id = q.id AND fr.revisao_status = 'pendente') AS pendentes
               FROM questionnaires q LEFT JOIN projects p ON p.id = q.project_id
              WHERE q.formato = 'estruturado'
              ORDER BY q.updated_at DESC NULLS LAST, q.id DESC")->result();
    }

    public function obter($id) {
        $q = $this->db->query(
            "SELECT q.*, p.name AS project_name FROM questionnaires q LEFT JOIN projects p ON p.id = q.project_id
              WHERE q.id = ? AND q.formato = 'estruturado'", [(int) $id])->row();
        return $q ?: NULL;
    }

    public function criar(array $d, $user_id) {
        $this->db->insert('questionnaires', [
            'title' => $d['title'],
            'description' => $d['description'] ?? NULL,
            'project_id' => $d['project_id'] ?: NULL,
            'status' => 'paused', // só vai ao app depois de publicado e ativado
            'formato' => 'estruturado',
            'estrutura_fonte' => $d['fonte'] ?? '',
            'created_by' => $user_id ?: NULL,
            'aplicadores' => NULL,
        ]);
        return (int) $this->db->insert_id();
    }

    public function salvar_fonte($id, $fonte) {
        $this->db->where('id', (int) $id)->where('formato', 'estruturado')
            ->update('questionnaires', ['estrutura_fonte' => $fonte, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function atualizar_dados($id, array $d) {
        $campos = array_intersect_key($d, array_flip(['title', 'description', 'project_id', 'status', 'aplicadores']));
        $campos['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $id)->where('formato', 'estruturado')->update('questionnaires', $campos);
    }

    /**
     * Compila a fonte salva e publica uma nova versão.
     * @return array [versão publicada ou NULL, erros, avisos]
     */
    public function publicar($id, $user_id) {
        $q = $this->obter($id);
        if (!$q) { return [NULL, [['linha' => NULL, 'mensagem' => 'Formulário não encontrado.']], []]; }
        list($def, $erros, $avisos) = Estrutura_compilador::compilar($q->estrutura_fonte);
        if ($erros) { return [NULL, $erros, $avisos]; }

        $this->db->trans_begin();
        $ultima = (int) $this->db->query('SELECT COALESCE(MAX(versao), 0) AS v FROM estrutura_versoes WHERE questionnaire_id = ?', [(int) $id])->row()->v;
        $versao = $ultima + 1;
        $def['questionario_id'] = (int) $id;
        $def['versao'] = $versao;
        $this->db->insert('estrutura_versoes', [
            'questionnaire_id' => (int) $id,
            'versao' => $versao,
            'definicao' => json_encode($def, JSON_UNESCAPED_UNICODE),
            'fonte' => $q->estrutura_fonte,
            'publicado_por' => $user_id ?: NULL,
        ]);
        $this->db->query('UPDATE questionnaires SET estrutura_versao = ?, version = version + 1, updated_at = NOW() WHERE id = ?', [$versao, (int) $id]);
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return [NULL, [['linha' => NULL, 'mensagem' => 'Falha ao gravar a versão.']], $avisos];
        }
        $this->db->trans_commit();
        return [$versao, [], $avisos];
    }

    /** Linha de estrutura_versoes com 'definicao' decodificada. */
    public function versao($qid, $versao = NULL) {
        if ($versao === NULL) {
            $row = $this->db->query('SELECT * FROM estrutura_versoes WHERE questionnaire_id = ? ORDER BY versao DESC LIMIT 1', [(int) $qid])->row();
        } else {
            $row = $this->db->get_where('estrutura_versoes', ['questionnaire_id' => (int) $qid, 'versao' => (int) $versao])->row();
        }
        if ($row) { $row->definicao = json_decode($row->definicao, TRUE); }
        return $row ?: NULL;
    }

    public function versao_por_id($id) {
        $row = $this->db->get_where('estrutura_versoes', ['id' => (int) $id])->row();
        if ($row) { $row->definicao = json_decode($row->definicao, TRUE); }
        return $row ?: NULL;
    }

    public function versoes($qid) {
        return $this->db->query('SELECT v.id, v.versao, v.publicado_em, u.full_name AS publicado_por
            FROM estrutura_versoes v LEFT JOIN users u ON u.id = v.publicado_por
            WHERE v.questionnaire_id = ? ORDER BY v.versao DESC', [(int) $qid])->result();
    }

    /** Formulários publicados e ativos que este aplicador pode coletar. */
    public function formularios_para_app($user_id) {
        $this->load->model('Questionnaire_model');
        $lista = $this->db->query(
            "SELECT q.id, q.title, q.description, q.project_id, q.aplicadores, q.estrutura_versao, q.updated_at,
                    p.name AS project_name, v.id AS versao_id, v.definicao
               FROM questionnaires q
               JOIN estrutura_versoes v ON v.questionnaire_id = q.id AND v.versao = q.estrutura_versao
               LEFT JOIN projects p ON p.id = q.project_id
              WHERE q.formato = 'estruturado' AND q.status = 'active'
                AND COALESCE(p.status, 'active') NOT IN ('completed', 'cancelled')
              ORDER BY q.title")->result();
        $saida = [];
        foreach ($lista as $q) {
            if (!$this->Questionnaire_model->is_aplicador_allowed($q, $user_id)) { continue; }
            $saida[] = [
                'id' => (int) $q->id,
                'titulo' => $q->title,
                'descricao' => $q->description,
                'project_id' => $q->project_id ? (int) $q->project_id : NULL,
                'project_name' => $q->project_name,
                'versao' => (int) $q->estrutura_versao,
                'versao_id' => (int) $q->versao_id,
                'atualizado_em' => $q->updated_at,
                'definicao' => json_decode($q->definicao, TRUE),
            ];
        }
        return $saida;
    }

    // ------------------------------------------------------------------
    // Entrevistas
    // ------------------------------------------------------------------

    /**
     * Grava (ou atualiza, se for edição mais nova) uma entrevista enviada pelo app.
     * @return array ['id', 'duplicado', 'editado', 'conferencia' => resultado de Estrutura_envio::conferir]
     */
    public function gravar_entrevista(array $d, $user_id) {
        $q = $this->obter($d['questionnaire_id']);
        if (!$q) { throw new InvalidArgumentException('Formulário estruturado não encontrado.'); }
        $versao = $this->versao($q->id, $d['versao']) ?: $this->versao($q->id);
        if (!$versao) { throw new InvalidArgumentException('Formulário sem versão publicada.'); }

        $conf = Estrutura_envio::conferir($versao->definicao, $d);

        $this->db->trans_begin();
        try {
            $existente = $this->db->query('SELECT id, edited_at FROM form_responses WHERE client_id = ? FOR UPDATE', [$d['client_id']])->row();
            if ($existente) {
                $mais_nova = $d['edited_at'] !== NULL
                    && (empty($existente->edited_at) || strtotime($d['edited_at']) > strtotime($existente->edited_at));
                if (!$mais_nova) {
                    $this->db->trans_commit();
                    return ['id' => (int) $existente->id, 'duplicado' => TRUE, 'editado' => FALSE, 'conferencia' => $conf];
                }
                $id = (int) $existente->id;
                $this->db->where('form_response_id', $id)->delete('respostas_estruturadas');
                $this->db->where('form_response_id', $id)->delete('moradores');
                $this->db->where('id', $id)->update('form_responses', $this->cabecalho($d, $conf, $versao, $user_id, FALSE));
                $editado = TRUE;
            } else {
                $this->db->insert('form_responses', $this->cabecalho($d, $conf, $versao, $user_id, TRUE));
                $id = (int) $this->db->insert_id();
                $editado = FALSE;
            }

            $this->gravar_respostas($id, NULL, $conf['respostas_domicilio'], $conf['calculados']['domicilio']);
            foreach ($d['moradores'] as $m) {
                $this->db->insert('moradores', [
                    'form_response_id' => $id, 'client_id' => $m['client_id'], 'ordem' => $m['ordem'],
                    'nome' => $m['nome'] !== '' ? $m['nome'] : 'Morador ' . $m['ordem'],
                    'status' => $m['status'], 'justificativa' => $m['justificativa'],
                ]);
                $mid = (int) $this->db->insert_id();
                $this->gravar_respostas($id, $mid, $conf['respostas_moradores'][$m['client_id']] ?? [],
                    $conf['calculados']['moradores'][$m['client_id']] ?? []);
            }

            if ($this->db->trans_status() === FALSE) {
                throw new RuntimeException('Falha ao gravar a entrevista.');
            }
            $this->db->trans_commit();
        } catch (Exception $e) {
            $this->db->trans_rollback();
            throw $e;
        }
        return ['id' => $id, 'duplicado' => FALSE, 'editado' => $editado, 'conferencia' => $conf];
    }

    private function cabecalho(array $d, array $conf, $versao, $user_id, $novo) {
        $lat = $d['latitude']; $lng = $d['longitude'];
        if (($lat === NULL || $lng === NULL) && $conf['coordenadas']) { list($lat, $lng) = $conf['coordenadas']; }
        $c = [
            'questionnaire_id' => (int) $d['questionnaire_id'],
            'applied_by' => (int) $user_id,
            'latitude' => $lat, 'longitude' => $lng,
            'location_name' => $d['location_name'],
            'photo_path' => $d['photo_path'] ?: $conf['foto'],
            'photo_path_2' => $d['photo_path_2'],
            'audio_path' => $d['audio_path'],
            'consent_given' => $d['consent_given'] ? 't' : 'f',
            'sync_status' => 'synced',
            'started_at' => $d['started_at'],
            'completed_at' => $d['completed_at'] ?: date('Y-m-d H:i:s'),
            'edited_at' => $d['edited_at'],
            'origin' => 'app',
            'estrutura_versao_id' => (int) $versao->id,
            'calculados' => json_encode($conf['calculados'], JSON_UNESCAPED_UNICODE),
            'alertas' => json_encode($conf['alertas'], JSON_UNESCAPED_UNICODE),
            'revisao_status' => $conf['revisao'],
            'revisao_erros' => $conf['erros'] || $conf['divergencias']
                ? json_encode(['erros' => $conf['erros'], 'divergencias' => $conf['divergencias']], JSON_UNESCAPED_UNICODE) : NULL,
        ];
        if ($novo) { $c['client_id'] = $d['client_id']; }
        return $c;
    }

    private function gravar_respostas($fid, $mid, array $respostas, array $calculados) {
        foreach ($respostas as $codigo => $r) {
            $v = $r['valor'] ?? NULL;
            $this->db->insert('respostas_estruturadas', [
                'form_response_id' => $fid, 'morador_id' => $mid, 'codigo' => $codigo,
                'valor' => empty($r['nsnr']) ? json_encode($v, JSON_UNESCAPED_UNICODE) : NULL,
                'valor_numero' => (empty($r['nsnr']) && is_numeric($v) && abs((float) $v) < 1e13) ? round((float) $v, 2) : NULL,
                'nsnr' => !empty($r['nsnr']) ? 't' : 'f',
                'complementos' => !empty($r['complementos']) || isset($r['complemento'])
                    ? json_encode(['opcoes' => $r['complementos'] ?? new stdClass(), 'pergunta' => $r['complemento'] ?? NULL], JSON_UNESCAPED_UNICODE) : NULL,
            ]);
        }
        foreach ($calculados as $codigo => $c) {
            $v = $c['valor'];
            $this->db->insert('respostas_estruturadas', [
                'form_response_id' => $fid, 'morador_id' => $mid, 'codigo' => $codigo,
                'valor' => json_encode($v, JSON_UNESCAPED_UNICODE),
                'valor_numero' => is_numeric($v) && abs((float) $v) < 1e13 ? round((float) $v, 2) : NULL,
                'calculado' => 't', 'incompleto' => $c['incompleto'] ? 't' : 'f',
            ]);
        }
    }

    public function entrevistas($qid, $limite = 500, $offset = 0) {
        return $this->db->query(
            "SELECT fr.id, fr.client_id, fr.completed_at, fr.started_at, fr.latitude, fr.longitude, fr.revisao_status,
                    fr.alertas, u.full_name AS aplicador,
                    (SELECT COUNT(*) FROM moradores m WHERE m.form_response_id = fr.id) AS moradores
               FROM form_responses fr LEFT JOIN users u ON u.id = fr.applied_by
              WHERE fr.questionnaire_id = ?
              ORDER BY fr.completed_at DESC NULLS LAST, fr.id DESC
              LIMIT ? OFFSET ?", [(int) $qid, (int) $limite, (int) $offset])->result();
    }

    /** Entrevista completa: cabeçalho, definição da versão, respostas e moradores. */
    public function entrevista($id) {
        $fr = $this->db->query(
            "SELECT fr.*, u.full_name AS aplicador, q.title AS formulario, q.id AS qid
               FROM form_responses fr JOIN questionnaires q ON q.id = fr.questionnaire_id
               LEFT JOIN users u ON u.id = fr.applied_by
              WHERE fr.id = ? AND q.formato = 'estruturado'", [(int) $id])->row();
        if (!$fr) { return NULL; }
        $versao = $fr->estrutura_versao_id ? $this->versao_por_id($fr->estrutura_versao_id) : $this->versao($fr->qid);
        $moradores = $this->db->order_by('ordem')->get_where('moradores', ['form_response_id' => (int) $id])->result();
        $rows = $this->db->get_where('respostas_estruturadas', ['form_response_id' => (int) $id])->result();
        $dom = []; $por_morador = [];
        foreach ($rows as $r) {
            $resp = self::linha_para_resposta($r);
            if ($r->morador_id) { $por_morador[(int) $r->morador_id][$r->codigo] = $resp; } else { $dom[$r->codigo] = $resp; }
        }
        foreach ($moradores as $m) { $m->respostas = $por_morador[(int) $m->id] ?? []; }
        return ['cabecalho' => $fr, 'versao' => $versao, 'respostas' => $dom, 'moradores' => $moradores];
    }

    public static function linha_para_resposta($r) {
        $resp = ['valor' => $r->valor !== NULL ? json_decode($r->valor, TRUE) : NULL];
        if ($r->nsnr === 't' || $r->nsnr === TRUE) { $resp['nsnr'] = TRUE; }
        if ($r->calculado === 't' || $r->calculado === TRUE) {
            $resp['calculado'] = TRUE;
            $resp['incompleto'] = ($r->incompleto === 't' || $r->incompleto === TRUE);
        }
        if ($r->complementos) {
            $c = json_decode($r->complementos, TRUE);
            if (!empty($c['opcoes'])) { $resp['complementos'] = $c['opcoes']; }
            if (isset($c['pergunta'])) { $resp['complemento'] = $c['pergunta']; }
        }
        return $resp;
    }

    /** Todas as respostas de um formulário, para exportação: [fid => ['dom' => [...], 'moradores' => [mid => [...]]]]. */
    public function dados_exportacao($qid) {
        $entrevistas = $this->db->query(
            "SELECT fr.*, u.full_name AS aplicador, v.versao
               FROM form_responses fr LEFT JOIN users u ON u.id = fr.applied_by
               LEFT JOIN estrutura_versoes v ON v.id = fr.estrutura_versao_id
              WHERE fr.questionnaire_id = ? ORDER BY fr.completed_at, fr.id", [(int) $qid])->result();
        $moradores = $this->db->query(
            "SELECT m.* FROM moradores m JOIN form_responses fr ON fr.id = m.form_response_id
              WHERE fr.questionnaire_id = ? ORDER BY m.form_response_id, m.ordem", [(int) $qid])->result();
        $respostas = $this->db->query(
            "SELECT r.* FROM respostas_estruturadas r JOIN form_responses fr ON fr.id = r.form_response_id
              WHERE fr.questionnaire_id = ?", [(int) $qid])->result();
        $idx = [];
        foreach ($respostas as $r) {
            $chave = $r->morador_id ? 'm' . $r->morador_id : 'f' . $r->form_response_id;
            $idx[$chave][$r->codigo] = self::linha_para_resposta($r);
        }
        return ['entrevistas' => $entrevistas, 'moradores' => $moradores, 'respostas' => $idx];
    }

    /** Mídias citadas pela entrevista que não estão no servidor. */
    public function midias_faltando($id, array $respostas_foto = []) {
        $row = $this->db->get_where('form_responses', ['id' => (int) $id])->row();
        if (!$row) { return []; }
        $faltam = [];
        $arquivos = [['photos', $row->photo_path], ['photos', $row->photo_path_2], ['audios', $row->audio_path]];
        foreach ($respostas_foto as $f) { $arquivos[] = ['photos', $f]; }
        foreach ($arquivos as $a) {
            $nome = trim((string) $a[1]);
            if ($nome === '') { continue; }
            if (strpbrk($nome, '/\\') !== FALSE || !is_file(FCPATH . 'uploads/' . $a[0] . '/' . $nome)) {
                $faltam[] = $nome;
            }
        }
        return array_values(array_unique($faltam));
    }
}
