<?php
/**
 * @var object $r
 * @var array  $conteudo    conteúdo que irá para o cliente
 * @var int    $n_graficos
 * @var array  $versoes
 * @var array  $marcos      tipo de evento => data mais recente
 */
$this->load->view('admin/relatorios_municipais/_estilos');
$publicado = $r->status === 'published';
$proxima = (int) $r->versao_atual + ($publicado ? 0 : 1);
$data = function ($tipo) use ($marcos) { return isset($marcos[$tipo]) ? date('d/m/Y', strtotime($marcos[$tipo])) : null; };
$etapas = [
    ['Dados coletados', $data('processado') ?: $data('criado')],
    ['Relatório interno', $data('processado')],
    ['Revisão concluída', $data('revisao_concluida')],
    ['Aprovação final', $publicado ? $data('publicado') : null],
];
?>
<style>
    .rm-linha-tempo { display: flex; justify-content: space-between; position: relative; }
    .rm-linha-tempo::before { content: ''; position: absolute; top: 14px; left: 8%; right: 8%; height: 2px; background: #dee2e6; }
    .rm-etapa { text-align: center; position: relative; flex: 1; font-size: .82rem; }
    .rm-etapa .ponto { width: 28px; height: 28px; border-radius: 50%; margin: 0 auto .35rem; display: flex; align-items: center;
                       justify-content: center; background: #fff; border: 2px solid #ced4da; color: #fff; position: relative; }
    .rm-etapa.feita .ponto { background: #8fae5d; border-color: #8fae5d; }
    .rm-previa { width: 100%; height: 760px; border: 1px solid #eef0f2; border-radius: .5rem; background: #e9ecef; display: block; }
    .rm-verif { display: flex; gap: .6rem; padding: .5rem 0; }
</style>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h2 class="mb-1">Relatório Municipal de <?= html_escape($r->municipio_nome) ?> - <?= html_escape($r->uf) ?></h2>
        <div class="text-muted small">Visualize o conteúdo do relatório antes da aprovação final para o cliente.</div>
    </div>
    <?= relatorio_status_badge($r->status) ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="rm-card p-2">
            <iframe class="rm-previa" src="<?= base_url('relatorios-automatizados/documento/' . $r->id) ?>?visualizador=1"
                    title="Prévia do relatório" allowfullscreen></iframe>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="rm-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Aprovação final</h6>
                <span class="badge bg-light text-success border">v<?= $proxima ?>.0</span>
            </div>
            <div class="small d-flex justify-content-between"><span class="text-muted">Versão do relatório</span><span>v<?= $proxima ?>.0</span></div>
            <div class="small d-flex justify-content-between mb-2"><span class="text-muted">Conteúdo</span><span><?= count($conteudo['secoes']) ?> seção(ões), <?= $n_graficos ?> gráfico(s)</span></div>
            <hr>

            <div class="rm-verif">
                <i class="fas fa-check-circle text-success mt-1"></i>
                <div><div class="small fw-semibold">Conteúdo aprovado</div><div class="rm-fonte">Todas as seções incluídas foram revisadas e aprovadas.</div></div>
            </div>
            <div class="rm-verif">
                <i class="fas fa-check-circle text-success mt-1"></i>
                <div><div class="small fw-semibold">Fontes conferidas</div><div class="rm-fonte">Checklist de fonte e período marcado em cada seção (<?= count($conteudo['fontes']) ?> fonte(s) citadas).</div></div>
            </div>

            <?php if ($publicado): ?>
                <div class="alert alert-success small mt-3 mb-0">
                    <i class="fas fa-paper-plane me-1"></i>
                    A versão <?= (int) $r->versao_atual ?> já foi aprovada e está disponível para o cliente.
                    Para gerar outra versão, altere o conteúdo na revisão.
                </div>
                <div class="d-grid gap-2 mt-3">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('relatorios-automatizados/revisar/' . $r->id) ?>"><i class="fas fa-arrow-left me-1"></i>Voltar para revisão</a>
                    <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="<?= base_url('relatorios-automatizados/documento/' . $r->id . '/' . (int) $r->versao_atual) ?>"><i class="fas fa-download me-1"></i>Abrir versão publicada</a>
                </div>
            <?php else: ?>
                <?= form_open('relatorios-automatizados/aprovar_final/' . $r->id, ['id' => 'form-aprovar']) ?>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="formatacao" required>
                        <label class="form-check-label small" for="formatacao">
                            <span class="fw-semibold">Formatação conferida</span><br>
                            <span class="rm-fonte">Conferi a prévia: padrão visual, textos e estrutura.</span>
                        </label>
                    </div>

                    <label class="form-label small fw-semibold mt-3" for="obs-final">Observações finais (opcional, interno)</label>
                    <textarea class="form-control form-control-sm mb-1" id="obs-final" name="observacoes_finais" rows="3" maxlength="500"
                              placeholder="Adicione uma observação antes da aprovação..."></textarea>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('relatorios-automatizados/revisar/' . $r->id) ?>"><i class="fas fa-arrow-left me-1"></i>Voltar para revisão</a>
                        <a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener" href="<?= base_url('relatorios-automatizados/documento/' . $r->id) ?>"><i class="fas fa-download me-1"></i>Baixar prévia</a>
                    </div>
                    <div class="d-grid mt-2">
                        <button type="submit" class="btn btn-success"
                                onclick="return confirm('Aprovar a versão <?= $proxima ?> deste relatório para o cliente? O conteúdo fica congelado nesta versão.');">
                            <i class="fas fa-check-double me-1"></i>Aprovar relatório
                        </button>
                    </div>
                <?= form_close() ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($versoes)): ?>
            <div class="rm-card">
                <h6 class="mb-2">Versões aprovadas</h6>
                <ul class="list-unstyled small mb-0">
                    <?php foreach ($versoes as $v): ?>
                        <li class="d-flex justify-content-between py-1 border-bottom">
                            <span>
                                <a target="_blank" rel="noopener" href="<?= base_url('relatorios-automatizados/documento/' . $r->id . '/' . (int) $v->versao) ?>">v<?= (int) $v->versao ?>.0</a>
                                <span class="text-muted"> · <?= html_escape((string) $v->aprovado_por_nome) ?></span>
                            </span>
                            <span class="text-muted"><?= date('d/m/Y H:i', strtotime($v->aprovado_em)) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="rm-card">
    <div class="rm-linha-tempo">
        <?php foreach ($etapas as $e): ?>
            <div class="rm-etapa <?= $e[1] ? 'feita' : '' ?>">
                <div class="ponto"><?= $e[1] ? '<i class="fas fa-check" style="font-size:.7rem"></i>' : '' ?></div>
                <div class="fw-semibold"><?= $e[0] ?></div>
                <div class="text-muted"><?= $e[1] ?: 'Pendente' ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
