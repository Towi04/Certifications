<?php

declare(strict_types=1);

namespace App\Users;

use App\Database\Connection;
use PDO;

final class UserRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    /** @return array<string, string> */
    public static function manageableRoles(): array
    {
        return [
            'admin' => 'Administrador',
            'assistant' => 'Asistente',
            'manager' => 'Gestor',
        ];
    }

    /** @return array<string, string> */
    public static function allRoleLabels(): array
    {
        return self::manageableRoles() + [
            'partner' => 'Partner TR',
            'student' => 'Alumno',
        ];
    }

    public const PARTNER_DEFAULT_PASSWORD = 'Doceo1234';
    public const DEFAULT_PASSWORD = self::PARTNER_DEFAULT_PASSWORD;

    public function createPartnerUser(string $email, string $firstName, string $lastName, ?string $phone = null): int
    {
        $email = strtolower(trim($email));
        $first = trim($firstName);
        $last = trim($lastName);
        $phone = trim((string) $phone) ?: null;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Correo inválido.');
        }
        if ($first === '' || $last === '') {
            throw new \RuntimeException('Nombre y apellidos son obligatorios.');
        }
        if ($this->findByEmail($email)) {
            throw new \RuntimeException('Ya existe un usuario con ese correo.');
        }

        $username = $this->allocateUsernameFromEmail($email);
        $name = self::displayName($first, $last);
        $hash = password_hash(self::DEFAULT_PASSWORD, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new \RuntimeException('No se pudo generar la contraseña.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, phone, username, password_hash, name, first_name, last_name, role, is_active, must_change_password, email_verified_at)
             VALUES (?,?,?,?,?,?,?,?,1,1,NULL)'
        );
        $stmt->execute([$email, $phone, $username, $hash, $name, $first, $last, 'partner']);
        return (int) $this->pdo->lastInsertId();
    }

    private function allocateUsernameFromEmail(string $email): string
    {
        $base = strtolower(explode('@', $email, 2)[0]);
        $base = preg_replace('/[^a-z0-9._-]+/', '', $base) ?: 'partner';
        $username = $base;
        $n = 1;
        while ($this->findByUsername($username)) {
            $n++;
            $username = $base . $n;
        }
        return $username;
    }

    public static function displayName(?string $firstName, ?string $lastName, ?string $fallback = null): string
    {
        $full = trim(trim((string) $firstName) . ' ' . trim((string) $lastName));
        if ($full !== '') {
            return $full;
        }
        $fallback = trim((string) $fallback);
        return $fallback !== '' ? $fallback : 'Usuario';
    }

    /** @return list<array<string, mixed>> */
    public function list(?array $filters = null): array
    {
        $sql = 'SELECT id, email, phone, username, name, first_name, last_name, role, is_active,
                       must_change_password, email_verified_at, created_at, updated_at
                FROM users WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                email LIKE ? OR username LIKE ? OR name LIKE ?
                OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ?
            )';
            $q = '%' . $filters['q'] . '%';
            array_push($params, $q, $q, $q, $q, $q, $q);
        }
        if (!empty($filters['role']) && isset(self::allRoleLabels()[$filters['role']])) {
            $sql .= ' AND role = ?';
            $params[] = $filters['role'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= ' AND is_active = ?';
            $params[] = (int) $filters['is_active'];
        }

        $sql .= ' ORDER BY is_active DESC, name ASC, email ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, phone, username, name, first_name, last_name, role, is_active,
                    must_change_password, email_verified_at, created_at, updated_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEmail(string $email, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT id FROM users WHERE LOWER(TRIM(email)) = ?';
        $params = [strtolower(trim($email))];
        if ($excludeId) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUsername(string $username, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT id FROM users WHERE LOWER(TRIM(username)) = ?';
        $params = [strtolower(trim($username))];
        if ($excludeId) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function countActiveAdmins(?int $excludeId = null): int
    {
        $sql = "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1";
        $params = [];
        if ($excludeId) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    /**
     * @param array{
     *   email:string, phone:?string, username:string, first_name:string, last_name:string,
     *   role:string, password?:?string, is_active?:int
     * } $data
     */
    public function create(array $data): int
    {
        $email = strtolower(trim($data['email']));
        $username = strtolower(trim($data['username']));
        $first = trim($data['first_name']);
        $last = trim($data['last_name']);
        $name = self::displayName($first, $last);
        $role = $data['role'];
        $phone = trim((string) ($data['phone'] ?? '')) ?: null;
        $password = self::DEFAULT_PASSWORD;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Correo inválido.');
        }
        if ($username === '') {
            throw new \RuntimeException('El usuario (login) es obligatorio.');
        }
        if ($first === '') {
            throw new \RuntimeException('El nombre es obligatorio.');
        }
        if ($last === '') {
            throw new \RuntimeException('Los apellidos son obligatorios.');
        }
        if (!isset(self::manageableRoles()[$role])) {
            throw new \RuntimeException('Rol no válido.');
        }
        if ($this->findByEmail($email)) {
            throw new \RuntimeException('Ya existe un usuario con ese correo.');
        }
        if ($this->findByUsername($username)) {
            throw new \RuntimeException('Ya existe ese nombre de usuario.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new \RuntimeException('No se pudo generar la contraseña.');
        }

        // Pendiente de activación por correo
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, phone, username, password_hash, name, first_name, last_name, role, is_active, must_change_password, email_verified_at)
             VALUES (?,?,?,?,?,?,?,?,0,1,NULL)'
        );
        $stmt->execute([$email, $phone, $username, $hash, $name, $first, $last, $role]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array{
     *   email:string, phone:?string, username:string, first_name:string, last_name:string,
     *   role:string, is_active?:int
     * } $data
     */
    public function update(int $id, array $data): void
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new \RuntimeException('Usuario no encontrado.');
        }

        $email = strtolower(trim($data['email']));
        $username = strtolower(trim($data['username']));
        $first = trim($data['first_name']);
        $last = trim($data['last_name']);
        $name = self::displayName($first, $last);
        $role = $data['role'];
        $phone = trim((string) ($data['phone'] ?? '')) ?: null;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Correo inválido.');
        }
        if ($username === '') {
            throw new \RuntimeException('El usuario (login) es obligatorio.');
        }
        if ($first === '') {
            throw new \RuntimeException('El nombre es obligatorio.');
        }
        if ($last === '') {
            throw new \RuntimeException('Los apellidos son obligatorios.');
        }
        if (!isset(self::manageableRoles()[$role])) {
            // Permitir conservar partner/alumno al editar desde Usuarios (no se crean aquí).
            $locked = in_array((string) $existing['role'], ['partner', 'student'], true)
                && $role === (string) $existing['role'];
            if (!$locked) {
                throw new \RuntimeException('Rol no válido.');
            }
        }
        if ($this->findByEmail($email, $id)) {
            throw new \RuntimeException('Ya existe un usuario con ese correo.');
        }
        if ($this->findByUsername($username, $id)) {
            throw new \RuntimeException('Ya existe ese nombre de usuario.');
        }

        // No dejar el sistema sin administradores activos
        $wasActiveAdmin = ($existing['role'] === 'admin' && (int) $existing['is_active'] === 1);
        $willRemainActiveAdmin = ($role === 'admin' && (int) ($data['is_active'] ?? $existing['is_active']) === 1);
        if ($wasActiveAdmin && !$willRemainActiveAdmin && $this->countActiveAdmins($id) < 1) {
            throw new \RuntimeException('Debe quedar al menos un Administrador activo.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE users SET email=?, phone=?, username=?, name=?, first_name=?, last_name=?, role=? WHERE id=?'
        );
        $stmt->execute([$email, $phone, $username, $name, $first, $last, $role, $id]);
    }

    public function setPassword(int $id, string $password, bool $forceChange = false): void
    {
        if ($password === '') {
            throw new \RuntimeException('La contraseña no puede estar vacía.');
        }
        if (!$this->find($id)) {
            throw new \RuntimeException('Usuario no encontrado.');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new \RuntimeException('No se pudo generar la contraseña.');
        }
        $stmt = $this->pdo->prepare(
            'UPDATE users SET password_hash = ?, must_change_password = ? WHERE id = ?'
        );
        $stmt->execute([$hash, $forceChange ? 1 : 0, $id]);
    }

    /** Restablece a la contraseña temporal Doceo1234 y obliga a cambiarla. */
    public function resetToDefaultPassword(int $id): void
    {
        $this->setPassword($id, self::DEFAULT_PASSWORD, true);
    }

    public function setActive(int $id, bool $active, ?int $actorId = null): void
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new \RuntimeException('Usuario no encontrado.');
        }
        if ($actorId !== null && $actorId === $id && !$active) {
            throw new \RuntimeException('No puedes deshabilitar tu propia cuenta.');
        }
        if ($active && empty($existing['email_verified_at'])) {
            throw new \RuntimeException('La cuenta aún no se ha activado por correo. El usuario debe abrir el enlace de activación.');
        }
        if (
            !$active
            && $existing['role'] === 'admin'
            && (int) $existing['is_active'] === 1
            && $this->countActiveAdmins($id) < 1
        ) {
            throw new \RuntimeException('Debe quedar al menos un Administrador activo.');
        }

        $stmt = $this->pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    public function createActivationToken(int $userId): string
    {
        $this->ensureActivationTable();
        $this->pdo->prepare('DELETE FROM account_activations WHERE user_id = ? OR expires_at < ?')
            ->execute([$userId, date('Y-m-d H:i:s')]);

        $token = bin2hex(random_bytes(24));
        $expiresAt = date('Y-m-d H:i:s', time() + 86400 * 7);
        $this->pdo->prepare(
            'INSERT INTO account_activations (user_id, token, expires_at) VALUES (?,?,?)'
        )->execute([$userId, $token, $expiresAt]);

        return $token;
    }

    public function findActivationByToken(string $token): ?array
    {
        if (trim($token) === '') {
            return null;
        }
        $this->ensureActivationTable();
        $stmt = $this->pdo->prepare(
            'SELECT a.token, a.expires_at, u.id AS user_id, u.email, u.username, u.name, u.first_name, u.last_name, u.role, u.is_active, u.email_verified_at
             FROM account_activations a
             JOIN users u ON u.id = a.user_id
             WHERE a.token = ? AND a.expires_at >= ?
             LIMIT 1'
        );
        $stmt->execute([$token, date('Y-m-d H:i:s')]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function consumeActivationToken(string $token): void
    {
        $this->ensureActivationTable();
        $this->pdo->prepare('DELETE FROM account_activations WHERE token = ?')->execute([$token]);
    }

    /** Activa la cuenta y guarda la nueva contraseña elegida por el usuario. */
    public function activateWithPassword(int $userId, string $password): void
    {
        if ($password === self::DEFAULT_PASSWORD) {
            throw new \RuntimeException('Elige una contraseña distinta a la temporal.');
        }
        if (strlen($password) < 8) {
            throw new \RuntimeException('La contraseña debe tener al menos 8 caracteres.');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new \RuntimeException('No se pudo generar la contraseña.');
        }
        $stmt = $this->pdo->prepare(
            'UPDATE users SET password_hash = ?, is_active = 1, must_change_password = 0, email_verified_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$hash, $userId]);
    }

    public function sendWelcomeActivationEmail(int $userId): void
    {
        $user = $this->find($userId);
        if (!$user) {
            throw new \RuntimeException('Usuario no encontrado.');
        }

        $token = $this->createActivationToken($userId);
        $url = $this->buildActivationUrl($token);
        $display = self::displayName($user['first_name'] ?? null, $user['last_name'] ?? null, $user['name'] ?? null);
        $roleLabels = self::allRoleLabels();
        $roleLabel = $roleLabels[$user['role']] ?? $user['role'];

        $subject = 'Activa tu cuenta — Instituto Doceo PDV';
        $body = "Hola {$display},\n\n";
        $body .= "Se creó tu cuenta en el sistema de certificaciones de Instituto Doceo.\n\n";
        $body .= "Datos de acceso temporales:\n";
        $body .= '- Correo: ' . $user['email'] . "\n";
        $body .= '- Usuario: ' . ($user['username'] ?? '') . "\n";
        $body .= '- Contraseña temporal: ' . self::DEFAULT_PASSWORD . "\n";
        $body .= '- Rol: ' . $roleLabel . "\n\n";
        $body .= "Para activar tu cuenta y elegir una contraseña nueva, abre este enlace:\n{$url}\n\n";
        $body .= "El enlace vence en 7 días. Si no solicitaste esta cuenta, ignora este mensaje.\n\n";
        $body .= "Saludos,\nInstituto Doceo";

        $mailer = new \App\Integrations\Mailer();
        $mailer->send((string) $user['email'], $subject, $body);
    }

    public static function statusLabel(array $user): string
    {
        if ((int) ($user['is_active'] ?? 0) === 1) {
            return 'Activo';
        }
        if (empty($user['email_verified_at'])) {
            return 'Pendiente';
        }
        return 'Deshabilitado';
    }

    private function buildActivationUrl(string $token): string
    {
        $scheme = 'http';
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        }
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        return $scheme . '://' . $host . '/activate-account?token=' . rawurlencode($token);
    }

    private function ensureActivationTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS account_activations ('
            . 'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, '
            . 'user_id BIGINT UNSIGNED NOT NULL, '
            . 'token VARCHAR(255) NOT NULL, '
            . 'expires_at DATETIME NOT NULL, '
            . 'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            . 'UNIQUE KEY uq_account_activations_token (token), '
            . 'KEY idx_account_activations_user (user_id)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
