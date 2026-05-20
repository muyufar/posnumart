<?php
ob_start();

include __DIR__ . '/../aksi/koneksi.php';
include __DIR__ . '/../aksi/halau.php';
require_once __DIR__ . '/../aksi/stock-opname-laporan-lib.php';

mysqli_set_charset($conn, 'utf8mb4');

if (empty($_SESSION['user_email']) && empty($_SESSION['user_password'])) {
    so_laporan_json_out(['error' => 'Sesi login habis', 'draw' => 1, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$cabang = 0;
if ($userId > 0) {
    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $cabang = (int) ($ru['user_cabang'] ?? 0);
    }
}

$periode = so_laporan_parse_periode($_GET['dari'] ?? '', $_GET['sampai'] ?? '');
$dari = $periode['dari'];
$sampai = $periode['sampai'];
$mode = trim((string) ($_GET['mode'] ?? 'sesi'));
$draw = (int) ($_GET['draw'] ?? 1);

try {
    if ($mode === 'hasil') {
        $search = trim((string) ($_GET['search'] ?? ''));
        $rows = so_laporan_fetch_hasil($conn, $cabang, $dari, $sampai, $search);
        $data = [];
        $i = 1;
        foreach ($rows as $r) {
            $sel = (float) ($r['soh_selisih'] ?? 0);
            $badge = $sel > 0 ? 'badge-success' : ($sel < 0 ? 'badge-danger' : 'badge-secondary');
            $selHtml = '<span class="badge ' . $badge . '">' . so_laporan_format_qty($sel) . '</span>';
            $sid = (int) ($r['soh_stock_opname_id'] ?? 0);
            $detailUrl = so_laporan_detail_url($sid, $dari, $sampai);
            $data[] = [
                $i++,
                'SO-' . str_pad((string) $sid, 5, '0', STR_PAD_LEFT),
                so_laporan_tanggal_indo($r['stock_opname_date_proses'] ?? ''),
                htmlspecialchars($r['soh_barang_kode'] ?? '', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($r['barang_nama'] ?? '', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($r['satuan_nama'] ?? '-', ENT_QUOTES, 'UTF-8'),
                so_laporan_format_qty($r['soh_barang_stock_system'] ?? 0),
                so_laporan_format_qty($r['soh_stock_fisik'] ?? 0),
                $selHtml,
                htmlspecialchars($r['soh_note'] ?? '', ENT_QUOTES, 'UTF-8'),
                '<a href="' . $detailUrl . '" class="btn btn-xs btn-primary" target="_blank" title="Lihat laporan sesi"><i class="fa fa-file-alt"></i> Laporan</a>',
            ];
        }
        so_laporan_json_out([
            'draw' => $draw,
            'recordsTotal' => count($data),
            'recordsFiltered' => count($data),
            'data' => $data,
        ]);
    }

    if ($mode === 'buku') {
        $rows = so_laporan_fetch_buku_stok($conn, $cabang, $dari, $sampai);
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['no'],
                so_laporan_tanggal_indo($r['tanggal'] ?? ''),
                htmlspecialchars($r['no_bukti'] ?? '', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($r['kode_barang'] ?? '', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($r['nama_barang'] ?? '', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($r['satuan'] ?? '', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($r['uraian'] ?? '', ENT_QUOTES, 'UTF-8'),
                so_laporan_format_qty($r['saldo_awal'] ?? 0),
                so_laporan_format_qty($r['masuk'] ?? 0),
                so_laporan_format_qty($r['keluar'] ?? 0),
                so_laporan_format_qty($r['saldo_akhir'] ?? 0),
                so_laporan_format_rupiah($r['harga_satuan'] ?? 0),
                so_laporan_format_rupiah($r['nilai_saldo_akhir'] ?? 0),
                htmlspecialchars($r['keterangan'] ?? '-', ENT_QUOTES, 'UTF-8'),
            ];
        }
        so_laporan_json_out([
            'draw' => $draw,
            'recordsTotal' => count($data),
            'recordsFiltered' => count($data),
            'data' => $data,
        ]);
    }

    $sesi = so_laporan_fetch_sesi($conn, $cabang, $dari, $sampai, 1);
    $data = [];
    $i = 1;
    foreach ($sesi as $r) {
        $id = (int) ($r['stock_opname_id'] ?? 0);
        $tipe = (int) ($r['stock_opname_tipe'] ?? 0);
        $detailUrl = so_laporan_detail_url($id, $dari, $sampai);
        $data[] = [
            $i++,
            'SO-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT),
            so_laporan_tanggal_indo($r['stock_opname_date_proses'] ?? ''),
            so_laporan_tipe_label($tipe),
            htmlspecialchars($r['user_eksekusi_nama'] ?? '-', ENT_QUOTES, 'UTF-8'),
            (int) ($r['jumlah_item'] ?? 0),
            (int) ($r['item_sesuai'] ?? 0),
            (int) ($r['item_lebih'] ?? 0),
            (int) ($r['item_kurang'] ?? 0),
            so_laporan_format_qty($r['total_selisih_qty'] ?? 0),
            '<a href="' . $detailUrl . '" class="btn btn-sm btn-primary" target="_blank"><i class="fa fa-file-alt"></i> Lihat Laporan</a>',
        ];
    }
    so_laporan_json_out([
        'draw' => $draw,
        'recordsTotal' => count($data),
        'recordsFiltered' => count($data),
        'data' => $data,
    ]);
} catch (Throwable $e) {
    so_laporan_json_out([
        'error' => $e->getMessage(),
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
    ]);
}
