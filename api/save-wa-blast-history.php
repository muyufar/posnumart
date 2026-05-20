<?php
/**
 * Simpan riwayat WA Blast + detail nomor penerima (anti-spam harian).
 */

header('Content-Type: application/json; charset=utf-8');
include '../aksi/koneksi.php';
require_once __DIR__ . '/wa-blast-lib.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$cabang = (int) ($_SESSION['user_cabang'] ?? 0);
$totalRecipients = (int) ($data['total_recipients'] ?? 0);
$messageTemplate = mysqli_real_escape_string($conn, (string) ($data['message_template'] ?? ''));
$blastType = mysqli_real_escape_string($conn, (string) ($data['blast_type'] ?? 'manual'));
$recipients = $data['recipients'] ?? [];

if ($userId === 0) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$query = "INSERT INTO wa_blast_history (cabang, user_id, message_template, total_recipients, blast_type) 
          VALUES ($cabang, $userId, '$messageTemplate', $totalRecipients, '$blastType')";

if (!mysqli_query($conn, $query)) {
    echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    exit;
}

$blastId = (int) mysqli_insert_id($conn);
$saved = 0;

if (is_array($recipients)) {
    foreach ($recipients as $row) {
        if (!is_array($row)) {
            continue;
        }
        $phoneRaw = (string) ($row['phone'] ?? '');
        $phoneKey = wa_blast_phone_key($phoneRaw);
        if ($phoneKey === '') {
            continue;
        }
        $customerId = (int) ($row['customer_id'] ?? $row['id'] ?? 0);
        $phoneEsc = mysqli_real_escape_string($conn, $phoneKey);
        $ins = "INSERT INTO wa_blast_recipients (blast_id, customer_id, customer_phone, status, sent_at)
                VALUES ($blastId, $customerId, '$phoneEsc', 'sent', NOW())";
        if (mysqli_query($conn, $ins)) {
            $saved++;
        }
    }
}

$sentToday = wa_blast_get_sent_today_rows($conn, $cabang);

echo json_encode([
    'success' => true,
    'message' => 'History saved',
    'blast_id' => $blastId,
    'recipients_saved' => $saved,
    'sent_today_count' => count($sentToday),
    'sent_today' => $sentToday,
], JSON_UNESCAPED_UNICODE);
