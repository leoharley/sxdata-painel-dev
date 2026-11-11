/**
 * Desenho dos gráficos dos relatórios municipais (Chart.js).
 *
 * Recebe a estrutura montada no servidor por Relatorio_graficos (PHP) e a
 * converte em configuração do Chart.js. O mesmo arquivo é usado na revisão
 * interna, na prévia e no documento entregue ao cliente.
 *
 * Uso: SXGraficos.desenhar(canvas, dados, { estatico: true })
 *   estatico  sem animação (para impressão/PDF)
 */
(function (global) {
    'use strict';

    // Cores fixas por abrangência: o leitor associa a cor ao recorte em
    // todos os gráficos do relatório
    var CORES = {
        municipio: '#8fae5d',
        estado: '#3b82c4',
        brasil: '#23345F',
        homens: '#3b82c4',
        mulheres: '#d9688f',
        percentual: '#23345F'
    };
    var PALETA = ['#8fae5d', '#3b82c4', '#23345F', '#f0ad4e', '#5a7536', '#8e6cbf', '#2aa198', '#d9822b', '#7f8c8d', '#c0504d'];

    function numero(v, casas) {
        return Number(v).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas });
    }

    /** Mesmo formato de Relatorio_graficos::formatar (PHP). */
    function formatar(valor, formato, unidade, comUnidade) {
        if (valor === null || valor === undefined || valor === '') return '-';
        var v = Number(valor);
        var txt;
        switch (formato) {
            case 'inteiro': txt = numero(v, 0); break;
            case 'decimal1': txt = numero(v, 1); break;
            case 'percentual': return numero(v, 1) + '%';
            case 'moeda':
                var mil = /mil/i.test(unidade || '');
                return 'R$ ' + numero(v, mil ? 0 : 2) + (mil ? ' mil' : '');
            case 'indice': return numero(v, 3);
            default: txt = numero(v, 2);
        }
        return (comUnidade !== false && unidade && unidade !== '%') ? txt + ' ' + unidade : txt;
    }

    /** Rótulo curto para os eixos (1,2 mi; 350 mil). */
    function compacto(v, formato) {
        if (formato === 'percentual') return numero(v, 0) + '%';
        var a = Math.abs(v);
        if (a >= 1e9) return numero(v / 1e9, 1) + ' bi';
        if (a >= 1e6) return numero(v / 1e6, 1) + ' mi';
        if (a >= 1e4) return numero(v / 1e3, 0) + ' mil';
        if (formato === 'indice') return numero(v, 2);
        return numero(v, Number.isInteger(v) ? 0 : 1);
    }

    function cor(dataset, i) {
        return CORES[dataset.abrangencia] || PALETA[i % PALETA.length];
    }

    function desenhar(canvas, g, opcoes) {
        opcoes = opcoes || {};
        if (!canvas || !g || g.vazio || typeof Chart === 'undefined') return null;

        var tooltipValor = function (ctx) {
            var d = g.datasets[ctx.datasetIndex];
            var v = ctx.raw;
            if (g.modo === 'piramide') return ctx.dataset.label + ': ' + formatar(Math.abs(v), 'percentual');
            if (d && d.papel === 'percentual') return ctx.dataset.label + ': ' + formatar(v, 'percentual');
            if (g.modo === 'composicao') {
                var abs = d.absolutos ? ' (' + formatar(d.absolutos[ctx.dataIndex], 'moeda', g.unidade) + ')' : '';
                return ctx.label + ': ' + formatar(v, 'percentual') + abs;
            }
            return ctx.dataset.label + ': ' + formatar(v, g.formato, g.unidade);
        };

        var base = {
            responsive: true,
            maintainAspectRatio: false,
            animation: opcoes.estatico ? false : { duration: 400 },
            plugins: {
                legend: { display: g.datasets.length > 1 || g.tipo === 'rosca', position: 'bottom',
                          labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: { callbacks: { label: tooltipValor } }
            }
        };

        var config;

        if (g.tipo === 'rosca') {
            var d0 = g.datasets[0];
            config = {
                type: 'doughnut',
                data: {
                    labels: g.labels,
                    datasets: [{ data: d0.valores, backgroundColor: g.labels.map(function (_, i) { return PALETA[i % PALETA.length]; }), borderWidth: 1 }]
                },
                options: Object.assign(base, { cutout: '55%' })
            };
            config.options.plugins.legend.position = 'right';
        } else if (g.tipo === 'piramide') {
            config = {
                type: 'bar',
                data: {
                    labels: g.labels,
                    datasets: g.datasets.map(function (d, i) {
                        return { label: d.rotulo, data: d.valores, backgroundColor: cor(d, i), borderRadius: 2 };
                    })
                },
                options: Object.assign(base, {
                    indexAxis: 'y',
                    scales: {
                        x: { stacked: true, ticks: { callback: function (v) { return numero(Math.abs(v), 0) + '%'; } } },
                        y: { stacked: true, ticks: { font: { size: 10 } } }
                    }
                })
            };
        } else if (g.tipo === 'combinado') {
            config = {
                type: 'bar',
                data: {
                    labels: g.labels,
                    datasets: g.datasets.map(function (d, i) {
                        if (d.papel === 'percentual') {
                            return { type: 'line', label: d.rotulo, data: d.valores, yAxisID: 'y1',
                                     borderColor: CORES.percentual, backgroundColor: CORES.percentual, tension: 0.25, pointRadius: 3 };
                        }
                        return { label: d.rotulo, data: d.valores, backgroundColor: CORES.municipio, borderRadius: 3 };
                    })
                },
                options: Object.assign(base, {
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: function (v) { return compacto(v, g.formato); } } },
                        y1: { beginAtZero: true, max: 100, position: 'right', grid: { drawOnChartArea: false },
                              ticks: { callback: function (v) { return v + '%'; } } }
                    }
                })
            };
        } else {
            var horizontal = g.tipo === 'barras';
            var linha = g.tipo === 'linha';
            config = {
                type: linha ? 'line' : 'bar',
                data: {
                    labels: g.labels,
                    datasets: g.datasets.map(function (d, i) {
                        var c = g.datasets.length === 1 && !linha && g.modo === 'ranking' ? CORES.municipio : cor(d, i);
                        return {
                            label: d.rotulo,
                            data: d.valores,
                            borderColor: c,
                            backgroundColor: linha ? c : c,
                            borderRadius: linha ? 0 : 3,
                            tension: 0.25,
                            pointRadius: linha ? 3 : 0,
                            spanGaps: true,
                            fill: false
                        };
                    })
                },
                options: Object.assign(base, {
                    indexAxis: horizontal ? 'y' : 'x',
                    scales: (function () {
                        var valor = {
                            beginAtZero: g.formato !== 'indice' || !linha,
                            ticks: { callback: function (v) { return compacto(v, g.formato); } }
                        };
                        var categoria = { ticks: { autoSkip: false, maxRotation: 45, font: { size: 10 } } };
                        return horizontal ? { x: valor, y: categoria } : { x: categoria, y: valor };
                    })()
                })
            };
        }

        if (canvas._sxChart) canvas._sxChart.destroy();
        canvas._sxChart = new Chart(canvas, config);
        return canvas._sxChart;
    }

    /** Ano de um rótulo de período ("2022", "2017/2018") ou null. */
    function ano(rotulo) {
        var m = String(rotulo).match(/\d{4}/);
        return m ? parseInt(m[0], 10) : null;
    }

    /**
     * Cópia do gráfico com o filtro de exibição aplicado. Não altera o que
     * foi gravado: serve só para a tela.
     *   comparar  abrangências mostradas junto do município (['estado', 'brasil'])
     *   de, ate   anos do período (só nas séries históricas, eixo X = períodos)
     */
    function filtrar(g, filtro) {
        filtro = filtro || {};
        var r = JSON.parse(JSON.stringify(g));
        if (r.vazio) return r;

        if (filtro.comparar) {
            var temMunicipio = r.datasets.some(function (d) { return d.abrangencia === 'municipio'; });
            if (temMunicipio) {
                r.datasets = r.datasets.filter(function (d) {
                    return (d.abrangencia !== 'estado' && d.abrangencia !== 'brasil') || filtro.comparar.indexOf(d.abrangencia) !== -1;
                });
            }
        }

        if (r.modo === 'serie' && (filtro.de || filtro.ate)) {
            var manter = [];
            r.labels.forEach(function (l, i) {
                var a = ano(l);
                if (a === null || ((!filtro.de || a >= filtro.de) && (!filtro.ate || a <= filtro.ate))) manter.push(i);
            });
            r.labels = manter.map(function (i) { return r.labels[i]; });
            r.datasets.forEach(function (d) { d.valores = manter.map(function (i) { return d.valores[i]; }); });
            // Série sem nenhum valor no período sai do gráfico
            r.datasets = r.datasets.filter(function (d) { return d.valores.some(function (v) { return v !== null; }); });
            r.periodo = r.labels.length > 1 ? r.labels[0] + ' a ' + r.labels[r.labels.length - 1] : (r.labels[0] || '');
        }

        r.vazio = !r.labels.length || !r.datasets.length;
        return r;
    }

    /** Anos das séries históricas de uma lista de gráficos, em ordem. */
    function anosDasSeries(graficos) {
        var anos = {};
        graficos.forEach(function (g) {
            if (g && !g.vazio && g.modo === 'serie') {
                g.labels.forEach(function (l) { var a = ano(l); if (a !== null) anos[a] = true; });
            }
        });
        return Object.keys(anos).map(Number).sort(function (a, b) { return a - b; });
    }

    /** Desenha todos os <canvas data-grafico='{json}'> da página. */
    function desenharTodos(raiz, opcoes) {
        (raiz || document).querySelectorAll('canvas[data-grafico]').forEach(function (canvas) {
            try {
                desenhar(canvas, JSON.parse(canvas.dataset.grafico), opcoes);
            } catch (e) {
                console.error('Gráfico não desenhado', e);
            }
        });
    }

    global.SXGraficos = { desenhar: desenhar, desenharTodos: desenharTodos, formatar: formatar,
                          filtrar: filtrar, anosDasSeries: anosDasSeries };
})(window);
