<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Recebe as coletas do app.
 *
 * O id devolvido em caso de sucesso e a PROVA de gravacao para o app: so
 * depois de recebe-lo a coleta sai da fila do aparelho. Por isso ele so e
 * devolvido apos o commit, e nunca em caso de erro.
 *
 * Uma mesma coleta pode chegar mais de uma vez (reenvio apos resposta
 * perdida) ou chegar editada. Nos dois casos ela e reconhecida e o registro
 * existente e reaproveitado, em vez de criar um novo. Antes, todo envio era
 * um INSERT: cada edicao duplicava a entrevista no painel.
 */
class Forms extends CI_Controller {

    /** Ids locais do app sao timestamps (~10^12); ids do banco cabem em INTEGER */
    const MAX_SERVER_ID = 2147483647;

    /** @var array cache de colunas opcionais (dependem da migracao) */
    private $columns = array();

    public function __construct() {
        parent::__construct();
        $this->load->model('Response_model');
        $this->load->model('Questionnaire_model');
        $this->load->library('api_token');
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($this->input->method() === 'options') {
            exit();
        }
    }

    public function submit() {
        $this->register_fatal_json_handler();

        if ($this->input->method() !== 'post') {
            $this->output->set_status_header(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $user_id = $this->verify_auth();
        if (!$user_id) {
            return;
        }

        $raw = file_get_contents('php://input');

        // Corpo maior que post_max_size: o PHP entrega vazio. Sem distinguir
        // esse caso, o app recebia "Invalid JSON" e o motivo ficava invisivel.
        $declared_len = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        if ($raw === '' && $declared_len > 0) {
            log_message('error', 'forms/submit - corpo vazio com Content-Length=' . $declared_len
                . '. Provavel post_max_size (' . ini_get('post_max_size') . ') excedido.');
            $this->output->set_status_header(413);
            echo json_encode([
                'success' => false,
                'message' => 'Payload excede o limite do servidor (post_max_size=' . ini_get('post_max_size') . ').',
                'limit_exceeded' => true
            ]);
            return;
        }

        $json = json_decode($raw, true);

        if (!is_array($json)) {
            log_message('error', 'forms/submit - JSON invalido. bytes=' . strlen($raw)
                . ' json_error=' . json_last_error_msg());
            $this->output->set_status_header(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON data',
                'json_error' => json_last_error_msg()
            ]);
            return;
        }

        foreach (['questionnaire_id', 'applied_by', 'responses'] as $field) {
            if (!isset($json[$field])) {
                $this->output->set_status_header(400);
                echo json_encode(['success' => false, 'message' => "Field '{$field}' is required"]);
                return;
            }
        }

        $client_id  = $this->clean_client_id($json['client_id'] ?? null);
        $edited_at  = $this->to_datetime($json['edited_at'] ?? null);
        $started_at = $this->to_datetime($json['started_at'] ?? null);

        try {
            // Transacao manual: com trans_start/trans_complete o rollback do
            // catch nao tem efeito, e o id precisa sair so apos commit real.
            $this->db->trans_begin();

            $existing = $this->find_existing($json, $client_id, $started_at, $user_id);

            if ($existing) {
                $is_newer_edit = $edited_at !== null
                    && (empty($existing->edited_at) || strtotime($edited_at) > strtotime($existing->edited_at));

                if (!$is_newer_edit) {
                    // Reenvio de algo que ja esta gravado: nada a fazer, so
                    // apontar para a midia que tenha chegado depois
                    $this->repair_media($existing, $json);
                    $this->db->trans_commit();
                    $this->respond_success((int) $existing->id, false, true,
                        'Form already synced');
                    return;
                }

                $form_response_id = (int) $existing->id;
                $this->update_existing($existing, $json, $client_id, $edited_at);
                $this->db->where('form_response_id', $form_response_id)->delete('question_responses');
                $is_edit = true;
            } else {
                $form_response_id = $this->insert_new($json, $client_id, $edited_at, $started_at);
                $is_edit = false;

                if ($form_response_id === 'duplicated') {
                    return; // resposta ja enviada por insert_new
                }
            }

            $saved_answers = 0;
            foreach ($json['responses'] as $response) {
                if (!isset($response['question_id'])) {
                    continue;
                }

                $answer_data = [
                    'form_response_id'  => $form_response_id,
                    'question_id'       => $response['question_id'],
                    'response_text'     => $response['response_text'] ?? null,
                    'response_number'   => $response['response_number'] ?? null,
                    'response_date'     => isset($response['response_date']) ? date('Y-m-d', strtotime($response['response_date'])) : null,
                    'response_datetime' => isset($response['response_datetime']) ? date('Y-m-d H:i:s', strtotime($response['response_datetime'])) : null,
                    'selected_options'  => isset($response['selected_options']) ? json_encode($response['selected_options']) : null
                ];

                // Antes o retorno era ignorado e um item recusado gravava a
                // coleta incompleta sem ninguem perceber
                if (!$this->Response_model->create_answer($answer_data)) {
                    throw new Exception('Failed to save answer for question ' . $response['question_id']);
                }
                $saved_answers++;
            }

            if ($this->db->trans_status() === FALSE) {
                throw new Exception('Transaction failed');
            }

            $this->db->trans_commit();

            log_message('info', 'forms/submit OK - id=' . $form_response_id
                . ' itens=' . $saved_answers
                . ' client_id=' . ($client_id ?: '-')
                . ' edit=' . ($is_edit ? '1' : '0'));

            $this->respond_success($form_response_id, $is_edit, false,
                $is_edit ? 'Form updated successfully' : 'Form submitted successfully');

        } catch (Exception $e) {
            $this->db->trans_rollback();

            log_message('error', 'forms/submit FALHOU - ' . $e->getMessage()
                . ' | client_id=' . ($client_id ?: '-')
                . ' | questionnaire_id=' . ($json['questionnaire_id'] ?? '-')
                . ' | applied_by=' . ($json['applied_by'] ?? '-'));

            $this->output->set_status_header(500);
            // Nunca devolver id aqui: o app mantem a coleta e reenvia
            echo json_encode([
                'success' => false,
                'message' => 'Failed to submit form: ' . $e->getMessage()
            ]);
        }
    }

    // ------------------------------------------------------------
    // Reconhecimento da coleta
    // ------------------------------------------------------------

    /**
     * Procura o registro desta coleta, se ela ja chegou antes.
     *
     * 1. client_id - chave gerada pelo app no nascimento da coleta
     * 2. id do servidor - edicao de um registro baixado do historico
     * 3. questionario + aplicador + inicio exato da entrevista - coletas de
     *    versoes do app sem client_id. O inicio e gravado ao segundo, entao
     *    duas entrevistas diferentes nunca coincidem nos tres campos.
     */
    private function find_existing($json, $client_id, $started_at, $user_id) {
        if ($client_id !== null && $this->has_column('client_id')) {
            $row = $this->db->get_where('form_responses', ['client_id' => $client_id])->row();
            if ($row) {
                return $row;
            }
        }

        $server_id = isset($json['id']) && is_numeric($json['id']) ? (int) $json['id'] : 0;
        if ($server_id > 0 && $server_id <= self::MAX_SERVER_ID) {
            $row = $this->db->get_where('form_responses', ['id' => $server_id])->row();
            if ($row && $this->can_edit($row, $user_id)) {
                return $row;
            }
        }

        if ($started_at !== null) {
            $this->db->where('questionnaire_id', (int) $json['questionnaire_id']);
            $this->db->where('applied_by', (int) $json['applied_by']);
            $this->db->where('started_at', $started_at);
            if ($client_id !== null && $this->has_column('client_id')) {
                // Registro de outra coleta ja identificada nao serve
                $this->db->where('client_id IS NULL', NULL, FALSE);
            }
            $this->db->order_by('id', 'ASC');
            $this->db->limit(1);
            $row = $this->db->get('form_responses')->row();
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Quem pode editar um registro pelo id: o proprio aplicador, ou
     * supervisor/administrador.
     */
    private function can_edit($row, $user_id) {
        if ((int) $row->applied_by === (int) $user_id) {
            return true;
        }

        $user = $this->db->select('role')->get_where('users', ['id' => (int) $user_id])->row();

        return $user && in_array($user->role, ['administrador', 'supervisor'], true);
    }

    // ------------------------------------------------------------
    // Gravacao
    // ------------------------------------------------------------

    /**
     * Atualiza uma coleta editada.
     *
     * Localizacao, aplicador e datas da entrevista NUNCA mudam numa edicao:
     * eles registram onde e quando a entrevista aconteceu. Versoes antigas
     * do app trocavam as coordenadas pela posicao do momento da edicao, e
     * varias entrevistas acabaram com a mesma coordenada.
     */
    private function update_existing($existing, $json, $client_id, $edited_at) {
        $data = [
            'respondent_name'  => $json['respondent_name'] ?? $existing->respondent_name,
            'respondent_email' => $json['respondent_email'] ?? $existing->respondent_email,
            'consent_given'    => isset($json['consent_given']) ? (bool) $json['consent_given'] : $existing->consent_given,
            'photo_path'       => $this->keep_or_replace($json['photo_path'] ?? null, $existing->photo_path),
            'photo_path_2'     => $this->keep_or_replace($json['photo_path_2'] ?? null, $existing->photo_path_2),
            'sync_status'      => 'synced',
        ];

        if ($this->has_column('audio_path')) {
            $data['audio_path'] = $this->keep_or_replace($json['audio_path'] ?? null,
                isset($existing->audio_path) ? $existing->audio_path : null);
        }

        if ($edited_at !== null && $this->has_column('edited_at')) {
            $data['edited_at'] = $edited_at;
        }

        // Registro antigo ganha a chave da coleta, para os proximos envios
        if ($client_id !== null && $this->has_column('client_id') && empty($existing->client_id)) {
            $data['client_id'] = $client_id;
        }

        // Coordenadas so sao preenchidas se o registro nunca as teve
        if (($existing->latitude === null || $existing->longitude === null)) {
            list($lat, $lng) = $this->clean_coordinates($json);
            if ($lat !== null && $lng !== null) {
                $data['latitude'] = $lat;
                $data['longitude'] = $lng;
            }
        }

        $this->db->where('id', (int) $existing->id)->update('form_responses', $data);
    }

    /**
     * Grava uma coleta nova. Devolve o id, ou 'duplicated' quando outro envio
     * simultaneo da mesma coleta ganhou a corrida (resposta ja enviada).
     */
    private function insert_new($json, $client_id, $edited_at, $started_at) {
        list($latitude, $longitude) = $this->clean_coordinates($json);

        $location_name = $json['location_name'] ?? null;
        $location_name = (is_string($location_name) && trim($location_name) !== '') ? $location_name : null;

        $form_data = [
            'questionnaire_id' => $json['questionnaire_id'],
            'respondent_name'  => $json['respondent_name'] ?? null,
            'respondent_email' => $json['respondent_email'] ?? null,
            'applied_by'       => $json['applied_by'],
            'latitude'         => $latitude,
            'longitude'        => $longitude,
            'location_name'    => $location_name,
            'photo_path'       => $json['photo_path'] ?? null,
            'photo_path_2'     => $json['photo_path_2'] ?? null,
            'consent_given'    => isset($json['consent_given']) ? (bool) $json['consent_given'] : false,
            'sync_status'      => 'synced',
            'started_at'       => $started_at,
            'completed_at'     => $this->to_datetime($json['completed_at'] ?? null) ?: date('Y-m-d H:i:s'),
        ];

        if ($client_id !== null && $this->has_column('client_id')) {
            $form_data['client_id'] = $client_id;
        }
        if ($edited_at !== null && $this->has_column('edited_at')) {
            $form_data['edited_at'] = $edited_at;
        }
        if ($this->has_column('audio_path')) {
            $form_data['audio_path'] = $json['audio_path'] ?? null;
        }

        // Violacao do UNIQUE de client_id (dois envios simultaneos da mesma
        // coleta) e esperada: tratar, nao abortar com pagina de erro
        $prev_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $id = $this->Response_model->create($form_data);
        $this->db->db_debug = $prev_debug;

        if ($id) {
            return (int) $id;
        }

        if ($client_id !== null && $this->has_column('client_id')) {
            // No PostgreSQL a transacao fica abortada apos o erro: desfazer
            // antes de consultar
            $this->db->trans_rollback();
            $row = $this->db->select('id')->get_where('form_responses', ['client_id' => $client_id])->row();
            if ($row) {
                $this->respond_success((int) $row->id, false, true, 'Form already synced (concurrent submit)');
                return 'duplicated';
            }
        }

        throw new Exception('Failed to create form response');
    }

    // ------------------------------------------------------------
    // Utilitarios
    // ------------------------------------------------------------

    /**
     * Sem coordenadas validas, grava NULL: nunca 0,0 (um ponto no oceano).
     */
    private function clean_coordinates($json) {
        $lat = $json['latitude'] ?? null;
        $lng = $json['longitude'] ?? null;

        $lat = (is_numeric($lat) && $lat >= -90 && $lat <= 90 && (float) $lat != 0) ? (float) $lat : null;
        $lng = (is_numeric($lng) && $lng >= -180 && $lng <= 180 && (float) $lng != 0) ? (float) $lng : null;

        return [$lat, $lng];
    }

    private function clean_client_id($value) {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : substr($value, 0, 64);
    }

    private function to_datetime($value) {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    private function keep_or_replace($incoming, $current) {
        return (is_string($incoming) && trim($incoming) !== '') ? $incoming : $current;
    }

    /**
     * As colunas client_id, edited_at e audio_path vem da migracao
     * sql/form_responses_sync_migration.sql. Enquanto ela nao roda, o envio
     * continua funcionando sem elas.
     */
    private function has_column($column) {
        if (!isset($this->columns[$column])) {
            $this->columns[$column] = $this->db->field_exists($column, 'form_responses');
        }

        return $this->columns[$column];
    }

    /**
     * Devolve o id em varios formatos de proposito: `form_response_id` (o que
     * as versoes em campo leem), `id` e `data.id`. Chamar so apos o commit.
     */
    private function respond_success($id, $is_edit, $duplicated, $message) {
        $this->output->set_status_header(200);
        echo json_encode([
            'success'          => true,
            'id'               => (int) $id,
            'form_response_id' => (int) $id,
            'data'             => ['id' => (int) $id],
            'edited'           => (bool) $is_edit,
            'duplicated'       => (bool) $duplicated,
            // Fotos/audio que a coleta cita e o servidor nao tem. O app
            // reenvia o que ainda tiver no aparelho e reenvia a coleta.
            'missing_media'    => $this->missing_media($id),
            'message'          => $message
        ]);
    }

    /**
     * Reenvio de coleta ja gravada cuja foto/audio nao esta no servidor: se o
     * app agora cita um arquivo que EXISTE (a foto foi gravada com outro nome,
     * ou o registro tinha o caminho do aparelho), o registro passa a cita-lo.
     * Sem isso o app ouviria "missing_media" e reenviaria a midia para sempre.
     */
    private function repair_media($existing, $json) {
        $folders = ['photo_path' => 'photos', 'photo_path_2' => 'photos', 'audio_path' => 'audios'];
        $update = [];
        foreach ($folders as $column => $folder) {
            if (!property_exists($existing, $column)) {
                continue;
            }
            $atual = trim((string) $existing->$column);
            $novo = isset($json[$column]) ? trim((string) $json[$column]) : '';
            if ($novo === '' || $novo === $atual || strpbrk($novo, '/\\') !== FALSE) {
                continue;
            }
            $atual_ok = $atual !== '' && strpbrk($atual, '/\\') === FALSE
                && is_file(FCPATH . 'uploads/' . $folder . '/' . $atual);
            if (!$atual_ok && is_file(FCPATH . 'uploads/' . $folder . '/' . $novo)) {
                $update[$column] = $novo;
            }
        }
        if ($update) {
            $this->db->where('id', (int) $existing->id)->update('form_responses', $update);
            log_message('error', 'forms/submit - coleta ' . (int) $existing->id
                . ' passou a citar a midia recebida: ' . implode(', ', $update));
        }
    }

    /**
     * Mídias citadas pela coleta gravada que nao existem em uploads/. Um nome
     * com "/" e caminho do aparelho, nunca um arquivo do servidor: sempre falta.
     */
    private function missing_media($id) {
        $row = $this->db->get_where('form_responses', ['id' => (int) $id])->row();
        if (!$row) {
            return [];
        }

        $missing = [];
        $folders = ['photo_path' => 'photos', 'photo_path_2' => 'photos', 'audio_path' => 'audios'];
        foreach ($folders as $column => $folder) {
            $name = isset($row->$column) ? trim((string) $row->$column) : '';
            if ($name === '') {
                continue;
            }
            if (strpos($name, '/') !== FALSE || strpos($name, '\\') !== FALSE
                || !is_file(FCPATH . 'uploads/' . $folder . '/' . $name)) {
                $missing[] = $name;
            }
        }

        if ($missing) {
            log_message('error', 'forms/submit - coleta ' . (int) $id . ' sem midia no servidor: ' . implode(', ', $missing));
        }
        return $missing;
    }

    /**
     * Garante JSON mesmo em erro fatal (memoria, timeout). Sem isso o app
     * recebe HTML e nao entende o que houve.
     */
    private function register_fatal_json_handler() {
        register_shutdown_function(function () {
            $err = error_get_last();
            if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            if (headers_sent()) {
                return;
            }

            log_message('error', 'forms/submit FATAL - ' . $err['message'] . ' em ' . $err['file'] . ':' . $err['line']);

            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno ao gravar o formulário. Reenvie.',
                'fatal'   => true
            ]);
        });
    }

    private function verify_auth() {
        $user_id = $this->api_token->user_id_from_request();

        if (!$user_id) {
            $this->output->set_status_header(401);
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            return false;
        }

        return $user_id;
    }
}
