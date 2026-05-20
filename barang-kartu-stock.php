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

$defaultMonth = date('Y-m');
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Kartu Stock</h1>
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
            <h3 class="card-title">Mutasi stok per bulan (cabang: <?= (int) $sessionCabang; ?>)</h3>
          </div>
          <div class="card-body">
            <form class="form-inline mb-3">
              <label class="mr-2">Bulan</label>
              <input type="month" class="form-control mr-3" id="month" value="<?= htmlspecialchars($defaultMonth, ENT_QUOTES, 'UTF-8'); ?>">
              <button type="button" class="btn btn-success" id="btnApply">Terapkan</button>
            </form>

            <div class="table-auto">
              <table id="kartuTable" class="table table-bordered table-striped" style="width: 100%">
                <thead>
                  <tr>
                    <th style="width: 6%;">No</th>
                    <th style="width: 13%;">Kode</th>
                    <th>Nama</th>
                    <th style="width: 12%;">Kode Supplier</th>
                    <th style="width: 10%;">Stok Awal Bulan</th>
                    <th style="width: 9%;">Pembelian</th>
                    <th style="width: 10%;">Retur Pembelian</th>
                    <th style="width: 9%;">TF Masuk</th>
                    <th style="width: 9%;">Penjualan</th>
                    <th style="width: 10%;">Retur Penjualan</th>
                    <th style="width: 9%;">TF Keluar</th>
                    <th style="width: 10%;">Stok Akhir Bulan</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <small class="text-muted">
              Catatan: Stok awal diambil dari hasil stock opname terakhir sebelum awal bulan (jika ada). Retur pembelian hanya dihitung jika data retur pembelian tersedia.
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
    return { month: document.getElementById('month').value };
  }

  $(document).ready(function () {
    var table = $('#kartuTable').DataTable({
      "processing": true,
      "serverSide": true,
      "order": [[1, "asc"]],
      "ajax": {
        "url": "barang-data-kartu-stock.php?cabang=<?= (int) $sessionCabang; ?>",
        "data": function (d) {
          return $.extend({}, d, getParams());
        }
      },
      "columnDefs": [
        { "targets": 0, "orderable": false, "searchable": false }
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
  });
</script>

<?php include '_footer.php'; ?>
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
</body>
</html>

