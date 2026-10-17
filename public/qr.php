<?php
declare(strict_types=1);
$homeDir = getenv('HOME');
$privateDir = $homeDir ? rtrim($homeDir, '/\\') . DIRECTORY_SEPARATOR . 'eventqr_private' : dirname(__DIR__) . '/eventqr_private';
if (!is_dir($privateDir)) $privateDir = dirname(__DIR__) . '/eventqr_private';
if (!is_dir($privateDir)) $privateDir = dirname(__DIR__) . '/private';
require $privateDir . '/bootstrap.php'; admin_required();
require $privateDir . '/qr.php';
$token = (string)($_GET['token'] ?? ''); $event = event_by_token($token); if (!$event) { http_response_code(404); exit; }
$url = rtrim($config['public_url'], '/') . '/?e=' . $token;
header('Content-Type: image/svg+xml; charset=utf-8'); header('Content-Disposition: attachment; filename="eventqr-' . (int)$event['id'] . '.svg"');
echo eventqr_svg($url);
