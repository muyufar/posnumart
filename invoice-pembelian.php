<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>

<?php  
  // ambil data di URL (pakai string agar no panjang seperti 202602044219160 tidak overflow)
  $id = isset($_GET['no']) ? trim($_GET['no']) : '';

  if ( $id === '' ) {
    header("location: pembelian");
    exit;
  }
  $id_esc = mysqli_real_escape_string($conn, $id);

  // query data invoice berdasarkan pembelian_invoice_parent (no = id transaksi)
  $invoice = query("SELECT * FROM invoice_pembelian WHERE pembelian_invoice_parent = '$id_esc' AND invoice_pembelian_cabang = $sessionCabang LIMIT 1")[0] ?? null;
  $noInvoice = $invoice ? $invoice['pembelian_invoice'] : null;

  if ( $invoice == null ) {
    header("location: pembelian");
    exit;
  }
?>

	<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Invoice Pembelian</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Invoice</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="callout callout-info">
              <h5><i class="fas fa-info"></i> Note:</h5>
				Halaman ini telah ditingkatkan untuk dicetak. Klik tombol cetak di bagian bawah faktur.
            </div>


            <!-- Main content -->
            <div class="invoice p-3 mb-3">
              <!-- title row -->
              <div class="row">
                <div class="col-12">
                  <h4>
                    <i class="fas fa-globe"></i> N0. Invoice: <?= $noInvoice; ?>
                    <small class="float-right">Tanggal: <?= $invoice['invoice_tgl']; ?></small>
                  </h4>
                </div>
                <!-- /.col -->
              </div>
              <!-- info row -->
              <?php  
                  $toko = query("SELECT * FROM toko WHERE toko_cabang = $sessionCabang");
              ?>
              <?php foreach ( $toko as $row ) : ?>
                  <?php 
                    $toko_nama   = $row['toko_nama'];
                    $toko_kota   = $row['toko_kota'];
                    $toko_tlpn   = $row['toko_tlpn'];
                    $toko_wa     = $row['toko_wa']; 
                    $toko_email  = $row['toko_email'];
                    $toko_alamat = $row['toko_alamat'];
                  ?>
              <?php endforeach; ?>
              <div class="row invoice-info">
                <div class="col-sm-4 invoice-col">
                  Dari
                  <address>
                    <strong><?= $toko_nama; ?></strong><br>
                    <?= $toko_alamat; ?><br>
                    Tlpn/wa: <?= $toko_tlpn; ?> / <?= $toko_wa; ?><br>
                    Email: <?= $toko_email; ?><br>

                    <?php  
                    	$kasir = $invoice['invoice_kasir'];
                    	$dataKasir = query("SELECT * FROM user WHERE user_id = $kasir");
                    ?>
                    <?php foreach ( $dataKasir as $ksr ) : ?>
                    	<?php $ksrDetail = $ksr['user_nama']; ?>
                    <?php endforeach; ?>

                    <b>Kasir: </b><?= $ksrDetail; ?>
                  </address>
                </div>
                <!-- /.col -->

                <div class="col-sm-4 invoice-col">
                  Supplier
                  <address>
                  	<?php  
                    	$supplier = $invoice['invoice_supplier'];
                    	$dataSupplier = query("SELECT * FROM supplier WHERE supplier_id = $supplier");
                    ?>
                    <?php foreach ( $dataSupplier as $ctr ) : ?>
                    	<?php 
                    		$supplier_nama   = $ctr['supplier_nama']; 
                    		$supplier_alamat = $ctr['supplier_alamat'];
                    		$supplier_company  = $ctr['supplier_company'];
                    		$supplier_wa   = $ctr['supplier_wa'];
                    	?>
                    <?php endforeach; ?>

                    <strong><?= $supplier_nama; ?></strong><br>
                    <?= $supplier_alamat; ?><br>
                    Tlpn/wa:
                    	<?php  
                    		if ( $supplier_wa == null ) {
                    			echo "-";
                    		} else {
                    			echo $supplier_wa;
                    		}
                    	?>

                    <br>
                    <b>Perusahaan:</b> 
                    	<?php  
                    		if ( $supplier_company == null ) {
                    			echo"-";
                    		} else {
                    			echo "<b>".$supplier_company."</b>";
                    		}
                    	?>
                  </address>
                </div>

                <!-- /.col -->
                <div class="col-sm-4 invoice-col">
                </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->

              <!-- Table row -->
              <div class="row">
                <div class="col-12 table-responsive">
                  <div class="table-auto">
                    <table class="table table-striped">
                      <thead>
                      <tr>
                        <th>No</th>
                        <th>Nama Barang</th>
                        <th>Harga Beli</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                      </tr>
                      </thead>
                      <tbody>
                      <?php 
                        // Data barang di invoice = dari transaksi (tabel pembelian): harga beli & qty terbaru dari transaksi
  	                  $i = 1;
  	                  $total_dari_transaksi = 0;
  	                  $queryProduct = $conn->query("SELECT pembelian.pembelian_id, barang.barang_id, barang.barang_nama, pembelian.barang_harga_beli AS harga_beli_transaksi, barang.barang_harga_beli AS harga_beli_barang, pembelian.barang_qty, pembelian.pembelian_invoice, pembelian.pembelian_invoice_parent, pembelian.pembelian_cabang
  	                             FROM pembelian 
  	                             JOIN barang ON pembelian.barang_id = barang.barang_id
  	                             WHERE pembelian.pembelian_invoice_parent = '".$id_esc."' AND pembelian.pembelian_cabang = '".$sessionCabang."'
  	                             ORDER BY pembelian.pembelian_id ASC
  	                             ");
  	                  while ($rowProduct = mysqli_fetch_array($queryProduct)) {
  	                    // Harga beli & qty dari transaksi (pembelian)
  	                    $harga_beli = (float)$rowProduct['harga_beli_transaksi'];
  	                    if ($harga_beli <= 0) {
  	                      $harga_beli = (float)$rowProduct['harga_beli_barang'];
  	                    }
  	                    $qty = (float)$rowProduct['barang_qty'];
  	                    $subTotal = round($harga_beli * $qty, 1);
  	                    $total_dari_transaksi += $subTotal;
  	                ?>
  	                
                      <tr>
                        <td><?= $i; ?></td>
                        <td><?= $rowProduct['barang_nama']; ?></td>
                        <td>Rp. <?= number_format($harga_beli, 1, ',', '.'); ?></td>
                        <td><?= number_format($qty, 1, ',', '.'); ?></td>
                        <td>Rp. <?= number_format($subTotal, 1, ',', '.'); ?></td>
                      </tr>
                      <?php $i++; ?>
                  	<?php } ?>
                      </tbody>
                    </table>
                  </div>
                </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->

              <div class="row">
                <!-- accepted payments column -->
                <div class="col-6">
                </div>
                <!-- /.col -->
                <div class="col-6">
                  <div class="table-responsive">
                    <table class="table">
                      <tr>
                        <th style="width:50%">Total:</th>
                        <td>Rp. <?= number_format(isset($total_dari_transaksi) ? $total_dari_transaksi : (float)$invoice['invoice_total'], 1, ',', '.'); ?></td>
                      </tr>
                      <tr>
                        <th>
                          <?php  
                            $tipeTransaksi = $invoice['invoice_hutang'];
                            if ( $tipeTransaksi < 1 ) {
                              echo "Bayar";
                            } else {
                              echo "DP";
                            }
                          ?>
                        </th>
                        <td>Rp. <?= number_format((float)$invoice['invoice_bayar'], 1, ',', '.'); ?></td>
                      </tr>
                      <tr>
                        <th>
                          <?php  
                            $tipeTransaksi = $invoice['invoice_hutang'];
                            if ( $tipeTransaksi < 1 ) {
                              echo "Uang Kembali";
                            } else {
                              echo "Sisa Hutang";
                            }
                          ?>
                        </th>
                        <td>Rp. <?= number_format((float)$invoice['invoice_kembali'], 1, ',', '.'); ?></td>
                      </tr>
                    </table>
                  </div>
                </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->

              <!-- this row will not appear when printing -->
              <div class="row no-print">
                <div class="col-12">
                  
                  <a href="nota-cetak-pembelian?no=<?= $invoice['invoice_pembelian_id']; ?>-invoice-<?= $id; ?>" target="_blank" class="btn btn-primary float-right"><i class="fas fa-print"></i> Print Invoice</a>
                  <a href="transaksi-pembelian" class="btn btn-default float-right" style="margin-right: 5px;"> Kembali Transaksi</a>
                </div>
              </div>
            </div>
            <!-- /.invoice -->
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
  </div>

<?php include '_footer.php'; ?>