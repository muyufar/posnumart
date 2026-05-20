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

$result   = so_laporan_fetch_nilai_per_bulan($conn, $cabang, $dari, $sampai);
$months   = $result['months'];
$itemRows = $result['rows'];
$perBulan = so_laporan_total_nilai_per_bulan($months, $itemRows);

/* Grand total */
$gtStok = 0; $gtBeli = 0; $gtJual = 0;
foreach ($perBulan as $b) {
    $gtStok += $b['total_stok'];
    $gtBeli += $b['total_nilai_beli'];
    $gtJual += $b['total_nilai_jual'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Nilai Stock per Bulan — <?= $tokoNama; ?></title>
  <style>
    @page  { size: A4 portrait; margin: 14mm 18mm; }
    * { box-sizing: border-box; }
    body   { font-family: Arial, sans-serif; font-size: 10pt; color: #111;
             margin: 0; padding: 10mm 16mm; }
    .no-print { margin-bottom: 14px; }
    @media print {
      .no-print { display: none !important; }
      body { padding: 0; }
    }

    h1  { font-size: 14pt; margin: 0 0 3px; text-align: center; text-transform: uppercase; letter-spacing: .5px; }
    h2  { font-size: 11pt; margin: 0 0 6px; text-align: center; font-weight: normal; }
    .meta { text-align: center; font-size: 9pt; line-height: 1.6; color: #444; margin-bottom: 12px; }
    hr  { border: none; border-top: 2px solid #1e3a5f; margin: 6px 0 14px; }

    table { width: 100%; border-collapse: collapse; }
    thead th {
      background: #1e3a5f; color: #fff; font-size: 9.5pt;
      padding: 7px 10px; border: 1px solid #1e3a5f; text-align: center;
    }
    tbody td { border: 1px solid #ccc; padding: 7px 10px; }
    td.num  { text-align: right; white-space: nowrap; }
    td.ctr  { text-align: center; }
    .alt-row td { background: #eef3fb; }
    .total-row td {
      background: #fff3cd; font-weight: bold;
      border-top: 2px solid #aaa;
    }
    .total-row td.num { font-size: 11pt; }

    .footer { margin-top: 18px; font-size: 8pt; color: #666;
              border-top: 1px solid #ccc; padding-top: 8px; }
    .ttd    { margin-top: 32px; display: flex; justify-content: space-between; }
    .ttd-box { width: 30%; text-align: center; font-size: 9pt; }
    .ttd-line { border-top: 1px solid #333; margin-top: 46px; padding-top: 4px; }
  </style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()">&#128438; Cetak / Simpan PDF</button>
  <button onclick="window.close()" style="margin-left:8px">&#10005; Tutup</button>
</div>

<h1>Laporan Nilai Stock Barang</h1>
<h2><?= $tokoNama; ?></h2>
<div class="meta">
  <?php if ($tokoAlamat !== ''): ?><?= $tokoAlamat; ?><?= $tokoKota !== '' ? ', ' . $tokoKota : ''; ?><br><?php endif; ?>
  Periode: <strong><?= tanggal_indo($dari); ?></strong> s/d <strong><?= tanggal_indo($sampai); ?></strong>
  &nbsp;&nbsp;|&nbsp;&nbsp; <?= count($months); ?> Bulan
  &nbsp;&nbsp;|&nbsp;&nbsp; Dicetak: <?= date('d F Y H:i'); ?>
  &nbsp;&nbsp;|&nbsp;&nbsp; Cabang: <?= (int) $cabang; ?>
</div>
<hr>

<?php if (empty($perBulan)): ?>
  <p>Tidak ada data pada periode yang dipilih.</p>
<?php else: ?>

<table>
  <thead>
    <tr>
      <th style="width:40px">No</th>
      <th>Bulan</th>
      <th style="width:170px">Total Stok Akhir (Unit)</th>
      <th style="width:170px">Nilai Stock (Harga Beli)</th>
      <th style="width:170px">Nilai Stock (Harga Jual)</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($perBulan as $i => $b): ?>
    <tr class="<?= $i % 2 === 1 ? 'alt-row' : ''; ?>">
      <td class="ctr"><?= $i + 1; ?></td>
      <td><strong><?= htmlspecialchars($b['label'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
      <td class="num"><?= so_laporan_format_qty($b['total_stok']); ?></td>
      <td class="num"><?= so_laporan_format_rupiah($b['total_nilai_beli']); ?></td>
      <td class="num"><?= so_laporan_format_rupiah($b['total_nilai_jual']); ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="total-row">
      <td colspan="2" class="ctr">TOTAL</td>
      <td class="num"><?= so_laporan_format_qty($gtStok); ?></td>
      <td class="num"><?= so_laporan_format_rupiah($gtBeli); ?></td>
      <td class="num"><?= so_laporan_format_rupiah($gtJual); ?></td>
    </tr>
  </tbody>
</table>

<div class="footer">
  Nilai Stock dihitung berdasarkan rekonstruksi stok akhir per bulan dari data transaksi master barang.<br>
  Nilai Beli = Stok Akhir &times; Harga Beli &nbsp;|&nbsp; Nilai Jual = Stok Akhir &times; Harga Jual (dari master barang).
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
