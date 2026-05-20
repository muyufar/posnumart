<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>
<?php  
  if ( $levelLogin === "kasir" || $levelLogin === "kurir" ) {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }   
?>

<?php  
// Insert Data
// cek apakah tombol submit sudah ditekan atau belum
if( isset($_POST["submit"]) ){

  if ( $_POST['tsc_cabang_pusat'] == $_POST['tsc_cabang_penerima'] ) {
      echo "
        <script>
          alert('Maaf !!! Lokasi toko TIDAK BOLEH SAMA Mohon cek kembali');
          document.location.href = 'transfer-stock-cabang';
        </script>
      ";
  } else {
      // cek apakah data berhasil di tambahkan atau tidak
      if( tambahTransferSelectCabang($_POST) > 0 ) {
        echo "
          <script>
            document.location.href = 'transfer-stock-cabang';
          </script>
        ";
      } else {
        echo "
          <script>
            alert('Data GAGAL Ditambahkan');
          </script>
        ";
      }
  }
}
?>

<?php  
// cek apakah tombol submit sudah ditekan atau belum
if( isset($_POST["resetcabang"]) ){
  // var_dump($_POST);

    // cek apakah data berhasil di tambahkan atau tidak
    if( resetTransferSelectCabang($_POST) > 0 ) {
      echo "
        <script>
          document.location.href = '';
        </script>
      ";
    } else {
      echo "
        <script>
          alert('Data GAGAL Dihapus');
        </script>
      ";
    } 
}

// Insert Ke keranjang search
if( isset($_POST["tambahkeranjangtransfer"]) ){
    // var_dump($_POST);

    // cek apakah data berhasil di tambahkan atau tidak
    if( tambahkeranjangtransfer($_POST) > 0 ) {
      echo "
        <script>
          document.location.href = '';
        </script>
      ";
    } 
}

// Insert Ke keranjang Scan Barcode
if( isset($_POST["inputbarcode"]) ){
    // var_dump($_POST);

    // cek apakah data berhasil di tambahkan atau tidak
    if( tambahKeranjangBarcodeTransfer($_POST) > 0 ) {
      echo "
        <script>
          document.location.href = '';
        </script>
      ";
    }  
}

// Update Data Harga Produk di Keranjang
if ( isset($_POST["updateQty"]) ) {
  if( updateQtyTransfer($_POST) > 0 ) {
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

// Update Data Produk SN dan Non SN 
if ( isset($_POST["updateSn"]) ) {
    if( updateSnTransfer($_POST) > 0 ) {
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

<?php 
error_reporting(0);
// Insert Ke keranjang
$inv      = $_POST["transfer_ref"];
$invLink  = base64_encode($inv);
if( isset($_POST["prosesTransfer"]) ){
  // var_dump($_POST);
  $sql = mysqli_query($conn, "SELECT * FROM transfer WHERE transfer_ref='$inv' && transfer_cabang = '$sessionCabang' ") or die (mysqli_error($conn));

  $hasilquery = mysqli_num_rows($sql);

  if( $hasilquery == 0){
      // cek apakah data berhasil di tambahkan atau tidak
      if( prosesTransfer($_POST) > 0 ) {
        echo "
          <script>
            document.location.href = 'transfer-detail?no=".$invLink."';
          </script>
        ";
      } else {
        echo "
          <script>
            alert('Transaksi Gagal !!');
          </script>
        ";
      }
  }else {
    echo "
        <script>
          document.location.href = 'transfer-detail?no=".$invLink."';
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
          <div class="col-sm-6">
            <h1>Transfer Stock Cabang</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Transfer Stock</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>


    <section class="content">
      <div class="container-fluid">
        <div class="card card-default">
          <div class="card-header">
            <h3 class="card-title">Pilih Lokasi Cabang</h3>

            <div class="card-tools">
              <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
              <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fas fa-remove"></i></button>
            </div>
          </div>
          <!-- /.card-header -->
          <?php  
              $tsc_user_id      = $_SESSION['user_id'];
              $tsc_cabang       = $sessionCabang;

              // Mencari data di tabel berdasrkan ID User dan lokasi Cabang sekaligus menghitung
              $count = mysqli_query($conn, "select * from transfer_select_cabang where tsc_user_id = ".$tsc_user_id." && tsc_cabang = ".$tsc_cabang." ");
              $count = mysqli_num_rows($count);


              // Mencari ID, Lokasi Awal & Lokasi Tujuan
              $selectCabang = mysqli_query( $conn, "select tsc_id, tsc_cabang_pusat, tsc_cabang_penerima from  transfer_select_cabang where tsc_user_id = ".$tsc_user_id." && tsc_cabang = ".$tsc_cabang." ");
              $sc = mysqli_fetch_array($selectCabang); 
              $tsc_id               = $sc["tsc_id"];
              $tsc_cabang_pusat     = $sc["tsc_cabang_pusat"];
              $tsc_cabang_penerima  = $sc["tsc_cabang_penerima"];
          ?>

          <?php if ( $count < 1 ) : ?>
          <form role="form" action="" method="POST">
            <div class="card-body">
              <?php if ( $sessionCabang < 1 ) : ?>
              <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tanggal_akhir">Lokasi Cabang Awal</label>
                        <select class="form-control select2bs4" required="" name="tsc_cabang_pusat">
                            <option selected="selected" value="">-- Pilih Lokasi --</option>
                            <?php  
                              $tokoselect = query("SELECT * FROM toko WHERE toko_status > 0 ORDER BY toko_id ASC");
                            ?>
                            <?php foreach ( $tokoselect as $row ) : ?>
                              <option value="<?= $row['toko_cabang'] ?>">
                                <?= $row['toko_nama'] ?> - <?= $row['toko_kota'] ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tanggal_akhir">Lokasi Cabang Penerima</label>
                        <select class="form-control select2bs4" required="" name="tsc_cabang_penerima">
                            <option selected="selected" value="">-- Pilih Lokasi --</option>
                            <?php foreach ( $tokoselect as $row ) : ?>
                              <option value="<?= $row['toko_cabang'] ?>">
                                <?= $row['toko_nama'] ?> - <?= $row['toko_kota'] ?>
                              </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
              </div>
              <?php else: ?>
              <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tanggal_akhir">Lokasi Cabang Awal</label>
                          <?php  
                            $tokoselectCabang = query("SELECT * FROM toko WHERE toko_cabang = $sessionCabang")[0];
                          ?>
                          <input type="hidden" name="tsc_cabang_pusat" value="<?= $sessionCabang; ?>">
                          <input type="text" class="form-control" name="" readonly="" value="<?= $tokoselectCabang['toko_nama'] ?> - <?= $tokoselectCabang['toko_kota'] ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tanggal_akhir">Lokasi Cabang Penerima</label>
                        <select class="form-control select2bs4" required="" name="tsc_cabang_penerima">
                            <option selected="selected" value="">-- Pilih Lokasi --</option>
                            <?php  
                              $tokoselect = query("SELECT * FROM toko WHERE toko_status > 0 && toko_cabang != $sessionCabang  ORDER BY toko_id ASC");
                            ?>
                            <?php foreach ( $tokoselect as $row ) : ?>
                              <option value="<?= $row['toko_cabang'] ?>">
                                <?= $row['toko_nama'] ?> - <?= $row['toko_kota'] ?>
                              </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
              </div>
              <?php endif; ?>
              <div class="card-footer text-right">
                  <input type="hidden" name="tsc_user_id" value="<?= $tsc_user_id; ?>">
                  <!-- User yg aksi -->
                  <input type="hidden" name="tsc_cabang" value="<?= $tsc_cabang; ?>">
                  <button type="submit" name="submit" class="btn btn-primary">
                    Pilih Cabang 
                  </button>
              </div>
            </div>
          </form>
          <?php else : ?>
          <form role="form" action="" method="POST">
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tanggal_akhir">Lokasi Cabang Awal</label>
                        <?php  
                            $tokoCabangAwal = query("SELECT * FROM toko WHERE toko_cabang = $tsc_cabang_pusat")[0];
                        ?>
                        <input type="text" class="form-control" value="<?= $tokoCabangAwal['toko_nama'] ?> - <?= $tokoCabangAwal['toko_kota'] ?>" readonly>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tanggal_akhir">Lokasi Cabang Penerima</label>
                        <?php  
                            $tokoCabangPenerima = query("SELECT * FROM toko WHERE toko_cabang = $tsc_cabang_penerima")[0];
                        ?>
                        <input type="text" class="form-control" value="<?= $tokoCabangPenerima['toko_nama'] ?> - <?= $tokoCabangPenerima['toko_kota'] ?>" readonly>
                    </div>
                </div>
              </div>
              <div class="card-footer text-right">
                  <input type="hidden" name="tsc_user_id" value="<?= $tsc_user_id; ?>">
                  <input type="hidden" name="tsc_cabang" value="<?= $tsc_cabang; ?>">
                  <input type="hidden" name="tsc_cabang_pusat" value="<?= $tsc_cabang_pusat; ?>">
                  <button type="submit" name="resetcabang" class="btn btn-danger">
                    Reset Cabang 
                  </button>
              </div>
            </div>
          </form>
          <?php endif; ?>
      </div>
    </section>

    <?php if ( $count > 0 ) { ?>
    <section class="content">
      <?php  
        $userId = $_SESSION['user_id'];
        $keranjang = query("SELECT * FROM keranjang_transfer WHERE keranjang_transfer_id_kasir = $userId && keranjang_transfer_cabang = $tsc_cabang_pusat ORDER BY keranjang_transfer_id ASC");
        
         $transfer = mysqli_query($conn,"select * from transfer where transfer_cabang = ".$sessionCabang." ");
         $jmlTransfer = mysqli_num_rows($transfer); 

         if ( $jmlTransfer < 1 ) {
              $transferProdukCount  = 1;
              $transferProdukKeluar = $tsc_cabang_pusat."1";
         } else {
              $transferProdukData = query("SELECT * FROM transfer WHERE transfer_pengirim_cabang = $tsc_cabang_pusat ORDER BY transfer_id DESC lIMIT 1")[0];
              $transferProdukParent = $transferProdukData['transfer_count'];
              $transferProdukCount  = $transferProdukParent + 1;
              $transferProdukKeluar = $tsc_cabang_pusat.$transferProdukCount;
          }
      ?>
          <div class="col-lg-12">
            <div class="card">
              <div class="card-header">
                <div class="row">
                  <div class="col-md-8 col-lg-8">
           <div class="card-invoice">
    <label for="ref">Ref :</label>
    <?php  
      $today = date("Ymd");
      $di = $today.$transferProdukKeluar;
    ?>
    <input type="text" name="ref" id="ref" value="<?= $di; ?>" placeholder="Masukkan referensi secara manual">
</div>

                  </div>
                  <div class="col-md-4 col-lg-4">
                      <div class="cari-barang-parent">
                        <div class="row">
                          <div class="col-10">
                              <form action="" method="post">
                                  <input type="hidden" name="keranjang_id_kasir" value="<?= $userId; ?>">
                                  <input type="hidden" name="keranjang_cabang" value="<?= $tsc_cabang_pusat; ?>">
                                  <input type="hidden" name="keranjang_cabang_pengirim" value="<?= $tsc_cabang_pusat; ?>">
                                  <input type="hidden" name="keranjang_cabang_tujuan" value="<?= $tsc_cabang_penerima; ?>">
                                  <input type="text" class="form-control" autofocus="" name="inputbarcode" placeholder="Barcode / Kode Barang" required="">
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
                  <table id="example1" class="table table-bordered table-striped">
                  <thead>
                  <tr>
                    <th style="width: 6%;">No.</th>
                    <th>Barang Kode</th>
                    <th>Nama</th>
                    <th style="text-align: center;">Satuan</th>
                    <th style="text-align: center;">QTY</th>
                    <th>No. SN</th>
                    <th style="text-align: center; width: 10%">Aksi</th>
                  </tr>
                  </thead>
                  <tbody>
                  <?php 
                    $i=1; 
                    $total = 0;
                  ?>
                  <?php 
                    foreach($keranjang as $row) : 

                    $bik = $row['barang_id'];
                    $stockParent = mysqli_query( $conn, "SELECT b.barang_kode, b.barang_stock, s.satuan_nama 
                                                         FROM barang b 
                                                         LEFT JOIN satuan s ON b.satuan_id = s.satuan_id 
                                                         WHERE b.barang_id = '".$bik."'");
                    $brg = mysqli_fetch_array($stockParent); 
                    $tb_kode = $brg['barang_kode'];
                    $tb_brg  = $brg['barang_stock'];
                    $tb_satuan = isset($brg['satuan_nama']) && !empty($brg['satuan_nama']) ? $brg['satuan_nama'] : '-';
          
                  ?>
                  <tr>
                      <td><?= $i; ?></td>
                      <td><?= $tb_kode; ?></td>
                      <td><?= $row['keranjang_transfer_nama'] ?></td>
                      <td style="text-align: center;"><span class="badge badge-info"><?= $tb_satuan; ?></span></td>
                      <td style="text-align: center;"><?= $row['keranjang_transfer_qty']; ?></td>
                      <td>
                        <?php  
                          if ( $row['keranjang_barang_option_sn'] < 1 ) {
                            $sn = "Non-SN";
                          } else {
                              $sn = $row['keranjang_sn'];
                              if ( $row['keranjang_sn'] < 1 ) {
                                echo '
                                  <span class="keranjang-right">
                                    <button class=" btn-success" name="" class="keranjang-pembelian" id="keranjang_sn" data-id="'.$row['keranjang_transfer_id'].'">
                                     <i class="fa fa-edit"></i>
                                    </button> 
                                  </span>';
                              } 
                          }
                          echo $sn;
                        ?>
                      </td>
                      <td class="orderan-online-button">
                          <?php if ( $row['keranjang_barang_option_sn'] < 1 ) : ?>
                          <a href="#!" title="Edit Data">
                            <button class="btn btn-primary" name="" class="keranjang-pembelian" id="keranjang-qty" data-id="<?= $row['keranjang_transfer_id']; ?>">
                                <i class="fa fa-pencil"></i>
                            </button> 
                          </a>
                          <?php else: ?>
                          <a href="#!" title="Edit Data">
                            <button class="btn btn-default" name="" class="keranjang-pembelian">
                                <i class="fa fa-pencil"></i>
                            </button> 
                          </a>
                          <?php endif; ?>

                          <a href="transfer-stock-cabang-delete?id=<?= base64_encode($row['keranjang_transfer_id']); ?>" title="Delete Data" onclick="return confirm('Yakin dihapus ?')">
                              <button class="btn btn-danger" type="submit" name="hapus">
                                  <i class="fa fa-trash-o"></i>
                              </button>
                          </a>
                      </td>
                  </tr>
                  <?php $i++; ?>
                  <?php endforeach; ?>
                </table>
                </div>
                
         
                <div class="btn-transaksi">
                  <form role="form" action="" method="POST">
                    <div class="row">
                      <div class="col-md-6 col-lg-6"></div>
                      <div class="col-md-6 col-lg-6">
                        <div class="invoice-table">
                            <div class="form-group">
                              <label for="transfer_note">Catatan (optional)</label>
                              <textarea name="transfer_note" id="transfer_note" class="form-control" rows="10" placeholder="Tambahkan Catatan Secara Detail"></textarea>
                            </div>

                            <?php  foreach ($keranjang as $stk) : ?>
                                <?php if ( $stk['keranjang_transfer_id_kasir'] === $userId ) { ?>
                                  <input type="hidden" name="barang_id[]" value="<?= $stk['barang_id']; ?>">
                                  <input type="hidden" name="tpk_kode_slug[]" value="<?= $stk['barang_kode_slug']; ?>">


                                  <input type="hidden" min="1" name="keranjang_transfer_qty[]" value="<?= $stk['keranjang_transfer_qty']; ?>"> 
                                  <input type="hidden" name="tpk_ref[]" value="<?= $di; ?>">

                                  <input type="hidden" name="tpk_date[]" value="<?= date('Y-m-d'); ?>">
                                  <input type="hidden" name="tpk_date_time[]" value="<?= date('d F Y g:i:s a'); ?>">

                                  <input type="hidden" name="tpk_barang_option_sn[]" value="<?= $stk['keranjang_barang_option_sn']; ?>">
                                  <input type="hidden" name="tpk_barang_sn_id[]" value="<?= $stk['keranjang_barang_sn_id']; ?>">


                                  <input type="hidden" name="tpk_barang_sn_desc[]" value="<?= $stk['keranjang_sn']; ?>">


                                  <input type="hidden" name="keranjang_transfer_id_kasir[]" value="<?= $stk['keranjang_transfer_id_kasir']; ?>">
                                  <input type="hidden" name="tpk_pengirim_cabang[]" value="<?= $stk['keranjang_pengirim_cabang']; ?>">
                                  <input type="hidden" name="tpk_penerima_cabang[]" value="<?= $stk['keranjang_penerima_cabang']; ?>">
                                  <input type="hidden" name="tpk_cabang[]" value="<?= $sessionCabang; ?>">
                                <?php } ?>
                                <?php endforeach; ?>  
                                <input type="hidden" name="transfer_ref" value="<?= $di; ?>"> 
                                <input type="hidden" name="transfer_count" value="<?= $transferProdukCount; ?>">

                                <input type="hidden" name="transfer_pengirim_cabang" value="<?= $tsc_cabang_pusat; ?>">
                                <input type="hidden" name="transfer_penerima_cabang" value="<?= $tsc_cabang_penerima; ?>">

                                <input type="hidden" name="transfer_id_tipe_keluar" value="<?= $sessionCabang; ?>">
                                <input type="hidden" name="transfer_id_tipe_masuk" value="<?= $tsc_cabang_penerima; ?>">

                                <input type="hidden" name="transfer_user" value="<?= $userId; ?>">
                                <input type="hidden" name="transfer_cabang" value="<?= $sessionCabang; ?>">

                            <div class="payment text-right">
                              <?php  
                                $idKasirKeranjang = $_SESSION['user_id'];
                                $dataSn = mysqli_query($conn,"select * from keranjang_transfer where keranjang_barang_option_sn > 0 && keranjang_sn < 1 && keranjang_transfer_cabang = $sessionCabang && keranjang_transfer_id_kasir = $idKasirKeranjang");
                                    $jmlDataSn = mysqli_num_rows($dataSn);
                              ?>
                              <?php if ( $jmlDataSn < 1 ) { ?>
                                  <button class="btn btn-primary" type="submit" name="prosesTransfer">Transfer Sekarang </button>
                              <?php } ?>

                              <?php if ( $jmlDataSn > 0 ) { ?>
                                  <a href="#!" class="btn btn-default jmlDataSn" type="" name="">Transfer Sekarang </a>
                              <?php } ?>
                          </div>
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
        <!-- /.row -->
    </section>
      <?php  
        $data = query("SELECT b.*, s.satuan_nama 
                       FROM barang b 
                       LEFT JOIN satuan s ON b.satuan_id = s.satuan_id 
                       WHERE b.barang_stock > 0 && b.barang_cabang = $tsc_cabang_pusat && b.barang_status = 1 
                       ORDER BY b.barang_id DESC");
      ?>
      <div class="modal fade" id="modal-id" data-backdrop="static">
          <div class="modal-dialog modal-lg-pop-up">
            <div class="modal-content">
              <div class="modal-body">
                    <div class="card">
                  <div class="card-header">
                    <h3 class="card-title">Data barang Keseluruhan Lokasi <b><?= $tokoCabangAwal['toko_nama'] ?> - <?= $tokoCabangAwal['toko_kota'] ?></b></h3>
                  </div>
                  <!-- /.card-header -->
                  <div class="card-body">
                    <div class="table-auto">
                      <table id="example1" class="table table-bordered table-striped">
                        <thead>
                        <tr>
                          <th style="width: 6%;">No.</th>
                          <th style="width: 13%;">Kode Barang</th>
                          <th>Nama</th>
                          <th style="text-align: center;">Satuan</th>
                          <th>Stock</th>
                          <th style="text-align: center;">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $i=1; ?>
                        <?php foreach($data as $row) : ?>
                        <?php $satuanNama = isset($row['satuan_nama']) && !empty($row['satuan_nama']) ? $row['satuan_nama'] : '-'; ?>
                        <tr>
                            <td><?= $i; ?></td>
                            <td><?= $row['barang_kode'] ?></td>
                            <td><?= $row['barang_nama'] ?></td>
                            <td style="text-align: center;"><span class="badge badge-info"><?= $satuanNama; ?></span></td>
                            <td><?= $row['barang_stock'] ?> <small class="text-muted"><?= $satuanNama; ?></small></td>
                            <td style="text-align: center; width: 17%;">
                              <form role="form" action="" method="post">
                                <input type="hidden" name="barang_id" value="<?= $row["barang_id"]; ?>">
                                <input type="hidden" name="keranjang_nama" value="<?= $row['barang_nama'];  ?>">
                                <input type="hidden" name="keranjang_id_kasir" value="<?= $_SESSION['user_id']; ?>">
                                <input type="hidden" name="keranjang_barang_option_sn" value="<?= $row['barang_option_sn']; ?>">
                                <input type="hidden" name="keranjang_cabang" value="<?= $tsc_cabang_pusat; ?>">

                                <input type="hidden" name="keranjang_cabang_pengirim" value="<?= $tsc_cabang_pusat; ?>">
                                <input type="hidden" name="keranjang_cabang_tujuan" value="<?= $tsc_cabang_penerima; ?>">
                                <input type="hidden" name="barang_kode_slug" value="<?= $row['barang_kode_slug']; ?>">
                                <input type="hidden" name="barang_kode" value="<?= $row['barang_kode']; ?>">
                                <input type="hidden" name="cabang_penerima_stock" value="<?= $tokoCabangPenerima['toko_nama'] ?> - <?= $tokoCabangPenerima['toko_kota'] ?>">

                                <button class="btn btn-primary" type="submit" name="tambahkeranjangtransfer">
                                    <i class="fa fa-shopping-cart"></i> Pilih
                                </button>
                              </form>
                            </td>
                        </tr>
                        <?php $i++; ?>
                        <?php endforeach; ?>
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

      <!-- Modal Update QTY -->
      <div class="modal fade" id="modal-id-2">
        <div class="modal-dialog">
          <div class="modal-content">

            <form role="form" id="form-edit-qty" method="POST" action="">
              <div class="modal-header">
                <h4 class="modal-title">Edit Produk</h4>
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
              </div>
              <div class="modal-body" id="data-keranjang-qty">
                
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-dismiss="modal">Tutup</button>
                <button type="submit" class="btn btn-primary" name="updateQty" >Edit Data</button>
              </div>
            </form>

          </div>
        </div>
      </div>
    <?php } ?>
  </div>
</div>

<?php include '_footer.php'; ?>
</body>
</html>
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<script>
  $(function () {
    $("#example1").DataTable();
  });

  $(document).ready(function(){

      // Memanggil Pop Up Data Produk SN dan Non SN
      $(document).on('click','#keranjang_sn',function(e){
          e.preventDefault();
          $("#modal-id-1").modal('show');
          $.post('transfer-stock-cabang-edit-no-sn.php',
            {id:$(this).attr('data-id')},
            function(html){
              $("#data-keranjang-no-sn").html(html);
            }   
          );
        });


      // Memanggil Pop Up Data Edit QTY
      $(document).on('click','#keranjang-qty',function(e){
          e.preventDefault();
          $("#modal-id-2").modal('show');
          $.post('transfer-stock-cabang-edit-qty.php',
            {id:$(this).attr('data-id')},
            function(html){
              $("#data-keranjang-qty").html(html);
            }   
          );
        });

      $(".jmlDataSn").click(function(){
        alert("Anda Tidak Bisa Melanjutkan Transaksi Karena data No. SN Masih Ada yang Kosong !!");
      });

  });


</script>