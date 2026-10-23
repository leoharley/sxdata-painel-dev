<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ai extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        $this->load->model('Ai_model');
        $this->load->model('Questionnaire_model');
        $this->load->model('Response_model');
        $this->load->library('ai_service');
        $this->load->library('ai_prompt_service');
        $this->check_auth();
    }

    // ============================================================
    // DASHBOARD DE IA
    // ============================================================

    public function index() {
        $data['title'] = 'Inteligência Artificial - SXData';
        $data['stats'] = $this->Ai_model->get_dashboard_stats();
        $data['execution_stats'] = $this->Ai_model->get_execution_stats();
        $data['settings'] = $this->Ai_model->get_settings_by_category();
        $data['is_configured'] = $this->ai_service->is_configured();

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/dashboard', $data);
        $this->load->view('admin/footer');
    }

    // ============================================================
    // CONFIGURAÇÕES
    // ============================================================

    public function settings() {
        $data['title'] = 'Configurações de IA - SXData';
        $data['settings'] = $this->Ai_model->get_settings_by_category();
        $data['prompts'] = $this->Ai_model->get_prompts();
        $data['is_configured'] = $this->ai_service->is_configured();

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/settings', $data);
        $this->load->view('admin/footer');
    }

    public function update_setting() {
        $id = $this->input->post('id');
        $data = array(
            'is_enabled' => $this->input->post('is_enabled') ? TRUE : FALSE,
            'model' => $this->input->post('model'),
            'temperature' => (float) $this->input->post('temperature'),
            'max_tokens' => (int) $this->input->post('max_tokens'),
            'timeout_seconds' => (int) $this->input->post('timeout_seconds'),
            'retry_attempts' => (int) $this->input->post('retry_attempts'),
        );

        $this->Ai_model->update_setting($id, $data);
        $this->session->set_flashdata('success', 'Configuração atualizada com sucesso!');
        redirect('ai/settings');
    }

    public function toggle_feature() {
        $feature_key = $this->input->post('feature_key');
        $enabled = $this->input->post('enabled') === 'true';

        $this->Ai_model->toggle_feature($feature_key, $enabled);

        header('Content-Type: application/json');
        echo json_encode(array('success' => true));
    }

    public function test_connection() {
        $result = $this->ai_service->test_connection();

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    // ============================================================
    // PROMPTS
    // ============================================================

    public function prompts() {
        $data['title'] = 'Prompts de IA - SXData';
        $data['prompts'] = $this->Ai_model->get_prompts();
        $data['settings'] = $this->Ai_model->get_all_settings();

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/prompts', $data);
        $this->load->view('admin/footer');
    }

    public function save_prompt() {
        $id = $this->input->post('id');
        $data = array(
            'feature_key' => $this->input->post('feature_key'),
            'prompt_name' => $this->input->post('prompt_name'),
            'system_prompt' => $this->input->post('system_prompt'),
            'user_prompt_template' => $this->input->post('user_prompt_template'),
            'is_active' => $this->input->post('is_active') ? TRUE : FALSE,
        );

        if ($id) {
            $this->Ai_model->update_prompt($id, $data);
            $this->session->set_flashdata('success', 'Prompt atualizado!');
        } else {
            $data['version'] = 1;
            $data['created_by'] = $this->session->userdata('admin_id');
            $this->Ai_model->create_prompt($data);
            $this->session->set_flashdata('success', 'Prompt criado!');
        }

        redirect('ai/prompts');
    }

    // ============================================================
    // LOGS
    // ============================================================

    public function logs() {
        $data['title'] = 'Logs de IA - SXData';

        $filters = array(
            'feature_key' => $this->input->get('feature_key'),
            'status' => $this->input->get('status'),
            'date_from' => $this->input->get('date_from'),
            'date_to' => $this->input->get('date_to'),
        );

        $page = max(1, (int) $this->input->get('page'));
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        $data['logs'] = $this->Ai_model->get_execution_logs($filters, $per_page, $offset);
        $data['total'] = $this->Ai_model->count_execution_logs($filters);
        $data['filters'] = $filters;
        $data['page'] = $page;
        $data['per_page'] = $per_page;
        $data['settings'] = $this->Ai_model->get_all_settings();

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/logs', $data);
        $this->load->view('admin/footer');
    }

    // ============================================================
    // TRANSCRIÇÃO DE ÁUDIO
    // ============================================================

    public function transcriptions() {
        $data['title'] = 'Transcrições de Áudio - SXData';
        $data['is_enabled'] = $this->Ai_model->is_feature_enabled('transcription');

        // Reseta transcrições presas em "processing" há mais de 5 minutos
        $this->db->where('status', 'processing')
                 ->where('updated_at <', date('Y-m-d H:i:s', strtotime('-5 minutes')))
                 ->update('ai_transcriptions', array(
                     'status' => 'error',
                     'error_message' => 'Timeout: o processamento excedeu o tempo limite.',
                 ));

        // Filtros
        $filter_questionnaire_id = $this->input->get('questionnaire_id');
        $filter_applicator_id    = $this->input->get('applicator_id');
        $filter_date_from        = $this->input->get('date_from');
        $filter_date_to          = $this->input->get('date_to');
        $filter_search           = $this->input->get('search');
        $page                    = max(1, (int)$this->input->get('page'));
        $per_page                = 20;
        $offset                  = ($page - 1) * $per_page;

        // Query base — resolve questionnaire via question_id se questionnaire_id estiver NULL
        $this->db->select('t.*, COALESCE(q.title, q2.title) as questionnaire_title, COALESCE(t.question_text, qst.question_text) as resolved_question_text', FALSE)
                 ->from('ai_transcriptions t')
                 ->join('questions qst', 'qst.id = t.question_id', 'left')
                 ->join('questionnaires q', 'q.id = t.questionnaire_id', 'left')
                 ->join('questionnaires q2', 'q2.id = qst.questionnaire_id', 'left');

        if ($filter_questionnaire_id) $this->db->where('t.questionnaire_id', $filter_questionnaire_id);
        if ($filter_applicator_id)    $this->db->where('t.applicator_id', $filter_applicator_id);
        if ($filter_date_from)        $this->db->where('COALESCE(t.timestamp_app, t.created_at) >=', $filter_date_from);
        if ($filter_date_to)          $this->db->where('COALESCE(t.timestamp_app, t.created_at) <=', $filter_date_to . ' 23:59:59');
        if ($filter_search) {
            $this->db->group_start()
                     ->like('t.transcription_text', $filter_search)
                     ->or_like('t.edited_text', $filter_search)
                     ->or_like('t.question_text', $filter_search)
                     ->or_like('t.applicator_name', $filter_search)
                     ->group_end();
        }

        $total = $this->db->count_all_results('', false);
        $this->db->order_by('COALESCE(t.timestamp_app, t.created_at) DESC', '', FALSE)
                 ->limit($per_page, $offset);
        $data['transcriptions'] = $this->db->get()->result();

        // Estatísticas gerais
        $data['stats'] = $this->db->select("
            COUNT(*) as total,
            AVG(confidence_score) as avg_confidence,
            SUM(CASE WHEN edited_text IS NOT NULL AND edited_text != transcription_text THEN 1 ELSE 0 END) as edited_count,
            SUM(COALESCE(recording_duration_secs, 0)) as total_seconds
        ")->from('ai_transcriptions')->get()->row();

        $data['status_counts'] = $this->Ai_model->count_transcriptions_by_status();

        // Paginação
        $data['pagination'] = array(
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => max(1, ceil($total / $per_page)),
        );

        // Dropdowns para filtros
        $data['filter_questionnaires'] = $this->db->select('id, title')
            ->from('questionnaires')->order_by('title')->get()->result();
        $data['filter_applicators'] = $this->db->select('applicator_id as id, applicator_name')
            ->from('ai_transcriptions')
            ->where('applicator_id IS NOT NULL')
            ->where('applicator_name IS NOT NULL')
            ->group_by('applicator_id, applicator_name')
            ->order_by('applicator_name')->get()->result();

        $data['filter_questionnaire_id'] = $filter_questionnaire_id;
        $data['filter_applicator_id']    = $filter_applicator_id;
        $data['filter_date_from']        = $filter_date_from;
        $data['filter_date_to']          = $filter_date_to;
        $data['filter_search']           = $filter_search;

        // Passa form_responses e questions para os dropdowns do upload
        $data['form_responses'] = $this->db
            ->select("fr.id, '#' || fr.id || ' - ' || q.title AS label")
            ->from('form_responses fr')
            ->join('questionnaires q', 'q.id = fr.questionnaire_id', 'left')
            ->order_by('fr.id', 'DESC')
            ->limit(100)
            ->get()->result();
        $data['questions'] = $this->db
            ->select("questions.id, q.title || ' > ' || questions.question_text AS label")
            ->from('questions')
            ->join('questionnaires q', 'q.id = questions.questionnaire_id', 'left')
            ->order_by('q.title', 'ASC')
            ->get()->result();

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/transcriptions', $data);
        $this->load->view('admin/footer');
    }

    public function upload_audio() {
        $config = array(
            'upload_path' => FCPATH . 'uploads/audio/',
            'allowed_types' => 'mp3|wav|m4a|ogg|webm|mp4',
            'max_size' => 25600, // 25MB (limite Whisper)
        );

        if (!is_dir($config['upload_path'])) {
            mkdir($config['upload_path'], 0755, true);
        }

        $this->load->library('upload', $config);

        if (!$this->upload->do_upload('audio_file')) {
            $this->session->set_flashdata('error', $this->upload->display_errors('', ''));
            redirect('ai/transcriptions');
            return;
        }

        $upload_data = $this->upload->data();
        $form_response_id = $this->input->post('form_response_id') ?: null;
        $question_id = $this->input->post('question_id') ?: null;

        $transcription_id = $this->Ai_model->create_transcription(array(
            'form_response_id' => $form_response_id,
            'question_id' => $question_id,
            'audio_file_path' => 'uploads/audio/' . $upload_data['file_name'],
            'status' => 'pending',
        ));

        // Processar imediatamente se a feature estiver ativa
        if ($this->Ai_model->is_feature_enabled('transcription')) {
            $this->process_transcription($transcription_id);
        }

        $this->session->set_flashdata('success', 'Áudio enviado com sucesso!');
        redirect('ai/transcriptions');
    }

    public function process_transcription($id = null) {
        if (!$id) {
            $id = $this->input->post('id');
        }

        $transcription = $this->Ai_model->get_transcription($id);
        if (!$transcription) {
            if ($this->input->is_ajax_request()) {
                header('Content-Type: application/json');
                echo json_encode(array('success' => false, 'error' => 'Transcrição não encontrada'));
                return;
            }
            $this->session->set_flashdata('error', 'Transcrição não encontrada.');
            redirect('ai/transcriptions');
            return;
        }

        $file_path = FCPATH . $transcription->audio_file_path;
        $this->Ai_model->update_transcription($id, array('status' => 'processing'));

        $result = $this->ai_service->transcribe_audio($file_path, array(
            'resource_type' => 'transcription',
            'resource_id' => $id,
        ));

        if ($result['success']) {
            $this->Ai_model->update_transcription($id, array(
                'transcription_text' => $result['text'],
                'audio_duration_seconds' => $result['duration'],
                'language' => $result['language'],
                'status' => 'completed',
                'model_used' => 'whisper-1',
                'processed_at' => date('Y-m-d H:i:s'),
                'processed_by' => $this->session->userdata('admin_id'),
            ));
        } else {
            $this->Ai_model->update_transcription($id, array(
                'status' => 'error',
                'error_message' => $result['error'],
            ));
        }

        if ($this->input->is_ajax_request()) {
            header('Content-Type: application/json');
            echo json_encode($result);
            return;
        }

        if ($result['success']) {
            $this->session->set_flashdata('success', 'Transcrição concluída!');
        } else {
            $this->session->set_flashdata('error', 'Erro na transcrição: ' . $result['error']);
        }
        redirect('ai/transcriptions');
    }

    public function save_transcription_edit() {
        $id = $this->input->post('id');
        $edited_text = $this->input->post('transcription_edited');

        $this->Ai_model->update_transcription($id, array(
            'transcription_edited' => $edited_text,
        ));

        header('Content-Type: application/json');
        echo json_encode(array('success' => true));
    }

    // ============================================================
    // DETECÇÃO DE INCONSISTÊNCIAS
    // ============================================================

    public function inconsistencies() {
        $data['title'] = 'Detecção de Inconsistências - SXData';

        $filters = array(
            'severity' => $this->input->get('severity'),
            'resolution_status' => $this->input->get('status'),
            'questionnaire_id' => $this->input->get('questionnaire_id'),
        );

        $data['inconsistencies'] = $this->Ai_model->get_inconsistencies($filters);
        $data['severity_counts'] = $this->Ai_model->count_inconsistencies_by_severity();
        $data['questionnaires'] = $this->Questionnaire_model->get_active();
        $data['filters'] = $filters;
        $data['is_enabled'] = $this->Ai_model->is_feature_enabled('inconsistency_detection');

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/inconsistencies', $data);
        $this->load->view('admin/footer');
    }

    public function analyze_inconsistencies() {
        header('Content-Type: application/json');

        try {
            $form_response_id = $this->input->post('form_response_id');

            if (!$form_response_id) {
                echo json_encode(array('success' => false, 'message' => 'Informe o ID da resposta.'));
                return;
            }

            // Verificar se a feature está habilitada
            if (!$this->Ai_model->is_feature_enabled('inconsistency_detection')) {
                echo json_encode(array('success' => false, 'message' => 'A funcionalidade "Detecção de Inconsistências" está desabilitada. Ative-a em Configurações de IA.'));
                return;
            }

            // Verificar se a API key está configurada
            if (!$this->ai_service->is_configured()) {
                echo json_encode(array('success' => false, 'message' => 'A chave da API OpenAI não está configurada. Verifique o arquivo .env'));
                return;
            }

            $context = $this->ai_prompt_service->prepare_inconsistency_data($form_response_id);
            if (!$context) {
                echo json_encode(array('success' => false, 'message' => 'Resposta #' . $form_response_id . ' não encontrada no banco de dados.'));
                return;
            }

            $messages_result = $this->ai_prompt_service->build_messages('inconsistency_detection', $context);
            if (!$messages_result['success']) {
                $error_msg = isset($messages_result['error']) ? $messages_result['error'] : 'Erro ao montar prompt.';
                echo json_encode(array('success' => false, 'message' => $error_msg));
                return;
            }

            $result = $this->ai_service->chat_completion_json('inconsistency_detection', $messages_result['messages'], array(
                'prompt_id' => $messages_result['prompt_id'],
                'resource_type' => 'form_response',
                'resource_id' => $form_response_id,
            ));

            if ($result['success'] && isset($result['parsed']) && $result['parsed']) {
                $items = is_array($result['parsed']) && isset($result['parsed'][0]) ? $result['parsed'] : array($result['parsed']);
                $count = 0;

                foreach ($items as $item) {
                    if (!is_array($item) || empty($item['description'])) continue;

                    $this->Ai_model->create_inconsistency(array(
                        'form_response_id' => $form_response_id,
                        'questionnaire_id' => $context['questionnaire_id'],
                        'inconsistency_type' => isset($item['inconsistency_type']) ? $item['inconsistency_type'] : 'logic',
                        'severity' => isset($item['severity']) ? $item['severity'] : 'medium',
                        'consistency_score' => isset($item['consistency_score']) ? $item['consistency_score'] : null,
                        'description' => isset($item['description']) ? $item['description'] : '',
                        'ai_justification' => isset($item['description']) ? $item['description'] : '',
                        'affected_questions' => json_encode(isset($item['affected_questions']) ? $item['affected_questions'] : array()),
                        'suggested_action' => isset($item['suggested_action']) ? $item['suggested_action'] : '',
                        'execution_log_id' => isset($result['log_id']) ? $result['log_id'] : null,
                    ));
                    $count++;
                }

                echo json_encode(array(
                    'success' => true,
                    'message' => 'Análise concluída com sucesso.',
                    'count' => $count,
                ));
            } else {
                $error_msg = isset($result['error']) ? $result['error'] : 'Erro ao processar análise com IA.';
                echo json_encode(array('success' => false, 'message' => $error_msg));
            }

        } catch (Exception $e) {
            log_message('error', 'AI analyze_inconsistencies error: ' . $e->getMessage());
            echo json_encode(array('success' => false, 'message' => 'Erro interno: ' . $e->getMessage()));
        }
    }

    public function clear_inconsistencies() {
        header('Content-Type: application/json');
        $this->db->truncate('ai_inconsistencies');
        echo json_encode(array('success' => true));
    }

    public function resolve_inconsistency() {
        $id = $this->input->post('id');
        $action = $this->input->post('action'); // confirmed, dismissed, resolved

        $this->Ai_model->resolve_inconsistency($id, array(
            'resolution_status' => $action,
            'resolved_by' => $this->session->userdata('admin_id'),
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolution_notes' => $this->input->post('notes'),
        ));

        header('Content-Type: application/json');
        echo json_encode(array('success' => true));
    }

    // ============================================================
    // CORREÇÃO E PADRONIZAÇÃO
    // ============================================================

    public function corrections() {
        $data['title'] = 'Correção e Padronização - SXData';

        $filters = array(
            'status' => $this->input->get('status'),
            'correction_type' => $this->input->get('type'),
        );

        $data['corrections'] = $this->Ai_model->get_corrections($filters);
        $data['filters'] = $filters;
        $data['is_enabled'] = $this->Ai_model->is_feature_enabled('data_correction');
        $data['questionnaires'] = $this->Questionnaire_model->get_active();

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/corrections', $data);
        $this->load->view('admin/footer');
    }

    public function analyze_corrections() {
        $form_response_id = $this->input->post('form_response_id');

        $context = $this->ai_prompt_service->prepare_correction_data($form_response_id);
        if (!$context) {
            header('Content-Type: application/json');
            echo json_encode(array('success' => false, 'error' => 'Resposta não encontrada'));
            return;
        }

        $messages_result = $this->ai_prompt_service->build_messages('data_correction', $context);
        if (!$messages_result['success']) {
            header('Content-Type: application/json');
            echo json_encode($messages_result);
            return;
        }

        $result = $this->ai_service->chat_completion_json('data_correction', $messages_result['messages'], array(
            'prompt_id' => $messages_result['prompt_id'],
            'resource_type' => 'form_response',
            'resource_id' => $form_response_id,
        ));

        if ($result['success'] && $result['parsed']) {
            $items = is_array($result['parsed']) && isset($result['parsed'][0]) ? $result['parsed'] : array($result['parsed']);

            foreach ($items as $item) {
                if (empty($item['suggested_value'])) continue;
                // Ignora sugestões idênticas ao valor original
                if (isset($item['original_value']) && trim((string)$item['original_value']) === trim((string)$item['suggested_value'])) continue;

                $this->Ai_model->create_correction(array(
                    'form_response_id' => $form_response_id,
                    'question_id' => $item['question_id'] ?? null,
                    'original_value' => $item['original_value'] ?? '',
                    'suggested_value' => $item['suggested_value'] ?? '',
                    'correction_type' => $item['correction_type'] ?? 'padronizacao',
                    'confidence_score' => $item['confidence'] ?? null,
                    'execution_log_id' => $result['log_id'],
                ));
            }
        }

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    public function clear_corrections() {
        header('Content-Type: application/json');
        $this->db->truncate('ai_corrections');
        echo json_encode(array('success' => true));
    }

    public function review_correction() {
        $id     = $this->input->post('id');
        $action = $this->input->post('action'); // accepted, rejected, edited

        $correction = $this->Ai_model->get_correction($id);
        if (!$correction) {
            header('Content-Type: application/json');
            echo json_encode(array('success' => false, 'message' => 'Correção não encontrada.'));
            return;
        }

        $applied_value = $this->input->post('applied_value');
        $new_value     = ($action === 'edited' && $applied_value !== null)
                         ? $applied_value
                         : $correction->suggested_value;

        $data = array(
            'status'       => $action,
            'reviewed_by'  => $this->session->userdata('admin_id'),
            'reviewed_at'  => date('Y-m-d H:i:s'),
            'applied_value'=> ($action !== 'rejected') ? $new_value : null,
        );

        $this->Ai_model->update_correction($id, $data);

        // Aplica a correção na resposta original (apenas para aceito/editado)
        if ($action !== 'rejected' && $correction->form_response_id && $correction->question_id) {
            $this->db->where('form_response_id', $correction->form_response_id)
                     ->where('question_id', $correction->question_id)
                     ->update('question_responses', array('response_text' => $new_value));
        }

        header('Content-Type: application/json');
        echo json_encode(array('success' => true));
    }

    // ============================================================
    // PREENCHIMENTO INTELIGENTE
    // ============================================================

    public function smart_fill() {
        $data['title'] = 'Preenchimento Inteligente - SXData';
        $data['suggestions'] = $this->Ai_model->get_field_suggestions();
        $data['questionnaires'] = $this->Questionnaire_model->get_active();
        $data['is_enabled'] = $this->Ai_model->is_feature_enabled('smart_fill');

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/smart_fill', $data);
        $this->load->view('admin/footer');
    }

    public function generate_suggestions() {
        $questionnaire_id = $this->input->post('questionnaire_id');
        $existing_answers = $this->input->post('existing_answers') ?: array();

        $context = $this->ai_prompt_service->prepare_smart_fill_data($questionnaire_id, $existing_answers);
        $messages_result = $this->ai_prompt_service->build_messages('smart_fill', $context);

        if (!$messages_result['success']) {
            header('Content-Type: application/json');
            echo json_encode($messages_result);
            return;
        }

        $result = $this->ai_service->chat_completion_json('smart_fill', $messages_result['messages'], array(
            'prompt_id' => $messages_result['prompt_id'],
            'resource_type' => 'questionnaire',
            'resource_id' => $questionnaire_id,
        ));

        if ($result['success'] && $result['parsed']) {
            $items = is_array($result['parsed']) && isset($result['parsed'][0]) ? $result['parsed'] : array($result['parsed']);

            foreach ($items as $item) {
                $this->Ai_model->create_field_suggestion(array(
                    'questionnaire_id' => $questionnaire_id,
                    'question_id' => $item['question_id'] ?? 0,
                    'suggested_value' => $item['suggested_value'] ?? '',
                    'confidence_score' => $item['confidence'] ?? null,
                    'context_data' => json_encode(array('rationale' => $item['rationale'] ?? '')),
                    'execution_log_id' => $result['log_id'],
                ));
            }
        }

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    public function approve_suggestion() {
        header('Content-Type: application/json');
        $suggestion_id = (int) $this->input->post('suggestion_id');

        if (!$suggestion_id) {
            echo json_encode(array('success' => false, 'message' => 'ID inválido.'));
            return;
        }

        $result = $this->Ai_model->update_field_suggestion($suggestion_id, array(
            'status'      => 'approved',
            'reviewed_by' => $this->session->userdata('admin_id'),
            'reviewed_at' => date('Y-m-d H:i:s'),
        ));

        echo json_encode(array('success' => (bool) $result));
    }

    public function reject_suggestion() {
        header('Content-Type: application/json');
        $suggestion_id = (int) $this->input->post('suggestion_id');

        if (!$suggestion_id) {
            echo json_encode(array('success' => false, 'message' => 'ID inválido.'));
            return;
        }

        $result = $this->Ai_model->update_field_suggestion($suggestion_id, array(
            'status'      => 'rejected',
            'reviewed_by' => $this->session->userdata('admin_id'),
            'reviewed_at' => date('Y-m-d H:i:s'),
        ));

        echo json_encode(array('success' => (bool) $result));
    }

    // ============================================================
    // REFORMULAÇÃO DE PERGUNTAS
    // ============================================================

    public function reformulations() {
        $data['title'] = 'Reformulação de Perguntas - SXData';
        $data['questionnaires'] = $this->Questionnaire_model->get_active();
        $data['is_enabled']     = $this->Ai_model->is_feature_enabled('question_reformulation');

        $questionnaire_id = (int) $this->input->get('questionnaire_id');
        $data['selected_questionnaire_id'] = $questionnaire_id;
        $data['reformulations'] = $questionnaire_id
            ? $this->Ai_model->get_reformulations_by_questionnaire($questionnaire_id)
            : array();

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/reformulations', $data);
        $this->load->view('admin/footer');
    }

    public function generate_reformulation() {
        $question_id = $this->input->post('question_id');
        $objective = $this->input->post('objective') ?: 'clareza';

        $context = $this->ai_prompt_service->prepare_reformulation_data($question_id, $objective);
        if (!$context) {
            header('Content-Type: application/json');
            echo json_encode(array('success' => false, 'error' => 'Pergunta não encontrada'));
            return;
        }

        $messages_result = $this->ai_prompt_service->build_messages('question_reformulation', $context);
        if (!$messages_result['success']) {
            header('Content-Type: application/json');
            echo json_encode($messages_result);
            return;
        }

        $result = $this->ai_service->chat_completion_json('question_reformulation', $messages_result['messages'], array(
            'prompt_id' => $messages_result['prompt_id'],
            'resource_type' => 'question',
            'resource_id' => $question_id,
        ));

        if ($result['success'] && $result['parsed']) {
            $parsed = $result['parsed'];
            $this->Ai_model->create_reformulation(array(
                'question_id' => $question_id,
                'original_text' => $context['original_question'],
                'reformulated_text' => $parsed['reformulated_text'] ?? '',
                'objective' => $objective,
                'execution_log_id' => $result['log_id'],
            ));
        }

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    public function approve_reformulation() {
        header('Content-Type: application/json');

        $reformulation_id = (int) $this->input->post('reformulation_id');
        $action           = $this->input->post('action'); // 'approved' ou 'rejected'

        if (!$reformulation_id || !in_array($action, array('approved', 'rejected'))) {
            echo json_encode(array('success' => false, 'message' => 'Parâmetros inválidos.'));
            return;
        }

        $reformulation = $this->Ai_model->get_reformulation($reformulation_id);
        if (!$reformulation) {
            echo json_encode(array('success' => false, 'message' => 'Reformulação não encontrada.'));
            return;
        }

        $this->Ai_model->update_reformulation($reformulation_id, array(
            'status'      => $action,
            'approved_by' => $this->session->userdata('admin_id'),
            'approved_at' => date('Y-m-d H:i:s'),
        ));

        // Ao aprovar: atualiza o texto da pergunta original
        if ($action === 'approved') {
            $this->load->model('Question_model');
            $this->Question_model->update($reformulation->question_id, array(
                'question_text' => $reformulation->reformulated_text,
            ));
        }

        echo json_encode(array('success' => true, 'action' => $action));
    }

    public function generate_reformulations_batch() {
        header('Content-Type: application/json');

        $questionnaire_id = (int) $this->input->post('questionnaire_id');
        $objective        = $this->input->post('objective') ?: 'clareza';

        if (!$questionnaire_id) {
            echo json_encode(array('success' => false, 'message' => 'Selecione um questionário.'));
            return;
        }

        $this->load->model('Question_model');
        $questions = $this->Question_model->get_by_questionnaire($questionnaire_id);

        if (empty($questions)) {
            echo json_encode(array('success' => false, 'message' => 'Nenhuma pergunta encontrada.'));
            return;
        }

        $generated = 0;
        $errors    = 0;

        foreach ($questions as $question) {
            $q_id    = is_array($question) ? $question['id'] : $question->id;
            $context = $this->ai_prompt_service->prepare_reformulation_data($q_id, $objective);
            if (!$context) { $errors++; continue; }

            $messages_result = $this->ai_prompt_service->build_messages('question_reformulation', $context);
            if (!$messages_result['success']) { $errors++; continue; }

            $result = $this->ai_service->chat_completion_json('question_reformulation', $messages_result['messages'], array(
                'prompt_id'     => $messages_result['prompt_id'],
                'resource_type' => 'question',
                'resource_id'   => $q_id,
            ));

            if ($result['success'] && !empty($result['parsed']['reformulated_text'])) {
                $this->Ai_model->create_reformulation(array(
                    'question_id'       => $q_id,
                    'original_text'     => $context['original_question'],
                    'reformulated_text' => $result['parsed']['reformulated_text'],
                    'objective'         => $objective,
                    'execution_log_id'  => $result['log_id'],
                ));
                $generated++;
            } else {
                $errors++;
            }
        }

        echo json_encode(array(
            'success'   => $generated > 0,
            'generated' => $generated,
            'errors'    => $errors,
            'message'   => "{$generated} reformulação(ões) gerada(s)." . ($errors > 0 ? " {$errors} falha(s)." : ''),
        ));
    }

    // ============================================================
    // QUESTIONÁRIO ADAPTATIVO
    // ============================================================

    public function adaptive() {
        $data['title'] = 'Questionário Adaptativo - SXData';
        $data['questionnaires'] = $this->Questionnaire_model->get_active();
        $data['is_enabled'] = $this->Ai_model->is_feature_enabled('adaptive_routing');

        $questionnaire_id = (int) $this->input->get('questionnaire_id');
        $data['selected_questionnaire_id'] = $questionnaire_id;

        if ($questionnaire_id) {
            $data['rules']     = $this->Ai_model->get_adaptive_rules($questionnaire_id);
            $this->load->model('Question_model');
            $data['questions'] = $this->Question_model->get_by_questionnaire($questionnaire_id);
        } else {
            $data['rules']     = array();
            $data['questions'] = array();
        }

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/adaptive', $data);
        $this->load->view('admin/footer');
    }

    public function clear_adaptive_rules() {
        header('Content-Type: application/json');
        $questionnaire_id = $this->input->post('questionnaire_id');
        if (!$questionnaire_id) {
            echo json_encode(array('success' => false, 'message' => 'Questionário não informado.'));
            return;
        }
        $this->db->where('questionnaire_id', $questionnaire_id)->delete('ai_adaptive_rules');
        echo json_encode(array('success' => true));
    }

    public function generate_adaptive_rules() {
        ob_start();
        $questionnaire_id = $this->input->post('questionnaire_id');

        if (!$questionnaire_id) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(array('success' => false, 'message' => 'Selecione um questionário.'));
            return;
        }

        $context = $this->ai_prompt_service->prepare_adaptive_data($questionnaire_id);
        $messages_result = $this->ai_prompt_service->build_messages('adaptive_routing', $context);

        if (!$messages_result['success']) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode($messages_result);
            return;
        }

        $result = $this->ai_service->chat_completion_json('adaptive_routing', $messages_result['messages'], array(
            'prompt_id'     => $messages_result['prompt_id'],
            'resource_type' => 'questionnaire',
            'resource_id'   => $questionnaire_id,
        ));

        ob_end_clean();

        if (!$result['success']) {
            header('Content-Type: application/json');
            echo json_encode($result);
            return;
        }

        $rules = $result['parsed'] ?? [];
        if (isset($rules['rules']) && is_array($rules['rules'])) {
            $rules = $rules['rules'];
        }
        if (!is_array($rules)) {
            $rules = [];
        }

        $count = 0;
        foreach ($rules as $rule) {
            if (empty($rule['source_question_id']) || empty($rule['target_question_id'])) continue;
            $this->Ai_model->create_adaptive_rule(array(
                'questionnaire_id'   => $questionnaire_id,
                'source_question_id' => $rule['source_question_id'],
                'target_question_id' => $rule['target_question_id'],
                'condition_logic'    => is_array($rule['condition_logic'] ?? null)
                                        ? json_encode($rule['condition_logic'])
                                        : ($rule['condition_logic'] ?? '{}'),
                'fallback_target_id' => $rule['fallback_target_id'] ?? null,
                'priority'           => $rule['priority'] ?? $count,
                'ai_generated'       => true,
                'is_active'          => true,
                'execution_log_id'   => $result['log_id'],
            ));
            $count++;
        }

        header('Content-Type: application/json');
        echo json_encode(array('success' => true, 'count' => $count,
            'message' => "{$count} regra(s) gerada(s) com sucesso."));
    }

    public function approve_adaptive_rule() {
        header('Content-Type: application/json');
        $id = $this->input->post('id');
        $this->load->model('Question_model');

        // Buscar a regra
        $rule = $this->db->where('id', $id)->get('ai_adaptive_rules')->row_array();
        if (!$rule) {
            echo json_encode(array('success' => false, 'message' => 'Regra não encontrada.'));
            return;
        }

        $source_id = (int) $rule['source_question_id'];
        $target_id = (int) $rule['target_question_id'];
        $condition_raw = json_decode($rule['condition_logic'], true);

        // Determinar operador e valor da condição
        $operator = 'equals';
        $value = '';
        if (is_array($condition_raw)) {
            $operator = $condition_raw['operator'] ?? 'equals';
            $value = $condition_raw['value'] ?? '';
        }

        // Buscar lógica condicional existente da pergunta destino
        $target_question = $this->db->select('id, conditional_logic')->where('id', $target_id)->get('questions')->row();
        if (!$target_question) {
            echo json_encode(array('success' => false, 'message' => 'Pergunta destino não encontrada.'));
            return;
        }

        $existing_logic = array();
        if (!empty($target_question->conditional_logic)) {
            $existing_logic = json_decode($target_question->conditional_logic, true);
            if (!is_array($existing_logic)) $existing_logic = array();
        }

        // Nova condição de visibilidade
        $new_condition = array(
            'question' => $source_id,
            'operator' => $operator,
            'value' => $value,
        );

        // Verificar se já existe a mesma condição para evitar duplicidade
        if (!empty($existing_logic['visibility']['conditions'])) {
            foreach ($existing_logic['visibility']['conditions'] as $c) {
                if ((int)($c['question'] ?? 0) === $source_id
                    && ($c['operator'] ?? '') === $operator
                    && ($c['value'] ?? '') === $value) {
                    // Já existe, apenas marcar como aprovada
                    $this->Ai_model->update_adaptive_rule($id, array(
                        'approved_by' => $this->session->userdata('admin_id'),
                        'approved_at' => date('Y-m-d H:i:s'),
                    ));
                    echo json_encode(array('success' => true, 'message' => 'Regra aprovada. Condição já existia no questionário.'));
                    return;
                }
            }
        }

        // Montar/mesclar a lógica de visibilidade
        if (empty($existing_logic['visibility'])) {
            $existing_logic['visibility'] = array(
                'operator' => 'AND',
                'conditions' => array($new_condition),
            );
        } else {
            $existing_logic['visibility']['conditions'][] = $new_condition;
        }

        // Atualizar a pergunta com a nova lógica condicional
        $this->Question_model->update($target_id, array(
            'conditional_logic' => json_encode($existing_logic, JSON_UNESCAPED_UNICODE),
        ));

        // Marcar regra como aprovada
        $this->Ai_model->update_adaptive_rule($id, array(
            'approved_by' => $this->session->userdata('admin_id'),
            'approved_at' => date('Y-m-d H:i:s'),
        ));

        echo json_encode(array(
            'success' => true,
            'message' => 'Regra aprovada! Lógica condicional aplicada: Q#' . $source_id . ' ' . $operator . ' "' . $value . '" → exibe Q#' . $target_id,
        ));
    }

    public function reject_adaptive_rule() {
        header('Content-Type: application/json');
        $id = $this->input->post('id');
        $this->Ai_model->update_adaptive_rule($id, array(
            'is_active' => false,
            'approved_by' => $this->session->userdata('admin_id'),
            'approved_at' => date('Y-m-d H:i:s'),
        ));
        echo json_encode(array('success' => true));
    }

    public function restore_adaptive_rule() {
        header('Content-Type: application/json');
        $id = $this->input->post('id');
        $this->Ai_model->update_adaptive_rule($id, array(
            'is_active' => true,
            'approved_by' => null,
            'approved_at' => null,
        ));
        echo json_encode(array('success' => true));
    }

    // ============================================================
    // SUGESTÕES DE FOLLOW-UP
    // ============================================================

    public function followup() {
        $data['title'] = 'Sugestões de Dicas - SXData';
        $data['suggestions'] = $this->Ai_model->get_followup_suggestions();
        $data['questionnaires'] = $this->Questionnaire_model->get_active();
        $data['is_enabled'] = $this->Ai_model->is_feature_enabled('followup_suggestions');

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/followup', $data);
        $this->load->view('admin/footer');
    }

    public function generate_followup() {
        $questionnaire_id = $this->input->post('questionnaire_id');

        $context = $this->ai_prompt_service->prepare_followup_data($questionnaire_id);
        $messages_result = $this->ai_prompt_service->build_messages('followup_suggestions', $context);

        if (!$messages_result['success']) {
            header('Content-Type: application/json');
            echo json_encode($messages_result);
            return;
        }

        $result = $this->ai_service->chat_completion_json('followup_suggestions', $messages_result['messages'], array(
            'prompt_id' => $messages_result['prompt_id'],
            'resource_type' => 'questionnaire',
            'resource_id' => $questionnaire_id,
        ));

        if ($result['success'] && $result['parsed']) {
            $items = is_array($result['parsed']) && isset($result['parsed'][0]) ? $result['parsed'] : array($result['parsed']);

            foreach ($items as $item) {
                $this->Ai_model->create_followup_suggestion(array(
                    'questionnaire_id' => $questionnaire_id,
                    'question_id' => !empty($item['question_id']) ? (int)$item['question_id'] : null,
                    'suggested_question_text' => $item['tip'] ?? $item['question_text'] ?? '',
                    'question_type' => $item['question_type'] ?? 'text',
                    'suggested_options' => json_encode($item['options'] ?? array()),
                    'rationale' => $item['rationale'] ?? '',
                    'execution_log_id' => $result['log_id'],
                ));
            }
        }

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    public function action_followup() {
        header('Content-Type: application/json');
        $id = $this->input->post('suggestion_id');
        $action = $this->input->post('action');

        $suggestion = $this->db->where('id', $id)->get('ai_followup_suggestions')->row();
        if (!$suggestion) {
            echo json_encode(array('success' => false, 'message' => 'Sugestão não encontrada.'));
            return;
        }

        if ($action === 'approve') {
            // Marcar como aprovada
            $this->Ai_model->update_followup_suggestion($id, array(
                'status' => 'approved',
                'reviewed_by' => $this->session->userdata('admin_id'),
                'reviewed_at' => date('Y-m-d H:i:s'),
            ));

            // Inserir como tip na pergunta (se tem question_id)
            if (!empty($suggestion->question_id)) {
                $exists = $this->db->where('question_id', $suggestion->question_id)
                                   ->where('tip', $suggestion->suggested_question_text)
                                   ->count_all_results('question_followup_tips');

                if ($exists == 0) {
                    $this->db->insert('question_followup_tips', array(
                        'question_id'      => $suggestion->question_id,
                        'tip'              => $suggestion->suggested_question_text,
                        'source'           => 'ai_approved',
                        'ai_suggestion_id' => $id,
                        'created_by'       => $this->session->userdata('admin_id'),
                    ));
                }
            }

            echo json_encode(array('success' => true, 'message' => 'Sugestão aprovada e adicionada como dica.'));
        } elseif ($action === 'discard') {
            $this->Ai_model->update_followup_suggestion($id, array(
                'status' => 'discarded',
                'reviewed_by' => $this->session->userdata('admin_id'),
                'reviewed_at' => date('Y-m-d H:i:s'),
            ));
            echo json_encode(array('success' => true));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Ação inválida.'));
        }
    }

    public function edit_followup() {
        header('Content-Type: application/json');
        $id = $this->input->post('suggestion_id');
        $text = $this->input->post('question_text');

        if (empty($text)) {
            echo json_encode(array('success' => false, 'message' => 'Texto é obrigatório.'));
            return;
        }

        $this->Ai_model->update_followup_suggestion($id, array(
            'suggested_question_text' => trim($text),
            'status' => 'edited',
            'reviewed_by' => $this->session->userdata('admin_id'),
            'reviewed_at' => date('Y-m-d H:i:s'),
        ));
        echo json_encode(array('success' => true));
    }

    public function clear_followups() {
        header('Content-Type: application/json');
        $this->db->truncate('ai_followup_suggestions');
        echo json_encode(array('success' => true));
    }

    // ============================================================
    // ANÁLISE ESTATÍSTICA
    // ============================================================

    public function analysis() {
        $data['title'] = 'Análise Estatística com IA - SXData';
        $data['analyses'] = $this->Ai_model->get_statistical_analyses();
        $data['questionnaires'] = $this->Questionnaire_model->get_active();
        $data['is_enabled'] = $this->Ai_model->is_feature_enabled('statistical_analysis');

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/analysis', $data);
        $this->load->view('admin/footer');
    }

    public function generate_analysis() {
        // Buffer para capturar eventuais notices PHP antes de enviar JSON limpo
        ob_start();

        $questionnaire_id = $this->input->post('questionnaire_id');
        $filters = array(
            'date_from' => $this->input->post('date_from'),
            'date_to'   => $this->input->post('date_to'),
        );

        $context = $this->ai_prompt_service->prepare_statistical_data($questionnaire_id, $filters);

        if ((int) $context['total_responses'] === 0) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(array('success' => false, 'error' => 'Não há entrevistas concluídas neste questionário (ou no período escolhido) para analisar.'));
            return;
        }

        // Formato da resposta definido no código (ver build_statistical_messages)
        $messages = $this->ai_prompt_service->build_statistical_messages($context);

        $result = $this->ai_service->chat_completion_json('statistical_analysis', $messages, array(
            'resource_type' => 'questionnaire',
            'resource_id'   => $questionnaire_id,
        ));

        $analysis_id = null;

        if ($result['success'] && $result['parsed']) {
            $analise = $this->_montar_analise($result['parsed'], $context);

            $analysis_id = $this->Ai_model->create_statistical_analysis(array(
                'questionnaire_id'  => $questionnaire_id,
                'analysis_type'     => 'general',
                'filters_applied'   => json_encode($filters),
                // versao 2: as telas mostram resumo, destaques, pontos de
                // atenção e recomendações; as análises antigas seguem iguais
                'summary_text'      => json_encode($analise['resumo'], JSON_UNESCAPED_UNICODE),
                'patterns'          => json_encode(array()),
                'outliers'          => json_encode($analise['resumo']['pontos_de_atencao'], JSON_UNESCAPED_UNICODE),
                'trends'            => json_encode($analise['resumo']['recomendacoes'], JSON_UNESCAPED_UNICODE),
                'insights'          => json_encode($analise['resumo']['destaques'], JSON_UNESCAPED_UNICODE),
                'chart_suggestions' => json_encode($analise['graficos'], JSON_UNESCAPED_UNICODE),
                'generated_by'      => $this->session->userdata('admin_id'),
                'execution_log_id'  => $result['log_id'],
            ));

            $result['analysis_id'] = $analysis_id;
        }

        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Junta o texto da IA aos números do banco.
     *
     * Os gráficos são montados aqui com a contagem real (antes eram
     * "sugestões" da IA, muitas vezes sem dados: "Dados insuficientes"). A
     * análise de cada pergunta escrita pela IA vira a descrição do gráfico
     * dela, guardada junto, sem nova chamada à OpenAI a cada visualização.
     */
    private function _montar_analise($parsed, $context) {
        $lista = function ($v) {
            if (!is_array($v)) {
                return array();
            }
            $out = array();
            foreach ($v as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $out[] = trim($item);
                } elseif (is_array($item)) {
                    $partes = array_filter(array_map(function ($x) { return is_scalar($x) ? trim((string) $x) : ''; }, $item));
                    if ($partes) {
                        $out[] = implode(' — ', $partes);
                    }
                }
            }
            return $out;
        };

        // Análise por pergunta, indexada pelo número (P1, P2...)
        $por_pergunta = array();
        foreach ((is_array($parsed['perguntas'] ?? null) ? $parsed['perguntas'] : array()) as $item) {
            if (is_array($item) && isset($item['numero']) && !empty($item['analise'])) {
                $num = (int) preg_replace('/\D/', '', (string) $item['numero']);
                $por_pergunta[$num] = trim((string) $item['analise']);
            }
        }

        $graficos = array();
        $sem_grafico = array();

        // Evolução da coleta, quando há mais de um dia
        $por_dia = $context['por_dia'] ?? array();
        if (count($por_dia) > 1) {
            $maior = array_reduce($por_dia, function ($a, $d) { return ($a === null || $d['total'] > $a['total']) ? $d : $a; });
            $graficos[] = array(
                'chart_type' => 'line',
                'title'      => 'Entrevistas por dia',
                'labels'     => array_map(function ($d) { return date('d/m', strtotime($d['dia'])); }, $por_dia),
                'data'       => array_map(function ($d) { return (int) $d['total']; }, $por_dia),
                'descricao'  => 'Coleta de ' . date('d/m', strtotime($por_dia[0]['dia'])) . ' a '
                    . date('d/m', strtotime(end($por_dia)['dia'])) . '. O dia com mais entrevistas foi '
                    . date('d/m', strtotime($maior['dia'])) . ', com ' . $maior['total'] . '.',
            );
        }

        foreach (($context['perguntas'] ?? array()) as $num => $p) {
            $dados = $p['dados'];
            $texto_ia = $por_pergunta[$num] ?? '';

            if ($p['tipo'] === 'fechada' && !empty($dados['linhas'])) {
                $tipo = $dados['question_type'] === 'checkbox' || count($dados['linhas']) > 6 ? 'bar' : 'doughnut';
                $graficos[] = array(
                    'chart_type' => $tipo,
                    'title'      => $dados['question_text'],
                    'labels'     => array_map(function ($l) { return (string) $l['rotulo']; }, $dados['linhas']),
                    'data'       => array_map(function ($l) { return (int) $l['total']; }, $dados['linhas']),
                    'total'      => (int) $dados['total'],
                    'descricao'  => $texto_ia,
                );
            } elseif ($texto_ia !== '') {
                $detalhe = '';
                if ($p['tipo'] === 'fechada' && !empty($dados['resumo'])) {
                    $detalhe = 'Média ' . number_format($dados['resumo']['media'], 2, ',', '')
                        . ' (mín. ' . $dados['resumo']['minimo'] . ', máx. ' . $dados['resumo']['maximo'] . ')';
                }
                $sem_grafico[] = array(
                    'pergunta' => $dados['question_text'],
                    'detalhe'  => $detalhe,
                    'analise'  => $texto_ia,
                );
            }
        }

        $visao = $context['visao_geral'] ?? array();

        return array(
            'resumo' => array(
                'versao'            => 2,
                'periodo'           => $context['date_range'] ?? '',
                'visao_geral'       => array(
                    'entrevistas' => (int) ($visao['total_responses'] ?? 0),
                    'aplicadores' => (int) ($visao['total_applicators'] ?? 0),
                    'locais'      => (int) ($visao['total_locations'] ?? 0),
                ),
                'resumo'            => is_string($parsed['resumo'] ?? null) ? trim($parsed['resumo']) : '',
                'destaques'         => $lista($parsed['destaques'] ?? null),
                'pontos_de_atencao' => $lista($parsed['pontos_de_atencao'] ?? null),
                'recomendacoes'     => $lista($parsed['recomendacoes'] ?? null),
                'outras_perguntas'  => $sem_grafico,
            ),
            'graficos' => $graficos,
        );
    }

    public function delete_analysis() {
        $id = $this->input->post('id');
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            return;
        }
        $this->db->delete('ai_statistical_analyses', ['id' => $id]);
        echo json_encode(['success' => true]);
    }

    public function view_analysis($id) {
        $data['title'] = 'Análise Estatística - SXData';
        $data['analysis'] = $this->Ai_model->get_statistical_analysis($id);

        if (!$data['analysis']) {
            $this->session->set_flashdata('error', 'Análise não encontrada.');
            redirect('ai/analysis');
            return;
        }

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/analysis_view', $data);
        $this->load->view('admin/footer');
    }

    public function get_question_detail() {
        header('Content-Type: application/json');
        $id = $this->input->get('id');
        $this->load->model('Question_model');

        $question = $this->Question_model->get_by_id($id);
        if (!$question) {
            echo json_encode(array('success' => false, 'message' => 'Pergunta não encontrada.'));
            return;
        }

        $result = array(
            'id' => $question->id,
            'question_text' => $question->question_text,
            'question_type' => $question->question_type ?? 'text',
            'is_required' => $question->is_required ?? 0,
            'order_index' => $question->order_index ?? null,
        );

        // Buscar nome do questionário
        $questionnaire = $this->Questionnaire_model->get_by_id($question->questionnaire_id);
        if ($questionnaire) {
            $result['questionnaire_title'] = $questionnaire->title ?? $questionnaire->name ?? '';
        }

        // Buscar opções se houver
        if (in_array($question->question_type, array('radio', 'checkbox', 'select'))) {
            $options = $this->Question_model->get_options($question->id);
            $result['options'] = array();
            foreach ($options as $opt) {
                $result['options'][] = array(
                    'option_text' => $opt->option_text,
                    'option_value' => $opt->option_value ?? $opt->option_text,
                );
            }
        }

        echo json_encode(array('success' => true, 'question' => $result));
    }

    // ============================================================
    // GRÁFICOS INTELIGENTES
    // ============================================================

    public function charts() {
        $data['title'] = 'Gráficos Inteligentes - SXData';
        $data['questionnaires'] = $this->Questionnaire_model->get_active();
        $data['is_enabled'] = $this->Ai_model->is_feature_enabled('smart_charts');

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/charts', $data);
        $this->load->view('admin/footer');
    }

    public function generate_charts() {
        $questionnaire_id = $this->input->post('questionnaire_id');
        $filters = array(
            'date_from' => $this->input->post('date_from'),
            'date_to' => $this->input->post('date_to'),
        );

        $context = $this->ai_prompt_service->prepare_statistical_data($questionnaire_id, $filters);
        $messages_result = $this->ai_prompt_service->build_messages('smart_charts', $context);

        if (!$messages_result['success']) {
            header('Content-Type: application/json');
            echo json_encode($messages_result);
            return;
        }

        ob_start();
        $result = $this->ai_service->chat_completion_json('smart_charts', $messages_result['messages'], array(
            'prompt_id' => $messages_result['prompt_id'],
            'resource_type' => 'questionnaire',
            'resource_id' => $questionnaire_id,
        ));
        ob_end_clean();

        if (!$result['success']) {
            header('Content-Type: application/json');
            echo json_encode($result);
            return;
        }

        $charts = $result['parsed'] ?? [];
        // A IA pode retornar {charts: [...]} ou diretamente [...]
        if (isset($charts['charts']) && is_array($charts['charts'])) {
            $charts = $charts['charts'];
        }
        if (!is_array($charts)) {
            $charts = [];
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'charts' => $charts]);
    }

    // ============================================================
    // RELATÓRIOS EM LINGUAGEM NATURAL
    // ============================================================

    public function reports() {
        $data['title'] = 'Relatórios com IA - SXData';
        $data['reports'] = $this->Ai_model->get_reports();
        $data['questionnaires'] = $this->Questionnaire_model->get_active();
        $data['is_enabled'] = $this->Ai_model->is_feature_enabled('natural_reports');

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/reports', $data);
        $this->load->view('admin/footer');
    }

    public function generate_report() {
        $questionnaire_id = $this->input->post('questionnaire_id');
        $report_type = $this->input->post('report_type') ?: 'general';
        $filters = array(
            'date_from' => $this->input->post('date_from'),
            'date_to' => $this->input->post('date_to'),
        );

        $context = $this->ai_prompt_service->prepare_report_data($questionnaire_id, $filters);
        $messages_result = $this->ai_prompt_service->build_messages('natural_reports', $context);

        if (!$messages_result['success']) {
            header('Content-Type: application/json');
            echo json_encode($messages_result);
            return;
        }

        $result = $this->ai_service->chat_completion($messages_result['messages'][0]['content'] === '' ? 'natural_reports' : 'natural_reports', $messages_result['messages'], array(
            'prompt_id' => $messages_result['prompt_id'],
            'resource_type' => 'questionnaire',
            'resource_id' => $questionnaire_id,
        ));

        if ($result['success']) {
            $report_id = $this->Ai_model->create_report(array(
                'title' => 'Relatório - ' . ($context['questionnaire_title'] ?? 'Geral') . ' - ' . date('d/m/Y'),
                'questionnaire_id' => $questionnaire_id,
                'report_type' => $report_type,
                'filters_applied' => json_encode($filters),
                'narrative_text' => $result['content'],
                'generated_by' => $this->session->userdata('admin_id'),
                'execution_log_id' => $result['log_id'],
                'status' => 'completed',
            ));

            $result['report_id'] = $report_id;
        }

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    public function view_report($id) {
        $data['title'] = 'Relatório - SXData';
        $data['report'] = $this->Ai_model->get_report($id);

        if (!$data['report']) {
            $this->session->set_flashdata('error', 'Relatório não encontrado.');
            redirect('ai/reports');
            return;
        }

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/report_view', $data);
        $this->load->view('admin/footer');
    }

    public function get_report_data() {
        header('Content-Type: application/json');
        $id = $this->input->get('id');
        $report = $this->Ai_model->get_report($id);
        if ($report) {
            echo json_encode(array('success' => true, 'narrative_text' => $report->narrative_text ?? ''));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Relatório não encontrado.'));
        }
    }

    public function update_report() {
        header('Content-Type: application/json');
        $id = $this->input->post('id');
        $data = array('updated_at' => date('Y-m-d H:i:s'));

        $title = $this->input->post('title');
        if ($title) $data['title'] = $title;

        $narrative = $this->input->post('narrative_text');
        if ($narrative !== null && $narrative !== '') $data['narrative_text'] = $narrative;

        $this->db->where('id', $id)->update('ai_reports', $data);
        echo json_encode(array('success' => true));
    }

    // ============================================================
    // PROCESSAMENTO EM LOTE
    // ============================================================

    public function batch_analyze() {
        header('Content-Type: application/json');

        $type = $this->input->post('type'); // inconsistencies, corrections
        $questionnaire_id = $this->input->post('questionnaire_id');

        if (!$questionnaire_id) {
            echo json_encode(array('success' => false, 'message' => 'Selecione um questionário.'));
            return;
        }

        $feature = $type === 'inconsistencies' ? 'inconsistency_detection' : 'data_correction';

        if (!$this->Ai_model->is_feature_enabled($feature)) {
            $name = $type === 'inconsistencies' ? 'Detecção de Inconsistências' : 'Correção e Padronização';
            echo json_encode(array('success' => false, 'message' => "A funcionalidade \"{$name}\" está desabilitada. Ative-a em Configurações de IA."));
            return;
        }

        $filters = array('questionnaire_id' => $questionnaire_id);
        $responses = $this->Response_model->get_filtered($filters);

        if (empty($responses)) {
            echo json_encode(array('success' => false, 'message' => 'Nenhuma resposta encontrada para este questionário.'));
            return;
        }

        $results = array('processed' => 0, 'errors' => 0, 'total' => count($responses), 'count' => 0);

        foreach ($responses as $response) {
            if ($type === 'inconsistencies') {
                $context = $this->ai_prompt_service->prepare_inconsistency_data($response->id);
            } else {
                $context = $this->ai_prompt_service->prepare_correction_data($response->id);
            }

            if (!$context) {
                $results['errors']++;
                continue;
            }

            $messages_result = $this->ai_prompt_service->build_messages($feature, $context);

            if (!$messages_result['success']) {
                $results['errors']++;
                continue;
            }

            $result = $this->ai_service->chat_completion_json($feature, $messages_result['messages'], array(
                'prompt_id' => $messages_result['prompt_id'],
                'resource_type' => 'form_response',
                'resource_id' => $response->id,
            ));

            if ($result['success'] && $result['parsed']) {
                $items = is_array($result['parsed']) && isset($result['parsed'][0]) ? $result['parsed'] : array($result['parsed']);

                foreach ($items as $item) {
                    // Validação por tipo: inconsistências exigem 'description', correções exigem 'suggested_value'
                    if ($type === 'inconsistencies' && empty($item['description'])) continue;
                    if ($type !== 'inconsistencies' && empty($item['suggested_value'])) continue;

                    // Ignora correções onde o valor sugerido é idêntico ao original
                    if ($type !== 'inconsistencies' && isset($item['original_value']) && isset($item['suggested_value'])) {
                        if (trim((string)$item['original_value']) === trim((string)$item['suggested_value'])) continue;
                    }

                    if ($type === 'inconsistencies') {
                        $this->Ai_model->create_inconsistency(array(
                            'form_response_id' => $response->id,
                            'questionnaire_id' => $questionnaire_id,
                            'inconsistency_type' => isset($item['inconsistency_type']) ? $item['inconsistency_type'] : 'logic',
                            'severity' => isset($item['severity']) ? $item['severity'] : 'medium',
                            'consistency_score' => isset($item['consistency_score']) ? $item['consistency_score'] : null,
                            'description' => isset($item['description']) ? $item['description'] : '',
                            'ai_justification' => isset($item['description']) ? $item['description'] : '',
                            'affected_questions' => json_encode(isset($item['affected_questions']) ? $item['affected_questions'] : array()),
                            'suggested_action' => isset($item['suggested_action']) ? $item['suggested_action'] : '',
                            'execution_log_id' => $result['log_id'],
                        ));
                        $results['count']++;
                    } else {
                        $this->Ai_model->create_correction(array(
                            'form_response_id' => $response->id,
                            'question_id' => isset($item['question_id']) ? $item['question_id'] : null,
                            'original_value' => isset($item['original_value']) ? $item['original_value'] : '',
                            'suggested_value' => isset($item['suggested_value']) ? $item['suggested_value'] : '',
                            'correction_type' => isset($item['correction_type']) ? $item['correction_type'] : 'general',
                            'confidence_score' => isset($item['confidence']) ? $item['confidence'] : null,
                            'execution_log_id' => $result['log_id'],
                        ));
                        $results['count']++;
                    }
                }

                $results['processed']++;
            } else {
                $results['errors']++;
            }

            // Pausa entre chamadas para evitar rate limiting da OpenAI
            usleep(500000); // 0.5s
        }

        echo json_encode(array(
            'success' => true,
            'message' => "Análise em lote concluída. {$results['processed']}/{$results['total']} respostas processadas.",
            'count' => $results['count'],
            'results' => $results,
        ));
    }

    // ============================================================
    // DIAGNÓSTICO (remover após debug)
    // ============================================================

    public function debug_analyze() {
        header('Content-Type: application/json');
        $steps = array();

        try {
            $steps[] = 'Step 1: Controller OK';

            $form_response_id = $this->input->post('form_response_id');
            $steps[] = 'Step 2: form_response_id = ' . var_export($form_response_id, true);

            $enabled = $this->Ai_model->is_feature_enabled('inconsistency_detection');
            $steps[] = 'Step 3: feature_enabled = ' . var_export($enabled, true);

            $configured = $this->ai_service->is_configured();
            $steps[] = 'Step 4: api_configured = ' . var_export($configured, true);

            if ($form_response_id) {
                $context = $this->ai_prompt_service->prepare_inconsistency_data($form_response_id);
                $steps[] = 'Step 5: context = ' . ($context ? 'OK (questionnaire: ' . $context['questionnaire_id'] . ')' : 'NULL - resposta não encontrada');

                if ($context) {
                    $messages_result = $this->ai_prompt_service->build_messages('inconsistency_detection', $context);
                    $steps[] = 'Step 6: build_messages = ' . ($messages_result['success'] ? 'OK' : 'FAIL: ' . (isset($messages_result['error']) ? $messages_result['error'] : 'unknown'));

                    if ($messages_result['success'] && $enabled && $configured) {
                        $test_ai = $this->input->post('test_ai');
                        if ($test_ai) {
                            $result = $this->ai_service->chat_completion_json('inconsistency_detection', $messages_result['messages'], array(
                                'prompt_id' => $messages_result['prompt_id'],
                                'resource_type' => 'form_response',
                                'resource_id' => $form_response_id,
                            ));
                            $steps[] = 'Step 7: OpenAI result success=' . var_export($result['success'], true);
                            if (!$result['success']) {
                                $steps[] = 'Step 7 ERROR: ' . (isset($result['error']) ? $result['error'] : 'unknown');
                            } else {
                                $steps[] = 'Step 7 parsed=' . var_export(isset($result['parsed']) && $result['parsed'] !== null, true);
                                $steps[] = 'Step 7 tokens=' . (isset($result['tokens_input']) ? $result['tokens_input'] : 0) . '+' . (isset($result['tokens_output']) ? $result['tokens_output'] : 0);
                            }
                        } else {
                            $steps[] = 'Step 7: Pronto para OpenAI. Envie test_ai=1 para testar a chamada real.';
                        }
                    }
                }
            }

            echo json_encode(array('success' => true, 'steps' => $steps));

        } catch (Exception $e) {
            $steps[] = 'EXCEPTION: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine();
            echo json_encode(array('success' => false, 'steps' => $steps));
        }
    }

    // ============================================================
    // DIRETRIZES DA IA
    // ============================================================

    public function security() {
        $data['title'] = 'Segurança e Privacidade - IA SXData';
        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/security', $data);
        $this->load->view('admin/footer');
    }

    public function directives() {
        $data['title'] = 'Diretrizes da IA - SXData';
        $data['directives'] = $this->Ai_model->get_directives();

        $this->load->view('admin/header', $data);
        $this->load->view('admin/ai/directives', $data);
        $this->load->view('admin/footer');
    }

    public function save_directive() {
        header('Content-Type: application/json');
        $id = $this->input->post('id');

        $data = array(
            'title' => $this->input->post('title'),
            'content' => $this->input->post('content'),
            'directive_type' => $this->input->post('directive_type'),
            'category' => $this->input->post('category'),
            'priority' => (int) $this->input->post('priority'),
            'is_active' => (int) $this->input->post('is_active'),
            'applies_to' => $this->input->post('applies_to'),
            'updated_at' => date('Y-m-d H:i:s'),
        );

        if ($id) {
            $this->db->where('id', $id)->update('ai_directives', $data);
        } else {
            $data['created_by'] = $this->session->userdata('admin_id');
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('ai_directives', $data);
        }

        echo json_encode(array('success' => true));
    }

    public function get_directive() {
        header('Content-Type: application/json');
        $id = $this->input->get('id');
        $directive = $this->db->where('id', $id)->get('ai_directives')->row();
        if ($directive) {
            echo json_encode(array('success' => true, 'directive' => $directive));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Diretriz não encontrada.'));
        }
    }

    public function toggle_directive() {
        header('Content-Type: application/json');
        $id = $this->input->post('id');
        $active = (int) $this->input->post('is_active');
        $this->db->where('id', $id)->update('ai_directives', array(
            'is_active' => $active,
            'updated_at' => date('Y-m-d H:i:s'),
        ));
        echo json_encode(array('success' => true));
    }

    public function delete_directive() {
        header('Content-Type: application/json');
        $id = $this->input->post('id');
        $this->db->where('id', $id)->delete('ai_directives');
        echo json_encode(array('success' => true));
    }

    public function clear_directives() {
        header('Content-Type: application/json');
        $this->db->truncate('ai_directives');
        echo json_encode(array('success' => true));
    }

    // ============================================================
    // AUTH
    // ============================================================

    private function check_auth() {
        if (!$this->session->userdata('admin_logged_in')) {
            redirect('auth/login');
        }

        // Cliente não acessa telas administrativas, nem pela URL
        exigir_equipe();
    }
}
