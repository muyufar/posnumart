<?php
/**
 * Inisialisasi aman untuk halaman customer-wa-blast (hindari blank page di live).
 *
 * @return array{
 *   error: string,
 *   limits: array,
 *   provider_label: string,
 *   provider_configured: bool,
 *   sent_today_by_phone: array
 * }
 */
function wa_blast_page_init($conn, $sessionCabang)
{
    $apiDir = __DIR__ . DIRECTORY_SEPARATOR;
    $required = [
        'wa-blast-lib.php',
        'wa-fonnte-lib.php',
        'wa-official-lib.php',
        'wa-send-lib.php',
        'wa-send-settings-lib.php',
        'wa-blast-schema.php',
    ];

    foreach ($required as $file) {
        if (!is_file($apiDir . $file)) {
            return wa_blast_page_init_fallback(
                'Modul WA belum lengkap di server (tidak ada api/' . $file . '). Upload folder api/ dari project terbaru.'
            );
        }
    }

    try {
        require_once $apiDir . 'wa-blast-schema.php';
        require_once $apiDir . 'wa-blast-lib.php';
        require_once $apiDir . 'wa-fonnte-lib.php';
        require_once $apiDir . 'wa-official-lib.php';
        require_once $apiDir . 'wa-send-lib.php';
        require_once $apiDir . 'wa-send-settings-lib.php';

        wa_blast_ensure_schema($conn);
        wa_send_settings_ensure_schema($conn);

        $limits = wa_send_settings_get($conn, (int) $sessionCabang);
        $sentToday = [];
        foreach (wa_blast_get_sent_today_rows($conn, (int) $sessionCabang) as $row) {
            $sentToday[$row['phone_key']] = $row;
        }

        return [
            'error' => '',
            'limits' => $limits,
            'provider_label' => wa_provider_label(),
            'provider_configured' => wa_provider_configured(),
            'sent_today_by_phone' => $sentToday,
        ];
    } catch (Throwable $e) {
        return wa_blast_page_init_fallback(
            'Gagal memuat modul WA: ' . $e->getMessage()
        );
    }
}

/**
 * @return array{error: string, limits: array, provider_label: string, provider_configured: bool, sent_today_by_phone: array}
 */
function wa_blast_page_init_fallback($message)
{
    return [
        'error' => $message,
        'limits' => [
            'max_contacts_per_batch' => 25,
            'min_interval_minutes' => 120,
            'delay_seconds_per_contact' => 3,
            'last_send_at' => null,
        ],
        'provider_label' => 'Fonnte',
        'provider_configured' => false,
        'sent_today_by_phone' => [],
    ];
}
