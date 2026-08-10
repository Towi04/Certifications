<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\Auth;
use App\Catalog\CatalogRepository;

final class PublicRoutes
{
    public static function register(Router $router): void
    {
        $repo = static fn (): CatalogRepository => new CatalogRepository();

        $router->get('/certificacion', static function () use ($repo): void {
            $slug = trim((string) ($_GET['slug'] ?? ''));
            $item = $slug !== '' ? $repo()->certificationBySlug($slug) : null;
            if (!$item || !(int) $item['is_published']) {
                http_response_code(404);
                echo 'Producto no encontrado.';
                exit;
            }

            $protocolSteps = [];
            if (!empty($item['protocol_id'])) {
                $protocolSteps = $repo()->protocolSteps((int) $item['protocol_id'], true);
            }

            view('store/show', [
                'title' => $item['name'],
                'item' => $item,
                'protocolSteps' => $protocolSteps,
                'courses' => $repo()->certificationCourses((int) $item['id']),
                'assets' => $repo()->assets('certification', (int) $item['id']),
                'providerAssets' => $repo()->assets('provider', (int) $item['provider_id']),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/adquirir', static function () use ($repo): void {
            $slug = trim((string) ($_GET['slug'] ?? ''));
            $item = $slug !== '' ? $repo()->certificationBySlug($slug) : null;
            if (!$item || !(int) $item['is_published']) {
                http_response_code(404);
                echo 'Producto no encontrado.';
                exit;
            }

            if (Auth::check()) {
                $role = Auth::user()['role'] ?? '';
                if ($role === 'partner') {
                    flash('info', 'Como Teacher Referral, registra alumnos desde tu panel. La compra pública es para alumnos.');
                    header('Location: /partner/certificacion?slug=' . rawurlencode($slug));
                    exit;
                }
                if ($role === 'student' || Auth::isStaffRole($role)) {
                    view('store/acquire', [
                        'title' => 'Adquirir · ' . $item['name'],
                        'item' => $item,
                        'user' => Auth::user(),
                        'logged_in' => true,
                        'error' => flash('error'),
                        'info' => flash('info'),
                        'old' => flash('old') ?? [],
                    ]);
                    return;
                }
            }

            view('store/acquire', [
                'title' => 'Adquirir · ' . $item['name'],
                'item' => $item,
                'user' => null,
                'logged_in' => false,
                'error' => flash('error'),
                'info' => flash('info'),
                'old' => flash('old') ?? [],
            ]);
        });

        $router->post('/adquirir', static function () use ($repo): void {
            $slug = trim((string) ($_POST['slug'] ?? ''));
            $item = $slug !== '' ? $repo()->certificationBySlug($slug) : null;
            if (!$item || !(int) $item['is_published']) {
                flash('error', 'Producto no encontrado.');
                header('Location: /');
                exit;
            }

            $mode = (string) ($_POST['mode'] ?? 'register');
            $oldPayload = static function () use ($mode): array {
                $extra = $_POST['extra'] ?? [];
                if (!is_array($extra)) {
                    $extra = [];
                }
                return [
                    'first_name' => (string) ($_POST['first_name'] ?? ''),
                    'last_name_p' => (string) ($_POST['last_name_p'] ?? ''),
                    'last_name_m' => (string) ($_POST['last_name_m'] ?? ''),
                    'email' => (string) ($_POST['email'] ?? ''),
                    'phone' => (string) ($_POST['phone'] ?? ''),
                    'curp' => (string) ($_POST['curp'] ?? ''),
                    'birth_date' => (string) ($_POST['birth_date'] ?? ''),
                    'sex' => (string) ($_POST['sex'] ?? ''),
                    'nationality' => (string) ($_POST['nationality'] ?? 'MEX'),
                    'exam_date' => (string) ($_POST['exam_date'] ?? ''),
                    'exam_time' => (string) ($_POST['exam_time'] ?? ''),
                    'exam_time_extraordinary' => (string) ($_POST['exam_time_extraordinary'] ?? ''),
                    'exam_time_mode' => ((string) ($_POST['exam_time'] ?? '') === '__extraordinary__') ? 'extraordinary' : '',
                    'accept_extraordinary' => isset($_POST['accept_extraordinary']) ? '1' : '',
                    'extra' => $extra,
                    'show_login' => $mode === 'login' ? '1' : '',
                ];
            };

            try {
                if ($mode === 'login') {
                    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                    $password = (string) ($_POST['password'] ?? '');
                    if (!Auth::attempt($email, $password)) {
                        throw new \RuntimeException('Correo o contraseña incorrectos.');
                    }
                    $role = Auth::user()['role'] ?? '';
                    if ($role === 'partner') {
                        throw new \RuntimeException('Las cuentas Teacher Referral no usan la compra pública.');
                    }
                    flash('info', 'Sesión iniciada. Completa tus datos de candidato para continuar.');
                    header('Location: /adquirir?slug=' . rawurlencode($slug));
                    exit;
                }

                $regConfig = CatalogRepository::decodeRegistrationConfig($item['registration_fields_json'] ?? null);
                $regCfg = $regConfig['modes'];
                $catalog = CatalogRepository::registrationFieldCatalog();
                $schedule = $regConfig['schedule'];
                $customDefs = $regConfig['custom'];

                $first = trim((string) ($_POST['first_name'] ?? ''));
                $lastP = trim((string) ($_POST['last_name_p'] ?? ''));
                $lastM = trim((string) ($_POST['last_name_m'] ?? ''));
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $curp = strtoupper(trim((string) ($_POST['curp'] ?? '')));
                $birthDate = trim((string) ($_POST['birth_date'] ?? ''));
                $sex = strtoupper(trim((string) ($_POST['sex'] ?? '')));
                $nationality = strtoupper(trim((string) ($_POST['nationality'] ?? '')));
                $examDate = trim((string) ($_POST['exam_date'] ?? ''));
                $examTimeRaw = trim((string) ($_POST['exam_time'] ?? ''));
                $examTimeExtra = substr(trim((string) ($_POST['exam_time_extraordinary'] ?? '')), 0, 5);
                $extraordinary = false;
                $extraordinaryFee = 0.0;
                $examTime = '';

                $posted = [
                    'first_name' => $first,
                    'last_name_p' => $lastP,
                    'last_name_m' => $lastM,
                    'email' => $email,
                    'phone' => $phone,
                    'curp' => $curp,
                    'birth_date' => $birthDate,
                    'sex' => $sex,
                    'nationality' => $nationality,
                    'exam_date' => $examDate,
                    'exam_time' => $examTimeRaw === '__extraordinary__' ? $examTimeExtra : $examTimeRaw,
                ];
                foreach ($regCfg as $key => $fieldMode) {
                    if ($fieldMode === 'off') {
                        $posted[$key] = '';
                        continue;
                    }
                    if ($fieldMode === 'required' && trim((string) ($posted[$key] ?? '')) === '') {
                        $label = $catalog[$key]['label'] ?? $key;
                        throw new \RuntimeException($label . ' es obligatorio para esta certificación.');
                    }
                }
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Correo inválido.');
                }
                if ($sex !== '' && !in_array($sex, ['F', 'M'], true)) {
                    $sex = '';
                }

                if (CatalogRepository::registrationFieldEnabled($regCfg, 'exam_time')) {
                    if ($examTimeRaw === '__extraordinary__') {
                        if (empty($schedule['extraordinary_enabled'])) {
                            throw new \RuntimeException('Esta certificación no admite horario fuera de rango.');
                        }
                        if (!isset($_POST['accept_extraordinary'])) {
                            throw new \RuntimeException('Debes aceptar el costo de aplicación extraordinaria.');
                        }
                        if ($examTimeExtra === '' || !preg_match('/^\d{2}:\d{2}$/', $examTimeExtra)) {
                            throw new \RuntimeException('Indica la hora fuera de horario.');
                        }
                        if (CatalogRepository::isExamTimeWithinRange($examTimeExtra, $schedule)) {
                            throw new \RuntimeException('Esa hora está dentro del horario regular; elige una opción de la lista.');
                        }
                        $extraordinary = true;
                        $extraordinaryFee = max(0, (float) ($schedule['extraordinary_fee'] ?? 0));
                        $examTime = $examTimeExtra;
                    } else {
                        $examTime = substr($examTimeRaw, 0, 5);
                        if ($examTime !== '' && !CatalogRepository::isExamTimeWithinRange($examTime, $schedule)) {
                            if (!empty($schedule['extraordinary_enabled'])) {
                                throw new \RuntimeException('Para horarios fuera de rango elige “Fuera de horario”.');
                            }
                            throw new \RuntimeException('La hora debe estar entre ' . $schedule['time_start'] . ' y ' . $schedule['time_end'] . '.');
                        }
                    }
                }

                $extraIn = $_POST['extra'] ?? [];
                if (!is_array($extraIn)) {
                    $extraIn = [];
                }
                $extraOut = [];
                foreach ($customDefs as $cf) {
                    $ck = $cf['key'];
                    $cval = trim((string) ($extraIn[$ck] ?? ''));
                    if (($cf['mode'] ?? '') === 'required' && $cval === '') {
                        throw new \RuntimeException(($cf['label'] ?? $ck) . ' es obligatorio.');
                    }
                    if ($cval !== '') {
                        $extraOut[$ck] = $cval;
                    }
                }

                // Campos apagados no se guardan
                if (!CatalogRepository::registrationFieldEnabled($regCfg, 'last_name_m')) {
                    $lastM = '';
                }
                if (!CatalogRepository::registrationFieldEnabled($regCfg, 'phone')) {
                    $phone = '';
                }
                if (!CatalogRepository::registrationFieldEnabled($regCfg, 'curp')) {
                    $curp = '';
                }
                if (!CatalogRepository::registrationFieldEnabled($regCfg, 'birth_date')) {
                    $birthDate = '';
                }
                if (!CatalogRepository::registrationFieldEnabled($regCfg, 'sex')) {
                    $sex = '';
                }
                if (!CatalogRepository::registrationFieldEnabled($regCfg, 'nationality')) {
                    $nationality = '';
                }
                if (!CatalogRepository::registrationFieldEnabled($regCfg, 'exam_date')) {
                    $examDate = '';
                }
                if (!CatalogRepository::registrationFieldEnabled($regCfg, 'exam_time')) {
                    $examTime = '';
                    $extraordinary = false;
                    $extraordinaryFee = 0.0;
                }

                $plainPassword = null;
                if ($mode === 'confirm') {
                    Auth::requireLogin();
                    $role = Auth::user()['role'] ?? '';
                    if ($role === 'partner') {
                        throw new \RuntimeException('Las cuentas Teacher Referral no usan la compra pública.');
                    }
                } else {
                    if (Auth::check()) {
                        throw new \RuntimeException('Ya tienes sesión iniciada.');
                    }
                    if (Auth::findUserByEmail($email)) {
                        flash('error', 'Ya tienes cuenta con ese correo. Inicia sesión para adquirir.');
                        flash('old', $oldPayload() + ['show_login' => '1']);
                        header('Location: /adquirir?slug=' . rawurlencode($slug));
                        exit;
                    }
                    $created = Auth::registerStudent([
                        'email' => $email,
                        'first_name' => $first,
                        'last_name' => trim($lastP . ' ' . $lastM),
                        'phone' => $phone,
                        'auto_password' => true,
                    ]);
                    $plainPassword = $created['plain_password'];
                    Auth::loginById($created['id']);
                }

                $user = Auth::user();
                if ($user === null) {
                    throw new \RuntimeException('No se pudo autenticar.');
                }

                if (empty($item['protocol_id'])) {
                    flash('info', 'Registro recibido. Esta certificación aún no tiene protocolo; el equipo te contactará.');
                    header('Location: /alumno');
                    exit;
                }

                $fullName = trim($first . ' ' . $lastP . ' ' . $lastM);
                $caseId = $repo()->openCertificationCase([
                    'certification_id' => (int) $item['id'],
                    'student_user_id' => (int) $user['id'],
                    'student_email' => $email !== '' ? $email : (string) $user['email'],
                    'student_name' => $first,
                    'student_last_name_p' => $lastP,
                    'student_last_name_m' => $lastM !== '' ? $lastM : null,
                    'student_phone' => $phone !== '' ? $phone : null,
                    'student_curp' => $curp !== '' ? $curp : null,
                    'student_birth_date' => $birthDate !== '' ? $birthDate : null,
                    'student_sex' => $sex !== '' ? $sex : null,
                    'student_nationality' => $nationality !== '' ? $nationality : null,
                    'exam_date' => $examDate !== '' ? $examDate : null,
                    'exam_time' => $examTime !== '' ? $examTime : null,
                    'exam_extraordinary' => $extraordinary ? 1 : 0,
                    'exam_extraordinary_fee' => $extraordinary ? $extraordinaryFee : null,
                    'registration_extra_json' => $extraOut !== []
                        ? (json_encode($extraOut, JSON_UNESCAPED_UNICODE) ?: null)
                        : null,
                    'notes' => 'Adquisición pública · datos para certificado: ' . $fullName
                        . ($extraordinary ? ' · aplicación extraordinaria' : ''),
                ]);

                // El formulario de adquisición cubre el registro del candidato.
                $repo()->markCaseStepDoneByKeywords($caseId, ['registro', 'candidato'], (int) $user['id'], 'Datos capturados en adquisición');

                $payNote = '';
                try {
                    $pay = new \App\Payments\OpenPayPaymentService($repo());
                    $pay->ensureSpeiCharge($caseId, false, true);
                    $repo()->markCaseStepDoneByKeywords($caseId, ['openpay genera', 'genera el link', 'link de pago'], (int) $user['id'], 'CLABE generada automáticamente');
                    $payNote = ' Ya tienes tu ficha SPEI para pagar.';
                } catch (\Throwable $payErr) {
                    error_log('[PDV] OpenPay al adquirir caso #' . $caseId . ': ' . $payErr->getMessage());
                    $payNote = ' El equipo generará tu liga/CLABE de pago en breve.';
                }

                if ($plainPassword) {
                    Auth::sendPurchaseAccountEmail(
                        (int) $user['id'],
                        $plainPassword,
                        (string) $item['name'],
                        $caseId
                    );
                }

                flash('info', 'Registro listo. Firma el reglamento (si aplica) y realiza tu pago.' . $payNote . ' Te enviamos un correo con el acceso a tu cuenta.');
                header('Location: /alumno/caso?id=' . $caseId);
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                flash('old', $oldPayload());
                header('Location: /adquirir?slug=' . rawurlencode($slug));
                exit;
            }
        });

        $router->get('/alumno', static function () use ($repo): void {
            Auth::requireStudent();
            $user = Auth::user();
            view('alumno/index', [
                'title' => 'Mi seguimiento',
                'user' => $user,
                'cases' => $repo()->casesForStudentUser((int) $user['id']),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/alumno/caso', static function () use ($repo): void {
            Auth::requireStudent();
            $user = Auth::user();
            $id = (int) ($_GET['id'] ?? 0);
            $item = $repo()->certificationCaseDetailed($id);
            if (!$item || (int) ($item['student_user_id'] ?? 0) !== (int) $user['id']) {
                if ($item && Auth::isStaffRole($user['role'] ?? null)) {
                    // ok
                } else {
                    flash('error', 'Caso no encontrado.');
                    header('Location: /alumno');
                    exit;
                }
            }

            view('alumno/caso', [
                'title' => 'Caso #' . $id,
                'item' => $item,
                'steps' => $repo()->certificationCaseSteps($id),
                'attachments' => $repo()->caseAttachments($id),
                'regulation' => $repo()->regulationDocumentForCertification((int) ($item['certification_id'] ?? 0)),
                'requires_regulation' => !empty($item['requires_regulation_signature']),
                'cenni_statuses' => \App\Payments\OpenPayPaymentService::cenniStatuses(),
                'phases' => CatalogRepository::protocolPhases(),
                'responsibles' => CatalogRepository::protocolResponsibles(),
                'user' => $user,
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->post('/alumno/caso/sign-regulation', static function () use ($repo): void {
            Auth::requireStudent();
            $user = Auth::user();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $item = $repo()->certificationCaseDetailed($caseId);
            if (!$item || (int) ($item['student_user_id'] ?? 0) !== (int) $user['id']) {
                flash('error', 'Caso no encontrado.');
                header('Location: /alumno');
                exit;
            }
            try {
                if (!empty($item['regulation_signed_at'])) {
                    throw new \RuntimeException('El reglamento ya fue firmado.');
                }
                $doc = $repo()->regulationDocumentForCertification((int) ($item['certification_id'] ?? 0));
                if (!$doc && !empty($item['requires_regulation_signature'])) {
                    throw new \RuntimeException('Aún no hay reglamento asignado a esta certificación. Contacta a Instituto Doceo.');
                }
                $accept = isset($_POST['accept_regulation']);
                if (!$accept) {
                    throw new \RuntimeException('Debes aceptar el reglamento para continuar.');
                }
                $signer = trim((string) ($_POST['signer_name'] ?? ''));
                $repo()->signCaseRegulation(
                    $caseId,
                    $signer,
                    $doc ? (int) $doc['id'] : null,
                    (int) $user['id']
                );
                flash('info', 'Reglamento firmado. Continúa con tu pago SPEI.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /alumno/caso?id=' . $caseId);
            exit;
        });

        $router->post('/alumno/caso/upload-cenni', static function () use ($repo): void {
            Auth::requireStudent();
            $user = Auth::user();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $item = $repo()->certificationCaseDetailed($caseId);
            if (!$item || (int) ($item['student_user_id'] ?? 0) !== (int) $user['id']) {
                flash('error', 'Caso no encontrado.');
                header('Location: /alumno');
                exit;
            }
            if (($item['cenni_process'] ?? '') !== 'doceo_managed') {
                flash('error', 'Esta certificación no recibe documentos CENNI en Doceo (se gestionan en UKS u otro canal).');
                header('Location: /alumno/caso?id=' . $caseId);
                exit;
            }
            try {
                $map = [
                    'cenni_ine' => ['kind' => 'ine', 'label' => 'INE'],
                    'cenni_curp' => ['kind' => 'curp', 'label' => 'CURP'],
                    'cenni_solicitud' => ['kind' => 'cenni', 'label' => 'Solicitud CENNI'],
                ];
                $uploaded = 0;
                foreach ($map as $field => $meta) {
                    $file = $_FILES[$field] ?? null;
                    if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    $path = \App\Support\Uploader::store($file, 'cases/' . $caseId . '/cenni');
                    $repo()->addCaseAttachment($caseId, $meta['kind'], $meta['label'], $path, (int) $user['id']);
                    $uploaded++;
                }
                if ($uploaded === 0) {
                    throw new \RuntimeException('Selecciona al menos un archivo (INE, CURP o solicitud).');
                }
                $repo()->updateCertificationCase($caseId, [
                    'cenni_status' => 'docs_in_review',
                    'cenni_status_updated_at' => date('Y-m-d H:i:s'),
                    'cenni_notes' => 'Documentos subidos por el alumno en PDV',
                ]);
                flash('info', 'Documentos recibidos. El equipo Doceo gestionará el trámite CENNI ante la SEP.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /alumno/caso?id=' . $caseId);
            exit;
        });

        $router->get('/pago/spei', static function () use ($repo): void {
            Auth::requireLogin();
            $user = Auth::user();
            $id = (int) ($_GET['id'] ?? 0);
            $item = $repo()->certificationCaseDetailed($id);
            if (!$item) {
                http_response_code(404);
                echo 'Ficha de pago no encontrada.';
                exit;
            }
            $isOwner = (int) ($item['student_user_id'] ?? 0) === (int) ($user['id'] ?? 0);
            $isStaff = Auth::isStaffRole($user['role'] ?? null);
            if (!$isOwner && !$isStaff) {
                http_response_code(403);
                echo 'No autorizado.';
                exit;
            }
            if (empty($item['openpay_clabe'])) {
                flash('error', 'Aún no hay datos SPEI para este caso.');
                header('Location: ' . ($isStaff ? '/admin/cases/show?id=' . $id : '/alumno/caso?id=' . $id));
                exit;
            }

            $beneficiary = (string) (\App\Config\Env::get('OPENPAY_BENEFICIARY_NAME', 'Instituto DOCEO') ?? 'Instituto DOCEO');
            view('pago/spei', [
                'title' => 'Ficha SPEI · caso #' . $id,
                'layout' => 'print',
                'print' => true,
                'item' => $item,
                'beneficiary' => $beneficiary,
            ]);
        });

        $router->post('/webhooks/openpay', static function () use ($repo): void {
            $raw = file_get_contents('php://input') ?: '';
            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'invalid_json']);
                exit;
            }
            try {
                $svc = new \App\Payments\OpenPayPaymentService($repo());
                $result = $svc->handleWebhook($payload);
                http_response_code(200);
                header('Content-Type: application/json');
                echo json_encode($result);
            } catch (\Throwable $e) {
                error_log('[PDV] OpenPay webhook: ' . $e->getMessage());
                http_response_code(200);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }
            exit;
        });
    }
}
