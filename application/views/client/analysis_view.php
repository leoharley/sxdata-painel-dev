<style>
    .client-chart-card {
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }
    .client-chart-header {
        padding: 1rem 1.25rem;
        border-bottom: 2px solid var(--primary-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .client-chart-header h6 {
        margin: 0;
        color: var(--secondary-color);
        font-weight: 600;
    }
    .chart-container {
        position: relative;
        height: 320px;
        padding: 1rem;
    }
    .chart-description {
        padding: 1rem 1.25rem;
        background: linear-gradient(135deg, rgba(143,174,93,0.06), rgba(35,52,95,0.03));
        border-top: 1px solid #f0f0f0;
    }
    .chart-description .desc-text {
        color: #444;
        font-size: 0.95rem;
        line-height: 1.6;
    }
    .chart-description .desc-loading {
        color: #999;
        font-size: 0.9rem;
    }
    .chart-controls {
        display: flex; gap: 0.5rem; align-items: center;
    }
    .chart-controls select {
        font-size: 0.78rem; padding: 0.2rem 0.4rem;
        border: 1px solid #dee2e6; border-radius: 0.25rem;
        background: white; color: var(--secondary-color);
    }
    .question-ref-link {
        color: var(--primary-color);
        text-decoration: none;
        border-bottom: 1px dashed var(--primary-color);
        cursor: pointer;
    }
    .question-ref-link:hover {
        color: var(--secondary-color);
        border-bottom-color: var(--secondary-color);
    }
    .question-modal-options .opt-item {
        display: inline-block;
        background: #e9ecef;
        padding: 0.25rem 0.6rem;
        border-radius: 0.25rem;
        font-size: 0.85rem;
        margin: 0.2rem;
    }
    .analysis-header-card {
        background: linear-gradient(135deg, rgba(143,174,93,0.1), rgba(35,52,95,0.05));
        border: 1px solid rgba(143,174,93,0.2);
        border-radius: 0.75rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #6c757d;
    }
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.4;
    }
</style>

<?php
    $chart_suggestions = is_string($analysis->chart_suggestions ?? '') ? json_decode($analysis->chart_suggestions, true) : ($analysis->chart_suggestions ?? array());

    function client_linkify_questions($text, $questions_map) {
        $escaped = htmlspecialchars($text);
        return preg_replace_callback('/\bq_(\d+)\b/', function($m) use ($questions_map) {
            $qid = $m[1];
            $title = isset($questions_map[$qid]) ? htmlspecialchars($questions_map[$qid]->question_text) : 'Pergunta #' . $qid;
            return '<a href="javascript:void(0)" class="question-ref-link" data-question-id="' . $qid . '" title="' . $title . '"><strong>q_' . $qid . '</strong></a>';
        }, $escaped);
    }
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h2>
            <i class="fas fa-chart-pie me-2" style="color: var(--primary-color);"></i>
            <?= htmlspecialchars($analysis->questionnaire_title ?? 'Análise #' . $analysis->id) ?>
        </h2>
        <small class="text-muted">
            Gerada em <?= date('d/m/Y H:i', strtotime($analysis->created_at)) ?>
        </small>
    </div>
    <a href="<?= base_url('client/dashboard') ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Voltar
    </a>
</div>

<!-- Resumo -->
<?php
    $summary_raw = $analysis->summary_text ?? '';
    $summary_decoded = json_decode($summary_raw, true);
?>
<?php if (!empty($summary_raw)): ?>
<div class="analysis-header-card">
    <h5 style="color: var(--secondary-color); font-weight: 600;">
        <i class="fas fa-file-alt me-2" style="color: var(--primary-color);"></i>Resumo da Análise
    </h5>
    <?php if (is_array($summary_decoded)): ?>
        <div class="row g-3 mt-1">
        <?php foreach ($summary_decoded as $key => $value): ?>
            <div class="col-md-4">
                <div class="p-3 bg-white rounded border">
                    <small class="text-muted d-block mb-1" style="text-transform: capitalize;">
                        <?= htmlspecialchars(str_replace('_', ' ', $key)) ?>
                    </small>
                    <?php if (is_array($value)): ?>
                        <ul class="mb-0 ps-3 small">
                        <?php foreach ($value as $k => $v): ?>
                            <li><?= htmlspecialchars(is_string($k) ? str_replace('_', ' ', $k) . ': ' : '') ?><strong><?= is_scalar($v) ? client_linkify_questions((string)$v, $questions_map) : htmlspecialchars(json_encode($v)) ?></strong></li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <strong style="font-size: 1.4rem; color: var(--secondary-color);"><?= is_scalar($value) ? client_linkify_questions((string)$value, $questions_map) : htmlspecialchars(json_encode($value)) ?></strong>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="mb-0" style="font-size: 1.05rem; line-height: 1.7; color: #333;">
            <?= nl2br(client_linkify_questions($summary_raw, $questions_map)) ?>
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Gráficos -->
<?php if (empty($chart_suggestions)): ?>
    <div class="empty-state">
        <i class="fas fa-chart-bar d-block"></i>
        <h5>Nenhum gráfico disponível</h5>
        <p>Esta análise não possui gráficos sugeridos.</p>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($chart_suggestions as $index => $chart):
            $chartTitle = $chart['title'] ?? 'Gráfico ' . ($index + 1);
        ?>
        <div class="col-lg-6">
            <div class="client-chart-card">
                <div class="client-chart-header">
                    <h6 class="mb-0"><?= client_linkify_questions($chartTitle, $questions_map) ?></h6>
                    <div class="chart-controls">
                        <select class="client-display-sel" data-idx="<?= $index ?>">
                            <option value="absolute">Absoluto</option>
                            <option value="percent">Percentual</option>
                        </select>
                        <select class="client-type-sel" data-idx="<?= $index ?>">
                            <option value="<?= htmlspecialchars($chart['chart_type'] ?? $chart['type'] ?? 'bar') ?>" selected><?= ucfirst($chart['chart_type'] ?? $chart['type'] ?? 'bar') ?></option>
                            <option value="bar">Barras</option>
                            <option value="line">Linha</option>
                            <option value="pie">Pizza</option>
                            <option value="doughnut">Rosca</option>
                            <option value="radar">Radar</option>
                        </select>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="chart-<?= $index ?>"></canvas>
                </div>
                <div class="chart-description" id="desc-<?= $index ?>">
                    <div class="desc-loading">
                        <i class="fas fa-robot me-2" style="color: var(--primary-color);"></i>
                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                        Gerando descrição explicativa...
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal: Detalhes da Pergunta -->
<div class="modal fade" id="questionDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), #1a2847); color: white;">
                <h5 class="modal-title" id="questionModalTitle"><i class="fas fa-question-circle me-2"></i>Pergunta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="questionModalBody">
                <div class="text-center py-3">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var BASE = '<?= base_url() ?>';
    var questionnaireId = '<?= $analysis->questionnaire_id ?? '' ?>';
    var chartSuggestions = <?= json_encode($chart_suggestions ?: array()) ?>;

    // Mapa de perguntas pré-carregado do PHP
    var questionsMap = <?= json_encode(array_map(function($q) {
        return array(
            'id' => $q->id,
            'question_text' => $q->question_text,
            'question_type' => $q->question_type ?? 'text',
            'is_required' => $q->is_required ?? 0,
            'order_index' => $q->order_index ?? null,
        );
    }, $questions_map), JSON_UNESCAPED_UNICODE) ?>;

    var colorPalette = [
        'rgba(143, 174, 93, 0.7)', 'rgba(35, 52, 95, 0.7)', 'rgba(91, 192, 222, 0.7)',
        'rgba(240, 173, 78, 0.7)', 'rgba(217, 83, 79, 0.7)', 'rgba(111, 66, 193, 0.7)',
        'rgba(32, 201, 151, 0.7)', 'rgba(253, 126, 20, 0.7)'
    ];
    var borderPalette = [
        'rgba(143, 174, 93, 1)', 'rgba(35, 52, 95, 1)', 'rgba(91, 192, 222, 1)',
        'rgba(240, 173, 78, 1)', 'rgba(217, 83, 79, 1)', 'rgba(111, 66, 193, 1)',
        'rgba(32, 201, 151, 1)', 'rgba(253, 126, 20, 1)'
    ];

    var chartInstances = [];
    var chartDataStore = [];

    chartSuggestions.forEach(function(chart, index) {
        var canvas = document.getElementById('chart-' + index);
        if (!canvas) return;

        var chartType = chart.chart_type || chart.type || 'bar';
        var labels = chart.labels || (chart.data_config && chart.data_config.labels) || [];
        var rawData = chart.data || chart.values || chart.counts ||
                      (chart.data_config && (chart.data_config.data || chart.data_config.values)) || null;

        if (!rawData && chart.distribution && typeof chart.distribution === 'object') {
            labels = Object.keys(chart.distribution);
            rawData = Object.values(chart.distribution);
        }

        var datasets = chart.datasets || (chart.data_config && chart.data_config.datasets) || [];
        if (datasets.length === 0 && rawData && rawData.length > 0) {
            datasets = [{
                label: chart.title || 'Dados',
                data: rawData,
                backgroundColor: colorPalette.slice(0, rawData.length),
                borderColor: borderPalette.slice(0, rawData.length),
                borderWidth: 1
            }];
        }

        if (datasets.length === 0 || !datasets[0].data || datasets[0].data.length === 0) {
            canvas.closest('.chart-container').innerHTML =
                '<div class="text-center text-muted py-5"><i class="fas fa-chart-bar fa-2x mb-2 d-block" style="opacity:0.3;"></i>Dados insuficientes</div>';
            document.getElementById('desc-' + index).innerHTML =
                '<p class="desc-text text-muted mb-0"><i class="fas fa-info-circle me-1"></i>Não há dados suficientes para gerar este gráfico.</p>';
            return;
        }

        datasets.forEach(function(ds, i) {
            if (!ds.backgroundColor) {
                ds.backgroundColor = (chartType === 'pie' || chartType === 'doughnut')
                    ? colorPalette.slice(0, (ds.data || []).length)
                    : colorPalette[i % colorPalette.length];
                ds.borderColor = (chartType === 'pie' || chartType === 'doughnut')
                    ? borderPalette.slice(0, (ds.data || []).length)
                    : borderPalette[i % borderPalette.length];
                ds.borderWidth = 1;
            }
        });

        // Store data for switching
        chartDataStore[index] = {
            labels: labels,
            datasets: JSON.parse(JSON.stringify(datasets)),
            chartType: chartType
        };

        buildClientChart(index, chartType, labels, datasets, false);

        // Deduplicate type selector options
        var typeSel = document.querySelector('.client-type-sel[data-idx="' + index + '"]');
        if (typeSel) {
            var seen = {};
            Array.from(typeSel.options).forEach(function(opt) {
                var v = opt.value.toLowerCase();
                if (seen[v]) { opt.remove(); }
                else { seen[v] = true; opt.value = v; }
            });
        }

        // Generate AI description
        generateDescription(index, chart, labels, rawData || (datasets[0] ? datasets[0].data : []));
    });

    function buildClientChart(index, type, labels, datasets, asPercent) {
        var canvas = document.getElementById('chart-' + index);
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

        displayDatasets.forEach(function(ds, i) {
            if (['pie', 'doughnut'].indexOf(type) >= 0) {
                ds.backgroundColor = colorPalette.slice(0, (ds.data || []).length);
                ds.borderColor = borderPalette.slice(0, (ds.data || []).length);
            } else {
                ds.backgroundColor = colorPalette[i % colorPalette.length];
                ds.borderColor = borderPalette[i % borderPalette.length];
            }
        });

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

        chartInstances[index] = new Chart(canvas, {
            type: type,
            data: { labels: labels, datasets: displayDatasets },
            options: options
        });
    }

    // Type change
    $(document).on('change', '.client-type-sel', function() {
        var idx = $(this).data('idx');
        var store = chartDataStore[idx];
        if (!store) return;
        var isPercent = $('[data-idx="' + idx + '"].client-display-sel').val() === 'percent';
        buildClientChart(idx, $(this).val(), store.labels, store.datasets, isPercent);
    });

    // Display mode change
    $(document).on('change', '.client-display-sel', function() {
        var idx = $(this).data('idx');
        var store = chartDataStore[idx];
        if (!store) return;
        var type = $('[data-idx="' + idx + '"].client-type-sel').val() || store.chartType;
        buildClientChart(idx, type, store.labels, store.datasets, $(this).val() === 'percent');
    });

    function generateDescription(index, chart, labels, data) {
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
                chart_title: chart.title || 'Gráfico ' + (index + 1),
                chart_data: JSON.stringify(chartData),
                questionnaire_id: questionnaireId
            },
            dataType: 'json',
            timeout: 30000,
            success: function(res) {
                var el = document.getElementById('desc-' + index);
                if (res.success && res.description) {
                    el.innerHTML = '<p class="desc-text mb-0"><i class="fas fa-robot me-2" style="color: var(--primary-color);"></i>' +
                        escapeHtml(res.description) + '</p>';
                } else {
                    showFallbackDescription(index, chart, labels, data);
                }
            },
            error: function() {
                showFallbackDescription(index, chart, labels, data);
            }
        });
    }

    function showFallbackDescription(index, chart, labels, data) {
        var el = document.getElementById('desc-' + index);
        var title = chart.title || 'Gráfico';

        // Resolver q_XXX no título
        var resolvedTitle = title.replace(/q_(\d+)/g, function(match, id) {
            if (questionsMap[id]) return '"' + questionsMap[id].question_text + '"';
            return match;
        });

        var total = 0;
        if (data) data.forEach(function(v) { total += (parseFloat(v) || 0); });

        var desc = 'Este gráfico apresenta a distribuição de respostas para ' + resolvedTitle + '.';
        if (labels && labels.length > 0 && data && total > 0) {
            var maxIdx = 0;
            for (var i = 1; i < data.length; i++) {
                if ((parseFloat(data[i]) || 0) > (parseFloat(data[maxIdx]) || 0)) maxIdx = i;
            }
            var pct = ((parseFloat(data[maxIdx]) || 0) / total * 100).toFixed(1);
            desc += ' A resposta mais frequente foi "' + labels[maxIdx] + '" com ' + pct + '% do total.';
        }

        el.innerHTML = '<p class="desc-text mb-0"><i class="fas fa-info-circle me-2" style="color: var(--primary-color);"></i>' +
            escapeHtml(desc) + '</p>';
    }

    // Question ref modal
    $(document).on('click', '.question-ref-link', function(e) {
        e.preventDefault();
        var qId = $(this).data('question-id');
        var modal = new bootstrap.Modal(document.getElementById('questionDetailModal'));

        $('#questionModalTitle').html('<i class="fas fa-question-circle me-2"></i>Pergunta #' + qId);

        if (questionsMap[qId]) {
            renderQuestion(questionsMap[qId]);
        } else {
            $('#questionModalBody').html('<div class="text-center py-3"><div class="spinner-border text-primary"></div></div>');
            $.ajax({
                url: BASE + 'client/get_question_detail',
                type: 'GET',
                data: { id: qId },
                dataType: 'json',
                success: function(res) {
                    if (res.success) renderQuestion(res.question);
                    else $('#questionModalBody').html('<div class="alert alert-warning mb-0">Pergunta não encontrada.</div>');
                },
                error: function() {
                    $('#questionModalBody').html('<div class="alert alert-danger mb-0">Erro ao buscar pergunta.</div>');
                }
            });
        }
        modal.show();
    });

    function renderQuestion(q) {
        var typeLabels = {
            'text': 'Texto', 'textarea': 'Texto Longo', 'number': 'Número',
            'radio': 'Escolha Única', 'checkbox': 'Múltipla Escolha', 'select': 'Seleção'
        };
        var html = '<div class="mb-3">' +
            '<span class="badge bg-secondary me-2">ID: ' + q.id + '</span>' +
            '<span class="badge bg-info">' + (typeLabels[q.question_type] || q.question_type) + '</span>' +
            '</div>' +
            '<div class="p-3 rounded mb-3" style="background: #f8f9fa; border-left: 4px solid var(--primary-color);">' +
            '<h6 class="mb-0" style="color: var(--secondary-color);">' + escapeHtml(q.question_text) + '</h6>' +
            '</div>';

        if (q.options && q.options.length > 0) {
            html += '<p class="small text-muted mb-1"><i class="fas fa-list me-1"></i>Opções:</p><div class="question-modal-options">';
            q.options.forEach(function(opt) {
                html += '<span class="opt-item">' + escapeHtml(opt.option_text || opt) + '</span>';
            });
            html += '</div>';
        }
        $('#questionModalBody').html(html);
    }

    function escapeHtml(text) {
        if (!text) return '';
        var d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }
});
</script>
