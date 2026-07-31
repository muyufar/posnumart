<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
require_once 'aksi/pengadaan-po-alokasi-lib.php';

if (!pengadaan_gudang_can_access((int) $sessionCabang, (string) $levelLogin)) {
	echo "<script>document.location.href='bo';</script>";
	exit;
}

pengadaan_po_ensure_tables($conn);
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1><i class="fa fa-history"></i> Riwayat PO &amp; Transfer Stock</h1>
          <p class="text-muted mb-0">
            PO yang sudah transaksi pembelian, beserta tujuan transfer stock ke toko.
          </p>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="pengadaan-gudang">Pengadaan Gudang</a></li>
            <li class="breadcrumb-item active">Riwayat PO</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title"><i class="fa fa-list"></i> Daftar PO Selesai / Sudah Invoice</h3>
          <div class="card-tools">
            <a href="pengadaan-gudang" class="btn btn-sm btn-outline-secondary"><i class="fa fa-warehouse"></i> Pusat Pengadaan</a>
          </div>
        </div>
        <div class="card-body">
          <form class="form-row mb-3" id="formFilterRiwayat">
            <div class="form-group col-md-3 mb-2">
              <label>Transfer stock</label>
              <select class="form-control" id="filterTransfer">
                <option value="semua">Semua</option>
                <option value="sudah">Sudah ditransfer</option>
                <option value="belum">Belum ditransfer</option>
              </select>
            </div>
            <div class="form-group col-md-2 mb-2">
              <label>&nbsp;</label>
              <button type="button" class="btn btn-primary btn-block" id="btnFilterRiwayat"><i class="fa fa-search"></i> Terapkan</button>
            </div>
          </form>

          <div class="table-responsive">
            <table id="tblPoRiwayat" class="table table-bordered table-striped table-sm w-100">
              <thead class="thead-dark">
                <tr>
                  <th>ID</th>
                  <th>No PO</th>
                  <th>Supplier</th>
                  <th>Item</th>
                  <th>Qty Diterima</th>
                  <th>Invoice</th>
                  <th>Status PO</th>
                  <th>Tujuan Transfer</th>
                  <th>Detail Transfer</th>
                  <th>Alokasi</th>
                  <th>Dibuat</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
          <small class="text-muted d-block mt-2">
            Badge tujuan = ringkasan qty per toko. Detail Transfer menampilkan nomor ref transfer (klik untuk zoom).
            Tombol truk = buka ulang halaman alokasi jika masih ada sisa stok gudang.
          </small>
        </div>
      </div>
    </div>
  </section>
</div>
</div>

<!-- Modal detail transfer -->
<div class="modal fade" id="modalTransferDetail" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Transfer Stock — <span id="modalPoNumber"></span></h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" id="modalTransferBody">
        <p class="text-muted mb-0">Memuat…</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<?php include '_footer.php'; ?>

<script>
$(function () {
  var table = $('#tblPoRiwayat').DataTable({
    processing: true,
    serverSide: true,
    order: [[0, 'desc']],
    pageLength: 25,
    ajax: {
      url: 'api/pengadaan-po-riwayat-data.php',
      data: function (d) {
        d.filter_transfer = $('#filterTransfer').val();
      }
    },
    columnDefs: [
      { targets: 0, visible: false },
      { targets: [7, 8, 11], orderable: false },
      { targets: 8, className: 'small' }
    ]
  });

  $('#btnFilterRiwayat, #filterTransfer').on('click change', function () {
    table.ajax.reload();
  });

  $('#tblPoRiwayat').on('click', '.btn-riwayat-transfer', function () {
    var po = $(this).data('po');
    var id = $(this).data('id');
    $('#modalPoNumber').text(po || '');
    var $body = $('#modalTransferBody').html('<p class="text-muted">Memuat…</p>');
    $('#modalTransferDetail').modal('show');
    // Ambil ulang dari cell detail yang sudah di-render (kolom index 8 di data) — reload row via API sederhana: buka alokasi/detail
    // Tampilkan isi dari baris tabel (kolom Detail Transfer)
    var $tr = $(this).closest('tr');
    var rowData = table.row($tr).data();
    if (rowData && rowData[8]) {
      $body.html(rowData[8]);
      $body.append(
        '<hr><a class="btn btn-sm btn-success" href="pengadaan-po-alokasi?po=' + id + '"><i class="fa fa-truck"></i> Alokasi ulang</a> '
        + '<a class="btn btn-sm btn-outline-info" href="pengadaan-po-detail?id=' + id + '"><i class="fa fa-eye"></i> Detail PO</a>'
      );
    } else {
      $body.html('<p class="text-muted">Tidak ada data transfer untuk PO ini.</p>');
    }
  });
});
</script>
</body>
</html>
