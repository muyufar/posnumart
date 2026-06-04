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
        ];
    }

    include $path;

    return [
        'admin_url' => $marketplace_belanja_admin_url ?? '',
        'sqlite_path' => $marketplace_belanja_sqlite_path ?? '',
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
        'pending_payment' => '<span class="badge badge-warning">Menunggu bayar</span>',
        'paid' => '<span class="badge badge-success">Lunas</span>',
        'processing' => '<span class="badge badge-info">Diproses</span>',
        'shipped' => '<span class="badge badge-primary">Dikirim</span>',
        'completed' => '<span class="badge badge-secondary">Selesai</span>',
        'cancelled' => '<span class="badge badge-dark">Batal</span>',
    ];

    return $map[$status] ?? '<span class="badge badge-light">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>';
}

/**
 * Pesanan belum masuk invoice (baca DB Laravel sqlite).
 *
 * @return array<int, array<string, mixed>>
 */
function marketplace_fetch_pending_orders(string $sqlitePath, int $filterCabang = -1): array
{
    if ($sqlitePath === '' || !is_file($sqlitePath)) {
        return [];
    }

    try {
        $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (Throwable $e) {
        return [];
    }

    $sql = "SELECT id, order_number, customer_name, customer_phone, fulfillment_cabang, fulfillment_label,
                   grand_total, status, created_at, expires_at
            FROM orders
            WHERE status = 'pending_payment'
            ORDER BY id DESC
            LIMIT 200";

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if ($filterCabang < 0) {
        return $rows;
    }

    return array_values(array_filter($rows, function ($r) use ($filterCabang) {
        return (int) ($r['fulfillment_cabang'] ?? 0) === $filterCabang;
    }));
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
