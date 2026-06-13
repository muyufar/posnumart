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

echo json_encode([
    'success' => true,
    'provider' => 'local',
    'provider_label' => wa_provider_label(),
    'configured' => wa_provider_configured(),
    'max_per_request' => wa_max_recipients_per_request(),
    'local' => [
        'engine_online' => wa_local_engine_online(),
        'base_url' => wa_local_base_url(),
        'secret_set' => wa_local_api_secret() !== '',
        'device_name' => (string) (wa_local_config()['device_name'] ?? 'NUMART'),
    ],
], JSON_UNESCAPED_UNICODE);
