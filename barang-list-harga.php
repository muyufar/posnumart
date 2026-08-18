<?php
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php';
  error_reporting(0);
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
  require_once __DIR__ . '/aksi/barang-list-harga-lib.php';

  barang_harga_beli_rata_ensure_column($conn);

  $kategoriFilter = isset($_GET['kategori_id']) ? (string) $_GET['kategori_id'] : 'semua';
  $marginFilter   = isset($_GET['margin']) ? (string) $_GET['margin'] : 'semua';

  $daftarKategori = barangListHarga_daftarKategori($conn, $sessionCabang);
  $ringkasan      = barangListHarga_ringkasan($conn, $sessionCabang, $kategoriFilter, $marginFilter);

  $paramExport = http_build_query(array(
    'kategori_id' => $kategoriFilter,
    'margin'      => $marginFilter,
  ));
?>

	<!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>List Harga Barang</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item"><a href="barang">Barang</a></li>
              <li class="breadcrumb-item active">List Harga</li>
            </ol>
          </div>
          <div class="tambah-data">
            <a href="barang" class="btn btn-primary">Kembali</a>
            <a href="export-barang-list-harga.php?<?= $paramExport; ?>" target="_blank" class="btn btn-success">
              <i class="fas fa-file-excel"></i> Export Excel
            </a>
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
              <h3 class="card-title">Daftar Harga Jual &amp; Persentase Laba</h3>
            </div>

            <div class="card-body">
              <form method="get" action="barang-list-harga" class="mb-3">
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Kategori</label>
                      <select name="kategori_id" class="form-control">
                        <option value="semua">Semua Kategori</option>
                        <?php foreach ($daftarKategori as $kat): ?>
                          <option value="<?= htmlspecialchars($kat['kategori_id'], ENT_QUOTES, 'UTF-8'); ?>"
                            <?= ((string) $kat['kategori_id'] === $kategoriFilter) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($kat['kategori_nama'], ENT_QUOTES, 'UTF-8'); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Kondisi Margin (Harga Umum)</label>
                      <select name="margin" class="form-control">
                        <option value="semua" <?= $marginFilter === 'semua' ? 'selected' : ''; ?>>Semua</option>
                        <option value="rugi" <?= $marginFilter === 'rugi' ? 'selected' : ''; ?>>Rugi (di bawah harga beli)</option>
                        <option value="tipis" <?= $marginFilter === 'tipis' ? 'selected' : ''; ?>>Margin tipis (&lt; 5%)</option>
                        <option value="belum_lengkap" <?= $marginFilter === 'belum_lengkap' ? 'selected' : ''; ?>>Harga/HPP belum lengkap</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>&nbsp;</label>
                      <button type="submit" class="btn btn-primary form-control">
                        <i class="fa fa-filter"></i> Terapkan Filter
                      </button>
                    </div>
                  </div>
                </div>
              </form>

              <div class="row">
                <div class="col-md-3 col-6">
                  <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-boxes"></i></span>
                    <div class="info-box-content">
                      <span class="info-box-text">Total Barang</span>
                      <span class="info-box-number"><?= number_format((int) $ringkasan['total_barang'], 0, ',', '.'); ?></span>
                    </div>
                  </div>
                </div>
                <div class="col-md-3 col-6">
                  <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-percentage"></i></span>
                    <div class="info-box-content">
                      <span class="info-box-text">Margin Umum (tertimbang)</span>
                      <span class="info-box-number"><?= blhPersen($ringkasan['rata_persen_umum']); ?></span>
                    </div>
                  </div>
                </div>
                <div class="col-md-3 col-6">
                  <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span>
                    <div class="info-box-content">
                      <span class="info-box-text">Margin Tipis (&lt; 5%)</span>
                      <span class="info-box-number"><?= number_format((int) $ringkasan['tipis'], 0, ',', '.'); ?></span>
                    </div>
                  </div>
                </div>
                <div class="col-md-3 col-6">
                  <div class="info-box">
                    <span class="info-box-icon bg-danger"><i class="fas fa-arrow-down"></i></span>
                    <div class="info-box-content">
                      <span class="info-box-text">Rugi / Belum Lengkap</span>
                      <span class="info-box-number">
                        <?= number_format((int) $ringkasan['rugi'], 0, ',', '.'); ?>
                        / <?= number_format((int) $ringkasan['belum_lengkap'], 0, ',', '.'); ?>
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <p class="text-muted mb-2">
                <i class="fa fa-info-circle"></i>
                Persentase dihitung terhadap harga beli (HPP): <code>(harga jual - harga beli) / harga beli</code>.
                Kolom laba mengacu pada harga satuan 1.
                Tabel bisa digeser ke kanan untuk melihat seluruh kolom laba.
              </p>

              <div class="table-responsive">
                <table id="tabel-list-harga" class="table table-bordered table-striped table-sm tabel-list-harga">
                  <thead>
                    <tr>
                      <th rowspan="3" style="width: 40px;">No.</th>
                      <th rowspan="3">Kode Barang</th>
                      <th rowspan="3">Nama Barang</th>
                      <th rowspan="3">Kategori</th>
                      <th rowspan="3" class="text-right">Hrg Beli</th>
                      <th colspan="3" class="text-center blh-head-s1">Harga Jual Satuan 1</th>
                      <th colspan="3" class="text-center blh-head-s2">Harga Jual Satuan 2</th>
                      <th colspan="6" class="text-center blh-head-laba">Laba (Satuan 1)</th>
                      <th rowspan="3" class="text-center" style="width: 110px;">Aksi</th>
                    </tr>
                    <tr>
                      <th rowspan="2" class="text-right blh-sub-s1">Umum</th>
                      <th rowspan="2" class="text-right blh-sub-s1">Retail</th>
                      <th rowspan="2" class="text-right blh-sub-s1">Grosir</th>
                      <th rowspan="2" class="text-right blh-sub-s2">Umum</th>
                      <th rowspan="2" class="text-right blh-sub-s2">Retail</th>
                      <th rowspan="2" class="text-right blh-sub-s2">Grosir</th>
                      <th colspan="2" class="text-center blh-sub-umum">Umum</th>
                      <th colspan="2" class="text-center blh-sub-retail">Retail</th>
                      <th colspan="2" class="text-center blh-sub-grosir">Grosir</th>
                    </tr>
                    <tr>
                      <th class="text-right blh-sub-umum">Jml</th>
                      <th class="text-center blh-sub-umum">%</th>
                      <th class="text-right blh-sub-retail">Jml</th>
                      <th class="text-center blh-sub-retail">%</th>
                      <th class="text-right blh-sub-grosir">Jml</th>
                      <th class="text-center blh-sub-grosir">%</th>
                    </tr>
                  </thead>
                  <tbody>
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

<style>
  .tabel-list-harga thead th {
    vertical-align: middle;
    font-size: 11px;
    white-space: nowrap;
    padding: 4px 6px;
  }
  .tabel-list-harga tbody td {
    font-size: 12px;
    white-space: nowrap;
    padding: 4px 6px;
  }
  /* Dua kolom teks ini boleh melipat supaya tabel tidak melebar tanpa perlu. */
  .tabel-list-harga td.blh-teks {
    white-space: normal;
    min-width: 160px;
  }
  .blh-head-s1  { background-color: #e53935; color: #fff; }
  .blh-head-s2  { background-color: #fdd835; color: #333; }
  .blh-head-laba{ background-color: #455a64; color: #fff; }
  .blh-sub-s1   { background-color: #ffcdd2; }
  .blh-sub-s2   { background-color: #fff9c4; }
  .blh-sub-umum   { background-color: #ffe0b2; }
  .blh-sub-retail { background-color: #ffecb3; }
  .blh-sub-grosir { background-color: #c8e6c9; }
  .blh-badge {
    display: inline-block;
    min-width: 54px;
    padding: 1px 6px;
    border-radius: 3px;
    font-weight: 600;
  }
  .blh-sehat  { background-color: #c8e6c9; color: #1b5e20; }
  .blh-tipis  { background-color: #ffe082; color: #7f5b00; }
  .blh-rugi   { background-color: #ef9a9a; color: #7f0000; }
  .blh-kosong { background-color: #eceff1; color: #78909c; }
  .blh-aksi {
    white-space: nowrap;
  }
  .blh-aksi .btn {
    margin: 1px;
  }
</style>

<script>
$(document).ready(function () {
    var kategoriFilter = <?= json_encode($kategoriFilter); ?>;
    var marginFilter   = <?= json_encode($marginFilter); ?>;

    var kolomAngka  = [4, 5, 6, 7, 8, 9, 10, 11, 13, 15];
    var kolomPersen = [12, 14, 16];

    var table = $('#tabel-list-harga').DataTable({
        "processing": true,
        "serverSide": true,
        "pageLength": 50,
        "lengthMenu": [[25, 50, 100, 250], [25, 50, 100, 250]],
        "ajax": "barang-data-list-harga.php?kategori_id=" + encodeURIComponent(kategoriFilter) +
                "&margin=" + encodeURIComponent(marginFilter),
        "order": [[2, 'asc']],
        "autoWidth": false,
        "columnDefs": [
            { "targets": 0, "orderable": false, "searchable": false, "className": "text-center" },
            { "targets": [2, 3], "className": "blh-teks" },
            { "targets": kolomAngka, "searchable": false, "className": "text-right" },
            { "targets": kolomPersen, "searchable": false, "className": "text-center" },
            {
                "targets": -1,
                "orderable": false,
                "searchable": false,
                "className": "text-center",
                "data": null,
                "defaultContent":
                  `<div class="blh-aksi">
                      <button class="btn btn-success btn-sm tblZoom" title="Lihat Data">
                          <i class="fa fa-eye"></i>
                      </button>
                      <button class="btn btn-primary btn-sm tblEdit" title="Edit Data">
                          <i class="fa fa-edit"></i>
                      </button>
                      <?php if ($sessionCabang == 0): ?>
                      <button class="btn btn-danger btn-sm tblDelete" title="Hapus Barang">
                          <i class="fa fa-trash-o"></i>
                      </button>
                      <?php endif; ?>
                  </div>`
            }
        ]
    });

    table.on('draw.dt', function () {
        var info = table.page.info();
        table.column(0, { search: 'applied', order: 'applied', page: 'applied' }).nodes().each(function (cell, i) {
            cell.innerHTML = i + 1 + info.start;
        });
    });

    $('#tabel-list-harga tbody').on('click', '.tblZoom', function () {
        var data = table.row($(this).parents('tr')).data();
        window.open('barang-zoom?id=' + btoa(data[0]), '_blank');
    });

    $('#tabel-list-harga tbody').on('click', '.tblEdit', function () {
        var data = table.row($(this).parents('tr')).data();
        window.open('barang-edit?id=' + btoa(data[0]), '_blank');
    });

    $('#tabel-list-harga tbody').on('click', '.tblDelete', function () {
        var data = table.row($(this).parents('tr')).data();
        var nama = data[2];
        if (confirm('Apakah Anda Yakin Hapus Produk ' + nama + ' ?')) {
            window.location.href = 'barang-delete?id=' + btoa(data[0]);
        }
    });
});
</script>

<?php include '_footer.php'; ?>
