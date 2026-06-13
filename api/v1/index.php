<?php
/**
 * NUMART WA Gateway API v1
 * Dokumentasi singkat — endpoint mirip Fonnte untuk integrasi eksternal.
 *
 * Base URL: https://domain-anda.com/api/v1/
 *
 * Autentikasi (semua endpoint kecuali webhook):
 *   Header: Authorization: TOKEN_API_ANDA
 *   atau:   Authorization: Bearer TOKEN_API_ANDA
 *
 * --- POST send.php ---
 * Kirim teks / gambar / file (URL publik)
 * Body JSON atau form-urlencoded:
 *   target   (wajib) nomor WA, contoh 0812xxx atau 62812xxx
 *   message  (wajib jika tanpa url) isi pesan
 *   url      (opsional) URL file/gambar publik
 *   filename (opsional) nama file
 *   delay    (opsional) detik, default dari config
 *   connectOnly (opsional) true/false
 *
 * Response:
 *   { "status": true, "detail": "...", "id": ["..."], "target": "628...", "log_id": 1 }
 *
 * --- POST device.php ---
 * Status device WA (engine mandiri / Fonnte)
 *
 * --- POST validate.php ---
 * Validasi nomor WhatsApp
 * Body: { "target": "0812..." }
 *
 * --- POST webhook.php ---
 * Terima webhook dari engine / Fonnte (status device, pesan masuk, dll.)
 *
 * Provider: engine mandiri NUMART (wa-engine/). Atur di api/wa-app.config.php
 */

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => true,
    'service' => 'NUMART WA Gateway API',
    'version' => '1.0',
    'endpoints' => [
        'POST /api/v1/send.php' => 'Kirim pesan WA (teks/media)',
        'POST /api/v1/device.php' => 'Status device',
        'POST /api/v1/validate.php' => 'Validasi nomor',
        'POST /api/v1/webhook.php' => 'Webhook engine',
        'GET /api/v1/status.php' => 'Health check',
    ],
    'auth' => 'Header Authorization: TOKEN (dari api/wa-gateway.config.php)',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
