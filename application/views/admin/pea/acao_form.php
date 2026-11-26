<?php defined('BASEPATH') OR exit('No direct script access allowed');
$v = function ($k, $padrao = '') use ($a) { return $a && isset($a->$k) ? $a->$k : $padrao; };
$pid = (int) ($a ? $a->projeto_id : $projeto_id);
?>
<div class="container-fluid" style="max-width: 1100px">
    <a href="<?= base_url($a ? 'pea/acao/' . (int) $a->id : 'pea/acoes') ?>" class="small"><i class="fas fa-arrow-left"></i> <?= $a ? 'Ficha da ação' : 'Ações' ?></a>
    <h1 class="h4"><?= $a ? 'Editar a ação' : 'Nova ação do PEA' ?></h1>
    <?= form_open('pea/salvar/' . ($a ? (int) $a->id : 0)) ?>
    <div class="card mb-3"><div class="card-body">
        <div class="row g-2">
            <div class="col-md-5"><label class="form-label small mb-0">Projeto *</label>
                <select name="projeto_id" class="form-select" required onchange="linhas()" id="projeto">
                    <option value="">Escolha…</option>
                    <?php foreach (Pea_model::PUBLICOS as $pk => $pn): ?><optgroup label="<?= $pn ?>">
                        <?php foreach ($projetos as $p): if ($p->publico !== $pk) { continue; } ?><option value="<?= (int) $p->id ?>" data-publico="<?= $pk ?>" <?= $pid === (int) $p->id ? 'selected' : '' ?>><?= html_escape($p->nome) ?></option><?php endforeach; ?>
                    </optgroup><?php endforeach; ?>
                </select></div>
            <div class="col-md-7"><label class="form-label small mb-0">Título da ação</label><input name="titulo" class="form-control" maxlength="250" value="<?= html_escape($v('titulo')) ?>" placeholder="Ex.: Bate-Papo Ambiental — uso consciente da água"></div>
            <div class="col-md-3"><label class="form-label small mb-0">Unidade</label>
                <select name="unidade_id" class="form-select"><option value="">—</option><?php foreach ($unidades as $u): ?><option value="<?= (int) $u->id ?>" <?= (int) $v('unidade_id') === (int) $u->id ? 'selected' : '' ?>><?= html_escape($u->sigla . ' — ' . $u->nome) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label small mb-0">Comunidade / ABEA</label>
                <select name="comunidade_id" class="form-select"><option value="">—</option><?php foreach ($comunidades as $c): ?><option value="<?= (int) $c->id ?>" <?= (int) $v('comunidade_id') === (int) $c->id ? 'selected' : '' ?>><?= html_escape(($c->sigla ? $c->sigla . ' · ' : '') . $c->nome . ($c->municipio ? ' (' . $c->municipio . ')' : '')) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-5"><label class="form-label small mb-0">Escola (público escolar)</label>
                <select name="escola_id" class="form-select"><option value="">—</option><?php foreach ($escolas as $e): ?><option value="<?= (int) $e->id ?>" <?= (int) $v('escola_id') === (int) $e->id ? 'selected' : '' ?>><?= html_escape($e->nome . ($e->municipio ? ' (' . $e->municipio . ')' : '')) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label small mb-0">Linha temática</label>
                <select name="linha_id" class="form-select" id="linha"><option value="">—</option><?php foreach ($linhas as $l): ?><option value="<?= (int) $l->id ?>" data-publico="<?= $l->publico ?>" <?= (int) $v('linha_id') === (int) $l->id ? 'selected' : '' ?>><?= html_escape($l->linha) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label small mb-0">Local</label><input name="local" class="form-control" maxlength="250" value="<?= html_escape($v('local')) ?>"></div>
        </div>
    </div></div>
    <div class="card mb-3"><div class="card-body">
        <div class="row g-2">
            <div class="col-md-3"><label class="form-label small mb-0">Data prevista no cronograma</label><input type="date" name="data_prevista" class="form-control" value="<?= html_escape($v('data_prevista')) ?>">
                <div class="form-text">Define o ano e o trimestre do ciclo (base do ICC).</div></div>
            <div class="col-md-3"><label class="form-label small mb-0">Data de realização</label><input type="date" name="data_realizacao" class="form-control" value="<?= html_escape($v('data_realizacao')) ?>">
                <div class="form-text">Vazio = ainda não realizada.</div></div>
            <div class="col-md-2"><label class="form-label small mb-0">Nº de participantes</label><input type="number" min="0" name="participantes" class="form-control" value="<?= html_escape($v('participantes')) ?>">
                <div class="form-text">Pela lista de presença (IP e denominador do IS).</div></div>
            <div class="col-md-2"><label class="form-label small mb-0">Carga horária (h)</label><input type="text" name="carga_horaria" class="form-control" value="<?= html_escape($v('carga_horaria')) ?>"></div>
            <div class="col-md-12"><label class="form-label small mb-0">Observações</label><textarea name="observacoes" class="form-control" rows="2"><?= html_escape($v('observacoes')) ?></textarea></div>
            <div class="col-md-12"><label class="form-label small mb-0">Justificativa (obrigatória se a ação sair do trimestre previsto: ICC abaixo de 50)</label><textarea name="justificativa" class="form-control" rows="2"><?= html_escape($v('justificativa')) ?></textarea></div>
        </div>
    </div></div>
    <button class="btn btn-primary">Salvar</button>
    <?php if ($a && $a->status !== 'cancelada'): ?><button class="btn btn-outline-dark" name="cancelar" value="1" onclick="return confirm('Marcar a ação como cancelada? Ela continua contando como prevista no ICC.')">Cancelar a ação</button><?php endif; ?>
    <?= form_close() ?>
</div>
<script>
function linhas() {
    var o = document.getElementById('projeto').selectedOptions[0], pub = o ? o.dataset.publico : '';
    document.querySelectorAll('#linha option[data-publico]').forEach(function (x) { x.hidden = pub && x.dataset.publico !== pub; });
}
linhas();
</script>
