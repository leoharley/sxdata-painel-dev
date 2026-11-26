<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="container-fluid">
    <a href="<?= base_url('formularios-estruturados') ?>" class="small"><i class="fas fa-arrow-left"></i> Formulários estruturados</a>
    <h1 class="h3">Biblioteca: conjuntos de perguntas e temas</h1>
    <p class="text-muted">Um <b>conjunto</b> é um bloco de perguntas reaproveitável (ex.: "Composição do domicílio", "Benfeitorias e bens"), com versões.
        Entra num formulário com <code>@usar codigo</code>; abre por resposta (<code>se:</code>), por tema (<code>tema:</code>) ou pelo entrevistado (<code>categoria:</code> / <code>entrevistado:</code>).
        Pode ser repetível (<code>@repetir</code>). Os entrevistados pré-cadastrados ficam em <a href="<?= base_url('formularios-estruturados/entrevistados') ?>">Entrevistados</a>.</p>
    <?php if ($this->session->flashdata('success')): ?><div class="alert alert-success"><?= html_escape($this->session->flashdata('success')) ?></div><?php endif; ?>
    <?php if ($this->session->flashdata('error')): ?><div class="alert alert-danger"><?= html_escape($this->session->flashdata('error')) ?></div><?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header fw-semibold">Conjuntos</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Conjunto</th><th>Código (para @usar)</th><th>Versão</th><th>Temas</th><th></th></tr></thead>
                        <tbody>
                        <?php if (!$conjuntos): ?><tr><td colspan="5" class="text-muted p-3">Nenhum conjunto ainda.</td></tr><?php endif; ?>
                        <?php foreach ($conjuntos as $c): ?>
                            <tr>
                                <td><a href="<?= base_url('formularios-estruturados/conjunto/' . $c->id) ?>" class="fw-semibold"><?= html_escape($c->nome) ?></a>
                                    <?php if ($c->descricao): ?><div class="small text-muted"><?= html_escape($c->descricao) ?></div><?php endif; ?></td>
                                <td><code>@usar <?= html_escape($c->codigo) ?></code></td>
                                <td><?= $c->versao_atual ? 'v' . (int) $c->versao_atual : '<span class="text-muted">rascunho</span>' ?></td>
                                <td class="small"><?= html_escape($c->temas ?: '—') ?></td>
                                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= base_url('formularios-estruturados/conjunto/' . $c->id) ?>">Editar</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mt-3" id="temas">
                <div class="card-header fw-semibold">Temas</div>
                <div class="card-body">
                    <p class="small text-muted mb-2">Declare no formulário os temas que o entrevistador pode escolher (<code># temas: Reassentamento, Saúde</code>)
                        e marque as etapas com <code>tema: Reassentamento</code>. Aqui fica o catálogo, com os conjuntos de cada tema.</p>
                    <?php foreach ($temas as $t): ?>
                    <form class="border rounded p-2 mb-2" method="post" action="<?= base_url('formularios-estruturados/tema_salvar') ?>">
                        <input type="hidden" name="id" value="<?= (int) $t->id ?>">
                        <div class="row g-2 align-items-start">
                            <div class="col-md-3"><input class="form-control form-control-sm fw-semibold" name="nome" value="<?= html_escape($t->nome) ?>"></div>
                            <div class="col-md-4"><input class="form-control form-control-sm" name="descricao" value="<?= html_escape($t->descricao) ?>" placeholder="Descrição"></div>
                            <div class="col-md-3"><select class="form-select form-select-sm" name="conjuntos[]" multiple size="3">
                                <?php $ids = array_map(function ($x) { return (int) $x->id; }, $t->conjuntos); ?>
                                <?php foreach ($conjuntos as $c): ?><option value="<?= (int) $c->id ?>" <?= in_array((int) $c->id, $ids, TRUE) ? 'selected' : '' ?>><?= html_escape($c->nome) ?></option><?php endforeach; ?>
                            </select></div>
                            <div class="col-md-2 text-end"><button class="btn btn-sm btn-outline-primary">Salvar</button>
                                <a class="btn btn-sm btn-outline-danger" href="<?= base_url('formularios-estruturados/tema_excluir/' . $t->id) ?>" onclick="return confirm('Excluir o tema?')">×</a></div>
                        </div>
                    </form>
                    <?php endforeach; ?>
                    <form class="border rounded p-2 bg-light" method="post" action="<?= base_url('formularios-estruturados/tema_salvar') ?>">
                        <div class="row g-2">
                            <div class="col-md-3"><input class="form-control form-control-sm" name="nome" placeholder="Novo tema" required></div>
                            <div class="col-md-4"><input class="form-control form-control-sm" name="descricao" placeholder="Descrição"></div>
                            <div class="col-md-3"><select class="form-select form-select-sm" name="conjuntos[]" multiple size="3">
                                <?php foreach ($conjuntos as $c): ?><option value="<?= (int) $c->id ?>"><?= html_escape($c->nome) ?></option><?php endforeach; ?>
                            </select></div>
                            <div class="col-md-2 text-end"><button class="btn btn-sm btn-primary">Adicionar</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <form class="card" method="post" action="<?= base_url('formularios-estruturados/conjunto_novo') ?>">
                <div class="card-header fw-semibold">Novo conjunto</div>
                <div class="card-body">
                    <div class="mb-2"><label class="form-label small mb-0">Nome</label><input class="form-control" name="nome" required placeholder="Ex.: Benfeitorias e bens"></div>
                    <div class="mb-2"><label class="form-label small mb-0">Código <span class="text-muted">(opcional: gerado do nome)</span></label><input class="form-control" name="codigo" placeholder="benfeitorias_e_bens"></div>
                    <div class="mb-2"><label class="form-label small mb-0">Descrição</label><input class="form-control" name="descricao"></div>
                    <div class="mb-2"><label class="form-label small mb-0">Tipo</label>
                        <select class="form-select" name="nivel">
                            <option value="domicilio">Perguntas feitas uma vez (domicílio/entrevista)</option>
                            <option value="morador">Perguntas por morador</option>
                            <option value="repetir">Bloco repetível (ex.: um por bem, um por negócio)</option>
                        </select></div>
                    <button class="btn btn-primary">Criar e editar</button>
                </div>
            </form>
        </div>
    </div>
</div>
