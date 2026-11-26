<?php defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ia_provedores.php';
$opcoes = function ($sel) use ($modelos, $chaves) {
    $h = '';
    foreach ($modelos as $p => $lista) {
        $sem = empty($chaves[$p]['origem']) || $chaves[$p]['origem'] === 'ilegivel';
        $h .= '<optgroup label="' . html_escape(Ia_provedores::nome($p) . ($sem ? ' (sem chave)' : '')) . '">';
        foreach ($lista as $m) { $h .= '<option value="' . html_escape($m) . '"' . ($m === $sel ? ' selected' : '') . '>' . html_escape($m) . '</option>'; }
        $h .= '</optgroup>';
    }
    return $h;
};
?>
<div class="container-fluid">
    <a href="<?= base_url('ai/provedores') ?>" class="small"><i class="fas fa-arrow-left"></i> Provedores e custos</a>
    <h1 class="h3 mb-0">Comparar modelos</h1>
    <p class="text-muted">O mesmo pedido em dois modelos, lado a lado, com tempo, tokens e custo. Os dados pessoais são retirados antes do envio, e cada execução entra no log de IA e no gasto do mês.</p>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label small mb-0">Instrução de sistema (opcional)</label>
                    <?php if ($prompts): ?>
                    <select class="form-select form-select-sm mb-1" onchange="if (this.value !== '') { var p = PROMPTS[this.value]; document.getElementById('sistema').value = p.s || ''; document.getElementById('texto').value = p.u || ''; }">
                        <option value="">Usar um prompt cadastrado…</option>
                        <?php foreach (array_values((array) $prompts) as $i => $p): $p = (array) $p; ?><option value="<?= $i ?>"><?= html_escape(($p['feature_key'] ?? '') . ' · ' . ($p['prompt_name'] ?? '')) ?></option><?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <textarea id="sistema" class="form-control form-control-sm" rows="4" placeholder="Ex.: Você é um analista de pesquisas socioeconômicas. Responda em português."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-0">Pedido</label>
                    <textarea id="texto" class="form-control form-control-sm" rows="6" placeholder="Cole aqui o texto ou os dados a analisar."></textarea>
                </div>
            </div>
            <div class="row g-2 mt-1 align-items-end">
                <div class="col-md-3"><label class="form-label small mb-0">Modelo A</label><select id="modeloA" class="form-select form-select-sm"><?= $opcoes('gpt-4o') ?></select></div>
                <div class="col-md-3"><label class="form-label small mb-0">Modelo B</label><select id="modeloB" class="form-select form-select-sm"><?= $opcoes('claude-sonnet-5-5') ?></select></div>
                <div class="col-md-2"><label class="form-label small mb-0">Máx. tokens</label><input id="max" type="number" class="form-control form-control-sm" value="2000" min="200" max="8000"></div>
                <div class="col-md-2"><label class="form-label small mb-0">Esforço (Claude)</label>
                    <select id="esforco" class="form-select form-select-sm"><option value="">Padrão</option><option value="low">Baixo</option><option value="medium">Médio</option><option value="high">Alto</option></select></div>
                <div class="col-md-2"><button id="btn" class="btn btn-primary btn-sm w-100" onclick="comparar()"><i class="fas fa-play me-1"></i> Comparar</button></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach (['A', 'B'] as $l): ?>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between"><strong id="titulo<?= $l ?>">Modelo <?= $l ?></strong><span class="small text-muted" id="meta<?= $l ?>"></span></div>
                <div class="card-body"><div id="saida<?= $l ?>" style="white-space: pre-wrap; min-height: 120px" class="small text-muted">O resultado aparece aqui.</div></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
var PROMPTS = <?= json_encode(array_map(function ($p) { $p = (array) $p; return ['s' => $p['system_prompt'] ?? '', 'u' => $p['user_prompt_template'] ?? '']; }, array_values((array) $prompts)), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
function rodar(lado) {
    var modelo = document.getElementById('modelo' + lado).value;
    var saida = document.getElementById('saida' + lado), meta = document.getElementById('meta' + lado);
    document.getElementById('titulo' + lado).textContent = modelo;
    saida.textContent = 'gerando…'; saida.className = 'small text-muted'; meta.textContent = '';
    var f = new FormData();
    f.append('modelo', modelo);
    f.append('sistema', document.getElementById('sistema').value);
    f.append('texto', document.getElementById('texto').value);
    f.append('max_tokens', document.getElementById('max').value);
    f.append('esforco', document.getElementById('esforco').value);
    return fetch('<?= base_url('ai/comparar_executar') ?>', { method: 'POST', body: f, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (!j.success) { saida.textContent = j.error || 'Erro.'; saida.className = 'small text-danger'; return; }
            saida.textContent = j.content; saida.className = 'small';
            meta.textContent = (j.duration_ms / 1000).toFixed(1) + ' s · ' + j.tokens_input + ' + ' + j.tokens_output + ' tokens · US$ ' + Number(j.cost_usd).toFixed(4) + (j.warning ? ' · ' + j.warning : '');
        })
        .catch(function () { saida.textContent = 'Falha na comunicação (o pedido pode ter demorado mais de 100 s).'; saida.className = 'small text-danger'; });
}
function comparar() {
    if (!document.getElementById('texto').value.trim()) { alert('Escreva o pedido.'); return; }
    var b = document.getElementById('btn'); b.disabled = true;
    Promise.all([rodar('A'), rodar('B')]).then(function () { b.disabled = false; });
}
</script>
