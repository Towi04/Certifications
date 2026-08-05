<?php

declare(strict_types=1);

use App\Auth\Auth;
use App\Http\Router;
use App\Integrations\HealthChecker;

require dirname(__DIR__) . '/src/bootstrap.php';

$router = new Router();

$router->get('/', static function (): void {
    view('home', [
        'title' => 'Inicio',
    ]);
});

$router->get('/login', static function (): void {
    if (Auth::check()) {
        $user = Auth::user();
        header('Location: ' . (($user['role'] ?? '') === 'admin' ? '/admin' : '/'));
        exit;
    }

    $info = flash('info');
    try {
        $reset = Auth::syncAdminPasswordFromEnv();
        if ($reset !== null) {
            $info = 'Hash actualizado para ' . $reset['email']
                . ' (longitud de ADMIN_PASSWORD: ' . $reset['length'] . '). '
                . 'Entra con ESE correo y la misma clave del .env. '
                . 'Si tiene * - # ! pon: ADMIN_PASSWORD="tu*clave". '
                . 'Luego pon ADMIN_RESET_PASSWORD=false.';
        }
    } catch (Throwable $e) {
        flash('error', 'No se pudo resetear admin: ' . $e->getMessage());
        $info = null;
    }

    view('auth/login', [
        'title' => 'Iniciar sesión',
        'error' => flash('error'),
        'info' => $info,
    ]);
});

$router->post('/login', static function (): void {
    Auth::ensureBootstrapAdmin();

    try {
        Auth::syncAdminPasswordFromEnv();
    } catch (Throwable $e) {
        flash('error', 'No se pudo resetear admin: ' . $e->getMessage());
        header('Location: /login');
        exit;
    }

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    try {
        if (!Auth::attempt($email, $password)) {
            $msg = 'Correo o contraseña incorrectos.';
            if (\App\Config\Env::getBool('ADMIN_RESET_PASSWORD', false)) {
                $envPass = (string) (\App\Config\Env::get('ADMIN_PASSWORD') ?? '');
                $adminEmail = strtolower(trim((string) (\App\Config\Env::get('ADMIN_EMAIL') ?? '')));
                if ($password !== $envPass) {
                    $msg = 'La contraseña del formulario NO coincide con ADMIN_PASSWORD del .env. '
                        . 'Longitud escrita: ' . strlen($password)
                        . ' · longitud en .env: ' . strlen($envPass) . '. '
                        . 'Usa comillas dobles si hay caracteres especiales: ADMIN_PASSWORD="tu*clave-1". '
                        . 'Correo admin configurado: ' . $adminEmail;
                } elseif ($email !== $adminEmail) {
                    $msg = "Estás entrando con [{$email}] pero el admin del .env es [{$adminEmail}]. Usa ese correo.";
                } else {
                    $msg = 'La clave coincide con el .env y el correo también, pero password_verify falló. '
                        . 'Revisa que la columna sea password_hash (VARCHAR 255) y vuelve a cargar /login con ADMIN_RESET_PASSWORD=true.';
                }
            } else {
                $msg .= ' Tip: ADMIN_RESET_PASSWORD=true y ADMIN_PASSWORD="tu clave" en el .env, luego abre /login.';
            }
            flash('error', $msg);
            header('Location: /login');
            exit;
        }
    } catch (Throwable $e) {
        flash('error', 'No se pudo iniciar sesión: ' . $e->getMessage() . ' ¿Ya importaste sql/schema.sql?');
        header('Location: /login');
        exit;
    }

    $user = Auth::user();
    if ($user && ($user['role'] ?? '') === 'admin') {
        header('Location: /admin');
    } else {
        header('Location: /');
    }
    exit;
});

$router->post('/logout', static function (): void {
    Auth::logout();
    header('Location: /login');
    exit;
});

$router->get('/admin', static function (): void {
    Auth::requireAdmin();
    view('admin/dashboard', [
        'title' => 'Administración',
        'user' => Auth::user(),
    ]);
});

$router->get('/admin/salud', static function (): void {
    Auth::requireAdmin();

    try {
        $checker = new HealthChecker();
        $runSmtp = isset($_GET['smtp']) && $_GET['smtp'] === '1';
        $results = [
            $checker->checkDatabase(),
            $checker->checkMoodle(),
            $checker->checkOpenPay(),
            $checker->checkStorage(),
        ];

        if ($runSmtp) {
            $results[] = $checker->checkSmtp();
        } else {
            $results[] = [
                'name' => 'SMTP',
                'ok' => null,
                'message' => 'No ejecutado automáticamente (envía correo real). Usa el botón “Probar SMTP”.',
                'meta' => ['skipped' => true],
            ];
        }

        view('admin/health', [
            'title' => 'Salud del sistema',
            'results' => $results,
            'user' => Auth::user(),
        ]);
    } catch (Throwable $e) {
        error_log('[PDV][salud] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        view('admin/health', [
            'title' => 'Salud del sistema',
            'results' => [[
                'name' => 'Panel',
                'ok' => false,
                'message' => 'Error al ejecutar salud: ' . $e->getMessage(),
            ]],
            'user' => Auth::user(),
        ]);
    }
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ?? '/');
