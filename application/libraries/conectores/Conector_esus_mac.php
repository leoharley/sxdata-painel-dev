<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once __DIR__ . '/Conector_esus_base.php';

/**
 * e-SUS (base municipal) - eixo "Saúde - Média e Alta Complexidade".
 *
 * Indicadores previstos: leitos_sus, internacoes_total,
 * atendimentos_ambulatoriais_mac e procedimentos_mac (categoria: grupo de
 * procedimento). estabelecimentos_mac vem do CNES (Conector_cnes).
 *
 * Depende do acesso do cliente; ver Conector_esus_base.
 */
class Conector_esus_mac extends Conector_esus_base {

    public function chave()  { return 'esus_mac'; }
    public function rotulo() { return 'e-SUS - Média e alta complexidade (base municipal)'; }
    public function eixos()  { return ['saude_mac']; }

    public function executar($relatorio) {
        $this->conectar($relatorio); // sem credencial: exceção "credencial não configurada"

        $this->consultas_pendentes($relatorio,
            ['leitos_sus', 'internacoes_total', 'atendimentos_ambulatoriais_mac', 'procedimentos_mac']);
        return [];
    }
}
