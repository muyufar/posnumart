<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
ob_start();
require numart_path('aksi/koneksi.php');
require numart_path('aksi/halau.php');
require_once numart_path('aksi/stock-opname-laporan-lib.php');
mysqli_set_charset($conn, 'utf8mb4');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$cabang = 0;
if ($userId > 0) {
    $resUb = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($resUb && ($ru = mysqli_fetch_assoc($resUb))) {
        $cabang = (int) ($ru['user_cabang'] ?? 0);
    }
}

$id = abs((int) base64_decode($_GET['id'] ?? ''));
$sesi = so_laporan_fetch_sesi_by_id($conn, $id, $cabang);
if ($sesi === null) {
    ob_end_clean();
    die('Sesi tidak ditemukan');
}
$items = so_laporan_fetch_hasil_sesi($conn, $id, $cabang);
$toko = so_laporan_get_toko($conn, $cabang);
$noSesi = 'SO-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';

$sumSistem = $sumFisik = $sumSelisih = 0.0;
foreach ($items as $it) {
    $sumSistem += (float) ($it['soh_barang_stock_system'] ?? 0);
    $sumFisik += (float) ($it['soh_stock_fisik'] ?? 0);
    $sumSelisih += (float) ($it['soh_selisih'] ?? 0);
}
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Laporan Hasil Stock Opname <?= htmlspecialchars($noSesi, ENT_QUOTES, 'UTF-8'); ?></title>
  <style>
    @page { size: A4 portrait; margin: 14mm; }
    body { font-family: Arial, sans-serif; font-size: 10pt; color: #111; margin: 14mm; }
    .no-print { margin-bottom: 12px; }
    @media print { .no-print { display: none; } }
    h1 { font-size: 14pt; text-align: center; margin: 0 0 4px; }
    h2 { font-size: 11pt; text-align: center; font-weight: normal; margin: 0 0 12px; }
    .meta { font-size: 9pt; margin-bottom: 12px; line-height: 1.5; }
    .cols { display: flex; gap: 12px; margin-bottom: 12px; }
    .cols > div { flex: 1; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #333; padding: 5px 6px; }
    th { background: #1e3a5f; color: #fff; font-size: 9pt; }
    td.num { text-align: right; }
    .total { font-weight: bold; background: #f0f0f0; }
    .ttd { margin-top: 36px; display: flex; justify-content: space-between; }
    .ttd div { width: 30%; text-align: center; font-size: 9pt; }
    .line { border-top: 1px solid #333; margin-top: 48px; padding-top: 4px; }
  </style>
</head>
<body>
  <div class="no-print">
    <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
  </div>

  <h1>BERITA ACARA HASIL STOCK OPNAME</h1>
  <h2><?= htmlspecialchars($toko['toko_nama'] ?? '', ENT_QUOTES, 'UTF-8'); ?> — <?= htmlspecialchars($noSesi, ENT_QUOTES, 'UTF-8'); ?></h2>

  <div class="meta">
    Tanggal proses: <strong><?= so_laporan_tanggal_indo($sesi['stock_opname_date_proses'] ?? ''); ?></strong> |
    Tipe: <?= so_laporan_tipe_label((int) ($sesi['stock_opname_tipe'] ?? 0)); ?> |
    Petugas: <?= htmlspecialchars($sesi['user_eksekusi_nama'] ?? '-', ENT_QUOTES, 'UTF-8'); ?> |
    Dicetak: <?= date('d/m/Y H:i'); ?>
  </div>

  <table>
    <thead>
      <tr>
        <th>No</th><th>Kode/Barcode</th><th>Nama Barang</th><th>Sat.</th>
        <th>Stok Sistem</th><th>Stok Fisik</th><th>Selisih</th><th>Catatan</th>
      </tr>
    </thead>
    <tbody>
      <?php $no = 1; foreach ($items as $row): ?>
      <tr>
        <td class="num"><?= $no++; ?></td>
        <td><?= htmlspecialchars($row['soh_barang_kode'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($row['barang_nama'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($row['satuan_nama'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="num"><?= so_laporan_format_qty($row['soh_barang_stock_system'] ?? 0); ?></td>
        <td class="num"><?= so_laporan_format_qty($row['soh_stock_fisik'] ?? 0); ?></td>
        <td class="num"><?= so_laporan_format_qty($row['soh_selisih'] ?? 0); ?></td>
        <td><?= htmlspecialchars($row['soh_note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="total">
        <td colspan="4" class="num">TOTAL</td>
        <td class="num"><?= so_laporan_format_qty($sumSistem); ?></td>
        <td class="num"><?= so_laporan_format_qty($sumFisik); ?></td>
        <td class="num"><?= so_laporan_format_qty($sumSelisih); ?></td>
        <td></td>
      </tr>
    </tbody>
  </table>

  <p style="font-size:8pt;margin-top:12px;color:#444;">
    Dokumen bukti hasil penghitungan fisik persediaan barang dagangan (stock opname) sesuai praktik pembukuan toko retail.
  </p>

  <div class="ttd">
    <div>Mengetahui,<br>Manager<div class="line">( ........................... )</div></div>
    <div>Diperiksa,<br>Supervisor<div class="line">( ........................... )</div></div>
    <div>Dibuat,<br>Petugas SO<div class="line">( ........................... )</div></div>
  </div>

  <?php if ($autoPrint): ?><script>window.onload=function(){window.print();};</script><?php endif; ?>
</body>
</html>
