<?php
// Testes das classes puras do SXData (sem CodeIgniter, sem banco).
// Rodar na raiz: php vendor/bin/phpunit -c tests/sxdata/phpunit.xml
define('BASEPATH', __DIR__ . '/../../system/');
define('APPPATH', __DIR__ . '/../../application/');

require_once APPPATH . 'libraries/Geo_municipio.php';
require_once APPPATH . 'libraries/Ponto_campo.php';
require_once APPPATH . 'libraries/Mapa_temas.php';
require_once APPPATH . 'libraries/Expressao.php';
require_once APPPATH . 'libraries/Estrutura_motor.php';
require_once APPPATH . 'libraries/Estrutura_compilador.php';
require_once APPPATH . 'libraries/Estrutura_envio.php';
require_once APPPATH . 'libraries/Totp.php';
require_once APPPATH . 'libraries/Politica_senha.php';
require_once APPPATH . 'libraries/Validacao_simples.php';
require_once APPPATH . 'libraries/Anonimizador.php';
require_once APPPATH . 'libraries/Ia_provedores.php';
require_once APPPATH . 'libraries/Cripto_chaves.php';
require_once APPPATH . 'libraries/Cruzamento.php';
