<?php
/**
 * Mapa integrado (docs/PLANO_MAPA_INTEGRADO.md).
 *
 * @var bool     $migrado    sql/field_points_migration.sql já rodou
 * @var object[] $projetos
 * @var array    $temas      Mapa_temas::temas
 * @var bool     $com_lista  ibge_municipios carregada
 * @var string[] $conectores conectores JS (consulta direta quando não há relatório)
 * @var array    $csrf
 */
?>
<style>
    .mi-card { background: #fff; border: 1px solid #e6e8eb; border-radius: 10px; padding: 1rem; margin-bottom: 1rem; }
    #mi-mapa { height: calc(100vh - 170px); min-height: 480px; border-radius: 10px; border: 1px solid #e6e8eb; }
    .mi-busca { position: relative; }
    .mi-sugestoes { position: absolute; z-index: 1100; left: 0; right: 0; background: #fff; border: 1px solid #dee2e6; border-radius: 6px; max-height: 260px; overflow-y: auto; display: none; }
    .mi-sugestoes button { display: block; width: 100%; text-align: left; border: 0; background: none; padding: .45rem .7rem; font-size: .9rem; }
    .mi-sugestoes button:hover { background: #f1f5f9; }
    .mi-chip { display: inline-flex; align-items: center; gap: .35rem; background: #eef2f7; border: 1px solid #d6dde6; border-radius: 999px; padding: .15rem .3rem .15rem .65rem; margin: 0 .3rem .3rem 0; font-size: .85rem; cursor: pointer; }
    .mi-chip.ativo { background: #8fae5d; border-color: #7a9a4c; color: #fff; }
    .mi-chip button { border: 0; background: none; color: inherit; padding: 0 .3rem; line-height: 1; }
    .mi-ind { padding: .55rem 0; border-bottom: 1px solid #f0f1f3; }
    .mi-ind:last-child { border-bottom: 0; }
    .mi-ind .valor { font-weight: 600; font-size: 1.05rem; }
    .mi-ind .meta { font-size: .75rem; color: #6c757d; }
    .mi-coleta { border: 1px solid #eef0f2; border-radius: 8px; padding: .6rem .7rem; margin-bottom: .6rem; }
    .mi-coleta img { width: 72px; height: 72px; object-fit: cover; border-radius: 6px; margin: .25rem .25rem 0 0; }
    .mi-coleta dl { margin: .4rem 0 0; font-size: .82rem; }
    .mi-coleta dt { font-weight: 600; color: #495057; }
    .mi-coleta dd { margin: 0 0 .25rem; }
    #mi-painel { max-height: calc(100vh - 420px); min-height: 200px; overflow-y: auto; }
    .mi-legenda span { display: inline-flex; align-items: center; gap: .3rem; margin-right: .7rem; font-size: .8rem; }
    .mi-legenda i { width: 11px; height: 11px; border-radius: 50%; display: inline-block; }
</style>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h2 class="mb-1">Mapa integrado</h2>
        <div class="text-muted">Contorno do município, indicadores por tema e pontos de campo do projeto com as coletas.</div>
    </div>
</div>

<?php if (!$migrado): ?>
    <div class="alert alert-warning">
        <i class="fas fa-database me-1"></i>
        Os pontos de campo ainda não estão disponíveis neste servidor: rode <code>sql/field_points_migration.sql</code>.
        O contorno e os indicadores funcionam assim mesmo.
    </div>
<?php endif; ?>
<?php if (!$com_lista): ?>
    <div class="alert alert-warning">
        <i class="fas fa-list me-1"></i> A lista de municípios está vazia: rode <code>sql/ibge_municipios_seed.sql</code>.
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="mi-card">
            <label class="form-label small fw-semibold mb-1" for="mi-projeto">Projeto</label>
            <select class="form-select form-select-sm mb-3" id="mi-projeto">
                <option value="">Selecione para ver os pontos</option>
                <?php foreach ($projetos as $p): ?>
                    <option value="<?= (int) $p->id ?>"><?= html_escape($p->name) ?><?= in_array($p->status, ['completed', 'cancelled'], true) ? ' (encerrado)' : '' ?></option>
                <?php endforeach; ?>
            </select>

            <label class="form-label small fw-semibold mb-1" for="mi-busca">Municípios</label>
            <div class="mi-busca mb-2">
                <input type="text" class="form-control form-control-sm" id="mi-busca" placeholder="Digite o nome do município" autocomplete="off" <?= $com_lista ? '' : 'disabled' ?>>
                <div class="mi-sugestoes" id="mi-sugestoes"></div>
            </div>
            <div id="mi-chips" class="mb-2"></div>

            <label class="form-label small fw-semibold mb-1" for="mi-tema">Tema dos indicadores</label>
            <select class="form-select form-select-sm mb-3" id="mi-tema">
                <?php foreach ($temas as $chave => $t): ?>
                    <option value="<?= html_escape($chave) ?>"><?= html_escape($t['titulo']) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="d-flex justify-content-between align-items-center">
                <div class="mi-legenda">
                    <span><i style="background:#2f80ed"></i>Comunidade</span>
                    <span><i style="background:#8fae5d"></i>Localidade</span>
                    <span><i style="background:#f2994a"></i>Equipamento</span>
                    <span><i style="background:#9b51e0"></i>Outro</span>
                </div>
                <a href="#" class="btn btn-sm btn-outline-secondary disabled" id="mi-kmz" title="Pontos do filtro e coletas vinculadas">
                    <i class="fas fa-download me-1"></i>KMZ
                </a>
            </div>
        </div>

        <div class="mi-card">
            <div id="mi-painel">
                <div class="text-muted small">Escolha um município para ver o contorno e os indicadores.</div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div id="mi-mapa"></div>
    </div>
</div>

<script src="<?= base_url('assets/js/relatorios/conectores.js') ?>"></script>
<?php foreach ($conectores as $arquivo): ?>
<script src="<?= base_url('assets/js/relatorios/conectores/' . rawurlencode($arquivo)) ?>"></script>
<?php endforeach; ?>
<script>
// Leaflet é carregado no rodapé: espera a página inteira
window.addEventListener('load', function () {
    var URL = {
        municipios: <?= json_encode(base_url('relatorios-automatizados/municipios')) ?>,
        malha: <?= json_encode(base_url('mapa-integrado/malha/')) ?>,
        indicadores: <?= json_encode(base_url('mapa-integrado/indicadores/')) ?>,
        resumir: <?= json_encode(base_url('mapa-integrado/resumir')) ?>,
        pontos: <?= json_encode(base_url('mapa-integrado/pontos/')) ?>,
        ponto: <?= json_encode(base_url('mapa-integrado/ponto/')) ?>,
        kmz: <?= json_encode(base_url('mapa-integrado/kmz')) ?>
    };
    var MIGRADO = <?= $migrado ? 'true' : 'false' ?>;
    var csrf = <?= json_encode($csrf) ?>;
    var CORES = { comunidade: '#2f80ed', localidade: '#8fae5d', equipamento: '#f2994a', outro: '#9b51e0' };

    var elProjeto = document.getElementById('mi-projeto');
    var elTema = document.getElementById('mi-tema');
    var elBusca = document.getElementById('mi-busca');
    var elSug = document.getElementById('mi-sugestoes');
    var elChips = document.getElementById('mi-chips');
    var elPainel = document.getElementById('mi-painel');
    var elKmz = document.getElementById('mi-kmz');

    var mapa = L.map('mi-mapa').setView([-14.5, -51.5], 4);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap'
    }).addTo(mapa);

    // codigo => { codigo, nome, uf, contorno: L.Layer, pontos: L.LayerGroup }
    var municipios = {};
    var ordem = [];
    var ativo = null;
    var consultaIndicadores = 0;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function getJSON(url) {
        return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                return r.json().catch(function () { throw new Error('Resposta inválida do servidor (HTTP ' + r.status + ')'); })
                    .then(function (j) { if (j && j.csrf) csrf.valor = j.csrf; if (!r.ok || j.ok === false) throw new Error(j.mensagem || ('HTTP ' + r.status)); return j; });
            });
    }
    function postar(url, campos) {
        var fd = new FormData();
        Object.keys(campos).forEach(function (k) { fd.append(k, campos[k]); });
        if (csrf.nome) fd.append(csrf.nome, csrf.valor);
        return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().then(function (j) { if (j && j.csrf) csrf.valor = j.csrf; if (!j.ok) throw new Error(j.mensagem || 'Falha'); return j; }); });
    }

    // ---------------- Busca de municípios ----------------
    var timer = null;
    elBusca.addEventListener('input', function () {
        clearTimeout(timer);
        var termo = elBusca.value.trim();
        if (termo.length < 2) { elSug.style.display = 'none'; return; }
        timer = setTimeout(function () {
            getJSON(URL.municipios + '?q=' + encodeURIComponent(termo)).then(function (j) {
                elSug.innerHTML = '';
                (j.municipios || []).forEach(function (m) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = m.nome + ' - ' + m.uf;
                    b.addEventListener('click', function () {
                        elSug.style.display = 'none';
                        elBusca.value = '';
                        adicionar({ codigo: parseInt(m.codigo, 10), nome: m.nome, uf: m.uf });
                    });
                    elSug.appendChild(b);
                });
                elSug.style.display = elSug.children.length ? 'block' : 'none';
            }).catch(function () { elSug.style.display = 'none'; });
        }, 250);
    });
    document.addEventListener('click', function (e) { if (e.target !== elBusca) elSug.style.display = 'none'; });

    function chips() {
        elChips.innerHTML = '';
        ordem.forEach(function (c) {
            var m = municipios[c];
            var el = document.createElement('span');
            el.className = 'mi-chip' + (c === ativo ? ' ativo' : '');
            el.innerHTML = esc(m.nome + ' - ' + m.uf) + '<button type="button" title="Remover">&times;</button>';
            el.addEventListener('click', function () { ativar(c, true); });
            el.querySelector('button').addEventListener('click', function (ev) { ev.stopPropagation(); remover(c); });
            elChips.appendChild(el);
        });
        atualizarKmz();
    }

    function adicionar(m) {
        if (municipios[m.codigo]) { ativar(m.codigo, true); return; }
        m.pontos = L.layerGroup().addTo(mapa);
        municipios[m.codigo] = m;
        ordem.push(m.codigo);
        getJSON(URL.malha + m.codigo).then(function (geo) {
            if (!municipios[m.codigo]) return;
            m.contorno = L.geoJSON(geo, { style: estilo(m.codigo === ativo) }).addTo(mapa);
            m.contorno.on('click', function () { ativar(m.codigo, false); });
            m.contorno.bindTooltip(m.nome + ' - ' + m.uf, { sticky: true });
            enquadrar();
        }).catch(function (e) {
            elPainel.insertAdjacentHTML('afterbegin', '<div class="alert alert-warning small py-2">Contorno de ' + esc(m.nome) + ': ' + esc(e.message) + '</div>');
        });
        carregarPontos(m);
        ativar(m.codigo, false);
    }

    function remover(c) {
        var m = municipios[c];
        if (m.contorno) mapa.removeLayer(m.contorno);
        mapa.removeLayer(m.pontos);
        delete municipios[c];
        ordem = ordem.filter(function (x) { return x !== c; });
        if (ativo === c) {
            ativo = null;
            if (ordem.length) ativar(ordem[ordem.length - 1], false);
            else elPainel.innerHTML = '<div class="text-muted small">Escolha um município para ver o contorno e os indicadores.</div>';
        }
        chips();
        enquadrar();
    }

    function estilo(destaque) {
        return { color: destaque ? '#e8590c' : '#495057', weight: destaque ? 3 : 2, fillColor: destaque ? '#ffd8a8' : '#adb5bd', fillOpacity: destaque ? 0.18 : 0.08 };
    }

    function ativar(c, enquadrarNele) {
        ativo = c;
        ordem.forEach(function (x) {
            var m = municipios[x];
            if (m.contorno) m.contorno.setStyle(estilo(x === c));
        });
        chips();
        carregarIndicadores();
        if (enquadrarNele && municipios[c].contorno) mapa.fitBounds(municipios[c].contorno.getBounds(), { padding: [20, 20] });
    }

    function enquadrar() {
        var b = null;
        ordem.forEach(function (c) {
            var m = municipios[c];
            if (!m.contorno) return;
            b = b ? b.extend(m.contorno.getBounds()) : L.latLngBounds(m.contorno.getBounds());
        });
        if (b) mapa.fitBounds(b, { padding: [20, 20] });
    }

    // ---------------- Pontos ----------------
    function carregarPontos(m) {
        m.pontos.clearLayers();
        m.semCoordenada = [];
        if (!MIGRADO || !elProjeto.value) return;
        getJSON(URL.pontos + m.codigo + '?projeto=' + encodeURIComponent(elProjeto.value)).then(function (j) {
            if (!municipios[m.codigo]) return;
            m.semCoordenada = j.sem_coordenada || [];
            (j.features || []).forEach(function (f) {
                var p = f.properties;
                var mk = L.circleMarker([f.geometry.coordinates[1], f.geometry.coordinates[0]], {
                    radius: 8, weight: p.dentro_municipio === false ? 3 : 2,
                    color: p.dentro_municipio === false ? '#c92a2a' : '#fff', dashArray: p.dentro_municipio === false ? '3 3' : null,
                    fillColor: CORES[p.tipo] || CORES.outro, fillOpacity: 0.95
                });
                mk.bindTooltip(esc(p.nome) + ' · ' + p.coletas + ' coleta(s)');
                mk.on('click', function () { abrirPonto(p.id); });
                mk.addTo(m.pontos);
            });
            if (m.codigo === ativo && painelModo === 'indicadores') desenharSemCoordenada();
        }).catch(function (e) {
            elPainel.insertAdjacentHTML('afterbegin', '<div class="alert alert-warning small py-2">Pontos: ' + esc(e.message) + '</div>');
        });
    }

    elProjeto.addEventListener('change', function () {
        ordem.forEach(function (c) { carregarPontos(municipios[c]); });
        atualizarKmz();
        if (painelModo === 'ponto') carregarIndicadores();
    });

    function atualizarKmz() {
        var ok = MIGRADO && elProjeto.value && ordem.length;
        elKmz.classList.toggle('disabled', !ok);
        elKmz.href = ok ? URL.kmz + '?projeto=' + encodeURIComponent(elProjeto.value) + '&municipios=' + ordem.join(',') : '#';
    }

    // ---------------- Painel lateral ----------------
    var painelModo = 'indicadores';

    function carregarIndicadores() {
        if (!ativo) return;
        painelModo = 'indicadores';
        var m = municipios[ativo];
        var tema = elTema.value;
        var temaTitulo = elTema.options[elTema.selectedIndex].text;
        var n = ++consultaIndicadores;
        elPainel.innerHTML = '<h6 class="mb-1">' + esc(m.nome + ' - ' + m.uf) + '</h6><div class="small text-muted mb-2">' + esc(temaTitulo) + '</div>'
            + '<div id="mi-origem" class="small mb-2"></div><div id="mi-lista"><span class="spinner-border spinner-border-sm text-primary"></span> Carregando...</div><div id="mi-semcoord"></div>';

        getJSON(URL.indicadores + m.codigo + '?tema=' + encodeURIComponent(tema)).then(function (j) {
            if (n !== consultaIndicadores) return;
            if (j.origem === 'relatorio') {
                var r = j.relatorio;
                document.getElementById('mi-origem').innerHTML = r.revisado
                    ? '<span class="badge bg-success">Relatório revisado</span> <a href="' + esc(r.url) + '">relatório ' + esc(r.periodo) + '</a>'
                    : '<span class="badge bg-warning text-dark">Não revisado</span> <a href="' + esc(r.url) + '">relatório ' + esc(r.periodo) + '</a> ainda em revisão';
                lista(j.itens);
            } else {
                document.getElementById('mi-origem').innerHTML = '<span class="badge bg-info text-dark">Consulta direta à fonte</span> sem relatório deste município; nada é gravado.';
                consultarFonte(j.contexto, tema, n);
            }
            desenharSemCoordenada();
        }).catch(function (e) {
            if (n !== consultaIndicadores) return;
            document.getElementById('mi-lista').innerHTML = '<div class="text-danger small">' + esc(e.message) + '</div>';
        });
    }
    elTema.addEventListener('change', carregarIndicadores);

    async function consultarFonte(ctx, tema, n) {
        var defs = (window.SXConectores ? SXConectores.lista() : []).filter(function (d) {
            return d.eixos.some(function (e) { return ctx.eixos.indexOf(e) !== -1; });
        });
        if (!defs.length) { lista([]); return; }
        var linhas = [], falhas = [];
        await Promise.all(defs.map(function (d) {
            return Promise.resolve().then(function () { return d.executar(ctx); })
                .then(function (l) { if (Array.isArray(l)) linhas = linhas.concat(l); })
                .catch(function (e) { falhas.push(d.rotulo + ': ' + (e && e.message ? e.message : e)); });
        }));
        if (n !== consultaIndicadores) return;
        try {
            var j = await postar(URL.resumir, { tema: tema, linhas: JSON.stringify(linhas) });
            if (n !== consultaIndicadores) return;
            lista(j.itens);
        } catch (e) {
            document.getElementById('mi-lista').innerHTML = '<div class="text-danger small">' + esc(e.message) + '</div>';
        }
        if (falhas.length) {
            document.getElementById('mi-lista').insertAdjacentHTML('beforeend', '<div class="small text-warning mt-2">Algumas fontes falharam: ' + esc(falhas.join(' | ')) + '</div>');
        }
    }

    function lista(itens) {
        var el = document.getElementById('mi-lista');
        if (!el) return;
        if (!itens || !itens.length) { el.innerHTML = '<div class="text-muted small">Nenhum indicador deste tema para o município.</div>'; return; }
        el.innerHTML = itens.map(function (i) {
            var valor;
            if (i.tipo === 'texto') valor = '<div class="small" style="white-space:pre-line">' + esc(i.valor_texto) + '</div>';
            else if (i.categorias && i.categorias.length) valor = '<ul class="small mb-0 ps-3">' + i.categorias.map(function (c) {
                return '<li>' + esc(c.categoria.replace('|', ' · ')) + ': <strong>' + esc(c.valor_formatado) + '</strong></li>'; }).join('') + '</ul>';
            else valor = '<div class="valor">' + esc(i.valor_formatado) + '</div>';
            var meta = [i.periodo, i.fonte].filter(Boolean).map(esc).join(' · ');
            return '<div class="mi-ind"><div class="small text-muted">' + esc(i.rotulo) + '</div>' + valor + (meta ? '<div class="meta">' + meta + '</div>' : '') + '</div>';
        }).join('');
    }

    function desenharSemCoordenada() {
        var el = document.getElementById('mi-semcoord');
        var m = municipios[ativo];
        if (!el || !m || !m.semCoordenada || !m.semCoordenada.length) return;
        el.innerHTML = '<div class="small fw-semibold mt-3 mb-1">Pontos sem coordenada</div>' + m.semCoordenada.map(function (p) {
            return '<a href="#" class="d-block small" data-ponto="' + p.id + '">' + esc(p.nome) + ' (' + p.coletas + ')</a>';
        }).join('');
        el.querySelectorAll('[data-ponto]').forEach(function (a) {
            a.addEventListener('click', function (ev) { ev.preventDefault(); abrirPonto(parseInt(a.dataset.ponto, 10)); });
        });
    }

    function abrirPonto(id) {
        painelModo = 'ponto';
        consultaIndicadores++;
        elPainel.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span> Carregando ponto...';
        getJSON(URL.ponto + id).then(function (j) {
            var p = j.ponto;
            var h = '<button type="button" class="btn btn-sm btn-link px-0 mb-2" id="mi-voltar"><i class="fas fa-arrow-left me-1"></i>Indicadores</button>'
                + '<h6 class="mb-1">' + esc(p.nome) + '</h6>'
                + '<div class="small text-muted mb-2">' + esc(p.tipo) + ' · ' + esc((p.municipio_nome || '') + ' - ' + (p.uf || '')) + ' · ' + esc(p.projeto_nome || '') + '</div>';
            if (p.dentro_municipio === false) h += '<div class="alert alert-warning small py-1 px-2">A coordenada fica fora do município informado.</div>';
            if (p.criado_por_nome) h += '<div class="small text-muted mb-2">Registrado por ' + esc(p.criado_por_nome) + '</div>';
            h += '<div class="small fw-semibold mb-2">' + j.total_coletas + ' coleta(s) vinculada(s)' + (j.total_coletas > j.coletas.length ? ' · mostrando as ' + j.coletas.length + ' mais recentes' : '') + '</div>';
            h += j.coletas.map(function (c) {
                return '<div class="mi-coleta">'
                    + '<div class="d-flex justify-content-between"><strong class="small">' + esc(c.questionario || 'Coleta') + '</strong><a class="small" href="' + esc(c.url) + '" target="_blank">abrir registro</a></div>'
                    + '<div class="small text-muted">' + esc(c.data) + (c.coletor ? ' · ' + esc(c.coletor) : '') + (c.respondente ? ' · ' + esc(c.respondente) : '') + '</div>'
                    + (c.fotos.length ? '<div>' + c.fotos.map(function (f) { return '<a href="' + esc(f) + '" target="_blank"><img src="' + esc(f) + '" alt="foto"></a>'; }).join('') + '</div>' : '')
                    + (c.respostas.length ? '<dl>' + c.respostas.map(function (r) { return '<dt>' + esc(r.pergunta) + '</dt><dd>' + esc(r.resposta) + '</dd>'; }).join('') + '</dl>' : '')
                    + '</div>';
            }).join('');
            elPainel.innerHTML = h;
            document.getElementById('mi-voltar').addEventListener('click', carregarIndicadores);
        }).catch(function (e) {
            elPainel.innerHTML = '<div class="text-danger small">' + esc(e.message) + '</div>';
        });
    }
});
</script>
