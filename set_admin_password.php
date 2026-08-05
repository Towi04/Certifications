<?php
declare(strict_types=1);
/**
 * set_admin_password.php — UN SOLO USO
 * Abre: /set_admin_password.php?key=TU_APP_KEY
 * Debe decir SET ADMIN v1. Luego BORRAR.
 */
header('Content-Type: text/html; charset=UTF-8');
echo '<h1>SET ADMIN v1</h1>';

$envFile = null;
foreach ([__DIR__ . '/.env', dirname(__DIR__) . '/.env'] as $p) {
    if (is_file($p)) { $envFile = $p; break; }
}
if (!$envFile) { http_response_code(500); echo 'No .env'; exit; }

$env = [];
$raw = file_get_contents($envFile);
if (str_starts_with($raw, "\xEF\xBB\xBF")) $raw = substr($raw, 3);
foreach (preg_split("/\r\n|\n|\r/", $raw) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $k = trim($k); $v = trim($v);
    if (($v[0] ?? '') === '"' && str_ends_with($v, '"')) $v = substr($v, 1, -1);
    if (($v[0] ?? '') === "'" && str_ends_with($v, "'")) $v = substr($v, 1, -1);
    $env[$k] = $v;
}

$key = (string)($_GET['key'] ?? '');
if (($env['APP_KEY'] ?? '') === '' || !hash_equals($env['APP_KEY'], $key)) {
    http_response_code(403); echo '403 usa ?key=APP_KEY'; exit;
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'] ?? 'localhost', $env['DB_NAME'] ?? ''),
        $env['DB_USER'] ?? '',
        $env['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    echo '<pre>';
    echo 'DB=' . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";

    $cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll();
    echo 'COLUMNS=' . implode(',', array_column($cols, 'Field')) . "\n";

    $rows = $pdo->query('SELECT id, email, role, is_active, LEFT(password_hash,20) AS hp, CHAR_LENGTH(password_hash) AS hl FROM users')->fetchAll();
    echo "BEFORE (" . count($rows) . "):\n";
    foreach ($rows as $r) echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";

    $email = strtolower(trim($env['ADMIN_EMAIL'] ?? 'admin@institutodoceo.com'));
    $username = $env['ADMIN_USERNAME'] ?? null;
    if ($username === null || trim($username) === '') {
        $username = explode('@', $email, 2)[0];
    }
    $username = strtolower(trim($username));

    $pass = (string)($env['ADMIN_PASSWORD'] ?? 'password');
    if ($pass === '') throw new RuntimeException('ADMIN_PASSWORD vacío');

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    if (!password_verify($pass, $hash)) throw new RuntimeException('hash inválido');

    // Borrar cualquier admin roto y recrear limpio
    $pdo->prepare('DELETE FROM users WHERE LOWER(TRIM(email)) = ?')->execute([$email]);
    $pdo->prepare('INSERT INTO users (email, username, password_hash, name, role, is_active) VALUES (?,?,?,?,?,1)')
        ->execute([$email, $username, $hash, 'Administrador', 'admin']);

    $st = $pdo->prepare('SELECT id, email, role, is_active, password_hash FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    $ok = $u && (int)$u['is_active'] === 1 && password_verify($pass, $u['password_hash']);

    echo "AFTER:\n";
    echo json_encode([
        'id' => $u['id'] ?? null,
        'email' => $u['email'] ?? null,
        'role' => $u['role'] ?? null,
        'is_active' => $u['is_active'] ?? null,
        'hash_len' => isset($u['password_hash']) ? strlen($u['password_hash']) : null,
        'hash_start' => isset($u['password_hash']) ? substr($u['password_hash'], 0, 7) : null,
        'password_len_env' => strlen($pass),
        'password_verify' => $ok ? 'OK' : 'FAIL',
    ], JSON_UNESCAPED_UNICODE) . "\n";

    if (!$ok) throw new RuntimeException('verify fail');

    echo "\nLISTO. Pon ADMIN_RESET_PASSWORD=false\n";
    echo "Login: {$email} / (ADMIN_PASSWORD del .env, len " . strlen($pass) . ")\n";
    echo "BORRA set_admin_password.php\n";
    echo '</pre>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<pre>ERROR: ' . htmlspecialchars($e->getMessage()) . '</pre>';
}
