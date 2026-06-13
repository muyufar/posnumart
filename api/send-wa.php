<?php
/**
 * Kirim pesan WA — engine mandiri NUMART (wa-engine/).
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

include __DIR__ . '/../aksi/koneksi.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-send-lib.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-blast-lib.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'wa-send-settings-lib.php';

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

$cabang = (int) ($_SESSION['user_cabang'] ?? 0);
$sendLimits = wa_send_settings_get($conn, $cabang);
$maxRecipients = (int) $sendLimits['max_contacts_per_batch'];

$intervalCheck = wa_send_settings_check_interval($conn, $cabang);
if (!$intervalCheck['allowed']) {
    echo json_encode([
        'success' => false,
        'message' => $intervalCheck['message'],
        'wait_minutes' => $intervalCheck['wait_minutes'],
        'min_interval_minutes' => (int) $sendLimits['min_interval_minutes'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$deferredCount = 0;
if (count($recipients) > $maxRecipients) {
    $deferredCount = count($recipients) - $maxRecipients;
    $recipients = array_slice($recipients, 0, $maxRecipients);
}

if (!wa_provider_configured()) {
    echo json_encode([
        'success' => false,
        'message' => wa_local_config_error_hint(),
    ]);
    exit;
}

$delayBetween = (string) ((int) ($sendLimits['delay_seconds_per_contact'] ?? 3));
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
    $target = wa_normalize_id_phone($phone);
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

$result = wa_send_built($built, $delayBetween);

if (!empty($result['success'])) {
    wa_send_settings_touch_last_send($conn, $cabang);
}

$out = [
    'success' => $result['success'],
    'sent_attempts' => $result['sent_attempts'],
    'chunks' => $result['chunks'],
    'provider' => $result['provider'] ?? 'local',
    'provider_label' => wa_provider_label(),
    'message' => $result['message'],
    'skipped_today' => $skippedToday,
    'skipped_today_count' => count($skippedToday),
    'max_contacts_per_batch' => $maxRecipients,
    'min_interval_minutes' => (int) $sendLimits['min_interval_minutes'],
    'delay_seconds_per_contact' => (int) $sendLimits['delay_seconds_per_contact'],
    'deferred_count' => $deferredCount,
];

if ($deferredCount > 0 && !empty($result['success'])) {
    $out['message'] .= ' (' . $deferredCount . ' penerima belum dikirim — kirim batch berikutnya setelah jeda ' . (int) $sendLimits['min_interval_minutes'] . ' menit.)';
}

if (isset($result['local_results'])) {
    $out['local_results'] = $result['local_results'];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
