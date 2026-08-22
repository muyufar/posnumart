<?php
include 'aksi/koneksi.php';
include 'aksi/halau.php';
include 'aksi/functions.php';

// Ambil cabang aktif dari sesi (ikuti pola _header-artibut.php)
$userLoginCabang = mysqli_query($conn, "select user_cabang from user where user_id = '" . $_SESSION['user_id'] . "'");
$sessionCabangData = mysqli_fetch_array($userLoginCabang);
$sessionCabang     = isset($sessionCabangData['user_cabang']) ? (int)$sessionCabangData['user_cabang'] : 0;

// Ambil parameter dari Shopee
$code    = isset($_GET['code']) ? trim($_GET['code']) : '';
$shop_id = isset($_GET['shop_id']) ? (int)$_GET['shop_id'] : 0;

if ($code === '' || $shop_id < 1) {
    echo "<script>alert('Callback Shopee tidak valid: code/shop_id kosong.');document.location.href='shopee-settings';</script>";
    exit;
}

// Ambil konfigurasi shopee_settings
$settingsRes = mysqli_query($conn, "SELECT * FROM shopee_settings WHERE cabang = " . $sessionCabang . " ORDER BY id DESC LIMIT 1");
if (!$settingsRes || mysqli_num_rows($settingsRes) < 1) {
    echo "<script>alert('Pengaturan Shopee belum disimpan.');document.location.href='shopee-settings';</script>";
    exit;
}
$settings = mysqli_fetch_assoc($settingsRes);

$partner_id  = $settings['partner_id'];
$partner_key = $settings['partner_key'];
$host        = $settings['host'];

if (empty($partner_id) || empty($partner_key) || empty($host)) {
    echo "<script>alert('Pengaturan Shopee tidak lengkap.');document.location.href='shopee-settings';</script>";
    exit;
}

/**
 * Shopee Open Platform Signature Generation
 * According to official documentation: https://open.shopee.com/developer-guide/16
 * 
 * Base String Format: partner_id + path + timestamp + access_token + shop_id
 * For OAuth token exchange: partner_id + path + timestamp (no access_token, no shop_id yet)
 */
function shopee_generate_signature($partner_id, $path, $timestamp, $access_token = '', $shop_id = 0)
{
    global $partner_key;

    // Build base string according to Shopee documentation
    $base_string = $partner_id . $path . $timestamp;

    // Add access_token if available
    if (!empty($access_token)) {
        $base_string .= $access_token;
    }

    // Add shop_id if available
    if ($shop_id > 0) {
        $base_string .= $shop_id;
    }

    // Generate HMAC-SHA256 signature
    $signature = hash_hmac('sha256', $base_string, $partner_key);

    // Debug logging
    error_log("Shopee Signature Debug:");
    error_log("  Partner ID: " . $partner_id);
    error_log("  Path: " . $path);
    error_log("  Timestamp: " . $timestamp);
    error_log("  Access Token: " . ($access_token ? 'YES' : 'NO'));
    error_log("  Shop ID: " . ($shop_id > 0 ? $shop_id : 'NO'));
    error_log("  Base String: " . $base_string);
    error_log("  Generated Signature: " . $signature);

    return $signature;
}

// Alternative signature methods - Shopee sometimes uses different formats
function shopee_sign_alternative1($partner_id, $path, $timestamp, $partner_key)
{
    // Format: partner_id + timestamp + path
    $base_string = $partner_id . $timestamp . $path;
    return hash_hmac('sha256', $base_string, $partner_key);
}

function shopee_sign_alternative2($partner_id, $path, $timestamp, $partner_key)
{
    // Format: path + partner_id + timestamp
    $base_string = $path . $partner_id . $timestamp;
    return hash_hmac('sha256', $base_string, $partner_key);
}

function shopee_sign_alternative3($partner_id, $path, $timestamp, $partner_key)
{
    // Format: timestamp + partner_id + path
    $base_string = $timestamp . $partner_id . $path;
    return hash_hmac('sha256', $base_string, $partner_key);
}

// OAuth Token Exchange
$path = '/api/v2/auth/token/get';
$timestamp = time();

// Generate multiple signature formats to try
$signature = shopee_generate_signature($partner_id, $path, $timestamp);
$sign_alt1 = shopee_sign_alternative1($partner_id, $path, $timestamp, $partner_key);
$sign_alt2 = shopee_sign_alternative2($partner_id, $path, $timestamp, $partner_key);
$sign_alt3 = shopee_sign_alternative3($partner_id, $path, $timestamp, $partner_key);

// Debug: log all signature formats
error_log("Shopee All Signature Formats:");
error_log("  Method 1 (partner_id + path + timestamp): " . $signature);
error_log("  Method 2 (partner_id + timestamp + path): " . $sign_alt1);
error_log("  Method 3 (path + partner_id + timestamp): " . $sign_alt2);
error_log("  Method 4 (timestamp + partner_id + path): " . $sign_alt3);

// Additional debugging information
error_log("Shopee Debug Details:");
error_log("  Partner ID Type: " . gettype($partner_id) . " = " . $partner_id);
error_log("  Partner Key Length: " . strlen($partner_key));
error_log("  Path: " . $path);
error_log("  Timestamp: " . $timestamp . " (" . date('Y-m-d H:i:s', $timestamp) . ")");
error_log("  Current Server Time: " . date('Y-m-d H:i:s'));

// Check if timestamp is too old (Shopee might reject old timestamps)
$currentTime = time();
$timeDiff = abs($currentTime - $timestamp);
if ($timeDiff > 300) { // 5 minutes tolerance
    error_log("Shopee WARNING: Timestamp difference is " . $timeDiff . " seconds - might be too old");
}

// Function to try API call with different signatures
function tryShopeeTokenExchange($host, $path, $partner_id, $timestamp, $signature, $payload)
{
    $query_params = [
        'partner_id' => (string)$partner_id,
        'timestamp'  => $timestamp,
        'sign'       => $signature
    ];

    $url = rtrim($host, '/') . $path . '?' . http_build_query($query_params);

    error_log("Shopee Trying Token Exchange with signature: " . substr($signature, 0, 20) . "...");
    error_log("  URL: " . $url);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL Error: " . $error);
    }

    curl_close($ch);

    error_log("Shopee Response - HTTP: " . $http_code . ", Body: " . $response);

    return [
        'http_code' => $http_code,
        'response' => $response,
        'data' => json_decode($response, true)
    ];
}

// Prepare payload for token exchange
$payload = [
    'code' => $code,
    'shop_id' => (int)$shop_id,
    'partner_id' => (int)$partner_id
];

error_log("Shopee Token Exchange Payload: " . json_encode($payload));

// Try method 1 first (original)
$result = null;
$success = false;

try {
    $result = tryShopeeTokenExchange($host, $path, $partner_id, $timestamp, $signature, $payload);

    if ($result['http_code'] === 200 && !isset($result['data']['error'])) {
        $success = true;
        error_log("Shopee Success with Method 1");
    } elseif (isset($result['data']['error']) && $result['data']['error'] === 'error_sign') {
        error_log("Shopee Method 1 failed with wrong sign, trying Method 2");

        // Try method 2
        $result = tryShopeeTokenExchange($host, $path, $partner_id, $timestamp, $sign_alt1, $payload);

        if ($result['http_code'] === 200 && !isset($result['data']['error'])) {
            $success = true;
            error_log("Shopee Success with Method 2");
        } elseif (isset($result['data']['error']) && $result['data']['error'] === 'error_sign') {
            error_log("Shopee Method 2 failed with wrong sign, trying Method 3");

            // Try method 3
            $result = tryShopeeTokenExchange($host, $path, $partner_id, $timestamp, $sign_alt2, $payload);

            if ($result['http_code'] === 200 && !isset($result['data']['error'])) {
                $success = true;
                error_log("Shopee Success with Method 3");
            } elseif (isset($result['data']['error']) && $result['data']['error'] === 'error_sign') {
                error_log("Shopee Method 3 failed with wrong sign, trying Method 4");

                // Try method 4
                $result = tryShopeeTokenExchange($host, $path, $partner_id, $timestamp, $sign_alt3, $payload);

                if ($result['http_code'] === 200 && !isset($result['data']['error'])) {
                    $success = true;
                    error_log("Shopee Success with Method 4");
                } else {
                    error_log("Shopee All methods failed");
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Shopee Exception: " . $e->getMessage());
    echo "<script>alert('Gagal menghubungi Shopee: " . htmlspecialchars($e->getMessage(), ENT_QUOTES) . "');document.location.href='shopee-settings';</script>";
    exit;
}

if (!$success || !$result) {
    $error_msg = 'Semua metode signature gagal';
    $request_id = 'N/A';

    if ($result && isset($result['data']['error'])) {
        $error_msg = isset($result['data']['message']) ? $result['data']['message'] : 'Unknown error';
        $request_id = isset($result['data']['request_id']) ? $result['data']['request_id'] : 'N/A';
    }

    error_log("Shopee Final Error: " . $error_msg . " (Request ID: " . $request_id . ")");

    echo "<script>alert('Shopee error: " . htmlspecialchars($error_msg, ENT_QUOTES) . "\\nRequest ID: " . htmlspecialchars($request_id, ENT_QUOTES) . "\\n\\nCek error log untuk detail signature yang dibuat.');document.location.href='shopee-settings';</script>";
    exit;
}

// Success - extract tokens
$data = $result['data'];

// Extract tokens
$access_token  = isset($data['access_token']) ? $data['access_token'] : '';
$refresh_token = isset($data['refresh_token']) ? $data['refresh_token'] : '';

if (empty($access_token) || empty($refresh_token)) {
    error_log("Shopee Error: Missing tokens in response");
    echo "<script>alert('Token tidak diterima dari Shopee.');document.location.href='shopee-settings';</script>";
    exit;
}

error_log("Shopee Success: Tokens received");
error_log("  Access Token: " . substr($access_token, 0, 20) . "...");
error_log("  Refresh Token: " . substr($refresh_token, 0, 20) . "...");

// Save tokens to database
$access_sql  = "'" . mysqli_real_escape_string($conn, $access_token) . "'";
$refresh_sql = "'" . mysqli_real_escape_string($conn, $refresh_token) . "'";
$shop_sql    = (int)$shop_id;

$exists = mysqli_query($conn, "SELECT id FROM shopee_settings WHERE cabang = " . $sessionCabang . " LIMIT 1");
if ($exists && mysqli_num_rows($exists) > 0) {
    $update_sql = "UPDATE shopee_settings SET 
                   shop_id = " . $shop_sql . ", 
                   access_token = " . $access_sql . ", 
                   refresh_token = " . $refresh_sql . ", 
                   updated_at = NOW() 
                   WHERE cabang = " . $sessionCabang;

    if (mysqli_query($conn, $update_sql)) {
        error_log("Shopee Success: Settings updated in database");
    } else {
        error_log("Shopee Error: Failed to update database: " . mysqli_error($conn));
    }
} else {
    $insert_sql = "INSERT INTO shopee_settings (cabang, shop_id, access_token, refresh_token, updated_at) 
                   VALUES (" . $sessionCabang . ", " . $shop_sql . ", " . $access_sql . ", " . $refresh_sql . ", NOW())";

    if (mysqli_query($conn, $insert_sql)) {
        error_log("Shopee Success: Settings inserted to database");
    } else {
        error_log("Shopee Error: Failed to insert to database: " . mysqli_error($conn));
    }
}

echo "<script>alert('Toko Shopee berhasil terhubung!\\nShop ID: " . $shop_id . "');document.location.href='shopee-settings';</script>";
exit;
