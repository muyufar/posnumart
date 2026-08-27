<?php

/**
 * Helper backoffice pesanan Belanja Online (marketplace).
 */

function marketplace_load_config(): array
{
    $path = __DIR__ . '/marketplace-config.php';
    if (!is_file($path)) {
        return [
            'admin_url' => '',
            'sqlite_path' => '',
            'db_host' => '',
            'db_name' => '',
            'db_user' => '',
            'db_pass' => '',
            'public_url' => '',
            'api_url' => '',
            'api_secret' => '',
        ];
    }

    include $path;

    return [
        'admin_url' => $marketplace_belanja_admin_url ?? '',
        'sqlite_path' => $marketplace_belanja_sqlite_path ?? '',
        'db_host' => $marketplace_belanja_db_host ?? '',
        'db_name' => $marketplace_belanja_db_name ?? '',
        'db_user' => $marketplace_belanja_db_user ?? '',
        'db_pass' => $marketplace_belanja_db_pass ?? '',
        'public_url' => $marketplace_belanja_public_url ?? '',
        'api_url' => $marketplace_belanja_api_url ?? '',
        'api_secret' => $marketplace_wa_secret ?? '',
    ];
}

function marketplace_cabang_toko(): array
{
    return [
        0 => 'Gudang Nugrasir',
        1 => 'Dukun',
        2 => 'Pakis',
        3 => 'PP Srumbung',
        5 => 'Tegalrejo',
    ];
}

function marketplace_cabang_label(int $cabangId): string
{
    $map = marketplace_cabang_toko();

    return $map[$cabangId] ?? ('Cabang ' . $cabangId);
}

function marketplace_can_access(string $levelLogin): bool
{
    return $levelLogin !== 'kurir';
}

function marketplace_kasir_label(): string
{
    return 'Belanja Online';
}

function marketplace_is_online_invoice(array $invoice): bool
{
    return trim((string) ($invoice['invoice_marketplace'] ?? '')) !== '';
}

function marketplace_status_badge(string $status): string
{
    $map = [
        'pending_payment' => '<span class="badge badge-secondary">Menunggu bayar (VA)</span>',
        'pending_transfer' => '<span class="badge badge-warning">Menunggu transfer</span>',
        'pending_cod' => '<span class="badge badge-info">COD — menunggu proses</span>',
        'proof_submitted' => '<span class="badge badge-primary">Bukti diupload</span>',
        'paid' => '<span class="badge badge-success">Lunas</span>',
        'processing' => '<span class="badge badge-success">Diproses</span>',
        'shipped' => '<span class="badge badge-primary">Dikirim</span>',
        'completed' => '<span class="badge badge-secondary">Selesai</span>',
        'cancelled' => '<span class="badge badge-dark">Batal</span>',
    ];

    return $map[$status] ?? '<span class="badge badge-light">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>';
}

function marketplace_payment_label(string $method): string
{
    return strtoupper($method) === 'COD' ? 'COD' : 'Transfer';
}

/**
 * Koneksi PDO ke database belanja (MySQL production atau SQLite lokal).
 */
function marketplace_belanja_pdo(array $cfg): ?PDO
{
    $host = trim((string) ($cfg['db_host'] ?? ''));
    $name = trim((string) ($cfg['db_name'] ?? ''));
    $user = (string) ($cfg['db_user'] ?? '');
    $pass = (string) ($cfg['db_pass'] ?? '');

    if ($host !== '' && $name !== '') {
        try {
            return new PDO(
                'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4',
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (Throwable $e) {
            // fallback sqlite
        }
    }

    $sqlitePath = trim((string) ($cfg['sqlite_path'] ?? ''));
    if ($sqlitePath === '' || !is_file($sqlitePath)) {
        return null;
    }

    try {
        return new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (Throwable $e) {
        return null;
    }
}

function marketplace_belanja_configured(array $cfg): bool
{
    $host = trim((string) ($cfg['db_host'] ?? ''));
    $name = trim((string) ($cfg['db_name'] ?? ''));

    if ($host !== '' && $name !== '') {
        return true;
    }

    $sqlitePath = trim((string) ($cfg['sqlite_path'] ?? ''));

    return $sqlitePath !== '' && is_file($sqlitePath);
}

function marketplace_proof_url(string $path, array $cfg): string
{
    $path = ltrim($path, '/');
    $base = rtrim((string) ($cfg['public_url'] ?? ''), '/');

    if ($base === '') {
        return '/storage/' . $path;
    }

    return $base . '/storage/' . $path;
}

/**
 * Pesanan aktif dari belanja.numart.id (belum masuk invoice POS).
 *
 * @return array<int, array<string, mixed>>
 */
function marketplace_fetch_open_orders(?PDO $pdo, int $filterCabang = -1): array
{
    if (!$pdo) {
        return [];
    }

    try {
        $sql = "SELECT id, order_number, customer_name, customer_phone, customer_address,
                       fulfillment_cabang, fulfillment_label, grand_total, status, payment_method,
                       payment_proof_path, payment_proof_at, created_at, expires_at, numart_invoice
                FROM orders
                WHERE status IN ('pending_transfer', 'pending_cod', 'proof_submitted')
                ORDER BY
                  CASE WHEN payment_proof_path IS NOT NULL AND payment_proof_path != '' THEN 0 ELSE 1 END,
                  COALESCE(payment_proof_at, created_at) DESC,
                  id DESC
                LIMIT 200";

        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }

    if ($filterCabang < 0) {
        return $rows;
    }

    return array_values(array_filter($rows, function ($r) use ($filterCabang) {
        return (int) ($r['fulfillment_cabang'] ?? 0) === $filterCabang;
    }));
}

/**
 * @return array<int, array<string, mixed>>
 */
function marketplace_fetch_order_items(?PDO $pdo, int $orderId): array
{
    if (!$pdo || $orderId < 1) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT barang_nama, barang_kode, qty, unit_price, line_total
             FROM order_items WHERE order_id = ? ORDER BY id'
        );
        $stmt->execute([$orderId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/** @deprecated gunakan marketplace_fetch_open_orders */
function marketplace_fetch_pending_orders(string $sqlitePath, int $filterCabang = -1): array
{
    $cfg = ['sqlite_path' => $sqlitePath];

    return marketplace_fetch_open_orders(marketplace_belanja_pdo($cfg), $filterCabang);
}

/**
 * Konfirmasi pembayaran via API Laravel (buat invoice POS).
 *
 * @return array{success: bool, message: string, invoice?: string}
 */
function marketplace_confirm_order_payment(array $cfg, int $orderId): array
{
    $apiUrl = rtrim((string) ($cfg['api_url'] ?? ''), '/');
    $secret = (string) ($cfg['api_secret'] ?? '');

    if ($apiUrl === '' || $secret === '' || $orderId < 1) {
        return ['success' => false, 'message' => 'API belanja belum dikonfigurasi (api_url / secret).'];
    }

    $url = $apiUrl . '/api/numart/orders/' . $orderId . '/confirm-payment';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Marketplace-Secret: ' . $secret,
        ],
        CURLOPT_POSTFIELDS => '{}',
        CURLOPT_TIMEOUT => 30,
    ]);

    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        return ['success' => false, 'message' => 'Tidak dapat menghubungi server belanja.'];
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        return ['success' => false, 'message' => 'Respon server tidak valid (HTTP ' . $code . ').'];
    }

    return [
        'success' => (bool) ($json['success'] ?? false),
        'message' => (string) ($json['message'] ?? 'Terjadi kesalahan.'),
        'invoice' => isset($json['invoice']) ? (string) $json['invoice'] : '',
    ];
}

/**
 * Ringkasan invoice marketplace di MySQL Numart.
 */
function marketplace_invoice_summary(mysqli $conn, int $sessionCabang): array
{
    $where = "invoice_marketplace != '' AND invoice_marketplace IS NOT NULL";
    if ($sessionCabang > 0) {
        $where .= ' AND invoice_cabang = ' . (int) $sessionCabang;
    }

    $today = date('Y-m-d');
    $res = mysqli_query($conn, "
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN invoice_date = '$today' THEN 1 ELSE 0 END) AS hari_ini,
            COALESCE(SUM(invoice_sub_total), 0) AS omzet
        FROM invoice
        WHERE $where
    ");

    $row = $res ? mysqli_fetch_assoc($res) : null;

    return [
        'total' => (int) ($row['total'] ?? 0),
        'hari_ini' => (int) ($row['hari_ini'] ?? 0),
        'omzet' => (float) ($row['omzet'] ?? 0),
    ];
}
