<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title><?= html_escape($r->titulo) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #222; max-width: 960px; margin: 0 auto; padding: 24px; }
        .capa { border-bottom: 4px solid #8fae5d; margin-bottom: 18px; padding-bottom: 10px; }
        .capa h1 { color: #23345F; font-size: 1.6rem; }
        table { page-break-inside: avoid; }
        <?php if ($r->status !== 'aprovado'): ?>
        body::before { content: 'RASCUNHO — NÃO APROVADO'; position: fixed; top: 40%; left: 5%; font-size: 64px; color: rgba(200, 0, 0, .12); transform: rotate(-25deg); z-index: -1; white-space: nowrap; }
        <?php endif; ?>
        @media print { .nao-imprimir { display: none; } body { padding: 0; } }
        @page { margin: 18mm 14mm; }
    </style>
</head>
<body>
    <div class="nao-imprimir mb-3"><button class="btn btn-primary btn-sm" onclick="window.print()">Imprimir / salvar em PDF</button></div>
    <div class="capa">
        <div class="small text-muted">Gerdau · Programa de Educação Ambiental — DN COPAM nº 238/2020</div>
        <h1><?= html_escape($r->titulo) ?></h1>
        <div class="small"><?= $r->status === 'aprovado' ? 'Aprovado em ' . date('d/m/Y', strtotime($r->aprovado_em)) : 'Versão preliminar, ainda não aprovada' ?> · elaborado pela SocialX no SXData</div>
    </div>
    <?php $this->load->view('admin/pea/_relatorio_corpo', ['r' => $r, 'regras' => $regras]); ?>
    <div class="small text-muted mt-4 border-top pt-2">Indicadores calculados pelo SXData com as regras de ponderação v<?= (int) $r->regra_versao ?>. Cada número pode ser rastreado até as respostas e às listas de presença das ações.</div>
</body>
</html>
