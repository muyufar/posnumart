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

/**
 * Nama bulan dalam Bahasa Indonesia (1–12).
 */
function so_laporan_nama_bulan(int $m): string
{
    $b = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $b[$m] ?? '';
}

/**
 * Kembalikan daftar bulan dalam periode [dari, sampai].
 * Setiap elemen: ['year'=>int, 'month'=>int, 'label'=>'Januari 2026',
 *                 'last_day'=>'2026-01-31', 'key'=>'m2026_01']
 * Maksimal 36 bulan untuk mencegah kolom terlalu banyak.
 */
function so_laporan_months_in_period(string $dari, string $sampai): array
{
    $months = [];
    $cur    = new DateTime(substr($dari, 0, 7) . '-01');
    $end    = new DateTime(substr($sampai, 0, 7) . '-01');
    $limit  = 36;

    while ($cur <= $end && $limit-- > 0) {
        $y    = (int) $cur->format('Y');
        $m    = (int) $cur->format('n');
        $last = (int) $cur->format('t');
        $months[] = [
            'year'     => $y,
            'month'    => $m,
            'label'    => so_laporan_nama_bulan($m) . ' ' . $y,
            'last_day' => sprintf('%04d-%02d-%02d', $y, $m, $last),
            'key'      => sprintf('m%04d_%02d', $y, $m),
        ];
        $cur->modify('+1 month');
    }
    return $months;
}

/**
 * Nilai persediaan per produk per bulan (matriks).
 *
 * Untuk tiap bulan B dalam periode, rekonstruksi stok akhir bulan B:
 *   stok_B = current_stock + penjualan setelah akhir_B - pembelian setelah akhir_B
 *   nilai_beli_B = stok_B × harga_beli
 *   nilai_jual_B = stok_B × harga_jual
 *
 * Return: array produk, tiap elemen memiliki key dinamis:
 *   nilai_beli_{key}, nilai_jual_{key}, stok_{key}
 */
function so_laporan_fetch_nilai_per_bulan($conn, int $cabang, string $dari, string $sampai, string $search = ''): array
{
    $cabang    = (int) $cabang;
    $sampaiEsc = mysqli_real_escape_string($conn, $sampai);
    $months    = so_laporan_months_in_period($dari, $sampai);

    if (empty($months)) return [];

    $searchSql = '';
    if ($search !== '') {
        $sEsc      = mysqli_real_escape_string($conn, $search);
        $searchSql = " AND (b.barang_kode LIKE '%$sEsc%' OR b.barang_nama LIKE '%$sEsc%') ";
    }

    /* Bangun CASE per bulan untuk penjualan & pembelian */
    $pjCases = '';
    $pbCases = '';
    foreach ($months as $mn) {
        $ld  = mysqli_real_escape_string($conn, $mn['last_day']);
        $key = $mn['key'];
        $pjCases .= "SUM(CASE WHEN penjualan_date > '$ld' THEN barang_qty ELSE 0 END) AS jual_after_{$key},\n";
        $pbCases .= "SUM(CASE WHEN pembelian_date > '$ld' THEN barang_qty ELSE 0 END) AS beli_after_{$key},\n";
    }
    $pjCases = rtrim($pjCases, ",\n");
    $pbCases = rtrim($pbCases, ",\n");

    /* Bangun SELECT join alias per bulan */
    $pjJoinCols = implode(",\n        ", array_map(fn($mn) =>
        "COALESCE(pj.jual_after_{$mn['key']}, 0) AS jual_after_{$mn['key']}", $months));
    $pbJoinCols = implode(",\n        ", array_map(fn($mn) =>
        "COALESCE(pb.beli_after_{$mn['key']}, 0) AS beli_after_{$mn['key']}", $months));

    $hjualExpr = "CAST(REPLACE(REPLACE(REPLACE(b.barang_harga,'.',''),',','.'), ' ', '') AS DECIMAL(15,2))";

    $sql = "
        SELECT
            b.barang_id,
            b.barang_kode,
            b.barang_nama,
            CAST(NULLIF(TRIM(b.barang_stock), '') AS DECIMAL(18,4)) AS current_stock,
            b.barang_harga_beli                                      AS harga_beli,
            $hjualExpr                                               AS harga_jual,
            IFNULL(k.kategori_nama, '-')                             AS kategori_nama,
            IFNULL(st.satuan_nama, '-')                              AS satuan_nama,
            $pjJoinCols,
            $pbJoinCols
        FROM barang b
        LEFT JOIN kategori k  ON k.kategori_id  = b.barang_kategori_id
        LEFT JOIN satuan   st ON st.satuan_id   = b.barang_satuan_id
        LEFT JOIN (
            SELECT barang_id, $pjCases
            FROM   penjualan
            WHERE  penjualan_cabang = $cabang
            GROUP  BY barang_id
        ) pj ON pj.barang_id = b.barang_id
        LEFT JOIN (
            SELECT barang_id, $pbCases
            FROM   pembelian
            WHERE  pembelian_cabang = $cabang
            GROUP  BY barang_id
        ) pb ON pb.barang_id = b.barang_id
        WHERE b.barang_cabang = $cabang
          AND b.barang_status = '1'
          $searchSql
        ORDER BY b.barang_nama ASC, b.barang_kode ASC
    ";

    $rows = [];
    $res  = mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $curStock = (float) ($r['current_stock'] ?? 0);
            $hBeli    = (float) ($r['harga_beli']    ?? 0);
            $hJual    = (float) ($r['harga_jual']    ?? 0);

            foreach ($months as $mn) {
                $key      = $mn['key'];
                $jualSth  = (float) ($r['jual_after_' . $key] ?? 0);
                $beliSth  = (float) ($r['beli_after_' . $key] ?? 0);
                $stok     = $curStock + $jualSth - $beliSth;
                $r['stok_'       . $key] = $stok;
                $r['nilai_beli_' . $key] = $stok * $hBeli;
                $r['nilai_jual_' . $key] = $stok * $hJual;
                /* bersihkan kolom raw */
                unset($r['jual_after_' . $key], $r['beli_after_' . $key]);
            }
            $r['harga_beli'] = $hBeli;
            $r['harga_jual'] = $hJual;
            $rows[] = $r;
        }
    }
    return ['months' => $months, 'rows' => $rows];
}

/**
 * Total nilai persediaan per bulan — satu baris per bulan.
 * Output: [['label'=>'Januari 2026','total_stok'=>...,'total_nilai_beli'=>...,'total_nilai_jual'=>...], ...]
 */
function so_laporan_total_nilai_per_bulan(array $months, array $itemRows): array
{
    $result = [];
    foreach ($months as $mn) {
        $totalStok = 0.0;
        $totalBeli = 0.0;
        $totalJual = 0.0;
        foreach ($itemRows as $r) {
            $totalStok += (float) ($r['stok_'       . $mn['key']] ?? 0);
            $totalBeli += (float) ($r['nilai_beli_' . $mn['key']] ?? 0);
            $totalJual += (float) ($r['nilai_jual_' . $mn['key']] ?? 0);
        }
        $result[] = [
            'label'            => $mn['label'],
            'total_stok'       => $totalStok,
            'total_nilai_beli' => $totalBeli,
            'total_nilai_jual' => $totalJual,
        ];
    }
    return $result;
}

/**
 * Ringkasan nilai per bulan per kategori dari hasil so_laporan_fetch_nilai_per_bulan().
 */
function so_laporan_ringkasan_per_bulan_kategori(array $months, array $rows): array
{
    $map = [];
    foreach ($rows as $r) {
        $kat = trim((string) ($r['kategori_nama'] ?? ''));
        if ($kat === '' || $kat === '-') $kat = 'Tanpa Kategori';
        if (!isset($map[$kat])) {
            $map[$kat] = ['kategori_nama' => $kat, 'jumlah_produk' => 0];
            foreach ($months as $mn) {
                $map[$kat]['stok_'       . $mn['key']] = 0.0;
                $map[$kat]['nilai_beli_' . $mn['key']] = 0.0;
                $map[$kat]['nilai_jual_' . $mn['key']] = 0.0;
            }
        }
        $map[$kat]['jumlah_produk']++;
        foreach ($months as $mn) {
            $map[$kat]['stok_'       . $mn['key']] += (float) ($r['stok_'       . $mn['key']] ?? 0);
            $map[$kat]['nilai_beli_' . $mn['key']] += (float) ($r['nilai_beli_' . $mn['key']] ?? 0);
            $map[$kat]['nilai_jual_' . $mn['key']] += (float) ($r['nilai_jual_' . $mn['key']] ?? 0);
        }
    }
    ksort($map);

    /* Grand total */
    $gt = ['kategori_nama' => 'GRAND TOTAL', 'jumlah_produk' => count($rows)];
    foreach ($months as $mn) {
        $gt['stok_'       . $mn['key']] = 0.0;
        $gt['nilai_beli_' . $mn['key']] = 0.0;
        $gt['nilai_jual_' . $mn['key']] = 0.0;
    }
    foreach ($map as $row) {
        foreach ($months as $mn) {
            $gt['nilai_beli_' . $mn['key']] += $row['nilai_beli_' . $mn['key']];
            $gt['nilai_jual_' . $mn['key']] += $row['nilai_jual_' . $mn['key']];
            $gt['stok_'       . $mn['key']] += $row['stok_'       . $mn['key']];
        }
    }

    $result = array_values($map);
    $result[] = $gt;
    return $result;
}

/**
 * Nilai Persediaan Barang per Periode — berbasis data master (akumulasi transaksi).
 *
 * Metode rekonstruksi stok pada akhir periode:
 *   stok_akhir = barang_stock (saat ini)
 *              + total penjualan setelah periode
 *              - total pembelian setelah periode
 *
 * Stok awal periode:
 *   stok_awal = stok_akhir - pembelian_dalam_periode + penjualan_dalam_periode
 *
 * Nilai = stok_akhir × harga_beli  dan  stok_akhir × harga_jual
 */
function so_laporan_fetch_nilai_stock($conn, int $cabang, string $dari, string $sampai, string $search = ''): array
{
    $cabang    = (int) $cabang;
    $dariEsc   = mysqli_real_escape_string($conn, $dari);
    $sampaiEsc = mysqli_real_escape_string($conn, $sampai);

    $searchSql = '';
    if ($search !== '') {
        $sEsc      = mysqli_real_escape_string($conn, $search);
        $searchSql = " AND (b.barang_kode LIKE '%$sEsc%' OR b.barang_nama LIKE '%$sEsc%') ";
    }

    $sql = "
        SELECT
            b.barang_id,
            b.barang_kode,
            b.barang_nama,
            CAST(NULLIF(TRIM(b.barang_stock), '') AS DECIMAL(18,4))     AS current_stock,
            b.barang_harga_beli                                          AS harga_beli,
            CAST(REPLACE(REPLACE(REPLACE(b.barang_harga, '.', ''), ',', '.'), ' ', '') AS DECIMAL(15,2))
                                                                         AS harga_jual,
            IFNULL(k.kategori_nama, '-')                                 AS kategori_nama,
            IFNULL(st.satuan_nama, '-')                                  AS satuan_nama,
            /* Pembelian dalam periode */
            COALESCE(pb.beli_dalam, 0)                                   AS beli_dalam,
            /* Penjualan dalam periode */
            COALESCE(pj.jual_dalam, 0)                                   AS jual_dalam,
            /* Pembelian SETELAH akhir periode (untuk rekonstruksi) */
            COALESCE(pba.beli_setelah, 0)                                AS beli_setelah,
            /* Penjualan SETELAH akhir periode (untuk rekonstruksi) */
            COALESCE(pja.jual_setelah, 0)                                AS jual_setelah
        FROM barang b
        LEFT JOIN kategori k  ON k.kategori_id  = b.barang_kategori_id
        LEFT JOIN satuan   st ON st.satuan_id   = b.barang_satuan_id
        /* Pembelian dalam periode */
        LEFT JOIN (
            SELECT barang_id, SUM(barang_qty) AS beli_dalam
            FROM   pembelian
            WHERE  pembelian_cabang = $cabang
              AND  pembelian_date  BETWEEN '$dariEsc' AND '$sampaiEsc'
            GROUP  BY barang_id
        ) pb  ON pb.barang_id  = b.barang_id
        /* Penjualan dalam periode */
        LEFT JOIN (
            SELECT barang_id, SUM(barang_qty) AS jual_dalam
            FROM   penjualan
            WHERE  penjualan_cabang = $cabang
              AND  penjualan_date  BETWEEN '$dariEsc' AND '$sampaiEsc'
            GROUP  BY barang_id
        ) pj  ON pj.barang_id  = b.barang_id
        /* Pembelian setelah periode (rollback ke akhir periode) */
        LEFT JOIN (
            SELECT barang_id, SUM(barang_qty) AS beli_setelah
            FROM   pembelian
            WHERE  pembelian_cabang = $cabang
              AND  pembelian_date  > '$sampaiEsc'
            GROUP  BY barang_id
        ) pba ON pba.barang_id = b.barang_id
        /* Penjualan setelah periode */
        LEFT JOIN (
            SELECT barang_id, SUM(barang_qty) AS jual_setelah
            FROM   penjualan
            WHERE  penjualan_cabang = $cabang
              AND  penjualan_date  > '$sampaiEsc'
            GROUP  BY barang_id
        ) pja ON pja.barang_id = b.barang_id
        WHERE b.barang_cabang  = $cabang
          AND b.barang_status  = '1'
          $searchSql
        ORDER BY b.barang_nama ASC, b.barang_kode ASC
    ";

    $rows = [];
    $res  = mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $curStock   = (float) ($r['current_stock'] ?? 0);
            $beliDlm    = (float) ($r['beli_dalam']    ?? 0);
            $jualDlm    = (float) ($r['jual_dalam']    ?? 0);
            $beliSth    = (float) ($r['beli_setelah']  ?? 0);
            $jualSth    = (float) ($r['jual_setelah']  ?? 0);
            $hBeli      = (float) ($r['harga_beli']    ?? 0);
            $hJual      = (float) ($r['harga_jual']    ?? 0);

            /* Rekonstruksi stok akhir periode */
            $stokAkhir  = $curStock + $jualSth - $beliSth;
            /* Rekonstruksi stok awal periode */
            $stokAwal   = $stokAkhir - $beliDlm + $jualDlm;

            $r['stok_awal']   = $stokAwal;
            $r['beli_dalam']  = $beliDlm;
            $r['jual_dalam']  = $jualDlm;
            $r['stok_akhir']  = $stokAkhir;
            $r['harga_beli']  = $hBeli;
            $r['harga_jual']  = $hJual;
            $r['nilai_beli']  = $stokAkhir * $hBeli;
            $r['nilai_jual']  = $stokAkhir * $hJual;
            $rows[] = $r;
        }
    }
    return $rows;
}

/**
 * Ringkasan nilai persediaan dikelompokkan per kategori.
 * Input: hasil dari so_laporan_fetch_nilai_stock().
 * Output: array baris [no, kategori_nama, jumlah_produk, stok_awal, beli_dalam, jual_dalam,
 *         stok_akhir, nilai_beli, nilai_jual] + baris grand-total terakhir.
 */
function so_laporan_nilai_ringkasan_per_kategori(array $rows): array
{
    $map = [];
    foreach ($rows as $r) {
        $kat = trim((string) ($r['kategori_nama'] ?? ''));
        if ($kat === '' || $kat === '-') $kat = 'Tanpa Kategori';
        if (!isset($map[$kat])) {
            $map[$kat] = [
                'kategori_nama' => $kat,
                'jumlah_produk' => 0,
                'stok_awal'     => 0.0,
                'beli_dalam'    => 0.0,
                'jual_dalam'    => 0.0,
                'stok_akhir'    => 0.0,
                'nilai_beli'    => 0.0,
                'nilai_jual'    => 0.0,
            ];
        }
        $map[$kat]['jumlah_produk']++;
        $map[$kat]['stok_awal']  += (float) ($r['stok_awal']  ?? 0);
        $map[$kat]['beli_dalam'] += (float) ($r['beli_dalam'] ?? 0);
        $map[$kat]['jual_dalam'] += (float) ($r['jual_dalam'] ?? 0);
        $map[$kat]['stok_akhir'] += (float) ($r['stok_akhir'] ?? 0);
        $map[$kat]['nilai_beli'] += (float) ($r['nilai_beli'] ?? 0);
        $map[$kat]['nilai_jual'] += (float) ($r['nilai_jual'] ?? 0);
    }

    /* Urutkan abjad */
    ksort($map);

    $result = [];
    $no = 1;
    $gt = ['jumlah_produk' => 0, 'stok_awal' => 0.0, 'beli_dalam' => 0.0,
           'jual_dalam' => 0.0, 'stok_akhir' => 0.0, 'nilai_beli' => 0.0, 'nilai_jual' => 0.0];

    foreach ($map as $row) {
        $row['no'] = $no++;
        $result[] = $row;
        foreach (['jumlah_produk','stok_awal','beli_dalam','jual_dalam','stok_akhir','nilai_beli','nilai_jual'] as $k) {
            $gt[$k] += $row[$k];
        }
    }

    /* Baris grand total */
    $gt['no']            = null;
    $gt['kategori_nama'] = 'GRAND TOTAL';
    $result[]            = $gt;

    return $result;
}

/**
 * Ringkasan total nilai persediaan.
 */
function so_laporan_nilai_stock_summary(array $rows): array
{
    $totalStokAkhir = 0;
    $totalNilaiBeli = 0;
    $totalNilaiJual = 0;
    $totalBeli      = 0;
    $totalJual      = 0;

    foreach ($rows as $r) {
        $totalStokAkhir += (float) ($r['stok_akhir'] ?? 0);
        $totalNilaiBeli += (float) ($r['nilai_beli'] ?? 0);
        $totalNilaiJual += (float) ($r['nilai_jual'] ?? 0);
        $totalBeli      += (float) ($r['beli_dalam'] ?? 0);
        $totalJual      += (float) ($r['jual_dalam'] ?? 0);
    }
    return [
        'total_stok_akhir' => $totalStokAkhir,
        'total_beli'       => $totalBeli,
        'total_jual'       => $totalJual,
        'total_nilai_beli' => $totalNilaiBeli,
        'total_nilai_jual' => $totalNilaiJual,
    ];
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
