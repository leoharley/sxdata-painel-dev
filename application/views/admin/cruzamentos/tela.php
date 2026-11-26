<?php defined('BASEPATH') OR exit('No direct script access allowed');
$cfg = $salva ? (json_decode($salva->config, TRUE) ?: []) : [];
$grupos = [];
foreach ($variaveis as $v) { $grupos[$v['grupo']][] = $v; }
$sel = function ($nome, $vazio, $so_numero = FALSE, $atual = NULL) use ($grupos, $cfg) {
    $h = '<select class="form-select form-select-sm" id="' . $nome . '">' . ($vazio !== NULL ? '<option value="">' . html_escape($vazio) . '</option>' : '');
    foreach ($grupos as $g => $lista) {
        $h .= '<optgroup label="' . html_escape($g) . '">';
        foreach ($lista as $v) {
            if ($so_numero && $v['tipo'] !== 'numero') { continue; }
            $s = (($atual !== NULL ? $atual : ($cfg[$nome] ?? '')) === $v['id']) ? ' selected' : '';
            $h .= '<option value="' . html_escape($v['id']) . '"' . $s . '>' . html_escape($v['rotulo']) . ($v['tipo'] === 'numero' ? ' (faixas)' : '') . '</option>';
        }
        $h .= '</optgroup>';
    }
    return $h . '</select>';
};
?>
<style>
    @media print {
        .sidebar, .navbar, .nao-imprimir, .alert-dismissible { display: none !important; }
        .main-content { margin: 0 !important; }
        .card { border: none !important; box-shadow: none !important; }
    }
    #tabela td, #tabela th { white-space: nowrap; }
    #tabela td.num { text-align: right; font-variant-numeric: tabular-nums; }
    .oculta { color: #999; font-style: italic; }
</style>
<div class="container-fluid">
    <div class="nao-imprimir"><a href="<?= base_url('cruzamentos') ?>" class="small"><i class="fas fa-arrow-left"></i> Cruzamento de variáveis</a></div>
    <h1 class="h3 mb-0"><?= html_escape($salva ? $salva->titulo : $q->title) ?></h1>
    <p class="text-muted mb-3"><?= html_escape($q->projeto ?: '') ?><?= $salva ? ' · ' . html_escape($q->title) : '' ?></p>

    <div class="card mb-3 nao-imprimir">
        <div class="card-body">
            <div class="row g-2">
                <?php if ($q->formato === 'estruturado'): ?>
                <div class="col-md-2"><label class="form-label small mb-0">Unidade de análise</label>
                    <select class="form-select form-select-sm" id="unidade" onchange="location.href='<?= base_url('cruzamentos/q/' . (int) $q->id) ?>?unidade=' + this.value">
                        <option value="domicilio" <?= $unidade === 'domicilio' ? 'selected' : '' ?>>Domicílio</option>
                        <option value="morador" <?= $unidade === 'morador' ? 'selected' : '' ?>>Morador</option>
                    </select></div>
                <?php endif; ?>
                <div class="col-md-<?= $q->formato === 'estruturado' ? 3 : 4 ?>"><label class="form-label small mb-0">Linhas</label><?= $sel('linha', NULL) ?></div>
                <div class="col-md-3"><label class="form-label small mb-0">Colunas</label><?= $sel('coluna', '(só a distribuição das linhas)') ?></div>
                <div class="col-md-3"><label class="form-label small mb-0">Medida numérica (média e mediana)</label><?= $sel('medida', '(nenhuma)', TRUE) ?></div>
                <div class="col-md-1"><label class="form-label small mb-0">Faixas</label><input type="number" id="faixas" class="form-control form-control-sm" min="2" max="10" value="<?= (int) ($cfg['faixas'] ?? 5) ?>"></div>
            </div>
            <div class="row g-2 mt-1 align-items-end">
                <div class="col-md-2"><label class="form-label small mb-0">De</label><input type="date" id="de" class="form-control form-control-sm" value="<?= html_escape($cfg['filtros']['de'] ?? '') ?>"></div>
                <div class="col-md-2"><label class="form-label small mb-0">Até</label><input type="date" id="ate" class="form-control form-control-sm" value="<?= html_escape($cfg['filtros']['ate'] ?? '') ?>"></div>
                <?php if (!$cliente): ?>
                <div class="col-md-2"><label class="form-label small mb-0">Entrevistador</label>
                    <select id="aplicador" class="form-select form-select-sm"><option value="">Todos</option>
                        <?php foreach ($aplicadores as $a): ?><option value="<?= (int) $a->id ?>" <?= (int) ($cfg['filtros']['aplicador'] ?? 0) === (int) $a->id ? 'selected' : '' ?>><?= html_escape($a->full_name) ?></option><?php endforeach; ?>
                    </select></div>
                <?php endif; ?>
                <div class="col-md-2"><label class="form-label small mb-0">Filtrar por</label><?= str_replace('id="filtro_var"', 'id="filtro_var" onchange="valoresFiltro()"', $sel("filtro_var", "(sem filtro)", FALSE, (string) ($cfg["filtros"]["var"] ?? ""))) ?></div>
                <div class="col-md-2"><label class="form-label small mb-0">igual a</label><select id="filtro_valor" class="form-select form-select-sm"></select></div>
                <div class="col-md-2">
                    <div class="form-check small"><input class="form-check-input" type="checkbox" id="vazios" <?= !empty($cfg['incluir_vazios']) ? 'checked' : '' ?>><label class="form-check-label" for="vazios">Incluir sem resposta</label></div>
                    <?php if (!$cliente): ?><div class="form-check small"><input class="form-check-input" type="checkbox" id="como_cliente" <?= !empty($cfg['como_cliente']) ? 'checked' : '' ?>><label class="form-check-label" for="como_cliente">Ver como o cliente (oculta &lt; <?= (int) $k ?>)</label></div><?php endif; ?>
                </div>
            </div>
            <div class="mt-2 d-flex flex-wrap gap-2">
                <button class="btn btn-primary btn-sm" onclick="calcular()"><i class="fas fa-table me-1"></i> Cruzar</button>
                <button class="btn btn-outline-success btn-sm" onclick="exportar('xlsx')"><i class="fas fa-file-excel me-1"></i> Excel</button>
                <button class="btn btn-outline-success btn-sm" onclick="exportar('csv')"><i class="fas fa-file-csv me-1"></i> CSV</button>
                <button class="btn btn-outline-secondary btn-sm" onclick="imagem()"><i class="fas fa-image me-1"></i> Imagem do gráfico</button>
                <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i> PDF / imprimir</button>
                <?php if (!$cliente): ?>
                <button class="btn btn-outline-primary btn-sm" onclick="interpretar()"><i class="fas fa-brain me-1"></i> Interpretar com IA</button>
                <button class="btn btn-outline-dark btn-sm" onclick="salvar()"><i class="fas fa-save me-1"></i> <?= $salva ? 'Atualizar análise' : 'Salvar análise' ?></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="resultado" style="display:none">
        <div class="card mb-3">
            <div class="card-body">
                <div id="resumo" class="small mb-2"></div>
                <div class="btn-group btn-group-sm mb-2 nao-imprimir" id="medidas">
                    <button class="btn btn-outline-secondary active" data-m="contagem">Contagem</button>
                    <button class="btn btn-outline-secondary" data-m="pct_linha">% na linha</button>
                    <button class="btn btn-outline-secondary" data-m="pct_coluna">% na coluna</button>
                    <button class="btn btn-outline-secondary" data-m="pct_total">% do total</button>
                    <button class="btn btn-outline-secondary d-none" data-m="media">Média</button>
                    <button class="btn btn-outline-secondary d-none" data-m="mediana">Mediana</button>
                </div>
                <div class="table-responsive"><table class="table table-sm table-bordered mb-0" id="tabela"></table></div>
                <div id="estatistica" class="small mt-2"></div>
            </div>
        </div>
        <div class="card mb-3"><div class="card-body"><canvas id="grafico" height="110"></canvas></div></div>
        <div class="card mb-3" id="card_interp" style="<?= $salva && $salva->interpretacao ? '' : 'display:none' ?>">
            <div class="card-header"><strong>Interpretação</strong> <span class="small text-muted" id="interp_modelo"></span></div>
            <div class="card-body">
                <?php if ($cliente): ?>
                    <div style="white-space: pre-wrap"><?= html_escape($salva->interpretacao ?? '') ?></div>
                <?php else: ?>
                    <textarea id="interpretacao" class="form-control" rows="6" placeholder="Texto da interpretação (revise antes de salvar)"><?= html_escape($salva->interpretacao ?? '') ?></textarea>
                    <p class="small text-muted mt-1 mb-0 nao-imprimir">Gerado por IA a partir da tabela; revise antes de salvar ou liberar ao cliente.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($salvas && !$salva): ?>
    <div class="card nao-imprimir">
        <div class="card-header"><strong>Análises salvas deste questionário</strong></div>
        <div class="list-group list-group-flush">
            <?php foreach ($salvas as $s): ?><a class="list-group-item list-group-item-action" href="?salva=<?= (int) $s->id ?>"><?= html_escape($s->titulo) ?> <small class="text-muted">· <?= date('d/m/Y', strtotime($s->atualizado_em)) ?></small></a><?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
var QID = <?= (int) $q->id ?>, SALVA = <?= $salva ? (int) $salva->id : 0 ?>, IA_LOG = 0;
var VARS = <?= json_encode(array_map(function ($v) { return ['tipo' => $v['tipo'], 'opcoes' => $v['opcoes']]; }, $variaveis), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
var FILTRO_VALOR = <?= json_encode((string) ($cfg['filtros']['valor'] ?? '')) ?>;
var R = null, MEDIDA = 'contagem', GRAF = null;
function $id(x) { return document.getElementById(x); }
function config() {
    var g = function (x) { var e = $id(x); return e ? e.value : ''; };
    return {
        unidade: g('unidade') || 'domicilio', linha: g('linha'), coluna: g('coluna'), medida: g('medida'), faixas: parseInt(g('faixas') || 5, 10),
        incluir_vazios: $id('vazios').checked, como_cliente: $id('como_cliente') ? $id('como_cliente').checked : false,
        filtros: { de: g('de'), ate: g('ate'), aplicador: g('aplicador'), var: g('filtro_var'), valor: g('filtro_valor') }
    };
}
function valoresFiltro() {
    var v = VARS[$id('filtro_var').value], s = $id('filtro_valor');
    s.innerHTML = '';
    if (!v) return;
    var op = v.opcoes || {};
    Object.keys(op).forEach(function (k) { var o = document.createElement('option'); o.value = k; o.textContent = op[k]; if (k === FILTRO_VALOR) o.selected = true; s.appendChild(o); });
    if (!Object.keys(op).length) { var o = document.createElement('option'); o.value = FILTRO_VALOR; o.textContent = FILTRO_VALOR || '(digite no Excel)'; s.appendChild(o); }
}
function post(url, dados) {
    var f = new FormData();
    Object.keys(dados).forEach(function (k) { f.append(k, dados[k]); });
    return fetch(url, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); });
}
function fmt(v, m) {
    if (v === null || v === undefined) return '<span class="oculta">&lt; ' + R.k_minimo + '</span>';
    if (m.indexOf('pct') === 0) return v.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
    if (m === 'media' || m === 'mediana') return Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 2 });
    return Number(v).toLocaleString('pt-BR');
}
function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function desenhar() {
    var r = R, m = MEDIDA, fonte = r[m] || r.contagem, h = '<thead class="table-light"><tr><th></th>';
    r.colunas.forEach(function (c) { h += '<th class="text-end">' + esc(c) + '</th>'; });
    var total = (m === 'contagem' || m === 'pct_linha');
    if (total && r.colunas.length > 1) h += '<th class="text-end">Total</th>';
    h += '</tr></thead><tbody>';
    r.linhas.forEach(function (l, i) {
        h += '<tr><th>' + esc(l) + '</th>';
        r.colunas.forEach(function (c, j) { h += '<td class="num">' + (fonte[i] ? fmt(fonte[i][j], m) : '') + '</td>'; });
        if (total && r.colunas.length > 1) h += '<td class="num fw-semibold">' + (m === 'contagem' ? fmt(r.total_linha[i], m) : '100,0%') + '</td>';
        h += '</tr>';
    });
    if (m === 'contagem' || m === 'pct_coluna') {
        h += '<tr class="table-light"><th>Total</th>';
        r.colunas.forEach(function (c, j) { h += '<td class="num fw-semibold">' + (m === 'contagem' ? fmt(r.total_coluna[j], m) : '100,0%') + '</td>'; });
        if (m === 'contagem' && r.colunas.length > 1) h += '<td class="num fw-bold">' + fmt(r.total, m) + '</td>';
        h += '</tr>';
    }
    $id('tabela').innerHTML = h + '</tbody>';
    document.querySelectorAll('#medidas button').forEach(function (b) { b.classList.toggle('active', b.dataset.m === m); });
    grafico();
}
function grafico() {
    var r = R, cores = ['#23345F', '#8fae5d', '#f0ad4e', '#5bc0de', '#d9534f', '#6f42c1', '#20c997', '#fd7e14', '#6c757d', '#e83e8c'];
    var usa = r.colunas.length > 1 ? 'pct_linha' : 'contagem';
    var ds = r.colunas.map(function (c, j) {
        return { label: c, backgroundColor: cores[j % cores.length], data: r.linhas.map(function (l, i) { var v = r[usa][i] ? r[usa][i][j] : null; return v === null ? 0 : v; }) };
    });
    if (GRAF) GRAF.destroy();
    GRAF = new Chart($id('grafico'), {
        type: 'bar', data: { labels: r.linhas, datasets: ds },
        options: { indexAxis: r.linhas.length > 6 ? 'y' : 'x', responsive: true, animation: false,
            plugins: { title: { display: true, text: r.colunas.length > 1 ? '% na linha' : 'Contagem' }, legend: { display: r.colunas.length > 1 } },
            scales: { x: { stacked: r.colunas.length > 1 }, y: { stacked: r.colunas.length > 1 } } }
    });
}
function calcular() {
    $id('resultado').style.display = 'none';
    return post('<?= base_url('cruzamentos/calcular') ?>', { q: QID, c: JSON.stringify(config()) }).then(function (j) {
        if (!j.success) { alert(j.error || 'Não foi possível cruzar.'); return; }
        R = j;
        var v = j.variaveis, res = '<strong>' + esc(v.linha) + '</strong>' + (v.coluna ? ' × <strong>' + esc(v.coluna) + '</strong>' : '');
        if (v.filtro) res += ' · filtro: ' + esc(v.filtro);
        res += '<br>Base: ' + j.base + (j.unidade === 'morador' ? ' moradores' : ' entrevistas') + ' · ' + j.n + ' com resposta' + (j.multipla ? ' · <span class="text-warning">múltipla escolha: os números são menções</span>' : '');
        if (j.suprimidas) res += ' · <span class="text-muted">' + j.suprimidas + ' célula(s) com menos de ' + j.k_minimo + ' respostas ocultas</span>';
        $id('resumo').innerHTML = res;
        document.querySelectorAll('#medidas [data-m=media], #medidas [data-m=mediana]').forEach(function (b) { b.classList.toggle('d-none', !j.media); });
        var e = '';
        if (j.qui) {
            var p = j.qui.p < 0.001 ? '&lt; 0,001' : j.qui.p.toLocaleString('pt-BR', { maximumFractionDigits: 3 });
            e = 'Qui-quadrado = ' + j.qui.qui2.toLocaleString('pt-BR', { maximumFractionDigits: 2 }) + ' · gl = ' + j.qui.gl + ' · p = ' + p + ' · V de Cramér = ' + j.qui.v_cramer.toLocaleString('pt-BR', { maximumFractionDigits: 3 })
                + ' — ' + (j.qui.significativo ? '<strong>há associação estatisticamente significativa (5%)</strong>' : 'sem associação significativa (5%)');
            if (j.qui.aviso) e += '<br><span class="text-warning"><i class="fas fa-exclamation-triangle"></i> ' + esc(j.qui.aviso) + '</span>';
        } else if (j.multipla && j.colunas.length > 1) {
            e = '<span class="text-muted">O qui-quadrado não se aplica a perguntas de múltipla escolha.</span>';
        }
        $id('estatistica').innerHTML = e;
        if (MEDIDA !== 'contagem' && !j[MEDIDA]) MEDIDA = 'contagem';
        $id('resultado').style.display = '';
        desenhar();
    });
}
document.querySelectorAll('#medidas button').forEach(function (b) { b.onclick = function () { MEDIDA = b.dataset.m; if (R) desenhar(); }; });
function exportar(f) { location.href = '<?= base_url('cruzamentos/exportar') ?>?formato=' + f + '&q=' + QID + '&c=' + encodeURIComponent(JSON.stringify(config())); }
function imagem() {
    if (!GRAF) { alert('Faça o cruzamento primeiro.'); return; }
    var a = document.createElement('a'); a.href = GRAF.toBase64Image('image/png', 1); a.download = 'cruzamento_' + QID + '.png'; a.click();
}
function interpretar() {
    var c = $id('card_interp'), t = $id('interpretacao');
    c.style.display = ''; t.value = 'Gerando interpretação…';
    post('<?= base_url('cruzamentos/interpretar') ?>', { q: QID, c: JSON.stringify(config()) }).then(function (j) {
        if (!j.success) { t.value = ''; alert(j.error || 'A IA não respondeu.'); return; }
        t.value = j.texto; IA_LOG = j.log_id || 0; $id('interp_modelo').textContent = '(' + j.modelo + ')';
    });
}
function salvar() {
    var titulo = prompt('Título da análise:', <?= json_encode($salva ? $salva->titulo : '') ?>);
    if (titulo === null) return;
    post('<?= base_url('cruzamentos/salvar') ?>', { id: SALVA, q: QID, c: JSON.stringify(config()), titulo: titulo, interpretacao: $id('interpretacao') ? $id('interpretacao').value : '', ia_log_id: IA_LOG })
        .then(function (j) { if (j.success) { location.href = '<?= base_url('cruzamentos/q/' . (int) $q->id) ?>?salva=' + j.id; } else { alert('Não foi possível salvar.'); } });
}
valoresFiltro();
<?php if ($salva): ?>calcular();<?php endif; ?>
</script>
