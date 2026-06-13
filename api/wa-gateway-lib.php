<?php
/**
 * Pustaka API Gateway WA — permukaan publik mirip Fonnte.
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-gateway-auth-lib.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-gateway-schema.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-send-lib.php';

if (!function_exists('wa_gateway_json')) {
    function wa_gateway_json($data, $httpCode = 200)
    {
        http_response_code((int) $httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    function wa_gateway_handle_options()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            wa_gateway_json(['status' => true, 'message' => 'OK'], 200);
        }
    }

    /**
     * @return array<string, mixed>
     */
    function wa_gateway_read_input()
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (strpos($contentType, 'application/json') !== false) {
            $json = json_decode(file_get_contents('php://input'), true);
            return is_array($json) ? $json : [];
        }

        if ($_POST !== []) {
            return $_POST;
        }

        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return [];
        }

        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }

        parse_str($raw, $parsed);
        return is_array($parsed) ? $parsed : [];
    }

    function wa_gateway_client_ip()
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    function wa_gateway_log_message($conn, array $ctx)
    {
        $cfg = wa_gateway_load_config();
        if (empty($cfg['log_messages'])) {
            return 0;
        }

        wa_gateway_ensure_schema($conn);

        $apiKeyName = mysqli_real_escape_string($conn, (string) ($ctx['api_key_name'] ?? ''));
        $target = mysqli_real_escape_string($conn, (string) ($ctx['target'] ?? ''));
        $messageType = mysqli_real_escape_string($conn, (string) ($ctx['message_type'] ?? 'text'));
        $preview = mysqli_real_escape_string($conn, mb_substr((string) ($ctx['message_preview'] ?? ''), 0, 255));
        $mediaUrl = mysqli_real_escape_string($conn, mb_substr((string) ($ctx['media_url'] ?? ''), 0, 500));
        $provider = mysqli_real_escape_string($conn, (string) ($ctx['provider'] ?? 'local'));
        $providerStatus = !empty($ctx['provider_status']) ? 1 : 0;
        $providerResponse = mysqli_real_escape_string($conn, (string) ($ctx['provider_response'] ?? ''));
        $providerMessageId = mysqli_real_escape_string($conn, (string) ($ctx['provider_message_id'] ?? ''));
        $ip = mysqli_real_escape_string($conn, wa_gateway_client_ip());

        mysqli_query(
            $conn,
            "INSERT INTO wa_gateway_message_log
                (api_key_name, target, message_type, message_preview, media_url, provider, provider_status, provider_response, provider_message_id, ip_address)
             VALUES
                ('$apiKeyName', '$target', '$messageType', '$preview', '$mediaUrl', '$provider', $providerStatus, '$providerResponse', '$providerMessageId', '$ip')"
        );

        return (int) mysqli_insert_id($conn);
    }

    function wa_gateway_log_webhook($conn, $eventType, $payload)
    {
        wa_gateway_ensure_schema($conn);
        $eventTypeEsc = mysqli_real_escape_string($conn, mb_substr((string) $eventType, 0, 64));
        $payloadEsc = mysqli_real_escape_string($conn, is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE));
        $ip = mysqli_real_escape_string($conn, wa_gateway_client_ip());

        mysqli_query(
            $conn,
            "INSERT INTO wa_gateway_webhook_events (event_type, payload, ip_address)
             VALUES ('$eventTypeEsc', '$payloadEsc', '$ip')"
        );

        return (int) mysqli_insert_id($conn);
    }

    /**
     * @return array{status: bool, detail: string, id: list, reason?: string, log_id?: int, provider?: string, raw?: mixed}
     */
    function wa_gateway_format_send_response(array $sendResult, $logId = 0)
    {
        $parsed = $sendResult['parsed'] ?? null;
        $ids = [];
        if (is_array($parsed)) {
            if (!empty($parsed['id'])) {
                $ids = is_array($parsed['id']) ? $parsed['id'] : [(string) $parsed['id']];
            } elseif (!empty($parsed['message_id'])) {
                $ids = [(string) $parsed['message_id']];
            }
        }

        $out = [
            'status' => !empty($sendResult['success']),
            'detail' => (string) ($sendResult['message'] ?? ($sendResult['success'] ? 'success' : 'failed')),
            'id' => $ids,
            'target' => (string) ($sendResult['target'] ?? ''),
            'provider' => (string) ($sendResult['provider'] ?? wa_get_provider()),
            'log_id' => (int) $logId,
        ];

        if (!$out['status'] && is_array($parsed) && !empty($parsed['reason'])) {
            $out['reason'] = (string) $parsed['reason'];
        }

        return $out;
    }

    /**
     * Kirim pesan via engine mandiri NUMART.
     *
     * @param array<string, mixed> $input
     */
    function wa_gateway_send(array $input, array $apiKey)
    {
        global $conn;

        $cfg = wa_gateway_load_config();
        $target = (string) ($input['target'] ?? $input['phone'] ?? $input['to'] ?? '');
        $message = (string) ($input['message'] ?? $input['text'] ?? '');
        $url = (string) ($input['url'] ?? $input['image'] ?? $input['file'] ?? '');
        $filename = (string) ($input['filename'] ?? '');
        $delay = $input['delay'] ?? ($cfg['default_delay'] ?? 2);

        if ($target === '') {
            return ['success' => false, 'message' => 'Parameter target wajib', 'parsed' => null];
        }

        if ($message === '' && $url === '') {
            return ['success' => false, 'message' => 'Parameter message atau url wajib', 'parsed' => null];
        }

        $keyName = (string) ($apiKey['name'] ?? 'default');
        $rateLimit = (int) ($apiKey['rate_per_minute'] ?? 60);
        if (isset($conn) && $conn instanceof mysqli) {
            wa_gateway_ensure_schema($conn);
            if (!wa_gateway_check_rate_limit($conn, $keyName, $rateLimit)) {
                return ['success' => false, 'message' => 'Rate limit terlampaui', 'parsed' => ['status' => false, 'reason' => 'rate limit']];
            }
        }

        $options = [
            'delay' => $delay,
        ];
        if (array_key_exists('connectOnly', $input)) {
            $options['connectOnly'] = (bool) $input['connectOnly'];
        } elseif (!empty($cfg['connect_only'])) {
            $options['connectOnly'] = true;
        }
        if ($url !== '') {
            $options['url'] = $url;
        }
        if ($filename !== '') {
            $options['filename'] = $filename;
        }

        $result = wa_local_send_message($target, $message, $options);
        $result['provider'] = 'local';
        return $result;
    }

    function wa_gateway_require_auth()
    {
        global $conn;

        wa_gateway_handle_options();

        $auth = wa_gateway_authenticate();
        if (empty($auth['ok'])) {
            wa_gateway_json([
                'status' => false,
                'reason' => (string) ($auth['message'] ?? 'Unauthorized'),
            ], (int) ($auth['http_code'] ?? 401));
        }

        return $auth['key'];
    }
}
