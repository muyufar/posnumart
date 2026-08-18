<?php
/**
 * Data laporan penjualan per kategori — dipakai laporan-penjualan-kategori.php
 * dan export-penjualan-kategori-excel.php agar angkanya selalu identik.
 *
 * Rumus per baris penjualan (sudah dicocokkan dengan total invoice):
 *   omzet = barang_qty * keranjang_harga                  -> selaras invoice_sub_total
 *   hpp   = barang_qty_keranjang * keranjang_harga_beli    -> selaras invoice_total_beli
 *
 * Penting performa:
 * Query lama memulai dari seluruh tabel `penjualan` lalu JOIN `invoice` hanya
 * lewat nomor nota. Filter cabang/tanggal ada di `invoice`, jadi MySQL sering
 * memindai ratusan ribu–jutaan baris penjualan semua cabang dulu.
 * Gudang (cabang 0) hampir tidak punya penjualan ritel → terasa cepat.
 * Toko cabang punya volume besar → timeout, halaman putih hanya sidebar.
 *
 * Query baru mengikuti laporan-produk / produk-analisa:
 * filter `penjualan_cabang` + `penjualan_date` dulu, join kategori setelah agregasi.
 */

if (!function_exists('laporanKategori_urutan')) {
    /**
     * Whitelist ORDER BY agar aman dari injeksi.
     */
    function laporanKategori_urutan($urutkan)
    {
        $map = array(
            'penjualan' => 'penjualan DESC',
            'laba'      => 'laba_kotor DESC',
            'margin'    => 'margin DESC',
            'qty'       => 'qty DESC',
            'nama'      => 'kategori_nama ASC',
        );

        return isset($map[$urutkan]) ? $map[$urutkan] : $map['penjualan'];
    }
}

if (!function_exists('laporanKategori_cabangUser')) {
    /**
     * Cabang milik user yang login, dibaca ulang dari tabel user seperti _header-artibut.php.
     */
    function laporanKategori_cabangUser($conn)
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

if (!function_exists('laporanKategori_wherePenjualan')) {
    /**
     * Predikat selektif di tabel penjualan (bukan invoice) supaya index cabang+tanggal terpakai.
     */
    function laporanKategori_wherePenjualan($conn, $alias, $cabang, $tanggalAwal, $tanggalAkhir)
    {
        $cabang   = (int) $cabang;
        $awalEsc  = mysqli_real_escape_string($conn, $tanggalAwal);
        $akhirEsc = mysqli_real_escape_string($conn, $tanggalAkhir);
        $p        = $alias === '' ? '' : $alias . '.';

        $where = "{$p}penjualan_cabang = {$cabang}
              AND {$p}penjualan_date BETWEEN '{$awalEsc}' AND '{$akhirEsc}'";

        return $where;
    }
}

if (!function_exists('laporanKategori_daftarKategori')) {
    /**
     * Dropdown kategori: baca tabel kategori per cabang, tanpa join 8.500 baris barang.
     */
    function laporanKategori_daftarKategori($conn, $cabang)
    {
        $cabang = (int) $cabang;
        $ambil = function ($cab) use ($conn) {
            $cab = (int) $cab;
            $rows = array();
            $res = mysqli_query($conn, "
                SELECT kategori_id, kategori_nama
                FROM kategori
                WHERE kategori_cabang = {$cab}
                ORDER BY kategori_nama ASC
            ");
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $rows[] = $row;
                }
            }

            return $rows;
        };

        $rows = $ambil($cabang);
        if (!$rows && $cabang !== 0) {
            $rows = $ambil(0);
        }

        return $rows;
    }
}

if (!function_exists('laporanKategori_ambilData')) {
    /**
     * @return array{
     *   rows: array<int, array<string, mixed>>,
     *   penjualan: float, hpp: float, laba: float,
     *   qty: float, produk: int, margin: float,
     *   margin_terbesar: float, transaksi: int
     * }
     */
    function laporanKategori_ambilData($conn, $cabang, $tanggalAwal, $tanggalAkhir, $kategoriId = 'semua', $urutkan = 'penjualan')
    {
        $cabang = (int) $cabang;
        $whereP = laporanKategori_wherePenjualan($conn, 'p', $cabang, $tanggalAwal, $tanggalAkhir);

        $whereKategori = '';
        if ($kategoriId !== 'semua' && $kategoriId !== '' && $kategoriId !== null) {
            $whereKategori = ' AND b.kategori_id = ' . (int) $kategoriId . ' ';
        }

        $orderBy = laporanKategori_urutan($urutkan);

        /*
         * 1) Agregasi hanya penjualan+barang (tanpa invoice, tanpa kategori).
         * 2) Nama kategori di-join setelah GROUP BY (~puluhan baris), jadi duplikat
         *    kategori_id antar cabang tidak menggandakan SUM.
         * COUNT(DISTINCT invoice) dihilangkan — tidak ditampilkan di tabel, dan
         * sangat mahal.
         */
        $sql = "
            SELECT
              agg.kategori_id,
              COALESCE(k.kategori_nama, '(Tanpa Kategori)') AS kategori_nama,
              agg.jml_produk,
              agg.qty,
              agg.penjualan,
              agg.hpp,
              (agg.penjualan - agg.hpp) AS laba_kotor,
              CASE
                WHEN agg.penjualan > 0 THEN (agg.penjualan - agg.hpp) / agg.penjualan * 100
                ELSE 0
              END AS margin
            FROM (
              SELECT
                COALESCE(b.kategori_id, 0) AS kategori_id,
                COUNT(DISTINCT p.barang_id) AS jml_produk,
                COALESCE(SUM(p.barang_qty_keranjang), 0) AS qty,
                COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) AS penjualan,
                COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS hpp
              FROM penjualan p
              INNER JOIN barang b ON b.barang_id = p.barang_id
              WHERE {$whereP}
                {$whereKategori}
              GROUP BY COALESCE(b.kategori_id, 0)
            ) agg
            LEFT JOIN (
              SELECT kategori_id, MAX(kategori_nama) AS kategori_nama
              FROM kategori
              GROUP BY kategori_id
            ) k ON k.kategori_id = agg.kategori_id
            ORDER BY {$orderBy}
        ";

        $rows = array();
        $totalPenjualan = 0.0;
        $totalHpp = 0.0;
        $totalQty = 0.0;
        $totalProduk = 0;
        $marginTerbesar = 0.0;

        $res = mysqli_query($conn, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
                $totalPenjualan += (float) $row['penjualan'];
                $totalHpp       += (float) $row['hpp'];
                $totalQty       += (float) $row['qty'];
                $totalProduk    += (int) $row['jml_produk'];
                $marginTerbesar = max($marginTerbesar, abs((float) $row['margin']));
            }
        }

        $totalLaba = $totalPenjualan - $totalHpp;

        return array(
            'rows'            => $rows,
            'penjualan'       => $totalPenjualan,
            'hpp'             => $totalHpp,
            'laba'            => $totalLaba,
            'qty'             => $totalQty,
            'produk'          => $totalProduk,
            'margin'          => $totalPenjualan > 0 ? ($totalLaba / $totalPenjualan) * 100 : 0.0,
            'margin_terbesar' => $marginTerbesar > 0 ? $marginTerbesar : 1.0,
            'transaksi'       => laporanKategori_totalTransaksi($conn, $cabang, $tanggalAwal, $tanggalAkhir),
        );
    }
}

if (!function_exists('laporanKategori_totalTransaksi')) {
    /**
     * Transaksi unik periode ini — dihitung dari penjualan (satu nota bisa banyak kategori).
     */
    function laporanKategori_totalTransaksi($conn, $cabang, $tanggalAwal, $tanggalAkhir)
    {
        $whereP = laporanKategori_wherePenjualan($conn, 'p', $cabang, $tanggalAwal, $tanggalAkhir);
        $res = mysqli_query($conn, "
            SELECT COUNT(DISTINCT p.penjualan_invoice) AS jml
            FROM penjualan p
            WHERE {$whereP}
        ");

        if ($res && ($row = mysqli_fetch_assoc($res))) {
            return (int) $row['jml'];
        }

        return 0;
    }
}

if (!function_exists('laporanKategori_normalisasiPeriode')) {
    /**
     * Default periode = bulan berjalan; tanggal terbalik otomatis ditukar.
     *
     * @return array{0: string, 1: string}
     */
    function laporanKategori_normalisasiPeriode($awal, $akhir)
    {
        $awal  = ($awal === null || $awal === '') ? date('Y-m-01') : (string) $awal;
        $akhir = ($akhir === null || $akhir === '') ? date('Y-m-d') : (string) $akhir;

        if (strtotime($awal) === false) {
            $awal = date('Y-m-01');
        }
        if (strtotime($akhir) === false) {
            $akhir = date('Y-m-d');
        }
        if (strtotime($awal) > strtotime($akhir)) {
            $tukar = $awal;
            $awal = $akhir;
            $akhir = $tukar;
        }

        return array($awal, $akhir);
    }
}
