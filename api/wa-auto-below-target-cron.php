<?php
/**
 * Cron WA blast otomatis (satu nomor per panggilan, antrian multi-hari).
 *
 * Jadwal server: panggil setiap 2–3 menit (bukan sekali sehari).
 * Per jam: 20–30 kontak (acak), dikirim satu per satu dengan jeda detik acak.
 * Satu nomor maks. 1x per bulan; tidak boleh duplikat dalam 2–3 hari (default 3).
 * Setelah tanggal mulai bulan ini, pengiriman lanjut tiap hari sampai antrian habis.
 *
 * Contoh:
 *   curl "https://domain/numart/api/wa-auto-blast-cron.php?key=KUNCI"
 * Uji: &dry_run=1
 */

declare(strict_types=1);

@set_time_limit(0);

$root = dirname(__DIR__);
require_once $root . '/aksi/functions.php';
require_once __DIR__ . '/wa-auto-blast-lib.php';

global $conn;

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

$report = [
    'ok' => true,
    'timezone' => date_default_timezone_get(),
    'now' => date('Y-m-d H:i:s'),
    'period' => date('Y-m'),
    'dry_run' => $dryRun,
    'hint' => 'Panggil cron ini setiap 2–3 menit. Satu perangkat Fonnte: maks. 1 WA per panggilan, bergiliran antar cabang aktif.',
    'cabang_results' => [],
];

foreach (wa_auto_blast_cron_run($conn, $dryRun) as $tick) {
    if (!empty($tick['error'])) {
        $report['ok'] = false;
    }
    $report['cabang_results'][] = $tick;
}

$json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if (PHP_SAPI === 'cli') {
    echo $json . "\n";
} else {
    echo $json;
}
