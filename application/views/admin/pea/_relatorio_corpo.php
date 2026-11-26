<?php defined('BASEPATH') OR exit('No direct script access allowed');
/* Corpo do relatório do PEA (tela e impressão). $r: relatório; $regras: versão usada. */
if (!function_exists('pea_num')) {
    function pea_num($v, $casas = 1) { return $v === NULL || $v === '' ? '—' : number_format((float) $v, $casas, ',', '.'); }
}
$d = $r->dados;
$t = $r->textos;
$inds = ['ICC' => 'icc', 'IP' => 'ip', 'IS' => 'is', 'IIA' => 'iia', 'IE' => 'ie'];
$nome_ind = ['ICC' => 'Cumprimento do Cronograma', 'IP' => 'Participação', 'IS' => 'Satisfação', 'IIA' => 'Impacto da Atividade', 'IE' => 'Execução'];
$pub = ['interno' => 'Interno', 'externo_comunidade' => 'Externo — comunidades', 'externo_escolar' => 'Externo — escolar'];
$texto = function ($k, $titulo) use ($t) { $v = trim((string) ($t[$k] ?? '')); return $v === '' ? '' : '<h3 class="h6 mt-3">' . $titulo . '</h3><div style="white-space: pre-wrap">' . html_escape($v) . '</div>'; };
?>
<div class="small text-muted mb-2"><?= html_escape($d['ciclo']['nome'] ?? '') ?><?= !empty($d['periodo']) ? ' · período ' . date('d/m/Y', strtotime($d['periodo'][0])) . ' a ' . date('d/m/Y', strtotime($d['periodo'][1])) : '' ?> · regras de ponderação v<?= (int) $r->regra_versao ?> · gerado em <?= date('d/m/Y H:i', strtotime($r->criado_em)) ?></div>
<?= $texto('apresentacao', 'Apresentação') ?>

<?php if ($r->tipo === 'ficha'): $a = $d['acao']; $i = $d['indicadores']; ?>
    <h3 class="h6 mt-3">Dados da ação</h3>
    <table class="table table-sm table-bordered small"><tbody>
        <tr><th style="width: 30%">Projeto</th><td><?= html_escape($a['projeto']) ?> (<?= $pub[$a['publico']] ?? '' ?>)</td></tr>
        <tr><th>Local</th><td><?= html_escape(trim(($a['unidade_nome'] ?? '') . ' ' . ($a['comunidade'] ? '· ' . $a['comunidade'] : '') . ' ' . ($a['escola'] ? '· ' . $a['escola'] : '') . ' ' . ($a['local'] ? '· ' . $a['local'] : ''))) ?></td></tr>
        <tr><th>Linha temática</th><td><?= html_escape($a['linha'] ?: '—') ?></td></tr>
        <tr><th>Previsto / realizado</th><td><?= $a['data_prevista'] ? date('d/m/Y', strtotime($a['data_prevista'])) . ' (ano ' . (int) $a['ano_previsto'] . ', ' . (int) $a['trimestre_previsto'] . 'º tri)' : '—' ?> / <?= $a['data_realizacao'] ? date('d/m/Y', strtotime($a['data_realizacao'])) : '—' ?></td></tr>
        <tr><th>Participantes · avaliações</th><td><?= (int) $i['participantes'] ?> · <?= (int) $i['respondentes'] ?> (<?= pea_num($i['adesao']) ?>% de adesão)</td></tr>
        <tr><th>Evidências</th><td><?= $d['evidencias'] ? html_escape(implode('; ', array_map(function ($e) { return $e['nome']; }, $d['evidencias']))) : 'nenhuma' ?></td></tr>
        <?php if (!empty($a['justificativa'])): ?><tr><th>Justificativa</th><td><?= nl2br(html_escape($a['justificativa'])) ?></td></tr><?php endif; ?>
    </tbody></table>
    <table class="table table-sm table-bordered small text-center"><thead class="table-light"><tr><?php foreach ($inds as $n => $k): ?><th><?= $n ?></th><?php endforeach; ?></tr></thead>
        <tbody><tr><?php foreach ($inds as $n => $k): ?><td><strong><?= pea_num($i[$k] ?? NULL) ?></strong><br><span class="text-muted"><?= html_escape(Pea_motor::classificar($n, $i[$k] ?? NULL, $regras)) ?></span></td><?php endforeach; ?></tr></tbody></table>
    <h3 class="h6">Satisfação por item (% satisfeitos)</h3>
    <p class="small">Conteúdo <?= pea_num($i['is_itens'][2] ?? NULL) ?> · Condução <?= pea_num($i['is_itens'][3] ?? NULL) ?> · Relação com o dia a dia <?= pea_num($i['is_itens'][4] ?? NULL) ?> · Organização <?= pea_num($i['is_itens'][5] ?? NULL) ?></p>
    <?php foreach (['aprendizado' => 'O que os participantes aprenderam', 'sugestao' => 'Sugestões'] as $k => $tit): if (empty($i['abertas'][$k])) { continue; } ?>
        <h3 class="h6"><?= $tit ?></h3><ul class="small"><?php foreach (array_slice($i['abertas'][$k], 0, 30) as $x): ?><li><?= html_escape($x) ?></li><?php endforeach; ?></ul>
    <?php endforeach; ?>
<?php else: $p = $d['painel']; $tt = $p['total']; ?>
    <h3 class="h6 mt-3">Indicadores do período</h3>
    <table class="table table-sm table-bordered small text-center"><thead class="table-light"><tr><?php foreach ($inds as $n => $k): ?><th><?= $n ?> — <?= $nome_ind[$n] ?></th><?php endforeach; ?></tr></thead>
        <tbody><tr><?php foreach ($inds as $n => $k): ?><td><strong><?= pea_num($tt[$k]) ?></strong><br><span class="text-muted"><?= html_escape(Pea_motor::classificar($n, $tt[$k], $regras)) ?></span></td><?php endforeach; ?></tr></tbody></table>
    <p class="small text-muted"><?= (int) $p['n_acoes'] ?> ação(ões) · <?= (int) $p['participantes'] ?> participantes · <?= (int) $p['respondentes'] ?> avaliações.</p>
    <h3 class="h6">Indicadores por projeto</h3>
    <table class="table table-sm table-bordered small">
        <thead class="table-light"><tr><th>Projeto</th><th>Público</th><th class="text-end">Previstas</th><th class="text-end">Realizadas</th><th class="text-end">No prazo</th><?php foreach ($inds as $n => $k): ?><th class="text-end"><?= $n ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($p['projetos'] as $x): ?>
            <tr><td><?= html_escape($x['projeto']['nome']) ?></td><td><?= $pub[$x['projeto']['publico']] ?? '' ?></td><td class="text-end"><?= (int) $x['previstas'] ?></td><td class="text-end"><?= (int) $x['realizadas'] ?></td><td class="text-end"><?= (int) $x['no_prazo'] ?></td>
                <?php foreach ($inds as $n => $k): ?><td class="text-end"><?= pea_num($x[$k]) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (!empty($d['por_ano'])): ?>
        <h3 class="h6">IE e componentes por ano do ciclo</h3>
        <table class="table table-sm table-bordered small text-center"><thead class="table-light"><tr><th>Ano</th><?php foreach ($inds as $n => $k): ?><th><?= $n ?></th><?php endforeach; ?><th>Ações</th></tr></thead><tbody>
            <?php foreach ($d['por_ano'] as $ano => $v): ?><tr><td><?= (int) $ano ?></td><?php foreach ($inds as $n => $k): ?><td><?= pea_num($v[$k]) ?></td><?php endforeach; ?><td><?= (int) $v['n_acoes'] ?></td></tr><?php endforeach; ?>
        </tbody></table>
    <?php endif; ?>
    <h3 class="h6">Situação do cronograma</h3>
    <table class="table table-sm table-bordered small text-center"><thead class="table-light"><tr><th>Ano / trimestre</th><th>Previstas</th><th>Realizadas</th></tr></thead><tbody>
        <?php foreach ($p['trimestral'] as $ano => $tris): if (!empty($r->ano) && (int) $ano !== (int) $r->ano) { continue; } ksort($tris); foreach ($tris as $tri => $v): if ($r->semestre && !in_array((int) $tri, $r->semestre == 2 ? [3, 4] : [1, 2], TRUE)) { continue; } ?>
            <tr><td>Ano <?= (int) $ano ?> · <?= (int) $tri ?>º</td><td><?= (int) ($v['previstas'] ?? 0) ?></td><td><?= (int) ($v['realizadas'] ?? 0) ?></td></tr>
        <?php endforeach; endforeach; ?>
    </tbody></table>
    <?php if (!empty($p['atrasadas'])): ?>
        <h3 class="h6">Ações atrasadas</h3><ul class="small"><?php foreach ($p['atrasadas'] as $a): ?><li><?= html_escape($a['titulo']) ?> (ano <?= (int) $a['ano_previsto'] ?>, <?= (int) $a['trimestre_previsto'] ?>º trimestre)</li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if ($r->tipo !== 'semestral' && !empty($d['eieap'])): ?>
        <h3 class="h6">Índices de percepção e comportamento ambiental (EIEAP)</h3>
        <table class="table table-sm table-bordered small"><thead class="table-light"><tr><th>Unidade</th><th>Índice</th><th class="text-end">Base 2021</th><th class="text-end">Atual</th><th class="text-end">Meta</th></tr></thead><tbody>
        <?php foreach ($d['eieap'] as $un => $v): foreach (($v['atual'] ?? []) + ($v['meta'] ?? []) as $no => $x): $at = $v['atual'][$no]['valor'] ?? NULL; ?>
            <tr><td><?= html_escape($un) ?></td><td><?= html_escape($no) ?></td><td class="text-end"><?= pea_num($v['base'][$no]['valor'] ?? NULL) ?></td><td class="text-end"><?= pea_num($at) ?> <span class="text-muted"><?= html_escape(Pea_motor::classificar('EIEAP', $at, $regras)) ?></span></td><td class="text-end"><?= pea_num($v['meta'][$no]['valor'] ?? NULL) ?></td></tr>
        <?php endforeach; endforeach; ?>
        </tbody></table>
    <?php endif; ?>
<?php endif; ?>
<?= $texto('analise', 'Análise') ?>
<?= $texto('desvios', 'Justificativas de desvios') ?>
<?= $texto('conclusao', 'Conclusão') ?>
