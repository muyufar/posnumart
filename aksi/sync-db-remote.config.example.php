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
    'http_export_secret' => 'ganti-dengan-string-acak-panjang',

    /** Potongan kecil — CDN Hostinger memotong response GET ~50 KB; data via POST */
    'http_chunk_max_bytes' => 6144,
    'http_chunk_max_rows' => 80,
    'http_chunk_max_seconds' => 5,

    /** Tabel yang datanya dilewati (struktur tetap). Contoh: audit log besar */
    'skip_tables' => [
        // 'audit_barang',
    ],

    /** Host MySQL live (mode mysql saja) */
    'remote_host' => 'srv1867.hstgr.io',

    'remote_port' => 3306,
    'remote_user' => 'u700125577_user',
    'remote_password' => 'Nugo@1990',
    'remote_database' => 'u700125577_numartv2',

    /**
     * Database tujuan sync. Kosongkan = pakai nama dari aksi/koneksi.php
     * Demopos: set 'u700125577_posnew'
     */
    'local_database' => null,

    /**
     * Kredensial MySQL di mesin TARGET sync (bukan production).
     * Laragon: root / kosong
     * Demopos Hostinger: user & password database u700125577_posnew (sama seperti koneksi.php demopos)
     */
    'local_user' => 'root',
    'local_password' => '',
    'local_host' => '127.0.0.1',
    'local_port' => 3306,

    /**
     * Host yang BOLEH menjalankan sync (selain localhost/Laragon).
     * Pakai untuk live developer, mis. demopos.numartmagelang.com.
     * Host di sini menang atas blocked_hosts.
     */
    'allowed_hosts' => [
        // 'demopos.numartmagelang.com',
    ],

    /**
     * Domain production — fitur sync otomatis diblokir jika HTTP_HOST cocok
     * (kecuali host ada di allowed_hosts).
     */
    'blocked_hosts' => [
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
