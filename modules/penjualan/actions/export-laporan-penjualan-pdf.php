<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
require numart_path('aksi/koneksi.php');
require numart_path('aksi/halau.php');
require numart_path('aksi/functions.php');
require numart_path('aksi/marketplace-lib.php');
require numart_path('aksi/laporan-penjualan-lib.php');

mysqli_set_charset($conn, 'utf8mb4');

if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
    http_response_code(403);
    exit('Akses ditolak');
}

$cabang = (int) $sessionCabang;
$filters = lpj_parse_filters($conn, $_GET, $cabang, (string) $levelLogin);
$mode = trim((string) ($_GET['mode'] ?? 'transaksi'));
$dari = $filters['dari'];
$sampai = $filters['sampai'];
$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';
$toko = lpj_get_toko($conn, $cabang);
$summary = lpj_fetch_summary($conn, $filters);

$tokoNama = htmlspecialchars($toko['toko_nama'] ?? 'Toko', ENT_QUOTES, 'UTF-8');
$tokoAlamat = htmlspecialchars($toko['toko_alamat'] ?? '', ENT_QUOTES, 'UTF-8');
$tokoKota = htmlspecialchars($toko['toko_kota'] ?? '', ENT_QUOTES, 'UTF-8');

if ($mode === 'detail') {
    $docTitle = 'Laporan Detail Item Penjualan';
    $rows = lpj_fetch_detail_item($conn, $filters);
} elseif ($mode === 'customer') {
    $docTitle = 'Laporan Rekap Penjualan per Customer';
    $rows = lpj_fetch_per_customer($conn, $filters);
} else {
    $mode = 'transaksi';
    $docTitle = 'Laporan Transaksi Penjualan';
    $rows = lpj_fetch_transaksi($conn, $filters);
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
    @media print { .no-print { display: none !important; } body { padding: 0; } }
    h1 { font-size: 14pt; margin: 0 0 4px; text-align: center; text-transform: uppercase; }
    h2 { font-size: 11pt; margin: 0 0 8px; text-align: center; font-weight: normal; }
    .meta { text-align: center; margin-bottom: 10px; font-size: 9pt; line-height: 1.5; }
    .summary { margin-bottom: 12px; font-size: 8.5pt; border: 1px solid #ccc; padding: 8px; background: #f8f9fa; }
    .summary span { margin-right: 16px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border: 1px solid #333; padding: 4px 5px; vertical-align: top; }
    th { background: #1e3a5f; color: #fff; font-size: 8pt; text-align: center; }
    td.num { text-align: right; white-space: nowrap; }
    td.center { text-align: center; }
    tfoot td { font-weight: bold; background: #fff3cd; }
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
    Periode: <strong><?= tanggal_indo($dari); ?></strong> s/d <strong><?= tanggal_indo($sampai); ?></strong><br>
    Dicetak: <?= date('d F Y H:i'); ?> | Cabang: <?= (int) $cabang; ?>
  </div>

  <div class="summary">
    <span><strong>Transaksi:</strong> <?= (int) $summary['jumlah_transaksi']; ?></span>
    <span><strong>Total Penjualan:</strong> <?= lpj_format_rupiah($summary['total_penjualan']); ?></span>
    <span><strong>Lunas:</strong> <?= lpj_format_rupiah($summary['total_lunas']); ?></span>
    <span><strong>Piutang:</strong> <?= lpj_format_rupiah($summary['total_piutang']); ?></span>
    <span><strong>Sisa Piutang:</strong> <?= lpj_format_rupiah($summary['sisa_piutang']); ?></span>
    <span><strong>Laba Kotor:</strong> <?= lpj_format_rupiah($summary['total_laba_kotor']); ?></span>
    <span><strong>Tunai:</strong> <?= lpj_format_rupiah($summary['total_tunai']); ?></span>
    <span><strong>Transfer:</strong> <?= lpj_format_rupiah($summary['total_transfer']); ?></span>
  </div>

  <?php if ($mode === 'transaksi'): ?>
  <table>
    <thead>
      <tr>
        <th>No</th><th>Invoice</th><th>Tgl</th><th>Customer</th><th>Kasir</th><th>Metode</th>
        <th>Item</th><th>Qty</th><th>Sub Total</th><th>Diskon</th><th>Ongkir</th><th>Bayar</th><th>Sisa</th><th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td class="center"><?= (int) $r['no']; ?></td>
        <td><?= htmlspecialchars($r['penjualan_invoice'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= htmlspecialchars($r['invoice_tgl'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($r['customer_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($r['kasir_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= htmlspecialchars($r['metode_bayar'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= (int) $r['jumlah_item']; ?></td>
        <td class="num"><?= lpj_format_qty($r['total_qty']); ?></td>
        <td class="num"><?= lpj_format_rupiah($r['invoice_sub_total']); ?></td>
        <td class="num"><?= lpj_format_rupiah($r['invoice_diskon']); ?></td>
        <td class="num"><?= lpj_format_rupiah($r['invoice_ongkir']); ?></td>
        <td class="num"><?= lpj_format_rupiah($r['invoice_bayar']); ?></td>
        <td class="num"><?= lpj_format_rupiah($r['sisa_piutang']); ?></td>
        <td class="center"><?= htmlspecialchars($r['status_bayar'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="8" class="num">TOTAL</td>
        <td class="num"><?= lpj_format_rupiah($summary['total_penjualan']); ?></td>
        <td class="num"><?= lpj_format_rupiah($summary['total_diskon']); ?></td>
        <td class="num"><?= lpj_format_rupiah($summary['total_ongkir']); ?></td>
        <td colspan="3"></td>
      </tr>
    </tfoot>
  </table>

  <?php elseif ($mode === 'detail'): ?>
  <?php $totalSub = $totalLaba = 0; ?>
  <table>
    <thead>
      <tr>
        <th>No</th><th>Invoice</th><th>Tgl</th><th>Kode</th><th>Nama</th><th>Kat.</th><th>Sat.</th>
        <th>Qty</th><th>Harga</th><th>Subtotal</th><th>Laba</th><th>Customer</th><th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): $totalSub += $r['subtotal']; $totalLaba += $r['laba_kotor']; ?>
      <tr>
        <td class="center"><?= (int) $r['no']; ?></td>
        <td><?= htmlspecialchars($r['penjualan_invoice'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= htmlspecialchars($r['invoice_tgl'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($r['barang_kode'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($r['barang_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($r['kategori_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= htmlspecialchars($r['satuan_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="num"><?= lpj_format_qty($r['barang_qty']); ?></td>
        <td class="num"><?= lpj_format_rupiah($r['keranjang_harga']); ?></td>
        <td class="num"><?= lpj_format_rupiah($r['subtotal']); ?></td>
        <td class="num"><?= lpj_format_rupiah($r['laba_kotor']); ?></td>
        <td><?= htmlspecialchars($r['customer_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= htmlspecialchars($r['status_bayar'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="9" class="num">TOTAL</td>
        <td class="num"><?= lpj_format_rupiah($totalSub); ?></td>
        <td class="num"><?= lpj_format_rupiah($totalLaba); ?></td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>

  <?php else: ?>
  <?php $tJual = $tLunas = $tPiut = $tSisa = 0; ?>
  <table>
    <thead>
      <tr>
        <th>No</th><th>Customer</th><th>Jumlah Trx</th><th>Total Qty</th>
        <th>Total Penjualan</th><th>Lunas</th><th>Piutang</th><th>Sisa Piutang</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r):
        $tJual += $r['total_penjualan'];
        $tLunas += $r['total_lunas'];
        $tPiut += $r['total_piutang'];
        $tSisa += $r['sisa_piutang'];
      ?>
      <tr>
        <td class="center"><?= (int) $r['no']; ?></td>
        <td><?= htmlspecialchars($r['customer_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= (int) $r['jumlah_transaksi']; ?></td>
        <td class="num"><?= lpj_format_qty($r['total_qty']); ?></td>
        <td class="num"><?= lpj_format_rupiah($r['total_penjualan']); ?></td>
        <td class="num"><?= lpj_format_rupiah($r['total_lunas']); ?></td>
        <td class="num"><?= lpj_format_rupiah($r['total_piutang']); ?></td>
        <td class="num"><?= lpj_format_rupiah($r['sisa_piutang']); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="4" class="num">TOTAL</td>
        <td class="num"><?= lpj_format_rupiah($tJual); ?></td>
        <td class="num"><?= lpj_format_rupiah($tLunas); ?></td>
        <td class="num"><?= lpj_format_rupiah($tPiut); ?></td>
        <td class="num"><?= lpj_format_rupiah($tSisa); ?></td>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>

  <div class="footer">Dokumen dicetak otomatis dari sistem POS Numart untuk keperluan audit internal.</div>

  <div class="ttd">
    <div class="ttd-box"><div>Mengetahui,</div><div class="ttd-line">Manager / Supervisor</div></div>
    <div class="ttd-box"><div>Diperiksa,</div><div class="ttd-line">Bagian Keuangan</div></div>
    <div class="ttd-box"><div>Dibuat,</div><div class="ttd-line">Petugas / Kasir</div></div>
  </div>

  <?php if ($autoPrint): ?>
  <script>window.onload = function () { window.print(); };</script>
  <?php endif; ?>
</body>
</html>
