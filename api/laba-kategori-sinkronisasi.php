<?php
header('Content-Type: application/json');
include '../aksi/koneksi.php';

// Cek session
session_start();
if (!isset($_SESSION['user_level'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user is admin or super admin
if ($_SESSION['user_level'] != 'admin' && $_SESSION['user_level'] != 'super admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Check if cabang column exists
$check_column = "SHOW COLUMNS FROM laba_kategori LIKE 'cabang'";
$column_result = mysqli_query($conn, $check_column);
$cabang_column_exists = ($column_result && mysqli_num_rows($column_result) > 0);

if ($method === 'GET') {
    handleGet();
} elseif ($method === 'POST') {
    handlePost();
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function handleGet()
{
    global $conn, $cabang_column_exists;

    $action = $_GET['action'] ?? 'get_all';

    if ($action === 'get_all') {
        // Get all akun from all cabang
        $filter_cabang = isset($_GET['cabang']) && $_GET['cabang'] !== '' ? (int)$_GET['cabang'] : null;
        $filter_kategori = isset($_GET['kategori']) && $_GET['kategori'] !== '' ? mysqli_real_escape_string($conn, $_GET['kategori']) : null;
        $search = isset($_GET['search']) && $_GET['search'] !== '' ? mysqli_real_escape_string($conn, $_GET['search']) : null;

        $query = "SELECT lk.*, 
                         CASE 
                           WHEN lk.cabang = 0 THEN 'PCNU (Default)'
                           WHEN t.toko_nama IS NOT NULL THEN CONCAT(t.toko_nama, ' - ', t.toko_kota)
                           ELSE 'Cabang Tidak Diketahui'
                         END as cabang_name,
                         t.toko_kota 
                  FROM laba_kategori lk 
                  LEFT JOIN toko t ON lk.cabang = t.toko_cabang 
                  WHERE 1=1";

        if ($cabang_column_exists && $filter_cabang !== null) {
            $query .= " AND lk.cabang = $filter_cabang";
        }

        if ($filter_kategori) {
            $query .= " AND lk.kategori = '$filter_kategori'";
        }

        if ($search) {
            $query .= " AND (lk.name LIKE '%$search%' OR lk.kode_akun LIKE '%$search%')";
        }

        $query .= " ORDER BY lk.cabang, lk.kategori, lk.name";

        $result = mysqli_query($conn, $query);

        if (!$result) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . mysqli_error($conn)
            ]);
            return;
        }

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        echo json_encode([
            'success' => true,
            'message' => 'success',
            'data' => $data
        ]);
    } elseif ($action === 'check_duplicate') {
        // Check for duplicate akun names across different cabangs
        if (!$cabang_column_exists) {
            echo json_encode([
                'success' => true,
                'message' => 'Kolom cabang belum ada, tidak ada duplikasi',
                'data' => []
            ]);
            return;
        }

        // Find duplicate names across different cabangs
        // Get all akun names that exist in multiple cabangs
        $query = "SELECT lk.name, 
                         GROUP_CONCAT(DISTINCT CONCAT(t.toko_nama, ' (', t.toko_kota, ')') ORDER BY t.toko_nama SEPARATOR ', ') as cabangs
                  FROM laba_kategori lk
                  LEFT JOIN toko t ON lk.cabang = t.toko_cabang
                  WHERE lk.cabang IS NOT NULL
                  GROUP BY lk.name
                  HAVING COUNT(DISTINCT lk.cabang) > 1";

        $result = mysqli_query($conn, $query);

        if (!$result) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Gagal mengecek duplikasi: ' . mysqli_error($conn)
            ]);
            return;
        }

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = [
                'name' => $row['name'],
                'cabangs' => explode(', ', $row['cabangs'])
            ];
        }

        echo json_encode([
            'success' => true,
            'message' => 'success',
            'data' => $data
        ]);
    }
}

function handlePost()
{
    global $conn, $cabang_column_exists;

    if (!$cabang_column_exists) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Kolom cabang belum ada di database. Silakan jalankan script SQL terlebih dahulu.'
        ]);
        return;
    }

    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);

    // Debug: Log received data
    error_log("POST Data received: " . print_r($data, true));
    error_log("Raw input: " . $raw_input);

    if ($data === null || empty($data)) {
        $data = $_POST;
        error_log("Using $_POST instead: " . print_r($_POST, true));
    }

    $action = $data['action'] ?? '';

    if ($action === 'sinkronisasi') {
        // Sinkronisasi semua akun dari satu cabang ke cabang lain
        // Get values and validate
        $source_cabang_raw = $data['source_cabang'] ?? null;
        $target_cabang_raw = $data['target_cabang'] ?? null;

        // Convert to integer, handle string, null, empty (0 is valid for PCNU)
        $source_cabang = null;
        $target_cabang = null;

        // Check if source_cabang is valid (not null, not empty - 0 is valid for PCNU)
        if ($source_cabang_raw !== null && $source_cabang_raw !== '') {
            $source_cabang = (int)$source_cabang_raw;
            // 0 is valid (PCNU), only reject if less than 0
            if ($source_cabang < 0) {
                $source_cabang = null;
            }
        }

        // Check if target_cabang is valid (not null, not empty - 0 is valid for PCNU)
        if ($target_cabang_raw !== null && $target_cabang_raw !== '') {
            $target_cabang = (int)$target_cabang_raw;
            // 0 is valid (PCNU), only reject if less than 0
            if ($target_cabang < 0) {
                $target_cabang = null;
            }
        }

        $skip_duplicate = isset($data['skip_duplicate']) ? (bool)$data['skip_duplicate'] : true;

        // Check if source_cabang and target_cabang are set (0 is valid for PCNU)
        if ($source_cabang === null || $target_cabang === null) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Cabang sumber dan tujuan harus diisi',
                'debug' => [
                    'source_cabang_raw' => $source_cabang_raw,
                    'target_cabang_raw' => $target_cabang_raw,
                    'source_cabang' => $source_cabang,
                    'target_cabang' => $target_cabang,
                    'received_data' => $data
                ]
            ]);
            return;
        }

        if ($source_cabang === $target_cabang) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Cabang sumber dan tujuan tidak boleh sama'
            ]);
            return;
        }

        // Get all akun from source cabang
        // Note: If source_cabang is 0 (PCNU), we get all accounts with cabang = 0
        $query_source = "SELECT * FROM laba_kategori WHERE cabang = $source_cabang";
        $result_source = mysqli_query($conn, $query_source);

        // Debug: Log query for troubleshooting
        error_log("Sinkronisasi: Source cabang = $source_cabang, Target cabang = $target_cabang");
        error_log("Query source: $query_source");

        // VERIFICATION: Check how many accounts exist in target cabang BEFORE sync
        $verify_query = "SELECT COUNT(*) as total FROM laba_kategori WHERE cabang = $target_cabang";
        $verify_result = mysqli_query($conn, $verify_query);
        $total_target_before = 0;
        $verify_row = null;
        if ($verify_result) {
            $verify_row = mysqli_fetch_assoc($verify_result);
            $total_target_before = (int)$verify_row['total'];
            error_log("VERIFICATION: Total accounts in target cabang $target_cabang BEFORE sync: $total_target_before");
        }

        // VERIFICATION: Check for accounts with same names in target cabang
        $verify_names_query = "SELECT name, COUNT(*) as cnt FROM laba_kategori WHERE cabang = $target_cabang GROUP BY name";
        $verify_names_result = mysqli_query($conn, $verify_names_query);
        $existing_names = [];
        if ($verify_names_result) {
            while ($vn_row = mysqli_fetch_assoc($verify_names_result)) {
                $existing_names[] = $vn_row['name'];
            }
            error_log("VERIFICATION: Existing account names in target cabang $target_cabang (" . count($existing_names) . " names): " . implode(', ', array_slice($existing_names, 0, 20)));
        }

        if (!$result_source) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Gagal mengambil akun dari cabang sumber: ' . mysqli_error($conn)
            ]);
            return;
        }

        $copied = 0;
        $skipped = 0;
        $errors = [];
        $skipped_details = []; // Store details of skipped accounts

        // Debug: Count total records from source
        $total_source = mysqli_num_rows($result_source);
        mysqli_data_seek($result_source, 0); // Reset pointer to start

        error_log("=== SINKRONISASI START ===");
        error_log("Source cabang: $source_cabang, Target cabang: $target_cabang");
        error_log("Total akun dari source: $total_source");
        error_log("Skip duplicate: " . ($skip_duplicate ? 'YES' : 'NO'));

        while ($row = mysqli_fetch_assoc($result_source)) {
            // Validate required fields
            if (empty($row['name']) || trim($row['name']) === '') {
                $errors[] = "Akun dengan ID {$row['id']} tidak memiliki nama, dilewati";
                continue;
            }

            if (empty($row['kategori']) || trim($row['kategori']) === '') {
                $errors[] = "Akun '{$row['name']}' tidak memiliki kategori, dilewati";
                continue;
            }

            if (empty($row['tipe_akun']) || trim($row['tipe_akun']) === '') {
                $errors[] = "Akun '{$row['name']}' tidak memiliki tipe akun, dilewati";
                continue;
            }

            // Check if akun already exists in target cabang (if skip_duplicate is true)
            if ($skip_duplicate) {
                $name_escaped = mysqli_real_escape_string($conn, trim($row['name']));
                $source_cabang_value = isset($row['cabang']) ? (int)$row['cabang'] : null;

                // Only check for duplicates if target cabang is NOT 0 (PCNU)
                // If target is cabang 0, we don't check duplicates because it's global
                if ($target_cabang != 0) {
                    // Check if account with same name exists in the SPECIFIC target cabang ONLY
                    // CRITICAL: We MUST exclude cabang 0 (PCNU) from the check
                    // because cabang 0 is global and should not prevent copying to specific branches
                    // IMPORTANT: We check EXACT cabang match only, not cabang 0
                    $check_query = "SELECT id, cabang, name, kode_akun, kategori, tipe_akun 
                                   FROM laba_kategori 
                                   WHERE name = '$name_escaped' 
                                   AND cabang = $target_cabang
                                   AND cabang IS NOT NULL
                                   LIMIT 1";

                    // Debug logging
                    error_log("Checking duplicate for: '{$row['name']}' (ID: {$row['id']}, source_cabang: $source_cabang_value)");
                    error_log("  Query: $check_query");

                    $check_result = mysqli_query($conn, $check_query);

                    if (!$check_result) {
                        // If query fails, log error but continue
                        $error_msg = mysqli_error($conn);
                        $errors[] = "Error checking duplicate for '{$row['name']}': " . $error_msg;
                        error_log("  ERROR: " . $error_msg);
                    } else {
                        $num_rows = mysqli_num_rows($check_result);
                        error_log("  Result: $num_rows row(s) found");

                        if ($num_rows > 0) {
                            // Account already exists in target cabang
                            $dup_row = mysqli_fetch_assoc($check_result);

                            // Verify: Double check that the found account really has the target cabang
                            $found_cabang = isset($dup_row['cabang']) ? (int)$dup_row['cabang'] : null;
                            $found_id = isset($dup_row['id']) ? (int)$dup_row['id'] : null;

                            error_log("  Found duplicate: ID=$found_id, cabang=$found_cabang, name='{$dup_row['name']}'");

                            // IMPORTANT: ID 0 is not valid (ID starts from 1)
                            // If found_id is 0, we should not skip because ID 0 is invalid
                            // We will insert with a valid ID (>= 1)
                            if ($found_id === 0) {
                                error_log("  → WARNING: Found ID is 0 (invalid ID, should start from 1)");
                                error_log("  → Will continue with insert using valid ID (>= 1)");
                                // Continue with insert, will use valid ID
                            } else if ($found_cabang === $target_cabang) {
                                // Only skip if the found account really belongs to target cabang and has valid ID (>= 1)
                                $skipped++;
                                error_log("  → SKIPPED (duplicate found in target cabang with valid ID)");

                                // Store first 10 skipped account details for debugging
                                if (count($skipped_details) < 10) {
                                    $skipped_details[] = [
                                        'name' => $row['name'],
                                        'source_cabang' => $source_cabang_value ?? 'NULL',
                                        'source_id' => $row['id'] ?? 'N/A',
                                        'target_cabang' => $target_cabang,
                                        'found_id' => $found_id ?? 'N/A',
                                        'found_cabang' => $found_cabang ?? 'N/A',
                                        'found_name' => $dup_row['name'] ?? 'N/A',
                                        'found_kode_akun' => $dup_row['kode_akun'] ?? 'N/A',
                                        'found_kategori' => $dup_row['kategori'] ?? 'N/A',
                                        'found_tipe_akun' => $dup_row['tipe_akun'] ?? 'N/A',
                                        'check_query' => $check_query,
                                        'verification' => "Found account ID $found_id with cabang $found_cabang matches target cabang $target_cabang",
                                        'full_found_data' => $dup_row
                                    ];
                                }
                                continue;
                            } else {
                                // Found account but cabang doesn't match - this shouldn't happen, log it
                                error_log("  WARNING: Cabang mismatch! Found cabang: $found_cabang, Target: $target_cabang");
                                error_log("  → CONTINUING (will insert anyway)");
                                // Continue with insert anyway
                            }
                        } else {
                            error_log("  → NO DUPLICATE (will insert)");
                        }
                    }
                } else {
                    // Target is cabang 0 (PCNU), don't check duplicates as it's global
                    // Just proceed with insert
                    error_log("Skipping duplicate check: target is cabang 0 (PCNU)");
                }
            } else {
                error_log("Skip duplicate is FALSE - will insert all accounts");
            }

            // Insert akun to target cabang
            $name = mysqli_real_escape_string($conn, trim($row['name']));
            $kode_akun = isset($row['kode_akun']) ? mysqli_real_escape_string($conn, trim($row['kode_akun'])) : '';
            $kategori = mysqli_real_escape_string($conn, trim($row['kategori']));
            $tipe_akun = mysqli_real_escape_string($conn, trim($row['tipe_akun']));
            $saldo = isset($row['saldo']) ? floatval($row['saldo']) : 0;

            // Get source ID, but ensure it's >= 1 (ID starts from 1, not 0)
            $source_id = isset($row['id']) ? (int)$row['id'] : 0;
            if ($source_id <= 0) {
                // If source ID is 0 or invalid, find next available ID starting from 1
                $max_id_query = "SELECT COALESCE(MAX(id), 0) as max_id FROM laba_kategori";
                $max_id_result = mysqli_query($conn, $max_id_query);
                if ($max_id_result && $max_row = mysqli_fetch_assoc($max_id_result)) {
                    $source_id = max(1, (int)$max_row['max_id'] + 1);
                } else {
                    $source_id = 1;
                }
                error_log("  Source ID was 0 or invalid, using next available ID: $source_id");
            }

            // Find available ID if source ID is already taken
            $target_id = $source_id;
            $id_check_query = "SELECT id FROM laba_kategori WHERE id = $target_id";
            $id_check_result = mysqli_query($conn, $id_check_query);

            if ($id_check_result && mysqli_num_rows($id_check_result) > 0) {
                // ID already exists, find next available ID
                error_log("  ID $target_id already exists, finding next available ID...");

                // Optimized: Find next available ID using query
                // Start from source_id and increment until we find an available ID
                $target_id = $source_id;
                $max_attempts = 1000; // Safety limit
                $attempts = 0;

                while ($attempts < $max_attempts) {
                    $check_id_query = "SELECT id FROM laba_kategori WHERE id = $target_id LIMIT 1";
                    $check_id_result = mysqli_query($conn, $check_id_query);

                    if (!$check_id_result || mysqli_num_rows($check_id_result) == 0) {
                        // Found available ID
                        break;
                    }

                    $target_id++;
                    $attempts++;
                }

                // Ensure ID is at least 1
                if ($target_id < 1) {
                    $target_id = 1;
                    $attempts = 0;
                    while ($attempts < $max_attempts) {
                        $check_id_query = "SELECT id FROM laba_kategori WHERE id = $target_id LIMIT 1";
                        $check_id_result = mysqli_query($conn, $check_id_query);

                        if (!$check_id_result || mysqli_num_rows($check_id_result) == 0) {
                            break;
                        }

                        $target_id++;
                        $attempts++;
                    }
                }

                if ($attempts >= $max_attempts) {
                    // Fallback: use MAX(id) + 1
                    $max_id_query = "SELECT COALESCE(MAX(id), 0) as max_id FROM laba_kategori";
                    $max_id_result = mysqli_query($conn, $max_id_query);
                    if ($max_id_result && $max_row = mysqli_fetch_assoc($max_id_result)) {
                        $target_id = max(1, (int)$max_row['max_id'] + 1);
                    } else {
                        $target_id = 1;
                    }
                    error_log("  Using fallback: MAX(id) + 1 = $target_id");
                } else {
                    error_log("  Found available ID: $target_id (source was $source_id, attempts: $attempts)");
                }
            }

            // Build INSERT query with explicit ID
            $insert_query = "INSERT INTO laba_kategori (id, name, kode_akun, kategori, tipe_akun, saldo, cabang) 
                            VALUES ($target_id, '$name', '$kode_akun', '$kategori', '$tipe_akun', $saldo, $target_cabang)";

            error_log("  Attempting INSERT: '{$row['name']}' to cabang $target_cabang with ID=$target_id (source ID was " . ($row['id'] ?? 'N/A') . ")");

            if (mysqli_query($conn, $insert_query)) {
                $copied++;
                error_log("  → SUCCESS: Inserted with ID=$target_id");
            } else {
                $error_msg = mysqli_error($conn);
                error_log("  → FAILED: " . $error_msg);
                // Check for duplicate entry error
                if (strpos($error_msg, 'Duplicate entry') !== false) {
                    // If still duplicate, try with next available ID
                    $target_id++;
                    $retry_query = "INSERT INTO laba_kategori (id, name, kode_akun, kategori, tipe_akun, saldo, cabang) 
                                   VALUES ($target_id, '$name', '$kode_akun', '$kategori', '$tipe_akun', $saldo, $target_cabang)";
                    if (mysqli_query($conn, $retry_query)) {
                        $copied++;
                        error_log("  → SUCCESS (retry): Inserted with ID=$target_id");
                    } else {
                        $skipped++;
                        $errors[] = "Gagal menyalin akun '{$row['name']}' (ID: {$row['id']}): " . mysqli_error($conn);
                        error_log("  → Marked as SKIPPED (duplicate entry error after retry)");
                    }
                } else {
                    $errors[] = "Gagal menyalin akun '{$row['name']}' (ID: {$row['id']}): " . $error_msg;
                }
            }
        }

        // VERIFICATION: Check how many accounts exist in target cabang AFTER sync
        $verify_after_query = "SELECT COUNT(*) as total FROM laba_kategori WHERE cabang = $target_cabang";
        $verify_after_result = mysqli_query($conn, $verify_after_query);
        $total_after = 0;
        if ($verify_after_result) {
            $verify_after_row = mysqli_fetch_assoc($verify_after_result);
            $total_after = $verify_after_row['total'];
        }

        error_log("=== SINKRONISASI END ===");
        error_log("Copied: $copied, Skipped: $skipped, Errors: " . count($errors));
        error_log("Total source: $total_source");
        error_log("Total accounts in target cabang AFTER sync: $total_after");

        // Get actual data from target cabang to verify
        $verify_data_query = "SELECT id, name, cabang FROM laba_kategori WHERE cabang = $target_cabang ORDER BY name LIMIT 10";
        $verify_data_result = mysqli_query($conn, $verify_data_query);
        $actual_accounts = [];
        if ($verify_data_result) {
            while ($vd_row = mysqli_fetch_assoc($verify_data_result)) {
                $actual_accounts[] = "ID:{$vd_row['id']} - {$vd_row['name']} (cabang:{$vd_row['cabang']})";
            }
            error_log("Sample accounts in target cabang: " . implode(', ', $actual_accounts));
        }

        $message = "Sinkronisasi selesai. Berhasil menyalin $copied akun";
        if ($skipped > 0) {
            $message .= ", melewati $skipped akun yang sudah ada";
            // Add debug info for skipped accounts
            if (count($skipped_details) > 0) {
                $message .= "\n\nContoh akun yang dilewati (10 pertama):";
                foreach ($skipped_details as $idx => $detail) {
                    $message .= "\n" . ($idx + 1) . ". {$detail['name']}";
                    $message .= "\n   - Dari: Cabang {$detail['source_cabang']} (ID: {$detail['source_id']})";
                    $message .= "\n   - Ke: Cabang {$detail['target_cabang']}";
                    $message .= "\n   - ⚠️ DITEMUKAN di database:";
                    $message .= "\n     • ID: {$detail['found_id']}";
                    $message .= "\n     • Nama: {$detail['found_name']}";
                    $message .= "\n     • Kode: " . ($detail['found_kode_akun'] ?? '-');
                    $message .= "\n     • Cabang: {$detail['found_cabang']}";
                    $message .= "\n     • Kategori: " . ($detail['found_kategori'] ?? '-');
                    $message .= "\n   → Akun ini SUDAH ADA di database dengan ID {$detail['found_id']} di cabang {$detail['target_cabang']}";
                }
            }
        }
        if (count($errors) > 0) {
            $message .= ". Terjadi " . count($errors) . " error";
            // Show first 5 errors in message for debugging
            $error_preview = array_slice($errors, 0, 5);
            $message .= "\n\nError detail (5 pertama):\n" . implode("\n", $error_preview);
            if (count($errors) > 5) {
                $message .= "\n... dan " . (count($errors) - 5) . " error lainnya";
            }
        }

        echo json_encode([
            'success' => count($errors) === 0, // Success only if no errors
            'message' => $message,
            'data' => [
                'copied' => $copied,
                'skipped' => $skipped,
                'total_source' => $total_source,
                'total_target_before' => $total_target_before,
                'total_target_after' => $total_after,
                'errors' => $errors,
                'skipped_details' => $skipped_details,
                'total_errors' => count($errors),
                'verification' => [
                    'target_cabang' => $target_cabang,
                    'existing_accounts_sample' => array_slice($actual_accounts, 0, 10),
                    'existing_names_count' => count($existing_names ?? [])
                ]
            ]
        ]);
    } elseif ($action === 'copy_single') {
        // Copy single akun to another cabang
        $akun_id = isset($data['akun_id']) ? (int)$data['akun_id'] : null;
        $target_cabang = isset($data['target_cabang']) ? (int)$data['target_cabang'] : null;

        if (!$akun_id || !$target_cabang) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID akun dan cabang tujuan harus diisi'
            ]);
            return;
        }

        // Get akun data
        $query_akun = "SELECT * FROM laba_kategori WHERE id = $akun_id";
        $result_akun = mysqli_query($conn, $query_akun);

        if (!$result_akun || mysqli_num_rows($result_akun) === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Akun tidak ditemukan'
            ]);
            return;
        }

        $akun = mysqli_fetch_assoc($result_akun);

        // Check if akun already exists in target cabang
        $check_query = "SELECT id FROM laba_kategori WHERE name = '" . mysqli_real_escape_string($conn, $akun['name']) . "' AND cabang = $target_cabang";
        $check_result = mysqli_query($conn, $check_query);
        if ($check_result && mysqli_num_rows($check_result) > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Akun dengan nama yang sama sudah ada di cabang tujuan'
            ]);
            return;
        }

        // Insert akun to target cabang
        $name = mysqli_real_escape_string($conn, $akun['name']);
        $kode_akun = mysqli_real_escape_string($conn, $akun['kode_akun'] ?? '');
        $kategori = mysqli_real_escape_string($conn, $akun['kategori']);
        $tipe_akun = mysqli_real_escape_string($conn, $akun['tipe_akun']);
        $saldo = floatval($akun['saldo'] ?? 0);

        // Get source ID, but ensure it's >= 1 (ID starts from 1, not 0)
        $source_id = isset($akun['id']) ? (int)$akun['id'] : 0;
        if ($source_id <= 0) {
            // If source ID is 0 or invalid, find next available ID starting from 1
            $max_id_query = "SELECT COALESCE(MAX(id), 0) as max_id FROM laba_kategori";
            $max_id_result = mysqli_query($conn, $max_id_query);
            if ($max_id_result && $max_row = mysqli_fetch_assoc($max_id_result)) {
                $source_id = max(1, (int)$max_row['max_id'] + 1);
            } else {
                $source_id = 1;
            }
        }

        // Find available ID if source ID is already taken
        $target_id = $source_id;
        $id_check_query = "SELECT id FROM laba_kategori WHERE id = $target_id";
        $id_check_result = mysqli_query($conn, $id_check_query);

        if ($id_check_result && mysqli_num_rows($id_check_result) > 0) {
            // ID already exists, find next available ID
            $target_id = $source_id;
            $max_attempts = 1000; // Safety limit
            $attempts = 0;

            while ($attempts < $max_attempts) {
                $check_id_query = "SELECT id FROM laba_kategori WHERE id = $target_id LIMIT 1";
                $check_id_result = mysqli_query($conn, $check_id_query);

                if (!$check_id_result || mysqli_num_rows($check_id_result) == 0) {
                    // Found available ID
                    break;
                }

                $target_id++;
                $attempts++;
            }

            // Ensure ID is at least 1
            if ($target_id < 1) {
                $target_id = 1;
                $attempts = 0;
                while ($attempts < $max_attempts) {
                    $check_id_query = "SELECT id FROM laba_kategori WHERE id = $target_id LIMIT 1";
                    $check_id_result = mysqli_query($conn, $check_id_query);

                    if (!$check_id_result || mysqli_num_rows($check_id_result) == 0) {
                        break;
                    }

                    $target_id++;
                    $attempts++;
                }
            }

            if ($attempts >= $max_attempts) {
                // Fallback: use MAX(id) + 1
                $max_id_query = "SELECT COALESCE(MAX(id), 0) as max_id FROM laba_kategori";
                $max_id_result = mysqli_query($conn, $max_id_query);
                if ($max_id_result && $max_row = mysqli_fetch_assoc($max_id_result)) {
                    $target_id = max(1, (int)$max_row['max_id'] + 1);
                } else {
                    $target_id = 1;
                }
            }
        }

        $insert_query = "INSERT INTO laba_kategori (id, name, kode_akun, kategori, tipe_akun, saldo, cabang) 
                        VALUES ($target_id, '$name', '$kode_akun', '$kategori', '$tipe_akun', $saldo, $target_cabang)";

        if (mysqli_query($conn, $insert_query)) {
            echo json_encode([
                'success' => true,
                'message' => 'Akun berhasil disalin ke cabang tujuan',
                'data' => ['id' => $target_id]
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Gagal menyalin akun: ' . mysqli_error($conn)
            ]);
        }
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Action tidak valid'
        ]);
    }
}
