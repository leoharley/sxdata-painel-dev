<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ai_prompt_service - Gerenciamento de prompts para IA
 *
 * Responsável por:
 * - Carregar prompts versionados do banco
 * - Interpolar variáveis nos templates
 * - Montar mensagens para chat completion
 * - Preparar dados de contexto para cada feature
 */
class Ai_prompt_service {

    private $CI;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->model('Ai_model');
    }

    /**
     * Monta mensagens de chat com base no prompt ativo e variáveis
     */
    public function build_messages($feature_key, $variables = array()) {
        $prompt = $this->CI->Ai_model->get_active_prompt($feature_key);

        if (!$prompt) {
            return array(
                'success' => false,
                'error' => "Nenhum prompt ativo encontrado para '{$feature_key}'",
            );
        }

        $messages = array();

        if (!empty($prompt->system_prompt)) {
            $system_content = $this->interpolate($prompt->system_prompt, $variables);

            // Injetar diretrizes ativas para esta feature
            $directives_text = $this->build_directives_block($feature_key);
            if ($directives_text) {
                $system_content .= "\n\n" . $directives_text;
            }

            $messages[] = array(
                'role' => 'system',
                'content' => $system_content,
            );
        }

        $messages[] = array(
            'role' => 'user',
            'content' => $this->interpolate($prompt->user_prompt_template, $variables),
        );

        return array(
            'success' => true,
            'messages' => $messages,
            'prompt_id' => $prompt->id,
        );
    }

    /**
     * Prepara dados de contexto para detecção de inconsistências
     */
    public function prepare_inconsistency_data($form_response_id) {
        $this->CI->load->model('Response_model');
        $this->CI->load->model('Questionnaire_model');

        $response = $this->CI->Response_model->get_by_id($form_response_id);
        if (!$response) {
            return null;
        }

        $answers = $this->CI->Response_model->get_answers($form_response_id);
        $questionnaire = $this->CI->Questionnaire_model->get_by_id($response->questionnaire_id);

        $responses_data = array();
        foreach ($answers as $answer) {
            $responses_data[] = array(
                'question_id' => $answer->question_id,
                'question' => $answer->question_text ?? 'Pergunta ' . $answer->question_id,
                'type' => $answer->question_type ?? 'text',
                'answer' => $this->extract_answer_value($answer),
            );
        }

        return array(
            'questionnaire_title' => $questionnaire ? $questionnaire->title : 'Questionário',
            'responses_data' => json_encode($responses_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'form_response_id' => $form_response_id,
            'questionnaire_id' => $response->questionnaire_id,
        );
    }

    /**
     * Prepara dados para correção e padronização
     *
     * Campos de texto livre (text/textarea/number/date): enviados como corrigíveis.
     * Campos de opção (radio/checkbox/select): enviados com o texto de exibição (option_text)
     * marcados como NÃO corrigíveis — o valor armazenado afeta regras de condicionalidade.
     */
    public function prepare_correction_data($form_response_id) {
        $this->CI->load->model('Response_model');
        $this->CI->load->model('Questionnaire_model');
        $this->CI->load->model('Question_model');

        $response = $this->CI->Response_model->get_by_id($form_response_id);
        if (!$response) {
            return null;
        }

        $answers  = $this->CI->Response_model->get_answers($form_response_id);
        $questionnaire = $this->CI->Questionnaire_model->get_by_id($response->questionnaire_id);

        $option_types = array('radio', 'checkbox', 'select');
        $field_data   = array();

        foreach ($answers as $answer) {
            $type = $answer->question_type ?? 'text';

            if (in_array($type, $option_types)) {
                // Busca o texto de exibição das opções selecionadas
                $selected_raw = json_decode($answer->selected_options ?? '[]', true);
                if (!is_array($selected_raw)) {
                    $selected_raw = array();
                }

                $display_texts = array();
                if (!empty($selected_raw)) {
                    $options = $this->CI->Question_model->get_options($answer->question_id);
                    foreach ($options as $opt) {
                        foreach ($selected_raw as $sel) {
                            if ((string)$sel === (string)$opt->option_text
                                || (string)$sel === (string)$opt->option_value
                                || (string)$sel === (string)$opt->id) {
                                $display_texts[] = $opt->option_text;
                                break;
                            }
                        }
                    }
                    // Fallback: usa os valores brutos se não encontrou nenhum label
                    if (empty($display_texts)) {
                        $display_texts = array_map('strval', $selected_raw);
                    }
                }

                $field_data[] = array(
                    'question_id'   => $answer->question_id,
                    'question'      => $answer->question_text ?? '',
                    'type'          => $type,
                    'display_value' => implode(', ', $display_texts),
                    'correctable'   => false,
                );
            } else {
                // Campo de texto livre — corrigível
                $value = $this->extract_answer_value($answer);
                if ($value === '' || $value === null) {
                    continue; // ignora vazios
                }

                $field_data[] = array(
                    'question_id'   => $answer->question_id,
                    'question'      => $answer->question_text ?? '',
                    'type'          => $type,
                    'display_value' => (string) $value,
                    'correctable'   => true,
                );
            }
        }

        return array(
            'questionnaire_title' => $questionnaire ? $questionnaire->title : 'Questionário',
            'field_data' => json_encode($field_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'form_response_id' => $form_response_id,
            'questionnaire_id' => $response->questionnaire_id,
        );
    }

    /**
     * Prepara dados para preenchimento inteligente
     */
    public function prepare_smart_fill_data($questionnaire_id, $existing_answers = array(), $pending_question_ids = array()) {
        $this->CI->load->model('Questionnaire_model');
        $this->CI->load->model('Question_model');

        $questionnaire = $this->CI->Questionnaire_model->get_by_id($questionnaire_id);
        $questions = $this->CI->Question_model->get_by_questionnaire($questionnaire_id);

        $existing = array();
        $pending = array();

        foreach ($questions as $q) {
            if (isset($existing_answers[$q->id])) {
                $existing[] = array(
                    'question_id' => $q->id,
                    'question' => $q->question_text,
                    'answer' => $existing_answers[$q->id],
                );
            } elseif (empty($pending_question_ids) || in_array($q->id, $pending_question_ids)) {
                $pending[] = array(
                    'question_id' => $q->id,
                    'question' => $q->question_text,
                    'type' => $q->question_type,
                );
            }
        }

        return array(
            'questionnaire_title' => $questionnaire ? $questionnaire->title : 'Questionário',
            'existing_answers' => json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'pending_fields' => json_encode($pending, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'questionnaire_id' => $questionnaire_id,
        );
    }

    /**
     * Prepara dados para reformulação de perguntas
     */
    public function prepare_reformulation_data($question_id, $objective = 'clareza') {
        $this->CI->load->model('Question_model');
        $this->CI->load->model('Questionnaire_model');

        $question = $this->CI->Question_model->get_by_id($question_id);
        if (!$question) {
            return null;
        }

        $questionnaire = $this->CI->Questionnaire_model->get_by_id($question->questionnaire_id);

        return array(
            'original_question' => $question->question_text,
            'objective' => $objective,
            'context' => $questionnaire ? $questionnaire->title . ' - ' . ($questionnaire->description ?? '') : '',
            'question_id' => $question_id,
            'questionnaire_id' => $question->questionnaire_id,
        );
    }

    /**
     * Prepara dados para geração de regras adaptativas
     */
    public function prepare_adaptive_data($questionnaire_id) {
        $this->CI->load->model('Questionnaire_model');
        $this->CI->load->model('Question_model');

        $questionnaire = $this->CI->Questionnaire_model->get_by_id($questionnaire_id);
        $questions     = $this->CI->Question_model->get_by_questionnaire($questionnaire_id);

        $questions_data = array();
        foreach ($questions as $q) {
            $entry = array(
                'id'          => $q->id,
                'text'        => $q->question_text,
                'type'        => $q->question_type,
                'order_index' => $q->order_index ?? 0,
            );
            if (!empty($q->options)) {
                $entry['options'] = array_map(function($o) {
                    return array('text' => $o->option_text, 'value' => $o->option_value ?? $o->option_text);
                }, $q->options);
            }
            $questions_data[] = $entry;
        }

        return array(
            'questionnaire_title' => $questionnaire ? $questionnaire->title : 'Questionário',
            'questionnaire_description' => $questionnaire ? ($questionnaire->description ?? '') : '',
            'questions_data' => json_encode($questions_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'questionnaire_id' => $questionnaire_id,
        );
    }

    /**
     * Prepara dados para sugestões de follow-up
     */
    public function prepare_followup_data($questionnaire_id) {
        $this->CI->load->model('Questionnaire_model');
        $this->CI->load->model('Response_model');
        $this->CI->load->model('Question_model');

        $questionnaire = $this->CI->Questionnaire_model->get_by_id($questionnaire_id);
        $questions = $this->CI->Question_model->get_by_questionnaire($questionnaire_id);
        $response_count = $this->CI->Response_model->count_by_filters(array('questionnaire_id' => $questionnaire_id));

        $questions_data = array();
        foreach ($questions as $q) {
            $entry = array(
                'id' => $q->id,
                'text' => $q->question_text,
                'type' => $q->question_type,
            );
            if (!empty($q->options)) {
                $entry['options'] = array_map(function($o) {
                    return $o->option_text;
                }, $q->options);
            }
            $questions_data[] = $entry;
        }

        return array(
            'questionnaire_title' => $questionnaire ? $questionnaire->title : 'Questionário',
            'questions_data' => json_encode($questions_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'total_responses' => $response_count,
            'questionnaire_id' => $questionnaire_id,
        );
    }

    /**
     * Prepara dados para análise estatística
     */
    /**
     * Dados da análise estatística, calculados de TODAS as respostas.
     *
     * Antes ia para a IA uma amostra de até 50 entrevistas cruas, com a
     * pergunta como "q_774" e a resposta como o valor interno da opção
     * ("muito_satisfeitoa"): a análise devolvia esses códigos ao cliente e
     * os números saíam da amostra. Agora vai um relatório legível, com o
     * texto das perguntas e das opções, e os números vêm do banco.
     */
    public function prepare_statistical_data($questionnaire_id, $filters = array()) {
        $this->CI->load->model('Questionnaire_model');
        $this->CI->load->model('Client_analytics_model');

        $questionnaire = $this->CI->Questionnaire_model->get_by_id($questionnaire_id);
        $periodo = array(
            'date_from' => !empty($filters['date_from']) ? $filters['date_from'] : null,
            'date_to'   => !empty($filters['date_to']) ? $filters['date_to'] : null,
        );

        $visao    = $this->CI->Client_analytics_model->get_overview($questionnaire_id, $periodo);
        $fechadas = $this->CI->Client_analytics_model->get_question_breakdown($questionnaire_id, $periodo);
        $abertas  = $this->CI->Client_analytics_model->get_open_answers($questionnaire_id, 120, $periodo);
        $por_dia  = $this->CI->Client_analytics_model->get_responses_by_day($questionnaire_id, 90);

        $fmt_data = function ($d) { return $d ? date('d/m/Y', strtotime($d)) : null; };
        $periodo_txt = ($periodo['date_from'] || $periodo['date_to'])
            ? ($fmt_data($periodo['date_from']) ?: 'início') . ' a ' . ($fmt_data($periodo['date_to']) ?: 'hoje')
            : 'toda a coleta';

        // Cada pergunta ganha um número (P1, P2...) para a resposta da IA
        // poder citá-la sem depender do texto exato
        $perguntas = array();
        $n = 0;
        $linhas = array();
        $linhas[] = 'Questionário: ' . ($questionnaire ? $questionnaire->title : 'Questionário');
        $linhas[] = 'Período analisado: ' . $periodo_txt;
        $linhas[] = 'Entrevistas concluídas: ' . $visao['total_responses'];
        $linhas[] = 'Aplicadores envolvidos: ' . $visao['total_applicators'];
        $linhas[] = 'Locais distintos: ' . $visao['total_locations'];
        if ($visao['first_response']) {
            $linhas[] = 'Primeira e última entrevista: ' . $fmt_data($visao['first_response']) . ' e ' . $fmt_data($visao['last_response']);
        }

        if (!empty($fechadas)) {
            $linhas[] = '';
            $linhas[] = 'PERGUNTAS DE ESCOLHA E NUMÉRICAS (contagem sobre todas as respostas):';
            foreach ($fechadas as $p) {
                $n++;
                $perguntas[$n] = array('tipo' => 'fechada', 'dados' => $p);
                $tipo = array('radio' => 'escolha única', 'select' => 'escolha única', 'checkbox' => 'múltipla escolha', 'number' => 'numérica');
                $linhas[] = "P{$n}. \"{$p['question_text']}\" (" . ($tipo[$p['question_type']] ?? $p['question_type']) . "; {$p['total']} responderam)";
                foreach ($p['linhas'] as $l) {
                    $linhas[] = "   - {$l['rotulo']}: {$l['total']} (" . number_format($l['percentual'], 1, ',', '') . '%)';
                }
                if (!empty($p['resumo'])) {
                    $r = $p['resumo'];
                    $linhas[] = '   - média ' . number_format($r['media'], 2, ',', '') . '; mínimo ' . $r['minimo'] . '; máximo ' . $r['maximo'];
                }
            }
        }

        if (!empty($abertas)) {
            $linhas[] = '';
            $linhas[] = 'PERGUNTAS ABERTAS (respostas mais recentes, até 15 por pergunta):';
            foreach ($abertas as $p) {
                $n++;
                $perguntas[$n] = array('tipo' => 'aberta', 'dados' => $p);
                $linhas[] = "P{$n}. \"{$p['question_text']}\"";
                foreach (array_slice($p['respostas'], 0, 15) as $resp) {
                    $texto = preg_replace('/\s+/', ' ', trim($resp['texto']));
                    if (mb_strlen($texto, 'UTF-8') > 300) {
                        $texto = mb_substr($texto, 0, 300, 'UTF-8') . '…';
                    }
                    $linhas[] = "   - \"{$texto}\"";
                }
            }
        }

        return array(
            'questionnaire_title' => $questionnaire ? $questionnaire->title : 'Questionário',
            'total_responses'     => $visao['total_responses'],
            'date_range'          => $periodo_txt,
            'data_summary'        => implode("\n", $linhas),
            // usado pelo prompt do relatório narrativo, que nunca recebia dados
            'aggregated_data'     => implode("\n", $linhas),
            'questionnaire_id'    => $questionnaire_id,
            'filters'             => $filters,
            'visao_geral'         => $visao,
            'perguntas'           => $perguntas,
            'por_dia'             => $por_dia,
        );
    }

    /**
     * Mensagens da análise estatística, com o formato da resposta fixo.
     *
     * O prompt cadastrado pedia um JSON vago ({summary, patterns...}) e a IA
     * inventava chaves em inglês. Aqui o formato é definido no código; as
     * diretrizes do administrador para esta função continuam valendo.
     */
    public function build_statistical_messages($context) {
        $system = 'Você é um analista de pesquisas de campo que escreve para o cliente final, em português do Brasil. '
            . 'Baseie-se SOMENTE nos dados fornecidos: não invente números, causas ou perguntas. '
            . 'Cite as perguntas pelo assunto, em linguagem natural, nunca por códigos (P1, q_123), IDs ou valores internos. '
            . 'Use percentuais com vírgula decimal. Se houver poucas entrevistas, diga que os resultados são preliminares. '
            . 'Responda SOMENTE com um JSON válido.';

        $directives = $this->build_directives_block('statistical_analysis');
        if ($directives) {
            $system .= "\n\n" . $directives;
        }

        $user = "Dados da pesquisa:\n\n" . $context['data_summary'] . "\n\n"
            . "Escreva a análise neste formato JSON, com todos os campos em português:\n"
            . "{\n"
            . "  \"resumo\": \"resumo executivo em 2 a 4 parágrafos curtos, separados por \\n\\n\",\n"
            . "  \"destaques\": [\"3 a 6 achados principais, cada um em uma frase com o número que o sustenta\"],\n"
            . "  \"perguntas\": [{\"numero\": <número da pergunta, ex.: 3 para P3>, \"analise\": \"1 a 3 frases sobre o resultado dessa pergunta\"}],\n"
            . "  \"pontos_de_atencao\": [\"resultados que merecem cuidado: avaliações negativas, respostas concentradas, amostra pequena\"],\n"
            . "  \"recomendacoes\": [\"2 a 5 recomendações práticas a partir dos resultados\"]\n"
            . "}\n"
            . "Inclua em \"perguntas\" uma entrada para cada pergunta listada nos dados.";

        return array(
            array('role' => 'system', 'content' => $system),
            array('role' => 'user', 'content' => $user),
        );
    }

    /**
     * Prepara dados para relatórios em linguagem natural
     */
    public function prepare_report_data($questionnaire_id, $filters = array()) {
        return $this->prepare_statistical_data($questionnaire_id, $filters);
    }

    // ============================================================
    // MÉTODOS AUXILIARES
    // ============================================================

    /**
     * Monta bloco de diretrizes do admin para injetar no system prompt
     */
    private function build_directives_block($feature_key) {
        $directives = $this->CI->Ai_model->get_active_directives_for_feature($feature_key);
        if (empty($directives)) {
            return '';
        }

        $type_headers = array(
            'instruction' => 'INSTRUÇÕES DO ADMINISTRADOR',
            'restriction' => 'RESTRIÇÕES E LIMITES (NUNCA VIOLAR)',
            'persona' => 'COMPORTAMENTO E PERSONA',
            'format' => 'FORMATO DE RESPOSTA',
            'context' => 'CONTEXTO ADICIONAL',
        );

        $grouped = array();
        foreach ($directives as $d) {
            $type = $d['directive_type'] ?? 'instruction';
            $grouped[$type][] = $d['content'];
        }

        $blocks = array();
        // Prioridade de tipo: restrições primeiro
        $type_order = array('restriction', 'instruction', 'persona', 'format', 'context');
        foreach ($type_order as $type) {
            if (!isset($grouped[$type])) continue;
            $header = $type_headers[$type] ?? strtoupper($type);
            $items = array();
            foreach ($grouped[$type] as $i => $content) {
                $items[] = ($i + 1) . '. ' . $content;
            }
            $blocks[] = "=== {$header} ===\n" . implode("\n", $items);
        }

        return "--- DIRETRIZES DO ADMINISTRADOR ---\nAs diretrizes abaixo foram definidas pelo administrador do sistema e devem ser seguidas rigorosamente.\n\n" . implode("\n\n", $blocks);
    }

    /**
     * Interpola variáveis no template de prompt
     * Variáveis no formato {{variable_name}}
     */
    private function interpolate($template, $variables) {
        foreach ($variables as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
    }

    /**
     * Extrai valor de resposta de um question_response
     */
    private function extract_answer_value($answer) {
        if (!empty($answer->response_text)) {
            return $answer->response_text;
        }
        if (isset($answer->response_number) && $answer->response_number !== null) {
            return $answer->response_number;
        }
        if (!empty($answer->response_date)) {
            return $answer->response_date;
        }
        if (!empty($answer->response_datetime)) {
            return $answer->response_datetime;
        }
        if (!empty($answer->selected_options)) {
            $options = json_decode($answer->selected_options, true);
            return is_array($options) ? implode(', ', $options) : $answer->selected_options;
        }
        return '';
    }
}
