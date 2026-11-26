<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR Code — <?= html_escape($a->titulo) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #fff; text-align: center; padding: 3vh 2vw; font-family: 'Segoe UI', sans-serif; }
        h1 { font-size: clamp(1.4rem, 3.5vw, 2.6rem); color: #23345F; }
        .sub { font-size: clamp(1rem, 2vw, 1.4rem); color: #555; }
        #qr { display: inline-block; padding: 16px; border: 6px solid #8fae5d; border-radius: 18px; margin: 2vh 0; }
        .passo { font-size: clamp(1rem, 2.2vw, 1.5rem); }
        @media print { .nao-imprimir { display: none; } }
    </style>
</head>
<body>
    <h1>Avalie esta atividade do PEA</h1>
    <div class="sub"><?= html_escape($a->titulo) ?> · <?= html_escape($a->projeto) ?></div>
    <div id="qr"></div>
    <p class="passo">Aponte a câmera do celular para o código. São 10 perguntas, cerca de 3 minutos.<br><strong>É anônimo: não pedimos nome nem CPF.</strong></p>
    <p class="small text-muted text-break"><?= html_escape($url) ?></p>
    <div class="nao-imprimir mt-3">
        <button class="btn btn-primary" onclick="window.print()">Imprimir</button>
        <a class="btn btn-outline-secondary" href="<?= html_escape($url) ?>" target="_blank">Abrir o formulário (modo assistido)</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        var t = Math.min(window.innerWidth * 0.6, window.innerHeight * 0.5, 420);
        if (window.QRCode) { new QRCode(document.getElementById('qr'), { text: <?= json_encode($url) ?>, width: t, height: t, correctLevel: QRCode.CorrectLevel.M }); }
        else { document.getElementById('qr').textContent = <?= json_encode($url) ?>; }
    </script>
</body>
</html>
