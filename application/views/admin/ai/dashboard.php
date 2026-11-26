<style>
    .ai-stat-card {
        background: white;
        border-radius: 0.75rem;
        padding: 1.25rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: transform 0.2s;
        height: 100%;
    }
    .ai-stat-card:hover { transform: translateY(-2px); }
    .ai-stat-icon {
        width: 50px; height: 50px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem; color: white;
    }
    .ai-feature-card {
        background: white; border-radius: 0.5rem; padding: 1rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 0.75rem;
        display: flex; align-items: center; justify-content: space-between;
    }
    .ai-badge-enabled { background: #8fae5d; color: white; }
    .ai-badge-disabled { background: #dc3545; color: white; }
    .ai-section-title {
        color: var(--secondary-color); font-weight: 600;
        border-bottom: 2px solid var(--primary-color);
        padding-bottom: 0.5rem; margin-bottom: 1.25rem;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-brain me-2" style="color: var(--primary-color);"></i>Inteligência Artificial</h2>
    <div>
        <?php if ($is_configured): ?>
            <span class="badge bg-success px-3 py-2"><i class="fas fa-check-circle me-1"></i>API Configurada</span>
        <?php else: ?>
            <span class="badge bg-danger px-3 py-2"><i class="fas fa-exclamation-triangle me-1"></i>API Não Configurada</span>
        <?php endif; ?>
        <a href="<?= base_url('ai/security') ?>" class="btn btn-outline-dark ms-2"><i class="fas fa-lock me-1"></i>Segurança</a>
        <a href="<?= base_url('ai/directives') ?>" class="btn btn-outline-primary ms-2"><i class="fas fa-shield-alt me-1"></i>Diretrizes</a>
        <a href="<?= base_url('ai/provedores') ?>" class="btn btn-outline-success ms-2"><i class="fas fa-random me-1"></i>Provedores e custos</a>
        <a href="<?= base_url('ai/comparar') ?>" class="btn btn-outline-success ms-2"><i class="fas fa-columns me-1"></i>Comparar modelos</a>
        <a href="<?= base_url('ai/settings') ?>" class="btn btn-primary ms-2"><i class="fas fa-cog me-1"></i>Configurações</a>
    </div>
</div>

<?php if (!$is_configured): ?>
<div class="alert alert-warning border-0 mb-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Atenção:</strong> A variável de ambiente <code>OPENAI_API_KEY</code> não está configurada.
    Configure-a no servidor para habilitar as funcionalidades de IA.
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="ai-stat-card">
            <div class="d-flex align-items-center">
                <div class="ai-stat-icon" style="background: linear-gradient(135deg, #8fae5d, #6d8a45);">
                    <i class="fas fa-toggle-on"></i>
                </div>
                <div class="ms-3">
                    <h3 class="mb-0" style="color: var(--secondary-color);"><?= $stats['enabled_features'] ?>/<?= $stats['total_features'] ?></h3>
                    <small class="text-muted">Recursos Ativos</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ai-stat-card">
            <div class="d-flex align-items-center">
                <div class="ai-stat-icon" style="background: linear-gradient(135deg, #23345F, #1a2847);">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="ms-3">
                    <h3 class="mb-0" style="color: var(--secondary-color);"><?= $stats['executions_today'] ?></h3>
                    <small class="text-muted">Execuções Hoje</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ai-stat-card">
            <div class="d-flex align-items-center">
                <div class="ai-stat-icon" style="background: linear-gradient(135deg, #f0ad4e, #ec971f);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="ms-3">
                    <h3 class="mb-0" style="color: var(--secondary-color);"><?= $stats['inconsistencies_pending'] ?></h3>
                    <small class="text-muted">Inconsistências Pendentes</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="ai-stat-card">
            <div class="d-flex align-items-center">
                <div class="ai-stat-icon" style="background: linear-gradient(135deg, #5bc0de, #46b8da);">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="ms-3">
                    <h3 class="mb-0" style="color: var(--secondary-color);">$<?= number_format($stats['cost_this_month'], 4) ?></h3>
                    <small class="text-muted">Custo do Mês</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Funcionalidades -->
    <div class="col-lg-8">
        <h5 class="ai-section-title"><i class="fas fa-puzzle-piece me-2"></i>Funcionalidades</h5>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <a href="<?= base_url('ai/transcriptions') ?>" class="text-decoration-none">
                    <div class="ai-feature-card">
                        <div>
                            <i class="fas fa-microphone text-primary me-2"></i>
                            <strong>Transcrições</strong>
                            <br><small class="text-muted"><?= $stats['transcriptions_total'] ?> total | <?= $stats['transcriptions_pending'] ?> pendentes</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url('ai/inconsistencies') ?>" class="text-decoration-none">
                    <div class="ai-feature-card">
                        <div>
                            <i class="fas fa-search text-warning me-2"></i>
                            <strong>Inconsistências</strong>
                            <br><small class="text-muted"><?= $stats['inconsistencies_total'] ?> detectadas | <?= $stats['inconsistencies_pending'] ?> pendentes</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url('ai/corrections') ?>" class="text-decoration-none">
                    <div class="ai-feature-card">
                        <div>
                            <i class="fas fa-spell-check text-info me-2"></i>
                            <strong>Correções</strong>
                            <br><small class="text-muted"><?= $stats['corrections_total'] ?> sugestões | <?= $stats['corrections_pending'] ?> pendentes</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url('ai/smart_fill') ?>" class="text-decoration-none">
                    <div class="ai-feature-card">
                        <div>
                            <i class="fas fa-magic text-success me-2"></i>
                            <strong>Preenchimento Inteligente</strong>
                            <br><small class="text-muted">Sugestões automáticas de campos</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url('ai/reformulations') ?>" class="text-decoration-none">
                    <div class="ai-feature-card">
                        <div>
                            <i class="fas fa-edit text-secondary me-2"></i>
                            <strong>Reformulação de Perguntas</strong>
                            <br><small class="text-muted">Reescrita inteligente</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url('ai/adaptive') ?>" class="text-decoration-none">
                    <div class="ai-feature-card">
                        <div>
                            <i class="fas fa-route text-danger me-2"></i>
                            <strong>Questionário Adaptativo</strong>
                            <br><small class="text-muted">Roteamento inteligente</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url('ai/followup') ?>" class="text-decoration-none">
                    <div class="ai-feature-card">
                        <div>
                            <i class="fas fa-lightbulb text-primary me-2"></i>
                            <strong>Sugestões de Dicas</strong>
                            <br><small class="text-muted">Dicas de follow-up para perguntas</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url('ai/analysis') ?>" class="text-decoration-none">
                    <div class="ai-feature-card">
                        <div>
                            <i class="fas fa-chart-line text-success me-2"></i>
                            <strong>Análise Estatística</strong>
                            <br><small class="text-muted"><?= $stats['analyses_total'] ?> análises geradas</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url('ai/charts') ?>" class="text-decoration-none">
                    <div class="ai-feature-card">
                        <div>
                            <i class="fas fa-chart-pie text-warning me-2"></i>
                            <strong>Gráficos Inteligentes</strong>
                            <br><small class="text-muted">Visualizações sugeridas por IA</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url('ai/reports') ?>" class="text-decoration-none">
                    <div class="ai-feature-card">
                        <div>
                            <i class="fas fa-file-alt text-info me-2"></i>
                            <strong>Relatórios Narrativos</strong>
                            <br><small class="text-muted"><?= $stats['reports_total'] ?> relatórios gerados</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Sidebar: Status e Erros -->
    <div class="col-lg-4">
        <h5 class="ai-section-title"><i class="fas fa-heartbeat me-2"></i>Status do Sistema</h5>

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>API OpenAI</span>
                    <?php if ($is_configured): ?>
                        <span class="badge bg-success">Conectada</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Desconectada</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Tokens (mês)</span>
                    <span class="fw-bold"><?= number_format($execution_stats['total_tokens_input'] + $execution_stats['total_tokens_output']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Execuções (24h)</span>
                    <span class="fw-bold"><?= $execution_stats['last_24h'] ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Erros Recentes</span>
                    <span class="fw-bold text-<?= count($execution_stats['recent_errors']) > 0 ? 'danger' : 'success' ?>">
                        <?= count($execution_stats['recent_errors']) ?>
                    </span>
                </div>

                <hr>
                <button class="btn btn-sm btn-outline-primary w-100" onclick="testConnection()">
                    <i class="fas fa-plug me-1"></i>Testar Conexão
                </button>
                <div id="connectionResult" class="mt-2" style="display:none;"></div>
            </div>
        </div>

        <!-- Erros Recentes -->
        <?php if (!empty($execution_stats['recent_errors'])): ?>
        <h5 class="ai-section-title"><i class="fas fa-exclamation-circle me-2"></i>Erros Recentes</h5>
        <div class="card">
            <div class="card-body p-0">
                <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                    <?php foreach (array_slice($execution_stats['recent_errors'], 0, 5) as $error): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <small class="fw-bold"><?= htmlspecialchars($error['feature_key'] ?? '') ?></small>
                            <small class="text-muted"><?= !empty($error['created_at']) ? date('d/m H:i', strtotime($error['created_at'])) : '' ?></small>
                        </div>
                        <small class="text-danger"><?= htmlspecialchars(substr($error['error_message'] ?? '', 0, 100)) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="mt-3">
            <a href="<?= base_url('ai/logs') ?>" class="btn btn-outline-secondary btn-sm w-100">
                <i class="fas fa-list me-1"></i>Ver Todos os Logs
            </a>
        </div>
    </div>
</div>

<script>
function testConnection() {
    const btn = event.target.closest('button');
    const result = document.getElementById('connectionResult');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Testando...';
    result.style.display = 'none';

    fetch('<?= base_url('ai/test_connection') ?>', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            result.style.display = 'block';
            if (data.success) {
                result.className = 'mt-2 alert alert-success py-1 px-2 mb-0';
                result.innerHTML = '<small><i class="fas fa-check me-1"></i>' + data.message + '</small>';
            } else {
                result.className = 'mt-2 alert alert-danger py-1 px-2 mb-0';
                result.innerHTML = '<small><i class="fas fa-times me-1"></i>' + data.message + '</small>';
            }
        })
        .catch(() => {
            result.style.display = 'block';
            result.className = 'mt-2 alert alert-danger py-1 px-2 mb-0';
            result.innerHTML = '<small>Erro de rede</small>';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plug me-1"></i>Testar Conexão';
        });
}
</script>
