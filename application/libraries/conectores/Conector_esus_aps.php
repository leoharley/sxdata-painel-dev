<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once __DIR__ . '/Conector_esus_base.php';

/**
 * e-SUS APS (base municipal) - eixo "Saúde - Atenção Primária".
 *
 * Indicadores previstos: equipes_esf, cobertura_aps, profissionais_aps
 * (categoria: categoria profissional), atendimentos_aps e procedimentos_aps
 * (categoria: tipo de procedimento). ubs_quantidade vem do CNES (Conector_cnes).
 *
 * Depende do acesso do cliente; ver Conector_esus_base.
 */
class Conector_esus_aps extends Conector_esus_base {

    public function chave()  { return 'esus_aps'; }
    public function rotulo() { return 'e-SUS APS (base municipal)'; }
    public function eixos()  { return ['saude_aps']; }

    public function executar($relatorio) {
        $this->conectar($relatorio); // sem credencial: exceção "credencial não configurada"

        // Quando o acesso for liberado, substituir pela leitura real, ex.:
        //   $r = $this->consultar($relatorio, 'SELECT ... WHERE ano = $1', [$ano]);
        //   $linhas[] = $this->linha('atendimentos_aps', 'municipio', $ano, (int) $r[0]['total'],
        //                            ['fonte' => 'e-SUS APS (base municipal)']);
        $this->consultas_pendentes($relatorio,
            ['equipes_esf', 'cobertura_aps', 'profissionais_aps', 'atendimentos_aps', 'procedimentos_aps']);
        return [];
    }
}
