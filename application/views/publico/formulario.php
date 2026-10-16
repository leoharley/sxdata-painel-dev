<?php
/**
 * Formulário público do questionário (coleta por QR Code).
 *
 * @var object $link
 * @var array  $questions
 * @var bool   $requires_consent
 * @var array  $errors
 * @var array  $old          respostas enviadas, para repreencher após erro
 * @var bool   $old_consent
 * @var string $ticket
 */
$page_title = $link->questionnaire_title;

$opt_value = function ($opt) {
    return ($opt->option_value !== null && $opt->option_value !== '') ? (string) $opt->option_value : (string) $opt->option_text;
};
$old_value = function ($qid) use ($old) {
    return isset($old[$qid]) ? $old[$qid] : null;
};

$this->load->view('publico/_topo', ['page_title' => $page_title]);
?>
<style>
    .pq { padding: 16px 0; border-top: 1px solid #eef0f2; }
    .pq:first-of-type { border-top: 0; }
    .pq-titulo { font-weight: 600; color: #23345F; margin-bottom: 8px; display: block; }
    .pq-obrigatoria { color: #dc3545; margin-left: 2px; }
    .pq-opcao { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid #e5e7eb;
                border-radius: 8px; margin-bottom: 8px; cursor: pointer; }
    .pq-opcao:hover { border-color: #8fae5d; }
    .pq-opcao input { margin-top: 3px; flex-shrink: 0; }
    .pq-opcao input:checked + span { font-weight: 600; }
    .pq.pq-erro .pq-titulo { color: #b02a37; }
    .pq-msg-erro { color: #b02a37; font-size: .85rem; margin-top: 6px; display: none; }
    .pq.pq-erro .pq-msg-erro { display: block; }
    .consentimento { background: #f1f6ea; border-radius: 10px; padding: 14px 16px; font-size: .9rem; }
    .campo-website { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
</style>

<h1><?= html_escape($link->questionnaire_title) ?></h1>
<?php if (!empty($link->questionnaire_description)): ?>
    <p class="text-muted mb-3"><?= nl2br(html_escape($link->questionnaire_description)) ?></p>
<?php endif; ?>

<p class="small text-muted mb-4">
    <i class="fas fa-user-shield me-1"></i>
    Sua resposta é anônima: não pedimos login nem cadastro.
    Perguntas marcadas com <span class="pq-obrigatoria">*</span> são obrigatórias.
</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" role="alert">
        <strong>Confira antes de enviar:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $e): ?>
                <li><?= html_escape($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?= form_open('responder/' . $link->token, ['id' => 'form-publico', 'novalidate' => 'novalidate']) ?>
    <input type="hidden" name="t" value="<?= html_escape($ticket) ?>">

    <!-- Armadilha para robôs: invisível para pessoas -->
    <div class="campo-website" aria-hidden="true">
        <label for="website">Não preencha este campo</label>
        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
    </div>

    <?php if ($requires_consent): ?>
        <div class="consentimento mb-4">
            <p class="mb-2">
                As informações desta pesquisa são protegidas pela Lei Geral de Proteção de Dados
                (Lei 13.709/2018) e serão usadas exclusivamente para os fins deste estudo.
            </p>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="consentimento" name="consentimento"
                       <?= $old_consent ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="consentimento">
                    Li e concordo em participar desta pesquisa.
                </label>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($questions as $q):
        $qid   = (int) $q->id;
        $type  = $q->question_type;
        $name  = 'q[' . $qid . ']';
        $value = $old_value($qid);
        $logic = !empty($q->conditional_logic_decoded) ? $q->conditional_logic_decoded : null;
    ?>
        <div class="pq" data-qid="<?= $qid ?>" data-type="<?= html_escape($type) ?>"
             data-required="<?= $q->is_required ? '1' : '0' ?>"
             <?php if ($logic): ?>data-logic="<?= html_escape(json_encode($logic, JSON_UNESCAPED_UNICODE)) ?>"<?php endif; ?>>

            <label class="pq-titulo" <?= in_array($type, ['radio', 'checkbox'], true) ? '' : 'for="q' . $qid . '"' ?>>
                <?= html_escape($q->question_text) ?><span class="pq-obrigatoria" <?= $q->is_required ? '' : 'hidden' ?>>*</span>
            </label>

            <?php if ($type === 'radio'): ?>
                <?php foreach ($q->options as $opt): $v = $opt_value($opt); ?>
                    <label class="pq-opcao">
                        <input class="form-check-input" type="radio" name="<?= $name ?>" value="<?= html_escape($v) ?>"
                               <?= ($value !== null && (string) $value === $v) ? 'checked' : '' ?>>
                        <span><?= html_escape($opt->option_text) ?></span>
                    </label>
                <?php endforeach; ?>

            <?php elseif ($type === 'checkbox'): $marcados = is_array($value) ? array_map('strval', $value) : []; ?>
                <?php foreach ($q->options as $opt): $v = $opt_value($opt); ?>
                    <label class="pq-opcao">
                        <input class="form-check-input" type="checkbox" name="<?= $name ?>[]" value="<?= html_escape($v) ?>"
                               <?= in_array($v, $marcados, true) ? 'checked' : '' ?>>
                        <span><?= html_escape($opt->option_text) ?></span>
                    </label>
                <?php endforeach; ?>

            <?php elseif ($type === 'select'): ?>
                <select class="form-select" id="q<?= $qid ?>" name="<?= $name ?>">
                    <option value="">Selecione...</option>
                    <?php foreach ($q->options as $opt): $v = $opt_value($opt); ?>
                        <option value="<?= html_escape($v) ?>" <?= ($value !== null && (string) $value === $v) ? 'selected' : '' ?>>
                            <?= html_escape($opt->option_text) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            <?php elseif ($type === 'textarea'): ?>
                <textarea class="form-control" id="q<?= $qid ?>" name="<?= $name ?>" rows="4"
                          maxlength="5000"><?= html_escape((string) $value) ?></textarea>

            <?php else:
                $input_type = [
                    'number'   => 'text',
                    'email'    => 'email',
                    'phone'    => 'tel',
                    'date'     => 'date',
                    'datetime' => 'datetime-local',
                    'time'     => 'time',
                ][$type] ?? 'text';
                $extra = [
                    'number' => 'inputmode="decimal" maxlength="5000"',
                    'phone'  => 'inputmode="tel" maxlength="15" placeholder="(00) 00000-0000" data-mask="telefone"',
                ][$type] ?? 'maxlength="5000"';
            ?>
                <input class="form-control" type="<?= $input_type ?>" id="q<?= $qid ?>" name="<?= $name ?>"
                       value="<?= html_escape((string) $value) ?>" <?= $extra ?>>
            <?php endif; ?>

            <div class="pq-msg-erro">Esta pergunta é obrigatória.</div>
        </div>
    <?php endforeach; ?>

    <div class="d-grid mt-4">
        <button type="submit" class="btn btn-sx btn-lg" id="btn-enviar">
            <i class="fas fa-paper-plane me-2"></i>Enviar respostas
        </button>
    </div>
<?= form_close() ?>

<script>
(function () {
    var form = document.getElementById('form-publico');
    var blocos = Array.prototype.slice.call(form.querySelectorAll('.pq'));
    var porId = {};
    blocos.forEach(function (b) { porId[b.dataset.qid] = b; });

    // Valor atual de uma pergunta, no mesmo formato usado pelo servidor
    function valor(qid) {
        var b = porId[qid];
        if (!b) return '';
        var tipo = b.dataset.type;
        if (tipo === 'checkbox') {
            return Array.prototype.slice.call(b.querySelectorAll('input:checked'))
                .map(function (i) { return i.value.trim(); })
                .filter(function (v) { return v !== ''; });
        }
        if (tipo === 'radio') {
            var m = b.querySelector('input:checked');
            return m ? m.value.trim() : '';
        }
        var campo = b.querySelector('input, select, textarea');
        return campo ? campo.value.trim() : '';
    }

    function ehNumero(s) { return s !== '' && !isNaN(Number(s)); }

    // Espelho de Questionnaire_model::evaluate_condition (PHP)
    function avaliaCondicao(resp, op, cond) {
        var respStr = Array.isArray(resp) ? resp.join(',') : String(resp == null ? '' : resp);
        var condStr = String(cond == null ? '' : cond);
        switch (op) {
            case 'equals':       return respStr === condStr;
            case 'not_equals':   return respStr !== condStr;
            case 'contains':     return Array.isArray(resp) ? resp.indexOf(condStr) !== -1 : respStr.indexOf(condStr) !== -1;
            case 'not_contains': return Array.isArray(resp) ? resp.indexOf(condStr) === -1 : respStr.indexOf(condStr) === -1;
            case 'greater_than': return ehNumero(respStr) && ehNumero(condStr) && Number(respStr) > Number(condStr);
            case 'less_than':    return ehNumero(respStr) && ehNumero(condStr) && Number(respStr) < Number(condStr);
            // empty() do PHP trata '0' como vazio
            case 'is_empty':     return respStr === '' || respStr === '0' || respStr.trim() === '';
            case 'is_not_empty': return respStr !== '' && respStr !== '0' && respStr.trim() !== '';
            default:             return false;
        }
    }

    // Espelho de Questionnaire_model::evaluate_rule_with_ids (PHP)
    function avaliaRegra(regra) {
        if (!regra || !regra.conditions || !regra.conditions.length) return true;
        var resultados = [];
        regra.conditions.forEach(function (c) {
            if (c.question === undefined || c.operator === undefined) return;
            resultados.push(avaliaCondicao(valor(String(c.question)), c.operator, c.value));
        });
        if (regra.operator === 'OR') return resultados.indexOf(true) !== -1;
        return resultados.indexOf(false) === -1;
    }

    function estados() {
        var mapa = {};
        blocos.forEach(function (b) {
            var visivel = true;
            var obrigatoria = b.dataset.required === '1';
            if (b.dataset.logic) {
                var logica = JSON.parse(b.dataset.logic);
                if (logica.visibility) visivel = avaliaRegra(logica.visibility);
                if (logica.required && avaliaRegra(logica.required)) obrigatoria = true;
                if (!visivel) obrigatoria = false;
            }
            mapa[b.dataset.qid] = { visivel: visivel, obrigatoria: obrigatoria };
        });
        return mapa;
    }

    function aplicar() {
        var mapa = estados();
        blocos.forEach(function (b) {
            var e = mapa[b.dataset.qid];
            b.hidden = !e.visivel;
            b.querySelector('.pq-obrigatoria').hidden = !e.obrigatoria;
            b.dataset.obrigatoriaAgora = e.obrigatoria ? '1' : '0';
            if (!e.visivel) b.classList.remove('pq-erro');
        });
    }

    // Telefone com DDD: (00) 0000-0000 (fixo) ou (00) 00000-0000 (celular)
    function mascaraTelefone(v) {
        var d = v.replace(/\D/g, '').slice(0, 11);
        if (d.length === 0) return '';
        if (d.length <= 2) return '(' + d;
        if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
        var meio = d.length === 11 ? 7 : 6;
        return '(' + d.slice(0, 2) + ') ' + d.slice(2, meio) + '-' + d.slice(meio);
    }

    function telefoneValido(v) {
        var d = v.replace(/\D/g, '');
        return (d.length === 10 || d.length === 11) && d[0] !== '0' && d[1] !== '0';
    }

    Array.prototype.slice.call(form.querySelectorAll('[data-mask="telefone"]')).forEach(function (campo) {
        // Ao apagar, não remascara: senão apagar o "-" ou o ")" não teria efeito
        campo.addEventListener('input', function (ev) {
            if (ev.inputType && ev.inputType.indexOf('delete') === 0) return;
            campo.value = mascaraTelefone(campo.value);
        });
        campo.addEventListener('blur', function () { campo.value = mascaraTelefone(campo.value); });
        if (campo.value) campo.value = mascaraTelefone(campo.value);
    });

    form.addEventListener('input', aplicar);
    form.addEventListener('change', aplicar);
    aplicar();

    form.addEventListener('submit', function (ev) {
        aplicar();
        var primeiroErro = null;
        blocos.forEach(function (b) {
            var v = valor(b.dataset.qid);
            var vazio = Array.isArray(v) ? v.length === 0 : v === '';
            var erro = !b.hidden && b.dataset.obrigatoriaAgora === '1' && vazio;
            // Telefone preenchido pela metade não passa, mesmo se opcional
            var telIncompleto = !b.hidden && b.dataset.type === 'phone' && !vazio && !telefoneValido(v);
            b.querySelector('.pq-msg-erro').textContent = telIncompleto
                ? 'Informe o telefone com DDD.'
                : 'Esta pergunta é obrigatória.';
            erro = erro || telIncompleto;
            b.classList.toggle('pq-erro', erro);
            if (erro && !primeiroErro) primeiroErro = b;
        });

        var consent = document.getElementById('consentimento');
        if (consent && !consent.checked) {
            ev.preventDefault();
            consent.closest('.consentimento').scrollIntoView({ behavior: 'smooth', block: 'center' });
            alert('Para responder, é preciso concordar com o termo de consentimento.');
            return;
        }

        if (primeiroErro) {
            ev.preventDefault();
            primeiroErro.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        var btn = document.getElementById('btn-enviar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
    });
})();
</script>

<?php $this->load->view('publico/_rodape'); ?>
