<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<style>
    .pq { padding: 10px 12px; border-left: 3px solid transparent; border-radius: 4px; }
    .pq.tem-erro { border-left-color: #dc3545; background: #fff5f5; }
    .pq .titulo { font-weight: 600; }
    .pq .ajuda { font-size: 12px; color: #6c757d; }
    .pq .erro { font-size: 12px; color: #dc3545; }
    .pq .alerta { font-size: 12px; color: #b26a00; }
    .pq.calc .valor { font-size: 1.1rem; font-weight: 600; }
    .bloco-titulo { background: #1e3a5f; color: #fff; padding: 6px 10px; border-radius: 4px; margin: 18px 0 8px; font-weight: 600; }
    .secao-titulo { color: #1e3a5f; border-bottom: 1px solid #dee2e6; padding-bottom: 2px; margin: 12px 0 6px; font-weight: 600; }
    .resumo { position: sticky; top: 70px; }
</style>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="<?= base_url('formularios-estruturados/editar/' . $q->id) ?>" class="small"><i class="fas fa-arrow-left"></i> Editor</a>
            <h1 class="h3 mb-0">Prévia: <?= html_escape($definicao['titulo'] ?: $q->title) ?></h1>
            <p class="text-muted mb-0 small">Simulação com o rascunho atual. Nada é gravado. As regras são conferidas pelo mesmo motor que valida os envios do app.</p>
        </div>
        <button class="btn btn-outline-secondary" onclick="location.reload()"><i class="fas fa-undo me-1"></i> Recomeçar</button>
    </div>
    <div class="row g-3">
        <div class="col-lg-8" id="form"></div>
        <div class="col-lg-4">
            <div class="card resumo">
                <div class="card-header fw-semibold">Conferência</div>
                <div class="card-body small" id="resumo">Responda para ver erros, alertas e cálculos.</div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const DEF = <?= json_encode($definicao, JSON_UNESCAPED_UNICODE) ?>;
    const URL = <?= json_encode(base_url('formularios-estruturados/avaliar/' . $q->id)) ?>;
    const dom = {};             // respostas do domicílio: codigo -> {valor, nsnr, complementos, complemento}
    let moradores = [];         // [{client_id, nome, resp: {}}]
    const grupos = {};          // grupo -> [{client_id, resp: {}}]
    const ENTREVISTADOS = <?= json_encode($entrevistados ?? [], JSON_UNESCAPED_UNICODE) ?>;
    let entrevistado = null;    // {id, contexto}
    let ultimo = null, timer = null, seq = 0;
    const form = document.getElementById('form');
    const hoje = new Date();
    const pad = n => String(n).padStart(2, '0');
    const dataHoje = hoje.getFullYear() + '-' + pad(hoje.getMonth() + 1) + '-' + pad(hoje.getDate());
    const horaAgora = () => { const d = new Date(); return pad(d.getHours()) + ':' + pad(d.getMinutes()); };
    const esc = s => { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'm' + Date.now() + Math.random());

    function instancia(escopo) {
        for (const g in grupos) { const i = grupos[g].find(x => x.client_id === escopo); if (i) return i; }
        return null;
    }
    function grupoDe(escopo) { for (const g in grupos) if (grupos[g].some(x => x.client_id === escopo)) return g; return null; }
    function store(escopo) {
        if (escopo === 'd') return dom;
        const m = moradores.find(x => x.client_id === escopo);
        return m ? m.resp : (instancia(escopo) || {}).resp;
    }
    function nomeDe(escopo) {
        const m = moradores.find(x => x.client_id === escopo);
        if (m) return m.nome || 'o(a) morador(a)';
        const g = grupoDe(escopo);
        return g ? (grupos[g].findIndex(x => x.client_id === escopo) + 1) + 'º item' : '';
    }

    // ---------------------------------------------------------- render
    function campo(p, escopo) {
        const id = 'q_' + escopo + '_' + p.codigo;
        const r = store(escopo)[p.codigo] || {};
        const v = r.valor;
        let h = '';
        const dis = r.nsnr ? 'disabled' : '';
        switch (p.tipo) {
            case 'unica':
            case 'multipla': {
                const tipo = p.tipo === 'unica' ? 'radio' : 'checkbox';
                (p.opcoes || []).forEach(o => {
                    const marc = p.tipo === 'unica' ? v === o.valor : Array.isArray(v) && v.includes(o.valor);
                    h += '<div class="form-check opc" data-valor="' + esc(o.valor) + '"><input class="form-check-input" type="' + tipo + '" name="' + id
                       + '" id="' + id + '_' + o.valor + '" value="' + esc(o.valor) + '" ' + (marc ? 'checked' : '') + ' data-acao="opcao" data-excl="' + (o.exclusiva ? 1 : 0) + '">'
                       + '<label class="form-check-label" for="' + id + '_' + o.valor + '">' + esc(o.texto) + '</label>';
                    if (o.complemento) {
                        const c = (r.complementos || {})[o.valor] || '';
                        h += ' <input class="form-control form-control-sm d-inline-block ms-2 comp" style="width:auto;' + (marc ? '' : 'display:none!important')
                           + '" data-acao="complemento" data-opcao="' + esc(o.valor) + '" type="' + (o.complemento.tipo === 'inteiro' ? 'number' : 'text')
                           + '" placeholder="' + esc(o.complemento.rotulo) + '" value="' + esc(c) + '">';
                    }
                    h += '</div>';
                });
                break;
            }
            case 'texto_longo': h = '<textarea class="form-control" rows="3" data-acao="valor" ' + dis + '>' + esc(v || '') + '</textarea>'; break;
            case 'inteiro': case 'decimal': case 'moeda':
                h = '<div class="input-group" style="max-width:260px">' + (p.tipo === 'moeda' ? '<span class="input-group-text">R$</span>' : '')
                  + '<input type="number" class="form-control" step="' + (p.tipo === 'inteiro' ? '1' : '0.01') + '" min="0" data-acao="valor" value="' + esc(v ?? '') + '" ' + dis + '></div>';
                break;
            case 'data': h = '<input type="date" class="form-control" style="max-width:200px" data-acao="valor" value="' + esc(v || '') + '" ' + dis + '>'; break;
            case 'hora':
                h = '<div class="input-group" style="max-width:220px"><input type="time" class="form-control" data-acao="valor" value="' + esc(v || '') + '" ' + dis + '>'
                  + (p.auto === 'hora_fim' ? '<button class="btn btn-outline-secondary" data-acao="agora" type="button">Agora</button>' : '') + '</div>';
                break;
            case 'composto':
                (p.campos || []).forEach(c => {
                    h += '<input type="number" min="0" class="form-control d-inline-block me-2" style="width:110px" data-acao="campo" data-campo="' + esc(c.codigo)
                       + '" value="' + esc((v || {})[c.codigo] ?? '') + '"> <span class="me-3">' + esc(c.rotulo) + '</span>';
                });
                break;
            case 'lista':
                for (let i = 0; i < (p.itens || 3); i++) {
                    h += '<input type="text" class="form-control mb-1" data-acao="item" data-i="' + i + '" placeholder="' + (i + 1) + '." value="' + esc((v || [])[i] || '') + '">';
                }
                break;
            case 'coordenadas':
                h = '<div class="d-flex gap-2" style="max-width:420px"><input type="number" step="any" class="form-control" placeholder="Latitude" data-acao="lat" value="' + esc((v || {}).lat ?? '')
                  + '"><input type="number" step="any" class="form-control" placeholder="Longitude" data-acao="lng" value="' + esc((v || {}).lng ?? '')
                  + '"><button class="btn btn-outline-secondary text-nowrap" type="button" data-acao="gps">Minha localização</button></div>';
                break;
            case 'foto': h = '<input type="file" accept="image/*" class="form-control" style="max-width:360px" data-acao="foto"><div class="small text-muted">' + esc(v || '') + '</div>'; break;
            case 'calculado': h = '<div class="valor">—</div>'; break;
            case 'mensagem': h = ''; break;
            default:
                h = '<input type="' + (p.tipo === 'telefone' ? 'tel' : 'text') + '" class="form-control" data-acao="valor" value="' + esc(v || '') + '" ' + dis + '>';
        }
        if (p.complemento) {
            h += '<input type="text" class="form-control form-control-sm mt-1" data-acao="complemento_q" placeholder="' + esc(p.complemento.rotulo) + '" value="' + esc(r.complemento || '') + '">';
        }
        if (p.nsnr) {
            h += '<div class="form-check mt-1"><input class="form-check-input" type="checkbox" data-acao="nsnr" id="' + id + '_nsnr" ' + (r.nsnr ? 'checked' : '') + '>'
               + '<label class="form-check-label small" for="' + id + '_nsnr">NS/NR</label></div>';
        }
        const texto = (p.texto || '').replace(/\[NOME\]/g, escopo === 'd' ? '[NOME]' : nomeDe(escopo));
        return '<div class="pq' + (p.tipo === 'calculado' ? ' calc' : '') + '" data-cod="' + p.codigo + '" data-escopo="' + escopo + '">'
             + '<div class="titulo">' + (p.numero ? esc(p.numero) + '. ' : '') + esc(texto) + (p.tipo !== 'calculado' && p.obrigatoria === false ? ' <span class="text-muted fw-normal small">(opcional)</span>' : '') + '</div>'
             + (p.ajuda ? '<div class="ajuda mb-1">' + esc(p.ajuda) + '</div>' : '') + h + '<div class="alerta"></div><div class="erro"></div></div>';
    }

    function perguntas(lista, escopo) {
        let h = '', bloco = null, secao = null;
        lista.forEach(p => {
            if (p.oculta) return;
            if (p.bloco && p.bloco !== bloco) { bloco = p.bloco; secao = null; h += '<div class="bloco-titulo">' + esc(bloco) + '</div>'; }
            if (p.secao && p.secao !== secao) { secao = p.secao; h += '<div class="secao-titulo">' + esc(secao) + '</div>'; }
            h += campo(p, escopo);
        });
        return h;
    }

    function render() {
        let h = '';
        DEF.etapas.forEach((e, i) => {
            if (e.tipo === 'moradores') {
                h += '<div class="card mb-3"><div class="card-header fw-semibold">' + (e.numero ? esc(e.numero) + '. ' : '') + esc(e.texto || 'Moradores') + '</div><div class="card-body">'
                   + (e.ajuda ? '<div class="ajuda small text-muted mb-2">' + esc(e.ajuda) + '</div>' : '');
                moradores.forEach((m, k) => {
                    h += '<div class="input-group mb-1"><span class="input-group-text">' + pad(k + 1) + (k === 0 ? ' - Respondente' : '') + '</span>'
                       + '<input class="form-control" data-mor-nome="' + m.client_id + '" value="' + esc(m.nome) + '" placeholder="Nome completo">'
                       + '<button class="btn btn-outline-danger" type="button" data-mor-del="' + m.client_id + '" title="Remover">×</button></div>';
                });
                h += '<button class="btn btn-sm btn-outline-primary mt-1" type="button" id="mor-add"><i class="fas fa-user-plus me-1"></i> Adicionar morador</button>'
                   + (e.respondente ? ' <button class="btn btn-sm btn-outline-secondary mt-1" type="button" id="mor-resp">Usar o nome do respondente (' + esc(e.respondente) + ')</button>' : '')
                   + '<div class="erro small text-danger mt-2" id="erro-moradores"></div><div class="alerta small mt-1" style="color:#b26a00" id="alerta-moradores"></div></div></div>';
                return;
            }
            h += '<div class="card mb-3"><div class="card-header fw-semibold">' + esc(e.titulo || (e.nivel === 'morador' ? 'Por morador' : 'Domicílio')) + '</div><div class="card-body">';
            if (e.nivel === 'grupo') {
                const lista = grupos[e.grupo] = grupos[e.grupo] || [];
                const rot = (e.repetir && e.repetir.rotulo) || ((e.titulo || 'Item') + ' {n}');
                h += '<ul class="nav nav-pills mb-2">';
                lista.forEach((it, k) => {
                    h += '<li class="nav-item"><a class="nav-link ' + (k === 0 ? 'active' : '') + '" data-bs-toggle="pill" href="#gr' + i + '_' + k + '">' + esc(rot.replace('{n}', k + 1)) + '</a></li>';
                });
                h += '</ul><div class="tab-content">';
                lista.forEach((it, k) => {
                    h += '<div class="tab-pane fade ' + (k === 0 ? 'show active' : '') + '" id="gr' + i + '_' + k + '">' + perguntas(e.perguntas, it.client_id)
                       + '<button class="btn btn-sm btn-outline-danger" type="button" data-grupo-del="' + e.grupo + '" data-cid="' + it.client_id + '">Remover este item</button></div>';
                });
                h += '</div><button class="btn btn-sm btn-outline-primary mt-2" type="button" data-grupo-add="' + e.grupo + '"><i class="fas fa-plus me-1"></i> Adicionar item</button>'
                   + (e.repetir && e.repetir.por ? ' <span class="small text-muted ms-2">Quantidade informada na questão ' + esc(e.repetir.por) + '</span>' : '')
                   + '</div></div>';
                return;
            }
            if (e.nivel === 'morador') {
                if (!moradores.length) { h += '<p class="text-muted mb-0">Cadastre os moradores para preencher esta etapa.</p></div></div>'; return; }
                h += '<ul class="nav nav-pills mb-2">';
                moradores.forEach((m, k) => {
                    h += '<li class="nav-item"><a class="nav-link ' + (k === 0 ? 'active' : '') + '" data-bs-toggle="pill" href="#et' + i + 'm' + k + '">' + esc(m.nome || 'Morador ' + (k + 1)) + '</a></li>';
                });
                h += '</ul><div class="tab-content">';
                moradores.forEach((m, k) => {
                    h += '<div class="tab-pane fade ' + (k === 0 ? 'show active' : '') + '" id="et' + i + 'm' + k + '">' + perguntas(e.perguntas, m.client_id) + '</div>';
                });
                h += '</div>';
            } else {
                h += perguntas(e.perguntas, 'd');
            }
            h += '</div></div>';
        });
        form.innerHTML = h;
        if (ultimo) aplicar(ultimo);
    }

    // ---------------------------------------------------------- eventos
    function resposta(escopo, cod) {
        const s = store(escopo);
        if (!s[cod]) s[cod] = {};
        return s[cod];
    }
    function pergunta(cod) {
        for (const e of DEF.etapas) for (const p of (e.perguntas || [])) if (p.codigo === cod) return p;
        return null;
    }

    form.addEventListener('input', ev => tratar(ev, false));
    form.addEventListener('change', ev => tratar(ev, true));
    form.addEventListener('click', ev => {
        const t = ev.target;
        if (t.dataset.grupoAdd) { grupos[t.dataset.grupoAdd].push({ client_id: uuid(), resp: {} }); render(); agendar(); }
        if (t.dataset.grupoDel) { grupos[t.dataset.grupoDel] = grupos[t.dataset.grupoDel].filter(x => x.client_id !== t.dataset.cid); render(); agendar(); }
        if (t.id === 'mor-add') { moradores.push({ client_id: uuid(), nome: '', resp: {} }); render(); agendar(); }
        if (t.id === 'mor-resp') {
            const e = DEF.etapas.find(x => x.tipo === 'moradores');
            const nome = (dom[e.respondente] || {}).valor || '';
            if (!moradores.length) moradores.push({ client_id: uuid(), nome: '', resp: {} });
            moradores[0].nome = nome; render(); agendar();
        }
        if (t.dataset.morDel) { if (confirm('Remover este morador e as respostas dele?')) { moradores = moradores.filter(m => m.client_id !== t.dataset.morDel); render(); agendar(); } }
        if (t.dataset.acao === 'agora') { const pq = t.closest('.pq'); resposta(pq.dataset.escopo, pq.dataset.cod).valor = horaAgora(); pq.querySelector('input[type=time]').value = horaAgora(); agendar(); }
        if (t.dataset.acao === 'gps' && navigator.geolocation) {
            const pq = t.closest('.pq');
            navigator.geolocation.getCurrentPosition(pos => {
                resposta(pq.dataset.escopo, pq.dataset.cod).valor = { lat: +pos.coords.latitude.toFixed(7), lng: +pos.coords.longitude.toFixed(7) };
                pq.querySelector('[data-acao=lat]').value = pos.coords.latitude.toFixed(7);
                pq.querySelector('[data-acao=lng]').value = pos.coords.longitude.toFixed(7);
                agendar();
            });
        }
    });

    function tratar(ev, mudanca) {
        const t = ev.target;
        if (t.dataset.morNome) {
            const m = moradores.find(x => x.client_id === t.dataset.morNome);
            if (m) m.nome = t.value;
            if (mudanca) render();
            agendar();
            return;
        }
        const pq = t.closest('.pq');
        if (!pq || !t.dataset.acao) return;
        const p = pergunta(pq.dataset.cod);
        const r = resposta(pq.dataset.escopo, pq.dataset.cod);
        switch (t.dataset.acao) {
            case 'valor':
                r.valor = (['inteiro', 'decimal', 'moeda'].includes(p.tipo) && t.value !== '') ? Number(t.value) : t.value;
                break;
            case 'opcao':
                if (p.tipo === 'unica') {
                    r.valor = t.value;
                } else {
                    let l = Array.isArray(r.valor) ? r.valor.slice() : [];
                    if (t.checked) {
                        if (t.dataset.excl === '1') l = [];
                        else l = l.filter(x => !(p.opcoes.find(o => o.valor === x) || {}).exclusiva);
                        l.push(t.value);
                    } else l = l.filter(x => x !== t.value);
                    r.valor = l;
                    pq.querySelectorAll('input[data-acao=opcao]').forEach(i => { i.checked = l.includes(i.value); });
                }
                pq.querySelectorAll('.opc').forEach(div => {
                    const c = div.querySelector('.comp');
                    if (c) c.style.setProperty('display', div.querySelector('input').checked ? 'inline-block' : 'none', 'important');
                });
                break;
            case 'complemento': r.complementos = r.complementos || {}; r.complementos[t.dataset.opcao] = t.value; break;
            case 'complemento_q': r.complemento = t.value; break;
            case 'nsnr':
                r.nsnr = t.checked;
                pq.querySelectorAll('[data-acao=valor]').forEach(i => { i.disabled = t.checked; });
                break;
            case 'campo': r.valor = Object.assign({}, r.valor || {}, { [t.dataset.campo]: t.value === '' ? '' : Number(t.value) }); break;
            case 'item': { const l = Array.isArray(r.valor) ? r.valor.slice() : []; l[+t.dataset.i] = t.value; r.valor = l; break; }
            case 'lat': case 'lng': r.valor = Object.assign({}, r.valor || {}, { [t.dataset.acao]: t.value === '' ? null : Number(t.value) }); break;
            case 'foto': r.valor = t.files[0] ? t.files[0].name : ''; break;
        }
        if (p.tipo === 'texto' || p.tipo === 'texto_longo' || !mudanca || true) agendar();
    }

    // ---------------------------------------------------------- avaliação
    function agendar() { clearTimeout(timer); timer = setTimeout(avaliar, 350); }

    async function avaliar() {
        const meu = ++seq;
        const g = {};
        Object.keys(grupos).forEach(k => { g[k] = grupos[k].map((it, i) => ({ client_id: it.client_id, indice: i + 1, respostas: it.resp })); });
        const corpo = { respostas: dom, moradores: moradores.map(m => ({ client_id: m.client_id, nome: m.nome, respostas: m.resp })),
                        grupos: g, entrevistado_id: entrevistado ? entrevistado.id : null };
        try {
            const r = await (await fetch(URL, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(corpo) })).json();
            if (meu !== seq || !r.ok) return;
            ultimo = r;
            aplicar(r);
        } catch (e) { document.getElementById('resumo').textContent = 'Falha ao conferir: ' + e; }
    }

    function aplicar(r) {
        const visDom = r.visiveis.domicilio || {};
        form.querySelectorAll('.pq').forEach(pq => {
            const cod = pq.dataset.cod, esc_ = pq.dataset.escopo;
            const gr = esc_ === 'd' ? null : grupoDe(esc_);
            const fonte = (tipo) => gr ? (((r[tipo].grupos || {})[gr] || {})[esc_] || {}) : (((r[tipo].moradores || {})[esc_]) || {});
            const vis = esc_ === 'd' ? visDom[cod] : fonte('visiveis')[cod];
            pq.style.display = vis === false ? 'none' : '';
            const calc = esc_ === 'd' ? (r.calculados.domicilio || {})[cod] : fonte('calculados')[cod];
            if (calc) {
                const p = pergunta(cod);
                let t = calc.valor === null ? '—' : calc.valor;
                if (calc.valor !== null && p.formato === 'moeda') t = 'R$ ' + Number(calc.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (calc.valor !== null && p.formato === 'duracao') t = Math.floor(calc.valor / 60) + ' h ' + (calc.valor % 60) + ' min';
                pq.querySelector('.valor').innerHTML = esc(t) + (calc.incompleto ? ' <span class="badge bg-warning text-dark">incompleta (há NS/NR)</span>' : '');
            }
            const opc = esc_ === 'd' ? (r.opcoes.domicilio || {})[cod] : fonte('opcoes')[cod];
            if (opc) {
                const ok = opc.map(o => o.valor);
                pq.querySelectorAll('.opc').forEach(div => { div.style.display = ok.includes(div.dataset.valor) ? '' : 'none'; });
            }
            const m = esc_ === 'd' ? null : esc_;
            const erros = r.erros.filter(e => e.codigo === cod && (e.morador || null) === m).map(e => e.mensagem);
            const alertas = r.alertas.filter(e => e.codigo === cod && (e.morador || null) === m).map(e => e.mensagem);
            pq.querySelector('.erro').innerHTML = erros.map(esc).join('<br>');
            pq.querySelector('.alerta').innerHTML = alertas.map(a => '⚠ ' + esc(a)).join('<br>');
            pq.classList.toggle('tem-erro', erros.length > 0);
        });
        // Idade e outras variáveis ocultas aparecem no resumo
        const gerais = r.erros.filter(e => !e.codigo).map(e => e.mensagem);
        const alGerais = r.alertas.filter(e => !e.codigo).map(e => e.mensagem);
        const em = document.getElementById('erro-moradores'), am = document.getElementById('alerta-moradores');
        if (em) em.innerHTML = gerais.map(esc).join('<br>');
        if (am) am.innerHTML = alGerais.map(a => '⚠ ' + esc(a)).join('<br>');

        let h = '<div class="mb-2">' + (r.erros.length ? '<span class="text-danger fw-semibold">' + r.erros.length + ' pendência(s) para finalizar</span>'
                                                      : '<span class="text-success fw-semibold">Pronta para finalizar</span>') + '</div>';
        if (r.alertas.length) h += '<div class="mb-2" style="color:#b26a00"><b>Alertas</b><br>' + r.alertas.map(a => '⚠ ' + esc(a.mensagem)).join('<br>') + '</div>';
        const calcs = [];
        Object.entries(r.calculados.domicilio || {}).forEach(([c, v]) => calcs.push([c, v]));
        Object.entries(r.calculados.moradores || {}).forEach(([cid, l]) => Object.entries(l).forEach(([c, v]) => calcs.push([nomeDe(cid) + ' · ' + c, v])));
        if (calcs.length) h += '<b>Calculados</b><br>' + calcs.map(([c, v]) => esc(c) + ': ' + esc(v.valor === null ? '—' : v.valor) + (v.incompleto ? ' (incompleto)' : '')).join('<br>');
        if (r.erros.length) h += '<hr><b>Pendências</b><ul class="ps-3 mb-0">' + r.erros.slice(0, 40).map(e => '<li>' + (e.morador ? esc(nomeDe(e.morador)) + ': ' : '') + esc(e.mensagem) + '</li>').join('') + '</ul>' + (r.erros.length > 40 ? '…' : '');
        document.getElementById('resumo').innerHTML = h;
    }

    // Preenchimento automático (data e hora de início)
    // Entrevistado pré-cadastrado: escolha no topo e preenchimento (preencher: ent_*)
    if (DEF.entrevistado) {
        const box = document.createElement('div');
        box.className = 'card mb-3';
        box.innerHTML = '<div class="card-body"><label class="form-label fw-semibold">Entrevistado pré-cadastrado ' + (DEF.entrevistado.modo === 'obrigatorio' ? '(obrigatório)' : '(opcional)')
            + '</label><select class="form-select" id="sel-ent"><option value="">— nenhum —</option>'
            + ENTREVISTADOS.map(e => '<option value="' + e.id + '">' + esc(e.codigo + ' - ' + e.nome + (e.categoria ? ' (' + e.categoria + ')' : '')) + '</option>').join('')
            + '</select>' + (ENTREVISTADOS.length ? '' : '<div class="small text-muted mt-1">Nenhum entrevistado cadastrado para o projeto deste formulário.</div>') + '</div>';
        form.parentNode.insertBefore(box, form);
        box.querySelector('#sel-ent').addEventListener('change', ev => {
            const e = ENTREVISTADOS.find(x => String(x.id) === ev.target.value);
            entrevistado = e ? { id: e.id, contexto: e.contexto } : null;
            if (e) DEF.etapas.forEach(et => (et.perguntas || []).forEach(p => {
                if (p.preencher && et.nivel !== 'morador' && et.nivel !== 'grupo') {
                    const v = e.contexto[p.preencher.substring(4)];
                    if (v !== undefined && v !== null) dom[p.codigo] = Object.assign({}, dom[p.codigo] || {}, { valor: v });
                }
            }));
            render(); agendar();
        });
    }
    DEF.etapas.forEach(e => (e.perguntas || []).forEach(p => {
        if (e.nivel !== 'morador' && p.auto === 'data') dom[p.codigo] = { valor: dataHoje };
        if (e.nivel !== 'morador' && p.auto === 'hora') dom[p.codigo] = { valor: horaAgora() };
    }));
    render();
    avaliar();
})();
</script>
