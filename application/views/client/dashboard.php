<style>
    .client-welcome {
        background: linear-gradient(135deg, rgba(143,174,93,0.1), rgba(35,52,95,0.05));
        border: 1px solid rgba(143,174,93,0.2);
        border-radius: 0.75rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .analysis-card {
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
        margin-bottom: 1rem;
    }
    .analysis-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    }
    .analysis-card .card-top {
        padding: 1.25rem;
        border-bottom: 1px solid #f0f0f0;
    }
    .analysis-card .card-bottom {
        padding: 0.75rem 1.25rem;
        background: #fafbfc;
    }
    .type-icon {
        width: 44px; height: 44px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        color: white; font-size: 1.1rem; flex-shrink: 0;
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
    /* Painel automatico */
    .live-block {
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }
    .live-block > header {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: .5rem;
    }
    .kpi {
        border: 1px solid #eef0f2;
        border-radius: .6rem;
        padding: .85rem 1rem;
        height: 100%;
    }
    .kpi .kpi-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--secondary-color);
        line-height: 1.2;
    }
    .kpi .kpi-label {
        font-size: .78rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .chart-box {
        position: relative;
        height: 260px;
    }
    .q-title {
        font-size: .95rem;
        font-weight: 600;
        color: var(--secondary-color);
        margin-bottom: .5rem;
    }
    .open-answer {
        border-left: 3px solid #dfe4ea;
        padding: .4rem 0 .4rem .75rem;
        margin-bottom: .5rem;
        font-size: .9rem;
    }
    .open-answer .meta {
        font-size: .75rem;
        color: #9aa4ae;
    }
    .live-updated {
        font-size: .78rem;
        color: #6c757d;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-chart-pie me-2" style="color: var(--primary-color);"></i>Painel de Análises</h2>
        <small class="text-muted">Resultados atualizados conforme as respostas chegam do campo</small>
    </div>
    <button class="btn btn-outline-secondary btn-sm" onclick="window.location.reload();">
        <i class="fas fa-sync-alt me-1"></i> Atualizar
    </button>
</div>

<div class="client-welcome">
    <div class="d-flex align-items-center">
        <i class="fas fa-user-circle me-3" style="font-size: 2rem; color: var(--primary-color);"></i>
        <div>
            <h5 class="mb-1" style="color: var(--secondary-color);">Bem-vindo(a), <?= htmlspecialchars($this->session->userdata('admin_name')) ?>!</h5>
            <p class="mb-0 text-muted">Aqui você acompanha os resultados da coleta em tempo real.</p>
        </div>
    </div>
</div>

<?php if (!empty($analyses)):
    // Acesso em destaque às análises de IA: antes ficavam só no fim da
    // página, depois de todas as estatísticas automáticas
    $recentes = array_slice(array_map(function ($a) { return (object) $a; }, $analyses), 0, 3);
    $qtd_analises = count($analyses);
?>
<style>
    .ia-destaque {
        background: linear-gradient(135deg, #23345F 0%, #2f4a7a 55%, #8fae5d 140%);
        color: #fff; border-radius: .9rem; padding: 1.4rem 1.6rem; margin-bottom: 1.5rem;
        box-shadow: 0 6px 18px rgba(35,52,95,.25);
    }
    .ia-destaque .ia-icone {
        width: 54px; height: 54px; border-radius: 14px; background: rgba(255,255,255,.15);
        display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0;
    }
    .ia-destaque h4 { margin: 0; font-weight: 700; }
    .ia-destaque p { margin: .25rem 0 0; opacity: .9; }
    .ia-destaque .btn-ia { background: #8fae5d; border: 0; color: #fff; font-weight: 600; padding: .55rem 1.1rem; }
    .ia-destaque .btn-ia:hover { background: #7a9850; color: #fff; }
    .ia-destaque .btn-ia-sec { border: 1px solid rgba(255,255,255,.6); color: #fff; padding: .55rem 1.1rem; }
    .ia-destaque .btn-ia-sec:hover { background: rgba(255,255,255,.12); color: #fff; }
    .ia-destaque .ia-links a {
        display: inline-block; color: #fff; background: rgba(255,255,255,.12); border-radius: 2rem;
        padding: .25rem .8rem; margin: .5rem .4rem 0 0; font-size: .85rem; text-decoration: none;
    }
    .ia-destaque .ia-links a:hover { background: rgba(255,255,255,.22); }
</style>
<div class="ia-destaque">
    <div class="d-flex flex-wrap align-items-center gap-3">
        <div class="ia-icone"><i class="fas fa-robot"></i></div>
        <div class="flex-grow-1">
            <h4>Análises aprofundadas por IA</h4>
            <p>
                <?= $qtd_analises ?> <?= $qtd_analises === 1 ? 'análise disponível' : 'análises disponíveis' ?>,
                com resumo executivo, principais achados, pontos de atenção e recomendações.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= base_url('client/view_analysis/' . $recentes[0]->id) ?>" class="btn btn-ia">
                <i class="fas fa-chart-line me-1"></i>Abrir a mais recente
            </a>
            <?php if ($qtd_analises > 1): ?>
            <a href="#analises-aprofundadas" class="btn btn-ia-sec">Ver todas</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($qtd_analises > 1): ?>
    <div class="ia-links">
        <?php foreach ($recentes as $r): ?>
            <a href="<?= base_url('client/view_analysis/' . $r->id) ?>">
                <i class="fas fa-file-alt me-1"></i><?= html_escape($r->questionnaire_title ?? 'Análise #' . $r->id) ?>
                · <?= date('d/m/Y', strtotime($r->created_at)) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($relatorios_municipais)): ?>
    <h5 class="mb-3" style="color: var(--secondary-color);">
        <i class="fas fa-globe-americas me-2"></i>Diagnósticos municipais
    </h5>
    <div class="row mb-4">
        <?php foreach ($relatorios_municipais as $rm): ?>
            <div class="col-md-6 col-lg-4">
                <a href="<?= base_url('relatorios-automatizados/documento/' . (int) $rm->id . '/' . (int) $rm->versao_atual) ?>"
                   target="_blank" rel="noopener" class="text-decoration-none">
                    <div class="analysis-card">
                        <div class="card-top">
                            <div class="d-flex align-items-start">
                                <div class="type-icon me-3" style="background: linear-gradient(135deg, #8fae5d, #5a7536);">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1" style="color: var(--secondary-color);">
                                        <?= html_escape($rm->municipio_nome) ?> - <?= html_escape($rm->uf) ?>
                                    </h6>
                                    <span class="badge" style="background: #8fae5d; color: white;">Diagnóstico <?= html_escape($rm->periodo_referencia) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="card-bottom d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i><?= $rm->publicado_em ? date('d/m/Y', strtotime($rm->publicado_em)) : '' ?>
                            </small>
                            <small class="text-muted">Versão <?= (int) $rm->versao_atual ?>.0 · PDF</small>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
// ============================================================
// BLOCO 1 - Estatisticas automaticas (sem IA, direto do banco)
// ============================================================
$live = isset($live) ? $live : array();
?>

<?php if (empty($live)): ?>
    <div class="empty-state">
        <i class="fas fa-chart-bar d-block"></i>
        <h5>Nenhuma resposta coletada ainda</h5>
        <p>Assim que os aplicadores enviarem as primeiras respostas, os gráficos aparecem aqui automaticamente.</p>
    </div>
<?php else: ?>
    <p class="live-updated mb-3">
        <i class="fas fa-clock me-1"></i>
        Dados apurados em <?= date('d/m/Y H:i') ?>. Use "Atualizar" para recarregar.
    </p>

    <?php foreach ($live as $bloco):
        $q        = $bloco['questionnaire'];
        $ov       = $bloco['overview'];
        $uid      = 'q' . (int) $q->id;
    ?>
    <section class="live-block">
        <header>
            <div>
                <h5 class="mb-0" style="color: var(--secondary-color);">
                    <?= htmlspecialchars($q->title) ?>
                </h5>
                <?php if (!empty($q->project_name)): ?>
                    <small class="text-muted"><i class="fas fa-folder me-1"></i><?= htmlspecialchars($q->project_name) ?></small>
                <?php endif; ?>
            </div>
            <span class="badge bg-success">
                <?= number_format($ov['total_responses'], 0, ',', '.') ?> resposta(s)
            </span>
        </header>

        <div class="p-3">
            <!-- KPIs -->
            <div class="row g-2 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="kpi">
                        <div class="kpi-value"><?= number_format($ov['total_responses'], 0, ',', '.') ?></div>
                        <div class="kpi-label">Respostas</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi">
                        <div class="kpi-value"><?= (int) $ov['total_applicators'] ?></div>
                        <div class="kpi-label">Aplicadores</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi">
                        <div class="kpi-value"><?= (int) $ov['total_locations'] ?></div>
                        <div class="kpi-label">Locais</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kpi">
                        <div class="kpi-value" style="font-size:1.05rem;">
                            <?= $ov['last_response'] ? date('d/m/Y', strtotime($ov['last_response'])) : '-' ?>
                        </div>
                        <div class="kpi-label">Última resposta</div>
                    </div>
                </div>
            </div>

            <!-- Evolucao + cobertura -->
            <div class="row g-3 mb-4">
                <?php if (!empty($bloco['por_dia'])): ?>
                <div class="col-lg-6">
                    <div class="q-title">Respostas por dia (últimos 30 dias)</div>
                    <div class="chart-box"><canvas id="dia_<?= $uid ?>"></canvas></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($bloco['por_local'])): ?>
                <div class="col-lg-6">
                    <div class="q-title">Cobertura por local</div>
                    <div class="chart-box"><canvas id="loc_<?= $uid ?>"></canvas></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($bloco['por_aplicador'])): ?>
                <div class="col-lg-6">
                    <div class="q-title">Respostas por aplicador</div>
                    <div class="chart-box"><canvas id="apl_<?= $uid ?>"></canvas></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Perguntas fechadas -->
            <?php if (!empty($bloco['perguntas'])): ?>
                <h6 class="mb-3" style="color: var(--secondary-color);">
                    <i class="fas fa-list-ul me-1"></i> Resultados por pergunta
                </h6>
                <div class="row g-3 mb-3">
                    <?php foreach ($bloco['perguntas'] as $i => $pg): ?>
                    <div class="col-lg-6">
                        <div class="q-title"><?= htmlspecialchars($pg['question_text']) ?></div>

                        <?php if (!empty($pg['resumo'])): ?>
                            <!-- Pergunta numerica -->
                            <div class="d-flex gap-3 small text-muted mb-2">
                                <span>Média: <strong><?= $pg['resumo']['media'] ?></strong></span>
                                <span>Mín: <strong><?= $pg['resumo']['minimo'] ?></strong></span>
                                <span>Máx: <strong><?= $pg['resumo']['maximo'] ?></strong></span>
                                <span><?= (int) $pg['total'] ?> resp.</span>
                            </div>
                        <?php else: ?>
                            <div class="chart-box"><canvas id="pg_<?= $uid ?>_<?= (int) $i ?>"></canvas></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Respostas abertas -->
            <?php if (!empty($bloco['abertas'])): ?>
                <h6 class="mb-2 mt-4" style="color: var(--secondary-color);">
                    <i class="fas fa-comment-dots me-1"></i> Respostas abertas (mais recentes)
                </h6>
                <?php foreach ($bloco['abertas'] as $grupo): ?>
                    <div class="mb-3">
                        <div class="q-title"><?= htmlspecialchars($grupo['question_text']) ?></div>
                        <?php foreach ($grupo['respostas'] as $resp): ?>
                            <div class="open-answer">
                                <?= nl2br(htmlspecialchars($resp['texto'])) ?>
                                <div class="meta">
                                    <?= $resp['data'] ? date('d/m/Y', strtotime($resp['data'])) : '' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php
// ============================================================
// BLOCO 2 - Analises de IA (opcional, quando o admin gerar)
// ============================================================
?>
<?php if (!empty($analyses)): ?>
    <h5 id="analises-aprofundadas" class="mb-3 mt-4" style="color: var(--secondary-color); scroll-margin-top: 1rem;">
        <i class="fas fa-robot me-2"></i>Análises aprofundadas
    </h5>
    <div class="row">
        <?php
            $typeLabels = array('statistical' => 'Estatística', 'sentiment' => 'Sentimento', 'correlation' => 'Correlação', 'general' => 'Geral');
            $typeIcons = array('statistical' => 'fa-chart-bar', 'sentiment' => 'fa-heart', 'correlation' => 'fa-project-diagram', 'general' => 'fa-chart-line');
            $typeColors = array('statistical' => '#8fae5d', 'sentiment' => '#e74c3c', 'correlation' => '#3498db', 'general' => '#f39c12');
        ?>
        <?php foreach ($analyses as $a):
            $a = (object)$a;
            $type = $a->analysis_type ?? 'general';
            $chart_suggestions = json_decode($a->chart_suggestions ?? '[]', true);
            $chart_count = is_array($chart_suggestions) ? count($chart_suggestions) : 0;
        ?>
        <div class="col-md-6 col-lg-4">
            <a href="<?= base_url('client/view_analysis/' . $a->id) ?>" class="text-decoration-none">
                <div class="analysis-card">
                    <div class="card-top">
                        <div class="d-flex align-items-start">
                            <div class="type-icon me-3" style="background: linear-gradient(135deg, <?= $typeColors[$type] ?? '#8fae5d' ?>, <?= $typeColors[$type] ?? '#6d8a45' ?>cc);">
                                <i class="fas <?= $typeIcons[$type] ?? 'fa-chart-line' ?>"></i>
                            </div>
                            <div>
                                <h6 class="mb-1" style="color: var(--secondary-color);">
                                    <?= htmlspecialchars($a->questionnaire_title ?? 'Análise #' . $a->id) ?>
                                </h6>
                                <span class="badge" style="background: <?= $typeColors[$type] ?? '#8fae5d' ?>; color: white;">
                                    <?= $typeLabels[$type] ?? ucfirst($type) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-bottom d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i><?= date('d/m/Y', strtotime($a->created_at)) ?>
                        </small>
                        <small class="text-muted">
                            <i class="fas fa-chart-pie me-1"></i><?= $chart_count ?> gráfico(s)
                        </small>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
(function () {
    // Paleta alinhada a identidade do painel
    var CORES = ['#8fae5d', '#23345f', '#3498db', '#f39c12', '#e74c3c',
                 '#16a085', '#9b59b6', '#34495e', '#d35400', '#7f8c8d'];

    function cores(n) {
        var out = [];
        for (var i = 0; i < n; i++) { out.push(CORES[i % CORES.length]); }
        return out;
    }

    function barras(id, rotulos, valores, horizontal) {
        var el = document.getElementById(id);
        if (!el) { return; }
        new Chart(el, {
            type: 'bar',
            data: {
                labels: rotulos,
                datasets: [{ data: valores, backgroundColor: cores(valores.length), borderRadius: 4 }]
            },
            options: {
                indexAxis: horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true }, y: { beginAtZero: true } }
            }
        });
    }

    function linha(id, rotulos, valores) {
        var el = document.getElementById(id);
        if (!el) { return; }
        new Chart(el, {
            type: 'line',
            data: {
                labels: rotulos,
                datasets: [{
                    data: valores,
                    borderColor: '#8fae5d',
                    backgroundColor: 'rgba(143,174,93,0.15)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    var dados = <?= json_encode(array_map(function ($b) {
        return array(
            'uid'       => 'q' . (int) $b['questionnaire']->id,
            'por_dia'   => $b['por_dia'],
            'por_local' => $b['por_local'],
            'por_apl'   => $b['por_aplicador'],
            'perguntas' => array_map(function ($p) {
                return array('linhas' => $p['linhas'], 'resumo' => $p['resumo']);
            }, $b['perguntas']),
        );
    }, $live), JSON_UNESCAPED_UNICODE) ?>;

    dados.forEach(function (d) {
        if (d.por_dia && d.por_dia.length) {
            linha('dia_' + d.uid,
                  d.por_dia.map(function (r) { return r.dia; }),
                  d.por_dia.map(function (r) { return r.total; }));
        }
        if (d.por_local && d.por_local.length) {
            barras('loc_' + d.uid,
                   d.por_local.map(function (r) { return r.rotulo; }),
                   d.por_local.map(function (r) { return r.total; }), true);
        }
        if (d.por_apl && d.por_apl.length) {
            barras('apl_' + d.uid,
                   d.por_apl.map(function (r) { return r.rotulo; }),
                   d.por_apl.map(function (r) { return r.total; }), true);
        }
        (d.perguntas || []).forEach(function (p, i) {
            if (p.linhas && p.linhas.length) {
                barras('pg_' + d.uid + '_' + i,
                       p.linhas.map(function (r) { return r.rotulo; }),
                       p.linhas.map(function (r) { return r.total; }), true);
            }
        });
    });
})();
</script>
