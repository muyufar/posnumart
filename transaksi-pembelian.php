<?php 
  error_reporting(0);
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
<?php 
if ( $dataTokoLogin['toko_status'] < 1 ) {
  echo "
      <script>
        alert('Status Toko Tidak Aktif Jadi Anda Tidak Bisa melakukan Transaksi !!');
        document.location.href = 'bo';
      </script>
    ";
}


// Insert Ke keranjang Scan Barcode
if( isset($_POST["inputbarcode"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if( tambahKeranjangPembelianBarcode($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = '';
      </script>
    ";
  } 
  
}

?>


<?php  
// Edit QTY
if( isset($_POST["update"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if ( updateQTYpembelian($_POST) === 0 ) {
    echo "
      <script>
        alert('Anda Belum Input Nominal QTY !!!!!');
      </script>
    ";
  } elseif( updateQTYpembelian($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = '';
      </script>
    ";
  } 
  else {
    echo "
      
    ";
  }
  
}
?>

<?php  
// Edit Harga Beli
if( isset($_POST["update_harga"]) ){
  if ( updateHargaBeliPembelian($_POST) > 0 ) {
    $r = isset($_GET['r']) ? $_GET['r'] : (isset($_POST['r']) ? $_POST['r'] : '');
    $url = 'transaksi-pembelian';
    if ( $r !== '' ) $url .= '?r=' . $r;
    echo "<script> document.location.href = '" . $url . "'; </script>";
    exit;
  } else {
    echo "<script> alert('Gagal mengupdate harga beli'); </script>";
  }
}
?>

<?php 
error_reporting(0);
// Insert Ke keranjang
$inv = $_POST["pembelian_invoice_parent2"];
if( isset($_POST["updateStock"]) ){
  // var_dump($_POST);
  $sql = mysqli_query($conn, "SELECT * FROM invoice_pembelian WHERE pembelian_invoice_parent='$inv' && invoice_pembelian_cabang = '$sessionCabang' ") or die (mysqli_error($conn));
  $hasilquery = mysqli_num_rows($sql);
  if( $hasilquery == 0){
      // cek apakah data berhasil di tambahkan atau tidak
      if( updateStockPembelian($_POST) > 0 ) {
        echo "
          <script>
            document.location.href = 'invoice-pembelian?no=".$inv."';
          </script>
        ";
      } else {
        echo "
          <script>
            alert('Transaksi Gagal');
          </script>
        ";
      }
  } else {
    echo "
        <script>
          document.location.href = 'invoice-pembelian?no=".$inv."';
        </script>
      ";
  }
}
?>

<!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Transaksi Pembelian Produk</h1>
            <div class="btn-cash-piutang">
              <?php  
                // Ambil data dari URL Untuk memberikan kondisi transaksi Cash atau Hutang
                if (empty(abs((int)base64_decode($_GET['r'])))) {
                    $r = 0;
                } else {
                    $r = abs((int)base64_decode($_GET['r']));
                }
              ?>

              <?php if ( $r == 1 ) : ?>
              <a href="transaksi-pembelian" class="btn btn-default">Cash</a>
              <a href="transaksi-pembelian?r=MQ==" class="btn btn-primary">Hutang</a>
              <?php else : ?>
              <a href="transaksi-pembelian" class="btn btn-primary">Cash</a>
              <a href="transaksi-pembelian?r=MQ==" class="btn btn-default">Hutang</a>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Barang</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <?php  
      $keranjang = query("SELECT * FROM keranjang_pembelian ORDER BY keranjang_id ASC");

      $pembelian = mysqli_query($conn,"select * from invoice_pembelian");
      $jmlPembelian = mysqli_num_rows($pembelian);
      $jmlPembelian1 = $jmlPembelian + 1;
    ?>
    <?php  
        $today = date("Ymd");
        $di = $today.$jmlPembelian1;
    ?>
    <section class="content">

        <div class="col-lg-12">

        	

            <!-- /.card-header -->
              <span id="transaksi-pembelian-keranjang"></span>
            <!-- /.card-body -->


        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
    </section>
    <!-- /.content -->
  </div>
</div>


    <?php  
      $data = query("SELECT * FROM barang WHERE barang_cabang = $sessionCabang ORDER BY barang_id DESC");
    ?>
    <div class="modal fade" id="modal-id" data-backdrop="static">
        <div class="modal-dialog modal-lg-pop-up">
          <div class="modal-content">
            <div class="modal-body">
                  <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Data barang Keseluruhan</h3>
                  <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                  <div class="table-auto">
                    <table id="example1" class="table table-bordered table-striped" style="width: 100%;">
                      <thead>
                      <tr>
                        <th style="width: 5%;">No.</th>
                        <th>Kode Barang</th>
                        <th>Nama</th>
                        <th>Satuan</th>
                        <th>Stock</th>
                        <th style="text-align: center;">Aksi</th>
                      </tr>
                      </thead>
                      <tbody>

                      </tbody>
                    </table>
                  </div>
                </div>
                <!-- /.card-body -->
              </div>    
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
    </div>

  <!-- Modal Tambah Invoice Pembelian -->
  <div id="modal-tambah-invoice" class="modal fade" role="dialog">
    <div class="modal-dialog">
      <div class="modal-content">
        <form role="form" id="form-tambah-invoice" method="post" action="transaksi-pembelian-input-no-invoice.php">
        <div class="modal-header">
          <h4 class="modal-title">Input No. Invoice Pembelian</h4>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
          <div class="modal-body">
            <div class="form-group">
              <label>No. Invoice</label>
              <input type="text" class="form-control" id="invoice_pembelian_number_input" name="invoice_pembelian_number_input" required="">
            </div>  

            <input type="hidden" name="invoice_pembelian_number_parent" value="<?= $di; ?>">    
            <input type="hidden" name="invoice_pembelian_number_user" value="<?= $_SESSION['user_id']; ?>">    
            <input type="hidden" name="invoice_pembelian_cabang" value="<?= $sessionCabang; ?>">
          </div>
          
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success" >Simpan</button>
          </div>
        </form>   
      </div>
    </div>
  </div>


  <!-- Modal Update Harga Pembelian -->
  <div class="modal fade" id="modal-id-3">
    <div class="modal-dialog">
      <div class="modal-content">

        <form role="form" id="form-edit-invoice" method="post" action="transaksi-pembelian-edit-invoice-proses.php">
          <div class="modal-header">
            <h4 class="modal-title">Edit No. Invoice</h4>
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
          </div>
          <div class="modal-body" id="data-edit-pembelian">
            
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-dismiss="modal">Tutup</button>
            <button type="submit" class="btn btn-primary" >Edit Data</button>
          </div>
        </form>

      </div>
    </div>
  </div>


  <!-- Modal Update Harga Pembelian -->
  <div class="modal fade" id="modal-id-2">
    <div class="modal-dialog">
      <div class="modal-content">

        <form role="form" id="form-edit-harga-beli" method="post" action="transaksi-pembelian-harga-beli-proses.php">
          <div class="modal-header">
            <h4 class="modal-title">Harga Pembelian</h4>
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
          </div>
          <div class="modal-body" id="data-keranjang-pembelian">
            
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-dismiss="modal">Tutup</button>
            <button type="submit" class="btn btn-primary" >Edit Data</button>
          </div>
        </form>

      </div>
    </div>
  </div>
  
  <script>
    $(document).ready(function(){
        var table = $('#example1').DataTable( { 
             "processing": true,
             "serverSide": true,
             "ajax": "transaksi-pembelian-search-data.php?cabang=<?= $sessionCabang; ?>",
             "columnDefs": 
             [
              {
                "targets": 3,
                  "render": $.fn.dataTable.render.number( '.', '', '', 'Rp. ' )
                 
              },
              {
                "targets": -1,
                  "data": null,
                  "defaultContent": 
                  `<center>

                      <button class='btn btn-primary tblInsert' title="Tambah Keranjang">
                         <i class="fa fa-shopping-cart"></i> Pilih
                      </button>

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

        $('#example1 tbody').on( 'click', '.tblInsert', function () {
            var data = table.row( $(this).parents('tr')).data();
            var data0 = data[0];
            var data0 = btoa(data0);
            window.location.href = "transaksi-pembelian-add?id="+ data0 + "&r=<?= $r; ?>";
        });

    });
  </script>

<?php include '_footer.php'; ?>

<script>
  $(document).ready(function(){
      $('#transaksi-pembelian-keranjang').load('transaksi-pembelian-keranjang.php?r=<?= $r; ?>');

      // Tambah Invoice
      $('#form-tambah-invoice').submit(function(e){
        e.preventDefault();

        var dataFormUser = $('#form-tambah-invoice').serialize();
        $.ajax({
          url: "transaksi-pembelian-input-no-invoice.php",
          type: "post",
          data: dataFormUser,
          success: function(result) {
            var hasil = JSON.parse(result);
            if (hasil.hasil !== "sukses") {
            } else {
              $('#modal-tambah-invoice').modal('hide');
              $('#transaksi-pembelian-keranjang').load('transaksi-pembelian-keranjang.php?r=<?= $r; ?>');
              Swal.fire(
                'Sukses',
                'Data Berhasil Disimpan',
                'success'
              );
            }
          }
        });
      });


      // Edit Invoice 
      $(document).on('click','#invoice_edit',function(e){
          e.preventDefault();
          $("#modal-id-3").modal('show');
          $.post('transaksi-pembelian-edit-invoice.php',
            {id:$(this).attr('data-id')},
            function(html){
              $("#data-edit-pembelian").html(html);
            }   
          );
        });

      $("#form-edit-invoice").submit(function(e) {
        e.preventDefault();
        
        var dataform = $("#form-edit-invoice").serialize();
        $.ajax({
          url: "transaksi-pembelian-edit-invoice-proses.php",
          data: dataform,
          type: "post",
          success: function(result) {
            var hasil = JSON.parse(result);
            if (hasil.hasil !== "sukses") {
            } else {
              $('#modal-id-3').modal('hide');
              Swal.fire(
                'Sukses !!',
                'Data Berhasil diupdate',
                'success'
              );
              $('#transaksi-pembelian-keranjang').load('transaksi-pembelian-keranjang.php?r=<?= $r; ?>');
            }
          }
        });
      });
      // End Edit Invoice


      // Edit Status 
      $(document).on('click','#keranjang-pembelian',function(e){
          e.preventDefault();
          $("#modal-id-2").modal('show');
          $.post('transaksi-pembelian-harga-beli.php',
            {id:$(this).attr('data-id')},
            function(html){
              $("#data-keranjang-pembelian").html(html);
            }   
          );
        });

      $("#form-edit-harga-beli").submit(function(e) {
        e.preventDefault();
        
        var dataform = $("#form-edit-harga-beli").serialize();
        $.ajax({
          url: "transaksi-pembelian-harga-beli-proses.php",
          data: dataform,
          type: "post",
          success: function(result) {
            var hasil = JSON.parse(result);
            if (hasil.hasil !== "sukses") {
            } else {
              $('#modal-id-2').modal('hide');
              Swal.fire(
                'Sukses !!',
                'Data Berhasil diupdate',
                'success'
              );
              $('#transaksi-pembelian-keranjang').load('transaksi-pembelian-keranjang.php?r=<?= $r; ?>');
            }
          }
        });
      });
      // End Edit Status
  });
</script>

<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<script>
  $(function () {
    $("#example1").DataTable();
  });
</script>
<script>
    function hanyaAngka(evt) {
      var charCode = (evt.which) ? evt.which : event.keyCode
       if (charCode > 31 && (charCode < 48 || charCode > 57))
 
        return false;
      return true;
    }
    function hanyaAngka1(evt) {
      var charCode = (evt.which) ? evt.which : event.keyCode
       if (charCode > 31 && (charCode < 48 || charCode > 57))
 
        return false;
      return true;
    }
</script>
 <script>
      function hitung2() {
      var a = $(".a2").val();
      var b = $(".b2").val();
      c = a - b;
      $(".c2").val(c);
      }
      function isNumberKey(evt){
       var charCode = (evt.which) ? evt.which : event.keyCode;
       if (charCode != 46 && charCode > 31 && (charCode < 48 || charCode > 57))
       return false;
       return true;
      }
</script>

</body>
</html>