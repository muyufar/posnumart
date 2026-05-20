<?php

if (session_status() === PHP_SESSION_NONE) {

    session_start();

}

header('Content-Type: application/json; charset=utf-8');



require_once __DIR__ . '/../aksi/koneksi.php';

require_once __DIR__ . '/laporan-pergantian-shift-lib.php';



if (!isset($_SESSION['user_id'])) {

    http_response_code(401);

    echo json_encode(['ok' => false, 'message' => 'Sesi habis, silakan login ulang.']);

    exit;

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode(['ok' => false, 'message' => 'Metode tidak diizinkan.']);

    exit;

}



try {

    $userLoginCabang = shift_laporan_db_query($conn, "SELECT user_cabang FROM user WHERE user_id = '" . (int) $_SESSION['user_id'] . "' LIMIT 1");

    $sessionCabangData = mysqli_fetch_array($userLoginCabang);

    $sessionCabang = ($sessionCabangData && isset($sessionCabangData['user_cabang'])) ? (int) $sessionCabangData['user_cabang'] : 0;



    shift_laporan_require_schema_ready($conn);



    $raw = file_get_contents('php://input');

    $data = json_decode($raw, true);

    if (!is_array($data)) {

        $data = $_POST;

    }



    $tanggal = isset($data['tanggal']) ? trim((string) $data['tanggal']) : '';

    $shift = shift_laporan_normalize_shift(isset($data['shift']) ? (string) $data['shift'] : 'pagi', $sessionCabang);

    $jamMulaiReq = isset($data['jam_mulai']) ? trim((string) $data['jam_mulai']) : '';

    $jamSelesaiReq = isset($data['jam_selesai']) ? trim((string) $data['jam_selesai']) : '';

    $jam = shift_laporan_resolve_jam($shift, $jamMulaiReq, $jamSelesaiReq, $sessionCabang);



    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {

        http_response_code(400);

        echo json_encode(['ok' => false, 'message' => 'Format tanggal tidak valid.']);

        exit;

    }



    $setorKe = isset($data['setor_ke']) ? trim((string) $data['setor_ke']) : '';

    $tglSetor = isset($data['tgl_setor']) ? trim((string) $data['tgl_setor']) : '';

    if ($tglSetor !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglSetor)) {

        $tglSetor = '';

    }



    $kasirInput = isset($data['kasir']) && is_array($data['kasir']) ? $data['kasir'] : [];

    $ttdInput = isset($data['ttd']) && is_array($data['ttd']) ? $data['ttd'] : [];



    $tanggalEsc = mysqli_real_escape_string($conn, $tanggal);

    $shiftEsc = mysqli_real_escape_string($conn, $shift);

    $jamMulaiEsc = mysqli_real_escape_string($conn, $jam['jam_mulai']);

    $jamSelesaiEsc = mysqli_real_escape_string($conn, $jam['jam_selesai']);

    $shiftIn = shift_laporan_shift_db_in_clause($shift, $sessionCabang);

    $setorKeEsc = mysqli_real_escape_string($conn, $setorKe);

    $tglSetorSql = $tglSetor === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $tglSetor) . "'";

    $userId = (int) $_SESSION['user_id'];

    $now = date('Y-m-d H:i:s');



    $ttdSql = '';

    $ttdSql .= shift_laporan_build_ttd_sql_part($conn, $ttdInput, 'kp_akt', 'ttd_kp_akt', $userId, $now);

    $ttdSql .= shift_laporan_build_ttd_sql_part($conn, $ttdInput, 'kasir1', 'ttd_kasir1', $userId, $now);

    $ttdSql .= shift_laporan_build_ttd_sql_part($conn, $ttdInput, 'kasir2', 'ttd_kasir2', $userId, $now);



    shift_laporan_db_begin($conn);



    $qExist = shift_laporan_db_query($conn, "

        SELECT id, shift FROM shift_laporan

        WHERE cabang = $sessionCabang AND tanggal = '$tanggalEsc' AND shift IN $shiftIn

        ORDER BY FIELD(shift, 'sore', 'malam', 'pagi', 'harian')

        LIMIT 1

    ");

    $rowExist = mysqli_fetch_assoc($qExist);



    if ($rowExist) {

        $laporanId = (int) $rowExist['id'];

        $sqlUpdate = "

            UPDATE shift_laporan

            SET setor_ke = '$setorKeEsc', tgl_setor = $tglSetorSql, created_by = $userId, shift = '$shiftEsc',

                jam_mulai = '$jamMulaiEsc', jam_selesai = '$jamSelesaiEsc'

                $ttdSql

            WHERE id = $laporanId

        ";

        try {

            shift_laporan_db_query($conn, $sqlUpdate);

        } catch (RuntimeException $e) {

            if ($ttdSql !== '' && stripos($e->getMessage(), 'packet') !== false) {

                shift_laporan_db_query($conn, "

                    UPDATE shift_laporan

                    SET setor_ke = '$setorKeEsc', tgl_setor = $tglSetorSql, created_by = $userId, shift = '$shiftEsc',

                        jam_mulai = '$jamMulaiEsc', jam_selesai = '$jamSelesaiEsc'

                    WHERE id = $laporanId

                ");

                throw new RuntimeException('Data tersimpan tanpa tanda tangan (gambar TTD terlalu besar). Kosongkan TTD atau hubungi admin.');

            }

            throw $e;

        }

        shift_laporan_db_query($conn, "DELETE FROM shift_laporan_kasir WHERE shift_laporan_id = $laporanId");

    } else {

        shift_laporan_db_query($conn, "

            INSERT INTO shift_laporan (cabang, tanggal, shift, jam_mulai, jam_selesai, setor_ke, tgl_setor, created_by)

            VALUES ($sessionCabang, '$tanggalEsc', '$shiftEsc', '$jamMulaiEsc', '$jamSelesaiEsc', '$setorKeEsc', $tglSetorSql, $userId)

        ");

        $laporanId = (int) mysqli_insert_id($conn);

        if ($laporanId < 1) {

            throw new RuntimeException('Gagal menyimpan header laporan (insert id kosong).');

        }

        if ($ttdSql !== '') {

            try {

                shift_laporan_db_query($conn, "UPDATE shift_laporan SET created_by = $userId $ttdSql WHERE id = $laporanId");

            } catch (RuntimeException $e) {

                if (stripos($e->getMessage(), 'packet') !== false) {

                    throw new RuntimeException('Laporan tersimpan tanpa tanda tangan (gambar TTD terlalu besar).');

                }

                throw $e;

            }

        }

    }



    foreach ($kasirInput as $k) {

        if (!is_array($k)) {

            continue;

        }

        $uid = (int) ($k['user_id'] ?? 0);

        if ($uid < 1) {

            continue;

        }

        $pengeluaran = max(0, (int) ($k['pengeluaran_kas'] ?? 0));

        $setoran = max(0, (int) ($k['setoran_kas'] ?? 0));

        shift_laporan_db_query($conn, "

            INSERT INTO shift_laporan_kasir (shift_laporan_id, user_id, pengeluaran_kas, setoran_kas)

            VALUES ($laporanId, $uid, $pengeluaran, $setoran)

        ");

    }



    shift_laporan_db_commit($conn);



    echo json_encode([

        'ok' => true,

        'message' => 'Laporan pergantian shift berhasil disimpan.',

        'laporan_id' => $laporanId,

        'jam' => [

            'jam_mulai' => substr($jam['jam_mulai'], 0, 5),

            'jam_selesai' => substr($jam['jam_selesai'], 0, 5),

        ],

    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    if (isset($conn) && $conn instanceof mysqli) {

        shift_laporan_db_rollback($conn);

    }

    http_response_code(500);

    echo json_encode(['ok' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);

}

