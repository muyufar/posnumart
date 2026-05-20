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

$periode = so_laporan_parse_periode($_GET['dari'] ?? '', $_GET['sampai'] ?? '');
$dari = $periode['dari'];
$sampai = $periode['sampai'];
$mode = trim((string) ($_GET['mode'] ?? 'sesi'));
$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';
$toko = so_laporan_get_toko($conn, $cabang);

$tokoNama = htmlspecialchars($toko['toko_nama'] ?? 'Toko', ENT_QUOTES, 'UTF-8');
$tokoAlamat = htmlspecialchars($toko['toko_alamat'] ?? '', ENT_QUOTES, 'UTF-8');
$tokoKota = htmlspecialchars($toko['toko_kota'] ?? '', ENT_QUOTES, 'UTF-8');

if ($mode === 'buku') {
    $docTitle = 'Buku Persediaan Barang Dagangan';
    $rows = so_laporan_fetch_buku_stok($conn, $cabang, $dari, $sampai);
} elseif ($mode === 'hasil') {
    $docTitle = 'Laporan Hasil Stock Opname per Barang';
    $rows = so_laporan_fetch_hasil($conn, $cabang, $dari, $sampai);
} else {
    $docTitle = 'Ringkasan Sesi Stock Opname';
    $rows = so_laporan_fetch_sesi($conn, $cabang, $dari, $sampai, 1);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($docTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <style>
    @page { size: A4 landscape; margin: 12mm; }
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 9pt; color: #111; margin: 0; padding: 12mm; }
    .no-print { margin-bottom: 12px; }
    @media print {
      .no-print { display: none !important; }
      body { padding: 0; }
    }
    h1 { font-size: 14pt; margin: 0 0 4px; text-align: center; text-transform: uppercase; }
    h2 { font-size: 11pt; margin: 0 0 8px; text-align: center; font-weight: normal; }
    .meta { text-align: center; margin-bottom: 14px; font-size: 9pt; line-height: 1.5; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border: 1px solid #333; padding: 4px 5px; vertical-align: top; }
    th { background: #1e3a5f; color: #fff; font-size: 8pt; text-align: center; }
    td.num { text-align: right; white-space: nowrap; }
    td.center { text-align: center; }
    .footer { margin-top: 16px; font-size: 8pt; color: #444; }
    .ttd { margin-top: 40px; display: flex; justify-content: space-between; }
    .ttd-box { width: 28%; text-align: center; font-size: 9pt; }
    .ttd-line { border-top: 1px solid #333; margin-top: 48px; padding-top: 4px; }
  </style>
</head>
<body>
  <div class="no-print">
    <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    <button type="button" onclick="window.close()">Tutup</button>
  </div>

  <h1><?= htmlspecialchars($docTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
  <h2><?= $tokoNama; ?></h2>
  <div class="meta">
    <?= $tokoAlamat; ?><?= $tokoKota !== '' ? ', ' . $tokoKota : ''; ?><br>
    Periode pengerjaan stock opname: <strong><?= tanggal_indo($dari); ?></strong> s/d <strong><?= tanggal_indo($sampai); ?></strong><br>
    Dicetak: <?= date('d F Y H:i'); ?> | Cabang: <?= (int) $cabang; ?>
  </div>

  <?php if ($mode === 'buku'): ?>
  <table>
    <thead>
      <tr>
        <th>No</th><th>Tanggal</th><th>No. Bukti</th><th>Kode</th><th>Nama Barang</th><th>Sat.</th>
        <th>Uraian</th><th>Saldo Awal</th><th>Masuk</th><th>Keluar</th><th>Saldo Akhir</th>
        <th>Harga @</th><th>Nilai Akhir</th><th>Ket.</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td class="center"><?= (int) $r['no']; ?></td>
        <td class="center"><?= tanggal_indo($r['tanggal'] ?? ''); ?></td>
        <td><?= htmlspecialchars($r['no_bukti'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($r['kode_barang'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($r['nama_barang'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= htmlspecialchars($r['satuan'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($r['uraian'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="num"><?= so_laporan_format_qty($r['saldo_awal'] ?? 0); ?></td>
        <td class="num"><?= so_laporan_format_qty($r['masuk'] ?? 0); ?></td>
        <td class="num"><?= so_laporan_format_qty($r['keluar'] ?? 0); ?></td>
        <td class="num"><?= so_laporan_format_qty($r['saldo_akhir'] ?? 0); ?></td>
        <td class="num"><?= so_laporan_format_rupiah($r['harga_satuan'] ?? 0); ?></td>
        <td class="num"><?= so_laporan_format_rupiah($r['nilai_saldo_akhir'] ?? 0); ?></td>
        <td><?= htmlspecialchars($r['keterangan'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php elseif ($mode === 'hasil'): ?>
  <table>
    <thead>
      <tr>
        <th>No</th><th>Tgl.</th><th>Kode</th><th>Nama</th><th>Sat.</th>
        <th>Sistem</th><th>Fisik</th><th>Selisih</th><th>Harga @</th><th>Nilai</th><th>Tipe</th><th>Ket.</th>
      </tr>
    </thead>
    <tbody>
      <?php $no = 1; foreach ($rows as $h): ?>
      <tr>
        <td class="center"><?= $no++; ?></td>
        <td class="center"><?= tanggal_indo($h['stock_opname_date_proses'] ?? ''); ?></td>
        <td><?= htmlspecialchars($h['soh_barang_kode'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($h['barang_nama'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= htmlspecialchars($h['satuan_nama'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="num"><?= so_laporan_format_qty($h['soh_barang_stock_system'] ?? 0); ?></td>
        <td class="num"><?= so_laporan_format_qty($h['soh_stock_fisik'] ?? 0); ?></td>
        <td class="num"><?= so_laporan_format_qty($h['soh_selisih'] ?? 0); ?></td>
        <td class="num"><?= so_laporan_format_rupiah($h['harga_satuan'] ?? 0); ?></td>
        <td class="num"><?= so_laporan_format_rupiah($h['nilai_persediaan'] ?? 0); ?></td>
        <td class="center"><?= htmlspecialchars($h['tipe_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($h['soh_note'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>No</th><th>No. Sesi</th><th>Tgl. Proses</th><th>Tipe</th><th>Petugas</th>
        <th>Item</th><th>Sesuai</th><th>Lebih</th><th>Kurang</th><th>Selisih (abs)</th>
      </tr>
    </thead>
    <tbody>
      <?php $no = 1; foreach ($rows as $s): ?>
      <tr>
        <td class="center"><?= $no++; ?></td>
        <td>SO-<?= str_pad((string) ($s['stock_opname_id'] ?? 0), 5, '0', STR_PAD_LEFT); ?></td>
        <td class="center"><?= tanggal_indo($s['stock_opname_date_proses'] ?? ''); ?></td>
        <td class="center"><?= so_laporan_tipe_label((int) ($s['stock_opname_tipe'] ?? 0)); ?></td>
        <td><?= htmlspecialchars($s['user_eksekusi_nama'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="num"><?= (int) ($s['jumlah_item'] ?? 0); ?></td>
        <td class="num"><?= (int) ($s['item_sesuai'] ?? 0); ?></td>
        <td class="num"><?= (int) ($s['item_lebih'] ?? 0); ?></td>
        <td class="num"><?= (int) ($s['item_kurang'] ?? 0); ?></td>
        <td class="num"><?= so_laporan_format_qty($s['total_selisih_qty'] ?? 0); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <div class="footer">
    Dokumen ini merupakan bukti pencatatan persediaan barang dagangan berdasarkan hasil stock opname,
    sesuai praktik pembukuan toko retail (Buku Persediaan Barang Dagangan — PMK No. 176/PMK.03/2017 dan peraturan pelaksanaannya).
  </div>

  <div class="ttd">
    <div class="ttd-box">Mengetahui,<br>Manager<div class="ttd-line">( ........................... )</div></div>
    <div class="ttd-box">Diperiksa,<br>Supervisor<div class="ttd-line">( ........................... )</div></div>
    <div class="ttd-box">Dibuat,<br>Petugas Stock Opname<div class="ttd-line">( ........................... )</div></div>
  </div>

  <?php if ($autoPrint): ?>
  <script>window.onload = function () { window.print(); };</script>
  <?php endif; ?>
</body>
</html>
