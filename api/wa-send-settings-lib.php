<?php
/**
 * Batas teknis pengiriman WA per cabang (diatur di Pengaturan Target, bukan di Fonnte).
 */

if (!function_exists('wa_send_settings_ensure_schema')) {
    function wa_send_settings_ensure_schema($conn)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $sql = "CREATE TABLE IF NOT EXISTS `wa_blast_send_settings` (
          `cabang` int(11) NOT NULL,
          `max_contacts_per_batch` int(11) NOT NULL DEFAULT 25,
          `min_interval_minutes` int(11) NOT NULL DEFAULT 120,
          `delay_seconds_per_contact` int(11) NOT NULL DEFAULT 3 COMMENT 'Jeda antar nomor dalam satu sesi',
          `last_send_at` datetime DEFAULT NULL,
          `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`cabang`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        mysqli_query($conn, $sql);

        $col = mysqli_query($conn, "SHOW COLUMNS FROM `wa_blast_send_settings` LIKE 'delay_seconds_per_contact'");
        if ($col && mysqli_num_rows($col) === 0) {
            mysqli_query(
                $conn,
                "ALTER TABLE `wa_blast_send_settings`
                 ADD COLUMN `delay_seconds_per_contact` int(11) NOT NULL DEFAULT 3
                 COMMENT 'Jeda antar nomor dalam satu sesi' AFTER `min_interval_minutes`"
            );
        }

        if (function_exists('wa_auto_blast_ensure_schema')) {
            wa_auto_blast_ensure_schema($conn);
        }
    }
}

if (!function_exists('wa_send_settings_defaults')) {
    /** @return array{max_contacts_per_batch: int, min_interval_minutes: int, delay_seconds_per_contact: int, last_send_at: ?string} */
    function wa_send_settings_defaults()
    {
        return [
            'max_contacts_per_batch' => 25,
            'min_interval_minutes' => 120,
            'delay_seconds_per_contact' => 3,
            'last_send_at' => null,
        ];
    }
}

if (!function_exists('wa_send_settings_get')) {
    /**
     * @return array{max_contacts_per_batch: int, min_interval_minutes: int, delay_seconds_per_contact: int, last_send_at: ?string}
     */
    function wa_send_settings_get($conn, $cabang)
    {
        wa_send_settings_ensure_schema($conn);
        $cabang = (int) $cabang;

        $row = null;
        if ($cabang > 0) {
            $rows = [];
            $res = mysqli_query($conn, "SELECT * FROM wa_blast_send_settings WHERE cabang = $cabang LIMIT 1");
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
            }
        }
        if ($row === null) {
            $res = mysqli_query($conn, "SELECT * FROM wa_blast_send_settings WHERE cabang = 0 LIMIT 1");
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
            }
        }

        $def = wa_send_settings_defaults();
        if ($row === null) {
            return $def;
        }

        $max = (int) ($row['max_contacts_per_batch'] ?? $def['max_contacts_per_batch']);
        $min = (int) ($row['min_interval_minutes'] ?? $def['min_interval_minutes']);
        $delay = (int) ($row['delay_seconds_per_contact'] ?? $def['delay_seconds_per_contact']);

        return [
            'max_contacts_per_batch' => max(1, min(25, $max)),
            'min_interval_minutes' => max(120, $min),
            'delay_seconds_per_contact' => max(1, min(120, $delay)),
            'last_send_at' => !empty($row['last_send_at']) ? (string) $row['last_send_at'] : null,
        ];
    }
}

if (!function_exists('wa_send_settings_check_interval')) {
    /**
     * @return array{allowed: bool, wait_minutes: int, message: string}
     */
    function wa_send_settings_check_interval($conn, $cabang)
    {
        $s = wa_send_settings_get($conn, $cabang);
        $min = (int) $s['min_interval_minutes'];
        $last = $s['last_send_at'];

        if ($last === null || $last === '') {
            return ['allowed' => true, 'wait_minutes' => 0, 'message' => ''];
        }

        $lastTs = strtotime($last);
        if ($lastTs === false) {
            return ['allowed' => true, 'wait_minutes' => 0, 'message' => ''];
        }

        $elapsed = time() - $lastTs;
        $required = $min * 60;
        if ($elapsed >= $required) {
            return ['allowed' => true, 'wait_minutes' => 0, 'message' => ''];
        }

        $waitMin = (int) ceil(($required - $elapsed) / 60);
        return [
            'allowed' => false,
            'wait_minutes' => $waitMin,
            'message' => 'Jeda pengiriman: tunggu sekitar ' . $waitMin . ' menit lagi (minimal ' . $min . ' menit antar batch).',
        ];
    }
}

if (!function_exists('wa_send_settings_touch_last_send')) {
    function wa_send_settings_touch_last_send($conn, $cabang)
    {
        wa_send_settings_ensure_schema($conn);
        $cabang = (int) $cabang;
        if ($cabang < 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $s = wa_send_settings_get($conn, $cabang);
        $max = (int) $s['max_contacts_per_batch'];
        $min = (int) $s['min_interval_minutes'];
        mysqli_query(
            $conn,
            "INSERT INTO wa_blast_send_settings (cabang, max_contacts_per_batch, min_interval_minutes, last_send_at)
             VALUES ($cabang, $max, $min, '$now')
             ON DUPLICATE KEY UPDATE last_send_at = '$now'"
        );
    }
}

if (!function_exists('wa_send_settings_save_limits')) {
    function wa_send_settings_save_limits($conn, $cabang, $maxContacts, $minInterval, $delayPerContact = 3)
    {
        wa_send_settings_ensure_schema($conn);
        $cabang = (int) $cabang;
        $maxContacts = max(1, min(25, (int) $maxContacts));
        $minInterval = max(120, (int) $minInterval);
        $delayPerContact = max(1, min(120, (int) $delayPerContact));

        $res = mysqli_query($conn, "SELECT cabang FROM wa_blast_send_settings WHERE cabang = $cabang LIMIT 1");
        $has = $res && mysqli_num_rows($res) > 0;

        if ($has) {
            mysqli_query(
                $conn,
                "UPDATE wa_blast_send_settings SET
                    max_contacts_per_batch = $maxContacts,
                    min_interval_minutes = $minInterval,
                    delay_seconds_per_contact = $delayPerContact
                 WHERE cabang = $cabang"
            );
        } else {
            mysqli_query(
                $conn,
                "INSERT INTO wa_blast_send_settings (cabang, max_contacts_per_batch, min_interval_minutes, delay_seconds_per_contact)
                 VALUES ($cabang, $maxContacts, $minInterval, $delayPerContact)"
            );
        }

        return [
            'max_contacts_per_batch' => $maxContacts,
            'min_interval_minutes' => $minInterval,
            'delay_seconds_per_contact' => $delayPerContact,
        ];
    }
}
