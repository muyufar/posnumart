<?php
/**
 * Ringkasan laba rugi accrual per cabang — dipakai inves/konsolidasi.php
 * Logika selaras laba-bersih-laporan-accural.php (basis accrual).
 */

if (!function_exists('invesKonsolidasi_tableHasColumn')) {
    function invesKonsolidasi_tableHasColumn($conn, $table, $column)
    {
        static $cache = array();
        $key = $table . '.' . $column;
        if (!isset($cache[$key])) {
            $tableEsc = mysqli_real_escape_string($conn, $table);
            $columnEsc = mysqli_real_escape_string($conn, $column);
            $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
            $cache[$key] = ($res && mysqli_num_rows($res) > 0);
        }
        return $cache[$key];
    }
}

if (!function_exists('invesKonsolidasi_isBebanLainFinansial')) {
    function invesKonsolidasi_isBebanLainFinansial($kode_akun, $nama_kategori)
    {
        $k = strtoupper(trim((string) $kode_akun));
        if ($k !== '' && preg_match('/^9-/', $k)) {
            return true;
        }
        $n = strtolower(trim((string) $nama_kategori));
        $keywords = array(
            'beban bunga', 'bunga bank', 'bunga rk', 'administrasi bank', 'admin bank',
            'beban pinjaman', 'provisi', 'beban provisi', 'biaya bank',
        );
        foreach ($keywords as $kw) {
            if ($n !== '' && strpos($n, $kw) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('invesKonsolidasi_bagiHasilRates')) {
    function invesKonsolidasi_bagiHasilRates($cabang)
    {
        $cabang = (int) $cabang;
        $rate_nugrosir = 0.0;
        $rate_pcnu = 0.0;
        if ($cabang === 1) {
            $rate_nugrosir = 0.45;
            $rate_pcnu = 0.05;
        } elseif ($cabang === 2) {
            $rate_nugrosir = 0.30;
            $rate_pcnu = 0.0;
        } elseif ($cabang === 3) {
            $rate_nugrosir = 0.25;
            $rate_pcnu = 0.05;
        } elseif ($cabang === 5) {
            $rate_nugrosir = 0.45;
            $rate_pcnu = 0.05;
        }
        return array('rate_nugrosir' => $rate_nugrosir, 'rate_pcnu' => $rate_pcnu);
    }
}

if (!function_exists('invesKonsolidasi_labaCabangUntukBagiHasil')) {
    function invesKonsolidasi_labaCabangUntukBagiHasil($conn, $cabang, $tanggal_awal, $tanggal_akhir)
    {
        $cabang = (int) $cabang;
        $tanggal_awal = mysqli_real_escape_string($conn, $tanggal_awal);
        $tanggal_akhir = mysqli_real_escape_string($conn, $tanggal_akhir);

        $sql = "
            SELECT
              (COALESCE(SUM(invoice_sub_total), 0)
               - COALESCE(SUM(invoice_total_beli), 0)
               - COALESCE((
                  SELECT SUM(CAST(REPLACE(REPLACE(l2.jumlah, '.', ''), ',', '') AS DECIMAL(18,2)))
                  FROM laba l2
                  LEFT JOIN laba_kategori lk2 ON CAST(l2.kategori AS UNSIGNED) = lk2.id
                  WHERE l2.tipe = 1
                    AND l2.cabang = {$cabang}
                    AND l2.date >= '{$tanggal_awal} 00:00:00'
                    AND l2.date <= '{$tanggal_akhir} 23:59:59'
                    AND lk2.kategori = 'beban'
                ), 0)
              ) AS laba_bersih
            FROM invoice
            WHERE invoice_cabang = {$cabang}
              AND invoice_date BETWEEN '{$tanggal_awal}' AND '{$tanggal_akhir}'
        ";
        $res = mysqli_query($conn, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        return (float) (isset($row['laba_bersih']) ? $row['laba_bersih'] : 0);
    }
}

if (!function_exists('invesKonsolidasi_skemaBagiHasilPusat')) {
    function invesKonsolidasi_skemaBagiHasilPusat()
    {
        return array(
            array('cabang' => 1, 'rate' => 0.45, 'nama' => 'Bagi Hasil Numart Dukun (45%)'),
            array('cabang' => 3, 'rate' => 0.50, 'nama' => 'Bagi Hasil Numart Pondok Srumbung (50%)'),
            array('cabang' => 2, 'rate' => 0.30, 'nama' => 'Bagi Hasil Numart Tren Pondok Pakis (30%)'),
            array('cabang' => 5, 'rate' => 0.45, 'nama' => 'Bagi Hasil Numart Tegalrejo (45%)'),
        );
    }
}

if (!function_exists('invesKonsolidasi_detailBagiHasilPusat')) {
    function invesKonsolidasi_detailBagiHasilPusat($conn, $tanggal_awal, $tanggal_akhir)
    {
        $detail = array();
        $total = 0.0;
        foreach (invesKonsolidasi_skemaBagiHasilPusat() as $item) {
            $cabang = (int) $item['cabang'];
            $rate = (float) $item['rate'];
            $laba = invesKonsolidasi_labaCabangUntukBagiHasil($conn, $cabang, $tanggal_awal, $tanggal_akhir);
            $nilai = $laba * $rate;
            $detail[] = array(
                'cabang' => $cabang,
                'nama' => $item['nama'],
                'rate' => $rate,
                'laba_cabang' => $laba,
                'nilai' => $nilai,
            );
            $total += $nilai;
        }
        return array('detail' => $detail, 'total' => $total);
    }
}

if (!function_exists('invesKonsolidasi_pendapatanBagiHasilPusat')) {
    function invesKonsolidasi_pendapatanBagiHasilPusat($conn, $tanggal_awal, $tanggal_akhir)
    {
        $hasil = invesKonsolidasi_detailBagiHasilPusat($conn, $tanggal_awal, $tanggal_akhir);
        return (float) $hasil['total'];
    }
}

if (!function_exists('invesKonsolidasi_queryPendapatanLain')) {
    function invesKonsolidasi_queryPendapatanLain($conn, $cabang, $escAwal, $escAkhir, $pendapatanLainLike)
    {
        $cabang = (int) $cabang;
        $hasAkunKredit = invesKonsolidasi_tableHasColumn($conn, 'laba', 'akun_kredit');

        if ($hasAkunKredit) {
            $sql = "
                SELECT COALESCE(SUM(CAST(REPLACE(REPLACE(l.jumlah, '.', ''), ',', '') AS DECIMAL(18,2))), 0) AS total
                FROM laba l
                LEFT JOIN laba_kategori lk_kredit
                  ON (
                    CAST(l.akun_kredit AS UNSIGNED) = lk_kredit.id
                    OR TRIM(COALESCE(l.akun_kredit, '')) = TRIM(COALESCE(lk_kredit.kode_akun, ''))
                  )
                LEFT JOIN laba_kategori lk
                  ON (
                    CAST(l.kategori AS UNSIGNED) = lk.id
                    OR TRIM(COALESCE(l.kategori, '')) = TRIM(COALESCE(lk.kode_akun, ''))
                  )
                WHERE l.tipe = 0
                  AND l.cabang = {$cabang}
                  AND l.date >= '{$escAwal} 00:00:00'
                  AND l.date <= '{$escAkhir} 23:59:59'
                  AND (
                    TRIM(COALESCE(lk_kredit.kode_akun, '')) LIKE '{$pendapatanLainLike}'
                    OR TRIM(COALESCE(lk.kode_akun, '')) LIKE '{$pendapatanLainLike}'
                  )
            ";
        } else {
            $sql = "
                SELECT COALESCE(SUM(CAST(REPLACE(REPLACE(l.jumlah, '.', ''), ',', '') AS DECIMAL(18,2))), 0) AS total
                FROM laba l
                LEFT JOIN laba_kategori lk ON CAST(l.kategori AS UNSIGNED) = lk.id
                WHERE l.tipe = 0
                  AND l.cabang = {$cabang}
                  AND l.date >= '{$escAwal} 00:00:00'
                  AND l.date <= '{$escAkhir} 23:59:59'
                  AND TRIM(COALESCE(lk.kode_akun, '')) LIKE '{$pendapatanLainLike}'
            ";
        }

        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return 0.0;
        }
        $row = mysqli_fetch_assoc($res);
        return (float) (isset($row['total']) ? $row['total'] : 0);
    }
}

if (!function_exists('invesKonsolidasi_ringkasanCabang')) {
    function invesKonsolidasi_ringkasanCabang($conn, $cabang, $tanggal_awal, $tanggal_akhir, $pendapatanBagiHasilPusat = null)
    {
        $cabang = (int) $cabang;
        $escAwal = mysqli_real_escape_string($conn, $tanggal_awal);
        $escAkhir = mysqli_real_escape_string($conn, $tanggal_akhir);

        $penjualanRes = mysqli_query($conn, "
            SELECT
                COALESCE(SUM(invoice_sub_total), 0) AS penjualan,
                COUNT(invoice_id) AS transaksi,
                COALESCE(SUM(invoice_total_beli), 0) AS hpp
            FROM invoice
            WHERE invoice_cabang = {$cabang}
              AND invoice_date BETWEEN '{$escAwal}' AND '{$escAkhir}'
        ");
        $penjualanRow = $penjualanRes ? mysqli_fetch_assoc($penjualanRes) : array();
        if (!is_array($penjualanRow)) {
            $penjualanRow = array();
        }

        $penjualan = (float) (isset($penjualanRow['penjualan']) ? $penjualanRow['penjualan'] : 0);
        $transaksi = (int) (isset($penjualanRow['transaksi']) ? $penjualanRow['transaksi'] : 0);
        $hpp = (float) (isset($penjualanRow['hpp']) ? $penjualanRow['hpp'] : 0);
        $labaKotor = $penjualan - $hpp;

        $pendapatanLainLike = mysqli_real_escape_string($conn, '8-') . '%';
        $pendapatanLain = invesKonsolidasi_queryPendapatanLain($conn, $cabang, $escAwal, $escAkhir, $pendapatanLainLike);

        $bebanOperasional = 0.0;
        $bebanLain = 0.0;
        $totalBeban = 0.0;
        $qBeban = mysqli_query($conn, "
            SELECT
                COALESCE(lk.name, 'Tanpa Kategori') AS kategori_nama,
                MAX(COALESCE(lk.kode_akun, '')) AS kode_akun,
                SUM(CAST(REPLACE(REPLACE(l.jumlah, '.', ''), ',', '') AS DECIMAL(18,2))) AS total
            FROM laba l
            LEFT JOIN laba_kategori lk ON CAST(l.kategori AS UNSIGNED) = lk.id
            WHERE l.tipe = 1
              AND l.cabang = {$cabang}
              AND l.date >= '{$escAwal} 00:00:00'
              AND l.date <= '{$escAkhir} 23:59:59'
              AND lk.kategori = 'beban'
            GROUP BY lk.name
        ");
        if ($qBeban) {
            while ($row = mysqli_fetch_assoc($qBeban)) {
                $nilai = (float) (isset($row['total']) ? $row['total'] : 0);
                $totalBeban += $nilai;
                if (invesKonsolidasi_isBebanLainFinansial(
                    isset($row['kode_akun']) ? $row['kode_akun'] : '',
                    isset($row['kategori_nama']) ? $row['kategori_nama'] : ''
                )) {
                    $bebanLain += $nilai;
                } else {
                    $bebanOperasional += $nilai;
                }
            }
        }

        $labaSebelumBeban = $labaKotor + $pendapatanLain;
        $labaOperasi = $labaSebelumBeban - $totalBeban;
        $cadanganPajak = 0.0;
        $labaSebelumBagi = $labaOperasi;
        $labaSebelumBagiHasilPcnu = $labaOperasi;
        $bagiHasilMasuk = 0.0;
        $bagiHasilKeluar = 0.0;
        $bagiHasilPcnu = 0.0;
        $labaBersih = $labaOperasi;

        if ($cabang === 0) {
            $cadanganPajak = $labaOperasi * 0.05;
            $labaSebelumBagi = $labaOperasi - $cadanganPajak;
            $labaSebelumBagiHasilPcnu = $labaSebelumBagi;
            $bagiHasilPcnu = $labaSebelumBagi * 0.05;
            if ($pendapatanBagiHasilPusat === null) {
                $bagiHasilMasuk = invesKonsolidasi_pendapatanBagiHasilPusat($conn, $tanggal_awal, $tanggal_akhir);
            } else {
                $bagiHasilMasuk = (float) $pendapatanBagiHasilPusat;
            }
            $labaBersih = $labaSebelumBagi - $bagiHasilPcnu + $bagiHasilMasuk;
        } else {
            $rates = invesKonsolidasi_bagiHasilRates($cabang);
            $cadanganPajak = $labaOperasi * 0.05;
            $labaSebelumBagi = $labaOperasi - $cadanganPajak;
            $labaSebelumBagiHasilPcnu = $labaSebelumBagi;
            $bagiHasilKeluar = $labaSebelumBagi * $rates['rate_nugrosir'];
            $bagiHasilPcnu = $labaSebelumBagi * $rates['rate_pcnu'];
            $labaBersih = $labaSebelumBagi - $bagiHasilKeluar - $bagiHasilPcnu;
        }

        $marginKotor = $penjualan > 0 ? ($labaKotor / $penjualan) * 100 : 0.0;

        return array(
            'penjualan' => $penjualan,
            'transaksi' => $transaksi,
            'hpp' => $hpp,
            'laba_kotor' => $labaKotor,
            'margin_kotor' => $marginKotor,
            'pendapatan_lain' => $pendapatanLain,
            'beban_operasional' => $bebanOperasional,
            'beban_lain' => $bebanLain,
            'total_beban' => $totalBeban,
            'laba_operasi' => $labaOperasi,
            'cadangan_pajak' => $cadanganPajak,
            'laba_sebelum_bagi_hasil' => $labaSebelumBagi,
            'laba_sebelum_bagi_hasil_pcnu' => $labaSebelumBagiHasilPcnu,
            'bagi_hasil_masuk' => $bagiHasilMasuk,
            'bagi_hasil_keluar' => $bagiHasilKeluar,
            'bagi_hasil_pcnu' => $bagiHasilPcnu,
            'pendapatan_bagi_hasil' => $bagiHasilMasuk,
            'laba_bersih' => $labaBersih,
        );
    }
}
