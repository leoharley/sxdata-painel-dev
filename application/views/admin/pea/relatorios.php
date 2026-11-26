<?php defined('BASEPATH') OR exit('No direct script access allowed');
$st = ['rascunho' => ['Rascunho', 'secondary'], 'em_conferencia' => ['Em conferência', 'warning text-dark'], 'aprovado' => ['Aprovado', 'success']];
$tipos = ['ficha' => 'Ficha da ação', 'semestral' => 'Formulário semestral', 'anual' => 'Relatório anual', 'ciclo' => 'Relatório do ciclo'];
?>
<div class="container-fluid">
    <p class="text-muted">O módulo gera a versão preliminar com os indicadores do período (congelados na geração, com a versão das regras). A equipe revisa os textos interpretativos, envia para conferência, e um administrador aprova. Só então o documento sai em PDF e Excel sem a marca de rascunho.</p>
    <?= form_open('pea/gerar', ['class' => 'card card-body mb-3']) ?>
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small mb-0">Relatório</label>
                <select name="tipo" class="form-select form-select-sm" id="tipo" onchange="campos()"><?php foreach ($tipos as $k => $n): ?><option value="<?= $k ?>"><?= $n ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2 c-ano"><label class="form-label small mb-0">Ano do ciclo</label>
                <select name="ano" class="form-select form-select-sm"><?php for ($a = 1; $a <= (int) $ciclo->anos; $a++): ?><option value="<?= $a ?>">Ano <?= $a ?> (<?= html_escape(implode(' a ', array_map(function ($d) { return date('m/Y', strtotime($d)); }, Pea_motor::intervalo($a, NULL, $ciclo->inicio)))) ?>)</option><?php endfor; ?></select></div>
            <div class="col-md-2 c-sem"><label class="form-label small mb-0">Semestre do ano</label>
                <select name="semestre" class="form-select form-select-sm"><option value="1">1º (trimestres 1 e 2)</option><option value="2">2º (trimestres 3 e 4)</option></select></div>
            <div class="col-md-4 c-acao"><label class="form-label small mb-0">Ação encerrada</label>
                <select name="acao_id" class="form-select form-select-sm"><?php foreach ($acoes as $a): ?><option value="<?= (int) $a->id ?>"><?= html_escape($a->titulo . ' — ' . ($a->data_realizacao ? date('d/m/Y', strtotime($a->data_realizacao)) : '')) ?></option><?php endforeach; ?><?php if (!$acoes): ?><option value="">Nenhuma ação encerrada</option><?php endif; ?></select></div>
            <div class="col-md-1"><button class="btn btn-success btn-sm w-100">Gerar</button></div>
        </div>
    <?= form_close() ?>
    <div class="card"><div class="card-body p-0">
        <table class="table table-sm table-hover mb-0 small align-middle">
            <thead class="table-light"><tr><th>#</th><th>Relatório</th><th>Gerado</th><th>Situação</th><th>Aprovado</th><th></th></tr></thead>
            <tbody>
            <?php if (!$lista): ?><tr><td colspan="6" class="p-3 text-muted">Nenhum relatório gerado.</td></tr><?php endif; ?>
            <?php foreach ($lista as $r): $s = $st[$r->status] ?? [$r->status, 'secondary']; ?>
                <tr><td><?= (int) $r->id ?></td><td><a href="<?= base_url('pea/relatorio/' . (int) $r->id) ?>" class="fw-semibold"><?= html_escape($r->titulo) ?></a><br><span class="text-muted"><?= $tipos[$r->tipo] ?? $r->tipo ?></span></td>
                    <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($r->criado_em)) ?><br><span class="text-muted"><?= html_escape($r->autor ?: '') ?></span></td>
                    <td><span class="badge bg-<?= $s[1] ?>"><?= $s[0] ?></span></td>
                    <td class="text-nowrap"><?= $r->aprovado_em ? date('d/m/Y', strtotime($r->aprovado_em)) . '<br><span class="text-muted">' . html_escape($r->aprovador) . '</span>' : '—' ?></td>
                    <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= base_url('pea/imprimir/' . (int) $r->id) ?>"><i class="fas fa-file-pdf"></i></a> <a class="btn btn-sm btn-outline-success" href="<?= base_url('pea/excel/' . (int) $r->id) ?>"><i class="fas fa-file-excel"></i></a></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
</div>
<script>
function campos() {
    var t = document.getElementById('tipo').value;
    document.querySelector('.c-ano').style.display = (t === 'semestral' || t === 'anual') ? '' : 'none';
    document.querySelector('.c-sem').style.display = t === 'semestral' ? '' : 'none';
    document.querySelector('.c-acao').style.display = t === 'ficha' ? '' : 'none';
}
campos();
</script>
