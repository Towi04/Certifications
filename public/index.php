<?php

declare(strict_types=1);

use App\Auth\Auth;
use App\Catalog\CatalogRepository;
use App\Http\AdminRoutes;
use App\Http\PartnerRoutes;
use App\Http\Router;

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
    $role = $user['role'] ?? '';
    if (Auth::isStaffRole($role)) {
        header('Location: /admin');
    } elseif ($role === 'partner') {
        header('Location: /partner');
    } else {
        header('Location: /');
    }
    exit;
};

$postLoginPath = static function (): string {
    $user = Auth::user();
    $role = $user['role'] ?? '';
    if (Auth::isStaffRole($role)) {
        return '/admin';
    }
    if ($role === 'partner') {
        return '/partner';
    }
    return '/';
};

$router->get('/', static function (): void {
    view('home', [
        'title' => 'Inicio',
    ]);
});

$router->post('/login', static function () use ($runLogin): void {
    $runLogin(false);
});

$router->get('/login', static function () use ($runLogin, $postLoginPath): void {
    if (Auth::check()) {
        header('Location: ' . $postLoginPath());
        exit;
    }

    $runLogin(true);
});

$router->post('/logout', static function (): void {
    Auth::logout();
    header('Location: /login');
    exit;
});

$router->get('/register', static function (): void {
    if (Auth::check()) {
        header('Location: /profile');
        exit;
    }

    view('auth/register', [
        'title' => 'Registro',
        'error' => flash('error'),
        'info' => flash('info'),
    ]);
});

$router->post('/register', static function (): void {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $name = trim((string) ($_POST['name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($email === '' || $name === '' || $password === '' || $passwordConfirm === '') {
        flash('error', 'Completa todos los campos.');
        header('Location: /register');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Correo electrónico no válido.');
        header('Location: /register');
        exit;
    }

    if ($password !== $passwordConfirm) {
        flash('error', 'Las contraseñas no coinciden.');
        header('Location: /register');
        exit;
    }

    if (strlen($password) < 8) {
        flash('error', 'La contraseña debe tener al menos 8 caracteres.');
        header('Location: /register');
        exit;
    }

    try {
        Auth::register($email, $name, $password);
        flash('info', 'Cuenta creada. Ahora inicia sesión.');
        header('Location: /login');
        exit;
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        header('Location: /register');
        exit;
    }
});

$router->get('/forgot-password', static function (): void {
    view('auth/forgot_password', [
        'title' => 'Olvidé mi contraseña',
        'error' => flash('error'),
        'info' => flash('info'),
    ]);
});

$router->post('/forgot-password', static function (): void {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Introduce un correo válido.');
        header('Location: /forgot-password');
        exit;
    }

    try {
        Auth::sendPasswordResetEmail($email);
        flash('info', 'Si existe una cuenta con ese correo, te hemos enviado un enlace para restablecer la contraseña.');
    } catch (Throwable $e) {
        error_log('[PDV] forgot-password: ' . $e->getMessage());
        flash('info', 'Si existe una cuenta con ese correo, te hemos enviado un enlace para restablecer la contraseña.');
    }

    header('Location: /forgot-password');
    exit;
});

$router->get('/reset-password', static function (): void {
    $token = (string) ($_GET['token'] ?? '');
    if ($token === '' || Auth::verifyPasswordResetToken($token) === null) {
        flash('error', 'Token inválido o expirado.');
        header('Location: /forgot-password');
        exit;
    }

    view('auth/reset_password', [
        'title' => 'Restablecer contraseña',
        'error' => flash('error'),
        'info' => flash('info'),
        'token' => $token,
    ]);
});

$router->post('/reset-password', static function (): void {
    $token = (string) ($_POST['token'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($token === '' || Auth::verifyPasswordResetToken($token) === null) {
        flash('error', 'Token inválido o expirado.');
        header('Location: /forgot-password');
        exit;
    }

    if ($password === '' || $passwordConfirm === '') {
        flash('error', 'Completa todos los campos.');
        header('Location: /reset-password?token=' . rawurlencode($token));
        exit;
    }

    if ($password !== $passwordConfirm) {
        flash('error', 'Las contraseñas no coinciden.');
        header('Location: /reset-password?token=' . rawurlencode($token));
        exit;
    }

    if (strlen($password) < 8) {
        flash('error', 'La contraseña debe tener al menos 8 caracteres.');
        header('Location: /reset-password?token=' . rawurlencode($token));
        exit;
    }

    $resetData = Auth::verifyPasswordResetToken($token);
    if ($resetData === null) {
        flash('error', 'Token inválido o expirado.');
        header('Location: /forgot-password');
        exit;
    }

    try {
        $user = Auth::findUserByEmail($resetData['email']);
        if (!$user) {
            throw new RuntimeException('No se encontró el usuario.');
        }

        Auth::updatePassword((int) $user['id'], $password);
        Auth::consumePasswordResetToken($token);
        flash('info', 'Contraseña actualizada. Ya puedes iniciar sesión.');
        header('Location: /login');
        exit;
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        header('Location: /reset-password?token=' . rawurlencode($token));
        exit;
    }
});

$router->get('/profile', static function (): void {
    Auth::requireLogin();

    view('auth/profile', [
        'title' => 'Perfil',
        'error' => flash('error'),
        'info' => flash('info'),
        'user' => Auth::user(),
    ]);
});

$router->post('/profile', static function (): void {
    Auth::requireLogin();
    $user = Auth::user();
    if ($user === null) {
        header('Location: /login');
        exit;
    }

    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($currentPassword === '' || $password === '' || $passwordConfirm === '') {
        flash('error', 'Completa todos los campos.');
        header('Location: /profile');
        exit;
    }

    if ($password !== $passwordConfirm) {
        flash('error', 'Las contraseñas no coinciden.');
        header('Location: /profile');
        exit;
    }

    if (strlen($password) < 8) {
        flash('error', 'La contraseña debe tener al menos 8 caracteres.');
        header('Location: /profile');
        exit;
    }

    if (!Auth::attempt($user['email'], $currentPassword)) {
        flash('error', 'La contraseña actual no es correcta.');
        header('Location: /profile');
        exit;
    }

    try {
        Auth::updatePassword((int) $user['id'], $password);
        flash('info', 'Tu contraseña se actualizó correctamente.');
        header('Location: /profile');
        exit;
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        header('Location: /profile');
        exit;
    }
});

$router->get('/admin', static function (): void {
    Auth::requireAdmin();
    $counts = [];
    try {
        $counts = (new CatalogRepository())->counts();
    } catch (Throwable $e) {
        error_log('[PDV] dashboard counts: ' . $e->getMessage());
    }
    view('admin/dashboard', [
        'title' => 'Administración',
        'user' => Auth::user(),
        'counts' => $counts,
    ]);
});

AdminRoutes::register($router);
PartnerRoutes::register($router);

$router->get('/media', static function (): void {
    $relative = (string) ($_GET['f'] ?? '');
    $path = \App\Support\Uploader::absolutePath($relative);
    if ($path === null || !is_file($path)) {
        http_response_code(404);
        echo 'Archivo no encontrado.';
        exit;
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=86400');
    readfile($path);
    exit;
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ?? '/');
