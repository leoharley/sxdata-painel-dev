<style>
    .log-stat-card {
        background: white;
        border-radius: 0.75rem;
        padding: 1.25rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        height: 100%;
    }
    .log-stat-icon {
        width: 50px; height: 50px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem; color: white;
    }
    .log-filter-bar {
        background: white;
        border-radius: 0.5rem;
        padding: 1rem 1.25rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        margin-bottom: 1.25rem;
    }
    .log-table th {
        background: var(--secondary-color);
        color: white;
        font-weight: 600;
        font-size: 0.85rem;
        white-space: nowrap;
        border: none;
    }
    .log-table td {
        vertical-align: middle;
        font-size: 0.875rem;
    }
    .log-table tbody tr {
        transition: background 0.15s;
    }
    .log-table tbody tr:hover {
        background: #f8f9fa;
    }
    .log-table tbody tr.row-error {
        background: #fff5f5;
    }
    .log-table tbody tr.row-error:hover {
        background: #ffe8e8;
    }
    .badge-status-success { background: #8fae5d; color: white; }
    .badge-status-error { background: #dc3545; color: white; }
    .badge-status-processing { background: #0d6efd; color: white; }
    .badge-status-pending { background: #6c757d; color: white; }
    .log-error-detail {
        display: none;
        background: #fff0f0;
        border-left: 3px solid #dc3545;
        padding: 0.625rem 1rem;
        font-size: 0.8rem;
        color: #842029;
    }
    .log-error-detail td {
        padding: 0.5rem 1rem !important;
    }
    .log-section-title {
        color: var(--secondary-color);
        font-weight: 600;
        border-bottom: 2px solid var(--primary-color);
        padding-bottom: 0.5rem;
        margin-bottom: 1.25rem;
    }
    .pagination-simple .btn {
        min-width: 110px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-list-alt me-2" style="color: var(--primary-color);"></i>Logs de Execução de IA</h2>
    <a href="<?= base_url('ai') ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Voltar ao Painel de IA
    </a>
</div>

<!-- Filter Bar -->
<div class="log-filter-bar">
    <form method="get" action="<?= base_url('ai/logs') ?>" id="filterForm">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label mb-1 small fw-bold">Funcionalidade</label>
                <select name="feature_key" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php if (!empty($settings)): ?>
                        <?php foreach ($settings as $s): ?>
                            <option value="<?= htmlspecialchars($s->feature_key ?? $s['feature_key'] ?? '') ?>"
                                <?= (!empty($filters['feature_key']) && ($filters['feature_key'] == ($s->feature_key ?? $s['feature_key'] ?? ''))) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s->feature_name ?? $s['feature_name'] ?? $s->feature_key ?? $s['feature_key'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small fw-bold">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="pending" <?= (!empty($filters['status']) && $filters['status'] == 'pending') ? 'selected' : '' ?>>Pendente</option>
                    <option value="processing" <?= (!empty($filters['status']) && $filters['status'] == 'processing') ? 'selected' : '' ?>>Processando</option>
                    <option value="success" <?= (!empty($filters['status']) && $filters['status'] == 'success') ? 'selected' : '' ?>>Sucesso</option>
                    <option value="error" <?= (!empty($filters['status']) && $filters['status'] == 'error') ? 'selected' : '' ?>>Erro</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small fw-bold">Data Início</label>
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small fw-bold">Data Fim</label>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-filter me-1"></i>Filtrar
                </button>
                <a href="<?= base_url('ai/logs') ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Limpar
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Summary Stats -->
<?php
    $total_executions = (int)($total ?? 0);
    $success_count = 0;
    $total_cost = 0;
    $total_tokens = 0;

    if (!empty($logs)) {
        foreach ($logs as $log) {
            $log = (object)$log;
            if (($log->status ?? '') === 'success') $success_count++;
            $total_cost += (float)($log->cost_usd ?? 0);
            $total_tokens += (int)($log->tokens_input ?? 0) + (int)($log->tokens_output ?? 0);
        }
    }

    $success_rate = $total_executions > 0 ? round(($success_count / count($logs)) * 100, 1) : 0;
?>
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="log-stat-card">
            <div class="d-flex align-items-center">
                <div class="log-stat-icon" style="background: linear-gradient(135deg, #23345F, #1a2847);">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="ms-3">
                    <h3 class="mb-0" style="color: var(--secondary-color);"><?= number_format($total_executions) ?></h3>
                    <small class="text-muted">Total de Execuções</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="log-stat-card">
            <div class="d-flex align-items-center">
                <div class="log-stat-icon" style="background: linear-gradient(135deg, #8fae5d, #6d8a45);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="ms-3">
                    <h3 class="mb-0" style="color: var(--secondary-color);"><?= $success_rate ?>%</h3>
                    <small class="text-muted">Taxa de Sucesso</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="log-stat-card">
            <div class="d-flex align-items-center">
                <div class="log-stat-icon" style="background: linear-gradient(135deg, #5bc0de, #46b8da);">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="ms-3">
                    <h3 class="mb-0" style="color: var(--secondary-color);">$<?= number_format($total_cost, 4) ?></h3>
                    <small class="text-muted">Custo (página atual)</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="log-stat-card">
            <div class="d-flex align-items-center">
                <div class="log-stat-icon" style="background: linear-gradient(135deg, #f0ad4e, #ec971f);">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="ms-3">
                    <h3 class="mb-0" style="color: var(--secondary-color);"><?= number_format($total_tokens) ?></h3>
                    <small class="text-muted">Tokens (página atual)</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Logs Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-0">Nenhum log encontrado para os filtros selecionados.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 log-table">
                    <thead>
                        <tr>
                            <th class="ps-3">Data/Hora</th>
                            <th>Funcionalidade</th>
                            <th>Modelo</th>
                            <th class="text-center">Tokens (In/Out)</th>
                            <th class="text-end">Custo</th>
                            <th class="text-end">Duração</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log):
                            $log = (object)$log;
                            $is_error = ($log->status ?? '') === 'error';
                            $has_error_msg = !empty($log->error_message);
                            $duration_ms = (int)($log->duration_ms ?? 0);
                            $duration_display = $duration_ms >= 1000
                                ? number_format($duration_ms / 1000, 2) . 's'
                                : $duration_ms . 'ms';
                        ?>
                        <tr class="<?= $is_error ? 'row-error' : '' ?> <?= $has_error_msg ? 'log-row-clickable' : '' ?>"
                            <?= $has_error_msg ? 'data-log-id="' . (int)$log->id . '" style="cursor:pointer;" title="Clique para ver detalhes do erro"' : '' ?>>
                            <td class="ps-3">
                                <span class="text-nowrap"><?= date('d/m/Y', strtotime($log->created_at)) ?></span>
                                <br>
                                <small class="text-muted"><?= date('H:i:s', strtotime($log->created_at)) ?></small>
                            </td>
                            <td>
                                <span class="fw-semibold"><?= htmlspecialchars($log->feature_key ?? '-') ?></span>
                            </td>
                            <td>
                                <small class="text-muted"><?= htmlspecialchars($log->model_used ?? '-') ?></small>
                                <?php if (!empty($log->provider)): ?><br><span class="badge bg-light text-dark border"><?= $log->provider === 'anthropic' ? 'Anthropic' : 'OpenAI' ?></span><?php endif; ?>
                                <?php if (!empty($log->fallback_de)): ?><br><span class="badge bg-warning text-dark" title="<?= htmlspecialchars($log->fallback_de) ?>">alternativa</span><?php endif; ?>
                                <?php if (!empty($log->anonimizado) && in_array($log->anonimizado, [true, 't', 1, '1'], true)): ?><br><span class="badge bg-success" title="Dados pessoais retirados antes do envio">anonimizado</span><?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="text-success fw-semibold"><?= number_format((int)($log->tokens_input ?? 0)) ?></span>
                                <span class="text-muted mx-1">/</span>
                                <span class="text-primary fw-semibold"><?= number_format((int)($log->tokens_output ?? 0)) ?></span>
                            </td>
                            <td class="text-end">
                                <span class="fw-semibold">$<?= number_format((float)($log->cost_usd ?? 0), 4) ?></span>
                            </td>
                            <td class="text-end">
                                <?= $duration_display ?>
                            </td>
                            <td class="text-center">
                                <?php
                                    $status = $log->status ?? 'pending';
                                    $status_labels = [
                                        'success' => 'Sucesso',
                                        'error' => 'Erro',
                                        'processing' => 'Processando',
                                        'pending' => 'Pendente',
                                    ];
                                    $status_icons = [
                                        'success' => 'fa-check-circle',
                                        'error' => 'fa-times-circle',
                                        'processing' => 'fa-spinner fa-spin',
                                        'pending' => 'fa-clock',
                                    ];
                                ?>
                                <span class="badge badge-status-<?= $status ?> px-2 py-1">
                                    <i class="fas <?= $status_icons[$status] ?? 'fa-circle' ?> me-1"></i><?= $status_labels[$status] ?? ucfirst($status) ?>
                                </span>
                                <?php if ($has_error_msg): ?>
                                    <br><small class="text-danger"><i class="fas fa-chevron-down ms-1 error-chevron" id="chevron-<?= (int)$log->id ?>"></i></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($has_error_msg): ?>
                        <tr class="log-error-detail" id="error-detail-<?= (int)$log->id ?>">
                            <td colspan="7">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Erro:</strong> <?= htmlspecialchars($log->error_message) ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination -->
<?php
    $current_page = (int)($page ?? 1);
    $items_per_page = (int)($per_page ?? 50);
    $total_pages = ceil((int)($total ?? 0) / $items_per_page);

    $query_params = [];
    if (!empty($filters['feature_key'])) $query_params['feature_key'] = $filters['feature_key'];
    if (!empty($filters['status'])) $query_params['status'] = $filters['status'];
    if (!empty($filters['date_from'])) $query_params['date_from'] = $filters['date_from'];
    if (!empty($filters['date_to'])) $query_params['date_to'] = $filters['date_to'];

    $base_query = $query_params ? '&' . http_build_query($query_params) : '';
?>
<?php if ($total_pages > 1): ?>
<div class="d-flex justify-content-between align-items-center mt-3 mb-4 pagination-simple">
    <div class="text-muted small">
        Exibindo <?= number_format(($current_page - 1) * $items_per_page + 1) ?>
        a <?= number_format(min($current_page * $items_per_page, (int)$total)) ?>
        de <?= number_format((int)$total) ?> registros
    </div>
    <div class="d-flex gap-2">
        <?php if ($current_page > 1): ?>
            <a href="<?= base_url('ai/logs?page=' . ($current_page - 1) . $base_query) ?>" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-chevron-left me-1"></i>Anterior
            </a>
        <?php else: ?>
            <button class="btn btn-sm btn-outline-secondary" disabled>
                <i class="fas fa-chevron-left me-1"></i>Anterior
            </button>
        <?php endif; ?>

        <span class="btn btn-sm btn-light disabled">
            <?= $current_page ?> / <?= $total_pages ?>
        </span>

        <?php if ($current_page < $total_pages): ?>
            <a href="<?= base_url('ai/logs?page=' . ($current_page + 1) . $base_query) ?>" class="btn btn-sm btn-outline-primary">
                Próximo<i class="fas fa-chevron-right ms-1"></i>
            </a>
        <?php else: ?>
            <button class="btn btn-sm btn-outline-secondary" disabled>
                Próximo<i class="fas fa-chevron-right ms-1"></i>
            </button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function() {
    // Toggle error detail rows
    $('.log-row-clickable').on('click', function() {
        var logId = $(this).data('log-id');
        var $detail = $('#error-detail-' + logId);
        var $chevron = $('#chevron-' + logId);

        $detail.slideToggle(200);
        $chevron.toggleClass('fa-chevron-down fa-chevron-up');
    });
});
</script>
