<?php
$root = dirname(__DIR__);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/modules'));
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') {
        continue;
    }
    $path = $f->getPathname();
    $c = file_get_contents($path);
    $n = preg_replace("/numart_path\\('([^']+)';/", "numart_path('$1');", $c);
    if ($n !== $c) {
        file_put_contents($path, $n);
        echo "$path\n";
    }
}
echo "Done.\n";
