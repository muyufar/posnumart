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

pos_display_update_totals($userId, [
    'sub_total' => (int) ($_POST['sub_total'] ?? 0),
    'ongkir' => (int) ($_POST['ongkir'] ?? 0),
    'diskon' => (int) ($_POST['diskon'] ?? 0),
    'bayar' => (int) ($_POST['bayar'] ?? 0),
    'kembali' => (int) ($_POST['kembali'] ?? 0),
]);

$totals = pos_display_totals($userId);

echo json_encode([
    'ok' => true,
    'total_bayar' => $totals['sub_total'],
    'bayar' => $totals['bayar'],
    'kembali' => $totals['kembali'],
    'ongkir' => $totals['ongkir'],
    'diskon' => $totals['diskon'],
], JSON_UNESCAPED_UNICODE);
