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
$chkAggInit = mysqli_query($conn, "SELECT id FROM pengadaan_request WHERE cabang_id = 0 AND status IN ('pending','diproses') LIMIT 1");
if (!$chkAggInit || mysqli_num_rows($chkAggInit) < 1) {
    $initSummary = pengadaan_gudang_summary_aggregated($conn);
}
$activePoPerPage = 15;
$activePoTotal = pengadaan_po_count_active($conn);
$activePoTotalPages = max(1, (int) ceil($activePoTotal / $activePoPerPage));
$activePoPage = max(1, (int) ($_GET['po_page'] ?? 1));
$activePoPage = min($activePoPage, $activePoTotalPages);
$activePoList = pengadaan_po_list_active($conn, $activePoPerPage, ($activePoPage - 1) * $activePoPerPage);
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

      <div class="alert alert-secondary py-2 mb-3">
        <i class="fas fa-layer-group"></i>
        Satu baris = satu kode barang. Kolom <strong>Stok</strong> = Gudang + Dukun + Pakis + PP Srumbung + Tegalrejo
        (live dari master barang, sama seperti Data Stock Keseluruhan). Klik <strong>Scan Ulang</strong> untuk refresh kebutuhan.
      </div>

      <div class="card card-outline card-success mb-3">
        <div class="card-header">
          <h3 class="card-title"><i class="fab fa-whatsapp"></i> Purchase Order Aktif</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-sm btn-success" id="btnBuatPoTerpilih" disabled>
              <i class="fas fa-clipboard-check mr-1" aria-hidden="true"></i> Buat PO dari Terpilih
            </button>
            <button type="button" class="btn btn-sm btn-outline-success" id="btnBuatPoSupplier" disabled>
              <i class="fas fa-truck-loading mr-1" aria-hidden="true"></i> Buat PO per Supplier (filter)
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
                      <td class="pgd-po-aksi-cell">
                        <div class="pgd-aksi-wrap pgd-aksi-wrap--po">
                          <button type="button" class="btn btn-success btn-sm btn-po-wa" data-id="<?= (int) $poRow['id']; ?>" title="Kirim WA Supplier"><i class="fab fa-whatsapp"></i></button>
                          <button type="button" class="btn btn-primary btn-sm btn-po-confirm" data-id="<?= (int) $poRow['id']; ?>" title="Supplier sudah konfirmasi"><i class="fa fa-check"></i></button>
                          <a href="pengadaan-po-detail?id=<?= (int) $poRow['id']; ?>&edit=1" class="btn btn-outline-secondary btn-sm" title="Edit qty/satuan"><i class="fa fa-edit"></i></a>
                          <a href="pengadaan-po-receive?id=<?= (int) $poRow['id']; ?>" class="btn btn-warning btn-sm" title="Terima barang (scan barcode)"><i class="fa fa-barcode"></i></a>
                          <a href="pengadaan-po-detail?id=<?= (int) $poRow['id']; ?>" class="btn btn-outline-info btn-sm" title="Detail PO"><i class="fa fa-eye"></i></a>
                          <button type="button" class="btn btn-danger btn-sm btn-po-delete" data-id="<?= (int) $poRow['id']; ?>" data-no="<?= htmlspecialchars((string) $poRow['po_number'], ENT_QUOTES, 'UTF-8'); ?>" title="Hapus PO"><i class="fa fa-trash"></i></button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php if ($activePoTotalPages > 1) : ?>
              <div class="d-flex flex-wrap justify-content-between align-items-center px-3 py-2 border-top bg-light">
                <small class="text-muted mb-2 mb-sm-0">
                  Menampilkan <?= (($activePoPage - 1) * $activePoPerPage) + 1; ?>–<?= min($activePoPage * $activePoPerPage, $activePoTotal); ?> dari <?= $activePoTotal; ?> PO aktif
                </small>
                <nav aria-label="Halaman daftar PO aktif">
                  <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $activePoPage <= 1 ? 'disabled' : ''; ?>">
                      <a class="page-link" href="pengadaan-gudang?po_page=<?= max(1, $activePoPage - 1); ?>" aria-label="Sebelumnya"><i class="fas fa-chevron-left"></i></a>
                    </li>
                    <?php for ($page = 1; $page <= $activePoTotalPages; $page++) : ?>
                      <li class="page-item <?= $page === $activePoPage ? 'active' : ''; ?>">
                        <a class="page-link" href="pengadaan-gudang?po_page=<?= $page; ?>"><?= $page; ?></a>
                      </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $activePoPage >= $activePoTotalPages ? 'disabled' : ''; ?>">
                      <a class="page-link" href="pengadaan-gudang?po_page=<?= min($activePoTotalPages, $activePoPage + 1); ?>" aria-label="Berikutnya"><i class="fas fa-chevron-right"></i></a>
                    </li>
                  </ul>
                </nav>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-list"></i> Daftar Permintaan Barang</h3>
        </div>
        <div class="card-body">
          <form id="formFilterPgd" class="pgd-filter-toolbar mb-3" onsubmit="return false;">
            <input type="hidden" id="filterCabang" value="0">
            <div class="form-group pgd-filter-field pgd-filter-field--status">
              <label>Status</label>
              <select class="form-control" id="filterStatus">
                <option value="aktif" selected>Menunggu + Diproses</option>
                <option value="pending">Menunggu saja</option>
                <option value="diproses">Diproses saja</option>
                <option value="semua">Semua status</option>
              </select>
            </div>
            <div class="form-group pgd-filter-field pgd-filter-field--priority">
              <label>Prioritas</label>
              <select class="form-control" id="filterPrioritas">
                <option value="semua">Semua</option>
                <option value="kritis">Kritis</option>
                <option value="perlu_isi">Perlu Isi</option>
              </select>
            </div>
            <div class="form-group pgd-filter-field pgd-filter-field--number">
              <label>Analisis (hari)</label>
              <input type="number" class="form-control" id="analisisHari" value="30" min="7" max="90">
            </div>
            <div class="form-group pgd-filter-field pgd-filter-field--number">
              <label>Target cover (hari)</label>
              <input type="number" class="form-control" id="targetCover" value="14" min="7" max="60">
            </div>
            <div class="form-group pgd-filter-field pgd-filter-field--supplier">
              <label for="filterKodeSuplier"><i class="fas fa-truck-loading"></i> Kode Supplier</label>
              <input type="text" class="form-control" id="filterKodeSuplier" placeholder="Cari kode supplier..." autocomplete="off">
            </div>
            <div class="pgd-filter-actions">
              <button type="button" class="btn btn-primary" id="btnTerapkanFilter"><i class="fa fa-search"></i> Terapkan</button>
              <button type="button" class="btn btn-outline-secondary" id="btnResetFilter"><i class="fa fa-undo"></i> Reset</button>
              <button type="button" class="btn btn-success" id="btnScanPgd"><i class="fa fa-sync"></i> Scan Ulang</button>
            </div>
          </form>
          <small class="text-muted d-block mb-3 pgd-filter-hint"><i class="fas fa-info-circle mr-1"></i>Kode supplier dapat diisi sebagian; tekan Enter atau tombol Terapkan untuk memfilter daftar.</small>

          <div class="table-responsive">
            <table id="tblPengadaanGudang" class="table table-bordered table-striped table-sm pgd-table w-100">
              <thead class="thead-dark">
                <tr>
                  <th>ID</th>
                  <th class="text-center pgd-col-check"><input type="checkbox" id="pgdCheckAll" title="Pilih semua di halaman ini"></th>
                  <th>Kode</th>
                  <th>Nama Barang</th>
                  <th>Supplier</th>
                  <th title="Stok seluruh toko + gudang">Stok</th>
                  <th>Avg/hari</th>
                  <th>Cover</th>
                  <th>Qty</th>
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
            Sistem otomatis membuat permintaan jika cover stok toko (akumulasi) &lt; target, stok habis, atau stok &lt; 3 hari penjualan.
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
.pgd-filter-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 12px;
  padding: 14px;
  border: 1px solid #d9e6f2;
  border-radius: 10px;
  background: #f8fbff;
}
.pgd-filter-toolbar .form-group { margin: 0; }
.pgd-filter-field label {
  display: block;
  margin: 0 0 5px;
  color: #495057;
  font-size: .78rem;
  font-weight: 700;
}
.pgd-filter-field .form-control { height: 38px; min-width: 118px; }
.pgd-filter-field--status { width: 190px; }
.pgd-filter-field--priority { width: 150px; }
.pgd-filter-field--number { width: 135px; }
.pgd-filter-field--supplier { flex: 1 1 210px; min-width: 210px; }
.pgd-filter-actions { display: flex; flex-wrap: wrap; gap: 7px; }
.pgd-filter-actions .btn { min-height: 38px; white-space: nowrap; }
.pgd-filter-hint { padding-left: 3px; font-size: .78rem; }
@media (max-width: 767.98px) {
  .pgd-filter-toolbar { gap: 10px; }
  .pgd-filter-field--status, .pgd-filter-field--priority, .pgd-filter-field--number { flex: 1 1 calc(50% - 5px); width: auto; }
  .pgd-filter-field--supplier { flex-basis: 100%; }
  .pgd-filter-actions { width: 100%; }
  .pgd-filter-actions .btn { flex: 1 1 auto; }
}
.pgd-table .pgd-col-check { width: 36px; min-width: 36px; }
.pgd-table td.pgd-col-check,
.pgd-table th.pgd-col-check { text-align: center; vertical-align: middle; }
.pgd-table th, .pgd-table td { vertical-align: middle; white-space: nowrap; }
.pgd-table td:nth-child(4) { white-space: normal; min-width: 180px; max-width: 280px; }
.pgd-aksi-wrap {
  display: inline-flex;
  flex-wrap: nowrap;
  gap: 4px;
  align-items: center;
  white-space: nowrap;
}
.pgd-aksi-group {
  display: inline-flex;
  flex-wrap: nowrap;
  gap: 4px;
  align-items: center;
}
.pgd-aksi-wrap .btn-sm {
  padding: 0.2rem 0.4rem;
  line-height: 1.25;
}
.pgd-aksi-wrap--po { min-width: 210px; }
.pgd-po-aksi-cell { white-space: nowrap; }
#tblPengadaanGudang td:last-child,
#tblPengadaanGudang th:last-child {
  min-width: 150px;
}
#tblPengadaanGudang td:nth-child(6),
#tblPengadaanGudang td:nth-child(7),
#tblPengadaanGudang td:nth-child(8),
#tblPengadaanGudang td:nth-child(9) {
  text-align: right;
  font-variant-numeric: tabular-nums;
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
    scrollX: true,
    autoWidth: false,
    order: [[9, 'asc']],
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
      { targets: [9, 10, 12], orderable: false },
      { targets: 12, className: 'pgd-col-aksi' },
      { targets: [5, 6, 7, 8], className: 'text-right' }
    ]
  });

  $('#filterStatus, #filterPrioritas').on('change', function () {
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

  function afterPoCreated(res) {
    var msg = (res && res.message) ? res.message : 'PO berhasil dibuat';
    var needWa = !!(res && res.need_wa && res.missing_wa && res.missing_wa.length);
    var firstMissing = needWa ? (res.missing_wa[0] || {}) : null;

    function reloadList() {
      // Reload cepat: jangan jalankan sync berat lagi
      window.location.href = 'pengadaan-gudang?po_created=1';
    }

    if (needWa && firstMissing) {
      var waMsg = msg + '. Beberapa supplier belum punya nomor WhatsApp — isi WA lalu kirim dari tombol hijau di list PO.';
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'PO dibuat',
          text: waMsg,
          icon: 'success',
          showCancelButton: true,
          confirmButtonText: 'Isi WA Supplier',
          cancelButtonText: 'Nanti',
          reverseButtons: true
        }).then(function (result) {
          if (result && (result.isConfirmed === true || result.value === true) && firstMissing.edit_url) {
            window.location.assign(firstMissing.edit_url);
          } else {
            reloadList();
          }
        });
      } else {
        alert(waMsg);
        reloadList();
      }
      return;
    }

    if (typeof Swal !== 'undefined') {
      Swal.fire({ title: 'PO dibuat', text: msg, icon: 'success', timer: 900, showConfirmButton: false })
        .then(reloadList);
    } else {
      alert(msg);
      reloadList();
    }
  }

  $('#btnBuatPoTerpilih').on('click', function () {
    var ids = selectedIds();
    if (!ids.length) {
      if (typeof Swal !== 'undefined') Swal.fire('Pilih barang', 'Centang minimal 1 barang di daftar permintaan.', 'info');
      return;
    }
    var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Membuat PO...');
    $.ajax({
      url: 'api/pengadaan-gudang-action.php',
      method: 'POST',
      dataType: 'json',
      data: { action: 'create_po', ids: ids }
    })
      .done(function (res) {
        if (res && res.ok) {
          afterPoCreated(res);
        } else if (typeof Swal !== 'undefined') {
          Swal.fire('Gagal', (res && res.message) ? res.message : 'Gagal buat PO', 'error');
        } else {
          alert((res && res.message) || 'Gagal buat PO');
        }
      })
      .fail(function () {
        if (typeof Swal !== 'undefined') Swal.fire('Gagal', 'Koneksi bermasalah', 'error');
        else alert('Koneksi bermasalah');
      })
      .always(function () {
        $btn.prop('disabled', false).html('<i class="fa fa-file-invoice"></i> Buat PO dari Terpilih');
        togglePoButtons();
      });
  });

  $('#btnBuatPoSupplier').on('click', function () {
    var ks = $.trim($('#filterKodeSuplier').val());
    if (!ks) return;
    var $btn = $(this).prop('disabled', true);
    if (!confirm('Buat PO untuk semua permintaan aktif supplier "' + ks + '"?')) {
      $btn.prop('disabled', false);
      togglePoButtons();
      return;
    }
    $.ajax({
      url: 'api/pengadaan-gudang-action.php',
      method: 'POST',
      dataType: 'json',
      data: { action: 'create_po_by_supplier', kode_suplier: ks }
    })
      .done(function (res) {
        if (res && res.ok) {
          afterPoCreated(res);
        } else if (typeof Swal !== 'undefined') {
          Swal.fire('Gagal', (res && res.message) ? res.message : 'Gagal buat PO', 'error');
        } else {
          alert((res && res.message) || 'Gagal');
        }
      })
      .fail(function () {
        if (typeof Swal !== 'undefined') Swal.fire('Gagal', 'Koneksi bermasalah', 'error');
      })
      .always(function () {
        $btn.prop('disabled', false);
        togglePoButtons();
      });
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

  $(document).on('click', '.btn-po-delete', function () {
    var poId = $(this).data('id');
    var noPo = $(this).data('no') || ('#' + poId);
    var $btn = $(this);
    function doDelete() {
      $btn.prop('disabled', true);
      $.post('api/pengadaan-gudang-action.php', { action: 'po_delete', po_id: poId })
        .done(function (res) {
          if (res && res.ok) {
            if (typeof Swal !== 'undefined') {
              Swal.fire({ icon: 'success', title: 'Terhapus', text: res.message || 'PO dihapus', timer: 1200, showConfirmButton: false })
                .then(function () { location.reload(); });
            } else {
              alert(res.message || 'PO dihapus');
              location.reload();
            }
          } else if (typeof Swal !== 'undefined') {
            Swal.fire('Gagal', (res && res.message) || 'Gagal hapus PO', 'error');
            $btn.prop('disabled', false);
          } else {
            alert((res && res.message) || 'Gagal hapus PO');
            $btn.prop('disabled', false);
          }
        })
        .fail(function () {
          if (typeof Swal !== 'undefined') Swal.fire('Gagal', 'Koneksi bermasalah', 'error');
          $btn.prop('disabled', false);
        });
    }
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Hapus PO?',
        text: 'PO ' + noPo + ' akan dihapus dari daftar. Permintaan terkait dikembalikan ke Menunggu.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Hapus',
        confirmButtonColor: '#d33',
        cancelButtonText: 'Batal'
      }).then(function (r) {
        if (r && (r.isConfirmed === true || r.value === true)) doDelete();
      });
    } else if (confirm('Hapus PO ' + noPo + '?')) {
      doDelete();
    }
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

  function setStatus(ids, status) {
    $.post('api/pengadaan-gudang-action.php', { action: 'update_status', ids: ids, status: status })
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
    setStatus($(this).data('ids') || $(this).data('id'), 'diproses');
  });
  $('#tblPengadaanGudang').on('click', '.btn-pgd-selesai', function () {
    setStatus($(this).data('ids') || $(this).data('id'), 'selesai');
  });
  $('#tblPengadaanGudang').on('click', '.btn-pgd-tolak', function () {
    if (confirm('Tolak permintaan ini?')) setStatus($(this).data('ids') || $(this).data('id'), 'ditolak');
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
