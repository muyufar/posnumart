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

const NUMART_EXPORT_PROTOCOL = 3;

/**
 * Batas per potongan — kecil agar tidak dipotong proxy/Cloudflare/LiteSpeed.
 * Bisa di-override lewat query: max_rows, max_bytes, max_seconds.
 */
const NUMART_CHUNK_MAX_ROWS = 80;
const NUMART_CHUNK_MAX_BYTES = 6144; // 6 KB SQL → base64 ~8 KB (CDN Hostinger ~50 KB)
const NUMART_CHUNK_MAX_SECONDS = 5.0;

/** Ambang flush batch INSERT. */
const NUMART_BATCH_MAX_ROWS = 5;
const NUMART_BATCH_MAX_BYTES = 2048;


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
$key = trim((string) ($_POST['key'] ?? $_GET['key'] ?? $_SERVER['HTTP_X_SYNC_KEY'] ?? ''));
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
 * Kompresi zlib dimatikan: gzip yang terpotong di proxy sering membuat trailer
 * hilang di sisi klien (HTTP 200 tapi body tidak lengkap).
 */
$beginOutput = static function (): void {
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    @ini_set('zlib.output_compression', '0');
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('CDN-Cache-Control: no-store');
    header('X-Accel-Buffering: no');
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
};

$reqParam = static function (string $name, string $default = ''): string {
    if (isset($_POST[$name])) {
        return (string) $_POST[$name];
    }
    if (isset($_GET[$name])) {
        return (string) $_GET[$name];
    }
    return $default;
};

$chunkMaxRows = max(1, min(2000, (int) $reqParam('max_rows', (string) NUMART_CHUNK_MAX_ROWS)));
$chunkMaxBytes = max(1024, min(1048576, (int) $reqParam('max_bytes', (string) NUMART_CHUNK_MAX_BYTES)));
$chunkMaxSeconds = max(2.0, min(25.0, (float) $reqParam('max_seconds', (string) NUMART_CHUNK_MAX_SECONDS)));

$phase = strtolower(trim($reqParam('phase')));

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

/** Fase data: satu potongan dari satu tabel (GET atau POST — POST disarankan, CDN tidak cache). */
if ($phase === 'data') {
    $table = $reqParam('table');
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

    $cursorRaw = $reqParam('cursor');
    $frag = max(0, (int) $reqParam('frag', '0'));

    if ($keyCol !== null) {
        if ($cursorRaw !== '' && preg_match('/^-?\d+$/', $cursorRaw) !== 1) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Cursor tidak valid';
            exit;
        }
        if ($frag > 0 && $cursorRaw !== '') {
            // Lanjutan potongan baris lebar: ambil baris yang sama lagi.
            $where = ' WHERE ' . $quoteIdent($keyCol) . ' = ' . $cursorRaw;
            $sql = 'SELECT * FROM ' . $tableQ . $where
                . ' ORDER BY ' . $quoteIdent($keyCol) . ' ASC LIMIT 1';
        } else {
            $where = $cursorRaw === '' ? '' : ' WHERE ' . $quoteIdent($keyCol) . ' > ' . $cursorRaw;
            $sql = 'SELECT * FROM ' . $tableQ . $where
                . ' ORDER BY ' . $quoteIdent($keyCol) . ' ASC LIMIT ' . $chunkMaxRows;
        }
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
        $limitRows = $frag > 0 ? 1 : $chunkMaxRows;
        $sql = 'SELECT * FROM ' . $tableQ . $orderBy . ' LIMIT ' . $offset . ', ' . $limitRows;
    }

    $beginOutput();

    /**
     * CDN Hostinger sering memotong body ~50 KB. Base64 menambah ~33%,
     * jadi SQL mentah per response harus jauh di bawah itu.
     * frag=N: potongan ke-N dari payload baris yang sama (baris lebar, mis. barang).
     */
    $safeSqlBytes = max(1024, min(28000, (int) floor($chunkMaxBytes * 0.85)));

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
    $committedKey = $cursorRaw; // PK terakhir yang sudah masuk payload/batch
    $batch = [];
    $batchBytes = 0;
    $startedAt = microtime(true);
    /** Buffer SQL lalu kirim base64 — hindari WAF/CDN yang memotong body berisi "INSERT INTO". */
    $payloadSql = '';
    $oversizedRowSql = null;

    $flushBatch = static function () use (&$batch, &$batchBytes, &$payloadSql, &$bytes, $insertPrefix): void {
        if ($batch === []) {
            return;
        }
        $sqlOut = $insertPrefix . implode(',', $batch) . ";\n";
        $payloadSql .= $sqlOut;
        $bytes += strlen($sqlOut);
        $batch = [];
        $batchBytes = 0;
    };

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

        $rowKey = null;
        if ($keyIndex !== null && $row[$keyIndex] !== null) {
            $rowKey = (string) $row[$keyIndex];
            $lastKey = $rowKey;
        }

        $tuple = '(' . implode(',', $vals) . ')';
        $tupleLen = strlen($tuple) + 1;
        $singleSql = $insertPrefix . $tuple . ";\n";
        $singleLen = strlen($singleSql);

        // Satu baris melebihi batas aman → kirim sendiri (bisa dipecah frag).
        if ($singleLen > $safeSqlBytes) {
            $flushBatch();
            if ($payloadSql !== '') {
                // Sudah ada data sebelumnya — hentikan sebelum baris lebar; baris ini di request berikutnya.
                $rows--;
                $stopped = true;
                $lastKey = $committedKey;
                break;
            }
            $oversizedRowSql = $singleSql;
            $committedKey = $rowKey !== null ? $rowKey : $committedKey;
            $stopped = true;
            break;
        }

        if ($batch !== [] && ($batchBytes + $tupleLen > $safeSqlBytes || $bytes + $batchBytes + $tupleLen > $safeSqlBytes)) {
            $flushBatch();
            if ($bytes >= $safeSqlBytes || (microtime(true) - $startedAt) >= $chunkMaxSeconds) {
                $rows--;
                $stopped = true;
                $lastKey = $committedKey;
                break;
            }
        }

        $batch[] = $tuple;
        $batchBytes += $tupleLen;
        if ($rowKey !== null) {
            $committedKey = $rowKey;
        }

        if (count($batch) >= NUMART_BATCH_MAX_ROWS || $batchBytes >= NUMART_BATCH_MAX_BYTES) {
            $flushBatch();
            if ($bytes >= $safeSqlBytes || (microtime(true) - $startedAt) >= $chunkMaxSeconds) {
                $stopped = true;
                break;
            }
        }
    }

    if (!$stopped && $batch !== []) {
        $flushBatch();
        if ($bytes >= $safeSqlBytes) {
            $stopped = true;
        }
    }

    mysqli_free_result($dataRes);

    if ($oversizedRowSql !== null) {
        $payloadSql = $oversizedRowSql;
        $bytes = strlen($payloadSql);
    }

    $fragTotal = 1;
    $more = 0;
    if ($payloadSql !== '' && strlen($payloadSql) > $safeSqlBytes) {
        $pieces = [];
        $len = strlen($payloadSql);
        for ($off = 0; $off < $len; $off += $safeSqlBytes) {
            $pieces[] = substr($payloadSql, $off, $safeSqlBytes);
        }
        $fragTotal = count($pieces);
        if ($frag >= $fragTotal) {
            $frag = $fragTotal - 1;
        }
        $payloadSql = $pieces[$frag];
        $bytes = strlen($payloadSql);
        $more = ($frag < $fragTotal - 1) ? 1 : 0;
    }

    // Keyset: selama masih frag, cursor = PK baris lebar (request berikutnya: key = PK).
    // Setelah frag terakhir / chunk normal, cursor = PK terakhir terkirim (request berikutnya: key > PK).
    if ($keyCol !== null) {
        $nextCursor = $committedKey !== null && $committedKey !== ''
            ? (string) $committedKey
            : ($lastKey !== null ? $lastKey : $cursorRaw);
    } else {
        // Offset mode: jangan maju offset sampai frag selesai.
        $baseOffset = $cursorRaw === '' ? 0 : (int) $cursorRaw;
        if ($more === 1) {
            $nextCursor = (string) $baseOffset;
        } else {
            $nextCursor = (string) ($baseOffset + $rows);
        }
    }

    $done = ($more === 0) && !$stopped && $rows < $chunkMaxRows && $oversizedRowSql === null;

    echo '-- NUMART_CHUNK_PAYLOAD encoding=base64 bytes=' . $bytes
        . ' frag=' . $frag
        . ' frag_total=' . $fragTotal
        . ' more=' . $more
        . "\n";
    echo base64_encode($payloadSql) . "\n";
    echo '-- NUMART_CHUNK_END'
        . ' table=' . base64_encode($table)
        . ' rows=' . $rows
        . ' bytes=' . $bytes
        . ' done=' . ($done ? 1 : 0)
        . ' more=' . $more
        . ' frag=' . $frag
        . ' frag_total=' . $fragTotal
        . ' mode=' . $pagingMode
        . ' encoding=base64'
        . ' cursor=' . rawurlencode((string) $nextCursor)
        . "\n";
    exit;
}

/**
 * Mode lama: seluruh database dalam satu request. Dipertahankan untuk klien
 * versi lama, tapi tetap rawan putus pada database besar — pakai protokol v3.
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
