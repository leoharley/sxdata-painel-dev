<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="<?= base_url('formularios-estruturados') ?>" class="small"><i class="fas fa-arrow-left"></i> Formulários estruturados</a>
            <h1 class="h3 mb-0"><?= html_escape($q->title) ?></h1>
            <p class="text-muted mb-0"><?= count($entrevistas) ?> entrevista(s)<?= count($entrevistas) >= 500 ? ' (mostrando as 500 mais recentes)' : '' ?></p>
        </div>
        <div>
            <a href="<?= base_url('formularios-estruturados/exportar/' . $q->id) ?>" class="btn btn-success"><i class="fas fa-file-excel me-1"></i> Exportar Excel</a>
            <a href="<?= base_url('formularios-estruturados/editar/' . $q->id) ?>" class="btn btn-outline-secondary">Editor</a>
        </div>
    </div>
    <?php if ($this->session->flashdata('error')): ?><div class="alert alert-danger"><?= html_escape($this->session->flashdata('error')) ?></div><?php endif; ?>
    <div class="card">
        <div class="card-body p-0">
            <?php if (!$entrevistas): ?>
                <p class="p-4 mb-0 text-muted">Nenhuma entrevista recebida ainda.</p>
            <?php else: ?>
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light"><tr><th>#</th><th>Concluída em</th><th>Aplicador</th><th class="text-end">Moradores</th><th>Revisão</th><th>Alertas</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($entrevistas as $e): $al = json_decode((string) $e->alertas, TRUE) ?: []; ?>
                    <tr>
                        <td><?= (int) $e->id ?></td>
                        <td><?= $e->completed_at ? date('d/m/Y H:i', strtotime($e->completed_at)) : '—' ?></td>
                        <td><?= html_escape($e->aplicador ?: '—') ?></td>
                        <td class="text-end"><?= (int) $e->moradores ?></td>
                        <td><?= $e->revisao_status === 'pendente' ? '<span class="badge bg-warning text-dark">A revisar</span>' : '<span class="badge bg-success">OK</span>' ?></td>
                        <td><?= $al ? '<span class="badge bg-info text-dark">' . count($al) . '</span>' : '' ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= base_url('formularios-estruturados/entrevista/' . $e->id) ?>">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
