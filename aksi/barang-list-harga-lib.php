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

require_once __DIR__ . '/functions.php';

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
