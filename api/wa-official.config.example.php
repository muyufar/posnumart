<?php
/**
 * Salin berkas ini menjadi: api/wa-official.config.php
 * Jangan commit wa-official.config.php (berisi token rahasia).
 *
 * Setup WhatsApp Cloud API (resmi Meta):
 * 1. https://developers.facebook.com → buat App → tambah produk WhatsApp
 * 2. WhatsApp → API Setup → ambil Phone number ID & Access token (permanen dari System User disarankan)
 * 3. Buat Message Template di WhatsApp Manager (wajib untuk blast ke customer di luar jendela 24 jam)
 * 4. Set provider ke 'official' di wa-official.config.php setelah Meta terverifikasi
 *
 * Sementara verifikasi Meta belum selesai, pakai provider 'fonnte' (token di api/no.js).
 *
 * Template contoh di Meta (Bahasa Indonesia):
 *   Nama: numart_blast
 *   Body: {{1}}
 *   (satu variabel = isi pesan lengkap setelah variabel {nama_customer} dll diisi aplikasi)
 */
return [
    /** 'fonnte' | 'official' — default fonnte sampai Meta siap */
    'provider' => 'fonnte',

    'official' => [
        /** Token dari Meta Business / System User */
        'access_token' => 'EAAxxxxxxxx',

        /** Dari WhatsApp → API Setup → Phone number ID */
        'phone_number_id' => '123456789012345',

        /** Opsional: WABA ID (untuk debug) */
        'waba_id' => '',

        'api_version' => 'v21.0',

        /**
         * template = kirim pakai template Meta (disarankan untuk blast / marketing)
         * text     = pesan teks bebas (hanya jika user sudah chat dalam 24 jam terakhir)
         */
        'send_mode' => 'template',

        'template' => [
            'name' => 'numart_blast',
            'language' => 'id',
            /**
             * text_as_body = seluruh pesan personal (setelah replace {nama_customer} dll) jadi parameter {{1}}
             * param_map    = urutan parameter body sesuai template Anda ({{1}}, {{2}}, ...)
             */
            'mode' => 'text_as_body',
            /** Hanya jika mode = param_map, contoh: ['nama_customer', 'kurang', 'target'] */
            'param_keys' => [],
        ],

        /** Jeda detik antar pesan (hindari rate limit), minimal 1 */
        'delay_seconds' => 2,

        /** Maksimal penerima per request API internal (disarankan ≤ 50 untuk official) */
        'max_per_request' => 50,
    ],
];
