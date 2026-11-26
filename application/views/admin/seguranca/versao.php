<?php defined('BASEPATH') OR exit('No direct script access allowed');
// Markdown mínimo das notas: títulos, listas, negrito e código
$html = '';
$lista = FALSE;
foreach (preg_split('/\r?\n/', (string) $notas) as $linha) {
    $t = html_escape($linha);
    $t = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $t);
    $t = preg_replace('/`(.+?)`/', '<code>$1</code>', $t);
    if (preg_match('/^\s*[-*] (.*)$/', $t, $m)) {
        if (!$lista) { $html .= '<ul>'; $lista = TRUE; }
        $html .= '<li>' . $m[1] . '</li>';
        continue;
    }
    if ($lista) { $html .= '</ul>'; $lista = FALSE; }
    if (preg_match('/^(#{1,4}) (.*)$/', $t, $m)) {
        $n = min(6, strlen($m[1]) + 2);
        $html .= "<h$n class=\"mt-4\">" . $m[2] . "</h$n>";
    } elseif (trim($t) !== '') {
        $html .= '<p>' . $t . '</p>';
    }
}
if ($lista) { $html .= '</ul>'; }
?>
<div class="container-fluid" style="max-width: 980px">
    <a href="<?= base_url('controle') ?>" class="small"><i class="fas fa-arrow-left"></i> Controle e segurança</a>
    <h1 class="h3 mb-0">Versão <?= html_escape($versao['numero'] ?? '-') ?></h1>
    <p class="text-muted">Publicada em <?= html_escape($versao['data'] ?? '-') ?></p>
    <div class="card"><div class="card-body"><?= $html ?: '<p class="text-muted mb-0">Sem notas de versão.</p>' ?></div></div>
</div>
