<?php
/**
 * Salin menjadi: api/sync-db-export.config.php di SERVER LIVE.
 * Secret harus sama dengan http_export_secret di aksi/sync-db-remote.config.php (lokal).
 */
return [
    /** Frasa rahasia panjang — jangan commit ke Git */
    'secret' => 'ganti-dengan-string-acak-panjang',

    /**
     * Kosongkan [] = izinkan dari IP manapun asal secret benar.
     * Atau isi IP publik PC dev: ['158.140.166.102']
     */
    'allowed_ips' => [],
];
