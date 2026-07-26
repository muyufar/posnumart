<?php
/**
 * Export DB khusus BAQNU — halaman UI + unduh SQL.
 * Download diproses sebelum HTML agar stream tidak tercemar.
 */
$exportLibPath = __DIR__ . '/aksi/export-baqnu-lib.php';
$isDownload = isset($_POST['download_baqnu_sql']);

if ($isDownload) {
    require_once __DIR__ . '/aksi/halau.php';
    require_once __DIR__ . '/aksi/koneksi.php';
    $levelLogin = (string) ($_SESSION['user_level'] ?? '');
    if ($levelLogin !== 'super admin') {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    if (!is_file($exportLibPath)) {
        http_response_code(503);
        echo 'File aksi/export-baqnu-lib.php belum di-upload ke server.';
        exit;
    }
    require_once $exportLibPath;
    if (!isset($conn) || !($conn instanceof mysqli)) {
        http_response_code(500);
        echo 'Koneksi database gagal';
        exit;
    }
    mysqli_query($conn, 'SET NAMES utf8mb4');

    $defaultCabang = export_baqnu_default_cabang();
    $cabang = isset($_REQUEST['cabang']) ? (int) $_REQUEST['cabang'] : $defaultCabang;
    if ($cabang < 0) {
        $cabang = $defaultCabang;
    }
    $remapToPusat = !isset($_REQUEST['remap_to_pusat']) || (string) $_REQUEST['remap_to_pusat'] === '1';
    $includeSharedMaster = !isset($_REQUEST['include_shared_master']) || (string) $_REQUEST['include_shared_master'] === '1';
    $confirm = trim((string) ($_POST['confirm_text'] ?? ''));
    if ($confirm !== 'EXPORT-BAQNU') {
        // jatuh ke halaman UI dengan error
        $downloadError = 'Frasa konfirmasi salah. Ketik persis: EXPORT-BAQNU';
        $isDownload = false;
    } else {
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        $filename = 'baqnu-cabang' . $cabang . ($remapToPusat ? '-remap0' : '') . '-' . date('Ymd-His') . '.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        header('X-Accel-Buffering: no');
        export_baqnu_stream_sql($conn, $cabang, $remapToPusat, $includeSharedMaster);
        exit;
    }
}

include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin !== 'super admin') {
    echo "<script>document.location.href = 'bo';</script>";
    exit;
}

if (!is_file($exportLibPath)) {
    echo '<div class="content-wrapper"><section class="content"><div class="container-fluid">';
    echo '<div class="alert alert-danger mt-3">';
    echo '<h4>File belum lengkap di server</h4>';
    echo '<p>Upload: <code>aksi/export-baqnu-lib.php</code> dan <code>export-baqnu.php</code></p>';
    echo '</div></div></section></div>';
    include '_footer.php';
    exit;
}

require_once $exportLibPath;
mysqli_query($conn, 'SET NAMES utf8mb4');

$defaultCabang = export_baqnu_default_cabang();
$cabang = isset($_REQUEST['cabang']) ? (int) $_REQUEST['cabang'] : $defaultCabang;
if ($cabang < 0) {
    $cabang = $defaultCabang;
}
$remapToPusat = !isset($_REQUEST['remap_to_pusat']) || (string) $_REQUEST['remap_to_pusat'] === '1';
$includeSharedMaster = !isset($_REQUEST['include_shared_master']) || (string) $_REQUEST['include_shared_master'] === '1';
if (!isset($downloadError)) {
    $downloadError = '';
}

try {
    $preview = export_baqnu_preview($conn, $cabang, $remapToPusat, $includeSharedMaster);
} catch (Throwable $e) {
    echo '<div class="content-wrapper"><section class="content"><div class="container-fluid">';
    echo '<div class="alert alert-danger mt-3"><strong>Error:</strong> ';
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
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
          <h1>Export Database BAQNU</h1>
          <p class="text-muted mb-0">
            Ekspor data khusus cabang BAQNU menjadi file SQL siap import ke
            <code>baqnu.numartmagelang.com</code>.
          </p>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Export BAQNU</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">

      <?php if ($downloadError !== '') : ?>
        <div class="alert alert-warning"><?= htmlspecialchars($downloadError, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <div class="callout callout-info">
        <h5><i class="fas fa-info-circle"></i> Cara pakai (aman)</h5>
        <ol class="mb-0 pl-3">
          <li>Preview dulu — pastikan jumlah invoice/barang/user masuk akal.</li>
          <li>Unduh SQL, buat DB baru di Hostinger untuk Baqnu.</li>
          <li>Import SQL ke DB baru, deploy copy aplikasi ke domain Baqnu, isi <code>aksi/koneksi.php</code>.</li>
          <li>Opsi remap ke pusat (direkomendasikan): <code>cabang <?= (int) $cabang; ?> → 0</code> agar menu pusat jalan di instance baru.</li>
          <li>Jangan hapus data Baqnu di POS lama sampai Baqnu baru sudah dicek OK.</li>
        </ol>
      </div>

      <div class="card card-primary">
        <div class="card-header">
          <h3 class="card-title">Pengaturan Export</h3>
        </div>
        <form method="get" action="export-baqnu" class="card-body">
          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label>Kode Cabang</label>
                <input type="number" name="cabang" class="form-control" value="<?= (int) $cabang; ?>" min="0">
                <small class="text-muted">BAQNU = 4</small>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Remap ke pusat (0)</label>
                <select name="remap_to_pusat" class="form-control">
                  <option value="1" <?= $remapToPusat ? 'selected' : ''; ?>>Ya — cabang <?= (int) $cabang; ?> → 0 (disarankan)</option>
                  <option value="0" <?= !$remapToPusat ? 'selected' : ''; ?>>Tidak — biarkan cabang <?= (int) $cabang; ?></option>
                </select>
              </div>
            </div>
            <div class="col-md-5">
              <div class="form-group">
                <label>Master shared (satuan/kategori/ekspedisi cabang 0)</label>
                <select name="include_shared_master" class="form-control">
                  <option value="1" <?= $includeSharedMaster ? 'selected' : ''; ?>>Ya — ikutkan (disarankan)</option>
                  <option value="0" <?= !$includeSharedMaster ? 'selected' : ''; ?>>Tidak — hanya data cabang ini</option>
                </select>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-secondary">
            <i class="fa fa-refresh"></i> Preview ulang
          </button>
        </form>
      </div>

      <div class="row">
        <div class="col-md-3">
          <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fa fa-file"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Invoice</span>
              <span class="info-box-number"><?= number_format((int) $preview['context']['invoice']); ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fa fa-cube"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Barang</span>
              <span class="info-box-number"><?= number_format((int) $preview['context']['barang']); ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="info-box">
            <span class="info-box-icon bg-warning"><i class="fa fa-users"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Customer</span>
              <span class="info-box-number"><?= number_format((int) $preview['context']['customer']); ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="info-box">
            <span class="info-box-icon bg-danger"><i class="fa fa-user"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">User</span>
              <span class="info-box-number"><?= number_format((int) $preview['context']['user']); ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">
            Ringkasan tabel —
            <?= number_format((int) $preview['totals']['export_rows']); ?> baris akan diekspor
            dari <?= (int) $preview['totals']['table_count']; ?> tabel
          </h3>
        </div>
        <div class="card-body table-responsive p-0" style="max-height:420px;">
          <table class="table table-sm table-striped table-head-fixed">
            <thead>
              <tr>
                <th>Tabel</th>
                <th>Mode</th>
                <th>Baris</th>
                <th>Catatan</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($preview['tables'] as $t) : ?>
                <tr class="<?= $t['mode'] === 'structure_only' ? 'text-muted' : ''; ?>">
                  <td><code><?= htmlspecialchars((string) $t['table'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                  <td>
                    <?php if ($t['mode'] === 'structure_only') : ?>
                      <span class="badge badge-secondary">struktur</span>
                    <?php else : ?>
                      <span class="badge badge-primary">data</span>
                    <?php endif; ?>
                  </td>
                  <td><?= number_format((int) $t['rows']); ?></td>
                  <td><small><?= htmlspecialchars((string) $t['note'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card card-success">
        <div class="card-header">
          <h3 class="card-title">Unduh SQL BAQNU</h3>
        </div>
        <form method="post" action="export-baqnu" class="card-body">
          <input type="hidden" name="cabang" value="<?= (int) $cabang; ?>">
          <input type="hidden" name="remap_to_pusat" value="<?= $remapToPusat ? '1' : '0'; ?>">
          <input type="hidden" name="include_shared_master" value="<?= $includeSharedMaster ? '1' : '0'; ?>">

          <div class="form-group">
            <label>Ketik frasa konfirmasi: <code>EXPORT-BAQNU</code></label>
            <input type="text" name="confirm_text" class="form-control" placeholder="EXPORT-BAQNU" required autocomplete="off">
          </div>

          <p class="text-muted">
            File berisi struktur semua tabel + data terfilter Baqnu
            <?= $remapToPusat ? '(dengan remap cabang → 0)' : '(tanpa remap)'; ?>.
            Proses bisa beberapa menit jika data besar — jangan tutup tab.
          </p>

          <button type="submit" name="download_baqnu_sql" value="1" class="btn btn-success btn-lg">
            <i class="fa fa-download"></i> Unduh SQL BAQNU
          </button>
        </form>
      </div>

    </div>
  </section>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
