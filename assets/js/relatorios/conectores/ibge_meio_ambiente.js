/**
 * Eixo "Meio ambiente".
 *
 * esgotamento_adequado    SIDRA 6805 v1000381 (Censo 2022): rede geral, rede pluvial ou fossa ligada à rede
 * informacoes_ambientais  texto montado com indicadores do IBGE Cidades: bioma (77861), sistema
 *                         costeiro-marinho (82270), arborização (60029) e urbanização de vias (60031),
 *                         área urbanizada (95335) e população exposta a risco (93371)
 */
(function () {
    'use strict';

    SXConectores.registrar({
        chave: 'ibge_meio_ambiente',
        rotulo: 'IBGE - Meio ambiente (Censo e IBGE Cidades)',
        eixos: ['meio_ambiente'],

        executar: function (ctx) {
            return SXIBGE.juntar({
                'Esgotamento sanitário': async function () {
                    var v = await SXIBGE.agregado(6805, ctx, {
                        variaveis: [1000381], periodos: '2022', classificacao: '11558[46290]'
                    });
                    return SXConectores.serie('esgotamento_adequado', SXIBGE.porAbrangencia(v),
                        { fonte: 'IBGE - Censo Demográfico 2022 (tabela 6805; rede geral, pluvial ou fossa ligada à rede)' });
                },

                'Informações ambientais': async function () {
                    var d = await SXIBGE.indicadores([77861, 82270, 60029, 60031, 95335, 93371], ctx, false);
                    var frases = [];
                    var anos = [];
                    function usar(r) { if (r) anos.push(r.periodo); return r; }

                    var bioma = usar(SXIBGE.maisRecente(d, 77861));
                    if (bioma) frases.push('Bioma predominante: ' + bioma.valor + '.');

                    var costa = SXIBGE.maisRecente(d, 82270);
                    if (costa) frases.push(/^n[aã]o/i.test(costa.valor)
                        ? 'O município não pertence ao sistema costeiro-marinho.'
                        : 'O município pertence ao sistema costeiro-marinho.');

                    var arb = usar(SXIBGE.maisRecente(d, 60029));
                    if (arb && SXConectores.numero(arb.valor) !== null) {
                        frases.push('Arborização de vias públicas: ' + SXIBGE.br(SXConectores.numero(arb.valor), 1)
                            + '% dos domicílios urbanos em vias com arborização (' + arb.periodo + ').');
                    }
                    var urb = usar(SXIBGE.maisRecente(d, 60031));
                    if (urb && SXConectores.numero(urb.valor) !== null) {
                        frases.push('Urbanização de vias públicas: ' + SXIBGE.br(SXConectores.numero(urb.valor), 1)
                            + '% dos domicílios urbanos em vias com bueiro, calçada, pavimentação e meio-fio (' + urb.periodo + ').');
                    }
                    var area = usar(SXIBGE.maisRecente(d, 95335));
                    if (area && SXConectores.numero(area.valor) !== null) {
                        frases.push('Área urbanizada: ' + SXIBGE.br(SXConectores.numero(area.valor), 2) + ' km² (' + area.periodo + ').');
                    }
                    var risco = usar(SXIBGE.maisRecente(d, 93371));
                    if (risco && SXConectores.numero(risco.valor) !== null) {
                        frases.push('População exposta a risco de desastres (deslizamentos, enchentes e inundações): '
                            + SXIBGE.br(SXConectores.numero(risco.valor)) + ' pessoas (' + risco.periodo + ').');
                    }

                    anos.sort();
                    return SXConectores.texto('informacoes_ambientais', frases.join('\n'), {
                        fonte: 'IBGE Cidades (Censo, Biomas e áreas de risco)',
                        periodo_fonte: anos.length ? anos[anos.length - 1] : ''
                    });
                }
            });
        }
    });
})();
