<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Cifra as chaves de API guardadas no banco (tabela ia_chaves). sodium
 * (XSalsa20-Poly1305); a chave mestra vem do servidor, nunca do banco:
 *
 *   1. IA_CHAVE_MESTRA no .env (64 caracteres hexadecimais), ou
 *   2. derivada do segredo dos tokens do app (API_TOKEN_SECRET no .env ou
 *      application/cache/api_token_secret.php).
 *
 * Trocar a chave mestra torna as chaves guardadas ilegíveis: basta
 * cadastrá-las de novo na tela de provedores.
 */
class Cripto_chaves {

    public static function cifrar($texto, $mestra = NULL) {
        $k = self::chave($mestra);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'v1:' . base64_encode($nonce . sodium_crypto_secretbox((string) $texto, $nonce, $k));
    }

    /** Texto original, ou NULL se não decifrar (chave mestra trocada, dado corrompido). */
    public static function decifrar($cifrado, $mestra = NULL) {
        if (!is_string($cifrado) || strpos($cifrado, 'v1:') !== 0) {
            return NULL;
        }
        $bin = base64_decode(substr($cifrado, 3), TRUE);
        if ($bin === FALSE || strlen($bin) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return NULL;
        }
        $nonce = substr($bin, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $r = sodium_crypto_secretbox_open(substr($bin, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, self::chave($mestra));
        return $r === FALSE ? NULL : $r;
    }

    public static function disponivel() {
        return function_exists('sodium_crypto_secretbox');
    }

    private static function chave($mestra) {
        if ($mestra === NULL) {
            $env = getenv('IA_CHAVE_MESTRA');
            if (is_string($env) && preg_match('/^[0-9a-f]{64}$/i', $env)) {
                return hex2bin($env);
            }
            $mestra = getenv('API_TOKEN_SECRET');
            if (!is_string($mestra) || strlen($mestra) < 32) {
                $arq = APPPATH . 'cache/api_token_secret.php';
                $mestra = is_file($arq) ? include $arq : NULL;
            }
            if (!is_string($mestra) || strlen($mestra) < 32) {
                throw new RuntimeException('Sem chave mestra: defina IA_CHAVE_MESTRA no .env do servidor.');
            }
        }
        return hash_hmac('sha256', 'sxdata-ia-chaves-v1', (string) $mestra, TRUE);
    }
}
