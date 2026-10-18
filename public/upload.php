<?php
declare(strict_types=1);
$homeDir = getenv('HOME');
$privateDir = $homeDir ? rtrim($homeDir, '/\\') . DIRECTORY_SEPARATOR . 'eventqr_private' : dirname(__DIR__) . '/eventqr_private';
if (!is_dir($privateDir)) $privateDir = dirname(__DIR__) . '/eventqr_private';
if (!is_dir($privateDir)) $privateDir = dirname(__DIR__) . '/private';
require $privateDir . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
function fail_upload(int $status, string $message): never { http_response_code($status); echo json_encode(['ok'=>false,'message'=>$message], JSON_UNESCAPED_UNICODE); exit; }
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST) && empty($_FILES)) {
    fail_upload(413, 'El servidor rechazó la carga por su límite post_max_size. Súbelo en la configuración PHP de cPanel.');
}
function make_preview(string $sourcePath, string $previewPath, string $mime): bool
{
    if (!extension_loaded('gd')) return false;
    $loaders = ['image/jpeg'=>'imagecreatefromjpeg', 'image/png'=>'imagecreatefrompng', 'image/webp'=>'imagecreatefromwebp'];
    $loader = $loaders[$mime] ?? null;
    if (!$loader || !function_exists($loader) || !function_exists('imagejpeg')) return false;
    $details = @getimagesize($sourcePath);
    if (!$details || $details[0] < 1 || $details[1] < 1 || $details[0] * $details[1] > 24000000) return false;
    $memoryLimit = trim((string)ini_get('memory_limit'));
    if ($memoryLimit !== '' && $memoryLimit !== '-1' && preg_match('/^(\d+)\s*([KMG])?$/i', $memoryLimit, $matches)) {
        $limitBytes = (int)$matches[1] * match (strtoupper($matches[2] ?? '')) { 'G'=>1073741824, 'M'=>1048576, 'K'=>1024, default=>1 };
        if (memory_get_usage(true) + ($details[0] * $details[1] * 8) > $limitBytes * 0.7) return false;
    }
    $source = @$loader($sourcePath);
    if (!$source) return false;
    $scale = min(1, 1440 / max($details[0], $details[1]));
    $width = max(1, (int)round($details[0] * $scale)); $height = max(1, (int)round($details[1] * $scale));
    $preview = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($preview, 255, 255, 255); imagefill($preview, 0, 0, $white);
    $resized = imagecopyresampled($preview, $source, 0, 0, 0, 0, $width, $height, $details[0], $details[1]);
    $saved = $resized && @imagejpeg($preview, $previewPath, 76);
    imagedestroy($preview); imagedestroy($source);
    if (!$saved) @unlink($previewPath);
    return $saved;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail_upload(405, 'Método no permitido.');
$token = (string)($_POST['token'] ?? ''); $event = event_by_token($token);
if (!$event || !(int)$event['activo']) fail_upload(404, 'El evento ya no está disponible.');
$uploader = trim((string)($_POST['uploader'] ?? ''));
if ($uploader === '' || (function_exists('mb_strlen') ? mb_strlen($uploader) : strlen($uploader)) > 120) fail_upload(422, 'Indica quién está enviando las fotografías.');
if (empty($_FILES['photo']) || is_array($_FILES['photo']['name'])) fail_upload(400, 'Archivo inválido.');
$base = rtrim($config['storage_path'], '/\\');
if (!is_dir($base) && !mkdir($base, 0700, true) && !is_dir($base)) fail_upload(500, 'No se pudo crear la carpeta de fotos. Revisa storage_path y los permisos en cPanel.');
$limitFile = fopen($base . DIRECTORY_SEPARATOR . '.limit-' . client_ip_key(), 'c+');
if (!$limitFile || !flock($limitFile, LOCK_EX)) fail_upload(500, 'No se pudo comprobar el límite de carga.');
$rawLimits = stream_get_contents($limitFile); $times = $rawLimits ? json_decode($rawLimits, true) : [];
if (!is_array($times)) $times = [];
$times = array_values(array_filter($times, static fn($time) => is_int($time) && $time > time() - 3600));
if (count($times) >= (int)$config['upload_limit_per_hour']) { flock($limitFile, LOCK_UN); fclose($limitFile); fail_upload(429, 'Has alcanzado el límite temporal de cargas.'); }
$times[] = time(); rewind($limitFile); ftruncate($limitFile, 0); fwrite($limitFile, json_encode($times)); fflush($limitFile); flock($limitFile, LOCK_UN); fclose($limitFile);
$file = $_FILES['photo'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'El servidor rechazó la foto por upload_max_filesize. Configura el límite en cPanel por encima de 50 MB.',
        UPLOAD_ERR_FORM_SIZE => 'La foto supera el límite permitido por el formulario.',
        UPLOAD_ERR_PARTIAL => 'La conexión se interrumpió mientras subía la foto. Inténtalo otra vez con una conexión estable.',
        UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal de carga configurada.',
        UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo temporal. Contacta al hosting.',
        UPLOAD_ERR_EXTENSION => 'Una extensión PHP detuvo la carga. Contacta al hosting.',
    ];
    fail_upload(400, $uploadErrors[$file['error']] ?? 'No se pudo recibir el archivo.');
}
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
if (!is_dir($folder) && !mkdir($folder, 0700, true) && !is_dir($folder)) fail_upload(500, 'No se pudo crear la carpeta del evento. Revisa los permisos de eventqr_fotos.');
@chmod($folder, 0700); $internal = bin2hex(random_bytes(32)); $path = $folder . DIRECTORY_SEPARATOR . $internal . '.' . $allowed[$mime];
if (!move_uploaded_file($file['tmp_name'], $path)) fail_upload(500, 'No se pudo guardar la fotografía. Revisa que PHP pueda escribir en eventqr_fotos.');
@chmod($path, 0600);
$previewPath = $folder . DIRECTORY_SEPARATOR . $internal . '.preview.jpg';
if (make_preview($path, $previewPath, $mime)) @chmod($previewPath, 0600);
try { $q = db()->prepare('INSERT INTO fotos (evento_id, subido_por, nombre_interno, nombre_original, mime, bytes) VALUES (?, ?, ?, ?, ?, ?)'); $q->execute([$event['id'], $uploader, $internal, basename((string)$file['name']), $mime, (int)$file['size']]); }
catch (Throwable $e) { error_log('EventQR photo insert failed: ' . $e->getMessage()); @unlink($path); @unlink($previewPath); fail_upload(500, 'No se pudo registrar la foto en la base de datos. Revisa la migración y la conexión MySQL.'); }
echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
