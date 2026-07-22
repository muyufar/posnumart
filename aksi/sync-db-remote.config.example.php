<?php
/**
 * Salin menjadi: aksi/sync-db-remote.config.php (gitignored)
 *
 * Dipakai halaman sync-database-live.php (HANYA localhost / Laragon).
 *
 * Hostinger: hPanel → Databases → Remote MySQL → aktifkan & whitelist IP publik PC Anda.
 * Host biasanya seperti: srv1234.hstgr.io (lihat di detail database hPanel).
 */
return [
    /**
     * mysql = Remote MySQL Hostinger
     * http  = unduh dump via HTTPS dari server live (disarankan jika Remote MySQL Access denied)
     */
    'sync_mode' => 'http',

    'http_export_url' => 'https://pos.numartmagelang.com/api/sync-db-export-live.php',
    'http_export_secret' => '15a4wdawd564awd5a41wd5a1w5d4aw54fa54543y',

    /** Host MySQL live (mode mysql saja) */
    'remote_host' => 'srv1867.hstgr.io',

    'remote_port' => 3306,
    'remote_user' => 'u700125577_user',
    'remote_password' => 'Nugo@1990',
    'remote_database' => 'u700125577_numartv2',

    /**
     * Database lokal tujuan. Kosongkan = pakai nama dari aksi/koneksi.php
     */
    'local_database' => null,

    /** User/password MySQL lokal (Laragon default: root / kosong) */
    'local_user' => 'root',
    'local_password' => '',
    'local_host' => '127.0.0.1',
    'local_port' => 3306,

    /**
     * Domain production — fitur sync otomatis diblokir jika HTTP_HOST cocok.
     */
    'blocked_hosts' => [
        'numartmagelang.com',
        'pos.numartmagelang.com',
        'api.numartmagelang.com',
    ],

    /** Ketik frasa ini saat konfirmasi sinkron (hindari klik tidak sengaja) */
    'confirm_phrase' => 'SYNC-LIVE',

    /** true = coba mysqldump Laragon dulu (lebih cepat). false = PHP table-by-table */
    'prefer_mysqldump' => true,

    /**
     * Hostinger Remote MySQL sering menolak IPv6.
     * true = resolve hostname ke IPv4 (DNS A record) sebelum konek.
     */
    'remote_prefer_ipv4' => true,
];
