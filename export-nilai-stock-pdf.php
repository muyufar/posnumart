<?php
require __DIR__ . '/aksi/koneksi.php';
require __DIR__ . '/aksi/halau.php';
require __DIR__ . '/aksi/functions.php';
require __DIR__ . '/aksi/stock-opname-laporan-lib.php';

mysqli_set_charset($conn, 'utf8mb4');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$cabang = 0;
if ($userId > 0) {
    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $cabang = (int) ($ru['user_cabang'] ?? 0);
    }
}
if ($cabang === 0 && isset($_GET['cabang']) && (int)$_GET['cabang'] >= 0) {
    $cabang = (int) $_GET['cabang'];
}

$periode   = so_laporan_parse_periode($_GET['dari'] ?? '', $_GET['sampai'] ?? '');
$dari      = $periode['dari'];
$sampai    = $periode['sampai'];
$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';
$toko      = so_laporan_get_toko($conn, $cabang);

$tokoNama   = htmlspecialchars($toko['toko_nama']   ?? 'Toko',    ENT_QUOTES, 'UTF-8');
$tokoAlamat = htmlspecialchars($toko['toko_alamat'] ?? '',         ENT_QUOTES, 'UTF-8');
$tokoKota   = htmlspecialchars($toko['toko_kota']   ?? '',         ENT_QUOTES, 'UTF-8');

$itemRows  = so_laporan_fetch_nilai_stock($conn, $cabang, $dari, $sampai);
$summary   = so_laporan_nilai_stock_summary($itemRows);
$ringkasan = so_laporan_nilai_ringkasan_per_kategori($itemRows);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Laporan Nilai Persediaan — <?= $tokoNama; ?></title>
  <style>
    @page { size: A4 portrait; margin: 12mm 14mm; }
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 9.5pt; color: #111; margin: 0; padding: 10mm 14mm; }
    .no-print { margin-bottom: 14px; }
    @media print {
      .no-print { display: none !important; }
      body { padding: 0; }
    }
    h1 { font-size: 13pt; margin: 0 0 3px; text-align: center; text-transform: uppercase; letter-spacing: 0.5px; }
    h2 { font-size: 10pt; margin: 0 0 6px; text-align: center; font-weight: normal; }
    .meta { text-align: center; margin-bottom: 10px; font-size: 8.5pt; line-height: 1.6; color: #333; }
    hr  { border: none; border-top: 1.5px solid #1e3a5f; margin: 6px 0 10px; }

    /* Kotak ringkasan total */
    .summary-grid { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
    .summary-card { flex: 1; min-width: 130px; border: 1px solid #ccc; border-radius: 4px;
                    padding: 6px 8px; background: #f0f4ff; text-align: center; }
    .summary-card .label { font-size: 7.5pt; color: #555; }
    .summary-card .value { font-size: 10.5pt; font-weight: bold; color: #1e3a5f; margin-top: 2px; }

    /* Tabel ringkasan */
    table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    th, td { border: 1px solid #aaa; padding: 4px 6px; vertical-align: middle; }
    th { background: #1e3a5f; color: #fff; font-size: 8pt; text-align: center; }
    td.num  { text-align: right;  white-space: nowrap; }
    td.ctr  { text-align: center; white-space: nowrap; }
    .total-row td { background: #fff3cd; font-weight: bold; }
    .alt-row td { background: #f5f8ff; }
    .section-title { font-weight: bold; font-size: 10pt; margin: 14px 0 4px;
                     border-left: 4px solid #1e3a5f; padding-left: 8px; }

    .footer { margin-top: 14px; font-size: 7.5pt; color: #555; border-top: 1px solid #ccc; padding-top: 6px; }
    .ttd    { margin-top: 30px; display: flex; justify-content: space-between; }
    .ttd-box { width: 30%; text-align: center; font-size: 9pt; }
    .ttd-line { border-top: 1px solid #333; margin-top: 44px; padding-top: 4px; }
  </style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()">&#128438; Cetak / Simpan PDF</button>
  <button onclick="window.close()" style="margin-left:8px">&#10005; Tutup</button>
</div>

<h1>Laporan Nilai Persediaan Barang</h1>
<h2><?= $tokoNama; ?></h2>
<div class="meta">
  <?php if ($tokoAlamat !== ''): ?><?= $tokoAlamat; ?><?= $tokoKota !== '' ? ', ' . $tokoKota : ''; ?><br><?php endif; ?>
  Periode: <strong><?= tanggal_indo($dari); ?></strong> s/d <strong><?= tanggal_indo($sampai); ?></strong><br>
  Dicetak: <?= date('d F Y H:i'); ?> &nbsp;|&nbsp; Cabang: <?= (int) $cabang; ?>
</div>
<hr>

<!-- Ringkasan Total -->
<div class="summary-grid">
  <div class="summary-card">
    <div class="label">Total Produk Aktif</div>
    <div class="value"><?= number_format(count($itemRows), 0, ',', '.'); ?></div>
  </div>
  <div class="summary-card">
    <div class="label">Total Kategori</div>
    <div class="value"><?= number_format(count($ringkasan) - 1, 0, ',', '.'); ?></div>
  </div>
  <div class="summary-card">
    <div class="label">Total Stok Akhir</div>
    <div class="value"><?= so_laporan_format_qty($summary['total_stok_akhir']); ?></div>
  </div>
  <div class="summary-card" style="background:#fffbe6;">
    <div class="label">Total Nilai Beli</div>
    <div class="value" style="color:#856404;"><?= so_laporan_format_rupiah($summary['total_nilai_beli']); ?></div>
  </div>
  <div class="summary-card" style="background:#e9f7ef;">
    <div class="label">Total Nilai Jual</div>
    <div class="value" style="color:#155724;"><?= so_laporan_format_rupiah($summary['total_nilai_jual']); ?></div>
  </div>
</div>

<!-- Tabel Ringkasan per Kategori -->
<div class="section-title">Ringkasan Nilai Persediaan per Kategori</div>
<table>
  <thead>
    <tr>
      <th style="width:30px">No</th>
      <th>Kategori</th>
      <th style="width:65px">Jml. Produk</th>
      <th style="width:70px">Stok Awal</th>
      <th style="width:70px">Pembelian</th>
      <th style="width:70px">Penjualan</th>
      <th style="width:70px">Stok Akhir</th>
      <th style="width:105px">Nilai Beli (Rp)</th>
      <th style="width:105px">Nilai Jual (Rp)</th>
    </tr>
  </thead>
  <tbody>
    <?php $alt = false; foreach ($ringkasan as $r):
      $isTotal = ($r['kategori_nama'] === 'GRAND TOTAL');
      $trClass = $isTotal ? 'total-row' : ($alt ? 'alt-row' : '');
      if (!$isTotal) $alt = !$alt;
    ?>
    <tr class="<?= $trClass; ?>">
      <td class="ctr"><?= $isTotal ? '' : $r['no']; ?></td>
      <td><?= htmlspecialchars($r['kategori_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
      <td class="num"><?= number_format((int) $r['jumlah_produk'], 0, ',', '.'); ?></td>
      <td class="num"><?= so_laporan_format_qty($r['stok_awal']); ?></td>
      <td class="num"><?= so_laporan_format_qty($r['beli_dalam']); ?></td>
      <td class="num"><?= so_laporan_format_qty($r['jual_dalam']); ?></td>
      <td class="num"><?= so_laporan_format_qty($r['stok_akhir']); ?></td>
      <td class="num"><?= so_laporan_format_rupiah($r['nilai_beli']); ?></td>
      <td class="num"><?= so_laporan_format_rupiah($r['nilai_jual']); ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="footer">
  Nilai persediaan dihitung berdasarkan rekonstruksi stok dari akumulasi transaksi master barang (pembelian &amp; penjualan).<br>
  Stok Akhir = Stok saat ini + Penjualan setelah periode &minus; Pembelian setelah periode.<br>
  Nilai Beli = Stok Akhir &times; Harga Beli &nbsp;|&nbsp; Nilai Jual = Stok Akhir &times; Harga Jual (dari master barang).
</div>

<div class="ttd">
  <div class="ttd-box">Mengetahui,<br>Manager<div class="ttd-line">( ........................... )</div></div>
  <div class="ttd-box">Diperiksa,<br>Supervisor<div class="ttd-line">( ........................... )</div></div>
  <div class="ttd-box">Dibuat,<br>Staff Gudang / Kasir<div class="ttd-line">( ........................... )</div></div>
</div>

<?php if ($autoPrint): ?>
<script>window.onload = function () { window.print(); };</script>
<?php endif; ?>
</body>
</html>
