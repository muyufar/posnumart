<?php
require __DIR__ . '/aksi/koneksi.php';
require __DIR__ . '/aksi/halau.php';
require __DIR__ . '/aksi/functions.php';
require __DIR__ . '/aksi/stock-opname-laporan-lib.php';

mysqli_set_charset($conn, 'utf8mb4');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$cabang = 0;
if ($userId > 0) {
    $res = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($res && ($ru = mysqli_fetch_assoc($res))) {
        $cabang = (int) ($ru['user_cabang'] ?? 0);
    }
}

$periode   = so_laporan_parse_periode($_GET['dari'] ?? '', $_GET['sampai'] ?? '');
$dari      = $periode['dari'];
$sampai    = $periode['sampai'];
$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';
$toko      = so_laporan_get_toko($conn, $cabang);

$tokoNama   = htmlspecialchars($toko['toko_nama']   ?? 'Toko', ENT_QUOTES, 'UTF-8');
$tokoAlamat = htmlspecialchars($toko['toko_alamat'] ?? '',      ENT_QUOTES, 'UTF-8');
$tokoKota   = htmlspecialchars($toko['toko_kota']   ?? '',      ENT_QUOTES, 'UTF-8');

$result    = so_laporan_fetch_nilai_per_bulan($conn, $cabang, $dari, $sampai);
$months    = $result['months'];
$itemRows  = $result['rows'];
$ringkasan = so_laporan_ringkasan_per_bulan_kategori($months, $itemRows);

/* Hitung lebar tabel: banyak bulan menentukan orientasi */
$nMonths    = count($months);
$pageOrient = $nMonths > 4 ? 'landscape' : 'portrait';
$fontSize   = $nMonths > 6 ? '7.5pt' : ($nMonths > 4 ? '8pt' : '9pt');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Nilai Persediaan per Bulan — <?= $tokoNama; ?></title>
  <style>
    @page { size: A4 <?= $pageOrient; ?>; margin: 10mm 12mm; }
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: <?= $fontSize; ?>; color: #111;
           margin: 0; padding: 8mm 10mm; }
    .no-print { margin-bottom: 12px; }
    @media print {
      .no-print { display: none !important; }
      body { padding: 0; }
    }
    h1 { font-size: 12pt; margin: 0 0 2px; text-align: center; text-transform: uppercase; letter-spacing: 0.4px; }
    h2 { font-size: 9.5pt; margin: 0 0 5px; text-align: center; font-weight: normal; }
    .meta { text-align: center; margin-bottom: 8px; font-size: 8pt; line-height: 1.5; color: #333; }
    hr   { border: none; border-top: 1.5px solid #1e3a5f; margin: 5px 0 8px; }

    table { width: 100%; border-collapse: collapse; }
    .tbl-head-group th { background: #2E6DA4; color: #fff; text-align: center;
                         font-size: 8pt; padding: 3px 4px; border: 1px solid #aaa; }
    thead tr.sub-head th { background: #1e3a5f; color: #fff; font-size: 7pt;
                           padding: 3px 4px; border: 1px solid #aaa; text-align: center; }
    tbody td { border: 1px solid #bbb; padding: 3px 5px; vertical-align: middle; }
    td.num  { text-align: right;  white-space: nowrap; }
    td.ctr  { text-align: center; }
    .total-row td { background: #fff3cd !important; font-weight: bold; }
    .alt-row  td { background: #f5f8ff; }

    .footer { margin-top: 12px; font-size: 7.5pt; color: #555;
              border-top: 1px solid #ccc; padding-top: 5px; }
    .ttd    { margin-top: 28px; display: flex; justify-content: space-between; }
    .ttd-box { width: 30%; text-align: center; font-size: 8.5pt; }
    .ttd-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 4px; }
  </style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()">&#128438; Cetak / Simpan PDF</button>
  <button onclick="window.close()" style="margin-left:8px">&#10005; Tutup</button>
</div>

<h1>Laporan Nilai Persediaan Barang per Bulan</h1>
<h2><?= $tokoNama; ?></h2>
<div class="meta">
  <?php if ($tokoAlamat !== ''): ?><?= $tokoAlamat; ?><?= $tokoKota !== '' ? ', ' . $tokoKota : ''; ?><br><?php endif; ?>
  Periode: <strong><?= tanggal_indo($dari); ?></strong> s/d <strong><?= tanggal_indo($sampai); ?></strong>
  &nbsp;|&nbsp; <?= $nMonths; ?> Bulan
  &nbsp;|&nbsp; Dicetak: <?= date('d F Y H:i'); ?>
  &nbsp;|&nbsp; Cabang: <?= (int) $cabang; ?>
</div>
<hr>

<?php if (empty($months)): ?>
  <p>Tidak ada data bulan dalam periode yang dipilih.</p>
<?php else: ?>

<table>
  <thead>
    <!-- Baris 1: header grup bulan -->
    <tr class="tbl-head-group">
      <th rowspan="2" style="width:28px">No</th>
      <th rowspan="2">Kategori</th>
      <th rowspan="2" style="width:50px">Jml.<br>Produk</th>
      <?php foreach ($months as $mn): ?>
      <th colspan="2"><?= htmlspecialchars($mn['label'], ENT_QUOTES, 'UTF-8'); ?></th>
      <?php endforeach; ?>
    </tr>
    <!-- Baris 2: sub-header kolom per bulan -->
    <tr class="sub-head">
      <?php foreach ($months as $mn): ?>
      <th>Stok Akhir</th>
      <th>Nilai Beli</th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php $alt = false; foreach ($ringkasan as $r):
      $isGT = ($r['kategori_nama'] === 'GRAND TOTAL');
      $cls  = $isGT ? 'total-row' : ($alt ? 'alt-row' : '');
      if (!$isGT) $alt = !$alt;
    ?>
    <tr class="<?= $cls; ?>">
      <td class="ctr"><?= $isGT ? '' : ($r['no'] ?? ''); ?></td>
      <td><?= htmlspecialchars($r['kategori_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
      <td class="num"><?= number_format((int) $r['jumlah_produk'], 0, ',', '.'); ?></td>
      <?php foreach ($months as $mn): ?>
      <td class="num"><?= so_laporan_format_qty($r['stok_'       . $mn['key']] ?? 0); ?></td>
      <td class="num"><?= so_laporan_format_rupiah($r['nilai_beli_' . $mn['key']] ?? 0); ?></td>
      <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="footer">
  Nilai persediaan = Stok Akhir &times; Harga Beli (dari master barang).<br>
  Stok Akhir per bulan direkonstruksi dari: Stok saat ini + Penjualan setelah akhir bulan &minus; Pembelian setelah akhir bulan.
</div>

<div class="ttd">
  <div class="ttd-box">Mengetahui,<br>Manager<div class="ttd-line">( ........................... )</div></div>
  <div class="ttd-box">Diperiksa,<br>Supervisor<div class="ttd-line">( ........................... )</div></div>
  <div class="ttd-box">Dibuat,<br>Staff Gudang / Kasir<div class="ttd-line">( ........................... )</div></div>
</div>

<?php endif; ?>

<?php if ($autoPrint): ?>
<script>window.onload = function () { window.print(); };</script>
<?php endif; ?>
</body>
</html>
