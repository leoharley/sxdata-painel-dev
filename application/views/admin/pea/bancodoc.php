<?php defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Bancodoc.php';
$admin = $this->session->userdata('admin_role') === 'administrador'; ?>
<div class="container-fluid">
    <div class="alert alert-light border small">
        <strong>Integração por arquivo.</strong> O BancoDOC não tem API pública documentada; enquanto a Gerdau não obtém a documentação e as credenciais (ponto 9 da seção 20), a base de terceiros entra por planilha e as participações saem num arquivo para importação no BancoDOC.
        A interface do conector já está pronta para a API: quando ela existir, as telas e os cálculos não mudam. As avaliações continuam anônimas e nunca vão para o BancoDOC; só a lista de presença.
    </div>
    <div class="row g-3 mb-3">
        <div class="col-lg-4"><div class="card h-100"><div class="card-header"><strong>Base de terceiros</strong></div><div class="card-body">
            <div class="display-6"><?= number_format($n_colab, 0, ',', '.') ?></div><div class="text-muted small mb-2">colaboradores · <?= number_format($n_emp, 0, ',', '.') ?> empresas</div>
            <?php if ($admin): ?>
            <?= form_open_multipart('pea/bancodoc_importar') ?>
                <input type="file" name="arquivo" class="form-control form-control-sm mb-2" accept=".csv,.xlsx,.xls,.txt" required>
                <button class="btn btn-primary btn-sm">Importar planilha</button> <a class="small ms-2" href="<?= base_url('pea/bancodoc_modelo') ?>">modelo</a>
            <?= form_close() ?>
            <p class="small text-muted mt-2 mb-0">Colunas aceitas: CPF, Matrícula, Nome, Empresa, CNPJ, Função, Unidade, Situação, Data de mobilização. Atualiza quem já existe (pelo CPF ou matrícula).</p>
            <?php endif; ?>
        </div></div></div>
        <div class="col-lg-4"><div class="card h-100"><div class="card-header"><strong>Participações para o BancoDOC</strong></div><div class="card-body">
            <div class="display-6"><?= number_format($pendentes, 0, ',', '.') ?></div><div class="text-muted small mb-2">presença(s) em ações encerradas ainda não enviadas</div>
            <a class="btn btn-success btn-sm" href="<?= base_url('pea/bancodoc_exportar') ?>"><i class="fas fa-file-csv me-1"></i> Gerar arquivo (pendentes)</a>
            <a class="btn btn-outline-success btn-sm" href="<?= base_url('pea/bancodoc_exportar?todas=1') ?>">Todas</a>
            <p class="small text-muted mt-2 mb-0">Colunas: <?= html_escape(implode(', ', Bancodoc::colunas_participacao())) ?>. Layout provisório até a Gerdau indicar o de importação do BancoDOC.</p>
        </div></div></div>
        <div class="col-lg-4"><div class="card h-100"><div class="card-header"><strong>Sem Módulo Básico</strong> <span class="small text-muted">(mobilizados)</span></div><div class="card-body small" style="max-height: 260px; overflow: auto">
            <?php foreach ($recentes_sem_modulo as $c): ?><div class="border-bottom py-1"><?= html_escape($c->nome) ?> <span class="text-muted"><?= html_escape(trim(($c->empresa ?: '') . ' · ' . ($c->unidade ?: ''), ' ·')) ?><?= $c->mobilizado_em ? ' · desde ' . date('d/m/Y', strtotime($c->mobilizado_em)) : '' ?></span></div><?php endforeach; ?>
            <?php if (!$recentes_sem_modulo): ?><p class="text-muted mb-0">Nenhum (ou a base ainda não foi importada).</p><?php endif; ?>
        </div></div></div>
    </div>
    <div class="card"><div class="card-header"><strong>Histórico de sincronização</strong></div><div class="card-body p-0">
        <table class="table table-sm mb-0 small"><thead class="table-light"><tr><th>Quando</th><th>Sentido</th><th>Forma</th><th>Situação</th><th class="text-end">Registros</th><th>Arquivo</th><th>Por</th><th>Detalhes</th></tr></thead><tbody>
        <?php foreach ($envios as $e): $det = json_decode((string) $e->detalhes, TRUE) ?: []; ?>
            <tr><td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($e->criado_em)) ?></td><td><?= $e->sentido === 'entrada' ? 'BancoDOC → SXData' : 'SXData → BancoDOC' ?></td><td><?= html_escape($e->conector) ?></td>
                <td><span class="badge bg-<?= $e->status === 'ok' ? 'success' : ($e->status === 'parcial' ? 'warning text-dark' : 'danger') ?>"><?= html_escape($e->status) ?></span></td><td class="text-end"><?= (int) $e->registros ?></td>
                <td><?= html_escape($e->arquivo) ?></td><td><?= html_escape($e->full_name ?: '—') ?></td>
                <td><?= isset($det['novos']) ? (int) $det['novos'] . ' novos, ' . (int) $det['atualizados'] . ' atualizados' . (!empty($det['erros']) ? ', ' . count($det['erros']) . ' recusadas' : '') : html_escape($det['mensagem'] ?? '') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$envios): ?><tr><td colspan="8" class="text-muted p-3">Nenhuma sincronização ainda.</td></tr><?php endif; ?>
        </tbody></table>
    </div></div>
</div>
