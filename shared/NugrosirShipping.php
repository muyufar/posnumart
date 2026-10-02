<?php

namespace Nugrosir;

use PDO;
use DomainException;

/** Shared by POS and Laravel. Money is integer rupiah; dates are UTC. */
final class Shipping
{
    public function __construct(public PDO $db) {}

    public static function policy(): array
    {
        return ['version' => 'muntilan-2026-10-v1', 'vehicle_per_km' => 650,
            'per_minute' => 250, 'per_order' => 500, 'minimum' => 8000,
            'handling' => 2500, 'risk_bps' => 50, 'min_contribution' => 3000,
            'promo_minimum' => 100000, 'promo_cap' => 2000,
            'payment_category' => 'regular', 'payment_fixed' => 0,
            'max_km' => 5, 'max_kg' => 10];
    }

    public static function payout(float $km, int $minutes, int $orders, array $p): int
    {
        self::check(is_finite($km) && $km >= 0 && $minutes >= 0 && $orders > 0, 'Data perjalanan tidak valid.');
        return (int) (ceil(max($p['minimum'], $km * $p['vehicle_per_km'] + $minutes * $p['per_minute'] + $orders * $p['per_order']) / 1000) * 1000);
    }

    public static function split(int $total, int $count): array
    {
        self::check($total >= 0 && $count > 0, 'Alokasi tidak valid.');
        return array_map(fn ($i) => intdiv($total, $count) + ($i < $total % $count ? 1 : 0), range(0, $count - 1));
    }

    public static function payment(int $total, string $method, array $p): int
    {
        if ($method === 'cod') return 0;
        $threshold = $p['payment_category'] === 'umi' ? 500000 : 100000;
        $bps = $p['payment_category'] === 'umi' ? 30 : 70;
        return (int) ceil(($total > $threshold ? $total * $bps / 10000 : 0) + $p['payment_fixed']);
    }

    public static function money(array $request, int $fare, array $p, int $budget): array
    {
        $goods = $request['basket'];
        $subtotal = (int) $goods['subtotal'];
        $gross = $subtotal - (int) $goods['cost'];
        $operating = $p['handling'] + (int) ceil($subtotal * $p['risk_bps'] / 10000);
        $cap = $goods['cost_known'] && $subtotal >= $p['promo_minimum'] ? min($fare, $budget, $p['promo_cap']) : 0;
        // Exhaustive integer search also handles the QRIS threshold discontinuity.
        $subsidy = 0;
        for ($candidate = $cap; $candidate > 0; $candidate--) {
            $fee = self::payment($subtotal + $fare - $candidate, $request['payment_method'], $p);
            if ($gross - $operating - $fee - $candidate >= $p['min_contribution']) {
                $subsidy = $candidate;
                break;
            }
        }
        $payment = self::payment($subtotal + $fare - $subsidy, $request['payment_method'], $p);
        return ['fare' => $fare, 'subsidy' => $subsidy, 'customer_fee' => $fare - $subsidy,
            'courier_allocation' => $fare, 'operating' => $operating, 'payment' => $payment,
            'gross' => $goods['cost_known'] ? $gross : null,
            'contribution' => $goods['cost_known'] ? $gross - $operating - $payment - $subsidy : null];
    }

    public static function check(bool $ok, string $message): void
    {
        if (!$ok) throw new DomainException($message);
    }

    public function rows(string $sql, array $args = []): array
    {
        $q = $this->db->prepare($sql);
        $q->execute($args);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function run(string $sql, array $args = []): void
    {
        $q = $this->db->prepare($sql);
        $q->execute($args);
    }

    public function transaction(callable $fn): mixed
    {
        $own = !$this->db->inTransaction();
        if ($own) $this->db->beginTransaction();
        try {
            // Serialize all budget, quote and trip transitions, including checkout.
            $this->run('UPDATE shipping_campaigns SET id = id WHERE id = 1');
            $result = $fn();
            if ($own) $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($own && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function campaign(): array
    {
        return $this->rows('SELECT * FROM shipping_campaigns WHERE id = 1')[0];
    }

    public function active(int $branch): bool
    {
        $c = $this->campaign();
        return (bool) $c['enabled'] && (int) $c['branch_id'] === $branch;
    }

    public function event(string $kind, int $id, string $actor, array $data = []): void
    {
        $this->run('INSERT INTO shipping_events (kind, entity_id, actor, payload, created_at) VALUES (?, ?, ?, ?, ?)',
            [$kind, $id, $actor, json_encode($data, JSON_THROW_ON_ERROR), gmdate('Y-m-d H:i:s')]);
    }

    public function request(int $user, int $branch, array $data): int
    {
        return $this->transaction(function () use ($user, $branch, $data) {
            self::check($this->active($branch), 'Layanan ongkir cabang belum aktif.');
            self::check($this->campaign()['ends_at'] > gmdate('Y-m-d H:i:s'), 'Pilot telah berakhir. Hubungi toko.');
            $this->run("UPDATE shipping_quotes SET status = 'expired' WHERE user_id = ? AND status = 'approved' AND expires_at <= ?", [$user, gmdate('Y-m-d H:i:s')]);
            $existing = $this->rows("SELECT id FROM shipping_quotes WHERE user_id = ? AND fingerprint = ? AND status IN ('pending','approved')", [$user, $data['fingerprint']]);
            if ($existing) return (int) $existing[0]['id'];
            $this->run("INSERT INTO shipping_quotes (user_id, branch_id, fingerprint, status, request_json, created_at) VALUES (?, ?, ?, 'pending', ?, ?)",
                [$user, $branch, $data['fingerprint'], json_encode($data, JSON_THROW_ON_ERROR), gmdate('Y-m-d H:i:s')]);
            $id = (int) $this->db->lastInsertId();
            $this->event('request', $id, 'customer:'.$user);
            return $id;
        });
    }

    public function approve(array $ids, array $route, array $verified, string $actor, int $branch): int
    {
        return $this->transaction(function () use ($ids, $route, $verified, $actor, $branch) {
            $c = $this->campaign();
            self::check($this->active($branch), 'Pilot belum aktif untuk cabang ini.');
            self::check($c['ends_at'] > gmdate('Y-m-d H:i:s'), 'Periode pilot sudah berakhir.');
            $p = json_decode($c['policy_json'], true, 512, JSON_THROW_ON_ERROR);
            $this->run("UPDATE shipping_quotes SET status = 'expired' WHERE status = 'approved' AND expires_at <= ?", [gmdate('Y-m-d H:i:s')]);
            $ids = array_values(array_unique(array_map('intval', $ids)));
            sort($ids);
            $n = count($ids);
            self::check($n >= 1 && $n <= 3, 'Pilih 1–3 permintaan.');
            self::check(in_array($route['service'], ['direct', 'scheduled', 'manual'], true), 'Layanan tidak valid.');
            self::check($route['service'] === 'scheduled' || $n === 1, 'Layanan langsung/manual harus satu pesanan.');
            self::check($route['service'] !== 'scheduled' || $n >= 2, 'Hemat membutuhkan minimal dua pesanan searah.');
            self::check($route['km'] > 0 && $route['minutes'] > 0 && trim($route['note']) !== '', 'Lengkapi rute, waktu dan bukti verifikasi.');
            self::check($route['start'] > gmdate('Y-m-d H:i:s') && $route['end'] > $route['start'], 'Jadwal penerimaan tidak valid.');
            if ($route['service'] === 'scheduled') {
                $local = new \DateTimeImmutable($route['start'], new \DateTimeZone('UTC'));
                $end = new \DateTimeImmutable($route['end'], new \DateTimeZone('UTC'));
                self::check(in_array($local->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('H:i'), ['11:00','16:00'], true)
                    && $end->getTimestamp() - $local->getTimestamp() === 7200, 'Slot hemat hanya 11–13 atau 16–18 WIB.');
            }
            $requests = [];
            $weight = 0;
            $solo = 0;
            $maxDistance = 0;
            foreach ($ids as $id) {
                $q = $this->rows('SELECT * FROM shipping_quotes WHERE id = ?', [$id])[0] ?? null;
                self::check($q && $q['status'] === 'pending' && (int) $q['branch_id'] === $branch, 'Permintaan sudah diproses atau berbeda cabang.');
                $data = json_decode($q['request_json'], true, 512, JSON_THROW_ON_ERROR);
                $v = $verified[$id] ?? [];
                self::check(isset($v['km'], $v['kg'], $v['lat'], $v['lng']) && $v['km'] > 0 && $v['kg'] > 0
                    && abs($v['lat']) <= 90 && abs($v['lng']) <= 180, 'Verifikasi jarak jalan, berat dan koordinat setiap alamat.');
                self::check($route['service'] === 'manual' || ($v['km'] <= $p['max_km'] && $data['service'] === $route['service']), 'Di luar jangkauan/pilihan pelanggan: gunakan penawaran manual.');
                $weight += $v['kg'];
                $maxDistance = max($maxDistance, $v['km']);
                $solo += self::payout($v['km'] * 2, (int) ceil(10 + $v['km'] * 6), 1, $p);
                $requests[$id] = ['request' => $data, 'verified' => $v];
            }
            self::check($route['km'] >= 2 * $maxDistance, 'Total rute termasuk kembali tidak boleh lebih pendek dari dua kali jarak alamat terjauh.');
            self::check($route['minutes'] >= (int) ceil($route['km'] * 3) + 10, 'Waktu rute harus mencakup perjalanan 20 km/jam dan minimal 10 menit penanganan.');
            self::check($route['service'] === 'manual' || $weight <= $p['max_kg'], 'Muatan gabungan melebihi 10 kg: gunakan penawaran manual kendaraan sesuai.');
            $pay = self::payout($route['km'], $route['minutes'], $n, $p);
            if ($route['service'] === 'manual') {
                self::check($route['manual_pay'] >= $pay, 'Penawaran manual tidak boleh di bawah biaya dasar.');
                $pay = $route['manual_pay'];
            }
            // Reserve for an unfilled batch too, not only the expected extra distance.
            $fallback = $route['service'] === 'scheduled' ? max($pay, $solo - $pay) : 0;
            $budgets = $this->budgets();
            self::check($budgets['fallback'] + $fallback <= $c['fallback_limit'], 'Cadangan selisih habis. Jangan menerima rute hemat baru ini.');
            $allocations = self::split($pay, $n);
            $promo = $budgets['promo'];
            foreach (array_keys($requests) as $i => $id) {
                $m = self::money($requests[$id]['request'], $allocations[$i], $p, max(0, $c['promo_limit'] - $promo));
                $requests[$id]['money'] = $m;
                $promo += $m['subsidy'];
            }
            $snapshot = ['route' => $route, 'policy' => $p, 'weight_kg' => $weight, 'store' => json_decode($c['store_json'], true)];
            $this->run("INSERT INTO shipping_trips (branch_id, status, agreed_pay, fallback_reserve, snapshot_json, created_at) VALUES (?, 'planned', ?, ?, ?, ?)",
                [$branch, $pay, $fallback, json_encode($snapshot, JSON_THROW_ON_ERROR), gmdate('Y-m-d H:i:s')]);
            $trip = (int) $this->db->lastInsertId();
            $expires = min(gmdate('Y-m-d H:i:s', time() + 86400), $route['start']);
            foreach ($requests as $id => $r) {
                $s = $snapshot + ['verified' => $r['verified'], 'money' => $r['money']];
                $this->run("UPDATE shipping_quotes SET status = 'approved', trip_id = ?, snapshot_json = ?, subsidy = ?, expires_at = ? WHERE id = ?",
                    [$trip, json_encode($s, JSON_THROW_ON_ERROR), $r['money']['subsidy'], $expires, $id]);
            }
            $this->event('approve_trip', $trip, $actor, ['quotes' => $ids]);
            return $trip;
        });
    }

    public function budgets(): array
    {
        $promo = (int) $this->rows("SELECT COALESCE(SUM(subsidy), 0) AS n FROM shipping_quotes WHERE status IN ('approved','consumed')")[0]['n'];
        $fallback = (int) $this->rows("SELECT COALESCE(SUM(CASE WHEN status = 'settled' THEN fallback_spent ELSE fallback_reserve END), 0) AS n FROM shipping_trips WHERE status <> 'void'")[0]['n'];
        return ['promo' => $promo, 'fallback' => $fallback];
    }

    /** Must be called inside the same database transaction as order creation. */
    public function consume(int $id, int $user, int $branch, string $fingerprint): array
    {
        self::check($this->db->inTransaction(), 'Checkout membutuhkan transaksi database.');
        $this->run('UPDATE shipping_campaigns SET id = id WHERE id = 1');
        $q = $this->rows('SELECT * FROM shipping_quotes WHERE id = ?', [$id])[0] ?? null;
        self::check($q && (int) $q['user_id'] === $user && (int) $q['branch_id'] === $branch
            && $q['status'] === 'approved' && $q['expires_at'] > gmdate('Y-m-d H:i:s')
            && hash_equals($q['fingerprint'], $fingerprint), 'Penawaran tidak berlaku untuk alamat, pembayaran atau keranjang ini. Ajukan ulang ongkir.');
        $this->run("UPDATE shipping_quotes SET status = 'consumed' WHERE id = ?", [$id]);
        $this->event('accept_quote', $id, 'customer:'.$user);
        return json_decode($q['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
    }

    public function cancelQuote(int $id, string $actor, ?int $user = null): void
    {
        $this->transaction(function () use ($id, $actor, $user) {
            $q = $this->rows('SELECT * FROM shipping_quotes WHERE id = ?', [$id])[0] ?? null;
            self::check($q && ($user === null || (int) $q['user_id'] === $user) && in_array($q['status'], ['pending','approved'], true), 'Penawaran tidak dapat dibatalkan.');
            $this->run("UPDATE shipping_quotes SET status = 'cancelled' WHERE id = ?", [$id]);
            // Retain trip reserve: other accepted orders must still be delivered.
            $this->event('cancel_quote', $id, $actor);
        });
    }

    public function settle(int $id, int $branch, int $courier, array $actual, string $actor): void
    {
        $this->transaction(function () use ($id, $branch, $courier, $actual, $actor) {
            $t = $this->rows('SELECT * FROM shipping_trips WHERE id = ?', [$id])[0] ?? null;
            self::check($t && (int) $t['branch_id'] === $branch && $t['status'] === 'planned', 'Perjalanan sudah ditutup atau bukan cabang ini.');
            self::check(trim($actual['note']) !== '', 'Isi bukti pekerjaan/pengeluaran dan alasan penyelesaian.');
            $quotes = $this->rows('SELECT * FROM shipping_quotes WHERE trip_id = ? ORDER BY id', [$id]);
            $accepted = array_filter($quotes, fn ($q) => $q['status'] === 'consumed');
            $s = json_decode($t['snapshot_json'], true);
            $p = $s['policy'];
            if ($actual['no_work']) {
                self::check(!$accepted && $actual['km'] == 0 && $actual['minutes'] == 0 && $actual['expenses'] == 0, 'Batal tanpa pekerjaan hanya untuk perjalanan tanpa pesanan diterima dan biaya nol.');
                $pay = 0;
            } else {
                self::check($courier > 0 && $actual['km'] >= 0 && $actual['minutes'] >= 0 && $actual['expenses'] >= 0, 'Kurir/data aktual tidak valid.');
                foreach ($accepted as $q) {
                    $o = $this->rows('SELECT status, tracking_status FROM orders WHERE id = ?', [$q['order_id']])[0] ?? null;
                    self::check($o && ($o['tracking_status'] === 'delivered' || $o['status'] === 'cancelled'), 'Selesaikan pengiriman atau pembatalan pesanan sebelum tutup perjalanan.');
                }
                $pay = max((int) $t['agreed_pay'], self::payout($actual['km'], $actual['minutes'], count($quotes), $p)) + $actual['expenses'];
            }
            $revenue = 0;
            foreach ($accepted as $q) {
                $o = $this->rows('SELECT status FROM orders WHERE id = ?', [$q['order_id']])[0] ?? null;
                if ($o && $o['status'] !== 'cancelled') $revenue += json_decode($q['snapshot_json'], true)['money']['fare'];
            }
            $spent = max(0, $pay - $revenue);
            $this->run('UPDATE shipping_trips SET status = ?, courier_id = ?, actual_pay = ?, fallback_spent = ?, actual_json = ?, settled_at = ? WHERE id = ?',
                [$actual['no_work'] ? 'void' : 'settled', $courier, $pay, $spent, json_encode($actual, JSON_THROW_ON_ERROR), gmdate('Y-m-d H:i:s'), $id]);
            $alloc = self::split($pay, count($quotes));
            foreach ($quotes as $i => $q) {
                $this->run('UPDATE shipping_quotes SET actual_allocation = ? WHERE id = ?', [$alloc[$i], $q['id']]);
                if (in_array($q['status'], ['approved','pending'], true)) $this->run("UPDATE shipping_quotes SET status = 'expired' WHERE id = ?", [$q['id']]);
            }
            $this->event('settle_trip', $id, $actor, ['pay' => $pay, 'actual' => $actual]);
        });
    }
}
