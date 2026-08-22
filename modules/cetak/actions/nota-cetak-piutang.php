<?php 
  include '_header-artibut.php';
?>
<?php  
  $status = $_SESSION['user_status'];
    if ( $status === '0') {
    echo"
          <script>
            alert('Akun Tidak Aktif');
            window.location='./';
          </script>";
  }
?>
<!DOCTYPE html>
<html>
<head>
	<title>Nota Cetak POS - CREATIVE CODE APP</title>
	<meta charset=utf-8>
	<meta name=description content="">
	<meta name=viewport content="width=device-width, initial-scale=1">
	<!-- Tempusdominus Bbootstrap 4 -->
    <!-- Favicon -->
    <link rel="icon" type="img/png" sizes="32x32" href="https://pos.numartmagelang.com/dist/img/logobumnupacnu.jpeg">
    <link rel="stylesheet" type="text/css" href="dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="dist/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="dist/css/style.css">

    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<?php  
    // ambil data di URL
    $id = abs((int)$_GET['no']);

    // query data 
    $invoice = query("SELECT * FROM invoice WHERE invoice_id = $id && invoice_cabang = $sessionCabang ")[0];
    $tipeTransaksi = $invoice['invoice_piutang'];
?>

<!-- Nama Kasir -->
<?php  
    $kasir = $invoice['invoice_kasir'];
    $dataKasir = query("SELECT * FROM user WHERE user_id = $kasir");
?>
<?php foreach ( $dataKasir as $ksr ) : ?>
    <?php $ksrDetail = $ksr['user_nama']; ?>
<?php endforeach; ?>

<!-- Nama Customer -->
<?php  
    $customer = $invoice['invoice_customer'];
    $dataCustomer = query("SELECT * FROM customer WHERE customer_id = $customer");
?>
<?php foreach ( $dataCustomer as $ctr ) : ?>
    <?php 
        $ctrId     = $ctr['customer_id']; 
        $ctrNama   = $ctr['customer_nama']; 
        $ctrAlmt   = $ctr['customer_alamat']; 
    ?>
<?php endforeach; ?>

<?php  
    $toko = query("SELECT * FROM toko WHERE toko_cabang = $sessionCabang");
?>
<?php foreach ( $toko as $row ) : ?>
    <?php 
        $toko_nama      = $row['toko_nama'];
        $toko_kota      = $row['toko_kota'];
        $toko_tlpn      = $row['toko_tlpn'];
        $toko_wa        = $row['toko_wa'];
        $toko_print     = $row['toko_print']; 
        $toko_alamat    = $row['toko_alamat'];
    ?>
<?php endforeach; ?>

    <section class="nota-lebar">
        <div class="container">
            <div class="nota-lebar-box">
                <div class="nzb-top">
                    <div class="row">
                        <div class="col-3 col-line-right">
                            <div class="nzb-top-img">
                                <h2><?= $toko_nama; ?></h2>
                            </div>
                        </div>
                        <div class="col-5">
                            <div class="nzb-top-text">
                                <p><?= $toko_nama; ?></p>
                                <p><?= $toko_alamat; ?></p>
                                <p><?= $toko_kota; ?></p>
                                <p><?= $toko_tlpn; ?> - <?= $toko_wa; ?></p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="nzb-top-invoice text-right">
                                <div class="nti-title">
                                    invoice
                                </div>
                                <div class="nti-no">
                                    <span>NO</span>
                                    <span><?= $invoice['penjualan_invoice']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="nzb-info-user">
                    <div class="row">
                        <div class="col-6">
                            <table class="table">
                                <tbody>
                                    <tr>
                                        <td>Tanggal</td>
                                        <td>: <?= date("d F Y g:i:s a");; ?></td>
                                    </tr>
                                    <tr>
                                        <td>Kepada</td>
                                        <td>: <?= $ctrNama; ?></td>
                                    </tr>
                                    <tr>
                                        <td>Alamat</td>
                                        <td>
                                            : 
                                            <?php 
                                                if ( $ctrAlmt == null ) {
                                                    echo "-";
                                                } else {
                                                    echo $ctrAlmt;
                                                }
                                            ?>        
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-6">
                            <table class="table">
                                <tbody>
                                    <tr>
                                        <td>Transaksi</td>
                                        <td>: 
                                            <?php  
                                                if ( $tipeTransaksi < 1 ) {
                                                    echo "Cash - ".$invoice['invoice_tgl'];
                                                } else {
                                                    echo "Piutang - ".$invoice['invoice_tgl'];
                                                }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Kasir</td>
                                        <td>: <?= $ksrDetail; ?></td>
                                    </tr>

                                    <?php if ( $tipeTransaksi == 1 ) { ?>
                                    <tr>
                                        <td>Jatuh Tempo</td>
                                        <td>: <?= tanggal_indo($invoice['invoice_piutang_jatuh_tempo']); ?></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="nzb-desc">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Item Description</th>
                                <th class="text-center">Qt</th>
                                <th class="text-center">Harga</th>
                                <th class="text-center" style="width: 230px">Total Harga</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                              $invoice1 = $invoice['penjualan_invoice'];
                              $i = 1; 
                              $queryProduct = $conn->query("SELECT penjualan.penjualan_id,
                               penjualan.barang_qty, 
                               penjualan.penjualan_invoice,
                               penjualan.barang_option_sn, 
                               penjualan.barang_sn_desc, 
                               penjualan.keranjang_harga, 
                               penjualan.penjualan_cabang, 
                               barang.barang_id, 
                               barang.barang_nama
                                         FROM penjualan 
                                         JOIN barang ON penjualan.barang_id = barang.barang_id
                                         WHERE penjualan_invoice = $invoice1 && penjualan_cabang = '".$sessionCabang."'
                                         ORDER BY penjualan_id DESC
                                         ");
                              while ($rowProduct = mysqli_fetch_array($queryProduct)) {
                            ?>
                            <tr>
                                <td>
                                    <?= $rowProduct['barang_nama']; ?>
                                    <?php  
                                        if ( $rowProduct['barang_option_sn'] > 0 ) {
                                            echo "<br><small>No. SN: ".$rowProduct['barang_sn_desc']."</small>";
                                        }
                                    ?>  
                                </td>
                                <td class="text-center"><?= $rowProduct['barang_qty']; ?></td>
                                <td class="text-right"><?= number_format($rowProduct['keranjang_harga'], 0, ',', '.'); ?></td>
                                <td class="text-right">
                                <?php  
                                    $subTotal = $rowProduct['barang_qty'] * $rowProduct['keranjang_harga'];
                                    echo(number_format($subTotal, 0, ',', '.'));
                                ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>

                    <h4>History Cicilan No. Invoice <?= $invoice['penjualan_invoice']; ?></h4>
                    <table class="table table-bordered">
                      <thead>
                        <tr>
                          <th style="width: 6%;">No.</th>
                          <th>Tanggal</th>
                          <th>Nominal</th>
                          <th>Pembayaran</th>
                          <th>Kasir</th>
                        </tr>
                       </thead>
                       <tbody>

                        <?php 
                          $i = 1; 
                          $invoicePenjualan = $invoice['penjualan_invoice'];
                          $queryProduct = $conn->query("SELECT piutang.piutang_id, piutang.piutang_invoice, piutang.piutang_date_time, piutang.piutang_kasir, piutang.piutang_nominal, piutang.piutang_tipe_pembayaran, piutang.piutang_cabang, user.user_id, user.user_nama
                                     FROM piutang 
                                     JOIN user ON piutang.piutang_kasir = user.user_id
                                     WHERE piutang_cabang = ".$sessionCabang." && piutang_invoice = ".$invoicePenjualan." ORDER BY piutang_id DESC
                                     ");
                          while ($rowProduct = mysqli_fetch_array($queryProduct)) {
                        ?>
                        <tr>
                            <td><?= $i; ?></td>
                            <td><?= $rowProduct['piutang_date_time']; ?></td>
                            <td>Rp. <?= number_format($rowProduct['piutang_nominal'], 0, ',', '.'); ?></td>
                            <td>
                              <?php  
                                $tipePembayaran = $rowProduct['piutang_tipe_pembayaran'];
                                if ( $tipePembayaran == 1 ) {
                                  echo "Transfer";
                                } elseif ( $tipePembayaran == 2 ) {
                                  echo "Debit";
                                } elseif ( $tipePembayaran == 3 ) {
                                  echo "Credit Card";
                                } else {
                                  echo "Cash";
                                }
                              ?>
                            </td>
                            <td><?= $rowProduct['user_nama']; ?></td>
                        </tr>
                        <?php $i++; ?>
                        <?php } ?>
                      </tbody>
                    </table>
                </div>

                <div class="nota-box-text">
                    <div class="row">
                        <div class="col-4">
                            <div class="nzb-ttd-box">
                                <div class="nzb-ttd-box-company">
                                    <?= $toko_nama; ?> <?= $toko_kota; ?>
                                </div>
                                <div class="nzb-ttd-box-line">
                                    ____________________________
                                </div>
                            </div>
                        </div>

                        <div class="col-3"></div>

                        <div class="col-5">
                            <div class="row">
                                <div class="col-5">
                                    <div class="nbt-text">
                                        <h5><b>Sub Total :</b></h5> 
                                    </div>
                                </div>
                                <div class="col-7">
                                    <h5>
                                        <b>
                                            Rp <span class="right-nota">
                                                <?= number_format($invoice['invoice_total'], 0, ',', '.'); ?>
                                            </span>
                                        </b>
                                    </h5>
                                </div>

                                <div class="col-5">
                                    <div class="nbt-text">
                                        <h5><b>Ongkir :</b></h5> 
                                    </div>
                                </div>
                                <div class="col-7">
                                    <h5>Rp. <span class="right-nota">
                                            <?= number_format($invoice['invoice_ongkir'], 0, ',', '.'); ?>
                                        </span>
                                    </h5>
                                </div>

                                <div class="col-5">
                                    <div class="nbt-text">
                                        <h5><b>Diskon :</b></h5>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <h5>Rp. <span class="right-nota">
                                            <?= number_format($invoice['invoice_diskon'], 0, ',', '.'); ?>
                                        </span>
                                    </h5>
                                </div>

                                <div class="col-5">
                                    <div class="nbt-text">
                                        <h5><b>Total :</b></h5>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <h5>Rp. <span class="right-nota">
                                            <?= number_format($invoice['invoice_sub_total'], 0, ',', '.'); ?>
                                        </span>
                                    </h5>
                                </div>

                                <?php if ( $tipeTransaksi == 1 ) { ?>
                                <div class="col-5">
                                    <div class="nbt-text">
                                        <h5><b>DP :</b></h5>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <h5>Rp. <span class="right-nota">
                                            <?= number_format($invoice['invoice_piutang_dp'], 0, ',', '.'); ?>
                                        </span>
                                    </h5>
                                </div>
                                <?php } ?>
                                
                                <div class="col-5">
                                    <div class="nbt-text">
                                        <h5>
                                            <b>
                                                <?php  
                                                    if ( $tipeTransaksi < 1 ) {
                                                        echo "Bayar :";
                                                    } else {
                                                        echo "DP + Cicilan :";
                                                    }
                                                ?>
                                            </b>
                                        </h5>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <h5>Rp. <span class="right-nota">
                                            <?= number_format($invoice['invoice_bayar'], 0, ',', '.'); ?>
                                        </span>
                                    </h5>
                                </div>

                                <div class="col-5">
                                    <div class="nbt-text">
                                        <h5>
                                            <b>
                                                <?php  
                                                    if ( $tipeTransaksi < 1 ) {
                                                        echo "Kembali :";
                                                    } else {
                                                        echo "Sisa Piutang :";
                                                    }
                                                ?>
                                            </b>
                                        </h5>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <h5>Rp. <span class="right-nota">
                                            <?= number_format($invoice['invoice_kembali'], 0, ',', '.'); ?> 
                                        </span>
                                    </h5>
                                </div> 
                            </div>
                        </div>
                    </div><br><br>
                </div>

<!--                <div class="nzb-footer">-->
<!--                    <div class="nzb-footer-box">-->
<!--                        <div class="nota-box-footer">-->
<!--                            <div class="nbf-text">-->
<!--                               <a href='http://www.numartmagelang.com'/> TOKO KAMI <a href='http://www.eydcom.com'/> - ONLINE DAN OFFLINE <a href='http://www.eydcom.com'/><br/>-->
<!-- &#169; <strong>2012-<?= date("Y"); ?><a href='http://www.eydcom.com'/>. EYD COMPUTER - <a href='http://www.eydcom.com'/>www.eydcom.com-->
<!--</div>-->
<!--                        </div>-->
<!--                    </div>-->
<!--                </div>-->
            </div>
        </div>
    </section>

</body>
</html>
<script>
    window.print();
</script>