<?php

declare(strict_types=1);

namespace App\Auth;

use App\Config\Env;
use App\Database\Connection;
use PDO;

final class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('doceo_pdv_session');
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
        ]);
    }

    public static function attempt(string $email, string $password): bool
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare('SELECT id, email, password_hash, role, name, is_active FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();

        if (!$user || !(int) $user['is_active']) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        try {
            session_regenerate_id(true);
        } catch (\Throwable $e) {
            error_log('[PDV] session_regenerate_id: ' . $e->getMessage());
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
            'name' => (string) $user['name'],
        ];

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    /** @return array{id:int,email:string,role:string,name:string}|null */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        $user = self::user();
        if ($user === null || $user['role'] !== 'admin') {
            http_response_code(403);
            echo 'Acceso denegado.';
            exit;
        }
    }

    /** Crea el admin inicial desde .env si la tabla users está vacía. */
    public static function ensureBootstrapAdmin(): void
    {
        try {
            $pdo = Connection::get();
        } catch (\Throwable) {
            return;
        }

        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        } catch (\Throwable) {
            return;
        }

        if ($count > 0) {
            return;
        }

        $email = strtolower(Env::get('ADMIN_EMAIL', 'admin@institutodoceo.com') ?? 'admin@institutodoceo.com');
        $password = Env::get('ADMIN_PASSWORD', 'CambiarYa123!') ?? 'CambiarYa123!';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (email, password_hash, name, role, is_active) VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([$email, $hash, 'Administrador', 'admin']);
    }
}
