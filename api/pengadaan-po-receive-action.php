<?php
include __DIR__ . '/../aksi/koneksi.php';
include __DIR__ . '/../aksi/halau.php';
require_once __DIR__ . '/../aksi/pengadaan-gudang-lib.php';
require_once __DIR__ . '/../aksi/pengadaan-po-lib.php';
require_once __DIR__ . '/../aksi/functions.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userCabang = 0;
if ($userId > 0) {
    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $userCabang = (int) ($ru['user_cabang'] ?? 0);
    }
}

$levelLogin = (string) ($_SESSION['user_level'] ?? '');
if (!pengadaan_gudang_can_access($userCabang, $levelLogin)) {
    pengadaan_gudang_json_out(['ok' => false, 'message' => 'Akses ditolak']);
}

$action = trim((string) ($_POST['action'] ?? ''));

if ($action === 'scan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $poId = (int) ($_POST['po_id'] ?? 0);
    $barcode = trim((string) ($_POST['barcode'] ?? ''));
    $line = pengadaan_po_scan_line($conn, $poId, $barcode);
    if (!$line) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'Barcode tidak ditemukan di PO ini']);
    }
    $lineId = (int) ($line['id'] ?? 0);
    pengadaan_po_increment_received($conn, $lineId, 1);
    $updated = pengadaan_po_scan_line($conn, $poId, $barcode);
    pengadaan_gudang_json_out([
        'ok' => true,
        'line_id' => $lineId,
        'barang_nama' => (string) ($line['barang_nama'] ?? ''),
        'qty_received' => (float) ($updated['qty_received'] ?? 0),
    ]);
}

if ($action === 'update_line' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $lineId = (int) ($_POST['line_id'] ?? 0);
    $qty = (float) ($_POST['qty_received'] ?? 0);
    $satuan = trim((string) ($_POST['satuan_nama'] ?? 'PCS'));
    $harga = (float) ($_POST['harga'] ?? 0);
    if ($lineId < 1) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'Line tidak valid']);
    }
    $ok = pengadaan_po_update_line($conn, $lineId, $qty, $satuan, $harga);
    pengadaan_gudang_json_out(['ok' => $ok, 'message' => $ok ? 'Disimpan' : 'Gagal']);
}

if ($action === 'prepare_invoice' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $poId = (int) ($_POST['po_id'] ?? 0);
    $lines = $_POST['lines'] ?? [];
    if (is_array($lines)) {
        foreach ($lines as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $lineId = (int) ($ln['line_id'] ?? 0);
            if ($lineId < 1) {
                continue;
            }
            pengadaan_po_update_line(
                $conn,
                $lineId,
                (float) ($ln['qty_received'] ?? 0),
                (string) ($ln['satuan_nama'] ?? 'PCS'),
                (float) ($ln['harga'] ?? 0)
            );
        }
    }
    $result = pengadaan_po_prepare_invoice_cart($conn, $poId, $userId, 0);
    pengadaan_gudang_json_out($result);
}

// Tambah baris manual ke PO (menggunakan barang_kode sebagai input)
if ($action === 'add_line' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $poId = (int) ($_POST['po_id'] ?? 0);
    $barangKode = trim((string) ($_POST['barang_kode'] ?? ''));
    $qtyPo = (float) ($_POST['qty_po'] ?? 0);
    $satuan = trim((string) ($_POST['satuan_nama'] ?? ''));
    $cabangId = (int) ($_POST['cabang_id'] ?? 0);

    if ($barangKode === '' || $qtyPo <= 0 || $poId < 1) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'Data tidak lengkap']);
    }

    // Resolve barang_id dari kode
    $barangId = pengadaan_po_gudang_barang_id($conn, $barangKode, 0);
    if ($barangId < 1) {
        // coba cari dari barang cabang lain
        // (fallback) search by kode in any branch
        $res = mysqli_query($conn, "SELECT barang_id FROM barang WHERE barang_kode = '" . mysqli_real_escape_string($conn, $barangKode) . "' LIMIT 1");
        if ($res && ($r = mysqli_fetch_assoc($res))) {
            $barangId = (int) ($r['barang_id'] ?? 0);
        }
    }
    if ($barangId < 1) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'Barang tidak ditemukan']);
    }

    $ret = pengadaan_po_add_line_manual($conn, $poId, $barangId, $qtyPo, $satuan, $cabangId);
    pengadaan_gudang_json_out($ret);
}

// Hapus baris dari PO
if ($action === 'delete_line' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $poId = (int) ($_POST['po_id'] ?? 0);
    $lineId = (int) ($_POST['line_id'] ?? 0);
    if ($poId < 1 || $lineId < 1) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'Data tidak valid']);
    }
    $res = pengadaan_po_delete_line($conn, $poId, $lineId);
    pengadaan_gudang_json_out($res);
}

pengadaan_gudang_json_out(['ok' => false, 'message' => 'Aksi tidak dikenal']);
