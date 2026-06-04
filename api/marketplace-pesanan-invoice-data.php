<?php

include '../aksi/koneksi.php';

$cabang = isset($_GET['cabang']) ? (int) $_GET['cabang'] : 0;

$dbDetails = [
    'host' => $servername,
    'user' => $username,
    'pass' => $password,
    'db' => $db,
];

$table = <<<EOT
(
    SELECT
        i.invoice_id,
        i.penjualan_invoice,
        i.invoice_date,
        i.invoice_marketplace,
        i.invoice_cabang,
        i.invoice_customer_category,
        i.invoice_sub_total,
        i.status,
        c.customer_nama
    FROM invoice i
    LEFT JOIN customer c ON c.customer_id = i.invoice_customer
) temp
EOT;

$primaryKey = 'invoice_id';

$columns = [
    ['db' => 'invoice_id', 'dt' => 0],
    ['db' => 'invoice_marketplace', 'dt' => 1],
    ['db' => 'invoice_date', 'dt' => 2],
    ['db' => 'customer_nama', 'dt' => 3],
    ['db' => 'invoice_cabang', 'dt' => 4],
    ['db' => 'invoice_sub_total', 'dt' => 5],
    ['db' => 'penjualan_invoice', 'dt' => 6],
];

require '../aksi/ssp.php';

$where = "invoice_marketplace != '' AND invoice_marketplace IS NOT NULL";
if ($cabang > 0) {
    $where .= ' AND invoice_cabang = ' . $cabang;
}

echo json_encode(
    SSP::simple($_GET, $dbDetails, $table, $primaryKey, $columns, null, $where)
);
