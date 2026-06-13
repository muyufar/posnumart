<?php
/**
 * Kirim pesan WhatsApp — API Gateway (mirip Fonnte /send).
 *
 * POST JSON atau form:
 *   target, message, url (opsional), filename, delay, connectOnly
 */
require_once __DIR__ . '/../../aksi/koneksi.php';
require_once __DIR__ . '/../wa-gateway-lib.php';

wa_gateway_handle_options();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    wa_gateway_json(['status' => false, 'reason' => 'Method not allowed — gunakan POST'], 405);
}

$apiKey = wa_gateway_require_auth();
$input = wa_gateway_read_input();

$result = wa_gateway_send($input, $apiKey);

$messageType = !empty($input['url']) || !empty($input['image']) ? 'image' : 'text';
$logId = 0;
if (isset($conn) && $conn instanceof mysqli) {
    $parsed = $result['parsed'] ?? null;
    $msgId = '';
    if (is_array($parsed)) {
        if (!empty($parsed['id'])) {
            $msgId = is_array($parsed['id']) ? (string) ($parsed['id'][0] ?? '') : (string) $parsed['id'];
        }
    }
    $logId = wa_gateway_log_message($conn, [
        'api_key_name' => (string) ($apiKey['name'] ?? ''),
        'target' => (string) ($result['target'] ?? ($input['target'] ?? '')),
        'message_type' => $messageType,
        'message_preview' => (string) ($input['message'] ?? ''),
        'media_url' => (string) ($input['url'] ?? $input['image'] ?? ''),
        'provider' => (string) ($result['provider'] ?? 'local'),
        'provider_status' => !empty($result['success']),
        'provider_response' => (string) ($result['http_raw'] ?? json_encode($parsed, JSON_UNESCAPED_UNICODE)),
        'provider_message_id' => $msgId,
    ]);
}

$out = wa_gateway_format_send_response($result, $logId);
wa_gateway_json($out, !empty($out['status']) ? 200 : 422);
