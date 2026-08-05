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
    view('auth/login', [
        'title' => 'Iniciar sesión',
        'error' => flash('error'),
    ]);
});

$router->post('/login', static function (): void {
    // Si el admin ya existe, no hace nada; nunca debe bloquear el login
    Auth::ensureBootstrapAdmin();

    $email = (string) ($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    try {
        if (!Auth::attempt($email, $password)) {
            flash('error', 'Correo o contraseña incorrectos.');
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
