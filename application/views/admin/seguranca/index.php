<?php defined('BASEPATH') OR exit('No direct script access allowed');
$admin = $this->session->userdata('admin_role') === 'administrador';
$c = $contadores ?: [];
$cards = [
    ['controle/revisao', 'fa-clipboard-check', 'Fila de revisão', 'Entrevistas gravadas com alguma inconsistência (obrigatória vazia, opção inexistente, cálculo divergente). Nada é recusado: confira e marque como revisada.', $c['revisao'] ?? 0, 'warning', TRUE],
    ['controle/aparelhos', 'fa-mobile-alt', 'Aparelhos em campo', 'O que cada aparelho ainda tem na fila (entrevistas e fotos não enviadas) e quando falou com o servidor pela última vez.', $c['aparelhos'] ?? 0, 'danger', TRUE],
    ['controle/erros', 'fa-bug', 'Erros do sistema', 'Erros do painel, da API e do app, agrupados. Marque como resolvido depois de tratar.', $c['erros'] ?? 0, 'danger', TRUE],
    ['controle/auditoria', 'fa-user-secret', 'Trilha de auditoria', 'Logins, tentativas, visualização de dados identificados, edições, exclusões e exportações. Com filtro e CSV.', 0, 'secondary', $admin],
    ['controle/lixeira', 'fa-trash-restore', 'Lixeira', 'Entrevistas, moradores, questionários, projetos e usuários excluídos. Restaure com um clique.', 0, 'secondary', $admin],
    ['conta/seguranca', 'fa-shield-alt', 'Minha conta', 'Trocar a senha e ativar a verificação em duas etapas (aplicativo autenticador).', 0, 'secondary', TRUE],
    ['controle/versao', 'fa-code-branch', 'Versão e novidades', 'Versão publicada e o que mudou em cada publicação.', 0, 'secondary', TRUE],
];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-end mb-3">
        <div>
            <h1 class="h3 mb-0">Controle e segurança</h1>
            <p class="text-muted mb-0">Versão <?= html_escape($versao['numero'] ?? '-') ?> · publicada em <?= html_escape($versao['data'] ?? '-') ?></p>
        </div>
    </div>
    <?php if (!$pronto): ?>
        <div class="alert alert-warning">As telas desta área precisam da migração <code>sql/robustez_migration.sql</code>.</div>
    <?php endif; ?>
    <div class="row g-3">
        <?php foreach ($cards as $k): if (!$k[6]) { continue; } ?>
        <div class="col-md-6 col-xl-4">
            <a href="<?= base_url($k[0]) ?>" class="card h-100 text-decoration-none text-body shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <h5 class="card-title mb-2"><i class="fas <?= $k[1] ?> me-2 text-primary"></i><?= $k[2] ?></h5>
                        <?php if ($k[4] > 0): ?><span class="badge bg-<?= $k[5] ?>"><?= (int) $k[4] ?></span><?php endif; ?>
                    </div>
                    <p class="card-text small text-muted mb-0"><?= $k[3] ?></p>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
