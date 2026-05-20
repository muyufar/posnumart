<?php
header('Content-Type: application/json');
include '../aksi/koneksi.php';

include '../aksi/functions.php';

// Cek session
session_start();
if (!isset($_SESSION['user_level'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];

// Handle routing
if ($method === 'GET') {
    // GET /api/laba atau GET /api/laba/{id}
    handleGet();
} elseif ($method === 'POST') {
    // POST /api/laba
    handlePost();
} elseif ($method === 'PUT') {
    // PUT /api/laba
    handlePut();
} elseif ($method === 'DELETE') {
    // DELETE /api/laba/{id}
    handleDelete();
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

/**
 * Handle file upload for transaction attachments
 * @param array $file $_FILES array element
 * @return string|false Relative path to uploaded file, or false on failure
 */
function handleFileUpload($file) {
    // Allowed file types
    $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("File upload error: " . $file['error']);
        return false;
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        error_log("File size exceeds maximum allowed size (5MB)");
        return false;
    }
    
    // Get file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file type
    if (!in_array($file_extension, $allowed_types)) {
        error_log("File type not allowed: " . $file_extension);
        return false;
    }
    
    // Create unique filename
    $unique_name = uniqid('lampiran_', true) . '_' . time() . '.' . $file_extension;
    
    // Destination directory (relative to api folder, so ../image/)
    $upload_dir = '../image/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Failed to create upload directory: " . $upload_dir);
            return false;
        }
    }
    
    // Full path to destination file
    $destination = $upload_dir . $unique_name;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Return relative path (from root, so 'image/filename.ext')
        return 'image/' . $unique_name;
    } else {
        error_log("Failed to move uploaded file to: " . $destination);
        return false;
    }
}

function handleGet() {
    global $conn;
    
    // Get query parameters
    $date_start = $_GET['date_start'] ?? null;
    $date_end = $_GET['date_end'] ?? null;
    $tipe = $_GET['tipe'] ?? null;
    $kategori = $_GET['kategori'] ?? null;
    $cabang = $_GET['cabang'] ?? null;
    $keterangan = $_GET['keterangan'] ?? null;
    $name = $_GET['name'] ?? null; // PJ/Penanggung Jawab
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    // Allow custom per_page from request, default to 10, max 1000
    $per_page = isset($_GET['per_page']) ? min((int)$_GET['per_page'], 1000) : 10;
    $offset = ($page - 1) * $per_page;
    
    // Sorting parameters
    $sort_by = $_GET['sort_by'] ?? 'created_at'; // Default: created_at
    $sort_order = strtoupper($_GET['sort_order'] ?? 'DESC'); // Default: DESC
    
    // Validate sort_by to prevent SQL injection (whitelist allowed columns)
    $allowed_sort_columns = ['created_at', 'date', 'jumlah', 'keterangan', 'name'];
    if (!in_array($sort_by, $allowed_sort_columns)) {
        $sort_by = 'created_at';
    }
    
    // Validate sort_order
    if ($sort_order !== 'ASC' && $sort_order !== 'DESC') {
        $sort_order = 'DESC';
    }
    
    // Build WHERE clause
    $where = [];
    if ($date_start && $date_end) {
        $where[] = "l.date BETWEEN '$date_start 00:00:00' AND '$date_end 23:59:59'";
    }
    if ($tipe !== null && $tipe !== '') {
        $where[] = "l.tipe = " . (int)$tipe;
    }
    if ($kategori) {
        $where[] = "l.kategori = '$kategori'";
    }
    if ($cabang !== null && $cabang !== '') {
        $where[] = "l.cabang = " . (int)$cabang;
    }
    if ($keterangan) {
        $keterangan_escaped = mysqli_real_escape_string($conn, $keterangan);
        $where[] = "l.keterangan LIKE '%$keterangan_escaped%'";
    }
    if ($name) {
        $name_escaped = mysqli_real_escape_string($conn, $name);
        $where[] = "l.name LIKE '%$name_escaped%'";
    }
    
    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Check if new columns exist
    $check_columns = "SHOW COLUMNS FROM laba LIKE 'jenis_transaksi'";
    $column_result = mysqli_query($conn, $check_columns);
    $has_new_columns = ($column_result && mysqli_num_rows($column_result) > 0);
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM laba l $where_clause";
    $count_result = mysqli_query($conn, $count_query);
    $total_records = mysqli_fetch_assoc($count_result)['total'];
    $total_pages = ceil($total_records / $per_page);
    
    // Build SELECT query with new columns if available
    $select_fields = "l.id, l.tipe, l.kategori, l.jumlah, l.keterangan, l.cabang, l.date, l.name, l.created_at";
    if ($has_new_columns) {
        $select_fields .= ", l.jenis_transaksi, l.akun_debit, l.akun_kredit, l.nominal, l.bunga, l.pajak, l.total, l.tag, l.file_lampiran";
    }
    
    // Get data with pagination
    $query = "SELECT 
        $select_fields,
        lk.id as kategori_id,
        lk.name as kategori_name,
        " . ($has_new_columns ? "lk_debit.id as akun_debit_id,
        lk_debit.name as akun_debit_name,
        lk_kredit.id as akun_kredit_id,
        lk_kredit.name as akun_kredit_name," : "") . "
        t.toko_id,
        t.toko_nama as toko_name,
        t.toko_cabang
    FROM laba l
    LEFT JOIN laba_kategori lk ON l.kategori = lk.id
    " . ($has_new_columns ? "LEFT JOIN laba_kategori lk_debit ON l.akun_debit = lk_debit.id
    LEFT JOIN laba_kategori lk_kredit ON l.akun_kredit = lk_kredit.id" : "") . "
    LEFT JOIN toko t ON l.cabang = t.toko_cabang
    $where_clause
    ORDER BY l.$sort_by $sort_order
    LIMIT $per_page OFFSET $offset";
    
    $result = mysqli_query($conn, $query);
    $data = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $item = [
            'id' => $row['id'],
            'tipe' => (int)$row['tipe'],
            'keterangan' => $row['keterangan'],
            'kategori' => [
                'id' => $row['kategori_id'],
                'name' => $row['kategori_name']
            ],
            'cabang' => [
                'id' => $row['toko_id'],
                'name' => $row['toko_name'],
                'cabang' => $row['toko_cabang']
            ],
            'jumlah' => $row['jumlah'],
            'date' => $row['date'] ? date('d/m/Y, H:i', strtotime($row['date'])) : null,
            'created_at' => $row['created_at'] ? date('d/m/Y, H:i', strtotime($row['created_at'])) : null,
            'name' => $row['name']
        ];
        
        // Add new fields if columns exist
        if ($has_new_columns) {
            $item['jenis_transaksi'] = $row['jenis_transaksi'] ?? null;
            $item['akun_debit'] = $row['akun_debit'] ?? null;
            $item['akun_kredit'] = $row['akun_kredit'] ?? null;
            $item['nominal'] = $row['nominal'] ?? null;
            $item['bunga'] = $row['bunga'] ?? null;
            $item['pajak'] = $row['pajak'] ?? null;
            $item['total'] = $row['total'] ?? $row['jumlah'];
            $item['tag'] = $row['tag'] ?? null;
            $item['file_lampiran'] = $row['file_lampiran'] ?? null;
            
            // Add akun debit and kredit details if available
            if ($row['akun_debit_id']) {
                $item['akun_debit_detail'] = [
                    'id' => $row['akun_debit_id'],
                    'name' => $row['akun_debit_name']
                ];
            }
            if ($row['akun_kredit_id']) {
                $item['akun_kredit_detail'] = [
                    'id' => $row['akun_kredit_id'],
                    'name' => $row['akun_kredit_name']
                ];
            }
        }
        
        $data[] = $item;
    }
    
    // Build pagination links
    $links = [];
    $base_path = $_SERVER['PHP_SELF'];
    
    // Build query string with existing filters
    $query_params = $_GET;
    unset($query_params['page']); // Remove page from query, but keep sort_by and sort_order
    
    $query_string = '';
    if (!empty($query_params)) {
        $query_string = '&' . http_build_query($query_params);
    }
    
    // Previous link
    $prev_page = $page > 1 ? $page - 1 : null;
    $links[] = [
        'url' => $prev_page ? $base_path . '?page=' . $prev_page . $query_string : null,
        'label' => '&laquo; Previous',
        'active' => false
    ];
    
    // Page links (show max 5 pages around current)
    $start_page = max(1, $page - 2);
    $end_page = min($total_pages, $page + 2);
    
    if ($start_page > 1) {
        $links[] = [
            'url' => $base_path . '?page=1' . $query_string,
            'label' => '1',
            'active' => false
        ];
        if ($start_page > 2) {
            $links[] = ['url' => null, 'label' => '...', 'active' => false];
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        $links[] = [
            'url' => $base_path . '?page=' . $i . $query_string,
            'label' => (string)$i,
            'active' => $i == $page
        ];
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $links[] = ['url' => null, 'label' => '...', 'active' => false];
        }
        $links[] = [
            'url' => $base_path . '?page=' . $total_pages . $query_string,
            'label' => (string)$total_pages,
            'active' => false
        ];
    }
    
    // Next link
    $next_page = $page < $total_pages ? $page + 1 : null;
    $links[] = [
        'url' => $next_page ? $base_path . '?page=' . $next_page . $query_string : null,
        'label' => 'Next &raquo;',
        'active' => false
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Data Ditemukan',
        'data' => [
            'data' => $data,
            'current_page' => $page,
            'per_page' => $per_page,
            'total' => $total_records,
            'last_page' => $total_pages,
            'links' => $links
        ]
    ]);
}

function handlePost() {
    global $conn;
    
    // Check for special actions first
    $action = isset($_POST['action']) ? $_POST['action'] : (isset($_REQUEST['action']) ? $_REQUEST['action'] : '');
    
    // Handle update_akun action (update akun_debit & akun_kredit only)
    if ($action === 'update_akun' || $action === 'update_transaksi') {
        handleUpdateAkun();
        return;
    }
    
    // Handle update with file using POST (more reliable than PUT with FormData)
    if ($action === 'update' || $action === 'update_with_file') {
        handlePut(); // Reuse PUT handler but called from POST
        return;
    }
    
    // Check if file is uploaded (multipart/form-data)
    $has_file = isset($_FILES['file_lampiran']) && $_FILES['file_lampiran']['error'] === UPLOAD_ERR_OK;
    
    // Try to get JSON data first
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);
    
    // If JSON is empty or null, try to get from POST (for FormData)
    if ($data === null || empty($data)) {
        $data = $_POST;
    }
    
    // Handle file upload if exists
    $file_lampiran_path = null;
    if ($has_file) {
        $file_lampiran_path = handleFileUpload($_FILES['file_lampiran']);
        if ($file_lampiran_path === false) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Gagal mengupload file lampiran'
            ]);
            exit;
        }
    }
    
    // Validate required fields - check if key exists and value is not empty string
    $errors = [];
    
    // Check date
    if (!isset($data['date']) || $data['date'] === '' || $data['date'] === null) {
        $errors[] = 'Tanggal harus diisi';
    }
    
    // Check jenis_transaksi (new) or tipe (old for backward compatibility)
    if (!isset($data['jenis_transaksi']) && (!isset($data['tipe']) || $data['tipe'] === '' || $data['tipe'] === null)) {
        $errors[] = 'Jenis Transaksi harus diisi';
    }
    
    // Check akun_debit (new) or kategori (old for backward compatibility)
    if (!isset($data['akun_debit']) && (!isset($data['kategori']) || $data['kategori'] === '' || $data['kategori'] === null)) {
        $errors[] = 'Akun Debit harus diisi';
    }
    
    // Check akun_kredit (new - required for double-entry)
    if (isset($data['akun_debit']) && (!isset($data['akun_kredit']) || $data['akun_kredit'] === '' || $data['akun_kredit'] === null)) {
        $errors[] = 'Akun Kredit harus diisi';
    }
    
    // Check cabang
    if (!isset($data['cabang']) || $data['cabang'] === '' || $data['cabang'] === null) {
        $errors[] = 'Cabang harus diisi';
    }
    
    // Validate that akun belongs to the same cabang (only if cabang column exists)
    if (isset($data['akun_debit']) && isset($data['cabang'])) {
        // Check if cabang column exists in laba_kategori table
        $check_column = "SHOW COLUMNS FROM laba_kategori LIKE 'cabang'";
        $column_result = mysqli_query($conn, $check_column);
        $cabang_column_exists = ($column_result && mysqli_num_rows($column_result) > 0);
        
        if ($cabang_column_exists) {
            $akun_debit_id = mysqli_real_escape_string($conn, $data['akun_debit']);
            $cabang_id = (int)$data['cabang'];
            
            // Check akun debit
            $check_debit = "SELECT id, cabang FROM laba_kategori WHERE id = '$akun_debit_id'";
            $result_debit = mysqli_query($conn, $check_debit);
            if ($result_debit && $row_debit = mysqli_fetch_assoc($result_debit)) {
                // Akun harus milik cabang yang sama, global (cabang IS NULL), atau default (cabang = 0)
                // Cabang 0 (PCNU) dianggap sebagai cabang default yang bisa digunakan semua cabang
                $akun_cabang = isset($row_debit['cabang']) ? (int)$row_debit['cabang'] : null;
                if ($akun_cabang !== null && $akun_cabang !== 0 && $akun_cabang !== $cabang_id) {
                    $errors[] = 'Akun Debit tidak tersedia untuk cabang yang dipilih';
                }
            } else {
                $errors[] = 'Akun Debit tidak ditemukan';
            }
            
            // Check akun kredit
            if (isset($data['akun_kredit']) && $data['akun_kredit'] !== '') {
                $akun_kredit_id = mysqli_real_escape_string($conn, $data['akun_kredit']);
                $check_kredit = "SELECT id, cabang FROM laba_kategori WHERE id = '$akun_kredit_id'";
                $result_kredit = mysqli_query($conn, $check_kredit);
                if ($result_kredit && $row_kredit = mysqli_fetch_assoc($result_kredit)) {
                    // Akun harus milik cabang yang sama, global (cabang IS NULL), atau default (cabang = 0)
                    // Cabang 0 (PCNU) dianggap sebagai cabang default yang bisa digunakan semua cabang
                    $akun_cabang = isset($row_kredit['cabang']) ? (int)$row_kredit['cabang'] : null;
                    if ($akun_cabang !== null && $akun_cabang !== 0 && $akun_cabang !== $cabang_id) {
                        $errors[] = 'Akun Kredit tidak tersedia untuk cabang yang dipilih';
                    }
                } else {
                    $errors[] = 'Akun Kredit tidak ditemukan';
                }
            }
        }
    }
    
    // Check jumlah or nominal
    if (!isset($data['jumlah']) && !isset($data['nominal'])) {
        $errors[] = 'Nominal/Jumlah harus diisi';
    }
    
    // Check keterangan
    if (!isset($data['keterangan']) || $data['keterangan'] === '' || $data['keterangan'] === null) {
        $errors[] = 'Keterangan harus diisi';
    }
    
    // Check name (penanggung jawab)
    if (!isset($data['name']) || $data['name'] === '' || $data['name'] === null) {
        $errors[] = 'Penanggung Jawab harus diisi';
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        // Include received data in error for debugging
        echo json_encode([
            'success' => false, 
            'message' => 'Data tidak valid', 
            'errors' => $errors,
            'received_data' => $data // For debugging - remove in production
        ]);
        exit;
    }
    
    // Generate UUID for ID
    $id = uniqid('laba_', true);
    
    // Handle new double-entry fields or backward compatibility
    $jenis_transaksi = isset($data['jenis_transaksi']) ? mysqli_real_escape_string($conn, $data['jenis_transaksi']) : null;
    $akun_debit = isset($data['akun_debit']) ? mysqli_real_escape_string($conn, $data['akun_debit']) : (isset($data['kategori']) ? mysqli_real_escape_string($conn, $data['kategori']) : null);
    $akun_kredit = isset($data['akun_kredit']) ? mysqli_real_escape_string($conn, $data['akun_kredit']) : null;
    
    // For backward compatibility: determine tipe from jenis_transaksi or use old tipe
    $tipe = null;
    if (isset($data['tipe'])) {
        $tipe = (int)$data['tipe'];
    } else if ($jenis_transaksi) {
        // Determine tipe from jenis_transaksi (0 = pendapatan/pemasukan, 1 = pengeluaran)
        // Pemasukan: pemasukan, pemasukan_piutang, tanam_modal
        // Pengeluaran: pengeluaran, hutang, tarik_modal, transfer_hutang
        // Netral: piutang, transfer_uang (bisa masuk atau keluar)
        $pemasukan_types = ['pemasukan', 'pemasukan_piutang', 'tanam_modal'];
        $pengeluaran_types = ['pengeluaran', 'hutang', 'tarik_modal', 'transfer_hutang'];
        
        if (in_array($jenis_transaksi, $pemasukan_types)) {
            $tipe = 0; // Pendapatan/Pemasukan
        } else if (in_array($jenis_transaksi, $pengeluaran_types)) {
            $tipe = 1; // Pengeluaran
        } else {
            // Default untuk piutang dan transfer_uang, bisa disesuaikan
            $tipe = 0; // Default ke pemasukan
        }
    }
    
    // Use kategori as akun_debit for backward compatibility
    $kategori = $akun_debit;
    
    $cabang = (int)$data['cabang'];
    $nominal = isset($data['nominal']) ? floatval($data['nominal']) : (isset($data['jumlah']) ? floatval($data['jumlah']) : 0);
    $bunga = isset($data['bunga']) ? floatval($data['bunga']) : 0;
    $pajak = isset($data['pajak']) ? floatval($data['pajak']) : 0;
    $total = isset($data['total']) ? floatval($data['total']) : $nominal;
    $jumlah = $total; // For backward compatibility, use total as jumlah
    $keterangan = mysqli_real_escape_string($conn, $data['keterangan']);
    $date = mysqli_real_escape_string($conn, $data['date']);
    $name = mysqli_real_escape_string($conn, $data['name']);
    $tag = isset($data['tag']) ? mysqli_real_escape_string($conn, $data['tag']) : null;
    
    // Use uploaded file path if available
    if ($file_lampiran_path !== null) {
        $file_lampiran = mysqli_real_escape_string($conn, $file_lampiran_path);
    } else {
        $file_lampiran = null;
    }
    
    // Format date if needed
    if (strlen($date) == 10) {
        $date = $date . ' ' . date('H:i:s');
    }
    
    // Build keterangan dengan informasi tambahan untuk double-entry
    $keterangan_full = $keterangan;
    if ($jenis_transaksi) {
        $keterangan_full = "[$jenis_transaksi] " . $keterangan;
    }
    if ($akun_kredit) {
        $keterangan_full .= " | Kredit: $akun_kredit";
    }
    if ($bunga > 0 || $pajak > 0) {
        $keterangan_full .= " | Bunga: $bunga%, Pajak: $pajak%";
    }
    if ($tag) {
        $keterangan_full .= " | Tag: $tag";
    }
    $keterangan_full = mysqli_real_escape_string($conn, $keterangan_full);
    
    // Check if new columns exist in database
    $check_columns = "SHOW COLUMNS FROM laba LIKE 'jenis_transaksi'";
    $column_result = mysqli_query($conn, $check_columns);
    $has_new_columns = ($column_result && mysqli_num_rows($column_result) > 0);
    
    // Build query - use new columns if available, otherwise use old structure
    if ($has_new_columns) {
        // Use new double-entry columns
        $query = "INSERT INTO laba (id, tipe, jenis_transaksi, kategori, akun_debit, akun_kredit, nominal, bunga, pajak, total, jumlah, keterangan, tag, file_lampiran, cabang, date, name, created_at) 
                  VALUES ('$id', " . ($tipe !== null ? $tipe : "0") . ", " .
                  ($jenis_transaksi ? "'$jenis_transaksi'" : "NULL") . ", " .
                  ($kategori ? "'$kategori'" : "NULL") . ", " .
                  ($akun_debit ? "'$akun_debit'" : "NULL") . ", " .
                  ($akun_kredit ? "'$akun_kredit'" : "NULL") . ", " .
                  ($nominal ? "'$nominal'" : "0") . ", " .
                  ($bunga ? "'$bunga'" : "0") . ", " .
                  ($pajak ? "'$pajak'" : "0") . ", " .
                  ($total ? "'$total'" : "'$jumlah'") . ", " .
                  "'$jumlah', " .
                  "'$keterangan', " .
                  ($tag ? "'$tag'" : "NULL") . ", " .
                  ($file_lampiran ? "'$file_lampiran'" : "NULL") . ", " .
                  "$cabang, '$date', '$name', NOW())";
    } else {
        // Backward compatibility: use old structure and store additional info in keterangan
        $query = "INSERT INTO laba (id, tipe, kategori, jumlah, keterangan, cabang, date, name, created_at) 
                  VALUES ('$id', " . ($tipe !== null ? $tipe : "0") . ", '$kategori', '$jumlah', '$keterangan_full', $cabang, '$date', '$name', NOW())";
    }
    
    if (mysqli_query($conn, $query)) {
        // Update saldo di laba_kategori untuk double-entry system
        if ($has_new_columns && $akun_debit && $akun_kredit && $total > 0) {
            // Cek apakah ini transfer dari 1-1100 ke 1-1152 untuk cabang selain 0
            $akun_debit_info = getAkunInfoForTransfer($conn, $akun_debit);
            $akun_kredit_info = getAkunInfoForTransfer($conn, $akun_kredit);
            
            $is_transfer_to_bank = false;
            if ($akun_debit_info && $akun_kredit_info) {
                $kode_debit = $akun_debit_info['kode_akun'] ?? '';
                $kode_kredit = $akun_kredit_info['kode_akun'] ?? '';
                
                // Cek jika transfer dari 1-1100 ke 1-1152 untuk cabang selain 0
                if (($kode_debit == '1-1100' && $kode_kredit == '1-1152' && $cabang != 0) ||
                    ($kode_kredit == '1-1100' && $kode_debit == '1-1152' && $cabang != 0) ||
                    ($jenis_transaksi == 'transfer_uang' && 
                     (($kode_debit == '1-1100' && $kode_kredit == '1-1152') || 
                      ($kode_kredit == '1-1100' && $kode_debit == '1-1152')) && $cabang != 0)) {
                    $is_transfer_to_bank = true;
                }
            }
            
            if ($is_transfer_to_bank) {
                // Transfer dari 1-1100 ke 1-1152 untuk cabang selain 0
                // 1. Update saldo normal (debit dan kredit)
                updateSaldoAkun($conn, $akun_debit, $akun_kredit, $total, $cabang, 'debit');
                updateSaldoAkun($conn, $akun_kredit, $akun_debit, $total, $cabang, 'kredit');
                
                // 2. Tambahkan juga ke 1-1153 (cabang 0)
                // Cari ID akun 1-1153 untuk cabang 0
                $query_1153 = "SELECT id FROM laba_kategori WHERE kode_akun = '1-1153' AND (cabang = 0 OR cabang IS NULL) LIMIT 1";
                $result_1153 = mysqli_query($conn, $query_1153);
                if ($result_1153 && mysqli_num_rows($result_1153) > 0) {
                    $row_1153 = mysqli_fetch_assoc($result_1153);
                    $akun_1153_id = intval($row_1153['id']);
                    // Tambah ke 1-1153 (cabang 0) sebagai debit (menambah aktiva)
                    updateSaldoAkun($conn, $akun_1153_id, $akun_kredit, $total, 0, 'debit');
                } else {
                    // Buat akun 1-1153 jika belum ada
                    $insert_1153 = "INSERT INTO laba_kategori (name, kode_akun, kategori, tipe_akun, saldo, cabang) 
                                    VALUES ('Kas Bank BRI R Transaksi 0251', '1-1153', 'aktiva', 'debit', $total, 0)";
                    mysqli_query($conn, $insert_1153);
                }
            } else {
                // Transaksi biasa
                updateSaldoAkun($conn, $akun_debit, $akun_kredit, $total, $cabang, 'debit');
                updateSaldoAkun($conn, $akun_kredit, $akun_debit, $total, $cabang, 'kredit');
            }
        } else if ($kategori && $jumlah > 0) {
            // Backward compatibility: update saldo untuk single-entry (kategori saja)
            updateSaldoAkunSingle($conn, $kategori, $jumlah, $tipe, $cabang);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Data berhasil disimpan',
            'data' => [
                'id' => $id
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data: ' . mysqli_error($conn)]);
    }
}

/**
 * Fungsi helper untuk mendapatkan info akun berdasarkan ID (untuk transfer)
 * @param mysqli $conn Database connection
 * @param int $akun_id ID akun
 * @return array|null Info akun atau null jika tidak ditemukan
 */
function getAkunInfoForTransfer($conn, $akun_id) {
    $query = "SELECT id, kode_akun, name, kategori, tipe_akun, cabang FROM laba_kategori WHERE id = " . (int)$akun_id;
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

/**
 * Update saldo akun untuk double-entry system
 * @param mysqli $conn Database connection
 * @param int $akun_id ID akun yang diupdate
 * @param int $akun_pasangan ID akun pasangan (untuk validasi)
 * @param float $jumlah Jumlah transaksi
 * @param int $cabang ID cabang
 * @param string $posisi 'debit' atau 'kredit'
 */
function updateSaldoAkun($conn, $akun_id, $akun_pasangan, $jumlah, $cabang, $posisi) {
    // Get akun info
    $akun_query = "SELECT id, kategori, tipe_akun, saldo, cabang FROM laba_kategori WHERE id = " . (int)$akun_id;
    $akun_result = mysqli_query($conn, $akun_query);
    
    if (!$akun_result || mysqli_num_rows($akun_result) == 0) {
        error_log("Akun dengan ID $akun_id tidak ditemukan");
        return;
    }
    
    $akun = mysqli_fetch_assoc($akun_result);
    $kategori = strtolower(trim($akun['kategori'] ?? ''));
    $tipe_akun = strtolower(trim($akun['tipe_akun'] ?? ''));
    $saldo_sekarang = floatval($akun['saldo'] ?? 0);
    $akun_cabang = $akun['cabang'] ?? null;
    
    // Pastikan akun sesuai dengan cabang transaksi
    if ($akun_cabang !== null && $akun_cabang != $cabang && $akun_cabang != 0) {
        error_log("Akun $akun_id tidak sesuai dengan cabang transaksi ($cabang)");
        return;
    }
    
    // Hitung perubahan saldo berdasarkan kategori dan posisi
    $perubahan_saldo = 0;
    
    if ($kategori == 'aktiva') {
        // Aktiva: normal saldo DEBIT
        // Debit = menambah aktiva, Kredit = mengurangi aktiva
        if ($posisi == 'debit') {
            $perubahan_saldo = $jumlah; // Menambah
        } else {
            $perubahan_saldo = -$jumlah; // Mengurangi
        }
        
        // Jika tipe_akun = 'kredit' (kontra aktiva), kebalikannya
        if ($tipe_akun == 'kredit') {
            $perubahan_saldo = -$perubahan_saldo;
        }
    } else if ($kategori == 'pasiva') {
        // Pasiva: normal saldo KREDIT
        // Debit = mengurangi pasiva, Kredit = menambah pasiva
        if ($posisi == 'debit') {
            $perubahan_saldo = -$jumlah; // Mengurangi
        } else {
            $perubahan_saldo = $jumlah; // Menambah
        }
        
        // Jika tipe_akun = 'debit' (kontra pasiva), kebalikannya
        if ($tipe_akun == 'debit') {
            $perubahan_saldo = -$perubahan_saldo;
        }
    } else if ($kategori == 'modal') {
        // Modal: normal saldo KREDIT
        // Debit = mengurangi modal, Kredit = menambah modal
        if ($posisi == 'debit') {
            $perubahan_saldo = -$jumlah; // Mengurangi
        } else {
            $perubahan_saldo = $jumlah; // Menambah
        }
        
        // Jika tipe_akun = 'debit' (kontra modal), kebalikannya
        if ($tipe_akun == 'debit') {
            $perubahan_saldo = -$perubahan_saldo;
        }
    } else {
        // Untuk kategori lain (pendapatan, beban), tidak update saldo di neraca
        // Karena pendapatan dan beban masuk ke laporan laba rugi, bukan neraca
        return;
    }
    
    $saldo_baru = $saldo_sekarang + $perubahan_saldo;
    
    // Update saldo
    $update_query = "UPDATE laba_kategori SET saldo = $saldo_baru WHERE id = " . (int)$akun_id;
    if (mysqli_query($conn, $update_query)) {
        error_log("Saldo akun $akun_id diupdate: $saldo_sekarang -> $saldo_baru (perubahan: $perubahan_saldo, posisi: $posisi)");
    } else {
        error_log("Gagal update saldo akun $akun_id: " . mysqli_error($conn));
    }
}

/**
 * Update saldo akun untuk single-entry system (backward compatibility)
 * @param mysqli $conn Database connection
 * @param int $kategori_id ID kategori
 * @param float $jumlah Jumlah transaksi
 * @param int $tipe 0 = masuk, 1 = keluar
 * @param int $cabang ID cabang
 */
function updateSaldoAkunSingle($conn, $kategori_id, $jumlah, $tipe, $cabang) {
    // Get kategori info
    $kat_query = "SELECT id, kategori, tipe_akun, saldo, cabang FROM laba_kategori WHERE id = " . (int)$kategori_id;
    $kat_result = mysqli_query($conn, $kat_query);
    
    if (!$kat_result || mysqli_num_rows($kat_result) == 0) {
        return;
    }
    
    $kat = mysqli_fetch_assoc($kat_result);
    $kategori = strtolower(trim($kat['kategori'] ?? ''));
    $tipe_akun = strtolower(trim($kat['tipe_akun'] ?? ''));
    $saldo_sekarang = floatval($kat['saldo'] ?? 0);
    $kat_cabang = $kat['cabang'] ?? null;
    
    // Pastikan kategori sesuai dengan cabang
    if ($kat_cabang !== null && $kat_cabang != $cabang && $kat_cabang != 0) {
        return;
    }
    
    // Hitung perubahan saldo
    $perubahan_saldo = 0;
    
    if ($kategori == 'aktiva') {
        // Aktiva: Masuk (tipe=0) = menambah, Keluar (tipe=1) = mengurangi
        if ($tipe == 0) {
            $perubahan_saldo = $jumlah;
        } else {
            $perubahan_saldo = -$jumlah;
        }
        
        if ($tipe_akun == 'kredit') {
            $perubahan_saldo = -$perubahan_saldo;
        }
    } else if ($kategori == 'pasiva') {
        // Pasiva: Masuk (tipe=0) = mengurangi, Keluar (tipe=1) = menambah
        if ($tipe == 0) {
            $perubahan_saldo = -$jumlah;
        } else {
            $perubahan_saldo = $jumlah;
        }
        
        if ($tipe_akun == 'debit') {
            $perubahan_saldo = -$perubahan_saldo;
        }
    } else if ($kategori == 'modal') {
        // Modal: Masuk (tipe=0) = menambah, Keluar (tipe=1) = mengurangi
        if ($tipe == 0) {
            $perubahan_saldo = $jumlah;
        } else {
            $perubahan_saldo = -$jumlah;
        }
        
        if ($tipe_akun == 'debit') {
            $perubahan_saldo = -$perubahan_saldo;
        }
    } else {
        // Pendapatan dan beban tidak update saldo neraca
        return;
    }
    
    $saldo_baru = $saldo_sekarang + $perubahan_saldo;
    
    // Update saldo
    $update_query = "UPDATE laba_kategori SET saldo = $saldo_baru WHERE id = " . (int)$kategori_id;
    mysqli_query($conn, $update_query);
}

function handlePut() {
    global $conn;
    
    // Check if file is uploaded (multipart/form-data)
    $has_file = isset($_FILES['file_lampiran']) && $_FILES['file_lampiran']['error'] === UPLOAD_ERR_OK;
    
    // Determine data source: prioritize $_POST for FormData, then try JSON
    // When called from POST with action=update, $_POST will be populated
    if ($has_file || !empty($_POST)) {
        // Use POST data for FormData (multipart/form-data)
        $data = $_POST;
        // If $_POST is empty but $_REQUEST has data, use $_REQUEST (fallback)
        if (empty($data) && !empty($_REQUEST)) {
            $data = $_REQUEST;
        }
    } else {
        // Try to get JSON data from php://input
        $raw_input = file_get_contents('php://input');
        $data = json_decode($raw_input, true);
        
        // If JSON decode failed, try POST/REQUEST as fallback
        if ($data === null || (json_last_error() !== JSON_ERROR_NONE && empty($data))) {
            $data = !empty($_POST) ? $_POST : (!empty($_REQUEST) ? $_REQUEST : []);
        }
    }
    
    // Handle file upload if exists
    $file_lampiran_path = null;
    if ($has_file) {
        $file_lampiran_path = handleFileUpload($_FILES['file_lampiran']);
        if ($file_lampiran_path === false) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Gagal mengupload file lampiran'
            ]);
            exit;
        }
    }
    
    // Debug: Log received data (remove in production)
    error_log("PUT/UPDATE Data: " . print_r($data, true));
    error_log("Has File: " . ($has_file ? 'yes' : 'no'));
    error_log("POST Data: " . print_r($_POST, true));
    error_log("REQUEST Data: " . print_r($_REQUEST, true));
    error_log("FILES Data: " . print_r($_FILES, true));
    
    // Get ID from data - check multiple possible sources in order of priority
    $id = null;
    
    // Priority 1: Check $data array (which should contain $_POST or $_REQUEST data)
    if (isset($data['id']) && $data['id'] !== '' && $data['id'] !== null) {
        $id = trim($data['id']);
    }
    // Priority 2: Check $_POST directly
    elseif (isset($_POST['id']) && $_POST['id'] !== '' && $_POST['id'] !== null) {
        $id = trim($_POST['id']);
    }
    // Priority 3: Check $_REQUEST as last resort
    elseif (isset($_REQUEST['id']) && $_REQUEST['id'] !== '' && $_REQUEST['id'] !== null) {
        $id = trim($_REQUEST['id']);
    }
    
    if (!$id || $id === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'ID tidak ditemukan',
            'debug' => [
                'data_id' => $data['id'] ?? 'not set',
                'post_id' => $_POST['id'] ?? 'not set',
                'request_id' => $_REQUEST['id'] ?? 'not set',
                'has_file' => $has_file,
                'data_keys' => array_keys($data ?? []),
                'post_keys' => array_keys($_POST ?? []),
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
            ]
        ]);
        exit;
    }
    
    $id = mysqli_real_escape_string($conn, $id);
    
    // Get old data untuk reverse saldo dan delete old file
    $old_query = "SELECT * FROM laba WHERE id = '$id'";
    $old_result = mysqli_query($conn, $old_query);
    $old_data = null;
    $old_file_path = null;
    if ($old_result && mysqli_num_rows($old_result) > 0) {
        $old_data = mysqli_fetch_assoc($old_result);
        // Check if file_lampiran column exists
        $check_file_column = "SHOW COLUMNS FROM laba LIKE 'file_lampiran'";
        $file_column_result = mysqli_query($conn, $check_file_column);
        if ($file_column_result && mysqli_num_rows($file_column_result) > 0) {
            $old_file_path = $old_data['file_lampiran'] ?? null;
        }
    }
    
    // Check if new columns exist
    $check_columns = "SHOW COLUMNS FROM laba LIKE 'jenis_transaksi'";
    $column_result = mysqli_query($conn, $check_columns);
    $has_new_columns = ($column_result && mysqli_num_rows($column_result) > 0);
    
    // Handle new double-entry fields
    $jenis_transaksi = isset($data['jenis_transaksi']) ? mysqli_real_escape_string($conn, $data['jenis_transaksi']) : null;
    $akun_debit = isset($data['akun_debit']) ? mysqli_real_escape_string($conn, $data['akun_debit']) : (isset($data['kategori']) ? mysqli_real_escape_string($conn, $data['kategori']) : null);
    $akun_kredit = isset($data['akun_kredit']) ? mysqli_real_escape_string($conn, $data['akun_kredit']) : null;
    
    // For backward compatibility: determine tipe from jenis_transaksi or use old tipe
    $tipe = null;
    if (isset($data['tipe'])) {
        $tipe = (int)$data['tipe'];
    } else if ($jenis_transaksi) {
        $pemasukan_types = ['pemasukan', 'pemasukan_piutang', 'tanam_modal'];
        $pengeluaran_types = ['pengeluaran', 'hutang', 'tarik_modal', 'transfer_hutang'];
        if (in_array($jenis_transaksi, $pemasukan_types)) {
            $tipe = 0;
        } else if (in_array($jenis_transaksi, $pengeluaran_types)) {
            $tipe = 1;
        } else {
            $tipe = 0;
        }
    }
    
    $kategori = $akun_debit;
    $cabang = isset($data['cabang']) ? (int)$data['cabang'] : null;
    $nominal = isset($data['nominal']) ? floatval($data['nominal']) : (isset($data['jumlah']) ? floatval($data['jumlah']) : null);
    $bunga = isset($data['bunga']) ? floatval($data['bunga']) : null;
    $pajak = isset($data['pajak']) ? floatval($data['pajak']) : null;
    $total = isset($data['total']) ? floatval($data['total']) : (isset($data['jumlah']) ? floatval($data['jumlah']) : null);
    $jumlah = $total !== null ? $total : (isset($data['jumlah']) ? floatval($data['jumlah']) : null);
    $keterangan = isset($data['keterangan']) ? mysqli_real_escape_string($conn, $data['keterangan']) : null;
    $date = isset($data['date']) ? mysqli_real_escape_string($conn, $data['date']) : null;
    $name = isset($data['name']) ? mysqli_real_escape_string($conn, $data['name']) : null;
    $tag = isset($data['tag']) ? mysqli_real_escape_string($conn, $data['tag']) : null;
    
    // Use uploaded file path if available, otherwise keep old file
    if ($file_lampiran_path !== null) {
        $file_lampiran = mysqli_real_escape_string($conn, $file_lampiran_path);
        // Delete old file if exists
        if ($old_file_path && file_exists('../' . $old_file_path)) {
            @unlink('../' . $old_file_path);
        }
    } else {
        // If no new file is uploaded, retain the old file path from the database
        // This ensures that if a user updates other fields but doesn't upload a new file,
        // the existing file attachment is not lost.
        $file_lampiran = mysqli_real_escape_string($conn, $old_file_path ?? '');
    }
    
    // Format date if needed
    if ($date && strlen($date) == 10) {
        $date = $date . ' ' . date('H:i:s');
    }
    
    $update_fields = [];
    if ($tipe !== null) $update_fields[] = "tipe = $tipe";
    if ($kategori !== null) $update_fields[] = "kategori = '$kategori'";
    if ($cabang !== null) $update_fields[] = "cabang = $cabang";
    if ($jumlah !== null) $update_fields[] = "jumlah = '$jumlah'";
    if ($keterangan !== null) $update_fields[] = "keterangan = " . ($keterangan ? "'$keterangan'" : "NULL");
    if ($date !== null) $update_fields[] = "date = '$date'";
    if ($name !== null) $update_fields[] = "name = " . ($name ? "'$name'" : "NULL");
    
    // Add new fields if columns exist
    if ($has_new_columns) {
        if ($jenis_transaksi !== null) $update_fields[] = "jenis_transaksi = " . ($jenis_transaksi ? "'$jenis_transaksi'" : "NULL");
        if ($akun_debit !== null) $update_fields[] = "akun_debit = " . ($akun_debit ? "'$akun_debit'" : "NULL");
        if ($akun_kredit !== null) $update_fields[] = "akun_kredit = " . ($akun_kredit ? "'$akun_kredit'" : "NULL");
        if ($nominal !== null) $update_fields[] = "nominal = '$nominal'";
        if ($bunga !== null) $update_fields[] = "bunga = '$bunga'";
        if ($pajak !== null) $update_fields[] = "pajak = '$pajak'";
        if ($total !== null) $update_fields[] = "total = '$total'";
        if ($tag !== null) $update_fields[] = "tag = " . ($tag ? "'$tag'" : "NULL");
        if ($file_lampiran !== null) $update_fields[] = "file_lampiran = " . ($file_lampiran ? "'$file_lampiran'" : "NULL");
    }
    
    // Check if updated_at column exists
    $check_updated_at = "SHOW COLUMNS FROM laba LIKE 'updated_at'";
    $updated_at_result = mysqli_query($conn, $check_updated_at);
    if ($updated_at_result && mysqli_num_rows($updated_at_result) > 0) {
        $update_fields[] = "updated_at = NOW()";
    }
    
    if (empty($update_fields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tidak ada data yang diupdate']);
        exit;
    }
    
    $query = "UPDATE laba SET " . implode(', ', $update_fields) . " WHERE id = '$id'";
    
    if (mysqli_query($conn, $query)) {
        // Reverse saldo lama jika ada
        if ($old_data) {
            $old_has_new_columns = isset($old_data['jenis_transaksi']);
            $old_akun_debit = $old_data['akun_debit'] ?? ($old_data['kategori'] ?? null);
            $old_akun_kredit = $old_data['akun_kredit'] ?? null;
            $old_total = floatval($old_data['total'] ?? $old_data['jumlah'] ?? 0);
            $old_cabang = intval($old_data['cabang'] ?? 0);
            $old_tipe = intval($old_data['tipe'] ?? 0);
            $old_kategori = $old_data['kategori'] ?? null;
            $old_jumlah = floatval($old_data['jumlah'] ?? 0);
            
            // Reverse saldo lama
            if ($old_has_new_columns && $old_akun_debit && $old_akun_kredit && $old_total > 0) {
                // Cek apakah ini transfer dari 1-1100 ke 1-1152 untuk cabang selain 0
                $old_akun_debit_info = getAkunInfoForTransfer($conn, $old_akun_debit);
                $old_akun_kredit_info = getAkunInfoForTransfer($conn, $old_akun_kredit);
                $old_jenis_transaksi = $old_data['jenis_transaksi'] ?? null;
                
                $is_transfer_to_bank = false;
                if ($old_akun_debit_info && $old_akun_kredit_info) {
                    $old_kode_debit = $old_akun_debit_info['kode_akun'] ?? '';
                    $old_kode_kredit = $old_akun_kredit_info['kode_akun'] ?? '';
                    
                    // Cek jika transfer dari 1-1100 ke 1-1152 untuk cabang selain 0
                    if (($old_kode_debit == '1-1100' && $old_kode_kredit == '1-1152' && $old_cabang != 0) ||
                        ($old_kode_kredit == '1-1100' && $old_kode_debit == '1-1152' && $old_cabang != 0) ||
                        ($old_jenis_transaksi == 'transfer_uang' && 
                         (($old_kode_debit == '1-1100' && $old_kode_kredit == '1-1152') || 
                          ($old_kode_kredit == '1-1100' && $old_kode_debit == '1-1152')) && $old_cabang != 0)) {
                        $is_transfer_to_bank = true;
                    }
                }
                
                if ($is_transfer_to_bank) {
                    // Reverse transfer dari 1-1100 ke 1-1152 untuk cabang selain 0
                    // 1. Reverse saldo normal (debit dan kredit)
                    updateSaldoAkun($conn, $old_akun_debit, $old_akun_kredit, $old_total, $old_cabang, 'kredit'); // Reverse debit
                    updateSaldoAkun($conn, $old_akun_kredit, $old_akun_debit, $old_total, $old_cabang, 'debit'); // Reverse kredit
                    
                    // 2. Reverse juga dari 1-1153 (cabang 0)
                    // Cari ID akun 1-1153 untuk cabang 0
                    $query_1153 = "SELECT id FROM laba_kategori WHERE kode_akun = '1-1153' AND (cabang = 0 OR cabang IS NULL) LIMIT 1";
                    $result_1153 = mysqli_query($conn, $query_1153);
                    if ($result_1153 && mysqli_num_rows($result_1153) > 0) {
                        $row_1153 = mysqli_fetch_assoc($result_1153);
                        $akun_1153_id = intval($row_1153['id']);
                        // Reverse dari 1-1153 (cabang 0) sebagai kredit (mengurangi aktiva)
                        updateSaldoAkun($conn, $akun_1153_id, $old_akun_kredit, $old_total, 0, 'kredit');
                    }
                } else {
                    // Reverse double-entry biasa
                    updateSaldoAkun($conn, $old_akun_debit, $old_akun_kredit, $old_total, $old_cabang, 'kredit'); // Reverse debit
                    updateSaldoAkun($conn, $old_akun_kredit, $old_akun_debit, $old_total, $old_cabang, 'debit'); // Reverse kredit
                }
            } else if ($old_kategori && $old_jumlah > 0) {
                // Reverse single-entry
                updateSaldoAkunSingle($conn, $old_kategori, $old_jumlah, ($old_tipe == 0 ? 1 : 0), $old_cabang); // Reverse tipe
            }
        }
        
        // Apply saldo baru
        if ($has_new_columns && $akun_debit && $akun_kredit && $total > 0) {
            // Cek apakah ini transfer dari 1-1100 ke 1-1152 untuk cabang selain 0
            $akun_debit_info = getAkunInfoForTransfer($conn, $akun_debit);
            $akun_kredit_info = getAkunInfoForTransfer($conn, $akun_kredit);
            
            $is_transfer_to_bank = false;
            if ($akun_debit_info && $akun_kredit_info) {
                $kode_debit = $akun_debit_info['kode_akun'] ?? '';
                $kode_kredit = $akun_kredit_info['kode_akun'] ?? '';
                
                // Cek jika transfer dari 1-1100 ke 1-1152 untuk cabang selain 0
                if (($kode_debit == '1-1100' && $kode_kredit == '1-1152' && $cabang != 0) ||
                    ($kode_kredit == '1-1100' && $kode_debit == '1-1152' && $cabang != 0) ||
                    ($jenis_transaksi == 'transfer_uang' && 
                     (($kode_debit == '1-1100' && $kode_kredit == '1-1152') || 
                      ($kode_kredit == '1-1100' && $kode_debit == '1-1152')) && $cabang != 0)) {
                    $is_transfer_to_bank = true;
                }
            }
            
            if ($is_transfer_to_bank) {
                // Transfer dari 1-1100 ke 1-1152 untuk cabang selain 0
                // 1. Update saldo normal (debit dan kredit)
                updateSaldoAkun($conn, $akun_debit, $akun_kredit, $total, $cabang, 'debit');
                updateSaldoAkun($conn, $akun_kredit, $akun_debit, $total, $cabang, 'kredit');
                
                // 2. Tambahkan juga ke 1-1153 (cabang 0)
                // Cari ID akun 1-1153 untuk cabang 0
                $query_1153 = "SELECT id FROM laba_kategori WHERE kode_akun = '1-1153' AND (cabang = 0 OR cabang IS NULL) LIMIT 1";
                $result_1153 = mysqli_query($conn, $query_1153);
                if ($result_1153 && mysqli_num_rows($result_1153) > 0) {
                    $row_1153 = mysqli_fetch_assoc($result_1153);
                    $akun_1153_id = intval($row_1153['id']);
                    // Tambah ke 1-1153 (cabang 0) sebagai debit (menambah aktiva)
                    updateSaldoAkun($conn, $akun_1153_id, $akun_kredit, $total, 0, 'debit');
                } else {
                    // Buat akun 1-1153 jika belum ada
                    $insert_1153 = "INSERT INTO laba_kategori (name, kode_akun, kategori, tipe_akun, saldo, cabang) 
                                    VALUES ('Kas Bank BRI R Transaksi 0251', '1-1153', 'aktiva', 'debit', $total, 0)";
                    mysqli_query($conn, $insert_1153);
                }
            } else {
                // Transaksi biasa
                updateSaldoAkun($conn, $akun_debit, $akun_kredit, $total, $cabang, 'debit');
                updateSaldoAkun($conn, $akun_kredit, $akun_debit, $total, $cabang, 'kredit');
            }
        } else if ($kategori && $jumlah > 0) {
            updateSaldoAkunSingle($conn, $kategori, $jumlah, $tipe, $cabang);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Data berhasil diupdate'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate data: ' . mysqli_error($conn)]);
    }
}

/**
 * Handle update akun_debit and akun_kredit only (for Edit Akun page)
 */
function handleUpdateAkun() {
    global $conn;
    
    // ID adalah varchar/string (UUID), bukan integer
    $id = isset($_POST['id']) ? mysqli_real_escape_string($conn, $_POST['id']) : null;
    $akun_debit = isset($_POST['akun_debit']) && $_POST['akun_debit'] !== '' ? (int)$_POST['akun_debit'] : null;
    $akun_kredit = isset($_POST['akun_kredit']) && $_POST['akun_kredit'] !== '' ? (int)$_POST['akun_kredit'] : null;
    
    if (!$id || $id === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID transaksi harus diisi']);
        return;
    }
    
    // Cek dulu apakah ID ada di database
    $checkQuery = "SELECT id, akun_debit, akun_kredit, kategori FROM laba WHERE id = '$id'";
    $checkResult = mysqli_query($conn, $checkQuery);
    $existingData = mysqli_fetch_assoc($checkResult);
    
    if (!$existingData) {
        echo json_encode([
            'success' => false, 
            'message' => 'ID tidak ditemukan di database',
            'id_dicari' => $id
        ]);
        return;
    }
    
    // Update tabel laba
    $updateFields = [];
    
    if ($akun_debit !== null) {
        $updateFields[] = "akun_debit = $akun_debit";
        // Juga update kategori untuk backward compatibility
        $updateFields[] = "kategori = '$akun_debit'";
    }
    
    if ($akun_kredit !== null) {
        $updateFields[] = "akun_kredit = $akun_kredit";
    }
    
    if (empty($updateFields)) {
        echo json_encode(['success' => true, 'message' => 'Tidak ada perubahan']);
        return;
    }
    
    // ID adalah string (UUID), gunakan quotes
    $updateQuery = "UPDATE laba SET " . implode(', ', $updateFields) . " WHERE id = '$id'";
    
    if (mysqli_query($conn, $updateQuery)) {
        $affectedRows = mysqli_affected_rows($conn);
        
        // Verifikasi dengan SELECT ulang
        $verifyQuery = "SELECT id, akun_debit, akun_kredit, kategori FROM laba WHERE id = '$id'";
        $verifyResult = mysqli_query($conn, $verifyQuery);
        $newData = mysqli_fetch_assoc($verifyResult);
        
        echo json_encode([
            'success' => true,
            'message' => 'Transaksi berhasil diupdate',
            'affected_rows' => $affectedRows,
            'data_sebelum' => $existingData,
            'data_sesudah' => $newData
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal update transaksi: ' . mysqli_error($conn),
            'query' => $updateQuery
        ]);
    }
}

function handleDelete() {
    global $conn;
    
    // Get ID from URL or request body
    $id = null;
    
    // Try to get from URL path
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path_parts = explode('/', trim($path, '/'));
    $last_part = end($path_parts);
    
    if ($last_part && $last_part !== 'laba.php') {
        $id = mysqli_real_escape_string($conn, $last_part);
    } else {
        // Try from GET parameter
        $id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : null;
    }
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan']);
        exit;
    }
    
    // Get data untuk reverse saldo sebelum delete
    $old_query = "SELECT * FROM laba WHERE id = '$id'";
    $old_result = mysqli_query($conn, $old_query);
    
    if (!$old_result || mysqli_num_rows($old_result) == 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        exit;
    }
    
    $old_data = mysqli_fetch_assoc($old_result);
    
    // Check if new columns exist
    $check_columns = "SHOW COLUMNS FROM laba LIKE 'jenis_transaksi'";
    $column_result = mysqli_query($conn, $check_columns);
    $has_new_columns = ($column_result && mysqli_num_rows($column_result) > 0);
    
    $old_akun_debit = $old_data['akun_debit'] ?? ($old_data['kategori'] ?? null);
    $old_akun_kredit = $old_data['akun_kredit'] ?? null;
    $old_total = floatval($old_data['total'] ?? $old_data['jumlah'] ?? 0);
    $old_cabang = intval($old_data['cabang'] ?? 0);
    $old_tipe = intval($old_data['tipe'] ?? 0);
    $old_kategori = $old_data['kategori'] ?? null;
    $old_jumlah = floatval($old_data['jumlah'] ?? 0);
    
    // Get old file path if column exists
    $old_file_path = null;
    $check_file_column = "SHOW COLUMNS FROM laba LIKE 'file_lampiran'";
    $file_column_result = mysqli_query($conn, $check_file_column);
    if ($file_column_result && mysqli_num_rows($file_column_result) > 0) {
        $old_file_path = $old_data['file_lampiran'] ?? null;
    }
    
    $query = "DELETE FROM laba WHERE id = '$id'";
    
    if (mysqli_query($conn, $query)) {
        // Delete file if exists
        if ($old_file_path && file_exists('../' . $old_file_path)) {
            @unlink('../' . $old_file_path);
        }
        
        // Reverse saldo setelah delete
        if ($has_new_columns && $old_akun_debit && $old_akun_kredit && $old_total > 0) {
            // Cek apakah ini transfer dari 1-1100 ke 1-1152 untuk cabang selain 0
            $old_akun_debit_info = getAkunInfoForTransfer($conn, $old_akun_debit);
            $old_akun_kredit_info = getAkunInfoForTransfer($conn, $old_akun_kredit);
            $old_jenis_transaksi = $old_data['jenis_transaksi'] ?? null;
            
            $is_transfer_to_bank = false;
            if ($old_akun_debit_info && $old_akun_kredit_info) {
                $old_kode_debit = $old_akun_debit_info['kode_akun'] ?? '';
                $old_kode_kredit = $old_akun_kredit_info['kode_akun'] ?? '';
                
                // Cek jika transfer dari 1-1100 ke 1-1152 untuk cabang selain 0
                if (($old_kode_debit == '1-1100' && $old_kode_kredit == '1-1152' && $old_cabang != 0) ||
                    ($old_kode_kredit == '1-1100' && $old_kode_debit == '1-1152' && $old_cabang != 0) ||
                    ($old_jenis_transaksi == 'transfer_uang' && 
                     (($old_kode_debit == '1-1100' && $old_kode_kredit == '1-1152') || 
                      ($old_kode_kredit == '1-1100' && $old_kode_debit == '1-1152')) && $old_cabang != 0)) {
                    $is_transfer_to_bank = true;
                }
            }
            
            if ($is_transfer_to_bank) {
                // Reverse transfer dari 1-1100 ke 1-1152 untuk cabang selain 0
                // 1. Reverse saldo normal (debit dan kredit)
                updateSaldoAkun($conn, $old_akun_debit, $old_akun_kredit, $old_total, $old_cabang, 'kredit'); // Reverse debit
                updateSaldoAkun($conn, $old_akun_kredit, $old_akun_debit, $old_total, $old_cabang, 'debit'); // Reverse kredit
                
                // 2. Reverse juga dari 1-1153 (cabang 0)
                // Cari ID akun 1-1153 untuk cabang 0
                $query_1153 = "SELECT id FROM laba_kategori WHERE kode_akun = '1-1153' AND (cabang = 0 OR cabang IS NULL) LIMIT 1";
                $result_1153 = mysqli_query($conn, $query_1153);
                if ($result_1153 && mysqli_num_rows($result_1153) > 0) {
                    $row_1153 = mysqli_fetch_assoc($result_1153);
                    $akun_1153_id = intval($row_1153['id']);
                    // Reverse dari 1-1153 (cabang 0) sebagai kredit (mengurangi aktiva)
                    updateSaldoAkun($conn, $akun_1153_id, $old_akun_kredit, $old_total, 0, 'kredit');
                }
            } else {
                // Reverse double-entry biasa
                updateSaldoAkun($conn, $old_akun_debit, $old_akun_kredit, $old_total, $old_cabang, 'kredit'); // Reverse debit
                updateSaldoAkun($conn, $old_akun_kredit, $old_akun_debit, $old_total, $old_cabang, 'debit'); // Reverse kredit
            }
        } else if ($old_kategori && $old_jumlah > 0) {
            // Reverse single-entry
            updateSaldoAkunSingle($conn, $old_kategori, $old_jumlah, ($old_tipe == 0 ? 1 : 0), $old_cabang); // Reverse tipe
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Data berhasil dihapus'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data: ' . mysqli_error($conn)]);
    }
}

