<?php
/**
 * Membuat tabel pengingat WA otomatis (below target) jika belum ada.
 */
function wa_auto_below_target_ensure_schema($conn)
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $sqlSettings = "CREATE TABLE IF NOT EXISTS `wa_auto_target_reminder_settings` (
      `cabang` int(11) NOT NULL,
      `enabled` tinyint(1) NOT NULL DEFAULT 0,
      `send_day` tinyint unsigned NOT NULL DEFAULT 26 COMMENT 'Tanggal 1-28 tiap bulan (Asia/Jakarta)',
      `message_template` text DEFAULT NULL,
      `created_at` datetime DEFAULT current_timestamp(),
      `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`cabang`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $sqlSent = "CREATE TABLE IF NOT EXISTS `wa_auto_below_target_sent` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `cabang` int(11) NOT NULL,
      `customer_id` int(11) NOT NULL,
      `period_yyyymm` char(7) NOT NULL,
      `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_cabang_cust_period` (`cabang`,`customer_id`,`period_yyyymm`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    mysqli_query($conn, $sqlSettings);
    mysqli_query($conn, $sqlSent);
}
