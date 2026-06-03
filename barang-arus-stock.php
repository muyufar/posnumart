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
?>

<div class="content-wrapper">
  <section class="content-header">
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
        <a href="barang" class="btn btn-primary">Kembali</a>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Analisa fast/slow moving (<?= $arusCabangToko ? 'cabang: ' . htmlspecialchars($arusNamaToko, ENT_QUOTES, 'UTF-8') : 'semua toko'; ?>)</h3>
          </div>
          <div class="card-body">
            <form id="formFilter" class="form-inline mb-3">
              <label class="mr-2">Dari</label>
              <input type="date" class="form-control mr-3" id="from" value="<?= htmlspecialchars($defaultFrom, ENT_QUOTES, 'UTF-8'); ?>">

              <label class="mr-2">Sampai</label>
              <input type="date" class="form-control mr-3" id="to" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>">

              <label class="mr-2">Fast &gt;=</label>
              <input type="number" step="0.01" class="form-control mr-3" id="fast" value="1">

              <label class="mr-2">Slow &lt;=</label>
              <input type="number" step="0.01" class="form-control mr-3" id="slow" value="0.2">

              <label class="mr-2">Target cover (hari)</label>
              <input type="number" step="1" class="form-control mr-3" id="cover" value="14">

              <button type="button" class="btn btn-success" id="btnApply">
                Terapkan
              </button>

              <button type="button" class="btn btn-info ml-2" id="btnExport">
                Export Excel
              </button>
            </form>

            <div class="table-auto">
              <table id="arusTable" class="table table-bordered table-striped" style="width: 100%">
                <thead>
                  <tr>
                    <th style="width: 6%;">No.</th>
                    <th style="width: 13%;">Kode</th>
                    <th>Nama</th>
                    <th style="width: 12%;">Kode Supplier</th>
                    <th style="width: 10%;">Terjual (periode)</th>
                    <th style="width: 10%;">Terjual (total)</th>
                    <?php if (!$arusCabangToko): ?>
                    <th style="width: 10%;">Gudang</th>
                    <th style="width: 10%;">Dukun</th>
                    <th style="width: 10%;">PP Srumbung</th>
                    <th style="width: 10%;">Pakis</th>
                    <th style="width: 10%;">Tegalrejo</th>
                    <?php endif; ?>
                    <th style="width: 10%;">Avg / hari</th>
                    <th style="width: 10%;"><?= $arusCabangToko ? 'Stok cabang (PCS)' : 'Total Stock'; ?></th>
                    <th style="width: 10%;">Cover (hari)</th>
                    <th style="width: 10%;">Kategori</th>
                    <th>Rekomendasi</th>
                    <th style="width: 8%;">Detail</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <small class="text-muted">
              Catatan: “Terjual” dihitung dari tabel transaksi `penjualan` berdasarkan tanggal yang kamu pilih, dalam PCS (qty × konversi ke satuan terkecil).
              Total stok dan cover memakai satuan terkecil (PCS), dari nilai stok disimpan menurut satuan barang.
              <?php if ($arusCabangToko): ?>
              Tampilan cabang: hanya penjualan &amp; stok toko yang Anda login; kolom per toko lain disembunyikan.
              <?php else: ?>
              Kolom <strong>Terjual (periode)</strong> = total penjualan semua cabang; kolom <strong>Gudang</strong> = cabang 0 (NU Grosir) saja; kolom toko lain = penjualan cabang tersebut.
              Total periode ≈ Gudang + Dukun + PP Srumbung + Pakis + Tegalrejo.
              <?php endif; ?>
            </small>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
</div>

<script>
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
    var arusDetailCol = arusCabangMode ? 11 : 16;
    var table = $('#arusTable').DataTable({
      "processing": true,
      "serverSide": true,
      "order": [[3, "desc"]],
      "ajax": {
        "url": "barang-data-arus-stock.php",
        "data": function (d) {
          return $.extend({}, d, getParams());
        }
      },
      "columnDefs": [
        { "targets": 0, "orderable": false, "searchable": false },
        { "targets": arusDetailCol, "orderable": false, "searchable": false, "className": "text-center" }
      ]
    });

    table.on('draw.dt', function () {
      var info = table.page.info();
      table.column(0, { search: 'applied', order: 'applied', page: 'applied' }).nodes().each(function (cell, i) {
        cell.innerHTML = i + 1 + info.start;
      });
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

