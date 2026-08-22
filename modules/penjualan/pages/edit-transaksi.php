<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>
<?php  
  if ( $levelLogin === "kasir") {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }
    
?>

	<!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Data Transaksi yang diedit (retur)</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Per Periode</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>


    <section class="content">
      <div class="container-fluid">
        <div class="card card-default">
          <div class="card-header">
            <h3 class="card-title">Filter Data Berdasrkan Tanggal</h3>

            <div class="card-tools">
              <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
              <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fas fa-remove"></i></button>
            </div>
          </div>
          <!-- /.card-header -->
          <form role="form" action="" method="POST">
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tanggal_awal">Tanggal Awal</label>
                        <input type="date" name="tanggal_awal" class="form-control" id="tanggal_awal" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tanggal_akhir">Tanggal Akhir</label>
                        <input type="date" name="tanggal_akhir" class="form-control" id="tanggal_akhir" required>
                    </div>
                </div>
              </div>
              <div class="card-footer text-right">
                  <button type="submit" name="submit" class="btn btn-primary">
                    <i class="fa fa-filter"></i> Filter
                  </button>
              </div>
            </div>
          </form>
      </div>
    </section>


    <?php if( isset($_POST["submit"]) ){ ?>
        <?php  
          $tanggal_awal  = $_POST['tanggal_awal'];
          $tanggal_akhir = $_POST['tanggal_akhir'];
        ?>
    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">

          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Laporan Penjualan Retur</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
                <table id="laporan-penjualan-retur" class="table table-bordered table-striped table-laporan">
                  <thead>
                  <tr>
                    <th style="width: 6%;">No.</th>
                    <th style="width: 13%;">Invoice</th>
                    <th>Tanggal Pembelian</th>
                    <th>Tanggal Edit</th>
                    <th>Customer</th>
                    <th>Kasir yang Edit</th>
                    <th>Sub Total</th>
                    <th style="width: 10%">Aksi</th>
                  </tr>
                  </thead>
                  <tbody>

                  <?php 
                    $i = 1; 
                    $total = 0;
                    $queryInvoice = $conn->query("SELECT invoice.invoice_id ,invoice.penjualan_invoice, invoice.invoice_tgl, customer.customer_id, customer.customer_nama, invoice.invoice_total, invoice.invoice_sub_total, invoice.invoice_date_edit, invoice.invoice_cabang, user.user_id, user.user_nama, user.user_no_hp
                               FROM invoice 
                               JOIN customer ON invoice.invoice_customer = customer.customer_id
                               JOIN user ON invoice.invoice_kasir_edit = user.user_id
                               WHERE invoice_cabang = '".$sessionCabang."' && invoice_date BETWEEN '".$tanggal_awal."' AND '".$tanggal_akhir."'
                               ORDER BY invoice_id DESC
                               ");
                    while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
                    $total += $rowProduct['invoice_sub_total'];
                  ?>
                  <tr>
                    	<td><?= $i; ?></td>
                      <td><?= $rowProduct['penjualan_invoice']; ?></td>
                      <td><?= $rowProduct['invoice_tgl']; ?></td>
                      <td><?= $rowProduct['invoice_date_edit']; ?></td>
                      <td>
                          <?php  
                            $customer = $rowProduct['customer_nama'];   
                            if  ( $customer === 'Umum' ) {
                              echo "<b style='color: red;'>Umum</b>";
                            } else {
                              echo($customer);
                            }
                          ?> 
                      </td>
                      <td><?= $rowProduct['user_nama']; ?></td>
                      <td>Rp. <?= number_format($rowProduct['invoice_sub_total'], 0, ',', '.'); ?></td>
                      <td class="orderan-online-button">
                        <?php  
                          $no_wa = substr_replace($rowProduct['user_no_hp'],'62',0,1)
                        ?>
                          <a href="https://api.whatsapp.com/send?phone=<?= $no_wa; ?>&text=Transaksi dengan No. Invoice <?= $rowProduct['penjualan_invoice']; ?> atas Customer: <?= $customer; ?> Terjadi perubahan Data. Kasir yang merubah dengan nama <?= $rowProduct['user_nama']; ?>. Bisa dijelaskan kenapa terjadi perubahan transaksi ?" title="Tanya Kasir" target="_blank">
                              <button class="btn btn-success" type="submit">
                                 <i class="fa fa-whatsapp"></i>
                              </button>
                          </a>
                          <a href="edit-transaksi-detail?no=<?= $rowProduct['invoice_id']; ?>-invoice-<?= $rowProduct['penjualan_invoice']; ?>" title="Lihat Data" target="_blank">
                              <button class="btn btn-primary" type="submit">
                                 <i class="fa fa-eye"></i>
                              </button>
                          </a>
                      </td>
                  </tr>
                  <?php $i++; ?>
                  <?php } ?>
                  <!-- <tr>
                      <td colspan="6">
                        <b>Total</b>
                      </td>
                      <td>
                        Rp. <?php echo number_format($total, 0, ',', '.'); ?>
                      </td>
                  </tr> -->
                  </tbody>
                </table>
              </div>
            </div>
            <!-- /.card-body -->
          </div>
          <!-- /.card -->
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
    </section>
    <!-- /.content -->
    <?php  } ?>
  </div>
</div>



<?php include '_footer.php'; ?>

<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<script>
  $(function () {
    $("#laporan-penjualan-retur").DataTable();
  });
</script>
</body>
</html>