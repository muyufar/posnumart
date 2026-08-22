<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>


	<!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Data Pembelian</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Pembelian</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>


    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">

          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Data barang Pembelian</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
              <table id="example1" class="table table-bordered table-striped">
                <thead>
                <tr>
                  <th style="width: 6%;">No.</th>
                  <th style="width: 13%;">Invoice</th>
                  <th>Tanggal</th>
                  <th>Supplier</th>
                  <th>Total</th>
                  <th>Bayar</th>
                  <th>Kembali</th>
                  <!-- <th>Kasir</th> -->
                  <th style="text-align: center; width: 16%">Aksi</th>
                </tr>
                </thead>
                <tbody>

                <?php 
                  $i = 1; 
                  $queryInvoice = $conn->query("SELECT invoice_pembelian.invoice_pembelian_id ,invoice_pembelian.pembelian_invoice, invoice_pembelian.pembelian_invoice_parent, invoice_pembelian.invoice_tgl, supplier.supplier_id, supplier.supplier_nama, supplier.supplier_company, invoice_pembelian.invoice_total, invoice_pembelian.invoice_bayar, invoice_pembelian.invoice_kembali, invoice_pembelian.invoice_pembelian_cabang, user.user_id, user.user_nama
                             FROM invoice_pembelian 
                             JOIN supplier ON invoice_pembelian.invoice_supplier = supplier.supplier_id
                             JOIN user ON invoice_pembelian.invoice_kasir = user.user_id
                             WHERE invoice_pembelian_cabang = $sessionCabang ORDER BY invoice_pembelian_id DESC
                             ");
                  while ($rowProduct = mysqli_fetch_array($queryInvoice)) {
                ?>
                <tr>
                  	<td><?= $i; ?></td>
                    <td><?= $rowProduct['pembelian_invoice']; ?></td>
                    <td><?= $rowProduct['invoice_tgl']; ?></td>
                    <td>
                      <?= $rowProduct['supplier_nama']; ?> - <?= $rowProduct['supplier_company']; ?> 
                    </td>
                    <td>Rp. <?= number_format($rowProduct['invoice_total'], 0, ',', '.'); ?></td>
                    <td>Rp. <?= number_format($rowProduct['invoice_bayar'], 0, ',', '.'); ?></td>
                    <td>Rp. <?= number_format($rowProduct['invoice_kembali'], 0, ',', '.'); ?></td>
                    <!-- <td><?= $rowProduct['user_nama'] ?></td> -->
                    <td class="orderan-online-button">
                      <?php $id = $rowProduct['invoice_pembelian_id']; ?>
                        <a href="pembelian-zoom?no=<?= $id; ?>-invoice-<?= $rowProduct['pembelian_invoice']; ?>" title="Lihat Data">
                            <button class="btn btn-primary" type="submit">
                               <i class="fa fa-eye"></i>
                            </button>
                        </a>
                    	  <a href="pembelian-edit?no=<?= $id; ?>-invoice-<?= $rowProduct['pembelian_invoice']; ?>" title="Edit Data" onclick="return confirm('Fitur ini digunkan untuk EDIT TRANSAKSI yang salah satu barang pembelian TIDAK JADI.. Apakah Anda Yakin !!!')">
                            <button class="btn btn-warning" type="submit">
                               <i class="fa fa-edit"></i>
                            </button>
                        </a>
                        <a href="nota-cetak-pembelian?no=<?= $id; ?>-invoice-<?= $rowProduct['pembelian_invoice']; ?>" title="Cetak Nota" target="_blank">
                            <button class="btn btn-success" type="submit">
                               <i class="fa fa-print"></i>
                            </button>
                        </a>
                        <?php if ( $levelLogin !== "kasir" ) { ?>
                        <a href="pembelian-delete-invoice?id=<?= $rowProduct['pembelian_invoice_parent']; ?>" onclick="return confirm('Apakah Anda Yakin Hapus Seluruh Data No. Invoice Pembelian <?= $rowProduct['pembelian_invoice']; ?> ?')" title="Delete Invoice">
                            <button class="btn btn-danger" type="submit" name="hapus">
                                <i class="fa fa-trash-o"></i>
                            </button>
                        </a>
                        <?php } ?>
                    </td>
                </tr>
                <?php $i++; ?>
                <?php } ?>
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
  </div>
</div>



<?php include '_footer.php'; ?>

<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<script>
  $(function () {
    $("#example1").DataTable();
  });
</script>
</body>
</html>