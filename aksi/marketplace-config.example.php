<?php
/**
 * Salin ke marketplace-config.php lalu sesuaikan.
 * marketplace-config.php tidak di-commit (tambahkan ke .gitignore jika perlu).
 */

/** URL panel admin Laravel (opsional) */
$marketplace_belanja_admin_url = 'http://belanja.numart.id.test/admin/pesanan';

/** URL publik belanja.numart.id — untuk link gambar bukti transfer (harus .id bukan .idt) */
$marketplace_belanja_public_url = 'https://belanja.numart.id';

/** URL API Laravel (tanpa slash di akhir) — untuk verifikasi pembayaran dari POS */
$marketplace_belanja_api_url = 'http://belanja.numart.id.test';

/**
 * Database MySQL marketplace (disarankan production).
 * Samakan dengan DB_* di .env Laravel belanja.numart.id
 */
$marketplace_belanja_db_host = '127.0.0.1';
$marketplace_belanja_db_name = 'belanja_numart';
$marketplace_belanja_db_user = 'root';
$marketplace_belanja_db_pass = '';

/**
 * Fallback lokal: SQLite Laravel jika MySQL belum diisi.
 * Contoh: C:/laragon/www/belanja.numart.id/database/database.sqlite
 */
$marketplace_belanja_sqlite_path = '';

/**
 * Secret untuk API WA (opsional).
 * Harus sama dengan NUMART_WA_API_SECRET di .env Laravel belanja.
 */
$marketplace_wa_secret = '';

/** User kasir virtual di tabel user (0 = cari by nama "Belanja Online") */
$marketplace_kasir_user_id = 0;

/** Customer default jika member belum punya numart_customer_id */
$marketplace_default_customer_id = 1;
