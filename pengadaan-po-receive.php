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
$supplier = null;
if (!empty($po['supplier_id'])) {
    $supRes = mysqli_query($conn, 'SELECT * FROM supplier WHERE supplier_id = ' . (int) $po['supplier_id'] . ' LIMIT 1');
    $supplier = $supRes ? mysqli_fetch_assoc($supRes) : null;
}
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1><i class="fa fa-barcode"></i> Terima Barang PO</h1>
          <p class="text-muted mb-0">
            <?= htmlspecialchars((string) $po['po_number'], ENT_QUOTES, 'UTF-8'); ?>
            — <?= htmlspecialchars((string) $po['kode_suplier'], ENT_QUOTES, 'UTF-8'); ?>
            <?= pengadaan_po_status_badge((string) ($po['status'] ?? '')); ?>
          </p>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="pengadaan-gudang">Pengadaan Gudang</a></li>
            <li class="breadcrumb-item active">Terima PO</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <?php if (in_array((string) ($po['status'] ?? ''), ['selesai', 'batal'], true)) : ?>
        <div class="alert alert-info">
          PO ini sudah <?= htmlspecialchars((string) $po['status'], ENT_QUOTES, 'UTF-8'); ?>.
          <?php if (!empty($po['pembelian_invoice_parent'])) : ?>
            <a href="invoice-pembelian?no=<?= urlencode((string) $po['pembelian_invoice_parent']); ?>">Lihat Invoice Pembelian</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="row mb-3">
        <div class="col-md-8">
          <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title"><i class="fa fa-qrcode"></i> Scan Barcode</h3></div>
            <div class="card-body">
              <form id="formScanPo" autocomplete="off">
                <input type="hidden" name="po_id" value="<?= $poId; ?>">
                <div class="input-group input-group-lg">
                  <input type="text" class="form-control" id="inputBarcodePo" name="barcode" placeholder="Scan / ketik barcode (barang_kode)..." autofocus>
                  <div class="input-group-append">
                    <button type="submit" class="btn btn-warning"><i class="fa fa-plus"></i> Tambah</button>
                  </div>
                </div>
                <small class="text-muted">Setiap scan menambah qty diterima +1 untuk barang yang cocok dengan PO.</small>
              </form>
              <div id="scanFeedback" class="mt-2"></div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title">Supplier</h3></div>
            <div class="card-body">
              <?php if ($supplier) : ?>
                <p class="mb-1"><strong><?= htmlspecialchars((string) ($supplier['supplier_nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></p>
                <p class="mb-1"><?= htmlspecialchars((string) ($supplier['supplier_company'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="mb-2">WA: <?= htmlspecialchars((string) ($supplier['supplier_wa'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></p>
              <?php else : ?>
                <p class="text-muted">Supplier belum terhubung. Kode: <strong><?= htmlspecialchars((string) $po['kode_suplier'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
              <?php endif; ?>
              <?php if (!empty($waData['has_wa'])) : ?>
                <a href="<?= htmlspecialchars((string) $waData['link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-success btn-sm btn-block"><i class="fab fa-whatsapp"></i> Kirim Ulang PO via WA</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card card-primary">
        <div class="card-header">
          <h3 class="card-title"><i class="fa fa-list"></i> Daftar Barang PO</h3>
          <div class="card-tools">
            <?php if (!in_array((string) ($po['status'] ?? ''), ['selesai', 'batal'], true)) : ?>
              <button type="button" class="btn btn-sm btn-primary" id="btnBuatInvoice"><i class="fa fa-file-invoice-dollar"></i> Lanjut ke Invoice Pembelian</button>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0" id="tblPoLines">
              <thead class="thead-dark">
                <tr>
                  <th>Barcode</th>
                  <th>Nama Barang</th>
                  <th>Cabang</th>
                  <th>Qty PO</th>
                  <th>Satuan</th>
                  <th>Qty Diterima</th>
                  <th>Harga Beli</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lines as $ln) :
                  $lineId = (int) ($ln['id'] ?? 0);
                  $qtyPo = (float) ($ln['qty_po'] ?? 0);
                  $qtyRc = (float) ($ln['qty_received'] ?? 0);
                  $harga = (float) ($ln['harga_actual'] ?? 0);
                  if ($harga <= 0) {
                      $harga = (float) ($ln['harga_estimasi'] ?? 0);
                  }
                  $rowClass = $qtyRc > 0 ? ($qtyRc >= $qtyPo ? 'table-success' : 'table-warning') : '';
                ?>
                  <tr class="po-line-row <?= $rowClass; ?>" data-line-id="<?= $lineId; ?>" data-kode="<?= htmlspecialchars((string) ($ln['barang_kode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <td><code><?= htmlspecialchars((string) ($ln['barang_kode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                    <td><?= htmlspecialchars((string) ($ln['barang_nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= pengadaan_gudang_cabang_label((int) ($ln['cabang_id'] ?? 0)); ?></td>
                    <td class="text-center"><?= number_format($qtyPo, 0, '.', ''); ?></td>
                    <td style="width:90px">
                      <input type="text" class="form-control form-control-sm inp-satuan" value="<?= htmlspecialchars((string) ($ln['satuan_nama'] ?? 'PCS'), ENT_QUOTES, 'UTF-8'); ?>">
                    </td>
                    <td style="width:100px">
                      <input type="number" min="0" step="0.1" class="form-control form-control-sm inp-qty" value="<?= number_format($qtyRc, 1, '.', ''); ?>">
                    </td>
                    <td style="width:120px">
                      <input type="number" min="0" step="0.1" class="form-control form-control-sm inp-harga" value="<?= number_format($harga, 1, '.', ''); ?>">
                    </td>
                    <td class="line-status text-center">
                      <?php if ($qtyRc <= 0) : ?>
                        <span class="badge badge-secondary">Belum</span>
                      <?php elseif ($qtyRc >= $qtyPo) : ?>
                        <span class="badge badge-success">Lengkap</span>
                      <?php else : ?>
                        <span class="badge badge-warning">Sebagian</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer">
          <small class="text-muted">Edit satuan & harga jika berbeda dari PO. Klik <strong>Lanjut ke Invoice Pembelian</strong> setelah semua barang discan — sistem akan membuka Transaksi Pembelian dengan keranjang terisi.</small>
        </div>
      </div>
    </div>
  </section>
</div>
</div>

<?php include '_footer.php'; ?>

<script>
$(function () {
  var poId = <?= $poId; ?>;

  function saveLine($row, cb) {
    $.post('api/pengadaan-po-receive-action.php', {
      action: 'update_line',
      line_id: $row.data('line-id'),
      qty_received: $row.find('.inp-qty').val(),
      satuan_nama: $row.find('.inp-satuan').val(),
      harga: $row.find('.inp-harga').val()
    }).done(cb);
  }

  function refreshRowStatus($row) {
    var qtyPo = parseFloat($row.find('td:eq(3)').text()) || 0;
    var qtyRc = parseFloat($row.find('.inp-qty').val()) || 0;
    var $st = $row.find('.line-status');
    $row.removeClass('table-success table-warning');
    if (qtyRc <= 0) {
      $st.html('<span class="badge badge-secondary">Belum</span>');
    } else if (qtyRc >= qtyPo) {
      $st.html('<span class="badge badge-success">Lengkap</span>');
      $row.addClass('table-success');
    } else {
      $st.html('<span class="badge badge-warning">Sebagian</span>');
      $row.addClass('table-warning');
    }
  }

  $('#tblPoLines').on('change', '.inp-qty, .inp-satuan, .inp-harga', function () {
    var $row = $(this).closest('.po-line-row');
    saveLine($row, function () { refreshRowStatus($row); });
  });

  $('#formScanPo').on('submit', function (e) {
    e.preventDefault();
    var barcode = $.trim($('#inputBarcodePo').val());
    if (!barcode) return;
    $.post('api/pengadaan-po-receive-action.php', { action: 'scan', po_id: poId, barcode: barcode })
      .done(function (res) {
        if (!res.ok) {
          $('#scanFeedback').html('<div class="alert alert-danger py-1 mb-0">' + (res.message || 'Barcode tidak ada di PO') + '</div>');
          return;
        }
        var $row = $('.po-line-row[data-line-id="' + res.line_id + '"]');
        $row.find('.inp-qty').val(res.qty_received);
        refreshRowStatus($row);
        $('#scanFeedback').html('<div class="alert alert-success py-1 mb-0"><i class="fa fa-check"></i> ' + res.barang_nama + ' — diterima: ' + res.qty_received + '</div>');
        $('#inputBarcodePo').val('').focus();
      });
  });

  $('#btnBuatInvoice').on('click', function () {
    var lines = [];
    $('.po-line-row').each(function () {
      var $r = $(this);
      lines.push({
        line_id: $r.data('line-id'),
        qty_received: $r.find('.inp-qty').val(),
        satuan_nama: $r.find('.inp-satuan').val(),
        harga: $r.find('.inp-harga').val()
      });
    });
    var $btn = $(this).prop('disabled', true);
    $.post('api/pengadaan-po-receive-action.php', { action: 'prepare_invoice', po_id: poId, lines: lines })
      .done(function (res) {
        if (res.ok && res.redirect) {
          window.location.href = res.redirect;
        } else if (typeof Swal !== 'undefined') {
          Swal.fire('Gagal', res.message || 'Gagal', 'error');
        } else {
          alert(res.message || 'Gagal');
        }
      }).always(function () { $btn.prop('disabled', false); });
  });
});
</script>
</body>
</html>
