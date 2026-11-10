/**
 * Funções compartilhadas pelos conectores do IBGE (registrado antes deles em
 * application/config/relatorio_conectores.php).
 *
 * Não é um conector: só expõe window.SXIBGE.
 *
 * Fontes (todas liberam CORS para qualquer origem):
 *  - agregados do SIDRA:  servicodados.ibge.gov.br/api/v3/agregados
 *  - indicadores do IBGE Cidades (Panorama): servicodados.ibge.gov.br/api/v1/pesquisas/indicadores
 *  - histórico do município (Biblioteca): servicodados.ibge.gov.br/api/v1/biblioteca
 *
 * Nada é guardado no navegador: cada execução busca dados atuais.
 */
(function (global) {
    'use strict';

    var BASE = 'https://servicodados.ibge.gov.br/api';
    var MAX_SIMULTANEAS = 3;   // o IBGE recusa rajadas grandes
    var TENTATIVAS = 3;
    var TEMPO_LIMITE = 45000;  // ms por requisição

    // ------------------------------------------------------------------
    // Fila: no máximo MAX_SIMULTANEAS requisições ao mesmo tempo
    // ------------------------------------------------------------------
    var ativas = 0;
    var fila = [];

    function naFila(fn) {
        return new Promise(function (resolve, reject) {
            fila.push({ fn: fn, resolve: resolve, reject: reject });
            proxima();
        });
    }

    function proxima() {
        if (ativas >= MAX_SIMULTANEAS || fila.length === 0) return;
        var item = fila.shift();
        ativas++;
        Promise.resolve().then(item.fn).then(item.resolve, item.reject).then(function () {
            ativas--;
            proxima();
        });
    }

    function esperar(ms) {
        return new Promise(function (r) { setTimeout(r, ms); });
    }

    /**
     * GET com fila, tempo limite e novas tentativas em falhas temporárias.
     * @param {string} url
     * @param {string} descricao  o que está sendo buscado (vai na mensagem de erro)
     * @param {boolean} comoTexto devolve o texto em vez do JSON
     */
    function buscar(url, descricao, comoTexto) {
        return naFila(async function () {
            var ultimoErro = null;
            for (var t = 1; t <= TENTATIVAS; t++) {
                var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
                var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, TEMPO_LIMITE) : null;
                try {
                    var resp = await fetch(url, ctrl ? { signal: ctrl.signal } : undefined);
                    if (!resp.ok) {
                        ultimoErro = new Error('IBGE indisponível (HTTP ' + resp.status + ') ao buscar ' + descricao);
                        // 4xx (exceto 429) não melhora tentando de novo
                        if (resp.status < 500 && resp.status !== 429) throw ultimoErro;
                    } else {
                        return comoTexto ? await resp.text() : await resp.json();
                    }
                } catch (e) {
                    if (e === ultimoErro) throw e;
                    ultimoErro = new Error(
                        (e && e.name === 'AbortError')
                            ? 'IBGE não respondeu a tempo ao buscar ' + descricao
                            : 'Falha de conexão com o IBGE ao buscar ' + descricao + (e && e.message ? ' (' + e.message + ')' : '')
                    );
                } finally {
                    if (timer) clearTimeout(timer);
                }
                if (t < TENTATIVAS) await esperar(1500 * t);
            }
            throw ultimoErro;
        });
    }

    // ------------------------------------------------------------------
    // Agregados do SIDRA (API v3)
    // ------------------------------------------------------------------
    var NIVEL_ABRANGENCIA = { N6: 'municipio', N3: 'estado', N1: 'brasil' };

    function localidades(ctx, niveis) {
        niveis = niveis || ['municipio', 'estado', 'brasil'];
        var partes = [];
        if (niveis.indexOf('municipio') !== -1) partes.push('N6[' + ctx.codigoIbge + ']');
        if (niveis.indexOf('estado') !== -1) partes.push('N3[' + ctx.codigoUf + ']');
        if (niveis.indexOf('brasil') !== -1) partes.push('N1[all]');
        return partes.join('|');
    }

    /**
     * Consulta um agregado e devolve uma lista plana de valores.
     *
     * @param {number} tabela
     * @param {object} op
     *   variaveis     [ids]
     *   periodos      '2022' | '-10' (últimos 10) | 'all'
     *   classificacao '2[4,5]|287[93070]' (opcional)
     *   niveis        ['municipio', 'estado', 'brasil'] (padrão: os três)
     * @return {Promise<Array<{variavel, abrangencia, periodo, valor, categorias, categoriaIds}>>}
     *   categorias:   { idClassificacao: nomeCategoria }
     *   categoriaIds: { idClassificacao: idCategoria }
     */
    async function agregado(tabela, ctx, op) {
        var url = BASE + '/v3/agregados/' + tabela
            + '/periodos/' + encodeURIComponent(op.periodos || 'all')
            + '/variaveis/' + op.variaveis.join('|')
            + '?localidades=' + localidades(ctx, op.niveis)
            + (op.classificacao ? '&classificacao=' + op.classificacao : '');

        var json = await buscar(url, 'a tabela ' + tabela + ' do SIDRA');
        if (!Array.isArray(json)) throw new Error('Resposta inesperada do IBGE (tabela ' + tabela + ')');

        var out = [];
        json.forEach(function (variavel) {
            (variavel.resultados || []).forEach(function (res) {
                var categorias = {};
                var categoriaIds = {};
                (res.classificacoes || []).forEach(function (c) {
                    var ids = Object.keys(c.categoria || {});
                    if (ids.length) {
                        categoriaIds[c.id] = ids[0];
                        categorias[c.id] = c.categoria[ids[0]];
                    }
                });
                (res.series || []).forEach(function (s) {
                    var abr = NIVEL_ABRANGENCIA[s.localidade && s.localidade.nivel && s.localidade.nivel.id];
                    if (!abr) return;
                    Object.keys(s.serie || {}).forEach(function (periodo) {
                        out.push({
                            variavel: String(variavel.id),
                            abrangencia: abr,
                            periodo: periodo,
                            valor: SXConectores.numero(s.serie[periodo]),
                            categorias: categorias,
                            categoriaIds: categoriaIds
                        });
                    });
                });
            });
        });
        return out;
    }

    /**
     * Agrupa valores planos em { municipio: {periodo: valor}, estado: {...}, brasil: {...} },
     * o formato de SXConectores.serie(). Valores nulos ficam de fora.
     */
    function porAbrangencia(valores, filtro) {
        var out = {};
        valores.forEach(function (v) {
            if (v.valor === null) return;
            if (filtro && !filtro(v)) return;
            out[v.abrangencia] = out[v.abrangencia] || {};
            out[v.abrangencia][v.periodo] = v.valor;
        });
        return out;
    }

    /** Período mais recente com valor para a abrangência (padrão: município). */
    function ultimoPeriodo(valores, abrangencia) {
        abrangencia = abrangencia || 'municipio';
        var ps = valores.filter(function (v) { return v.abrangencia === abrangencia && v.valor !== null; })
            .map(function (v) { return v.periodo; });
        ps.sort();
        return ps.length ? ps[ps.length - 1] : null;
    }

    // ------------------------------------------------------------------
    // Indicadores do IBGE Cidades (Panorama)
    // ------------------------------------------------------------------

    /**
     * Busca indicadores do IBGE Cidades para o município e a UF (a API não
     * tem o Brasil). Localidade de 6 dígitos = município; de 2 = UF.
     *
     * @return {Promise<object>} { idIndicador: { municipio: {periodo: valorBruto}, estado: {...} } }
     *   Os valores vêm como texto ('12.3', '-', 'Cerrado'); converta com SXConectores.numero().
     */
    async function indicadores(ids, ctx, comUf) {
        var locais = String(ctx.codigoIbge).substr(0, 6) + (comUf === false ? '' : '|' + ctx.codigoUf);
        var url = BASE + '/v1/pesquisas/indicadores/' + ids.join('|') + '/resultados/' + locais;
        var json = await buscar(url, 'os indicadores do IBGE Cidades');
        if (!Array.isArray(json)) throw new Error('Resposta inesperada do IBGE Cidades');

        var out = {};
        json.forEach(function (ind) {
            var porAbr = {};
            (ind.res || []).forEach(function (r) {
                var abr = String(r.localidade).length === 2 ? 'estado' : 'municipio';
                porAbr[abr] = r.res || {};
            });
            out[String(ind.id)] = porAbr;
        });
        return out;
    }

    /** Série numérica de um indicador do IBGE Cidades, sem os marcadores de ausência. */
    function serieIndicador(dados, id) {
        var ind = dados[String(id)] || {};
        var out = {};
        Object.keys(ind).forEach(function (abr) {
            Object.keys(ind[abr]).forEach(function (p) {
                var v = SXConectores.numero(ind[abr][p]);
                if (v === null) return;
                out[abr] = out[abr] || {};
                out[abr][p] = v;
            });
        });
        return out;
    }

    /** Valor mais recente (período, valor bruto) de um indicador para a abrangência. */
    function maisRecente(dados, id, abrangencia) {
        var ind = (dados[String(id)] || {})[abrangencia || 'municipio'] || {};
        var ps = Object.keys(ind).filter(function (p) {
            var v = ind[p];
            return v !== null && v !== undefined && String(v).trim() !== '' && !/^(\.{2,3}|-|x|X)$/.test(String(v).trim());
        }).sort();
        if (!ps.length) return null;
        var p = ps[ps.length - 1];
        return { periodo: p, valor: ind[p] };
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    /** Arredonda para n casas, devolvendo número. */
    function arredondar(v, casas) {
        var f = Math.pow(10, casas);
        return Math.round(v * f) / f;
    }

    /** Nome em maiúsculas para "Nome Próprio" (partículas em minúsculas). */
    function nomeProprio(s) {
        var minusculas = ['da', 'de', 'do', 'das', 'dos', 'e'];
        return String(s || '').toLowerCase().split(/\s+/).filter(Boolean).map(function (p, i) {
            return (i > 0 && minusculas.indexOf(p) !== -1) ? p : p.charAt(0).toUpperCase() + p.slice(1);
        }).join(' ');
    }

    /** Formata número no padrão brasileiro (para textos). */
    function br(v, casas) {
        return Number(v).toLocaleString('pt-BR', { minimumFractionDigits: casas || 0, maximumFractionDigits: casas || 0 });
    }

    /**
     * Roda as partes de um conector (cada uma devolve linhas) e junta tudo.
     * Se alguma falhar, o conector falha com a lista do que não veio: dado
     * faltando sem aviso é pior que uma nova consulta.
     *
     * @param {object} partes { 'rótulo legível': async function () { return [linhas] } }
     */
    async function juntar(partes) {
        var nomes = Object.keys(partes);
        var resultados = await Promise.all(nomes.map(function (nome) {
            return Promise.resolve().then(partes[nome]).then(
                function (linhas) { return { ok: true, linhas: linhas || [] }; },
                function (e) { return { ok: false, nome: nome, erro: (e && e.message) || String(e) }; }
            );
        }));
        var falhas = resultados.filter(function (r) { return !r.ok; });
        if (falhas.length) {
            throw new Error(falhas.map(function (f) { return f.nome + ': ' + f.erro; }).join(' · ')
                + '. Clique em "Consultar de novo".');
        }
        return resultados.reduce(function (acc, r) { return acc.concat(r.linhas); }, []);
    }

    global.SXIBGE = {
        juntar: juntar,
        buscar: buscar,
        agregado: agregado,
        porAbrangencia: porAbrangencia,
        ultimoPeriodo: ultimoPeriodo,
        indicadores: indicadores,
        serieIndicador: serieIndicador,
        maisRecente: maisRecente,
        arredondar: arredondar,
        nomeProprio: nomeProprio,
        br: br,
        BASE: BASE
    };
})(window);
