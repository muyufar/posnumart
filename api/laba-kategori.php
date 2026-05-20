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

$method = $_SERVER['REQUEST_METHOD'];

/**
 * @param mysqli $conn
 */
function labaKategoriColumnExists($conn, $column)
{
    $column = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM laba_kategori LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

/**
 * Validasi induk COA untuk create/update (level 2–4).
 *
 * @param array $data data request (name, kategori, cabang, parent_id)
 * @param int   $coa_level 1–4
 */
function labaKategoriAppendHierarchyErrors($conn, array $data, &$errors, $coa_level)
{
    $parent_id_col = labaKategoriColumnExists($conn, 'parent_id');
    $level_col = labaKategoriColumnExists($conn, 'level');
    if (!$parent_id_col || !$level_col) {
        return;
    }
    if ($coa_level === 1) {
        return;
    }
    $pid = isset($data['parent_id']) ? (int) $data['parent_id'] : 0;
    if ($pid <= 0) {
        $errors[] = 'Pilih induk akun sesuai level yang dipilih';

        return;
    }
    if (isset($data['id']) && $pid === (int) $data['id']) {
        $errors[] = 'Akun tidak boleh menjadi induk untuk dirinya sendiri';

        return;
    }
    $pq = mysqli_query($conn, "SELECT id, level, cabang, kategori FROM laba_kategori WHERE id = $pid");
    if (!$pq || !($prow = mysqli_fetch_assoc($pq))) {
        $errors[] = 'Akun induk tidak ditemukan';

        return;
    }
    $pl = isset($prow['level']) ? (int) $prow['level'] : 0;
    $expected_parent_level = $coa_level - 1;
    if ($pl !== $expected_parent_level) {
        $errors[] = 'Induk harus berupa akun level '.$expected_parent_level.' (sesuai jenis akun yang Anda pilih)';
    }
    $pk = isset($prow['kategori']) ? trim((string) $prow['kategori']) : '';
    if ($pk !== '' && $pk !== trim((string) ($data['kategori'] ?? ''))) {
        $errors[] = 'Kategori harus sama dengan akun induk';
    }
    if (labaKategoriColumnExists($conn, 'cabang')) {
        $pCab = isset($prow['cabang']) ? (int) $prow['cabang'] : null;
        $childCab = isset($data['cabang']) && $data['cabang'] !== '' && $data['cabang'] !== null
            ? (int) $data['cabang']
            : (isset($_SESSION['user_cabang']) ? (int) $_SESSION['user_cabang'] : null);
        if ($childCab !== null && $pCab !== null && $pCab !== $childCab) {
            $errors[] = 'Cabang harus sama dengan cabang akun induk';
        }
    }
}

/**
 * Rantai id dari akar ke induk langsung baris $id (tanpa $id sendiri).
 *
 * @param mysqli $conn
 * @return int[]
 */
function labaKategoriAncestorIdsRootToParent($conn, $id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return [];
    }
    $q = "SELECT parent_id FROM laba_kategori WHERE id = $id";
    $result = mysqli_query($conn, $q);
    if (!$result || !($row = mysqli_fetch_assoc($result))) {
        return [];
    }
    $pid = (int) ($row['parent_id'] ?? 0);
    if ($pid <= 0) {
        return [];
    }
    $chain = [];
    $current = $pid;
    $guard = 0;
    while ($current > 0 && $guard < 64) {
        $guard++;
        $q2 = "SELECT id, parent_id FROM laba_kategori WHERE id = $current";
        $r2 = mysqli_query($conn, $q2);
        if (!$r2 || !($row2 = mysqli_fetch_assoc($r2))) {
            break;
        }
        array_unshift($chain, (int) $row2['id']);
        $current = (int) ($row2['parent_id'] ?? 0);
    }
    return $chain;
}

/**
 * Label singkat posisi level akun (untuk respons JSON).
 */
function labaKategoriLevelLabel($level)
{
    $level = (int) $level;
    $map = [
        1 => 'Kepala Level 1 (akar COA)',
        2 => 'Kepala Level 2',
        3 => 'Kepala Level 3',
        4 => 'Sub akun (Level 4)',
    ];
    if (isset($map[$level])) {
        return $map[$level];
    }

    return $level > 0 ? 'Level '.$level : '-';
}

/**
 * Tambah field level_label, induk_langsung, keterangan_hierarki pada satu baris hasil query.
 *
 * @param mysqli $conn
 */
function labaKategoriEnrichHierarchyFields($conn, array &$row)
{
    $level = isset($row['level']) ? (int) $row['level'] : 0;
    $row['level_label'] = labaKategoriLevelLabel($level);
    $row['induk_langsung'] = null;
    $row['keterangan_hierarki'] = '';

    if (! labaKategoriColumnExists($conn, 'parent_id')) {
        $row['keterangan_hierarki'] = '-';

        return;
    }

    $id = isset($row['id']) ? (int) $row['id'] : 0;
    $pid = isset($row['parent_id']) ? (int) $row['parent_id'] : 0;

    if ($pid <= 0) {
        $row['keterangan_hierarki'] = 'Akar COA — tidak ada kepala akun di atasnya';

        return;
    }

    $pq = mysqli_query($conn, "SELECT name, kode_akun FROM laba_kategori WHERE id = $pid LIMIT 1");
    if ($pq && $pr = mysqli_fetch_assoc($pq)) {
        $s = $pr['name'];
        if (! empty($pr['kode_akun'])) {
            $s .= ' ('.$pr['kode_akun'].')';
        }
        $row['induk_langsung'] = $s;
    }

    if ($id <= 0) {
        return;
    }

    $chainIds = labaKategoriAncestorIdsRootToParent($conn, $id);
    $parts = [];
    foreach ($chainIds as $aid) {
        $r = mysqli_query($conn, 'SELECT name, kode_akun, level FROM laba_kategori WHERE id = '.(int) $aid.' LIMIT 1');
        if ($r && $rn = mysqli_fetch_assoc($r)) {
            $lv = isset($rn['level']) ? (int) $rn['level'] : 0;
            $lbl = '[L'.$lv.'] '.$rn['name'];
            if (! empty($rn['kode_akun'])) {
                $lbl .= ' ('.$rn['kode_akun'].')';
            }
            $parts[] = $lbl;
        }
    }
    $row['keterangan_hierarki'] = implode(' › ', $parts);
}

/**
 * Saldo tampilan per akun = saldo sendiri + jumlah saldo semua turunan (sub → menjumlah ke L3, L2, L1).
 * Menggunakan filter cabang yang sama dengan daftar (GET cabang / session).
 *
 * @param mysqli $conn
 * @param mixed  $cabang nilai $cabang dari handleGet (boleh null)
 *
 * @return array<int, float> id => saldo teragregasi
 */
function labaKategoriSaldoAggregatedMap($conn, $cabang_column_exists, $cabang)
{
    if (! labaKategoriColumnExists($conn, 'parent_id')) {
        return [];
    }

    $aggQuery = 'SELECT id, parent_id, saldo FROM laba_kategori WHERE 1=1';
    if (isset($_GET['cabang']) && $_GET['cabang'] !== '' && $cabang_column_exists) {
        $cabang_filter = (int) $_GET['cabang'];
        if ($cabang_filter === 0) {
            $aggQuery .= ' AND cabang = 0';
        } else {
            $aggQuery .= " AND cabang = $cabang_filter";
        }
    } elseif ($cabang !== null && $cabang_column_exists) {
        if ($cabang != 0) {
            $aggQuery .= " AND cabang = $cabang";
        }
    }

    $res = mysqli_query($conn, $aggQuery);
    if (! $res) {
        return [];
    }

    $byId = [];
    $children = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $id = (int) $row['id'];
        $byId[$id] = $row;
        $pid = isset($row['parent_id']) ? (int) $row['parent_id'] : 0;
        if ($pid <= 0) {
            $pid = 0;
        }
        if (! isset($children[$pid])) {
            $children[$pid] = [];
        }
        $children[$pid][] = $id;
    }

    if ($byId === []) {
        return [];
    }

    $memo = [];
    $rollup = function ($id) use (&$rollup, &$byId, &$children, &$memo) {
        if (isset($memo[$id])) {
            return $memo[$id];
        }
        $own = floatval($byId[$id]['saldo'] ?? 0);
        $sum = $own;
        foreach ($children[$id] ?? [] as $cid) {
            $sum += $rollup($cid);
        }
        $memo[$id] = $sum;

        return $sum;
    };

    $out = [];
    foreach (array_keys($byId) as $id) {
        $out[$id] = $rollup($id);
    }

    return $out;
}

/**
 * Terapkan saldo teragregasi: saldo_asli = kolom DB, saldo = jumlah turunan.
 *
 * @param array<int, float> $aggMap
 */
function labaKategoriApplySaldoRollup(array &$row, array $aggMap)
{
    $id = isset($row['id']) ? (int) $row['id'] : 0;
    if ($id <= 0 || ! isset($aggMap[$id])) {
        return;
    }
    $row['saldo_asli'] = isset($row['saldo']) ? floatval($row['saldo']) : 0.0;
    $row['saldo'] = round($aggMap[$id], 2);
}

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost();
        break;
    case 'PUT':
        handlePut();
        break;
    case 'DELETE':
        handleDelete();
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}

function handleGetHierarchy()
{
    global $conn;

    $parent_id_column = labaKategoriColumnExists($conn, 'parent_id');
    $level_column = labaKategoriColumnExists($conn, 'level');
    if (!$parent_id_column || !$level_column) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Kolom hierarki (parent_id / level) tidak ditemukan pada tabel laba_kategori',
        ]);
        return;
    }

    $cabang_column_exists = labaKategoriColumnExists($conn, 'cabang');

    if (!isset($_GET['cabang']) || $_GET['cabang'] === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cabang wajib untuk memuat hierarki COA']);
        return;
    }
    $cabang = (int) $_GET['cabang'];

    if (!isset($_GET['kategori']) || trim((string) $_GET['kategori']) === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Kategori wajib untuk memuat hierarki COA']);
        return;
    }
    $kategori = mysqli_real_escape_string($conn, trim($_GET['kategori']));

    $parent_id = isset($_GET['parent_id']) ? (int) $_GET['parent_id'] : 0;

    $query = "SELECT id, name, kode_akun, level, parent_id FROM laba_kategori WHERE kategori = '$kategori'";

    if ($cabang_column_exists) {
        if ($cabang === 0) {
            $query .= ' AND (cabang = 0 OR cabang IS NULL)';
        } else {
            $query .= " AND cabang = $cabang";
        }
    }

    if ($parent_id === 0) {
        $query .= ' AND (parent_id IS NULL OR parent_id = 0) AND level = 1';
    } else {
        $query .= " AND parent_id = $parent_id";
    }

    $query .= ' ORDER BY name';

    $result = mysqli_query($conn, $query);
    if (!$result) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mengambil hierarki: ' . mysqli_error($conn),
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
        'data' => $data,
    ]);
}

function handleGet()
{
    global $conn;

    if (isset($_GET['for_hierarchy']) && $_GET['for_hierarchy'] === '1') {
        handleGetHierarchy();
        return;
    }

    // Check if cabang column exists
    $cabang_column_exists = false;
    $check_column = "SHOW COLUMNS FROM laba_kategori LIKE 'cabang'";
    $column_result = mysqli_query($conn, $check_column);
    if ($column_result && mysqli_num_rows($column_result) > 0) {
        $cabang_column_exists = true;
    }

    // Get cabang from query parameter (explicit filter)
    // If cabang is provided in GET, use it (even if it's 0)
    // If not provided, check if we should use session cabang
    $cabang = null;
    if (isset($_GET['cabang']) && $_GET['cabang'] !== '') {
        // Explicit filter from user
        $cabang = (int)$_GET['cabang'];
    } else {
        // No explicit filter, use session cabang as default (if exists)
        $session_cabang = isset($_SESSION['user_cabang']) ? (int)$_SESSION['user_cabang'] : null;
        $cabang = $session_cabang;
    }

    // Get single item by ID
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $query = "SELECT * FROM laba_kategori WHERE id = $id";
        if ($cabang !== null && $cabang_column_exists) {
            // Include categories for this cabang OR global categories (cabang IS NULL) OR default cabang (cabang = 0)
            // Cabang 0 (PCNU) dianggap sebagai cabang default yang bisa digunakan semua cabang
            $query .= " AND (cabang = $cabang OR cabang IS NULL OR cabang = 0)";
        }
        $result = mysqli_query($conn, $query);

        if ($result && $row = mysqli_fetch_assoc($result)) {
            if (isset($_GET['with_ancestors']) && $_GET['with_ancestors'] === '1' && labaKategoriColumnExists($conn, 'parent_id')) {
                $row['ancestor_ids'] = labaKategoriAncestorIdsRootToParent($conn, $id);
            }
            if (! isset($_GET['skip_saldo_rollup']) || $_GET['skip_saldo_rollup'] !== '1') {
                $aggMapSingle = labaKategoriSaldoAggregatedMap($conn, $cabang_column_exists, $cabang);
                labaKategoriApplySaldoRollup($row, $aggMapSingle);
            }
            if (! isset($_GET['skip_hierarchy_keterangan']) || $_GET['skip_hierarchy_keterangan'] !== '1') {
                labaKategoriEnrichHierarchyFields($conn, $row);
            }
            echo json_encode([
                'success' => true,
                'message' => 'success',
                'data' => $row
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        }
        return;
    }

    // Get all categories filtered by cabang
    $query = "SELECT * FROM laba_kategori WHERE 1=1";

    // Filter by cabang if provided and column exists
    // IMPORTANT: Only filter if cabang is explicitly provided in GET parameter
    // If no cabang parameter, show all accounts (no filter)
    if (isset($_GET['cabang']) && $_GET['cabang'] !== '' && $cabang_column_exists) {
        $cabang_filter = (int)$_GET['cabang'];
        // Check if cabang is 0 (PCNU/Default) or specific cabang
        if ($cabang_filter == 0) {
            // If filter is cabang 0, show only cabang 0
            $query .= " AND cabang = 0";
        } else {
            // If filter is specific cabang, show ONLY that cabang (not including cabang 0 or NULL)
            // This ensures that when user selects a specific branch, they only see accounts from that branch
            $query .= " AND cabang = $cabang_filter";
        }
    } else if ($cabang !== null && $cabang_column_exists) {
        // Fallback: Use session cabang if no explicit filter (for backward compatibility)
        // But only if cabang is not 0 (to avoid showing all accounts)
        if ($cabang != 0) {
            $query .= " AND cabang = $cabang";
        }
    }
    
    // Filter by kategori if provided
    if (isset($_GET['kategori']) && $_GET['kategori'] !== '') {
        $kategori = mysqli_real_escape_string($conn, $_GET['kategori']);
        $query .= " AND kategori = '$kategori'";
    }
    
    // Filter by tipe_akun if provided
    if (isset($_GET['tipe_akun']) && $_GET['tipe_akun'] !== '') {
        $tipe_akun = mysqli_real_escape_string($conn, $_GET['tipe_akun']);
        $query .= " AND tipe_akun = '$tipe_akun'";
    }
    
    // Search by name or kode_akun if provided
    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $search = mysqli_real_escape_string($conn, $_GET['search']);
        $query .= " AND (name LIKE '%$search%' OR kode_akun LIKE '%$search%')";
    }

    // Urutan: sort=kode_akun|name, order=asc|desc (aman whitelist)
    $sortParam = isset($_GET['sort']) ? strtolower(trim((string) $_GET['sort'])) : '';
    $orderParam = isset($_GET['order']) ? strtolower(trim((string) $_GET['order'])) : 'asc';
    if ($orderParam !== 'desc') {
        $orderParam = 'asc';
    }
    $orderSql = strtoupper($orderParam);

    if ($sortParam === 'kode_akun') {
        // NULL / kosong di akhir agar tidak mengganggu urutan kode
        $query .= " ORDER BY (kode_akun IS NULL OR kode_akun = ''), kode_akun $orderSql, name ASC, id ASC";
    } elseif ($sortParam === 'name') {
        $query .= " ORDER BY name $orderSql, kode_akun ASC, id ASC";
    } else {
        $query .= ' ORDER BY kategori ASC, name ASC';
    }

    $result = mysqli_query($conn, $query);

    if (!$result) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mengambil data: ' . mysqli_error($conn)
        ]);
        return;
    }

    $skip_enrich = isset($_GET['skip_hierarchy_keterangan']) && $_GET['skip_hierarchy_keterangan'] === '1';
    $skip_saldo_rollup = isset($_GET['skip_saldo_rollup']) && $_GET['skip_saldo_rollup'] === '1';

    $aggMapList = [];
    if (! $skip_saldo_rollup) {
        $aggMapList = labaKategoriSaldoAggregatedMap($conn, $cabang_column_exists, $cabang);
    }

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        if (! $skip_saldo_rollup && $aggMapList !== []) {
            labaKategoriApplySaldoRollup($row, $aggMapList);
        }
        if (! $skip_enrich) {
            labaKategoriEnrichHierarchyFields($conn, $row);
        }
        $data[] = $row;
    }

    echo json_encode([
        'success' => true,
        'message' => 'success',
        'data' => $data
    ]);
}

function handlePost()
{
    global $conn;

    // Get JSON data
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);

    if ($data === null || empty($data)) {
        $data = $_POST;
    }

    // Validate required fields
    $errors = [];
    if (!isset($data['name']) || trim($data['name']) === '') {
        $errors[] = 'Nama kategori harus diisi';
    }
    if (!isset($data['kategori']) || trim($data['kategori']) === '') {
        $errors[] = 'Kategori harus diisi';
    }
    if (!isset($data['tipe_akun']) || trim($data['tipe_akun']) === '') {
        $errors[] = 'Tipe akun harus diisi';
    }

    $parent_id_col = labaKategoriColumnExists($conn, 'parent_id');
    $level_col = labaKategoriColumnExists($conn, 'level');

    $coa_level = isset($data['coa_level']) ? (int) $data['coa_level'] : 4;
    if ($coa_level < 1 || $coa_level > 4) {
        $coa_level = 4;
    }

    if ($parent_id_col && $level_col) {
        labaKategoriAppendHierarchyErrors($conn, $data, $errors, $coa_level);
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Data tidak valid',
            'errors' => $errors
        ]);
        exit;
    }

    $name = mysqli_real_escape_string($conn, trim($data['name']));
    $kode_akun = isset($data['kode_akun']) ? mysqli_real_escape_string($conn, trim($data['kode_akun'])) : '';
    $kategori = mysqli_real_escape_string($conn, trim($data['kategori']));
    $tipe_akun = mysqli_real_escape_string($conn, trim($data['tipe_akun']));
    $saldo = isset($data['saldo']) ? floatval($data['saldo']) : 0;

    // Check if cabang column exists
    $cabang_column_exists = false;
    $check_column = "SHOW COLUMNS FROM laba_kategori LIKE 'cabang'";
    $column_result = mysqli_query($conn, $check_column);
    if ($column_result && mysqli_num_rows($column_result) > 0) {
        $cabang_column_exists = true;
    }

    // Get cabang from data or session
    $cabang = null;
    if (isset($data['cabang']) && $data['cabang'] !== '' && $data['cabang'] !== null) {
        $cabang = (int)$data['cabang'];
    } else if (isset($_SESSION['user_cabang'])) {
        $cabang = (int)$_SESSION['user_cabang'];
    }

    $parent_id = isset($data['parent_id']) ? (int) $data['parent_id'] : 0;

    // Duplikat: nama harus unik per cabang + per induk (parent_id), bukan sekadar nama global.
    // Dulu OR cabang IS NULL membuat baris "global" memblokir cabang manapun — diperbaiki.
    $check_query = "SELECT id FROM laba_kategori WHERE name = '$name'";
    if ($cabang !== null && $cabang_column_exists) {
        if ($cabang === 0) {
            $check_query .= ' AND (cabang = 0 OR cabang IS NULL)';
        } else {
            $check_query .= " AND cabang = $cabang";
        }
    }
    if ($parent_id_col && $level_col) {
        if ($coa_level === 1) {
            $check_query .= ' AND (parent_id IS NULL OR parent_id = 0)';
        } else {
            $check_query .= " AND parent_id = $parent_id";
        }
    }
    $check_result = mysqli_query($conn, $check_query);
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Nama kategori sudah dipakai pada cabang dan posisi induk yang sama (ubah nama atau pilih induk lain)',
        ]);
        exit;
    }

    // Build query with cabang (only if column exists)
    $query = "INSERT INTO laba_kategori (name, kode_akun, kategori, tipe_akun, saldo";
    $values = "VALUES ('$name', '$kode_akun', '$kategori', '$tipe_akun', $saldo";

    if ($parent_id_col && $level_col) {
        $query .= ', parent_id, level';
        if ($coa_level === 1) {
            $values .= ', NULL, 1';
        } else {
            $values .= ", $parent_id, $coa_level";
        }
    }

    if ($cabang !== null && $cabang_column_exists) {
        $query .= ", cabang";
        $values .= ", $cabang";
    }

    $query .= ") " . $values . ")";

    if (mysqli_query($conn, $query)) {
        $id = mysqli_insert_id($conn);
        echo json_encode([
            'success' => true,
            'message' => 'Data berhasil disimpan',
            'data' => ['id' => $id]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menyimpan data: ' . mysqli_error($conn)
        ]);
    }
}

function handlePut()
{
    global $conn;

    // Get JSON data
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);

    if ($data === null || empty($data)) {
        $data = $_POST;
    }

    // Validate required fields
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID harus diisi']);
        exit;
    }

    $errors = [];
    if (!isset($data['name']) || trim($data['name']) === '') {
        $errors[] = 'Nama kategori harus diisi';
    }
    if (!isset($data['kategori']) || trim($data['kategori']) === '') {
        $errors[] = 'Kategori harus diisi';
    }
    if (!isset($data['tipe_akun']) || trim($data['tipe_akun']) === '') {
        $errors[] = 'Tipe akun harus diisi';
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Data tidak valid',
            'errors' => $errors
        ]);
        exit;
    }

    $id = (int)$data['id'];
    $name = mysqli_real_escape_string($conn, trim($data['name']));
    $kode_akun = isset($data['kode_akun']) ? mysqli_real_escape_string($conn, trim($data['kode_akun'])) : '';
    $kategori = mysqli_real_escape_string($conn, trim($data['kategori']));
    $tipe_akun = mysqli_real_escape_string($conn, trim($data['tipe_akun']));
    $saldo = isset($data['saldo']) ? floatval($data['saldo']) : 0;

    // Check if cabang column exists
    $cabang_column_exists = false;
    $check_column = "SHOW COLUMNS FROM laba_kategori LIKE 'cabang'";
    $column_result = mysqli_query($conn, $check_column);
    if ($column_result && mysqli_num_rows($column_result) > 0) {
        $cabang_column_exists = true;
    }

    // Get cabang from data or session
    $cabang = null;
    if (isset($data['cabang']) && $data['cabang'] !== '' && $data['cabang'] !== null) {
        $cabang = (int)$data['cabang'];
    } else if (isset($_SESSION['user_cabang'])) {
        $cabang = (int)$_SESSION['user_cabang'];
    }

    $parent_id_col = labaKategoriColumnExists($conn, 'parent_id');
    $level_col = labaKategoriColumnExists($conn, 'level');

    $curRow = null;
    $curQ = mysqli_query($conn, "SELECT parent_id, level FROM laba_kategori WHERE id = $id LIMIT 1");
    if ($curQ && $r = mysqli_fetch_assoc($curQ)) {
        $curRow = $r;
    }
    if (!$curRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);

        return;
    }

    $coa_level = isset($data['coa_level']) ? (int) $data['coa_level'] : 0;
    if ($coa_level < 1 || $coa_level > 4) {
        $coa_level = (int) ($curRow['level'] ?? 4);
        if ($coa_level < 1 || $coa_level > 4) {
            $coa_level = 4;
        }
    }

    $parent_id = isset($data['parent_id']) ? (int) $data['parent_id'] : 0;
    if ($coa_level === 1) {
        $parent_id = 0;
    } elseif ($parent_id === 0) {
        $parent_id = (int) ($curRow['parent_id'] ?? 0);
    }
    $data['parent_id'] = $parent_id;
    $data['id'] = $id;

    if ($parent_id_col && $level_col) {
        labaKategoriAppendHierarchyErrors($conn, $data, $errors, $coa_level);
    }
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Data tidak valid',
            'errors' => $errors,
        ]);
        exit;
    }

    // Duplikat: sama seperti POST — per cabang + per induk (setelah perubahan level/induk)
    $check_query = "SELECT id FROM laba_kategori WHERE name = '$name' AND id != $id";
    if ($cabang !== null && $cabang_column_exists) {
        if ($cabang === 0) {
            $check_query .= ' AND (cabang = 0 OR cabang IS NULL)';
        } else {
            $check_query .= " AND cabang = $cabang";
        }
    }
    if ($parent_id_col && $level_col) {
        if ($coa_level === 1) {
            $check_query .= ' AND (parent_id IS NULL OR parent_id = 0)';
        } else {
            $check_query .= " AND parent_id = $parent_id";
        }
    }
    $check_result = mysqli_query($conn, $check_query);
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Nama kategori sudah dipakai pada cabang dan posisi induk yang sama (ubah nama atau pilih induk lain)',
        ]);
        exit;
    }

    // Build update query
    $query = "UPDATE laba_kategori 
              SET name = '$name', 
                  kode_akun = '$kode_akun', 
                  kategori = '$kategori', 
                  tipe_akun = '$tipe_akun', 
                  saldo = $saldo";

    if ($cabang !== null && $cabang_column_exists) {
        $query .= ", cabang = $cabang";
    }

    if ($parent_id_col && $level_col) {
        if ($coa_level === 1) {
            $query .= ', parent_id = NULL, level = 1';
        } else {
            $query .= ", parent_id = $parent_id, level = $coa_level";
        }
    }

    $query .= " WHERE id = $id";

    if (mysqli_query($conn, $query)) {
        echo json_encode([
            'success' => true,
            'message' => 'Data berhasil diupdate'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mengupdate data: ' . mysqli_error($conn)
        ]);
    }
}

function handleDelete()
{
    global $conn;

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit;
    }

    // Check if category is being used
    $check_query = "SELECT COUNT(*) as count FROM laba WHERE kategori = '$id'";
    $check_result = mysqli_query($conn, $check_query);
    $check_row = mysqli_fetch_assoc($check_result);

    if ($check_row['count'] > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Kategori tidak dapat dihapus karena masih digunakan'
        ]);
        exit;
    }

    $query = "DELETE FROM laba_kategori WHERE id = $id";

    if (mysqli_query($conn, $query)) {
        echo json_encode([
            'success' => true,
            'message' => 'Data berhasil dihapus'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menghapus data: ' . mysqli_error($conn)
        ]);
    }
}
