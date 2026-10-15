<?php 

function get_location_name($latitude, $longitude)
{
    // Verificar se as coordenadas são válidas
    if (empty($latitude) || empty($longitude) || 
        !is_numeric($latitude) || !is_numeric($longitude)) {
        return 'N/A';
    }

    // Validar range das coordenadas
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        return 'N/A';
    }

    try {
        // Usando OpenStreetMap Nominatim (gratuito, sem necessidade de API key)
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}&zoom=18&addressdetails=1";
        
        // Configurar contexto da requisição
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: SXData-App/1.0',
                    'Accept: application/json'
                ],
                'timeout' => 5 // Reduzido para 5 segundos para não travar a página
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        
        if ($response === FALSE) {
            return 'N/A';
        }

        $data = json_decode($response, true);
        
        if (isset($data['display_name'])) {
            // Retornar o nome formatado da localização
            return format_location_name($data); // Removido $this->
        }

    } catch (Exception $e) {
        // Log do erro se necessário (CodeIgniter)
        if (function_exists('log_message')) {
            log_message('error', 'Erro na geocodificação: ' . $e->getMessage());
        }
    }

    return 'N/A';
}

function format_location_name($data)
{
    if (!isset($data['address'])) {
        return isset($data['display_name']) ? $data['display_name'] : 'N/A';
    }

    $address = $data['address'];
    $location_parts = [];

    // Priorizar informações mais específicas para o Brasil
    if (!empty($address['road'])) {
        $location_parts[] = $address['road'];
    }
    
    if (!empty($address['suburb']) || !empty($address['neighbourhood'])) {
        $location_parts[] = $address['suburb'] ?? $address['neighbourhood'];
    }
    
    if (!empty($address['city']) || !empty($address['town']) || !empty($address['village'])) {
        $location_parts[] = $address['city'] ?? $address['town'] ?? $address['village'];
    }
    
    if (!empty($address['state'])) {
        $location_parts[] = $address['state'];
    }

    // Limitar o tamanho da string retornada para não quebrar o layout
    $result = !empty($location_parts) ? implode(', ', $location_parts) : $data['display_name'];
    
    // Se muito longo, truncar e adicionar "..."
    if (strlen($result) > 60) {
        $result = substr($result, 0, 57) . '...';
    }
    
    return $result;
}

// Função alternativa mais rápida que usa cache simples
function get_location_name_cached($latitude, $longitude)
{
    // Criar uma chave de cache baseada nas coordenadas (arredondadas)
    $cache_key = 'location_' . round($latitude, 3) . '_' . round($longitude, 3);
    
    // Verificar se já temos o resultado em cache (usando CI cache se disponível)
    $CI =& get_instance();
    if (method_exists($CI, 'cache')) {
        $cached_result = $CI->cache->get($cache_key);
        if ($cached_result !== FALSE) {
            return $cached_result;
        }
    }
    
    // Se não estiver em cache, buscar
    $result = get_location_name($latitude, $longitude);
    
    // Salvar no cache por 1 hora
    if (method_exists($CI, 'cache') && $result !== 'N/A') {
        $CI->cache->save($cache_key, $result, 3600);
    }
    
    return $result;
}

?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Respostas dos Formulários</h2>
            <div>
                <button class="btn btn-outline-primary me-2" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                    <i class="fas fa-filter me-2"></i>
                    Filtros
                </button>
                <a href="<?= base_url('responses/export?' . http_build_query($filters)) ?>" class="btn btn-success me-2">
                    <i class="fas fa-download me-2"></i>
                    Exportar
                </a>
                <!-- NOVO BOTÃO: Exportar Dados Brutos -->
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportRawDataModal">
                    <i class="fas fa-file-excel me-2"></i>
                    Exportar Dados Brutos
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="collapse <?= !empty($filters) ? 'show' : '' ?>" id="filtersCollapse">
    <div class="card mb-4">
        <div class="card-body">
            <?= form_open('responses', ['method' => 'GET']) ?>
            <div class="row">
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
                    <label class="form-label">Aplicador</label>
                    <select class="form-select" name="applied_by">
                        <option value="">Todos</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?= $user->id ?>" 
                                <?= set_select('applied_by', $user->id, 
                                   isset($filters['applied_by']) && $filters['applied_by'] == $user->id) ?>>
                            <?= $user->full_name ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Data Início</label>
                    <input type="date" class="form-control" name="date_from" 
                           value="<?= $filters['date_from'] ?? '' ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Data Fim</label>
                    <input type="date" class="form-control" name="date_to" 
                           value="<?= $filters['date_to'] ?? '' ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Status de Sincronização</label>
                    <select class="form-select" name="sync_status">
                        <option value="">Todos</option>
                        <option value="synced" <?= set_select('sync_status', 'synced', 
                               isset($filters['sync_status']) && $filters['sync_status'] == 'synced') ?>>
                            Sincronizado
                        </option>
                        <option value="pending" <?= set_select('sync_status', 'pending', 
                               isset($filters['sync_status']) && $filters['sync_status'] == 'pending') ?>>
                            Pendente
                        </option>
                    </select>
                </div>
                
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>

<!-- NOVO MODAL: Exportação de Dados Brutos -->
<div class="modal fade" id="exportRawDataModal" tabindex="-1" aria-labelledby="exportRawDataModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportRawDataModalLabel">
                    <i class="fas fa-file-excel me-2"></i>
                    Exportar Dados Brutos
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Container para alertas dinâmicos -->
                <div id="export-alert-container"></div>
                
                <form id="exportRawDataForm" action="<?= base_url('responses/export_raw_data') ?>" method="POST">
                    <div class="mb-3">
                        <label for="export_questionnaire_id" class="form-label">
                            <i class="fas fa-list me-2"></i>
                            Selecionar Questionário
                        </label>
                        <select class="form-select" id="export_questionnaire_id" name="questionnaire_id" required>
                            <option value="">Selecione um questionário...</option>
                            <option value="all">Todos os Questionários</option>
                            <?php foreach ($questionnaires as $questionnaire): ?>
                            <option value="<?= $questionnaire->id ?>">
                                <?= $questionnaire->title ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Selecione "Todos os Questionários" para exportar dados de todos os formulários ou escolha um questionário específico.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-calendar me-2"></i>
                            Filtros Adicionais (Opcional)
                        </label>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="export_date_from" class="form-label small">Data Início</label>
                                <input type="date" class="form-control" id="export_date_from" name="date_from">
                            </div>
                            <div class="col-md-6">
                                <label for="export_date_to" class="form-label small">Data Fim</label>
                                <input type="date" class="form-control" id="export_date_to" name="date_to">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="export_applied_by" class="form-label">
                            <i class="fas fa-user me-2"></i>
                            Aplicador (Opcional)
                        </label>
                        <select class="form-select" id="export_applied_by" name="applied_by">
                            <option value="">Todos os aplicadores</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?= $user->id ?>">
                                <?= $user->full_name ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Formato de Exportação:</strong> Os dados serão exportados em formato Excel (.xlsx) seguindo o modelo de estrutura fornecido, com todas as respostas organizadas em colunas correspondentes às perguntas dos questionários.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Cancelar
                </button>
                <button type="submit" form="exportRawDataForm" class="btn btn-primary">
                    <i class="fas fa-download me-2"></i>
                    Exportar Dados
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Tabela de Respostas (mantém o código original) -->
<div class="card">
    <div class="card-body">
        <?php if (!empty($responses)): ?>
        <div class="table-responsive">
            <table class="table data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Questionário</th>
                        <th>Respondente</th>
                        <th>Aplicador</th>
                        <th>Local</th>
                        <th>Data/Hora</th>
                        <th>Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($responses as $response): ?>
                    <tr>
                        <td><strong>#<?= $response->id ?></strong></td>
                        <td>
                            <strong><?= $response->questionnaire_title ?></strong>
                        </td>
                        <td>
                            <?php if ($response->respondent_name): ?>
                                <?= $response->respondent_name ?>
                                <?php if ($response->respondent_email): ?>
                                    <br><small class="text-muted"><?= $response->respondent_email ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Não informado</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($response->origin) && $response->origin === 'qrcode'): ?>
                                <span class="badge bg-light text-dark border" title="Respondida pelo próprio participante, sem aplicador">
                                    <i class="fas fa-qrcode me-1"></i>Coleta pública (QR Code)
                                </span>
                            <?php else: ?>
                                <?= html_escape($response->applied_by_name) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($response->latitude && $response->longitude): ?>
                                <div class="location-info" data-lat="<?= $response->latitude ?>" data-lng="<?= $response->longitude ?>">
                                    <div class="location-loading">
                                        <i class="fas fa-spinner fa-spin me-1"></i>
                                        <span class="loading-text">Carregando localização...</span>
                                    </div>
                                    <div class="location-content" style="display: none;">
                                        <strong class="location-name"></strong>
                                        <br><small class="text-muted">
                                            <i class="fas fa-map-pin me-1"></i>
                                            <?= number_format($response->latitude, 4) ?>, 
                                            <?= number_format($response->longitude, 4) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">
                                    <i class="fas fa-map-pin me-1"></i>
                                    Localização não capturada
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($response->completed_at): ?>
                                <small>
                                    <i class="fas fa-clock me-1"></i>
                                    <?= date('d/m/Y H:i', strtotime($response->completed_at)) ?>
                                </small>
                            <?php else: ?>
                                <span class="text-warning">
                                    <i class="fas fa-clock me-1"></i>
                                    Incompleto
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $status_classes = [
                                'synced' => 'success',
                                'pending' => 'warning',
                                'error' => 'danger'
                            ];
                            $status_texts = [
                                'synced' => 'Sincronizado',
                                'pending' => 'Pendente',
                                'error' => 'Erro'
                            ];
                            $class = $status_classes[$response->sync_status] ?? 'secondary';
                            $text = $status_texts[$response->sync_status] ?? $response->sync_status;
                            ?>
                            <span class="badge bg-<?= $class ?>">
                                <?= $text ?>
                            </span>
                            
                            <?php if ($response->consent_given): ?>
                                <br><small class="text-success">
                                    <i class="fas fa-check-circle me-1"></i>
                                    Consentimento
                                </small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <a href="<?= base_url('responses/view/' . $response->id) ?>" 
                                   class="btn btn-sm btn-outline-primary" title="Visualizar">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($response->latitude && $response->longitude): ?>
                                <a href="https://maps.google.com/?q=<?= $response->latitude ?>,<?= $response->longitude ?>" 
                                   target="_blank" class="btn btn-sm btn-outline-success" title="Ver no Mapa">
                                    <i class="fas fa-map-marker-alt"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($response->photo_path): ?>
                                <button type="button" class="btn btn-sm btn-outline-info"
                                        onclick="showPhoto('<?= base_url('uploads/photos/' . $response->photo_path) ?>')"
                                        title="Ver Foto 1">
                                    <i class="fas fa-camera"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($response->photo_path_2): ?>
                                <button type="button" class="btn btn-sm btn-outline-info"
                                        onclick="showPhoto('<?= base_url('uploads/photos/' . $response->photo_path_2) ?>')"
                                        title="Ver Foto 2">
                                    <i class="fas fa-camera"></i> 2
                                </button>
                                <?php endif; ?>
                                <?php if (!empty($response->audios['arquivos'])): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="showAudio(this)"
                                        data-response-id="<?= (int) $response->id ?>"
                                        data-audios="<?= html_escape(json_encode($response->audios['arquivos'])) ?>"
                                        title="Ouvir Áudio da Entrevista">
                                    <i class="fas fa-headphones"></i>
                                </button>
                                <?php elseif (!empty($response->audios['registrado'])): ?>
                                <span class="d-inline-block" tabindex="0"
                                      title="Áudio gravado, mas ainda não chegou ao servidor. Ele é enviado na próxima sincronização do aparelho.">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled style="pointer-events: none;">
                                        <i class="fas fa-headphones"></i>
                                    </button>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Loading indicator global para geocodificação -->
        <div id="geocoding-global-loading" class="alert alert-info text-center" style="display: none;">
            <div class="d-flex align-items-center justify-content-center">
                <i class="fas fa-spinner fa-spin me-2"></i>
                <div>
                    <strong>Carregando informações de localização...</strong>
                    <br>
                    <small>Aguarde enquanto buscamos os endereços das coordenadas (<span id="loading-progress">0</span> de <span id="total-locations">0</span>)</small>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Nenhuma resposta encontrada</h5>
            <p class="text-muted">Tente ajustar os filtros ou aguarde novas respostas serem coletadas.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para exibir foto -->
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Foto Capturada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="photoImage" src="" class="img-fluid" alt="Foto capturada" style="max-height: 70vh;">
            </div>
        </div>
    </div>
</div>

<!-- Modal para ouvir o áudio da entrevista -->
<div class="modal fade" id="audioModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-microphone me-2"></i>Áudio da Entrevista <span id="audioModalId"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="audioModalBody"></div>
        </div>
    </div>
</div>

<style>
/* Loading states */
.location-loading {
    font-size: 0.8rem;
    color: #6c757d;
}

.location-info {
    max-width: 200px;
    word-wrap: break-word;
    min-height: 40px;
}

.table td {
    vertical-align: middle;
}

/* Melhorar a apresentação dos badges */
.badge {
    font-size: 0.7rem;
}

/* Loading animation */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.location-content {
    animation: fadeIn 0.5s ease-in;
}

/* Pulse animation para loading */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.loading-text {
    animation: pulse 1.5s infinite;
}

/* Alert personalizado */
#geocoding-global-loading {
    margin-bottom: 1rem;
    border-left: 4px solid #0d6efd;
}

/* Estilos para o modal de exportação */
#exportRawDataModal .modal-dialog {
    max-width: 600px;
}

#exportRawDataModal .form-text {
    color: #6c757d;
    font-size: 0.875rem;
}

#exportRawDataModal .alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}
</style>

<script>
// Função para buscar localização via PHP (backend) - mantém o código original
async function fetchLocationName(latitude, longitude) {
    try {
        // Criar URL para chamar uma função PHP via AJAX
        const formData = new FormData();
        formData.append('latitude', latitude);
        formData.append('longitude', longitude);
        formData.append('action', 'get_location');
        
        const response = await fetch('<?= base_url("responses/get_location") ?>', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error('Erro na requisição');
        }
        
        const result = await response.text();
        return result && result !== 'N/A' ? result : 'Localização não encontrada';
        
    } catch (error) {
        console.error('Erro ao buscar localização:', error);
        return 'Erro ao carregar';
    }
}

// Função alternativa usando Nominatim diretamente (fallback) - mantém o código original
async function fetchLocationNameDirect(latitude, longitude) {
    try {
        // Usar JSONP ou proxy para contornar CORS
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=16&addressdetails=1`;
        
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error('Erro na API');
        }
        
        const data = await response.json();
        
        if (data && data.display_name) {
            return formatLocationName(data);
        }
        
        return 'N/A';
    } catch (error) {
        console.error('Erro ao buscar localização diretamente:', error);
        return 'Erro ao carregar';
    }
}

// Mantém todas as outras funções originais...
function formatLocationName(data) {
    if (!data || !data.address) {
        return data && data.display_name ? data.display_name.substring(0, 60) : 'N/A';
    }

    const address = data.address;
    const locationParts = [];

    // Priorizar informações mais relevantes
    if (address.road) locationParts.push(address.road);
    if (address.suburb || address.neighbourhood) {
        locationParts.push(address.suburb || address.neighbourhood);
    }
    if (address.city || address.town || address.village) {
        locationParts.push(address.city || address.town || address.village);
    }
    if (address.state) locationParts.push(address.state);

    let result = locationParts.length > 0 ? locationParts.join(', ') : data.display_name;
    
    // Limitar tamanho para não quebrar layout
    if (result && result.length > 50) {
        result = result.substring(0, 47) + '...';
    }
    
    return result || 'N/A';
}

// Função para carregar uma localização individual
async function loadSingleLocation(element, index, total) {
    const lat = parseFloat(element.getAttribute('data-lat'));
    const lng = parseFloat(element.getAttribute('data-lng'));
    
    // Validar coordenadas
    if (!lat || !lng || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        updateLocationUI(element, 'Coordenadas inválidas');
        return;
    }
    
    try {
        let locationName = 'N/A';
        
        // Tentar buscar localização
        try {
            locationName = await fetchLocationNameDirect(lat, lng);
        } catch (error) {
            console.warn('Falha na busca direta, tentando método alternativo...');
            locationName = 'Localização não disponível';
        }
        
        // Atualizar interface
        updateLocationUI(element, locationName);
        
        // Atualizar progresso
        updateProgress(index + 1, total);
        
    } catch (error) {
        console.error('Erro ao processar localização:', error);
        updateLocationUI(element, 'Erro ao carregar');
        updateProgress(index + 1, total);
    }
}

// Função para atualizar a UI de uma localização
function updateLocationUI(element, locationName) {
    const loadingDiv = element.querySelector('.location-loading');
    const contentDiv = element.querySelector('.location-content');
    const nameSpan = element.querySelector('.location-name');
    
    if (loadingDiv) loadingDiv.style.display = 'none';
    if (contentDiv) contentDiv.style.display = 'block';
    if (nameSpan) nameSpan.textContent = locationName || 'N/A';
}

// Função para atualizar o progresso
function updateProgress(current, total) {
    const progressSpan = document.getElementById('loading-progress');
    const totalSpan = document.getElementById('total-locations');
    
    if (progressSpan) progressSpan.textContent = current;
    if (totalSpan) totalSpan.textContent = total;
    
    // Se terminou, esconder loading
    if (current >= total) {
        setTimeout(() => {
            const globalLoading = document.getElementById('geocoding-global-loading');
            if (globalLoading) globalLoading.style.display = 'none';
        }, 1000);
    }
}

// Função principal para carregar todas as localizações
async function loadAllLocations() {
    const locationElements = document.querySelectorAll('.location-info[data-lat][data-lng]');
    const totalLocations = locationElements.length;
    
    console.log(`Encontrados ${totalLocations} elementos com coordenadas`);
    
    if (totalLocations === 0) {
        console.log('Nenhuma localização para processar');
        return;
    }
    
    // Mostrar loading global
    const globalLoading = document.getElementById('geocoding-global-loading');
    if (globalLoading) {
        globalLoading.style.display = 'block';
        updateProgress(0, totalLocations);
    }
    
    // Processar cada localização com delay
    for (let i = 0; i < locationElements.length; i++) {
        console.log(`Processando localização ${i + 1}/${totalLocations}`);
        
        await loadSingleLocation(locationElements[i], i, totalLocations);
        
        // Delay entre requisições (reduzido para 500ms)
        if (i < locationElements.length - 1) {
            await new Promise(resolve => setTimeout(resolve, 500));
        }
    }
    
    console.log('Todas as localizações foram processadas');
}

function showPhoto(photoUrl) {
    document.getElementById('photoImage').src = photoUrl;
    new bootstrap.Modal(document.getElementById('photoModal')).show();
}

// A versão web do app grava a entrevista em partes (uma por período de tela
// ativa). Cada parte vira um player e, ao terminar uma, a seguinte começa.
function showAudio(botao) {
    const arquivos = JSON.parse(botao.dataset.audios);
    const corpo = document.getElementById('audioModalBody');
    document.getElementById('audioModalId').textContent = '#' + botao.dataset.responseId;
    corpo.innerHTML = '';

    const players = arquivos.map(function (arquivo, i) {
        const bloco = document.createElement('div');
        bloco.className = 'mb-3';

        if (arquivos.length > 1) {
            const rotulo = document.createElement('div');
            rotulo.className = 'small text-muted mb-1';
            rotulo.textContent = 'Parte ' + (i + 1) + ' de ' + arquivos.length;
            bloco.appendChild(rotulo);
        }

        const player = document.createElement('audio');
        player.controls = true;
        player.preload = 'none';
        player.className = 'w-100';
        player.src = arquivo.url;
        bloco.appendChild(player);

        const rodape = document.createElement('div');
        rodape.className = 'd-flex justify-content-between align-items-center mt-1';
        rodape.innerHTML = '<small class="text-muted"></small>'
            + '<a download class="btn btn-outline-secondary btn-sm"><i class="fas fa-download me-1"></i>Baixar</a>';
        rodape.querySelector('small').textContent =
            (arquivo.tamanho / 1048576).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' MB';
        rodape.querySelector('a').href = arquivo.url;
        bloco.appendChild(rodape);

        corpo.appendChild(bloco);
        return player;
    });

    players.forEach(function (player, i) {
        if (players[i + 1]) {
            player.addEventListener('ended', function () { players[i + 1].play(); });
        }
    });

    bootstrap.Modal.getOrCreateInstance(document.getElementById('audioModal')).show();
    // O clique no botão autoriza o navegador a tocar sem outro clique
    players[0].play().catch(function () { /* navegador bloqueou: fica o player */ });
}

// Fechar o modal para o áudio, senão ele continua tocando escondido
document.getElementById('audioModal').addEventListener('hidden.bs.modal', function () {
    document.querySelectorAll('#audioModalBody audio').forEach(function (player) { player.pause(); });
    document.getElementById('audioModalBody').innerHTML = '';
});

// NOVA FUNÇÃO: Validação do formulário de exportação
function validateExportForm() {
    const questionnaireSelect = document.getElementById('export_questionnaire_id');
    
    if (!questionnaireSelect.value) {
        alert('Por favor, selecione um questionário para exportar.');
        questionnaireSelect.focus();
        return false;
    }
    
    return true;
}

// Inicializar quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM carregado, iniciando carregamento de localizações...');
    
    // Aguardar um pouco para garantir que tudo carregou
    setTimeout(() => {
        loadAllLocations().catch(error => {
            console.error('Erro no carregamento das localizações:', error);
            
            // Esconder loading em caso de erro
            const globalLoading = document.getElementById('geocoding-global-loading');
            if (globalLoading) {
                globalLoading.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Não foi possível carregar algumas localizações. Verifique sua conexão.
                    </div>
                `;
                setTimeout(() => {
                    globalLoading.style.display = 'none';
                }, 3000);
            }
        });
    }, 1000);
    
    // NOVO: Adicionar validação ao formulário de exportação
    const exportForm = document.getElementById('exportRawDataForm');
    if (exportForm) {
        exportForm.addEventListener('submit', function(e) {
            if (!validateExportForm()) {
                e.preventDefault();
                return false;
            }
            
            // Mostrar loading no botão
            const submitBtn = exportForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Exportando...';
            submitBtn.disabled = true;
            
            // Restaurar botão após alguns segundos (caso não haja redirect)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        });
    }
});
</script>