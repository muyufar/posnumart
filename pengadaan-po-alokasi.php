<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
require_once 'aksi/pengadaan-po-alokasi-lib.php';

if (!pengadaan_gudang_can_access((int) $sessionCabang, (string) $levelLogin)) {
	echo "<script>document.location.href='bo';</script>";
	exit;
}

$poId = (int) ($_GET['po'] ?? $_GET['id'] ?? 0);
pengadaan_po_ensure_tables($conn);
$po = pengadaan_po_get($conn, $poId);
if (!$po) {
	echo "<script>alert('PO tidak ditemukan'); document.location.href='pengadaan-gudang';</script>";
	exit;
}

$status = (string) ($po['status'] ?? '');
if (!in_array($status, ['diterima', 'selesai'], true)) {
	echo "<script>alert('Selesaikan transaksi pembelian PO dulu sebelum alokasi transfer'); document.location.href='pengadaan-po-detail?id=" . $poId . "';</script>";
	exit;
}

$cabangToko = pengadaan_gudang_cabang_toko();
$lines = pengadaan_po_alokasi_lines($conn, $poId);
$invoiceNo = (string) ($po['pembelian_invoice_parent'] ?? '');
$alokasiAt = (string) ($po['alokasi_at'] ?? '');
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1><i class="fa fa-truck"></i> Alokasi Transfer Stock</h1>
          <p class="text-muted mb-0">
            PO <strong><?= htmlspecialchars((string) $po['po_number'], ENT_QUOTES, 'UTF-8'); ?></strong>
            — plot qty yang dikirim ke masing-masing toko. Sisa qty tetap di gudang.
          </p>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="pengadaan-gudang">Pengadaan Gudang</a></li>
            <li class="breadcrumb-item"><a href="pengadaan-po-detail?id=<?= $poId; ?>">Detail PO</a></li>
            <li class="breadcrumb-item active">Alokasi</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <?php if ($alokasiAt !== '') : ?>
        <div class="alert alert-warning">
          Alokasi pernah dibuat pada <strong><?= date('d/m/Y H:i', strtotime($alokasiAt)); ?></strong>.
          Anda bisa membuat transfer tambahan jika masih ada sisa stok gudang.
        </div>
      <?php endif; ?>

      <div class="alert alert-info py-2">
        Isi qty per toko (Dukun / Pakis / PP Srumbung / Tegalrejo). Total per baris tidak boleh melebihi
        <strong>Qty PO diterima</strong>. Centang opsi di bawah jika stok langsung dipindah tanpa menunggu konfirmasi toko.
        <?php if ($invoiceNo !== '') : ?>
          | Invoice: <a href="invoice-pembelian?no=<?= urlencode($invoiceNo); ?>"><?= htmlspecialchars($invoiceNo, ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endif; ?>
      </div>

      <?php if ($lines === []) : ?>
        <div class="card">
          <div class="card-body text-muted">Tidak ada barang dengan qty diterima pada PO ini.</div>
          <div class="card-footer">
            <a href="pengadaan-po-detail?id=<?= $poId; ?>" class="btn btn-default">Kembali</a>
          </div>
        </div>
      <?php else : ?>
        <div class="card card-outline card-primary">
          <div class="card-header">
            <h3 class="card-title"><i class="fa fa-th"></i> Plot Barang → Toko</h3>
            <div class="card-tools">
              <button type="button" class="btn btn-sm btn-outline-secondary" id="btnBagiRata" title="Bagi rata qty ke semua toko">
                <i class="fa fa-balance-scale"></i> Bagi rata
              </button>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearAlloc">Reset qty</button>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-bordered table-sm mb-0" id="tblAlokasi">
                <thead class="thead-dark">
                  <tr>
                    <th>Barcode</th>
                    <th>Nama</th>
                    <th>Satuan</th>
                    <th class="text-right">Qty PO</th>
                    <th class="text-right">Stok Gudang</th>
                    <?php foreach ($cabangToko as $cabId => $cabNama) : ?>
                      <th class="text-center" style="min-width:90px;"><?= htmlspecialchars($cabNama, ENT_QUOTES, 'UTF-8'); ?></th>
                    <?php endforeach; ?>
                    <th class="text-right">Sisa Gudang</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($lines as $ln) :
					$lineId = (int) $ln['line_id'];
					$qty = (float) $ln['qty_tersedia'];
					?>
                    <tr data-line-id="<?= $lineId; ?>" data-qty="<?= htmlspecialchars((string) $qty, ENT_QUOTES, 'UTF-8'); ?>">
                      <td><code><?= htmlspecialchars((string) $ln['barang_kode'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                      <td>
                        <?= htmlspecialchars((string) $ln['barang_nama'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ((int) ($ln['option_sn'] ?? 0) > 0) : ?>
                          <span class="badge badge-warning">SN — manual</span>
                        <?php endif; ?>
                      </td>
                      <td><?= htmlspecialchars((string) $ln['satuan_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td class="text-right font-weight-bold"><?= number_format($qty, 0, ',', '.'); ?></td>
                      <td class="text-right"><?= number_format((float) $ln['stok_gudang'], 0, ',', '.'); ?></td>
                      <?php foreach ($cabangToko as $cabId => $cabNama) : ?>
                        <td>
                          <?php if ((int) ($ln['option_sn'] ?? 0) > 0) : ?>
                            <input type="number" class="form-control form-control-sm text-center" value="0" disabled>
                          <?php else : ?>
                            <input type="number"
                                   class="form-control form-control-sm text-center alloc-qty"
                                   name="alloc[<?= $lineId; ?>][<?= (int) $cabId; ?>]"
                                   data-cabang="<?= (int) $cabId; ?>"
                                   min="0" step="1" value="0">
                          <?php endif; ?>
                        </td>
                      <?php endforeach; ?>
                      <td class="text-right sisa-gudang"><?= number_format($qty, 0, ',', '.'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="card-footer">
            <div class="custom-control custom-checkbox mb-2">
              <input type="checkbox" class="custom-control-input" id="autoConfirm" checked>
              <label class="custom-control-label" for="autoConfirm">
                Langsung pindahkan stok ke toko (tanpa menunggu konfirmasi cabang)
              </label>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <a href="pengadaan-po-detail?id=<?= $poId; ?>" class="btn btn-default">Kembali ke PO</a>
                <?php if ($invoiceNo !== '') : ?>
                  <a href="invoice-pembelian?no=<?= urlencode($invoiceNo); ?>" class="btn btn-outline-secondary">Lihat Invoice</a>
                <?php endif; ?>
                <a href="pengadaan-gudang" class="btn btn-outline-secondary">Lewati / Nanti</a>
              </div>
              <button type="button" class="btn btn-success" id="btnSubmitAlokasi">
                <i class="fa fa-truck"></i> Buat Transfer ke Toko
              </button>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>
</div>

<?php include '_footer.php'; ?>

<?php if ($lines !== []) : ?>
<script>
(function () {
  var poId = <?= (int) $poId; ?>;
  var cabangIds = <?= json_encode(array_map('intval', array_keys($cabangToko))); ?>;

  function fmt(n) {
    return Number(n || 0).toLocaleString('id-ID');
  }

  function recalcRow($tr) {
    var maxQty = parseFloat($tr.data('qty')) || 0;
    var sum = 0;
    $tr.find('.alloc-qty').each(function () {
      sum += parseFloat($(this).val()) || 0;
    });
    var sisa = maxQty - sum;
    $tr.find('.sisa-gudang').text(fmt(sisa));
    $tr.toggleClass('table-danger', sisa < -0.0001);
    return sisa;
  }

  $('#tblAlokasi').on('input change', '.alloc-qty', function () {
    recalcRow($(this).closest('tr'));
  });
  $('#tblAlokasi tr[data-line-id]').each(function () { recalcRow($(this)); });

  $('#btnClearAlloc').on('click', function () {
    $('#tblAlokasi .alloc-qty').val(0);
    $('#tblAlokasi tr[data-line-id]').each(function () { recalcRow($(this)); });
  });

  $('#btnBagiRata').on('click', function () {
    var n = cabangIds.length || 1;
    $('#tblAlokasi tr[data-line-id]').each(function () {
      var $tr = $(this);
      if (!$tr.find('.alloc-qty').length) return;
      var maxQty = parseFloat($tr.data('qty')) || 0;
      var base = Math.floor(maxQty / n);
      var sisa = maxQty - (base * n);
      $tr.find('.alloc-qty').each(function (idx) {
        var v = base + (idx < sisa ? 1 : 0);
        $(this).val(v);
      });
      recalcRow($tr);
    });
  });

  $('#btnSubmitAlokasi').on('click', function () {
    var invalid = false;
    var alloc = {};
    var hasAny = false;
    $('#tblAlokasi tr[data-line-id]').each(function () {
      var $tr = $(this);
      var lineId = $tr.data('line-id');
      var sisa = recalcRow($tr);
      if (sisa < -0.0001) invalid = true;
      alloc[lineId] = {};
      $tr.find('.alloc-qty').each(function () {
        var cab = $(this).data('cabang');
        var qty = parseFloat($(this).val()) || 0;
        alloc[lineId][cab] = qty;
        if (qty > 0) hasAny = true;
      });
    });
    if (invalid) {
      if (window.Swal) Swal.fire('Qty melebihi', 'Ada baris yang total alokasinya melebihi Qty PO.', 'warning');
      else alert('Ada baris yang total alokasinya melebihi Qty PO.');
      return;
    }
    if (!hasAny) {
      if (window.Swal) Swal.fire('Kosong', 'Isi qty transfer ke minimal 1 toko.', 'info');
      else alert('Isi qty transfer ke minimal 1 toko.');
      return;
    }

    var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Memproses...');
    $.ajax({
      url: 'api/pengadaan-po-alokasi-action.php',
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'submit',
        po_id: poId,
        auto_confirm: $('#autoConfirm').is(':checked') ? 1 : 0,
        alloc: alloc
      }
    })
      .done(function (res) {
        if (res && res.ok) {
          if (window.Swal) {
            Swal.fire({
              icon: 'success',
              title: 'Transfer dibuat',
              text: res.message || 'OK',
              confirmButtonText: 'Ke Transfer Keluar'
            }).then(function () {
              window.location.href = 'transfer-stock-cabang-keluar';
            });
          } else {
            alert(res.message || 'OK');
            window.location.href = 'transfer-stock-cabang-keluar';
          }
        } else {
          var msg = (res && res.message) ? res.message : 'Gagal membuat transfer';
          if (window.Swal) Swal.fire('Gagal', msg, 'error');
          else alert(msg);
        }
      })
      .fail(function () {
        if (window.Swal) Swal.fire('Gagal', 'Koneksi bermasalah', 'error');
        else alert('Koneksi bermasalah');
      })
      .always(function () {
        $btn.prop('disabled', false).html('<i class="fa fa-truck"></i> Buat Transfer ke Toko');
      });
  });
})();
</script>
<?php endif; ?>
</body>
</html>
