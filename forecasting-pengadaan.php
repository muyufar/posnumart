<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
include 'aksi/koneksi.php';

if ($levelLogin === "kasir" || $levelLogin === "kurir") {
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
          <h1><i class="fa fa-chart-line"></i> Forecasting Pengadaan Barang (AI)</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Forecasting Pengadaan</li>
          </ol>
        </div>
      </div>
    </div><!-- /.container-fluid -->
  </section>

  <section class="content">
    <div class="container-fluid">
      <!-- Filter Card -->
      <div class="card card-default">
        <div class="card-header">
          <h3 class="card-title"><i class="fa fa-filter"></i> Filter Data Forecasting</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
          </div>
        </div>
        <form role="form" action="" method="POST" id="formForecasting">
          <div class="card-body">
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label for="tanggal_awal">Periode Analisis (Awal)</label>
                  <input type="date" name="tanggal_awal" class="form-control" id="tanggal_awal"
                    value="<?= $_POST['tanggal_awal'] ?? date('Y-m-d', strtotime('-3 months')) ?>" required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="tanggal_akhir">Periode Analisis (Akhir)</label>
                  <input type="date" name="tanggal_akhir" class="form-control" id="tanggal_akhir"
                    value="<?= $_POST['tanggal_akhir'] ?? date('Y-m-d') ?>" required>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label for="periode_forecast">Periode Forecast (Hari)</label>
                  <input type="number" name="periode_forecast" class="form-control" id="periode_forecast"
                    value="<?= $_POST['periode_forecast'] ?? 30 ?>" min="7" max="365" required>
                  <small class="text-muted">7-365 hari</small>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label for="min_stock">Min. Stock Alert</label>
                  <input type="number" name="min_stock" class="form-control" id="min_stock"
                    value="<?= $_POST['min_stock'] ?? 10 ?>" min="0" required>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label>&nbsp;</label>
                  <button type="submit" name="submit" class="btn btn-primary form-control">
                    <i class="fa fa-calculator"></i> Hitung Forecasting
                  </button>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label>Kategori Barang (Opsional)</label>
                  <select class="form-control select2bs4" name="kategori_id" id="kategori_id">
                    <option value="semua">Semua Kategori</option>
                    <?php
                    $kategori = query("SELECT * FROM kategori WHERE kategori_cabang = $sessionCabang ORDER BY kategori_nama ASC");
                    foreach ($kategori as $row) : ?>
                      <option value="<?= $row['kategori_id'] ?>"
                        <?= (isset($_POST['kategori_id']) && $_POST['kategori_id'] == $row['kategori_id']) ? 'selected' : '' ?>>
                        <?= $row['kategori_nama'] ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Kode Supplier (Opsional)</label>
                  <select class="form-control select2bs4" name="kode_suplier" id="kode_suplier">
                    <option value="semua">Semua Supplier</option>
                    <?php
                    // Ambil daftar kode supplier yang unik dari tabel barang
                    $kode_suplier_list = query("SELECT DISTINCT kode_suplier FROM barang WHERE barang_cabang = $sessionCabang AND barang_status = '1' AND kode_suplier != '' AND kode_suplier IS NOT NULL ORDER BY kode_suplier ASC");
                    foreach ($kode_suplier_list as $row) : ?>
                      <option value="<?= htmlspecialchars($row['kode_suplier']) ?>"
                        <?= (isset($_POST['kode_suplier']) && $_POST['kode_suplier'] == $row['kode_suplier']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['kode_suplier']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Filter Stock Cabang</label>
                  <select class="form-control select2bs4" name="filter_stock_cabang" id="filter_stock_cabang">
                    <option value="semua" <?= (!isset($_POST['filter_stock_cabang']) || $_POST['filter_stock_cabang'] == 'semua') ? 'selected' : '' ?>>Semua Cabang (Total)</option>
                    <?php
                    // Ambil daftar cabang dari tabel toko
                    $list_toko = query("SELECT * FROM toko WHERE toko_status = '1' ORDER BY toko_cabang ASC");
                    foreach ($list_toko as $toko) :
                      $cabang_label = ($toko['toko_cabang'] == 0) ? 'Pusat' : 'Cabang ' . $toko['toko_cabang'];
                      $cabang_nama = !empty($toko['toko_nama']) ? $toko['toko_nama'] : $cabang_label;
                    ?>
                      <option value="<?= $toko['toko_cabang'] ?>"
                        <?= (isset($_POST['filter_stock_cabang']) && $_POST['filter_stock_cabang'] == $toko['toko_cabang']) ? 'selected' : '' ?>>
                        <?= $cabang_label ?> - <?= $cabang_nama ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <small class="text-muted">Stock yang ditampilkan</small>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Metode Forecasting</label>
                  <select class="form-control" name="metode" id="metode">
                    <option value="moving_average" <?= (!isset($_POST['metode']) || $_POST['metode'] == 'moving_average') ? 'selected' : '' ?>>Moving Average (Rata-rata Bergerak)</option>
                    <option value="exponential" <?= (isset($_POST['metode']) && $_POST['metode'] == 'exponential') ? 'selected' : '' ?>>Exponential Smoothing</option>
                    <option value="linear" <?= (isset($_POST['metode']) && $_POST['metode'] == 'linear') ? 'selected' : '' ?>>Linear Regression</option>
                    <option value="weighted" <?= (isset($_POST['metode']) && $_POST['metode'] == 'weighted') ? 'selected' : '' ?>>Weighted Average</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>

      <?php if (isset($_POST["submit"])) { ?>
        <?php
        $tanggal_awal = $_POST['tanggal_awal'];
        $tanggal_akhir = $_POST['tanggal_akhir'];
        $periode_forecast = intval($_POST['periode_forecast']);
        $min_stock = intval($_POST['min_stock']);
        $kategori_id = $_POST['kategori_id'] ?? 'semua';
        $kode_suplier = $_POST['kode_suplier'] ?? 'semua';
        $filter_stock_cabang = $_POST['filter_stock_cabang'] ?? 'semua';
        $metode = $_POST['metode'] ?? 'moving_average';

        // Ambil data forecasting
        $forecasting_data = [];

        // Query untuk mendapatkan data penjualan per barang
        $where_kategori = ($kategori_id != 'semua') ? "AND b.kategori_id = '$kategori_id'" : "";
        $where_suplier = ($kode_suplier != 'semua') ? "AND b.kode_suplier = '" . mysqli_real_escape_string($conn, $kode_suplier) . "'" : "";

        // Query yang dioptimalkan: ambil penjualan dan supplier dalam satu query
        $query_penjualan = "
            SELECT 
                p.barang_id,
                b.barang_kode,
                b.barang_nama,
                b.kode_suplier,
                k.kategori_nama,
                DATE(p.penjualan_date) as tanggal,
                SUM(p.barang_qty) as qty_terjual
            FROM penjualan p
            INNER JOIN barang b ON p.barang_id = b.barang_id
            LEFT JOIN kategori k ON b.kategori_id = k.kategori_id
            WHERE p.penjualan_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
            AND p.penjualan_cabang = $sessionCabang
            AND b.barang_status = '1'
            $where_kategori
            $where_suplier
            GROUP BY p.barang_id, DATE(p.penjualan_date)
            ORDER BY p.barang_id, tanggal
        ";

        $result_penjualan = mysqli_query($conn, $query_penjualan);

        // Simpan hasil query ke array dan ambil daftar barang_id sekaligus
        $penjualan_data = [];
        $barang_ids = [];
        if ($result_penjualan) {
          while ($row = mysqli_fetch_assoc($result_penjualan)) {
            $penjualan_data[] = $row;
            if (!in_array($row['barang_id'], $barang_ids)) {
              $barang_ids[] = intval($row['barang_id']);
            }
          }
        }

        // Ambil stock sesuai filter cabang (hanya untuk barang yang ada di hasil penjualan)
        $stock_by_barang = [];
        if (!empty($barang_ids)) {
          $barang_ids_str = implode(',', $barang_ids);

          // Ambil barang_kode untuk barang yang ada di hasil penjualan
          $barang_kodes_query = "SELECT DISTINCT barang_kode FROM barang WHERE barang_id IN ($barang_ids_str)";
          $result_kodes = mysqli_query($conn, $barang_kodes_query);
          $barang_kodes = [];
          if ($result_kodes) {
            while ($row = mysqli_fetch_assoc($result_kodes)) {
              $barang_kodes[] = "'" . mysqli_real_escape_string($conn, $row['barang_kode']) . "'";
            }
          }

          if (!empty($barang_kodes)) {
            $barang_kodes_str = implode(',', $barang_kodes);

            if ($filter_stock_cabang == 'semua') {
              // Ambil total stock dari semua cabang per barang_kode
              $query_stock = "
                  SELECT 
                      b.barang_id,
                      COALESCE(SUM(b2.barang_stock), 0) as total_stock
                  FROM barang b
                  LEFT JOIN barang b2 ON b.barang_kode = b2.barang_kode 
                      AND b2.barang_status = '1'
                  WHERE b.barang_id IN ($barang_ids_str)
                  GROUP BY b.barang_id
              ";
            } else {
              // Ambil stock dari cabang tertentu - query yang lebih sederhana dan efisien
              $filter_cabang = intval($filter_stock_cabang);
              $query_stock = "
                  SELECT 
                      b.barang_id,
                      COALESCE(MAX(b2.barang_stock), 0) as total_stock
                  FROM barang b
                  LEFT JOIN barang b2 ON b.barang_kode = b2.barang_kode 
                      AND b2.barang_cabang = $filter_cabang 
                      AND b2.barang_status = '1'
                  WHERE b.barang_id IN ($barang_ids_str)
                  GROUP BY b.barang_id
              ";
            }

            $result_stock = mysqli_query($conn, $query_stock);
            if ($result_stock) {
              while ($row = mysqli_fetch_assoc($result_stock)) {
                $stock_by_barang[$row['barang_id']] = intval($row['total_stock']);
              }
            }
          }
        }

        // Query supplier yang dioptimalkan: hanya untuk barang yang ada di hasil penjualan
        // Gunakan pendekatan yang lebih sederhana dan cepat
        $supplier_by_barang = [];
        if (!empty($barang_ids)) {
          $barang_ids_str = implode(',', $barang_ids);

          // Query yang lebih cepat: ambil semua pembelian terakhir per barang dalam satu query
          // Gunakan ORDER BY dan GROUP BY untuk mendapatkan yang terakhir
          $query_supplier = "
                SELECT 
                    p.barang_id,
                    s.supplier_id,
                    s.supplier_nama,
                    s.supplier_wa,
                    s.supplier_company
                FROM (
                    SELECT 
                        p1.barang_id,
                        p1.pembelian_invoice,
                        ip1.invoice_tgl,
                        ip1.invoice_supplier,
                        @row_number := IF(@prev_barang = p1.barang_id, @row_number + 1, 1) AS rn,
                        @prev_barang := p1.barang_id
                    FROM pembelian p1
                    INNER JOIN invoice_pembelian ip1 ON p1.pembelian_invoice = ip1.pembelian_invoice
                    CROSS JOIN (SELECT @row_number := 0, @prev_barang := 0) AS vars
                    WHERE p1.barang_id IN ($barang_ids_str)
                    AND p1.pembelian_cabang = $sessionCabang
                    ORDER BY p1.barang_id, ip1.invoice_tgl DESC
                ) latest_pembelian
                INNER JOIN supplier s ON latest_pembelian.invoice_supplier = s.supplier_id
                WHERE latest_pembelian.rn = 1
            ";

          // Jika query dengan variabel tidak didukung, gunakan query alternatif yang lebih sederhana
          $result_supplier = @mysqli_query($conn, $query_supplier);
          if (!$result_supplier) {
            // Fallback: query yang lebih sederhana tapi masih efisien
            $query_supplier = "
                    SELECT 
                        p.barang_id,
                        s.supplier_id,
                        s.supplier_nama,
                        s.supplier_wa,
                        s.supplier_company
                    FROM pembelian p
                    INNER JOIN invoice_pembelian ip ON p.pembelian_invoice = ip.pembelian_invoice
                    INNER JOIN supplier s ON ip.invoice_supplier = s.supplier_id
                    WHERE p.barang_id IN ($barang_ids_str)
                    AND p.pembelian_cabang = $sessionCabang
                    ORDER BY p.barang_id, ip.invoice_tgl DESC
                ";

            $result_supplier = mysqli_query($conn, $query_supplier);
            if ($result_supplier) {
              $current_barang = 0;
              while ($row = mysqli_fetch_assoc($result_supplier)) {
                // Ambil hanya yang pertama (terbaru) untuk setiap barang
                if ($current_barang != $row['barang_id']) {
                  $current_barang = $row['barang_id'];
                  $supplier_by_barang[$row['barang_id']] = [
                    'supplier_id' => $row['supplier_id'],
                    'supplier_nama' => $row['supplier_nama'] ?? '',
                    'supplier_wa' => $row['supplier_wa'] ?? '',
                    'supplier_company' => $row['supplier_company'] ?? ''
                  ];
                }
              }
            }
          } else {
            // Query dengan variabel berhasil
            while ($row = mysqli_fetch_assoc($result_supplier)) {
              $supplier_by_barang[$row['barang_id']] = [
                'supplier_id' => $row['supplier_id'],
                'supplier_nama' => $row['supplier_nama'] ?? '',
                'supplier_wa' => $row['supplier_wa'] ?? '',
                'supplier_company' => $row['supplier_company'] ?? ''
              ];
            }
          }
        }

        if (!empty($penjualan_data)) {
          // Organize data by barang_id dari array yang sudah disimpan
          $data_by_barang = [];
          foreach ($penjualan_data as $row) {
            $barang_id = $row['barang_id'];
            if (!isset($data_by_barang[$barang_id])) {
              $supplier_data = $supplier_by_barang[$barang_id] ?? null;
              // Gunakan stock dari query stock yang sudah difilter, jika tidak ada gunakan 0
              $stock_sekarang = $stock_by_barang[$barang_id] ?? 0;
              $data_by_barang[$barang_id] = [
                'barang_id' => $row['barang_id'],
                'barang_kode' => $row['barang_kode'],
                'barang_nama' => $row['barang_nama'],
                'stock_sekarang' => $stock_sekarang,
                'kode_suplier' => $row['kode_suplier'] ?? '',
                'kategori_nama' => $row['kategori_nama'],
                'supplier_wa' => $supplier_data['supplier_wa'] ?? '',
                'supplier_nama' => $supplier_data['supplier_nama'] ?? '',
                'supplier_company' => $supplier_data['supplier_company'] ?? '',
                'penjualan_harian' => []
              ];
            }
            $data_by_barang[$barang_id]['penjualan_harian'][] = [
              'tanggal' => $row['tanggal'],
              'qty' => intval($row['qty_terjual'])
            ];
          }

          // Hitung forecasting untuk setiap barang
          foreach ($data_by_barang as $barang_id => $data) {
            $penjualan_harian = $data['penjualan_harian'];

            if (empty($penjualan_harian)) {
              continue;
            }

            // Hitung jumlah hari dalam periode
            $start_date = new DateTime($tanggal_awal);
            $end_date = new DateTime($tanggal_akhir);
            $jumlah_hari = $start_date->diff($end_date)->days + 1;

            // Hitung total penjualan
            $total_penjualan = array_sum(array_column($penjualan_harian, 'qty'));

            // Hitung rata-rata penjualan per hari
            $avg_penjualan_per_hari = $total_penjualan / $jumlah_hari;

            // Hitung prediksi kebutuhan berdasarkan metode
            $prediksi_kebutuhan = 0;

            switch ($metode) {
              case 'moving_average':
                $prediksi_kebutuhan = $avg_penjualan_per_hari * $periode_forecast;
                break;

              case 'exponential':
                $alpha = 0.3;
                $values = array_column($penjualan_harian, 'qty');
                $forecast = $values[0];
                for ($i = 1; $i < count($values); $i++) {
                  $forecast = $alpha * $values[$i] + (1 - $alpha) * $forecast;
                }
                $prediksi_kebutuhan = $forecast * $periode_forecast;
                break;

              case 'linear':
                $n = count($penjualan_harian);
                if ($n > 1) {
                  $x_sum = 0;
                  $y_sum = 0;
                  $xy_sum = 0;
                  $x2_sum = 0;

                  foreach ($penjualan_harian as $idx => $item) {
                    $x = $idx + 1;
                    $y = $item['qty'];
                    $x_sum += $x;
                    $y_sum += $y;
                    $xy_sum += $x * $y;
                    $x2_sum += $x * $x;
                  }

                  $slope = ($n * $xy_sum - $x_sum * $y_sum) / ($n * $x2_sum - $x_sum * $x_sum);
                  $intercept = ($y_sum - $slope * $x_sum) / $n;

                  $next_x = $n + ($periode_forecast / $jumlah_hari);
                  $prediksi_per_hari = $slope * $next_x + $intercept;
                  $prediksi_kebutuhan = max(0, $prediksi_per_hari * $periode_forecast);
                } else {
                  $prediksi_kebutuhan = $avg_penjualan_per_hari * $periode_forecast;
                }
                break;

              case 'weighted':
                $total_weighted = 0;
                $total_weight = 0;
                $n = count($penjualan_harian);

                foreach ($penjualan_harian as $idx => $item) {
                  $weight = $idx + 1;
                  $total_weighted += $item['qty'] * $weight;
                  $total_weight += $weight;
                }

                $weighted_avg = $total_weight > 0 ? $total_weighted / $total_weight : 0;
                $prediksi_kebutuhan = $weighted_avg * $periode_forecast;
                break;

              default:
                $prediksi_kebutuhan = $avg_penjualan_per_hari * $periode_forecast;
            }

            // Hitung rekomendasi qty
            $safety_stock = $prediksi_kebutuhan * 0.2;
            $kebutuhan_total = $prediksi_kebutuhan + $safety_stock;
            $rekomendasi_qty = max(0, ceil($kebutuhan_total - $data['stock_sekarang']));

            if ($data['stock_sekarang'] < $min_stock && $rekomendasi_qty < $min_stock) {
              $rekomendasi_qty = max($rekomendasi_qty, $min_stock * 2);
            }

            $forecasting_data[] = [
              'barang_id' => $data['barang_id'],
              'barang_kode' => $data['barang_kode'],
              'barang_nama' => $data['barang_nama'],
              'kode_suplier' => $data['kode_suplier'] ?? '',
              'kategori_nama' => $data['kategori_nama'],
              'stock_sekarang' => $data['stock_sekarang'],
              'supplier_wa' => $data['supplier_wa'] ?? '',
              'supplier_nama' => $data['supplier_nama'] ?? '',
              'supplier_company' => $data['supplier_company'] ?? '',
              'avg_penjualan_per_hari' => round($avg_penjualan_per_hari, 2),
              'prediksi_kebutuhan' => round($prediksi_kebutuhan, 0),
              'rekomendasi_qty' => $rekomendasi_qty,
              'total_penjualan_periode' => $total_penjualan,
              'jumlah_hari_analisis' => $jumlah_hari
            ];
          }
        }
        ?>

        <!-- Summary Card -->
        <div class="row">
          <div class="col-md-3">
            <div class="info-box">
              <span class="info-box-icon bg-info elevation-1"><i class="fa fa-box"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Barang Dianalisis</span>
                <span class="info-box-number"><?= count($forecasting_data) ?></span>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="info-box">
              <span class="info-box-icon bg-warning elevation-1"><i class="fa fa-exclamation-triangle"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Perlu Pengadaan</span>
                <span class="info-box-number">
                  <?= count(array_filter($forecasting_data, function ($item) {
                    return $item['rekomendasi_qty'] > 0;
                  })) ?>
                </span>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="info-box">
              <span class="info-box-icon bg-danger elevation-1"><i class="fa fa-arrow-down"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Stock Rendah</span>
                <span class="info-box-number">
                  <?= count(array_filter($forecasting_data, function ($item) use ($min_stock) {
                    return $item['stock_sekarang'] < $min_stock;
                  })) ?>
                </span>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="info-box">
              <span class="info-box-icon bg-success elevation-1"><i class="fa fa-check-circle"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Stock Cukup</span>
                <span class="info-box-number">
                  <?= count(array_filter($forecasting_data, function ($item) use ($min_stock) {
                    return $item['stock_sekarang'] >= $min_stock && $item['rekomendasi_qty'] == 0;
                  })) ?>
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Results Card -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fa fa-list"></i> Hasil Forecasting Pengadaan Barang
              <?php if ($filter_stock_cabang != 'semua') :
                $toko_info = query("SELECT * FROM toko WHERE toko_cabang = " . intval($filter_stock_cabang) . " LIMIT 1");
                $cabang_label = ($filter_stock_cabang == 0) ? 'Pusat' : 'Cabang ' . $filter_stock_cabang;
              ?>
                <small class="text-muted">(Stock: <?= $cabang_label ?>)</small>
              <?php else : ?>
                <small class="text-muted">(Stock: Semua Cabang - Total)</small>
              <?php endif; ?>
            </h3>
            <div class="card-tools">
              <button type="button" class="btn btn-sm btn-success" onclick="exportToExcel()">
                <i class="fa fa-file-excel"></i> Export Excel
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="tableForecasting" class="table table-bordered table-striped table-hover">
                <thead>
                  <tr>
                    <th style="width: 5%;">No</th>
                    <th>Kode Barang</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th>Kode Supplier</th>
                    <th style="text-align: center;">Stock Sekarang</th>
                    <th style="text-align: center;">Rata-rata Penjualan/Hari</th>
                    <th style="text-align: center;">Prediksi Kebutuhan</th>
                    <th style="text-align: center;">Rekomendasi Qty</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center;">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($forecasting_data)) : ?>
                    <tr>
                      <td colspan="11" class="text-center">
                        <div class="alert alert-info">
                          <i class="fa fa-info-circle"></i> Tidak ada data untuk ditampilkan. Pastikan ada data penjualan dalam periode yang dipilih.
                        </div>
                      </td>
                    </tr>
                  <?php else : ?>
                    <?php
                    $no = 1;
                    // Urutkan berdasarkan rekomendasi qty (tertinggi dulu)
                    usort($forecasting_data, function ($a, $b) {
                      return $b['rekomendasi_qty'] <=> $a['rekomendasi_qty'];
                    });
                    foreach ($forecasting_data as $item) :
                      $status_class = '';
                      $status_text = '';
                      if ($item['stock_sekarang'] < $min_stock) {
                        $status_class = 'danger';
                        $status_text = 'Stock Rendah';
                      } elseif ($item['rekomendasi_qty'] > 0) {
                        $status_class = 'warning';
                        $status_text = 'Perlu Pengadaan';
                      } else {
                        $status_class = 'success';
                        $status_text = 'Stock Cukup';
                      }
                    ?>
                      <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($item['barang_kode']) ?></td>
                        <td><?= htmlspecialchars($item['barang_nama']) ?></td>
                        <td><?= htmlspecialchars($item['kategori_nama'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($item['kode_suplier'] ?? '-') ?></td>
                        <td style="text-align: center;">
                          <span class="badge badge-<?= $item['stock_sekarang'] < $min_stock ? 'danger' : 'info' ?>">
                            <?= number_format($item['stock_sekarang'], 0, ',', '.') ?>
                          </span>
                        </td>
                        <td style="text-align: center;">
                          <?= number_format($item['avg_penjualan_per_hari'], 2, ',', '.') ?>
                        </td>
                        <td style="text-align: center;">
                          <strong><?= number_format($item['prediksi_kebutuhan'], 0, ',', '.') ?></strong>
                        </td>
                        <td style="text-align: center;">
                          <?php if ($item['rekomendasi_qty'] > 0) : ?>
                            <span class="badge badge-warning badge-lg">
                              <?= number_format($item['rekomendasi_qty'], 0, ',', '.') ?>
                            </span>
                          <?php else : ?>
                            <span class="badge badge-secondary">-</span>
                          <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                          <span class="badge badge-<?= $status_class ?>"><?= $status_text ?></span>
                        </td>
                        <td style="text-align: center;">
                          <?php if ($item['rekomendasi_qty'] > 0 && !empty($item['supplier_wa'])) :
                            // Format nomor WA (hapus karakter non-numeric)
                            $wa_number = preg_replace('/[^0-9]/', '', $item['supplier_wa']);
                            // Jika tidak dimulai dengan 62, tambahkan 62
                            if (substr($wa_number, 0, 2) != '62' && substr($wa_number, 0, 1) == '0') {
                              $wa_number = '62' . substr($wa_number, 1);
                            } elseif (substr($wa_number, 0, 2) != '62' && substr($wa_number, 0, 1) != '0') {
                              $wa_number = '62' . $wa_number;
                            }

                            // Buat pesan WhatsApp
                            $pesan = "Halo " . ($item['supplier_nama'] ?? 'Supplier') . ",\n\n";
                            $pesan .= "Saya ingin memesan barang berikut:\n\n";
                            $pesan .= "📦 *" . htmlspecialchars($item['barang_nama']) . "*\n";
                            $pesan .= "Kode: " . htmlspecialchars($item['barang_kode']) . "\n";
                            $pesan .= "Jumlah: *" . number_format($item['rekomendasi_qty'], 0, ',', '.') . " pcs*\n";
                            $pesan .= "Stock saat ini: " . number_format($item['stock_sekarang'], 0, ',', '.') . " pcs\n\n";
                            $pesan .= "Mohon konfirmasi ketersediaan dan harga. Terima kasih.";

                            $wa_link = "https://wa.me/" . $wa_number . "?text=" . urlencode($pesan);
                          ?>
                            <a href="<?= $wa_link ?>"
                              target="_blank"
                              class="btn btn-sm btn-success"
                              title="Hubungi Supplier via WhatsApp">
                              <i class="fa fa-whatsapp"></i> Hubungi Supplier
                            </a>
                          <?php elseif ($item['rekomendasi_qty'] > 0 && empty($item['supplier_wa'])) : ?>
                            <span class="badge badge-secondary" title="Nomor WhatsApp supplier tidak tersedia">
                              <i class="fa fa-info-circle"></i> WA Tidak Tersedia
                            </span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php } else { ?>
        <div class="card">
          <div class="card-body">
            <div class="alert alert-info">
              <h5><i class="icon fa fa-info"></i> Informasi</h5>
              <p>Silakan pilih periode analisis dan parameter forecasting, kemudian klik tombol <strong>"Hitung Forecasting"</strong> untuk melihat prediksi pengadaan barang.</p>
              <hr>
              <h6><strong>Cara Kerja Forecasting:</strong></h6>
              <ul>
                <li>Sistem menganalisis data penjualan dan pembelian dalam periode yang dipilih</li>
                <li>Menghitung rata-rata penjualan per hari untuk setiap barang</li>
                <li>Memprediksi kebutuhan barang untuk periode forecast yang ditentukan</li>
                <li>Memberikan rekomendasi jumlah pengadaan berdasarkan stock saat ini</li>
              </ul>
            </div>
          </div>
        </div>
      <?php } ?>
    </div>
  </section>
</div>

<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<script>
  $(function() {
    $("#tableForecasting").DataTable({
      "responsive": true,
      "autoWidth": false,
      "order": [
        [7, "desc"]
      ], // Sort by rekomendasi qty
      "pageLength": 25,
      "language": {
        "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
      }
    });
  });

  // Initialize Select2
  $(function() {
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    });
  });

  // Export to Excel function
  function exportToExcel() {
    var table = document.getElementById("tableForecasting");
    var html = table.outerHTML;
    var url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
    var link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", "forecasting_pengadaan_<?= date('Y-m-d') ?>.xls");
    link.click();
  }
</script>

<?php include '_footer.php'; ?>