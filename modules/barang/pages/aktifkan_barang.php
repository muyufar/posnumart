<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
  error_reporting(0);
?>
<?php  
  if ( $levelLogin === "kasir" && $levelLogin === "kurir" ) {
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
            <h1>Data Barang</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Barang</li>
            </ol>
          </div>
          <div class="tambah-data">
            <?php if ($sessionCabang == 0): ?>
          	<a href="barang-add" class="btn btn-primary">Tambah Data</a>
          	<a href="export/download_template_barang.php" class="btn btn-danger">Download Template</a>
            <form id="importForm" action="import/import-barang.php" method="post" enctype="multipart/form-data" style="display:inline;">
                <input type="file" name="excel_file" id="excelFileInput" accept=".xls, .xlsx" style="display:none;" required>
                <button type="button" id="importButton" class="btn btn-warning">Import Data</button>
            </form>
             <form action="export/export_barang_template.php" method="get" style="display:inline;">
                <input type="hidden" name="id" value="<?= $sessionCabang; ?>">
                <button type="submit" class="btn btn-success">Ekspor Data</button>
            </form>
            <?php else: ?>
            <form action="export/export_barang_template.php" method="get" style="display:inline;">
                <input type="hidden" name="id" value="<?= $sessionCabang; ?>">
                <button type="submit" class="btn btn-success">Ekspor Data</button>
            </form>
            <?php endif; ?>
            <div id="toast" class="toast"></div>
            
          </div>
          
        </div>
      </div><!-- /.container-fluid -->
    </section>


    <?php  
     $qu = "SELECT * FROM barang WHERE barang_cabang = $sessionCabang ORDER BY barang_id DESC";
    $data = query($qu);
    	// $data = query("SELECT * FROM barang ORDER BY barang_id DESC");
    ?>
    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">

          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Data barang Keseluruhan</h3>
            </div>
            
            
            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
                <table id="example1" class="table table-bordered table-striped">
                  <thead>
                  <tr>
                    <th style="width: 6%;">No.</th>
                    <th style="width: 13%;">Kode Barang</th>
                    <th>Nama</th>
                    <th>Kategori</th>
                    <th style="width: 10%;">Harga Beli</th> 
                    <th>Harga Umum</th>
                    <th>Stock</th>
                    <th style="text-align: center; width: 12%">Aksi</th>
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
             "ajax": "aktifkan-barang-data.php?cabang=<?= $sessionCabang; ?>",
            //  "columns": [
            //     { "data": 0 }, // barang_id
            //     { "data": 1 }, // barang_kode
            //     { "data": 2 }, // barang_nama
            //     { "data": 3 }, // kategori_nama
            //     { "data": 4 }, // barang_harga_beli
            //     { "data": 5 }, // barang_harga
            //     { "data": 6 }  // barang_stock
            //  ],
             "columnDefs": 
             [
              {
                "targets": 4,
                  "render": $.fn.dataTable.render.number( '.', '.', '1', 'Rp. ' )
                 
              },
                {
                    "targets": 5, // Kolom barang_harga
                    "render": $.fn.dataTable.render.number('.', '.', '1', 'Rp. ')
                },
              {
                "targets": -1,
                  "data": null,
                  "defaultContent": 
                  `<center class="orderan-online-button">
                      <button class='btn btn-success tblZoom' title='Lihat Data'>
                          <i class='fa fa-eye'></i>
                      </button>&nbsp;

                      <button class='btn btn-primary tblEdit' title="Edit Data">
                          <i class='fa fa-edit'></i>
                      </button>&nbsp;

                      <button class='btn btn-danger tblAktifkan' title="Aktifkan">
                          <i class="fa fa-chevron-up"></i>
                      </button>&nbsp;


                  
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
            window.open('barang-zoom?id='+ data0, '_blank');
        });

        $('#example1 tbody').on( 'click', '.tblEdit', function () {
            var data  = table.row( $(this).parents('tr')).data();
            var data0 = data[0];
            var data0 = btoa(data0);
            window.open('barang-edit?id='+ data0, '_blank');
        });

        $('#example1 tbody').on( 'click', '.tblAktifkan', function () {
            var data  = table.row( $(this).parents('tr')).data();
            var data0 = data[0];
            var data0 = btoa(data0);
            var data1 = data[2];
            var link  = confirm('Apakah Anda Yakin Aktifkan Produk '+ data1 + ' ?');
            if ( link === true ) {
                window.location.href = "barang-aktif?id="+ data0;
            }
        });

        $('#example1 tbody').on( 'click', '.tblBarcode', function () {
            var data = table.row( $(this).parents('tr')).data();
            var data0 = data[0];
            var data0 = btoa(data0);
            window.open('barang-generate-barcode?id='+ data0, '_blank');
            // window.location.href = "barang-generate-barcode?id="+ data0;
        });

        $('#example1 tbody').on( 'click', '.tblDelete', function () {
            var data  = table.row( $(this).parents('tr')).data();
            var data0 = data[0];
            var data0 = btoa(data0);
            var data1 = data[2];
            var link  = confirm('Apakah Anda Yakin Hapus Produk '+ data1 + ' ?');
            if ( link === true ) {
                window.location.href = "barang-delete?id="+ data0;
            }
        });

    });
  </script>
  
<script>
// Toast function
function showToast(message, type) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.style.backgroundColor = type === 'success' ? '#4CAF50' : '#f44336'; // Green for success, red for error
    toast.className = "toast show";
    setTimeout(() => { 
        toast.className = toast.className.replace("show", ""); 
        if (type === 'success') {
            location.reload(); // Refresh halaman jika sukses
        }
    }, 3000);
}

// Import button click event
document.getElementById('importButton').addEventListener('click', () => {
    const fileInput = document.getElementById('excelFileInput');
    fileInput.click(); // Trigger file input dialog
});

// Handle file selection
document.getElementById('excelFileInput').addEventListener('change', (event) => {
    const formData = new FormData(document.getElementById('importForm'));

    // Nonaktifkan tombol untuk mencegah pemrosesan ulang
    const importButton = document.getElementById('importButton');
    importButton.disabled = true;

    fetch('import/import-barang.php', {
        method: 'POST',
        body: formData,
    })
    .then(response => response.json()) // Pastikan response adalah JSON
    .then(data => {
        console.log(data);  // Debugging response dari server
        if (data.success) {
            showToast(data.message, 'success'); // Tampilkan toast sukses
        } else {
            showToast(data.message, 'error'); // Tampilkan toast error
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Terjadi kesalahan saat mengimpor data.', 'error');
    })
    .finally(() => {
        // Aktifkan kembali tombol setelah request selesai
        importButton.disabled = false;
    });
});
</script>

<style>
    .toast {
        visibility: hidden;
        min-width: 250px;
        margin-left: -125px;
        background-color: #333;
        color: #fff;
        text-align: center;
        border-radius: 5px;
        padding: 10px;
        position: fixed;
        z-index: 1;
        left: 50%;
        bottom: 30px;
        font-size: 17px;
    }
    .toast.show {
        visibility: visible;
        animation: fadein 0.5s, fadeout 0.5s 2.5s;
    }
    @keyframes fadein {
        from {bottom: 0; opacity: 0;}
        to {bottom: 30px; opacity: 1;}
    }
    @keyframes fadeout {
        from {bottom: 30px; opacity: 1;}
        to {bottom: 0; opacity: 0;}
    }
</style>

<?php include '_footer.php'; ?>

<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<script>
  $(function () {
    $("#example1").DataTable();
  });

  $(".delete-data").click(function(){
    alert("Data tidak bisa dihapus karena masih ada di data Invoice");
  });
</script>
</body>
</html>