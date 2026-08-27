<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

$id = abs((int) ($_GET['id'] ?? 0));
$rows = query("SELECT * FROM customer WHERE customer_id = $id");
if ($rows === [] || !isset($rows[0])) {
    echo "<script>alert('Customer tidak ditemukan'); document.location.href='customer';</script>";
    exit;
}
$customer = $rows[0];

$poinDb = (int) ($customer['customer_poin'] ?? 0);
$qpoin = query("SELECT SUM(invoice_total) AS total_transaksi FROM invoice WHERE invoice_customer = $id");
$poinHitung = 0;
if ($qpoin !== [] && isset($qpoin[0]['total_transaksi'])) {
    $poinHitung = (int) floor(((float) $qpoin[0]['total_transaksi']) / 100000);
}

$hasVerifikasi = function_exists('customer_has_column') && customer_has_column($conn, 'customer_verifikasi_status');
$ktpUrl = '';
$warungUrl = '';
if ($hasVerifikasi) {
    require_once numart_path('aksi/marketplace-lib.php');
    $mpCfg = marketplace_load_config();
    $ktpPath = trim((string) ($customer['customer_ktp_path'] ?? ''));
    $warungPath = trim((string) ($customer['customer_foto_warung_path'] ?? ''));
    $ktpUrl = $ktpPath !== '' ? marketplace_verification_doc_url($ktpPath, $mpCfg) : '';
    $warungUrl = $warungPath !== '' ? marketplace_verification_doc_url($warungPath, $mpCfg) : '';
}

$cat = (int) ($customer['customer_category'] ?? 0);
$catLabel = $cat === 1 ? 'Member Retail' : ($cat === 2 ? 'Member Grosir' : 'Umum');
$statusLabel = ((string) ($customer['customer_status'] ?? '') === '1') ? 'Active' : 'Not Active';
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Detail Customer</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="customer">Data Customer</a></li>
            <li class="breadcrumb-item active">Detail</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12">
          <div class="card card-primary">
            <div class="card-header">
              <h3 class="card-title"><?= htmlspecialchars((string) $customer['customer_nama'], ENT_QUOTES, 'UTF-8'); ?></h3>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                  <dl class="row mb-0">
                    <dt class="col-sm-4">Nama</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars((string) $customer['customer_nama'], ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-4">No. WA</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars((string) $customer['customer_tlpn'], ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-4">Email</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars((string) ($customer['customer_email'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-4">Tanggal Lahir</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars((string) ($customer['customer_birthday'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-4">No Kartu</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars((string) ($customer['customer_kartu'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-4">Kategori</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-4">Poin (DB)</dt>
                    <dd class="col-sm-8"><?= $poinDb; ?></dd>
                    <dt class="col-sm-4">Poin (hitung omzet)</dt>
                    <dd class="col-sm-8"><?= $poinHitung; ?></dd>
                    <dt class="col-sm-4">Dibuat</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars((string) ($customer['customer_create'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-4">Cabang</dt>
                    <dd class="col-sm-8"><?= (int) ($customer['customer_cabang'] ?? 0); ?></dd>
                  </dl>
                </div>
                <div class="col-md-6">
                  <h5><i class="fas fa-map-marker-alt"></i> Alamat</h5>
                  <p><?= nl2br(htmlspecialchars((string) ($customer['customer_alamat'] ?? '-'), ENT_QUOTES, 'UTF-8')); ?></p>
                  <dl class="row mb-0">
                    <dt class="col-sm-4">Dusun</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars((string) ($customer['alamat_dusun'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-4">Desa</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars((string) ($customer['alamat_desa'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-4">Kecamatan</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars((string) ($customer['alamat_kecamatan'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-4">Kabupaten</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars((string) ($customer['alamat_kabupaten'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-4">Provinsi</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars((string) ($customer['alamat_provinsi'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                  </dl>

                  <?php if ($hasVerifikasi) : ?>
                    <hr>
                    <h5><i class="fas fa-id-card"></i> Verifikasi Belanja Online</h5>
                    <p><?= customer_verifikasi_badge((string) ($customer['customer_verifikasi_status'] ?? 'none')); ?></p>
                    <p class="mb-1"><small class="text-muted">Waktu: <?= htmlspecialchars((string) ($customer['customer_verifikasi_at'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></small></p>
                    <p class="mb-1">
                      KTP:
                      <?php if ($ktpUrl !== '') : ?>
                        <a href="<?= htmlspecialchars($ktpUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Lihat dokumen</a>
                      <?php else : ?>
                        <span class="text-muted">Belum ada</span>
                      <?php endif; ?>
                    </p>
                    <p class="mb-0">
                      Foto warung:
                      <?php if ($warungUrl !== '') : ?>
                        <a href="<?= htmlspecialchars($warungUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Lihat dokumen</a>
                      <?php else : ?>
                        <span class="text-muted">Belum ada</span>
                      <?php endif; ?>
                    </p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="card-footer text-right">
              <a href="customer-edit?id=<?= $id; ?>" class="btn btn-primary">Edit</a>
              <a href="customer" class="btn btn-success">Kembali</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include '_footer.php'; ?>
