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

            // Si ya está logueado como alumno, ir directo a confirmar
            if (Auth::check()) {
                $role = Auth::user()['role'] ?? '';
                if ($role === 'student' || Auth::isStaffRole($role)) {
                    view('store/acquire_confirm', [
                        'title' => 'Adquirir · ' . $item['name'],
                        'item' => $item,
                        'user' => Auth::user(),
                        'error' => flash('error'),
                        'info' => flash('info'),
                    ]);
                    return;
                }
                if ($role === 'partner') {
                    flash('info', 'Como Teacher Referral, registra alumnos desde tu panel. La compra pública es para alumnos.');
                    header('Location: /partner/certificacion?slug=' . rawurlencode($slug));
                    exit;
                }
            }

            view('store/acquire', [
                'title' => 'Adquirir · ' . $item['name'],
                'item' => $item,
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
                } elseif ($mode === 'confirm') {
                    Auth::requireLogin();
                } else {
                    // register
                    if (Auth::check()) {
                        throw new \RuntimeException('Ya tienes sesión iniciada.');
                    }
                    $first = trim((string) ($_POST['first_name'] ?? ''));
                    $last = trim((string) ($_POST['last_name'] ?? ''));
                    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                    $phone = trim((string) ($_POST['phone'] ?? ''));
                    $password = (string) ($_POST['password'] ?? '');
                    $password2 = (string) ($_POST['password_confirm'] ?? '');

                    if ($first === '' || $last === '') {
                        throw new \RuntimeException('Nombre y apellido son obligatorios.');
                    }
                    if ($password !== $password2) {
                        throw new \RuntimeException('Las contraseñas no coinciden.');
                    }

                    // Si el correo ya existe, orientar a login
                    if (Auth::findUserByEmail($email)) {
                        flash('error', 'Ya tienes cuenta con ese correo. Inicia sesión para adquirir.');
                        flash('old', ['email' => $email, 'show_login' => '1']);
                        header('Location: /adquirir?slug=' . rawurlencode($slug));
                        exit;
                    }

                    $userId = Auth::registerStudent([
                        'email' => $email,
                        'first_name' => $first,
                        'last_name' => $last,
                        'phone' => $phone,
                        'password' => $password,
                    ]);
                    Auth::loginById($userId);
                }

                $user = Auth::user();
                if ($user === null) {
                    throw new \RuntimeException('No se pudo autenticar.');
                }

                if (empty($item['protocol_id'])) {
                    flash('info', 'Cuenta lista. Esta certificación aún no tiene protocolo de seguimiento; el equipo te contactará.');
                    header('Location: /alumno');
                    exit;
                }

                $caseId = $repo()->openCertificationCase([
                    'certification_id' => (int) $item['id'],
                    'student_user_id' => (int) $user['id'],
                    'student_email' => (string) $user['email'],
                    'student_name' => trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')))
                        ?: (string) $user['name'],
                    'student_phone' => (string) ($user['phone'] ?? ''),
                    'notes' => 'Adquisición pública desde vitrina',
                ]);

                $payNote = '';
                try {
                    $pay = new \App\Payments\OpenPayPaymentService($repo());
                    $pay->ensureSpeiCharge($caseId, false, true);
                    $payNote = ' Te enviamos la CLABE SPEI para pagar.';
                } catch (\Throwable $payErr) {
                    error_log('[PDV] OpenPay al adquirir caso #' . $caseId . ': ' . $payErr->getMessage());
                    $payNote = ' El equipo generará tu liga/CLABE de pago en breve.';
                }

                flash('info', 'Listo. Ya puedes dar seguimiento a tu certificación.' . $payNote);
                header('Location: /alumno/caso?id=' . $caseId);
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                flash('old', [
                    'first_name' => (string) ($_POST['first_name'] ?? ''),
                    'last_name' => (string) ($_POST['last_name'] ?? ''),
                    'email' => (string) ($_POST['email'] ?? ''),
                    'phone' => (string) ($_POST['phone'] ?? ''),
                    'show_login' => $mode === 'login' ? '1' : '',
                ]);
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
                'cenni_statuses' => \App\Payments\OpenPayPaymentService::cenniStatuses(),
                'phases' => CatalogRepository::protocolPhases(),
                'responsibles' => CatalogRepository::protocolResponsibles(),
                'user' => $user,
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
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
