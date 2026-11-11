<?php
// Testes das classes puras do SXData (sem CodeIgniter, sem banco).
// Rodar na raiz: php vendor/bin/phpunit -c tests/sxdata/phpunit.xml
define('BASEPATH', __DIR__ . '/../../system/');
define('APPPATH', __DIR__ . '/../../application/');

require_once APPPATH . 'libraries/Geo_municipio.php';
require_once APPPATH . 'libraries/Ponto_campo.php';
