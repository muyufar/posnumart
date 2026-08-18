<?php
/**
 * Data laporan penjualan per kategori — dipakai laporan-penjualan-kategori.php
 * dan export-penjualan-kategori-excel.php agar angkanya selalu identik.
 *
 * Rumus per baris penjualan (sudah dicocokkan dengan total invoice):
 *   omzet = barang_qty * keranjang_harga                  -> selaras invoice_sub_total
 *   hpp   = barang_qty_keranjang * keranjang_harga_beli    -> selaras invoice_total_beli
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

if (!function_exists('laporanKategori_daftarKategori')) {
    /**
     * Kategori yang benar-benar dipakai barang cabang ini.
     * Baris kategori bisa milik cabang lain, jadi disaring lewat barang.
     */
    function laporanKategori_daftarKategori($conn, $cabang)
    {
        $cabang = (int) $cabang;
        $sql = "
            SELECT DISTINCT k.kategori_id, k.kategori_nama
            FROM kategori k
            INNER JOIN barang b ON b.kategori_id = k.kategori_id
            WHERE b.barang_cabang = {$cabang}
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
        $awalEsc  = mysqli_real_escape_string($conn, $tanggalAwal);
        $akhirEsc = mysqli_real_escape_string($conn, $tanggalAkhir);

        $whereKategori = '';
        if ($kategoriId !== 'semua' && $kategoriId !== '' && $kategoriId !== null) {
            $whereKategori = ' AND b.kategori_id = ' . (int) $kategoriId . ' ';
        }

        $orderBy = laporanKategori_urutan($urutkan);

        $sql = "
            SELECT
              COALESCE(k.kategori_id, 0)                                        AS kategori_id,
              COALESCE(k.kategori_nama, '(Tanpa Kategori)')                     AS kategori_nama,
              COUNT(DISTINCT i.invoice_id)                                      AS jml_transaksi,
              COUNT(DISTINCT b.barang_id)                                       AS jml_produk,
              COALESCE(SUM(p.barang_qty_keranjang), 0)                          AS qty,
              COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0)                AS penjualan,
              COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS hpp,
              COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0)
                - COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS laba_kotor,
              CASE
                WHEN COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) > 0
                THEN (
                  COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0)
                  - COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0)
                ) / COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) * 100
                ELSE 0
              END                                                               AS margin
            FROM penjualan p
            INNER JOIN invoice i
              ON i.penjualan_invoice = p.penjualan_invoice
             AND i.invoice_cabang    = p.penjualan_cabang
            INNER JOIN barang b ON b.barang_id = p.barang_id
            LEFT JOIN kategori k ON k.kategori_id = b.kategori_id
            WHERE i.invoice_cabang = {$cabang}
              AND i.invoice_date BETWEEN '{$awalEsc}' AND '{$akhirEsc}'
              {$whereKategori}
            GROUP BY COALESCE(k.kategori_id, 0), COALESCE(k.kategori_nama, '(Tanpa Kategori)')
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
     * Transaksi unik periode ini — tidak bisa dijumlah dari baris kategori
     * karena satu invoice bisa memuat beberapa kategori.
     */
    function laporanKategori_totalTransaksi($conn, $cabang, $tanggalAwal, $tanggalAkhir)
    {
        $cabang = (int) $cabang;
        $awalEsc  = mysqli_real_escape_string($conn, $tanggalAwal);
        $akhirEsc = mysqli_real_escape_string($conn, $tanggalAkhir);

        $res = mysqli_query($conn, "
            SELECT COUNT(DISTINCT i.invoice_id) AS jml
            FROM invoice i
            WHERE i.invoice_cabang = {$cabang}
              AND i.invoice_date BETWEEN '{$awalEsc}' AND '{$akhirEsc}'
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
