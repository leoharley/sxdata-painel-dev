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
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}&zoom=16&addressdetails=1&accept-language=pt-BR";
        
        // Configurar contexto da requisição
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: SXData-App/1.0',
                    'Accept: application/json'
                ],
                'timeout' => 5
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        
        if ($response === FALSE) {
            return 'N/A';
        }

        $data = json_decode($response, true);
        
        if (isset($data['display_name'])) {
            return format_location_name($data);
        }

    } catch (Exception $e) {
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

    $result = !empty($location_parts) ? implode(', ', $location_parts) : $data['display_name'];
    
    // Não limitar muito o tamanho aqui pois é uma tela de detalhes
    if (strlen($result) > 100) {
        $result = substr($result, 0, 97) . '...';
    }
    
    return $result;
}

?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Resposta #<?= $response->id ?></h2>
            <div>
                <a href="<?= base_url('responses/export?form_id=' . $response->id) ?>" 
                   class="btn btn-outline-success me-2">
                    <i class="fas fa-download me-2"></i>
                    Exportar
                </a>
                <a href="<?= base_url('responses') ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>
                    Voltar
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Informações do Formulário -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Informações Gerais</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Questionário:</strong><br>
                        <span class="text-primary"><?= $questionnaire->title ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Aplicador:</strong><br>
                        <?php if (isset($response->origin) && $response->origin === 'qrcode'): ?>
                            <i class="fas fa-qrcode me-1"></i>Coleta pública (QR Code) - respondida pelo próprio participante
                        <?php else: ?>
                            <?= $applied_by ? html_escape($applied_by->full_name) : '<span class="text-muted">Não identificado</span>' ?>
                        <?php endif; ?>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Data/Hora Conclusão:</strong><br>
                        <?= $response->completed_at ? date('d/m/Y H:i:s', strtotime($response->completed_at)) : 'Não concluído' ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Status de Sincronização:</strong><br>
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
                        <span class="badge bg-<?= $class ?>"><?= $text ?></span>
                    </div>
                </div>
                
                <?php if ($response->respondent_name || $response->respondent_email): ?>
                <hr>
                <div class="row">
                    <?php if ($response->respondent_name): ?>
                    <div class="col-md-6">
                        <strong>Nome do Respondente:</strong><br>
                        <?= $response->respondent_name ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($response->respondent_email): ?>
                    <div class="col-md-6">
                        <strong>Email do Respondente:</strong><br>
                        <?= $response->respondent_email ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($response->consent_given): ?>
                <hr>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Consentimento concedido</strong> - O respondente autorizou o uso dos dados conforme LGPD.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Respostas -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Respostas do Questionário</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($answers)): ?>
                    <?php foreach ($answers as $index => $answer): ?>
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="text-primary mb-0">Pergunta <?= $index + 1 ?></h6>
                            <span class="badge bg-secondary"><?= ucfirst($answer->question_type) ?></span>
                        </div>
                        
                        <p class="mb-2"><strong><?= $answer->question_text ?></strong></p>
                        
                        <div class="ms-3">
                            <?php if ($answer->response_text): ?>
                                <div class="bg-light p-3 rounded">
                                    <?= nl2br(htmlspecialchars($answer->response_text)) ?>
                                </div>
                            <?php elseif ($answer->response_number !== null): ?>
                                <div class="bg-light p-3 rounded">
                                    <strong><?= number_format($answer->response_number, 2) ?></strong>
                                </div>
                            <?php elseif ($answer->response_date): ?>
                                <div class="bg-light p-3 rounded">
                                    <strong><?= date('d/m/Y', strtotime($answer->response_date)) ?></strong>
                                </div>
                            <?php elseif ($answer->response_datetime): ?>
                                <div class="bg-light p-3 rounded">
                                    <strong><?= date('d/m/Y H:i', strtotime($answer->response_datetime)) ?></strong>
                                </div>
                            <?php elseif ($answer->selected_options): ?>
                                <div class="bg-light p-3 rounded">
                                    <?php 
                                    $options = json_decode($answer->selected_options);
                                    if (is_array($options)):
                                        foreach ($options as $option): ?>
                                            <span class="badge bg-primary me-1"><?= htmlspecialchars($option) ?></span>
                                        <?php endforeach;
                                    endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-muted">
                                    <em>Não respondido</em>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Nenhuma resposta encontrada para este formulário.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Localização -->
        <?php if ($response->latitude && $response->longitude): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-map-marker-alt me-2"></i>
                    Localização
                </h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <i class="fas fa-map-marker-alt fa-2x text-success mb-2"></i>
                    <br>
                    <strong>Coordenadas GPS</strong>
                </div>
                
                <div class="text-center mb-3">
                    <code class="fs-6"><?= number_format($response->latitude, 6) ?>, <?= number_format($response->longitude, 6) ?></code>
                </div>
                
                <!-- Endereço formatado -->
                <div class="text-center mb-3">
                    <div class="alert-info mb-2" style="padding:10px;background-color:#cff4fc;border: none;border-radius: 0.5rem;">
                        <i class="fas fa-map-pin me-2"></i>
                        <strong><?= get_location_name($response->latitude, $response->longitude) ?></strong>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <a href="https://maps.google.com/?q=<?= $response->latitude ?>,<?= $response->longitude ?>" 
                       target="_blank" class="btn btn-success btn-sm">
                        <i class="fas fa-external-link-alt me-1"></i>
                        Ver no Google Maps
                    </a>
                    <button type="button" class="btn btn-outline-primary btn-sm" 
                            onclick="copyCoordinates('<?= $response->latitude ?>,<?= $response->longitude ?>')">
                        <i class="fas fa-copy me-1"></i>
                        Copiar Coordenadas
                    </button>
                </div>
                
                <!-- Informações adicionais da localização -->
                <hr>
                <div class="text-center">
                    <small class="text-muted">
                        <strong>Precisão:</strong> GPS<br>
                        <strong>Capturado em:</strong> <?= $response->completed_at ? date('d/m/Y H:i', strtotime($response->completed_at)) : 'N/A' ?>
                    </small>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Foto 1 -->
        <?php if ($response->photo_path): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-camera me-2"></i>
                    Evidência Fotográfica 1
                </h5>
            </div>
            <div class="card-body text-center">
                <?php if (empty($response->photo_ok)): ?>
                <div class="alert alert-warning text-start small mb-0">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <strong>Foto registrada, mas o arquivo não chegou ao servidor.</strong>
                    O app reenvia na próxima sincronização se ainda tiver a foto no aparelho.
                    <div class="text-muted mt-1">Arquivo esperado: <code><?= html_escape($response->photo_path) ?></code></div>
                </div>
                <?php else: ?>
                <img src="<?= base_url('uploads/photos/' . $response->photo_path) ?>"
                     class="img-fluid rounded mb-3 shadow-sm" alt="Foto capturada"
                     style="max-height: 200px; cursor: pointer; border: 2px solid #e9ecef;"
                     onclick="showFullPhoto(this.src)">
                <br>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm"
                            onclick="showFullPhoto('<?= base_url('uploads/photos/' . $response->photo_path) ?>')">
                        <i class="fas fa-expand me-1"></i>
                        Ver em Tamanho Real
                    </button>
                    <a href="<?= base_url('uploads/photos/' . $response->photo_path) ?>"
                       download class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-download me-1"></i>
                        Baixar Foto
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Foto 2 -->
        <?php if ($response->photo_path_2): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-camera me-2"></i>
                    Evidência Fotográfica 2
                </h5>
            </div>
            <div class="card-body text-center">
                <?php if (empty($response->photo2_ok)): ?>
                <div class="alert alert-warning text-start small mb-0">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <strong>Foto registrada, mas o arquivo não chegou ao servidor.</strong>
                    O app reenvia na próxima sincronização se ainda tiver a foto no aparelho.
                    <div class="text-muted mt-1">Arquivo esperado: <code><?= html_escape($response->photo_path_2) ?></code></div>
                </div>
                <?php else: ?>
                <img src="<?= base_url('uploads/photos/' . $response->photo_path_2) ?>"
                     class="img-fluid rounded mb-3 shadow-sm" alt="Foto capturada 2"
                     style="max-height: 200px; cursor: pointer; border: 2px solid #e9ecef;"
                     onclick="showFullPhoto(this.src)">
                <br>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm"
                            onclick="showFullPhoto('<?= base_url('uploads/photos/' . $response->photo_path_2) ?>')">
                        <i class="fas fa-expand me-1"></i>
                        Ver em Tamanho Real
                    </button>
                    <a href="<?= base_url('uploads/photos/' . $response->photo_path_2) ?>"
                       download class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-download me-1"></i>
                        Baixar Foto
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Áudio da entrevista -->
        <?php if (!empty($audios['registrado'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-microphone me-2"></i>
                    Áudio da Entrevista
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($audios['arquivos'])): ?>
                    <div class="text-muted small">
                        <i class="fas fa-hourglass-half me-1"></i>
                        O áudio foi gravado, mas o arquivo ainda não chegou ao servidor.
                        Ele é enviado na próxima sincronização do aparelho.
                    </div>
                <?php else: ?>
                    <?php foreach ($audios['arquivos'] as $i => $audio): ?>
                        <?php if (count($audios['arquivos']) > 1): ?>
                            <div class="small text-muted mb-1">Parte <?= $i + 1 ?> de <?= count($audios['arquivos']) ?></div>
                        <?php endif; ?>
                        <audio controls preload="none" class="w-100 mb-2" src="<?= html_escape($audio['url']) ?>">
                            Seu navegador não reproduz este formato. Use o botão de download.
                        </audio>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <small class="text-muted"><?= number_format($audio['tamanho'] / 1048576, 1, ',', '.') ?> MB</small>
                            <a href="<?= html_escape($audio['url']) ?>" download class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-download me-1"></i>Baixar
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Metadados -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Metadados
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-12">
                        <small class="text-muted">
                            <strong>ID da Resposta:</strong><br>
                            <span class="badge bg-light text-dark"><?= $response->id ?></span>
                        </small>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">
                            <strong>Data de Criação:</strong><br>
                            <?= date('d/m/Y H:i:s', strtotime($response->created_at)) ?>
                        </small>
                    </div>
                    <?php if ($response->started_at): ?>
                    <div class="col-12">
                        <small class="text-muted">
                            <strong>Iniciado em:</strong><br>
                            <?= date('d/m/Y H:i:s', strtotime($response->started_at)) ?>
                        </small>
                    </div>
                    <?php endif; ?>
                    <?php if ($response->completed_at): ?>
                    <div class="col-12">
                        <small class="text-muted">
                            <strong>Concluído em:</strong><br>
                            <?= date('d/m/Y H:i:s', strtotime($response->completed_at)) ?>
                        </small>
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <small class="text-muted">
                            <strong>Versão do Questionário:</strong><br>
                            <span class="badge bg-secondary"><?= $questionnaire->version ?></span>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para foto em tamanho real -->
<div class="modal fade" id="fullPhotoModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-camera me-2"></i>
                    Evidência Fotográfica - Tamanho Real
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="fullPhotoImage" src="" class="img-fluid rounded shadow" alt="Foto em tamanho real">
            </div>
            <div class="modal-footer">
                <a id="downloadPhotoLink" href="" download class="btn btn-success">
                    <i class="fas fa-download me-1"></i>
                    Baixar Foto
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast para notificações -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="coordinatesToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="fas fa-check-circle text-success me-2"></i>
            <strong class="me-auto">Sucesso</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            Coordenadas copiadas para a área de transferência!
        </div>
    </div>
</div>

<style>
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

.alert-info {
    border-left: 4px solid #0dcaf0;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.code {
    background-color: #f8f9fa;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-family: 'Courier New', monospace;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

#location-display {
    animation: fadeIn 0.5s ease-in;
}
</style>

<script>
function showFullPhoto(photoUrl) {
    document.getElementById('fullPhotoImage').src = photoUrl;
    document.getElementById('downloadPhotoLink').href = photoUrl;
    new bootstrap.Modal(document.getElementById('fullPhotoModal')).show();
}

function copyCoordinates(coordinates) {
    navigator.clipboard.writeText(coordinates).then(function() {
        // Mostrar toast de sucesso
        const toast = new bootstrap.Toast(document.getElementById('coordinatesToast'));
        toast.show();
    }).catch(function(err) {
        console.error('Erro ao copiar coordenadas: ', err);
        alert('Coordenadas: ' + coordinates);
    });
}
</script>