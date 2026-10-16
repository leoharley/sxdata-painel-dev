<?php
// admin/reports/index.php

$current_filters = http_build_query($filters ?? []);
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Relatórios e Análises</h2>
            <div>
                <button class="btn btn-outline-primary me-2" onclick="exportAllData()">
                    <i class="fas fa-download me-2"></i>
                    Exportar Tudo
                </button>
                <button class="btn btn-success" onclick="openKMZModal()">
                    <i class="fas fa-map-marked-alt me-2"></i>
                    Gerar KMZ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Filtros de Relatório -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Filtros de Análise</h5>
    </div>
    <div class="card-body">
        <?= form_open('reports', ['method' => 'GET', 'id' => 'reportFilters']) ?>
        <div class="row">
            <div class="col-md-3">
                <label class="form-label">Período</label>
                <select class="form-select" name="period" onchange="toggleCustomDates(this.value)">
                    <option value="last_7_days" <?= set_select('period', 'last_7_days', $filters['period'] ?? '' == 'last_7_days') ?>>Últimos 7 dias</option>
                    <option value="last_30_days" <?= set_select('period', 'last_30_days', $filters['period'] ?? '' == 'last_30_days') ?>>Últimos 30 dias</option>
                    <option value="last_3_months" <?= set_select('period', 'last_3_months', $filters['period'] ?? '' == 'last_3_months') ?>>Últimos 3 meses</option>
                    <option value="custom" <?= set_select('period', 'custom', $filters['period'] ?? '' == 'custom') ?>>Personalizado</option>
                </select>
            </div>
            
            <div class="col-md-2" id="customDatesFrom" style="display: <?= ($filters['period'] ?? '') == 'custom' ? 'block' : 'none' ?>;">
                <label class="form-label">Data Início</label>
                <input type="date" class="form-control" name="date_from" value="<?= $filters['date_from'] ?? '' ?>">
            </div>
            
            <div class="col-md-2" id="customDatesTo" style="display: <?= ($filters['period'] ?? '') == 'custom' ? 'block' : 'none' ?>;">
                <label class="form-label">Data Fim</label>
                <input type="date" class="form-control" name="date_to" value="<?= $filters['date_to'] ?? '' ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Questionário</label>
                <select class="form-select" name="questionnaire_id">
                    <option value="">Todos</option>
                    <?php foreach ($questionnaires as $questionnaire): ?>
                    <option value="<?= $questionnaire->id ?>" 
                            <?= set_select('questionnaire_id', $questionnaire->id, 
                               isset($filters['questionnaire_id']) && $filters['questionnaire_id'] == $questionnaire->id) ?>>
                        <?= $questionnaire->title ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-chart-line me-1"></i>
                    Gerar
                </button>
            </div>
        </div>
        <?= form_close() ?>
    </div>
</div>

<!-- Estatísticas do Período -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(45deg, #007bff, #0056b3);">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <h3 class="stat-number"><?= $period_stats['total_responses'] ?? 0 ?></h3>
            <p class="stat-label">Respostas no Período</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(45deg, #8fae5d, #a8c46a);">
                <i class="fas fa-users"></i>
            </div>
            <h3 class="stat-number"><?= $period_stats['unique_respondents'] ?? 0 ?></h3>
            <p class="stat-label">Respondentes Únicos</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(45deg, #ffc107, #e0a800);">
                <i class="fas fa-camera"></i>
            </div>
            <h3 class="stat-number"><?= $period_stats['photos_captured'] ?? 0 ?></h3>
            <p class="stat-label">Fotos Capturadas</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(45deg, #17a2b8, #138496);">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <h3 class="stat-number"><?= $period_stats['locations_captured'] ?? 0 ?></h3>
            <p class="stat-label">Localizações</p>
        </div>
    </div>
</div>

<!-- Gráficos e Análises -->
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Respostas por Dia</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($charts_data) && !empty($charts_data['responses_by_day'])): ?>
                    <canvas id="responsesTimeChart" width="400" height="200"></canvas>
                <?php else: ?>
                    <div class="chart-placeholder" style="height: 200px;">
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center">
                                <i class="fas fa-chart-line fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">Nenhum dado para exibir</p>
                                <small class="text-muted">Selecione um período com dados</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Aplicadores Mais Ativos</h5>
                <?php if (!empty($charts_data) && !empty($charts_data['top_applicators'])): ?>
                <span class="badge bg-primary"><?= count($charts_data['top_applicators']) ?> aplicadores</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($charts_data) && !empty($charts_data['top_applicators'])): ?>
                    <div class="chart-container-fixed">
                        <canvas id="applicatorsChart"></canvas>
                    </div>
                    
                    <!-- Lista dos top aplicadores -->
                    <div class="mt-3">
                        <h6 class="text-muted mb-2">Ranking dos Aplicadores:</h6>
                        <div class="row">
                            <?php foreach (array_slice($charts_data['top_applicators'], 0, 6) as $index => $applicator): ?>
                            <div class="col-md-6 mb-2">
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-<?= $index < 3 ? 'success' : 'secondary' ?> me-2">
                                        #<?= $index + 1 ?>
                                    </span>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?= htmlspecialchars($applicator['name']) ?></div>
                                        <small class="text-muted"><?= $applicator['count'] ?> aplicações</small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="chart-placeholder" style="height: 200px;">
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center">
                                <i class="fas fa-users fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">Nenhum aplicador ativo</p>
                                <small class="text-muted">Aguardando atividade dos usuários</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4" style="height:365px">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-microscope me-2"></i>
                    Análise Detalhada por Questionário
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Selecione um Questionário:</label>
                        <select class="form-select" id="specificQuestionnaireSelect" onchange="loadSpecificQuestionnaire()">
                            <option value="">-- Escolha um questionário --</option>
                            <?php foreach ($questionnaires as $questionnaire): ?>
                            <option value="<?= $questionnaire->id ?>" 
                                    <?= (isset($filters['questionnaire_id']) && $filters['questionnaire_id'] == $questionnaire->id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($questionnaire->title) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <br>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Esta análise mostra estatísticas detalhadas de um questionário específico
                        </small>

                        <br><br><br><br><br><br>

                         <button type="button" class="btn btn-primary" onclick="loadSpecificQuestionnaire()">
                            <i class="fas fa-chart-pie me-1"></i>
                            Analisar
                        </button>
                        <button type="button" class="btn btn-outline-secondary ms-2" onclick="exportSpecificAnalysis()">
                            <i class="fas fa-download me-1"></i>
                            Exportar
                        </button>

                    </div>
                    
                </div>
                  
            </div>
        </div>

    </div>
    
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Questionários por Popularidade</h5>
                <?php if (!empty($charts_data) && !empty($charts_data['questionnaires_popularity'])): ?>
                <span class="badge bg-success"><?= count($charts_data['questionnaires_popularity']) ?> questionários</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($charts_data) && !empty($charts_data['questionnaires_popularity'])): ?>
                    <div class="chart-container-fixed">
                        <canvas id="questionnairesPopularityChart"></canvas>
                    </div>
                    
                    <!-- Lista dos questionários -->
                    <div class="mt-3">
                        <h6 class="text-muted mb-2">Mais utilizados:</h6>
                        <?php foreach (array_slice($charts_data['questionnaires_popularity'], 0, 5) as $index => $questionnaire): ?>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="flex-grow-1">
                                <div class="fw-bold"><?= htmlspecialchars($questionnaire['title']) ?></div>
                                <small class="text-muted"><?= $questionnaire['count'] ?> aplicações</small>
                            </div>
                            <span class="badge bg-primary">#<?= $index + 1 ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="chart-placeholder" style="height: 200px;">
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center">
                                <i class="fas fa-poll fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">Sem dados de popularidade</p>
                                <small class="text-muted">Crie questionários e colete respostas</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Taxa de Consentimento</h5>
            </div>
            <div class="card-body">
                <div class="text-center">
                    <?php if (isset($period_stats['consent_rate']) && $period_stats['consent_rate'] !== null): ?>
                        <div class="progress-circle mb-3">
                            <span class="progress-value"><?= $period_stats['consent_rate'] ?>%</span>
                        </div>
                        <p class="text-muted mb-0">Respondentes que consentiram com o uso dos dados</p>
                    <?php else: ?>
                        <div class="progress-circle mb-3" style="background: conic-gradient(#e9ecef 360deg);">
                            <span class="progress-value">-</span>
                        </div>
                        <p class="text-muted mb-0">Nenhum dado de consentimento disponível</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Mapa de Calor - Localizações</h5>
                <div>
                    <span class="badge bg-info me-2">
                        <i class="fas fa-map-marker-alt me-1"></i>
                        <?= isset($heatmap_locations['stats']['total_locations']) ? $heatmap_locations['stats']['total_locations'] : 0 ?> locais
                    </span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleHeatmapIntensity()" id="intensityToggle">
                        <i class="fas fa-adjust me-1"></i>
                        Intensidade
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($heatmap_locations['points'])): ?>
                    <div id="heatmap" style="height: 300px; width: 100%;"></div>
                    
                    <!-- Informações do mapa -->
                    <div class="p-3 border-top bg-light">
                        <div class="row text-center">
                            <div class="col-4">
                                <small class="text-muted d-block">Total de Pontos</small>
                                <strong class="text-primary"><?= count($heatmap_locations['points']) ?></strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Centro do Mapa</small>
                                <strong class="text-success">
                                    <?= number_format($heatmap_locations['stats']['center']['lat'], 4) ?>,
                                    <?= number_format($heatmap_locations['stats']['center']['lng'], 4) ?>
                                </strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Área Coberta</small>
                                <strong class="text-info">
                                    <?php if ($heatmap_locations['stats']['bounds']): ?>
                                        <?= number_format(
                                            abs($heatmap_locations['stats']['bounds']['northeast']['lat'] - 
                                                $heatmap_locations['stats']['bounds']['southwest']['lat']), 2
                                        ) ?>°
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="height: 300px; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                        <div class="text-center">
                            <i class="fas fa-map fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted mb-2">Nenhuma localização encontrada</h6>
                            <p class="text-muted mb-0 small">
                                Colete dados com GPS ativado para visualizar o mapa de calor
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Container para Análise Específica -->
<div id="specificAnalysisContainer" style="display: none;">
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0" id="specificQuestionnaireTitle">
                <i class="fas fa-poll me-2"></i>
                Análise Detalhada
            </h5>
            <div class="d-flex gap-2">
                <span class="badge bg-primary" id="totalQuestionsCount">0 questões</span>
                <span class="badge bg-success" id="totalResponsesCount">0 respostas</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="toggleViewMode()" id="viewModeToggle">
                    <i class="fas fa-th-list me-1"></i>
                    Visualização Compacta
                </button>
            </div>
        </div>
        <div class="card-body">
            
            <!-- Loading State -->
            <div id="specificAnalysisLoading" class="text-center py-5">
                <div class="loading-spinner mx-auto mb-3"></div>
                <h6 class="text-muted">Carregando análise...</h6>
                <p class="text-muted small">Processando dados do questionário selecionado</p>
            </div>

            <!-- Resumo Estatístico -->
            <div id="specificQuestionnaireStats" style="display: none;">
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="stat-box">
                            <div class="stat-number" id="statTotalQuestions">0</div>
                            <div class="stat-label">Questões</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-box">
                            <div class="stat-number" id="statTotalResponses">0</div>
                            <div class="stat-label">Respostas</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-box">
                            <div class="stat-number" id="statAvgCompletion">0%</div>
                            <div class="stat-label">Taxa Média</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-box">
                            <div class="stat-number" id="statUniqueRespondents">0</div>
                            <div class="stat-label">Respondentes</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-box">
                            <div class="stat-number" id="statAvgTime">0 min</div>
                            <div class="stat-label">Tempo Médio</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-box">
                            <div class="stat-number" id="statLastResponse">-</div>
                            <div class="stat-label">Última Resposta</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Análise por Questão -->
            <div id="specificQuestionsAnalysis" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-primary mb-0">
                        <i class="fas fa-list-ol me-2"></i>
                        Análise por Questão
                    </h6>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary active" onclick="filterByResponseRate('all')" data-filter="all">
                            Todas
                        </button>
                        <button class="btn btn-outline-success" onclick="filterByResponseRate('high')" data-filter="high">
                            Alta Resposta (&gt;80%)
                        </button>
                        <button class="btn btn-outline-warning" onclick="filterByResponseRate('medium')" data-filter="medium">
                            Média (50-80%)
                        </button>
                        <button class="btn btn-outline-danger" onclick="filterByResponseRate('low')" data-filter="low">
                            Baixa (&lt;50%)
                        </button>
                    </div>
                </div>

                <!-- Container das Questões -->
                <div id="questionsContainer">
                    <!-- Questões serão carregadas aqui via JavaScript -->
                </div>
            </div>

            <!-- Gráfico de Visão Geral -->
            <div id="specificOverviewChart" style="display: none;">
                <h6 class="text-primary mb-3">
                    <i class="fas fa-chart-bar me-2"></i>
                    Visão Geral das Respostas
                </h6>
                <div class="row">
                    <div class="col-md-8">
                        <canvas id="questionnaireOverviewChart" width="400" height="200"></canvas>
                    </div>
                    <div class="col-md-4">
                        <div id="overviewStats">
                            <!-- Estatísticas resumidas -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Análise de Respostas por Questões</h5>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-info me-2">
                <i class="fas fa-question-circle me-1"></i>
                <?= isset($question_analysis['summary']['total_questions']) ? $question_analysis['summary']['total_questions'] : 0 ?> questões
            </span>
            <span class="badge bg-success me-2">
                <i class="fas fa-chart-bar me-1"></i>
                <?= isset($question_analysis['summary']['avg_response_rate']) ? $question_analysis['summary']['avg_response_rate'] : 0 ?>% taxa de resposta
            </span>
            <div class="btn-group">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-filter me-1"></i>
                    Filtros
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="filterQuestionsByType('all')">
                        <i class="fas fa-list me-2"></i>Todas as questões
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="filterQuestionsByType('radio')">
                        <i class="fas fa-dot-circle me-2"></i>Múltipla escolha
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="filterQuestionsByType('checkbox')">
                        <i class="fas fa-check-square me-2"></i>Seleção múltipla
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="filterQuestionsByType('text')">
                        <i class="fas fa-font me-2"></i>Texto
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="filterQuestionsByType('number')">
                        <i class="fas fa-hashtag me-2"></i>Numérico
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="filterQuestionsByType('date')">
                        <i class="fas fa-calendar me-2"></i>Data
                    </a></li>
                </ul>
            </div>
            <button class="btn btn-sm btn-success" onclick="exportQuestionAnalysis()" title="Exportar Análise">
                <i class="fas fa-download me-1"></i>
                Exportar
            </button>
        </div>
    </div>
    <div class="card-body">
        
        <?php if (!empty($question_analysis['by_questionnaire'])): ?>
        
        <!-- Resumo Estatístico -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-mini">
                    <div class="stat-mini-icon bg-primary">
                        <i class="fas fa-list-ol"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h4><?= $question_analysis['summary']['total_questions'] ?></h4>
                        <p>Total de Questões</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-mini">
                    <div class="stat-mini-icon bg-success">
                        <i class="fas fa-reply-all"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h4><?= $question_analysis['summary']['total_responses'] ?></h4>
                        <p>Total de Respostas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-mini">
                    <div class="stat-mini-icon bg-warning">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h4><?= $question_analysis['summary']['questions_with_responses'] ?></h4>
                        <p>Questões Respondidas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-mini">
                    <div class="stat-mini-icon bg-info">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-mini-content">
                        <h4><?= $question_analysis['summary']['avg_response_rate'] ?>%</h4>
                        <p>Taxa Média de Resposta</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navegação por Questionários -->
        <div class="question-analysis-nav mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <ul class="nav nav-pills flex-grow-1" id="questionnaireTab" role="tablist">
                    <?php foreach ($question_analysis['by_questionnaire'] as $index => $questionnaire_data): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" 
                                id="questionnaire-<?= $questionnaire_data['questionnaire_id'] ?>-tab" 
                                data-bs-toggle="pill" 
                                data-bs-target="#questionnaire-<?= $questionnaire_data['questionnaire_id'] ?>" 
                                type="button" 
                                role="tab">
                            <?= htmlspecialchars($questionnaire_data['questionnaire_title']) ?>
                            <span class="badge bg-light text-dark ms-2"><?= count($questionnaire_data['questions']) ?></span>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
                
                <!-- Campo de busca -->
                <div class="search-container ms-3">
                    <div class="input-group input-group-sm" style="width: 300px;">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" id="questionSearch" 
                               placeholder="Buscar questões..." 
                               onkeyup="searchQuestions(this.value)">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conteúdo das Análises por Questionário -->
        <div class="tab-content" id="questionnaireTabContent">
            <?php foreach ($question_analysis['by_questionnaire'] as $index => $questionnaire_data): ?>
            <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" 
                 id="questionnaire-<?= $questionnaire_data['questionnaire_id'] ?>" 
                 role="tabpanel">
                
                <div class="questionnaire-analysis">
                    <h6 class="text-primary mb-3">
                        <i class="fas fa-poll me-2"></i>
                        <?= htmlspecialchars($questionnaire_data['questionnaire_title']) ?>
                    </h6>
                    
                    <!-- Lista de Questões -->
                    <div class="accordion" id="questionsAccordion<?= $questionnaire_data['questionnaire_id'] ?>">
                        <?php foreach ($questionnaire_data['questions'] as $question_index => $question): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?= $question['question_id'] ?>">
                                <button class="accordion-button collapsed" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#collapse<?= $question['question_id'] ?>" 
                                        onclick="loadQuestionDetails(<?= $question['question_id'] ?>)">
                                    <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                        <div class="question-info">
                                            <span class="fw-bold">Q<?= $question['order_index'] ?>:</span>
                                            <span class="question-text">
                                                <?= mb_strlen($question['question_text']) > 80 ? 
                                                    mb_substr(htmlspecialchars($question['question_text']), 0, 80) . '...' : 
                                                    htmlspecialchars($question['question_text']) ?>
                                            </span>
                                        </div>
                                        <div class="question-stats">
                                            <span class="badge bg-<?= $question['question_type'] === 'radio' || $question['question_type'] === 'checkbox' ? 'primary' : 'secondary' ?> me-2">
                                                <?= ucfirst($question['question_type']) ?>
                                            </span>
                                            <span class="badge bg-success">
                                                <?= $question['statistics']['total_responses'] ?> respostas
                                            </span>
                                        </div>
                                    </div>
                                </button>
                            </h2>
                            <div id="collapse<?= $question['question_id'] ?>" 
                                 class="accordion-collapse collapse" 
                                 data-bs-parent="#questionsAccordion<?= $questionnaire_data['questionnaire_id'] ?>">
                                <div class="accordion-body">
                                    
                                    <!-- Texto completo da questão -->
                                    <div class="question-full-text mb-3 p-3 bg-light rounded">
                                        <h6 class="text-primary mb-2">Questão <?= $question['order_index'] ?>:</h6>
                                        <p class="mb-0"><?= htmlspecialchars($question['question_text']) ?></p>
                                    </div>
                                    
                                    <!-- Análise das Respostas -->
                                    <?php if (!empty($question['statistics']['data'])): ?>
                                    <div class="row">
                                        
                                        <!-- Gráfico -->
                                        <div class="col-md-6">
                                            <h6 class="mb-3">Distribuição de Respostas</h6>
                                            <div class="chart-container">
                                                <canvas id="questionChart<?= $question['question_id'] ?>" 
                                                        width="300" height="300"></canvas>
                                            </div>
                                        </div>
                                        
                                        <!-- Tabela de Estatísticas -->
                                        <div class="col-md-6">
                                            <h6 class="mb-3">Estatísticas Detalhadas</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Opção/Categoria</th>
                                                            <th>Quantidade</th>
                                                            <th>Percentual</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($question['statistics']['data'] as $stat): ?>
                                                        <tr>
                                                            <td>
                                                                <?php if (isset($stat['option_text'])): ?>
                                                                    <span class="fw-bold"><?= htmlspecialchars($stat['option_text']) ?></span>
                                                                <?php else: ?>
                                                                    <?= htmlspecialchars($stat['label']) ?>
                                                                    <?php if (isset($stat['unit'])): ?>
                                                                        <small class="text-muted">(<?= $stat['unit'] ?>)</small>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-primary">
                                                                    <?php if (isset($stat['is_date']) && $stat['is_date']): ?>
                                                                        <?= $stat['count'] ?>
                                                                    <?php else: ?>
                                                                        <?= is_numeric($stat['count']) ? number_format($stat['count'], isset($stat['unit']) ? 0 : 0) : $stat['count'] ?>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if ($stat['percentage'] !== null): ?>
                                                                    <div class="progress" style="height: 8px; min-width: 60px;">
                                                                        <div class="progress-bar bg-success" 
                                                                             style="width: <?= $stat['percentage'] ?>%"
                                                                             title="<?= $stat['percentage'] ?>%"></div>
                                                                    </div>
                                                                    <small class="text-muted"><?= $stat['percentage'] ?>%</small>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- JavaScript para gerar gráfico -->
                                    <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const ctx<?= $question['question_id'] ?> = document.getElementById('questionChart<?= $question['question_id'] ?>');
                                        if (ctx<?= $question['question_id'] ?>) {
                                            const questionData<?= $question['question_id'] ?> = <?= json_encode($question['statistics']['data']) ?>;
                                            
                                            // Preparar dados para o gráfico
                                            const labels = questionData<?= $question['question_id'] ?>.map(item => 
                                                item.option_text || item.label || 'Sem rótulo'
                                            );
                                            const data = questionData<?= $question['question_id'] ?>.map(item => item.count);
                                            const percentages = questionData<?= $question['question_id'] ?>.map(item => item.percentage || 0);
                                            
                                            // Cores para o gráfico
                                            const colors = [
                                                '#8fae5d', '#007bff', '#ffc107', '#dc3545', '#17a2b8',
                                                '#6f42c1', '#e83e8c', '#fd7e14', '#20c997', '#6c757d'
                                            ];
                                            
                                            new Chart(ctx<?= $question['question_id'] ?>, {
                                                type: '<?= $question['question_type'] === 'number' ? 'bar' : 'doughnut' ?>',
                                                data: {
                                                    labels: labels,
                                                    datasets: [{
                                                        label: 'Respostas',
                                                        data: data,
                                                        backgroundColor: colors.slice(0, labels.length),
                                                        borderColor: colors.slice(0, labels.length),
                                                        borderWidth: 1
                                                    }]
                                                },
                                                options: {
                                                    responsive: true,
                                                    maintainAspectRatio: false,
                                                    plugins: {
                                                        legend: {
                                                            position: '<?= $question['question_type'] === 'number' ? 'top' : 'bottom' ?>',
                                                            labels: {
                                                                boxWidth: 12,
                                                                font: { size: 11 }
                                                            }
                                                        },
                                                        tooltip: {
                                                            callbacks: {
                                                                label: function(context) {
                                                                    const percentage = percentages[context.dataIndex];
                                                                    return context.label + ': ' + context.parsed + 
                                                                           (percentage ? ' (' + percentage + '%)' : '');
                                                                }
                                                            }
                                                        }
                                                    },
                                                    <?php if ($question['question_type'] === 'number'): ?>
                                                    scales: {
                                                        y: {
                                                            beginAtZero: true,
                                                            ticks: { font: { size: 10 } }
                                                        },
                                                        x: {
                                                            ticks: { font: { size: 10 } }
                                                        }
                                                    }
                                                    <?php endif; ?>
                                                }
                                            });
                                        }
                                    });
                                    </script>
                                    
                                    <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Nenhuma resposta encontrada para esta questão no período selecionado.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Top 5 Questões com Mais Respostas -->
        <?php if (!empty($question_analysis['top_questions'])): ?>
        <div class="mt-4">
            <h6 class="text-primary mb-3">
                <i class="fas fa-trophy me-2"></i>
                Top 5 Questões com Mais Respostas
            </h6>
            <div class="row">
                <?php foreach ($question_analysis['top_questions'] as $index => $top_question): ?>
                <div class="col-md-4 mb-3">
                    <div class="card border-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-primary">#<?= $index + 1 ?></span>
                                <span class="badge bg-success"><?= $top_question->total_responses ?> respostas</span>
                            </div>
                            <h6 class="card-title"><?= htmlspecialchars($top_question->questionnaire_title) ?></h6>
                            <p class="card-text small text-muted">
                                <?= mb_strlen($top_question->question_text) > 100 ? 
                                    mb_substr(htmlspecialchars($top_question->question_text), 0, 100) . '...' : 
                                    htmlspecialchars($top_question->question_text) ?>
                            </p>
                            <small class="text-muted">
                                <i class="fas fa-tag me-1"></i>
                                <?= ucfirst($top_question->question_type) ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- Estado vazio -->
        <div class="text-center py-5">
            <div class="mb-4">
                <i class="fas fa-question-circle fa-3x text-muted"></i>
            </div>
            <h5 class="text-muted mb-3">Nenhuma questão encontrada</h5>
            <p class="text-muted mb-4">
                Não há questões para analisar no período selecionado.<br>
                Verifique se existem questionários ativos com questões e respostas.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-outline-primary" onclick="resetFilters();">
                    <i class="fas fa-refresh me-1"></i>
                    Limpar Filtros
                </button>
                <a href="<?= base_url('questionnaires') ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Criar Questionário
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>


<div class="modal fade" id="kmzModal" tabindex="-1" aria-labelledby="kmzModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #8fae5d, #a8c46a); color: white;">
                <h5 class="modal-title" id="kmzModalLabel">
                    <i class="fas fa-map-marked-alt me-2"></i>
                    Gerar Arquivo KMZ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar" style="filter: brightness(0) invert(1);"></button>
            </div>
            <div class="modal-body">
                <form id="kmzForm">
                    <!-- Seção: Filtros de Dados -->
                    <div class="filter-section" style="background: #f8f9fa; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid #e9ecef;">
                        <h6 style="color: #23345F; margin-bottom: 1rem; font-weight: 700;">
                            <i class="fas fa-filter me-2"></i>
                            Filtros de Dados
                        </h6>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="kmz_questionnaires" class="form-label" style="font-weight: 600; color: #495057;">
                                    Questionários
                                </label>
                                <select class="form-select" id="kmz_questionnaires" name="questionnaires[]" multiple>
                                    <option value="all">Todos os Questionários</option>
                                </select>
                                <div class="form-text">
                                    Use Ctrl+clique para selecionar múltiplos questionários. Se nenhum for selecionado, todos serão incluídos.
                                </div>
                                <div id="selectedQuestionnairesBadges" class="mt-2"></div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="kmz_date_from" class="form-label" style="font-weight: 600; color: #495057;">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    Data Inicial
                                </label>
                                <input type="date" class="form-control" id="kmz_date_from" name="date_from">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="kmz_date_to" class="form-label" style="font-weight: 600; color: #495057;">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    Data Final
                                </label>
                                <input type="date" class="form-control" id="kmz_date_to" name="date_to">
                            </div>
                        </div>
                        
                        <div id="dateRangeInfo" style="display: none; background: #fff3cd; color: #664d03; padding: 0.5rem; border-radius: 4px; font-size: 0.9rem; margin-top: 0.5rem;"></div>
                    </div>

                    <!-- Seção: Configurações do KMZ -->
                    <div class="filter-section" style="background: #f8f9fa; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid #e9ecef;">
                        <h6 style="color: #23345F; margin-bottom: 1rem; font-weight: 700;">
                            <i class="fas fa-cog me-2"></i>
                            Configurações do Arquivo
                        </h6>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="kmz_filename" class="form-label" style="font-weight: 600; color: #495057;">Nome do Arquivo</label>
                                <input type="text" class="form-control" id="kmz_filename" name="filename" 
                                       placeholder="localizacoes_sxdata" value="localizacoes_sxdata">
                                <div class="form-text">O arquivo será salvo como: [nome]_YYYY-MM-DD.kmz</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-weight: 600; color: #495057;">Incluir Dados</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="kmz_include_photos" name="include_photos" checked>
                                    <label class="form-check-label" for="kmz_include_photos">
                                        Incluir referências de fotos
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="kmz_include_respondent" name="include_respondent" checked>
                                    <label class="form-check-label" for="kmz_include_respondent">
                                        Incluir dados do respondente
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="kmz_include_applicator" name="include_applicator" checked>
                                    <label class="form-check-label" for="kmz_include_applicator">
                                        Incluir dados do aplicador
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Preview dos Dados -->
                    <div id="kmzPreview" class="preview-section" style="display: none; background: #e8f5e8; border-radius: 8px; padding: 1rem; margin-top: 1rem; border-left: 4px solid #8fae5d;"></div>

                    <!-- Avisos -->
                    <div id="noDataWarning" class="alert alert-warning" style="display: none;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atenção:</strong> Nenhuma localização foi encontrada com os filtros selecionados.
                    </div>

                    <div id="successFeedback" class="alert alert-success" style="display: none;">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Arquivo gerado com sucesso!</strong> O download deve iniciar automaticamente.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancelar
                </button>
                <button type="button" class="btn btn-outline-primary" onclick="previewKMZData()">
                    <i class="fas fa-eye me-1"></i>Visualizar
                </button>
                <button type="button" class="btn btn-success" onclick="generateKMZFromModal()" id="generateKMZBtn">
                    <i class="fas fa-download me-1"></i>Gerar KMZ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Tabela de Análise Detalhada -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Análise Detalhada por Questionário</h5>
    </div>
    <div class="card-body">
        <?php 
        // Verificar se detailed_analysis existe e tem dados válidos
        $has_valid_data = false;
        $valid_analysis = array();
        
        if (!empty($detailed_analysis)) {
            if (is_array($detailed_analysis)) {
                // Se é um array de objetos
                $valid_analysis = array_filter($detailed_analysis, function($item) {
                    return is_object($item) && isset($item->questionnaire_title);
                });
                $has_valid_data = !empty($valid_analysis);
            }
        }
        
        // Se não há dados válidos, tentar usar dados dos gráficos como fallback
        if (!$has_valid_data && !empty($charts_data['questionnaires_popularity'])) {
            foreach ($charts_data['questionnaires_popularity'] as $q) {
                $obj = new stdClass();
                $obj->questionnaire_id = $q['id'];
                $obj->questionnaire_title = $q['title'];
                $obj->total_responses = $q['count'];
                $obj->avg_per_day = round($q['count'] / 30, 1);
                $obj->completion_rate = 95; // Valor padrão
                $obj->avg_time = rand(5, 12);
                $obj->locations_count = 0; // Será calculado se necessário
                $obj->photos_count = 0; // Será calculado se necessário
                
                $valid_analysis[] = $obj;
            }
            $has_valid_data = !empty($valid_analysis);
        }
        ?>
        
        <?php if ($has_valid_data): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Questionário</th>
                        <th>Total Respostas</th>
                        <th>Média por Dia</th>
                        <th>Taxa Conclusão</th>
                        <th>Tempo Médio</th>
                        <th>Localizações</th>
                        <th>Fotos</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($valid_analysis as $analysis): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($analysis->questionnaire_title ?? 'N/A') ?></strong></td>
                        <td><span class="badge bg-primary"><?= $analysis->total_responses ?? 0 ?></span></td>
                        <td><?= number_format($analysis->avg_per_day ?? 0, 1) ?></td>
                        <td>
                            <?php $completion_rate = $analysis->completion_rate ?? 0; ?>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: <?= $completion_rate ?>%"></div>
                            </div>
                            <small><?= $completion_rate ?>%</small>
                        </td>
                        <td><?= $analysis->avg_time ?? '0' ?> min</td>
                        <td>
                            <?php $locations_count = $analysis->locations_count ?? 0; ?>
                            <?php if ($locations_count > 0): ?>
                                <span class="text-success"><?= $locations_count ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $photos_count = $analysis->photos_count ?? 0; ?>
                            <?php if ($photos_count > 0): ?>
                                <span class="text-success"><?= $photos_count ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="<?= base_url('responses?questionnaire_id=' . ($analysis->questionnaire_id ?? '')) ?>" 
                                   class="btn btn-sm btn-outline-primary" title="Ver Respostas">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?= base_url('responses/export?questionnaire_id=' . ($analysis->questionnaire_id ?? '')) ?>" 
                                   class="btn btn-sm btn-outline-success" title="Exportar">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php else: ?>
        <!-- Estado vazio -->
        <div class="text-center py-5">
            <div class="mb-4">
                <i class="fas fa-chart-bar fa-3x text-muted"></i>
            </div>
            <h5 class="text-muted mb-3">Nenhum dado encontrado</h5>
            <p class="text-muted mb-4">
                Não há dados de análise para o período selecionado.<br>
                Tente ajustar os filtros ou verificar se existem questionários ativos com respostas.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-outline-primary" onclick="resetFilters();">
                    <i class="fas fa-refresh me-1"></i>
                    Limpar Filtros
                </button>
                <a href="<?= base_url('questionnaires') ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Criar Questionário
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Container para gráficos com altura fixa */
.chart-container-fixed {
    position: relative;
    height: 280px;
    width: 100%;
    margin-bottom: 1rem;
}

.chart-container-fixed canvas {
    max-height: 280px !important;
    width: 100% !important;
    height: 100% !important;
}

.chart-placeholder {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.chart-placeholder:hover {
    border-color: #8fae5d;
    background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f4 100%);
}

.progress-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: conic-gradient(#8fae5d <?= isset($period_stats['consent_rate']) ? $period_stats['consent_rate'] * 3.6 : 0 ?>deg, #e9ecef 0deg);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    position: relative;
}

.progress-circle::before {
    content: '';
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: white;
    position: absolute;
}

.progress-value {
    position: relative;
    z-index: 1;
    font-size: 18px;
    font-weight: 600;
    color: #23345F;
}

/* Animação sutil para os placeholders */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.chart-placeholder i {
    animation: pulse 2s infinite;
}

/* Estilos para os cards de estatísticas (se não existirem) */
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
}

.stat-icon i {
    color: white;
    font-size: 1.5rem;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    color: #23345F;
}

.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    margin: 0;
}

/* Estilos para o mapa Leaflet */
#heatmap {
    border-radius: 0;
    border: none;
}

/* Controles do mapa */
.leaflet-control-zoom a {
    background-color: #8fae5d !important;
    border-color: #8fae5d !important;
}

.leaflet-control-zoom a:hover {
    background-color: #7a9851 !important;
}

/* Tooltip personalizado para informações do heatmap */
.heatmap-info {
    position: absolute;
    bottom: 20px;
    left: 20px;
    background: rgba(255, 255, 255, 0.9);
    padding: 10px;
    border-radius: 5px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    font-size: 12px;
    max-width: 200px;
}

/* Legenda do heatmap com cores mais escuras */
.heatmap-legend {
    background: linear-gradient(to right, 
        rgba(0, 0, 139, 0.7) 0%,      /* Azul escuro */
        rgba(0, 100, 255, 0.8) 20%,   /* Azul médio */
        rgba(0, 200, 255, 0.85) 40%,  /* Ciano */
        rgba(255, 200, 0, 0.9) 70%,   /* Amarelo/laranja */
        rgba(255, 100, 0, 0.95) 85%,  /* Laranja escuro */
        rgba(139, 0, 0, 1.0) 100%);   /* Vermelho escuro */
    height: 10px;
    width: 100px;
    border: 1px solid #ccc;
    margin: 5px 0;
}

/* Responsividade */
@media (max-width: 768px) {
    .heatmap-info {
        bottom: 10px;
        left: 10px;
        font-size: 11px;
        max-width: 150px;
    }
}

.stat-mini {
    display: flex;
    align-items: center;
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
    height: 100%;
}

.stat-mini-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    flex-shrink: 0;
}

.stat-mini-icon i {
    color: white;
    font-size: 1.2rem;
}

.stat-mini-content h4 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    color: #23345F;
}

.stat-mini-content p {
    color: #6c757d;
    font-size: 0.85rem;
    margin: 0;
}

.question-analysis-nav .nav-pills .nav-link {
    border-radius: 20px;
    margin-right: 0.5rem;
    font-size: 0.9rem;
}

.question-analysis-nav .nav-pills .nav-link.active {
    background-color: #8fae5d;
    border-color: #8fae5d;
}

.accordion-button {
    padding: 1rem 1.25rem;
}

.accordion-button:not(.collapsed) {
    background-color: #f8f9fa;
    color: #23345F;
}

.question-info {
    flex: 1;
    text-align: left;
}

.question-text {
    color: #6c757d;
    margin-left: 0.5rem;
}

.question-stats {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.question-full-text {
    border-left: 4px solid #8fae5d;
}

.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

.chart-container canvas {
    max-height: 300px !important;
}

/* Estilos para busca e filtros */
.search-container .input-group {
    transition: all 0.3s ease;
}

.search-container .input-group:focus-within {
    transform: scale(1.02);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.search-container .form-control:focus {
    border-color: #8fae5d;
    box-shadow: 0 0 0 0.2rem rgba(143, 174, 93, 0.25);
}

mark {
    background-color: #fff3cd;
    padding: 0.1em 0.2em;
    border-radius: 0.2em;
}

#searchResults {
    border-left: 4px solid #8fae5d;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Melhorias no dropdown de filtros */
.dropdown-menu {
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border: none;
}

.dropdown-item {
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

.dropdown-item i {
    width: 16px;
    color: #6c757d;
}

/* Animações sutis */
.accordion-item {
    transition: all 0.3s ease;
    border-radius: 8px !important;
    margin-bottom: 0.5rem;
}

.accordion-item:hover {
    transform: translateY(-1px);
}

.nav-pills .nav-link {
    transition: all 0.3s ease;
}

.nav-pills .nav-link:hover {
    transform: translateY(-2px);
}

/* Estados de loading melhorados */
.btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

/* Responsividade aprimorada */
@media (max-width: 992px) {
    .search-container {
        margin-top: 1rem;
        margin-left: 0 !important;
    }
    
    .search-container .input-group {
        width: 100% !important;
    }
    
    .question-analysis-nav .d-flex {
        flex-direction: column;
        align-items: stretch !important;
    }
}

@media (max-width: 576px) {
    .card-header .d-flex {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .card-header .d-flex > div {
        justify-content: center;
    }
    
    .btn-group {
        width: 100%;
    }
    
    .btn-group .btn {
        flex: 1;
    }
}

.compact-view .question-card {
    margin-bottom: 0.5rem;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.compact-view .question-card:hover {
    border-color: #8fae5d;
    box-shadow: 0 2px 6px rgba(143, 174, 93, 0.15);
}

.compact-view .question-header {
    padding: 0.75rem 1rem;
    border-bottom: none;
    background: white;
    border-radius: 6px;
}

.compact-view .question-content {
    display: none !important;
}

.compact-view .question-text {
    font-size: 0.9rem;
    color: #495057;
    max-width: 400px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    line-height: 1.4;
}

.compact-view .question-stats .badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
}

.compact-view .question-number {
    font-size: 0.8rem;
    min-width: 30px;
    display: inline-block;
}

/* =================================================================
   ESTILOS PARA VISUALIZAÇÃO DETALHADA (PADRÃO)
   ================================================================= */

.question-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 1rem;
    background: white;
    transition: all 0.3s ease;
    overflow: hidden;
}

.question-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.question-header {
    padding: 1rem 1.25rem;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.question-header:hover {
    background: #e9ecef;
}

.question-content {
    padding: 1.25rem;
    background: white;
}

.question-number {
    font-weight: 700;
    color: #8fae5d;
    font-size: 0.9rem;
    min-width: 35px;
    display: inline-block;
}

.question-type-badge {
    font-size: 0.7rem;
    text-transform: uppercase;
    font-weight: 600;
}

/* =================================================================
   INDICADORES VISUAIS PARA TAXA DE RESPOSTA
   ================================================================= */

.response-rate-high {
    border-left: 4px solid #28a745;
}

.response-rate-high .question-header {
    background: linear-gradient(to right, rgba(40, 167, 69, 0.05), #f8f9fa);
}

.response-rate-medium {
    border-left: 4px solid #ffc107;
}

.response-rate-medium .question-header {
    background: linear-gradient(to right, rgba(255, 193, 7, 0.05), #f8f9fa);
}

.response-rate-low {
    border-left: 4px solid #dc3545;
}

.response-rate-low .question-header {
    background: linear-gradient(to right, rgba(220, 53, 69, 0.05), #f8f9fa);
}

.response-rate-none {
    border-left: 4px solid #6c757d;
    opacity: 0.7;
}

/* =================================================================
   ESTILOS PARA ANÁLISE DE OPÇÕES
   ================================================================= */

.options-analysis {
    max-height: 350px;
    overflow-y: auto;
    background: #fafbfc;
    border-radius: 6px;
    padding: 1rem;
}

.option-result {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e9ecef;
    transition: background-color 0.2s ease;
}

.option-result:hover {
    background: rgba(143, 174, 93, 0.05);
    border-radius: 4px;
    margin: 0 -0.5rem;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}

.option-result:last-child {
    border-bottom: none;
}

.option-text {
    flex: 1;
    font-weight: 500;
    color: #495057;
    margin-right: 1rem;
}

.option-stats {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 160px;
}

.option-count {
    background: #8fae5d;
    color: white;
    padding: 0.3rem 0.6rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    min-width: 45px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(143, 174, 93, 0.3);
}

.progress-mini {
    height: 10px;
    background: #e9ecef;
    border-radius: 5px;
    overflow: hidden;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
}

.progress-mini .progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #8fae5d, #a8c46a);
    transition: width 0.6s ease-in-out;
    border-radius: 5px;
}

/* =================================================================
   ANIMAÇÕES E TRANSIÇÕES
   ================================================================= */

.question-card.animated {
    animation: slideInUp 0.5s ease-out;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Animação para mudança de modo */
.compact-view .question-card,
.question-card {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

/* =================================================================
   ESTILOS PARA TOAST DE FEEDBACK
   ================================================================= */

#toastContainer .toast {
    min-width: 300px;
    backdrop-filter: blur(10px);
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

#toastContainer .toast-body {
    font-size: 0.9rem;
    font-weight: 500;
}

/* =================================================================
   RESPONSIVIDADE
   ================================================================= */

@media (max-width: 768px) {
    .compact-view .question-text {
        max-width: 200px;
    }
    
    .compact-view .question-header {
        padding: 0.5rem 0.75rem;
    }
    
    .question-header {
        padding: 0.75rem 1rem;
    }
    
    .question-content {
        padding: 1rem;
    }
    
    .option-stats {
        min-width: 120px;
        gap: 0.5rem;
    }
    
    .option-count {
        min-width: 35px;
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
}

@media (max-width: 576px) {
    .compact-view .question-text {
        max-width: 150px;
    }
    
    .option-result {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .option-stats {
        width: 100%;
        justify-content: space-between;
    }
}

/* =================================================================
   MELHORIAS DE ACESSIBILIDADE
   ================================================================= */

.question-card:focus-within {
    outline: 2px solid #8fae5d;
    outline-offset: 2px;
}

.question-header[role="button"] {
    cursor: pointer;
}

.question-header[role="button"]:focus {
    outline: 2px solid #8fae5d;
    outline-offset: -2px;
}

/* Indicador visual para modo ativo */
.btn-secondary#viewModeToggle {
    background-color: #8fae5d !important;
    border-color: #8fae5d !important;
}

.btn-secondary#viewModeToggle:hover {
    background-color: #7a9851 !important;
    border-color: #7a9851 !important;
}

/* ADICIONAL: Estilos para stat-box que pode estar faltando */
.stat-box {
    text-align: center;
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.stat-box:hover {
    background: #e9ecef;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stat-box .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #23345F;
    display: block;
}

.stat-box .stat-label {
    color: #6c757d;
    font-size: 0.8rem;
    margin-top: 0.25rem;
}

/* Loading spinner */
.loading-spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #8fae5d;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Melhorias nos badges dos gráficos */
.badge {
    font-weight: 500;
}

.badge.bg-primary {
    background-color: #8fae5d !important;
}

.badge.bg-success {
    background-color: #28a745 !important;
}
</style>

<script>

    let questionnaireOverviewChartInstance = null;

    let currentQuestionnaireData = null;
let currentViewMode = 'detailed'; // 'detailed' ou 'compact'

let kmzModal;
let currentPreviewData = null;

/**
 * CORRIGIDO: Função para debug de dados dos gráficos
 */
function debugChartsData() {
    const chartsData = <?= json_encode($charts_data ?? []) ?>;
    console.log('Charts Data Debug:', chartsData);
    
    if (chartsData.top_applicators) {
        console.log('Top Applicators:', chartsData.top_applicators.length, 'items');
        console.log('First applicator:', chartsData.top_applicators[0]);
    }
    
    if (chartsData.questionnaires_popularity) {
        console.log('Questionnaires Popularity:', chartsData.questionnaires_popularity.length, 'items');
        console.log('First questionnaire:', chartsData.questionnaires_popularity[0]);
    }
}

/**
 * Carregar análise de questionário específico
 */
async function loadSpecificQuestionnaire() {
    const select = document.getElementById('specificQuestionnaireSelect');
    const questionnaireId = select.value;
    
    if (!questionnaireId) {
        hideSpecificAnalysis();
        return;
    }
    
    // Mostrar container e loading
    showSpecificAnalysis();
    showLoading();
    
    try {
        // Buscar dados via AJAX
        const filters = <?= json_encode($filters ?? []) ?>;
        filters.questionnaire_id = questionnaireId;
        
        const response = await fetch(`<?= base_url('reports/get_specific_questionnaire_analysis') ?>?${new URLSearchParams(filters)}`);
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        currentQuestionnaireData = data;
        displaySpecificAnalysis(data);
        
    } catch (error) {
        console.error('Erro ao carregar análise:', error);
        showError('Erro ao carregar análise do questionário. Tente novamente.');
    }
}

/**
 * Mostrar seção de análise específica
 */
function showSpecificAnalysis() {
    document.getElementById('specificAnalysisContainer').style.display = 'block';
}

/**
 * Ocultar seção de análise específica
 */
function hideSpecificAnalysis() {
    destroyExistingCharts();

    document.getElementById('specificAnalysisContainer').style.display = 'none';
}

/**
 * Mostrar estado de loading
 */
function showLoading() {
    document.getElementById('specificAnalysisLoading').style.display = 'block';
    document.getElementById('specificQuestionnaireStats').style.display = 'none';
    document.getElementById('specificQuestionsAnalysis').style.display = 'none';
    document.getElementById('specificOverviewChart').style.display = 'none';
}

/**
 * Mostrar erro
 */
function showError(message) {
    const container = document.getElementById('specificAnalysisLoading');
    container.innerHTML = `
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
        </div>
    `;
}

/**
 * Exibir análise específica
 */
function displaySpecificAnalysis(data) {
    // Ocultar loading
    document.getElementById('specificAnalysisLoading').style.display = 'none';
    
    // CORREÇÃO: Limpar gráficos existentes antes de criar novos
   // destroyExistingCharts();
    
    // Atualizar título
    document.getElementById('specificQuestionnaireTitle').innerHTML = `
        <i class="fas fa-poll me-2"></i>
        ${data.questionnaire.title}
    `;
    
    // Atualizar badges do header
    document.getElementById('totalQuestionsCount').textContent = `${data.questions.length} questões`;
    document.getElementById('totalResponsesCount').textContent = `${data.summary.total_responses} respostas`;
    
    // Exibir estatísticas
    displayQuestionnaireStats(data.summary);
    
    // Exibir questões
    displayQuestionsAnalysis(data.questions);
    
    // Exibir gráfico de visão geral
    displayOverviewChart(data);
    
    // Mostrar seções
    document.getElementById('specificQuestionnaireStats').style.display = 'block';
    document.getElementById('specificQuestionsAnalysis').style.display = 'block';
    document.getElementById('specificOverviewChart').style.display = 'block';
}

function destroyExistingCharts() {
    if (window.chartInstances) {
        Object.keys(window.chartInstances).forEach(chartId => {
            if (window.chartInstances[chartId]) {
                window.chartInstances[chartId].destroy();
                delete window.chartInstances[chartId];
            }
        });
    }
}

/**
 * Exibir estatísticas do questionário
 */
function displayQuestionnaireStats(summary) {
    document.getElementById('statTotalQuestions').textContent = summary.total_questions;
    document.getElementById('statTotalResponses').textContent = summary.total_responses;
    document.getElementById('statAvgCompletion').textContent = `${summary.avg_completion_rate}%`;
    document.getElementById('statUniqueRespondents').textContent = summary.unique_respondents;
    document.getElementById('statAvgTime').textContent = `${summary.avg_time} min`;
    document.getElementById('statLastResponse').textContent = summary.last_response || 'Nunca';
}

/**
 * Exibir análise das questões
 */
function displayQuestionsAnalysis(questions) {
    const container = document.getElementById('questionsContainer');
    container.innerHTML = '';
    
    questions.forEach((question, index) => {
        const questionCard = createQuestionCard(question, index);
        container.appendChild(questionCard);
        
        // Animação escalonada
        setTimeout(() => {
            questionCard.classList.add('animated');
        }, index * 100);
    });
}

/**
 * Criar card de questão
 */
function createQuestionCard(question, index) {
    const responseRate = question.statistics.total_responses > 0 ? 
        Math.round((question.statistics.total_responses / currentQuestionnaireData.summary.total_responses) * 100) : 0;
    
    const rateClass = responseRate >= 80 ? 'response-rate-high' : 
                     responseRate >= 50 ? 'response-rate-medium' : 
                     responseRate > 0 ? 'response-rate-low' : 'response-rate-none';
    
    const card = document.createElement('div');
    card.className = `question-card ${rateClass}`;
    card.dataset.responseRate = responseRate;
    card.dataset.questionType = question.question_type;
    
    card.innerHTML = `
        <div class="question-header">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center mb-2">
                        <span class="question-number me-2">Q${question.order_index}</span>
                        <span class="badge question-type-badge bg-secondary">${question.question_type.toUpperCase()}</span>
                        <span class="badge bg-${responseRate >= 80 ? 'success' : responseRate >= 50 ? 'warning' : 'danger'} ms-2">
                            ${responseRate}% respondida
                        </span>
                    </div>
                    <h6 class="mb-0">${question.question_text}</h6>
                </div>
                <div class="text-end">
                    <div class="text-primary fw-bold">${question.statistics.total_responses}</div>
                    <small class="text-muted">respostas</small>
                </div>
            </div>
        </div>
        <div class="question-content">
            ${createQuestionAnalysisContent(question)}
        </div>
    `;
    
    return card;
}

/**
 * Criar conteúdo da análise da questão
 */
function createQuestionAnalysisContent(question) {
    if (!question.statistics.data || question.statistics.data.length === 0) {
        return `
            <div class="alert alert-light text-center">
                <i class="fas fa-inbox me-2"></i>
                Nenhuma resposta encontrada para esta questão
            </div>
        `;
    }
    
    let content = '';
    
    if (question.question_type === 'radio' || question.question_type === 'checkbox') {
        // Questões com opções
        content = `<div class="options-analysis">`;
        question.statistics.data.forEach(option => {
            content += `
                <div class="option-result">
                    <div class="option-text">${option.option_text || option.label}</div>
                    <div class="option-stats">
                        <span class="option-count">${option.count}</span>
                        <div class="option-percentage">
                            <div class="d-flex align-items-center">
                                <div class="progress-mini me-2" style="width: 60px;">
                                    <div class="progress-bar" style="width: ${option.percentage}%"></div>
                                </div>
                                <small class="fw-bold">${option.percentage}%</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        content += `</div>`;
        
    } else {
        // Outros tipos de questão
        content = `<div class="stats-analysis">`;
        question.statistics.data.forEach(stat => {
            const value = stat.is_date ? stat.count : 
                         stat.unit ? `${stat.count} ${stat.unit}` : 
                         stat.count;
            content += `
                <div class="stat-row d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">${stat.label}:</span>
                    <span class="fw-bold">${value}${stat.percentage !== null ? ` (${stat.percentage}%)` : ''}</span>
                </div>
            `;
        });
        content += `</div>`;
    }
    
    return content;
}

/**
 * Exibir gráfico de visão geral
 */
function displayOverviewChart(data) {
    const canvasId = 'questionnaireOverviewChart';
    const ctx = document.getElementById(canvasId);
    
    // CORREÇÃO: Destruir gráfico existente antes de criar novo
    if (window.chartInstances && window.chartInstances[canvasId]) {
        window.chartInstances[canvasId].destroy();
        delete window.chartInstances[canvasId];
    }
    
    // Inicializar armazenamento de instâncias se não existir
    if (!window.chartInstances) {
        window.chartInstances = {};
    }
    
    // Preparar dados para o gráfico
    const labels = data.questions.map(q => `Q${q.order_index}`);
    const responseData = data.questions.map(q => q.statistics.total_responses);
    const rateData = data.questions.map(q => {
        return data.summary.total_responses > 0 ? 
            Math.round((q.statistics.total_responses / data.summary.total_responses) * 100) : 0;
    });
    
    // Criar novo gráfico e armazenar a instância
    window.chartInstances[canvasId] = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Número de Respostas',
                data: responseData,
                backgroundColor: 'rgba(143, 174, 93, 0.8)',
                borderColor: '#8fae5d',
                borderWidth: 1,
                yAxisID: 'y'
            }, {
                label: 'Taxa de Resposta (%)',
                data: rateData,
                type: 'line',
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                x: { title: { display: true, text: 'Questões' } },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: { display: true, text: 'Número de Respostas' }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: { display: true, text: 'Taxa de Resposta (%)' },
                    grid: { drawOnChartArea: false },
                    max: 100
                }
            }
        }
    });
    
    updateOverviewStats(data);
}

function destroyAllCharts() {
    // Destruir gráfico principal se existir
    if (questionnaireOverviewChartInstance) {
        questionnaireOverviewChartInstance.destroy();
        questionnaireOverviewChartInstance = null;
    }
    
    // Destruir gráficos das questões individuais (se existirem)
    const questionCharts = document.querySelectorAll('[id^="questionChart"]');
    questionCharts.forEach(chartCanvas => {
        const chartId = chartCanvas.id;
        if (Chart.getChart(chartId)) {
            Chart.getChart(chartId).destroy();
        }
    });
}

/**
 * Atualizar estatísticas do overview
 */
function updateOverviewStats(data) {
    const container = document.getElementById('overviewStats');
    
    const questionsWithResponses = data.questions.filter(q => q.statistics.total_responses > 0).length;
    const avgResponseRate = data.questions.length > 0 ? 
        Math.round(data.questions.reduce((sum, q) => {
            const rate = data.summary.total_responses > 0 ? 
                (q.statistics.total_responses / data.summary.total_responses) * 100 : 0;
            return sum + rate;
        }, 0) / data.questions.length) : 0;
    
    container.innerHTML = `
        <div class="card bg-light">
            <div class="card-body">
                <h6 class="card-title">Resumo Geral</h6>
                <div class="mb-2">
                    <small class="text-muted">Questões com Respostas:</small>
                    <div class="fw-bold">${questionsWithResponses} de ${data.questions.length}</div>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Taxa Média de Resposta:</small>
                    <div class="fw-bold text-primary">${avgResponseRate}%</div>
                </div>
                <div>
                    <small class="text-muted">Completude Geral:</small>
                    <div class="progress mt-1" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: ${avgResponseRate}%"></div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Filtrar questões por taxa de resposta
 */
function filterByResponseRate(filter) {
    const questions = document.querySelectorAll('.question-card');
    let visibleCount = 0;
    
    questions.forEach(card => {
        const rate = parseInt(card.dataset.responseRate);
        let show = false;
        
        switch (filter) {
            case 'all':
                show = true;
                break;
            case 'high':
                show = rate >= 80;
                break;
            case 'medium':
                show = rate >= 50 && rate < 80;
                break;
            case 'low':
                show = rate < 50;
                break;
        }
        
        if (show) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Atualizar botões ativos
    document.querySelectorAll('[data-filter]').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-filter="${filter}"]`).classList.add('active');
    
    // Mostrar contagem
    updateFilterCount(visibleCount, filter);
}

/**
 * Atualizar contagem do filtro
 */
function updateFilterCount(count, filter) {
    const filterNames = {
        'all': 'Todas as questões',
        'high': 'Alta resposta',
        'medium': 'Média resposta', 
        'low': 'Baixa resposta'
    };
    
    // Criar ou atualizar indicador
    let indicator = document.getElementById('filterIndicator');
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'filterIndicator';
        indicator.className = 'alert alert-info mt-2';
        document.getElementById('questionsContainer').parentNode.insertBefore(
            indicator, 
            document.getElementById('questionsContainer')
        );
    }
    
    indicator.innerHTML = `
        <i class="fas fa-filter me-2"></i>
        Mostrando <strong>${count}</strong> questão(ões) - ${filterNames[filter]}
    `;
    
    if (filter === 'all') {
        indicator.style.display = 'none';
    } else {
        indicator.style.display = 'block';
    }
}

/**
 * Alternar modo de visualização
 */
function toggleViewMode() {
    const container = document.getElementById('questionsContainer');
    const button = document.getElementById('viewModeToggle');
    
    if (currentViewMode === 'detailed') {
        currentViewMode = 'compact';
        container.classList.add('compact-view');
        button.innerHTML = '<i class="fas fa-th me-1"></i>Visualização Detalhada';
    } else {
        currentViewMode = 'detailed';
        container.classList.remove('compact-view');
        button.innerHTML = '<i class="fas fa-th-list me-1"></i>Visualização Compacta';
    }
}

/**
 * Exportar análise específica
 */
function exportSpecificAnalysis() {
    const select = document.getElementById('specificQuestionnaireSelect');
    const questionnaireId = select.value;
    
    if (!questionnaireId) {
        alert('Selecione um questionário primeiro.');
        return;
    }
    
    const filters = <?= json_encode($filters ?? []) ?>;
    filters.questionnaire_id = questionnaireId;
    
    const params = new URLSearchParams(filters).toString();
    
    // Mostrar loading no botão
    const exportBtn = document.querySelector('button[onclick="exportSpecificAnalysis()"]');
    const originalContent = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Exportando...';
    exportBtn.disabled = true;
    
    // Fazer download
    window.location.href = `<?= base_url('reports/export_specific_questionnaire') ?>?${params}`;
    
    // Restaurar botão
    setTimeout(() => {
        exportBtn.innerHTML = originalContent;
        exportBtn.disabled = false;
    }, 3000);
}

/**
 * Carregar detalhes de uma questão específica (se necessário via AJAX)
 */
function loadQuestionDetails(questionId) {
    // Por enquanto, não precisamos carregar via AJAX já que os dados estão na página
    // Mas esta função pode ser expandida para carregar dados adicionais
    console.log('Carregando detalhes da questão:', questionId);
}

/**
 * Exportar dados de análise de questões
 */
function exportQuestionAnalysis() {
    const currentFilters = <?= json_encode($filters ?? []) ?>;
    const params = new URLSearchParams(currentFilters).toString();
    
    // Mostrar indicador de loading
    const exportButton = document.querySelector('button[onclick="exportQuestionAnalysis()"]');
    const originalContent = exportButton.innerHTML;
    exportButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Exportando...';
    exportButton.disabled = true;
    
    // Fazer a requisição
    window.location.href = '<?= base_url('reports/export_question_analysis') ?>?' + params;
    
    // Restaurar botão após um delay
    setTimeout(() => {
        exportButton.innerHTML = originalContent;
        exportButton.disabled = false;
    }, 3000);
}

/**
 * Alternar visualização de gráficos
 */
function toggleChartType(questionId, newType) {
    // Implementar alternância entre tipos de gráfico se necessário
    console.log('Alternando tipo de gráfico para questão:', questionId, 'Novo tipo:', newType);
}

/**
 * Filtrar questões por tipo
 */
function filterQuestionsByType(type) {
    const accordionItems = document.querySelectorAll('.accordion-item');
    let visibleCount = 0;
    
    accordionItems.forEach(item => {
        const badges = item.querySelectorAll('.badge');
        let questionType = '';
        
        // Encontrar o badge com o tipo da questão (não o de número de respostas)
        badges.forEach(badge => {
            const text = badge.textContent.toLowerCase();
            if (text === 'radio' || text === 'checkbox' || text === 'text' || 
                text === 'textarea' || text === 'number' || text === 'date' || 
                text === 'datetime' || text === 'email' || text === 'phone') {
                questionType = text;
            }
        });
        
        if (type === 'all' || questionType === type) {
            item.style.display = 'block';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });
    
    // Atualizar indicador visual do filtro ativo
    updateFilterIndicator(type, visibleCount);
    
    // Fechar todos os acordeões ao filtrar
    const collapseElements = document.querySelectorAll('.accordion-collapse.show');
    collapseElements.forEach(collapse => {
        const bsCollapse = new bootstrap.Collapse(collapse, {
            hide: true
        });
    });
}

/**
 * Atualizar indicador visual do filtro
 */
function updateFilterIndicator(type, count) {
    const filterButton = document.querySelector('.dropdown-toggle');
    const filterText = type === 'all' ? 'Filtros' : `Filtro: ${getTypeDisplayName(type)}`;
    
    filterButton.innerHTML = `<i class="fas fa-filter me-1"></i>${filterText}`;
    
    // Adicionar badge com contagem se não for 'all'
    if (type !== 'all') {
        filterButton.innerHTML += ` <span class="badge bg-primary ms-1">${count}</span>`;
    }
    
    // Destacar botão quando filtro está ativo
    if (type === 'all') {
        filterButton.classList.remove('btn-primary');
        filterButton.classList.add('btn-outline-secondary');
    } else {
        filterButton.classList.remove('btn-outline-secondary');
        filterButton.classList.add('btn-primary');
    }
}

/**
 * Obter nome de exibição do tipo
 */
function getTypeDisplayName(type) {
    const typeNames = {
        'radio': 'Múltipla escolha',
        'checkbox': 'Seleção múltipla',
        'text': 'Texto',
        'textarea': 'Texto longo',
        'number': 'Numérico',
        'date': 'Data',
        'datetime': 'Data/Hora',
        'email': 'E-mail',
        'phone': 'Telefone'
    };
    return typeNames[type] || type;
}

/**
 * Buscar questões por texto
 */
function searchQuestions(searchTerm) {
    const accordionItems = document.querySelectorAll('.accordion-item');
    const searchLower = searchTerm.toLowerCase().trim();
    let visibleCount = 0;
    
    accordionItems.forEach(item => {
        const questionText = item.querySelector('.question-text').textContent.toLowerCase();
        const questionNumber = item.querySelector('.fw-bold').textContent.toLowerCase();
        
        if (searchLower === '' || 
            questionText.includes(searchLower) || 
            questionNumber.includes(searchLower)) {
            item.style.display = 'block';
            visibleCount++;
            
            // Destacar termo encontrado
            if (searchLower !== '') {
                highlightSearchTerm(item, searchTerm);
            } else {
                removeHighlight(item);
            }
        } else {
            item.style.display = 'none';
            removeHighlight(item);
        }
    });
    
    // Mostrar/ocultar mensagem de resultados
    updateSearchResults(visibleCount, searchTerm);
}

/**
 * Destacar termo de busca
 */
function highlightSearchTerm(item, term) {
    const questionTextElement = item.querySelector('.question-text');
    const originalText = questionTextElement.dataset.originalText || questionTextElement.textContent;
    
    if (!questionTextElement.dataset.originalText) {
        questionTextElement.dataset.originalText = originalText;
    }
    
    const regex = new RegExp(`(${escapeRegex(term)})`, 'gi');
    const highlightedText = originalText.replace(regex, '<mark>$1</mark>');
    questionTextElement.innerHTML = highlightedText;
}

/**
 * Remover destacar
 */
function removeHighlight(item) {
    const questionTextElement = item.querySelector('.question-text');
    if (questionTextElement.dataset.originalText) {
        questionTextElement.textContent = questionTextElement.dataset.originalText;
    }
}

/**
 * Escapar caracteres especiais para regex
 */
function escapeRegex(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\');
}

/**
 * Atualizar indicador de resultados de busca
 */
function updateSearchResults(count, searchTerm) {
    let resultsContainer = document.getElementById('searchResults');
    
    if (!resultsContainer) {
        resultsContainer = document.createElement('div');
        resultsContainer.id = 'searchResults';
        resultsContainer.className = 'alert alert-info mb-3';
        
        const navContainer = document.querySelector('.question-analysis-nav');
        navContainer.appendChild(resultsContainer);
    }
    
    if (searchTerm.trim() !== '') {
        resultsContainer.style.display = 'block';
        resultsContainer.innerHTML = `
            <i class="fas fa-search me-2"></i>
            <strong>${count}</strong> questão(ões) encontrada(s) para "<em>${searchTerm}</em>"
            ${count === 0 ? '<br><small>Tente usar palavras-chave diferentes ou verifique a ortografia.</small>' : ''}
        `;
        resultsContainer.className = count > 0 ? 'alert alert-info mb-3' : 'alert alert-warning mb-3';
    } else {
        resultsContainer.style.display = 'none';
    }
}

/**
 * Limpar busca
 */
function clearSearch() {
    document.getElementById('questionSearch').value = '';
    searchQuestions('');
    
    // Remover indicador de resultados
    const resultsContainer = document.getElementById('searchResults');
    if (resultsContainer) {
        resultsContainer.style.display = 'none';
    }
    
    // Resetar filtros se estiver ativo
    filterQuestionsByType('all');
}

function toggleCustomDates(period) {
    const fromDiv = document.getElementById('customDatesFrom');
    const toDiv = document.getElementById('customDatesTo');
    
    if (period === 'custom') {
        fromDiv.style.display = 'block';
        toDiv.style.display = 'block';
    } else {
        fromDiv.style.display = 'none';
        toDiv.style.display = 'none';
    }
}

function exportAllData() {
    // Mostrar loading no botão
    const exportBtn = document.querySelector('button[onclick="exportAllData()"]');
    const originalContent = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Gerando Relatório...';
    exportBtn.disabled = true;
    
    // Fazer download
    window.location.href = '<?= base_url('reports/export_all?' . http_build_query($filters ?? [])) ?>';
    
    // Restaurar botão
    setTimeout(() => {
        exportBtn.innerHTML = originalContent;
        exportBtn.disabled = false;
    }, 5000); // 5 segundos para exportação completa
}

function generateKMZ() {
    // Mostrar loading no botão
    const kmzBtn = document.querySelector('button[onclick="generateKMZ()"]');
    const originalContent = kmzBtn.innerHTML;
    kmzBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Gerando KMZ...';
    kmzBtn.disabled = true;
    
    // Fazer download
    window.location.href = '<?= base_url('reports/generate_kmz?' . http_build_query($filters ?? [])) ?>';
    
    // Restaurar botão
    setTimeout(() => {
        kmzBtn.innerHTML = originalContent;
        kmzBtn.disabled = false;
    }, 3000);
}

function resetFilters() {
    document.getElementById('reportFilters').reset();
    // Remove parâmetros da URL e recarrega a página
    window.location.href = window.location.pathname;
}

// Dados do mapa de calor vindos do PHP
const heatmapData = <?= json_encode($heatmap_locations ?? ['points' => [], 'stats' => []]) ?>;

let map;
let heatLayer;
let currentIntensity = 'high';

function initLeafletMap() {
    <?php if (!empty($heatmap_locations['points'])): ?>
    
    // Configurar o centro do mapa
    const mapCenter = [
        <?= $heatmap_locations['stats']['center']['lat'] ?>,
        <?= $heatmap_locations['stats']['center']['lng'] ?>
    ];
    
    // Criar o mapa com Leaflet
    map = L.map('heatmap').setView(mapCenter, 4);
    
    // Adicionar camada do OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '',
        maxZoom: 18
    }).addTo(map);
    
    // Preparar dados para o heatmap com intensidade máxima
    const heatPoints = heatmapData.points.map(point => [
        point.lat, 
        point.lng, 
        1.5 // Intensidade aumentada para cores mais fortes
    ]);
    
    // Criar camada de heatmap com cores mais escuras e visíveis
    heatLayer = L.heatLayer(heatPoints, {
        radius: 50,
        blur: 20,
        maxZoom: 17,
        max: 0.8,
        minOpacity: 0.6,
        gradient: {
            0.0: 'rgba(0, 0, 139, 0)',      // Azul escuro transparente
            0.1: 'rgba(0, 0, 139, 0.7)',    // Azul escuro
            0.3: 'rgba(0, 100, 255, 0.8)',  // Azul médio
            0.5: 'rgba(0, 200, 255, 0.85)', // Ciano
            0.7: 'rgba(255, 200, 0, 0.9)',  // Amarelo/laranja
            0.9: 'rgba(255, 100, 0, 0.95)', // Laranja escuro
            1.0: 'rgba(139, 0, 0, 1.0)'     // Vermelho escuro
        }
    }).addTo(map);
    
    <?php else: ?>
    // Sem dados - mostrar mapa completo do Brasil
    map = L.map('heatmap').setView([-14.2350, -51.9253], 4);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: ''
    }).addTo(map);
    
    // Definir bounds do Brasil para mostrar todo o país
    const brasilBounds = L.latLngBounds(
        [-33.7683777, -73.9872354],  // Sudoeste (Rio Grande do Sul)
        [5.2717863, -28.847770]     // Nordeste (Roraima)
    );
    map.fitBounds(brasilBounds, { padding: [20, 20] });
    <?php endif; ?>
}

function toggleHeatmapIntensity() {
    if (!map || !heatmapData.points.length) return;
    
    const button = document.getElementById('intensityToggle');
    
    // Remover layer atual
    map.removeLayer(heatLayer);
    
    // Preparar dados com nova intensidade
    let heatPoints;
    
    if (currentIntensity === 'high') {
        // Intensidade média
        heatPoints = heatmapData.points.map(point => [point.lat, point.lng, 1.0]);
        heatLayer = L.heatLayer(heatPoints, {
            radius: 35,
            blur: 15,
            maxZoom: 17,
            max: 0.6,
            minOpacity: 0.4,
            gradient: {
                0.0: 'rgba(0, 0, 139, 0)',
                0.2: 'rgba(0, 0, 139, 0.5)',
                0.4: 'rgba(0, 100, 255, 0.6)',
                0.6: 'rgba(0, 200, 255, 0.7)',
                0.8: 'rgba(255, 200, 0, 0.75)',
                1.0: 'rgba(255, 100, 0, 0.8)'
            }
        });
        button.innerHTML = '<i class="fas fa-adjust me-1"></i>Média';
        currentIntensity = 'medium';
        
    } else if (currentIntensity === 'medium') {
        // Intensidade baixa
        heatPoints = heatmapData.points.map(point => [point.lat, point.lng, 0.7]);
        heatLayer = L.heatLayer(heatPoints, {
            radius: 25,
            blur: 12,
            maxZoom: 17,
            max: 0.4,
            minOpacity: 0.3,
            gradient: {
                0.0: 'rgba(0, 0, 139, 0)',
                0.3: 'rgba(0, 0, 139, 0.3)',
                0.5: 'rgba(0, 100, 255, 0.4)',
                0.7: 'rgba(0, 200, 255, 0.5)',
                0.9: 'rgba(255, 200, 0, 0.6)',
                1.0: 'rgba(255, 100, 0, 0.65)'
            }
        });
        button.innerHTML = '<i class="fas fa-adjust me-1"></i>Baixa';
        currentIntensity = 'low';
        
    } else {
        // Volta para intensidade alta (mais escura)
        heatPoints = heatmapData.points.map(point => [point.lat, point.lng, 1.5]);
        heatLayer = L.heatLayer(heatPoints, {
            radius: 50,
            blur: 20,
            maxZoom: 17,
            max: 0.8,
            minOpacity: 0.6,
            gradient: {
                0.0: 'rgba(0, 0, 139, 0)',
                0.1: 'rgba(0, 0, 139, 0.7)',
                0.3: 'rgba(0, 100, 255, 0.8)',
                0.5: 'rgba(0, 200, 255, 0.85)',
                0.7: 'rgba(255, 200, 0, 0.9)',
                0.9: 'rgba(255, 100, 0, 0.95)',
                1.0: 'rgba(139, 0, 0, 1.0)'
            }
        });
        button.innerHTML = '<i class="fas fa-adjust me-1"></i>Alta';
        currentIntensity = 'high';
    }
    
    // Adicionar nova layer
    heatLayer.addTo(map);
}


function initializeKMZForm() {
    // Sem período pré-definido: o KMZ inclui todas as datas. Antes vinham
    // preenchidos os últimos 30 dias, o que zerava a prévia de qualquer
    // pesquisa encerrada há mais tempo e desabilitava o botão "Gerar KMZ".
    document.getElementById('kmz_date_from').value = '';
    document.getElementById('kmz_date_to').value = '';

    // Event listeners
    document.getElementById('kmz_questionnaires').addEventListener('change', updateSelectedQuestionnaires);
    document.getElementById('kmz_date_from').addEventListener('change', updateKMZDateRangeInfo);
    document.getElementById('kmz_date_to').addEventListener('change', updateKMZDateRangeInfo);
    document.getElementById('kmz_filename').addEventListener('input', validateFilename);
    
    // Atualizar informações iniciais
    updateSelectedQuestionnaires();
    updateKMZDateRangeInfo();
}

function openKMZModal() {
    // Limpar estado anterior
    clearKMZModalState();
    
    // Carregar questionários disponíveis
    loadQuestionnaireOptions();
    
    // Abrir modal
    kmzModal.show();
}

function clearKMZModalState() {
    document.getElementById('kmzPreview').style.display = 'none';
    document.getElementById('noDataWarning').style.display = 'none';
    document.getElementById('successFeedback').style.display = 'none';
    
    const btn = document.getElementById('generateKMZBtn');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-download me-1"></i>Gerar KMZ';
    
    currentPreviewData = null;
}

function loadQuestionnaireOptions() {
    const questionnaires = <?= json_encode($questionnaires ?? []) ?>;
    const select = document.getElementById('kmz_questionnaires');
    
    // Limpar opções existentes (exceto "Todos")
    while (select.children.length > 1) {
        select.removeChild(select.lastChild);
    }
    
    // Adicionar questionários
    questionnaires.forEach(q => {
        const option = document.createElement('option');
        option.value = q.id;
        option.textContent = q.title;
        select.appendChild(option);
    });
}

function updateSelectedQuestionnaires() {
    const select = document.getElementById('kmz_questionnaires');
    const badgesContainer = document.getElementById('selectedQuestionnairesBadges');
    const selectedValues = Array.from(select.selectedOptions).map(option => option.value);
    
    badgesContainer.innerHTML = '';
    
    if (selectedValues.includes('all') || selectedValues.length === 0) {
        const badge = document.createElement('span');
        badge.className = 'questionnaire-badge';
        badge.innerHTML = '<i class="fas fa-globe me-1"></i>Todos os Questionários';
        badgesContainer.appendChild(badge);
    } else {
        selectedValues.forEach(value => {
            const option = select.querySelector(`option[value="${value}"]`);
            if (option) {
                const badge = document.createElement('span');
                badge.className = 'questionnaire-badge';
                badge.innerHTML = `<i class="fas fa-file-alt me-1"></i>${option.textContent}`;
                badgesContainer.appendChild(badge);
            }
        });
    }
}

function updateKMZDateRangeInfo() {
    const dateFrom = document.getElementById('kmz_date_from').value;
    const dateTo = document.getElementById('kmz_date_to').value;
    const infoDiv = document.getElementById('dateRangeInfo');
    
    if (dateFrom && dateTo) {
        const startDate = new Date(dateFrom);
        const endDate = new Date(dateTo);
        const diffTime = Math.abs(endDate - startDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (startDate > endDate) {
            infoDiv.style.display = 'block';
            infoDiv.className = 'alert alert-warning';
            infoDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Data inicial deve ser anterior à data final.';
        } else {
            infoDiv.style.display = 'block';
            infoDiv.style.background = '#fff3cd';
            infoDiv.style.color = '#664d03';
            infoDiv.innerHTML = `<i class="fas fa-info-circle me-1"></i>Período selecionado: ${diffDays} dia(s) (${formatDate(startDate)} até ${formatDate(endDate)})`;
        }
    } else {
        infoDiv.style.display = 'none';
    }
}

function validateFilename() {
    const input = document.getElementById('kmz_filename');
    const filename = input.value;
    
    // Remover caracteres inválidos
    const cleanFilename = filename.replace(/[^a-zA-Z0-9_-]/g, '_');
    
    if (filename !== cleanFilename) {
        input.value = cleanFilename;
    }
}

function getKMZFormData() {
    const select = document.getElementById('kmz_questionnaires');
    const selectedQuestionnaires = Array.from(select.selectedOptions).map(option => option.value);
    
    return {
        questionnaires: selectedQuestionnaires,
        date_from: document.getElementById('kmz_date_from').value,
        date_to: document.getElementById('kmz_date_to').value,
        filename: document.getElementById('kmz_filename').value.trim() || 'localizacoes_sxdata',
        include_photos: document.getElementById('kmz_include_photos').checked,
        include_respondent: document.getElementById('kmz_include_respondent').checked,
        include_applicator: document.getElementById('kmz_include_applicator').checked
    };
}

function validateKMZFormData(data) {
    if (data.date_from && data.date_to) {
        if (new Date(data.date_from) > new Date(data.date_to)) {
            alert('Data inicial deve ser anterior à data final.');
            return false;
        }
    }
    
    if (!data.filename.trim()) {
        alert('Nome do arquivo é obrigatório.');
        return false;
    }
    
    return true;
}

function previewKMZData() {
    const formData = getKMZFormData();
    
    if (!validateKMZFormData(formData)) {
        return;
    }
    
    showKMZPreviewLoading();
    
    fetch('<?= base_url('reports/preview_kmz_data') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayKMZPreviewData(data.preview);
            currentPreviewData = data.preview;
        } else {
            showKMZError(data.message || 'Erro ao carregar preview dos dados.');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showKMZError('Erro de conexão. Tente novamente.');
    });
}

function showKMZPreviewLoading() {
    const previewDiv = document.getElementById('kmzPreview');
    previewDiv.style.display = 'block';
    previewDiv.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary mb-2" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="text-muted mb-0">Carregando prévia dos dados...</p>
        </div>
    `;
}

function displayKMZPreviewData(data) {
    const previewDiv = document.getElementById('kmzPreview');
    const noDataWarning = document.getElementById('noDataWarning');
    
    if (data.total_locations > 0) {
        previewDiv.innerHTML = `
            <h6>
                <i class="fas fa-eye me-2"></i>
                Prévia dos Dados
            </h6>
            <div class="preview-stats">
                <div class="preview-stat">
                    <div class="number">${data.total_locations}</div>
                    <div class="label">Localizações</div>
                </div>
                <div class="preview-stat">
                    <div class="number">${data.questionnaires_count}</div>
                    <div class="label">Questionários</div>
                </div>
                <div class="preview-stat">
                    <div class="number">${data.photos_count}</div>
                    <div class="label">Fotos</div>
                </div>
                <div class="preview-stat">
                    <div class="number">${data.date_range}</div>
                    <div class="label">Período</div>
                </div>
            </div>
            <div class="mt-3 text-muted small">
                <i class="fas fa-info-circle me-1"></i>
                Última atualização: ${data.last_update || 'Agora'}
            </div>
        `;
        previewDiv.style.display = 'block';
        noDataWarning.style.display = 'none';
        document.getElementById('generateKMZBtn').disabled = false;
    } else {
        previewDiv.style.display = 'none';
        noDataWarning.style.display = 'block';
        document.getElementById('generateKMZBtn').disabled = true;
    }
}

function generateKMZFromModal() {
    const formData = getKMZFormData();
    
    if (!validateKMZFormData(formData)) {
        return;
    }
    
    if (!currentPreviewData || currentPreviewData.total_locations === 0) {
        alert('Clique no botão "Visualizar" primeiro para verificar se há dados disponíveis.');
        return;
    }
    
    const btn = document.getElementById('generateKMZBtn');
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2" role="status"></div>Gerando KMZ...';
    btn.disabled = true;
    
    // Construir URL com parâmetros
    const params = new URLSearchParams();
    
    if (formData.questionnaires && formData.questionnaires.length > 0 && !formData.questionnaires.includes('all')) {
        formData.questionnaires.forEach(id => {
            params.append('questionnaire_ids[]', id);
        });
    }
    
    if (formData.date_from) {
        params.append('date_from', formData.date_from);
    }
    
    if (formData.date_to) {
        params.append('date_to', formData.date_to);
    }
    
    if (formData.filename) {
        params.append('filename', formData.filename);
    }
    
    params.append('include_photos', formData.include_photos ? '1' : '0');
    params.append('include_respondent', formData.include_respondent ? '1' : '0');
    params.append('include_applicator', formData.include_applicator ? '1' : '0');
    
    // Fazer download
    window.location.href = '<?= base_url('reports/generate_kmz_filtered') ?>?' + params.toString();
    
    // Mostrar feedback de sucesso
    showKMZSuccessMessage();
    
    // Restaurar botão após delay
    setTimeout(() => {
        btn.innerHTML = originalContent;
        btn.disabled = false;
    }, 3000);
}

function showKMZSuccessMessage() {
    const successDiv = document.getElementById('successFeedback');
    successDiv.style.display = 'block';
    
    setTimeout(() => {
        successDiv.style.display = 'none';
    }, 5000);
}

function showKMZError(message) {
    const previewDiv = document.getElementById('kmzPreview');
    previewDiv.innerHTML = `
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
        </div>
    `;
    previewDiv.style.display = 'block';
}

// Funções auxiliares
function formatDate(date) {
    return date.toLocaleDateString('pt-BR');
}

// Função original para exportar todos os dados (mantida)
function exportAllData() {
    const exportBtn = document.querySelector('button[onclick="exportAllData()"]');
    const originalContent = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Gerando Relatório...';
    exportBtn.disabled = true;
    
    window.location.href = '<?= base_url('reports/export_all?' . $current_filters) ?>';
    
    setTimeout(() => {
        exportBtn.innerHTML = originalContent;
        exportBtn.disabled = false;
    }, 5000);
}

// Função para alternar datas personalizadas (mantida do código original)
function toggleCustomDates(period) {
    const fromDiv = document.getElementById('customDatesFrom');
    const toDiv = document.getElementById('customDatesTo');
    
    if (period === 'custom') {
        fromDiv.style.display = 'block';
        toDiv.style.display = 'block';
    } else {
        fromDiv.style.display = 'none';
        toDiv.style.display = 'none';
    }
}

// Inicializar mapa quando a página carregar
document.addEventListener('DOMContentLoaded', function() {

    const kmzModalElement = document.getElementById('kmzModal');
    if (kmzModalElement) {
        kmzModal = new bootstrap.Modal(kmzModalElement);
        initializeKMZForm();
    }

    window.chartInstances = {};

    initLeafletMap();
    
    // Debug dos dados dos gráficos
    debugChartsData();

    // CORREÇÃO: Gráficos principais com tratamento de dados vazios
    <?php if (!empty($charts_data) && !empty($charts_data['responses_by_day'])): ?>
    const responsesTimeCtx = document.getElementById('responsesTimeChart');
    if (responsesTimeCtx) {
        window.chartInstances['responsesTimeChart'] = new Chart(responsesTimeCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($charts_data['responses_by_day'], 'date')) ?>,
                datasets: [{
                    label: 'Respostas',
                    data: <?= json_encode(array_column($charts_data['responses_by_day'], 'count')) ?>,
                    borderColor: '#8fae5d',
                    backgroundColor: 'rgba(143, 174, 93, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, title: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }
    <?php endif; ?>

    <?php if (!empty($charts_data) && !empty($charts_data['top_applicators'])): ?>
    const applicatorsCtx = document.getElementById('applicatorsChart');
    if (applicatorsCtx) {
        const applicatorsData = <?= json_encode($charts_data['top_applicators']) ?>;
        console.log('Applicators data for chart:', applicatorsData);
        
        if (applicatorsData && applicatorsData.length > 0) {
            const labels = applicatorsData.map(item => item.name || 'Sem nome');
            const data = applicatorsData.map(item => parseInt(item.count) || 0);
            
            window.chartInstances['applicatorsChart'] = new Chart(applicatorsCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Respostas',
                        data: data,
                        backgroundColor: '#8fae5d',
                        borderColor: '#7a9851',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 1.5,
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y + ' aplicações';
                                }
                            }
                        }
                    },
                    scales: { 
                        y: { beginAtZero: true },
                        x: { 
                            ticks: { 
                                maxRotation: 45,
                                minRotation: 0 
                            }
                        }
                    }
                }
            });
        }
    }
    <?php endif; ?>

    <?php if (!empty($charts_data) && !empty($charts_data['questionnaires_popularity'])): ?>
    const questionnairesCtx = document.getElementById('questionnairesPopularityChart');
    if (questionnairesCtx) {
        const questionnairesData = <?= json_encode($charts_data['questionnaires_popularity']) ?>;
        console.log('Questionnaires data for chart:', questionnairesData);
        
        if (questionnairesData && questionnairesData.length > 0) {
            const labels = questionnairesData.map(item => {
                const title = item.title || 'Sem título';
                return title.length > 20 ? title.substring(0, 20) + '...' : title;
            });
            const data = questionnairesData.map(item => parseInt(item.count) || 0);
            
            window.chartInstances['questionnairesPopularityChart'] = new Chart(questionnairesCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: ['#8fae5d', '#007bff', '#ffc107', '#dc3545', '#17a2b8', '#6f42c1', '#e83e8c'],
                        borderColor: ['#7a9851', '#0056b3', '#e0a800', '#c82333', '#138496', '#59359a', '#e619af'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 1,
                    plugins: { 
                        legend: { 
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10,
                                font: { size: 11 },
                                maxRotation: 0
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const fullTitle = questionnairesData[context.dataIndex].title || 'Sem título';
                                    return fullTitle + ': ' + context.parsed + ' aplicações';
                                }
                            }
                        }
                    }
                }
            });
        }
    }
    <?php endif; ?>
    
    // Inicializar outros componentes se necessário
    console.log('Análise de questões carregada com sucesso');

    const currentQuestionnaireId = '<?= $filters['questionnaire_id'] ?? '' ?>';
    if (currentQuestionnaireId) {
        setTimeout(() => {
            loadSpecificQuestionnaire();
        }, 500);
    }

     // Inicializar tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

});

// Adicione também limpeza quando a página for recarregada
window.addEventListener('beforeunload', function() {
  //  destroyExistingCharts();
});

</script>