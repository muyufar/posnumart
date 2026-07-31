<?php
/**
 * One-time / manual: samakan saldo kas toko di Nugrosir (cabang 0)
 * dengan saldo pemilik Numart (1-1102..1-1105).
 *
 * CLI: php tools/sync-kas-tunai-mirror-nugrosir.php
 * Web (admin): /tools/sync-kas-tunai-mirror-nugrosir.php
 */
require_once __DIR__ . '/../aksi/koneksi.php';
require_once __DIR__ . '/../aksi/akun-link-lib.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
	// Samakan pola akses halaman COA / recalculate di proyek ini
	$level = strtolower(trim((string) ($_SESSION['user_level'] ?? '')));
	$allowed = ['admin', 'super admin', 'superadmin'];
	if (!in_array($level, $allowed, true)) {
		http_response_code(403);
		header('Content-Type: text/html; charset=utf-8');
		echo 'Akses ditolak. Login sebagai <strong>admin</strong> / <strong>super admin</strong> dulu, lalu buka ulang halaman ini.';
		exit;
	}
	header('Content-Type: text/plain; charset=utf-8');
}

echo "Sinkron mirror kas tunai Nugrosir...\n";
$result = akun_sync_all_kas_tunai_mirror_nugrosir($conn);
echo 'Synced: ' . (int) ($result['synced'] ?? 0) . "\n";
echo 'Codes: ' . implode(', ', $result['codes'] ?? []) . "\n\n";

$q = mysqli_query($conn, "
	SELECT kode_akun, cabang, saldo, name
	FROM laba_kategori
	WHERE kode_akun IN ('1-1102','1-1103','1-1104','1-1105')
	ORDER BY kode_akun, cabang
");
while ($q && ($r = mysqli_fetch_assoc($q))) {
	echo sprintf(
		"%s\tcab=%s\tsaldo=%s\t%s\n",
		$r['kode_akun'],
		$r['cabang'],
		number_format((float) $r['saldo'], 0, ',', '.'),
		$r['name']
	);
}
echo "\nSelesai.\n";
