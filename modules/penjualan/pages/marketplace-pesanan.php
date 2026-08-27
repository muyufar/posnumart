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
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_payment') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $belanjaPdo = marketplace_belanja_pdo($cfg);
    $flash = marketplace_confirm_and_sync_order($conn, $belanjaPdo, $orderId, $cfg);
    if ($flash['success']) {
        echo "<script>document.location.href='marketplace-pesanan?ok=" . urlencode($flash['message']) . "';</script>";
        exit;
    }
}

if (isset($_GET['ok'])) {
    $flash = ['success' => true, 'message' => (string) $_GET['ok']];
}

$summary = marketplace_invoice_summary($conn, (int) $sessionCabang);
$filterCabang = (int) $sessionCabang;
$belanjaPdo = marketplace_belanja_pdo($cfg);
$belanjaOk = marketplace_belanja_configured($cfg) && $belanjaPdo !== null;
$openOrders = marketplace_fetch_open_orders(
    $belanjaPdo,
    $filterCabang > 0 ? $filterCabang : -1
);

$proofOrders = [];
$awaitingTransfer = [];
$codOrders = [];

foreach ($openOrders as $row) {
    $status = (string) ($row['status'] ?? '');
    $method = (string) ($row['payment_method'] ?? '');
    $hasProof = trim((string) ($row['payment_proof_path'] ?? '')) !== '';

    if ($status === 'proof_submitted' || ($method === 'transfer' && $hasProof)) {
        $proofOrders[] = $row;
    } elseif ($method === 'cod' && $status === 'pending_cod') {
        $codOrders[] = $row;
    } else {
        $awaitingTransfer[] = $row;
    }
}

$cabangList = marketplace_cabang_toko();
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1><i class="fas fa-store"></i> Pesanan Belanja Online</h1>
          <p class="text-muted mb-0">Pantau pesanan <strong>belanja.numart.id</strong> — bukti transfer, COD, dan invoice POS.</p>
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
      <?php if (!$belanjaOk) { ?>
        <div class="alert alert-warning">
          <i class="fas fa-cog"></i>
          Salin <code>aksi/marketplace-config.example.php</code> → <code>marketplace-config.php</code>,
          lalu isi koneksi MySQL <code>belanja_numart</code> (production) atau path SQLite (lokal).
        </div>
      <?php } ?>

      <?php if ($flash) { ?>
        <div class="alert alert-<?= !empty($flash['success']) ? 'success' : 'danger'; ?>">
          <?= htmlspecialchars($flash['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php } ?>

      <div class="row">
        <div class="col-md-3">
          <div class="info-box bg-success">
            <span class="info-box-icon"><i class="fas fa-check"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Invoice online</span>
              <span class="info-box-number"><?= (int) $summary['total']; ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="info-box bg-info">
            <span class="info-box-icon"><i class="fas fa-calendar-day"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Invoice hari ini</span>
              <span class="info-box-number"><?= (int) $summary['hari_ini']; ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="info-box bg-danger">
            <span class="info-box-icon"><i class="fas fa-receipt"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Bukti transfer</span>
              <span class="info-box-number"><?= count($proofOrders); ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="info-box bg-warning">
            <span class="info-box-icon"><i class="fas fa-hourglass-half"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Belum selesai</span>
              <span class="info-box-number"><?= count($openOrders); ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="card card-danger card-outline">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-image"></i> Bukti transfer — perlu verifikasi</h3>
        </div>
        <div class="card-body table-responsive p-0">
          <table class="table table-hover table-sm mb-0">
            <thead>
              <tr>
                <th>No. Order</th>
                <th>Pelanggan</th>
                <th>Total</th>
                <th>Upload</th>
                <th>Bukti</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($proofOrders === []) { ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada bukti transfer menunggu verifikasi.</td></tr>
              <?php } else {
                  foreach ($proofOrders as $p) {
                      $proofPath = trim((string) ($p['payment_proof_path'] ?? ''));
                      $proofUrl = $proofPath !== '' ? marketplace_proof_url($proofPath, $cfg) : '';
                      ?>
                <tr>
                  <td>
                    <code><?= htmlspecialchars($p['order_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?></code><br>
                    <?= marketplace_status_badge((string) ($p['status'] ?? '')); ?>
                  </td>
                  <td>
                    <?= htmlspecialchars($p['customer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
                    <small class="text-muted"><?= htmlspecialchars($p['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                  </td>
                  <td>Rp <?= number_format((float) ($p['grand_total'] ?? 0), 0, ',', '.'); ?></td>
                  <td><?= htmlspecialchars($p['payment_proof_at'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <?php if ($proofUrl !== '') { ?>
                      <a href="<?= htmlspecialchars($proofUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary">
                        <i class="fas fa-search"></i> Lihat
                      </a>
                      <button type="button" class="btn btn-xs btn-info btn-preview-proof"
                              data-proof="<?= htmlspecialchars($proofUrl, ENT_QUOTES, 'UTF-8'); ?>"
                              data-order="<?= htmlspecialchars($p['order_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        Preview
                      </button>
                    <?php } else { ?>
                      <span class="text-muted">—</span>
                    <?php } ?>
                  </td>
                  <td>
                    <button type="button" class="btn btn-xs btn-success btn-order-detail"
                            data-id="<?= (int) ($p['id'] ?? 0); ?>"
                            data-order="<?= htmlspecialchars($p['order_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                      Detail
                    </button>
                    <form method="post" class="d-inline" onsubmit="return confirm('Konfirmasi pembayaran dan buat invoice POS?');">
                      <input type="hidden" name="action" value="confirm_payment">
                      <input type="hidden" name="order_id" value="<?= (int) ($p['id'] ?? 0); ?>">
                      <button type="submit" class="btn btn-xs btn-primary">
                        <i class="fas fa-check"></i> Verifikasi
                      </button>
                    </form>
                  </td>
                </tr>
              <?php }
                  } ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card card-warning card-outline">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-money-bill-wave"></i> Menunggu transfer (belum upload bukti)</h3>
        </div>
        <div class="card-body table-responsive p-0">
          <table class="table table-hover table-sm mb-0">
            <thead>
              <tr>
                <th>No. Order</th>
                <th>Pelanggan</th>
                <th>Cabang</th>
                <th>Total</th>
                <th>Dibuat</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($awaitingTransfer === []) { ?>
                <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada pesanan transfer menunggu bukti.</td></tr>
              <?php } else {
                  foreach ($awaitingTransfer as $p) { ?>
                <tr>
                  <td><code><?= htmlspecialchars($p['order_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?></code></td>
                  <td>
                    <?= htmlspecialchars($p['customer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
                    <small class="text-muted"><?= htmlspecialchars($p['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                  </td>
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

      <div class="card card-info card-outline">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-truck"></i> COD — menunggu proses</h3>
        </div>
        <div class="card-body table-responsive p-0">
          <table class="table table-hover table-sm mb-0">
            <thead>
              <tr>
                <th>No. Order</th>
                <th>Pelanggan</th>
                <th>Alamat</th>
                <th>Total</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($codOrders === []) { ?>
                <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada pesanan COD aktif.</td></tr>
              <?php } else {
                  foreach ($codOrders as $p) { ?>
                <tr>
                  <td><code><?= htmlspecialchars($p['order_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?></code></td>
                  <td>
                    <?= htmlspecialchars($p['customer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
                    <small class="text-muted"><?= htmlspecialchars($p['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                  </td>
                  <td><small><?= htmlspecialchars($p['customer_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small></td>
                  <td>Rp <?= number_format((float) ($p['grand_total'] ?? 0), 0, ',', '.'); ?></td>
                  <td>
                    <button type="button" class="btn btn-xs btn-success btn-order-detail"
                            data-id="<?= (int) ($p['id'] ?? 0); ?>"
                            data-order="<?= htmlspecialchars($p['order_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                      Detail
                    </button>
                    <form method="post" class="d-inline" onsubmit="return confirm('Proses pesanan COD dan buat invoice POS?');">
                      <input type="hidden" name="action" value="confirm_payment">
                      <input type="hidden" name="order_id" value="<?= (int) ($p['id'] ?? 0); ?>">
                      <button type="submit" class="btn btn-xs btn-primary">
                        <i class="fas fa-check"></i> Proses
                      </button>
                    </form>
                  </td>
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

<div class="modal fade" id="modalProof" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Bukti transfer — <span id="proofOrderNo"></span></h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body text-center">
        <img id="proofImage" src="" alt="Bukti transfer" class="img-fluid" style="max-height:70vh;border-radius:8px">
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalOrderDetail" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detail pesanan — <span id="detailOrderNo"></span></h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" id="detailOrderBody">
        <p class="text-muted mb-0">Memuat...</p>
      </div>
    </div>
  </div>
</div>

<?php include '_footer.php'; ?>

<script>
var orderItemsCache = <?= json_encode(array_reduce($openOrders, function ($carry, $row) use ($belanjaPdo) {
    $id = (int) ($row['id'] ?? 0);
    if ($id > 0) {
        $carry[$id] = marketplace_fetch_order_items($belanjaPdo, $id);
    }

    return $carry;
}, []), JSON_UNESCAPED_UNICODE); ?>;

$(function () {
  var cabangFilter = <?= (int) $sessionCabang > 0 ? (int) $sessionCabang : 0; ?>;

  $('.btn-preview-proof').on('click', function () {
    $('#proofOrderNo').text($(this).data('order'));
    $('#proofImage').attr('src', $(this).data('proof'));
    $('#modalProof').modal('show');
  });

  $('.btn-order-detail').on('click', function () {
    var id = parseInt($(this).data('id'), 10);
    var orderNo = $(this).data('order');
    var items = orderItemsCache[id] || [];
    var html = '';

    if (items.length === 0) {
      html = '<p class="text-muted">Detail item tidak tersedia.</p>';
    } else {
      html = '<table class="table table-sm"><thead><tr><th>Barang</th><th>Qty</th><th>Subtotal</th></tr></thead><tbody>';
      items.forEach(function (it) {
        html += '<tr><td>' + $('<div>').text(it.barang_nama).html() +
          '<br><small class="text-muted">' + $('<div>').text(it.barang_kode).html() + '</small></td>' +
          '<td>' + it.qty + '</td>' +
          '<td>Rp ' + Number(it.line_total).toLocaleString('id-ID') + '</td></tr>';
      });
      html += '</tbody></table>';
    }

    $('#detailOrderNo').text(orderNo);
    $('#detailOrderBody').html(html);
    $('#modalOrderDetail').modal('show');
  });

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
