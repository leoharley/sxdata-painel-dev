<?php defined('BASEPATH') OR exit('No direct script access allowed');
$rotulos = ['login' => 'Login', 'login_falha' => 'Login com erro', 'login_bloqueado' => 'Bloqueio', 'login_negado' => 'Login negado', 'logout' => 'Saída',
    '2fa_ativado' => '2FA ativado', '2fa_desativado' => '2FA desativado', '2fa_falha' => '2FA com erro', '2fa_removido' => '2FA removido',
    'visualizar' => 'Visualizou', 'editar' => 'Editou', 'criar' => 'Criou', 'excluir' => 'Excluiu', 'restaurar' => 'Restaurou', 'purgar' => 'Apagou de vez',
    'exportar' => 'Exportou', 'baixar' => 'Baixou', 'senha' => 'Senha', 'senha_falha' => 'Senha com erro', 'config' => 'Configuração', 'revisao' => 'Revisão',
    'desbloquear' => 'Desbloqueou', 'publicar' => 'Publicou', 'ia' => 'IA'];
$cor = function ($a) { return strpos($a, 'falha') !== FALSE || strpos($a, 'bloque') !== FALSE || $a === 'login_negado' ? 'danger'
    : (in_array($a, ['excluir', 'purgar', '2fa_removido', '2fa_desativado'], TRUE) ? 'warning text-dark' : (in_array($a, ['exportar', 'baixar', 'visualizar'], TRUE) ? 'info text-dark' : 'secondary')); };
$qs = http_build_query(array_filter($f));
$paginas = max(1, (int) ceil($total / $por_pagina));
?>
<div class="container-fluid">
    <a href="<?= base_url('controle') ?>" class="small"><i class="fas fa-arrow-left"></i> Controle e segurança</a>
    <div class="d-flex justify-content-between align-items-end mb-3">
        <div>
            <h1 class="h3 mb-0">Trilha de auditoria</h1>
            <p class="text-muted mb-0"><?= number_format($total, 0, ',', '.') ?> registro(s)</p>
        </div>
        <a class="btn btn-success" href="<?= base_url('controle/auditoria_csv' . ($qs ? '?' . $qs : '')) ?>"><i class="fas fa-file-csv me-1"></i> Exportar CSV</a>
    </div>
    <form class="card card-body mb-3 py-2" method="get">
        <div class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label small mb-0">Usuário</label>
                <select name="user_id" class="form-select form-select-sm"><option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?><option value="<?= (int) $u->id ?>" <?= $f['user_id'] === (int) $u->id ? 'selected' : '' ?>><?= html_escape($u->full_name) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-2"><label class="form-label small mb-0">Ação</label>
                <select name="acao" class="form-select form-select-sm"><option value="">Todas</option>
                    <?php foreach ($acoes as $a): ?><option value="<?= html_escape($a) ?>" <?= $f['acao'] === $a ? 'selected' : '' ?>><?= html_escape($rotulos[$a] ?? $a) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-2"><label class="form-label small mb-0">O quê</label>
                <select name="entidade" class="form-select form-select-sm"><option value="">Tudo</option>
                    <?php foreach ($entidades as $e): ?><option value="<?= html_escape($e) ?>" <?= $f['entidade'] === $e ? 'selected' : '' ?>><?= html_escape($e) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-2"><label class="form-label small mb-0">De</label><input type="date" name="de" value="<?= html_escape($f['de']) ?>" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label small mb-0">Até</label><input type="date" name="ate" value="<?= html_escape($f['ate']) ?>" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label small mb-0">Busca (texto, id, IP)</label>
                <div class="input-group input-group-sm"><input type="text" name="busca" value="<?= html_escape($f['busca']) ?>" class="form-control"><button class="btn btn-primary"><i class="fas fa-search"></i></button></div></div>
        </div>
    </form>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0 align-middle small">
                <thead class="table-light"><tr><th>Quando</th><th>Quem</th><th>Ação</th><th>O quê</th><th>Descrição</th><th>IP</th></tr></thead>
                <tbody>
                <?php if (!$linhas): ?><tr><td colspan="6" class="text-muted p-3">Nenhum registro com esses filtros.</td></tr><?php endif; ?>
                <?php foreach ($linhas as $l): ?>
                    <tr>
                        <td class="text-nowrap"><?= date('d/m/Y H:i:s', strtotime($l->criado_em)) ?></td>
                        <td><?= html_escape($l->username ?: '—') ?><?= $l->papel ? '<br><span class="text-muted">' . html_escape($l->papel) . '</span>' : '' ?></td>
                        <td><span class="badge bg-<?= $cor($l->acao) ?>"><?= html_escape($rotulos[$l->acao] ?? $l->acao) ?></span></td>
                        <td><?= html_escape($l->entidade ?: '') ?><?= $l->entidade_id !== NULL && $l->entidade_id !== '' ? ' #' . html_escape($l->entidade_id) : '' ?></td>
                        <td><?= html_escape($l->descricao) ?>
                            <?php if ($l->antes || $l->depois): ?>
                                <details><summary class="text-primary">detalhes</summary>
                                    <?php if ($l->antes): ?><div><strong>Antes:</strong> <code class="text-wrap"><?= html_escape(mb_substr($l->antes, 0, 2000)) ?></code></div><?php endif; ?>
                                    <?php if ($l->depois): ?><div><strong>Depois:</strong> <code class="text-wrap"><?= html_escape(mb_substr($l->depois, 0, 2000)) ?></code></div><?php endif; ?>
                                </details>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap" title="<?= html_escape($l->user_agent) ?>"><?= html_escape($l->ip) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($paginas > 1): ?>
    <nav class="mt-3"><ul class="pagination pagination-sm">
        <?php for ($p = max(1, $pagina - 4); $p <= min($paginas, $pagina + 4); $p++): ?>
            <li class="page-item <?= $p === $pagina ? 'active' : '' ?>"><a class="page-link" href="?<?= html_escape(http_build_query(array_filter($f) + ['p' => $p])) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
        <li class="page-item disabled"><span class="page-link">de <?= $paginas ?></span></li>
    </ul></nav>
    <?php endif; ?>
</div>
