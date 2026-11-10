/**
 * Eixo "Economia e trabalho".
 *
 * pib                  SIDRA 5938 v37 (PIB dos Municípios), últimos 10 anos
 * pib_per_capita       município: IBGE Cidades 47001; estado e Brasil: PIB (5938) / população
 *                      estimada do mesmo ano (6579; em 2022, Censo 4714), como o IBGE calcula
 * pib_composicao       SIDRA 5938 v513, 517, 6575, 525, 543 (ano mais recente, município)
 * pea                  SIDRA 10299 v1641 (Censo 2022): pessoas de 14 anos ou mais na força de trabalho
 * taxa_ocupacao        IBGE Cidades 60036 (pessoal ocupado / população, CEMPRE)
 * ocupados_por_setor   SIDRA 9528 v707 por seção da CNAE (CEMPRE, ano mais recente, município)
 * empresas             SIDRA 1685 (2006-2021) e 9510 (2022 em diante) v706, unidades locais
 * frota_veiculos       IBGE Cidades, pesquisa 22 (Frota, dados da SENATRAN), por tipo
 * producao_agricola    SIDRA 5457 v215 (PAM), valor da produção por produto, ano mais recente
 */
(function () {
    'use strict';

    var COMPOSICAO = {
        '513': 'Agropecuária',
        '517': 'Indústria',
        '6575': 'Serviços',
        '525': 'Administração pública',
        '543': 'Impostos'
    };

    // Tabelas 9510/9528, classificação 12762 (CNAE 2.0): seções
    var SECOES = {
        116830: 'Agropecuária, produção florestal, pesca e aquicultura', 116880: 'Indústrias extrativas',
        116910: 'Indústrias de transformação', 117296: 'Eletricidade e gás',
        117307: 'Água, esgoto e gestão de resíduos', 117329: 'Construção',
        117363: 'Comércio e reparação de veículos', 117484: 'Transporte, armazenagem e correio',
        117543: 'Alojamento e alimentação', 117555: 'Informação e comunicação',
        117608: 'Atividades financeiras e seguros', 117666: 'Atividades imobiliárias',
        117673: 'Atividades profissionais, científicas e técnicas', 117714: 'Atividades administrativas',
        117774: 'Administração pública, defesa e seguridade social', 117788: 'Educação',
        117810: 'Saúde humana e serviços sociais', 117838: 'Artes, cultura, esporte e recreação',
        117861: 'Outras atividades de serviços', 117888: 'Serviços domésticos',
        117892: 'Organismos internacionais'
    };

    // IBGE Cidades, pesquisa 22 (Frota): tipos de veículo
    var FROTA = {
        28122: 'Automóvel', 28123: 'Caminhão', 28124: 'Caminhão trator', 28125: 'Caminhonete',
        28126: 'Camioneta', 28127: 'Micro-ônibus', 28128: 'Motocicleta', 28129: 'Motoneta',
        28130: 'Ônibus', 78164: 'Ciclomotor', 78165: 'Quadriciclo', 78166: 'Reboque',
        78167: 'Semirreboque', 28131: 'Trator de rodas', 78170: 'Triciclo', 28132: 'Utilitário',
        78162: 'Bonde', 78163: 'Chassi plataforma', 78168: 'Sidecar', 78169: 'Trator de esteira',
        28133: 'Outros'
    };

    SXConectores.registrar({
        chave: 'ibge_economia',
        rotulo: 'IBGE - Economia e trabalho (PIB, CEMPRE, PAM, frota)',
        eixos: ['economia_trabalho'],

        executar: function (ctx) {
            return SXIBGE.juntar({
                'PIB e PIB per capita': async function () {
                    var pib = await SXIBGE.agregado(5938, ctx, { variaveis: [37], periodos: '-10' });
                    var linhas = SXConectores.serie('pib', SXIBGE.porAbrangencia(pib),
                        { fonte: 'IBGE - PIB dos Municípios (tabela 5938)' });

                    // Per capita do município: valor oficial do IBGE
                    var cidades = await SXIBGE.indicadores([47001], ctx, false);
                    var anosPib = {};
                    pib.forEach(function (x) { anosPib[x.periodo] = true; });
                    var pcMun = SXIBGE.serieIndicador(cidades, 47001).municipio || {};
                    Object.keys(pcMun).forEach(function (p) { if (!anosPib[p]) delete pcMun[p]; });
                    linhas = linhas.concat(SXConectores.serie('pib_per_capita', { municipio: pcMun },
                        { fonte: 'IBGE - PIB dos Municípios' }));

                    // Estado e Brasil: PIB / população do mesmo ano
                    var anos = Object.keys(anosPib).join('|');
                    var pop = await SXIBGE.agregado(6579, ctx, { variaveis: [9324], periodos: anos, niveis: ['estado', 'brasil'] });
                    if (anosPib['2022']) {
                        pop = pop.concat(await SXIBGE.agregado(4714, ctx, { variaveis: [93], periodos: '2022', niveis: ['estado', 'brasil'] }));
                    }
                    var popPor = SXIBGE.porAbrangencia(pop);
                    var pibPor = SXIBGE.porAbrangencia(pib);
                    ['estado', 'brasil'].forEach(function (abr) {
                        var serie = {};
                        Object.keys(pibPor[abr] || {}).forEach(function (p) {
                            var habitantes = popPor[abr] && popPor[abr][p];
                            if (habitantes) serie[p] = SXIBGE.arredondar(pibPor[abr][p] * 1000 / habitantes, 2);
                        });
                        var s = {}; s[abr] = serie;
                        linhas = linhas.concat(SXConectores.serie('pib_per_capita', s,
                            { fonte: 'IBGE - PIB dos Municípios (tabela 5938) / população estimada (tabela 6579)' }));
                    });
                    return linhas;
                },

                'Composição da economia': async function () {
                    var v = await SXIBGE.agregado(5938, ctx, {
                        // o ano mais recente da tabela às vezes só tem o PIB total no município
                        variaveis: Object.keys(COMPOSICAO), periodos: '-3', niveis: ['municipio']
                    });
                    var periodo = SXIBGE.ultimoPeriodo(v);
                    var cats = {};
                    v.forEach(function (x) { if (x.periodo === periodo && x.valor !== null) cats[COMPOSICAO[x.variavel]] = x.valor; });
                    return SXConectores.categorias('pib_composicao', 'municipio', periodo || '', cats,
                        { fonte: 'IBGE - PIB dos Municípios (tabela 5938)' });
                },

                'População economicamente ativa': async function () {
                    var v = await SXIBGE.agregado(10299, ctx, {
                        variaveis: [1641], periodos: '2022', classificacao: '2[6794]|629[32386]|58[95253]'
                    });
                    return SXConectores.serie('pea', SXIBGE.porAbrangencia(v),
                        { fonte: 'IBGE - Censo Demográfico 2022 (tabela 10299; 14 anos ou mais na força de trabalho)' });
                },

                'Taxa de ocupação': async function () {
                    var dados = await SXIBGE.indicadores([60036], ctx, false);
                    return SXConectores.serie('taxa_ocupacao', SXIBGE.serieIndicador(dados, 60036),
                        { fonte: 'IBGE - Cadastro Central de Empresas (pessoal ocupado / população)' });
                },

                'Ocupados por setor': async function () {
                    var v = await SXIBGE.agregado(9528, ctx, {
                        variaveis: [707], periodos: '-1', niveis: ['municipio'],
                        classificacao: '12762[' + Object.keys(SECOES).join(',') + ']'
                    });
                    var periodo = SXIBGE.ultimoPeriodo(v);
                    var cats = {};
                    v.forEach(function (x) {
                        if (x.periodo !== periodo || x.valor === null || x.valor === 0) return;
                        cats[SECOES[x.categoriaIds['12762']]] = x.valor;
                    });
                    return SXConectores.categorias('ocupados_por_setor', 'municipio', periodo || '', cats,
                        { fonte: 'IBGE - Cadastro Central de Empresas (tabela 9528)' });
                },

                'Empresas': async function () {
                    var antiga = await SXIBGE.agregado(1685, ctx, { variaveis: [706], periodos: '-6' });
                    var nova = await SXIBGE.agregado(9510, ctx, { variaveis: [706], periodos: 'all', classificacao: '12762[117897]' });
                    return [].concat(
                        SXConectores.serie('empresas', SXIBGE.porAbrangencia(antiga),
                            { fonte: 'IBGE - Cadastro Central de Empresas (tabela 1685)' }),
                        SXConectores.serie('empresas', SXIBGE.porAbrangencia(nova),
                            { fonte: 'IBGE - Cadastro Central de Empresas (tabela 9510)' })
                    );
                },

                'Frota de veículos': async function () {
                    var ids = Object.keys(FROTA);
                    var dados = await SXIBGE.indicadores(ids, ctx, false);
                    // ano mais recente comum aos tipos
                    var periodo = null;
                    ids.forEach(function (id) {
                        var r = SXIBGE.maisRecente(dados, id);
                        if (r && (!periodo || r.periodo > periodo)) periodo = r.periodo;
                    });
                    if (!periodo) return [];
                    var cats = {};
                    ids.forEach(function (id) {
                        var m = (dados[id] || {}).municipio || {};
                        var v = SXConectores.numero(m[periodo]);
                        if (v) cats[FROTA[id]] = v;
                    });
                    return SXConectores.categorias('frota_veiculos', 'municipio', periodo, cats,
                        { fonte: 'SENATRAN - Frota de veículos (via IBGE Cidades)' });
                },

                'Produção agrícola': async function () {
                    var v = await SXIBGE.agregado(5457, ctx, {
                        variaveis: [215], periodos: '-1', niveis: ['municipio'], classificacao: '782[allxt]'
                    });
                    var periodo = SXIBGE.ultimoPeriodo(v);
                    var cats = {};
                    v.forEach(function (x) {
                        if (x.periodo !== periodo || !x.valor) return;
                        var nome = String(x.categorias['782'] || '').replace(/\*+$/, '').trim();
                        if (nome && nome !== 'Total') cats[nome] = x.valor;
                    });
                    return SXConectores.categorias('producao_agricola', 'municipio', periodo || '', cats,
                        { fonte: 'IBGE - Produção Agrícola Municipal (tabela 5457)' });
                }
            });
        }
    });
})();
