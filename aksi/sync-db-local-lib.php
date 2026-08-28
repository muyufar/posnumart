<?php
/**
 * Sinkron database live → lokal / staging (bukan production).
 */

/** Polyfill PHP 8 string helpers — demopos Hostinger masih bisa PHP 7.3 */
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle === '' || strpos((string) $haystack, (string) $needle) !== false;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        if ($needle === '') {
            return true;
        }
        $len = strlen($needle);
        return $len === 0 || substr($haystack, -$len) === $needle;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('sync_db_config_path')) {
    function sync_db_config_path(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'sync-db-remote.config.php';
    }
}

if (!function_exists('sync_db_cache_dir')) {
    function sync_db_cache_dir(): string
    {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'sync-db-cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        return $dir;
    }
}

if (!function_exists('sync_db_load_config')) {
    function sync_db_load_config(): array
    {
        $path = sync_db_config_path();
        if (!is_file($path)) {
            return [];
        }
        $cfg = include $path;
        return is_array($cfg) ? $cfg : [];
    }
}

if (!function_exists('sync_db_normalize_host')) {
    function sync_db_normalize_host(string $host): string
    {
        $host = strtolower(trim($host));
        return (string) preg_replace('/:\d+$/', '', $host);
    }
}

if (!function_exists('sync_db_is_local_environment')) {
    function sync_db_is_local_environment(?array $cfg = null): bool
    {
        $cfg = $cfg ?? sync_db_load_config();
        $host = sync_db_normalize_host((string) ($_SERVER['HTTP_HOST'] ?? ''));

        // Staging / live-developer yang diizinkan menarik dump dari production.
        // Contoh: demopos.numartmagelang.com — harus didaftarkan eksplisit.
        foreach ($cfg['allowed_hosts'] ?? [] as $allowed) {
            $allowed = sync_db_normalize_host((string) $allowed);
            if ($allowed !== '' && ($host === $allowed || str_ends_with($host, '.' . $allowed))) {
                return true;
            }
        }

        foreach ($cfg['blocked_hosts'] ?? [] as $blocked) {
            $blocked = sync_db_normalize_host((string) $blocked);
            if ($blocked !== '' && ($host === $blocked || str_ends_with($host, '.' . $blocked))) {
                return false;
            }
        }

        $allowedExact = ['localhost', '127.0.0.1', '::1'];
        if (in_array($host, $allowedExact, true)) {
            return true;
        }

        if (preg_match('/\.(test|local|localhost)$/', $host) === 1) {
            return true;
        }

        $addr = (string) ($_SERVER['SERVER_ADDR'] ?? '');
        if (in_array($addr, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        if ((string) getenv('NUMART_ALLOW_DB_SYNC') === '1') {
            return true;
        }

        return false;
    }
}

if (!function_exists('sync_db_local_database_name')) {
    function sync_db_local_database_name(array $cfg, mysqli $localConn): string
    {
        $fromCfg = trim((string) ($cfg['local_database'] ?? ''));
        if ($fromCfg !== '') {
            return $fromCfg;
        }
        $res = mysqli_query($localConn, 'SELECT DATABASE() AS db');
        $row = $res ? mysqli_fetch_assoc($res) : null;
        return trim((string) ($row['db'] ?? ''));
    }
}

if (!function_exists('sync_db_log')) {
    function sync_db_log(array &$log, string $line): void
    {
        $entry = '[' . date('H:i:s') . '] ' . $line;
        $log[] = $entry;

        /** Dipakai halaman sync untuk menampilkan progres real-time. */
        $sink = $GLOBALS['sync_db_log_sink'] ?? null;
        if (is_callable($sink)) {
            $sink($entry);
        }
    }
}

if (!function_exists('sync_db_mysql_bin_dir')) {
    function sync_db_mysql_bin_dir(): ?string
    {
        $dirs = glob('C:/laragon/bin/mysql/mysql-*-winx64/bin', GLOB_ONLYDIR);
        if (!$dirs) {
            $dirs = glob('C:/laragon/bin/mysql/mysql-*/bin', GLOB_ONLYDIR);
        }
        if (!$dirs) {
            return null;
        }
        rsort($dirs);
        return $dirs[0];
    }
}

if (!function_exists('sync_db_write_defaults_file')) {
    function sync_db_write_defaults_file(array $client): string
    {
        $file = sync_db_cache_dir() . DIRECTORY_SEPARATOR . 'my.cnf.' . bin2hex(random_bytes(6));
        $lines = ["[client]\n"];
        foreach ($client as $k => $v) {
            $lines[] = $k . '=' . str_replace(["\r", "\n"], '', (string) $v) . "\n";
        }
        file_put_contents($file, implode('', $lines));
        return $file;
    }
}

if (!function_exists('sync_db_unlink_quiet')) {
    function sync_db_unlink_quiet(?string $path): void
    {
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }
}

if (!function_exists('sync_db_resolve_remote_host')) {
    function sync_db_resolve_remote_host(array $cfg): string
    {
        $host = trim((string) ($cfg['remote_host'] ?? ''));
        if ($host === '') {
            return '';
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        if (empty($cfg['remote_prefer_ipv4'])) {
            return $host;
        }
        $records = @dns_get_record($host, DNS_A);
        if (is_array($records)) {
            foreach ($records as $rec) {
                if (!empty($rec['ip']) && filter_var($rec['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $rec['ip'];
                }
            }
        }
        return $host;
    }
}

if (!function_exists('sync_db_public_ip_hint')) {
    function sync_db_public_ip_hint(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.ipify.org?format=text');
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 4);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
                $body = curl_exec($ch);
                curl_close($ch);
                if (is_string($body) && trim($body) !== '') {
                    $cached = trim($body);
                    return $cached;
                }
            }
        }
        $cached = '';
        return $cached;
    }
}

if (!function_exists('sync_db_client_ip_hint')) {
    function sync_db_client_ip_hint(): string
    {
        $public = sync_db_public_ip_hint();
        if ($public !== '') {
            return $public;
        }
        return '(lihat IP di pesan error MySQL di bawah — itulah IP outbound PC Anda)';
    }
}

if (!function_exists('sync_db_test_remote')) {
    function sync_db_test_remote(array $cfg): array
    {
        $host = sync_db_resolve_remote_host($cfg);
        $user = (string) ($cfg['remote_user'] ?? '');
        $pass = (string) ($cfg['remote_password'] ?? '');
        $db = (string) ($cfg['remote_database'] ?? '');
        $port = (int) ($cfg['remote_port'] ?? 3306);

        if ($host === '' || $user === '' || $db === '') {
            return ['ok' => false, 'message' => 'Config remote belum lengkap (host/user/database).'];
        }

        mysqli_report(MYSQLI_REPORT_OFF);
        $remote = @new mysqli($host, $user, $pass, $db, $port);
        if ($remote->connect_error) {
            $hint = sync_db_client_ip_hint();
            $msg = 'Gagal konek live: ' . $remote->connect_error;
            if (str_contains($remote->connect_error, 'Access denied')) {
                $msg .= ' — whitelist IP ini di hPanel → Remote MySQL: ' . $hint;
                if (str_contains($remote->connect_error, ':') && filter_var($hint, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $msg .= ' (IPv6; tambahkan juga IPv4 atau set remote_prefer_ipv4 => true di config).';
                }
            } else {
                $msg .= ' — pastikan Remote MySQL Hostinger aktif & IP di-whitelist.';
            }
            return ['ok' => false, 'message' => $msg, 'resolved_host' => $host];
        }
        $remote->set_charset('utf8mb4');
        $remote->close();

        return ['ok' => true, 'message' => 'Koneksi ke database live berhasil.'];
    }
}

if (!function_exists('sync_db_count_tables_in_dump')) {
    function sync_db_count_tables_in_dump(string $dumpFile): int
    {
        if (preg_match('/NUMART_TABLE_COUNT:\s*(\d+)/', (string) @file_get_contents($dumpFile, false, null, 0, 4096), $m)) {
            return (int) $m[1];
        }
        $n = 0;
        $fh = @fopen($dumpFile, 'rb');
        if (!$fh) {
            return 0;
        }
        while (($line = fgets($fh)) !== false) {
            if (stripos($line, 'CREATE TABLE') !== false) {
                $n++;
            }
        }
        fclose($fh);
        return $n;
    }
}

if (!function_exists('sync_db_dump_export_complete')) {
    function sync_db_dump_export_complete(string $dumpFile): bool
    {
        $size = filesize($dumpFile);
        if ($size === false || $size < 32) {
            return false;
        }
        $tail = @file_get_contents($dumpFile, false, null, max(0, $size - 4096));
        return is_string($tail) && str_contains($tail, 'NUMART_EXPORT_END');
    }
}

if (!function_exists('sync_db_local_connection')) {
    /**
     * Koneksi ke DB tujuan sync.
     * Urutan: kredensial local_* di config → fallback ke $conn aplikasi (koneksi.php)
     * bila database-nya sama (penting di Hostinger/posgit).
     *
     * @return array{0:?mysqli,1:string} [conn, error]
     */
    function sync_db_local_connection(array $cfg, string $localDb): array
    {
        global $conn;

        mysqli_report(MYSQLI_REPORT_OFF);
        $host = (string) ($cfg['local_host'] ?? '127.0.0.1');
        $user = (string) ($cfg['local_user'] ?? 'root');
        $pass = (string) ($cfg['local_password'] ?? '');
        $port = (int) ($cfg['local_port'] ?? 3306);

        $c = @new mysqli($host, $user, $pass, $localDb, $port);
        if (!$c->connect_error) {
            $c->set_charset('utf8mb4');
            return [$c, ''];
        }
        $errCfg = $c->connect_error;

        // Fallback: pakai koneksi halaman yang sudah login ke DB ini.
        if (isset($conn) && $conn instanceof mysqli) {
            $dbRes = @mysqli_query($conn, 'SELECT DATABASE() AS db');
            $dbRow = $dbRes ? mysqli_fetch_assoc($dbRes) : null;
            $currentDb = trim((string) ($dbRow['db'] ?? ''));
            if ($currentDb !== '' && strcasecmp($currentDb, $localDb) === 0) {
                return [$conn, ''];
            }
            // Coba SELECT ke localDb dengan user yang sama (koneksi.php).
            if (@mysqli_select_db($conn, $localDb)) {
                $conn->set_charset('utf8mb4');
                return [$conn, ''];
            }
        }

        return [null, 'config local_* gagal (' . $errCfg . '). Samakan local_user/local_password dengan aksi/koneksi.php posgit.'];
    }
}

if (!function_exists('sync_db_count_local_tables')) {
    function sync_db_count_local_tables(mysqli $conn): int
    {
        $dbRes = mysqli_query($conn, 'SELECT DATABASE() AS d');
        $dbRow = $dbRes ? mysqli_fetch_assoc($dbRes) : null;
        $db = mysqli_real_escape_string($conn, (string) ($dbRow['d'] ?? ''));
        $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = '$db' AND table_type = 'BASE TABLE'");
        $row = $res ? mysqli_fetch_assoc($res) : null;
        return (int) ($row['c'] ?? 0);
    }
}

if (!function_exists('sync_db_collation_compat_map')) {
    function sync_db_collation_compat_map(): array
    {
        return [
            'utf8mb4_uca1400_ai_ci' => 'utf8mb4_unicode_ci',
            'utf8mb4_uca1400_as_ci' => 'utf8mb4_unicode_ci',
            'utf8mb4_uca1400_as_cs' => 'utf8mb4_unicode_ci',
            'utf8mb4_0900_ai_ci' => 'utf8mb4_unicode_ci',
            'utf8mb4_0900_as_ci' => 'utf8mb4_unicode_ci',
            'utf8mb4_0900_as_cs' => 'utf8mb4_unicode_ci',
            'utf8mb4_0900_bin' => 'utf8mb4_bin',
        ];
    }
}

if (!function_exists('sync_db_sanitize_sql_ddl')) {
    function sync_db_sanitize_sql_ddl(string $sql): string
    {
        return str_replace(
            array_keys(sync_db_collation_compat_map()),
            array_values(sync_db_collation_compat_map()),
            $sql
        );
    }
}

if (!function_exists('sync_db_normalize_dump_file')) {
    /**
     * Hostinger MySQL 8.4+ pakai collation uca1400 — Laragon/MySQL lokal sering belum support.
     */
    function sync_db_normalize_dump_file(string $dumpFile, array &$log): bool
    {
        $tmp = $dumpFile . '.compat.sql';
        $in = fopen($dumpFile, 'rb');
        if ($in === false) {
            return false;
        }
        $out = fopen($tmp, 'wb');
        if ($out === false) {
            fclose($in);
            return false;
        }

        $map = sync_db_collation_compat_map();
        $hits = 0;
        while (($line = fgets($in)) !== false) {
            $newLine = str_replace(array_keys($map), array_values($map), $line);
            if ($newLine !== $line) {
                $hits++;
            }
            fwrite($out, $newLine);
        }
        fclose($in);
        fclose($out);

        if (!@unlink($dumpFile) || !@rename($tmp, $dumpFile)) {
            sync_db_unlink_quiet($tmp);
            return false;
        }

        if ($hits > 0) {
            sync_db_log($log, 'Collation dinormalisasi untuk MySQL lokal (' . $hits . ' baris).');
        }

        return true;
    }
}

if (!function_exists('sync_db_drop_all_tables')) {
    function sync_db_drop_all_tables(mysqli $conn, array &$log): void
    {
        mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');
        $res = mysqli_query($conn, 'SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        $tables = [];
        if ($res) {
            while ($row = mysqli_fetch_array($res)) {
                $tables[] = $row[0];
            }
        }
        foreach ($tables as $table) {
            $t = '`' . str_replace('`', '``', $table) . '`';
            if (!mysqli_query($conn, "DROP TABLE IF EXISTS $t")) {
                sync_db_log($log, 'WARN drop ' . $table . ': ' . mysqli_error($conn));
            }
        }
        mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');
        sync_db_log($log, 'Tabel lokal lama dihapus: ' . count($tables) . ' tabel.');
    }
}

if (!function_exists('sync_db_run_mysqldump_sync')) {
    function sync_db_run_mysqldump_sync(array $cfg, string $localDb, array &$log): array
    {
        $bin = sync_db_mysql_bin_dir();
        if ($bin === null) {
            return ['ok' => false, 'message' => 'mysqldump Laragon tidak ditemukan (C:/laragon/bin/mysql/...).'];
        }

        $dumpFile = sync_db_cache_dir() . DIRECTORY_SEPARATOR . 'live_dump_' . date('Ymd_His') . '.sql';
        $remoteDefaults = sync_db_write_defaults_file([
            'host' => sync_db_resolve_remote_host($cfg),
            'port' => (int) ($cfg['remote_port'] ?? 3306),
            'user' => $cfg['remote_user'],
            'password' => $cfg['remote_password'],
        ]);
        $localDefaults = sync_db_write_defaults_file([
            'host' => $cfg['local_host'] ?? '127.0.0.1',
            'port' => (int) ($cfg['local_port'] ?? 3306),
            'user' => $cfg['local_user'] ?? 'root',
            'password' => $cfg['local_password'] ?? '',
        ]);

        $mysqldump = $bin . DIRECTORY_SEPARATOR . 'mysqldump.exe';
        $mysql = $bin . DIRECTORY_SEPARATOR . 'mysql.exe';
        $remoteDb = $cfg['remote_database'];

        sync_db_log($log, 'Mengunduh dump dari live via mysqldump...');

        $dumpCmd = sprintf(
            'cmd /c ""%s" --defaults-extra-file=%s --single-transaction --routines --triggers --set-gtid-purged=OFF --default-character-set=utf8mb4 %s > "%s" 2>&1"',
            $mysqldump,
            escapeshellarg($remoteDefaults),
            escapeshellarg($remoteDb),
            $dumpFile
        );

        $out = [];
        $code = 0;
        exec($dumpCmd, $out, $code);
        if ($code !== 0 || !is_file($dumpFile) || filesize($dumpFile) < 32) {
            sync_db_unlink_quiet($remoteDefaults);
            sync_db_unlink_quiet($localDefaults);
            sync_db_unlink_quiet($dumpFile);
            return [
                'ok' => false,
                'message' => 'mysqldump gagal (kode ' . $code . '). ' . implode(' ', $out),
            ];
        }

        sync_db_log($log, 'Dump live: ' . number_format(filesize($dumpFile) / 1048576, 2) . ' MB');

        if (!sync_db_normalize_dump_file($dumpFile, $log)) {
            sync_db_log($log, 'WARN: normalisasi collation dilewati.');
        }

        global $conn;
        sync_db_drop_all_tables($conn, $log);

        sync_db_log($log, 'Mengimpor ke database lokal: ' . $localDb);

        $importCmd = sprintf(
            'cmd /c ""%s" --defaults-extra-file=%s %s < "%s" 2>&1"',
            $mysql,
            escapeshellarg($localDefaults),
            escapeshellarg($localDb),
            $dumpFile
        );
        $out2 = [];
        $code2 = 0;
        exec($importCmd, $out2, $code2);

        sync_db_unlink_quiet($remoteDefaults);
        sync_db_unlink_quiet($localDefaults);

        if ($code2 !== 0) {
            sync_db_log($log, 'Import error: ' . implode("\n", $out2));
            return ['ok' => false, 'message' => 'Import mysql gagal (kode ' . $code2 . ').'];
        }

        sync_db_unlink_quiet($dumpFile);
        sync_db_log($log, 'Import selesai.');

        return ['ok' => true, 'message' => 'Database live berhasil disinkronkan ke lokal.'];
    }
}

if (!function_exists('sync_db_get_skip_tables')) {
    /**
     * @return array<string, true> lowercase table name => true
     */
    function sync_db_get_skip_tables(array $cfg): array
    {
        $skip = [];
        foreach ([
            'audit_barang',
            'barang_barcode_ubah_log',
            'wa_blast_history',
            'wa_blast_recipients',
            'wa_auto_blast_sent_log',
            'wa_auto_below_target_sent',
            'midtrans_payment_history',
            'user_attendance',
            'user_attendance_recap',
            'user_attendance_request',
            'shift_laporan',
            'shift_laporan_kasir',
            'shift_laporan_pengeluaran',
        ] as $defaultSkip) {
            $skip[strtolower($defaultSkip)] = true;
        }
        foreach ((array) ($cfg['skip_tables'] ?? []) as $t) {
            $t = trim((string) $t);
            if ($t !== '') {
                $skip[strtolower($t)] = true;
            }
        }
        if (!empty($cfg['skip_tables_only']) && is_array($cfg['skip_tables'])) {
            $skip = [];
            foreach ($cfg['skip_tables'] as $t) {
                $t = trim((string) $t);
                if ($t !== '') {
                    $skip[strtolower($t)] = true;
                }
            }
        }
        return $skip;
    }
}

if (!function_exists('sync_db_run_php_sync')) {
    function sync_db_run_php_sync(array $cfg, string $localDb, array &$log): array
    {
        $host = sync_db_resolve_remote_host($cfg);
        $port = (int) ($cfg['remote_port'] ?? 3306);
        $remote = new mysqli(
            $host,
            (string) $cfg['remote_user'],
            (string) $cfg['remote_password'],
            (string) $cfg['remote_database'],
            $port
        );
        if ($remote->connect_error) {
            return ['ok' => false, 'message' => 'Remote: ' . $remote->connect_error];
        }
        $remote->set_charset('utf8mb4');

        [$localConn, $localErr] = sync_db_local_connection($cfg, $localDb);
        if (!$localConn) {
            $remote->close();
            return ['ok' => false, 'message' => 'Koneksi database target gagal: ' . $localDb . ' — ' . $localErr];
        }

        sync_db_drop_all_tables($localConn, $log);

        $skipTables = sync_db_get_skip_tables($cfg);

        $tablesRes = mysqli_query($remote, 'SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        $tables = [];
        if ($tablesRes) {
            while ($row = mysqli_fetch_array($tablesRes)) {
                $tables[] = (string) $row[0];
            }
            mysqli_free_result($tablesRes);
        }

        mysqli_query($localConn, 'SET FOREIGN_KEY_CHECKS=0');
        mysqli_query($localConn, 'SET NAMES utf8mb4');

        $tableCount = count($tables);
        $tableIndex = 0;
        foreach ($tables as $table) {
            $tableIndex++;
            $tEsc = mysqli_real_escape_string($remote, $table);
            $createRes = mysqli_query($remote, "SHOW CREATE TABLE `$tEsc`");
            $createRow = $createRes ? mysqli_fetch_assoc($createRes) : null;
            if ($createRes) {
                mysqli_free_result($createRes);
            }
            if (!$createRow || empty($createRow['Create Table'])) {
                sync_db_log($log, 'SKIP ' . $table . ' (CREATE tidak ditemukan)');
                continue;
            }

            $ddl = sync_db_sanitize_sql_ddl((string) $createRow['Create Table']);
            if (!mysqli_query($localConn, 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`')) {
                mysqli_query($localConn, 'SET FOREIGN_KEY_CHECKS=1');
                $remote->close();
                $localConn->close();
                return ['ok' => false, 'message' => 'DROP ' . $table . ': ' . mysqli_error($localConn)];
            }
            if (!mysqli_query($localConn, $ddl)) {
                mysqli_query($localConn, 'SET FOREIGN_KEY_CHECKS=1');
                $remote->close();
                $localConn->close();
                return ['ok' => false, 'message' => 'CREATE ' . $table . ': ' . mysqli_error($localConn)];
            }

            if (isset($skipTables[strtolower($table)])) {
                sync_db_log($log, sprintf(
                    'SKIP [%d/%d] %s — skip_tables (struktur saja)',
                    $tableIndex,
                    $tableCount,
                    $table
                ));
                continue;
            }

            $dataRes = mysqli_query($remote, "SELECT * FROM `$tEsc`", MYSQLI_USE_RESULT);
            $batch = [];
            $batchSize = 80;
            $rows = 0;
            $cols = [];
            if ($dataRes) {
                while ($dataRow = mysqli_fetch_assoc($dataRes)) {
                    if ($cols === []) {
                        foreach (array_keys($dataRow) as $col) {
                            $cols[] = '`' . str_replace('`', '``', (string) $col) . '`';
                        }
                    }
                    $vals = [];
                    foreach ($dataRow as $val) {
                        if ($val === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = "'" . mysqli_real_escape_string($localConn, (string) $val) . "'";
                        }
                    }
                    $batch[] = '(' . implode(',', $vals) . ')';
                    $rows++;
                    if (count($batch) >= $batchSize) {
                        $sql = 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(',', $cols) . ') VALUES ' . implode(',', $batch);
                        if (!mysqli_query($localConn, $sql)) {
                            mysqli_query($localConn, 'SET FOREIGN_KEY_CHECKS=1');
                            $remote->close();
                            $localConn->close();
                            return ['ok' => false, 'message' => 'INSERT ' . $table . ': ' . mysqli_error($localConn)];
                        }
                        $batch = [];
                    }
                }
                mysqli_free_result($dataRes);
            }
            if ($batch !== [] && $cols !== []) {
                $sql = 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(',', $cols) . ') VALUES ' . implode(',', $batch);
                if (!mysqli_query($localConn, $sql)) {
                    mysqli_query($localConn, 'SET FOREIGN_KEY_CHECKS=1');
                    $remote->close();
                    $localConn->close();
                    return ['ok' => false, 'message' => 'INSERT ' . $table . ': ' . mysqli_error($localConn)];
                }
            }

            sync_db_log($log, sprintf(
                'OK [%d/%d] %s — %s baris',
                $tableIndex,
                $tableCount,
                $table,
                number_format($rows)
            ));
        }

        mysqli_query($localConn, 'SET FOREIGN_KEY_CHECKS=1');
        $remote->close();
        $localConn->close();

        return [
            'ok' => true,
            'message' => 'Sinkron MySQL langsung selesai: ' . $tableCount . ' tabel.',
        ];
    }
}

if (!function_exists('sync_db_test_http')) {
    function sync_db_test_http(array $cfg): array
    {
        $url = trim((string) ($cfg['http_export_url'] ?? ''));
        $secret = trim((string) ($cfg['http_export_secret'] ?? ''));
        if ($url === '' || $secret === '') {
            return ['ok' => false, 'message' => 'http_export_url / http_export_secret belum diisi di config.'];
        }

        $pingUrl = $url . (str_contains($url, '?') ? '&' : '?') . 'ping=1&key=' . rawurlencode($secret);
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'Ekstensi curl PHP tidak aktif di Laragon.'];
        }

        $ch = curl_init($pingUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $err !== '') {
            return ['ok' => false, 'message' => 'HTTP gagal: ' . ($err ?: 'unknown')];
        }
        if ($code === 403) {
            return ['ok' => false, 'message' => 'Secret salah atau IP diblokir di server live (403).'];
        }
        if ($code === 503) {
            return ['ok' => false, 'message' => 'Export belum di-setup di live (503). Upload api/sync-db-export-live.php + config.'];
        }
        if ($code !== 200) {
            return ['ok' => false, 'message' => 'HTTP ' . $code . ': ' . substr((string) $body, 0, 200)];
        }

        $json = json_decode((string) $body, true);
        if (!is_array($json) || empty($json['ok'])) {
            return ['ok' => false, 'message' => 'Respons live tidak valid.'];
        }

        $cnt = (int) ($json['table_count'] ?? 0);
        $protocol = (int) ($json['protocol'] ?? 1);
        $tables = [];
        foreach ((array) ($json['tables'] ?? []) as $t) {
            $t = (string) $t;
            if ($t !== '') {
                $tables[] = $t;
            }
        }

        $msg = 'Endpoint export live siap (mode HTTP, protokol v' . $protocol . ')';
        if ($cnt > 0) {
            $msg .= " — $cnt tabel di live.";
        }
        if ($protocol < 4) {
            $msg .= ' PERINGATAN: upload api/sync-db-export-live.php terbaru ke pos (butuh protokol v4).';
        }

        return [
            'ok' => true,
            'message' => $msg,
            'table_count' => $cnt,
            'protocol' => $protocol,
            'tables' => $tables,
        ];
    }
}

if (!function_exists('sync_db_import_sql_file')) {
    function sync_db_import_sql_file(
        array $cfg,
        string $localDb,
        string $dumpFile,
        array &$log,
        bool $skipNormalize = false
    ): array {
        global $conn;

        if (!is_file($dumpFile) || filesize($dumpFile) < 32) {
            return ['ok' => false, 'message' => 'File dump kosong atau tidak ditemukan.'];
        }

        sync_db_log($log, 'Dump: ' . number_format(filesize($dumpFile) / 1048576, 2) . ' MB');

        $expectedTables = sync_db_count_tables_in_dump($dumpFile);
        sync_db_log($log, 'Tabel dalam dump: ' . $expectedTables);

        if (!sync_db_dump_export_complete($dumpFile)) {
            return [
                'ok' => false,
                'message' => 'Dump tidak lengkap — export dari live terputus sebelum selesai. Coba sinkron ulang.',
            ];
        }

        if (!$skipNormalize && !sync_db_normalize_dump_file($dumpFile, $log)) {
            sync_db_log($log, 'WARN: normalisasi collation dilewati.');
        }

        $head = @file_get_contents($dumpFile, false, null, 0, 8192);
        if ($head === false || stripos($head, 'CREATE TABLE') === false) {
            return ['ok' => false, 'message' => 'File dump tidak valid (tidak ada CREATE TABLE).'];
        }

        [$target, $targetErr] = sync_db_local_connection($cfg, $localDb);
        $ownsTarget = false;
        if ($target === null) {
            if (isset($conn) && $conn instanceof mysqli) {
                $target = $conn;
                sync_db_log($log, 'WARN koneksi local_* gagal, pakai koneksi.php: ' . $targetErr);
            } else {
                return ['ok' => false, 'message' => 'Koneksi database target gagal: ' . $localDb . ' — ' . $targetErr];
            }
        } else {
            // Hanya close jika bukan global $conn
            $ownsTarget = !isset($conn) || $target !== $conn;
        }

        sync_db_drop_all_tables($target, $log);

        $bin = sync_db_mysql_bin_dir();
        if ($bin !== null && PHP_OS_FAMILY === 'Windows') {
            $localDefaults = sync_db_write_defaults_file([
                'host' => $cfg['local_host'] ?? '127.0.0.1',
                'port' => (int) ($cfg['local_port'] ?? 3306),
                'user' => $cfg['local_user'] ?? 'root',
                'password' => $cfg['local_password'] ?? '',
            ]);
            $mysql = $bin . DIRECTORY_SEPARATOR . 'mysql.exe';
            $importCmd = sprintf(
                'cmd /c ""%s" --defaults-extra-file=%s --force %s < "%s" 2>&1"',
                $mysql,
                escapeshellarg($localDefaults),
                escapeshellarg($localDb),
                $dumpFile
            );
            sync_db_log($log, 'Mengimpor ke database lokal: ' . $localDb);

            $out = [];
            $code = 0;
            exec($importCmd, $out, $code);
            sync_db_unlink_quiet($localDefaults);

            /** --force melanjutkan setelah error, jadi kode keluar saja tidak cukup. */
            $errorLines = [];
            foreach ($out as $line) {
                if (stripos($line, 'ERROR') !== false) {
                    $errorLines[] = trim($line);
                }
            }

            $localCount = sync_db_count_local_tables($target);
            if ($ownsTarget) {
                $target->close();
            }
            sync_db_log($log, 'Tabel lokal setelah import: ' . $localCount);

            if ($errorLines !== []) {
                sync_db_log($log, 'Import melaporkan ' . count($errorLines) . ' error SQL:');
                foreach (array_slice($errorLines, 0, 8) as $line) {
                    sync_db_log($log, '  ' . $line);
                }
            }

            if ($expectedTables > 0 && $localCount < $expectedTables) {
                return [
                    'ok' => false,
                    'message' => "Import tidak lengkap: lokal $localCount tabel, dump $expectedTables tabel. Cek log.",
                ];
            }

            if ($errorLines !== []) {
                return [
                    'ok' => false,
                    'message' => 'Semua tabel terbentuk tapi ada ' . count($errorLines)
                        . ' error SQL saat import — sebagian data bisa hilang. Cek log.',
                ];
            }

            if ($code === 0) {
                sync_db_log($log, 'Import selesai.');
                return ['ok' => true, 'message' => "Database live disinkronkan ($localCount tabel)."];
            }

            sync_db_log($log, 'Import mysql keluar dengan kode ' . $code . ': ' . implode(' ', array_slice($out, -10)));
        } elseif ($ownsTarget) {
            $target->close();
        }

        return ['ok' => false, 'message' => 'Import gagal. Cek log — biasanya collation MySQL live lebih baru dari lokal.'];
    }
}

if (!function_exists('sync_db_http_export_base_url')) {
    function sync_db_http_export_base_url(array $cfg): string
    {
        $url = trim((string) ($cfg['http_export_url'] ?? ''));
        $secret = trim((string) ($cfg['http_export_secret'] ?? ''));
        return $url . (str_contains($url, '?') ? '&' : '?') . 'key=' . rawurlencode($secret);
    }
}

if (!function_exists('sync_db_http_export_endpoint')) {
    function sync_db_http_export_endpoint(array $cfg): string
    {
        return trim((string) ($cfg['http_export_url'] ?? ''));
    }
}

if (!function_exists('sync_db_http_export_secret')) {
    function sync_db_http_export_secret(array $cfg): string
    {
        return trim((string) ($cfg['http_export_secret'] ?? ''));
    }
}

if (!function_exists('sync_db_download_post_to_file')) {
    /**
     * POST ke export live — CDN/proxy tidak cache POST (hindari potong ~50 KB).
     *
     * @return array{ok: bool, code: int, error: string}
     */
    function sync_db_download_post_to_file(string $url, array $post, string $target, int $timeout = 180): array
    {
        $fp = fopen($target, 'wb');
        if ($fp === false) {
            return ['ok' => false, 'code' => 0, 'error' => 'Tidak bisa menulis ' . basename($target)];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_LOW_SPEED_LIMIT => 64,
            CURLOPT_LOW_SPEED_TIME => 90,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => 'identity',
            CURLOPT_HTTPHEADER => [
                'Accept-Encoding: identity',
                'Cache-Control: no-cache',
            ],
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($ok === false || $err !== '') {
            return ['ok' => false, 'code' => $code, 'error' => $err !== '' ? $err : 'transfer gagal'];
        }
        if ($code !== 200) {
            return ['ok' => false, 'code' => $code, 'error' => 'HTTP ' . $code];
        }

        return ['ok' => true, 'code' => $code, 'error' => ''];
    }
}

if (!function_exists('sync_db_download_to_file')) {
    /**
     * @return array{ok: bool, code: int, error: string}
     */
    function sync_db_download_to_file(string $url, string $target, int $timeout = 180): array
    {
        $fp = fopen($target, 'wb');
        if ($fp === false) {
            return ['ok' => false, 'code' => 0, 'error' => 'Tidak bisa menulis ' . basename($target)];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_LOW_SPEED_LIMIT => 64,
            CURLOPT_LOW_SPEED_TIME => 90,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            // Jangan minta gzip: body terpotong + gzip = trailer hilang tanpa error HTTP.
            CURLOPT_ENCODING => 'identity',
            CURLOPT_HTTPHEADER => ['Accept-Encoding: identity'],
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($ok === false || $err !== '') {
            return ['ok' => false, 'code' => $code, 'error' => $err !== '' ? $err : 'transfer gagal'];
        }
        if ($code !== 200) {
            return ['ok' => false, 'code' => $code, 'error' => 'HTTP ' . $code];
        }

        return ['ok' => true, 'code' => $code, 'error' => ''];
    }
}

if (!function_exists('sync_db_read_trailer')) {
    /**
     * Penanda akhir ada di baris terakhir file; kehadirannya membuktikan
     * response tidak terpotong di tengah jalan.
     *
     * @return array<string, string>|null
     */
    function sync_db_read_trailer(string $file, string $marker): ?array
    {
        $size = @filesize($file);
        if ($size === false || $size <= 0) {
            return null;
        }
        $tail = @file_get_contents($file, false, null, max(0, $size - 2048));
        if (!is_string($tail)) {
            return null;
        }

        $needle = '-- ' . $marker;
        $pos = strrpos($tail, $needle);
        if ($pos === false) {
            return null;
        }

        $rest = substr($tail, $pos + strlen($needle));
        $newline = strpos($rest, "\n");
        $line = trim($newline === false ? $rest : substr($rest, 0, $newline));

        $parsed = [];
        foreach (preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $pair) {
            $eq = strpos($pair, '=');
            if ($eq !== false) {
                $parsed[substr($pair, 0, $eq)] = substr($pair, $eq + 1);
            }
        }

        return $parsed;
    }
}

if (!function_exists('sync_db_fetch_part')) {
    /**
     * Unduh satu bagian dan pastikan penanda akhirnya ada; ulangi bila terpotong.
     *
     * @return array{ok: bool, message: string, trailer: array<string, string>}
     */
    function sync_db_fetch_part(string $url, string $target, string $marker, array &$log, int $attempts = 5): array
    {
        $lastError = '';
        for ($i = 1; $i <= $attempts; $i++) {
            $res = sync_db_download_to_file($url, $target);
            if ($res['ok']) {
                $trailer = sync_db_read_trailer($target, $marker);
                if ($trailer !== null) {
                    return ['ok' => true, 'message' => '', 'trailer' => $trailer];
                }
                $errorTrailer = sync_db_read_trailer($target, 'NUMART_CHUNK_ERROR');
                $size = (int) (@filesize($target) ?: 0);
                $head = substr((string) @file_get_contents($target, false, null, 0, 120), 0, 120);
                $head = str_replace(["\r", "\n"], ' ', $head);
                $lastError = $errorTrailer !== null
                    ? 'live menolak query tabel ini'
                    : 'response terpotong (penanda akhir tidak ada, ' . number_format($size)
                        . ' byte, awal: ' . $head . ')';
            } else {
                $lastError = $res['error'];
            }

            if ($i < $attempts) {
                sync_db_log($log, 'WARN percobaan ' . $i . ' gagal (' . $lastError . '), mengulang...');
                sleep(min(8, 2 * $i));
            }
        }

        sync_db_unlink_quiet($target);
        return ['ok' => false, 'message' => $lastError, 'trailer' => []];
    }
}

if (!function_exists('sync_db_fetch_data_part')) {
    /**
     * Unduh satu potongan data tabel via POST + perkecil otomatis bila CDN memotong (~50 KB).
     *
     * @return array{ok: bool, message: string, trailer: array<string, string>}
     */
    function sync_db_fetch_data_part(
        array $cfg,
        string $table,
        string $cursor,
        int &$maxRows,
        int &$maxBytes,
        float $maxSeconds,
        string $target,
        array &$log,
        int $frag = 0
    ): array {
        $url = sync_db_http_export_endpoint($cfg);
        $secret = sync_db_http_export_secret($cfg);
        $marker = 'NUMART_CHUNK_END';
        $lastError = '';
        $attempts = 0;
        $maxAttempts = 10;

        while ($attempts < $maxAttempts) {
            $attempts++;
            $endpoint = $url;
            $sep = (strpos($endpoint, '?') === false) ? '?' : '&';
            // Duplikasi param di query: beberapa WAF Hostinger mengosongkan body POST.
            $endpoint .= $sep . http_build_query([
                'key' => $secret,
                'phase' => 'data',
                'table' => $table,
                'cursor' => $cursor,
                'frag' => (string) max(0, $frag),
                'max_rows' => (string) max(1, $maxRows),
                'max_bytes' => (string) max(1024, $maxBytes),
                'max_seconds' => (string) $maxSeconds,
                'nocache' => uniqid((string) mt_rand(), true),
            ]);
            $post = [
                'key' => $secret,
                'phase' => 'data',
                'table' => $table,
                'cursor' => $cursor,
                'frag' => (string) max(0, $frag),
                'max_rows' => (string) max(1, $maxRows),
                'max_bytes' => (string) max(1024, $maxBytes),
                'max_seconds' => (string) $maxSeconds,
            ];

            $res = sync_db_download_post_to_file($endpoint, $post, $target);
            if ($res['ok']) {
                $trailer = sync_db_read_trailer($target, $marker);
                if ($trailer !== null) {
                    return ['ok' => true, 'message' => '', 'trailer' => $trailer];
                }
                $errorTrailer = sync_db_read_trailer($target, 'NUMART_CHUNK_ERROR');
                $size = (int) (@filesize($target) ?: 0);
                $head = substr((string) @file_get_contents($target, false, null, 0, 120), 0, 120);
                $head = str_replace(["\r", "\n"], ' ', $head);
                if ($errorTrailer !== null) {
                    $lastError = 'live menolak query tabel ini';
                } elseif (stripos($head, 'NUMART_EXPORT_ERROR') !== false) {
                    $lastError = 'export live menolak request (phase/protokol) — pastikan api/sync-db-export-live.php terbaru di pos';
                } elseif (stripos($head, '<!DOCTYPE') !== false || stripos($head, '<html') !== false) {
                    $lastError = 'export mengembalikan HTML (bukan SQL) — cek URL/secret/login WAF';
                } else {
                    $lastError = 'response terpotong (' . number_format($size) . ' byte, awal: ' . $head . ')';
                }

                // CDN Hostinger ~50 KB — perkecil potongan dan coba lagi.
                if ($maxBytes > 1024 || $maxRows > 1) {
                    $maxBytes = (int) max(1024, floor($maxBytes / 2));
                    $maxRows = (int) max(1, floor($maxRows / 2));
                    sync_db_log($log, 'WARN response terpotong, perkecil potongan → max_bytes='
                        . $maxBytes . ' max_rows=' . $maxRows);
                    continue;
                }
            } else {
                $lastError = $res['error'];
            }

            if ($attempts < $maxAttempts) {
                sync_db_log($log, 'WARN percobaan ' . $attempts . ' gagal (' . $lastError . '), mengulang...');
                sleep(min(6, $attempts));
            }
        }

        sync_db_unlink_quiet($target);
        return ['ok' => false, 'message' => $lastError, 'trailer' => []];
    }
}

if (!function_exists('sync_db_append_file')) {
    function sync_db_append_file($handle, string $source): bool
    {
        $in = fopen($source, 'rb');
        if ($in === false) {
            return false;
        }
        $ok = stream_copy_to_stream($in, $handle) !== false;
        fclose($in);
        return $ok;
    }
}

if (!function_exists('sync_db_extract_chunk_sql')) {
    /**
     * Ambil SQL dari file potongan. Protokol v3: body base64 di antara
     * NUMART_CHUNK_PAYLOAD dan NUMART_CHUNK_END. Protokol lama: SQL mentah.
     */
    function sync_db_extract_chunk_sql(string $partFile, array $trailer): string
    {
        $raw = (string) @file_get_contents($partFile);
        if ($raw === '') {
            return '';
        }

        $encoding = strtolower((string) ($trailer['encoding'] ?? ''));
        $payloadPos = strpos($raw, '-- NUMART_CHUNK_PAYLOAD');
        $endPos = strrpos($raw, '-- NUMART_CHUNK_END');

        if ($payloadPos !== false && $endPos !== false && $endPos > $payloadPos) {
            $afterHeader = strpos($raw, "\n", $payloadPos);
            if ($afterHeader === false) {
                return '';
            }
            $b64 = substr($raw, $afterHeader + 1, $endPos - ($afterHeader + 1));
            $decoded = base64_decode(preg_replace('/\s+/', '', $b64) ?? '', true);
            return is_string($decoded) ? $decoded : '';
        }

        if ($encoding === 'base64') {
            return '';
        }

        // Legacy: buang baris trailer/komentar protokol di akhir.
        if ($endPos !== false) {
            $raw = substr($raw, 0, $endPos);
        }
        return $raw;
    }
}

if (!function_exists('sync_db_run_http_sync_chunked')) {
    /**
     * Protokol v2: rakit dump dari banyak request kecil supaya batas memori dan
     * waktu eksekusi hosting tidak pernah tersentuh, dan potongan yang gagal
     * bisa diulang tanpa mengulang seluruh database.
     *
     * @param string[] $tables
     */
    function sync_db_run_http_sync_chunked(array $cfg, string $localDb, array $tables, array &$log): array
    {
        $base = sync_db_http_export_base_url($cfg);
        $chunkMaxRows = (int) ($cfg['http_chunk_max_rows'] ?? 40);
        $chunkMaxBytes = (int) ($cfg['http_chunk_max_bytes'] ?? 3072);
        $chunkMaxSeconds = (float) ($cfg['http_chunk_max_seconds'] ?? 5);
        // Amankan di bawah batas CDN ~50 KB sejak awal.
        $chunkMaxRows = max(1, min(80, $chunkMaxRows));
        $chunkMaxBytes = max(1024, min(8192, $chunkMaxBytes));
        $skipTables = sync_db_get_skip_tables($cfg);
        $cacheDir = sync_db_cache_dir();
        $dumpFile = $cacheDir . DIRECTORY_SEPARATOR . 'live_http_' . date('Ymd_His') . '.sql';
        $partFile = $dumpFile . '.part';

        $fh = fopen($dumpFile, 'wb');
        if ($fh === false) {
            return ['ok' => false, 'message' => 'Gagal membuat file cache dump.'];
        }

        $fail = static function (string $message) use ($fh, $dumpFile, $partFile): array {
            fclose($fh);
            sync_db_unlink_quiet($partFile);
            sync_db_unlink_quiet($dumpFile);
            return ['ok' => false, 'message' => $message];
        };

        fwrite($fh, '-- NUMART dump rakitan ' . date('c') . "\n");
        fwrite($fh, '-- NUMART_TABLE_COUNT: ' . count($tables) . "\n");
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        sync_db_log($log, 'Mengunduh struktur tabel...');
        $structBust = '&nocache=' . rawurlencode(uniqid((string) mt_rand(), true));
        $structRes = sync_db_fetch_part($base . '&phase=struct' . $structBust, $partFile, 'NUMART_STRUCT_END', $log);
        if (!$structRes['ok']) {
            return $fail('Gagal mengunduh struktur: ' . $structRes['message']);
        }

        /** DDL saja yang dinormalisasi — mengganti string di dalam data justru merusaknya. */
        $structSql = (string) @file_get_contents($partFile);
        if (stripos($structSql, 'CREATE TABLE') === false) {
            return $fail('Struktur dari live tidak berisi CREATE TABLE.');
        }
        fwrite($fh, sync_db_sanitize_sql_ddl($structSql));
        fwrite($fh, "\n");
        sync_db_log($log, 'Struktur diterima: ' . (int) ($structRes['trailer']['count'] ?? 0) . ' tabel.');

        $totalRows = 0;
        $totalBytes = 0;

        $tableIndex = 0;
        foreach ($tables as $table) {
            $tableIndex++;
            $cursor = '';
            $rowsTable = 0;
            $chunks = 0;
            $pagingMode = '';

            fwrite($fh, '-- TABLE_DATA: `' . str_replace('`', '``', $table) . "`\n");

            if (isset($skipTables[strtolower($table)])) {
                sync_db_log($log, sprintf(
                    'SKIP [%d/%d] %s — ada di skip_tables (struktur tetap, data kosong)',
                    $tableIndex,
                    count($tables),
                    $table
                ));
                fwrite($fh, "\n");
                continue;
            }

            $skippedAfterFail = false;
            $tableMaxRows = $chunkMaxRows;
            $tableMaxBytes = $chunkMaxBytes;
            $frag = 0;
            $pendingWideSql = '';
            while (true) {
                $part = sync_db_fetch_data_part(
                    $cfg,
                    $table,
                    $cursor,
                    $tableMaxRows,
                    $tableMaxBytes,
                    $chunkMaxSeconds,
                    $partFile,
                    $log,
                    $frag
                );
                if (!$part['ok']) {
                    // Auto-skip tabel log/riwayat jika terus gagal (bukan tabel transaksi inti)
                    $isSkipable = (bool) preg_match('/(_log|_history|audit_|attendance|wa_blast|wa_auto)/i', $table);
                    if ($isSkipable) {
                        sync_db_log($log, sprintf(
                            'SKIP [%d/%d] %s — gagal unduh, dilewati (%s)',
                            $tableIndex,
                            count($tables),
                            $table,
                            $part['message']
                        ));
                        $skippedAfterFail = true;
                        break;
                    }
                    return $fail('Gagal mengunduh data `' . $table . '`: ' . $part['message']);
                }

                $trailer = $part['trailer'];
                if (base64_decode((string) ($trailer['table'] ?? ''), true) !== $table) {
                    return $fail('Response live tidak cocok untuk tabel `' . $table . '`.');
                }

                $chunkSql = sync_db_extract_chunk_sql($partFile, $trailer);
                $more = (string) ($trailer['more'] ?? '0') === '1';
                if ($more) {
                    // Baris lebar dipecah: gabungkan frag SQL sebelum tulis ke dump.
                    $pendingWideSql .= $chunkSql;
                    $frag = (int) ($trailer['frag'] ?? $frag) + 1;
                    $next = rawurldecode((string) ($trailer['cursor'] ?? ''));
                    if ($next === '') {
                        return $fail('Fragment `' . $table . '` tanpa cursor.');
                    }
                    $cursor = $next;
                    $chunks++;
                    sync_db_log($log, sprintf(
                        'FRAG `%s` %d/%s — lanjut unduh potongan baris lebar',
                        $table,
                        $frag,
                        (string) ($trailer['frag_total'] ?? '?')
                    ));
                    if ($chunks > 100000) {
                        return $fail('Terlalu banyak potongan untuk tabel `' . $table . '`.');
                    }
                    continue;
                }

                if ($pendingWideSql !== '') {
                    $chunkSql = $pendingWideSql . $chunkSql;
                    $pendingWideSql = '';
                    $frag = 0;
                }

                if ($chunkSql !== '' && fwrite($fh, $chunkSql) === false) {
                    return $fail('Gagal menulis potongan `' . $table . '` ke dump.');
                }

                $rows = (int) ($trailer['rows'] ?? 0);
                $done = (string) ($trailer['done'] ?? '0') === '1';
                $next = rawurldecode((string) ($trailer['cursor'] ?? ''));
                $pagingMode = (string) ($trailer['mode'] ?? '');

                $rowsTable += $rows;
                $totalBytes += (int) ($trailer['bytes'] ?? 0);
                $chunks++;

                if ($done) {
                    break;
                }

                /** Penjaga: tanpa kemajuan cursor, loop tidak akan pernah berakhir. */
                if ($rows === 0 || $next === '' || ($next === $cursor && $frag === 0)) {
                    return $fail('Sinkron tabel `' . $table . '` mandek (cursor tidak bergerak).');
                }
                $cursor = $next;
                $frag = 0;

                if ($chunks > 100000) {
                    return $fail('Terlalu banyak potongan untuk tabel `' . $table . '`.');
                }
            }

            fwrite($fh, "\n");
            if ($skippedAfterFail) {
                continue;
            }
            $totalRows += $rowsTable;

            if ($pagingMode === 'scan' && $chunks > 1) {
                sync_db_log($log, 'WARN `' . $table . '` tidak punya primary key — urutan antar potongan tidak dijamin.');
            }

            sync_db_log($log, sprintf(
                'OK [%d/%d] %s — %s baris, %d potongan',
                $tableIndex,
                count($tables),
                $table,
                number_format($rowsTable),
                $chunks
            ));
        }

        fwrite($fh, "SET UNIQUE_CHECKS=1;\nSET FOREIGN_KEY_CHECKS=1;\n");
        fwrite($fh, '-- NUMART_EXPORT_END tables=' . count($tables) . "\n");
        fclose($fh);
        sync_db_unlink_quiet($partFile);

        sync_db_log($log, 'Unduhan selesai: ' . number_format($totalRows) . ' baris, '
            . number_format($totalBytes / 1048576, 1) . ' MB SQL.');

        $result = sync_db_import_sql_file($cfg, $localDb, $dumpFile, $log, true);
        if (!empty($result['ok'])) {
            sync_db_unlink_quiet($dumpFile);
        } else {
            sync_db_log($log, 'Dump disimpan untuk diperiksa: ' . $dumpFile);
        }

        return $result;
    }
}

if (!function_exists('sync_db_run_http_sync_single')) {
    /** Cadangan untuk endpoint live yang belum diperbarui ke protokol v2. */
    function sync_db_run_http_sync_single(array $cfg, string $localDb, array &$log): array
    {
        $dumpFile = sync_db_cache_dir() . DIRECTORY_SEPARATOR . 'live_http_' . date('Ymd_His') . '.sql';

        sync_db_log($log, 'Mengunduh dump dari live (mode satu request)...');

        $res = sync_db_download_to_file(sync_db_http_export_base_url($cfg), $dumpFile, 0);
        if (!$res['ok']) {
            sync_db_unlink_quiet($dumpFile);
            return ['ok' => false, 'message' => 'Unduh gagal: ' . $res['error']];
        }

        $result = sync_db_import_sql_file($cfg, $localDb, $dumpFile, $log);
        if (!empty($result['ok'])) {
            sync_db_unlink_quiet($dumpFile);
        } else {
            sync_db_log($log, 'Dump disimpan untuk diperiksa: ' . $dumpFile);
        }
        return $result;
    }
}

if (!function_exists('sync_db_run_http_sync')) {
    function sync_db_run_http_sync(array $cfg, string $localDb, array &$log): array
    {
        $url = trim((string) ($cfg['http_export_url'] ?? ''));
        $secret = trim((string) ($cfg['http_export_secret'] ?? ''));
        if ($url === '' || $secret === '') {
            return ['ok' => false, 'message' => 'Mode HTTP: isi http_export_url dan http_export_secret.'];
        }

        $test = sync_db_test_http($cfg);
        if (!$test['ok']) {
            return $test;
        }
        sync_db_log($log, $test['message']);

        $protocol = (int) ($test['protocol'] ?? 1);
        $tables = (array) ($test['tables'] ?? []);

        if ($protocol >= 2 && $tables !== []) {
            return sync_db_run_http_sync_chunked($cfg, $localDb, $tables, $log);
        }

        sync_db_log($log, 'Endpoint live masih protokol lama — upload ulang api/sync-db-export-live.php agar sync bertahap aktif.');
        return sync_db_run_http_sync_single($cfg, $localDb, $log);
    }
}

if (!function_exists('sync_db_get_mode')) {
    function sync_db_get_mode(array $cfg): string
    {
        $mode = strtolower(trim((string) ($cfg['sync_mode'] ?? 'mysql')));
        return $mode === 'http' ? 'http' : 'mysql';
    }
}

if (!function_exists('sync_db_test_connection')) {
    function sync_db_test_connection(array $cfg): array
    {
        if (sync_db_get_mode($cfg) === 'http') {
            return sync_db_test_http($cfg);
        }
        return sync_db_test_remote($cfg);
    }
}

if (!function_exists('sync_db_run_sync')) {
    function sync_db_run_sync(array $cfg, array &$log): array
    {
        global $conn;

        $localDb = sync_db_local_database_name($cfg, $conn);
        if ($localDb === '') {
            return ['ok' => false, 'message' => 'Nama database lokal tidak diketahui. Set local_database di config.'];
        }

        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        sync_db_log($log, 'Target lokal: ' . $localDb);
        $mode = sync_db_get_mode($cfg);
        $remoteHost = strtolower(trim((string) ($cfg['remote_host'] ?? '')));
        // demopos/posgit di Hostinger yang sama dengan pos: MySQL localhost jauh lebih andal dari HTTP/CDN.
        if ($mode === 'http' && in_array($remoteHost, ['localhost', '127.0.0.1', '::1'], true)) {
            $probe = sync_db_test_remote($cfg);
            if (!empty($probe['ok'])) {
                sync_db_log($log, 'Mode config=http, tapi remote_host=localhost — pakai MySQL langsung (hindari CDN).');
                $mode = 'mysql';
            }
        }
        sync_db_log($log, 'Mode: ' . $mode);

        if ($mode === 'http') {
            $result = sync_db_run_http_sync($cfg, $localDb, $log);
            $allowMysqlFallback = array_key_exists('mysql_fallback', $cfg)
                ? !empty($cfg['mysql_fallback'])
                : true;
            if (!$result['ok'] && $allowMysqlFallback) {
                $test = sync_db_test_remote($cfg);
                if ($test['ok']) {
                    sync_db_log($log, 'HTTP gagal (CDN/proxy). Fallback ke MySQL langsung...');
                    return sync_db_run_php_sync($cfg, $localDb, $log);
                }
                sync_db_log($log, 'MySQL fallback tidak tersedia: ' . ($test['message'] ?? ''));
            }
            return $result;
        }

        sync_db_log($log, 'Sumber live: ' . ($cfg['remote_database'] ?? '') . '@' . ($cfg['remote_host'] ?? ''));

        $test = sync_db_test_remote($cfg);
        if (!$test['ok']) {
            return $test;
        }
        sync_db_log($log, $test['message']);

        $preferDump = !empty($cfg['prefer_mysqldump']);
        if ($preferDump && PHP_OS_FAMILY === 'Windows') {
            $result = sync_db_run_mysqldump_sync($cfg, $localDb, $log);
            if ($result['ok']) {
                return $result;
            }
            sync_db_log($log, 'mysqldump gagal, fallback ke PHP: ' . ($result['message'] ?? ''));
        }

        return sync_db_run_php_sync($cfg, $localDb, $log);
    }
}
