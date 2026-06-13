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

    /**
     * Batas jam kerja cron WA otomatis (hindari kirim malam/dini hari).
     * Cron URL boleh tetap dipanggil 24 jam; pengiriman hanya di dalam jendela ini.
     */
    'cron' => [
        'business_hours_enabled' => true,
        'business_hours_start' => '07:00',
        'business_hours_end' => '21:00',
    ],

    /**
     * Proteksi blast manual (halaman WA Blast / send-wa.php).
     */
    'manual' => [
        'business_hours_enabled' => true,
        'respect_global_lock' => true,
        'reconnect_cooldown_minutes' => 30,
    ],

    /**
     * Minggu pertama kampanye: kuota per jam diturunkan otomatis.
     */
    'safety' => [
        'ramp_down_days' => 7,
        'ramp_down_hourly_min' => 10,
        'ramp_down_hourly_max' => 15,
    ],
];
