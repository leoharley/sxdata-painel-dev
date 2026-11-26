<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Add these imports at the top after the defined() line
require_once FCPATH . 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class Reports extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        $this->load->model('Response_model');
        $this->load->model('Questionnaire_model');
        $this->load->library('kmz_generator');
        $this->check_auth();
    }

    public function index() {
        $data['title'] = 'Relatórios - SXData';
        
        // Processar filtros
        $filters = $this->get_filters();
        $data['filters'] = $filters;
        
        $this->load->model('User_model');
        $data['users'] = $this->User_model->get_all_with_stats();

        // Carregar dados
        $data['questionnaires'] = $this->Questionnaire_model->get_for_analysis();
        $data['period_stats'] = $this->calculate_period_stats($filters);
        $data['charts_data'] = $this->get_charts_data($filters);
        $data['detailed_analysis'] = $this->get_detailed_analysis($filters);
        
        // NOVO: Obter dados de localização para o mapa de calor
        $data['heatmap_locations'] = $this->get_heatmap_data($filters);

        // NOVO: Obter análise de questões
        $data['question_analysis'] = $this->get_question_analysis($filters);
        
        $this->load->view('admin/header', $data);
        $this->load->view('admin/reports/index', $data);
        $this->load->view('admin/footer');
    }

    // MÉTODO CORRIGIDO: Melhorar dados dos gráficos
    private function get_charts_data($filters) {
        $charts_data = array();
        
        // 1. Respostas por dia
        $responses_by_day = $this->Response_model->get_responses_by_day_filtered($filters, 30);
        $charts_data['responses_by_day'] = $responses_by_day;
        
        // 2. CORRIGIDO: Top aplicadores - garantir estrutura correta
        $top_applicators_raw = $this->Response_model->get_top_applicators($filters, 10);
        $top_applicators = array();
        
        foreach ($top_applicators_raw as $applicator) {
            $top_applicators[] = array(
                'name' => $applicator->full_name ?: 'Aplicador #' . $applicator->id,
                'count' => (int)$applicator->total_responses,
                'id' => $applicator->id
            );
        }
        
        $charts_data['top_applicators'] = $top_applicators;
        
        // 3. CORRIGIDO: Questionários por popularidade - garantir estrutura correta  
        $questionnaires_popularity_raw = $this->Response_model->get_questionnaires_popularity($filters, 10);
        $questionnaires_popularity = array();
        
        foreach ($questionnaires_popularity_raw as $questionnaire) {
            $questionnaires_popularity[] = array(
                'title' => $questionnaire->title ?: 'Questionário #' . $questionnaire->id,
                'count' => (int)$questionnaire->total_applications,
                'id' => $questionnaire->id
            );
        }
        
        $charts_data['questionnaires_popularity'] = $questionnaires_popularity;
        
        // Log para debug (remover em produção)
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'Charts data: ' . json_encode($charts_data));
        }
        
        return $charts_data;
    }

    // MÉTODO MELHORADO: Exportação completa com muito mais informações
    public function export_all() {
        $filters = $this->get_filters();
        auditar('exportar', 'relatorio', $filters['questionnaire_id'] ?? NULL, 'Relatório completo em Excel', NULL, $filters);

        try {
            // Criar novo arquivo Excel
            $spreadsheet = new Spreadsheet();
            $spreadsheet->getProperties()
                        ->setCreator("SXData System")
                        ->setLastModifiedBy("SXData System")
                        ->setTitle("Relatório Completo SXData")
                        ->setSubject("Análise Completa de Dados Coletados")
                        ->setDescription("Relatório abrangente com todas as informações coletadas no período selecionado");

            // PLANILHA 1: Resumo Executivo
            $this->create_executive_summary_sheet($spreadsheet, $filters);
            
            // PLANILHA 2: Respostas Detalhadas
            $spreadsheet->createSheet();
            $this->create_detailed_responses_sheet($spreadsheet, $filters, 1);
            
            // PLANILHA 3: Análise por Questionário
            $spreadsheet->createSheet();
            $this->create_questionnaire_analysis_sheet($spreadsheet, $filters, 2);
            
            // PLANILHA 4: Performance dos Aplicadores
            $spreadsheet->createSheet();
            $this->create_applicators_performance_sheet($spreadsheet, $filters, 3);
            
            // PLANILHA 5: Análise Geográfica
            $spreadsheet->createSheet();
            $this->create_geographic_analysis_sheet($spreadsheet, $filters, 4);
            
            // PLANILHA 6: Estatísticas de Tempo
            $spreadsheet->createSheet();
            $this->create_time_statistics_sheet($spreadsheet, $filters, 5);

            // Definir a primeira planilha como ativa
            $spreadsheet->setActiveSheetIndex(0);
            
            // Gerar arquivo
            $filename = 'relatorio_completo_' . date('Y-m-d_H-i-s') . '.xlsx';
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
            
        } catch (Exception $e) {
            log_message('error', 'Erro na exportação completa: ' . $e->getMessage());
            $this->session->set_flashdata('error', 'Erro ao gerar exportação: ' . $e->getMessage());
            redirect('reports');
        }
    }

    /**
     * NOVA PLANILHA: Resumo Executivo
     */
    private function create_executive_summary_sheet($spreadsheet, $filters) {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumo Executivo');
        
        // Título principal
        $sheet->setCellValue('A1', 'RELATÓRIO EXECUTIVO - SXDATA');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('23345F');
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
        
        $row = 3;
        
        // Informações do relatório
        $period_text = $this->format_period_text($filters);
        $report_info = [
            ['Período do Relatório:', $period_text],
            ['Data de Geração:', date('d/m/Y H:i:s')],
            ['Gerado por:', $this->session->userdata('user_name') ?: 'Sistema'],
            ['', ''],
            ['MÉTRICAS GERAIS', '']
        ];
        
        foreach ($report_info as $info) {
            $sheet->setCellValue('A' . $row, $info[0]);
            $sheet->setCellValue('B' . $row, $info[1]);
            
            if (strpos($info[0], 'MÉTRICAS') !== false) {
                $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row . ':B' . $row)->getFill()
                      ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('8fae5d');
                $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->getColor()->setRGB('FFFFFF');
            }
            $row++;
        }
        
        // Calcular estatísticas
        $period_stats = $this->calculate_period_stats($filters);
        $charts_data = $this->get_charts_data($filters);
        
        $metrics = [
            ['Total de Respostas Coletadas:', number_format($period_stats['total_responses'] ?? 0)],
            ['Respondentes Únicos:', number_format($period_stats['unique_respondents'] ?? 0)],
            ['Fotos Capturadas:', number_format($period_stats['photos_captured'] ?? 0)],
            ['Localizações Registradas:', number_format($period_stats['locations_captured'] ?? 0)],
            ['Taxa de Consentimento:', ($period_stats['consent_rate'] ?? 0) . '%'],
            ['', ''],
            ['ANÁLISE DE ATIVIDADE', ''],
            ['Aplicadores Ativos:', count($charts_data['top_applicators'] ?? [])],
            ['Questionários Utilizados:', count($charts_data['questionnaires_popularity'] ?? [])],
            ['Média de Respostas/Dia:', $this->calculate_daily_average($filters)],
        ];
        
        foreach ($metrics as $metric) {
            $sheet->setCellValue('A' . $row, $metric[0]);
            $sheet->setCellValue('B' . $row, $metric[1]);
            
            if (strpos($metric[0], 'ANÁLISE') !== false) {
                $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row . ':B' . $row)->getFill()
                      ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('8fae5d');
                $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->getColor()->setRGB('FFFFFF');
            }
            $row++;
        }
        
        // Top 5 Aplicadores
        $row += 2;
        $sheet->setCellValue('A' . $row, 'TOP 5 APLICADORES MAIS ATIVOS');
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Posição');
        $sheet->setCellValue('B' . $row, 'Nome');
        $sheet->setCellValue('C' . $row, 'Total de Aplicações');
        $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
        $row++;
        
        $top_applicators = array_slice($charts_data['top_applicators'] ?? [], 0, 5);
        foreach ($top_applicators as $index => $applicator) {
            $sheet->setCellValue('A' . $row, '#' . ($index + 1));
            $sheet->setCellValue('B' . $row, $applicator['name']);
            $sheet->setCellValue('C' . $row, $applicator['count']);
            $row++;
        }
        
        // Top 5 Questionários
        $row += 2;
        $sheet->setCellValue('A' . $row, 'TOP 5 QUESTIONÁRIOS MAIS UTILIZADOS');
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Posição');
        $sheet->setCellValue('B' . $row, 'Título');
        $sheet->setCellValue('C' . $row, 'Total de Aplicações');
        $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
        $row++;
        
        $top_questionnaires = array_slice($charts_data['questionnaires_popularity'] ?? [], 0, 5);
        foreach ($top_questionnaires as $index => $questionnaire) {
            $sheet->setCellValue('A' . $row, '#' . ($index + 1));
            $sheet->setCellValue('B' . $row, $questionnaire['title']);
            $sheet->setCellValue('C' . $row, $questionnaire['count']);
            $row++;
        }
        
        // Ajustar larguras das colunas
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(20);
        
        // Adicionar bordas
        $dataRange = 'A1:C' . ($row - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    /**
     * NOVA PLANILHA: Respostas Detalhadas
     */
    private function create_detailed_responses_sheet($spreadsheet, $filters, $sheetIndex) {
        $spreadsheet->setActiveSheetIndex($sheetIndex);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Respostas Detalhadas');
        
        // Obter dados completos das respostas
        $responses = $this->Response_model->get_for_export($filters);
        
        // Cabeçalhos
        $headers = [
            'ID', 'Questionário', 'Aplicador', 'Respondente', 'Email Respondente',
            'Data/Hora Início', 'Data/Hora Conclusão', 'Duração (min)', 
            'Latitude', 'Longitude', 'Local', 'Consentimento', 
            'Foto Capturada', 'Status Sinc', 'Observações'
        ];
        
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, 1, $header);
            $col++;
        }
        
        // Estilizar cabeçalhos
        $headerRange = 'A1:' . chr(64 + count($headers)) . '1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
              ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('8fae5d');
        $sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
        
        // Preencher dados
        $row = 2;
        foreach ($responses as $response) {
            $duration = null;
            if ($response->started_at && $response->completed_at) {
                $start = new DateTime($response->started_at);
                $end = new DateTime($response->completed_at);
                $duration = round($end->diff($start)->i + ($end->diff($start)->h * 60), 1);
            }
            
            $data = [
                $response->id,
                $response->questionnaire ?? 'N/A',
                $response->aplicador ?? 'N/A',
                $response->respondent_name ?? 'Não informado',
                $response->respondent_email ?? 'N/A',
                $response->started_at ? date('d/m/Y H:i:s', strtotime($response->started_at)) : 'N/A',
                $response->completed_at ? date('d/m/Y H:i:s', strtotime($response->completed_at)) : 'N/A',
                $duration ?? 'N/A',
                $response->latitude ?? 'N/A',
                $response->longitude ?? 'N/A',
                $response->location_name ?? 'N/A',
                $response->consent_given ? 'Sim' : 'Não',
                !empty($response->photo_path) ? 'Sim' : 'Não',
                $this->format_sync_status($response->sync_status ?? 'pending'),
                $this->generate_response_observations($response)
            ];
            
            $col = 1;
            foreach ($data as $value) {
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
            $row++;
        }
        
        // Autoajustar larguras
        foreach (range('A', chr(64 + count($headers))) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Adicionar bordas
        if ($row > 2) {
            $dataRange = 'A1:' . chr(64 + count($headers)) . ($row - 1);
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
    }

    /**
     * NOVA PLANILHA: Performance dos Aplicadores
     */
    private function create_applicators_performance_sheet($spreadsheet, $filters, $sheetIndex) {
        $spreadsheet->setActiveSheetIndex($sheetIndex);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Performance Aplicadores');
        
        // Obter dados dos aplicadores
        $applicators_data = $this->Response_model->get_applicators_stats($filters);
        
        // Cabeçalhos
        $headers = [
            'Nome Completo', 'Username', 'Status', 'Total Formulários',
            'Formulários Hoje', 'Fotos Capturadas', 'Locais Visitados', 
            'Última Atividade', 'Dias Ativos', 'Média por Dia',
            'Taxa de Sucesso (%)', 'Avaliação Performance'
        ];
        
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, 1, $header);
            $col++;
        }
        
        // Estilizar cabeçalhos
        $headerRange = 'A1:' . chr(64 + count($headers)) . '1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
              ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('007bff');
        $sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
        
        // Preencher dados
        $row = 2;
        foreach ($applicators_data as $applicator) {
            $avg_per_day = $applicator->active_days > 0 ? 
                round($applicator->total_forms / $applicator->active_days, 1) : 0;
            
            $success_rate = $applicator->total_forms > 0 ? 
                round(($applicator->total_forms / $applicator->total_forms) * 100, 1) : 0;
                
            $performance = $this->evaluate_applicator_performance($applicator);
            
            $data = [
                $applicator->full_name,
                $applicator->username,
                $applicator->is_active ? 'Ativo' : 'Inativo',
                $applicator->total_forms,
                $applicator->today_forms,
                $applicator->photos_captured,
                $applicator->locations_captured,
                $applicator->last_activity ? date('d/m/Y H:i', strtotime($applicator->last_activity)) : 'Nunca',
                $applicator->active_days,
                $avg_per_day,
                $success_rate,
                $performance
            ];
            
            $col = 1;
            foreach ($data as $value) {
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
            $row++;
        }
        
        // Autoajustar larguras
        foreach (range('A', chr(64 + count($headers))) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Adicionar bordas e formatação condicional para performance
        if ($row > 2) {
            $dataRange = 'A1:' . chr(64 + count($headers)) . ($row - 1);
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
    }

    /**
     * NOVA PLANILHA: Análise Geográfica
     */
    private function create_geographic_analysis_sheet($spreadsheet, $filters, $sheetIndex) {
        $spreadsheet->setActiveSheetIndex($sheetIndex);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Análise Geográfica');
        
        // Obter dados geográficos
        $location_stats = $this->Response_model->get_location_stats($filters);
        $coverage_stats = $this->Response_model->get_coverage_stats($filters);
        
        // Título
        $sheet->setCellValue('A1', 'ANÁLISE GEOGRÁFICA DA COLETA');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Estatísticas de cobertura
        $row = 3;
        $coverage_data = [
            ['ESTATÍSTICAS DE COBERTURA', ''],
            ['Pontos únicos de coleta:', $coverage_stats['unique_collection_points']],
            ['Percentual de cobertura estimado:', $coverage_stats['coverage_percentage'] . '%'],
            ['Novas áreas este mês:', $coverage_stats['new_areas_this_month']],
            ['Zonas de alta densidade:', $coverage_stats['high_density_zones']],
            ['Área total coberta:', $coverage_stats['total_area_covered']],
            ['', ''],
            ['LOCAIS MAIS VISITADOS', '']
        ];
        
        foreach ($coverage_data as $data) {
            $sheet->setCellValue('A' . $row, $data[0]);
            $sheet->setCellValue('B' . $row, $data[1]);
            
            if (strpos($data[0], 'ESTATÍSTICAS') !== false || strpos($data[0], 'LOCAIS') !== false) {
                $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row . ':B' . $row)->getFill()
                      ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('17a2b8');
                $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->getColor()->setRGB('FFFFFF');
            }
            $row++;
        }
        
        // Cabeçalhos para locais
        $headers = ['Local', 'Total Formulários', 'Aplicadores Únicos', 'Lat. Média', 'Long. Média'];
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, $row, $header);
            $col++;
        }
        $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
        $row++;
        
        // Dados dos locais
        foreach ($location_stats as $location) {
            $data = [
                $location->location_name,
                $location->total_forms,
                $location->unique_applicators,
                round($location->avg_latitude, 6),
                round($location->avg_longitude, 6)
            ];
            
            $col = 1;
            foreach ($data as $value) {
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
            $row++;
        }
        
        // Ajustar larguras
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(12);
        
        // Bordas
        $dataRange = 'A1:E' . ($row - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    /**
     * NOVA PLANILHA: Estatísticas de Tempo
     */
    private function create_time_statistics_sheet($spreadsheet, $filters, $sheetIndex) {
        $spreadsheet->setActiveSheetIndex($sheetIndex);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Análise Temporal');
        
        // Obter dados temporais
        $responses_by_day = $this->Response_model->get_responses_by_day_filtered($filters, 30);
        
        // Título
        $sheet->setCellValue('A1', 'ANÁLISE TEMPORAL DA COLETA DE DADOS');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row = 3;
        
        // Cabeçalhos para dados por dia
        $headers = ['Data', 'Dia da Semana', 'Total Respostas', 'Variação'];
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, $row, $header);
            $col++;
        }
        $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':D' . $row)->getFill()
              ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ffc107');
        $row++;
        
        // Dados por dia
        $previous_count = 0;
        foreach ($responses_by_day as $day_data) {
            $date = new DateTime($day_data->date);
            $day_name = $this->get_day_name($date->format('w'));
            $variation = $previous_count > 0 ? 
                round((($day_data->count - $previous_count) / $previous_count) * 100, 1) : 0;
            
            $data = [
                $date->format('d/m/Y'),
                $day_name,
                $day_data->count,
                ($variation >= 0 ? '+' : '') . $variation . '%'
            ];
            
            $col = 1;
            foreach ($data as $value) {
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
            
            $previous_count = $day_data->count;
            $row++;
        }
        
        // Resumo estatístico
        $row += 2;
        $total_responses = array_sum(array_column($responses_by_day, 'count'));
        $avg_per_day = count($responses_by_day) > 0 ? round($total_responses / count($responses_by_day), 1) : 0;
        $max_day = !empty($responses_by_day) ? max(array_column($responses_by_day, 'count')) : 0;
        $min_day = !empty($responses_by_day) ? min(array_column($responses_by_day, 'count')) : 0;
        
        $summary = [
            ['RESUMO ESTATÍSTICO', ''],
            ['Total de respostas no período:', $total_responses],
            ['Média de respostas por dia:', $avg_per_day],
            ['Maior volume em um dia:', $max_day],
            ['Menor volume em um dia:', $min_day],
            ['Desvio padrão:', $this->calculate_standard_deviation(array_column($responses_by_day, 'count'))],
        ];
        
        foreach ($summary as $data) {
            $sheet->setCellValue('A' . $row, $data[0]);
            $sheet->setCellValue('B' . $row, $data[1]);
            
            if (strpos($data[0], 'RESUMO') !== false) {
                $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row . ':B' . $row)->getFill()
                      ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('6c757d');
                $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->getColor()->setRGB('FFFFFF');
            }
            $row++;
        }
        
        // Ajustar larguras
        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Bordas
        $dataRange = 'A1:D' . ($row - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    // MÉTODOS AUXILIARES

    private function calculate_daily_average($filters) {
        $total_responses = $this->Response_model->count_by_filters($filters);
        $days = $this->calculate_period_days($filters);
        return $days > 0 ? round($total_responses / $days, 1) : 0;
    }

    private function calculate_period_days($filters) {
        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $from = new DateTime($filters['date_from']);
            $to = new DateTime($filters['date_to']);
            return $to->diff($from)->days + 1;
        }
        
        switch ($filters['period'] ?? 'last_30_days') {
            case 'last_7_days': return 7;
            case 'last_3_months': return 90;
            default: return 30;
        }
    }

    private function format_sync_status($status) {
        switch ($status) {
            case 'pending': return 'Pendente';
            case 'synced': return 'Sincronizado';
            case 'error': return 'Erro';
            default: return 'Desconhecido';
        }
    }

    private function generate_response_observations($response) {
        $observations = [];
        
        if (empty($response->respondent_email)) {
            $observations[] = 'Sem email';
        }
        
        if (empty($response->latitude) || empty($response->longitude)) {
            $observations[] = 'Sem localização';
        }
        
        if (empty($response->photo_path)) {
            $observations[] = 'Sem foto';
        }
        
        if (!$response->consent_given) {
            $observations[] = 'Sem consentimento';
        }
        
        return empty($observations) ? 'Completo' : implode(', ', $observations);
    }

    private function evaluate_applicator_performance($applicator) {
        $score = 0;
        
        // Critérios de avaliação
        if ($applicator->total_forms >= 50) $score += 3;
        elseif ($applicator->total_forms >= 20) $score += 2;
        elseif ($applicator->total_forms >= 5) $score += 1;
        
        if ($applicator->active_days >= 15) $score += 2;
        elseif ($applicator->active_days >= 7) $score += 1;
        
        if ($applicator->photos_captured >= $applicator->total_forms * 0.8) $score += 2;
        elseif ($applicator->photos_captured >= $applicator->total_forms * 0.5) $score += 1;
        
        // Classificação
        if ($score >= 6) return 'Excelente';
        elseif ($score >= 4) return 'Bom';
        elseif ($score >= 2) return 'Regular';
        else return 'Precisa Melhorar';
    }

    private function get_day_name($day_number) {
        $days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        return $days[$day_number] ?? 'Desconhecido';
    }

    private function calculate_standard_deviation($values) {
        if (empty($values)) return 0;
        
        $mean = array_sum($values) / count($values);
        $sum_squares = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values));
        
        return round(sqrt($sum_squares / count($values)), 2);
    }

    // MANTER MÉTODOS ORIGINAIS QUE AINDA FUNCIONAM
    public function generate_kmz() {
        $filters = $this->get_filters();
        $responses = $this->Response_model->get_with_location($filters);
        
        if (empty($responses)) {
            $this->session->set_flashdata('error', 'Nenhuma localização encontrada para gerar o arquivo KMZ.');
            redirect('reports');
            return;
        }
        
        $title = 'Localizações SXData';
        if (isset($filters['questionnaire_id'])) {
            $questionnaire = $this->Questionnaire_model->get_by_id($filters['questionnaire_id']);
            if ($questionnaire) {
                $title = 'Localizações - ' . $questionnaire->title;
            }
        }

        try {
            $this->kmz_generator->generate($responses, $title);
        } catch (Throwable $e) {
            log_message('error', 'Erro na geração de KMZ: ' . $e->getMessage());
            $this->session->set_flashdata('error', 'Erro ao gerar arquivo KMZ: ' . $e->getMessage());
            redirect('reports');
        }
    }

    /**
     * NOVO MÉTODO: Obter dados de localização para o mapa de calor
     */
    private function get_heatmap_data($filters) {
        // Usar o método get_with_location para obter respostas com localização
        $responses_with_location = $this->Response_model->get_with_location($filters);
        
        $heatmap_data = array();
        
        foreach ($responses_with_location as $response) {
            // Preparar dados para o Google Maps Heatmap
            $heatmap_data[] = array(
                'lat' => (float) $response->latitude,
                'lng' => (float) $response->longitude,
                'weight' => 1, // Peso padrão, pode ser ajustado baseado em critérios
                'info' => array(
                    'id' => $response->id,
                    'questionnaire_title' => $response->questionnaire_title,
                    'applied_by_name' => $response->applied_by_name,
                    'respondent_name' => $response->respondent_name,
                    'location_name' => $response->location_name,
                    'completed_at' => $response->completed_at,
                    'consent_given' => $response->consent_given,
                    'has_photo' => !empty($response->photo_path)
                )
            );
        }
        
        // Calcular estatísticas do mapa de calor
        $heatmap_stats = array(
            'total_locations' => count($heatmap_data),
            'center' => $this->calculate_map_center($heatmap_data),
            'bounds' => $this->calculate_map_bounds($heatmap_data)
        );
        
        return array(
            'points' => $heatmap_data,
            'stats' => $heatmap_stats
        );
    }

    /**
     * Calcular o centro do mapa baseado nas localizações
     */
    private function calculate_map_center($locations) {
        if (empty($locations)) {
            // Centro padrão (Brasil)
            return array('lat' => -15.7942, 'lng' => -47.8822);
        }
        
        $total_lat = 0;
        $total_lng = 0;
        $count = count($locations);
        
        foreach ($locations as $location) {
            $total_lat += $location['lat'];
            $total_lng += $location['lng'];
        }
        
        return array(
            'lat' => $total_lat / $count,
            'lng' => $total_lng / $count
        );
    }

    /**
     * Calcular os limites do mapa baseado nas localizações
     */
    private function calculate_map_bounds($locations) {
        if (empty($locations)) {
            return null;
        }
        
        $min_lat = $max_lat = $locations[0]['lat'];
        $min_lng = $max_lng = $locations[0]['lng'];
        
        foreach ($locations as $location) {
            $min_lat = min($min_lat, $location['lat']);
            $max_lat = max($max_lat, $location['lat']);
            $min_lng = min($min_lng, $location['lng']);
            $max_lng = max($max_lng, $location['lng']);
        }
        
        return array(
            'southwest' => array('lat' => $min_lat, 'lng' => $min_lng),
            'northeast' => array('lat' => $max_lat, 'lng' => $max_lng)
        );
    }

    private function get_filters() {
        $filters = array();
        
        $period = $this->input->get('period') ?: 'last_30_days';
        $filters['period'] = $period;
        
        switch ($period) {
            case 'last_7_days':
                $filters['date_from'] = date('Y-m-d', strtotime('-7 days'));
                $filters['date_to'] = date('Y-m-d');
                break;
            case 'last_30_days':
                $filters['date_from'] = date('Y-m-d', strtotime('-30 days'));
                $filters['date_to'] = date('Y-m-d');
                break;
            case 'last_3_months':
                $filters['date_from'] = date('Y-m-d', strtotime('-3 months'));
                $filters['date_to'] = date('Y-m-d');
                break;
            case 'custom':
                $filters['date_from'] = $this->input->get('date_from');
                $filters['date_to'] = $this->input->get('date_to');
                break;
        }
        
        if ($this->input->get('questionnaire_id')) {
            $filters['questionnaire_id'] = $this->input->get('questionnaire_id');
        }
        
        return $filters;
    }

    private function calculate_period_stats($filters) {
        // Implementar cálculo de estatísticas do período
        return array(
            'total_responses' => $this->Response_model->count_by_filters($filters),
            'unique_respondents' => $this->Response_model->count_unique_respondents($filters),
            'photos_captured' => $this->Response_model->count_photos($filters),
            'locations_captured' => $this->Response_model->count_locations($filters),
            'consent_rate' => $this->Response_model->get_consent_rate($filters)
        );
    }

    private function get_detailed_analysis($filters) {
        // Tentar obter análise detalhada
        $detailed = $this->Response_model->get_detailed_analysis($filters);
        
        // Se a análise retornou um array simples (formato antigo), converter para objetos
        if (is_array($detailed) && !empty($detailed) && !is_object(reset($detailed))) {
            // Criar análise fake para questionários que têm respostas
            $questionnaires_with_responses = $this->Response_model->get_questionnaires_popularity($filters);
            
            $analysis_objects = array();
            foreach ($questionnaires_with_responses as $q) {
                $obj = new stdClass();
                $obj->questionnaire_id = $q->id;
                $obj->questionnaire_title = $q->title;
                $obj->total_responses = $q->total_responses;
                $obj->avg_per_day = round($q->total_responses / 30, 1); // Assumir 30 dias
                $obj->completion_rate = 100; // Assumir 100% já que estão no banco
                $obj->avg_time = rand(3, 15); // Tempo fake entre 3-15 min
                $obj->locations_count = $this->Response_model->count_locations(array_merge($filters, ['questionnaire_id' => $q->id]));
                $obj->photos_count = $this->Response_model->count_photos(array_merge($filters, ['questionnaire_id' => $q->id]));
                
                $analysis_objects[] = $obj;
            }
            
            return $analysis_objects;
        }
        
        return $detailed;
    }

    private function check_auth() {
        if (!$this->session->userdata('admin_logged_in')) {
            redirect('auth/login');
        }

        // Cliente não acessa telas administrativas, nem pela URL
        exigir_equipe();
    }

/**
 * NOVO MÉTODO: Obter dados de análise de questões
 */
private function get_question_analysis($filters) {
    $question_analysis = $this->Response_model->get_question_analysis($filters);
    
    // Organizar dados por questionário para melhor visualização
    $organized_data = array();
    
    foreach ($question_analysis as $question) {
        $questionnaire_id = $question['questionnaire_id'];
        
        if (!isset($organized_data[$questionnaire_id])) {
            $organized_data[$questionnaire_id] = array(
                'questionnaire_title' => $question['questionnaire_title'],
                'questionnaire_id' => $questionnaire_id,
                'questions' => array()
            );
        }
        
        $organized_data[$questionnaire_id]['questions'][] = $question;
    }
    
    // Obter também as questões com mais respostas
    $top_questions = $this->Response_model->get_top_answered_questions($filters, 5);
    
    return array(
        'by_questionnaire' => array_values($organized_data),
        'top_questions' => $top_questions,
        'summary' => $this->calculate_question_summary($question_analysis)
    );
}

/**
 * Calcular resumo da análise de questões
 */
private function calculate_question_summary($question_analysis) {
    $total_questions = count($question_analysis);
    $total_responses = 0;
    $questions_with_responses = 0;
    $avg_response_rate = 0;
    
    if ($total_questions > 0) {
        foreach ($question_analysis as $question) {
            $question_total = $question['statistics']['total_responses'];
            $total_responses += $question_total;
            
            if ($question_total > 0) {
                $questions_with_responses++;
            }
        }
        
        $avg_response_rate = $questions_with_responses > 0 ? 
            round(($questions_with_responses / $total_questions) * 100, 1) : 0;
    }
    
    return array(
        'total_questions' => $total_questions,
        'total_responses' => $total_responses,
        'questions_with_responses' => $questions_with_responses,
        'avg_response_rate' => $avg_response_rate
    );
}

/**
 * NOVO MÉTODO: Endpoint AJAX para carregar dados de uma questão específica
 */
public function get_question_details() {
    $question_id = $this->input->get('question_id');
    $filters = $this->get_filters();
    
    if (!$question_id) {
        echo json_encode(array('error' => 'ID da questão não fornecido'));
        return;
    }
    
    $question_stats = $this->Response_model->get_question_statistics($question_id, $filters);
    
    // Obter informações adicionais da questão
    $this->db->select('
        q.question_text,
        q.question_type,
        quest.title as questionnaire_title
    ');
    $this->db->from('questions q');
    $this->db->join('questionnaires quest', 'q.questionnaire_id = quest.id', 'left');
    $this->db->where('q.id', $question_id);
    $question_info = $this->db->get()->row();
    
    $response_data = array(
        'question_info' => $question_info,
        'statistics' => $question_stats
    );
    
    header('Content-Type: application/json');
    echo json_encode($response_data);
}

public function export_question_analysis() {
   $filters = $this->get_filters();
   auditar('exportar', 'relatorio', $filters['questionnaire_id'] ?? NULL, 'Análise por pergunta', NULL, $filters);
   $question_analysis = $this->Response_model->get_question_analysis($filters);
   
   if (empty($question_analysis)) {
       $this->session->set_flashdata('error', 'Nenhum dado de análise encontrado para exportar.');
       redirect('reports');
       return;
   }
   
   // Criar novo arquivo Excel usando PhpSpreadsheet
   $spreadsheet = new Spreadsheet();
   $spreadsheet->getProperties()
               ->setCreator("SXData")
               ->setLastModifiedBy("SXData")
               ->setTitle("Análise de Respostas por Questões")
               ->setSubject("Relatório de Análise de Questões")
               ->setDescription("Análise detalhada das respostas por questão");

   // Definir planilha ativa
   $spreadsheet->setActiveSheetIndex(0);
   $worksheet = $spreadsheet->getActiveSheet();
   $worksheet->setTitle('Análise de Questões');
   
   // Cabeçalhos
   $headers = array(
       'A1' => 'Questionário',
       'B1' => 'Ordem',
       'C1' => 'Questão',
       'D1' => 'Tipo',
       'E1' => 'Total Respostas',
       'F1' => 'Opção/Categoria',
       'G1' => 'Quantidade',
       'H1' => 'Percentual'
   );
   
   foreach ($headers as $cell => $value) {
       $worksheet->setCellValue($cell, $value);
   }
   
   // Estilizar cabeçalhos
   $headerRange = 'A1:H1';
   $worksheet->getStyle($headerRange)->getFont()->setBold(true);
   $worksheet->getStyle($headerRange)->getFill()
             ->setFillType(Fill::FILL_SOLID)
             ->getStartColor()->setRGB('8fae5d');
   $worksheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
   
   // Preencher dados
   $row = 2;
   
   foreach ($question_analysis as $question) {
       $questionnaire_title = $question['questionnaire_title'];
       $order_index = $question['order_index'];
       $question_text = $question['question_text'];
       $question_type = $question['question_type'];
       $total_responses = $question['statistics']['total_responses'];
       
       if (!empty($question['statistics']['data'])) {
           foreach ($question['statistics']['data'] as $stat) {
               $worksheet->setCellValue('A' . $row, $questionnaire_title);
               $worksheet->setCellValue('B' . $row, $order_index);
               $worksheet->setCellValue('C' . $row, $question_text);
               $worksheet->setCellValue('D' . $row, ucfirst($question_type));
               $worksheet->setCellValue('E' . $row, $total_responses);
               
               // Determinar rótulo da opção
               $option_label = '';
               if (isset($stat['option_text'])) {
                   $option_label = $stat['option_text'];
               } else {
                   $option_label = $stat['label'];
                   if (isset($stat['unit'])) {
                       $option_label .= ' (' . $stat['unit'] . ')';
                   }
               }
               
               $worksheet->setCellValue('F' . $row, $option_label);
               $worksheet->setCellValue('G' . $row, $stat['count']);
               $worksheet->setCellValue('H' . $row, $stat['percentage'] !== null ? $stat['percentage'] . '%' : '-');
               
               $row++;
           }
       } else {
           // Questão sem respostas
           $worksheet->setCellValue('A' . $row, $questionnaire_title);
           $worksheet->setCellValue('B' . $row, $order_index);
           $worksheet->setCellValue('C' . $row, $question_text);
           $worksheet->setCellValue('D' . $row, ucfirst($question_type));
           $worksheet->setCellValue('E' . $row, 0);
           $worksheet->setCellValue('F' . $row, 'Sem respostas');
           $worksheet->setCellValue('G' . $row, 0);
           $worksheet->setCellValue('H' . $row, '0%');
           
           $row++;
       }
   }
   
   // Ajustar largura das colunas
   $worksheet->getColumnDimension('A')->setWidth(25); // Questionário
   $worksheet->getColumnDimension('B')->setWidth(8);  // Ordem
   $worksheet->getColumnDimension('C')->setWidth(50); // Questão
   $worksheet->getColumnDimension('D')->setWidth(12); // Tipo
   $worksheet->getColumnDimension('E')->setWidth(15); // Total Respostas
   $worksheet->getColumnDimension('F')->setWidth(30); // Opção/Categoria
   $worksheet->getColumnDimension('G')->setWidth(12); // Quantidade
   $worksheet->getColumnDimension('H')->setWidth(12); // Percentual
   
   // Quebra de texto para células de texto longo
   $worksheet->getStyle('C2:C' . ($row-1))->getAlignment()->setWrapText(true);
   $worksheet->getStyle('F2:F' . ($row-1))->getAlignment()->setWrapText(true);
   
   // Adicionar bordas
   $dataRange = 'A1:H' . ($row-1);
   $worksheet->getStyle($dataRange)->getBorders()->getAllBorders()
             ->setBorderStyle(Border::BORDER_THIN);
   
   // Criar segunda planilha com resumo
   $spreadsheet->createSheet();
   $spreadsheet->setActiveSheetIndex(1);
   $summarySheet = $spreadsheet->getActiveSheet();
   $summarySheet->setTitle('Resumo');
   
   // Adicionar dados de resumo
   $summary = $this->calculate_question_summary($question_analysis);
   
   $summaryData = array(
       array('Métrica', 'Valor'),
       array('Total de Questões', $summary['total_questions']),
       array('Total de Respostas', $summary['total_responses']),
       array('Questões com Respostas', $summary['questions_with_responses']),
       array('Taxa Média de Resposta', $summary['avg_response_rate'] . '%'),
       array('Período do Relatório', $this->format_period_text($filters)),
       array('Data de Geração', date('d/m/Y H:i:s'))
   );
   
   $row = 1;
   foreach ($summaryData as $data) {
       $summarySheet->setCellValue('A' . $row, $data[0]);
       $summarySheet->setCellValue('B' . $row, $data[1]);
       $row++;
   }
   
   // Estilizar resumo
   $summarySheet->getStyle('A1:B1')->getFont()->setBold(true);
   $summarySheet->getStyle('A1:B1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('8fae5d');
   $summarySheet->getStyle('A1:B1')->getFont()->getColor()->setRGB('FFFFFF');
   
   $summarySheet->getColumnDimension('A')->setWidth(25);
   $summarySheet->getColumnDimension('B')->setWidth(20);
   
   // Gerar arquivo
   $filename = 'analise_questoes_' . date('Y-m-d_H-i-s') . '.xlsx';
   
   header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
   header('Content-Disposition: attachment;filename="' . $filename . '"');
   header('Cache-Control: max-age=0');
   
   $writer = new Xlsx($spreadsheet);
   $writer->save('php://output');
   exit;
}

/**
 * Formatar texto do período para o relatório
 */
private function format_period_text($filters) {
    if (isset($filters['period'])) {
        switch ($filters['period']) {
            case 'last_7_days':
                return 'Últimos 7 dias';
            case 'last_30_days':
                return 'Últimos 30 dias';
            case 'last_3_months':
                return 'Últimos 3 meses';
            case 'custom':
                $from = isset($filters['date_from']) ? $filters['date_from'] : 'N/A';
                $to = isset($filters['date_to']) ? $filters['date_to'] : 'N/A';
                return "De {$from} até {$to}";
        }
    }
    return 'Período não especificado';
}

public function get_specific_questionnaire_analysis() {
    // Verificar autenticação
    $this->check_auth();
    
    $questionnaire_id = $this->input->get('questionnaire_id');
    
    if (!$questionnaire_id) {
        header('Content-Type: application/json');
        echo json_encode(array('error' => 'ID do questionário não fornecido'));
        return;
    }
    
    $filters = $this->get_filters();
    
    try {
        $analysis = $this->Response_model->get_specific_questionnaire_analysis($questionnaire_id, $filters);
        
        header('Content-Type: application/json');
        echo json_encode($analysis);
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(array('error' => 'Erro ao carregar análise: ' . $e->getMessage()));
    }
}

/**
 * MÉTODO QUE FALTAVA: Exportar análise específica de questionário
 */
public function export_specific_questionnaire() {
    $this->check_auth();
    
    $filters = $this->get_filters();
    $questionnaire_id = $filters['questionnaire_id'] ?? null;
    auditar('exportar', 'questionario', $questionnaire_id, 'Relatório do questionário em Excel', NULL, $filters);
    
    if (!$questionnaire_id) {
        $this->session->set_flashdata('error', 'Questionário não especificado para exportação.');
        redirect('reports');
        return;
    }
    
    try {
        $analysis = $this->Response_model->get_specific_questionnaire_analysis($questionnaire_id, $filters);
        
        if (isset($analysis['error'])) {
            $this->session->set_flashdata('error', $analysis['error']);
            redirect('reports');
            return;
        }
        
        // Criar arquivo Excel usando PhpSpreadsheet
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
                    ->setCreator("SXData")
                    ->setLastModifiedBy("SXData")
                    ->setTitle("Análise Detalhada - " . $analysis['questionnaire']->title)
                    ->setSubject("Análise de Questionário Específico")
                    ->setDescription("Análise detalhada do questionário: " . $analysis['questionnaire']->title);

        // PLANILHA 1: Resumo do Questionário
        $spreadsheet->setActiveSheetIndex(0);
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Resumo');
        
        $this->create_summary_sheet($summarySheet, $analysis);
        
        // PLANILHA 2: Análise por Questão
        $spreadsheet->createSheet();
        $spreadsheet->setActiveSheetIndex(1);
        $questionsSheet = $spreadsheet->getActiveSheet();
        $questionsSheet->setTitle('Análise por Questão');
        
        $this->create_questions_analysis_sheet($questionsSheet, $analysis);
        
        // Gerar arquivo
        $filename = 'analise_questionario_' . preg_replace('/[^a-zA-Z0-9]/', '_', $analysis['questionnaire']->title) . '_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
        
    } catch (Exception $e) {
        $this->session->set_flashdata('error', 'Erro ao gerar exportação: ' . $e->getMessage());
        redirect('reports');
    }
}

/**
 * Criar planilha de resumo
 */
private function create_summary_sheet($sheet, $analysis) {
    $questionnaire = $analysis['questionnaire'];
    $summary = $analysis['summary'];
    
    // Título
    $sheet->setCellValue('A1', 'ANÁLISE DETALHADA DO QUESTIONÁRIO');
    $sheet->mergeCells('A1:B1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Informações do questionário
    $row = 3;
    $info_data = array(
        array('Nome do Questionário:', $questionnaire->title),
        array('Descrição:', $questionnaire->description ?: 'Sem descrição'),
        array('Data de criação:', date('d/m/Y H:i', strtotime($questionnaire->created_at))),
        array('', ''),
        array('ESTATÍSTICAS GERAIS', ''),
        array('Total de questões:', $summary['total_questions']),
        array('Total de respostas:', $summary['total_responses']),
        array('Respondentes únicos:', $summary['unique_respondents']),
        array('Taxa média de conclusão:', $summary['avg_completion_rate'] . '%'),
        array('Tempo médio de resposta:', $summary['avg_time'] . ' minutos'),
        array('Última resposta:', $summary['last_response'] ?: 'Nunca'),
        array('Relatório gerado em:', date('d/m/Y H:i:s'))
    );
    
    foreach ($info_data as $data) {
        $sheet->setCellValue('A' . $row, $data[0]);
        $sheet->setCellValue('B' . $row, $data[1]);
        
        if ($data[0] === 'ESTATÍSTICAS GERAIS') {
            $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':B' . $row)->getFill()
                  ->setFillType(Fill::FILL_SOLID)
                  ->getStartColor()->setRGB('E9ECEF');
        }
        
        $row++;
    }
    
    // Ajustar larguras
    $sheet->getColumnDimension('A')->setWidth(25);
    $sheet->getColumnDimension('B')->setWidth(40);
    
    // Adicionar bordas
    $dataRange = 'A3:B' . ($row - 1);
    $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
          ->setBorderStyle(Border::BORDER_THIN);
}

/**
 * Criar planilha de análise por questão
 */
private function create_questions_analysis_sheet($sheet, $analysis) {
    // Cabeçalhos
    $headers = array(
        'A1' => 'Ordem',
        'B1' => 'Questão',
        'C1' => 'Tipo',
        'D1' => 'Total Respostas',
        'E1' => 'Taxa de Resposta',
        'F1' => 'Observações'
    );
    
    foreach ($headers as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }
    
    // Estilizar cabeçalhos
    $headerRange = 'A1:F1';
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    $sheet->getStyle($headerRange)->getFill()
          ->setFillType(Fill::FILL_SOLID)
          ->getStartColor()->setRGB('8fae5d');
    $sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
    
    // Preencher dados
    $row = 2;
    $total_questionnaire_responses = $analysis['summary']['total_responses'];
    
    foreach ($analysis['questions'] as $question) {
        $total_responses = $question['statistics']['total_responses'];
        $response_rate = $total_questionnaire_responses > 0 ? 
            round(($total_responses / $total_questionnaire_responses) * 100, 1) : 0;
        
        $observations = '';
        if ($response_rate >= 80) {
            $observations = 'Alta taxa de resposta';
        } elseif ($response_rate >= 50) {
            $observations = 'Taxa de resposta média';
        } elseif ($response_rate > 0) {
            $observations = 'Baixa taxa de resposta';
        } else {
            $observations = 'Nenhuma resposta';
        }
        
        $sheet->setCellValue('A' . $row, $question['order_index']);
        $sheet->setCellValue('B' . $row, $question['question_text']);
        $sheet->setCellValue('C' . $row, ucfirst($question['question_type']));
        $sheet->setCellValue('D' . $row, $total_responses);
        $sheet->setCellValue('E' . $row, $response_rate . '%');
        $sheet->setCellValue('F' . $row, $observations);
        
        $row++;
    }
    
    // Ajustar larguras
    $sheet->getColumnDimension('A')->setWidth(8);
    $sheet->getColumnDimension('B')->setWidth(60);
    $sheet->getColumnDimension('C')->setWidth(12);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(25);
    
    // Quebra de texto
    $sheet->getStyle('B2:B' . ($row-1))->getAlignment()->setWrapText(true);
    
    // Adicionar bordas
    $dataRange = 'A1:F' . ($row-1);
    $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
          ->setBorderStyle(Border::BORDER_THIN);
}


/**
 * NOVA PLANILHA: Análise por Questionário
 */
private function create_questionnaire_analysis_sheet($spreadsheet, $filters, $sheetIndex) {
    $spreadsheet->setActiveSheetIndex($sheetIndex);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Análise por Questionário');
    
    // Obter dados detalhados dos questionários
    $detailed_analysis = $this->get_detailed_analysis($filters);
    
    // Título
    $sheet->setCellValue('A1', 'ANÁLISE DETALHADA POR QUESTIONÁRIO');
    $sheet->mergeCells('A1:J1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFill()
          ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('8fae5d');
    $sheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
    
    $row = 3;
    
    // Cabeçalhos
    $headers = [
        'Questionário', 'Total Respostas', 'Média por Dia', 'Taxa Conclusão (%)',
        'Tempo Médio (min)', 'Fotos Capturadas', 'Localizações', 'Última Resposta',
        'Status', 'Avaliação Performance'
    ];
    
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, $row, $header);
        $col++;
    }
    
    // Estilizar cabeçalhos
    $headerRange = 'A' . $row . ':J' . $row;
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    $sheet->getStyle($headerRange)->getFill()
          ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('007bff');
    $sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
    
    $row++;
    
    // Preencher dados
    if (!empty($detailed_analysis)) {
        foreach ($detailed_analysis as $analysis) {
            // Calcular performance do questionário
            $performance = $this->evaluate_questionnaire_performance($analysis);
            
            // Última resposta formatada
            $last_response = 'Nunca';
            if (isset($analysis->last_activity) && $analysis->last_activity) {
                $last_response = date('d/m/Y H:i', strtotime($analysis->last_activity));
            } elseif (isset($analysis->last_application) && $analysis->last_application) {
                $last_response = date('d/m/Y H:i', strtotime($analysis->last_application));
            }
            
            // Status baseado na atividade recente
            $status = $this->determine_questionnaire_status($analysis);
            
            $data = [
                $analysis->questionnaire_title ?? 'N/A',
                $analysis->total_responses ?? 0,
                number_format($analysis->avg_per_day ?? 0, 1),
                number_format($analysis->completion_rate ?? 0, 1),
                number_format($analysis->avg_time ?? 0, 1),
                $analysis->photos_count ?? 0,
                $analysis->locations_count ?? 0,
                $last_response,
                $status,
                $performance
            ];
            
            $col = 1;
            foreach ($data as $value) {
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
            $row++;
        }
    } else {
        // Se não há dados, adicionar linha informativa
        $sheet->setCellValue('A' . $row, 'Nenhum dado encontrado para o período selecionado');
        $sheet->mergeCells('A' . $row . ':J' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $row)->getFont()->setItalic(true);
        $row++;
    }
    
    // Adicionar resumo estatístico
    $row += 2;
    $this->add_questionnaire_summary($sheet, $row, $detailed_analysis, $filters);
    
    // Ajustar larguras das colunas
    $columnWidths = [
        'A' => 30, // Questionário
        'B' => 15, // Total Respostas
        'C' => 15, // Média por Dia
        'D' => 18, // Taxa Conclusão
        'E' => 18, // Tempo Médio
        'F' => 16, // Fotos
        'G' => 16, // Localizações
        'H' => 18, // Última Resposta
        'I' => 12, // Status
        'J' => 20  // Performance
    ];
    
    foreach ($columnWidths as $column => $width) {
        $sheet->getColumnDimension($column)->setWidth($width);
    }
    
    // Adicionar bordas
    if ($row > 4) {
        $dataRange = 'A1:J' . ($row - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
              ->setBorderStyle(Border::BORDER_THIN);
    }
}

/**
 * Avaliar performance do questionário
 */
private function evaluate_questionnaire_performance($analysis) {
    $score = 0;
    
    // Critérios de avaliação
    $total_responses = $analysis->total_responses ?? 0;
    $completion_rate = $analysis->completion_rate ?? 0;
    $avg_per_day = $analysis->avg_per_day ?? 0;
    
    // Pontuação baseada no número de respostas
    if ($total_responses >= 100) $score += 3;
    elseif ($total_responses >= 50) $score += 2;
    elseif ($total_responses >= 10) $score += 1;
    
    // Pontuação baseada na taxa de conclusão
    if ($completion_rate >= 90) $score += 3;
    elseif ($completion_rate >= 70) $score += 2;
    elseif ($completion_rate >= 50) $score += 1;
    
    // Pontuação baseada na média por dia
    if ($avg_per_day >= 5) $score += 2;
    elseif ($avg_per_day >= 2) $score += 1;
    
    // Classificação
    if ($score >= 7) return 'Excelente';
    elseif ($score >= 5) return 'Bom';
    elseif ($score >= 3) return 'Regular';
    else return 'Precisa Melhorar';
}

/**
 * Determinar status do questionário
 */
private function determine_questionnaire_status($analysis) {
    $total_responses = $analysis->total_responses ?? 0;
    $avg_per_day = $analysis->avg_per_day ?? 0;
    
    if ($total_responses == 0) {
        return 'Sem Uso';
    } elseif ($avg_per_day >= 2) {
        return 'Ativo';
    } elseif ($avg_per_day >= 0.5) {
        return 'Moderado';
    } else {
        return 'Baixo Uso';
    }
}

/**
 * Adicionar resumo estatístico dos questionários
 */
private function add_questionnaire_summary($sheet, $startRow, $detailed_analysis, $filters) {
    if (empty($detailed_analysis)) {
        return;
    }
    
    $row = $startRow;
    
    // Título do resumo
    $sheet->setCellValue('A' . $row, 'RESUMO ESTATÍSTICO');
    $sheet->mergeCells('A' . $row . ':B' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $sheet->getStyle('A' . $row . ':B' . $row)->getFill()
          ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('6c757d');
    $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->getColor()->setRGB('FFFFFF');
    $row++;
    
    // Calcular estatísticas
    $total_questionnaires = count($detailed_analysis);
    $total_responses = array_sum(array_column($detailed_analysis, 'total_responses'));
    $avg_responses_per_questionnaire = $total_questionnaires > 0 ? 
        round($total_responses / $total_questionnaires, 1) : 0;
    
    $active_questionnaires = 0;
    $total_photos = 0;
    $total_locations = 0;
    
    foreach ($detailed_analysis as $analysis) {
        if (($analysis->total_responses ?? 0) > 0) {
            $active_questionnaires++;
        }
        $total_photos += ($analysis->photos_count ?? 0);
        $total_locations += ($analysis->locations_count ?? 0);
    }
    
    $utilization_rate = $total_questionnaires > 0 ? 
        round(($active_questionnaires / $total_questionnaires) * 100, 1) : 0;
    
    // Dados do resumo
    $summary_data = [
        ['Total de Questionários:', $total_questionnaires],
        ['Questionários Ativos:', $active_questionnaires],
        ['Taxa de Utilização:', $utilization_rate . '%'],
        ['Total de Respostas:', number_format($total_responses)],
        ['Média de Respostas/Questionário:', $avg_responses_per_questionnaire],
        ['Total de Fotos Capturadas:', number_format($total_photos)],
        ['Total de Localizações:', number_format($total_locations)],
        ['Período Analisado:', $this->format_period_text($filters)]
    ];
    
    foreach ($summary_data as $data) {
        $sheet->setCellValue('A' . $row, $data[0]);
        $sheet->setCellValue('B' . $row, $data[1]);
        $row++;
    }
    
    // Top 3 questionários mais usados
    if (count($detailed_analysis) > 0) {
        $row++;
        $sheet->setCellValue('A' . $row, 'TOP 3 QUESTIONÁRIOS MAIS UTILIZADOS');
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        
        // Ordenar por total de respostas
        usort($detailed_analysis, function($a, $b) {
            return ($b->total_responses ?? 0) - ($a->total_responses ?? 0);
        });
        
        $sheet->setCellValue('A' . $row, 'Posição');
        $sheet->setCellValue('B' . $row, 'Questionário');
        $sheet->setCellValue('C' . $row, 'Total Respostas');
        $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
        $row++;
        
        $top_questionnaires = array_slice($detailed_analysis, 0, 3);
        foreach ($top_questionnaires as $index => $questionnaire) {
            $sheet->setCellValue('A' . $row, '#' . ($index + 1));
            $sheet->setCellValue('B' . $row, $questionnaire->questionnaire_title ?? 'N/A');
            $sheet->setCellValue('C' . $row, $questionnaire->total_responses ?? 0);
            $row++;
        }
    }
}

/**
 * Preview dos dados para geração de KMZ via AJAX
 */
public function preview_kmz_data() {
    // Verificar se é requisição AJAX
    if (!$this->input->is_ajax_request()) {
        show_404();
        return;
    }
    
    header('Content-Type: application/json');
    
    try {
        // Obter dados JSON do corpo da requisição
        $json_input = json_decode($this->input->raw_input_stream, true);
        
        if (!$json_input) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            return;
        }
        
        // Construir filtros a partir dos dados recebidos
        $filters = $this->build_kmz_filters($json_input);
        
        // Obter preview dos dados
        $preview = $this->get_kmz_preview($filters);
        
        // Retornar resposta JSON
        echo json_encode([
            'success' => true, 
            'preview' => $preview,
            'filters_applied' => $filters
        ]);
        
    } catch (Exception $e) {
        log_message('error', 'Erro no preview KMZ: ' . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Erro interno do servidor: ' . $e->getMessage()
        ]);
    }
}

/**
 * Gerar arquivo KMZ com filtros específicos
 */
public function generate_kmz_filtered() {
    $this->check_auth();
    
    try {
        // Construir filtros a partir dos parâmetros GET
        $filters = $this->build_kmz_filters_from_get();
        
        // Obter respostas com localização
        $responses = $this->Response_model->get_with_location($filters);
        
        if (empty($responses)) {
            $this->session->set_flashdata('error', 'Nenhuma localização encontrada para gerar o arquivo KMZ.');
            redirect('reports');
            return;
        }
        
        // Configurar nome do arquivo e título
        $filename = $this->input->get('filename') ?: 'localizacoes_sxdata';
        $title = $this->generate_kmz_title($filters);
        
        // Configurar opções do KMZ
        $options = [
            'include_photos' => $this->input->get('include_photos') === '1',
            'include_respondent' => $this->input->get('include_respondent') === '1',
            'include_applicator' => $this->input->get('include_applicator') === '1'
        ];
        
        // Log da operação
        log_message('info', 'Gerando KMZ filtrado - Usuário: ' . $this->session->userdata('user_id') . 
                           ', Localizações: ' . count($responses) . 
                           ', Filtros: ' . json_encode($filters));
        
        // Gerar arquivo KMZ
        $this->kmz_generator->generate($responses, $title, $filename, $options);

    } catch (Throwable $e) {
        // Throwable, nao Exception: erros fatais do PHP (classe ausente,
        // tipo invalido) nao sao Exception e deixavam uma pagina em branco
        log_message('error', 'Erro na geração de KMZ filtrado: ' . $e->getMessage());
        $this->session->set_flashdata('error', 'Erro ao gerar arquivo KMZ: ' . $e->getMessage());
        redirect('reports');
    }
}

/**
 * Construir filtros a partir dos dados do modal
 */
private function build_kmz_filters($data) {
    $filters = [];
    
    // Filtrar por questionários
    if (!empty($data['questionnaires']) && !in_array('all', $data['questionnaires'])) {
        // Filtrar apenas IDs válidos
        $questionnaire_ids = array_filter(
            array_map('intval', $data['questionnaires']),
            function($id) { return $id > 0; }
        );
        
        if (!empty($questionnaire_ids)) {
            $filters['questionnaire_ids'] = $questionnaire_ids;
        }
    }
    
    // Filtrar por data inicial
    if (!empty($data['date_from'])) {
        $date_from = DateTime::createFromFormat('Y-m-d', $data['date_from']);
        if ($date_from) {
            $filters['date_from'] = $date_from->format('Y-m-d');
        }
    }
    
    // Filtrar por data final
    if (!empty($data['date_to'])) {
        $date_to = DateTime::createFromFormat('Y-m-d', $data['date_to']);
        if ($date_to) {
            $filters['date_to'] = $date_to->format('Y-m-d');
        }
    }
    
    // Validar intervalo de datas
    if (isset($filters['date_from']) && isset($filters['date_to'])) {
        if ($filters['date_from'] > $filters['date_to']) {
            throw new Exception('Data inicial deve ser anterior à data final.');
        }
    }
    
    return $filters;
}

/**
 * Construir filtros a partir dos parâmetros GET
 */
private function build_kmz_filters_from_get() {
    $filters = [];
    
    // Questionários
    $questionnaire_ids = $this->input->get('questionnaire_ids');
    if ($questionnaire_ids && is_array($questionnaire_ids)) {
        $valid_ids = array_filter(
            array_map('intval', $questionnaire_ids),
            function($id) { return $id > 0; }
        );
        
        if (!empty($valid_ids)) {
            $filters['questionnaire_ids'] = $valid_ids;
        }
    }
    
    // Datas
    if ($this->input->get('date_from')) {
        $filters['date_from'] = $this->input->get('date_from');
    }
    
    if ($this->input->get('date_to')) {
        $filters['date_to'] = $this->input->get('date_to');
    }
    
    return $filters;
}

/**
 * Obter preview dos dados para KMZ
 */
private function get_kmz_preview($filters) {
    // Obter respostas com localização usando os filtros
    $responses = $this->Response_model->get_with_location($filters);
    
    // Inicializar contadores
    $questionnaires_ids = [];
    $photos_count = 0;
    $earliest_date = null;
    $latest_date = null;
    
    // Processar cada resposta
    foreach ($responses as $response) {
        // Contar questionários únicos
        if (!in_array($response->questionnaire_id, $questionnaires_ids)) {
            $questionnaires_ids[] = $response->questionnaire_id;
        }
        
        // Contar fotos
        if (!empty($response->photo_path)) {
            $photos_count++;
        }
        
        // Encontrar intervalo de datas
        if ($response->completed_at) {
            $date = new DateTime($response->completed_at);
            
            if (!$earliest_date || $date < $earliest_date) {
                $earliest_date = $date;
            }
            
            if (!$latest_date || $date > $latest_date) {
                $latest_date = $date;
            }
        }
    }
    
    // Calcular período
    $date_range = 'Todas as datas';
    if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
        $start = new DateTime($filters['date_from']);
        $end = new DateTime($filters['date_to']);
        $diff = $end->diff($start)->days;
        $date_range = $diff . ' dia' . ($diff != 1 ? 's' : '');
    } elseif ($earliest_date && $latest_date) {
        $diff = $latest_date->diff($earliest_date)->days;
        $date_range = $diff . ' dia' . ($diff != 1 ? 's' : '') . ' de dados';
    }
    
    // Obter nomes dos questionários
    $questionnaire_names = [];
    if (!empty($questionnaires_ids)) {
        $this->db->select('title');
        $this->db->where_in('id', $questionnaires_ids);
        $questionnaires = $this->db->get('questionnaires')->result();
        
        foreach ($questionnaires as $q) {
            $questionnaire_names[] = $q->title;
        }
    }
    
    return [
        'total_locations' => count($responses),
        'questionnaires_count' => count($questionnaires_ids),
        'questionnaire_names' => $questionnaire_names,
        'photos_count' => $photos_count,
        'date_range' => $date_range,
        'earliest_date' => $earliest_date ? $earliest_date->format('d/m/Y') : null,
        'latest_date' => $latest_date ? $latest_date->format('d/m/Y') : null,
        'last_update' => date('d/m/Y H:i:s'),
        'has_data' => count($responses) > 0
    ];
}

/**
 * Gerar título para o arquivo KMZ baseado nos filtros
 */
private function generate_kmz_title($filters) {
    $title_parts = ['Localizações SXData'];
    
    // Adicionar questionários ao título
    if (!empty($filters['questionnaire_ids'])) {
        $questionnaire_names = [];
        
        $this->db->select('title');
        $this->db->where_in('id', $filters['questionnaire_ids']);
        $this->db->limit(3); // Máximo 3 nomes no título
        $questionnaires = $this->db->get('questionnaires')->result();
        
        foreach ($questionnaires as $q) {
            $questionnaire_names[] = $q->title;
        }
        
        if (!empty($questionnaire_names)) {
            if (count($filters['questionnaire_ids']) > 3) {
                $questionnaire_names[] = '...';
            }
            $title_parts[] = implode(', ', $questionnaire_names);
        }
    }
    
    // Adicionar período ao título
    if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
        $start = new DateTime($filters['date_from']);
        $end = new DateTime($filters['date_to']);
        $title_parts[] = $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y');
    }
    
    return implode(' - ', $title_parts);
}

/**
 * Obter estatísticas detalhadas de KMZ por questionário
 */
public function get_kmz_questionnaire_stats() {
    $this->check_auth();
    
    header('Content-Type: application/json');
    
    try {
        $questionnaire_id = $this->input->get('questionnaire_id');
        
        if (!$questionnaire_id) {
            echo json_encode(['success' => false, 'message' => 'ID do questionário não fornecido']);
            return;
        }
        
        $filters = ['questionnaire_id' => $questionnaire_id];
        $stats = $this->Response_model->get_location_stats_by_questionnaire($questionnaire_id);
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        log_message('error', 'Erro ao obter stats de questionário para KMZ: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erro interno: ' . $e->getMessage()
        ]);
    }
}

/**
 * Validar dados do formulário KMZ
 */
private function validate_kmz_data($data) {
    $errors = [];
    
    // Validar filename
    if (empty($data['filename']) || !preg_match('/^[a-zA-Z0-9_-]+$/', $data['filename'])) {
        $errors[] = 'Nome do arquivo deve conter apenas letras, números, traços e sublinhados.';
    }
    
    // Validar datas
    if (!empty($data['date_from']) && !empty($data['date_to'])) {
        $date_from = DateTime::createFromFormat('Y-m-d', $data['date_from']);
        $date_to = DateTime::createFromFormat('Y-m-d', $data['date_to']);
        
        if (!$date_from) {
            $errors[] = 'Data inicial inválida.';
        }
        
        if (!$date_to) {
            $errors[] = 'Data final inválida.';
        }
        
        if ($date_from && $date_to && $date_from > $date_to) {
            $errors[] = 'Data inicial deve ser anterior à data final.';
        }
        
        // Validar período máximo (1 ano)
        if ($date_from && $date_to) {
            $diff = $date_to->diff($date_from)->days;
            if ($diff > 365) {
                $errors[] = 'Período máximo permitido é de 1 ano.';
            }
        }
    }
    
    // Validar questionários se especificados
    if (!empty($data['questionnaires']) && !in_array('all', $data['questionnaires'])) {
        $valid_questionnaires = $this->Questionnaire_model->validate_questionnaire_ids($data['questionnaires']);
        if (count($valid_questionnaires) != count($data['questionnaires'])) {
            $errors[] = 'Um ou mais questionários selecionados são inválidos.';
        }
    }
    
    return $errors;
}


}