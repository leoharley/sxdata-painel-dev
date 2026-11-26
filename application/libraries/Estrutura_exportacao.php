<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once __DIR__ . '/Estrutura_motor.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exportação Excel de um formulário estruturado: aba Domicílios (uma linha
 * por entrevista, com renda total e per capita e a marca de incompleta), aba
 * Moradores (uma linha por morador, com o ID da entrevista) e Dicionário.
 */
class Estrutura_exportacao {

    /** @return string caminho do arquivo .xlsx temporário */
    public function planilha($q, array $definicao, array $dados) {
        $motor = new Estrutura_motor($definicao);
        $planilha = new Spreadsheet();

        // ---- Domicílios
        $dom = $planilha->getActiveSheet();
        $dom->setTitle('Domicílios');
        $cols_dom = $this->colunas($motor, 'domicilio');
        $cab = ['ID entrevista', 'client_id', 'Aplicador', 'Início', 'Conclusão', 'Latitude', 'Longitude', 'Versão',
            'Revisão', 'Alertas', 'Nº de moradores'];
        foreach ($cols_dom as $c) { $cab[] = $c['titulo']; }
        $linhas = [$cab];
        $n_moradores = [];
        foreach ($dados['moradores'] as $m) { $n_moradores[$m->form_response_id] = ($n_moradores[$m->form_response_id] ?? 0) + 1; }
        foreach ($dados['entrevistas'] as $e) {
            $alertas = json_decode((string) $e->alertas, TRUE) ?: [];
            $l = [(int) $e->id, $e->client_id, $e->aplicador, $e->started_at, $e->completed_at,
                $e->latitude !== NULL ? (float) $e->latitude : NULL, $e->longitude !== NULL ? (float) $e->longitude : NULL,
                $e->versao, $e->revisao_status === 'pendente' ? 'Pendente' : '',
                implode(' | ', array_unique(array_column($alertas, 'mensagem'))), $n_moradores[$e->id] ?? 0];
            $resp = $dados['respostas']['f' . $e->id] ?? [];
            foreach ($cols_dom as $c) { $l[] = $this->celula($c, $resp[$c['codigo']] ?? NULL); }
            $linhas[] = $l;
        }
        $dom->fromArray($linhas, NULL, 'A1', TRUE);

        // ---- Moradores
        if ($motor->tem_moradores()) {
            $mor = $planilha->createSheet();
            $mor->setTitle('Moradores');
            $cols_m = $this->colunas($motor, 'morador');
            $cab = ['ID entrevista', 'client_id da entrevista', 'Ordem', 'Nome', 'Status'];
            foreach ($cols_m as $c) { $cab[] = $c['titulo']; }
            $client = [];
            foreach ($dados['entrevistas'] as $e) { $client[$e->id] = $e->client_id; }
            $linhas = [$cab];
            foreach ($dados['moradores'] as $m) {
                $l = [(int) $m->form_response_id, $client[$m->form_response_id] ?? '', (int) $m->ordem, $m->nome, $m->status];
                $resp = $dados['respostas']['m' . $m->id] ?? [];
                foreach ($cols_m as $c) { $l[] = $this->celula($c, $resp[$c['codigo']] ?? NULL); }
                $linhas[] = $l;
            }
            $mor->fromArray($linhas, NULL, 'A1', TRUE);
        }

        // ---- Dicionário
        $dic = $planilha->createSheet();
        $dic->setTitle('Dicionário');
        $linhas = [['Código', 'Número', 'Nível', 'Bloco', 'Seção', 'Pergunta', 'Tipo', 'Opções (código = texto)', 'Exibir se', 'Fórmula']];
        foreach (['domicilio', 'morador'] as $nivel) {
            foreach ($motor->sequencia($nivel) as $codigo) {
                $p = $motor->pergunta($codigo);
                $op = implode("\n", array_map(function ($o) { return $o['valor'] . ' = ' . $o['texto']; }, $p['opcoes'] ?? []));
                $linhas[] = [$codigo, $p['numero'] ?? '', $nivel === 'morador' ? 'Morador' : 'Domicílio', $p['bloco'] ?? '',
                    $p['secao'] ?? '', $p['texto'], $p['tipo'], $op, $p['exibir_se'] ?? '', $p['formula'] ?? ''];
            }
        }
        $dic->fromArray($linhas, NULL, 'A1', TRUE);

        foreach ($planilha->getAllSheets() as $aba) {
            $aba->freezePane('A2');
            $aba->getStyle('1:1')->getFont()->setBold(TRUE);
        }
        $planilha->setActiveSheetIndex(0);
        $arquivo = tempnam(sys_get_temp_dir(), 'sxest') . '.xlsx';
        (new Xlsx($planilha))->save($arquivo);
        return $arquivo;
    }

    /** Colunas de um nível; cada calculado ganha também a coluna "incompleta". */
    private function colunas(Estrutura_motor $motor, $nivel) {
        $cols = [];
        foreach ($motor->sequencia($nivel) as $codigo) {
            $p = $motor->pergunta($codigo);
            if ($p['tipo'] === 'mensagem') { continue; }
            $rotulo = ($p['numero'] !== '' ? $p['numero'] . ' - ' : '') . mb_substr(str_replace('[NOME]', '...', $p['texto']), 0, 80);
            $cols[] = ['codigo' => $codigo, 'p' => $p, 'titulo' => $rotulo, 'parte' => 'valor'];
            if ($p['tipo'] === 'calculado') {
                $cols[] = ['codigo' => $codigo, 'p' => $p, 'titulo' => ($p['numero'] ?: $codigo) . ' - incompleta?', 'parte' => 'incompleto'];
            }
        }
        return $cols;
    }

    private function celula(array $c, $resp) {
        if ($resp === NULL) { return NULL; }
        if ($c['parte'] === 'incompleto') { return !empty($resp['incompleto']) ? 'Sim' : 'Não'; }
        $v = Estrutura_motor::rotulo_resposta($c['p'], $resp, TRUE);
        return $v === '' ? NULL : $v;
    }
}
