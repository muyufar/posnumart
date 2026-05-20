<?php
/**
 * Lapisan pengiriman WA: Fonnte atau WhatsApp Cloud API (resmi).
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-fonnte-lib.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-official-lib.php';

if (!function_exists('wa_app_config_path')) {
    function wa_app_config_path(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'wa-official.config.php';
    }
}

if (!function_exists('wa_load_app_config')) {
    /**
     * @return array{provider: string, official: array<string, mixed>}
     */
    function wa_load_app_config(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $defaults = [
            'provider' => 'fonnte',
            'official' => [
                'access_token' => '',
                'phone_number_id' => '',
                'api_version' => 'v21.0',
                'send_mode' => 'template',
                'template' => [
                    'name' => '',
                    'language' => 'id',
                    'mode' => 'text_as_body',
                    'param_keys' => [],
                ],
                'delay_seconds' => 2,
                'max_per_request' => 50,
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
        $cfg = wa_load_app_config();
        $p = strtolower(trim((string) ($cfg['provider'] ?? 'fonnte')));
        return $p === 'official' ? 'official' : 'fonnte';
    }
}

if (!function_exists('wa_provider_label')) {
    function wa_provider_label(): string
    {
        return wa_get_provider() === 'official'
            ? 'WhatsApp Cloud API (resmi)'
            : 'Fonnte';
    }
}

if (!function_exists('wa_provider_configured')) {
    function wa_provider_configured(): bool
    {
        if (wa_get_provider() !== 'official') {
            $configPath = __DIR__ . DIRECTORY_SEPARATOR . 'no.js';
            $config = fonnte_load_no_js($configPath);
            return ($config['token'] ?? '') !== '';
        }

        $cfg = wa_load_app_config();
        $o = $cfg['official'] ?? [];
        return trim((string) ($o['access_token'] ?? '')) !== ''
            && trim((string) ($o['phone_number_id'] ?? '')) !== '';
    }
}

if (!function_exists('wa_max_recipients_per_request')) {
    function wa_max_recipients_per_request(): int
    {
        if (wa_get_provider() === 'official') {
            $cfg = wa_load_app_config();
            $max = (int) ($cfg['official']['max_per_request'] ?? 50);
            return $max > 0 ? min($max, 300) : 50;
        }
        return 300;
    }
}

/**
 * @param list<array{target: string, message: string, template_params?: array}> $built
 * @return array{success: bool, sent_attempts: int, chunks: int, provider: string, message: string, fonnte_results?: list, api_results?: list}
 */
function wa_send_built(array $built, string $delayBetween = '2')
{
    if (wa_get_provider() === 'official') {
        $cfg = wa_load_app_config();
        $official = $cfg['official'] ?? [];
        if (!is_array($official)) {
            $official = [];
        }
        $result = wa_official_send_built($built, $delayBetween, $official);
        return $result;
    }

    $result = wa_fonnte_send_built($built, $delayBetween);
    $result['provider'] = 'fonnte';
    return $result;
}
