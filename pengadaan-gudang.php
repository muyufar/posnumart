<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
require_once 'aksi/pengadaan-gudang-lib.php';

if (!pengadaan_gudang_can_access((int) $sessionCabang, (string) $levelLogin)) {
    echo "<script>document.location.href = 'bo';</script>";
    exit;
}

$cabangList = pengadaan_gudang_cabang_toko();
pengadaan_gudang_ensure_table($conn);
$initSummary = pengadaan_gudang_summary($conn);
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
            <table id="tblPengadaanGudang" class="table table-bordered table-striped table-sm" style="width:100%">
              <thead class="thead-dark">
                <tr>
                  <th>ID</th>
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
            Notifikasi web ditampilkan di menu sidebar (badge merah). Refresh halaman atau klik Scan Ulang untuk pembaruan terbaru.
          </small>
        </div>
      </div>
    </div>
  </section>
</div>
</div>

<?php include '_footer.php'; ?>

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
    if ($b.length) {
      if (badge > 0) { $b.text(badge).show(); } else { $b.hide(); }
    }
  }

  var table = $('#tblPengadaanGudang').DataTable({
    processing: true,
    serverSide: true,
    order: [[10, 'asc']],
    pageLength: 25,
    ajax: {
      url: 'api/pengadaan-gudang-data.php',
      data: function (d) {
        return $.extend({}, d, filterParams());
      },
      dataSrc: function (json) {
        updateSummary(json.summary);
        return json.data || [];
      }
    },
    columnDefs: [
      { targets: [10, 11, 13], orderable: false },
      { targets: 0, visible: false }
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
