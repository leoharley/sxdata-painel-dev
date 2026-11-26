<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="container-fluid" style="max-width: 980px">
    <h1 class="h3 mb-1">Minha conta</h1>
    <p class="text-muted"><?= html_escape($u->full_name) ?> · usuário <strong><?= html_escape($u->username) ?></strong></p>

    <?php if ($exigido): ?>
        <div class="alert alert-warning"><i class="fas fa-shield-alt me-1"></i> A verificação em duas etapas é obrigatória para administradores e supervisores. Ative abaixo para continuar usando o painel.</div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong><i class="fas fa-shield-alt me-1"></i> Verificação em duas etapas</strong></div>
                <div class="card-body">
                    <?php if (!$robustez): ?>
                        <p class="text-muted mb-0">Indisponível: rode <code>sql/robustez_migration.sql</code> no servidor.</p>
                    <?php elseif ($ativo): ?>
                        <p><span class="badge bg-success">Ativada</span> O login pede o código do aplicativo autenticador.</p>
                        <p class="small text-muted">Para desativar, confirme a senha e um código atual.</p>
                        <?= form_open('conta/desativar_2fa') ?>
                            <div class="mb-2"><input type="password" name="senha" class="form-control" placeholder="Senha" required autocomplete="current-password"></div>
                            <div class="mb-2"><input type="text" name="codigo" class="form-control" placeholder="Código de 6 dígitos" inputmode="numeric" maxlength="7" required autocomplete="one-time-code"></div>
                            <button class="btn btn-outline-danger btn-sm">Desativar</button>
                        <?= form_close() ?>
                    <?php elseif ($novo): ?>
                        <ol class="small ps-3">
                            <li>Instale um aplicativo autenticador no celular (Google Authenticator, Microsoft Authenticator ou Authy).</li>
                            <li>No aplicativo, adicione uma conta e leia o QR Code abaixo.</li>
                            <li>Digite o código de 6 dígitos que aparecer.</li>
                        </ol>
                        <div class="text-center my-3"><div id="qr" class="d-inline-block p-2 bg-white border rounded"></div></div>
                        <p class="small text-muted text-center">Sem câmera? Digite a chave no aplicativo:<br><code style="letter-spacing:1px"><?= html_escape(trim(chunk_split($novo['segredo'], 4, ' '))) ?></code></p>
                        <?= form_open('conta/confirmar_2fa') ?>
                            <div class="input-group">
                                <input type="text" name="codigo" class="form-control" placeholder="000000" inputmode="numeric" maxlength="7" required autofocus autocomplete="one-time-code">
                                <button class="btn btn-primary">Confirmar e ativar</button>
                            </div>
                        <?= form_close() ?>
                        <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
                        <script>
                            (function () {
                                var uri = <?= json_encode($novo['uri']) ?>;
                                if (window.QRCode) { new QRCode(document.getElementById('qr'), { text: uri, width: 190, height: 190, correctLevel: QRCode.CorrectLevel.M }); }
                                else { document.getElementById('qr').innerHTML = '<span class="small text-muted">QR Code indisponível: use a chave abaixo.</span>'; }
                            })();
                        </script>
                    <?php else: ?>
                        <p><span class="badge bg-secondary">Desativada</span></p>
                        <p class="small">Com a verificação em duas etapas, além da senha o login pede um código que muda a cada 30 segundos no seu celular. Mesmo que alguém descubra a senha, não entra sem o celular.</p>
                        <?= form_open('conta/iniciar_2fa') ?><button class="btn btn-primary"><i class="fas fa-qrcode me-1"></i> Ativar</button><?= form_close() ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong><i class="fas fa-key me-1"></i> Trocar senha</strong></div>
                <div class="card-body">
                    <?= form_open('conta/senha') ?>
                        <div class="mb-2"><label class="form-label small">Senha atual</label><input type="password" name="senha_atual" class="form-control" required autocomplete="current-password"></div>
                        <div class="mb-2"><label class="form-label small">Senha nova</label><input type="password" name="senha_nova" class="form-control" required minlength="8" autocomplete="new-password"></div>
                        <div class="mb-2"><label class="form-label small">Confirme a senha nova</label><input type="password" name="senha_confirma" class="form-control" required minlength="8" autocomplete="new-password"></div>
                        <p class="small text-muted"><?= html_escape($politica) ?></p>
                        <button class="btn btn-primary">Trocar senha</button>
                    <?= form_close() ?>
                </div>
            </div>
        </div>
    </div>
</div>
