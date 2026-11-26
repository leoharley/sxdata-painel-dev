<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Monitor_erros.php';

/**
 * Leva os erros do PHP e as exceções não tratadas para a tela
 * "Monitoramento > Erros" (tabela erros_sistema), além do log de sempre.
 * Avisos de depreciação e notices ficam só no log.
 */
class MY_Exceptions extends CI_Exceptions {

    private static $ignorar = [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED, E_STRICT];

    public function log_exception($severity, $message, $filepath, $line) {
        parent::log_exception($severity, $message, $filepath, $line);
        if (is_int($severity) && in_array($severity, self::$ignorar, TRUE)) {
            return;
        }
        $nivel = is_int($severity) ? (isset($this->levels[$severity]) ? strtolower($this->levels[$severity]) : 'erro') : (string) $severity;
        Monitor_erros::registrar(Monitor_erros::origem(), $nivel, $message, $filepath, $line);
    }

    public function show_error($heading, $message, $template = 'error_general', $status_code = 500) {
        if ((int) $status_code >= 500) {
            $texto = is_array($message) ? implode(' | ', $message) : (string) $message;
            Monitor_erros::registrar(Monitor_erros::origem(), $template === 'error_db' ? 'banco' : 'erro',
                trim(strip_tags($heading . ': ' . $texto)));
        }
        return parent::show_error($heading, $message, $template, $status_code);
    }
}
