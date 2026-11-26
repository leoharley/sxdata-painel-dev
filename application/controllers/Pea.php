<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Pea_motor.php';
require_once APPPATH . 'libraries/Bancodoc.php';

/**
 * Módulo PEA Gerdau (Parte I). Rotas pea/... Só a equipe (administrador e
 * supervisor). Alterar regras, estrutura e aprovar relatórios: administrador.
 */
class Pea extends CI_Controller {

    private $ciclo;

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        if (!$this->session->userdata('admin_logged_in')) {
            redirect('auth/login');
        }
        exigir_equipe();
        $this->load->model('Pea_model', 'pea');
        if (!$this->pea->disponivel()) {
            $this->session->set_flashdata('error', 'Módulo PEA indisponível: rode sql/pea_migration.sql no servidor.');
            redirect('dashboard');
        }
        $this->ciclo = $this->pea->ciclo();
        $m = $this->router->method;
        if (!$this->ciclo && !in_array($m, ['estrutura', 'carregar'], TRUE)) {
            redirect('pea/estrutura');
        }
    }

    // ================= Painel =================

    public function index() {
        $f = $this->filtros();
        $dados = $this->pea->painel($f + ['ciclo' => $this->ciclo]);
        $this->tela('painel', ['title' => 'PEA Gerdau - Painel', 'f' => $f, 'd' => $dados, 'ciclo' => $this->ciclo,
            'unidades' => $this->pea->unidades(), 'comunidades' => $this->pea->comunidades(), 'projetos' => $this->pea->projetos($this->ciclo->id),
            'eieap' => $this->pea->eieap()]);
    }

    // ================= Estrutura =================

    public function estrutura() {
        $c = $this->ciclo;
        $this->tela('estrutura', ['title' => 'PEA Gerdau - Estrutura', 'ciclo' => $c,
            'projetos' => $c ? $this->pea->projetos($c->id) : [], 'cronograma' => $c ? $this->pea->cronograma($c->id) : [],
            'unidades' => $this->pea->unidades(), 'comunidades' => $c ? $this->pea->comunidades() : [], 'escolas' => $c ? $this->pea->escolas() : [],
            'linhas' => $c ? $this->pea->linhas($c->id) : [], 'eixos' => $this->pea->eixos(), 'formulario' => $this->pea->formulario(),
            'seed' => is_file(APPPATH . 'config/pea_2026_2031.json')]);
    }

    public function carregar() {
        $this->so_admin_post();
        $s = json_decode((string) @file_get_contents(APPPATH . 'config/pea_2026_2031.json'), TRUE);
        if (!is_array($s)) {
            $this->voltar('error', 'Arquivo do PEA 2026–2031 não encontrado.', 'pea/estrutura');
        }
        try {
            $r = $this->pea->carregar($s, $this->eu());
        } catch (Exception $e) {
            $this->voltar('error', $e->getMessage(), 'pea/estrutura');
        }
        auditar('criar', 'pea', $r['ciclo'], 'PEA 2026–2031 carregado', NULL, $r);
        $this->voltar('success', "PEA carregado: {$r['projetos']} projetos, {$r['comunidades']} comunidades, {$r['escolas']} escolas, {$r['linhas']} linhas temáticas e {$r['cronograma']} registros de cronograma. Formulário de avaliação criado.", 'pea/estrutura');
    }

    public function salvar_projeto($id) {
        $this->so_admin_post();
        $p = $this->pea->projeto($id);
        if (!$p) { show_404(); }
        $fx = NULL;
        $b = $this->input->post('baixa_de');
        if ($b !== NULL && $b !== '') {
            $n = function ($k) { $v = $this->input->post($k); return ($v === '' || $v === NULL) ? NULL : (int) $v; };
            $fx = ['baixa' => [$n('baixa_de'), $n('baixa_ate')], 'media' => [$n('media_de'), $n('media_ate')], 'alta' => [$n('alta_de'), NULL]];
        }
        $d = ['nome' => trim($this->input->post('nome')) ?: $p->nome, 'periodicidade' => trim((string) $this->input->post('periodicidade')) ?: NULL,
            'ip_faixas' => $fx ? json_encode($fx) : NULL, 'avaliacao' => (bool) $this->input->post('avaliacao'), 'eixo_id' => (int) $this->input->post('eixo_id') ?: NULL];
        list($a, $dd) = auditoria_diferenca((array) $p, $d);
        $this->pea->salvar_projeto($id, $d);
        auditar('editar', 'pea_projeto', $id, 'Projeto do PEA alterado: ' . $p->nome, $a, $dd);
        $this->voltar('success', 'Projeto salvo.', 'pea/estrutura#projetos');
    }

    public function salvar_cronograma() {
        $this->so_admin_post();
        $n = 0;
        foreach ((array) $this->input->post('c') as $pid => $anos) {
            foreach ((array) $anos as $ano => $tris) {
                foreach ((array) $tris as $t => $v) {
                    $this->pea->salvar_cronograma($pid, $ano, $t, $v === '' ? 0 : $v);
                    $n++;
                }
            }
        }
        auditar('editar', 'pea_cronograma', $this->ciclo->id, 'Cronograma previsto do PEA salvo');
        $this->voltar('success', 'Cronograma salvo.', 'pea/estrutura#cronograma');
    }

    // ================= Ações =================

    public function acoes() {
        $f = $this->filtros();
        $f['status'] = $this->input->get('status');
        $this->tela('acoes', ['title' => 'PEA Gerdau - Ações', 'f' => $f, 'acoes' => $this->pea->acoes($f + ['ciclo' => $this->ciclo->id]),
            'projetos' => $this->pea->projetos($this->ciclo->id), 'unidades' => $this->pea->unidades(), 'comunidades' => $this->pea->comunidades()]);
    }

    public function nova() {
        $this->form_acao(NULL);
    }

    public function editar($id) {
        $a = $this->pea->acao($id);
        if (!$a) { show_404(); }
        $this->form_acao($a);
    }

    private function form_acao($a) {
        $this->tela('acao_form', ['title' => $a ? 'Editar ação' : 'Nova ação do PEA', 'a' => $a, 'ciclo' => $this->ciclo,
            'projetos' => $this->pea->projetos($this->ciclo->id), 'unidades' => $this->pea->unidades(), 'comunidades' => $this->pea->comunidades(),
            'escolas' => $this->pea->escolas(), 'linhas' => $this->pea->linhas($this->ciclo->id),
            'projeto_id' => (int) $this->input->get('projeto')]);
    }

    public function salvar($id = 0) {
        $this->so_post();
        $p = $this->pea->projeto($this->input->post('projeto_id'));
        if (!$p) { $this->voltar('error', 'Escolha o projeto.', 'pea/nova'); }
        $txt = function ($k, $n = 250) { $v = trim((string) $this->input->post($k)); return $v === '' ? NULL : mb_substr($v, 0, $n); };
        $data = function ($k) { $v = $this->input->post($k); return $v && strtotime($v) ? date('Y-m-d', strtotime($v)) : NULL; };
        $d = ['projeto_id' => (int) $p->id, 'titulo' => $txt('titulo') ?: $p->nome, 'unidade_id' => (int) $this->input->post('unidade_id') ?: NULL,
            'comunidade_id' => (int) $this->input->post('comunidade_id') ?: NULL, 'escola_id' => (int) $this->input->post('escola_id') ?: NULL,
            'linha_id' => (int) $this->input->post('linha_id') ?: NULL, 'data_prevista' => $data('data_prevista'), 'data_realizacao' => $data('data_realizacao'),
            'participantes' => $this->input->post('participantes') !== '' ? max(0, (int) $this->input->post('participantes')) : NULL,
            'carga_horaria' => $this->input->post('carga_horaria') !== '' ? (float) str_replace(',', '.', $this->input->post('carga_horaria')) : NULL,
            'local' => $txt('local'), 'observacoes' => $txt('observacoes', 5000), 'justificativa' => $txt('justificativa', 5000)];
        $antes = $id ? $this->pea->acao($id) : NULL;
        if ($antes && $antes->status === 'encerrada') {
            $this->voltar('error', 'Ação encerrada: reabra para editar.', 'pea/acao/' . (int) $id);
        }
        if (!$id) {
            $d['criado_por'] = $this->eu();
            $d['status'] = 'planejada';
        }
        if ($d['data_realizacao']) {
            $d['status'] = 'realizada';
        } elseif ($antes && $antes->status === 'realizada') {
            $d['status'] = 'planejada';
        }
        if ($this->input->post('cancelar')) {
            $d['status'] = 'cancelada';
        }
        $nid = $this->pea->salvar_acao($id, $d, $this->ciclo);
        auditar($id ? 'editar' : 'criar', 'pea_acao', $nid, ($id ? 'Ficha da ação alterada: ' : 'Ação criada: ') . $d['titulo'],
            $antes ? auditoria_diferenca((array) $antes, $d)[0] : NULL, $antes ? auditoria_diferenca((array) $antes, $d)[1] : NULL);
        $this->voltar('success', 'Ação salva.', 'pea/acao/' . $nid);
    }

    public function acao($id) {
        $a = $this->pea->acao($id);
        if (!$a) { show_404(); }
        list($versao, $regras) = $this->pea->regras();
        $ind = $this->pea->indicadores_acao($a, $regras);
        $this->tela('acao', ['title' => 'Ação: ' . $a->titulo, 'a' => $a, 'ind' => $ind, 'regras' => $regras, 'versao' => $versao,
            'evidencias' => $this->pea->evidencias($id), 'presencas' => $this->pea->presencas($id),
            'app' => $a->public_link_id ? $this->pea->respostas_do_app_sem_acao($a) : [], 'ciclo' => $this->ciclo]);
    }

    /** QR Code da ação para projetar ou imprimir. */
    public function qr($id) {
        $a = $this->pea->acao($id);
        if (!$a) { show_404(); }
        try {
            $this->pea->link_da_acao($a, $this->eu());
        } catch (Exception $e) {
            $this->voltar('error', $e->getMessage(), 'pea/acao/' . (int) $id);
        }
        $a = $this->pea->acao($id);
        $this->load->view('admin/pea/qr', ['a' => $a, 'url' => base_url('responder/' . $a->token)]);
    }

    public function encerrar($id) {
        $this->so_post();
        $a = $this->pea->acao($id);
        if (!$a) { show_404(); }
        if ($this->input->post('reabrir')) {
            $this->db->where('id', (int) $id)->update('pea_acoes', ['status' => $a->data_realizacao ? 'realizada' : 'planejada', 'encerrada_em' => NULL, 'encerrada_por' => NULL]);
            auditar('editar', 'pea_acao', $id, 'Ação reaberta: ' . $a->titulo);
            $this->voltar('success', 'Ação reaberta.', 'pea/acao/' . (int) $id);
        }
        if (!$a->data_realizacao) {
            $this->voltar('error', 'Informe a data de realização antes de encerrar.', 'pea/acao/' . (int) $id);
        }
        list($versao, $regras) = $this->pea->regras();
        $a->status = 'realizada';
        $ind = $this->pea->indicadores_acao($a, $regras);
        $just = trim((string) $this->input->post('justificativa')) ?: $a->justificativa;
        if ($ind['icc'] !== NULL && $ind['icc'] < 50 && !$just) {
            $this->voltar('error', 'ICC abaixo de 50 (ação fora do trimestre previsto): a justificativa é obrigatória e vai para o relatório.', 'pea/acao/' . (int) $id);
        }
        $this->db->where('id', (int) $id)->update('pea_acoes', ['status' => 'encerrada', 'justificativa' => $just, 'encerrada_em' => date('Y-m-d H:i:s'),
            'encerrada_por' => $this->eu(), 'regra_versao' => $versao, 'indicadores' => json_encode($ind, JSON_UNESCAPED_UNICODE)]);
        if ($a->public_link_id) {
            $this->db->where('id', (int) $a->public_link_id)->update('questionnaire_public_links', ['is_active' => FALSE, 'deactivated_at' => date('Y-m-d H:i:s')]);
        }
        auditar('editar', 'pea_acao', $id, 'Ação encerrada: ' . $a->titulo, NULL, ['icc' => $ind['icc'], 'ip' => $ind['ip'], 'is' => $ind['is'], 'iia' => $ind['iia'], 'regra' => $versao]);
        $this->voltar('success', 'Ação encerrada: indicadores calculados com as regras v' . $versao . ' e QR Code desativado.', 'pea/acao/' . (int) $id);
    }

    public function vincular_app($id) {
        $this->so_post();
        $a = $this->pea->acao($id);
        if (!$a) { show_404(); }
        $n = $this->pea->vincular_respostas($a, (array) $this->input->post('ids'));
        auditar('editar', 'pea_acao', $id, $n . ' resposta(s) do aplicativo ligadas à ação');
        $this->voltar('success', $n . ' resposta(s) do aplicativo ligadas à ação.', 'pea/acao/' . (int) $id);
    }

    public function evidencia($id) {
        $this->so_post();
        $a = $this->pea->acao($id);
        if (!$a) { show_404(); }
        $dir = FCPATH . 'uploads/pea/';
        if (!is_dir($dir)) { @mkdir($dir, 0750, TRUE); }
        if (empty($_FILES['arquivo']['tmp_name']) || !is_uploaded_file($_FILES['arquivo']['tmp_name'])) {
            $this->voltar('error', 'Escolha o arquivo.', 'pea/acao/' . (int) $id);
        }
        $orig = $_FILES['arquivo']['name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'doc', 'docx', 'xls', 'xlsx', 'csv'], TRUE) || $_FILES['arquivo']['size'] > 20 * 1048576) {
            $this->voltar('error', 'Arquivo não aceito (PDF, imagem, Word ou Excel, até 20 MB).', 'pea/acao/' . (int) $id);
        }
        $nome = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $dir . $nome)) {
            $this->voltar('error', 'Não foi possível gravar o arquivo.', 'pea/acao/' . (int) $id);
        }
        $tipo = in_array($this->input->post('tipo'), ['lista_presenca', 'foto', 'relatorio', 'outro'], TRUE) ? $this->input->post('tipo') : 'outro';
        $this->db->insert('pea_evidencias', ['acao_id' => (int) $id, 'tipo' => $tipo, 'arquivo' => $nome, 'nome_original' => mb_substr($orig, 0, 250),
            'tamanho' => (int) $_FILES['arquivo']['size'], 'enviado_por' => $this->eu()]);
        auditar('criar', 'pea_evidencia', $id, 'Evidência anexada (' . $tipo . '): ' . $orig);
        $this->voltar('success', 'Evidência anexada.', 'pea/acao/' . (int) $id);
    }

    public function baixar($eid) {
        $e = $this->db->get_where('pea_evidencias', ['id' => (int) $eid])->row();
        $arq = $e ? FCPATH . 'uploads/pea/' . basename($e->arquivo) : NULL;
        if (!$e || !is_file($arq)) { show_404(); }
        auditar('baixar', 'pea_evidencia', $eid, 'Evidência baixada: ' . $e->nome_original);
        $this->load->helper('download');
        force_download($e->nome_original ?: $e->arquivo, file_get_contents($arq), TRUE);
    }

    public function evidencia_excluir($eid) {
        $this->so_post();
        $e = $this->db->get_where('pea_evidencias', ['id' => (int) $eid])->row();
        if ($e) {
            $this->db->where('id', (int) $eid)->delete('pea_evidencias');
            @unlink(FCPATH . 'uploads/pea/' . basename($e->arquivo));
            auditar('excluir', 'pea_evidencia', $eid, 'Evidência removida: ' . $e->nome_original);
        }
        $this->voltar('success', 'Evidência removida.', 'pea/acao/' . (int) ($e ? $e->acao_id : 0));
    }

    public function presenca($id) {
        $this->so_post();
        $a = $this->pea->acao($id);
        if (!$a) { show_404(); }
        try {
            $da_base = $this->pea->adicionar_presenca($id, ['nome' => trim((string) $this->input->post('nome')), 'cpf' => $this->input->post('cpf'),
                'matricula' => trim((string) $this->input->post('matricula')) ?: NULL, 'empresa' => trim((string) $this->input->post('empresa')) ?: NULL]);
        } catch (InvalidArgumentException $e) {
            $this->voltar('error', $e->getMessage(), 'pea/acao/' . (int) $id . '#presenca');
        }
        $n = count($this->pea->presencas($id));
        if ((int) $a->participantes < $n) {
            $this->db->where('id', (int) $id)->update('pea_acoes', ['participantes' => $n]);
        }
        $this->voltar('success', $da_base ? 'Presença registrada (encontrado na base do BancoDOC).' : 'Presença registrada.', 'pea/acao/' . (int) $id . '#presenca');
    }

    public function presenca_excluir($pid) {
        $this->so_post();
        $p = $this->db->get_where('pea_presencas', ['id' => (int) $pid])->row();
        if ($p) { $this->db->where('id', (int) $pid)->delete('pea_presencas'); }
        $this->voltar('success', 'Presença removida.', 'pea/acao/' . (int) ($p ? $p->acao_id : 0) . '#presenca');
    }

    // ================= Regras =================

    public function regras() {
        list($versao, $regras) = $this->pea->regras();
        $this->tela('regras', ['title' => 'PEA Gerdau - Regras de ponderação', 'versao' => $versao, 'regras' => $regras, 'historico' => $this->pea->historico_regras()]);
    }

    public function salvar_regras() {
        $this->so_admin_post();
        list($v, $atual) = $this->pea->regras();
        $p = $this->input->post();
        $num = function ($x, $padrao) { return is_numeric(str_replace(',', '.', (string) $x)) ? (float) str_replace(',', '.', $x) : $padrao; };
        $novo = $atual;
        $novo['is']['denominador'] = ($p['is_denominador'] ?? '') === 'respondentes' ? 'respondentes' : 'participantes';
        $novo['is']['satisfeito'] = array_values(array_intersect((array) ($p['is_satisfeito'] ?? []), ['muito_insatisfeito', 'insatisfeito', 'indiferente', 'satisfeito', 'muito_satisfeito']));
        foreach (['peso_a', 'peso_c', 'peso_d', 'divisor'] as $k) { $novo['iia'][$k] = $num($p['iia_' . $k] ?? NULL, $atual['iia'][$k]); }
        $novo['iia']['piso_zero'] = !empty($p['iia_piso_zero']);
        $novo['icc']['tolerancia_trimestres'] = max(0, (int) ($p['icc_tolerancia'] ?? 0));
        foreach (['baixa', 'media', 'alta', 'abaixo_da_baixa'] as $k) { $novo['ip'][$k] = $num($p['ip_' . $k] ?? NULL, $atual['ip'][$k]); }
        foreach (['icc', 'ip', 'is', 'iia'] as $k) { $novo['ie']['pesos'][$k] = $num($p['ie_' . $k] ?? NULL, $atual['ie']['pesos'][$k]); }
        $erros = Pea_motor::validar_regras($novo);
        if ($erros) {
            $this->voltar('error', implode(' ', $erros), 'pea/regras');
        }
        $nota = trim((string) ($p['nota'] ?? ''));
        if ($nota === '') {
            $this->voltar('error', 'Descreva o motivo da alteração (fica no histórico e nos relatórios).', 'pea/regras');
        }
        $nv = $this->pea->salvar_regras($novo, $nota, $this->eu());
        list($a, $d) = auditoria_diferenca(['is' => json_encode($atual['is']), 'iia' => json_encode($atual['iia']), 'icc' => json_encode($atual['icc']), 'ip' => json_encode($atual['ip']), 'ie' => json_encode($atual['ie'])],
            ['is' => json_encode($novo['is']), 'iia' => json_encode($novo['iia']), 'icc' => json_encode($novo['icc']), 'ip' => json_encode($novo['ip']), 'ie' => json_encode($novo['ie'])]);
        auditar('config', 'pea_regras', $nv, 'Regras de ponderação v' . $nv . ': ' . $nota, $a, $d);
        $this->voltar('success', 'Regras salvas como versão ' . $nv . '. Ações já encerradas e relatórios já gerados mantêm a versão com que foram calculados.', 'pea/regras');
    }

    // ================= EIEAP =================

    public function eieap() {
        $this->tela('eieap', ['title' => 'PEA Gerdau - Percepção e comportamento (EIEAP)', 'e' => $this->pea->eieap(), 'regras' => $this->pea->regras()[1]]);
    }

    public function salvar_eieap() {
        $this->so_admin_post();
        if ($this->input->post('novo_nome')) {
            $c = $this->pea->salvar_no($this->input->post('novo_codigo'), trim($this->input->post('novo_nome')), $this->input->post('novo_pai'), $this->input->post('novo_peso') ?: 1);
            auditar('config', 'pea_eieap', $c, 'Nó da árvore EIEAP salvo: ' . $this->input->post('novo_nome'));
        }
        foreach ((array) $this->input->post('peso') as $cod => $peso) {
            $this->db->where('codigo', $cod)->update('pea_eieap_nos', ['peso' => max(0, (float) str_replace(',', '.', $peso))]);
        }
        $ano = (int) $this->input->post('ano') ?: (int) date('Y');
        foreach ((array) $this->input->post('v') as $un => $por_no) {
            foreach ((array) $por_no as $no => $tipos) {
                foreach ((array) $tipos as $tipo => $valor) {
                    if (!in_array($tipo, ['base', 'meta', 'atual'], TRUE)) { continue; }
                    $a = $tipo === 'base' ? 2021 : $ano;
                    $this->pea->salvar_eieap_valor($no, $un, $tipo, $a, $valor);
                }
            }
        }
        auditar('config', 'pea_eieap', NULL, 'Valores EIEAP salvos (ano ' . $ano . ')');
        $this->voltar('success', 'Árvore e valores salvos.', 'pea/eieap');
    }

    // ================= Relatórios =================

    public function relatorios() {
        $this->tela('relatorios', ['title' => 'PEA Gerdau - Relatórios', 'lista' => $this->pea->relatorios(), 'ciclo' => $this->ciclo,
            'acoes' => $this->pea->acoes(['ciclo' => $this->ciclo->id, 'status' => 'encerrada'])]);
    }

    public function gerar() {
        $this->so_post();
        $tipo = $this->input->post('tipo');
        $ano = (int) $this->input->post('ano');
        $sem = (int) $this->input->post('semestre');
        $c = $this->ciclo;
        list($versao, $regras) = $this->pea->regras();
        $dados = ['ciclo' => ['nome' => $c->nome, 'inicio' => $c->inicio]];
        if ($tipo === 'ficha') {
            $a = $this->pea->acao($this->input->post('acao_id'));
            if (!$a) { $this->voltar('error', 'Escolha a ação.', 'pea/relatorios'); }
            $ind = $a->indicadores ? json_decode($a->indicadores, TRUE) : $this->pea->indicadores_acao($a, $regras);
            $versao = $a->regra_versao ?: $versao;
            $dados += ['acao' => (array) $a, 'indicadores' => $ind, 'evidencias' => array_map(function ($e) { return ['tipo' => $e->tipo, 'nome' => $e->nome_original]; }, $this->pea->evidencias($a->id)),
                'presentes' => count($this->pea->presencas($a->id))];
            $titulo = 'Ficha da ação — ' . $a->titulo;
        } elseif (in_array($tipo, ['semestral', 'anual', 'ciclo'], TRUE)) {
            $f = ['ciclo' => $c];
            if ($tipo !== 'ciclo') {
                if ($ano < 1 || $ano > (int) $c->anos) { $this->voltar('error', 'Escolha o ano do ciclo.', 'pea/relatorios'); }
                $f['ano'] = $ano;
            }
            if ($tipo === 'semestral') {
                $f['trimestres'] = $sem === 2 ? [3, 4] : [1, 2];
            }
            $p = $this->pea->painel($f);
            $dados += ['painel' => array_diff_key($p, ['regras' => 1]), 'faixas' => $regras['faixas'], 'eieap' => $this->pea->eieap()['unidades'],
                'por_ano' => $tipo === 'ciclo' ? $this->por_ano($c) : NULL];
            list($de, $ate) = $tipo === 'ciclo' ? [$c->inicio, Pea_motor::intervalo((int) $c->anos, NULL, $c->inicio)[1]] : Pea_motor::intervalo($ano, NULL, $c->inicio);
            $dados['periodo'] = [$de, $ate];
            $titulo = ['semestral' => 'Formulário semestral — ano ' . $ano . ', ' . ($sem === 2 ? '2º' : '1º') . ' semestre', 'anual' => 'Relatório anual — ano ' . $ano . ' do ciclo',
                'ciclo' => 'Relatório do ciclo ' . $c->nome][$tipo];
        } else {
            $this->voltar('error', 'Tipo de relatório inválido.', 'pea/relatorios');
        }
        $this->db->insert('pea_relatorios', ['tipo' => $tipo, 'ciclo_id' => $c->id, 'ano' => $ano ?: NULL, 'semestre' => $tipo === 'semestral' ? ($sem ?: 1) : NULL,
            'acao_id' => $tipo === 'ficha' ? (int) $this->input->post('acao_id') : NULL, 'titulo' => $titulo, 'status' => 'rascunho',
            'dados' => json_encode($dados, JSON_UNESCAPED_UNICODE), 'textos' => json_encode(['apresentacao' => '', 'analise' => '', 'desvios' => '', 'conclusao' => ''], JSON_UNESCAPED_UNICODE),
            'regra_versao' => $versao, 'criado_por' => $this->eu()]);
        $rid = (int) $this->db->insert_id();
        $this->pea->evento($rid, 'gerado', $this->eu(), 'Regras v' . $versao);
        auditar('criar', 'pea_relatorio', $rid, 'Relatório gerado: ' . $titulo);
        $this->voltar('success', 'Versão preliminar gerada. Revise os textos e envie para conferência.', 'pea/relatorio/' . $rid);
    }

    private function por_ano($c) {
        $r = [];
        for ($a = 1; $a <= (int) $c->anos; $a++) {
            $p = $this->pea->painel(['ciclo' => $c, 'ano' => $a]);
            $r[$a] = $p['total'] + ['n_acoes' => $p['n_acoes']];
        }
        return $r;
    }

    public function relatorio($id) {
        $r = $this->pea->relatorio($id);
        if (!$r) { show_404(); }
        $this->tela('relatorio', ['title' => $r->titulo, 'r' => $r, 'regras' => $this->pea->regras_versao($r->regra_versao) ?: $this->pea->regras()[1],
            'admin' => $this->session->userdata('admin_role') === 'administrador']);
    }

    public function relatorio_acao($id) {
        $this->so_post();
        $r = $this->pea->relatorio($id);
        if (!$r) { show_404(); }
        $acao = $this->input->post('acao');
        $com = trim((string) $this->input->post('comentario'));
        $admin = $this->session->userdata('admin_role') === 'administrador';
        if ($acao === 'salvar') {
            if ($r->status === 'aprovado') { $this->voltar('error', 'Relatório aprovado não muda: gere uma nova versão.', 'pea/relatorio/' . (int) $id); }
            $t = [];
            foreach (['apresentacao', 'analise', 'desvios', 'conclusao'] as $k) { $t[$k] = (string) $this->input->post($k); }
            $this->db->where('id', (int) $id)->update('pea_relatorios', ['textos' => json_encode($t, JSON_UNESCAPED_UNICODE)]);
            $this->pea->evento($id, 'editado', $this->eu());
            $this->voltar('success', 'Textos salvos.', 'pea/relatorio/' . (int) $id);
        }
        $trans = ['enviar' => ['rascunho', 'em_conferencia'], 'devolver' => ['em_conferencia', 'rascunho'], 'aprovar' => ['em_conferencia', 'aprovado']];
        if ($acao === 'comentar' && $com !== '') {
            $this->pea->evento($id, 'comentario', $this->eu(), $com);
            $this->voltar('success', 'Comentário registrado.', 'pea/relatorio/' . (int) $id);
        }
        if (!isset($trans[$acao]) || $r->status !== $trans[$acao][0]) {
            $this->voltar('error', 'Ação não permitida no estado atual.', 'pea/relatorio/' . (int) $id);
        }
        if ($acao === 'aprovar' && !$admin) {
            $this->voltar('error', 'Somente administradores aprovam.', 'pea/relatorio/' . (int) $id);
        }
        $d = ['status' => $trans[$acao][1]];
        if ($acao === 'enviar') { $d['conferido_por'] = NULL; }
        if ($acao === 'aprovar') { $d += ['aprovado_por' => $this->eu(), 'aprovado_em' => date('Y-m-d H:i:s'), 'conferido_por' => $this->eu()]; }
        $this->db->where('id', (int) $id)->update('pea_relatorios', $d);
        $this->pea->evento($id, ['enviar' => 'enviado', 'devolver' => 'devolvido', 'aprovar' => 'aprovado'][$acao], $this->eu(), $com);
        auditar('editar', 'pea_relatorio', $id, 'Relatório ' . ['enviar' => 'enviado para conferência', 'devolver' => 'devolvido para ajustes', 'aprovar' => 'aprovado'][$acao] . ': ' . $r->titulo);
        $this->voltar('success', ['enviar' => 'Enviado para conferência.', 'devolver' => 'Devolvido para ajustes.', 'aprovar' => 'Relatório aprovado: liberado para exportação.'][$acao], 'pea/relatorio/' . (int) $id);
    }

    /** Versão para impressão / PDF (aprovado; o rascunho sai com marca d'água). */
    public function imprimir($id) {
        $r = $this->pea->relatorio($id);
        if (!$r) { show_404(); }
        auditar('exportar', 'pea_relatorio', $id, 'Relatório impresso/PDF: ' . $r->titulo);
        if ($r->status === 'aprovado') { $this->pea->evento($id, 'exportado', $this->eu(), 'PDF'); }
        $this->load->view('admin/pea/imprimir', ['r' => $r, 'regras' => $this->pea->regras_versao($r->regra_versao) ?: $this->pea->regras()[1]]);
    }

    /** Base de cálculo em Excel. */
    public function excel($id) {
        $r = $this->pea->relatorio($id);
        if (!$r) { show_404(); }
        require_once FCPATH . 'vendor/autoload.php';
        $pl = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $aba = $pl->getActiveSheet();
        $aba->setTitle('Indicadores');
        $linha = 1;
        $put = function (array $vals) use (&$linha, $aba) {
            foreach (array_values($vals) as $i => $v) { $aba->setCellValueByColumnAndRow($i + 1, $linha, $v); }
            $linha++;
        };
        $put([$r->titulo]);
        $put(['Regras de ponderação: versão ' . $r->regra_versao, 'Status: ' . $r->status]);
        $linha++;
        $d = $r->dados;
        if ($r->tipo === 'ficha') {
            $i = $d['indicadores'];
            $put(['Indicador', 'Valor']);
            foreach (['icc' => 'ICC', 'ip' => 'IP', 'is' => 'IS', 'iia' => 'IIA', 'ie' => 'IE'] as $k => $n) { $put([$n, $i[$k]]); }
            $put(['Participantes', $i['participantes']]);
            $put(['Respondentes', $i['respondentes']]);
        } else {
            $put(['Projeto', 'Público', 'Previstas', 'Realizadas', 'No prazo', 'ICC', 'IP', 'IS', 'IIA', 'IE']);
            foreach ($d['painel']['projetos'] as $p) {
                $put([$p['projeto']['nome'], $p['projeto']['publico'], $p['previstas'], $p['realizadas'], $p['no_prazo'], $p['icc'], $p['ip'], $p['is'], $p['iia'], $p['ie']]);
            }
            $t = $d['painel']['total'];
            $put(['Total (média dos projetos)', '', '', '', '', $t['icc'], $t['ip'], $t['is'], $t['iia'], $t['ie']]);
            $ac = $pl->createSheet();
            $ac->setTitle('Ações');
            $ac->fromArray(['Ação', 'Projeto', 'Ano', 'Trimestre', 'Realizada em', 'Participantes', 'Respondentes', 'ICC', 'IP', 'IS', 'IIA', 'IE', 'Justificativa'], NULL, 'A1');
            $l = 2;
            foreach ($this->pea->acoes(['ciclo' => $this->ciclo->id] + ($r->ano ? ['ano' => $r->ano] : [])) as $a) {
                if ($r->semestre && !in_array((int) $a->trimestre_previsto, $r->semestre == 2 ? [3, 4] : [1, 2], TRUE)) { continue; }
                $a->ciclo_id = $this->ciclo->id;
                $i = $a->indicadores ? json_decode($a->indicadores, TRUE) : $this->pea->indicadores_acao($a);
                $ac->fromArray([$a->titulo, $a->projeto, $a->ano_previsto, $a->trimestre_previsto, $a->data_realizacao, $a->participantes, $i['respondentes'],
                    $i['icc'], $i['ip'], $i['is'], $i['iia'], $i['ie'], $a->justificativa], NULL, 'A' . $l++);
            }
        }
        $aba->getColumnDimension('A')->setWidth(45);
        auditar('exportar', 'pea_relatorio', $id, 'Base de cálculo em Excel: ' . $r->titulo);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="pea_relatorio_' . (int) $id . '.xlsx"');
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($pl, 'Xlsx')->save('php://output');
        exit;
    }

    // ================= BancoDOC (frente H) =================

    public function bancodoc() {
        $this->tela('bancodoc', ['title' => 'PEA Gerdau - BancoDOC', 'ciclo' => $this->ciclo,
            'n_colab' => $this->db->count_all('pea_colaboradores'), 'n_emp' => $this->db->count_all('pea_empresas'),
            'envios' => $this->db->select('b.*, u.full_name')->from('pea_bancodoc_envios b')->join('users u', 'u.id = b.user_id', 'left')->order_by('b.id', 'DESC')->limit(30)->get()->result(),
            'pendentes' => (int) $this->db->query("SELECT COUNT(*) AS n FROM pea_presencas p JOIN pea_acoes a ON a.id = p.acao_id WHERE a.status = 'encerrada' AND p.enviado_bancodoc_em IS NULL")->row()->n,
            'recentes_sem_modulo' => $this->recem_mobilizados()]);
    }

    /** Colaboradores mobilizados que ainda não fizeram o Módulo Básico. */
    private function recem_mobilizados() {
        return $this->db->query("SELECT c.nome, c.cpf, c.matricula, c.unidade, c.mobilizado_em, e.nome AS empresa FROM pea_colaboradores c
            LEFT JOIN pea_empresas e ON e.id = c.empresa_id
            WHERE COALESCE(c.situacao, '') NOT ILIKE 'desmob%' AND NOT EXISTS (
                SELECT 1 FROM pea_presencas p JOIN pea_acoes a ON a.id = p.acao_id JOIN pea_projetos pr ON pr.id = a.projeto_id
                 WHERE (p.colaborador_id = c.id OR (p.cpf IS NOT NULL AND p.cpf = c.cpf)) AND pr.nome ILIKE 'Módulo Básico%')
            ORDER BY c.mobilizado_em DESC NULLS LAST, c.nome LIMIT 200")->result();
    }

    public function bancodoc_importar() {
        $this->so_admin_post();
        if (empty($_FILES['arquivo']['tmp_name']) || !is_uploaded_file($_FILES['arquivo']['tmp_name'])) {
            $this->voltar('error', 'Escolha a planilha exportada do BancoDOC.', 'pea/bancodoc');
        }
        $ext = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt', 'xlsx', 'xls'], TRUE)) {
            $this->voltar('error', 'Use CSV ou Excel.', 'pea/bancodoc');
        }
        $tmp = sys_get_temp_dir() . '/bdoc_' . bin2hex(random_bytes(6)) . '.' . $ext;
        move_uploaded_file($_FILES['arquivo']['tmp_name'], $tmp);
        try {
            $linhas = Bancodoc::conector('arquivo')->colaboradores($tmp);
        } catch (Throwable $e) {
            @unlink($tmp);
            $this->voltar('error', 'Não foi possível ler a planilha: ' . $e->getMessage(), 'pea/bancodoc');
        }
        @unlink($tmp);
        $novos = 0; $atual = 0; $erros = [];
        $this->db->trans_begin();
        foreach ($linhas as $i => $l) {
            list($d, $erro) = Bancodoc::normalizar_linha($l);
            if ($erro) { if (count($erros) < 50) { $erros[] = 'Linha ' . ($i + 2) . ': ' . $erro; } continue; }
            $emp = NULL;
            if (!empty($d['empresa'])) {
                $e = $this->db->get_where('pea_empresas', ['nome' => $d['empresa']])->row();
                if ($e) { $emp = (int) $e->id; } else {
                    $this->db->insert('pea_empresas', ['nome' => mb_substr($d['empresa'], 0, 250), 'cnpj' => isset($d['cnpj']) ? preg_replace('/\D/', '', $d['cnpj']) : NULL]);
                    $emp = (int) $this->db->insert_id();
                }
            }
            $row = ['nome' => mb_substr($d['nome'], 0, 200), 'cpf' => $d['cpf'] ?? NULL, 'matricula' => $d['matricula'] ?? NULL, 'empresa_id' => $emp,
                'funcao' => isset($d['funcao']) ? mb_substr($d['funcao'], 0, 200) : NULL, 'unidade' => $d['unidade'] ?? NULL, 'situacao' => $d['situacao'] ?? NULL,
                'mobilizado_em' => $d['mobilizado_em'] ?? NULL, 'origem' => 'planilha', 'atualizado_em' => date('Y-m-d H:i:s')];
            $ex = !empty($row['cpf']) ? $this->db->get_where('pea_colaboradores', ['cpf' => $row['cpf']])->row()
                : $this->db->get_where('pea_colaboradores', ['matricula' => $row['matricula']])->row();
            if ($ex) { $this->db->where('id', $ex->id)->update('pea_colaboradores', $row); $atual++; }
            else { $this->db->insert('pea_colaboradores', $row); $novos++; }
        }
        $this->db->insert('pea_bancodoc_envios', ['sentido' => 'entrada', 'conector' => 'arquivo', 'status' => $erros ? 'parcial' : 'ok', 'registros' => $novos + $atual,
            'arquivo' => mb_substr($_FILES['arquivo']['name'], 0, 200), 'detalhes' => json_encode(['novos' => $novos, 'atualizados' => $atual, 'erros' => $erros], JSON_UNESCAPED_UNICODE), 'user_id' => $this->eu()]);
        $this->db->trans_commit();
        auditar('criar', 'pea_bancodoc', NULL, "Base do BancoDOC importada: $novos novos, $atual atualizados, " . count($erros) . ' linha(s) com erro');
        $this->voltar($erros ? 'error' : 'success', "Importação concluída: $novos novos, $atual atualizados." . ($erros ? ' Linhas recusadas: ' . implode('; ', array_slice($erros, 0, 10)) . (count($erros) > 10 ? '…' : '') : ''), 'pea/bancodoc');
    }

    /** Arquivo de participações (ações encerradas) no layout de importação do BancoDOC. */
    public function bancodoc_exportar() {
        $so_pendentes = $this->input->get('todas') ? FALSE : TRUE;
        $q = $this->db->select("p.id, p.cpf, p.matricula, p.nome, COALESCE(p.empresa, e.nome) AS empresa, u.sigla AS unidade, a.id AS acao_id, a.titulo, pr.nome AS projeto, a.data_realizacao, a.carga_horaria", FALSE)
            ->from('pea_presencas p')->join('pea_acoes a', 'a.id = p.acao_id')->join('pea_projetos pr', 'pr.id = a.projeto_id')
            ->join('pea_unidades u', 'u.id = a.unidade_id', 'left')->join('pea_colaboradores c', 'c.id = p.colaborador_id', 'left')->join('pea_empresas e', 'e.id = c.empresa_id', 'left')
            ->where('a.status', 'encerrada');
        if ($so_pendentes) { $q->where('p.enviado_bancodoc_em IS NULL', NULL, FALSE); }
        $linhas = $q->order_by('a.data_realizacao')->get()->result();
        list($ok, $msg) = Bancodoc::conector('arquivo')->enviar_participacoes($linhas);
        $ids = array_map(function ($l) { return (int) $l->id; }, $linhas);
        if ($ids) {
            $this->db->where_in('id', $ids)->update('pea_presencas', ['enviado_bancodoc_em' => date('Y-m-d H:i:s')]);
        }
        $this->db->insert('pea_bancodoc_envios', ['sentido' => 'saida', 'conector' => 'arquivo', 'status' => 'ok', 'registros' => count($linhas),
            'arquivo' => 'participacoes_pea_' . date('Ymd_His') . '.csv', 'detalhes' => json_encode(['so_pendentes' => $so_pendentes, 'mensagem' => $msg], JSON_UNESCAPED_UNICODE), 'user_id' => $this->eu()]);
        auditar('exportar', 'pea_bancodoc', NULL, 'Arquivo de participações para o BancoDOC: ' . count($linhas) . ' linha(s)');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="participacoes_pea_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, Bancodoc::colunas_participacao(), ';');
        foreach ($linhas as $l) {
            fputcsv($out, [$l->cpf, $l->matricula, $l->nome, $l->empresa, $l->unidade, $l->titulo, $l->projeto,
                $l->data_realizacao ? date('d/m/Y', strtotime($l->data_realizacao)) : '', $l->carga_horaria !== NULL ? str_replace('.', ',', $l->carga_horaria) : '', 'PEA-' . $l->acao_id], ';');
        }
        fclose($out);
        exit;
    }

    public function bancodoc_modelo() {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="modelo_base_bancodoc.csv"');
        echo "\xEF\xBB\xBF" . "CPF;Matrícula;Nome;Empresa;CNPJ;Função;Unidade;Situação;Data de mobilização\n"
            . "123.456.789-09;M0001;Nome do Colaborador;Empresa Parceira Ltda;12.345.678/0001-90;Operador;UOB;Mobilizado;01/09/2026\n";
        exit;
    }

    // ================= apoio =================

    private function filtros() {
        $g = $this->input->get();
        return ['ano' => (int) ($g['ano'] ?? 0) ?: NULL, 'trimestre' => (int) ($g['trimestre'] ?? 0) ?: NULL,
            'publico' => array_key_exists($g['publico'] ?? '', Pea_model::PUBLICOS) ? $g['publico'] : NULL,
            'unidade_id' => (int) ($g['unidade_id'] ?? 0) ?: NULL, 'comunidade_id' => (int) ($g['comunidade_id'] ?? 0) ?: NULL,
            'projeto_id' => (int) ($g['projeto_id'] ?? 0) ?: NULL];
    }

    private function eu() {
        return (int) $this->session->userdata('admin_id');
    }

    private function so_post() {
        if ($this->input->method() !== 'post') { show_error('Use o botão da tela.', 405); }
    }

    private function so_admin_post() {
        $this->so_post();
        if ($this->session->userdata('admin_role') !== 'administrador') {
            $this->voltar('error', 'Somente administradores alteram a estrutura e as regras do PEA.', 'pea');
        }
    }

    private function voltar($tipo, $msg, $destino) {
        $this->session->set_flashdata($tipo, html_escape($msg));
        redirect($destino);
    }

    private function tela($view, $dados) {
        $this->load->view('admin/header', $dados);
        $this->load->view('admin/pea/_menu', $dados);
        $this->load->view('admin/pea/' . $view, $dados);
        $this->load->view('admin/footer');
    }
}
