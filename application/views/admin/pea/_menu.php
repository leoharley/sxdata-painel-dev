<?php defined('BASEPATH') OR exit('No direct script access allowed');
$m = $this->router->method;
$itens = ['index' => ['pea', 'Painel', 'fa-chart-line'], 'acoes' => ['pea/acoes', 'Ações', 'fa-calendar-check'], 'relatorios' => ['pea/relatorios', 'Relatórios', 'fa-file-signature'],
    'estrutura' => ['pea/estrutura', 'Estrutura e cronograma', 'fa-sitemap'], 'regras' => ['pea/regras', 'Regras de ponderação', 'fa-balance-scale'],
    'eieap' => ['pea/eieap', 'Percepção (EIEAP)', 'fa-seedling'], 'bancodoc' => ['pea/bancodoc', 'BancoDOC', 'fa-id-badge']];
$ativo = in_array($m, ['acao', 'nova', 'editar'], TRUE) ? 'acoes' : ($m === 'relatorio' ? 'relatorios' : $m);
if (!function_exists('pea_num')) {
    function pea_num($v, $casas = 1) { return $v === NULL || $v === '' ? '—' : number_format((float) $v, $casas, ',', '.'); }
}
?>
<style>.pea-nav .nav-link { padding: .35rem .75rem; } .pea-card .valor { font-size: 2rem; font-weight: 700; line-height: 1; }</style>
<div class="container-fluid d-print-none">
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <div><span class="h4 mb-0"><i class="fas fa-leaf text-success me-1"></i> PEA Gerdau</span>
            <?php if (!empty($ciclo)): ?><span class="text-muted small ms-2"><?= html_escape($ciclo->nome) ?></span><?php endif; ?></div>
        <ul class="nav nav-pills pea-nav small">
            <?php foreach ($itens as $k => $i): ?>
                <li class="nav-item"><a class="nav-link <?= $ativo === $k ? 'active' : '' ?>" href="<?= base_url($i[0]) ?>"><i class="fas <?= $i[2] ?> me-1"></i><?= $i[1] ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
