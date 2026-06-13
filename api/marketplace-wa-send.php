<?php
/**
 * Kirim WA untuk aktivasi Belanja Online (tanpa session login POS).
 * Set secret di marketplace-config.php: marketplace_wa_secret
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$configPath = __DIR__ . '/../aksi/marketplace-config.php';
$secret = '';
if (is_file($configPath)) {
    require $configPath;
    $secret = (string) ($marketplace_wa_secret ?? '');
}

$incoming = $_SERVER['HTTP_X_MARKETPLACE_SECRET'] ?? '';
if ($secret === '' || !hash_equals($secret, (string) $incoming)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$phone = isset($input['phone']) ? (string) $input['phone'] : '';
$message = isset($input['message']) ? (string) $input['message'] : '';

if ($phone === '' || $message === '') {
    echo json_encode(['success' => false, 'message' => 'phone dan message wajib']);
    exit;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-send-lib.php';

if (!wa_provider_configured()) {
    echo json_encode([
        'success' => false,
        'message' => wa_local_config_error_hint(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$target = wa_normalize_id_phone($phone);
if ($target === '') {
    echo json_encode(['success' => false, 'message' => 'Nomor HP tidak valid']);
    exit;
}

$result = wa_send_built([['target' => $target, 'message' => $message]], '3');

echo json_encode([
    'success' => (bool) ($result['success'] ?? false),
    'message' => (string) ($result['message'] ?? 'Selesai'),
], JSON_UNESCAPED_UNICODE);
