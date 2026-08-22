<?php

include '_header.php';
include '_nav.php';
include '_sidebar.php';
require_once 'aksi/marketplace-lib.php';

if (!marketplace_can_access((string) $levelLogin)) {
    echo "<script>document.location.href = 'bo';</script>";
    exit;
}

$cfg = marketplace_load_config();
$summary = marketplace_invoice_summary($conn, (int) $sessionCabang);
$filterCabang = (int) $sessionCabang;
$pending = marketplace_fetch_pending_orders(
    (string) ($cfg['sqlite_path'] ?? ''),
    $filterCabang > 0 ? $filterCabang : -1
);
$cabangList = marketplace_cabang_toko();
$sqliteOk = ($cfg['sqlite_path'] ?? '') !== '' && is_file($cfg['sqlite_path']);
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1><i class="fas fa-store"></i> Pesanan Belanja Online</h1>
          <p class="text-muted mb-0">Pantau pesanan dari <strong>belanja.numart.id</strong> — menunggu bayar &amp; yang sudah masuk invoice POS.</p>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Belanja Online</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <?php if (!$sqliteOk) { ?>
        <div class="alert alert-warning">
          <i class="fas fa-cog"></i>
          Untuk menampilkan pesanan <strong>menunggu bayar</strong>, salin
          <code>aksi/marketplace-config.example.php</code> menjadi
          <code>aksi/marketplace-config.php</code> dan isi path SQLite Laravel
          (<code>belanja.numart.id/database/database.sqlite</code>).
        </div>
      <?php } ?>

      <div class="row">
        <div class="col-md-4">
          <div class="info-box bg-success">
            <span class="info-box-icon"><i class="fas fa-check"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Invoice online (total)</span>
              <span class="info-box-number"><?= (int) $summary['total']; ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-box bg-info">
            <span class="info-box-icon"><i class="fas fa-calendar-day"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Invoice hari ini</span>
              <span class="info-box-number"><?= (int) $summary['hari_ini']; ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-box bg-warning">
            <span class="info-box-icon"><i class="fas fa-clock"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Menunggu bayar</span>
              <span class="info-box-number"><?= count($pending); ?></span>
            </div>
          </div>
        </div>
      </div>

      <?php if (!empty($cfg['admin_url'])) { ?>
        <p>
          <a href="<?= htmlspecialchars($cfg['admin_url'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
            <i class="fas fa-external-link-alt"></i> Panel admin Laravel
          </a>
        </p>
      <?php } ?>

      <div class="card card-warning card-outline">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-hourglass-half"></i> Menunggu pembayaran (BRI VA)</h3>
        </div>
        <div class="card-body table-responsive p-0">
          <table class="table table-hover table-sm">
            <thead>
              <tr>
                <th>No. Order</th>
                <th>Pelanggan</th>
                <th>HP</th>
                <th>Fulfillment</th>
                <th>Total</th>
                <th>Dibuat</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($pending === []) { ?>
                <tr><td colspan="6" class="text-center text-muted">Tidak ada pesanan pending<?= $sqliteOk ? '' : ' (konfigurasi SQLite belum aktif)'; ?>.</td></tr>
              <?php } else {
                  foreach ($pending as $p) { ?>
                <tr>
                  <td><code><?= htmlspecialchars($p['order_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?></code></td>
                  <td><?= htmlspecialchars($p['customer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars($p['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars($p['fulfillment_label'] ?? marketplace_cabang_label((int) ($p['fulfillment_cabang'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>Rp <?= number_format((float) ($p['grand_total'] ?? 0), 0, ',', '.'); ?></td>
                  <td><?= htmlspecialchars($p['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
              <?php }
                  } ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-file-invoice"></i> Sudah lunas — masuk invoice POS</h3>
          <?php if ((int) $sessionCabang < 1) { ?>
            <div class="card-tools">
              <select id="filterCabangInvoice" class="form-control form-control-sm" style="width:160px">
                <option value="0">Semua cabang</option>
                <?php foreach ($cabangList as $id => $nama) {
                    if ((int) $id === 0) {
                        continue;
                    } ?>
                  <option value="<?= (int) $id; ?>"><?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php } ?>
              </select>
            </div>
          <?php } ?>
        </div>
        <div class="card-body">
          <table id="tableMarketplaceInvoice" class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>No. Marketplace</th>
                <th>Tanggal</th>
                <th>Customer</th>
                <th>Cabang</th>
                <th>Total</th>
                <th>Invoice POS</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include '_footer.php'; ?>

<script>
$(function () {
  var cabangFilter = <?= (int) $sessionCabang > 0 ? (int) $sessionCabang : 0; ?>;

  var dt = $('#tableMarketplaceInvoice').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
      url: 'api/marketplace-pesanan-invoice-data.php',
      data: function (d) {
        d.cabang = cabangFilter;
      }
    },
    order: [[0, 'desc']],
    columns: [
      { data: 0 },
      { data: 1 },
      { data: 2 },
      { data: 3 },
      { data: 4, render: function (v) {
          var labels = <?= json_encode(marketplace_cabang_toko()); ?>;
          return labels[v] || ('Cabang ' + v);
        }
      },
      { data: 5, render: function (v) {
          return 'Rp ' + Number(v).toLocaleString('id-ID');
        }
      },
      { data: 6 },
      { data: 0, orderable: false, searchable: false, render: function (id) {
          return '<a class="btn btn-xs btn-primary" href="penjualan-zoom?no=' + btoa(String(id)) + '"><i class="fas fa-eye"></i></a>';
        }
      }
    ]
  });

  $('#filterCabangInvoice').on('change', function () {
    cabangFilter = parseInt(this.value, 10) || 0;
    dt.ajax.reload();
  });
});
</script>
