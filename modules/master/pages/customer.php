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
      <div class="form-row align-items-end mb-3">
        <div class="col-md-5">
          <label class="mb-1">Cari</label>
          <input type="text" name="search" class="form-control" placeholder="Nama / No. WA / Kartu / Email" value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-3">
          <label class="mb-1">Verifikasi Online</label>
          <select name="verifikasi" class="form-control">
            <?php
              $verifikasiFilter = trim((string) ($_GET['verifikasi'] ?? ''));
              $verOpts = [
                '' => 'Semua',
                'none' => 'Belum upload',
                'pending' => 'Menunggu verifikasi',
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak',
              ];
              foreach ($verOpts as $vk => $vl) {
                $sel = $verifikasiFilter === (string) $vk ? ' selected' : '';
                echo '<option value="' . htmlspecialchars((string) $vk, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars($vl, ENT_QUOTES, 'UTF-8') . '</option>';
              }
            ?>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary btn-block" type="submit">Cari</button>
        </div>
      </div>
    </form>
  </div>

  <?php
    $search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
    $verifikasiFilter = isset($_GET['verifikasi']) ? trim((string) $_GET['verifikasi']) : '';
    $hasVerifikasiCol = function_exists('customer_has_column') && customer_has_column($conn, 'customer_verifikasi_status');

    $cabang = (int) $sessionCabang;
    $qu = "SELECT * FROM customer WHERE customer_cabang = $cabang";
    if ($search !== '') {
      $s = mysqli_real_escape_string($conn, $search);
      $qu .= " AND (
        customer_kartu LIKE '%$s%'
        OR customer_nama LIKE '%$s%'
        OR customer_tlpn LIKE '%$s%'
        OR customer_email LIKE '%$s%'
      )";
    }
    if ($hasVerifikasiCol && $verifikasiFilter !== '' && in_array($verifikasiFilter, ['none', 'pending', 'approved', 'rejected'], true)) {
      $vf = mysqli_real_escape_string($conn, $verifikasiFilter);
      $qu .= " AND customer_verifikasi_status = '$vf'";
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
                    <th>Alamat / Wilayah</th>
                    <th>No. WA</th>
                    <th>Kategori</th>
                    <th>Kartu</th>
                    <th>Poin</th>
                    <?php if ($hasVerifikasiCol) : ?>
                      <th>Verifikasi Online</th>
                    <?php endif; ?>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center; width: 14%;">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $i = 1; ?>
                  <?php foreach ($data as $row) : ?>
                    <?php if ((int) $row['customer_id'] > 1 && $row['customer_nama'] !== "Customer Umum") { ?>
                      <tr>
                        <td><?= $i; ?></td>
                        <td>
                          <?= htmlspecialchars($row['customer_nama'], ENT_QUOTES, 'UTF-8'); ?>
                          <?php if (!empty($row['customer_email'])) : ?>
                            <br><small class="text-muted"><?= htmlspecialchars($row['customer_email'], ENT_QUOTES, 'UTF-8'); ?></small>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php
                            $alamat = (string) ($row['customer_alamat'] ?? '');
                            $alamat1 = mb_strlen($alamat) > 40 ? mb_substr($alamat, 0, 40) . '...' : $alamat;
                            echo htmlspecialchars($alamat1, ENT_QUOTES, 'UTF-8');
                            $wilayah = trim(implode(', ', array_filter([
                              $row['alamat_desa'] ?? '',
                              $row['alamat_kecamatan'] ?? '',
                              $row['alamat_kabupaten'] ?? '',
                            ])));
                            if ($wilayah !== '') {
                              echo '<br><small class="text-muted">' . htmlspecialchars($wilayah, ENT_QUOTES, 'UTF-8') . '</small>';
                            }
                          ?>
                        </td>
                        <td><?= htmlspecialchars((string) $row['customer_tlpn'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                          <?php
                            $customer_category = ((int) $row['customer_category'] === 1)
                              ? 'Member Retail'
                              : (((int) $row['customer_category'] === 2) ? 'Grosir' : 'Umum');
                            echo $customer_category;
                          ?>
                        </td>
                        <td><?= htmlspecialchars((string) ($row['customer_kartu'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= (int) ($row['customer_poin'] ?? 0); ?></td>
                        <?php if ($hasVerifikasiCol) : ?>
                          <td>
                            <?= customer_verifikasi_badge((string) ($row['customer_verifikasi_status'] ?? 'none')); ?>
                            <?php if (!empty($row['customer_verifikasi_at'])) : ?>
                              <br><small class="text-muted"><?= htmlspecialchars((string) $row['customer_verifikasi_at'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                          </td>
                        <?php endif; ?>
                        <td style="text-align: center;">
                          <?= ((string) $row['customer_status'] === "1") ? "<b>Aktif</b>" : "<b style='color: red;'>Tidak Aktif</b>"; ?>
                        </td>
                        <td class="orderan-online-button">
                          <?php $id = (int) $row["customer_id"]; ?>
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
                      <?php $i++; ?>
                    <?php } ?>
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
