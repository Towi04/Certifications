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
            'partner' => 'Partner TR',
        ];
    }

    /** @return array<string, string> */
    public static function allRoleLabels(): array
    {
        return self::manageableRoles() + ['student' => 'Alumno'];
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
        $sql = 'SELECT id, email, phone, username, name, first_name, last_name, role, is_active, created_at, updated_at
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
            'SELECT id, email, phone, username, name, first_name, last_name, role, is_active, created_at, updated_at
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
        $password = (string) ($data['password'] ?? '');

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
        if ($password === '') {
            throw new \RuntimeException('La contraseña es obligatoria.');
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

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, phone, username, password_hash, name, first_name, last_name, role, is_active)
             VALUES (?,?,?,?,?,?,?,?,1)'
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
        if (!isset(self::manageableRoles()[$role]) && !($role === 'student' && $existing['role'] === 'student')) {
            throw new \RuntimeException('Rol no válido.');
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

    public function setPassword(int $id, string $password): void
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
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $id]);
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
}
