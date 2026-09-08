<?php
/**
 * Data "List Harga" barang — dipakai bersama oleh:
 *   - barang-list-harga.php          (tampilan)
 *   - barang-data-list-harga.php     (sumber DataTables server-side)
 *   - export-barang-list-harga.php   (export Excel)
 *
 * Level harga mengikuti form barang-edit.php:
 *   barang_harga            = Harga Umum
 *   barang_harga_grosir_1   = Harga Member Retail
 *   barang_harga_grosir_2   = Harga Grosir
 * Akhiran _s2 adalah level harga yang sama untuk satuan ke-2.
 *
 * Persentase laba dihitung terhadap harga beli (HPP), bukan terhadap harga jual:
 *   persen = (harga jual - harga beli) / harga beli * 100
 * Satuan 1 memakai HPP dasar. Satuan 2 memakai HPP × satuan_isi_2
 * (sama seperti barang-edit / barang-zoom).
 */

if (!defined('NUMART_ROOT')) {
    require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
}
require_once numart_path('aksi/functions.php');


if (!function_exists('barangListHarga_cabangUser')) {
    /**
     * Cabang milik user yang login, dibaca ulang dari tabel user seperti _header-artibut.php.
     */
    function barangListHarga_cabangUser($conn)
    {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        if ($userId > 0) {
            $res = @mysqli_query($conn, "SELECT user_cabang FROM user WHERE user_id = {$userId} LIMIT 1");
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                return (int) $row['user_cabang'];
            }
        }

        return isset($_SESSION['user_cabang']) ? (int) $_SESSION['user_cabang'] : 0;
    }
}

if (!function_exists('barangListHarga_kolomHarga')) {
    /**
     * Harga disimpan sebagai varchar, jadi dinormalkan dulu ke DECIMAL.
     * String kosong dianggap 0 supaya tidak memicu warning konversi.
     */
    function barangListHarga_kolomHarga($kolom, $alias = 'b')
    {
        return "COALESCE(CAST(NULLIF(TRIM({$alias}.{$kolom}), '') AS DECIMAL(18,2)), 0)";
    }
}

if (!function_exists('barangListHarga_derivedTable')) {
    /**
     * Sub-query berisi semua kolom siap pakai (termasuk laba dan persennya)
     * supaya sorting server-side bisa langsung memakai kolom hasil hitungan.
     * $cabang didorong ke dalam sub-query agar tidak menghitung seluruh cabang.
     */
    function barangListHarga_derivedTable($cabang = null)
    {
        $hpp = barang_hpp_sql_expr('b');
        $isi2 = "COALESCE(CAST(NULLIF(TRIM(b.satuan_isi_2), '') AS DECIMAL(18,4)), 0)";
        $hppS2 = "CASE WHEN {$hpp} > 0 AND {$isi2} > 0 THEN {$hpp} * {$isi2} END";

        $s1Umum   = barangListHarga_kolomHarga('barang_harga');
        $s1Retail = barangListHarga_kolomHarga('barang_harga_grosir_1');
        $s1Grosir = barangListHarga_kolomHarga('barang_harga_grosir_2');
        $s2Umum   = barangListHarga_kolomHarga('barang_harga_s2');
        $s2Retail = barangListHarga_kolomHarga('barang_harga_grosir_1_s2');
        $s2Grosir = barangListHarga_kolomHarga('barang_harga_grosir_2_s2');

        // Laba/persen bernilai NULL bila harga jual atau HPP belum lengkap,
        // supaya di tampilan bisa dibedakan dari laba yang memang nol.
        $laba = function ($harga, $hppExpr) {
            return "CASE WHEN {$harga} > 0 AND {$hppExpr} > 0 THEN {$harga} - {$hppExpr} END";
        };
        $persen = function ($harga, $hppExpr) {
            return "CASE WHEN {$harga} > 0 AND {$hppExpr} > 0 THEN ({$harga} - {$hppExpr}) / {$hppExpr} * 100 END";
        };

        $labaUmum     = $laba($s1Umum, $hpp);
        $persenUmum   = $persen($s1Umum, $hpp);
        $labaRetail   = $laba($s1Retail, $hpp);
        $persenRetail = $persen($s1Retail, $hpp);
        $labaGrosir   = $laba($s1Grosir, $hpp);
        $persenGrosir = $persen($s1Grosir, $hpp);

        $labaUmumS2     = $laba($s2Umum, $hppS2);
        $persenUmumS2   = $persen($s2Umum, $hppS2);
        $labaRetailS2   = $laba($s2Retail, $hppS2);
        $persenRetailS2 = $persen($s2Retail, $hppS2);
        $labaGrosirS2   = $laba($s2Grosir, $hppS2);
        $persenGrosirS2 = $persen($s2Grosir, $hppS2);

        $filterCabang = $cabang === null ? '' : ' AND b.barang_cabang = ' . (int) $cabang;

        return " (
            SELECT
                b.barang_id,
                b.barang_kode,
                b.barang_nama,
                b.barang_cabang,
                b.kategori_id,
                COALESCE(k.kategori_nama, '-') AS kategori_nama,
                b.barang_stock,
                {$hpp}      AS hrg_beli,
                {$s1Umum}   AS s1_umum,
                {$s1Retail} AS s1_retail,
                {$s1Grosir} AS s1_grosir,
                {$s2Umum}   AS s2_umum,
                {$s2Retail} AS s2_retail,
                {$s2Grosir} AS s2_grosir,
                {$labaUmum}     AS laba_umum,
                {$persenUmum}   AS persen_umum,
                {$labaRetail}   AS laba_retail,
                {$persenRetail} AS persen_retail,
                {$labaGrosir}   AS laba_grosir,
                {$persenGrosir} AS persen_grosir,
                {$labaUmumS2}     AS laba_umum_s2,
                {$persenUmumS2}   AS persen_umum_s2,
                {$labaRetailS2}   AS laba_retail_s2,
                {$persenRetailS2} AS persen_retail_s2,
                {$labaGrosirS2}   AS laba_grosir_s2,
                {$persenGrosirS2} AS persen_grosir_s2
            FROM barang b
            LEFT JOIN kategori k ON b.kategori_id = k.kategori_id
            WHERE b.barang_status = '1'{$filterCabang}
        ) temp";
    }
}

if (!function_exists('barangListHarga_where')) {
    /**
     * Filter tambahan yang dipakai baik oleh SSP maupun export.
     */
    function barangListHarga_where($conn, $cabang, $kategoriId = 'semua', $margin = 'semua')
    {
        $where = array('barang_cabang = ' . (int) $cabang);

        if ($kategoriId !== 'semua' && $kategoriId !== '' && $kategoriId !== null) {
            $kat = mysqli_real_escape_string($conn, (string) $kategoriId);
            $where[] = "kategori_id = '{$kat}'";
        }

        if ($margin === 'rugi') {
            $where[] = 'persen_umum IS NOT NULL AND persen_umum < 0';
        } elseif ($margin === 'tipis') {
            $where[] = 'persen_umum IS NOT NULL AND persen_umum >= 0 AND persen_umum < 5';
        } elseif ($margin === 'belum_lengkap') {
            $where[] = 'persen_umum IS NULL';
        }

        return implode(' AND ', $where);
    }
}

if (!function_exists('barangListHarga_urutan')) {
    /**
     * Whitelist ORDER BY untuk export (SSP punya mekanisme sortir sendiri).
     */
    function barangListHarga_urutan($urutkan)
    {
        $map = array(
            'nama'         => 'barang_nama ASC',
            'kode'         => 'barang_kode ASC',
            'kategori'     => 'kategori_nama ASC, barang_nama ASC',
            'margin_besar' => 'persen_umum IS NULL, persen_umum DESC',
            'margin_kecil' => 'persen_umum IS NULL, persen_umum ASC',
            'harga_besar'  => 's1_umum DESC',
        );

        return isset($map[$urutkan]) ? $map[$urutkan] : $map['nama'];
    }
}

if (!function_exists('barangListHarga_daftarKategori')) {
    /**
     * Kategori yang benar-benar dipakai barang aktif di cabang ini.
     */
    function barangListHarga_daftarKategori($conn, $cabang)
    {
        $cabang = (int) $cabang;
        $sql = "
            SELECT DISTINCT k.kategori_id, k.kategori_nama
            FROM kategori k
            INNER JOIN barang b ON b.kategori_id = k.kategori_id
            WHERE b.barang_cabang = {$cabang} AND b.barang_status = '1'
            ORDER BY k.kategori_nama ASC
        ";

        $rows = array();
        $res = mysqli_query($conn, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('barangListHarga_ambilData')) {
    /**
     * Seluruh baris tanpa paging — dipakai export Excel.
     */
    function barangListHarga_ambilData($conn, $cabang, $kategoriId = 'semua', $margin = 'semua', $urutkan = 'nama')
    {
        $table   = barangListHarga_derivedTable($cabang);
        $where   = barangListHarga_where($conn, $cabang, $kategoriId, $margin);
        $orderBy = barangListHarga_urutan($urutkan);

        $rows = array();
        $res = mysqli_query($conn, "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderBy}");
        if (!$res) {
            throw new RuntimeException('Query list harga gagal: ' . mysqli_error($conn));
        }
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }

        return $rows;
    }
}

if (!function_exists('barangListHarga_ringkasan')) {
    /**
     * Angka ringkasan untuk kartu di atas tabel.
     */
    function barangListHarga_ringkasan($conn, $cabang, $kategoriId = 'semua', $margin = 'semua')
    {
        $table = barangListHarga_derivedTable($cabang);
        $where = barangListHarga_where($conn, $cabang, $kategoriId, $margin);

        /*
         * Margin dirata-rata secara tertimbang (total laba / total harga beli), bukan
         * AVG per baris. AVG persen mudah meledak gara-gara barang ber-HPP sangat kecil.
         */
        $tertimbang = function ($kolomLaba, $kolomPersen) {
            return "SUM(CASE WHEN {$kolomPersen} IS NOT NULL THEN {$kolomLaba} END)
                    / NULLIF(SUM(CASE WHEN {$kolomPersen} IS NOT NULL THEN hrg_beli END), 0) * 100";
        };

        $rataUmum   = $tertimbang('laba_umum', 'persen_umum');
        $rataRetail = $tertimbang('laba_retail', 'persen_retail');
        $rataGrosir = $tertimbang('laba_grosir', 'persen_grosir');

        $sql = "
            SELECT
                COUNT(*)                                                   AS total_barang,
                SUM(CASE WHEN persen_umum IS NULL THEN 1 ELSE 0 END)       AS belum_lengkap,
                SUM(CASE WHEN persen_umum < 0 THEN 1 ELSE 0 END)           AS rugi,
                SUM(CASE WHEN persen_umum >= 0 AND persen_umum < 5 THEN 1 ELSE 0 END) AS tipis,
                {$rataUmum}   AS rata_persen_umum,
                {$rataRetail} AS rata_persen_retail,
                {$rataGrosir} AS rata_persen_grosir
            FROM {$table}
            WHERE {$where}
        ";

        $kosong = array(
            'total_barang'       => 0,
            'belum_lengkap'      => 0,
            'rugi'               => 0,
            'tipis'              => 0,
            'rata_persen_umum'   => null,
            'rata_persen_retail' => null,
            'rata_persen_grosir' => null,
        );

        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return $kosong;
        }
        $row = mysqli_fetch_assoc($res);

        return $row ? array_merge($kosong, $row) : $kosong;
    }
}

if (!function_exists('blhAngka')) {
    /**
     * Angka rupiah tanpa desimal; nilai kosong ditampilkan sebagai strip.
     */
    function blhAngka($nilai)
    {
        if ($nilai === null || $nilai === '' || (float) $nilai == 0.0) {
            return '-';
        }

        return number_format((float) $nilai, 0, ',', '.');
    }
}

if (!function_exists('blhPersen')) {
    function blhPersen($nilai)
    {
        if ($nilai === null || $nilai === '') {
            return '-';
        }

        return number_format((float) $nilai, 1, ',', '.') . '%';
    }
}

if (!function_exists('blhKelasPersen')) {
    /**
     * Warna sel persentase: merah bila rugi, kuning bila margin tipis, hijau bila sehat.
     */
    function blhKelasPersen($nilai)
    {
        if ($nilai === null || $nilai === '') {
            return 'blh-kosong';
        }
        $n = (float) $nilai;
        if ($n < 0) {
            return 'blh-rugi';
        }
        if ($n < 5) {
            return 'blh-tipis';
        }

        return 'blh-sehat';
    }
}

if (!function_exists('barangListHarga_json')) {
    function barangListHarga_json(array $payload, $httpCode = 200)
    {
        if (!headers_sent()) {
            http_response_code((int) $httpCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        echo json_encode($payload, $flags);
        exit;
    }
}

if (!function_exists('barangListHarga_datatables')) {
    /**
     * Respons DataTables tanpa SSP: cari hanya kode/nama/kategori, tanpa SQL_CALC_FOUND_ROWS.
     */
    function barangListHarga_datatables($conn, $cabang, $kategori, $margin, array $req)
    {
        $draw = (int) ($req['draw'] ?? 1);
        $start = max(0, (int) ($req['start'] ?? 0));
        $length = (int) ($req['length'] ?? 50);
        if ($length < 1 || $length > 250) {
            $length = 50;
        }

        $search = '';
        if (isset($req['search']) && is_array($req['search'])) {
            $search = trim((string) ($req['search']['value'] ?? ''));
        }

        $cabang = (int) $cabang;
        $table = barangListHarga_derivedTable($cabang);
        $where = barangListHarga_where($conn, $cabang, $kategori, $margin);

        if ($search !== '') {
            $esc = mysqli_real_escape_string($conn, $search);
            $where .= " AND (barang_kode LIKE '%{$esc}%' OR barang_nama LIKE '%{$esc}%' OR kategori_nama LIKE '%{$esc}%')";
        }

        $orderMap = [
            1 => 'barang_kode',
            2 => 'barang_nama',
            3 => 'kategori_nama',
            4 => 'hrg_beli',
            5 => 's1_umum',
            6 => 's1_retail',
            7 => 's1_grosir',
            8 => 's2_umum',
            9 => 's2_retail',
            10 => 's2_grosir',
            11 => 'laba_umum',
            12 => 'persen_umum',
            13 => 'laba_retail',
            14 => 'persen_retail',
            15 => 'laba_grosir',
            16 => 'persen_grosir',
            17 => 'laba_umum_s2',
            18 => 'persen_umum_s2',
            19 => 'laba_retail_s2',
            20 => 'persen_retail_s2',
            21 => 'laba_grosir_s2',
            22 => 'persen_grosir_s2',
        ];
        $orderCol = isset($req['order'][0]['column']) ? (int) $req['order'][0]['column'] : 2;
        $orderDir = (isset($req['order'][0]['dir']) && strtolower((string) $req['order'][0]['dir']) === 'desc')
            ? 'DESC'
            : 'ASC';
        $orderBy = $orderMap[$orderCol] ?? 'barang_nama';

        $sqlTotal = "SELECT COUNT(*) AS c FROM barang WHERE barang_status = '1' AND barang_cabang = {$cabang}";
        if ($kategori !== 'semua' && $kategori !== '' && $kategori !== null) {
            $kat = mysqli_real_escape_string($conn, (string) $kategori);
            $sqlTotal .= " AND kategori_id = '{$kat}'";
        }
        $resTotal = mysqli_query($conn, $sqlTotal);
        $recordsTotal = ($resTotal && ($row = mysqli_fetch_assoc($resTotal))) ? (int) $row['c'] : 0;

        $needDerivedCount = ($search !== '' || !in_array((string) $margin, ['semua', ''], true));
        if (!$needDerivedCount) {
            $recordsFiltered = $recordsTotal;
        } else {
            $resFiltered = mysqli_query($conn, "SELECT COUNT(*) AS c FROM {$table} WHERE {$where}");
            if (!$resFiltered) {
                barangListHarga_json([
                    'draw' => $draw,
                    'recordsTotal' => $recordsTotal,
                    'recordsFiltered' => 0,
                    'data' => [],
                    'error' => 'Gagal menghitung data list harga',
                ], 500);
            }
            $recordsFiltered = (int) (mysqli_fetch_assoc($resFiltered)['c'] ?? 0);
        }

        $sqlItems = "
            SELECT
                barang_id, barang_kode, barang_nama, kategori_nama, hrg_beli,
                s1_umum, s1_retail, s1_grosir, s2_umum, s2_retail, s2_grosir,
                laba_umum, persen_umum, laba_retail, persen_retail, laba_grosir, persen_grosir,
                laba_umum_s2, persen_umum_s2, laba_retail_s2, persen_retail_s2, laba_grosir_s2, persen_grosir_s2
            FROM {$table}
            WHERE {$where}
            ORDER BY {$orderBy} {$orderDir}, barang_id DESC
            LIMIT {$start}, {$length}
        ";
        $resItems = mysqli_query($conn, $sqlItems);
        if (!$resItems) {
            barangListHarga_json([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => [],
                'error' => 'Gagal memuat data list harga',
            ], 500);
        }

        $laba = static function ($nilai) {
            if ($nilai === null || $nilai === '') {
                return '-';
            }
            $kelas = (float) $nilai < 0 ? 'text-danger font-weight-bold' : '';
            return '<span class="' . $kelas . '">' . number_format((float) $nilai, 0, ',', '.') . '</span>';
        };
        $persen = static function ($nilai) {
            return '<span class="blh-badge ' . blhKelasPersen($nilai) . '">' . blhPersen($nilai) . '</span>';
        };

        $data = [];
        while ($r = mysqli_fetch_assoc($resItems)) {
            $data[] = [
                (string) ($r['barang_id'] ?? ''),
                htmlspecialchars((string) ($r['barang_kode'] ?? ''), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) ($r['barang_nama'] ?? ''), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) ($r['kategori_nama'] ?? ''), ENT_QUOTES, 'UTF-8'),
                blhAngka($r['hrg_beli'] ?? null),
                blhAngka($r['s1_umum'] ?? null),
                blhAngka($r['s1_retail'] ?? null),
                blhAngka($r['s1_grosir'] ?? null),
                blhAngka($r['s2_umum'] ?? null),
                blhAngka($r['s2_retail'] ?? null),
                blhAngka($r['s2_grosir'] ?? null),
                $laba($r['laba_umum'] ?? null),
                $persen($r['persen_umum'] ?? null),
                $laba($r['laba_retail'] ?? null),
                $persen($r['persen_retail'] ?? null),
                $laba($r['laba_grosir'] ?? null),
                $persen($r['persen_grosir'] ?? null),
                $laba($r['laba_umum_s2'] ?? null),
                $persen($r['persen_umum_s2'] ?? null),
                $laba($r['laba_retail_s2'] ?? null),
                $persen($r['persen_retail_s2'] ?? null),
                $laba($r['laba_grosir_s2'] ?? null),
                $persen($r['persen_grosir_s2'] ?? null),
            ];
        }

        barangListHarga_json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }
}
