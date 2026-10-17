<?php
declare(strict_types=1);
$homeDir = getenv('HOME');
$privateDir = $homeDir ? rtrim($homeDir, '/\\') . DIRECTORY_SEPARATOR . 'eventqr_private' : dirname(__DIR__) . '/eventqr_private';
if (!is_dir($privateDir)) $privateDir = dirname(__DIR__) . '/eventqr_private';
if (!is_dir($privateDir)) $privateDir = dirname(__DIR__) . '/private';
require $privateDir . '/bootstrap.php';
if (!empty($_SESSION['admin_id'])) redirect('/events.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $stmt = db()->prepare('SELECT id, password_hash FROM usuarios WHERE usuario = ? LIMIT 1'); $stmt->execute([trim((string)($_POST['usuario'] ?? ''))]); $user = $stmt->fetch();
    if ($user && password_verify((string)($_POST['password'] ?? ''), $user['password_hash'])) {
        session_regenerate_id(true); $_SESSION['admin_id'] = (int)$user['id']; $_SESSION['csrf'] = bin2hex(random_bytes(32)); redirect('/events.php');
    }
    usleep(350000); $error = 'Usuario o contraseña incorrectos.';
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Administración · EventQR</title><link rel="stylesheet" href="/assets/style.css"></head><body class="admin"><main class="card login-card"><div class="brand">Event<span>QR</span></div><h1>Administración</h1><?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><label>Usuario<input name="usuario" required autocomplete="username" maxlength="80"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button class="button primary">Iniciar sesión</button></form></main></body></html>
