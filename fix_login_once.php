<?php
declare(strict_types=1);
/**
 * FIX LOGIN v3 — si no ves "FIX LOGIN v3" arriba del resultado, ESTE archivo no está en el servidor.
 * https://pdv.institutodoceo.com/fix_login_once.php?key=TU_APP_KEY
 * BORRAR después de usar.
 */

header('Content-Type: text/html; charset=UTF-8');
echo '<h1>FIX LOGIN v3</h1>';

// --- localizar .env ---
$roots = [__DIR__, dirname(__DIR__), dirname(__DIR__, 2)];
$envPath = null;
foreach ($roots as $r) {
    if (is_file($r . '/.env')) { $envPath = $r . '/.env'; $root = $r; break; }
}
if (!$envPath) {
    http_response_code(500);
    echo '<p>No encontré .env. Sube este archivo junto al .env (raíz del proyecto).</p>';
    exit;
}

function env_load(string $path): array {
    $out = [];
    $raw = file_get_contents($path);
    if ($raw === false) return $out;
    if (str_starts_with($raw, "\xEF\xBB\xBF")) $raw = substr($raw, 3);
    foreach (preg_split("/\r\n|\n|\r/", $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v);
        if ($k === '') continue;
        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
        }
        $out[$k] = $v;
    }
    return $out;
}

$env = env_load($envPath);
$key = (string)($_GET['key'] ?? '');
$appKey = (string)($env['APP_KEY'] ?? '');
if ($appKey === '' || $key === '' || !hash_equals($appKey, $key)) {
    http_response_code(403);
    echo '<p>403 — usa ?key= valor de APP_KEY del .env</p>';
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$lines = [];
$ok = false;

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $env['DB_HOST'] ?? 'localhost',
        $env['DB_NAME'] ?? ''
    );
    $pdo = new PDO($dsn, $env['DB_USER'] ?? '', $env['DB_PASS'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $lines[] = 'OK conexión a [' . $db . ']';
    $lines[] = '.env path = ' . $envPath;

    // ¿Existe users?
    $exists = (bool)$pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($exists) {
        $cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll();
        $names = array_column($cols, 'Field');
        $lines[] = 'Columnas actuales: ' . implode(', ', $names);

        $need = ['id','email','password_hash','name','role','is_active'];
        $missing = array_diff($need, $names);
        $lines[] = 'Faltan: ' . ($missing ? implode(', ', $missing) : '(ninguna)');

        // Dump crudo
        $raw = $pdo->query('SELECT * FROM users LIMIT 3')->fetchAll();
        $lines[] = 'SELECT * count=' . count($raw);
        foreach ($raw as $i => $row) {
            $lines[] = ' keys['.$i.']=' . implode('|', array_keys($row));
            $copy = $row;
            foreach ($copy as $k => $v) {
                if (stripos($k, 'pass') !== false || stripos($k, 'hash') !== false) {
                    $copy[$k] = is_string($v) ? (substr($v, 0, 7) . '…') : $v;
                }
            }
            $lines[] = ' data['.$i.']=' . json_encode($copy, JSON_UNESCAPED_UNICODE);
        }

        if ($missing) {
            $bak = 'users_backup_' . date('Ymd_His');
            $pdo->exec("RENAME TABLE users TO `{$bak}`");
            $lines[] = "Renombrada users → {$bak}";
            $exists = false;
        }
    }

    if (!$exists) {
        $pdo->exec("CREATE TABLE users (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          email VARCHAR(190) NOT NULL,
          username VARCHAR(190) NULL,
          password_hash VARCHAR(255) NOT NULL,
          name VARCHAR(190) NOT NULL,
          role ENUM('admin','partner','student') NOT NULL DEFAULT 'student',
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uq_users_email (email),
          UNIQUE KEY uq_users_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $lines[] = 'Tabla users creada con esquema correcto';
    }

    $email = strtolower(trim($env['ADMIN_EMAIL'] ?? 'admin@institutodoceo.com'));
    $username = $env['ADMIN_USERNAME'] ?? null;
    if ($username === null || trim($username) === '') {
        $username = explode('@', $email, 2)[0];
    }
    $username = strtolower(trim($username));

    $pass = (string)($env['ADMIN_PASSWORD'] ?? '');
    if ($pass === '') throw new RuntimeException('ADMIN_PASSWORD vacío');
    $hash = password_hash($pass, PASSWORD_DEFAULT);

    $id = $pdo->prepare('SELECT id FROM users WHERE LOWER(TRIM(email)) = ?');
    $id->execute([$email]);
    $uid = $id->fetchColumn();

    $username = $env['ADMIN_USERNAME'] ?? null;
    if ($username === null || trim($username) === '') {
        $username = explode('@', $email, 2)[0];
    }
    $username = strtolower(trim($username));

    if ($uid) {
        $pdo->prepare('UPDATE users SET email=?, username=?, password_hash=?, name=?, role=?, is_active=1 WHERE id=?')
            ->execute([$email, $username, $hash, 'Administrador', 'admin', (int)$uid]);
        $lines[] = "UPDATE admin id={$uid}";
    } else {
        $pdo->prepare('INSERT INTO users (email,username,password_hash,name,role,is_active) VALUES (?,?,?,?,?,1)')
            ->execute([$email, $username, $hash, 'Administrador', 'admin']);
        $uid = $pdo->lastInsertId();
        $lines[] = "INSERT admin id={$uid}";
    }

    $st = $pdo->prepare('SELECT id,email,role,is_active,password_hash FROM users WHERE id=?');
    $st->execute([(int)$uid]);
    $u = $st->fetch();
    $ver = $u && password_verify($pass, $u['password_hash']);
    $lines[] = 'Resultado: ' . json_encode([
        'id' => $u['id'] ?? null,
        'email' => $u['email'] ?? null,
        'role' => $u['role'] ?? null,
        'is_active' => $u['is_active'] ?? null,
        'hash_prefix' => isset($u['password_hash']) ? substr($u['password_hash'], 0, 7) : null,
        'password_verify' => $ver ? 'OK' : 'FAIL',
    ], JSON_UNESCAPED_UNICODE);

    if (!$u || !(int)$u['is_active'] || !$ver) {
        throw new RuntimeException('Admin no quedó usable');
    }

    $ok = true;
    $lines[] = "LISTO → /login con {$email} y ADMIN_PASSWORD (len ".strlen($pass).')';
    $lines[] = 'Pon ADMIN_RESET_PASSWORD=false y BORRA este archivo';
} catch (Throwable $e) {
    $lines[] = 'ERROR: ' . $e->getMessage();
}
?>
<pre style="background:#111;color:#eee;padding:1rem;border-radius:8px;white-space:pre-wrap"><?php
echo $ok ? "ÉXITO\n" : "FALLO\n";
foreach ($lines as $l) echo h($l), "\n";
?></pre>
<p style="color:#f5df25;font-family:monospace">Si no viste el título <b>FIX LOGIN v3</b>, el archivo del servidor NO se actualizó.</p>
