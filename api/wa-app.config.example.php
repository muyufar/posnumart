<?php
/**
 * Salin berkas ini menjadi: api/wa-app.config.php
 * (atau tetap pakai api/wa-official.config.php — masih didukung untuk server lama)
 *
 * Jangan commit wa-app.config.php (berisi api_secret rahasia).
 */
return [
    'local' => [
        /** URL engine Node.js (wa-engine/) — production: http://127.0.0.1:3920 */
        'base_url' => 'http://127.0.0.1:3920',
        /** Harus sama dengan WA_API_SECRET di wa-engine/.env */
        'api_secret' => 'ubah-ini-jadi-rahasia-panjang',
        'device_name' => 'NUMART Pusat',
    ],
];
