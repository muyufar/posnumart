<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
?>

<?php
if ($levelLogin !== "super admin") {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
}

// Auto-create settings table if missing
$createTableSql = "CREATE TABLE IF NOT EXISTS shopee_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cabang INT NOT NULL DEFAULT 0,
    partner_id BIGINT NULL,
    partner_key VARCHAR(255) NULL,
    redirect_url VARCHAR(255) NULL,
    host VARCHAR(100) NULL,
    shop_id BIGINT NULL,
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    updated_at DATETIME NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $createTableSql);

// Determine active cabang scope
$activeCabang = isset($sessionCabang) ? (int)$sessionCabang : 0;

// Load existing settings
$settings = [
    'partner_id' => '',
    'partner_key' => '',
    'redirect_url' => '',
    'host' => 'https://partner.test-stable.shopeemobile.com',
];

$res = mysqli_query($conn, "SELECT * FROM shopee_tokens WHERE cabang = " . $activeCabang . " LIMIT 1");
if ($res && mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    $settings['partner_id'] = $row['partner_id'];
    $settings['partner_key'] = $row['partner_key'];
    $settings['redirect_url'] = $row['redirect_url'];
    $settings['host'] = $row['host'];
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $partner_id   = isset($_POST['partner_id']) ? trim($_POST['partner_id']) : '';
    $partner_key  = isset($_POST['partner_key']) ? trim($_POST['partner_key']) : '';
    $redirect_url = isset($_POST['redirect_url']) ? trim($_POST['redirect_url']) : '';
    $host         = isset($_POST['host']) ? trim($_POST['host']) : '';

    $partner_id_sql   = $partner_id !== '' ? (int)$partner_id : 'NULL';
    $partner_key_sql  = $partner_key !== '' ? "'" . mysqli_real_escape_string($conn, $partner_key) . "'" : 'NULL';
    $redirect_url_sql = $redirect_url !== '' ? "'" . mysqli_real_escape_string($conn, $redirect_url) . "'" : 'NULL';
    $host_sql         = $host !== '' ? "'" . mysqli_real_escape_string($conn, $host) . "'" : 'NULL';

    // Upsert by cabang
    $exists = mysqli_query($conn, "SELECT id FROM shopee_settings WHERE cabang = " . $activeCabang . " LIMIT 1");
    if ($exists && mysqli_num_rows($exists) > 0) {
        mysqli_query($conn, "UPDATE shopee_tokens  SET partner_id = " . $partner_id_sql . ", partner_key = " . $partner_key_sql . ", redirect_url = " . $redirect_url_sql . ", host = " . $host_sql . ", updated_at = NOW() WHERE cabang = " . $activeCabang);
    } else {
        mysqli_query($conn, "INSERT INTO shopee_tokens  (cabang, partner_id, partner_key, redirect_url, host, updated_at) VALUES (" . $activeCabang . ", " . $partner_id_sql . ", " . $partner_key_sql . ", " . $redirect_url_sql . ", " . $host_sql . ", NOW())");
    }

    echo "<script>alert('Pengaturan Shopee disimpan.');document.location.href='shopee-settings';</script>";
    exit;
}

// Helper sign for OAuth install
function shopee_sign_install($partnerId, $partnerKey, $path, $timestamp)
{
    $baseString = $partnerId . $path . $timestamp;
    return hash_hmac('sha256', $baseString, $partnerKey);
}

// Precompute OAuth URL if possible
// Initialize OAuth URL for Shopee API authentication
$oauth_url = '';

// Check if all required settings are present
if (!empty($settings['partner_id']) && !empty($settings['partner_key']) && !empty($settings['redirect_url']) && !empty($settings['host'])) {
    // API endpoint for shop authentication
    $path = '/api/v2/shop/auth_partner';
    $timestamp = time();

    // Generate authentication signature
    $sign = shopee_sign_install(
        (string)$settings['partner_id'],
        (string)$settings['partner_key'],
        $path,
        $timestamp
    );

    // Prepare query parameters for OAuth
    $query = http_build_query([
        'partner_id' => (string)$settings['partner_id'],
        'timestamp'  => $timestamp,
        'sign'       => $sign,
        'redirect'   => $settings['redirect_url']
    ]);

    // Construct full OAuth URL ensuring no double slashes
    $oauth_url = rtrim($settings['host'], '/') . $path . '?' . $query;

    // Log OAuth attempt for debugging
    error_log("Shopee OAuth URL generated: " . substr($oauth_url, 0, 100) . "...");
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Pengaturan Shopee</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="bo">Home</a></li>
                        <li class="breadcrumb-item active">Integrasi Shopee</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Kredensial API</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="form-group">
                                <label>Partner ID</label>
                                <input type="text" name="partner_id" class="form-control" value="<?= htmlspecialchars($settings['partner_id']); ?>" placeholder="1183639">
                            </div>
                            <div class="form-group">
                                <label>Partner Key</label>
                                <input type="text" name="partner_key" class="form-control" value="<?= htmlspecialchars($settings['partner_key']); ?>" placeholder="shpk...">
                            </div>
                            <div class="form-group">
                                <label>Redirect URL</label>
                                <input type="text" name="redirect_url" class="form-control" value="<?= htmlspecialchars($settings['redirect_url']); ?>" placeholder="https://yourdomain.com/shopee-callback">
                                <small class="form-text text-muted">Harus mengarah ke file shopee-callback.php di server Anda</small>
                            </div>
                            <div class="form-group">
                                <label>Host</label>
                                <select name="host" class="form-control">
                                    <option value="https://partner.test-stable.shopeemobile.com" <?= $settings['host'] === 'https://partner.test-stable.shopeemobile.com' ? 'selected' : '' ?>>Sandbox (test-stable)</option>
                                    <option value="https://partner.shopeemobile.com" <?= $settings['host'] === 'https://partner.shopeemobile.com' ? 'selected' : '' ?>>Production</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <?php if ($oauth_url !== '') { ?>
                                <a href="<?= htmlspecialchars($oauth_url); ?>" target="_blank" class="btn btn-success" style="margin-left: 10px;">Refresh Token Shopee</a>
                            <?php } else { ?>
                                <button type="button" class="btn btn-default" title="Lengkapi data di atas" disabled>Hubungkan Toko Shopee</button>
                            <?php } ?>
                            <a href="shopee" target="_blank" class="btn btn-warning" style="margin-left: 10px;">Dashboard Shopee</a>

                        </form>

                        <?php if (!empty($settings['shop_id']) || !empty($settings['access_token'])) { ?>
                            <hr>
                            <div class="mt-3">
                                <h5>Status Koneksi Shopee</h5>
                                <?php if (!empty($settings['shop_id'])) { ?>
                                    <p><strong>Shop ID:</strong> <?= htmlspecialchars($settings['shop_id']); ?></p>
                                <?php } ?>
                                <?php if (!empty($settings['access_token'])) { ?>
                                    <p><strong>Status:</strong> <span class="text-success">Terhubung</span></p>
                                    <p><strong>Terakhir Update:</strong> <?= !empty($settings['updated_at']) ? htmlspecialchars($settings['updated_at']) : 'Tidak diketahui'; ?></p>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <div class="mt-3">
                            <small class="text-muted">
                                <strong>Alert:</strong> Jika pada dashboard shopee tidak menampilkan data produk maka klik Refresh Token Shopee pada tombol berwarna hijau.
                            </small>
                        </div>

                        <div class="mt-3">
                            <div class="alert alert-info">
                                <h6><i class="fa fa-info-circle"></i> Cara Debug Error "Wrong Sign"</h6>
                                <ol>
                                    <li><strong>Cek Error Log PHP:</strong> Buka file error log PHP (biasanya di folder logs server)</li>
                                    <li><strong>Cari Log "Shopee":</strong> Cari log yang dimulai dengan "Shopee" untuk melihat detail signature</li>
                                    <li><strong>Verifikasi Data:</strong> Pastikan Partner ID, Partner Key, dan Redirect URL sudah benar</li>
                                    <li><strong>Timestamp:</strong> Pastikan server timezone sudah benar (Asia/Jakarta)</li>
                                    <li><strong>Contact Support:</strong> Jika masih error, share log error dengan support Shopee</li>
                                </ol>
                                <p class="mb-0"><strong>Lokasi Error Log:</strong>
                                    <code>C:\xampp\php\logs\php_error_log</code> (untuk XAMPP) atau
                                    <code>/var/log/php_errors.log</code> (untuk Linux)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '_footer.php'; ?>
</body>

</html>