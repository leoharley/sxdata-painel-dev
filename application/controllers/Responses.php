<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Responses extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        $this->load->model('Response_model');
        $this->load->model('Questionnaire_model');
        $this->load->model('Question_model');
        $this->load->model('User_model');
        $this->check_auth();
    }

    /**
     * Página principal de listagem de respostas
     */
    public function index() {
        $data['title'] = 'Respostas - SXData';
        
        // Filtros
        $filters = array();
        if ($this->input->get('questionnaire_id')) {
            $filters['questionnaire_id'] = $this->input->get('questionnaire_id');
        }
        if ($this->input->get('applied_by')) {
            $filters['applied_by'] = $this->input->get('applied_by');
        }
        if ($this->input->get('date_from')) {
            $filters['date_from'] = $this->input->get('date_from');
        }
        if ($this->input->get('date_to')) {
            $filters['date_to'] = $this->input->get('date_to');
        }
        if ($this->input->get('sync_status')) {
            $filters['sync_status'] = $this->input->get('sync_status');
        }

        $data['responses'] = $this->Response_model->get_filtered($filters);
        // Para ouvir o áudio direto da listagem, sem abrir cada resposta
        foreach ($data['responses'] as $response) {
            $response->audios = !empty($response->audio_path)
                ? $this->arquivos_de_audio($response->audio_path)
                : null;
        }
        $data['questionnaires'] = $this->Questionnaire_model->get_for_analysis();
        $data['users'] = $this->User_model->get_aplicadores();
        $data['filters'] = $filters;
        
        $this->load->view('admin/header', $data);
        $this->load->view('admin/responses/index', $data);
        $this->load->view('admin/footer');
    }

    /**
     * Visualizar resposta individual
     */
    public function view($id) {
        $response = $this->Response_model->get_by_id($id);
        if (!$response) {
            show_404();
        }

        $data['title'] = 'Visualizar Resposta - SXData';
        $data['response'] = $response;
        $data['questionnaire'] = $this->Questionnaire_model->get_by_id($response->questionnaire_id);
        $data['questions'] = $this->Question_model->get_by_questionnaire($response->questionnaire_id);
        $data['answers'] = $this->Response_model->get_answers($id);
        $data['applied_by'] = $this->User_model->get_by_id($response->applied_by);
        $data['audios'] = $this->arquivos_de_audio(isset($response->audio_path) ? $response->audio_path : null);

        $this->load->view('admin/header', $data);
        $this->load->view('admin/responses/view', $data);
        $this->load->view('admin/footer');
    }

    /**
     * Áudios da entrevista em uploads/audios/.
     *
     * O app no Android grava um arquivo único. A versão web grava em
     * segmentos (audio_web_<sessão>_p<n>.<ext>), um por período de tela
     * ativa, e só o primeiro fica em audio_path: os demais são achados pelo
     * nome da sessão.
     *
     * @return array ['registrado' => bool, 'arquivos' => [['nome', 'url', 'tamanho']]]
     */
    private function arquivos_de_audio($audio_path) {
        $resultado = ['registrado' => false, 'arquivos' => []];
        $nome = basename((string) $audio_path);
        if ($nome === '') {
            return $resultado;
        }
        $resultado['registrado'] = true;

        $pasta = FCPATH . 'uploads/audios/';
        $nomes = [$nome];

        if (preg_match('/^(audio_web_[A-Za-z0-9\-]+)_p\d+\.([a-z0-9]+)$/i', $nome, $m)) {
            $segmentos = glob($pasta . $m[1] . '_p*.' . $m[2]) ?: [];
            if (!empty($segmentos)) {
                $nomes = array_map('basename', $segmentos);
                // Ordem de gravação: p1, p2, ..., p10 (não alfabética)
                usort($nomes, function ($a, $b) {
                    preg_match('/_p(\d+)\./', $a, $x);
                    preg_match('/_p(\d+)\./', $b, $y);
                    return (int) $x[1] <=> (int) $y[1];
                });
            }
        }

        foreach ($nomes as $arquivo) {
            if (is_file($pasta . $arquivo)) {
                $resultado['arquivos'][] = [
                    'nome'    => $arquivo,
                    'url'     => base_url('uploads/audios/' . rawurlencode($arquivo)),
                    'tamanho' => filesize($pasta . $arquivo),
                ];
            }
        }

        return $resultado;
    }

    /**
     * Exportação padrão do sistema (mantido para compatibilidade)
     */
    public function export() {
        $this->load->library('excel');
        
        $filters = array();
        if ($this->input->get('questionnaire_id')) {
            $filters['questionnaire_id'] = $this->input->get('questionnaire_id');
        }
        if ($this->input->get('date_from')) {
            $filters['date_from'] = $this->input->get('date_from');
        }
        if ($this->input->get('date_to')) {
            $filters['date_to'] = $this->input->get('date_to');
        }

        $responses = $this->Response_model->get_for_export($filters);
        
        // Gerar arquivo Excel
        $this->excel->create_export($responses);
    }

    /**
     * NOVO: Exportar dados brutos com colunas dinâmicas das perguntas
     */
    public function export_raw_data() {
        // Verificar autenticação
        if (!$this->session->userdata('admin_logged_in')) {
            show_404();
            return;
        }
        
        $user_id = $this->session->userdata('user_id');
        
        // Verificar se é uma requisição POST
        if ($this->input->method() !== 'post') {
            $this->session->set_flashdata('error', 'Método de requisição inválido.');
            redirect('responses');
            return;
        }
        
        // Obter filtros do formulário
        $filters = array();
        $questionnaire_id = $this->input->post('questionnaire_id');
        
        if (empty($questionnaire_id)) {
            $this->session->set_flashdata('error', 'Por favor, selecione um questionário.');
            redirect('responses');
            return;
        }
        
        if ($questionnaire_id && $questionnaire_id !== 'all') {
            $filters['questionnaire_id'] = $questionnaire_id;
            
            // Verificar se o questionário existe
            $questionnaire = $this->Questionnaire_model->get_by_id($questionnaire_id);
            if (!$questionnaire) {
                $this->session->set_flashdata('error', 'Questionário não encontrado.');
                redirect('responses');
                return;
            }
        }
        
        if ($this->input->post('date_from')) {
            $filters['date_from'] = $this->input->post('date_from');
        }
        
        if ($this->input->post('date_to')) {
            $filters['date_to'] = $this->input->post('date_to');
        }
        
        if ($this->input->post('applied_by')) {
            $filters['applied_by'] = $this->input->post('applied_by');
        }
        
        // Validar filtros
        $validation_errors = $this->Response_model->validate_export_filters($filters);
        if (!empty($validation_errors)) {
            $this->Response_model->log_export_activity(
                $user_id, 
                'raw_data', 
                $filters, 
                0, 
                'validation_error', 
                implode('; ', $validation_errors)
            );
            
            $this->session->set_flashdata('error', implode('<br>', $validation_errors));
            redirect('responses');
            return;
        }
        
        try {
            // Contar registros antes da exportação
            $record_count = $this->Response_model->count_by_filters($filters);
            
            // Log início da exportação
            $this->Response_model->log_export_activity(
                $user_id, 
                'raw_data', 
                $filters, 
                $record_count, 
                'started'
            );
            
            // Aumentar limites para exportação
            ini_set('memory_limit', '2024M');
            ini_set('max_execution_time', 600);
            
            // IMPORTANTE: Limpar qualquer output anterior e iniciar buffer
            if (ob_get_level()) {
                ob_end_clean();
            }
            ob_start();
            
            // Gerar arquivo Excel com colunas dinâmicas
            $this->_generate_raw_data_excel_dynamic($filters, $questionnaire_id);
            
            // Log sucesso da exportação
            $this->Response_model->log_export_activity(
                $user_id, 
                'raw_data', 
                $filters, 
                $record_count, 
                'success'
            );
            
        } catch (Exception $e) {
            // Limpar buffer em caso de erro
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Log erro da exportação
            $this->Response_model->log_export_activity(
                $user_id, 
                'raw_data', 
                $filters, 
                0, 
                'error', 
                $e->getMessage()
            );
            
            log_message('error', 'Erro na exportação de dados brutos: ' . $e->getMessage());
            $this->session->set_flashdata('error', 'Erro ao gerar exportação. Contate o administrador se o problema persistir.');
            redirect('responses');
        }
    }

    /**
     * NOVO: Método para gerar Excel com colunas dinâmicas das perguntas
     */
    private function _generate_raw_data_excel_dynamic($filters, $questionnaire_id) {
        // Obter dados das respostas
        $raw_data = $this->Response_model->get_raw_data_for_export($filters);
        
        if (empty($raw_data)) {
            $this->session->set_flashdata('warning', 'Nenhum dado encontrado para exportar com os filtros selecionados.');
            redirect('responses');
            return;
        }
        
        // Log do número de registros
        log_message('info', 'Exportando ' . count($raw_data) . ' registros com colunas dinâmicas');
        
        try {
            // PASSO 1: Identificar todas as perguntas únicas presentes nos dados
            $all_questions = $this->_extract_all_questions($raw_data);
            
            // PASSO 2: Gerar headers dinâmicos
            $dynamic_headers = $this->_generate_dynamic_headers($all_questions);
            
            // Definir nome do arquivo
            $filename = 'dados_brutos_' . date('Y-m-d_H-i-s') . '.xls';
            if ($questionnaire_id && $questionnaire_id !== 'all') {
                $questionnaire = $this->Questionnaire_model->get_by_id($questionnaire_id);
                if ($questionnaire) {
                    $safe_title = preg_replace('/[^a-zA-Z0-9_-]/', '_', $questionnaire->title);
                    $filename = 'dados_brutos_' . $safe_title . '_' . date('Y-m-d_H-i-s') . '.xls';
                }
            }
            
            // PASSO 3: Gerar conteúdo Excel XML com colunas dinâmicas
            $excel_content = $this->_generate_excel_xml_dynamic($raw_data, $dynamic_headers, $all_questions);
            
            // Limpar buffer anterior
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Configurar headers para download
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Content-Length: ' . strlen($excel_content));
            
            // Enviar conteúdo
            echo $excel_content;
            
            // Log do arquivo gerado
            log_message('info', 'Arquivo Excel gerado: ' . $filename . ' com ' . count($raw_data) . ' registros e ' . count($all_questions) . ' colunas de perguntas');
            
            exit;
            
        } catch (Exception $e) {
            log_message('error', 'Erro ao criar arquivo Excel dinâmico: ' . $e->getMessage());
            throw new Exception('Erro ao gerar arquivo Excel: ' . $e->getMessage());
        }
    }
    
    /**
     * NOVO: Extrair todas as perguntas únicas de todos os registros
     */
    private function _extract_all_questions($raw_data) {
        $all_questions = array();
        
        foreach ($raw_data as $response) {
            if (!empty($response->answers_json)) {
                $answers = json_decode($response->answers_json, true);
                
                if (is_array($answers)) {
                    foreach ($answers as $answer) {
                        if (isset($answer['question_id']) && isset($answer['question_text'])) {
                            $question_id = $answer['question_id'];
                            
                            if (!isset($all_questions[$question_id])) {
                                $all_questions[$question_id] = array(
                                    'id' => $question_id,
                                    'text' => $answer['question_text'],
                                    'type' => $answer['question_type'] ?? 'text',
                                    'order_index' => $answer['order_index'] ?? 999
                                );
                            }
                        }
                    }
                }
            }
        }
        
        // Ordenar por order_index
        uasort($all_questions, function($a, $b) {
            return $a['order_index'] <=> $b['order_index'];
        });
        
        return $all_questions;
    }
    
    /**
     * NOVO: Gerar headers dinâmicos combinando fixos + perguntas
     */
    private function _generate_dynamic_headers($all_questions) {
        // Headers fixos (sem RESPOSTAS_JSON)
        $fixed_headers = [
            'QUESTIONÁRIO',
            'TÉCNICO RESPONSÁVEL PELA APLICAÇÃO',
            'DATA',
            'LATITUDE',
            'LONGITUDE',
            'LOCALIZAÇÃO',
            'CONSENTIMENTO DADO',
            'STATUS SINCRONIZAÇÃO',
            'DATA INÍCIO',
            'DATA CONCLUSÃO',
            'FOTO CAPTURADA',
            'ID DA QUESTÃO'
        ];
        
        // Headers das perguntas (limitado a 60 caracteres por header)
        $question_headers = array();
        foreach ($all_questions as $question) {
            $header_text = $question['text'];
            
            // Limitar tamanho do header
            if (strlen($header_text) > 60) {
                $header_text = substr($header_text, 0, 57) . '...';
            }
            
            // Limpar caracteres problemáticos
            $header_text = preg_replace('/[^\p{L}\p{N}\s\-_\?\!\.\,\:]/u', '', $header_text);
            
            $question_headers[] = $header_text;
        }
        
        // Combinar headers
        return array_merge($fixed_headers, $question_headers);
    }
    
    /**
     * NOVO: Gerar XML Excel com colunas dinâmicas
     */
    private function _generate_excel_xml_dynamic($raw_data, $dynamic_headers, $all_questions) {
        // Início do XML Excel
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        
        // Estilos
        $xml .= $this->_get_excel_styles();
        
        // Planilha de Resultados
        $xml .= '<Worksheet ss:Name="Resultados">' . "\n";
        $xml .= '<Table>' . "\n";
        
        // Definir larguras das colunas dinamicamente
        foreach ($dynamic_headers as $header) {
            $width = $this->_get_dynamic_column_width($header);
            $xml .= '<Column ss:Width="' . $width . '"/>' . "\n";
        }
        
        // Headers
        $xml .= '<Row ss:StyleID="HeaderStyle">' . "\n";
        foreach ($dynamic_headers as $header) {
            $xml .= '<Cell><Data ss:Type="String">' . $this->_escape_xml($header) . '</Data></Cell>' . "\n";
        }
        $xml .= '</Row>' . "\n";
        
        // Dados
        foreach ($raw_data as $response_data) {
            $mapped_data = $this->_map_response_to_dynamic_format($response_data, $all_questions);
            
            $xml .= '<Row>' . "\n";
            foreach ($mapped_data as $value) {
                // Sanitizar e validar valor
                $cell_value = $this->_sanitize_cell_value($value);
                $cell_type = $this->_get_cell_type($cell_value);
                
                $xml .= '<Cell><Data ss:Type="' . $cell_type . '">' . 
                        $this->_escape_xml($cell_value) . '</Data></Cell>' . "\n";
            }
            $xml .= '</Row>' . "\n";
        }
        
        $xml .= '</Table>' . "\n";
        $xml .= '</Worksheet>' . "\n";
        
        // Planilha de Estatísticas
        $xml .= $this->_generate_statistics_worksheet($raw_data);
        
        // Planilha de Perguntas (nova)
        $xml .= $this->_generate_questions_worksheet($all_questions);
        
        $xml .= '</Workbook>';
        
        return $xml;
    }
    
    /**
     * NOVO: Mapear dados para formato dinâmico com colunas de perguntas
     */
    private function _map_response_to_dynamic_format($response_data, $all_questions) {
        // Dados fixos (sem RESPOSTAS_JSON)
        $fixed_data = [
            $this->_get_safe_value($response_data, 'questionnaire_title'),
            $this->_get_safe_value($response_data, 'applied_by_name'),
            $this->_format_date($response_data->completed_at),
            $this->_get_safe_value($response_data, 'latitude'),
            $this->_get_safe_value($response_data, 'longitude'),
            $this->_get_safe_value($response_data, 'location_name'),            
            $response_data->consent_given ? 'SIM' : 'NÃO',
            strtoupper($this->_get_safe_value($response_data, 'sync_status', 'UNKNOWN')),
            $this->_format_datetime($response_data->started_at),
            $this->_format_datetime($response_data->completed_at),
            !empty($response_data->photo_path) ? 'SIM' : 'NÃO',
            $response_data->id . '-' . uniqid()
        ];
        
        // Criar mapa de respostas desta resposta específica
        $answers_map = array();
        if (!empty($response_data->answers_json)) {
            $answers = json_decode($response_data->answers_json, true);
            
            if (is_array($answers)) {
                foreach ($answers as $answer) {
                    if (isset($answer['question_id'])) {
                        $question_id = $answer['question_id'];
                        $response_value = $answer['response_value'] ?? '';
                        
                        // Formatar valor da resposta baseado no tipo
                        $answers_map[$question_id] = $this->_format_answer_value($response_value, $answer['question_type'] ?? 'text');
                    }
                }
            }
        }
        
        // Dados dinâmicos das perguntas (em ordem)
        $question_data = array();
        foreach ($all_questions as $question) {
            $question_id = $question['id'];
            
            if (isset($answers_map[$question_id])) {
                $question_data[] = $answers_map[$question_id];
            } else {
                $question_data[] = ''; // Resposta vazia para esta pergunta
            }
        }
        
        // Combinar dados fixos + dados das perguntas
        return array_merge($fixed_data, $question_data);
    }
    
    /**
     * NOVO: Formatar valor da resposta baseado no tipo da pergunta
     */
    private function _format_answer_value($value, $question_type) {
        if (empty($value)) {
            return '';
        }
        
        switch ($question_type) {
            case 'radio':
            case 'checkbox':
                // Se é array de opções selecionadas
                if (is_array($value)) {
                    return implode(', ', $value);
                }
                // Se é JSON string
                if (is_string($value) && (strpos($value, '[') === 0 || strpos($value, '{') === 0)) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        return implode(', ', $decoded);
                    }
                }
                return strval($value);
                
            case 'date':
                if (!empty($value)) {
                    try {
                        return date('d/m/Y', strtotime($value));
                    } catch (Exception $e) {
                        return strval($value);
                    }
                }
                return '';
                
            case 'datetime':
                if (!empty($value)) {
                    try {
                        return date('d/m/Y H:i', strtotime($value));
                    } catch (Exception $e) {
                        return strval($value);
                    }
                }
                return '';
                
            case 'number':
                return is_numeric($value) ? $value : strval($value);
                
            default:
                return strval($value);
        }
    }
    
    /**
     * NOVO: Determinar largura da coluna dinamicamente
     */
    private function _get_dynamic_column_width($header) {
        // Larguras dos headers fixos
        $fixed_widths = array(
            'QUESTIONÁRIO' => 180,
            'TÉCNICO RESPONSÁVEL PELA APLICAÇÃO' => 200,
            'DATA' => 80,
            'LATITUDE' => 100,
            'LONGITUDE' => 100,
            'LOCALIZAÇÃO' => 200,
            'CONSENTIMENTO DADO' => 120,
            'STATUS SINCRONIZAÇÃO' => 120,
            'DATA INÍCIO' => 120,
            'DATA CONCLUSÃO' => 120,
            'FOTO CAPTURADA' => 100,
            'ID DA QUESTÃO' => 150
        );
        
        // Se é header fixo, usar largura predefinida
        if (isset($fixed_widths[$header])) {
            return $fixed_widths[$header];
        }
        
        // Para headers de perguntas, calcular baseado no tamanho do texto
        $length = strlen($header);
        
        if ($length <= 20) {
            return 120;
        } elseif ($length <= 40) {
            return 180;
        } elseif ($length <= 60) {
            return 250;
        } else {
            return 300;
        }
    }
    
    /**
     * NOVO: Gerar planilha com lista de perguntas
     */
    private function _generate_questions_worksheet($all_questions) {
        $xml = '<Worksheet ss:Name="Perguntas">' . "\n";
        $xml .= '<Table>' . "\n";
        
        // Headers da planilha de perguntas
        $xml .= '<Row ss:StyleID="HeaderStyle">' . "\n";
        $xml .= '<Cell><Data ss:Type="String">ID</Data></Cell>' . "\n";
        $xml .= '<Cell><Data ss:Type="String">Ordem</Data></Cell>' . "\n";
        $xml .= '<Cell><Data ss:Type="String">Tipo</Data></Cell>' . "\n";
        $xml .= '<Cell><Data ss:Type="String">Texto da Pergunta</Data></Cell>' . "\n";
        $xml .= '</Row>' . "\n";
        
        // Dados das perguntas
        foreach ($all_questions as $question) {
            $xml .= '<Row>' . "\n";
            $xml .= '<Cell><Data ss:Type="Number">' . $question['id'] . '</Data></Cell>' . "\n";
            $xml .= '<Cell><Data ss:Type="Number">' . $question['order_index'] . '</Data></Cell>' . "\n";
            $xml .= '<Cell><Data ss:Type="String">' . $this->_escape_xml($question['type']) . '</Data></Cell>' . "\n";
            $xml .= '<Cell><Data ss:Type="String">' . $this->_escape_xml($question['text']) . '</Data></Cell>' . "\n";
            $xml .= '</Row>' . "\n";
        }
        
        $xml .= '</Table>' . "\n";
        $xml .= '</Worksheet>' . "\n";
        
        return $xml;
    }

    // =====================================
    // MÉTODOS AUXILIARES MANTIDOS
    // =====================================
    
    /**
     * Sanitizar valor da célula
     */
    private function _sanitize_cell_value($value) {
        if (is_null($value)) {
            return '';
        }
        
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }
        
        if (is_object($value)) {
            return 'Objeto não convertível';
        }
        
        if (is_bool($value)) {
            return $value ? 'SIM' : 'NÃO';
        }
        
        $str_value = strval($value);
        
        if (strlen($str_value) > 32000) {
            $str_value = substr($str_value, 0, 31997) . '...';
        }
        
        return $str_value;
    }
    
    /**
     * Determinar tipo da célula
     */
    private function _get_cell_type($value) {
        if (is_numeric($value) && !preg_match('/^0/', $value) && strlen($value) < 15) {
            return 'Number';
        }
        
        return 'String';
    }
    
    /**
     * Escape seguro para XML
     */
    private function _escape_xml($value) {
        $str_value = $this->_sanitize_cell_value($value);
        $escaped = htmlspecialchars($str_value, ENT_XML1 | ENT_COMPAT, 'UTF-8', false);
        return preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $escaped);
    }
    
    /**
     * Gerar estilos Excel XML
     */
    private function _get_excel_styles() {
        return '<Styles>
            <Style ss:ID="HeaderStyle">
                <Font ss:Bold="1" ss:Size="12"/>
                <Interior ss:Color="#D9EDF7" ss:Pattern="Solid"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
                </Borders>
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            </Style>
            <Style ss:ID="DataStyle">
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
                </Borders>
            </Style>
        </Styles>' . "\n";
    }
    
    /**
     * Gerar planilha de estatísticas
     */
    private function _generate_statistics_worksheet($raw_data) {
        $total_responses = count($raw_data);
        $total_with_consent = 0;
        $total_with_location = 0;
        $total_with_photo = 0;
        $applicators = array();
        
        foreach ($raw_data as $response) {
            if ($response->consent_given) $total_with_consent++;
            if (!empty($response->latitude) && !empty($response->longitude)) $total_with_location++;
            if (!empty($response->photo_path)) $total_with_photo++;
            
            $applicator = $response->applied_by_name ?? 'Não informado';
            $applicators[$applicator] = ($applicators[$applicator] ?? 0) + 1;
        }
        
        $xml = '<Worksheet ss:Name="Estatísticas">' . "\n";
        $xml .= '<Table>' . "\n";
        
        $xml .= '<Row>' . "\n";
        $xml .= '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Estatísticas da Exportação</Data></Cell>' . "\n";
        $xml .= '<Cell></Cell>' . "\n";
        $xml .= '</Row>' . "\n";
        
        $xml .= '<Row><Cell></Cell></Row>' . "\n";
        
        $stats = array(
            'Total de Respostas' => $total_responses,
            'Com Consentimento' => $total_with_consent,
            'Com Localização' => $total_with_location,
            'Com Foto' => $total_with_photo,
            'Taxa de Consentimento (%)' => $total_responses > 0 ? round(($total_with_consent / $total_responses) * 100, 1) : 0,
            'Taxa de Localização (%)' => $total_responses > 0 ? round(($total_with_location / $total_responses) * 100, 1) : 0,
            'Taxa de Fotos (%)' => $total_responses > 0 ? round(($total_with_photo / $total_responses) * 100, 1) : 0
        );
        
        foreach ($stats as $label => $value) {
            $xml .= '<Row>' . "\n";
            $xml .= '<Cell><Data ss:Type="String">' . $this->_escape_xml($label) . '</Data></Cell>' . "\n";
            $xml .= '<Cell><Data ss:Type="Number">' . $value . '</Data></Cell>' . "\n";
            $xml .= '</Row>' . "\n";
        }
        
        $xml .= '<Row><Cell></Cell></Row>' . "\n";
        
        $xml .= '<Row>' . "\n";
        $xml .= '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Respostas por Aplicador</Data></Cell>' . "\n";
        $xml .= '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Quantidade</Data></Cell>' . "\n";
        $xml .= '</Row>' . "\n";
        
        foreach ($applicators as $applicator => $count) {
            $xml .= '<Row>' . "\n";
            $xml .= '<Cell><Data ss:Type="String">' . $this->_escape_xml($applicator) . '</Data></Cell>' . "\n";
            $xml .= '<Cell><Data ss:Type="Number">' . $count . '</Data></Cell>' . "\n";
            $xml .= '</Row>' . "\n";
        }
        
        $xml .= '</Table>' . "\n";
        $xml .= '</Worksheet>' . "\n";
        
        return $xml;
    }
    
    /**
     * Obter valor seguro de propriedade
     */
    private function _get_safe_value($object, $property, $default = '') {
        if (isset($object->$property) && !is_null($object->$property)) {
            $value = $object->$property;
            
            if (is_array($value)) {
                return implode(', ', $value);
            }
            
            if (is_object($value)) {
                return method_exists($value, '__toString') ? strval($value) : $default;
            }
            
            return strval($value);
        }
        
        return $default;
    }
    
    /**
     * Formatar data segura
     */
    private function _format_date($date_string) {
        if (empty($date_string)) {
            return '';
        }
        
        try {
            return date('d/m/Y', strtotime($date_string));
        } catch (Exception $e) {
            return 'Data inválida';
        }
    }
    
    /**
     * Formatar data e hora segura
     */
    private function _format_datetime($datetime_string) {
        if (empty($datetime_string)) {
            return '';
        }
        
        try {
            return date('d/m/Y H:i', strtotime($datetime_string));
        } catch (Exception $e) {
            return 'Data inválida';
        }
    }

    // =====================================
    // MÉTODOS AJAX MANTIDOS
    // =====================================
    
    public function ajax_count_responses() {
        if (!$this->input->is_ajax_request()) {
            show_404();
            return;
        }
        
        $filters = array();
        
        if ($this->input->post('questionnaire_id') && $this->input->post('questionnaire_id') !== 'all') {
            $filters['questionnaire_id'] = $this->input->post('questionnaire_id');
        }
        
        if ($this->input->post('date_from')) {
            $filters['date_from'] = $this->input->post('date_from');
        }
        
        if ($this->input->post('date_to')) {
            $filters['date_to'] = $this->input->post('date_to');
        }
        
        if ($this->input->post('applied_by')) {
            $filters['applied_by'] = $this->input->post('applied_by');
        }
        
        $count = $this->Response_model->count_by_filters($filters);
        $statistics = $this->Response_model->get_export_statistics($filters);
        
        $response = array(
            'success' => true,
            'total_responses' => $count,
            'statistics' => $statistics
        );
        
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }
    
    public function ajax_preview_export() {
        if (!$this->input->is_ajax_request()) {
            show_404();
            return;
        }
        
        $filters = array();
        
        if ($this->input->post('questionnaire_id') && $this->input->post('questionnaire_id') !== 'all') {
            $filters['questionnaire_id'] = $this->input->post('questionnaire_id');
        }
        
        if ($this->input->post('date_from')) {
            $filters['date_from'] = $this->input->post('date_from');
        }
        
        if ($this->input->post('date_to')) {
            $filters['date_to'] = $this->input->post('date_to');
        }
        
        if ($this->input->post('applied_by')) {
            $filters['applied_by'] = $this->input->post('applied_by');
        }
        
        try {
            $preview_data = $this->Response_model->get_export_preview($filters, 5);
            $count = $this->Response_model->count_by_filters($filters);
            
            $response = array(
                'success' => true,
                'preview_data' => $preview_data,
                'total_count' => $count,
                'message' => "Prévia de {$count} registros encontrados"
            );
            
        } catch (Exception $e) {
            $response = array(
                'success' => false,
                'message' => 'Erro ao gerar prévia: ' . $e->getMessage()
            );
        }
        
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }
    
    public function ajax_validate_export() {
        if (!$this->input->is_ajax_request()) {
            show_404();
            return;
        }
        
        $filters = array();
        
        if ($this->input->post('questionnaire_id') && $this->input->post('questionnaire_id') !== 'all') {
            $filters['questionnaire_id'] = $this->input->post('questionnaire_id');
        }
        
        if ($this->input->post('date_from')) {
            $filters['date_from'] = $this->input->post('date_from');
        }
        
        if ($this->input->post('date_to')) {
            $filters['date_to'] = $this->input->post('date_to');
        }
        
        if ($this->input->post('applied_by')) {
            $filters['applied_by'] = $this->input->post('applied_by');
        }
        
        $validation_errors = $this->Response_model->validate_export_filters($filters);
        
        if (empty($validation_errors)) {
            $count = $this->Response_model->count_by_filters($filters);
            $statistics = $this->Response_model->get_export_statistics($filters);
            
            $response = array(
                'success' => true,
                'message' => "Validação concluída. {$count} registros serão exportados.",
                'count' => $count,
                'statistics' => $statistics
            );
        } else {
            $response = array(
                'success' => false,
                'errors' => $validation_errors,
                'message' => implode(' ', $validation_errors)
            );
        }
        
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }

    public function export_history() {
        $data['title'] = 'Histórico de Exportações - SXData';
        
        $user_id = $this->session->userdata('user_id');
        $is_admin = $this->session->userdata('user_role') === 'administrador';
        
        if ($is_admin) {
            $data['export_history'] = $this->Response_model->get_export_usage_statistics(30);
        } else {
            $data['export_history'] = $this->Response_model->get_user_export_history($user_id, 20);
        }
        
        $data['export_limits'] = $this->Response_model->check_export_limits($user_id);
        
        $this->load->view('admin/header', $data);
        $this->load->view('admin/responses/export_history', $data);
        $this->load->view('admin/footer');
    }

    public function get_location() {
        if ($this->input->method() !== 'post') {
            show_404();
            return;
        }
        
        $latitude = $this->input->post('latitude');
        $longitude = $this->input->post('longitude');
        
        if (empty($latitude) || empty($longitude) || 
            !is_numeric($latitude) || !is_numeric($longitude)) {
            echo 'N/A';
            return;
        }
        
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            echo 'N/A';
            return;
        }
        
        try {
            $location_name = $this->get_location_name_api($latitude, $longitude);
            echo $location_name;
        } catch (Exception $e) {
            log_message('error', 'Erro na geocodificação: ' . $e->getMessage());
            echo 'Erro ao carregar';
        }
    }

    private function get_location_name_api($latitude, $longitude) {
        $cache_key = 'location_' . round($latitude, 3) . '_' . round($longitude, 3);
        
        if ($this->cache) {
            $cached_result = $this->cache->get($cache_key);
            if ($cached_result !== FALSE) {
                return $cached_result;
            }
        }
        
        try {
            $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}&zoom=16&addressdetails=1&accept-language=pt-BR,pt,en";
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: SXData-App/1.0 (contact@sxdata.com)',
                        'Accept: application/json',
                        'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8'
                    ],
                    'timeout' => 10
                ]
            ]);

            $response = file_get_contents($url, false, $context);
            
            if ($response === FALSE) {
                return 'N/A';
            }

            $data = json_decode($response, true);
            
            if (isset($data['display_name'])) {
                $formatted_name = $this->format_location_name_api($data);
                
                if ($this->cache && $formatted_name !== 'N/A') {
                    $this->cache->save($cache_key, $formatted_name, 3600);
                }
                
                return $formatted_name;
            }

        } catch (Exception $e) {
            log_message('error', 'Erro na API de geocodificação: ' . $e->getMessage());
        }

        return 'N/A';
    }

    private function format_location_name_api($data) {
        if (!isset($data['address'])) {
            return isset($data['display_name']) ? substr($data['display_name'], 0, 50) . '...' : 'N/A';
        }

        $address = $data['address'];
        $location_parts = [];

        if (!empty($address['road'])) {
            $location_parts[] = $address['road'];
        }
        
        if (!empty($address['suburb']) || !empty($address['neighbourhood'])) {
            $location_parts[] = $address['suburb'] ?? $address['neighbourhood'];
        }
        
        if (!empty($address['city']) || !empty($address['town']) || !empty($address['village'])) {
            $location_parts[] = $address['city'] ?? $address['town'] ?? $address['village'];
        }
        
        if (!empty($address['state'])) {
            $location_parts[] = $address['state'];
        }

        $result = !empty($location_parts) ? implode(', ', $location_parts) : $data['display_name'];
        
        if (strlen($result) > 50) {
            $result = substr($result, 0, 47) . '...';
        }
        
        return $result;
    }

    private function check_auth() {
        if (!$this->session->userdata('admin_logged_in')) {
            redirect('auth/login');
        }

        // Cliente não acessa telas administrativas, nem pela URL
        exigir_equipe();
    }
    
}