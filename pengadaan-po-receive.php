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
$satuanMaster = satuan_list_active('satuan_nama ASC');
$cabangList = pengadaan_gudang_cabang_toko();
$poLocked = in_array((string) ($po['status'] ?? ''), ['selesai', 'batal'], true);
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
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0"><i class="fa fa-list"></i> Daftar Barang PO</h3>
          <?php if (!$poLocked) : ?>
            <div class="card-tools">
              <button type="button" class="btn btn-sm btn-success mr-2" id="btnTambahBarang"><i class="fa fa-plus"></i> Tambah Barang</button>
              <button type="button" class="btn btn-sm btn-primary" id="btnBuatInvoice"><i class="fa fa-file-invoice-dollar"></i> Lanjut ke Invoice Pembelian</button>
            </div>
          <?php endif; ?>
        </div>
        <div class="card-body p-0">
          <style>
            #tblPoLines .col-cabang { width: 88px; max-width: 88px; padding-left: 6px !important; padding-right: 4px !important; white-space: nowrap; }
            #tblPoLines .col-qty-po { width: 52px; max-width: 52px; padding-left: 4px !important; padding-right: 6px !important; text-align: center; }
            #tblPoLines .col-qty-diterima { width: 110px; min-width: 110px; }
            #tblPoLines .col-qty-diterima .inp-qty { min-width: 90px; text-align: center; }
            #tblPoLines .col-satuan { width: 130px; min-width: 130px; }
            #tblPoLines .col-satuan .inp-satuan { min-width: 115px; }
            #tblPoLines .col-harga { width: 150px; min-width: 150px; }
            #tblPoLines .col-harga .inp-harga { min-width: 130px; text-align: right; }
            #tblPoLines .col-jumlah { width: 115px; min-width: 115px; text-align: right; font-weight: 600; white-space: nowrap; }
          </style>
          <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0" id="tblPoLines">
              <thead class="thead-dark">
                <tr>
                  <th>Barcode</th>
                  <th>Nama Barang</th>
                  <th class="col-cabang">Cabang</th>
                  <th class="col-qty-po">Qty PO</th>
                  <th class="col-satuan">Satuan</th>
                  <th class="col-qty-diterima">Qty Diterima</th>
                  <th class="col-harga">Harga Beli</th>
                  <th class="col-jumlah">Jumlah</th>
                  <th style="width:85px">Status</th>
                  <th style="width:70px">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $totalJumlah = 0;
                foreach ($lines as $ln) :
                  $lineId = (int) ($ln['id'] ?? 0);
                  $qtyPo = (float) ($ln['qty_po'] ?? 0);
                  $qtyRc = (float) ($ln['qty_received'] ?? 0);
                  $harga = (float) ($ln['harga_actual'] ?? 0);
                  if ($harga <= 0) {
                      $harga = (float) ($ln['harga_estimasi'] ?? 0);
                  }
                  $rowClass = $qtyRc > 0 ? ($qtyRc >= $qtyPo ? 'table-success' : 'table-warning') : '';
                  $jumlah = $qtyRc * $harga;
                  $totalJumlah += $jumlah;
                ?>
                  <tr class="po-line-row <?= $rowClass; ?>" data-line-id="<?= $lineId; ?>" data-qty-po="<?= number_format($qtyPo, 0, '.', ''); ?>" data-kode="<?= htmlspecialchars((string) ($ln['barang_kode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <td><code><?= htmlspecialchars((string) ($ln['barang_kode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                    <td><?= htmlspecialchars((string) ($ln['barang_nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="col-cabang"><?= pengadaan_gudang_cabang_label((int) ($ln['cabang_id'] ?? 0)); ?></td>
                    <td class="col-qty-po"><?= number_format($qtyPo, 0, '.', ''); ?></td>
                    <td class="col-satuan">
                      <?php
                        $satuan = trim((string) ($ln['satuan_nama'] ?? 'PCS'));
                        $satuanUpper = strtoupper($satuan);
                        $matched = false;
                      ?>
                      <?php if ($poLocked) : ?>
                        <?= htmlspecialchars($satuan, ENT_QUOTES, 'UTF-8'); ?>
                      <?php else : ?>
                        <select class="form-control form-control-sm inp-satuan" required>
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
                      <?php endif; ?>
                    </td>
                    <td class="col-qty-diterima">
                      <input type="number" min="0" step="0.1" class="form-control form-control-sm inp-qty" value="<?= number_format($qtyRc, 1, '.', ''); ?>">
                    </td>
                    <td class="col-harga">
                      <input type="number" min="0" step="1" class="form-control form-control-sm inp-harga" value="<?= number_format($harga, 0, '.', ''); ?>">
                    </td>
                    <td class="col-jumlah line-jumlah"><?= number_format($jumlah, 0, ',', '.'); ?></td>
                    <td class="line-status text-center">
                      <?php if ($qtyRc <= 0) : ?>
                        <span class="badge badge-secondary">Belum</span>
                      <?php elseif ($qtyRc >= $qtyPo) : ?>
                        <span class="badge badge-success">Lengkap</span>
                      <?php else : ?>
                        <span class="badge badge-warning">Sebagian</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center">
                      <?php if (!$poLocked) : ?>
                        <?php if ($qtyRc > 0) : ?>
                          <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Sudah ada qty diterima — tidak bisa dihapus"><i class="fa fa-trash"></i></button>
                        <?php else : ?>
                          <button type="button" class="btn btn-sm btn-danger btn-delete-line" data-line-id="<?= $lineId; ?>" data-nama="<?= htmlspecialchars((string) ($ln['barang_nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" title="Hapus barang dari PO"><i class="fa fa-trash"></i></button>
                        <?php endif; ?>
                      <?php else : ?>
                        &mdash;
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="table-active font-weight-bold">
                  <td colspan="7" class="text-right py-2">Jumlah Total</td>
                  <td class="col-jumlah py-2" id="poTotalJumlah"><?= number_format($totalJumlah, 0, ',', '.'); ?></td>
                  <td colspan="2"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
        <div class="card-footer">
          <small class="text-muted">Satuan diambil dari <strong>master satuan</strong>. Edit qty diterima & harga bila perlu. Klik <strong>Lanjut ke Invoice Pembelian</strong> setelah scan — keranjang pembelian terisi otomatis.</small>
        </div>
      </div>
    </div>
  </section>
</div>

<?php if (!$poLocked) : ?>
<!-- Modal: Tambah Barang Manual -->
<div class="modal fade" id="modalTambahBarang" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Tambah Barang ke PO</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="formTambahBarang" autocomplete="off">
          <input type="hidden" name="po_id" value="<?= $poId; ?>">
          <input type="hidden" name="barang_id" id="tb_barang_id" value="">
          <div class="form-group position-relative">
            <label>Cari barang (barcode / nama)</label>
            <input type="text" class="form-control" id="tb_cari_barang" placeholder="Ketik minimal 2 huruf...">
            <div id="tb_hasil_cari" class="list-group position-absolute w-100 shadow-sm" style="z-index:1051;display:none;max-height:220px;overflow:auto;"></div>
            <small class="text-muted" id="tb_barang_label">Belum ada barang dipilih</small>
          </div>
          <div class="form-group">
            <label>Qty PO</label>
            <input type="number" step="1" min="1" class="form-control" name="qty_po" id="tb_qty_po" value="1">
          </div>
          <div class="form-group">
            <label>Satuan</label>
            <select class="form-control" name="satuan_nama" id="tb_satuan_nama" required>
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
          <div class="form-group mb-0">
            <label>Cabang (opsional)</label>
            <select class="form-control" name="cabang_id" id="tb_cabang_id">
              <option value="0">Semua toko / Gudang</option>
              <?php foreach ($cabangList as $cabId => $cabNama) : ?>
                <option value="<?= (int) $cabId; ?>"><?= htmlspecialchars((string) $cabNama, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>
        <div id="modalTambahFeedback" class="mt-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="modalTambahSubmit"><i class="fa fa-plus"></i> Tambah</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include '_footer.php'; ?>

<script>
$(function () {
  var poId = <?= $poId; ?>;
  var cariTimer = null;

  function saveLine($row, cb) {
    var satuan = $.trim($row.find('.inp-satuan').val() || '');
    if (!satuan) {
      if (window.Swal) Swal.fire('Satuan wajib', 'Pilih satuan dari master data.', 'warning');
      else alert('Pilih satuan dari master data');
      return;
    }
    $.post('api/pengadaan-po-receive-action.php', {
      action: 'update_line',
      line_id: $row.data('line-id'),
      qty_received: $row.find('.inp-qty').val(),
      satuan_nama: satuan,
      harga: $row.find('.inp-harga').val()
    }, null, 'json').done(cb);
  }

  function formatJumlah(n) {
    var v = Math.round(parseFloat(n) || 0);
    return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function refreshLineJumlah($row) {
    var qtyRc = parseFloat($row.find('.inp-qty').val()) || 0;
    var harga = parseFloat($row.find('.inp-harga').val()) || 0;
    $row.find('.line-jumlah').text(formatJumlah(qtyRc * harga));
    refreshTotalJumlah();
  }

  function refreshTotalJumlah() {
    var total = 0;
    $('.po-line-row').each(function () {
      var qtyRc = parseFloat($(this).find('.inp-qty').val()) || 0;
      var harga = parseFloat($(this).find('.inp-harga').val()) || 0;
      total += qtyRc * harga;
    });
    $('#poTotalJumlah').text(formatJumlah(total));
  }

  function refreshRowStatus($row) {
    var qtyPo = parseFloat($row.data('qty-po')) || 0;
    var qtyRc = parseFloat($row.find('.inp-qty').val()) || 0;
    var $st = $row.find('.line-status');
    $row.removeClass('table-success table-warning');
    refreshLineJumlah($row);
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
    refreshLineJumlah($row);
    saveLine($row, function () { refreshRowStatus($row); });
  });

  $('#tblPoLines').on('input', '.inp-qty, .inp-harga', function () {
    refreshLineJumlah($(this).closest('.po-line-row'));
  });

  $('#formScanPo').on('submit', function (e) {
    e.preventDefault();
    var barcode = $.trim($('#inputBarcodePo').val());
    if (!barcode) return;
    $.post('api/pengadaan-po-receive-action.php', { action: 'scan', po_id: poId, barcode: barcode }, null, 'json')
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
    var missingSatuan = false;
    $('.po-line-row').each(function () {
      var $r = $(this);
      var satuan = $.trim($r.find('.inp-satuan').val() || '');
      if (!satuan) missingSatuan = true;
      lines.push({
        line_id: $r.data('line-id'),
        qty_received: $r.find('.inp-qty').val(),
        satuan_nama: satuan,
        harga: $r.find('.inp-harga').val()
      });
    });
    if (missingSatuan) {
      if (window.Swal) Swal.fire('Satuan belum lengkap', 'Semua baris harus punya satuan dari master data.', 'warning');
      else alert('Semua baris harus punya satuan dari master data.');
      return;
    }
    var $btn = $(this).prop('disabled', true);
    $.post('api/pengadaan-po-receive-action.php', { action: 'prepare_invoice', po_id: poId, lines: lines }, null, 'json')
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

  function resetModalTambah() {
    $('#tb_barang_id').val('');
    $('#tb_cari_barang').val('');
    $('#tb_barang_label').text('Belum ada barang dipilih');
    $('#tb_hasil_cari').hide().empty();
    $('#tb_qty_po').val('1');
    $('#tb_satuan_nama').val('');
    $('#tb_cabang_id').val('0');
    $('#modalTambahFeedback').html('');
  }

  function setSelectedBarang(item) {
    $('#tb_barang_id').val(item.barang_id || '');
    $('#tb_barang_label').text((item.barang_kode || '') + ' — ' + (item.barang_nama || ''));
    $('#tb_cari_barang').val((item.barang_kode || '') + ' | ' + (item.barang_nama || ''));
    $('#tb_hasil_cari').hide().empty();
    if (item.satuan_nama) {
      var $opt = $('#tb_satuan_nama option').filter(function () {
        return String($(this).val()).toUpperCase() === String(item.satuan_nama).toUpperCase();
      }).first();
      if ($opt.length) {
        $('#tb_satuan_nama').val($opt.val());
      }
    }
  }

  $('#btnTambahBarang').on('click', function () {
    resetModalTambah();
    $('#modalTambahBarang').modal('show');
    setTimeout(function () { $('#tb_cari_barang').trigger('focus'); }, 300);
  });

  $('#tb_cari_barang').on('input', function () {
    var q = $.trim($(this).val());
    $('#tb_barang_id').val('');
    $('#tb_barang_label').text('Belum ada barang dipilih');
    clearTimeout(cariTimer);
    if (q.length < 2) {
      $('#tb_hasil_cari').hide().empty();
      return;
    }
    cariTimer = setTimeout(function () {
      $.getJSON('api/pengadaan-gudang-action.php', { action: 'po_search_barang', po_id: poId, q: q })
        .done(function (res) {
          var items = (res && res.items) ? res.items : [];
          var $box = $('#tb_hasil_cari').empty();
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
    if (!$(e.target).closest('#tb_cari_barang, #tb_hasil_cari').length) {
      $('#tb_hasil_cari').hide();
    }
  });

  $('#modalTambahSubmit').on('click', function () {
    var barangId = parseInt($('#tb_barang_id').val(), 10) || 0;
    var qty = parseFloat($('#tb_qty_po').val()) || 0;
    var satuan = $.trim($('#tb_satuan_nama').val() || '');
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
    $.post('api/pengadaan-po-receive-action.php', {
      action: 'add_line',
      po_id: poId,
      barang_id: barangId,
      qty_po: qty,
      satuan_nama: satuan,
      cabang_id: $('#tb_cabang_id').val()
    }, null, 'json')
      .done(function (res) {
        if (!res.ok) {
          $('#modalTambahFeedback').html('<div class="alert alert-danger mb-0">' + (res.message || 'Gagal') + '</div>');
          return;
        }
        $('#modalTambahBarang').modal('hide');
        location.reload();
      })
      .fail(function () {
        $('#modalTambahFeedback').html('<div class="alert alert-danger mb-0">Gagal koneksi</div>');
      })
      .always(function () { $btn.prop('disabled', false); });
  });

  $('#tblPoLines').on('click', '.btn-delete-line', function () {
    var lineId = $(this).data('line-id');
    var nama = $(this).data('nama') || 'barang ini';
    if (!confirm('Hapus "' + nama + '" dari PO? Aksi ini tidak dapat dibatalkan.')) return;
    var $btn = $(this).prop('disabled', true);
    $.post('api/pengadaan-po-receive-action.php', { action: 'delete_line', po_id: poId, line_id: lineId }, null, 'json')
      .done(function (res) {
        if (!res.ok) {
          if (window.Swal) Swal.fire('Gagal', res.message || 'Gagal hapus', 'error');
          else alert(res.message || 'Gagal hapus');
          $btn.prop('disabled', false);
          return;
        }
        $('.po-line-row[data-line-id="' + lineId + '"]').remove();
        refreshTotalJumlah();
      })
      .fail(function () {
        if (window.Swal) Swal.fire('Gagal', 'Gagal koneksi', 'error');
        else alert('Gagal koneksi');
        $btn.prop('disabled', false);
      });
  });
});
</script>
