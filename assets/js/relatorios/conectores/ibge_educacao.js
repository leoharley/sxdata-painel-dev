/**
 * Eixo "Educação".
 *
 * ideb_anos_iniciais       IBGE Cidades 78187 (INEP, rede pública), município e UF
 * ideb_anos_finais         IBGE Cidades 78192 (INEP, rede pública), município e UF
 * ideb_ensino_medio        IBGE Cidades 78197 (INEP, rede pública), município e UF
 * taxa_analfabetismo       100 - SIDRA 9543 v2513 (taxa de alfabetização, 15 anos ou mais, Censo 2022)
 * taxa_escolarizacao_6_14  SIDRA 10139 v12805 (Censo 2022) + IBGE Cidades 60045 (Censo 2010, município)
 * matriculas_etapa         IBGE Cidades, pesquisa 13 (INEP - Censo Escolar), ano mais recente
 */
(function () {
    'use strict';

    var IDEB = {
        ideb_anos_iniciais: 78187,
        ideb_anos_finais: 78192,
        ideb_ensino_medio: 78197
    };

    var ETAPAS = {
        77882: 'Creche',
        5903: 'Pré-escola',
        5908: 'Ensino fundamental',
        5913: 'Ensino médio'
    };

    SXConectores.registrar({
        chave: 'ibge_educacao',
        rotulo: 'IBGE / INEP - Educação (IDEB, Censo e Censo Escolar)',
        eixos: ['educacao'],

        executar: function (ctx) {
            return SXIBGE.juntar({
                'IDEB': async function () {
                    var ids = Object.keys(IDEB).map(function (k) { return IDEB[k]; });
                    var dados = await SXIBGE.indicadores(ids, ctx, true);
                    var linhas = [];
                    Object.keys(IDEB).forEach(function (chave) {
                        linhas = linhas.concat(SXConectores.serie(chave, SXIBGE.serieIndicador(dados, IDEB[chave]),
                            { fonte: 'INEP - IDEB, rede pública (via IBGE Cidades)' }));
                    });
                    return linhas;
                },

                'Analfabetismo': async function () {
                    var v = await SXIBGE.agregado(9543, ctx, {
                        variaveis: [2513], periodos: '2022', classificacao: '2[6794]|86[95251]|287[100362]'
                    });
                    var linhas = [];
                    v.forEach(function (x) {
                        if (x.valor === null) return;
                        linhas.push(SXConectores.linha('taxa_analfabetismo', x.abrangencia, x.periodo,
                            SXIBGE.arredondar(100 - x.valor, 2),
                            { fonte: 'IBGE - Censo Demográfico 2022 (tabela 9543; 100 - taxa de alfabetização)' }));
                    });
                    return linhas;
                },

                'Escolarização de 6 a 14 anos': async function () {
                    var v = await SXIBGE.agregado(10139, ctx, {
                        variaveis: [12805], periodos: '2022', classificacao: '2[6794]|58[31615]|839[46583]'
                    });
                    var dados = await SXIBGE.indicadores([60045], ctx, false);
                    var antiga = SXIBGE.serieIndicador(dados, 60045);
                    if (antiga.municipio) delete antiga.municipio['2022'];
                    return [].concat(
                        SXConectores.serie('taxa_escolarizacao_6_14', SXIBGE.porAbrangencia(v),
                            { fonte: 'IBGE - Censo Demográfico 2022 (tabela 10139)' }),
                        SXConectores.serie('taxa_escolarizacao_6_14', antiga,
                            { fonte: 'IBGE - Censo Demográfico 2010 (IBGE Cidades)' })
                    );
                },

                'Matrículas por etapa': async function () {
                    var ids = Object.keys(ETAPAS);
                    var dados = await SXIBGE.indicadores(ids, ctx, false);
                    var periodo = null;
                    ids.forEach(function (id) {
                        var r = SXIBGE.maisRecente(dados, id);
                        if (r && (!periodo || r.periodo > periodo)) periodo = r.periodo;
                    });
                    if (!periodo) return [];
                    var cats = {};
                    ids.forEach(function (id) {
                        var v = SXConectores.numero(((dados[id] || {}).municipio || {})[periodo]);
                        if (v !== null) cats[ETAPAS[id]] = v;
                    });
                    return SXConectores.categorias('matriculas_etapa', 'municipio', periodo, cats,
                        { fonte: 'INEP - Censo Escolar (via IBGE Cidades)' });
                }
            });
        }
    });
})();
