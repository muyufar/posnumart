<?php
/**
 * Skema tabel log API Gateway WA.
 */

if (!function_exists('wa_gateway_ensure_schema')) {
    function wa_gateway_ensure_schema($conn)
    {
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS wa_gateway_message_log (
            log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            api_key_name VARCHAR(80) NOT NULL DEFAULT '',
            target VARCHAR(32) NOT NULL DEFAULT '',
            message_type ENUM('text','image','file','other') NOT NULL DEFAULT 'text',
            message_preview VARCHAR(255) NOT NULL DEFAULT '',
            media_url VARCHAR(500) NOT NULL DEFAULT '',
            provider VARCHAR(32) NOT NULL DEFAULT 'local',
            provider_status TINYINT(1) NOT NULL DEFAULT 0,
            provider_response MEDIUMTEXT NULL,
            provider_message_id VARCHAR(120) NOT NULL DEFAULT '',
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (log_id),
            KEY idx_wa_gw_log_created (created_at),
            KEY idx_wa_gw_log_target (target)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS wa_gateway_webhook_events (
            event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(64) NOT NULL DEFAULT 'unknown',
            payload MEDIUMTEXT NULL,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (event_id),
            KEY idx_wa_gw_wh_created (created_at),
            KEY idx_wa_gw_wh_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS wa_gateway_rate_limit (
            rate_key VARCHAR(120) NOT NULL,
            window_start INT UNSIGNED NOT NULL DEFAULT 0,
            hit_count INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (rate_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
