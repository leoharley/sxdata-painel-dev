<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once __DIR__ . '/Expressao.php';
require_once __DIR__ . '/Estrutura_motor.php';

/**
 * Compila o formato-texto de um formulário estruturado (o que a equipe edita
 * no painel) na definição JSON que o app baixa e o motor avalia.
 * Formato documentado em docs/FORMULARIOS_ESTRUTURADOS.md. Resumo:
 *
 *   # titulo: Formulário Geral
 *   @domicilio Identificação                 etapa do domicílio
 *   == BLOCO A - AUTORIZAÇÃO                 título de bloco
 *   -- A.1 Termo de autorização              título de seção
 *   1. O termo foi lido? [unica]             questão (número, texto, [tipo e marcas])
 *     ajuda: Técnico: ...
 *     se: q27 == "Não"                       exibir se (textos das opções viram códigos)
 *     - Sim
 *     - Outros. Qual?                        opção com complemento detectado
 *   @moradores 17. Nome de cada morador      etapa da lista de moradores
 *   @morador Questões por morador            etapa repetida por morador
 *     var idade = idade(q21, q5)             variável calculada oculta
 *
 * Classe pura: tests/sxdata/Estrutura_compilador_test.php.
 */
class Estrutura_compilador {

    /** Opções de múltipla escolha que não combinam com outras. */
    const EXCLUSIVAS = ['nenhum', 'nenhuma', 'ns_nr', 'nao', 'nao_ha', 'ninguem', 'apenas_residencial', 'nada_falta',
        'nao_possui_telefone_nem_internet', 'nenhum_efeito_positivo', 'nenhum_efeito_negativo',
        'nao_compra_mercadorias_ou_insumos', 'prefere_nao_responder', 'apenas_para_o_negocio',
        'aleatorio_sem_padrao_de_sazonalidade'];

    const ATRIBUTOS = ['ajuda', 'se', 'pula', 'alerta', 'formula', 'opcoes_de', 'auto', 'complemento', 'campos',
        'validacao', 'formato', 'codigo', 'destino', 'itens', 'respondente', 'declarado', 'rotulo', 'regra', 'obrigatoria',
        'tema', 'categoria', 'entrevistado', 'por', 'max', 'min', 'preencher'];

    /** Atributos que valem para a etapa inteira (antes da primeira questão). */
    const ATRIBUTOS_ETAPA = ['se', 'tema', 'categoria', 'entrevistado', 'por', 'max', 'min', 'rotulo'];

    private $erros = [];
    private $avisos = [];

    /**
     * @return array [definição ou NULL se houver erro, erros, avisos]; cada
     *               erro/aviso é ['linha' => int|null, 'mensagem' => string]
     */
    /**
     * @param callable|null $conjuntos fn(codigo) => ['id', 'codigo', 'versao', 'nome', 'fonte'] ou NULL
     *                                 (biblioteca de conjuntos de perguntas, para @usar)
     */
    public static function compilar($texto, $conjuntos = NULL) {
        $c = new self();
        $def = $c->ler((string) $texto);
        if (!$c->erros) {
            $c->resolver_usar($def, $conjuntos);
        }
        if (!$c->erros) {
            $c->montar_etapas($def);
        }
        if (!$c->erros) {
            $c->traduzir_expressoes($def);
        }
        if (!$c->erros) {
            $c->validar($def);
        }
        return [$c->erros ? NULL : $def, $c->erros, $c->avisos];
    }

    public static function slug($s) {
        $s = mb_strtolower(trim((string) $s));
        $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e',
            'í' => 'i', 'ì' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ú' => 'u', 'ü' => 'u', 'ù' => 'u',
            'ç' => 'c', 'ñ' => 'n']);
        $s = trim(preg_replace('/[^a-z0-9]+/', '_', $s), '_');
        return rtrim(substr($s, 0, 60), '_');
    }

    private function erro($linha, $msg) { $this->erros[] = ['linha' => $linha, 'mensagem' => $msg]; }
    private function aviso($linha, $msg) { $this->avisos[] = ['linha' => $linha, 'mensagem' => $msg]; }

    // ------------------------------------------------------------------
    // Leitura linha a linha
    // ------------------------------------------------------------------

    private function ler($texto) {
        $def = ['schema' => 1, 'titulo' => '', 'descricao' => '', 'etapas' => []];
        $etapa = NULL;          // índice da etapa atual
        $p = NULL;              // referência à pergunta atual
        $bloco = ''; $secao = '';
        $linhas = preg_split('/\r\n|\r|\n/', $texto);

        foreach ($linhas as $i => $bruta) {
            $ln = $i + 1;
            $linha = rtrim($bruta);
            $t = trim($linha);
            if ($t === '' || strpos($t, '//') === 0) { continue; }

            if (preg_match('/^#\s*(titulo|descricao)\s*:\s*(.*)$/u', $t, $m)) {
                $def[$m[1]] = $m[2];
                continue;
            }
            if (preg_match('/^#\s*temas\s*:\s*(.+)$/u', $t, $m)) {
                $def['temas'] = array_values(array_filter(array_map('trim', explode(',', $m[1])), 'strlen'));
                continue;
            }
            if (preg_match('/^#\s*entrevistado\s*:\s*(obrigatorio|opcional)\s*$/u', $t, $m)) {
                $def['entrevistado'] = ['modo' => $m[1]];
                continue;
            }
            if ($t[0] === '#') { continue; }

            if (preg_match('/^@(domicilio|morador)\b\s*(.*)$/u', $t, $m)) {
                $def['etapas'][] = ['tipo' => 'perguntas', 'nivel' => $m[1], 'titulo' => $m[2], 'perguntas' => [], '_linha' => $ln];
                $etapa = count($def['etapas']) - 1;
                unset($p); $p = NULL;
                continue;
            }
            if (preg_match('/^@repetir\s+([a-z_][a-z0-9_]*)\s*(.*)$/u', $t, $m)) {
                $def['etapas'][] = ['tipo' => 'perguntas', 'nivel' => 'grupo', 'grupo' => $m[1], 'titulo' => $m[2],
                    'repetir' => [], 'perguntas' => [], '_linha' => $ln];
                $etapa = count($def['etapas']) - 1;
                unset($p); $p = NULL;
                continue;
            }
            if (preg_match('/^@usar\s+([a-z_][a-z0-9_]*)\s*(.*)$/u', $t, $m)) {
                $def['etapas'][] = ['tipo' => 'usar', 'conjunto' => $m[1], 'titulo' => $m[2], 'bloco' => $bloco, 'secao' => $secao, '_linha' => $ln];
                $etapa = count($def['etapas']) - 1;
                unset($p); $p = NULL;
                continue;
            }
            if (preg_match('/^@moradores\b\s*(?:(\d+[a-z]?)\.\s*)?(.*)$/u', $t, $m)) {
                $def['etapas'][] = ['tipo' => 'moradores', 'numero' => $m[1], 'texto' => $m[2], 'rotulo' => 'Morador',
                    'regras' => [], 'bloco' => $bloco, 'secao' => $secao, '_linha' => $ln];
                $etapa = count($def['etapas']) - 1;
                unset($p); $p = NULL;
                continue;
            }
            if (preg_match('/^==\s*(.+)$/u', $t, $m)) { $bloco = trim($m[1]); $secao = ''; continue; }
            if (preg_match('/^--\s*(.+)$/u', $t, $m)) { $secao = trim($m[1]); continue; }

            if ($etapa === NULL) {
                $this->erro($ln, 'Comece com uma etapa (@domicilio, @moradores ou @morador) antes das questões.');
                return $def;
            }
            $e = &$def['etapas'][$etapa];

            // var nome = expressão  (calculado oculto)
            if (preg_match('/^var\s+([a-z_][a-z0-9_]*)\s*=\s*(.+)$/u', $t, $m)) {
                if ($e['tipo'] !== 'perguntas') { $this->erro($ln, 'Variáveis só dentro de @domicilio ou @morador.'); unset($e); continue; }
                $e['perguntas'][] = ['codigo' => $m[1], 'numero' => '', 'texto' => $m[1], 'tipo' => 'calculado',
                    'formula' => trim($m[2]), 'oculta' => TRUE, 'obrigatoria' => FALSE, 'formato' => 'auto', '_linha' => $ln];
                unset($p); $p = NULL;
                unset($e);
                continue;
            }

            // Questão: "53. Texto [tipo marcas]"
            if (preg_match('/^(\d+[a-z]?)\.\s+(.+)$/u', $t, $m) && $e['tipo'] === 'perguntas') {
                $texto_q = $m[2]; $spec = '';
                if (preg_match('/^(.*?)\s*\[([a-z0-9_ =\.\-]+)\]\s*$/u', $texto_q, $mm)) {
                    $texto_q = $mm[1]; $spec = $mm[2];
                }
                $q = ['codigo' => 'q' . strtolower($m[1]), 'numero' => $m[1], 'texto' => $texto_q, 'tipo' => 'texto',
                    'obrigatoria' => TRUE, 'bloco' => $bloco, 'secao' => $secao, '_linha' => $ln];
                $this->aplicar_spec($q, $spec, $ln);
                $e['perguntas'][] = $q;
                unset($p);
                $p = &$e['perguntas'][count($e['perguntas']) - 1];
                unset($e);
                continue;
            }

            // Opção "- Texto {marcas}"
            if (preg_match('/^-\s+(.+)$/u', $t, $m)) {
                if ($p === NULL) { $this->erro($ln, 'Opção fora de uma questão.'); unset($e); continue; }
                $p['opcoes'][] = $this->opcao($m[1], $ln);
                unset($e);
                continue;
            }

            // Atributo "chave: valor"
            if (preg_match('/^([a-z_]+)\s*:\s*(.*)$/u', $t, $m) && in_array($m[1], self::ATRIBUTOS, TRUE)) {
                if ($e['tipo'] === 'moradores' && $p === NULL) {
                    $this->atributo_moradores($e, $m[1], $m[2], $ln);
                } elseif ($p === NULL && in_array($e['tipo'], ['perguntas', 'usar'], TRUE)) {
                    $this->atributo_etapa($e, $m[1], $m[2], $ln);
                } elseif ($p !== NULL) {
                    $this->atributo($p, $m[1], $m[2], $ln);
                } else {
                    $this->erro($ln, 'Atributo "' . $m[1] . '" fora de uma questão.');
                }
                unset($e);
                continue;
            }

            // Continuação do texto da ajuda ou da questão
            if ($p !== NULL && preg_match('/^\s+/', $linha)) {
                if (!empty($p['ajuda'])) { $p['ajuda'] .= ' ' . $t; } else { $p['texto'] .= ' ' . $t; }
                unset($e);
                continue;
            }
            $this->erro($ln, 'Linha não reconhecida: "' . mb_substr($t, 0, 60) . '".');
            unset($e);
        }
        return $def;
    }

    private function aplicar_spec(array &$q, $spec, $ln) {
        foreach (preg_split('/\s+/', trim($spec)) as $tk) {
            if ($tk === '') { continue; }
            if (in_array($tk, Estrutura_motor::TIPOS, TRUE)) { $q['tipo'] = $tk; continue; }
            if ($tk === 'numero') { $q['tipo'] = 'decimal'; continue; }
            if ($tk === 'nsnr') { $q['nsnr'] = TRUE; continue; }
            if ($tk === 'opcional') { $q['obrigatoria'] = FALSE; continue; }
            if ($tk === 'oculta') { $q['oculta'] = TRUE; continue; }
            if (preg_match('/^auto=(data|hora|hora_fim)$/', $tk, $m)) { $q['auto'] = $m[1]; continue; }
            if (preg_match('/^formato=(moeda|decimal|inteiro|duracao)$/', $tk, $m)) { $q['formato'] = $m[1]; continue; }
            if (preg_match('/^itens=(\d+)$/', $tk, $m)) { $q['itens'] = (int) $m[1]; continue; }
            if (preg_match('/^destino=(foto|foto2|coordenadas)$/', $tk, $m)) { $q['destino'] = $m[1]; continue; }
            $this->erro($ln, "Marca desconhecida \"$tk\" entre colchetes.");
        }
        if (in_array($q['tipo'], ['calculado', 'mensagem'], TRUE)) { $q['obrigatoria'] = FALSE; }
        if ($q['tipo'] === 'calculado' && empty($q['formato'])) { $q['formato'] = 'decimal'; }
        if (in_array($q['tipo'], ['unica', 'multipla'], TRUE)) { $q['opcoes'] = []; }
    }

    private function opcao($bruto, $ln) {
        $flags = [];
        if (preg_match('/^(.*?)\s*\{([^}]*)\}\s*$/u', $bruto, $m)) {
            $bruto = $m[1];
            foreach (preg_split('/\s*,\s*|\s+/', trim($m[2])) as $f) { if ($f !== '') { $flags[] = $f; } }
        }
        $texto = trim(preg_replace('/\s*_{3,}\s*/', ' ', $bruto));
        $base = $texto; $comp = NULL;
        if (preg_match('/^(.*?)\.\s*(Quant[oa]s)\s*:?\s*$/u', $texto, $m)) {
            $base = $m[1]; $comp = ['tipo' => 'inteiro', 'rotulo' => $m[2]];
        } elseif (preg_match('/^(.*?)\.\s*((Qual|Quais|Descreva|Quem|Onde)\b.*)$/u', $texto, $m)) {
            $base = $m[1]; $comp = ['tipo' => 'texto', 'rotulo' => rtrim(trim($m[2]), ':')];
        } elseif (preg_match('/^(.*?)\s*\((especificar|explicar)\)\s*:?$/iu', $texto, $m)) {
            $base = $m[1]; $comp = ['tipo' => 'texto', 'rotulo' => ucfirst(mb_strtolower($m[2]))];
        }
        $o = ['valor' => self::slug($base), 'texto' => $texto];
        foreach ($flags as $f) {
            if ($f === 'exclusiva') { $o['exclusiva'] = TRUE; }
            elseif ($f === 'sempre') { $o['sempre'] = TRUE; }
            elseif ($f === 'sem_complemento') { $comp = NULL; }
            elseif ($f === 'complemento_opcional' && $comp) { $comp['obrigatorio'] = FALSE; }
            elseif (preg_match('/^valor=([a-z0-9_]+)$/', $f, $m)) { $o['valor'] = $m[1]; }
            elseif (preg_match('/^complemento=(texto|inteiro)$/', $f, $m)) { $comp = ['tipo' => $m[1], 'rotulo' => $comp['rotulo'] ?? 'Especificar']; }
            else { $this->erro($ln, "Marca de opção desconhecida \"$f\"."); }
        }
        if ($o['valor'] === '') { $this->erro($ln, 'Opção sem texto.'); }
        if ($comp) { $o['complemento'] = $comp; }
        if ($o['valor'] === 'ns_nr') { $o['sempre'] = TRUE; }
        return $o;
    }

    private function atributo(array &$p, $chave, $valor, $ln) {
        $valor = trim($valor);
        switch ($chave) {
            case 'ajuda': $p['ajuda'] = $valor; break;
            case 'se': $p['exibir_se'] = $valor; $p['_l_se'] = $ln; break;
            case 'formula': $p['formula'] = $valor; $p['_l_formula'] = $ln; break;
            case 'opcoes_de': $p['opcoes_de'] = self::ref($valor); break;
            case 'auto':
                if (!in_array($valor, ['data', 'hora', 'hora_fim'], TRUE)) { $this->erro($ln, 'auto deve ser data, hora ou hora_fim.'); }
                $p['auto'] = $valor; break;
            case 'formato': $p['formato'] = $valor; break;
            case 'validacao': $p['validacao'] = $valor; break;
            case 'codigo':
                if (!preg_match('/^[a-z_][a-z0-9_]*$/', $valor)) { $this->erro($ln, 'Código inválido (use letras minúsculas, números e _).'); }
                $p['codigo'] = $valor; break;
            case 'destino': $p['destino'] = $valor; break;
            case 'itens': $p['itens'] = (int) $valor; break;
            case 'preencher':
                if (!preg_match('/^ent_[a-z0-9_]+$/', $valor)) { $this->erro($ln, 'preencher: use um dado do entrevistado (ent_nome, ent_codigo, ent_localidade...).'); }
                $p['preencher'] = $valor; break;
            case 'obrigatoria': $p['obrigatoria'] = !in_array(mb_strtolower($valor), ['nao', 'não', 'false', '0'], TRUE); break;
            case 'pula':
                if (!preg_match('/^(.*?)\s*->\s*([qQ]?\d+[a-z]?)\s*$/u', $valor, $m)) {
                    $this->erro($ln, 'Use: pula: condição -> número da questão de destino.');
                    break;
                }
                $p['pular'] = ['se' => $m[1], 'para' => self::ref($m[2]), '_linha' => $ln];
                break;
            case 'alerta':
                $partes = array_map('trim', explode('|', $valor, 2));
                if (count($partes) < 2) { $this->erro($ln, 'Use: alerta: condição | mensagem.'); break; }
                $p['alertas'][] = ['se' => $partes[0], 'mensagem' => $partes[1], '_linha' => $ln];
                break;
            case 'complemento':
                $obrig = !preg_match('/\(opcional\)\s*$/u', $valor);
                $p['complemento'] = ['tipo' => 'texto', 'rotulo' => trim(preg_replace('/\(opcional\)\s*$/u', '', $valor)), 'obrigatorio' => $obrig];
                break;
            case 'campos':
                $p['campos'] = [];
                foreach (explode(',', $valor) as $c) {
                    $x = array_map('trim', explode(':', $c));
                    if ($x[0] === '') { continue; }
                    $p['campos'][] = ['codigo' => self::slug($x[0]), 'rotulo' => $x[2] ?? $x[0], 'tipo' => $x[1] ?? 'texto'];
                }
                break;
            default:
                $this->erro($ln, "Atributo \"$chave\" não se aplica a uma questão.");
        }
    }

    private function atributo_etapa(array &$e, $chave, $valor, $ln) {
        $valor = trim($valor);
        if (!in_array($chave, self::ATRIBUTOS_ETAPA, TRUE)) {
            $this->erro($ln, "Atributo \"$chave\" fora de uma questão (na etapa valem: " . implode(', ', self::ATRIBUTOS_ETAPA) . ').');
            return;
        }
        $lista = function ($v) { return array_values(array_filter(array_map('trim', explode(',', $v)), 'strlen')); };
        switch ($chave) {
            case 'se': $e['_se'] = $valor; $e['_l_se'] = $ln; break;
            case 'tema': $e['_temas'] = $lista($valor); break;
            case 'categoria': $e['_categorias'] = $lista($valor); break;
            case 'entrevistado': $e['_entrevistados'] = $lista($valor); break;
            case 'por': $e['_repetir']['por'] = self::ref($valor); break;
            case 'max': $e['_repetir']['max'] = (int) $valor; break;
            case 'min': $e['_repetir']['min'] = (int) $valor; break;
            case 'rotulo': $e['_repetir']['rotulo'] = $valor; break;
        }
    }

    /** Troca cada @usar pela etapa do conjunto (última versão publicada na biblioteca). */
    private function resolver_usar(array &$def, $conjuntos) {
        foreach ($def['etapas'] as $k => $e) {
            if ($e['tipo'] !== 'usar') { continue; }
            $c = $conjuntos ? call_user_func($conjuntos, $e['conjunto']) : NULL;
            if (!$c) { $this->erro($e['_linha'], 'Conjunto "' . $e['conjunto'] . '" não existe na biblioteca ou não tem versão publicada.'); continue; }
            list($cd, $erros) = self::compilar($c['fonte']);
            if ($erros) {
                foreach ($erros as $er) { $this->erro($e['_linha'], 'Conjunto "' . $c['codigo'] . '"' . ($er['linha'] ? ', linha ' . $er['linha'] : '') . ': ' . $er['mensagem']); }
                continue;
            }
            if (count($cd['etapas']) !== 1 || $cd['etapas'][0]['tipo'] !== 'perguntas') {
                $this->erro($e['_linha'], 'O conjunto "' . $c['codigo'] . '" deve ter exatamente uma etapa (@domicilio, @morador ou @repetir).');
                continue;
            }
            $nova = $cd['etapas'][0];
            if (($e['titulo'] ?? '') !== '') { $nova['titulo'] = $e['titulo']; }
            $nova['conjunto'] = ['id' => (int) $c['id'], 'codigo' => $c['codigo'], 'versao' => (int) $c['versao'], 'nome' => $c['nome']];
            foreach (['_se', '_l_se', '_temas', '_categorias', '_entrevistados'] as $a) {
                if (isset($e[$a])) { $nova[$a] = $e[$a]; }
            }
            if (isset($e['_repetir'])) { $nova['_repetir'] = array_merge($nova['repetir'] ?? [], $e['_repetir']); }
            if (!empty($nova['se'])) {
                $nova['_se'] = isset($nova['_se']) ? '(' . $nova['se'] . ') e (' . $nova['_se'] . ')' : $nova['se'];
                unset($nova['se']);
            }
            $nova['_linha'] = $e['_linha'];
            foreach ($nova['perguntas'] as $j => $q) {
                $nova['perguntas'][$j]['_linha'] = $e['_linha'];
                if (($q['bloco'] ?? '') === '' && !empty($e['bloco'])) { $nova['perguntas'][$j]['bloco'] = $e['bloco']; }
            }
            $def['etapas'][$k] = $nova;
        }
    }

    /** Condição da etapa (se + tema + categoria + entrevistado), repetição e a questão dos temas. */
    private function montar_etapas(array &$def) {
        if (!empty($def['temas'])) {
            $q = ['codigo' => 'temas', 'numero' => '', 'texto' => 'Temas desta entrevista', 'tipo' => 'multipla', 'obrigatoria' => TRUE,
                'ajuda' => 'Marque os temas a aplicar: as questões de cada tema entram na sequência.', 'opcoes' => [], '_linha' => NULL];
            foreach ($def['temas'] as $t) { $q['opcoes'][] = ['valor' => self::slug($t), 'texto' => $t]; }
            $alvo = NULL;
            foreach ($def['etapas'] as $k => $e) {
                if ($e['tipo'] === 'perguntas' && $e['nivel'] === 'domicilio' && empty($e['_temas']) && empty($e['_se'])) { $alvo = $k; break; }
            }
            if ($alvo === NULL) {
                array_unshift($def['etapas'], ['tipo' => 'perguntas', 'nivel' => 'domicilio', 'titulo' => 'Temas', 'perguntas' => [$q], '_linha' => NULL]);
            } else {
                array_unshift($def['etapas'][$alvo]['perguntas'], $q);
            }
        }
        foreach ($def['etapas'] as &$e) {
            if ($e['tipo'] !== 'perguntas') { continue; }
            $partes = [];
            if (!empty($e['_se'])) { $partes[] = '(' . $e['_se'] . ')'; }
            if (!empty($e['_temas'])) {
                if (empty($def['temas'])) { $this->erro($e['_linha'], 'Etapa com tema, mas o formulário não declara "# temas: ...".'); }
                $partes[] = '(' . implode(' ou ', array_map(function ($t) { return 'temas contem "' . str_replace('"', '', $t) . '"'; }, $e['_temas'])) . ')';
            }
            if (!empty($e['_categorias'])) {
                $partes[] = '(ent_categoria em [' . implode(', ', array_map(function ($c) { return '"' . self::slug($c) . '"'; }, $e['_categorias'])) . '])';
            }
            if (!empty($e['_entrevistados'])) {
                $partes[] = '(ent_codigo em [' . implode(', ', array_map(function ($c) { return '"' . str_replace('"', '', $c) . '"'; }, $e['_entrevistados'])) . '])';
            }
            if ((!empty($e['_categorias']) || !empty($e['_entrevistados'])) && empty($def['entrevistado'])) {
                $this->erro($e['_linha'], 'Etapa por categoria ou entrevistado: declare "# entrevistado: opcional" (ou obrigatorio) no início.');
            }
            if ($partes) { $e['se'] = implode(' e ', $partes); $e['_l_se'] = $e['_l_se'] ?? $e['_linha']; }
            if ($e['nivel'] === 'grupo') {
                $e['repetir'] = array_merge($e['repetir'] ?? [], $e['_repetir'] ?? []);
            } elseif (!empty($e['_repetir'])) {
                $this->erro($e['_linha'], 'por/max/min/rotulo só valem em @repetir.');
            }
            unset($e['_se'], $e['_temas'], $e['_categorias'], $e['_entrevistados'], $e['_repetir']);
        }
        unset($e);
    }

    private function atributo_moradores(array &$e, $chave, $valor, $ln) {
        $valor = trim($valor);
        switch ($chave) {
            case 'ajuda': $e['ajuda'] = $valor; break;
            case 'respondente': $e['respondente'] = self::ref($valor); break;
            case 'declarado': $e['declarado'] = self::ref($valor); break;
            case 'rotulo': $e['rotulo'] = $valor; break;
            case 'regra':
                $partes = array_map('trim', explode('|', $valor));
                if (count($partes) < 2) { $this->erro($ln, 'Use: regra: condição de erro | mensagem [| bloqueia].'); break; }
                $e['regras'][] = ['se' => $partes[0], 'mensagem' => $partes[1], 'bloqueia' => ($partes[2] ?? '') === 'bloqueia', '_linha' => $ln];
                break;
            default:
                $this->erro($ln, "Atributo \"$chave\" não se aplica à lista de moradores.");
        }
    }

    private static function ref($s) {
        $s = trim($s);
        return preg_match('/^[qQ]?(\d+[a-z]?)$/', $s, $m) ? 'q' . strtolower($m[1]) : $s;
    }

    // ------------------------------------------------------------------
    // Tradução dos textos das opções para códigos nas expressões
    // ------------------------------------------------------------------

    private $perguntas = [];

    private function indexar(array $def) {
        $this->perguntas = [];
        foreach ($def['etapas'] as $e) {
            foreach ($e['perguntas'] ?? [] as $p) {
                $nv = $e['nivel'] ?? 'domicilio';
                $this->perguntas[$p['codigo']] = $p + ['_nivel' => $nv === 'grupo' ? 'grupo:' . $e['grupo'] : $nv];
            }
        }
    }

    private function traduzir_expressoes(array &$def) {
        $this->indexar($def);
        foreach ($def['etapas'] as &$e) {
            if (!empty($e['se'])) { $e['se'] = $this->traduzir($e['se'], $e['_l_se'] ?? $e['_linha']); }
            foreach ($e['regras'] ?? [] as $k => $r) {
                $e['regras'][$k]['se'] = $this->traduzir($r['se'], $r['_linha']);
            }
            foreach ($e['perguntas'] ?? [] as $k => $p) {
                $q = &$e['perguntas'][$k];
                if (!empty($q['exibir_se'])) { $q['exibir_se'] = $this->traduzir($q['exibir_se'], $q['_l_se'] ?? $q['_linha']); }
                if (!empty($q['formula'])) { $q['formula'] = $this->traduzir($q['formula'], $q['_l_formula'] ?? $q['_linha']); }
                if (!empty($q['pular'])) { $q['pular']['se'] = $this->traduzir($q['pular']['se'], $q['pular']['_linha']); }
                foreach ($q['alertas'] ?? [] as $j => $a) {
                    $q['alertas'][$j]['se'] = $this->traduzir($a['se'], $a['_linha']);
                }
                unset($q);
            }
        }
        unset($e);
    }

    private function traduzir($texto, $ln) {
        try {
            $arvore = Expressao::compilar($texto);
        } catch (InvalidArgumentException $ex) {
            $this->erro($ln, 'Expressão inválida: ' . $ex->getMessage());
            return $texto;
        }
        return Expressao::texto($this->mapear($arvore, $ln));
    }

    private function mapear($n, $ln) {
        if ($n[0] === 'ref') {
            return ['ref', self::ref($n[1])]; // Q27 -> q27
        }
        if ($n[0] === 'bin' && $n[2][0] === 'ref') {
            $n[2] = ['ref', self::ref($n[2][1])];
        }
        if ($n[0] === 'bin' && in_array($n[1], ['==', '!=', 'em', 'fora_de', 'contem', 'comeca'], TRUE) && $n[2][0] === 'ref') {
            $cod = $n[2][1];
            $p = $this->perguntas[$cod] ?? NULL;
            if ($p && in_array($p['tipo'], ['unica', 'multipla'], TRUE)) {
                $n[3] = $this->mapear_literal($n[3], $p, $n[1] === 'comeca', $ln);
            }
            return $n;
        }
        foreach ($n as $k => $filho) {
            if ($k === 0) { continue; }
            if (is_array($filho) && isset($filho[0]) && is_string($filho[0])) {
                $n[$k] = $this->mapear($filho, $ln);
            } elseif (is_array($filho)) {
                foreach ($filho as $j => $neto) {
                    if (is_array($neto) && isset($neto[0]) && is_string($neto[0])) { $n[$k][$j] = $this->mapear($neto, $ln); }
                }
            }
        }
        return $n;
    }

    private function mapear_literal($n, array $p, $prefixo, $ln) {
        if ($n[0] === 'lista') {
            foreach ($n[1] as $k => $item) { $n[1][$k] = $this->mapear_literal($item, $p, $prefixo, $ln); }
            return $n;
        }
        if ($n[0] !== 'str') { return $n; }
        $s = self::slug($n[1]);
        foreach ($p['opcoes'] ?? [] as $o) {
            if ($o['valor'] === $n[1] || $o['valor'] === $s || self::slug($o['texto']) === $s) {
                return ['str', $o['valor']];
            }
        }
        if ($prefixo) {
            foreach ($p['opcoes'] ?? [] as $o) {
                if (strpos($o['valor'], $s) === 0) { return ['str', $s]; }
            }
        }
        $this->erro($ln, 'A questão ' . $p['numero'] . ' não tem a opção "' . $n[1] . '".');
        return $n;
    }

    // ------------------------------------------------------------------
    // Validação da definição
    // ------------------------------------------------------------------

    private function validar(array &$def) {
        $this->indexar($def);
        if (trim($def['titulo']) === '') { $this->aviso(NULL, 'Sem título (# titulo: ...).'); }
        $vistos = [];
        $grupos = [];
        $moradores = 0;
        $tem_morador = FALSE;
        foreach ($def['etapas'] as $e) {
            if ($e['tipo'] === 'moradores') { $moradores++; continue; }
            if ($e['tipo'] !== 'perguntas') { continue; }
            if ($e['nivel'] === 'morador') { $tem_morador = TRUE; }
            $nivel = $e['nivel'] === 'grupo' ? 'grupo:' . $e['grupo'] : $e['nivel'];
            if (empty($e['perguntas'])) { $this->aviso($e['_linha'], 'Etapa sem questões.'); }
            if (!empty($e['se'])) { $this->validar_expr($e['se'], $nivel === 'morador' || $e['nivel'] === 'grupo' ? 'domicilio' : $nivel, $e['_l_se'] ?? $e['_linha']); }
            if ($e['nivel'] === 'grupo') {
                if (isset($grupos[$e['grupo']])) { $this->erro($e['_linha'], 'Grupo repetido: ' . $e['grupo'] . '.'); }
                $grupos[$e['grupo']] = TRUE;
                $por = $e['repetir']['por'] ?? NULL;
                if ($por !== NULL && (!isset($this->perguntas[$por]) || $this->perguntas[$por]['_nivel'] !== 'domicilio' || $this->perguntas[$por]['tipo'] !== 'inteiro')) {
                    $this->erro($e['_linha'], "por: a questão $por precisa ser [inteiro] do domicílio.");
                }
            }
            foreach ($e['perguntas'] as $p) {
                $ln = $p['_linha'];
                if (isset($vistos[$p['codigo']])) { $this->erro($ln, 'Código repetido: ' . $p['codigo'] . ($vistos[$p['codigo']] ? ' (também na linha ' . $vistos[$p['codigo']] . ')' : '') . '. Se veio de um conjunto, troque o código com "codigo:".'); }
                $vistos[$p['codigo']] = $ln;
                $this->validar_pergunta($p, $nivel, $ln);
            }
        }
        if ($moradores > 1) { $this->erro(NULL, 'Só pode haver uma lista de moradores (@moradores).'); }
        if ($tem_morador && $moradores === 0) { $this->erro(NULL, 'Há questões por morador (@morador) mas não há lista de moradores (@moradores).'); }
        foreach ($def['etapas'] as $e) {
            if ($e['tipo'] !== 'moradores') { continue; }
            foreach (['respondente', 'declarado'] as $k) {
                if (!empty($e[$k]) && (!isset($this->perguntas[$e[$k]]) || $this->perguntas[$e[$k]]['_nivel'] !== 'domicilio')) {
                    $this->erro($e['_linha'], "$k: a questão {$e[$k]} não existe no domicílio.");
                }
            }
            foreach ($e['regras'] ?? [] as $r) { $this->validar_expr($r['se'], 'domicilio', $r['_linha']); }
        }
        // Remove marcas internas de linha
        foreach ($def['etapas'] as &$e) {
            unset($e['_linha'], $e['_l_se']);
            foreach ($e['regras'] ?? [] as $k => $r) { unset($e['regras'][$k]['_linha']); }
            foreach ($e['perguntas'] ?? [] as $k => $p) {
                unset($e['perguntas'][$k]['_linha'], $e['perguntas'][$k]['_l_se'], $e['perguntas'][$k]['_l_formula']);
                if ($p['tipo'] === 'multipla') {
                    foreach ($p['opcoes'] as $j => $o) {
                        if (!isset($o['exclusiva']) && in_array($o['valor'], self::EXCLUSIVAS, TRUE)) {
                            $e['perguntas'][$k]['opcoes'][$j]['exclusiva'] = TRUE;
                        }
                    }
                }
                if (isset($p['pular'])) { unset($e['perguntas'][$k]['pular']['_linha']); }
                foreach ($p['alertas'] ?? [] as $j => $a) { unset($e['perguntas'][$k]['alertas'][$j]['_linha']); }
            }
        }
        unset($e);
    }

    private function validar_pergunta(array $p, $nivel, $ln) {
        $n = $p['numero'] !== '' ? 'Questão ' . $p['numero'] : $p['codigo'];
        if (in_array($p['tipo'], ['unica', 'multipla'], TRUE)) {
            if (count($p['opcoes'] ?? []) < 2 && empty($p['opcoes_de'])) { $this->erro($ln, "$n precisa de ao menos 2 opções."); }
            $valores = [];
            foreach ($p['opcoes'] ?? [] as $o) {
                if (isset($valores[$o['valor']])) { $this->erro($ln, "$n tem duas opções com o mesmo código \"{$o['valor']}\" (use {valor=...})."); }
                $valores[$o['valor']] = TRUE;
            }
        } elseif (!empty($p['opcoes'])) {
            $this->erro($ln, "$n tem opções, mas o tipo é \"{$p['tipo']}\" (use [unica] ou [multipla]).");
        }
        if ($p['tipo'] === 'calculado' && empty($p['formula'])) { $this->erro($ln, "$n é calculada e precisa de formula:."); }
        if ($p['tipo'] === 'composto' && empty($p['campos'])) { $this->erro($ln, "$n é composta e precisa de campos:."); }
        if ($p['tipo'] === 'lista' && empty($p['itens'])) { $this->erro($ln, "$n é uma lista e precisa de [itens=N]."); }
        if (!empty($p['nsnr']) && !in_array($p['tipo'], ['inteiro', 'decimal', 'moeda', 'texto', 'texto_longo', 'data', 'hora', 'cpf', 'cnpj', 'telefone'], TRUE)) {
            $this->erro($ln, "$n: NS/NR como marca só vale para campos de digitar (número, texto, data, hora, CPF, telefone); em escolha, use uma opção NS/NR.");
        }
        foreach (['exibir_se', 'formula'] as $k) {
            if (!empty($p[$k])) { $this->validar_expr($p[$k], $nivel, $ln); }
        }
        foreach ($p['alertas'] ?? [] as $a) { $this->validar_expr($a['se'], $nivel, $ln); }
        if (!empty($p['pular'])) {
            $this->validar_expr($p['pular']['se'], $nivel, $ln);
            $d = $this->perguntas[$p['pular']['para']] ?? NULL;
            if (!$d) { $this->erro($ln, "$n: questão de destino do pulo {$p['pular']['para']} não existe."); }
            elseif ($d['_nivel'] !== $nivel) { $this->erro($ln, "$n: o pulo precisa ir para uma questão do mesmo nível."); }
        }
        if (!empty($p['opcoes_de'])) {
            $o = $this->perguntas[$p['opcoes_de']] ?? NULL;
            if (!$o || empty($o['opcoes'])) { $this->erro($ln, "$n: opcoes_de aponta para {$p['opcoes_de']}, que não é de escolha."); }
        }
    }

    private function validar_expr($texto, $nivel, $ln) {
        try {
            $arvore = Expressao::compilar($texto);
        } catch (InvalidArgumentException $e) {
            $this->erro($ln, 'Expressão inválida: ' . $e->getMessage());
            return;
        }
        $this->checar_refs($arvore, $nivel, FALSE, $ln);
    }

    private function checar_refs($n, $nivel, $agregado, $ln) {
        if ($n[0] === 'ref') {
            $p = $this->perguntas[$n[1]] ?? NULL;
            if (!$p) {
                if (strpos($n[1], 'ent_') === 0) { return; } // dado do entrevistado pré-cadastrado
                $this->erro($ln, "A expressão cita \"{$n[1]}\", que não existe.");
                return;
            }
            if ($p['_nivel'] !== 'domicilio' && $p['_nivel'] !== $nivel && !$agregado) {
                $this->erro($ln, $p['_nivel'] === 'morador'
                    ? "\"{$n[1]}\" é uma questão por morador: fora dela, use soma_moradores(), contar_moradores() ou existe_morador()."
                    : "\"{$n[1]}\" é de um grupo repetido: fora dele, use soma_grupo(), contar_grupo() ou existe_grupo().");
            }
            return;
        }
        if ($n[0] === 'call') {
            $ag = $agregado || in_array($n[1], Expressao::FUNCOES_MORADOR, TRUE);
            foreach ($n[2] as $a) { $this->checar_refs($a, $nivel, $ag, $ln); }
            return;
        }
        if ($n[0] === 'lista') { foreach ($n[1] as $a) { $this->checar_refs($a, $nivel, $agregado, $ln); } return; }
        for ($i = 1; $i < count($n); $i++) {
            if (is_array($n[$i]) && isset($n[$i][0]) && is_string($n[$i][0])) { $this->checar_refs($n[$i], $nivel, $agregado, $ln); }
        }
    }
}
