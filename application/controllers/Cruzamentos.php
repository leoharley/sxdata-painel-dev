<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Cruzamento de variáveis (frente J). Rotas: cruzamentos/...
 *
 * - Equipe (administrador e supervisor): todos os questionários, todas as
 *   variáveis, análises salvas, liberação ao cliente e interpretação por IA.
 * - Cliente: só os projetos dele (project_clients), sem variáveis que
 *   identificam pessoas (entrevistador, local, ponto), com as células de
 *   menos de k respostas ocultas (Configurações: cruzamento_k_minimo, padrão 5).
 *   Vê as análises salvas que a equipe liberou.
 */
class Cruzamentos extends CI_Controller {

    private $cliente = FALSE;
    private $projetos = NULL;

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        if (!$this->session->userdata('admin_logged_in')) {
            redirect('auth/login');
        }
        $papel = $this->session->userdata('admin_role');
        if (!in_array($papel, ['administrador', 'supervisor', 'cliente'], TRUE)) {
            redirect('auth/login');
        }
        $this->load->model('Cruzamento_model', 'cz');
        if ($papel === 'cliente') {
            $this->cliente = TRUE;
            $this->projetos = array_map('intval', array_column($this->db->select('project_id')
                ->get_where('project_clients', ['user_id' => (int) $this->session->userdata('admin_id')])->result_array(), 'project_id'));
        }
    }

    public function index() {
        $this->tela('index', [
            'title' => 'Cruzamento de variáveis - SXData',
            'pronto' => $this->cz->disponivel(),
            'questionarios' => $this->cz->questionarios($this->projetos),
            'salvas' => $this->cz->disponivel() ? $this->cz->salvas(NULL, $this->cliente, $this->projetos) : [],
            'cliente' => $this->cliente,
        ]);
    }

    /** Tela do cruzamento de um questionário (opcional: ?salva=id para abrir uma análise salva). */
    public function q($qid) {
        $q = $this->questionario($qid);
        $salva = NULL;
        if ($this->input->get('salva')) {
            $salva = $this->cz->salva($this->input->get('salva'));
            if (!$salva || (int) $salva->questionnaire_id !== (int) $q->id || ($this->cliente && !in_array($salva->liberado_cliente, [TRUE, 't'], TRUE))) {
                show_404();
            }
        }
        $unidade = $this->input->get('unidade') === 'morador' ? 'morador' : 'domicilio';
        if ($salva) {
            $cfg = json_decode($salva->config, TRUE) ?: [];
            $unidade = ($cfg['unidade'] ?? '') === 'morador' ? 'morador' : 'domicilio';
        }
        auditar('visualizar', 'cruzamento', $q->id, 'Abriu o cruzamento de ' . $q->title);
        $this->tela('tela', [
            'title' => 'Cruzamento: ' . $q->title,
            'q' => $q,
            'unidade' => $unidade,
            'variaveis' => $this->cz->variaveis($q, $unidade, $this->cliente),
            'salva' => $salva,
            'salvas' => $this->cz->disponivel() ? $this->cz->salvas($q->id, $this->cliente) : [],
            'aplicadores' => $this->cliente ? [] : $this->db->query('SELECT DISTINCT u.id, u.full_name FROM form_responses fr JOIN users u ON u.id = fr.applied_by WHERE fr.questionnaire_id = ? ORDER BY u.full_name', [(int) $q->id])->result(),
            'cliente' => $this->cliente,
            'k' => $this->k(),
        ]);
    }

    /** POST (JSON): calcula a tabela. */
    public function calcular() {
        header('Content-Type: application/json; charset=utf-8');
        try {
            list($q, $c) = $this->pedido();
            $r = $this->cz->calcular($q, $c, $this->cliente, $this->k_para($c));
            echo json_encode(['success' => TRUE] + $r, JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            echo json_encode(['success' => FALSE, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** GET ?q=&c=<json> &formato=xlsx|csv */
    public function exportar() {
        list($q, $c) = $this->pedido(TRUE);
        $r = $this->cz->calcular($q, $c, $this->cliente, $this->k_para($c));
        auditar('exportar', 'cruzamento', $q->id, 'Cruzamento exportado (' . ($this->input->get('formato') === 'csv' ? 'CSV' : 'Excel') . '): ' . $r['variaveis']['linha'] . ($r['variaveis']['coluna'] ? ' x ' . $r['variaveis']['coluna'] : ''), NULL, $c);
        $nome = 'cruzamento_' . $q->id . '_' . date('Ymd_His');
        $blocos = $this->blocos($r);
        if ($this->input->get('formato') === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nome . '.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($this->cabecalho($q, $r) as $l) { fputcsv($out, [$l], ';'); }
            foreach ($blocos as $titulo => $tab) {
                fputcsv($out, [], ';');
                fputcsv($out, [$titulo], ';');
                foreach ($tab as $linha) { fputcsv($out, array_map([$this, 'csv'], $linha), ';'); }
            }
            fclose($out);
            exit;
        }
        require_once FCPATH . 'vendor/autoload.php';
        $pl = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $aba = $pl->getActiveSheet();
        $aba->setTitle('Cruzamento');
        $lin = 1;
        foreach ($this->cabecalho($q, $r) as $l) {
            $aba->setCellValue('A' . $lin++, $l);
        }
        $aba->getStyle('A1')->getFont()->setBold(TRUE)->setSize(13);
        foreach ($blocos as $titulo => $tab) {
            $lin++;
            $aba->setCellValue('A' . $lin, $titulo);
            $aba->getStyle('A' . $lin)->getFont()->setBold(TRUE);
            $lin++;
            foreach ($tab as $i => $linha) {
                foreach (array_values($linha) as $j => $v) {
                    $aba->setCellValueByColumnAndRow($j + 1, $lin, $v === NULL ? '<' . $r['k_minimo'] : $v);
                }
                if ($i === 0) {
                    $aba->getStyle('A' . $lin . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($linha)) . $lin)->getFont()->setBold(TRUE);
                }
                $lin++;
            }
        }
        $aba->getColumnDimension('A')->setWidth(40);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nome . '.xlsx"');
        $w = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($pl, 'Xlsx');
        $w->save('php://output');
        exit;
    }

    /** POST (JSON): texto de interpretação pela IA (só equipe). */
    public function interpretar() {
        header('Content-Type: application/json; charset=utf-8');
        if ($this->cliente) {
            echo json_encode(['success' => FALSE, 'error' => 'Indisponível para o seu perfil.']);
            return;
        }
        try {
            list($q, $c) = $this->pedido();
            $r = $this->cz->calcular($q, $c, FALSE, 0);
        } catch (InvalidArgumentException $e) {
            echo json_encode(['success' => FALSE, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            return;
        }
        $texto = implode("\n", $this->cabecalho($q, $r)) . "\n\n";
        foreach ($this->blocos($r) as $titulo => $tab) {
            if (strpos($titulo, 'Contagem') === 0 || strpos($titulo, '% na linha') === 0 || strpos($titulo, 'Média') === 0) {
                $texto .= $titulo . "\n";
                foreach ($tab as $linha) { $texto .= implode(' | ', array_map(function ($v) { return $v === NULL ? '-' : $v; }, $linha)) . "\n"; }
                $texto .= "\n";
            }
        }
        $this->load->library('ai_service');
        $res = $this->ai_service->chat_completion('statistical_analysis', [
            ['role' => 'system', 'content' => 'Você é um estatístico que explica resultados de pesquisas socioeconômicas para gestores, em português do Brasil. '
                . 'Escreva de 2 a 4 parágrafos curtos: o que a tabela mostra, as diferenças mais relevantes entre as categorias (cite percentuais), '
                . 'o que o teste qui-quadrado indica (se houver) e cuidados de interpretação (base pequena, células com poucos casos, múltipla escolha). '
                . 'Não invente dados que não estão na tabela. Sem títulos nem listas.'],
            ['role' => 'user', 'content' => $texto],
        ], ['resource_type' => 'questionnaire', 'resource_id' => $q->id]);
        echo json_encode($res['success'] ? ['success' => TRUE, 'texto' => trim($res['content']), 'log_id' => $res['log_id'],
            'modelo' => ($res['provider'] ?? '') . ' ' . $res['model']] : ['success' => FALSE, 'error' => $res['error']], JSON_UNESCAPED_UNICODE);
    }

    public function salvar() {
        $this->so_equipe();
        list($q, $c) = $this->pedido();
        $titulo = trim((string) $this->input->post('titulo'));
        if ($titulo === '') {
            $titulo = 'Cruzamento ' . date('d/m/Y H:i');
        }
        $id = $this->cz->salvar((int) $this->input->post('id'), $q->id, $titulo, $c, trim((string) $this->input->post('interpretacao')),
            $this->session->userdata('admin_id'), (int) $this->input->post('ia_log_id') ?: NULL);
        auditar('criar', 'cruzamento', $id, 'Análise salva: ' . $titulo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => TRUE, 'id' => $id]);
    }

    public function liberar($id) {
        $this->so_equipe();
        $s = $this->cz->salva($id);
        if (!$s) { show_404(); }
        $sim = !in_array($s->liberado_cliente, [TRUE, 't'], TRUE);
        $this->cz->liberar($id, $sim);
        auditar('editar', 'cruzamento', $id, ($sim ? 'Liberada ao cliente: ' : 'Retirada do cliente: ') . $s->titulo);
        $this->session->set_flashdata('success', $sim ? 'Análise liberada ao cliente.' : 'Análise retirada do cliente.');
        redirect($this->input->post('voltar') === 'q' ? 'cruzamentos/q/' . (int) $s->questionnaire_id : 'cruzamentos');
    }

    public function excluir($id) {
        $this->so_equipe();
        $s = $this->cz->salva($id);
        if ($s) {
            $this->cz->excluir($id);
            auditar('excluir', 'cruzamento', $id, 'Análise excluída: ' . $s->titulo);
        }
        $this->session->set_flashdata('success', 'Análise excluída.');
        redirect('cruzamentos');
    }

    // ---------------- apoio ----------------

    private function pedido($get = FALSE) {
        $fonte = $get ? $this->input->get() : $this->input->post();
        $q = $this->questionario($fonte['q'] ?? 0);
        $c = json_decode((string) ($fonte['c'] ?? ''), TRUE);
        if (!is_array($c)) {
            throw new InvalidArgumentException('Pedido inválido.');
        }
        $limpo = [
            'unidade' => ($c['unidade'] ?? '') === 'morador' ? 'morador' : 'domicilio',
            'linha' => (string) ($c['linha'] ?? ''), 'coluna' => (string) ($c['coluna'] ?? ''), 'medida' => (string) ($c['medida'] ?? ''),
            'faixas' => max(2, min(10, (int) ($c['faixas'] ?? 5))),
            'incluir_vazios' => !empty($c['incluir_vazios']),
            'como_cliente' => !empty($c['como_cliente']),
            'filtros' => [
                'de' => (string) ($c['filtros']['de'] ?? ''), 'ate' => (string) ($c['filtros']['ate'] ?? ''),
                'aplicador' => $this->cliente ? 0 : (int) ($c['filtros']['aplicador'] ?? 0),
                'var' => (string) ($c['filtros']['var'] ?? ''), 'valor' => (string) ($c['filtros']['valor'] ?? ''),
            ],
        ];
        return [$q, $limpo];
    }

    private function questionario($qid) {
        $q = $this->cz->questionario($qid);
        if (!$q || ($this->cliente && !in_array((int) $q->project_id, $this->projetos, TRUE))) {
            show_404();
        }
        return $q;
    }

    private function k() {
        $this->load->model('Settings_model');
        $k = $this->Settings_model->get_setting('cruzamento_k_minimo');
        return is_numeric($k) && (int) $k >= 0 ? (int) $k : 5;
    }

    /** Cliente sempre com supressão; a equipe pode ver "como o cliente vê". */
    private function k_para($c) {
        return ($this->cliente || !empty($c['como_cliente'])) ? $this->k() : 0;
    }

    private function cabecalho($q, $r) {
        $v = $r['variaveis'];
        $l = [$q->title, 'Linhas: ' . $v['linha']];
        if ($v['coluna']) { $l[] = 'Colunas: ' . $v['coluna']; }
        if ($v['medida']) { $l[] = 'Medida: ' . $v['medida']; }
        if ($v['filtro']) { $l[] = 'Filtro: ' . $v['filtro']; }
        $l[] = 'Unidade: ' . ($r['unidade'] === 'morador' ? 'morador' : 'entrevista/domicílio') . ' · base ' . $r['base'] . ' · ' . $r['n'] . ' com resposta'
            . ($r['multipla'] ? ' · múltipla escolha (contagem de menções)' : '');
        if ($r['qui']) {
            $l[] = sprintf('Qui-quadrado = %s; gl = %d; p = %s; V de Cramér = %s%s', number_format($r['qui']['qui2'], 2, ',', '.'), $r['qui']['gl'],
                $r['qui']['p'] < 0.001 ? '< 0,001' : number_format($r['qui']['p'], 3, ',', '.'), number_format($r['qui']['v_cramer'], 3, ',', '.'),
                $r['qui']['aviso'] ? ' (' . $r['qui']['aviso'] . ')' : '');
        }
        if ($r['suprimidas']) { $l[] = 'Células com menos de ' . $r['k_minimo'] . ' respostas foram ocultadas.'; }
        $l[] = 'Gerado em ' . date('d/m/Y H:i') . ' - SXData';
        return $l;
    }

    /** Tabelas para exportar: título => linhas (a primeira é o cabeçalho). */
    private function blocos($r) {
        $cab = array_merge([''], $r['colunas'], ['Total']);
        $mk = function ($fonte, $com_total) use ($r, $cab) {
            $t = [$cab];
            foreach ($r['linhas'] as $i => $rot) {
                $t[] = array_merge([$rot], $fonte[$i] ?? [], $com_total ? [$r['total_linha'][$i]] : ['']);
            }
            if ($com_total) {
                $t[] = array_merge(['Total'], $r['total_coluna'], [$r['total']]);
            }
            return $t;
        };
        $b = ['Contagem' => $mk($r['contagem'], TRUE)];
        if (count($r['colunas']) > 1) {
            $b['% na linha'] = $mk($r['pct_linha'], FALSE);
            $b['% na coluna'] = $mk($r['pct_coluna'], FALSE);
        }
        $b['% do total'] = $mk($r['pct_total'], FALSE);
        if ($r['media'] !== NULL) {
            $b['Média: ' . $r['variaveis']['medida']] = $mk($r['media'], FALSE);
            $b['Mediana: ' . $r['variaveis']['medida']] = $mk($r['mediana'], FALSE);
        }
        return $b;
    }

    private function csv($v) {
        return is_float($v) ? number_format($v, 2, ',', '') : ($v === NULL ? '-' : $v);
    }

    private function so_equipe() {
        if ($this->cliente || $this->input->method() !== 'post') {
            show_error('Acesso negado', 403);
        }
    }

    private function tela($view, $dados) {
        $this->load->view('admin/header', $dados);
        $this->load->view('admin/cruzamentos/' . $view, $dados);
        $this->load->view('admin/footer');
    }
}
