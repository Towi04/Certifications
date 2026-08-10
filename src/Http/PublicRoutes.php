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

                if (CatalogRepository::registrationFieldEnabled($regCfg, 'exam_date') && $examDate !== '') {
                    if (!CatalogRepository::isExamDateOpen($examDate, $schedule) && empty($schedule['extraordinary_enabled'])) {
                        throw new \RuntimeException('Ese día no hay horario de aplicación para esta certificación.');
                    }
                }

                if (CatalogRepository::registrationFieldEnabled($regCfg, 'exam_time')) {
                    $dateOpen = $examDate === '' || CatalogRepository::isExamDateOpen($examDate, $schedule);
                    if ($examTimeRaw === '__extraordinary__') {
                        if (empty($schedule['extraordinary_enabled'])) {
                            throw new \RuntimeException('Esta certificación no admite aplicación extraordinaria.');
                        }
                        if (!isset($_POST['accept_extraordinary'])) {
                            throw new \RuntimeException('Debes aceptar el costo de aplicación extraordinaria.');
                        }
                        if ($examTimeExtra === '' || !preg_match('/^\d{2}:\d{2}$/', $examTimeExtra)) {
                            throw new \RuntimeException('Indica la hora fuera de horario.');
                        }
                        if ($dateOpen && $examDate !== '' && CatalogRepository::isExamTimeWithinRange($examTimeExtra, $schedule, $examDate)) {
                            throw new \RuntimeException('Esa hora está dentro del horario regular; elige una opción de la lista.');
                        }
                        $extraordinary = true;
                        $extraordinaryFee = max(0, (float) ($schedule['extraordinary_fee'] ?? 0));
                        $examTime = $examTimeExtra;
                    } else {
                        $examTime = substr($examTimeRaw, 0, 5);
                        if ($examTime !== '') {
                            if ($examDate === '') {
                                throw new \RuntimeException('Indica la fecha de examen para validar el horario.');
                            }
                            if (!$dateOpen) {
                                if (!empty($schedule['extraordinary_enabled'])) {
                                    throw new \RuntimeException('Ese día no tiene horario regular; elige “Fuera de horario / día”.');
                                }
                                throw new \RuntimeException('Ese día no hay aplicaciones para esta certificación.');
                            }
                            if (!CatalogRepository::isExamTimeWithinRange($examTime, $schedule, $examDate)) {
                                if (!empty($schedule['extraordinary_enabled'])) {
                                    throw new \RuntimeException('Para horarios fuera de rango elige “Fuera de horario / día”.');
                                }
                                throw new \RuntimeException('La hora no está disponible en el horario de ese día.');
                            }
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
                'cenni_docs' => $repo()->certificationDocumentsByStage((int) ($item['certification_id'] ?? 0), 'cenni'),
                'cenni_statuses' => \App\Payments\OpenPayPaymentService::cenniStatuses(),
                'phases' => CatalogRepository::protocolPhases(),
                'responsibles' => CatalogRepository::protocolResponsibles(),
                'user' => $user,
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/alumno/caso/sign-regulation', static function (): void {
            Auth::requireStudent();
            $caseId = (int) ($_GET['id'] ?? $_GET['case_id'] ?? 0);
            flash('error', 'La firma no se completó. Vuelve a firmar (si dibujas, espera a que termine de enviar).');
            header('Location: ' . ($caseId > 0 ? '/alumno/caso?id=' . $caseId : '/alumno'));
            exit;
        });

        $router->post('/alumno/caso/sign-regulation', static function () use ($repo): void {
            Auth::requireStudent();
            $user = Auth::user();
            // Si el POST viene vacío (post_max_size / límite del servidor), no hay case_id
            $contentLen = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            $caseId = (int) ($_POST['case_id'] ?? 0);
            if ($caseId <= 0 && $contentLen > 0 && $_POST === []) {
                flash('error', 'La firma dibujada era demasiado grande para el servidor. Inténtalo de nuevo (ya se comprime automáticamente) o firma con tu nombre escrito.');
                header('Location: /alumno');
                exit;
            }
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
                // Firmar implica haber leído y aceptado el reglamento
                $signer = trim((string) ($_POST['signer_name'] ?? ''));
                $mode = (string) ($_POST['signature_mode'] ?? 'type');
                $sigData = (string) ($_POST['signature_data'] ?? '');
                $sigFile = isset($_FILES['signature_image']) ? $_FILES['signature_image'] : null;
                if (
                    $mode === 'draw'
                    && $sigData === ''
                    && $sigFile
                    && (int) ($sigFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
                ) {
                    $tmp = (string) ($sigFile['tmp_name'] ?? '');
                    $bin = is_file($tmp) ? (string) file_get_contents($tmp) : '';
                    if ($bin !== '') {
                        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
                        if (!str_starts_with($mime, 'image/')) {
                            throw new \RuntimeException('El archivo de firma no es una imagen válida.');
                        }
                        $sigData = 'data:' . $mime . ';base64,' . base64_encode($bin);
                    }
                }
                $repo()->signCaseRegulation(
                    $caseId,
                    $signer,
                    $doc ? (int) $doc['id'] : null,
                    (int) $user['id'],
                    $mode,
                    $sigData !== '' ? $sigData : null
                );
                flash('info', 'Reglamento firmado. Ya puedes continuar con tu pago.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /alumno/caso?id=' . $caseId);
            exit;
        });

        $router->post('/alumno/caso/reschedule', static function () use ($repo): void {
            Auth::requireStudent();
            $user = Auth::user();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $item = $repo()->certificationCaseDetailed($caseId);
            if (!$item || (int) ($item['student_user_id'] ?? 0) !== (int) $user['id']) {
                flash('error', 'Caso no encontrado.');
                header('Location: /alumno');
                exit;
            }
            $paid = !empty($item['payment_confirmed_at'])
                || in_array(strtolower((string) ($item['openpay_status'] ?? '')), ['completed', 'paid'], true);
            if (!$paid) {
                flash('error', 'Solo puedes solicitar reagenda después de confirmar el pago.');
                header('Location: /alumno/caso?id=' . $caseId);
                exit;
            }
            try {
                $svc = new \App\Mail\CaseMailService($repo());
                $result = $svc->rescheduleAndNotifyProvider(
                    $caseId,
                    trim((string) ($_POST['reschedule_date'] ?? '')),
                    trim((string) ($_POST['reschedule_time'] ?? '')),
                    trim((string) ($_POST['reschedule_reason'] ?? '')) ?: null,
                    null,
                    (int) $user['id'],
                    true
                );
                if (!empty($result['mailed'])) {
                    flash('info', 'Reagenda solicitada. Se notificó al proveedor.');
                } else {
                    flash('info', 'Reagenda guardada. El equipo Doceo dará seguimiento.');
                }
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /alumno/caso?id=' . $caseId);
            exit;
        });

        $router->post('/alumno/caso/request-spei', static function () use ($repo): void {
            Auth::requireStudent();
            $user = Auth::user();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $item = $repo()->certificationCaseDetailed($caseId);
            if (!$item || (int) ($item['student_user_id'] ?? 0) !== (int) $user['id']) {
                flash('error', 'Caso no encontrado.');
                header('Location: /alumno');
                exit;
            }
            $paid = !empty($item['payment_confirmed_at'])
                || in_array(strtolower((string) ($item['openpay_status'] ?? '')), ['completed', 'paid'], true);
            if ($paid) {
                flash('info', 'Tu pago ya está confirmado.');
                header('Location: /alumno/caso?id=' . $caseId);
                exit;
            }
            try {
                $force = isset($_POST['force_new']);
                $fields = (new \App\Payments\OpenPayPaymentService($repo()))->ensureSpeiCharge($caseId, $force, true);
                flash(
                    'info',
                    'CLABE SPEI lista: ' . ($fields['openpay_clabe'] ?? '')
                    . '. También te enviamos las instrucciones por correo.'
                );
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /alumno/caso?id=' . $caseId . '#pago');
            exit;
        });

        $router->post('/alumno/caso/upload-payment-proof', static function () use ($repo): void {
            Auth::requireStudent();
            $user = Auth::user();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $item = $repo()->certificationCaseDetailed($caseId);
            if (!$item || (int) ($item['student_user_id'] ?? 0) !== (int) $user['id']) {
                flash('error', 'Caso no encontrado.');
                header('Location: /alumno');
                exit;
            }
            $paid = !empty($item['payment_confirmed_at'])
                || in_array(strtolower((string) ($item['openpay_status'] ?? '')), ['completed', 'paid'], true);
            if ($paid) {
                flash('info', 'Tu pago ya está confirmado; no es necesario subir otro comprobante.');
                header('Location: /alumno/caso?id=' . $caseId);
                exit;
            }
            $file = $_FILES['payment_proof'] ?? null;
            if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                flash('error', 'Selecciona el comprobante de pago (PDF o imagen).');
                header('Location: /alumno/caso?id=' . $caseId . '#pago');
                exit;
            }
            try {
                $method = strtolower(trim((string) ($_POST['payment_method'] ?? 'transfer')));
                if (!in_array($method, ['cash', 'transfer', 'other'], true)) {
                    $method = 'transfer';
                }
                $labels = [
                    'cash' => 'Comprobante pago efectivo (alumno)',
                    'transfer' => 'Comprobante transferencia (alumno)',
                    'other' => 'Comprobante de pago (alumno)',
                ];
                $path = \App\Support\Uploader::store($file, 'cases/' . $caseId);
                $repo()->addCaseAttachment($caseId, 'payment', $labels[$method], $path, (int) $user['id']);
                $repo()->ensurePaymentMethodColumn();
                $now = date('Y-m-d H:i:s');
                $noteExtra = trim((string) ($_POST['payment_note'] ?? ''));
                $stamp = $now . ' Comprobante subido por alumno (' . $method . ')'
                    . ($noteExtra !== '' ? ': ' . $noteExtra : '');
                $prev = trim((string) ($item['notes'] ?? ''));
                $fields = [
                    'payment_proof_path' => $path,
                    'payment_method' => $method,
                    'notes' => $prev !== '' ? ($prev . "\n" . $stamp) : $stamp,
                ];
                $repo()->updateCertificationCase($caseId, $fields);
                try {
                    $repo()->markCaseStepDoneByKeywords(
                        $caseId,
                        ['comprobante', 'transferencia', 'efectivo'],
                        (int) $user['id'],
                        'Comprobante subido por alumno — pendiente de confirmación Doceo'
                    );
                } catch (\Throwable) {
                }
                flash(
                    'info',
                    'Comprobante recibido. Instituto Doceo confirmará la recepción del pago para que puedas continuar.'
                );
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /alumno/caso?id=' . $caseId . '#pago');
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
            header('Content-Type: application/json; charset=utf-8');

            $expectedUser = trim((string) (\App\Config\Env::get('OPENPAY_WEBHOOK_USER', '') ?? ''));
            $expectedPass = (string) (\App\Config\Env::get('OPENPAY_WEBHOOK_PASSWORD', '') ?? '');
            if ($expectedUser !== '') {
                $givenUser = (string) ($_SERVER['PHP_AUTH_USER'] ?? '');
                $givenPass = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');
                if ($givenUser === '' && isset($_SERVER['HTTP_AUTHORIZATION'])
                    && preg_match('/Basic\s+(\S+)/i', (string) $_SERVER['HTTP_AUTHORIZATION'], $m)) {
                    $decoded = base64_decode($m[1], true);
                    if (is_string($decoded) && str_contains($decoded, ':')) {
                        [$givenUser, $givenPass] = explode(':', $decoded, 2);
                    }
                }
                $userOk = hash_equals($expectedUser, $givenUser);
                $passOk = hash_equals($expectedPass, $givenPass);
                if (!$userOk || !$passOk) {
                    http_response_code(401);
                    header('WWW-Authenticate: Basic realm="OpenPay Webhook"');
                    echo json_encode(['error' => 'unauthorized']);
                    exit;
                }
            }

            $raw = file_get_contents('php://input') ?: '';
            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                http_response_code(400);
                echo json_encode(['error' => 'invalid_json']);
                exit;
            }
            try {
                $svc = new \App\Payments\OpenPayPaymentService($repo());
                $result = $svc->handleWebhook($payload);
                http_response_code(200);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
            } catch (\Throwable $e) {
                error_log('[PDV] OpenPay webhook: ' . $e->getMessage());
                // OpenPay reintenta si no es 2xx; devolvemos 200 tras registrar el error
                // para no ciclar, salvo fallos de autenticación ya manejados arriba.
                http_response_code(200);
                echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            exit;
        });

        // Ping público simple (GET) para comprobar que la URL está viva antes de registrarla.
        $router->get('/webhooks/openpay', static function (): void {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(200);
            echo json_encode([
                'ok' => true,
                'service' => 'openpay-webhook',
                'hint' => 'Usa POST con el JSON de OpenPay. Registra la URL desde Admin → OpenPay.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        });
    }
}
