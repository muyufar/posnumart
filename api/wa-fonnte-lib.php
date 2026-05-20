<?php
/**
 * Pustaka bersama Fonnte (token dari api/no.js).
 * Dipakai oleh send-wa-fonnte.php dan cron pengingat otomatis.
 */

if (!function_exists('fonnte_load_no_js')) {
    /**
     * @return array{token?: string, wa_number?: string}
     */
    function fonnte_load_no_js($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $out = [];
        $first = ltrim($raw);
        if ($first !== '' && ($first[0] === '{' || $first[0] === '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) {
                    $out[strtolower((string) $k)] = is_scalar($v) ? trim((string) $v) : '';
                }
            }
        } else {
            $lines = preg_split("/\r\n|\n|\r/", $raw);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strncmp($line, '//', 2) === 0) {
                    continue;
                }
                if (preg_match('/^\s*(\w+)\s*[:=]\s*(.+)$/u', $line, $m)) {
                    $key = strtolower($m[1]);
                    $out[$key] = trim($m[2]);
                }
            }
        }

        $token = $out['token'] ?? $out['fonnte_token'] ?? $out['authorization'] ?? '';
        $wa = $out['wa_number'] ?? $out['wa'] ?? $out['phone'] ?? '';

        return [
            'token' => $token,
            'wa_number' => $wa,
        ];
    }

    function fonnte_config_error_hint($path)
    {
        if (!is_file($path)) {
            return 'Berkas api/no.js tidak ada. Buat file tersebut di folder api.';
        }
        if (!is_readable($path)) {
            return 'api/no.js tidak bisa dibaca (izin file).';
        }
        $raw = file_get_contents($path);
        if ($raw === false || trim((string) $raw) === '') {
            return 'api/no.js kosong. Simpan isi file (Ctrl+S), lalu isi token, contoh: token : TOKEN_FONNTE_ANDA';
        }

        return 'Token Fonnte tidak ditemukan di api/no.js. Gunakan baris: token : TOKEN_ANDA atau JSON: {"token":"TOKEN_ANDA"}';
    }

    function fonnte_normalize_id_phone($raw)
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

    function fonnte_response_ok($decoded)
    {
        if (!is_array($decoded)) {
            return false;
        }
        if (isset($decoded['status']) && $decoded['status'] === true) {
            return true;
        }
        if (isset($decoded['Status']) && $decoded['Status'] === true) {
            return true;
        }
        return false;
    }

    /**
     * @param list<array{target: string, message: string, delay?: string}> $dataPayload
     */
    function fonnte_curl_send($token, array $dataPayload)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'data' => json_encode($dataPayload, JSON_UNESCAPED_UNICODE),
                'typing' => 'false',
            ],
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $token,
            ],
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($curl);
        if ($response === false) {
            $err = curl_error($curl);
            curl_close($curl);
            return json_encode(['status' => false, 'reason' => 'curl: ' . $err]);
        }
        curl_close($curl);
        return $response;
    }
}

if (!function_exists('wa_fonnte_parse_delay_seconds')) {
    function wa_fonnte_parse_delay_seconds(string $delayBetween, int $fallback = 3): int
    {
        if (preg_match('/^(\d+)/', $delayBetween, $m)) {
            return max(1, (int) $m[1]);
        }
        return max(1, $fallback);
    }
}

/**
 * Kirim satu per satu (bukan 25 sekaligus dalam satu payload Fonnte).
 *
 * @param list<array{target: string, message: string}> $built
 * @return array{success: bool, sent_attempts: int, chunks: int, fonnte_results: list, message: string}
 */
function wa_fonnte_send_built(array $built, string $delayBetween = '3')
{
    if ($built === []) {
        return [
            'success' => false,
            'sent_attempts' => 0,
            'chunks' => 0,
            'fonnte_results' => [],
            'message' => 'Tidak ada penerima valid',
        ];
    }

    @set_time_limit(0);

    $delaySec = wa_fonnte_parse_delay_seconds($delayBetween, 3);

    $configPath = __DIR__ . DIRECTORY_SEPARATOR . 'no.js';
    $config = fonnte_load_no_js($configPath);
    $token = $config['token'] ?? '';
    if ($token === '') {
        return [
            'success' => false,
            'sent_attempts' => 0,
            'chunks' => 0,
            'fonnte_results' => [],
            'message' => fonnte_config_error_hint($configPath),
        ];
    }

    $built = array_slice($built, 0, 25);
    $chunkResponses = [];
    $allOk = true;
    $sent = 0;

    foreach ($built as $idx => $item) {
        $dataPayload = [[
            'target' => $item['target'],
            'message' => $item['message'],
        ]];

        $raw = fonnte_curl_send($token, $dataPayload);
        $decoded = json_decode($raw, true);
        $ok = fonnte_response_ok($decoded);
        $chunkResponses[] = [
            'target' => $item['target'],
            'http_raw' => $raw,
            'parsed' => $decoded,
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
        'fonnte_results' => $chunkResponses,
        'message' => $allOk
            ? 'Pesan dikirim satu per satu ke Fonnte (' . count($built) . ' nomor)'
            : 'Sebagian atau semua pengiriman gagal; cek fonnte_results',
    ];
}
