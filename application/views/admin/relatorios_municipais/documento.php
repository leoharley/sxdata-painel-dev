<?php
/**
 * Documento do relatório municipal (leitura, impressão e PDF).
 *
 * @var array $conteudo   conteúdo montado (prévia) ou congelado (versão publicada)
 * @var bool  $publicado
 * @var bool  $equipe
 * @var bool  $visualizador  prévia embutida na Aprovação final: miniaturas,
 *                           navegação por página e zoom
 */
$c = $conteudo;
$vis = !empty($visualizador);
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$versao = isset($c['versao']) ? 'v' . (int) $c['versao'] . '.0' : 'Prévia';
$data_ref = !empty($c['aprovado_em']) ? $c['aprovado_em'] : $c['gerado_em'];
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Diagnóstico Municipal <?= $e($c['periodo']) ?> - <?= $e($c['municipio']) ?> - <?= $e($c['uf']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --verde: #8fae5d; --verde-escuro: #5a7536; --azul: #23345F; --cinza: #6b7280; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", Arial, sans-serif; color: #1f2937; background: #e9ecef; font-size: 10.5pt; line-height: 1.5; }
        .barra { position: sticky; top: 0; z-index: 10; background: var(--azul); color: #fff; padding: 10px 16px;
                 display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .barra button { background: var(--verde); color: #fff; border: 0; border-radius: 6px; padding: 8px 14px; font-weight: 600; cursor: pointer; }
        .aviso-previa { background: #fff3cd; color: #664d03; text-align: center; padding: 8px; font-size: 9.5pt; }
        .pagina { background: #fff; width: 210mm; min-height: 297mm; margin: 16px auto; padding: 18mm 16mm; position: relative; overflow: hidden;
                  box-shadow: 0 2px 10px rgba(0,0,0,.12); }

        /* Capa */
        .capa { display: flex; flex-direction: column; justify-content: center; }
        .capa .forma1 { position: absolute; top: 0; right: 0; width: 0; height: 0; border-top: 190px solid var(--azul); border-left: 190px solid transparent; }
        .capa .forma2 { position: absolute; top: 40px; right: 40px; width: 70px; height: 70px; background: var(--verde); transform: rotate(45deg); opacity: .9; }
        .capa .forma3 { position: absolute; bottom: 0; left: 0; width: 0; height: 0; border-bottom: 120px solid var(--verde); border-right: 260px solid transparent; opacity: .35; }
        .capa img { width: 210px; margin-bottom: 48px; }
        .capa h1 { font-size: 34pt; color: var(--azul); margin: 0; line-height: 1.1; }
        .capa .ano { font-size: 34pt; color: var(--verde); font-weight: 700; margin: 0 0 18px; }
        .capa .local { font-size: 18pt; color: #374151; }
        .capa .linha { width: 120px; height: 4px; background: var(--verde); margin: 28px 0; }
        .capa .meta { color: var(--cinza); font-size: 10pt; }

        h2.titulo-secao { color: var(--azul); font-size: 17pt; margin: 0 0 6px; display: flex; align-items: center; gap: 10px; }
        h2.titulo-secao i { width: 36px; height: 36px; border-radius: 50%; background: var(--verde); color: #fff; display: inline-flex;
                            align-items: center; justify-content: center; font-size: 13pt; }
        .faixa { height: 3px; background: linear-gradient(90deg, var(--verde), transparent); margin-bottom: 14px; }
        .texto { text-align: justify; margin-bottom: 14px; }
        .kpis { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
        .kpi { flex: 1 1 150px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; }
        .kpi .valor { font-size: 17pt; font-weight: 700; color: var(--azul); line-height: 1.1; }
        .kpi .rotulo { font-size: 9pt; color: #374151; }
        .kpi .fonte, .grafico .fonte, .qualitativo .fonte { font-size: 8pt; color: var(--cinza); }
        .graficos { display: flex; flex-wrap: wrap; gap: 14px; }
        .grafico { flex: 1 1 calc(50% - 7px); min-width: 260px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; break-inside: avoid; }
        .grafico h3 { font-size: 11pt; color: var(--azul); margin: 0 0 8px; }
        .grafico .area { position: relative; height: 230px; }
        .grafico .interp { margin-top: 8px; font-size: 9.5pt; background: #f4f8ee; border-radius: 6px; padding: 7px 9px; }
        .qualitativo { border-left: 3px solid var(--verde); background: #f8faf6; padding: 8px 12px; margin-bottom: 10px; border-radius: 4px; }
        .qualitativo strong { color: var(--azul); }
        .sumario li { padding: 6px 0; border-bottom: 1px dotted #ced4da; list-style: none; display: flex; justify-content: space-between; }
        .sumario ol { padding: 0; }
        .rodape-pagina { position: absolute; bottom: 10mm; left: 16mm; right: 16mm; font-size: 8pt; color: var(--cinza);
                         display: flex; justify-content: space-between; border-top: 1px solid #eef0f2; padding-top: 4px; }
        .fontes li { margin-bottom: 4px; }

        @page { size: A4; margin: 0; }
        @media print {
            body { background: #fff; }
            .barra, .aviso-previa { display: none; }
            .pagina { margin: 0; box-shadow: none; width: auto; min-height: 297mm; page-break-after: always; }
            .pagina:last-child { page-break-after: auto; }
        }
        @media (max-width: 230mm) {
            body:not(.vis) .pagina { width: auto; min-height: 0; margin: 8px; padding: 20px 16px 50px; }
            body:not(.vis) .grafico { flex-basis: 100%; }
        }

        /* Visualizador (F05): folhas A4 em escala, miniaturas e navegação */
        body.vis { height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
        .vis-barra { display: flex; align-items: center; gap: 4px; padding: 6px 10px; background: #fff; border-bottom: 1px solid #dee2e6;
                     font-size: 10pt; color: #374151; flex-wrap: wrap; }
        .vis-barra button { background: none; border: 1px solid transparent; border-radius: 6px; padding: 4px 9px; cursor: pointer; color: #374151; font-size: 10pt; }
        .vis-barra button:hover { background: #f1f3f5; border-color: #e5e7eb; }
        .vis-barra button.ativo { color: var(--verde-escuro); }
        .vis-barra input { width: 46px; text-align: center; border: 1px solid #ced4da; border-radius: 5px; padding: 3px; font-size: 10pt; }
        .vis-barra .zoom { min-width: 48px; text-align: center; }
        .vis-sep { width: 1px; height: 20px; background: #dee2e6; margin: 0 6px; }
        .vis-espaco { flex: 1; }
        .vis-corpo { flex: 1; display: flex; min-height: 0; }
        .vis-miniaturas { width: 158px; flex-shrink: 0; overflow-y: auto; background: #f8f9fa; border-right: 1px solid #dee2e6; padding: 12px 0; }
        .vis-miniaturas.oculto { display: none; }
        .vis-mini { display: block; width: 100%; background: none; border: 0; cursor: pointer; margin-bottom: 12px; font-size: 8pt; color: #6b7280; text-align: center; }
        .vis-mini .quadro { width: 113px; height: 160px; margin: 0 auto 4px; position: relative; overflow: hidden; background: #fff;
                            border: 2px solid transparent; box-shadow: 0 1px 4px rgba(0,0,0,.18); }
        .vis-mini.ativa .quadro { border-color: var(--verde); }
        .vis-mini.ativa { color: var(--verde-escuro); font-weight: 600; }
        .vis-mini .quadro > .pagina { position: absolute; top: 0; left: 0; margin: 0; box-shadow: none; transform-origin: top left; pointer-events: none; }
        .vis-area { flex: 1; overflow: auto; }
        .vis-escala { margin: 0 auto; }
        body.vis #paginas { width: 210mm; position: relative; transform-origin: top left; }
        body.vis #paginas .pagina { margin: 16px 0; }
    </style>
    <?php if ($vis): ?>
    <style>
        @media print {
            body.vis { height: auto; overflow: visible; display: block; }
            .vis-barra, .vis-miniaturas { display: none !important; }
            .vis-corpo, .vis-area { display: block; overflow: visible; }
            .vis-escala { width: auto !important; height: auto !important; }
            body.vis #paginas { transform: none !important; width: auto; }
            body.vis #paginas .pagina { margin: 0; }
        }
    </style>
    <?php endif; ?>
</head>
<body class="<?= $vis ? 'vis' : '' ?>">

<?php if ($vis): ?>
<div class="vis-barra" role="toolbar" aria-label="Navegação da prévia">
    <button type="button" id="vis-miniaturas" class="ativo" title="Mostrar ou ocultar miniaturas"><i class="fas fa-columns"></i></button>
    <span class="vis-sep"></span>
    <button type="button" id="vis-anterior" title="Página anterior"><i class="fas fa-arrow-up"></i></button>
    <input type="number" id="vis-pagina" min="1" value="1" aria-label="Página atual">
    <span>/ <span id="vis-total">1</span></span>
    <button type="button" id="vis-proxima" title="Próxima página"><i class="fas fa-arrow-down"></i></button>
    <span class="vis-sep"></span>
    <button type="button" id="vis-menos" title="Diminuir zoom"><i class="fas fa-minus"></i></button>
    <span class="zoom" id="vis-zoom">100%</span>
    <button type="button" id="vis-mais" title="Aumentar zoom"><i class="fas fa-plus"></i></button>
    <button type="button" id="vis-ajustar" title="Ajustar à largura"><i class="fas fa-arrows-alt-h"></i></button>
    <span class="vis-espaco"></span>
    <button type="button" onclick="window.print()" title="Baixar PDF / Imprimir"><i class="fas fa-download"></i></button>
    <button type="button" id="vis-tela-cheia" title="Tela cheia"><i class="fas fa-expand"></i></button>
</div>
<div class="vis-corpo">
<nav class="vis-miniaturas" id="vis-lista" aria-label="Miniaturas das páginas"></nav>
<div class="vis-area" id="vis-area"><div class="vis-escala" id="vis-escala">
<?php else: ?>
<div class="barra">
    <span><i class="fas fa-file-alt me-1"></i> Diagnóstico Municipal · <?= $e($c['municipio']) ?> - <?= $e($c['uf']) ?> · <?= $e($versao) ?></span>
    <button type="button" onclick="window.print()"><i class="fas fa-file-pdf"></i> Baixar PDF / Imprimir</button>
</div>
<?php if (!$publicado): ?>
    <div class="aviso-previa"><i class="fas fa-eye"></i> Prévia interna com o conteúdo aprovado até agora. Ainda não foi liberada para o cliente.</div>
<?php endif; ?>
<?php endif; ?>

<div id="paginas">
<!-- Capa -->
<section class="pagina capa">
    <div class="forma1"></div><div class="forma2"></div><div class="forma3"></div>
    <img src="<?= img_url('logo_socialx.png') ?>" alt="SocialX">
    <h1>Diagnóstico Municipal</h1>
    <div class="ano"><?= $e($c['periodo']) ?></div>
    <div class="local"><?= $e($c['municipio']) ?> - <?= $e($c['uf']) ?></div>
    <div class="linha"></div>
    <div class="meta">
        <?php if (!empty($c['projeto'])): ?><div>Projeto: <?= $e($c['projeto']) ?></div><?php endif; ?>
        <div>Código IBGE: <?= (int) $c['codigo_ibge'] ?></div>
        <div><?= $publicado ? 'Versão ' . $e($versao) . ' · aprovada em ' : 'Gerado em ' ?><?= date('d/m/Y', strtotime($data_ref)) ?></div>
    </div>
</section>

<!-- Sumário -->
<section class="pagina">
    <h2 class="titulo-secao"><i class="fas fa-list"></i>Sumário</h2>
    <div class="faixa"></div>
    <?php if (empty($c['secoes'])): ?>
        <p class="texto">Nenhuma seção aprovada ainda.</p>
    <?php else: ?>
        <ol class="sumario">
            <?php foreach ($c['secoes'] as $i => $s): ?>
                <li><span><?= $i + 1 ?>. <?= $e($s['titulo']) ?></span><span><?= count($s['graficos']) ?> gráfico(s)</span></li>
            <?php endforeach; ?>
            <li><span><?= count($c['secoes']) + 1 ?>. Fontes consultadas</span><span></span></li>
        </ol>
    <?php endif; ?>
    <p class="texto" style="margin-top: 24px; color: var(--cinza); font-size: 9.5pt;">
        As informações deste diagnóstico foram obtidas em bases públicas e, quando indicado, em bases locais do município,
        e foram conferidas pela equipe técnica antes da publicação. Fonte e período de referência são informados em cada indicador.
    </p>
    <div class="rodape-pagina"><span>SXData · SocialX</span><span><?= $e($c['municipio']) ?> - <?= $e($c['uf']) ?></span></div>
</section>

<?php foreach ($c['secoes'] as $i => $s): ?>
<section class="pagina">
    <h2 class="titulo-secao"><i class="fas <?= $e($s['icone']) ?>"></i><?= $i + 1 ?>. <?= $e($s['titulo']) ?></h2>
    <div class="faixa"></div>

    <?php if (trim($s['texto']) !== ''): ?>
        <div class="texto"><?= nl2br($e($s['texto'])) ?></div>
    <?php endif; ?>

    <?php if (!empty($s['kpis'])): ?>
        <div class="kpis">
            <?php foreach ($s['kpis'] as $k): ?>
                <div class="kpi">
                    <div class="valor"><?= $e($k['valor']) ?></div>
                    <div class="rotulo"><?= $e($k['rotulo']) ?><?= $k['unidade'] ? ' (' . $e($k['unidade']) . ')' : '' ?></div>
                    <div class="fonte"><?= $e($k['fonte']) ?> · <?= $e($k['periodo']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($s['qualitativos'] as $q): ?>
        <div class="qualitativo">
            <strong><?= $e($q['rotulo']) ?></strong>
            <div><?= nl2br($e($q['texto'])) ?></div>
            <div class="fonte"><?= $e($q['fonte']) ?><?= $q['periodo'] ? ' · ' . $e($q['periodo']) : '' ?></div>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($s['graficos'])): ?>
        <div class="graficos">
            <?php foreach ($s['graficos'] as $g): ?>
                <div class="grafico">
                    <h3><?= $e($g['titulo']) ?></h3>
                    <div class="area"><canvas data-grafico="<?= $e(json_encode($g['dados'], JSON_UNESCAPED_UNICODE)) ?>"></canvas></div>
                    <?php if (trim($g['texto']) !== ''): ?>
                        <div class="interp"><i class="fas fa-check-circle" style="color: var(--verde)"></i> <?= $e($g['texto']) ?></div>
                    <?php endif; ?>
                    <div class="fonte" style="margin-top: 6px;">
                        Fonte: <?= $e(implode('; ', $g['dados']['fontes']) ?: 'não informada') ?>
                        <?= $g['dados']['periodo'] !== '' ? ' · Referência: ' . $e($g['dados']['periodo']) : '' ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="rodape-pagina"><span>SXData · SocialX</span><span><?= $e($c['municipio']) ?> - <?= $e($c['uf']) ?> · <?= $e($versao) ?></span></div>
</section>
<?php endforeach; ?>

<?php if (!empty($c['secoes'])): ?>
<section class="pagina">
    <h2 class="titulo-secao"><i class="fas fa-book"></i><?= count($c['secoes']) + 1 ?>. Fontes consultadas</h2>
    <div class="faixa"></div>
    <ul class="fontes">
        <?php foreach ($c['fontes'] as $f): ?>
            <li><?= $e($f) ?></li>
        <?php endforeach; ?>
    </ul>
    <div class="rodape-pagina"><span>SXData · SocialX</span><span><?= $e($c['municipio']) ?> - <?= $e($c['uf']) ?> · <?= $e($versao) ?></span></div>
</section>
<?php endif; ?>
</div><!-- #paginas -->
<?php if ($vis): ?>
</div></div><!-- .vis-escala, .vis-area -->
</div><!-- .vis-corpo -->
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= base_url('assets/js/relatorios/graficos.js') ?>"></script>
<script>
// Sem animação: a impressão captura o gráfico já desenhado
SXGraficos.desenharTodos(document, { estatico: true });
// Redesenha no tamanho da folha ao imprimir
window.addEventListener('beforeprint', function () {
    document.querySelectorAll('canvas[data-grafico]').forEach(function (c) { if (c._sxChart) c._sxChart.resize(); });
});
</script>
<?php if ($vis): ?>
<script>
// Visualizador da prévia (F05): miniaturas, navegação por página e zoom
(function () {
    var area = document.getElementById('vis-area');
    var escala = document.getElementById('vis-escala');
    var conteudo = document.getElementById('paginas');
    var lista = document.getElementById('vis-lista');
    var paginas = Array.prototype.slice.call(conteudo.querySelectorAll(':scope > .pagina'));
    var campo = document.getElementById('vis-pagina');
    var rotuloZoom = document.getElementById('vis-zoom');
    var PASSOS = [0.5, 0.67, 0.75, 0.9, 1, 1.1, 1.25, 1.5, 1.75, 2];
    var zoom = 1;
    var atual = 0;

    document.getElementById('vis-total').textContent = paginas.length;
    campo.max = paginas.length;

    function larguraFolha() { return conteudo.offsetWidth; }

    function aplicarZoom(z, manterPagina) {
        var pagina = atual;
        zoom = Math.max(0.25, Math.min(3, z));
        conteudo.style.transform = 'scale(' + zoom + ')';
        escala.style.width = (larguraFolha() * zoom) + 'px';
        escala.style.height = (conteudo.offsetHeight * zoom) + 'px';
        rotuloZoom.textContent = Math.round(zoom * 100) + '%';
        if (manterPagina) irPara(pagina, true);
    }

    function ajustarLargura() {
        aplicarZoom((area.clientWidth - 32) / larguraFolha(), true);
    }

    function irPara(i, instantaneo) {
        i = Math.max(0, Math.min(paginas.length - 1, i));
        area.scrollTo({ top: paginas[i].offsetTop * zoom, behavior: instantaneo ? 'auto' : 'smooth' });
        marcar(i);
    }

    function marcar(i) {
        atual = i;
        campo.value = i + 1;
        lista.querySelectorAll('.vis-mini').forEach(function (m, k) {
            m.classList.toggle('ativa', k === i);
            if (k === i && !lista.classList.contains('oculto')) {
                var topo = m.offsetTop - lista.offsetTop;
                if (topo < lista.scrollTop || topo + m.offsetHeight > lista.scrollTop + lista.clientHeight) {
                    lista.scrollTop = topo - 12;
                }
            }
        });
    }

    // Página atual: a última cujo topo já passou de um terço da área visível
    area.addEventListener('scroll', function () {
        var linha = area.scrollTop + area.clientHeight / 3;
        var i = 0;
        paginas.forEach(function (p, k) { if (p.offsetTop * zoom <= linha) i = k; });
        if (i !== atual) marcar(i);
    });

    document.getElementById('vis-anterior').addEventListener('click', function () { irPara(atual - 1); });
    document.getElementById('vis-proxima').addEventListener('click', function () { irPara(atual + 1); });
    campo.addEventListener('change', function () { irPara((parseInt(campo.value, 10) || 1) - 1); });
    document.getElementById('vis-mais').addEventListener('click', function () {
        var prox = PASSOS.filter(function (p) { return p > zoom + 0.001; })[0];
        aplicarZoom(prox || 3, true);
    });
    document.getElementById('vis-menos').addEventListener('click', function () {
        var ant = PASSOS.filter(function (p) { return p < zoom - 0.001; }).pop();
        aplicarZoom(ant || 0.25, true);
    });
    document.getElementById('vis-ajustar').addEventListener('click', ajustarLargura);
    document.getElementById('vis-miniaturas').addEventListener('click', function () {
        var oculto = lista.classList.toggle('oculto');
        this.classList.toggle('ativo', !oculto);
    });

    var telaCheia = document.getElementById('vis-tela-cheia');
    if (!document.fullscreenEnabled) {
        telaCheia.style.display = 'none';
    } else {
        telaCheia.addEventListener('click', function () {
            if (document.fullscreenElement) document.exitFullscreen();
            else document.documentElement.requestFullscreen();
        });
        document.addEventListener('fullscreenchange', function () {
            telaCheia.querySelector('i').className = document.fullscreenElement ? 'fas fa-compress' : 'fas fa-expand';
        });
    }

    // Miniaturas: cópia de cada folha em escala, com os gráficos como imagem
    function montarMiniaturas() {
        lista.innerHTML = '';
        var fator = 113 / larguraFolha();
        paginas.forEach(function (p, i) {
            var copia = p.cloneNode(true);
            var originais = p.querySelectorAll('canvas');
            copia.querySelectorAll('canvas').forEach(function (cv, k) {
                var img = document.createElement('img');
                try { img.src = originais[k].toDataURL('image/png'); } catch (e) { /* gráfico ainda não desenhado */ }
                img.style.width = '100%';
                img.style.height = '100%';
                cv.parentNode.replaceChild(img, cv);
            });
            copia.removeAttribute('id');
            copia.style.transform = 'scale(' + fator + ')';
            copia.style.width = larguraFolha() + 'px';
            copia.setAttribute('aria-hidden', 'true');

            var botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'vis-mini' + (i === atual ? ' ativa' : '');
            botao.title = 'Ir para a página ' + (i + 1);
            var quadro = document.createElement('div');
            quadro.className = 'quadro';
            quadro.appendChild(copia);
            botao.appendChild(quadro);
            botao.appendChild(document.createTextNode(String(i + 1)));
            botao.addEventListener('click', function () { irPara(i); });
            lista.appendChild(botao);
        });
    }

    // Abre ajustado à largura, sem passar de 100%
    aplicarZoom(Math.min(1, (area.clientWidth - 32) / larguraFolha()), false);
    var redimensionar;
    window.addEventListener('resize', function () {
        clearTimeout(redimensionar);
        redimensionar = setTimeout(function () { aplicarZoom(zoom, true); }, 150);
    });
    // Os gráficos já estão desenhados (sem animação); o atraso cobre fontes e ícones
    setTimeout(function () { montarMiniaturas(); aplicarZoom(zoom, false); }, 400);
})();
</script>
<?php endif; ?>
</body>
</html>
