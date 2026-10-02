<?php
require_once dirname(__DIR__, 3).'/bootstrap/paths.php';
include '_header.php';
require_once numart_path('aksi/marketplace-lib.php');
require_once numart_path('shared/NugrosirShipping.php');
if (!in_array($levelLogin, ['admin', 'super admin'], true) || (int) $sessionCabang !== 0 || ($_SESSION['user_status'] ?? '0') !== '1') {
    http_response_code(403);
    exit('Hanya admin cabang Nugrosir yang dapat mengelola pilot ongkir.');
}
include '_nav.php';
include '_sidebar.php';
$_SESSION['shipping_csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['shipping_csrf'];
$h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rupiah = fn ($v) => $v === null ? 'HPP belum lengkap' : 'Rp '.number_format((float) $v, 0, ',', '.');
$engine = null;
$error = $notice = null;
$actor = 'pos:'.(int) $_SESSION['user_id'];
$number = function ($value, float $min, float $max): float {
    \Nugrosir\Shipping::check(is_scalar($value) && is_numeric($value) && is_finite((float) $value) && $value >= $min && $value <= $max, 'Angka di luar batas atau tidak valid.');
    return (float) $value;
};
$utc = function ($value): string {
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', (string) $value, new \DateTimeZone('Asia/Jakarta'));
    \Nugrosir\Shipping::check($date && $date->format('Y-m-d\TH:i') === $value, 'Jadwal tidak valid.');
    return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
};
try {
    $pdo = marketplace_belanja_pdo(marketplace_load_config());
    if (!$pdo) throw new \DomainException('Koneksi database belanja belum tersedia.');
    $engine = new \Nugrosir\Shipping($pdo);
    $campaign = $engine->campaign();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        \Nugrosir\Shipping::check(hash_equals($csrf, (string) ($_POST['csrf'] ?? '')), 'Sesi formulir tidak valid. Muat ulang halaman.');
        $action = $_POST['action'] ?? '';
        if ($action === 'activate') {
            $lat = $number($_POST['lat'] ?? '', -90, 90);
            $lng = $number($_POST['lng'] ?? '', -180, 180);
            $address = trim((string) ($_POST['store_address'] ?? ''));
            $category = (string) ($_POST['payment_category'] ?? '');
            \Nugrosir\Shipping::check(strlen($address) >= 10 && isset($_POST['verified']) && in_array($category, ['umi','regular'], true), 'Verifikasi titik toko, kategori QRIS, dan kesepakatan kurir.');
            $engine->transaction(function () use ($engine, $lat, $lng, $address, $category, $actor, $number) {
                $c = $engine->campaign();
                $p = json_decode($c['policy_json'], true);
                foreach (['vehicle_per_km','per_minute','per_order','minimum','handling','payment_fixed'] as $key) {
                    $p[$key] = (int) $number($_POST[$key] ?? '', 0, 1000000);
                }
                \Nugrosir\Shipping::check($p['vehicle_per_km'] > 0 && $p['per_minute'] > 0 && $p['minimum'] > 0, 'Biaya kendaraan, waktu dan minimum harus positif.');
                $p['payment_category'] = $category;
                $p['version'] = 'muntilan-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
                $store = json_encode(['lat' => $lat, 'lng' => $lng, 'address' => $address]);
                $end = $c['ends_at'] ?? gmdate('Y-m-d H:i:s', time() + 30 * 86400);
                \Nugrosir\Shipping::check($end > gmdate('Y-m-d H:i:s'), 'Pilot berakhir; evaluasi hasil sebelum membuat periode lanjutan.');
                $engine->run('UPDATE shipping_campaigns SET enabled = 1, store_json = ?, policy_json = ?, ends_at = ? WHERE id = 1', [$store, json_encode($p), $end]);
                $engine->event('configure', 1, $actor, ['policy' => $p, 'store' => json_decode($store, true)]);
            });
            $notice = 'Pilot diaktifkan. Tarif baru hanya berlaku untuk penawaran baru.';
        } elseif ($action === 'pause') {
            $engine->transaction(function () use ($engine, $actor) {
                $engine->run('UPDATE shipping_campaigns SET enabled = 0 WHERE id = 1');
                $engine->event('pause', 1, $actor);
            });
            $notice = 'Permintaan baru dihentikan. Penawaran yang sudah disepakati tetap berlaku.';
        } elseif ($action === 'approve') {
            $ids = $_POST['ids'] ?? [];
            \Nugrosir\Shipping::check(is_array($ids) && isset($_POST['route_verified']), 'Pilih permintaan dan konfirmasi kelayakan kendaraan/rute.');
            $verified = [];
            foreach ($ids as $id) {
                $v = $_POST['verified'][$id] ?? [];
                $verified[(int) $id] = ['km' => $number($v['km'] ?? '', 0.01, 1000), 'kg' => $number($v['kg'] ?? '', 0.01, 1000),
                    'lat' => $number($v['lat'] ?? '', -90, 90), 'lng' => $number($v['lng'] ?? '', -180, 180)];
            }
            $route = ['service' => (string) ($_POST['service'] ?? ''), 'km' => $number($_POST['km'] ?? '', 0.01, 3000),
                'minutes' => (int) $number($_POST['minutes'] ?? '', 1, 1440), 'note' => trim((string) ($_POST['note'] ?? '')),
                'start' => $utc($_POST['start'] ?? ''), 'end' => $utc($_POST['end'] ?? ''),
                'manual_pay' => (int) $number($_POST['manual_pay'] ?? 0, 0, 10000000)];
            $trip = $engine->approve($ids, $route, $verified, $actor, 0);
            $notice = 'Perjalanan #'.$trip.' dibuat. Pelanggan dapat meninjau total sebelum membuat pesanan.';
        } elseif ($action === 'cancel_quote') {
            $q = $engine->rows('SELECT branch_id FROM shipping_quotes WHERE id = ?', [(int) ($_POST['quote_id'] ?? 0)])[0] ?? null;
            \Nugrosir\Shipping::check($q && (int) $q['branch_id'] === 0, 'Bukan permintaan cabang ini.');
            $engine->cancelQuote((int) $_POST['quote_id'], $actor);
            $notice = 'Penawaran dibatalkan; anggaran subsidi dilepas.';
        } elseif ($action === 'settle') {
            $courier = (int) ($_POST['courier'] ?? 0);
            $noWork = isset($_POST['no_work']);
            if (!$noWork) {
                $valid = marketplace_fetch_kurir_users($conn, 0);
                $valid = array_filter($valid, fn ($r) => (int) $r['user_cabang'] === 0 && (int) $r['user_id'] === $courier);
                \Nugrosir\Shipping::check((bool) $valid, 'Pilih kurir aktif cabang Nugrosir.');
                $assigned = $engine->rows('SELECT o.numart_invoice FROM shipping_quotes q JOIN orders o ON o.id = q.order_id WHERE q.trip_id = ? AND o.status <> ?', [(int) ($_POST['trip_id'] ?? 0), 'cancelled']);
                foreach ($assigned as $order) {
                    $stmt = $conn->prepare('SELECT invoice_kurir FROM invoice WHERE penjualan_invoice = ? AND invoice_cabang = 0');
                    $stmt->bind_param('s', $order['numart_invoice']);
                    $stmt->execute();
                    $invoice = $stmt->get_result()->fetch_assoc();
                    \Nugrosir\Shipping::check($invoice && (int) $invoice['invoice_kurir'] === $courier, 'Kurir pembayaran harus sama dengan kurir semua invoice perjalanan.');
                }
            }
            $actual = ['km' => $number($_POST['km'] ?? '', 0, 3000), 'minutes' => (int) $number($_POST['minutes'] ?? '', 0, 1440),
                'expenses' => (int) $number($_POST['expenses'] ?? 0, 0, 10000000), 'note' => trim((string) ($_POST['note'] ?? '')), 'no_work' => $noWork];
            $engine->settle((int) ($_POST['trip_id'] ?? 0), 0, $courier, $actual, $actor);
            $notice = 'Perjalanan ditutup. Hak kurir tercatat sekali; alokasi biaya direkonsiliasi.';
        }
    }
    $campaign = $engine->campaign();
    $policy = json_decode($campaign['policy_json'], true);
    $store = json_decode($campaign['store_json'] ?? '{}', true);
    $budgets = $engine->budgets();
    $requests = $engine->rows("SELECT * FROM shipping_quotes WHERE branch_id = 0 AND status IN ('pending','approved') ORDER BY id DESC LIMIT 100");
    $trips = $engine->rows('SELECT * FROM shipping_trips WHERE branch_id = 0 ORDER BY id DESC LIMIT 100');
    $report = $engine->rows('SELECT q.*, o.order_number, o.status AS order_status FROM shipping_quotes q LEFT JOIN orders o ON o.id = q.order_id WHERE q.branch_id = 0 ORDER BY q.id DESC LIMIT 200');
    $couriers = array_filter(marketplace_fetch_kurir_users($conn, 0), fn ($r) => (int) $r['user_cabang'] === 0);
} catch (\DomainException $e) {
    $error = $e->getMessage();
} catch (\Throwable $e) {
    error_log('shipping pilot: '.$e->getMessage());
    $error = 'Data ongkir belum siap atau gagal disimpan. Pastikan migrasi shipping_pilot sudah dijalankan di database belanja.';
}
?>
<div class="content-wrapper"><section class="content-header"><h1>Ongkir Nugrosir Muntilan</h1>
<a href="marketplace-pesanan">Kembali ke pesanan online</a></section><section class="content p-3">
<?php if ($error): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif ?>
<?php if ($notice): ?><div class="alert alert-success"><?= $h($notice) ?></div><?php endif ?>
<?php if (!isset($requests)): ?><p><a href="marketplace-ongkir">Muat ulang halaman</a></p><?php else: ?>
<div class="card"><div class="card-body">
<h2 class="h5">Pilot 30 hari — <?= $campaign['enabled'] ? 'Aktif' : 'Belum aktif / dijeda' ?></h2>
<p>Subsidi terpakai/ditahan <?= $rupiah($budgets['promo']) ?> / <?= $rupiah($campaign['promo_limit']) ?>.
Cadangan selisih terpakai/ditahan <?= $rupiah($budgets['fallback']) ?> / <?= $rupiah($campaign['fallback_limit']) ?>.
Berakhir (UTC): <?= $h($campaign['ends_at'] ?? 'Belum dimulai') ?>.</p>
<p>Default tarif adalah simulasi. Verifikasi titik toko, kategori QRIS, biaya motor, dan kesepakatan mitra sebelum aktivasi. Tarif tersimpan tidak mengubah penawaran lama.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="action" value="activate">
<div class="row">
<?php foreach (['store_address' => ['Alamat toko', $store['address'] ?? ''], 'lat' => ['Latitude toko', $store['lat'] ?? ''], 'lng' => ['Longitude toko', $store['lng'] ?? '']] as $key => [$label, $value]): ?>
<div class="col-md-4 form-group"><label><?= $h($label) ?><input class="form-control" name="<?= $key ?>" value="<?= $h($value) ?>" required></label></div>
<?php endforeach ?>
<?php foreach (['vehicle_per_km' => 'Biaya kendaraan/km', 'per_minute' => 'Imbalan/menit', 'per_order' => 'Perlengkapan/pesanan', 'minimum' => 'Minimum perjalanan', 'handling' => 'Persiapan pesanan', 'payment_fixed' => 'Biaya integrasi pembayaran'] as $key => $label): ?>
<div class="col-md-4 form-group"><label><?= $label ?><input class="form-control" type="number" min="0" max="1000000" name="<?= $key ?>" value="<?= $h($policy[$key]) ?>" required></label></div>
<?php endforeach ?>
</div><label>Kategori QRIS <select name="payment_category"><option value="regular" <?= $policy['payment_category'] === 'regular' ? 'selected' : '' ?>>Non-UMI</option><option value="umi" <?= $policy['payment_category'] === 'umi' ? 'selected' : '' ?>>UMI</option></select></label><br>
<label><input type="checkbox" name="verified" required> Titik toko, biaya dan kategori pembayaran sudah diverifikasi; mitra menyetujui tarif.</label><br>
<button class="btn btn-primary">Simpan dan aktifkan</button></form>
<form method="post" class="mt-2"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><button class="btn btn-outline-danger" name="action" value="pause">Jeda permintaan baru</button></form>
</div></div>
<div class="card"><div class="card-body"><h2 class="h5">Permintaan ongkir — verifikasi manual</h2>
<form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="action" value="approve">
<?php foreach ($requests as $q): $r = json_decode($q['request_json'], true); ?>
<fieldset class="border p-3 mb-3"><legend class="h6">
<?php if ($q['status'] === 'pending'): ?><input type="checkbox" name="ids[]" value="<?= (int) $q['id'] ?>"><?php endif ?>
#<?= (int) $q['id'] ?> — <?= $h($q['status']) ?> — <?= $h($r['name']) ?></legend>
<p><?= $h($r['address']) ?> · <?= $h($r['phone']) ?> · Pilihan <?= $h($r['service']) ?> · <?= $rupiah($r['basket']['subtotal']) ?></p>
<ul><?php foreach ($r['basket']['lines'] as $line): ?><li><?= $h($line['name']) ?> × <?= (int) $line['qty'] ?> (isi <?= (int) $line['conversion'] ?>)</li><?php endforeach ?></ul>
<?php if ($q['status'] === 'pending'): ?><div class="row">
<?php foreach (['km' => 'Jarak jalan satu arah (km)', 'kg' => 'Berat pesanan (kg)', 'lat' => 'Latitude penerima', 'lng' => 'Longitude penerima'] as $key => $label): ?>
<div class="col-md-3"><label><?= $label ?><input class="form-control" name="verified[<?= (int) $q['id'] ?>][<?= $key ?>]" type="number" step="0.000001"></label></div>
<?php endforeach ?></div><?php else: $s = json_decode($q['snapshot_json'], true); ?><p>Ongkir <?= $rupiah($s['money']['customer_fee']) ?> · Subsidi <?= $rupiah($q['subsidy']) ?> · Batas persetujuan (UTC) <?= $h($q['expires_at']) ?></p><?php endif ?>
</fieldset><?php endforeach ?>
<label>Layanan <select name="service"><option value="direct">Langsung</option><option value="scheduled">Hemat gabungan 2–3 pesanan</option><option value="manual">Khusus (berat/di luar area)</option></select></label>
<div class="row">
<?php foreach (['km' => 'Total km termasuk ambil/kembali', 'minutes' => 'Total menit kerja', 'manual_pay' => 'Penawaran kendaraan khusus (Rp)'] as $key => $label): ?>
<div class="col-md-4"><label><?= $label ?><input class="form-control" name="<?= $key ?>" type="number" min="0" step="0.01" value="0" required></label></div>
<?php endforeach ?>
<div class="col-md-6"><label>Awal estimasi diterima (WIB)<input class="form-control" type="datetime-local" name="start" required></label></div>
<div class="col-md-6"><label>Akhir estimasi diterima (WIB)<input class="form-control" type="datetime-local" name="end" required></label></div>
</div><label class="d-block">Catatan verifikasi rute, kendaraan, dimensi dan kesiapan kurir<textarea class="form-control" name="note" required></textarea></label>
<label><input type="checkbox" name="route_verified" required> Rute jalan dan kapasitas kendaraan/jadwal telah diperiksa. Total muatan standar maksimal 10 kg.</label><br>
<button class="btn btn-primary">Hitung, cadangkan anggaran dan terbitkan penawaran</button></form>
<form method="post" class="mt-3"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="action" value="cancel_quote"><label>ID penawaran belum diterima <input type="number" name="quote_id" min="1" required></label> <button class="btn btn-outline-danger">Batalkan penawaran</button></form>
</div></div>
<div class="card"><div class="card-body"><h2 class="h5">Perjalanan dan pembayaran kurir</h2>
<p>Hak kurir dicatat sekali per perjalanan, terpisah dari ongkir invoice. Penyelesaian ulang ditolak. Biaya tambahan yang sudah menjadi hak kurir tetap dicatat walaupun cadangan terlampaui.</p>
<?php foreach ($trips as $t): $s = json_decode($t['snapshot_json'], true); ?>
<details class="border p-3 mb-2"><summary>#<?= (int) $t['id'] ?> · <?= $h($t['status']) ?> · <?= $h($s['route']['service']) ?> · Disepakati <?= $rupiah($t['agreed_pay']) ?> · Aktual <?= $t['actual_pay'] === null ? 'Belum ditutup' : $rupiah($t['actual_pay']) ?></summary>
<p>Rute <?= $h($s['route']['km']) ?> km / <?= $h($s['route']['minutes']) ?> menit · <?= $h($s['weight_kg']) ?> kg · <?= $h($s['route']['note']) ?></p>
<?php if ($t['status'] === 'settled'): $a = json_decode($t['actual_json'], true); $n = count($engine->rows('SELECT id FROM shipping_quotes WHERE trip_id = ?', [$t['id']])); $vehicleCost = (int) ceil($a['km'] * $s['policy']['vehicle_per_km'] + $n * $s['policy']['per_order']); $net = $t['actual_pay'] - $vehicleCost - $a['expenses']; ?>
<p>Kurir #<?= (int) $t['courier_id'] ?> · Biaya kendaraan/perlengkapan <?= $rupiah($vehicleCost) ?> · Penggantian pengeluaran <?= $rupiah($a['expenses']) ?> · Imbalan setelah biaya <?= $rupiah($net) ?> · Per jam tercatat <?= $a['minutes'] > 0 ? $rupiah(round($net * 60 / $a['minutes'])) : 'Durasi belum tersedia' ?>.<br>Selisih ditanggung toko <?= $rupiah($t['fallback_spent']) ?>. <?= $h($a['note']) ?></p>
<?php endif ?>
<?php $tripQuotes = $engine->rows('SELECT q.id, q.status, o.order_number, o.tracking_status FROM shipping_quotes q LEFT JOIN orders o ON o.id = q.order_id WHERE q.trip_id = ?', [$t['id']]); ?>
<ul><?php foreach ($tripQuotes as $q): ?><li>Penawaran #<?= (int) $q['id'] ?> · <?= $h($q['status']) ?> · <?= $h($q['order_number'] ?? 'Belum checkout') ?> · <?= $h($q['tracking_status'] ?? '') ?></li><?php endforeach ?></ul>
<?php if ($t['status'] === 'planned'): ?><form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="action" value="settle"><input type="hidden" name="trip_id" value="<?= (int) $t['id'] ?>">
<label>Kurir <select name="courier"><option value="0">Pilih kurir</option><?php foreach ($couriers as $courier): ?><option value="<?= (int) $courier['user_id'] ?>"><?= $h($courier['user_nama']) ?></option><?php endforeach ?></select></label>
<div class="row"><?php foreach (['km' => 'Km aktual', 'minutes' => 'Menit aktual termasuk tunggu', 'expenses' => 'Parkir/pengeluaran terbukti (Rp)'] as $key => $label): ?><div class="col-md-4"><label><?= $label ?><input class="form-control" name="<?= $key ?>" type="number" min="0" step="0.01" required></label></div><?php endforeach ?></div>
<label class="d-block">Bukti/hasil pekerjaan dan biaya<textarea name="note" class="form-control" required></textarea></label>
<label><input type="checkbox" name="no_work"> Tidak ada pelanggan menerima penawaran dan belum ada pekerjaan/biaya (batalkan perjalanan)</label><br><button class="btn btn-success">Tutup dan catat hak kurir</button></form><?php endif ?>
</details><?php endforeach ?>
</div></div>
<div class="card"><div class="card-body"><h2 class="h5">Kontribusi per pesanan (200 penawaran terbaru)</h2><p>Estimasi kontribusi belum laba bersih. Baris tanpa HPP tidak boleh dipakai untuk mengklaim keuntungan.</p>
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Penawaran / pesanan</th><th>Status</th><th>Ongkir pembeli</th><th>Subsidi</th><th>Hak kurir awal / aktual</th><th>Kontribusi estimasi / aktual*</th></tr></thead><tbody>
<?php foreach ($report as $q): if (!$q['snapshot_json']) continue; $m = json_decode($q['snapshot_json'], true)['money']; $actualContribution = $m['contribution'] === null || $q['actual_allocation'] === null || $q['order_status'] === 'cancelled' ? null : $m['contribution'] + $m['courier_allocation'] - $q['actual_allocation']; ?>
<tr><td>#<?= (int) $q['id'] ?> <?= $h($q['order_number'] ?? '') ?></td><td><?= $h($q['order_status'] ?? $q['status']) ?></td><td><?= $rupiah($m['customer_fee']) ?></td><td><?= $rupiah($m['subsidy']) ?></td><td><?= $rupiah($m['courier_allocation']) ?> / <?= $q['actual_allocation'] === null ? 'Belum tutup' : $rupiah($q['actual_allocation']) ?></td><td><?= $rupiah($m['contribution']) ?> / <?= $q['status'] !== 'consumed' ? 'Bukan penjualan' : ($actualContribution === null ? 'Belum lengkap' : $rupiah($actualContribution)) ?></td></tr>
<?php endforeach ?></tbody></table></div><p>*Aktual berarti pembayaran perjalanan sudah ditutup; HPP dan biaya persiapan tetap berdasarkan snapshot. Retur/pembatalan perlu rekonsiliasi keuangan tersendiri. Alokasi perjalanan atas penawaran yang tidak menjadi pesanan tetap tampil sebagai biaya perjalanan, bukan laba penjualan.</p>
</div></div>
<?php endif ?></section></div>
<?php include '_footer.php'; ?>
