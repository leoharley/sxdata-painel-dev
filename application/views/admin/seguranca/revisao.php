<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="container-fluid">
    <a href="<?= base_url('controle') ?>" class="small"><i class="fas fa-arrow-left"></i> Controle e segurança</a>
    <div class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0">Fila de revisão</h1>
            <p class="text-muted mb-0">O servidor confere cada envio do app. O que tiver inconsistência é gravado do mesmo jeito e aparece aqui.</p>
        </div>
        <form class="d-flex gap-2" method="get">
            <select name="q" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todos os questionários</option>
                <?php foreach ($questionarios as $qq): ?>
                    <option value="<?= (int) $qq->id ?>" <?= $qid === (int) $qq->id ? 'selected' : '' ?>><?= html_escape($qq->title) ?> (<?= (int) $qq->n ?>)</option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>A revisar</option>
                <option value="revisada" <?= $status === 'revisada' ? 'selected' : '' ?>>Já revisadas</option>
            </select>
        </form>
    </div>
    <div class="card">
        <div class="card-body p-0">
        <?php if (!$lista): ?>
            <p class="p-4 mb-0 text-muted"><?= $status === 'pendente' ? 'Nenhuma entrevista aguardando revisão.' : 'Nenhuma entrevista revisada ainda.' ?></p>
        <?php else: ?>
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>#</th><th>Questionário</th><th>Aplicador</th><th>Concluída</th><th style="width:40%">O que o servidor encontrou</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($lista as $r):
                    $erros = Seguranca_model::mensagens_revisao($r->revisao_erros);
                    $ver = $r->formato === 'estruturado' ? 'formularios-estruturados/entrevista/' . $r->id : 'responses/view/' . $r->id; ?>
                    <tr>
                        <td><?= (int) $r->id ?></td>
                        <td><?= html_escape($r->title) ?><?= $r->formato === 'estruturado' ? ' <span class="badge bg-light text-dark border">estruturado</span>' : '' ?></td>
                        <td><?= html_escape($r->aplicador ?: '—') ?></td>
                        <td class="text-nowrap"><?= $r->completed_at ? date('d/m/Y H:i', strtotime($r->completed_at)) : '—' ?></td>
                        <td class="small">
                            <?php $mostrar = array_slice($erros, 0, 4); foreach ($mostrar as $e): ?>
                                <div>• <?= html_escape(is_array($e) ? ($e['mensagem'] ?? json_encode($e, JSON_UNESCAPED_UNICODE)) : $e) ?></div>
                            <?php endforeach; ?>
                            <?php if (count($erros) > 4): ?><div class="text-muted">+ <?= count($erros) - 4 ?> outro(s)</div><?php endif; ?>
                            <?php if ($status === 'revisada'): ?><div class="text-success mt-1">Revisada por <?= html_escape($r->revisor ?: '—') ?> em <?= $r->revisado_em ? date('d/m/Y H:i', strtotime($r->revisado_em)) : '—' ?><?= $r->revisao_nota ? ': ' . html_escape($r->revisao_nota) : '' ?></div><?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= base_url($ver) ?>">Ver</a>
                            <?= form_open('controle/revisar/' . (int) $r->id, ['class' => 'd-inline']) ?>
                                <input type="hidden" name="voltar" value="<?= html_escape('controle/revisao?status=' . $status . ($qid ? '&q=' . $qid : '')) ?>">
                                <?php if ($status === 'pendente'): ?>
                                    <input type="hidden" name="nota" value="">
                                    <button class="btn btn-sm btn-success" onclick="var n = prompt('Observação da revisão (opcional):', ''); if (n === null) return false; this.form.nota.value = n;">Revisada</button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" name="reabrir" value="1">Reabrir</button>
                                <?php endif; ?>
                            <?= form_close() ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
    </div>
</div>
