<?php 
  include '_header.php';
?>
<?php  
  if ( $levelLogin === "kasir") {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }

  $input_barcode  = htmlspecialchars($_POST['input_barcode']);
  $input_kode     = htmlspecialchars($_POST['input_kode']);

  $barang = mysqli_query( $conn, "SELECT barang_nama, barang_harga FROM barang WHERE barang_kode = '".$input_kode."'");
  $ns = mysqli_fetch_array($barang); 
  $barang_nama  = $ns["barang_nama"];
  $barang_harga = number_format($ns["barang_harga"], 0, ',', '.');
 
?>


  <section class="detail-barcode">
      <div class="container">
          <br><br><br>
          <div class="text-center">
              <h3>Barcode Produk <b><?= $barang_nama; ?></b> Kode <b><?= $input_kode; ?></b></h3>
          </div>
          <br><br>

          <div class="row" style="margin-left: 20px;">
              <?php  
                for ( $i = 1; $i <= $input_barcode; $i++ ) {
                  echo '
                      <div class="col-6" style="transform: scale(1.2); margin-bottom: 15px;">
                          <div class="detail-barcode-box text-center" id="detail-barcode-box">
                          
                            <span class="title-barcode-box" style="font-size: 18px;">'.$barang_nama.'</span><br>
                            <b class="title-barcode-box" style="font-size: 18px;">'. $input_kode.'</b><br>
                            <div class="row justify-content-center">
                              <div class="col-auto">
                                <b>Rp</b>
                              </div>

                              <div class="col-auto">
                                <b style="font-size: 25px;">'.$barang_harga.'</b>
                              </div>
                            </div>
                            
                          </div><br>
                      </div>
                  ';
                }
              ?>
          </div>
      </div>
  </section>
  <script>
    window.print();
  </script>
