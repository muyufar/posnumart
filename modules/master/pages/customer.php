<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
  error_reporting(0);
?>

<?php  
  if ($levelLogin === "kurir") {
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
          <h1>Data Customer</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Customers</li>
          </ol>
        </div>
        <div class="tambah-data">
          <a href="customer-add" class="btn btn-primary">Tambah Data</a>
          <!-- Button for Export Excel -->
          <a href="export/download_template_customer.php" class="btn btn-danger">Download Template</a>
          <form id="importForm" action="import/import-customer.php" method="post" enctype="multipart/form-data" style="display:inline;">
                <input type="file" name="excel_file" id="excelFileInput" accept=".xls, .xlsx" style="display:none;" required>
                <button type="button" id="importButton" class="btn btn-warning">Import Data</button>
            </form>
             <form action="export/export_customer_uji.php" method="get" style="display:inline;">
                <input type="hidden" name="id" value="<?= $sessionCabang; ?>">
                <button type="submit" class="btn btn-success">Ekspor Data</button>
            </form>
          <div id="toast" class="toast"></div>
        </div>
      </div>
    </div>
  </section>

  <!-- Form untuk Pencarian -->
  <div class="container-fluid">
    <form method="GET" action="">
      <div class="input-group mb-3">
        <input type="text" name="search" class="form-control" placeholder="Cari berdasarkan Kartu Customer" value="<?= isset($_GET['search']) ? $_GET['search'] : '' ?>">
        <div class="input-group-append">
          <button class="btn btn-primary" type="submit">Cari</button>
        </div>
      </div>
    </form>
  </div>

  <?php  
    // Mengambil parameter pencarian
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Query dengan kondisi pencarian
    $qu = "SELECT * FROM customer WHERE customer_cabang = $sessionCabang";
    if (!empty($search)) {
      $qu .= " AND customer_kartu LIKE '%" . $search . "%'";
    }
    $qu .= " ORDER BY customer_id DESC";

    $data = query($qu);
  ?>

  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Data Customer Keseluruhan</h3>
          </div>
          <div class="card-body">
            <div class="table-auto">
              <table id="example1" class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>No.</th>
                    <th>Nama</th>
                    <th>Alamat</th>
                    <th>No. Hp</th>
                    <th>Kategori</th>
                    <th>Kartu</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center; width: 14%;">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $i = 1; ?>
                  <?php foreach ($data as $row) : ?>
                    <?php if ($row['customer_id'] > 1 && $row['customer_nama'] !== "Customer Umum") { ?>
                      <tr>
                        <td><?= $i; ?></td>
                        <td><?= $row['customer_nama']; ?></td>
                        <td>
                          <?php  
                            $alamat = $row['customer_alamat'];
                            $alamat1 = substr($row['customer_alamat'], 0, 18) . '...';
                            echo (str_word_count($alamat) > 2) ? $alamat1 : $alamat;
                          ?>    
                        </td>
                        <td><?= $row['customer_tlpn']; ?></td>
                        <td>
                          <?php  
                            $customer_category = $row['customer_category'] == 1 ? "Member Retail" : ($row['customer_category'] == 2 ? "Grosir" : "Umum");
                            echo $customer_category;
                          ?>
                        </td>
                        <td><?= $row['customer_kartu']; ?></td>
                        <td style="text-align: center;">
                          <?= $row['customer_status'] === "1" ? "<b>Aktif</b>" : "<b style='color: red;'>Tidak Aktif</b>"; ?>
                        </td>
                        <td class="orderan-online-button">
                          <?php $id = $row["customer_id"]; ?>
                          <a href="customer-zoom?id=<?= $id; ?>" title="Zoom Data">
                            <button class="btn btn-success" type="button">
                              <i class="fa fa-search"></i>
                            </button>
                          </a>
                          <a href="customer-edit?id=<?= $id; ?>" title="Edit Data">
                            <button class="btn btn-primary" type="button">
                              <i class="fa fa-edit"></i>
                            </button>
                          </a>
                          <a href="customer-delete?id=<?= $id; ?>" onclick="return confirm('Yakin dihapus?')" title="Delete Data">
                            <button class="btn btn-danger" type="button">
                              <i class="fa fa-trash-o"></i>
                            </button>
                          </a>
                        </td>
                      </tr>
                    <?php } ?>
                  <?php $i++; ?>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include '_footer.php'; ?>
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

    fetch('import/import-customer.php', {
        method: 'POST',
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
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
<script>
  $(function () {
    $("#example1").DataTable();
  });
</script>
</body>
</html>
