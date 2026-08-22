<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>
<?php  
  if ( $levelLogin !== "kurir") {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }

  $status = abs((int)base64_decode($_GET['r']));
    
?>

	<!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Data Kurir <b><?= $_SESSION['user_nama']; ?></b></h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Kurir</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <?php  
      $type_kurir    = $_SESSION['user_id'];
    ?>
    <section class="content">
        <div class="row">
          <div class="col-12">
            
            <div class="card card-mobile">
              <div class="card-header">
                <h3 class="card-title">Data Pengiriman</h3>
              </div>

              <div class="card-search">
                <div class="row">
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-6">
                                <form action="" method="post">
                                  <input type="text" class="form-control" name="keyword" placeholder="Cari No. Invoice" autocomplete="off" id="keyword">
                                </form>
                            </div>
                            <div class="col-6">
                                <div class="box-user-select">
                                    <span>
                                        <select class="form-control" id="mySelect" onchange="myFunction()">
                                          <?php if ( $status == 0 ) : ?>
                                            <option value="0">Semua Status</option>
                                            <option value="1">Packing</option>
                                            <option value="2">Proses</option>
                                            <option value="3">Selesai</option>
                                            <option value="4">Gagal</option>

                                          <?php elseif ( $status == 1 ) : ?>
                                            <option value="1">Packing</option>
                                            <option value="2">Proses</option>
                                            <option value="3">Selesai</option>
                                            <option value="4">Gagal</option>
                                            <option value="0">Semua Status</option>

                                          <?php elseif ( $status == 2 ) : ?>
                                            <option value="2">Proses</option>
                                            <option value="3">Selesai</option>
                                            <option value="4">Gagal</option>
                                            <option value="1">Packing</option>
                                            <option value="0">Semua Status</option>

                                          <?php elseif ( $status == 3 ) : ?>
                                            <option value="3">Selesai</option>
                                            <option value="4">Gagal</option>
                                            <option value="1">Packing</option>
                                            <option value="2">Proses</option>
                                            <option value="0">Semua Status</option>

                                          <?php elseif ( $status == 4 ) : ?>
                                            <option value="4">Gagal</option>
                                            <option value="1">Packing</option>
                                            <option value="2">Proses</option>
                                            <option value="3">Selesai</option>
                                            <option value="0">Semua Status</option>

                                          <?php else : ?>
                                            <option value="0">Semua Status</option>
                                            <option value="1">Packing</option>
                                            <option value="2">Proses</option>
                                            <option value="3">Selesai</option>
                                            <option value="4">Gagal</option>
                                          <?php endif; ?>
                                        </select>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6"></div>
                </div>
              </div>

              <!-- /.card-header -->
              <div class="card-body" id="container">
                <div class="row">
                  <?php  
                    $jumlahDataPerHalaman = 9;
                    $jumlahData = count(query("SELECT * FROM invoice WHERE invoice_cabang = '".$sessionCabang."' && invoice_kurir = '".$type_kurir."' && invoice_status_kurir > 0 && invoice_status_kurir = '".$status."' "));
                    $jumlahHalaman = ceil($jumlahData / $jumlahDataPerHalaman);

                    $halamanAktif = ( isset($_GET["page"]) ) ? $_GET["page"] : 1 ;
                    $awalData = ( $jumlahDataPerHalaman * $halamanAktif ) - $jumlahDataPerHalaman;
                  ?>
                  <?php 
                    $total = 0;
                    $queryInvoice = $conn->query("SELECT invoice.invoice_id ,invoice.penjualan_invoice, invoice.invoice_tgl, customer.customer_id, customer.customer_nama, customer.customer_tlpn, customer.customer_alamat, invoice.invoice_kurir, invoice.invoice_status_kurir, invoice.invoice_date_selesai_kurir, invoice.invoice_cabang, invoice.invoice_sub_total, user.user_id, user.user_nama
                               FROM invoice 
                               JOIN customer ON invoice.invoice_customer = customer.customer_id
                               JOIN user ON invoice.invoice_kurir = user.user_id
                               WHERE invoice_cabang = '".$sessionCabang."' && invoice_kurir = '".$type_kurir."' && invoice_status_kurir > 0 && invoice_status_kurir = '".$status."'
                               ORDER BY invoice_id DESC LIMIT $awalData, $jumlahDataPerHalaman
                               ");
                    while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
                  ?>
                    <?php  
                        $id = base64_encode($rowProduct['invoice_id']);
                        $alamat = str_replace(" ", "+", $rowProduct['customer_alamat']);
                        $no_wa = substr_replace($rowProduct['customer_tlpn'],'62',0,1);
                    ?>
                  <div class="col-md-4 col-lg-4">
                      <div class="card-desktop-box">
                          <div class="cdb-top">
                              <div class="row">
                                  <div class="col-6">
                                      <div class="cdb-top-date">
                                        <i class="fa fa-clock-o" aria-hidden="true"></i> 
                                        <span><?= $rowProduct['invoice_tgl']; ?></span>
                                      </div>
                                  </div>
                                  <div class="col-6">
                                      <div class="cdb-top-info">
                                          <div class="cti cdb-top-info-status">
                                              <?php 
                                                $statusKurir = $rowProduct['invoice_status_kurir'];
                                                if ( $statusKurir == 1 ) {
                                                  $sk = "<span class='badge badge-warning'>Packing</span>";
                                                } elseif ( $statusKurir == 2 ) {
                                                  $sk = "<span class='badge badge-success'>Proses</span>";
                                                } elseif ( $statusKurir == 3 ) {
                                                  $sk = "<span class='badge badge-primary'>Selesai</span>";
                                                } elseif ( $statusKurir == 4 ) {
                                                  $sk = "<span class='badge badge-danger'>Gagal</span>";
                                                } else {
                                                  $sk = "Tanpa Kurir";
                                                }
                                                echo $sk;
                                              ?>      
                                          </div>
                                          <div class="cti cdb-top-info-action">
                                              <div class="btn-group">
                                                <a class="btn dropdown-toggle" data-toggle="dropdown" href="#"> <i class="fa fa-ellipsis-v"></i></a>
                                                <ul class="dropdown-menu">
                                                  <li><a href="penjualan-zoom?no=<?= $id; ?>" target="_blank">Detail Invoice</a></li>

                                                  <li><a href="https://api.whatsapp.com/send?phone=<?= $no_wa; ?>&text=Hallo <?= $customer; ?> Kami dari *<?= $dataTokoLogin['toko_nama']; ?> <?= $dataTokoLogin['toko_kota']; ?>* akan mengirimkan Produk dengan No. Invoice <?= $rowProduct['penjualan_invoice']; ?>" target="_blank">WhatsApp</a></li>

                                                  <li><a href="nota-cetak?no=<?= $rowProduct['invoice_id']; ?>-no-invoice-<?= $rowProduct['penjualan_invoice']; ?>" target="_blank">Print</a></li>

                                                  <li><a href="https://www.google.com/maps/search/<?= $alamat; ?>" target="_blank">GPS</a></li>
                                                </ul>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                              </div>
                          </div>
                          <div class="cdb-detail">
                              <div class="cdb-detail-title">
                                No. Invoice: <?= $rowProduct['penjualan_invoice']; ?>
                              </div>
                              <div class="cdb-detail-desc">
                                Terkirim: <?= $rowProduct['invoice_date_selesai_kurir']; ?>
                              </div>
                          </div>
                          <div class="cdb-bottom">
                              <div class="row">
                                  <div class="col-6">
                                    <div class="cdb-bottom-left">
                                        <div class="cbl-title">
                                            Sub Total:
                                        </div>
                                        <div class="cbl-desc">
                                            Rp <?= number_format($rowProduct['invoice_sub_total'], 0, ',', '.'); ?>
                                        </div>
                                    </div>
                                  </div>
                                  <div class="col-6">
                                      <div class="cdb-bottom-right">
                                         <a href="kurir-data-edit?id=<?= $id; ?>" class="btn btn-primary">
                                            Edit Status
                                         </a>
                                      </div>
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>
                  <?php } ?>
                </div>

                <div class="product-pagination">
                  <nav aria-label="">
                    <ul class="pagination">
                      <?php if( $halamanAktif > 1) : ?>
                      <li class="page-item disabled">
                          <a class="page-link" href="?page=<?= $halamanAktif - 1; ?>" tabindex="-1">Previous</a>
                        </li>
                    <?php endif; ?>
                    <?php for( $i = 1; $i <= $jumlahHalaman; $i++) : ?>
                      <?php if( $i == $halamanAktif ) : ?>
                        <li class="page-item active">
                            <a class="page-link" href="?page=<?= $i; ?>&r=<?= $_GET['r']; ?>"><?= $i; ?> <span class="sr-only">(current)</span></a>
                          </li>
                      <?php else : ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $i; ?>&r=<?= $_GET['r']; ?>"><?= $i; ?></a></li>
                      <?php endif; ?>
                    <?php endfor; ?>
                      
                      <?php if( $halamanAktif < $jumlahHalaman ) : ?>
                      <li class="page-item">
                          <a class="page-link" href="?page=<?= $halamanAktif + 1; ?>&r=<?= $_GET['r']; ?>">Next</a>
                        </li>
                    <?php endif; ?>
                    </ul>
                  </nav>
                </div>

              </div>
            </div>
          </div>
          <!-- /.col -->
        </div>
        <!-- /.row -->
    </section>

    </div>
</div>





    <?php include '_footer.php'; ?>

<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<!-- AdminLTE App -->
<!-- <script src="dist/js/adminlte.min.js"></script> -->
<!-- AdminLTE for demo purposes -->
<!-- <script src="dist/js/demo.js"></script> -->
<!-- page script -->
<script>
  $(function () {
    $("#example1").DataTable();
  });
</script>

<!-- Aksi jika Form Input Search -->
<script>
  // ambil elemen2 yang dibutuhkan
  var keyword = document.getElementById('keyword');
  var tombolCari = document.getElementById('tombol-cari');
  var container = document.getElementById('container');

  keyword.addEventListener('keyup', function() {
    // console.log(keyword.value);

    // buat objeck ajak
    var xhr = new XMLHttpRequest();

    // cek kesiapan ajak
    xhr.onreadystatechange = function() {
      if( xhr.readyState == 4 && xhr.status == 200 ) {
        // console.log(xhr.responseText);
        container.innerHTML = xhr.responseText;
      }
    }

    // eksekusi ajak
    xhr.open('GET', 'kurir-data-search.php?keyword=' + keyword.value, true);
    xhr.send();

});
</script>

<script>
  // Aksi Select Status
  function myFunction() {
    var x = document.getElementById("mySelect").value;
    if ( x === "1" ) {
      document.location.href = "kurir-data-status?r="+ btoa(1);

    } else if ( x === "2" ) {
      document.location.href = "kurir-data-status?r="+ btoa(2);

    } else if ( x === "3" ) {
      document.location.href = "kurir-data-status?r="+ btoa(3);

    } else if ( x === "4" ) {
      document.location.href = "kurir-data-status?r="+ btoa(4);

    } else {
      document.location.href = "kurir-data";
    }
  }
</script>
</body>
</html>