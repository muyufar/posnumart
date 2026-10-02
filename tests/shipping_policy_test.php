<?php
/** php tests/shipping_policy_test.php — no production database access. */
require_once dirname(__DIR__).'/shared/NugrosirShipping.php';
require_once dirname(__DIR__).'/aksi/marketplace-shipping.php';

$assert = function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
$p = \Nugrosir\Shipping::policy();
$assert(\Nugrosir\Shipping::payout(8, 45, 3, $p) === 18000, 'Pembayaran gabungan');
$assert(array_sum(\Nugrosir\Shipping::split(17000, 3)) === 17000, 'Alokasi rupiah harus tepat');
$db = new PDO('sqlite::memory:');
$assert(!marketplace_shipping_ready($db), 'Database lama harus kompatibel');
$db->exec('CREATE TABLE shipping_quotes (id INTEGER, trip_id INTEGER, order_id INTEGER, snapshot_json TEXT, actual_allocation INTEGER)');
$db->exec('CREATE TABLE orders (id INTEGER, numart_invoice TEXT)');
$db->exec('CREATE TABLE shipping_trips (id INTEGER, status TEXT)');
$db->exec("INSERT INTO orders VALUES (1, 'INV-TEST')");
$db->exec("INSERT INTO shipping_trips VALUES (1, 'planned')");
$q = $db->prepare('INSERT INTO shipping_quotes VALUES (1, 1, 1, ?, NULL)');
$q->execute([json_encode(['money' => ['courier_allocation' => 8000, 'customer_fee' => 6000, 'subsidy' => 2000]])]);
$map = marketplace_shipping_invoice_map($db);
$assert($map['INV-TEST']['money']['courier_allocation'] === 8000, 'Subsidi tidak mengurangi upah');
$assert($map['INV-TEST']['trip_status'] === 'planned', 'Belum dibayar sebelum perjalanan ditutup');
$sibling = dirname(__DIR__, 2).'/belanja.numart.id/app/Support/NugrosirShipping.php';
if (is_file($sibling)) $assert(hash_file('sha256', $sibling) === hash_file('sha256', dirname(__DIR__).'/shared/NugrosirShipping.php'), 'Mesin tarif dua repo harus identik');
echo "OK: tarif, alokasi, kompatibilitas, pemisahan subsidi, sinkronisasi engine\n";
