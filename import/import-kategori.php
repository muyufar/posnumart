<?php
header('Content-Type: application/json');

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

require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File tidak ditemukan atau terjadi kesalahan saat upload.']);
    exit;
}

$fileTmpPath = $_FILES['excel_file']['tmp_name'];

try {
    $spreadsheet = IOFactory::load($fileTmpPath);
    $sheet = $spreadsheet->getActiveSheet();
    $data = $sheet->toArray();

    $query = "INSERT INTO kategori (kategori_nama, kategori_status, kategori_cabang) VALUES (?, ?, ?)";
    $stmt = $db->prepare($query);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $db->error]);
        exit;
    }

    $successCount = 0;
    foreach ($data as $index => $row) {
        if ($index === 0) continue;

        $kategori_nama = $row[0] ?? null;
        $kategori_status = $row[1] ?? null;
        $kategori_cabang = $row[2] ?? null;

        if (empty($kategori_nama) || empty($kategori_status) || !is_numeric($kategori_cabang)) {
            continue;
        }

        $stmt->bind_param('ssi', $kategori_nama, $kategori_status, $kategori_cabang);
        if ($stmt->execute()) {
            $successCount++;
        }
    }

    $stmt->close();
    $db->close();

    echo json_encode(['success' => true, 'message' => "Data berhasil diimpor: $successCount baris."]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
