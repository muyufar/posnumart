<?php
/**
 * Helper migrasi modul: pindah file ke modules/{modul}/ dan buat stub di root.
 * Usage: php tools/migrate-module.php {modul} {subdir} file1.php file2.php ...
 * subdir: pages|data|actions|lib
 */
if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

$root = dirname(__DIR__);
require $root . '/bootstrap/paths.php';

$modul = isset($argv[1]) ? $argv[1] : '';
$subdir = isset($argv[2]) ? $argv[2] : 'pages';
$files = array_slice($argv, 3);

if ($modul === '' || empty($files)) {
    fwrite(STDERR, "Usage: php tools/migrate-module.php {modul} {pages|data|actions|lib} file1.php ...\n");
    exit(1);
}

$allowed = array('pages', 'data', 'actions', 'lib', 'api');
if (!in_array($subdir, $allowed, true)) {
    fwrite(STDERR, "Invalid subdir: $subdir\n");
    exit(1);
}

$destDir = numart_path("modules/$modul/$subdir");
if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

$stubTpl = "<?php\nrequire __DIR__ . '/bootstrap/paths.php';\nchdir(NUMART_ROOT);\nrequire numart_path('modules/%s/%s/%s');\n";

foreach ($files as $file) {
    $file = basename($file);
    $src = $root . '/' . $file;
    $dest = $destDir . '/' . $file;

    if (!is_file($src)) {
        fwrite(STDERR, "Skip (not found): $file\n");
        continue;
    }

    // Skip if already a stub
    $head = file_get_contents($src, false, null, 0, 120);
    if (strpos($head, 'numart_stub') !== false || strpos($head, "numart_path('modules/") !== false) {
        fwrite(STDERR, "Skip (already stub): $file\n");
        continue;
    }

    if (!rename($src, $dest)) {
        fwrite(STDERR, "Failed move: $file\n");
        continue;
    }

    file_put_contents($src, sprintf($stubTpl, $modul, $subdir, $file));
    echo "OK: $file -> modules/$modul/$subdir/$file\n";
}

echo "Done.\n";
