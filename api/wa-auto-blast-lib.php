<?php
/**
 * WA blast otomatis via cron: antrian multi-hari, 20-30 kontak/jam, satu per satu + jeda acak.
 */

require_once __DIR__ . '/wa-send-lib.php';
require_once __DIR__ . '/wa-blast-lib.php';

if (!function_exists('wa_auto_blast_ensure_timezone')) {
    function wa_auto_blast_ensure_timezone($conn)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (date_default_timezone_get() !== 'Asia/Jakarta') {
            date_default_timezone_set('Asia/Jakarta');
        }
        if ($conn instanceof mysqli) {
            @mysqli_query($conn, "SET time_zone = '+07:00'");
        }
    }
}

if (!function_exists('wa_auto_blast_now_sql')) {
    function wa_auto_blast_now_sql()
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('wa_auto_blast_ensure_schema')) {
    function wa_auto_blast_ensure_schema($conn)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        wa_auto_blast_ensure_timezone($conn);

        require_once __DIR__ . '/wa-auto-schema.php';
        wa_auto_below_target_ensure_schema($conn);
        require_once __DIR__ . '/wa-send-settings-lib.php';
        wa_send_settings_ensure_schema($conn);

        $col = mysqli_query($conn, "SHOW COLUMNS FROM `wa_auto_target_reminder_settings` LIKE 'blast_mode'");
        if ($col && mysqli_num_rows($col) === 0) {
            mysqli_query(
                $conn,
                "ALTER TABLE `wa_auto_target_reminder_settings`
                 ADD COLUMN `blast_mode` varchar(20) NOT NULL DEFAULT 'below_target'
                 COMMENT 'below_target | all_valid' AFTER `message_template`"
            );
        }

        $cols = [
            'contacts_per_hour_min' => "int(11) NOT NULL DEFAULT 20 AFTER `delay_seconds_per_contact`",
            'contacts_per_hour_max' => "int(11) NOT NULL DEFAULT 30 AFTER `contacts_per_hour_min`",
            'delay_seconds_min' => "int(11) NOT NULL DEFAULT 90 AFTER `contacts_per_hour_max`",
            'delay_seconds_max' => "int(11) NOT NULL DEFAULT 180 AFTER `delay_seconds_min`",
            'dedup_days' => "int(11) NOT NULL DEFAULT 3 AFTER `delay_seconds_max`",
            'next_send_at' => "datetime DEFAULT NULL AFTER `last_send_at`",
        ];
        foreach ($cols as $name => $def) {
            $c = mysqli_query($conn, "SHOW COLUMNS FROM `wa_blast_send_settings` LIKE '$name'");
            if ($c && mysqli_num_rows($c) === 0) {
                mysqli_query($conn, "ALTER TABLE `wa_blast_send_settings` ADD COLUMN `$name` $def");
            }
        }

        mysqli_query(
            $conn,
            "CREATE TABLE IF NOT EXISTS `wa_auto_blast_hourly` (
              `cabang` int(11) NOT NULL,
              `hour_key` char(13) NOT NULL,
              `target_count` int(11) NOT NULL,
              `sent_count` int(11) NOT NULL DEFAULT 0,
              `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`cabang`,`hour_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        mysqli_query(
            $conn,
            "CREATE TABLE IF NOT EXISTS `wa_auto_blast_sent_log` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `cabang` int(11) NOT NULL,
              `customer_id` int(11) NOT NULL,
              `phone_key` varchar(20) NOT NULL,
              `period_yyyymm` char(7) NOT NULL,
              `blast_mode` varchar(20) NOT NULL DEFAULT 'below_target',
              `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_cabang_period_phone` (`cabang`,`period_yyyymm`,`phone_key`),
              KEY `idx_cabang_phone_sent` (`cabang`,`phone_key`,`sent_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        mysqli_query(
            $conn,
            "CREATE TABLE IF NOT EXISTS `wa_auto_blast_global` (
              `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
              `next_send_at` datetime DEFAULT NULL COMMENT 'Jeda antar kirim Fonnte (semua cabang)',
              `last_send_at` datetime DEFAULT NULL,
              `last_cabang` int(11) DEFAULT NULL COMMENT 'Round-robin cabang terakhir',
              `contacts_per_hour_min` int(11) NOT NULL DEFAULT 20,
              `contacts_per_hour_max` int(11) NOT NULL DEFAULT 30,
              `delay_seconds_min` int(11) NOT NULL DEFAULT 90,
              `delay_seconds_max` int(11) NOT NULL DEFAULT 180,
              `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        mysqli_query(
            $conn,
            "INSERT IGNORE INTO `wa_auto_blast_global` (`id`) VALUES (1)"
        );
    }
}

/** cabang=0 di wa_auto_blast_hourly = kuota per jam untuk satu perangkat Fonnte */
if (!defined('WA_AUTO_BLAST_GLOBAL_CABANG')) {
    define('WA_AUTO_BLAST_GLOBAL_CABANG', 0);
}

if (!function_exists('wa_auto_blast_global_get')) {
    /**
     * @return array{next_send_at: ?string, last_send_at: ?string, last_cabang: ?int}
     */
    function wa_auto_blast_global_get($conn)
    {
        wa_auto_blast_ensure_schema($conn);
        $res = mysqli_query($conn, 'SELECT * FROM wa_auto_blast_global WHERE id = 1 LIMIT 1');
        $row = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;
        if ($row === null) {
            return ['next_send_at' => null, 'last_send_at' => null, 'last_cabang' => null];
        }
        return [
            'next_send_at' => !empty($row['next_send_at']) ? (string) $row['next_send_at'] : null,
            'last_send_at' => !empty($row['last_send_at']) ? (string) $row['last_send_at'] : null,
            'last_cabang' => isset($row['last_cabang']) ? (int) $row['last_cabang'] : null,
        ];
    }
}

if (!function_exists('wa_auto_blast_global_sched_resolve')) {
    /**
     * Pengaturan pacing global (satu WA Fonnte untuk semua cabang).
     *
     * @return array{contacts_per_hour_min: int, contacts_per_hour_max: int, delay_seconds_min: int, delay_seconds_max: int}
     */
    function wa_auto_blast_global_sched_resolve($conn)
    {
        wa_auto_blast_ensure_schema($conn);
        $res = mysqli_query($conn, 'SELECT * FROM wa_auto_blast_global WHERE id = 1 LIMIT 1');
        $row = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;

        $minH = (int) ($row['contacts_per_hour_min'] ?? 20);
        $maxH = (int) ($row['contacts_per_hour_max'] ?? 30);
        $dMin = (int) ($row['delay_seconds_min'] ?? 90);
        $dMax = (int) ($row['delay_seconds_max'] ?? 180);

        $remRows = query('SELECT cabang FROM wa_auto_target_reminder_settings WHERE enabled = 1');
        foreach ($remRows as $rem) {
            $s = wa_auto_blast_scheduler_get($conn, (int) $rem['cabang']);
            $minH = min($minH, (int) $s['contacts_per_hour_min']);
            $maxH = min($maxH, (int) $s['contacts_per_hour_max']);
            $dMin = max($dMin, (int) $s['delay_seconds_min']);
            $dMax = max($dMax, (int) $s['delay_seconds_max']);
        }

        if ($minH > $maxH) {
            $t = $minH;
            $minH = $maxH;
            $maxH = $t;
        }
        $minH = max(1, min(30, $minH));
        $maxH = max($minH, min(30, $maxH));
        $dMin = max(30, min(600, $dMin));
        $dMax = max($dMin, min(600, $dMax));
    
        return [
            'contacts_per_hour_min' => $minH,
            'contacts_per_hour_max' => $maxH,
            'delay_seconds_min' => $dMin,
            'delay_seconds_max' => $dMax,
        ];
    }
}

if (!function_exists('wa_auto_blast_global_set_next_send_at')) {
    function wa_auto_blast_global_set_next_send_at($conn, array $globalSched, $cabang)
    {
        wa_auto_blast_ensure_schema($conn);
        $cabang = (int) $cabang;
        $delay = random_int((int) $globalSched['delay_seconds_min'], (int) $globalSched['delay_seconds_max']);
        $next = date('Y-m-d H:i:s', time() + $delay);
        $now = wa_auto_blast_now_sql();
        mysqli_query(
            $conn,
            "UPDATE wa_auto_blast_global SET
                next_send_at = '$next',
                last_send_at = '$now',
                last_cabang = $cabang
             WHERE id = 1"
        );
        return ['next_send_at' => $next, 'delay_seconds' => $delay];
    }
}

if (!function_exists('wa_auto_blast_global_wait_reason')) {
    function wa_auto_blast_global_wait_reason(array $global, array $globalHourly)
    {
        if ((int) $globalHourly['remaining'] <= 0) {
            return 'Kuota global engine WA jam ini sudah terpenuhi ('
                . (int) $globalHourly['sent_count'] . '/' . (int) $globalHourly['target_count']
                . '). Semua cabang menunggu jam berikutnya.';
        }

        $next = $global['next_send_at'] ?? null;
        if ($next !== null && $next !== '') {
            $nextTs = strtotime($next);
            if ($nextTs !== false && $nextTs > time()) {
                $wait = $nextTs - time();
                return 'Jeda global engine WA (satu perangkat untuk semua cabang): ~' . $wait . ' detik lagi.';
            }
        }

        return '';
    }
}

if (!function_exists('wa_auto_blast_round_robin_start_index')) {
    function wa_auto_blast_round_robin_start_index(array $remRows, $lastCabang)
    {
        $n = count($remRows);
        if ($n <= 1) {
            return 0;
        }
        $lastCabang = (int) $lastCabang;
        if ($lastCabang <= 0) {
            return 0;
        }
        for ($i = 0; $i < $n; $i++) {
            if ((int) ($remRows[$i]['cabang'] ?? 0) === $lastCabang) {
                return ($i + 1) % $n;
            }
        }
        return 0;
    }
}

if (!function_exists('wa_auto_blast_cron_should_try_next_cabang')) {
    function wa_auto_blast_cron_should_try_next_cabang(array $tick)
    {
        if (!empty($tick['sent']) || !empty($tick['error'])) {
            return false;
        }
        if (!empty($tick['skipped_not_started']) || !empty($tick['skipped_hourly_full']) || !empty($tick['skipped_wait'])) {
            return true;
        }
        $note = (string) ($tick['note'] ?? '');
        if ($note !== '' && strpos($note, 'Tidak ada customer') !== false) {
            return true;
        }
        return false;
    }
}

if (!function_exists('wa_auto_blast_cron_run')) {
    /**
     * Satu panggilan cron = maksimal 1 WA ke Fonnte, bergiliran antar cabang aktif.
     *
     * @return list<array<string, mixed>>
     */
    function wa_auto_blast_cron_run($conn, $dryRun = false)
    {
        wa_auto_blast_ensure_schema($conn);

        $remRows = query('SELECT * FROM wa_auto_target_reminder_settings WHERE enabled = 1 ORDER BY cabang ASC');
        if (empty($remRows)) {
            return [];
        }

        $global = wa_auto_blast_global_get($conn);
        $globalSched = wa_auto_blast_global_sched_resolve($conn);
        $globalHourly = wa_auto_blast_hourly_get($conn, WA_AUTO_BLAST_GLOBAL_CABANG, $globalSched);

        if (!$dryRun) {
            $globalWait = wa_auto_blast_global_wait_reason($global, $globalHourly);
            if ($globalWait !== '') {
                return [[
                    'cabang' => (int) ($global['last_cabang'] ?? 0),
                    'skipped_wait' => true,
                    'skipped_global' => true,
                    'global_hourly' => $globalHourly,
                    'global_next_send_at' => $global['next_send_at'],
                    'note' => $globalWait,
                ]];
            }
        }

        $startIdx = wa_auto_blast_round_robin_start_index($remRows, (int) ($global['last_cabang'] ?? 0));
        $results = [];
        $n = count($remRows);

        for ($i = 0; $i < $n; $i++) {
            $rem = $remRows[($startIdx + $i) % $n];
            $tick = wa_auto_blast_tick_cabang($conn, $rem, $dryRun, [
                'skip_global_wait' => true,
            ]);
            $results[] = $tick;

            if (!empty($tick['sent']) || !empty($tick['error'])) {
                break;
            }
            if (!wa_auto_blast_cron_should_try_next_cabang($tick)) {
                break;
            }
        }

        return $results;
    }
}

if (!function_exists('wa_auto_blast_phone_key')) {
    function wa_auto_blast_phone_key($raw)
    {
        if (function_exists('wa_blast_phone_key')) {
            return wa_blast_phone_key($raw);
        }
        $p = preg_replace('/^0/', '62', (string) $raw);
        return preg_replace('/[^0-9]/', '', $p);
    }
}

if (!function_exists('wa_auto_blast_phone_is_valid')) {
    function wa_auto_blast_phone_is_valid($normalizedDigits)
    {
        $normalizedDigits = (string) $normalizedDigits;
        if ($normalizedDigits === '') {
            return false;
        }
        $len = strlen($normalizedDigits);
        if ($len < 11 || $len > 15) {
            return false;
        }
        if (strpos($normalizedDigits, '62') !== 0) {
            return false;
        }
        if (!isset($normalizedDigits[2]) || $normalizedDigits[2] !== '8') {
            return false;
        }
        return (bool) preg_match('/^62[0-9]{9,13}$/', $normalizedDigits);
    }
}

if (!function_exists('wa_auto_blast_scheduler_get')) {
    /**
     * @return array{contacts_per_hour_min: int, contacts_per_hour_max: int, delay_seconds_min: int, delay_seconds_max: int, dedup_days: int, next_send_at: ?string}
     */
    function wa_auto_blast_scheduler_get($conn, $cabang)
    {
        wa_auto_blast_ensure_schema($conn);
        $s = wa_send_settings_get($conn, (int) $cabang);
        $res = mysqli_query($conn, "SELECT * FROM wa_blast_send_settings WHERE cabang = " . (int) $cabang . " LIMIT 1");
        $row = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;
        if ($row === null) {
            $res = mysqli_query($conn, "SELECT * FROM wa_blast_send_settings WHERE cabang = 0 LIMIT 1");
            $row = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;
        }

        $minH = (int) ($row['contacts_per_hour_min'] ?? 20);
        $maxH = (int) ($row['contacts_per_hour_max'] ?? 30);
        if ($minH > $maxH) {
            $t = $minH;
            $minH = $maxH;
            $maxH = $t;
        }
        $minH = max(1, min(30, $minH));
        $maxH = max($minH, min(30, $maxH));

        $dMin = (int) ($row['delay_seconds_min'] ?? 90);
        $dMax = (int) ($row['delay_seconds_max'] ?? 180);
        if ($dMin > $dMax) {
            $t = $dMin;
            $dMin = $dMax;
            $dMax = $t;
        }
        $dMin = max(30, min(600, $dMin));
        $dMax = max($dMin, min(600, $dMax));

        return [
            'contacts_per_hour_min' => $minH,
            'contacts_per_hour_max' => $maxH,
            'delay_seconds_min' => $dMin,
            'delay_seconds_max' => $dMax,
            'dedup_days' => max(2, min(7, (int) ($row['dedup_days'] ?? 3))),
            'next_send_at' => !empty($row['next_send_at']) ? (string) $row['next_send_at'] : null,
        ];
    }
}

if (!function_exists('wa_auto_blast_hour_key')) {
    function wa_auto_blast_hour_key()
    {
        return date('Y-m-d H');
    }
}

if (!function_exists('wa_auto_blast_hourly_get')) {
    /**
     * @return array{hour_key: string, target_count: int, sent_count: int, remaining: int}
     */
    function wa_auto_blast_hourly_get($conn, $cabang, array $sched)
    {
        wa_auto_blast_ensure_schema($conn);
        $cabang = (int) $cabang;
        $hourKey = wa_auto_blast_hour_key();
        $hourEsc = mysqli_real_escape_string($conn, $hourKey);
        $res = mysqli_query($conn, "SELECT * FROM wa_auto_blast_hourly WHERE cabang = $cabang AND hour_key = '$hourEsc' LIMIT 1");

        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            $target = (int) $row['target_count'];
            $sent = (int) $row['sent_count'];
        } else {
            $minH = (int) $sched['contacts_per_hour_min'];
            $maxH = (int) $sched['contacts_per_hour_max'];
            $target = random_int($minH, $maxH);
            mysqli_query(
                $conn,
                "INSERT INTO wa_auto_blast_hourly (cabang, hour_key, target_count, sent_count)
                 VALUES ($cabang, '$hourEsc', $target, 0)"
            );
            $sent = 0;
        }

        return [
            'hour_key' => $hourKey,
            'target_count' => $target,
            'sent_count' => $sent,
            'remaining' => max(0, $target - $sent),
        ];
    }
}

if (!function_exists('wa_auto_blast_hourly_increment')) {
    function wa_auto_blast_hourly_increment($conn, $cabang)
    {
        $cabang = (int) $cabang;
        $hourEsc = mysqli_real_escape_string($conn, wa_auto_blast_hour_key());
        mysqli_query(
            $conn,
            "UPDATE wa_auto_blast_hourly SET sent_count = sent_count + 1 WHERE cabang = $cabang AND hour_key = '$hourEsc'"
        );
    }
}

if (!function_exists('wa_auto_blast_set_next_send_at')) {
    function wa_auto_blast_set_next_send_at($conn, $cabang, array $sched)
    {
        $cabang = (int) $cabang;
        $delay = random_int((int) $sched['delay_seconds_min'], (int) $sched['delay_seconds_max']);
        $next = date('Y-m-d H:i:s', time() + $delay);

        $chk = mysqli_query($conn, "SELECT cabang FROM wa_blast_send_settings WHERE cabang = $cabang LIMIT 1");
        if ($chk && mysqli_num_rows($chk) > 0) {
            $now = wa_auto_blast_now_sql();
            mysqli_query($conn, "UPDATE wa_blast_send_settings SET next_send_at = '$next', last_send_at = '$now' WHERE cabang = $cabang");
        } else {
            $s = wa_send_settings_get($conn, $cabang);
            $max = (int) $s['max_contacts_per_batch'];
            $min = (int) $s['min_interval_minutes'];
            $dpc = (int) $s['delay_seconds_per_contact'];
            mysqli_query(
                $conn,
                "INSERT INTO wa_blast_send_settings (cabang, max_contacts_per_batch, min_interval_minutes, delay_seconds_per_contact, last_send_at, next_send_at)
                 VALUES ($cabang, $max, $min, $dpc, '" . wa_auto_blast_now_sql() . "', '$next')"
            );
        }

        return ['next_send_at' => $next, 'delay_seconds' => $delay];
    }
}

if (!function_exists('wa_auto_blast_wait_reason')) {
    function wa_auto_blast_wait_reason($conn, $cabang, array $sched, array $hourly, array $options = [])
    {
        $skipGlobal = !empty($options['skip_global_wait']);

        if (!$skipGlobal) {
            $global = wa_auto_blast_global_get($conn);
            $globalSched = wa_auto_blast_global_sched_resolve($conn);
            $globalHourly = wa_auto_blast_hourly_get($conn, WA_AUTO_BLAST_GLOBAL_CABANG, $globalSched);
            $globalWait = wa_auto_blast_global_wait_reason($global, $globalHourly);
            if ($globalWait !== '') {
                return $globalWait;
            }
        }

        if ((int) $hourly['remaining'] <= 0) {
            return 'Kuota jam cabang ini sudah terpenuhi (' . $hourly['sent_count'] . '/' . $hourly['target_count'] . '). Lanjut jam berikutnya.';
        }

        $next = $sched['next_send_at'];
        if ($next !== null && $next !== '') {
            $nextTs = strtotime($next);
            if ($nextTs !== false && $nextTs > time()) {
                $wait = $nextTs - time();
                return 'Menunggu jeda cabang (~' . $wait . ' detik lagi).';
            }
        }

        return '';
    }
}

if (!function_exists('wa_auto_blast_load_block_sets')) {
    /**
     * Muat sekali data blokir bulan + dedup (hindari ribuan query per cron tick).
     *
     * @return array{month_phones: array<string, true>, recent_phones: array<string, string>, legacy_customers: array<int, true>}
     */
    function wa_auto_blast_load_block_sets($conn, $cabang, $period, $dedupDays, $blastMode = 'below_target')
    {
        $cabang = (int) $cabang;
        $periodEsc = mysqli_real_escape_string($conn, $period);
        $since = date('Y-m-d H:i:s', strtotime('-' . (int) $dedupDays . ' days'));

        $monthPhones = [];
        foreach (query("SELECT phone_key FROM wa_auto_blast_sent_log
            WHERE cabang = $cabang AND period_yyyymm = '$periodEsc'") as $row) {
            $monthPhones[(string) $row['phone_key']] = true;
        }

        $recentPhones = [];
        foreach (query("SELECT phone_key, MAX(sent_at) AS last_sent FROM wa_auto_blast_sent_log
            WHERE cabang = $cabang AND sent_at >= '$since'
            GROUP BY phone_key") as $row) {
            $recentPhones[(string) $row['phone_key']] = (string) $row['last_sent'];
        }

        $legacyCustomers = [];
        if ($blastMode === 'below_target') {
            foreach (query("SELECT customer_id FROM wa_auto_below_target_sent
                WHERE cabang = $cabang AND period_yyyymm = '$periodEsc'") as $row) {
                $legacyCustomers[(int) $row['customer_id']] = true;
            }
        }

        return [
            'month_phones' => $monthPhones,
            'recent_phones' => $recentPhones,
            'legacy_customers' => $legacyCustomers,
        ];
    }
}

if (!function_exists('wa_auto_blast_phone_blocked_sets')) {
    function wa_auto_blast_phone_blocked_sets($phoneKey, array $blockSets, $dedupDays)
    {
        $phoneKey = (string) $phoneKey;
        if ($phoneKey === '') {
            return true;
        }
        if (!empty($blockSets['month_phones'][$phoneKey])) {
            return true;
        }
        if (!empty($blockSets['recent_phones'][$phoneKey])) {
            return true;
        }
        return false;
    }
}

if (!function_exists('wa_auto_blast_phone_blocked')) {
    function wa_auto_blast_phone_blocked($conn, $cabang, $phoneKey, $period, $dedupDays)
    {
        $sets = wa_auto_blast_load_block_sets($conn, (int) $cabang, $period, $dedupDays);
        return wa_auto_blast_phone_blocked_sets((string) $phoneKey, $sets, (int) $dedupDays);
    }
}

if (!function_exists('wa_auto_blast_campaign_started')) {
    function wa_auto_blast_campaign_started($conn, $cabang, $period)
    {
        $cabang = (int) $cabang;
        $periodEsc = mysqli_real_escape_string($conn, $period);
        $rows = query("SELECT id FROM wa_auto_blast_sent_log WHERE cabang = $cabang AND period_yyyymm = '$periodEsc' LIMIT 1");
        return !empty($rows);
    }
}

if (!function_exists('wa_auto_blast_fetch_candidates')) {
  /**
   * @return list<array<string, mixed>>
   */
    function wa_auto_blast_fetch_candidates($conn, $cabang, $blastMode, $targetBulan)
    {
        $cabang = (int) $cabang;
        $startOfMonth = date('Y-m-01');
        $endOfMonth = date('Y-m-t');

        if ($blastMode === 'all_valid') {
            $q = "SELECT c.customer_id, c.customer_nama, c.customer_tlpn, 0 AS total_belanja
                  FROM customer c
                  WHERE c.customer_cabang = $cabang
                    AND c.customer_id > 1
                    AND c.customer_nama != 'Customer Umum'
                    AND c.customer_status = '1'
                    AND c.customer_tlpn IS NOT NULL
                    AND TRIM(c.customer_tlpn) != ''
                  ORDER BY c.customer_id ASC";
            return query($q);
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
        return query($q);
    }
}

if (!function_exists('wa_auto_blast_pick_next')) {
    function wa_auto_blast_pick_next($conn, $cabang, $blastMode, $targetBulan, $period, $dedupDays, array $blockSets = null)
    {
        $candidates = wa_auto_blast_fetch_candidates($conn, $cabang, $blastMode, $targetBulan);
        $cabang = (int) $cabang;
        if ($blockSets === null) {
            $blockSets = wa_auto_blast_load_block_sets($conn, $cabang, $period, $dedupDays, $blastMode);
        }

        foreach ($candidates as $c) {
            $phoneKey = wa_auto_blast_phone_key($c['customer_tlpn'] ?? '');
            if (!wa_auto_blast_phone_is_valid($phoneKey)) {
                continue;
            }
            if (wa_auto_blast_phone_blocked_sets($phoneKey, $blockSets, $dedupDays)) {
                continue;
            }

            $cid = (int) $c['customer_id'];
            if ($blastMode === 'below_target' && !empty($blockSets['legacy_customers'][$cid])) {
                continue;
            }

            $phone = wa_normalize_id_phone((string) $c['customer_tlpn']);
            if ($phone === '') {
                continue;
            }

            return [
                'customer_id' => $cid,
                'customer_nama' => (string) $c['customer_nama'],
                'customer_tlpn' => (string) $c['customer_tlpn'],
                'phone_key' => $phoneKey,
                'target' => $phone,
                'total_belanja' => (float) ($c['total_belanja'] ?? 0),
            ];
        }

        return null;
    }
}

if (!function_exists('wa_auto_blast_build_message')) {
    function wa_auto_blast_build_message($tpl, array $customer, $tokoNama, $targetBulan, $blastMode)
    {
        $total = (float) ($customer['total_belanja'] ?? 0);
        $kurang = max(0, $targetBulan - $total);

        if ($blastMode === 'all_valid') {
            $tplDefault = "Halo {nama_customer},\n\n"
                . "Terima kasih telah menjadi pelanggan setia {nama_toko}. "
                . "Kami informasikan promo dan layanan terbaru untuk Anda.\n\n"
                . "Silakan berkunjung kembali. Terima kasih.";
        } else {
            $tplDefault = "Halo {nama_customer},\n\n"
                . "Total belanja Anda bulan ini {total_belanja} belum mencapai target minimum {target} di {nama_toko}. "
                . "Masih kurang {kurang}.\n\n"
                . "Silakan berkunjung kembali. Terima kasih.";
        }

        $tpl = trim((string) $tpl);
        if ($tpl === '') {
            $tpl = $tplDefault;
        }

        return str_replace(
            ['{nama_customer}', '{total_belanja}', '{nama_toko}', '{target}', '{kurang}'],
            [
                $customer['customer_nama'],
                'Rp ' . number_format($total, 0, ',', '.'),
                $tokoNama,
                'Rp ' . number_format($targetBulan, 0, ',', '.'),
                'Rp ' . number_format($kurang, 0, ',', '.'),
            ],
            $tpl
        );
    }
}

if (!function_exists('wa_auto_blast_mark_sent')) {
    function wa_auto_blast_mark_sent($conn, $cabang, $customerId, $phoneKey, $period, $blastMode)
    {
        $cabang = (int) $cabang;
        $customerId = (int) $customerId;
        $phoneEsc = mysqli_real_escape_string($conn, $phoneKey);
        $periodEsc = mysqli_real_escape_string($conn, $period);
        $modeEsc = mysqli_real_escape_string($conn, $blastMode);

        $sentAt = wa_auto_blast_now_sql();
        mysqli_query(
            $conn,
            "INSERT INTO wa_auto_blast_sent_log (cabang, customer_id, phone_key, period_yyyymm, blast_mode, sent_at)
             VALUES ($cabang, $customerId, '$phoneEsc', '$periodEsc', '$modeEsc', '$sentAt')"
        );

        if ($blastMode === 'below_target') {
            mysqli_query(
                $conn,
                "INSERT IGNORE INTO wa_auto_below_target_sent (cabang, customer_id, period_yyyymm)
                 VALUES ($cabang, $customerId, '$periodEsc')"
            );
        }
    }
}

if (!function_exists('wa_auto_blast_count_pending')) {
    function wa_auto_blast_count_pending($conn, $cabang, $blastMode, $targetBulan, $period, $dedupDays, array $blockSets = null)
    {
        $count = 0;
        $candidates = wa_auto_blast_fetch_candidates($conn, $cabang, $blastMode, $targetBulan);
        $cabang = (int) $cabang;
        if ($blockSets === null) {
            $blockSets = wa_auto_blast_load_block_sets($conn, $cabang, $period, $dedupDays, $blastMode);
        }

        foreach ($candidates as $c) {
            $phoneKey = wa_auto_blast_phone_key($c['customer_tlpn'] ?? '');
            if (!wa_auto_blast_phone_is_valid($phoneKey)) {
                continue;
            }
            if (wa_auto_blast_phone_blocked_sets($phoneKey, $blockSets, $dedupDays)) {
                continue;
            }
            if ($blastMode === 'below_target' && !empty($blockSets['legacy_customers'][(int) $c['customer_id']])) {
                continue;
            }
            $count++;
        }
        return $count;
    }
}

if (!function_exists('wa_auto_blast_monitor_target_bulan')) {
    function wa_auto_blast_monitor_target_bulan($conn, $cabang)
    {
        $cabang = (int) $cabang;
        $targetRows = query("SELECT * FROM customer_target_settings WHERE cabang = $cabang");
        if (empty($targetRows)) {
            $targetRows = query("SELECT * FROM customer_target_settings WHERE cabang = 0");
        }
        if (!empty($targetRows)) {
            return (float) ($targetRows[0]['target_bulanan'] ?? 100000);
        }
        return 100000.0;
    }
}

if (!function_exists('wa_auto_blast_monitor_sent_rows')) {
    /**
     * @return list<array<string, mixed>>
     */
    function wa_auto_blast_monitor_sent_rows($conn, $cabang, $period)
    {
        wa_auto_blast_ensure_schema($conn);
        $cabang = (int) $cabang;
        $periodEsc = mysqli_real_escape_string($conn, $period);

        return query("SELECT
                l.id,
                l.customer_id,
                l.phone_key,
                l.sent_at,
                l.blast_mode,
                c.customer_nama,
                c.customer_tlpn
            FROM wa_auto_blast_sent_log l
            LEFT JOIN customer c ON c.customer_id = l.customer_id
            WHERE l.cabang = $cabang AND l.period_yyyymm = '$periodEsc'
            ORDER BY l.sent_at DESC");
    }
}

if (!function_exists('wa_auto_blast_monitor_contact_rows')) {
    /**
     * Klasifikasi semua kandidat untuk halaman monitor.
     *
     * @return array{
     *   sent: list<array<string, mixed>>,
     *   pending: list<array<string, mixed>>,
     *   invalid: list<array<string, mixed>>,
     *   skipped_dedup: list<array<string, mixed>>
     * }
     */
    function wa_auto_blast_monitor_contact_rows(
        $conn,
        $cabang,
        $blastMode,
        $targetBulan,
        $period,
        $dedupDays
    ) {
        wa_auto_blast_ensure_schema($conn);
        $cabang = (int) $cabang;
        $dedupDays = (int) $dedupDays;

        $sentMap = [];
        foreach (wa_auto_blast_monitor_sent_rows($conn, $cabang, $period) as $row) {
            $sentMap[(int) $row['customer_id']] = $row;
        }

        $blockSets = wa_auto_blast_load_block_sets($conn, $cabang, $period, $dedupDays, $blastMode);
        $recentPhones = $blockSets['recent_phones'];

        $sent = [];
        $pending = [];
        $invalid = [];
        $skippedDedup = [];

        $candidates = wa_auto_blast_fetch_candidates($conn, $cabang, $blastMode, $targetBulan);
        foreach ($candidates as $c) {
            $cid = (int) $c['customer_id'];
            $phoneKey = wa_auto_blast_phone_key($c['customer_tlpn'] ?? '');
            $row = [
                'customer_id' => $cid,
                'customer_nama' => (string) ($c['customer_nama'] ?? ''),
                'customer_tlpn' => (string) ($c['customer_tlpn'] ?? ''),
                'phone_key' => $phoneKey,
                'total_belanja' => (float) ($c['total_belanja'] ?? 0),
            ];

            if (isset($sentMap[$cid])) {
                $row['sent_at'] = $sentMap[$cid]['sent_at'];
                $sent[] = $row;
                continue;
            }

            if ($blastMode === 'below_target' && !empty($blockSets['legacy_customers'][$cid])) {
                $row['sent_at'] = null;
                $row['note'] = 'Tercatat terkirim (log lama)';
                $sent[] = $row;
                continue;
            }

            if (!wa_auto_blast_phone_is_valid($phoneKey)) {
                $invalid[] = $row;
                continue;
            }

            if (wa_auto_blast_phone_blocked_sets($phoneKey, $blockSets, $dedupDays)) {
                if (isset($recentPhones[$phoneKey])) {
                    $row['last_sent_at'] = $recentPhones[$phoneKey];
                    $row['note'] = 'Tunggu jeda dedup (' . $dedupDays . ' hari)';
                    $skippedDedup[] = $row;
                }
                continue;
            }

            $pending[] = $row;
        }

        usort($sent, static function ($a, $b) {
            return strcmp((string) ($b['sent_at'] ?? ''), (string) ($a['sent_at'] ?? ''));
        });

        return [
            'sent' => $sent,
            'pending' => $pending,
            'invalid' => $invalid,
            'skipped_dedup' => $skippedDedup,
        ];
    }
}

if (!function_exists('wa_auto_blast_monitor_hourly_recent')) {
    /**
     * @return list<array<string, mixed>>
     */
    function wa_auto_blast_monitor_hourly_recent($conn, $cabang, $limit = 24)
    {
        wa_auto_blast_ensure_schema($conn);
        $cabang = (int) $cabang;
        $limit = max(1, min(72, (int) $limit));

        return query("SELECT hour_key, target_count, sent_count, updated_at
            FROM wa_auto_blast_hourly
            WHERE cabang = $cabang
            ORDER BY hour_key DESC
            LIMIT $limit");
    }
}

if (!function_exists('wa_auto_blast_monitor_cron_health')) {
    /**
     * @param array<string, mixed> $remRow
     * @return array<string, mixed>
     */
    function wa_auto_blast_monitor_cron_health($conn, $cabang, array $remRow, array $sched, array $hourly, $pendingCount)
    {
        $enabled = !empty($remRow['enabled']);
        $sendDay = max(1, min(28, (int) ($remRow['send_day'] ?? 26)));
        $period = date('Y-m');
        $todayDom = (int) date('j');
        $started = wa_auto_blast_campaign_started($conn, (int) $cabang, $period);
        $wait = wa_auto_blast_wait_reason($conn, (int) $cabang, $sched, $hourly);

        $global = wa_auto_blast_global_get($conn);
        $globalSched = wa_auto_blast_global_sched_resolve($conn);
        $globalHourly = wa_auto_blast_hourly_get($conn, WA_AUTO_BLAST_GLOBAL_CABANG, $globalSched);

        $logRows = query("SELECT MAX(sent_at) AS last_log FROM wa_auto_blast_sent_log WHERE cabang = " . (int) $cabang);
        $lastLogAt = !empty($logRows[0]['last_log']) ? (string) $logRows[0]['last_log'] : null;

        $lastActivity = $global['last_send_at'];
        if ($lastLogAt !== null && ($lastActivity === null || strtotime($lastLogAt) > strtotime($lastActivity))) {
            $lastActivity = $lastLogAt;
        }

        $campaignPhase = 'idle';
        $campaignLabel = 'Menunggu';
        if (!$enabled) {
            $campaignPhase = 'disabled';
            $campaignLabel = 'Nonaktif';
        } elseif (!$started && $todayDom < $sendDay) {
            $campaignPhase = 'scheduled';
            $campaignLabel = 'Belum mulai (tanggal ' . $sendDay . ')';
        } elseif ((int) $pendingCount <= 0) {
            $campaignPhase = 'completed';
            $campaignLabel = 'Antrian bulan ini selesai';
        } else {
            $campaignPhase = 'running';
            $campaignLabel = 'Sedang berjalan';
        }

        $cronState = 'unknown';
        $cronLabel = 'Tidak diketahui';
        $cronHint = '';

        if (!$enabled) {
            $cronState = 'off';
            $cronLabel = 'Cron tidak diperlukan (blast nonaktif)';
        } elseif ($campaignPhase === 'scheduled') {
            $cronState = 'scheduled';
            $cronLabel = 'Menunggu tanggal mulai';
            $cronHint = 'Cron boleh dijalankan; pengiriman dimulai tanggal ' . $sendDay . '.';
        } elseif ($campaignPhase === 'completed') {
            $cronState = 'ok';
            $cronLabel = 'Selesai bulan ini';
        } elseif ($wait !== '') {
            $cronState = 'waiting';
            $cronLabel = 'Menunggu jadwal';
            $cronHint = $wait;
        } elseif ($lastActivity === null) {
            $cronState = 'warn';
            $cronLabel = 'Belum ada pengiriman tercatat';
            $cronHint = 'Pastikan cron memanggil URL setiap 2–3 menit dan blast sudah aktif.';
        } else {
            $ageSec = time() - strtotime($lastActivity);
            $nextTs = !empty($sched['next_send_at']) ? strtotime((string) $sched['next_send_at']) : false;
            $nextInFuture = $nextTs !== false && $nextTs > time();

            if ($ageSec <= 900 || $nextInFuture) {
                $cronState = 'ok';
                $cronLabel = 'Cron terlihat aktif';
                $cronHint = 'Aktivitas terakhir: ' . $lastActivity;
            } elseif ($ageSec <= 3600) {
                $cronState = 'idle';
                $cronLabel = 'Jeda normal';
                $cronHint = 'Terakhir kirim ' . round($ageSec / 60) . ' menit lalu. Mungkin menunggu kuota jam atau jeda antar nomor.';
            } else {
                $cronState = 'warn';
                $cronLabel = 'Perlu dicek';
                $cronHint = 'Tidak ada pengiriman sejak ' . $lastActivity . ' padahal masih ada antrian. Periksa cron server.';
            }
        }

        return [
            'enabled' => $enabled,
            'campaign_phase' => $campaignPhase,
            'campaign_label' => $campaignLabel,
            'cron_state' => $cronState,
            'cron_label' => $cronLabel,
            'cron_hint' => $cronHint,
            'last_activity_at' => $lastActivity,
            'last_log_at' => $lastLogAt,
            'next_send_at' => $global['next_send_at'] ?? ($sched['next_send_at'] ?? null),
            'last_global_cabang' => $global['last_cabang'] ?? null,
            'wait_reason' => $wait,
            'hourly' => $hourly,
            'global_hourly' => $globalHourly,
        ];
    }
}

if (!function_exists('wa_auto_blast_monitor_snapshot')) {
    /**
     * @return array<string, mixed>
     */
    function wa_auto_blast_monitor_snapshot($conn, $cabang, $period = null)
    {
        wa_auto_blast_ensure_schema($conn);
        $cabang = (int) $cabang;
        $period = $period !== null && preg_match('/^\d{4}-\d{2}$/', (string) $period)
            ? (string) $period
            : date('Y-m');

        $remRows = query("SELECT * FROM wa_auto_target_reminder_settings WHERE cabang = $cabang LIMIT 1");
        $rem = !empty($remRows) ? $remRows[0] : [
            'enabled' => 0,
            'send_day' => 26,
            'blast_mode' => 'below_target',
            'message_template' => null,
        ];

        $blastMode = strtolower(trim((string) ($rem['blast_mode'] ?? 'below_target')));
        if (!in_array($blastMode, ['below_target', 'all_valid'], true)) {
            $blastMode = 'below_target';
        }

        $targetBulan = wa_auto_blast_monitor_target_bulan($conn, $cabang);
        $sched = wa_auto_blast_scheduler_get($conn, $cabang);
        $hourly = wa_auto_blast_hourly_get($conn, $cabang, $sched);
        $contacts = wa_auto_blast_monitor_contact_rows(
            $conn,
            $cabang,
            $blastMode,
            $targetBulan,
            $period,
            (int) $sched['dedup_days']
        );

        $health = wa_auto_blast_monitor_cron_health(
            $conn,
            $cabang,
            $rem,
            $sched,
            $hourly,
            count($contacts['pending'])
        );

        $sentLogRows = wa_auto_blast_monitor_sent_rows($conn, $cabang, $period);
        $belanjaMap = [];
        foreach (wa_auto_blast_fetch_candidates($conn, $cabang, $blastMode, $targetBulan) as $c) {
            $belanjaMap[(int) $c['customer_id']] = (float) ($c['total_belanja'] ?? 0);
        }
        $sentFromLog = [];
        foreach ($sentLogRows as $logRow) {
            $cid = (int) ($logRow['customer_id'] ?? 0);
            $sentFromLog[] = [
                'customer_id' => $cid,
                'customer_nama' => (string) ($logRow['customer_nama'] ?? ''),
                'customer_tlpn' => (string) ($logRow['customer_tlpn'] ?? ''),
                'phone_key' => (string) ($logRow['phone_key'] ?? ''),
                'sent_at' => (string) ($logRow['sent_at'] ?? ''),
                'total_belanja' => $belanjaMap[$cid] ?? 0.0,
            ];
        }

        return [
            'period' => $period,
            'period_label' => date('F Y', strtotime($period . '-01')),
            'reminder' => $rem,
            'blast_mode' => $blastMode,
            'blast_mode_label' => $blastMode === 'all_valid'
                ? 'Semua customer valid'
                : 'Belum capai target bulanan',
            'target_bulanan' => $targetBulan,
            'scheduler' => $sched,
            'health' => $health,
            'counts' => [
                'sent' => count($sentFromLog),
                'pending' => count($contacts['pending']),
                'invalid' => count($contacts['invalid']),
                'skipped_dedup' => count($contacts['skipped_dedup']),
                'total_candidates' => count($contacts['pending'])
                    + count($contacts['invalid'])
                    + count($contacts['skipped_dedup'])
                    + count($sentFromLog),
            ],
            'contacts' => [
                'sent' => $sentFromLog,
                'pending' => $contacts['pending'],
                'invalid' => $contacts['invalid'],
                'skipped_dedup' => $contacts['skipped_dedup'],
            ],
            'hourly_recent' => wa_auto_blast_monitor_hourly_recent($conn, $cabang, 24),
        ];
    }
}

if (!function_exists('wa_auto_blast_save_scheduler')) {
    function wa_auto_blast_save_scheduler(
        $conn,
        $cabang,
        $contactsPerHourMin,
        $contactsPerHourMax,
        $delaySecondsMin,
        $delaySecondsMax,
        $dedupDays
    ) {
        wa_auto_blast_ensure_schema($conn);
        $cabang = (int) $cabang;

        $minH = max(1, min(30, (int) $contactsPerHourMin));
        $maxH = max(1, min(30, (int) $contactsPerHourMax));
        if ($minH > $maxH) {
            $t = $minH;
            $minH = $maxH;
            $maxH = $t;
        }

        $dMin = max(30, min(600, (int) $delaySecondsMin));
        $dMax = max(30, min(600, (int) $delaySecondsMax));
        if ($dMin > $dMax) {
            $t = $dMin;
            $dMin = $dMax;
            $dMax = $t;
        }

        $dedup = max(2, min(7, (int) $dedupDays));

        $chk = mysqli_query($conn, "SELECT cabang FROM wa_blast_send_settings WHERE cabang = $cabang LIMIT 1");
        if ($chk && mysqli_num_rows($chk) > 0) {
            mysqli_query(
                $conn,
                "UPDATE wa_blast_send_settings SET
                    contacts_per_hour_min = $minH,
                    contacts_per_hour_max = $maxH,
                    delay_seconds_min = $dMin,
                    delay_seconds_max = $dMax,
                    dedup_days = $dedup
                 WHERE cabang = $cabang"
            );
        } else {
            $s = wa_send_settings_get($conn, $cabang);
            $max = (int) $s['max_contacts_per_batch'];
            $min = (int) $s['min_interval_minutes'];
            $dpc = (int) $s['delay_seconds_per_contact'];
            mysqli_query(
                $conn,
                "INSERT INTO wa_blast_send_settings (
                    cabang, max_contacts_per_batch, min_interval_minutes, delay_seconds_per_contact,
                    contacts_per_hour_min, contacts_per_hour_max, delay_seconds_min, delay_seconds_max, dedup_days
                 ) VALUES ($cabang, $max, $min, $dpc, $minH, $maxH, $dMin, $dMax, $dedup)"
            );
        }

        return [
            'contacts_per_hour_min' => $minH,
            'contacts_per_hour_max' => $maxH,
            'delay_seconds_min' => $dMin,
            'delay_seconds_max' => $dMax,
            'dedup_days' => $dedup,
        ];
    }
}

if (!function_exists('wa_auto_blast_tick_cabang')) {
    /**
     * Satu langkah cron: kirim maksimal 1 WA jika lolos aturan.
     *
     * @param array<string, mixed> $remRow
     * @return array<string, mixed>
     */
    function wa_auto_blast_tick_cabang($conn, array $remRow, $dryRun = false, array $options = [])
    {
        wa_auto_blast_ensure_schema($conn);

        $cabang = (int) $remRow['cabang'];
        $sendDay = max(1, min(28, (int) ($remRow['send_day'] ?? 26)));
        $blastMode = strtolower(trim((string) ($remRow['blast_mode'] ?? 'below_target')));
        if (!in_array($blastMode, ['below_target', 'all_valid'], true)) {
            $blastMode = 'below_target';
        }

        $period = date('Y-m');
        $todayDom = (int) date('j');
        $sched = wa_auto_blast_scheduler_get($conn, $cabang);
        $hourly = wa_auto_blast_hourly_get($conn, $cabang, $sched);

        $report = [
            'cabang' => $cabang,
            'blast_mode' => $blastMode,
            'period' => $period,
            'send_day_setting' => $sendDay,
            'skipped_not_started' => false,
            'skipped_wait' => false,
            'skipped_hourly_full' => false,
            'hourly' => $hourly,
            'scheduler' => $sched,
            'pending_count' => 0,
            'sent' => false,
            'customer' => null,
            'send' => null,
            'error' => null,
            'note' => null,
        ];

        $started = wa_auto_blast_campaign_started($conn, $cabang, $period);
        if (!$dryRun && $todayDom < $sendDay && !$started) {
            $report['skipped_not_started'] = true;
            $report['note'] = 'Kampanye bulan ini mulai tanggal ' . $sendDay . '. Setelah mulai, pengiriman lanjut tiap hari sampai antrian habis.';
            return $report;
        }

        $targetRows = query("SELECT * FROM customer_target_settings WHERE cabang = $cabang");
        if (empty($targetRows)) {
            $targetRows = query("SELECT * FROM customer_target_settings WHERE cabang = 0");
        }
        $targetBulan = 100000;
        if (!empty($targetRows)) {
            $targetBulan = (float) ($targetRows[0]['target_bulanan'] ?? 100000);
        }

        $blockSets = wa_auto_blast_load_block_sets(
            $conn,
            $cabang,
            $period,
            (int) $sched['dedup_days'],
            $blastMode
        );

        $report['pending_count'] = wa_auto_blast_count_pending(
            $conn,
            $cabang,
            $blastMode,
            $targetBulan,
            $period,
            (int) $sched['dedup_days'],
            $blockSets
        );

        $wait = wa_auto_blast_wait_reason($conn, $cabang, $sched, $hourly, $options);
        if ($wait !== '') {
            $report['skipped_wait'] = true;
            $report['note'] = $wait;
            if ((int) $hourly['remaining'] <= 0) {
                $report['skipped_hourly_full'] = true;
            }
            return $report;
        }

        $next = wa_auto_blast_pick_next(
            $conn,
            $cabang,
            $blastMode,
            $targetBulan,
            $period,
            (int) $sched['dedup_days'],
            $blockSets
        );

        if ($next === null) {
            $report['note'] = 'Tidak ada customer valid yang belum dikirim bulan ini.';
            return $report;
        }

        $tokoRows = query("SELECT toko_nama FROM toko WHERE toko_cabang = $cabang LIMIT 1");
        $tokoNama = (!empty($tokoRows) && !empty($tokoRows[0]['toko_nama'])) ? $tokoRows[0]['toko_nama'] : 'Toko';
        $msg = wa_auto_blast_build_message(
            $remRow['message_template'] ?? '',
            $next,
            $tokoNama,
            $targetBulan,
            $blastMode
        );

        $report['customer'] = [
            'customer_id' => $next['customer_id'],
            'customer_nama' => $next['customer_nama'],
            'phone_key' => $next['phone_key'],
        ];

        if ($dryRun) {
            $report['sent'] = true;
            $report['note'] = 'Dry run: pesan siap dikirim (tidak dikirim ke engine).';
            return $report;
        }

        $sendResult = wa_send_built([['target' => $next['target'], 'message' => $msg]], '1');
        $report['send'] = [
            'success' => $sendResult['success'],
            'message' => $sendResult['message'] ?? '',
            'provider' => $sendResult['provider'] ?? 'local',
        ];

        if (empty($sendResult['success'])) {
            $report['error'] = 'Pengiriman gagal; antrian tidak di-mark terkirim.';
            $report['ok'] = false;
            return $report;
        }

        wa_auto_blast_mark_sent($conn, $cabang, $next['customer_id'], $next['phone_key'], $period, $blastMode);
        wa_auto_blast_hourly_increment($conn, $cabang);
        wa_auto_blast_hourly_increment($conn, WA_AUTO_BLAST_GLOBAL_CABANG);
        $nextSched = wa_auto_blast_set_next_send_at($conn, $cabang, $sched);
        $globalSched = wa_auto_blast_global_sched_resolve($conn);
        $globalNext = wa_auto_blast_global_set_next_send_at($conn, $globalSched, $cabang);

        $report['sent'] = true;
        $report['next_send_at'] = $globalNext['next_send_at'];
        $report['delay_seconds'] = $globalNext['delay_seconds'];
        $report['global_next_send_at'] = $globalNext['next_send_at'];
        $report['pending_count'] = max(0, $report['pending_count'] - 1);
        $report['note'] = 'Terkirim 1 nomor (cabang ' . $cabang . '). Jeda global engine WA '
            . $globalNext['delay_seconds'] . ' detik sebelum cabang lain.';

        return $report;
    }
}
