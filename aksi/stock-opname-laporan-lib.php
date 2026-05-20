<?php
/**
 * Library laporan stock opname & buku persediaan barang dagangan (format standar toko retail).
 */

function so_laporan_tanggal_indo($tanggal): string
{
    $tanggal = trim((string) $tanggal);
    if ($tanggal === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) !== 1) {
        return '-';
    }
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];
    $split = explode('-', $tanggal);
    $m = (int) ($split[1] ?? 0);
    return ($split[2] ?? '') . ' ' . ($bulan[$m] ?? '') . ' ' . ($split[0] ?? '');
}

function so_laporan_json_out(array $payload): void
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function so_laporan_sanitize_date(string $s, string $fallback): string
{
    $s = trim($s);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) !== 1) {
        return $fallback;
    }
    return $s;
}

function so_laporan_parse_periode($dariRaw, $sampaiRaw): array
{
    $today = date('Y-m-d');
    $defaultDari = date('Y-m-01');
    $dari = so_laporan_sanitize_date((string) $dariRaw, $defaultDari);
    $sampai = so_laporan_sanitize_date((string) $sampaiRaw, $today);
    if (strtotime($dari) > strtotime($sampai)) {
        $tmp = $dari;
        $dari = $sampai;
        $sampai = $tmp;
    }
    return ['dari' => $dari, 'sampai' => $sampai];
}

function so_laporan_get_toko($conn, int $cabang): array
{
    $cabang = (int) $cabang;
    $res = mysqli_query($conn, "SELECT * FROM toko WHERE toko_cabang = $cabang LIMIT 1");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return $row;
    }
    return [
        'toko_nama' => 'Toko',
        'toko_alamat' => '',
        'toko_kota' => '',
        'toko_tlpn' => '',
        'toko_wa' => '',
        'toko_email' => '',
    ];
}

/**
 * Sesi stock opname selesai dalam periode (filter tanggal proses).
 */
function so_laporan_fetch_sesi($conn, int $cabang, string $dari, string $sampai, int $statusOnly = 1): array
{
    $cabang = (int) $cabang;
    $dariEsc = mysqli_real_escape_string($conn, $dari);
    $sampaiEsc = mysqli_real_escape_string($conn, $sampai);
    $statusSql = $statusOnly > 0 ? ' AND s.stock_opname_status > 0 ' : '';

    $sql = "
        SELECT
            s.stock_opname_id,
            s.stock_opname_date_create,
            s.stock_opname_date_proses,
            s.stock_opname_datetime_create,
            s.stock_opname_datetime_upload,
            s.stock_opname_status,
            s.stock_opname_tipe,
            s.stock_opname_user_create,
            s.stock_opname_user_eksekusi,
            s.stock_opname_user_upload,
            uc.user_nama AS user_create_nama,
            ue.user_nama AS user_eksekusi_nama,
            uu.user_nama AS user_upload_nama,
            (SELECT COUNT(*) FROM stock_opname_hasil h
             WHERE h.soh_stock_opname_id = s.stock_opname_id AND h.soh_barang_cabang = s.stock_opname_cabang) AS jumlah_item,
            (SELECT COUNT(*) FROM stock_opname_hasil h
             WHERE h.soh_stock_opname_id = s.stock_opname_id AND h.soh_barang_cabang = s.stock_opname_cabang AND h.soh_selisih > 0) AS item_lebih,
            (SELECT COUNT(*) FROM stock_opname_hasil h
             WHERE h.soh_stock_opname_id = s.stock_opname_id AND h.soh_barang_cabang = s.stock_opname_cabang AND h.soh_selisih < 0) AS item_kurang,
            (SELECT COUNT(*) FROM stock_opname_hasil h
             WHERE h.soh_stock_opname_id = s.stock_opname_id AND h.soh_barang_cabang = s.stock_opname_cabang AND h.soh_selisih = 0) AS item_sesuai,
            (SELECT IFNULL(SUM(ABS(h.soh_selisih)), 0) FROM stock_opname_hasil h
             WHERE h.soh_stock_opname_id = s.stock_opname_id AND h.soh_barang_cabang = s.stock_opname_cabang) AS total_selisih_qty
        FROM stock_opname s
        LEFT JOIN user uc ON uc.user_id = s.stock_opname_user_create
        LEFT JOIN user ue ON ue.user_id = s.stock_opname_user_eksekusi
        LEFT JOIN user uu ON uu.user_id = s.stock_opname_user_upload
        WHERE s.stock_opname_cabang = $cabang
          AND s.stock_opname_date_proses BETWEEN '$dariEsc' AND '$sampaiEsc'
          $statusSql
        ORDER BY s.stock_opname_date_proses DESC, s.stock_opname_id DESC
    ";
    $rows = [];
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = $r;
        }
    }
    return $rows;
}

/**
 * Satu sesi stock opname (untuk halaman detail / cetak).
 */
function so_laporan_fetch_sesi_by_id($conn, int $sesiId, int $cabang): ?array
{
    $sesiId = (int) $sesiId;
    $cabang = (int) $cabang;
    $sql = "
        SELECT s.*,
            uc.user_nama AS user_create_nama,
            ue.user_nama AS user_eksekusi_nama,
            uu.user_nama AS user_upload_nama
        FROM stock_opname s
        LEFT JOIN user uc ON uc.user_id = s.stock_opname_user_create
        LEFT JOIN user ue ON ue.user_id = s.stock_opname_user_eksekusi
        LEFT JOIN user uu ON uu.user_id = s.stock_opname_user_upload
        WHERE s.stock_opname_id = $sesiId AND s.stock_opname_cabang = $cabang
        LIMIT 1
    ";
    $res = mysqli_query($conn, $sql);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return $row;
    }
    return null;
}

/**
 * Baris hasil satu sesi (format laporan resmi).
 */
function so_laporan_fetch_hasil_sesi($conn, int $sesiId, int $cabang): array
{
    $sesiId = (int) $sesiId;
    $cabang = (int) $cabang;
    $sql = "
        SELECT
            h.soh_id,
            h.soh_barang_kode,
            h.soh_barang_stock_system,
            h.soh_stock_fisik,
            h.soh_selisih,
            h.soh_note,
            b.barang_nama,
            IFNULL(st.satuan_nama, '-') AS satuan_nama,
            IFNULL(b.barang_harga, 0) AS barang_harga
        FROM stock_opname_hasil h
        LEFT JOIN barang b ON b.barang_id = h.soh_barang_id AND b.barang_cabang = h.soh_barang_cabang
        LEFT JOIN satuan st ON st.satuan_id = b.barang_satuan_id
        WHERE h.soh_stock_opname_id = $sesiId AND h.soh_barang_cabang = $cabang
        ORDER BY h.soh_barang_kode ASC
    ";
    $rows = [];
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = $r;
        }
    }
    return $rows;
}

function so_laporan_detail_url(int $sesiId, string $dari = '', string $sampai = ''): string
{
    $q = 'stock-opname-laporan-detail?id=' . base64_encode((string) $sesiId);
    if ($dari !== '') {
        $q .= '&dari=' . urlencode($dari);
    }
    if ($sampai !== '') {
        $q .= '&sampai=' . urlencode($sampai);
    }
    return $q;
}

/**
 * Detail hasil stock opname (baris per barang) dalam periode.
 */
function so_laporan_fetch_hasil($conn, int $cabang, string $dari, string $sampai, string $search = ''): array
{
    $cabang = (int) $cabang;
    $dariEsc = mysqli_real_escape_string($conn, $dari);
    $sampaiEsc = mysqli_real_escape_string($conn, $sampai);
    $searchSql = '';
    if ($search !== '') {
        $sEsc = mysqli_real_escape_string($conn, $search);
        $searchSql = " AND (h.soh_barang_kode LIKE '%$sEsc%' OR b.barang_nama LIKE '%$sEsc%' OR b.kode_suplier LIKE '%$sEsc%') ";
    }

    $sql = "
        SELECT
            h.soh_id,
            h.soh_stock_opname_id,
            h.soh_barang_kode,
            h.soh_barang_stock_system,
            h.soh_stock_fisik,
            h.soh_selisih,
            h.soh_note,
            h.soh_date,
            h.soh_tipe,
            0 AS soh_approved,
            s.stock_opname_date_proses,
            s.stock_opname_tipe,
            s.stock_opname_status,
            b.barang_nama,
            b.barang_harga,
            b.kode_suplier,
            IFNULL(st.satuan_nama, '-') AS satuan_nama
        FROM stock_opname_hasil h
        INNER JOIN stock_opname s ON s.stock_opname_id = h.soh_stock_opname_id
        LEFT JOIN barang b ON b.barang_id = h.soh_barang_id AND b.barang_cabang = h.soh_barang_cabang
        LEFT JOIN satuan st ON st.satuan_id = b.barang_satuan_id
        WHERE h.soh_barang_cabang = $cabang
          AND s.stock_opname_cabang = $cabang
          AND s.stock_opname_status > 0
          AND s.stock_opname_date_proses BETWEEN '$dariEsc' AND '$sampaiEsc'
          $searchSql
        ORDER BY s.stock_opname_date_proses DESC, h.soh_barang_kode ASC
    ";
    $rows = [];
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $harga = (float) ($r['barang_harga'] ?? 0);
            $fisik = (float) ($r['soh_stock_fisik'] ?? 0);
            $r['harga_satuan'] = $harga;
            $r['nilai_persediaan'] = $fisik * $harga;
            $r['tipe_label'] = ((int) ($r['stock_opname_tipe'] ?? 0) === 1) ? 'Keseluruhan' : 'Per Produk';
            $rows[] = $r;
        }
    }
    return $rows;
}

/**
 * Buku Persediaan Barang Dagangan — baris standar (PMK / praktik toko retail).
 * Satu baris penyesuaian per item hasil stock opname dalam periode.
 */
function so_laporan_fetch_buku_stok($conn, int $cabang, string $dari, string $sampai): array
{
    $hasil = so_laporan_fetch_hasil($conn, $cabang, $dari, $sampai);
    $rows = [];
    $no = 1;
    foreach ($hasil as $h) {
        $selisih = (float) ($h['soh_selisih'] ?? 0);
        $masuk = $selisih > 0 ? $selisih : 0;
        $keluar = $selisih < 0 ? abs($selisih) : 0;
        $sistem = (float) ($h['soh_barang_stock_system'] ?? 0);
        $fisik = (float) ($h['soh_stock_fisik'] ?? 0);
        $harga = (float) ($h['harga_satuan'] ?? 0);

        $rows[] = [
            'no' => $no++,
            'tanggal' => $h['stock_opname_date_proses'] ?? $h['soh_date'],
            'no_bukti' => 'SO-' . str_pad((string) ($h['soh_stock_opname_id'] ?? '0'), 5, '0', STR_PAD_LEFT),
            'kode_barang' => $h['soh_barang_kode'] ?? '',
            'nama_barang' => $h['barang_nama'] ?? '',
            'satuan' => $h['satuan_nama'] ?? '-',
            'kode_supplier' => $h['kode_suplier'] ?? '',
            'uraian' => 'Penyesuaian Stock Opname — ' . ($h['tipe_label'] ?? ''),
            'saldo_awal' => $sistem,
            'masuk' => $masuk,
            'keluar' => $keluar,
            'saldo_akhir' => $fisik,
            'harga_satuan' => $harga,
            'nilai_saldo_awal' => $sistem * $harga,
            'nilai_masuk' => $masuk * $harga,
            'nilai_keluar' => $keluar * $harga,
            'nilai_saldo_akhir' => $fisik * $harga,
            'keterangan' => trim((string) ($h['soh_note'] ?? '')),
            'selisih' => $selisih,
        ];
    }
    return $rows;
}

function so_laporan_tipe_label(int $tipe): string
{
    return $tipe === 1 ? 'Keseluruhan' : 'Per Produk';
}

function so_laporan_format_rupiah($n): string
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function so_laporan_format_qty($n): string
{
    $v = (float) $n;
    if (abs($v - round($v)) < 0.0001) {
        return (string) (int) round($v);
    }
    return number_format($v, 2, ',', '.');
}
