<?php
declare(strict_types=1);
require dirname(__DIR__) . '/private/bootstrap.php'; admin_required();
require dirname(__DIR__) . '/private/qr.php';
$token = (string)($_GET['token'] ?? ''); $event = event_by_token($token); if (!$event) { http_response_code(404); exit; }
$url = rtrim($config['public_url'], '/') . '/?e=' . $token;
header('Content-Type: image/svg+xml; charset=utf-8'); header('Content-Disposition: attachment; filename="eventqr-' . (int)$event['id'] . '.svg"');
echo eventqr_svg($url);
