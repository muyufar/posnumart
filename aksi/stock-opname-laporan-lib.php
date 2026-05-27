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

    /*
     * Bangun CASE per bulan untuk penjualan, pembelian, transfer keluar, transfer masuk, SO.
     *
     * Formula rekonstruksi per bulan B:
     *   stok_B = current_stock
     *          + jual_after_B   (undo penjualan setelah akhir bulan B)
     *          + tfk_after_B    (undo transfer keluar setelah akhir bulan B)
     *          - tfm_after_B    (undo transfer masuk setelah akhir bulan B; deduplicated by ref+slug)
     *          - so_after_B     (undo SO adjustment setelah akhir bulan B)
     *          - beli_after_B   (undo pembelian langsung; biasanya 0 untuk cabang retail)
     *
     * Dedup transfer masuk: GROUP BY (tpm_ref, tpm_kode_slug) + MAX(tpm_qty) menghilangkan
     * semua duplikat akibat double-confirm, termasuk yang tpm_date_time-nya berbeda.
     */
    $pjCases  = '';
    $pbCases  = '';
    $tfkCases = '';
    $tfmCases = '';
    $soCases  = '';
    foreach ($months as $mn) {
        $ld  = mysqli_real_escape_string($conn, $mn['last_day']);
        $key = $mn['key'];
        $pjCases  .= "SUM(CASE WHEN penjualan_date > '$ld' THEN barang_qty ELSE 0 END) AS jual_after_{$key},\n";
        $pbCases  .= "SUM(CASE WHEN pembelian_date > '$ld' THEN barang_qty ELSE 0 END) AS beli_after_{$key},\n";
        $tfkCases .= "SUM(CASE WHEN tpk_date > '$ld' THEN tpk_qty ELSE 0 END) AS tfk_after_{$key},\n";
        $tfmCases .= "SUM(CASE WHEN tpk_date > '$ld' THEN tpk_qty ELSE 0 END) AS tfm_after_{$key},\n";
        $soCases  .= "SUM(CASE WHEN s.stock_opname_date_proses > '$ld' THEN h.soh_selisih ELSE 0 END) AS so_after_{$key},\n";
    }
    $pjCases  = rtrim($pjCases,  ",\n");
    $pbCases  = rtrim($pbCases,  ",\n");
    $tfkCases = rtrim($tfkCases, ",\n");
    $tfmCases = rtrim($tfmCases, ",\n");
    $soCases  = rtrim($soCases,  ",\n");

    /* Bangun SELECT join alias per bulan */
    $pjJoinCols  = implode(",\n        ", array_map(fn($mn) =>
        "COALESCE(pj.jual_after_{$mn['key']}, 0) AS jual_after_{$mn['key']}", $months));
    $pbJoinCols  = implode(",\n        ", array_map(fn($mn) =>
        "COALESCE(pb.beli_after_{$mn['key']}, 0) AS beli_after_{$mn['key']}", $months));
    $tfkJoinCols = implode(",\n        ", array_map(fn($mn) =>
        "COALESCE(tfk.tfk_after_{$mn['key']}, 0) AS tfk_after_{$mn['key']}", $months));
    $tfmJoinCols = implode(",\n        ", array_map(fn($mn) =>
        "COALESCE(tfm.tfm_after_{$mn['key']}, 0) AS tfm_after_{$mn['key']}", $months));
    $soJoinCols  = implode(",\n        ", array_map(fn($mn) =>
        "COALESCE(soadj.so_after_{$mn['key']}, 0) AS so_after_{$mn['key']}", $months));

    $hjualExpr = "CAST(REPLACE(REPLACE(REPLACE(b.barang_harga,'.',''),',','.'), ' ', '') AS DECIMAL(15,2))";

    $sql = "
        SELECT
            b.barang_id,
            b.barang_kode,
            b.barang_nama,
            b.barang_kode_slug,
            CAST(NULLIF(TRIM(b.barang_stock), '') AS DECIMAL(18,4)) AS current_stock,
            b.barang_harga_beli                                      AS harga_beli,
            $hjualExpr                                               AS harga_jual,
            IFNULL(k.kategori_nama, '-')                             AS kategori_nama,
            IFNULL(st.satuan_nama, '-')                              AS satuan_nama,
            $pjJoinCols,
            $pbJoinCols,
            $tfkJoinCols,
            $tfmJoinCols,
            $soJoinCols
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
        LEFT JOIN (
            SELECT tpk_barang_id, $tfkCases
            FROM   transfer_produk_keluar
            WHERE  tpk_pengirim_cabang = $cabang
            GROUP  BY tpk_barang_id
        ) tfk ON tfk.tpk_barang_id = b.barang_id
        /* transfer masuk per bulan — menggunakan transfer_produk_keluar sebagai sumber bersih.
         * JOIN via tpk_kode_slug karena tpk_barang_id adalah ID barang cabang PENGIRIM. */
        LEFT JOIN (
            SELECT tpk_kode_slug, $tfmCases
            FROM   transfer_produk_keluar
            WHERE  tpk_penerima_cabang = $cabang
            GROUP  BY tpk_kode_slug
        ) tfm ON tfm.tpk_kode_slug = b.barang_kode_slug
        LEFT JOIN (
            SELECT CAST(h.soh_barang_id AS UNSIGNED) AS barang_id, $soCases
            FROM   stock_opname_hasil h
            JOIN   stock_opname s ON s.stock_opname_id = h.soh_stock_opname_id
            WHERE  h.soh_barang_cabang  = $cabang
              AND  s.stock_opname_status > 0
            GROUP  BY CAST(h.soh_barang_id AS UNSIGNED)
        ) soadj ON soadj.barang_id = b.barang_id
        WHERE b.barang_cabang = $cabang
          AND b.barang_status = '1'
          $searchSql
        ORDER BY b.barang_nama ASC, b.barang_kode ASC
    ";

    $rows = [];
    $res  = mysqli_query($conn, $sql);
    if (!$res) {
        error_log('[so_laporan_fetch_nilai_per_bulan] SQL error: ' . mysqli_error($conn));
        return ['months' => $months, 'rows' => []];
    }
    while ($r = mysqli_fetch_assoc($res)) {
        $curStock = (float) ($r['current_stock'] ?? 0);
        $hBeli    = (float) ($r['harga_beli']    ?? 0);
        $hJual    = (float) ($r['harga_jual']    ?? 0);

        foreach ($months as $mn) {
            $key     = $mn['key'];
            $jualSth = (float) ($r['jual_after_' . $key] ?? 0);
            $beliSth = (float) ($r['beli_after_' . $key] ?? 0);
            $tfkSth  = (float) ($r['tfk_after_'  . $key] ?? 0);
            $tfmSth  = (float) ($r['tfm_after_'  . $key] ?? 0);
            $soSth   = (float) ($r['so_after_'   . $key] ?? 0);
            $stok    = $curStock + $jualSth + $tfkSth - $tfmSth - $soSth - $beliSth;
            $stok    = max(0.0, $stok); // stok tidak bisa negatif
            $r['stok_'       . $key] = $stok;
            $r['nilai_beli_' . $key] = $stok * $hBeli;
            $r['nilai_jual_' . $key] = $stok * $hJual;
            unset(
                $r['jual_after_' . $key], $r['beli_after_' . $key],
                $r['tfk_after_'  . $key], $r['tfm_after_'  . $key],
                $r['so_after_'   . $key]
            );
        }
        $r['harga_beli'] = $hBeli;
        $r['harga_jual'] = $hJual;
        $rows[] = $r;
    }
    return ['months' => $months, 'rows' => $rows];
}

/**
 * Total nilai persediaan (Rp) seluruh produk aktif pada akhir tanggal tertentu.
 *
 * Metode rekonstruksi:
 *   stok_pada_$tanggal = barang_stock
 *                      + SUM(penjualan setelah $tanggal)
 *                      - SUM(pembelian setelah $tanggal)
 *   nilai = stok × harga_beli
 *
 * Gunakan $tanggal = hari terakhir bulan sebelumnya untuk mendapat nilai awal bulan.
 * Gunakan $tanggal = hari terakhir bulan berjalan untuk mendapat nilai akhir bulan.
 */
function so_laporan_nilai_persediaan_pada_tanggal($conn, int $cabang, string $tanggal): float
{
    $cabang = (int) $cabang;
    $tglEsc = mysqli_real_escape_string($conn, $tanggal);

    /*
     * Formula rekonstruksi (untuk cabang retail — non-cabang 0):
     *   stok = current_stock
     *        + penjualan_setelah        (undo penjualan yg mengurangi stok)
     *        + tfk_setelah              (undo transfer keluar yg mengurangi stok)
     *        - tfm_setelah              (undo transfer masuk yg menambah stok)
     *        - so_setelah               (undo SO adjustment setelah tanggal)
     *        - pembelian_setelah        (undo pembelian langsung, relevan untuk cabang 0 / gudang)
     *
     * Deduplication transfer masuk: GROUP BY (tpm_ref, tpm_kode_slug) dengan MAX(tpm_qty).
     * Ini menghilangkan duplikat yang terjadi akibat double-confirm penerimaan transfer,
     * bahkan jika tpm_date_time berbeda antar duplikat.
     */
    $sql = "
        SELECT COALESCE(SUM(
            GREATEST(0,
                COALESCE(CAST(NULLIF(TRIM(b.barang_stock), '') AS DECIMAL(18,4)), 0)
                + COALESCE(pj.jual_setelah, 0)
                + COALESCE(tfk.tfk_setelah, 0)
                - COALESCE(tfm.tfm_setelah, 0)
                - COALESCE(so.so_setelah, 0)
                - COALESCE(pb.beli_setelah, 0)
            ) * COALESCE(b.barang_harga_beli, 0)
        ), 0) AS total_nilai
        FROM barang b
        /* penjualan setelah tanggal */
        LEFT JOIN (
            SELECT barang_id, SUM(barang_qty) AS jual_setelah
            FROM   penjualan
            WHERE  penjualan_cabang = $cabang
              AND  penjualan_date   > '$tglEsc'
            GROUP  BY barang_id
        ) pj ON pj.barang_id = b.barang_id
        /* pembelian setelah tanggal (cabang 0 / gudang; biasanya 0 untuk cabang retail) */
        LEFT JOIN (
            SELECT barang_id, SUM(barang_qty) AS beli_setelah
            FROM   pembelian
            WHERE  pembelian_cabang = $cabang
              AND  pembelian_date   > '$tglEsc'
            GROUP  BY barang_id
        ) pb ON pb.barang_id = b.barang_id
        /* transfer keluar setelah tanggal */
        LEFT JOIN (
            SELECT tpk_barang_id, SUM(tpk_qty) AS tfk_setelah
            FROM   transfer_produk_keluar
            WHERE  tpk_pengirim_cabang = $cabang
              AND  tpk_date            > '$tglEsc'
            GROUP  BY tpk_barang_id
        ) tfk ON tfk.tpk_barang_id = b.barang_id
        /* transfer masuk setelah tanggal
         * — menggunakan transfer_produk_keluar (sumber data bersih, ditulis sekali saat pengiriman).
         * JOIN via tpk_kode_slug = b.barang_kode_slug karena tpk_barang_id adalah ID barang di
         * cabang PENGIRIM, bukan cabang penerima — sehingga JOIN by barang_id ke cabang penerima
         * selalu menghasilkan 0.
         */
        LEFT JOIN (
            SELECT tpk_kode_slug, SUM(tpk_qty) AS tfm_setelah
            FROM   transfer_produk_keluar
            WHERE  tpk_penerima_cabang = $cabang
              AND  tpk_date            > '$tglEsc'
            GROUP  BY tpk_kode_slug
        ) tfm ON tfm.tpk_kode_slug = b.barang_kode_slug
        /* stock opname adjustment setelah tanggal (selisih = fisik - sistem) */
        LEFT JOIN (
            SELECT CAST(h.soh_barang_id AS UNSIGNED) AS barang_id,
                   SUM(h.soh_selisih) AS so_setelah
            FROM   stock_opname_hasil h
            JOIN   stock_opname s ON s.stock_opname_id = h.soh_stock_opname_id
            WHERE  h.soh_barang_cabang           = $cabang
              AND  s.stock_opname_status          > 0
              AND  s.stock_opname_date_proses     > '$tglEsc'
            GROUP  BY CAST(h.soh_barang_id AS UNSIGNED)
        ) so ON so.barang_id = b.barang_id
        WHERE b.barang_cabang = $cabang
          AND b.barang_status  = '1'
    ";

    $res = mysqli_query($conn, $sql);
    if ($res && ($r = mysqli_fetch_assoc($res))) {
        return max(0.0, (float) ($r['total_nilai'] ?? 0));
    }
    return 0.0;
}

/**
 * Hitung mutasi nilai persediaan per bulan: pembelian, penjualan/HPP, transfer, retur, SO.
 *
 * Untuk setiap bulan dalam period $dari–$sampai, return:
 *   label                  : nama bulan (misal "Januari 2025")
 *   key                    : key unik bulan (misal "m2025_01")
 *   nilai_pembelian        : + SUM(qty > 0 × harga_beli) dari tabel pembelian
 *   nilai_retur_beli       : - SUM(qty < 0 × harga_beli) dari tabel pembelian (nilai positif)
 *   nilai_penjualan_hpp    : - SUM(invoice_total_beli)   dari tabel invoice (biaya stok terjual / HPP)
 *   nilai_penjualan_jual   :   SUM(invoice_sub_total)    dari tabel invoice (harga jual / revenue, info saja)
 *   nilai_retur_jual       : + SUM(barang_stock × harga_beli) dari tabel retur
 *   nilai_transfer_keluar  : - SUM(tpk_qty × harga_beli) transfer ke cabang lain
 *   nilai_transfer_masuk   : + SUM(tpm_qty × harga_beli) transfer dari cabang lain
 *   nilai_opname           : +/- SUM(soh_selisih × harga_beli) penyesuaian SO selesai
 */
function so_laporan_mutasi_per_bulan($conn, int $cabang, string $dari, string $sampai): array
{
    $cabang = (int) $cabang;
    $months = so_laporan_months_in_period($dari, $sampai);
    $result = [];

    foreach ($months as $mn) {
        $fd  = mysqli_real_escape_string($conn, sprintf('%04d-%02d-01', $mn['year'], $mn['month']));
        $ld  = mysqli_real_escape_string($conn, $mn['last_day']);

        /* ── Pembelian masuk (qty > 0) ── */
        $r = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COALESCE(SUM(CASE WHEN barang_qty > 0 THEN barang_qty * barang_harga_beli ELSE 0 END), 0) AS msk,
                   COALESCE(SUM(CASE WHEN barang_qty < 0 THEN ABS(barang_qty) * barang_harga_beli ELSE 0 END), 0) AS ret
            FROM pembelian
            WHERE pembelian_cabang = $cabang
              AND pembelian_date BETWEEN '$fd' AND '$ld'
        ") ?: null);
        $nilai_pembelian  = (float) ($r['msk'] ?? 0);
        $nilai_retur_beli = (float) ($r['ret'] ?? 0);

        /* ── Penjualan: HPP (invoice_total_beli) & Nilai Jual (invoice_sub_total) ── */
        $r = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COALESCE(SUM(invoice_total_beli), 0) AS hpp,
                   COALESCE(SUM(invoice_sub_total),  0) AS jual
            FROM invoice
            WHERE invoice_cabang = $cabang
              AND invoice_date BETWEEN '$fd' AND '$ld'
        ") ?: null);
        $nilai_penjualan_hpp  = (float) ($r['hpp']  ?? 0);
        $nilai_penjualan_jual = (float) ($r['jual'] ?? 0);

        /* ── Retur penjualan (barang kembali ke stok) ── */
        $r = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COALESCE(SUM(r.barang_stock * b.barang_harga_beli), 0) AS ret
            FROM retur r
            JOIN invoice i  ON i.penjualan_invoice = r.retur_invoice AND i.invoice_cabang = $cabang
            JOIN barang  b  ON CAST(r.retur_barang_id AS UNSIGNED) = b.barang_id AND b.barang_cabang = $cabang
            WHERE r.retur_date BETWEEN '$fd' AND '$ld'
        ") ?: null);
        $nilai_retur_jual = (float) ($r['ret'] ?? 0);

        /* ── Transfer Keluar ── */
        $r = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COALESCE(SUM(tpk.tpk_qty * b.barang_harga_beli), 0) AS total
            FROM transfer_produk_keluar tpk
            JOIN barang b ON tpk.tpk_barang_id = b.barang_id AND b.barang_cabang = $cabang
            WHERE tpk.tpk_pengirim_cabang = $cabang
              AND tpk.tpk_date BETWEEN '$fd' AND '$ld'
        ") ?: null);
        $nilai_transfer_keluar = (float) ($r['total'] ?? 0);

        /* ── Transfer Masuk (sumber bersih: transfer_produk_keluar tpk_penerima_cabang)
         *    JOIN via kode_slug karena tpk_barang_id adalah ID barang cabang PENGIRIM. ── */
        $r = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COALESCE(SUM(tpk.tpk_qty * b.barang_harga_beli), 0) AS total
            FROM transfer_produk_keluar tpk
            JOIN barang b ON tpk.tpk_kode_slug = b.barang_kode_slug AND b.barang_cabang = $cabang
            WHERE tpk.tpk_penerima_cabang = $cabang
              AND tpk.tpk_date BETWEEN '$fd' AND '$ld'
        ") ?: null);
        $nilai_transfer_masuk = (float) ($r['total'] ?? 0);

        /* ── Stock Opname penyesuaian (selisih × harga_beli) ── */
        $r = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COALESCE(SUM(h.soh_selisih * b.barang_harga_beli), 0) AS total
            FROM stock_opname_hasil h
            JOIN stock_opname s ON s.stock_opname_id = h.soh_stock_opname_id
            JOIN barang        b ON b.barang_id = CAST(h.soh_barang_id AS UNSIGNED)
                                 AND b.barang_cabang = $cabang
            WHERE h.soh_barang_cabang = $cabang
              AND s.stock_opname_status > 0
              AND s.stock_opname_date_proses BETWEEN '$fd' AND '$ld'
        ") ?: null);
        $nilai_opname = (float) ($r['total'] ?? 0);

        $result[] = [
            'label'                 => $mn['label'],
            'key'                   => $mn['key'],
            'nilai_pembelian'       => $nilai_pembelian,
            'nilai_retur_beli'      => $nilai_retur_beli,
            'nilai_penjualan_hpp'   => $nilai_penjualan_hpp,
            'nilai_penjualan_jual'  => $nilai_penjualan_jual,
            'nilai_retur_jual'      => $nilai_retur_jual,
            'nilai_transfer_keluar' => $nilai_transfer_keluar,
            'nilai_transfer_masuk'  => $nilai_transfer_masuk,
            'nilai_opname'          => $nilai_opname,
        ];
    }
    return $result;
}

/**
 * Hitung Persediaan AWAL dari Persediaan AKHIR + mutasi periode.
 *
 * Rumus akuntansi (satu periode):
 *   Persediaan_Awal + Pembelian + Transfer_Masuk + SO_gain
 *       = HPP + Transfer_Keluar + SO_loss + Persediaan_Akhir
 *
 * Disederhanakan (SO_selisih = fisik − sistem, bisa + atau −):
 *   Persediaan_Awal = Persediaan_Akhir + HPP + Transfer_Keluar
 *                   − Transfer_Masuk − Pembelian_net − SO_selisih
 *
 * KEUNGGULAN vs rekonstruksi mundur dari stok saat ini:
 *   • Hanya menggunakan transaksi 1 periode (bukan 5 bulan ke belakang).
 *   • Rekonstruksi hanya diperlukan untuk Persediaan_Akhir (= so_laporan_nilai_persediaan_pada_tanggal).
 *
 * @param string $tgl_awal   hari pertama periode (mis. 2026-01-01)
 * @param string $tgl_akhir  hari terakhir periode (mis. 2026-01-31)
 */
function so_laporan_hitung_persediaan_awal($conn, int $cabang, string $tgl_awal, string $tgl_akhir): float
{
    $cabang    = (int) $cabang;
    $dariEsc   = mysqli_real_escape_string($conn, $tgl_awal);
    $sampaiEsc = mysqli_real_escape_string($conn, $tgl_akhir);

    /* 1. Persediaan akhir (rekonstruksi mundur dari stok saat ini ke akhir periode) */
    $p_akhir = so_laporan_nilai_persediaan_pada_tanggal($conn, $cabang, $tgl_akhir);

    /* 2. HPP penjualan dalam periode (nilai stok yang keluar via penjualan) */
    $r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(invoice_total_beli), 0) AS v
         FROM invoice
         WHERE invoice_cabang = $cabang
           AND invoice_date BETWEEN '$dariEsc' AND '$sampaiEsc'"
    ) ?: null);
    $hpp = (float) ($r['v'] ?? 0);

    /* 3. Transfer keluar periode (cabang ini sbg pengirim — stok berkurang) */
    $r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(tpk.tpk_qty * b.barang_harga_beli), 0) AS v
         FROM transfer_produk_keluar tpk
         JOIN barang b ON tpk.tpk_barang_id = b.barang_id AND b.barang_cabang = $cabang
         WHERE tpk.tpk_pengirim_cabang = $cabang
           AND tpk.tpk_date BETWEEN '$dariEsc' AND '$sampaiEsc'"
    ) ?: null);
    $tfk = (float) ($r['v'] ?? 0);

    /* 4. Transfer masuk periode (cabang ini sbg penerima — stok bertambah)
     *    Sumber: transfer_produk_keluar.tpk_penerima_cabang (bersih, tidak ada duplikat) */
    $r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(tpk.tpk_qty * b.barang_harga_beli), 0) AS v
         FROM transfer_produk_keluar tpk
         JOIN barang b ON tpk.tpk_barang_id = b.barang_id AND b.barang_cabang = $cabang
         WHERE tpk.tpk_penerima_cabang = $cabang
           AND tpk.tpk_date BETWEEN '$dariEsc' AND '$sampaiEsc'"
    ) ?: null);
    $tfm = (float) ($r['v'] ?? 0);

    /* 5. Pembelian langsung neto dalam periode (qty × harga_beli di tabel pembelian;
     *    qty negatif = retur beli, otomatis mengurangi nilai) */
    $r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(barang_qty * barang_harga_beli), 0) AS v
         FROM pembelian
         WHERE pembelian_cabang = $cabang
           AND pembelian_date BETWEEN '$dariEsc' AND '$sampaiEsc'"
    ) ?: null);
    $pembelian = (float) ($r['v'] ?? 0);

    /* 6. Stock opname selisih dalam periode (selisih = fisik − sistem;
     *    > 0 = stok bertambah, < 0 = stok berkurang) */
    $r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(h.soh_selisih * b.barang_harga_beli), 0) AS v
         FROM stock_opname_hasil h
         JOIN stock_opname s ON s.stock_opname_id = h.soh_stock_opname_id
         JOIN barang b ON b.barang_id = CAST(h.soh_barang_id AS UNSIGNED)
                       AND b.barang_cabang = $cabang
         WHERE h.soh_barang_cabang = $cabang
           AND s.stock_opname_status > 0
           AND s.stock_opname_date_proses BETWEEN '$dariEsc' AND '$sampaiEsc'"
    ) ?: null);
    $so_adj = (float) ($r['v'] ?? 0);

    /* Persediaan_Awal = Persediaan_Akhir + HPP + TF_keluar − TF_masuk − Pembelian − SO_selisih */
    return max(0.0, $p_akhir + $hpp + $tfk - $tfm - $pembelian - $so_adj);
}

/**
 * Hitung nilai persediaan pada akhir $tgl_target menggunakan metode MAJU (awal → akhir).
 *
 * Proses:
 *   1. Cari bulan pertama yang memiliki transaksi untuk cabang ini.
 *   2. Dapatkan nilai anchor = rekonstruksi mundur ke akhir bulan SEBELUM bulan pertama
 *      (rekonstruksi mundur hanya dilakukan SEKALI sebagai titik awal).
 *   3. Roll forward bulan per bulan menggunakan data mutasi aktual:
 *        Akhir = Awal + Pembelian − Retur_Beli + TF_Masuk + Retur_Jual − HPP − TF_Keluar ± SO
 *      di mana HPP diambil dari invoice_total_beli (nilai beli historis aktual, bukan harga saat ini).
 *
 * Keunggulan vs rekonstruksi mundur:
 *   - Menggunakan data transaksi historis yang disimpan di invoice_total_beli.
 *   - Tidak terpengaruh perubahan harga barang setelah transaksi terjadi.
 *   - Perhitungan konsisten: setiap bulan dibangun di atas bulan sebelumnya.
 *
 * @param int    $cabang     Kode cabang
 * @param string $tgl_target Tanggal akhir target (Y-m-d), misal "2025-12-31"
 */
function so_laporan_persediaan_forward($conn, int $cabang, string $tgl_target): float
{
    $cabang = (int) $cabang;

    /* 1. Cari bulan paling awal yang memiliki data transaksi untuk cabang ini.
     *    Gunakan YEAR(date) > 0 agar aman di MySQL strict mode
     *    (perbandingan langsung != '0000-00-00' bisa throw error di strict mode). */
    $r = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT MIN(d) AS oldest FROM (
            SELECT MIN(penjualan_date) AS d
              FROM penjualan
             WHERE penjualan_cabang = $cabang
               AND penjualan_date IS NOT NULL
               AND YEAR(penjualan_date) > 0
            UNION ALL
            SELECT MIN(pembelian_date)
              FROM pembelian
             WHERE pembelian_cabang = $cabang
               AND pembelian_date IS NOT NULL
               AND YEAR(pembelian_date) > 0
            UNION ALL
            SELECT MIN(tpk_date)
              FROM transfer_produk_keluar
             WHERE (tpk_pengirim_cabang = $cabang OR tpk_penerima_cabang = $cabang)
               AND tpk_date IS NOT NULL
               AND YEAR(tpk_date) > 0
            UNION ALL
            SELECT MIN(s.stock_opname_date_proses)
              FROM stock_opname s
             WHERE s.stock_opname_cabang = $cabang AND s.stock_opname_status > 0
               AND s.stock_opname_date_proses IS NOT NULL
               AND YEAR(s.stock_opname_date_proses) > 0
        ) AS tbl WHERE d IS NOT NULL AND YEAR(d) > 0
    ") ?: null);

    if (empty($r['oldest'])) return 0.0;

    $oldest_ym          = substr($r['oldest'], 0, 7);    /* "2024-11" */
    $oldest_month_start = $oldest_ym . '-01';            /* "2024-11-01" */

    /* 2. Anchor: rekonstruksi mundur ke akhir bulan SEBELUM bulan pertama.
     *    Ini hanya dilakukan sekali sebagai nilai awal perhitungan maju. */
    $anchor_date = date('Y-m-d', strtotime($oldest_month_start . ' -1 day'));
    $anchor_val  = so_laporan_nilai_persediaan_pada_tanggal($conn, $cabang, $anchor_date);

    /* 3. Rentang bulan: dari bulan pertama s/d akhir bulan target */
    $tgl_target_last = date('Y-m-t', strtotime(substr($tgl_target, 0, 7) . '-01'));
    $months = so_laporan_months_in_period($oldest_month_start, $tgl_target_last);

    /*
     * Roll forward bulan per bulan.
     *
     * Semua komponen dihitung menggunakan barang_harga_beli SAAT INI agar konsisten
     * (sama dengan metode backward reconstruction). Mencampur invoice_total_beli
     * (harga historis) dengan barang_harga_beli (harga saat ini) untuk komponen berbeda
     * menyebabkan HPP > TFM pada banyak kasus sehingga nilai menjadi negatif.
     *
     * Formula per bulan:
     *   Akhir = Awal + TFM + Pembelian_net − JUAL_HPP − TFK ± SO
     *   di mana semuanya = qty × barang_harga_beli (harga saat ini, konsisten satu sama lain)
     */
    $val = $anchor_val;
    foreach ($months as $mn) {
        $fd = mysqli_real_escape_string($conn, sprintf('%04d-%02d-01', $mn['year'], $mn['month']));
        $ld = mysqli_real_escape_string($conn, $mn['last_day']);

        /* Transfer masuk (cabang ini sbg penerima)
         * JOIN via kode_slug karena tpk_barang_id adalah ID barang cabang PENGIRIM. */
        $r = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COALESCE(SUM(tpk.tpk_qty * b.barang_harga_beli), 0) AS v
             FROM transfer_produk_keluar tpk
             JOIN barang b ON tpk.tpk_kode_slug = b.barang_kode_slug AND b.barang_cabang = $cabang
             WHERE tpk.tpk_penerima_cabang = $cabang
               AND tpk.tpk_date BETWEEN '$fd' AND '$ld'"
        ) ?: null);
        $tfm = (float) ($r['v'] ?? 0);

        /* Transfer keluar (cabang ini sbg pengirim) */
        $r = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COALESCE(SUM(tpk.tpk_qty * b.barang_harga_beli), 0) AS v
             FROM transfer_produk_keluar tpk
             JOIN barang b ON b.barang_id = tpk.tpk_barang_id AND b.barang_cabang = $cabang
             WHERE tpk.tpk_pengirim_cabang = $cabang
               AND tpk.tpk_date BETWEEN '$fd' AND '$ld'"
        ) ?: null);
        $tfk = (float) ($r['v'] ?? 0);

        /* Penjualan: HPP = qty × barang_harga_beli (harga saat ini, konsisten) */
        $r = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COALESCE(SUM(p.barang_qty * b.barang_harga_beli), 0) AS v
             FROM penjualan p
             JOIN barang b ON b.barang_id = p.barang_id AND b.barang_cabang = $cabang
             WHERE p.penjualan_cabang = $cabang
               AND p.penjualan_date BETWEEN '$fd' AND '$ld'"
        ) ?: null);
        $hpp = (float) ($r['v'] ?? 0);

        /* Pembelian neto (qty bisa negatif untuk retur beli) */
        $r = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COALESCE(SUM(barang_qty * barang_harga_beli), 0) AS v
             FROM pembelian
             WHERE pembelian_cabang = $cabang
               AND pembelian_date BETWEEN '$fd' AND '$ld'"
        ) ?: null);
        $pb = (float) ($r['v'] ?? 0);

        /* Stock opname penyesuaian */
        $r = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COALESCE(SUM(h.soh_selisih * b.barang_harga_beli), 0) AS v
             FROM stock_opname_hasil h
             JOIN stock_opname s ON s.stock_opname_id = h.soh_stock_opname_id
             JOIN barang b ON b.barang_id = CAST(h.soh_barang_id AS UNSIGNED)
                           AND b.barang_cabang = $cabang
             WHERE h.soh_barang_cabang = $cabang
               AND s.stock_opname_status > 0
               AND s.stock_opname_date_proses BETWEEN '$fd' AND '$ld'"
        ) ?: null);
        $so = (float) ($r['v'] ?? 0);

        $val = $val + $tfm + $pb - $hpp - $tfk + $so;
    }

    return max(0.0, $val);
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
            $stokAkhir  = max(0.0, $curStock + $jualSth - $beliSth);
            /* Rekonstruksi stok awal periode */
            $stokAwal   = max(0.0, $stokAkhir - $beliDlm + $jualDlm);

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
