<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$fr = $cabecalho;
$def = $versao ? $versao->definicao : ['etapas' => []];
$motor = new Estrutura_motor($def);
$alertas = json_decode((string) $fr->alertas, TRUE) ?: [];
$revisao = json_decode((string) $fr->revisao_erros, TRUE) ?: [];
$nomes = [];
foreach ($moradores as $m) { $nomes[$m->client_id] = $m->nome; }

$linhas = function (array $perguntas, array $resps, $nome) {
    $h = ''; $bloco = NULL; $secao = NULL;
    foreach ($perguntas as $p) {
        if (!empty($p['oculta']) && $p['tipo'] !== 'calculado') { continue; }
        $r = $resps[$p['codigo']] ?? NULL;
        if ($r === NULL) { continue; }
        if (($p['bloco'] ?? '') !== '' && $p['bloco'] !== $bloco) { $bloco = $p['bloco']; $secao = NULL; $h .= '<tr class="table-primary"><th colspan="2">' . html_escape($bloco) . '</th></tr>'; }
        if (($p['secao'] ?? '') !== '' && $p['secao'] !== $secao) { $secao = $p['secao']; $h .= '<tr class="table-light"><th colspan="2" class="small">' . html_escape($secao) . '</th></tr>'; }
        $txt = Estrutura_motor::rotulo_resposta($p, $r);
        if ($p['tipo'] === 'foto' && ($r['valor'] ?? '') !== '') {
            $url = base_url('uploads/photos/' . rawurlencode(basename($r['valor'])));
            $txt = '<a href="' . $url . '" target="_blank"><img src="' . $url . '" style="max-height:140px" class="rounded border"></a>';
        } else {
            $txt = nl2br(html_escape($txt));
        }
        if (!empty($r['incompleto'])) { $txt .= ' <span class="badge bg-warning text-dark">incompleta (há NS/NR)</span>'; }
        $rotulo = ($p['numero'] !== '' ? $p['numero'] . '. ' : '') . Estrutura_motor::texto_para($p, $nome);
        $h .= '<tr><td class="w-50 small">' . html_escape($rotulo) . '</td><td>' . $txt . '</td></tr>';
    }
    return $h;
};
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <a href="<?= base_url('formularios-estruturados/entrevistas/' . (int) $fr->qid) ?>" class="small"><i class="fas fa-arrow-left"></i> Entrevistas</a>
            <h1 class="h3 mb-0"><?= html_escape($fr->formulario) ?> · entrevista #<?= (int) $fr->id ?></h1>
            <div class="text-muted small">
                Aplicador: <?= html_escape($fr->aplicador ?: '—') ?> ·
                <?= $fr->started_at ? 'Início ' . date('d/m/Y H:i', strtotime($fr->started_at)) . ' · ' : '' ?>
                Conclusão <?= $fr->completed_at ? date('d/m/Y H:i', strtotime($fr->completed_at)) : '—' ?> ·
                Versão do formulário: v<?= $versao ? (int) $versao->versao : '?' ?>
                <?php if ($fr->latitude && $fr->longitude): ?> ·
                    <a href="https://www.google.com/maps?q=<?= (float) $fr->latitude ?>,<?= (float) $fr->longitude ?>" target="_blank"><i class="fas fa-map-marker-alt"></i> <?= (float) $fr->latitude ?>, <?= (float) $fr->longitude ?></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-end">
            <?= $fr->revisao_status === 'pendente' ? '<span class="badge bg-warning text-dark fs-6">Na fila de revisão</span>' : '<span class="badge bg-success fs-6">Conferida</span>' ?>
            <?php $pode_excluir = $this->session->userdata('admin_role') === 'administrador' && $this->db->table_exists('lixeira'); if ($pode_excluir): ?>
            <?= form_open('controle/excluir_entrevista/' . (int) $fr->id, ['class' => 'd-inline ms-2', 'onsubmit' => "var m = prompt('Excluir esta entrevista (domicílio, moradores e repetições)? Ela vai para a Lixeira e pode ser restaurada.\\n\\nMotivo (opcional):', ''); if (m === null) return false; this.motivo.value = m; return true;"]) ?>
                <input type="hidden" name="motivo" value="">
                <input type="hidden" name="voltar" value="formularios-estruturados/entrevistas/<?= (int) $fr->qid ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i> Excluir</button>
            <?= form_close() ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($revisao['erros']) || !empty($revisao['divergencias'])): ?>
    <div class="alert alert-warning">
        <b>Pontos a revisar</b> (a entrevista foi gravada mesmo assim):
        <ul class="mb-0">
            <?php foreach ($revisao['erros'] ?? [] as $e): ?>
                <li><?= !empty($e['morador']) ? html_escape($nomes[$e['morador']] ?? 'Morador') . ': ' : '' ?><?= html_escape($e['mensagem']) ?></li>
            <?php endforeach; ?>
            <?php foreach ($revisao['divergencias'] ?? [] as $d): ?>
                <li>Cálculo de <?= html_escape($d['codigo']) ?> divergente: app <?= html_escape(json_encode($d['app'])) ?>, servidor <?= html_escape(json_encode($d['servidor'])) ?> (vale o do servidor).</li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    <?php if ($alertas): ?>
    <div class="alert alert-info"><b>Alertas</b><ul class="mb-0">
        <?php foreach ($alertas as $a): ?><li><?= !empty($a['morador']) ? html_escape($nomes[$a['morador']] ?? 'Morador') . ': ' : '' ?><?= html_escape($a['mensagem']) ?></li><?php endforeach; ?>
    </ul></div>
    <?php endif; ?>

    <?php if (!empty($entrevistado)): ?>
    <div class="alert alert-light border"><b>Entrevistado pré-cadastrado:</b> <?= html_escape($entrevistado->nome) ?>
        (código <?= html_escape($entrevistado->codigo) ?><?= $entrevistado->categoria ? ', ' . html_escape($entrevistado->categoria) : '' ?><?= $entrevistado->localidade ? ', ' . html_escape($entrevistado->localidade) : '' ?>)</div>
    <?php endif; ?>
    <?php foreach ($def['etapas'] as $i => $e): ?>
        <?php if ($e['tipo'] === 'moradores'): ?>
            <div class="card mb-3">
                <div class="card-header fw-semibold"><?= $e['numero'] ? html_escape($e['numero']) . '. ' : '' ?>Moradores (<?= count($moradores) ?>)</div>
                <ol class="list-group list-group-numbered list-group-flush">
                    <?php foreach ($moradores as $m): ?><li class="list-group-item"><?= html_escape($m->nome) ?><?= (int) $m->ordem === 1 ? ' <span class="badge bg-light text-dark border">respondente</span>' : '' ?></li><?php endforeach; ?>
                </ol>
            </div>
        <?php elseif ($e['nivel'] === 'grupo'): $itens = $repeticoes[$e['grupo']] ?? []; ?>
            <div class="card mb-3">
                <div class="card-header fw-semibold"><?= html_escape($e['titulo'] ?: $e['grupo']) ?> (<?= count($itens) ?>)</div>
                <?php if (!$itens): ?><div class="card-body text-muted small">Nenhum item.</div><?php endif; ?>
                <div class="accordion accordion-flush">
                    <?php foreach ($itens as $k => $it): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button <?= $k ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#g<?= $i ?>_<?= $k ?>"><?= html_escape($it->rotulo ?: ('Item ' . $it->indice)) ?></button></h2>
                        <div id="g<?= $i ?>_<?= $k ?>" class="accordion-collapse collapse <?= $k ? '' : 'show' ?>">
                            <table class="table table-sm mb-0"><?= $linhas($e['perguntas'], $it->respostas, $it->rotulo) ?></table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ($e['nivel'] === 'morador'): ?>
            <div class="card mb-3">
                <div class="card-header fw-semibold"><?= html_escape($e['titulo'] ?: 'Por morador') ?></div>
                <div class="accordion accordion-flush" id="acc<?= $i ?>">
                    <?php foreach ($moradores as $k => $m): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button <?= $k ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#m<?= $i ?>_<?= $k ?>">
                            <?= (int) $m->ordem ?>. <?= html_escape($m->nome) ?></button></h2>
                        <div id="m<?= $i ?>_<?= $k ?>" class="accordion-collapse collapse <?= $k ? '' : 'show' ?>">
                            <table class="table table-sm mb-0"><?= $linhas($e['perguntas'], $m->respostas, $m->nome) ?></table>
                            <?php if (!empty($pode_excluir) && isset($m->id)): ?>
                            <div class="text-end p-2"><?= form_open('controle/excluir_morador/' . (int) $m->id, ['class' => 'd-inline', 'onsubmit' => "var x = prompt('Excluir este morador e as respostas dele? Vai para a Lixeira.\\n\\nMotivo (opcional):', ''); if (x === null) return false; this.motivo.value = x; return true;"]) ?>
                                <input type="hidden" name="motivo" value=""><button class="btn btn-sm btn-outline-danger"><i class="fas fa-user-minus me-1"></i> Excluir morador</button><?= form_close() ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card mb-3">
                <div class="card-header fw-semibold"><?= html_escape($e['titulo'] ?: 'Domicílio') ?></div>
                <table class="table table-sm mb-0"><?= $linhas($e['perguntas'], $respostas, NULL) ?></table>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
