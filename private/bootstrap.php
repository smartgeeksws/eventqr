<?php
declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Falta configurar EventQR.');
}
$config = require $configFile;

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_start();

function db(): PDO
{
    static $pdo;
    global $config;
    if (!$pdo) {
        $db = $config['db'];
        $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}", $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function app_base_path(): string
{
    $path = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
    return $path === '/' || $path === '.' ? '' : rtrim($path, '/');
}
function app_url(string $path = ''): string { return app_base_path() . '/' . ltrim($path, '/'); }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419); exit('Solicitud expirada. Recarga la página.');
    }
}
function admin_required(): void
{
    if (empty($_SESSION['admin_id'])) { header('Location: ' . app_url('admin.php')); exit; }
}
function redirect(string $url): never { header('Location: ' . $url); exit; }
function event_by_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) return null;
    $q = db()->prepare('SELECT id, nombre, fecha, activo FROM eventos WHERE token = ? LIMIT 1');
    $q->execute([$token]); return $q->fetch() ?: null;
}
function client_ip_key(): string { return hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')); }
