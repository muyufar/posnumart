<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
require_once 'aksi/pengadaan-gudang-lib.php';
require_once 'aksi/pengadaan-po-lib.php';

if (!pengadaan_gudang_can_access((int) $sessionCabang, (string) $levelLogin)) {
    echo "<script>document.location.href = 'bo';</script>";
    exit;
}

pengadaan_po_ensure_tables($conn);
$poFilter = (int) ($_GET['po'] ?? 0);
$lines = pengadaan_po_get_lines_tidak_datang($conn, $poFilter);
$total = count($lines);
?>
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1><i class="fa fa-box-open"></i> Barang PO Tidak Datang</h1>
          <p class="text-muted mb-0">Daftar barang yang ada di PO tetapi tidak masuk invoice (qty diterima = 0).</p>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="pengadaan-gudang">Pengadaan Gudang</a></li>
            <li class="breadcrumb-item active">Tidak Datang</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card card-outline card-secondary">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">
            <i class="fa fa-folder-open"></i>
            Folder khusus — <?= (int) $total; ?> barang
            <?php if ($poFilter > 0) : ?>
              (filter PO #<?= $poFilter; ?>)
            <?php endif; ?>
          </h3>
          <div>
            <?php if ($poFilter > 0) : ?>
              <a href="pengadaan-po-tidak-datang" class="btn btn-sm btn-outline-secondary">Semua PO</a>
            <?php endif; ?>
            <a href="pengadaan-gudang" class="btn btn-sm btn-primary"><i class="fa fa-warehouse"></i> Pusat Pengadaan</a>
          </div>
        </div>
        <div class="card-body p-0">
          <?php if ($lines === []) : ?>
            <p class="text-muted p-3 mb-0">Belum ada barang tidak datang. Item muncul di sini setelah PO dilanjutkan ke invoice dan masih ada baris dengan qty diterima 0.</p>
          <?php else : ?>
            <div class="table-responsive" style="max-height: calc(100vh - 260px); overflow: auto;">
              <table class="table table-sm table-striped table-bordered mb-0">
                <thead class="thead-dark" style="position: sticky; top: 0; z-index: 2;">
                  <tr>
                    <th style="width:40px">No</th>
                    <th>No PO</th>
                    <th>Supplier</th>
                    <th>Barcode</th>
                    <th>Nama Barang</th>
                    <th class="text-center">Qty PO</th>
                    <th>Satuan</th>
                    <th>Status PO</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $n = 1; foreach ($lines as $ln) : ?>
                    <tr>
                      <td><?= $n++; ?></td>
                      <td><strong><?= htmlspecialchars((string) ($ln['po_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                      <td><?= htmlspecialchars((string) ($ln['kode_suplier'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><code><?= htmlspecialchars((string) ($ln['barang_kode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                      <td><?= htmlspecialchars((string) ($ln['barang_nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                      <td class="text-center"><?= number_format((float) ($ln['qty_po'] ?? 0), 0, ',', '.'); ?></td>
                      <td><?= htmlspecialchars((string) ($ln['satuan_nama'] ?? 'PCS'), ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?= pengadaan_po_status_badge((string) ($ln['po_status'] ?? '')); ?></td>
                      <td>
                        <a href="pengadaan-po-detail?id=<?= (int) ($ln['po_id'] ?? 0); ?>" class="btn btn-xs btn-outline-info" title="Detail PO"><i class="fa fa-eye"></i></a>
                        <a href="pengadaan-po-receive?id=<?= (int) ($ln['po_id'] ?? 0); ?>" class="btn btn-xs btn-warning" title="Terima / scan ulang"><i class="fa fa-barcode"></i></a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
        <div class="card-footer">
          <small class="text-muted">Catatan: hanya barang yang <strong>sudah datang</strong> (qty diterima &gt; 0) yang masuk Invoice Pembelian. Folder ini untuk tracking sisa PO yang belum/tidak dikirim supplier.</small>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include '_footer.php'; ?>
</body>
</html>
