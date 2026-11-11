<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Mapa integrado: contorno do município, indicadores do tema escolhido e
 * pontos de campo do projeto com as coletas vinculadas.
 *
 * Restrito à equipe (administrador e supervisor). Plano em
 * docs/PLANO_MAPA_INTEGRADO.md.
 *
 * Rotas: mapa-integrado/...
 */
class Mapa_integrado extends CI_Controller {

    /** Coletas por ponto no painel lateral (as mais recentes) */
    const COLETAS_POR_PONTO = 50;

    public function __construct() {
        parent::__construct();
        $this->load->library('session');

        if (!$this->session->userdata('admin_logged_in')) {
            redirect('auth/login');
        }

        $this->load->database();
        $this->load->library('geo_municipio');
        $this->load->library('mapa_temas');
        $this->load->model('Field_point_model', 'pontos');
    }

    public function index() {
        $this->equipe();

        $migrado = $this->pontos->disponivel();
        $this->load->model('Relatorio_municipal_model', 'relatorios');
        $cat = $this->relatorios->catalogo();

        $projetos = $this->db->select('id, name, status')->order_by('name', 'ASC')->get('projects')->result();

        $this->load->view('admin/header', ['title' => 'Mapa integrado - SXData']);
        $this->load->view('admin/mapa_integrado/index', [
            'migrado'    => $migrado,
            'projetos'   => $projetos,
            'temas'      => Mapa_temas::temas($cat['eixos']),
            'com_lista'  => $this->relatorios->municipios_carregados(),
            'conectores' => $this->config_conectores_js(),
            'csrf'       => $this->csrf(),
        ]);
        $this->load->view('admin/footer');
    }

    /** GET mapa-integrado/malha/{codigo}: GeoJSON do contorno (cache em application/cache/malhas). */
    public function malha($codigo = NULL) {
        $this->equipe(TRUE);
        $this->load->library('malha_municipal');

        $texto = $this->malha_municipal->obter((int) $codigo);
        if ($texto === NULL) {
            return $this->json(['ok' => FALSE, 'mensagem' => 'Malha do município indisponível (IBGE fora do ar ou código inválido).'], 404);
        }
        $this->output
             ->set_content_type('application/geo+json', 'utf-8')
             ->set_header('Cache-Control: private, max-age=86400')
             ->set_output($texto);
    }

    /**
     * GET mapa-integrado/indicadores/{codigo}?tema=
     *
     * Do relatório mais recente do município: publicado/aprovado primeiro;
     * senão o mais recente em qualquer status ("não revisado"). Sem
     * relatório, devolve o contexto para a tela consultar a fonte direto
     * (conectores JS, sem gravar nada).
     */
    public function indicadores($codigo = NULL) {
        $this->equipe(TRUE);
        $municipio = $this->municipio($codigo);
        if (!$municipio) {
            return $this->json(['ok' => FALSE, 'mensagem' => 'Município não encontrado.'], 404);
        }
        list($tema, $eixos_tema, $catalogo) = $this->tema((string) $this->input->get('tema'));
        if ($tema === NULL) {
            return $this->json(['ok' => FALSE, 'mensagem' => 'Tema inválido.'], 400);
        }

        $base = [
            'ok'        => TRUE,
            'municipio' => $municipio,
            'tema'      => $tema,
            'contexto'  => [
                'id' => 0, 'codigoIbge' => (string) $municipio['codigo'], 'codigoUf' => substr((string) $municipio['codigo'], 0, 2),
                'uf' => $municipio['uf'], 'municipio' => $municipio['nome'], 'periodo' => date('Y'), 'eixos' => $eixos_tema,
            ],
        ];

        $relatorio = $this->relatorio_mais_recente($municipio['codigo']);
        if (!$relatorio) {
            return $this->json($base + ['origem' => 'nenhum', 'relatorio' => NULL, 'itens' => []]);
        }

        $chaves = array_keys(array_filter($catalogo, function ($d) use ($eixos_tema) { return in_array($d['eixo'], $eixos_tema, TRUE); }));
        $linhas = [];
        foreach ($this->relatorios->indicadores_agrupados($relatorio->id, $chaves) as $lista) {
            $linhas = array_merge($linhas, $lista);
        }

        $this->json($base + [
            'origem'    => 'relatorio',
            'relatorio' => [
                'id'       => (int) $relatorio->id,
                'status'   => $relatorio->status,
                'revisado' => in_array($relatorio->status, ['published', 'approved'], TRUE),
                'periodo'  => $relatorio->periodo_referencia,
                'url'      => base_url('relatorios-automatizados/revisar/' . (int) $relatorio->id),
            ],
            'itens'     => $this->formatar(Mapa_temas::resumo($linhas, $eixos_tema, $catalogo)),
        ]);
    }

    /**
     * POST mapa-integrado/resumir: resume linhas trazidas pelos conectores JS
     * (consulta direta à fonte). Nada é gravado.
     */
    public function resumir() {
        $this->equipe(TRUE);
        if ($this->input->method() !== 'post') {
            return $this->json(['ok' => FALSE, 'mensagem' => 'Use POST.'], 405);
        }
        list($tema, $eixos_tema, $catalogo) = $this->tema((string) $this->input->post('tema'));
        if ($tema === NULL) {
            return $this->json(['ok' => FALSE, 'mensagem' => 'Tema inválido.'], 400);
        }
        $linhas = json_decode((string) $this->input->post('linhas', FALSE), TRUE);
        if (!is_array($linhas)) {
            return $this->json(['ok' => FALSE, 'mensagem' => 'Linhas inválidas.'], 400);
        }
        $linhas = array_slice(array_filter($linhas, 'is_array'), 0, 5000);

        $this->json(['ok' => TRUE, 'itens' => $this->formatar(Mapa_temas::resumo($linhas, $eixos_tema, $catalogo))]);
    }

    /** GET mapa-integrado/pontos/{codigo}?projeto=: GeoJSON dos pontos do município no projeto. */
    public function pontos($codigo = NULL) {
        $this->equipe(TRUE);
        if (!$this->pontos->disponivel()) {
            return $this->json(['ok' => FALSE, 'mensagem' => 'Rode sql/field_points_migration.sql no servidor.'], 503);
        }
        $projeto = (int) $this->input->get('projeto');
        if ($projeto <= 0) {
            return $this->json(['ok' => FALSE, 'mensagem' => 'Selecione o projeto.'], 400);
        }

        $features = [];
        $sem_coordenada = [];
        foreach ($this->pontos->listar([$projeto], (int) $codigo) as $p) {
            $props = [
                'id' => $p->id, 'nome' => $p->nome, 'tipo' => $p->tipo, 'coletas' => $p->coletas,
                'dentro_municipio' => $p->dentro_municipio, 'origem_coord' => $p->origem_coord,
                'criado_por' => $p->criado_por_nome, 'criado_em' => $p->criado_em,
            ];
            if ($p->latitude === NULL || $p->longitude === NULL) {
                $sem_coordenada[] = $props;
                continue;
            }
            $features[] = ['type' => 'Feature', 'properties' => $props,
                           'geometry' => ['type' => 'Point', 'coordinates' => [$p->longitude, $p->latitude]]];
        }

        $this->json(['ok' => TRUE, 'type' => 'FeatureCollection', 'features' => $features, 'sem_coordenada' => $sem_coordenada]);
    }

    /** GET mapa-integrado/ponto/{id}: ponto e coletas vinculadas (respostas, fotos, data, coletor). */
    public function ponto($id = NULL) {
        $this->equipe(TRUE);
        if (!$this->pontos->disponivel()) {
            return $this->json(['ok' => FALSE, 'mensagem' => 'Rode sql/field_points_migration.sql no servidor.'], 503);
        }
        $p = $this->pontos->obter((int) $id);
        if (!$p) {
            return $this->json(['ok' => FALSE, 'mensagem' => 'Ponto não encontrado.'], 404);
        }
        $p = $this->pontos->normalizar($p);

        $coletas = [];
        $total = 0;
        if ($this->pontos->vinculo_disponivel()) {
            $total = $this->db->where('field_point_id', $p->id)->count_all_results('form_responses');
            $this->load->model('Response_model');
            $rows = $this->db->select('fr.id, fr.completed_at, fr.respondent_name, fr.photo_path, fr.photo_path_2, fr.latitude, fr.longitude,
                                       q.title AS questionario, u.full_name AS coletor')
                             ->from('form_responses fr')
                             ->join('questionnaires q', 'q.id = fr.questionnaire_id', 'left')
                             ->join('users u', 'u.id = fr.applied_by', 'left')
                             ->where('fr.field_point_id', $p->id)
                             ->order_by('fr.completed_at', 'DESC')
                             ->limit(self::COLETAS_POR_PONTO)
                             ->get()->result();
            foreach ($rows as $r) {
                $coletas[] = [
                    'id'          => (int) $r->id,
                    'questionario'=> $r->questionario,
                    'data'        => $r->completed_at ? date('d/m/Y H:i', strtotime($r->completed_at)) : '',
                    'coletor'     => $r->coletor,
                    'respondente' => $r->respondent_name,
                    'fotos'       => $this->fotos($r),
                    'respostas'   => $this->respostas($r->id),
                    'url'         => base_url('responses/view/' . (int) $r->id),
                ];
            }
        }

        $this->json(['ok' => TRUE, 'ponto' => $p, 'coletas' => $coletas, 'total_coletas' => $total]);
    }

    /** GET mapa-integrado/kmz?projeto=&municipios=1713601,1721000: pontos do filtro + coletas vinculadas. */
    public function kmz() {
        $this->equipe();
        $projeto = (int) $this->input->get('projeto');
        $codigos = array_values(array_filter(array_map('intval', explode(',', (string) $this->input->get('municipios')))));
        if ($projeto <= 0 || !$codigos || !$this->pontos->disponivel()) {
            $this->session->set_flashdata('error', 'Selecione o projeto e pelo menos um município.');
            redirect('mapa-integrado');
            return;
        }

        $pontos = [];
        foreach ($codigos as $c) {
            $pontos = array_merge($pontos, $this->pontos->listar([$projeto], $c));
        }

        $coletas = [];
        $ids = array_column($pontos, 'id');
        if ($ids && $this->pontos->vinculo_disponivel()) {
            $coletas = $this->db->select('fr.*, q.title AS questionnaire_title, u.full_name AS applied_by_name')
                                ->from('form_responses fr')
                                ->join('questionnaires q', 'q.id = fr.questionnaire_id', 'left')
                                ->join('users u', 'u.id = fr.applied_by', 'left')
                                ->where_in('fr.field_point_id', $ids)
                                ->where('fr.latitude IS NOT NULL', NULL, FALSE)
                                ->where('fr.longitude IS NOT NULL', NULL, FALSE)
                                ->where('fr.completed_at IS NOT NULL', NULL, FALSE)
                                ->get()->result();
        }

        $nome = $this->db->select('name')->get_where('projects', ['id' => $projeto])->row();
        $this->load->library('kmz_generator');
        try {
            $this->kmz_generator->generate($coletas, 'Mapa integrado - ' . ($nome ? $nome->name : 'Projeto ' . $projeto),
                'mapa_integrado', ['points' => $pontos]);
        } catch (Throwable $e) {
            $this->session->set_flashdata('error', 'Erro ao gerar o KMZ: ' . $e->getMessage());
            redirect('mapa-integrado');
        }
    }

    // ------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------

    private function municipio($codigo) {
        $row = $this->db->get_where('ibge_municipios', ['codigo' => (int) $codigo])->row();
        return $row ? ['codigo' => (int) $row->codigo, 'nome' => $row->nome, 'uf' => $row->uf] : NULL;
    }

    /** @return array [chave do tema ou NULL, eixos do tema, catálogo de indicadores] */
    private function tema($chave) {
        $this->load->model('Relatorio_municipal_model', 'relatorios');
        $cat = $this->relatorios->catalogo();
        $temas = Mapa_temas::temas($cat['eixos']);
        if (!isset($temas[$chave])) {
            return [NULL, [], $cat['indicadores']];
        }
        return [$chave, $temas[$chave]['eixos'], $cat['indicadores']];
    }

    private function relatorio_mais_recente($codigo) {
        if (!$this->db->table_exists('municipal_reports')) {
            return NULL;
        }
        foreach ([['published', 'approved'], NULL] as $status) {
            $this->db->where('codigo_ibge', (int) $codigo);
            if ($status) {
                $this->db->where_in('status', $status);
            }
            $r = $this->db->order_by('updated_at', 'DESC')->order_by('id', 'DESC')->limit(1)->get('municipal_reports')->row();
            if ($r) {
                return $r;
            }
        }
        return NULL;
    }

    /** Acrescenta valor_formatado com a mesma formatação dos relatórios. */
    private function formatar(array $itens) {
        if (!isset($this->relatorio_graficos)) {
            $this->load->library('relatorio_graficos', ['indicadores' => $this->relatorios->catalogo()['indicadores']]);
        }
        foreach ($itens as &$i) {
            if ($i['tipo'] === 'numero' && $i['valor'] !== NULL) {
                $i['valor_formatado'] = $this->relatorio_graficos->formatar($i['valor'], $i['formato'], $i['unidade']);
            }
            foreach ($i['categorias'] as &$c) {
                $c['valor_formatado'] = $this->relatorio_graficos->formatar($c['valor'], $i['formato'], $i['unidade']);
            }
            unset($c);
        }
        unset($i);
        return $itens;
    }

    private function fotos($r) {
        $out = [];
        foreach (['photo_path', 'photo_path_2'] as $col) {
            $nome = trim((string) $r->$col);
            // nome com barra é caminho do aparelho, não arquivo do servidor
            if ($nome !== '' && strpbrk($nome, '/\\') === FALSE && is_file(FCPATH . 'uploads/photos/' . $nome)) {
                $out[] = base_url('uploads/photos/' . rawurlencode($nome));
            }
        }
        return $out;
    }

    private function respostas($form_response_id) {
        $out = [];
        foreach ($this->Response_model->get_answers($form_response_id) as $a) {
            $valor = '';
            if ($a->selected_options) {
                $ops = json_decode($a->selected_options, TRUE);
                $valor = is_array($ops) ? implode(', ', array_map(function ($o) {
                    return is_array($o) ? (string) ($o['option_text'] ?? $o['text'] ?? $o['value'] ?? json_encode($o, JSON_UNESCAPED_UNICODE)) : (string) $o;
                }, $ops)) : (string) $a->selected_options;
            } elseif ($a->response_text !== NULL && $a->response_text !== '') {
                $valor = $a->response_text;
            } elseif ($a->response_number !== NULL) {
                $valor = (string) $a->response_number;
            } elseif ($a->response_date) {
                $valor = date('d/m/Y', strtotime($a->response_date));
            } elseif ($a->response_datetime) {
                $valor = date('d/m/Y H:i', strtotime($a->response_datetime));
            }
            if ($valor !== '') {
                $out[] = ['pergunta' => (string) $a->question_text, 'resposta' => mb_substr($valor, 0, 500)];
            }
        }
        return $out;
    }

    private function config_conectores_js() {
        $this->config->load('relatorio_conectores', TRUE);
        $cfg = $this->config->item('relatorio_conectores');
        $js = [];
        foreach ($cfg['relatorio_conectores_js'] ?? [] as $arquivo) {
            $arquivo = basename((string) $arquivo);
            if (is_file(FCPATH . 'assets/js/relatorios/conectores/' . $arquivo)) {
                $js[] = $arquivo;
            }
        }
        return $js;
    }

    private function equipe($json = FALSE) {
        if (in_array($this->session->userdata('admin_role'), ['administrador', 'supervisor'], TRUE)) {
            return;
        }
        if ($json) {
            $this->json(['ok' => FALSE, 'mensagem' => 'Acesso restrito à equipe do SXData.'], 403);
            $this->output->_display();
            exit;
        }
        $this->session->set_flashdata('error', 'Acesso restrito à equipe do SXData.');
        redirect($this->session->userdata('admin_role') === 'cliente' ? 'client/dashboard' : 'dashboard');
        exit;
    }

    private function json($data, $status = 200) {
        $data['csrf'] = $this->security->get_csrf_hash();
        $this->output
             ->set_status_header($status)
             ->set_content_type('application/json', 'utf-8')
             ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    private function csrf() {
        return [
            'nome'  => $this->config->item('csrf_protection') ? $this->security->get_csrf_token_name() : '',
            'valor' => $this->config->item('csrf_protection') ? $this->security->get_csrf_hash() : '',
        ];
    }
}
