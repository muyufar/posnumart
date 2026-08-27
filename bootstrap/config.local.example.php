<?php
/**
 * Salin ke config.local.php (file ini TIDAK di-commit — beda tiap server).
 *
 * Hanya buat jika menu POS logout / link salah (base href).
 *
 * demopos (subfolder):
 *   define('NUMART_WEB_BASE', '/posgit/');
 *
 * pos.numartmagelang.com (root domain):
 *   define('NUMART_WEB_BASE', '/');
 *   — atau hapus config.local.php, biarkan deteksi otomatis.
 *
 * Laragon subfolder:
 *   define('NUMART_WEB_BASE', '/numart/');
 */
// define('NUMART_WEB_BASE', '/posgit/');
