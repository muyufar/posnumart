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

$cabangList = pengadaan_gudang_cabang_toko();
pengadaan_gudang_ensure_table($conn);
pengadaan_po_ensure_tables($conn);
$initSummary = pengadaan_gudang_summary($conn);
$activePoList = pengadaan_po_list_active($conn, 15);
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-7">
          <h1><i class="fas fa-warehouse"></i> Pusat Pengadaan Gudang</h1>
          <p class="text-muted mb-0">Permintaan barang otomatis dari cabang Numart — pantau & proses sebelum stok habis.</p>
        </div>
        <div class="col-sm-5">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Pengadaan Gudang</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div id="pgdAlertBanner" class="alert alert-danger alert-dismissible" style="<?= $initSummary['kritis'] > 0 ? '' : 'display:none;' ?>">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <h5><i class="icon fas fa-exclamation-triangle"></i> Perhatian Gudang</h5>
        <span id="pgdAlertText"><?= (int) $initSummary['kritis']; ?> permintaan KRITIS menunggu tindakan segera.</span>
      </div>

      <div class="row" id="pgdSummaryCards">
        <div class="col-md-3 col-sm-6">
          <div class="info-box bg-danger">
            <span class="info-box-icon"><i class="fas fa-bell"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Menunggu (Pending)</span>
              <span class="info-box-number" id="sumPending"><?= (int) $initSummary['pending']; ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="info-box bg-warning">
            <span class="info-box-icon"><i class="fas fa-fire"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Kritis</span>
              <span class="info-box-number" id="sumKritis"><?= (int) $initSummary['kritis']; ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="info-box bg-info">
            <span class="info-box-icon"><i class="fas fa-cog"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Sedang Diproses</span>
              <span class="info-box-number" id="sumDiproses"><?= (int) $initSummary['diproses']; ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="info-box bg-secondary">
            <span class="info-box-icon"><i class="fas fa-store"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Cabang Aktif</span>
              <span class="info-box-number"><?= count($cabangList); ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="row mb-3">
        <?php foreach ($cabangList as $cabId => $cabNama): ?>
          <?php $cnt = (int) ($initSummary['by_cabang'][$cabId] ?? 0); ?>
          <div class="col-md-3 col-sm-6 mb-2">
            <div class="callout callout-<?= $cnt > 0 ? 'warning' : 'success'; ?> mb-0 pgd-cabang-card" data-cabang="<?= (int) $cabId; ?>">
              <h5><?= htmlspecialchars($cabNama, ENT_QUOTES, 'UTF-8'); ?></h5>
              <p class="mb-0"><strong class="pgd-cabang-count"><?= $cnt; ?></strong> permintaan pending</p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="card card-outline card-success mb-3">
        <div class="card-header">
          <h3 class="card-title"><i class="fab fa-whatsapp"></i> Purchase Order Aktif</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-sm btn-success" id="btnBuatPoTerpilih" disabled>
              <i class="fa fa-file-invoice"></i> Buat PO dari Terpilih
            </button>
            <button type="button" class="btn btn-sm btn-outline-success" id="btnBuatPoSupplier" disabled>
              <i class="fa fa-truck-loading"></i> Buat PO per Supplier (filter)
            </button>
          </div>
        </div>
        <div class="card-body p-0">
          <?php if ($activePoList === []) : ?>
            <p class="text-muted p-3 mb-0">Belum ada PO aktif. Pilih permintaan barang lalu klik <strong>Buat PO</strong>, atau buat PO per supplier dari filter.</p>
          <?php else : ?>
            <div class="table-responsive">
              <table class="table table-sm table-striped mb-0">
                <thead class="thead-light">
                  <tr>
                    <th>No PO</th>
                    <th>Supplier</th>
                    <th>Item</th>
                    <th>Status</th>
                    <th>Dibuat</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($activePoList as $poRow) : ?>
                    <tr>
                      <td><strong><?= htmlspecialchars((string) $poRow['po_number'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                      <td><?= htmlspecialchars((string) $poRow['kode_suplier'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?= (int) ($poRow['jml_item'] ?? 0); ?> barang</td>
                      <td><?= pengadaan_po_status_badge((string) ($poRow['status'] ?? '')); ?></td>
                      <td><?= date('d/m/Y H:i', strtotime((string) ($poRow['created_at'] ?? 'now'))); ?></td>
                      <td>
                        <div class="pgd-aksi-wrap pgd-aksi-wrap--po">
                          <div class="pgd-aksi-group">
                            <button type="button" class="btn btn-success btn-sm btn-po-wa" data-id="<?= (int) $poRow['id']; ?>" title="Kirim WA Supplier"><i class="fab fa-whatsapp"></i></button>
                            <button type="button" class="btn btn-primary btn-sm btn-po-confirm" data-id="<?= (int) $poRow['id']; ?>" title="Supplier sudah konfirmasi"><i class="fa fa-check"></i></button>
                          </div>
                          <div class="pgd-aksi-group">
                            <a href="pengadaan-po-receive?id=<?= (int) $poRow['id']; ?>" class="btn btn-warning btn-sm" title="Terima barang (scan barcode)"><i class="fa fa-barcode"></i></a>
                            <a href="pengadaan-po-detail?id=<?= (int) $poRow['id']; ?>" class="btn btn-outline-info btn-sm" title="Detail PO"><i class="fa fa-eye"></i></a>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-list"></i> Daftar Permintaan Barang</h3>
        </div>
        <div class="card-body">
          <form id="formFilterPgd" class="form-row mb-3">
            <div class="form-group col-md-2">
              <label>Cabang</label>
              <select class="form-control" id="filterCabang">
                <option value="0">Semua cabang</option>
                <?php foreach ($cabangList as $cabId => $cabNama): ?>
                  <option value="<?= (int) $cabId; ?>"><?= htmlspecialchars($cabNama, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-2">
              <label>Status</label>
              <select class="form-control" id="filterStatus">
                <option value="aktif" selected>Menunggu + Diproses</option>
                <option value="pending">Menunggu saja</option>
                <option value="diproses">Diproses saja</option>
                <option value="semua">Semua status</option>
              </select>
            </div>
            <div class="form-group col-md-2">
              <label>Prioritas</label>
              <select class="form-control" id="filterPrioritas">
                <option value="semua">Semua</option>
                <option value="kritis">Kritis</option>
                <option value="perlu_isi">Perlu Isi</option>
              </select>
            </div>
            <div class="form-group col-md-2">
              <label>Analisis (hari)</label>
              <input type="number" class="form-control" id="analisisHari" value="30" min="7" max="90">
            </div>
            <div class="form-group col-md-2">
              <label>Target cover (hari)</label>
              <input type="number" class="form-control" id="targetCover" value="14" min="7" max="60">
            </div>
            <div class="form-group col-md-2">
              <label>&nbsp;</label>
              <button type="button" class="btn btn-success btn-block" id="btnScanPgd"><i class="fa fa-sync"></i> Scan Ulang</button>
            </div>
          </form>
          <div class="form-row mb-3">
            <div class="form-group col-md-4 col-lg-3">
              <label for="filterKodeSuplier"><i class="fas fa-truck-loading"></i> Kode Supplier</label>
              <input type="text" class="form-control" id="filterKodeSuplier" placeholder="Cari kode supplier..." autocomplete="off">
              <small class="text-muted">Bisa sebagian kode; tekan Enter atau klik Terapkan.</small>
            </div>
            <div class="form-group col-md-2">
              <label>&nbsp;</label>
              <button type="button" class="btn btn-primary btn-block" id="btnTerapkanFilter"><i class="fa fa-search"></i> Terapkan</button>
            </div>
            <div class="form-group col-md-2">
              <label>&nbsp;</label>
              <button type="button" class="btn btn-outline-secondary btn-block" id="btnResetFilter"><i class="fa fa-undo"></i> Reset</button>
            </div>
          </div>

          <div class="table-auto">
            <table id="tblPengadaanGudang" class="table table-bordered table-striped table-sm pgd-table" style="width:100%">
              <thead class="thead-dark">
                <tr>
                  <th>ID</th>
                  <th class="text-center pgd-col-check"><input type="checkbox" id="pgdCheckAll" title="Pilih semua di halaman ini"></th>
                  <th>Cabang</th>
                  <th>Kode</th>
                  <th>Nama Barang</th>
                  <th>Kode Supplier</th>
                  <th>Stok Cabang</th>
                  <th>Stok Gudang</th>
                  <th>Avg/hari</th>
                  <th>Cover (hr)</th>
                  <th>Qty Diminta</th>
                  <th>Prioritas</th>
                  <th>Status</th>
                  <th>Update</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

          <small class="text-muted d-block mt-2">
            Sistem otomatis membuat permintaan jika cover stok cabang &lt; target, stok habis, atau stok &lt; 3 hari penjualan.
            Centang barang → <strong>Buat PO</strong> → kirim template WA ke supplier → setelah barang datang, <strong>scan barcode</strong> di halaman terima → lanjut ke Invoice Pembelian.
            Notifikasi web ditampilkan di menu sidebar (badge merah).
          </small>
        </div>
      </div>
    </div>
  </section>
</div>
</div>

<?php include '_footer.php'; ?>

<style>
.pgd-table .pgd-col-check { width: 42px; min-width: 42px; }
.pgd-table td.pgd-col-check,
.pgd-table th.pgd-col-check { text-align: center; vertical-align: middle; }
.pgd-aksi-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
  justify-content: flex-start;
  min-width: 120px;
}
.pgd-aksi-group {
  display: inline-flex;
  flex-wrap: nowrap;
  gap: 4px;
  align-items: center;
}
.pgd-aksi-wrap .btn-sm {
  padding: 0.2rem 0.45rem;
  line-height: 1.3;
}
#tblPengadaanGudang td:last-child,
#tblPengadaanGudang th:last-child {
  min-width: 130px;
  white-space: normal;
}
</style>

<script>
$(function () {
  function filterParams() {
    return {
      cabang: $('#filterCabang').val(),
      status: $('#filterStatus').val(),
      prioritas: $('#filterPrioritas').val(),
      analisis_hari: $('#analisisHari').val(),
      target_cover: $('#targetCover').val(),
      kode_suplier: $('#filterKodeSuplier').val()
    };
  }

  function updateSummary(summary) {
    if (!summary) return;
    $('#sumPending').text(summary.pending || 0);
    $('#sumKritis').text(summary.kritis || 0);
    $('#sumDiproses').text(summary.diproses || 0);
    if ((summary.kritis || 0) > 0) {
      $('#pgdAlertBanner').show();
      $('#pgdAlertText').text((summary.kritis || 0) + ' permintaan KRITIS menunggu tindakan segera.');
    } else {
      $('#pgdAlertBanner').hide();
    }
    if (summary.by_cabang) {
      $('.pgd-cabang-card').each(function () {
        var cab = $(this).data('cabang');
        var c = summary.by_cabang[cab] || 0;
        $(this).find('.pgd-cabang-count').text(c);
        $(this).removeClass('callout-success callout-warning').addClass(c > 0 ? 'callout-warning' : 'callout-success');
      });
    }
    var badge = (summary.pending || 0) + (summary.diproses || 0);
    var $b = $('#badge-pengadaan-gudang');
    var $nav = $('#nav-pengadaan-gudang');
    if ($b.length) {
      if (badge > 0) {
        $b.text(badge > 99 ? '99+' : badge).addClass('is-visible').show();
        $nav.addClass('has-badge');
        $b.removeClass('badge-danger badge-warning badge-light is-kritis');
        $b.addClass((summary.kritis || 0) > 0 ? 'badge-danger is-kritis' : 'badge-light');
      } else {
        $b.removeClass('is-visible').hide();
        $nav.removeClass('has-badge');
      }
    }
  }

  var table = $('#tblPengadaanGudang').DataTable({
    processing: true,
    serverSide: true,
    order: [[11, 'asc']],
    pageLength: 25,
    ajax: {
      url: 'api/pengadaan-gudang-data.php',
      data: function (d) {
        return $.extend({}, d, filterParams());
      },
      dataSrc: function (json) {
        updateSummary(json.summary);
        $('#pgdCheckAll').prop('checked', false);
        return json.data || [];
      }
    },
    columnDefs: [
      { targets: 0, visible: false },
      { targets: 1, orderable: false, className: 'pgd-col-check text-center' },
      { targets: [11, 12, 14], orderable: false },
      { targets: 14, className: 'pgd-col-aksi' }
    ]
  });

  $('#filterCabang, #filterStatus, #filterPrioritas').on('change', function () {
    table.ajax.reload();
  });

  $('#btnTerapkanFilter').on('click', function () {
    table.ajax.reload();
  });

  $('#filterKodeSuplier').on('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      table.ajax.reload();
    }
  });

  var pgdSuplierTimer;
  $('#filterKodeSuplier').on('input', function () {
    clearTimeout(pgdSuplierTimer);
    pgdSuplierTimer = setTimeout(function () {
      table.ajax.reload();
    }, 450);
  });

  $('#btnResetFilter').on('click', function () {
    $('#filterCabang').val('0');
    $('#filterStatus').val('aktif');
    $('#filterPrioritas').val('semua');
    $('#filterKodeSuplier').val('');
    table.ajax.reload();
    togglePoButtons();
  });

  function selectedIds() {
    var ids = [];
    $('#tblPengadaanGudang .pgd-check:checked').each(function () {
      ids.push($(this).val());
    });
    return ids;
  }

  function togglePoButtons() {
    var ids = selectedIds();
    var hasSupplier = $.trim($('#filterKodeSuplier').val()) !== '';
    $('#btnBuatPoTerpilih').prop('disabled', ids.length === 0);
    $('#btnBuatPoSupplier').prop('disabled', !hasSupplier);
    var $checks = $('#tblPengadaanGudang .pgd-check');
    var $checked = $('#tblPengadaanGudang .pgd-check:checked');
    $('#pgdCheckAll').prop('checked', $checks.length > 0 && $checks.length === $checked.length);
  }

  $('#pgdCheckAll').on('change', function () {
    var checked = $(this).is(':checked');
    $('#tblPengadaanGudang .pgd-check').prop('checked', checked);
    togglePoButtons();
  });

  function promptSupplierWaEdit(res) {
    var editUrl = (res && res.edit_url) ? String(res.edit_url) : 'supplier-add';
    var nama = (res && (res.supplier_nama || res.kode_suplier)) || 'Supplier';
    var msg = (res && res.alert_message)
      ? res.alert_message
      : ('Supplier "' + nama + '" belum punya nomor WhatsApp.');
    msg += ' Lengkapi nomor WA di halaman edit supplier.';

    function goEdit() {
      window.location.assign(editUrl);
    }

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'WhatsApp belum diisi',
        text: msg,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Edit Supplier',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        focusConfirm: true,
        footer: '<a href="' + editUrl.replace(/"/g, '&quot;') + '">Klik di sini jika tombol tidak berfungsi</a>'
      }).then(function (result) {
        if (!result) return;
        if (result.isConfirmed === true || result.value === true) {
          goEdit();
        }
      });
    } else if (confirm(msg + '\n\nBuka halaman edit supplier?')) {
      goEdit();
    }
  }

  $('#tblPengadaanGudang').on('change', '.pgd-check', togglePoButtons);
  table.on('draw', togglePoButtons);
  $('#filterKodeSuplier').on('input change', togglePoButtons);

  $('#btnBuatPoTerpilih').on('click', function () {
    var ids = selectedIds();
    if (!ids.length) return;
    var $btn = $(this).prop('disabled', true);
    $.post('api/pengadaan-gudang-action.php', { action: 'create_po', ids: ids })
      .done(function (res) {
        if (res.ok) {
          if (typeof Swal !== 'undefined') Swal.fire('PO dibuat', res.message, 'success');
          location.reload();
        } else if (res.edit_url) {
          promptSupplierWaEdit(res);
        } else if (typeof Swal !== 'undefined') {
          Swal.fire('Gagal', res.message || 'Gagal buat PO', 'error');
        } else {
          alert(res.message || 'Gagal buat PO');
        }
      }).always(function () { $btn.prop('disabled', false); togglePoButtons(); });
  });

  $('#btnBuatPoSupplier').on('click', function () {
    var ks = $.trim($('#filterKodeSuplier').val());
    if (!ks) return;
    var $btn = $(this).prop('disabled', true);
    $.getJSON('api/pengadaan-gudang-action.php', { action: 'check_supplier_wa', kode_suplier: ks })
      .done(function (check) {
        if (!check.has_wa) {
          promptSupplierWaEdit(check);
          return;
        }
        if (!confirm('Buat PO untuk semua permintaan aktif supplier "' + ks + '"?')) return;
        $.post('api/pengadaan-gudang-action.php', { action: 'create_po_by_supplier', kode_suplier: ks })
          .done(function (res) {
            if (res.ok) {
              if (typeof Swal !== 'undefined') Swal.fire('PO dibuat', res.message, 'success');
              location.reload();
            } else if (res.edit_url) {
              promptSupplierWaEdit(res);
            } else if (typeof Swal !== 'undefined') {
              Swal.fire('Gagal', res.message || 'Gagal buat PO', 'error');
            }
          });
      }).always(function () { $btn.prop('disabled', false); togglePoButtons(); });
  });

  function openPoWa(poId) {
    $.getJSON('api/pengadaan-gudang-action.php', { action: 'po_wa', po_id: poId })
      .done(function (res) {
        if (!res.ok) {
          if (typeof Swal !== 'undefined') Swal.fire('Gagal', res.message || 'Gagal', 'error');
          return;
        }
        if (!res.has_wa) {
          promptSupplierWaEdit(res);
          return;
        }
        window.open(res.link, '_blank');
      });
  }

  $(document).on('click', '.btn-po-wa', function () {
    openPoWa($(this).data('id'));
  });

  $(document).on('click', '.btn-po-confirm', function () {
    var poId = $(this).data('id');
    $.post('api/pengadaan-gudang-action.php', { action: 'po_confirm', po_id: poId })
      .done(function (res) {
        if (res.ok) location.reload();
        else if (typeof Swal !== 'undefined') Swal.fire('Gagal', res.message, 'error');
      });
  });

  $('#btnScanPgd').on('click', function () {
    var $btn = $(this).prop('disabled', true);
    $.post('api/pengadaan-gudang-action.php', {
      action: 'sync',
      analisis_hari: $('#analisisHari').val(),
      target_cover: $('#targetCover').val()
    }).done(function (res) {
      if (res.ok) {
        table.ajax.reload();
        if (typeof Swal !== 'undefined') {
          Swal.fire('Scan selesai', 'Ditemukan ' + (res.stats.created || 0) + ' baru, ' + (res.stats.updated || 0) + ' diperbarui.', 'success');
        }
      }
    }).always(function () { $btn.prop('disabled', false); });
  });

  function setStatus(id, status) {
    $.post('api/pengadaan-gudang-action.php', { action: 'update_status', id: id, status: status })
      .done(function (res) {
        if (res.ok) {
          table.ajax.reload(null, false);
          $.getJSON('api/pengadaan-gudang-notif.php').done(function (n) {
            if (n.ok) updateSummary(n);
          });
        } else if (typeof Swal !== 'undefined') {
          Swal.fire('Gagal', res.message || 'Gagal memperbarui', 'error');
        }
      });
  }

  $('#tblPengadaanGudang').on('click', '.btn-pgd-proses', function () {
    setStatus($(this).data('id'), 'diproses');
  });
  $('#tblPengadaanGudang').on('click', '.btn-pgd-selesai', function () {
    setStatus($(this).data('id'), 'selesai');
  });
  $('#tblPengadaanGudang').on('click', '.btn-pgd-tolak', function () {
    if (confirm('Tolak permintaan ini?')) setStatus($(this).data('id'), 'ditolak');
  });

  $('.pgd-cabang-card').css('cursor', 'pointer').on('click', function () {
    $('#filterCabang').val($(this).data('cabang')).trigger('change');
  });

  setInterval(function () {
    $.getJSON('api/pengadaan-gudang-notif.php').done(function (n) {
      if (n.ok) updateSummary(n);
    });
  }, 60000);
});
</script>
</body>
</html>
