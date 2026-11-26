<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $status_txt = ['active' => ['Ativo no app', 'success'], 'paused' => ['Pausado', 'secondary'], 'inactive' => ['Inativo', 'dark']]; ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0">Formulários estruturados</h1>
            <p class="text-muted mb-0">Questionários com domicílio e moradores, questões repetidas por pessoa, campos calculados e pulos.
                Os questionários comuns continuam em <a href="<?= base_url('questionnaires') ?>">Questionários</a>.</p>
        </div>
        <div class="text-nowrap">
            <a href="<?= base_url('formularios-estruturados/conjuntos') ?>" class="btn btn-outline-secondary"><i class="fas fa-layer-group me-1"></i> Conjuntos e temas</a>
            <a href="<?= base_url('formularios-estruturados/entrevistados') ?>" class="btn btn-outline-secondary"><i class="fas fa-id-card me-1"></i> Entrevistados</a>
            <a href="<?= base_url('formularios-estruturados/novo') ?>" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Novo formulário</a>
        </div>
    </div>

    <?php if ($this->session->flashdata('success')): ?><div class="alert alert-success"><?= html_escape($this->session->flashdata('success')) ?></div><?php endif; ?>
    <?php if ($this->session->flashdata('error')): ?><div class="alert alert-danger"><?= html_escape($this->session->flashdata('error')) ?></div><?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <?php if (!$formularios): ?>
                <p class="p-4 mb-0 text-muted">Nenhum formulário estruturado ainda. Crie um em branco ou a partir de um modelo.</p>
            <?php else: ?>
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Formulário</th><th>Projeto</th><th>Versão publicada</th><th>Status</th><th class="text-end">Entrevistas</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($formularios as $f): $s = $status_txt[$f->status] ?? [$f->status, 'light']; ?>
                    <tr>
                        <td><a href="<?= base_url('formularios-estruturados/editar/' . $f->id) ?>" class="fw-semibold"><?= html_escape($f->title) ?></a></td>
                        <td><?= html_escape($f->project_name ?: '—') ?></td>
                        <td><?= $f->estrutura_versao ? 'v' . (int) $f->estrutura_versao : '<span class="text-muted">rascunho</span>' ?></td>
                        <td><span class="badge bg-<?= $s[1] ?>"><?= $s[0] ?></span></td>
                        <td class="text-end">
                            <?= (int) $f->entrevistas ?>
                            <?php if ((int) $f->pendentes): ?><span class="badge bg-warning text-dark ms-1" title="Na fila de revisão"><?= (int) $f->pendentes ?> a revisar</span><?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= base_url('formularios-estruturados/editar/' . $f->id) ?>"><i class="fas fa-edit"></i> Editar</a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('formularios-estruturados/previa/' . $f->id) ?>"><i class="fas fa-eye"></i> Prévia</a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('formularios-estruturados/entrevistas/' . $f->id) ?>"><i class="fas fa-list"></i> Entrevistas</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
