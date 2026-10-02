<?php

require_once dirname(__DIR__).'/shared/NugrosirShipping.php';

/** Missing migration is legacy mode; other database errors must not hide payroll. */
function marketplace_shipping_ready(?PDO $db): bool
{
    if (!$db) return false;
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        return (bool) $db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'shipping_quotes'")->fetchColumn();
    }
    $q = $db->query("SHOW TABLES LIKE 'shipping_quotes'");
    return (bool) $q->fetchColumn();
}

function marketplace_shipping_invoice_map(PDO $db): array
{
    $engine = new \Nugrosir\Shipping($db);
    $rows = $engine->rows('SELECT o.numart_invoice, q.trip_id, q.snapshot_json, q.actual_allocation, t.status AS trip_status
        FROM shipping_quotes q JOIN orders o ON o.id = q.order_id JOIN shipping_trips t ON t.id = q.trip_id WHERE o.numart_invoice IS NOT NULL');
    $map = [];
    foreach ($rows as $r) {
        $r['money'] = json_decode($r['snapshot_json'], true)['money'];
        $map[$r['numart_invoice']] = $r;
    }
    return $map;
}

/** Exclude quote-based invoice fees, then add each settled trip exactly once. */
function marketplace_shipping_earnings(mysqli $conn, PDO $db, int $courier, int $branch, array $earn): array
{
    if (!marketplace_shipping_ready($db)) return $earn;
    $map = marketplace_shipping_invoice_map($db);
    $res = mysqli_query($conn, "SELECT penjualan_invoice, invoice_ongkir, invoice_date_selesai_kurir FROM invoice
        WHERE invoice_kurir = $courier AND invoice_cabang = $branch AND invoice_status_kurir = 3");
    while ($row = mysqli_fetch_assoc($res)) {
        if (!isset($map[$row['penjualan_invoice']])) continue;
        $fee = (int) $row['invoice_ongkir'];
        $earn['total_fee'] -= $fee;
        if (str_starts_with($row['invoice_date_selesai_kurir'], date('d F Y'))) $earn['today_fee'] -= $fee;
        if (str_contains($row['invoice_date_selesai_kurir'], date('F Y'))) $earn['month_fee'] -= $fee;
    }
    $engine = new \Nugrosir\Shipping($db);
    $trips = $engine->rows("SELECT actual_pay, settled_at FROM shipping_trips WHERE courier_id = ? AND branch_id = ? AND status = 'settled'", [$courier, $branch]);
    $today = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    foreach ($trips as $trip) {
        $local = (new DateTimeImmutable($trip['settled_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'));
        $pay = (int) $trip['actual_pay'];
        $earn['total_fee'] += $pay;
        if ($local->format('Y-m-d') === $today->format('Y-m-d')) $earn['today_fee'] += $pay;
        if ($local->format('Y-m') === $today->format('Y-m')) $earn['month_fee'] += $pay;
    }
    return $earn;
}
