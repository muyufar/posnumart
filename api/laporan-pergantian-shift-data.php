<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../aksi/koneksi.php';
require_once __DIR__ . '/laporan-pergantian-shift-lib.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sesi habis, silakan login ulang.']);
    exit;
}

$userLoginCabang = mysqli_query($conn, "SELECT user_cabang FROM user WHERE user_id = '" . (int) $_SESSION['user_id'] . "'");
$sessionCabangData = mysqli_fetch_array($userLoginCabang);
$sessionCabang = ($sessionCabangData && isset($sessionCabangData['user_cabang'])) ? (int) $sessionCabangData['user_cabang'] : 0;

shift_laporan_ensure_tables($conn);

$pakaiPergantianShift = shift_laporan_pakai_pergantian_shift($sessionCabang);

$tanggal = isset($_GET['tanggal']) ? trim((string) $_GET['tanggal']) : date('Y-m-d');
$shift = shift_laporan_normalize_shift(isset($_GET['shift']) ? (string) $_GET['shift'] : 'pagi', $sessionCabang);
$jamMulaiReq = isset($_GET['jam_mulai']) ? trim((string) $_GET['jam_mulai']) : '';
$jamSelesaiReq = isset($_GET['jam_selesai']) ? trim((string) $_GET['jam_selesai']) : '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Format tanggal tidak valid.']);
    exit;
}

$simpan = shift_laporan_ambil_simpanan($conn, $sessionCabang, $tanggal, $shift);
$headerSimpan = $simpan['header'] ?? null;

if (isset($_GET['jam_only']) && (string) $_GET['jam_only'] === '1') {
    $jamInfo = shift_laporan_jam_dari_simpanan($headerSimpan, $shift, $sessionCabang);
    echo json_encode([
        'ok' => true,
        'jam' => [
            'jam_mulai' => $jamInfo['jam_mulai'],
            'jam_selesai' => $jamInfo['jam_selesai'],
        ],
        'has_saved' => $jamInfo['from_saved'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$previewJam = $pakaiPergantianShift && isset($_GET['preview_jam']) && (string) $_GET['preview_jam'] === '1';

if ($pakaiPergantianShift && !$previewJam) {
    $jamTersimpan = shift_laporan_jam_dari_simpanan($headerSimpan, $shift, $sessionCabang);
    if ($jamTersimpan['from_saved']) {
        $jamMulaiReq = $jamTersimpan['jam_mulai'];
        $jamSelesaiReq = $jamTersimpan['jam_selesai'];
    }
}

$jam = shift_laporan_resolve_jam($shift, $jamMulaiReq, $jamSelesaiReq, $sessionCabang);
if ($pakaiPergantianShift && shift_laporan_parse_jam($jamMulaiReq) === null && $jamMulaiReq !== '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Format jam mulai tidak valid (gunakan HH:MM).']);
    exit;
}
if ($pakaiPergantianShift && shift_laporan_parse_jam($jamSelesaiReq) === null && $jamSelesaiReq !== '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Format jam selesai tidak valid (gunakan HH:MM).']);
    exit;
}

$tokoRows = mysqli_query($conn, "SELECT toko_nama, toko_kota, toko_wa FROM toko WHERE toko_cabang = $sessionCabang LIMIT 1");
$toko = mysqli_fetch_assoc($tokoRows) ?: ['toko_nama' => 'NUMART', 'toko_kota' => '', 'toko_wa' => ''];
$namaToko = trim(($toko['toko_nama'] ?? 'NUMART') . ' ' . ($toko['toko_kota'] ?? ''));

$penjualan = shift_laporan_ambil_penjualan_kasir($conn, $sessionCabang, $tanggal, $shift, $jam['jam_mulai'], $jam['jam_selesai']);

$kasirList = [];
foreach ($penjualan as $row) {
    $uid = $row['user_id'];
    $manual = $simpan['kasir'][$uid] ?? ['pengeluaran_kas' => 0, 'setoran_kas' => 0];
    $jumlah = $row['penjualan_kas'];
    $sisaKas = $jumlah - $manual['pengeluaran_kas'];
    $selisih = $manual['setoran_kas'] - $sisaKas;

    $kasirList[] = array_merge($row, [
        'jumlah_penjualan' => $row['penjualan_sistem'],
        'pengeluaran_kas' => $manual['pengeluaran_kas'],
        'sisa_kas' => $sisaKas,
        'setoran_kas' => $manual['setoran_kas'],
        'selisih' => $selisih,
    ]);
}

$totalSistem = array_sum(array_column($kasirList, 'penjualan_sistem'));
$totalQrisTf = array_sum(array_column($kasirList, 'penjualan_qris_tf'));
$totalKas = array_sum(array_column($kasirList, 'penjualan_kas'));
$totalPiutang = array_sum(array_column($kasirList, 'penjualan_piutang'));
$totalJumlah = array_sum(array_column($kasirList, 'jumlah_penjualan'));
$totalPengeluaranKasir = array_sum(array_column($kasirList, 'pengeluaran_kas'));
$totalSisaKasKasir = array_sum(array_column($kasirList, 'sisa_kas'));
$totalSetoranKasir = array_sum(array_column($kasirList, 'setoran_kas'));

$pengeluaranRows = shift_laporan_ambil_pengeluaran_laba($conn, $sessionCabang, $tanggal, $shift, $jam['jam_mulai'], $jam['jam_selesai']);
$totalPengeluaranRincian = array_sum(array_column($pengeluaranRows, 'jumlah'));
$totalSisaPenjualanKas = $totalKas - $totalPengeluaranRincian;
$selisihAkhir = $totalSetoranKasir - $totalSisaPenjualanKas;

$hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$ts = strtotime($tanggal);
$hari = $hariIndo[(int) date('w', $ts)] ?? '';
$tglTampil = date('d/m/y', $ts);

echo json_encode([
    'ok' => true,
    'meta' => [
        'toko_nama' => $namaToko,
        'tanggal' => $tanggal,
        'hari' => $hari,
        'tanggal_tampil' => $tglTampil,
        'shift' => $shift,
        'shift_label' => shift_laporan_label($shift, $sessionCabang),
        'pakai_pergantian_shift' => $pakaiPergantianShift,
        'tampil_piutang' => shift_laporan_cabang_nugrosir($sessionCabang),
        'jam' => [
            'jam_mulai' => substr($jam['jam_mulai'], 0, 5),
            'jam_selesai' => substr($jam['jam_selesai'], 0, 5),
        ],
        'jam_tampil' => $pakaiPergantianShift ? shift_laporan_jam_tampil($jam['jam_mulai'], $jam['jam_selesai']) : '',
        'jam_from_saved' => $pakaiPergantianShift && !$previewJam && $headerSimpan && !empty($headerSimpan['jam_mulai']),
        'pengeluaran_sumber' => 'laba-bersih-data',
        'default_wa' => (string) ($toko['toko_wa'] ?? ''),
    ],
    'kasir' => $kasirList,
    'totals' => [
        'penjualan_sistem' => $totalSistem,
        'penjualan_qris_tf' => $totalQrisTf,
        'penjualan_kas' => $totalKas,
        'penjualan_piutang' => $totalPiutang,
        'jumlah_penjualan' => $totalJumlah,
        'pengeluaran_kas_kasir' => $totalPengeluaranKasir,
        'sisa_kas_kasir' => $totalSisaKasKasir,
        'setoran_kas_kasir' => $totalSetoranKasir,
        'total_pengeluaran_rincian' => $totalPengeluaranRincian,
        'total_sisa_penjualan_kas' => $totalSisaPenjualanKas,
        'selisih_akhir' => $selisihAkhir,
    ],
    'pengeluaran' => $pengeluaranRows,
    'footer' => [
        'setor_ke' => ($simpan['header'] ?? [])['setor_ke'] ?? '',
        'tgl_setor' => ($simpan['header'] ?? [])['tgl_setor'] ?? '',
    ],
    'ttd' => $simpan['ttd'] ?? [
        'kp_akt' => ['image' => '', 'nama' => '', 'signed_at' => '', 'signed_by' => null],
        'kasir1' => ['image' => '', 'nama' => '', 'signed_at' => '', 'signed_by' => null],
        'kasir2' => ['image' => '', 'nama' => '', 'signed_at' => '', 'signed_by' => null],
    ],
    'laporan_id' => ($simpan['header'] ?? [])['id'] ?? null,
], JSON_UNESCAPED_UNICODE);
