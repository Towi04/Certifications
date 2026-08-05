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

        $hash = trim((string) $user['password_hash']);
        if (!password_verify($password, $hash)) {
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

    /** Crea el admin inicial desde .env solo si ese correo aún no existe. */
    public static function ensureBootstrapAdmin(): void
    {
        try {
            $pdo = Connection::get();
            $email = strtolower(Env::get('ADMIN_EMAIL', 'admin@institutodoceo.com') ?? 'admin@institutodoceo.com');

            $exists = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $exists->execute([$email]);
            if ($exists->fetch()) {
                return;
            }

            $password = Env::get('ADMIN_PASSWORD', 'CambiarYa123!') ?? 'CambiarYa123!';
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                'INSERT INTO users (email, password_hash, name, role, is_active) VALUES (?, ?, ?, ?, 1)'
            );
            $stmt->execute([$email, $hash, 'Administrador', 'admin']);
        } catch (\PDOException $e) {
            // Duplicado por carrera entre requests: no bloquea el login
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), '1062')) {
                return;
            }
            error_log('[PDV] ensureBootstrapAdmin: ' . $e->getMessage());
        } catch (\Throwable $e) {
            error_log('[PDV] ensureBootstrapAdmin: ' . $e->getMessage());
        }
    }

    /**
     * Regenera el hash del admin desde ADMIN_PASSWORD del .env.
     * Activar solo temporalmente: ADMIN_RESET_PASSWORD=true
     * Usa comillas dobles si la clave tiene * - # ! etc:
     * ADMIN_PASSWORD="mi*clave-segura"
     *
     * @return array{email: string, length: int}|null
     */
    public static function syncAdminPasswordFromEnv(): ?array
    {
        if (!Env::getBool('ADMIN_RESET_PASSWORD', false)) {
            return null;
        }

        $email = strtolower(trim(Env::get('ADMIN_EMAIL', 'admin@institutodoceo.com') ?? 'admin@institutodoceo.com'));
        $password = Env::get('ADMIN_PASSWORD');
        if ($password === null || $password === '') {
            throw new \RuntimeException('ADMIN_PASSWORD vacío en .env; no se puede resetear.');
        }

        // Evitar espacios accidentales pegados al editar en cPanel
        if ($password !== trim($password)) {
            throw new \RuntimeException(
                'ADMIN_PASSWORD tiene espacios al inicio/final. Ponla entre comillas dobles sin espacios de más.'
            );
        }

        $pdo = Connection::get();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false || !password_verify($password, $hash)) {
            throw new \RuntimeException('No se pudo generar un hash bcrypt válido.');
        }

        $stmt = $pdo->prepare(
            'UPDATE users SET password_hash = ?, is_active = 1, role = ? WHERE email = ?'
        );
        $stmt->execute([$hash, 'admin', $email]);

        if ($stmt->rowCount() === 0) {
            // Confirmar si existe con otra capitalización / collation
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $check->execute([$email]);
            if (!$check->fetch()) {
                $insert = $pdo->prepare(
                    'INSERT INTO users (email, password_hash, name, role, is_active) VALUES (?, ?, ?, ?, 1)'
                );
                $insert->execute([$email, $hash, 'Administrador', 'admin']);
            } else {
                // rowCount 0 pero existe: forzar update por id
                $force = $pdo->prepare('UPDATE users SET password_hash = ?, is_active = 1, role = ? WHERE email = ?');
                $force->execute([$hash, 'admin', $email]);
            }
        }

        $verify = $pdo->prepare('SELECT password_hash, is_active FROM users WHERE email = ? LIMIT 1');
        $verify->execute([$email]);
        $row = $verify->fetch();
        if (!$row) {
            throw new \RuntimeException("No se encontró el usuario {$email} tras el reset.");
        }
        if (!(int) $row['is_active']) {
            throw new \RuntimeException("El usuario {$email} está inactivo.");
        }
        if (!is_string($row['password_hash']) || !preg_match('/^\$2[ayb]\$/', $row['password_hash'])) {
            throw new \RuntimeException('El hash en BD no parece bcrypt. Revisa la columna password_hash.');
        }
        if (!password_verify($password, $row['password_hash'])) {
            throw new \RuntimeException(
                'El hash se guardó pero no verifica contra ADMIN_PASSWORD. ' .
                'Pon la clave entre comillas dobles en el .env, ej: ADMIN_PASSWORD="tu*clave".'
            );
        }

        return [
            'email' => $email,
            'length' => strlen($password),
        ];
    }
}
