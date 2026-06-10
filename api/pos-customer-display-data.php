<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

include __DIR__ . '/../aksi/koneksi.php';
include __DIR__ . '/../aksi/halau.php';
require_once __DIR__ . '/../aksi/functions.php';

mysqli_set_charset($conn, 'utf8mb4');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userNama = (string) ($_SESSION['user_nama'] ?? 'Kasir');

$displayState = pos_display_state($userId);
$tipeHarga = (int) $displayState['tipe_customer'];
$userCabang = (int) $displayState['cabang'];

if ($userCabang < 1) {
    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $userCabang = (int) ($ru['user_cabang'] ?? 0);
    }
}

$nameTipeHarga = pos_display_tipe_label($tipeHarga);

$tokoNama = 'NUMART';
if ($userCabang > 0) {
    $tokoRows = query("SELECT toko_nama FROM toko WHERE toko_cabang = $userCabang LIMIT 1");
    if (!empty($tokoRows[0]['toko_nama'])) {
        $tokoNama = $tokoRows[0]['toko_nama'];
    }
}

$keranjang = query(
    "SELECT * FROM keranjang
     WHERE keranjang_id_kasir = $userId
       AND keranjang_tipe_customer = $tipeHarga
       AND keranjang_cabang = $userCabang
     ORDER BY keranjang_id DESC"
);

$items = [];
$total = 0;
$i = 1;

foreach ($keranjang as $row) {
    if ((int) $row['keranjang_id_kasir'] !== $userId) {
        continue;
    }

    $qtyView = $row['keranjang_qty_view'];
    if ($qtyView === null || $qtyView === '') {
        $qtyView = $row['keranjang_qty'];
    }
    $qtyView = (float) $qtyView;
    $harga = (float) $row['keranjang_harga'];
    $subtotal = (int) round($harga * $qtyView);
    $total += $subtotal;

    $satuanNama = '-';
    if (function_exists('satuan_nama_by_id')) {
        $satuanNama = satuan_nama_by_id($conn, (int) $row['keranjang_satuan']) ?: '-';
    }

    $items[] = [
        'no' => $i,
        'nama' => (string) $row['keranjang_nama'],
        'qty' => $qtyView,
        'qty_label' => rtrim(rtrim(number_format($qtyView, 2, ',', '.'), '0'), ','),
        'satuan' => $satuanNama,
        'harga' => (int) round($harga),
        'subtotal' => $subtotal,
    ];
    $i++;
}

$paymentType = (int) ($displayState['payment_type'] ?? 0);
$qrisUrl = '';
if ($paymentType === 1) {
    $qrisUrl = pos_display_qris_url($conn, $userCabang);
}

echo json_encode([
    'ok' => true,
    'toko_nama' => $tokoNama,
    'kasir_nama' => $userNama,
    'tipe_customer' => $nameTipeHarga,
    'tipe_customer_id' => $tipeHarga,
    'payment_type' => $paymentType,
    'payment_label' => pos_display_payment_label($paymentType),
    'show_qris' => $paymentType === 1,
    'qris_url' => $qrisUrl !== '' ? $qrisUrl : null,
    'items' => $items,
    'item_count' => count($items),
    'total' => $total,
    'revision' => (int) $displayState['revision'],
    'event' => (string) $displayState['event'],
    'updated_at' => date('c'),
], JSON_UNESCAPED_UNICODE);