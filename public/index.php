<?php

declare(strict_types=1);

use App\Auth\Auth;
use App\Http\Router;
use App\Integrations\HealthChecker;

require dirname(__DIR__) . '/src/bootstrap.php';

$router = new Router();

$runLogin = static function (bool $fromGet = false): void {
    Auth::ensureBootstrapAdmin();

    try {
        Auth::syncAdminPasswordFromEnv();
    } catch (Throwable $e) {
        error_log('[PDV] reset admin en login: ' . $e->getMessage());
    }

    $credentials = Auth::resolveLoginCredentials($fromGet ? ($_GET ?? []) : ($_POST ?? []));
    $login = strtolower(trim($credentials['identifier']));
    $password = (string) $credentials['password'];

    if ($login === '' || $password === '') {
        if ($fromGet) {
            $info = flash('info');
            try {
                $reset = Auth::syncAdminPasswordFromEnv();
                if ($reset !== null) {
                    $info = 'Hash actualizado para ' . $reset['email']
                        . ' (longitud ADMIN_PASSWORD: ' . $reset['length'] . '). '
                        . 'Entra con ese correo/clave y luego pon ADMIN_RESET_PASSWORD=false.';
                }
            } catch (Throwable $e) {
                $info = 'Aviso reset: ' . $e->getMessage()
                    . ' — Si ya insertaste el admin en phpMyAdmin, pon ADMIN_RESET_PASSWORD=false y entra normal.';
            }

            view('auth/login', [
                'title' => 'Iniciar sesión',
                'error' => flash('error'),
                'info' => $info,
            ]);
            return;
        }

        flash('error', 'Correo o contraseña incorrectos.');
        header('Location: /login');
        exit;
    }

    try {
        if (!Auth::attempt($login, $password)) {
            $msg = 'Correo o contraseña incorrectos.';
            if (\App\Config\Env::getBool('ADMIN_RESET_PASSWORD', false)) {
                $msg .= ' Tip: pon ADMIN_RESET_PASSWORD=false en el .env si ya reparaste el usuario en la BD.';
            }
            flash('error', $msg);
            header('Location: /login');
            exit;
        }
    } catch (Throwable $e) {
        flash('error', 'No se pudo iniciar sesión: ' . $e->getMessage());
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
};

$router->get('/', static function (): void {
    view('home', [
        'title' => 'Inicio',
    ]);
});

$router->post('/login', static function () use ($runLogin): void {
    $runLogin(false);
});

$router->get('/login', static function () use ($runLogin): void {
    if (Auth::check()) {
        $user = Auth::user();
        header('Location: ' . (($user['role'] ?? '') === 'admin' ? '/admin' : '/'));
        exit;
    }

    $runLogin(true);
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
