<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Conferência do envio de um questionário simples (api/forms/submit).
 *
 * Não recusa nada: o envio continua sendo gravado como hoje, e o que estiver
 * inconsistente vai para a fila de revisão do painel. Por isso a regra é
 * conservadora: só aponta o que é certamente um problema.
 *
 * - pergunta que não é deste questionário;
 * - pergunta respondida duas vezes;
 * - número que não é número; e-mail inválido; data inválida;
 * - opção que não existe na pergunta (compara valor e texto da opção);
 * - obrigatória sem resposta, quando a pergunta está visível. A condição de
 *   visibilidade aceita tanto o texto quanto o valor da opção (o editor grava
 *   o texto, o app compara com o valor); na dúvida, não aponta.
 */
class Validacao_simples {

    /**
     * @param array $perguntas [id => ['texto','tipo','obrigatoria','logica','opcoes' => [[valor, texto], ...]]]
     * @param array $respostas lista do campo "responses" do envio
     * @return array lista de mensagens (vazia = sem pendência)
     */
    public static function conferir(array $perguntas, array $respostas) {
        $erros = [];
        $valores = [];
        $vistas = [];
        foreach ($respostas as $r) {
            if (!is_array($r) || !isset($r['question_id'])) {
                continue;
            }
            $qid = (int) $r['question_id'];
            if (!isset($perguntas[$qid])) {
                $erros[] = "Resposta para a pergunta {$qid}, que não é deste questionário.";
                continue;
            }
            if (isset($vistas[$qid])) {
                $erros[] = 'Pergunta respondida duas vezes: ' . self::nome($perguntas[$qid]) . '.';
            }
            $vistas[$qid] = TRUE;
            $valor = self::valor($r);
            $valores[$qid] = $valor;
            if (self::vazio($valor)) {
                continue;
            }
            $p = $perguntas[$qid];
            $tipo = $p['tipo'];
            if ($tipo === 'number') {
                $n = isset($r['response_number']) && $r['response_number'] !== '' ? $r['response_number'] : ($r['response_text'] ?? NULL);
                if ($n !== NULL && $n !== '' && !is_numeric(str_replace(',', '.', (string) $n))) {
                    $erros[] = self::nome($p) . ': "' . self::curto($n) . '" não é um número.';
                }
            } elseif ($tipo === 'email') {
                $t = trim((string) (is_array($valor) ? implode(',', $valor) : $valor));
                if ($t !== '' && !filter_var($t, FILTER_VALIDATE_EMAIL)) {
                    $erros[] = self::nome($p) . ': e-mail inválido ("' . self::curto($t) . '").';
                }
            } elseif ($tipo === 'date') {
                $d = $r['response_date'] ?? ($r['response_text'] ?? '');
                if ($d !== '' && strtotime((string) $d) === FALSE) {
                    $erros[] = self::nome($p) . ': data inválida ("' . self::curto($d) . '").';
                }
            } elseif (in_array($tipo, ['radio', 'checkbox', 'select', 'dropdown'], TRUE) && !empty($p['opcoes'])) {
                $lista = is_array($valor) ? $valor : [$valor];
                if ($tipo !== 'checkbox' && count($lista) > 1) {
                    $erros[] = self::nome($p) . ': mais de uma opção marcada numa pergunta de resposta única.';
                }
                foreach ($lista as $v) {
                    if (!self::opcao_existe($p['opcoes'], $v)) {
                        $erros[] = self::nome($p) . ': a opção "' . self::curto($v) . '" não existe na pergunta.';
                    }
                }
            }
        }

        foreach ($perguntas as $qid => $p) {
            $logica = self::logica($p['logica'] ?? NULL);
            $obrigatoria = !empty($p['obrigatoria']);
            if (!$obrigatoria && empty($logica['required'])) {
                continue;
            }
            if (!self::vazio($valores[$qid] ?? NULL)) {
                continue;
            }
            if (!empty($logica['visibility'])) {
                $vis = self::regra($logica['visibility'], $valores, $perguntas);
                if ($vis !== TRUE) {
                    continue; // oculta ou incerta: não aponta
                }
            }
            if (!$obrigatoria && self::regra($logica['required'], $valores, $perguntas) !== TRUE) {
                continue;
            }
            $erros[] = 'Obrigatória sem resposta: ' . self::nome($p) . '.';
        }
        return array_values(array_unique($erros));
    }

    /** Valor de uma resposta do envio: lista (checkbox), texto ou número. */
    public static function valor($r) {
        if (isset($r['selected_options']) && $r['selected_options'] !== '' && $r['selected_options'] !== NULL) {
            $s = $r['selected_options'];
            if (is_string($s)) {
                $d = json_decode($s, TRUE);
                $s = is_array($d) ? $d : [$s];
            }
            $s = array_values(array_filter(array_map(function ($x) { return is_scalar($x) ? trim((string) $x) : ''; }, (array) $s), 'strlen'));
            if ($s) {
                return $s;
            }
        }
        foreach (['response_number', 'response_text', 'response_date', 'response_datetime'] as $k) {
            if (isset($r[$k]) && trim((string) $r[$k]) !== '') {
                return is_string($r[$k]) ? trim($r[$k]) : $r[$k];
            }
        }
        return NULL;
    }

    private static function vazio($v) {
        return $v === NULL || $v === '' || $v === [];
    }

    private static function chave($s) {
        $s = function_exists('mb_strtolower') ? mb_strtolower(trim((string) $s), 'UTF-8') : strtolower(trim((string) $s));
        return preg_replace('/\s+/', ' ', $s);
    }

    private static function opcao_existe($opcoes, $v) {
        $k = self::chave($v);
        foreach ($opcoes as $o) {
            if (self::chave($o[0] ?? '') === $k || self::chave($o[1] ?? '') === $k) {
                return TRUE;
            }
        }
        return FALSE;
    }

    private static function logica($l) {
        if (is_string($l)) {
            $l = json_decode($l, TRUE);
        }
        return is_array($l) ? $l : [];
    }

    /** TRUE, FALSE ou NULL (não dá para saber: operador desconhecido). */
    private static function regra($regra, $valores, $perguntas) {
        if (!is_array($regra) || empty($regra['conditions'])) {
            return TRUE;
        }
        $res = [];
        foreach ($regra['conditions'] as $c) {
            if (!isset($c['question'], $c['operator'])) {
                continue;
            }
            $qid = (int) $c['question'];
            $res[] = self::condicao($valores[$qid] ?? NULL, $c['operator'], $c['value'] ?? '', $perguntas[$qid]['opcoes'] ?? []);
        }
        if (!$res) {
            return TRUE;
        }
        if (in_array(NULL, $res, TRUE)) {
            return NULL;
        }
        return (($regra['operator'] ?? 'AND') === 'OR') ? in_array(TRUE, $res, TRUE) : !in_array(FALSE, $res, TRUE);
    }

    /** Compara aceitando o valor ou o texto da opção como equivalentes. */
    private static function condicao($resp, $op, $cond, $opcoes) {
        $equivalentes = [self::chave($cond)];
        foreach ($opcoes as $o) {
            if (self::chave($o[0] ?? '') === $equivalentes[0] || self::chave($o[1] ?? '') === $equivalentes[0]) {
                $equivalentes[] = self::chave($o[0] ?? '');
                $equivalentes[] = self::chave($o[1] ?? '');
            }
        }
        $lista = $resp === NULL ? [] : array_map([__CLASS__, 'chave'], is_array($resp) ? $resp : [$resp]);
        $tem = (bool) array_intersect($lista, $equivalentes);
        $texto = implode(',', $lista);
        switch ($op) {
            case 'equals':       return $tem;
            case 'not_equals':   return !$tem;
            case 'contains':     return $tem || ($equivalentes[0] !== '' && strpos($texto, $equivalentes[0]) !== FALSE);
            case 'not_contains': return !($tem || ($equivalentes[0] !== '' && strpos($texto, $equivalentes[0]) !== FALSE));
            case 'greater_than': return is_numeric($texto) && is_numeric($cond) ? (float) $texto > (float) $cond : FALSE;
            case 'less_than':    return is_numeric($texto) && is_numeric($cond) ? (float) $texto < (float) $cond : FALSE;
            case 'is_empty':     return $texto === '' || $texto === '0';
            case 'is_not_empty': return $texto !== '' && $texto !== '0';
            default:             return NULL;
        }
    }

    private static function nome($p) {
        $t = trim(strip_tags((string) ($p['texto'] ?? '')));
        return '"' . self::curto($t, 70) . '"';
    }

    private static function curto($s, $n = 40) {
        $s = is_array($s) ? implode(', ', $s) : (string) $s;
        return (function_exists('mb_strlen') && mb_strlen($s) > $n) ? mb_substr($s, 0, $n - 1) . '…' : $s;
    }
}
