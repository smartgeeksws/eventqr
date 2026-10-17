<?php
declare(strict_types=1);
require dirname(__DIR__) . '/private/bootstrap.php'; admin_required();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $action = (string)($_POST['action'] ?? '');
    if ($action === 'create') {
        $name = trim((string)($_POST['nombre'] ?? '')); $date = (string)($_POST['fecha'] ?? '');
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        if ($name === '' || mb_strlen($name) > 160 || !$parsed || $parsed->format('Y-m-d') !== $date) $error = 'Indica un nombre y una fecha válidos.';
        else { $q = db()->prepare('INSERT INTO eventos (nombre, fecha, token) VALUES (?, ?, ?)'); $q->execute([$name, $date, bin2hex(random_bytes(24))]); redirect('/events.php'); }
    } elseif ($action === 'toggle') {
        $q = db()->prepare('UPDATE eventos SET activo = IF(activo = 1, 0, 1) WHERE id = ?'); $q->execute([(int)($_POST['id'] ?? 0)]); redirect('/events.php');
    } elseif ($action === 'logout') { session_destroy(); redirect('/admin.php'); }
}
$events = db()->query('SELECT e.*, (SELECT COUNT(*) FROM fotos f WHERE f.evento_id=e.id) AS total_fotos FROM eventos e ORDER BY e.creado_en DESC')->fetchAll();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Eventos · EventQR</title><link rel="stylesheet" href="/assets/style.css"></head><body class="admin"><header class="topbar"><a class="brand" href="/events.php">Event<span>QR</span></a><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="link-button">Cerrar sesión</button></form></header><main class="container"><section class="card"><h1>Crear evento</h1><?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?><form class="create-form" method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><label>Nombre del evento<input name="nombre" maxlength="160" required></label><label>Fecha<input type="date" name="fecha" required></label><button class="button primary">Crear evento</button></form></section><h2>Eventos</h2><?php if (!$events): ?><p class="muted">Aún no hay eventos.</p><?php endif; ?><div class="event-list"><?php foreach ($events as $item): $link = rtrim($config['public_url'], '/') . '/?e=' . $item['token']; ?><article class="card event-card"><div class="event-info"><h3><?= e($item['nombre']) ?></h3><p><?= e(date('d/m/Y', strtotime($item['fecha']))) ?> · <?= (int)$item['total_fotos'] ?> fotografías · <?= $item['activo'] ? 'Activo' : 'Cerrado' ?></p><a href="/gallery.php?id=<?= (int)$item['id'] ?>">Ver galería</a></div><div class="event-actions"><a class="button secondary" href="/qr.php?token=<?= e($item['token']) ?>" download="eventqr-<?= (int)$item['id'] ?>.svg">Descargar QR</a><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="button secondary"><?= $item['activo'] ? 'Cerrar' : 'Activar' ?></button></form><input class="event-link" readonly value="<?= e($link) ?>" aria-label="Enlace público"></div></article><?php endforeach; ?></div></main></body></html>
