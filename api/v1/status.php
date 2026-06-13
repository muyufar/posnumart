<?php
/**
 * Health check API Gateway (tanpa auth — tidak mengekspos token).
 */
require_once __DIR__ . '/../wa-gateway-lib.php';

wa_gateway_handle_options();

$cfg = wa_gateway_load_config();
$keys = is_array($cfg['keys'] ?? null) ? $cfg['keys'] : [];
$activeKeys = 0;
foreach ($keys as $k) {
    if (is_array($k) && !empty($k['enabled']) && trim((string) ($k['token'] ?? '')) !== '') {
        $activeKeys++;
    }
}

wa_gateway_json([
    'status' => true,
    'service' => 'NUMART WA Gateway',
    'version' => '1.0',
    'gateway_enabled' => !empty($cfg['enabled']),
    'api_keys_active' => $activeKeys,
    'provider' => wa_get_provider(),
    'provider_label' => wa_provider_label(),
    'provider_configured' => wa_provider_configured(),
    'time' => date('c'),
]);
