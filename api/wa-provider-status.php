<?php
/**
 * Status provider WA (tanpa mengekspos token).
 */
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-send-lib.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$provider = wa_get_provider();
$cfg = wa_load_app_config();
$official = $cfg['official'] ?? [];

echo json_encode([
    'success' => true,
    'provider' => $provider,
    'provider_label' => wa_provider_label(),
    'configured' => wa_provider_configured(),
    'max_per_request' => wa_max_recipients_per_request(),
    'official' => [
        'send_mode' => (string) ($official['send_mode'] ?? 'template'),
        'template_name' => (string) (($official['template']['name'] ?? '')),
        'template_language' => (string) (($official['template']['language'] ?? 'id')),
        'phone_number_id_set' => trim((string) ($official['phone_number_id'] ?? '')) !== '',
    ],
], JSON_UNESCAPED_UNICODE);
