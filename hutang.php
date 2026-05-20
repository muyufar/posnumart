<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
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
              <h3 class="card-title">Data Hutang</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
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
        var table = $('#example1').DataTable( { 
             "processing": true,
             "serverSide": true,
             "ajax": "hutang-data.php?cabang=<?= $sessionCabang; ?>",
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
<script>
  $(function () {
    $("#example1").DataTable();
  });
</script>
</body>
</html>