<?php
require __DIR__ . '/../_nav.php';
$phases = $phases ?? [];
$responsibles = $responsibles ?? [];
$attachments = $attachments ?? [];
$mail_log = $mail_log ?? [];
$mail_templates = $mail_templates ?? [];
$export_formats = $export_formats ?? [];
$statusLabels = [
    'pending' => 'Pendiente',
    'current' => 'En curso',
    'done' => 'Hecho',
    'skipped' => 'Omitido',
    'blocked' => 'Bloqueado',
];
$exportLabel = $export_formats[$item['export_format'] ?? 'none'] ?? ($item['export_format'] ?? 'none');
$caseId = (int) $item['id'];

$tabLabels = [
    'alumno' => 'Alumno',
    'reglamento' => 'Reglamento',
    'accesos' => 'Accesos',
    'resultados' => 'Resultados',
    'pago' => 'Pago',
    'operacion' => 'Operación',
    'cenni' => 'CENNI',
    'adjuntos' => 'Adjuntos',
    'protocolo' => 'Protocolo',
];
$caseTabs = $case_tabs ?? array_keys($tabLabels);
$allowed = [];
foreach ($caseTabs as $tabKey) {
    if (isset($tabLabels[$tabKey])) {
        $allowed[$tabKey] = $tabLabels[$tabKey];
    }
}
if ($allowed === []) {
    $allowed = $tabLabels;
}
$tab = $tab ?? (string) ($_GET['tab'] ?? 'alumno');
if (!isset($allowed[$tab])) {
    $tab = array_key_first($allowed);
}

$studentFields = $student_fields ?? null;
$showStudentField = static function (string $key) use ($studentFields): bool {
    if ($studentFields === null) {
        return true;
    }

    return isset($studentFields[$key]);
};
$studentFieldRequired = static function (string $key) use ($studentFields): bool {
    if ($studentFields === null) {
        return in_array($key, ['first_name', 'last_name_p', 'email'], true);
    }

    return ($studentFields[$key] ?? '') === 'required';
};

$requiresRegulation = isset($requires_regulation)
    ? !empty($requires_regulation)
    : !empty($item['requires_regulation_signature']);
$requiresZoom = !empty($item['requires_zoom']);

$fichaTitle = 'Caso #' . $caseId;
$fichaSubtitle = e($item['certification_code']) . ' · ' . e($item['certification_name'])
    . '<br>Protocolo: ' . e($item['protocol_name']) . ' · Estado: ' . e($item['status'])
    . ' · Export: ' . e($exportLabel);
$fichaBackUrl = '/admin/cases';
$fichaTabBase = '/admin/cases/view?id=' . $caseId;
$fichaMode = 'url';
$tabs = $allowed;

$opPaid = !empty($item['payment_confirmed_at']) || in_array(strtolower((string)($item['openpay_status'] ?? '')), ['completed', 'paid'], true);
$payMethod = trim((string) ($item['payment_method'] ?? ''));
$payMethodLabel = match ($payMethod) {
    'cash' => 'Efectivo',
    'transfer' => 'Transferencia',
    'openpay' => 'OpenPay SPEI',
    'other' => 'Otro',
    default => $payMethod !== '' ? $payMethod : '',
};
$hasStudentProof = !$opPaid && trim((string) ($item['payment_proof_path'] ?? '')) !== '';
$defaultPayMethod = in_array($payMethod, ['cash', 'transfer', 'openpay', 'other'], true)
    ? $payMethod
    : 'transfer';
?>
<section class="admin-ficha">
    <?php require __DIR__ . '/../_ficha_head.php'; ?>

    <?php if (!empty($info)): ?><p class="alert alert-ok"><?= e($info) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

    <?php if ($tab === 'alumno'): ?>
    <div class="admin-ficha-panel is-active">
        <h3>Datos del alumno y agenda</h3>
        <p class="muted">Solo se muestran los campos activos en la certificación / proveedor.</p>
        <form method="post" action="/admin/cases/update" class="stack form-grid">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <input type="hidden" name="tab" value="alumno">
            <?php if ($showStudentField('first_name')): ?>
                <label>Nombre(s)<input name="student_name" <?= $studentFieldRequired('first_name') ? 'required' : '' ?> value="<?= e($item['student_name'] ?? '') ?>"></label>
            <?php endif; ?>
            <?php if ($showStudentField('last_name_p')): ?>
                <label>Apellido paterno<input name="student_last_name_p" <?= $studentFieldRequired('last_name_p') ? 'required' : '' ?> value="<?= e($item['student_last_name_p'] ?? '') ?>"></label>
            <?php endif; ?>
            <?php if ($showStudentField('last_name_m')): ?>
                <label>Apellido materno<input name="student_last_name_m" <?= $studentFieldRequired('last_name_m') ? 'required' : '' ?> value="<?= e($item['student_last_name_m'] ?? '') ?>"></label>
            <?php endif; ?>
            <?php if ($showStudentField('email')): ?>
                <label>E-mail<input type="email" name="student_email" <?= $studentFieldRequired('email') ? 'required' : '' ?> value="<?= e($item['student_email'] ?? '') ?>"></label>
            <?php endif; ?>
            <?php if ($showStudentField('phone')): ?>
                <label>Teléfono<input name="student_phone" <?= $studentFieldRequired('phone') ? 'required' : '' ?> value="<?= e($item['student_phone'] ?? '') ?>"></label>
            <?php endif; ?>
            <label>CC (TR)<input type="email" name="cc_email" value="<?= e($item['cc_email'] ?? $item['partner_email'] ?? '') ?>" placeholder="correo del TR"></label>
            <?php if ($showStudentField('curp')): ?>
                <label>CURP<input name="student_curp" <?= $studentFieldRequired('curp') ? 'required' : '' ?> value="<?= e($item['student_curp'] ?? '') ?>"></label>
            <?php endif; ?>
            <?php if ($showStudentField('birth_date')): ?>
                <label>Fecha nacimiento<input type="date" name="student_birth_date" <?= $studentFieldRequired('birth_date') ? 'required' : '' ?> value="<?= e($item['student_birth_date'] ?? '') ?>"></label>
            <?php endif; ?>
            <?php if ($showStudentField('sex')): ?>
                <label>Sexo
                    <select name="student_sex" <?= $studentFieldRequired('sex') ? 'required' : '' ?>>
                        <?php $sx = (string)($item['student_sex'] ?? ''); ?>
                        <option value="">—</option>
                        <option value="F" <?= $sx === 'F' || str_starts_with(strtolower($sx), 'f') ? 'selected' : '' ?>>Femenino</option>
                        <option value="M" <?= $sx === 'M' || str_starts_with(strtolower($sx), 'm') ? 'selected' : '' ?>>Masculino</option>
                    </select>
                </label>
            <?php endif; ?>
            <?php if ($showStudentField('nationality')): ?>
                <label>Nacionalidad<input name="student_nationality" <?= $studentFieldRequired('nationality') ? 'required' : '' ?> value="<?= e($item['student_nationality'] ?? 'MEX') ?>"></label>
            <?php endif; ?>
            <?php if ($showStudentField('exam_date')): ?>
                <label>Fecha examen<input type="date" name="exam_date" <?= $studentFieldRequired('exam_date') ? 'required' : '' ?> value="<?= e($item['exam_date'] ?? '') ?>"></label>
            <?php endif; ?>
            <?php if ($showStudentField('exam_time')): ?>
                <label>Hora examen<input name="exam_time" <?= $studentFieldRequired('exam_time') ? 'required' : '' ?> value="<?= e($item['exam_time'] ?? '') ?>" placeholder="11:00"></label>
            <?php endif; ?>
            <?php if ($showStudentField('exam_date') || $showStudentField('exam_time')): ?>
                <label>Reagenda fecha<input type="date" name="reschedule_date" value="<?= e($item['reschedule_date'] ?? '') ?>"><small class="muted">Solo guarda; para avisar al proveedor usa la pestaña Operación.</small></label>
                <label>Reagenda hora<input name="reschedule_time" value="<?= e($item['reschedule_time'] ?? '') ?>"></label>
            <?php endif; ?>
            <label class="field-wide">Notas<textarea name="notes" rows="2"><?= e($item['notes'] ?? '') ?></textarea></label>
            <div class="admin-ficha-actions"><button class="btn" type="submit">Guardar datos</button></div>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'reglamento' && $requiresRegulation): ?>
    <div class="admin-ficha-panel is-active">
        <h3>Reglamento firmado</h3>
        <?php
        $regulation_doc = $regulation_doc ?? null;
        $sigAtt = null;
        foreach ($attachments as $att) {
            if (($att['kind'] ?? '') === 'regulation_signature') {
                $sigAtt = $att;
                break;
            }
        }
        ?>
        <?php if (!empty($item['regulation_signed_at'])): ?>
            <p>
                <span class="pill pill-ok">Firmado digitalmente</span>
                el <?= e((string)$item['regulation_signed_at']) ?>
                por <strong><?= e((string)($item['regulation_signer_name'] ?? '')) ?></strong>
                <?php if (!empty($item['regulation_signature_mode'])): ?>
                    <span class="muted">(<?= e($item['regulation_signature_mode'] === 'draw' ? 'dibujo' : 'nombre escrito') ?>)</span>
                <?php endif; ?>
            </p>
            <?php if ($regulation_doc): ?>
                <p>
                    Reglamento original:
                    <strong><?= e((string)($regulation_doc['title'] ?? 'Reglamento')) ?></strong>
                    <?php if (!empty($regulation_doc['version'])): ?>
                        <span class="muted">v<?= e((string)$regulation_doc['version']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($regulation_doc['file_path'])): ?>
                        · <a href="/media?f=<?= e(rawurlencode((string)$regulation_doc['file_path'])) ?>" target="_blank" rel="noopener">ver PDF original</a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <p class="admin-ficha-actions">
                <?php if (!empty($item['regulation_signed_pdf_path'])): ?>
                    <a class="btn" href="/media?f=<?= e(rawurlencode((string)$item['regulation_signed_pdf_path'])) ?>" target="_blank" rel="noopener">
                        Descargar reglamento + hoja de firma
                    </a>
                <?php endif; ?>
                <?php if (!empty($item['regulation_signature_path'])): ?>
                    <a class="btn btn-ghost" href="/media?f=<?= e(rawurlencode((string)$item['regulation_signature_path'])) ?>" target="_blank" rel="noopener">Imagen de firma</a>
                <?php endif; ?>
                <?php if ($sigAtt): ?>
                    <a class="btn btn-ghost" href="/media?f=<?= e(rawurlencode((string)$sigAtt['file_path'])) ?>" target="_blank" rel="noopener">
                        Constancia HTML
                    </a>
                <?php endif; ?>
            </p>
            <p class="muted">El PDF incluye el reglamento original más una hoja final con la firma digital y los datos del sistema (para enviar al proveedor).</p>
        <?php else: ?>
            <p class="muted">El alumno aún no ha firmado el reglamento de esta certificación.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'accesos'): ?>
    <div class="admin-ficha-panel is-active">
        <h3>Credenciales y links operativos</h3>
        <?php
        $moodleDefaultPass = \App\Integrations\MoodleEnrolService::defaultPassword();
        $moodleUserVal = trim((string) ($item['moodle_user'] ?? ''));
        if ($moodleUserVal === '') {
            $emailLocal = strtolower((string) strstr((string) ($item['student_email'] ?? ''), '@', true));
            $moodleUserVal = \App\Integrations\MoodleClient::sanitizeUsername(
                $emailLocal !== '' ? $emailLocal : ('alumno' . $caseId)
            );
        }
        $moodlePassVal = trim((string) ($item['moodle_password'] ?? ''));
        if ($moodlePassVal === '') {
            $moodlePassVal = $moodleDefaultPass;
        }
        $hasExamCreds = trim((string) ($item['folio_id'] ?? '')) !== ''
            && trim((string) ($item['access_key'] ?? '')) !== '';
        $hasInventoryAssigned = !empty($item['inventory_code_id']) || $hasExamCreds;
        $accessMailSent = !empty($access_mail_sent);
        $accessSendLabel = $accessMailSent ? 'Reenviar acceso' : 'Enviar acceso';
        $accessSendConfirm = $accessMailSent
            ? '¿Guardar folio/clave y reenviar el correo de acceso al alumno?'
            : '¿Guardar folio/clave y enviar el correo de acceso al alumno?';
        $isLinguaskillCase = (bool) preg_match('/linguaskill/i', (string) (
            ($item['protocol_code'] ?? '') . ' '
            . ($item['protocol_name'] ?? '') . ' '
            . ($item['certification_code'] ?? '') . ' '
            . ($item['certification_name'] ?? '') . ' '
            . ($item['provider_code'] ?? '') . ' '
            . ($item['provider_name'] ?? '')
        ));
        ?>

        <form id="caseAccessSaveForm" method="post" action="/admin/cases/update" class="hidden-form">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <input type="hidden" name="tab" value="accesos">
        </form>
        <form id="caseAssignInventoryForm" method="post" action="/admin/cases/assign-inventory" class="hidden-form">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <input type="hidden" name="tab" value="accesos">
        </form>
        <form id="caseMoodleEnrolForm" method="post" action="/admin/cases/moodle-enrol" class="hidden-form">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <input type="hidden" name="tab" value="accesos">
        </form>
        <form id="caseMoodleResetForm" method="post" action="/admin/cases/moodle-reset-password" class="hidden-form">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <input type="hidden" name="tab" value="accesos">
        </form>

        <div class="case-access-row">
            <label>Folio / ID<input form="caseAccessSaveForm" name="folio_id" value="<?= e($item['folio_id'] ?? '') ?>"></label>
            <label>Clave<input form="caseAccessSaveForm" name="access_key" value="<?= e($item['access_key'] ?? '') ?>"></label>
            <div class="case-inline-actions">
                <?php if (!empty($item['uses_inventory']) && !$hasInventoryAssigned): ?>
                    <button class="btn btn-ghost" type="submit" form="caseAssignInventoryForm"
                            onclick="return confirm('¿Asignar un código de inventario y enviar acceso al alumno?');">
                        Asignar código
                    </button>
                <?php endif; ?>
                <button class="btn" type="submit" form="caseAccessSaveForm"
                        formaction="/admin/cases/resend-access"
                        onclick="return confirm(<?= json_encode($accessSendConfirm, JSON_UNESCAPED_UNICODE) ?>);">
                    <?= e($accessSendLabel) ?>
                </button>
            </div>
        </div>

        <?php if ($requiresZoom): ?>
            <div class="case-access-row">
                <label class="field-wide">Zoom<input form="caseAccessSaveForm" name="zoom_url" value="<?= e($item['zoom_url'] ?? '') ?>" placeholder="https://…"></label>
            </div>
        <?php endif; ?>
        <?php if ($isLinguaskillCase): ?>
            <div class="case-access-row">
                <label>Doc prep (sin acceso)<input form="caseAccessSaveForm" name="prep_doc_url" value="<?= e($item['prep_doc_url'] ?? '') ?>" placeholder="https://…"></label>
                <label>Doc con acceso / token<input form="caseAccessSaveForm" name="access_doc_url" value="<?= e($item['access_doc_url'] ?? '') ?>" placeholder="https://…"></label>
            </div>
        <?php endif; ?>

        <div class="case-access-row">
            <label>Moodle user<input form="caseAccessSaveForm" name="moodle_user" value="<?= e($moodleUserVal) ?>" placeholder="se propone desde el e-mail"></label>
            <label>Moodle password
                <input form="caseAccessSaveForm" name="moodle_password" value="<?= e($moodlePassVal) ?>">
            </label>
            <div class="case-inline-actions">
                <button class="btn btn-ghost" type="submit" form="caseMoodleEnrolForm"
                        onclick="return confirm('¿Crear/matricular en Moodle con usuario y clave <?= e($moodleDefaultPass) ?>? Se avisará al alumno por correo.');">
                    Sincronizar Moodle
                </button>
                <button class="btn btn-ghost" type="submit" form="caseMoodleResetForm"
                        onclick="return confirm('¿Restablecer la contraseña Moodle a <?= e($moodleDefaultPass) ?> y avisar al alumno por correo?');">
                    Restablecer contraseña
                </button>
            </div>
        </div>

        <div class="admin-ficha-actions">
            <button class="btn btn-ghost" type="submit" form="caseAccessSaveForm">Guardar credenciales</button>
        </div>

        <?php if (!empty($item['uses_inventory'])): ?>
            <p class="muted" style="margin-top:0.75rem">
                Protocolo con inventario.
                <?php if (!empty($item['inventory_code_id'])): ?>
                    Código asignado #<?= (int)$item['inventory_code_id'] ?>
                    (folio <code><?= e($item['folio_id'] ?? '') ?></code>).
                <?php elseif ($hasExamCreds): ?>
                    Folio/clave capturados. Usa <strong><?= e($accessSendLabel) ?></strong> para notificar (se guardan al enviar).
                <?php else: ?>
                    Aún sin código — carga stock en <a href="/admin/inventory">Inventario</a> y usa <strong>Asignar código</strong>, o captura Folio/Clave y pulsa <strong>Enviar acceso</strong>.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="muted" style="margin-top:0.75rem">
                Escribe Folio/ID y Clave y pulsa <strong><?= e($accessSendLabel) ?></strong>
                (se guardan y se envían juntos). Solo cambia la clave si reagendas el examen.
                Tras el pago OpenPay (o “Confirmar pago”) se intenta automáticamente Moodle si hay cursos vinculados.
            </p>
        <?php endif; ?>

        <h4 style="margin-top:1.5rem">Acceso Moodle (6 meses) y prórrogas</h4>
        <?php
        $moodle_enrolments = $moodle_enrolments ?? [];
        $course_prorrogas = $course_prorrogas ?? [];
        ?>
        <?php if ($moodle_enrolments): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Curso</th>
                        <th>Vigencia</th>
                        <th>Estatus</th>
                        <th>Prórroga</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($moodle_enrolments as $enrol): ?>
                        <tr>
                            <td><?= e($enrol['course_name'] ?? '') ?></td>
                            <td><?= e($enrol['access_starts_at'] ?? '') ?> → <?= e($enrol['access_ends_at'] ?? '') ?></td>
                            <td><?= e($enrol['status'] ?? '') ?></td>
                            <td>
                                <?php if ($enrol['prorroga_price'] !== null && $enrol['prorroga_price'] !== ''): ?>
                                    <?= e(\App\Support\Str::money((float)$enrol['prorroga_price'])) ?> / +6 meses
                                <?php else: ?>
                                    <span class="muted">Sin precio</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">Aún no hay matrículas Moodle registradas para este caso.</p>
        <?php endif; ?>

        <?php
        $pendingProrrogas = array_values(array_filter(
            $course_prorrogas,
            static fn (array $p): bool => in_array(($p['status'] ?? ''), ['pending', 'proof_uploaded'], true)
        ));
        ?>
        <?php if ($pendingProrrogas): ?>
            <h4>Prórrogas por confirmar</h4>
            <?php foreach ($pendingProrrogas as $pr): ?>
                <div class="note" style="margin:0.6rem 0">
                    <p>
                        #<?= (int)$pr['id'] ?> · <?= e($pr['course_name'] ?? '') ?>
                        · $<?= e(number_format((float)($pr['amount'] ?? 0), 2)) ?>
                        · <?= e($pr['status'] ?? '') ?>
                        <?= !empty($pr['payment_method']) ? ' · ' . e($pr['payment_method']) : '' ?>
                        <?php if (!empty($pr['payment_proof_path'])): ?>
                            · <a href="/media?f=<?= e(rawurlencode((string)$pr['payment_proof_path'])) ?>" target="_blank" rel="noopener">ver comprobante</a>
                        <?php endif; ?>
                    </p>
                    <form method="post" action="/admin/prorrogas/confirm" class="admin-ficha-actions"
                          onsubmit="return confirm('¿Confirmar prórroga y extender Moodle 6 meses?');">
                        <input type="hidden" name="prorroga_id" value="<?= (int)$pr['id'] ?>">
                        <input type="hidden" name="payment_method" value="<?= e($pr['payment_method'] ?? 'transfer') ?>">
                        <input type="hidden" name="redirect" value="/admin/cases/view?id=<?= $caseId ?>&amp;tab=accesos">
                        <button class="btn" type="submit">Confirmar prórroga</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'resultados'): ?>
    <div class="admin-ficha-panel is-active">
        <h3>Resultados del examen</h3>
        <?php
        $outcome = (string) ($item['exam_outcome'] ?? 'pending');
        $outcomeLabel = match ($outcome) {
            'delivered' => 'Entregados',
            'invalidated' => 'Invalidado',
            default => 'Pendiente',
        };
        $isItepCase = (bool) preg_match('/itep/i', (string) (
            ($item['protocol_code'] ?? '') . ' '
            . ($item['protocol_name'] ?? '') . ' '
            . ($item['certification_code'] ?? '') . ' '
            . ($item['certification_name'] ?? '') . ' '
            . ($item['provider_code'] ?? '') . ' '
            . ($item['provider_name'] ?? '')
        ));
        ?>
        <p>Estatus: <strong><?= e($outcomeLabel) ?></strong>
            <?php if ($outcome === 'invalidated' && !empty($item['invalidation_reason'])): ?>
                · Motivo: <?= e($item['invalidation_reason']) ?>
            <?php endif; ?>
        </p>
        <form method="post" action="/admin/cases/exam-results" class="stack form-grid">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <input type="hidden" name="tab" value="resultados">
            <input type="hidden" name="action" value="deliver">
            <?php if (!$isItepCase): ?>
                <label>URL resultados
                    <input name="results_url" value="<?= e($item['results_url'] ?? '') ?>" placeholder="https://…">
                </label>
            <?php else: ?>
                <input type="hidden" name="results_url" value="">
                <p class="muted field-wide" style="margin:0">iTEP: registra solo Score report y Certificate (URLs completas con https://).</p>
            <?php endif; ?>
            <label>URL Score report
                <input name="score_url" value="<?= e($item['score_url'] ?? '') ?>" placeholder="https://…">
                <span class="muted" style="font-weight:400;display:block;margin-top:0.25rem">Pega la URL completa, p. ej. https://www.itepexam.com/…</span>
            </label>
            <label>URL Certificate
                <input name="certificate_url" value="<?= e($item['certificate_url'] ?? '') ?>" placeholder="https://…">
                <span class="muted" style="font-weight:400;display:block;margin-top:0.25rem">Pega la URL completa del certificado.</span>
            </label>
            <label>Plantilla de correo
                <select name="template_code">
                    <option value="itep_resultados">itep_resultados</option>
                    <?php foreach (($mail_templates ?? []) as $tpl): ?>
                        <?php if (($tpl['audience'] ?? '') === 'student' && ($tpl['code'] ?? '') !== 'itep_resultados'): ?>
                            <option value="<?= e($tpl['code']) ?>"><?= e($tpl['code']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </label>
            <p class="muted field-wide" style="margin:0">Al guardar se publican los enlaces en la ficha del alumno y se envía el correo automáticamente.</p>
            <div class="admin-ficha-actions"><button class="btn" type="submit">Guardar y enviar por correo</button></div>
        </form>
        <hr>
        <form method="post" action="/admin/cases/exam-results" class="stack form-grid"
              onsubmit="return confirm('¿Marcar el examen como invalidado y avisar al alumno por correo?');">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <input type="hidden" name="tab" value="resultados">
            <input type="hidden" name="action" value="invalidate">
            <label class="field-wide">Motivo de invalidación
                <textarea name="invalidation_reason" rows="3" required placeholder="Describe por qué se invalidó el examen"><?= e($item['invalidation_reason'] ?? '') ?></textarea>
            </label>
            <input type="hidden" name="template_code" value="itep_invalidado">
            <div class="admin-ficha-actions"><button class="btn btn-ghost" type="submit">Invalidar y enviar por correo</button></div>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'pago'): ?>
    <div class="admin-ficha-panel is-active">
        <h3>OpenPay — CLABE SPEI única</h3>
        <?php if (!empty($item['openpay_clabe'])): ?>
            <ul>
                <li><strong>Beneficiario:</strong> <?= e(\App\Config\Env::get('OPENPAY_BENEFICIARY_NAME', 'Instituto DOCEO') ?? 'Instituto DOCEO') ?></li>
                <li><strong>CLABE:</strong> <code><?= e($item['openpay_clabe']) ?></code></li>
                <li><strong>Banco:</strong> <?= e($item['openpay_bank'] ?? '') ?></li>
                <li><strong>Referencia:</strong> <?= e($item['openpay_reference'] ?? '') ?></li>
                <li><strong>Monto:</strong> $<?= e(number_format((float)($item['openpay_amount'] ?? 0), 2)) ?></li>
                <li><strong>Estatus OpenPay:</strong> <?= e($item['openpay_status'] ?? '') ?><?= $opPaid ? ' · confirmado' : '' ?></li>
                <li><strong>Charge ID:</strong> <?= e($item['openpay_charge_id'] ?? '') ?></li>
            </ul>
            <p class="admin-ficha-actions">
                <a class="btn btn-ghost" href="/pago/spei?id=<?= $caseId ?>">Ficha SPEI Doceo</a>
                <?php if (!empty($item['openpay_pdf_url'])): ?>
                    <a class="btn btn-ghost" href="<?= e($item['openpay_pdf_url']) ?>" target="_blank" rel="noopener">PDF OpenPay</a>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="muted">Aún no hay cargo SPEI. Genera la CLABE para este alumno.</p>
        <?php endif; ?>
        <form method="post" action="/admin/cases/openpay-spei" class="admin-ficha-actions">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <input type="hidden" name="tab" value="pago">
            <button class="btn" type="submit"><?= !empty($item['openpay_clabe']) ? 'Reenviar instrucciones' : 'Generar CLABE OpenPay' ?></button>
            <?php if (!empty($item['openpay_clabe']) && !$opPaid): ?>
                <label class="check"><input type="checkbox" name="force_new" value="1"> Forzar nueva CLABE</label>
            <?php endif; ?>
        </form>
        <p class="muted">Webhook: <code>POST <?= e(rtrim((string)(\App\Config\Env::get('APP_URL', 'https://pdv.institutodoceo.com') ?? ''), '/')) ?>/webhooks/openpay</code>
            · <a href="/admin/openpay">configurar</a></p>

        <h4 style="margin-top:1.5rem">Estado de pago</h4>
        <?php if ($opPaid): ?>
            <p class="alert alert-ok">
                Pago confirmado
                <?= $payMethodLabel !== '' ? ' · ' . e($payMethodLabel) : '' ?>
                <?= !empty($item['payment_confirmed_at']) ? ' · ' . e((string)$item['payment_confirmed_at']) : '' ?>
                <?php if (!empty($item['payment_proof_path'])): ?>
                    · <a href="/media?f=<?= e(rawurlencode((string)$item['payment_proof_path'])) ?>" target="_blank" rel="noopener">ver comprobante</a>
                <?php endif; ?>
            </p>
        <?php elseif ($hasStudentProof): ?>
            <p class="alert alert-warn">
                El alumno subió un comprobante y espera confirmación.
                Revisa el archivo y marca el pago recibido para que pueda continuar (Moodle / códigos).
                <?php if (!empty($item['payment_proof_path'])): ?>
                    · <a href="/media?f=<?= e(rawurlencode((string)$item['payment_proof_path'])) ?>" target="_blank" rel="noopener">ver comprobante</a>
                <?php endif; ?>
                <?= $payMethodLabel !== '' ? ' · método indicado: ' . e($payMethodLabel) : '' ?>
            </p>
        <?php else: ?>
            <p class="alert alert-warn">
                Alumno <strong>pendiente de pago</strong>.
                Si pagó en efectivo o transferencia (sin OpenPay), márcalo aquí para que pueda continuar.
            </p>
        <?php endif; ?>
        <form method="post" action="/admin/cases/mark-payment" enctype="multipart/form-data" class="stack form-grid">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <input type="hidden" name="tab" value="pago">
            <label>Método
                <select name="payment_method" required>
                    <option value="cash" <?= $defaultPayMethod === 'cash' ? 'selected' : '' ?>>Efectivo</option>
                    <option value="transfer" <?= $defaultPayMethod === 'transfer' ? 'selected' : '' ?>>Transferencia bancaria</option>
                    <option value="openpay" <?= $defaultPayMethod === 'openpay' ? 'selected' : '' ?>>OpenPay (manual)</option>
                    <option value="other" <?= $defaultPayMethod === 'other' ? 'selected' : '' ?>>Otro</option>
                </select>
            </label>
            <label>Nota interna<input name="payment_note" placeholder="Ej. transferencia BBVA ref. 1234"></label>
            <label class="field-wide">Comprobante <?= $hasStudentProof ? '(opcional; reemplaza el del alumno)' : '(opcional)' ?>
                <input type="file" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png,.webp">
            </label>
            <div class="admin-ficha-actions">
                <button class="btn" type="submit">
                    <?php if ($opPaid): ?>
                        Actualizar / reconfirmar pago
                    <?php elseif ($hasStudentProof): ?>
                        Confirmar recepción del comprobante
                    <?php else: ?>
                        Marcar pago recibido
                    <?php endif; ?>
                </button>
            </div>
        </form>
        <p class="muted">Esto solo confirma el pago para el alumno (y dispara Moodle/inventario si aplica). No envía correo al proveedor ni genera la exportación.</p>

        <h4 style="margin-top:1.5rem">Pago → exportación → correo al proveedor</h4>
        <p class="muted">
            La plantilla se configura en
            <strong>Admin → Protocolos → “Plantilla solicitud a empresa”</strong>.
            Para UKS/ELET el correo lleva: datos del alumno, fecha/hora, reglamento firmado,
            CSV de registro y el <strong>comprobante Doceo → UKS</strong> (no el pago del alumno a Doceo).
            El correo <strong>no adjunta archivos</strong>; incluye enlaces públicos
            (<code>{{Comprobante URL}}</code>, <code>{{Exportacion URL}}</code>, reglamento).
        </p>
        <p class="muted">
            Plantilla del protocolo:
            <?php if (!empty($item['provider_request_template'])): ?>
                <code><?= e((string)$item['provider_request_template']) ?></code>
            <?php else: ?>
                <em>sin configurar</em> — asóciala en el protocolo para poder solicitar al proveedor.
            <?php endif; ?>
            · Formato exportación: <code><?= e((string)($item['export_format'] ?? 'none')) ?></code>
        </p>
        <p class="muted">
            Pago alumno confirmado:
            <?= !empty($item['payment_confirmed_at']) ? e($item['payment_confirmed_at']) : 'aún no' ?>
            <?php if (!empty($item['payment_proof_path'])): ?>
                · <a href="/media?f=<?= e(rawurlencode((string)$item['payment_proof_path'])) ?>" target="_blank" rel="noopener">comprobante alumno</a>
            <?php endif; ?>
            <?php if (!empty($item['provider_payment_proof_path'])): ?>
                · <a href="/media?f=<?= e(rawurlencode((string)$item['provider_payment_proof_path'])) ?>" target="_blank" rel="noopener">comprobante Doceo→UKS</a>
            <?php endif; ?>
            <?php if (!empty($item['provider_request_sent_at'])): ?>
                · Solicitud enviada: <?= e($item['provider_request_sent_at']) ?>
            <?php endif; ?>
        </p>
        <?php
        $payment_share_url = $payment_share_url ?? '';
        $export_share_url = $export_share_url ?? '';
        $provider_payment_share_url = $provider_payment_share_url ?? '';
        $isUksFlow = str_starts_with(strtoupper((string) ($item['protocol_code'] ?? '')), 'UKS')
            && strtoupper((string) ($item['protocol_code'] ?? '')) !== 'UKS_CENNI';
        ?>
        <?php if ($payment_share_url !== '' || $export_share_url !== '' || $provider_payment_share_url !== ''): ?>
            <div class="inline-form-panel" style="margin-bottom:1rem">
                <h4 style="margin:0 0 0.5rem">Enlaces para la plantilla</h4>
                <?php if ($provider_payment_share_url !== ''): ?>
                    <label class="field-wide">Comprobante Doceo→proveedor · <code>{{Comprobante URL}}</code>
                        <div class="icon-actions" style="margin-top:0.35rem">
                            <input type="text" class="share-url-input" readonly value="<?= e($provider_payment_share_url) ?>" style="width:100%;max-width:36rem;font-size:0.85rem">
                            <button type="button" class="icon-btn js-copy-share" data-url="<?= e($provider_payment_share_url) ?>" title="Copiar">Copiar</button>
                        </div>
                    </label>
                <?php elseif ($payment_share_url !== ''): ?>
                    <label class="field-wide">Comprobante · <code>{{Comprobante URL}}</code>
                        <div class="icon-actions" style="margin-top:0.35rem">
                            <input type="text" class="share-url-input" readonly value="<?= e($payment_share_url) ?>" style="width:100%;max-width:36rem;font-size:0.85rem">
                            <button type="button" class="icon-btn js-copy-share" data-url="<?= e($payment_share_url) ?>" title="Copiar">Copiar</button>
                        </div>
                    </label>
                <?php endif; ?>
                <?php if ($export_share_url !== ''): ?>
                    <label class="field-wide" style="margin-top:0.75rem">Exportación · <code>{{Exportacion URL}}</code>
                        <div class="icon-actions" style="margin-top:0.35rem">
                            <input type="text" class="share-url-input" readonly value="<?= e($export_share_url) ?>" style="width:100%;max-width:36rem;font-size:0.85rem">
                            <button type="button" class="icon-btn js-copy-share" data-url="<?= e($export_share_url) ?>" title="Copiar">Copiar</button>
                        </div>
                    </label>
                <?php endif; ?>
            </div>
            <script>
            document.querySelectorAll('.js-copy-share').forEach((btn) => {
              btn.addEventListener('click', async () => {
                const url = btn.getAttribute('data-url') || '';
                if (!url) return;
                try {
                  await navigator.clipboard.writeText(url);
                  btn.textContent = 'Copiado';
                  setTimeout(() => { btn.textContent = 'Copiar'; }, 1200);
                } catch (e) {
                  window.prompt('Copia este enlace:', url);
                }
              });
            });
            </script>
        <?php endif; ?>
        <form method="post" action="/admin/cases/confirm-payment" enctype="multipart/form-data" class="stack">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <input type="hidden" name="tab" value="pago">
            <label><?= $isUksFlow ? 'Comprobante Doceo → UKS (PDF/imagen)' : 'Comprobante de pago (PDF/imagen)' ?>
                <input type="file" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png,.webp" <?= $isUksFlow ? 'required' : '' ?>>
            </label>
            <button class="btn" type="submit">Confirmar pago y solicitar al proveedor</button>
        </form>
        <?php if (!empty($item['provider_request_template'])): ?>
            <form method="post" action="/admin/cases/send-provider-request" enctype="multipart/form-data" class="stack" style="margin-top:1rem">
                <input type="hidden" name="case_id" value="<?= $caseId ?>">
                <input type="hidden" name="tab" value="pago">
                <p class="muted">Reenvía la plantilla del protocolo (con links de exportación + comprobante Doceo→proveedor).</p>
                <label><?= $isUksFlow ? 'Comprobante Doceo → UKS' : 'Comprobante (opcional; reemplaza el actual)' ?>
                    <input type="file" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png,.webp" <?= $isUksFlow && empty($item['provider_payment_proof_path']) ? 'required' : '' ?>>
                </label>
                <div class="admin-ficha-actions">
                    <button class="btn" type="submit">Enviar solicitud al proveedor</button>
                </div>
            </form>
        <?php endif; ?>
        <div class="admin-ficha-actions" style="margin-top:1rem">
            <?php if (!empty($item['provider_export_path'])): ?>
                <a class="btn btn-ghost" href="/admin/cases/download-export?id=<?= $caseId ?>">Descargar exportación / CSV UKS</a>
            <?php endif; ?>
            <?php if (($item['export_format'] ?? 'none') !== 'none'): ?>
                <form method="post" action="/admin/cases/regenerate-export">
                    <input type="hidden" name="case_id" value="<?= $caseId ?>">
                    <input type="hidden" name="tab" value="pago">
                    <button class="btn btn-ghost" type="submit">Regenerar archivo</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'operacion'): ?>
    <div class="admin-ficha-panel is-active">
        <h3>Reagenda examen → notificar proveedor</h3>
        <p class="muted">
            Guarda la nueva fecha/hora y envía la plantilla <code>reagenda_solicitud</code>
            (se crea sola si no existe; si falla, usa la plantilla de solicitud del protocolo).
            Puedes adjuntar el comprobante que tú pagas al proveedor.
            El alumno también puede pedir reagenda desde su panel.
        </p>
        <?php if (!empty($item['reschedule_date'])): ?>
            <p class="muted">Reagenda actual: <strong><?= e($item['reschedule_date']) ?></strong>
                <?php if (!empty($item['reschedule_time'])): ?> · <?= e($item['reschedule_time']) ?><?php endif; ?></p>
        <?php endif; ?>
        <form method="post" action="/admin/cases/reschedule" enctype="multipart/form-data" class="stack form-grid">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <input type="hidden" name="tab" value="operacion">
            <label>Nueva fecha<input type="date" name="reschedule_date" required value="<?= e($item['reschedule_date'] ?? $item['exam_date'] ?? '') ?>"></label>
            <label>Nueva hora<input type="time" name="reschedule_time" required value="<?= e(substr((string)($item['reschedule_time'] ?? $item['exam_time'] ?? '11:00'), 0, 5)) ?>"></label>
            <label class="field-wide">Motivo / nota<input name="reschedule_reason" placeholder="Ej. conflicto de agenda del alumno"></label>
            <label class="field-wide">Comprobante (opcional)<input type="file" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
            <label class="check field-wide"><input type="checkbox" name="skip_notify" value="1"> Solo guardar fecha, no enviar correo</label>
            <div class="admin-ficha-actions"><button class="btn" type="submit">Guardar reagenda y avisar al proveedor</button></div>
        </form>

        <h4 style="margin-top:1.5rem">Enviar plantilla de correo</h4>
        <p class="muted">Envío manual de cualquier plantilla. Si eliges una de proveedor y subes comprobante, se adjunta al correo.</p>
        <form method="post" action="/admin/cases/send-mail" enctype="multipart/form-data" class="stack form-grid">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <input type="hidden" name="tab" value="operacion">
            <label>Plantilla
                <select name="template_code" required>
                    <option value="">—</option>
                    <?php foreach ($mail_templates as $tpl): ?>
                        <option value="<?= e($tpl['code']) ?>"><?= e($tpl['name']) ?> (<?= e($tpl['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field-wide">Comprobante de pago (opcional)
                <input type="file" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png,.webp">
            </label>
            <div class="admin-ficha-actions"><button class="btn" type="submit">Enviar ahora</button></div>
        </form>

        <?php
        $protoCodeUpper = strtoupper((string) ($item['protocol_code'] ?? ''));
        $showUksPostExam = str_starts_with($protoCodeUpper, 'UKS') && $protoCodeUpper !== 'UKS_CENNI';
        ?>
        <?php if ($showUksPostExam): ?>
            <h4 style="margin-top:1.5rem">Post-examen UKS</h4>
            <p class="muted">
                Al terminar el examen envía el agradecimiento (<code>uks_post_examen</code>):
                cierra el contacto operativo del examen y deja abierta la puerta a dudas CENNI
                (tú puedes registrar el avance desde la pestaña CENNI consultando UKS).
            </p>
            <?php if (!empty($item['exam_presented_at'])): ?>
                <p class="muted">Examen marcado: <?= e((string)$item['exam_presented_at']) ?>
                    <?php if (!empty($item['post_exam_thanks_sent_at'])): ?>
                        · Agradecimiento enviado: <?= e((string)$item['post_exam_thanks_sent_at']) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <form method="post" action="/admin/cases/uks-post-exam-thanks" class="stack">
                <input type="hidden" name="case_id" value="<?= $caseId ?>">
                <input type="hidden" name="tab" value="operacion">
                <div class="admin-ficha-actions">
                    <button class="btn" type="submit">
                        <?= !empty($item['post_exam_thanks_sent_at']) ? 'Reenviar agradecimiento post-examen' : 'Marcar examen y enviar agradecimiento' ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($mail_log): ?>
            <table class="table" style="margin-top:1rem">
                <thead><tr><th>Cuándo</th><th>Plantilla</th><th>Para</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($mail_log as $log): ?>
                    <tr>
                        <td><?= e($log['created_at']) ?></td>
                        <td><?= e($log['template_code'] ?? '') ?></td>
                        <td><?= e($log['to_email']) ?><?php if (!empty($log['cc_email'])): ?><br><small>CC <?= e($log['cc_email']) ?></small><?php endif; ?></td>
                        <td><?= e($log['status']) ?><?php if (!empty($log['error_message'])): ?><br><small class="muted"><?= e($log['error_message']) ?></small><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'cenni'): ?>
    <div class="admin-ficha-panel is-active">
        <?php
        $cenniProcesses = $cenni_processes ?? [];
        $cenniStatuses = $cenni_statuses ?? [];
        $proc = (string) ($item['cenni_process'] ?? 'none');
        $cenniCaseDocs = $cenni_docs_case ?? [];
        $docKindLabels = ['ine' => 'INE', 'curp' => 'CURP', 'cenni' => 'Solicitud CENNI'];
        $reviewedCount = 0;
        $rejectedCount = 0;
        foreach ($cenniCaseDocs as $d) {
            $rs = (string) ($d['review_status'] ?? '');
            if ($rs === 'approved' || $rs === 'rejected') {
                $reviewedCount++;
            }
            if ($rs === 'rejected') {
                $rejectedCount++;
            }
        }
        $allReviewed = $cenniCaseDocs !== [] && $reviewedCount === count($cenniCaseDocs);
        ?>
        <h3>Documentos del alumno</h3>
        <p class="muted">
            Proceso:
            <strong><?= e($cenniProcesses[$proc] ?? $proc) ?></strong>
            <?php if ($proc === 'uks_external'): ?>
                — el alumno sube docs en UKS; aquí registras el avance que consultes en su plataforma.
                Si vencen los 15 días, puede adquirir el producto CENNI vinculado en la ficha de la certificación.
            <?php elseif ($proc === 'doceo_managed'): ?>
                — el alumno sube INE, CURP y solicitud en el portal; aprueba o rechaza cada uno.
            <?php endif; ?>
        </p>

        <?php if ($cenniCaseDocs): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Subido</th>
                        <th>Revisión</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cenniCaseDocs as $doc): ?>
                        <?php
                        $docId = (int) ($doc['id'] ?? 0);
                        $kind = (string) ($doc['kind'] ?? '');
                        $review = (string) ($doc['review_status'] ?? '');
                        $reviewLabel = match ($review) {
                            'approved' => 'Aprobado',
                            'rejected' => 'Rechazado',
                            default => 'Pendiente',
                        };
                        $pillClass = match ($review) {
                            'approved' => 'pill-ok',
                            'rejected' => 'pill-warn',
                            default => 'pill-muted',
                        };
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($docKindLabels[$kind] ?? ($doc['label'] ?? $kind)) ?></strong>
                                <?php if ($review === 'rejected' && !empty($doc['review_notes'])): ?>
                                    <br><small class="muted"><?= e((string)$doc['review_notes']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small class="muted"><?= e((string)($doc['created_at'] ?? '')) ?></small></td>
                            <td><span class="pill <?= e($pillClass) ?>"><?= e($reviewLabel) ?></span></td>
                            <td>
                                <div class="icon-actions">
                                    <a class="icon-btn" href="/media?f=<?= e(rawurlencode((string)$doc['file_path'])) ?>" target="_blank" rel="noopener" title="Ver documento">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <form method="post" action="/admin/cases/cenni-doc-review" class="inline-form">
                                        <input type="hidden" name="case_id" value="<?= $caseId ?>">
                                        <input type="hidden" name="attachment_id" value="<?= $docId ?>">
                                        <input type="hidden" name="review_status" value="approved">
                                        <button class="icon-btn icon-btn-wa" type="submit" title="Aprobar" <?= $review === 'approved' ? 'disabled' : '' ?>>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                        </button>
                                    </form>
                                    <button type="button" class="icon-btn icon-btn-danger" title="Rechazar"
                                            data-cenni-reject="<?= $docId ?>"
                                            aria-expanded="false"
                                            aria-controls="cenni-reject-<?= $docId ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                    </button>
                                </div>
                                <form method="post" action="/admin/cases/cenni-doc-review" class="stack cenni-reject-box" id="cenni-reject-<?= $docId ?>" hidden style="margin-top:0.55rem">
                                    <input type="hidden" name="case_id" value="<?= $caseId ?>">
                                    <input type="hidden" name="attachment_id" value="<?= $docId ?>">
                                    <input type="hidden" name="review_status" value="rejected">
                                    <label>Motivo del rechazo (<?= e($docKindLabels[$kind] ?? $kind) ?>)
                                        <textarea name="review_notes" rows="2" required placeholder="Qué debe corregir el alumno"><?= e((string)($doc['review_notes'] ?? '')) ?></textarea>
                                    </label>
                                    <div class="actions">
                                        <button class="btn btn-ghost" type="submit">Confirmar rechazo</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="post" action="/admin/cases/cenni-notify-docs" class="admin-ficha-actions" style="margin-top:0.85rem"
                  onsubmit="return confirm('¿Guardar el resultado de la revisión y avisar al alumno por correo?');">
                <input type="hidden" name="case_id" value="<?= $caseId ?>">
                <button class="btn" type="submit" <?= $allReviewed ? '' : 'disabled' ?>
                        title="<?= $allReviewed ? 'Guardar revisión y enviar correo' : 'Revisa todos los documentos primero' ?>">
                    Avisar al alumno (revisión de documentos)
                </button>
            </form>
            <?php if (!$allReviewed): ?>
                <p class="muted">Aprueba o rechaza cada documento; luego usa el botón para notificar.</p>
            <?php elseif ($rejectedCount > 0): ?>
                <p class="muted">Hay documentos rechazados: al avisar se marcará “Documentos rechazados” y se enviará <code>cenni_docs_rechazados</code>.</p>
            <?php else: ?>
                <p class="muted">Todos aprobados: al avisar se marcará “En trámite ante la SEP” y se enviará <code>cenni_seguimiento</code>.</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="muted">Aún no hay documentos CENNI subidos por el alumno.</p>
        <?php endif; ?>

        <hr style="margin:1.5rem 0">
        <h3>Seguimiento CENNI</h3>
        <p class="muted">Actualiza folio, enlaces y estatus. El botón guarda en el sistema y envía el correo de la plantilla elegida.</p>
        <form method="post" action="/admin/cases/cenni-status" class="stack form-grid">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <input type="hidden" name="tab" value="cenni">
            <label>Estatus
                <select name="cenni_status">
                    <?php foreach ($cenniStatuses as $code => $label): ?>
                        <?php if ($code === 'none') continue; ?>
                        <option value="<?= e($code) ?>" <?= ($item['cenni_status'] ?? '') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Folio CENNI<input name="cenni_folio" value="<?= e($item['cenni_folio'] ?? '') ?>"></label>
            <label>Link de descarga CENNI<input name="cenni_download_url" value="<?= e($item['cenni_download_url'] ?? '') ?>" placeholder="https://…"></label>
            <label>Página oficial SEP<input name="cenni_sep_url" value="<?= e($item['cenni_sep_url'] ?? '') ?>" placeholder="https://www.gob.mx/…"></label>
            <label class="field-wide">Notas / indicaciones al alumno
                <textarea name="cenni_notes" rows="3" placeholder="Notas para el alumno o seguimiento interno"><?= e($item['cenni_notes'] ?? '') ?></textarea>
            </label>
            <label>Plantilla de correo
                <select name="template_code">
                    <?php
                    $preferred = match ((string) ($item['cenni_status'] ?? '')) {
                        'issued' => 'cenni_emitido',
                        'docs_rejected' => 'cenni_docs_rechazados',
                        default => 'cenni_seguimiento',
                    };
                    $cenniTplCodes = ['cenni_seguimiento', 'cenni_docs_rechazados', 'cenni_emitido'];
                    ?>
                    <?php foreach ($cenniTplCodes as $code): ?>
                        <option value="<?= e($code) ?>" <?= $preferred === $code ? 'selected' : '' ?>><?= e($code) ?></option>
                    <?php endforeach; ?>
                    <?php foreach ($mail_templates as $tpl): ?>
                        <?php if (($tpl['audience'] ?? '') !== 'student') continue; ?>
                        <?php if (in_array(($tpl['code'] ?? ''), $cenniTplCodes, true)) continue; ?>
                        <option value="<?= e($tpl['code']) ?>"><?= e($tpl['code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="admin-ficha-actions">
                <button class="btn" type="submit">Guardar y avisar al alumno</button>
            </div>
        </form>
    </div>
    <script>
    (() => {
      document.querySelectorAll('[data-cenni-reject]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = btn.getAttribute('data-cenni-reject');
          const box = document.getElementById('cenni-reject-' + id);
          if (!box) return;
          const open = box.hasAttribute('hidden');
          box.toggleAttribute('hidden', !open);
          btn.setAttribute('aria-expanded', open ? 'true' : 'false');
          if (open) box.querySelector('textarea')?.focus();
        });
      });
    })();
    </script>
    <?php endif; ?>

    <?php if ($tab === 'adjuntos'): ?>
    <div class="admin-ficha-panel is-active">
        <h3>Adjuntos del caso</h3>
        <?php if ($attachments): ?>
            <ul>
                <?php foreach ($attachments as $att): ?>
                    <li>
                        <?= e($att['kind']) ?> — <?= e($att['label'] ?? basename((string)$att['file_path'])) ?>
                        · <a href="/media?f=<?= e(rawurlencode((string)$att['file_path'])) ?>" target="_blank" rel="noopener">ver</a>
                        <small class="muted"><?= e($att['created_at']) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">No hay adjuntos en este caso.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'protocolo'): ?>
    <div class="admin-ficha-panel is-active">
        <h3>Progreso del protocolo</h3>
        <ol class="protocol-timeline protocol-timeline--progress">
            <?php
            $lastPhase = null;
            foreach ($steps as $step):
                $phase = (string) $step['phase'];
                if ($phase !== $lastPhase):
                    $lastPhase = $phase;
            ?>
                <li class="protocol-phase-label"><?= e($phases[$phase] ?? $phase) ?></li>
            <?php endif; ?>
                <li class="protocol-step status-<?= e($step['status']) ?>">
                    <div class="protocol-step-head">
                        <span class="protocol-step-num"><?= (int)$step['sort_order'] ?></span>
                        <strong><?= e($step['title']) ?></strong>
                        <span class="pill"><?= e($statusLabels[$step['status']] ?? $step['status']) ?></span>
                        <span class="pill"><?= e($responsibles[$step['responsible']] ?? $step['responsible']) ?></span>
                    </div>
                    <?php if (!empty($step['description'])): ?>
                        <p class="muted"><?= e($step['description']) ?></p>
                    <?php endif; ?>
                    <?php if ($step['status'] === 'current' && $item['status'] === 'in_progress'): ?>
                        <form method="post" action="/admin/cases/complete-step" class="stack" style="margin-top:0.75rem">
                            <input type="hidden" name="case_id" value="<?= $caseId ?>">
                            <input type="hidden" name="tab" value="protocolo">
                            <input type="hidden" name="case_step_id" value="<?= (int)$step['id'] ?>">
                            <label>Nota (opcional)<input name="notes" placeholder="Ej. correo enviado a UKS"></label>
                            <button class="btn" type="submit">Marcar paso como hecho →</button>
                        </form>
                    <?php elseif ($step['status'] === 'done' && !empty($step['completed_at'])): ?>
                        <p class="muted">Completado: <?= e($step['completed_at']) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php endif; ?>
</section>
