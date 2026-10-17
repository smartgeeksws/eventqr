<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc !== 3) { fwrite(STDERR, "Uso: php create_admin.php USUARIO CONTRASENA\n"); exit(2); }
[$script, $username, $password] = $argv;
if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $username) || strlen($password) < 12) {
    fwrite(STDERR, "Usuario inválido o contraseña menor de 12 caracteres.\n"); exit(2);
}
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = db()->prepare('INSERT INTO usuarios (usuario, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)');
$stmt->execute([$username, $hash]);
fwrite(STDOUT, "Administrador creado/actualizado: {$username}\n");
