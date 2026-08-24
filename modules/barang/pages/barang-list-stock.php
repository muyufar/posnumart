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
            <h1>Data Stock Barang</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Barang</li>
            </ol>
          </div>
           <a href="barang" class="btn btn-primary">Kembali</a>
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
              <h3 class="card-title">Data stock barang Keseluruhan</h3>
              <div class="card-tools">
                <a href="export-stock-barang" class="btn btn-success btn-sm">
                  <i class="fas fa-file-excel"></i> Export Excel (.xls)
                </a>
              </div>
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
                    <th>Total Penjualan</th>
                    <th>Kode Suplier</th>
                    <th>Stock RETURAN</th>
                    <th>Stock Gudang</th>
                    <th>Stock Dukun</th>
                    <th>Stock PP Srumbung</th>
                    <th>Stock Pakis</th>
                    <th>Stock Tegalrejo</th>
                    <th>Keterangan</th>
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

<!-- Modal Duplikasi Barang -->
<div class="modal fade" id="modalDuplikasi" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info">
        <h5 class="modal-title">Duplikasi Barang ke Cabang</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p><strong>Kode Barang:</strong> <span id="duplikasi-kode"></span></p>
        <p><strong>Nama Barang:</strong> <span id="duplikasi-nama"></span></p>
        <hr>
        <p class="text-muted">Pilih cabang yang ingin ditambahkan:</p>
        <div id="cabang-checkbox-container">
          <!-- Checkbox akan diisi via JavaScript -->
        </div>
        <div id="duplikasi-progress" style="display:none;" class="mt-3">
          <div class="progress">
            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
          </div>
          <p class="text-center mt-2">Memproses...</p>
        </div>
        <div id="duplikasi-result" style="display:none;" class="mt-3 alert"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-info" id="btn-proses-duplikasi">
          <i class="fas fa-copy"></i> Proses Duplikasi
        </button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
    var table = $('#example1').DataTable({ 
        "processing": true,
        "serverSide": true,
        "ajax": "barang-data-list-stock.php?cabang=<?= $sessionCabang; ?>",
        "columns": [
            { "data": 0 }, // No
            { "data": 1 }, // Kode Barang
            { "data": 2 }, // Nama
            { "data": 3 }, // Total Penjualan
            { "data": 4 }, // Kode Suplier
            { "data": 5 }, // Stock RETURAN
            { "data": 6 }, // Stock Gudang
            { "data": 7 }, // Stock Dukun
            { "data": 8 }, // Stock PP Srumbung
            { "data": 9 }, // Stock Pakis
            { "data": 10 }, // Stock Tegalrejo
            { 
                "data": 11, // Keterangan (HTML)
                "render": function(data, type, row) {
                    // Jika type adalah 'display', return HTML as is
                    // Jika type adalah 'filter' atau 'sort', return text saja
                    if (type === 'display') {
                        return data; // Return HTML
                    }
                    return data.replace(/<[^>]*>/g, ''); // Strip HTML untuk filter/sort
                }
            }
        ],
        "columnDefs": [{
            "targets": [3, 4, 5],
            "className": 'text-center'
        }]
    });

    table.on('draw.dt', function () {
        var info = table.page.info();
        table.column(0, { search: 'applied', order: 'applied', page: 'applied' }).nodes().each(function (cell, i) {
            cell.innerHTML = i + 1 + info.start;
        });
    });
    
    // Handle klik tombol duplikasi (menggunakan event delegation)
    $('#example1').on('click', '.btn-duplikasi', function() {
        var kode = $(this).data('kode');
        var nama = $(this).data('nama');
        var cabangTersedia = $(this).data('cabang');
        
        console.log('Kode:', kode);
        console.log('Nama:', nama);
        console.log('Cabang Tersedia:', cabangTersedia);
        
        // Set data ke modal
        $('#duplikasi-kode').text(kode);
        $('#duplikasi-nama').text(nama);
        
        // Semua cabang yang diharapkan
        var allCabang = [0, 1, 2, 3, 5, 6];
        var cabangNames = {
            0: 'Gudang',
            1: 'Dukun',
            2: 'Pakis',
            3: 'PP Srumbung',
            5: 'Tegalrejo',
            6: 'RETURAN'
        };
        
        // Parse cabang tersedia (string "0,1,3" -> array [0,1,3])
        var tersediaArr = [];
        if (cabangTersedia) {
            tersediaArr = cabangTersedia.toString().split(',').map(function(x) { 
                return parseInt(x); 
            });
        }
        
        console.log('Cabang Tersedia Array:', tersediaArr);
        
        // Cari cabang yang missing
        var missingIds = [];
        allCabang.forEach(function(id) {
            if (tersediaArr.indexOf(id) === -1) {
                missingIds.push(id);
            }
        });
        
        console.log('Missing IDs:', missingIds);
        
        // Generate checkbox untuk cabang yang missing
        var checkboxHtml = '';
        if (missingIds.length === 0) {
            checkboxHtml = '<p class="text-success">Barang sudah ada di semua cabang!</p>';
        } else {
            missingIds.forEach(function(id) {
                checkboxHtml += '<div class="form-check">';
                checkboxHtml += '<input class="form-check-input cabang-checkbox" type="checkbox" value="' + id + '" id="cb_' + id + '" checked>';
                checkboxHtml += '<label class="form-check-label" for="cb_' + id + '">' + cabangNames[id] + '</label>';
                checkboxHtml += '</div>';
            });
        }
        $('#cabang-checkbox-container').html(checkboxHtml);
        
        // Reset state
        $('#duplikasi-progress').hide();
        $('#duplikasi-result').hide();
        $('#btn-proses-duplikasi').prop('disabled', false);
        
        // Store kode barang di modal untuk nanti
        $('#modalDuplikasi').data('kode', kode);
        
        // Show modal
        $('#modalDuplikasi').modal('show');
    });
    
    // Handle proses duplikasi
    $('#btn-proses-duplikasi').click(function() {
        var kode = $('#modalDuplikasi').data('kode');
        var selectedCabang = [];
        
        // Ambil cabang yang dicentang
        $('.cabang-checkbox:checked').each(function() {
            selectedCabang.push($(this).val());
        });
        
        if (selectedCabang.length === 0) {
            alert('Pilih minimal 1 cabang tujuan!');
            return;
        }
        
        // Disable button dan show progress
        $('#btn-proses-duplikasi').prop('disabled', true);
        $('#duplikasi-progress').show();
        $('#duplikasi-result').hide();
        
        // Kirim request ke server
        $.ajax({
            url: 'aksi/barang-duplikasi-ke-cabang.php',
            method: 'POST',
            data: {
                barang_kode: kode,
                target_cabang: selectedCabang
            },
            dataType: 'json',
            success: function(response) {
                $('#duplikasi-progress').hide();
                $('#btn-proses-duplikasi').prop('disabled', false);
                
                if (response.success) {
                    $('#duplikasi-result')
                        .removeClass('alert-danger')
                        .addClass('alert-success')
                        .html('<strong>Berhasil!</strong><br>' + response.message)
                        .show();
                    
                    // Reload tabel setelah 2 detik
                    setTimeout(function() {
                        table.ajax.reload();
                        $('#modalDuplikasi').modal('hide');
                    }, 2000);
                } else {
                    $('#duplikasi-result')
                        .removeClass('alert-success')
                        .addClass('alert-danger')
                        .html('<strong>Gagal!</strong><br>' + response.message)
                        .show();
                }
                
                // Show logs jika ada
                if (response.logs && response.logs.length > 0) {
                    var logsHtml = '<hr><small><strong>Detail:</strong><br>' + response.logs.join('<br>') + '</small>';
                    $('#duplikasi-result').append(logsHtml);
                }
            },
            error: function(xhr, status, error) {
                $('#duplikasi-progress').hide();
                $('#btn-proses-duplikasi').prop('disabled', false);
                $('#duplikasi-result')
                    .removeClass('alert-success')
                    .addClass('alert-danger')
                    .html('<strong>Error!</strong><br>Terjadi kesalahan: ' + error)
                    .show();
            }
        });
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
    
    /* Style untuk tombol duplikasi */
    .btn-duplikasi {
        margin-top: 5px;
        font-size: 11px;
        padding: 3px 10px;
        white-space: nowrap;
    }
    
    .btn-duplikasi i {
        margin-right: 3px;
    }
    
    /* Style untuk badge di kolom keterangan */
    .badge {
        font-size: 11px;
        padding: 4px 8px;
    }
    
    /* Style untuk checkbox di modal */
    #cabang-checkbox-container .form-check {
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }
    
    #cabang-checkbox-container .form-check:last-child {
        border-bottom: none;
    }
    
    #cabang-checkbox-container .form-check-input {
        margin-top: 5px;
    }
    
    #cabang-checkbox-container .form-check-label {
        font-weight: 500;
        cursor: pointer;
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