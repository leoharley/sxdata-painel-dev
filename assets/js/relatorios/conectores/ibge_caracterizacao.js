/**
 * Eixo "Caracterização do município".
 *
 * historia          IBGE Biblioteca (histórico do município)
 * quadro_politico   IBGE Cidades, indicador 29170 (prefeito; origem TSE)
 * area_territorial  SIDRA 4714, variável 6318 (Censo 2022)
 * populacao_censo   SIDRA 4714, variável 93 (Censo 2022) + IBGE Cidades 96385 (Censo 2010)
 */
(function () {
    'use strict';

    /**
     * A API da Biblioteca devolve um "JSON" com a chave sem aspas e quebras
     * de linha cruas dentro dos textos: {1713601:{"HISTORICO":"..."}}
     */
    function lerBiblioteca(texto) {
        var t = String(texto || '').trim();
        if (!t || t === '{}' || t === '[]') return null;
        t = t.replace(/^\{\s*(\d+)\s*:/, '{"$1":')
             .replace(/\r\n|\r|\n/g, '\\n')
             .replace(/[\u0000-\u001f]/g, ' ');
        var json;
        try { json = JSON.parse(t); } catch (e) { throw new Error('histórico do IBGE em formato inesperado'); }
        var chaves = Object.keys(json);
        return chaves.length ? json[chaves[0]] : null;
    }

    function limparTexto(s) {
        return String(s || '')
            .replace(/<br\s*\/?>/gi, '\n')
            .replace(/<[^>]+>/g, '')
            .replace(/&nbsp;/g, ' ')
            .split(/\n/)
            .map(function (p) { return p.replace(/\s+/g, ' ').trim(); })
            .filter(Boolean)
            .join('\n\n');
    }

    SXConectores.registrar({
        chave: 'ibge_caracterizacao',
        rotulo: 'IBGE - Caracterização (histórico, prefeito, área e população)',
        eixos: ['caracterizacao'],

        executar: function (ctx) {
            return SXIBGE.juntar({
                'Histórico': async function () {
                    var txt = await SXIBGE.buscar(SXIBGE.BASE + '/v1/biblioteca?codmun=' + ctx.codigoIbge, 'o histórico do município', true);
                    var reg = lerBiblioteca(txt);
                    if (!reg) return [];
                    var partes = [];
                    var historico = limparTexto(reg.HISTORICO);
                    if (historico) partes.push(historico);
                    var formacao = limparTexto(reg.FORMACAO_ADMINISTRATIVA);
                    if (formacao) partes.push('Formação administrativa\n\n' + formacao);
                    var gentilico = limparTexto(reg.GENTILICO);
                    if (gentilico) partes.push('Gentílico: ' + gentilico + '.');
                    return SXConectores.texto('historia', partes.join('\n\n'), {
                        fonte: 'IBGE Cidades - Histórico' + (reg.ANO ? ' (Biblioteca do IBGE, ' + reg.ANO + ')' : ''),
                        periodo_fonte: reg.ANO || ''
                    });
                },

                'Prefeito': async function () {
                    var dados = await SXIBGE.indicadores([29170], ctx, false);
                    var ultimo = SXIBGE.maisRecente(dados, 29170);
                    if (!ultimo) return [];
                    var ini = parseInt(ultimo.periodo, 10);
                    var texto = 'Prefeito(a) do mandato ' + ini + '-' + (ini + 3) + ': ' + SXIBGE.nomeProprio(ultimo.valor) + '.';
                    return SXConectores.texto('quadro_politico', texto, {
                        fonte: 'IBGE Cidades (dados do TSE)', periodo_fonte: ultimo.periodo
                    });
                },

                'Área e população do Censo 2022': async function () {
                    var v = await SXIBGE.agregado(4714, ctx, { variaveis: [93, 6318], periodos: '2022' });
                    return [].concat(
                        SXConectores.serie('area_territorial', SXIBGE.porAbrangencia(v, function (x) { return x.variavel === '6318'; }),
                            { fonte: 'IBGE - Censo Demográfico 2022 (tabela 4714)' }),
                        SXConectores.serie('populacao_censo', SXIBGE.porAbrangencia(v, function (x) { return x.variavel === '93'; }),
                            { fonte: 'IBGE - Censo Demográfico 2022 (tabela 4714)' })
                    );
                },

                'População do Censo 2010': async function () {
                    var dados = await SXIBGE.indicadores([96385], ctx, false);
                    var serie = SXIBGE.serieIndicador(dados, 96385);
                    // 2022 já vem da tabela 4714, com estado e Brasil
                    if (serie.municipio) delete serie.municipio['2022'];
                    return SXConectores.serie('populacao_censo', serie, { fonte: 'IBGE - Censo Demográfico 2010 (IBGE Cidades)' });
                }
            });
        }
    });
})();
