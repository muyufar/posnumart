<?php
/**
 * Validasi nomor WhatsApp — engine mandiri NUMART.
 */
require_once __DIR__ . '/../wa-gateway-lib.php';

wa_gateway_handle_options();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    wa_gateway_json(['status' => false, 'reason' => 'Method not allowed — gunakan POST'], 405);
}

wa_gateway_require_auth();
$input = wa_gateway_read_input();

$target = (string) ($input['target'] ?? $input['phone'] ?? '');
if ($target === '') {
    wa_gateway_json(['status' => false, 'reason' => 'Parameter target wajib'], 400);
}

$normalized = wa_normalize_id_phone($target);
if ($normalized === '') {
    wa_gateway_json(['status' => false, 'reason' => 'Format nomor tidak valid', 'target' => $target], 400);
}

$result = wa_local_validate_number($target);
$parsed = $result['parsed'] ?? [];

wa_gateway_json([
    'status' => !empty($result['success']),
    'target' => $normalized,
    'provider' => 'local',
    'detail' => is_array($parsed) ? $parsed : null,
    'reason' => is_array($parsed) ? ($parsed['reason'] ?? null) : null,
], !empty($result['success']) ? 200 : 422);
