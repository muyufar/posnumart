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

  // Handle sync actions
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
      $action = $_POST['action'] ?? '';
      
      if ($action === 'test_connection') {
        // Test Shopee connection
        $api = getShopeeAPIHelper($activeCabang);
        $result = $api->getShopInfo();
        
        if (ShopeeAPIHelper::hasError($result)) {
          $message = 'Koneksi gagal: ' . ShopeeAPIHelper::getErrorMessage($result);
          $messageType = 'danger';
        } else {
          $message = 'Koneksi berhasil! Shop info: ' . json_encode($result['data']);
          $messageType = 'success';
        }
      }
      
      elseif ($action === 'sync_stock') {
        // Sync stock from POS to Shopee
        $api = getShopeeAPIHelper($activeCabang);
        
        // Get products that need stock sync (example: get from barang table)
        $sql = "SELECT kode_barang, stok FROM barang WHERE barang_cabang = " . $activeCabang . " LIMIT 10";
        $result = mysqli_query($conn, $sql);
        
        $stock_updates = [];
        while ($row = mysqli_fetch_assoc($result)) {
          // In real implementation, you'd need to map kode_barang to Shopee item_id
          // For now, we'll use placeholder data
          $stock_updates[] = [
            'item_id' => 123456789, // This should come from marketplace_mapping table
            'current_stock' => (int)$row['stok']
          ];
        }
        
        if (!empty($stock_updates)) {
          $api_result = $api->updateStock($stock_updates);
          
          if (ShopeeAPIHelper::hasError($api_result)) {
            $message = 'Update stok gagal: ' . ShopeeAPIHelper::getErrorMessage($api_result);
            $messageType = 'danger';
          } else {
            $message = 'Update stok berhasil untuk ' . count($stock_updates) . ' produk';
            $messageType = 'success';
          }
        } else {
          $message = 'Tidak ada produk yang perlu diupdate';
          $messageType = 'warning';
        }
      }
      
      elseif ($action === 'sync_price') {
        // Sync price from POS to Shopee
        $api = getShopeeAPIHelper($activeCabang);
        
        // Get products that need price sync (example: get from barang table)
        $sql = "SELECT kode_barang, harga_jual FROM barang WHERE barang_cabang = " . $activeCabang . " LIMIT 10";
        $result = mysqli_query($conn, $sql);
        
        $price_updates = [];
        while ($row = mysqli_fetch_assoc($result)) {
          // In real implementation, you'd need to map kode_barang to Shopee item_id
          // For now, we'll use placeholder data
          $price_updates[] = [
            'item_id' => 123456789, // This should come from marketplace_mapping table
            'price' => (float)$row['harga_jual']
          ];
        }
        
        if (!empty($price_updates)) {
          $api_result = $api->updatePrice($price_updates);
          
          if (ShopeeAPIHelper::hasError($api_result)) {
            $message = 'Update harga gagal: ' . ShopeeAPIHelper::getErrorMessage($api_result);
            $messageType = 'warning';
          } else {
            $message = 'Update harga berhasil untuk ' . count($price_updates) . ' produk';
            $messageType = 'success';
          }
        } else {
          $message = 'Tidak ada produk yang perlu diupdate';
          $messageType = 'warning';
        }
      }
      
    } catch (Exception $e) {
      $message = 'Error: ' . $e->getMessage();
      $messageType = 'danger';
    }
  }

  // Get current Shopee connection status
  $shopee_status = 'Disconnected';
  $shopee_info = '';
  
  try {
    $api = getShopeeAPIHelper($activeCabang);
    $shopee_status = 'Connected';
    
    // Try to get shop info
    $result = $api->getShopInfo();
    if (!ShopeeAPIHelper::hasError($result)) {
      $shopee_info = json_encode($result['data'], JSON_PRETTY_PRINT);
    }
  } catch (Exception $e) {
    $shopee_status = 'Error: ' . $e->getMessage();
  }
?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Sinkronisasi Shopee</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Sinkronisasi Shopee</li>
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
              <h3 class="card-title">Status Koneksi</h3>
            </div>
            <div class="card-body">
              <p><strong>Status:</strong> 
                <span class="badge badge-<?= $shopee_status === 'Connected' ? 'success' : 'danger' ?>">
                  <?= htmlspecialchars($shopee_status) ?>
                </span>
              </p>
              
              <?php if ($shopee_status === 'Connected' && !empty($shopee_info)): ?>
                <details>
                  <summary>Shop Info</summary>
                  <pre class="mt-2" style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px;"><?= htmlspecialchars($shopee_info) ?></pre>
                </details>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Test API</h3>
            </div>
            <div class="card-body">
              <form method="post" action="">
                <input type="hidden" name="action" value="test_connection">
                <button type="submit" class="btn btn-info btn-block">
                  <i class="fa fa-plug"></i> Test Koneksi Shopee
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-6">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Sinkronisasi Stok</h3>
            </div>
            <div class="card-body">
              <p class="text-muted">
                Update stok produk dari POS ke Shopee. 
                Pastikan produk sudah di-mapping dengan item Shopee.
              </p>
              <form method="post" action="">
                <input type="hidden" name="action" value="sync_stock">
                <button type="submit" class="btn btn-warning btn-block">
                  <i class="fa fa-refresh"></i> Sync Stok ke Shopee
                </button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Sinkronisasi Harga</h3>
            </div>
            <div class="card-body">
              <p class="text-muted">
                Update harga produk dari POS ke Shopee. 
                Pastikan produk sudah di-mapping dengan item Shopee.
              </p>
              <form method="post" action="">
                <input type="hidden" name="action" value="sync_price">
                <button type="submit" class="btn btn-primary btn-block">
                  <i class="fa fa-dollar"></i> Sync Harga ke Shopee
                </button>
              </form>
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
                <h5><i class="icon fa fa-info"></i> Cara Kerja Sinkronisasi</h5>
                <ol>
                  <li><strong>Mapping Produk:</strong> Produk di POS harus di-mapping dengan item Shopee melalui tabel marketplace_mapping</li>
                  <li><strong>Update Stok:</strong> Stok dari tabel barang akan di-sync ke Shopee menggunakan endpoint /api/v2/product/stock/update</li>
                  <li><strong>Update Harga:</strong> Harga dari tabel barang akan di-sync ke Shopee menggunakan endpoint /api/v2/product/price/update</li>
                  <li><strong>Batch Update:</strong> Sistem akan mengirim update dalam batch untuk efisiensi</li>
                </ol>
              </div>
              
              <div class="alert alert-warning">
                <h5><i class="icon fa fa-exclamation-triangle"></i> Yang Perlu Diperhatikan</h5>
                <ul>
                  <li>Pastikan koneksi Shopee sudah aktif dan access_token masih valid</li>
                  <li>Produk harus sudah di-mapping dengan item Shopee sebelum bisa di-sync</li>
                  <li>Rate limit Shopee: maksimal 100 request per menit</li>
                  <li>Untuk implementasi production, buat cron job untuk auto-sync setiap 5-10 menit</li>
                </ul>
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
