/**
 * Eixo "Saúde - Estrutura, morbidade e mortalidade" (parte acessível pelo navegador).
 *
 * mortalidade_infantil  IBGE Cidades 30279 (Ministério da Saúde - SIM/SINASC), município e UF
 *
 * Os estabelecimentos de saúde vêm do CNES pelo servidor (Conector_cnes): a
 * API de dados abertos do Ministério da Saúde não libera CORS.
 */
(function () {
    'use strict';

    SXConectores.registrar({
        chave: 'ibge_saude',
        rotulo: 'IBGE Cidades - Saúde (mortalidade infantil)',
        eixos: ['saude'],

        executar: async function (ctx) {
            var dados = await SXIBGE.indicadores([30279], ctx, true);
            return SXConectores.serie('mortalidade_infantil', SXIBGE.serieIndicador(dados, 30279),
                { fonte: 'Ministério da Saúde - SIM/SINASC (via IBGE Cidades)' });
        }
    });
})();
