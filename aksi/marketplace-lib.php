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
        'kasir_user_id' => (int) ($marketplace_kasir_user_id ?? 0),
        'default_customer_id' => (int) ($marketplace_default_customer_id ?? 1),
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

function marketplace_normalize_base_url(string $url): string
{
    $url = rtrim(trim($url), '/');

    // Typo umum saat input manual di config live.
    if (preg_match('/\.idt$/i', $url)) {
        $url = preg_replace('/\.idt$/i', '.id', $url);
    }

    // Hindari redirect 301 HTTP → HTTPS di Hostinger.
    if (preg_match('#^http://([^/]*numart\.id[^/]*)$#i', $url)) {
        $url = preg_replace('#^http://#i', 'https://', $url);
    }

    return $url;
}

/**
 * @return array{success: bool, message: string, body: string, code: int, url: string}
 */
function marketplace_http_post_json(string $url, array $headers, string $payload = '{}'): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
    ];

    if (defined('CURLOPT_POSTREDIR')) {
        $opts[CURLOPT_POSTREDIR] = CURL_REDIR_POST_ALL;
    }

    curl_setopt_array($ch, $opts);

    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return [
            'success' => false,
            'message' => 'Tidak dapat menghubungi server belanja: ' . ($curlError ?: 'koneksi gagal'),
            'body' => '',
            'code' => 0,
            'url' => $effectiveUrl !== '' ? $effectiveUrl : $url,
        ];
    }

    return [
        'success' => true,
        'message' => '',
        'body' => (string) $body,
        'code' => $code,
        'url' => $effectiveUrl !== '' ? $effectiveUrl : $url,
    ];
}

function marketplace_proof_url(string $path, array $cfg): string
{
    $path = ltrim($path, '/');
    $base = marketplace_normalize_base_url((string) ($cfg['public_url'] ?? ''));

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
 * @return array<string, mixed>|null
 */
function marketplace_fetch_order_row(?PDO $pdo, int $orderId): ?array
{
    if (!$pdo || $orderId < 1) {
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT o.*, u.numart_customer_id
             FROM orders o
             LEFT JOIN users u ON u.id = o.user_id
             WHERE o.id = ?
             LIMIT 1'
        );
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function marketplace_fetch_order_items_full(?PDO $pdo, int $orderId): array
{
    if (!$pdo || $orderId < 1) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT barang_id, barang_kode, barang_nama, qty, unit_price, line_total,
                    harga_beli, satuan_id, konversi_isi
             FROM order_items WHERE order_id = ? ORDER BY id'
        );
        $stmt->execute([$orderId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function marketplace_validate_order_for_confirm(array $order): ?string
{
    $status = (string) ($order['status'] ?? '');
    $method = (string) ($order['payment_method'] ?? '');

    if (trim((string) ($order['numart_invoice'] ?? '')) !== '') {
        return null;
    }

    if ($method === 'transfer') {
        if ($status !== 'proof_submitted') {
            return 'Transfer belum ada bukti upload atau sudah diproses.';
        }
        if (trim((string) ($order['payment_proof_path'] ?? '')) === '') {
            return 'Bukti transfer belum diupload member.';
        }
    } elseif ($method === 'cod') {
        if ($status !== 'pending_cod') {
            return 'Pesanan COD tidak dalam status menunggu proses.';
        }
    } else {
        return 'Metode pembayaran tidak dikenali.';
    }

    return null;
}

function marketplace_resolve_kasir_user_id(mysqli $conn, array $cfg): int
{
    $configured = (int) ($cfg['kasir_user_id'] ?? 0);
    if ($configured > 0) {
        $res = mysqli_query(
            $conn,
            'SELECT user_id FROM user WHERE user_id = ' . $configured . " AND user_status = '1' LIMIT 1"
        );
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            return (int) $row['user_id'];
        }
    }

    $label = mysqli_real_escape_string($conn, marketplace_kasir_label());
    $res = mysqli_query(
        $conn,
        "SELECT user_id FROM user WHERE user_nama = '$label' AND user_status = '1' LIMIT 1"
    );
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return (int) $row['user_id'];
    }

    throw new RuntimeException(
        'User kasir "' . marketplace_kasir_label() . '" belum ada. Jalankan db/migration_marketplace_kasir_user.sql.'
    );
}

function marketplace_next_invoice_count(mysqli $conn, int $cabang): int
{
    $cabang = (int) $cabang;
    $res = mysqli_query(
        $conn,
        "SELECT penjualan_invoice_count FROM invoice WHERE invoice_cabang = $cabang ORDER BY invoice_id DESC LIMIT 1"
    );
    $last = 0;
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $last = (int) ($row['penjualan_invoice_count'] ?? 0);
    }

    return $last + 1;
}

/**
 * Buat invoice POS langsung dari DB belanja (tanpa API Laravel / NUMART_DB di belanja).
 *
 * @throws RuntimeException
 */
function marketplace_sync_order_to_pos(mysqli $conn, PDO $belanjaPdo, int $orderId, array $cfg): string
{
    $order = marketplace_fetch_order_row($belanjaPdo, $orderId);
    if (!$order) {
        throw new RuntimeException('Pesanan tidak ditemukan.');
    }

    $existingInvoice = trim((string) ($order['numart_invoice'] ?? ''));
    if ($existingInvoice !== '') {
        return $existingInvoice;
    }

    $validationError = marketplace_validate_order_for_confirm($order);
    if ($validationError !== null) {
        throw new RuntimeException($validationError);
    }

    $items = marketplace_fetch_order_items_full($belanjaPdo, $orderId);
    if ($items === []) {
        throw new RuntimeException('Item pesanan kosong.');
    }

    $cabang = (int) ($order['fulfillment_cabang'] ?? 0);
    $kasirId = marketplace_resolve_kasir_user_id($conn, $cfg);
    $customerId = (int) ($order['numart_customer_id'] ?? 0);
    if ($customerId < 1) {
        $customerId = (int) ($cfg['default_customer_id'] ?? 1);
    }

    $invoiceCount = marketplace_next_invoice_count($conn, $cabang);
    $invoiceNo = date('YmdHis') . $invoiceCount . $kasirId;
    $tgl = date('d F Y g:i:s a');
    $date = date('Y-m-d');
    $ym = date('Y-m');

    $totalBeli = 0;
    foreach ($items as $item) {
        $totalBeli += (int) ($item['harga_beli'] ?? 0) * (int) ($item['qty'] ?? 0);
    }

    $total = (int) ($order['subtotal'] ?? 0);
    $ongkir = (int) ($order['shipping_fee'] ?? 0);
    $diskon = (int) ($order['discount'] ?? 0);
    $subTotal = (int) ($order['grand_total'] ?? 0);
    $priceTier = (int) ($order['price_tier'] ?? 0);
    $orderNumber = mysqli_real_escape_string($conn, (string) ($order['order_number'] ?? ''));

    mysqli_begin_transaction($conn);

    try {
        $ok = mysqli_query($conn, "INSERT INTO invoice (
            penjualan_invoice, penjualan_invoice_count, invoice_tgl, invoice_customer,
            invoice_customer_category, invoice_kurir, invoice_status_kurir, status,
            invoice_tipe_transaksi, invoice_total_beli, invoice_total, invoice_ongkir,
            invoice_diskon, invoice_sub_total, invoice_bayar, invoice_kembali, invoice_kasir,
            invoice_date, invoice_date_year_month, invoice_date_edit, invoice_kasir_edit,
            invoice_total_beli_lama, invoice_total_lama, invoice_ongkir_lama,
            invoice_sub_total_lama, invoice_bayar_lama, invoice_kembali_lama,
            invoice_marketplace, invoice_ekspedisi, invoice_no_resi, invoice_date_selesai_kurir,
            invoice_piutang, invoice_piutang_dp, invoice_piutang_jatuh_tempo, invoice_piutang_lunas,
            invoice_draft, invoice_cabang
        ) VALUES (
            '$invoiceNo', '$invoiceCount', '$tgl', '$customerId',
            $priceTier, '0', 1, 2,
            1, $totalBeli, $total, $ongkir,
            $diskon, $subTotal, $subTotal, 0, '$kasirId',
            '$date', '$ym', ' ', ' ',
            $totalBeli, '$total', $ongkir,
            $subTotal, '$subTotal', '0',
            '$orderNumber', 0, '-', '-',
            0, '0', '0', 0,
            0, $cabang
        )");

        if (!$ok) {
            throw new RuntimeException('Gagal insert invoice: ' . mysqli_error($conn));
        }

        foreach ($items as $item) {
            $barangId = (int) ($item['barang_id'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);
            $konversi = max(1, (int) ($item['konversi_isi'] ?? 1));
            $qtyKeranjang = $qty * $konversi;
            $satuanId = (int) ($item['satuan_id'] ?? 0);
            $hargaBeli = (int) ($item['harga_beli'] ?? 0);
            $unitPrice = (int) ($item['unit_price'] ?? 0);

            $ok = mysqli_query($conn, "INSERT INTO penjualan (
                penjualan_barang_id, barang_id, barang_qty, barang_qty_keranjang,
                barang_qty_konversi_isi, keranjang_satuan, keranjang_harga_beli,
                keranjang_harga, keranjang_harga_parent, keranjang_harga_edit,
                keranjang_id_kasir, penjualan_invoice, penjualan_date, penjualan_date_year_month,
                barang_qty_lama, barang_qty_lama_parent, barang_option_sn, barang_sn_id,
                barang_sn_desc, invoice_customer_category, penjualan_cabang
            ) VALUES (
                $barangId, $barangId, $qty, $qtyKeranjang,
                $konversi, $satuanId, '$hargaBeli',
                '$unitPrice', $unitPrice, 0,
                $kasirId, '$invoiceNo', '$date', '$ym',
                '$qty', '$qty', 0, 0,
                '0', $priceTier, $cabang
            )");

            if (!$ok) {
                throw new RuntimeException('Gagal insert penjualan: ' . mysqli_error($conn));
            }

            mysqli_query($conn, "INSERT INTO terlaris (barang_id, barang_terjual) VALUES ($barangId, $qty)");
        }

        $stmt = $belanjaPdo->prepare(
            "UPDATE orders SET numart_invoice = ?, status = 'processing', paid_at = ?, updated_at = ? WHERE id = ?"
        );
        $now = date('Y-m-d H:i:s');
        $stmt->execute([$invoiceNo, $now, $now, $orderId]);

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw new RuntimeException($e->getMessage());
    }

    return $invoiceNo;
}

/**
 * Konfirmasi pembayaran + invoice POS (langsung dari POS, tanpa NUMART_DB di Laravel).
 *
 * @return array{success: bool, message: string, invoice?: string}
 */
function marketplace_confirm_and_sync_order(mysqli $conn, ?PDO $belanjaPdo, int $orderId, array $cfg): array
{
    if (!$belanjaPdo || $orderId < 1) {
        return ['success' => false, 'message' => 'Database belanja belum dikonfigurasi.'];
    }

    try {
        $invoice = marketplace_sync_order_to_pos($conn, $belanjaPdo, $orderId, $cfg);

        return [
            'success' => true,
            'message' => 'Pembayaran dikonfirmasi. Invoice POS: ' . $invoice,
            'invoice' => $invoice,
        ];
    } catch (Throwable $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Konfirmasi via API Laravel (fallback — butuh NUMART_DB di server belanja).
 *
 * @return array{success: bool, message: string, invoice?: string}
 */
function marketplace_confirm_order_payment(array $cfg, int $orderId): array
{
    $apiUrl = marketplace_normalize_base_url((string) ($cfg['api_url'] ?? ''));
    $secret = (string) ($cfg['api_secret'] ?? '');

    if ($apiUrl === '' || $secret === '' || $orderId < 1) {
        return ['success' => false, 'message' => 'API belanja belum dikonfigurasi (api_url / secret).'];
    }

    $url = $apiUrl . '/api/numart/orders/' . $orderId . '/confirm-payment';

    $response = marketplace_http_post_json($url, [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-Marketplace-Secret: ' . $secret,
    ]);

    if (!$response['success']) {
        return ['success' => false, 'message' => $response['message']];
    }

    $body = $response['body'];
    $code = $response['code'];

    $json = json_decode($body, true);
    if (!is_array($json)) {
        $hint = match (true) {
            $code === 301, $code === 302 => ' Coba set marketplace_belanja_api_url=https://belanja.numart.id (HTTPS).',
            $code === 404 => ' Route API belum ada di server belanja (git pull + php artisan route:cache).',
            $code === 405 => ' Method tidak didukung — pastikan URL API benar dan route POST sudah deploy.',
            $code === 403 => ' Secret salah — samakan marketplace_wa_secret dengan NUMART_WA_API_SECRET di .env belanja.',
            default => '',
        };

        return [
            'success' => false,
            'message' => 'Respon server tidak valid (HTTP ' . $code . ').' . $hint,
        ];
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

function marketplace_verification_doc_url(string $path, array $cfg): string
{
    return marketplace_proof_url($path, $cfg);
}

function marketplace_verification_status_badge(string $status): string
{
    $map = [
        'none' => '<span class="badge badge-secondary">Belum upload</span>',
        'pending' => '<span class="badge badge-warning">Menunggu verifikasi</span>',
        'approved' => '<span class="badge badge-success">Disetujui</span>',
        'rejected' => '<span class="badge badge-danger">Ditolak</span>',
    ];

    return $map[$status] ?? '<span class="badge badge-light">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>';
}

function marketplace_customer_category_label(int $category): string
{
    return match ($category) {
        2 => 'Grosir',
        1 => 'Retail',
        default => 'Umum',
    };
}

/**
 * Member menunggu verifikasi KTP/foto warung (dari tabel customer Numart).
 *
 * @return array{rows: array<int, array<string, mixed>>, error: string|null}
 */
function marketplace_fetch_pending_member_verifications(mysqli $conn, int $filterCabang = -1): array
{
    $where = "customer_verifikasi_status = 'pending' AND customer_id NOT IN (0, 1)";
    if ($filterCabang >= 0) {
        $where .= ' AND customer_cabang = ' . (int) $filterCabang;
    }

    $sql = "SELECT customer_id, customer_nama, customer_kartu, customer_tlpn, customer_email,
                   customer_category, customer_cabang, customer_ktp_path, customer_foto_warung_path,
                   customer_verifikasi_at, customer_create
            FROM customer
            WHERE $where
            ORDER BY COALESCE(customer_verifikasi_at, customer_create) DESC, customer_id DESC
            LIMIT 100";

    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return [
            'rows' => [],
            'error' => 'Kolom verifikasi belum ada. Jalankan db/migration_customer_verifikasi.sql di MySQL Numart.',
        ];
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }

    return ['rows' => $rows, 'error' => null];
}

/**
 * Setujui / tolak verifikasi member + sinkron ke users belanja.numart.id.
 *
 * @return array{success: bool, message: string}
 */
function marketplace_set_member_verification(
    mysqli $conn,
    ?PDO $belanjaPdo,
    int $customerId,
    string $status
): array {
    if (!in_array($status, ['approved', 'rejected'], true)) {
        return ['success' => false, 'message' => 'Status tidak valid.'];
    }

    $customerId = (int) $customerId;
    if ($customerId < 1) {
        return ['success' => false, 'message' => 'Customer tidak valid.'];
    }

    $now = date('Y-m-d H:i:s');
    $statusEsc = mysqli_real_escape_string($conn, $status);
    $ok = mysqli_query(
        $conn,
        "UPDATE customer
         SET customer_verifikasi_status = '$statusEsc', customer_verifikasi_at = '$now'
         WHERE customer_id = $customerId AND customer_verifikasi_status = 'pending'"
    );

    if (!$ok) {
        return [
            'success' => false,
            'message' => 'Gagal update customer. Pastikan migration_customer_verifikasi.sql sudah dijalankan.',
        ];
    }

    if (mysqli_affected_rows($conn) < 1) {
        return ['success' => false, 'message' => 'Customer tidak ditemukan atau sudah diproses.'];
    }

    if ($belanjaPdo) {
        try {
            $stmt = $belanjaPdo->prepare(
                'UPDATE users
                 SET member_verification_status = ?, verification_reviewed_at = ?, updated_at = ?
                 WHERE numart_customer_id = ?'
            );
            $stmt->execute([$status, $now, $now, $customerId]);
        } catch (Throwable $e) {
            // Non-fatal: sumber kebenaran tetap di customer POS.
        }
    }

    $label = $status === 'approved' ? 'disetujui — member bisa COD' : 'ditolak';

    return ['success' => true, 'message' => 'Verifikasi member ' . $label . '.'];
}
