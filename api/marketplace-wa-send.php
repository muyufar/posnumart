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

require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-fonnte-lib.php';

$noJs = __DIR__ . DIRECTORY_SEPARATOR . 'no.js';
$cfg = fonnte_load_no_js($noJs);
$token = $cfg['token'] ?? '';

if ($token === '') {
    echo json_encode([
        'success' => false,
        'message' => fonnte_config_error_hint($noJs),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$target = fonnte_normalize_id_phone($phone);
if ($target === '') {
    echo json_encode(['success' => false, 'message' => 'Nomor HP tidak valid']);
    exit;
}

$built = [['target' => $target, 'message' => $message]];
$result = wa_fonnte_send_built($built, '3');

echo json_encode([
    'success' => (bool) ($result['success'] ?? false),
    'message' => (string) ($result['message'] ?? 'Selesai'),
], JSON_UNESCAPED_UNICODE);
