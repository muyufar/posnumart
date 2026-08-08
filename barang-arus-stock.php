<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
error_reporting(0);

if ($levelLogin === "kasir" && $levelLogin === "kurir") {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
}

$today = date('Y-m-d');
$defaultFrom = date('Y-m-d', strtotime('-29 days'));

$arusCabangToko = isset($sessionCabang) && (int) $sessionCabang >= 1;
$arusNamaToko = '';
if ($arusCabangToko) {
    $arusNamaToko = (string) ($dataTokoLogin['toko_nama'] ?? '');
    if ($arusNamaToko === '') {
        $arusMapCab = [1 => 'Dukun', 2 => 'Pakis', 3 => 'PP Srumbung', 5 => 'Tegalrejo'];
        $arusNamaToko = $arusMapCab[(int) $sessionCabang] ?? ('Cabang ' . (int) $sessionCabang);
    }
}

$arusBranches = include __DIR__ . '/aksi/arus-stock-branches.php';
?>

<div class="content-wrapper arus-stock-page">
  <div class="arus-stock-sticky">
    <section class="content-header pb-0">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Arus Stock Barang</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Barang</li>
            </ol>
          </div>
          <div class="col-12">
            <a href="barang" class="btn btn-primary btn-sm">Kembali</a>
          </div>
        </div>
      </div>
    </section>

    <section class="content pt-2">
      <div class="container-fluid">
        <div class="card card-outline card-primary mb-0">
          <div class="card-header py-2">
            <h3 class="card-title mb-0">Analisa fast/slow moving (<?= $arusCabangToko ? 'cabang: ' . htmlspecialchars($arusNamaToko, ENT_QUOTES, 'UTF-8') : 'semua toko'; ?>)</h3>
          </div>
          <div class="card-body py-2">
            <form id="formFilter" class="arus-filter-toolbar mb-0" onsubmit="return false;">
              <div class="form-group mb-0">
                <label>Dari</label>
                <input type="date" class="form-control form-control-sm" id="from" value="<?= htmlspecialchars($defaultFrom, ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="form-group mb-0">
                <label>Sampai</label>
                <input type="date" class="form-control form-control-sm" id="to" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="form-group mb-0">
                <label>Fast &gt;=</label>
                <input type="number" step="0.01" class="form-control form-control-sm" id="fast" value="1" style="width:90px;">
              </div>
              <div class="form-group mb-0">
                <label>Slow &lt;=</label>
                <input type="number" step="0.01" class="form-control form-control-sm" id="slow" value="0.2" style="width:90px;">
              </div>
              <div class="form-group mb-0">
                <label>Target cover (hari)</label>
                <input type="number" step="1" class="form-control form-control-sm" id="cover" value="14" style="width:90px;">
              </div>
              <div class="arus-filter-actions">
                <button type="button" class="btn btn-success btn-sm" id="btnApply"><i class="fa fa-check"></i> Terapkan</button>
                <button type="button" class="btn btn-info btn-sm" id="btnExport"><i class="fa fa-file-excel"></i> Export Excel</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </section>
  </div>

  <section class="content pt-2">
    <div class="container-fluid">
      <div class="card mb-2">
        <div class="card-body pt-2">
          <table id="arusTable" class="table table-bordered table-striped table-sm" style="width: 100%">
            <thead class="thead-dark">
              <tr>
                <th rowspan="2" style="width: 6%; vertical-align: middle;">No.</th>
                <th rowspan="2" style="width: 13%; vertical-align: middle;">Kode</th>
                <th rowspan="2" style="vertical-align: middle;">Nama</th>
                <th rowspan="2" style="width: 12%; vertical-align: middle;">Kode Supplier</th>
                <th rowspan="2" style="width: 10%; vertical-align: middle;">Terjual (total)</th>
                <th rowspan="2" style="width: 10%; vertical-align: middle;">Terjual (periode)</th>
                <?php if ($arusCabangToko): ?>
                <th colspan="2" class="text-center"><?= htmlspecialchars($arusNamaToko, ENT_QUOTES, 'UTF-8'); ?></th>
                <?php else: ?>
                <?php foreach ($arusBranches as $br): ?>
                <th colspan="2" class="text-center"><?= htmlspecialchars($br['label'], ENT_QUOTES, 'UTF-8'); ?></th>
                <?php endforeach; ?>
                <?php endif; ?>
                <th rowspan="2" style="width: 10%; vertical-align: middle;">Avg / hari</th>
                <?php if (!$arusCabangToko): ?>
                <th rowspan="2" style="width: 10%; vertical-align: middle;">Total Stock</th>
                <?php endif; ?>
                <th rowspan="2" style="width: 10%; vertical-align: middle;">Cover (hari)</th>
                <th rowspan="2" style="width: 10%; vertical-align: middle;">Kategori</th>
                <th rowspan="2" style="vertical-align: middle;">Rekomendasi</th>
                <th rowspan="2" style="width: 8%; vertical-align: middle;">Detail</th>
              </tr>
              <tr>
                <?php if ($arusCabangToko): ?>
                <th class="text-center" style="width: 8%;">Penjualan</th>
                <th class="text-center" style="width: 8%;">Stock</th>
                <?php else: ?>
                <?php foreach ($arusBranches as $br): ?>
                <th class="text-center" style="width: 8%;">Penjualan</th>
                <th class="text-center" style="width: 8%;">Stock</th>
                <?php endforeach; ?>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
          <small class="text-muted d-block mt-2">
            Catatan: “Terjual” dihitung dari tabel transaksi `penjualan` berdasarkan tanggal yang kamu pilih, dalam PCS (qty × konversi ke satuan terkecil).
            Total stok dan cover memakai satuan terkecil (PCS), dari nilai stok disimpan menurut satuan barang.
            <?php if ($arusCabangToko): ?>
            Tampilan cabang: kolom toko menampilkan <strong>Penjualan</strong> (periode filter) dan <strong>Stock</strong> cabang Anda.
            <?php else: ?>
            Kolom <strong>Terjual (periode)</strong> = total penjualan semua cabang; setiap kolom cabang berisi <strong>Penjualan</strong> (periode) dan <strong>Stock</strong> (PCS) cabang tersebut.
            Total periode ≈ jumlah Penjualan semua cabang.
            <?php endif; ?>
          </small>
          <div class="mt-2">
            <span class="arus-val-badge arus-val-zero">0</span> Merah &nbsp;
            <span class="arus-val-badge arus-val-low">1–5</span> Kuning &nbsp;
            <span class="arus-val-badge arus-val-high">&gt;5</span> Hijau
            <small class="text-muted ml-2">(berlaku untuk kolom penjualan &amp; stok)</small>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
</div>

<style>
  .arus-stock-sticky {
    position: sticky;
    top: 0;
    z-index: 30;
    background: #f4f6f9;
    padding-bottom: 4px;
    box-shadow: 0 2px 6px rgba(0,0,0,.06);
  }
  .arus-filter-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 10px;
  }
  .arus-filter-toolbar .form-group label {
    display: block;
    margin: 0 0 3px;
    font-size: .75rem;
    font-weight: 700;
    color: #495057;
  }
  .arus-filter-toolbar .form-control { height: 34px; min-width: 120px; }
  .arus-filter-actions { display: flex; flex-wrap: wrap; gap: 6px; padding-bottom: 1px; }
  /* Header kolom mati (DataTables scrollHead), isi yang scroll */
  #arusTable_wrapper .dataTables_scroll {
    border: 1px solid #dee2e6;
  }
  #arusTable_wrapper .dataTables_scrollHead {
    background: #343a40 !important;
  }
  #arusTable_wrapper .dataTables_scrollHead thead th,
  #arusTable_wrapper .dataTables_scrollBody thead th {
    background: #343a40 !important;
    color: #fff !important;
    border-color: #454d55 !important;
    vertical-align: middle;
  }
  #arusTable_wrapper .dataTables_scrollBody {
    background: #fff;
  }
  #arusTable_wrapper .dataTables_filter {
    float: right;
    margin-bottom: 8px;
  }
  #arusTable_wrapper .dataTables_info,
  #arusTable_wrapper .dataTables_paginate {
    margin-top: 8px;
  }
  .arus-val-badge,
  .arus-val-cell {
    display: inline-block;
    min-width: 2.5rem;
    padding: 0.15rem 0.45rem;
    border-radius: 0.25rem;
    font-weight: 600;
    text-align: center;
    line-height: 1.2;
  }
  .arus-val-zero {
    background-color: #dc3545;
    color: #fff;
  }
  .arus-val-low {
    background-color: #ffc107;
    color: #212529;
  }
  .arus-val-high {
    background-color: #28a745;
    color: #fff;
  }
  #arusTable td.text-center .arus-val-cell {
    min-width: 2rem;
  }
  @media (max-width: 767.98px) {
    .arus-filter-toolbar .form-control { min-width: 100%; }
    .arus-filter-toolbar .form-group { flex: 1 1 calc(50% - 10px); }
  }
</style>

<script>
  function arusValClass(v) {
    var n = parseFloat(v);
    if (isNaN(n) || n <= 0) return 'arus-val-zero';
    if (n <= 5) return 'arus-val-low';
    return 'arus-val-high';
  }

  function arusValRender(data, type) {
    if (type === 'display' || type === 'filter') {
      var n = parseFloat(data);
      if (isNaN(n)) return data;
      var txt = Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
      return '<span class="arus-val-cell ' + arusValClass(n) + '">' + txt + '</span>';
    }
    return data;
  }

  function getParams() {
    return {
      from: document.getElementById('from').value,
      to: document.getElementById('to').value,
      fast: document.getElementById('fast').value,
      slow: document.getElementById('slow').value,
      cover: document.getElementById('cover').value,
    };
  }

  $(document).ready(function () {
    var arusCabangMode = <?= $arusCabangToko ? 'true' : 'false'; ?>;
    var arusDetailCol = arusCabangMode ? 12 : 21;
    var arusColorCols = arusCabangMode
      ? [4, 5, 6, 7]
      : [4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 17];

    var arusColumnDefs = [
      { targets: 0, orderable: false, searchable: false },
      { targets: arusDetailCol, orderable: false, searchable: false, className: 'text-center' }
    ];
    arusColorCols.forEach(function (col) {
      arusColumnDefs.push({
        targets: col,
        className: 'text-center',
        render: arusValRender
      });
    });

    function calcArusScrollY() {
      var stickyH = $('.arus-stock-sticky').outerHeight() || 180;
      var h = window.innerHeight - stickyH - 160;
      return (h < 240 ? 240 : h) + 'px';
    }

    var table = $('#arusTable').DataTable({
      processing: true,
      serverSide: true,
      autoWidth: false,
      // Header kolom mati; hanya body yang scroll naik-turun
      scrollY: calcArusScrollY(),
      scrollX: true,
      scrollCollapse: true,
      order: [[3, 'desc']],
      pageLength: 25,
      lengthChange: false, // tanpa filter "Tampil X baris"
      language: {
        search: 'Cari:',
        info: 'Menampilkan _START_–_END_ dari _TOTAL_',
        infoEmpty: 'Tidak ada data',
        zeroRecords: 'Tidak ditemukan',
        paginate: { previous: '‹', next: '›' }
      },
      ajax: {
        url: 'barang-data-arus-stock.php',
        data: function (d) {
          return $.extend({}, d, getParams());
        }
      },
      columnDefs: arusColumnDefs,
      dom: '<"row mb-2"<"col-sm-12"f>>t<"row mt-2"<"col-sm-5"i><"col-sm-7"p>>'
    });

    function refreshArusScroll() {
      var api = table;
      var settings = api.settings()[0];
      if (!settings || !settings.oScroll) return;
      var y = calcArusScrollY();
      settings.oScroll.sY = y.replace('px', '');
      $('.dataTables_scrollBody').css('max-height', y).css('height', y);
      api.columns.adjust();
    }

    $(window).on('resize', function () {
      clearTimeout(window._arusResizeTimer);
      window._arusResizeTimer = setTimeout(refreshArusScroll, 150);
    });

    table.on('draw.dt', function () {
      var info = table.page.info();
      table.column(0, { search: 'applied', order: 'applied', page: 'applied' }).nodes().each(function (cell, i) {
        cell.innerHTML = i + 1 + info.start;
      });
      table.columns.adjust();
    });

    $('#btnApply').on('click', function () {
      table.ajax.reload();
    });

    $('#btnExport').on('click', function () {
      var p = getParams();
      // Ambil search yang sedang aktif di DataTables
      var q = table.search() || '';

      var url = "export-arus-stock.php" +
        "?from=" + encodeURIComponent(p.from) +
        "&to=" + encodeURIComponent(p.to) +
        "&fast=" + encodeURIComponent(p.fast) +
        "&slow=" + encodeURIComponent(p.slow) +
        "&cover=" + encodeURIComponent(p.cover) +
        "&search=" + encodeURIComponent(q);

      window.location.href = url;
    });

    $('#arusTable tbody').on('click', '.btn-detail-arus', function () {
      var kode = $(this).data('kode');
      var p = getParams();
      var url = "barang-arus-stock-detail?kode=" + encodeURIComponent(kode) +
        "&from=" + encodeURIComponent(p.from) +
        "&to=" + encodeURIComponent(p.to);
      window.open(url, '_blank');
    });
  });
</script>

<?php include '_footer.php'; ?>

<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
</body>
</html>

