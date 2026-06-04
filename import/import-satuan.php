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

    $query = "INSERT INTO satuan (satuan_id, satuan_nama, satuan_status, satuan_cabang) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($query);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $db->error]);
        exit;
    }

    $nextId = (int) $db->query('SELECT IFNULL(MAX(satuan_id), 0) + 1 AS next_id FROM satuan')->fetch_assoc()['next_id'];
    $successCount = 0;
    foreach ($data as $index => $row) {
        if ($index === 0) continue;

        $satuan_nama = $row[1] ?? null;
        $satuan_status = $row[2] ?? null;
        $satuan_cabang = 0;

        if (empty($satuan_nama) || empty($satuan_status)) {
            continue;
        }

        $stmt->bind_param('issi', $nextId, $satuan_nama, $satuan_status, $satuan_cabang);
        if ($stmt->execute()) {
            $successCount++;
            $nextId++;
        }
    }

    $stmt->close();
    $db->close();

    echo json_encode(['success' => true, 'message' => "Data berhasil diimpor: $successCount baris."]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
