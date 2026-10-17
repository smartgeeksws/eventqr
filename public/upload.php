<?php
declare(strict_types=1);
require dirname(__DIR__) . '/private/bootstrap.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
function fail_upload(int $status, string $message): never { http_response_code($status); echo json_encode(['ok'=>false,'message'=>$message], JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail_upload(405, 'Método no permitido.');
$token = (string)($_POST['token'] ?? ''); $event = event_by_token($token);
if (!$event || !(int)$event['activo']) fail_upload(404, 'El evento ya no está disponible.');
if (empty($_FILES['photo']) || is_array($_FILES['photo']['name'])) fail_upload(400, 'Archivo inválido.');
$base = rtrim($config['storage_path'], '/\\');
if (!is_dir($base) && !mkdir($base, 0700, true) && !is_dir($base)) fail_upload(500, 'No se pudo preparar el almacenamiento.');
$limitFile = fopen($base . DIRECTORY_SEPARATOR . '.limit-' . client_ip_key(), 'c+');
if (!$limitFile || !flock($limitFile, LOCK_EX)) fail_upload(500, 'No se pudo comprobar el límite de carga.');
$rawLimits = stream_get_contents($limitFile); $times = $rawLimits ? json_decode($rawLimits, true) : [];
if (!is_array($times)) $times = [];
$times = array_values(array_filter($times, static fn($time) => is_int($time) && $time > time() - 3600));
if (count($times) >= (int)$config['upload_limit_per_hour']) { flock($limitFile, LOCK_UN); fclose($limitFile); fail_upload(429, 'Has alcanzado el límite temporal de cargas.'); }
$times[] = time(); rewind($limitFile); ftruncate($limitFile, 0); fwrite($limitFile, json_encode($times)); fflush($limitFile); flock($limitFile, LOCK_UN); fclose($limitFile);
$file = $_FILES['photo']; if ($file['error'] !== UPLOAD_ERR_OK) fail_upload(400, 'No se pudo recibir el archivo.');
if ($file['size'] < 1 || $file['size'] > min(52428800, (int)$config['max_file_bytes'])) fail_upload(413, 'Cada fotografía debe pesar hasta 50 MB.');
if (!is_uploaded_file($file['tmp_name'])) fail_upload(400, 'Carga inválida.');
$finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($file['tmp_name']);
$allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
if ($mime === 'image/heic' || $mime === 'image/heif' || $mime === 'image/heic-sequence' || $mime === 'image/heif-sequence') {
    if (!function_exists('exif_imagetype')) fail_upload(415, 'El servidor no puede validar este formato HEIC/HEIF.');
    $type = @exif_imagetype($file['tmp_name']);
    if (!in_array($type, [defined('IMAGETYPE_HEIC') ? IMAGETYPE_HEIC : -1], true)) fail_upload(415, 'El servidor no puede validar este formato HEIC/HEIF.');
    $allowed[$mime] = 'heic';
}
if (!isset($allowed[$mime])) fail_upload(415, 'Formato no permitido.');
$folder = $base . DIRECTORY_SEPARATOR . (int)$event['id'];
if (!is_dir($folder) && !mkdir($folder, 0700, true) && !is_dir($folder)) fail_upload(500, 'No se pudo preparar el almacenamiento.');
@chmod($folder, 0700); $internal = bin2hex(random_bytes(32)); $path = $folder . DIRECTORY_SEPARATOR . $internal . '.' . $allowed[$mime];
if (!move_uploaded_file($file['tmp_name'], $path)) fail_upload(500, 'No se pudo guardar la fotografía.');
@chmod($path, 0600);
try { $q = db()->prepare('INSERT INTO fotos (evento_id, nombre_interno, nombre_original, mime, bytes) VALUES (?, ?, ?, ?, ?)'); $q->execute([$event['id'], $internal, basename((string)$file['name']), $mime, (int)$file['size']]); }
catch (Throwable $e) { @unlink($path); fail_upload(500, 'No se pudo registrar la fotografía.'); }
echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
