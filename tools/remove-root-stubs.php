<?php
/**
 * Hapus stub root yang hanya forward ke modules/ (diganti router .htaccess).
 * CLI: php tools/remove-root-stubs.php [--dry-run]
 */
if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

$root = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv, true);

$keep = array(
    'index.php', 'bo.php', 'bo-grafik.php', 'default.php', 'test.php',
    'functions.php', 'functions1.php', 'functions-18.php',
    '_footer.php', '_header.php', '_header2.php', '_nav.php', '_nav2.php',
    '_sidebar.php', '_sidebar2.php', '_header-artibut.php', '_header-origin.php', '_footerlaporan.php',
);

$removed = 0;
$kept = 0;

foreach (glob($root . '/*.php') as $file) {
    $name = basename($file);
    if (in_array($name, $keep, true)) {
        $kept++;
        continue;
    }
    $content = file_get_contents($file);
    if (strpos($content, "numart_path('modules/") === false) {
        echo "KEEP (not module stub): $name\n";
        $kept++;
        continue;
    }
    if ($dryRun) {
        echo "WOULD REMOVE: $name\n";
    } else {
        unlink($file);
        echo "REMOVED: $name\n";
    }
    $removed++;
}

echo "\nRemoved: $removed, Kept: $kept" . ($dryRun ? ' (dry-run)' : '') . "\n";
