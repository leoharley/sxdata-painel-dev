<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Política de senha do painel e do app. Vale para senha nova (cadastro,
 * troca e redefinição); senhas já gravadas continuam funcionando.
 */
class Politica_senha {

    const MINIMO = 8;

    private static $comuns = ['12345678', '123456789', '1234567890', 'password', 'password1', 'senha123',
        'senha1234', 'qwerty123', 'abc12345', 'abcd1234', 'admin123', 'admin1234', 'sxdata123', 'mudar123',
        'brasil123', 'teste123', '11111111', '00000000', 'a1b2c3d4'];

    /** Lista de problemas (vazia = senha aceita). */
    public static function validar($senha, $usuario = NULL, $nome = NULL) {
        $senha = (string) $senha;
        $erros = [];
        if (function_exists('mb_strlen') ? mb_strlen($senha) < self::MINIMO : strlen($senha) < self::MINIMO) {
            $erros[] = 'A senha precisa ter pelo menos ' . self::MINIMO . ' caracteres.';
        }
        if (!preg_match('/[A-Za-zÀ-ÿ]/u', $senha)) {
            $erros[] = 'A senha precisa ter pelo menos uma letra.';
        }
        if (!preg_match('/\d/', $senha)) {
            $erros[] = 'A senha precisa ter pelo menos um número.';
        }
        $minuscula = strtolower($senha);
        if (in_array($minuscula, self::$comuns, TRUE) || preg_match('/^(.)\1+$/', $senha)) {
            $erros[] = 'Essa senha é muito comum. Escolha outra.';
        }
        if ($usuario !== NULL && $usuario !== '' && stripos($senha, (string) $usuario) !== FALSE) {
            $erros[] = 'A senha não pode conter o nome de usuário.';
        }
        if ($nome) {
            foreach (preg_split('/\s+/', strtolower(trim((string) $nome))) as $parte) {
                if (strlen($parte) >= 4 && strpos($minuscula, $parte) !== FALSE) {
                    $erros[] = 'A senha não pode conter o seu nome.';
                    break;
                }
            }
        }
        return $erros;
    }

    /** Senha aleatória que já cumpre a política (redefinição pelo administrador). */
    public static function gerar($tamanho = 10) {
        $letras = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $numeros = '23456789';
        $todos = $letras . $numeros;
        $s = $letras[random_int(0, strlen($letras) - 1)] . $numeros[random_int(0, strlen($numeros) - 1)];
        while (strlen($s) < $tamanho) {
            $s .= $todos[random_int(0, strlen($todos) - 1)];
        }
        return str_shuffle($s);
    }

    /** Texto para mostrar perto do campo de senha. */
    public static function descricao() {
        return 'Mínimo de ' . self::MINIMO . ' caracteres, com letras e números. Não use o seu nome nem o usuário.';
    }
}
