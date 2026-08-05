<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../aksi/koneksi.php';
require_once __DIR__ . '/../aksi/coa-link-mirror-lib.php';

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$level = strtolower(trim((string) ($_SESSION['user_level'] ?? '')));
if (!in_array($level, ['admin', 'super admin', 'superadmin'], true)) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'Akses ditolak']);
	exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'panels'));

function coa_link_json(array $payload, int $code = 200): void
{
	http_response_code($code);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE);
	exit;
}

function coa_link_read_json_body(): array
{
	$raw = file_get_contents('php://input');
	$data = json_decode($raw ?: '', true);
	if (!is_array($data)) {
		$data = $_POST;
	}
	return is_array($data) ? $data : [];
}

coa_link_mirror_ensure_table($conn);

if ($action === 'panels') {
	$cabangToko = (int) ($_GET['cabang_toko'] ?? 1);
	if ($cabangToko <= 0) {
		$cabangToko = 1;
	}
	$qLeft = trim((string) ($_GET['q_left'] ?? ''));
	$qRight = trim((string) ($_GET['q_right'] ?? ''));
	coa_link_json([
		'ok' => true,
		'left' => coa_link_mirror_list_by_cabang($conn, 0, $qLeft),
		'right' => coa_link_mirror_list_by_cabang($conn, $cabangToko, $qRight),
		'links' => array_values(array_filter(coa_link_mirror_list_aktif($conn), static function ($l) {
			return (int) ($l['cabang_sumber'] ?? -1) === 0;
		})),
		'cabang_toko' => $cabangToko,
	]);
}

if ($action === 'connect' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$data = coa_link_read_json_body();
	$grosirId = (int) ($data['grosir_akun_id'] ?? 0);
	$tokoId = (int) ($data['toko_akun_id'] ?? 0);
	$result = coa_link_mirror_connect_toko_to_nugrosir($conn, $grosirId, $tokoId, $userId);
	coa_link_json($result, !empty($result['ok']) ? 200 : 400);
}

if ($action === 'unlink' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$data = coa_link_read_json_body();
	if (!empty($data['link_id'])) {
		$result = coa_link_mirror_unlink($conn, (int) $data['link_id']);
	} else {
		$result = coa_link_mirror_unlink_by_kode_sumber(
			$conn,
			(string) ($data['kode_akun'] ?? ''),
			0,
			(int) ($data['cabang_target'] ?? $data['cabang_sumber'] ?? 0)
		);
	}
	coa_link_json($result, !empty($result['ok']) ? 200 : 400);
}

if ($action === 'sync') {
	$result = coa_link_mirror_sync_all($conn);
	coa_link_json(['ok' => true, 'message' => 'Sinkron ' . (int) $result['synced'] . ' akun', 'result' => $result]);
}

if ($action === 'duplicate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$data = coa_link_read_json_body();
	$akunId = (int) ($data['akun_id'] ?? 0);
	$target = isset($data['target_cabang']) ? (int) $data['target_cabang'] : 0;
	$kodeBaru = isset($data['kode_akun']) ? (string) $data['kode_akun'] : null;
	$namaBaru = isset($data['name']) ? (string) $data['name'] : null;
	$alsoLink = false; // link harus memilih dua akun existing dengan kode identik
	$result = coa_link_mirror_duplicate_akun($conn, $akunId, $target, $kodeBaru, $namaBaru);
	if (!empty($result['ok']) && $alsoLink && $target === 0) {
		$srcRes = mysqli_query($conn, 'SELECT * FROM laba_kategori WHERE id = ' . $akunId . ' LIMIT 1');
		$src = $srcRes ? mysqli_fetch_assoc($srcRes) : null;
		$srcKode = $src ? trim((string) ($src['kode_akun'] ?? '')) : '';
		$createdKode = trim((string) (($result['akun']['kode_akun'] ?? '') ?: ''));
		// Auto-link hanya jika kode Nugrosir = kode sumber toko (mirror 1:1)
		if ($src && $srcKode !== '' && $createdKode === $srcKode && (int) ($src['cabang'] ?? 0) > 0) {
			coa_link_mirror_upsert_one(
				$conn,
				$srcKode,
				(int) $src['cabang'],
				0,
				(string) ($src['name'] ?? ''),
				$userId,
				true
			);
			$result['message'] .= ' & di-link ke Nugrosir';
		}
	}
	coa_link_json($result, !empty($result['ok']) ? 200 : 400);
}

if ($action === 'create_toko' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$result = coa_link_mirror_create_akun_toko($conn, coa_link_read_json_body());
	coa_link_json($result, !empty($result['ok']) ? 200 : 400);
}

if ($action === 'update_toko' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$data = coa_link_read_json_body();
	$result = coa_link_mirror_update_akun_toko($conn, (int) ($data['id'] ?? 0), $data);
	coa_link_json($result, !empty($result['ok']) ? 200 : 400);
}

if ($action === 'delete_toko' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$data = coa_link_read_json_body();
	$result = coa_link_mirror_delete_akun_toko($conn, (int) ($data['id'] ?? 0));
	coa_link_json($result, !empty($result['ok']) ? 200 : 400);
}

coa_link_json(['ok' => false, 'message' => 'Aksi tidak dikenal'], 400);
