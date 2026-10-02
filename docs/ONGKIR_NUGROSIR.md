# Pilot ongkir Nugrosir Muntilan

Implementasi lokal pada POS dan `belanja.numart.id`, tanpa API peta berbayar. Perutean, berat, dimensi dan kapasitas diverifikasi admin sebelum penawaran diterbitkan. Ini bukan tarif resmi GoFood/ShopeeFood.

## Deploy dan aktivasi

1. Deploy perubahan **kedua repo**, termasuk `shared/NugrosirShipping.php` di POS dan salinan identiknya `app/Support/NugrosirShipping.php` di Laravel. Jangan mengubah hanya satu salinan. `php tests/shipping_policy_test.php` memeriksa kesamaan jika dua repo berdampingan.
2. Backup database belanja. Jalankan `php artisan migrate --force` di repo Laravel, lalu `php artisan view:clear`. Migrasi hanya menambah tabel/kolom; tidak mengubah invoice atau konfigurasi produksi POS. Jangan rollback migrasi setelah ada transaksi pilot tanpa mengarsipkan ledger.
3. Pastikan koneksi database belanja pada POS mengarah ke database yang sama dengan Laravel. Deploy `bootstrap/routes-map.php` yang sudah diregenerasi.
4. Admin/super admin **cabang 0** membuka Penjualan → Belanja Online → **Ongkir & perjalanan Nugrosir** (`marketplace-ongkir`). Verifikasi bahwa cabang 0 memang Nugrosir Muntilan. Isi titik/alamat toko yang benar, kategori QRIS dan biaya aktual serta persetujuan mitra.
5. Aktifkan pilot melalui formulir. Batas 30 hari dimulai saat aktivasi pertama. Default sebelum aktivasi: checkout lama tetap berjalan. Setelah pernah aktif, jeda/akhir pilot menghentikan permintaan baru dan tidak mengembalikan ongkir gratis lama. Penawaran lama yang belum kedaluwarsa tetap dapat disetujui.
6. Uji dengan pelanggan dan kurir uji sebelum menerima transaksi umum. Minimal belanja lama tidak diubah otomatis; sesuaikan melalui menu Minimal Pesanan bila kebijakan bisnis mengizinkan keranjang Rp50–100 ribu.

Tidak ada migrasi atau aktivasi pada database operasional yang dilakukan otomatis oleh perubahan kode ini.

## Alur pelanggan dan admin

- Checkout: pelanggan memilih langsung, hemat terjadwal, atau khusus lalu **Ajukan ongkir**. Permintaan bukan pesanan dan belum meminta pembayaran atau menahan stok.
- Admin memilih 1 permintaan langsung/khusus atau 2–3 permintaan hemat. Verifikasi koordinat tujuan, jarak **jalan** satu arah, berat gabungan, dimensi dan kendaraan. Masukkan total perjalanan termasuk pengambilan/kembali, waktu penanganan dan menunggu, serta catatan rute.
- Jadwal hemat: 11–13 atau 16–18 WIB. Pastikan kurir dan kapasitas benar-benar tersedia; perencanaan/penugasan dilakukan manual, bukan optimasi rute otomatis.
- Batas standar 5 km dan 10 kg **gabungan**. Pesanan di luar batas membutuhkan penawaran manual untuk kendaraan sesuai dan biaya yang disepakati, tidak lolos sebagai motor standar.
- Penawaran berlaku sampai yang lebih awal: 24 jam atau awal jadwal. Pelanggan memilih penawaran di checkout, memeriksa total dan membuat pesanan. Tidak ada kenaikan sepihak setelah checkout. Perubahan alamat, metode pembayaran, layanan, barang, jumlah, harga atau HPP mengharuskan penawaran ulang.
- Muat ulang checkout untuk melihat perubahan; tidak ada pengiriman WhatsApp/notifikasi eksternal otomatis.
- Penugasan kurir dan tracking memakai alur POS yang sudah ada. Semua invoice satu perjalanan harus ditugaskan ke kurir yang sama sebelum ditutup.
- Setelah pengiriman selesai, admin menutup perjalanan sekali dengan km/waktu aktual, kurir, bukti pengeluaran dan catatan. Durasi mencakup waktu tunggu yang harus dibayar. Pengeluaran dibayar penuh di luar pembulatan tarif. Laporan membedakan penerimaan kotor, estimasi biaya kendaraan, serta imbalan bersih per jam tercatat.

## Tarif dan akuntansi

`bayar = ceil(max(8000, km_total*650 + menit*250 + jumlah_pesanan*500)/1000)*1000`.

Pembayaran perjalanan dialokasikan merata sampai rupiah terakhir. Total alokasi selalu sama dengan total perjalanan. Diskon tidak mengubah hak kurir. `invoice_ongkir` tetap ongkir pembeli untuk menjaga total tagihan. Ringkasan upah kurir mengecualikan invoice dengan penawaran baru dan menggantinya dengan ledger perjalanan yang ditutup; invoice lama tetap menggunakan perilaku lama. Tanpa koneksi belanja, ringkasan upah ditandai tidak tersedia.

HPP memakai `barang_harga_beli_rata` positif bila tersedia, fallback `barang_harga_beli`, dikali konversi satuan dasar dan jumlah. HPP nol/tidak diketahui mematikan subsidi dan tidak menampilkan estimasi laba yang menyesatkan. Harga jual berasal dari katalog setelah diskon.

Subsidi maksimal Rp2.000 hanya pada subtotal minimal Rp100.000, dibatasi anggaran tersisa dan kontribusi minimal Rp3.000 setelah biaya persiapan Rp2.500, cadangan risiko 0,5%, serta biaya pembayaran. Transfer pada alur yang ada diperlakukan sebagai QRIS; kategori merchant dipilih admin. COD biaya kanal nol. Biaya integrasi tetap dapat dikonfigurasi. Aturan MDR yang disimpan pada versi ini: reguler 0% sampai Rp100.000 lalu 0,7%; UMI 0% sampai Rp500.000 lalu 0,3%. Dasar adalah total termasuk ongkir sesudah subsidi. Perubahan ketentuan memerlukan versi aturan baru, tidak mengubah snapshot lama.

Subsidi Rp300.000 dan cadangan selisih Rp300.000 dicadangkan secara transaksional. Semua mutasi mengunci baris campaign yang sama. Cadangan hemat memakai nilai konservatif `max(bayar perjalanan, jumlah biaya kirim sendiri - bayar perjalanan)` agar pembatalan anggota rute tidak memotong kurir. Anggaran bukan janji biaya nol: kewajiban yang sudah timbul tetap dicatat meskipun biaya aktual melewati cadangan; sistem kemudian menolak penawaran hemat baru yang tidak terdanai.

## Pembatalan, kedaluwarsa, dan rekonsiliasi

- Pelanggan/admin bisa membatalkan **penawaran yang belum menjadi pesanan**, melepas subsidi. Kedaluwarsa melepas subsidi saat permintaan/penawaran berikutnya diproses. Cadangan perjalanan tetap ditahan sampai perjalanan ditutup karena anggota lain mungkin sudah checkout.
- Tanpa pelanggan menerima dan tanpa pekerjaan/pengeluaran, admin bisa menutup sebagai batal tanpa pekerjaan, pembayaran nol. Jika sudah ada pekerjaan, bayar setidaknya kesepakatan awal beserta pengeluaran terbukti. Ini pilihan konservatif untuk melindungi mitra.
- Pembatalan **pesanan yang sudah dibuat/dibayar**, retur barang, pengembalian uang pelanggan, serta pembatalan invoice memakai rekonsiliasi operasional/keuangan yang sudah ada; halaman ongkir tidak membalik invoice atau mengirim refund otomatis. Status pesanan harus selesai dikirim atau cancelled sebelum perjalanan bisa ditutup. Jangan menandai delivered hanya agar upah muncul.
- Kontribusi aktual di tabel berarti alokasi pembayaran kurir sudah aktual, sedangkan HPP, biaya persiapan dan cadangan risiko tetap snapshot. Baris batal dan biaya perjalanan yang tidak menjadi penjualan tidak boleh dihitung sebagai laba. Rekonsiliasi kas/pembayaran mitra fisik terpisah; ledger ini mencatat **hak bayar**, bukan bukti transfer bank.
- Seluruh perubahan campaign, penerbitan/penerimaan/pembatalan penawaran dan penyelesaian perjalanan memiliki audit `shipping_events` dengan aktor. Tidak ada reset anggaran atau perpanjangan pilot otomatis.

## Verifikasi dan batas pengujian

Laravel: `php vendor/bin/phpunit --filter ShippingPilotTest`. POS: `php tests/shipping_policy_test.php`.

Pengujian memakai SQLite in-memory, bukan database operasional. Cakupan: contoh tarif 1–5 km, kelompok 2–3 pesanan, batas jarak/berat, koordinat hilang, penawaran khusus, QRIS, subsidi/margin, anggaran, kedaluwarsa, kepemilikan, perubahan keranjang, replay, snapshot tetap, pembatalan sebelum/sesudah kerja, alokasi tepat, HPP konversi dan rollback checkout gagal.

Saat staging MySQL: uji dua checkout/approval bersamaan, konfirmasi hanya satu yang mengonsumsi penawaran dan plafon tidak terlampaui; uji login admin/kurir/member, CSRF, UI ponsel, penugasan invoice, tutup perjalanan dan rekonsiliasi total upah. Ukur keterlambatan, pembelian ulang, penolakan setelah melihat tarif dan waktu menganggur secara operasional selama 30 hari sebelum memperluas pilot.
