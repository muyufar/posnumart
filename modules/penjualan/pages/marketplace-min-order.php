<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
include '_header.php';
include '_nav.php';
include '_sidebar.php';
require_once numart_path('aksi/marketplace-lib.php');

if (!marketplace_can_access((string) $levelLogin)) {
    echo "<script>document.location.href = 'bo';</script>";
    exit;
}

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_min_order') {
    $retailRaw = preg_replace('/[^\d]/', '', (string) ($_POST['min_order_retail'] ?? '0'));
    $grosirRaw = preg_replace('/[^\d]/', '', (string) ($_POST['min_order_grosir'] ?? '0'));
    $flash = marketplace_min_order_save($conn, (int) $retailRaw, (int) $grosirRaw);
    if (!empty($flash['success'])) {
        echo "<script>document.location.href='marketplace-min-order?ok=" . urlencode($flash['message']) . "';</script>";
        exit;
    }
}

if (isset($_GET['ok'])) {
    $flash = ['success' => true, 'message' => (string) $_GET['ok']];
}

$settings = marketplace_min_order_get($conn);
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1><i class="fas fa-sliders-h"></i> Minimal Pesanan Belanja Online</h1>
          <p class="text-muted mb-0">
            Atur batas minimal belanja (Rp) untuk member retail &amp; grosir di
            <strong>belanja.numart.id</strong>. Perubahan langsung dipakai saat checkout.
          </p>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="marketplace-pesanan">Belanja Online</a></li>
            <li class="breadcrumb-item active">Minimal Pesanan</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <?php if ($flash) { ?>
        <div class="alert alert-<?= !empty($flash['success']) ? 'success' : 'danger'; ?>">
          <?= htmlspecialchars($flash['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php } ?>

      <?php if (empty($settings['ok'])) { ?>
        <div class="alert alert-warning">
          Tabel <code>marketplace_settings</code> belum siap.
          Jalankan SQL <code>db/marketplace_settings.sql</code> atau pastikan user DB punya hak CREATE TABLE.
        </div>
      <?php } ?>

      <div class="row">
        <div class="col-lg-8">
          <div class="card card-primary">
            <div class="card-header">
              <h3 class="card-title">Nilai minimal pembelian</h3>
            </div>
            <form method="post">
              <input type="hidden" name="action" value="save_min_order">
              <div class="card-body">
                <div class="form-group">
                  <label for="min_order_retail">Member Retail (Rp)</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text">Rp</span>
                    </div>
                    <input
                      type="text"
                      inputmode="numeric"
                      class="form-control js-rupiah"
                      id="min_order_retail"
                      name="min_order_retail"
                      value="<?= htmlspecialchars(number_format((int) $settings['retail'], 0, ',', '.'), ENT_QUOTES, 'UTF-8'); ?>"
                      required
                    >
                  </div>
                  <small class="form-text text-muted">
                    Default: Rp 500.000. Member retail (kategori 1) tidak bisa checkout di bawah nilai ini.
                  </small>
                </div>

                <div class="form-group">
                  <label for="min_order_grosir">Member Grosir (Rp)</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text">Rp</span>
                    </div>
                    <input
                      type="text"
                      inputmode="numeric"
                      class="form-control js-rupiah"
                      id="min_order_grosir"
                      name="min_order_grosir"
                      value="<?= htmlspecialchars(number_format((int) $settings['grosir'], 0, ',', '.'), ENT_QUOTES, 'UTF-8'); ?>"
                      required
                    >
                  </div>
                  <small class="form-text text-muted">
                    Default: Rp 1.000.000. Member grosir (kategori 2) tidak bisa checkout di bawah nilai ini.
                  </small>
                </div>

                <div class="alert alert-info mb-0">
                  <i class="fas fa-info-circle"></i>
                  Customer umum (bukan member) tidak terkena batas minimal.
                  Isi <strong>0</strong> jika ingin menonaktifkan batas untuk tier tersebut.
                </div>
              </div>
              <div class="card-footer">
                <button type="submit" class="btn btn-primary" <?= empty($settings['ok']) ? 'disabled' : ''; ?>>
                  <i class="fas fa-save"></i> Simpan
                </button>
                <a href="marketplace-pesanan" class="btn btn-default">Kembali ke Pesanan</a>
              </div>
            </form>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card card-outline card-secondary">
            <div class="card-header">
              <h3 class="card-title">Ringkasan aktif</h3>
            </div>
            <div class="card-body">
              <dl class="mb-0">
                <dt>Retail</dt>
                <dd class="h5">Rp <?= number_format((int) $settings['retail'], 0, ',', '.'); ?></dd>
                <dt>Grosir</dt>
                <dd class="h5 mb-0">Rp <?= number_format((int) $settings['grosir'], 0, ',', '.'); ?></dd>
              </dl>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
(function () {
  function formatRupiah(n) {
    var s = String(n).replace(/\D/g, '');
    return s.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }
  document.querySelectorAll('.js-rupiah').forEach(function (el) {
    el.addEventListener('input', function () {
      var pos = el.selectionStart;
      var before = el.value.length;
      el.value = formatRupiah(el.value);
      var after = el.value.length;
      try { el.setSelectionRange(pos + (after - before), pos + (after - before)); } catch (e) {}
    });
  });
})();
</script>

<?php include '_footer.php'; ?>
