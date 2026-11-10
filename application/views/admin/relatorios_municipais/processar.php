<?php
/**
 * @var object $r
 * @var array  $eixos
 * @var array  $fontes      execuções anteriores (report_sources)
 * @var array  $conectores  ['js' => arquivos, 'php' => [chave, rotulo]]
 * @var array  $contexto    dados do município para os conectores
 * @var array  $csrf
 */
$this->load->view('admin/relatorios_municipais/_estilos');
$sem_conectores = empty($conectores['js']) && empty($conectores['php']);
$em_processamento = $r->status === 'processing';
?>
<style>
    .rm-fonte-linha { display: flex; align-items: center; gap: .75rem; padding: .7rem 0; border-bottom: 1px solid #eef0f2; }
    .rm-fonte-linha:last-child { border-bottom: 0; }
    .rm-fonte-status { width: 26px; text-align: center; flex-shrink: 0; }
</style>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h2 class="mb-1">Processamento dos dados</h2>
        <div class="text-muted"><?= html_escape($r->municipio_nome) ?> - <?= html_escape($r->uf) ?> · IBGE <?= (int) $r->codigo_ibge ?> · <?= html_escape($r->periodo_referencia) ?></div>
    </div>
    <div><?= relatorio_status_badge($r->status) ?></div>
</div>

<div class="rm-passos">
    <div class="rm-passo feito"><div class="num">1</div><div>Município</div></div>
    <div class="rm-passo feito"><div class="num">2</div><div>Fontes de dados</div></div>
    <div class="rm-passo ativo"><div class="num">3</div><div>Processamento</div></div>
    <div class="rm-passo"><div class="num">4</div><div>Revisão</div></div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="rm-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Consulta às fontes</h6>
                <?php if (!$sem_conectores): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-consultar">
                        <i class="fas fa-sync-alt me-1"></i><span>Consultar fontes</span>
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($sem_conectores): ?>
                <div class="alert alert-light border mb-0">
                    <i class="fas fa-plug me-1"></i>
                    Nenhuma integração com APIs configurada ainda. Preencha os dados na etapa
                    <a href="<?= base_url('relatorios-automatizados/dados/' . $r->id) ?>">Dados</a>
                    e conclua o processamento para seguir para a revisão.
                </div>
            <?php else: ?>
                <div id="lista-fontes">
                    <?php foreach ($fontes as $f): ?>
                        <div class="rm-fonte-linha" data-chave="<?= html_escape($f->conector) ?>">
                            <div class="rm-fonte-status"></div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold rotulo"><?= html_escape($f->rotulo ?: $f->conector) ?></div>
                                <div class="small text-muted detalhe"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="small text-muted mt-2" id="aviso-inicial">
                    <?= empty($fontes) ? 'Clique em "Consultar fontes" para buscar os dados.' : 'Resultado da última consulta.' ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="d-flex justify-content-between flex-wrap gap-2 mb-4">
            <a href="<?= base_url('relatorios-automatizados/dados/' . $r->id) ?>" class="btn btn-outline-secondary">
                <i class="fas fa-table me-1"></i>Conferir e complementar dados
            </a>
            <?php if ($em_processamento): ?>
                <?= form_open('relatorios-automatizados/concluir_processamento/' . $r->id) ?>
                    <button type="submit" class="btn btn-success" id="btn-concluir">
                        <i class="fas fa-check me-1"></i>Concluir processamento e revisar
                    </button>
                <?= form_close() ?>
            <?php else: ?>
                <a href="<?= base_url('relatorios-automatizados/revisar/' . $r->id) ?>" class="btn btn-success">
                    <i class="fas fa-user-check me-1"></i>Ir para a revisão
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="rm-card">
            <h6 class="mb-2">Eixos deste relatório</h6>
            <ul class="list-unstyled small mb-0">
                <?php foreach ($r->eixos_lista as $e): ?>
                    <li class="py-1"><i class="fas <?= $eixos[$e]['icone'] ?? 'fa-file' ?> me-2 text-secondary"></i><?= html_escape($eixos[$e]['titulo'] ?? $e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if (!$em_processamento): ?>
            <div class="rm-card">
                <h6 class="mb-2">Consultar de novo</h6>
                <p class="small text-muted">A nova consulta atualiza os dados; o que já foi revisado é mantido.</p>
                <?= form_open('relatorios-automatizados/reprocessar/' . $r->id) ?>
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-redo me-1"></i>Reabrir processamento</button>
                <?= form_close() ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$sem_conectores && $em_processamento): ?>
<script src="<?= base_url('assets/js/relatorios/conectores.js') ?>"></script>
<?php foreach ($conectores['js'] as $arquivo): ?>
<script src="<?= base_url('assets/js/relatorios/conectores/' . rawurlencode($arquivo)) ?>"></script>
<?php endforeach; ?>
<script>
(function () {
    var lista = document.getElementById('lista-fontes');
    var botao = document.getElementById('btn-consultar');
    var concluir = document.getElementById('btn-concluir');
    var ICONES = {
        executando: '<span class="spinner-border spinner-border-sm text-primary"></span>',
        ok: '<i class="fas fa-check-circle text-success"></i>',
        error: '<i class="fas fa-times-circle text-danger"></i>'
    };

    function linha(chave, rotulo) {
        var el = lista.querySelector('[data-chave="' + CSS.escape(chave) + '"]');
        if (!el) {
            el = document.createElement('div');
            el.className = 'rm-fonte-linha';
            el.dataset.chave = chave;
            el.innerHTML = '<div class="rm-fonte-status"></div><div class="flex-grow-1"><div class="fw-semibold rotulo"></div><div class="small text-muted detalhe"></div></div>';
            lista.appendChild(el);
        }
        if (rotulo) el.querySelector('.rotulo').textContent = rotulo;
        return el;
    }

    function atualizar(chave, estado) {
        var el = linha(chave, estado.rotulo);
        el.querySelector('.rm-fonte-status').innerHTML = ICONES[estado.status] || '';
        var det = el.querySelector('.detalhe');
        if (estado.status === 'executando') det.textContent = 'Consultando...';
        else if (estado.status === 'ok') {
            var txt = (estado.linhas || 0) + ' valor(es) gravado(s)';
            if (estado.avisos && estado.avisos.length) txt += ' · ' + estado.avisos.length + ' recusado(s): ' + estado.avisos.slice(0, 3).join(' ');
            if (estado.mensagem) txt += ' · ' + estado.mensagem;
            det.textContent = txt;
        } else det.textContent = 'Falha: ' + (estado.mensagem || 'erro desconhecido');
    }

    function consultar() {
        botao.disabled = true;
        if (concluir) concluir.disabled = true;
        botao.querySelector('span').textContent = 'Consultando...';
        document.getElementById('aviso-inicial').textContent = 'Consultando as fontes. Não feche esta página.';

        SXConectores.executarTodos({
            relatorio: <?= json_encode($contexto, JSON_UNESCAPED_UNICODE) ?>,
            urls: {
                receber: <?= json_encode(base_url('relatorios-automatizados/receber/' . $r->id)) ?>,
                fonte: <?= json_encode(base_url('relatorios-automatizados/fonte/' . $r->id)) ?>,
                servidor: <?= json_encode(empty($conectores['php']) ? null : base_url('relatorios-automatizados/executar_servidor/' . $r->id)) ?>
            },
            csrf: <?= json_encode($csrf) ?>,
            aoAtualizar: atualizar
        }).then(function (n) {
            document.getElementById('aviso-inicial').textContent = 'Consulta concluída. Confira os dados e conclua o processamento.';
        }).catch(function (e) {
            document.getElementById('aviso-inicial').textContent = 'A consulta foi interrompida: ' + e.message;
        }).finally(function () {
            botao.disabled = false;
            if (concluir) concluir.disabled = false;
            botao.querySelector('span').textContent = 'Consultar de novo';
        });
    }

    botao.addEventListener('click', consultar);
    // primeira vez: consulta ao abrir (o comentário fica fora do bloco PHP: o ?>
    // engole a quebra de linha e comentaria a linha seguinte)
    <?php if (empty($fontes)): ?>consultar();<?php endif; ?>
})();
</script>
<?php endif; ?>

<?php if (!empty($fontes)): ?>
<script>
// Status da última execução registrada no servidor
(function () {
    var ICONES = { ok: '<i class="fas fa-check-circle text-success"></i>', error: '<i class="fas fa-times-circle text-danger"></i>', pending: '<i class="far fa-circle text-secondary"></i>' };
    var fontes = <?= json_encode(array_map(function ($f) {
        return ['chave' => $f->conector, 'status' => $f->status, 'linhas' => (int) $f->linhas, 'mensagem' => $f->mensagem,
                'quando' => $f->executado_em ? date('d/m/Y H:i', strtotime($f->executado_em)) : ''];
    }, $fontes), JSON_UNESCAPED_UNICODE) ?>;
    fontes.forEach(function (f) {
        var el = document.querySelector('.rm-fonte-linha[data-chave="' + CSS.escape(f.chave) + '"]');
        if (!el) return;
        el.querySelector('.rm-fonte-status').innerHTML = ICONES[f.status] || '';
        el.querySelector('.detalhe').textContent = f.status === 'error'
            ? 'Falha: ' + (f.mensagem || '') + (f.quando ? ' (' + f.quando + ')' : '')
            : f.linhas + ' valor(es)' + (f.mensagem ? ' · ' + f.mensagem : '') + (f.quando ? ' · ' + f.quando : '');
    });
})();
</script>
<?php endif; ?>
