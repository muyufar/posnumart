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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'ok' => true,
        'ctx' => beli_langsung_ctx_get($userId),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = (string) ($_POST['action'] ?? 'save');

if ($action === 'clear') {
    beli_langsung_ctx_clear($userId);
    echo json_encode(['ok' => true, 'ctx' => beli_langsung_ctx_get($userId)], JSON_UNESCAPED_UNICODE);
    exit;
}

$hasCustomer = array_key_exists('customer_id', $_POST);
$hasPayment = array_key_exists('payment_type', $_POST);

if (!$hasCustomer && !$hasPayment) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Data tidak lengkap'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ctx = beli_langsung_ctx_get($userId);
$customerId = $hasCustomer ? (int) $_POST['customer_id'] : ($ctx['customer_id'] !== null ? (int) $ctx['customer_id'] : 0);
$paymentType = $hasPayment ? (int) $_POST['payment_type'] : ($ctx['payment_type'] !== null ? (int) $ctx['payment_type'] : 0);

if ($hasCustomer && !$hasPayment) {
    beli_langsung_ctx_update_customer($userId, $customerId);
} elseif ($hasPayment && !$hasCustomer) {
    beli_langsung_ctx_update_payment($userId, $paymentType);
} else {
    beli_langsung_ctx_save($userId, $customerId, $paymentType, true);
}

echo json_encode([
    'ok' => true,
    'ctx' => beli_langsung_ctx_get($userId),
], JSON_UNESCAPED_UNICODE);
