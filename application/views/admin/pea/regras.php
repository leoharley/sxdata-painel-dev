<?php defined('BASEPATH') OR exit('No direct script access allowed');
$admin = $this->session->userdata('admin_role') === 'administrador';
$dis = $admin ? '' : 'disabled';
$r = $regras;
$escala = ['muito_insatisfeito' => 'Muito insatisfeito', 'insatisfeito' => 'Insatisfeito', 'indiferente' => 'Indiferente', 'satisfeito' => 'Satisfeito', 'muito_satisfeito' => 'Muito satisfeito'];
$n = function ($v) { return html_escape(rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.')); };
?>
<div class="container-fluid" style="max-width: 1150px">
    <p class="text-muted">As fórmulas do PEA, com os pesos e escolhas editáveis. Cada alteração vira uma <strong>versão nova</strong> (com data, responsável e motivo). Ações encerradas e relatórios já gerados guardam a versão com que foram calculados: um ajuste no meio do ciclo não muda números já enviados ao órgão ambiental. Versão vigente: <strong>v<?= (int) $versao ?></strong>.</p>
    <?= form_open('pea/salvar_regras') ?>
    <div class="row g-3">
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><strong>IS — Satisfação</strong> <span class="small text-muted">pergunta 6</span></div><div class="card-body small">
            <p class="mb-1">IS = (opções marcadas abaixo na P6) ÷ denominador × 100</p>
            <div class="mb-2"><?php foreach ($escala as $k => $l): ?><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_satisfeito[]" value="<?= $k ?>" id="s<?= $k ?>" <?= in_array($k, $r['is']['satisfeito'], TRUE) ? 'checked' : '' ?> <?= $dis ?>><label class="form-check-label" for="s<?= $k ?>"><?= $l ?></label></div><?php endforeach; ?></div>
            <label class="form-label mb-0">Denominador (ponto 1 da seção 20)</label>
            <select name="is_denominador" class="form-select form-select-sm" <?= $dis ?>>
                <option value="participantes" <?= $r['is']['denominador'] === 'participantes' ? 'selected' : '' ?>>Total de participantes da lista de presença (como no PEA)</option>
                <option value="respondentes" <?= $r['is']['denominador'] === 'respondentes' ? 'selected' : '' ?>>Total de quem respondeu</option>
            </select>
            <p class="text-muted mt-1 mb-0">As perguntas 2 a 5 aparecem como satisfação por item, com as mesmas opções.</p>
        </div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><strong>IIA — Impacto da atividade</strong> <span class="small text-muted">pergunta 7</span></div><div class="card-body small">
            <p class="mb-1">IIA = [<input name="iia_peso_a" class="form-control form-control-sm d-inline" style="width:60px" value="<?= $n($r['iia']['peso_a']) ?>" <?= $dis ?>>·a + <input name="iia_peso_c" class="form-control form-control-sm d-inline" style="width:60px" value="<?= $n($r['iia']['peso_c']) ?>" <?= $dis ?>>·c + <input name="iia_peso_d" class="form-control form-control-sm d-inline" style="width:60px" value="<?= $n($r['iia']['peso_d']) ?>" <?= $dis ?>>·(d − 1)] ÷ <input name="iia_divisor" class="form-control form-control-sm d-inline" style="width:60px" value="<?= $n($r['iia']['divisor']) ?>" <?= $dis ?>> × 100</p>
            <p class="text-muted mb-1">a, c e d são as proporções (0 a 1) de quem marcou (a) ampliou, (c) passou a conhecer e (d) continua sem conhecer. A opção (b) entra só no total.</p>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="iia_piso_zero" value="1" id="piso" <?= !empty($r['iia']['piso_zero']) ? 'checked' : '' ?> <?= $dis ?>><label class="form-check-label" for="piso">Piso em 0 (ponto 2: com todos em (b) a fórmula daria −33)</label></div>
        </div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><strong>ICC — Cumprimento do cronograma</strong></div><div class="card-body small">
            <p class="mb-1">ICC = ações realizadas no trimestre previsto ÷ ações previstas × 100 (ponto 4). Previstas = o maior entre o cronograma e as fichas abertas no período.</p>
            <label class="form-label mb-0">Tolerância (trimestres depois do previsto que ainda contam no prazo)</label>
            <input type="number" min="0" max="4" name="icc_tolerancia" class="form-control form-control-sm" style="width:100px" value="<?= (int) $r['icc']['tolerancia_trimestres'] ?>" <?= $dis ?>>
        </div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><strong>IP — Participação</strong> e <strong>IE — Execução</strong></div><div class="card-body small">
            <p class="mb-1">Valor do IP por faixa (as faixas de cada projeto ficam em Estrutura):
                baixa <input name="ip_baixa" class="form-control form-control-sm d-inline" style="width:55px" value="<?= $n($r['ip']['baixa']) ?>" <?= $dis ?>>
                média <input name="ip_media" class="form-control form-control-sm d-inline" style="width:55px" value="<?= $n($r['ip']['media']) ?>" <?= $dis ?>>
                alta <input name="ip_alta" class="form-control form-control-sm d-inline" style="width:55px" value="<?= $n($r['ip']['alta']) ?>" <?= $dis ?>>
                abaixo da faixa baixa <input name="ip_abaixo_da_baixa" class="form-control form-control-sm d-inline" style="width:55px" value="<?= $n($r['ip']['abaixo_da_baixa']) ?>" <?= $dis ?>> (ponto 3)</p>
            <p class="mb-0">IE = média ponderada, pesos:
                <?php foreach (['icc', 'ip', 'is', 'iia'] as $k): ?><?= strtoupper($k) ?> <input name="ie_<?= $k ?>" class="form-control form-control-sm d-inline" style="width:50px" value="<?= $n($r['ie']['pesos'][$k]) ?>" <?= $dis ?>> <?php endforeach; ?>
                <br><span class="text-muted">Componente sem dado (ex.: projeto sem faixa de IP) fica fora da média.</span></p>
        </div></div></div>
    </div>
    <?php if ($admin): ?>
    <div class="card mt-3"><div class="card-body">
        <label class="form-label small mb-0">Motivo da alteração (obrigatório; aparece no histórico e nos relatórios)</label>
        <div class="input-group"><input name="nota" class="form-control" maxlength="500" placeholder="Ex.: Gerdau pediu IS sobre respondentes (ofício de 10/01/2027)"><button class="btn btn-primary">Salvar como versão <?= (int) $versao + 1 ?></button></div>
    </div></div>
    <?php endif; ?>
    <?= form_close() ?>

    <div class="row g-3 mt-1">
        <div class="col-lg-6"><div class="card"><div class="card-header"><strong>Faixas de leitura</strong> <span class="small text-muted">(PEA, Tabelas 02, 10 e 12 a 14)</span></div><div class="card-body p-0">
            <table class="table table-sm mb-0 small"><tbody>
            <?php foreach ($r['faixas'] as $ind => $fx): foreach ($fx as $f): ?>
                <tr><td class="fw-semibold"><?= $ind ?></td><td><?= $f[0] !== NULL && $f[1] !== NULL && $f[0] == $f[1] ? '= ' . pea_num($f[0], 0) : (($f[0] !== NULL ? '≥ ' . pea_num($f[0], 0) : '') . ($f[0] !== NULL && $f[1] !== NULL ? ' e ' : '') . ($f[1] !== NULL ? '< ' . pea_num($f[1], 0) : '')) ?></td><td><?= html_escape($f[2]) ?></td></tr>
            <?php endforeach; endforeach; ?>
            </tbody></table>
        </div></div></div>
        <div class="col-lg-6"><div class="card"><div class="card-header"><strong>Histórico de versões</strong></div><div class="card-body p-0">
            <table class="table table-sm mb-0 small"><tbody>
            <?php foreach ($historico as $h): ?><tr><td class="fw-semibold">v<?= (int) $h->versao ?></td><td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($h->criado_em)) ?></td><td><?= html_escape($h->autor ?: '—') ?></td><td><?= html_escape($h->nota) ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div></div></div>
    </div>
</div>
