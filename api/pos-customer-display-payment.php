<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

include __DIR__ . '/../aksi/koneksi.php';
include __DIR__ . '/../aksi/halau.php';
require_once __DIR__ . '/../aksi/functions.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId < 1) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$paymentType = isset($_POST['payment_type']) ? (int) $_POST['payment_type'] : -1;
if ($paymentType !== 0 && $paymentType !== 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'payment_type tidak valid'], JSON_UNESCAPED_UNICODE);
    exit;
}

pos_display_update_payment($userId, $paymentType);

echo json_encode([
    'ok' => true,
    'payment_type' => $paymentType,
    'payment_label' => pos_display_payment_label($paymentType),
], JSON_UNESCAPED_UNICODE);
