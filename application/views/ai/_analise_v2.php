<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Análise estatística no formato novo (summary_text com "versao": 2).
 * Usada na tela do cliente e na do administrador.
 *
 * Variáveis: $v2 (array decodificado do summary_text) e $secao:
 *   'topo'   = visão geral, resumo, destaques, atenção e recomendações
 *   'outras' = análise das perguntas sem gráfico (numéricas e abertas)
 */
$v2 = isset($v2) && is_array($v2) ? $v2 : array();
$secao = isset($secao) ? $secao : 'topo';
?>
<?php if ($secao === 'topo'): ?>
<style>
    .analysis-header-card { background: linear-gradient(135deg, rgba(143,174,93,0.1), rgba(35,52,95,0.05));
        border: 1px solid rgba(143,174,93,0.2); border-radius: .75rem; padding: 1.5rem; margin-bottom: 1.5rem; }
    .av2-kpis { display: flex; flex-wrap: wrap; gap: 1rem; margin: 1rem 0 0; }
    .av2-kpi { background: #fff; border: 1px solid #e9ecef; border-radius: .6rem; padding: .75rem 1.1rem; min-width: 150px; }
    .av2-kpi strong { display: block; font-size: 1.6rem; color: var(--secondary-color); line-height: 1.1; }
    .av2-kpi span { font-size: .8rem; color: #6c757d; }
    .av2-resumo p { font-size: 1.02rem; line-height: 1.75; color: #333; margin-bottom: .8rem; }
    .av2-card { background: #fff; border-radius: .75rem; box-shadow: 0 2px 8px rgba(0,0,0,.08); height: 100%; overflow: hidden; }
    .av2-card h6 { margin: 0; padding: .9rem 1.1rem; font-weight: 600; color: var(--secondary-color); border-bottom: 1px solid #f0f0f0; }
    .av2-card ul { margin: 0; padding: .9rem 1.1rem .9rem 2.2rem; }
    .av2-card li { margin-bottom: .55rem; line-height: 1.55; color: #333; }
</style>

<div class="analysis-header-card">
    <h5 style="color: var(--secondary-color); font-weight: 600;">
        <i class="fas fa-file-alt me-2" style="color: var(--primary-color);"></i>Resumo da Análise
    </h5>
    <small class="text-muted">Período: <?= html_escape($v2['periodo'] ?? '') ?></small>

    <?php $vg = $v2['visao_geral'] ?? array(); ?>
    <div class="av2-kpis">
        <div class="av2-kpi"><strong><?= (int) ($vg['entrevistas'] ?? 0) ?></strong><span>entrevistas analisadas</span></div>
        <div class="av2-kpi"><strong><?= (int) ($vg['aplicadores'] ?? 0) ?></strong><span>aplicadores</span></div>
        <div class="av2-kpi"><strong><?= (int) ($vg['locais'] ?? 0) ?></strong><span>locais</span></div>
    </div>

    <?php if (!empty($v2['resumo'])): ?>
    <div class="av2-resumo mt-3">
        <?php foreach (preg_split('/\n\s*\n/', trim($v2['resumo'])) as $paragrafo): ?>
            <p><?= nl2br(html_escape(trim($paragrafo))) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php
$blocos = array(
    array('destaques', 'Principais achados', 'fa-lightbulb', '#8fae5d'),
    array('pontos_de_atencao', 'Pontos de atenção', 'fa-exclamation-triangle', '#f0ad4e'),
    array('recomendacoes', 'Recomendações', 'fa-check-circle', '#23345F'),
);
$com_conteudo = array_filter($blocos, function ($b) use ($v2) { return !empty($v2[$b[0]]); });
?>
<?php if ($com_conteudo): ?>
<div class="row g-3 mb-4">
    <?php foreach ($com_conteudo as $b): ?>
    <div class="col-lg-<?= count($com_conteudo) === 3 ? 4 : 6 ?>">
        <div class="av2-card">
            <h6><i class="fas <?= $b[2] ?> me-2" style="color: <?= $b[3] ?>;"></i><?= $b[1] ?></h6>
            <ul>
                <?php foreach ($v2[$b[0]] as $item): ?>
                    <li><?= html_escape($item) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($secao === 'outras' && !empty($v2['outras_perguntas'])): ?>
<h5 class="mt-2 mb-3" style="color: var(--secondary-color); font-weight: 600;">
    <i class="fas fa-comments me-2" style="color: var(--primary-color);"></i>Outras perguntas
</h5>
<div class="row g-3 mb-4">
    <?php foreach ($v2['outras_perguntas'] as $op): ?>
    <div class="col-lg-6">
        <div class="av2-card p-3">
            <div style="font-weight: 600; color: var(--secondary-color);"><?= html_escape($op['pergunta'] ?? '') ?></div>
            <?php if (!empty($op['detalhe'])): ?>
                <small class="text-muted d-block mt-1"><?= html_escape($op['detalhe']) ?></small>
            <?php endif; ?>
            <p class="mb-0 mt-2" style="line-height: 1.6; color: #333;"><?= html_escape($op['analise'] ?? '') ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
