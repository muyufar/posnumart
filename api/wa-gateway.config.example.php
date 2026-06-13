<?php
/**
 * Salin ke wa-gateway.config.php lalu sesuaikan.
 * File wa-gateway.config.php tidak di-commit (lihat .gitignore).
 *
 * API Gateway WA — dipakai aplikasi eksternal untuk kirim WA
 * lewat engine mandiri NUMART (wa-engine/).
 */

return [
    /** Matikan seluruh API gateway tanpa menghapus file */
    'enabled' => true,

    /**
     * API key untuk klien eksternal.
     * Header: Authorization: TOKEN_ANDA
     * atau: Authorization: Bearer TOKEN_ANDA
     */
    'keys' => [
        [
            'name' => 'default',
            'token' => 'ganti-dengan-token-rahasia-panjang',
            'enabled' => true,
            'rate_per_minute' => 60,
        ],
    ],

    /** Delay default antar kirim (detik) */
    'default_delay' => 2,

    /** true = tolak kirim jika device WA disconnect */
    'connect_only' => true,

    /** Simpan log pengiriman ke database */
    'log_messages' => true,

    /**
     * Secret untuk verifikasi webhook dari wa-engine (opsional).
     * Set WA_WEBHOOK_URL di wa-engine/.env ke api/v1/webhook.php
     */
    'webhook_secret' => '',
];
