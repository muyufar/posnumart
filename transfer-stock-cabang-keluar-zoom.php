<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>

<?php  
  // ambil data di URL
  $id = base64_decode($_GET['no']);

  // query data transfer berdasarkan id
  $transfer = query("SELECT * FROM transfer WHERE transfer_ref = $id ")[0];

  if ( $transfer == null ) {
    header("location: transfer-stock-cabang-keluar");
  }
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Data Transfer</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Transfer</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-12">
          <div class="invoice p-3 mb-3">
            <div class="row">
              <div class="col-12">
                <h4>
                  <i class="fas fa-globe"></i> No. Ref: <?= $id; ?>
                  <small class="float-right">Tanggal Kirim: <?= $transfer['transfer_date_time']; ?></small><br>
                  <?php if ( $transfer['transfer_terima_date_time'] != null ) { ?>
                    <small class="float-right">Tanggal Diterima: <?= $transfer['transfer_terima_date_time']; ?></small>
                  <?php } ?>
                </h4>
              </div>
            </div>

            <div class="row">
              <div class="col-12 table-responsive">
                <div class="table-auto">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th>No</th>
                        <th>Barcode</th>
                        <th>Nama Barang</th>
                        <th>Qty</th>
                        <th>Harga Satuan</th>
                        <th>Total Harga</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php 
                        $transfer1 = $id;
                        $i = 1; 
                        $subtotal = 0;

                        $queryProduct = $conn->query(
                          "SELECT 
                            transfer_produk_keluar.tpk_id, 
                            transfer_produk_keluar.tpk_qty, 
                            transfer_produk_keluar.tpk_ref, 
                            transfer_produk_keluar.tpk_barang_option_sn, 
                            transfer_produk_keluar.tpk_barang_sn_desc,  
                            barang.barang_id, 
                            barang.barang_nama, 
                            barang.barang_kode, 
                            barang.barang_harga_beli,
                            barang.barang_harga_beli_rata
                          FROM transfer_produk_keluar 
                          JOIN barang ON transfer_produk_keluar.tpk_barang_id = barang.barang_id
                          WHERE tpk_ref = $transfer1 
                          ORDER BY tpk_id DESC
                        ");

                        while ($rowProduct = mysqli_fetch_array($queryProduct)) {
                          $qty = $rowProduct['tpk_qty'];
                          $hargaSatuan = barang_hpp_dari_row($rowProduct);
                          $totalHarga = $qty * $hargaSatuan;
                          $subtotal += $totalHarga;
                      ?>
                      <tr>
                        <td><?= $i; ?></td>
                        <td><?= $rowProduct['barang_kode']; ?></td>
                        <td>
                          <?= $rowProduct['barang_nama']; ?><br>
                          <?php if ($rowProduct['tpk_barang_option_sn'] > 0) { ?>  
                            <small>No. SN: <?= $rowProduct['tpk_barang_sn_desc']; ?></small>
                          <?php } ?>
                        </td>
                        <td><?= $qty; ?></td>
                        <td>Rp. <?= number_format($hargaSatuan, 0, ',', '.'); ?></td>
                        <td>Rp. <?= number_format($totalHarga, 0, ',', '.'); ?></td>
                      </tr>
                      <?php $i++; } ?>
                    </tbody>
                    <tfoot>
                      <tr>
                        <th colspan="5" style="text-align:right">Subtotal:</th>
                        <th>Rp. <?= number_format($subtotal, 0, ',', '.'); ?></th>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            </div>
            <div class="row no-print">
              <div class="col-12">
                <a href="#" class="btn btn-success float-right" onclick="self.close()" style="margin-right: 5px;">Kembali</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include '_footer.php'; ?>
