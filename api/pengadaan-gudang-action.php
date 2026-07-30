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
    $status = trim((string) ($_POST['status'] ?? ''));
    $catatan = trim((string) ($_POST['catatan'] ?? ''));
    $allowed = ['diproses', 'selesai', 'ditolak'];
    if ($id < 1 || !in_array($status, $allowed, true)) {
        pengadaan_gudang_json_out(['ok' => false, 'message' => 'Data tidak valid']);
    }
    $catatanEsc = mysqli_real_escape_string($conn, $catatan);
    $setCatatan = $catatan !== '' ? ", catatan = '$catatanEsc'" : '';
    $setProses = $status === 'diproses' ? ", diproses_by = $userId, diproses_at = NOW()" : '';
    $ok = mysqli_query($conn, "
        UPDATE pengadaan_request SET status = '$status', updated_at = NOW() $setCatatan $setProses
        WHERE id = $id
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
    $waCheck = pengadaan_po_validate_requests_supplier_wa($conn, $ids, 0);
    if (!$waCheck['ok']) {
        $first = $waCheck['missing'][0] ?? [];
        pengadaan_gudang_json_out([
            'ok' => false,
            'message' => 'Supplier "' . ($first['kode_suplier'] ?? '') . '" belum punya nomor WhatsApp',
            'missing_wa' => $waCheck['missing'],
            'edit_url' => (string) ($first['edit_url'] ?? 'supplier-add'),
        ]);
    }
    $result = pengadaan_po_create_from_requests($conn, $ids, $userId, 0);
    pengadaan_gudang_json_out([
        'ok' => $result['created'] > 0,
        'message' => $result['created'] > 0
            ? ('Berhasil buat ' . $result['created'] . ' PO')
            : (implode('; ', $result['errors']) ?: 'Gagal buat PO'),
        'created' => $result['created'],
        'po_ids' => $result['po_ids'],
        'errors' => $result['errors'],
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
    if (!$waCheck['has_wa']) {
        pengadaan_gudang_json_out([
            'ok' => false,
            'message' => $waCheck['message'] ?? 'Nomor WhatsApp supplier belum diisi',
            'edit_url' => $waCheck['edit_url'],
            'supplier_nama' => $waCheck['supplier_nama'],
            'kode_suplier' => $kodeSuplier,
        ]);
    }
    $result = pengadaan_po_create_from_requests($conn, $ids, $userId, 0);
    pengadaan_gudang_json_out([
        'ok' => $result['created'] > 0,
        'message' => $result['created'] > 0 ? 'PO supplier berhasil dibuat' : (implode('; ', $result['errors']) ?: 'Gagal'),
        'created' => $result['created'],
        'po_ids' => $result['po_ids'],
        'errors' => $result['errors'],
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

pengadaan_gudang_json_out(['ok' => false, 'message' => 'Aksi tidak dikenal']);
