<?php
declare(strict_types=1);
$homeDir = getenv('HOME');
$privateDir = $homeDir ? rtrim($homeDir, '/\\') . DIRECTORY_SEPARATOR . 'eventqr_private' : dirname(__DIR__) . '/eventqr_private';
if (!is_dir($privateDir)) $privateDir = dirname(__DIR__) . '/eventqr_private';
if (!is_dir($privateDir)) $privateDir = dirname(__DIR__) . '/private';
require $privateDir . '/bootstrap.php'; admin_required();
$q = db()->prepare('SELECT f.*, e.id AS eid FROM fotos f JOIN eventos e ON e.id=f.evento_id WHERE f.id=?'); $q->execute([(int)($_GET['id'] ?? 0)]); $photo = $q->fetch();
if (!$photo) { http_response_code(404); exit; }
$ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/heic'=>'heic','image/heif'=>'heic','image/heic-sequence'=>'heic','image/heif-sequence'=>'heic'][$photo['mime']] ?? null;
if (!$ext) { http_response_code(415); exit; }
$folder = rtrim($config['storage_path'], '/\\') . DIRECTORY_SEPARATOR . (int)$photo['eid'];
$path = $folder . DIRECTORY_SEPARATOR . $photo['nombre_interno'] . '.' . $ext;
$isPreview = isset($_GET['preview']) && !isset($_GET['download']);
if ($isPreview && is_file($folder . DIRECTORY_SEPARATOR . $photo['nombre_interno'] . '.preview.jpg')) {
    $path = $folder . DIRECTORY_SEPARATOR . $photo['nombre_interno'] . '.preview.jpg';
    $photo['mime'] = 'image/jpeg';
}
if (!is_file($path)) { http_response_code(404); exit; }
header('Content-Type: ' . $photo['mime']); header('Content-Length: ' . filesize($path)); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store');
if (isset($_GET['download'])) header('Content-Disposition: attachment; filename="' . rawurlencode($photo['nombre_original']) . '"'); else header('Content-Disposition: inline');
readfile($path);
