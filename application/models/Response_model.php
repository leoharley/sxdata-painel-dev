<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Response_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    public function get_all() {
        $this->db->select('fr.*, q.title as questionnaire_title, u.full_name as applied_by_name');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');
        $this->db->order_by('fr.created_at', 'DESC');
        return $this->db->get()->result();
    }

    /**
     * @param int|null $limite as mais recentes primeiro. Sem limite, com
     *        centenas de milhares de entrevistas a tela de respostas travava
     *        (teste de carga da frente I).
     */
    public function get_filtered($filters = array(), $limite = NULL) {
        $this->db->select('fr.*, q.title as questionnaire_title, u.full_name as applied_by_name');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');


        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }

        if (isset($filters['applied_by']) && $filters['applied_by']) {
            $this->db->where('fr.applied_by', $filters['applied_by']);
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }

        if (isset($filters['sync_status']) && $filters['sync_status']) {
            $this->db->where('fr.sync_status', $filters['sync_status']);
        }

        $this->db->order_by('fr.created_at', 'DESC');
        if ($limite) {
            $this->db->limit((int) $limite);
        }
        return $this->db->get()->result();
    }

    public function get_by_id($id) {
        $this->db->select('fr.*, q.title as questionnaire_title, u.full_name as applied_by_name');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');
        $this->db->where('fr.id', $id);
        return $this->db->get()->row();
    }

    public function get_answers($form_response_id) {
        $this->db->select('qr.*, q.question_text, q.question_type');
        $this->db->from('question_responses qr');
        $this->db->join('questions q', 'qr.question_id = q.id', 'left');
        $this->db->where('qr.form_response_id', $form_response_id);
        $this->db->order_by('q.order_index', 'ASC');
        return $this->db->get()->result();
    }

    public function create($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        return $this->db->insert('form_responses', $data) ? $this->db->insert_id() : FALSE;
    }

    public function create_answer($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        return $this->db->insert('question_responses', $data) ? $this->db->insert_id() : FALSE;
    }

    public function update_sync_status($id, $status) {
        $this->db->where('id', $id);
        return $this->db->update('form_responses', array('sync_status' => $status));
    }

    public function count_all() {
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        return $this->db->count_all_results();
    }

    public function count_today() {
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->where('DATE(fr.created_at)', date('Y-m-d'));
        return $this->db->count_all_results();
    }

    public function count_pending_sync() {
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->where('fr.sync_status', 'pending');
        return $this->db->count_all_results();
    }

    public function get_recent($limit = 10) {
        $this->db->select('fr.*, q.title as questionnaire_title, u.full_name as applied_by_name');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');
        $this->db->order_by('fr.created_at', 'DESC');
        $this->db->limit($limit);
        return $this->db->get()->result();
    }

    public function get_responses_by_day($days = 30) {
        $this->db->select('DATE(fr.created_at) as date, COUNT(*) as count');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->where('fr.created_at >=', date('Y-m-d', strtotime("-{$days} days")));
        $this->db->group_by('DATE(fr.created_at)');
        $this->db->order_by('date', 'ASC');
        return $this->db->get()->result();
    }

    public function count_by_filters($filters = array()) {
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');


        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }

        if (isset($filters['applied_by']) && $filters['applied_by']) {
            $this->db->where('fr.applied_by', $filters['applied_by']);
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }

        if (isset($filters['sync_status']) && $filters['sync_status']) {
            $this->db->where('fr.sync_status', $filters['sync_status']);
        }

        return $this->db->count_all_results();
    }

    public function count_unique_respondents($filters = array()) {
        $this->db->select('COUNT(DISTINCT fr.respondent_email) as count');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');


        // Aplicar os mesmos filtros
        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }

        if (isset($filters['applied_by']) && $filters['applied_by']) {
            $this->db->where('fr.applied_by', $filters['applied_by']);
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }

        if (isset($filters['sync_status']) && $filters['sync_status']) {
            $this->db->where('fr.sync_status', $filters['sync_status']);
        }

        // Garantir que só conte emails não nulos/vazios
        $this->db->where('fr.respondent_email IS NOT NULL');
        $this->db->where("fr.respondent_email != ''");

        $result = $this->db->get()->row();
        return $result ? $result->count : 0;
    }

    public function get_with_location($filters = array()) {
        // Construir condições WHERE
        $where_conditions = array();
        $where_conditions[] = "fr.latitude IS NOT NULL";
        $where_conditions[] = "fr.longitude IS NOT NULL";
        $where_conditions[] = "fr.latitude != 0";
        $where_conditions[] = "fr.longitude != 0";
        $where_conditions[] = "fr.completed_at IS NOT NULL";
        
        // Parâmetros para prepared statement
        $params = array();
        
        // Suporte para múltiplos questionários
        if (isset($filters['questionnaire_ids']) && is_array($filters['questionnaire_ids']) && !empty($filters['questionnaire_ids'])) {
            $valid_ids = array_filter(
                array_map('intval', $filters['questionnaire_ids']),
                function($id) { return $id > 0; }
            );
            
            if (!empty($valid_ids)) {
                $placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
                $where_conditions[] = "fr.questionnaire_id IN ($placeholders)";
                $params = array_merge($params, $valid_ids);
            }
        } elseif (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $where_conditions[] = "fr.questionnaire_id = ?";
            $params[] = (int)$filters['questionnaire_id'];
        }
        
        if (isset($filters['applied_by']) && $filters['applied_by']) {
            $where_conditions[] = "fr.applied_by = ?";
            $params[] = (int)$filters['applied_by'];
        }
        
        if (isset($filters['date_from']) && $filters['date_from']) {
            $where_conditions[] = "DATE(fr.completed_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to']) && $filters['date_to']) {
            $where_conditions[] = "DATE(fr.completed_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (isset($filters['sync_status']) && $filters['sync_status']) {
            $where_conditions[] = "fr.sync_status = ?";
            $params[] = $filters['sync_status'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Query corrigida para PostgreSQL - tratando selected_options como JSON
        $sql = "
            SELECT 
                fr.id,
                fr.questionnaire_id,
                fr.respondent_name,
                fr.respondent_email,
                fr.applied_by,
                fr.latitude,
                fr.longitude,
                fr.location_name,
                fr.photo_path,
                fr.photo_path_2,
                fr.consent_given,
                fr.sync_status,
                fr.started_at,
                fr.completed_at,
                fr.created_at,
                q.title as questionnaire_title,
                q.description as questionnaire_description,
                u.full_name as applied_by_name,
                u.username as applied_by_username,
                CASE 
                    WHEN fr.started_at IS NOT NULL AND fr.completed_at IS NOT NULL 
                    THEN EXTRACT(EPOCH FROM (fr.completed_at - fr.started_at))/60 
                    ELSE NULL 
                END as duration_minutes,
                (
                    SELECT CASE
                        WHEN qr.response_text IS NOT NULL AND qr.response_text != '' 
                            THEN qr.response_text
                        WHEN qr.response_number IS NOT NULL 
                            THEN qr.response_number::text
                        WHEN qr.response_date IS NOT NULL 
                            THEN TO_CHAR(qr.response_date, 'DD/MM/YYYY')
                        WHEN qr.response_datetime IS NOT NULL 
                            THEN TO_CHAR(qr.response_datetime, 'DD/MM/YYYY HH24:MI')
                        WHEN qr.selected_options IS NOT NULL 
                            THEN qr.selected_options::text
                        ELSE NULL
                    END
                    FROM question_responses qr
                    JOIN questions quest ON qr.question_id = quest.id
                    WHERE qr.form_response_id = fr.id
                    ORDER BY quest.order_index ASC
                    LIMIT 1
                ) as indexador
            FROM form_responses fr
            LEFT JOIN questionnaires q ON fr.questionnaire_id = q.id
            LEFT JOIN users u ON fr.applied_by = u.id
            WHERE {$where_clause}
            ORDER BY fr.completed_at DESC
        ";
        
        try {
            if (!empty($params)) {
                $query = $this->db->query($sql, $params);
            } else {
                $query = $this->db->query($sql);
            }
            
            $result = $query->result();
            
            // Processar resultados
            foreach ($result as &$response) {
                // Formatações adicionais
                $response->duration_formatted = $this->format_duration($response->duration_minutes);
                $response->completed_at_formatted = date('d/m/Y H:i', strtotime($response->completed_at));
                $response->has_photo = !empty($response->photo_path);
                $response->coordinates_formatted = number_format($response->latitude, 6) . ', ' . number_format($response->longitude, 6);
                
                // Processar indexador se for JSON
                if ($response->indexador && $this->is_json($response->indexador)) {
                    $decoded = json_decode($response->indexador, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        if (is_array($decoded)) {
                            $response->indexador = implode(', ', $decoded);
                        }
                    }
                }
            }
            
            if (ENVIRONMENT === 'development') {
                log_message('debug', 'Query get_with_location executada com sucesso. Total: ' . count($result));
            }
            
            return $result;
            
        } catch (Exception $e) {
            log_message('error', 'Erro em get_with_location: ' . $e->getMessage());
            log_message('error', 'SQL: ' . $sql);
            return array();
        }
    }

    private function is_json($string) {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }


    public function count_photos($filters = array()) {
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');


        // Aplicar os mesmos filtros
        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }

        if (isset($filters['applied_by']) && $filters['applied_by']) {
            $this->db->where('fr.applied_by', $filters['applied_by']);
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }

        if (isset($filters['sync_status']) && $filters['sync_status']) {
            $this->db->where('fr.sync_status', $filters['sync_status']);
        }

        // Contar apenas respostas que têm fotos (photo_path não é nulo/vazio)
        $this->db->where('fr.photo_path IS NOT NULL');
        $this->db->where("fr.photo_path != ''");

        return $this->db->count_all_results();
    }

    public function count_locations($filters = array()) {
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');


        // Aplicar os mesmos filtros
        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }

        if (isset($filters['applied_by']) && $filters['applied_by']) {
            $this->db->where('fr.applied_by', $filters['applied_by']);
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }

        if (isset($filters['sync_status']) && $filters['sync_status']) {
            $this->db->where('fr.sync_status', $filters['sync_status']);
        }

        // Contar apenas respostas que têm localização (latitude e longitude não são nulas)
        $this->db->where('fr.latitude IS NOT NULL');
        $this->db->where('fr.longitude IS NOT NULL');

        return $this->db->count_all_results();
    }

    public function get_consent_rate($filters = array()) {
        // Primeiro, contar o total de respostas com os filtros
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');


        // Aplicar os mesmos filtros
        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }

        if (isset($filters['applied_by']) && $filters['applied_by']) {
            $this->db->where('fr.applied_by', $filters['applied_by']);
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }

        if (isset($filters['sync_status']) && $filters['sync_status']) {
            $this->db->where('fr.sync_status', $filters['sync_status']);
        }

        $total = $this->db->count_all_results();

        if ($total == 0) {
            return 0;
        }

        // Agora contar quantas deram consentimento
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');


        // Aplicar os mesmos filtros novamente
        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }

        if (isset($filters['applied_by']) && $filters['applied_by']) {
            $this->db->where('fr.applied_by', $filters['applied_by']);
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }

        if (isset($filters['sync_status']) && $filters['sync_status']) {
            $this->db->where('fr.sync_status', $filters['sync_status']);
        }

        // Filtrar apenas os que deram consentimento
        $this->db->where('fr.consent_given', TRUE);

        $with_consent = $this->db->count_all_results();

        // Calcular a taxa de consentimento em porcentagem
        return round(($with_consent / $total) * 100, 2);
    }

    public function get_responses_by_day_filtered($filters = array(), $days = 30) {
        $this->db->select('DATE(fr.created_at) as date, COUNT(*) as count');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');


        // Filtro base de período (últimos X dias)
        $this->db->where('fr.created_at >=', date('Y-m-d', strtotime("-{$days} days")));

        // Aplicar filtros adicionais
        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }

        if (isset($filters['applied_by']) && $filters['applied_by']) {
            $this->db->where('fr.applied_by', $filters['applied_by']);
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }

        if (isset($filters['sync_status']) && $filters['sync_status']) {
            $this->db->where('fr.sync_status', $filters['sync_status']);
        }

        $this->db->group_by('DATE(fr.created_at)');
        $this->db->order_by('date', 'ASC');

        return $this->db->get()->result();
    }

    // CORRIGIDO: Top aplicadores com estrutura garantida
    public function get_top_applicators($filters = array(), $limit = 10) {
        $this->db->select('
            u.id,
            u.full_name,
            u.username,
            COUNT(fr.id) as total_responses
        ');
        $this->db->from('form_responses fr');
        $this->db->join('users u', 'fr.applied_by = u.id', 'inner'); // INNER JOIN para garantir usuário válido
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');


        // Aplicar filtros
        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }

        if (isset($filters['applied_by']) && $filters['applied_by']) {
            $this->db->where('fr.applied_by', $filters['applied_by']);
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }

        if (isset($filters['sync_status']) && $filters['sync_status']) {
            $this->db->where('fr.sync_status', $filters['sync_status']);
        }

        // Garantir que há um aplicador válido e ativo
        $this->db->where('fr.applied_by IS NOT NULL');
        $this->db->where('u.full_name IS NOT NULL');
        $this->db->where("u.full_name != ''");
        $this->db->where('u.is_active', TRUE); // Apenas usuários ativos

        // Incluir todas as colunas não agregadas no GROUP BY
        $this->db->group_by('u.id, u.full_name, u.username');
        $this->db->having('COUNT(fr.id) > 0'); // Apenas aplicadores com respostas
        $this->db->order_by('total_responses', 'DESC');
        $this->db->limit($limit);

        $result = $this->db->get()->result();

        // Log para debug (remover em produção)
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'Top Applicators Query: ' . $this->db->last_query());
            log_message('debug', 'Top Applicators Result: ' . json_encode($result));
        }

        return $result;
    }

    // CORRIGIDO: Questionários por popularidade com estrutura garantida
    public function get_questionnaires_popularity($filters = array(), $limit = 10) {
        $this->db->select('
            q.id, 
            q.title, 
            COUNT(fr.id) as total_applications,
            COUNT(fr.id) as total_responses, -- Alias adicional para compatibilidade
            MAX(fr.completed_at) as last_application
        ');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'inner'); // INNER JOIN para garantir questionário válido
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');
        
        // Aplicar filtros
        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }
        
        if (isset($filters['applied_by']) && $filters['applied_by']) {
            $this->db->where('fr.applied_by', $filters['applied_by']);
        }
        
        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }
        
        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }
        
        if (isset($filters['sync_status']) && $filters['sync_status']) {
            $this->db->where('fr.sync_status', $filters['sync_status']);
        }
        
        // Garantir que há um questionário válido
        $this->db->where('fr.questionnaire_id IS NOT NULL');
        $this->db->where('q.title IS NOT NULL');
        $this->db->where("q.title != ''");
        
        // Incluir todas as colunas não agregadas no GROUP BY
        $this->db->group_by('q.id, q.title');
        $this->db->having('COUNT(fr.id) > 0'); // Apenas questionários com respostas
        $this->db->order_by('total_applications', 'DESC');
        $this->db->limit($limit);
        
        $result = $this->db->get()->result();
        
        // Log para debug (remover em produção)
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'Questionnaires Popularity Query: ' . $this->db->last_query());
            log_message('debug', 'Questionnaires Popularity Result: ' . json_encode($result));
        }
        
        return $result;
    }

    public function get_detailed_analysis($filters = array()) {
        // Em vez de retornar um array simples, vamos retornar dados por questionário
        $this->db->select("
            q.id as questionnaire_id,
            q.title as questionnaire_title,
            COUNT(fr.id) as total_responses,
            COUNT(CASE WHEN fr.photo_path IS NOT NULL AND fr.photo_path != '' THEN 1 END) as photos_count,
            COUNT(CASE WHEN fr.latitude IS NOT NULL AND fr.longitude IS NOT NULL THEN 1 END) as locations_count,
            AVG(CASE WHEN fr.completed_at IS NOT NULL AND fr.started_at IS NOT NULL 
                THEN EXTRACT(EPOCH FROM (fr.completed_at - fr.started_at))/60 END) as avg_time,
            COUNT(CASE WHEN fr.completed_at IS NOT NULL THEN 1 END) as completed_responses
        ");
        $this->db->from('questionnaires q');
        $this->db->join('form_responses fr', 'q.id = fr.questionnaire_id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');
        
        // Aplicar filtros
        
        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('q.id', $filters['questionnaire_id']);
        }
        
        if (isset($filters['applied_by']) && $filters['applied_by']) {
            $this->db->where('fr.applied_by', $filters['applied_by']);
        }
        
        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }
        
        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }
        
        if (isset($filters['sync_status']) && $filters['sync_status']) {
            $this->db->where('fr.sync_status', $filters['sync_status']);
        }
        
        $this->db->group_by('q.id, q.title');
        $this->db->having('COUNT(fr.id) >', 0); // Apenas questionários com respostas
        $this->db->order_by('COUNT(fr.id)', 'DESC');
        
        $questionnaire_analysis = $this->db->get()->result();
        
        // Calcular métricas adicionais para cada questionário
        foreach ($questionnaire_analysis as $analysis) {
            // Calcular média por dia (assumindo período de 30 dias se não especificado)
            $days_in_period = 30;
            if (isset($filters['date_from']) && isset($filters['date_to'])) {
                $date_from = new DateTime($filters['date_from']);
                $date_to = new DateTime($filters['date_to']);
                $days_in_period = max(1, $date_to->diff($date_from)->days);
            } elseif (isset($filters['period'])) {
                switch ($filters['period']) {
                    case 'last_7_days':
                        $days_in_period = 7;
                        break;
                    case 'last_30_days':
                        $days_in_period = 30;
                        break;
                    case 'last_3_months':
                        $days_in_period = 90;
                        break;
                }
            }
            
            $analysis->avg_per_day = round($analysis->total_responses / $days_in_period, 1);
            
            // Taxa de conclusão (assumindo que nem todas as respostas foram concluídas)
            $analysis->completion_rate = $analysis->total_responses > 0 
                ? round(($analysis->completed_responses / $analysis->total_responses) * 100, 1)
                : 0;
            
            // Formatar tempo médio
            $analysis->avg_time = $analysis->avg_time ? round($analysis->avg_time, 1) : 0;
        }
        
        return $questionnaire_analysis;
    }

    public function get_sync_status_stats() {
        $this->db->select('fr.sync_status, COUNT(*) as count');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->group_by('fr.sync_status');
        return $this->db->get()->result();
    }

    public function get_for_export($filters = array()) {
        // Implementar lógica de exportação com joins complexos
        $this->db->select('
            fr.id,
            q.title as questionnaire,
            u.full_name as aplicador,
            fr.respondent_name,
            fr.respondent_email,
            fr.latitude,
            fr.longitude,
            fr.location_name,
            fr.consent_given,
            fr.started_at,
            fr.completed_at,
            fr.sync_status,
            fr.photo_path,
            fr.photo_path_2
        ');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');
        
        // Aplicar filtros similares ao get_filtered
        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }
        
        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }
        
        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }
        
        $this->db->order_by('fr.completed_at', 'DESC');
        return $this->db->get()->result();
    }

    /**
     * Obter estatísticas de aplicadores para supervisores
     * CORRIGIDO: Usar aspas simples para PostgreSQL
     */
    public function get_applicators_stats($filters = array()) {
        $this->db->select("
            u.id,
            u.full_name,
            u.username,
            u.is_active,
            COUNT(fr.id) as total_forms,
            COUNT(CASE WHEN DATE(fr.completed_at) = CURRENT_DATE THEN 1 END) as today_forms,
            COUNT(CASE WHEN fr.photo_path IS NOT NULL AND fr.photo_path != '' THEN 1 END) as photos_captured,
            COUNT(CASE WHEN fr.latitude IS NOT NULL AND fr.longitude IS NOT NULL THEN 1 END) as locations_captured,
            MAX(fr.completed_at) as last_activity,
            COUNT(DISTINCT DATE(fr.completed_at)) as active_days
        ");
        $this->db->from('users u');
        $this->db->join('form_responses fr', 'u.id = fr.applied_by', 'left');
        $this->db->where('u.role', 'aplicador');
        
        // Aplicar filtros
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $this->db->where('u.is_active', TRUE);
            } elseif ($filters['status'] === 'inactive') {
                $this->db->where('u.is_active', FALSE);
            }
        }
        
        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }
        
        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }
        
        // Incluir todas as colunas não agregadas no GROUP BY
        $this->db->group_by('u.id, u.full_name, u.username, u.is_active');
        $this->db->order_by('total_forms', 'DESC');
        
        return $this->db->get()->result();
    }

    /**
     * Obter estatísticas por localização/região
     */
    public function get_location_stats($filters = array()) {
        $this->db->select('
            fr.location_name,
            COUNT(fr.id) as total_forms,
            COUNT(DISTINCT fr.applied_by) as unique_applicators,
            AVG(fr.latitude) as avg_latitude,
            AVG(fr.longitude) as avg_longitude
        ');
        $this->db->from('form_responses fr');
        $this->db->where('fr.location_name IS NOT NULL');
        $this->db->where("fr.location_name != ''");
        
        // Aplicar filtros de data
        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }
        
        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }
        
        $this->db->group_by('fr.location_name');
        $this->db->order_by('total_forms', 'DESC');
        $this->db->limit(20); // Top 20 localizações
        
        return $this->db->get()->result();
    }

    /**
     * Obter resumo para supervisores
     */
    public function get_supervisor_summary($filters = array()) {
        $summary = array();
        
        // Total de aplicadores
        $this->db->where('role', 'aplicador');
        $summary['total_applicators'] = $this->db->count_all_results('users');
        
        // Aplicadores ativos
        $this->db->where('role', 'aplicador');
        $this->db->where('is_active', TRUE);
        $summary['active_applicators'] = $this->db->count_all_results('users');
        
        // Total de formulários no período
        $this->db->from('form_responses fr');
        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }
        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }
        $summary['total_forms'] = $this->db->count_all_results();
        
        // Formulários hoje
        $summary['today_forms'] = $this->count_by_filters(array(
            'date_from' => date('Y-m-d'),
            'date_to' => date('Y-m-d')
        ));
        
        // Regiões cobertas
        $this->db->select('COUNT(DISTINCT location_name) as covered_regions');
        $this->db->from('form_responses');
        $this->db->where('location_name IS NOT NULL');
        $this->db->where("location_name != ''");
        $regions_result = $this->db->get()->row();
        $summary['covered_regions'] = $regions_result ? $regions_result->covered_regions : 0;
        
        // Taxa de produtividade
        $summary['productivity_rate'] = $summary['active_applicators'] > 0 
            ? round($summary['total_forms'] / $summary['active_applicators'], 1) 
            : 0;
        
        return $summary;
    }

    /**
     * Obter dados para o mapa de aplicadores
     */
    public function get_map_locations($filters = array()) {
        $this->db->select('
            fr.latitude,
            fr.longitude,
            fr.location_name,
            fr.applied_by,
            u.full_name as applicator_name,
            COUNT(fr.id) as forms_count,
            MAX(fr.completed_at) as last_activity
        ');
        $this->db->from('form_responses fr');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');
        $this->db->where('fr.latitude IS NOT NULL');
        $this->db->where('fr.longitude IS NOT NULL');
        $this->db->where('fr.latitude !=', 0);
        $this->db->where('fr.longitude !=', 0);
        
        // Aplicar filtros
        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }
        
        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }
        
        if (isset($filters['applicator_id']) && $filters['applicator_id']) {
            $this->db->where('fr.applied_by', $filters['applicator_id']);
        }
        
        // Incluir todas as colunas não agregadas no GROUP BY para PostgreSQL
        $this->db->group_by('fr.latitude, fr.longitude, fr.location_name, fr.applied_by, u.full_name');
        $this->db->order_by('forms_count', 'DESC');
        
        return $this->db->get()->result();
    }

    /**
     * Obter estatísticas de cobertura territorial
     * CORRIGIDO: Compatibilidade com PostgreSQL
     */
    public function get_coverage_stats($filters = array()) {
        // Número total de pontos únicos de coleta
        $this->db->select("COUNT(DISTINCT CONCAT(latitude::text, ',', longitude::text)) as unique_points");
        $this->db->from('form_responses');
        $this->db->where('latitude IS NOT NULL');
        $this->db->where('longitude IS NOT NULL');
        $this->db->where('latitude !=', 0);
        $this->db->where('longitude !=', 0);
        
        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(completed_at) >=', $filters['date_from']);
        }
        
        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(completed_at) <=', $filters['date_to']);
        }
        
        $unique_points_result = $this->db->get()->row();
        $unique_points = $unique_points_result ? $unique_points_result->unique_points : 0;
        
        // Novas áreas este mês usando EXTRACT para PostgreSQL
        $this->db->select('COUNT(DISTINCT location_name) as new_areas');
        $this->db->from('form_responses');
        $this->db->where('EXTRACT(YEAR FROM completed_at) =', date('Y'));
        $this->db->where('EXTRACT(MONTH FROM completed_at) =', date('n'));
        $this->db->where('location_name IS NOT NULL');
        $this->db->where("location_name != ''");
        $new_areas_result = $this->db->get()->row();
        $new_areas = $new_areas_result ? $new_areas_result->new_areas : 0;
        
        // Zonas de alta densidade usando subconsulta compatível com PostgreSQL
        $high_density_query = "
            SELECT COUNT(*) as high_density_zones
            FROM (
                SELECT location_name, COUNT(*) as forms_count 
                FROM form_responses 
                WHERE location_name IS NOT NULL AND location_name != ''
                GROUP BY location_name 
                HAVING COUNT(*) > 10
            ) as density_zones
        ";
        
        $high_density_result = $this->db->query($high_density_query)->row();
        $high_density = $high_density_result ? $high_density_result->high_density_zones : 0;
        
        return array(
            'unique_collection_points' => (int)$unique_points,
            'coverage_percentage' => min(100, ($unique_points * 2)), // Cálculo simplificado
            'new_areas_this_month' => (int)$new_areas,
            'high_density_zones' => (int)$high_density,
            'total_area_covered' => $unique_points . ' pontos únicos'
        );
    }

    /**
     * Obter localização primária de um aplicador
     */
    public function get_applicator_primary_location($applicator_id) {
        $this->db->select('fr.location_name, COUNT(*) as count');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->where('fr.applied_by', $applicator_id);
        $this->db->where('fr.location_name IS NOT NULL');
        $this->db->where("fr.location_name != ''");
        $this->db->group_by('fr.location_name');
        $this->db->order_by('count', 'DESC');
        $this->db->limit(1);

        $result = $this->db->get()->row();
        return $result ? $result->location_name : 'Não definida';
    }

    /**
     * Obter dias ativos de um usuário
     */
    public function get_active_days($user_id, $days = 30) {
        $this->db->select('COUNT(DISTINCT DATE(fr.completed_at)) as active_days');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->where('fr.applied_by', $user_id);
        $this->db->where('fr.completed_at IS NOT NULL');
        $this->db->where('fr.completed_at >=', date('Y-m-d', strtotime("-{$days} days")));

        $result = $this->db->get()->row();
        return $result ? $result->active_days : 0;
    }

    /**
     * Obter atividade recente de um usuário
     */
    public function get_recent_activity($user_id, $limit = 10) {
        $this->db->select('
            fr.id,
            fr.completed_at,
            fr.sync_status,
            q.title as questionnaire_title,
            fr.respondent_name
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
     * Obter estatísticas de um período específico
     */
    public function get_period_stats($user_id, $days) {
        $filters = array(
            'applied_by' => $user_id,
            'date_from' => date('Y-m-d', strtotime("-{$days} days")),
            'date_to' => date('Y-m-d')
        );

        $total = $this->count_by_filters($filters);
        $photos = $this->count_photos($filters);
        $locations = $this->count_locations($filters);
        $avg_per_day = $days > 0 ? round($total / $days, 1) : 0;

        return array(
            'total_forms' => (int)$total,
            'photos_captured' => (int)$photos,
            'locations_captured' => (int)$locations,
            'avg_per_day' => (float)$avg_per_day,
            'period_days' => (int)$days
        );
    }

    /**
     * Obter questionários mais aplicados por um usuário
     */
    public function get_top_questionnaires_by_user($user_id, $limit = 5) {
        $this->db->select('
            q.id,
            q.title,
            COUNT(fr.id) as total_applications,
            MAX(fr.completed_at) as last_application
        ');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->where('fr.applied_by', $user_id);
        $this->db->where('fr.completed_at IS NOT NULL');

        // Incluir todas as colunas não agregadas no GROUP BY
        $this->db->group_by('q.id, q.title');
        $this->db->order_by('total_applications', 'DESC');
        $this->db->limit($limit);

        return $this->db->get()->result();
    }

    /**
     * Verificar se usuário é supervisor ou administrador
     */
    public function is_supervisor_or_admin($user_id) {
        $this->db->select('role');
        $this->db->where('id', $user_id);
        $user = $this->db->get('users')->row();
        
        if (!$user) {
            return false;
        }
        
        // Lista completa de roles que têm permissões administrativas
        $admin_roles = array(
            'supervisor', 
            'administrador', 
            'admin', 
            'administrator',
            'gestor',
            'manager'
        );
        
        return in_array(strtolower($user->role), $admin_roles);
    }

    /**
     * Obter informações do usuário
     */
    public function get_user_info($user_id) {
        $this->db->select('id, username, full_name, role, is_active');
        $this->db->where('id', $user_id);
        return $this->db->get('users')->row();
    }

    /**
     * Obter dados agregados para administradores
     * Este método retorna estatísticas de todo o sistema
     */
    public function get_admin_aggregated_stats() {
        // Total de formulários de todos os aplicadores
        $total_forms = $this->count_all();
        
        // Formulários hoje
        $today_forms = $this->count_today();
        
        // Pendentes de sincronização
        $pending_sync = $this->count_pending_sync();
        
        // Fotos capturadas (total)
        $photos_captured = $this->count_photos([]);
        
        // Taxa de sucesso geral
        $synced_forms = $this->count_by_filters(['sync_status' => 'synced']);
        $success_rate = $total_forms > 0 ? round(($synced_forms / $total_forms) * 100) : 100;
        
        return [
            'total_forms' => (int)$total_forms,
            'today_forms' => (int)$today_forms,
            'pending_sync' => (int)$pending_sync,
            'photos_captured' => (int)$photos_captured,
            'success_rate' => (int)$success_rate
        ];
    }

    /**
     * Contar total de aplicadores
     */
    public function count_applicators($status = 'all') {
        $this->db->where('role', 'aplicador');
        
        if ($status === 'active') {
            $this->db->where('is_active', TRUE);
        } elseif ($status === 'inactive') {
            $this->db->where('is_active', FALSE);
        }
        
        return $this->db->count_all_results('users');
    }

/**
 * Analisar respostas de texto
 */
private function analyze_text_responses($question_id, $filters, $total_responses) {
    $this->db->select('
        COUNT(*) as total_text_responses,
        COUNT(CASE WHEN response_text IS NOT NULL AND response_text != \'\' THEN 1 END) as filled_responses,
        AVG(LENGTH(response_text)) as avg_length
    ');
    $this->db->from('question_responses qr');
    $this->db->join('form_responses fr', 'qr.form_response_id = fr.id', 'inner');
    $this->db->join('questions q', 'qr.question_id = q.id', 'inner');
    $this->db->join('questionnaires quest', 'q.questionnaire_id = quest.id', 'inner');
    $this->db->where('qr.question_id', $question_id);

    // Aplicar filtros
    if (isset($filters['date_from']) && $filters['date_from']) {
        $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
    }
    if (isset($filters['date_to']) && $filters['date_to']) {
        $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
    }
    if (isset($filters['applied_by']) && $filters['applied_by']) {
        $this->db->where('fr.applied_by', $filters['applied_by']);
    }

    $result = $this->db->get()->row();
    
    $filled = $result ? $result->filled_responses : 0;
    $empty = $total_responses - $filled;
    
    return array(
        array(
            'label' => 'Respostas Preenchidas',
            'count' => (int)$filled,
            'percentage' => round(($filled / $total_responses) * 100, 1)
        ),
        array(
            'label' => 'Respostas Vazias',
            'count' => (int)$empty,
            'percentage' => round(($empty / $total_responses) * 100, 1)
        ),
        array(
            'label' => 'Comprimento Médio',
            'count' => $result ? round($result->avg_length, 0) : 0,
            'percentage' => null,
            'unit' => 'caracteres'
        )
    );
}

public function get_specific_questionnaire_analysis($questionnaire_id, $filters = array()) {
    // Primeiro, obter informações do questionário
    $this->db->select('
        q.id,
        q.title,
        q.description,
        q.created_at,
        q.requires_consent,
        q.requires_location,
        q.requires_photo,
        q.estimated_time,
        u.full_name as created_by_name
    ');
    $this->db->from('questionnaires q');
    $this->db->join('users u', 'q.created_by = u.id', 'left');
    $this->db->where('q.id', $questionnaire_id);
    $questionnaire = $this->db->get()->row();
    
    if (!$questionnaire) {
        return array('error' => 'Questionário não encontrado');
    }
    
    // Obter todas as questões do questionário
    $this->db->select('
        q.id,
        q.question_text,
        q.question_type,
        q.is_required,
        q.order_index
    ');
    $this->db->from('questions q');
    $this->db->where('q.questionnaire_id', $questionnaire_id);
    $this->db->order_by('q.order_index');
    $questions = $this->db->get()->result_array();
    
    // Para cada questão, obter suas opções (se aplicável)
    foreach ($questions as &$question) {
        if (in_array($question['question_type'], ['radio', 'checkbox'])) {
            $this->db->select('id, option_text, option_value, order_index');
            $this->db->from('question_options');
            $this->db->where('question_id', $question['id']);
            $this->db->order_by('order_index');
            $question['options'] = $this->db->get()->result_array();
        } else {
            $question['options'] = array();
        }
        
        // Obter estatísticas da questão
        $question['statistics'] = $this->get_question_statistics($question['id'], $filters);
    }
    
    // Calcular resumo geral do questionário
    $summary = $this->get_questionnaire_summary($questionnaire_id, $filters);
    
    return array(
        'questionnaire' => $questionnaire,
        'questions' => $questions,
        'summary' => $summary
    );
}

/**
 * MÉTODO QUE FALTAVA: Obter resumo estatístico de um questionário
 */
public function get_questionnaire_summary($questionnaire_id, $filters = array()) {
    // Total de respostas do questionário
    $this->db->select('COUNT(*) as total_responses');
    $this->db->from('form_responses fr');
    $this->db->where('fr.questionnaire_id', $questionnaire_id);
    $this->db->where('fr.completed_at IS NOT NULL');
    
    // Aplicar filtros de data
    if (isset($filters['date_from']) && $filters['date_from']) {
        $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
    }
    if (isset($filters['date_to']) && $filters['date_to']) {
        $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
    }
    if (isset($filters['applied_by']) && $filters['applied_by']) {
        $this->db->where('fr.applied_by', $filters['applied_by']);
    }
    
    $total_result = $this->db->get()->row();
    $total_responses = $total_result ? $total_result->total_responses : 0;
    
    // Respondentes únicos
    $this->db->select('COUNT(DISTINCT fr.respondent_email) as unique_respondents');
    $this->db->from('form_responses fr');
    $this->db->where('fr.questionnaire_id', $questionnaire_id);
    $this->db->where('fr.completed_at IS NOT NULL');
    $this->db->where('fr.respondent_email IS NOT NULL');
    $this->db->where("fr.respondent_email != ''");
    
    // Aplicar mesmos filtros
    if (isset($filters['date_from']) && $filters['date_from']) {
        $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
    }
    if (isset($filters['date_to']) && $filters['date_to']) {
        $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
    }
    if (isset($filters['applied_by']) && $filters['applied_by']) {
        $this->db->where('fr.applied_by', $filters['applied_by']);
    }
    
    $unique_result = $this->db->get()->row();
    $unique_respondents = $unique_result ? $unique_result->unique_respondents : 0;
    
    // Tempo médio de conclusão (compatível com PostgreSQL)
    $sql = "SELECT AVG(EXTRACT(EPOCH FROM (completed_at - started_at))/60) as avg_time
            FROM form_responses 
            WHERE questionnaire_id = ? 
            AND completed_at IS NOT NULL 
            AND started_at IS NOT NULL";
    
    $params = array($questionnaire_id);
    
    if (isset($filters['date_from']) && $filters['date_from']) {
        $sql .= " AND DATE(completed_at) >= ?";
        $params[] = $filters['date_from'];
    }
    if (isset($filters['date_to']) && $filters['date_to']) {
        $sql .= " AND DATE(completed_at) <= ?";
        $params[] = $filters['date_to'];
    }
    if (isset($filters['applied_by']) && $filters['applied_by']) {
        $sql .= " AND applied_by = ?";
        $params[] = $filters['applied_by'];
    }
    
    $time_result = $this->db->query($sql, $params)->row();
    $avg_time = $time_result && $time_result->avg_time ? round($time_result->avg_time, 1) : 0;
    
    // Última resposta
    $this->db->select('MAX(completed_at) as last_response');
    $this->db->from('form_responses fr');
    $this->db->where('fr.questionnaire_id', $questionnaire_id);
    $this->db->where('fr.completed_at IS NOT NULL');
    
    // Aplicar filtros
    if (isset($filters['date_from']) && $filters['date_from']) {
        $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
    }
    if (isset($filters['date_to']) && $filters['date_to']) {
        $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
    }
    if (isset($filters['applied_by']) && $filters['applied_by']) {
        $this->db->where('fr.applied_by', $filters['applied_by']);
    }
    
    $last_result = $this->db->get()->row();
    $last_response = $last_result && $last_result->last_response ? 
        date('d/m/Y H:i', strtotime($last_result->last_response)) : null;
    
    // Contagem de questões
    $this->db->select('COUNT(*) as total_questions');
    $this->db->from('questions');
    $this->db->where('questionnaire_id', $questionnaire_id);
    $questions_result = $this->db->get()->row();
    $total_questions = $questions_result ? $questions_result->total_questions : 0;
    
    // Taxa de conclusão média (simplificada)
    $avg_completion_rate = 0;
    if ($total_questions > 0 && $total_responses > 0) {
        // Calcular taxa baseada na média de respostas por questão
        $this->db->select('AVG(question_responses_count) as avg_responses');
        $this->db->from('(
            SELECT COUNT(qr.id) as question_responses_count
            FROM questions q
            LEFT JOIN question_responses qr ON q.id = qr.question_id
            LEFT JOIN form_responses fr ON qr.form_response_id = fr.id
            WHERE q.questionnaire_id = ' . $questionnaire_id . '
            ' . (isset($filters['date_from']) && $filters['date_from'] ? 
                'AND DATE(fr.completed_at) >= \'' . $filters['date_from'] . '\'' : '') . '
            ' . (isset($filters['date_to']) && $filters['date_to'] ? 
                'AND DATE(fr.completed_at) <= \'' . $filters['date_to'] . '\'' : '') . '
            ' . (isset($filters['applied_by']) && $filters['applied_by'] ? 
                'AND fr.applied_by = ' . $filters['applied_by'] : '') . '
            GROUP BY q.id
        ) as question_stats', false);
        
        $rate_result = $this->db->get()->row();
        $avg_completion_rate = $rate_result && $rate_result->avg_responses ? 
            round(($rate_result->avg_responses / $total_responses) * 100, 1) : 0;
    }
    
    return array(
        'total_questions' => (int)$total_questions,
        'total_responses' => (int)$total_responses,
        'unique_respondents' => (int)$unique_respondents,
        'avg_time' => (float)$avg_time,
        'last_response' => $last_response,
        'avg_completion_rate' => (float)$avg_completion_rate
    );
}

/**
 * MÉTODO QUE FALTAVA: Obter análise detalhada de respostas por questão
 */
public function get_question_analysis($filters = array()) {
    // Primeiro, obter todas as questões dos questionários filtrados
    $this->db->select('
        q.id as question_id,
        q.question_text,
        q.question_type,
        q.questionnaire_id,
        quest.title as questionnaire_title,
        q.order_index
    ');
    $this->db->from('questions q');
    $this->db->join('questionnaires quest', 'q.questionnaire_id = quest.id', 'inner');
    
    // Se houver filtro por questionário específico
    if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
        $this->db->where('q.questionnaire_id', $filters['questionnaire_id']);
    }
    
    // Ativos, ou pausados/inativos que já têm respostas: pausar tira o
    // questionário do app, não da análise
    $this->db->where("(quest.status = 'active' OR EXISTS (SELECT 1 FROM form_responses frx WHERE frx.questionnaire_id = quest.id))", NULL, FALSE);
    $this->db->order_by('quest.title, q.order_index');
    
    $questions = $this->db->get()->result();
   
    $analysis_data = array();
    
    foreach ($questions as $question) {
        $question_stats = $this->get_question_statistics($question->question_id, $filters);
        
        $analysis_data[] = array(
            'question_id' => $question->question_id,
            'questionnaire_id' => $question->questionnaire_id,
            'questionnaire_title' => $question->questionnaire_title,
            'question_text' => $question->question_text,
            'question_type' => $question->question_type,
            'order_index' => $question->order_index,
            'statistics' => $question_stats
        );
    }
    
    return $analysis_data;
}

/**
 * MÉTODO QUE FALTAVA: Obter estatísticas específicas de uma questão
 */
public function get_question_statistics($question_id, $filters = array()) {
    // Obter informações da questão
    $this->db->select('question_type');
    $this->db->where('id', $question_id);
    $question = $this->db->get('questions')->row();

    if (!$question) {
        return array();
    }

    // Contar total de respostas para esta questão
    $this->db->select('COUNT(qr.id) as total_responses');
    $this->db->from('question_responses qr');
    $this->db->join('form_responses fr', 'qr.form_response_id = fr.id', 'inner');
    $this->db->join('questions q', 'qr.question_id = q.id', 'inner');
    $this->db->join('questionnaires quest', 'q.questionnaire_id = quest.id', 'inner');
    $this->db->where('qr.question_id', $question_id);

    // Aplicar filtros de data se fornecidos
    if (isset($filters['date_from']) && $filters['date_from']) {
        $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
    }
    if (isset($filters['date_to']) && $filters['date_to']) {
        $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
    }
    if (isset($filters['applied_by']) && $filters['applied_by']) {
        $this->db->where('fr.applied_by', $filters['applied_by']);
    }

    $total_result = $this->db->get()->row();
    $total_responses = $total_result ? $total_result->total_responses : 0;
    
    $statistics = array(
        'total_responses' => (int)$total_responses,
        'response_rate' => 0,
        'data' => array()
    );
    
    if ($total_responses == 0) {
        return $statistics;
    }
    
    // Análise baseada no tipo de questão
    switch ($question->question_type) {
        case 'radio':
            $statistics['data'] = $this->analyze_option_responses($question_id, $filters, $total_responses);
            break;
        case 'checkbox':
            $statistics['data'] = $this->analyze_option_responses($question_id, $filters, $total_responses);
            break;
            
        case 'text':
        case 'textarea':
            $statistics['data'] = $this->analyze_text_responses($question_id, $filters, $total_responses);
            break;
            
        case 'number':
            $statistics['data'] = $this->analyze_number_responses($question_id, $filters, $total_responses);
            break;
            
        case 'date':
        case 'datetime':
            $statistics['data'] = $this->analyze_date_responses($question_id, $filters, $total_responses);
            break;
            
        default:
            $statistics['data'] = $this->analyze_generic_responses($question_id, $filters, $total_responses);
    }
    
    return $statistics;
}

/**
 * MÉTODO CORRIGIDO: Analisar respostas de questões com opções (radio, checkbox)
 */
private function analyze_option_responses($question_id, $filters, $total_responses) {
    // Primeiro, determinar o tipo da questão
    $this->db->select('question_type');
    $this->db->where('id', $question_id);
    $question = $this->db->get('questions')->row();
    
    if (!$question) {
        return array();
    }
    
    // Obter opções da questão
    $this->db->select('id, option_text, option_value, order_index');
    $this->db->where('question_id', $question_id);
    $this->db->order_by('order_index');
    $options = $this->db->get('question_options')->result();
    
    // Obter todas as respostas para análise
    $this->db->select('qr.selected_options');
    $this->db->from('question_responses qr');
    $this->db->join('form_responses fr', 'qr.form_response_id = fr.id', 'inner');
    $this->db->join('questions q', 'qr.question_id = q.id', 'inner');
    $this->db->join('questionnaires quest', 'q.questionnaire_id = quest.id', 'inner');
    $this->db->where('qr.question_id', $question_id);
    $this->db->where('qr.selected_options IS NOT NULL');

    // Aplicar filtros
    if (isset($filters['date_from']) && $filters['date_from']) {
        $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
    }
    if (isset($filters['date_to']) && $filters['date_to']) {
        $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
    }
    if (isset($filters['applied_by']) && $filters['applied_by']) {
        $this->db->where('fr.applied_by', $filters['applied_by']);
    }

    $responses = $this->db->get()->result();
    
    $analysis = array();
    
    foreach ($options as $option) {
        $count = 0;
        
        // Contar respostas para esta opção
        foreach ($responses as $response) {
            if (!empty($response->selected_options)) {
                // Tentar decodificar JSON
                $selected = json_decode($response->selected_options, true);
                
                if (is_array($selected)) {
                    // Verificar se a opção está selecionada (case-insensitive)
                    foreach ($selected as $selected_value) {
                        if ($this->compareOptionValues($selected_value, $option->option_value)) {
                            $count++;
                            break; // Evitar contagem dupla se a mesma opção aparece múltiplas vezes
                        }
                    }
                } else {
                    // Fallback: buscar como string se não for JSON válido
                    if ($this->compareOptionValues($response->selected_options, $option->option_value)) {
                        $count++;
                    }
                }
            }
        }
        
        $percentage = $total_responses > 0 ? round(($count / $total_responses) * 100, 1) : 0;
        
        $analysis[] = array(
            'option_id' => $option->id,
            'option_text' => $option->option_text,
            'option_value' => $option->option_value,
            'count' => (int)$count,
            'percentage' => (float)$percentage,
            'order_index' => $option->order_index
        );
    }
    
    return $analysis;
}

private function compareOptionValues($response_value, $option_value) {
    // Limpar e normalizar os valores
    $response_clean = trim(strtolower($response_value));
    $option_clean = trim(strtolower($option_value));
    
    // Comparação direta
    if ($response_clean === $option_clean) {
        return true;
    }
    
    // Remover caracteres especiais e acentos para comparação mais flexível
    $response_normalized = $this->normalizeString($response_clean);
    $option_normalized = $this->normalizeString($option_clean);
    
    if ($response_normalized === $option_normalized) {
        return true;
    }
    
    // Verificar variações comuns
    $common_variations = array(
        'sim' => array('sim', 'yes', 's', 'verdadeiro', 'true', '1'),
        'não' => array('não', 'nao', 'no', 'n', 'falso', 'false', '0'),
        'yes' => array('sim', 'yes', 's', 'verdadeiro', 'true', '1'),
        'no' => array('não', 'nao', 'no', 'n', 'falso', 'false', '0')
    );
    
    foreach ($common_variations as $key => $variations) {
        if (in_array($response_normalized, $variations) && in_array($option_normalized, $variations)) {
            return true;
        }
    }
    
    return false;
}

private function normalizeString($string) {
    // Converter para minúsculas
    $string = strtolower($string);
    
    // Remover acentos
    $string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
    
    // Remover caracteres especiais exceto letras e números
    $string = preg_replace('/[^a-z0-9]/', '', $string);
    
    return $string;
}

/**
 * Contar respostas de checkbox para uma opção específica
 */
private function count_checkbox_option($question_id, $option_value, $filters) {
    // Obter todas as respostas para a questão
    $this->db->select('qr.selected_options');
    $this->db->from('question_responses qr');
    $this->db->join('form_responses fr', 'qr.form_response_id = fr.id', 'inner');
    $this->db->join('questions q', 'qr.question_id = q.id', 'inner');
    $this->db->join('questionnaires quest', 'q.questionnaire_id = quest.id', 'inner');
    $this->db->where('qr.question_id', $question_id);
    $this->db->where('qr.selected_options IS NOT NULL');

    // Aplicar filtros
    if (isset($filters['date_from']) && $filters['date_from']) {
        $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
    }
    if (isset($filters['date_to']) && $filters['date_to']) {
        $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
    }
    if (isset($filters['applied_by']) && $filters['applied_by']) {
        $this->db->where('fr.applied_by', $filters['applied_by']);
    }

    $responses = $this->db->get()->result();
    
    $count = 0;
    foreach ($responses as $response) {
        if (!empty($response->selected_options)) {
            // Tentar decodificar JSON
            $selected = json_decode($response->selected_options, true);
            
            // Verificar se a decodificação foi bem-sucedida e se é um array
            if (is_array($selected)) {
                foreach ($selected as $selected_value) {
                    if ($this->compareOptionValues($selected_value, $option_value)) {
                        $count++;
                        break; // Evitar contagem dupla
                    }
                }
            } elseif (is_string($response->selected_options)) {
                // Fallback: buscar como string se não for JSON válido
                if ($this->compareOptionValues($response->selected_options, $option_value)) {
                    $count++;
                }
            }
        }
    }
    
    return $count;
}

/**
 * Analisar respostas numéricas
 */
private function analyze_number_responses($question_id, $filters, $total_responses) {
    $this->db->select('
        COUNT(CASE WHEN response_number IS NOT NULL THEN 1 END) as filled_responses,
        MIN(response_number) as min_value,
        MAX(response_number) as max_value,
        AVG(response_number) as avg_value
    ');
    $this->db->from('question_responses qr');
    $this->db->join('form_responses fr', 'qr.form_response_id = fr.id', 'inner');
    $this->db->join('questions q', 'qr.question_id = q.id', 'inner');
    $this->db->join('questionnaires quest', 'q.questionnaire_id = quest.id', 'inner');
    $this->db->where('qr.question_id', $question_id);

    // Aplicar filtros
    if (isset($filters['date_from']) && $filters['date_from']) {
        $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
    }
    if (isset($filters['date_to']) && $filters['date_to']) {
        $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
    }
    if (isset($filters['applied_by']) && $filters['applied_by']) {
        $this->db->where('fr.applied_by', $filters['applied_by']);
    }

    $result = $this->db->get()->row();
    
    $filled = $result ? $result->filled_responses : 0;
    $empty = $total_responses - $filled;
    
    return array(
        array(
            'label' => 'Respostas Preenchidas',
            'count' => (int)$filled,
            'percentage' => round(($filled / $total_responses) * 100, 1)
        ),
        array(
            'label' => 'Respostas Vazias',
            'count' => (int)$empty,
            'percentage' => round(($empty / $total_responses) * 100, 1)
        ),
        array(
            'label' => 'Valor Mínimo',
            'count' => $result ? $result->min_value : 0,
            'percentage' => null
        ),
        array(
            'label' => 'Valor Máximo',
            'count' => $result ? $result->max_value : 0,
            'percentage' => null
        ),
        array(
            'label' => 'Valor Médio',
            'count' => $result ? round($result->avg_value, 2) : 0,
            'percentage' => null
        )
    );
}

/**
 * Analisar respostas de data
 */
private function analyze_date_responses($question_id, $filters, $total_responses) {
    $this->db->select('
        COUNT(CASE WHEN response_date IS NOT NULL OR response_datetime IS NOT NULL THEN 1 END) as filled_responses,
        MIN(COALESCE(response_date, DATE(response_datetime))) as earliest_date,
        MAX(COALESCE(response_date, DATE(response_datetime))) as latest_date
    ');
    $this->db->from('question_responses qr');
    $this->db->join('form_responses fr', 'qr.form_response_id = fr.id', 'inner');
    $this->db->join('questions q', 'qr.question_id = q.id', 'inner');
    $this->db->join('questionnaires quest', 'q.questionnaire_id = quest.id', 'inner');
    $this->db->where('qr.question_id', $question_id);

    // Aplicar filtros
    if (isset($filters['date_from']) && $filters['date_from']) {
        $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
    }
    if (isset($filters['date_to']) && $filters['date_to']) {
        $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
    }
    if (isset($filters['applied_by']) && $filters['applied_by']) {
        $this->db->where('fr.applied_by', $filters['applied_by']);
    }

    $result = $this->db->get()->row();
    
    $filled = $result ? $result->filled_responses : 0;
    $empty = $total_responses - $filled;
    
    return array(
        array(
            'label' => 'Respostas Preenchidas',
            'count' => (int)$filled,
            'percentage' => round(($filled / $total_responses) * 100, 1)
        ),
        array(
            'label' => 'Respostas Vazias',
            'count' => (int)$empty,
            'percentage' => round(($empty / $total_responses) * 100, 1)
        ),
        array(
            'label' => 'Data Mais Antiga',
            'count' => $result && $result->earliest_date ? $result->earliest_date : 'N/A',
            'percentage' => null,
            'is_date' => true
        ),
        array(
            'label' => 'Data Mais Recente',
            'count' => $result && $result->latest_date ? $result->latest_date : 'N/A',
            'percentage' => null,
            'is_date' => true
        )
    );
}

/**
 * Analisar respostas genéricas (fallback)
 */
private function analyze_generic_responses($question_id, $filters, $total_responses) {
    $this->db->select('
        COUNT(CASE WHEN response_text IS NOT NULL AND response_text != \'\' THEN 1 END) as filled_responses
    ');
    $this->db->from('question_responses qr');
    $this->db->join('form_responses fr', 'qr.form_response_id = fr.id', 'inner');
    $this->db->join('questions q', 'qr.question_id = q.id', 'inner');
    $this->db->join('questionnaires quest', 'q.questionnaire_id = quest.id', 'inner');
    $this->db->where('qr.question_id', $question_id);

    // Aplicar filtros
    if (isset($filters['date_from']) && $filters['date_from']) {
        $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
    }
    if (isset($filters['date_to']) && $filters['date_to']) {
        $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
    }
    if (isset($filters['applied_by']) && $filters['applied_by']) {
        $this->db->where('fr.applied_by', $filters['applied_by']);
    }

    $result = $this->db->get()->row();
    
    $filled = $result ? $result->filled_responses : 0;
    $empty = $total_responses - $filled;
    
    return array(
        array(
            'label' => 'Respostas Preenchidas',
            'count' => (int)$filled,
            'percentage' => round(($filled / $total_responses) * 100, 1)
        ),
        array(
            'label' => 'Respostas Vazias',
            'count' => (int)$empty,
            'percentage' => round(($empty / $total_responses) * 100, 1)
        )
    );
}

/**
 * Obter top 5 questões com mais respostas
 */
public function get_top_answered_questions($filters = array(), $limit = 5) {
    $this->db->select('
        q.id,
        q.question_text,
        q.question_type,
        quest.title as questionnaire_title,
        COUNT(qr.id) as total_responses
    ');
    $this->db->from('questions q');
    $this->db->join('questionnaires quest', 'q.questionnaire_id = quest.id', 'inner');
    $this->db->join('question_responses qr', 'q.id = qr.question_id', 'inner');
    $this->db->join('form_responses fr', 'qr.form_response_id = fr.id', 'inner');
    
    // Aplicar filtros
    if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
        $this->db->where('q.questionnaire_id', $filters['questionnaire_id']);
    }
    if (isset($filters['date_from']) && $filters['date_from']) {
        $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
    }
    if (isset($filters['date_to']) && $filters['date_to']) {
        $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
    }
    if (isset($filters['applied_by']) && $filters['applied_by']) {
        $this->db->where('fr.applied_by', $filters['applied_by']);
    }
    
    $this->db->group_by('q.id, q.question_text, q.question_type, quest.title');
    $this->db->order_by('total_responses', 'DESC');
    $this->db->limit($limit);
    
    return $this->db->get()->result();
}

public function log_export_activity($user_id, $export_type, $filters, $record_count, $status = 'success', $error_message = null) {
    $log_data = array(
        'user_id' => $user_id,
        'action' => 'export_raw_data',
        'resource_type' => 'responses',
        'details' => json_encode(array(
            'export_type' => $export_type,
            'filters' => $filters,
            'record_count' => $record_count,
            'status' => $status,
            'error_message' => $error_message,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        )),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'created_at' => date('Y-m-d H:i:s')
    );
    
    // Inserir no log de atividades
    $this->db->insert('activity_logs', $log_data);
    
    // Log também no arquivo do sistema
    $log_message = "Export Raw Data - User: {$user_id}, Type: {$export_type}, Records: {$record_count}, Status: {$status}";
    if ($error_message) {
        $log_message .= ", Error: {$error_message}";
    }
    
    log_message($status === 'success' ? 'info' : 'error', $log_message);
}

/**
 * Obter histórico de exportações do usuário
 */
public function get_user_export_history($user_id, $limit = 10) {
    $this->db->select('
        al.*,
        u.full_name,
        u.username
    ');
    $this->db->from('activity_logs al');
    $this->db->join('users u', 'al.user_id = u.id', 'left');
    $this->db->where('al.user_id', $user_id);
    $this->db->where('al.action', 'export_raw_data');
    $this->db->order_by('al.created_at', 'DESC');
    $this->db->limit($limit);
    
    $results = $this->db->get()->result();
    
    // Decodificar details JSON
    foreach ($results as &$result) {
        if ($result->details) {
            $result->details = json_decode($result->details, true);
        }
    }
    
    return $results;
}

/**
 * Verificar limites de exportação por usuário (prevenção de abuso)
 */
public function check_export_limits($user_id) {
    // Verificar quantas exportações nas últimas 24 horas
    $this->db->where('user_id', $user_id);
    $this->db->where('action', 'export_raw_data');
    $this->db->where('created_at >=', date('Y-m-d H:i:s', strtotime('-24 hours')));
    $count_24h = $this->db->count_all_results('activity_logs');
    
    // Verificar quantas exportações na última hora
    $this->db->where('user_id', $user_id);
    $this->db->where('action', 'export_raw_data');
    $this->db->where('created_at >=', date('Y-m-d H:i:s', strtotime('-1 hour')));
    $count_1h = $this->db->count_all_results('activity_logs');
    
    $limits = array(
        'max_per_hour' => 5,      // Máximo 5 exportações por hora
        'max_per_day' => 20,      // Máximo 20 exportações por dia
        'current_hour' => $count_1h,
        'current_day' => $count_24h,
        'hour_exceeded' => $count_1h >= 5,
        'day_exceeded' => $count_24h >= 20
    );
    
    return $limits;
}

/**
 * Obter estatísticas de uso do sistema de exportação
 */
public function get_export_usage_statistics($days = 30) {
    // Exportações por dia
    $this->db->select('
        DATE(created_at) as date,
        COUNT(*) as export_count,
        COUNT(DISTINCT user_id) as unique_users
    ');
    $this->db->from('activity_logs');
    $this->db->where('action', 'export_raw_data');
    $this->db->where('created_at >=', date('Y-m-d', strtotime("-{$days} days")));
    $this->db->group_by('DATE(created_at)');
    $this->db->order_by('date', 'ASC');
    $daily_stats = $this->db->get()->result();
    
    // Usuários mais ativos
    $this->db->select('
        u.full_name,
        u.username,
        COUNT(al.id) as export_count
    ');
    $this->db->from('activity_logs al');
    $this->db->join('users u', 'al.user_id = u.id', 'left');
    $this->db->where('al.action', 'export_raw_data');
    $this->db->where('al.created_at >=', date('Y-m-d', strtotime("-{$days} days")));
    $this->db->group_by('u.id, u.full_name, u.username');
    $this->db->order_by('export_count', 'DESC');
    $this->db->limit(10);
    $top_users = $this->db->get()->result();
    
    // Tipos de exportação mais utilizados
    $this->db->select('
        JSON_UNQUOTE(JSON_EXTRACT(details, "$.export_type")) as export_type,
        COUNT(*) as count
    ');
    $this->db->from('activity_logs');
    $this->db->where('action', 'export_raw_data');
    $this->db->where('created_at >=', date('Y-m-d', strtotime("-{$days} days")));
    $this->db->group_by('export_type');
    $this->db->order_by('count', 'DESC');
    $export_types = $this->db->get()->result();
    
    return array(
        'daily_statistics' => $daily_stats,
        'top_users' => $top_users,
        'export_types' => $export_types,
        'period_days' => $days
    );
}

public function validate_export_filters($filters) {
        $errors = array();
        
        // Validar questionário se especificado
        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id'] && $filters['questionnaire_id'] !== 'all') {
            $this->db->where('id', $filters['questionnaire_id']);
            $questionnaire = $this->db->get('questionnaires')->row();
            if (!$questionnaire) {
                $errors[] = 'Questionário especificado não foi encontrado.';
            }
        }
        
        // Validar datas
        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            if ($filters['date_from'] && $filters['date_to']) {
                if (strtotime($filters['date_from']) > strtotime($filters['date_to'])) {
                    $errors[] = 'Data de início deve ser anterior à data de fim.';
                }
            }
        }
        
        // Validar se existe pelo menos uma resposta com os filtros
        $count = $this->count_by_filters($filters);
        if ($count === 0) {
            $errors[] = 'Nenhuma resposta encontrada com os filtros especificados.';
        }
        
        return $errors;
    }

    /**
     * Obter preview dos dados que serão exportados
     */
    public function get_export_preview($filters = array(), $limit = 5) {
        $this->db->select('
            fr.id,
            fr.respondent_name,
            fr.completed_at,
            q.title as questionnaire_title,
            u.full_name as applied_by_name
        ');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');
        
        // Aplicar filtros
        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }
        
        if (isset($filters['applied_by']) && $filters['applied_by']) {
            $this->db->where('fr.applied_by', $filters['applied_by']);
        }
        
        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }
        
        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }
        
        $this->db->where('fr.completed_at IS NOT NULL');
        $this->db->order_by('fr.completed_at', 'DESC');
        $this->db->limit($limit);
        
        return $this->db->get()->result();
    }

    public function get_raw_data_for_export($filters = array()) {
        // Query base com todos os dados necessários
        $this->db->select('
            fr.id,
            fr.questionnaire_id,
            fr.respondent_name,
            fr.respondent_email,
            fr.applied_by,
            fr.latitude,
            fr.longitude,
            fr.location_name,
            fr.photo_path,
            fr.photo_path_2,
            fr.consent_given,
            CASE
                WHEN fr.sync_status = \'synced\' THEN \'SINCRONIZADO\'
                ELSE \'PENDENTE\'
            END as sync_status,
            fr.started_at,
            fr.completed_at,
            fr.created_at,
            q.title as questionnaire_title,
            u.full_name as applied_by_name,
            u.username as applied_by_username
        ');
        $this->db->from('form_responses fr');
        $this->db->join('questionnaires q', 'fr.questionnaire_id = q.id', 'left');
        $this->db->join('users u', 'fr.applied_by = u.id', 'left');


        // Aplicar filtros
        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }

        if (isset($filters['applied_by']) && $filters['applied_by']) {
            $this->db->where('fr.applied_by', $filters['applied_by']);
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }

        if (isset($filters['sync_status']) && $filters['sync_status']) {
            $this->db->where('fr.sync_status', $filters['sync_status']);
        }

        // Apenas respostas concluídas
        $this->db->where('fr.completed_at IS NOT NULL');
        
        $this->db->order_by('fr.completed_at', 'DESC');
        $responses = $this->db->get()->result();
        
        // Para cada resposta, buscar todas as respostas das questões
        foreach ($responses as &$response) {
            $response->answers_json = $this->_get_response_answers_json($response->id);
            
            // Adicionar campos extras que podem estar nas respostas individuais
            $individual_data = $this->_extract_individual_response_data($response->id);
            $response = (object) array_merge((array) $response, $individual_data);
        }
        
        return $responses;
    }
    
    /**
     * Obter todas as respostas de uma form_response em formato JSON estruturado
     */
    private function _get_response_answers_json($form_response_id) {
        $this->db->select('
            qr.id,
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
        $this->db->where('qr.form_response_id', $form_response_id);
        $this->db->order_by('q.order_index', 'ASC');
        
        $answers = $this->db->get()->result();
        
        $structured_answers = array();
        
        foreach ($answers as $answer) {
            // Determinar o valor da resposta baseado no tipo
            $response_value = null;
            
            switch ($answer->question_type) {
                case 'text':
                case 'textarea':
                    $response_value = $answer->response_text;
                    break;
                    
                case 'number':
                    $response_value = $answer->response_number;
                    break;
                    
                case 'date':
                    $response_value = $answer->response_date;
                    break;
                    
                case 'datetime':
                    $response_value = $answer->response_datetime;
                    break;
                    
                case 'radio':
                case 'checkbox':
                    $response_value = $answer->selected_options;
                    // Tentar decodificar JSON se possível
                    if (!empty($answer->selected_options)) {
                        $decoded = json_decode($answer->selected_options, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $response_value = $decoded;
                        }
                    }
                    break;
                    
                default:
                    $response_value = $answer->response_text ?: $answer->selected_options;
            }
            
            $structured_answers[] = array(
                'question_id' => $answer->question_id,
                'question_text' => $answer->question_text,
                'question_type' => $answer->question_type,
                'order_index' => $answer->order_index,
                'response_value' => $response_value
            );
        }
        
        return json_encode($structured_answers, JSON_UNESCAPED_UNICODE);
    }

    private function _extract_individual_response_data($form_response_id) {
        $additional_data = array();
        
        // Buscar algumas respostas específicas que podem conter dados importantes
        // como CPF, idade, sexo, etc. baseado no padrão do modelo
        $this->db->select('
            qr.response_text,
            qr.response_number,
            qr.selected_options,
            q.question_text,
            q.question_type
        ');
        $this->db->from('question_responses qr');
        $this->db->join('questions q', 'qr.question_id = q.id', 'left');
        $this->db->where('qr.form_response_id', $form_response_id);
        
        $responses = $this->db->get()->result();
        
        foreach ($responses as $resp) {
            $question_lower = strtolower($resp->question_text);
            
            // Mapear campos comuns baseado no texto da pergunta
            if (strpos($question_lower, 'cpf') !== false) {
                $additional_data['respondent_cpf'] = $resp->response_text;
            } elseif (strpos($question_lower, 'idade') !== false) {
                $additional_data['respondent_age'] = $resp->response_number ?: $resp->response_text;
            } elseif (strpos($question_lower, 'sexo') !== false || strpos($question_lower, 'gênero') !== false) {
                $additional_data['respondent_gender'] = $resp->response_text ?: $resp->selected_options;
            } elseif (strpos($question_lower, 'comunidade') !== false || strpos($question_lower, 'localidade') !== false) {
                $additional_data['respondent_community'] = $resp->response_text;
            }
        }
        
        return $additional_data;
    }

    public function get_export_statistics($filters = array()) {
        // Total de respostas
        $total = $this->count_by_filters($filters);
        
        // Respostas com fotos
        $with_photos = $this->count_photos($filters);
        
        // Respostas com localização
        $with_location = $this->count_locations($filters);
        
        // Respostas com consentimento
        $this->db->from('form_responses fr');
        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }
        if (isset($filters['applied_by']) && $filters['applied_by']) {
            $this->db->where('fr.applied_by', $filters['applied_by']);
        }
        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }
        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }
        $this->db->where('fr.consent_given', TRUE);
        $with_consent = $this->db->count_all_results();
        
        // Aplicadores únicos
        $this->db->select('COUNT(DISTINCT fr.applied_by) as unique_applicators');
        $this->db->from('form_responses fr');
        if (isset($filters['questionnaire_id']) && $filters['questionnaire_id']) {
            $this->db->where('fr.questionnaire_id', $filters['questionnaire_id']);
        }
        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('DATE(fr.completed_at) >=', $filters['date_from']);
        }
        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('DATE(fr.completed_at) <=', $filters['date_to']);
        }
        $unique_result = $this->db->get()->row();
        $unique_applicators = $unique_result ? $unique_result->unique_applicators : 0;
        
        return array(
            'total_responses' => (int)$total,
            'with_photos' => (int)$with_photos,
            'with_location' => (int)$with_location,
            'with_consent' => (int)$with_consent,
            'unique_applicators' => (int)$unique_applicators,
            'consent_rate' => $total > 0 ? round(($with_consent / $total) * 100, 1) : 0,
            'photo_rate' => $total > 0 ? round(($with_photos / $total) * 100, 1) : 0,
            'location_rate' => $total > 0 ? round(($with_location / $total) * 100, 1) : 0
        );
    }
    
/**
 * MÉTODO DE APOIO: Validar formato de data
 */
private function validate_date($date) {
    if (empty($date)) {
        return false;
    }
    
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * MÉTODO DE APOIO: Sanitizar coordenadas
 */
private function sanitize_coordinates($lat, $lng) {
    $lat = floatval($lat);
    $lng = floatval($lng);
    
    // Validar limites geográficos
    if ($lat < -90 || $lat > 90) {
        return array('valid' => false, 'error' => 'Latitude inválida');
    }
    
    if ($lng < -180 || $lng > 180) {
        return array('valid' => false, 'error' => 'Longitude inválida');
    }
    
    // Verificar se não são coordenadas nulas/padrão
    if ($lat == 0 && $lng == 0) {
        return array('valid' => false, 'error' => 'Coordenadas não podem ser 0,0');
    }
    
    return array(
        'valid' => true,
        'lat' => $lat,
        'lng' => $lng
    );
}

/**
 * MÉTODO DE APOIO: Construir cláusula WHERE dinamicamente
 */
private function build_where_clause($conditions, $params) {
    if (empty($conditions)) {
        return array('clause' => '1=1', 'params' => array());
    }
    
    return array(
        'clause' => implode(' AND ', $conditions),
        'params' => $params
    );
}

/**
 * MÉTODO DE APOIO: Calcular distância entre dois pontos (em km)
 */
private function calculate_distance($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371; // Raio da Terra em km
    
    $lat1_rad = deg2rad($lat1);
    $lng1_rad = deg2rad($lng1);
    $lat2_rad = deg2rad($lat2);
    $lng2_rad = deg2rad($lng2);
    
    $delta_lat = $lat2_rad - $lat1_rad;
    $delta_lng = $lng2_rad - $lng1_rad;
    
    $a = sin($delta_lat / 2) * sin($delta_lat / 2) +
         cos($lat1_rad) * cos($lat2_rad) *
         sin($delta_lng / 2) * sin($delta_lng / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earth_radius * $c;
}

/**
 * MÉTODO ADICIONAL: Obter estatísticas de cobertura geográfica
 */
public function get_coverage_statistics($filters = array()) {
    $validation = $this->validate_filter_params($filters);
    if (!$validation['is_valid']) {
        log_message('error', 'Parâmetros inválidos em get_coverage_statistics: ' . implode(', ', $validation['errors']));
        return array('error' => 'Parâmetros inválidos: ' . implode(', ', $validation['errors']));
    }
    
    $filters = $validation['validated'];
    
    // Query corrigida para PostgreSQL
    $where_conditions = array();
    $where_conditions[] = "fr.latitude IS NOT NULL";
    $where_conditions[] = "fr.longitude IS NOT NULL";
    $where_conditions[] = "fr.latitude != 0";
    $where_conditions[] = "fr.longitude != 0";
    $where_conditions[] = "fr.completed_at IS NOT NULL";
    
    $params = array();
    
    // Aplicar filtros validados
    if (isset($filters['questionnaire_ids'])) {
        $placeholders = implode(',', array_fill(0, count($filters['questionnaire_ids']), '?'));
        $where_conditions[] = "fr.questionnaire_id IN ($placeholders)";
        $params = array_merge($params, $filters['questionnaire_ids']);
    }
    
    if (isset($filters['date_from'])) {
        $where_conditions[] = "DATE(fr.completed_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (isset($filters['date_to'])) {
        $where_conditions[] = "DATE(fr.completed_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Query simplificada sem CTEs complexas para melhor compatibilidade
    $sql = "
        SELECT 
            COUNT(DISTINCT CONCAT(fr.latitude::text, ',', fr.longitude::text)) as total_unique_points,
            COUNT(DISTINCT CASE WHEN fr.location_name IS NOT NULL AND fr.location_name != '' 
                               THEN fr.location_name END) as unique_named_locations,
            COUNT(*) as total_responses,
            MIN(fr.latitude) as south_bound,
            MAX(fr.latitude) as north_bound,
            MIN(fr.longitude) as west_bound,
            MAX(fr.longitude) as east_bound,
            MIN(fr.completed_at) as earliest_data,
            MAX(fr.completed_at) as latest_data
        FROM form_responses fr
        WHERE {$where_clause}
    ";
    
    try {
        if (!empty($params)) {
            $result = $this->db->query($sql, $params)->row();
        } else {
            $result = $this->db->query($sql)->row();
        }
        
        if ($result && $result->total_unique_points > 0) {
            // Calcular métricas derivadas
            $avg_responses_per_point = $result->total_unique_points > 0 ? 
                round($result->total_responses / $result->total_unique_points, 1) : 0;
            
            $lat_span = $result->north_bound - $result->south_bound;
            $lng_span = $result->east_bound - $result->west_bound;
            
            // Calcular área aproximada (em km²)
            $lat_km = $lat_span * 111; // 1 grau de latitude ≈ 111 km
            $avg_lat = ($result->north_bound + $result->south_bound) / 2;
            $lng_km = $lng_span * 111 * cos(deg2rad($avg_lat));
            $approximate_area = abs($lat_km * $lng_km);
            
            // Calcular período de coleta
            $collection_days = 0;
            if ($result->earliest_data && $result->latest_data) {
                $start = new DateTime($result->earliest_data);
                $end = new DateTime($result->latest_data);
                $collection_days = $end->diff($start)->days;
            }
            
            // Criar objeto de estatísticas para avaliação
            $stats_obj = (object) array(
                'total_unique_points' => $result->total_unique_points,
                'unique_named_locations' => $result->unique_named_locations,
                'avg_responses_per_point' => $avg_responses_per_point
            );
            
            return array(
                'total_unique_points' => (int)$result->total_unique_points,
                'unique_named_locations' => (int)$result->unique_named_locations,
                'total_responses' => (int)$result->total_responses,
                'avg_responses_per_point' => $avg_responses_per_point,
                'geographic_bounds' => array(
                    'north' => (float)$result->north_bound,
                    'south' => (float)$result->south_bound,
                    'east' => (float)$result->east_bound,
                    'west' => (float)$result->west_bound
                ),
                'geographic_span' => array(
                    'latitude_degrees' => round($lat_span, 4),
                    'longitude_degrees' => round($lng_span, 4),
                    'approximate_area_km2' => round($approximate_area, 1)
                ),
                'temporal_span' => array(
                    'earliest_data' => $result->earliest_data,
                    'latest_data' => $result->latest_data,
                    'collection_days' => $collection_days
                ),
                'coverage_quality' => $this->assess_coverage_quality($stats_obj)
            );
        } else {
            return array(
                'total_unique_points' => 0,
                'unique_named_locations' => 0,
                'message' => 'Nenhum dado de localização encontrado'
            );
        }
        
    } catch (Exception $e) {
        log_message('error', 'Erro em get_coverage_statistics: ' . $e->getMessage());
        return array('error' => 'Erro ao calcular estatísticas de cobertura');
    }
}

/**
 * MÉTODO PRIVADO: Avaliar qualidade da cobertura geográfica
 */
private function assess_coverage_quality($stats) {
    $quality_score = 0;
    $recommendations = array();
    
    // Avaliar número de pontos únicos
    if ($stats->total_unique_points >= 100) {
        $quality_score += 3;
    } elseif ($stats->total_unique_points >= 50) {
        $quality_score += 2;
    } elseif ($stats->total_unique_points >= 10) {
        $quality_score += 1;
    } else {
        $recommendations[] = 'Coletar dados em mais localizações diferentes';
    }
    
    // Avaliar distribuição de respostas por ponto
    if ($stats->avg_responses_per_point >= 3 && $stats->avg_responses_per_point <= 10) {
        $quality_score += 2; // Boa distribuição
    } elseif ($stats->avg_responses_per_point > 10) {
        $recommendations[] = 'Considere diversificar mais as localizações de coleta';
    } else {
        $recommendations[] = 'Considere coletar mais dados por localização';
    }
    
    // Avaliar diversidade de locais nomeados
    $location_diversity = $stats->unique_named_locations / max(1, $stats->total_unique_points);
    if ($location_diversity >= 0.8) {
        $quality_score += 2;
    } elseif ($location_diversity >= 0.5) {
        $quality_score += 1;
    } else {
        $recommendations[] = 'Melhorar nomenclatura/identificação dos locais';
    }
    
    // Determinar classificação
    if ($quality_score >= 6) {
        $classification = 'Excelente';
    } elseif ($quality_score >= 4) {
        $classification = 'Boa';
    } elseif ($quality_score >= 2) {
        $classification = 'Regular';
    } else {
        $classification = 'Precisa melhorar';
    }
    
    return array(
        'score' => $quality_score,
        'max_score' => 7,
        'classification' => $classification,
        'recommendations' => $recommendations
    );
}

/**
 * MÉTODO ADICIONAL: Detectar clusters de localizações
 */
public function detect_location_clusters($filters = array(), $max_distance_km = 1.0) {
    // Obter todas as localizações
    $locations = $this->get_location_heatmap_data($filters);
    
    if (empty($locations['points'])) {
        return array('clusters' => array(), 'isolated_points' => array());
    }
    
    $points = $locations['points'];
    $clusters = array();
    $processed = array();
    
    foreach ($points as $i => $point1) {
        if (in_array($i, $processed)) {
            continue;
        }
        
        $cluster = array($point1);
        $cluster_indices = array($i);
        
        foreach ($points as $j => $point2) {
            if ($i == $j || in_array($j, $processed)) {
                continue;
            }
            
            $distance = $this->calculate_distance(
                $point1['lat'], $point1['lng'],
                $point2['lat'], $point2['lng']
            );
            
            if ($distance <= $max_distance_km) {
                $cluster[] = $point2;
                $cluster_indices[] = $j;
            }
        }
        
        if (count($cluster) > 1) {
            // É um cluster
            $clusters[] = array(
                'points' => $cluster,
                'count' => count($cluster),
                'total_responses' => array_sum(array_column($cluster, 'weight')),
                'center' => $this->calculate_cluster_center($cluster),
                'max_distance_km' => $max_distance_km
            );
            $processed = array_merge($processed, $cluster_indices);
        } else {
            // Ponto isolado
            $processed[] = $i;
        }
    }
    
    // Pontos isolados
    $isolated_points = array();
    foreach ($points as $i => $point) {
        if (!in_array($i, $processed)) {
            $isolated_points[] = $point;
        }
    }
    
    return array(
        'clusters' => $clusters,
        'isolated_points' => $isolated_points,
        'total_clusters' => count($clusters),
        'total_isolated' => count($isolated_points)
    );
}

/**
 * MÉTODO PRIVADO: Calcular centro de um cluster
 */
private function calculate_cluster_center($cluster_points) {
    $total_weight = array_sum(array_column($cluster_points, 'weight'));
    $weighted_lat = 0;
    $weighted_lng = 0;
    
    foreach ($cluster_points as $point) {
        $weight_ratio = $point['weight'] / $total_weight;
        $weighted_lat += $point['lat'] * $weight_ratio;
        $weighted_lng += $point['lng'] * $weight_ratio;
    }
    
    return array(
        'lat' => $weighted_lat,
        'lng' => $weighted_lng
    );
}

private function validate_filter_params($filters) {
    $validated = array();
    $errors = array();
    
    // Validar questionnaire_ids
    if (isset($filters['questionnaire_ids'])) {
        if (is_array($filters['questionnaire_ids'])) {
            $valid_ids = array_filter(
                array_map('intval', $filters['questionnaire_ids']),
                function($id) { return $id > 0; }
            );
            if (!empty($valid_ids)) {
                $validated['questionnaire_ids'] = $valid_ids;
            }
        } else {
            $errors[] = 'questionnaire_ids deve ser um array';
        }
    }
    
    // Validar questionnaire_id
    if (isset($filters['questionnaire_id'])) {
        $id = intval($filters['questionnaire_id']);
        if ($id > 0) {
            $validated['questionnaire_id'] = $id;
        } else {
            $errors[] = 'questionnaire_id deve ser um número positivo';
        }
    }
    
    // Validar applied_by
    if (isset($filters['applied_by'])) {
        $id = intval($filters['applied_by']);
        if ($id > 0) {
            $validated['applied_by'] = $id;
        } else {
            $errors[] = 'applied_by deve ser um número positivo';
        }
    }
    
    // Validar datas
    if (isset($filters['date_from'])) {
        if ($this->validate_date($filters['date_from'])) {
            $validated['date_from'] = $filters['date_from'];
        } else {
            $errors[] = 'date_from deve estar no formato YYYY-MM-DD';
        }
    }
    
    if (isset($filters['date_to'])) {
        if ($this->validate_date($filters['date_to'])) {
            $validated['date_to'] = $filters['date_to'];
        } else {
            $errors[] = 'date_to deve estar no formato YYYY-MM-DD';
        }
    }
    
    // Validar intervalo de datas
    if (isset($validated['date_from']) && isset($validated['date_to'])) {
        if ($validated['date_from'] > $validated['date_to']) {
            $errors[] = 'date_from deve ser anterior a date_to';
        }
    }
    
    // Validar sync_status
    if (isset($filters['sync_status'])) {
        $valid_statuses = array('pending', 'synced', 'error');
        if (in_array($filters['sync_status'], $valid_statuses)) {
            $validated['sync_status'] = $filters['sync_status'];
        } else {
            $errors[] = 'sync_status deve ser: ' . implode(', ', $valid_statuses);
        }
    }
    
    return array(
        'validated' => $validated,
        'errors' => $errors,
        'is_valid' => empty($errors)
    );
}

public function get_location_stats_by_questionnaire($questionnaire_id) {
    $questionnaire_id = (int)$questionnaire_id;
    
    $sql = "
        SELECT 
            COUNT(*) as total_responses,
            COUNT(CASE WHEN fr.latitude IS NOT NULL AND fr.longitude IS NOT NULL 
                       AND fr.latitude != 0 AND fr.longitude != 0 
                  THEN 1 END) as with_location,
            COUNT(CASE WHEN fr.photo_path IS NOT NULL AND fr.photo_path != '' 
                  THEN 1 END) as with_photos,
            COUNT(DISTINCT CASE WHEN fr.location_name IS NOT NULL AND fr.location_name != ''
                               THEN fr.location_name END) as unique_locations,
            COUNT(DISTINCT fr.applied_by) as unique_applicators,
            MIN(fr.completed_at) as earliest_response,
            MAX(fr.completed_at) as latest_response,
            AVG(CASE 
                WHEN fr.latitude IS NOT NULL AND fr.longitude IS NOT NULL 
                     AND fr.latitude != 0 AND fr.longitude != 0
                THEN fr.latitude 
            END) as avg_latitude,
            AVG(CASE 
                WHEN fr.latitude IS NOT NULL AND fr.longitude IS NOT NULL 
                     AND fr.latitude != 0 AND fr.longitude != 0
                THEN fr.longitude 
            END) as avg_longitude
        FROM form_responses fr
        WHERE fr.questionnaire_id = ? AND fr.completed_at IS NOT NULL
    ";
    
    try {
        $result = $this->db->query($sql, array($questionnaire_id))->row();
        
        if ($result) {
            // Calcular taxas
            $result->location_rate = $result->total_responses > 0 ? 
                round(($result->with_location / $result->total_responses) * 100, 1) : 0;
            
            $result->photo_rate = $result->total_responses > 0 ? 
                round(($result->with_photos / $result->total_responses) * 100, 1) : 0;
            
            // Formatar datas
            if ($result->earliest_response) {
                $result->earliest_response_formatted = date('d/m/Y H:i', strtotime($result->earliest_response));
            }
            
            if ($result->latest_response) {
                $result->latest_response_formatted = date('d/m/Y H:i', strtotime($result->latest_response));
            }
            
            // Calcular período de coleta em dias
            if ($result->earliest_response && $result->latest_response) {
                $start = new DateTime($result->earliest_response);
                $end = new DateTime($result->latest_response);
                $result->collection_period_days = $end->diff($start)->days;
            } else {
                $result->collection_period_days = 0;
            }
        }
        
        return $result;
        
    } catch (Exception $e) {
        log_message('error', 'Erro em get_location_stats_by_questionnaire: ' . $e->getMessage());
        return null;
    }
}

/**
 * MÉTODO CORRIGIDO: Validar disponibilidade de dados para KMZ
 */
public function validate_kmz_data_availability($filters = array()) {
    $where_conditions = array();
    $where_conditions[] = "fr.latitude IS NOT NULL";
    $where_conditions[] = "fr.longitude IS NOT NULL";
    $where_conditions[] = "fr.latitude != 0";
    $where_conditions[] = "fr.longitude != 0";
    $where_conditions[] = "fr.completed_at IS NOT NULL";
    
    $params = array();
    
    // Aplicar filtros
    if (isset($filters['questionnaire_ids']) && is_array($filters['questionnaire_ids']) && !empty($filters['questionnaire_ids'])) {
        $valid_ids = array_filter(array_map('intval', $filters['questionnaire_ids']), function($id) { return $id > 0; });
        if (!empty($valid_ids)) {
            $placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
            $where_conditions[] = "fr.questionnaire_id IN ($placeholders)";
            $params = array_merge($params, $valid_ids);
        }
    }
    
    if (isset($filters['date_from']) && $filters['date_from']) {
        $where_conditions[] = "DATE(fr.completed_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (isset($filters['date_to']) && $filters['date_to']) {
        $where_conditions[] = "DATE(fr.completed_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $sql = "
        SELECT 
            COUNT(*) as total,
            COUNT(DISTINCT fr.questionnaire_id) as unique_questionnaires,
            COUNT(CASE WHEN fr.photo_path IS NOT NULL AND fr.photo_path != '' THEN 1 END) as with_photos,
            COUNT(DISTINCT CASE WHEN fr.location_name IS NOT NULL AND fr.location_name != '' 
                               THEN fr.location_name END) as unique_locations
        FROM form_responses fr 
        WHERE {$where_clause}
    ";
    
    try {
        if (!empty($params)) {
            $result = $this->db->query($sql, $params)->row();
        } else {
            $result = $this->db->query($sql)->row();
        }
        
        $count = $result ? $result->total : 0;
        
        return array(
            'has_data' => $count > 0,
            'total_locations' => (int)$count,
            'unique_questionnaires' => $result ? (int)$result->unique_questionnaires : 0,
            'with_photos' => $result ? (int)$result->with_photos : 0,
            'unique_locations' => $result ? (int)$result->unique_locations : 0,
            'minimum_required' => 1,
            'meets_requirements' => $count >= 1
        );
        
    } catch (Exception $e) {
        log_message('error', 'Erro em validate_kmz_data_availability: ' . $e->getMessage());
        return array(
            'has_data' => false,
            'total_locations' => 0,
            'meets_requirements' => false,
            'error' => $e->getMessage()
        );
    }
}

/**
 * MÉTODO CORRIGIDO: Obter distribuição geográfica das respostas
 */
public function get_geographic_distribution($filters = array()) {
    $where_conditions = array();
    $where_conditions[] = "fr.latitude IS NOT NULL";
    $where_conditions[] = "fr.longitude IS NOT NULL";
    $where_conditions[] = "fr.latitude != 0";
    $where_conditions[] = "fr.longitude != 0";
    $where_conditions[] = "fr.location_name IS NOT NULL";
    $where_conditions[] = "fr.location_name != ''";
    $where_conditions[] = "fr.completed_at IS NOT NULL";
    
    $params = array();
    
    // Aplicar filtros
    if (isset($filters['questionnaire_ids']) && is_array($filters['questionnaire_ids']) && !empty($filters['questionnaire_ids'])) {
        $valid_ids = array_filter(array_map('intval', $filters['questionnaire_ids']), function($id) { return $id > 0; });
        if (!empty($valid_ids)) {
            $placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
            $where_conditions[] = "fr.questionnaire_id IN ($placeholders)";
            $params = array_merge($params, $valid_ids);
        }
    }
    
    if (isset($filters['date_from']) && $filters['date_from']) {
        $where_conditions[] = "DATE(fr.completed_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (isset($filters['date_to']) && $filters['date_to']) {
        $where_conditions[] = "DATE(fr.completed_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $sql = "
        SELECT
            fr.location_name,
            COUNT(*) as total_responses,
            COUNT(DISTINCT fr.applied_by) as unique_applicators,
            COUNT(DISTINCT fr.questionnaire_id) as unique_questionnaires,
            COUNT(CASE WHEN fr.photo_path IS NOT NULL AND fr.photo_path != '' THEN 1 END) as responses_with_photos,
            AVG(fr.latitude) as avg_latitude,
            AVG(fr.longitude) as avg_longitude,
            MIN(fr.completed_at) as first_response_date,
            MAX(fr.completed_at) as last_response_date
        FROM form_responses fr
        WHERE {$where_clause}
        GROUP BY fr.location_name
        ORDER BY total_responses DESC
        LIMIT 50
    ";
    
    try {
        if (!empty($params)) {
            $query = $this->db->query($sql, $params);
        } else {
            $query = $this->db->query($sql);
        }
        
        $distribution = $query->result();
        
        // Processar dados
        foreach ($distribution as &$location) {
            $location->photo_rate = $location->total_responses > 0 ? 
                round(($location->responses_with_photos / $location->total_responses) * 100, 1) : 0;
            
            $location->first_response_formatted = date('d/m/Y', strtotime($location->first_response_date));
            $location->last_response_formatted = date('d/m/Y', strtotime($location->last_response_date));
            
            // Calcular duração da coleta neste local
            $first = new DateTime($location->first_response_date);
            $last = new DateTime($location->last_response_date);
            $location->collection_duration_days = $last->diff($first)->days;
        }
        
        return $distribution;
        
    } catch (Exception $e) {
        log_message('error', 'Erro em get_geographic_distribution: ' . $e->getMessage());
        return array();
    }
}

/**
 * MÉTODO CORRIGIDO: Obter dados do mapa de calor
 */
public function get_location_heatmap_data($filters = array()) {
    $where_conditions = array();
    $where_conditions[] = "fr.latitude IS NOT NULL";
    $where_conditions[] = "fr.longitude IS NOT NULL"; 
    $where_conditions[] = "fr.latitude != 0";
    $where_conditions[] = "fr.longitude != 0";
    $where_conditions[] = "fr.completed_at IS NOT NULL";
    
    $params = array();
    
    // Aplicar filtros
    if (isset($filters['questionnaire_ids']) && is_array($filters['questionnaire_ids']) && !empty($filters['questionnaire_ids'])) {
        $valid_ids = array_filter(array_map('intval', $filters['questionnaire_ids']), function($id) { return $id > 0; });
        if (!empty($valid_ids)) {
            $placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
            $where_conditions[] = "fr.questionnaire_id IN ($placeholders)";
            $params = array_merge($params, $valid_ids);
        }
    }
    
    if (isset($filters['date_from']) && $filters['date_from']) {
        $where_conditions[] = "DATE(fr.completed_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (isset($filters['date_to']) && $filters['date_to']) {
        $where_conditions[] = "DATE(fr.completed_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Query corrigida para PostgreSQL com STRING_AGG
    $sql = "
        SELECT 
            fr.latitude,
            fr.longitude,
            fr.location_name,
            COUNT(*) as response_count,
            COUNT(DISTINCT fr.questionnaire_id) as questionnaire_count,
            COUNT(CASE WHEN fr.photo_path IS NOT NULL AND fr.photo_path != '' THEN 1 END) as photo_count,
            STRING_AGG(DISTINCT q.title, ', ' ORDER BY q.title) as questionnaire_titles,
            MIN(fr.completed_at) as first_response,
            MAX(fr.completed_at) as last_response
        FROM form_responses fr
        LEFT JOIN questionnaires q ON fr.questionnaire_id = q.id
        WHERE {$where_clause}
        GROUP BY fr.latitude, fr.longitude, fr.location_name
        ORDER BY response_count DESC
    ";
    
    try {
        if (!empty($params)) {
            $query = $this->db->query($sql, $params);
        } else {
            $query = $this->db->query($sql);
        }
        
        $locations = $query->result();
        
        // Processar dados para heatmap
        $heatmap_points = array();
        $total_responses = 0;
        
        foreach ($locations as $location) {
            $heatmap_points[] = array(
                'lat' => (float) $location->latitude,
                'lng' => (float) $location->longitude,
                'weight' => (int) $location->response_count,
                'location_name' => $location->location_name ?: 'Localização sem nome',
                'questionnaire_count' => (int) $location->questionnaire_count,
                'questionnaire_titles' => $location->questionnaire_titles,
                'photo_count' => (int) $location->photo_count,
                'first_response' => $location->first_response,
                'last_response' => $location->last_response
            );
            
            $total_responses += (int)$location->response_count;
        }
        
        return array(
            'points' => $heatmap_points,
            'total_locations' => count($locations),
            'total_responses' => $total_responses,
            'max_weight' => !empty($locations) ? max(array_column($heatmap_points, 'weight')) : 0,
            'avg_responses_per_location' => count($locations) > 0 ? round($total_responses / count($locations), 1) : 0
        );
        
    } catch (Exception $e) {
        log_message('error', 'Erro em get_location_heatmap_data: ' . $e->getMessage());
        return array(
            'points' => array(),
            'total_locations' => 0,
            'total_responses' => 0,
            'max_weight' => 0,
            'error' => $e->getMessage()
        );
    }
}

/**
 * MÉTODO AUXILIAR: Formatar duração em minutos
 */
private function format_duration($minutes) {
    if (!$minutes || $minutes <= 0) {
        return 'N/A';
    }
    
    if ($minutes < 60) {
        return round($minutes) . ' min';
    } else {
        $hours = floor($minutes / 60);
        $mins = round($minutes % 60);
        return $hours . 'h ' . ($mins > 0 ? $mins . 'min' : '');
    }
}

/**
 * MÉTODO AUXILIAR: Formatar status de sincronização
 */
private function format_sync_status($status) {
    switch (strtolower($status)) {
        case 'synced': return 'Sincronizado';
        case 'pending': return 'Pendente';
        case 'error': return 'Erro';
        default: return 'Desconhecido';
    }
}

/**
 * MÉTODO ADICIONAL: Obter estatísticas resumidas para KMZ
 */
public function get_kmz_summary_stats($filters = array()) {
    $locations_data = $this->validate_kmz_data_availability($filters);
    $geographic_data = $this->get_geographic_distribution($filters);
    
    return array(
        'total_locations' => $locations_data['total_locations'],
        'unique_questionnaires' => $locations_data['unique_questionnaires'], 
        'with_photos' => $locations_data['with_photos'],
        'unique_geographic_locations' => $locations_data['unique_locations'],
        'top_locations' => array_slice($geographic_data, 0, 5),
        'photo_coverage_rate' => $locations_data['total_locations'] > 0 ? 
            round(($locations_data['with_photos'] / $locations_data['total_locations']) * 100, 1) : 0,
        'geographic_diversity' => count($geographic_data),
        'ready_for_kmz' => $locations_data['has_data'] && $locations_data['meets_requirements']
    );
}

/**
 * MÉTODO ADICIONAL: Obter bounds geográficos para centrar mapa
 */
public function get_geographic_bounds($filters = array()) {
    $where_conditions = array();
    $where_conditions[] = "fr.latitude IS NOT NULL";
    $where_conditions[] = "fr.longitude IS NOT NULL";
    $where_conditions[] = "fr.latitude != 0";
    $where_conditions[] = "fr.longitude != 0";
    
    $params = array();
    
    // Aplicar filtros básicos
    if (isset($filters['questionnaire_ids']) && is_array($filters['questionnaire_ids']) && !empty($filters['questionnaire_ids'])) {
        $valid_ids = array_filter(array_map('intval', $filters['questionnaire_ids']), function($id) { return $id > 0; });
        if (!empty($valid_ids)) {
            $placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
            $where_conditions[] = "fr.questionnaire_id IN ($placeholders)";
            $params = array_merge($params, $valid_ids);
        }
    }
    
    if (isset($filters['date_from']) && $filters['date_from']) {
        $where_conditions[] = "DATE(fr.completed_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (isset($filters['date_to']) && $filters['date_to']) {
        $where_conditions[] = "DATE(fr.completed_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $sql = "
        SELECT 
            MIN(fr.latitude) as min_lat,
            MAX(fr.latitude) as max_lat,
            MIN(fr.longitude) as min_lng,
            MAX(fr.longitude) as max_lng,
            AVG(fr.latitude) as center_lat,
            AVG(fr.longitude) as center_lng,
            COUNT(*) as total_points
        FROM form_responses fr
        WHERE {$where_clause}
    ";
    
    try {
        if (!empty($params)) {
            $result = $this->db->query($sql, $params)->row();
        } else {
            $result = $this->db->query($sql)->row();
        }
        
        if ($result && $result->total_points > 0) {
            return array(
                'bounds' => array(
                    'southwest' => array(
                        'lat' => (float)$result->min_lat,
                        'lng' => (float)$result->min_lng
                    ),
                    'northeast' => array(
                        'lat' => (float)$result->max_lat,
                        'lng' => (float)$result->max_lng
                    )
                ),
                'center' => array(
                    'lat' => (float)$result->center_lat,
                    'lng' => (float)$result->center_lng
                ),
                'total_points' => (int)$result->total_points
            );
        } else {
            // Retornar bounds padrão do Brasil se não houver dados
            return array(
                'bounds' => array(
                    'southwest' => array('lat' => -33.7683777, 'lng' => -73.9872354),
                    'northeast' => array('lat' => 5.2717863, 'lng' => -28.847770)
                ),
                'center' => array('lat' => -15.7942, 'lng' => -47.8822),
                'total_points' => 0
            );
        }
        
    } catch (Exception $e) {
        log_message('error', 'Erro em get_geographic_bounds: ' . $e->getMessage());
        return null;
    }

}

}
