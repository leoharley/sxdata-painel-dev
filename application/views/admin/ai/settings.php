<style>
    .ai-settings-card {
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }
    .ai-settings-card .card-header {
        background: white;
        border-bottom: 2px solid var(--primary-color);
        padding: 1rem 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .ai-settings-card .card-header h5 {
        margin: 0;
        color: var(--secondary-color);
        font-weight: 600;
    }
    .ai-settings-card .card-body { padding: 0; }

    .ai-category-icon {
        width: 36px; height: 36px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        color: white; font-size: 0.95rem; margin-right: 0.75rem;
    }

    .feature-row {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.15s;
    }
    .feature-row:last-child { border-bottom: none; }
    .feature-row:hover { background: #fafbfc; }

    .feature-name {
        font-weight: 600;
        color: var(--secondary-color);
        font-size: 0.95rem;
    }
    .feature-meta {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-top: 0.35rem;
    }
    .feature-meta .meta-item {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .feature-meta .meta-item span {
        font-weight: 600;
        color: var(--secondary-color);
    }

    .form-switch .form-check-input:checked {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .btn-edit-feature {
        border: 1px solid #dee2e6;
        background: white;
        color: var(--secondary-color);
        border-radius: 0.375rem;
        padding: 0.25rem 0.6rem;
        font-size: 0.8rem;
        transition: all 0.2s;
    }
    .btn-edit-feature:hover {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
    }

    .accordion-button:not(.collapsed) {
        background-color: rgba(143, 174, 93, 0.08);
        color: var(--secondary-color);
        box-shadow: none;
    }
    .accordion-button:focus { box-shadow: 0 0 0 0.15rem rgba(143, 174, 93, 0.25); }

    .connection-btn {
        background: linear-gradient(135deg, var(--secondary-color), #1a2847);
        border: none;
        color: white;
        padding: 0.5rem 1.25rem;
        border-radius: 0.5rem;
        font-weight: 500;
        transition: opacity 0.2s;
    }
    .connection-btn:hover { opacity: 0.9; color: white; }
    .connection-btn:disabled { opacity: 0.6; color: white; }

    .breadcrumb { background: transparent; padding: 0; margin: 0; }
    .breadcrumb-item a { color: var(--primary-color); text-decoration: none; }
    .breadcrumb-item.active { color: var(--secondary-color); }

    .spinner-overlay {
        position: absolute; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(255,255,255,0.8);
        display: flex; align-items: center; justify-content: center;
        border-radius: 0.75rem;
        z-index: 10;
    }

    .modal .form-label {
        font-weight: 600;
        color: var(--secondary-color);
        font-size: 0.875rem;
    }

    .badge-category {
        font-size: 0.7rem;
        padding: 0.3em 0.65em;
        border-radius: 0.35rem;
        font-weight: 500;
    }
</style>

<!-- Breadcrumb e Header -->
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= base_url('ai') ?>"><i class="fas fa-brain me-1"></i>Inteligencia Artificial</a></li>
                <li class="breadcrumb-item active">Configuracoes</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fas fa-cog me-2" style="color: var(--primary-color);"></i>Configuracoes de IA</h2>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if ($is_configured): ?>
            <span class="badge bg-success px-3 py-2"><i class="fas fa-check-circle me-1"></i>API Configurada</span>
        <?php else: ?>
            <span class="badge bg-danger px-3 py-2"><i class="fas fa-exclamation-triangle me-1"></i>API Nao Configurada</span>
        <?php endif; ?>
        <button type="button" class="connection-btn" id="btnTestConnection" onclick="testConnection()">
            <i class="fas fa-plug me-1"></i>Testar Conexao
        </button>
    </div>
</div>

<!-- Connection test result -->
<div id="connectionResult" class="mb-3" style="display:none;"></div>

<!-- API Warning -->
<?php if (!$is_configured): ?>
<div class="alert alert-warning border-0 mb-4 d-flex align-items-center">
    <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
    <div>
        <strong>Atencao:</strong> A variavel de ambiente <code>OPENAI_API_KEY</code> nao esta configurada no servidor.
        As funcionalidades de IA estarao desabilitadas ate que a chave seja definida.
        <br><small class="text-muted">Configure a variavel no arquivo <code>.env</code> ou nas variaveis de ambiente do servidor.</small>
    </div>
</div>
<?php endif; ?>

<!-- Categories -->
<?php
$category_labels = [
    'coleta'       => 'Coleta de Dados',
    'qualidade'    => 'Qualidade de Dados',
    'questionario' => 'Questionarios',
    'analise'      => 'Analise e Relatorios',
];
$category_icons = [
    'coleta'       => 'fas fa-database',
    'qualidade'    => 'fas fa-shield-alt',
    'questionario' => 'fas fa-clipboard-list',
    'analise'      => 'fas fa-chart-line',
];
$category_colors = [
    'coleta'       => 'linear-gradient(135deg, #8fae5d, #6d8a45)',
    'qualidade'    => 'linear-gradient(135deg, #f0ad4e, #ec971f)',
    'questionario' => 'linear-gradient(135deg, #5bc0de, #46b8da)',
    'analise'      => 'linear-gradient(135deg, #23345F, #1a2847)',
];
require_once APPPATH . 'libraries/Ia_provedores.php';
$modelos_por_provedor = Ia_provedores::modelos();
$available_models = array_merge($modelos_por_provedor['openai'], $modelos_por_provedor['anthropic'], ['whisper-1']);
$opcoes_modelo = function ($com_whisper) use ($modelos_por_provedor) {
    $h = '';
    foreach ($modelos_por_provedor as $p => $lista) {
        $h .= '<optgroup label="' . html_escape(Ia_provedores::nome($p)) . '">';
        foreach ($lista as $m) { $h .= '<option value="' . html_escape($m) . '">' . html_escape($m) . '</option>'; }
        $h .= '</optgroup>';
    }
    return $h . ($com_whisper ? '<optgroup label="Transcrição"><option value="whisper-1">whisper-1</option></optgroup>' : '');
};
?>
<div class="alert alert-light border small">
    <i class="fas fa-random me-1"></i> Cada função pode usar <strong>OpenAI</strong> ou <strong>Anthropic (Claude)</strong>, com um modelo de <strong>alternativa</strong> se o principal falhar.
    Chaves, preços, limite mensal e IA por projeto ficam em <a href="<?= base_url('ai/provedores') ?>">Provedores e custos</a>;
    para comparar dois modelos lado a lado, use <a href="<?= base_url('ai/comparar') ?>">Comparar modelos</a>.
    Antes de qualquer envio, CPF, telefone, e-mail e nomes conhecidos são retirados do texto.
</div>

<?php foreach ($category_labels as $cat_key => $cat_label): ?>
    <?php if (empty($settings[$cat_key])) continue; ?>
    <div class="ai-settings-card" id="card-<?= $cat_key ?>">
        <div class="card-header">
            <div class="d-flex align-items-center">
                <div class="ai-category-icon" style="background: <?= $category_colors[$cat_key] ?>;">
                    <i class="<?= $category_icons[$cat_key] ?>"></i>
                </div>
                <h5><?= $cat_label ?></h5>
            </div>
            <span class="badge badge-category bg-secondary">
                <?php
                    $enabled_count = 0;
                    $total_count = count($settings[$cat_key]);
                    foreach ($settings[$cat_key] as $s) {
                        if (!empty($s->is_enabled)) $enabled_count++;
                    }
                ?>
                <?= $enabled_count ?>/<?= $total_count ?> ativos
            </span>
        </div>
        <div class="card-body">
            <?php foreach ($settings[$cat_key] as $setting): ?>
            <div class="feature-row" id="feature-row-<?= $setting->id ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center">
                            <span class="feature-name"><?= htmlspecialchars($setting->feature_name) ?></span>
                            <?php if (!empty($setting->is_enabled)): ?>
                                <span class="badge bg-success ms-2" style="font-size:0.7rem;">Ativo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary ms-2" style="font-size:0.7rem;">Inativo</span>
                            <?php endif; ?>
                        </div>
                        <div class="feature-meta">
                            <div class="meta-item">Modelo: <span><?= htmlspecialchars($setting->model ?? 'gpt-4o-mini') ?></span></div>
                            <div class="meta-item">Temperatura: <span><?= number_format($setting->temperature ?? 0.7, 1) ?></span></div>
                            <div class="meta-item">Max Tokens: <span><?= number_format($setting->max_tokens ?? 2000) ?></span></div>
                            <div class="meta-item">Timeout: <span><?= $setting->timeout_seconds ?? 30 ?>s</span></div>
                            <div class="meta-item">Tentativas: <span><?= $setting->retry_attempts ?? 3 ?></span></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-3">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="toggle-<?= $setting->id ?>"
                                   <?= !empty($setting->is_enabled) ? 'checked' : '' ?>
                                   onchange="toggleFeature(<?= $setting->id ?>, this.checked)">
                        </div>
                        <button type="button" class="btn-edit-feature" onclick="openEditModal(<?= htmlspecialchars(json_encode($setting)) ?>)">
                            <i class="fas fa-pen me-1"></i>Editar
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<!-- Prompts Section -->
<?php if (!empty($prompts)): ?>
<div class="ai-settings-card">
    <div class="card-header">
        <div class="d-flex align-items-center">
            <div class="ai-category-icon" style="background: linear-gradient(135deg, #6f42c1, #5a32a3);">
                <i class="fas fa-scroll"></i>
            </div>
            <h5>Prompts Configurados</h5>
        </div>
        <span class="badge badge-category bg-secondary"><?= count($prompts) ?> prompts</span>
    </div>
    <div class="card-body">
        <div class="accordion" id="promptsAccordion">
            <?php foreach ($prompts as $index => $prompt): ?>
            <div class="accordion-item border-0 border-bottom">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-3 px-4" type="button"
                            data-bs-toggle="collapse" data-bs-target="#prompt-<?= $index ?>">
                        <strong class="me-2"><?= htmlspecialchars($prompt->feature_key ?? '') ?></strong>
                        <small class="text-muted"><?= htmlspecialchars(substr($prompt->prompt_name ?? '', 0, 60)) ?></small>
                    </button>
                </h2>
                <div id="prompt-<?= $index ?>" class="accordion-collapse collapse" data-bs-parent="#promptsAccordion">
                    <div class="accordion-body bg-light">
                        <pre class="mb-0" style="white-space: pre-wrap; font-size: 0.85rem; color: #333;"><?= htmlspecialchars($prompt->user_prompt_template ?? '') ?></pre>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Edit Modal -->
<div class="modal fade" id="editSettingModal" tabindex="-1" aria-labelledby="editSettingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <?= form_open('ai/update_setting', ['id' => 'formEditSetting']) ?>
            <div class="modal-header" style="border-bottom: 2px solid var(--primary-color);">
                <h5 class="modal-title" id="editSettingModalLabel" style="color: var(--secondary-color);">
                    <i class="fas fa-pen me-2"></i>Editar Configuracao
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit_setting_id">

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_feature_name" class="form-label">Nome da Funcionalidade</label>
                            <input type="text" class="form-control" id="edit_feature_name" readonly
                                   style="background-color: #f8f9fa;">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_model" class="form-label">Modelo</label>
                            <select class="form-select" name="model" id="edit_model">
                                <?= $opcoes_modelo(TRUE) ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 offset-md-6">
                        <div class="mb-3">
                            <label for="edit_fallback_model" class="form-label">Alternativa se o modelo falhar</label>
                            <select class="form-select" name="fallback_model" id="edit_fallback_model">
                                <option value="">Nenhuma</option>
                                <?= $opcoes_modelo(FALSE) ?>
                            </select>
                            <div class="form-text">Usada quando o provedor principal está fora do ar, sem chave ou sem crédito.</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="edit_temperature" class="form-label">Temperatura</label>
                            <input type="number" class="form-control" name="temperature" id="edit_temperature"
                                   step="0.1" min="0" max="2" value="0.7">
                            <div class="form-text">0 = preciso, 2 = criativo</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="edit_max_tokens" class="form-label">Max Tokens</label>
                            <input type="number" class="form-control" name="max_tokens" id="edit_max_tokens"
                                   min="100" max="128000" step="100" value="2000">
                            <div class="form-text">Limite de tokens na resposta</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="edit_timeout" class="form-label">Timeout (segundos)</label>
                            <input type="number" class="form-control" name="timeout_seconds" id="edit_timeout"
                                   min="5" max="300" value="30">
                        </div>
                    </div>
                </div>

                <!-- Advanced Parameters -->
                <div class="accordion mb-3" id="advancedAccordion">
                    <div class="accordion-item border">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#advancedParams">
                                <i class="fas fa-sliders-h me-2"></i>Parametros Avancados
                            </button>
                        </h2>
                        <div id="advancedParams" class="accordion-collapse collapse" data-bs-parent="#advancedAccordion">
                            <div class="accordion-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="edit_retry" class="form-label">Tentativas de Retry</label>
                                            <input type="number" class="form-control" name="retry_attempts" id="edit_retry"
                                                   min="0" max="10" value="3">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="edit_top_p" class="form-label">Top P</label>
                                            <input type="number" class="form-control" name="top_p" id="edit_top_p"
                                                   step="0.05" min="0" max="1" value="1">
                                            <div class="form-text">Nucleus sampling</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="edit_frequency_penalty" class="form-label">Penalidade Frequencia</label>
                                            <input type="number" class="form-control" name="frequency_penalty" id="edit_frequency_penalty"
                                                   step="0.1" min="0" max="2" value="0">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="edit_presence_penalty" class="form-label">Penalidade Presenca</label>
                                            <input type="number" class="form-control" name="presence_penalty" id="edit_presence_penalty"
                                                   step="0.1" min="0" max="2" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="edit_stop_sequences" class="form-label">Stop Sequences</label>
                                            <input type="text" class="form-control" name="stop_sequences" id="edit_stop_sequences"
                                                   placeholder="Separar por virgula">
                                            <div class="form-text">Sequencias que interrompem a geracao</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_enabled" value="1" id="edit_is_enabled">
                    <label class="form-check-label" for="edit_is_enabled" style="font-weight:600; color: var(--secondary-color);">
                        Funcionalidade Ativa
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancelar
                </button>
                <button type="submit" class="btn btn-primary" id="btnSaveSetting">
                    <i class="fas fa-save me-1"></i>Salvar Alteracoes
                </button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>

<script>
// Test API Connection
function testConnection() {
    const btn = document.getElementById('btnTestConnection');
    const result = document.getElementById('connectionResult');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Testando...';
    result.style.display = 'none';

    fetch('<?= base_url("ai/test_connection") ?>', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        result.style.display = 'block';
        if (data.success) {
            result.className = 'mb-3 alert alert-success border-0 d-flex align-items-center';
            result.innerHTML = '<i class="fas fa-check-circle me-2 fs-5"></i><div><strong>Conexao bem-sucedida!</strong> ' + (data.message || 'API respondendo normalmente.') + '</div>';
        } else {
            result.className = 'mb-3 alert alert-danger border-0 d-flex align-items-center';
            result.innerHTML = '<i class="fas fa-times-circle me-2 fs-5"></i><div><strong>Falha na conexao.</strong> ' + (data.message || 'Verifique a chave da API.') + '</div>';
        }
    })
    .catch(() => {
        result.style.display = 'block';
        result.className = 'mb-3 alert alert-danger border-0';
        result.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Erro de rede ao testar a conexao.';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plug me-1"></i>Testar Conexao';
    });
}

// Toggle Feature On/Off
function toggleFeature(settingId, enabled) {
    const row = document.getElementById('feature-row-' + settingId);
    const switchEl = document.getElementById('toggle-' + settingId);

    // Show spinner overlay
    const overlay = document.createElement('div');
    overlay.className = 'spinner-overlay';
    overlay.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div>';
    row.style.position = 'relative';
    row.appendChild(overlay);

    fetch('<?= base_url("ai/toggle_feature") ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ setting_id: settingId, is_enabled: enabled ? 1 : 0 })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Update badge
            const badge = row.querySelector('.badge');
            if (enabled) {
                badge.className = 'badge bg-success ms-2';
                badge.style.fontSize = '0.7rem';
                badge.textContent = 'Ativo';
            } else {
                badge.className = 'badge bg-secondary ms-2';
                badge.style.fontSize = '0.7rem';
                badge.textContent = 'Inativo';
            }
            // Update category counter
            updateCategoryCounters();
            showToast('Funcionalidade ' + (enabled ? 'ativada' : 'desativada') + ' com sucesso.', 'success');
        } else {
            switchEl.checked = !enabled;
            showToast(data.message || 'Erro ao alterar status.', 'danger');
        }
    })
    .catch(() => {
        switchEl.checked = !enabled;
        showToast('Erro de rede. Tente novamente.', 'danger');
    })
    .finally(() => {
        overlay.remove();
    });
}

// Update category counters after toggle
function updateCategoryCounters() {
    document.querySelectorAll('.ai-settings-card').forEach(card => {
        const rows = card.querySelectorAll('.feature-row');
        let total = rows.length;
        let enabled = 0;
        rows.forEach(row => {
            const sw = row.querySelector('.form-check-input[type="checkbox"]');
            if (sw && sw.checked) enabled++;
        });
        const badge = card.querySelector('.card-header .badge-category');
        if (badge) {
            badge.textContent = enabled + '/' + total + ' ativos';
        }
    });
}

// Open Edit Modal
function openEditModal(setting) {
    document.getElementById('edit_setting_id').value = setting.id;
    document.getElementById('edit_feature_name').value = setting.feature_name || '';
    document.getElementById('edit_model').value = setting.model || 'gpt-4o-mini';
    document.getElementById('edit_fallback_model').value = setting.fallback_model || '';
    document.getElementById('edit_temperature').value = setting.temperature ?? 0.7;
    document.getElementById('edit_max_tokens').value = setting.max_tokens ?? 2000;
    document.getElementById('edit_timeout').value = setting.timeout_seconds ?? 30;
    document.getElementById('edit_retry').value = setting.retry_attempts ?? 3;
    // O PostgreSQL devolve 't'/'f': parseInt('t') dava falso e o modal abria desligado
    document.getElementById('edit_is_enabled').checked = [true, 1, '1', 't', 'true'].includes(setting.is_enabled);

    // Advanced
    document.getElementById('edit_top_p').value = setting.top_p ?? 1;
    document.getElementById('edit_frequency_penalty').value = setting.frequency_penalty ?? 0;
    document.getElementById('edit_presence_penalty').value = setting.presence_penalty ?? 0;
    document.getElementById('edit_stop_sequences').value = setting.stop_sequences || '';

    // Collapse advanced section
    const advPanel = document.getElementById('advancedParams');
    if (advPanel.classList.contains('show')) {
        bootstrap.Collapse.getInstance(advPanel)?.hide();
    }

    const modal = new bootstrap.Modal(document.getElementById('editSettingModal'));
    modal.show();
}

// Submit Edit Form via AJAX
document.getElementById('formEditSetting').addEventListener('submit', function(e) {
    e.preventDefault();

    const btn = document.getElementById('btnSaveSetting');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Salvando...';

    const formData = new FormData(this);

    fetch(this.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Configuracao salva com sucesso.', 'success');
            // Reload after short delay to reflect changes
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message || 'Erro ao salvar configuracao.', 'danger');
        }
    })
    .catch(() => {
        showToast('Erro de rede. Tente novamente.', 'danger');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

// Toast notification
function showToast(message, type) {
    const existing = document.getElementById('aiSettingsToast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'aiSettingsToast';
    toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:300px;';
    toast.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show border-0 shadow-lg mb-0" role="alert">' +
        '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + ' me-2"></i>' +
        message +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    document.body.appendChild(toast);

    setTimeout(() => {
        const alertEl = toast.querySelector('.alert');
        if (alertEl) {
            alertEl.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }
    }, 4000);
}
</script>
