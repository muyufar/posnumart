<?php
/**
 * Proxy admin untuk status engine WA lokal (tanpa mengekspos api_secret ke browser).
 */
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-send-lib.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$level = strtolower(trim((string) ($_SESSION['user_level'] ?? $_SESSION['level'] ?? '')));
if ($level !== 'super admin' && $level !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$action = trim((string) ($_GET['action'] ?? 'status'));

if ($action === 'qr') {
    $qr = wa_local_qr_status();
    echo json_encode([
        'success' => !empty($qr['success']),
        'provider' => 'local',
        'data' => $qr['parsed'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'logout') {
    $res = wa_local_logout();
    echo json_encode([
        'success' => !empty($res['ok']),
        'parsed' => $res['parsed'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$device = wa_local_device_profile();
$local = wa_local_config();

echo json_encode([
    'success' => true,
    'provider' => wa_get_provider(),
    'provider_label' => wa_provider_label(),
    'configured' => wa_provider_configured(),
    'engine_online' => wa_local_engine_online(),
    'hint' => wa_local_config_error_hint(),
    'local' => [
        'base_url' => wa_local_base_url(),
        'device_name' => (string) ($local['device_name'] ?? 'NUMART'),
        'secret_set' => wa_local_api_secret() !== '',
    ],
    'device' => $device['parsed'] ?? null,
], JSON_UNESCAPED_UNICODE);
