<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
  include 'shopee-api-helper.php';
?>

<?php  
  if ( $levelLogin !== "super admin" ) {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }

  $activeCabang = isset($sessionCabang) ? (int)$sessionCabang : 0;
  $message = '';
  $messageType = '';

  // Auto-create mapping table if missing
  $createTableSql = "CREATE TABLE IF NOT EXISTS marketplace_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cabang INT NOT NULL DEFAULT 0,
    kode_barang VARCHAR(100) NOT NULL,
    marketplace VARCHAR(50) NOT NULL DEFAULT 'shopee',
    shop_id BIGINT NULL,
    item_id BIGINT NOT NULL,
    variation_id BIGINT NULL,
    seller_sku VARCHAR(100) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_mapping (cabang, kode_barang, marketplace, item_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  mysqli_query($conn, $createTableSql);

  // Handle form submissions
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_mapping') {
      $kode_barang = trim($_POST['kode_barang']);
      $item_id = (int)$_POST['item_id'];
      $variation_id = !empty($_POST['variation_id']) ? (int)$_POST['variation_id'] : 'NULL';
      $seller_sku = trim($_POST['seller_sku']);
      
      if (empty($kode_barang) || $item_id < 1) {
        $message = 'Kode barang dan Item ID harus diisi';
        $messageType = 'danger';
      } else {
        $variation_sql = $variation_id !== 'NULL' ? (int)$variation_id : 'NULL';
        $seller_sku_sql = !empty($seller_sku) ? "'" . mysqli_real_escape_string($conn, $seller_sku) . "'" : 'NULL';
        
        $sql = "INSERT INTO marketplace_mapping (cabang, kode_barang, marketplace, item_id, variation_id, seller_sku) 
                VALUES (" . $activeCabang . ", '" . mysqli_real_escape_string($conn, $kode_barang) . "', 'shopee', " . $item_id . ", " . $variation_sql . ", " . $seller_sku_sql . ")
                ON DUPLICATE KEY UPDATE 
                variation_id = VALUES(variation_id), 
                seller_sku = VALUES(seller_sku), 
                updated_at = NOW()";
        
        if (mysqli_query($conn, $sql)) {
          $message = 'Mapping berhasil disimpan';
          $messageType = 'success';
        } else {
          $message = 'Gagal menyimpan mapping: ' . mysqli_error($conn);
          $messageType = 'danger';
        }
      }
    }
    
    elseif ($action === 'delete_mapping') {
      $id = (int)$_POST['id'];
      $sql = "DELETE FROM marketplace_mapping WHERE id = " . $id . " AND cabang = " . $activeCabang;
      
      if (mysqli_query($conn, $sql)) {
        $message = 'Mapping berhasil dihapus';
        $messageType = 'success';
      } else {
        $message = 'Gagal menghapus mapping: ' . mysqli_error($conn);
        $messageType = 'danger';
      }
    }
    
    elseif ($action === 'search_shopee_items') {
      try {
        $api = getShopeeAPIHelper($activeCabang);
        $search_term = trim($_POST['search_term']);
        
        if (!empty($search_term)) {
          // Get product list and search for matching items
          $result = $api->getProductList(0, 100, 'NORMAL');
          
          if (!ShopeeAPIHelper::hasError($result)) {
            $items = $result['data']['item_list'] ?? [];
            $filtered_items = [];
            
            foreach ($items as $item) {
              if (stripos($item['item_name'], $search_term) !== false || 
                  stripos($item['item_sku'], $search_term) !== false) {
                $filtered_items[] = $item;
              }
            }
            
            if (!empty($filtered_items)) {
              $message = 'Ditemukan ' . count($filtered_items) . ' item Shopee yang cocok';
              $messageType = 'success';
            } else {
              $message = 'Tidak ada item Shopee yang cocok dengan pencarian';
              $messageType = 'warning';
            }
          } else {
            $message = 'Gagal mengambil data Shopee: ' . ShopeeAPIHelper::getErrorMessage($result);
            $messageType = 'danger';
          }
        }
      } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
      }
    }
  }

  // Get existing mappings
  $mappings = [];
  $sql = "SELECT m.*, b.nama_barang 
          FROM marketplace_mapping m 
          LEFT JOIN barang b ON m.kode_barang = b.kode_barang AND m.cabang = b.barang_cabang
          WHERE m.cabang = " . $activeCabang . " 
          ORDER BY m.created_at DESC";
  $result = mysqli_query($conn, $sql);
  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $mappings[] = $row;
    }
  }

  // Get available products for mapping
  $products = [];
  $sql = "SELECT kode_barang, nama_barang, stok, harga_jual 
          FROM barang 
          WHERE barang_cabang = " . $activeCabang . " 
          ORDER BY nama_barang";
  $result = mysqli_query($conn, $sql);
  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $products[] = $row;
    }
  }
?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Mapping Produk Shopee</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Mapping Shopee</li>
            </ol>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible">
          <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <div class="row">
        <div class="col-md-6">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Tambah Mapping Baru</h3>
            </div>
            <div class="card-body">
              <form method="post" action="">
                <input type="hidden" name="action" value="add_mapping">
                
                <div class="form-group">
                  <label>Produk POS</label>
                  <select name="kode_barang" class="form-control" required>
                    <option value="">Pilih Produk</option>
                    <?php foreach ($products as $product): ?>
                      <option value="<?= htmlspecialchars($product['kode_barang']) ?>">
                        <?= htmlspecialchars($product['nama_barang']) ?> (<?= htmlspecialchars($product['kode_barang']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                
                <div class="form-group">
                  <label>Item ID Shopee</label>
                  <input type="number" name="item_id" class="form-control" placeholder="123456789" required>
                  <small class="form-text text-muted">ID item dari Shopee (bisa dilihat di URL produk Shopee)</small>
                </div>
                
                <div class="form-group">
                  <label>Variation ID (Opsional)</label>
                  <input type="number" name="variation_id" class="form-control" placeholder="987654321">
                  <small class="form-text text-muted">ID variasi jika produk memiliki variasi (ukuran/warna)</small>
                </div>
                
                <div class="form-group">
                  <label>Seller SKU (Opsional)</label>
                  <input type="text" name="seller_sku" class="form-control" placeholder="SKU123">
                  <small class="form-text text-muted">SKU kustom yang Anda gunakan di Shopee</small>
                </div>
                
                <button type="submit" class="btn btn-primary">Simpan Mapping</button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Cari Item Shopee</h3>
            </div>
            <div class="card-body">
              <form method="post" action="">
                <input type="hidden" name="action" value="search_shopee_items">
                
                <div class="form-group">
                  <label>Kata Kunci Pencarian</label>
                  <input type="text" name="search_term" class="form-control" placeholder="Nama produk atau SKU" required>
                  <small class="form-text text-muted">Cari item di Shopee berdasarkan nama atau SKU</small>
                </div>
                
                <button type="submit" class="btn btn-info">Cari Item Shopee</button>
              </form>
              
              <div class="mt-3">
                <small class="text-muted">
                  <strong>Tips:</strong> Untuk mendapatkan Item ID Shopee:
                  <ol>
                    <li>Buka produk di Shopee</li>
                    <li>Lihat URL, Item ID ada di bagian akhir</li>
                    <li>Contoh: https://shopee.co.id/product/123456789 → Item ID = 123456789</li>
                  </ol>
                </small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Mapping yang Sudah Ada</h3>
            </div>
            <div class="card-body">
              <?php if (empty($mappings)): ?>
                <div class="text-center text-muted py-4">
                  <i class="fa fa-info-circle fa-3x mb-3"></i>
                  <p>Belum ada mapping produk dengan Shopee</p>
                  <p>Gunakan form di atas untuk menambahkan mapping</p>
                </div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-bordered table-striped">
                    <thead>
                      <tr>
                        <th>No.</th>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Item ID Shopee</th>
                        <th>Variation ID</th>
                        <th>Seller SKU</th>
                        <th>Status</th>
                        <th>Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($mappings as $index => $mapping): ?>
                        <tr>
                          <td><?= $index + 1 ?></td>
                          <td><?= htmlspecialchars($mapping['kode_barang']) ?></td>
                          <td><?= htmlspecialchars($mapping['nama_barang'] ?? 'Tidak ditemukan') ?></td>
                          <td><?= htmlspecialchars($mapping['item_id']) ?></td>
                          <td><?= $mapping['variation_id'] ? htmlspecialchars($mapping['variation_id']) : '-' ?></td>
                          <td><?= htmlspecialchars($mapping['seller_sku'] ?? '-') ?></td>
                          <td>
                            <?php if ($mapping['nama_barang']): ?>
                              <span class="badge badge-success">Terhubung</span>
                            <?php else: ?>
                              <span class="badge badge-warning">Barang Tidak Ditemukan</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <form method="post" action="" style="display: inline;">
                              <input type="hidden" name="action" value="delete_mapping">
                              <input type="hidden" name="id" value="<?= $mapping['id'] ?>">
                              <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Yakin hapus mapping ini?')">
                                <i class="fa fa-trash"></i>
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Informasi Penting</h3>
            </div>
            <div class="card-body">
              <div class="alert alert-info">
                <h5><i class="icon fa fa-info"></i> Mengapa Mapping Diperlukan?</h5>
                <p>Mapping diperlukan untuk menghubungkan produk di sistem POS Anda dengan item yang ada di Shopee. Tanpa mapping, sistem tidak akan tahu item mana yang harus diupdate saat sinkronisasi stok atau harga.</p>
              </div>
              
              <div class="alert alert-warning">
                <h5><i class="icon fa fa-exclamation-triangle"></i> Langkah Mapping</h5>
                <ol>
                  <li><strong>Identifikasi Produk:</strong> Pilih produk dari sistem POS yang ingin di-mapping</li>
                  <li><strong>Dapatkan Item ID:</strong> Buka produk di Shopee dan catat Item ID dari URL</li>
                  <li><strong>Isi Form:</strong> Masukkan Item ID dan informasi tambahan jika diperlukan</li>
                  <li><strong>Verifikasi:</strong> Pastikan mapping sudah benar sebelum melakukan sinkronisasi</li>
                </ol>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

<?php include '_footer.php'; ?>
</body>
</html>
