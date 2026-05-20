<?php 
  error_reporting(0);
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>

<?php  
  $userId = $_SESSION['user_id'];
  $tipeHarga = base64_decode($_GET['customer']);
  $invoiceDraft = base64_decode($_GET['invoice']);

  $invoiceDataDraft = query("SELECT * FROM invoice WHERE penjualan_invoice = $invoiceDraft && invoice_cabang = $sessionCabang")[0];
  if ( $invoiceDataDraft == null ) {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }

  if ( $tipeHarga == 1 ) {
      $nameTipeHarga = "Grosir";
  } elseif ( $tipeHarga == 2 ) {
      $nameTipeHarga = "KH";
  } else {
      $nameTipeHarga = "Umum";
  }

  if ( $levelLogin === "kurir") {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }  


if ( $dataTokoLogin['toko_status'] < 1 ) {
  echo "
      <script>
        alert('Status Toko Tidak Aktif Jadi Anda Tidak Bisa melakukan Transaksi !!');
        document.location.href = 'bo';
      </script>
    ";
}



// Insert Ke keranjang Scan Barcode
if( isset($_POST["inputbarcodeDraft"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if( tambahKeranjangBarcodeDraft($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = '';
      </script>
    ";
  }  
}
?>



<?php 
error_reporting(0);
// Insert Ke keranjang
$inv = $_POST["penjualan_invoice2"];
if( isset($_POST["updateStock"]) ){
  // var_dump($_POST);
 
  if( updateStockSaveDraft($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = 'invoice?no=".$inv."';
      </script>
    ";
  } else {
    echo "
      <script>
        alert('Transaksi Gagal !!');
      </script>
    ";
  } 
}
?>




<?php
  // Update Data Produk SN dan Non SN 
  if ( isset($_POST["updateSn"]) ) {
    if( updateSnDrfat($_POST) > 0 ) {
      echo "
        <script>
          document.location.href = '';
        </script>
      ";
    } else {
      echo "
        <script>
          alert('Data Gagal edit');
        </script>
      ";
    }
  }

  // Update Data Harga Produk di Keranjang
  if ( isset($_POST["updateHarga"]) ) {
    if( updateQTYHargaDraft($_POST) > 0 ) {
      echo "
        <script>
          document.location.href = '';
        </script>
      ";
    } else {
      echo "
        <script>
          alert('Data Gagal edit');
        </script>
      ";
    }
  }
?>

<!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-8">
            <h1>Transaksi Kasir <b style="color: #007bff; ">Customer <?= $nameTipeHarga; ?></b></h1>
            <div class="btn-cash-piutang">
              <?php  
                // Ambil data dari URL Untuk memberikan kondisi transaksi Cash atau Piutang
                if (empty(abs((int)base64_decode($_GET['r'])))) {
                    $r = 0;
                } else {
                    $r = abs((int)base64_decode($_GET['r']));
                }
              ?>

              <?php if ( $r == 1 ) : ?>
              <a href="beli-langsung?customer=<?= $_GET['customer']; ?>" class="btn btn-default">Cash</a>
              <a href="beli-langsung?customer=<?= $_GET['customer']; ?>&r=MQ==" class="btn btn-primary">Piutang</a>
              <?php else : ?>
              <a href="beli-langsung?customer=<?= $_GET['customer']; ?>" class="btn btn-primary">Cash</a>
              <a href="beli-langsung?customer=<?= $_GET['customer']; ?>&r=MQ==" class="btn btn-default">Piutang</a>
              <?php endif; ?>
              <a class="btn btn-danger" data-toggle="modal" href='#modal-id-draft' data-backdrop="static">Pending</a>
              <div class="modal fade" id="modal-id-draft">
                <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h4 class="modal-title">Data Transaksi Pending</h4>
                      <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    </div>
                    <div class="modal-body">
                      <?php  
                          $draft = query("SELECT * FROM invoice WHERE invoice_draft = 1 && invoice_kasir = $userId && invoice_cabang = $sessionCabang ORDER BY invoice_id DESC");
                      ?>
                      <div class="table-auto">
                        <table id="example7" class="table table-bordered table-striped">
                          <thead>
                          <tr>
                            <th style="width: 5px;">No.</th>
                            <th>Invoice</th>
                            <th style="width: 40% !important;">Tanggal</th>
                            <th>Customer</th>
                            <th class="text-center" style="width: 5%;">Aksi</th>
                          </tr>
                          </thead>
                          <tbody>

                          <?php $i = 1; ?>
                          <?php foreach ( $draft as $row ) : ?>
                          <tr>
                              <td><?= $i; ?></td>
                              <td><?= $row['penjualan_invoice']; ?></td>
                              <td><?= tanggal_indo($row['invoice_tgl']); ?></td>
                              <td>
                                  <?php 
                                    $customer_id_draft = $row['invoice_customer']; 
                                    $namaCustomerDraft = mysqli_query($conn, "SELECT customer_nama FROM customer WHERE customer_id = $customer_id_draft");
                                    $namaCustomerDraft = mysqli_fetch_array($namaCustomerDraft);
                                    $customer_nama_draft = $namaCustomerDraft['customer_nama'];

                                    if ( $customer_id_draft < 1 ) {
                                      echo "Customer Umum";
                                    } else {
                                      echo $customer_nama_draft;
                                    }
                                  ?> 
                              </td>
                              <td class="orderan-online-button">
                                <a href="beli-langsung-draft?customer=<?= base64_encode($row['invoice_customer_category']); ?>&r=<?= base64_encode($row['invoice_piutang']); ?>&invoice=<?= base64_encode($row['penjualan_invoice']); ?>" title="Edit Data">
                                      <button class="btn btn-primary" type="submit">
                                         <i class="fa fa-edit"></i>
                                      </button>
                                  </a>
                                  <a href="beli-langsung-draft-delete?invoice=<?= $row['penjualan_invoice']; ?>&customer=<?= $_GET['customer']; ?>&cabang=<?= $sessionCabang; ?>" onclick="return confirm('Yakin dihapus ?')" title="Delete Data">
                                      <button class="btn btn-danger" type="submit">
                                         <i class="fa fa-trash"></i>
                                      </button>
                                  </a>
                              </td>
                          </tr>
                          <?php $i++; ?>
                        <?php endforeach; ?>
                        </tbody>
                        </table>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-4">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Barang</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>


    <section class="content">
    <?php  
      $userId = $_SESSION['user_id'];
      $keranjang = query("SELECT * FROM keranjang_draft WHERE keranjang_tipe_customer = $tipeHarga && keranjang_invoice = $invoiceDraft && keranjang_cabang = $sessionCabang ORDER BY keranjang_draf_id ASC");

    ?>
        <div class="col-lg-12">
          <div class="card">
            <div class="card-header">
              <div class="row">
                <div class="col-md-8 col-lg-8">
                    <div class="card-invoice">
                      <span>No. Invoice: </span>
                      <?php  
                        $di = $invoiceDraft;
                      ?>
                      <input type="" name="" value="<?= $di; ?>">
                    </div>
                </div>
                <div class="col-md-4 col-lg-4">
                    <div class="cari-barang-parent">
                      <div class="row">
                        <div class="col-10">
                            <form action="" method="post">
                                <input type="hidden" name="keranjang_id_kasir" value="<?= $userId; ?>">
                                <input type="hidden" name="keranjang_cabang" value="<?= $sessionCabang; ?>">
                                <input type="hidden" name="tipe_harga" value="<?= $tipeHarga; ?>">
                                <input type="hidden" name="keranjang_invoice" value="<?= $di; ?>">
                                <input type="text" class="form-control" autofocus="" name="inputbarcodeDraft" placeholder="Barcode / Kode Barang" required="">
                            </form>
                        </div>
                        <div class="col-2">
                            <a class="btn btn-primary" title="Cari Produk" data-toggle="modal" id="cari-barang" href='#modal-id'>
                               <i class="fa fa-search"></i>
                            </a>
                        </div>
                      </div>
                    </div>
                </div>
                </div>
              </div>

            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
                <table id="" class="table table-bordered table-striped">
                <thead>
                <tr>
                  <th style="width: 6%;">No.</th>
                  <th>Nama</th>
                  <th>Harga</th>
                  <th>Satuan</th>
                  <th style="text-align: center;">QTY</th>
                  <th>No. SN</th>
                  <th style="width: 20%;">Sub Total</th>
                  <th style="text-align: center; width: 10%;">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php 
                  $i          = 1; 
                  $total_beli = 0;
                  $total      = 0;
                ?>
                <?php 
                  foreach($keranjang as $row) : 

                  $bik = $row['barang_id'];
                  $stockParent = mysqli_query( $conn, "select barang_stock, satuan_isi_1, satuan_isi_2, satuan_isi_3 from barang where barang_id = '".$bik."'");
                  $brg = mysqli_fetch_array($stockParent); 
                  $tb_brg       = $brg['barang_stock'];

                  $sub_total_beli = +$row['keranjang_harga_beli'] * $row['keranjang_qty_view'];
                  $sub_total      = +$row['keranjang_harga'] * $row['keranjang_qty_view'];
        
                  if ( $row['keranjang_id_kasir'] === $_SESSION['user_id'] ) {
                  $total_beli += $sub_total_beli;
                  $total += $sub_total;
                ?>
                <tr>
                    <td><?= $i; ?></td>
                    <td><?= $row['keranjang_nama'] ?></td>
                    <td>Rp. <?= number_format($row['keranjang_harga'], 0, ',', '.'); ?></td>
                    <td>
                      <?php  
                        $satuan = $row['keranjang_satuan'];
                        $dataSatuan = mysqli_query($conn, "select satuan_nama from satuan where satuan_id = ".$satuan." ");
                        $dataSatuan = mysqli_fetch_array($dataSatuan);
                        $dataSatuan = $dataSatuan['satuan_nama'];
                        echo $dataSatuan;
                      ?>
                    </td>
                    <td style="text-align: center;"><?= $row['keranjang_qty_view']; ?></td>
                    <td>
                      <?php  
                        if ( $row['keranjang_barang_option_sn'] < 1 ) {
                          $sn = "Non-SN";
                        } else {
                            $sn = $row['keranjang_sn'];
                            if ( $row['keranjang_sn'] == null ) {
                              echo '
                                <span class="keranjang-right">
                                  <button class=" btn-success" name="" class="keranjang-pembelian"    id="keranjang_sn" data-id="'.$row['keranjang_id'].'">
                                   <i class="fa fa-edit"></i>
                                  </button> 
                                </span>';
                            } elseif ( $row['keranjang_sn'] === "0" ) {
                              echo '
                                <span class="keranjang-right">
                                  <button class=" btn-success" name="" class="keranjang-pembelian"    id="keranjang_sn" data-id="'.$row['keranjang_id'].'">
                                   <i class="fa fa-edit"></i>
                                  </button> 
                                </span>';
                            } 
                        }
                        echo $sn;
                      ?>
                    </td>
                    <td>Rp. <?= number_format($sub_total, 0, ',', '.'); ?></td>
                    <td class="orderan-online-button">
                        <a href="#!" title="Edit Data">
                          <button class="btn btn-primary" name="" class="keranjang-pembelian" id="keranjang-harga" data-id="<?= $row['keranjang_draf_id']; ?>">
                              <i class="fa fa-pencil"></i>
                          </button> 
                        </a>
                        <a href="beli-langsung-delete-draft?id=<?= $row['keranjang_draf_id']; ?>&customer=<?= $_GET['customer']; ?>&r=<?= $r; ?>&invoice=<?= $_GET['invoice']; ?>" title="Delete Data" onclick="return confirm('Yakin dihapus ?')">
                            <button class="btn btn-danger" type="submit" name="hapus">
                                <i class="fa fa-trash-o"></i>
                            </button>
                        </a>
                    </td>
                </tr>
                <?php $i++; ?>
                <?php } ?>
                <?php endforeach; ?>
              </table>
              </div>
              
       
              <div class="btn-transaksi">
                <form role="form" action="" method="POST">
                  <div class="row">
                    <div class="col-md-6 col-lg-7">
                        <div class="filter-customer">
                          <div class="form-group">
                            <label>Customer <b style="color: #007bff; "><?= $nameTipeHarga; ?></b></label>
                            <?php  
                              $idCustomerDraft = $invoiceDataDraft['invoice_customer'];
                              $namaCustomer = mysqli_query($conn, "SELECT customer_nama FROM customer WHERE customer_id = $idCustomerDraft ");
                              $namaCustomer = mysqli_fetch_array($namaCustomer);
                              $invoice_customer = $namaCustomer['customer_nama'];
                            ?>
                            <select class="form-control select2bs4 pilihan-marketplace" required="" name="invoice_customer">
                              <option selected="selected" value="<?= $idCustomerDraft; ?>"><?= $invoice_customer; ?></option>

                            <?php if (  $idCustomerDraft > 0 ) { ?> 
                              <?php if ( $r != 1 && $tipeHarga < 1 ) { ?>
                              <option value="0">Umum</option>
                              <?php } ?>
                            <?php } ?>

                              <?php  
                                $customer = query("SELECT * FROM customer WHERE customer_cabang = $sessionCabang && customer_status = 1 && customer_category = $tipeHarga ORDER BY customer_id DESC ");
                              ?>
                              <?php foreach ( $customer as $ctr ) : ?>
                                <?php if ( $ctr['customer_id'] > 1 && $ctr['customer_nama'] !== "Customer Umum" ) { ?>
                                <?php if ( $ctr['customer_id'] != $idCustomerDraft ) { ?>
                                <option value="<?= $ctr['customer_id'] ?>"><?= $ctr['customer_nama'] ?></option>
                                <?php } ?>
                                <?php } ?>
                              <?php endforeach; ?>
                            </select>
                            <small>
                              <a href="customer-add">Tambah Customer <i class="fa fa-plus"></i></a>
                            </small>
                        </div>

                        <!-- View Jika Select Dari Marketplace -->
                        <span id="beli-langsung-marketplace"></span>

                        <div class="form-group">
                            <label>Tipe Pembayaran</label>
                            <select class="form-control" required="" name="invoice_tipe_transaksi">
                              <option selected="selected" value="0">Cash</option>
                              <option value="1">Transfer</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Kurir</label>
                            <select class="form-control select2bs4" required="" name="invoice_kurir">
                              <?php if ( $dataTokoLogin['toko_ongkir'] > 0 ) { ?>
                              <option selected="selected" value="">-- Pilih Kurir --</option>
                              <?php } ?>
                              <option value="0">Tanpa Kurir</option>
                              <?php  
                                $kurir = query("SELECT * FROM user WHERE user_level = 'kurir' && user_cabang = $sessionCabang && user_status = '1' ORDER BY user_id DESC ");
                              ?>
                              <?php foreach ( $kurir as $row ) : ?>
                                <option value="<?= $row['user_id']; ?>">
                                  <?= $row['user_nama']; ?>  
                                </option>
                              <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- kondisi jika memilih piutang -->
                        <?php if ( $r == 1 ) : ?>
                        <div class="form-group">
                            <label style="color: red;">Jatuh Tempo</label>
                            <input type="date" name="invoice_piutang_jatuh_tempo" class="form-control" required="" value="<?= date("Y-m-d"); ?>">
                        </div>
                       <?php else : ?>
                          <input type="hidden" name="invoice_piutang_jatuh_tempo" value="0">
                       <?php endif; ?>

                      </div>
                    </div>
                    <div class="col-md-6 col-lg-5">
                      <div class="invoice-table">
                        <table class="table">
                          <tr>
                              <td style="width: 110px;"><b>Total</b></td>
                              <td class="table-nominal">
                                 <!-- Rp. <?php echo number_format($total, 0, ',', '.'); ?> -->
                                 <span>Rp. </span>
                                 <span>
                                    <input type="text" name="invoice_total" id="angka2" class="a2"  value="<?= $total; ?>" onkeyup="return isNumberKey(event)" size="10" readonly>
                                 </span>
                                 
                              </td>
                          </tr>

                        <!-- Ongkir Dinamis untuk Inputan -->
                          <tr class="ongkir-dinamis none">
                              <td>Ongkir</td>
                              <td class="table-nominal tn">
                                 <span>Rp.</span> 
                                 <span class="ongkir-beli-langsung">
                                   <input type="number" name="invoice_ongkir" id="" class="b2 ongkir-dinamis-input" autocomplete="off" onkeyup="hitung2();" onkeyup="return isNumberKey(event)"  onkeypress="return hanyaAngka1(event)">
                                   <i class="fa fa-close fa-ongkir-dinamis"></i>
                                 </span>
                              </td>
                          </tr>
                          <tr class="ongkir-dinamis none">
                              <td>Diskon</td>
                              <td class="table-nominal tn">
                                 <span>Rp.</span> 
                                 <span>
                                   <input type="number" name="invoice_diskon" id="" class="f2 ongkir-dinamis-diskon" autocomplete="off" onkeyup="hitung6();" onkeyup="return isNumberKey(event)" onkeypress="return hanyaAngka1(event)" size="10">
                                 </span>
                              </td>
                          </tr>

                          <tr class="ongkir-dinamis none">
                                <td><b>Sub Total</b></td>

                                <td class="table-nominal c2parent">
                                   <span>Rp. </span>
                                   <span>
                                      <input type="text" name="invoice_sub_total"  class="c2"  value="<?= $total; ?>" readonly>
                                   </span>
                                </td>

                                <td class="table-nominal g2parent" style="display: none;">
                                   <span>Rp. </span>
                                   <span >
                                      <input type="text" name="invoice_sub_total"  class="g2"  value="<?= $total; ?>" readonly>
                                   </span>
                                </td>
                          </tr>

                          <tr class="ongkir-dinamis none">
                                <td>
                                    <b style="color: red;">
                                        <?php  
                                          // kondisi jika memilih piutang
                                          if ( $r == 1 ) {
                                            echo "DP";
                                          } else {
                                            echo "Bayar";
                                          }
                                        ?>      
                                    </b>
                                </td>

                                <td class="table-nominal tn d2parent">
                                   <span>Rp.</span> 
                                   <span class="">
                                     <input type="number" name="angka1" id="angka1" class="d2 ongkir-dinamis-bayar" autocomplete="off" onkeyup="hitung3();"  onkeyup="return isNumberKey(event)" onkeypress="return hanyaAngka1(event)" size="10">
                                   </span>
                                </td>

                                <td class="table-nominal tn h2parent" style="display: none;">
                                   <span>Rp.</span> 
                                   <span class="" >
                                     <input type="number" name="angka1" id="angka1" class="h22 ongkir-dinamis-bayar" autocomplete="off" onkeyup="hitung7();"  onkeyup="return isNumberKey(event)" onkeypress="return hanyaAngka1(event)" size="10">
                                   </span>
                                </td>
                          </tr>

                          <tr class="ongkir-dinamis none">
                              <td>
                                  <?php  
                                    // kondisi jika memilih piutang
                                    if ( $r == 1 ) {
                                        echo "Sisa Piutang";
                                    } else {
                                        echo "Kembali";
                                    }
                                  ?>  
                              </td>
                              <td class="table-nominal">
                                <span>Rp.</span>
                                 <span>
                                  <input type="text" name="hasil" id="hasil" class="e2" readonly size="10" disabled>
                                </span>
                              </td>
                          </tr>
                        <!-- End Ongkir Dinamis untuk Inputan -->

                        <!-- Ongkir Statis untuk Inputan -->
                          <tr class="ongkir-statis">
                              <td>Ongkir</td>
                              <td class="table-nominal tn">
                                 <span>Rp.</span> 
                                 <span class="ongkir-beli-langsung">
                                   <input type="number" value="<?= $dataTokoLogin['toko_ongkir']; ?>" name="invoice_ongkir" id="" class="b2 ongkir-statis-input" readonly>
                                   <i class="fa fa-close fa-ongkir-statis"></i>
                                 </span>
                              </td>
                          </tr>
                          <tr class="ongkir-statis">
                              <td>Diskon</td>
                              <td class="table-nominal tn">
                                 <span>Rp.</span> 
                                 <span>
                                   <input type="number" name="invoice_diskon" id="" class="f21 ongkir-statis-diskon" value="0" required="" autocomplete="off" onkeyup="hitung5();" onkeyup="return isNumberKey(event)" onkeypress="return hanyaAngka1(event)" size="10">
                                 </span>
                              </td>
                          </tr>
                          <tr class="ongkir-statis">
                              <td><b>Sub Total</b></td>
                              <td class="table-nominal">
                                 <span>Rp. </span>
                                 <span>
                                    <?php  
                                      $subTotal = $total + $dataTokoLogin['toko_ongkir'];
                                    ?>
                                    <input type="hidden" name=""  class="g21"  value="<?= $subTotal; ?>" readonly>
                                    <input type="text" name="invoice_sub_total"  class="c21"  value="<?= $subTotal; ?>" readonly>
                                 </span>
                                 
                              </td>
                          </tr>
                          <tr class="ongkir-statis">
                              <td>
                                  <b style="color: red;">
                                      <?php  
                                        // kondisi jika memilih piutang
                                        if ( $r == 1 ) {
                                          echo "DP";
                                        } else {
                                          echo "Bayar";
                                        }
                                      ?>      
                                  </b>
                              </td>
                              <td class="table-nominal tn">
                                 <span>Rp.</span> 
                                 <span>
                                   <input type="number" name="angka1" id="angka1" class="d21 ongkir-statis-bayar" autocomplete="off" onkeyup="hitung4();"  onkeyup="return isNumberKey(event)" onkeypress="return hanyaAngka1(event)" size="10">
                                 </span>
                              </td>
                          </tr>
                          <tr class="ongkir-statis">
                              <td>
                                  <?php  
                                    // kondisi jika memilih piutang
                                    if ( $r == 1 ) {
                                        echo "Sisa Piutang";
                                    } else {
                                        echo "Kembali";
                                    }
                                  ?>  
                              </td>
                              <td class="table-nominal">
                                <span>Rp.</span>
                                 <span>
                                  <input type="text" name="hasil" id="hasil" class="e21" readonly size="10" disabled>
                                </span>
                              </td>
                          </tr>
                        <!-- End Ongkir Statis untuk Inputan -->

                          
                          <tr>
                              <td></td>
                              <td>

                                <?php  foreach ($keranjang as $stk) : ?>
                                <?php if ( $stk['keranjang_id_kasir'] === $userId ) { ?>
                                  <input type="hidden" name="barang_ids[]" value="<?= $stk['barang_id']; ?>">
                                  <input type="hidden" min="1" name="keranjang_qty[]" value="<?= $stk['keranjang_qty']; ?>"> 
                                  <input type="hidden" min="1" name="keranjang_qty_view[]" value="<?= $stk['keranjang_qty_view']; ?>"> 
                                  <input type="hidden" name="keranjang_konversi_isi[]" value="<?= $stk['keranjang_konversi_isi']; ?>"> 
                                  <input type="hidden" name="keranjang_satuan[]" value="<?= $stk['keranjang_satuan']; ?>"> 
                                  <input type="hidden" name="keranjang_harga_beli[]" value="<?= $stk['keranjang_harga_beli']; ?>">
                                  <input type="hidden" name="keranjang_harga[]" value="<?= $stk['keranjang_harga']; ?>">
                                  <input type="hidden" name="keranjang_harga_parent[]" value="<?= $stk['keranjang_harga_parent']; ?>">
                                  <input type="hidden" name="keranjang_harga_edit[]" value="<?= $stk['keranjang_harga_edit']; ?>">
                                  <input type="hidden" name="keranjang_id_kasir[]" value="<?= $stk['keranjang_id_kasir']; ?>">

                                  <input type="hidden" name="penjualan_invoice[]" value="<?= $di; ?>">
                                  <input type="hidden" name="penjualan_date[]" value="<?= date("Y-m-d") ?>">

                                  <input type="hidden" name="keranjang_barang_option_sn[]" value="<?= $stk['keranjang_barang_option_sn']; ?>">
                                  <input type="hidden" name="keranjang_barang_sn_id[]" value="<?= $stk['keranjang_barang_sn_id']; ?>">
                                  <input type="hidden" name="keranjang_sn[]" value="<?= $stk['keranjang_sn']; ?>">
                                  <input type="hidden" name="invoice_customer_category2[]" value="<?= $tipeHarga; ?>">
                                  <input type="hidden" name="keranjang_nama[]" value="<?= $stk['keranjang_nama']; ?>">
                                  <input type="hidden" name="barang_kode_slug[]" value="<?= $stk['barang_kode_slug']; ?>">
                                  <input type="hidden" name="keranjang_id_cek[]" value="<?= $stk['keranjang_id_cek']; ?>">
                                  <input type="hidden" name="penjualan_cabang[]" value="<?= $sessionCabang; ?>">
                                <?php } ?>
                                <?php endforeach; ?>  
                                <input type="hidden" name="penjualan_invoice2" value="<?= $di; ?>">
                                <input type="hidden" name="invoice_customer_category" value="<?= $tipeHarga; ?>">
                                <input type="hidden" name="kik" value="<?= $userId; ?>">
                                <input type="hidden" name="penjualan_invoice_count" value="<?= $jmlPenjualan1; ?>">
                                <input type="hidden" name="invoice_piutang" value="<?= $r; ?>">
                                <input type="hidden" name="invoice_piutang_lunas" value="0">
                                <input type="hidden" name="invoice_cabang" value="<?= $sessionCabang; ?>">
                                <input type="hidden" name="invoice_total_beli" value="<?= $total_beli; ?>">
                                <input type="hidden" name="invoice_id" value="<?= $invoiceDataDraft['invoice_id']; ?>">
                              </td>
                          </tr>
                        </table>
                      </div>
                      <div class="payment">
                          <?php  
                              $idKasirKeranjang = $_SESSION['user_id'];
                              $dataSn = mysqli_query($conn,"select * from keranjang where keranjang_barang_option_sn > 0 && keranjang_sn != null && keranjang_cabang = $sessionCabang && keranjang_id_kasir = $idKasirKeranjang");
                              $jmlDataSn = mysqli_num_rows($dataSn);
                          ?>
                          <?php if ( $jmlDataSn < 1 ) { ?>
                              <a href="beli-langsung?customer=<?= $_GET['customer']; ?>&r=<?= base64_encode($r); ?>" class="btn btn-danger">Transaksi Pending <i class="fa fa-file-o"></i>
                              </a>

                              <button class="btn btn-primary" type="submit" name="updateStock">Simpan Payment <i class="fa fa-shopping-cart"></i></button>
                          <?php } ?>

                          <?php if ( $jmlDataSn > 0 ) { ?>
                              <a href="#!" class="btn btn-default jmlDataSn" type="" name="">Transaksi Pending<i class="fa fa-file-o"></i></a>

                              <a href="#!" class="btn btn-default jmlDataSn" type="" name="">Simpan Payment <i class="fa fa-shopping-cart"></i></a>
                          <?php } ?>
                        </div>
                    </div>
                  </div>
               </form>
              </div>
            </div>
            <!-- /.card-body -->
          </div>
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
    </section>
    <!-- /.content -->
  </div>
</div>


    <div class="modal fade" id="modal-id" data-backdrop="static">
        <div class="modal-dialog modal-lg-pop-up">
          <div class="modal-content">
            <div class="modal-body">
                  <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Data barang Keseluruhan</h3>
                  <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                  <div class="table-auto">
                    <table id="example1" class="table table-bordered table-striped" style="width: 100%;">
                      <thead>
                      <tr>
                        <th style="width: 5%;">No.</th>
                        <th>Kode Barang</th>
                        <th>Nama</th>
                        <th>
                          <?php  
                            echo "Harga <b style='color: #007bff;'>".$nameTipeHarga."</b>";
                          ?>
                        </th>
                        <th>Stock</th>
                        <th style="text-align: center;">Aksi</th>
                      </tr>
                      </thead>
                      <tbody>

                      </tbody>
                  </table>
                </div>
              </div>
                <!-- /.card-body -->
              </div>    
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
    </div>


  
  <!-- Modal Update SN -->
  <div class="modal fade" id="modal-id-1">
    <div class="modal-dialog">
      <div class="modal-content">

        <form role="form" id="form-edit-no-sn" method="POST" action="">
          <div class="modal-header">
            <h4 class="modal-title">No. SN Produk</h4>
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
          </div>
          <div class="modal-body" id="data-keranjang-no-sn">
            
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-dismiss="modal">Tutup</button>
            <button type="submit" class="btn btn-primary" name="updateSn" >Edit Data</button>
          </div>
        </form>

      </div>
    </div>
  </div>

  <!-- Modal Update QTY Penjualan -->
  <div class="modal fade" id="modal-id-2">
    <div class="modal-dialog">
      <div class="modal-content">

        <form role="form" id="form-edit-harga" method="POST" action="">
          <div class="modal-header">
            <h4 class="modal-title">Edit Produk</h4>
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
          </div>
          <div class="modal-body" id="data-keranjang-harga">
            
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-dismiss="modal">Tutup</button>
            <button type="submit" class="btn btn-primary" name="updateHarga" >Edit Data</button>
          </div>
        </form>

      </div>
    </div>
  </div>

  <script>
    $(document).ready(function(){
        var table = $('#example1').DataTable( { 
             "processing": true,
             "serverSide": true,

             <?php if ( $tipeHarga == 1 ) : ?>
              "ajax": "beli-langsung-search-data-grosir-1.php?cabang=<?= $sessionCabang; ?>",
             <?php elseif ( $tipeHarga == 2 ) : ?>
              "ajax": "beli-langsung-search-data-grosir-2.php?cabang=<?= $sessionCabang; ?>",
             <?php else : ?>
              "ajax": "beli-langsung-search-data.php?cabang=<?= $sessionCabang; ?>",
             <?php endif; ?>

             "columnDefs": 
             [
              {
                "targets": 3,
                  "render": $.fn.dataTable.render.number( '.', '', '', 'Rp. ' )
                 
              },
              {
                "targets": -1,
                  "data": null,
                  "defaultContent": 
                  `<center>

                      <button class='btn btn-primary tblInsert' title="Tambah Keranjang">
                         <i class="fa fa-shopping-cart"></i> Pilih
                      </button>

                  </center>` 
              }
            ]
        });

        table.on('draw.dt', function () {
            var info = table.page.info();
            table.column(0, { search: 'applied', order: 'applied', page: 'applied' }).nodes().each(function (cell, i) {
                cell.innerHTML = i + 1 + info.start;
            });
        });

        $('#example1 tbody').on( 'click', '.tblInsert', function () {
            var data = table.row( $(this).parents('tr')).data();
            var data0 = data[0];
            var data0 = btoa(data0);
            window.location.href = "beli-langsung-add-draft?id="+ data0 + "&customer=<?= $_GET['customer']; ?>&r=<?= $r; ?>&invoice=<?= $_GET['invoice']; ?>";
        });

    });
  </script>

<?php include '_footer.php'; ?>

<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<script>
  $(function () {
    $("#example1").DataTable();
  });
  $(function () {
    $("#example7").DataTable();
  });
</script>
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
      // function hitung2() {
      // var a = $(".a2").val();
      // var b = $(".b2").val();
      // c = a - b;
      // $(".c2").val(c);
      // }

      function hitung2() {
          var txtFirstNumberValue = document.querySelector('.a2').value;
          var txtSecondNumberValue = document.querySelector('.b2').value;
          var result = parseInt(txtFirstNumberValue) + parseInt(txtSecondNumberValue);
          if (!isNaN(result)) {
             document.querySelector('.c2').value = result;
          }
      }
      function hitung3() {
        var a = $(".d2").val();
        var b = $(".c2").val();
        c = a - b;
        $(".e2").val(c);
      }
      function hitung7() {
        var a = $(".h22").val();
        var b = $(".g2").val();
        c = a - b;
        $(".e2").val(c);
      }

      // Diskon
      function hitung6() {
        document.querySelector(".g2parent").style.display = "block";
        document.querySelector(".c2parent").style.display = "none";
        document.querySelector(".h2parent").style.display = "block";
        document.querySelector(".d2parent").style.display = "none";
        var a = $(".c2").val();
        var b = $(".f2").val();
        c = a - b;
        $(".g2").val(c);
      }

      // =================================== Statis ================================== //
      // Sub Total - Bayar = kembalian
      function hitung4() {
        var a = $(".d21").val();
        var b = $(".c21").val();
        c = a - b;
        $(".e21").val(c);
      }

      // Diskon
      function hitung5() {
        var a = $(".g21").val();
        var b = $(".f21").val();
        c = a - b;
        $(".c21").val(c);
      }
      // =================================== End Statis ================================== //

      function isNumberKey(evt){
       var charCode = (evt.which) ? evt.which : event.keyCode;
       if (charCode != 46 && charCode > 31 && (charCode < 48 || charCode > 57))
       return false;
       return true;
      }
</script>
<script>
  $(function () {

    //Initialize Select2 Elements
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    })
  });
</script>

<script>
  $(document).ready(function(){
      $(".pilihan-marketplace").change(function(){
          $(this).find("option:selected").each(function(){
              var optionValue = $(this).attr("value");
              if(optionValue){
                  $(".box1").not("." + optionValue).hide();
                  $("." + optionValue).show();
              } else{
                  $(".box1").hide();
              }
          });
      }).change();

      // Memanggil Pop Up Data Produk SN dan Non SN
      $(document).on('click','#keranjang_sn',function(e){
          e.preventDefault();
          $("#modal-id-1").modal('show');
          $.post('beli-langsung-sn-draft.php',
            {id:$(this).attr('data-id')},
            function(html){
              $("#data-keranjang-no-sn").html(html);
            }   
          );
        });


      // Memanggil Pop Up Data Edit Harga
      $(document).on('click','#keranjang-harga',function(e){
          e.preventDefault();
          $("#modal-id-2").modal('show');
          $.post('beli-langsung-edit-qty-draft.php?customer=<?= $tipeHarga; ?>&invoice=<?= $invoiceDraft; ?>',
            {id:$(this).attr('data-id')},
            function(html){
              $("#data-keranjang-harga").html(html);
            }   
          );
        });

      $(".jmlDataSn").click(function(){
        alert("Anda Tidak Bisa Melanjutkan Transaksi Karena data No. SN Masih Ada yang Kosong !!");
      });

      // View Hidden Ongkir
      $(".fa-ongkir-statis").click(function(){
          $(".ongkir-statis").addClass("none");
          $(".ongkir-statis-input").attr("name", "");
          $(".ongkir-dinamis-input").attr("name", "invoice_ongkir");

          $(".ongkir-statis-diskon").attr("name", "");
          $(".ongkir-dinamis-diskon").attr("name", "invoice_diskon");

          $(".ongkir-statis-bayar").attr("name", "");
          $(".ongkir-dinamis-bayar").attr("name", "angka1");

          // $(".ongkir-dinamis-bayar").attr("required", true);
          $(".ongkir-statis-bayar").removeAttr("required");
          $(".ongkir-statis-diskon").removeAttr("required");
          $(".ongkir-dinamis-diskon").attr("required", true);
          $(".ongkir-dinamis").removeClass("none");
      });

      $(".fa-ongkir-dinamis").click(function(){
          $(".ongkir-dinamis").addClass("none");
          $(".ongkir-dinamis-input").attr("name", "");
          $(".ongkir-statis-input").attr("name", "invoice_ongkir");

          $(".ongkir-dinamis-diskon").attr("name", "");
          $(".ongkir-statis-diskon").attr("name", "invoice_diskon");

          $(".ongkir-dinamis-bayar").attr("name", "");
          $(".ongkir-statis-bayar").attr("name", "angka1");

          // $(".ongkir-dinamis-bayar").removeAttr("required");
          $(".ongkir-dinamis-diskon").removeAttr("required");
          $(".ongkir-statis-diskon").attr("required", true);
          $(".ongkir-statis-bayar").attr("required", true);
          $(".ongkir-statis").removeClass("none");
      });
  });

  // load halaman di pilihan select jenis usaha
  $('#beli-langsung-marketplace').load('beli-langsung-marketplace.php');

</script>

</body>
</html>

<script>
  // Aksi Select Status
  function myFunction() {
    var x = document.getElementById("mySelect").value;
    if ( x === "1" ) {
      document.location.href = "beli-langsung?customer=<?= base64_encode(1); ?>";

    } else if ( x === "2" ) {
      document.location.href = "beli-langsung?customer=<?= base64_encode(2); ?>";

    } else {
      document.location.href = "beli-langsung?customer=<?= base64_encode(0); ?>";
    }
  }
</script>