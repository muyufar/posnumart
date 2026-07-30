<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
require_once 'aksi/pengadaan-gudang-lib.php';
require_once 'aksi/pengadaan-po-lib.php';
require_once 'aksi/satuan-lib.php';

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
$poStatus = (string) ($po['status'] ?? '');
$canEdit = !in_array($poStatus, ['selesai', 'batal'], true);
$editMode = $canEdit && (isset($_GET['edit']) && (string) $_GET['edit'] === '1');
$satuanMaster = $editMode ? satuan_list_active('satuan_nama ASC') : [];
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1><i class="fa fa-file-invoice"></i> Detail PO<?= $editMode ? ' — Edit' : ''; ?></h1>
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
            <?php if ($canEdit && !$editMode) : ?>
              <a href="pengadaan-po-detail?id=<?= $poId; ?>&edit=1" class="btn btn-sm btn-secondary"><i class="fa fa-edit"></i> Edit PO</a>
            <?php endif; ?>
            <?php if ($canEdit) : ?>
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
            <div class="col-md-3"><strong>Status:</strong> <?= pengadaan_po_status_badge($poStatus); ?></div>
            <div class="col-md-3"><strong>Dibuat:</strong> <?= date('d/m/Y H:i', strtotime((string) ($po['created_at'] ?? 'now'))); ?></div>
            <div class="col-md-3">
              <?php if (!empty($po['pembelian_invoice_parent'])) : ?>
                <strong>Invoice:</strong> <a href="invoice-pembelian?no=<?= urlencode((string) $po['pembelian_invoice_parent']); ?>"><?= htmlspecialchars((string) $po['pembelian_invoice_parent'], ENT_QUOTES, 'UTF-8'); ?></a>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($editMode) : ?>
            <div class="alert alert-info py-2">
              Ubah <strong>Qty PO</strong> / <strong>Satuan</strong>, atau <strong>tambah barang manual</strong> di bawah.
              Qty tidak boleh lebih kecil dari qty yang sudah diterima. Setelah simpan, kirim ulang WA jika perlu.
            </div>

            <div class="card card-outline card-secondary mb-3">
              <div class="card-header py-2">
                <h3 class="card-title mb-0"><i class="fa fa-plus"></i> Tambah Barang Manual</h3>
              </div>
              <div class="card-body">
                <div class="form-row align-items-end">
                  <div class="form-group col-md-5 mb-2 position-relative">
                    <label>Cari barang (barcode / nama)</label>
                    <input type="text" class="form-control" id="inpCariBarangPo" placeholder="Ketik minimal 2 huruf..." autocomplete="off">
                    <input type="hidden" id="addBarangId" value="">
                    <div id="hasilCariBarangPo" class="list-group position-absolute w-100 shadow-sm" style="z-index:20;display:none;max-height:240px;overflow:auto;"></div>
                    <small class="text-muted" id="addBarangLabel">Belum ada barang dipilih</small>
                  </div>
                  <div class="form-group col-md-2 mb-2">
                    <label>Qty</label>
                    <input type="number" class="form-control" id="addQtyPo" value="1" min="1" step="1">
                  </div>
                  <div class="form-group col-md-3 mb-2">
                    <label>Satuan</label>
                    <select class="form-control" id="addSatuanPo">
                      <option value="">— pilih satuan —</option>
                      <?php foreach ($satuanMaster as $satRow) :
                          $namaSat = trim((string) ($satRow['satuan_nama'] ?? ''));
                          if ($namaSat === '') {
                              continue;
                          }
                          ?>
                        <option value="<?= htmlspecialchars($namaSat, ENT_QUOTES, 'UTF-8'); ?>">
                          <?= htmlspecialchars($namaSat, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-group col-md-2 mb-2">
                    <button type="button" class="btn btn-success btn-block" id="btnTambahBarangPo">
                      <i class="fa fa-plus"></i> Tambah
                    </button>
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <h5>Template PO (format WA)</h5>
          <pre class="bg-light p-3 border rounded" id="poWaTemplate" style="white-space:pre-wrap;font-size:13px;"><?= htmlspecialchars((string) ($waData['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></pre>

          <form id="formEditPoLines" class="mt-3">
            <input type="hidden" name="po_id" value="<?= $poId; ?>">
            <div class="table-responsive">
              <table class="table table-bordered table-sm">
                <thead class="thead-dark">
                  <tr>
                    <th>Barcode</th>
                    <th>Nama Barang</th>
                    <th>Cabang</th>
                    <th style="width:110px;">Qty PO</th>
                    <th style="width:120px;">Satuan</th>
                    <th>Qty Diterima</th>
                    <th>Harga Est.</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($lines as $ln) :
                      $lineId = (int) ($ln['id'] ?? 0);
                      $qtyPo = (float) ($ln['qty_po'] ?? 0);
                      $qtyRecv = (float) ($ln['qty_received'] ?? 0);
                      $satuan = (string) ($ln['satuan_nama'] ?? 'PCS');
                      $minQty = max(0.1, $qtyRecv > 0 ? $qtyRecv : 0.1);
                      ?>
                    <tr data-line-id="<?= $lineId; ?>">
                      <td><code><?= htmlspecialchars((string) ($ln['barang_kode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                      <td><?= htmlspecialchars((string) ($ln['barang_nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?= pengadaan_gudang_cabang_label((int) ($ln['cabang_id'] ?? 0)); ?></td>
                      <td class="text-center">
                        <?php if ($editMode) : ?>
                          <input type="number"
                                 class="form-control form-control-sm text-center inp-qty-po"
                                 name="lines[<?= $lineId; ?>][qty_po]"
                                 value="<?= htmlspecialchars(number_format($qtyPo, 0, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                 min="<?= htmlspecialchars(number_format($minQty, 1, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                 step="1"
                                 required>
                        <?php else : ?>
                          <?= number_format($qtyPo, 0, '.', ''); ?>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($editMode) :
                            $satuanUpper = strtoupper(trim($satuan));
                            $matched = false;
                            ?>
                          <select class="form-control form-control-sm inp-satuan"
                                  name="lines[<?= $lineId; ?>][satuan_nama]"
                                  required>
                            <option value="">— pilih satuan —</option>
                            <?php foreach ($satuanMaster as $satRow) :
                                $namaSat = trim((string) ($satRow['satuan_nama'] ?? ''));
                                if ($namaSat === '') {
                                    continue;
                                }
                                $selected = (strtoupper($namaSat) === $satuanUpper);
                                if ($selected) {
                                    $matched = true;
                                }
                                ?>
                              <option value="<?= htmlspecialchars($namaSat, ENT_QUOTES, 'UTF-8'); ?>"<?= $selected ? ' selected' : ''; ?>>
                                <?= htmlspecialchars($namaSat, ENT_QUOTES, 'UTF-8'); ?>
                              </option>
                            <?php endforeach; ?>
                            <?php if ($satuan !== '' && !$matched) : ?>
                              <option value="" selected disabled>
                                <?= htmlspecialchars($satuan, ENT_QUOTES, 'UTF-8'); ?> (lama — pilih dari master)
                              </option>
                            <?php endif; ?>
                          </select>
                        <?php else : ?>
                          <?= htmlspecialchars($satuan, ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                      </td>
                      <td class="text-center"><?= number_format($qtyRecv, 1, '.', ''); ?></td>
                      <td class="text-right"><?= number_format((float) ($ln['harga_estimasi'] ?? 0), 1, ',', '.'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </form>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
          <a href="pengadaan-gudang" class="btn btn-default"><i class="fa fa-arrow-left"></i> Kembali</a>
          <?php if ($editMode) : ?>
            <div>
              <a href="pengadaan-po-detail?id=<?= $poId; ?>" class="btn btn-outline-secondary">Batal</a>
              <button type="button" class="btn btn-primary" id="btnSimpanPoEdit"><i class="fa fa-save"></i> Simpan Perubahan</button>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
</div>
</div>

<?php include '_footer.php'; ?>

<?php if ($editMode) : ?>
<script>
(function () {
  var poId = <?= (int) $poId; ?>;
  var cariTimer = null;

  function setSelectedBarang(item) {
    $('#addBarangId').val(item.barang_id || '');
    $('#addBarangLabel').text((item.barang_kode || '') + ' — ' + (item.barang_nama || ''));
    $('#inpCariBarangPo').val((item.barang_kode || '') + ' | ' + (item.barang_nama || ''));
    $('#hasilCariBarangPo').hide().empty();
    if (item.satuan_nama) {
      var $opt = $('#addSatuanPo option').filter(function () {
        return String($(this).val()).toUpperCase() === String(item.satuan_nama).toUpperCase();
      }).first();
      if ($opt.length) {
        $('#addSatuanPo').val($opt.val());
      }
    }
  }

  $('#inpCariBarangPo').on('input', function () {
    var q = $.trim($(this).val());
    $('#addBarangId').val('');
    $('#addBarangLabel').text('Belum ada barang dipilih');
    clearTimeout(cariTimer);
    if (q.length < 2) {
      $('#hasilCariBarangPo').hide().empty();
      return;
    }
    cariTimer = setTimeout(function () {
      $.getJSON('api/pengadaan-gudang-action.php', { action: 'po_search_barang', po_id: poId, q: q })
        .done(function (res) {
          var items = (res && res.items) ? res.items : [];
          var $box = $('#hasilCariBarangPo').empty();
          if (!items.length) {
            $box.append('<div class="list-group-item text-muted">Tidak ditemukan</div>').show();
            return;
          }
          items.forEach(function (it) {
            var label = (it.barang_kode || '') + ' — ' + (it.barang_nama || '');
            if (it.kode_suplier) label += ' [' + it.kode_suplier + ']';
            var $a = $('<a href="#" class="list-group-item list-group-item-action"></a>').text(label);
            $a.on('click', function (e) {
              e.preventDefault();
              setSelectedBarang(it);
            });
            $box.append($a);
          });
          $box.show();
        });
    }, 300);
  });

  $(document).on('click', function (e) {
    if (!$(e.target).closest('#inpCariBarangPo, #hasilCariBarangPo').length) {
      $('#hasilCariBarangPo').hide();
    }
  });

  $('#btnTambahBarangPo').on('click', function () {
    var barangId = parseInt($('#addBarangId').val(), 10) || 0;
    var qty = parseFloat($('#addQtyPo').val()) || 0;
    var satuan = $.trim($('#addSatuanPo').val() || '');
    if (!barangId) {
      if (window.Swal) Swal.fire('Pilih barang', 'Cari lalu pilih barang dari daftar.', 'info');
      else alert('Pilih barang dulu');
      return;
    }
    if (qty <= 0 || !satuan) {
      if (window.Swal) Swal.fire('Data belum lengkap', 'Isi qty dan satuan.', 'warning');
      else alert('Isi qty dan satuan');
      return;
    }
    var $btn = $(this).prop('disabled', true);
    $.ajax({
      url: 'api/pengadaan-gudang-action.php',
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'po_add_line',
        po_id: poId,
        barang_id: barangId,
        qty_po: qty,
        satuan_nama: satuan,
        cabang_id: 0
      }
    })
      .done(function (res) {
        if (res && res.ok) {
          if (window.Swal) {
            Swal.fire({ icon: 'success', title: 'Ditambahkan', text: res.message || 'OK', timer: 1000, showConfirmButton: false })
              .then(function () { window.location.href = 'pengadaan-po-detail?id=' + poId + '&edit=1'; });
          } else {
            window.location.href = 'pengadaan-po-detail?id=' + poId + '&edit=1';
          }
        } else {
          var msg = (res && res.message) ? res.message : 'Gagal menambah barang';
          if (window.Swal) Swal.fire('Gagal', msg, 'error');
          else alert(msg);
        }
      })
      .fail(function () {
        if (window.Swal) Swal.fire('Gagal', 'Koneksi bermasalah', 'error');
        else alert('Koneksi bermasalah');
      })
      .always(function () { $btn.prop('disabled', false); });
  });

  $('#btnSimpanPoEdit').on('click', function () {
    var $btn = $(this);
    var lines = {};
    var valid = true;

    $('#formEditPoLines tr[data-line-id]').each(function () {
      var lineId = String($(this).data('line-id'));
      var qty = parseFloat($(this).find('.inp-qty-po').val());
      var satuan = $.trim($(this).find('.inp-satuan').val() || '');
      var minQty = parseFloat($(this).find('.inp-qty-po').attr('min')) || 0.1;

      if (!qty || qty < minQty || !satuan) {
        valid = false;
        return false;
      }

      lines[lineId] = { qty_po: qty, satuan_nama: satuan };
    });

    if (!valid) {
      if (window.Swal) {
        Swal.fire('Data belum valid', 'Pastikan qty ≥ qty diterima dan satuan terisi.', 'warning');
      } else {
        alert('Pastikan qty ≥ qty diterima dan satuan terisi.');
      }
      return;
    }

    $btn.prop('disabled', true);
    $.ajax({
      url: 'api/pengadaan-gudang-action.php',
      method: 'POST',
      dataType: 'json',
      data: { action: 'po_edit_lines', po_id: poId, lines: lines }
    })
      .done(function (res) {
        if (res && res.ok) {
          if (window.Swal) {
            Swal.fire({ icon: 'success', title: 'Tersimpan', text: res.message || 'PO diperbarui', timer: 1400, showConfirmButton: false })
              .then(function () { window.location.href = 'pengadaan-po-detail?id=' + poId; });
          } else {
            alert(res.message || 'Tersimpan');
            window.location.href = 'pengadaan-po-detail?id=' + poId;
          }
        } else {
          $btn.prop('disabled', false);
          var msg = (res && res.message) ? res.message : 'Gagal menyimpan';
          if (window.Swal) Swal.fire('Gagal', msg, 'error');
          else alert(msg);
        }
      })
      .fail(function () {
        $btn.prop('disabled', false);
        if (window.Swal) Swal.fire('Gagal', 'Koneksi bermasalah', 'error');
        else alert('Koneksi bermasalah');
      });
  });
})();
</script>
<?php endif; ?>
