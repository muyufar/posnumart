<?php
// Pastikan tidak ada output sebelum ini
if (ob_get_level()) {
    ob_end_clean();
}

// Disable error display untuk mencegah output HTML sebelum JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering
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
    ob_get_clean();
    
    $response = array_merge([
        'success' => $success,
        'message' => $message
    ], $data);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Include koneksi
    if (!@include 'koneksi.php') {
        sendResponse(false, 'Gagal memuat file koneksi database');
    }
    
    // Cek koneksi database
    if (!isset($conn) || !$conn) {
        sendResponse(false, 'Koneksi database gagal');
    }
    
    // Set charset
    mysqli_set_charset($conn, 'latin1');
    
} catch (Exception $e) {
    sendResponse(false, 'Error koneksi: ' . $e->getMessage());
}

// Cek method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method tidak diizinkan');
}

// Ambil parameter
$barang_kode = isset($_POST['barang_kode']) ? mysqli_real_escape_string($conn, $_POST['barang_kode']) : '';
$target_cabang_list = isset($_POST['target_cabang']) ? $_POST['target_cabang'] : [];

// Validasi
if (empty($barang_kode)) {
    sendResponse(false, 'Kode barang tidak boleh kosong');
}

if (empty($target_cabang_list) || !is_array($target_cabang_list)) {
    sendResponse(false, 'Pilih minimal 1 cabang tujuan');
}

try {
    $inserted_total = 0;
    $skipped_total = 0;
    $failed_total = 0;
    $logs = [];
    
    // Ambil data barang template dari salah satu cabang yang sudah ada
    $query_template = "SELECT * FROM barang 
                       WHERE barang_kode = '$barang_kode' 
                       AND barang_status = '1' 
                       LIMIT 1";
    
    $result_template = mysqli_query($conn, $query_template);
    
    if (!$result_template || mysqli_num_rows($result_template) == 0) {
        sendResponse(false, 'Barang dengan kode ' . $barang_kode . ' tidak ditemukan');
    }
    
    $barang_template = mysqli_fetch_assoc($result_template);
    
    // Loop setiap cabang tujuan
    foreach ($target_cabang_list as $target_cabang) {
        $target_cabang = (int)$target_cabang;
        
        // Cek apakah barang sudah ada di cabang tujuan
        $check_query = "SELECT COUNT(*) as count, barang_id, barang_status 
                        FROM barang 
                        WHERE barang_kode = '$barang_kode' 
                        AND barang_cabang = $target_cabang 
                        LIMIT 1";
        $check_result = mysqli_query($conn, $check_query);
        
        if (!$check_result) {
            $failed_total++;
            $logs[] = "Error: Gagal mengecek barang di cabang $target_cabang";
            continue;
        }
        
        $check_data = mysqli_fetch_assoc($check_result);
        
        // Jika sudah ada
        if ($check_data['count'] > 0) {
            // Jika status tidak aktif, aktifkan kembali
            if ($check_data['barang_status'] != '1') {
                $update_query = "UPDATE barang SET barang_status = '1' WHERE barang_id = " . (int)$check_data['barang_id'];
                if (mysqli_query($conn, $update_query)) {
                    $inserted_total++;
                    $logs[] = "Barang berhasil diaktifkan di cabang $target_cabang";
                } else {
                    $failed_total++;
                    $logs[] = "Error: Gagal mengaktifkan barang di cabang $target_cabang";
                }
            } else {
                $skipped_total++;
                $logs[] = "Barang sudah ada dan aktif di cabang $target_cabang";
            }
            continue;
        }
        
        // Siapkan data untuk insert - copy dari template
        $barang_kode_slug = mysqli_real_escape_string($conn, $barang_template['barang_kode_slug']);
        $barang_kode_count = (int)$barang_template['barang_kode_count'];
        $barang_nama = mysqli_real_escape_string($conn, $barang_template['barang_nama']);
        $barang_harga_beli = mysqli_real_escape_string($conn, $barang_template['barang_harga_beli']);
        $barang_harga_beli_rata = mysqli_real_escape_string($conn, (string) ($barang_template['barang_harga_beli_rata'] ?? $barang_template['barang_harga_beli'] ?? '0'));
        $barang_harga = mysqli_real_escape_string($conn, $barang_template['barang_harga']);
        $barang_harga_grosir_1 = mysqli_real_escape_string($conn, $barang_template['barang_harga_grosir_1']);
        $barang_harga_grosir_2 = mysqli_real_escape_string($conn, $barang_template['barang_harga_grosir_2']);
        $barang_harga_s2 = mysqli_real_escape_string($conn, $barang_template['barang_harga_s2']);
        $barang_harga_grosir_1_s2 = mysqli_real_escape_string($conn, $barang_template['barang_harga_grosir_1_s2']);
        $barang_harga_grosir_2_s2 = mysqli_real_escape_string($conn, $barang_template['barang_harga_grosir_2_s2']);
        $barang_harga_s3 = mysqli_real_escape_string($conn, $barang_template['barang_harga_s3']);
        $barang_harga_grosir_1_s3 = mysqli_real_escape_string($conn, $barang_template['barang_harga_grosir_1_s3']);
        $barang_harga_grosir_2_s3 = mysqli_real_escape_string($conn, $barang_template['barang_harga_grosir_2_s3']);
        $barang_stock = '0'; // Set stock ke 0 untuk barang baru
        $barang_tanggal = date("d F Y g:i:s a");
        $barang_kategori_id = mysqli_real_escape_string($conn, $barang_template['barang_kategori_id']);
        $kategori_id = mysqli_real_escape_string($conn, $barang_template['kategori_id']);
        $barang_satuan_id = mysqli_real_escape_string($conn, $barang_template['barang_satuan_id']);
        $satuan_id = mysqli_real_escape_string($conn, $barang_template['satuan_id']);
        $satuan_id_2 = (int)$barang_template['satuan_id_2'];
        $satuan_id_3 = (int)$barang_template['satuan_id_3'];
        $satuan_isi_1 = (int)$barang_template['satuan_isi_1'];
        $satuan_isi_2 = (int)$barang_template['satuan_isi_2'];
        $satuan_isi_3 = (int)$barang_template['satuan_isi_3'];
        $barang_deskripsi = mysqli_real_escape_string($conn, $barang_template['barang_deskripsi']);
        $barang_option_sn = (int)$barang_template['barang_option_sn'];
        $barang_terjual = 0; // Reset terjual ke 0
        $barang_konsi = (int)$barang_template['barang_konsi'];
        $barang_status = '1';
        $kode_suplier = mysqli_real_escape_string($conn, $barang_template['kode_suplier']);
        $barang_gambar = mysqli_real_escape_string($conn, (string) ($barang_template['barang_gambar'] ?? ''));
        
        // Insert barang ke cabang tujuan (barang_id akan auto increment)
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
        
        if (mysqli_query($conn, $insert_query)) {
            $inserted_total++;
            // Get nama cabang
            $nama_cabang = '';
            switch($target_cabang) {
                case 0: $nama_cabang = 'Gudang'; break;
                case 1: $nama_cabang = 'Dukun'; break;
                case 2: $nama_cabang = 'Pakis'; break;
                case 3: $nama_cabang = 'PP Srumbung'; break;
                case 5: $nama_cabang = 'Tegalrejo'; break;
                default: $nama_cabang = "Cabang $target_cabang";
            }
            $logs[] = "Berhasil menambahkan barang ke $nama_cabang";
        } else {
            $failed_total++;
            $error_msg = mysqli_error($conn);
            $logs[] = "Error: Gagal menambahkan ke cabang $target_cabang - " . substr($error_msg, 0, 100);
        }
    }
    
    // Response
    if ($inserted_total > 0) {
        sendResponse(true, "Berhasil menambahkan barang ke $inserted_total cabang", [
            'inserted' => $inserted_total,
            'skipped' => $skipped_total,
            'failed' => $failed_total,
            'logs' => $logs
        ]);
    } else if ($skipped_total > 0 && $failed_total == 0) {
        sendResponse(true, "Barang sudah ada di semua cabang yang dipilih", [
            'inserted' => $inserted_total,
            'skipped' => $skipped_total,
            'failed' => $failed_total,
            'logs' => $logs
        ]);
    } else {
        sendResponse(false, "Gagal menambahkan barang ke cabang", [
            'inserted' => $inserted_total,
            'skipped' => $skipped_total,
            'failed' => $failed_total,
            'logs' => $logs
        ]);
    }
    
} catch (Exception $e) {
    sendResponse(false, 'Exception: ' . $e->getMessage());
}
?>
