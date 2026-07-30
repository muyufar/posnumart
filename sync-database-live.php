<?php
/**
 * Halaman recovery sync DB — bootstrap minimal agar tetap jalan walau tabel user hilang.
 */
require_once __DIR__ . '/aksi/koneksi.php';
require_once __DIR__ . '/aksi/sync-db-local-lib.php';

$cfg = sync_db_load_config();
$isLocal = sync_db_is_local_environment($cfg);
$configReady = is_file(sync_db_config_path());

$dbKosong = false;
if (isset($conn) && $conn instanceof mysqli) {
    $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'user'");
    $dbKosong = !($chk && mysqli_num_rows($chk) > 0);
}

/** Recovery: DB kosong + localhost → boleh sync tanpa login (dev only). */
$recoveryMode = $isLocal && $dbKosong;

if (!$recoveryMode) {
    require_once __DIR__ . '/aksi/halau.php';
}

$levelLogin = (string) ($_SESSION['user_level'] ?? '');

if (!$recoveryMode && $levelLogin !== 'super admin') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>Hanya Super Admin. <a href="bo">Kembali</a></p>';
    exit;
}

$confirmPhrase = trim((string) ($cfg['confirm_phrase'] ?? 'SYNC-LIVE'));

$pesan = '';
$tipePesan = 'info';
$logLines = [];

if (!$isLocal) {    $pesan = 'Fitur ini hanya tersedia di localhost / Laragon. Jangan deploy atau buka di server live.';
    $tipePesan = 'danger';
}

if ($isLocal && isset($_POST['sync_live_db']) && $configReady) {
    $typed = trim((string) ($_POST['confirm_text'] ?? ''));
    if ($typed !== $confirmPhrase) {
        $pesan = 'Frasa konfirmasi salah. Ketik persis: ' . $confirmPhrase;
        $tipePesan = 'warning';
    } else {
        $result = sync_db_run_sync($cfg, $logLines);
        $pesan = (string) ($result['message'] ?? 'Selesai.');
        $tipePesan = !empty($result['ok']) ? 'success' : 'danger';
    }
}

global $conn;
$localDbName = $configReady ? sync_db_local_database_name($cfg, $conn) : '';
$remoteTest = ($isLocal && $configReady) ? sync_db_test_connection($cfg) : null;
$syncMode = $configReady ? sync_db_get_mode($cfg) : 'mysql';
$resolvedHost = $configReady ? sync_db_resolve_remote_host($cfg) : '';
$mysqldumpDir = sync_db_mysql_bin_dir();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sync Database Live — NUMART</title>
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
<div class="content-wrapper" style="margin-left:0;">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-8">
                    <h1>Sinkron Database dari Live</h1>
                    <p class="text-muted mb-0">Development only — unduh DB production ke MySQL lokal dengan satu klik.</p>
                </div>
                <div class="col-sm-4">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="bo">Home</a></li>
                        <li class="breadcrumb-item"><a href="backup">Backup &amp; Restore</a></li>
                        <li class="breadcrumb-item active">Sync DB Live</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <?php if ($recoveryMode) : ?>
                <div class="alert alert-warning">
                    <strong>Mode recovery</strong> — database lokal kosong, login tidak bisa dipakai.
                    Jalankan sinkron di bawah untuk memulihkan data dari live.
                </div>
            <?php elseif ($dbKosong) : ?>
                <div class="alert alert-danger">
                    <strong>Database lokal kosong</strong> (tabel <code>user</code> tidak ada) — kemungkinan sync sebelumnya gagal saat import.
                    Klik <strong>Unduh &amp; sinkronkan</strong> di bawah untuk memulihkan dari live.
                </div>
            <?php endif; ?>

            <?php if ($pesan !== '') : ?>
                <div class="alert alert-<?= htmlspecialchars($tipePesan, ENT_QUOTES, 'UTF-8'); ?>">
                    <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="callout callout-warning">
                <p class="mb-1"><strong>Peringatan:</strong> Semua tabel di database lokal <code><?= htmlspecialchars($localDbName ?: '(koneksi.php)', ENT_QUOTES, 'UTF-8'); ?></code> akan <strong>dihapus dan diganti</strong> data dari live.</p>
                <p class="mb-0">File upload/gambar <em>tidak</em> ikut tersinkron — hanya struktur &amp; data MySQL.</p>
            </div>

            <div class="row">
                <div class="col-lg-5">
                    <div class="card card-outline card-primary">
                        <div class="card-header"><h3 class="card-title">Status</h3></div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <td>Environment</td>
                                        <td><?= $isLocal ? '<span class="badge badge-success">Lokal OK</span>' : '<span class="badge badge-danger">Bukan lokal</span>'; ?></td>
                                    </tr>
                                    <tr>
                                        <td>Config remote</td>
                                        <td><?= $configReady ? '<span class="badge badge-success">Ada</span>' : '<span class="badge badge-danger">Belum</span>'; ?></td>
                                    </tr>
                                    <tr>
                                        <td>Database lokal</td>
                                        <td><code><?= htmlspecialchars($localDbName ?: '-', ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    </tr>
                                    <tr>
                                        <td>Mode sync</td>
                                        <td><span class="badge badge-<?= $syncMode === 'http' ? 'info' : 'secondary'; ?>"><?= htmlspecialchars(strtoupper($syncMode), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    </tr>
                                    <?php if ($syncMode === 'http') : ?>
                                    <tr>
                                        <td>Export URL</td>
                                        <td><code style="font-size:11px;"><?= htmlspecialchars((string) ($cfg['http_export_url'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($syncMode !== 'http') : ?>
                                    <tr>
                                        <td>Live host</td>
                                        <td><code><?= htmlspecialchars((string) ($cfg['remote_host'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></code>
                                            <?php if ($resolvedHost !== '' && $resolvedHost !== ($cfg['remote_host'] ?? '')) : ?>
                                                <br><small class="text-muted">Konek via IPv4: <code><?= htmlspecialchars($resolvedHost, ENT_QUOTES, 'UTF-8'); ?></code></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Live user</td>
                                        <td><code><?= htmlspecialchars((string) ($cfg['remote_user'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    </tr>
                                    <tr>
                                        <td>Live database</td>
                                        <td><code><?= htmlspecialchars((string) ($cfg['remote_database'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    </tr>
                                    <tr>
                                        <td>IP outbound PC</td>
                                        <td><code><?= htmlspecialchars(sync_db_public_ip_hint() ?: '-', ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td>Koneksi live</td>
                                        <td>
                                            <?php if ($remoteTest === null) : ?>
                                                -
                                            <?php elseif ($remoteTest['ok']) : ?>
                                                <span class="badge badge-success">Terhubung</span>
                                            <?php else : ?>
                                                <span class="badge badge-danger">Gagal</span>
                                                <small class="d-block text-muted"><?= htmlspecialchars($remoteTest['message'], ENT_QUOTES, 'UTF-8'); ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>mysqldump Laragon</td>
                                        <td><?= $mysqldumpDir ? '<span class="badge badge-info">Siap</span> <small>' . htmlspecialchars(basename(dirname($mysqldumpDir)), ENT_QUOTES, 'UTF-8') . '</small>' : '<span class="badge badge-secondary">Tidak ada (pakai PHP)</span>'; ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if ($configReady && $syncMode === 'http') : ?>
                    <div class="card card-outline card-success">
                        <div class="card-header"><h3 class="card-title">Setup mode HTTP (sekali di server live)</h3></div>
                        <div class="card-body">
                            <ol class="mb-0 pl-3">
                                <li>Upload ke server live: <code>api/sync-db-export-live.php</code></li>
                                <li>Salin <code>api/sync-db-export.config.example.php</code> → <code>api/sync-db-export.config.php</code> di live</li>
                                <li>Set <code>secret</code> yang <strong>sama</strong> dengan <code>http_export_secret</code> di config lokal</li>
                                <li>Tes di browser: <code><?= htmlspecialchars((string) ($cfg['http_export_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>?ping=1&amp;key=SECRET</code> → harus JSON <code>{"ok":true}</code></li>
                                <li>Refresh halaman ini → Koneksi live = Terhubung → klik sinkron</li>
                            </ol>
                        </div>
                    </div>
                    <?php elseif (!$configReady) : ?>
                    <div class="card card-outline card-info">
                        <div class="card-header"><h3 class="card-title">Setup (sekali)</h3></div>
                        <div class="card-body">
                            <ol class="mb-0 pl-3">
                                <li>Salin <code>aksi/sync-db-remote.config.example.php</code> → <code>aksi/sync-db-remote.config.php</code></li>
                                <li>Isi host/user/password/database MySQL live (dari hPanel Hostinger)</li>
                                <li>hPanel → <strong>Databases → Remote MySQL</strong> → whitelist IP publik PC (IPv4 &amp; IPv6 jika perlu)</li>
                                <li>Tambahkan <code>'remote_prefer_ipv4' => true</code> di config jika Access denied dari IPv6</li>
                                <li>Refresh halaman ini, lalu klik sinkron</li>
                            </ol>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-7">
                    <div class="card card-danger">
                        <div class="card-header"><h3 class="card-title">Sinkronkan sekarang</h3></div>
                        <form method="post" onsubmit="return confirm('Database lokal akan ditimpa total. Lanjutkan?');">
                            <div class="card-body">
                                <p>Ketik <strong><code><?= htmlspecialchars($confirmPhrase, ENT_QUOTES, 'UTF-8'); ?></code></strong> untuk konfirmasi:</p>
                                <input type="text" name="confirm_text" class="form-control mb-3" placeholder="<?= htmlspecialchars($confirmPhrase, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" <?= (!$isLocal || !$configReady) ? 'disabled' : ''; ?> required>
                                <p class="text-muted small mb-0">Proses bisa beberapa menit tergantung ukuran database. Jangan tutup browser.</p>
                            </div>
                            <div class="card-footer">
                                <button type="submit" name="sync_live_db" value="1" class="btn btn-danger" <?= (!$isLocal || !$configReady) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-download"></i> Unduh &amp; sinkronkan dari live
                                </button>
                                <a href="backup" class="btn btn-secondary">Kembali</a>
                            </div>
                        </form>
                    </div>

                    <?php if ($logLines !== []) : ?>
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Log proses</h3></div>
                        <div class="card-body">
                            <pre class="mb-0 small" style="max-height:400px;overflow:auto;background:#111;color:#0f0;padding:12px;border-radius:4px;"><?php
                                foreach ($logLines as $line) {
                                    echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "\n";
                                }
                            ?></pre>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </section>
</div>
</div>
</body>
</html>
