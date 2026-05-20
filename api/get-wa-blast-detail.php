<?php
/**
 * Detail satu riwayat WA blast: pesan + daftar penerima.
 */

header('Content-Type: application/json; charset=utf-8');
include '../aksi/koneksi.php';
require_once __DIR__ . '/wa-blast-lib.php';
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$blastId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$cabang = (int) ($_SESSION['user_cabang'] ?? 0);

if ($blastId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID blast tidak valid']);
    exit;
}

$blastIdEsc = $blastId;
$sql = "SELECT 
            h.id,
            h.message_template,
            h.total_recipients,
            h.blast_type,
            h.created_at,
            u.user_nama
        FROM wa_blast_history h
        JOIN user u ON h.user_id = u.user_id
        WHERE h.id = $blastIdEsc
          AND h.cabang = $cabang
        LIMIT 1";

$res = mysqli_query($conn, $sql);
$blast = $res ? mysqli_fetch_assoc($res) : null;

if (!$blast) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Riwayat blast tidak ditemukan']);
    exit;
}

$recipients = [];
$recSql = "SELECT 
              r.customer_id,
              r.customer_phone,
              r.status,
              r.sent_at,
              r.created_at,
              COALESCE(c.customer_nama, '') AS customer_nama
           FROM wa_blast_recipients r
           LEFT JOIN customer c ON c.customer_id = r.customer_id
           WHERE r.blast_id = $blastIdEsc
           ORDER BY COALESCE(r.sent_at, r.created_at) ASC, r.id ASC";

$recRes = mysqli_query($conn, $recSql);
if ($recRes) {
    while ($row = mysqli_fetch_assoc($recRes)) {
        $phoneKey = wa_blast_phone_key($row['customer_phone'] ?? '');
        $recipients[] = [
            'customer_id' => (int) $row['customer_id'],
            'customer_phone' => $phoneKey !== '' ? $phoneKey : (string) $row['customer_phone'],
            'customer_nama' => (string) $row['customer_nama'],
            'status' => (string) $row['status'],
            'sent_at' => $row['sent_at'] ? (string) $row['sent_at'] : null,
            'created_at' => (string) $row['created_at'],
        ];
    }
}

echo json_encode([
    'success' => true,
    'blast' => [
        'id' => (int) $blast['id'],
        'message_template' => (string) $blast['message_template'],
        'total_recipients' => (int) $blast['total_recipients'],
        'blast_type' => (string) $blast['blast_type'],
        'created_at' => (string) $blast['created_at'],
        'user_nama' => (string) $blast['user_nama'],
        'recipient_rows' => count($recipients),
    ],
    'recipients' => $recipients,
], JSON_UNESCAPED_UNICODE);
