<style>
    .ai-page-card {
        background: white; border-radius: 0.75rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 1.5rem;
    }
    .ai-page-card .card-header {
        background: white; border-bottom: 2px solid var(--primary-color);
        padding: 1rem 1.25rem;
    }
    .ai-page-card .card-header h5 { margin: 0; color: var(--secondary-color); font-weight: 600; }
    .breadcrumb { background: transparent; padding: 0; margin: 0; }
    .breadcrumb-item a { color: var(--primary-color); text-decoration: none; }
    .breadcrumb-item.active { color: var(--secondary-color); }
    .btn-ai-primary {
        background: linear-gradient(135deg, #8fae5d, #6d8a45);
        border: none; color: white; font-weight: 500;
    }
    .btn-ai-primary:hover { opacity: 0.9; color: white; }
    .btn-ai-primary:disabled { opacity: 0.6; color: white; }
    .empty-state { text-align: center; padding: 3rem 1rem; color: #6c757d; }
    .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.4; }
    .loading-overlay { display: none; text-align: center; padding: 2rem; }
    .loading-overlay.active { display: block; }
    .chart-wrapper {
        background: white; border: 1px solid #e9ecef; border-radius: 0.75rem;
        overflow: hidden; margin-bottom: 1.5rem;
    }
    .chart-wrapper .chart-title {
        font-weight: 600; color: var(--secondary-color);
        padding: 1rem 1.25rem; border-bottom: 1px solid #f0f0f0;
        display: flex; justify-content: space-between; align-items: center;
    }
    .chart-container { position: relative; height: 350px; padding: 1rem; }
    .chart-controls {
        display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;
    }
    .chart-type-selector, .chart-display-selector {
        font-size: 0.8rem; padding: 0.2rem 0.5rem;
        border: 1px solid #dee2e6; border-radius: 0.25rem;
        background: white; color: var(--secondary-color);
    }
    .chart-description {
        padding: 1rem 1.25rem;
        background: linear-gradient(135deg, rgba(143,174,93,0.06), rgba(35,52,95,0.03));
        border-top: 1px solid #f0f0f0;
    }
    .chart-description .desc-text {
        color: #444; font-size: 0.9rem; line-height: 1.6; margin: 0;
    }
    .question-ref-link {
        color: var(--primary-color); text-decoration: none;
        border-bottom: 1px dashed var(--primary-color); cursor: pointer;
    }
    .question-ref-link:hover {
        color: var(--secondary-color); border-bottom-color: var(--secondary-color);
    }
    .question-modal-options .opt-item {
        display: inline-block; background: #e9ecef;
        padding: 0.25rem 0.6rem; border-radius: 0.25rem;
        font-size: 0.85rem; margin: 0.2rem;
    }
</style>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= base_url('ai') ?>"><i class="fas fa-brain me-1"></i>Inteligência Artificial</a></li>
                <li class="breadcrumb-item active">Gráficos Inteligentes</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fas fa-chart-pie me-2" style="color: var(--primary-color);"></i>Gráficos Inteligentes</h2>
    </div>
    <a href="<?= base_url('ai') ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Voltar
    </a>
</div>

<?php if (!$is_enabled): ?>
<div class="alert alert-warning border-0 mb-4">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Atenção:</strong> Este recurso está desativado. Ative-o nas <a href="<?= base_url('ai/settings') ?>">configurações</a>.
</div>
<?php endif; ?>

<!-- Formulário -->
<div class="ai-page-card">
    <div class="card-header">
        <h5><i class="fas fa-cogs me-2"></i>Gerar Gráficos</h5>
    </div>
    <div class="card-body p-3">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="questionnaire_id" class="form-label fw-bold" style="color: var(--secondary-color);">Questionário</label>
                <select class="form-select" id="questionnaire_id" <?= !$is_enabled ? 'disabled' : '' ?>>
                    <option value="">Selecione um questionário...</option>
                    <?php foreach ($questionnaires as $q): ?>
                        <option value="<?= $q->id ?>"><?= htmlspecialchars($q->title ?? $q->name ?? 'Questionário #' . $q->id) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="date_from" class="form-label fw-bold" style="color: var(--secondary-color);">Data Início</label>
                <input type="date" class="form-control" id="date_from" <?= !$is_enabled ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label fw-bold" style="color: var(--secondary-color);">Data Fim</label>
                <input type="date" class="form-control" id="date_to" <?= !$is_enabled ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-ai-primary w-100" id="btnGenerate" <?= !$is_enabled ? 'disabled' : '' ?>>
                    <i class="fas fa-bolt me-1"></i>Gerar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Loading -->
<div class="loading-overlay" id="loadingIndicator">
    <div class="spinner-border text-success mb-3" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Carregando...</span>
    </div>
    <p class="text-muted">Analisando dados e gerando gráficos inteligentes...</p>
</div>

<!-- Container de Gráficos -->
<div id="chartsContainer">
    <div class="empty-state" id="emptyState">
        <i class="fas fa-chart-bar d-block"></i>
        <h5>Nenhum gráfico gerado</h5>
        <p>Selecione um questionário e clique em "Gerar" para visualizar gráficos sugeridos pela IA.</p>
    </div>
</div>

<!-- Modal: Detalhes da Pergunta -->
<div class="modal fade" id="questionDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), #1a2847); color: white;">
                <h5 class="modal-title" id="questionModalTitle"><i class="fas fa-question-circle me-2"></i>Pergunta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="questionModalBody">
                <div class="text-center py-3"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
(function waitForJQuery() {
    if (typeof jQuery === 'undefined') return setTimeout(waitForJQuery, 50);
    jQuery(function($) {

var BASE = '<?= base_url() ?>';
var chartInstances = [];
var chartDataStore = [];
var questionCache = {};

var colorPalette = [
    'rgba(143, 174, 93, 0.7)', 'rgba(35, 52, 95, 0.7)', 'rgba(91, 192, 222, 0.7)',
    'rgba(240, 173, 78, 0.7)', 'rgba(217, 83, 79, 0.7)', 'rgba(111, 66, 193, 0.7)',
    'rgba(32, 201, 151, 0.7)', 'rgba(253, 126, 20, 0.7)', 'rgba(102, 16, 242, 0.7)',
    'rgba(13, 110, 253, 0.7)'
];
var borderPalette = [
    'rgba(143, 174, 93, 1)', 'rgba(35, 52, 95, 1)', 'rgba(91, 192, 222, 1)',
    'rgba(240, 173, 78, 1)', 'rgba(217, 83, 79, 1)', 'rgba(111, 66, 193, 1)',
    'rgba(32, 201, 151, 1)', 'rgba(253, 126, 20, 1)', 'rgba(102, 16, 242, 1)',
    'rgba(13, 110, 253, 1)'
];

$('#btnGenerate').on('click', function() {
    var qId = $('#questionnaire_id').val();
    if (!qId) { showToast('Selecione um questionário.', 'warning'); return; }

    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Gerando...';
    $('#loadingIndicator').addClass('active');
    $('#emptyState').hide();

    chartInstances.forEach(function(c) { if (c) c.destroy(); });
    chartInstances = [];
    chartDataStore = [];

    $.ajax({
        url: BASE + 'ai/generate_charts',
        type: 'POST',
        data: {
            questionnaire_id: qId,
            date_from: $('#date_from').val(),
            date_to: $('#date_to').val()
        },
        dataType: 'json',
        timeout: 120000,
        success: function(data) {
            if (data.success && data.charts && data.charts.length > 0) {
                renderCharts(data.charts, qId);
                showToast('Gráficos gerados com sucesso!', 'success');
            } else if (data.success && (!data.charts || data.charts.length === 0)) {
                $('#emptyState').show();
                showToast('Nenhum gráfico sugerido para estes dados.', 'warning');
            } else {
                $('#emptyState').show();
                showToast(data.message || 'Erro ao gerar gráficos.', 'danger');
            }
        },
        error: function(xhr) {
            $('#emptyState').show();
            showToast(xhr.statusText === 'timeout' ? 'Tempo esgotado.' : 'Erro de rede.', 'danger');
        },
        complete: function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-bolt me-1"></i>Gerar';
            $('#loadingIndicator').removeClass('active');
        }
    });
});

function linkifyQuestions(text) {
    return escapeHtml(text).replace(/\bq_(\d+)\b/g, '<a href="javascript:void(0)" class="question-ref-link" data-question-id="$1"><strong>q_$1</strong></a>');
}

function renderCharts(charts, questionnaireId) {
    var container = document.getElementById('chartsContainer');
    container.innerHTML = '<div class="row" id="chartsRow"></div>';
    var row = document.getElementById('chartsRow');

    charts.forEach(function(chart, index) {
        var config = chart.data_config || chart.config || chart;
        var chartType = chart.chart_type || config.type || 'bar';
        var title = chart.title || 'Gráfico ' + (index + 1);
        var labels = config.labels || [];
        var rawData = config.data || null;
        var datasets = config.datasets || [];

        // Handle distribution objects
        if (!rawData && !datasets.length && chart.distribution && typeof chart.distribution === 'object') {
            labels = Object.keys(chart.distribution);
            rawData = Object.values(chart.distribution);
        }

        if (datasets.length === 0 && rawData) {
            datasets = [{
                label: title,
                data: rawData,
                backgroundColor: colorPalette.slice(0, rawData.length),
                borderColor: borderPalette.slice(0, rawData.length),
                borderWidth: 1
            }];
        }

        datasets.forEach(function(ds, i) {
            if (!ds.backgroundColor) {
                if (['pie', 'doughnut'].indexOf(chartType) >= 0) {
                    ds.backgroundColor = colorPalette.slice(0, (ds.data || []).length);
                    ds.borderColor = borderPalette.slice(0, (ds.data || []).length);
                } else {
                    ds.backgroundColor = colorPalette[i % colorPalette.length];
                    ds.borderColor = borderPalette[i % borderPalette.length];
                }
                ds.borderWidth = 1;
            }
        });

        // Store data for toggling
        chartDataStore[index] = {
            labels: labels,
            datasets: JSON.parse(JSON.stringify(datasets)),
            chartType: chartType,
            title: title,
            questionnaireId: questionnaireId
        };

        var colSize = charts.length === 1 ? 'col-12' : 'col-md-6';
        var wrapper = document.createElement('div');
        wrapper.className = colSize;
        wrapper.innerHTML =
            '<div class="chart-wrapper">' +
                '<div class="chart-title">' +
                    '<span>' + linkifyQuestions(title) + '</span>' +
                    '<div class="chart-controls">' +
                        '<select class="chart-display-selector" data-idx="' + index + '">' +
                            '<option value="absolute">Absoluto</option>' +
                            '<option value="percent">Percentual</option>' +
                        '</select>' +
                        '<select class="chart-type-selector" data-idx="' + index + '">' +
                            '<option value="bar"' + (chartType === 'bar' ? ' selected' : '') + '>Barras</option>' +
                            '<option value="line"' + (chartType === 'line' ? ' selected' : '') + '>Linha</option>' +
                            '<option value="pie"' + (chartType === 'pie' ? ' selected' : '') + '>Pizza</option>' +
                            '<option value="doughnut"' + (chartType === 'doughnut' ? ' selected' : '') + '>Rosca</option>' +
                            '<option value="radar"' + (chartType === 'radar' ? ' selected' : '') + '>Radar</option>' +
                        '</select>' +
                    '</div>' +
                '</div>' +
                '<div class="chart-container"><canvas id="ai-chart-' + index + '"></canvas></div>' +
                '<div class="chart-description" id="chart-desc-' + index + '">' +
                    '<p class="desc-text mb-0 text-muted"><i class="fas fa-robot me-2" style="color: var(--primary-color);"></i>' +
                    '<span class="spinner-border spinner-border-sm me-1"></span>Gerando descrição...</p>' +
                '</div>' +
            '</div>';
        row.appendChild(wrapper);

        var isPercent = false;
        buildChart(index, chartType, labels, datasets, isPercent);
        generateDescription(index, title, labels, datasets[0] ? datasets[0].data : [], questionnaireId);
    });
}

function buildChart(index, type, labels, datasets, asPercent) {
    var canvas = document.getElementById('ai-chart-' + index);
    if (!canvas) return;

    if (chartInstances[index]) chartInstances[index].destroy();

    var displayDatasets = JSON.parse(JSON.stringify(datasets));

    if (asPercent) {
        displayDatasets.forEach(function(ds) {
            var total = 0;
            (ds.data || []).forEach(function(v) { total += (parseFloat(v) || 0); });
            if (total > 0) {
                ds.data = ds.data.map(function(v) { return parseFloat(((parseFloat(v) || 0) / total * 100).toFixed(1)); });
            }
        });
    }

    // Adjust colors for pie/doughnut
    displayDatasets.forEach(function(ds, i) {
        if (['pie', 'doughnut'].indexOf(type) >= 0) {
            ds.backgroundColor = colorPalette.slice(0, (ds.data || []).length);
            ds.borderColor = borderPalette.slice(0, (ds.data || []).length);
        } else {
            ds.backgroundColor = colorPalette[i % colorPalette.length];
            ds.borderColor = borderPalette[i % borderPalette.length];
        }
    });

    // Check if labels contain q_XXX patterns
    var hasQuestionLabels = labels.some(function(l) { return /^q_\d+$/.test(l); });

    var options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    };

    if (asPercent && ['bar', 'line', 'radar'].indexOf(type) >= 0) {
        options.scales = { y: { ticks: { callback: function(v) { return v + '%'; } } } };
    }
    if (asPercent && ['pie', 'doughnut'].indexOf(type) >= 0) {
        options.plugins.tooltip = {
            callbacks: { label: function(ctx) { return ctx.label + ': ' + ctx.parsed + '%'; } }
        };
    }

    // Style q_XXX labels as clickable links on X axis
    if (hasQuestionLabels && ['bar', 'line'].indexOf(type) >= 0) {
        if (!options.scales) options.scales = {};
        options.scales.x = options.scales.x || {};
        options.scales.x.ticks = options.scales.x.ticks || {};
        options.scales.x.ticks.color = function(ctx) {
            var label = labels[ctx.index] || '';
            return /^q_\d+$/.test(label) ? '#8fae5d' : '#666';
        };
        options.scales.x.ticks.font = function(ctx) {
            var label = labels[ctx.index] || '';
            return /^q_\d+$/.test(label) ? { weight: 'bold', size: 12 } : {};
        };
        // Cursor pointer on hover over labels
        options.onHover = function(event, elements, chart) {
            var labelClicked = getLabelAtEvent(chart, event);
            canvas.style.cursor = labelClicked ? 'pointer' : 'default';
        };
    }

    // For pie/doughnut, style legend labels
    if (hasQuestionLabels && ['pie', 'doughnut'].indexOf(type) >= 0) {
        options.plugins.legend.labels = {
            color: function(ctx) {
                var label = labels[ctx.index] || '';
                return /^q_\d+$/.test(label) ? '#8fae5d' : '#666';
            },
            font: { weight: 'bold' }
        };
    }

    chartInstances[index] = new Chart(canvas, {
        type: type,
        data: { labels: labels, datasets: displayDatasets },
        options: options
    });

    // Click handler for axis labels & legend
    canvas.onclick = function(evt) {
        var chart = chartInstances[index];
        if (!chart) return;

        // Check axis label click (bar/line)
        var labelValue = getLabelAtEvent(chart, evt);
        if (labelValue && /^q_(\d+)$/.test(labelValue)) {
            var qId = labelValue.match(/^q_(\d+)$/)[1];
            openQuestionModal(qId);
            return;
        }

        // Check legend click (pie/doughnut) — handled via plugin below
        // Check tooltip data click
        var points = chart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, false);
        if (points.length > 0) {
            var clickedLabel = labels[points[0].index];
            if (clickedLabel && /^q_(\d+)$/.test(clickedLabel)) {
                openQuestionModal(clickedLabel.match(/^q_(\d+)$/)[1]);
            }
        }
    };
}

// Helper: detect if click is on an axis label
function getLabelAtEvent(chart, event) {
    var scales = chart.scales;
    if (!scales || !scales.x) return null;
    var xScale = scales.x;
    var rect = chart.canvas.getBoundingClientRect();
    var y = event.y !== undefined ? event.y : (event.native ? event.native.clientY - rect.top : null);
    var x = event.x !== undefined ? event.x : (event.native ? event.native.clientX - rect.left : null);
    if (y === null || x === null) return null;

    // Only respond if click is in the label area (below the chart area)
    if (y < xScale.top || y > xScale.bottom + 20) return null;

    // Find which label was clicked
    for (var i = 0; i < xScale.ticks.length; i++) {
        var pos = xScale.getPixelForTick(i);
        var halfStep = (xScale.width / xScale.ticks.length) / 2;
        if (x >= pos - halfStep && x <= pos + halfStep) {
            return chart.data.labels[i] || null;
        }
    }
    return null;
}

function openQuestionModal(qId) {
    var modal = new bootstrap.Modal(document.getElementById('questionDetailModal'));
    $('#questionModalTitle').html('<i class="fas fa-question-circle me-2"></i>Pergunta #' + qId);
    $('#questionModalBody').html('<div class="text-center py-3"><div class="spinner-border text-primary"></div></div>');
    modal.show();

    if (questionCache[qId]) { renderQuestion(questionCache[qId]); return; }

    $.ajax({
        url: BASE + 'ai/get_question_detail',
        type: 'GET',
        data: { id: qId },
        dataType: 'json',
        success: function(res) {
            if (res.success) { questionCache[qId] = res.question; renderQuestion(res.question); }
            else $('#questionModalBody').html('<div class="alert alert-warning mb-0">Pergunta não encontrada.</div>');
        },
        error: function() { $('#questionModalBody').html('<div class="alert alert-danger mb-0">Erro ao buscar.</div>'); }
    });
}

// Chart type change
$(document).on('change', '.chart-type-selector', function() {
    var idx = $(this).data('idx');
    var newType = $(this).val();
    var store = chartDataStore[idx];
    if (!store) return;
    store.chartType = newType;
    var isPercent = $('[data-idx="' + idx + '"].chart-display-selector').val() === 'percent';
    buildChart(idx, newType, store.labels, store.datasets, isPercent);
});

// Display mode change (absolute/percent)
$(document).on('change', '.chart-display-selector', function() {
    var idx = $(this).data('idx');
    var mode = $(this).val();
    var store = chartDataStore[idx];
    if (!store) return;
    var type = $('[data-idx="' + idx + '"].chart-type-selector').val() || store.chartType;
    buildChart(idx, type, store.labels, store.datasets, mode === 'percent');
});

// Generate AI description
function generateDescription(index, title, labels, data, questionnaireId) {
    var chartData = {};
    if (labels && data) {
        for (var i = 0; i < labels.length; i++) {
            chartData[labels[i]] = data[i] || 0;
        }
    }

    $.ajax({
        url: BASE + 'client/generate_chart_description',
        type: 'POST',
        data: {
            chart_title: title,
            chart_data: JSON.stringify(chartData),
            questionnaire_id: questionnaireId
        },
        dataType: 'json',
        timeout: 30000,
        success: function(res) {
            var el = document.getElementById('chart-desc-' + index);
            if (res.success && res.description) {
                el.innerHTML = '<p class="desc-text mb-0"><i class="fas fa-robot me-2" style="color: var(--primary-color);"></i>' +
                    linkifyQuestions(res.description) + '</p>';
            } else {
                showFallbackDesc(index, title, labels, data);
            }
        },
        error: function() { showFallbackDesc(index, title, labels, data); }
    });
}

function showFallbackDesc(index, title, labels, data) {
    var el = document.getElementById('chart-desc-' + index);
    var total = 0;
    if (data) data.forEach(function(v) { total += (parseFloat(v) || 0); });

    var desc = 'Distribuição de respostas para ' + title.replace(/q_(\d+)/g, 'pergunta #$1') + '.';
    if (labels && labels.length > 0 && data && total > 0) {
        var maxIdx = 0;
        for (var i = 1; i < data.length; i++) {
            if ((parseFloat(data[i]) || 0) > (parseFloat(data[maxIdx]) || 0)) maxIdx = i;
        }
        var pct = ((parseFloat(data[maxIdx]) || 0) / total * 100).toFixed(1);
        desc += ' A resposta mais frequente foi "' + labels[maxIdx] + '" com ' + pct + '% do total.';
    }
    el.innerHTML = '<p class="desc-text mb-0"><i class="fas fa-info-circle me-2" style="color: var(--primary-color);"></i>' + escapeHtml(desc) + '</p>';
}

// Question ref modal (titles & descriptions)
$(document).on('click', '.question-ref-link', function(e) {
    e.preventDefault();
    openQuestionModal($(this).data('question-id'));
});

function renderQuestion(q) {
    var typeLabels = {
        'text': 'Texto', 'textarea': 'Texto Longo', 'number': 'Número',
        'email': 'E-mail', 'phone': 'Telefone', 'date': 'Data', 'datetime': 'Data/Hora',
        'radio': 'Escolha Única', 'checkbox': 'Múltipla Escolha', 'select': 'Seleção'
    };
    var html = '<div class="mb-3">' +
        '<span class="badge bg-secondary me-2">ID: ' + q.id + '</span>' +
        '<span class="badge bg-info">' + (typeLabels[q.question_type] || q.question_type) + '</span>' +
        (q.is_required == 1 ? '<span class="badge bg-danger ms-1">Obrigatória</span>' : '') +
        '</div>' +
        '<div class="p-3 rounded mb-3" style="background: #f8f9fa; border-left: 4px solid var(--primary-color);">' +
        '<h6 class="mb-0" style="color: var(--secondary-color);">' + escapeHtml(q.question_text) + '</h6></div>';
    if (q.questionnaire_title) {
        html += '<p class="small text-muted mb-2"><i class="fas fa-clipboard-list me-1"></i>Questionário: <strong>' + escapeHtml(q.questionnaire_title) + '</strong></p>';
    }
    if (q.options && q.options.length > 0) {
        html += '<p class="small text-muted mb-1"><i class="fas fa-list me-1"></i>Opções:</p><div class="question-modal-options">';
        q.options.forEach(function(opt) { html += '<span class="opt-item">' + escapeHtml(opt.option_text || opt) + '</span>'; });
        html += '</div>';
    }
    if (q.order_index !== undefined && q.order_index !== null) {
        html += '<p class="small text-muted mt-3 mb-0"><i class="fas fa-sort-numeric-down me-1"></i>Posição: <strong>#' + q.order_index + '</strong></p>';
    }
    $('#questionModalBody').html(html);
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

function showToast(message, type) {
    var existing = document.getElementById('aiToast');
    if (existing) existing.remove();
    var toast = document.createElement('div');
    toast.id = 'aiToast';
    toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:300px;';
    toast.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show border-0 shadow-lg mb-0" role="alert">' +
        '<i class="fas fa-' + (type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle') + ' me-2"></i>' +
        message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    document.body.appendChild(toast);
    setTimeout(function() { if (toast.parentNode) toast.remove(); }, 4000);
}

    });
})();
</script>
