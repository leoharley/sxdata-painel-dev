<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$status_txt = ['active' => ['Ativo no app', 'success'], 'paused' => ['Pausado', 'secondary'], 'inactive' => ['Inativo', 'dark']];
$s = $status_txt[$q->status] ?? [$q->status, 'light'];
$apl = $q->aplicadores === NULL || trim((string) $q->aplicadores) === '' ? NULL : (json_decode($q->aplicadores, TRUE) ?: []);
?>
<style>
    .ed-wrap { position: relative; display: flex; border: 1px solid #ced4da; border-radius: .375rem; background: #fff; }
    .ed-num { padding: 8px 6px; text-align: right; color: #adb5bd; background: #f8f9fa; border-right: 1px solid #e9ecef;
              font: 13px/1.5 Consolas, "Courier New", monospace; user-select: none; overflow: hidden; white-space: pre; min-width: 3.2em; }
    .ed-txt { flex: 1; border: 0; outline: 0; resize: vertical; padding: 8px; font: 13px/1.5 Consolas, "Courier New", monospace;
              white-space: pre; overflow: auto; min-height: 70vh; tab-size: 2; }
    .lista-erros li { cursor: pointer; }
    .lista-erros li:hover { text-decoration: underline; }
    .ajuda-formato code { white-space: pre; display: block; background: #f8f9fa; padding: 6px 8px; border-radius: 4px; font-size: 12px; }
</style>
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <a href="<?= base_url('formularios-estruturados') ?>" class="small"><i class="fas fa-arrow-left"></i> Formulários estruturados</a>
            <h1 class="h3 mb-1"><?= html_escape($q->title) ?></h1>
            <span class="badge bg-<?= $s[1] ?>"><?= $s[0] ?></span>
            <span class="text-muted small ms-2"><?= $q->estrutura_versao ? 'Versão publicada: v' . (int) $q->estrutura_versao : 'Ainda sem versão publicada' ?>
                <?= $q->project_name ? ' · Projeto: ' . html_escape($q->project_name) : '' ?></span>
        </div>
        <div class="text-nowrap">
            <a class="btn btn-outline-secondary" href="<?= base_url('formularios-estruturados/previa/' . $q->id) ?>" target="_blank"><i class="fas fa-eye me-1"></i> Prévia</a>
            <a class="btn btn-outline-secondary" href="<?= base_url('formularios-estruturados/entrevistas/' . $q->id) ?>"><i class="fas fa-list me-1"></i> Entrevistas</a>
            <a class="btn btn-outline-secondary" href="<?= base_url('formularios-estruturados/fonte/' . $q->id) ?>"><i class="fas fa-download me-1"></i> Texto</a>
        </div>
    </div>

    <?php if ($this->session->flashdata('success')): ?><div class="alert alert-success"><?= html_escape($this->session->flashdata('success')) ?></div><?php endif; ?>
    <?php if ($this->session->flashdata('error')): ?><div class="alert alert-danger"><?= html_escape($this->session->flashdata('error')) ?></div><?php endif; ?>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="d-flex align-items-center gap-2 mb-2">
                <button class="btn btn-outline-primary btn-sm" id="b-validar"><i class="fas fa-check me-1"></i> Validar</button>
                <button class="btn btn-primary btn-sm" id="b-salvar"><i class="fas fa-save me-1"></i> Salvar rascunho <small class="opacity-75">(Ctrl+S)</small></button>
                <button class="btn btn-success btn-sm" id="b-publicar"><i class="fas fa-upload me-1"></i> Publicar versão</button>
                <span class="small text-muted ms-2" id="estado">Rascunho carregado.</span>
            </div>
            <div class="ed-wrap">
                <div class="ed-num" id="num"></div>
                <textarea class="ed-txt" id="fonte" spellcheck="false" wrap="off"><?= html_escape($q->estrutura_fonte) ?></textarea>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header fw-semibold">Validação</div>
                <div class="card-body" id="validacao"></div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold">Estrutura</div>
                <div class="card-body small" id="estrutura"></div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold">Configurações</div>
                <form class="card-body" method="post" action="<?= base_url('formularios-estruturados/configurar/' . $q->id) ?>">
                    <div class="mb-2"><label class="form-label small mb-0">Título</label>
                        <input class="form-control form-control-sm" name="title" value="<?= html_escape($q->title) ?>"></div>
                    <div class="mb-2"><label class="form-label small mb-0">Descrição</label>
                        <textarea class="form-control form-control-sm" name="description" rows="2"><?= html_escape($q->description) ?></textarea></div>
                    <div class="mb-2"><label class="form-label small mb-0">Projeto</label>
                        <select class="form-select form-select-sm" name="project_id">
                            <option value="">Sem projeto</option>
                            <?php foreach ($projetos as $p): ?><option value="<?= (int) $p->id ?>" <?= (int) $q->project_id === (int) $p->id ? 'selected' : '' ?>><?= html_escape($p->name) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="mb-2"><label class="form-label small mb-0">Status</label>
                        <select class="form-select form-select-sm" name="status">
                            <?php foreach ($status_txt as $k => $t): ?><option value="<?= $k ?>" <?= $q->status === $k ? 'selected' : '' ?>><?= $t[0] ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="mb-1 small">Aplicadores que recebem no app</div>
                    <div class="form-check form-check-sm">
                        <input class="form-check-input" type="checkbox" name="aplicadores_todos" value="1" id="ap-todos" <?= $apl === NULL ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="ap-todos">Todos os aplicadores</label>
                    </div>
                    <div id="ap-lista" class="border rounded p-2 mb-2" style="max-height: 160px; overflow: auto; <?= $apl === NULL ? 'display:none' : '' ?>">
                        <?php foreach ($aplicadores as $a): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="aplicadores[]" value="<?= (int) $a->id ?>" id="ap<?= (int) $a->id ?>"
                                <?= $apl !== NULL && in_array((int) $a->id, array_map('intval', $apl), TRUE) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="ap<?= (int) $a->id ?>"><?= html_escape($a->full_name ?: $a->username) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn btn-sm btn-outline-primary">Salvar configurações</button>
                </form>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold">Versões publicadas</div>
                <ul class="list-group list-group-flush small">
                    <?php if (!$versoes): ?><li class="list-group-item text-muted">Nenhuma ainda.</li><?php endif; ?>
                    <?php foreach ($versoes as $v): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>v<?= (int) $v->versao ?> · <?= date('d/m/Y H:i', strtotime($v->publicado_em)) ?><?= $v->publicado_por ? ' · ' . html_escape($v->publicado_por) : '' ?></span>
                        <a href="<?= base_url('formularios-estruturados/fonte/' . $q->id . '/' . (int) $v->versao) ?>">texto</a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="card mb-3 ajuda-formato">
                <div class="card-header fw-semibold"><a data-bs-toggle="collapse" href="#ajuda" class="text-decoration-none">Ajuda do formato ▾</a></div>
                <div class="collapse card-body small" id="ajuda">
                    <p class="mb-1"><b>Etapas</b> (na ordem da entrevista):</p>
<code>@domicilio Título    perguntas feitas uma vez
@moradores 17. Texto  lista de moradores
  respondente: q12    (nome do 1º morador)
  declarado: q15      (alerta se a lista divergir)
  regra: condição | mensagem | bloqueia
@morador Título       perguntas repetidas por morador
  var idade = idade(q21, q5)</code>
                    <p class="mb-1 mt-2"><b>Questão</b>: número, texto e tipo entre colchetes. Use <b>[NOME]</b> para o nome do morador.</p>
<code>53. Rendimento de [NOME]? [moeda nsnr]
  ajuda: texto de apoio ao entrevistador
  se: q49 == "Conta própria"
  - Opção          - Outros. Qual?  (pede o complemento)
  - Sim. Quantas:  - Nenhum {exclusiva}</code>
                    <p class="mb-1 mt-2"><b>Tipos</b>: unica, multipla, texto, texto_longo, inteiro, decimal, moeda, data, hora, telefone, cpf, cnpj, composto (campos: anos:inteiro, meses:inteiro), lista (itens=3), calculado, foto, coordenadas, mensagem.
                        <b>Marcas</b>: nsnr, opcional, oculta, auto=data|hora|hora_fim, formato=moeda|duracao.</p>
                    <p class="mb-1"><b>Atributos</b>: <i>se:</i> (exibir se), <i>pula: condição -&gt; 121</i>, <i>alerta: condição | mensagem</i>, <i>formula:</i>, <i>opcoes_de: q143</i>, <i>complemento: Rótulo (opcional)</i>, <i>validacao: anos_virgula</i>.</p>
                    <p class="mb-1"><b>Condições</b>: <i>==, !=, &gt;, &gt;=, &lt;, &lt;=, em [..], fora_de [..], contem, comeca, e, ou, nao (...)</i>,
                        <i>preenchido(q), vazio(q), nsnr(q), tem_valor(q), contar(q)</i>. Compare com o <b>texto da opção</b> entre aspas.</p>
                    <p class="mb-0"><b>Cálculos</b>: <i>+ - * /, soma(...), div(a, b), arred(x, 2), soma_moradores(expr, filtro), contar_moradores(filtro), existe_morador(filtro), idade(nasc, data), duracao(h1, h2)</i>.
                        NS/NR conta 0 mas marca o resultado como <b>incompleto</b>. Manual completo: docs/FORMULARIOS_ESTRUTURADOS.md.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const URL_VALIDAR = <?= json_encode(base_url('formularios-estruturados/validar')) ?>;
    const URL_SALVAR = <?= json_encode(base_url('formularios-estruturados/salvar/' . $q->id)) ?>;
    const URL_PUBLICAR = <?= json_encode(base_url('formularios-estruturados/publicar/' . $q->id)) ?>;
    const ta = document.getElementById('fonte');
    const num = document.getElementById('num');
    const estado = document.getElementById('estado');
    let alterado = false, timer = null;

    function numerar() {
        const n = ta.value.split('\n').length;
        let s = '';
        for (let i = 1; i <= n; i++) s += i + '\n';
        num.textContent = s;
        num.style.height = ta.clientHeight + 'px';
        num.scrollTop = ta.scrollTop;
    }
    ta.addEventListener('scroll', () => { num.scrollTop = ta.scrollTop; });
    new ResizeObserver(numerar).observe(ta);

    function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

    function irParaLinha(l) {
        const linhas = ta.value.split('\n');
        let pos = 0;
        for (let i = 0; i < l - 1 && i < linhas.length; i++) pos += linhas[i].length + 1;
        ta.focus();
        ta.setSelectionRange(pos, pos + (linhas[l - 1] || '').length);
        ta.scrollTop = Math.max(0, (l - 5) * 19.5);
    }

    function mostrar(r) {
        const v = document.getElementById('validacao');
        let h = '';
        if (r.valido) h += '<div class="text-success mb-2"><i class="fas fa-check-circle"></i> Sem erros. Pode publicar.</div>';
        if (r.erros && r.erros.length) {
            h += '<div class="text-danger fw-semibold mb-1">' + r.erros.length + ' erro(s)</div><ul class="lista-erros small text-danger ps-3">';
            r.erros.forEach(e => { h += '<li data-l="' + (e.linha || '') + '">' + (e.linha ? 'Linha ' + e.linha + ': ' : '') + esc(e.mensagem) + '</li>'; });
            h += '</ul>';
        }
        if (r.avisos && r.avisos.length) {
            h += '<div class="text-warning fw-semibold mb-1">Avisos</div><ul class="lista-erros small ps-3">';
            r.avisos.forEach(e => { h += '<li data-l="' + (e.linha || '') + '">' + (e.linha ? 'Linha ' + e.linha + ': ' : '') + esc(e.mensagem) + '</li>'; });
            h += '</ul>';
        }
        v.innerHTML = h;
        v.querySelectorAll('li[data-l]').forEach(li => li.addEventListener('click', () => { if (li.dataset.l) irParaLinha(+li.dataset.l); }));

        const e = document.getElementById('estrutura');
        if (!r.etapas || !r.etapas.length) { e.innerHTML = '<span class="text-muted">—</span>'; return; }
        let t = '<div class="mb-2"><b>' + esc(r.titulo || '') + '</b><br>' + r.total + ' questões</div><ol class="ps-3 mb-0">';
        const nome = { domicilio: 'Domicílio', morador: 'Por morador', moradores: 'Lista de moradores' };
        r.etapas.forEach(x => {
            t += '<li><span class="badge bg-light text-dark border">' + nome[x.tipo] + '</span> ' + esc(x.titulo || '')
               + (x.n ? ' <span class="text-muted">(' + x.n + ' questões' + (x.de ? ', ' + esc(x.de) + ' a ' + esc(x.ate) : '') + ')</span>' : '') + '</li>';
        });
        e.innerHTML = t + '</ol>';
    }

    async function enviar(url) {
        const fd = new FormData();
        fd.append('fonte', ta.value);
        const r = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
        return r.json();
    }

    async function validar() {
        try { mostrar(await enviar(URL_VALIDAR)); } catch (e) { estado.textContent = 'Falha ao validar: ' + e; }
    }
    async function salvar() {
        estado.textContent = 'Salvando...';
        try {
            const r = await enviar(URL_SALVAR);
            mostrar(r);
            alterado = false;
            estado.textContent = 'Rascunho salvo às ' + r.salvo_em + (r.valido ? '.' : ' (com erros: corrija antes de publicar).');
        } catch (e) { estado.textContent = 'Falha ao salvar: ' + e; }
    }
    async function publicar() {
        if (!confirm('Publicar uma nova versão? Os aplicadores recebem a nova versão quando o formulário estiver ativo; as entrevistas já feitas continuam ligadas à versão em que foram coletadas.')) return;
        estado.textContent = 'Publicando...';
        try {
            const r = await enviar(URL_PUBLICAR);
            if (r.ok) { alterado = false; alert(r.mensagem); location.reload(); }
            else { mostrar({ valido: false, erros: r.erros || [], avisos: r.avisos || [] }); estado.textContent = r.mensagem || 'Não publicado.'; }
        } catch (e) { estado.textContent = 'Falha ao publicar: ' + e; }
    }

    ta.addEventListener('input', () => {
        alterado = true;
        estado.textContent = 'Alterações não salvas.';
        numerar();
        clearTimeout(timer);
        timer = setTimeout(validar, 1200);
    });
    ta.addEventListener('keydown', ev => {
        if (ev.key === 'Tab') { ev.preventDefault(); const p = ta.selectionStart; ta.setRangeText('  ', p, ta.selectionEnd, 'end'); }
        if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 's') { ev.preventDefault(); salvar(); }
    });
    document.getElementById('b-validar').addEventListener('click', validar);
    document.getElementById('b-salvar').addEventListener('click', salvar);
    document.getElementById('b-publicar').addEventListener('click', publicar);
    window.addEventListener('beforeunload', ev => { if (alterado) { ev.preventDefault(); ev.returnValue = ''; } });
    document.getElementById('ap-todos').addEventListener('change', ev => {
        document.getElementById('ap-lista').style.display = ev.target.checked ? 'none' : '';
    });

    numerar();
    mostrar(<?= json_encode($resultado, JSON_UNESCAPED_UNICODE) ?>);
})();
</script>
