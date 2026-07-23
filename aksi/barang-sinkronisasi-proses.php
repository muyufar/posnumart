<?php
// Pastikan tidak ada output sebelum ini
if (ob_get_level()) {
    ob_end_clean();
}

// Disable error display untuk mencegah output HTML sebelum JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('max_execution_time', 300); // 5 menit
ini_set('memory_limit', '256M');

// Start output buffering SEBELUM include apapun
ob_start();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Fungsi untuk mengirim response dan exit
function sendResponse($success, $message, $data = []) {
    global $conn;
    
    // Tutup koneksi jika ada
    if (isset($conn) && $conn) {
        @mysqli_close($conn);
    }
    
    // Clear output buffer
    $output = ob_get_clean();
    if ($output && trim($output) !== '') {
        error_log("Unexpected output: " . substr($output, 0, 500));
    }
    
    $response = array_merge([
        'success' => $success,
        'message' => $message
    ], $data);
    
    $json = @json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    
    if ($json === false) {
        // Ultimate fallback
        $json = json_encode([
            'success' => $success,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE);
    }
    
    echo $json;
    exit;
}

try {
    // Include koneksi dengan error handling
    if (!@include 'koneksi.php') {
        sendResponse(false, 'Gagal memuat file koneksi database');
    }
    
    // Cek apakah variabel $conn ada
    if (!isset($conn)) {
        sendResponse(false, 'Variabel koneksi database tidak ditemukan');
    }
    
    // Cek koneksi database
    if (!$conn || (is_object($conn) && property_exists($conn, 'connect_error') && $conn->connect_error)) {
        $error_msg = is_object($conn) && property_exists($conn, 'connect_error') ? $conn->connect_error : 'Koneksi null';
        sendResponse(false, 'Gagal menghubungkan ke database: ' . $error_msg);
    }
    
    // Set charset untuk mencegah encoding issues
    mysqli_set_charset($conn, 'latin1');
    
} catch (Exception $e) {
    sendResponse(false, 'Error koneksi: ' . $e->getMessage());
} catch (Error $e) {
    sendResponse(false, 'Fatal error koneksi: ' . $e->getMessage());
}

// Cek method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method tidak diizinkan');
}

// Ambil parameter
$source_cabang = isset($_POST['source_cabang']) ? (int)$_POST['source_cabang'] : 0;
$target_cabang = isset($_POST['target_cabang']) ? (int)$_POST['target_cabang'] : 0;
$offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
$limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 500; // Default 500 per batch

// Validasi
if ($source_cabang < 0 || $target_cabang < 0) {
    sendResponse(false, 'Cabang tidak valid');
}

if ($source_cabang == $target_cabang) {
    sendResponse(false, 'Cabang sumber dan tujuan tidak boleh sama');
}

if ($offset < 0) {
    $offset = 0;
}

if ($limit < 1 || $limit > 1000) {
    $limit = 500; // Batasi maksimal 1000 per batch
}

try {
    // Hitung total barang di cabang sumber
    $count_query = "SELECT COUNT(*) as total FROM barang WHERE barang_cabang = $source_cabang AND barang_status = '1'";
    $count_result = mysqli_query($conn, $count_query);
    if (!$count_result) {
        sendResponse(false, 'Gagal menghitung total barang: ' . mysqli_error($conn));
    }
    $count_data = mysqli_fetch_assoc($count_result);
    $total_source = (int)$count_data['total'];
    
    // Ambil barang dari cabang sumber dengan LIMIT dan OFFSET (batch processing)
    // Query sederhana dulu, filter di PHP untuk menghindari masalah dengan LEFT JOIN yang kompleks
    $query_source = "SELECT * FROM barang 
                     WHERE barang_cabang = $source_cabang 
                     AND barang_status = '1'
                     ORDER BY barang_id
                     LIMIT $limit OFFSET $offset";
    
    $result_source = mysqli_query($conn, $query_source);
    
    if (!$result_source) {
        sendResponse(false, 'Gagal mengambil data dari cabang sumber: ' . mysqli_error($conn));
    }

    $processed_count = mysqli_num_rows($result_source);
    $inserted = 0;
    $skipped = 0;
    $failed = 0;
    $logs = [];
    $error_details = [];

    // Loop setiap barang dari cabang sumber
    $counter = 0;
    while ($barang = mysqli_fetch_assoc($result_source)) {
        $counter++;
        
        // Cek memory usage setiap 100 item
        if ($counter % 100 == 0) {
            $memory_usage = memory_get_usage(true) / 1024 / 1024; // MB
            if ($memory_usage > 200) { // Jika lebih dari 200MB
                $logs[] = "Warning: Memory usage tinggi ($memory_usage MB)";
            }
        }
        
        $barang_kode = mysqli_real_escape_string($conn, $barang['barang_kode']);
        
        // Double check apakah barang sudah ada (untuk safety)
        $check_query = "SELECT COUNT(*) as count FROM barang WHERE barang_kode = '$barang_kode' AND barang_cabang = $target_cabang";
        $check_result = mysqli_query($conn, $check_query);
        
        if (!$check_result) {
            $failed++;
            $error_msg = mysqli_error($conn);
            $error_details[] = "Error check barang kode $barang_kode: $error_msg";
            if ($failed <= 10) {
                $logs[] = "Error: Gagal mengecek barang kode $barang_kode - " . substr($error_msg, 0, 100);
            }
            continue;
        }

        $check_data = mysqli_fetch_assoc($check_result);
        
        // Jika sudah ada, cek statusnya
        if ($check_data['count'] > 0) {
            // Cek status barang yang sudah ada
            $check_status_query = "SELECT barang_id, barang_status FROM barang WHERE barang_kode = '$barang_kode' AND barang_cabang = $target_cabang LIMIT 1";
            $check_status_result = mysqli_query($conn, $check_status_query);
            
            if ($check_status_result && $status_data = mysqli_fetch_assoc($check_status_result)) {
                // Jika status tidak aktif, update menjadi aktif (tanpa mengubah barang_id)
                if ($status_data['barang_status'] != '1') {
                    $update_status_query = "UPDATE barang SET barang_status = '1' WHERE barang_id = " . (int)$status_data['barang_id'];
                    if (@mysqli_query($conn, $update_status_query)) {
                        $inserted++; // Dianggap sebagai update yang berhasil
                        if ($inserted % 20 == 0 || $inserted <= 5) {
                            $logs[] = "Update: Barang kode $barang_kode diaktifkan di cabang $target_cabang";
                        }
                    } else {
                        $failed++;
                        $error_msg = mysqli_error($conn);
                        $error_details[] = "Error update status barang kode $barang_kode: $error_msg";
                        if ($failed <= 10) {
                            $logs[] = "Error: Gagal mengaktifkan barang kode $barang_kode - " . substr($error_msg, 0, 100);
                        }
                    }
                } else {
                    // Sudah ada dan sudah aktif, skip
                    $skipped++;
                    if ($skipped % 50 == 0 || $skipped <= 5) {
                        $logs[] = "Skip: Barang kode $barang_kode sudah ada dan aktif di cabang $target_cabang";
                    }
                }
            } else {
                // Error saat cek status, skip
                $skipped++;
                if ($skipped % 50 == 0 || $skipped <= 5) {
                    $logs[] = "Skip: Barang kode $barang_kode sudah ada di cabang $target_cabang (error cek status)";
                }
            }
            continue;
        }

        // Siapkan data untuk insert - pastikan semua field ada
        $barang_kode_slug = isset($barang['barang_kode_slug']) ? mysqli_real_escape_string($conn, $barang['barang_kode_slug']) : str_replace(" ", "-", $barang_kode);
        $barang_kode_count = isset($barang['barang_kode_count']) ? (int)$barang['barang_kode_count'] : 0;
        $barang_nama = isset($barang['barang_nama']) ? mysqli_real_escape_string($conn, $barang['barang_nama']) : '';
        $barang_harga_beli = isset($barang['barang_harga_beli']) ? mysqli_real_escape_string($conn, $barang['barang_harga_beli']) : '0';
        $barang_harga_beli_rata = mysqli_real_escape_string($conn, (string) ($barang['barang_harga_beli_rata'] ?? $barang['barang_harga_beli'] ?? '0'));
        $barang_harga = isset($barang['barang_harga']) ? mysqli_real_escape_string($conn, $barang['barang_harga']) : '0';
        $barang_harga_grosir_1 = isset($barang['barang_harga_grosir_1']) ? mysqli_real_escape_string($conn, $barang['barang_harga_grosir_1']) : '0';
        $barang_harga_grosir_2 = isset($barang['barang_harga_grosir_2']) ? mysqli_real_escape_string($conn, $barang['barang_harga_grosir_2']) : '0';
        $barang_harga_s2 = isset($barang['barang_harga_s2']) ? mysqli_real_escape_string($conn, $barang['barang_harga_s2']) : '0';
        $barang_harga_grosir_1_s2 = isset($barang['barang_harga_grosir_1_s2']) ? mysqli_real_escape_string($conn, $barang['barang_harga_grosir_1_s2']) : '0';
        $barang_harga_grosir_2_s2 = isset($barang['barang_harga_grosir_2_s2']) ? mysqli_real_escape_string($conn, $barang['barang_harga_grosir_2_s2']) : '0';
        $barang_harga_s3 = isset($barang['barang_harga_s3']) ? mysqli_real_escape_string($conn, $barang['barang_harga_s3']) : '0';
        $barang_harga_grosir_1_s3 = isset($barang['barang_harga_grosir_1_s3']) ? mysqli_real_escape_string($conn, $barang['barang_harga_grosir_1_s3']) : '0';
        $barang_harga_grosir_2_s3 = isset($barang['barang_harga_grosir_2_s3']) ? mysqli_real_escape_string($conn, $barang['barang_harga_grosir_2_s3']) : '0';
        $barang_stock = '0'; // Set stock ke 0 untuk barang baru
        $barang_tanggal = date("d F Y g:i:s a");
        $barang_kategori_id = isset($barang['barang_kategori_id']) ? mysqli_real_escape_string($conn, $barang['barang_kategori_id']) : '';
        $kategori_id = isset($barang['kategori_id']) ? mysqli_real_escape_string($conn, $barang['kategori_id']) : '';
        $barang_satuan_id = isset($barang['barang_satuan_id']) ? mysqli_real_escape_string($conn, $barang['barang_satuan_id']) : '';
        $satuan_id = isset($barang['satuan_id']) ? mysqli_real_escape_string($conn, $barang['satuan_id']) : '';
        $satuan_id_2 = isset($barang['satuan_id_2']) ? (int)$barang['satuan_id_2'] : 0;
        $satuan_id_3 = isset($barang['satuan_id_3']) ? (int)$barang['satuan_id_3'] : 0;
        $satuan_isi_1 = isset($barang['satuan_isi_1']) ? (int)$barang['satuan_isi_1'] : 1;
        $satuan_isi_2 = isset($barang['satuan_isi_2']) ? (int)$barang['satuan_isi_2'] : 0;
        $satuan_isi_3 = isset($barang['satuan_isi_3']) ? (int)$barang['satuan_isi_3'] : 0;
        $barang_deskripsi = isset($barang['barang_deskripsi']) ? mysqli_real_escape_string($conn, $barang['barang_deskripsi']) : '';
        $barang_option_sn = isset($barang['barang_option_sn']) ? (int)$barang['barang_option_sn'] : 0;
        $barang_terjual = 0; // Reset terjual ke 0
        $barang_konsi = isset($barang['barang_konsi']) ? (int)$barang['barang_konsi'] : 0;
        $barang_status = '1';
        $kode_suplier = isset($barang['kode_suplier']) ? mysqli_real_escape_string($conn, $barang['kode_suplier']) : '';
        $barang_gambar = mysqli_real_escape_string($conn, (string) ($barang['barang_gambar'] ?? ''));

        // Insert barang ke cabang tujuan
        // Urutan field sesuai dengan CREATE TABLE
        $insert_query = "INSERT INTO barang (
            barang_kode, 
            barang_kode_slug, 
            barang_kode_count, 
            barang_nama, 
            barang_harga_beli, 
            barang_harga, 
            barang_harga_grosir_1, 
            barang_harga_grosir_2, 
            barang_harga_s2, 
            barang_harga_grosir_1_s2, 
            barang_harga_grosir_2_s2, 
            barang_harga_s3, 
            barang_harga_grosir_1_s3, 
            barang_harga_grosir_2_s3, 
            barang_stock, 
            barang_tanggal, 
            barang_kategori_id, 
            kategori_id, 
            barang_satuan_id, 
            satuan_id, 
            satuan_id_2, 
            satuan_id_3, 
            satuan_isi_1, 
            satuan_isi_2, 
            satuan_isi_3, 
            barang_deskripsi, 
            barang_option_sn, 
            barang_terjual, 
            barang_cabang, 
            barang_konsi, 
            barang_status, 
            kode_suplier,
            barang_gambar,
            barang_harga_beli_rata
        ) VALUES (
            '$barang_kode',
            '$barang_kode_slug',
            $barang_kode_count,
            '$barang_nama',
            '$barang_harga_beli',
            '$barang_harga',
            '$barang_harga_grosir_1',
            '$barang_harga_grosir_2',
            '$barang_harga_s2',
            '$barang_harga_grosir_1_s2',
            '$barang_harga_grosir_2_s2',
            '$barang_harga_s3',
            '$barang_harga_grosir_1_s3',
            '$barang_harga_grosir_2_s3',
            '$barang_stock',
            '$barang_tanggal',
            '$barang_kategori_id',
            '$kategori_id',
            '$barang_satuan_id',
            '$satuan_id',
            $satuan_id_2,
            $satuan_id_3,
            $satuan_isi_1,
            $satuan_isi_2,
            $satuan_isi_3,
            '$barang_deskripsi',
            $barang_option_sn,
            $barang_terjual,
            $target_cabang,
            $barang_konsi,
            '$barang_status',
            '$kode_suplier',
            '$barang_gambar',
            '$barang_harga_beli_rata'
        )";

        $insert_result = @mysqli_query($conn, $insert_query);
        if ($insert_result) {
            $inserted++;
            // Hanya log setiap 20 item untuk mengurangi ukuran response
            if ($inserted % 20 == 0 || $inserted <= 5) {
                $logs[] = "Success: Barang kode $barang_kode berhasil disalin";
            }
        } else {
            $failed++;
            $error_msg = mysqli_error($conn);
            $error_details[] = "Error insert barang kode $barang_kode: $error_msg";
            if ($failed <= 10) {
                $logs[] = "Error: Gagal menyimpan barang kode $barang_kode - " . substr($error_msg, 0, 100);
            }
        }
    }

    // Limit logs untuk response
    $limited_logs = array_slice($logs, 0, 30);
    
    // Sanitize logs untuk JSON
    $sanitized_logs = [];
    foreach ($limited_logs as $log) {
        $clean_log = mb_convert_encoding($log, 'UTF-8', 'UTF-8');
        $clean_log = preg_replace('/[\x00-\x1F\x7F]/u', '', $clean_log);
        $clean_log = mb_substr($clean_log, 0, 200, 'UTF-8');
        $sanitized_logs[] = $clean_log;
    }

    // Hitung progress
    $next_offset = $offset + $processed_count;
    $has_more = $next_offset < $total_source;
    $progress_percent = $total_source > 0 ? round(($next_offset / $total_source) * 100, 2) : 0;
    
    // Response sukses
    sendResponse(true, $has_more ? 'Batch selesai, lanjutkan...' : 'Sinkronisasi selesai', [
        'total_source' => (int)$total_source,
        'processed' => (int)$processed_count,
        'inserted' => (int)$inserted,
        'skipped' => (int)$skipped,
        'failed' => (int)$failed,
        'offset' => (int)$offset,
        'next_offset' => (int)$next_offset,
        'has_more' => $has_more,
        'progress_percent' => $progress_percent,
        'logs' => $sanitized_logs,
        'error_count' => count($error_details)
    ]);

} catch (Exception $e) {
    sendResponse(false, 'Exception: ' . $e->getMessage());
} catch (Error $e) {
    sendResponse(false, 'Fatal Error: ' . $e->getMessage());
}
