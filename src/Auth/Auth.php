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

        $sessionDir = self::resolveSessionDirectory();
        if ($sessionDir !== '') {
            session_save_path($sessionDir);
        }

        session_name('doceo_pdv_session');
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
        ]);

        self::writeDebugLog('session_started path=' . session_save_path() . ' session_id=' . session_id());
    }

    private static function resolveSessionDirectory(): string
    {
        $candidates = [
            BASE_PATH . '/storage/sessions',
            rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/doceo-pdv-sessions',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if (!is_dir($candidate)) {
                @mkdir($candidate, 0755, true);
            }
            if (is_dir($candidate) && is_writable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private static function writeDebugLog(string $message): void
    {
        $logFile = BASE_PATH . '/storage/logs/login-debug.log';
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND);
    }

    private static function normalizeIdentifier(string $identifier): string
    {
        return strtolower(trim($identifier));
    }

    /**
     * Resuelve las credenciales desde POST, GET o un cuerpo JSON.
     *
     * @param array<string, mixed> $input
     * @return array{identifier:string,password:string}
     */
    public static function resolveLoginCredentials(array $input = []): array
    {
        $source = $input;
        if ($source === []) {
            $source = array_merge($_GET, $_POST);
            if ($source === []) {
                $raw = file_get_contents('php://input');
                if (is_string($raw) && trim($raw) !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $source = $decoded;
                    }
                }
            }
        }

        $identifier = '';
        foreach (['email', 'username', 'login', 'user', 'identifier', 'email_or_username', 'user_login'] as $key) {
            if (isset($source[$key])) {
                $identifier = (string) $source[$key];
                break;
            }
        }

        $password = '';
        foreach (['password', 'pass', 'pwd'] as $key) {
            if (isset($source[$key])) {
                $password = (string) $source[$key];
                break;
            }
        }

        return [
            'identifier' => self::normalizeIdentifier($identifier),
            'password' => $password,
        ];
    }

    public static function attempt(string $identifier, string $password): bool
    {
        $normalized = self::normalizeIdentifier($identifier);
        self::writeDebugLog('login_attempt identifier=' . $normalized . ' password_len=' . strlen($password));

        $pdo = Connection::get();
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $quotedIdentifier = $pdo->quote($normalized);
        $query = 'SELECT id, email, username, password_hash, role, name, is_active '
            . "FROM users WHERE LOWER(TRIM(email)) = {$quotedIdentifier} OR LOWER(TRIM(username)) = {$quotedIdentifier} LIMIT 1";
        $stmt = $pdo->query($query);
        $user = $stmt ? $stmt->fetch() : false;

        if (!$user) {
            self::writeDebugLog('db_name=' . $dbName . ' login_failed reason=user_not_found identifier=' . $normalized);
            return false;
        }

        self::writeDebugLog('db_name=' . $dbName . ' row=' . json_encode($user, JSON_UNESCAPED_UNICODE));

        if (!$user) {
            self::writeDebugLog('login_failed reason=user_not_found identifier=' . $normalized);
            return false;
        }

        $hash = trim((string) $user['password_hash']);
        if (!password_verify($password, $hash)) {
            self::writeDebugLog('login_failed reason=password_mismatch identifier=' . $normalized . ' user_id=' . ((int) $user['id']));
            return false;
        }

        $userId = (int) $user['id'];
        $role = (string) ($user['role'] ?? 'student');
        $isActive = (int) ($user['is_active'] ?? 0);
        if ($isActive !== 1) {
            if ($role === 'admin') {
                $pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ?')->execute([$userId]);
                $isActive = 1;
                self::writeDebugLog('admin_reactivated user_id=' . $userId . ' identifier=' . $normalized);
            } else {
                self::writeDebugLog('login_failed reason=user_inactive identifier=' . $normalized . ' user_id=' . $userId . ' role=' . $role);
                return false;
            }
        }

        try {
            session_regenerate_id(true);
        } catch (\Throwable $e) {
            error_log('[PDV] session_regenerate_id: ' . $e->getMessage());
        }

        $_SESSION['user'] = [
            'id' => $userId,
            'email' => (string) $user['email'],
            'role' => $role,
            'name' => (string) $user['name'],
        ];

        self::writeDebugLog('login_success user_id=' . $userId . ' role=' . $role . ' session_id=' . session_id());

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
            $username = Env::get('ADMIN_USERNAME');
            if ($username === null || trim($username) === '') {
                $username = explode('@', $email, 2)[0];
            }
            $username = strtolower(trim($username));

            $exists = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $exists->execute([$email]);
            if ($exists->fetch()) {
                return;
            }

            $password = Env::get('ADMIN_PASSWORD', 'CambiarYa123!') ?? 'CambiarYa123!';
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                'INSERT INTO users (email, username, password_hash, name, role, is_active) VALUES (?, ?, ?, ?, ?, 1)'
            );
            $stmt->execute([$email, $username, $hash, 'Administrador', 'admin']);
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

        $find = $pdo->prepare('SELECT id, email, is_active FROM users WHERE LOWER(TRIM(email)) = ? LIMIT 1');
        $find->execute([$email]);
        $user = $find->fetch();

        $username = Env::get('ADMIN_USERNAME');
        if ($username === null || trim($username) === '') {
            $username = explode('@', $email, 2)[0];
        }
        $username = strtolower(trim($username));

        if ($user) {
            // Reactivar + nueva clave por id (evita fallos si is_active=0 u email con espacios)
            $upd = $pdo->prepare(
                'UPDATE users SET email = ?, username = ?, password_hash = ?, is_active = 1, role = ? WHERE id = ?'
            );
            $upd->execute([$email, $username, $hash, 'admin', (int) $user['id']]);
            $userId = (int) $user['id'];
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO users (email, username, password_hash, name, role, is_active) VALUES (?, ?, ?, ?, ?, 1)'
            );
            $insert->execute([$email, $username, $hash, 'Administrador', 'admin']);
            $userId = (int) $pdo->lastInsertId();
        }

        $verify = $pdo->prepare('SELECT email, password_hash, is_active, role FROM users WHERE id = ? LIMIT 1');
        $verify->execute([$userId]);
        $row = $verify->fetch();
        if (!$row) {
            throw new \RuntimeException("No se encontró el usuario {$email} tras el reset.");
        }
        if ((int) $row['is_active'] !== 1) {
            // Segundo intento explícito
            $pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ?')->execute([$userId]);
            $row['is_active'] = 1;
        }
        if ((string) $row['role'] !== 'admin') {
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute(['admin', $userId]);
        }
        if (!is_string($row['password_hash']) || !preg_match('/^\$2[ayb]\$/', $row['password_hash'])) {
            throw new \RuntimeException('El hash en BD no parece bcrypt. Revisa la columna password_hash.');
        }
        if (!password_verify($password, trim((string) $row['password_hash']))) {
            throw new \RuntimeException(
                'El hash se guardó pero no verifica contra ADMIN_PASSWORD. ' .
                'Pon la clave entre comillas dobles en el .env, ej: ADMIN_PASSWORD="tu*clave".'
            );
        }

        return [
            'email' => (string) $row['email'],
            'length' => strlen($password),
        ];
    }
}
