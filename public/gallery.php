<?php
declare(strict_types=1);
$homeDir = getenv('HOME');
$privateDir = $homeDir ? rtrim($homeDir, '/\\') . DIRECTORY_SEPARATOR . 'eventqr_private' : dirname(__DIR__) . '/eventqr_private';
if (!is_dir($privateDir)) $privateDir = dirname(__DIR__) . '/eventqr_private';
if (!is_dir($privateDir)) $privateDir = dirname(__DIR__) . '/private';
require $privateDir . '/bootstrap.php'; admin_required();
$id = (int)($_GET['id'] ?? 0); $q = db()->prepare('SELECT * FROM eventos WHERE id=?'); $q->execute([$id]); $event = $q->fetch(); if (!$event) { http_response_code(404); exit('Evento no encontrado.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $photoId = (int)($_POST['photo_id'] ?? 0); $q = db()->prepare('SELECT * FROM fotos WHERE id=? AND evento_id=?'); $q->execute([$photoId, $id]); $photo = $q->fetch();
    if ($photo) { $path = rtrim($config['storage_path'], '/\\') . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . $photo['nombre_interno'] . '.' . (['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/heic'=>'heic','image/heif'=>'heic','image/heic-sequence'=>'heic','image/heif-sequence'=>'heic'][$photo['mime']] ?? ''); if (is_file($path)) unlink($path); $d = db()->prepare('DELETE FROM fotos WHERE id=?'); $d->execute([$photoId]); }
    redirect(app_url('gallery.php?id=' . $id));
}
$q = db()->prepare('SELECT * FROM fotos WHERE evento_id=? ORDER BY creado_en DESC'); $q->execute([$id]); $photos = $q->fetchAll();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Galería · EventQR</title><link rel="stylesheet" href="<?= e(app_url('assets/style.css')) ?>"></head><body class="admin"><header class="topbar"><a class="brand" href="<?= e(app_url('events.php')) ?>">Event<span>QR</span></a><a href="<?= e(app_url('events.php')) ?>">Volver a eventos</a></header><main class="container"><h1><?= e($event['nombre']) ?></h1><p class="muted"><?= count($photos) ?> fotografías</p><div class="gallery"><?php foreach ($photos as $photo): ?><article class="photo-card"><img src="<?= e(app_url('photo.php?id=' . (int)$photo['id'])) ?>" alt="Fotografía subida" loading="lazy"><div class="photo-meta"><span><?= e($photo['nombre_original']) ?><br><?= number_format((int)$photo['bytes']/1048576, 1) ?> MB</span><a class="button secondary" href="<?= e(app_url('photo.php?id=' . (int)$photo['id'] . '&download=1')) ?>">Descargar original</a><form method="post" onsubmit="return confirm('¿Eliminar esta fotografía?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="photo_id" value="<?= (int)$photo['id'] ?>"><button class="button danger">Eliminar</button></form></div></article><?php endforeach; ?></div></main></body></html>
