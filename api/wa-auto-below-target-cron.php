<?php
/**
 * Cron / endpoint: kirim WA (Fonnte) otomatis ke customer yang belum capai target bulanan.
 *
 * Jadwal: per cabang, hari kalender (1-28) diatur di Pengaturan Target Customer.
 * Maksimal sekali per customer per periode bulan (YYYY-MM).
 *
 * Pemanggilan (contoh, ganti KUNCI):
 *   curl "https://domain-anda/numart/api/wa-auto-below-target-cron.php?key=KUNCI"
 * Windows Task Scheduler: Program curl dengan argumen di atas (setiap hari jam 08:00).
 * Atau CLI: php wa-auto-below-target-cron.php --key=KUNCI
 *
 * Opsional: buat berkas api/wa-cron-key.php berisi hanya: <?php return 'kunci-rahasia-panjang';
 * Jika berkas itu ada, nilai return dipakai sebagai kunci (mengganti default di bawah).
 *
 * Uji tanpa kirim: tambah &dry_run=1
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/aksi/functions.php';
require_once __DIR__ . '/wa-send-lib.php';
require_once __DIR__ . '/wa-auto-schema.php';
require_once __DIR__ . '/wa-send-settings-lib.php';

global $conn;
wa_auto_below_target_ensure_schema($conn);

$cronKey = 'y9Db7kVX2rAe4Z!8wjPpQ^tFLGumYSx6';
$keyFile = __DIR__ . DIRECTORY_SEPARATOR . 'wa-cron-key.php';
if (is_readable($keyFile)) {
    $loaded = include $keyFile;
    if (is_string($loaded) && trim($loaded) !== '') {
        $cronKey = trim($loaded);
    }
}

$key = '';
if (PHP_SAPI === 'cli') {
    foreach ($_SERVER['argv'] ?? [] as $i => $arg) {
        if ($arg === '--key' && isset($_SERVER['argv'][$i + 1])) {
            $key = (string) $_SERVER['argv'][$i + 1];
            break;
        }
    }
} else {
    $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
}

if ($key === '' || !hash_equals($cronKey, $key)) {
    if (PHP_SAPI !== 'cli') {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Forbidden';
    exit(1);
}

$dryRun = false;
if (PHP_SAPI !== 'cli') {
    $dryRun = isset($_GET['dry_run']) && (string) $_GET['dry_run'] === '1';
    header('Content-Type: application/json; charset=utf-8');
}

$todayDom = (int) date('j');
$period = date('Y-m');
$tzNote = date_default_timezone_get();

$report = [
    'ok' => true,
    'timezone' => $tzNote,
    'today' => date('Y-m-d'),
    'day_of_month' => $todayDom,
    'period' => $period,
    'dry_run' => $dryRun,
    'cabang_results' => [],
];

$remRows = query("SELECT * FROM wa_auto_target_reminder_settings WHERE enabled = 1");
foreach ($remRows as $rem) {
    $cabang = (int) $rem['cabang'];
    $sendDay = (int) $rem['send_day'];
    if ($sendDay < 1) {
        $sendDay = 26;
    }
    if ($sendDay > 28) {
        $sendDay = 28;
    }

    $sendLimits = wa_send_settings_get($conn, $cabang);

    $cabReport = [
        'cabang' => $cabang,
        'send_day_setting' => $sendDay,
        'skipped_not_scheduled_day' => false,
        'skipped_interval' => false,
        'target_bulanan' => null,
        'below_target_count' => 0,
        'pending_count' => 0,
        'batch_size' => 0,
        'queued_remaining' => 0,
        'max_contacts_per_batch' => (int) $sendLimits['max_contacts_per_batch'],
        'min_interval_minutes' => (int) $sendLimits['min_interval_minutes'],
        'new_recipients' => 0,
        'fonnte' => null,
        'error' => null,
    ];

    if (!$dryRun && $todayDom !== $sendDay) {
        $cabReport['skipped_not_scheduled_day'] = true;
        $report['cabang_results'][] = $cabReport;
        continue;
    }

    $targetRows = query("SELECT * FROM customer_target_settings WHERE cabang = $cabang");
    if (empty($targetRows)) {
        $targetRows = query("SELECT * FROM customer_target_settings WHERE cabang = 0");
    }
    $targetBulan = 100000;
    if (!empty($targetRows)) {
        $targetBulan = (float) ($targetRows[0]['target_bulanan'] ?? 100000);
    }
    $cabReport['target_bulanan'] = $targetBulan;

    $startOfMonth = date('Y-m-01');
    $endOfMonth = date('Y-m-t');

    $tokoRows = query("SELECT toko_nama FROM toko WHERE toko_cabang = $cabang LIMIT 1");
    $tokoNama = (!empty($tokoRows) && !empty($tokoRows[0]['toko_nama'])) ? $tokoRows[0]['toko_nama'] : 'Toko';

    $tplDefault = "Halo {nama_customer},\n\n"
        . "Total belanja Anda bulan ini {total_belanja} belum mencapai target minimum {target} di {nama_toko}. "
        . "Masih kurang {kurang}.\n\n"
        . "Silakan berkunjung kembali. Terima kasih.";

    $tpl = trim((string) ($rem['message_template'] ?? ''));
    if ($tpl === '') {
        $tpl = $tplDefault;
    }

    $q = "SELECT 
            c.customer_id,
            c.customer_nama,
            c.customer_tlpn,
            COALESCE(SUM(i.invoice_sub_total), 0) AS total_belanja
          FROM customer c
          LEFT JOIN invoice i ON c.customer_id = i.invoice_customer 
            AND i.invoice_date BETWEEN '$startOfMonth' AND '$endOfMonth'
            AND i.invoice_cabang = $cabang
          WHERE c.customer_cabang = $cabang
            AND c.customer_id > 1
            AND c.customer_nama != 'Customer Umum'
            AND c.customer_status = '1'
            AND c.customer_tlpn IS NOT NULL
            AND TRIM(c.customer_tlpn) != ''
          GROUP BY c.customer_id
          HAVING total_belanja < $targetBulan
          ORDER BY total_belanja ASC";

    $customers = query($q);
    $cabReport['below_target_count'] = count($customers);

    $periodEsc = mysqli_real_escape_string($conn, $period);
    $pending = [];
    foreach ($customers as $c) {
        $cid = (int) $c['customer_id'];
        $sent = query("SELECT id FROM wa_auto_below_target_sent WHERE cabang = $cabang AND customer_id = $cid AND period_yyyymm = '$periodEsc' LIMIT 1");
        if (empty($sent)) {
            $pending[] = $c;
        }
    }
    $cabReport['pending_count'] = count($pending);

    $maxBatch = (int) $sendLimits['max_contacts_per_batch'];
    $batch = array_slice($pending, 0, $maxBatch);
    $cabReport['batch_size'] = count($batch);
    $cabReport['queued_remaining'] = max(0, count($pending) - count($batch));

    if (!$dryRun) {
        $intervalCheck = wa_send_settings_check_interval($conn, $cabang);
        if (!$intervalCheck['allowed']) {
            $cabReport['skipped_interval'] = true;
            $cabReport['error'] = $intervalCheck['message'];
            $cabReport['wait_minutes'] = $intervalCheck['wait_minutes'];
            $report['cabang_results'][] = $cabReport;
            continue;
        }
    }

    if ($dryRun) {
        $cabReport['new_recipients'] = count($batch);
        $cabReport['sample'] = array_slice(array_map(static function ($r) {
            return [
                'customer_id' => (int) $r['customer_id'],
                'customer_nama' => $r['customer_nama'],
            ];
        }, $batch), 0, 15);
        $report['cabang_results'][] = $cabReport;
        continue;
    }

    $newRows = [];
    foreach ($batch as $c) {
        $cid = (int) $c['customer_id'];
        mysqli_query(
            $conn,
            "INSERT IGNORE INTO wa_auto_below_target_sent (cabang, customer_id, period_yyyymm) VALUES ($cabang, $cid, '$periodEsc')"
        );
        if (mysqli_affected_rows($conn) > 0) {
            $newRows[] = $c;
        }
    }

    $cabReport['new_recipients'] = count($newRows);

    $built = [];
    foreach ($newRows as $c) {
        $phone = fonnte_normalize_id_phone((string) $c['customer_tlpn']);
        if ($phone === '') {
            continue;
        }
        $total = (float) $c['total_belanja'];
        $kurang = max(0, $targetBulan - $total);
        $msg = str_replace(
            ['{nama_customer}', '{total_belanja}', '{nama_toko}', '{target}', '{kurang}'],
            [
                $c['customer_nama'],
                'Rp ' . number_format($total, 0, ',', '.'),
                $tokoNama,
                'Rp ' . number_format($targetBulan, 0, ',', '.'),
                'Rp ' . number_format($kurang, 0, ',', '.'),
            ],
            $tpl
        );
        $built[] = ['target' => $phone, 'message' => $msg];
    }

    if ($built === []) {
        $report['cabang_results'][] = $cabReport;
        continue;
    }

    $delaySec = (string) ((int) ($sendLimits['delay_seconds_per_contact'] ?? 3));
    $sendResult = wa_send_built($built, $delaySec);
    $cabReport['fonnte'] = [
        'success' => $sendResult['success'],
        'sent_attempts' => $sendResult['sent_attempts'],
        'message' => $sendResult['message'],
    ];

    if ($sendResult['success']) {
        wa_send_settings_touch_last_send($conn, $cabang);
        if ($cabReport['queued_remaining'] > 0) {
            $cabReport['note'] = 'Masih ada ' . $cabReport['queued_remaining'] . ' customer antrian; jalankan cron lagi setelah jeda ' . (int) $sendLimits['min_interval_minutes'] . ' menit.';
        }
    } else {
        foreach ($newRows as $c) {
            $cid = (int) $c['customer_id'];
            mysqli_query(
                $conn,
                "DELETE FROM wa_auto_below_target_sent WHERE cabang = $cabang AND customer_id = $cid AND period_yyyymm = '$periodEsc'"
            );
        }
        $cabReport['error'] = 'Pengiriman WA gagal; log periode di-rollback agar bisa dicoba lagi.';
        $report['ok'] = false;
    }

    $report['cabang_results'][] = $cabReport;
}

$json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if (PHP_SAPI === 'cli') {
    echo $json . "\n";
} else {
    echo $json;
}
