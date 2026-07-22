<?php
/**
 * Export database SQL dari server live (localhost MySQL di Hostinger).
 * Upload ke production: api/sync-db-export-live.php + api/sync-db-export.config.php
 */
declare(strict_types=1);

$configPath = __DIR__ . DIRECTORY_SEPARATOR . 'sync-db-export.config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'sync-db-export.config.php belum ada. Salin dari sync-db-export.config.example.php';
    exit;
}

$cfg = include $configPath;
if (!is_array($cfg)) {
    http_response_code(500);
    echo 'Config invalid';
    exit;
}

$secret = trim((string) ($cfg['secret'] ?? ''));
$key = trim((string) ($_GET['key'] ?? $_SERVER['HTTP_X_SYNC_KEY'] ?? ''));
if ($secret === '' || !hash_equals($secret, $key)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

$allowedIps = $cfg['allowed_ips'] ?? [];
if (is_array($allowedIps) && $allowedIps !== []) {
    $clientIp = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (!in_array($clientIp, $allowedIps, true)) {
        http_response_code(403);
        echo 'IP not allowed: ' . $clientIp;
        exit;
    }
}

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'aksi' . DIRECTORY_SEPARATOR . 'koneksi.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'Database connection failed';
    exit;
}

mysqli_query($conn, 'SET NAMES utf8mb4');

$res = mysqli_query($conn, 'SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
$tables = [];
if ($res) {
    while ($row = mysqli_fetch_array($res)) {
        $tables[] = (string) $row[0];
    }
}
sort($tables, SORT_STRING);

if (isset($_GET['ping'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'message' => 'Export endpoint siap',
        'table_count' => count($tables),
        'tables' => $tables,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

@set_time_limit(0);
@ini_set('memory_limit', '768M');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
    ob_end_flush();
}

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="numart-live-' . date('Ymd-His') . '.sql"');
header('Cache-Control: no-store');
header('X-Accel-Buffering: no');

$out = fopen('php://output', 'wb');
if ($out === false) {
    http_response_code(500);
    echo 'Output stream failed';
    exit;
}

$collationMap = [
    'utf8mb4_uca1400_ai_ci' => 'utf8mb4_unicode_ci',
    'utf8mb4_uca1400_as_ci' => 'utf8mb4_unicode_ci',
    'utf8mb4_uca1400_as_cs' => 'utf8mb4_unicode_ci',
    'utf8mb4_0900_ai_ci' => 'utf8mb4_unicode_ci',
    'utf8mb4_0900_as_ci' => 'utf8mb4_unicode_ci',
    'utf8mb4_0900_as_cs' => 'utf8mb4_unicode_ci',
    'utf8mb4_0900_bin' => 'utf8mb4_bin',
];

$sanitizeDdl = static function (string $sql) use ($collationMap): string {
    return str_replace(array_keys($collationMap), array_values($collationMap), $sql);
};

$flushOut = static function () use ($out): void {
    fflush($out);
    if (function_exists('flush')) {
        flush();
    }
};

$tableCount = count($tables);
fwrite($out, "-- NUMART live DB export " . date('c') . "\n");
fwrite($out, '-- NUMART_TABLE_COUNT: ' . $tableCount . "\n");
fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

/** Fase 1: semua struktur dulu — jika timeout saat data, minimal 60 tabel tetap kebentuk */
fwrite($out, "-- === PHASE 1: STRUCTURE ===\n\n");
foreach ($tables as $table) {
    $tEsc = mysqli_real_escape_string($conn, $table);
    $createRes = mysqli_query($conn, "SHOW CREATE TABLE `$tEsc`");
    $createRow = $createRes ? mysqli_fetch_assoc($createRes) : null;
    if (!$createRow || empty($createRow['Create Table'])) {
        continue;
    }

    fwrite($out, "-- TABLE_STRUCT: `$table`\n");
    fwrite($out, 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . "`;\n");
    fwrite($out, $sanitizeDdl($createRow['Create Table']) . ";\n\n");
    $flushOut();
}

fwrite($out, "-- === PHASE 2: DATA ===\n\n");

foreach ($tables as $table) {
    $tEsc = mysqli_real_escape_string($conn, $table);
    fwrite($out, "-- TABLE_DATA: `$table`\n");

    $dataRes = mysqli_query($conn, "SELECT * FROM `$tEsc`");
    if (!$dataRes) {
        fwrite($out, "\n");
        $flushOut();
        continue;
    }

    $cols = null;
    $batch = [];
    $batchSize = 80;

    while ($dataRow = mysqli_fetch_assoc($dataRes)) {
        if ($cols === null) {
            $cols = [];
            foreach (array_keys($dataRow) as $col) {
                $cols[] = '`' . str_replace('`', '``', $col) . '`';
            }
        }
        $vals = [];
        foreach ($dataRow as $val) {
            if ($val === null) {
                $vals[] = 'NULL';
            } else {
                $vals[] = "'" . mysqli_real_escape_string($conn, (string) $val) . "'";
            }
        }
        $batch[] = '(' . implode(',', $vals) . ')';

        if (count($batch) >= $batchSize) {
            fwrite(
                $out,
                'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(',', $cols) . ') VALUES '
                . implode(',', $batch) . ";\n"
            );
            $batch = [];
        }
    }

    if ($batch !== [] && $cols !== null) {
        fwrite(
            $out,
            'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(',', $cols) . ') VALUES '
            . implode(',', $batch) . ";\n"
        );
    }
    fwrite($out, "\n");
    $flushOut();
}

fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
fwrite($out, '-- NUMART_EXPORT_END tables=' . $tableCount . "\n");
fclose($out);
