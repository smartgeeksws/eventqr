<?php
declare(strict_types=1);
$homeDir = getenv('HOME');
$privateDir = $homeDir ? rtrim($homeDir, '/\\') . DIRECTORY_SEPARATOR . 'eventqr_private' : dirname(__DIR__) . '/eventqr_private';
if (!is_dir($privateDir)) $privateDir = dirname(__DIR__) . '/eventqr_private';
if (!is_dir($privateDir)) $privateDir = dirname(__DIR__) . '/private';
require $privateDir . '/bootstrap.php'; admin_required();

if (!class_exists(ZipArchive::class)) { http_response_code(500); exit('La extensión ZIP de PHP no está habilitada en el servidor.'); }
$eventId = (int)($_GET['event_id'] ?? 0);
$eventQuery = db()->prepare('SELECT id FROM eventos WHERE id=?'); $eventQuery->execute([$eventId]);
if (!$eventQuery->fetch()) { http_response_code(404); exit('Evento no encontrado.'); }
$query = db()->prepare('SELECT nombre_interno, nombre_original, mime FROM fotos WHERE evento_id=? ORDER BY creado_en, id');
$query->execute([$eventId]); $photos = $query->fetchAll();
$extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/heic'=>'heic','image/heif'=>'heic','image/heic-sequence'=>'heic','image/heif-sequence'=>'heic'];
$temporaryZip = tempnam(sys_get_temp_dir(), 'eventqr-');
if ($temporaryZip === false) { http_response_code(500); exit('No se pudo preparar la descarga.'); }
$zip = new ZipArchive();
if ($zip->open($temporaryZip, ZipArchive::OVERWRITE) !== true) { @unlink($temporaryZip); http_response_code(500); exit('No se pudo crear el archivo ZIP.'); }
$usedNames = []; $added = 0; $failed = false; $storage = rtrim($config['storage_path'], '/\\') . DIRECTORY_SEPARATOR . $eventId;
foreach ($photos as $photo) {
    $extension = $extensions[$photo['mime']] ?? null;
    if (!$extension || !preg_match('/^[a-f0-9]{64}$/', $photo['nombre_interno'])) { $failed = true; break; }
    $path = $storage . DIRECTORY_SEPARATOR . $photo['nombre_interno'] . '.' . $extension;
    if (!is_file($path)) { $failed = true; break; }
    $name = basename(str_replace('\\', '/', (string)$photo['nombre_original']));
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?: '';
    if ($name === '' || $name === '.' || $name === '..') $name = 'foto-' . ($added + 1) . '.' . $extension;
    $entryName = $name;
    if (isset($usedNames[strtolower($entryName)])) {
        $suffix = 2;
        do { $entryName = pathinfo($name, PATHINFO_FILENAME) . '-' . $suffix++ . '.' . $extension; } while (isset($usedNames[strtolower($entryName)]));
    }
    if (!$zip->addFile($path, $entryName)) { $failed = true; break; }
    $usedNames[strtolower($entryName)] = true; $added++;
}
if ($failed) { $zip->close(); @unlink($temporaryZip); http_response_code(500); exit('No se pudo incluir una de las fotografías en el ZIP.'); }
if (!$zip->close()) { @unlink($temporaryZip); http_response_code(500); exit('No se pudo completar el archivo ZIP.'); }
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="eventqr-evento-' . $eventId . '.zip"');
header('Content-Length: ' . filesize($temporaryZip));
header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store');
readfile($temporaryZip); @unlink($temporaryZip);
