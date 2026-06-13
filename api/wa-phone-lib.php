<?php
/**
 * Utilitas nomor telepon & jeda pengiriman WA.
 */

if (!function_exists('wa_normalize_id_phone')) {
    function wa_normalize_id_phone($raw): string
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

if (!function_exists('wa_parse_delay_seconds')) {
    function wa_parse_delay_seconds(string $delayBetween, int $fallback = 3): int
    {
        if (preg_match('/^(\d+)/', $delayBetween, $m)) {
            return max(1, (int) $m[1]);
        }
        return max(1, $fallback);
    }
}
