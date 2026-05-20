<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug input
file_put_contents('import_log.txt', print_r($_FILES, true), FILE_APPEND);
file_put_contents('import_log.txt', print_r($_POST, true), FILE_APPEND);

// Parameter koneksi database
$host = 'localhost'; // Ganti dengan host Anda
$username = 'u700125577_user';  // Ganti dengan username Anda
$password = '@u700125577_User'; // Ganti dengan password Anda
$database = 'u700125577_numart'; // Ganti dengan nama database Anda

// Membuat koneksi ke database
$db = new mysqli($host, $username, $password, $database);

// Periksa apakah koneksi berhasil
if ($db->connect_error) {
    die("Koneksi database gagal: " . $db->connect_error);
}

// Lokasi file log
$logFile = 'import_log.txt';

// Fungsi untuk menulis log
function writeLog($message) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND);
}

// Mengambil data dari file Excel
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$fileTmpPath = $_FILES['excel_file']['tmp_name'];

$response = array('success' => false, 'message' => ''); // Default response

if ($fileTmpPath) {
    try {
        $spreadsheet = IOFactory::load($fileTmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        // Query untuk insert data ke tabel barang
        $query = "INSERT INTO barang (
            barang_kode, barang_kode_slug, barang_kode_count, barang_nama, barang_harga_beli, barang_harga, 
            barang_harga_grosir_1, barang_harga_grosir_2, barang_harga_s2, barang_harga_grosir_1_s2, 
            barang_harga_grosir_2_s2, barang_harga_s3, barang_harga_grosir_1_s3, barang_harga_grosir_2_s3, 
            barang_stock, barang_tanggal, barang_kategori_id, kategori_id, barang_satuan_id, satuan_id, 
            satuan_id_2, satuan_id_3, satuan_isi_1, satuan_isi_2, satuan_isi_3, barang_deskripsi, 
            barang_option_sn, barang_terjual, barang_cabang, barang_konsi
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($query);

        if (!$stmt) {
            writeLog("Query preparation failed: " . $db->error);
            $response['message'] = 'Query preparation failed: ' . $db->error;
            echo json_encode($response);
            exit();
        }

        // Proses data dari Excel
        foreach ($data as $index => $row) {
            if ($index === 0) continue; // Lewati header

            // Ambil data sesuai urutan kolom di tabel
            $barang_kode = $row[0] ?? null;
            $barang_kode_slug = $row[1] ?? null;
            $barang_kode_count = $row[2] ?? null;
            $barang_nama = $row[3] ?? null;
            $barang_harga_beli = $row[4] ?? null;
            $barang_harga = $row[5] ?? null;
            $barang_harga_grosir_1 = $row[6] ?? null;
            $barang_harga_grosir_2 = $row[7] ?? null;
            $barang_harga_s2 = $row[8] ?? null;
            $barang_harga_grosir_1_s2 = $row[9] ?? null;
            $barang_harga_grosir_2_s2 = $row[10] ?? null;
            $barang_harga_s3 = $row[11] ?? null;
            $barang_harga_grosir_1_s3 = $row[12] ?? null;
            $barang_harga_grosir_2_s3 = $row[13] ?? null;
            $barang_stock = $row[14] ?? null;
            $barang_tanggal = $row[15] ?? null;
            $barang_kategori_id = $row[16] ?? null;
            $kategori_id = $row[17] ?? null;
            $barang_satuan_id = $row[18] ?? null;
            $satuan_id = $row[19] ?? null;
            $satuan_id_2 = $row[20] ?? null;
            $satuan_id_3 = $row[21] ?? null;
            $satuan_isi_1 = $row[22] ?? null;
            $satuan_isi_2 = $row[23] ?? null;
            $satuan_isi_3 = $row[24] ?? null;
            $barang_deskripsi = $row[25] ?? null;
            $barang_option_sn = $row[26] ?? null;
            $barang_terjual = $row[27] ?? null;
            $barang_cabang = $row[28] ?? null;
            $barang_konsi = $row[29] ?? null;

            // Validasi data (pastikan kolom penting tidak kosong)
            if (!$barang_kode) {
                writeLog("Baris $index dilewati: barang_kode kosong.");
                continue;
            }

            // Periksa apakah barang_kode sudah ada di database
            $checkQuery = "SELECT COUNT(*) FROM barang WHERE barang_kode = ? AND barang_cabang = $barang_cabang";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bind_param('s', $barang_kode);
            $checkStmt->execute();
            $checkStmt->bind_result($existing_cabang);
            $checkStmt->fetch();
            $checkStmt->close();

            if ($existing_cabang > 0) {
                writeLog("Baris $index dilewati: barang_kode '$barang_kode' sudah ada di database.");
                continue;
            }

            // Bind parameter dan eksekusi query
            $stmt->bind_param(
                'ssisssiiiiiiiississsiiiiisiiii',
                $barang_kode, $barang_kode_slug, $barang_kode_count, $barang_nama, $barang_harga_beli, $barang_harga,
                $barang_harga_grosir_1, $barang_harga_grosir_2, $barang_harga_s2, $barang_harga_grosir_1_s2,
                $barang_harga_grosir_2_s2, $barang_harga_s3, $barang_harga_grosir_1_s3, $barang_harga_grosir_2_s3,
                $barang_stock, $barang_tanggal, $barang_kategori_id, $kategori_id, $barang_satuan_id, $satuan_id,
                $satuan_id_2, $satuan_id_3, $satuan_isi_1, $satuan_isi_2, $satuan_isi_3, $barang_deskripsi,
                $barang_option_sn, $barang_terjual, $barang_cabang, $barang_konsi
            );

            if ($stmt->execute()) {
                writeLog("Berhasil mengimpor barang kode: $barang_kode");
            } else {
                writeLog("Gagal mengimpor barang kode: $barang_kode");
            }
        }

        $stmt->close();
        $db->close();

        $response['success'] = true;
        $response['message'] = 'Data berhasil diimpor!';
    } catch (Exception $e) {
        writeLog('Error: ' . $e->getMessage());
        $response['message'] = 'Terdapat kode barang yang sama, harap cek kembali';
    }
} else {
    writeLog("File Excel tidak ditemukan.");
    $response['message'] = 'File Excel tidak ditemukan.';
}

echo json_encode($response);
?>
