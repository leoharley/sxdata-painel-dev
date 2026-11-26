<?php defined('BASEPATH') OR exit('No direct script access allowed');
$admin = $this->session->userdata('admin_role') === 'administrador';
$dis = $admin ? '' : 'disabled';
$uns = ['UOB', 'MVL', 'MMB', 'Gerdau'];
$filhos = [];
foreach ($e['nos'] as $no) { $filhos[$no['pai'] ?? ''][] = $no; }
$linhas = [];
$walk = function ($pai, $nivel) use (&$walk, &$linhas, $filhos) { foreach ($filhos[$pai] ?? [] as $n) { $linhas[] = [$n, $nivel]; $walk($n['codigo'], $nivel + 1); } };
$walk('', 0);
?>
<div class="container-fluid">
    <p class="text-muted">Árvore de indicadores do método EIEAP: as perguntas formam os indicadores; os indicadores, ponderados, formam os índices de Percepção e de Comportamento Ambiental; e estes formam o Indicador de Educação Ambiental. Cada nó tem peso, linha de base de 2021, valor atual e meta por unidade. Carregado com os índices das Tabelas 03 e 04 do PEA (pesos iguais). <strong>O questionário e os pesos do método dependem da Gerdau ou da consultoria (ponto 7 da seção 20).</strong> Quando um nó não tem valor medido, o painel usa a média ponderada dos filhos.</p>
    <?= form_open('pea/salvar_eieap') ?>
    <div class="card mb-3"><div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0 small align-middle">
            <thead class="table-light"><tr><th rowspan="2">Nó</th><th rowspan="2" style="width:70px">Peso</th><?php foreach ($uns as $u): ?><th colspan="3" class="text-center"><?= $u ?></th><?php endforeach; ?></tr>
                <tr><?php foreach ($uns as $u): ?><th class="text-center">Base 2021</th><th class="text-center">Atual</th><th class="text-center">Meta</th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach ($linhas as $l): list($no, $niv) = $l; ?>
                <tr><td style="padding-left: <?= 6 + $niv * 18 ?>px"><?= $niv ? '└ ' : '' ?><?= html_escape($no['nome']) ?></td>
                    <td><input name="peso[<?= html_escape($no['codigo']) ?>]" class="form-control form-control-sm" value="<?= html_escape((float) $no['peso']) ?>" <?= $dis ?>></td>
                    <?php foreach ($uns as $u): $v = $e['unidades'][$u] ?? ['base' => [], 'atual' => [], 'meta' => [], 'calculado' => []]; ?>
                        <?php foreach (['base', 'atual', 'meta'] as $t): $x = $v[$t][$no['codigo']]['valor'] ?? NULL; $calc = $t === 'atual' && $x === NULL ? ($v['calculado'][$no['codigo']] ?? NULL) : NULL; ?>
                            <td class="p-0"><input name="v[<?= $u ?>][<?= html_escape($no['codigo']) ?>][<?= $t ?>]" class="form-control form-control-sm border-0 text-center" style="min-width:58px"
                                value="<?= $x !== NULL ? html_escape($x) : '' ?>" placeholder="<?= $calc !== NULL ? pea_num($calc) : '' ?>" title="<?= $calc !== NULL ? 'Calculado pela árvore: ' . pea_num($calc) . ' (' . html_escape(Pea_motor::classificar('EIEAP', $calc, $regras)) . ')' : html_escape($x !== NULL ? Pea_motor::classificar('EIEAP', $x, $regras) : '') ?>" <?= $dis ?>></td>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div></div>
    <?php if ($admin): ?>
    <div class="row g-2 align-items-end mb-4">
        <div class="col-md-2"><label class="form-label small mb-0">Ano dos valores "atual" e "meta"</label><input type="number" name="ano" class="form-control form-control-sm" value="<?= date('Y') ?>"></div>
        <div class="col-md-3"><label class="form-label small mb-0">Novo nó (opcional): nome</label><input name="novo_nome" class="form-control form-control-sm" placeholder="Ex.: Conhecimento sobre resíduos"></div>
        <div class="col-md-2"><label class="form-label small mb-0">Código</label><input name="novo_codigo" class="form-control form-control-sm" placeholder="automático"></div>
        <div class="col-md-3"><label class="form-label small mb-0">Pai</label><select name="novo_pai" class="form-select form-select-sm"><option value="">(raiz)</option><?php foreach ($e['nos'] as $no): ?><option value="<?= html_escape($no['codigo']) ?>"><?= html_escape($no['nome']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-1"><label class="form-label small mb-0">Peso</label><input name="novo_peso" class="form-control form-control-sm" value="1"></div>
        <div class="col-md-1"><button class="btn btn-primary btn-sm w-100">Salvar</button></div>
    </div>
    <?php endif; ?>
    <?= form_close() ?>
</div>
