<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="container-fluid">
    <a href="<?= base_url('controle') ?>" class="small"><i class="fas fa-arrow-left"></i> Controle e segurança</a>
    <h1 class="h3 mb-0">Aparelhos em campo</h1>
    <p class="text-muted">O app informa ao servidor, sempre que abre e a cada sincronização, quantas entrevistas e fotos ainda estão no aparelho sem envio. Em vermelho: há pendência e o aparelho não fala com o servidor há mais de 24 horas.</p>
    <div class="card">
        <div class="card-body p-0">
        <?php if (!$lista): ?>
            <p class="p-4 mb-0 text-muted">Nenhum aparelho informou ainda. Os aparelhos aparecem aqui quando o app atualizado (com o envio de situação) for instalado.</p>
        <?php else: ?>
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>Entrevistador</th><th>Aparelho</th><th>Versão do app</th><th class="text-end">Entrevistas na fila</th><th class="text-end">Mídias na fila</th><th class="text-end">Rascunhos</th><th>Último envio</th><th>Visto pela última vez</th></tr></thead>
                <tbody>
                <?php foreach ($lista as $d):
                    $pend = (int) $d->pendentes + (int) $d->pendentes_midias;
                    $sumido = strtotime($d->visto_em) < time() - 86400;
                    $classe = $pend > 0 && $sumido ? 'table-danger' : ($pend > 0 ? 'table-warning' : ''); ?>
                    <tr class="<?= $classe ?>">
                        <td><?= html_escape($d->full_name ?: '—') ?><br><span class="small text-muted"><?= html_escape($d->username ?: '') ?></span></td>
                        <td class="small"><?= html_escape($d->plataforma ?: '—') ?><br><span class="text-muted" title="<?= html_escape($d->device_id) ?>"><?= html_escape(mb_substr($d->device_id, 0, 12)) ?>…</span></td>
                        <td><?= html_escape($d->app_versao ?: '—') ?></td>
                        <td class="text-end fw-bold"><?= (int) $d->pendentes ?></td>
                        <td class="text-end"><?= (int) $d->pendentes_midias ?></td>
                        <td class="text-end"><?= (int) $d->rascunhos ?></td>
                        <td class="text-nowrap"><?= $d->ultimo_envio_em ? date('d/m/Y H:i', strtotime($d->ultimo_envio_em)) : '—' ?></td>
                        <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($d->visto_em)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
    </div>
</div>
