<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin === 'kurir') {
    echo "<script>document.location.href = 'bo';</script>";
    exit;
}

require_once __DIR__ . '/aksi/stock-opname-laporan-lib.php';

$id = abs((int) base64_decode($_GET['id'] ?? ''));
$dari = so_laporan_sanitize_date((string) ($_GET['dari'] ?? ''), date('Y-m-01'));
$sampai = so_laporan_sanitize_date((string) ($_GET['sampai'] ?? ''), date('Y-m-d'));
$cabang = (int) $sessionCabang;

$sesi = so_laporan_fetch_sesi_by_id($conn, $id, $cabang);
if ($sesi === null) {
    echo "<script>alert('Sesi stock opname tidak ditemukan');document.location.href='stock-opname-laporan';</script>";
    exit;
}

$items = so_laporan_fetch_hasil_sesi($conn, $id, $cabang);
$toko = so_laporan_get_toko($conn, $cabang);
$noSesi = 'SO-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
$tipeLabel = so_laporan_tipe_label((int) ($sesi['stock_opname_tipe'] ?? 0));
$statusLabel = ((int) ($sesi['stock_opname_status'] ?? 0) > 0) ? 'Selesai' : 'Proses';

$sumSistem = 0.0;
$sumFisik = 0.0;
$sumSelisih = 0.0;
foreach ($items as $it) {
    $sumSistem += (float) ($it['soh_barang_stock_system'] ?? 0);
    $sumFisik += (float) ($it['soh_stock_fisik'] ?? 0);
    $sumSelisih += (float) ($it['soh_selisih'] ?? 0);
}

$backUrl = 'stock-opname-laporan?dari=' . urlencode($dari) . '&sampai=' . urlencode($sampai);
$exportQs = 'id=' . urlencode(base64_encode((string) $id));
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Laporan Hasil Stock Opname</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8'); ?>">Laporan Stock Opname</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($noSesi, ENT_QUOTES, 'UTF-8'); ?></li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="callout callout-info">
        <h5><i class="fas fa-clipboard-check"></i> Berita Acara Stock Opname</h5>
        <p class="mb-0">
          Sesi <strong><?= htmlspecialchars($noSesi, ENT_QUOTES, 'UTF-8'); ?></strong> —
          Tanggal proses <strong><?= tanggal_indo($sesi['stock_opname_date_proses'] ?? ''); ?></strong>
          (<?= htmlspecialchars($tipeLabel, ENT_QUOTES, 'UTF-8'); ?>, Status: <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>)
        </p>
      </div>

      <div class="invoice p-3 mb-3" id="areaCetak">
        <div class="row invoice-info mb-3">
          <div class="col-sm-4">
            <h5><b>Toko</b></h5>
            <address>
              <strong><?= htmlspecialchars($toko['toko_nama'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong><br>
              <?= htmlspecialchars($toko['toko_alamat'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
              Tlpn/WA: <?= htmlspecialchars($toko['toko_tlpn'] ?? '', ENT_QUOTES, 'UTF-8'); ?> / <?= htmlspecialchars($toko['toko_wa'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
              Email: <?= htmlspecialchars($toko['toko_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
            </address>
          </div>
          <div class="col-sm-4">
            <h5><b>Petugas / Penanggung Jawab</b></h5>
            <address>
              Dibuat: <strong><?= htmlspecialchars($sesi['user_create_nama'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></strong><br>
              <?= htmlspecialchars($sesi['stock_opname_datetime_create'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
              Eksekusi: <strong><?= htmlspecialchars($sesi['user_eksekusi_nama'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></strong><br>
              Dijadwalkan: <?= tanggal_indo($sesi['stock_opname_date_proses'] ?? ''); ?>
            </address>
          </div>
          <div class="col-sm-4">
            <h5><b>Penutupan Sesi</b></h5>
            <address>
              Upload: <strong><?= htmlspecialchars($sesi['user_upload_nama'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></strong><br>
              <?= htmlspecialchars($sesi['stock_opname_datetime_upload'] ?? '-', ENT_QUOTES, 'UTF-8'); ?><br>
              Jumlah barang dihitung: <strong><?= count($items); ?></strong>
            </address>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-striped" id="tblDetail">
            <thead class="thead-dark">
              <tr>
                <th style="width:4%">No</th>
                <th style="width:14%">Kode / Barcode</th>
                <th>Nama Barang</th>
                <th style="width:7%">Satuan</th>
                <th style="width:10%">Stock Sistem</th>
                <th style="width:10%">Stock Fisik</th>
                <th style="width:9%">Selisih</th>
                <th style="width:18%">Catatan</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($items)): ?>
              <tr><td colspan="8" class="text-center text-muted">Belum ada barang tercatat pada sesi ini.</td></tr>
              <?php else: ?>
              <?php $no = 1; foreach ($items as $row): ?>
              <?php
                $sel = (float) ($row['soh_selisih'] ?? 0);
                $cls = $sel > 0 ? 'text-success' : ($sel < 0 ? 'text-danger' : '');
              ?>
              <tr>
                <td class="text-center"><?= $no++; ?></td>
                <td><?= htmlspecialchars($row['soh_barang_kode'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars($row['barang_nama'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="text-center"><?= htmlspecialchars($row['satuan_nama'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="text-right"><?= so_laporan_format_qty($row['soh_barang_stock_system'] ?? 0); ?></td>
                <td class="text-right"><?= so_laporan_format_qty($row['soh_stock_fisik'] ?? 0); ?></td>
                <td class="text-right font-weight-bold <?= $cls; ?>"><?= so_laporan_format_qty($sel); ?></td>
                <td><?= htmlspecialchars($row['soh_note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
              <?php endforeach; ?>
              <tr class="bg-light font-weight-bold">
                <td colspan="4" class="text-right">TOTAL</td>
                <td class="text-right"><?= so_laporan_format_qty($sumSistem); ?></td>
                <td class="text-right"><?= so_laporan_format_qty($sumFisik); ?></td>
                <td class="text-right"><?= so_laporan_format_qty($sumSelisih); ?></td>
                <td></td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="row mt-4 d-none d-print-flex">
          <div class="col-4 text-center"><br><br>Mengetahui,<br>Manager<br><br>________________</div>
          <div class="col-4 text-center"><br><br>Diperiksa,<br>Supervisor<br><br>________________</div>
          <div class="col-4 text-center"><br><br>Dibuat,<br>Petugas SO<br><br>________________</div>
        </div>
      </div>

      <div class="row no-print mb-4">
        <div class="col-12">
          <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Kembali ke Laporan</a>
          <a href="export-stock-opname-sesi-excel.php?<?= $exportQs; ?>" class="btn btn-success"><i class="fa fa-file-excel"></i> Export Excel</a>
          <button type="button" class="btn btn-danger" onclick="window.open('export-stock-opname-sesi-pdf.php?<?= $exportQs; ?>','_blank')"><i class="fa fa-file-pdf"></i> Export PDF</button>
          <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fa fa-print"></i> Cetak</button>
        </div>
      </div>
    </div>
  </section>
</div>

<style>
@media print {
  .main-sidebar, .main-header, .main-footer, .no-print, .content-header { display: none !important; }
  .content-wrapper { margin: 0 !important; padding: 0 !important; }
}
</style>

<?php include '_footer.php'; ?>
