<?php defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ia_provedores.php';
$pct = $cfg['limite'] > 0 ? min(100, $gasto['total'] / $cfg['limite'] * 100) : 0;
$dis = $admin ? '' : 'disabled';
$por_funcao = [];
foreach ($funcoes as $f) { $por_funcao[] = $f; }
?>
<div class="container-fluid">
    <a href="<?= base_url('ai/settings') ?>" class="small"><i class="fas fa-arrow-left"></i> Configurações de IA</a>
    <div class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0">Provedores e custos de IA</h1>
            <p class="text-muted mb-0">OpenAI e Anthropic (Claude). Chaves cifradas, preço por modelo, limite de gasto e IA por projeto.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?= base_url('ai/comparar') ?>"><i class="fas fa-columns me-1"></i> Comparar modelos</a>
    </div>
    <?php if (!$pronto): ?><div class="alert alert-warning">Rode <code>sql/ia_provedores_migration.sql</code> no servidor para usar esta tela.</div><?php endif; ?>
    <?php if (!$admin): ?><div class="alert alert-info small">Somente administradores alteram esta tela.</div><?php endif; ?>

    <div class="row g-3 mb-3">
        <?php foreach (['openai', 'anthropic'] as $p): $c = $chaves[$p]; ?>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><?= html_escape(Ia_provedores::nome($p)) ?></strong>
                    <?php if ($c['origem'] === 'tela'): ?><span class="badge bg-success">Chave cadastrada <?= html_escape($c['final']) ?></span>
                    <?php elseif ($c['origem'] === 'env'): ?><span class="badge bg-info text-dark">Chave do servidor (.env) <?= html_escape($c['final']) ?></span>
                    <?php elseif ($c['origem'] === 'ilegivel'): ?><span class="badge bg-danger">Chave ilegível: cadastre de novo</span>
                    <?php else: ?><span class="badge bg-secondary">Sem chave</span><?php endif; ?>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        <?= $p === 'anthropic' ? 'Modelos Claude: Opus 5.5 (mais capaz), Sonnet 5.5 (equilíbrio) e Haiku 5.5 (rápido e barato). Chave em console.anthropic.com.' : 'Modelos GPT. A transcrição de áudio (Whisper) usa sempre esta chave.' ?>
                    </p>
                    <?= form_open('ai/salvar_chave', ['autocomplete' => 'off']) ?>
                        <input type="hidden" name="provedor" value="<?= $p ?>">
                        <div class="input-group input-group-sm">
                            <input type="password" name="chave" class="form-control" placeholder="Colar a chave nova" autocomplete="new-password" <?= $dis ?>>
                            <button class="btn btn-primary" <?= $dis ?>>Salvar e testar</button>
                            <?php if ($c['origem'] === 'tela' || $c['origem'] === 'ilegivel'): ?><button class="btn btn-outline-danger" name="remover" value="1" <?= $dis ?> onclick="return confirm('Remover a chave cadastrada?')">Remover</button><?php endif; ?>
                        </div>
                    <?= form_close() ?>
                    <button type="button" class="btn btn-sm btn-link px-0 mt-1" onclick="testar('<?= $p ?>', this)">Testar conexão</button>
                    <span class="small" id="teste-<?= $p ?>"></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><strong>Gasto do mês</strong></div>
                <div class="card-body">
                    <div class="display-6">US$ <?= number_format($gasto['total'], 2, ',', '.') ?></div>
                    <div class="text-muted small mb-2"><?= (int) $gasto['n'] ?> execução(ões) com sucesso neste mês<?= $cfg['limite'] > 0 ? ' · limite US$ ' . number_format($cfg['limite'], 2, ',', '.') : ' · sem limite' ?></div>
                    <?php if ($cfg['limite'] > 0): ?>
                        <div class="progress mb-3" style="height: 10px"><div class="progress-bar <?= $pct >= 100 ? 'bg-danger' : ($pct >= $cfg['alerta'] ? 'bg-warning' : 'bg-success') ?>" style="width: <?= round($pct) ?>%"></div></div>
                    <?php endif; ?>
                    <?= form_open('ai/salvar_limite') ?>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label small mb-0">Limite mensal (US$, 0 = sem limite)</label><input type="number" step="0.01" min="0" name="limite" value="<?= html_escape($cfg['limite']) ?>" class="form-control form-control-sm" <?= $dis ?>></div>
                            <div class="col-6"><label class="form-label small mb-0">Avisar ao chegar em (%)</label><input type="number" min="0" max="100" name="alerta" value="<?= (int) $cfg['alerta'] ?>" class="form-control form-control-sm" <?= $dis ?>></div>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="anonimizar" value="1" id="anon" <?= $cfg['anonimizar'] ? 'checked' : '' ?> <?= $dis ?>>
                            <label class="form-check-label small" for="anon">Retirar CPF, CNPJ, telefone, e-mail e nomes conhecidos antes de enviar (recomendado)</label>
                        </div>
                        <button class="btn btn-sm btn-primary mt-2" <?= $dis ?>>Salvar</button>
                    <?= form_close() ?>
                    <p class="small text-muted mt-2 mb-0">Ao atingir o limite, as funções de IA param até o mês seguinte ou até o limite subir. O aviso aparece em Controle e segurança &gt; Erros do sistema.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><strong>Uso no mês por modelo</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 small">
                        <thead class="table-light"><tr><th>Provedor</th><th>Modelo</th><th class="text-end">Execuções</th><th class="text-end">Tokens entrada</th><th class="text-end">Tokens saída</th><th class="text-end">US$</th></tr></thead>
                        <tbody>
                        <?php if (!$por_modelo): ?><tr><td colspan="6" class="text-muted p-3">Nenhum uso neste mês.</td></tr><?php endif; ?>
                        <?php foreach ($por_modelo as $m): ?>
                            <tr><td><?= html_escape($m->provedor ? Ia_provedores::nome($m->provedor) : Ia_provedores::nome(Ia_provedores::provedor_do_modelo($m->model_used))) ?></td><td><?= html_escape($m->model_used) ?></td>
                                <td class="text-end"><?= (int) $m->n ?></td><td class="text-end"><?= number_format((int) $m->ent, 0, ',', '.') ?></td><td class="text-end"><?= number_format((int) $m->sai, 0, ',', '.') ?></td><td class="text-end"><?= number_format((float) $m->custo, 4, ',', '.') ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><strong>Preço por modelo</strong> <span class="text-muted small">(US$ por milhão de tokens; usado no cálculo de custo)</span></div>
                <div class="card-body p-0">
                    <?= form_open('ai/salvar_precos') ?>
                    <table class="table table-sm mb-0 small align-middle">
                        <thead class="table-light"><tr><th>Provedor</th><th>Modelo</th><th style="width:120px">Entrada</th><th style="width:120px">Saída</th><th>Ativo</th></tr></thead>
                        <tbody>
                        <?php foreach ($modelos as $m): ?>
                            <tr title="<?= html_escape($m->observacao) ?>">
                                <td><?= html_escape(Ia_provedores::nome($m->provedor)) ?></td>
                                <td><strong><?= html_escape($m->nome) ?></strong><br><code><?= html_escape($m->modelo) ?></code></td>
                                <td><input type="text" name="entrada[<?= (int) $m->id ?>]" value="<?= html_escape((float) $m->preco_entrada) ?>" class="form-control form-control-sm" <?= $dis ?>></td>
                                <td><input type="text" name="saida[<?= (int) $m->id ?>]" value="<?= html_escape((float) $m->preco_saida) ?>" class="form-control form-control-sm" <?= $dis ?>></td>
                                <td><input type="checkbox" class="form-check-input" name="ativo[<?= (int) $m->id ?>]" value="1" <?= in_array($m->ativo, [TRUE, 't', 1, '1'], TRUE) ? 'checked' : '' ?> <?= $dis ?>></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="p-2"><button class="btn btn-sm btn-primary" <?= $dis ?>>Salvar preços</button> <span class="small text-muted">Preços de referência de novembro de 2026; confira no site do provedor.</span></div>
                    <?= form_close() ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><strong>IA por projeto</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 small align-middle">
                        <tbody>
                        <?php foreach ($projetos as $pj): $off = in_array($pj->ia_desligada, [TRUE, 't', 1, '1'], TRUE); ?>
                            <tr>
                                <td><?= html_escape($pj->name) ?></td>
                                <td><?= $off ? '<span class="badge bg-secondary">IA desligada</span>' : '<span class="badge bg-success">IA ligada</span>' ?></td>
                                <td class="text-end"><?= form_open('ai/ia_projeto', ['class' => 'd-inline']) ?><input type="hidden" name="project_id" value="<?= (int) $pj->id ?>"><input type="hidden" name="desligar" value="<?= $off ? '0' : '1' ?>">
                                    <button class="btn btn-sm btn-outline-<?= $off ? 'success' : 'secondary' ?> py-0" <?= $dis ?>><?= $off ? 'Ligar' : 'Desligar' ?></button><?= form_close() ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="small text-muted p-2 mb-0">Com a IA desligada, nenhuma função de IA roda para os questionários e entrevistas do projeto (inclusive pelo app).</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Modelo de cada função</strong> <a class="small ms-2" href="<?= base_url('ai/settings') ?>">alterar</a></div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0 small">
                <thead class="table-light"><tr><th>Função</th><th>Situação</th><th>Provedor</th><th>Modelo</th><th>Alternativa</th></tr></thead>
                <tbody>
                <?php foreach ($por_funcao as $f): ?>
                    <tr><td><?= html_escape($f['feature_name']) ?></td>
                        <td><?= in_array($f['is_enabled'], [TRUE, 't', 1, '1'], TRUE) ? '<span class="badge bg-success">Ativa</span>' : '<span class="badge bg-secondary">Inativa</span>' ?></td>
                        <td><?= html_escape(Ia_provedores::nome(Ia_provedores::provedor_do_modelo($f['model']))) ?></td><td><code><?= html_escape($f['model']) ?></code></td>
                        <td><?= !empty($f['fallback_model']) ? '<code>' . html_escape($f['fallback_model']) . '</code>' : '—' ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
function testar(p, btn) {
    var alvo = document.getElementById('teste-' + p);
    alvo.textContent = 'testando…';
    fetch('<?= base_url('ai/test_connection') ?>?provedor=' + p, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) { alvo.textContent = j.message; alvo.className = 'small ' + (j.success ? 'text-success' : 'text-danger'); })
        .catch(function () { alvo.textContent = 'Falha ao testar.'; alvo.className = 'small text-danger'; });
}
</script>
