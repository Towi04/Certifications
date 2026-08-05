<?php

declare(strict_types=1);

/**
 * Script de UN SOLO USO para diagnosticar y reparar el login admin.
 * 1) Súbelo al servidor (junto a index.php / en la raíz del proyecto o en public/).
 * 2) Ábrelo en el navegador:
 *    https://pdv.institutodoceo.com/fix_login_once.php?key=VALOR_DE_APP_KEY
 *    (APP_KEY es el del archivo .env)
 * 3) BORRA este archivo al terminar.
 */

$root = is_file(dirname(__DIR__) . '/src/bootstrap.php')
    ? dirname(__DIR__)
    : __DIR__;

if (!is_file($root . '/src/bootstrap.php')) {
    // Si el Document Root es la raíz del repo
    $root = __DIR__;
}

require $root . '/src/bootstrap.php';

use App\Config\Env;
use App\Database\Connection;

header('Content-Type: text/html; charset=UTF-8');

$provided = (string) ($_GET['key'] ?? '');
$appKey = (string) (Env::get('APP_KEY') ?? '');

if ($appKey === '' || $provided === '' || !hash_equals($appKey, $provided)) {
    http_response_code(403);
    echo '<h1>403</h1><p>Usa <code>?key=TU_APP_KEY</code> (el valor de <code>APP_KEY</code> en el .env).</p>';
    exit;
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$lines = [];
$ok = false;

try {
    $pdo = Connection::get();
    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $dbUser = (string) $pdo->query('SELECT CURRENT_USER()')->fetchColumn();
    $lines[] = "Conectado a DATABASE() = [{$dbName}] como [{$dbUser}]";
    $lines[] = 'DB_NAME en .env = [' . (Env::get('DB_NAME') ?? '') . ']';
    $lines[] = 'DB_USER en .env = [' . (Env::get('DB_USER') ?? '') . ']';
    $lines[] = 'ADMIN_EMAIL en .env = [' . (Env::get('ADMIN_EMAIL') ?? '') . ']';
    $lines[] = 'ADMIN_PASSWORD longitud = ' . strlen((string) (Env::get('ADMIN_PASSWORD') ?? ''));
    $lines[] = 'ADMIN_RESET_PASSWORD = [' . (Env::get('ADMIN_RESET_PASSWORD') ?? '') . ']';

    $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
    if (!$tables) {
        throw new RuntimeException("En la base [{$dbName}] NO existe la tabla users. ¿Importaste sql/schema.sql aquí?");
    }

    $all = $pdo->query('SELECT id, email, role, is_active, CHAR_LENGTH(password_hash) AS hash_len, LEFT(password_hash, 7) AS hash_prefix FROM users ORDER BY id')->fetchAll();
    $lines[] = 'Usuarios en esta base: ' . count($all);
    foreach ($all as $u) {
        $lines[] = sprintf(
            ' - id=%s email=[%s] hex=%s role=%s active=%s hash_len=%s prefix=%s',
            $u['id'],
            $u['email'],
            bin2hex((string) $u['email']),
            $u['role'],
            $u['is_active'],
            $u['hash_len'],
            $u['hash_prefix']
        );
    }

    $email = strtolower(trim((string) (Env::get('ADMIN_EMAIL') ?: 'admin@institutodoceo.com')));
    $password = (string) (Env::get('ADMIN_PASSWORD') ?? '');
    if ($password === '') {
        $password = 'DoceoTemp' . random_int(1000, 9999);
        $lines[] = "ADMIN_PASSWORD vacío: se usará temporal [{$password}]";
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('password_hash falló');
    }

    $find = $pdo->prepare('SELECT id, email FROM users WHERE LOWER(TRIM(email)) = ? LIMIT 1');
    $find->execute([$email]);
    $existing = $find->fetch();

    if ($existing) {
        $stmt = $pdo->prepare('UPDATE users SET email = ?, password_hash = ?, role = ?, is_active = 1, name = COALESCE(NULLIF(name, \'\'), ?) WHERE id = ?');
        $stmt->execute([$email, $hash, 'admin', 'Administrador', (int) $existing['id']]);
        $lines[] = 'UPDATE hecho sobre id=' . $existing['id'];
    } else {
        // También intenta por LIKE por si hay basura invisible
        $fuzzy = $pdo->query("SELECT id, email, HEX(email) AS hex FROM users")->fetchAll();
        $matched = null;
        foreach ($fuzzy as $row) {
            if (str_contains(strtolower($row['email']), 'admin@') || str_contains(strtolower($row['email']), 'institutodoceo')) {
                $matched = $row;
                break;
            }
        }
        if ($matched) {
            $stmt = $pdo->prepare('UPDATE users SET email = ?, password_hash = ?, role = ?, is_active = 1 WHERE id = ?');
            $stmt->execute([$email, $hash, 'admin', (int) $matched['id']]);
            $lines[] = 'UPDATE fuzzy: email BD era [' . $matched['email'] . '] hex=' . $matched['hex'];
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, name, role, is_active) VALUES (?, ?, ?, ?, 1)');
            $stmt->execute([$email, $hash, 'Administrador', 'admin']);
            $lines[] = 'INSERT nuevo admin id=' . $pdo->lastInsertId();
        }
    }

    $check = $pdo->prepare('SELECT id, email, role, is_active, password_hash FROM users WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    $row = $check->fetch();
    if (!$row) {
        throw new RuntimeException('Tras UPDATE/INSERT sigue sin aparecer el admin. Revisa que el .env use la misma BD que phpMyAdmin.');
    }

    $verify = password_verify($password, (string) $row['password_hash']);
    $lines[] = 'is_active=' . $row['is_active'] . ' role=' . $row['role'] . ' password_verify=' . ($verify ? 'OK' : 'FAIL');

    if (!(int) $row['is_active'] || !$verify) {
        throw new RuntimeException('No quedó usable el admin. Revisa columnas is_active/password_hash.');
    }

    $ok = true;
    $lines[] = 'LISTO. Entra en /login con:';
    $lines[] = "  correo: {$email}";
    $lines[] = "  contraseña: (la de ADMIN_PASSWORD en .env, longitud " . strlen($password) . ')';
    $lines[] = 'Luego pon ADMIN_RESET_PASSWORD=false y BORRA fix_login_once.php del servidor.';
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
        .box { background: #315285; color: #fff; padding: 1rem 1.25rem; border-radius: 12px; max-width: 900px; }
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
