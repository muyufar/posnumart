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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$fromTipe = (int) ($_POST['from_tipe'] ?? -1);
$toTipe = (int) ($_POST['to_tipe'] ?? -1);

if ($fromTipe < 0 || $fromTipe > 2 || $toTipe < 0 || $toTipe > 2) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Tipe customer tidak valid'], JSON_UNESCAPED_UNICODE);
    exit;
}

$resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
$sessionCabang = 0;
if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
    $sessionCabang = (int) ($ru['user_cabang'] ?? 0);
}

$updated = keranjang_update_tipe_harga($userId, $sessionCabang, $fromTipe, $toTipe);

$ctx = beli_langsung_ctx_get($userId);
if ($ctx['customer_id'] !== null && !beli_langsung_customer_valid($conn, (int) $ctx['customer_id'], $toTipe, $sessionCabang)) {
    beli_langsung_ctx_update_customer($userId, 0);
}

echo json_encode([
    'ok' => true,
    'updated' => $updated,
    'to_tipe' => $toTipe,
    'ctx' => beli_langsung_ctx_get($userId),
], JSON_UNESCAPED_UNICODE);
