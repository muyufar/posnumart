<?php
$_GET['route'] = 'barang-data';
$_GET['cabang'] = '0';
$_GET['draw'] = '1';
$_GET['start'] = '0';
$_GET['length'] = '10';
$_GET['columns'] = array(array('data' => 0, 'searchable' => 'true', 'orderable' => 'true', 'search' => array('value' => '')));
$_GET['order'] = array(array('column' => 0, 'dir' => 'asc'));
$_GET['search'] = array('value' => '');
$_SERVER['DOCUMENT_ROOT'] = 'C:/laragon/www';
$_SERVER['SCRIPT_NAME'] = '/numart/bootstrap/front.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

// Fake session for halau if needed - barang-data might not use halau
chdir(__DIR__);
ob_start();
$t0 = microtime(true);
try {
    include dirname(__DIR__) . '/bootstrap/front.php';
    $out = ob_get_clean();
    $ms = round((microtime(true) - $t0) * 1000);
    echo "Time: {$ms}ms\n";
    echo "Length: " . strlen($out) . "\n";
    echo substr($out, 0, 800) . "\n";
    $j = json_decode($out, true);
    if ($j === null) {
        echo "JSON INVALID: " . json_last_error_msg() . "\n";
    } else {
        echo "JSON OK recordsTotal=" . ($j['recordsTotal'] ?? '?') . "\n";
    }
} catch (Throwable $e) {
    ob_end_clean();
    echo "EXCEPTION: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
}
