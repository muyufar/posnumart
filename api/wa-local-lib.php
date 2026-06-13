<?php
/**
 * Klien engine WhatsApp mandiri NUMART (clone Fonnte, tanpa pihak ketiga).
 * Engine berjalan di wa-engine/ (Node.js + Baileys).
 */

if (!function_exists('wa_local_config')) {
    /**
     * @return array<string, mixed>
     */
    function wa_local_config(): array
    {
        if (!function_exists('wa_load_app_config')) {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-send-lib.php';
        }
        $cfg = wa_load_app_config();
        $local = $cfg['local'] ?? [];
        return is_array($local) ? $local : [];
    }

    function wa_local_base_url(): string
    {
        $local = wa_local_config();
        $url = trim((string) ($local['base_url'] ?? 'http://127.0.0.1:3920'));
        return rtrim($url, '/');
    }

    function wa_local_api_secret(): string
    {
        $local = wa_local_config();
        return trim((string) ($local['api_secret'] ?? ''));
    }

    function wa_local_configured(): bool
    {
        return wa_local_api_secret() !== '';
    }

    /**
     * @param array<string, scalar|null> $postFields
     * @return array{ok: bool, http_code: int, http_raw: string, parsed: ?array}
     */
    function wa_local_engine_request(string $endpoint, array $postFields = [], string $method = 'POST')
    {
        $secret = wa_local_api_secret();
        if ($secret === '') {
            return [
                'ok' => false,
                'http_code' => 0,
                'http_raw' => json_encode(['status' => false, 'reason' => 'local engine api_secret kosong'], JSON_UNESCAPED_UNICODE),
                'parsed' => ['status' => false, 'reason' => 'local engine api_secret kosong'],
            ];
        }

        $endpoint = ltrim($endpoint, '/');
        $url = wa_local_base_url() . '/' . $endpoint;
        $method = strtoupper($method);

        $curl = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $secret,
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 5,
        ];

        if ($method === 'GET') {
            if ($postFields !== []) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($postFields);
                $opts[CURLOPT_URL] = $url;
            }
        } else {
            $opts[CURLOPT_POST] = true;
            // PHP array POSTFIELDS = multipart; engine Express hanya baca urlencoded/json.
            $opts[CURLOPT_POSTFIELDS] = http_build_query($postFields);
            $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
        }

        curl_setopt_array($curl, $opts);
        $response = curl_exec($curl);
        if ($response === false) {
            $err = curl_error($curl);
            curl_close($curl);
            $parsed = ['status' => false, 'reason' => 'engine offline: ' . $err];
            return [
                'ok' => false,
                'http_code' => 0,
                'http_raw' => json_encode($parsed, JSON_UNESCAPED_UNICODE),
                'parsed' => $parsed,
            ];
        }

        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $decoded = json_decode($response, true);

        $ok = is_array($decoded) && (!empty($decoded['status']) || !empty($decoded['ok']));

        return [
            'ok' => $ok,
            'http_code' => $httpCode,
            'http_raw' => (string) $response,
            'parsed' => is_array($decoded) ? $decoded : null,
        ];
    }

    function wa_local_engine_online(): bool
    {
        $res = wa_local_engine_request('health', [], 'GET');
        $parsed = $res['parsed'] ?? null;
        return is_array($parsed) && !empty($parsed['ok']);
    }

    function wa_local_config_error_hint(): string
    {
        if (!wa_local_configured()) {
            return 'Engine lokal belum dikonfigurasi. Isi local.api_secret di api/wa-app.config.php';
        }
        if (!wa_local_engine_online()) {
            return 'Engine WA belum berjalan. Jalankan: cd wa-engine && npm install && npm start';
        }
        return 'Engine lokal siap';
    }

    /**
     * @param array{url?: string, filename?: string, delay?: int|string, connectOnly?: bool} $options
     */
    function wa_local_send_message($target, $message, array $options = [])
    {
        if (!wa_local_configured()) {
            return [
                'success' => false,
                'message' => wa_local_config_error_hint(),
                'provider' => 'local',
                'parsed' => null,
            ];
        }

        $target = wa_normalize_id_phone($target);
        if ($target === '') {
            return [
                'success' => false,
                'message' => 'Nomor target tidak valid',
                'provider' => 'local',
                'parsed' => null,
            ];
        }

        $fields = [
            'target' => $target,
            'message' => (string) $message,
            'delay' => (string) ($options['delay'] ?? '0'),
        ];

        if (array_key_exists('connectOnly', $options)) {
            $fields['connectOnly'] = $options['connectOnly'] ? 'true' : 'false';
        }

        if (!empty($options['url'])) {
            $fields['url'] = (string) $options['url'];
        }
        if (!empty($options['filename'])) {
            $fields['filename'] = (string) $options['filename'];
        }

        $res = wa_local_engine_request('send', $fields);
        $parsed = $res['parsed'] ?? null;

        return [
            'success' => !empty($res['ok']),
            'message' => is_array($parsed)
                ? (string) ($parsed['detail'] ?? $parsed['reason'] ?? ($res['ok'] ? 'OK' : 'Gagal'))
                : 'Gagal',
            'provider' => 'local',
            'target' => $target,
            'http_code' => (int) ($res['http_code'] ?? 0),
            'http_raw' => (string) ($res['http_raw'] ?? ''),
            'parsed' => $parsed,
        ];
    }

    function wa_local_device_profile()
    {
        if (!wa_local_configured()) {
            return [
                'success' => false,
                'message' => wa_local_config_error_hint(),
                'parsed' => null,
            ];
        }

        $res = wa_local_engine_request('device', []);
        $parsed = $res['parsed'] ?? null;

        return [
            'success' => !empty($res['ok']),
            'message' => is_array($parsed)
                ? (string) ($parsed['reason'] ?? ($res['ok'] ? 'OK' : 'Gagal'))
                : 'Gagal',
            'parsed' => $parsed,
            'http_raw' => (string) ($res['http_raw'] ?? ''),
        ];
    }

    function wa_local_qr_status()
    {
        if (!wa_local_configured()) {
            return [
                'success' => false,
                'message' => wa_local_config_error_hint(),
                'parsed' => null,
            ];
        }

        $res = wa_local_engine_request('qr', [], 'GET');
        $parsed = $res['parsed'] ?? null;

        return [
            'success' => is_array($parsed),
            'parsed' => $parsed,
            'http_raw' => (string) ($res['http_raw'] ?? ''),
        ];
    }

    function wa_local_validate_number($target)
    {
        if (!wa_local_configured()) {
            return [
                'success' => false,
                'message' => wa_local_config_error_hint(),
                'parsed' => null,
            ];
        }

        $target = wa_normalize_id_phone($target);
        if ($target === '') {
            return [
                'success' => false,
                'message' => 'Nomor tidak valid',
                'parsed' => null,
            ];
        }

        $res = wa_local_engine_request('validate', ['target' => $target]);
        $parsed = $res['parsed'] ?? null;

        return [
            'success' => !empty($res['ok']),
            'target' => $target,
            'parsed' => $parsed,
            'http_raw' => (string) ($res['http_raw'] ?? ''),
        ];
    }

    function wa_local_logout()
    {
        return wa_local_engine_request('logout', []);
    }
}

/**
 * @param list<array{target: string, message: string}> $built
 */
function wa_local_send_built(array $built, string $delayBetween = '3')
{
    if ($built === []) {
        return [
            'success' => false,
            'sent_attempts' => 0,
            'chunks' => 0,
            'local_results' => [],
            'message' => 'Tidak ada penerima valid',
        ];
    }

    @set_time_limit(0);

    if (!wa_local_configured()) {
        return [
            'success' => false,
            'sent_attempts' => 0,
            'chunks' => 0,
            'local_results' => [],
            'message' => wa_local_config_error_hint(),
        ];
    }

    $delaySec = wa_parse_delay_seconds($delayBetween, 3);
    $built = array_slice($built, 0, 300);
    $results = [];
    $allOk = true;
    $sent = 0;

    foreach ($built as $idx => $item) {
        $res = wa_local_send_message($item['target'], $item['message'], ['delay' => '0']);
        $ok = !empty($res['success']);
        $results[] = [
            'target' => $item['target'],
            'http_raw' => $res['http_raw'] ?? '',
            'parsed' => $res['parsed'] ?? null,
            'ok' => $ok,
        ];

        if ($ok) {
            $sent++;
        } else {
            $allOk = false;
        }

        if ($idx < count($built) - 1) {
            sleep($delaySec);
        }
    }

    return [
        'success' => $allOk,
        'sent_attempts' => $sent,
        'chunks' => count($built),
        'local_results' => $results,
        'message' => $allOk
            ? 'Pesan dikirim via engine lokal (' . count($built) . ' nomor)'
            : 'Sebagian atau semua pengiriman gagal; cek local_results',
    ];
}
