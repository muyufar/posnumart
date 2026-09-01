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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_tracking') {
    $orderNumber = trim((string) ($_POST['order_number'] ?? ''));
    $trackingStatus = trim((string) ($_POST['tracking_status'] ?? ''));
    $note = trim((string) ($_POST['tracking_note'] ?? ''));
    $belanjaPdo = marketplace_belanja_pdo($cfg);

    if (!$belanjaPdo) {
        $flash = ['success' => false, 'message' => 'Database belanja belum dikonfigurasi.'];
    } elseif (!array_key_exists($trackingStatus, marketplace_tracking_labels())) {
        $flash = ['success' => false, 'message' => 'Status pengiriman tidak valid.'];
    } elseif (marketplace_update_order_tracking($belanjaPdo, $orderNumber, $trackingStatus, $note !== '' ? $note : null)) {
        echo "<script>document.location.href='marketplace-pesanan?ok=" . urlencode('Status pengiriman diperbarui.') . "';</script>";
        exit;
    } else {
        $flash = ['success' => false, 'message' => 'Gagal memperbarui status pengiriman.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_payment') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $belanjaPdo = marketplace_belanja_pdo($cfg);
    $flash = marketplace_confirm_and_sync_order($conn, $belanjaPdo, $orderId, $cfg);
    if ($flash['success']) {
        echo "<script>document.location.href='marketplace-pesanan?ok=" . urlencode($flash['message']) . "';</script>";
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_member') {
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $belanjaPdo = marketplace_belanja_pdo($cfg);
    $flash = marketplace_set_member_verification($conn, $belanjaPdo, $customerId, $decision);
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

$shipmentOrders = marketplace_fetch_shipment_orders(
    $belanjaPdo,
    $filterCabang > 0 ? $filterCabang : -1
);
$trackingLabels = marketplace_tracking_labels();

$cabangList = marketplace_cabang_toko();

$verificationFilterCabang = (int) $sessionCabang > 0 ? (int) $sessionCabang : -1;
$pendingVerifications = marketplace_fetch_pending_member_verifications($conn, $verificationFilterCabang);
$pendingMembers = $pendingVerifications['rows'];
$verificationMigrationError = $pendingVerifications['error'];
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1><i class="fas fa-store"></i> Pesanan Belanja Online</h1>
          <p class="text-muted mb-0">Pantau pesanan <strong>belanja.numart.id</strong> — bukti transfer, COD, dan invoice POS.</p>
          <div class="mt-2">
            <a href="marketplace-min-order" class="btn btn-sm btn-outline-primary">
              <i class="fas fa-sliders-h"></i> Atur Minimal Pesanan
            </a>
            <a href="marketplace-diskon" class="btn btn-sm btn-outline-secondary">
              <i class="fas fa-percent"></i> Diskon Online
            </a>
          </div>
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
        <div class="col-md-3">
          <div class="info-box bg-teal">
            <span class="info-box-icon"><i class="fas fa-shipping-fast"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Dalam pengiriman</span>
              <span class="info-box-number"><?= count($shipmentOrders); ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="info-box bg-purple">
            <span class="info-box-icon"><i class="fas fa-id-card"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Verifikasi member</span>
              <span class="info-box-number"><?= count($pendingMembers); ?></span>
            </div>
          </div>
        </div>
      </div>

      <?php if ($verificationMigrationError) { ?>
        <div class="alert alert-warning">
          <i class="fas fa-database"></i>
          <?= htmlspecialchars($verificationMigrationError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php } ?>

      <div class="card card-purple card-outline">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-id-card"></i> Verifikasi member — KTP &amp; foto warung</h3>
        </div>
        <div class="card-body table-responsive p-0">
          <table class="table table-hover table-sm mb-0">
            <thead>
              <tr>
                <th>Member</th>
                <th>Kategori</th>
                <th>Cabang</th>
                <th>Upload</th>
                <th>Dokumen</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($pendingMembers === []) { ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada member menunggu verifikasi.</td></tr>
              <?php } else {
                  foreach ($pendingMembers as $m) {
                      $ktpPath = trim((string) ($m['customer_ktp_path'] ?? ''));
                      $warungPath = trim((string) ($m['customer_foto_warung_path'] ?? ''));
                      $ktpUrl = $ktpPath !== '' ? marketplace_verification_doc_url($ktpPath, $cfg) : '';
                      $warungUrl = $warungPath !== '' ? marketplace_verification_doc_url($warungPath, $cfg) : '';
                      ?>
                <tr>
                  <td>
                    <?= htmlspecialchars($m['customer_nama'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
                    <small class="text-muted">
                      <?= htmlspecialchars($m['customer_kartu'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                      · <?= htmlspecialchars($m['customer_tlpn'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </small>
                  </td>
                  <td><?= htmlspecialchars(marketplace_customer_category_label((int) ($m['customer_category'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars(marketplace_cabang_label((int) ($m['customer_cabang'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars($m['customer_verifikasi_at'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <?php if ($ktpUrl !== '') { ?>
                      <button type="button" class="btn btn-xs btn-outline-primary btn-preview-doc"
                              data-title="KTP — <?= htmlspecialchars($m['customer_nama'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                              data-url="<?= htmlspecialchars($ktpUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        KTP
                      </button>
                    <?php } ?>
                    <?php if ($warungUrl !== '') { ?>
                      <button type="button" class="btn btn-xs btn-outline-info btn-preview-doc"
                              data-title="Foto warung — <?= htmlspecialchars($m['customer_nama'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                              data-url="<?= htmlspecialchars($warungUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        Warung
                      </button>
                    <?php } ?>
                    <?php if ($ktpUrl === '' && $warungUrl === '') { ?>
                      <span class="text-muted">—</span>
                    <?php } ?>
                  </td>
                  <td>
                    <form method="post" class="d-inline" onsubmit="return confirm('Setujui verifikasi member ini? Member bisa COD.');">
                      <input type="hidden" name="action" value="verify_member">
                      <input type="hidden" name="customer_id" value="<?= (int) ($m['customer_id'] ?? 0); ?>">
                      <input type="hidden" name="decision" value="approved">
                      <button type="submit" class="btn btn-xs btn-success">
                        <i class="fas fa-check"></i> Setujui
                      </button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('Tolak verifikasi member ini?');">
                      <input type="hidden" name="action" value="verify_member">
                      <input type="hidden" name="customer_id" value="<?= (int) ($m['customer_id'] ?? 0); ?>">
                      <input type="hidden" name="decision" value="rejected">
                      <button type="submit" class="btn btn-xs btn-danger">
                        <i class="fas fa-times"></i> Tolak
                      </button>
                    </form>
                    <a class="btn btn-xs btn-secondary" href="customer-zoom?id=<?= (int) ($m['customer_id'] ?? 0); ?>">
                      Profil
                    </a>
                  </td>
                </tr>
              <?php }
                  } ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card card-teal card-outline">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-route"></i> Pemantauan pengiriman — pesanan online</h3>
        </div>
        <div class="card-body table-responsive p-0">
          <table class="table table-hover table-sm mb-0">
            <thead>
              <tr>
                <th>No. Order</th>
                <th>Pelanggan</th>
                <th>Status pengiriman</th>
                <th>Invoice POS</th>
                <th>Diperbarui</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($shipmentOrders === []) { ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada pesanan dalam proses pengiriman.</td></tr>
              <?php } else {
                  foreach ($shipmentOrders as $s) {
                      $trackStatus = (string) ($s['tracking_status'] ?? 'preparing');
                      ?>
                <tr>
                  <td>
                    <code><?= htmlspecialchars($s['order_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?></code><br>
                    <small class="text-muted"><?= htmlspecialchars($s['fulfillment_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                  </td>
                  <td>
                    <?= htmlspecialchars($s['customer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
                    <small class="text-muted"><?= htmlspecialchars($s['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                  </td>
                  <td>
                    <?= marketplace_tracking_badge($trackStatus); ?>
                    <?php if (!empty($s['tracking_note'])) { ?>
                      <br><small class="text-muted"><?= htmlspecialchars((string) $s['tracking_note'], ENT_QUOTES, 'UTF-8'); ?></small>
                    <?php } ?>
                  </td>
                  <td><code><?= htmlspecialchars($s['numart_invoice'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></code></td>
                  <td><?= htmlspecialchars($s['tracking_updated_at'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <form method="post" class="form-inline">
                      <input type="hidden" name="action" value="update_tracking">
                      <input type="hidden" name="order_number" value="<?= htmlspecialchars($s['order_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                      <select name="tracking_status" class="form-control form-control-sm mr-1 mb-1" required>
                        <?php foreach ($trackingLabels as $key => $label) { ?>
                          <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"<?= $trackStatus === $key ? ' selected' : ''; ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                          </option>
                        <?php } ?>
                      </select>
                      <input type="text" name="tracking_note" class="form-control form-control-sm mr-1 mb-1" placeholder="Catatan (opsional)" value="<?= htmlspecialchars((string) ($s['tracking_note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                      <button type="submit" class="btn btn-xs btn-primary mb-1">
                        <i class="fas fa-save"></i> Update
                      </button>
                    </form>
                  </td>
                </tr>
              <?php }
                  } ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer text-muted">
          Status otomatis tersinkron saat kurir diubah di <strong>Penjualan → Edit Kurir</strong> atau halaman kurir.
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

<div class="modal fade" id="modalDoc" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="docModalTitle">Dokumen verifikasi</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body text-center">
        <img id="docImage" src="" alt="Dokumen verifikasi" class="img-fluid" style="max-height:70vh;border-radius:8px">
      </div>
    </div>
  </div>
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

  $('.btn-preview-doc').on('click', function () {
    $('#docModalTitle').text($(this).data('title'));
    $('#docImage').attr('src', $(this).data('url'));
    $('#modalDoc').modal('show');
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
