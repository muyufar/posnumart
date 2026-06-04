<?php
/**
 * Salin ke marketplace-config.php lalu sesuaikan.
 * marketplace-config.php tidak di-commit (tambahkan ke .gitignore jika perlu).
 */

/** URL panel admin Laravel (opsional, tombol buka panel penuh) */
$marketplace_belanja_admin_url = 'http://belanja.numart.id.test/admin/pesanan';

/**
 * Path file SQLite Laravel (database pesanan menunggu bayar).
 * Contoh: C:/laragon/www/belanja.numart.id/database/database.sqlite
 */
$marketplace_belanja_sqlite_path = '';

/**
 * Secret untuk API WA aktivasi (api/marketplace-wa-send.php).
 * Harus sama dengan NUMART_WA_API_SECRET di .env Laravel belanja.
 */
$marketplace_wa_secret = '';
