# Struktur Modul Numart

Dokumen mapping reorganisasi project POS Numart. URL publik tetap sama via **stub** di root.

## Infrastruktur

| Path | Fungsi |
|------|--------|
| `bootstrap/paths.php` | `NUMART_ROOT`, `numart_path()`, `numart_require_layout()` |
| `shared/layout/` | Partial layout (`_header`, `_sidebar`, dll.) |
| `modules/{modul}/pages/` | Halaman UI utama |
| `modules/{modul}/data/` | Endpoint DataTables/AJAX |
| `modules/{modul}/actions/` | Delete, export, proses POST |
| `modules/{modul}/lib/` | Library bisnis modul |
| `tools/migrate-module.php` | CLI migrasi file + stub |
| `tools/fix-module-paths.php` | Perbaiki path vendor setelah migrasi |

## Stub pattern (root) — diganti router (2026-08-22)

**Sebelum:** 282 file stub tipis di root (`barang.php` → require modules/...)

**Sekarang:** Router terpusat — root hanya ~18 file entry/layout.

- [`bootstrap/front.php`](bootstrap/front.php) — front controller
- [`bootstrap/routes-map.php`](bootstrap/routes-map.php) — mapping URL → modul (281 route)
- [`.htaccess`](.htaccess) — arahkan URL ke `bootstrap/front.php`
- Regenerate map: `php tools/generate-routes.php`
- Hapus stub lama: `php tools/remove-root-stubs.php`

URL tetap sama: `barang`, `barang-data.php`, `beli-langsung`, dll.

### Routing `.htaccess` + base URL

1. File fisik langsung (`aksi/login.php`, `api/*`, `dist/*`)
2. Tanpa `.php` → file jika ada (`aksi/login` → `aksi/login.php`)
3. Modul virtual → `bootstrap/front.php`

- `numart_web_base()` / `numart_url('path')` di `bootstrap/paths.php`
- `<base href="...">` di layout & halaman login
- Override: `bootstrap/config.local.php` → `define('NUMART_WEB_BASE', '/numart/');`

## Modul

### cetak
- `cetak-label-harga.php`, `cetak-label-pdf.php`, `cetak-label-excel.php`
- `cetak-berita-acara-kirim-barang.php`
- `nota-cetak.php`, `nota-cetak-hutang.php`, `nota-cetak-pembelian.php`, `nota-cetak-piutang.php`

### barang
- Prefix: `barang*`, `kategori*`, `satuan*`, `get-barang*`, `export-barang*`, `export_barang*`
- Lib: `aksi/barang-*-lib.php`, `aksi/satuan-lib.php`

### penjualan
- `beli-langsung*`, `penjualan*`, `invoice*`, `edit-transaksi*`, `layar-konsumen.php`

### pembelian
- `pembelian*`, `transaksi-pembelian*`, `pengadaan-*`, `forecasting-*`

### stock
- `stock-opname*`, `stok.php`, `penyesuaian-stock`, `transfer-stock-cabang*`, `transfer-cetak*`, `monitor-duplikat-*`

### keuangan
- `laba-*`, `hpp-*`, `coa-link*`, `piutang*`, `hutang*`, `laporan-*`, `produk-analisa*`, `rekonsiliasi-*`, `perbaiki-*`, `recalculate-*`

### master
- `customer*`, `supplier*`, `ekspedisi*`, `toko*`, `users*`, `user-*`, `kurir-*`, `backup.php`, `restore.php`, `shopee-*`, `marketplace-*`

### util
- `audit*`, `debug_*`, `verifikasi-*`, `investor-dashboard.php`, `pantau.php`, `hapus-akun-null.php`

## Yang tidak dipindah (fase awal)

- `aksi/` — login, koneksi, halau, ssp, search-barang, endpoint global; lib sudah distub ke `modules/*/lib/`
- `api/` — endpoint JSON/cron/WA (URL eksternal, tidak dipindah agar cron tetap jalan)
- `export/` — template legacy + README
- `midtrans-api/`, `wa-engine/` — subproyek terpisah
- `index.php`, `bo.php`, `bo-grafik.php` — entry point

## Status migrasi (2026-08-22)

- ~295 file root dipindah ke `modules/{cetak,barang,penjualan,pembelian,stock,keuangan,master,util}/`
- Stub tipis di root mempertahankan URL `.htaccess`
- Layout partials di `shared/layout/` dengan stub `_*.php` di root
- Lib bisnis dipindah ke `modules/*/lib/` dengan stub forward di `aksi/`
- `functions.php`, `functions1.php`, `functions-18.php` di root → redirect ke `aksi/functions.php`
