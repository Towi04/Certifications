<?php

declare(strict_types=1);

/**
 * Prueba E2E del flujo iTEP Academic-Plus:
 * registro → pago → Moodle/inventario → resultados → CENNI (rechazo + emisión).
 *
 * Uso: php bin/itep-e2e-test.php
 */

use App\Catalog\CatalogRepository;
use App\Config\Env;
use App\Database\Connection;
use App\Mail\CaseMailService;

require dirname(__DIR__) . '/src/bootstrap.php';

$repo = new CatalogRepository();
$mail = new CaseMailService($repo);
$pdo = Connection::get();
$repo->ensureInventoryAndResultColumns();

$override = trim((string) (Env::get('MAIL_OVERRIDE_TO', '') ?? ''));
$report = [
    'mail_override' => $override,
    'smtp_transport' => Env::get('SMTP_TRANSPORT', ''),
    'steps' => [],
    'mails' => [],
    'errors' => [],
];

$ok = static function (string $label, mixed $detail = null) use (&$report): void {
    $report['steps'][] = ['ok' => true, 'label' => $label, 'detail' => $detail];
    echo "[OK] {$label}\n";
    if ($detail !== null) {
        echo '     ' . (is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "\n";
    }
};

$fail = static function (string $label, Throwable $e) use (&$report): void {
    $report['steps'][] = ['ok' => false, 'label' => $label, 'error' => $e->getMessage()];
    $report['errors'][] = $label . ': ' . $e->getMessage();
    echo "[FAIL] {$label}: " . $e->getMessage() . "\n";
};

// --- Configuración previa ---
try {
    // Liberar códigos huérfanos "assigned" sin caso
    $pdo->exec(
        "UPDATE inventory_codes
         SET status = 'available', assigned_case_id = NULL, assigned_at = NULL
         WHERE status = 'assigned' AND (assigned_case_id IS NULL OR assigned_case_id = 0)"
    );

    // Asegurar pasos del protocolo con títulos que matchean keywords
    $proto = $pdo->query("SELECT id FROM protocols WHERE code = 'ITEP_INVENTORY' LIMIT 1")->fetch();
    if (!$proto) {
        throw new RuntimeException('Protocolo ITEP_INVENTORY no encontrado');
    }
    $protocolId = (int) $proto['id'];
    $desired = [
        [1, 'pre_exam', 'Registro del alumno', 'student'],
        [2, 'pre_exam', 'Pago confirmado', 'admin'],
        [3, 'pre_exam', 'Acceso al curso Moodle', 'system'],
        [4, 'pre_exam', 'Códigos de examen inventario', 'system'],
        [5, 'during_exam', 'Presentar examen', 'student'],
        [6, 'post_exam', 'Recepción de resultados y certificado', 'admin'],
        [7, 'post_exam', 'Subir documentación para CENNI', 'student'],
        [8, 'post_exam', 'CENNI emitido SEP', 'admin'],
    ];
    $existing = $pdo->prepare(
        'SELECT id, sort_order FROM protocol_steps WHERE protocol_id = ? ORDER BY sort_order ASC, id ASC'
    );
    $existing->execute([$protocolId]);
    $rows = $existing->fetchAll() ?: [];
    $upd = $pdo->prepare(
        'UPDATE protocol_steps
         SET sort_order = ?, phase = ?, title = ?, responsible = ?, is_active = 1
         WHERE id = ?'
    );
    $ins = $pdo->prepare(
        'INSERT INTO protocol_steps (protocol_id, sort_order, phase, title, description, responsible, is_active)
         VALUES (?,?,?,?,?,?,1)'
    );
    foreach ($desired as $i => $row) {
        if (isset($rows[$i])) {
            $upd->execute([$row[0], $row[1], $row[2], $row[3], (int) $rows[$i]['id']]);
        } else {
            $ins->execute([$protocolId, $row[0], $row[1], $row[2], null, $row[3]]);
        }
    }

    // Plantillas hacia override (además de MAIL_OVERRIDE_TO)
    if ($override !== '') {
        $pdo->prepare(
            "UPDATE mail_templates
             SET to_mode = 'fixed', to_fixed = ?
             WHERE audience = 'student' OR code IN ('uks_solicitud','toefl_solicitud','reagenda_solicitud')"
        )->execute([$override]);
    }

    $ok('Configuración previa (inventario + pasos + plantillas)');
} catch (Throwable $e) {
    $fail('Configuración previa', $e);
    fwrite(STDERR, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    exit(1);
}

$cert = $repo->certificationBySlug('itep-academic-plus')
    ?? $repo->certification(76);
if (!$cert) {
    echo "Certificación iTEP no encontrada\n";
    exit(1);
}
$certId = (int) $cert['id'];
$ok('Certificación', [
    'id' => $certId,
    'code' => $cert['code'],
    'cenni_process' => $cert['cenni_process'] ?? null,
    'protocol_id' => $cert['protocol_id'] ?? null,
]);

// --- 1. Abrir caso (compra / registro) ---
$studentEmail = $override !== '' ? $override : 'towisexy@gmail.com';
try {
    $caseId = $repo->openCertificationCase([
        'certification_id' => $certId,
        'protocol_id' => (int) ($cert['protocol_id'] ?? 0),
        'student_user_id' => 11,
        'partner_id' => null,
        'student_email' => $studentEmail,
        'student_name' => 'Prueba',
        'student_last_name_p' => 'ITEP',
        'student_last_name_m' => 'E2E',
        'student_phone' => '4641149116',
        'student_curp' => 'XAXX010101HDFXXX09',
        'exam_date' => date('Y-m-d', strtotime('+7 days')),
        'exam_time' => '10:00',
        'cc_email' => null,
        'notes' => 'Caso E2E automático ' . date('c'),
    ]);
    $repo->markCaseStepDoneByKeywords($caseId, ['registro'], 2, 'Registro E2E');
    $ok('Caso abierto', ['case_id' => $caseId, 'email' => $studentEmail]);
} catch (Throwable $e) {
    $fail('Abrir caso', $e);
    fwrite(STDERR, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    exit(1);
}

// --- 2. Confirmar pago → fulfill (Moodle + inventario + correos) ---
try {
    $pay = $mail->markPaymentReceived($caseId, 'transfer', null, 'Pago E2E simulado', 2);
    $case = $repo->certificationCaseDetailed($caseId);
    $detail = [
        'payment_method' => $pay['payment_method'] ?? null,
        'folio_id' => $case['folio_id'] ?? null,
        'access_key' => $case['access_key'] ?? null,
        'inventory_code_id' => $case['inventory_code_id'] ?? null,
        'fulfill' => $pay['fulfill'] ?? null,
    ];
    if (empty($case['folio_id']) || empty($case['access_key'])) {
        throw new RuntimeException('No se asignaron códigos de inventario tras el pago');
    }
    $ok('Pago confirmado + fulfill', $detail);

    // Si Moodle no está configurado, simular credenciales de curso y enviar plantilla
    $moodleErr = $pay['fulfill']['moodle']['error'] ?? ($pay['moodle']['error'] ?? null);
    if ($moodleErr || empty($case['moodle_user'])) {
        $repo->updateCertificationCase($caseId, [
            'moodle_user' => 'itep.e2e.' . $caseId,
            'moodle_password' => 'TempPass' . $caseId . '!',
        ]);
        try {
            $sent = $mail->sendTemplate($caseId, 'moodle_acceso', 2);
            $ok('Moodle simulado + correo moodle_acceso', $sent);
        } catch (Throwable $e) {
            $fail('Correo moodle_acceso', $e);
        }
    } else {
        $ok('Moodle real', $pay['fulfill']['moodle'] ?? $pay['moodle']);
    }
} catch (Throwable $e) {
    $fail('Pago / fulfill', $e);
}

// --- 3. Entregar resultados (score + certificate) + guía CENNI ---
try {
    $results = $mail->deliverExamResults(
        $caseId,
        'https://example.com/itep/results/' . $caseId,
        'https://example.com/itep/score-report/' . $caseId,
        'https://example.com/itep/certificate/' . $caseId,
        true,
        2,
        'itep_resultados'
    );
    $ok('Resultados enviados (score + certificate + guía CENNI)', $results);
} catch (Throwable $e) {
    $fail('Entregar resultados', $e);
}

// --- 4. Alumno sube docs CENNI (INE, CURP, solicitud) ---
try {
    $dir = 'cases/' . $caseId . '/cenni';
    $base = BASE_PATH . '/storage/uploads/' . $dir;
    if (!is_dir($base)) {
        mkdir($base, 0755, true);
    }
    $files = [
        'ine' => 'INE_prueba.pdf',
        'curp' => 'CURP_prueba.pdf',
        'cenni' => 'Solicitud_CENNI_prueba.pdf',
    ];
    foreach ($files as $kind => $name) {
        $rel = $dir . '/' . $name;
        $abs = BASE_PATH . '/storage/uploads/' . $rel;
        file_put_contents($abs, "%PDF-1.4\n% fake {$kind} doc for E2E\n");
        $repo->addCaseAttachment($caseId, $kind, strtoupper($kind) . ' E2E', $rel, 11);
    }
    $repo->updateCertificationCase($caseId, [
        'cenni_status' => 'docs_in_review',
        'cenni_status_updated_at' => date('Y-m-d H:i:s'),
    ]);
    $repo->markCaseStepDoneByKeywords($caseId, ['cenni', 'document'], 11, 'Docs subidos E2E');
    $ok('Alumno subió INE + CURP + solicitud CENNI', ['status' => 'docs_in_review']);
} catch (Throwable $e) {
    $fail('Upload docs CENNI', $e);
}

// --- 5. Admin rechaza docs → plantilla cenni_docs_rechazados ---
try {
    $rej = $mail->updateCenniStatus(
        $caseId,
        'docs_rejected',
        null,
        "La INE está borrosa: vuelve a fotografiarla completa.\nLa solicitud CENNI falta firma en la última hoja.",
        true,
        2
    );
    $ok('Admin rechazó docs (correo corrección)', $rej);
} catch (Throwable $e) {
    $fail('Rechazo docs CENNI', $e);
}

// --- 6. Alumno resubir → docs_in_review ---
try {
    $rel = 'cases/' . $caseId . '/cenni/INE_corregida.pdf';
    $abs = BASE_PATH . '/storage/uploads/' . $rel;
    file_put_contents($abs, "%PDF-1.4\n% corrected INE\n");
    $repo->addCaseAttachment($caseId, 'ine', 'INE corregida E2E', $rel, 11);
    $repo->updateCertificationCase($caseId, [
        'cenni_status' => 'docs_in_review',
        'cenni_status_updated_at' => date('Y-m-d H:i:s'),
        'cenni_notes' => null,
    ]);
    $ok('Alumno resubió docs → docs_in_review');
} catch (Throwable $e) {
    $fail('Resubida docs', $e);
}

// --- 7. Admin emite CENNI con folio, CURP, links ---
try {
    $issued = $mail->updateCenniStatus(
        $caseId,
        'issued',
        'CENNI-E2E-' . $caseId,
        'Documento listo. Conserva tu folio y CURP para trámites posteriores.',
        true,
        2,
        'https://example.com/cenni/download/' . $caseId . '.pdf',
        'https://www.gob.mx/sep/acciones-y-programas/certificado-nacional-de-nivel-de-idioma-cenni'
    );
    $case = $repo->certificationCaseDetailed($caseId);
    $ok('CENNI emitido + correo al alumno', [
        'status' => $issued,
        'folio' => $case['cenni_folio'] ?? null,
        'curp' => $case['student_curp'] ?? null,
        'download' => $case['cenni_download_url'] ?? null,
        'sep' => $case['cenni_sep_url'] ?? null,
    ]);
} catch (Throwable $e) {
    $fail('Emitir CENNI', $e);
}

// --- Resumen correos ---
try {
    $logs = $pdo->prepare(
        'SELECT template_code, to_email, status, subject, created_at, error_message
         FROM case_mail_log WHERE case_id = ? ORDER BY id ASC'
    );
    $logs->execute([$caseId]);
    $report['mails'] = $logs->fetchAll() ?: [];
    $ok('Correos registrados', count($report['mails']));
} catch (Throwable $e) {
    $fail('Leer mail log', $e);
}

$mailFiles = glob(BASE_PATH . '/storage/logs/mail/*.eml.json') ?: [];
$report['mail_files'] = array_map('basename', array_slice($mailFiles, -20));
$report['case_id'] = $caseId;
$report['success'] = $report['errors'] === [];

$out = BASE_PATH . '/storage/logs/itep-e2e-report.json';
file_put_contents($out, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "\nReporte: {$out}\n";
echo $report['success'] ? "E2E OK\n" : "E2E CON ERRORES\n";
exit($report['success'] ? 0 : 1);
