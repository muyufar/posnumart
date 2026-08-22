<?php
include '_header.php';

if ($levelLogin != "admin" && $levelLogin != "super admin") {
  echo "
    <script>
      document.location.href = 'bo';
    </script>
  ";
}

if (empty($_POST['tanggal_awal']) && empty($_POST['tanggal_akhir'])) {
  echo "
    <script>
      document.location.href = 'bo';
    </script>
  ";
}


// Validasi tanggal
$tanggal_awal = $_POST['tanggal_awal'];
$tanggal_akhir = $_POST['tanggal_akhir'];

// Ambil data toko
$toko = query("SELECT * FROM toko WHERE toko_cabang = $sessionCabang");
foreach ($toko as $row) {
  $toko_nama = $row['toko_nama'];
  $toko_kota = $row['toko_kota'];
  $toko_tlpn = $row['toko_tlpn'];
  $toko_wa = $row['toko_wa'];
  $toko_print = $row['toko_print'];
}

// Total penjualan
$totalPenjualan = 0;
$queryInvoice = $conn->query("SELECT invoice_sub_total FROM invoice 
        WHERE invoice_cabang = '$sessionCabang' 
        AND invoice_piutang = 0 
        AND invoice_piutang_lunas = 0 
        AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'");

while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
  $totalPenjualan += $rowProduct['invoice_sub_total'];
}

// Total HPP
$totalHpp = 0;
$queryInvoice = $conn->query("SELECT invoice_total_beli FROM invoice 
        WHERE invoice_cabang = '$sessionCabang' 
        AND invoice_piutang = 0 
        AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'");

while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
  $totalHpp += $rowProduct['invoice_total_beli'];
}

// Total Piutang
$totalPiutang = 0;
$queryInvoice = $conn->query("SELECT piutang_nominal FROM piutang 
        WHERE piutang_cabang = '$sessionCabang' 
        AND piutang_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'");

while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
  $totalPiutang += $rowProduct['piutang_nominal'];
}

// Total Piutang Kembalian
$totalPiutangKembalian = 0;
$queryInvoice = $conn->query("SELECT pl_nominal FROM piutang_kembalian 
        WHERE pl_cabang = '$sessionCabang' 
        AND pl_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'");

while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
  $totalPiutangKembalian += $rowProduct['pl_nominal'];
}

// Piutang bersih
$piutang = $totalPiutang - $totalPiutangKembalian;

// Total Hutang
$totalHutang = 0;
$queryInvoice = $conn->query("SELECT hutang_nominal FROM hutang 
        WHERE hutang_cabang = '$sessionCabang' 
        AND hutang_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'");

while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
  $totalHutang += $rowProduct['hutang_nominal'];
}

// Total Hutang Kembalian
$totalHutangKembalian = 0;
$queryInvoice = $conn->query("SELECT hl_nominal FROM hutang_kembalian 
        WHERE hl_cabang = '$sessionCabang' 
        AND hl_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'");

while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
  $totalHutangKembalian += $rowProduct['hl_nominal'];
}

// Hutang bersih
$hutang = $totalHutang - $totalHutangKembalian;

// Total DP Piutang
$totalDp = 0;
$queryInvoice = $conn->query("SELECT invoice_piutang_dp FROM invoice 
        WHERE invoice_cabang = '$sessionCabang' 
        AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'");

while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
  $totalDp += $rowProduct['invoice_piutang_dp'];
}

// Total DP Hutang
$totalDpHutang = 0;
$queryInvoice = $conn->query("SELECT invoice_hutang_dp FROM invoice_pembelian 
        WHERE invoice_pembelian_cabang = '$sessionCabang' 
        AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'");

while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
  $totalDpHutang += $rowProduct['invoice_hutang_dp'];
}

// Total Pembelian
$totalPembelian = 0;
$queryInvoice = $conn->query("SELECT invoice_total FROM invoice_pembelian 
        WHERE invoice_pembelian_cabang = '$sessionCabang' 
        AND invoice_hutang = 0 
        AND invoice_hutang_lunas = 0 
        AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'");

while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
  $totalPembelian += $rowProduct['invoice_total'];
}

// Data Laba Bersih
$labaBersihData = query("SELECT * FROM laba_bersih WHERE lb_cabang = $sessionCabang");
foreach ($labaBersihData as $row) {
  $lb_pendapatan_lain = $row['lb_pendapatan_lain'];
  $lb_pengeluaran_gaji = $row['lb_pengeluaran_gaji'];
  $lb_pengeluaran_listrik = $row['lb_pengeluaran_listrik'];
  $lb_pengeluaran_tlpn_internet = $row['lb_pengeluaran_tlpn_internet'];
  $lb_pengeluaran_perlengkapan_toko = $row['lb_pengeluaran_perlengkapan_toko'];
  $lb_pengeluaran_biaya_penyusutan = $row['lb_pengeluaran_biaya_penyusutan'];
  $lb_pengeluaran_bensin = $row['lb_pengeluaran_bensin'];
  $lb_pengeluaran_tak_terduga = $row['lb_pengeluaran_tak_terduga'];
  $lb_pengeluaran_lain = $row['lb_pengeluaran_lain'];
}
?>


<section class="laporan-laba-bersih">
  <div class="container">
    <div class="llb-header">
      <div class="llb-header-parent">
        <?= $toko_nama; ?>
      </div>
      <div class="llb-header-address">
        <?= $toko_kota; ?>
      </div>
      <div class="llb-header-contact">
        <ul>
          <li><b>No.tlpn:</b> <?= $toko_tlpn; ?></li>&nbsp;&nbsp;
          <li><b>Wa:</b> <?= $toko_wa; ?></li>
        </ul>
      </div>
    </div>


    <div class="laporan-laba-bersih-detail">
      <div class="llbd-title">
        Laporan Laba Bersih Periode <b>[<?= tanggal_indo($tanggal_awal); ?> - <?= tanggal_indo($tanggal_akhir); ?>]</b>
      </div>
      <table class="table">
        <thead>
          <tr>
            <th colspan="2">1. Pendapatan</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>a. Sub Total Penjualan</td>
            <td>Rp <?= number_format($totalPenjualan, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td>b. DP Piutang</td>
            <td>Rp <?= number_format($totalDp, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td>c. Piutang (Cicilan)</td>
            <td>Rp <?= number_format($piutang, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td>d. Pendapatan Lain</td>
            <td>Rp <?= number_format($lb_pendapatan_lain, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td><b>Total Pendapatan</b></td>
            <td>
              <?php
              $totalPendapatan = $totalPenjualan + $piutang + $totalDp + $lb_pendapatan_lain;
              echo "<b>Rp " . number_format($totalPendapatan, 0, ',', '.') . "</b>";
              ?>
            </td>
          </tr>

          <tr>
            <th colspan="2">2. HPP</th>
          </tr>
          <tr>
            <td>a. HPP (Harga Pokok Penjualan)</td>
            <td>Rp <?= number_format($totalHpp, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td><b>Laba / Rugi Kotor</b></td>
            <td>
              <?php
              $labaRugiKotor = $totalPendapatan - $totalHpp;
              echo "<b>Rp " . number_format($labaRugiKotor, 0, ',', '.') . "</b>";
              ?>
            </td>
          </tr>

          <tr>
            <th colspan="2">3. Biaya Pengeluaran</th>
          </tr>
          <tr>
            <td>a. Total Gaji Pegawai</td>
            <td>Rp <?= number_format($lb_pengeluaran_gaji, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td>b. Biaya Listrik 1 Bulan</td>
            <td>Rp <?= number_format($lb_pengeluaran_listrik, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td>c. Telepon & Internet</td>
            <td>Rp <?= number_format($lb_pengeluaran_tlpn_internet, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td>d. Perlengkapan Toko</td>
            <td>Rp <?= number_format($lb_pengeluaran_perlengkapan_toko, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td>e. Biaya Penyusutan</td>
            <td>Rp <?= number_format($lb_pengeluaran_biaya_penyusutan, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td>f. Transportasi & Bensin</td>
            <td>Rp <?= number_format($lb_pengeluaran_bensin, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td>g. Biaya Tak Terduga</td>
            <td>Rp <?= number_format($lb_pengeluaran_tak_terduga, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td>h. Pengeluaran Lain</td>
            <td>Rp <?= number_format($lb_pengeluaran_lain, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td>i. Total Pembelian ke Supplier</td>
            <td>Rp <?= number_format($totalPembelian, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td>j. DP Hutang</td>
            <td>Rp <?= number_format($totalDpHutang, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td>k. Hutang (Cicilan)</td>
            <td>Rp <?= number_format($hutang, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td><b>Total Biaya Pengeluaran</b></td>
            <td>
              <?php
              $totalBiayaPengeluaran = $lb_pengeluaran_gaji + $lb_pengeluaran_listrik + $lb_pengeluaran_tlpn_internet + $lb_pengeluaran_perlengkapan_toko + $lb_pengeluaran_biaya_penyusutan + $lb_pengeluaran_bensin + $lb_pengeluaran_tak_terduga + $lb_pengeluaran_lain + $hutang + $totalDpHutang + $totalPembelian;
              echo "<b>Rp " . number_format($totalBiayaPengeluaran, 0, ',', '.') . "</b>";
              ?>
            </td>
          </tr>
          <tr>
            <th>Laba Bersih</th>
            <th>
              <?php
              $labaBersih = $labaRugiKotor - $totalBiayaPengeluaran;
              echo "Rp " . number_format($labaBersih, 0, ',', '.');
              ?>
            </th>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="nzb-footer">
      <div class="nzb-footer-box">
        <div class="nota-box-footer">
          <div class="nbf-text">
            <a href='https://www.pcnukabmagelang.or.id' /> NUMART PCNU <a href='https://www.pcnukabmagelang.or.id/' /> - KAB MAGELANG <a href='https://www.pcnukabmagelang.or.id/' /><br />
            &#169; <strong>2020-<?= date("Y"); ?><a href='http://www.eydcom.com' />. BUMNU - <a href='https://www.pcnukabmagelang.or.id/' />www.pcnukabmagelang.or.id
          </div>
        </div>
      </div>
    </div>
</section>


</body>

</html>
<script>
  window.print();
</script>