<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
include 'aksi/koneksi.php';
require_once 'aksi/stock-opname-laporan-lib.php';

if ($levelLogin != "admin" && $levelLogin != "super admin") {
  echo "<script>document.location.href = 'bo';</script>";
  exit;
}

$listCabang = query("SELECT * FROM toko");

$tanggal_awal = $_POST['tanggal_awal'] ?? date('Y-m-01');
$tanggal_akhir = $_POST['tanggal_akhir'] ?? date('Y-m-t');
if (strtotime($tanggal_awal) > strtotime($tanggal_akhir)) {
  $tmpTgl = $tanggal_awal;
  $tanggal_awal = $tanggal_akhir;
  $tanggal_akhir = $tmpTgl;
}
$user_cabang_login = (int) ($_SESSION['user_cabang'] ?? 0);
/** Cabang selain 0: filter cabang dikunci ke cabang login; pusat/gudang (0) boleh pilih cabang. */
$cabang_filter_terkunci = ($user_cabang_login !== 0);
if ($cabang_filter_terkunci) {
  $cabang = (string) $user_cabang_login;
} else {
  $cabang = isset($_POST['cabang']) ? $_POST['cabang'] : ($_SESSION['user_cabang'] ?? '0');
}
$bulan_pilih = substr($tanggal_awal, 0, 7);

function rupiah($angka)
{
  return 'Rp ' . number_format($angka, 0, ',', '.');
}

/** Huruf penomoran: 0=a, 25=z, 26=aa (tanpa batas jumlah baris) */
function labaAccrual_indexToLetter($index)
{
  $s = '';
  $i = (int) $index;
  while ($i >= 0) {
    $s = chr($i % 26 + ord('a')) . $s;
    $i = intdiv($i, 26) - 1;
  }
  return $s;
}

/** Beban keuangan/bunga/admin bank â†’ kelompok Beban Lain (COA 9-xxxx + cadangan nama) */
function labaAccrual_isBebanLainFinansial($kode_akun, $nama_kategori)
{
  $k = strtoupper(trim((string) $kode_akun));
  if ($k !== '' && preg_match('/^9-/', $k)) {
    return true;
  }
  $n = strtolower((string) $nama_kategori);
  $keywords = ['beban bunga', 'bunga bank', 'bunga rk', 'administrasi bank', 'admin bank', 'beban pinjaman', 'provisi', 'beban provisi', 'biaya bank'];
  foreach ($keywords as $kw) {
    if ($n !== '' && strpos($n, $kw) !== false) {
      return true;
    }
  }
  return false;
}

/**
 * Hitung total persediaan barang awal (accrual) dari tabel pembelian.
 *
 * Logika:
 * - Ambil data pembelian per cabang dan rentang tanggal.
 * - Per barang_id: nilai tiap transaksi = barang_qty * barang_harga_beli.
 * - Jika satu barang_id punya banyak transaksi: total_nilai = SUM(nilai), jumlah_transaksi = COUNT(*),
 *   lalu rata_rata = total_nilai / jumlah_transaksi (satu angka per barang).
 * - Total persediaan awal = jumlah semua rata_rata per barang_id.
 *
 * @param mysqli $conn Koneksi database
 * @param string|int $cabang Kode cabang (pembelian_cabang)
 * @param string $tanggal_awal Format Y-m-d
 * @param string $tanggal_akhir Format Y-m-d
 * @return float Total persediaan barang awal
 */
function hitungPersediaanAwalDariPembelian($conn, $cabang, $tanggal_awal, $tanggal_akhir)
{
  $cabang = mysqli_real_escape_string($conn, $cabang);
  $tanggal_awal = mysqli_real_escape_string($conn, $tanggal_awal);
  $tanggal_akhir = mysqli_real_escape_string($conn, $tanggal_akhir);

  $sql = "
    SELECT COALESCE(SUM(rata_rata), 0) AS total_persediaan_awal
    FROM (
      SELECT
        barang_id,
        SUM(barang_qty * barang_harga_beli) AS total_nilai_transaksi,
        COUNT(*) AS jumlah_transaksi,
        SUM(barang_qty * barang_harga_beli) / COUNT(*) AS rata_rata
      FROM pembelian
      WHERE pembelian_cabang = '$cabang'
        AND pembelian_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
      GROUP BY barang_id
    ) AS per_barang
  ";
  
  $queryhitung = "SELECT 
    COALESCE(SUM(barang_qty * barang_harga_beli), 0) AS total_persediaan_awal
FROM pembelian
WHERE pembelian_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir' AND pembelian_cabang = '$cabang'";

  $q = mysqli_query($conn, $queryhitung);
  if (!$q) {
    return 0;
  }
  $row = mysqli_fetch_assoc($q);
  return (float) ($row['total_persediaan_awal'] ?? 0);
}

// Ambil data toko
$toko = query("SELECT * FROM toko WHERE toko_cabang = '$cabang' ")[0];

$cabang_int_ringkasan = (int) $cabang;
$label_laba_operasi_display = 'Laba Operasi';
$label_laba_bersih_akhir_display = 'Laba Bersih';
if ($cabang_int_ringkasan !== 0) {
  $toko_kota_ringkasan = trim((string) ($toko['toko_kota'] ?? ''));
  if ($toko_kota_ringkasan !== '') {
    $label_laba_operasi_display = 'Laba Operasi ' . $toko_kota_ringkasan;
    $label_laba_bersih_akhir_display = 'Laba Bersih ' . $toko_kota_ringkasan;
  }
}

/* -------------------------------------------
   0. PERSEDIAAN BARANG
   
   CABANG 0 (PUSAT):
   - Persediaan Awal = Total Pembelian (invoice_pembelian)
   - Persediaan Akhir = Pembelian - HPP - Transfer Stock ke cabang lain
   
   CABANG LAIN (1,2,3,dst):
   - Persediaan Awal = Total Transfer Stock yang diterima dari Cabang 0
   - Persediaan Akhir = Transfer Stock - HPP (penjualan)
------------------------------------------- */

/* Tanggal rekonstruksi persediaan:
   - Awal  = akhir hari sebelum periode mulai  (misal: pilih Feb â†’ 31 Jan)
   - Akhir = hari terakhir periode             (misal: pilih Feb â†’ 28/29 Feb)
*/
$tgl_sebelum_awal = date('Y-m-d', strtotime($tanggal_awal . ' -1 day'));

/**
 * Nilai persediaan (stok aktif Ã— harga beli) pada akhir $tanggal.
 * Rekonstruksi mundur langsung ke tanggal tersebut â€” satu query, andal.
 */
function hitungPersediaanAkumulasi($conn, int $cabang, string $tanggal): float
{
    return so_laporan_nilai_persediaan_pada_tanggal($conn, $cabang, $tanggal);
}

if ($cabang == 0) {
  // CABANG 0 (NUGROSIR/GUDANG)
  $persediaan_awal  = hitungPersediaanAkumulasi($conn, 0, $tgl_sebelum_awal);
  $persediaan_label = "Nilai Persediaan Awal (akhir " . date('d/m/Y', strtotime($tgl_sebelum_awal)) . ")";

  // Komponen mutasi untuk cabang 0 (dipakai di rumus persediaan akhir)
  $total_pembelian_masuk = 0;
  $total_retur_pembelian = 0;
  $total_transfer_balik = 0;
  $total_retur_penjualan = 0;
  $total_barang_hilang = 0;

  // Pembelian & Retur Pembelian (jika qty negatif)
  $q_pb = mysqli_query($conn, "
    SELECT
      COALESCE(SUM(CASE WHEN barang_qty > 0 THEN barang_qty * barang_harga_beli ELSE 0 END), 0) AS pembelian_masuk,
      COALESCE(SUM(CASE WHEN barang_qty < 0 THEN ABS(barang_qty) * barang_harga_beli ELSE 0 END), 0) AS retur_pembelian
    FROM pembelian
    WHERE pembelian_cabang = 0
      AND pembelian_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
  ");
  if ($q_pb && ($r = mysqli_fetch_assoc($q_pb))) {
    $total_pembelian_masuk = (float) ($r['pembelian_masuk'] ?? 0);
    $total_retur_pembelian = (float) ($r['retur_pembelian'] ?? 0);
  }

  // Transfer balik (TF masuk ke gudang/pusat)
  $q_tf_balik = mysqli_query($conn, "
    SELECT COALESCE(SUM(tpm.tpm_qty * (CASE WHEN b.barang_harga_beli_rata > 0 THEN b.barang_harga_beli_rata ELSE b.barang_harga_beli END)), 0) AS total
    FROM transfer_produk_masuk tpm
    JOIN barang b ON b.barang_kode_slug = tpm.tpm_kode_slug AND b.barang_cabang = 0
    WHERE tpm.tpm_penerima_cabang = 0
      AND tpm.tpm_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
  ");
  if ($q_tf_balik && ($r = mysqli_fetch_assoc($q_tf_balik))) {
    $total_transfer_balik = (float) ($r['total'] ?? 0);
  }

  // Retur penjualan (barang kembali dari penjualan cabang 0)
  $q_retur_jual = mysqli_query($conn, "
    SELECT COALESCE(SUM(r.barang_stock * (CASE WHEN b.barang_harga_beli_rata > 0 THEN b.barang_harga_beli_rata ELSE b.barang_harga_beli END)), 0) AS total
    FROM retur r
    JOIN invoice i ON i.penjualan_invoice = r.retur_invoice AND i.invoice_cabang = 0
    JOIN barang b ON CAST(r.retur_barang_id AS UNSIGNED) = b.barang_id AND b.barang_cabang = 0
    WHERE r.retur_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
  ");
  if ($q_retur_jual && ($r = mysqli_fetch_assoc($q_retur_jual))) {
    $total_retur_penjualan = (float) ($r['total'] ?? 0);
  }

  // Barang hilang (diambil dari akun beban kehilangan barang 6-3200, jika dipakai)
  $q_hilang = mysqli_query($conn, "
    SELECT COALESCE(SUM(CAST(REPLACE(REPLACE(l.jumlah, '.', ''), ',', '') AS DECIMAL(18,2))), 0) AS total
    FROM laba l
    LEFT JOIN laba_kategori lk ON (
      CAST(l.kategori AS UNSIGNED) = lk.id
      OR TRIM(COALESCE(l.kategori, '')) = TRIM(COALESCE(lk.kode_akun, ''))
    )
    WHERE l.tipe = 1
      AND l.cabang = 0
      AND l.date >= '$tanggal_awal 00:00:00'
      AND l.date <= '$tanggal_akhir 23:59:59'
      AND TRIM(COALESCE(lk.kode_akun, '')) = '6-3200'
  ");
  if ($q_hilang && ($r = mysqli_fetch_assoc($q_hilang))) {
    $total_barang_hilang = (float) ($r['total'] ?? 0);
  }
} else {
  // CABANG NUMART (selain 0)
  $cabang_int = (int) $cabang;

  $persediaan_awal  = hitungPersediaanAkumulasi($conn, $cabang_int, $tgl_sebelum_awal);
  $persediaan_label = "Nilai Persediaan Awal (akhir " . date('d/m/Y', strtotime($tgl_sebelum_awal)) . ")";

  // Mutasi periode untuk cabang numart:
  // + transfer stock masuk (dari NUGrosir/pusat dan numart lain)
  // + retur penjualan
  // - transfer stock balik
  $total_transfer_masuk = 0;
  $total_transfer_balik = 0;
  $total_retur_penjualan = 0;

  // Transfer masuk â€” sumber bersih: transfer_produk_keluar (tpk_penerima_cabang).
  // JOIN via tpk_kode_slug karena tpk_barang_id adalah ID barang cabang PENGIRIM.
  $q_tf_masuk = mysqli_query($conn, "
    SELECT COALESCE(SUM(tpk.tpk_qty * (CASE WHEN b.barang_harga_beli_rata > 0 THEN b.barang_harga_beli_rata ELSE b.barang_harga_beli END)), 0) AS total
    FROM transfer_produk_keluar tpk
    JOIN barang b ON tpk.tpk_kode_slug = b.barang_kode_slug AND b.barang_cabang = $cabang_int
    WHERE tpk.tpk_penerima_cabang = $cabang_int
      AND tpk.tpk_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
  ");
  if ($q_tf_masuk && ($r = mysqli_fetch_assoc($q_tf_masuk))) {
    $total_transfer_masuk = (float) ($r['total'] ?? 0);
  }

  // Transfer balik (keluar dari cabang ini ke cabang lain, termasuk balik ke gudang)
  $q_tf_balik = mysqli_query($conn, "
    SELECT COALESCE(SUM(tpk.tpk_qty * (CASE WHEN b.barang_harga_beli_rata > 0 THEN b.barang_harga_beli_rata ELSE b.barang_harga_beli END)), 0) AS total
    FROM transfer_produk_keluar tpk
    JOIN barang b ON tpk.tpk_barang_id = b.barang_id AND b.barang_cabang = $cabang_int
    WHERE tpk.tpk_pengirim_cabang = $cabang_int
      AND tpk.tpk_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
  ");
  if ($q_tf_balik && ($r = mysqli_fetch_assoc($q_tf_balik))) {
    $total_transfer_balik = (float) ($r['total'] ?? 0);
  }

  // Retur penjualan (barang kembali dari penjualan cabang ini)
  $q_retur_jual = mysqli_query($conn, "
    SELECT COALESCE(SUM(r.barang_stock * (CASE WHEN b.barang_harga_beli_rata > 0 THEN b.barang_harga_beli_rata ELSE b.barang_harga_beli END)), 0) AS total
    FROM retur r
    JOIN invoice i ON i.penjualan_invoice = r.retur_invoice AND i.invoice_cabang = $cabang_int
    JOIN barang b ON CAST(r.retur_barang_id AS UNSIGNED) = b.barang_id AND b.barang_cabang = $cabang_int
    WHERE r.retur_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
  ");
  if ($q_retur_jual && ($r = mysqli_fetch_assoc($q_retur_jual))) {
    $total_retur_penjualan = (float) ($r['total'] ?? 0);
  }

  // Variabel lain tidak dipakai untuk rumus cabang numart, tapi tetap diset agar aman dipakai di bagian lain.
  $total_pembelian_masuk = 0;
  $total_retur_pembelian = 0;
  $total_barang_hilang = 0;
}

/* -------------------------------------------
   1. PENJUALAN
   CATATAN: DP Kredit dihitung sebagai bagian dari Penjualan Cash
   - Penjualan Cash = penjualan cash biasa + DP dari penjualan kredit
   - Penjualan Kredit = total penjualan kredit - DP yang sudah dibayar
------------------------------------------- */
$q_penjualan = mysqli_query($conn, "
  SELECT 
    SUM(CASE WHEN invoice_piutang = 0 THEN invoice_sub_total ELSE 0 END) AS total_cash_biasa,
    SUM(CASE WHEN invoice_piutang = 1 THEN invoice_sub_total ELSE 0 END) AS total_kredit_penuh,
    SUM(CASE WHEN invoice_piutang = 1 THEN invoice_piutang_dp ELSE 0 END) AS total_dp,
    SUM(invoice_sub_total) AS total_penjualan
  FROM invoice
  WHERE invoice_cabang = '$cabang'
  AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
");
$penjualan = mysqli_fetch_assoc($q_penjualan);
$total_cash_biasa = $penjualan['total_cash_biasa'] ?? 0;
$total_kredit_penuh = $penjualan['total_kredit_penuh'] ?? 0;
$total_dp = $penjualan['total_dp'] ?? 0;
$total_penjualan = $penjualan['total_penjualan'] ?? 0;

// Hitung penjualan cash (cash biasa + DP) dan penjualan kredit (kredit - DP)
$total_cash = $total_cash_biasa + $total_dp;
$total_kredit = $total_kredit_penuh - $total_dp;

/* -------------------------------------------
   3. HPP
------------------------------------------- */
$q_hpp = mysqli_query($conn, "
  SELECT SUM(invoice_total_beli) AS total_hpp
  FROM invoice
  WHERE invoice_cabang = '$cabang'
  AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
");
$hpp = mysqli_fetch_assoc($q_hpp)['total_hpp'] ?? 0;

// Persediaan Akhir akan dihitung setelah total_transfer_stok tersedia (untuk cabang 0)

/* -------------------------------------------
   4. Pendapatan Lain-lain dari tabel laba (laba.tipe = 0)
   Semua kategori yang terhubung ke COA berawalan 8- (kelompok pendapatan lain / non-penjualan).
   CATATAN: Menggunakan l.date (tanggal transaksi), BUKAN created_at.
------------------------------------------- */
$pendapatan_lain_prefix_kode = '8-';
$pendapatan_lain_like_sql = mysqli_real_escape_string($conn, $pendapatan_lain_prefix_kode) . '%';

$pendapatan_lain = [];
$total_pendapatan_lain = 0;

$q_pendapatan_lain = mysqli_query($conn, "
  SELECT 
    COALESCE(lk_kredit.name, lk.name, 'Tanpa Kategori') AS kategori_nama,
    SUM(CAST(REPLACE(REPLACE(l.jumlah, '.', ''), ',', '') AS DECIMAL(18,2))) AS total 
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
  AND l.cabang = '$cabang'
  AND l.date >= '$tanggal_awal 00:00:00'
  AND l.date <= '$tanggal_akhir 23:59:59'
  AND (
    TRIM(COALESCE(lk_kredit.kode_akun, '')) LIKE '$pendapatan_lain_like_sql'
    OR TRIM(COALESCE(lk.kode_akun, '')) LIKE '$pendapatan_lain_like_sql'
  )
  GROUP BY COALESCE(lk_kredit.name, lk.name, 'Tanpa Kategori')
  ORDER BY COALESCE(lk_kredit.name, lk.name, 'Tanpa Kategori')
");
if ($q_pendapatan_lain) {
  while ($row = mysqli_fetch_assoc($q_pendapatan_lain)) {
    $pendapatan_lain[] = $row;
    $total_pendapatan_lain += $row['total'];
  }
} else {
  error_log('pendapatan lain (COA 8-): ' . mysqli_error($conn));
}

/* -------------------------------------------
   5. Pengeluaran / Beban Operasi (laba.tipe = 1)
   CATATAN: 
   - Menggunakan l.date (tanggal transaksi dilakukan), BUKAN created_at (tanggal dibuat)
   - Hanya kategori dengan label 'beban' yang masuk ke Beban Operasi
------------------------------------------- */
$q_pengeluaran = mysqli_query($conn, "
  SELECT 
    COALESCE(lk.name, 'Tanpa Kategori') AS kategori_nama,
    MAX(COALESCE(lk.kode_akun, '')) AS kode_akun,
    SUM(CAST(REPLACE(REPLACE(l.jumlah, '.', ''), ',', '') AS DECIMAL(18,2))) AS total 
  FROM laba l
  LEFT JOIN laba_kategori lk ON CAST(l.kategori AS UNSIGNED) = lk.id
  WHERE l.tipe = 1
  AND l.cabang = '$cabang'
  AND l.date >= '$tanggal_awal 00:00:00'
  AND l.date <= '$tanggal_akhir 23:59:59'
  AND lk.kategori = 'beban'
  GROUP BY lk.name
  ORDER BY lk.name
");
$pengeluaran = [];
$pengeluaran_operasional = [];
$pengeluaran_lain = [];
$total_pengeluaran = 0;
$total_beban_operasional = 0;
$total_beban_lain_finansial = 0;
while ($row = mysqli_fetch_assoc($q_pengeluaran)) {
  $pengeluaran[] = $row;
  $total_pengeluaran += $row['total'];
  if (labaAccrual_isBebanLainFinansial($row['kode_akun'] ?? '', $row['kategori_nama'] ?? '')) {
    $pengeluaran_lain[] = $row;
    $total_beban_lain_finansial += $row['total'];
  } else {
    $pengeluaran_operasional[] = $row;
    $total_beban_operasional += $row['total'];
  }
}

/* -------------------------------------------
   6. Sharing Profit (khusus cabang 0)
   CATATAN: Hanya menghitung beban operasi (kategori 'beban')
------------------------------------------- */
// Sharing Profit tidak dipakai di laporan ini karena sudah terwakili pada bagian
// "Pendapatan Bagi Hasil". Jadi diset 0 agar tidak mempengaruhi ringkasan.
$sharing_profit = 0;
$sharing_detail = [];

/* -------------------------------------------
   7. Pendapatan Lain (Bagi Hasil dari Cabang)
   CATATAN: 
   - 45% dari laba bersih Numart Dukun, 50% dari laba bersih Pondok Srumbung
   - 30% dari laba bersih Numart Tren Pakis, 45% dari laba bersih Numart Tegalrejo
   - Laba Bersih = Total Pendapatan - HPP - Total Beban Operasi (hanya kategori 'beban')
------------------------------------------- */
$pendapatan_lain_bagi_hasil = 0;
$pendapatan_lain_detail = [];

if ($cabang == 0) {
  // Hitung laba bersih Numart Dukun (Cabang 1)
  $q_laba_bersih_cbg1 = mysqli_query($conn, "
    SELECT 
      (SUM(invoice_sub_total) 
       - SUM(invoice_total_beli)
       - COALESCE((
          SELECT SUM(CAST(REPLACE(REPLACE(l2.jumlah, '.', ''), ',', '') AS DECIMAL(18,2))) 
          FROM laba l2 
          LEFT JOIN laba_kategori lk2 ON CAST(l2.kategori AS UNSIGNED) = lk2.id
          WHERE l2.tipe = 1 
          AND l2.cabang = 1 
          AND l2.date >= '$tanggal_awal 00:00:00'
          AND l2.date <= '$tanggal_akhir 23:59:59'
          AND lk2.kategori = 'beban'
        ),0)
      ) AS laba_bersih_cabang1
    FROM invoice
    WHERE invoice_cabang = 1
    AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
  ");
  $laba_bersih_cbg1 = mysqli_fetch_assoc($q_laba_bersih_cbg1)['laba_bersih_cabang1'] ?? 0;
  $bagi_hasil_cbg1 = $laba_bersih_cbg1 * 0.45;
  $pendapatan_lain_bagi_hasil += $bagi_hasil_cbg1;
  $pendapatan_lain_detail[] = [
    'nama' => 'Bagi Hasil Numart Dukun (45%)',
    'nilai' => $bagi_hasil_cbg1
  ];

  // Hitung laba bersih Numart Pondok Srumbung (Cabang 3)
  $q_laba_bersih_cbg3 = mysqli_query($conn, "
    SELECT 
      (SUM(invoice_sub_total) 
       - SUM(invoice_total_beli)
       - COALESCE((
          SELECT SUM(CAST(REPLACE(REPLACE(l2.jumlah, '.', ''), ',', '') AS DECIMAL(18,2))) 
          FROM laba l2 
          LEFT JOIN laba_kategori lk2 ON CAST(l2.kategori AS UNSIGNED) = lk2.id
          WHERE l2.tipe = 1 
          AND l2.cabang = 3 
          AND l2.date >= '$tanggal_awal 00:00:00'
          AND l2.date <= '$tanggal_akhir 23:59:59'
          AND lk2.kategori = 'beban'
        ),0)
      ) AS laba_bersih_cabang3
    FROM invoice
    WHERE invoice_cabang = 3
    AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
  ");
  $laba_bersih_cbg3 = mysqli_fetch_assoc($q_laba_bersih_cbg3)['laba_bersih_cabang3'] ?? 0;
  $bagi_hasil_cbg3 = $laba_bersih_cbg3 * 0.50;
  $pendapatan_lain_bagi_hasil += $bagi_hasil_cbg3;
  $pendapatan_lain_detail[] = [
    'nama' => 'Bagi Hasil Numart Pondok Srumbung (50%)',
    'nilai' => $bagi_hasil_cbg3
  ];

  // Hitung laba bersih Numart Tren Pakis (Cabang 2)
  $q_laba_bersih_cbg2 = mysqli_query($conn, "
    SELECT 
      (SUM(invoice_sub_total) 
       - SUM(invoice_total_beli)
       - COALESCE((
          SELECT SUM(CAST(REPLACE(REPLACE(l2.jumlah, '.', ''), ',', '') AS DECIMAL(18,2))) 
          FROM laba l2 
          LEFT JOIN laba_kategori lk2 ON CAST(l2.kategori AS UNSIGNED) = lk2.id
          WHERE l2.tipe = 1 
          AND l2.cabang = 2 
          AND l2.date >= '$tanggal_awal 00:00:00'
          AND l2.date <= '$tanggal_akhir 23:59:59'
          AND lk2.kategori = 'beban'
        ),0)
      ) AS laba_bersih_cabang2
    FROM invoice
    WHERE invoice_cabang = 2
    AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
  ");
  $laba_bersih_cbg2 = mysqli_fetch_assoc($q_laba_bersih_cbg2)['laba_bersih_cabang2'] ?? 0;
  $bagi_hasil_cbg2 = $laba_bersih_cbg2 * 0.30;
  $pendapatan_lain_bagi_hasil += $bagi_hasil_cbg2;
  $pendapatan_lain_detail[] = [
    'nama' => 'Bagi Hasil Numart Tren Pondok Pakis (30%)',
    'nilai' => $bagi_hasil_cbg2
  ];

    // Hitung laba bersih Numart Tegalrejo (Cabang 5)
  $q_laba_bersih_cbg5 = mysqli_query($conn, "
  SELECT 
    (SUM(invoice_sub_total) 
     - SUM(invoice_total_beli)
     - COALESCE((
        SELECT SUM(CAST(REPLACE(REPLACE(l2.jumlah, '.', ''), ',', '') AS DECIMAL(18,2))) 
        FROM laba l2 
          LEFT JOIN laba_kategori lk2 ON CAST(l2.kategori AS UNSIGNED) = lk2.id
        WHERE l2.tipe = 1 
        AND l2.cabang = 5 
        AND l2.date >= '$tanggal_awal 00:00:00'
        AND l2.date <= '$tanggal_akhir 23:59:59'
          AND lk2.kategori = 'beban'
      ),0)
    ) AS laba_bersih_cabang5
  FROM invoice
  WHERE invoice_cabang = 5
  AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
");
$laba_bersih_cbg5 = mysqli_fetch_assoc($q_laba_bersih_cbg5)['laba_bersih_cabang5'] ?? 0;
$bagi_hasil_cbg5 = $laba_bersih_cbg5 * 0.45;
$pendapatan_lain_bagi_hasil += $bagi_hasil_cbg5;
$pendapatan_lain_detail[] = [
  'nama' => 'Bagi Hasil Numart Tegalrejo (45%)',
  'nilai' => $bagi_hasil_cbg5
];
}

/* -------------------------------------------
   8. Hitung Total Laba
------------------------------------------- */
// Total pendapatan (penjualan + pendapatan lain-lain COA 8-)
$total_pendapatan = $total_cash + $total_kredit + $total_pendapatan_lain;
$laba_kotor = $total_penjualan - $hpp;
// Laba sebelum beban = laba kotor + pendapatan lain-lain (COA berawalan 8-)
$laba_sebelum_beban = $laba_kotor + $total_pendapatan_lain;
$laba_operasi = $laba_sebelum_beban - $total_pengeluaran;
$beban_lain = 0; // Beban Lain (bisa ditambahkan nanti jika diperlukan)

// Bagi hasil cabang NUMART (dibayar ke Nugrosir & PCNU)
$biaya_cadangan_pajak = 0;
$laba_sebelum_bagi_hasil = $laba_operasi;
$bagi_hasil_nugrosir = 0;
$bagi_hasil_pcnu = 0;
$total_bagi_hasil = 0;

if ((int) $cabang !== 0) {
  $rate_nugrosir = 0.0;
  $rate_pcnu = 0.0;
  // Mengikuti skema pada halaman investor cabang
  if ((int) $cabang === 1) { // Dukun
    $rate_nugrosir = 0.45;
    $rate_pcnu = 0.05;
  } elseif ((int) $cabang === 2) { // Pakis
    $rate_nugrosir = 0.30;
    $rate_pcnu = 0.00;
  } elseif ((int) $cabang === 3) { // Srumbung
    $rate_nugrosir = 0.25;
    $rate_pcnu = 0.05;
  } elseif ((int) $cabang === 5) { // Tegalrejo
    $rate_nugrosir = 0.45;
    $rate_pcnu = 0.05;
  }

  // Cadangan pajak 5% dari laba operasi (dasar bagi hasil)
  $biaya_cadangan_pajak = $laba_operasi * 0.05;
  $laba_sebelum_bagi_hasil = $laba_operasi - $biaya_cadangan_pajak;

  $bagi_hasil_nugrosir = $laba_sebelum_bagi_hasil * $rate_nugrosir;
  $bagi_hasil_pcnu = $laba_sebelum_bagi_hasil * $rate_pcnu;
  $total_bagi_hasil = $bagi_hasil_nugrosir + $bagi_hasil_pcnu;
}

if ((int) $cabang === 0) {
  // Pusat: cadangan pajak 5% dari laba operasi sendiri, lalu pendapatan bagi hasil cabang
  $biaya_cadangan_pajak = $laba_operasi * 0.05;
  $laba_bersih = ($laba_operasi - $biaya_cadangan_pajak) + $pendapatan_lain_bagi_hasil - $beban_lain;
} else {
  // Cabang mengeluarkan bagi hasil
  $laba_bersih = $laba_sebelum_bagi_hasil - $total_bagi_hasil - $beban_lain;
}
// Note: DP sudah termasuk dalam total_cash, jadi tidak perlu ditambahkan lagi
$persentase = $hpp > 0 ? round(($laba_bersih / $hpp) * 100, 2) : 0;

/* ========================================================
   8. TOTAL TRANSFER STOK (Cabang Utama)
======================================================== */
$total_transfer_stok = 0;
$transfer_detail = [];

if ($cabang == 0) {
  require_once __DIR__ . '/aksi/cabang-arsip-lib.php';
  $excludeArsipTransfer = cabang_sql_exclude_arsip($conn, 'tpk_penerima_cabang');
  $q_transfer = mysqli_query($conn, "
    SELECT 
      tpk_penerima_cabang,
      COALESCE(SUM(tpk_qty * (CASE WHEN b.barang_harga_beli_rata > 0 THEN b.barang_harga_beli_rata ELSE b.barang_harga_beli END)), 0) AS total_transfer
    FROM transfer_produk_keluar tpk
    JOIN barang b ON tpk.tpk_barang_id = b.barang_id
    WHERE tpk_penerima_cabang != 0
      AND tpk.tpk_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
      $excludeArsipTransfer
    GROUP BY tpk_penerima_cabang
  ");

  while ($row = mysqli_fetch_assoc($q_transfer)) {
    $total_transfer_stok += $row['total_transfer'];
    $nama_cabang = '';
    if ($row['tpk_penerima_cabang'] == 1) {
      $nama_cabang = 'NUMART DUKUN';
    } elseif ($row['tpk_penerima_cabang'] == 2) {
      $nama_cabang = 'NUMART TREN PAKIS';
    } elseif ($row['tpk_penerima_cabang'] == 3) {
      $nama_cabang = 'NUMART PONDOK SRUMBUNG';
    } elseif ($row['tpk_penerima_cabang'] == 4) {
      $nama_cabang = 'BAQNU PCNU';
    } elseif ($row['tpk_penerima_cabang'] == 5) {
      $nama_cabang = 'NUMART TEGALREJO';
    } else {
      $nama_cabang = 'Cabang ' . $row['tpk_penerima_cabang'];
    }
    $transfer_detail[] = [
      'nama' => 'Transfer Stok ke ' . $nama_cabang,
      'nilai' => $row['total_transfer']
    ];
  }
}

/* ========================================================
   9. HITUNG PERSEDIAAN AKHIR
   Rumus:
   NUGROSIR : PA_awal + Pembelian + TF_Balik + Retur_Jual
                      - TF_ke_Numart - Penjualan(HPP) - Retur_Beli - Hilang
   NUMART   : PA_awal + TF_Masuk + Retur_Jual - TF_Balik - Penjualan(HPP)
======================================================== */
if ($cabang == 0) {
    $persediaan_akhir = $persediaan_awal
        + $total_pembelian_masuk
        + $total_transfer_balik
        + $total_retur_penjualan
        - $total_transfer_stok
        - (float) $hpp
        - $total_retur_pembelian
        - $total_barang_hilang;
} else {
    $persediaan_akhir = $persediaan_awal
        + $total_transfer_masuk
        + $total_retur_penjualan
        - $total_transfer_balik
        - (float) $hpp;
}
$persediaan_akhir = max(0.0, $persediaan_akhir);
?>

<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Laporan Laba Rugi (Accrual Basis)</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="laba-bersih-laporan-neraca">Neraca</a></li>
            <li class="breadcrumb-item active">Laba Rugi Accrual</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">

      <!-- Filter -->
      <div class="card card-default">
        <div class="card-header">
          <h3 class="card-title">Filter Data</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
            <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fas fa-times"></i></button>
          </div>
        </div>
        <form method="POST">
          <div class="card-body">
            <div class="row">
              <div class="col-md-2">
                <div class="form-group">
                  <label for="bulan">Bulan</label>
                  <input type="month" id="bulan" class="form-control" value="<?= htmlspecialchars($bulan_pilih, ENT_QUOTES, 'UTF-8'); ?>" title="Mengisi tanggal awal (hari 1) dan tanggal akhir (hari terakhir) bulan yang dipilih" autocomplete="off">
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label for="tanggal_awal">Tanggal Awal</label>
                  <input type="date" name="tanggal_awal" id="tanggal_awal" class="form-control" value="<?= htmlspecialchars($tanggal_awal, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label for="tanggal_akhir">Tanggal Akhir</label>
                  <input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control" value="<?= htmlspecialchars($tanggal_akhir, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="cabang">Cabang</label>
                  <?php if ($cabang_filter_terkunci) : ?>
                    <input type="hidden" name="cabang" value="<?= htmlspecialchars((string) $cabang, ENT_QUOTES, 'UTF-8') ?>">
                  <?php endif; ?>
                  <select id="cabang" class="form-control"<?= $cabang_filter_terkunci ? ' disabled' : ' name="cabang"' ?>>
                    <?php foreach ($listCabang as $cab) : ?>
                      <?php if ($cabang_filter_terkunci && (int) $cab['toko_cabang'] !== $user_cabang_login) {
                        continue;
                      } ?>
                      <option value="<?= $cab['toko_cabang'] ?>" <?= $cab['toko_cabang'] == $cabang ? 'selected' : '' ?>>
                        <?= $cab['toko_nama'] ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if ($cabang_filter_terkunci) : ?>
                    <small class="text-muted">Cabang mengikuti akun yang sedang login.</small>
                  <?php endif; ?>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>&nbsp;</label>
                  <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa fa-filter"></i> Tampilkan
                  </button>
                </div>
              </div>
            </div>
            <small class="text-muted d-block mb-2">Pilih <strong>Bulan</strong> untuk laporan satu bulan penuh, atau ubah tanggal awal/akhir secara manual.</small>
          </div>
        </form>
        <script>
        (function () {
          var elBulan = document.getElementById('bulan');
          var elAwal = document.getElementById('tanggal_awal');
          var elAkhir = document.getElementById('tanggal_akhir');
          if (!elBulan || !elAwal || !elAkhir) return;
          function syncTanggalDariBulan() {
            var v = elBulan.value;
            if (!v || v.length < 7) return;
            var p = v.split('-');
            var y = parseInt(p[0], 10);
            var m = parseInt(p[1], 10);
            if (!y || !m) return;
            var first = v + '-01';
            var lastD = new Date(y, m, 0).getDate();
            var last = v + '-' + (lastD < 10 ? '0' : '') + lastD;
            elAwal.value = first;
            elAkhir.value = last;
          }
          elBulan.addEventListener('change', syncTanggalDariBulan);
          function syncBulanDariAwal() {
            var v = elAwal.value;
            if (v && v.length >= 7) elBulan.value = v.substring(0, 7);
          }
          elAwal.addEventListener('change', syncBulanDariAwal);
        })();
        </script>
      </div>

      <!-- Laporan -->
      <div class="card card-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0 text-white">Periode <?= date('d M Y', strtotime($tanggal_awal)) ?> - <?= date('d M Y', strtotime($tanggal_akhir)) ?></h3>
          <div class="card-tools">
            <button type="button" class="btn btn-success btn-sm no-print" onclick="exportExcel()">
              <i class="fas fa-file-excel"></i> Export Excel
            </button>
            <button type="button" class="btn btn-danger btn-sm ml-1 no-print" onclick="exportPDF()">
              <i class="fas fa-file-pdf"></i> Export PDF
            </button>
            <button type="button" class="btn btn-info btn-sm ml-1 no-print" onclick="window.print()">
              <i class="fas fa-print"></i> Print
            </button>
          </div>
        </div>
        <div class="card-body" id="laporan-content">

          <!-- Persediaan Awal Barang -->
          <table class="table table-bordered">
            <thead>
              <tr>
                <th colspan="2" class="bg-secondary text-white">
                  <i class="fas fa-boxes"></i> PERSEDIAAN AWAL BARANG
                </th>
              </tr>
            </thead>
            <tbody>
              <tr class="table-warning">
                <td>
                  <b><?= $persediaan_label ?></b>
                  <small class="text-muted d-block">
                    Rekonstruksi nilai stok aktif cabang ini Ã— harga beli pada akhir
                    <strong><?= date('d/m/Y', strtotime($tgl_sebelum_awal)) ?></strong>
                    (sebelum transaksi periode berjalan)
                  </small>
                </td>
                <td class="text-right"><b><?= rupiah($persediaan_awal) ?></b></td>
              </tr>
            </tbody>
          </table>

          <!-- Laporan Laba Rugi dalam format yang lebih rapi -->
          <table class="table table-bordered">
            <thead>
              <tr>
                <th colspan="2" class="bg-primary text-white">1. PENDAPATAN</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>a. Penjualan Cash</td>
                <td class="text-right"><?= rupiah($total_cash) ?></td>
              </tr>
              <tr>
                <td>b. Penjualan Kredit</td>
                <td class="text-right"><?= rupiah($total_kredit) ?></td>
              </tr>
              <tr>
                <td>c. Total Penjualan</td>
                <td class="text-right"><?= rupiah($total_penjualan) ?></td>
              </tr>
              <tr class="table-info">
                <td><b>Total Penjualan</b></td>
                <td class="text-right"><b><?= rupiah($total_penjualan) ?></b></td>
              </tr>
            </tbody>
          </table>

          <!-- HPP -->
          <table class="table table-bordered">
            <thead>
              <tr>
                <th colspan="2" class="bg-primary text-white"><?= $cabang == 0 && !empty($transfer_detail) ? '2. HPP' : '2. HPP' ?></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>a. Harga Pokok Penjualan</td>
                <td class="text-right"><?= rupiah($hpp) ?></td>
              </tr>
              <tr class="table-info">
                <td><b>Laba Kotor</b></td>
                <td class="text-right"><b><?= rupiah($laba_kotor) ?></b></td>
              </tr>
              <tr>
                <td>Margin Laba Kotor (% dari Penjualan)</td>
                <td class="text-right"><?= $total_penjualan > 0 ? round(($laba_kotor / $total_penjualan) * 100, 2) : 0 ?>%</td>
              </tr>
            </tbody>
          </table>

          <!-- Pengeluaran: Beban Operasional & Beban Lain -->
          <table class="table table-bordered">
            <thead>
              <tr>
                <th colspan="2" class="bg-primary text-white">3. BEBAN</th>
              </tr>
            </thead>
            <tbody>
              <tr class="table-secondary">
                <td colspan="2"><strong>Beban Operasional</strong></td>
              </tr>
              <?php
              $counter_index = 0;
              foreach ($pengeluaran_operasional as $p) : ?>
                <tr>
                  <td><?= labaAccrual_indexToLetter($counter_index) ?>. <?= htmlspecialchars($p['kategori_nama']) ?></td>
                  <td class="text-right"><?= rupiah($p['total']) ?></td>
                </tr>
                <?php $counter_index++; ?>
              <?php endforeach; ?>
              <tr class="bg-light">
                <td><em>Subtotal Beban Operasional</em></td>
                <td class="text-right"><em><?= rupiah($total_beban_operasional) ?></em></td>
              </tr>
              <tr class="table-secondary">
                <td colspan="2"><strong>Beban Lain</strong> <span class="text-muted font-weight-normal">(bunga bank, administrasi bank, pinjaman, dll.)</span></td>
              </tr>
              <?php
              $counter_index = 0;
              foreach ($pengeluaran_lain as $p) : ?>
                <tr>
                  <td><?= labaAccrual_indexToLetter($counter_index) ?>. <?= htmlspecialchars($p['kategori_nama']) ?></td>
                  <td class="text-right"><?= rupiah($p['total']) ?></td>
                </tr>
                <?php $counter_index++; ?>
              <?php endforeach; ?>
              <?php if (empty($pengeluaran_lain)) : ?>
                <tr>
                  <td class="text-muted font-italic">Tidak ada transaksi beban lain pada periode ini.</td>
                  <td class="text-right text-muted"><?= rupiah(0) ?></td>
                </tr>
              <?php endif; ?>
              <tr class="bg-light">
                <td><em>Subtotal Beban Lain</em></td>
                <td class="text-right"><em><?= rupiah($total_beban_lain_finansial) ?></em></td>
              </tr>
              <tr class="table-info">
                <td><b>Total Biaya Pengeluaran</b></td>
                <td class="text-right"><b><?= rupiah($total_pengeluaran) ?></b></td>
              </tr>
            </tbody>
          </table>

          <!-- Laba Bersih -->
          <table class="table table-bordered">
            <thead>
              <tr>
                <th colspan="2"class="bg-primary text-white">
                  <?php
                  $section_number = 4;
                  if ($cabang == 0 && !empty($transfer_detail)) {
                    $section_number = 4;
                  }
                  echo $section_number . '. LABA BERSIH';
                  ?>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Laba Kotor</td>
                <td class="text-right"><?= rupiah($laba_kotor) ?></td>
              </tr>
              <tr>
                <td>Beban Operasional</td>
                <td class="text-right"><?= rupiah($total_beban_operasional) ?></td>
              </tr>
              <tr>
                <td>Beban Lain</td>
                <td class="text-right"><?= rupiah($total_beban_lain_finansial) ?></td>
              </tr>
              <tr class="table-light">
                <td><em>Total Biaya Pengeluaran</em></td>
                <td class="text-right"><em><?= rupiah($total_pengeluaran) ?></em></td>
              </tr>
              <tr>
                <td><b>Laba Bersih</b></td>
                <td class="text-right">
                  <?php
                  // Laba Bersih = Laba Kotor - Total Pengeluaran
                  $laba_bersih_section = $laba_kotor - $total_pengeluaran;
                  // Markup Laba Bersih = (Laba Bersih / HPP) * 100
                  $persentase_section = $hpp > 0 ? round(($laba_bersih_section / $hpp) * 100, 2) : 0;
                  ?>
                  <b class="<?= $laba_bersih_section >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= rupiah($laba_bersih_section) ?>
                  </b>
                </td>
              </tr>
              <?php if ($hpp > 0) : ?>
                <tr>
                  <td>Markup Laba Bersih (% dari HPP)</td>
                  <td class="text-right">
                    <span class="<?= $persentase_section >= 0 ? 'text-success' : 'text-danger' ?>">
                      <b><?= $persentase_section ?>%</b>
                    </span>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>

          <!-- Persediaan Akhir Barang (untuk cabang selain 0) -->
          <?php if ($cabang != 0) : ?>
          <table class="table table-bordered">
            <thead>
              <tr>
                <th colspan="2" class="bg-primary text-white">
                  <i class="fas fa-boxes"></i> PERSEDIAAN AKHIR BARANG
                </th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><?= $persediaan_label ?></td>
                <td class="text-right"><?= rupiah($persediaan_awal) ?></td>
              </tr>
              <tr class="table-warning">
                <td>
                  <b>Persediaan Akhir Barang</b>
                  <small class="text-muted d-block">
                    Rekonstruksi nilai stok aktif Ã— harga beli pada akhir
                    <strong><?= date('d/m/Y', strtotime($tanggal_akhir)) ?></strong>
                  </small>
                </td>
                <td class="text-right">
                  <b class="<?= $persediaan_akhir >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= rupiah($persediaan_akhir) ?>
                  </b>
                </td>
              </tr>
            </tbody>
          </table>
          <?php endif; ?>

          <!-- Pendapatan Lain (Bagi Hasil) -->
          <?php if ($cabang == 0 && !empty($pendapatan_lain_detail)) : ?>
            <table class="table table-bordered">
              <thead>
                <tr class="bg-primary text-white">
                  <th colspan="2" class="bg-primary text-white">
                    <?php
                    $section_number_pendapatan = 5;
                    if ($cabang == 0 && !empty($transfer_detail)) {
                      $section_number_pendapatan = 5;
                    }
                    echo $section_number_pendapatan . '. PENDAPATAN BAGI HASIL';
                    ?> 
                  </th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pendapatan_lain_detail as $p) : ?>
                  <tr>
                    <td><?= $p['nama'] ?></td>
                    <td class="text-right"><?= rupiah($p['nilai']) ?></td>
                  </tr>
                <?php endforeach; ?>
                <tr class="table-info">
                  <td><b>Total Pendapatan Bagi Hasil</b></td>
                  <td class="text-right"><b><?= rupiah($pendapatan_lain_bagi_hasil) ?></b></td>
                </tr>
              </tbody>
            </table>
          <?php endif; ?>

          <?php if ($total_pendapatan_lain != 0) : ?>
          <!-- Pendapatan lain-lain (COA 8-) ditempatkan setelah Pendapatan Bagi Hasil -->
          <table class="table table-bordered">
            <thead>
              <tr>
                <th colspan="2" class="bg-success text-white">
                  PENDAPATAN LAIN-LAIN <span class="font-weight-normal">(semua akun berawalan <?= htmlspecialchars($pendapatan_lain_prefix_kode) ?>)</span>
                </th>
              </tr>
            </thead>
            <tbody>
              
              <?php
              $idx_pl = 0;
              foreach ($pendapatan_lain as $pl) : ?>
                <tr>
                  <td><?= labaAccrual_indexToLetter($idx_pl) ?>. <?= htmlspecialchars($pl['kategori_nama']) ?></td>
                  <td class="text-right"><?= rupiah($pl['total']) ?></td>
                </tr>
                <?php $idx_pl++; ?>
              <?php endforeach; ?>
              <tr class="table-info">
                <td><b>Total Pendapatan Lain-lain</b></td>
                <td class="text-right"><b><?= rupiah($total_pendapatan_lain) ?></b></td>
              </tr>
            </tbody>
          </table>
          <?php endif; ?>

          <!-- Laba Rugi (Ringkasan) -->
          <?php
          $section_number_ringkasan = 6;
          if ($cabang == 0 && !empty($transfer_detail)) {
            $section_number_ringkasan = 6;
          }
          if ($cabang == 0 && !empty($pendapatan_lain_detail)) {
            $section_number_ringkasan = 6;
            if (!empty($transfer_detail)) {
              $section_number_ringkasan = 6;
            }
          }
          ?>
          <table class="table table-bordered mt-3">
            <thead>
              <tr>
                <th colspan="2" class="bg-primary text-white"><?= $section_number_ringkasan ?>. LABA RUGI (Ringkasan)</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Total Penjualan</td>
                <td class="text-right"><?= rupiah($total_penjualan) ?></td>
              </tr>
              <tr>
                <td>Total HPP</td>
                <td class="text-right"><?= rupiah($hpp) ?></td>
              </tr>
              <tr>
                <td>Laba Kotor <span class="text-muted font-weight-normal">(Penjualan âˆ’ HPP)</span></td>
                <td class="text-right"><?= rupiah($laba_kotor) ?></td>
              </tr>
              <?php if ($total_pendapatan_lain != 0) : ?>
                <tr>
                  <td>Pendapatan Lain-lain <span class="text-muted font-weight-normal">(COA berawalan <?= htmlspecialchars($pendapatan_lain_prefix_kode) ?>)</span></td>
                  <td class="text-right"><?= rupiah($total_pendapatan_lain) ?></td>
                </tr>
              <?php endif; ?>
              <?php if ($total_pendapatan_lain != 0) : ?>
                <tr class="table-light">
                  <td>
                    <em>Laba sebelum beban</em>
                  </td>
                  <td class="text-right"><em><?= rupiah($laba_sebelum_beban) ?></em></td>
                </tr>
              <?php endif; ?>
              <tr>
                <td>Beban Operasional</td>
                <td class="text-right"><?= rupiah($total_beban_operasional) ?></td>
              </tr>
              <tr>
                <td>Beban Lain</td>
                <td class="text-right"><?= rupiah($total_beban_lain_finansial) ?></td>
              </tr>
              <tr class="table-light">
                <td><em>Jumlah Beban</em></td>
                <td class="text-right"><em><?= rupiah($total_pengeluaran) ?></em></td>
              </tr>
              <tr class="table-success">
                <td><b><?= htmlspecialchars($label_laba_operasi_display, ENT_QUOTES, 'UTF-8') ?></b> <span class="text-muted font-weight-normal">(Laba sebelum beban âˆ’ jumlah beban)</span></td>
                <td class="text-right">
                  <b class="<?= $laba_operasi >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= rupiah($laba_operasi) ?>
                  </b>
                </td>
              </tr>
              <?php if ((int) $cabang === 0) : ?>
                <?php if ($biaya_cadangan_pajak != 0) : ?>
                  <tr>
                    <td>Cadangan Pajak (5% dari Laba Operasi)</td>
                    <td class="text-right">(<?= rupiah($biaya_cadangan_pajak) ?>)</td>
                  </tr>
                <?php endif; ?>
                <tr>
                  <td>Pendapatan Bagi Hasil</td>
                  <td class="text-right"><?= rupiah($pendapatan_lain_bagi_hasil) ?></td>
                </tr>
              <?php else : ?>
                <?php if ($biaya_cadangan_pajak != 0) : ?>
                  <tr>
                    <td>Cadangan Pajak (5%)</td>
                    <td class="text-right">(<?= rupiah($biaya_cadangan_pajak) ?>)</td>
                  </tr>
                <?php endif; ?>
                <?php if ($bagi_hasil_nugrosir != 0) : ?>
                  <tr>
                    <td>Bagi Hasil ke Nugrosir</td>
                    <td class="text-right">(<?= rupiah($bagi_hasil_nugrosir) ?>)</td>
                  </tr>
                <?php endif; ?>
                <?php if ($bagi_hasil_pcnu != 0) : ?>
                  <tr>
                    <td>Bagi Hasil ke PCNU</td>
                    <td class="text-right">(<?= rupiah($bagi_hasil_pcnu) ?>)</td>
                  </tr>
                <?php endif; ?>
              <?php endif; ?>
              <?php if ($beban_lain > 0) : ?>
                <tr>
                  <td>Beban Lain</td>
                  <td class="text-right"><?= rupiah($beban_lain) ?></td>
                </tr>
              <?php endif; ?>
              <tr class="table-success">
                <td><b><?= htmlspecialchars($label_laba_bersih_akhir_display, ENT_QUOTES, 'UTF-8') ?></b></td>
                <td class="text-right">
                  <b class="<?= $laba_bersih >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= rupiah($laba_bersih) ?>
                  </b>
                </td>
              </tr>
            </tbody>
          </table>

          <!--Transfer Stock-->
          <?php if ($cabang == 0 && !empty($transfer_detail)) : ?>
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th colspan="2" class="bg-primary text-white">
                    <?php
                    $section_number_transfer = 6;
                    if (!empty($pendapatan_lain_detail)) {
                      $section_number_transfer = 7;
                    }
                    echo $section_number_transfer . '. TOTAL TRANSFER STOCK (Dikirim oleh Cabang)';
                    ?>
                  </th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($transfer_detail as $t) : ?>
                  <tr>
                    <td><?= $t['nama'] ?></td>
                    <td class="text-right"><?= rupiah($t['nilai']) ?></td>
                  </tr>
                <?php endforeach; ?>
                <tr class="table-info">
                  <td><b>Total Transfer Stok</b></td>
                  <td class="text-right"><b><?= rupiah($total_transfer_stok) ?></b></td>
                </tr>
              </tbody>
            </table>
          
            <!-- Persediaan Akhir Barang (Cabang 0 - setelah transfer) -->
            <table class="table table-bordered mt-3">
              <thead>
                <tr>
                  <th colspan="2" class="bg-secondary text-white">
                    <i class="fas fa-boxes"></i> PERSEDIAAN AKHIR BARANG
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><?= $persediaan_label ?></td>
                  <td class="text-right"><?= rupiah($persediaan_awal) ?></td>
                </tr>
                <tr class="table-warning">
                  <td>
                    <b>Persediaan Akhir Barang</b>
                    <small class="text-muted d-block">
                      Rekonstruksi nilai stok aktif Ã— harga beli pada akhir
                      <strong><?= date('d/m/Y', strtotime($tanggal_akhir)) ?></strong>
                    </small>
                  </td>
                  <td class="text-right">
                    <b class="<?= $persediaan_akhir >= 0 ? 'text-success' : 'text-danger' ?>">
                      <?= rupiah($persediaan_akhir) ?>
                    </b>
                  </td>
                </tr>
              </tbody>
            </table>
          <?php endif; ?>

        </div>
      </div>

      <div class="card card-outline card-secondary mt-3 no-print">
        <div class="card-body py-2">
          <i class="fas fa-balance-scale mr-1"></i>
          Posisi keuangan (neraca) tersedia terpisah di
          <a href="laba-bersih-laporan-neraca"><strong>Laporan Neraca</strong></a>
          (per tanggal, dari saldo COA).
        </div>
      </div>
    </div>
  </section>
</div>

<style>
@media print {
  .content-header, .card-default, .card-tools, .main-sidebar, .main-header, .main-footer, .breadcrumb, .no-print {
    display: none !important;
  }
  .content-wrapper {
    margin-left: 0 !important;
    padding: 0 !important;
  }
  .card {
    border: none !important;
    box-shadow: none !important;
  }
  body {
    font-size: 12px;
  }
  .table {
    font-size: 11px;
  }
  /* Warna baris laporan (termasuk pemisah Beban Operasional / Beban Lain) agar print sama dengan layar */
  .table-warning,
  .table-info,
  .table-success,
  .table-secondary,
  .table-light,
  .bg-light,
  .bg-secondary,
  .bg-primary,
  .bg-success {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>

<script>
// Export to Excel using table2excel approach
function exportExcel() {
  try {
    const toko = '<?= addslashes($toko['toko_nama'] ?? 'Laporan') ?>';
    const periode = '<?= date('d-m-Y', strtotime($tanggal_awal)) ?>_sd_<?= date('d-m-Y', strtotime($tanggal_akhir)) ?>';
    const filename = 'Laporan_Laba_Rugi_' + toko.replace(/\s+/g, '_') + '_' + periode;
    
    // Build HTML table for export
    let html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    html += '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    html += '<x:Name>Laba Rugi</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>';
    html += '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>';
    
    // Header
    html += '<table border="1">';
    html += '<tr><td colspan="2" style="font-size:16pt;font-weight:bold;text-align:center;">LAPORAN LABA RUGI</td></tr>';
    html += '<tr><td colspan="2" style="font-size:14pt;text-align:center;">' + toko + '</td></tr>';
    html += '<tr><td colspan="2" style="text-align:center;">Periode: <?= date('d M Y', strtotime($tanggal_awal)) ?> - <?= date('d M Y', strtotime($tanggal_akhir)) ?></td></tr>';
    html += '<tr><td colspan="2"></td></tr>';
    
    function laporanExcelRowBg(tr) {
      if (!tr || !tr.classList) return '';
      const cls = tr.className;
      if (cls.indexOf('table-secondary') !== -1) return 'background-color:#e2e3e5;';
      if (cls.indexOf('table-info') !== -1) return 'background-color:#d1ecf1;';
      if (cls.indexOf('table-warning') !== -1) return 'background-color:#fff3cd;';
      if (cls.indexOf('table-success') !== -1) return 'background-color:#d4edda;';
      if (cls.indexOf('table-light') !== -1 || cls.indexOf('bg-light') !== -1) return 'background-color:#f8f9fa;';
      return '';
    }

    function laporanExcelCellStyle(cell, tr) {
      let s = laporanExcelRowBg(tr);
      const trBold = tr && tr.classList && (tr.classList.contains('table-secondary') || tr.classList.contains('bg-primary'));
      if (trBold || cell.querySelector('b, strong') || cell.tagName === 'TH') s += 'font-weight:bold;';
      if (cell.querySelector('em') && !cell.querySelector('strong')) s += 'font-style:italic;';
      if (cell.classList.contains('text-right')) s += 'text-align:right;';
      return s;
    }

    // Get all tables (struktur sama dengan halaman: Beban Operasional + Beban Lain + subtotal)
    const tables = document.querySelectorAll('#laporan-content table');
    tables.forEach(table => {
      const rows = table.querySelectorAll('tr');
      rows.forEach(row => {
        html += '<tr>';
        const cells = row.querySelectorAll('th, td');
        cells.forEach(cell => {
          const colspan = cell.getAttribute('colspan') || 1;
          const text = cell.innerText.trim().replace(/\n/g, ' ');
          const style = laporanExcelCellStyle(cell, row);
          html += '<td colspan="' + colspan + '" style="' + style + '">' + text + '</td>';
        });
        html += '</tr>';
      });
      html += '<tr><td colspan="2"></td></tr>';
    });
    
    html += '</table></body></html>';
    
    // Download
    const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '.xls';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    Swal.fire({
      icon: 'success',
      title: 'Export Berhasil',
      text: 'File Excel telah didownload',
      timer: 2000,
      showConfirmButton: false
    });
  } catch (err) {
    console.error('Excel Error:', err);
    Swal.fire({
      icon: 'error',
      title: 'Gagal Export',
      text: 'Terjadi kesalahan: ' + err.message
    });
  }
}

// Export to PDF - Open print dialog
function exportPDF() {
  // Create printable version
  const toko = '<?= addslashes($toko['toko_nama'] ?? 'Laporan') ?>';
  const content = document.getElementById('laporan-content').innerHTML;
  
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Laporan Laba Rugi - ${toko}</title>
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
      <style>
        body { font-size: 12px; padding: 20px; }
        .table { font-size: 11px; }
        .table-warning { background-color: #fff3cd !important; }
        .table-info { background-color: #d1ecf1 !important; }
        .table-success { background-color: #d4edda !important; }
        .table-secondary { background-color: #e2e3e5 !important; }
        .table-light { background-color: #f8f9fa !important; }
        .bg-light { background-color: #f8f9fa !important; }
        .bg-secondary { background-color: #6c757d !important; color: white; }
        .bg-primary { background-color: #007bff !important; color: white; }
        .bg-success { background-color: #28a745 !important; color: white; }
        .text-success { color: #28a745 !important; }
        .text-danger { color: #dc3545 !important; }
        .text-muted { color: #6c757d !important; }
        h2 { margin-bottom: 5px; }
        @media print {
          .table-warning, .table-info, .table-success, .table-secondary, .table-light, .bg-light,
          .bg-secondary, .bg-primary, .bg-success {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
        }
      </style>
    </head>
    <body>
      <div class="text-center mb-3">
        <h2>LAPORAN LABA RUGI</h2>
        <h4>${toko}</h4>
        <p>Periode: <?= date('d M Y', strtotime($tanggal_awal)) ?> - <?= date('d M Y', strtotime($tanggal_akhir)) ?></p>
      </div>
      ${content}
      <script>
        window.onload = function() {
          window.print();
          setTimeout(function() { window.close(); }, 500);
        };
      <\/script>
    </body>
    </html>
  `);
  printWindow.document.close();
}
</script>

<?php include '_footerlaporan.php' ?>
<?php include '_footer.php'; ?>
