<?php defined('BASEPATH') OR exit('No direct script access allowed');
$r = $d['regras'];
$t = $d['total'];
$nomes = ['ICC' => 'Cumprimento do cronograma', 'IP' => 'Participação', 'IS' => 'Satisfação', 'IIA' => 'Impacto da atividade', 'IE' => 'Execução'];
$itens_nome = [2 => 'Conteúdo / tema', 3 => 'Condução', 4 => 'Relação com o dia a dia', 5 => 'Organização'];
$pratica_nome = ['trabalho' => 'Sim, no trabalho', 'casa_comunidade' => 'Sim, em casa/comunidade', 'ambos' => 'Sim, nos dois', 'talvez' => 'Talvez', 'nao' => 'Não'];
$q = function ($extra) use ($f) { return '?' . http_build_query(array_filter(array_merge($f, $extra))); };
?>
<div class="container-fluid">
    <form class="card card-body py-2 mb-3 d-print-none" method="get">
        <div class="row g-2 align-items-end">
            <div class="col-md-1"><label class="form-label small mb-0">Ano</label>
                <select name="ano" class="form-select form-select-sm"><option value="">Ciclo</option><?php for ($a = 1; $a <= (int) $ciclo->anos; $a++): ?><option value="<?= $a ?>" <?= (int) $f['ano'] === $a ? 'selected' : '' ?>>Ano <?= $a ?></option><?php endfor; ?></select></div>
            <div class="col-md-1"><label class="form-label small mb-0">Trimestre</label>
                <select name="trimestre" class="form-select form-select-sm"><option value="">Todos</option><?php for ($i = 1; $i <= 4; $i++): ?><option value="<?= $i ?>" <?= (int) $f['trimestre'] === $i ? 'selected' : '' ?>><?= $i ?>º</option><?php endfor; ?></select></div>
            <div class="col-md-2"><label class="form-label small mb-0">Público</label>
                <select name="publico" class="form-select form-select-sm"><option value="">Todos</option><?php foreach (Pea_model::PUBLICOS as $k => $n): ?><option value="<?= $k ?>" <?= $f['publico'] === $k ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label small mb-0">Unidade</label>
                <select name="unidade_id" class="form-select form-select-sm"><option value="">Todas</option><?php foreach ($unidades as $u): ?><option value="<?= (int) $u->id ?>" <?= (int) $f['unidade_id'] === (int) $u->id ? 'selected' : '' ?>><?= html_escape($u->sigla . ' — ' . $u->nome) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label small mb-0">Comunidade / ABEA</label>
                <select name="comunidade_id" class="form-select form-select-sm"><option value="">Todas</option><?php foreach ($comunidades as $c): ?><option value="<?= (int) $c->id ?>" <?= (int) $f['comunidade_id'] === (int) $c->id ? 'selected' : '' ?>><?= html_escape(($c->sigla ? $c->sigla . ' · ' : '') . $c->nome) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label small mb-0">Projeto</label>
                <select name="projeto_id" class="form-select form-select-sm"><option value="">Todos</option><?php foreach ($projetos as $p): ?><option value="<?= (int) $p->id ?>" <?= (int) $f['projeto_id'] === (int) $p->id ? 'selected' : '' ?>><?= html_escape($p->nome) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-1"><button class="btn btn-primary btn-sm w-100">Filtrar</button></div>
        </div>
    </form>

    <div class="row g-2 mb-3">
        <?php foreach (['ICC' => 'icc', 'IP' => 'ip', 'IS' => 'is', 'IIA' => 'iia', 'IE' => 'ie'] as $ind => $k): $v = $t[$k]; $niv = Pea_motor::nivel($ind, $v); ?>
        <div class="col">
            <div class="card pea-card border-<?= $niv ?> h-100">
                <div class="card-body py-2">
                    <div class="small text-muted"><?= $ind ?> · <?= $nomes[$ind] ?></div>
                    <div class="valor text-<?= $niv === 'secondary' ? 'muted' : $niv ?>"><?= pea_num($v) ?></div>
                    <div class="small"><?= html_escape(Pea_motor::classificar($ind, $v, $r) ?: 'Sem dados no período') ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <p class="small text-muted">Base: <?= (int) $d['n_acoes'] ?> ação(ões) no recorte · <?= (int) $d['participantes'] ?> participantes · <?= (int) $d['respondentes'] ?> avaliações · regras de ponderação v<?= (int) $d['versao_regras'] ?>. ICC, IP, IS e IIA do recorte são a média dos projetos; o IE combina os quatro.</p>

    <?php if ($d['atrasadas']): ?>
    <div class="alert alert-warning">
        <strong><i class="fas fa-exclamation-triangle me-1"></i><?= count($d['atrasadas']) ?> ação(ões) atrasada(s) no cronograma</strong> (trimestre previsto já passou e a ação não foi realizada):
        <?php foreach (array_slice($d['atrasadas'], 0, 8) as $a): ?><a class="badge bg-warning text-dark text-decoration-none ms-1" href="<?= base_url('pea/acao/' . (int) $a->id) ?>"><?= html_escape($a->titulo) ?> (ano <?= (int) $a->ano_previsto ?>, <?= (int) $a->trimestre_previsto ?>º tri)</a><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header"><strong>Execução por projeto</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0 small align-middle">
                        <thead class="table-light"><tr><th>Projeto</th><th class="text-end">Previstas</th><th class="text-end">Realizadas</th><th class="text-end">ICC</th><th class="text-end">IP</th><th class="text-end">IS</th><th class="text-end">IIA</th><th class="text-end">IE</th></tr></thead>
                        <tbody>
                        <?php foreach ($d['projetos'] as $p): ?>
                            <tr>
                                <td><a href="<?= base_url('pea' . $q(['projeto_id' => $p['projeto']->id])) ?>"><?= html_escape($p['projeto']->nome) ?></a><br><span class="text-muted"><?= Pea_model::PUBLICOS[$p['projeto']->publico] ?? '' ?> · <?= html_escape($p['projeto']->eixo ?? '') ?></span></td>
                                <td class="text-end"><?= (int) $p['previstas'] ?></td><td class="text-end"><?= (int) $p['realizadas'] ?></td>
                                <?php foreach (['ICC' => 'icc', 'IP' => 'ip', 'IS' => 'is', 'IIA' => 'iia', 'IE' => 'ie'] as $ind => $k): ?>
                                    <td class="text-end"><span class="text-<?= Pea_motor::nivel($ind, $p[$k]) ?> fw-semibold" title="<?= html_escape(Pea_motor::classificar($ind, $p[$k], $r)) ?>"><?= pea_num($p[$k]) ?></span></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card h-100"><div class="card-header"><strong>IE e componentes por projeto</strong></div><div class="card-body"><canvas id="gProjetos" height="260"></canvas></div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header"><strong>Cronograma: ações previstas × realizadas por trimestre</strong></div><div class="card-body"><canvas id="gCrono" height="180"></canvas></div></div>
        </div>
        <div class="col-lg-3">
            <div class="card h-100"><div class="card-header"><strong>Satisfação por item</strong> <span class="small text-muted">(% satisfeitos)</span></div>
                <div class="card-body small">
                    <?php foreach ($itens_nome as $n => $nome): $v = $d['is_itens'][$n] ?? NULL; ?>
                        <div class="mb-2"><?= $nome ?><div class="progress" style="height: 14px"><div class="progress-bar bg-<?= Pea_motor::nivel('IS', $v) ?>" style="width: <?= (float) $v ?>%"><?= pea_num($v) ?></div></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card h-100"><div class="card-header"><strong>Vai colocar em prática?</strong> <span class="small text-muted">(P9)</span></div>
                <div class="card-body small">
                    <?php $tp = array_sum($d['pratica']); foreach ($pratica_nome as $k => $nome): $n = (int) ($d['pratica'][$k] ?? 0); ?>
                        <div class="d-flex justify-content-between"><span><?= $nome ?></span><span><?= $n ?> (<?= $tp ? pea_num($n / $tp * 100) : '—' ?>%)</span></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between"><strong>Percepção e comportamento ambiental (EIEAP)</strong><a class="small" href="<?= base_url('pea/eieap') ?>">valores e árvore</a></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 small">
                        <thead class="table-light"><tr><th>Índice</th><th>Unidade</th><th class="text-end">Base 2021</th><th class="text-end">Atual</th><th class="text-end">Meta</th><th class="text-end">Falta</th></tr></thead>
                        <tbody>
                        <?php $tem = FALSE; foreach ($eieap['nos'] as $no): if ($no['codigo'] === 'iea' && count($eieap['nos']) > 1) { continue; } foreach ($eieap['unidades'] as $un => $v):
                            $b = $v['base'][$no['codigo']]['valor'] ?? NULL; $at = $v['atual'][$no['codigo']]['valor'] ?? ($v['calculado'][$no['codigo']] ?? NULL); $mt = $v['meta'][$no['codigo']]['valor'] ?? NULL;
                            if ($b === NULL && $at === NULL && $mt === NULL) { continue; } $tem = TRUE; ?>
                            <tr><td><?= html_escape($no['nome']) ?></td><td><?= html_escape($un) ?></td><td class="text-end"><?= pea_num($b) ?></td>
                                <td class="text-end"><span title="<?= html_escape(Pea_motor::classificar('EIEAP', $at, $r)) ?>"><?= pea_num($at) ?></span></td><td class="text-end"><?= pea_num($mt) ?></td>
                                <td class="text-end"><?= $at !== NULL && $mt !== NULL ? ($mt - $at > 0 ? '<span class="text-danger">' . pea_num($mt - $at) . '</span>' : '<span class="text-success">atingida</span>') : '—' ?></td></tr>
                        <?php endforeach; endforeach; ?>
                        <?php if (!$tem): ?><tr><td colspan="6" class="text-muted p-3">Sem valores cadastrados. O método EIEAP (perguntas e pesos) depende da Gerdau (ponto 7).</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong>Respostas abertas</strong> <span class="small text-muted">(P8 aprendizados · P10 sugestões)</span></div>
                <div class="card-body small" style="max-height: 320px; overflow: auto">
                    <?php foreach (['aprendizado' => 'O que aprenderam', 'sugestao' => 'Sugestões de tema e melhoria'] as $k => $tit): ?>
                        <div class="fw-semibold mt-1"><?= $tit ?> (<?= count($d['abertas'][$k]) ?>)</div>
                        <?php foreach (array_slice($d['abertas'][$k], 0, 40) as $x): ?><div class="border-bottom py-1">“<?= html_escape($x['texto']) ?>” <span class="text-muted">— <?= html_escape($x['projeto']) ?></span></div><?php endforeach; ?>
                        <?php if (!$d['abertas'][$k]): ?><div class="text-muted">Nenhuma ainda.</div><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var proj = <?= json_encode(array_map(function ($p) { return ['n' => $p['projeto']->nome, 'icc' => $p['icc'], 'ip' => $p['ip'], 'is' => $p['is'], 'iia' => $p['iia'], 'ie' => $p['ie']]; }, $d['projetos']), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    var cores = {ICC: '#23345F', IP: '#5bc0de', IS: '#8fae5d', IIA: '#f0ad4e', IE: '#d9534f'};
    new Chart(document.getElementById('gProjetos'), {
        type: 'bar', data: { labels: proj.map(function (p) { return p.n; }),
            datasets: ['ICC', 'IP', 'IS', 'IIA', 'IE'].map(function (k) { return { label: k, backgroundColor: cores[k], data: proj.map(function (p) { return p[k.toLowerCase()]; }) }; }) },
        options: { indexAxis: 'y', animation: false, scales: { x: { min: 0, max: 100 } }, plugins: { legend: { position: 'bottom' } } }
    });
    var tri = <?= json_encode($d['trimestral']) ?>, lab = [], prev = [], real = [];
    Object.keys(tri).forEach(function (a) { [1, 2, 3, 4].forEach(function (t) { var x = (tri[a] || {})[t] || {}; lab.push('A' + a + ' T' + t); prev.push(x.previstas || 0); real.push(x.realizadas || 0); }); });
    new Chart(document.getElementById('gCrono'), { type: 'bar', data: { labels: lab, datasets: [{ label: 'Previstas', backgroundColor: '#ced4da', data: prev }, { label: 'Realizadas', backgroundColor: '#8fae5d', data: real }] },
        options: { animation: false, plugins: { legend: { position: 'bottom' } } } });
})();
</script>
