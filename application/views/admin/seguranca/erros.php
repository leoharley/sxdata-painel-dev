<?php defined('BASEPATH') OR exit('No direct script access allowed');
$base = 'controle/erros?' . ($abertos ? '' : 'resolvidos=1&'); ?>
<div class="container-fluid">
    <a href="<?= base_url('controle') ?>" class="small"><i class="fas fa-arrow-left"></i> Controle e segurança</a>
    <div class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0">Erros do sistema</h1>
            <p class="text-muted mb-0">Painel, API e app. O mesmo erro aparece uma vez, com o número de ocorrências.</p>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group btn-group-sm">
                <?php foreach (['' => 'Todas as origens', 'painel' => 'Painel', 'api' => 'API', 'app' => 'App', 'cli' => 'Servidor'] as $k => $n): ?>
                    <a class="btn btn-outline-secondary <?= (string) $origem === $k ? 'active' : '' ?>" href="<?= base_url($base . ($k ? 'origem=' . $k : '')) ?>"><?= $n ?></a>
                <?php endforeach; ?>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="<?= base_url($abertos ? 'controle/erros?resolvidos=1' : 'controle/erros') ?>"><?= $abertos ? 'Ver resolvidos' : 'Ver abertos' ?></a>
            <?php if ($abertos && $lista): ?>
                <?= form_open('controle/resolver_erro/0', ['class' => 'd-inline']) ?><input type="hidden" name="origem" value="<?= html_escape((string) $origem) ?>">
                <button class="btn btn-sm btn-outline-success" onclick="return confirm('Marcar todos os erros listados como resolvidos?')">Resolver todos</button><?= form_close() ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-body p-0">
        <?php if (!$lista): ?>
            <p class="p-4 mb-0 text-muted"><?= $abertos ? 'Nenhum erro aberto.' : 'Nenhum erro resolvido.' ?></p>
        <?php else: ?>
            <table class="table table-sm mb-0 align-middle small">
                <thead class="table-light"><tr><th>Origem</th><th>Erro</th><th class="text-end">Vezes</th><th>Última</th><th>Primeira</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($lista as $e): ?>
                    <tr>
                        <td><span class="badge bg-<?= $e->origem === 'app' ? 'info text-dark' : ($e->origem === 'api' ? 'primary' : 'secondary') ?>"><?= html_escape($e->origem) ?></span><br><span class="text-muted"><?= html_escape($e->nivel) ?></span></td>
                        <td style="max-width:640px">
                            <div class="text-break"><?= html_escape(mb_substr($e->mensagem, 0, 400)) ?></div>
                            <?php if ($e->arquivo): ?><div class="text-muted"><?= html_escape($e->arquivo) ?><?= $e->linha ? ':' . (int) $e->linha : '' ?></div><?php endif; ?>
                            <details><summary class="text-primary">detalhes</summary>
                                <?php if ($e->url): ?><div><strong>Endereço:</strong> <?= html_escape($e->url) ?></div><?php endif; ?>
                                <?php if ($e->usuario): ?><div><strong>Usuário:</strong> <?= html_escape($e->usuario) ?></div><?php endif; ?>
                                <?php if (mb_strlen($e->mensagem) > 400): ?><pre class="small text-wrap"><?= html_escape($e->mensagem) ?></pre><?php endif; ?>
                                <?php if ($e->contexto): ?><pre class="small text-wrap"><?= html_escape(json_encode(json_decode($e->contexto), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre><?php endif; ?>
                            </details>
                        </td>
                        <td class="text-end"><?= (int) $e->ocorrencias ?></td>
                        <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($e->ultimo_em)) ?></td>
                        <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($e->primeiro_em)) ?></td>
                        <td class="text-end">
                            <?php if (!$e->resolvido_em): ?>
                                <?= form_open('controle/resolver_erro/' . (int) $e->id, ['class' => 'd-inline']) ?><input type="hidden" name="voltar" value="<?= html_escape($base . ($origem ? 'origem=' . $origem : '')) ?>"><button class="btn btn-sm btn-outline-success">Resolvido</button><?= form_close() ?>
                            <?php else: ?><span class="text-success">resolvido <?= date('d/m H:i', strtotime($e->resolvido_em)) ?></span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
    </div>
</div>
