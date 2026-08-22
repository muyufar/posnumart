<?php
/**
 * Front controller — arahkan URL ke file modul tanpa stub per file di root.
 */
require __DIR__ . '/paths.php';

$route = isset($_GET['route']) ? trim((string) $_GET['route'], '/') : '';
$route = preg_replace('/\.php$/', '', $route);

if ($route === '' || $route === 'bootstrap/front') {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$mapFile = __DIR__ . '/routes-map.php';
if (!is_file($mapFile)) {
    http_response_code(500);
    echo 'Route map missing. Run: php tools/generate-routes.php';
    exit;
}

$routes = require $mapFile;

if (!isset($routes[$route])) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$target = numart_path($routes[$route]);
if (!is_file($target)) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

chdir(NUMART_ROOT);
require $target;
