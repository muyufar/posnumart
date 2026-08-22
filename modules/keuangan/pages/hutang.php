<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 

  $today = date('Y-m-d');
  $defaultFrom = date('Y-01-01');
?>

<?php  
  if ( $levelLogin === "kurir") {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }  
?>

	<!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Data Hutang <b>Belum Lunas</b></h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Hutang</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>


    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">

          <div class="card">
            <div class="card-header">
              <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title mb-0">Data Hutang</h3>
                <div class="mt-2 mt-md-0">
                  <span class="badge badge-danger p-2" style="font-size: 0.95rem;">
                    Total Hutang: <span id="totalHutang">Rp. 0</span>
                  </span>
                  <button type="button" class="btn btn-success btn-sm ml-2" id="btnExportExcel">
                    <i class="fa fa-file-excel"></i> Export Excel
                  </button>
                </div>
              </div>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <form id="formFilter" class="form-inline mb-3 flex-wrap">
                <label class="mr-2 mb-2">Periode</label>
                <select class="form-control mr-2 mb-2" id="tipe">
                  <option value="transaksi">Tanggal Transaksi</option>
                  <option value="jatuh_tempo">Jatuh Tempo</option>
                </select>
                <label class="mr-2 mb-2">Dari</label>
                <input type="date" class="form-control mr-3 mb-2" id="from" value="<?= htmlspecialchars($defaultFrom, ENT_QUOTES, 'UTF-8'); ?>">
                <label class="mr-2 mb-2">Sampai</label>
                <input type="date" class="form-control mr-3 mb-2" id="to" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="button" class="btn btn-primary mb-2" id="btnApply">
                  <i class="fa fa-filter"></i> Terapkan
                </button>
              </form>
              <div class="table-auto">
              <table id="example1" class="table table-bordered table-striped">
                <thead>
                <tr>
                  <th style="width: 6%;">No.</th>
                  <th style="width: 13%;">Invoice</th>
                  <th>Tanggal Transaksi</th>
                  <th>Supplier</th>
                  <th>Jatuh Tempo</th>
                  <th>Sub Total</th>
                  <!-- <th>Kasir</th> -->
                  <th style="text-align: center; width: 16%">Aksi</th>
                </tr>
                </thead>
                <tbody>
                
                </tbody>
              </table>
            </div>
            </div>
            <!-- /.card-body -->
          </div>
          <!-- /.card -->
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
    </section>
    <!-- /.content -->
  </div>
</div>

<script>
    $(document).ready(function(){
        function formatRupiah(value) {
            var number = parseFloat(value || 0);
            if (isNaN(number)) number = 0;
            return 'Rp. ' + number.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }

        function getFilterParams() {
            return {
                from: $('#from').val(),
                to: $('#to').val(),
                tipe: $('#tipe').val()
            };
        }

        var table = $('#example1').DataTable( { 
             "processing": true,
             "serverSide": true,
             "ajax": {
                "url": "hutang-data.php?cabang=<?= $sessionCabang; ?>",
                "data": function(d) {
                    var p = getFilterParams();
                    d.from = p.from;
                    d.to = p.to;
                    d.tipe = p.tipe;
                },
                "dataSrc": function(json) {
                    if (json && typeof json.totalHutang !== 'undefined') {
                        $('#totalHutang').text(formatRupiah(json.totalHutang));
                    } else {
                        $('#totalHutang').text('Rp. 0');
                    }
                    return json.data;
                }
             },
             "columnDefs": 
             [
              {
                "targets": 5,
                  "render": $.fn.dataTable.render.number( '.', '', '', 'Rp. ' )
                 
              },
              {
                "targets": -1,
                  "data": null,
                  "defaultContent": 
                  `<center class="orderan-online-button">
                      <button class='btn btn-info tblZoom' title='Lihat Data'>
                          <i class='fa fa-eye'></i>
                      </button>&nbsp; 

                      <button class='btn btn-success tblCicilan' title='Cicilan'>
                          <i class='fa fa-money'></i>
                      </button>&nbsp;

                      <?php if ( $levelLogin !== "kasir" ) { ?>
                        <button class='btn btn-primary tblEdit' title="Retur">
                            <i class='fa fa-edit'></i>
                        </button>&nbsp;
                      <?php } ?> 

                      <button class='btn btn-warning tblPrint' title="Cetak Nota">
                          <i class='fa fa-print'></i>
                      </button>&nbsp;

                      <?php if ( $levelLogin === "super admin" ) { ?>
                        <button class='btn btn-danger tblDelete' title="Delete Invoice">
                            <i class='fa fa-trash-o'></i>
                        </button> 
                      <?php } ?> 
                  </center>` 
              }
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

        $('#btnExportExcel').on('click', function () {
            var p = getFilterParams();
            var url = 'export-hutang-excel.php'
                + '?from=' + encodeURIComponent(p.from)
                + '&to=' + encodeURIComponent(p.to)
                + '&tipe=' + encodeURIComponent(p.tipe);
            window.location.href = url;
        });

        $('#example1 tbody').on( 'click', '.tblZoom', function () {
            var data = table.row( $(this).parents('tr')).data();
            var data0 = data[0];
            var data0 = btoa(data0);
            window.open('pembelian-zoom?no='+ data0, '_blank');
        });

        $('#example1 tbody').on( 'click', '.tblCicilan', function () {
            var data = table.row( $(this).parents('tr')).data();
            var data0 = data[0];
            var data0 = btoa(data0);
            window.location.href = "hutang-cicilan?no="+ data0;
        });

        $('#example1 tbody').on( 'click', '.tblEdit', function () {
            var data  = table.row( $(this).parents('tr')).data();
            var data0 = data[0];
            var data0 = btoa(data0);
            var link  = confirm('Fitur ini digunkan untuk RETUR TRANSAKSI jika barang pembelian TIDAK JADI atau ingin Mengurangi QTY.. Apakah Anda Yakin !!!');
            if ( link === true ) {
                window.location.href = "hutang-edit?no="+ data0;
            }
        });


        $('#example1 tbody').on( 'click', '.tblPrint', function () {
            var data = table.row( $(this).parents('tr')).data();
            var data0 = data[0];
            window.open('nota-cetak-hutang?no='+ data0, '_blank');
        });


        $('#example1 tbody').on( 'click', '.tblDelete', function () {
            var data  = table.row( $(this).parents('tr')).data();
            var data1a= data[1];
            var data1 = data[0];
            var link  = confirm('Apakah Anda Yakin Hapus Seluruh Data No. Invoice '+ data1a + ' ?');
            if ( link === true ) {
                window.location.href = "pembelian-delete-invoice?id="+ data1 + "&page=hutang";
            }
        });

    });
  </script>

<?php include '_footer.php'; ?>

<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
</body>
</html>