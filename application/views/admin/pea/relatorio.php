<?php defined('BASEPATH') OR exit('No direct script access allowed');
$st = ['rascunho' => ['Rascunho', 'secondary'], 'em_conferencia' => ['Em conferência', 'warning text-dark'], 'aprovado' => ['Aprovado', 'success']][$r->status] ?? [$r->status, 'secondary'];
$editavel = $r->status !== 'aprovado';
$ev = ['gerado' => 'Gerado', 'editado' => 'Textos editados', 'enviado' => 'Enviado para conferência', 'comentario' => 'Comentário', 'devolvido' => 'Devolvido para ajustes', 'aprovado' => 'Aprovado', 'exportado' => 'Exportado'];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div><a href="<?= base_url('pea/relatorios') ?>" class="small"><i class="fas fa-arrow-left"></i> Relatórios</a>
            <h1 class="h4 mb-0"><?= html_escape($r->titulo) ?> <span class="badge bg-<?= $st[1] ?> fs-6 align-middle"><?= $st[0] ?></span></h1></div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?= base_url('pea/imprimir/' . (int) $r->id) ?>"><i class="fas fa-file-pdf me-1"></i> PDF / imprimir<?= $r->status !== 'aprovado' ? ' (rascunho)' : '' ?></a>
            <a class="btn btn-outline-success btn-sm" href="<?= base_url('pea/excel/' . (int) $r->id) ?>"><i class="fas fa-file-excel me-1"></i> Base de cálculo</a>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3"><div class="card-body"><?php $this->load->view('admin/pea/_relatorio_corpo', ['r' => $r, 'regras' => $regras]); ?></div></div>
        </div>
        <div class="col-xl-4">
            <?= form_open('pea/relatorio_acao/' . (int) $r->id, ['class' => 'card mb-3']) ?>
                <div class="card-header"><strong>Textos interpretativos</strong></div>
                <div class="card-body">
                    <?php foreach (['apresentacao' => 'Apresentação', 'analise' => 'Análise dos indicadores', 'desvios' => 'Justificativas de desvios', 'conclusao' => 'Conclusão e próximos passos'] as $k => $n): ?>
                        <label class="form-label small mb-0"><?= $n ?></label>
                        <textarea name="<?= $k ?>" class="form-control form-control-sm mb-2" rows="<?= $k === 'analise' ? 6 : 3 ?>" <?= $editavel ? '' : 'readonly' ?>><?= html_escape($r->textos[$k] ?? '') ?></textarea>
                    <?php endforeach; ?>
                    <?php if ($editavel): ?><button class="btn btn-primary btn-sm" name="acao" value="salvar">Salvar textos</button><?php endif; ?>
                </div>
            <?= form_close() ?>
            <?= form_open('pea/relatorio_acao/' . (int) $r->id, ['class' => 'card mb-3']) ?>
                <div class="card-header"><strong>Conferência e aprovação</strong></div>
                <div class="card-body">
                    <textarea name="comentario" class="form-control form-control-sm mb-2" rows="2" placeholder="Comentário (opcional)"></textarea>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-sm btn-outline-secondary" name="acao" value="comentar">Comentar</button>
                        <?php if ($r->status === 'rascunho'): ?><button class="btn btn-sm btn-warning" name="acao" value="enviar">Enviar para conferência</button><?php endif; ?>
                        <?php if ($r->status === 'em_conferencia'): ?>
                            <button class="btn btn-sm btn-outline-danger" name="acao" value="devolver">Devolver para ajustes</button>
                            <?php if ($admin): ?><button class="btn btn-sm btn-success" name="acao" value="aprovar" onclick="return confirm('Aprovar? O relatório fica bloqueado para edição.')">Aprovar</button><?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($r->status === 'aprovado'): ?><p class="small text-success mt-2 mb-0">Aprovado: liberado para exportação. Para mudar, gere uma nova versão.</p><?php endif; ?>
                    <ul class="list-unstyled small mt-3 mb-0">
                        <?php foreach ($r->eventos as $e): ?><li class="border-bottom py-1"><strong><?= $ev[$e->evento] ?? $e->evento ?></strong> · <?= html_escape($e->full_name ?: '—') ?> · <?= date('d/m/Y H:i', strtotime($e->criado_em)) ?><?= $e->comentario ? '<br>' . html_escape($e->comentario) : '' ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
