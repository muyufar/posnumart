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
  
$i = 1;
$query = "
  SELECT 
    barang.barang_id, barang.barang_kode, barang.barang_nama, barang.barang_harga, barang.barang_terjual, barang.barang_cabang, kategori.kategori_id, kategori.kategori_nama, satuan.satuan_id, satuan.satuan_nama 
  FROM barang 
  JOIN kategori 
    ON barang.kategori_id = kategori.kategori_id 
  JOIN satuan 
    ON barang.satuan_id = satuan.satuan_id 
  WHERE 
    barang_cabang = '" . $sessionCabang . "' && 
    barang_terjual > 0
  ORDER BY barang_terjual DESC";

$get = $conn->query($query);
$results = [];
while ($row = mysqli_fetch_assoc($get)) {
  $results['label'][] = $row['barang_nama'];
  $results['value'][] = $row['barang_terjual'];
}
$gafik = json_encode($results);
?>

	<!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Data Barang Terlaris</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Barang Terlaris</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>


    <!-- Main content -->
    <section class="content">
      <div class="row">
          <div class="col-6">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Grafik batang</h3>
            <div class="card-body">
              <div class="">
                <canvas id="myChart" style="width: 100%; height: 300px;"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Grafik Pie</h3>
            <div class="card-body">
              <div class="">
                <canvas id="myChart2" style="width: 100%; height: 300px;"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>
        <div class="col-12">

          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Data barang Keseluruhan</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <div class="table-auto">
                <table id="laporan-data-barang-terlaris" class="table table-bordered table-striped table-laporan">
                  <thead>
                  <tr>
                    <th style="width: 6%;">No.</th>
                    <th style="width: 13%;">Kode Barang</th>
                    <th>Nama</th>
                    <th>Kategori</th>
                    <th>Harga</th>
                    <th>Terjual</th>
                    <th>Satuan</th>
                  </tr>
                  </thead>
                  <tbody>

                  <?php 
                    $i = 1; 
                   $product = $conn->query($query);
                  while ($rowProduct = mysqli_fetch_array($product)) {
                  ?>
                  <tr>
                    	<td><?= $i; ?></td>
                      <td><?= $rowProduct['barang_kode']; ?></td>
                      <td><?= $rowProduct['barang_nama']; ?></td>
                      <td><?= $rowProduct['kategori_nama']; ?></td>
                      <td>Rp. <?= number_format($rowProduct['barang_harga'], 0, ',', '.'); ?></td>
                      <td>
                        <b><?= $rowProduct['barang_terjual']; ?></b>
                      </td>
                      <td><?= $rowProduct['satuan_nama']; ?></td>
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
    $("#laporan-data-barang-terlaris").DataTable();
  });
  
  const chartData = <?php echo $gafik; ?>;

  function getRandomColor() {
    const r = Math.floor(Math.random() * 256);
    const g = Math.floor(Math.random() * 256);
    const b = Math.floor(Math.random() * 256);
    return `rgba(${r}, ${g}, ${b},`;
  }

  const bg_border = chartData?.value?.map(() => {
    const color = getRandomColor()
    return {
      bg: color + ' 0.2)',
      border: color + ' 1)'
    }
  })

  const backgroundColors = bg_border?.map(item => item.bg);
  const borderColors = bg_border?.map(item => item.border);
  console.log("🚀 ~ constbg_border=chartData?.value?.map ~ bg_border:", bg_border)
  const data = {
    labels: chartData?.label,
    datasets: [{
      label: 'Grafik Batang',
      data: chartData?.value,
      backgroundColor: backgroundColors,
      borderColor: borderColors,
      borderWidth: 1
    }]
  }
  const ctx = $('#myChart')
  const config = {
    type: 'bar',
    data: data,
    options: {
      scales: {
        yAxes: [{
          ticks: {
            beginAtZero: true
          }
        }]
      }
    }
  };
  const myChart = new Chart(ctx, config);
  const myChart2 = new Chart($('#myChart2'), {
    type: 'doughnut',
    data: data
  });
</script>
</body>
</html>