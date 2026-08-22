<?php
/**
 * Perbaiki path absolut di file modul setelah dipindah.
 */
if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

$root = dirname(__DIR__);
require $root . '/bootstrap/paths.php';

$dir = isset($argv[1]) ? $argv[1] : 'modules';
$target = numart_path($dir);
if (!is_dir($target)) {
    fwrite(STDERR, "Directory not found: $target\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));

$replacements = array(
    "require __DIR__ . '/vendor/autoload.php'" => "require numart_path('vendor/autoload.php')",
    'require __DIR__ . "/vendor/autoload.php"' => 'require numart_path("vendor/autoload.php")',
    "require_once __DIR__ . '/vendor/autoload.php'" => "require_once numart_path('vendor/autoload.php')",
    "include __DIR__ . '/vendor/autoload.php'" => "include numart_path('vendor/autoload.php')",
    "require __DIR__ . '/aksi/" => "require numart_path('aksi/",
    "require_once __DIR__ . '/aksi/" => "require_once numart_path('aksi/",
    "include __DIR__ . '/aksi/" => "include numart_path('aksi/",
    "include_once __DIR__ . '/aksi/" => "include_once numart_path('aksi/",
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $content = file_get_contents($path);
    $orig = $content;

    foreach ($replacements as $from => $to) {
        if (strpos($from, "numart_path('aksi/") !== false) {
            // Fix closing quote: '/aksi/foo.php' -> 'aksi/foo.php')
            $content = preg_replace(
                '/require(_once)? __DIR__ \. \'\/aksi\/([^\']+)\'/',
                'require$1 numart_path(\'aksi/$2\'',
                $content
            );
            $content = preg_replace(
                '/include(_once)? __DIR__ \. \'\/aksi\/([^\']+)\'/',
                'include$1 numart_path(\'aksi/$2\'',
                $content
            );
        } else {
            $content = str_replace($from, $to, $content);
        }
    }

    // Fix numart_path('aksi/...' missing closing paren - add ) before semicolon
    $content = preg_replace(
        "/numart_path\\('aksi\\/([^']+)'\\s*;/",
        "numart_path('aksi/$1');",
        $content
    );

    if (strpos($content, 'numart_path(') !== false && strpos($content, 'bootstrap/paths.php') === false) {
        $depth = substr_count(str_replace('\\', '/', str_replace($root . '/', '', $path)), '/');
        $bootstrapRel = str_repeat('/..', $depth + 1) . '/bootstrap/paths.php';
        $content = preg_replace(
            '/^<\?php\s*\n/',
            "<?php\nrequire_once __DIR__ . '$bootstrapRel';\n",
            $content,
            1
        );
    }

    if ($content !== $orig) {
        file_put_contents($path, $content);
        echo "Fixed: $path\n";
    }
}

echo "Done.\n";
