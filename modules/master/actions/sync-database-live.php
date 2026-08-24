<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
/**
 * Halaman recovery sync DB — bootstrap minimal agar tetap jalan walau tabel user hilang.
 */
require_once numart_path('aksi/koneksi.php');
require_once numart_path('aksi/sync-db-local-lib.php');

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
    require_once numart_path('aksi/halau.php');
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

if (!$isLocal) {
    $pesan = 'Fitur sync tidak diizinkan di host ini. Izinkan lewat allowed_hosts di aksi/sync-db-remote.config.php (mis. demopos), atau jalankan di Laragon.';
    $tipePesan = 'danger';
}

/**
 * Sinkron bisa berjalan belasan menit; log dialirkan langsung ke browser
 * supaya progres terlihat dan koneksi tidak dianggap mati.
 */
function sync_db_render_streaming_page(array $cfg): void
{
    @set_time_limit(0);
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    ob_implicit_flush(true);

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Accel-Buffering: no');

    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Sinkron berjalan — NUMART</title>'
        . '<link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">'
        . '<link rel="stylesheet" href="dist/css/adminlte.min.css"></head>'
        . '<body class="hold-transition"><div class="content-wrapper" style="margin-left:0;">'
        . '<section class="content pt-3"><div class="container-fluid">'
        . '<h4 class="mb-3"><i class="fas fa-sync fa-spin"></i> Sinkron database dari live berjalan…</h4>'
        . '<p class="text-muted">Jangan tutup halaman ini. Progres muncul di bawah.</p>'
        . '<script>setInterval(function(){var e=document.getElementById("synclog");'
        . 'if(e){e.scrollTop=e.scrollHeight;}},400);</script>'
        . '<pre id="synclog" style="max-height:60vh;overflow:auto;background:#111;color:#0f0;'
        . 'padding:12px;border-radius:4px;font-size:12px;">';

    /** Sebagian browser menunda render sampai beberapa KB pertama diterima. */
    echo str_repeat(' ', 4096) . "\n";
    flush();

    $logLines = [];
    $GLOBALS['sync_db_log_sink'] = static function (string $line): void {
        echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "\n";
        flush();
    };

    $finished = false;
    register_shutdown_function(static function () use (&$finished): void {
        if (!$finished) {
            echo '</pre><div class="alert alert-danger mt-3">Proses berhenti tak terduga '
                . '(cek error log PHP). Coba jalankan ulang.</div>'
                . '<a href="sync-database-live" class="btn btn-secondary">Kembali</a>'
                . '</div></section></div></body></html>';
        }
    });

    $result = sync_db_run_sync($cfg, $logLines);
    $finished = true;
    unset($GLOBALS['sync_db_log_sink']);

    $ok = !empty($result['ok']);
    echo '</pre>';
    echo '<div class="alert alert-' . ($ok ? 'success' : 'danger') . ' mt-3">'
        . htmlspecialchars((string) ($result['message'] ?? 'Selesai.'), ENT_QUOTES, 'UTF-8')
        . '</div>';
    echo '<a href="sync-database-live" class="btn btn-secondary">Kembali ke halaman sync</a> '
        . '<a href="bo" class="btn btn-primary">Ke dashboard</a>';
    echo '</div></section></div></body></html>';
}

if ($isLocal && isset($_POST['sync_live_db']) && $configReady) {
    $typed = trim((string) ($_POST['confirm_text'] ?? ''));
    if ($typed !== $confirmPhrase) {
        $pesan = 'Frasa konfirmasi salah. Ketik persis: ' . $confirmPhrase;
        $tipePesan = 'warning';
    } else {
        sync_db_render_streaming_page($cfg);
        exit;
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
                    <p class="text-muted mb-0">Tarik database production ke environment uji (Laragon / demopos). Production tidak boleh jadi target sync.</p>
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
                <p class="mb-1"><strong>Peringatan:</strong> Semua tabel di database target <code><?= htmlspecialchars($localDbName ?: '(koneksi.php)', ENT_QUOTES, 'UTF-8'); ?></code> akan <strong>dihapus dan diganti</strong> data dari live production.</p>
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
                                <li>Upload ke <strong>production</strong> (<code>pos.numartmagelang.com</code>): <code>api/sync-db-export-live.php</code> + <code>api/sync-db-export.config.php</code></li>
                                <li>Di target sync (Laragon / demopos): salin <code>aksi/sync-db-remote.config.example.php</code> → <code>aksi/sync-db-remote.config.php</code></li>
                                <li>Samakan <code>secret</code> (live) dengan <code>http_export_secret</code> (target)</li>
                                <li>Untuk demopos: isi <code>allowed_hosts</code> → <code>demopos.numartmagelang.com</code> dan pastikan <code>koneksi.php</code> pakai DB <code>u700125577_posnew</code></li>
                                <li>Tes: <code><?= htmlspecialchars((string) ($cfg['http_export_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>?ping=1&amp;key=SECRET</code> → JSON <code>{"ok":true}</code></li>
                                <li>Buka halaman ini di demopos → Koneksi live = Terhubung → klik sinkron</li>
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

                </div>
            </div>

        </div>
    </section>
</div>
</div>
</body>
</html>
