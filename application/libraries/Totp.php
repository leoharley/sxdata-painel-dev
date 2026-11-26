<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Senha de uso único por tempo (TOTP, RFC 6238), compatível com Google
 * Authenticator, Microsoft Authenticator, Authy e afins. PHP puro, sem
 * dependência: HMAC-SHA1, passo de 30 s, 6 dígitos.
 */
class Totp {

    const PASSO = 30;
    const DIGITOS = 6;
    const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Segredo novo em base32 (160 bits, como recomenda a RFC 4226). */
    public static function gerar_segredo($bytes = 20) {
        return self::base32_encode(random_bytes($bytes));
    }

    public static function base32_encode($dados) {
        $bits = '';
        foreach (str_split($dados) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 5) as $bloco) {
            $saida .= self::ALFABETO[bindec(str_pad($bloco, 5, '0'))];
        }
        return $saida;
    }

    public static function base32_decode($texto) {
        $texto = strtoupper(preg_replace('/[\s=-]/', '', (string) $texto));
        $bits = '';
        for ($i = 0, $n = strlen($texto); $i < $n; $i++) {
            $v = strpos(self::ALFABETO, $texto[$i]);
            if ($v === FALSE) {
                return FALSE;
            }
            $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $saida .= chr(bindec($byte));
            }
        }
        return $saida;
    }

    /** Código do passo de tempo informado (ou do atual). */
    public static function codigo($segredo, $passo = NULL, $digitos = self::DIGITOS, $chave_crua = FALSE) {
        $passo = $passo === NULL ? self::passo_atual() : (int) $passo;
        $chave = $chave_crua ? $segredo : self::base32_decode($segredo);
        if ($chave === FALSE || $chave === '') {
            return NULL;
        }
        $contador = pack('N2', ($passo >> 32) & 0xFFFFFFFF, $passo & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $contador, $chave, TRUE);
        $desloc = ord($hash[19]) & 0x0F;
        $valor = ((ord($hash[$desloc]) & 0x7F) << 24)
            | ((ord($hash[$desloc + 1]) & 0xFF) << 16)
            | ((ord($hash[$desloc + 2]) & 0xFF) << 8)
            | (ord($hash[$desloc + 3]) & 0xFF);
        return str_pad((string) ($valor % (10 ** $digitos)), $digitos, '0', STR_PAD_LEFT);
    }

    public static function passo_atual($agora = NULL) {
        return (int) floor(($agora === NULL ? time() : $agora) / self::PASSO);
    }

    /**
     * Confere o código aceitando um passo antes e um depois (relógio do
     * celular adiantado ou atrasado até 30 s). Devolve o passo aceito, para
     * quem chama impedir o reuso do mesmo código, ou FALSE.
     */
    public static function verificar($segredo, $codigo, $ultimo_passo = NULL, $agora = NULL, $janela = 1) {
        $codigo = preg_replace('/\D/', '', (string) $codigo);
        if (strlen($codigo) !== self::DIGITOS) {
            return FALSE;
        }
        $atual = self::passo_atual($agora);
        for ($d = -$janela; $d <= $janela; $d++) {
            $p = $atual + $d;
            if ($ultimo_passo !== NULL && $p <= (int) $ultimo_passo) {
                continue; // código já usado (ou mais antigo que o último usado)
            }
            $esperado = self::codigo($segredo, $p);
            if ($esperado !== NULL && hash_equals($esperado, $codigo)) {
                return $p;
            }
        }
        return FALSE;
    }

    /** Endereço otpauth:// que o aplicativo autenticador lê pelo QR Code. */
    public static function uri($segredo, $conta, $emissor = 'SXData') {
        return 'otpauth://totp/' . rawurlencode($emissor) . ':' . rawurlencode($conta)
            . '?secret=' . $segredo . '&issuer=' . rawurlencode($emissor)
            . '&algorithm=SHA1&digits=' . self::DIGITOS . '&period=' . self::PASSO;
    }
}
