<?php
/**
 * WhatsApp Cloud API (Meta) — pengiriman resmi.
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api
 */

if (!function_exists('wa_official_normalize_phone')) {
    function wa_official_normalize_phone($raw)
    {
        $d = preg_replace('/\D+/', '', (string) $raw);
        if ($d === '') {
            return '';
        }
        if (strncmp($d, '62', 2) === 0) {
            return $d;
        }
        if (isset($d[0]) && $d[0] === '0') {
            return '62' . substr($d, 1);
        }
        return '62' . $d;
    }
}

if (!function_exists('wa_official_parse_delay_seconds')) {
    function wa_official_parse_delay_seconds(string $delayBetween, int $fallback = 2): int
    {
        if (preg_match('/^(\d+)/', $delayBetween, $m)) {
            return max(1, (int) $m[1]);
        }
        return max(1, $fallback);
    }
}

if (!function_exists('wa_official_http_post')) {
    /**
     * @return array{ok: bool, http_code: int, body: string, parsed: array|null, error: string}
     */
    function wa_official_http_post(string $url, string $accessToken, array $payload)
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 60,
        ]);

        $body = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($body === false) {
            $err = curl_error($curl);
            curl_close($curl);
            return [
                'ok' => false,
                'http_code' => 0,
                'body' => '',
                'parsed' => null,
                'error' => 'curl: ' . $err,
            ];
        }
        curl_close($curl);

        $parsed = json_decode($body, true);
        $ok = $httpCode >= 200 && $httpCode < 300
            && is_array($parsed)
            && isset($parsed['messages'][0]['id']);

        $error = '';
        if (!$ok && is_array($parsed) && isset($parsed['error']['message'])) {
            $error = (string) $parsed['error']['message'];
        } elseif (!$ok) {
            $error = 'HTTP ' . $httpCode;
        }

        return [
            'ok' => $ok,
            'http_code' => $httpCode,
            'body' => (string) $body,
            'parsed' => is_array($parsed) ? $parsed : null,
            'error' => $error,
        ];
    }
}

if (!function_exists('wa_official_build_template_components')) {
    /**
     * @param array<string, mixed> $templateCfg
     * @param array<string, string> $paramValues keyed by param_keys
     */
    function wa_official_build_template_components(array $templateCfg, string $message, array $paramValues = []): array
    {
        $mode = (string) ($templateCfg['mode'] ?? 'text_as_body');
        $bodyParams = [];

        if ($mode === 'param_map') {
            $keys = $templateCfg['param_keys'] ?? [];
            if (!is_array($keys)) {
                $keys = [];
            }
            foreach ($keys as $key) {
                $key = (string) $key;
                $val = $paramValues[$key] ?? '';
                if ($val === '' && $key !== '') {
                    $val = '—';
                }
                $bodyParams[] = ['type' => 'text', 'text' => (string) $val];
            }
        } else {
            $bodyParams[] = ['type' => 'text', 'text' => $message];
        }

        if ($bodyParams === []) {
            return [];
        }

        return [
            [
                'type' => 'body',
                'parameters' => $bodyParams,
            ],
        ];
    }
}

if (!function_exists('wa_official_send_one')) {
    /**
     * @param array<string, mixed> $officialCfg
     * @return array{ok: bool, target: string, response: array, error: string}
     */
    function wa_official_send_one(array $officialCfg, string $to, string $message, array $extraParams = [])
    {
        $accessToken = trim((string) ($officialCfg['access_token'] ?? ''));
        $phoneNumberId = trim((string) ($officialCfg['phone_number_id'] ?? ''));
        $apiVersion = trim((string) ($officialCfg['api_version'] ?? 'v21.0'));
        if ($apiVersion === '') {
            $apiVersion = 'v21.0';
        }

        $to = wa_official_normalize_phone($to);
        if ($to === '') {
            return ['ok' => false, 'target' => '', 'response' => [], 'error' => 'Nomor kosong'];
        }
        if ($accessToken === '' || $phoneNumberId === '') {
            return [
                'ok' => false,
                'target' => $to,
                'response' => [],
                'error' => 'access_token atau phone_number_id belum diisi di wa-official.config.php',
            ];
        }

        $url = 'https://graph.facebook.com/' . $apiVersion . '/' . $phoneNumberId . '/messages';
        $sendMode = strtolower((string) ($officialCfg['send_mode'] ?? 'template'));

        if ($sendMode === 'text') {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message,
                ],
            ];
        } else {
            $templateCfg = $officialCfg['template'] ?? [];
            if (!is_array($templateCfg)) {
                $templateCfg = [];
            }
            $templateName = trim((string) ($templateCfg['name'] ?? ''));
            $language = trim((string) ($templateCfg['language'] ?? 'id'));
            if ($templateName === '') {
                return [
                    'ok' => false,
                    'target' => $to,
                    'response' => [],
                    'error' => 'template.name wajib diisi (buat template di Meta WhatsApp Manager)',
                ];
            }

            $components = wa_official_build_template_components($templateCfg, $message, $extraParams);
            $template = [
                'name' => $templateName,
                'language' => ['code' => $language],
            ];
            if ($components !== []) {
                $template['components'] = $components;
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'template',
                'template' => $template,
            ];
        }

        $http = wa_official_http_post($url, $accessToken, $payload);

        return [
            'ok' => $http['ok'],
            'target' => $to,
            'response' => [
                'http_code' => $http['http_code'],
                'parsed' => $http['parsed'],
                'body' => $http['body'],
            ],
            'error' => $http['error'],
        ];
    }
}

/**
 * @param list<array{target: string, message: string, template_params?: array}> $built
 * @param array<string, mixed> $officialCfg
 * @return array{success: bool, sent_attempts: int, chunks: int, provider: string, api_results: list, message: string}
 */
function wa_official_send_built(array $built, string $delayBetween, array $officialCfg)
{
    if ($built === []) {
        return [
            'success' => false,
            'sent_attempts' => 0,
            'chunks' => 0,
            'provider' => 'official',
            'api_results' => [],
            'message' => 'Tidak ada penerima valid',
        ];
    }

    @set_time_limit(0);
    $built = array_slice($built, 0, 25);

    $delaySec = wa_official_parse_delay_seconds(
        $delayBetween,
        (int) ($officialCfg['delay_seconds'] ?? 2)
    );

    $apiResults = [];
    $allOk = true;
    $sent = 0;

    foreach ($built as $idx => $item) {
        $target = (string) ($item['target'] ?? '');
        $message = (string) ($item['message'] ?? '');
        $extra = [];
        if (isset($item['template_params']) && is_array($item['template_params'])) {
            $extra = $item['template_params'];
        }

        $one = wa_official_send_one($officialCfg, $target, $message, $extra);
        $apiResults[] = $one;
        if ($one['ok']) {
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
        'chunks' => 1,
        'provider' => 'official',
        'api_results' => $apiResults,
        'message' => $allOk
            ? 'Pesan terkirim via WhatsApp Cloud API'
            : 'Sebagian atau semua pengiriman gagal; cek api_results',
    ];
}
