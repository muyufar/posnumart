<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
  
  global $url;
  
?>
<?php  
  if ( $levelLogin === "kurir") {
    echo "
      <script>
        document.location.href = 'kurir-data';
      </script>
    ";
  }  
?>
  <?php  
    $invoice_date_year_month = date("Y-m");
    $penjualanSum = query("SELECT COALESCE(SUM(barang_qty), 0) AS total FROM penjualan WHERE penjualan_cabang = $sessionCabang AND penjualan_date_year_month = '".$invoice_date_year_month."' LIMIT 1");
    $jmlPenjualan = (int) ($penjualanSum[0]['total'] ?? 0);
  ?>
  
<?php    
  // Hitung total penjualan bulan ini  
  $totalPenjualanBulanIni = 0;  
  $tanggalBulanIni = date("Y-m"); // Tahun dan bulan sekarang  

  $queryInvoice = $conn->query("
    SELECT COALESCE(SUM(invoice_sub_total), 0) AS total
    FROM invoice  
    WHERE 
      invoice_cabang = '".$sessionCabang."' 
      AND DATE_FORMAT(invoice_date, '%Y-%m') = '".$tanggalBulanIni."'
  ");  
  if ($queryInvoice && ($row = $queryInvoice->fetch_assoc())) {
      $totalPenjualanBulanIni = (float) ($row['total'] ?? 0);
  }
?>


<!-- Total penjualan Nominal hari ini -->
<?php  
  // Hitung total penjualan hari ini
  $totalPenjualanHariIni = 0;
  $tanggalHariIni = date("Y-m-d");

  $queryInvoiceHariIni = $conn->query("
    SELECT COALESCE(SUM(invoice_sub_total), 0) AS total
    FROM invoice 
    WHERE 
      invoice_cabang = '".$sessionCabang."' 
      AND DATE(invoice_date) = '".$tanggalHariIni."'
  ");
  if ($queryInvoiceHariIni && ($row = $queryInvoiceHariIni->fetch_assoc())) {
      $totalPenjualanHariIni = (float) ($row['total'] ?? 0);
  }
?>


<?php
  // =========================
  // TOTAL TRANSFER STOK HARI INI
  // =========================
  $totalTransferHariIni = 0;
  $tanggalHariIni = date("Y-m-d");

  $resultTransferHariIni = $conn->query("
    SELECT 
      SUM(tpk.tpk_qty * CAST((CASE WHEN b.barang_harga_beli_rata > 0 THEN b.barang_harga_beli_rata ELSE b.barang_harga_beli END) AS DECIMAL(11,1))) AS total
    FROM transfer_produk_keluar AS tpk
    LEFT JOIN barang AS b ON tpk.tpk_barang_id = b.barang_id
    WHERE 
      tpk.tpk_pengirim_cabang = '".$sessionCabang."'
      AND DATE(tpk.tpk_date) = '".$tanggalHariIni."'
  ");

  $dataTransferHariIni = $resultTransferHariIni ? $resultTransferHariIni->fetch_assoc() : null;
  $totalTransferHariIni = $dataTransferHariIni['total'] ?? 0;


  // =========================
  // TOTAL TRANSFER STOK BULAN INI
  // =========================
  $totalTransferBulanIni = 0;
  $tanggalBulanIni = date("Y-m");

  $resultTransferBulanIni = $conn->query("
    SELECT 
      SUM(tpk.tpk_qty * CAST((CASE WHEN b.barang_harga_beli_rata > 0 THEN b.barang_harga_beli_rata ELSE b.barang_harga_beli END) AS DECIMAL(11,1))) AS total
    FROM transfer_produk_keluar AS tpk
    LEFT JOIN barang AS b ON tpk.tpk_barang_id = b.barang_id
    WHERE 
      tpk.tpk_pengirim_cabang = '".$sessionCabang."'
      AND DATE_FORMAT(tpk.tpk_date, '%Y-%m') = '".$tanggalBulanIni."'
  ");

  $dataTransferBulanIni = $resultTransferBulanIni ? $resultTransferBulanIni->fetch_assoc() : null;
  $totalTransferBulanIni = $dataTransferBulanIni['total'] ?? 0;
?>

  <!-- End Total penjualan Nominal hari ini -->

  <?php  

    $barangCount = mysqli_query($conn, "SELECT COUNT(*) AS total FROM barang WHERE barang_cabang = ".$sessionCabang." AND barang_status = 1");
    $jmlBarang = 0;
    if ($barangCount && ($rowBarang = mysqli_fetch_assoc($barangCount))) {
        $jmlBarang = (int) ($rowBarang['total'] ?? 0);
    }

    $invoiceCount = mysqli_query($conn, "SELECT COUNT(*) AS total FROM invoice WHERE invoice_cabang = '".$sessionCabang."' AND invoice_piutang < 1 AND invoice_date_year_month = '".$invoice_date_year_month."'");
    $jmlInvoice = 0;
    if ($invoiceCount && ($rowInvoice = mysqli_fetch_assoc($invoiceCount))) {
        $jmlInvoice = (int) ($rowInvoice['total'] ?? 0);
    }
  ?>

    
  <!-- Total Invoice hari ini -->
  <?php  
    $invoiceHariIniCount = mysqli_query($conn, "SELECT COUNT(*) AS total FROM invoice WHERE invoice_cabang = '".$sessionCabang."' AND invoice_date = '".$tanggalHariIni."' AND invoice_piutang = 0 AND invoice_piutang_lunas = 0");
    $jmlInvoiceHariIni = 0;
    if ($invoiceHariIniCount && ($rowInvHari = mysqli_fetch_assoc($invoiceHariIniCount))) {
        $jmlInvoiceHariIni = (int) ($rowInvHari['total'] ?? 0);
    }
  ?>
  <!-- End Total Invoice hari ini -->
    
<?php
// Fungsi untuk memformat angka menjadi format Rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Fungsi untuk menghitung asumsi keuntungan
function asumsiKeuntungan($totalJual, $totalBeli) {
    return $totalJual - $totalBeli;
}

// Total nilai persediaan berdasarkan HPP rata-rata (bukan harga beli terakhir)
$totalNilaiBarang = dashboard_total_nilai_stok_beli_hpp($conn, $sessionCabang);

$totalNilaiBarangJual = 0.0;
$nilaiBarangJual = mysqli_query($conn, "SELECT COALESCE(SUM(barang_stock * barang_harga), 0) AS total
                                        FROM barang 
                                        WHERE barang_cabang = '".$sessionCabang."' AND barang_status = 1 AND barang_stock > 0");
if ($nilaiBarangJual && ($rowNilaiJual = mysqli_fetch_assoc($nilaiBarangJual))) {
    $totalNilaiBarangJual = (float) ($rowNilaiJual['total'] ?? 0);
}

// Menampilkan total nilai barang jual
// echo "Total Nilai Barang Jual: " . formatRupiah($totalNilaiBarangJual) . "<br>";

// Menghitung dan menampilkan asumsi keuntungan
$keuntungan = asumsiKeuntungan($totalNilaiBarangJual, $totalNilaiBarang);
// echo "Asumsi Keuntungan: " . formatRupiah($keuntungan);
?>



  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper modern-dashboard">
    <!-- Content Header (Page header) -->
    <div class="content-header modern-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0 text-dark modern-title">Dashboard <b><?= $tipeToko; ?> <?= $dataTokoLogin['toko_kota']; ?></b></h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right modern-breadcrumb">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Dashboard</li>
            </ol>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content modern-content">
      <div class="container-fluid">
        <!-- Small boxes (Stat box) -->
        <div class="row modern-stats-row">
          <div class="col-lg-6 col-md-6">
            <!-- small box -->
            <div class="small-box bg-info">
              <div class="inner">
                <h3>Rp <?= number_format($totalPenjualanBulanIni, 0, ',', '.'); ?></h3>

                <p>Penjualan <b>Bulan ini</b></p>
              </div>
              <div class="icon">
                <i class="fa fa-money"></i>
              </div>
              
            </div>
          </div>

  <div class="col-lg-6 col-md-6">
            <!-- small box -->
            <div class="small-box bg-info">
              <div class="inner">
                <h3>Rp <?= number_format($totalPenjualanHariIni, 0, ',', '.'); ?></h3>

                <p>Penjualan <b>Hari ini</b></p>
              </div>
              <div class="icon">
                <i class="fa fa-money"></i>
              </div>
              
            </div>
          </div>
          


<div class="col-lg-6 col-md-6">
  <div class="small-box bg-primary">
    <div class="inner">
      <h3><?= number_format($totalTransferBulanIni, 0, ',', '.'); ?></h3>
      <p>Total Transfer Stok Bulan Ini</p>
    </div>
    <div class="icon">
      <i class="fas fa-warehouse"></i>
    </div>
  </div>
</div>

<div class="col-lg-3 col-6">
  <div class="small-box bg-primary">
    <div class="inner">
      <h3><?= number_format($totalTransferHariIni, 0, ',', '.'); ?></h3>
      <p>Total Transfer Stok Hari Ini</p>
    </div>
    <div class="icon">
      <i class="fas fa-exchange-alt"></i>
    </div>
  </div>
</div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
              <div class="inner">
                <h3><?= singkat_angka($jmlInvoiceHariIni); ?></h3>
                <p>Invoice Penjualan <b>Hari ini</b></p>
              </div>
              <div class="icon">
                <i class="fa fa-file-text-o"></i>
              </div>
            </div>
          </div>

            <div class="col-lg-3 col-md-6">
            <!-- small box -->
            <div class="small-box bg-warning" style="background: #fff;">
              <div class="inner">
                <h3><?= $jmlPenjualan; ?></h3>
                <p><b>Total</b> Barang Terjual Bulan Ini</p>
              </div>
              <div class="icon">
                <i class="fa fa-shopping-cart" style="color: #17a2b8;"></i>
              </div>
            </div>
          </div>

          <div class="col-lg-4 col-md-6">
            <!-- small box -->
            <div class="small-box" style="background: #fff;">
              <div class="inner">
                <h3><?= $jmlBarang; ?></h3>

                <p>Jumlah Barang</p>
              </div>
              <div class="icon">
                <i class="fa fa-shopping-bag" style="color: #28a745;"></i>
              </div>
            </div>
          </div>

          <div class="col-lg-4 col-md-6">
            <!-- small box -->
            <div class="small-box" style="background: #fff;">
              <div class="inner">
                <h3><?= $jmlInvoice; ?></h3>

                <p><b>Total</b> Invoice Penjualan Bulan ini</p>
              </div>
              <div class="icon">
                <i class="fa fa-table" style="color: #dc3545;"></i>
              </div>
            </div>
          </div>
          

          
        </div>
        <!-- /.row -->
       
      </div><!-- /.container-fluid -->
    </section>
    
    
    <section class="content">
    <div class="container-fluid">
        <div class="row"> <!-- Tambahkan row di sini -->
            <div class="col-lg-4 col-md-6">
                <!-- small box -->
                <div class="small-box" style="background: #fff;">
                    <div class="inner">
                        <h2><?= formatRupiah($totalNilaiBarang); ?></h2>
                        <p><b>Total</b> Stock Barang Beli (HPP)</p>
                    </div>
                    <div class="icon">
                        <i class="fa fa-money" style="color: #dc3545;"></i>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <!-- small box -->
                <div class="small-box" style="background: #fff;">
                    <div class="inner">
                        <h2><?= formatRupiah($totalNilaiBarangJual); ?></h2>
                        <p><b>Total</b> Stock Barang Jual</p>
                    </div>
                    <div class="icon">
                        <i class="fa fa-money" style="color: #dc3545;"></i>
                    </div>
                </div>
            </div>
            
            
             <div class="col-lg-4 col-md-6">
                <!-- small box -->
                <div class="small-box" style="background: #fff;">
                    <div class="inner">
                        <h2><?= formatRupiah($keuntungan); ?></h2>
                        <p><b>Total</b> Asumsi Keuntungan</p>
                    </div>
                    <div class="icon">
                        <i class="fa fa-money" style="color: #dc3545;"></i>
                    </div>
                </div>
            </div>
        </div> <!-- Tutup row di sini -->
    </div>
</section>

    <!-- /.content -->
    <?php include 'bo-grafik.php' ?>
    <section class="table-informasi">
        <div class="row">
          <div class="col-lg-6">
             <div class="card">
                <div class="card-header">
                  <h3 class="card-title"><b>Data Barang</b> Terlaris</h3>
                </div>
              <!-- /.card-header -->
                <div class="card-body">
                  <div class="table-auto">
                    <table id="" class="table table-bordered table-striped">
                      <thead>
                      <tr>
                        <th>No.</th>
                        <th>Kode Barang</th>
                        <th>Nama</th>
                        <th>Terjual</th>
                      </tr>
                      </thead>
                      <tbody>

                      <?php 
                        $i = 1; 
                        $queryProduct = $conn->query("SELECT barang.barang_id, barang.barang_kode, barang.barang_nama, barang.barang_harga, barang.barang_terjual, barang.barang_cabang
                                   FROM barang 
                                   WHERE barang_cabang = '".$sessionCabang."' && barang_terjual > 0  ORDER BY barang_terjual DESC LIMIT 5
                                   ");
                        while ($rowProduct = mysqli_fetch_array($queryProduct)) {
                      ?>
                      <tr>
                          <td><?= $i; ?></td>
                          <td><?= $rowProduct['barang_kode']; ?></td>
                          <td><?= $rowProduct['barang_nama']; ?></td>
                          <td>
                            <b><?= $rowProduct['barang_terjual']; ?></b>
                          </td>
                      </tr>
                      <?php $i++; ?>
                      <?php } ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              <!-- /.card-body -->
              </div>
          </div>

          <div class="col-lg-6">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><b>Data Stok</b> Terkecil</h3>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <div class="table-auto">
                  <table id="" class="table table-bordered table-striped">
                    <thead>
                    <tr>
                      <th>No.</th>
                      <th>Kode Barang</th>
                      <th>Nama</th>
                      <th>Stock</th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php 
                      $i = 1; 
                        $q = "SELECT barang.barang_id, barang.barang_kode, barang.barang_nama, barang.barang_harga, barang.barang_stock, barang.barang_cabang
                                 FROM barang 
                                 WHERE barang_cabang = '" . $sessionCabang . "' && barang_stock < 10 ORDER BY barang_stock ASC LIMIT 5
                                 ";
                        $queryProduct = $conn->query($q);
                      while ($rowProduct = mysqli_fetch_array($queryProduct)) {
                    ?>
                    <tr>
                        <td><?= $i; ?></td>
                        <td><?= $rowProduct['barang_kode']; ?></td>
                        <td><?= $rowProduct['barang_nama']; ?></td>
                        <td>
                          <b><?= $rowProduct['barang_stock']; ?></b>
                        </td>
                    </tr>
                    <?php $i++; ?>
                    <?php } ?>
                    </tbody>
                  </table>
                </div>
              <!-- /.card-body -->
              </div>
            </div>
          </div>

          <div class="col-lg-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><b>Data Piutang</b> Jatuh Tempo Kurang dari 30 Hari</h3>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <div class="table-auto">
                  <table id="example1" class="table table-bordered table-striped">
                    <thead>
                    <tr>
                      <th>No.</th>
                      <th>Invoice</th>
                      <th>Transaksi</th>
                      <th>Customer</th>
                      <th>Jatuh Tempo</th>
                      <th>Sub Total</th>
                      <th class="text-center">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php 
                      $i = 1; 
                      $date_max  = date('Y-m-d', strtotime('30 days'));
                      $queryProduct = $conn->query("SELECT invoice.invoice_id, invoice.penjualan_invoice, invoice.invoice_date, invoice.invoice_sub_total, invoice.invoice_cabang, invoice.invoice_customer, invoice.invoice_piutang_jatuh_tempo, invoice.invoice_piutang_dp, invoice.invoice_bayar, invoice.invoice_kembali, customer.customer_id, customer.customer_nama, customer.customer_tlpn
                                 FROM invoice 
                                 JOIN customer ON invoice.invoice_customer = customer.customer_id
                                 WHERE invoice_cabang = '".$sessionCabang."' && invoice_piutang > 0 && invoice_piutang_jatuh_tempo <= '".$date_max."' ORDER BY invoice_id DESC
                                 ");
                      while ($rowProduct = mysqli_fetch_array($queryProduct)) {
                    ?>
                    <tr>
                        <td><?= $i; ?></td>
                        <td><?= $rowProduct['penjualan_invoice']; ?></td>
                        <td><?= tanggal_indo($rowProduct['invoice_date']); ?></td>
                        <td><?= $rowProduct['customer_nama']; ?></td>
                        <td>
                            <?php
                                // Jatuh Tempo
                                $piutangJatuhTempo = tanggal_indo($rowProduct['invoice_piutang_jatuh_tempo']);

                                // Durasi Hari
                                $tgl1 = new DateTime($tanggalHariIni);
                                $tgl2 = new DateTime($rowProduct['invoice_piutang_jatuh_tempo']);
                                $d = $tgl2->diff($tgl1)->days;

                                if ( $tanggalHariIni > $rowProduct['invoice_piutang_jatuh_tempo']) {
                                  $nh = "<span class='badge badge-danger'>Lewat ".$d." Hari</span>";
                                  $dWA = "Lewat ".$d." Hari";
                                  echo "<s>".$piutangJatuhTempo."<s> ".$nh;

                                } else {
                                  if ( $d > 20 ) {
                                     $nh = "<span class='badge badge-warning'>".$d." Hari Lagi</span>";
                                  } elseif ( $d <= 20 ) {
                                      $nh = "<span class='badge badge-danger'>".$d." Hari Lagi</span>";
                                  } else {
                                      $nh = "<span class='badge badge-danger'>".$d." Hari Lagi</span>";
                                  }
                                  $dWA = $d." Hari Lagi";
                                  echo $piutangJatuhTempo." ".$nh;  
                                }
                            ?>
                        </td>
                        <td>
                          Rp. <?= number_format($rowProduct['invoice_sub_total'], 0, ',', '.'); ?>
                        </td>
                        <td class="orderan-online-button">
                            <a href="penjualan-zoom?no=<?= base64_encode($rowProduct['invoice_id']); ?>" target="_blank">
                              <button class='btn btn-info' title='Lihat Data'>
                                <i class='fa fa-eye'></i>
                              </button>
                            </a>

                            <a href="piutang-cicilan?no=<?= base64_encode($rowProduct['invoice_id']); ?>">
                              <button class='btn btn-primary' title='Cicilan'>
                                <i class='fa fa-money'></i>
                              </button>
                            </a>

                            <?php  
                              $no_wa = substr_replace($rowProduct['customer_tlpn'],'62',0,1)
                            ?>
                            <a href="https://api.whatsapp.com/send?phone=<?= $no_wa; ?>&text=Halo <?= $rowProduct['customer_nama'];?>, Kami dari *<?= $dataTokoLogin['toko_nama']; ?> <?= $dataTokoLogin['toko_kota']; ?>* memberikan informasi bahwa transaksi *No Invoice <?= $rowProduct['penjualan_invoice'];?> dengan jumlah transaksi Rp <?= number_format($rowProduct['invoice_sub_total'], 0, ',', '.'); ?>* sudah mendekati jatuh tempo pada <?= $piutangJatuhTempo; ?> (<?= $dWA; ?> dimulai dari tanggal sekarang).%0A%0ASub Total: Rp <?= number_format($rowProduct['invoice_sub_total'], 0, ',', '.'); ?>%2C%0ADP: Rp <?= number_format($rowProduct['invoice_piutang_dp'], 0, ',', '.'); ?>%2C%0ADP ditambah Total Cicilan: Rp <?= number_format($rowProduct['invoice_bayar'], 0, ',', '.'); ?> %2C%0A*Sisa Piutang: Rp <?= number_format($rowProduct['invoice_kembali'], 0, ',', '.'); ?>*%2C%0A%0A%0AMohon Segera Dilunasi" target="_blank">
                              <button class='btn btn-success' title='Cicilan'>
                                <i class='fa fa-whatsapp'></i>
                              </button>
                            </a>

                            <a href="nota-cetak?no=<?= $rowProduct['invoice_id']; ?>" target="_blank">
                              <button class='btn btn-warning' title="Cetak Nota">
                                <i class='fa fa-print'></i>
                              </button>
                            </a>
                        </td>
                    </tr>
                    <?php $i++; ?>

                    <?php } ?>
                    </tbody>
                  </table>
                </div>
              <!-- /.card-body -->
              </div>
            </div>
          </div>

          <div class="col-lg-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><b>Data Hutang</b> Jatuh Tempo Kurang dari 30 Hari</h3>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <div class="table-auto">
                  <table id="example2" class="table table-bordered table-striped">
                    <thead>
                    <tr>
                      <th>No.</th>
                      <th>Invoice</th>
                      <th>Transaksi</th>
                      <th>Supplier</th>
                      <th>Jatuh Tempo</th>
                      <th>Sub Total</th>
                      <th class="text-center">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php 
                      $i = 1; 
                      $date_max  = date('Y-m-d', strtotime('30 days'));
                      $queryProduct = $conn->query("SELECT invoice_pembelian.invoice_pembelian_id, 
                        invoice_pembelian.pembelian_invoice, 
                        invoice_pembelian.invoice_date, 
                        invoice_pembelian.invoice_total, 
                        invoice_pembelian.invoice_pembelian_cabang, 
                        invoice_pembelian.invoice_supplier, 
                        invoice_pembelian.invoice_hutang_jatuh_tempo, 
                        invoice_pembelian.invoice_hutang_dp, 
                        invoice_pembelian.invoice_bayar, 
                        invoice_pembelian.invoice_kembali, 
                        supplier.supplier_id, 
                        supplier.supplier_nama, 
                        supplier.supplier_company
                                 FROM invoice_pembelian 
                                 JOIN supplier ON invoice_pembelian.invoice_supplier = supplier.supplier_id
                                 WHERE invoice_pembelian_cabang = '".$sessionCabang."' && invoice_hutang > 0 && invoice_bayar < invoice_total && invoice_hutang_jatuh_tempo <= '".$date_max."' ORDER BY invoice_pembelian_id DESC
                                 ");
                      while ($rowProduct = mysqli_fetch_array($queryProduct)) {
                    ?>
                    <tr>
                        <td><?= $i; ?></td>
                        <td><?= $rowProduct['pembelian_invoice']; ?></td>
                        <td><?= tanggal_indo($rowProduct['invoice_date']); ?></td>
                        <td><?= $rowProduct['supplier_company']; ?> - <?= $rowProduct['supplier_nama']; ?></td>
                        <td>
                            <?php
                                // Jatuh Tempo
                                $hutangJatuhTempo = tanggal_indo($rowProduct['invoice_hutang_jatuh_tempo']);

                                // Durasi Hari
                                $tgl1a = new DateTime($tanggalHariIni);
                                $tgl2a = new DateTime($rowProduct['invoice_hutang_jatuh_tempo']);
                                $da = $tgl2a->diff($tgl1a)->days;

                                if ( $tanggalHariIni > $rowProduct['invoice_hutang_jatuh_tempo']) {
                                  $nha = "<span class='badge badge-danger'>Lewat ".$da." Hari</span>";
                                  echo "<s>".$hutangJatuhTempo."<s> ".$nha;

                                } else {
                                  if ( $da > 20 ) {
                                     $nha = "<span class='badge badge-warning'>".$da." Hari Lagi</span>";
                                  } elseif ( $da <= 20 ) {
                                      $nha = "<span class='badge badge-danger'>".$da." Hari Lagi</span>";
                                  } else {
                                      $nha = "<span class='badge badge-danger'>".$da." Hari Lagi</span>";
                                  }
                                  echo $hutangJatuhTempo." ".$nha;
                                } 
                            ?>
                        </td>
                        <td>
                          Rp. <?= number_format($rowProduct['invoice_total'], 0, ',', '.'); ?>
                        </td>
                        <td class="orderan-online-button">
                            <a href="pembelian-zoom?no=<?= base64_encode($rowProduct['invoice_pembelian_id']); ?>" target="_blank">
                              <button class='btn btn-info' title='Lihat Data'>
                                <i class='fa fa-eye'></i>
                              </button>
                            </a>

                            <a href="hutang-cicilan?no=<?= base64_encode($rowProduct['invoice_pembelian_id']); ?>">
                              <button class='btn btn-primary' title='Cicilan'>
                                <i class='fa fa-money'></i>
                              </button>
                            </a>

                            <a href="nota-cetak-pembelian?no=<?= $rowProduct['invoice_pembelian_id']; ?>" target="_blank">
                              <button class='btn btn-warning' title="Cetak Nota">
                                <i class='fa fa-print'></i>
                              </button>
                            </a>
                        </td>
                    </tr>
                    <?php $i++; ?>

                    <?php } ?>
                    </tbody>
                  </table>
                </div>
              <!-- /.card-body -->
              </div>
            </div>
          </div>
    </section>
  </div>
  <!-- /.content-wrapper -->

  <section class="kasir-bo">
    <a href="beli-langsung?customer=<?= base64_encode(0); ?>">
      <img src="dist/img/kasir.png" alt="POS CREATIVE CODE APP" class="img-fluid"> 
    </a>   
  </section>

<?php include '_footer.php'; ?>
<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<script>
  $(function () {
    $("#example1").DataTable();
    $("#example2").DataTable();
  });
</script>
<script>
    const ctx = $('#myChart')
  const ctx2 = $('#myChart2')
  const ctx3 = $('#myChart3')
  const ctx4 = $('#myChart4')
  const api = 'api/grafik/'
  // jQuery AJAX request to fetch the API data
  // !Grafik 1
  $.ajax({
    url: api + 'penjualan', // Ganti dengan path API PHP Anda
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      $('#loading').hide();
      // Menginisialisasi Chart.js setelah data diambil
      new Chart(ctx, {
        type: 'bar', // Tipe chart
        data: data, // Data dari API
        options: {
          responsive: true,
          scales: {
            x: {
              type: 'category',
              title: {
                display: true,
                text: 'Invoice Date'
              }
            },
            y: {
              ticks: {
                callback: function(value, index, values) {
                  // Format nilai sumbu Y sebagai mata uang Rupiah
                  return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                  }).format(value);
                  console.log("🚀 ~ value:", value)
                }
              }
            }
          },
          tooltips: {
            mode: 'index',
            intersect: false,
            callbacks: {
              label: function(tooltipItem, chart) {
                const datasetLabel = chart?.datasets[tooltipItem?.datasetIndex].label || '';
                const value = tooltipItem.yLabel;

                // Format nilai sebagai mata uang Rupiah
                const formattedValue = new Intl.NumberFormat('id-ID', {
                  style: 'currency',
                  currency: 'IDR',
                  minimumFractionDigits: 0, // Atur sesuai kebutuhan, misalnya 2 untuk dua desimal
                  maximumFractionDigits: 0
                }).format(value);

                return `${datasetLabel} ${formattedValue}`;
              }
            }
          }
        }
      });
    },
    error: function(xhr, status, error) {
      console.error('Error fetching data: ' + error);
    }
  });
  // !Grafik 2
  $.ajax({
    url: api + 'pembelian', // Ganti dengan path API PHP Anda
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      $('#loading2').hide();
      // Menginisialisasi Chart.js setelah data diambil
      new Chart(ctx2, {
        type: 'bar', // Tipe chart
        data: data, // Data dari API
        options: {
          responsive: true,
          scales: {
            x: {
              type: 'category',
              title: {
                display: true,
                text: 'Invoice Date'
              }
            },
            y: {
              ticks: {
                callback: function(value, index, values) {
                  // Format nilai sumbu Y sebagai mata uang Rupiah
                  return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                  }).format(value);
                  console.log("🚀 ~ value:", value)
                }
              }
            }
          },
          tooltips: {
            mode: 'index',
            intersect: false,
            callbacks: {
              label: function(tooltipItem, chart) {
                const datasetLabel = chart?.datasets[tooltipItem?.datasetIndex].label || '';
                const value = tooltipItem.yLabel;

                // Format nilai sebagai mata uang Rupiah
                const formattedValue = new Intl.NumberFormat('id-ID', {
                  style: 'currency',
                  currency: 'IDR',
                  minimumFractionDigits: 0, // Atur sesuai kebutuhan, misalnya 2 untuk dua desimal
                  maximumFractionDigits: 0
                }).format(value);

                return `${datasetLabel} ${formattedValue}`;
              }
            }
          }

        }
      });
    },
    error: function(xhr, status, error) {
      console.error('Error fetching data: ' + error);
    }
  });
  // !Grafik 3
  $.ajax({
    url: api + 'terlaris', // Ganti dengan path API PHP Anda
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      $('#loading3').hide();
      // Menginisialisasi Chart.js setelah data diambil
      new Chart(ctx3, {
        type: 'doughnut', // Tipe chart
        data: data, // Data dari API
        options: {
          responsive: true,
          scales: {
            x: {
              type: 'category',
              title: {
                display: true,
                text: 'Invoice Date'
              }
            },
            y: {
              ticks: {
                callback: function(value, index, values) {
                  // Format nilai sumbu Y sebagai mata uang Rupiah
                  return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                  }).format(value);
                  console.log("🚀 ~ value:", value)
                }
              }
            }
          },
        }
      });
    },
    error: function(xhr, status, error) {
      console.error('Error fetching data: ' + error);
    }
  });
  // !Grafik 4
  $.ajax({
    url: api + 'stok-menipis', // Ganti dengan path API PHP Anda
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      $('#loading4').hide();
      // Menginisialisasi Chart.js setelah data diambil
      new Chart(ctx4, {
        type: 'doughnut', // Tipe chart
        data: data, // Data dari API
        options: {
          responsive: true,
          scales: {
            x: {
              type: 'category',
              title: {
                display: true,
                text: 'Invoice Date'
              }
            },
            y: {
              ticks: {
                callback: function(value, index, values) {
                  // Format nilai sumbu Y sebagai mata uang Rupiah
                  return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                  }).format(value);
                  console.log("🚀 ~ value:", value)
                }
              }
            }
          },
        }
      });
    },
    error: function(xhr, status, error) {
      console.error('Error fetching data: ' + error);
    }
  });
</script>

