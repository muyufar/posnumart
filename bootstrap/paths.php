<?php
/**
 * Path bootstrap — single source of truth for project root.
 */
if (!defined('NUMART_ROOT')) {
    define('NUMART_ROOT', dirname(__DIR__));
}

$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

if (!function_exists('numart_path')) {
    function numart_path($rel)
    {
        return NUMART_ROOT . '/' . ltrim(str_replace('\\', '/', $rel), '/');
    }
}

if (!function_exists('numart_require')) {
    function numart_require($rel)
    {
        require numart_path($rel);
    }
}

if (!function_exists('numart_require_once')) {
    function numart_require_once($rel)
    {
        require_once numart_path($rel);
    }
}

if (!function_exists('numart_require_layout')) {
    function numart_require_layout($file)
    {
        require numart_path('shared/layout/' . ltrim($file, '/'));
    }
}

if (!function_exists('numart_stub')) {
    /** Forward root URL stub to module file. */
    function numart_stub($moduleRel)
    {
        if (!defined('NUMART_ROOT')) {
            require __DIR__ . '/paths.php';
        }
        require numart_path($moduleRel);
    }
}

/**
 * Base URL web relatif app (mis. /numart/ lokal, / di live root).
 */
if (!function_exists('numart_web_base')) {
    function numart_web_base()
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }

        if (defined('NUMART_WEB_BASE')) {
            $base = NUMART_WEB_BASE;
            return $base;
        }

        // Paling andal: bandingkan DOCUMENT_ROOT vs folder app
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
        $appRoot = realpath(NUMART_ROOT);
        if ($docRoot && $appRoot && strpos($appRoot, $docRoot) === 0) {
            $rel = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
            if ($rel === '' || $rel === '/') {
                $base = '/';
            } else {
                $base = rtrim($rel, '/') . '/';
            }
            return $base;
        }

        // Fallback dari SCRIPT_NAME (front controller bootstrap/front.php)
        $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
        $dir = dirname($script);
        if (preg_match('#/bootstrap$#', $dir)) {
            $dir = dirname($dir);
        }
        if ($dir === '/' || $dir === '\\' || $dir === '.' || $dir === '') {
            $base = '/';
        } else {
            $base = rtrim($dir, '/') . '/';
        }
        return $base;
    }
}

if (!function_exists('numart_url')) {
    /** URL absolut relatif root web — aman untuk subfolder lokal & live. */
    function numart_url($path = '')
    {
        $path = ltrim(str_replace('\\', '/', (string) $path), '/');
        $base = numart_web_base();
        if ($path === '') {
            return $base;
        }
        return $base . $path;
    }
}
