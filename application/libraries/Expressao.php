<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Linguagem de expressões dos formulários estruturados: a mesma serve para
 * condição de exibição, pulo, alerta, regra de validação e campo calculado.
 * O app (Dart) implementa exatamente a mesma linguagem; os casos de
 * tests/fixtures/expressoes.json valem para os dois motores.
 *
 * Sintaxe (exemplos):
 *   q27 == "sim"                         q48 em ["sim_trabalhou", "afastado"]
 *   q57 comeca "sim"                     q87 contem "no_proprio_imovel"
 *   nao (q49 em ["casa", "anexo"] e q24 == "sim")
 *   idade >= 5                           preenchido(q100)      nsnr(q82)
 *   soma_moradores(q53 + q55, q19 fora_de ["pensionista"]) + q102
 *   div(q109, contar_moradores(q19 != "pensionista"))
 *
 * Valores: número, texto, lista (múltipla escolha), nulo (sem resposta ou
 * pergunta não aplicável) e NS/NR. Na conta, nulo vale 0 (não aplicável) e
 * NS/NR também vale 0, mas marca o resultado como incompleto: a renda com um
 * componente NS/NR sai sinalizada como "incompleta", nunca como se fosse 0.
 *
 * Classe pura (sem CodeIgniter, sem banco): tests/sxdata/Expressao_test.php.
 */
class Expressao {

    /** Marcador da resposta "NS/NR" (não sabe / não respondeu). */
    const NSNR = "\0NSNR";

    const PALAVRAS = ['e', 'ou', 'nao', 'em', 'fora_de', 'contem', 'comeca', 'verdadeiro', 'falso', 'nulo'];

    /** Funções que recebem a expressão sem avaliar (avaliam por morador). */
    const FUNCOES_MORADOR = ['soma_moradores', 'contar_moradores', 'existe_morador', 'todos_moradores'];

    const FUNCOES = ['soma', 'min', 'max', 'arred', 'div', 'abs', 'se', 'preenchido', 'vazio', 'nsnr',
        'tem_valor', 'contar', 'idade', 'duracao', 'soma_moradores', 'contar_moradores',
        'existe_morador', 'todos_moradores'];

    private static $cache = [];

    /** @var bool algum NS/NR entrou numa conta durante a última avaliação */
    private $incompleto = FALSE;
    /** @var callable fn(string $nome) => valor */
    private $resolver;
    /** @var callable|null fn() => lista de callables resolvedores, um por morador */
    private $moradores;

    // ------------------------------------------------------------------
    // API
    // ------------------------------------------------------------------

    /**
     * Compila (com cache) e devolve a árvore. Lança InvalidArgumentException
     * com mensagem em português se a expressão for inválida.
     */
    public static function compilar($texto) {
        $texto = (string) $texto;
        if (!isset(self::$cache[$texto])) {
            $p = new Expressao_parser(self::tokens($texto));
            self::$cache[$texto] = $p->tudo();
        }
        return self::$cache[$texto];
    }

    /** [ok, mensagem de erro] sem lançar exceção. */
    public static function validar($texto) {
        try {
            self::compilar($texto);
            return [TRUE, NULL];
        } catch (InvalidArgumentException $e) {
            return [FALSE, $e->getMessage()];
        }
    }

    /** Nomes de perguntas referenciados (para validar o formulário). */
    public static function referencias($texto) {
        $nomes = [];
        self::coletar(self::compilar($texto), $nomes);
        return array_values(array_unique($nomes));
    }

    /**
     * @param callable $resolver fn(string $codigo) => valor no escopo atual
     * @param callable|null $moradores fn() => [callable resolvedor por morador]
     */
    public function __construct(callable $resolver, $moradores = NULL) {
        $this->resolver = $resolver;
        $this->moradores = $moradores;
    }

    /**
     * Avalia e devolve ['valor' => ..., 'incompleto' => bool].
     */
    public function avaliar($texto) {
        $this->incompleto = FALSE;
        $v = $this->ev(self::compilar($texto));
        return ['valor' => $v === self::NSNR ? NULL : $v, 'incompleto' => $this->incompleto];
    }

    /** Avalia como condição (verdadeiro/falso). */
    public function condicao($texto) {
        $this->incompleto = FALSE;
        return self::verdade($this->ev(self::compilar($texto)));
    }

    /** Usado pelo motor: valor calculado de outra pergunta repassa o "incompleto". */
    public function marcar_incompleto() {
        $this->incompleto = TRUE;
    }

    // ------------------------------------------------------------------
    // Avaliação
    // ------------------------------------------------------------------

    private function ev($n) {
        switch ($n[0]) {
            case 'num': return $n[1];
            case 'str': return $n[1];
            case 'bool': return $n[1];
            case 'nulo': return NULL;
            case 'ref': return call_user_func($this->resolver, $n[1], $this);
            case 'lista':
                $l = [];
                foreach ($n[1] as $item) {
                    $l[] = $this->ev($item);
                }
                return $l;
            case 'neg': return -$this->num($this->ev($n[1]));
            case 'nao': return !self::verdade($this->ev($n[1]));
            case 'e': return self::verdade($this->ev($n[1])) && self::verdade($this->ev($n[2]));
            case 'ou': return self::verdade($this->ev($n[1])) || self::verdade($this->ev($n[2]));
            case 'bin': return $this->binario($n[1], $this->ev($n[2]), $this->ev($n[3]));
            case 'call': return $this->funcao($n[1], $n[2]);
        }
        throw new InvalidArgumentException('Nó desconhecido: ' . $n[0]);
    }

    private function binario($op, $a, $b) {
        switch ($op) {
            case '+': return $this->num($a) + $this->num($b);
            case '-': return $this->num($a) - $this->num($b);
            case '*': return $this->num($a) * $this->num($b);
            case '/':
                $d = $this->num($b);
                return $d == 0 ? NULL : $this->num($a) / $d;
            case '==': return self::igual($a, $b);
            case '!=': return !self::igual($a, $b);
            case '>': case '>=': case '<': case '<=':
                if (!self::numerico($a) || !self::numerico($b)) {
                    return FALSE; // sem resposta, NS/NR ou texto: comparação de ordem é falsa
                }
                $x = (float) $a; $y = (float) $b;
                return $op === '>' ? $x > $y : ($op === '>=' ? $x >= $y : ($op === '<' ? $x < $y : $x <= $y));
            case 'em': return self::em($a, $b);
            case 'fora_de': return !self::em($a, $b);
            case 'contem': return self::contem($a, $b);
            case 'comeca': return self::comeca($a, $b);
        }
        throw new InvalidArgumentException("Operador desconhecido: $op");
    }

    private function funcao($nome, $args) {
        if (in_array($nome, self::FUNCOES_MORADOR, TRUE)) {
            return $this->por_morador($nome, $args);
        }
        if ($nome === 'se') {
            if (count($args) !== 3) {
                throw new InvalidArgumentException('se() precisa de 3 argumentos: se(condição, valor_se_sim, valor_se_não).');
            }
            return self::verdade($this->ev($args[0])) ? $this->ev($args[1]) : $this->ev($args[2]);
        }
        $v = [];
        foreach ($args as $a) {
            $v[] = $this->ev($a);
        }
        switch ($nome) {
            case 'soma':
                $t = 0;
                foreach (self::achatar($v) as $x) {
                    $t += $this->num($x);
                }
                return $t;
            case 'min':
            case 'max':
                $nums = [];
                foreach (self::achatar($v) as $x) {
                    if ($x === self::NSNR) { $this->incompleto = TRUE; continue; }
                    if (self::numerico($x)) { $nums[] = (float) $x; }
                }
                return $nums ? ($nome === 'min' ? min($nums) : max($nums)) : NULL;
            case 'abs': return abs($this->num($v[0] ?? NULL));
            case 'arred':
                if (($v[0] ?? NULL) === NULL) { return NULL; }
                return round($this->num($v[0]), (int) ($v[1] ?? 0));
            case 'div':
                // Divisão por zero (ex.: nenhum morador considerado) fica vazia
                $den = $this->num($v[1] ?? NULL);
                $num = $this->num($v[0] ?? NULL);
                return $den == 0 ? NULL : $num / $den;
            case 'preenchido': return self::preenchido($v[0] ?? NULL);
            case 'vazio': return !self::preenchido($v[0] ?? NULL);
            case 'nsnr': return ($v[0] ?? NULL) === self::NSNR;
            case 'tem_valor': return self::preenchido($v[0] ?? NULL) && ($v[0] ?? NULL) !== self::NSNR;
            case 'contar':
                $x = $v[0] ?? NULL;
                return is_array($x) ? count($x) : (self::preenchido($x) && $x !== self::NSNR ? 1 : 0);
            case 'idade': return self::idade($v[0] ?? NULL, $v[1] ?? NULL);
            case 'duracao': return self::duracao($v[0] ?? NULL, $v[1] ?? NULL);
        }
        throw new InvalidArgumentException("Função desconhecida: $nome()");
    }

    /** soma_moradores(expr[, condição]), contar_moradores([condição]), existe_morador(cond), todos_moradores(cond) */
    private function por_morador($nome, $args) {
        $lista = $this->moradores ? call_user_func($this->moradores) : [];
        $externo = $this->resolver;
        $total = 0; $conta = 0; $existe = FALSE; $todos = TRUE;
        foreach ($lista as $resolver_morador) {
            $this->resolver = $resolver_morador;
            try {
                if ($nome === 'soma_moradores') {
                    if (!isset($args[0])) {
                        throw new InvalidArgumentException('soma_moradores() precisa de uma expressão.');
                    }
                    if (isset($args[1]) && !self::verdade($this->ev($args[1]))) {
                        continue;
                    }
                    $total += $this->num($this->ev($args[0]));
                } else {
                    $ok = isset($args[0]) ? self::verdade($this->ev($args[0])) : TRUE;
                    if ($ok) { $conta++; $existe = TRUE; } else { $todos = FALSE; }
                }
            } finally {
                $this->resolver = $externo;
            }
        }
        switch ($nome) {
            case 'soma_moradores': return $total;
            case 'contar_moradores': return $conta;
            case 'existe_morador': return $existe;
            default: return $todos;
        }
    }

    // ------------------------------------------------------------------
    // Semântica dos valores
    // ------------------------------------------------------------------

    private function num($v) {
        if ($v === self::NSNR) {
            $this->incompleto = TRUE;
            return 0;
        }
        if (is_bool($v)) { return $v ? 1 : 0; }
        if (self::numerico($v)) { return $v + 0; }
        return 0; // nulo (não aplicável) e texto valem 0 na conta
    }

    public static function numerico($v) {
        return (is_int($v) || is_float($v)) || (is_string($v) && $v !== self::NSNR && is_numeric($v));
    }

    public static function verdade($v) {
        if ($v === NULL || $v === self::NSNR || $v === FALSE || $v === '' || $v === []) { return FALSE; }
        if (self::numerico($v)) { return (float) $v != 0; }
        return TRUE;
    }

    public static function preenchido($v) {
        if ($v === NULL || $v === '' || $v === []) { return FALSE; }
        if (is_string($v) && trim($v) === '') { return FALSE; }
        if (is_array($v)) {
            foreach ($v as $x) { if (self::preenchido($x)) { return TRUE; } }
            return FALSE;
        }
        return TRUE;
    }

    private static function norm($v) {
        if (is_bool($v)) { return $v ? '1' : '0'; }
        if (self::numerico($v)) { return (string) ($v + 0); }
        return mb_strtolower(trim((string) $v));
    }

    /** Igualdade: com lista (múltipla escolha), "==" significa "foi marcada". */
    private static function igual($a, $b) {
        if ($a === self::NSNR || $b === self::NSNR) { return $a === $b; }
        if ($a === NULL || $b === NULL) { return $a === $b; }
        if (is_array($a) && !is_array($b)) { return self::contem($a, $b); }
        if (is_array($b) && !is_array($a)) { return self::contem($b, $a); }
        if (is_array($a) && is_array($b)) {
            $x = array_map([__CLASS__, 'norm'], $a); sort($x);
            $y = array_map([__CLASS__, 'norm'], $b); sort($y);
            return $x === $y;
        }
        if (self::numerico($a) && self::numerico($b)) { return (float) $a == (float) $b; }
        return self::norm($a) === self::norm($b);
    }

    /** x em [lista]: x (ou algum item de x, se for múltipla) está na lista. */
    private static function em($a, $lista) {
        if ($a === NULL || $a === self::NSNR) { return FALSE; }
        $lista = is_array($lista) ? $lista : [$lista];
        foreach ((is_array($a) ? $a : [$a]) as $x) {
            foreach ($lista as $y) {
                if (self::igual($x, $y)) { return TRUE; }
            }
        }
        return FALSE;
    }

    /** lista contem valor (ou algum valor de uma lista); texto contem trecho. */
    private static function contem($a, $b) {
        if ($a === NULL || $a === self::NSNR || $b === NULL) { return FALSE; }
        if (is_array($a)) {
            foreach ((is_array($b) ? $b : [$b]) as $y) {
                foreach ($a as $x) {
                    if (self::igual($x, $y)) { return TRUE; }
                }
            }
            return FALSE;
        }
        return mb_strpos(self::norm($a), self::norm($b)) !== FALSE;
    }

    private static function comeca($a, $b) {
        if ($a === NULL || $a === self::NSNR || $b === NULL) { return FALSE; }
        $p = self::norm($b);
        foreach ((is_array($a) ? $a : [$a]) as $x) {
            if ($p === '' || mb_strpos(self::norm($x), $p) === 0) { return TRUE; }
        }
        return FALSE;
    }

    private static function achatar($v) {
        $r = [];
        foreach ($v as $x) {
            if (is_array($x)) { foreach (self::achatar($x) as $y) { $r[] = $y; } } else { $r[] = $x; }
        }
        return $r;
    }

    /** Idade em anos completos entre a data de nascimento e a data de referência (Y-m-d). */
    public static function idade($nasc, $ref) {
        $n = self::data($nasc);
        $r = self::data($ref);
        if (!$n || !$r || $n > $r) { return NULL; }
        list($ny, $nm, $nd) = array_map('intval', explode('-', $n));
        list($ry, $rm, $rd) = array_map('intval', explode('-', $r));
        $anos = $ry - $ny;
        if ($rm < $nm || ($rm === $nm && $rd < $nd)) { $anos--; }
        return $anos;
    }

    /** Minutos entre duas horas HH:MM (passando da meia-noite, soma 24 h). */
    public static function duracao($ini, $fim) {
        $a = self::minutos($ini);
        $b = self::minutos($fim);
        if ($a === NULL || $b === NULL) { return NULL; }
        return $b >= $a ? $b - $a : $b + 1440 - $a;
    }

    private static function data($v) {
        if (!is_string($v)) { return NULL; }
        $v = trim($v);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "$m[1]-$m[2]-$m[3]";
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $v, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return "$m[3]-$m[2]-$m[1]";
        }
        return NULL;
    }

    private static function minutos($v) {
        if (!is_string($v) || !preg_match('/^(\d{1,2}):(\d{2})/', trim($v), $m)) { return NULL; }
        $h = (int) $m[1]; $i = (int) $m[2];
        return ($h < 24 && $i < 60) ? $h * 60 + $i : NULL;
    }

    // ------------------------------------------------------------------
    // Léxico
    // ------------------------------------------------------------------

    public static function tokens($s) {
        $t = [];
        $i = 0;
        $n = strlen($s);
        while ($i < $n) {
            $c = $s[$i];
            if (ctype_space($c)) { $i++; continue; }
            if (ctype_digit($c) || ($c === '.' && $i + 1 < $n && ctype_digit($s[$i + 1]))) {
                preg_match('/\G\d*\.?\d+(?:[eE][-+]?\d+)?/', $s, $m, 0, $i);
                $t[] = ['num', $m[0] + 0, $i];
                $i += strlen($m[0]);
                continue;
            }
            if ($c === '"' || $c === "'") {
                $j = $i + 1; $buf = '';
                while ($j < $n && $s[$j] !== $c) {
                    if ($s[$j] === '\\' && $j + 1 < $n) { $j++; }
                    $buf .= $s[$j]; $j++;
                }
                if ($j >= $n) { throw new InvalidArgumentException('Texto sem aspas de fechamento na posição ' . ($i + 1) . '.'); }
                $t[] = ['str', $buf, $i];
                $i = $j + 1;
                continue;
            }
            if (preg_match('/\G[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*/', $s, $m, 0, $i)) {
                $p = mb_strtolower($m[0]);
                $p = strtr($p, ['ã' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ú' => 'u']);
                $t[] = in_array($p, self::PALAVRAS, TRUE) ? ['kw', $p, $i] : ['id', $m[0], $i];
                $i += strlen($m[0]);
                continue;
            }
            foreach (['==', '!=', '>=', '<=', '<>'] as $op) {
                if (substr($s, $i, 2) === $op) {
                    $t[] = ['op', $op === '<>' ? '!=' : $op, $i];
                    $i += 2;
                    continue 2;
                }
            }
            if (strpos('+-*/()[],<>=!', $c) !== FALSE) {
                $op = $c === '=' ? '==' : $c;
                if ($c === '!') { throw new InvalidArgumentException('Use "nao" para negar (posição ' . ($i + 1) . ').'); }
                $t[] = ['op', $op, $i];
                $i++;
                continue;
            }
            throw new InvalidArgumentException('Caractere inesperado "' . $c . '" na posição ' . ($i + 1) . '.');
        }
        $t[] = ['fim', NULL, $n];
        return $t;
    }

    private static function coletar($n, &$nomes) {
        if ($n[0] === 'ref') { $nomes[] = $n[1]; return; }
        if ($n[0] === 'call') { foreach ($n[2] as $a) { self::coletar($a, $nomes); } return; }
        if ($n[0] === 'lista') { foreach ($n[1] as $a) { self::coletar($a, $nomes); } return; }
        for ($i = 1; $i < count($n); $i++) {
            if (is_array($n[$i]) && isset($n[$i][0]) && is_string($n[$i][0])) { self::coletar($n[$i], $nomes); }
        }
    }

    // ------------------------------------------------------------------
    // Árvore -> texto (o importador traduz textos de opções em códigos)
    // ------------------------------------------------------------------

    public static function texto($n) {
        switch ($n[0]) {
            case 'num': return (string) $n[1];
            case 'str': return '"' . str_replace('"', '\\"', $n[1]) . '"';
            case 'bool': return $n[1] ? 'verdadeiro' : 'falso';
            case 'nulo': return 'nulo';
            case 'ref': return $n[1];
            case 'lista': return '[' . implode(', ', array_map([__CLASS__, 'texto'], $n[1])) . ']';
            case 'neg': return '-' . self::texto($n[1]);
            case 'nao': return 'nao (' . self::texto($n[1]) . ')';
            case 'e': case 'ou': return '(' . self::texto($n[1]) . ' ' . $n[0] . ' ' . self::texto($n[2]) . ')';
            case 'bin': return '(' . self::texto($n[2]) . ' ' . $n[1] . ' ' . self::texto($n[3]) . ')';
            case 'call': return $n[1] . '(' . implode(', ', array_map([__CLASS__, 'texto'], $n[2])) . ')';
        }
        return '';
    }
}

/** Parser descendente recursivo da linguagem (precedência: ou < e < nao < comparação < +- < * /). */
class Expressao_parser {

    private $t;
    private $i = 0;

    public function __construct($tokens) {
        $this->t = $tokens;
    }

    public function tudo() {
        if ($this->t[0][0] === 'fim') {
            throw new InvalidArgumentException('Expressão vazia.');
        }
        $n = $this->ou();
        if ($this->t[$this->i][0] !== 'fim') {
            throw new InvalidArgumentException('Trecho inesperado na posição ' . ($this->t[$this->i][2] + 1) . ': "' . $this->t[$this->i][1] . '".');
        }
        return $n;
    }

    private function ve($tipo, $valor = NULL) {
        $tk = $this->t[$this->i];
        return $tk[0] === $tipo && ($valor === NULL || $tk[1] === $valor);
    }

    private function exige($tipo, $valor) {
        if (!$this->ve($tipo, $valor)) {
            $tk = $this->t[$this->i];
            throw new InvalidArgumentException('Esperado "' . $valor . '" na posição ' . ($tk[2] + 1) . '.');
        }
        $this->i++;
    }

    private function ou() {
        $n = $this->e();
        while ($this->ve('kw', 'ou')) { $this->i++; $n = ['ou', $n, $this->e()]; }
        return $n;
    }

    private function e() {
        $n = $this->nao();
        while ($this->ve('kw', 'e')) { $this->i++; $n = ['e', $n, $this->nao()]; }
        return $n;
    }

    private function nao() {
        if ($this->ve('kw', 'nao')) { $this->i++; return ['nao', $this->nao()]; }
        return $this->comparacao();
    }

    private function comparacao() {
        $n = $this->soma();
        foreach (['==', '!=', '>=', '<=', '>', '<'] as $op) {
            if ($this->ve('op', $op)) { $this->i++; return ['bin', $op, $n, $this->soma()]; }
        }
        foreach (['em', 'fora_de', 'contem', 'comeca'] as $kw) {
            if ($this->ve('kw', $kw)) { $this->i++; return ['bin', $kw, $n, $this->soma()]; }
        }
        // "fora de" escrito com espaço
        return $n;
    }

    private function soma() {
        $n = $this->produto();
        while ($this->ve('op', '+') || $this->ve('op', '-')) {
            $op = $this->t[$this->i++][1];
            $n = ['bin', $op, $n, $this->produto()];
        }
        return $n;
    }

    private function produto() {
        $n = $this->unario();
        while ($this->ve('op', '*') || $this->ve('op', '/')) {
            $op = $this->t[$this->i++][1];
            $n = ['bin', $op, $n, $this->unario()];
        }
        return $n;
    }

    private function unario() {
        if ($this->ve('op', '-')) { $this->i++; return ['neg', $this->unario()]; }
        return $this->primario();
    }

    private function primario() {
        $tk = $this->t[$this->i];
        switch ($tk[0]) {
            case 'num': $this->i++; return ['num', $tk[1]];
            case 'str': $this->i++; return ['str', $tk[1]];
            case 'kw':
                if ($tk[1] === 'verdadeiro' || $tk[1] === 'falso') { $this->i++; return ['bool', $tk[1] === 'verdadeiro']; }
                if ($tk[1] === 'nulo') { $this->i++; return ['nulo']; }
                break;
            case 'id':
                $this->i++;
                if ($this->ve('op', '(')) {
                    $nome = mb_strtolower($tk[1]);
                    if (!in_array($nome, Expressao::FUNCOES, TRUE)) {
                        throw new InvalidArgumentException('Função desconhecida: ' . $tk[1] . '().');
                    }
                    $this->i++;
                    $args = [];
                    if (!$this->ve('op', ')')) {
                        do { $args[] = $this->ou(); } while ($this->ve('op', ',') && ++$this->i);
                    }
                    $this->exige('op', ')');
                    return ['call', $nome, $args];
                }
                return ['ref', $tk[1]];
            case 'op':
                if ($tk[1] === '(') {
                    $this->i++;
                    $n = $this->ou();
                    $this->exige('op', ')');
                    return $n;
                }
                if ($tk[1] === '[') {
                    $this->i++;
                    $itens = [];
                    if (!$this->ve('op', ']')) {
                        do { $itens[] = $this->ou(); } while ($this->ve('op', ',') && ++$this->i);
                    }
                    $this->exige('op', ']');
                    return ['lista', $itens];
                }
                break;
            case 'fim':
                throw new InvalidArgumentException('A expressão terminou antes do esperado.');
        }
        throw new InvalidArgumentException('Trecho inesperado na posição ' . ($tk[2] + 1) . ': "' . $tk[1] . '".');
    }
}
