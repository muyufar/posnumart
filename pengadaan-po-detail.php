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

$poId = (int) ($_GET['id'] ?? 0);
pengadaan_po_ensure_tables($conn);
$po = pengadaan_po_get($conn, $poId);
if (!$po) {
    echo "<script>alert('PO tidak ditemukan'); document.location.href='pengadaan-gudang';</script>";
    exit;
}

$lines = pengadaan_po_get_lines($conn, $poId);
$waData = pengadaan_po_wa_data($conn, $poId);
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1><i class="fa fa-file-invoice"></i> Detail PO</h1>
          <p class="text-muted mb-0"><?= htmlspecialchars((string) $po['po_number'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="pengadaan-gudang">Pengadaan Gudang</a></li>
            <li class="breadcrumb-item active">Detail PO</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Informasi PO</h3>
          <div class="card-tools">
            <?php if (!in_array((string) ($po['status'] ?? ''), ['selesai', 'batal'], true)) : ?>
              <a href="pengadaan-po-receive?id=<?= $poId; ?>" class="btn btn-sm btn-warning"><i class="fa fa-barcode"></i> Terima Barang</a>
            <?php endif; ?>
            <?php if (!empty($waData['has_wa']) && !empty($waData['link'])) : ?>
              <a href="<?= htmlspecialchars((string) $waData['link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-sm btn-success"><i class="fab fa-whatsapp"></i> WA Supplier</a>
            <?php elseif (!empty($waData['edit_url'])) : ?>
              <a href="<?= htmlspecialchars((string) $waData['edit_url'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-warning"><i class="fa fa-edit"></i> Isi Nomor WA Supplier</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-3"><strong>Supplier:</strong> <?= htmlspecialchars((string) $po['kode_suplier'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="col-md-3"><strong>Status:</strong> <?= pengadaan_po_status_badge((string) ($po['status'] ?? '')); ?></div>
            <div class="col-md-3"><strong>Dibuat:</strong> <?= date('d/m/Y H:i', strtotime((string) ($po['created_at'] ?? 'now'))); ?></div>
            <div class="col-md-3">
              <?php if (!empty($po['pembelian_invoice_parent'])) : ?>
                <strong>Invoice:</strong> <a href="invoice-pembelian?no=<?= urlencode((string) $po['pembelian_invoice_parent']); ?>"><?= htmlspecialchars((string) $po['pembelian_invoice_parent'], ENT_QUOTES, 'UTF-8'); ?></a>
              <?php endif; ?>
            </div>
          </div>

          <h5>Template PO (format WA)</h5>
          <pre class="bg-light p-3 border rounded" style="white-space:pre-wrap;font-size:13px;"><?= htmlspecialchars((string) ($waData['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></pre>

          <div class="table-responsive mt-3">
            <table class="table table-bordered table-sm">
              <thead class="thead-dark">
                <tr>
                  <th>Barcode</th>
                  <th>Nama Barang</th>
                  <th>Cabang</th>
                  <th>Qty PO</th>
                  <th>Satuan</th>
                  <th>Qty Diterima</th>
                  <th>Harga Est.</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lines as $ln) : ?>
                  <tr>
                    <td><code><?= htmlspecialchars((string) ($ln['barang_kode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                    <td><?= htmlspecialchars((string) ($ln['barang_nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= pengadaan_gudang_cabang_label((int) ($ln['cabang_id'] ?? 0)); ?></td>
                    <td class="text-center"><?= number_format((float) ($ln['qty_po'] ?? 0), 0, '.', ''); ?></td>
                    <td><?= htmlspecialchars((string) ($ln['satuan_nama'] ?? 'PCS'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="text-center"><?= number_format((float) ($ln['qty_received'] ?? 0), 1, '.', ''); ?></td>
                    <td class="text-right"><?= number_format((float) ($ln['harga_estimasi'] ?? 0), 1, ',', '.'); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer">
          <a href="pengadaan-gudang" class="btn btn-default"><i class="fa fa-arrow-left"></i> Kembali</a>
        </div>
      </div>
    </div>
  </section>
</div>
</div>

<?php include '_footer.php'; ?>
