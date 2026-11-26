<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="container-fluid">
    <h1 class="h3 mb-0">Cruzamento de variáveis</h1>
    <p class="text-muted">Escolha um questionário e cruze duas perguntas (ou uma pergunta com o mês, o entrevistador ou o local), com percentuais, média, mediana, teste qui-quadrado e gráfico.<?= $cliente ? ' Células com poucas respostas ficam ocultas para proteger os entrevistados.' : '' ?></p>
    <?php if (!$pronto && !$cliente): ?><div class="alert alert-warning small">Para salvar análises, rode <code>sql/cruzamento_migration.sql</code> no servidor.</div><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong>Questionários com respostas</strong></div>
                <div class="list-group list-group-flush">
                    <?php if (!$questionarios): ?><div class="list-group-item text-muted">Nenhum questionário com respostas<?= $cliente ? ' nos seus projetos' : '' ?>.</div><?php endif; ?>
                    <?php foreach ($questionarios as $q): ?>
                        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?= base_url('cruzamentos/q/' . (int) $q->id) ?>">
                            <span><?= html_escape($q->title) ?><br><small class="text-muted"><?= html_escape($q->projeto ?: 'Sem projeto') ?><?= $q->formato === 'estruturado' ? ' · estruturado' : '' ?></small></span>
                            <span class="badge bg-secondary rounded-pill"><?= number_format((int) $q->n, 0, ',', '.') ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong><?= $cliente ? 'Análises disponíveis' : 'Análises salvas' ?></strong></div>
                <div class="list-group list-group-flush">
                    <?php if (!$salvas): ?><div class="list-group-item text-muted">Nenhuma análise <?= $cliente ? 'liberada' : 'salva' ?> ainda.</div><?php endif; ?>
                    <?php foreach ($salvas as $s): $lib = in_array($s->liberado_cliente, [TRUE, 't'], TRUE); ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="<?= base_url('cruzamentos/q/' . (int) $s->questionnaire_id . '?salva=' . (int) $s->id) ?>">
                                <?= html_escape($s->titulo) ?><br><small class="text-muted"><?= html_escape($s->questionario) ?> · <?= date('d/m/Y', strtotime($s->atualizado_em)) ?><?= $cliente ? '' : ' · ' . html_escape($s->autor ?: '') ?></small>
                            </a>
                            <?php if (!$cliente): ?>
                            <span class="text-nowrap">
                                <?= form_open('cruzamentos/liberar/' . (int) $s->id, ['class' => 'd-inline']) ?><button class="btn btn-sm <?= $lib ? 'btn-success' : 'btn-outline-secondary' ?>" title="<?= $lib ? 'Liberada ao cliente (clique para retirar)' : 'Liberar ao cliente' ?>"><i class="fas fa-<?= $lib ? 'eye' : 'eye-slash' ?>"></i></button><?= form_close() ?>
                                <?= form_open('cruzamentos/excluir/' . (int) $s->id, ['class' => 'd-inline']) ?><button class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir a análise?')"><i class="fas fa-trash"></i></button><?= form_close() ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
