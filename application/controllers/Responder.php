<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Formulário público de questionário (coleta por QR Code).
 *
 * Acesso sem login, restrito ao questionário do link: quem responde não vê
 * o painel, as respostas de ninguém nem outras áreas do sistema.
 *
 * Rotas: responder/<token> e responder/<token>/obrigado
 */
class Responder extends CI_Controller {

    /** Envio mais rápido que isso é robô */
    const MIN_FILL_SECONDS = 3;

    /** Página aberta há mais tempo que isso precisa ser recarregada */
    const MAX_FORM_AGE_SECONDS = 86400;

    const MAX_TEXT_LENGTH = 5000;

    public function __construct() {
        parent::__construct();
        $this->load->model('Public_link_model');
        $this->load->model('Question_model');
        $this->load->model('Questionnaire_model');
        $this->load->library('api_token');

        // Formulário público não deve aparecer em buscadores
        header('X-Robots-Tag: noindex, nofollow');
    }

    public function index($token = '') {
        $link = $this->Public_link_model->get_by_token($token);
        $reason = $this->Public_link_model->unavailable_reason($link);
        if ($reason !== null) {
            $this->show_unavailable($reason);
            return;
        }

        $questions = $this->load_questions($link->questionnaire_id);

        $data = [
            'link'             => $link,
            'questions'        => $questions,
            'requires_consent' => $this->truthy($link->requires_consent),
            'errors'           => [],
            'old'              => [],
            'old_consent'      => false,
            'ticket'           => null,
        ];

        if ($this->input->method() === 'post') {
            $result = $this->handle_submit($link, $questions);

            if ($result['ok']) {
                redirect('responder/' . $link->token . '/obrigado');
                return;
            }

            $data['errors']      = $result['errors'];
            $data['old']         = $result['old'];
            $data['old_consent'] = $result['consent'];
            // Mantém o mesmo bilhete: preserva o horário de início e evita
            // gravar duas vezes se a pessoa enviar de novo
            $data['ticket']      = $result['ticket'];
        }

        if ($data['ticket'] === null) {
            $data['ticket'] = $this->make_ticket($link->id);
        }

        $this->load->view('publico/formulario', $data);
    }

    public function obrigado($token = '') {
        $link = $this->Public_link_model->get_by_token($token);
        if (!$link) {
            $this->show_unavailable($this->Public_link_model->unavailable_reason(null));
            return;
        }

        $this->load->view('publico/obrigado', ['link' => $link]);
    }

    // ------------------------------------------------------------
    // Envio
    // ------------------------------------------------------------

    private function handle_submit($link, $questions) {
        $posted_ticket = (string) $this->input->post('t');
        $raw_answers   = $this->input->post('q');
        $raw_answers   = is_array($raw_answers) ? $raw_answers : [];
        $consent       = $this->input->post('consentimento') === '1';

        $result = [
            'ok'      => false,
            'errors'  => [],
            'old'     => $raw_answers,
            'consent' => $consent,
            'ticket'  => $posted_ticket,
        ];

        // Campo invisível: pessoas não preenchem, robôs preenchem
        if (trim((string) $this->input->post('website')) !== '') {
            $result['ok'] = true; // finge sucesso, não grava nada
            return $result;
        }

        $ticket = $this->read_ticket($posted_ticket, (int) $link->id);
        if ($ticket === null) {
            $result['errors'][] = 'Esta página expirou. Recarregue e responda novamente.';
            $result['ticket'] = null;
            return $result;
        }

        if (time() - $ticket['s'] < self::MIN_FILL_SECONDS) {
            $result['errors'][] = 'Envio muito rápido. Confira as respostas e envie de novo.';
            return $result;
        }

        $ip_hash = $this->Public_link_model->ip_hash($this->input->ip_address());
        if ($this->Public_link_model->is_rate_limited($link->id, $ip_hash)) {
            $result['errors'][] = 'Muitas respostas enviadas desta rede na última hora. Tente mais tarde.';
            return $result;
        }

        if ($this->truthy($link->requires_consent) && !$consent) {
            $result['errors'][] = 'Para responder, é preciso concordar com o termo de consentimento.';
        }

        list($answers, $answer_errors) = $this->build_answers($questions, $raw_answers);
        $result['errors'] = array_merge($result['errors'], $answer_errors);

        if (!empty($result['errors'])) {
            return $result;
        }

        if (empty($answers)) {
            $result['errors'][] = 'Responda pelo menos uma pergunta antes de enviar.';
            return $result;
        }

        $saved = $this->Public_link_model->save_submission(
            $link,
            $answers,
            $consent,
            date('Y-m-d H:i:s', $ticket['s']),
            'pub-' . $ticket['n']
        );

        if ($saved === FALSE) {
            log_message('error', 'Responder - falha ao gravar resposta do link ' . $link->id);
            $result['errors'][] = 'Não foi possível registrar sua resposta. Tente novamente em instantes.';
            return $result;
        }

        if ($saved !== 'duplicated') {
            $this->Public_link_model->log_submission($link->id, $ip_hash);
        }

        $result['ok'] = true;
        return $result;
    }

    /**
     * Converte o que veio do formulário nas linhas de question_responses, no
     * mesmo formato do app, aplicando a lógica condicional do questionário.
     *
     * @return array [linhas, erros]
     */
    private function build_answers($questions, $raw_answers) {
        // Valores no formato que a lógica condicional espera (id => valor)
        $logic_values = [];
        foreach ($questions as $q) {
            $logic_values[$q->id] = $this->normalize_raw($q, $raw_answers[$q->id] ?? null);
        }

        $states = $this->Questionnaire_model->execute_conditional_logic($questions, $logic_values);

        $answers = [];
        $errors  = [];

        foreach ($questions as $q) {
            $state = $states[$q->id] ?? ['visible' => true, 'required' => $q->is_required];

            // Pergunta escondida pela lógica: a resposta não vale, mesmo se veio
            if (!$state['visible']) {
                continue;
            }

            $value = $logic_values[$q->id];
            $empty = is_array($value) ? count($value) === 0 : $value === '';

            if ($empty) {
                if ($state['required']) {
                    $errors[] = 'Responda a pergunta: "' . $this->short($q->question_text) . '"';
                }
                continue;
            }

            $row = $this->to_answer_row($q, $value, $error);
            if ($error !== null) {
                $errors[] = $error;
                continue;
            }

            $answers[] = $row;
        }

        return [$answers, $errors];
    }

    private function normalize_raw($question, $raw) {
        if ($question->question_type === 'checkbox') {
            $values = is_array($raw) ? $raw : ($raw === null ? [] : [$raw]);
            return array_values(array_filter(array_map(function ($v) {
                return trim((string) $v);
            }, $values), 'strlen'));
        }

        return is_array($raw) ? '' : trim((string) $raw);
    }

    /**
     * Uma linha de question_responses. Mesmo formato do app:
     * múltipla escolha em selected_options (JSON), número em
     * response_number, datas nos campos de data, o resto em response_text.
     */
    private function to_answer_row($question, $value, &$error) {
        $error = null;
        $label = '"' . $this->short($question->question_text) . '"';

        $row = [
            'question_id'       => (int) $question->id,
            'response_text'     => null,
            'response_number'   => null,
            'response_date'     => null,
            'response_datetime' => null,
            'selected_options'  => null,
        ];

        switch ($question->question_type) {
            case 'radio':
            case 'checkbox':
                $values = is_array($value) ? $value : [$value];
                $allowed = $this->option_values($question);
                foreach ($values as $v) {
                    if (!in_array($v, $allowed, true)) {
                        $error = 'Opção inválida na pergunta ' . $label . '.';
                        return null;
                    }
                }
                $row['selected_options'] = json_encode(array_values($values), JSON_UNESCAPED_UNICODE);
                break;

            case 'select':
                if (!in_array($value, $this->option_values($question), true)) {
                    $error = 'Opção inválida na pergunta ' . $label . '.';
                    return null;
                }
                $row['response_text'] = $value;
                break;

            case 'number':
                $normalized = str_replace(',', '.', $value);
                if (!is_numeric($normalized)) {
                    $error = 'A pergunta ' . $label . ' aceita apenas números.';
                    return null;
                }
                $row['response_number'] = (float) $normalized;
                break;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Informe um e-mail válido na pergunta ' . $label . '.';
                    return null;
                }
                $row['response_text'] = $value;
                break;

            case 'phone':
                $phone = $this->format_phone($value);
                if ($phone === null) {
                    $error = 'Informe o telefone com DDD na pergunta ' . $label . '.';
                    return null;
                }
                $row['response_text'] = $phone;
                break;

            case 'date':
                $d = DateTime::createFromFormat('Y-m-d', $value);
                if (!$d || $d->format('Y-m-d') !== $value) {
                    $error = 'Informe uma data válida na pergunta ' . $label . '.';
                    return null;
                }
                $row['response_date'] = $value;
                break;

            case 'datetime':
                $d = DateTime::createFromFormat('Y-m-d\TH:i', $value);
                if (!$d) {
                    $error = 'Informe data e hora válidas na pergunta ' . $label . '.';
                    return null;
                }
                $row['response_datetime'] = $d->format('Y-m-d H:i:s');
                break;

            default: // text, textarea, time e tipos não previstos
                if (mb_strlen($value) > self::MAX_TEXT_LENGTH) {
                    $error = 'A resposta da pergunta ' . $label . ' é longa demais.';
                    return null;
                }
                $row['response_text'] = $value;
        }

        return $row;
    }

    /**
     * Valores aceitos de uma pergunta de opções. Mesma regra do app: o valor
     * da opção, ou o texto quando não há valor.
     */
    private function option_values($question) {
        $values = [];
        foreach ($question->options ?? [] as $opt) {
            $v = ($opt->option_value !== null && $opt->option_value !== '') ? $opt->option_value : $opt->option_text;
            $values[] = (string) $v;
        }

        return $values;
    }

    // ------------------------------------------------------------
    // Bilhete do formulário
    // ------------------------------------------------------------

    /**
     * Bilhete assinado gerado ao abrir a página: prova que o envio veio do
     * formulário, de quando ele foi aberto, e identifica o envio (o mesmo
     * envio repetido não grava duas vezes).
     */
    private function make_ticket($link_id) {
        $payload = rtrim(strtr(base64_encode(json_encode([
            'l' => (int) $link_id,
            's' => time(),
            'n' => bin2hex(random_bytes(12)),
        ])), '+/', '-_'), '=');

        return $payload . '.' . $this->api_token->signature($payload);
    }

    private function read_ticket($ticket, $link_id) {
        $parts = explode('.', $ticket);
        if (count($parts) !== 2) {
            return null;
        }

        if (!hash_equals($this->api_token->signature($parts[0]), $parts[1])) {
            return null;
        }

        $payload = $parts[0];
        $remainder = strlen($payload) % 4;
        if ($remainder) {
            $payload .= str_repeat('=', 4 - $remainder);
        }
        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), TRUE), TRUE);

        if (!is_array($data) || ($data['l'] ?? null) !== $link_id || empty($data['s']) || empty($data['n'])) {
            return null;
        }

        if (time() - (int) $data['s'] > self::MAX_FORM_AGE_SECONDS) {
            return null;
        }

        return $data;
    }

    // ------------------------------------------------------------
    // Utilitários
    // ------------------------------------------------------------

    private function load_questions($questionnaire_id) {
        $questions = $this->Question_model->get_by_questionnaire_with_logic_robust($questionnaire_id);

        foreach ($questions as $q) {
            // PostgreSQL devolve 't'/'f'; (bool)'f' seria verdadeiro e todas
            // as perguntas ficariam obrigatórias
            $q->is_required = $this->truthy($q->is_required);
            $q->question_type = strtolower(trim((string) $q->question_type));
        }

        return $questions;
    }

    private function show_unavailable($reason) {
        $this->output->set_status_header(404);
        $this->load->view('publico/indisponivel', ['reason' => $reason]);
    }

    /**
     * Telefone com DDD no mesmo formato gravado pelo app:
     * (DD) XXXX-XXXX (fixo) ou (DD) XXXXX-XXXX (celular). NULL se inválido.
     */
    private function format_phone($value) {
        $d = preg_replace('/\D/', '', (string) $value);
        $len = strlen($d);
        if (($len !== 10 && $len !== 11) || $d[0] === '0' || $d[1] === '0') {
            return null;
        }

        $meio = $len === 11 ? 5 : 4;
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, $meio) . '-' . substr($d, 2 + $meio);
    }

    private function short($text) {
        $text = trim(strip_tags((string) $text));
        return mb_strlen($text) > 80 ? mb_substr($text, 0, 77) . '...' : $text;
    }

    private function truthy($value) {
        return $value === TRUE || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }
}
