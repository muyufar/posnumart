<?php
/**
 * Autentikasi API Gateway WA (mirip Fonnte token).
 */

if (!function_exists('wa_gateway_load_config')) {
    function wa_gateway_config_path()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'wa-gateway.config.php';
    }

    /**
     * @return array<string, mixed>
     */
    function wa_gateway_load_config()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $defaults = [
            'enabled' => true,
            'keys' => [],
            'default_delay' => 2,
            'connect_only' => true,
            'log_messages' => true,
            'webhook_secret' => '',
        ];

        $path = wa_gateway_config_path();
        if (is_file($path)) {
            $loaded = include $path;
            if (is_array($loaded)) {
                $cached = array_replace_recursive($defaults, $loaded);
                return $cached;
            }
        }

        $cached = $defaults;
        return $cached;
    }

    function wa_gateway_extract_token()
    {
        $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if ($auth === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $k => $v) {
                if (strtolower((string) $k) === 'authorization') {
                    $auth = (string) $v;
                    break;
                }
            }
        }

        if ($auth !== '') {
            if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
                return trim($m[1]);
            }
            return trim($auth);
        }

        if (!empty($_POST['token'])) {
            return trim((string) $_POST['token']);
        }

        if (!empty($_GET['token'])) {
            return trim((string) $_GET['token']);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (is_array($input) && !empty($input['token'])) {
            return trim((string) $input['token']);
        }

        return '';
    }

    /**
     * @return array{ok: bool, key?: array, message?: string, http_code?: int}
     */
    function wa_gateway_authenticate()
    {
        $cfg = wa_gateway_load_config();
        if (empty($cfg['enabled'])) {
            return ['ok' => false, 'message' => 'API gateway dinonaktifkan', 'http_code' => 503];
        }

        $token = wa_gateway_extract_token();
        if ($token === '') {
            return ['ok' => false, 'message' => 'Token API wajib (header Authorization)', 'http_code' => 401];
        }

        $keys = $cfg['keys'] ?? [];
        if (!is_array($keys) || $keys === []) {
            return ['ok' => false, 'message' => 'API key belum dikonfigurasi (wa-gateway.config.php)', 'http_code' => 503];
        }

        foreach ($keys as $key) {
            if (!is_array($key)) {
                continue;
            }
            if (empty($key['enabled'])) {
                continue;
            }
            $expected = (string) ($key['token'] ?? '');
            if ($expected === '') {
                continue;
            }
            if (hash_equals($expected, $token)) {
                return [
                    'ok' => true,
                    'key' => $key,
                ];
            }
        }

        return ['ok' => false, 'message' => 'Token tidak valid', 'http_code' => 403];
    }

    function wa_gateway_check_rate_limit($conn, $keyName, $limitPerMinute)
    {
        $limitPerMinute = max(1, (int) $limitPerMinute);
        $window = (int) floor(time() / 60);
        $rateKey = 'gw:' . preg_replace('/[^a-zA-Z0-9:_-]/', '', (string) $keyName) . ':' . $window;

        mysqli_query(
            $conn,
            "INSERT INTO wa_gateway_rate_limit (rate_key, window_start, hit_count)
             VALUES ('" . mysqli_real_escape_string($conn, $rateKey) . "', $window, 1)
             ON DUPLICATE KEY UPDATE hit_count = hit_count + 1"
        );

        $res = mysqli_query(
            $conn,
            "SELECT hit_count FROM wa_gateway_rate_limit WHERE rate_key = '" . mysqli_real_escape_string($conn, $rateKey) . "' LIMIT 1"
        );
        $row = $res ? mysqli_fetch_assoc($res) : null;
        $hits = (int) ($row['hit_count'] ?? 0);

        if ($hits > $limitPerMinute) {
            return false;
        }

        return true;
    }
}
