<?php
declare(strict_types=1);
/**
 * debug_login.php — UN SOLO USO. Debe decir DEBUG LOGIN v1.
 * /debug_login.php?key=APP_KEY
 * BORRAR después.
 */
header('Content-Type: text/html; charset=UTF-8');
echo '<h1>DEBUG LOGIN v1</h1><pre>';

$roots = [dirname(__DIR__), __DIR__];
$boot = null;
foreach ($roots as $r) {
    if (is_file($r . '/src/bootstrap.php')) { $boot = $r . '/src/bootstrap.php'; break; }
}
if (!$boot) { echo "No bootstrap\n"; exit; }

require $boot;

use App\Auth\Auth;
use App\Config\Env;
use App\Database\Connection;

$key = (string)($_GET['key'] ?? '');
$appKey = (string)(Env::get('APP_KEY') ?? '');
if ($appKey === '' || $key === '' || !hash_equals($appKey, $key)) {
    http_response_code(403);
    echo "403 — ?key=APP_KEY\n";
    exit;
}

$email = strtolower(trim((string)(Env::get('ADMIN_EMAIL') ?? '')));
$pass = (string)(Env::get('ADMIN_PASSWORD') ?? '');
$reset = Env::get('ADMIN_RESET_PASSWORD');

echo 'bootstrap OK\n';
echo 'ADMIN_EMAIL=[' . $email . "] len=" . strlen($email) . "\n";
echo 'ADMIN_PASSWORD len=' . strlen($pass) . " hex=" . bin2hex($pass) . "\n";
echo 'ADMIN_RESET_PASSWORD=[' . $reset . "] bool=" . (Env::getBool('ADMIN_RESET_PASSWORD', false) ? 'true' : 'false') . "\n";
echo 'DB_NAME=[' . Env::get('DB_NAME') . "]\n";

try {
    $pdo = Connection::get();
    echo 'DATABASE()=[' . $pdo->query('SELECT DATABASE()')->fetchColumn() . "]\n";

    $rows = $pdo->query('SELECT id, email, role, is_active, CHAR_LENGTH(password_hash) hl, LEFT(password_hash,10) hp, HEX(email) email_hex FROM users')->fetchAll();
    echo 'users count=' . count($rows) . "\n";
    foreach ($rows as $r) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        $verify = password_verify($pass, (string)$r['password_hash']);
        echo '  password_verify(ENV vs this row)=' . ($verify ? 'OK' : 'FAIL') . "\n";
    }

    $stmt = $pdo->prepare('SELECT id, email, password_hash, role, name, is_active FROM users WHERE LOWER(TRIM(email)) = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        echo "LOOKUP by ADMIN_EMAIL: NOT FOUND\n";
    } else {
        echo 'LOOKUP found id=' . $user['id'] . ' active=' . $user['is_active'] . ' role=' . $user['role'] . "\n";
        echo 'verify ENV pass: ' . (password_verify($pass, trim((string)$user['password_hash'])) ? 'OK' : 'FAIL') . "\n";
    }

    // Intento real con Auth::attempt (mismo que el formulario)
    $ok = Auth::attempt($email, $pass);
    echo 'Auth::attempt(ENV email, ENV password)=' . ($ok ? 'OK' : 'FAIL') . "\n";
    if ($ok) {
        echo 'SESSION user=' . json_encode(Auth::user(), JSON_UNESCAPED_UNICODE) . "\n";
        echo "LISTO: el backend puede autenticar. El fallo es del formulario/POST/WAF.\n";
        echo "Prueba login manual con la clave exacta del .env (mira hex arriba).\n";
    } else {
        echo "Auth::attempt FALLÓ con las mismas credenciales del .env.\n";
        // Rehash now via same Env values and retry
        if ($user) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash=?, is_active=1, role=? WHERE id=?')
                ->execute([$hash, 'admin', (int)$user['id']]);
            echo 'Rehash aplicado. verify=' . (password_verify($pass, $hash) ? 'OK' : 'FAIL') . "\n";
            $ok2 = Auth::attempt($email, $pass);
            echo 'Auth::attempt after rehash=' . ($ok2 ? 'OK' : 'FAIL') . "\n";
        }
    }
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
}

echo "\nBORRA debug_login.php\n</pre>";
