<?php
/**
 * Kirim pesan WA lewat Fonnte (token & nomor device dari api/no.js).
 * Hanya dipanggil dari aplikasi (session). Token tidak pernah dikirim ke browser.
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

include __DIR__ . '/../aksi/koneksi.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-fonnte-lib.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-blast-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$recipients = $input['recipients'] ?? null;
if (!is_array($recipients) || $recipients === []) {
    echo json_encode(['success' => false, 'message' => 'recipients wajib diisi']);
    exit;
}

if (count($recipients) > 300) {
    echo json_encode(['success' => false, 'message' => 'Maksimal 300 penerima per permintaan']);
    exit;
}

$delayBetween = isset($input['delay_between']) ? (string) $input['delay_between'] : '2';
$cabang = (int) ($_SESSION['user_cabang'] ?? 0);
$sentTodaySet = wa_blast_phones_sent_today_set($conn, $cabang);

$built = [];
$skippedToday = [];
foreach ($recipients as $row) {
    if (!is_array($row)) {
        continue;
    }
    $phone = isset($row['phone']) ? (string) $row['phone'] : '';
    $message = isset($row['message']) ? (string) $row['message'] : '';
    $phoneKey = wa_blast_phone_key($phone);
    if ($phoneKey !== '' && isset($sentTodaySet[$phoneKey])) {
        $skippedToday[] = [
            'phone' => $phoneKey,
            'reason' => 'Sudah dikirim pesan hari ini',
        ];
        continue;
    }
    $target = fonnte_normalize_id_phone($phone);
    if ($target === '' || trim($message) === '') {
        continue;
    }
    $built[] = ['target' => $target, 'message' => $message, 'phone_key' => $phoneKey];
}

if ($built === [] && $skippedToday !== []) {
    echo json_encode([
        'success' => false,
        'message' => 'Semua nomor terpilih sudah dikirim pesan hari ini.',
        'skipped_today' => $skippedToday,
        'skipped_today_count' => count($skippedToday),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($built === []) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada penerima valid untuk dikirim']);
    exit;
}

$payload = array_map(static function ($item) {
    return ['target' => $item['target'], 'message' => $item['message']];
}, $built);

$result = wa_fonnte_send_built($payload, $delayBetween);
echo json_encode([
    'success' => $result['success'],
    'sent_attempts' => $result['sent_attempts'],
    'chunks' => $result['chunks'],
    'fonnte_results' => $result['fonnte_results'],
    'message' => $result['message'],
    'skipped_today' => $skippedToday,
    'skipped_today_count' => count($skippedToday),
], JSON_UNESCAPED_UNICODE);
