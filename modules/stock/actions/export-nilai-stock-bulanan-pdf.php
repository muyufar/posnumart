<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
require numart_path('aksi/koneksi.php');
require numart_path('aksi/halau.php');
require numart_path('aksi/functions.php');
require numart_path('aksi/stock-opname-laporan-lib.php');

mysqli_set_charset($conn, 'utf8mb4');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$cabang = 0;
if ($userId > 0) {
    $res = mysqli_query($conn, 'SELECT user_cabang FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
    if ($res && ($ru = mysqli_fetch_assoc($res))) {
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

$tokoNama   = htmlspecialchars($toko['toko_nama']   ?? 'Toko', ENT_QUOTES, 'UTF-8');
$tokoAlamat = htmlspecialchars($toko['toko_alamat'] ?? '',      ENT_QUOTES, 'UTF-8');
$tokoKota   = htmlspecialchars($toko['toko_kota']   ?? '',      ENT_QUOTES, 'UTF-8');

/* ── Data nilai akhir per bulan ── */
$result   = so_laporan_fetch_nilai_per_bulan($conn, $cabang, $dari, $sampai);
$months   = $result['months'];
$itemRows = $result['rows'];
$perBulan = so_laporan_total_nilai_per_bulan($months, $itemRows);

/* ── Data mutasi per bulan ── */
$mutasi = so_laporan_mutasi_per_bulan($conn, $cabang, $dari, $sampai);

/* ── Nilai awal bulan pertama: metode MAJU (forward rolling) ── */
$tgl_sebelum_pertama = date('Y-m-d', strtotime($dari . ' -1 day'));
$nilai_awal_pertama  = so_laporan_persediaan_forward($conn, $cabang, $tgl_sebelum_pertama);

/* ── Gabungkan: nilai_akhir dihitung secara MAJU (forward rolling) ── */
$rows = [];
$prevAkhir = $nilai_awal_pertama;
foreach ($perBulan as $i => $b) {
    $m = $mutasi[$i] ?? [];

    $nilai_akhir_fwd = $prevAkhir
        + (float) ($m['nilai_pembelian']       ?? 0)
        - (float) ($m['nilai_retur_beli']      ?? 0)
        + (float) ($m['nilai_transfer_masuk']  ?? 0)
        + (float) ($m['nilai_retur_jual']      ?? 0)
        - (float) ($m['nilai_penjualan_hpp']   ?? 0)
        - (float) ($m['nilai_transfer_keluar'] ?? 0)
        + (float) ($m['nilai_opname']          ?? 0);
    $nilai_akhir_fwd = max(0.0, $nilai_akhir_fwd);

    $rows[] = [
        'label'                 => $b['label'],
        'nilai_awal'            => $prevAkhir,
        'nilai_pembelian'       => $m['nilai_pembelian']        ?? 0,
        'nilai_retur_beli'      => $m['nilai_retur_beli']       ?? 0,
        'nilai_transfer_masuk'  => $m['nilai_transfer_masuk']   ?? 0,
        'nilai_retur_jual'      => $m['nilai_retur_jual']       ?? 0,
        'nilai_penjualan_jual'  => $m['nilai_penjualan_jual']   ?? 0,
        'nilai_penjualan_hpp'   => $m['nilai_penjualan_hpp']    ?? 0,
        'nilai_transfer_keluar' => $m['nilai_transfer_keluar']  ?? 0,
        'nilai_opname'          => $m['nilai_opname']           ?? 0,
        'nilai_akhir'           => $nilai_akhir_fwd,
    ];
    $prevAkhir = $nilai_akhir_fwd;
}

function rpf($n) {
    if ($n == 0) return '<span style="color:#999">—</span>';
    $neg = $n < 0;
    $s = 'Rp ' . number_format(abs($n), 0, ',', '.');
    return $neg ? '<span style="color:#c0392b">(' . $s . ')</span>' : $s;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Mutasi Nilai Stock per Bulan — <?= $tokoNama ?></title>
  <style>
    @page  { size: A4 landscape; margin: 10mm 12mm; }
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 7.5pt; color: #111;
           margin: 0; padding: 8mm 10mm; }
    .no-print { margin-bottom: 12px; }
    @media print { .no-print { display: none !important; } body { padding: 0; } }

    h1  { font-size: 12pt; margin: 0 0 2px; text-align: center; text-transform: uppercase; }
    h2  { font-size: 9.5pt; margin: 0 0 4px; text-align: center; font-weight: normal; }
    .meta { text-align: center; font-size: 7.5pt; line-height: 1.7; color: #444; margin-bottom: 8px; }
    hr   { border: none; border-top: 2px solid #1e3a5f; margin: 5px 0 10px; }

    table { width: 100%; border-collapse: collapse; }

    /* Group header baris 1 */
    .grp-in  { background: #1b5e20 !important; }
    .grp-out { background: #b71c1c !important; }
    .grp-so  { background: #e65100 !important; }
    .grp-def { background: #1e3a5f !important; }

    thead tr.h1 th, thead tr.h2 th {
      color: #fff; padding: 5px 4px; border: 1px solid rgba(255,255,255,.3);
      text-align: center; font-size: 7pt; line-height: 1.25;
    }
    thead tr.h1 th { font-size: 7.5pt; }

    tbody td { border: 1px solid #ccc; padding: 5px 4px; font-size: 7.5pt; }
    td.num  { text-align: right; white-space: nowrap; }
    td.ctr  { text-align: center; }
    td.in-val  { background: #f1f8e9; }
    td.out-val { background: #fce4ec; }
    td.so-val  { background: #fff3e0; }
    td.akh-val { background: #e3f2fd; font-weight: bold; }
    .alt-row td { filter: brightness(0.97); }
    .total-row td {
      background: #fff3cd !important; font-weight: bold;
      border-top: 2px solid #888; font-size: 7.5pt;
    }

    .keterangan { margin-top: 10px; font-size: 7pt; color: #555; line-height: 1.6;
                  border-top: 1px solid #ccc; padding-top: 6px; }
    .ttd { margin-top: 18px; display: flex; justify-content: space-between; }
    .ttd-box { width: 30%; text-align: center; font-size: 7.5pt; }
    .ttd-line { border-top: 1px solid #333; margin-top: 36px; padding-top: 3px; }
  </style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()">&#128438; Cetak / Simpan PDF</button>
  <button onclick="window.close()" style="margin-left:8px">&#10005; Tutup</button>
</div>

<h1>Laporan Mutasi Nilai Persediaan Barang per Bulan</h1>
<h2><?= $tokoNama ?></h2>
<div class="meta">
  <?php if ($tokoAlamat !== ''): ?><?= $tokoAlamat ?><?= $tokoKota !== '' ? ', '.$tokoKota : '' ?><br><?php endif; ?>
  Periode: <strong><?= tanggal_indo($dari) ?></strong> s/d <strong><?= tanggal_indo($sampai) ?></strong>
  &nbsp;|&nbsp; <?= count($months) ?> Bulan
  &nbsp;|&nbsp; Dicetak: <?= date('d F Y H:i') ?>
  &nbsp;|&nbsp; Cabang: <?= (int) $cabang ?>
</div>
<hr>

<?php if (empty($rows)): ?>
  <p>Tidak ada data pada periode yang dipilih.</p>
<?php else: ?>

<table>
  <thead>
    <tr class="h1">
      <th rowspan="2" class="grp-def" style="width:30px">No</th>
      <th rowspan="2" class="grp-def" style="width:65px">Bulan</th>
      <th rowspan="2" class="grp-def" style="width:80px">Nilai Persediaan Awal</th>
      <!-- MASUK -->
      <th colspan="4" class="grp-in">PENAMBAH PERSEDIAAN (+)</th>
      <!-- INFO penjualan -->
      <th rowspan="2" style="background:#1565C0;width:72px">Nilai Penjualan Revenue<br><small>(Info — Harga Jual)</small></th>
      <!-- KELUAR -->
      <th colspan="2" class="grp-out">PENGURANG PERSEDIAAN (−)</th>
      <!-- SO -->
      <th rowspan="2" class="grp-so" style="width:64px">Selisih SO (+/−)</th>
      <!-- AKHIR -->
      <th rowspan="2" class="grp-def" style="width:72px">Nilai Persediaan Akhir</th>
    </tr>
    <tr class="h2">
      <th class="grp-in" style="width:62px">Pembelian Masuk</th>
      <th class="grp-in" style="width:54px">Retur Beli</th>
      <th class="grp-in" style="width:62px">Transfer Masuk</th>
      <th class="grp-in" style="width:58px">Retur Penjualan</th>
      <th class="grp-out" style="width:64px">HPP (Harga Beli Terjual)</th>
      <th class="grp-out" style="width:62px">Transfer Keluar</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $gt = array_fill_keys(['nilai_awal','nilai_pembelian','nilai_retur_beli','nilai_transfer_masuk',
                            'nilai_retur_jual','nilai_penjualan_jual','nilai_penjualan_hpp',
                            'nilai_transfer_keluar','nilai_opname','nilai_akhir'], 0.0);
    foreach ($rows as $i => $b):
        foreach (array_keys($gt) as $k) $gt[$k] += $b[$k];
    ?>
    <tr class="<?= $i % 2 === 1 ? 'alt-row' : '' ?>">
      <td class="ctr"><?= $i + 1 ?></td>
      <td><strong><?= htmlspecialchars($b['label'], ENT_QUOTES, 'UTF-8') ?></strong></td>
      <td class="num"><?= rpf($b['nilai_awal']) ?></td>
      <td class="num in-val"><?= rpf($b['nilai_pembelian']) ?></td>
      <td class="num in-val"><?= rpf($b['nilai_retur_beli']) ?></td>
      <td class="num in-val"><?= rpf($b['nilai_transfer_masuk']) ?></td>
      <td class="num in-val"><?= rpf($b['nilai_retur_jual']) ?></td>
      <td class="num" style="background:#E3F2FD"><?= rpf($b['nilai_penjualan_jual']) ?></td>
      <td class="num out-val"><?= rpf($b['nilai_penjualan_hpp']) ?></td>
      <td class="num out-val"><?= rpf($b['nilai_transfer_keluar']) ?></td>
      <td class="num so-val"><?= rpf($b['nilai_opname']) ?></td>
      <td class="num akh-val"><?= rpf($b['nilai_akhir']) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="total-row">
      <td colspan="2" class="ctr">TOTAL / RATA-RATA</td>
      <td class="num"><?= rpf($rows[0]['nilai_awal']) ?>*</td>
      <td class="num"><?= rpf($gt['nilai_pembelian']) ?></td>
      <td class="num"><?= rpf($gt['nilai_retur_beli']) ?></td>
      <td class="num"><?= rpf($gt['nilai_transfer_masuk']) ?></td>
      <td class="num"><?= rpf($gt['nilai_retur_jual']) ?></td>
      <td class="num"><?= rpf($gt['nilai_penjualan_jual']) ?></td>
      <td class="num"><?= rpf($gt['nilai_penjualan_hpp']) ?></td>
      <td class="num"><?= rpf($gt['nilai_transfer_keluar']) ?></td>
      <td class="num"><?= rpf($gt['nilai_opname']) ?></td>
      <td class="num"><?= rpf(end($rows)['nilai_akhir']) ?>*</td>
    </tr>
  </tbody>
</table>

<div class="keterangan">
  <strong>Keterangan:</strong>
  &nbsp; * Nilai Persediaan Awal/Akhir = akhir hari sebelum/terakhir periode (rekonstruksi historis).
  &nbsp;|&nbsp; <span style="background:#E3F2FD;padding:0 3px"><strong>[INFO] Nilai Penjualan Revenue</strong> = harga jual ke customer (invoice_sub_total) — cocokkan dengan Laporan Penjualan Periode. <em>Bukan pengurang stok.</em></span>
  &nbsp;|&nbsp; <span style="background:#fce4ec;padding:0 3px"><strong>HPP</strong> = harga BELI barang terjual (invoice_total_beli) — inilah yang mengurangi nilai persediaan.</span>
  &nbsp;|&nbsp; Revenue ≠ HPP karena Revenue = HPP + Laba Kotor.
  &nbsp;|&nbsp; Selisih SO = soh_selisih &times; harga_beli dari SO selesai.
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
