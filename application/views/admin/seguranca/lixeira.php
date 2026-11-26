<?php defined('BASEPATH') OR exit('No direct script access allowed');
$nomes = ['entrevista' => 'Entrevista', 'morador' => 'Morador', 'questionario' => 'Questionário', 'projeto' => 'Projeto']; ?>
<div class="container-fluid">
    <a href="<?= base_url('controle') ?>" class="small"><i class="fas fa-arrow-left"></i> Controle e segurança</a>
    <div class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0">Lixeira</h1>
            <p class="text-muted mb-0">O que foi excluído fica guardado aqui, completo. Restaurar devolve tudo como estava, com os mesmos números.</p>
        </div>
        <div class="btn-group btn-group-sm">
            <a class="btn btn-outline-secondary <?= !$tipo ? 'active' : '' ?>" href="<?= base_url('controle/lixeira') ?>">Tudo</a>
            <?php foreach ($nomes as $k => $n): ?><a class="btn btn-outline-secondary <?= $tipo === $k ? 'active' : '' ?>" href="<?= base_url('controle/lixeira?tipo=' . $k) ?>"><?= $n ?></a><?php endforeach; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body p-0">
        <?php if (!$itens): ?>
            <p class="p-4 mb-0 text-muted">A lixeira está vazia.</p>
        <?php else: ?>
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>Tipo</th><th>Item</th><th>Excluído por</th><th>Quando</th><th>Motivo</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($itens as $i): ?>
                    <tr class="<?= $i->restaurado_em ? 'text-muted' : '' ?>">
                        <td><span class="badge bg-secondary"><?= $nomes[$i->tipo] ?? html_escape($i->tipo) ?></span></td>
                        <td><?= html_escape($i->descricao) ?></td>
                        <td><?= html_escape($i->excluido_por_nome ?: '—') ?></td>
                        <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($i->excluido_em)) ?></td>
                        <td class="small"><?= html_escape($i->motivo ?: '') ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($i->restaurado_em): ?>
                                <span class="small">Restaurado por <?= html_escape($i->restaurado_por_nome ?: '—') ?> em <?= date('d/m/Y H:i', strtotime($i->restaurado_em)) ?></span>
                            <?php else: ?>
                                <?= form_open('controle/restaurar/' . (int) $i->id, ['class' => 'd-inline']) ?>
                                    <button class="btn btn-sm btn-success" onclick="return confirm('Restaurar este item?')"><i class="fas fa-undo me-1"></i>Restaurar</button>
                                <?= form_close() ?>
                                <?= form_open('controle/purgar/' . (int) $i->id, ['class' => 'd-inline']) ?>
                                    <button class="btn btn-sm btn-outline-danger" title="Apagar de vez" onclick="return confirm('Apagar DE VEZ? Não será mais possível restaurar.')"><i class="fas fa-times"></i></button>
                                <?= form_close() ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
    </div>

    <h2 class="h5">Usuários excluídos</h2>
    <div class="card">
        <div class="card-body p-0">
        <?php if (!$usuarios): ?>
            <p class="p-3 mb-0 text-muted">Nenhum usuário excluído.</p>
        <?php else: ?>
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light"><tr><th>Usuário</th><th>Nome</th><th>Perfil</th><th>Excluído por</th><th>Quando</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= html_escape($u->username) ?></td><td><?= html_escape($u->full_name) ?></td><td><?= html_escape($u->role) ?></td>
                        <td><?= html_escape($u->excluido_por ?: '—') ?></td><td><?= date('d/m/Y H:i', strtotime($u->deleted_at)) ?></td>
                        <td class="text-end"><?= form_open('controle/restaurar_usuario/' . (int) $u->id, ['class' => 'd-inline']) ?><button class="btn btn-sm btn-success"><i class="fas fa-undo me-1"></i>Restaurar</button><?= form_close() ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
    </div>
</div>
