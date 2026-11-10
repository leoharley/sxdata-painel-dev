/**
 * Eixo "Território e população".
 *
 * populacao_estimada     SIDRA 6579 v9324 (estimativas) + 4714 v93 (Censo 2022, ano sem estimativa)
 * densidade_demografica  SIDRA 4714 v614 (Censo 2022)
 * taxa_urbanizacao       SIDRA 202 v1000093 (Censos 1991-2010) + 9923 v1000093 (Censo 2022), situação urbana
 * populacao_faixa_sexo   SIDRA 9514 v93 (Censo 2022), grupos de 5 anos; 80 anos ou mais somados
 * domicilios             SIDRA 4712 v381 (Censo 2022)
 * densidade_domiciliar   SIDRA 4712 v5930 (Censo 2022)
 * saneamento_basico      SIDRA 6803, 6805 e 6892 v1000381 (Censo 2022), % dos domicílios
 */
(function () {
    'use strict';

    // Tabela 9514, classificação 287 (idade): grupos de 5 anos
    var FAIXAS = {
        93070: '0 a 4 anos', 93084: '5 a 9 anos', 93085: '10 a 14 anos', 93086: '15 a 19 anos',
        93087: '20 a 24 anos', 93088: '25 a 29 anos', 93089: '30 a 34 anos', 93090: '35 a 39 anos',
        93091: '40 a 44 anos', 93092: '45 a 49 anos', 93093: '50 a 54 anos', 93094: '55 a 59 anos',
        93095: '60 a 64 anos', 93096: '65 a 69 anos', 93097: '70 a 74 anos', 93098: '75 a 79 anos',
        49108: '80 anos ou mais', 49109: '80 anos ou mais', 60040: '80 anos ou mais',
        60041: '80 anos ou mais', 6653: '80 anos ou mais'
    };
    var SEXO = { 4: 'Homens', 5: 'Mulheres' };

    var SANEAMENTO = [
        // [tabela, classificação, categoria, rótulo no catálogo]
        [6803, 1821, 72144, 'Água (rede geral)'],
        [6805, 11558, 46290, 'Esgoto (rede coletora)'],
        [6892, 67, 2520, 'Coleta de resíduos']
    ];

    SXConectores.registrar({
        chave: 'ibge_populacao',
        rotulo: 'IBGE - Território e população (estimativas e Censo)',
        eixos: ['territorio_populacao'],

        executar: function (ctx) {
            return SXIBGE.juntar({
                'População estimada': async function () {
                    var est = await SXIBGE.agregado(6579, ctx, { variaveis: [9324], periodos: 'all' });
                    var censo = await SXIBGE.agregado(4714, ctx, { variaveis: [93], periodos: '2022' });
                    return [].concat(
                        SXConectores.serie('populacao_estimada', SXIBGE.porAbrangencia(est),
                            { fonte: 'IBGE - Estimativas populacionais (tabela 6579)' }),
                        SXConectores.serie('populacao_estimada', SXIBGE.porAbrangencia(censo),
                            { fonte: 'IBGE - Censo Demográfico 2022 (tabela 4714)' })
                    );
                },

                'Densidade demográfica': async function () {
                    var v = await SXIBGE.agregado(4714, ctx, { variaveis: [614], periodos: '2022' });
                    return SXConectores.serie('densidade_demografica', SXIBGE.porAbrangencia(v),
                        { fonte: 'IBGE - Censo Demográfico 2022 (tabela 4714)' });
                },

                'Taxa de urbanização': async function () {
                    var antigos = await SXIBGE.agregado(202, ctx, {
                        variaveis: [1000093], periodos: '1991|2000|2010', classificacao: '1[1]|2[0]'
                    });
                    var c2022 = await SXIBGE.agregado(9923, ctx, {
                        variaveis: [1000093], periodos: '2022', classificacao: '1[1]'
                    });
                    return [].concat(
                        SXConectores.serie('taxa_urbanizacao', SXIBGE.porAbrangencia(antigos),
                            { fonte: 'IBGE - Censos Demográficos (tabela 202)' }),
                        SXConectores.serie('taxa_urbanizacao', SXIBGE.porAbrangencia(c2022),
                            { fonte: 'IBGE - Censo Demográfico 2022 (tabela 9923)' })
                    );
                },

                'Pirâmide etária': async function () {
                    var v = await SXIBGE.agregado(9514, ctx, {
                        variaveis: [93], periodos: '2022', niveis: ['municipio'],
                        classificacao: '2[4,5]|286[113635]|287[' + Object.keys(FAIXAS).join(',') + ']'
                    });
                    var soma = {};
                    v.forEach(function (x) {
                        if (x.valor === null) return;
                        var faixa = FAIXAS[x.categoriaIds['287']];
                        var sexo = SEXO[x.categoriaIds['2']];
                        if (!faixa || !sexo) return;
                        var cat = faixa + '|' + sexo;
                        soma[cat] = (soma[cat] || 0) + x.valor;
                    });
                    return SXConectores.categorias('populacao_faixa_sexo', 'municipio', '2022', soma,
                        { fonte: 'IBGE - Censo Demográfico 2022 (tabela 9514)' });
                },

                'Domicílios': async function () {
                    var v = await SXIBGE.agregado(4712, ctx, { variaveis: [381, 5930], periodos: '2022' });
                    return [].concat(
                        SXConectores.serie('domicilios', SXIBGE.porAbrangencia(v, function (x) { return x.variavel === '381'; }),
                            { fonte: 'IBGE - Censo Demográfico 2022 (tabela 4712)' }),
                        SXConectores.serie('densidade_domiciliar', SXIBGE.porAbrangencia(v, function (x) { return x.variavel === '5930'; }),
                            { fonte: 'IBGE - Censo Demográfico 2022 (tabela 4712)' })
                    );
                },

                'Saneamento básico': async function () {
                    var linhas = [];
                    for (var i = 0; i < SANEAMENTO.length; i++) {
                        var s = SANEAMENTO[i];
                        var v = await SXIBGE.agregado(s[0], ctx, {
                            variaveis: [1000381], periodos: '2022', classificacao: s[1] + '[' + s[2] + ']'
                        });
                        v.forEach(function (x) {
                            if (x.valor === null) return;
                            var l = SXConectores.linha('saneamento_basico', x.abrangencia, x.periodo, x.valor,
                                { fonte: 'IBGE - Censo Demográfico 2022 (tabela ' + s[0] + ')' });
                            l.categoria = s[3];
                            linhas.push(l);
                        });
                    }
                    return linhas;
                }
            });
        }
    });
})();
