<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="container-fluid">
    <a href="<?= base_url('formularios-estruturados') ?>" class="small"><i class="fas fa-arrow-left"></i> Formulários estruturados</a>
    <h1 class="h3">Entrevistados pré-cadastrados</h1>
    <p class="text-muted">Cadastro por projeto, para entrevistas repetidas com as mesmas pessoas. O app baixa os entrevistados dos projetos do aplicador e
        funciona sem sinal. No formulário: <code># entrevistado: opcional</code> (ou <code>obrigatorio</code>), etapas com <code>categoria:</code> ou
        <code>entrevistado:</code>, questões com <code>preencher: ent_nome</code> e condições com <code>ent_localidade</code>, <code>ent_categoria</code> e campos extras (<code>ent_telefone</code>...).</p>
    <?php if ($this->session->flashdata('success')): ?><div class="alert alert-success"><?= html_escape($this->session->flashdata('success')) ?></div><?php endif; ?>
    <?php if ($this->session->flashdata('error')): ?><div class="alert alert-danger"><?= html_escape($this->session->flashdata('error')) ?></div><?php endif; ?>

    <form class="row g-2 mb-3" method="get" action="">
        <div class="col-auto">
            <select class="form-select" onchange="location.href='<?= base_url('formularios-estruturados/entrevistados/') ?>' + this.value">
                <?php foreach ($projetos as $p): ?><option value="<?= (int) $p->id ?>" <?= (int) $p->id === $project_id ? 'selected' : '' ?>><?= html_escape($p->name) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><input class="form-control" name="q" value="<?= html_escape($busca) ?>" placeholder="Buscar por nome ou código"></div>
        <div class="col-auto"><button class="btn btn-outline-secondary">Buscar</button></div>
    </form>

    <?php if ($project_id): ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card"><div class="card-body p-0">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>Código</th><th>Nome</th><th>Categoria</th><th>Localidade</th><th>Outros campos</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$lista): ?><tr><td colspan="6" class="text-muted p-3">Nenhum entrevistado neste projeto.</td></tr><?php endif; ?>
                    <?php foreach ($lista as $e): $campos = json_decode((string) $e->campos, TRUE) ?: []; ?>
                        <tr>
                            <td><code><?= html_escape($e->codigo) ?></code></td>
                            <td><?= html_escape($e->nome) ?></td>
                            <td><?= html_escape($e->categoria ?: '—') ?></td>
                            <td><?= html_escape($e->localidade ?: '—') ?></td>
                            <td class="small"><?= html_escape(implode(' · ', array_map(function ($k, $v) { return "$k: $v"; }, array_keys($campos), $campos))) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-danger" href="<?= base_url('formularios-estruturados/entrevistado_excluir/' . $e->id) ?>" onclick="return confirm('Remover este entrevistado do app?')">×</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div></div>
            <p class="small text-muted mt-1">Mostrando até 2.000. Para alterar, cadastre de novo com o mesmo código (ou reimporte a planilha): o registro é atualizado.</p>
        </div>
        <div class="col-lg-4">
            <form class="card mb-3" method="post" enctype="multipart/form-data" action="<?= base_url('formularios-estruturados/entrevistados_importar/' . $project_id) ?>">
                <div class="card-header fw-semibold">Importar planilha</div>
                <div class="card-body">
                    <p class="small mb-2">CSV (; ou ,) ou XLSX com as colunas <b>codigo</b> e <b>nome</b> (obrigatórias), <b>categoria</b>, <b>localidade</b>, <b>latitude</b>,
                        <b>longitude</b>; qualquer outra coluna vira campo extra. <a href="<?= base_url('formularios-estruturados/entrevistados_modelo') ?>">Baixar modelo</a>.</p>
                    <input type="file" class="form-control form-control-sm mb-2" name="arquivo" accept=".csv,.xlsx,.xls" required>
                    <button class="btn btn-primary btn-sm">Importar</button>
                </div>
            </form>
            <form class="card" method="post" action="<?= base_url('formularios-estruturados/entrevistado_salvar/' . $project_id) ?>">
                <div class="card-header fw-semibold">Cadastrar (ou atualizar pelo código)</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-4"><input class="form-control form-control-sm" name="codigo" placeholder="Código" required></div>
                        <div class="col-8"><input class="form-control form-control-sm" name="nome" placeholder="Nome" required></div>
                        <div class="col-6"><input class="form-control form-control-sm" name="categoria" placeholder="Categoria"></div>
                        <div class="col-6"><input class="form-control form-control-sm" name="localidade" placeholder="Localidade"></div>
                        <div class="col-6"><input class="form-control form-control-sm" name="latitude" placeholder="Latitude"></div>
                        <div class="col-6"><input class="form-control form-control-sm" name="longitude" placeholder="Longitude"></div>
                        <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="col-5"><input class="form-control form-control-sm" name="campo_nome[]" placeholder="Campo extra"></div>
                        <div class="col-7"><input class="form-control form-control-sm" name="campo_valor[]" placeholder="Valor"></div>
                        <?php endfor; ?>
                    </div>
                    <button class="btn btn-primary btn-sm mt-2">Salvar</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
