<?php

declare(strict_types=1);

/**
 * Script de UN SOLO USO — diagnosticar y reparar login admin + esquema users.
 *
 * Abrir: https://pdv.institutodoceo.com/fix_login_once.php?key=TU_APP_KEY
 * Luego BORRAR este archivo.
 */

$rootCandidates = [
    dirname(__DIR__),
    __DIR__,
    dirname(__DIR__, 2),
];
$root = null;
foreach ($rootCandidates as $candidate) {
    if (is_file($candidate . '/src/bootstrap.php')) {
        $root = $candidate;
        break;
    }
}
if ($root === null) {
    http_response_code(500);
    echo 'No se encontró src/bootstrap.php';
    exit;
}

require $root . '/src/bootstrap.php';

use App\Config\Env;
use App\Database\Connection;

header('Content-Type: text/html; charset=UTF-8');

$provided = (string) ($_GET['key'] ?? '');
$appKey = (string) (Env::get('APP_KEY') ?? '');
if ($appKey === '' || $provided === '' || !hash_equals($appKey, $provided)) {
    http_response_code(403);
    echo '<h1>403</h1><p>Usa <code>?key=TU_APP_KEY</code> (valor de <code>APP_KEY</code> en el .env).</p>';
    exit;
}

function h(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$lines = [];
$ok = false;

try {
    $pdo = Connection::get();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $dbUser = (string) $pdo->query('SELECT CURRENT_USER()')->fetchColumn();
    $lines[] = "DATABASE()=[{$dbName}] CURRENT_USER=[{$dbUser}]";
    $lines[] = 'env DB_NAME=[' . (Env::get('DB_NAME') ?? '') . '] DB_USER=[' . (Env::get('DB_USER') ?? '') . ']';

    $hasUsers = (bool) $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$hasUsers) {
        $lines[] = 'Tabla users NO existe. Creándola...';
        $pdo->exec(<<<SQL
CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(190) NOT NULL,
  role ENUM('admin', 'partner', 'student') NOT NULL DEFAULT 'student',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $lines[] = 'Tabla users creada.';
    }

    $columns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_map(static fn(array $c): string => (string) $c['Field'], $columns);
    $lines[] = 'Columnas users: ' . implode(', ', $colNames);

    $required = ['id', 'email', 'password_hash', 'name', 'role', 'is_active'];
    $missing = array_values(array_diff($required, $colNames));
    if ($missing !== []) {
        $lines[] = 'FALTAN columnas: ' . implode(', ', $missing) . ' — se intentará reparar el esquema.';

        // Renombrar tabla rota y crear la correcta
        $backup = 'users_backup_' . date('Ymd_His');
        $pdo->exec("RENAME TABLE users TO `{$backup}`");
        $lines[] = "Tabla anterior renombrada a {$backup}";

        $pdo->exec(<<<SQL
CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(190) NOT NULL,
  role ENUM('admin', 'partner', 'student') NOT NULL DEFAULT 'student',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $lines[] = 'Nueva tabla users creada con esquema correcto.';

        // Intentar migrar datos obvios desde el backup
        $backupCols = $pdo->query("SHOW COLUMNS FROM `{$backup}`")->fetchAll(PDO::FETCH_ASSOC);
        $backupNames = array_map(static fn(array $c): string => strtolower((string) $c['Field']), $backupCols);
        $lines[] = "Columnas {$backup}: " . implode(', ', $backupNames);

        $mapEmail = null;
        foreach (['email', 'correo', 'user_email', 'username', 'user'] as $cand) {
            if (in_array($cand, $backupNames, true)) {
                $mapEmail = $cand;
                break;
            }
        }
        $mapPass = null;
        foreach (['password_hash', 'password', 'pass', 'clave', 'hash'] as $cand) {
            if (in_array($cand, $backupNames, true)) {
                $mapPass = $cand;
                break;
            }
        }
        if ($mapEmail) {
            $rows = $pdo->query("SELECT * FROM `{$backup}`")->fetchAll(PDO::FETCH_ASSOC);
            $ins = $pdo->prepare('INSERT INTO users (email, password_hash, name, role, is_active) VALUES (?, ?, ?, ?, 1)');
            foreach ($rows as $r) {
                $keys = array_change_key_case($r, CASE_LOWER);
                $em = strtolower(trim((string) ($keys[$mapEmail] ?? '')));
                if ($em === '' || !str_contains($em, '@')) {
                    continue;
                }
                $rawPass = (string) ($keys[$mapPass] ?? '');
                $hash = str_starts_with($rawPass, '$2') ? $rawPass : password_hash($rawPass !== '' ? $rawPass : 'Temporal123!', PASSWORD_DEFAULT);
                $name = (string) ($keys['name'] ?? $keys['nombre'] ?? 'Usuario');
                $role = in_array(($keys['role'] ?? ''), ['admin', 'partner', 'student'], true) ? $keys['role'] : 'admin';
                try {
                    $ins->execute([$em, $hash, $name !== '' ? $name : 'Usuario', $role]);
                    $lines[] = "Migrado: {$em}";
                } catch (Throwable $e) {
                    $lines[] = "No migró {$em}: " . $e->getMessage();
                }
            }
        }
    }

    $raw = $pdo->query('SELECT * FROM users LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
    $lines[] = 'Filas actuales: ' . count($raw);
    foreach ($raw as $i => $row) {
        $safe = $row;
        if (isset($safe['password_hash'])) {
            $safe['password_hash'] = substr((string) $safe['password_hash'], 0, 7) . '…(len ' . strlen((string) $row['password_hash']) . ')';
        }
        $lines[] = ' row[' . $i . '] keys=' . implode('|', array_keys($row));
        $lines[] = ' row[' . $i . ']=' . json_encode($safe, JSON_UNESCAPED_UNICODE);
    }

    $email = strtolower(trim((string) (Env::get('ADMIN_EMAIL') ?: 'admin@institutodoceo.com')));
    $password = (string) (Env::get('ADMIN_PASSWORD') ?? '');
    if ($password === '') {
        throw new RuntimeException('ADMIN_PASSWORD vacío en .env');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false || !password_verify($password, $hash)) {
        throw new RuntimeException('No se pudo generar bcrypt');
    }

    $find = $pdo->prepare('SELECT id FROM users WHERE LOWER(TRIM(email)) = ? LIMIT 1');
    $find->execute([$email]);
    $id = $find->fetchColumn();

    if ($id) {
        $upd = $pdo->prepare('UPDATE users SET email = ?, password_hash = ?, name = ?, role = ?, is_active = 1 WHERE id = ?');
        $upd->execute([$email, $hash, 'Administrador', 'admin', (int) $id]);
        $lines[] = "Admin actualizado id={$id}";
    } else {
        $ins = $pdo->prepare('INSERT INTO users (email, password_hash, name, role, is_active) VALUES (?, ?, ?, ?, 1)');
        $ins->execute([$email, $hash, 'Administrador', 'admin']);
        $id = (int) $pdo->lastInsertId();
        $lines[] = "Admin creado id={$id}";
    }

    $check = $pdo->prepare('SELECT id, email, role, is_active, password_hash FROM users WHERE id = ?');
    $check->execute([(int) $id]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('No se pudo releer el admin tras guardar.');
    }

    $verify = password_verify($password, (string) $row['password_hash']);
    $lines[] = 'Verify: id=' . $row['id']
        . ' email=' . $row['email']
        . ' role=' . $row['role']
        . ' active=' . $row['is_active']
        . ' password_verify=' . ($verify ? 'OK' : 'FAIL');

    if ((int) $row['is_active'] !== 1 || !$verify || (string) $row['role'] !== 'admin') {
        throw new RuntimeException('Admin aún no usable tras reparación.');
    }

    $ok = true;
    $lines[] = 'LISTO. Entra a /login con:';
    $lines[] = "  email: {$email}";
    $lines[] = '  password: (ADMIN_PASSWORD del .env, longitud ' . strlen($password) . ')';
    $lines[] = 'Luego: ADMIN_RESET_PASSWORD=false y BORRA fix_login_once.php';
} catch (Throwable $e) {
    $lines[] = 'ERROR: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Fix login Doceo</title>
    <style>
        body { font-family: ui-monospace, monospace; background: #0a0a0a; color: #f5df25; padding: 2rem; }
        .box { background: #315285; color: #fff; padding: 1rem 1.25rem; border-radius: 12px; max-width: 980px; }
        pre { white-space: pre-wrap; background: #111; color: #eee; padding: 1rem; border-radius: 8px; }
        .ok { color: #8dffb0; } .bad { color: #ff8d8d; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Reparar login admin</h1>
        <p class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? 'Éxito' : 'Falló' ?></p>
        <pre><?php foreach ($lines as $line) {
            echo h($line), "\n";
        } ?></pre>
        <p>BORRA este archivo cuando termines.</p>
    </div>
</body>
</html>
