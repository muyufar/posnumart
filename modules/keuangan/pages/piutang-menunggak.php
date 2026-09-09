<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>

<?php  
  if ( $levelLogin === "kurir") {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
    exit;
  }  
?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Piutang Menunggak</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Piutang Menunggak</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <?php  
      $day  = date("Y-m")."-01";
      $data = query("
        SELECT 
            p.piutang_id,
            p.piutang_invoice,
            p.piutang_date,
            p.piutang_date_time,
            i.invoice_id,
            i.invoice_customer,
            i.invoice_date,
            i.invoice_sub_total,
            i.invoice_piutang_dp,
            i.invoice_bayar,
            i.invoice_kembali,
            i.invoice_piutang_jatuh_tempo,
            COALESCE(c.customer_nama, 'Umum') AS customer_nama,
            COALESCE(c.customer_tlpn, '') AS customer_tlpn
        FROM (
            SELECT piutang_invoice, MAX(piutang_id) AS max_piutang_id
            FROM piutang
            WHERE piutang_cabang = $sessionCabang
            GROUP BY piutang_invoice
        ) p_sub
        JOIN piutang p ON p.piutang_id = p_sub.max_piutang_id
        JOIN invoice i ON p.piutang_invoice = i.penjualan_invoice AND i.invoice_cabang = $sessionCabang
        LEFT JOIN customer c ON i.invoice_customer = c.customer_id AND c.customer_cabang = $sessionCabang
        WHERE p.piutang_date < '$day'
          AND p.piutang_date IS NOT NULL
          AND p.piutang_date != ''
          AND p.piutang_date != '0000-00-00'
        ORDER BY p.piutang_id DESC
      ");
    ?>
    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Data Piutang Menunggak</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
                <table id="example1" class="table table-bordered table-striped">
                  <thead>
                  <tr>
                    <th style="width: 5%;">No.</th>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Transaksi</th>
                    <th>Terakhir Bayar</th>
                    <th>Menunggak</th>
                    <th>Jatuh Tempo</th>
                    <th style="text-align: center; width: 15%;">Aksi</th>
                  </tr>
                  </thead>
                  <tbody>

                  <?php $i = 1; ?>
                  <?php foreach ( $data as $row ) : ?>
                  <?php  
                      $piutang_invoice             = $row['piutang_invoice'];
                      $piutang_date                = $row['piutang_date'];
                      $piutang_date_time           = $row['piutang_date_time'];
                      $invoice_id                  = $row['invoice_id'];
                      $invoice_customer            = (int) ($row['invoice_customer'] ?? 0);
                      $invoice_date                = $row['invoice_date'];
                      $invoice_sub_total           = (float) ($row['invoice_sub_total'] ?? 0);
                      $invoice_piutang_dp          = (float) ($row['invoice_piutang_dp'] ?? 0);
                      $invoice_bayar               = (float) ($row['invoice_bayar'] ?? 0);
                      $invoice_kembali             = (float) ($row['invoice_kembali'] ?? 0);
                      $invoice_piutang_jatuh_tempo = $row['invoice_piutang_jatuh_tempo'];
                      $customer_nama               = $row['customer_nama'];
                      $customer_tlpn               = $row['customer_tlpn'];
                  ?> 
                  <tr>
                      <td><?= $i; ?></td>
                      <td><?= htmlspecialchars($piutang_invoice); ?></td>
                      <td><?= htmlspecialchars($customer_nama); ?></td>
                      <td><?= !empty($invoice_date) && $invoice_date !== '0000-00-00' ? tanggal_indo($invoice_date) : '-'; ?></td>
                      <td><?= htmlspecialchars($piutang_date_time); ?></td>
                      <td>
                        <?php  
                          $dateNunggak = '-';
                          if (!empty($piutang_date) && $piutang_date !== '0000-00-00') {
                              try {
                                  $tanggal = new DateTime($piutang_date);
                                  $today   = new DateTime('today');
                                  $tahun   = $today->diff($tanggal)->y;
                                  $bulan   = $today->diff($tanggal)->m;
                                  $hari    = $today->diff($tanggal)->d;

                                  if ( $tahun < 1 && $bulan > 0 && $hari > 0) {
                                    $dateNunggak = $bulan." bulan, ".$hari." hari ";
                                  } elseif ( $tahun < 1 && $bulan < 1 && $hari > 0 ) {
                                    $dateNunggak = $hari." hari ";
                                  } elseif ( $tahun < 1 && $bulan > 0 && $hari < 1 ) {
                                    $dateNunggak = $bulan." bulan ";
                                  } elseif ( $tahun > 0 && $bulan < 1 && $hari > 0 ) {
                                    $dateNunggak = $tahun." tahun, ".$hari." hari ";
                                  } elseif ( $tahun > 0 && $bulan < 1 && $hari < 1 ) {
                                    $dateNunggak = $tahun." tahun ";
                                  } else {
                                    $dateNunggak = $tahun." tahun, ".$bulan." bulan, ".$hari." hari ";
                                  }
                              } catch (Exception $e) {
                                  $dateNunggak = '-';
                              }
                          }
                          echo $dateNunggak;
                        ?>
                      </td>
                      <td><?= !empty($invoice_piutang_jatuh_tempo) && $invoice_piutang_jatuh_tempo !== '0000-00-00' ? tanggal_indo($invoice_piutang_jatuh_tempo) : '-'; ?></td>
                      <td class="orderan-online-button">
                         <a href="piutang-cicilan?no=<?= base64_encode($invoice_id); ?>">
                              <button class='btn btn-primary' title='Cicilan'>
                                <i class='fa fa-money'></i>
                              </button>
                            </a>

                            <?php  
                              $no_wa = !empty($customer_tlpn) ? substr_replace($customer_tlpn,'62',0,1) : '';
                            ?>
                            <a href="https://api.whatsapp.com/send?phone=<?= $no_wa; ?>&text=Halo <?= urlencode($customer_nama);?>, Kami dari *<?= urlencode($dataTokoLogin['toko_nama'] ?? ''); ?> <?= urlencode($dataTokoLogin['toko_kota'] ?? ''); ?>* memberikan informasi bahwa transaksi *No Invoice <?= urlencode($piutang_invoice);?> dengan jumlah transaksi Rp <?= number_format($invoice_sub_total, 0, ',', '.'); ?>* Sudah Menunggak Pembayaran Piutang Selama <?= urlencode($dateNunggak); ?>dari terakhir melakukan cicilan pada <?= urlencode($piutang_date_time); ?>.%0A%0ASub Total: Rp <?= number_format($invoice_sub_total, 0, ',', '.'); ?>%2C%0ADP: Rp <?= number_format($invoice_piutang_dp, 0, ',', '.'); ?>%2C%0ADP ditambah Total Cicilan: Rp <?= number_format($invoice_bayar, 0, ',', '.'); ?> %2C%0A*Sisa Piutang: Rp <?= number_format($invoice_kembali, 0, ',', '.'); ?>*%2C%0A%0A%0AMohon Segera Dilunasi" target="_blank">
                              <button class='btn btn-success' title='Cicilan'>
                                <i class='fa fa-whatsapp'></i>
                              </button>
                            </a>

                            <a href="nota-cetak-piutang?no=<?= $invoice_id; ?>" target="_blank">
                              <button class='btn btn-warning' title="Cetak Nota">
                                <i class='fa fa-print'></i>
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

<?php include '_footer.php'; ?>

<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<script>
  $(function () {
    if ($.fn.DataTable.isDataTable('#example1')) {
      $('#example1').DataTable().destroy();
    }
    $("#example1").DataTable({
      "responsive": true,
      "autoWidth": false,
      "pageLength": 10,
      "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]]
    });
  });
</script>
</body>
</html>
