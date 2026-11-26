<?php defined('BASEPATH') OR exit('No direct script access allowed');
$st = ['planejada' => ['Planejada', 'secondary'], 'realizada' => ['Realizada', 'info text-dark'], 'encerrada' => ['Encerrada', 'success'], 'cancelada' => ['Cancelada', 'dark']][$a->status] ?? [$a->status, 'secondary'];
$encerrada = $a->status === 'encerrada';
$snap = $encerrada && $a->indicadores ? json_decode($a->indicadores, TRUE) : NULL;
$i = $snap ?: $ind;
$escala = ['muito_insatisfeito' => 'Muito insatisfeito', 'insatisfeito' => 'Insatisfeito', 'indiferente' => 'Indiferente', 'satisfeito' => 'Satisfeito', 'muito_satisfeito' => 'Muito satisfeito'];
$p7 = ['a' => '(a) conhecia e ampliou', 'b' => '(b) conhecia, nada novo', 'c' => '(c) não conhecia e agora conhece', 'd' => '(d) continua sem conhecer'];
$tipos_ev = ['lista_presenca' => 'Lista de presença', 'foto' => 'Foto', 'relatorio' => 'Relatório da ação', 'outro' => 'Outro'];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
            <a href="<?= base_url('pea/acoes') ?>" class="small"><i class="fas fa-arrow-left"></i> Ações</a>
            <h1 class="h4 mb-0"><?= html_escape($a->titulo) ?> <span class="badge bg-<?= $st[1] ?> align-middle fs-6"><?= $st[0] ?></span></h1>
            <div class="text-muted small"><?= html_escape($a->projeto) ?> · <?= Pea_model::PUBLICOS[$a->publico] ?? '' ?><?= $a->unidade ? ' · ' . html_escape($a->unidade_nome) : '' ?><?= $a->comunidade ? ' · ' . html_escape($a->comunidade) : '' ?><?= $a->escola ? ' · ' . html_escape($a->escola) : '' ?><?= $a->linha ? ' · Linha: ' . html_escape($a->linha) : '' ?></div>
        </div>
        <div class="d-flex gap-2">
            <?php if (!$encerrada): ?><a class="btn btn-outline-primary btn-sm" href="<?= base_url('pea/editar/' . (int) $a->id) ?>"><i class="fas fa-edit me-1"></i> Editar ficha</a><?php endif; ?>
            <?php if (in_array($a->avaliacao, [TRUE, 't'], TRUE)): ?><a class="btn btn-success btn-sm" target="_blank" href="<?= base_url('pea/qr/' . (int) $a->id) ?>"><i class="fas fa-qrcode me-1"></i> QR Code da avaliação</a><?php endif; ?>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <?php foreach (['ICC' => 'icc', 'IP' => 'ip', 'IS' => 'is', 'IIA' => 'iia', 'IE' => 'ie'] as $nome => $k): $val = $i[$k] ?? NULL; $niv = Pea_motor::nivel($nome, $val); ?>
            <div class="col"><div class="card pea-card border-<?= $niv ?>"><div class="card-body py-2">
                <div class="small text-muted"><?= $nome ?></div><div class="valor text-<?= $niv === 'secondary' ? 'muted' : $niv ?>"><?= pea_num($val) ?></div>
                <div class="small"><?= html_escape(Pea_motor::classificar($nome, $val, $regras) ?: ($k === 'ip' && $val === NULL ? 'Projeto sem faixa de IP' : '—')) ?></div>
            </div></div></div>
        <?php endforeach; ?>
    </div>
    <p class="small text-muted">
        <?= $snap ? 'Indicadores congelados no encerramento (regras v' . (int) $a->regra_versao . ').' : 'Indicadores calculados agora com as regras v' . (int) $versao . '; ficam congelados ao encerrar a ação.' ?>
        Previsto: <?= $a->data_prevista ? date('d/m/Y', strtotime($a->data_prevista)) . ' (ano ' . (int) $a->ano_previsto . ', ' . (int) $a->trimestre_previsto . 'º trimestre)' : 'sem data' ?> ·
        Realizada: <?= $a->data_realizacao ? date('d/m/Y', strtotime($a->data_realizacao)) . ($i['no_prazo'] ? ' (no prazo)' : ' (fora do trimestre previsto)') : 'não' ?>
        <?= $a->carga_horaria ? ' · ' . pea_num($a->carga_horaria) . ' h' : '' ?><?= $a->local ? ' · ' . html_escape($a->local) : '' ?>
    </p>
    <?php if ($a->justificativa): ?><div class="alert alert-light border small"><strong>Justificativa:</strong> <?= nl2br(html_escape($a->justificativa)) ?></div><?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card h-100"><div class="card-header"><strong>Adesão à avaliação</strong></div><div class="card-body">
                <div class="d-flex justify-content-between"><span>Participantes (lista)</span><strong><?= (int) $i['participantes'] ?></strong></div>
                <div class="d-flex justify-content-between"><span>Avaliações recebidas</span><strong><?= (int) $i['respondentes'] ?></strong></div>
                <?php if ($i['adesao'] !== NULL): ?><div class="progress mt-2" style="height: 12px"><div class="progress-bar <?= $i['adesao'] >= 80 ? 'bg-success' : 'bg-warning' ?>" style="width: <?= min(100, $i['adesao']) ?>%"></div></div>
                    <div class="small text-muted mt-1"><?= pea_num($i['adesao']) ?>% responderam. Quem não responde reduz o IS (o denominador é o total de participantes).</div><?php endif; ?>
                <?php if ($a->token): ?><div class="small mt-2">Link: <code class="text-break"><?= html_escape(base_url('responder/' . $a->token)) ?></code> <?= in_array($a->link_ativo, [TRUE, 't'], TRUE) ? '' : '<span class="badge bg-secondary">desativado</span>' ?></div><?php endif; ?>
                <?php if ($app): ?>
                    <?= form_open('pea/vincular_app/' . (int) $a->id, ['class' => 'mt-2']) ?>
                        <div class="small"><strong><?= count($app) ?> avaliação(ões) feitas pelo aplicativo</strong> no dia da ação ainda não ligadas a nenhuma ação:</div>
                        <?php foreach ($app as $r): ?><div class="form-check small"><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $r->id ?>" checked id="app<?= (int) $r->id ?>"><label class="form-check-label" for="app<?= (int) $r->id ?>">#<?= (int) $r->id ?> · <?= date('H:i', strtotime($r->completed_at)) ?> · <?= html_escape($r->aplicador ?: '—') ?></label></div><?php endforeach; ?>
                        <button class="btn btn-sm btn-outline-primary mt-1">Ligar à ação</button>
                    <?= form_close() ?>
                <?php endif; ?>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100"><div class="card-header"><strong>Satisfação</strong></div><div class="card-body small">
                <div class="fw-semibold">Geral (P6)</div>
                <?php $t6 = array_sum($i['is_respostas']); foreach ($escala as $k => $n): $c = (int) ($i['is_respostas'][$k] ?? 0); ?>
                    <div class="d-flex justify-content-between"><span><?= $n ?></span><span><?= $c ?><?= $t6 ? ' (' . pea_num($c / $t6 * 100) . '%)' : '' ?></span></div>
                <?php endforeach; ?>
                <div class="fw-semibold mt-2">Por item (% satisfeitos)</div>
                <?php foreach ([2 => 'Conteúdo', 3 => 'Condução', 4 => 'Relação com o dia a dia', 5 => 'Organização'] as $q => $n): ?>
                    <div class="d-flex justify-content-between"><span><?= $n ?></span><span><?= pea_num($i['is_itens'][$q] ?? NULL) ?></span></div>
                <?php endforeach; ?>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100"><div class="card-header"><strong>Impacto (P7) e prática (P9)</strong></div><div class="card-body small">
                <?php $t7 = array_sum($i['iia_respostas']); foreach ($p7 as $k => $n): $c = (int) ($i['iia_respostas'][$k] ?? 0); ?>
                    <div class="d-flex justify-content-between"><span><?= $n ?></span><span><?= $c ?><?= $t7 ? ' (' . pea_num($c / $t7 * 100) . '%)' : '' ?></span></div>
                <?php endforeach; ?>
                <div class="fw-semibold mt-2">Vai colocar em prática</div>
                <?php foreach (['trabalho' => 'No trabalho', 'casa_comunidade' => 'Em casa/comunidade', 'ambos' => 'Nos dois', 'talvez' => 'Talvez', 'nao' => 'Não'] as $k => $n): ?>
                    <div class="d-flex justify-content-between"><span><?= $n ?></span><span><?= (int) ($i['pratica'][$k] ?? 0) ?></span></div>
                <?php endforeach; ?>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header"><strong>Evidências</strong></div><div class="card-body small">
                <?php foreach ($evidencias as $e): ?>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span><span class="badge bg-light text-dark border"><?= $tipos_ev[$e->tipo] ?? $e->tipo ?></span> <a href="<?= base_url('pea/baixar/' . (int) $e->id) ?>"><?= html_escape($e->nome_original) ?></a> <span class="text-muted"><?= date('d/m/Y', strtotime($e->criado_em)) ?></span></span>
                        <?php if (!$encerrada): ?><?= form_open('pea/evidencia_excluir/' . (int) $e->id, ['class' => 'd-inline']) ?><button class="btn btn-sm btn-link text-danger p-0" onclick="return confirm('Remover a evidência?')"><i class="fas fa-times"></i></button><?= form_close() ?><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$evidencias): ?><p class="text-muted">Nenhuma evidência anexada.</p><?php endif; ?>
                <?= form_open_multipart('pea/evidencia/' . (int) $a->id, ['class' => 'mt-2 d-flex gap-2']) ?>
                    <select name="tipo" class="form-select form-select-sm" style="max-width: 170px"><?php foreach ($tipos_ev as $k => $n): ?><option value="<?= $k ?>"><?= $n ?></option><?php endforeach; ?></select>
                    <input type="file" name="arquivo" class="form-control form-control-sm" required>
                    <button class="btn btn-sm btn-primary">Anexar</button>
                <?= form_close() ?>
                <div class="text-muted mt-1">Os arquivos ficam fora do acesso público e cada download entra na trilha de auditoria.</div>
            </div></div>
        </div>
        <div class="col-lg-6" id="presenca">
            <div class="card h-100"><div class="card-header"><strong>Lista de presença</strong> <span class="small text-muted">(identificada, separada das avaliações anônimas)</span></div><div class="card-body small">
                <div style="max-height: 220px; overflow: auto">
                <?php foreach ($presencas as $p): ?>
                    <div class="d-flex justify-content-between border-bottom py-1"><span><?= html_escape($p->nome) ?> <span class="text-muted"><?= $p->cpf ? '· CPF ***' . html_escape(substr($p->cpf, -4)) : '' ?><?= $p->matricula ? ' · ' . html_escape($p->matricula) : '' ?><?= $p->empresa ? ' · ' . html_escape($p->empresa) : '' ?></span>
                        <?= $p->origem === 'bancodoc' ? '<span class="badge bg-info text-dark">BancoDOC</span>' : '' ?></span>
                        <?php if (!$encerrada): ?><?= form_open('pea/presenca_excluir/' . (int) $p->id, ['class' => 'd-inline']) ?><button class="btn btn-sm btn-link text-danger p-0"><i class="fas fa-times"></i></button><?= form_close() ?><?php endif; ?></div>
                <?php endforeach; ?>
                <?php if (!$presencas): ?><p class="text-muted">Ninguém registrado. Você pode anexar a lista em papel como evidência e informar só o número de participantes na ficha.</p><?php endif; ?>
                </div>
                <?php if (!$encerrada): ?>
                <?= form_open('pea/presenca/' . (int) $a->id, ['class' => 'row g-1 mt-2']) ?>
                    <div class="col-4"><input name="cpf" class="form-control form-control-sm" placeholder="CPF"></div>
                    <div class="col-3"><input name="matricula" class="form-control form-control-sm" placeholder="Matrícula"></div>
                    <div class="col-5"><input name="nome" class="form-control form-control-sm" placeholder="Nome (se não estiver na base)"></div>
                    <div class="col-8"><input name="empresa" class="form-control form-control-sm" placeholder="Empresa (opcional)"></div>
                    <div class="col-4"><button class="btn btn-sm btn-primary w-100">Registrar</button></div>
                <?= form_close() ?>
                <div class="text-muted mt-1">Com CPF ou matrícula da base do BancoDOC, o nome e a empresa vêm sozinhos.</div>
                <?php endif; ?>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><strong>O que aprenderam (P8)</strong></div><div class="card-body small" style="max-height: 220px; overflow: auto">
            <?php foreach ($i['abertas']['aprendizado'] as $t): ?><div class="border-bottom py-1">“<?= html_escape($t) ?>”</div><?php endforeach; ?><?php if (!$i['abertas']['aprendizado']): ?><span class="text-muted">Nenhuma resposta.</span><?php endif; ?></div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><strong>Sugestões (P10)</strong></div><div class="card-body small" style="max-height: 220px; overflow: auto">
            <?php foreach ($i['abertas']['sugestao'] as $t): ?><div class="border-bottom py-1">“<?= html_escape($t) ?>”</div><?php endforeach; ?><?php if (!$i['abertas']['sugestao']): ?><span class="text-muted">Nenhuma resposta.</span><?php endif; ?></div></div></div>
    </div>

    <div class="card mb-4"><div class="card-body">
        <?php if ($encerrada): ?>
            <p class="mb-2">Encerrada em <?= date('d/m/Y H:i', strtotime($a->encerrada_em)) ?>.</p>
            <?= form_open('pea/encerrar/' . (int) $a->id, ['class' => 'd-inline']) ?><input type="hidden" name="reabrir" value="1"><button class="btn btn-outline-secondary btn-sm" onclick="return confirm('Reabrir a ação? Os indicadores voltam a ser recalculados.')">Reabrir</button><?= form_close() ?>
        <?php elseif ($a->status === 'cancelada'): ?>
            <p class="mb-0 text-muted">Ação cancelada: conta como prevista e não realizada no ICC.</p>
        <?php else: ?>
            <?= form_open('pea/encerrar/' . (int) $a->id) ?>
                <p class="mb-1"><strong>Encerrar a ação</strong>: congela os indicadores com as regras vigentes e desativa o QR Code.</p>
                <?php if ($a->data_realizacao && !$i['no_prazo']): ?>
                    <label class="form-label small text-danger mb-0">A ação saiu do trimestre previsto (ICC abaixo de 50): justificativa obrigatória</label>
                    <textarea name="justificativa" class="form-control mb-2" rows="2" required><?= html_escape($a->justificativa) ?></textarea>
                <?php endif; ?>
                <button class="btn btn-success" <?= $a->data_realizacao ? '' : 'disabled title="Informe a data de realização na ficha"' ?>><i class="fas fa-lock me-1"></i> Encerrar e calcular</button>
            <?= form_close() ?>
        <?php endif; ?>
    </div></div>
</div>
