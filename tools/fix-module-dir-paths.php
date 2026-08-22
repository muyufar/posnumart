<?php
/**
 * Perbaiki path __DIR__ rusak setelah migrasi modul.
 */
if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

$root = dirname(__DIR__);
require $root . '/bootstrap/paths.php';

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(numart_path('modules')));

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $content = file_get_contents($path);
    $orig = $content;

    // Bootstrap path salah (4 level) -> 3 level dari pages|data|actions|lib
    $content = str_replace(
        "require_once __DIR__ . '/../../../../bootstrap/paths.php';",
        "require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';",
        $content
    );

    $replacements = array(
        "require __DIR__ . '/_header-artibut.php'" => "require numart_path('shared/layout/_header-artibut.php')",
        "include __DIR__ . '/_header-artibut.php'" => "include numart_path('shared/layout/_header-artibut.php')",
        "include __DIR__ . '/_footer.php'" => "include numart_path('shared/layout/_footer.php')",
        "include __DIR__ . '/_barang-gambar-form.php'" => "include numart_path('modules/barang/pages/_barang-gambar-form.php')",
        "__DIR__ . '/api/" => "numart_path('api/",
        "__DIR__ . '/aksi/" => "numart_path('aksi/",
    );

    foreach ($replacements as $from => $to) {
        $content = str_replace($from, $to, $content);
    }

    // Tutup numart_path('api/foo' -> numart_path('api/foo')
    $content = preg_replace(
        "/numart_path\\('(api|aksi)\\/([^']+)'\\s*\\./",
        "numart_path('$1/$2') .",
        $content
    );

    // Fix broken: numart_path('api/foo' . '/bar' -> numart_path('api/foo/bar')
    $content = preg_replace(
        "/numart_path\\('(api|aksi)\\/([^']+)'\\) \\. '([^']+)'/",
        "numart_path('$1/$2$3')",
        $content
    );

    // Inject bootstrap if numart_path used without bootstrap
    if (strpos($content, 'numart_path(') !== false && strpos($content, 'bootstrap/paths.php') === false) {
        $content = preg_replace(
            '/^<\?php\s*\n/',
            "<?php\nrequire_once dirname(__DIR__, 3) . '/bootstrap/paths.php';\n",
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
