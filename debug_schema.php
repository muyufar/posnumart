<?php
session_start();
require 'aksi/koneksi.php';
require_once 'aksi/stock-opname-laporan-lib.php';
mysqli_set_charset($conn, 'utf8mb4');

$cabang    = (int)($_GET['cabang'] ?? 1);
$tgl_awal  = $_GET['dari']  ?? '2026-01-01';
$tgl_akhir = $_GET['sampai'] ?? '2026-01-31';

echo "<!DOCTYPE html><html><head><title>Debug Persediaan Awal</title>
<style>
body{font-family:Arial,sans-serif;padding:20px;font-size:14px;max-width:800px}
.box{border:2px solid #4a9eff;background:#e8f4ff;padding:16px;border-radius:8px;margin:12px 0}
.box.green{border-color:#28a745;background:#e8f9ec}
.box.red{border-color:#dc3545;background:#fde8e8}
.box.yellow{border-color:#ffc107;background:#fff9e0}
table{border-collapse:collapse;width:100%;margin:8px 0}
td,th{border:1px solid #ccc;padding:6px 12px} th{background:#f0f0f0}
td.num{text-align:right;font-family:monospace}
.big{font-size:20px;font-weight:bold}
.ok{color:green} .err{color:red} .note{color:#666;font-size:12px}
</style></head><body>";

echo "<h2>🔍 Debug Persediaan Awal — Cabang $cabang</h2>";
echo "<form method=get style='background:#f5f5f5;padding:12px;border-radius:6px;margin-bottom:16px'>
  Cabang: <input name=cabang value='$cabang' style='width:60px;padding:4px'>
  &nbsp; Dari: <input name=dari value='$tgl_awal' style='width:120px;padding:4px'>
  &nbsp; Sampai: <input name=sampai value='$tgl_akhir' style='width:120px;padding:4px'>
  <button style='padding:5px 16px;background:#2196F3;color:white;border:none;border-radius:4px'>▶ Analisa</button>
</form>";

$dariEsc   = mysqli_real_escape_string($conn, $tgl_awal);
$sampaiEsc = mysqli_real_escape_string($conn, $tgl_akhir);

/* ── 1. Persediaan AKHIR (akhir periode, rekonstruksi dari stok sekarang) ── */
$p_akhir = so_laporan_nilai_persediaan_pada_tanggal($conn, $cabang, $tgl_akhir);

/* ── 2. HPP penjualan dalam periode ── */
$r = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(invoice_total_beli),0) AS hpp, COALESCE(SUM(invoice_sub_total),0) AS jual
     FROM invoice WHERE invoice_cabang=$cabang AND invoice_date BETWEEN '$dariEsc' AND '$sampaiEsc'
    ") ?: null);
$hpp  = (float)($r['hpp']  ?? 0);
$jual = (float)($r['jual'] ?? 0);

/* ── 3. Transfer Keluar periode ── */
$r = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(tpk.tpk_qty * b.barang_harga_beli),0) AS v
     FROM transfer_produk_keluar tpk JOIN barang b ON tpk.tpk_barang_id=b.barang_id AND b.barang_cabang=$cabang
     WHERE tpk.tpk_pengirim_cabang=$cabang AND tpk.tpk_date BETWEEN '$dariEsc' AND '$sampaiEsc'
    ") ?: null);
$tfk = (float)($r['v'] ?? 0);

/* ── 4. Transfer Masuk periode (JOIN via kode_slug karena tpk_barang_id = ID cabang pengirim) ── */
$r = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(tpk.tpk_qty * b.barang_harga_beli),0) AS v
     FROM transfer_produk_keluar tpk
     JOIN barang b ON tpk.tpk_kode_slug = b.barang_kode_slug AND b.barang_cabang = $cabang
     WHERE tpk.tpk_penerima_cabang=$cabang AND tpk.tpk_date BETWEEN '$dariEsc' AND '$sampaiEsc'
    ") ?: null);
$tfm = (float)($r['v'] ?? 0);

/* ── 5. Pembelian neto dalam periode ── */
$r = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(barang_qty * barang_harga_beli),0) AS v
     FROM pembelian WHERE pembelian_cabang=$cabang AND pembelian_date BETWEEN '$dariEsc' AND '$sampaiEsc'
    ") ?: null);
$pembelian = (float)($r['v'] ?? 0);

/* ── 6. SO adjustment dalam periode ── */
/* Diagnostic: cek status yang ada di database untuk cabang & periode ini */
$r_diag = mysqli_query($conn,
    "SELECT s.stock_opname_status, COUNT(*) AS cnt, COUNT(h.soh_id) AS hasil_cnt
     FROM stock_opname s
     LEFT JOIN stock_opname_hasil h ON h.soh_stock_opname_id = s.stock_opname_id
       AND h.soh_barang_cabang = $cabang
     WHERE s.stock_opname_cabang = $cabang
       AND s.stock_opname_date_proses BETWEEN '$dariEsc' AND '$sampaiEsc'
     GROUP BY s.stock_opname_status");
$so_diag = [];
if ($r_diag) while ($rd = mysqli_fetch_assoc($r_diag)) $so_diag[] = $rd;

$r = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(h.soh_selisih * b.barang_harga_beli),0) AS v
     FROM stock_opname_hasil h JOIN stock_opname s ON s.stock_opname_id=h.soh_stock_opname_id
     JOIN barang b ON b.barang_id=CAST(h.soh_barang_id AS UNSIGNED) AND b.barang_cabang=$cabang
     WHERE h.soh_barang_cabang=$cabang AND s.stock_opname_status > 0
       AND s.stock_opname_date_proses BETWEEN '$dariEsc' AND '$sampaiEsc'
    ") ?: null);
$so_adj = (float)($r['v'] ?? 0);

/* ── Hitung persediaan awal ── */
$p_awal = $p_akhir + $hpp + $tfk - $tfm - $pembelian - $so_adj;

$tgl_sebelum = date('d/m/Y', strtotime($tgl_awal . ' -1 day'));
$tgl_akhir_fmt = date('d/m/Y', strtotime($tgl_akhir));

?>
<div class="box green">
<b>💡 Logika yang Digunakan (Rumus Akuntansi — 1 Periode Saja):</b><br><br>
Persediaan Awal = Persediaan Akhir + HPP Terjual + Transfer Keluar − Transfer Masuk − Pembelian − SO Adj
</div>

<table>
<tr><th colspan="2" style="background:#333;color:white;font-size:15px">Breakdown Persediaan Awal per <?= $tgl_sebelum ?> (Awal <?= date('F Y', strtotime($tgl_awal)) ?>)</th></tr>

<tr>
  <td><b>Persediaan AKHIR</b> (<?= $tgl_akhir_fmt ?>)<br>
  <span class="note">Rekonstruksi stok aktif cabang ini dari kondisi saat ini ke tanggal akhir periode</span></td>
  <td class="num big">Rp <?= number_format($p_akhir) ?></td>
</tr>
<tr>
  <td><b>+ HPP Terjual</b> (invoice_total_beli periode ini)<br>
  <span class="note">Nilai harga beli dari barang yang terjual — stok berkurang akibat penjualan</span></td>
  <td class="num" style="color:green">+ Rp <?= number_format($hpp) ?></td>
</tr>
<tr>
  <td><b>+ Transfer Keluar</b> (cabang ini sbg pengirim)<br>
  <span class="note">Stok yang dikirim ke cabang lain — mengurangi stok cabang ini</span></td>
  <td class="num" style="color:green">+ Rp <?= number_format($tfk) ?></td>
</tr>
<tr>
  <td><b>− Transfer Masuk</b> (cabang ini sbg penerima, bersih dari tpk_keluar)<br>
  <span class="note">Stok yang diterima dari cabang lain — menambah stok</span></td>
  <td class="num" style="color:red">− Rp <?= number_format($tfm) ?></td>
</tr>
<tr>
  <td><b>− Pembelian Langsung</b><br>
  <span class="note">Pembelian langsung ke supplier (biasanya 0 untuk cabang retail)</span></td>
  <td class="num" style="color:red">− Rp <?= number_format($pembelian) ?></td>
</tr>
<tr>
  <td><b>− SO Adjustment</b><br>
  <span class="note">Penyesuaian stock opname (selisih fisik − sistem)</span>
  <?php if (!empty($so_diag)): ?>
    <br><span class="note" style="color:#c00">
    Status SO dalam periode:
    <?php foreach ($so_diag as $sd): ?>
      status=<?= $sd['stock_opname_status'] ?> (<?= $sd['cnt'] ?> sesi, <?= $sd['hasil_cnt'] ?> baris hasil)
    <?php endforeach; ?>
    </span>
  <?php else: ?>
    <br><span class="note" style="color:#c00">⚠ Tidak ada sesi SO dalam periode ini untuk cabang <?= $cabang ?></span>
  <?php endif; ?>
  </td>
  <td class="num" style="color:red">− Rp <?= number_format($so_adj) ?></td>
</tr>
<tr style="background:#fffde0;font-size:16px;font-weight:bold">
  <td>= <b>PERSEDIAAN AWAL</b> (<?= $tgl_sebelum ?>)</td>
  <td class="num big">Rp <?= number_format(max(0, $p_awal)) ?></td>
</tr>
</table>

<div class="box yellow">
<b>📌 Penjelasan Mengapa Persediaan Awal > Persediaan Akhir:</b><br><br>
Persediaan Awal (awal <?= date('F Y', strtotime($tgl_awal)) ?>) 
= Persediaan Akhir (akhir <?= date('F Y', strtotime($tgl_awal)) ?>)
<b> + HPP yang terjual selama periode ini</b><br><br>

Karena selama <?= date('F Y', strtotime($tgl_awal)) ?>, cabang ini <b>menjual barang seharga Rp <?= number_format($hpp) ?> (HPP)</b>
— artinya di awal bulan, stok lebih besar dari di akhir bulan.<br><br>

Selisih: Rp <?= number_format(max(0,$p_awal)) ?> − Rp <?= number_format($p_akhir) ?> = 
<b>Rp <?= number_format(max(0,$p_awal) - $p_akhir) ?></b>
(ini adalah neto stok yang keluar: HPP + TF_Keluar − TF_Masuk − Pembelian)
</div>

<div class="box">
<b>🧮 Verifikasi silang — Persediaan Akhir dari Stock Opname page:</b><br>
Nilai yang ditampilkan di <b>halaman Stock Opname → Nilai Persediaan (Beli)</b> untuk bulan 
<?= date('F Y', strtotime($tgl_awal)) ?> seharusnya mendekati: <b>Rp <?= number_format($p_akhir) ?></b><br><br>
Jika berbeda, berarti ada perbedaan cara menghitung persediaan_akhir antara kedua halaman tersebut.
</div>
<?php
echo "</body></html>";
