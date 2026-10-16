<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Questionnaires extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        $this->load->model('Questionnaire_model');
        $this->load->model('Question_model');
        $this->load->model('User_model');
        $this->load->model('Project_model');
        $this->load->library('form_validation');
        $this->check_auth();
    }

    public function index() {
        $data['title'] = 'Questionários - SXData';
        $data['questionnaires'] = $this->Questionnaire_model->get_all_with_stats();
        $data['projects'] = $this->Project_model->get_for_select();
        
        $this->load->view('admin/header', $data);
        $this->load->view('admin/questionnaires/index', $data);
        $this->load->view('admin/footer');
    }

    public function create() {
        $preselected_project_id = $this->input->get('project_id');
        
        if ($this->input->post()) {
            $this->form_validation->set_rules('title', 'Título', 'required|max_length[200]');
            $this->form_validation->set_rules('description', 'Descrição', 'max_length[1000]');

            if ($this->form_validation->run()) {
                // Processar aplicadores selecionados
                $aplicadores = $this->input->post('aplicadores');
                $aplicadores_json = null;
                
                // Convencao do campo "aplicadores":
                //   NULL = todos (inclusive usuarios criados depois)
                //   "[]"  = nenhum (oculto no app, preservado no painel)
                //   "[1]" = apenas os IDs listados
                if ($aplicadores && is_array($aplicadores)) {
                    if (in_array('none', $aplicadores)) {
                        $aplicadores_json = json_encode(array());
                    } elseif (in_array('all', $aplicadores)) {
                        // NULL em vez de uma lista fixa de IDs: assim um aplicador
                        // novo passa a enxergar o questionario sem precisar reedita-lo.
                        $aplicadores_json = NULL;
                    } else {
                        $aplicadores_json = json_encode(array_map('intval', $aplicadores));
                    }
                }

                $questionnaire_data = array(
                    'title' => $this->input->post('title'),
                    'description' => $this->input->post('description'),
                    'created_by' => $this->session->userdata('admin_id'),
                    'requires_consent' => $this->input->post('requires_consent') ? TRUE : FALSE,
                    'requires_location' => $this->input->post('requires_location') ? TRUE : FALSE,
                    'requires_photo' => $this->input->post('requires_photo') ? TRUE : FALSE,
                    'estimated_time' => $this->input->post('estimated_time') ?: NULL,
                    'aplicadores' => $aplicadores_json,
                    'project_id' => $this->input->post('project_id') ?: NULL
                );

                // Iniciar transação
                $this->db->trans_start();

                $questionnaire_id = $this->Questionnaire_model->create($questionnaire_data);

                if ($questionnaire_id) {
                    $questions = $this->input->post('questions');
                    if ($questions && is_array($questions)) {
                        // ETAPA 1: Filtrar e validar perguntas
                        $valid_questions = $this->filter_and_validate_questions($questions);
                        
                        // ETAPA 2: Criar perguntas SEM lógica condicional primeiro para obter IDs reais
                        $temp_id_to_real_id_map = array();
                        $question_ids = array();
                        
                        foreach ($valid_questions as $index => $question) {
                            if (empty(trim($question['text'])) || empty($question['type'])) {
                                continue;
                            }
                            
                            $valid_types = ['text', 'textarea', 'number', 'email', 'phone', 'date', 'datetime', 'radio', 'checkbox', 'select'];
                            if (!in_array($question['type'], $valid_types)) {
                                log_message('error', "Tipo de pergunta inválido: {$question['type']}");
                                continue;
                            }
                            
                            // Criar pergunta SEM lógica condicional
                            $question_data = array(
                                'questionnaire_id' => $questionnaire_id,
                                'question_text' => trim($question['text']),
                                'question_type' => $question['type'],
                                'is_required' => isset($question['required']) ? TRUE : FALSE,
                                'order_index' => $index + 1,
                                'conditional_logic' => NULL // Temporariamente NULL
                            );

                            try {
                                $question_id = $this->Question_model->create($question_data);
                                
                                if ($question_id) {
                                    $question_ids[$index] = $question_id;
                                    
                                    // Mapear ID temporário para ID real (se existir)
                                    if (isset($question['temp_id'])) {
                                        $temp_id_to_real_id_map[$question['temp_id']] = $question_id;
                                    }
                                    // Para compatibilidade, também mapear baseado no padrão temp_X
                                    $temp_id_to_real_id_map["temp_" . ($index + 1)] = $question_id;
                                    
                                    // Salvar opções para tipos de múltipla escolha
                                    if (in_array($question['type'], ['radio', 'checkbox', 'select']) && isset($question['options'])) {
                                        $this->save_question_options($question_id, $question['options']);
                                    }
                                }
                            } catch (Exception $e) {
                                log_message('error', "Erro ao criar pergunta: " . $e->getMessage());
                                continue;
                            }
                        }
                        
                        // ETAPA 3: Processar e atualizar lógica condicional com IDs reais
                        foreach ($valid_questions as $index => $question) {
                            if (!isset($question_ids[$index])) {
                                continue; // Pergunta não foi criada com sucesso
                            }
                            
                            $question_id = $question_ids[$index];
                            $conditional_logic = null;
                            
                            // Processar lógica condicional se existir
                            if (isset($question['conditional_logic']) && !empty($question['conditional_logic'])) {
                                $logic = json_decode($question['conditional_logic'], true);
                                
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    // Converter IDs temporários para IDs reais
                                    $updated_logic = $this->convert_temp_ids_to_real_ids($logic, $temp_id_to_real_id_map);
                                    
                                    // Validar a lógica com IDs reais
                                    $validation = $this->validate_converted_logic($updated_logic, $index, $question_ids);
                                    
                                    if ($validation['valid']) {
                                        $conditional_logic = json_encode($updated_logic);
                                    } else {
                                        log_message('warning', 'Lógica condicional inválida removida da pergunta ' . ($index + 1) . ': ' . implode(', ', $validation['errors']));
                                    }
                                } else {
                                    log_message('warning', 'JSON inválido na lógica condicional da pergunta ' . ($index + 1));
                                }
                            }
                            
                            // Atualizar pergunta com lógica condicional processada
                            if ($conditional_logic !== null) {
                                $this->db->where('id', $question_id);
                                $this->db->update('questions', array('conditional_logic' => $conditional_logic));
                            }
                        }
                    }

                    // Finalizar transação
                    $this->db->trans_complete();

                    if ($this->db->trans_status() === FALSE) {
                        $this->session->set_flashdata('error', 'Erro ao criar questionário.');
                    } else {
                        $this->session->set_flashdata('success', 'Questionário criado com sucesso!');
                        
                        if ($this->input->post('project_id')) {
                            redirect('projects/view/' . $this->input->post('project_id'));
                        } else {
                            redirect('questionnaires');
                        }
                    }
                } else {
                    $this->db->trans_rollback();
                    $data['error'] = 'Erro ao criar questionário.';
                }
            }
        }

        $data['title'] = 'Criar Questionário - SXData';
        $data['aplicadores'] = $this->User_model->get_aplicadores_para_atribuicao();
        $data['projects'] = $this->Project_model->get_for_select();
        $data['preselected_project_id'] = $preselected_project_id;
        
        $this->load->view('admin/header', $data);
        $this->load->view('admin/questionnaires/create', $data);
        $this->load->view('admin/footer');
    }

    private function validate_converted_logic($logic, $current_index, $question_ids) {
        $errors = [];
        $warnings = [];
        
        // Criar array de IDs reais para validação
        $real_ids = array_values($question_ids);
        
        // Validar regras de visibilidade
        if (isset($logic['visibility']['conditions'])) {
            foreach ($logic['visibility']['conditions'] as $condition_index => $condition) {
                $validation = $this->validate_converted_condition($condition, $current_index, $question_ids, $condition_index + 1);
                $errors = array_merge($errors, $validation['errors']);
                $warnings = array_merge($warnings, $validation['warnings']);
            }
        }
        
        // Validar regras de obrigatoriedade
        if (isset($logic['required']['conditions'])) {
            foreach ($logic['required']['conditions'] as $condition_index => $condition) {
                $validation = $this->validate_converted_condition($condition, $current_index, $question_ids, $condition_index + 1);
                $errors = array_merge($errors, $validation['errors']);
                $warnings = array_merge($warnings, $validation['warnings']);
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    private function validate_converted_condition($condition, $current_index, $question_ids, $condition_number) {
        $errors = [];
        $warnings = [];
        
        if (!isset($condition['question']) || (!is_numeric($condition['question']) && !is_string($condition['question']))) {
            $errors[] = "Condição {$condition_number}: ID de pergunta inválido";
            return ['errors' => $errors, 'warnings' => $warnings];
        }
        
        if (!isset($condition['operator']) || empty($condition['operator'])) {
            $errors[] = "Condição {$condition_number}: Operador não definido";
            return ['errors' => $errors, 'warnings' => $warnings];
        }
        
        $target_question_id = $condition['question'];
        $operator = $condition['operator'];
        $value = isset($condition['value']) ? $condition['value'] : '';
        
        // Verificar se a questão referenciada existe nos IDs criados
        if (!in_array($target_question_id, array_values($question_ids))) {
            $errors[] = "Condição {$condition_number}: Questão referenciada (ID: {$target_question_id}) não existe";
            return ['errors' => $errors, 'warnings' => $warnings];
        }
        
        // Verificar se não está referenciando pergunta posterior (baseado no mapeamento)
        $target_index = array_search($target_question_id, $question_ids);
        if ($target_index !== false && $target_index >= $current_index) {
            $errors[] = "Condição {$condition_number}: Não pode referenciar pergunta posterior ou a si mesma";
        }
        
        $valid_operators = ['equals', 'not_equals', 'contains', 'not_contains', 'greater_than', 'less_than', 'is_empty', 'is_not_empty'];
        if (!in_array($operator, $valid_operators)) {
            $errors[] = "Condição {$condition_number}: Operador '{$operator}' inválido";
        }
        
        if (!in_array($operator, ['is_empty', 'is_not_empty']) && empty($value)) {
            $warnings[] = "Condição {$condition_number}: Valor não definido para operador '{$operator}'";
        }
        
        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function convert_temp_ids_to_real_ids($logic, $temp_id_to_real_id_map) {
        $updated_logic = $logic;
        
        // Processar regras de visibilidade
        if (isset($updated_logic['visibility']['conditions'])) {
            foreach ($updated_logic['visibility']['conditions'] as &$condition) {
                if (isset($condition['question'])) {
                    $temp_id = $condition['question'];
                    if (isset($temp_id_to_real_id_map[$temp_id])) {
                        $condition['question'] = $temp_id_to_real_id_map[$temp_id];
                        log_message('debug', "Convertido ID temporário '$temp_id' para ID real '{$temp_id_to_real_id_map[$temp_id]}'");
                    } else {
                        log_message('warning', "ID temporário '$temp_id' não encontrado no mapeamento");
                    }
                }
            }
        }
        
        // Processar regras de obrigatoriedade
        if (isset($updated_logic['required']['conditions'])) {
            foreach ($updated_logic['required']['conditions'] as &$condition) {
                if (isset($condition['question'])) {
                    $temp_id = $condition['question'];
                    if (isset($temp_id_to_real_id_map[$temp_id])) {
                        $condition['question'] = $temp_id_to_real_id_map[$temp_id];
                        log_message('debug', "Convertido ID temporário '$temp_id' para ID real '{$temp_id_to_real_id_map[$temp_id]}'");
                    } else {
                        log_message('warning', "ID temporário '$temp_id' não encontrado no mapeamento");
                    }
                }
            }
        }
        
        return $updated_logic;
    }

    private function filter_and_validate_questions($questions) {
        $valid_questions = array();
        
        foreach ($questions as $index => $question) {
            // Verificar se a pergunta tem dados básicos
            if (empty($question['text']) || empty($question['type'])) {
                continue;
            }
            
            // Limpar e validar dados da pergunta
            $clean_question = array(
                'text' => trim($question['text']),
                'type' => trim($question['type']),
                'required' => isset($question['required']) ? $question['required'] : false,
                'conditional_logic' => isset($question['conditional_logic']) ? $question['conditional_logic'] : null
            );
            
            // Validar e limpar opções para tipos de múltipla escolha
            if (in_array($clean_question['type'], ['radio', 'checkbox', 'select'])) {
                $clean_options = array();
                
                if (isset($question['options']) && is_array($question['options'])) {
                    foreach ($question['options'] as $opt_index => $option) {
                        if (isset($option['text']) && !empty(trim($option['text']))) {
                            $clean_options[] = array(
                                'text' => trim($option['text']),
                                'value' => isset($option['value']) && !empty($option['value']) 
                                        ? trim($option['value']) 
                                        : strtolower(str_replace(' ', '_', trim($option['text'])))
                            );
                        }
                    }
                }
                
                // Só adicionar pergunta se tiver pelo menos 2 opções válidas
                if (count($clean_options) >= 2) {
                    $clean_question['options'] = $clean_options;
                    $valid_questions[] = $clean_question;
                }
            } else {
                // Para outros tipos, adicionar sem opções
                $valid_questions[] = $clean_question;
            }
        }
        
        return $valid_questions;
    }

    private function save_question_options($question_id, $options) {
        if (!is_array($options)) {
            return false;
        }
        
        foreach ($options as $opt_index => $option) {
            if (isset($option['text']) && !empty(trim($option['text']))) {
                $option_data = array(
                    'question_id' => $question_id,
                    'option_text' => trim($option['text']),
                    'option_value' => isset($option['value']) && !empty($option['value']) 
                                ? trim($option['value']) 
                                : strtolower(str_replace(' ', '_', trim($option['text']))),
                    'order_index' => $opt_index + 1
                );
                
                try {
                    $this->Question_model->create_option($option_data);
                } catch (Exception $e) {
                    log_message('error', "Erro ao criar opção: " . $e->getMessage());
                    log_message('error', "Dados da opção: " . json_encode($option_data));
                }
            }
        }
        
        return true;
    }

     public function edit($id) {
        $questionnaire = $this->Questionnaire_model->get_by_id($id);
        if (!$questionnaire) {
            show_404();
        }

        // Debug em desenvolvimento
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'Questionnaire data from DB: ' . json_encode($questionnaire));
        }

        // Garantir que os valores sejam tratados como boolean
        $questionnaire->requires_consent = $this->_convert_to_boolean($questionnaire->requires_consent);
        $questionnaire->requires_location = $this->_convert_to_boolean($questionnaire->requires_location);
        $questionnaire->requires_photo = $this->_convert_to_boolean($questionnaire->requires_photo);

        if ($this->input->post()) {
            $this->form_validation->set_rules('title', 'Título', 'required|max_length[200]');
            $this->form_validation->set_rules('description', 'Descrição', 'max_length[1000]');

            if ($this->form_validation->run()) {
                // Processar aplicadores selecionados
                $aplicadores = $this->input->post('aplicadores');
                $aplicadores_json = null;
                
                // Convencao do campo "aplicadores":
                //   NULL = todos (inclusive usuarios criados depois)
                //   "[]"  = nenhum (oculto no app, preservado no painel)
                //   "[1]" = apenas os IDs listados
                if ($aplicadores && is_array($aplicadores)) {
                    if (in_array('none', $aplicadores)) {
                        $aplicadores_json = json_encode(array());
                    } elseif (in_array('all', $aplicadores)) {
                        // NULL em vez de uma lista fixa de IDs: assim um aplicador
                        // novo passa a enxergar o questionario sem precisar reedita-lo.
                        $aplicadores_json = NULL;
                    } else {
                        $aplicadores_json = json_encode(array_map('intval', $aplicadores));
                    }
                }

                $questionnaire_data = array(
                    'title' => $this->input->post('title'),
                    'description' => $this->input->post('description'),
                    'status' => $this->input->post('status'),
                    'requires_consent' => $this->input->post('requires_consent') ? TRUE : FALSE,
                    'requires_location' => $this->input->post('requires_location') ? TRUE : FALSE,
                    'requires_photo' => $this->input->post('requires_photo') ? TRUE : FALSE,
                    'estimated_time' => $this->input->post('estimated_time') ?: NULL,
                    'aplicadores' => $aplicadores_json,
                    'project_id' => $this->input->post('project_id') ?: NULL
                );

                // Iniciar transação
                $this->db->trans_start();

                // Atualizar dados do questionário
                $questionnaire_updated = $this->Questionnaire_model->update($id, $questionnaire_data);

                if ($questionnaire_updated) {
                    // Só processar perguntas se o modo de edição estava ativo
                    // Isso evita sobrescrever perguntas/lógica ao alterar apenas metadados
                    $edit_mode = $this->input->post('edit_mode');

                    if ($edit_mode === '1') {
                        // Processar perguntas editadas com lógica condicional
                        $questions = $this->input->post('questions');

                        if ($questions && is_array($questions)) {
                            // Processar lógica condicional
                            $processed_questions = $this->process_conditional_logic($questions);

                            // Obter perguntas existentes
                            $existing_questions = $this->Question_model->get_by_questionnaire($id);
                            $existing_question_ids = array_column($existing_questions, 'id');
                            $processed_question_ids = array();

                            foreach ($processed_questions as $index => $question) {
                                if (empty(trim($question['text']))) {
                                    continue;
                                }

                                $question_data = array(
                                    'questionnaire_id' => $id,
                                    'question_text' => trim($question['text']),
                                    'question_type' => $question['type'],
                                    'is_required' => isset($question['required']) ? TRUE : FALSE,
                                    'order_index' => $index + 1,
                                    'conditional_logic' => $question['conditional_logic']
                                );

                                $question_id = null;

                                // Verificar se é pergunta existente ou nova
                                if (!empty($question['id']) && is_numeric($question['id'])) {
                                    // Pergunta existente - atualizar
                                    $question_id = intval($question['id']);
                                    $this->Question_model->update($question_id, $question_data);
                                    $processed_question_ids[] = $question_id;
                                } else {
                                    // Nova pergunta - criar
                                    $question_id = $this->Question_model->create($question_data);
                                    if ($question_id) {
                                        $processed_question_ids[] = $question_id;
                                    }
                                }

                                // Processar opções para perguntas de múltipla escolha
                                if ($question_id && in_array($question['type'], ['radio', 'checkbox', 'select'])) {
                                    // Remover opções existentes
                                    $this->Question_model->delete_options($question_id);

                                    // Adicionar novas opções
                                    if (isset($question['options']) && is_array($question['options'])) {
                                        foreach ($question['options'] as $opt_index => $option) {
                                            if (!empty(trim($option['text']))) {
                                                $option_value = !empty($option['value']) ?
                                                            $option['value'] :
                                                            strtolower(str_replace(' ', '_', trim($option['text'])));

                                                $option_data = array(
                                                    'question_id' => $question_id,
                                                    'option_text' => trim($option['text']),
                                                    'option_value' => $option_value,
                                                    'order_index' => $opt_index + 1
                                                );

                                                $this->Question_model->create_option($option_data);
                                            }
                                        }
                                    }
                                }

                                // Salvar dicas de follow-up
                                if ($question_id) {
                                    $tips = isset($question['tips']) ? $question['tips'] : array();
                                    $this->_save_followup_tips($question_id, $tips);
                                }
                            }

                            // Remover perguntas que foram excluídas
                            $questions_to_delete = array_diff($existing_question_ids, $processed_question_ids);
                            foreach ($questions_to_delete as $question_id_to_delete) {
                                $this->Question_model->delete($question_id_to_delete);
                            }
                        }
                    }

                    // Finalizar transação
                    $this->db->trans_complete();

                    if ($this->db->trans_status() === FALSE) {
                        $this->session->set_flashdata('error', 'Erro ao salvar as alterações do questionário.');
                    } else {
                        $this->session->set_flashdata('success', 'Questionário atualizado com sucesso!');
                        
                        if ($questionnaire_data['project_id']) {
                            redirect('projects/view/' . $questionnaire_data['project_id']);
                        } else {
                            redirect('questionnaires');
                        }
                    }
                } else {
                    $this->db->trans_rollback();
                    $data['error'] = 'Erro ao atualizar questionário.';
                }
            }
        }

        $data['title'] = 'Editar Questionário - SXData';
        $data['questionnaire'] = $questionnaire;
        
        // Usar método seguro para carregar perguntas com lógica condicional
        $data['questions'] = $this->get_questions_safe($id);
        
        // Processar resumo da lógica condicional para cada pergunta
        foreach ($data['questions'] as $question) {
            if (!empty($question->conditional_logic)) {
                $question->logic_summary = $this->generate_logic_summary($question->conditional_logic);
            } else {
                $question->logic_summary = '';
            }
        }
        
        $data['aplicadores'] = $this->User_model->get_aplicadores_para_atribuicao();
        $data['projects'] = $this->Project_model->get_for_select();
        
        // Carregar dicas de follow-up agrupadas por question_id
        $data['question_tips'] = $this->_get_all_question_tips($id);

        // Decodificar aplicadores selecionados
        $data['aplicadores_selecionados'] = array();
        if ($questionnaire->aplicadores) {
            $data['aplicadores_selecionados'] = json_decode($questionnaire->aplicadores, true) ?: array();
        }

        if (ENVIRONMENT === 'development') {
            log_message('debug', 'Data being sent to view - requires_consent: ' . var_export($data['questionnaire']->requires_consent, true));
            log_message('debug', 'Questions loaded: ' . count($data['questions']));
        }
        
        $this->load->view('admin/header', $data);
        $this->load->view('admin/questionnaires/edit', $data);
        $this->load->view('admin/footer');
    }

    /**
     * Gerar resumo da lógica condicional
     * 
     * @param string $conditional_logic_json JSON da lógica condicional
     * @return string Resumo formatado
     */
    private function generate_logic_summary($conditional_logic_json) {
        if (empty($conditional_logic_json)) {
            return '';
        }
        
        $logic = json_decode($conditional_logic_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '<span class="text-danger">Lógica inválida</span>';
        }
        
        $summary = array();
        
        if (isset($logic['visibility']) && !empty($logic['visibility']['conditions'])) {
            $visibility_count = count($logic['visibility']['conditions']);
            $visibility_operator = isset($logic['visibility']['operator']) ? $logic['visibility']['operator'] : 'AND';
            $summary[] = "<strong>Visibilidade:</strong> $visibility_count condição(ões) com operador $visibility_operator";
        }
        
        if (isset($logic['required']) && !empty($logic['required']['conditions'])) {
            $required_count = count($logic['required']['conditions']);
            $required_operator = isset($logic['required']['operator']) ? $logic['required']['operator'] : 'AND';
            $summary[] = "<strong>Obrigatória:</strong> $required_count condição(ões) com operador $required_operator";
        }
        
        if (empty($summary)) {
            return '<span class="text-muted">Lógica vazia</span>';
        }
        
        return implode('<br>', $summary);
    }

    /**
     * Método helper robusto para carregar perguntas com lógica condicional
     */
    private function get_questions_safe($questionnaire_id) {
        // Lista de métodos para tentar, em ordem de preferência
        $methods = array(
            'get_by_questionnaire_with_logic_robust',
            'get_by_questionnaire_with_logic_subquery', 
            'get_by_questionnaire_with_logic_simple',
            'get_by_questionnaire_with_logic_individual',
            'get_by_questionnaire_with_logic'
        );
        
        foreach ($methods as $method) {
            try {
                if (method_exists($this->Question_model, $method)) {
                    $result = $this->Question_model->$method($questionnaire_id);
                    if (!empty($result)) {
                        log_message('debug', "Sucesso com método: $method");
                        return $result;
                    }
                }
            } catch (Exception $e) {
                log_message('error', "Falha no método $method: " . $e->getMessage());
                continue;
            }
        }
        
        // Se todos os métodos falharam, usar o método mais básico possível
        log_message('warning', 'Todos os métodos avançados falharam, usando método básico');
        return $this->get_questions_basic_fallback($questionnaire_id);
    }

    /**
     * Método básico de emergência para carregar perguntas
     */
    private function get_questions_basic_fallback($questionnaire_id) {
        try {
            $this->db->select('*');
            $this->db->from('questions');
            $this->db->where('questionnaire_id', $questionnaire_id);
            $this->db->order_by('order_index', 'ASC');
            
            $questions = $this->db->get()->result();
            
            foreach ($questions as &$question) {
                // Buscar opções uma por uma
                $this->db->select('*');
                $this->db->from('question_options');
                $this->db->where('question_id', $question->id);
                $this->db->order_by('order_index', 'ASC');
                
                $question->options = $this->db->get()->result();
                
                // Adicionar campos necessários para lógica condicional
                $question->conditional_logic_decoded = null;
                if (!empty($question->conditional_logic)) {
                    $logic = json_decode($question->conditional_logic, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $question->conditional_logic_decoded = $logic;
                    }
                }
            }
            
            return $questions;
            
        } catch (Exception $e) {
            log_message('error', 'Até mesmo o método básico falhou: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Processar e validar lógica condicional
     */
    private function process_conditional_logic($questions) {
        $processed_questions = [];
        
        foreach ($questions as $index => $question) {
            $processed_question = $question;
            
            // Processar lógica condicional se existir
            if (isset($question['conditional_logic']) && !empty($question['conditional_logic'])) {
                $logic = json_decode($question['conditional_logic'], true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Validar a lógica usando IDs
                    $validation = $this->validate_conditional_logic_structure_with_ids($logic, $index, $questions);
                    
                    if (!$validation['valid']) {
                        // Se há erros, remover a lógica inválida
                        $processed_question['conditional_logic'] = null;
                        log_message('warning', 'Lógica condicional inválida removida da pergunta ' . ($index + 1) . ': ' . implode(', ', $validation['errors']));
                    } else {
                        $processed_question['conditional_logic'] = json_encode($logic);
                    }
                } else {
                    $processed_question['conditional_logic'] = null;
                    log_message('warning', 'JSON inválido na lógica condicional da pergunta ' . ($index + 1));
                }
            } else {
                $processed_question['conditional_logic'] = null;
            }
            
            $processed_questions[] = $processed_question;
        }
        
        return $processed_questions;
    }

    private function validate_conditional_logic_structure_with_ids($logic, $current_index, $all_questions) {
        $errors = [];
        $warnings = [];
        
        // Criar mapa de ID para questão para validação
        $question_map = [];
        foreach ($all_questions as $idx => $q) {
            if (isset($q['id']) && !empty($q['id'])) {
                $question_map[$q['id']] = [
                    'index' => $idx,
                    'data' => $q
                ];
            }
        }
        
        // Validar regras de visibilidade
        if (isset($logic['visibility'])) {
            $validation = $this->validate_rule_structure_with_ids($logic['visibility'], 'visibility', $current_index, $question_map);
            $errors = array_merge($errors, $validation['errors']);
            $warnings = array_merge($warnings, $validation['warnings']);
        }
        
        // Validar regras de obrigatoriedade
        if (isset($logic['required'])) {
            $validation = $this->validate_rule_structure_with_ids($logic['required'], 'required', $current_index, $question_map);
            $errors = array_merge($errors, $validation['errors']);
            $warnings = array_merge($warnings, $validation['warnings']);
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    private function validate_rule_structure_with_ids($rule, $rule_type, $current_index, $question_map) {
        $errors = [];
        $warnings = [];
        
        if (!isset($rule['operator']) || !in_array($rule['operator'], ['AND', 'OR'])) {
            $errors[] = "Operador lógico inválido para regra de {$rule_type}";
        }
        
        if (!isset($rule['conditions']) || !is_array($rule['conditions']) || empty($rule['conditions'])) {
            $errors[] = "Nenhuma condição definida para regra de {$rule_type}";
            return ['errors' => $errors, 'warnings' => $warnings];
        }
        
        foreach ($rule['conditions'] as $condition_index => $condition) {
            $condition_validation = $this->validate_condition_structure_with_ids($condition, $current_index, $question_map, $condition_index + 1);
            $errors = array_merge($errors, $condition_validation['errors']);
            $warnings = array_merge($warnings, $condition_validation['warnings']);
        }
        
        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function validate_condition_structure_with_ids($condition, $current_index, $question_map, $condition_number) {
        $errors = [];
        $warnings = [];
        
        if (!isset($condition['question']) || (!is_numeric($condition['question']) && !is_string($condition['question']))) {
            $errors[] = "Condição {$condition_number}: ID de pergunta de referência inválido";
            return ['errors' => $errors, 'warnings' => $warnings];
        }
        
        if (!isset($condition['operator']) || empty($condition['operator'])) {
            $errors[] = "Condição {$condition_number}: Operador não definido";
            return ['errors' => $errors, 'warnings' => $warnings];
        }
        
        $target_question_id = $condition['question'];
        $operator = $condition['operator'];
        $value = isset($condition['value']) ? $condition['value'] : '';
        
        // Verificar se a questão referenciada existe
        if (!isset($question_map[$target_question_id])) {
            $errors[] = "Condição {$condition_number}: Questão referenciada (ID: {$target_question_id}) não existe";
            return ['errors' => $errors, 'warnings' => $warnings];
        }
        
        $target_question_info = $question_map[$target_question_id];
        
        // Verificar se não está referenciando pergunta posterior ou a si mesma
        if ($target_question_info['index'] >= $current_index) {
            $errors[] = "Condição {$condition_number}: Não pode referenciar pergunta posterior ou a si mesma";
        }
        
        $valid_operators = ['equals', 'not_equals', 'contains', 'not_contains', 'greater_than', 'less_than', 'is_empty', 'is_not_empty'];
        if (!in_array($operator, $valid_operators)) {
            $errors[] = "Condição {$condition_number}: Operador '{$operator}' inválido";
        }
        
        if (!in_array($operator, ['is_empty', 'is_not_empty']) && empty($value)) {
            $warnings[] = "Condição {$condition_number}: Valor não definido para operador '{$operator}'";
        }
        
        return ['errors' => $errors, 'warnings' => $warnings];
    }

    public function migrate_logic_from_indices_to_ids($questionnaire_id) {
        if (ENVIRONMENT !== 'development') {
            show_404();
        }
        
        try {
            $this->db->trans_start();
            
            // Obter todas as questões do questionário
            $questions = $this->get_questions_safe($questionnaire_id);
            
            $migrated_count = 0;
            
            foreach ($questions as $question) {
                if (!empty($question->conditional_logic)) {
                    $logic = json_decode($question->conditional_logic, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $updated_logic = $this->convert_logic_indices_to_ids($logic, $questions);
                        
                        if ($updated_logic !== $logic) {
                            $this->db->where('id', $question->id);
                            $this->db->update('questions', ['conditional_logic' => json_encode($updated_logic)]);
                            $migrated_count++;
                        }
                    }
                }
            }
            
            $this->db->trans_complete();
            
            if ($this->db->trans_status() === FALSE) {
                $this->session->set_flashdata('error', 'Erro na migração da lógica condicional.');
            } else {
                $this->session->set_flashdata('success', "Migração concluída! {$migrated_count} pergunta(s) foram migradas para usar IDs.");
            }
            
        } catch (Exception $e) {
            $this->db->trans_rollback();
            $this->session->set_flashdata('error', 'Erro na migração: ' . $e->getMessage());
        }
        
        redirect('questionnaires/edit/' . $questionnaire_id);
    }

    private function convert_logic_indices_to_ids($logic, $questions) {
        $updated_logic = $logic;
        
        // Criar mapa de índice para ID
        $index_to_id_map = [];
        foreach ($questions as $index => $question) {
            $index_to_id_map[$index] = $question->id;
        }
        
        // Processar regras de visibilidade
        if (isset($updated_logic['visibility']['conditions'])) {
            foreach ($updated_logic['visibility']['conditions'] as &$condition) {
                if (isset($condition['question']) && is_numeric($condition['question'])) {
                    $question_index = intval($condition['question']);
                    if (isset($index_to_id_map[$question_index])) {
                        $condition['question'] = $index_to_id_map[$question_index];
                    }
                }
            }
        }
        
        // Processar regras de obrigatoriedade
        if (isset($updated_logic['required']['conditions'])) {
            foreach ($updated_logic['required']['conditions'] as &$condition) {
                if (isset($condition['question']) && is_numeric($condition['question'])) {
                    $question_index = intval($condition['question']);
                    if (isset($index_to_id_map[$question_index])) {
                        $condition['question'] = $index_to_id_map[$question_index];
                    }
                }
            }
        }
        
        return $updated_logic;
    }
        

    /**
     * Validar estrutura da lógica condicional
     */
    private function validate_conditional_logic_structure($logic, $current_index, $all_questions) {
        $errors = [];
        $warnings = [];
        
        // Validar regras de visibilidade
        if (isset($logic['visibility'])) {
            $validation = $this->validate_rule_structure($logic['visibility'], 'visibility', $current_index, $all_questions);
            $errors = array_merge($errors, $validation['errors']);
            $warnings = array_merge($warnings, $validation['warnings']);
        }
        
        // Validar regras de obrigatoriedade
        if (isset($logic['required'])) {
            $validation = $this->validate_rule_structure($logic['required'], 'required', $current_index, $all_questions);
            $errors = array_merge($errors, $validation['errors']);
            $warnings = array_merge($warnings, $validation['warnings']);
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Validar estrutura de uma regra específica
     */
    private function validate_rule_structure($rule, $rule_type, $current_index, $all_questions) {
        $errors = [];
        $warnings = [];
        
        if (!isset($rule['operator']) || !in_array($rule['operator'], ['AND', 'OR'])) {
            $errors[] = "Operador lógico inválido para regra de {$rule_type}";
        }
        
        if (!isset($rule['conditions']) || !is_array($rule['conditions']) || empty($rule['conditions'])) {
            $errors[] = "Nenhuma condição definida para regra de {$rule_type}";
            return ['errors' => $errors, 'warnings' => $warnings];
        }
        
        foreach ($rule['conditions'] as $condition_index => $condition) {
            $condition_validation = $this->validate_condition_structure($condition, $current_index, $all_questions, $condition_index + 1);
            $errors = array_merge($errors, $condition_validation['errors']);
            $warnings = array_merge($warnings, $condition_validation['warnings']);
        }
        
        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validar estrutura de uma condição
     */
    private function validate_condition_structure($condition, $current_index, $all_questions, $condition_number) {
        $errors = [];
        $warnings = [];
        
        if (!isset($condition['question']) || !is_numeric($condition['question'])) {
            $errors[] = "Condição {$condition_number}: Pergunta de referência inválida";
            return ['errors' => $errors, 'warnings' => $warnings];
        }
        
        if (!isset($condition['operator']) || empty($condition['operator'])) {
            $errors[] = "Condição {$condition_number}: Operador não definido";
            return ['errors' => $errors, 'warnings' => $warnings];
        }
        
        $target_question_index = intval($condition['question']);
        $operator = $condition['operator'];
        $value = isset($condition['value']) ? $condition['value'] : '';
        
        if ($target_question_index >= $current_index) {
            $errors[] = "Condição {$condition_number}: Não pode referenciar pergunta posterior ou a si mesma";
        }
        
        if (!isset($all_questions[$target_question_index])) {
            $errors[] = "Condição {$condition_number}: Pergunta referenciada não existe";
        }
        
        $valid_operators = ['equals', 'not_equals', 'contains', 'not_contains', 'greater_than', 'less_than', 'is_empty', 'is_not_empty'];
        if (!in_array($operator, $valid_operators)) {
            $errors[] = "Condição {$condition_number}: Operador '{$operator}' inválido";
        }
        
        if (!in_array($operator, ['is_empty', 'is_not_empty']) && empty($value)) {
            $warnings[] = "Condição {$condition_number}: Valor não definido para operador '{$operator}'";
        }
        
        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Teste de lógica condicional
     */
    public function test_conditional_logic($questionnaire_id) {
        $questionnaire = $this->Questionnaire_model->get_by_id($questionnaire_id);
        if (!$questionnaire) {
            show_404();
        }
        
        $questions = $this->get_questions_safe($questionnaire_id);
        
        if ($this->input->post()) {
            $test_responses = $this->input->post('responses', array());
            
            try {
                if (method_exists($this->Questionnaire_model, 'execute_conditional_logic')) {
                    $question_states = $this->Questionnaire_model->execute_conditional_logic($questions, $test_responses);
                    $validation = $this->Questionnaire_model->validate_responses_with_conditional_logic($questions, $test_responses);
                    
                    $data['test_results'] = array(
                        'responses' => $test_responses,
                        'states' => $question_states,
                        'validation' => $validation
                    );
                } else {
                    $data['test_results'] = array(
                        'responses' => $test_responses,
                        'states' => $this->create_basic_states($questions),
                        'validation' => array('valid' => true, 'errors' => array())
                    );
                }
            } catch (Exception $e) {
                $data['test_error'] = 'Erro ao executar lógica condicional: ' . $e->getMessage();
            }
        }
        
        $data['title'] = 'Testar Lógica Condicional - ' . $questionnaire->title;
        $data['questionnaire'] = $questionnaire;
        $data['questions'] = $questions;
        
        $this->load->view('admin/header', $data);
        $this->load->view('admin/questionnaires/test_logic', $data);
        $this->load->view('admin/footer');
    }

    /**
     * Criar estados básicos para perguntas
     */
    private function create_basic_states($questions) {
        $states = array();
        
        foreach ($questions as $index => $question) {
            $states["q_$index"] = array(
                'visible' => true,
                'required' => (bool)$question->is_required,
                'original_required' => (bool)$question->is_required
            );
        }
        
        return $states;
    }

    /**
     * Validação AJAX de lógica condicional
     */
    public function validate_conditional_logic() {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        
        $questions = $this->input->post('questions');
        $response = array('success' => false, 'errors' => array(), 'warnings' => array());
        
        if ($questions && is_array($questions)) {
            $all_errors = array();
            $all_warnings = array();
            
            foreach ($questions as $index => $question) {
                if (isset($question['conditional_logic']) && !empty($question['conditional_logic'])) {
                    $logic = json_decode($question['conditional_logic'], true);
                    
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $validation = $this->validate_conditional_logic_structure_with_ids($logic, $index, $questions);
                        
                        foreach ($validation['errors'] as $error) {
                            $all_errors[] = "Pergunta " . ($index + 1) . ": " . $error;
                        }
                        
                        foreach ($validation['warnings'] as $warning) {
                            $all_warnings[] = "Pergunta " . ($index + 1) . ": " . $warning;
                        }
                    } else {
                        $all_errors[] = "Pergunta " . ($index + 1) . ": JSON inválido na lógica condicional";
                    }
                }
            }
            
            $response['success'] = empty($all_errors);
            $response['errors'] = $all_errors;
            $response['warnings'] = $all_warnings;
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    /**
     * Preview da lógica condicional via AJAX
     */
    public function preview_conditional_logic() {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        
        $questions = $this->input->post('questions');
        $sample_responses = $this->input->post('responses', array());
        
        $response = array('success' => false, 'data' => array());
        
        if ($questions && is_array($questions)) {
            try {
                $processed_questions = $this->process_conditional_logic($questions);
                
                if (method_exists($this->Questionnaire_model, 'execute_conditional_logic')) {
                    $question_states = $this->Questionnaire_model->execute_conditional_logic($processed_questions, $sample_responses);
                } else {
                    $question_states = $this->create_basic_states($processed_questions);
                }
                
                $response['success'] = true;
                $response['data'] = array(
                    'questions' => $processed_questions,
                    'states' => $question_states,
                    'sample_responses' => $sample_responses
                );
            } catch (Exception $e) {
                $response['error'] = 'Erro ao processar lógica condicional: ' . $e->getMessage();
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    /**
     * Helper para exibir resumo da lógica condicional
     */
    public function display_conditional_logic_summary($conditional_logic_json) {
        if (empty($conditional_logic_json)) {
            return '';
        }
        
        $logic = json_decode($conditional_logic_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '<span class="text-danger">Lógica inválida</span>';
        }
        
        $summary = array();
        
        if (isset($logic['visibility']) && !empty($logic['visibility']['conditions'])) {
            $summary[] = '<strong>Visibilidade:</strong> Tem regras definidas';
        }
        
        if (isset($logic['required']) && !empty($logic['required']['conditions'])) {
            $summary[] = '<strong>Obrigatória:</strong> Tem regras definidas';
        }
        
        return implode('<br>', $summary);
    }

    /**
     * Converter valor para boolean
     */
    private function _convert_to_boolean($value) {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return (bool) intval($value);
        }
        
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'true', 'yes', 'on', 't', 'y']);
        }
        
        return false;
    }

    /**
     * Filtrar questionários por projeto
     */
    public function by_project($project_id) {
        $project = $this->Project_model->get_by_id($project_id);
        if (!$project) {
            show_404();
        }

        $data['title'] = 'Questionários - ' . $project->name . ' - SXData';
        $data['questionnaires'] = $this->Questionnaire_model->get_by_project($project_id);
        $data['project'] = $project;
        $data['projects'] = $this->Project_model->get_for_select();
        
        $this->load->view('admin/header', $data);
        $this->load->view('admin/questionnaires/by_project', $data);
        $this->load->view('admin/footer');
    }

    public function duplicate($id) {
        $original = $this->Questionnaire_model->get_by_id($id);
        if (!$original) {
            show_404();
        }

        $original->requires_consent = $this->_convert_to_boolean($original->requires_consent);
        $original->requires_location = $this->_convert_to_boolean($original->requires_location);
        $original->requires_photo = $this->_convert_to_boolean($original->requires_photo);

        $new_data = array(
            'title' => $original->title . ' (Cópia)',
            'description' => $original->description,
            'created_by' => $this->session->userdata('admin_id'),
            'requires_consent' => $original->requires_consent,
            'requires_location' => $original->requires_location,
            'requires_photo' => $original->requires_photo,
            'estimated_time' => $original->estimated_time,
            'aplicadores' => $original->aplicadores,
            'project_id' => $original->project_id
        );

        $new_id = $this->Questionnaire_model->create($new_data);

        if ($new_id) {
            // Copiar perguntas SEM lógica condicional primeiro
            $questions = $this->get_questions_safe($id);
            $old_to_new_id_map = array(); // Mapeamento de IDs antigos para novos
            $new_question_ids = array(); // Array de novos IDs em ordem

            // Primeira passagem: criar perguntas sem lógica condicional
            foreach ($questions as $question) {
                $question_data = array(
                    'questionnaire_id' => $new_id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'is_required' => $question->is_required,
                    'order_index' => $question->order_index,
                    'conditional_logic' => null // Sem lógica condicional inicialmente
                );

                $new_question_id = $this->Question_model->create($question_data);

                // Mapear ID antigo para novo ID
                $old_to_new_id_map[$question->id] = $new_question_id;
                $new_question_ids[] = $new_question_id;

                // Copiar opções
                if (!empty($question->options)) {
                    foreach ($question->options as $option) {
                        $this->Question_model->create_option(array(
                            'question_id' => $new_question_id,
                            'option_text' => $option->option_text,
                            'option_value' => $option->option_value,
                            'order_index' => $option->order_index
                        ));
                    }
                }
            }

            // Segunda passagem: atualizar lógica condicional com IDs corretos
            foreach ($questions as $index => $question) {
                if (!empty($question->conditional_logic)) {
                    $logic = json_decode($question->conditional_logic, true);

                    if (json_last_error() === JSON_ERROR_NONE && $logic) {
                        // Converter IDs antigos para novos IDs
                        $updated_logic = $this->convert_temp_ids_to_real_ids($logic, $old_to_new_id_map);

                        // Atualizar a pergunta com a lógica corrigida
                        $new_question_id = $new_question_ids[$index];
                        $this->Question_model->update($new_question_id, array(
                            'conditional_logic' => json_encode($updated_logic)
                        ));

                        log_message('debug', "Lógica condicional atualizada para pergunta duplicada ID: $new_question_id");
                    } else {
                        log_message('warning', "Erro ao decodificar lógica condicional da pergunta original ID: {$question->id}");
                    }
                }
            }

            $this->session->set_flashdata('success', 'Questionário duplicado com sucesso!');
        } else {
            $this->session->set_flashdata('error', 'Erro ao duplicar questionário.');
        }

        redirect('questionnaires');
    }

    public function delete($id) {
        if ($this->Questionnaire_model->delete($id)) {
            $this->session->set_flashdata('success', 'Questionário excluído com sucesso!');
        } else {
            $this->session->set_flashdata('error', 'Erro ao excluir questionário.');
        }
        redirect('questionnaires');
    }

    /**
     * API para obter dados dos questionários
     */
    public function get_api_data() {
        $user_role = $this->input->get('role');
        $questionnaires = $this->Questionnaire_model->get_for_api($user_role);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $questionnaires
        ]);
    }

    /**
     * Busca AJAX de questionários
     */
    public function search() {
        $term = $this->input->get('term');
        $project_id = $this->input->get('project_id');
        
        if (!$term) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Termo de busca é obrigatório']);
            return;
        }
        
        $results = $this->Questionnaire_model->search($term, $project_id);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Debug do banco de dados
     */
    public function debug_database($questionnaire_id) {
        if (ENVIRONMENT !== 'development') {
            show_404();
        }
        
        echo "<h2>Debug do Banco de Dados</h2>";
        echo "<p><strong>Questionnaire ID:</strong> $questionnaire_id</p>";
        
        // Teste 1: Estrutura da tabela
        echo "<h3>1. Estrutura da tabela questions:</h3>";
        try {
            $result = $this->db->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'questions'")->result();
            echo "<pre>";
            print_r($result);
            echo "</pre>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
        }
        
        // Teste 2: Consulta básica
        echo "<h3>2. Consulta básica:</h3>";
        try {
            $this->db->select('id, question_text, conditional_logic');
            $this->db->from('questions');
            $this->db->where('questionnaire_id', $questionnaire_id);
            $basic = $this->db->get()->result();
            echo "<pre>";
            print_r($basic);
            echo "</pre>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
        }
        
        // Teste 3: Método seguro
        echo "<h3>3. Método seguro:</h3>";
        try {
            $safe_result = $this->get_questions_safe($questionnaire_id);
            echo "<p><strong>Número de perguntas carregadas:</strong> " . count($safe_result) . "</p>";
            if (!empty($safe_result)) {
                echo "<pre>";
                print_r($safe_result[0]);
                echo "</pre>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Erro no método seguro: " . $e->getMessage() . "</p>";
        }
        
        echo "<hr><p><a href='" . base_url('questionnaires/edit/' . $questionnaire_id) . "'>← Voltar para edição</a></p>";
    }

    /**
     * Exportar questionário com lógica condicional
     */
    public function export_with_logic($questionnaire_id) {
        $questionnaire = $this->Questionnaire_model->get_by_id($questionnaire_id);
        if (!$questionnaire) {
            show_404();
        }
        
        $questions = $this->get_questions_safe($questionnaire_id);
        
        $export_data = array(
            'questionnaire' => $questionnaire,
            'questions' => array()
        );
        
        foreach ($questions as $question) {
            $question_data = array(
                'id' => $question->id,
                'text' => $question->question_text,
                'type' => $question->question_type,
                'required' => (bool)$question->is_required,
                'order' => $question->order_index,
                'conditional_logic' => $question->conditional_logic ? json_decode($question->conditional_logic, true) : null,
                'options' => $question->options ?: array()
            );
            
            $export_data['questions'][] = $question_data;
        }
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="questionnaire_' . $questionnaire_id . '_with_logic.json"');
        echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Importar questionário com lógica condicional
     */
    public function import_with_logic() {
        if ($this->input->post() && isset($_FILES['import_file'])) {
            $file = $_FILES['import_file'];
            
            if ($file['type'] !== 'application/json') {
                $this->session->set_flashdata('error', 'Arquivo deve ser do tipo JSON.');
                redirect('questionnaires');
            }
            
            $content = file_get_contents($file['tmp_name']);
            $import_data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->session->set_flashdata('error', 'Arquivo JSON inválido.');
                redirect('questionnaires');
            }
            
            if (!isset($import_data['questionnaire']) || !isset($import_data['questions'])) {
                $this->session->set_flashdata('error', 'Estrutura do arquivo inválida.');
                redirect('questionnaires');
            }
            
            try {
                $this->db->trans_start();
                
                $questionnaire_data = array(
                    'title' => $import_data['questionnaire']->title . ' (Importado)',
                    'description' => $import_data['questionnaire']->description,
                    'created_by' => $this->session->userdata('admin_id'),
                    'requires_consent' => $import_data['questionnaire']->requires_consent ?: FALSE,
                    'requires_location' => $import_data['questionnaire']->requires_location ?: FALSE,
                    'requires_photo' => $import_data['questionnaire']->requires_photo ?: FALSE,
                    'estimated_time' => $import_data['questionnaire']->estimated_time,
                    'project_id' => $this->input->post('target_project_id') ?: NULL
                );
                
                $new_questionnaire_id = $this->Questionnaire_model->create($questionnaire_data);
                
                if ($new_questionnaire_id) {
                    foreach ($import_data['questions'] as $question_data) {
                        $new_question_data = array(
                            'questionnaire_id' => $new_questionnaire_id,
                            'question_text' => $question_data['text'],
                            'question_type' => $question_data['type'],
                            'is_required' => $question_data['required'] ?: FALSE,
                            'order_index' => $question_data['order'],
                            'conditional_logic' => $question_data['conditional_logic'] ? json_encode($question_data['conditional_logic']) : NULL
                        );
                        
                        $new_question_id = $this->Question_model->create($new_question_data);
                        
                        if (!empty($question_data['options'])) {
                            foreach ($question_data['options'] as $option_index => $option) {
                                $this->Question_model->create_option(array(
                                    'question_id' => $new_question_id,
                                    'option_text' => $option['option_text'],
                                    'option_value' => $option['option_value'],
                                    'order_index' => $option_index + 1
                                ));
                            }
                        }
                    }
                    
                    $this->db->trans_complete();
                    
                    if ($this->db->trans_status() === FALSE) {
                        $this->session->set_flashdata('error', 'Erro ao importar questionário.');
                    } else {
                        $this->session->set_flashdata('success', 'Questionário importado com sucesso!');
                        redirect('questionnaires/edit/' . $new_questionnaire_id);
                    }
                }
                
            } catch (Exception $e) {
                $this->db->trans_rollback();
                $this->session->set_flashdata('error', 'Erro ao importar: ' . $e->getMessage());
            }
        }
        
        redirect('questionnaires');
    }

    /**
     * Obter estatísticas de lógica condicional
     */
    public function conditional_logic_stats($questionnaire_id = null) {
        try {
            if (method_exists($this->Question_model, 'get_conditional_logic_stats')) {
                $stats = $this->Question_model->get_conditional_logic_stats($questionnaire_id);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'data' => $stats
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Método de estatísticas não disponível'
                ]);
            }
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao obter estatísticas: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Validar integridade da lógica condicional
     */
    public function validate_logic_integrity($questionnaire_id) {
        try {
            if (method_exists($this->Question_model, 'validate_questionnaire_logic_integrity')) {
                $validation = $this->Question_model->validate_questionnaire_logic_integrity($questionnaire_id);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'data' => $validation
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Método de validação não disponível'
                ]);
            }
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao validar integridade: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Limpar lógica condicional inválida
     */
    public function clean_invalid_logic($questionnaire_id) {
        try {
            if (method_exists($this->Question_model, 'clean_invalid_conditional_logic')) {
                $cleaned_count = $this->Question_model->clean_invalid_conditional_logic($questionnaire_id);
                
                $this->session->set_flashdata('success', "Lógica condicional limpa! $cleaned_count pergunta(s) foram corrigidas.");
            } else {
                $this->session->set_flashdata('error', 'Método de limpeza não disponível.');
            }
        } catch (Exception $e) {
            $this->session->set_flashdata('error', 'Erro ao limpar lógica: ' . $e->getMessage());
        }
        
        redirect('questionnaires/edit/' . $questionnaire_id);
    }

    /**
     * Análise detalhada da lógica condicional
     */
    public function logic_analysis($questionnaire_id) {
        $questionnaire = $this->Questionnaire_model->get_by_id($questionnaire_id);
        if (!$questionnaire) {
            show_404();
        }
        
        $questions = $this->get_questions_safe($questionnaire_id);
        
        $data['title'] = 'Análise de Lógica Condicional - ' . $questionnaire->title;
        $data['questionnaire'] = $questionnaire;
        $data['questions'] = $questions;
        
        // Obter análise detalhada se método existe
        if (method_exists($this->Question_model, 'export_conditional_logic_analysis')) {
            $data['analysis'] = $this->Question_model->export_conditional_logic_analysis($questionnaire_id);
        } else {
            $data['analysis'] = array();
        }
        
        // Obter estatísticas se método existe
        if (method_exists($this->Question_model, 'get_conditional_logic_stats')) {
            $data['stats'] = $this->Question_model->get_conditional_logic_stats($questionnaire_id);
        } else {
            $data['stats'] = array();
        }
        
        $this->load->view('admin/header', $data);
        $this->load->view('admin/questionnaires/logic_analysis', $data);
        $this->load->view('admin/footer');
    }

    /**
     * Página de ajuda sobre lógica condicional
     */
    public function conditional_logic_help() {
        $data['title'] = 'Ajuda - Lógica Condicional - SXData';
        
        $this->load->view('admin/header', $data);
        $this->load->view('admin/questionnaires/conditional_logic_help', $data);
        $this->load->view('admin/footer');
    }

    /**
     * Migrar lógica condicional (para atualizações futuras)
     */
    public function migrate_logic($questionnaire_id = null) {
        if (ENVIRONMENT !== 'development') {
            show_404();
        }
        
        try {
            if (method_exists($this->Question_model, 'migrate_conditional_logic')) {
                if ($questionnaire_id) {
                    $migrated_count = $this->Question_model->migrate_conditional_logic($questionnaire_id);
                    $this->session->set_flashdata('success', "Migração concluída! $migrated_count pergunta(s) foram migradas.");
                    redirect('questionnaires/edit/' . $questionnaire_id);
                } else {
                    echo "<h2>Migração de Lógica Condicional</h2>";
                    echo "<p>Esta função migraria todas as lógicas condicionais do sistema.</p>";
                    echo "<p><strong>Disponível apenas em desenvolvimento.</strong></p>";
                }
            } else {
                $this->session->set_flashdata('error', 'Método de migração não disponível.');
                redirect('questionnaires');
            }
        } catch (Exception $e) {
            $this->session->set_flashdata('error', 'Erro na migração: ' . $e->getMessage());
            redirect('questionnaires');
        }
    }

    /**
     * Verificação de autenticação
     */
    private function _save_followup_tips($question_id, $tips) {
        // Remover tips manuais existentes
        $this->db->where('question_id', $question_id)->delete('question_followup_tips');

        if (empty($tips) || !is_array($tips)) return;

        foreach ($tips as $tip_text) {
            $tip_text = trim($tip_text);
            if (empty($tip_text)) continue;

            $this->db->insert('question_followup_tips', array(
                'question_id' => $question_id,
                'tip'         => $tip_text,
                'source'      => 'manual',
                'created_by'  => $this->session->userdata('admin_id'),
            ));
        }
    }

    private function _get_all_question_tips($questionnaire_id) {
        $questions = $this->Question_model->get_by_questionnaire($questionnaire_id);
        $qids = array();
        foreach ($questions as $q) {
            $qids[] = $q->id;
        }
        if (empty($qids)) return array();

        $tips = $this->db->where_in('question_id', $qids)
                         ->order_by('id', 'ASC')
                         ->get('question_followup_tips')
                         ->result();

        $grouped = array();
        foreach ($tips as $tip) {
            $grouped[$tip->question_id][] = $tip;
        }
        return $grouped;
    }

    // ============================================================
    // COLETA PÚBLICA POR QR CODE
    // ============================================================

    /**
     * Links públicos (QR Codes) de um questionário.
     */
    public function qrcode($id) {
        $this->require_team();

        $questionnaire = $this->Questionnaire_model->get_by_id($id);
        if (!$questionnaire) {
            show_404();
            return;
        }

        $this->load->model('Public_link_model');

        $data['title'] = 'QR Code - ' . $questionnaire->title . ' - SXData';
        $data['questionnaire'] = $questionnaire;
        $data['links'] = $this->Public_link_model->get_for_questionnaire($id);

        $this->load->view('admin/header', $data);
        $this->load->view('admin/questionnaires/qrcode', $data);
        $this->load->view('admin/footer');
    }

    public function qrcode_criar($id) {
        $this->require_team();

        if ($this->input->method() !== 'post' || !$this->Questionnaire_model->get_by_id($id)) {
            redirect('questionnaires');
            return;
        }

        $this->load->model('Public_link_model');

        $label = trim((string) $this->input->post('label'));
        $expires = trim((string) $this->input->post('expires_at'));
        $expires_at = null;
        if ($expires !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $expires);
            // Vale até o fim do dia escolhido
            $expires_at = $d ? $d->format('Y-m-d') . ' 23:59:59' : null;
        }

        if ($this->Public_link_model->create($id, mb_substr($label, 0, 200), $expires_at, $this->session->userdata('admin_id'))) {
            $this->session->set_flashdata('success', 'QR Code criado. Ele já pode ser impresso ou compartilhado.');
        } else {
            $this->session->set_flashdata('error', 'Não foi possível criar o QR Code.');
        }

        redirect('questionnaires/qrcode/' . (int) $id);
    }

    /**
     * Ativa ou desativa um link. Desativado, o QR Code impresso passa a
     * mostrar "pesquisa encerrada"; as respostas já recebidas continuam.
     */
    public function qrcode_status($link_id) {
        $this->require_team();

        $this->load->model('Public_link_model');
        $link = $this->Public_link_model->get_by_id($link_id);

        if ($this->input->method() !== 'post' || !$link) {
            redirect('questionnaires');
            return;
        }

        $ativar = $this->input->post('ativar') === '1';
        $this->Public_link_model->set_active($link_id, $ativar);
        $this->session->set_flashdata('success', $ativar ? 'QR Code reativado.' : 'QR Code desativado: ele não aceita mais respostas.');

        redirect('questionnaires/qrcode/' . (int) $link->questionnaire_id);
    }

    /**
     * Ações que só a equipe (administrador e supervisor) pode executar.
     */
    private function require_team() {
        if (!in_array($this->session->userdata('admin_role'), ['administrador', 'supervisor'], true)) {
            $this->session->set_flashdata('error', 'Acesso restrito à equipe do SXData.');
            redirect('dashboard');
            exit;
        }
    }

    private function check_auth() {
        if (!$this->session->userdata('admin_logged_in')) {
            redirect('auth/login');
        }

        // Cliente não acessa telas administrativas, nem pela URL
        exigir_equipe();
    }
}