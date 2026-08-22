<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>

<?php  
	// ambil data di URL
	$id = abs((int)base64_decode($_GET['no']));

	// query data mahasiswa berdasarkan id
	$invoice = query("SELECT * FROM invoice WHERE invoice_id = $id && invoice_cabang = $sessionCabang ")[0];
  $tipeTransaksi  = $invoice['invoice_piutang'];
  $tipePembayaran = $invoice['invoice_tipe_transaksi'];
?>
	<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Invoice</h1>
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
				      Data detail Transaksi dengan N0. Invoice: <?= $invoice['penjualan_invoice']; ?>
            </div>


            <!-- Main content -->
            <div class="invoice p-3 mb-3">
              <!-- title row -->
              <div class="row">
                <div class="col-12">
                  <h4>
                    <i class="fas fa-globe"></i> N0. Invoice: <?= $invoice['penjualan_invoice']; ?>
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
                  Pembeli
                  <address>
                  	<?php  
                    	$customer = $invoice['invoice_customer'];
                    	$dataCustomer = query("SELECT * FROM customer WHERE customer_id = $customer");
                    ?>
                    <?php foreach ( $dataCustomer as $ctr ) : ?>
                      <?php 
                        $ctrId        = $ctr['customer_id']; 
                        $ctrNama      = $ctr['customer_nama']; 
                        $ctrAlamat    = $ctr['customer_alamat'];
                        $ctrEmail     = $ctr['customer_email'];
                        $ctrTlpn      = $ctr['customer_tlpn'];
                        $ctrCategory  = $ctr['customer_category'];
                      ?>
                    <?php endforeach; ?>

                    <strong>
                        <?= $ctrNama; ?> 
                        <?php  
                          if ( $ctrCategory == 1 ) {
                            echo "- Customer Grosir 1";
                          } elseif ( $ctrCategory == 2 ) {
                            echo "- Customer Grosir 2";
                          } 
                        ?>
                    </strong>
                    <br>
                    <?php  
                        if ( $ctrId == 1 ) {
                          echo "No. Invoice Marketplace: ".$invoice['invoice_marketplace'];
                        } 
                    ?>

                    <?php  
                      $alamatCustomer = str_replace(" ", "+", $ctrAlamat);
                    ?>
                    <a href="https://www.google.com/maps/search/<?= $alamatCustomer; ?>" target="_blank">
                        <?= $ctrAlamat; ?>
                    </a>
                    <br>

                    Tlpn/wa:
                    	<?php  
                    		if ( $ctrTlpn == null ) {
                    			echo"-";
                    		} else {
                    			echo($ctrTlpn);
                    		}
                    	?>

                    <br>
                    Email: 
                    	<?php  
                    		if ( $ctrEmail == null ) {
                    			echo"-";
                    		} else {
                    			echo($ctrEmail);
                    		}
                    	?>

                    <br>
                    <b>Nama Kurir: </b>
                    <?php  
                      $kurir = $invoice['invoice_kurir'];

                      if ( $kurir > 0 ) {
                        $dataKurir = query("SELECT * FROM user WHERE user_id = $kurir")[0];
                        echo $dataKurir['user_nama'];
                      } else {
                        echo "-";
                      }
                      
                    ?>

                    <br>
                    <b>Tipe Transaksi: </b>
                    <?php  
                      if ( $tipePembayaran > 0 ) {
                        echo "Transfer";
                      } else {
                        echo "Cash";
                      }
                    ?>
                  </address>
                </div>
                <!-- /.col -->
                <div class="col-sm-4 invoice-col">
                    <?php if ( $ctrId == 1 ) { ?>
                    <h4><b>Ekspedisi & No. Resi</b></h4>
                      <?php  
                        $ekspedisi = $invoice['invoice_ekspedisi'];

                        $ekspedisiData = mysqli_query($conn, "select ekspedisi_nama from ekspedisi where ekspedisi_id = $ekspedisi ");
                        $ed = mysqli_fetch_array($ekspedisiData);
                        $ed = $ed['ekspedisi_nama'];
                      ?>
                      Ekspedisi: <?= $ed; ?><br>
                      No. Resi: <?= $invoice['invoice_no_resi']; ?>
                    <?php } ?>

                </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->

              <!-- Table row -->
              <div class="row">
                <div class="col-12 table-responsive">
                  <table class="table table-striped">
                    <thead>
                    <tr>
                      <th>No</th>
                      <th>Nama Barang</th>
                      <th>Satuan</th>
                      <th>Harga</th>
                      <th style="text-align: center;">Qty</th>
                      <th>Subtotal</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $invoice1 = $invoice['penjualan_invoice'];
                    $total = 0;
	                  $i = 1; 
	                  $queryProduct = $conn->query("SELECT penjualan.penjualan_id, penjualan.barang_qty, penjualan.penjualan_invoice, penjualan.barang_option_sn, penjualan.barang_sn_desc, penjualan.penjualan_cabang, penjualan.keranjang_harga, penjualan.keranjang_satuan, barang.barang_id, barang.barang_nama, barang.barang_stock, satuan.satuan_id, satuan.satuan_nama
	                             FROM penjualan 
	                             JOIN barang ON penjualan.barang_id = barang.barang_id
                               JOIN satuan ON penjualan.keranjang_satuan = satuan.satuan_id
	                             WHERE penjualan_invoice = $invoice1 && penjualan_cabang = '".$sessionCabang."'
	                             ORDER BY penjualan_id DESC
	                             ");
	                  while ($rowProduct = mysqli_fetch_array($queryProduct)) {
                      $subTotal = $rowProduct['barang_qty'] * $rowProduct['keranjang_harga'];
                      $total += $subTotal;
	                ?>
	                
                    <tr>
                      <td><?= $i; ?></td>
                      <td>
                            <?= $rowProduct['barang_nama']; ?><br>
                            <?php if ( $rowProduct['barang_option_sn'] > 0 ) { ?>  
                            <small>No. SN: <?= $rowProduct['barang_sn_desc']; ?></small>
                            <?php } ?>
                      </td>
                      <td><?= $rowProduct['satuan_nama']; ?></td>
                      <td><?= $rowProduct['keranjang_harga']; ?></td>
                      <td style="text-align: center; width: 11%;">
                        <?= $rowProduct['barang_qty']; ?>
                      </td>
                      <td><?= $subTotal; ?>
                      </td>
                    </tr>
                    <?php $i++; ?>
                	<?php } ?>
                    </tbody>
                  </table>
                </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->

              <div class="row">
                <!-- accepted payments column -->
                <div class="col-md-6 col-2">
                  
                </div>
                <!-- /.col -->
                <div class="col-md-6 col-10">
                  <div class="table-responsive">

                    <table class="table">
                      <tr>
                        <th style="width:50%">Sub Total:</th>
                        <td>Rp. <?= number_format($total, 0, ',', '.'); ?></td>
                      </tr>
                      <tr>
                        <th>Ongkir</th>
                        <td>Rp. <?= number_format($invoice['invoice_ongkir'], 0, ',', '.'); ?></td>
                      </tr>
                      <tr>
                        <th>Diskon</th>
                        <td>Rp. <?= number_format($invoice['invoice_diskon'], 0, ',', '.'); ?></td>
                      </tr>
                      <tr>
                        <th>Total</th>
                        <td>Rp. <?= number_format($invoice['invoice_sub_total'], 0, ',', '.'); ?></td>
                      </tr>

                      <?php if ( $tipeTransaksi == 1 ) { ?>
                      <tr>
                        <th>DP</th>
                        <td>Rp. <?= number_format($invoice['invoice_piutang_dp'], 0, ',', '.'); ?></td>
                      </tr>
                      <?php } ?>

                      <tr>
                        <th>
                          <?php 
                            if ( $tipeTransaksi < 1 ) {
                              echo "Bayar";
                            } else {
                              echo "DP + Cicilan";
                            }
                          ?>
                        </th>
                        <td>Rp. <?= number_format($invoice['invoice_bayar'], 0, ',', '.'); ?></td>
                      </tr>
                      <tr>
                        <th>
                          <?php  
                            if ( $tipeTransaksi < 1 ) {
                              echo "Uang Kembali";
                            } else {
                              echo "Sisa Piutang";
                            }
                          ?>
                        </th>
                        <td>Rp. <?= number_format($invoice['invoice_kembali'], 0, ',', '.'); ?></td>
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
                  <a href="#!" class="btn btn-success float-right" onclick="self.close()" style="margin-right: 5px;"> Kembali</a>
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


