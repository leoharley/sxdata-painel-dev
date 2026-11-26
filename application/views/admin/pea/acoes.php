<?php defined('BASEPATH') OR exit('No direct script access allowed');
$st = ['planejada' => ['Planejada', 'secondary'], 'realizada' => ['Realizada', 'info text-dark'], 'encerrada' => ['Encerrada', 'success'], 'cancelada' => ['Cancelada', 'dark']]; ?>
<div class="container-fluid">
    <form class="card card-body py-2 mb-3" method="get">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small mb-0">Projeto</label>
                <select name="projeto_id" class="form-select form-select-sm"><option value="">Todos</option><?php foreach ($projetos as $p): ?><option value="<?= (int) $p->id ?>" <?= (int) $f['projeto_id'] === (int) $p->id ? 'selected' : '' ?>><?= html_escape($p->nome) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label small mb-0">Público</label>
                <select name="publico" class="form-select form-select-sm"><option value="">Todos</option><?php foreach (Pea_model::PUBLICOS as $k => $n): ?><option value="<?= $k ?>" <?= $f['publico'] === $k ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label small mb-0">Unidade</label>
                <select name="unidade_id" class="form-select form-select-sm"><option value="">Todas</option><?php foreach ($unidades as $u): ?><option value="<?= (int) $u->id ?>" <?= (int) $f['unidade_id'] === (int) $u->id ? 'selected' : '' ?>><?= html_escape($u->sigla) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-1"><label class="form-label small mb-0">Ano</label><input type="number" name="ano" min="1" max="5" class="form-control form-control-sm" value="<?= html_escape($f['ano']) ?>"></div>
            <div class="col-md-2"><label class="form-label small mb-0">Situação</label>
                <select name="status" class="form-select form-select-sm"><option value="">Todas</option><?php foreach ($st as $k => $s): ?><option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= $s[0] ?></option><?php endforeach; ?></select></div>
            <div class="col-md-1"><button class="btn btn-primary btn-sm w-100">Filtrar</button></div>
            <div class="col-md-1"><a href="<?= base_url('pea/nova') ?>" class="btn btn-success btn-sm w-100"><i class="fas fa-plus"></i> Nova</a></div>
        </div>
    </form>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0 small align-middle">
                <thead class="table-light"><tr><th>Ação</th><th>Projeto</th><th>Local</th><th>Previsto</th><th>Realizada</th><th class="text-end">Participantes</th><th class="text-end">Avaliações</th><th>Situação</th></tr></thead>
                <tbody>
                <?php if (!$acoes): ?><tr><td colspan="8" class="p-3 text-muted">Nenhuma ação com esses filtros. Abra a ficha de uma ação com "Nova".</td></tr><?php endif; ?>
                <?php foreach ($acoes as $a): $s = $st[$a->status] ?? [$a->status, 'secondary']; ?>
                    <tr>
                        <td><a href="<?= base_url('pea/acao/' . (int) $a->id) ?>" class="fw-semibold"><?= html_escape($a->titulo) ?></a></td>
                        <td><?= html_escape($a->projeto) ?><br><span class="text-muted"><?= Pea_model::PUBLICOS[$a->publico] ?? '' ?></span></td>
                        <td><?= html_escape(trim(($a->unidade ?: '') . ' ' . ($a->comunidade ? '· ' . $a->comunidade : ''))) ?: '—' ?></td>
                        <td class="text-nowrap"><?= $a->data_prevista ? date('d/m/Y', strtotime($a->data_prevista)) : '—' ?><br><span class="text-muted"><?= $a->ano_previsto ? 'ano ' . (int) $a->ano_previsto . ', ' . (int) $a->trimestre_previsto . 'º tri' : '' ?></span></td>
                        <td class="text-nowrap"><?= $a->data_realizacao ? date('d/m/Y', strtotime($a->data_realizacao)) : '—' ?></td>
                        <td class="text-end"><?= $a->participantes !== NULL ? (int) $a->participantes : '—' ?></td>
                        <td class="text-end"><?= (int) $a->respostas ?></td>
                        <td><span class="badge bg-<?= $s[1] ?>"><?= $s[0] ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
