<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\Auth;
use App\Catalog\CatalogRepository;
use App\Config\Env;
use App\Integrations\OpenPayClient;
use App\Mail\StudentAcquireMailer;

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

            // En la ficha pública solo mostramos un resumen, no el protocolo completo.
            $protocolSteps = [];
            if (!empty($item['protocol_id'])) {
                $all = $repo()->protocolSteps((int) $item['protocol_id'], true);
                $protocolSteps = array_values(array_filter(
                    $all,
                    static fn (array $s): bool => in_array((string) ($s['responsible'] ?? ''), ['student', 'student_or_tr'], true)
                        && (int) ($s['sort_order'] ?? 0) <= 4
                ));
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
            }

            $user = Auth::user();
            $old = flash('old') ?? [];
            if ($old === [] && $user && (($user['role'] ?? '') === 'student' || Auth::isStaffRole($user['role'] ?? null))) {
                $old = [
                    'first_name' => (string) ($user['first_name'] ?? ''),
                    'last_name_p' => (string) ($user['last_name'] ?? ''),
                    'email' => (string) ($user['email'] ?? ''),
                    'phone' => (string) ($user['phone'] ?? ''),
                    'nationality' => 'MEX',
                ];
            }

            view('store/acquire', [
                'title' => 'Adquirir · ' . $item['name'],
                'item' => $item,
                'user' => $user,
                'error' => flash('error'),
                'info' => flash('info'),
                'old' => is_array($old) ? $old : [],
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
            $catalog = $repo();

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
                    // Tras login, redirigir al formulario de datos (mismo GET) para completar el caso.
                    header('Location: /adquirir?slug=' . rawurlencode($slug));
                    exit;
                }

                // register | confirm → capturar datos de certificación y abrir caso
                $payload = self::parseAcquireStudentPayload($_POST);
                $createdAccount = false;

                if ($mode === 'confirm' || Auth::check()) {
                    Auth::requireLogin();
                    $role = Auth::user()['role'] ?? '';
                    if ($role === 'partner') {
                        throw new \RuntimeException('Las cuentas Teacher Referral no usan la compra pública.');
                    }
                } else {
                    if (Auth::findUserByEmail($payload['email'])) {
                        flash('error', 'Ya tienes acceso con ese correo. Inicia sesión para continuar tu solicitud.');
                        flash('old', $payload + ['show_login' => '1']);
                        header('Location: /adquirir?slug=' . rawurlencode($slug));
                        exit;
                    }

                    $userId = Auth::registerStudent([
                        'email' => $payload['email'],
                        'first_name' => $payload['first_name'],
                        'last_name' => trim($payload['last_name_p'] . ' ' . $payload['last_name_m']),
                        'phone' => $payload['phone'],
                        'use_default_password' => true,
                    ]);
                    Auth::loginById($userId);
                    $createdAccount = true;
                }

                $user = Auth::user();
                if ($user === null) {
                    throw new \RuntimeException('No se pudo autenticar.');
                }

                if (empty($item['protocol_id'])) {
                    flash('info', 'Solicitud recibida. Esta certificación aún no tiene protocolo de seguimiento; el equipo te contactará.');
                    header('Location: /alumno');
                    exit;
                }

                $fullName = trim($payload['first_name'] . ' ' . $payload['last_name_p'] . ' ' . $payload['last_name_m']);
                $caseId = $catalog->openCertificationCase([
                    'certification_id' => (int) $item['id'],
                    'student_user_id' => (int) $user['id'],
                    'student_email' => $payload['email'],
                    'student_name' => $payload['first_name'],
                    'exam_date' => $payload['exam_date'],
                    'notes' => 'Adquisición pública desde vitrina',
                ]);

                $payment = self::buildPaymentLink($item, $caseId, $payload);
                $catalog->updateCertificationCase($caseId, [
                    'student_name' => $payload['first_name'],
                    'student_last_name_p' => $payload['last_name_p'],
                    'student_last_name_m' => $payload['last_name_m'],
                    'student_email' => $payload['email'],
                    'student_phone' => $payload['phone'],
                    'student_birth_date' => $payload['birth_date'],
                    'student_sex' => $payload['sex'],
                    'student_nationality' => $payload['nationality'],
                    'exam_date' => $payload['exam_date'],
                    'exam_time' => $payload['exam_time'],
                    'payment_link_url' => $payment['url'],
                    'payment_link_id' => $payment['id'],
                    'notes' => 'Adquisición pública · Nombre en certificado: ' . $fullName,
                ]);

                $case = $catalog->certificationCase($caseId) ?? ['id' => $caseId];
                try {
                    (new StudentAcquireMailer())->sendWelcome($user, $case + [
                        'student_email' => $payload['email'],
                        'student_name' => $payload['first_name'],
                        'exam_date' => $payload['exam_date'],
                        'exam_time' => $payload['exam_time'],
                        'certification_name' => $item['name'],
                    ], $item);
                } catch (\Throwable $mailError) {
                    error_log('[PDV] acquire welcome mail: ' . $mailError->getMessage());
                }

                flash(
                    'info',
                    $createdAccount
                        ? 'Registro listo. Firma el reglamento y realiza el pago. Te enviamos un correo con el acceso a tu seguimiento.'
                        : 'Registro listo. Firma el reglamento y realiza el pago para continuar.'
                );
                header('Location: /alumno/caso?id=' . $caseId);
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                flash('old', self::parseAcquireStudentPayload($_POST, false) + [
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
            $catalog = $repo();
            $item = $catalog->certificationCaseDetailed($id) ?? $catalog->certificationCase($id);
            if (!$item || (int) ($item['student_user_id'] ?? 0) !== (int) $user['id']) {
                if ($item && Auth::isStaffRole($user['role'] ?? null)) {
                    // ok
                } else {
                    flash('error', 'Caso no encontrado.');
                    header('Location: /alumno');
                    exit;
                }
            }

            $steps = $catalog->certificationCaseSteps($id);
            $providerId = (int) ($item['provider_id'] ?? 0);
            $regulation = $catalog->activeRegulationDocument($providerId > 0 ? $providerId : null);

            view('alumno/caso', [
                'title' => 'Caso #' . $id,
                'item' => $item,
                'steps' => $steps,
                'stages' => CatalogRepository::studentFacingStages($item, $steps),
                'regulation' => $regulation,
                'phases' => CatalogRepository::protocolPhases(),
                'responsibles' => CatalogRepository::protocolResponsibles(),
                'user' => $user,
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->post('/alumno/caso/firmar-reglamento', static function () use ($repo): void {
            Auth::requireStudent();
            $user = Auth::user();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $catalog = $repo();
            $item = $catalog->certificationCase($caseId);
            if (!$item || (int) ($item['student_user_id'] ?? 0) !== (int) $user['id']) {
                flash('error', 'Caso no encontrado.');
                header('Location: /alumno');
                exit;
            }

            try {
                if (!empty($item['regulation_signed_at'])) {
                    throw new \RuntimeException('El reglamento ya fue firmado.');
                }
                if (empty($_POST['accept_regulation'])) {
                    throw new \RuntimeException('Debes aceptar el reglamento para continuar.');
                }
                $signer = trim((string) ($_POST['signer_name'] ?? ''));
                if ($signer === '') {
                    throw new \RuntimeException('Escribe tu nombre completo tal como aparece en tu certificación.');
                }
                $expected = trim(
                    (string) ($item['student_name'] ?? '') . ' '
                    . (string) ($item['student_last_name_p'] ?? '') . ' '
                    . (string) ($item['student_last_name_m'] ?? '')
                );
                $normalize = static fn (string $s): string => preg_replace('/\s+/', ' ', mb_strtolower(trim($s))) ?? '';
                if ($normalize($signer) !== $normalize($expected)) {
                    throw new \RuntimeException(
                        'El nombre firmado debe coincidir exactamente con el de tu registro: ' . $expected
                    );
                }

                $catalog->updateCertificationCase($caseId, [
                    'regulation_signed_at' => date('Y-m-d H:i:s'),
                    'regulation_signer_name' => $signer,
                ]);

                $catalog->completeCurrentCaseStepIfMatches(
                    $caseId,
                    (int) $user['id'],
                    'Reglamento firmado por el alumno',
                    1,
                    null
                ) || $catalog->completeCurrentCaseStepIfMatches(
                    $caseId,
                    (int) $user['id'],
                    'Reglamento firmado por el alumno',
                    null,
                    'reglamento'
                );

                $catalog->advanceStudentPreExamAfterRegulation($caseId, (int) $user['id']);

                flash('info', 'Reglamento firmado. Continúa con el pago si aún no lo has hecho.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }

            header('Location: /alumno/caso?id=' . $caseId);
            exit;
        });

        $router->post('/alumno/caso/confirmar-pago-iniciado', static function () use ($repo): void {
            Auth::requireStudent();
            $user = Auth::user();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $catalog = $repo();
            $item = $catalog->certificationCase($caseId);
            if (!$item || (int) ($item['student_user_id'] ?? 0) !== (int) $user['id']) {
                flash('error', 'Caso no encontrado.');
                header('Location: /alumno');
                exit;
            }

            // El alumno indica que ya pagó / inició el pago; la confirmación formal la hace admin.
            $notes = trim((string) ($item['notes'] ?? ''));
            $stamp = 'Alumno reportó pago el ' . date('Y-m-d H:i');
            $catalog->updateCertificationCase($caseId, [
                'notes' => $notes === '' ? $stamp : ($notes . "\n" . $stamp),
            ]);
            flash('info', 'Gracias. El equipo revisará tu pago y te contactará si hace falta. Mientras tanto, un día antes del examen recibirás tu código de acceso (o podrás verlo aquí cuando esté asignado).');
            header('Location: /alumno/caso?id=' . $caseId);
            exit;
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   first_name:string,last_name_p:string,last_name_m:string,email:string,phone:string,
     *   birth_date:string,sex:string,nationality:string,exam_date:string,exam_time:string
     * }
     */
    private static function parseAcquireStudentPayload(array $input, bool $validate = true): array
    {
        $payload = [
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name_p' => trim((string) ($input['last_name_p'] ?? $input['last_name'] ?? '')),
            'last_name_m' => trim((string) ($input['last_name_m'] ?? '')),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'birth_date' => trim((string) ($input['birth_date'] ?? '')),
            'sex' => strtoupper(trim((string) ($input['sex'] ?? ''))),
            'nationality' => strtoupper(trim((string) ($input['nationality'] ?? 'MEX'))) ?: 'MEX',
            'exam_date' => trim((string) ($input['exam_date'] ?? '')),
            'exam_time' => trim((string) ($input['exam_time'] ?? '')),
        ];

        if (!$validate) {
            return $payload;
        }

        if ($payload['first_name'] === '' || $payload['last_name_p'] === '') {
            throw new \RuntimeException('Nombre(s) y apellido paterno son obligatorios.');
        }
        if ($payload['email'] === '' || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Correo inválido.');
        }
        if ($payload['phone'] === '') {
            throw new \RuntimeException('El teléfono es obligatorio para agendar tu examen.');
        }
        if ($payload['birth_date'] === '') {
            throw new \RuntimeException('La fecha de nacimiento es obligatoria.');
        }
        if (!in_array($payload['sex'], ['F', 'M'], true)) {
            throw new \RuntimeException('Selecciona el sexo tal como debe figurar en la certificación.');
        }
        if ($payload['exam_date'] === '') {
            throw new \RuntimeException('Elige la fecha en la que deseas presentar el examen.');
        }
        if ($payload['exam_date'] < date('Y-m-d')) {
            throw new \RuntimeException('La fecha del examen no puede ser en el pasado.');
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $item certification
     * @param array<string, string> $payload
     * @return array{id:?string,url:?string}
     */
    private static function buildPaymentLink(array $item, int $caseId, array $payload): array
    {
        $amount = $item['public_price'] ?? null;
        $fallback = Env::get('OPENPAY_FALLBACK_PAYMENT_URL');

        if (OpenPayClient::isConfigured() && $amount !== null && (float) $amount > 0) {
            try {
                $client = new OpenPayClient();
                $redirect = rtrim((string) (Env::get('APP_URL') ?: ''), '/');
                if ($redirect === '') {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $redirect = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                }
                $redirect .= '/alumno/caso?id=' . $caseId;

                $charge = $client->createRedirectCharge([
                    'amount' => (float) $amount,
                    'description' => 'PDV ' . ($item['code'] ?? $item['name'] ?? 'cert') . ' #' . $caseId,
                    'order_id' => 'pdv-case-' . $caseId . '-' . time(),
                    'redirect_url' => $redirect,
                    'currency' => (string) ($item['currency'] ?? 'MXN'),
                    'customer' => [
                        'name' => $payload['first_name'],
                        'last_name' => trim($payload['last_name_p'] . ' ' . $payload['last_name_m']),
                        'email' => $payload['email'],
                        'phone_number' => preg_replace('/\D+/', '', $payload['phone']) ?: '0000000000',
                    ],
                ]);

                return ['id' => $charge['id'], 'url' => $charge['url']];
            } catch (\Throwable $e) {
                error_log('[PDV] OpenPay payment link: ' . $e->getMessage());
            }
        }

        if ($fallback !== null && trim($fallback) !== '') {
            return ['id' => null, 'url' => trim($fallback)];
        }

        // Entorno local / sin OpenPay: página de instrucciones de pago en el propio seguimiento.
        return ['id' => null, 'url' => '/alumno/caso?id=' . $caseId . '#pago'];
    }
}
