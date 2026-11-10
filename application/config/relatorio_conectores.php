<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| ============================================================================
| Conectores de dados dos relatórios municipais
| ============================================================================
|
| Um conector busca dados de uma fonte (IBGE, DATASUS, INEP, e-SUS...) e
| devolve valores no formato do catálogo (application/config/relatorio_catalogo.php).
| Ver docs/PROMPT_SERGIO_INTEGRACOES.md.
|
| Há dois tipos, e um relatório pode usar os dois:
|
| JavaScript (no navegador) — para APIs públicas que aceitam chamadas do
| navegador (IBGE/SIDRA, servicodados). Reaproveita o código da página IBGE
| Panorama. Os arquivos ficam em assets/js/relatorios/conectores/ e rodam na
| etapa "Processamento" do relatório.
|
| PHP (no servidor) — para fontes que o navegador não alcança: a base local
| do e-SUS (banco do município, acesso por VPN/credencial) e APIs que exigem
| chave ou não liberam CORS. As classes ficam em
| application/libraries/conectores/ e estendem Relatorio_conector.
*/

// Caminhos relativos a assets/js/relatorios/conectores/, na ordem de execução.
// ibge_comum.js não é conector: traz as funções usadas pelos demais (SXIBGE).
$config['relatorio_conectores_js'] = [
    'ibge_comum.js',
    'ibge_caracterizacao.js',
    'ibge_populacao.js',
    'ibge_economia.js',
    'ibge_educacao.js',
    'ibge_meio_ambiente.js',
    'ibge_saude.js',
];

// Nomes das classes (arquivo application/libraries/conectores/<Classe>.php).
// Conector_esus_base não entra aqui: é só a classe base dos dois do e-SUS.
$config['relatorio_conectores_php'] = [
    'Conector_cnes',      // CNES (dados abertos do Ministério da Saúde; sem CORS)
    'Conector_esus_aps',  // depende das credenciais ESUS_<COD_IBGE>_* no .env
    'Conector_esus_mac',
];
