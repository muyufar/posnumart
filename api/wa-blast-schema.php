<?php
/**
 * Buat tabel WA blast / template jika belum ada (live server lama).
 */
function wa_blast_ensure_schema($conn)
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $sqlHistory = "CREATE TABLE IF NOT EXISTS `wa_blast_history` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `cabang` int(11) NOT NULL DEFAULT 0,
      `user_id` int(11) NOT NULL,
      `message_template` text NOT NULL,
      `total_recipients` int(11) DEFAULT 0,
      `blast_type` varchar(50) DEFAULT 'manual',
      `filter_criteria` text DEFAULT NULL,
      `created_at` datetime DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_cabang_created` (`cabang`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $sqlRecipients = "CREATE TABLE IF NOT EXISTS `wa_blast_recipients` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `blast_id` int(11) NOT NULL,
      `customer_id` int(11) NOT NULL,
      `customer_phone` varchar(20) NOT NULL,
      `status` enum('pending','sent','failed') DEFAULT 'pending',
      `sent_at` datetime DEFAULT NULL,
      `created_at` datetime DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_blast_status` (`blast_id`,`status`),
      KEY `idx_phone_sent` (`customer_phone`,`sent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $sqlTemplates = "CREATE TABLE IF NOT EXISTS `wa_templates` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `cabang` int(11) NOT NULL DEFAULT 0,
      `template_name` varchar(100) NOT NULL,
      `template_content` text NOT NULL,
      `is_active` tinyint(1) DEFAULT 1,
      `created_at` datetime DEFAULT current_timestamp(),
      `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_cabang_active` (`cabang`,`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    mysqli_query($conn, $sqlHistory);
    mysqli_query($conn, $sqlRecipients);
    mysqli_query($conn, $sqlTemplates);
}
