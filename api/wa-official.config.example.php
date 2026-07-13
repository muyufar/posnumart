<?php
/**
 * Salin berkas ini menjadi: api/wa-official.config.php
 * Jangan commit wa-official.config.php (berisi token rahasia).
 *
 * Provider WA:
 * - local   = engine mandiri NUMART (disarankan) — jalankan wa-engine dengan Node.js
 * - fonnte  = pihak ketiga api.fonnte.com (token di api/no.js)
 * - official = Meta WhatsApp Cloud API
 */
return [
    /** 'local' | 'fonnte' | 'official' */
    'provider' => 'local',

    /**
     * Engine WhatsApp mandiri (clone Fonnte di server Anda).
     * 1. cd wa-engine && copy .env.example .env
     * 2. Sesuaikan WA_API_SECRET (harus sama dengan api_secret di bawah)
     * 3. npm install && npm start
     * 4. Buka menu WA Device di NUMART → scan QR
     */
    'local' => [
        'base_url' => 'http://127.0.0.1:3920',
        'api_secret' => 'y9Db7kVX2rAe4Z!8wjPpQ^tFLGumYSx6',
        'device_name' => 'NUMART Pusat',
    ],

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
