<?php
/**
 * @var object $r
 * @var array  $secoes
 * @var array  $resumo    section_id => ['pendentes', 'total']
 * @var object $atual     seção aberta
 * @var array  $conteudo  montar_secao()
 * @var array  $eixos
 */
$this->load->view('admin/relatorios_municipais/_estilos');

$incluidas = array_filter($secoes, function ($s) { return in_array($s->incluir_no_final, [true, 1, '1', 't', 'true'], true); });
$aprovadas = count(array_filter($incluidas, function ($s) { return $s->status === 'approved'; }));
$pct = count($incluidas) ? round($aprovadas / count($incluidas) * 100) : 0;
$pendentes_secao = $resumo[$atual->id]['pendentes'] ?? 0;
$checklist = $conteudo['checklist'];
$secao_incluida = in_array($atual->incluir_no_final, [true, 1, '1', 't', 'true'], true);
$cores_eixo = ['approved' => '#8fae5d', 'in_review' => '#3b82c4', 'adjustment_requested' => '#dc3545', 'pending' => '#f0ad4e'];

// Filtros de exibição (C03/C04): só aparecem se algum gráfico da seção puder usá-los
$tem_comparacao = false;
$tem_serie = false;
foreach ($conteudo['graficos'] as $g) {
    if (!empty($g['dados']['vazio'])) {
        continue;
    }
    $tem_serie = $tem_serie || $g['dados']['modo'] === 'serie';
    foreach ($g['dados']['datasets'] as $ds) {
        $tem_comparacao = $tem_comparacao || in_array($ds['abrangencia'] ?? '', ['estado', 'brasil'], true);
    }
}
?>
<style>
    .rm-secao-item { display: flex; align-items: center; gap: .6rem; padding: .6rem; border-radius: .5rem; border: 1px solid #eef0f2;
                     margin-bottom: .4rem; text-decoration: none; color: inherit; }
    .rm-secao-item:hover { background: #f8f9fa; color: inherit; }
    .rm-secao-item.ativa { border-color: #3b82c4; background: #f1f6fc; box-shadow: inset 3px 0 0 #3b82c4; }
    .rm-secao-icone { width: 34px; height: 34px; border-radius: 50%; color: #fff; display: flex; align-items: center;
                      justify-content: center; flex-shrink: 0; font-size: .85rem; }
    .rm-grafico-card { border: 1px solid #eef0f2; border-radius: .6rem; padding: 1rem; margin-bottom: 1rem; scroll-margin-top: 80px; }
    .rm-grafico-card.status-approved { border-left: 4px solid #8fae5d; }
    .rm-grafico-card.status-rejected { opacity: .65; }
    .rm-grafico-card.status-adjustment_requested { border-left: 4px solid #dc3545; }
    .rm-qualitativo { background: #f8faf6; border-left: 3px solid #8fae5d; padding: .6rem .8rem; border-radius: .3rem; margin-bottom: .6rem; }
    .rm-filtros { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; padding: .6rem .8rem; }
    .rm-filtros .input-group { width: auto; }
    .rm-filtros .form-select { min-width: 0; }
</style>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h2 class="mb-1"><?= html_escape($r->municipio_nome) ?> - <?= html_escape($r->uf) ?> · Relatório <?= html_escape($r->periodo_referencia) ?></h2>
        <?= relatorio_status_badge($r->status) ?>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= base_url('relatorios-automatizados/dados/' . $r->id) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-table me-1"></i>Dados</a>
        <a href="<?= base_url('relatorios-automatizados/documento/' . $r->id) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-eye me-1"></i>Prévia do aprovado
        </a>
        <?php if (in_array($r->status, ['approved', 'published'], true)): ?>
            <a href="<?= base_url('relatorios-automatizados/aprovacao/' . $r->id) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-signature me-1"></i>Aprovação final</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($tem_comparacao || $tem_serie): ?>
    <div class="rm-card rm-filtros" id="rm-filtros">
        <?php if ($tem_serie): ?>
            <div class="input-group input-group-sm" title="Vale para as séries históricas">
                <span class="input-group-text"><i class="far fa-calendar-alt me-1"></i>Período</span>
                <select class="form-select" id="filtro-de" aria-label="Período: ano inicial"></select>
                <span class="input-group-text">a</span>
                <select class="form-select" id="filtro-ate" aria-label="Período: ano final"></select>
            </div>
        <?php endif; ?>
        <?php if ($tem_comparacao): ?>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fas fa-globe-americas me-1"></i>Comparar com</span>
                <select class="form-select" id="filtro-comparar" aria-label="Comparar com">
                    <option value="estado,brasil">Estado (<?= html_escape($r->uf) ?>) e Brasil</option>
                    <option value="estado">Estado (<?= html_escape($r->uf) ?>)</option>
                    <option value="brasil">Brasil</option>
                    <option value="">Só o município</option>
                </select>
            </div>
        <?php endif; ?>
        <button type="button" class="btn btn-link btn-sm text-decoration-none d-none" id="filtro-limpar">
            <i class="fas fa-undo me-1"></i>Mostrar tudo
        </button>
        <span class="rm-fonte ms-auto">Muda só a visualização; os dados e o relatório final não são alterados.</span>
    </div>
<?php endif; ?>

<div class="row g-3">
    <!-- Seções -->
    <div class="col-xl-3 col-lg-4">
        <div class="rm-card">
            <h6 class="mb-1">Seções do relatório</h6>
            <div class="small text-muted mb-1">Progresso geral: <?= $aprovadas ?> de <?= count($incluidas) ?></div>
            <div class="rm-progresso mb-3"><div style="width: <?= $pct ?>%; background: #8fae5d;"></div></div>
            <?php foreach ($secoes as $s):
                $incl = in_array($s->incluir_no_final, [true, 1, '1', 't', 'true'], true);
            ?>
                <a class="rm-secao-item <?= $s->id === $atual->id ? 'ativa' : '' ?>"
                   href="<?= base_url('relatorios-automatizados/revisar/' . $r->id . '/' . $s->eixo) ?>">
                    <div class="rm-secao-icone" style="background: <?= $cores_eixo[$s->status] ?? '#adb5bd' ?>;">
                        <i class="fas <?= $eixos[$s->eixo]['icone'] ?? 'fa-file' ?>"></i>
                    </div>
                    <div class="flex-grow-1" style="min-width: 0;">
                        <div class="small fw-semibold text-truncate"><?= html_escape($s->titulo) ?></div>
                        <div style="font-size: .75rem;">
                            <?= secao_status_badge($s->status) ?>
                            <?php if (!$incl): ?><span class="badge bg-light text-muted border">fora do final</span><?php endif; ?>
                            <?php if (($resumo[$s->id]['pendentes'] ?? 0) > 0): ?>
                                <span class="text-muted"><?= (int) $resumo[$s->id]['pendentes'] ?> gráf. a conferir</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Conteúdo da seção -->
    <div class="col-xl-6 col-lg-8">
        <div class="rm-card">
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="rm-secao-icone" style="background: #3b82c4; width: 42px; height: 42px;"><i class="fas <?= $conteudo['icone'] ?>"></i></div>
                <h4 class="mb-0 flex-grow-1"><?= html_escape($conteudo['titulo']) ?></h4>
                <?= secao_status_badge($atual->status) ?>
            </div>

            <?php if (!empty($conteudo['kpis'])): ?>
                <div class="row g-2 mb-3">
                    <?php foreach ($conteudo['kpis'] as $k): ?>
                        <div class="col-sm-6 col-md-4">
                            <div class="border rounded p-2 h-100">
                                <div class="rm-kpi-valor" style="font-size: 1.3rem;"><?= html_escape($k['valor']) ?></div>
                                <div class="small"><?= html_escape($k['rotulo']) ?><?= $k['unidade'] ? ' (' . html_escape($k['unidade']) . ')' : '' ?></div>
                                <div class="rm-fonte"><?= html_escape($k['fonte']) ?> · <?= html_escape($k['periodo']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <label class="form-label fw-semibold small" for="texto-secao">Texto da seção (vai para o cliente)</label>
            <textarea class="form-control mb-1" id="texto-secao" name="texto" form="form-secao" rows="5"
                      placeholder="Texto interpretativo da seção"><?= html_escape($atual->texto !== null && $atual->texto !== '' ? $atual->texto : $conteudo['sugestao']) ?></textarea>
            <?php if (($atual->texto === null || $atual->texto === '') && $conteudo['sugestao'] !== ''): ?>
                <div class="rm-fonte mb-3"><i class="fas fa-magic me-1"></i>Texto sugerido a partir dos dados. Revise antes de aprovar.</div>
            <?php endif; ?>

            <?php if (!empty($conteudo['qualitativos'])): ?>
                <h6 class="mt-3 small fw-bold">Informações complementares</h6>
                <?php foreach ($conteudo['qualitativos'] as $q): ?>
                    <div class="rm-qualitativo">
                        <div class="small fw-semibold"><?= html_escape($q['rotulo']) ?></div>
                        <div class="small"><?= nl2br(html_escape($q['texto'])) ?></div>
                        <div class="rm-fonte"><?= html_escape($q['fonte']) ?><?= $q['periodo'] ? ' · ' . html_escape($q['periodo']) : '' ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php foreach ($conteudo['graficos'] as $g):
            $d = $g['dados'];
            $texto = $g['texto'] !== null && $g['texto'] !== '' ? $g['texto'] : $g['sugestao'];
        ?>
            <div class="rm-card rm-grafico-card status-<?= $g['status'] ?>" id="grafico-<?= $g['id'] ?>">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <h6 class="mb-0"><?= html_escape($g['titulo']) ?></h6>
                    <?= secao_status_badge($g['status']) ?>
                </div>

                <?php if ($d['vazio']): ?>
                    <div class="rm-vazio">
                        <i class="fas fa-chart-bar fa-2x mb-2" style="opacity: .4"></i>
                        <div class="small">Sem dados para este gráfico.</div>
                        <a class="small" href="<?= base_url('relatorios-automatizados/dados/' . $r->id . '?eixo=' . $atual->eixo) ?>">Complementar dados</a>
                        <div class="small mt-1">ou rejeite o gráfico para deixá-lo fora do relatório.</div>
                    </div>
                <?php else: ?>
                    <div class="rm-grafico-box">
                        <canvas data-grafico="<?= html_escape(json_encode($d, JSON_UNESCAPED_UNICODE)) ?>"></canvas>
                    </div>
                    <div class="rm-vazio rm-vazio-filtro d-none">
                        <i class="fas fa-filter fa-2x mb-2" style="opacity: .4"></i>
                        <div class="small">Sem dados no período escolhido.</div>
                    </div>
                    <div class="rm-fonte mt-2">
                        Fonte: <?= html_escape(implode('; ', $d['fontes']) ?: 'não informada') ?>
                        <span class="rm-ref" data-original="<?= html_escape($d['periodo']) ?>"><?= $d['periodo'] !== '' ? ' · Referência: ' . html_escape($d['periodo']) : '' ?></span>
                    </div>
                <?php endif; ?>

                <?= form_open('relatorios-automatizados/salvar_grafico/' . $r->id . '/' . $g['id'], ['class' => 'mt-3']) ?>
                    <label class="form-label small mb-1">Texto interpretativo</label>
                    <textarea class="form-control form-control-sm mb-2" name="texto" rows="2"><?= html_escape($texto) ?></textarea>

                    <details class="mb-2" <?= $g['observacoes'] ? 'open' : '' ?>>
                        <summary class="small text-muted">Observação interna (não vai para o cliente)</summary>
                        <textarea class="form-control form-control-sm mt-1" name="observacoes_internas" rows="2" maxlength="1000"><?= html_escape((string) $g['observacoes']) ?></textarea>
                    </details>

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="form-check form-switch small mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" name="incluir_no_final" value="1"
                                   id="incl-<?= $g['id'] ?>" <?= $g['incluir'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="incl-<?= $g['id'] ?>">No relatório final</label>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button type="submit" name="acao" value="salvar" class="btn btn-outline-secondary" title="Salvar sem decidir"><i class="fas fa-save"></i></button>
                            <button type="submit" name="acao" value="rejeitar" class="btn btn-outline-secondary">Rejeitar</button>
                            <button type="submit" name="acao" value="ajuste" class="btn btn-outline-danger">Solicitar ajuste</button>
                            <button type="submit" name="acao" value="aprovar" class="btn btn-success" <?= $d['vazio'] ? 'disabled title="Sem dados"' : '' ?>>
                                <i class="fas fa-check me-1"></i>Aprovar
                            </button>
                        </div>
                    </div>
                <?= form_close() ?>
            </div>
        <?php endforeach; ?>

        <?php if (empty($conteudo['graficos'])): ?>
            <div class="rm-card text-muted small"><i class="fas fa-info-circle me-1"></i>Esta seção não tem gráficos no catálogo.</div>
        <?php endif; ?>
    </div>

    <!-- Revisão da seção -->
    <div class="col-xl-3 col-lg-12">
        <div class="rm-card" style="position: sticky; top: 80px;">
            <h6 class="mb-3">Revisão da seção</h6>
            <?= form_open('relatorios-automatizados/salvar_secao/' . $r->id . '/' . $atual->id, ['id' => 'form-secao']) ?>
                <div class="mb-3">
                    <div class="small fw-semibold mb-1">Checklist de revisão</div>
                    <?php foreach (['fonte' => 'Fonte conferida', 'periodo' => 'Período conferido', 'texto' => 'Texto revisado'] as $k => $rot): ?>
                        <div class="form-check">
                            <input class="form-check-input chk" type="checkbox" name="checklist[]" value="<?= $k ?>" id="chk-<?= $k ?>"
                                   <?= in_array($k, $checklist, true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="chk-<?= $k ?>"><?= $rot ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <label class="form-label small fw-semibold" for="obs-secao">Observações internas</label>
                <textarea class="form-control form-control-sm mb-1" id="obs-secao" name="observacoes_internas" rows="4" maxlength="1000"
                          placeholder="Apontamentos e recomendações sobre esta seção"><?= html_escape((string) $atual->observacoes_internas) ?></textarea>
                <div class="rm-fonte mb-3">Nunca são enviadas ao cliente.</div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" name="incluir_no_final" value="1" id="incl-secao"
                           <?= $secao_incluida ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="incl-secao">Incluir seção no relatório final</label>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" name="acao" value="ajuste" class="btn btn-outline-danger btn-sm"><i class="fas fa-comment-dots me-1"></i>Solicitar ajuste</button>
                    <button type="submit" name="acao" value="salvar" class="btn btn-outline-secondary btn-sm"><i class="fas fa-save me-1"></i>Salvar revisão</button>
                    <button type="submit" name="acao" value="aprovar" class="btn btn-success" id="btn-aprovar-secao">
                        <i class="fas fa-check-circle me-1"></i>Aprovar seção
                    </button>
                </div>
                <div class="small text-muted mt-2" id="motivo-bloqueio">
                    <?php if ($pendentes_secao > 0): ?>
                        <i class="fas fa-info-circle me-1"></i><?= (int) $pendentes_secao ?> gráfico(s) ainda sem decisão.
                    <?php endif; ?>
                </div>
            <?= form_close() ?>

            <div class="alert alert-light border small mt-3 mb-0">
                <i class="fas fa-shield-alt me-1 text-success"></i>
                Seção aprovada entra no relatório final. Gráficos rejeitados ou fora do final não são enviados ao cliente.
            </div>
        </div>
    </div>
</div>

<script src="<?= base_url('assets/js/relatorios/graficos.js') ?>"></script>
<script>
// Gráficos com o filtro de período e comparação (C03/C04). O filtro só muda a
// tela; fica guardado por relatório neste navegador.
(function () {
    var canvases = Array.prototype.slice.call(document.querySelectorAll('canvas[data-grafico]'));
    var graficos = canvases.map(function (c) {
        try { return JSON.parse(c.dataset.grafico); } catch (e) { return null; }
    });
    var selDe = document.getElementById('filtro-de');
    var selAte = document.getElementById('filtro-ate');
    var selComparar = document.getElementById('filtro-comparar');
    var limpar = document.getElementById('filtro-limpar');
    var chave = 'sx-rm-filtro-<?= (int) $r->id ?>';

    var anos = SXGraficos.anosDasSeries(graficos);
    if (selDe && selAte) {
        anos.forEach(function (a) {
            selDe.add(new Option(a, a));
            selAte.add(new Option(a, a));
        });
        if (anos.length) {
            selDe.value = anos[0];
            selAte.value = anos[anos.length - 1];
        } else {
            selDe.closest('.input-group').classList.add('d-none');
        }
    }

    var guardado = null;
    try { guardado = JSON.parse(localStorage.getItem(chave) || 'null'); } catch (e) { guardado = null; }
    if (guardado) {
        if (selDe && anos.indexOf(guardado.de) !== -1) selDe.value = guardado.de;
        if (selAte && anos.indexOf(guardado.ate) !== -1) selAte.value = guardado.ate;
        if (selComparar && guardado.comparar !== undefined) selComparar.value = guardado.comparar;
    }

    function filtroAtual() {
        var de = selDe && anos.length ? parseInt(selDe.value, 10) : null;
        var ate = selAte && anos.length ? parseInt(selAte.value, 10) : null;
        if (de && ate && de > ate) { var t = de; de = ate; ate = t; }
        return {
            de: de, ate: ate,
            comparar: selComparar ? (selComparar.value ? selComparar.value.split(',') : []) : null
        };
    }

    function aplicar() {
        var f = filtroAtual();
        var tudo = (!anos.length || (f.de === anos[0] && f.ate === anos[anos.length - 1]))
                   && (!selComparar || selComparar.value === 'estado,brasil');
        if (limpar) limpar.classList.toggle('d-none', tudo);
        try {
            if (tudo) localStorage.removeItem(chave);
            else localStorage.setItem(chave, JSON.stringify({ de: f.de, ate: f.ate, comparar: selComparar ? selComparar.value : undefined }));
        } catch (e) { /* sem armazenamento: o filtro vale só nesta visita */ }

        canvases.forEach(function (canvas, i) {
            if (!graficos[i]) return;
            var g = SXGraficos.filtrar(graficos[i], f);
            var card = canvas.closest('.rm-grafico-card');
            var box = canvas.closest('.rm-grafico-box');
            var aviso = card && card.querySelector('.rm-vazio-filtro');
            var ref = card && card.querySelector('.rm-ref');
            if (box) box.classList.toggle('d-none', g.vazio);
            if (aviso) aviso.classList.toggle('d-none', !g.vazio);
            if (ref) ref.textContent = g.periodo ? ' · Referência: ' + g.periodo : '';
            if (g.vazio) {
                if (canvas._sxChart) { canvas._sxChart.destroy(); canvas._sxChart = null; }
            } else {
                SXGraficos.desenhar(canvas, g);
            }
        });
    }

    [selDe, selAte, selComparar].forEach(function (s) { if (s) s.addEventListener('change', aplicar); });
    if (limpar) limpar.addEventListener('click', function () {
        if (anos.length) { selDe.value = anos[0]; selAte.value = anos[anos.length - 1]; }
        if (selComparar) selComparar.value = 'estado,brasil';
        aplicar();
    });
    aplicar();
})();

// Aprovar seção exige os três itens do checklist (o servidor confere de novo)
(function () {
    var botao = document.getElementById('btn-aprovar-secao');
    var pendentes = <?= (int) $pendentes_secao ?>;
    var atualizar = function () {
        var completos = document.querySelectorAll('.chk:checked').length === 3;
        botao.disabled = !completos || pendentes > 0;
        botao.title = !completos ? 'Marque os três itens do checklist' : (pendentes > 0 ? 'Decida todos os gráficos da seção' : '');
    };
    document.querySelectorAll('.chk').forEach(function (c) { c.addEventListener('change', atualizar); });
    atualizar();
})();
</script>
