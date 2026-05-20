<?php 
  include '_header.php';
  include '_nav2.php';
  include '_sidebar2.php'; 
?>

<?php  
// Edit QTY
if( isset($_POST["update"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if ( updateQTY2pembelian($_POST) === 0 ) {
    echo "
      <script>
        alert('Anda Belum Input Nominal QTY !!!!!');
      </script>
    ";
  } elseif( updateQTY2pembelian($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = 'transaksi-pembelian';
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
  if( updateInvoicePembelian($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = 'pembelian';
      </script>
    ";
  } if( updateInvoicePembelian($_POST) === 0 ){
    echo "
      <script>
        document.location.href = 'pembelian';
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
	$invoice = query("SELECT * FROM invoice_pembelian WHERE invoice_pembelian_id = $id && invoice_pembelian_cabang = $sessionCabang ")[0];
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
				      Jika terjadi kesalahan dalam transaksi penjulan barang bisa melakukan Edit QTY di halaman ini. Anda <b>WAJIB MENYELESAIKAN TRANSAKSI</b> Invoice saat masuk halaman ini
            </div>


            <!-- Main content -->
            <div class="invoice p-3 mb-3">
              <!-- title row -->
              <div class="row">
                <div class="col-12">
                  <h4>
                    <i class="fas fa-globe"></i> N0. Invoice: <?= $invoice['pembelian_invoice']; ?>
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
                  <table class="table table-striped">
                    <thead>
                    <tr>
                      <th>No</th>
                      <th>Nama Barang</th>
                      <th>Harga Beli</th>
                      <th>Qty</th>
                      <th>Subtotal</th>
                      <!-- <th>Aksi</th> -->
                    </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $invoice1 = $invoice['pembelian_invoice_parent'];
                    $total = 0;
	                  $i = 1; 
	                  $queryProduct = $conn->query("SELECT pembelian.pembelian_id, barang.barang_id, barang.barang_nama, pembelian.barang_harga_beli, barang.barang_stock, pembelian.barang_qty, pembelian.pembelian_invoice, pembelian.pembelian_invoice_parent, pembelian.barang_qty_lama, pembelian.pembelian_cabang
	                             FROM pembelian 
	                             JOIN barang ON pembelian.barang_id = barang.barang_id
	                             WHERE pembelian_invoice_parent = $invoice1 && pembelian_cabang = '".$sessionCabang."'
	                             ORDER BY pembelian_id DESC
	                             ");
	                  while ($rowProduct = mysqli_fetch_array($queryProduct)) {
                      $subTotal = $rowProduct['barang_qty'] * $rowProduct['barang_harga_beli'];
                      $total += $subTotal;
	                ?>
	                
                    <tr>
                      <td><?= $i; ?></td>
                      <td><?= $rowProduct['barang_nama']; ?></td>
                      <td><?= $rowProduct['barang_harga_beli']; ?></td>
                      <td style="text-align: center; width: 11%;">
                        <form role="form" action="" method="post">
                          <input type="hidden" name="pembelian_id" value="<?= $rowProduct['pembelian_id']; ?>">
                          <input type="number" min="0" name="barang_qty" value="<?= $rowProduct['barang_qty']; ?>" onkeypress="return hanyaAngka(event)" style="text-align: center; width: 60%;"> 
                          <input type="hidden" name="barang_qty_lama" value="<?= $rowProduct['barang_qty_lama']; ?>">


                          <input type="hidden" name="barang_id" value="<?= $rowProduct['barang_id']; ?>">
                          <input type="hidden" name="barang_stock" value="<?= $rowProduct['barang_stock']; ?>">

                         
                            <button type="submit" name="update" class=" btn-primary" >
                              <i class="fa fa-refresh"></i>
                            </button>
                              
                        </form>
                      </td>
                      <td><?= $subTotal; ?>
                      </td>
                      <!-- <td>
                      	<a href="pembelian-delete?id=<?= $rowProduct['pembelian_id']; ?>" onclick="return confirm('Yakin dihapus ?')" title="Delete Data" class="btn btn-danger">
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
                         <td><b>Total</b></td>
                         <td class="table-nominal">
                                 <!-- Rp. <?php echo number_format($total, 0, ',', '.'); ?> -->
                            <span>Rp. </span>
                            <span>
                              <input type="text" value="<?= $invoice['invoice_total']; ?>" size="10" readonly size="10" disabled>
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
                  <!-- <div class="table-responsive">

                    <table class="table">
                      <tr>
                        <th style="width:50%">Total:</th>
                        <td>Rp. <?= number_format($total, 0, ',', '.'); ?></td>
                      </tr>
                      <tr>
                        <th>Bayar</th>
                        <td>Rp. <?= number_format($invoice['invoice_bayar'], 0, ',', '.'); ?></td>
                      </tr>
                      <tr>
                        <th>Uang Kembali</th>
                        <td>Rp. <?= number_format($invoice['invoice_kembali'], 0, ',', '.'); ?></td>
                      </tr>
                    </table>
                  </div> -->
                  <div class="invoice-table">
                    <form role="form" action="" method="POST">
                        <table class="table">
                          <tr>
                              <td><b>Total</b></td>
                              <td class="table-nominal">
                                 <!-- Rp. <?php echo number_format($total, 0, ',', '.'); ?> -->
                                 <span>Rp. </span>
                                 <span>
                                    <input type="text" name="invoice_total" id="angka2" class="b2" onkeyup="hitung2();" value="<?= $total; ?>" onkeyup="return isNumberKey(event)" size="10" readonly>
                                 </span>
                                 
                              </td>
                          </tr>
                          <tr>
                              <td><b style="color: red;">Bayar</b></td>
                              <td class="table-nominal tn td-edit-transaksi">
                                 <span>Rp.</span> 
                                 <span>
                                   <input type="text" name="angka1" class="a2" id="angka1" autocomplete="off" onkeyup="hitung2();" required="" onkeyup="return isNumberKey(event)" onkeypress="return hanyaAngka1(event)" size="10">
                                 </span>
                              </td>
                          </tr>
                          <tr>
                              <td>Kembali</td>
                              <td class="table-nominal">
                                <span>Rp.</span>
                                 <span>
                                  <input type="text" name="hasil" id="hasil" class="c2" readonly size="10" disabled>
                                </span>
                              </td>
                          </tr>
                          <tr>
                              <td>
                              </td>
                              <td>
                                <input type="hidden" name="invoice_kasir_edit" value="<?= $_SESSION['user_id']; ?>">
                                <input type="hidden" name="invoice_pembelian_id" value="<?= $id; ?>">
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
                  <a href="pembelian" class="btn btn-default float-right" style="margin-right: 5px;"> Kembali</a>
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
