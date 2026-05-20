<?php
/**
 * API Proxy untuk wilayah.id
 * Menghindari masalah CORS saat memanggil API wilayah.id dari frontend
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$type = isset($_GET['type']) ? $_GET['type'] : '';
$code = isset($_GET['code']) ? $_GET['code'] : '';

$baseUrl = 'https://wilayah.id/api/';
$url = '';

switch ($type) {
    case 'provinces':
        $url = $baseUrl . 'provinces.json';
        break;
    case 'regencies':
        if (empty($code)) {
            echo json_encode(['error' => 'Province code required']);
            exit;
        }
        $url = $baseUrl . 'regencies/' . $code . '.json';
        break;
    case 'districts':
        if (empty($code)) {
            echo json_encode(['error' => 'Regency code required']);
            exit;
        }
        $url = $baseUrl . 'districts/' . $code . '.json';
        break;
    case 'villages':
        if (empty($code)) {
            echo json_encode(['error' => 'District code required']);
            exit;
        }
        $url = $baseUrl . 'villages/' . $code . '.json';
        break;
    default:
        echo json_encode(['error' => 'Invalid type. Use: provinces, regencies, districts, or villages']);
        exit;
}

// Fetch data from wilayah.id
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'User-Agent: Numart-POS/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo json_encode(['error' => 'Failed to fetch data: ' . $error]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(['error' => 'API returned status ' . $httpCode]);
    exit;
}

// Return the response
echo $response;


