<?php
/**
 * Webhook receiver — event dari wa-engine (device connect, pesan masuk, dll.).
 * Menyimpan event ke wa_gateway_webhook_events.
 */
require_once __DIR__ . '/../../aksi/koneksi.php';
require_once __DIR__ . '/../wa-gateway-lib.php';
require_once __DIR__ . '/../wa-auto-blast-lib.php';

wa_gateway_handle_options();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    wa_gateway_json(['status' => false, 'reason' => 'Method not allowed'], 405);
}

$cfg = wa_gateway_load_config();
$secret = (string) ($cfg['webhook_secret'] ?? '');
if ($secret !== '') {
    $incoming = (string) ($_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? $_GET['secret'] ?? '');
    if (!hash_equals($secret, $incoming)) {
        wa_gateway_json(['status' => false, 'reason' => 'Webhook secret invalid'], 403);
    }
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = ['raw' => $raw];
}

$eventType = 'unknown';
if (isset($payload['device'], $payload['status'])) {
    $eventType = 'device_status';
} elseif (isset($payload['message_id'], $payload['state'])) {
    $eventType = 'message_status';
} elseif (isset($payload['type'])) {
    $eventType = (string) $payload['type'];
}

$eventId = 0;
if (isset($conn) && $conn instanceof mysqli) {
    $eventId = wa_gateway_log_webhook($conn, $eventType, $payload);

    $engineEvent = (string) ($payload['event'] ?? '');
    if ($engineEvent === 'device.connect') {
        $minutes = (int) (wa_manual_send_config()['reconnect_cooldown_minutes'] ?? 30);
        wa_auto_blast_set_device_cooldown($conn, $minutes);
    }
}

wa_gateway_json([
    'status' => true,
    'received' => true,
    'event_id' => $eventId,
    'event_type' => $eventType,
]);
