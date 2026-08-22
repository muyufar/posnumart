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
     * Dropdown kategori: selalu master Nugrosir (kategori_cabang = 0).
     * Parameter $cabang diabaikan (tetap ada agar pemanggilan lama tidak rusak).
     */
    function laporanKategori_daftarKategori($conn, $cabang = 0)
    {
        $rows = array();
        $res = mysqli_query($conn, "
            SELECT kategori_id, kategori_nama
            FROM kategori
            WHERE kategori_cabang = 0
              AND kategori_status > 0
            ORDER BY kategori_nama ASC
        ");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('laporanKategori_nugrosir_kategori_expr')) {
    /**
     * Ekspresi SQL: resolve kategori barang ke ID master Nugrosir.
     * Butuh alias join: kl (kategori lokal) dan kn (kategori Nugrosir by nama).
     * Prioritas: barang_kategori_id → kn.kategori_id → kategori_id lokal.
     */
    function laporanKategori_nugrosir_kategori_expr($barangAlias = 'b')
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $barangAlias);
        if ($a === '') {
            $a = 'b';
        }

        return "COALESCE(NULLIF({$a}.barang_kategori_id, 0), kn.kategori_id, COALESCE({$a}.kategori_id, 0))";
    }
}

if (!function_exists('laporanKategori_joinKategoriNugrosir')) {
    /**
     * JOIN untuk mapping kategori lokal → Nugrosir by nama.
     */
    function laporanKategori_joinKategoriNugrosir($barangAlias = 'b')
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $barangAlias);
        if ($a === '') {
            $a = 'b';
        }

        return "
            LEFT JOIN kategori kl ON kl.kategori_id = {$a}.kategori_id
            LEFT JOIN kategori kn
              ON kn.kategori_cabang = 0
             AND kn.kategori_nama = kl.kategori_nama
        ";
    }
}

if (!function_exists('laporanKategori_nugrosir_kategori_ids')) {
    /**
     * ID kategori Nugrosir + ID kategori cabang lain dengan nama sama (untuk filter barang toko).
     *
     * @return int[]
     */
    function laporanKategori_nugrosir_kategori_ids($conn, $kategoriId)
    {
        $kategoriId = (int) $kategoriId;
        if ($kategoriId < 1) {
            return [];
        }
        $ids = [$kategoriId];
        $res = mysqli_query($conn, "SELECT kategori_nama FROM kategori WHERE kategori_id = {$kategoriId} LIMIT 1");
        $row = $res ? mysqli_fetch_assoc($res) : null;
        $nama = trim((string) ($row['kategori_nama'] ?? ''));
        if ($nama === '') {
            return $ids;
        }
        $namaEsc = mysqli_real_escape_string($conn, $nama);
        $res2 = mysqli_query($conn, "SELECT kategori_id FROM kategori WHERE kategori_nama = '{$namaEsc}'");
        if ($res2) {
            while ($r = mysqli_fetch_assoc($res2)) {
                $ids[] = (int) $r['kategori_id'];
            }
        }

        return array_values(array_unique(array_filter($ids, static function ($id) {
            return $id > 0;
        })));
    }
}

if (!function_exists('laporanKategori_whereBarangKategoriNugrosir')) {
    /**
     * Filter barang yang masuk kategori Nugrosir terpilih (termasuk alias nama di cabang toko).
     * Untuk $kategoriId = 0 (tanpa kategori), kembalikan string kosong — filter diquery lewat expr + join.
     */
    function laporanKategori_whereBarangKategoriNugrosir($conn, $kategoriId, $barangAlias = 'b')
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $barangAlias);
        if ($a === '') {
            $a = 'b';
        }
        $kategoriId = (int) $kategoriId;
        if ($kategoriId < 1) {
            $expr = laporanKategori_nugrosir_kategori_expr($a);
            return " AND ({$expr}) = 0 ";
        }
        $ids = laporanKategori_nugrosir_kategori_ids($conn, $kategoriId);
        if (!$ids) {
            return ' AND 1=0 ';
        }
        $in = implode(',', $ids);

        return " AND ({$a}.kategori_id IN ({$in}) OR {$a}.barang_kategori_id IN ({$in})) ";
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
     * @param bool $denganTransaksi hitung COUNT DISTINCT invoice (mahal); default false
     */
    function laporanKategori_ambilData($conn, $cabang, $tanggalAwal, $tanggalAkhir, $kategoriId = 'semua', $urutkan = 'penjualan', $denganTransaksi = false)
    {
        $cabang = (int) $cabang;
        $whereP = laporanKategori_wherePenjualan($conn, 'p', $cabang, $tanggalAwal, $tanggalAkhir);

        $whereKategori = '';
        if ($kategoriId !== 'semua' && $kategoriId !== '' && $kategoriId !== null) {
            $whereKategori = laporanKategori_whereBarangKategoriNugrosir($conn, (int) $kategoriId, 'b');
        }

        $orderBy = laporanKategori_urutan($urutkan);
        $katExpr = laporanKategori_nugrosir_kategori_expr('b');
        $katJoin = laporanKategori_joinKategoriNugrosir('b');

        /*
         * Agregasi per kategori master Nugrosir (cabang 0).
         * Nama hanya diambil dari kategori Nugrosir agar list tidak terpecah per cabang toko.
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
                ({$katExpr}) AS kategori_id,
                COUNT(DISTINCT p.barang_id) AS jml_produk,
                COALESCE(SUM(p.barang_qty_keranjang), 0) AS qty,
                COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) AS penjualan,
                COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS hpp
              FROM penjualan p
              INNER JOIN barang b ON b.barang_id = p.barang_id
              {$katJoin}
              WHERE {$whereP}
                {$whereKategori}
              GROUP BY ({$katExpr})
            ) agg
            LEFT JOIN kategori k
              ON k.kategori_id = agg.kategori_id
             AND k.kategori_cabang = 0
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
            'transaksi'       => $denganTransaksi
                ? laporanKategori_totalTransaksi($conn, $cabang, $tanggalAwal, $tanggalAkhir)
                : 0,
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

if (!function_exists('laporanKategori_namaKategori')) {
    function laporanKategori_namaKategori($conn, $kategoriId)
    {
        $kategoriId = (int) $kategoriId;
        if ($kategoriId < 1) {
            return '(Tanpa Kategori)';
        }
        $res = mysqli_query($conn, "
            SELECT kategori_nama
            FROM kategori
            WHERE kategori_id = {$kategoriId}
              AND kategori_cabang = 0
            LIMIT 1
        ");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $nama = trim((string) ($row['kategori_nama'] ?? ''));
            if ($nama !== '') {
                return $nama;
            }
        }
        // Fallback: ID lokal toko → cari nama yang sama di Nugrosir.
        $res2 = mysqli_query($conn, "
            SELECT kn.kategori_nama
            FROM kategori kl
            INNER JOIN kategori kn
              ON kn.kategori_cabang = 0
             AND kn.kategori_nama = kl.kategori_nama
            WHERE kl.kategori_id = {$kategoriId}
            LIMIT 1
        ");
        if ($res2 && ($row2 = mysqli_fetch_assoc($res2))) {
            $nama2 = trim((string) ($row2['kategori_nama'] ?? ''));
            if ($nama2 !== '') {
                return $nama2;
            }
        }

        return 'Kategori #' . $kategoriId;
    }
}

if (!function_exists('laporanKategori_urutanBarang')) {
    function laporanKategori_urutanBarang($urutkan)
    {
        $map = array(
            'laba'      => 'laba_kotor ASC',
            'laba_desc' => 'laba_kotor DESC',
            'penjualan' => 'penjualan DESC',
            'margin'    => 'margin ASC',
            'qty'       => 'qty DESC',
            'nama'      => 'barang_nama ASC',
            'nama_desc' => 'barang_nama DESC',
        );

        return isset($map[$urutkan]) ? $map[$urutkan] : $map['laba'];
    }
}

if (!function_exists('laporanKategori_ambilDataBarang')) {
    /**
     * Rincian penjualan per barang dalam satu kategori (untuk deteksi rugi/untung).
     *
     * @param string $statusFilter semua|rugi|untung|impas
     * @return array{
     *   rows: array<int, array<string, mixed>>,
     *   penjualan: float, hpp: float, laba: float,
     *   qty: float, produk: int, margin: float,
     *   margin_terbesar: float, jml_rugi: int, jml_untung: int
     * }
     */
    function laporanKategori_ambilDataBarang(
        $conn,
        $cabang,
        $tanggalAwal,
        $tanggalAkhir,
        $kategoriId,
        $statusFilter = 'semua',
        $urutkan = 'laba'
    ) {
        $cabang = (int) $cabang;
        $kategoriId = (int) $kategoriId;
        $whereP = laporanKategori_wherePenjualan($conn, 'p', $cabang, $tanggalAwal, $tanggalAkhir);
        $orderBy = laporanKategori_urutanBarang($urutkan);
        $whereKategori = laporanKategori_whereBarangKategoriNugrosir($conn, $kategoriId, 'b');
        $katJoin = laporanKategori_joinKategoriNugrosir('b');

        // HAVING status di SQL agar filter rugi tidak memproses semua baris di PHP.
        $havingStatus = '';
        if ($statusFilter === 'rugi') {
            $havingStatus = ' HAVING laba_kotor < 0 ';
        } elseif ($statusFilter === 'untung') {
            $havingStatus = ' HAVING laba_kotor > 0 ';
        } elseif ($statusFilter === 'impas') {
            $havingStatus = ' HAVING laba_kotor = 0 ';
        }

        $sql = "
            SELECT
              p.barang_id,
              MAX(b.barang_kode) AS barang_kode,
              MAX(b.barang_nama) AS barang_nama,
              MAX(IFNULL(b.kode_suplier, '')) AS kode_suplier,
              COUNT(DISTINCT p.penjualan_invoice) AS jml_transaksi,
              COALESCE(SUM(p.barang_qty_keranjang), 0) AS qty,
              COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) AS penjualan,
              COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS hpp,
              (COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0)
                - COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0)) AS laba_kotor,
              CASE
                WHEN COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) > 0
                  THEN (
                    COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0)
                    - COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0)
                  ) / COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) * 100
                ELSE 0
              END AS margin
            FROM penjualan p
            INNER JOIN barang b ON b.barang_id = p.barang_id
            {$katJoin}
            WHERE {$whereP}
              {$whereKategori}
            GROUP BY p.barang_id
            {$havingStatus}
            ORDER BY {$orderBy}
        ";

        $rows = array();
        $totalPenjualan = 0.0;
        $totalHpp = 0.0;
        $totalQty = 0.0;
        $jmlRugi = 0;
        $jmlUntung = 0;
        $marginTerbesar = 0.0;

        // Hitung ringkas status dari query ringan (tanpa filter status).
        $sqlCount = "
            SELECT
              SUM(CASE WHEN x.laba_kotor < 0 THEN 1 ELSE 0 END) AS jml_rugi,
              SUM(CASE WHEN x.laba_kotor > 0 THEN 1 ELSE 0 END) AS jml_untung
            FROM (
              SELECT
                (COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0)
                  - COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0)) AS laba_kotor
              FROM penjualan p
              INNER JOIN barang b ON b.barang_id = p.barang_id
              {$katJoin}
              WHERE {$whereP}
                {$whereKategori}
              GROUP BY p.barang_id
            ) x
        ";
        $resCount = mysqli_query($conn, $sqlCount);
        if ($resCount && ($rowC = mysqli_fetch_assoc($resCount))) {
            $jmlRugi = (int) ($rowC['jml_rugi'] ?? 0);
            $jmlUntung = (int) ($rowC['jml_untung'] ?? 0);
        }

        $res = mysqli_query($conn, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
                $totalPenjualan += (float) $row['penjualan'];
                $totalHpp       += (float) $row['hpp'];
                $totalQty       += (float) $row['qty'];
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
            'produk'          => count($rows),
            'margin'          => $totalPenjualan > 0 ? ($totalLaba / $totalPenjualan) * 100 : 0.0,
            'margin_terbesar' => $marginTerbesar > 0 ? $marginTerbesar : 1.0,
            'jml_rugi'        => $jmlRugi,
            'jml_untung'      => $jmlUntung,
        );
    }
}

if (!function_exists('laporanKategori_ambilDataTransaksiBarang')) {
    /**
     * Baris penjualan (per nota) untuk satu barang — deteksi transaksi rugi.
     *
     * @param string $statusFilter semua|rugi|untung|impas
     * @return array{
     *   rows: array<int, array<string, mixed>>,
     *   penjualan: float, hpp: float, laba: float,
     *   qty: float, transaksi: int, margin: float,
     *   jml_rugi: int, jml_untung: int,
     *   barang: ?array<string, mixed>
     * }
     */
    function laporanKategori_ambilDataTransaksiBarang(
        $conn,
        $cabang,
        $tanggalAwal,
        $tanggalAkhir,
        $barangId,
        $statusFilter = 'semua'
    ) {
        $cabang = (int) $cabang;
        $barangId = (int) $barangId;
        $whereP = laporanKategori_wherePenjualan($conn, 'p', $cabang, $tanggalAwal, $tanggalAkhir);

        $barang = null;
        $resB = mysqli_query($conn, "
            SELECT barang_id, barang_kode, barang_nama, IFNULL(kode_suplier, '') AS kode_suplier,
                   COALESCE(kategori_id, 0) AS kategori_id
            FROM barang
            WHERE barang_id = {$barangId}
            LIMIT 1
        ");
        if ($resB && ($rowB = mysqli_fetch_assoc($resB))) {
            $barang = $rowB;
        }

        $sql = "
            SELECT
              p.penjualan_id,
              p.penjualan_invoice,
              p.penjualan_date,
              p.barang_qty,
              p.barang_qty_keranjang,
              p.keranjang_satuan,
              p.keranjang_harga,
              p.keranjang_harga_beli,
              (p.barang_qty * p.keranjang_harga) AS penjualan,
              (p.barang_qty_keranjang * p.keranjang_harga_beli) AS hpp,
              ((p.barang_qty * p.keranjang_harga)
                - (p.barang_qty_keranjang * p.keranjang_harga_beli)) AS laba_kotor,
              CASE
                WHEN (p.barang_qty * p.keranjang_harga) > 0
                  THEN (
                    ((p.barang_qty * p.keranjang_harga)
                      - (p.barang_qty_keranjang * p.keranjang_harga_beli))
                    / (p.barang_qty * p.keranjang_harga) * 100
                  )
                ELSE 0
              END AS margin,
              i.invoice_id
            FROM penjualan p
            LEFT JOIN invoice i
              ON i.penjualan_invoice = p.penjualan_invoice
             AND i.invoice_cabang = p.penjualan_cabang
            WHERE {$whereP}
              AND p.barang_id = {$barangId}
            ORDER BY laba_kotor ASC, p.penjualan_date DESC, p.penjualan_id DESC
        ";

        $rows = array();
        $totalPenjualan = 0.0;
        $totalHpp = 0.0;
        $totalQty = 0.0;
        $jmlRugi = 0;
        $jmlUntung = 0;

        $res = mysqli_query($conn, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $laba = (float) $row['laba_kotor'];
                if ($laba < 0) {
                    $jmlRugi++;
                } elseif ($laba > 0) {
                    $jmlUntung++;
                }

                if ($statusFilter === 'rugi' && $laba >= 0) {
                    continue;
                }
                if ($statusFilter === 'untung' && $laba <= 0) {
                    continue;
                }
                if ($statusFilter === 'impas' && $laba != 0.0) {
                    continue;
                }

                $rows[] = $row;
                $totalPenjualan += (float) $row['penjualan'];
                $totalHpp       += (float) $row['hpp'];
                $totalQty       += (float) $row['barang_qty_keranjang'];
            }
        }

        $totalLaba = $totalPenjualan - $totalHpp;

        return array(
            'rows'       => $rows,
            'penjualan'  => $totalPenjualan,
            'hpp'        => $totalHpp,
            'laba'       => $totalLaba,
            'qty'        => $totalQty,
            'transaksi'  => count($rows),
            'margin'     => $totalPenjualan > 0 ? ($totalLaba / $totalPenjualan) * 100 : 0.0,
            'jml_rugi'   => $jmlRugi,
            'jml_untung' => $jmlUntung,
            'barang'     => $barang,
        );
    }
}
