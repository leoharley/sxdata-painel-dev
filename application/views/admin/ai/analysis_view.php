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
    .section-icon {
        width: 40px; height: 40px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        color: white; font-size: 1rem; margin-right: 0.75rem; flex-shrink: 0;
    }
    .analysis-list-item {
        padding: 0.75rem 0; border-bottom: 1px solid #f0f0f0;
    }
    .analysis-list-item:last-child { border-bottom: none; }
    .chart-container {
        position: relative; height: 300px; margin-bottom: 1rem;
    }
    .summary-card {
        background: linear-gradient(135deg, rgba(143,174,93,0.08), rgba(35,52,95,0.05));
        border: 1px solid rgba(143,174,93,0.2); border-radius: 0.75rem;
        padding: 1.5rem;
    }
    .question-ref-link {
        color: var(--primary-color);
        text-decoration: none;
        border-bottom: 1px dashed var(--primary-color);
        cursor: pointer;
        transition: color 0.2s;
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
</style>

<?php
    $patterns        = is_string($analysis->patterns        ?? '') ? json_decode($analysis->patterns,        true) : ($analysis->patterns        ?? []);
    $outliers        = is_string($analysis->outliers        ?? '') ? json_decode($analysis->outliers,        true) : ($analysis->outliers        ?? []);
    $trends          = is_string($analysis->trends          ?? '') ? json_decode($analysis->trends,          true) : ($analysis->trends          ?? []);
    $insights        = is_string($analysis->insights        ?? '') ? json_decode($analysis->insights,        true) : ($analysis->insights        ?? []);
    $chart_suggestions = is_string($analysis->chart_suggestions ?? '') ? json_decode($analysis->chart_suggestions, true) : ($analysis->chart_suggestions ?? []);

    // Converte referências q_XXX em links clicáveis
    function linkify_questions($text) {
        $escaped = htmlspecialchars($text);
        return preg_replace(
            '/\bq_(\d+)\b/',
            '<a href="javascript:void(0)" class="question-ref-link" data-question-id="$1" title="Ver pergunta #$1"><strong>q_$1</strong></a>',
            $escaped
        );
    }

    // Função para renderizar um item de lista (string ou array com chaves variadas)
    function render_list_item($item, $icon_class, $icon_color = '') {
        if (is_string($item)) {
            echo '<p class="mb-0"><i class="' . $icon_class . ' me-2" style="' . $icon_color . '"></i>' . linkify_questions($item) . '</p>';
            return;
        }
        if (is_array($item)) {
            // Tenta chaves padrão de título
            $title = $item['title'] ?? $item['name'] ?? $item['pattern'] ?? $item['trend'] ?? $item['outlier'] ?? $item['insight'] ?? null;
            // Tenta chaves padrão de descrição
            $desc  = $item['description'] ?? $item['text'] ?? $item['detail'] ?? $item['details'] ?? $item['observation'] ?? null;

            if ($title === null && $desc === null) {
                // Nenhuma chave conhecida — renderiza todos os valores string do array
                $parts = [];
                foreach ($item as $k => $v) {
                    if (is_scalar($v) && $v !== '') $parts[] = linkify_questions((string)$v);
                }
                echo '<p class="mb-0"><i class="' . $icon_class . ' me-2" style="' . $icon_color . '"></i>' . implode(' — ', $parts) . '</p>';
            } else {
                if ($title) echo '<strong style="color: var(--secondary-color);">' . linkify_questions($title) . '</strong>';
                if ($desc)  echo '<p class="mb-0 text-muted small">' . linkify_questions($desc) . '</p>';
            }
        }
    }
?>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= base_url('ai') ?>"><i class="fas fa-brain me-1"></i>Inteligência Artificial</a></li>
                <li class="breadcrumb-item"><a href="<?= base_url('ai/analysis') ?>">Análise Estatística</a></li>
                <li class="breadcrumb-item active">Visualizar</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="fas fa-chart-line me-2" style="color: var(--primary-color);"></i>
            <?= htmlspecialchars($analysis->questionnaire_title ?? 'Análise #' . $analysis->id) ?>
        </h2>
        <small class="text-muted">
            <?php
                $typeLabels = ['statistical' => 'Estatística', 'sentiment' => 'Sentimento', 'correlation' => 'Correlação', 'general' => 'Geral'];
            ?>
            Tipo: <?= $typeLabels[$analysis->analysis_type] ?? ucfirst($analysis->analysis_type ?? '') ?>
            | Gerada em: <?= date('d/m/Y H:i', strtotime($analysis->created_at)) ?>
        </small>
    </div>
    <a href="<?= base_url('ai/analysis') ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Voltar
    </a>
</div>

<!-- Resumo -->
<div class="summary-card mb-4">
    <h5 style="color: var(--secondary-color); font-weight: 600;">
        <i class="fas fa-file-alt me-2" style="color: var(--primary-color);"></i>Resumo
    </h5>
    <?php
        $summary_raw = $analysis->summary_text ?? '';
        $summary_decoded = json_decode($summary_raw, true);
    ?>
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
                            <li><?= htmlspecialchars(is_string($k) ? str_replace('_', ' ', $k) . ': ' : '') ?><strong><?= is_scalar($v) ? linkify_questions((string)$v) : htmlspecialchars(json_encode($v)) ?></strong></li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <strong style="font-size: 1.4rem; color: var(--secondary-color);"><?= htmlspecialchars($value) ?></strong>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="mb-0" style="font-size: 1.05rem; line-height: 1.7; color: #333;">
            <?= nl2br(linkify_questions($summary_raw)) ?>
        </p>
    <?php endif; ?>
</div>

<div class="row">
    <!-- Padrões -->
    <?php if (!empty($patterns)): ?>
    <div class="col-md-6">
        <div class="ai-page-card">
            <div class="card-header">
                <div class="d-flex align-items-center">
                    <div class="section-icon" style="background: linear-gradient(135deg, #8fae5d, #6d8a45);">
                        <i class="fas fa-puzzle-piece"></i>
                    </div>
                    <h5>Padrões Identificados</h5>
                </div>
            </div>
            <div class="card-body p-3">
                <?php foreach ($patterns as $item): ?>
                <div class="analysis-list-item">
                    <?php render_list_item($item, 'fas fa-check-circle', 'color: var(--primary-color);'); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Outliers -->
    <?php if (!empty($outliers)): ?>
    <div class="col-md-6">
        <div class="ai-page-card">
            <div class="card-header">
                <div class="d-flex align-items-center">
                    <div class="section-icon" style="background: linear-gradient(135deg, #f0ad4e, #ec971f);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h5>Outliers</h5>
                </div>
            </div>
            <div class="card-body p-3">
                <?php foreach ($outliers as $item): ?>
                <div class="analysis-list-item">
                    <?php render_list_item($item, 'fas fa-exclamation', 'color: #f0ad4e;'); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tendências -->
    <?php if (!empty($trends)): ?>
    <div class="col-md-6">
        <div class="ai-page-card">
            <div class="card-header">
                <div class="d-flex align-items-center">
                    <div class="section-icon" style="background: linear-gradient(135deg, #5bc0de, #46b8da);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h5>Tendências</h5>
                </div>
            </div>
            <div class="card-body p-3">
                <?php foreach ($trends as $item): ?>
                <div class="analysis-list-item">
                    <?php render_list_item($item, 'fas fa-arrow-trend-up', 'color: #5bc0de;'); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Insights -->
    <?php if (!empty($insights)): ?>
    <div class="col-md-6">
        <div class="ai-page-card">
            <div class="card-header">
                <div class="d-flex align-items-center">
                    <div class="section-icon" style="background: linear-gradient(135deg, #23345F, #1a2847);">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <h5>Insights</h5>
                </div>
            </div>
            <div class="card-body p-3">
                <?php foreach ($insights as $item): ?>
                <div class="analysis-list-item">
                    <?php render_list_item($item, 'fas fa-lightbulb', 'color: var(--primary-color);'); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Gráficos Sugeridos -->
<?php if (!empty($chart_suggestions)): ?>
<div class="ai-page-card">
    <div class="card-header">
        <div class="d-flex align-items-center">
            <div class="section-icon" style="background: linear-gradient(135deg, #8fae5d, #6d8a45);">
                <i class="fas fa-chart-pie"></i>
            </div>
            <h5>Gráficos Sugeridos</h5>
        </div>
    </div>
    <div class="card-body p-3">
        <div class="row">
            <?php foreach ($chart_suggestions as $index => $chart): ?>
            <div class="col-md-6 mb-3">
                <div class="border rounded p-3">
                    <h6 class="mb-3" style="color: var(--secondary-color);">
                        <?= linkify_questions($chart['title'] ?? 'Gráfico ' . ($index + 1)) ?>
                    </h6>
                    <div class="chart-container">
                        <canvas id="chart-<?= $index ?>"></canvas>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
<?php if (!empty($chart_suggestions)): ?>
document.addEventListener('DOMContentLoaded', function() {
    var chartSuggestions = <?= json_encode($chart_suggestions) ?>;

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

    chartSuggestions.forEach(function(chart, index) {
        var canvas = document.getElementById('chart-' + index);
        if (!canvas) return;

        // Suporta múltiplos formatos de resposta da IA
        var chartType = chart.chart_type || chart.type || 'bar';

        // Tenta extrair labels de vários formatos
        var labels = chart.labels || (chart.data_config && chart.data_config.labels) || [];

        // Tenta extrair valores de vários formatos
        var rawData = chart.data || chart.values || chart.counts ||
                      (chart.data_config && (chart.data_config.data || chart.data_config.values)) || null;

        // Se labels e data vieram como objeto {chave: valor}
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

        // Sem dados reais: não renderiza canvas vazio
        if (datasets.length === 0 || !datasets[0].data || datasets[0].data.length === 0) {
            canvas.closest('.border.rounded').innerHTML +=
                '<p class="text-muted small text-center mt-2">Dados insuficientes para gerar gráfico.</p>';
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

        new Chart(canvas, {
            type: chartType,
            data: { labels: labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    });
});
<?php endif; ?>
</script>

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
                    <p class="text-muted mt-2 mb-0">Carregando...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function waitForJQuery() {
    if (typeof jQuery === 'undefined') return setTimeout(waitForJQuery, 50);
    jQuery(function($) {
        var BASE = '<?= base_url() ?>';
        var questionCache = {};

        $(document).on('click', '.question-ref-link', function(e) {
            e.preventDefault();
            var qId = $(this).data('question-id');
            var modal = new bootstrap.Modal(document.getElementById('questionDetailModal'));

            $('#questionModalTitle').html('<i class="fas fa-question-circle me-2"></i>Pergunta #' + qId);
            $('#questionModalBody').html(
                '<div class="text-center py-3">' +
                '<div class="spinner-border text-primary" role="status"></div>' +
                '<p class="text-muted mt-2 mb-0">Carregando...</p></div>'
            );
            modal.show();

            if (questionCache[qId]) {
                renderQuestion(questionCache[qId]);
                return;
            }

            $.ajax({
                url: BASE + 'ai/get_question_detail',
                type: 'GET',
                data: { id: qId },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        questionCache[qId] = res.question;
                        renderQuestion(res.question);
                    } else {
                        $('#questionModalBody').html(
                            '<div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle me-2"></i>' +
                            (res.message || 'Pergunta não encontrada.') + '</div>'
                        );
                    }
                },
                error: function() {
                    $('#questionModalBody').html(
                        '<div class="alert alert-danger mb-0"><i class="fas fa-times-circle me-2"></i>Erro ao buscar pergunta.</div>'
                    );
                }
            });
        });

        function renderQuestion(q) {
            var typeLabels = {
                'text': 'Texto', 'textarea': 'Texto Longo', 'number': 'Número',
                'email': 'E-mail', 'phone': 'Telefone', 'date': 'Data', 'datetime': 'Data/Hora',
                'radio': 'Escolha Única', 'checkbox': 'Múltipla Escolha', 'select': 'Seleção'
            };
            var typeLabel = typeLabels[q.question_type] || q.question_type;

            var html = '<div class="mb-3">' +
                '<span class="badge bg-secondary me-2">ID: ' + q.id + '</span>' +
                '<span class="badge bg-info">' + typeLabel + '</span>' +
                (q.is_required == 1 ? '<span class="badge bg-danger ms-1">Obrigatória</span>' : '') +
                '</div>' +
                '<div class="p-3 rounded mb-3" style="background: #f8f9fa; border-left: 4px solid var(--primary-color);">' +
                '<h6 class="mb-0" style="color: var(--secondary-color);">' + escapeHtml(q.question_text) + '</h6>' +
                '</div>';

            if (q.questionnaire_title) {
                html += '<p class="small text-muted mb-2"><i class="fas fa-clipboard-list me-1"></i>Questionário: <strong>' + escapeHtml(q.questionnaire_title) + '</strong></p>';
            }

            if (q.options && q.options.length > 0) {
                html += '<p class="small text-muted mb-1"><i class="fas fa-list me-1"></i>Opções:</p>' +
                    '<div class="question-modal-options">';
                q.options.forEach(function(opt) {
                    html += '<span class="opt-item">' + escapeHtml(opt.option_text || opt) + '</span>';
                });
                html += '</div>';
            }

            if (q.order_index !== undefined) {
                html += '<p class="small text-muted mt-3 mb-0"><i class="fas fa-sort-numeric-down me-1"></i>Posição no questionário: <strong>#' + q.order_index + '</strong></p>';
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
})();
</script>
