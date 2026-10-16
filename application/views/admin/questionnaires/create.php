<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Criar Questionário</h2>
                <?php if (isset($preselected_project_id) && $preselected_project_id): ?>
                    <?php 
                    // Buscar nome do projeto selecionado
                    $selected_project = null;
                    foreach ($projects as $proj) {
                        if ($proj->id == $preselected_project_id) {
                            $selected_project = $proj;
                            break;
                        }
                    }
                    ?>
                    <?php if ($selected_project): ?>
                        <small class="text-muted d-block">
                            <i class="fas fa-project-diagram me-1"></i>
                            Vinculando ao projeto: <strong><?= $selected_project->name ?></strong>
                        </small>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <a href="<?= base_url('questionnaires') ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>
                Voltar
            </a>
        </div>
    </div>
</div>

<!-- CSS para Lógica Condicional -->
<style>
    .question-item {
        transition: all 0.3s ease;
    }
    
    .question-item.has-conditional {
        border-left: 4px solid #007bff;
    }
    
    .conditional-rules {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 15px;
        margin-top: 10px;
        display: none;
    }
    
    .condition-item {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        padding: 10px;
        margin-bottom: 8px;
    }
    
    .logic-type-selector {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .logic-type-btn {
        flex: 1;
        text-align: center;
        padding: 8px;
        border: 2px solid #dee2e6;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .logic-type-btn.active {
        border-color: #007bff;
        background: #e7f3ff;
    }
    
    .logic-type-btn:hover {
        border-color: #007bff;
    }
    
    .operator-selector {
        background: #e9ecef;
        padding: 4px 8px;
        border-radius: 4px;
        margin: 5px 0;
    }
    
    .logic-preview {
        background: #e7f3ff;
        border: 1px solid #b3d7ff;
        border-radius: 4px;
        padding: 8px;
        font-size: 0.9em;
        margin-top: 10px;
    }
    
    .question-reference {
        background: #fff3cd;
        padding: 2px 6px;
        border-radius: 3px;
        font-weight: bold;
    }
    
    .validation-errors {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
        padding: 8px;
        border-radius: 4px;
        margin-top: 5px;
    }
</style>

<?= form_open('questionnaires/create', ['id' => 'questionnaireForm']) ?>
<div class="row">
    <div class="col-lg-8">
        <!-- Informações Básicas -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Informações Básicas</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="title" class="form-label">Título *</label>
                    <input type="text" class="form-control" id="title" name="title" 
                           value="<?= set_value('title') ?>" required maxlength="200">
                    <?= form_error('title', '<small class="text-danger">', '</small>') ?>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Descrição</label>
                    <textarea class="form-control" id="description" name="description" 
                              rows="3" maxlength="1000"><?= set_value('description') ?></textarea>
                    <?= form_error('description', '<small class="text-danger">', '</small>') ?>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <label for="estimated_time" class="form-label">Tempo Estimado (minutos)</label>
                        <input type="number" class="form-control" id="estimated_time" name="estimated_time" 
                               value="<?= set_value('estimated_time') ?>" min="1" max="120">
                    </div>
                    <div class="col-md-6">
                        <!-- Select de Projeto -->
                        <label for="project_id" class="form-label">Projeto</label>
                        <select class="form-select" id="project_id" name="project_id">
                            <option value="">Selecione um projeto (opcional)</option>
                            <?php foreach ($projects as $project): ?>
                            <option value="<?= $project->id ?>" 
                                    <?= set_select('project_id', $project->id, (isset($preselected_project_id) && $preselected_project_id == $project->id)) ?>>
                                <?= $project->name ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Vincule este questionário a um projeto específico para melhor organização
                        </small>
                    </div>
                </div>

                <!-- Select de Aplicadores -->
                <div class="mb-3 mt-3">
                    <label for="aplicadores" class="form-label">Aplicadores Permitidos *</label>
                    <select class="form-select" id="aplicadores" name="aplicadores[]" multiple size="6" required>
                        <option value="all">🌟 Todos os Aplicadores</option>
                        <option value="none">🚫 Nenhum Aplicador (ocultar do app)</option>
                        <?php foreach ($aplicadores as $aplicador): ?>
                        <option value="<?= $aplicador->id ?>" <?= set_select('aplicadores[]', $aplicador->id) ?>>
                            <?= $aplicador->full_name ?> (<?= $aplicador->username ?>)<?= $aplicador->role !== 'aplicador' ? ' — ' . ucfirst($aplicador->role) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Segure Ctrl (Windows) ou Cmd (Mac) para selecionar múltiplos aplicadores. 
                        Selecione "Todos os Aplicadores" para permitir que qualquer aplicador use este questionário.
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Perguntas -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Perguntas</h5>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-info me-2" onclick="previewLogic()">
                        <i class="fas fa-eye me-1"></i>
                        Visualizar Lógica
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addQuestion()">
                        <i class="fas fa-plus me-1"></i>
                        Adicionar Pergunta
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Validação Global -->
                <div id="globalValidation" class="validation-errors" style="display: none;"></div>
                
                <div id="questionsContainer">
                    <!-- Perguntas serão adicionadas aqui via JavaScript -->
                </div>
                
                <div id="noQuestions" class="text-center py-4">
                    <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Nenhuma pergunta adicionada ainda.</p>
                    <button type="button" class="btn btn-primary" onclick="addQuestion()">
                        Adicionar Primeira Pergunta
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Informações do Projeto Selecionado -->
        <div class="card mb-4" id="projectInfoCard" style="display: none;">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-project-diagram me-2"></i>
                    Projeto Selecionado
                </h5>
            </div>
            <div class="card-body" id="projectInfo">
                <!-- Informações do projeto serão carregadas aqui -->
            </div>
        </div>
        
        <!-- Configurações -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Configurações</h5>
            </div>
            <div class="card-body">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="requires_consent" 
                           name="requires_consent" value="1" <?= set_checkbox('requires_consent', '1') ?>>
                    <label class="form-check-label" for="requires_consent">
                        <strong>Requer Consentimento</strong>
                        <br><small class="text-muted">Exibir termo de consentimento antes do questionário</small>
                    </label>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="requires_location" 
                           name="requires_location" value="1" <?= set_checkbox('requires_location', '1') ?>>
                    <label class="form-check-label" for="requires_location">
                        <strong>Capturar Localização</strong>
                        <br><small class="text-muted">Registrar coordenadas GPS automaticamente</small>
                    </label>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="requires_photo" 
                           name="requires_photo" value="1" <?= set_checkbox('requires_photo', '1') ?>>
                    <label class="form-check-label" for="requires_photo">
                        <strong>Requer Foto</strong>
                        <br><small class="text-muted">Solicitar foto como evidência</small>
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Ações -->
        <div class="card">
            <div class="card-body">
                <button type="submit" class="btn btn-primary w-100 mb-2">
                    <i class="fas fa-save me-2"></i>
                    Salvar Questionário
                </button>
                <a href="<?= base_url('questionnaires') ?>" class="btn btn-outline-secondary w-100">
                    Cancelar
                </a>
            </div>
        </div>
    </div>
</div>
<?= form_close() ?>

<!-- Modal para Visualização da Lógica -->
<div class="modal fade" id="logicPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-sitemap me-2"></i>
                    Visualização da Lógica Condicional
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="logicPreviewContent">
                    <!-- Conteúdo será gerado dinamicamente -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let questionIndex = 0;
let nextQuestionId = 1; // Contador para IDs temporários de novas questões

// Dados dos projetos para JavaScript
const projectsData = <?= json_encode($projects) ?>;

// Tipos de operadores para lógica condicional
const logicOperators = {
    'equals': 'Igual a',
    'not_equals': 'Diferente de',
    'contains': 'Contém',
    'not_contains': 'Não contém',
    'greater_than': 'Maior que',
    'less_than': 'Menor que',
    'is_empty': 'Está vazio',
    'is_not_empty': 'Não está vazio'
};

// Gerenciamento inicial
document.addEventListener('DOMContentLoaded', function() {
    const aplicadoresSelect = document.getElementById('aplicadores');
    const projectSelect = document.getElementById('project_id');
    
    // Gerenciamento do select de aplicadores
    aplicadoresSelect.addEventListener('change', function() {
        const allOption = this.querySelector('option[value="all"]');
        const noneOption = this.querySelector('option[value="none"]');
        // "all" e "none" sao exclusivos entre si e com a selecao individual
        const otherOptions = Array.from(
            this.querySelectorAll('option:not([value="all"]):not([value="none"])')
        );

        if (noneOption.selected && this.dataset.lastNone !== 'true') {
            allOption.selected = false;
            otherOptions.forEach(option => option.selected = false);
        } else if (allOption.selected && this.dataset.lastAll !== 'true') {
            noneOption.selected = false;
            otherOptions.forEach(option => option.selected = false);
        } else {
            const hasSpecificSelection = otherOptions.some(option => option.selected);
            if (hasSpecificSelection) {
                allOption.selected = false;
                noneOption.selected = false;
            }
        }

        if (!Array.from(this.selectedOptions).length) {
            allOption.selected = true;
        }

        this.dataset.lastAll = allOption.selected ? 'true' : 'false';
        this.dataset.lastNone = noneOption.selected ? 'true' : 'false';
    });
    
    // Gerenciamento do select de projeto
    projectSelect.addEventListener('change', function() {
        updateProjectInfo(this.value);
    });
    
    // Selecionar "Todos" por padrão
    aplicadoresSelect.querySelector('option[value="all"]').selected = true;
    
    // Verificar se há projeto pré-selecionado
    if (projectSelect.value) {
        updateProjectInfo(projectSelect.value);
    }
});

// Função para atualizar informações do projeto
function updateProjectInfo(projectId) {
    const projectInfoCard = document.getElementById('projectInfoCard');
    const projectInfo = document.getElementById('projectInfo');
    
    if (!projectId) {
        projectInfoCard.style.display = 'none';
        return;
    }
    
    const project = projectsData.find(p => p.id == projectId);
    if (!project) {
        projectInfoCard.style.display = 'none';
        return;
    }
    
    projectInfo.innerHTML = `
        <h6 class="mb-2">${project.name}</h6>
        <p class="text-muted small mb-2">${project.description || 'Sem descrição'}</p>
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">ID: ${project.id}</small>
            <a href="<?= base_url('projects/view/') ?>${project.id}" 
               class="btn btn-sm btn-outline-info" target="_blank" title="Ver projeto">
                <i class="fas fa-external-link-alt"></i>
            </a>
        </div>
    `;
    
    projectInfoCard.style.display = 'block';
}

// Função para adicionar pergunta
function addQuestion() {
    const container = document.getElementById('questionsContainer');
    const noQuestions = document.getElementById('noQuestions');
    
    // Gerar ID temporário para nova pergunta
    const tempQuestionId = `temp_${nextQuestionId}`;
    nextQuestionId++;
    
    const questionHtml = `
        <div class="question-item border rounded p-3 mb-3" data-index="${questionIndex}" data-question-id="${tempQuestionId}">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h6 class="mb-0">Pergunta ${questionIndex + 1}</h6>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                            onclick="toggleConditionalLogic(${questionIndex})" title="Adicionar Lógica Condicional">
                        <i class="fas fa-project-diagram"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeQuestion(${questionIndex})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Texto da Pergunta *</label>
                <textarea class="form-control" name="questions[${questionIndex}][text]" 
                          rows="2" required onchange="updateLogicPreview()"></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Tipo de Pergunta *</label>
                    <select class="form-select" name="questions[${questionIndex}][type]" 
                            onchange="handleQuestionTypeChange(${questionIndex}, this.value)" required>
                        <option value="">Selecione...</option>
                        <option value="text">Texto Simples</option>
                        <option value="textarea">Texto Longo</option>
                        <option value="number">Número</option>
                        <option value="email">E-mail</option>
                        <option value="phone">Telefone</option>
                        <option value="date">Data</option>
                        <option value="datetime">Data e Hora</option>
                        <option value="radio">Múltipla Escolha (única)</option>
                        <option value="checkbox">Múltipla Escolha (múltipla)</option>
                        <option value="select">Lista Suspensa</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">&nbsp;</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" 
                               name="questions[${questionIndex}][required]" value="1">
                        <label class="form-check-label">
                            Pergunta obrigatória
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Opções de Resposta -->
            <div id="options-${questionIndex}" class="mt-3" style="display: none;">
                <label class="form-label">Opções de Resposta</label>
                <div id="optionsContainer-${questionIndex}">
                    <!-- Opções serão adicionadas aqui -->
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" 
                        onclick="addOption(${questionIndex})">
                    <i class="fas fa-plus me-1"></i>
                    Adicionar Opção
                </button>
            </div>
            
            <!-- Lógica Condicional -->
            <div id="conditionalLogic-${questionIndex}" class="conditional-rules">
                <h6 class="mb-3">
                    <i class="fas fa-project-diagram me-2"></i>
                    Lógica Condicional
                </h6>
                
                <!-- Seletor de Tipo de Lógica -->
                <div class="logic-type-selector">
                    <div class="logic-type-btn" onclick="selectLogicType(${questionIndex}, 'visibility')">
                        <i class="fas fa-eye me-1"></i>
                        <strong>Visibilidade</strong>
                        <small class="d-block text-muted">Mostrar/ocultar pergunta</small>
                    </div>
                    <div class="logic-type-btn" onclick="selectLogicType(${questionIndex}, 'required')">
                        <i class="fas fa-asterisk me-1"></i>
                        <strong>Obrigatoriedade</strong>
                        <small class="d-block text-muted">Tornar obrigatória</small>
                    </div>
                </div>
                
                <!-- Regras de Visibilidade -->
                <div id="visibilityRules-${questionIndex}" class="logic-rules" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Mostrar esta pergunta quando:</strong>
                        <button type="button" class="btn btn-xs btn-outline-primary" 
                                onclick="addCondition(${questionIndex}, 'visibility')">
                            <i class="fas fa-plus"></i> Condição
                        </button>
                    </div>
                    
                    <div class="operator-selector mb-2">
                        <select class="form-select form-select-sm" name="questions[${questionIndex}][logic][visibility][operator]">
                            <option value="AND">Todas as condições (E)</option>
                            <option value="OR">Qualquer condição (OU)</option>
                        </select>
                    </div>
                    
                    <div id="visibilityConditions-${questionIndex}">
                        <!-- Condições serão adicionadas aqui -->
                    </div>
                </div>
                
                <!-- Regras de Obrigatoriedade -->
                <div id="requiredRules-${questionIndex}" class="logic-rules" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Tornar obrigatória quando:</strong>
                        <button type="button" class="btn btn-xs btn-outline-primary" 
                                onclick="addCondition(${questionIndex}, 'required')">
                            <i class="fas fa-plus"></i> Condição
                        </button>
                    </div>
                    
                    <div class="operator-selector mb-2">
                        <select class="form-select form-select-sm" name="questions[${questionIndex}][logic][required][operator]">
                            <option value="AND">Todas as condições (E)</option>
                            <option value="OR">Qualquer condição (OU)</option>
                        </select>
                    </div>
                    
                    <div id="requiredConditions-${questionIndex}">
                        <!-- Condições serão adicionadas aqui -->
                    </div>
                </div>
                
                <!-- Preview da Lógica -->
                <div id="logicPreview-${questionIndex}" class="logic-preview" style="display: none;">
                    <!-- Preview será gerado aqui -->
                </div>
                
                <div class="text-end mt-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                            onclick="clearConditionalLogic(${questionIndex})">
                        Limpar Lógica
                    </button>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', questionHtml);
    noQuestions.style.display = 'none';
    questionIndex++;
    updateQuestionNumbers();
}

// Função para remover pergunta
function removeQuestion(index) {
    if (confirm('Tem certeza que deseja remover esta pergunta? Isso pode afetar as regras condicionais de outras perguntas.')) {
        const questionItem = document.querySelector(`[data-index="${index}"]`);
        questionItem.remove();
        
        const remainingQuestions = document.querySelectorAll('.question-item');
        if (remainingQuestions.length === 0) {
            document.getElementById('noQuestions').style.display = 'block';
        }
        updateQuestionNumbers();
        validateAllConditionalLogic();
    }
}

// Função para lidar com mudança de tipo de pergunta
function handleQuestionTypeChange(questionIndex, type) {
    const optionsDiv = document.getElementById(`options-${questionIndex}`);
    
    if (['radio', 'checkbox', 'select'].includes(type)) {
        optionsDiv.style.display = 'block';
        const optionsContainer = document.getElementById(`optionsContainer-${questionIndex}`);
        if (optionsContainer.children.length === 0) {
            addOption(questionIndex);
            addOption(questionIndex);
        }
        
        // Adicionar required aos campos de opção quando visíveis
        const optionInputs = optionsContainer.querySelectorAll('input[name*="[text]"]');
        optionInputs.forEach(input => {
            input.setAttribute('required', 'required');
        });
    } else {
        optionsDiv.style.display = 'none';
        
        // Remover required dos campos de opção quando ocultos
        const optionInputs = optionsDiv.querySelectorAll('input[name*="[text]"]');
        optionInputs.forEach(input => {
            input.removeAttribute('required');
        });
    }
    
    updateLogicPreview();
}

// Função para adicionar opção
function addOption(questionIndex) {
    const container = document.getElementById(`optionsContainer-${questionIndex}`);
    const optionIndex = container.children.length;
    
    const optionHtml = `
        <div class="input-group mb-2">
            <input type="text" class="form-control" 
                   name="questions[${questionIndex}][options][${optionIndex}][text]" 
                   placeholder="Texto da opção" onchange="updateLogicPreview()">
            <input type="hidden" 
                   name="questions[${questionIndex}][options][${optionIndex}][value]" 
                   value="">
            <button type="button" class="btn btn-outline-danger" 
                    onclick="removeOption(this, ${questionIndex})">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', optionHtml);
}

// Função para remover opção
function removeOption(button, questionIndex) {
    const optionDiv = button.parentElement;
    const container = optionDiv.parentElement;
    
    optionDiv.remove();
    
    // Verificar se ainda há opções suficientes
    const remainingOptions = container.children.length;
    const questionItem = document.querySelector(`[data-index="${questionIndex}"]`);
    const typeSelect = questionItem.querySelector('select[name*="[type]"]');
    
    if (remainingOptions < 2 && ['radio', 'checkbox', 'select'].includes(typeSelect.value)) {
        // Se tem menos de 2 opções, adicionar uma nova
        addOption(questionIndex);
    }
    
    updateLogicPreview();
}

// Função para atualizar numeração das perguntas
function updateQuestionNumbers() {
    const questions = document.querySelectorAll('.question-item');
    questions.forEach((question, index) => {
        const title = question.querySelector('h6');
        title.textContent = `Pergunta ${index + 1}`;
        
        // Atualizar referências nos selects de condições
        updateConditionQuestionOptions(question, index);
    });
}

// Função para mostrar/ocultar lógica condicional
function toggleConditionalLogic(questionIndex) {
    const logicDiv = document.getElementById(`conditionalLogic-${questionIndex}`);
    const questionItem = document.querySelector(`[data-index="${questionIndex}"]`);
    
    if (logicDiv.style.display === 'none' || logicDiv.style.display === '') {
        logicDiv.style.display = 'block';
        questionItem.classList.add('has-conditional');
    } else {
        logicDiv.style.display = 'none';
        questionItem.classList.remove('has-conditional');
    }
}

// Função para selecionar tipo de lógica
function selectLogicType(questionIndex, type) {
    const visibilityBtn = document.querySelector(`[data-index="${questionIndex}"] .logic-type-btn:first-child`);
    const requiredBtn = document.querySelector(`[data-index="${questionIndex}"] .logic-type-btn:last-child`);
    const visibilityRules = document.getElementById(`visibilityRules-${questionIndex}`);
    const requiredRules = document.getElementById(`requiredRules-${questionIndex}`);
    
    // Reset active states
    visibilityBtn.classList.remove('active');
    requiredBtn.classList.remove('active');
    visibilityRules.style.display = 'none';
    requiredRules.style.display = 'none';
    
    if (type === 'visibility') {
        visibilityBtn.classList.add('active');
        visibilityRules.style.display = 'block';
    } else if (type === 'required') {
        requiredBtn.classList.add('active');
        requiredRules.style.display = 'block';
    }
    
    updateLogicPreview();
}

// Função para adicionar condição
function addCondition(questionIndex, ruleType) {
    const container = document.getElementById(`${ruleType}Conditions-${questionIndex}`);
    const conditionIndex = container.children.length;
    
    const availableQuestions = getAvailableQuestionsForCondition(questionIndex);
    
    if (availableQuestions.length === 0) {
        alert('Não há perguntas anteriores disponíveis para criar condições.');
        return;
    }
    
    const conditionHtml = `
        <div class="condition-item">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <select class="form-select form-select-sm" 
                            name="questions[${questionIndex}][logic][${ruleType}][conditions][${conditionIndex}][question]"
                            onchange="updateConditionOperators(${questionIndex}, '${ruleType}', ${conditionIndex})">
                        <option value="">Pergunta...</option>
                        ${availableQuestions.map(q => `<option value="${q.id}">${q.title}</option>`).join('')}
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" 
                            name="questions[${questionIndex}][logic][${ruleType}][conditions][${conditionIndex}][operator]"
                            onchange="updateConditionValue(${questionIndex}, '${ruleType}', ${conditionIndex})">
                        <option value="">Operador...</option>
                        ${Object.entries(logicOperators).map(([key, value]) => `<option value="${key}">${value}</option>`).join('')}
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control form-control-sm" 
                           name="questions[${questionIndex}][logic][${ruleType}][conditions][${conditionIndex}][value]"
                           placeholder="Valor..." onchange="updateLogicPreview()">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-sm btn-outline-danger w-100" 
                            onclick="removeCondition(this, ${questionIndex})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', conditionHtml);
    updateLogicPreview();
}

// Função para remover condição
function removeCondition(button, questionIndex) {
    button.closest('.condition-item').remove();
    updateLogicPreview();
}

// Função para obter perguntas disponíveis para condição
function getAvailableQuestionsForCondition(currentQuestionIndex) {
    const questions = document.querySelectorAll('.question-item');
    const available = [];
    
    questions.forEach((question, index) => {
        if (index < currentQuestionIndex) {
            const textArea = question.querySelector('textarea[name*="[text]"]');
            const questionId = question.getAttribute('data-question-id');
            const title = textArea ? textArea.value.substring(0, 50) + '...' : `Pergunta ${index + 1}`;
            
            available.push({
                id: questionId, // Usar ID ao invés de índice
                index: index,
                title: title || `Pergunta ${index + 1}`
            });
        }
    });
    
    return available;
}

// Função para atualizar operadores da condição (CORRIGIDA)
function updateConditionOperators(questionIndex, ruleType, conditionIndex) {
    const questionSelect = document.querySelector(`select[name="questions[${questionIndex}][logic][${ruleType}][conditions][${conditionIndex}][question]"]`);
    const operatorSelect = document.querySelector(`select[name="questions[${questionIndex}][logic][${ruleType}][conditions][${conditionIndex}][operator]"]`);
    const valueInput = document.querySelector(`input[name="questions[${questionIndex}][logic][${ruleType}][conditions][${conditionIndex}][value]"]`);
    
    const selectedQuestionId = questionSelect.value;
    if (!selectedQuestionId) return;
    
    let targetQuestion = null;
    
    // Buscar pergunta alvo usando ID
    const targetQuestionElement = document.querySelector(`[data-question-id="${selectedQuestionId}"]`);
    if (targetQuestionElement) {
        const typeSelect = targetQuestionElement.querySelector('select[name*="[type]"]');
        targetQuestion = {
            question_type: typeSelect ? typeSelect.value : 'text'
        };
    }
    
    // Se não encontrou a pergunta, usar tipo padrão
    if (!targetQuestion) {
        targetQuestion = { question_type: 'text' };
    }
    
    // Limpar operadores atuais
    operatorSelect.innerHTML = '<option value="">Operador...</option>';
    
    // Adicionar operadores baseados no tipo de pergunta
    let availableOperators = [];
    
    switch (targetQuestion.question_type) {
        case 'number':
            availableOperators = ['equals', 'not_equals', 'greater_than', 'less_than', 'is_empty', 'is_not_empty'];
            break;
        case 'radio':
        case 'select':
            availableOperators = ['equals', 'not_equals', 'is_empty', 'is_not_empty'];
            break;
        case 'checkbox':
            availableOperators = ['contains', 'not_contains', 'is_empty', 'is_not_empty'];
            break;
        default:
            availableOperators = ['equals', 'not_equals', 'contains', 'not_contains', 'is_empty', 'is_not_empty'];
    }
    
    availableOperators.forEach(op => {
        const option = document.createElement('option');
        option.value = op;
        option.textContent = logicOperators[op];
        operatorSelect.appendChild(option);
    });
    
    updateConditionValue(questionIndex, ruleType, conditionIndex);
}

// Função para atualizar campo de valor da condição (CORRIGIDA)
function updateConditionValue(questionIndex, ruleType, conditionIndex) {
    const questionSelect = document.querySelector(`select[name="questions[${questionIndex}][logic][${ruleType}][conditions][${conditionIndex}][question]"]`);
    const operatorSelect = document.querySelector(`select[name="questions[${questionIndex}][logic][${ruleType}][conditions][${conditionIndex}][operator]"]`);
    const valueInput = document.querySelector(`input[name="questions[${questionIndex}][logic][${ruleType}][conditions][${conditionIndex}][value]"]`);
    
    const selectedQuestionId = questionSelect.value;
    const selectedOperator = operatorSelect.value;
    
    if (!selectedQuestionId || !selectedOperator) return;
    
    // Se operador é "is_empty" ou "is_not_empty", esconder campo de valor
    if (['is_empty', 'is_not_empty'].includes(selectedOperator)) {
        valueInput.style.display = 'none';
        valueInput.value = '';
        return;
    } else {
        valueInput.style.display = 'block';
    }
    
    // Buscar pergunta alvo usando ID
    const targetQuestion = document.querySelector(`[data-question-id="${selectedQuestionId}"]`);
    if (!targetQuestion) return;
    
    const questionType = targetQuestion.querySelector('select[name*="[type]"]').value;
    
    // Se é pergunta de múltipla escolha, converter para select
    if (['radio', 'checkbox', 'select'].includes(questionType)) {
        const options = targetQuestion.querySelectorAll('input[name*="[options]"][name*="[text]"]');
        
        if (options.length > 0) {
            // Criar select com as opções
            const select = document.createElement('select');
            select.className = 'form-select form-select-sm';
            select.name = valueInput.name;
            select.onchange = () => updateLogicPreview();
            
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Selecione...';
            select.appendChild(defaultOption);
            
            options.forEach(option => {
                if (option.value.trim()) {
                    const optionElement = document.createElement('option');
                    optionElement.value = option.value.trim();
                    optionElement.textContent = option.value.trim();
                    select.appendChild(optionElement);
                }
            });
            
            valueInput.parentNode.replaceChild(select, valueInput);
        }
    }
    
    updateLogicPreview();
}

// Função para atualizar opções de pergunta nas condições
function updateConditionQuestionOptions(questionElement, newIndex) {
    const conditionSelects = questionElement.querySelectorAll('select[name*="[question]"]');
    
    conditionSelects.forEach(select => {
        // Remover opções que referenciam perguntas posteriores ou a própria pergunta
        const currentQuestionId = questionElement.getAttribute('data-question-id');
        
        Array.from(select.options).forEach(option => {
            if (option.value) {
                // Encontrar o elemento da pergunta referenciada
                const referencedQuestion = document.querySelector(`[data-question-id="${option.value}"]`);
                if (referencedQuestion) {
                    const referencedIndex = parseInt(referencedQuestion.getAttribute('data-index'));
                    if (referencedIndex >= newIndex) {
                        option.remove();
                    }
                }
            }
        });
    });
}

// Função para limpar lógica condicional
function clearConditionalLogic(questionIndex) {
    if (confirm('Tem certeza que deseja limpar toda a lógica condicional desta pergunta?')) {
        const visibilityConditions = document.getElementById(`visibilityConditions-${questionIndex}`);
        const requiredConditions = document.getElementById(`requiredConditions-${questionIndex}`);
        const logicDiv = document.getElementById(`conditionalLogic-${questionIndex}`);
        const questionItem = document.querySelector(`[data-index="${questionIndex}"]`);
        
        visibilityConditions.innerHTML = '';
        requiredConditions.innerHTML = '';
        logicDiv.style.display = 'none';
        questionItem.classList.remove('has-conditional');
        
        updateLogicPreview();
    }
}

// Função para atualizar preview da lógica
function updateLogicPreview() {
    const questions = document.querySelectorAll('.question-item');
    
    questions.forEach((question, index) => {
        const previewDiv = question.querySelector(`#logicPreview-${index}`);
        if (!previewDiv) return;
        
        const visibilityConditions = question.querySelectorAll('#visibilityConditions-' + index + ' .condition-item');
        const requiredConditions = question.querySelectorAll('#requiredConditions-' + index + ' .condition-item');
        
        let previewText = '';
        
        if (visibilityConditions.length > 0) {
            previewText += '<strong>Visibilidade:</strong> ';
            previewText += generateConditionsPreview(visibilityConditions, index, 'visibility');
            previewText += '<br>';
        }
        
        if (requiredConditions.length > 0) {
            previewText += '<strong>Obrigatoriedade:</strong> ';
            previewText += generateConditionsPreview(requiredConditions, index, 'required');
        }
        
        if (previewText) {
            previewDiv.innerHTML = previewText;
            previewDiv.style.display = 'block';
        } else {
            previewDiv.style.display = 'none';
        }
    });
}

// Função para gerar preview das condições
function generateConditionsPreview(conditions, questionIndex, ruleType) {
    if (conditions.length === 0) return '';
    
    const operatorSelect = document.querySelector(`select[name="questions[${questionIndex}][logic][${ruleType}][operator]"]`);
    const operator = operatorSelect ? operatorSelect.value : 'AND';
    
    const conditionTexts = [];
    
    conditions.forEach(condition => {
        const questionSelect = condition.querySelector('select[name*="[question]"]');
        const operatorSelect = condition.querySelector('select[name*="[operator]"]');
        const valueField = condition.querySelector('input[name*="[value]"], select[name*="[value]"]');
        
        if (questionSelect && operatorSelect) {
            const questionText = questionSelect.selectedOptions[0]?.text || 'Pergunta';
            const operatorText = logicOperators[operatorSelect.value] || 'operador';
            const valueText = valueField ? valueField.value : '';
            
            let conditionText = `<span class="question-reference">${questionText}</span> ${operatorText}`;
            
            if (valueText && !['is_empty', 'is_not_empty'].includes(operatorSelect.value)) {
                conditionText += ` "${valueText}"`;
            }
            
            conditionTexts.push(conditionText);
        }
    });
    
    if (conditionTexts.length === 0) return '';
    
    const operatorText = operator === 'AND' ? ' E ' : ' OU ';
    return conditionTexts.join(operatorText);
}

// Função para validar toda a lógica condicional
function validateAllConditionalLogic() {
    const questions = document.querySelectorAll('.question-item');
    const errors = [];
    const warnings = [];
    
    questions.forEach((question, index) => {
        const validation = validateQuestionConditionalLogic(question, index);
        errors.push(...validation.errors);
        warnings.push(...validation.warnings);
    });
    
    // Mostrar erros globais
    const globalValidation = document.getElementById('globalValidation');
    if (errors.length > 0 || warnings.length > 0) {
        let content = '';
        
        if (errors.length > 0) {
            content += '<strong>Erros:</strong><ul>';
            errors.forEach(error => {
                content += `<li>${error}</li>`;
            });
            content += '</ul>';
        }
        
        if (warnings.length > 0) {
            content += '<strong>Avisos:</strong><ul>';
            warnings.forEach(warning => {
                content += `<li>${warning}</li>`;
            });
            content += '</ul>';
        }
        
        globalValidation.innerHTML = content;
        globalValidation.style.display = 'block';
    } else {
        globalValidation.style.display = 'none';
    }
    
    return { valid: errors.length === 0, errors, warnings };
}

// Função para validar lógica condicional de uma pergunta
function validateQuestionConditionalLogic(question, questionIndex) {
    const errors = [];
    const warnings = [];
    
    const visibilityConditions = question.querySelectorAll('#visibilityConditions-' + questionIndex + ' .condition-item');
    const requiredConditions = question.querySelectorAll('#requiredConditions-' + questionIndex + ' .condition-item');
    
    // Obter todas as questões existentes para validação
    const allQuestions = document.querySelectorAll('.question-item');
    const questionIds = [];
    const questionIndices = {};
    
    allQuestions.forEach((q, idx) => {
        const questionId = q.getAttribute('data-question-id');
        questionIds.push(questionId);
        questionIndices[questionId] = idx;
    });
    
    [...visibilityConditions, ...requiredConditions].forEach((condition, condIndex) => {
        const questionSelect = condition.querySelector('select[name*="[question]"]');
        const operatorSelect = condition.querySelector('select[name*="[operator]"]');
        const valueField = condition.querySelector('input[name*="[value]"], select[name*="[value]"]');
        
        if (!questionSelect.value) {
            errors.push(`Pergunta ${questionIndex + 1}: Condição ${condIndex + 1} sem pergunta selecionada`);
        }
        
        if (!operatorSelect.value) {
            errors.push(`Pergunta ${questionIndex + 1}: Condição ${condIndex + 1} sem operador selecionado`);
        }
        
        // Validar se a pergunta referenciada existe e não é posterior
        if (questionSelect.value) {
            const targetQuestionId = questionSelect.value;
            
            if (!questionIds.includes(targetQuestionId)) {
                errors.push(`Pergunta ${questionIndex + 1}: Referencia pergunta inexistente`);
            } else {
                const targetIndex = questionIndices[targetQuestionId];
                if (targetIndex >= questionIndex) {
                    errors.push(`Pergunta ${questionIndex + 1}: Não pode referenciar pergunta posterior ou a si mesma`);
                }
            }
        }
        
        if (operatorSelect.value && !['is_empty', 'is_not_empty'].includes(operatorSelect.value) && (!valueField || !valueField.value.trim())) {
            warnings.push(`Pergunta ${questionIndex + 1}: Condição ${condIndex + 1} sem valor definido`);
        }
    });
    
    return { errors, warnings };
}

// Função para visualizar lógica completa
function previewLogic() {
    const modal = new bootstrap.Modal(document.getElementById('logicPreviewModal'));
    const content = document.getElementById('logicPreviewContent');
    
    const questions = document.querySelectorAll('.question-item');
    let previewHtml = '';
    
    if (questions.length === 0) {
        previewHtml = '<p class="text-muted">Nenhuma pergunta criada ainda.</p>';
    } else {
        previewHtml = '<div class="row">';
        
        questions.forEach((question, index) => {
            const textArea = question.querySelector('textarea[name*="[text]"]');
            const typeSelect = question.querySelector('select[name*="[type]"]');
            const requiredCheckbox = question.querySelector('input[name*="[required]"]');
            
            const questionText = textArea ? textArea.value : `Pergunta ${index + 1}`;
            const questionType = typeSelect ? typeSelect.value : 'text';
            const isRequired = requiredCheckbox ? requiredCheckbox.checked : false;
            
            const visibilityConditions = question.querySelectorAll('#visibilityConditions-' + index + ' .condition-item');
            const requiredConditions = question.querySelectorAll('#requiredConditions-' + index + ' .condition-item');
            
            let cardClass = 'border-start border-3 ';
            if (visibilityConditions.length > 0 && requiredConditions.length > 0) {
                cardClass += 'border-warning';
            } else if (visibilityConditions.length > 0) {
                cardClass += 'border-info';
            } else if (requiredConditions.length > 0) {
                cardClass += 'border-primary';
            } else {
                cardClass += 'border-secondary';
            }
            
            previewHtml += `
                <div class="col-12 mb-3">
                    <div class="card ${cardClass}">
                        <div class="card-body">
                            <h6 class="card-title">
                                Pergunta ${index + 1}
                                ${isRequired ? '<i class="fas fa-asterisk text-danger ms-1" title="Obrigatória"></i>' : ''}
                            </h6>
                            <p class="card-text">${questionText || 'Texto não definido'}</p>
                            <small class="text-muted">Tipo: ${questionType || 'Não definido'}</small>
                            
                            ${visibilityConditions.length > 0 ? `
                                <div class="mt-2">
                                    <span class="badge bg-info">Lógica de Visibilidade</span>
                                    <div class="mt-1 small">
                                        ${generateConditionsPreview(visibilityConditions, index, 'visibility')}
                                    </div>
                                </div>
                            ` : ''}
                            
                            ${requiredConditions.length > 0 ? `
                                <div class="mt-2">
                                    <span class="badge bg-primary">Lógica de Obrigatoriedade</span>
                                    <div class="mt-1 small">
                                        ${generateConditionsPreview(requiredConditions, index, 'required')}
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        previewHtml += '</div>';
        
        // Adicionar resumo geral
        const totalWithLogic = Array.from(questions).filter(q => {
            const visibilityConditions = q.querySelectorAll('[id*="visibilityConditions"] .condition-item');
            const requiredConditions = q.querySelectorAll('[id*="requiredConditions"] .condition-item');
            return visibilityConditions.length > 0 || requiredConditions.length > 0;
        }).length;
        
        if (totalWithLogic > 0) {
            previewHtml = `
                <div class="alert alert-info mb-3">
                    <h6><i class="fas fa-info-circle me-2"></i>Resumo da Lógica Condicional</h6>
                    <p class="mb-0">
                        <strong>${totalWithLogic}</strong> de <strong>${questions.length}</strong> perguntas possuem lógica condicional definida.
                    </p>
                </div>
                ${previewHtml}
            `;
        }
    }
    
    content.innerHTML = previewHtml;
    modal.show();
}

// Função para limpar campos vazios antes do envio
function cleanEmptyFields() {
    const questions = document.querySelectorAll('.question-item');
    
    questions.forEach((question, questionIndex) => {
        const typeSelect = question.querySelector('select[name*="[type]"]');
        const type = typeSelect.value;
        
        // Se não é tipo de múltipla escolha, remover todos os campos de opção
        if (!['radio', 'checkbox', 'select'].includes(type)) {
            const optionsDiv = question.querySelector(`#options-${questionIndex}`);
            if (optionsDiv) {
                const optionInputs = optionsDiv.querySelectorAll('input');
                optionInputs.forEach(input => {
                    input.removeAttribute('name'); // Remove do envio
                    input.removeAttribute('required');
                });
            }
        } else {
            // Para tipos de múltipla escolha, remover opções vazias
            const optionsContainer = question.querySelector(`#optionsContainer-${questionIndex}`);
            if (optionsContainer) {
                const optionDivs = optionsContainer.querySelectorAll('.input-group');
                optionDivs.forEach(optionDiv => {
                    const textInput = optionDiv.querySelector('input[name*="[text]"]');
                    if (!textInput.value.trim()) {
                        // Remove opção vazia do envio
                        const inputs = optionDiv.querySelectorAll('input');
                        inputs.forEach(input => {
                            input.removeAttribute('name');
                            input.removeAttribute('required');
                        });
                    } else {
                        // Garantir que opções válidas tenham o value correto
                        const valueInput = optionDiv.querySelector('input[name*="[value]"]');
                        if (valueInput && !valueInput.value) {
                            valueInput.value = textInput.value.toLowerCase().replace(/\s+/g, '_');
                        }
                    }
                });
            }
        }
    });
}

// Função para serializar lógica condicional
function serializeConditionalLogic() {
    const questions = document.querySelectorAll('.question-item');
    
    questions.forEach((question, index) => {
        const logicData = {
            visibility: null,
            required: null
        };
        
        // Serializar condições de visibilidade
        const visibilityConditions = question.querySelectorAll('#visibilityConditions-' + index + ' .condition-item');
        if (visibilityConditions.length > 0) {
            const visibilityOperator = question.querySelector(`select[name="questions[${index}][logic][visibility][operator]"]`);
            
            logicData.visibility = {
                operator: visibilityOperator ? visibilityOperator.value : 'AND',
                conditions: []
            };
            
            visibilityConditions.forEach(condition => {
                const questionSelect = condition.querySelector('select[name*="[question]"]');
                const operatorSelect = condition.querySelector('select[name*="[operator]"]');
                const valueField = condition.querySelector('input[name*="[value]"], select[name*="[value]"]');
                
                if (questionSelect.value && operatorSelect.value) {
                    logicData.visibility.conditions.push({
                        question: questionSelect.value, // Mantém o ID temporário
                        operator: operatorSelect.value,
                        value: valueField ? valueField.value : ''
                    });
                }
            });
        }
        
        // Serializar condições de obrigatoriedade
        const requiredConditions = question.querySelectorAll('#requiredConditions-' + index + ' .condition-item');
        if (requiredConditions.length > 0) {
            const requiredOperator = question.querySelector(`select[name="questions[${index}][logic][required][operator]"]`);
            
            logicData.required = {
                operator: requiredOperator ? requiredOperator.value : 'AND',
                conditions: []
            };
            
            requiredConditions.forEach(condition => {
                const questionSelect = condition.querySelector('select[name*="[question]"]');
                const operatorSelect = condition.querySelector('select[name*="[operator]"]');
                const valueField = condition.querySelector('input[name*="[value]"], select[name*="[value]"]');
                
                if (questionSelect.value && operatorSelect.value) {
                    logicData.required.conditions.push({
                        question: questionSelect.value, // Mantém o ID temporário
                        operator: operatorSelect.value,
                        value: valueField ? valueField.value : ''
                    });
                }
            });
        }
        
        // Adicionar campo hidden com a lógica serializada
        if (logicData.visibility || logicData.required) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = `questions[${index}][conditional_logic]`;
            hiddenInput.value = JSON.stringify(logicData);
            question.appendChild(hiddenInput);
        }
        
        // NOVO: Adicionar ID temporário como campo hidden para mapeamento
        const questionId = question.getAttribute('data-question-id');
        if (questionId && questionId.startsWith('temp_')) {
            const tempIdInput = document.createElement('input');
            tempIdInput.type = 'hidden';
            tempIdInput.name = `questions[${index}][temp_id]`;
            tempIdInput.value = questionId;
            question.appendChild(tempIdInput);
        }
    });
}

// Validação do formulário (ATUALIZADA)
document.getElementById('questionnaireForm').addEventListener('submit', function(e) {
    const questions = document.querySelectorAll('.question-item');
    if (questions.length === 0) {
        e.preventDefault();
        alert('Adicione pelo menos uma pergunta ao questionário.');
        return false;
    }
    
    // Validar aplicadores
    const aplicadoresSelect = document.getElementById('aplicadores');
    if (!aplicadoresSelect.selectedOptions.length) {
        e.preventDefault();
        alert('Selecione pelo menos um aplicador para este questionário.');
        return false;
    }
    
    // Validar perguntas e remover required de campos ocultos
    let valid = true;
    questions.forEach((question, index) => {
        const typeSelect = question.querySelector('select[name*="[type]"]');
        const type = typeSelect.value;
        const textArea = question.querySelector('textarea[name*="[text]"]');
        
        // Validar se pergunta tem texto
        if (!textArea.value.trim()) {
            alert(`A pergunta ${index + 1} deve ter um texto.`);
            valid = false;
            return;
        }
        
        // Validar tipo de pergunta
        if (!type) {
            alert(`Selecione um tipo para a pergunta ${index + 1}.`);
            valid = false;
            return;
        }
        
        if (['radio', 'checkbox', 'select'].includes(type)) {
            const optionsDiv = question.querySelector(`#options-${index}`);
            const optionInputs = optionsDiv.querySelectorAll('input[name*="[text]"]');
            const validOptions = Array.from(optionInputs).filter(input => input.value.trim());
            
            if (validOptions.length < 2) {
                alert(`A pergunta ${index + 1} deve ter pelo menos 2 opções válidas.`);
                valid = false;
                return;
            }
            
            // Garantir que campos de opção visíveis tenham required
            optionInputs.forEach(input => {
                if (input.value.trim()) {
                    input.setAttribute('required', 'required');
                } else {
                    input.removeAttribute('required');
                }
            });
        } else {
            // Remover required de campos de opção para tipos que não precisam
            const optionsDiv = question.querySelector(`#options-${index}`);
            if (optionsDiv) {
                const optionInputs = optionsDiv.querySelectorAll('input[name*="[text]"]');
                optionInputs.forEach(input => {
                    input.removeAttribute('required');
                });
            }
        }
    });
    
    if (!valid) {
        e.preventDefault();
        return false;
    }
    
    // Validar lógica condicional
    const logicValidation = validateAllConditionalLogic();
    if (!logicValidation.valid) {
        e.preventDefault();
        alert('Existem erros na lógica condicional. Verifique as mensagens de erro e corrija-as antes de salvar.');
        return false;
    }
    
    // Limpar campos vazios antes do envio
    cleanEmptyFields();
    
    // Serializar lógica condicional para envio
    serializeConditionalLogic();
    
    console.log('Formulário validado e enviado com sucesso');
});

// Validação do formulário
document.getElementById('questionnaireForm').addEventListener('submit', function(e) {
    const questions = document.querySelectorAll('.question-item');
    if (questions.length === 0) {
        e.preventDefault();
        alert('Adicione pelo menos uma pergunta ao questionário.');
        return false;
    }
    
    // Validar aplicadores
    const aplicadoresSelect = document.getElementById('aplicadores');
    if (!aplicadoresSelect.selectedOptions.length) {
        e.preventDefault();
        alert('Selecione pelo menos um aplicador para este questionário.');
        return false;
    }
    
    // Validar perguntas e remover required de campos ocultos
    let valid = true;
    questions.forEach((question, index) => {
        const typeSelect = question.querySelector('select[name*="[type]"]');
        const type = typeSelect.value;
        const textArea = question.querySelector('textarea[name*="[text]"]');
        
        // Validar se pergunta tem texto
        if (!textArea.value.trim()) {
            alert(`A pergunta ${index + 1} deve ter um texto.`);
            valid = false;
            return;
        }
        
        // Validar tipo de pergunta
        if (!type) {
            alert(`Selecione um tipo para a pergunta ${index + 1}.`);
            valid = false;
            return;
        }
        
        if (['radio', 'checkbox', 'select'].includes(type)) {
            const optionsDiv = question.querySelector(`#options-${index}`);
            const optionInputs = optionsDiv.querySelectorAll('input[name*="[text]"]');
            const validOptions = Array.from(optionInputs).filter(input => input.value.trim());
            
            if (validOptions.length < 2) {
                alert(`A pergunta ${index + 1} deve ter pelo menos 2 opções válidas.`);
                valid = false;
                return;
            }
            
            // Garantir que campos de opção visíveis tenham required
            optionInputs.forEach(input => {
                if (input.value.trim()) {
                    input.setAttribute('required', 'required');
                } else {
                    input.removeAttribute('required');
                }
            });
        } else {
            // Remover required de campos de opção para tipos que não precisam
            const optionsDiv = question.querySelector(`#options-${index}`);
            if (optionsDiv) {
                const optionInputs = optionsDiv.querySelectorAll('input[name*="[text]"]');
                optionInputs.forEach(input => {
                    input.removeAttribute('required');
                });
            }
        }
    });
    
    if (!valid) {
        e.preventDefault();
        return false;
    }
    
    // Validar lógica condicional
    const logicValidation = validateAllConditionalLogic();
    if (!logicValidation.valid) {
        e.preventDefault();
        alert('Existem erros na lógica condicional. Verifique as mensagens de erro e corrija-as antes de salvar.');
        return false;
    }
    
    // Limpar campos vazios antes do envio
    cleanEmptyFields();
    
    // Serializar lógica condicional para envio
    serializeConditionalLogic();
});
</script>