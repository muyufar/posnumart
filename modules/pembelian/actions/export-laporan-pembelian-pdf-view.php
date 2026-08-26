<?php
/** @var string $docTitle @var string $mode @var array $rows @var array $summary @var string $dari @var string $sampai @var int $cabang @var bool $autoPrint @var string $tokoNama @var string $tokoAlamat @var string $tokoKota */
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
    <span><strong>Total Pembelian:</strong> <?= lp_format_rupiah($summary['total_pembelian']); ?></span>
    <span><strong>Cash:</strong> <?= lp_format_rupiah($summary['total_cash']); ?></span>
    <span><strong>Hutang:</strong> <?= lp_format_rupiah($summary['total_hutang']); ?></span>
    <span><strong>Sisa Hutang:</strong> <?= lp_format_rupiah($summary['sisa_hutang']); ?></span>
    <span><strong>Total Qty:</strong> <?= lp_format_qty($summary['total_qty']); ?></span>
  </div>

  <?php if ($mode === 'transaksi'): ?>
  <table>
    <thead>
      <tr>
        <th>No</th><th>No. Invoice</th><th>Tanggal</th><th>Supplier</th><th>Kasir</th>
        <th>Item</th><th>Qty</th><th>Total</th><th>Bayar</th><th>Sisa</th><th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td class="center"><?= (int) $r['no']; ?></td>
        <td><?= htmlspecialchars($r['pembelian_invoice'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= htmlspecialchars($r['invoice_tgl'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($r['supplier_label'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($r['kasir_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= (int) $r['jumlah_item']; ?></td>
        <td class="num"><?= lp_format_qty($r['total_qty']); ?></td>
        <td class="num"><?= lp_format_rupiah($r['invoice_total']); ?></td>
        <td class="num"><?= lp_format_rupiah($r['invoice_bayar']); ?></td>
        <td class="num"><?= lp_format_rupiah($r['sisa_hutang']); ?></td>
        <td class="center"><?= htmlspecialchars($r['status_bayar'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="7" class="num">TOTAL</td>
        <td class="num"><?= lp_format_rupiah($summary['total_pembelian']); ?></td>
        <td colspan="3"></td>
      </tr>
    </tfoot>
  </table>

  <?php elseif ($mode === 'detail'): ?>
  <?php $totalSub = $tQty = 0; ?>
  <table>
    <thead>
      <tr>
        <th>No</th><th>Invoice</th><th>Tgl</th><th>Kode</th><th>Nama Barang</th><th>Kat.</th><th>Sat.</th>
        <th>Qty</th><th>Harga Beli</th><th>Subtotal</th><th>Supplier</th><th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): $totalSub += $r['subtotal']; $tQty += $r['barang_qty']; ?>
      <tr>
        <td class="center"><?= (int) $r['no']; ?></td>
        <td><?= htmlspecialchars($r['pembelian_invoice'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= htmlspecialchars($r['invoice_tgl'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($r['barang_kode'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($r['barang_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($r['kategori_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= htmlspecialchars($r['satuan_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="num"><?= lp_format_qty($r['barang_qty']); ?></td>
        <td class="num"><?= lp_format_rupiah($r['barang_harga_beli']); ?></td>
        <td class="num"><?= lp_format_rupiah($r['subtotal']); ?></td>
        <td><?= htmlspecialchars($r['supplier_label'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= htmlspecialchars($r['status_bayar'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="7" class="num">TOTAL</td>
        <td class="num"><?= lp_format_qty($tQty); ?></td>
        <td></td>
        <td class="num"><?= lp_format_rupiah($totalSub); ?></td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>

  <?php elseif ($mode === 'barang'): ?>
  <?php $totalSub = $tQty = 0; ?>
  <table>
    <thead>
      <tr>
        <th>No</th><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th>Satuan</th>
        <th>Trx</th><th>Qty</th><th>Harga Beli Avg</th><th>Total Pembelian</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): $totalSub += $r['total_pembelian']; $tQty += $r['total_qty']; ?>
      <tr>
        <td class="center"><?= (int) $r['no']; ?></td>
        <td><?= htmlspecialchars($r['barang_kode'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($r['barang_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= htmlspecialchars($r['kategori_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= htmlspecialchars($r['satuan_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= (int) $r['jumlah_transaksi']; ?></td>
        <td class="num"><?= lp_format_qty($r['total_qty']); ?></td>
        <td class="num"><?= lp_format_rupiah($r['harga_beli_avg']); ?></td>
        <td class="num"><?= lp_format_rupiah($r['total_pembelian']); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="6" class="num">TOTAL</td>
        <td class="num"><?= lp_format_qty($tQty); ?></td>
        <td></td>
        <td class="num"><?= lp_format_rupiah($totalSub); ?></td>
      </tr>
    </tfoot>
  </table>

  <?php else: ?>
  <?php $tPem = $tCash = $tHut = $tSisa = 0; ?>
  <table>
    <thead>
      <tr>
        <th>No</th><th>Supplier</th><th>Jumlah Trx</th><th>Total Qty</th>
        <th>Total Pembelian</th><th>Cash</th><th>Hutang</th><th>Sisa Hutang</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r):
        $tPem += $r['total_pembelian'];
        $tCash += $r['total_cash'];
        $tHut += $r['total_hutang'];
        $tSisa += $r['sisa_hutang'];
      ?>
      <tr>
        <td class="center"><?= (int) $r['no']; ?></td>
        <td><?= htmlspecialchars($r['supplier_label'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="center"><?= (int) $r['jumlah_transaksi']; ?></td>
        <td class="num"><?= lp_format_qty($r['total_qty']); ?></td>
        <td class="num"><?= lp_format_rupiah($r['total_pembelian']); ?></td>
        <td class="num"><?= lp_format_rupiah($r['total_cash']); ?></td>
        <td class="num"><?= lp_format_rupiah($r['total_hutang']); ?></td>
        <td class="num"><?= lp_format_rupiah($r['sisa_hutang']); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="4" class="num">TOTAL</td>
        <td class="num"><?= lp_format_rupiah($tPem); ?></td>
        <td class="num"><?= lp_format_rupiah($tCash); ?></td>
        <td class="num"><?= lp_format_rupiah($tHut); ?></td>
        <td class="num"><?= lp_format_rupiah($tSisa); ?></td>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>

  <div class="footer">
    Dokumen ini dicetak otomatis dari sistem POS Numart untuk keperluan audit internal.
  </div>

  <div class="ttd">
    <div class="ttd-box">
      <div>Mengetahui,</div>
      <div class="ttd-line">Manager / Supervisor</div>
    </div>
    <div class="ttd-box">
      <div>Diperiksa,</div>
      <div class="ttd-line">Bagian Keuangan</div>
    </div>
    <div class="ttd-box">
      <div>Dibuat,</div>
      <div class="ttd-line">Petugas / Kasir</div>
    </div>
  </div>

  <?php if ($autoPrint): ?>
  <script>window.onload = function () { window.print(); };</script>
  <?php endif; ?>
</body>
</html>
