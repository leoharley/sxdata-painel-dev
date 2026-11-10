<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Relatórios municipais automatizados.
 *
 * Tabelas em sql/municipal_reports_migration.sql. Catálogo de eixos,
 * indicadores e gráficos em application/config/relatorio_catalogo.php.
 *
 * Situação do relatório:
 *   processing       conectores buscando os dados
 *   awaiting_review  dados prontos, nenhuma seção revisada ainda
 *   in_review        revisão em andamento
 *   approved         todas as seções incluídas aprovadas (revisão interna concluída)
 *   published        versão final aprovada e disponível ao cliente
 */
class Relatorio_municipal_model extends CI_Model {

    const ABRANGENCIAS = ['municipio', 'estado', 'brasil'];

    /** @var array|null */
    private $catalogo = null;

    public function __construct() {
        parent::__construct();
        // Rotas como a busca de municípios não carregam outro model que abra o banco
        $this->load->database();
    }

    // ------------------------------------------------------------
    // Catálogo
    // ------------------------------------------------------------

    public function catalogo() {
        if ($this->catalogo === null) {
            $this->config->load('relatorio_catalogo', TRUE);
            $c = $this->config->item('relatorio_catalogo');
            $this->catalogo = [
                'eixos'        => $c['relatorio_eixos'],
                'indicadores'  => $c['relatorio_indicadores'],
                'graficos'     => $c['relatorio_graficos'],
            ];
        }

        return $this->catalogo;
    }

    // ------------------------------------------------------------
    // Relatórios
    // ------------------------------------------------------------

    public function listar($filtros = []) {
        $this->db->select('r.*, u.full_name AS criado_por, p.name AS projeto_nome');
        $this->db->from('municipal_reports r');
        $this->db->join('users u', 'u.id = r.created_by', 'left');
        $this->db->join('projects p', 'p.id = r.project_id', 'left');

        if (!empty($filtros['uf'])) {
            $this->db->where('r.uf', strtoupper($filtros['uf']));
        }
        if (!empty($filtros['municipio'])) {
            // Curingas removidos: a busca é por trecho do nome
            $this->db->where('r.municipio_nome ILIKE', '%' . str_replace(['%', '_', '\\'], '', $filtros['municipio']) . '%');
        }
        if (!empty($filtros['periodo'])) {
            $this->db->where('r.periodo_referencia', $filtros['periodo']);
        }
        if (!empty($filtros['status'])) {
            $this->db->where('r.status', $filtros['status']);
        }

        $this->db->order_by('r.updated_at', 'DESC');
        $relatorios = $this->db->get()->result();

        foreach ($relatorios as $r) {
            $r->progresso = $this->progresso($r);
        }

        return $relatorios;
    }

    public function contadores() {
        $rows = $this->db->select('status, COUNT(*) AS total')
                         ->group_by('status')
                         ->get('municipal_reports')
                         ->result();

        $por_status = [];
        foreach ($rows as $row) {
            $por_status[$row->status] = (int) $row->total;
        }

        return [
            'total'          => array_sum($por_status),
            'processamento'  => $por_status['processing'] ?? 0,
            'revisao'        => ($por_status['awaiting_review'] ?? 0) + ($por_status['in_review'] ?? 0),
            'aprovados'      => ($por_status['approved'] ?? 0) + ($por_status['published'] ?? 0),
        ];
    }

    public function obter($id) {
        $this->db->select('r.*, u.full_name AS criado_por, p.name AS projeto_nome');
        $this->db->from('municipal_reports r');
        $this->db->join('users u', 'u.id = r.created_by', 'left');
        $this->db->join('projects p', 'p.id = r.project_id', 'left');
        $this->db->where('r.id', (int) $id);
        $r = $this->db->get()->row();

        if ($r) {
            $r->eixos_lista = json_decode($r->eixos, true) ?: [];
        }

        return $r;
    }

    /**
     * Cria o relatório com uma seção por eixo e os gráficos do catálogo.
     */
    public function criar($dados, $user_id) {
        $cat = $this->catalogo();

        // Mantém a ordem do catálogo, independente da ordem enviada
        $eixos = array_values(array_filter(array_keys($cat['eixos']), function ($e) use ($dados) {
            return in_array($e, $dados['eixos'], true);
        }));

        $now = date('Y-m-d H:i:s');

        $this->db->trans_begin();

        $this->db->insert('municipal_reports', [
            'municipio_nome'     => $dados['municipio_nome'],
            'uf'                 => strtoupper($dados['uf']),
            'codigo_ibge'        => (int) $dados['codigo_ibge'],
            'periodo_referencia' => $dados['periodo_referencia'],
            'eixos'              => json_encode($eixos),
            'project_id'         => $dados['project_id'] ?: null,
            'status'             => 'processing',
            'created_by'         => $user_id,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);
        $report_id = (int) $this->db->insert_id();

        foreach ($eixos as $ordem => $eixo) {
            $this->db->insert('report_sections', [
                'report_id' => $report_id,
                'eixo'      => $eixo,
                'titulo'    => $cat['eixos'][$eixo]['titulo'],
                'ordem'     => $ordem,
                'status'    => 'pending',
            ]);
            $section_id = (int) $this->db->insert_id();

            $ordem_grafico = 0;
            foreach ($cat['graficos'] as $chave => $g) {
                if ($g['eixo'] !== $eixo) {
                    continue;
                }
                $this->db->insert('report_charts', [
                    'report_id'  => $report_id,
                    'section_id' => $section_id,
                    'grafico'    => $chave,
                    'titulo'     => $g['titulo'],
                    'status'     => 'pending',
                    'ordem'      => $ordem_grafico++,
                ]);
            }
        }

        $this->evento($report_id, 'criado', 'Relatório criado', $user_id);

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();

        return $report_id;
    }

    public function excluir($id) {
        return $this->db->where('id', (int) $id)->delete('municipal_reports');
    }

    public function tocar($id) {
        $this->db->where('id', (int) $id)->update('municipal_reports', ['updated_at' => date('Y-m-d H:i:s')]);
    }

    public function definir_status($id, $status) {
        $this->db->where('id', (int) $id)->update('municipal_reports', [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Recalcula a situação a partir das seções. Não mexe em relatórios em
     * processamento: esses só avançam pela ação "Concluir processamento".
     */
    public function recalcular_status($id) {
        $r = $this->obter($id);
        if (!$r || $r->status === 'processing') {
            return;
        }

        $secoes = $this->secoes($id);
        $incluidas = array_filter($secoes, function ($s) { return $this->truthy($s->incluir_no_final); });

        $aprovadas = count(array_filter($incluidas, function ($s) { return $s->status === 'approved'; }));
        $tocadas   = count(array_filter($secoes, function ($s) { return $s->status !== 'pending'; }));

        if (count($incluidas) > 0 && $aprovadas === count($incluidas)) {
            $novo = 'approved';
        } elseif ($tocadas > 0) {
            $novo = 'in_review';
        } else {
            $novo = 'awaiting_review';
        }

        // Publicado continua publicado até algo mudar: quem chama este método
        // é sempre uma alteração, então a versão publicada fica para trás e a
        // próxima aprovação final gera outra versão.
        $this->definir_status($id, $novo);

        if ($novo === 'approved' && $r->status !== 'approved') {
            $this->evento($id, 'revisao_concluida', 'Revisão interna concluída: todas as seções aprovadas', null);
        }
    }

    /**
     * Percentual exibido na lista: fontes concluídas durante o
     * processamento; seções aprovadas depois.
     */
    public function progresso($r) {
        if ($r->status === 'published') {
            return 100;
        }

        if ($r->status === 'processing') {
            $fontes = $this->fontes($r->id);
            if (empty($fontes)) {
                return 0;
            }
            $prontas = count(array_filter($fontes, function ($f) { return $f->status !== 'pending'; }));
            return (int) round($prontas / count($fontes) * 100);
        }

        $secoes = array_filter($this->secoes($r->id), function ($s) { return $this->truthy($s->incluir_no_final); });
        if (empty($secoes)) {
            return 0;
        }
        $aprovadas = count(array_filter($secoes, function ($s) { return $s->status === 'approved'; }));

        return (int) round($aprovadas / count($secoes) * 100);
    }

    // ------------------------------------------------------------
    // Seções e gráficos
    // ------------------------------------------------------------

    public function secoes($report_id) {
        return $this->db->order_by('ordem', 'ASC')
                        ->get_where('report_sections', ['report_id' => (int) $report_id])
                        ->result();
    }

    public function secao($id) {
        return $this->db->get_where('report_sections', ['id' => (int) $id])->row();
    }

    public function secao_por_eixo($report_id, $eixo) {
        return $this->db->get_where('report_sections', ['report_id' => (int) $report_id, 'eixo' => $eixo])->row();
    }

    public function atualizar_secao($id, $dados) {
        return $this->db->where('id', (int) $id)->update('report_sections', $dados);
    }

    public function graficos($report_id, $section_id = null) {
        $this->db->where('report_id', (int) $report_id);
        if ($section_id !== null) {
            $this->db->where('section_id', (int) $section_id);
        }

        return $this->db->order_by('ordem', 'ASC')->get('report_charts')->result();
    }

    public function grafico($id) {
        return $this->db->get_where('report_charts', ['id' => (int) $id])->row();
    }

    public function atualizar_grafico($id, $dados) {
        return $this->db->where('id', (int) $id)->update('report_charts', $dados);
    }

    // ------------------------------------------------------------
    // Indicadores
    // ------------------------------------------------------------

    /**
     * @param array|null $chaves restringe aos indicadores informados
     * @return array chave do indicador => lista de linhas
     */
    public function indicadores_agrupados($report_id, $chaves = null) {
        $this->db->where('report_id', (int) $report_id);
        if (is_array($chaves)) {
            if (empty($chaves)) {
                return [];
            }
            $this->db->where_in('indicador', $chaves);
        }
        $rows = $this->db->order_by('indicador, abrangencia, periodo, categoria')->get('report_indicators')->result();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->indicador][] = $row;
        }

        return $out;
    }

    public function indicador($report_id, $id) {
        return $this->db->get_where('report_indicators', ['report_id' => (int) $report_id, 'id' => (int) $id])->row();
    }

    public function excluir_indicador($report_id, $id) {
        return $this->db->where('report_id', (int) $report_id)->where('id', (int) $id)->delete('report_indicators');
    }

    /**
     * Valida as linhas contra o catálogo e grava (insere ou substitui).
     *
     * Linha recusada não interrompe as demais: o motivo volta na lista de
     * avisos, para o conector ser corrigido.
     *
     * @return array ['gravadas' => int, 'avisos' => string[]]
     */
    public function gravar_indicadores($report_id, $linhas, $origem, $conector) {
        $cat = $this->catalogo()['indicadores'];
        $gravadas = 0;
        $avisos = [];

        $sql = 'INSERT INTO report_indicators
                    (report_id, indicador, abrangencia, periodo, categoria, valor, valor_texto,
                     unidade, fonte, periodo_fonte, origem, conector, atualizado_em)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (report_id, indicador, abrangencia, periodo, categoria) DO UPDATE SET
                    valor = EXCLUDED.valor,
                    valor_texto = EXCLUDED.valor_texto,
                    unidade = EXCLUDED.unidade,
                    fonte = EXCLUDED.fonte,
                    periodo_fonte = EXCLUDED.periodo_fonte,
                    origem = EXCLUDED.origem,
                    conector = EXCLUDED.conector,
                    atualizado_em = EXCLUDED.atualizado_em';

        foreach ($linhas as $i => $l) {
            $n = $i + 1;
            if (!is_array($l)) {
                $avisos[] = "Linha $n: formato inválido.";
                continue;
            }

            $chave = isset($l['indicador']) ? (string) $l['indicador'] : '';
            if (!isset($cat[$chave])) {
                $avisos[] = "Linha $n: indicador \"$chave\" não existe no catálogo.";
                continue;
            }

            $abrangencia = isset($l['abrangencia']) ? (string) $l['abrangencia'] : '';
            if (!in_array($abrangencia, self::ABRANGENCIAS, true)) {
                $avisos[] = "Linha $n ($chave): abrangência \"$abrangencia\" inválida (use municipio, estado ou brasil).";
                continue;
            }

            $tipo_texto = ($cat[$chave]['tipo'] ?? 'numero') === 'texto';
            $valor = $l['valor'] ?? null;
            $valor_texto = isset($l['valor_texto']) ? trim((string) $l['valor_texto']) : null;

            if ($valor !== null && $valor !== '') {
                if (is_string($valor)) {
                    // Aceita "1.234,5" (padrão brasileiro) e "1234.5"
                    $valor = preg_match('/^-?[\d.]+,\d+$/', $valor) ? str_replace(['.', ','], ['', '.'], $valor) : $valor;
                }
                if (!is_numeric($valor)) {
                    $avisos[] = "Linha $n ($chave): valor \"$valor\" não é numérico.";
                    continue;
                }
                $valor = (float) $valor;
            } else {
                $valor = null;
            }

            if ($valor === null && ($valor_texto === null || $valor_texto === '')) {
                $avisos[] = "Linha $n ($chave): sem valor.";
                continue;
            }
            if (!$tipo_texto && $valor === null) {
                $avisos[] = "Linha $n ($chave): indicador numérico sem valor numérico.";
                continue;
            }

            $this->db->query($sql, [
                (int) $report_id,
                $chave,
                $abrangencia,
                mb_substr(trim((string) ($l['periodo'] ?? '')), 0, 20),
                mb_substr(trim((string) ($l['categoria'] ?? '')), 0, 150),
                $valor,
                $valor_texto !== '' ? $valor_texto : null,
                isset($l['unidade']) ? mb_substr((string) $l['unidade'], 0, 40) : ($cat[$chave]['unidade'] ?? null),
                isset($l['fonte']) ? mb_substr((string) $l['fonte'], 0, 200) : ($cat[$chave]['fonte'] ?? null),
                isset($l['periodo_fonte']) ? mb_substr((string) $l['periodo_fonte'], 0, 40) : null,
                $origem,
                $conector !== null ? mb_substr((string) $conector, 0, 80) : null,
                date('Y-m-d H:i:s'),
            ]);
            $gravadas++;
        }

        if ($gravadas > 0) {
            $this->tocar($report_id);
        }

        return ['gravadas' => $gravadas, 'avisos' => $avisos];
    }

    // ------------------------------------------------------------
    // Fontes (status dos conectores)
    // ------------------------------------------------------------

    public function fontes($report_id) {
        return $this->db->order_by('id', 'ASC')->get_where('report_sources', ['report_id' => (int) $report_id])->result();
    }

    public function registrar_fonte($report_id, $conector, $rotulo, $status, $mensagem = null, $linhas = 0) {
        $sql = 'INSERT INTO report_sources (report_id, conector, rotulo, status, mensagem, linhas, executado_em)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (report_id, conector) DO UPDATE SET
                    rotulo = EXCLUDED.rotulo, status = EXCLUDED.status, mensagem = EXCLUDED.mensagem,
                    linhas = EXCLUDED.linhas, executado_em = EXCLUDED.executado_em';

        $this->db->query($sql, [
            (int) $report_id,
            mb_substr((string) $conector, 0, 80),
            $rotulo !== null ? mb_substr((string) $rotulo, 0, 150) : null,
            in_array($status, ['pending', 'ok', 'error'], true) ? $status : 'error',
            $mensagem,
            (int) $linhas,
            $status === 'pending' ? null : date('Y-m-d H:i:s'),
        ]);
    }

    // ------------------------------------------------------------
    // Linha do tempo
    // ------------------------------------------------------------

    public function evento($report_id, $tipo, $descricao, $user_id) {
        $this->db->insert('report_events', [
            'report_id'  => (int) $report_id,
            'tipo'       => $tipo,
            'descricao'  => $descricao,
            'user_id'    => $user_id ? (int) $user_id : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function eventos($report_id, $limite = 30) {
        $this->db->select('e.*, u.full_name AS usuario');
        $this->db->from('report_events e');
        $this->db->join('users u', 'u.id = e.user_id', 'left');
        $this->db->where('e.report_id', (int) $report_id);
        $this->db->order_by('e.created_at', 'DESC');
        $this->db->limit((int) $limite);

        return $this->db->get()->result();
    }

    /** Data mais recente de cada tipo de evento, para a linha do tempo da aprovação final. */
    public function marcos($report_id) {
        $rows = $this->db->select('tipo, MAX(created_at) AS quando')
                         ->where('report_id', (int) $report_id)
                         ->group_by('tipo')
                         ->get('report_events')
                         ->result();
        $out = [];
        foreach ($rows as $row) {
            $out[$row->tipo] = $row->quando;
        }

        return $out;
    }

    // ------------------------------------------------------------
    // Versões publicadas
    // ------------------------------------------------------------

    /**
     * Congela o conteúdo aprovado como nova versão e publica.
     *
     * @return int número da versão criada
     */
    public function publicar_versao($report_id, $conteudo, $observacoes, $user_id) {
        $r = $this->obter($report_id);
        $versao = (int) $r->versao_atual + 1;
        $conteudo['versao'] = $versao;

        $this->db->trans_begin();

        $this->db->insert('report_versions', [
            'report_id'          => (int) $report_id,
            'versao'             => $versao,
            'conteudo'           => json_encode($conteudo, JSON_UNESCAPED_UNICODE),
            'observacoes_finais' => $observacoes !== '' ? $observacoes : null,
            'aprovado_por'       => $user_id,
            'aprovado_em'        => date('Y-m-d H:i:s'),
        ]);

        $this->db->where('id', (int) $report_id)->update('municipal_reports', [
            'versao_atual' => $versao,
            'status'       => 'published',
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->evento($report_id, 'publicado', 'Versão ' . $versao . ' aprovada para o cliente', $user_id);

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }
        $this->db->trans_commit();

        return $versao;
    }

    public function versao($report_id, $versao) {
        $row = $this->db->get_where('report_versions', ['report_id' => (int) $report_id, 'versao' => (int) $versao])->row();
        if ($row) {
            $row->conteudo_dados = json_decode($row->conteudo, true);
        }

        return $row;
    }

    public function versoes($report_id) {
        $this->db->select('v.id, v.versao, v.aprovado_em, v.observacoes_finais, u.full_name AS aprovado_por_nome');
        $this->db->from('report_versions v');
        $this->db->join('users u', 'u.id = v.aprovado_por', 'left');
        $this->db->where('v.report_id', (int) $report_id);
        $this->db->order_by('v.versao', 'DESC');

        return $this->db->get()->result();
    }

    /**
     * Relatórios com versão publicada, dos projetos informados (painel do cliente).
     */
    public function publicados_dos_projetos($project_ids) {
        if (empty($project_ids)) {
            return [];
        }

        $this->db->select('r.id, r.municipio_nome, r.uf, r.periodo_referencia, r.versao_atual, p.name AS projeto_nome,
                           (SELECT MAX(v.aprovado_em) FROM report_versions v
                             WHERE v.report_id = r.id AND v.versao = r.versao_atual) AS publicado_em', FALSE);
        $this->db->from('municipal_reports r');
        $this->db->join('projects p', 'p.id = r.project_id', 'left');
        $this->db->where_in('r.project_id', array_map('intval', $project_ids));
        $this->db->where('r.versao_atual >', 0);
        $this->db->order_by('publicado_em', 'DESC');

        return $this->db->get()->result();
    }

    // ------------------------------------------------------------
    // Municípios
    // ------------------------------------------------------------

    public function buscar_municipios($termo, $uf = '', $limite = 20) {
        $this->db->select('codigo, nome, uf, codigo_uf');
        $this->db->from('ibge_municipios');
        if ($uf !== '') {
            $this->db->where('uf', strtoupper($uf));
        }
        if ($termo !== '') {
            $this->db->where('nome ILIKE', '%' . str_replace(['%', '_', '\\'], '', $termo) . '%');
        }
        $this->db->order_by('nome', 'ASC');
        $this->db->limit((int) $limite);

        return $this->db->get()->result();
    }

    public function municipios_carregados() {
        return $this->db->count_all('ibge_municipios') > 0;
    }

    private function truthy($value) {
        return $value === TRUE || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }
}
