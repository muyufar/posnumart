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

$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));

if ($action === 'sync') {
    $analisisHari = (int) ($_POST['analisis_hari'] ?? $_GET['analisis_hari'] ?? 30);
    $targetCover = (int) ($_POST['target_cover'] ?? $_GET['target_cover'] ?? 14);
    $stats = pengadaan_gudang_sync($conn, $analisisHari, $targetCover);
    pengadaan_gudang_json_out(['ok' => true, 'message' => 'Scan selesai', 'stats' => $stats]);
}

if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $idsRaw = trim((string) ($_POST['ids'] ?? ''));
    $ids = [];
    if ($idsRaw !== '') {
        foreach (preg_split('/\s*,\s*/', $idsRaw) as $part) {
            $n = (int) $part;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }
    }
    if ($id > 0) {
        $ids[$id] = $id;
    }
    $ids = array_values($ids);
    $status = trim((string) ($_POST['status'] ?? ''));
    $catatan = trim((string) ($_POST['catatan'] ?? ''));
    $allowed = ['diproses', 'selesai', 'ditolak'];
    if ($ids === [] || !in_array($status, $allowed, true)) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'Data tidak valid']);
    }
    $catatanEsc = mysqli_real_escape_string($conn, $catatan);
    $setCatatan = $catatan !== '' ? ", catatan = '$catatanEsc'" : '';
    $setProses = $status === 'diproses' ? ", diproses_by = $userId, diproses_at = NOW()" : '';
    $idsStr = implode(',', $ids);
    $ok = mysqli_query($conn, "
        UPDATE pengadaan_request SET status = '$status', updated_at = NOW() $setCatatan $setProses
        WHERE id IN ($idsStr)
    ");
    pengadaan_gudang_json_out([
        'ok' => (bool) $ok,
        'message' => $ok ? 'Status permintaan diperbarui' : ('Gagal: ' . mysqli_error($conn)),
    ]);
}

if ($action === 'create_po' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    // Checkbox akumulasi bisa berisi "12,45,78"
    $flat = [];
    foreach ($ids as $raw) {
        foreach (preg_split('/\s*,\s*/', (string) $raw) as $part) {
            $n = (int) $part;
            if ($n > 0) {
                $flat[$n] = $n;
            }
        }
    }
    $ids = array_values($flat);
    if ($ids === []) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'Pilih minimal 1 barang untuk PO']);
    }

    // Langsung buat PO (skip cek WA berat — kirim WA belakangan dari list PO Aktif)
    @set_time_limit(120);
    $result = pengadaan_po_create_from_requests($conn, $ids, $userId, 0);
    $ok = $result['created'] > 0;
    pengadaan_gudang_json_out([
        'ok' => $ok,
        'message' => $ok
            ? ('Berhasil buat ' . $result['created'] . ' PO')
            : (implode('; ', $result['errors']) ?: 'Gagal buat PO'),
        'created' => $result['created'],
        'po_ids' => $result['po_ids'],
        'errors' => $result['errors'],
        'missing_wa' => [],
        'need_wa' => false,
    ]);
}

if ($action === 'create_po_by_supplier' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $kodeSuplier = trim((string) ($_POST['kode_suplier'] ?? ''));
    if ($kodeSuplier === '') {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'Kode supplier kosong']);
    }
    $ksEsc = mysqli_real_escape_string($conn, $kodeSuplier);
    $res = mysqli_query($conn, "
        SELECT id FROM pengadaan_request
        WHERE kode_suplier = '$ksEsc' AND status IN ('pending','diproses')
          AND (po_id IS NULL OR po_id = 0)
    ");
    $ids = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $ids[] = (int) $r['id'];
        }
    }
    if ($ids === []) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'Tidak ada permintaan aktif untuk supplier ini']);
    }
    $waCheck = pengadaan_po_supplier_wa_check($conn, $kodeSuplier, 0);
    @set_time_limit(120);
    $result = pengadaan_po_create_from_requests($conn, $ids, $userId, 0);
    $ok = $result['created'] > 0;
    $missingWa = [];
    if ($ok && empty($waCheck['has_wa'])) {
        $missingWa[] = [
            'kode_suplier' => $kodeSuplier,
            'supplier_nama' => $waCheck['supplier_nama'] ?? $kodeSuplier,
            'edit_url' => $waCheck['edit_url'] ?? 'supplier-add',
            'message' => $waCheck['message'] ?? 'WA belum diisi',
        ];
    }
    pengadaan_gudang_json_out([
        'ok' => $ok,
        'message' => $ok ? 'PO supplier berhasil dibuat' : (implode('; ', $result['errors']) ?: 'Gagal'),
        'created' => $result['created'],
        'po_ids' => $result['po_ids'],
        'errors' => $result['errors'],
        'missing_wa' => $missingWa,
        'need_wa' => $missingWa !== [],
        'edit_url' => $missingWa[0]['edit_url'] ?? null,
    ]);
}

if ($action === 'check_supplier_wa') {
    $kodeSuplier = trim((string) ($_GET['kode_suplier'] ?? $_POST['kode_suplier'] ?? ''));
    if ($kodeSuplier === '') {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'Kode supplier kosong']);
    }
    $check = pengadaan_po_supplier_wa_check($conn, $kodeSuplier, 0);
    pengadaan_gudang_json_out($check);
}

if ($action === 'po_wa') {
    $poId = (int) ($_GET['po_id'] ?? $_POST['po_id'] ?? 0);
    if ($poId < 1) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'PO tidak valid']);
    }
    $wa = pengadaan_po_wa_data($conn, $poId);
    if ($wa['ok'] && !empty($wa['has_wa'])) {
        pengadaan_po_mark_sent($conn, $poId, $userId);
    }
    pengadaan_gudang_json_out($wa);
}

if ($action === 'po_confirm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $poId = (int) ($_POST['po_id'] ?? 0);
    if ($poId < 1) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'PO tidak valid']);
    }
    $ok = pengadaan_po_mark_confirmed($conn, $poId, $userId);
    pengadaan_gudang_json_out(['ok' => $ok, 'message' => $ok ? 'PO ditandai dikonfirmasi supplier' : 'Gagal']);
}

if ($action === 'po_batal' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $poId = (int) ($_POST['po_id'] ?? 0);
    if ($poId < 1) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'PO tidak valid']);
    }
    $ok = (bool) mysqli_query($conn, "UPDATE pengadaan_po SET status = 'batal', updated_at = NOW() WHERE id = $poId AND status NOT IN ('selesai')");
    if ($ok) {
        mysqli_query($conn, "UPDATE pengadaan_request SET po_id = NULL, status = 'pending', updated_at = NOW() WHERE po_id = $poId AND status = 'diproses'");
    }
    pengadaan_gudang_json_out(['ok' => $ok, 'message' => $ok ? 'PO dibatalkan' : 'Gagal membatalkan PO']);
}

if ($action === 'po_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $poId = (int) ($_POST['po_id'] ?? 0);
    $result = pengadaan_po_delete($conn, $poId);
    pengadaan_gudang_json_out($result);
}

if ($action === 'po_edit_lines' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $poId = (int) ($_POST['po_id'] ?? 0);
    $lines = $_POST['lines'] ?? [];
    if ($poId < 1 || !is_array($lines) || $lines === []) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'Data edit PO tidak valid']);
    }
    $result = pengadaan_po_update_lines_qty_satuan($conn, $poId, $lines);
    if (!empty($result['ok']) && array_key_exists('diskon_estimasi', $_POST)) {
        $disk = pengadaan_po_set_diskon_estimasi($conn, $poId, (float) $_POST['diskon_estimasi']);
        if ($disk !== true) {
            pengadaan_gudang_json_out(['ok' => false, 'message' => (string) $disk, 'updated' => (int) ($result['updated'] ?? 0)]);
        }
    }
    pengadaan_gudang_json_out($result);
}

if ($action === 'po_save_diskon' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $poId = (int) ($_POST['po_id'] ?? 0);
    $diskon = (float) ($_POST['diskon_estimasi'] ?? 0);
    $disk = pengadaan_po_set_diskon_estimasi($conn, $poId, $diskon);
    if ($disk !== true) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => (string) $disk]);
    }
    pengadaan_gudang_json_out(['ok' => true, 'message' => 'Diskon estimasi disimpan', 'diskon_estimasi' => max(0, round($diskon, 2))]);
}

if ($action === 'po_search_barang') {
    $q = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
    $poId = (int) ($_GET['po_id'] ?? $_POST['po_id'] ?? 0);
    $preferKode = '';
    if ($poId > 0) {
        $po = pengadaan_po_get($conn, $poId);
        $preferKode = $po ? (string) ($po['kode_suplier'] ?? '') : '';
    }
    $items = pengadaan_po_search_barang($conn, $q, $preferKode, 20);
    pengadaan_gudang_json_out(['ok' => true, 'items' => $items]);
}

if ($action === 'po_add_line' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $poId = (int) ($_POST['po_id'] ?? 0);
    $barangId = (int) ($_POST['barang_id'] ?? 0);
    $qtyPo = (float) ($_POST['qty_po'] ?? 0);
    $satuan = trim((string) ($_POST['satuan_nama'] ?? ''));
    $cabangId = (int) ($_POST['cabang_id'] ?? 0);
    if ($poId < 1 || $barangId < 1) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'Pilih barang terlebih dahulu']);
    }
    $result = pengadaan_po_add_line_manual($conn, $poId, $barangId, $qtyPo, $satuan, $cabangId);
    pengadaan_gudang_json_out($result);
}

if ($action === 'po_delete_line' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $poId = (int) ($_POST['po_id'] ?? 0);
    $lineId = (int) ($_POST['line_id'] ?? 0);
    $result = pengadaan_po_delete_line($conn, $poId, $lineId);
    pengadaan_gudang_json_out($result);
}

pengadaan_gudang_json_out(['ok' => false, 'message' => 'Aksi tidak dikenal']);
