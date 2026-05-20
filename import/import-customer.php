<?php
session_start();
header('Content-Type: application/json');

require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Validasi file upload
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File tidak ditemukan atau terjadi kesalahan saat upload.']);
    exit;
}

// Konfigurasi database
$host = 'localhost'; 
$username = 'u700125577_user'; 
$password = '@u700125577_User'; 
$database = 'u700125577_numart'; 

$db = new mysqli($host, $username, $password, $database);
if ($db->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal: ' . $db->connect_error]);
    exit;
}

$fileTmpPath = $_FILES['excel_file']['tmp_name'];

try {
    $spreadsheet = IOFactory::load($fileTmpPath);
    $sheet = $spreadsheet->getActiveSheet();
    $data = $sheet->toArray();

    $query = "INSERT INTO customer (customer_nama, customer_kartu, customer_poin, customer_tlpn, customer_email, customer_alamat, customer_create, customer_status, customer_category, customer_cabang) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $db->error]);
        exit;
    }

    $successCount = 0;
    $failedCount = 0;

    foreach ($data as $index => $row) {
        if ($index === 0) continue; // Lewati baris header

        $customer_nama = $row[0] ?? null; 
        $customer_kartu = $row[1] ?? null; 
        $customer_tlpn = $row[2] ?? null; 
        $customer_email = $row[3] ?? null; 
        $customer_alamat = $row[4] ?? null; 
        $customerCategory = intval($row[5]) ?? null; 
        $customerCabang = intval($row[6]) ?? null; 
        $customerCreate = date('Y-m-d H:i:s'); // Gunakan format standar DATETIME

        // Validasi data penting
        if (empty($customer_nama) || empty($customerCategory) || empty($customerCabang)) {
            $failedCount++;
            continue; // Lewati data yang tidak valid
        }

        $stmt->bind_param(
            'ssisssssii', 
            $customer_nama, 
            $customer_kartu,
            0, // Poin default
            $customer_tlpn, 
            $customer_email, 
            $customer_alamat,
            $customerCreate,
            '1', // Status default
            $customerCategory,
            $customerCabang
        );

        if (!$stmt->execute()) {
            $failedCount++;
            continue; // Lewati data jika terjadi kesalahan
        }

        $successCount++;
    }

    $stmt->close();
    $db->close();

    echo json_encode(['success' => true, 'message' => "Data berhasil diimpor: $successCount baris. Gagal: $failedCount baris."]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
