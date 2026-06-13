<?php
/**
 * Lapisan pengiriman WA — engine mandiri NUMART (wa-engine/).
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-phone-lib.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-local-lib.php';

if (!function_exists('wa_app_config_path')) {
    function wa_app_config_path(): string
    {
        $dir = __DIR__ . DIRECTORY_SEPARATOR;
        if (is_file($dir . 'wa-app.config.php')) {
            return $dir . 'wa-app.config.php';
        }
        return $dir . 'wa-official.config.php';
    }
}

if (!function_exists('wa_load_app_config')) {
    /**
     * @return array{local: array<string, mixed>}
     */
    function wa_load_app_config(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $defaults = [
            'local' => [
                'base_url' => 'http://127.0.0.1:3920',
                'api_secret' => '',
                'device_name' => 'NUMART Pusat',
            ],
            'cron' => [
                'business_hours_enabled' => true,
                'business_hours_start' => '07:00',
                'business_hours_end' => '21:00',
            ],
            'manual' => [
                'business_hours_enabled' => true,
                'respect_global_lock' => true,
                'reconnect_cooldown_minutes' => 30,
            ],
            'safety' => [
                'ramp_down_days' => 7,
                'ramp_down_hourly_min' => 10,
                'ramp_down_hourly_max' => 15,
            ],
        ];

        $path = wa_app_config_path();
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
}

if (!function_exists('wa_get_provider')) {
    function wa_get_provider(): string
    {
        return 'local';
    }
}

if (!function_exists('wa_provider_label')) {
    function wa_provider_label(): string
    {
        return 'NUMART WA Engine (mandiri)';
    }
}

if (!function_exists('wa_provider_configured')) {
    function wa_provider_configured(): bool
    {
        return wa_local_configured() && wa_local_engine_online();
    }
}

if (!function_exists('wa_max_recipients_per_request')) {
    function wa_max_recipients_per_request(): int
    {
        return 300;
    }
}

/**
 * @param list<array{target: string, message: string}> $built
 */
function wa_send_built(array $built, string $delayBetween = '2')
{
    $result = wa_local_send_built($built, $delayBetween);
    $result['provider'] = 'local';
    return $result;
}
