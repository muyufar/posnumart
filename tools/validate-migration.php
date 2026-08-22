<?php
/**
 * Validasi stub root & aksi forward ke modul.
 */
if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

$root = dirname(__DIR__);
require $root . '/bootstrap/paths.php';

$errors = 0;
$ok = 0;

foreach (glob($root . '/*.php') as $stub) {
    $content = file_get_contents($stub);
    if (strpos($content, "numart_path('modules/") === false) {
        continue;
    }
    if (!preg_match("/numart_path\\('modules\\/([^']+)'\\)/", $content, $m)) {
        $errors++;
        echo "Parse fail: " . basename($stub) . "\n";
        continue;
    }
    if (!preg_match("/modules\\/[^']+/", $content, $pathMatch)) {
        continue;
    }
    preg_match("/numart_path\\('([^']+)'\\)/", $content, $full);
    if (empty($full[1])) {
        continue;
    }
    $target = numart_path($full[1]);
    if (!is_file($target)) {
        $errors++;
        echo "MISSING: " . basename($stub) . " -> $target\n";
    } else {
        $ok++;
    }
}

foreach (glob($root . '/aksi/*.php') as $stub) {
    $content = file_get_contents($stub);
    if (strpos($content, "numart_path('modules/") === false) {
        continue;
    }
    preg_match("/numart_path\\('([^']+)'\\)/", $content, $full);
    if (empty($full[1])) {
        continue;
    }
    $target = numart_path($full[1]);
    if (!is_file($target)) {
        $errors++;
        echo "MISSING aksi stub: " . basename($stub) . " -> $target\n";
    } else {
        $ok++;
    }
}

echo "\nOK: $ok, Errors: $errors\n";
exit($errors > 0 ? 1 : 0);
