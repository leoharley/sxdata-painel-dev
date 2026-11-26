<?php defined('BASEPATH') OR exit('No direct script access allowed');
$admin = $this->session->userdata('admin_role') === 'administrador';
$dis = $admin ? '' : 'disabled';
?>
<div class="container-fluid">
    <?php if (!$ciclo): ?>
        <div class="card"><div class="card-body">
            <h2 class="h5">Programa ainda não carregado</h2>
            <p>O módulo vem com o PEA Gerdau 2026–2031 pronto para carregar: ciclo (cinco anos, de agosto a agosto), unidades, comunidades das ABEAs e da AID, escolas, eixos, os 16 projetos com as faixas de participação, linhas temáticas, cronograma trimestral, índices EIEAP, regras de ponderação (versão 1) e o formulário de avaliação de 10 perguntas.</p>
            <?php if ($seed && $admin): ?>
                <?= form_open('pea/carregar') ?><button class="btn btn-success"><i class="fas fa-download me-1"></i> Carregar o PEA 2026–2031</button><?= form_close() ?>
            <?php elseif (!$admin): ?><p class="text-muted">Peça a um administrador para carregar.</p><?php else: ?><p class="text-danger">Arquivo application/config/pea_2026_2031.json não encontrado.</p><?php endif; ?>
        </div></div>
    <?php else: ?>
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#projetos" type="button">Projetos e faixas de IP</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cronograma" type="button">Cronograma previsto</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#territorio" type="button">Unidades, comunidades e escolas</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#linhas" type="button">Linhas temáticas</button></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="projetos">
            <p class="small text-muted">Ciclo <?= html_escape($ciclo->nome) ?>: início em <?= date('d/m/Y', strtotime($ciclo->inicio)) ?>, <?= (int) $ciclo->anos ?> anos (cada ano começa no mês <?= (int) $ciclo->mes_inicio ?>). Formulário de avaliação:
                <?= $formulario ? '<a href="' . base_url('questionnaires/view/' . (int) $formulario->id) . '">' . html_escape($formulario->title) . '</a>' : '<span class="text-danger">não encontrado</span>' ?>.
                IP: participantes que valem 30 (baixa), 60 (média) e 100 (alta); sem faixa, o projeto fica fora do IP (Comunicados, Workshop).</p>
            <div class="table-responsive">
            <table class="table table-sm align-middle small">
                <thead class="table-light"><tr><th>Projeto</th><th>Público</th><th>Eixo</th><th>Periodicidade</th><th>Baixa (30)</th><th>Média (60)</th><th>Alta (100)</th><th>Avaliação</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($projetos as $p): $fx = json_decode((string) $p->ip_faixas, TRUE) ?: []; ?>
                    <tr>
                    <!-- campos ligados ao form fp{id} (fora da tabela) -->
                        <td><input name="nome" class="form-control form-control-sm" value="<?= html_escape($p->nome) ?>" <?= $dis ?> form="fp<?= (int) $p->id ?>"></td>
                        <td class="text-nowrap"><?= Pea_model::PUBLICOS[$p->publico] ?? $p->publico ?></td>
                        <td><select name="eixo_id" class="form-select form-select-sm" <?= $dis ?> form="fp<?= (int) $p->id ?>"><option value="">—</option><?php foreach ($eixos as $e): ?><option value="<?= (int) $e->id ?>" <?= (int) $p->eixo_id === (int) $e->id ? 'selected' : '' ?>><?= html_escape($e->nome) ?></option><?php endforeach; ?></select></td>
                        <td><input name="periodicidade" class="form-control form-control-sm" value="<?= html_escape($p->periodicidade) ?>" <?= $dis ?> form="fp<?= (int) $p->id ?>"></td>
                        <td class="text-nowrap"><input name="baixa_de" type="number" class="form-control form-control-sm d-inline" style="width:70px" value="<?= html_escape($fx['baixa'][0] ?? '') ?>" <?= $dis ?> form="fp<?= (int) $p->id ?>"> a <input name="baixa_ate" type="number" class="form-control form-control-sm d-inline" style="width:70px" value="<?= html_escape($fx['baixa'][1] ?? '') ?>" <?= $dis ?> form="fp<?= (int) $p->id ?>"></td>
                        <td class="text-nowrap"><input name="media_de" type="number" class="form-control form-control-sm d-inline" style="width:70px" value="<?= html_escape($fx['media'][0] ?? '') ?>" <?= $dis ?> form="fp<?= (int) $p->id ?>"> a <input name="media_ate" type="number" class="form-control form-control-sm d-inline" style="width:70px" value="<?= html_escape($fx['media'][1] ?? '') ?>" <?= $dis ?> form="fp<?= (int) $p->id ?>"></td>
                        <td class="text-nowrap">≥ <input name="alta_de" type="number" class="form-control form-control-sm d-inline" style="width:80px" value="<?= html_escape($fx['alta'][0] ?? '') ?>" <?= $dis ?> form="fp<?= (int) $p->id ?>"></td>
                        <td><input type="checkbox" class="form-check-input" name="avaliacao" value="1" <?= in_array($p->avaliacao, [TRUE, 't'], TRUE) ? 'checked' : '' ?> <?= $dis ?> form="fp<?= (int) $p->id ?>" title="Tem formulário de avaliação por participante (IS e IIA)"></td>
                        <td><?php if ($admin): ?><button class="btn btn-sm btn-outline-primary py-0" form="fp<?= (int) $p->id ?>">Salvar</button><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php foreach ($projetos as $p): ?><form id="fp<?= (int) $p->id ?>" method="post" action="<?= base_url('pea/salvar_projeto/' . (int) $p->id) ?>"></form><?php endforeach; ?>
            </div>
        </div>

        <div class="tab-pane fade" id="cronograma">
            <p class="small text-muted">Quantidade de ações previstas por trimestre de cada ano do ciclo (Tabelas 15, 21 e 27 do PEA). É a base do ICC e do alerta de ações atrasadas.</p>
            <?= form_open('pea/salvar_cronograma') ?>
            <div class="table-responsive" style="max-height: 70vh">
            <table class="table table-sm table-bordered small text-center align-middle">
                <thead class="table-light" style="position: sticky; top: 0">
                    <tr><th rowspan="2" class="text-start">Projeto</th><?php for ($a = 1; $a <= (int) $ciclo->anos; $a++): ?><th colspan="4">Ano <?= $a ?></th><?php endfor; ?><th rowspan="2">Total</th></tr>
                    <tr><?php for ($a = 1; $a <= (int) $ciclo->anos; $a++): for ($t = 1; $t <= 4; $t++): ?><th><?= $t ?>º</th><?php endfor; endfor; ?></tr>
                </thead>
                <tbody>
                <?php foreach ($projetos as $p): $tot = 0; ?>
                    <tr><td class="text-start text-nowrap"><?= html_escape($p->nome) ?></td>
                    <?php for ($a = 1; $a <= (int) $ciclo->anos; $a++): for ($t = 1; $t <= 4; $t++): $v = $cronograma[(int) $p->id][$a][$t] ?? 0; $tot += $v; ?>
                        <td class="p-0"><input type="number" min="0" name="c[<?= (int) $p->id ?>][<?= $a ?>][<?= $t ?>]" value="<?= $v ?: '' ?>" class="form-control form-control-sm border-0 text-center px-0" style="width:42px" <?= $dis ?>></td>
                    <?php endfor; endfor; ?>
                    <td class="fw-semibold"><?= $tot ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php if ($admin): ?><button class="btn btn-primary btn-sm">Salvar cronograma</button><?php endif; ?>
            <?= form_close() ?>
        </div>

        <div class="tab-pane fade" id="territorio">
            <div class="row g-3">
                <div class="col-lg-3"><h3 class="h6">Unidades</h3><ul class="list-group small"><?php foreach ($unidades as $u): ?><li class="list-group-item"><strong><?= html_escape($u->sigla) ?></strong> — <?= html_escape($u->nome) ?></li><?php endforeach; ?></ul></div>
                <div class="col-lg-4"><h3 class="h6">Comunidades (ABEAs e AID) — <?= count($comunidades) ?></h3>
                    <div style="max-height: 60vh; overflow: auto"><table class="table table-sm small"><tbody><?php foreach ($comunidades as $c): ?><tr><td><?= html_escape($c->nome) ?></td><td><?= html_escape($c->municipio) ?></td><td><?= html_escape($c->sigla) ?></td><td class="text-muted"><?= html_escape($c->tipo) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
                <div class="col-lg-5"><h3 class="h6">Escolas — <?= count($escolas) ?></h3>
                    <div style="max-height: 60vh; overflow: auto"><table class="table table-sm small"><tbody><?php foreach ($escolas as $e): ?><tr><td><?= html_escape($e->nome) ?></td><td><?= html_escape($e->municipio) ?></td><td><?= html_escape($e->comunidade) ?></td><td><?= html_escape($e->sigla) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
            </div>
        </div>

        <div class="tab-pane fade" id="linhas">
            <?php foreach (Pea_model::PUBLICOS as $pk => $pn): $ls = array_filter($linhas, function ($l) use ($pk) { return $l->publico === $pk; }); if (!$ls) { continue; } ?>
                <h3 class="h6 mt-2"><?= $pn ?></h3>
                <ul class="small"><?php foreach ($ls as $l): $c = json_decode((string) $l->conteudos, TRUE) ?: []; ?><li><strong><?= html_escape($l->linha) ?></strong><?= $c ? ': ' . html_escape(implode('; ', $c)) : '' ?></li><?php endforeach; ?></ul>
            <?php endforeach; ?>
            <?php if (!$linhas): ?><p class="text-muted">Nenhuma linha temática carregada.</p><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
if (location.hash) { var b = document.querySelector('[data-bs-target="' + location.hash + '"]'); if (b) { new bootstrap.Tab(b).show(); } }
</script>
