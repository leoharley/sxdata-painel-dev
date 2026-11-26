<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once __DIR__ . '/Expressao.php';

/**
 * Motor dos formulários estruturados (domicílio + moradores): a partir da
 * definição publicada e das respostas, decide o que é visível, recalcula os
 * campos calculados, valida tipos e obrigatoriedade e levanta alertas.
 *
 * Mesmo comportamento do motor do app (Dart). O servidor usa este motor para
 * conferir cada envio e recalcular os campos; o painel, para a prévia.
 *
 * Respostas: [codigo => ['valor' => ..., 'nsnr' => bool, 'complementos' => [opcao => txt], 'complemento' => txt]]
 * Moradores: [['client_id' => ..., 'ordem' => 1, 'nome' => ..., 'respostas' => [...]]]
 *
 * Classe pura: tests/sxdata/Estrutura_motor_test.php.
 */
class Estrutura_motor {

    const TIPOS = ['texto', 'texto_longo', 'inteiro', 'decimal', 'moeda', 'data', 'hora', 'telefone', 'cpf',
        'cnpj', 'unica', 'multipla', 'composto', 'lista', 'calculado', 'foto', 'coordenadas', 'mensagem'];

    /** Tipos que o entrevistador não responde. */
    const SEM_RESPOSTA = ['calculado', 'mensagem'];

    private $def;
    /** @var array codigo => ['p' => pergunta, 'nivel' => domicilio|morador, 'pos' => int] */
    private $idx = [];
    /** @var array nivel => [codigos na ordem] */
    private $seq = ['domicilio' => [], 'morador' => []];
    /** @var array|null etapa de moradores */
    private $etapa_moradores = NULL;

    public function __construct(array $definicao) {
        $this->def = $definicao;
        foreach ($definicao['etapas'] ?? [] as $etapa) {
            if (($etapa['tipo'] ?? '') === 'moradores') {
                $this->etapa_moradores = $etapa;
                continue;
            }
            $nivel = ($etapa['nivel'] ?? 'domicilio') === 'morador' ? 'morador' : 'domicilio';
            foreach ($etapa['perguntas'] ?? [] as $p) {
                $this->idx[$p['codigo']] = ['p' => $p, 'nivel' => $nivel, 'pos' => count($this->seq[$nivel])];
                $this->seq[$nivel][] = $p['codigo'];
            }
        }
    }

    public function pergunta($codigo) {
        return $this->idx[$codigo]['p'] ?? NULL;
    }

    public function nivel($codigo) {
        return $this->idx[$codigo]['nivel'] ?? NULL;
    }

    public function sequencia($nivel) {
        return $this->seq[$nivel] ?? [];
    }

    public function tem_moradores() {
        return $this->etapa_moradores !== NULL;
    }

    public function etapa_moradores() {
        return $this->etapa_moradores;
    }

    /**
     * Avalia a entrevista inteira.
     *
     * @return array visiveis, calculados, erros, alertas, descartadas
     */
    public function avaliar(array $respostas, array $moradores = []) {
        $dom = new Estrutura_escopo($this, 'domicilio', $respostas, NULL, NULL);
        $escopos = [];
        usort($moradores, function ($a, $b) { return ((int) ($a['ordem'] ?? 0)) <=> ((int) ($b['ordem'] ?? 0)); });
        foreach ($moradores as $i => $m) {
            $cid = (string) ($m['client_id'] ?? ('m' . ($i + 1)));
            $escopos[$cid] = new Estrutura_escopo($this, 'morador', $m['respostas'] ?? [], $m, $dom);
        }
        $dom->definir_moradores($escopos);

        $r = ['visiveis' => ['domicilio' => [], 'moradores' => []],
              'calculados' => ['domicilio' => [], 'moradores' => []],
              'opcoes' => ['domicilio' => [], 'moradores' => []],
              'erros' => [], 'alertas' => [], 'descartadas' => []];

        $this->percorrer($dom, NULL, $r);
        foreach ($escopos as $cid => $esc) {
            $this->percorrer($esc, $cid, $r);
        }

        if ($this->etapa_moradores !== NULL) {
            $em = $this->etapa_moradores;
            if (count($escopos) === 0) {
                $r['erros'][] = ['codigo' => NULL, 'morador' => NULL, 'mensagem' => 'Cadastre ao menos um morador.'];
            }
            foreach ($moradores as $i => $m) {
                if (trim((string) ($m['nome'] ?? '')) === '') {
                    $r['erros'][] = ['codigo' => NULL, 'morador' => (string) ($m['client_id'] ?? ''), 'mensagem' => 'Morador ' . ($i + 1) . ' sem nome.'];
                }
            }
            if (!empty($em['declarado']) && isset($this->idx[$em['declarado']])) {
                $declarado = $dom->valor($em['declarado']);
                if (Expressao::numerico($declarado) && (int) $declarado !== count($escopos)) {
                    $r['alertas'][] = ['codigo' => $em['declarado'], 'morador' => NULL,
                        'mensagem' => sprintf('Foram informadas %d pessoas na questão %s, mas a lista tem %d morador(es).',
                            (int) $declarado, $this->numero($em['declarado']), count($escopos))];
                }
            }
            foreach ($em['regras'] ?? [] as $regra) {
                if ($dom->condicao($regra['se'])) {
                    $item = ['codigo' => NULL, 'morador' => NULL, 'mensagem' => $regra['mensagem']];
                    if (!empty($regra['bloqueia'])) { $r['erros'][] = $item; } else { $r['alertas'][] = $item; }
                }
            }
        }
        return $r;
    }

    private function percorrer(Estrutura_escopo $esc, $cid, array &$r) {
        $nivel = $esc->nivel;
        $vis = []; $calc = []; $opc = [];
        foreach ($this->seq[$nivel] as $codigo) {
            $p = $this->idx[$codigo]['p'];
            $visivel = $esc->visivel($codigo);
            $vis[$codigo] = $visivel;
            $resp = $esc->resposta($codigo);
            if (!$visivel) {
                if ($resp !== NULL && self::respondida($resp)) {
                    $r['descartadas'][] = ['codigo' => $codigo, 'morador' => $cid];
                }
                continue;
            }
            if ($p['tipo'] === 'calculado') {
                $calc[$codigo] = $esc->calculo($codigo);
                continue;
            }
            if (!empty($p['opcoes_de'])) {
                $opc[$codigo] = $esc->opcoes_permitidas($codigo);
            }
            foreach ($this->validar_resposta($p, $resp, $esc) as $msg) {
                $r['erros'][] = ['codigo' => $codigo, 'morador' => $cid, 'mensagem' => $msg];
            }
            foreach ($p['alertas'] ?? [] as $a) {
                if ($esc->condicao($a['se'])) {
                    $r['alertas'][] = ['codigo' => $codigo, 'morador' => $cid, 'mensagem' => $a['mensagem']];
                }
            }
            if (($p['tipo'] === 'cpf') && $resp !== NULL && empty($resp['nsnr']) && self::tem_texto($resp['valor'] ?? NULL)
                && !self::cpf_valido((string) $resp['valor'])) {
                $r['alertas'][] = ['codigo' => $codigo, 'morador' => $cid, 'mensagem' => 'CPF com dígito verificador inválido na questão ' . ($p['numero'] ?? $codigo) . '.'];
            }
        }
        if ($cid === NULL) {
            $r['visiveis']['domicilio'] = $vis;
            $r['calculados']['domicilio'] = $calc;
            $r['opcoes']['domicilio'] = $opc;
        } else {
            $r['visiveis']['moradores'][$cid] = $vis;
            $r['calculados']['moradores'][$cid] = $calc;
            $r['opcoes']['moradores'][$cid] = $opc;
        }
    }

    /** Mensagens de erro da resposta de uma pergunta visível. */
    public function validar_resposta(array $p, $resp, Estrutura_escopo $esc) {
        $tipo = $p['tipo'];
        if (in_array($tipo, self::SEM_RESPOSTA, TRUE) || !empty($p['oculta'])) {
            return [];
        }
        $n = $p['numero'] ?? $p['codigo'];
        $obrig = !array_key_exists('obrigatoria', $p) || $p['obrigatoria'];
        if ($resp === NULL || !self::respondida($resp)) {
            return $obrig ? ["Questão $n é obrigatória."] : [];
        }
        if (!empty($resp['nsnr'])) {
            return empty($p['nsnr']) ? ["Questão $n não aceita NS/NR."] : [];
        }
        $v = $resp['valor'] ?? NULL;
        $erros = [];
        switch ($tipo) {
            case 'inteiro':
                if (!is_numeric($v) || (float) $v != (int) $v) { $erros[] = "Questão $n: informe um número inteiro."; }
                elseif ((int) $v < 0) { $erros[] = "Questão $n: o valor não pode ser negativo."; }
                break;
            case 'decimal':
            case 'moeda':
                if (!is_numeric($v)) { $erros[] = "Questão $n: informe um número."; }
                elseif ((float) $v < 0) { $erros[] = "Questão $n: o valor não pode ser negativo."; }
                break;
            case 'data':
                if (!is_string($v) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                    $erros[] = "Questão $n: data inválida.";
                }
                break;
            case 'hora':
                if (!is_string($v) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) { $erros[] = "Questão $n: hora inválida (use HH:MM)."; }
                break;
            case 'cpf':
                if (strlen(preg_replace('/\D/', '', (string) $v)) !== 11) { $erros[] = "Questão $n: o CPF deve ter 11 dígitos."; }
                break;
            case 'cnpj':
                if (strlen(preg_replace('/\D/', '', (string) $v)) !== 14) { $erros[] = "Questão $n: o CNPJ deve ter 14 dígitos."; }
                break;
            case 'telefone':
                $d = strlen(preg_replace('/\D/', '', (string) $v));
                if ($d < 10 || $d > 11) { $erros[] = "Questão $n: telefone deve ter DDD e 8 ou 9 dígitos."; }
                break;
            case 'unica':
                $ok = array_column($esc->opcoes_permitidas($p['codigo']), 'valor');
                if (!is_string($v) || !in_array($v, $ok, TRUE)) { $erros[] = "Questão $n: opção inválida."; }
                else { $erros = array_merge($erros, $this->checar_complementos($p, [$v], $resp)); }
                break;
            case 'multipla':
                $ok = array_column($esc->opcoes_permitidas($p['codigo']), 'valor');
                if (!is_array($v) || count($v) === 0) { $erros[] = "Questão $n: marque ao menos uma opção."; break; }
                foreach ($v as $x) {
                    if (!in_array($x, $ok, TRUE)) { $erros[] = "Questão $n: opção inválida ($x)."; }
                }
                foreach ($p['opcoes'] ?? [] as $o) {
                    if (!empty($o['exclusiva']) && in_array($o['valor'], $v, TRUE) && count($v) > 1) {
                        $erros[] = "Questão $n: \"" . $o['texto'] . '" não pode ser marcada junto com outras opções.';
                    }
                }
                $erros = array_merge($erros, $this->checar_complementos($p, $v, $resp));
                break;
            case 'composto':
                if (!is_array($v)) { $erros[] = "Questão $n: resposta inválida."; break; }
                foreach ($p['campos'] ?? [] as $c) {
                    $x = $v[$c['codigo']] ?? NULL;
                    if ($x === NULL || $x === '') { $erros[] = "Questão $n: preencha " . $c['rotulo'] . '.'; }
                    elseif (in_array($c['tipo'] ?? 'texto', ['inteiro', 'decimal', 'moeda'], TRUE) && (!is_numeric($x) || $x < 0)) {
                        $erros[] = "Questão $n: " . $c['rotulo'] . ' deve ser um número.';
                    }
                }
                break;
            case 'lista':
                if (!is_array($v) || !self::tem_texto(implode('', array_map('strval', $v)))) { $erros[] = "Questão $n: informe ao menos um item."; }
                elseif (count($v) > (int) ($p['itens'] ?? 99)) { $erros[] = "Questão $n: no máximo " . (int) $p['itens'] . ' itens.'; }
                break;
            case 'coordenadas':
                if (!is_array($v) || !is_numeric($v['lat'] ?? NULL) || !is_numeric($v['lng'] ?? NULL)
                    || abs((float) $v['lat']) > 90 || abs((float) $v['lng']) > 180) {
                    $erros[] = "Questão $n: coordenadas inválidas.";
                }
                break;
        }
        if (($p['validacao'] ?? '') === 'anos_virgula' && is_string($v)
            && !preg_match('/^\s*(19|20)\d{2}(\s*[,;]\s*(19|20)\d{2})*\s*$/', $v)) {
            $erros[] = "Questão $n: informe os anos com 4 dígitos, separados por vírgula (ex.: 2019, 2022).";
        }
        if (!empty($p['complemento']['obrigatorio']) && !self::tem_texto($resp['complemento'] ?? NULL)) {
            $erros[] = "Questão $n: preencha \"" . $p['complemento']['rotulo'] . '".';
        }
        return $erros;
    }

    private function checar_complementos(array $p, array $marcadas, array $resp) {
        $erros = [];
        foreach ($p['opcoes'] ?? [] as $o) {
            if (empty($o['complemento']) || !in_array($o['valor'], $marcadas, TRUE)) { continue; }
            $c = $resp['complementos'][$o['valor']] ?? NULL;
            $tipo = $o['complemento']['tipo'] ?? 'texto';
            if (!self::tem_texto(is_scalar($c) ? (string) $c : NULL)) {
                if (!array_key_exists('obrigatorio', $o['complemento']) || $o['complemento']['obrigatorio']) {
                    $erros[] = 'Questão ' . ($p['numero'] ?? $p['codigo']) . ': preencha "' . $o['complemento']['rotulo'] . '" da opção "' . $o['texto'] . '".';
                }
            } elseif ($tipo === 'inteiro' && (!is_numeric($c) || (int) $c < 0)) {
                $erros[] = 'Questão ' . ($p['numero'] ?? $p['codigo']) . ': "' . $o['complemento']['rotulo'] . '" deve ser um número.';
            }
        }
        return $erros;
    }

    private function numero($codigo) {
        return $this->idx[$codigo]['p']['numero'] ?? $codigo;
    }

    public static function respondida($resp) {
        if (!is_array($resp)) { return FALSE; }
        if (!empty($resp['nsnr'])) { return TRUE; }
        return Expressao::preenchido($resp['valor'] ?? NULL);
    }

    private static function tem_texto($v) {
        return is_scalar($v) && trim((string) $v) !== '';
    }

    public static function cpf_valido($cpf) {
        $c = preg_replace('/\D/', '', $cpf);
        if (strlen($c) !== 11 || preg_match('/^(\d)\1{10}$/', $c)) { return FALSE; }
        for ($t = 9; $t < 11; $t++) {
            $s = 0;
            for ($i = 0; $i < $t; $i++) { $s += (int) $c[$i] * (($t + 1) - $i); }
            if ((int) $c[$t] !== ((10 * $s) % 11) % 10) { return FALSE; }
        }
        return TRUE;
    }

    /**
     * Resposta em texto legível (tela da entrevista, exportação).
     * @param bool $numero_cru números (moeda, inteiro, calculado) sem formatação, para planilhas
     */
    public static function rotulo_resposta(array $p, $resp, $numero_cru = FALSE) {
        if (!is_array($resp)) { return ''; }
        if (!empty($resp['nsnr'])) { return 'NS/NR'; }
        $v = $resp['valor'] ?? NULL;
        if ($v === NULL || $v === '' || $v === []) { return ''; }
        $tipo = $p['tipo'];
        $formato = $tipo === 'calculado' ? ($p['formato'] ?? 'decimal') : $tipo;
        $opcoes = [];
        foreach ($p['opcoes'] ?? [] as $o) { $opcoes[$o['valor']] = $o; }
        $opcao = function ($valor) use ($opcoes, $resp) {
            $o = $opcoes[$valor] ?? NULL;
            $t = $o ? preg_replace('/\s*[\.:]?\s*(Quant[oa]s|Qual\(is\)|Qual|Quais|Descreva quais|Quem\/como|Onde\?).*$/u', '', $o['texto']) : (string) $valor;
            $c = $resp['complementos'][$valor] ?? NULL;
            return ($c !== NULL && $c !== '') ? "$t: $c" : $t;
        };
        switch ($tipo) {
            case 'unica': $txt = $opcao($v); break;
            case 'multipla': $txt = implode('; ', array_map($opcao, (array) $v)); break;
            case 'composto':
                $partes = [];
                foreach ($p['campos'] ?? [] as $c) {
                    if (isset($v[$c['codigo']]) && $v[$c['codigo']] !== '') { $partes[] = $v[$c['codigo']] . ' ' . $c['rotulo']; }
                }
                $txt = implode(', ', $partes);
                break;
            case 'lista': $txt = implode('; ', array_filter(array_map('strval', (array) $v), 'strlen')); break;
            case 'coordenadas': $txt = is_array($v) ? (($v['lat'] ?? '') . ', ' . ($v['lng'] ?? '')) : (string) $v; break;
            case 'data': $txt = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $v, $m) ? "$m[3]/$m[2]/$m[1]" : (string) $v; break;
            default:
                if (is_numeric($v) && in_array($formato, ['moeda', 'decimal', 'inteiro', 'duracao', 'auto'], TRUE)) {
                    if ($numero_cru) { return $v + 0; }
                    if ($formato === 'moeda') { $txt = 'R$ ' . number_format((float) $v, 2, ',', '.'); }
                    elseif ($formato === 'duracao') { $txt = intdiv((int) $v, 60) . ' h ' . ((int) $v % 60) . ' min'; }
                    elseif ($formato === 'decimal') { $txt = number_format((float) $v, 2, ',', '.'); }
                    else { $txt = (string) ($v + 0); }
                } else {
                    $txt = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
                }
        }
        if (isset($resp['complemento']) && $resp['complemento'] !== '') {
            $txt .= ' | ' . ($p['complemento']['rotulo'] ?? 'Complemento') . ': ' . $resp['complemento'];
        }
        return $txt;
    }

    /** Texto da pergunta com o nome do morador no lugar de [NOME]. */
    public static function texto_para(array $p, $nome) {
        return str_replace('[NOME]', $nome !== NULL && $nome !== '' ? $nome : 'o(a) morador(a)', $p['texto'] ?? '');
    }
}

/**
 * Escopo de avaliação: o domicílio, ou um morador (que enxerga também as
 * respostas do domicílio). Memoriza visibilidade e cálculos.
 */
class Estrutura_escopo {

    public $nivel;
    private $motor;
    private $respostas;
    private $morador;
    /** @var Estrutura_escopo|null domicílio (para o escopo de morador) */
    private $pai;
    /** @var Estrutura_escopo[] moradores (para o escopo do domicílio) */
    private $moradores = [];
    private $memo_vis = [];
    private $memo_calc = [];
    private $em_curso = [];

    public function __construct(Estrutura_motor $motor, $nivel, array $respostas, $morador, $pai) {
        $this->motor = $motor;
        $this->nivel = $nivel;
        $this->respostas = $respostas;
        $this->morador = $morador;
        $this->pai = $pai;
    }

    public function definir_moradores(array $escopos) {
        $this->moradores = array_values($escopos);
    }

    public function resposta($codigo) {
        $r = $this->respostas[$codigo] ?? NULL;
        return is_array($r) ? $r : NULL;
    }

    private function expressao() {
        $self = $this;
        $moradores = function () use ($self) {
            $lista = [];
            foreach ($self->escopos_moradores() as $m) {
                $lista[] = function ($nome, $ev) use ($m) { return $m->valor($nome, $ev); };
            }
            return $lista;
        };
        return new Expressao(function ($nome, $ev) use ($self) { return $self->valor($nome, $ev); }, $moradores);
    }

    public function escopos_moradores() {
        return $this->nivel === 'domicilio' ? $this->moradores : ($this->pai ? $this->pai->escopos_moradores() : []);
    }

    public function condicao($texto) {
        try {
            return $this->expressao()->condicao($texto);
        } catch (InvalidArgumentException $e) {
            return FALSE;
        }
    }

    /**
     * Valor de uma pergunta neste escopo, como a linguagem enxerga: nulo se não
     * respondida ou não aplicável (oculta), NS/NR, número, texto, lista.
     */
    public function valor($codigo, $ev = NULL) {
        $nivel = $this->motor->nivel($codigo);
        if ($nivel === NULL) {
            return NULL;
        }
        if ($nivel !== $this->nivel) {
            // Morador enxerga o domicílio; o domicílio só vê morador por soma_moradores etc.
            return ($nivel === 'domicilio' && $this->pai) ? $this->pai->valor($codigo, $ev) : NULL;
        }
        if (!$this->visivel($codigo)) {
            return NULL;
        }
        $p = $this->motor->pergunta($codigo);
        if ($p['tipo'] === 'calculado') {
            $c = $this->calculo($codigo);
            if ($c['incompleto'] && $ev) { $ev->marcar_incompleto(); }
            return $c['valor'];
        }
        $r = $this->resposta($codigo);
        if ($r === NULL) { return NULL; }
        if (!empty($r['nsnr'])) { return Expressao::NSNR; }
        $v = $r['valor'] ?? NULL;
        if (in_array($p['tipo'], ['inteiro', 'decimal', 'moeda'], TRUE)) {
            return is_numeric($v) ? $v + 0 : NULL;
        }
        return $v;
    }

    public function visivel($codigo) {
        if (array_key_exists($codigo, $this->memo_vis)) {
            return $this->memo_vis[$codigo];
        }
        if (isset($this->em_curso['v' . $codigo])) {
            return FALSE; // referência circular: tratada como não aplicável
        }
        $this->em_curso['v' . $codigo] = TRUE;
        $p = $this->motor->pergunta($codigo);
        $ok = empty($p['exibir_se']) || $this->condicao($p['exibir_se']);
        if ($ok) {
            // Pulo: uma pergunta anterior visível manda pular para depois desta
            $seq = $this->motor->sequencia($this->nivel);
            $pos = array_search($codigo, $seq, TRUE);
            for ($i = 0; $i < $pos && $ok; $i++) {
                $q = $this->motor->pergunta($seq[$i]);
                if (empty($q['pular'])) { continue; }
                $destino = array_search($q['pular']['para'], $seq, TRUE);
                if ($destino !== FALSE && $destino > $pos && $this->visivel($seq[$i]) && $this->condicao($q['pular']['se'])) {
                    $ok = FALSE;
                }
            }
        }
        unset($this->em_curso['v' . $codigo]);
        return $this->memo_vis[$codigo] = $ok;
    }

    /** ['valor' => ..., 'incompleto' => bool] de um campo calculado. */
    public function calculo($codigo) {
        if (array_key_exists($codigo, $this->memo_calc)) {
            return $this->memo_calc[$codigo];
        }
        if (isset($this->em_curso['c' . $codigo])) {
            return ['valor' => NULL, 'incompleto' => FALSE];
        }
        $this->em_curso['c' . $codigo] = TRUE;
        $p = $this->motor->pergunta($codigo);
        try {
            $r = $this->expressao()->avaliar($p['formula'] ?? '');
        } catch (InvalidArgumentException $e) {
            $r = ['valor' => NULL, 'incompleto' => FALSE];
        }
        if (is_float($r['valor']) || is_int($r['valor'])) {
            // moeda/decimal: 2 casas (float); inteiro/duração: inteiro; auto: mantém o tipo
            $fmt = $p['formato'] ?? 'decimal';
            if (in_array($fmt, ['inteiro', 'duracao'], TRUE)) {
                $r['valor'] = (int) round($r['valor']);
            } elseif ($fmt !== 'auto' || is_float($r['valor'])) {
                $r['valor'] = (float) round($r['valor'], 2);
            }
        } elseif (is_bool($r['valor'])) {
            $r['valor'] = $r['valor'] ? 1 : 0;
        }
        unset($this->em_curso['c' . $codigo]);
        return $this->memo_calc[$codigo] = $r;
    }

    /** Opções que podem ser escolhidas (com "opcoes_de", só as marcadas na outra pergunta). */
    public function opcoes_permitidas($codigo) {
        $p = $this->motor->pergunta($codigo);
        $opcoes = $p['opcoes'] ?? [];
        if (empty($p['opcoes_de'])) {
            return $opcoes;
        }
        $origem = $this->valor($p['opcoes_de']);
        $marcadas = is_array($origem) ? $origem : ($origem !== NULL && $origem !== Expressao::NSNR ? [$origem] : []);
        return array_values(array_filter($opcoes, function ($o) use ($marcadas) {
            return !empty($o['sempre']) || in_array($o['valor'], $marcadas, TRUE);
        }));
    }
}
