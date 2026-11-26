<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<style>
    .ed-txt { font: 13px/1.5 Consolas, "Courier New", monospace; white-space: pre; min-height: 55vh; tab-size: 2; }
</style>
<div class="container-fluid">
    <a href="<?= base_url('formularios-estruturados/conjuntos') ?>" class="small"><i class="fas fa-arrow-left"></i> Biblioteca</a>
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h1 class="h3 mb-0"><?= html_escape($c->nome) ?></h1>
            <div class="text-muted small">Use em um formulário com <code>@usar <?= html_escape($c->codigo) ?></code> ·
                <?= $c->versao_atual ? 'versão publicada v' . (int) $c->versao_atual : 'ainda sem versão publicada' ?></div>
        </div>
        <a class="btn btn-outline-danger btn-sm" href="<?= base_url('formularios-estruturados/conjunto_excluir/' . $c->id) ?>" onclick="return confirm('Excluir o conjunto?')">Excluir</a>
    </div>
    <div class="row g-3 mt-1">
        <div class="col-lg-8">
            <div class="row g-2 mb-2">
                <div class="col-md-5"><input class="form-control form-control-sm" id="nome" value="<?= html_escape($c->nome) ?>"></div>
                <div class="col-md-7"><input class="form-control form-control-sm" id="descricao" value="<?= html_escape($c->descricao) ?>" placeholder="Descrição"></div>
            </div>
            <textarea class="form-control ed-txt" id="fonte" spellcheck="false" wrap="off"><?= html_escape($c->fonte) ?></textarea>
            <div class="mt-2 d-flex gap-2 align-items-center">
                <button class="btn btn-primary btn-sm" id="salvar">Salvar</button>
                <button class="btn btn-success btn-sm" id="publicar">Publicar versão</button>
                <span class="small text-muted" id="estado"></span>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-header fw-semibold">Validação</div><div class="card-body small" id="validacao"></div></div>
            <div class="card mb-3"><div class="card-header fw-semibold">Usado em</div>
                <ul class="list-group list-group-flush small">
                    <?php if (!$usos): ?><li class="list-group-item text-muted">Nenhum formulário.</li><?php endif; ?>
                    <?php foreach ($usos as $u): ?><li class="list-group-item"><a href="<?= base_url('formularios-estruturados/editar/' . $u->id) ?>"><?= html_escape($u->title) ?></a></li><?php endforeach; ?>
                </ul>
                <div class="card-footer small text-muted">Ao publicar uma versão nova do conjunto, publique de novo os formulários para que a usem. Entrevistas já feitas ficam na versão em que foram coletadas.</div>
            </div>
            <div class="card"><div class="card-header fw-semibold">Versões</div>
                <ul class="list-group list-group-flush small">
                    <?php if (!$versoes): ?><li class="list-group-item text-muted">Nenhuma.</li><?php endif; ?>
                    <?php foreach ($versoes as $v): ?><li class="list-group-item">v<?= (int) $v->versao ?> · <?= date('d/m/Y H:i', strtotime($v->publicado_em)) ?><?= $v->autor ? ' · ' . html_escape($v->autor) : '' ?></li><?php endforeach; ?>
                </ul></div>
        </div>
    </div>
</div>
<script>
(function () {
    const URL = <?= json_encode(base_url('formularios-estruturados/conjunto_salvar/' . $c->id)) ?>;
    const esc = s => { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    function mostrar(erros) {
        document.getElementById('validacao').innerHTML = erros.length
            ? '<ul class="text-danger ps-3 mb-0">' + erros.map(e => '<li>' + (e.linha ? 'Linha ' + e.linha + ': ' : '') + esc(e.mensagem) + '</li>').join('') + '</ul>'
            : '<span class="text-success"><i class="fas fa-check-circle"></i> Sem erros.</span>';
    }
    async function enviar(publicar) {
        const fd = new FormData();
        fd.append('fonte', document.getElementById('fonte').value);
        fd.append('nome', document.getElementById('nome').value);
        fd.append('descricao', document.getElementById('descricao').value);
        if (publicar) fd.append('publicar', '1');
        const r = await (await fetch(URL, { method: 'POST', body: fd, credentials: 'same-origin' })).json();
        mostrar(r.erros || []);
        document.getElementById('estado').textContent = r.mensagem || ('Salvo às ' + r.salvo_em);
        if (publicar && r.ok) setTimeout(() => location.reload(), 1200);
    }
    document.getElementById('salvar').onclick = () => enviar(false);
    document.getElementById('publicar').onclick = () => { if (confirm('Publicar uma nova versão do conjunto?')) enviar(true); };
    document.getElementById('fonte').addEventListener('keydown', ev => {
        if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 's') { ev.preventDefault(); enviar(false); }
    });
    mostrar(<?= json_encode($erros, JSON_UNESCAPED_UNICODE) ?>);
})();
</script>
