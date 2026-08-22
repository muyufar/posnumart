<?php
/**
 * Generate bootstrap/routes-map.php dari scan modules/{modul}/{pages|data|actions}/
 * CLI: php tools/generate-routes.php
 */
if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

$root = dirname(__DIR__);
require $root . '/bootstrap/paths.php';

$subdirs = array('pages', 'data', 'actions');
$routes = array();
$duplicates = array();

foreach (glob(numart_path('modules/*'), GLOB_ONLYDIR) as $modDir) {
    $mod = basename($modDir);
    foreach ($subdirs as $sub) {
        $dir = $modDir . '/' . $sub;
        if (!is_dir($dir)) {
            continue;
        }
        foreach (glob($dir . '/*.php') as $file) {
            $base = basename($file, '.php');
            if ($base === '' || $base[0] === '_') {
                continue;
            }
            $rel = 'modules/' . $mod . '/' . $sub . '/' . basename($file);
            if (isset($routes[$base])) {
                $duplicates[$base][] = $rel;
                $duplicates[$base][] = $routes[$base];
                continue;
            }
            $routes[$base] = $rel;
        }
    }
}

ksort($routes);

$out = numart_path('bootstrap/routes-map.php');
$php = "<?php\n/**\n * Auto-generated route map — jangan edit manual.\n * Regenerate: php tools/generate-routes.php\n */\nreturn " . var_export($routes, true) . ";\n";
file_put_contents($out, $php);

echo 'Routes: ' . count($routes) . "\n";
if (!empty($duplicates)) {
    echo "WARN duplicates (first wins):\n";
    foreach ($duplicates as $name => $paths) {
        echo "  $name: " . implode(' vs ', array_unique($paths)) . "\n";
    }
}
echo "Written: $out\n";
