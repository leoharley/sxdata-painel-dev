<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Relatórios municipais automatizados (proposta de evolução, frentes C a F).
 *
 * Fluxo: novo -> processar (conectores gravam os dados) -> revisar
 * (conferência e moderação por seção e por gráfico) -> final (prévia e
 * aprovação) -> documento (versão entregue ao cliente).
 *
 * Restrito à equipe (administrador e supervisor), exceto o documento
 * publicado, que o cliente do projeto vinculado também acessa.
 *
 * Rotas: relatorios-automatizados/...
 */
class Relatorios_municipais extends CI_Controller {

    const UFS = ['AC' => 12, 'AL' => 27, 'AP' => 16, 'AM' => 13, 'BA' => 29, 'CE' => 23, 'DF' => 53, 'ES' => 32,
                 'GO' => 52, 'MA' => 21, 'MT' => 51, 'MS' => 50, 'MG' => 31, 'PA' => 15, 'PB' => 25, 'PR' => 41,
                 'PE' => 26, 'PI' => 22, 'RJ' => 33, 'RN' => 24, 'RS' => 43, 'RO' => 11, 'RR' => 14, 'SC' => 42,
                 'SP' => 35, 'SE' => 28, 'TO' => 17];

    public function __construct() {
        parent::__construct();
        $this->load->library('session');

        if (!$this->session->userdata('admin_logged_in')) {
            redirect('auth/login');
        }

        $this->load->helper('relatorio');
        $this->load->model('Relatorio_municipal_model', 'relatorios');
        $this->load->library('relatorio_graficos', ['indicadores' => $this->relatorios->catalogo()['indicadores']]);
    }

    // ============================================================
    // Lista e criação
    // ============================================================

    public function index() {
        $this->equipe();

        if (!$this->db->table_exists('municipal_reports')) {
            $this->exibir('admin/relatorios_municipais/migracao', ['title' => 'Relatórios Automatizados - SXData']);
            return;
        }

        $filtros = [
            'uf'        => (string) $this->input->get('uf'),
            'municipio' => trim((string) $this->input->get('municipio')),
            'periodo'   => trim((string) $this->input->get('periodo')),
            'status'    => (string) $this->input->get('status'),
        ];

        $this->exibir('admin/relatorios_municipais/index', [
            'title'      => 'Relatórios Automatizados - SXData',
            'relatorios' => $this->relatorios->listar($filtros),
            'contadores' => $this->relatorios->contadores(),
            'filtros'    => $filtros,
            'ufs'        => array_keys(self::UFS),
        ]);
    }

    public function novo() {
        $this->equipe();
        $this->load->model('Project_model');

        $this->exibir('admin/relatorios_municipais/novo', [
            'title'      => 'Gerar relatório municipal - SXData',
            'eixos'      => $this->relatorios->catalogo()['eixos'],
            'ufs'        => array_keys(self::UFS),
            'projetos'   => $this->Project_model->get_for_select(),
            'com_lista'  => $this->relatorios->municipios_carregados(),
            'conectores' => $this->conectores_disponiveis(),
        ]);
    }

    public function criar() {
        $this->equipe();
        if ($this->input->method() !== 'post') {
            redirect('relatorios-automatizados/novo');
            return;
        }

        $eixos_validos = array_keys($this->relatorios->catalogo()['eixos']);
        $eixos = array_values(array_intersect((array) $this->input->post('eixos'), $eixos_validos));

        $dados = [
            'municipio_nome'     => trim((string) $this->input->post('municipio_nome')),
            'uf'                 => strtoupper(trim((string) $this->input->post('uf'))),
            'codigo_ibge'        => preg_replace('/\D/', '', (string) $this->input->post('codigo_ibge')),
            'periodo_referencia' => trim((string) $this->input->post('periodo_referencia')),
            'eixos'              => $eixos,
            'project_id'         => (int) $this->input->post('project_id'),
        ];

        $erros = [];
        if ($dados['municipio_nome'] === '' || mb_strlen($dados['municipio_nome']) > 150) {
            $erros[] = 'Informe o município.';
        }
        if (!isset(self::UFS[$dados['uf']])) {
            $erros[] = 'Selecione a UF.';
        }
        if (!preg_match('/^\d{7}$/', $dados['codigo_ibge'])) {
            $erros[] = 'O código IBGE do município tem 7 dígitos.';
        } elseif (isset(self::UFS[$dados['uf']]) && (int) substr($dados['codigo_ibge'], 0, 2) !== self::UFS[$dados['uf']]) {
            // Os dois primeiros dígitos do código IBGE são o código da UF
            $erros[] = 'O código IBGE não pertence à UF selecionada.';
        }
        if ($dados['periodo_referencia'] === '' || mb_strlen($dados['periodo_referencia']) > 20) {
            $erros[] = 'Informe o período de referência.';
        }
        if (empty($eixos)) {
            $erros[] = 'Selecione pelo menos um eixo temático.';
        }

        if (!empty($erros)) {
            $this->session->set_flashdata('error', implode(' ', $erros));
            redirect('relatorios-automatizados/novo');
            return;
        }

        $id = $this->relatorios->criar($dados, $this->usuario());
        if (!$id) {
            $this->session->set_flashdata('error', 'Não foi possível criar o relatório.');
            redirect('relatorios-automatizados/novo');
            return;
        }

        redirect('relatorios-automatizados/processar/' . $id);
    }

    /** Busca de municípios para o formulário (JSON). */
    public function municipios() {
        $this->equipe();
        $lista = $this->relatorios->buscar_municipios(
            trim((string) $this->input->get('q')),
            (string) $this->input->get('uf')
        );
        $this->json(['ok' => true, 'municipios' => $lista]);
    }

    public function excluir($id) {
        $this->equipe();
        if ($this->input->method() !== 'post' || $this->session->userdata('admin_role') !== 'administrador') {
            $this->session->set_flashdata('error', 'Somente administradores podem excluir relatórios.');
            redirect('relatorios-automatizados');
            return;
        }

        $r = $this->obter_ou_404($id);
        $this->relatorios->excluir($r->id);
        $this->session->set_flashdata('success', 'Relatório de ' . $r->municipio_nome . ' excluído.');
        redirect('relatorios-automatizados');
    }

    // ============================================================
    // Processamento (conectores)
    // ============================================================

    public function processar($id) {
        $this->equipe();
        $r = $this->obter_ou_404($id);

        $this->exibir('admin/relatorios_municipais/processar', [
            'title'      => 'Processamento - ' . $r->municipio_nome . ' - SXData',
            'r'          => $r,
            'eixos'      => $this->relatorios->catalogo()['eixos'],
            'fontes'     => $this->relatorios->fontes($r->id),
            'conectores' => $this->conectores_disponiveis($r->eixos_lista),
            'contexto'   => $this->contexto_conector($r),
            'csrf'       => $this->csrf(),
        ]);
    }

    /** Recebe um lote de linhas de um conector JavaScript (JSON). */
    public function receber($id) {
        $this->equipe(true);
        $r = $this->obter_ou_404($id, true);

        $linhas = json_decode((string) $this->input->post('dados'), true);
        $conector = mb_substr(trim((string) $this->input->post('conector')), 0, 80);
        $rotulo = mb_substr(trim((string) $this->input->post('rotulo')), 0, 150);

        if (!is_array($linhas) || $conector === '') {
            $this->json(['ok' => false, 'mensagem' => 'Lote inválido.'], 400);
            return;
        }

        $res = $this->relatorios->gravar_indicadores($r->id, $linhas, 'api', $conector);

        if ($this->input->post('ultimo') === '1') {
            $total = $this->db->where(['report_id' => $r->id, 'conector' => $conector])->count_all_results('report_indicators');
            $msg = count($res['avisos']) > 0 ? count($res['avisos']) . ' linha(s) recusada(s) no último lote.' : null;
            $this->relatorios->registrar_fonte($r->id, $conector, $rotulo, 'ok', $msg, $total);
        }

        $this->json(['ok' => true, 'gravadas' => $res['gravadas'], 'avisos' => array_slice($res['avisos'], 0, 20)]);
    }

    /** Registra o status de um conector JavaScript (falha ou fonte sem dados). */
    public function fonte($id) {
        $this->equipe(true);
        $r = $this->obter_ou_404($id, true);

        $this->relatorios->registrar_fonte(
            $r->id,
            mb_substr(trim((string) $this->input->post('conector')), 0, 80),
            mb_substr(trim((string) $this->input->post('rotulo')), 0, 150),
            $this->input->post('status') === 'ok' ? 'ok' : 'error',
            mb_substr(trim((string) $this->input->post('mensagem')), 0, 1000),
            0
        );

        $this->json(['ok' => true]);
    }

    /** Executa os conectores PHP (e-SUS e fontes sem acesso pelo navegador). */
    public function executar_servidor($id) {
        $this->equipe(true);
        $r = $this->obter_ou_404($id, true);

        $resultados = [];
        foreach ($this->conectores_php() as $c) {
            if (!array_intersect($c->eixos(), $r->eixos_lista)) {
                continue;
            }
            try {
                $linhas = $c->executar($r);
                $res = $this->relatorios->gravar_indicadores($r->id, is_array($linhas) ? $linhas : [], 'api', $c->chave());
                $msg = count($res['avisos']) > 0 ? count($res['avisos']) . ' linha(s) recusada(s).' : null;
                $this->relatorios->registrar_fonte($r->id, $c->chave(), $c->rotulo(), 'ok', $msg, $res['gravadas']);
                $resultados[] = ['chave' => $c->chave(), 'rotulo' => $c->rotulo(), 'status' => 'ok',
                                 'linhas' => $res['gravadas'], 'avisos' => array_slice($res['avisos'], 0, 20)];
            } catch (Throwable $e) {
                log_message('error', 'Conector ' . $c->chave() . ' falhou: ' . $e->getMessage());
                $this->relatorios->registrar_fonte($r->id, $c->chave(), $c->rotulo(), 'error', $e->getMessage(), 0);
                $resultados[] = ['chave' => $c->chave(), 'rotulo' => $c->rotulo(), 'status' => 'error', 'mensagem' => $e->getMessage()];
            }
        }

        $this->json(['ok' => true, 'conectores' => $resultados]);
    }

    public function concluir_processamento($id) {
        $this->equipe();
        $r = $this->obter_ou_404($id);
        if ($this->input->method() !== 'post') {
            redirect('relatorios-automatizados/processar/' . $r->id);
            return;
        }

        $this->relatorios->definir_status($r->id, 'awaiting_review');
        // Reprocessamento de relatório já em revisão volta para o estado
        // correspondente às seções, sem perder o que foi revisado
        $this->relatorios->recalcular_status($r->id);
        $this->relatorios->evento($r->id, 'processado', 'Processamento concluído; relatório preliminar disponível', $this->usuario());

        $this->session->set_flashdata('success', 'Relatório preliminar gerado. Confira cada seção.');
        redirect('relatorios-automatizados/revisar/' . $r->id);
    }

    /** Volta à etapa de processamento para rodar os conectores de novo. */
    public function reprocessar($id) {
        $this->equipe();
        $r = $this->obter_ou_404($id);
        if ($this->input->method() === 'post') {
            $this->relatorios->definir_status($r->id, 'processing');
            $this->relatorios->evento($r->id, 'reprocessamento', 'Dados enviados para nova consulta às fontes', $this->usuario());
        }
        redirect('relatorios-automatizados/processar/' . $r->id);
    }

    // ============================================================
    // Complementação manual dos dados
    // ============================================================

    public function dados($id) {
        $this->equipe();
        $r = $this->obter_ou_404($id);
        $cat = $this->relatorios->catalogo();

        $indicadores = array_filter($cat['indicadores'], function ($ind) use ($r) {
            return in_array($ind['eixo'], $r->eixos_lista, true);
        });

        $this->exibir('admin/relatorios_municipais/dados', [
            'title'       => 'Dados - ' . $r->municipio_nome . ' - SXData',
            'r'           => $r,
            'eixos'       => $cat['eixos'],
            'indicadores' => $indicadores,
            'grupos'      => $this->relatorios->indicadores_agrupados($r->id, array_keys($indicadores)),
            'eixo_aberto' => (string) $this->input->get('eixo'),
        ]);
    }

    public function salvar_dado($id) {
        $this->equipe();
        $r = $this->obter_ou_404($id);
        if ($this->input->method() !== 'post') {
            redirect('relatorios-automatizados/dados/' . $r->id);
            return;
        }

        $linha = [
            'indicador'     => (string) $this->input->post('indicador'),
            'abrangencia'   => (string) $this->input->post('abrangencia'),
            'periodo'       => (string) $this->input->post('periodo'),
            'categoria'     => (string) $this->input->post('categoria'),
            'valor'         => trim((string) $this->input->post('valor')),
            'valor_texto'   => (string) $this->input->post('valor_texto'),
            'fonte'         => trim((string) $this->input->post('fonte')),
            'periodo_fonte' => trim((string) $this->input->post('periodo_fonte')),
        ];
        if ($linha['fonte'] === '') {
            unset($linha['fonte']); // usa a fonte do catálogo
        }

        $res = $this->relatorios->gravar_indicadores($r->id, [$linha], 'manual', 'manual');
        if ($res['gravadas'] === 1) {
            $this->relatorios->evento($r->id, 'dado_manual', 'Dado complementado manualmente: ' . $linha['indicador'], $this->usuario());
            $this->session->set_flashdata('success', 'Dado gravado.');
        } else {
            $this->session->set_flashdata('error', implode(' ', $res['avisos']));
        }

        $eixo = $this->relatorios->catalogo()['indicadores'][$linha['indicador']]['eixo'] ?? '';
        redirect('relatorios-automatizados/dados/' . $r->id . '?eixo=' . urlencode($eixo));
    }

    public function excluir_dado($id, $indicador_id) {
        $this->equipe();
        $r = $this->obter_ou_404($id);
        $linha = $this->relatorios->indicador($r->id, $indicador_id);

        if ($this->input->method() === 'post' && $linha) {
            $this->relatorios->excluir_indicador($r->id, $linha->id);
            $this->relatorios->evento($r->id, 'dado_excluido', 'Dado excluído: ' . $linha->indicador, $this->usuario());
            $this->session->set_flashdata('success', 'Dado excluído.');
        }

        $eixo = $linha ? ($this->relatorios->catalogo()['indicadores'][$linha->indicador]['eixo'] ?? '') : '';
        redirect('relatorios-automatizados/dados/' . $r->id . '?eixo=' . urlencode($eixo));
    }

    // ============================================================
    // Revisão e moderação
    // ============================================================

    public function revisar($id, $eixo = null) {
        $this->equipe();
        $r = $this->obter_ou_404($id);

        if ($r->status === 'processing') {
            $this->session->set_flashdata('error', 'Conclua o processamento antes de revisar.');
            redirect('relatorios-automatizados/processar/' . $r->id);
            return;
        }

        $secoes = $this->relatorios->secoes($r->id);
        if (empty($secoes)) {
            show_404();
            return;
        }

        $atual = null;
        foreach ($secoes as $s) {
            if ($eixo !== null && $s->eixo === $eixo) {
                $atual = $s;
            }
        }
        if ($atual === null) {
            // Primeira seção ainda não aprovada
            foreach ($secoes as $s) {
                if ($atual === null && $s->status !== 'approved') {
                    $atual = $s;
                }
            }
            $atual = $atual ?: $secoes[0];
        }

        $grupos = $this->relatorios->indicadores_agrupados($r->id);

        $resumo = [];
        foreach ($secoes as $s) {
            $graficos = $this->relatorios->graficos($r->id, $s->id);
            $resumo[$s->id] = [
                // Mesma regra da aprovação da seção: gráfico fora do final não bloqueia
                'pendentes' => count(array_filter($graficos, function ($g) {
                    return in_array($g->status, ['pending', 'adjustment_requested'], true) && $this->truthy($g->incluir_no_final);
                })),
                'total'     => count($graficos),
            ];
        }

        $this->exibir('admin/relatorios_municipais/revisar', [
            'title'   => 'Revisão - ' . $r->municipio_nome . ' - SXData',
            'r'       => $r,
            'secoes'  => $secoes,
            'resumo'  => $resumo,
            'atual'   => $atual,
            'conteudo'=> $this->montar_secao($r, $atual, $grupos, false),
            'eixos'   => $this->relatorios->catalogo()['eixos'],
        ]);
    }

    public function salvar_secao($id, $section_id) {
        $this->equipe();
        $r = $this->obter_ou_404($id);
        $secao = $this->relatorios->secao($section_id);

        if ($this->input->method() !== 'post' || !$secao || (int) $secao->report_id !== (int) $r->id) {
            redirect('relatorios-automatizados/revisar/' . $r->id);
            return;
        }

        $acao = (string) $this->input->post('acao');
        $checklist = array_values(array_intersect((array) $this->input->post('checklist'), ['fonte', 'periodo', 'texto']));
        $incluir = $this->input->post('incluir_no_final') === '1';

        $dados = [
            'texto'                => trim((string) $this->input->post('texto')),
            'observacoes_internas' => trim((string) $this->input->post('observacoes_internas')),
            'checklist'            => json_encode($checklist),
            'incluir_no_final'     => $incluir,
            'revisado_por'         => $this->usuario(),
            'revisado_em'          => date('Y-m-d H:i:s'),
        ];

        if ($acao === 'aprovar') {
            $pendentes = array_filter($this->relatorios->graficos($r->id, $secao->id), function ($g) {
                return in_array($g->status, ['pending', 'adjustment_requested'], true) && $this->truthy($g->incluir_no_final);
            });

            if (count($checklist) < 3) {
                $this->session->set_flashdata('error', 'Para aprovar, marque os três itens do checklist de revisão.');
                $dados['status'] = $secao->status === 'pending' ? 'in_review' : $secao->status;
            } elseif (!empty($pendentes)) {
                $this->session->set_flashdata('error', 'Há ' . count($pendentes) . ' gráfico(s) sem decisão nesta seção. Aprove ou rejeite cada um antes de aprovar a seção.');
                $dados['status'] = $secao->status === 'pending' ? 'in_review' : $secao->status;
            } else {
                $dados['status'] = 'approved';
                $this->relatorios->evento($r->id, 'secao_aprovada', 'Seção aprovada: ' . $secao->titulo, $this->usuario());
                $this->session->set_flashdata('success', 'Seção "' . $secao->titulo . '" aprovada.');
            }
        } elseif ($acao === 'ajuste') {
            $dados['status'] = 'adjustment_requested';
            $this->relatorios->evento($r->id, 'ajuste_solicitado', 'Ajuste solicitado na seção ' . $secao->titulo, $this->usuario());
            $this->session->set_flashdata('success', 'Ajuste registrado na seção.');
        } else {
            // Salvar: aprovada editada volta para revisão
            $dados['status'] = $secao->status === 'pending' || $secao->status === 'approved' ? 'in_review' : $secao->status;
            $this->session->set_flashdata('success', 'Revisão salva.');
        }

        $this->relatorios->atualizar_secao($secao->id, $dados);
        $this->relatorios->recalcular_status($r->id);

        $proximo = $secao->eixo;
        if (($dados['status'] ?? '') === 'approved') {
            // Segue para a próxima seção ainda não aprovada
            foreach ($this->relatorios->secoes($r->id) as $s) {
                if ($s->status !== 'approved') {
                    $proximo = $s->eixo;
                    break;
                }
            }
        }

        redirect('relatorios-automatizados/revisar/' . $r->id . '/' . $proximo);
    }

    public function salvar_grafico($id, $chart_id) {
        $this->equipe();
        $r = $this->obter_ou_404($id);
        $grafico = $this->relatorios->grafico($chart_id);

        if ($this->input->method() !== 'post' || !$grafico || (int) $grafico->report_id !== (int) $r->id) {
            redirect('relatorios-automatizados/revisar/' . $r->id);
            return;
        }

        $secao = $this->relatorios->secao($grafico->section_id);
        $acao = (string) $this->input->post('acao');
        $status_por_acao = ['aprovar' => 'approved', 'ajuste' => 'adjustment_requested', 'rejeitar' => 'rejected'];

        $dados = [
            'texto'                => trim((string) $this->input->post('texto')),
            'observacoes_internas' => trim((string) $this->input->post('observacoes_internas')),
            'incluir_no_final'     => $this->input->post('incluir_no_final') === '1',
            'revisado_por'         => $this->usuario(),
            'revisado_em'          => date('Y-m-d H:i:s'),
        ];
        if (isset($status_por_acao[$acao])) {
            $dados['status'] = $status_por_acao[$acao];
        }

        $this->relatorios->atualizar_grafico($grafico->id, $dados);

        $descricao = [
            'aprovar'  => 'Gráfico aprovado',
            'ajuste'   => 'Ajuste solicitado no gráfico',
            'rejeitar' => 'Gráfico rejeitado',
        ][$acao] ?? null;
        if ($descricao) {
            $this->relatorios->evento($r->id, 'grafico_' . $acao, $descricao . ': ' . $grafico->titulo, $this->usuario());
        }

        // Mudança num gráfico de seção já aprovada reabre a seção
        if ($secao && $secao->status === 'approved' && $acao !== 'aprovar') {
            $this->relatorios->atualizar_secao($secao->id, ['status' => 'in_review']);
        } elseif ($secao && $secao->status === 'pending') {
            $this->relatorios->atualizar_secao($secao->id, ['status' => 'in_review']);
        }
        $this->relatorios->recalcular_status($r->id);

        $this->session->set_flashdata('success', $descricao ? $descricao . '.' : 'Gráfico salvo.');
        redirect('relatorios-automatizados/revisar/' . $r->id . '/' . ($secao ? $secao->eixo : '') . '#grafico-' . $grafico->id);
    }

    // ============================================================
    // Relatório final
    // ============================================================

    public function aprovacao($id) {
        $this->equipe();
        $r = $this->obter_ou_404($id);

        if (!in_array($r->status, ['approved', 'published'], true)) {
            $this->session->set_flashdata('error', 'O relatório final fica disponível quando todas as seções incluídas forem aprovadas.');
            redirect('relatorios-automatizados/revisar/' . $r->id);
            return;
        }

        $conteudo = $this->montar_conteudo($r);
        $n_graficos = 0;
        foreach ($conteudo['secoes'] as $s) {
            $n_graficos += count($s['graficos']);
        }

        $this->exibir('admin/relatorios_municipais/aprovacao', [
            'title'      => 'Aprovação final - ' . $r->municipio_nome . ' - SXData',
            'r'          => $r,
            'conteudo'   => $conteudo,
            'n_graficos' => $n_graficos,
            'versoes'    => $this->relatorios->versoes($r->id),
            'marcos'     => $this->relatorios->marcos($r->id),
        ]);
    }

    public function aprovar_final($id) {
        $this->equipe();
        $r = $this->obter_ou_404($id);

        if ($this->input->method() !== 'post') {
            redirect('relatorios-automatizados/aprovacao/' . $r->id);
            return;
        }
        if ($r->status !== 'approved') {
            $this->session->set_flashdata('error', $r->status === 'published'
                ? 'Esta versão já foi aprovada. Altere o conteúdo na revisão para gerar uma nova.'
                : 'Há seções pendentes de aprovação.');
            redirect('relatorios-automatizados/aprovacao/' . $r->id);
            return;
        }

        $conteudo = $this->montar_conteudo($r);
        if (empty($conteudo['secoes'])) {
            $this->session->set_flashdata('error', 'Nenhuma seção marcada para o relatório final.');
            redirect('relatorios-automatizados/aprovacao/' . $r->id);
            return;
        }

        $versao = $this->relatorios->publicar_versao($r->id, $conteudo, trim((string) $this->input->post('observacoes_finais')), $this->usuario());

        $this->session->set_flashdata($versao ? 'success' : 'error', $versao
            ? 'Versão ' . $versao . ' aprovada e disponível para o cliente.'
            : 'Não foi possível aprovar o relatório.');
        redirect('relatorios-automatizados/aprovacao/' . $r->id);
    }

    /**
     * Documento para leitura, impressão e PDF.
     *   sem versão: prévia do conteúdo aprovado (só a equipe)
     *   com versão: versão publicada (equipe ou cliente do projeto)
     */
    public function documento($id, $versao = null) {
        $r = $this->relatorios->obter($id);
        if (!$r) {
            show_404();
            return;
        }

        $equipe = $this->eh_equipe();

        if ($versao === null) {
            if (!$equipe) {
                show_404();
                return;
            }
            $conteudo = $this->montar_conteudo($r);
            $publicado = false;
        } else {
            $v = $this->relatorios->versao($r->id, $versao);
            if (!$v || (!$equipe && !$this->cliente_pode_ver($r))) {
                show_404();
                return;
            }
            $conteudo = $v->conteudo_dados;
            $conteudo['aprovado_em'] = $v->aprovado_em;
            $publicado = true;
        }

        $this->load->view('admin/relatorios_municipais/documento', [
            'conteudo'  => $conteudo,
            'publicado' => $publicado,
            'equipe'    => $equipe,
            // Prévia dentro da Aprovação final: miniaturas, páginas e zoom (F05)
            'visualizador' => $this->input->get('visualizador') === '1',
        ]);
    }

    // ============================================================
    // Montagem do conteúdo
    // ============================================================

    /**
     * Dados de uma seção: KPIs, informações em texto e gráficos.
     *
     * @param bool $somente_final apenas gráficos aprovados e incluídos (relatório final)
     */
    private function montar_secao($r, $secao, $grupos, $somente_final) {
        $cat = $this->relatorios->catalogo();
        $ctx = ['municipio' => $r->municipio_nome, 'uf' => $r->uf];

        $graficos = [];
        $sugestoes = [];
        foreach ($this->relatorios->graficos($r->id, $secao->id) as $c) {
            $def = $cat['graficos'][$c->grafico] ?? null;
            if (!$def) {
                continue;
            }
            if ($somente_final && (!$this->truthy($c->incluir_no_final) || $c->status !== 'approved')) {
                continue;
            }

            $dados = $this->relatorio_graficos->montar($def, $grupos, $ctx);
            if ($somente_final && $dados['vazio']) {
                continue;
            }

            $sugestao = $this->relatorio_graficos->texto_sugerido($dados, $ctx);
            if ($sugestao !== '') {
                $sugestoes[] = $sugestao;
            }

            $graficos[] = [
                'id'          => (int) $c->id,
                'titulo'      => $c->titulo,
                'status'      => $c->status,
                'texto'       => $c->texto,
                'sugestao'    => $sugestao,
                'observacoes' => $c->observacoes_internas,
                'incluir'     => $this->truthy($c->incluir_no_final),
                'dados'       => $dados,
            ];
        }

        $eixo = $cat['eixos'][$secao->eixo] ?? ['titulo' => $secao->titulo, 'icone' => 'fa-file-alt', 'kpis' => []];

        return [
            'secao'        => $secao,
            'eixo'         => $secao->eixo,
            'titulo'       => $secao->titulo,
            'icone'        => $eixo['icone'],
            'kpis'         => $this->relatorio_graficos->kpis($eixo['kpis'], $grupos),
            'qualitativos' => $this->relatorio_graficos->qualitativos($secao->eixo, $grupos),
            'graficos'     => $graficos,
            // Sugestão para a seção: os primeiros destaques dos gráficos
            'sugestao'     => implode(' ', array_slice($sugestoes, 0, 3)),
            'checklist'    => json_decode((string) $secao->checklist, true) ?: [],
        ];
    }

    /**
     * Conteúdo do relatório final: só seções aprovadas e incluídas, só
     * gráficos aprovados e incluídos. Observações internas nunca entram.
     * É o que fica congelado ao publicar uma versão.
     */
    private function montar_conteudo($r) {
        $grupos = $this->relatorios->indicadores_agrupados($r->id);
        $secoes = [];
        $fontes = [];

        foreach ($this->relatorios->secoes($r->id) as $s) {
            if ($s->status !== 'approved' || !$this->truthy($s->incluir_no_final)) {
                continue;
            }
            $m = $this->montar_secao($r, $s, $grupos, true);

            $graficos = array_map(function ($g) use (&$fontes) {
                foreach ($g['dados']['fontes'] as $f) {
                    $fontes[$f] = true;
                }
                return ['titulo' => $g['titulo'], 'texto' => (string) $g['texto'], 'dados' => $g['dados']];
            }, $m['graficos']);

            foreach (array_merge($m['kpis'], $m['qualitativos']) as $item) {
                if (!empty($item['fonte'])) {
                    $fontes[$item['fonte']] = true;
                }
            }

            $secoes[] = [
                'eixo'         => $s->eixo,
                'titulo'       => $s->titulo,
                'icone'        => $m['icone'],
                'texto'        => (string) $s->texto,
                'kpis'         => $m['kpis'],
                'qualitativos' => $m['qualitativos'],
                'graficos'     => $graficos,
            ];
        }

        $fontes = array_keys($fontes);
        sort($fontes);

        return [
            'relatorio_id' => (int) $r->id,
            'municipio'    => $r->municipio_nome,
            'uf'           => $r->uf,
            'codigo_ibge'  => (int) $r->codigo_ibge,
            'periodo'      => $r->periodo_referencia,
            'projeto'      => $r->projeto_nome,
            'gerado_em'    => date('Y-m-d H:i:s'),
            'secoes'       => $secoes,
            'fontes'       => $fontes,
        ];
    }

    // ============================================================
    // Conectores
    // ============================================================

    /** Conectores JS registrados cujo arquivo existe, e conectores PHP. */
    private function conectores_disponiveis($eixos = null) {
        $this->config->load('relatorio_conectores', TRUE);
        $cfg = $this->config->item('relatorio_conectores');

        $js = [];
        foreach ($cfg['relatorio_conectores_js'] ?? [] as $arquivo) {
            $arquivo = basename((string) $arquivo);
            if (is_file(FCPATH . 'assets/js/relatorios/conectores/' . $arquivo)) {
                $js[] = $arquivo;
            }
        }

        $php = [];
        foreach ($this->conectores_php() as $c) {
            if ($eixos === null || array_intersect($c->eixos(), $eixos)) {
                $php[] = ['chave' => $c->chave(), 'rotulo' => $c->rotulo()];
            }
        }

        return ['js' => $js, 'php' => $php];
    }

    /** @return Relatorio_conector[] */
    private function conectores_php() {
        $this->config->load('relatorio_conectores', TRUE);
        $cfg = $this->config->item('relatorio_conectores');
        require_once APPPATH . 'libraries/Relatorio_conector.php';

        $lista = [];
        foreach ($cfg['relatorio_conectores_php'] ?? [] as $classe) {
            $classe = preg_replace('/[^A-Za-z0-9_]/', '', (string) $classe);
            $arquivo = APPPATH . 'libraries/conectores/' . $classe . '.php';
            if (!is_file($arquivo)) {
                log_message('error', 'Conector PHP não encontrado: ' . $arquivo);
                continue;
            }
            require_once $arquivo;
            if (class_exists($classe) && is_subclass_of($classe, 'Relatorio_conector')) {
                $lista[] = new $classe();
            }
        }

        return $lista;
    }

    private function contexto_conector($r) {
        return [
            'id'         => (int) $r->id,
            'codigoIbge' => (string) $r->codigo_ibge,
            'codigoUf'   => substr((string) $r->codigo_ibge, 0, 2),
            'uf'         => $r->uf,
            'municipio'  => $r->municipio_nome,
            'periodo'    => $r->periodo_referencia,
            'eixos'      => $r->eixos_lista,
        ];
    }

    // ============================================================
    // Acesso e utilitários
    // ============================================================

    private function eh_equipe() {
        return in_array($this->session->userdata('admin_role'), ['administrador', 'supervisor'], true);
    }

    private function equipe($json = false) {
        if ($this->eh_equipe()) {
            return;
        }
        if ($json) {
            $this->json(['ok' => false, 'mensagem' => 'Acesso restrito à equipe do SXData.'], 403);
            $this->output->_display();
            exit;
        }
        $this->session->set_flashdata('error', 'Acesso restrito à equipe do SXData.');
        redirect($this->session->userdata('admin_role') === 'cliente' ? 'client/dashboard' : 'dashboard');
        exit;
    }

    /** Cliente vinculado ao projeto do relatório. */
    private function cliente_pode_ver($r) {
        if ($this->session->userdata('admin_role') !== 'cliente' || empty($r->project_id)) {
            return false;
        }

        return $this->db->where([
            'user_id'    => (int) $this->session->userdata('admin_id'),
            'project_id' => (int) $r->project_id,
        ])->count_all_results('project_clients') > 0;
    }

    private function obter_ou_404($id, $json = false) {
        $r = $this->relatorios->obter($id);
        if (!$r) {
            if ($json) {
                $this->json(['ok' => false, 'mensagem' => 'Relatório não encontrado.'], 404);
                $this->output->_display();
                exit;
            }
            show_404();
        }

        return $r;
    }

    private function exibir($view, $data) {
        $this->load->view('admin/header', $data);
        $this->load->view($view, $data);
        $this->load->view('admin/footer');
    }

    private function json($data, $status = 200) {
        // Token CSRF renovado: o JavaScript usa no próximo envio
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

    private function usuario() {
        return (int) $this->session->userdata('admin_id') ?: null;
    }

    private function truthy($value) {
        return $value === TRUE || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }
}
