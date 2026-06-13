<?php
/**
 * Status device WhatsApp — engine mandiri NUMART.
 */
require_once __DIR__ . '/../wa-gateway-lib.php';

wa_gateway_handle_options();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    wa_gateway_json(['status' => false, 'reason' => 'Method not allowed'], 405);
}

wa_gateway_require_auth();

$result = wa_local_device_profile();
$parsed = $result['parsed'] ?? [];

if (!empty($result['success']) && is_array($parsed)) {
    wa_gateway_json([
        'status' => true,
        'provider' => 'local',
        'device' => (string) ($parsed['device'] ?? ''),
        'device_status' => (string) ($parsed['device_status'] ?? ''),
        'name' => (string) ($parsed['name'] ?? ''),
        'package' => (string) ($parsed['package'] ?? 'numart-local'),
        'quota' => (string) ($parsed['quota'] ?? 'unlimited'),
        'messages' => (string) ($parsed['messages'] ?? '0'),
        'expired' => (string) ($parsed['expired'] ?? 'never'),
    ]);
}

wa_gateway_json([
    'status' => false,
    'reason' => (string) ($result['message'] ?? 'Gagal mengambil status device'),
    'provider' => 'local',
    'parsed' => $parsed,
], 503);
