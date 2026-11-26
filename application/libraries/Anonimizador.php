<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Remove dados pessoais de um texto antes de ele ir para a IA (e para o log
 * da IA): CPF, CNPJ, telefone, e-mail e, quando conhecidos, nomes de
 * entrevistados e moradores. Frente K.
 *
 * Troca por marcadores ([CPF], [TELEFONE], [E-MAIL], [NOME]) para a IA
 * continuar entendendo a frase. É conservador com números: só troca o que
 * tem formato de CPF/CNPJ/telefone, não qualquer sequência de dígitos.
 */
class Anonimizador {

    /** @return string */
    public static function limpar($texto, array $nomes = []) {
        if (!is_string($texto) || $texto === '') {
            return $texto;
        }
        $t = $texto;
        // E-mail primeiro (tem dígitos que pareceriam telefone)
        $t = preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u', '[E-MAIL]', $t);
        // CNPJ: 00.000.000/0000-00 ou 14 dígitos seguidos
        $t = preg_replace('/(?<!\d)\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}(?!\d)/', '[CNPJ]', $t);
        // CPF: 000.000.000-00 ou 11 dígitos seguidos
        $t = preg_replace('/(?<!\d)\d{3}\.\d{3}\.\d{3}-\d{2}(?!\d)/', '[CPF]', $t);
        $t = preg_replace('/(?<![\d.,])\d{11}(?![\d.,])/', '[CPF]', $t);
        // Telefone: +55, DDD entre parênteses ou não, 8 ou 9 dígitos com hífen/espaço
        $t = preg_replace('/(?:\+?55[\s\-]?)?(?:\(?\d{2}\)?[\s\-]?)?9?\d{4}[\s\-]\d{4}(?!\d)/', '[TELEFONE]', $t);
        $t = preg_replace('/(?<![\d.,])\(\d{2}\)\s?9?\d{8}(?!\d)/', '[TELEFONE]', $t);
        // Nomes conhecidos (completos e cada parte com 4+ letras, exceto palavras comuns)
        $partes = [];
        foreach ($nomes as $n) {
            $n = trim(preg_replace('/\s+/u', ' ', (string) $n));
            if (mb_strlen($n) < 3) {
                continue;
            }
            $partes[$n] = TRUE;
            foreach (explode(' ', $n) as $p) {
                if (mb_strlen($p) >= 4 && !in_array(mb_strtolower($p), ['silva', 'santos', 'souza', 'sousa', 'filho', 'junior', 'júnior', 'neto'], TRUE)) {
                    $partes[$p] = TRUE;
                }
            }
        }
        $partes = array_keys($partes);
        usort($partes, function ($a, $b) { return mb_strlen($b) - mb_strlen($a); });
        // Uma expressão para cada 200 nomes (do mais longo para o mais curto)
        foreach (array_chunk($partes, 200) as $bloco) {
            $alt = implode('|', array_map(function ($p) { return preg_quote($p, '/'); }, $bloco));
            $novo = preg_replace('/(?<![\p{L}])(?:' . $alt . ')(?![\p{L}])/iu', '[NOME]', $t);
            if ($novo !== NULL) {
                $t = $novo;
            }
        }
        // Junta marcadores repetidos ("[NOME] [NOME]" -> "[NOME]")
        $t = preg_replace('/\[NOME\](?:\s+\[NOME\])+/u', '[NOME]', $t);
        return $t;
    }

    /** Aplica em todas as mensagens (formato chat: role + content texto). */
    public static function mensagens(array $mensagens, array $nomes = []) {
        foreach ($mensagens as $i => $m) {
            if (isset($m['content']) && is_string($m['content'])) {
                $mensagens[$i]['content'] = self::limpar($m['content'], $nomes);
            }
        }
        return $mensagens;
    }
}
