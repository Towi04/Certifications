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
        header('Location: /admin');
        exit;
    }
    view('auth/login', [
        'title' => 'Iniciar sesión',
        'error' => flash('error'),
    ]);
});

$router->post('/login', static function (): void {
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
    if ($user && $user['role'] === 'admin') {
        header('Location: /admin/salud');
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
    header('Location: /admin/salud');
    exit;
});

$router->get('/admin/salud', static function (): void {
    Auth::requireAdmin();

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
        ];
    }

    view('admin/health', [
        'title' => 'Salud del sistema',
        'results' => $results,
        'user' => Auth::user(),
    ]);
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ?? '/');
