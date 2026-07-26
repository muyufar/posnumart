<?php
/**
 * Arsip BAQNU di server utama (soft-disable).
 * Data TIDAK dihapus — aman untuk stok/transfer cabang lain.
 */
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin !== 'super admin') {
    echo "<script>document.location.href = 'bo';</script>";
    exit;
}

$libPath = __DIR__ . '/aksi/cabang-arsip-lib.php';
if (!is_file($libPath)) {
    echo '<div class="content-wrapper"><section class="content"><div class="container-fluid">';
    echo '<div class="alert alert-danger mt-3">';
    echo '<h4>File belum lengkap di server</h4>';
    echo '<p>Upload file berikut ke Hostinger (folder yang sama dengan POS utama):</p>';
    echo '<ul>';
    echo '<li><code>aksi/cabang-arsip-lib.php</code></li>';
    echo '<li><code>arsip-baqnu.php</code></li>';
    echo '</ul>';
    echo '<p class="mb-0">Tanpa file library, halaman ini tidak bisa jalan (HTTP 500).</p>';
    echo '</div></div></section></div>';
    include '_footer.php';
    exit;
}

require_once $libPath;

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo '<div class="content-wrapper"><section class="content"><div class="container-fluid">';
    echo '<div class="alert alert-danger mt-3">Koneksi database gagal.</div></div></section></div>';
    include '_footer.php';
    exit;
}

$cabang = cabang_arsip_baqnu_id();
$pesan = '';
$tipePesan = 'info';
$log = [];

try {
    if (isset($_POST['arsip_baqnu'])) {
        $confirm = trim((string) ($_POST['confirm_text'] ?? ''));
        if ($confirm !== 'ARSIP-BAQNU') {
            $pesan = 'Frasa konfirmasi salah. Ketik persis: ARSIP-BAQNU';
            $tipePesan = 'warning';
        } else {
            $nonaktifBarang = isset($_POST['nonaktifkan_barang']);
            $hasil = baqnu_arsip_jalankan($conn, $cabang, $nonaktifBarang);
            $pesan = (string) $hasil['message'];
            $tipePesan = !empty($hasil['ok']) ? 'success' : 'danger';
            $log = $hasil['log'] ?? [];
        }
    }

    if (isset($_POST['batalkan_arsip_baqnu'])) {
        $confirm = trim((string) ($_POST['confirm_text'] ?? ''));
        if ($confirm !== 'AKTIFKAN-BAQNU') {
            $pesan = 'Frasa konfirmasi salah. Ketik persis: AKTIFKAN-BAQNU';
            $tipePesan = 'warning';
        } else {
            $aktifBarang = isset($_POST['aktifkan_barang']);
            $hasil = baqnu_arsip_batalkan($conn, $cabang, $aktifBarang);
            $pesan = (string) $hasil['message'];
            $tipePesan = !empty($hasil['ok']) ? 'success' : 'danger';
            $log = $hasil['log'] ?? [];
        }
    }

    if (isset($_POST['sesuaikan_piutang_baqnu'])) {
        $confirm = trim((string) ($_POST['confirm_text'] ?? ''));
        if ($confirm !== 'KURANGI-PIUTANG-BAQNU') {
            $pesan = 'Frasa konfirmasi salah. Ketik persis: KURANGI-PIUTANG-BAQNU';
            $tipePesan = 'warning';
        } else {
            $userName = (string) ($_SESSION['user_nama'] ?? $_SESSION['user_email'] ?? 'SUPER ADMIN');
            $hasil = baqnu_piutang_penyesuaian_jalankan($conn, $cabang, $userName);
            $pesan = (string) $hasil['message'];
            $tipePesan = !empty($hasil['ok']) ? 'success' : 'danger';
            $log = $hasil['log'] ?? [];
        }
    }

    if (isset($_POST['batalkan_sesuaikan_piutang_baqnu'])) {
        $confirm = trim((string) ($_POST['confirm_text'] ?? ''));
        if ($confirm !== 'BATAL-PIUTANG-BAQNU') {
            $pesan = 'Frasa konfirmasi salah. Ketik persis: BATAL-PIUTANG-BAQNU';
            $tipePesan = 'warning';
        } else {
            $hasil = baqnu_piutang_penyesuaian_batalkan($conn, $cabang);
            $pesan = (string) $hasil['message'];
            $tipePesan = !empty($hasil['ok']) ? 'success' : 'danger';
            $log = $hasil['log'] ?? [];
        }
    }

    $status = baqnu_arsip_status($conn, $cabang);
    $piutangPreview = baqnu_piutang_penyesuaian_preview($conn, $cabang);
} catch (Throwable $e) {
    echo '<div class="content-wrapper"><section class="content"><div class="container-fluid">';
    echo '<div class="alert alert-danger mt-3"><strong>Error:</strong> ';
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '<br><small>' . htmlspecialchars($e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8') . '</small>';
    echo '</div></div></section></div>';
    include '_footer.php';
    exit;
}
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1>Arsip BAQNU (Server Utama)</h1>
          <p class="text-muted mb-0">
            Nonaktifkan BAQNU di POS utama agar omset/hitungan Nugrosir &amp; cabang lain tidak tercampur.
            Data <strong>tidak dihapus</strong>.
          </p>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="export-baqnu">Export BAQNU</a></li>
            <li class="breadcrumb-item active">Arsip BAQNU</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">

      <?php if ($pesan !== '') : ?>
        <div class="alert alert-<?= htmlspecialchars($tipePesan, ENT_QUOTES, 'UTF-8'); ?>">
          <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8'); ?>
          <?php if ($log !== []) : ?>
            <ul class="mb-0 mt-2">
              <?php foreach ($log as $line) : ?>
                <li><small><?= htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8'); ?></small></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="callout callout-warning">
        <h5><i class="fas fa-exclamation-triangle"></i> Penting — jangan hard-delete</h5>
        <ul class="mb-0 pl-3">
          <li>Menghapus baris transfer BAQNU bisa <strong>merusak stok</strong> Nugrosir (ada trigger DB).</li>
          <li>Biaya patungan yang tercatat di cabang 0 (Nugrosir) biarkan — itu buku Nugrosir, bukan data cabang 4.</li>
          <li>Urutan aman: pastikan <a href="export-baqnu">Export BAQNU</a> &amp; instance <code>baqnu.numartmagelang.com</code> sudah OK → baru arsip di sini.</li>
        </ul>
      </div>

      <div class="row">
        <div class="col-md-3">
          <div class="info-box">
            <span class="info-box-icon bg-<?= !empty($status['sudah_arsip']) ? 'secondary' : 'success'; ?>">
              <i class="fa fa-store"></i>
            </span>
            <div class="info-box-content">
              <span class="info-box-text">Status Toko</span>
              <span class="info-box-number">
                <?= !empty($status['sudah_arsip']) ? 'DIARSIPKAN' : 'AKTIF'; ?>
              </span>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fa fa-user"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">User aktif / total</span>
              <span class="info-box-number"><?= (int) $status['user_aktif']; ?> / <?= (int) $status['user_total']; ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="info-box">
            <span class="info-box-icon bg-warning"><i class="fa fa-cube"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Barang aktif / total</span>
              <span class="info-box-number"><?= (int) $status['barang_aktif']; ?> / <?= (int) $status['barang_total']; ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fa fa-file"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Invoice (tetap di DB)</span>
              <span class="info-box-number"><?= number_format((int) $status['invoice']); ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Toko BAQNU</h3>
        </div>
        <div class="card-body">
          <?php if ($status['toko']) : ?>
            <p class="mb-1">
              <strong><?= htmlspecialchars((string) $status['toko']['toko_nama'], ENT_QUOTES, 'UTF-8'); ?></strong>
              — <?= htmlspecialchars((string) $status['toko']['toko_kota'], ENT_QUOTES, 'UTF-8'); ?>
              (cabang <?= (int) $cabang; ?>)
            </p>
            <p class="text-muted mb-0">
              Setelah diarsip: user BAQNU tidak bisa login di POS utama; pantau/total cabang aktif
              tidak menghitung omset BAQNU; history transfer tetap utuh.
            </p>
          <?php else : ?>
            <div class="alert alert-danger mb-0">Baris toko cabang <?= (int) $cabang; ?> tidak ditemukan.</div>
          <?php endif; ?>
        </div>
      </div>

      <?php if (empty($status['sudah_arsip'])) : ?>
        <div class="card card-danger">
          <div class="card-header">
            <h3 class="card-title">Arsipkan BAQNU sekarang</h3>
          </div>
          <form method="post" action="arsip-baqnu" class="card-body">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="nonaktifkan_barang" id="nonaktifkan_barang" checked>
              <label class="form-check-label" for="nonaktifkan_barang">
                Nonaktifkan juga semua barang cabang BAQNU
              </label>
            </div>
            <div class="form-group">
              <label>Ketik frasa konfirmasi: <code>ARSIP-BAQNU</code></label>
              <input type="text" name="confirm_text" class="form-control" placeholder="ARSIP-BAQNU" required autocomplete="off">
            </div>
            <button type="submit" name="arsip_baqnu" value="1" class="btn btn-danger btn-lg">
              <i class="fa fa-archive"></i> Arsipkan BAQNU di server utama
            </button>
          </form>
        </div>
      <?php else : ?>
        <div class="card card-secondary">
          <div class="card-header">
            <h3 class="card-title">BAQNU sudah diarsipkan</h3>
          </div>
          <form method="post" action="arsip-baqnu" class="card-body">
            <p>Jika perlu rollback (misalnya export/instance baru belum siap):</p>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="aktifkan_barang" id="aktifkan_barang" checked>
              <label class="form-check-label" for="aktifkan_barang">
                Aktifkan kembali barang BAQNU
              </label>
            </div>
            <div class="form-group">
              <label>Ketik frasa konfirmasi: <code>AKTIFKAN-BAQNU</code></label>
              <input type="text" name="confirm_text" class="form-control" placeholder="AKTIFKAN-BAQNU" required autocomplete="off">
            </div>
            <button type="submit" name="batalkan_arsip_baqnu" value="1" class="btn btn-warning">
              <i class="fa fa-undo"></i> Batalkan arsip (aktifkan lagi)
            </button>
          </form>
        </div>
      <?php endif; ?>

      <div class="card card-primary">
        <div class="card-header">
          <h3 class="card-title">Penyesuaian Piutang 1-1301 (kurangi porsi BAQNU)</h3>
        </div>
        <div class="card-body">
          <p class="mb-3">
            Akun <code>1-1301 Piutang Dagang</code> di PCNU menampung piutang semua cabang.
            Tombol ini mengurangi saldo tersebut sebesar sisa piutang invoice
            <strong>cabang <?= (int) $cabang; ?> (BAQNU)</strong> yang belum lunas di DB utama
            (karena sudah dipindah ke instance baru).
          </p>

          <div class="table-responsive mb-3">
            <table class="table table-bordered table-sm mb-0">
              <tbody>
                <tr>
                  <th style="width:40%">Saldo 1-1301 sekarang (PCNU)</th>
                  <td>Rp <?= number_format((float) $piutangPreview['saldo_sekarang'], 0, ',', '.'); ?></td>
                </tr>
                <tr>
                  <th>Sisa piutang BAQNU (belum lunas)</th>
                  <td>
                    Rp <?= number_format((float) $piutangPreview['nominal_baqnu'], 0, ',', '.'); ?>
                    <small class="text-muted">
                      (<?= (int) $piutangPreview['jumlah_invoice']; ?> invoice)
                    </small>
                  </td>
                </tr>
                <tr>
                  <th>Saldo 1-1301 setelah penyesuaian</th>
                  <td>
                    <strong>Rp <?= number_format((float) $piutangPreview['saldo_setelah'], 0, ',', '.'); ?></strong>
                  </td>
                </tr>
                <tr>
                  <th>Status</th>
                  <td>
                    <?php if (!empty($piutangPreview['sudah_disesuaikan'])) : ?>
                      <span class="badge badge-success">Sudah disesuaikan</span>
                      <?php if (!empty($piutangPreview['log']['keterangan'])) : ?>
                        <br><small class="text-muted"><?= htmlspecialchars((string) $piutangPreview['log']['keterangan'], ENT_QUOTES, 'UTF-8'); ?></small>
                      <?php endif; ?>
                    <?php else : ?>
                      <span class="badge badge-warning">Belum disesuaikan</span>
                    <?php endif; ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <?php if (empty($piutangPreview['sudah_disesuaikan'])) : ?>
            <?php if ((float) $piutangPreview['nominal_baqnu'] > 0) : ?>
              <form method="post" action="arsip-baqnu">
                <div class="form-group">
                  <label>Ketik frasa konfirmasi: <code>KURANGI-PIUTANG-BAQNU</code></label>
                  <input type="text" name="confirm_text" class="form-control" placeholder="KURANGI-PIUTANG-BAQNU" required autocomplete="off">
                </div>
                <button type="submit" name="sesuaikan_piutang_baqnu" value="1" class="btn btn-primary btn-lg"
                  onclick="return confirm('Kurangi saldo 1-1301 sebesar Rp <?= number_format((float) $piutangPreview['nominal_baqnu'], 0, ',', '.'); ?> ?');">
                  <i class="fa fa-balance-scale"></i>
                  Kurangi 1-1301 porsi BAQNU
                </button>
              </form>
            <?php else : ?>
              <div class="alert alert-info mb-0">
                Tidak ada sisa piutang BAQNU yang belum lunas — penyesuaian tidak diperlukan.
              </div>
            <?php endif; ?>
          <?php else : ?>
            <form method="post" action="arsip-baqnu">
              <div class="form-group">
                <label>Rollback — ketik: <code>BATAL-PIUTANG-BAQNU</code></label>
                <input type="text" name="confirm_text" class="form-control" placeholder="BATAL-PIUTANG-BAQNU" required autocomplete="off">
              </div>
              <button type="submit" name="batalkan_sesuaikan_piutang_baqnu" value="1" class="btn btn-outline-warning">
                <i class="fa fa-undo"></i> Batalkan penyesuaian piutang
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </section>
</div>

<?php include '_footer.php'; ?>
