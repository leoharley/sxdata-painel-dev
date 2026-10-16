<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Questionnaire_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    public function get_all() {
        $this->db->select('q.*, u.full_name as created_by_name, p.name as project_name');
        $this->db->from('questionnaires q');
        $this->db->join('users u', 'q.created_by = u.id', 'left');
        $this->db->join('projects p', 'q.project_id = p.id', 'left');
        $this->db->order_by('q.created_at', 'DESC');
        return $this->db->get()->result();
    }

    public function get_active() {
        $this->db->select('q.*, u.full_name as created_by_name, p.name as project_name');
        $this->db->from('questionnaires q');
        $this->db->join('users u', 'q.created_by = u.id', 'left');
        $this->db->join('projects p', 'q.project_id = p.id', 'left');
        $this->db->where('q.status', 'active');
        $this->db->order_by('q.created_at', 'DESC');
        return $this->db->get()->result();
    }

    /**
     * Questionarios que devem aparecer nos FILTROS do painel (relatorios, respostas).
     *
     * Inclui os pausados/inativos que ja possuem respostas coletadas, para que
     * o historico nunca desapareca do dashboard quando o questionario e tirado do app.
     * Um questionario inativo e SEM respostas nao polui o filtro.
     */
    public function get_for_analysis() {
        $this->db->select('q.*, u.full_name as created_by_name, p.name as project_name,
                          (SELECT COUNT(*) FROM form_responses fr WHERE fr.questionnaire_id = q.id) as response_count');
        $this->db->from('questionnaires q');
        $this->db->join('users u', 'q.created_by = u.id', 'left');
        $this->db->join('projects p', 'q.project_id = p.id', 'left');
        $this->db->where("(q.status = 'active' OR EXISTS (SELECT 1 FROM form_responses fr3 WHERE fr3.questionnaire_id = q.id))", NULL, FALSE);
        $this->db->order_by('q.created_at', 'DESC');
        return $this->db->get()->result();
    }

    public function get_all_with_stats() {
        $questionnaires = $this->get_all();
        
        foreach ($questionnaires as &$questionnaire) {
            // Contar perguntas
            $this->db->where('questionnaire_id', $questionnaire->id);
            $questionnaire->question_count = $this->db->count_all_results('questions');
            
            // Contar respostas
            $this->db->where('questionnaire_id', $questionnaire->id);
            $questionnaire->response_count = $this->db->count_all_results('form_responses');
            
            // Última resposta
            $this->db->select('MAX(completed_at) as last_response');
            $this->db->where('questionnaire_id', $questionnaire->id);
            $this->db->where('completed_at IS NOT NULL');
            $last = $this->db->get('form_responses')->row();
            $questionnaire->last_response = $last ? $last->last_response : NULL;
        }
        
        return $questionnaires;
    }

    public function get_by_id($id) {
        // CORREÇÃO: Incluir explicitamente os campos de checkbox e project_id
        $this->db->select('q.id, q.title, q.description, q.status, q.version, 
                          q.requires_consent, q.requires_location, q.requires_photo,
                          q.estimated_time, q.aplicadores, q.created_by, q.created_at, q.updated_at,
                          q.project_id, p.name as project_name,
                          u.full_name as created_by_name');
        $this->db->from('questionnaires q');
        $this->db->join('users u', 'q.created_by = u.id', 'left');
        $this->db->join('projects p', 'q.project_id = p.id', 'left');
        $this->db->where('q.id', $id);
        
        $result = $this->db->get()->row();
        
        // Debug: Log da query executada (remover em produção)
        if (ENVIRONMENT === 'development' && $result) {
            log_message('debug', 'SQL Query: ' . $this->db->last_query());
            log_message('debug', 'Raw result from DB: ' . json_encode($result));
        }
        
        return $result;
    }

    public function get_by_project($project_id) {
        $this->db->select('q.*, u.full_name as created_by_name');
        $this->db->from('questionnaires q');
        $this->db->join('users u', 'q.created_by = u.id', 'left');
        $this->db->where('q.project_id', $project_id);
        $this->db->order_by('q.created_at', 'DESC');
        
        $questionnaires = $this->db->get()->result();
        
        // Adicionar contagem de perguntas e respostas
        foreach ($questionnaires as &$questionnaire) {
            $this->db->where('questionnaire_id', $questionnaire->id);
            $questionnaire->question_count = $this->db->count_all_results('questions');
            
            $this->db->where('questionnaire_id', $questionnaire->id);
            $questionnaire->response_count = $this->db->count_all_results('form_responses');
        }
        
        return $questionnaires;
    }

    public function count_by_project($project_id) {
        $this->db->where('project_id', $project_id);
        return $this->db->count_all_results('questionnaires');
    }

    public function create($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        
        // Debug: Log dos dados que serão inseridos (remover em produção)
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'Creating questionnaire with data: ' . json_encode($data));
        }
        
        return $this->db->insert('questionnaires', $data) ? $this->db->insert_id() : FALSE;
    }

    public function update($id, $data) {
       
        // Buscar versão atual antes de atualizar
        $this->db->select('version');
        $this->db->where('id', $id);
        $current = $this->db->get('questionnaires')->row();
        
        if ($current) {
            $data['version'] = $current->version + 1;
        }
        
        // Debug: Log dos dados que serão atualizados (remover em produção)
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'Updating questionnaire ID ' . $id . ' with data: ' . json_encode($data));
        }
        
        $this->db->where('id', $id);
        $result = $this->db->update('questionnaires', $data);
        
        // Debug: Log da query executada (remover em produção)
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'Update SQL Query: ' . $this->db->last_query());
            log_message('debug', 'Update result: ' . var_export($result, true));
        }
        
        return $result;
    }

    public function delete($id) {
        // Verificar se tem respostas associadas
        $this->db->where('questionnaire_id', $id);
        $has_responses = $this->db->count_all_results('form_responses') > 0;
        
        if ($has_responses) {
            // Apenas marcar como inativo se tiver respostas
            return $this->update($id, array('status' => 'inactive'));
        } else {
            // Deletar completamente se não tiver respostas
            $this->db->where('id', $id);
            return $this->db->delete('questionnaires');
        }
    }

    public function count_all() {
        return $this->db->count_all('questionnaires');
    }

    public function count_active() {
        $this->db->where('status', 'active');
        return $this->db->count_all_results('questionnaires');
    }

    public function get_usage_stats() {
        // Estatistica historica: NAO filtra por status.
        // Questionarios pausados/inativos continuam exibindo as respostas ja coletadas.
        $this->db->select('q.title, COUNT(fr.id) as response_count');
        $this->db->from('questionnaires q');
        $this->db->join('form_responses fr', 'q.id = fr.questionnaire_id', 'left');
        $this->db->group_by('q.id, q.title');
        $this->db->order_by('response_count', 'DESC');
        $this->db->limit(10);

        return $this->db->get()->result();
    }

    /**
     * Questionarios entregues ao APP.
     *
     * @param string|null $user_role  Mantido por compatibilidade
     * @param int|null    $user_id    Aplicador autenticado. Quando informado, aplica
     *                                a restricao de aplicadores permitidos do questionario.
     */
    public function get_for_api($user_role = null, $user_id = null) {
        $this->db->select('q.*, COUNT(questions.id) as question_count, p.name as project_name');
        $this->db->from('questionnaires q');
        $this->db->join('questions', 'q.id = questions.questionnaire_id', 'left');
        $this->db->join('projects p', 'q.project_id = p.id', 'left');
        $this->db->where('q.status', 'active');
        // Projeto concluido/cancelado tira o questionario do app automaticamente,
        // sem alterar o status do questionario (historico preservado no painel).
        // COALESCE cobre o LEFT JOIN sem projeto, onde p.status vem NULL.
        $this->db->where("COALESCE(p.status, 'active') NOT IN ('completed', 'cancelled')", NULL, FALSE);
        $this->db->group_by('q.id,p.name');
        $this->db->order_by('q.title', 'ASC');

        $questionnaires = $this->db->get()->result();

        // Filtrar por aplicadores permitidos (NULL = todos, "[]" = nenhum).
        // Feito em PHP porque o campo e um JSON em coluna TEXT.
        if ($user_id !== null) {
            $permitidos = array();
            foreach ($questionnaires as $questionnaire) {
                if ($this->is_aplicador_allowed($questionnaire, $user_id)) {
                    $permitidos[] = $questionnaire;
                }
            }
            $questionnaires = $permitidos;
        }

        // Adicionar perguntas para cada questionário
        foreach ($questionnaires as &$questionnaire) {
            $questionnaire->questions = $this->get_questions_with_options($questionnaire->id);
        }

        return $questionnaires;
    }

    /**
     * Regra unica de "este aplicador pode ver este questionario".
     *
     * NULL / vazio = sem restricao (todos)
     * "[]"         = nenhum aplicador (oculto no app)
     * "[1,2]"      = apenas os IDs listados
     *
     * @param object $questionnaire Registro do questionario (precisa ter ->aplicadores)
     * @param int    $aplicador_id
     * @return bool
     */
    public function is_aplicador_allowed($questionnaire, $aplicador_id) {
        if (!isset($questionnaire->aplicadores)
            || $questionnaire->aplicadores === NULL
            || trim((string) $questionnaire->aplicadores) === '') {
            return TRUE;
        }

        $aplicadores_permitidos = json_decode($questionnaire->aplicadores, true);

        if (!is_array($aplicadores_permitidos)) {
            return TRUE; // Fallback: JSON invalido nao bloqueia o aplicador
        }

        if (count($aplicadores_permitidos) === 0) {
            return FALSE; // "[]" = nenhum aplicador
        }

        // IDs podem vir como string no JSON; normaliza antes de comparar
        $aplicadores_permitidos = array_map('intval', $aplicadores_permitidos);
        return in_array((int) $aplicador_id, $aplicadores_permitidos, TRUE);
    }

    private function get_questions_with_options($questionnaire_id) {
        // Primeiro, buscar as questões
        $this->db->select('*');
        $this->db->from('questions');
        $this->db->where('questionnaire_id', $questionnaire_id);
        $this->db->order_by('order_index', 'ASC');
        
        $questions = $this->db->get()->result();
        
        // Para cada questão, buscar suas opções
        foreach ($questions as &$question) {
            $this->db->select('*');
            $this->db->from('question_options');
            $this->db->where('question_id', $question->id);
            $this->db->order_by('order_index', 'ASC');
            
            $question->options = $this->db->get()->result();
        }
        
        return $questions;
    }

    public function can_aplicador_access($questionnaire_id, $aplicador_id) {
        $questionnaire = $this->get_by_id($questionnaire_id);
        
        if (!$questionnaire || $questionnaire->status !== 'active') {
            return FALSE;
        }
        
        // Mesma regra usada pela API do app (NULL=todos, []=nenhum, [ids]=lista)
        return $this->is_aplicador_allowed($questionnaire, $aplicador_id);
    }

    /**
     * Retorna questionários que um aplicador específico pode acessar
     * 
     * @param int $aplicador_id ID do aplicador
     * @return array Lista de questionários disponíveis para o aplicador
     */
    public function get_for_aplicador($aplicador_id) {
        $this->db->select('q.*, COUNT(questions.id) as question_count, p.name as project_name');
        $this->db->from('questionnaires q');
        $this->db->join('questions', 'q.id = questions.questionnaire_id', 'left');
        $this->db->join('projects p', 'q.project_id = p.id', 'left');
        $this->db->where('q.status', 'active');
        // Mesma regra do app: projeto concluido/cancelado nao aparece para o aplicador.
        $this->db->where("COALESCE(p.status, 'active') NOT IN ('completed', 'cancelled')", NULL, FALSE);
        $this->db->group_by('q.id');
        $this->db->order_by('q.title', 'ASC');

        $all_questionnaires = $this->db->get()->result();
        
        // Filtrar questionários que o aplicador pode acessar
        $accessible_questionnaires = array();
        
        foreach ($all_questionnaires as $questionnaire) {
            if ($this->can_aplicador_access($questionnaire->id, $aplicador_id)) {
                // Adicionar perguntas para cada questionário
                $questionnaire->questions = $this->get_questions_with_options($questionnaire->id);
                $accessible_questionnaires[] = $questionnaire;
            }
        }
        
        return $accessible_questionnaires;
    }

    /**
     * Retorna estatísticas de uso por aplicador
     * 
     * @param int $questionnaire_id ID do questionário (opcional)
     * @return array Estatísticas de aplicadores
     */
    public function get_aplicador_stats($questionnaire_id = null) {
        $this->db->select('u.id, u.full_name, u.username, COUNT(fr.id) as total_responses');
        $this->db->from('users u');
        $this->db->join('form_responses fr', 'u.id = fr.applied_by', 'left');
        $this->db->where('u.role', 'aplicador');
        $this->db->where('u.is_active', TRUE);
        
        if ($questionnaire_id) {
            $this->db->where('fr.questionnaire_id', $questionnaire_id);
        }
        
        $this->db->group_by('u.id, u.full_name, u.username');
        $this->db->order_by('total_responses', 'DESC');
        
        return $this->db->get()->result();
    }

    /**
     * Retorna os nomes dos aplicadores permitidos para um questionário
     * 
     * @param object $questionnaire Objeto do questionário
     * @return string Nomes dos aplicadores separados por vírgula
     */
    public function get_aplicadores_names($questionnaire) {
        // NULL / string vazia = sem restricao
        if ($questionnaire->aplicadores === NULL || trim((string) $questionnaire->aplicadores) === '') {
            return 'Todos os aplicadores';
        }

        $aplicadores_ids = json_decode($questionnaire->aplicadores, true);

        if (!is_array($aplicadores_ids)) {
            return 'Todos os aplicadores';
        }

        // "[]" = nenhum aplicador (questionario oculto no app)
        if (count($aplicadores_ids) === 0) {
            return 'Nenhum aplicador (oculto no app)';
        }

        $this->db->select('full_name');
        $this->db->where_in('id', $aplicadores_ids);
        $this->db->where('role', 'aplicador');
        $this->db->where('is_active', TRUE);
        $aplicadores = $this->db->get('users')->result();
        
        if (empty($aplicadores)) {
            return 'Nenhum aplicador válido';
        }
        
        $nomes = array_column($aplicadores, 'full_name');
        return implode(', ', $nomes);
    }

    /**
     * Retorna estatísticas de questionários por projeto
     * 
     * @return array Estatísticas agrupadas por projeto
     */
    public function get_stats_by_project() {
        $this->db->select('p.id, p.name, COUNT(q.id) as questionnaire_count, 
                          SUM(CASE WHEN q.status = "active" THEN 1 ELSE 0 END) as active_count');
        $this->db->from('projects p');
        $this->db->join('questionnaires q', 'p.id = q.project_id', 'left');
        $this->db->group_by('p.id, p.name');
        $this->db->order_by('questionnaire_count', 'DESC');
        
        return $this->db->get()->result();
    }

    /**
     * Buscar questionários por termo
     * 
     * @param string $term Termo de busca
     * @param int $project_id ID do projeto (opcional)
     * @return array Lista de questionários encontrados
     */
    public function search($term, $project_id = null) {
        $this->db->select('q.*, u.full_name as created_by_name, p.name as project_name');
        $this->db->from('questionnaires q');
        $this->db->join('users u', 'q.created_by = u.id', 'left');
        $this->db->join('projects p', 'q.project_id = p.id', 'left');
        
        $this->db->group_start();
            $this->db->like('q.title', $term);
            $this->db->or_like('q.description', $term);
        $this->db->group_end();
        
        if ($project_id) {
            $this->db->where('q.project_id', $project_id);
        }
        
        $this->db->order_by('q.created_at', 'DESC');
        
        return $this->db->get()->result();
    }

    /**
     * Método para verificar se os campos existem na tabela
     * (método auxiliar para debug - remover em produção)
     */
    public function verify_table_structure() {
        if (ENVIRONMENT === 'development') {
            $query = $this->db->query("DESCRIBE questionnaires");
            $fields = $query->result();
            
            log_message('debug', 'Questionnaires table structure:');
            foreach ($fields as $field) {
                log_message('debug', 'Field: ' . $field->Field . ', Type: ' . $field->Type . ', Null: ' . $field->Null . ', Default: ' . $field->Default);
            }
        }
    }

    /**
     * Executar lógica condicional para um conjunto de respostas
     * 
     * @param array $questions Array de perguntas com lógica condicional
     * @param array $responses Respostas fornecidas
     * @return array Estados das perguntas (visível, obrigatória, etc.)
     */
    public function execute_conditional_logic($questions, $responses = []) {
        $question_states = [];
        
        // Criar mapa de ID para pergunta
        $question_map = [];
        foreach ($questions as $index => $question) {
            $question_map[$question->id] = [
                'index' => $index,
                'question' => $question
            ];
            
            // Inicializar estados padrão usando IDs
            $question_states[$question->id] = [
                'visible' => true,
                'required' => (bool)$question->is_required,
                'original_required' => (bool)$question->is_required,
                'index' => $index
            ];
        }
        
        // Processar lógica condicional
        foreach ($questions as $index => $question) {
            if (!empty($question->conditional_logic_decoded)) {
                $logic = $question->conditional_logic_decoded;
                
                // Processar regras de visibilidade
                if (isset($logic['visibility'])) {
                    $visibility_result = $this->evaluate_rule_with_ids($logic['visibility'], $responses, $question_map);
                    $question_states[$question->id]['visible'] = $visibility_result;
                }
                
                // Processar regras de obrigatoriedade
                if (isset($logic['required'])) {
                    $required_result = $this->evaluate_rule_with_ids($logic['required'], $responses, $question_map);
                    if ($required_result) {
                        $question_states[$question->id]['required'] = true;
                    }
                }
                
                // Se pergunta não está visível, não deve ser obrigatória
                if (!$question_states[$question->id]['visible']) {
                    $question_states[$question->id]['required'] = false;
                }
            }
        }
        
        return $question_states;
    }

    private function evaluate_rule_with_ids($rule, $responses, $question_map) {
        if (!isset($rule['conditions']) || empty($rule['conditions'])) {
            return true;
        }
        
        $operator = isset($rule['operator']) ? $rule['operator'] : 'AND';
        $results = [];
        
        foreach ($rule['conditions'] as $condition) {
            if (!isset($condition['question'], $condition['operator'])) {
                continue;
            }
            
            $target_question_id = $condition['question']; // Agora é ID
            $condition_operator = $condition['operator'];
            $condition_value = isset($condition['value']) ? $condition['value'] : '';
            
            // Obter resposta da pergunta alvo usando ID
            $response_value = isset($responses[$target_question_id]) ? 
                            $responses[$target_question_id] : '';
            
            // Obter dados da pergunta alvo
            $target_question = isset($question_map[$target_question_id]) ? 
                            $question_map[$target_question_id]['question'] : null;
            
            // Avaliar condição
            $condition_result = $this->evaluate_condition(
                $response_value, 
                $condition_operator, 
                $condition_value,
                $target_question
            );
            
            $results[] = $condition_result;
        }
        
        // Aplicar operador lógico
        if ($operator === 'OR') {
            return in_array(true, $results);
        } else { // AND
            return !in_array(false, $results);
        }
    }

    /**
     * Avaliar uma regra de lógica condicional
     * 
     * @param array $rule Regra a ser avaliada
     * @param array $responses Respostas fornecidas
     * @param array $questions Array de perguntas
     * @return bool Resultado da avaliação
     */
    private function evaluate_rule($rule, $responses, $questions) {
        if (!isset($rule['conditions']) || empty($rule['conditions'])) {
            return true;
        }
        
        $operator = isset($rule['operator']) ? $rule['operator'] : 'AND';
        $results = [];
        
        foreach ($rule['conditions'] as $condition) {
            if (!isset($condition['question'], $condition['operator'])) {
                continue;
            }
            
            $target_question_index = intval($condition['question']);
            $condition_operator = $condition['operator'];
            $condition_value = isset($condition['value']) ? $condition['value'] : '';
            
            // Obter resposta da pergunta alvo
            $response_value = isset($responses["q_$target_question_index"]) ? 
                            $responses["q_$target_question_index"] : '';
            
            // Avaliar condição
            $condition_result = $this->evaluate_condition(
                $response_value, 
                $condition_operator, 
                $condition_value,
                isset($questions[$target_question_index]) ? $questions[$target_question_index] : null
            );
            
            $results[] = $condition_result;
        }
        
        // Aplicar operador lógico
        if ($operator === 'OR') {
            return in_array(true, $results);
        } else { // AND
            return !in_array(false, $results);
        }
    }

    /**
     * Avaliar uma condição específica
     * 
     * @param mixed $response_value Valor da resposta
     * @param string $operator Operador da condição
     * @param mixed $condition_value Valor da condição
     * @param object $target_question Pergunta alvo (opcional)
     * @return bool Resultado da avaliação
     */
    private function evaluate_condition($response_value, $operator, $condition_value, $target_question = null) {
        // Normalizar valores
        $response_str = is_array($response_value) ? implode(',', $response_value) : strval($response_value);
        $condition_str = strval($condition_value);
        
        switch ($operator) {
            case 'equals':
                return $response_str === $condition_str;
                
            case 'not_equals':
                return $response_str !== $condition_str;
                
            case 'contains':
                if (is_array($response_value)) {
                    return in_array($condition_str, $response_value);
                }
                return strpos($response_str, $condition_str) !== false;
                
            case 'not_contains':
                if (is_array($response_value)) {
                    return !in_array($condition_str, $response_value);
                }
                return strpos($response_str, $condition_str) === false;
                
            case 'greater_than':
                return is_numeric($response_str) && is_numeric($condition_str) && 
                       floatval($response_str) > floatval($condition_str);
                
            case 'less_than':
                return is_numeric($response_str) && is_numeric($condition_str) && 
                       floatval($response_str) < floatval($condition_str);
                
            case 'is_empty':
                return empty($response_str) || trim($response_str) === '';
                
            case 'is_not_empty':
                return !empty($response_str) && trim($response_str) !== '';
                
            default:
                return false;
        }
    }

    /**
     * Validar respostas com lógica condicional
     * 
     * @param array $questions Array de perguntas
     * @param array $responses Respostas fornecidas
     * @return array Resultado da validação
     */
    public function validate_responses_with_conditional_logic($questions, $responses) {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];
        
        try {
            // Executar lógica condicional para obter estados das perguntas
            $question_states = $this->execute_conditional_logic($questions, $responses);
            
            // Validar cada pergunta
            foreach ($questions as $index => $question) {
                $state = $question_states[$question->id];
                $response = isset($responses[$question->id]) ? $responses[$question->id] : null;
                
                // Se pergunta não está visível, pular validação
                if (!$state['visible']) {
                    continue;
                }
                
                // Validar obrigatoriedade
                if ($state['required']) {
                    if (empty($response) && $response !== '0') {
                        $validation['errors'][] = "Pergunta " . ($index + 1) . " é obrigatória.";
                        $validation['valid'] = false;
                    }
                }
                
                // Validar tipo de resposta
                if (!empty($response)) {
                    $type_validation = $this->validate_response_type($question, $response);
                    if (!$type_validation['valid']) {
                        $validation['errors'] = array_merge($validation['errors'], $type_validation['errors']);
                        $validation['valid'] = false;
                    }
                }
            }
            
        } catch (Exception $e) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Erro na validação: ' . $e->getMessage();
            log_message('error', 'Erro na validação com lógica condicional: ' . $e->getMessage());
        }
        
        return $validation;
    }


    /**
     * Validar tipo de resposta para uma pergunta
     * 
     * @param object $question Pergunta
     * @param mixed $response Resposta
     * @return array Resultado da validação
     */
    private function validate_response_type($question, $response) {
        $validation = [
            'valid' => true,
            'errors' => []
        ];
        
        switch ($question->question_type) {
            case 'number':
                if (!is_numeric($response)) {
                    $validation['valid'] = false;
                    $validation['errors'][] = "Pergunta " . $question->order_index . " deve ser um número.";
                }
                break;
                
            case 'email':
                if (!filter_var($response, FILTER_VALIDATE_EMAIL)) {
                    $validation['valid'] = false;
                    $validation['errors'][] = "Pergunta " . $question->order_index . " deve ser um email válido.";
                }
                break;

            case 'phone':
                // DDD + 8 dígitos (fixo) ou DDD + 9 dígitos (celular)
                $digits = preg_replace('/\D/', '', (string) $response);
                if (strlen($digits) !== 10 && strlen($digits) !== 11) {
                    $validation['valid'] = false;
                    $validation['errors'][] = "Pergunta " . $question->order_index . " deve ser um telefone com DDD.";
                }
                break;

            case 'date':
                if (!$this->validate_date_format($response)) {
                    $validation['valid'] = false;
                    $validation['errors'][] = "Pergunta " . $question->order_index . " deve ser uma data válida.";
                }
                break;
                
            case 'radio':
            case 'select':
                // Validar se valor está nas opções disponíveis
                if (!empty($question->options)) {
                    $valid_values = array_column($question->options, 'option_text');
                    if (!in_array($response, $valid_values)) {
                        $validation['valid'] = false;
                        $validation['errors'][] = "Pergunta " . $question->order_index . " tem valor inválido.";
                    }
                }
                break;
                
            case 'checkbox':
                // Validar se é array e todos os valores estão nas opções
                if (!is_array($response)) {
                    $response = [$response];
                }
                
                if (!empty($question->options)) {
                    $valid_values = array_column($question->options, 'option_text');
                    foreach ($response as $value) {
                        if (!in_array($value, $valid_values)) {
                            $validation['valid'] = false;
                            $validation['errors'][] = "Pergunta " . $question->order_index . " tem valor inválido: $value";
                        }
                    }
                }
                break;
        }
        
        return $validation;
    }

    /**
     * Validar formato de data
     * 
     * @param string $date Data a ser validada
     * @return bool Válida ou não
     */
    private function validate_date_format($date) {
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s'];
        
        foreach ($formats as $format) {
            $d = DateTime::createFromFormat($format, $date);
            if ($d && $d->format($format) === $date) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Helper para exibir resumo da lógica condicional (usado na view)
     * Método chamado pelo controlador no edit.php
     * 
     * @param string $conditional_logic_json JSON da lógica condicional
     * @return string HTML do resumo
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
     * Verificar se questionário tem lógica condicional
     * 
     * @param int $questionnaire_id ID do questionário
     * @return bool Tem lógica condicional ou não
     */
    public function has_conditional_logic($questionnaire_id) {
        $this->db->where('questionnaire_id', $questionnaire_id);
        $this->db->where('conditional_logic IS NOT NULL');
        $this->db->where('conditional_logic !=', '');
        
        return $this->db->count_all_results('questions') > 0;
    }

    /**
     * Processar respostas com lógica condicional para salvar no banco
     * 
     * @param array $questions Array de perguntas
     * @param array $raw_responses Respostas brutas do formulário
     * @return array Respostas processadas e validadas
     */
    public function process_responses_with_logic($questions, $raw_responses) {
        $processed = [
            'responses' => [],
            'metadata' => [
                'total_questions' => count($questions),
                'answered_questions' => 0,
                'visible_questions' => 0,
                'required_questions' => 0,
                'logic_applied' => false
            ],
            'validation' => ['valid' => true, 'errors' => []]
        ];
        
        try {
            // Executar lógica condicional
            $question_states = $this->execute_conditional_logic($questions, $raw_responses);
            $processed['metadata']['logic_applied'] = true;
            
            // Processar cada pergunta
            foreach ($questions as $index => $question) {
                $state = $question_states[$question->id];
                $raw_response = isset($raw_responses[$question->id]) ? $raw_responses[$question->id] : null;
                
                // Contabilizar pergunta visível
                if ($state['visible']) {
                    $processed['metadata']['visible_questions']++;
                    
                    if ($state['required']) {
                        $processed['metadata']['required_questions']++;
                    }
                    
                    // Processar resposta se pergunta está visível
                    if (!empty($raw_response) || $raw_response === '0') {
                        $processed['responses'][$question->id] = [
                            'question_id' => $question->id,
                            'question_text' => $question->question_text,
                            'question_type' => $question->question_type,
                            'response_value' => $this->normalize_response_value($raw_response, $question),
                            'was_visible' => true,
                            'was_required' => $state['required'],
                            'order_index' => $question->order_index
                        ];
                        $processed['metadata']['answered_questions']++;
                    } else if ($state['required']) {
                        // Pergunta obrigatória não respondida
                        $processed['validation']['valid'] = false;
                        $processed['validation']['errors'][] = "Pergunta " . ($index + 1) . " é obrigatória.";
                    }
                } else {
                    // Pergunta não visível - registrar como não aplicável
                    $processed['responses'][$question->id] = [
                        'question_id' => $question->id,
                        'question_text' => $question->question_text,
                        'question_type' => $question->question_type,
                        'response_value' => null,
                        'was_visible' => false,
                        'was_required' => false,
                        'order_index' => $question->order_index,
                        'not_applicable' => true
                    ];
                }
            }
            
        } catch (Exception $e) {
            $processed['validation']['valid'] = false;
            $processed['validation']['errors'][] = 'Erro no processamento: ' . $e->getMessage();
            log_message('error', 'Erro no processamento de respostas com lógica: ' . $e->getMessage());
        }
        
        return $processed;
    }

    /**
     * Normalizar valor de resposta baseado no tipo de pergunta
     * 
     * @param mixed $raw_value Valor bruto
     * @param object $question Pergunta
     * @return mixed Valor normalizado
     */
    private function normalize_response_value($raw_value, $question) {
        switch ($question->question_type) {
            case 'checkbox':
                return is_array($raw_value) ? $raw_value : [$raw_value];
                
            case 'number':
                return is_numeric($raw_value) ? floatval($raw_value) : $raw_value;
                
            case 'radio':
            case 'select':
            case 'text':
            case 'textarea':
            case 'email':
            case 'date':
            default:
                return is_string($raw_value) ? trim($raw_value) : $raw_value;
        }
    }

    /**
     * Gerar relatório de uso da lógica condicional
     * 
     * @param int $questionnaire_id ID do questionário (opcional)
     * @return array Relatório detalhado
     */
    public function generate_conditional_logic_report($questionnaire_id = null) {
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'scope' => $questionnaire_id ? 'single_questionnaire' : 'all_questionnaires',
            'questionnaire_id' => $questionnaire_id,
            'summary' => [],
            'questionnaires' => [],
            'total_logic_usage' => 0,
            'complexity_analysis' => []
        ];
        
        try {
            // Query base
            $this->db->select('q.id, q.title, q.status, COUNT(questions.id) as total_questions');
            $this->db->from('questionnaires q');
            $this->db->join('questions', 'q.id = questions.questionnaire_id', 'left');
            
            if ($questionnaire_id) {
                $this->db->where('q.id', $questionnaire_id);
            }
            
            $this->db->group_by('q.id, q.title, q.status');
            $questionnaires = $this->db->get()->result();
            
            foreach ($questionnaires as $questionnaire) {
                // Análise detalhada por questionário
                $questionnaire_analysis = [
                    'id' => $questionnaire->id,
                    'title' => $questionnaire->title,
                    'status' => $questionnaire->status,
                    'total_questions' => $questionnaire->total_questions,
                    'questions_with_logic' => 0,
                    'logic_types' => ['visibility' => 0, 'required' => 0, 'both' => 0],
                    'complexity_score' => 0,
                    'dependencies' => [],
                    'id_based_logic' => true // Indicador de que usa IDs
                ];
                
                // Obter estatísticas de lógica condicional
                $this->load->model('Question_model');
                if (method_exists($this->Question_model, 'get_conditional_logic_stats')) {
                    $logic_stats = $this->Question_model->get_conditional_logic_stats($questionnaire->id);
                    
                    $questionnaire_analysis['questions_with_logic'] = $logic_stats['questions_with_logic'];
                    $questionnaire_analysis['logic_types']['visibility'] = $logic_stats['visibility_rules'];
                    $questionnaire_analysis['logic_types']['required'] = $logic_stats['required_rules'];
                    $questionnaire_analysis['complexity_score'] = $logic_stats['complex_logic'];
                    
                    $report['total_logic_usage'] += $logic_stats['questions_with_logic'];
                }
                
                $report['questionnaires'][] = $questionnaire_analysis;
            }
            
            // Gerar resumo
            $report['summary'] = [
                'total_questionnaires' => count($questionnaires),
                'questionnaires_with_logic' => count(array_filter($report['questionnaires'], function($q) {
                    return $q['questions_with_logic'] > 0;
                })),
                'total_questions' => array_sum(array_column($report['questionnaires'], 'total_questions')),
                'total_logic_questions' => $report['total_logic_usage'],
                'average_complexity' => count($report['questionnaires']) > 0 ? 
                    array_sum(array_column($report['questionnaires'], 'complexity_score')) / count($report['questionnaires']) : 0,
                'uses_id_based_logic' => true
            ];
            
        } catch (Exception $e) {
            $report['error'] = 'Erro na geração do relatório: ' . $e->getMessage();
            log_message('error', 'Erro no relatório de lógica condicional: ' . $e->getMessage());
        }
        
        return $report;
    }

    public function convert_responses_indices_to_ids($responses, $questions) {
        $converted_responses = [];
        
        // Criar mapa de índice para ID
        $index_to_id_map = [];
        foreach ($questions as $index => $question) {
            $index_to_id_map["q_$index"] = $question->id;
        }
        
        foreach ($responses as $key => $value) {
            if (isset($index_to_id_map[$key])) {
                $question_id = $index_to_id_map[$key];
                $converted_responses[$question_id] = $value;
            } else {
                // Se já está no formato de ID, manter
                $converted_responses[$key] = $value;
            }
        }
        
        return $converted_responses;
    }

    public function convert_states_indices_to_ids($states, $questions) {
        $converted_states = [];
        
        // Criar mapa de índice para ID
        $index_to_id_map = [];
        foreach ($questions as $index => $question) {
            $index_to_id_map["q_$index"] = $question->id;
        }
        
        foreach ($states as $key => $value) {
            if (isset($index_to_id_map[$key])) {
                $question_id = $index_to_id_map[$key];
                $converted_states[$question_id] = $value;
            } else {
                // Se já está no formato de ID, manter
                $converted_states[$key] = $value;
            }
        }
        
        return $converted_states;
    }

    public function detect_logic_format($questionnaire_id) {
        try {
            $this->db->select('conditional_logic');
            $this->db->from('questions');
            $this->db->where('questionnaire_id', $questionnaire_id);
            $this->db->where('conditional_logic IS NOT NULL');
            $this->db->where('conditional_logic !=', '');
            $this->db->limit(5); // Amostra pequena
            
            $results = $this->db->get()->result();
            
            $uses_ids = 0;
            $uses_indices = 0;
            
            foreach ($results as $result) {
                $logic = json_decode($result->conditional_logic, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    foreach (['visibility', 'required'] as $rule_type) {
                        if (isset($logic[$rule_type]['conditions'])) {
                            foreach ($logic[$rule_type]['conditions'] as $condition) {
                                if (isset($condition['question'])) {
                                    if (is_numeric($condition['question']) && $condition['question'] < 100) {
                                        $uses_indices++;
                                    } else {
                                        $uses_ids++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            return [
                'format' => $uses_ids > $uses_indices ? 'ids' : 'indices',
                'confidence' => $uses_ids + $uses_indices > 0 ? max($uses_ids, $uses_indices) / ($uses_ids + $uses_indices) : 0,
                'samples' => [
                    'ids' => $uses_ids,
                    'indices' => $uses_indices
                ]
            ];
            
        } catch (Exception $e) {
            log_message('error', 'Erro ao detectar formato da lógica: ' . $e->getMessage());
            return [
                'format' => 'unknown',
                'confidence' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Simular execução de lógica condicional (para testes)
     * 
     * @param int $questionnaire_id ID do questionário
     * @param array $test_responses Respostas de teste
     * @return array Resultado da simulação
     */
    public function simulate_conditional_logic($questionnaire_id, $test_responses = []) {
        $simulation = [
            'questionnaire_id' => $questionnaire_id,
            'test_responses' => $test_responses,
            'question_states' => [],
            'execution_log' => [],
            'performance' => [
                'start_time' => microtime(true),
                'end_time' => null,
                'execution_time' => null,
                'memory_usage' => memory_get_usage(true)
            ],
            'errors' => []
        ];
        
        try {
            $this->load->model('Question_model');
            
            if (method_exists($this->Question_model, 'get_by_questionnaire_with_logic')) {
                $questions = $this->Question_model->get_by_questionnaire_with_logic($questionnaire_id);
                $simulation['execution_log'][] = "Carregadas " . count($questions) . " perguntas";
                
                // Executar lógica condicional
                $question_states = $this->execute_conditional_logic($questions, $test_responses);
                $simulation['question_states'] = $question_states;
                $simulation['execution_log'][] = "Lógica condicional executada com sucesso";
                
                // Validar respostas
                $validation = $this->validate_responses_with_conditional_logic($questions, $test_responses);
                $simulation['validation'] = $validation;
                $simulation['execution_log'][] = "Validação concluída: " . ($validation['valid'] ? 'VÁLIDA' : 'INVÁLIDA');
                
                // Estatísticas da simulação
                $visible_questions = count(array_filter($question_states, function($state) {
                    return $state['visible'];
                }));
                $required_questions = count(array_filter($question_states, function($state) {
                    return $state['required'];
                }));
                
                $simulation['statistics'] = [
                    'total_questions' => count($questions),
                    'visible_questions' => $visible_questions,
                    'required_questions' => $required_questions,
                    'answered_questions' => count(array_filter($test_responses, function($response) {
                        return !empty($response) || $response === '0';
                    }))
                ];
                
            } else {
                $simulation['errors'][] = 'Método de lógica condicional não disponível';
            }
            
        } catch (Exception $e) {
            $simulation['errors'][] = 'Erro na simulação: ' . $e->getMessage();
            log_message('error', 'Erro na simulação de lógica condicional: ' . $e->getMessage());
        }
        
        $simulation['performance']['end_time'] = microtime(true);
        $simulation['performance']['execution_time'] = $simulation['performance']['end_time'] - $simulation['performance']['start_time'];
        $simulation['performance']['memory_peak'] = memory_get_peak_usage(true);
        
        return $simulation;
    }


    public function get_application_history($user_id, $filters = []) {
        // Debug: Log dos filtros recebidos
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'get_application_history called with user_id: ' . $user_id . ', filters: ' . json_encode($filters));
        }
        
        // CORREÇÃO: Reset query builder para evitar conflitos
        $this->db->reset_query();
        
        $this->db->select('
            fr.id,
            fr.questionnaire_id,
            fr.respondent_name,
            fr.respondent_email,
            fr.location_name,
            fr.latitude,
            fr.longitude,
            fr.started_at,
            fr.completed_at,
            fr.sync_status,
            fr.photo_path,
            fr.consent_given,
            fr.created_at,
            q.title as questionnaire_title,
            CONCAT(\'#\', LPAD(CAST(q.id AS VARCHAR), 3, \'0\')) as questionnaire_code
        ');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        
        // Condição principal: usuário e formulários completados
        $this->db->where('fr.applied_by', $user_id);
        $this->db->where('fr.completed_at IS NOT NULL');
        
        // Aplicar filtros - CORREÇÃO: Verificar se filtros existem antes de aplicar
        if (isset($filters['period']) && !empty($filters['period']) && $filters['period'] !== 'all') {
            switch ($filters['period']) {
                case 'today':
                    $this->db->where('fr.completed_at >=', date('Y-m-d 00:00:00'));
                    $this->db->where('fr.completed_at <=', date('Y-m-d 23:59:59'));
                    break;
                case 'week':
                    $this->db->where('fr.completed_at >=', date('Y-m-d 00:00:00', strtotime('-7 days')));
                    break;
                case 'month':
                    $this->db->where('fr.completed_at >=', date('Y-m-d 00:00:00', strtotime('-30 days')));
                    break;
            }
            
            // Debug: Log do filtro de período aplicado
            if (ENVIRONMENT === 'development') {
                log_message('debug', 'Applied period filter: ' . $filters['period']);
            }
        }
        
        if (isset($filters['sync_status']) && !empty($filters['sync_status'])) {
            $this->db->where('fr.sync_status', $filters['sync_status']);
            
            // Debug: Log do filtro de status aplicado
            if (ENVIRONMENT === 'development') {
                log_message('debug', 'Applied sync_status filter: ' . $filters['sync_status']);
            }
        }
        
        if (isset($filters['questionnaire_id']) && !empty($filters['questionnaire_id'])) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }
        
        // Paginação - CORREÇÃO: Validar valores numéricos
        if (isset($filters['limit']) && is_numeric($filters['limit']) && $filters['limit'] > 0) {
            $this->db->limit((int)$filters['limit']);
        } else {
            $this->db->limit(50); // Limite padrão
        }
        
        if (isset($filters['offset']) && is_numeric($filters['offset']) && $filters['offset'] >= 0) {
            $this->db->offset((int)$filters['offset']);
        }
        
        // Ordenação
        $this->db->order_by('fr.completed_at', 'DESC');
        
        // Execute a query
        $results = $this->db->get()->result();
        
        // Debug: Log da query executada e resultados
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'SQL Query executed: ' . $this->db->last_query());
            log_message('debug', 'Results count: ' . count($results));
            
            if (count($results) > 0) {
                log_message('debug', 'First result sample: ' . json_encode($results[0]));
            }
        }
        
        return $results;
    }

    /**
     * Obter contadores para filtros do histórico
     * 
     * @param int $user_id ID do usuário
     * @return array Contadores por filtro
     */
    public function get_history_counters($user_id) {
        $counters = [];
        
        // CORREÇÃO: Resetar query builder antes de cada consulta
        // para evitar acúmulo de condições WHERE
        
        // Total
        $this->db->reset_query();
        $this->db->where('applied_by', $user_id);
        $this->db->where('completed_at IS NOT NULL'); // Apenas formulários completados
        $counters['total'] = $this->db->count_all_results('form_responses');
        
        // Hoje - usando abordagem mais robusta
        $this->db->reset_query();
        $this->db->where('applied_by', $user_id);
        $this->db->where('completed_at IS NOT NULL');
        $this->db->where('completed_at >=', date('Y-m-d 00:00:00')); // Início do dia
        $this->db->where('completed_at <=', date('Y-m-d 23:59:59')); // Final do dia
        $counters['today'] = $this->db->count_all_results('form_responses');
        
        // Esta semana (últimos 7 dias)
        $this->db->reset_query();
        $this->db->where('applied_by', $user_id);
        $this->db->where('completed_at IS NOT NULL');
        $this->db->where('completed_at >=', date('Y-m-d 00:00:00', strtotime('-7 days')));
        $counters['week'] = $this->db->count_all_results('form_responses');
        
        // Por status de sincronização - Pendentes
        $this->db->reset_query();
        $this->db->where('applied_by', $user_id);
        $this->db->where('completed_at IS NOT NULL');
        $this->db->where('sync_status', 'pending');
        $counters['pending'] = $this->db->count_all_results('form_responses');
        
        // Sincronizados
        $this->db->reset_query();
        $this->db->where('applied_by', $user_id);
        $this->db->where('completed_at IS NOT NULL');
        $this->db->where('sync_status', 'synced');
        $counters['synced'] = $this->db->count_all_results('form_responses');
        
        // Com erro
        $this->db->reset_query();
        $this->db->where('applied_by', $user_id);
        $this->db->where('completed_at IS NOT NULL');
        $this->db->where('sync_status', 'error');
        $counters['error'] = $this->db->count_all_results('form_responses');
        
        // Debug log (remover em produção)
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'History counters for user ' . $user_id . ': ' . json_encode($counters));
        }
        
        return $counters;
    }

    /**
     * Obter resumo do histórico de aplicações
     * 
     * @param int $user_id ID do usuário
     * @return array Resumo do histórico
     */
    public function get_history_summary($user_id) {
        $summary = [];
        
        // Total de formulários aplicados
        $this->db->where('applied_by', $user_id);
        $summary['total_applications'] = $this->db->count_all_results('form_responses');
        
        // Formulários aplicados hoje
        $this->db->where('applied_by', $user_id);
        $this->db->where('DATE(completed_at)', date('Y-m-d'));
        $summary['today_applications'] = $this->db->count_all_results('form_responses');
        
        // Formulários aplicados esta semana
        $this->db->where('applied_by', $user_id);
        $this->db->where('completed_at >=', date('Y-m-d', strtotime('-7 days')));
        $summary['week_applications'] = $this->db->count_all_results('form_responses');
        
        // Formulários aplicados este mês
        $this->db->where('applied_by', $user_id);
        $this->db->where('completed_at >=', date('Y-m-d', strtotime('-30 days')));
        $summary['month_applications'] = $this->db->count_all_results('form_responses');
        
        // Questionários únicos aplicados
        $this->db->select('COUNT(DISTINCT questionnaire_id) as unique_questionnaires');
        $this->db->where('applied_by', $user_id);
        $result = $this->db->get('form_responses')->row();
        $summary['unique_questionnaires'] = $result ? $result->unique_questionnaires : 0;
        
        // Última aplicação
        $this->db->select('MAX(completed_at) as last_application');
        $this->db->where('applied_by', $user_id);
        $this->db->where('completed_at IS NOT NULL');
        $result = $this->db->get('form_responses')->row();
        $summary['last_application'] = $result ? $result->last_application : null;
        
        // Taxa de sucesso na sincronização
        $this->db->where('applied_by', $user_id);
        $this->db->where('sync_status', 'synced');
        $synced_count = $this->db->count_all_results('form_responses');
        
        $summary['sync_success_rate'] = $summary['total_applications'] > 0 
            ? round(($synced_count / $summary['total_applications']) * 100, 2)
            : 100;
        
        // Média de aplicações por dia (últimos 30 dias)
        $this->db->where('applied_by', $user_id);
        $this->db->where('completed_at >=', date('Y-m-d', strtotime('-30 days')));
        $last_30_days = $this->db->count_all_results('form_responses');
        $summary['avg_applications_per_day'] = round($last_30_days / 30, 2);
        
        // Total de fotos capturadas
        $this->db->where('applied_by', $user_id);
        $this->db->where('photo_path IS NOT NULL');
        $this->db->where('photo_path !=', '');
        $summary['total_photos'] = $this->db->count_all_results('form_responses');
        
        // Total de localizações capturadas
        $this->db->where('applied_by', $user_id);
        $this->db->where('latitude IS NOT NULL');
        $this->db->where('longitude IS NOT NULL');
        $summary['total_locations'] = $this->db->count_all_results('form_responses');
        
        return $summary;
    }

    /**
     * Obter aplicações recentes
     * 
     * @param int $user_id ID do usuário
     * @param int $limit Limite de resultados
     * @return array Lista de aplicações recentes
     */
    public function get_recent_applications($user_id, $limit = 5) {
        $this->db->select('
            fr.id,
            fr.questionnaire_id,
            fr.respondent_name,
            fr.location_name,
            fr.completed_at,
            fr.sync_status,
            q.title as questionnaire_title,
            CONCAT(\'#\', LPAD(q.id::text, 3, \'0\')) as questionnaire_code
        ');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->where('fr.applied_by', $user_id);
        $this->db->where('fr.completed_at IS NOT NULL');
        $this->db->order_by('fr.completed_at', 'DESC');
        $this->db->limit($limit);
        
        return $this->db->get()->result();
    }

    /**
     * Obter estatísticas de questionários por usuário
     * 
     * @param int $user_id ID do usuário
     * @return array Estatísticas por questionário
     */
    public function get_user_questionnaire_stats($user_id) {
        $this->db->select('
            q.id,
            q.title,
            CONCAT(\'#\', LPAD(q.id::text, 3, \'0\')) as code,
            COUNT(fr.id) as total_applications,
            MAX(fr.completed_at) as last_application,
            MIN(fr.completed_at) as first_application,
            SUM(CASE WHEN fr.sync_status = \'synced\' THEN 1 ELSE 0 END) as synced_count,
            SUM(CASE WHEN fr.sync_status = \'pending\' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN fr.sync_status = \'error\' THEN 1 ELSE 0 END) as error_count,
            SUM(CASE WHEN fr.photo_path IS NOT NULL AND fr.photo_path != \'\' THEN 1 ELSE 0 END) as photos_count,
            SUM(CASE WHEN fr.latitude IS NOT NULL AND fr.longitude IS NOT NULL THEN 1 ELSE 0 END) as locations_count,
            AVG(CASE 
                WHEN fr.started_at IS NOT NULL AND fr.completed_at IS NOT NULL 
                THEN EXTRACT(EPOCH FROM (fr.completed_at - fr.started_at))/60 
                ELSE NULL 
            END) as avg_duration_minutes
        ');
        $this->db->from('questionnaires q');
        $this->db->join('form_responses fr', 'q.id = fr.questionnaire_id AND fr.applied_by = ' . (int)$user_id, 'left');
        // Historico do aplicador: sem filtro de status (having > 0 ja limita aos aplicados)
        $this->db->group_by('q.id, q.title');
        $this->db->having('COUNT(fr.id) > 0');
        $this->db->order_by('total_applications', 'DESC');
        
        $results = $this->db->get()->result();
        
        // Processar resultados para incluir taxa de sucesso
        foreach ($results as &$stat) {
            $total = (int)$stat->total_applications;
            $synced = (int)$stat->synced_count;
            
            $stat->success_rate = $total > 0 ? round(($synced / $total) * 100, 2) : 0;
            $stat->avg_duration_minutes = $stat->avg_duration_minutes ? round($stat->avg_duration_minutes, 1) : null;
        }
        
        return $results;
    }

    /**
     * Obter detalhes de uma aplicação específica
     * 
     * @param int $response_id ID da resposta
     * @param int $user_id ID do usuário (para verificação de permissão)
     * @return object|null Detalhes da aplicação
     */
    public function get_application_details($response_id, $user_id = null) {
        $this->db->select('
            fr.*,
            q.title as questionnaire_title,
            CONCAT(\'#\', LPAD(q.id::text, 3, \'0\')) as questionnaire_code,
            q.description as questionnaire_description,
            u.full_name as applied_by_name
        ');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');
        $this->db->where('fr.id', $response_id);
        
        // Se especificado user_id, verificar permissão
        if ($user_id !== null) {
            $this->db->where('fr.applied_by', $user_id);
        }
        
        $application = $this->db->get()->row();
        
        if ($application) {
            // Buscar as respostas das perguntas
            $this->db->select('
                qr.question_id,
                qr.response_text,
                qr.response_number,
                qr.response_date,
                qr.response_datetime,
                qr.selected_options,
                q.question_text,
                q.question_type,
                q.order_index
            ');
            $this->db->from('question_responses qr');
            $this->db->join('questions q', 'qr.question_id = q.id', 'left');
            $this->db->where('qr.form_response_id', $response_id);
            $this->db->order_by('q.order_index', 'ASC');
            
            $answers = $this->db->get()->result();
            
            // Processar as respostas para formato unificado
            foreach ($answers as &$answer) {
                // Determinar o valor da resposta baseado no tipo
                switch ($answer->question_type) {
                    case 'number':
                        $answer->response_value = $answer->response_number;
                        break;
                    case 'date':
                        $answer->response_value = $answer->response_date;
                        break;
                    case 'datetime':
                        $answer->response_value = $answer->response_datetime;
                        break;
                    case 'checkbox':
                    case 'radio':
                        $answer->response_value = $answer->selected_options ? 
                            json_decode($answer->selected_options, true) : 
                            $answer->response_text;
                        break;
                    default:
                        $answer->response_value = $answer->response_text;
                        break;
                }
            }
            
            $application->answers = $answers;
        }
        
        return $application;
    }

    /**
     * Atualizar status de sincronização de uma aplicação
     * 
     * @param int $response_id ID da resposta
     * @param string $status Novo status (pending, synced, error)
     * @param string $sync_message Mensagem de sincronização (opcional)
     * @return bool Sucesso na atualização
     */
    public function update_sync_status($response_id, $status, $sync_message = null) {
        $data = [
            'sync_status' => $status
        ];
        
        // Note: Como não há campo updated_at na tabela form_responses, 
        // não incluímos na atualização
        
        $this->db->where('id', $response_id);
        return $this->db->update('form_responses', $data);
    }

    /**
     * Obter estatísticas de sincronização por período
     * 
     * @param int $user_id ID do usuário
     * @param int $days Número de dias para análise
     * @return array Estatísticas de sincronização
     */
    public function get_sync_stats($user_id, $days = 30) {
        $stats = [];
        
        // Estatísticas gerais
        $this->db->select('
            sync_status,
            COUNT(*) as count,
            MAX(completed_at) as last_occurrence
        ');
        $this->db->where('applied_by', $user_id);
        $this->db->where('completed_at >=', date('Y-m-d', strtotime("-{$days} days")));
        $this->db->group_by('sync_status');
        
        $results = $this->db->get('form_responses')->result();
        
        foreach ($results as $result) {
            $stats[$result->sync_status] = [
                'count' => (int)$result->count,
                'last_occurrence' => $result->last_occurrence
            ];
        }
        
        // Calcular porcentagens
        $total = array_sum(array_column($stats, 'count'));
        
        foreach ($stats as $status => &$data) {
            $data['percentage'] = $total > 0 ? round(($data['count'] / $total) * 100, 2) : 0;
        }
        
        // Estatísticas por dia
        $this->db->select('
            DATE(completed_at) as date,
            sync_status,
            COUNT(*) as count
        ');
        $this->db->where('applied_by', $user_id);
        $this->db->where('completed_at >=', date('Y-m-d', strtotime("-{$days} days")));
        $this->db->group_by('DATE(completed_at), sync_status');
        $this->db->order_by('date', 'ASC');
        
        $daily_stats = $this->db->get('form_responses')->result();
        
        $stats['daily_breakdown'] = $daily_stats;
        $stats['period_days'] = $days;
        $stats['total_applications'] = $total;
        
        return $stats;
    }

    /**
     * Buscar formulários pendentes de sincronização
     * 
     * @param int $user_id ID do usuário (opcional)
     * @param int $limit Limite de resultados
     * @return array Lista de formulários pendentes
     */
    public function get_pending_sync_forms($user_id = null, $limit = 50) {
        $this->db->select('
            fr.id,
            fr.questionnaire_id,
            fr.respondent_name,
            fr.location_name,
            fr.completed_at,
            fr.created_at,
            q.title as questionnaire_title
        ');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->where('fr.sync_status', 'pending');
        
        if ($user_id) {
            $this->db->where('fr.applied_by', $user_id);
        }
        
        $this->db->order_by('fr.created_at', 'ASC'); // Mais antigos primeiro
        $this->db->limit($limit);
        
        return $this->db->get()->result();
    }

    /**
     * Marcar múltiplos formulários como sincronizados
     * 
     * @param array $response_ids IDs dos formulários
     * @return bool Sucesso na operação
     */
    public function mark_as_synced($response_ids) {
        if (empty($response_ids)) {
            return false;
        }
        
        $this->db->where_in('id', $response_ids);
        return $this->db->update('form_responses', ['sync_status' => 'synced']);
    }

    /**
     * Obter estatísticas de desempenho de aplicação
     * 
     * @param int $user_id ID do usuário
     * @param int $days Período em dias
     * @return array Estatísticas de desempenho
     */
    public function get_performance_stats($user_id, $days = 30) {
        $stats = [];
        
        // Aplicações por dia da semana
        $this->db->select('
            EXTRACT(DOW FROM completed_at) as day_of_week,
            COUNT(*) as count
        ');
        $this->db->where('applied_by', $user_id);
        $this->db->where('completed_at >=', date('Y-m-d', strtotime("-{$days} days")));
        $this->db->where('completed_at IS NOT NULL');
        $this->db->group_by('EXTRACT(DOW FROM completed_at)');
        $this->db->order_by('day_of_week');
        
        $stats['by_day_of_week'] = $this->db->get('form_responses')->result();
        
        // Aplicações por hora do dia
        $this->db->select('
            EXTRACT(HOUR FROM completed_at) as hour_of_day,
            COUNT(*) as count
        ');
        $this->db->where('applied_by', $user_id);
        $this->db->where('completed_at >=', date('Y-m-d', strtotime("-{$days} days")));
        $this->db->where('completed_at IS NOT NULL');
        $this->db->group_by('EXTRACT(HOUR FROM completed_at)');
        $this->db->order_by('hour_of_day');
        
        $stats['by_hour_of_day'] = $this->db->get('form_responses')->result();
        
        // Duração média por questionário
        $this->db->select('
            q.title,
            q.id,
            COUNT(fr.id) as total_applications,
            AVG(CASE 
                WHEN fr.started_at IS NOT NULL AND fr.completed_at IS NOT NULL 
                THEN EXTRACT(EPOCH FROM (fr.completed_at - fr.started_at))/60 
                ELSE NULL 
            END) as avg_duration_minutes
        ');
        $this->db->from('questionnaires q');
        $this->db->join('form_responses fr', 'q.id = fr.questionnaire_id AND fr.applied_by = ' . (int)$user_id, 'inner');
        $this->db->where('fr.completed_at >=', date('Y-m-d', strtotime("-{$days} days")));
        $this->db->where('fr.started_at IS NOT NULL');
        $this->db->where('fr.completed_at IS NOT NULL');
        $this->db->group_by('q.id, q.title');
        $this->db->having('COUNT(fr.id) > 0');
        $this->db->order_by('total_applications', 'DESC');
        
        $duration_stats = $this->db->get()->result();
        
        // Processar duração média
        foreach ($duration_stats as &$stat) {
            $stat->avg_duration_minutes = $stat->avg_duration_minutes ? 
                round($stat->avg_duration_minutes, 1) : null;
        }
        
        $stats['duration_by_questionnaire'] = $duration_stats;
        
        return $stats;
    }

public function validate_questionnaire_ids($questionnaire_ids) {
    if (empty($questionnaire_ids) || !is_array($questionnaire_ids)) {
        return [];
    }
    
    // Filtrar apenas IDs válidos (números inteiros positivos)
    $valid_ids = array_filter(
        array_map('intval', $questionnaire_ids),
        function($id) { return $id > 0; }
    );
    
    if (empty($valid_ids)) {
        return [];
    }
    
    // Verificar se os questionários existem no banco
    // NAO filtra por status: relatorios precisam aceitar questionarios pausados/inativos
    // que ja possuem respostas coletadas.
    $this->db->select('id, title, status');
    $this->db->where_in('id', $valid_ids);
    $questionnaires = $this->db->get('questionnaires')->result();
    
    return array_column($questionnaires, 'id');
}

/**
 * NOVO: Obter questionários com contagem de localizações
 */
public function get_questionnaires_with_location_count($filters = []) {
    $this->db->select('
        q.id,
        q.title,
        q.description,
        q.status,
        q.created_at,
        u.full_name as created_by_name,
        COUNT(DISTINCT fr.id) as total_responses,
        COUNT(DISTINCT CASE 
            WHEN fr.latitude IS NOT NULL AND fr.longitude IS NOT NULL 
                AND fr.latitude != 0 AND fr.longitude != 0 
            THEN fr.id 
            ELSE NULL 
        END) as responses_with_location,
        COUNT(DISTINCT CASE 
            WHEN fr.photo_path IS NOT NULL AND fr.photo_path != \'\'
            THEN fr.id 
            ELSE NULL 
        END) as responses_with_photos,
        COUNT(DISTINCT fr.location_name) as unique_locations,
        COUNT(DISTINCT fr.applied_by) as unique_applicators,
        MIN(fr.completed_at) as first_response,
        MAX(fr.completed_at) as last_response
    ');
    
    $this->db->from('questionnaires q');
    $this->db->join('users u', 'q.created_by = u.id', 'left');
    $this->db->join('form_responses fr', 'q.id = fr.questionnaire_id AND fr.completed_at IS NOT NULL', 'left');
    
    // Aplicar filtros se fornecidos
    // Sem filtro explicito, NAO restringe por status: o mapa/analise historica
    // deve continuar mostrando questionarios pausados/inativos ja aplicados.
    if (isset($filters['status']) && !empty($filters['status'])) {
        $this->db->where('q.status', $filters['status']);
    }
    
    if (isset($filters['date_from']) && !empty($filters['date_from'])) {
        $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
    }
    
    if (isset($filters['date_to']) && !empty($filters['date_to'])) {
        $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
    }
    
    $this->db->group_by('q.id, q.title, q.description, q.status, q.created_at, u.full_name');
    $this->db->order_by('responses_with_location', 'DESC');
    
    $questionnaires = $this->db->get()->result();
    
    // Processar dados adicionais
    foreach ($questionnaires as &$questionnaire) {
        // Calcular taxa de localização
        $questionnaire->location_rate = $questionnaire->total_responses > 0 ? 
            round(($questionnaire->responses_with_location / $questionnaire->total_responses) * 100, 1) : 0;
        
        // Calcular taxa de fotos
        $questionnaire->photo_rate = $questionnaire->total_responses > 0 ? 
            round(($questionnaire->responses_with_photos / $questionnaire->total_responses) * 100, 1) : 0;
        
        // Formatar datas
        $questionnaire->first_response_formatted = $questionnaire->first_response ? 
            date('d/m/Y', strtotime($questionnaire->first_response)) : 'N/A';
        
        $questionnaire->last_response_formatted = $questionnaire->last_response ? 
            date('d/m/Y', strtotime($questionnaire->last_response)) : 'N/A';
        
        // Calcular adequação para KMZ
        $questionnaire->kmz_ready = ($questionnaire->responses_with_location > 0);
        $questionnaire->kmz_quality_score = $this->calculate_kmz_quality_score($questionnaire);
    }
    
    return $questionnaires;
}

/**
 * NOVO: Calcular score de qualidade para KMZ
 */
private function calculate_kmz_quality_score($questionnaire) {
    $score = 0;
    
    // Pontuação por número de localizações (máximo 40 pontos)
    if ($questionnaire->responses_with_location >= 100) {
        $score += 40;
    } elseif ($questionnaire->responses_with_location >= 50) {
        $score += 30;
    } elseif ($questionnaire->responses_with_location >= 20) {
        $score += 20;
    } elseif ($questionnaire->responses_with_location >= 5) {
        $score += 10;
    }
    
    // Pontuação por diversidade de locais (máximo 25 pontos)
    if ($questionnaire->unique_locations >= 20) {
        $score += 25;
    } elseif ($questionnaire->unique_locations >= 10) {
        $score += 20;
    } elseif ($questionnaire->unique_locations >= 5) {
        $score += 15;
    } elseif ($questionnaire->unique_locations >= 2) {
        $score += 10;
    }
    
    // Pontuação por taxa de localização (máximo 20 pontos)
    if ($questionnaire->location_rate >= 90) {
        $score += 20;
    } elseif ($questionnaire->location_rate >= 70) {
        $score += 15;
    } elseif ($questionnaire->location_rate >= 50) {
        $score += 10;
    } elseif ($questionnaire->location_rate >= 25) {
        $score += 5;
    }
    
    // Pontuação por presença de fotos (máximo 15 pontos)
    if ($questionnaire->photo_rate >= 80) {
        $score += 15;
    } elseif ($questionnaire->photo_rate >= 50) {
        $score += 10;
    } elseif ($questionnaire->photo_rate >= 25) {
        $score += 5;
    }
    
    return $score;
}

/**
 * NOVO: Obter questionários mais adequados para KMZ
 */
public function get_top_kmz_questionnaires($limit = 10) {
    $questionnaires = $this->get_questionnaires_with_location_count();
    
    // Filtrar apenas questionários com dados de localização
    $kmz_ready = array_filter($questionnaires, function($q) {
        return $q->kmz_ready && $q->responses_with_location > 0;
    });
    
    // Ordenar por score de qualidade
    usort($kmz_ready, function($a, $b) {
        return $b->kmz_quality_score - $a->kmz_quality_score;
    });
    
    return array_slice($kmz_ready, 0, $limit);
}

/**
 * NOVO: Obter detalhes de questionário para KMZ
 */
public function get_questionnaire_kmz_details($questionnaire_id) {
    // Buscar informações básicas do questionário
    $this->db->select('
        q.*,
        u.full_name as created_by_name,
        p.name as project_name
    ');
    $this->db->from('questionnaires q');
    $this->db->join('users u', 'q.created_by = u.id', 'left');
    $this->db->join('projects p', 'q.project_id = p.id', 'left');
    $this->db->where('q.id', $questionnaire_id);
    
    $questionnaire = $this->db->get()->row();
    
    if (!$questionnaire) {
        return null;
    }
    
    // Buscar estatísticas de localização
    $this->load->model('Response_model');
    $location_stats = $this->Response_model->get_location_stats_by_questionnaire($questionnaire_id);
    
    // Buscar distribuição geográfica
    $geographic_distribution = $this->Response_model->get_geographic_distribution([
        'questionnaire_ids' => [$questionnaire_id]
    ]);
    
    // Buscar histórico de aplicações por mês
    $this->db->select('
        DATE_TRUNC(\'month\', completed_at) as month,
        COUNT(*) as total_responses,
        COUNT(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 END) as responses_with_location
    ');
    $this->db->from('form_responses');
    $this->db->where('questionnaire_id', $questionnaire_id);
    $this->db->where('completed_at IS NOT NULL');
    $this->db->group_by('DATE_TRUNC(\'month\', completed_at)');
    $this->db->order_by('month', 'ASC');
    
    $monthly_stats = $this->db->get()->result();
    
    // Processar dados mensais
    foreach ($monthly_stats as &$stat) {
        $stat->month_formatted = date('m/Y', strtotime($stat->month));
        $stat->location_rate = $stat->total_responses > 0 ? 
            round(($stat->responses_with_location / $stat->total_responses) * 100, 1) : 0;
    }
    
    return [
        'questionnaire' => $questionnaire,
        'location_stats' => $location_stats,
        'geographic_distribution' => $geographic_distribution,
        'monthly_stats' => $monthly_stats,
        'kmz_quality_score' => $this->calculate_kmz_quality_score((object)[
            'responses_with_location' => $location_stats->with_location ?? 0,
            'unique_locations' => $location_stats->unique_locations ?? 0,
            'location_rate' => $location_stats->location_rate ?? 0,
            'photo_rate' => $location_stats->photo_rate ?? 0
        ])
    ];
}

/**
 * NOVO: Verificar se questionário tem dados suficientes para KMZ
 */
public function check_kmz_readiness($questionnaire_id) {
    $this->db->select('
        COUNT(*) as total_responses,
        COUNT(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL 
                  AND latitude != 0 AND longitude != 0 
             THEN 1 END) as responses_with_location,
        COUNT(DISTINCT location_name) as unique_locations
    ');
    $this->db->from('form_responses');
    $this->db->where('questionnaire_id', $questionnaire_id);
    $this->db->where('completed_at IS NOT NULL');
    
    $stats = $this->db->get()->row();
    
    if (!$stats) {
        return [
            'ready' => false,
            'reason' => 'Nenhuma resposta encontrada',
            'requirements' => [],
            'stats' => null
        ];
    }
    
    $requirements = [
        'has_responses' => $stats->total_responses > 0,
        'has_locations' => $stats->responses_with_location > 0,
        'min_locations' => $stats->responses_with_location >= 1,
        'location_diversity' => $stats->unique_locations >= 1
    ];
    
    $ready = array_reduce($requirements, function($carry, $req) {
        return $carry && $req;
    }, true);
    
    $reason = '';
    if (!$requirements['has_responses']) {
        $reason = 'Questionário não possui respostas';
    } elseif (!$requirements['has_locations']) {
        $reason = 'Nenhuma resposta possui dados de localização';
    } elseif (!$requirements['min_locations']) {
        $reason = 'Número insuficiente de localizações';
    }
    
    return [
        'ready' => $ready,
        'reason' => $reason,
        'requirements' => $requirements,
        'stats' => $stats,
        'recommendations' => $this->get_kmz_recommendations($stats)
    ];
}

/**
 * NOVO: Obter recomendações para melhorar dados de KMZ
 */
private function get_kmz_recommendations($stats) {
    $recommendations = [];
    
    if ($stats->responses_with_location == 0) {
        $recommendations[] = [
            'type' => 'critical',
            'message' => 'Ative a captura de localização nas configurações do questionário',
            'action' => 'edit_questionnaire_settings'
        ];
    } elseif ($stats->responses_with_location < 10) {
        $recommendations[] = [
            'type' => 'warning',
            'message' => 'Colete mais dados com localização para melhor visualização',
            'action' => 'continue_data_collection'
        ];
    }
    
    if ($stats->unique_locations <= 1) {
        $recommendations[] = [
            'type' => 'info',
            'message' => 'Diversifique os locais de coleta para melhor cobertura geográfica',
            'action' => 'expand_geographic_coverage'
        ];
    }
    
    $location_rate = $stats->total_responses > 0 ? 
        ($stats->responses_with_location / $stats->total_responses) * 100 : 0;
    
    if ($location_rate < 50) {
        $recommendations[] = [
            'type' => 'warning',
            'message' => 'Taxa de captura de localização baixa (' . round($location_rate, 1) . '%)',
            'action' => 'improve_location_capture'
        ];
    }
    
    return $recommendations;
}

/**
 * NOVO: Obter questionários por região geográfica
 */
public function get_questionnaires_by_region($filters = []) {
    $this->db->select('
        q.id,
        q.title,
        COUNT(DISTINCT fr.location_name) as unique_locations,
        COUNT(fr.id) as total_responses,
        STRING_AGG(DISTINCT fr.location_name, \', \' ORDER BY fr.location_name) as locations_list
    ');
    
    $this->db->from('questionnaires q');
    $this->db->join('form_responses fr', 'q.id = fr.questionnaire_id AND fr.completed_at IS NOT NULL', 'inner');

    // Analise historica por regiao: sem filtro de status (join inner ja limita aos aplicados)
    $this->db->where('fr.location_name IS NOT NULL');
    $this->db->where('fr.location_name !=', '');
    
    // Filtros opcionais
    if (isset($filters['location_pattern']) && !empty($filters['location_pattern'])) {
        $this->db->like('fr.location_name', $filters['location_pattern'], 'both');
    }
    
    if (isset($filters['min_locations']) && is_numeric($filters['min_locations'])) {
        $this->db->having('COUNT(DISTINCT fr.location_name) >=', (int)$filters['min_locations']);
    }
    
    $this->db->group_by('q.id, q.title');
    $this->db->order_by('unique_locations', 'DESC');
    
    if (isset($filters['limit']) && is_numeric($filters['limit'])) {
        $this->db->limit((int)$filters['limit']);
    }
    
    return $this->db->get()->result();
}
}
