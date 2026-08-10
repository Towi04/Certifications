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
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0"><?= e($title) ?></h2>
            <p class="muted" style="margin:0.35rem 0 0">
                <?= e($item['certification_code']) ?> · <?= e($item['certification_name']) ?><br>
                Protocolo: <?= e($item['protocol_name']) ?> ·
                Estado: <?= e($item['status']) ?> ·
                Export: <?= e($exportLabel) ?>
            </p>
        </div>
        <a class="btn btn-ghost" href="/admin/cases">Volver</a>
    </div>
    <?php if (!empty($info)): ?><p class="alert alert-ok"><?= e($info) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
</section>

<section class="note">
    <h3>Datos del alumno y agenda</h3>
    <form method="post" action="/admin/cases/update" class="stack form-grid">
        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
        <label>Nombre(s)<input name="student_name" required value="<?= e($item['student_name'] ?? '') ?>"></label>
        <label>Apellido paterno<input name="student_last_name_p" value="<?= e($item['student_last_name_p'] ?? '') ?>"></label>
        <label>Apellido materno<input name="student_last_name_m" value="<?= e($item['student_last_name_m'] ?? '') ?>"></label>
        <label>E-mail<input type="email" name="student_email" required value="<?= e($item['student_email'] ?? '') ?>"></label>
        <label>Teléfono<input name="student_phone" value="<?= e($item['student_phone'] ?? '') ?>"></label>
        <label>CC (TR)<input type="email" name="cc_email" value="<?= e($item['cc_email'] ?? $item['partner_email'] ?? '') ?>" placeholder="correo del TR"></label>
        <label>CURP<input name="student_curp" value="<?= e($item['student_curp'] ?? '') ?>"></label>
        <label>Fecha nacimiento<input type="date" name="student_birth_date" value="<?= e($item['student_birth_date'] ?? '') ?>"></label>
        <label>Sexo
            <select name="student_sex">
                <?php $sx = (string)($item['student_sex'] ?? ''); ?>
                <option value="">—</option>
                <option value="F" <?= $sx === 'F' || str_starts_with(strtolower($sx), 'f') ? 'selected' : '' ?>>Femenino</option>
                <option value="M" <?= $sx === 'M' || str_starts_with(strtolower($sx), 'm') ? 'selected' : '' ?>>Masculino</option>
            </select>
        </label>
        <label>Nacionalidad<input name="student_nationality" value="<?= e($item['student_nationality'] ?? 'MEX') ?>"></label>
        <label>Fecha examen<input type="date" name="exam_date" value="<?= e($item['exam_date'] ?? '') ?>"></label>
        <label>Hora examen<input name="exam_time" value="<?= e($item['exam_time'] ?? '') ?>" placeholder="11:00"></label>
        <label>Reagenda fecha<input type="date" name="reschedule_date" value="<?= e($item['reschedule_date'] ?? '') ?>"><small class="muted">Solo guarda; para avisar al proveedor usa la sección “Reagenda” abajo.</small></label>
        <label>Reagenda hora<input name="reschedule_time" value="<?= e($item['reschedule_time'] ?? '') ?>"></label>
        <label>Notas<textarea name="notes" rows="2"><?= e($item['notes'] ?? '') ?></textarea></label>
        <div class="actions"><button class="btn" type="submit">Guardar datos</button></div>
    </form>
</section>

<section class="note">
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
            <span class="pill pill-ok">Firmado</span>
            el <?= e((string)$item['regulation_signed_at']) ?>
            por <strong><?= e((string)($item['regulation_signer_name'] ?? '')) ?></strong>
        </p>
        <?php if ($regulation_doc): ?>
            <p>
                Documento:
                <strong><?= e((string)($regulation_doc['title'] ?? 'Reglamento')) ?></strong>
                <?php if (!empty($regulation_doc['version'])): ?>
                    <span class="muted">v<?= e((string)$regulation_doc['version']) ?></span>
                <?php endif; ?>
                <?php if (!empty($regulation_doc['file_path'])): ?>
                    · <a href="/media?f=<?= e(rawurlencode((string)$regulation_doc['file_path'])) ?>" target="_blank" rel="noopener">ver PDF firmado/aceptado</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if ($sigAtt): ?>
            <p>
                Constancia de firma:
                <a href="/media?f=<?= e(rawurlencode((string)$sigAtt['file_path'])) ?>" target="_blank" rel="noopener">
                    <?= e((string)($sigAtt['label'] ?? 'ver constancia')) ?>
                </a>
            </p>
        <?php endif; ?>
        <p class="muted">Úsala si el alumno dice que no se le informó: hay registro de lectura/aceptación con nombre, fecha e IP.</p>
    <?php else: ?>
        <p class="muted">El alumno aún no ha firmado el reglamento de esta certificación.</p>
    <?php endif; ?>
</section>

<section class="note">
    <h3>Credenciales y links operativos</h3>
    <form method="post" action="/admin/cases/update" class="stack form-grid">
        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
        <label>Folio / ID<input name="folio_id" value="<?= e($item['folio_id'] ?? '') ?>"></label>
        <label>Clave<input name="access_key" value="<?= e($item['access_key'] ?? '') ?>"></label>
        <label>Zoom (TOEFL)<input name="zoom_url" value="<?= e($item['zoom_url'] ?? '') ?>"></label>
        <label>Doc prep (sin acceso)<input name="prep_doc_url" value="<?= e($item['prep_doc_url'] ?? '') ?>"></label>
        <label>Doc con acceso / token<input name="access_doc_url" value="<?= e($item['access_doc_url'] ?? '') ?>"></label>
        <label>Moodle user<input name="moodle_user" value="<?= e($item['moodle_user'] ?? '') ?>"></label>
        <label>Moodle password<input name="moodle_password" value="<?= e($item['moodle_password'] ?? '') ?>"></label>
        <label>URL resultados<input name="results_url" value="<?= e($item['results_url'] ?? '') ?>"></label>
        <label>Motivo cancelación<textarea name="cancel_reason" rows="2"><?= e($item['cancel_reason'] ?? '') ?></textarea></label>
        <div class="actions">
            <button class="btn" type="submit">Guardar credenciales</button>
        </div>
    </form>
    <form method="post" action="/admin/cases/moodle-enrol" class="actions" style="margin-top:0.75rem"
          onsubmit="return confirm('¿Crear/matricular usuario Moodle para los cursos ligados a esta certificación?');">
        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
        <button class="btn btn-ghost" type="submit">Sincronizar Moodle (crear usuario + enrol)</button>
    </form>
    <p class="muted">Tras el pago OpenPay (o “Confirmar pago”) se intenta automáticamente si la certificación tiene cursos Moodle vinculados.</p>
</section>

<section class="note">
    <h3>OpenPay — CLABE SPEI única</h3>
    <?php
    $opPaid = !empty($item['payment_confirmed_at']) || in_array(strtolower((string)($item['openpay_status'] ?? '')), ['completed', 'paid'], true);
    ?>
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
        <p class="actions">
            <a class="btn btn-ghost" href="/pago/spei?id=<?= (int)$item['id'] ?>">Ficha SPEI Doceo</a>
            <?php if (!empty($item['openpay_pdf_url'])): ?>
                <a class="btn btn-ghost" href="<?= e($item['openpay_pdf_url']) ?>" target="_blank" rel="noopener">PDF OpenPay</a>
            <?php endif; ?>
        </p>
    <?php else: ?>
        <p class="muted">Aún no hay cargo SPEI. Genera la CLABE para este alumno.</p>
    <?php endif; ?>
    <form method="post" action="/admin/cases/openpay-spei" class="actions">
        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
        <button class="btn" type="submit"><?= !empty($item['openpay_clabe']) ? 'Reenviar instrucciones' : 'Generar CLABE OpenPay' ?></button>
        <?php if (!empty($item['openpay_clabe']) && !$opPaid): ?>
            <label class="check"><input type="checkbox" name="force_new" value="1"> Forzar nueva CLABE</label>
        <?php endif; ?>
    </form>
    <p class="muted">Webhook: <code>POST <?= e(rtrim((string)(\App\Config\Env::get('APP_URL', 'https://pdv.institutodoceo.com') ?? ''), '/')) ?>/webhooks/openpay</code>
        · <a href="/admin/openpay">configurar</a></p>
</section>

<section class="note">
    <h3>Pago → exportación → correo al proveedor</h3>
    <p class="muted">
        La plantilla que se manda al proveedor se configura en
        <strong>Admin → Protocolos → “Plantilla solicitud a empresa”</strong>
        (ej. <code>uks_solicitud</code>), no en la certificación.
        El check “Adjuntar exportación…” en la plantilla adjunta el CSV/Excel del registro del alumno
        (no el PDF del reglamento). El comprobante de pago se adjunta aparte si la audiencia es “Proveedor”.
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
        Pago confirmado:
        <?= !empty($item['payment_confirmed_at']) ? e($item['payment_confirmed_at']) : 'aún no' ?>
        <?php if (!empty($item['payment_proof_path'])): ?>
            · <a href="/media?f=<?= e(rawurlencode((string)$item['payment_proof_path'])) ?>" target="_blank" rel="noopener">ver comprobante</a>
        <?php endif; ?>
        <?php if (!empty($item['provider_request_sent_at'])): ?>
            · Solicitud enviada: <?= e($item['provider_request_sent_at']) ?>
        <?php endif; ?>
    </p>
    <form method="post" action="/admin/cases/confirm-payment" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
        <label>Comprobante de pago (PDF/imagen)<input type="file" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
        <button class="btn" type="submit">Confirmar pago y solicitar al proveedor</button>
    </form>
    <?php if (!empty($item['provider_request_template'])): ?>
        <form method="post" action="/admin/cases/send-provider-request" enctype="multipart/form-data" class="stack" style="margin-top:1rem">
            <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
            <p class="muted">Reenvía solo la plantilla del protocolo (con exportación + comprobante). Útil si ya confirmaste pago y quieres volver a pedir el registro.</p>
            <label>Comprobante (opcional; reemplaza el actual)
                <input type="file" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png,.webp">
            </label>
            <div class="actions">
                <button class="btn" type="submit">Enviar solicitud al proveedor</button>
            </div>
        </form>
    <?php endif; ?>
    <div class="actions" style="margin-top:1rem">
        <?php if (!empty($item['provider_export_path'])): ?>
            <a class="btn btn-ghost" href="/admin/cases/download-export?id=<?= (int)$item['id'] ?>">Descargar exportación</a>
        <?php endif; ?>
        <?php if (($item['export_format'] ?? 'none') !== 'none'): ?>
            <form method="post" action="/admin/cases/regenerate-export">
                <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
                <button class="btn btn-ghost" type="submit">Regenerar archivo</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="note">
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
        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
        <label>Nueva fecha<input type="date" name="reschedule_date" required value="<?= e($item['reschedule_date'] ?? $item['exam_date'] ?? '') ?>"></label>
        <label>Nueva hora<input type="time" name="reschedule_time" required value="<?= e(substr((string)($item['reschedule_time'] ?? $item['exam_time'] ?? '11:00'), 0, 5)) ?>"></label>
        <label class="field-wide">Motivo / nota<input name="reschedule_reason" placeholder="Ej. conflicto de agenda del alumno"></label>
        <label class="field-wide">Comprobante (opcional)<input type="file" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
        <label class="check field-wide"><input type="checkbox" name="skip_notify" value="1"> Solo guardar fecha, no enviar correo</label>
        <div class="actions"><button class="btn" type="submit">Guardar reagenda y avisar al proveedor</button></div>
    </form>
</section>

<section class="note">
    <h3>Seguimiento CENNI</h3>
    <?php
    $cenniProcesses = $cenni_processes ?? [];
    $cenniStatuses = $cenni_statuses ?? [];
    $proc = (string) ($item['cenni_process'] ?? 'none');
    ?>
    <p class="muted">
        Proceso del producto:
        <strong><?= e($cenniProcesses[$proc] ?? $proc) ?></strong>
        <?php if ($proc === 'uks_external'): ?>
            — el alumno sube docs en UKS (constancia/QR). Aquí solo registras el avance que ves en la plataforma UKS / SEP.
        <?php elseif ($proc === 'doceo_managed'): ?>
            — el alumno sube docs en el portal alumno; Doceo gestiona ante la SEP.
        <?php endif; ?>
    </p>
    <?php if ($proc !== 'none'): ?>
        <form method="post" action="/admin/cases/cenni-status" class="stack form-grid">
            <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
            <label>Estatus
                <select name="cenni_status">
                    <?php foreach ($cenniStatuses as $code => $label): ?>
                        <?php if ($code === 'none') continue; ?>
                        <option value="<?= e($code) ?>" <?= ($item['cenni_status'] ?? '') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Folio CENNI<input name="cenni_folio" value="<?= e($item['cenni_folio'] ?? '') ?>"></label>
            <label>Notas internas<textarea name="cenni_notes" rows="2"><?= e($item['cenni_notes'] ?? '') ?></textarea></label>
            <label class="check"><input type="checkbox" name="notify_student" value="1" checked> Avisar al alumno por correo</label>
            <div class="actions"><button class="btn" type="submit">Guardar estatus CENNI</button></div>
        </form>
    <?php endif; ?>
</section>

<section class="note">
    <h3>Enviar plantilla de correo</h3>
    <p class="muted">Envío manual de cualquier plantilla. Si eliges una de proveedor y subes comprobante, se adjunta al correo.</p>
    <form method="post" action="/admin/cases/send-mail" enctype="multipart/form-data" class="stack form-grid">
        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
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
        <div class="actions"><button class="btn" type="submit">Enviar ahora</button></div>
    </form>
    <?php if ($mail_log): ?>
        <table class="table" style="margin-top:1rem">
            <thead><tr><th>Cuándo</th><th>Plantilla</th><th>Para</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($mail_log as $log): ?>
                <tr>
                    <td><?= e($log['created_at']) ?></td>
                    <td><?= e($log['template_code'] ?? '') ?></td>
                    <td><?= e($log['to_email']) ?><?php if (!empty($log['cc_email'])): ?><br><small>CC <?= e($log['cc_email']) ?></small><?php endif; ?></td>
                    <td><?= e($log['status']) ?><?php if (!empty($log['error_message'])): ?><br><small><?= e($log['error_message']) ?></small><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php if ($attachments): ?>
<section class="note">
    <h3>Adjuntos del caso</h3>
    <ul>
        <?php foreach ($attachments as $att): ?>
            <li>
                <?= e($att['kind']) ?> — <?= e($att['label'] ?? basename((string)$att['file_path'])) ?>
                · <a href="/media?f=<?= e(rawurlencode((string)$att['file_path'])) ?>" target="_blank" rel="noopener">ver</a>
                <small class="muted"><?= e($att['created_at']) ?></small>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="note">
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
                        <input type="hidden" name="case_id" value="<?= (int)$item['id'] ?>">
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
</section>
