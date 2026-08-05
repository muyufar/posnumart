<?php
/**
 * Linking COA: mirror saldo akun cabang sumber → Nugrosir (cabang target, default 0) secara realtime.
 */

require_once __DIR__ . '/akun-link-lib.php';

function coa_link_mirror_ensure_table(mysqli $conn): void
{
	static $done = false;
	if ($done) {
		return;
	}
	$sql = @file_get_contents(__DIR__ . '/../db/migration_coa_link_mirror.sql');
	if ($sql !== false) {
		foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
			$stmt = trim($stmt);
			if ($stmt === '' || stripos($stmt, 'CREATE TABLE') === false) {
				continue;
			}
			@mysqli_query($conn, $stmt);
		}
	} else {
		@mysqli_query($conn, "
			CREATE TABLE IF NOT EXISTS coa_link_mirror (
				id INT NOT NULL AUTO_INCREMENT,
				kode_akun VARCHAR(50) NOT NULL,
				nama_akun VARCHAR(255) NULL,
				cabang_sumber INT NOT NULL,
				cabang_target INT NOT NULL DEFAULT 0,
				aktif TINYINT(1) NOT NULL DEFAULT 1,
				catatan VARCHAR(255) NULL,
				created_by INT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY uq_coa_link (kode_akun, cabang_sumber, cabang_target)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");
	}
	$columns = [];
	$qColumns = @mysqli_query($conn, 'SHOW COLUMNS FROM coa_link_mirror');
	while ($qColumns && ($column = mysqli_fetch_assoc($qColumns))) {
		$columns[(string) $column['Field']] = true;
	}
	if (!isset($columns['akun_sumber_id'])) {
		mysqli_query($conn, 'ALTER TABLE coa_link_mirror ADD COLUMN akun_sumber_id INT NULL AFTER nama_akun');
	}
	if (!isset($columns['akun_target_id'])) {
		mysqli_query($conn, 'ALTER TABLE coa_link_mirror ADD COLUMN akun_target_id INT NULL AFTER akun_sumber_id');
	}
	// Legacy menyimpan toko sebagai sumber. Normalisasi menjadi pusat -> follower toko.
	$qLegacy = mysqli_query($conn, 'SELECT * FROM coa_link_mirror');
	while ($qLegacy && ($link = mysqli_fetch_assoc($qLegacy))) {
		$targetBranch = (int) $link['cabang_target'];
		if ((int) $link['cabang_sumber'] > 0 && $targetBranch === 0) {
			$targetBranch = (int) $link['cabang_sumber'];
		}
		$source = akun_find_laba_kategori_row_exact($conn, (string) $link['kode_akun'], 0);
		$target = akun_find_laba_kategori_row_exact($conn, (string) $link['kode_akun'], $targetBranch);
		$isLegacyReverse = (int) $link['cabang_sumber'] > 0 && (int) $link['cabang_target'] === 0;
		// Jangan mengaktifkan arti baru secara diam-diam untuk link legacy yang dulu berarah terbalik.
		$active = (!$isLegacyReverse && $targetBranch > 0 && $source && $target) ? (int) $link['aktif'] : 0;
		$sourceId = $source ? (int) $source['id'] : 'NULL';
		$targetId = $target ? (int) $target['id'] : 'NULL';
		mysqli_query($conn, 'UPDATE coa_link_mirror SET cabang_sumber=0,cabang_target=' . $targetBranch
			. ',akun_sumber_id=' . $sourceId . ',akun_target_id=' . $targetId . ',aktif=' . $active
			. ' WHERE id=' . (int) $link['id']);
	}
	coa_link_mirror_seed_kas_tunai($conn);
	$done = true;
}

/** Seed default: kas tunai toko 1-1102..1-1105 → Nugrosir. */
function coa_link_mirror_seed_kas_tunai(mysqli $conn): void
{
	if (!function_exists('akun_link_kas_tunai_map')) {
		return;
	}
	foreach (akun_link_kas_tunai_map() as $cabangId => $info) {
		$cabangId = (int) $cabangId;
		if ($cabangId === 0) {
			continue;
		}
		$kode = (string) ($info['kode'] ?? '');
		$nama = (string) ($info['nama'] ?? '');
		if ($kode === '') {
			continue;
		}
		$kodeEsc = mysqli_real_escape_string($conn, $kode);
		$namaEsc = mysqli_real_escape_string($conn, $nama);
		$exists = mysqli_query($conn, "
			SELECT id FROM coa_link_mirror
			WHERE kode_akun = '$kodeEsc' AND cabang_sumber = 0 AND cabang_target = $cabangId
			LIMIT 1
		");
		if ($exists && mysqli_num_rows($exists) > 0) {
			continue;
		}
		$source = akun_find_laba_kategori_row_exact($conn, $kode, 0);
		$target = akun_find_laba_kategori_row_exact($conn, $kode, $cabangId);
		if ($source && $target) {
			mysqli_query($conn, "INSERT INTO coa_link_mirror (kode_akun,nama_akun,akun_sumber_id,akun_target_id,cabang_sumber,cabang_target,aktif,catatan) VALUES ('$kodeEsc','$namaEsc'," . (int) $source['id'] . ',' . (int) $target['id'] . ",0,$cabangId,0,'Legacy default: perlu link ulang oleh admin')");
		}
	}
}

function coa_link_mirror_cabang_label(mysqli $conn, int $cabang): string
{
	static $map = null;
	if ($map === null) {
		$map = [0 => 'Nugrosir / PCNU'];
		$q = mysqli_query($conn, 'SELECT toko_cabang, toko_nama, toko_kota FROM toko ORDER BY toko_cabang');
		while ($q && ($r = mysqli_fetch_assoc($q))) {
			$c = (int) ($r['toko_cabang'] ?? -1);
			$nama = trim((string) ($r['toko_nama'] ?? ''));
			$kota = trim((string) ($r['toko_kota'] ?? ''));
			$map[$c] = trim($nama . ($kota !== '' ? ' ' . $kota : '')) ?: ('Cabang ' . $c);
		}
	}
	return $map[$cabang] ?? ('Cabang ' . $cabang);
}

/**
 * @return list<array<string,mixed>>
 */
function coa_link_mirror_list_aktif(mysqli $conn): array
{
	coa_link_mirror_ensure_table($conn);
	$list = [];
	$res = mysqli_query($conn, '
		SELECT * FROM coa_link_mirror WHERE aktif = 1
		ORDER BY kode_akun ASC, cabang_sumber ASC
	');
	while ($res && ($row = mysqli_fetch_assoc($res))) {
		$list[] = $row;
	}
	return $list;
}

/**
 * Cari link aktif untuk kode akun (opsional filter cabang terlibat).
 *
 * @return list<array<string,mixed>>
 */
function coa_link_mirror_find_for_kode(mysqli $conn, string $kode, ?int $cabangTerlibat = null): array
{
	coa_link_mirror_ensure_table($conn);
	$kodeEsc = mysqli_real_escape_string($conn, $kode);
	$sql = "SELECT * FROM coa_link_mirror WHERE aktif = 1 AND kode_akun = '$kodeEsc'";
	if ($cabangTerlibat !== null) {
		$c = (int) $cabangTerlibat;
		$sql .= " AND (cabang_sumber = $c OR cabang_target = $c)";
	}
	$sql .= ' ORDER BY id ASC';
	$list = [];
	$res = mysqli_query($conn, $sql);
	while ($res && ($row = mysqli_fetch_assoc($res))) {
		$list[] = $row;
	}
	return $list;
}

/** Cabang sumber (pemilik) untuk kode, dari konfigurasi link ke Nugrosir. */
function coa_link_mirror_owner_cabang(mysqli $conn, string $kode, int $targetCabang = 0): ?int
{
	$links = coa_link_mirror_find_for_kode($conn, $kode);
	foreach ($links as $link) {
		if ((int) ($link['cabang_sumber'] ?? -1) === 0) {
			return 0;
		}
	}
	return null;
}

/**
 * Salin saldo absolut dari cabang sumber → target (buat baris target jika belum ada).
 */
function coa_link_mirror_sync_one(mysqli $conn, string $kode, int $cabangSumber, int $cabangTarget = 0, string $nama = '', int $sourceAccountId = 0, int $targetAccountId = 0): bool
{
	if (!akun_link_cabang_column_exists($conn)) {
		return false;
	}
	$kode = trim($kode);
	if ($kode === '' || $cabangSumber !== 0 || $cabangTarget <= 0) {
		return false;
	}

	$owner = $sourceAccountId > 0
		? mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM laba_kategori WHERE id=$sourceAccountId AND cabang=0 LIMIT 1"))
		: akun_find_laba_kategori_row_exact($conn, $kode, 0);
	if (!$owner || trim((string) ($owner['kode_akun'] ?? '')) !== $kode) {
		return false;
	}
	$saldoOwner = $owner ? (float) ($owner['saldo'] ?? 0) : 0.0;
	if ($nama === '') {
		$nama = $owner ? (string) ($owner['name'] ?? $kode) : $kode;
	}
	$kategori = 'aktiva';
	$tipe = 'debit';
	if ($owner) {
		$qMeta = mysqli_query($conn, 'SELECT kategori, tipe_akun, name FROM laba_kategori WHERE id = ' . (int) $owner['id'] . ' LIMIT 1');
		if ($qMeta && ($m = mysqli_fetch_assoc($qMeta))) {
			$kategori = (string) ($m['kategori'] ?? 'aktiva');
			$tipe = (string) ($m['tipe_akun'] ?? 'debit');
			if ($nama === '' || $nama === $kode) {
				$nama = (string) ($m['name'] ?? $kode);
			}
		}
	}

	$mirror = $targetAccountId > 0
		? mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM laba_kategori WHERE id=$targetAccountId AND cabang=$cabangTarget LIMIT 1"))
		: akun_find_laba_kategori_row_exact($conn, $kode, $cabangTarget);
	if ($mirror && trim((string) ($mirror['kode_akun'] ?? '')) !== $kode) {
		return false;
	}
	if ($mirror) {
		mysqli_query($conn, 'UPDATE laba_kategori SET saldo = ' . $saldoOwner . ' WHERE id = ' . (int) $mirror['id']);
		return true;
	}

	return false;
}

/** Sync semua link aktif. */
function coa_link_mirror_sync_all(mysqli $conn): array
{
	coa_link_mirror_ensure_table($conn);
	$synced = 0;
	$items = [];
	foreach (coa_link_mirror_list_aktif($conn) as $link) {
		$kode = (string) ($link['kode_akun'] ?? '');
		$src = (int) ($link['cabang_sumber'] ?? 0);
		$tgt = (int) ($link['cabang_target'] ?? 0);
		$nama = (string) ($link['nama_akun'] ?? '');
		if (coa_link_mirror_sync_one($conn, $kode, $src, $tgt, $nama, (int) ($link['akun_sumber_id'] ?? 0), (int) ($link['akun_target_id'] ?? 0))) {
			$synced++;
			$items[] = $kode . ' cab' . $src . '→' . $tgt;
		}
	}
	return ['synced' => $synced, 'items' => $items];
}

/**
 * Hook setelah saldo berubah: jaga mirror sesuai konfigurasi.
 */
function coa_link_mirror_after_saldo_change(mysqli $conn, string $kode, int $cabangTerlibat, float $perubahanSaldo = 0.0): void
{
	$kode = trim($kode);
	if ($kode === '' || !akun_link_cabang_column_exists($conn)) {
		return;
	}
	$links = coa_link_mirror_find_for_kode($conn, $kode, $cabangTerlibat);
	foreach ($links as $link) {
		if ($cabangTerlibat === (int) ($link['cabang_target'] ?? -1)) {
			coa_link_mirror_sync_one($conn, $kode, 0, (int) $link['cabang_target'], (string) ($link['nama_akun'] ?? ''), (int) ($link['akun_sumber_id'] ?? 0), (int) ($link['akun_target_id'] ?? 0));
			return; // follower tidak pernah mendorong delta ke canonical
		}
	}
	if ($links === []) {
		return;
	}

	foreach ($links as $link) {
		$src = (int) ($link['cabang_sumber'] ?? 0);
		$tgt = (int) ($link['cabang_target'] ?? 0);
		$nama = (string) ($link['nama_akun'] ?? '');

		// Perubahan di target (Nugrosir) → dorong ke sumber dulu
		if ($cabangTerlibat === $src) {
			coa_link_mirror_sync_one($conn, $kode, $src, $tgt, $nama, (int) ($link['akun_sumber_id'] ?? 0), (int) ($link['akun_target_id'] ?? 0));
		}
	}
}

function coa_link_mirror_is_follower_account(mysqli $conn, int $accountId): bool
{
	coa_link_mirror_ensure_table($conn);
	$accountId = (int) $accountId;
	$q = mysqli_query($conn, "SELECT id FROM coa_link_mirror WHERE aktif=1 AND akun_target_id=$accountId LIMIT 1");
	return $q && mysqli_num_rows($q) > 0;
}

/** @return array{ok:bool,message?:string,account_id?:int} */
function coa_link_mirror_validate_mutation_accounts(mysqli $conn, array $data): array
{
	foreach (['akun_debit', 'akun_kredit', 'kategori'] as $field) {
		$accountId = isset($data[$field]) ? (int) $data[$field] : 0;
		if ($accountId > 0 && coa_link_mirror_is_follower_account($conn, $accountId)) {
			return [
				'ok' => false,
				'account_id' => $accountId,
				'message' => 'Akun ini adalah follower COA Grosir dan tidak boleh dipakai untuk transaksi manual. Posting ke akun canonical Grosir atau putuskan link terlebih dahulu.',
			];
		}
	}
	return ['ok' => true];
}

/**
 * Ganti seluruh set link aktif ke Nugrosir (cabang 0) sesuai checklist admin.
 *
 * @param list<array{kode_akun:string,cabang_sumber:int,nama_akun?:string}> $selected
 */
function coa_link_mirror_replace_nugrosir_links(mysqli $conn, array $selected, int $userId = 0): array
{
	return [
		'ok' => false,
		'saved' => 0,
		'errors' => ['Operasi checklist lama dinonaktifkan karena arah link ambigu. Gunakan pemilihan pasangan akun Grosir dan toko.'],
		'sync' => 0,
	];
}

/**
 * Daftar kandidat akun COA per cabang (untuk checklist UI).
 *
 * @return list<array<string,mixed>>
 */
function coa_link_mirror_list_kandidat(mysqli $conn, string $search = '', int $filterCabang = -1): array
{
	coa_link_mirror_ensure_table($conn);
	if (!akun_link_cabang_column_exists($conn)) {
		return [];
	}

	$where = " WHERE kode_akun IS NOT NULL AND TRIM(kode_akun) != '' AND TRIM(kode_akun) != '-'
		AND cabang IS NOT NULL AND cabang != 0 ";
	if ($filterCabang > 0) {
		$where .= ' AND cabang = ' . (int) $filterCabang . ' ';
	}
	if ($search !== '') {
		$like = mysqli_real_escape_string($conn, str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search));
		$where .= " AND (kode_akun LIKE '%$like%' OR name LIKE '%$like%') ";
	}

	// Hindari header kosong: prioritaskan yang punya saldo atau level leaf (level >= 3 bila ada)
	$hasLevel = akun_link_column_exists($conn, 'level');
	$order = $hasLevel ? 'cabang ASC, level ASC, kode_akun ASC' : 'cabang ASC, kode_akun ASC';

	$sql = "
		SELECT id, kode_akun, name, kategori, tipe_akun, saldo, cabang"
		. ($hasLevel ? ', level, parent_id' : '') . "
		FROM laba_kategori
		$where
		ORDER BY $order
		LIMIT 3000
	";
	$res = mysqli_query($conn, $sql);
	$aktifMap = [];
	foreach (coa_link_mirror_list_aktif($conn) as $link) {
		if ((int) ($link['cabang_sumber'] ?? -1) !== 0) {
			continue;
		}
		$key = (string) $link['kode_akun'] . '|' . (int) $link['cabang_target'];
		$aktifMap[$key] = (int) $link['id'];
	}

	// Prefetch saldo Nugrosir per kode
	$nugMap = [];
	$qn = mysqli_query($conn, "
		SELECT kode_akun, saldo FROM laba_kategori
		WHERE cabang = 0 AND kode_akun IS NOT NULL AND TRIM(kode_akun) != ''
	");
	while ($qn && ($rn = mysqli_fetch_assoc($qn))) {
		$nugMap[(string) $rn['kode_akun']] = (float) ($rn['saldo'] ?? 0);
	}

	$hasil = [];
	while ($res && ($row = mysqli_fetch_assoc($res))) {
		$kode = (string) ($row['kode_akun'] ?? '');
		$cab = (int) ($row['cabang'] ?? 0);
		$key = $kode . '|' . $cab;
		$hasNug = array_key_exists($kode, $nugMap);
		$saldoToko = (float) ($row['saldo'] ?? 0);
		$saldoNug = $hasNug ? (float) $nugMap[$kode] : null;
		$hasil[] = [
			'id' => (int) $row['id'],
			'kode_akun' => $kode,
			'name' => (string) ($row['name'] ?? ''),
			'kategori' => (string) ($row['kategori'] ?? ''),
			'tipe_akun' => (string) ($row['tipe_akun'] ?? ''),
			'saldo' => $saldoToko,
			'cabang' => $cab,
			'cabang_label' => coa_link_mirror_cabang_label($conn, $cab),
			'level' => isset($row['level']) ? (int) $row['level'] : null,
			'linked' => isset($aktifMap[$key]),
			'link_id' => $aktifMap[$key] ?? null,
			'saldo_nugrosir' => $saldoNug,
			'sinkron' => $hasNug ? (abs($saldoToko - $saldoNug) < 0.005) : false,
		];
	}
	return $hasil;
}

/**
 * Daftar akun COA satu cabang (untuk panel kiri/kanan).
 *
 * @return list<array<string,mixed>>
 */
function coa_link_mirror_list_by_cabang(mysqli $conn, int $cabang, string $search = ''): array
{
	coa_link_mirror_ensure_table($conn);
	if (!akun_link_cabang_column_exists($conn)) {
		return [];
	}
	$cabang = (int) $cabang;
	$where = " WHERE cabang = $cabang
		AND kode_akun IS NOT NULL AND TRIM(kode_akun) != '' AND TRIM(kode_akun) != '-' ";
	if ($search !== '') {
		$like = mysqli_real_escape_string($conn, str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search));
		$where .= " AND (kode_akun LIKE '%$like%' OR name LIKE '%$like%') ";
	}
	$hasLevel = akun_link_column_exists($conn, 'level');
	$order = $hasLevel ? 'level ASC, kode_akun ASC, name ASC' : 'kode_akun ASC, name ASC';
	$sql = 'SELECT id, kode_akun, name, kategori, tipe_akun, saldo, cabang'
		. ($hasLevel ? ', level, parent_id' : '')
		. " FROM laba_kategori $where ORDER BY $order LIMIT 2500";

	$aktifByKode = [];
	foreach (coa_link_mirror_list_aktif($conn) as $link) {
		if ((int) ($link['cabang_sumber'] ?? -1) !== 0) {
			continue;
		}
		$k = (string) ($link['kode_akun'] ?? '');
		$aktifByKode[$k][] = $link;
	}

	$hasil = [];
	$res = mysqli_query($conn, $sql);
	while ($res && ($row = mysqli_fetch_assoc($res))) {
		$kode = (string) ($row['kode_akun'] ?? '');
		$links = $aktifByKode[$kode] ?? [];
		$linked = false;
		$linkMeta = null;
		if ($cabang === 0) {
			$linked = $links !== [];
			$linkMeta = $links[0] ?? null;
		} else {
			foreach ($links as $ln) {
				if ((int) ($ln['cabang_target'] ?? -1) === $cabang) {
					$linked = true;
					$linkMeta = $ln;
					break;
				}
			}
		}
		$hasil[] = [
			'id' => (int) $row['id'],
			'kode_akun' => $kode,
			'name' => (string) ($row['name'] ?? ''),
			'kategori' => (string) ($row['kategori'] ?? ''),
			'tipe_akun' => (string) ($row['tipe_akun'] ?? ''),
			'saldo' => (float) ($row['saldo'] ?? 0),
			'cabang' => $cabang,
			'cabang_label' => coa_link_mirror_cabang_label($conn, $cabang),
			'level' => isset($row['level']) ? (int) $row['level'] : null,
			'parent_id' => isset($row['parent_id']) ? (int) $row['parent_id'] : null,
			'linked' => $linked,
			'link_id' => $linkMeta ? (int) ($linkMeta['id'] ?? 0) : null,
			'link_sumber' => $linkMeta ? (int) ($linkMeta['cabang_sumber'] ?? 0) : null,
		];
	}
	return $hasil;
}

/** Hubungkan 1 akun toko → Nugrosir (buat mirror + aktifkan link). */
function coa_link_mirror_connect_toko_to_nugrosir(mysqli $conn, int $grosirAkunId, int $tokoAkunId, int $userId = 0): array
{
	coa_link_mirror_ensure_table($conn);
	$grosirAkunId = (int) $grosirAkunId;
	$tokoAkunId = (int) $tokoAkunId;
	$qSource = mysqli_query($conn, "SELECT * FROM laba_kategori WHERE id=$grosirAkunId LIMIT 1");
	$qTarget = mysqli_query($conn, "SELECT * FROM laba_kategori WHERE id=$tokoAkunId LIMIT 1");
	$source = $qSource ? mysqli_fetch_assoc($qSource) : null;
	$target = $qTarget ? mysqli_fetch_assoc($qTarget) : null;
	if (!$source || !$target) {
		return ['ok' => false, 'message' => 'Akun Grosir atau akun toko tidak ditemukan'];
	}
	if ((int) $source['cabang'] !== 0 || (int) $target['cabang'] <= 0) {
		return ['ok' => false, 'message' => 'Arah link wajib Grosir (cabang 0) -> toko'];
	}
	$kode = trim((string) $source['kode_akun']);
	if ($kode === '' || $kode === '-' || $kode !== trim((string) $target['kode_akun'])) {
		return ['ok' => false, 'message' => 'Kode akun Grosir dan toko wajib sama persis'];
	}
	$result = coa_link_mirror_upsert_one($conn, $kode, 0, (int) $target['cabang'], (string) $source['name'], $userId, true, $grosirAkunId, $tokoAkunId);
	return $result;
}

function coa_link_mirror_upsert_one(
	mysqli $conn,
	string $kode,
	int $cabangSumber,
	int $cabangTarget,
	string $nama,
	int $userId = 0,
	bool $sync = true,
	int $sourceAccountId = 0,
	int $targetAccountId = 0
): array {
	coa_link_mirror_ensure_table($conn);
	$kode = trim($kode);
	if ($kode === '' || $cabangSumber !== 0 || $cabangTarget <= 0) {
		return ['ok' => false, 'message' => 'Arah link wajib Grosir -> toko'];
	}
	$source = $sourceAccountId > 0 ? mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM laba_kategori WHERE id=$sourceAccountId LIMIT 1")) : akun_find_laba_kategori_row_exact($conn, $kode, 0);
	$target = $targetAccountId > 0 ? mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM laba_kategori WHERE id=$targetAccountId LIMIT 1")) : akun_find_laba_kategori_row_exact($conn, $kode, $cabangTarget);
	if (!$source || !$target || (int) $source['cabang'] !== 0 || (int) $target['cabang'] !== $cabangTarget || trim((string) $source['kode_akun']) !== $kode || trim((string) $target['kode_akun']) !== $kode) {
		return ['ok' => false, 'message' => 'Pasangan akun canonical/follower tidak valid'];
	}
	$sourceAccountId = (int) $source['id'];
	$targetAccountId = (int) $target['id'];
	$kodeEsc = mysqli_real_escape_string($conn, $kode);
	$namaEsc = mysqli_real_escape_string($conn, $nama);
	$userSql = $userId > 0 ? (string) $userId : 'NULL';
	$chk = mysqli_query($conn, "
		SELECT id FROM coa_link_mirror
		WHERE akun_target_id = $targetAccountId OR (kode_akun = '$kodeEsc' AND cabang_sumber = 0 AND cabang_target = $cabangTarget)
		LIMIT 1
	");
	if ($chk && ($row = mysqli_fetch_assoc($chk))) {
		mysqli_query($conn, "
			UPDATE coa_link_mirror SET aktif = 1, cabang_sumber=0,cabang_target=$cabangTarget,
				akun_sumber_id=$sourceAccountId,akun_target_id=$targetAccountId,
				nama_akun = IF('$namaEsc'='', nama_akun, '$namaEsc'),
				updated_at = NOW()
			WHERE id = " . (int) $row['id']
		);
		$linkId = (int) $row['id'];
	} else {
		$ok = mysqli_query($conn, "
			INSERT INTO coa_link_mirror (kode_akun,nama_akun,akun_sumber_id,akun_target_id,cabang_sumber,cabang_target,aktif,created_by)
			VALUES ('$kodeEsc','$namaEsc',$sourceAccountId,$targetAccountId,0,$cabangTarget,1,$userSql)
		");
		if (!$ok) {
			return ['ok' => false, 'message' => mysqli_error($conn)];
		}
		$linkId = (int) mysqli_insert_id($conn);
	}
	if ($sync) {
		coa_link_mirror_sync_one($conn, $kode, $cabangSumber, $cabangTarget, $nama, $sourceAccountId, $targetAccountId);
	}
	return ['ok' => true, 'message' => 'Akun toko mengikuti saldo canonical Grosir', 'link_id' => $linkId];
}

function coa_link_mirror_unlink(mysqli $conn, int $linkId): array
{
	coa_link_mirror_ensure_table($conn);
	$linkId = (int) $linkId;
	if ($linkId < 1) {
		return ['ok' => false, 'message' => 'Link tidak valid'];
	}
	$ok = mysqli_query($conn, "UPDATE coa_link_mirror SET aktif = 0, updated_at = NOW() WHERE id = $linkId");
	return ['ok' => (bool) $ok, 'message' => $ok ? 'Link dinonaktifkan' : mysqli_error($conn)];
}

function coa_link_mirror_unlink_by_kode_sumber(mysqli $conn, string $kode, int $cabangSumber, int $cabangTarget = 0): array
{
	coa_link_mirror_ensure_table($conn);
	$kodeEsc = mysqli_real_escape_string($conn, trim($kode));
	$src = (int) $cabangSumber;
	$tgt = (int) $cabangTarget;
	$ok = mysqli_query($conn, "
		UPDATE coa_link_mirror SET aktif = 0, updated_at = NOW()
		WHERE kode_akun = '$kodeEsc' AND cabang_sumber = $src AND cabang_target = $tgt
	");
	return ['ok' => (bool) $ok, 'message' => $ok ? 'Link dinonaktifkan' : mysqli_error($conn)];
}

/** Duplikat akun toko ke Nugrosir (tanpa wajib link) ATAU duplikat di cabang yang sama. */
function coa_link_mirror_duplicate_akun(mysqli $conn, int $akunId, int $targetCabang, ?string $kodeBaru = null, ?string $namaBaru = null): array
{
	$akunId = (int) $akunId;
	$targetCabang = (int) $targetCabang;
	$res = mysqli_query($conn, "SELECT * FROM laba_kategori WHERE id = $akunId LIMIT 1");
	$src = $res ? mysqli_fetch_assoc($res) : null;
	if (!$src) {
		return ['ok' => false, 'message' => 'Akun sumber tidak ditemukan'];
	}
	$kode = $kodeBaru !== null && trim($kodeBaru) !== '' ? trim($kodeBaru) : trim((string) ($src['kode_akun'] ?? ''));
	$nama = $namaBaru !== null && trim($namaBaru) !== '' ? trim($namaBaru) : (trim((string) ($src['name'] ?? '')) . ' (copy)');
	if ($kode === '') {
		return ['ok' => false, 'message' => 'Kode akun wajib diisi'];
	}
	$exist = akun_find_laba_kategori_row_exact($conn, $kode, $targetCabang);
	if ($exist) {
		return ['ok' => false, 'message' => "Kode $kode sudah ada di cabang tujuan"];
	}
	$kategori = (string) ($src['kategori'] ?? 'aktiva');
	$tipe = (string) ($src['tipe_akun'] ?? 'debit');
	$parentId = isset($src['parent_id']) ? (int) $src['parent_id'] : null;
	$level = isset($src['level']) ? (int) $src['level'] : null;
	// Parent hanya dipakai jika di cabang yang sama; kalau beda cabang, cari parent kode sama
	if ($parentId && (int) ($src['cabang'] ?? -1) !== $targetCabang) {
		$pq = mysqli_query($conn, 'SELECT kode_akun FROM laba_kategori WHERE id = ' . (int) $parentId . ' LIMIT 1');
		$prow = $pq ? mysqli_fetch_assoc($pq) : null;
		$parentKode = $prow ? trim((string) ($prow['kode_akun'] ?? '')) : '';
		$parentId = null;
		if ($parentKode !== '') {
			$pTarget = akun_find_laba_kategori_row_exact($conn, $parentKode, $targetCabang);
			$parentId = $pTarget ? (int) $pTarget['id'] : null;
		}
	}
	akun_link_ensure_akun_exists($conn, $kode, $nama, $kategori, $tipe, $targetCabang, $parentId, $level);
	$created = akun_find_laba_kategori_row_exact($conn, $kode, $targetCabang);
	if (!$created) {
		return ['ok' => false, 'message' => 'Gagal menduplikasi akun'];
	}
	// Saldo awal 0 (struktur saja); sync link yang mengatur saldo
	mysqli_query($conn, 'UPDATE laba_kategori SET saldo = 0 WHERE id = ' . (int) $created['id']);
	return [
		'ok' => true,
		'message' => 'Akun berhasil diduplikasi',
		'akun' => $created,
	];
}

function coa_link_mirror_create_akun_toko(mysqli $conn, array $data): array
{
	$cabang = (int) ($data['cabang'] ?? 0);
	$kode = trim((string) ($data['kode_akun'] ?? ''));
	$nama = trim((string) ($data['name'] ?? ''));
	$kategori = trim((string) ($data['kategori'] ?? 'aktiva'));
	$tipe = trim((string) ($data['tipe_akun'] ?? 'debit'));
	$saldo = (float) ($data['saldo'] ?? 0);
	$parentId = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null;
	$level = isset($data['level']) && $data['level'] !== '' ? (int) $data['level'] : 4;
	if ($cabang <= 0) {
		return ['ok' => false, 'message' => 'Cabang toko wajib dipilih'];
	}
	if ($kode === '' || $nama === '') {
		return ['ok' => false, 'message' => 'Kode dan nama akun wajib diisi'];
	}
	if (akun_find_laba_kategori_row_exact($conn, $kode, $cabang)) {
		return ['ok' => false, 'message' => 'Kode akun sudah ada di cabang ini'];
	}
	$kodeEsc = mysqli_real_escape_string($conn, $kode);
	$namaEsc = mysqli_real_escape_string($conn, $nama);
	$katEsc = mysqli_real_escape_string($conn, $kategori);
	$tipEsc = mysqli_real_escape_string($conn, $tipe);
	$cols = 'name, kode_akun, kategori, tipe_akun, saldo, cabang';
	$vals = "'$namaEsc', '$kodeEsc', '$katEsc', '$tipEsc', $saldo, $cabang";
	if ($parentId && akun_link_column_exists($conn, 'parent_id')) {
		$cols .= ', parent_id';
		$vals .= ', ' . (int) $parentId;
	}
	if (akun_link_column_exists($conn, 'level')) {
		$cols .= ', level';
		$vals .= ', ' . (int) $level;
	}
	$ok = mysqli_query($conn, "INSERT INTO laba_kategori ($cols) VALUES ($vals)");
	if (!$ok) {
		return ['ok' => false, 'message' => mysqli_error($conn)];
	}
	$id = (int) mysqli_insert_id($conn);
	return ['ok' => true, 'message' => 'Akun toko ditambahkan', 'id' => $id];
}

function coa_link_mirror_update_akun_toko(mysqli $conn, int $id, array $data): array
{
	$id = (int) $id;
	$res = mysqli_query($conn, "SELECT * FROM laba_kategori WHERE id = $id LIMIT 1");
	$row = $res ? mysqli_fetch_assoc($res) : null;
	if (!$row) {
		return ['ok' => false, 'message' => 'Akun tidak ditemukan'];
	}
	if ((int) ($row['cabang'] ?? 0) <= 0) {
		return ['ok' => false, 'message' => 'Hanya akun cabang toko yang bisa diedit di sini'];
	}
	if (coa_link_mirror_is_follower_account($conn, $id)) {
		return ['ok' => false, 'message' => 'Akun follower tidak dapat diedit. Putuskan link terlebih dahulu.'];
	}
	$kode = trim((string) ($data['kode_akun'] ?? $row['kode_akun']));
	$nama = trim((string) ($data['name'] ?? $row['name']));
	$kategori = trim((string) ($data['kategori'] ?? $row['kategori']));
	$tipe = trim((string) ($data['tipe_akun'] ?? $row['tipe_akun']));
	if ($kode === '' || $nama === '') {
		return ['ok' => false, 'message' => 'Kode dan nama wajib'];
	}
	$cab = (int) $row['cabang'];
	$dup = mysqli_query($conn, "
		SELECT id FROM laba_kategori
		WHERE kode_akun = '" . mysqli_real_escape_string($conn, $kode) . "'
		  AND cabang = $cab AND id != $id LIMIT 1
	");
	if ($dup && mysqli_num_rows($dup) > 0) {
		return ['ok' => false, 'message' => 'Kode akun sudah dipakai akun lain di cabang ini'];
	}
	$ok = mysqli_query($conn, "
		UPDATE laba_kategori SET
			kode_akun = '" . mysqli_real_escape_string($conn, $kode) . "',
			name = '" . mysqli_real_escape_string($conn, $nama) . "',
			kategori = '" . mysqli_real_escape_string($conn, $kategori) . "',
			tipe_akun = '" . mysqli_real_escape_string($conn, $tipe) . "'
		WHERE id = $id
	");
	if (!$ok) {
		return ['ok' => false, 'message' => mysqli_error($conn)];
	}
	// Update nama di link aktif bila kode sama
	mysqli_query($conn, "
		UPDATE coa_link_mirror SET
			nama_akun = '" . mysqli_real_escape_string($conn, $nama) . "',
			kode_akun = '" . mysqli_real_escape_string($conn, $kode) . "'
		WHERE aktif = 1 AND cabang_sumber = $cab
		  AND kode_akun = '" . mysqli_real_escape_string($conn, (string) $row['kode_akun']) . "'
	");
	return ['ok' => true, 'message' => 'Akun diperbarui'];
}

function coa_link_mirror_delete_akun_toko(mysqli $conn, int $id): array
{
	$id = (int) $id;
	$res = mysqli_query($conn, "SELECT * FROM laba_kategori WHERE id = $id LIMIT 1");
	$row = $res ? mysqli_fetch_assoc($res) : null;
	if (!$row) {
		return ['ok' => false, 'message' => 'Akun tidak ditemukan'];
	}
	if ((int) ($row['cabang'] ?? 0) <= 0) {
		return ['ok' => false, 'message' => 'Akun Nugrosir tidak boleh dihapus dari halaman ini'];
	}
	if (coa_link_mirror_is_follower_account($conn, $id)) {
		return ['ok' => false, 'message' => 'Akun follower tidak dapat dihapus. Putuskan link terlebih dahulu.'];
	}
	// Cegah hapus jika punya anak
	if (akun_link_column_exists($conn, 'parent_id')) {
		$child = mysqli_query($conn, "SELECT id FROM laba_kategori WHERE parent_id = $id LIMIT 1");
		if ($child && mysqli_num_rows($child) > 0) {
			return ['ok' => false, 'message' => 'Akun masih punya sub-akun. Hapus/pindahkan anak dulu.'];
		}
	}
	$kode = (string) ($row['kode_akun'] ?? '');
	$cab = (int) $row['cabang'];
	coa_link_mirror_unlink_by_kode_sumber($conn, $kode, $cab, 0);
	$ok = mysqli_query($conn, "DELETE FROM laba_kategori WHERE id = $id LIMIT 1");
	return ['ok' => (bool) $ok, 'message' => $ok ? 'Akun toko dihapus' : mysqli_error($conn)];
}
