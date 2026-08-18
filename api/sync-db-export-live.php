<?php
/**
 * Export database SQL dari server live (localhost MySQL di Hostinger).
 *
 * Protokol v2: klien mengambil struktur lalu data per potongan kecil, sehingga
 * satu request tidak pernah menyentuh batas memori/waktu hosting. Mode lama
 * (satu request untuk seluruh database) tetap tersedia sebagai cadangan.
 *
 * Upload ke production: api/sync-db-export-live.php + api/sync-db-export.config.php
 */
declare(strict_types=1);

const NUMART_EXPORT_PROTOCOL = 2;

/** Batas per potongan — dipilih jauh di bawah limit hosting agar tidak pernah terpotong. */
const NUMART_CHUNK_MAX_ROWS = 20000;
const NUMART_CHUNK_MAX_BYTES = 6291456;
const NUMART_CHUNK_MAX_SECONDS = 20.0;

/** Ambang flush batch INSERT. */
const NUMART_BATCH_MAX_ROWS = 400;
const NUMART_BATCH_MAX_BYTES = 524288;

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
    header('Content-Type: text/plain; charset=utf-8');
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
        header('Content-Type: text/plain; charset=utf-8');
        echo 'IP not allowed: ' . $clientIp;
        exit;
    }
}

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'aksi' . DIRECTORY_SEPARATOR . 'koneksi.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
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
    mysqli_free_result($res);
}
sort($tables, SORT_STRING);

if (isset($_GET['ping'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => true,
        'protocol' => NUMART_EXPORT_PROTOCOL,
        'message' => 'Export endpoint siap',
        'table_count' => count($tables),
        'tables' => $tables,
        'chunk_max_rows' => NUMART_CHUNK_MAX_ROWS,
    ], JSON_UNESCAPED_UNICODE);
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

$quoteIdent = static function (string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
};

/**
 * Kompresi streaming lewat zlib: hemat 5-10x bandwidth tanpa menahan output di memori.
 * ob_end_clean() dipanggil sebelum zlib diaktifkan agar handler-nya tidak ikut terbuang.
 */
$beginOutput = static function (): void {
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (extension_loaded('zlib') && !headers_sent()) {
        @ini_set('zlib.output_compression', '1');
        @ini_set('zlib.output_compression_level', '5');
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Accel-Buffering: no');
};

$phase = strtolower(trim((string) ($_GET['phase'] ?? '')));

/** Fase struktur: semua CREATE TABLE dalam satu request kecil. */
if ($phase === 'struct') {
    $beginOutput();

    echo '-- NUMART_PROTOCOL: ' . NUMART_EXPORT_PROTOCOL . "\n";
    echo '-- NUMART_TABLE_COUNT: ' . count($tables) . "\n\n";

    $written = 0;
    foreach ($tables as $table) {
        $createRes = mysqli_query($conn, 'SHOW CREATE TABLE ' . $quoteIdent($table));
        $createRow = $createRes ? mysqli_fetch_assoc($createRes) : null;
        if ($createRes) {
            mysqli_free_result($createRes);
        }
        if (!$createRow || empty($createRow['Create Table'])) {
            continue;
        }

        echo '-- TABLE_STRUCT: ' . $quoteIdent($table) . "\n";
        echo 'DROP TABLE IF EXISTS ' . $quoteIdent($table) . ";\n";
        echo $sanitizeDdl((string) $createRow['Create Table']) . ";\n\n";
        $written++;
    }

    echo '-- NUMART_STRUCT_END count=' . $written . "\n";
    exit;
}

/** Fase data: satu potongan dari satu tabel, dikendalikan cursor buram dari server. */
if ($phase === 'data') {
    $table = (string) ($_GET['table'] ?? '');
    if (!in_array($table, $tables, true)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Tabel tidak dikenal';
        exit;
    }

    $tableQ = $quoteIdent($table);

    $pkCols = [];
    $keysRes = mysqli_query($conn, 'SHOW KEYS FROM ' . $tableQ . " WHERE Key_name = 'PRIMARY'");
    if ($keysRes) {
        while ($k = mysqli_fetch_assoc($keysRes)) {
            $pkCols[(int) $k['Seq_in_index']] = (string) $k['Column_name'];
        }
        mysqli_free_result($keysRes);
    }
    ksort($pkCols);
    $pkCols = array_values($pkCols);

    $columnTypes = [];
    $colsRes = mysqli_query($conn, 'SHOW COLUMNS FROM ' . $tableQ);
    if ($colsRes) {
        while ($c = mysqli_fetch_assoc($colsRes)) {
            $columnTypes[(string) $c['Field']] = strtolower((string) $c['Type']);
        }
        mysqli_free_result($colsRes);
    }

    /**
     * Keyset pagination bila PK tunggal & integer — OFFSET besar makin lambat
     * secara kuadratik pada tabel jutaan baris.
     */
    $keyCol = null;
    if (count($pkCols) === 1) {
        $type = $columnTypes[$pkCols[0]] ?? '';
        if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $type) === 1) {
            $keyCol = $pkCols[0];
        }
    }
    $pagingMode = $keyCol !== null ? 'keyset' : ($pkCols !== [] ? 'offset' : 'scan');

    $cursorRaw = (string) ($_GET['cursor'] ?? '');
    if ($keyCol !== null) {
        if ($cursorRaw !== '' && preg_match('/^-?\d+$/', $cursorRaw) !== 1) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Cursor tidak valid';
            exit;
        }
        $where = $cursorRaw === '' ? '' : ' WHERE ' . $quoteIdent($keyCol) . ' > ' . $cursorRaw;
        $sql = 'SELECT * FROM ' . $tableQ . $where
            . ' ORDER BY ' . $quoteIdent($keyCol) . ' ASC LIMIT ' . NUMART_CHUNK_MAX_ROWS;
    } else {
        if ($cursorRaw !== '' && preg_match('/^\d+$/', $cursorRaw) !== 1) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Cursor tidak valid';
            exit;
        }
        $offset = $cursorRaw === '' ? 0 : (int) $cursorRaw;
        /** Tanpa PK urutan antar-request tidak dijamin; pakai PK majemuk bila ada. */
        $orderBy = '';
        if ($pkCols !== []) {
            $parts = [];
            foreach ($pkCols as $c) {
                $parts[] = $quoteIdent($c) . ' ASC';
            }
            $orderBy = ' ORDER BY ' . implode(',', $parts);
        }
        $sql = 'SELECT * FROM ' . $tableQ . $orderBy . ' LIMIT ' . $offset . ', ' . NUMART_CHUNK_MAX_ROWS;
    }

    $beginOutput();

    /** MYSQLI_USE_RESULT: baris dialirkan satu per satu, bukan ditarik semua ke RAM. */
    $dataRes = mysqli_query($conn, $sql, MYSQLI_USE_RESULT);
    if (!$dataRes) {
        echo '-- NUMART_CHUNK_ERROR ' . str_replace(["\r", "\n"], ' ', mysqli_error($conn)) . "\n";
        exit;
    }

    $fields = mysqli_fetch_fields($dataRes);
    $colList = [];
    $useHex = [];
    $keyIndex = null;
    foreach ($fields as $i => $field) {
        $colList[] = $quoteIdent((string) $field->name);
        $binaryTypes = [
            MYSQLI_TYPE_STRING,
            MYSQLI_TYPE_VAR_STRING,
            MYSQLI_TYPE_BLOB,
            MYSQLI_TYPE_TINY_BLOB,
            MYSQLI_TYPE_MEDIUM_BLOB,
            MYSQLI_TYPE_LONG_BLOB,
        ];
        $useHex[$i] = ((int) $field->type === MYSQLI_TYPE_BIT)
            || ((int) $field->charsetnr === 63 && in_array((int) $field->type, $binaryTypes, true));
        if ($keyCol !== null && (string) $field->name === $keyCol) {
            $keyIndex = $i;
        }
    }
    $insertPrefix = 'INSERT INTO ' . $tableQ . ' (' . implode(',', $colList) . ') VALUES ';

    $rows = 0;
    $bytes = 0;
    $stopped = false;
    $lastKey = null;
    $batch = [];
    $batchBytes = 0;
    $startedAt = microtime(true);

    while (($row = mysqli_fetch_row($dataRes)) !== null) {
        $rows++;

        $vals = [];
        foreach ($row as $i => $val) {
            if ($val === null) {
                $vals[] = 'NULL';
            } elseif ($useHex[$i]) {
                $vals[] = $val === '' ? "''" : '0x' . bin2hex((string) $val);
            } else {
                $vals[] = "'" . mysqli_real_escape_string($conn, (string) $val) . "'";
            }
        }

        if ($keyIndex !== null && $row[$keyIndex] !== null) {
            $lastKey = (string) $row[$keyIndex];
        }

        $tuple = '(' . implode(',', $vals) . ')';
        $batch[] = $tuple;
        $batchBytes += strlen($tuple) + 1;

        if (count($batch) >= NUMART_BATCH_MAX_ROWS || $batchBytes >= NUMART_BATCH_MAX_BYTES) {
            $sqlOut = $insertPrefix . implode(',', $batch) . ";\n";
            echo $sqlOut;
            $bytes += strlen($sqlOut);
            $batch = [];
            $batchBytes = 0;

            if ($bytes >= NUMART_CHUNK_MAX_BYTES || (microtime(true) - $startedAt) >= NUMART_CHUNK_MAX_SECONDS) {
                $stopped = true;
                break;
            }
        }
    }

    if ($batch !== []) {
        $sqlOut = $insertPrefix . implode(',', $batch) . ";\n";
        echo $sqlOut;
        $bytes += strlen($sqlOut);
    }

    mysqli_free_result($dataRes);

    $done = !$stopped && $rows < NUMART_CHUNK_MAX_ROWS;

    if ($keyCol !== null) {
        $nextCursor = $lastKey !== null ? $lastKey : $cursorRaw;
    } else {
        $nextCursor = (string) ((int) ($cursorRaw === '' ? 0 : $cursorRaw) + $rows);
    }

    echo '-- NUMART_CHUNK_END'
        . ' table=' . base64_encode($table)
        . ' rows=' . $rows
        . ' bytes=' . $bytes
        . ' done=' . ($done ? 1 : 0)
        . ' mode=' . $pagingMode
        . ' cursor=' . rawurlencode($nextCursor)
        . "\n";
    exit;
}

/**
 * Mode lama: seluruh database dalam satu request. Dipertahankan untuk klien
 * versi lama, tapi tetap rawan putus pada database besar — pakai protokol v2.
 */
$beginOutput();
header('Content-Disposition: attachment; filename="numart-live-' . date('Ymd-His') . '.sql"');

$tableCount = count($tables);
echo '-- NUMART live DB export ' . date('c') . "\n";
echo '-- NUMART_PROTOCOL: ' . NUMART_EXPORT_PROTOCOL . "\n";
echo '-- NUMART_TABLE_COUNT: ' . $tableCount . "\n";
echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";

echo "-- === PHASE 1: STRUCTURE ===\n\n";
foreach ($tables as $table) {
    $createRes = mysqli_query($conn, 'SHOW CREATE TABLE ' . $quoteIdent($table));
    $createRow = $createRes ? mysqli_fetch_assoc($createRes) : null;
    if ($createRes) {
        mysqli_free_result($createRes);
    }
    if (!$createRow || empty($createRow['Create Table'])) {
        continue;
    }

    echo '-- TABLE_STRUCT: ' . $quoteIdent($table) . "\n";
    echo 'DROP TABLE IF EXISTS ' . $quoteIdent($table) . ";\n";
    echo $sanitizeDdl((string) $createRow['Create Table']) . ";\n\n";
}

echo "-- === PHASE 2: DATA ===\n\n";

foreach ($tables as $table) {
    $tableQ = $quoteIdent($table);
    echo '-- TABLE_DATA: ' . $tableQ . "\n";

    $dataRes = mysqli_query($conn, 'SELECT * FROM ' . $tableQ, MYSQLI_USE_RESULT);
    if (!$dataRes) {
        echo "\n";
        continue;
    }

    $fields = mysqli_fetch_fields($dataRes);
    $colList = [];
    $useHex = [];
    foreach ($fields as $i => $field) {
        $colList[] = $quoteIdent((string) $field->name);
        $binaryTypes = [
            MYSQLI_TYPE_STRING,
            MYSQLI_TYPE_VAR_STRING,
            MYSQLI_TYPE_BLOB,
            MYSQLI_TYPE_TINY_BLOB,
            MYSQLI_TYPE_MEDIUM_BLOB,
            MYSQLI_TYPE_LONG_BLOB,
        ];
        $useHex[$i] = ((int) $field->type === MYSQLI_TYPE_BIT)
            || ((int) $field->charsetnr === 63 && in_array((int) $field->type, $binaryTypes, true));
    }
    $insertPrefix = 'INSERT INTO ' . $tableQ . ' (' . implode(',', $colList) . ') VALUES ';

    $batch = [];
    $batchBytes = 0;
    while (($row = mysqli_fetch_row($dataRes)) !== null) {
        $vals = [];
        foreach ($row as $i => $val) {
            if ($val === null) {
                $vals[] = 'NULL';
            } elseif ($useHex[$i]) {
                $vals[] = $val === '' ? "''" : '0x' . bin2hex((string) $val);
            } else {
                $vals[] = "'" . mysqli_real_escape_string($conn, (string) $val) . "'";
            }
        }
        $tuple = '(' . implode(',', $vals) . ')';
        $batch[] = $tuple;
        $batchBytes += strlen($tuple) + 1;

        if (count($batch) >= NUMART_BATCH_MAX_ROWS || $batchBytes >= NUMART_BATCH_MAX_BYTES) {
            echo $insertPrefix . implode(',', $batch) . ";\n";
            $batch = [];
            $batchBytes = 0;
        }
    }

    if ($batch !== []) {
        echo $insertPrefix . implode(',', $batch) . ";\n";
    }

    mysqli_free_result($dataRes);
    echo "\n";
}

echo "SET UNIQUE_CHECKS=1;\nSET FOREIGN_KEY_CHECKS=1;\n";
echo '-- NUMART_EXPORT_END tables=' . $tableCount . "\n";
