<?php 
  error_reporting(0);
  include '_header.php';
  include '_nav2.php';
  include '_sidebar2.php'; 
?>

<?php  
// Edit QTY
if( isset($_POST["update"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if ( updateQTY2($_POST) === 0 ) {
    echo "
      <script>
        alert('Anda Belum Input Nominal QTY !!!!!');
      </script>
    ";
  } elseif( updateQTY2($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = 'beli-langsung';
      </script>
    ";
  } 
  else {
    echo "
      
    ";
  }
  
}
?>
<?php 

// Insert Ke keranjang
if( isset($_POST["updateInvoice"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if( updateInvoice($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = 'penjualan';
      </script>
    ";
  } if( updateInvoice($_POST) === 0 ){
    echo "
      <script>
        document.location.href = 'penjualan';
      </script>
    ";
  } else {
    echo "
      <script>
        alert('Data Gagal Diupdate');
      </script>
    ";
  }
  
}
?>
<?php  
	// ambil data di URL
	$id = abs((int)base64_decode($_GET['no']));

	// query data mahasiswa berdasarkan id
	$invoice = query("SELECT * FROM invoice WHERE invoice_id = $id && invoice_cabang = $sessionCabang")[0];
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
				      Jika terjadi kesalahan dalam transaksi penjulan barang bisa melakukan Edit QTY barang di halaman ini. Anda <b>WAJIB MENYELESAIKAN TRANSAKSI</b> Invoice saat masuk halaman ini dengan <b>Klik Tombol Edit Payment</b>.
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
                          } else {
                            echo "- Customer Umum";
                          }
                        ?>
                    </strong>
                    <br>
                    <?php  
                        if ( $ctrId == 1 ) {
                          echo "No. Invoice Marketplace: ".$invoice['invoice_marketplace'];
                        } 
                    ?>
                    <?= $ctrAlamat; ?><br>
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
                      $tipeTransaksi = $invoice['invoice_tipe_transaksi'];

                      if ( $tipeTransaksi > 0 ) {
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
                      <th>Qty</th>
                      <th>Subtotal</th>
                      <!-- <th>Aksi</th> -->
                    </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $invoice1   = $invoice['penjualan_invoice'];
                    $total_beli = 0;
                    $total      = 0;
	                  $i          = 1; 
	                  $queryProduct = $conn->query("SELECT penjualan.penjualan_id, penjualan.barang_qty, penjualan.penjualan_invoice, penjualan.barang_qty_lama, penjualan.barang_option_sn, penjualan.barang_sn_id, penjualan.barang_sn_desc, penjualan.keranjang_harga_beli, penjualan.keranjang_harga, penjualan.barang_qty_konversi_isi, penjualan.keranjang_satuan, penjualan.penjualan_cabang, barang.barang_id, barang.barang_nama, barang.barang_harga, barang.barang_stock, barang.barang_terjual, satuan.satuan_id, satuan.satuan_nama
	                             FROM penjualan 
	                             JOIN barang ON penjualan.barang_id = barang.barang_id
                               JOIN satuan ON penjualan.keranjang_satuan = satuan.satuan_id
	                             WHERE penjualan_invoice = $invoice1 && penjualan_cabang = '".$sessionCabang."'
	                             ORDER BY penjualan_id DESC
	                             ");
	                  while ($rowProduct = mysqli_fetch_array($queryProduct)) {
                      $subTotal_beli = $rowProduct['barang_qty'] * $rowProduct['keranjang_harga_beli'];
                      $subTotal = $rowProduct['barang_qty'] * $rowProduct['keranjang_harga'];

                      $total_beli += $subTotal_beli;
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
                        <form role="form" action="" method="post">
                          <input type="hidden" name="penjualan_id" value="<?= $rowProduct['penjualan_id']; ?>">
                          <input type="number" min="0" name="barang_qty" value="<?= $rowProduct['barang_qty']; ?>" onkeypress="return hanyaAngka(event)" style="text-align: center; width: 60%;"> 
                          <input type="hidden" name="barang_qty_lama" value="<?= $rowProduct['barang_qty_lama']; ?>">


                          <input type="hidden" name="barang_id" value="<?= $rowProduct['barang_id']; ?>">
                          <input type="hidden" name="barang_stock" value="<?= $rowProduct['barang_stock']; ?>">
                          <input type="hidden" name="barang_terjual" value="<?= $rowProduct['barang_terjual']; ?>">

                          <input type="hidden" name="barang_option_sn" value="<?= $rowProduct['barang_option_sn']; ?>">
                          <input type="hidden" name="barang_sn_id" value="<?= $rowProduct['barang_sn_id']; ?>">
                          <input type="hidden" name="barang_qty_konversi_isi" value="<?= $rowProduct['barang_qty_konversi_isi']; ?>">

                          <button type="submit" name="update" class=" btn-primary" >
                              <i class="fa fa-refresh"></i>
                          </button>
                              
                        </form>
                      </td>
                      <td><?= $subTotal; ?>
                      </td>
                      <!-- <td>
                      	<a href="penjualan-delete?id=<?= $rowProduct['penjualan_id']; ?>" onclick="return confirm('Yakin dihapus ?')" title="Delete Data" class="btn btn-danger">
                            <i class="fa fa-trash-o"></i>
                        </a>
                      </td> -->
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
                <div class="col-6">
                  <div class="table-sebelumnya-box">
                    <h3><b>Data Transaksi Sebelumnya</b></h3>
                   <table class="table table-sebelumnya">
                      <tr>
                         <td><b>Sub Total</b></td>
                         <td class="table-nominal">
                            <span>Rp. </span>
                            <span>
                              <input type="text" value="<?= $invoice['invoice_total']; ?>" size="10" readonly size="10" disabled>
                            </span>
                                 
                           </td>
                      </tr>
                      <tr>
                         <td><b>Ongkir</b></td>
                         <td class="table-nominal">
                            <span>Rp. </span>
                            <span>
                              <input type="text" value="<?= $invoice['invoice_ongkir']; ?>" size="10" readonly size="10" disabled>
                            </span>
                                 
                           </td>
                      </tr>
                      <tr>
                         <td><b>Diskon</b></td>
                         <td class="table-nominal">
                            <span>Rp. </span>
                            <span>
                              <input type="text" value="<?= $invoice['invoice_diskon']; ?>" size="10" readonly size="10" disabled>
                            </span>
                                 
                           </td>
                      </tr>
                      <tr>
                         <td><b>Total</b></td>
                         <td class="table-nominal">
                            <span>Rp. </span>
                            <span>
                              <input type="text" value="<?= $invoice['invoice_sub_total']; ?>" size="10" readonly size="10" disabled>
                            </span>
                                 
                           </td>
                      </tr>
                      <tr>
                          <td><b style="color: red;">Bayar</b></td>
                          <td class="table-nominal">
                            <span>Rp.</span> 
                            <span>
                                   <input type="text" value="<?= $invoice['invoice_bayar']; ?>" size="10" readonly size="10" disabled>
                                 </span>
                          </td>
                      </tr>
                      <tr>
                          <td>Kembali</td>
                          <td class="table-nominal">
                            <span>Rp.</span>
                            <span>
                                <input type="text" value="<?= $invoice['invoice_kembali']; ?>" readonly size="10" disabled>
                            </span>
                          </td>
                        </tr>
                    </table>
                  </div>
                </div>
                <!-- /.col -->
                <div class="col-6">
                  <div class="invoice-table">
                    <form role="form" action="" method="POST">
                        <table class="table">
                          <tr>
                              <td><b>Sub Total</b></td>
                              <td class="table-nominal">
                                 <span>Rp. </span>
                                 <span>
                                    <input type="text" name="invoice_total" id="angka2" class="b2" onkeyup="hitung2();" value="<?= $total; ?>" onkeyup="return isNumberKey(event)" size="10" readonly>
                                 </span>
                                 
                              </td>
                          </tr>
                          <tr>
                             <td><b>Ongkir</b></td>
                             <td class="table-nominal">
                                <span>Rp. </span>
                                <span>
                                  <input type="text" name="invoice_ongkir" value="<?= $invoice['invoice_ongkir']; ?>" size="10" readonly>
                                </span>
                                     
                               </td>
                          </tr>
                          <tr>
                             <td><b>Diskon</b></td>
                             <td class="table-nominal">
                                <span>Rp. </span>
                                <span>
                                  <input type="text" name="invoice_diskon" value="<?= $invoice['invoice_diskon']; ?>" size="10" readonly>
                                </span>
                                     
                               </td>
                          </tr>
                          <tr>
                             <td><b>Total</b></td>
                             <td class="table-nominal">
                                <span>Rp. </span>
                                <span>
                                  <?php  
                                    $invoiceSubTotal = $invoice['invoice_ongkir'] + $total;
                                  ?>
                                  <input type="text" name="invoice_sub_total" value="<?= $invoiceSubTotal; ?>" readonly size="10">
                                </span>
                                     
                               </td>
                          </tr>
                          <tr>
                              <td><b style="color: red;">Bayar</b></td>
                              <td class="table-nominal ">
                                 <span>Rp.</span> 
                                 <span>
                                   <input type="text" name="angka1" class="a2" id="angka1" autocomplete="off" onkeyup="hitung2();" required="" value="<?= $invoice['invoice_bayar']; ?>" size="10" readonly>
                                 </span>
                              </td>
                          </tr>
                          <tr>
                              <td>Kembali</td>
                              <td class="table-nominal ">
                                <span>Rp.</span>
                                 <span>
                                  <?php 
                                    $kembali = $invoice['invoice_bayar'] - $invoiceSubTotal
                                  ?>
                                  <input type="number" name="hasil" value="<?= $kembali; ?>" readonly="">
                                </span>
                              </td>
                          </tr>
                          <tr>
                              <td>
                              </td>
                              <td>
                                <input type="hidden" name="invoice_kasir_edit" value="<?= $_SESSION['user_id']; ?>">
                                <input type="hidden" name="invoice_id" value="<?= $id; ?>">
                                <input type="hidden" name="invoice_total_beli" value="<?= $total_beli; ?>">

                                <div class="payment">
                                  <button class="btn btn-success" type="submit" name="updateInvoice">Edit Payment <i class="fa fa-shopping-cart"></i></button>
                                </div>
                              </td>
                          </tr>
                        </table>
                    </form>
                  </div>
                </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->

              <!-- this row will not appear when printing -->
              <!-- <div class="row no-print">
                <div class="col-12">
                  <button class="btn btn-success float-right" type="submit" name="EditInvoice">Edit Invoice</button>
                  <a href="penjualan" class="btn btn-default float-right" style="margin-right: 5px;"> Kembali</a>
                </div>
              </div> -->
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

<script>
  $(".nav-link").on("click", function (){
      alert("Anda WAJIB MENYELESAIKAN Edit Invoice !!!");
  });
</script>
