<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="container-fluid" style="max-width: 820px">
    <h1 class="h3 mb-3">Novo formulário estruturado</h1>
    <?php if ($this->session->flashdata('error')): ?><div class="alert alert-danger"><?= html_escape($this->session->flashdata('error')) ?></div><?php endif; ?>
    <form method="post" action="<?= base_url('formularios-estruturados/novo') ?>" class="card">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label fw-semibold">Título</label>
                <input type="text" name="title" class="form-control" required maxlength="300">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Descrição <span class="text-muted fw-normal">(opcional)</span></label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Projeto</label>
                <select name="project_id" class="form-select">
                    <option value="">Sem projeto</option>
                    <?php foreach ($projetos as $p): ?><option value="<?= (int) $p->id ?>"><?= html_escape($p->name) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2 fw-semibold">Começar a partir de</div>
            <div class="form-check mb-1">
                <input class="form-check-input" type="radio" name="modelo" id="m0" value="" checked>
                <label class="form-check-label" for="m0">Formulário em branco (com um exemplo de domicílio e moradores)</label>
            </div>
            <?php foreach ($modelos as $chave => $rotulo): ?>
            <div class="form-check mb-1">
                <input class="form-check-input" type="radio" name="modelo" id="m_<?= $chave ?>" value="<?= $chave ?>">
                <label class="form-check-label" for="m_<?= $chave ?>"><?= html_escape($rotulo) ?></label>
            </div>
            <?php endforeach; ?>
            <p class="text-muted small mt-3 mb-0">O formulário nasce pausado. Depois de revisar, publique uma versão e ative-o para que vá ao app.</p>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <a href="<?= base_url('formularios-estruturados') ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-primary">Criar e abrir o editor</button>
        </div>
    </form>
</div>
