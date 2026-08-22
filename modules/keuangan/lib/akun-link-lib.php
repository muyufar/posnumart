<?php
/**
 * Pemetaan akun sesuai chart akuntansi Numart.
 * Kas tunai per cabang (1-1101 s/d 1-1105).
 * Bank BRI operasional: kode 1-1202 per cabang (induk L3 = 1-1200 KAS BANK).
 * PCNU (cabang 0) boleh punya sub rekening fisik tambahan (1-1201 BNU, 1-1203 Koperasi, 1-1204 Gaji).
 * Piutang 1-1301, hutang 2-1101.
 */

function akun_link_cabang_column_exists($conn)
{
	static $exists = null;
	if ($exists !== null) {
		return $exists;
	}
	$exists = akun_link_column_exists($conn, 'cabang');
	return $exists;
}

function akun_link_column_exists($conn, $column)
{
	static $cache = [];
	$column = preg_replace('/[^a-z0-9_]/', '', (string) $column);
	if ($column === '') {
		return false;
	}
	if (array_key_exists($column, $cache)) {
		return $cache[$column];
	}
	$chk = mysqli_query($conn, "SHOW COLUMNS FROM laba_kategori LIKE '$column'");
	$cache[$column] = ($chk && mysqli_num_rows($chk) > 0);
	return $cache[$column];
}

/** @return array<int, array{kode: string, nama: string}> */
function akun_link_kas_tunai_map()
{
	return [
		0 => ['kode' => '1-1101', 'nama' => 'Kas Tunai Nugrosir'],
		1 => ['kode' => '1-1102', 'nama' => 'Kas Tunai Dukun'],
		3 => ['kode' => '1-1103', 'nama' => 'Kas Tunai Srumbung'],
		2 => ['kode' => '1-1104', 'nama' => 'Kas Tunai Pakis'],
		5 => ['kode' => '1-1105', 'nama' => 'Kas Tunai Tegalrejo'],
	];
}

function akun_kas_tunai_kode($cabang)
{
	$cabang = (int) $cabang;
	$map = akun_link_kas_tunai_map();
	return $map[$cabang]['kode'] ?? $map[0]['kode'];
}

function akun_kas_tunai_nama($cabang)
{
	$cabang = (int) $cabang;
	$map = akun_link_kas_tunai_map();
	return $map[$cabang]['nama'] ?? $map[0]['nama'];
}

/** @return array<int, array{kode: string, nama: string}> */
function akun_link_persediaan_map()
{
	return [
		0 => ['kode' => '1-1501', 'nama' => 'Persediaan Barang Gudang NU Grosir'],
		1 => ['kode' => '1-1502', 'nama' => 'Persediaan Barang NU Mart Dukun'],
		3 => ['kode' => '1-1503', 'nama' => 'Persediaan Barang NU Tren PP Srumbung'],
		2 => ['kode' => '1-1504', 'nama' => 'Persediaan Barang NU Tren PP Pakis'],
		5 => ['kode' => '1-1505', 'nama' => 'Persediaan Barang NU Mart Tegalrejo'],
	];
}

function akun_persediaan_head_kode()
{
	return '1-1500';
}

function akun_persediaan_kode($cabang)
{
	$cabang = akun_link_normalize_cabang_transaksi((int) $cabang);
	$map = akun_link_persediaan_map();
	return $map[$cabang]['kode'] ?? akun_persediaan_head_kode();
}

function akun_persediaan_nama($cabang)
{
	$cabang = akun_link_normalize_cabang_transaksi((int) $cabang);
	$map = akun_link_persediaan_map();
	return $map[$cabang]['nama'] ?? 'Persediaan Barang Dagangan';
}

/**
 * Cabang pemilik resmi untuk kode kas tunai toko (mirror Nugrosir = cabang 0).
 * 1-1101 milik Nugrosir sendiri — tidak di-mirror.
 *
 * @return int|null
 */
function akun_kas_tunai_pemilik_cabang($kode)
{
	$kode = (string) $kode;
	static $reverse = null;
	if ($reverse === null) {
		$reverse = [];
		foreach (akun_link_kas_tunai_map() as $cabangId => $info) {
			$k = (string) ($info['kode'] ?? '');
			if ($k !== '') {
				$reverse[$k] = (int) $cabangId;
			}
		}
	}
	if (!isset($reverse[$kode])) {
		return null;
	}
	$owner = $reverse[$kode];
	// Nugrosir sendiri tidak perlu mirror
	if ($owner === 0) {
		return null;
	}
	return $owner;
}

/** Apakah kode termasuk kas tunai toko yang wajib di-mirror ke Nugrosir. */
function akun_kas_tunai_perlu_mirror_nugrosir($kode)
{
	return akun_kas_tunai_pemilik_cabang($kode) !== null;
}

/**
 * Salin saldo absolut kas toko (pemilik Numart) → baris mirror di Nugrosir (cabang 0).
 * Baris toko tidak dihapus; hanya disamakan nilainya di pusat.
 */
function akun_sync_kas_tunai_mirror_nugrosir($conn, $kode_akun)
{
	$kode_akun = (string) $kode_akun;
	$ownerCabang = akun_kas_tunai_pemilik_cabang($kode_akun);
	if ($ownerCabang === null || !akun_link_cabang_column_exists($conn)) {
		return false;
	}

	$ownerRow = akun_find_laba_kategori_row_exact($conn, $kode_akun, $ownerCabang);
	$saldoOwner = $ownerRow ? (float) ($ownerRow['saldo'] ?? 0) : 0.0;
	$nama = akun_kas_tunai_nama($ownerCabang);

	$mirror = akun_find_laba_kategori_row_exact($conn, $kode_akun, 0);
	if ($mirror) {
		mysqli_query($conn, 'UPDATE laba_kategori SET saldo = ' . $saldoOwner
			. ", name = '" . mysqli_real_escape_string($conn, $nama) . "'"
			. ' WHERE id = ' . (int) $mirror['id']);
		return true;
	}

	akun_link_ensure_akun_exists($conn, $kode_akun, $nama, 'aktiva', 'debit', 0);
	$mirror = akun_find_laba_kategori_row_exact($conn, $kode_akun, 0);
	if ($mirror) {
		mysqli_query($conn, 'UPDATE laba_kategori SET saldo = ' . $saldoOwner . ' WHERE id = ' . (int) $mirror['id']);
		return true;
	}
	return false;
}

/**
 * Sinkronkan semua kas toko 1-1102..1-1105 ke mirror Nugrosir.
 *
 * @return array{synced:int, codes:list<string>}
 */
function akun_sync_all_kas_tunai_mirror_nugrosir($conn)
{
	$synced = 0;
	$codes = [];
	foreach (akun_link_kas_tunai_map() as $cabangId => $info) {
		if ((int) $cabangId === 0) {
			continue;
		}
		$kode = (string) ($info['kode'] ?? '');
		if ($kode === '') {
			continue;
		}
		// Pastikan baris pemilik ada
		akun_link_ensure_akun_exists($conn, $kode, $info['nama'], 'aktiva', 'debit', (int) $cabangId);
		if (akun_sync_kas_tunai_mirror_nugrosir($conn, $kode)) {
			$synced++;
			$codes[] = $kode;
		}
	}
	return ['synced' => $synced, 'codes' => $codes];
}

/**
 * Setelah saldo diubah by ID (Data Operasional dll).
 */
function akun_link_after_saldo_update_by_id($conn, $akunId, $perubahanSaldo = 0.0)
{
	$akunId = (int) $akunId;
	if ($akunId < 1 || !akun_link_cabang_column_exists($conn)) {
		return;
	}
	$res = mysqli_query($conn, "SELECT id, kode_akun, cabang, saldo, name FROM laba_kategori WHERE id = $akunId LIMIT 1");
	$row = $res ? mysqli_fetch_assoc($res) : null;
	if (!$row) {
		return;
	}
	$kode = (string) ($row['kode_akun'] ?? '');
	$cabang = (int) ($row['cabang'] ?? 0);
	if (akun_kas_tunai_pemilik_cabang($kode) !== null) {
		akun_sync_kas_tunai_mirror_nugrosir($conn, $kode);
		return;
	}
	$lib = __DIR__ . '/coa-link-mirror-lib.php';
	if (is_file($lib)) {
		require_once $lib;
		if (function_exists('coa_link_mirror_after_saldo_change')) {
			coa_link_mirror_after_saldo_change($conn, $kode, $cabang, (float) $perubahanSaldo);
			return;
		}
	}
	// Fallback lama (kas tunai hardcode)
	$ownerCabang = akun_kas_tunai_pemilik_cabang($kode);
	if ($ownerCabang === null) {
		return;
	}
	if ($cabang === 0 && (float) $perubahanSaldo != 0.0) {
		$owner = akun_find_laba_kategori_row_exact($conn, $kode, $ownerCabang);
		if ($owner) {
			mysqli_query($conn, 'UPDATE laba_kategori SET saldo = '
				. ((float) ($owner['saldo'] ?? 0) + (float) $perubahanSaldo)
				. ' WHERE id = ' . (int) $owner['id']);
		}
	}
	akun_sync_kas_tunai_mirror_nugrosir($conn, $kode);
}

/** @return array<int, array{kode: string, nama: string}> */
function akun_link_kas_bank_bri_map()
{
	return [
		0 => ['kode' => '1-1202', 'nama' => 'Kas Bank BRI R Transaksi 025101001953566'],
		1 => ['kode' => '1-1202', 'nama' => 'Kas Bank BRI 0251 Dukun'],
		3 => ['kode' => '1-1202', 'nama' => 'Kas Bank BRI 0251 Srumbung'],
		2 => ['kode' => '1-1202', 'nama' => 'Kas Bank BRI 0251 Pakis'],
		5 => ['kode' => '1-1202', 'nama' => 'Kas Bank BRI 0251 Tegalrejo'],
	];
}

/** Cabang toko operasional (selain pusat PCNU). */
function akun_link_cabang_toko_list()
{
	return [1, 2, 3, 5];
}

/**
 * Kode BRI lama (skema 1-1203..1-1206 per cabang) → cabang pemilik.
 * Hanya dipakai saat migrasi/normalisasi.
 */
function akun_link_legacy_bri_kode_to_cabang($kode)
{
	static $legacy = [
		'1-1203' => 1,
		'1-1204' => 3,
		'1-1205' => 2,
		'1-1206' => 5,
	];
	return $legacy[(string) $kode] ?? null;
}

function akun_kas_bank_bri_kode($cabang = 0)
{
	$cabang = (int) $cabang;
	$map = akun_link_kas_bank_bri_map();
	return $map[$cabang]['kode'] ?? $map[0]['kode'];
}

function akun_kas_bank_bri_nama($cabang = 0)
{
	$cabang = (int) $cabang;
	$map = akun_link_kas_bank_bri_map();
	return $map[$cabang]['nama'] ?? $map[0]['nama'];
}

/** Cabang pemilik baris akun BRI (sama dengan cabang transaksi). */
function akun_kas_bank_bri_cabang($cabang)
{
	return (int) $cabang;
}

/** @deprecated Gunakan akun_kas_bank_bri_cabang($cabang). */
function akun_kas_bank_cabang($cabang = 0)
{
	return akun_kas_bank_bri_cabang($cabang);
}

function akun_is_kas_bank_bri_kode($kode)
{
	$kode = (string) $kode;
	if (in_array($kode, ['1-1152', '1-1153', '1-1202'], true)) {
		return true;
	}
	// Kode lama per cabang (1-1203..1-1206) — akan dinormalisasi ke 1-1202
	return akun_link_legacy_bri_kode_to_cabang($kode) !== null;
}

/**
 * @param int|null $cabangTransaksi Wajib disertakan jika kode = 1-1202 (sama di semua cabang).
 */
function akun_cabang_dari_kode_bank_bri($kode, $cabangTransaksi = null)
{
	$kode = (string) $kode;
	if ($kode === '1-1202') {
		return $cabangTransaksi !== null ? (int) $cabangTransaksi : null;
	}
	$legacy = akun_link_legacy_bri_kode_to_cabang($kode);
	if ($legacy !== null) {
		return $legacy;
	}
	return null;
}

function akun_sql_kas_bank_bri_kode_list()
{
	return ['1-1202', '1-1152', '1-1153', '1-1203', '1-1204', '1-1205', '1-1206'];
}

/**
 * Tentukan baris BRI yang benar untuk cabang (hindari fallback ke akun legacy cabang 0).
 *
 * @return array{kode: string, nama: string, cabang: int}
 */
function akun_link_resolve_bri_posting_target($conn, $cabang)
{
	$cabang = (int) $cabang;
	$mapKode = akun_kas_bank_bri_kode($cabang);
	$mapNama = akun_kas_bank_bri_nama($cabang);

	if (akun_find_laba_kategori_row_exact($conn, $mapKode, $cabang)) {
		return [
			'kode' => $mapKode,
			'nama' => $mapNama,
			'cabang' => $cabang,
		];
	}

	// Nugrosir legacy: rekening transaksi 1-1202 (bukan 1-1200 header / 1-1204 gaji)
	if ($cabang === 0) {
		$row1202 = akun_find_laba_kategori_row_exact($conn, '1-1202', 0);
		if ($row1202) {
			return [
				'kode' => '1-1202',
				'nama' => (string) ($row1202['name'] ?? $mapNama),
				'cabang' => 0,
			];
		}
	}

	return [
		'kode' => $mapKode,
		'nama' => $mapNama,
		'cabang' => $cabang,
	];
}

/**
 * Rekening BRI operasional untuk setor toko & penjualan QRIS/TF dari toko → Nugrosir (cab. 0).
 */
function akun_bri_cabang_konsolidasi_toko(int $cabangTransaksi): int
{
	return (int) $cabangTransaksi > 0 ? 0 : (int) $cabangTransaksi;
}

/**
 * Update saldo rekening BRI cabang yang diminta (biasanya cab. 0 untuk konsolidasi toko).
 */
function akun_update_saldo_bank_bri($conn, $cabang, $delta)
{
	$cabang = (int) $cabang;
	$delta = (float) $delta;
	if ($delta == 0.0) {
		return;
	}

	$target = akun_link_resolve_bri_posting_target($conn, $cabang);
	akun_update_saldo_delta(
		$conn,
		$target['kode'],
		$target['nama'],
		'aktiva',
		'debit',
		$delta,
		$target['cabang']
	);
}

function akun_update_saldo_pembayaran($conn, $cabang, $kodeBayar, $delta)
{
	$cabang = (int) $cabang;
	$delta = (float) $delta;
	if ($delta == 0.0) {
		return;
	}
	if (akun_is_kas_bank_bri_kode($kodeBayar)) {
		$cbBri = akun_cabang_dari_kode_bank_bri($kodeBayar, $cabang);
		akun_update_saldo_bank_bri($conn, $cbBri !== null ? $cbBri : $cabang, $delta);
		return;
	}
	akun_update_saldo_delta(
		$conn,
		$kodeBayar,
		akun_nama_pembayaran_dari_kode($kodeBayar, $cabang),
		'aktiva',
		'debit',
		$delta,
		$cabang
	);
}

/**
 * Setoran kas tunai ke BRI saat pergantian shift (manual di lapangan).
 */
function akun_posting_setoran_shift_ke_bank_bri($conn, $cabang, $nominal)
{
	$nominal = (float) $nominal;
	if ($nominal <= 0) {
		return;
	}
	$cabang = (int) $cabang;
	akun_update_saldo_delta(
		$conn,
		akun_kas_tunai_kode($cabang),
		akun_kas_tunai_nama($cabang),
		'aktiva',
		'debit',
		-$nominal,
		$cabang
	);
	akun_update_saldo_bank_bri($conn, $cabang, $nominal);
}

function akun_posting_batal_setoran_shift_dari_bank_bri($conn, $cabang, $nominal)
{
	$nominal = (float) $nominal;
	if ($nominal <= 0) {
		return;
	}
	$cabang = (int) $cabang;
	akun_update_saldo_delta(
		$conn,
		akun_kas_tunai_kode($cabang),
		akun_kas_tunai_nama($cabang),
		'aktiva',
		'debit',
		$nominal,
		$cabang
	);
	akun_update_saldo_bank_bri($conn, $cabang, -$nominal);
}

function akun_piutang_kode()
{
	return '1-1301';
}

function akun_hutang_kode()
{
	return '2-1101';
}

/** Kode lama → kode baru (untuk lookup saldo). */
function akun_kode_lookup_variants($kode)
{
	$kode = (string) $kode;
	$variants = [$kode];
	$legacy = [
		'1-1101' => ['1-1100'],
		'1-1102' => ['1-1100'],
		'1-1103' => ['1-1100'],
		'1-1104' => ['1-1100'],
		'1-1105' => ['1-1100'],
		'1-1202' => ['1-1152', '1-1153', '1-1203', '1-1204', '1-1205', '1-1206'],
		'1-1301' => ['1-1300'],
		'2-1101' => ['2-1100'],
	];
	if (isset($legacy[$kode])) {
		$variants = array_merge($variants, $legacy[$kode]);
	}
	return array_values(array_unique($variants));
}

function akun_find_laba_kategori_row($conn, $kode_akun, $cabang)
{
	$cabang = (int) $cabang;
	$cabangExists = akun_link_cabang_column_exists($conn);
	$variants = akun_kode_lookup_variants($kode_akun);
	foreach ($variants as $tryKode) {
		$tryEsc = mysqli_real_escape_string($conn, $tryKode);
		if ($cabangExists) {
			$q = "SELECT id, saldo, kode_akun, cabang FROM laba_kategori
				  WHERE kode_akun = '$tryEsc' AND cabang = $cabang LIMIT 1";
			$r = mysqli_query($conn, $q);
			if ($r && ($row = mysqli_fetch_assoc($r))) {
				return $row;
			}
			$q = "SELECT id, saldo, kode_akun, cabang FROM laba_kategori
				  WHERE kode_akun = '$tryEsc' AND (cabang = 0 OR cabang IS NULL)
				  ORDER BY cabang DESC LIMIT 1";
			$r = mysqli_query($conn, $q);
			if ($r && ($row = mysqli_fetch_assoc($r))) {
				return $row;
			}
		} else {
			$q = "SELECT id, saldo, kode_akun FROM laba_kategori WHERE kode_akun = '$tryEsc' LIMIT 1";
			$r = mysqli_query($conn, $q);
			if ($r && ($row = mysqli_fetch_assoc($r))) {
				return $row;
			}
		}
	}
	return null;
}

/** Lookup akun hanya di cabang yang diminta (tanpa fallback cabang 0). */
function akun_find_laba_kategori_row_exact($conn, $kode_akun, $cabang)
{
	$cabang = (int) $cabang;
	$kodeEsc = mysqli_real_escape_string($conn, (string) $kode_akun);
	if (akun_link_cabang_column_exists($conn)) {
		$q = "SELECT id, saldo, kode_akun, cabang, parent_id, level FROM laba_kategori
			  WHERE kode_akun = '$kodeEsc' AND cabang = $cabang LIMIT 1";
	} else {
		$q = "SELECT id, saldo, kode_akun, parent_id, level FROM laba_kategori
			  WHERE kode_akun = '$kodeEsc' LIMIT 1";
	}
	$r = mysqli_query($conn, $q);
	if ($r && ($row = mysqli_fetch_assoc($r))) {
		return $row;
	}
	return null;
}

function akun_link_find_parent_kas_di_bank($conn, $cabang)
{
	$cabang = (int) $cabang;
	$row = akun_find_laba_kategori_row_exact($conn, '1-1200', $cabang);
	if ($row) {
		return $row;
	}
	$row = akun_find_laba_kategori_row_exact($conn, '1-1150', $cabang);
	if ($row) {
		return $row;
	}
	if (!akun_link_cabang_column_exists($conn)) {
		return null;
	}
	$q = mysqli_query($conn, "SELECT id, level, parent_id, kode_akun, name FROM laba_kategori
		WHERE cabang = $cabang AND kategori = 'aktiva'
		AND (kode_akun = '1-1150' OR name LIKE '%Kas di Bank%' OR name LIKE '%Bank BRI%')
		ORDER BY level DESC, id ASC LIMIT 1");
	if ($q && ($row = mysqli_fetch_assoc($q))) {
		return $row;
	}
	return null;
}

function akun_link_update_hierarchy($conn, $accountId, $parentId, $level)
{
	$accountId = (int) $accountId;
	$parentId = (int) $parentId;
	$level = (int) $level;
	if ($accountId < 1 || !akun_link_column_exists($conn, 'parent_id') || !akun_link_column_exists($conn, 'level')) {
		return;
	}
	if ($parentId > 0) {
		mysqli_query($conn, "UPDATE laba_kategori SET parent_id = $parentId, level = $level WHERE id = $accountId");
		return;
	}
	mysqli_query($conn, "UPDATE laba_kategori SET parent_id = NULL, level = $level WHERE id = $accountId");
}

function akun_update_saldo_delta($conn, $kode_akun, $nama, $kategori, $tipe_akun, $delta, $cabang)
{
	$delta = (float) $delta;
	if ($delta == 0.0) {
		return;
	}
	$cabangExists = akun_link_cabang_column_exists($conn);
	$kode_akun = (string) $kode_akun;
	$cabang = (int) $cabang;
	$cabangDiminta = $cabang;

	// Kas tunai toko: pemilik = cabang toko; baris Nugrosir (cab.0) hanya tampilan mirror.
	$kasOwnerCabang = akun_kas_tunai_pemilik_cabang($kode_akun);
	if ($kasOwnerCabang !== null) {
		if ($cabang === 0) {
			$cabang = (int) $kasOwnerCabang;
		}
		$nama = akun_kas_tunai_nama($kasOwnerCabang);
	} else {
		$cabangDiminta = $cabang;
	}

	// Link COA canonical (Grosir→follower) — bukan kas tunai.
	$ownerCanonical = null;
	$libMirror = __DIR__ . '/coa-link-mirror-lib.php';
	if ($kasOwnerCabang === null && is_file($libMirror)) {
		require_once $libMirror;
		$requestedAccount = akun_find_laba_kategori_row_exact($conn, $kode_akun, $cabangDiminta);
		if ($requestedAccount && function_exists('coa_link_mirror_is_follower_account')
			&& coa_link_mirror_is_follower_account($conn, (int) $requestedAccount['id'])) {
			throw new RuntimeException('Akun follower COA Grosir tidak boleh menerima transaksi manual; gunakan akun canonical Grosir.');
		}
		if (function_exists('coa_link_mirror_owner_cabang')) {
			$ownerCanonical = coa_link_mirror_owner_cabang($conn, $kode_akun, 0);
		}
	}
	if ($ownerCanonical !== null) {
		$cabang = (int) $ownerCanonical;
	}

	// Kas/bank: exact cabang dulu agar 1-1204 gaji (cabang 0) tidak kena posting cabang lain
	if (akun_is_kas_bank_bri_kode($kode_akun) || akun_is_kas_tunai_kode($kode_akun)) {
		$row = akun_find_laba_kategori_row_exact($conn, $kode_akun, $cabang);
		if (!$row && $kode_akun === '1-1100') {
			$row = akun_find_laba_kategori_row($conn, $kode_akun, $cabang);
		}
	} else {
		$row = akun_find_laba_kategori_row($conn, $kode_akun, $cabang);
	}
	if ($row) {
		$saldoBaru = (float) ($row['saldo'] ?? 0) + $delta;
		mysqli_query($conn, 'UPDATE laba_kategori SET saldo = ' . $saldoBaru . ' WHERE id = ' . (int) $row['id']);
		if ($kasOwnerCabang !== null) {
			akun_sync_kas_tunai_mirror_nugrosir($conn, $kode_akun);
		} elseif ($ownerCanonical !== null && function_exists('coa_link_mirror_after_saldo_change')) {
			coa_link_mirror_after_saldo_change($conn, $kode_akun, (int) $ownerCanonical, 0.0);
		}
		return;
	}
	$kodeEsc = mysqli_real_escape_string($conn, $kode_akun);
	$namaEsc = mysqli_real_escape_string($conn, $nama);
	$katEsc = mysqli_real_escape_string($conn, $kategori);
	$tipEsc = mysqli_real_escape_string($conn, $tipe_akun);
	if ($cabangExists) {
		mysqli_query($conn, "INSERT INTO laba_kategori (name, kode_akun, kategori, tipe_akun, saldo, cabang)
			VALUES ('$namaEsc', '$kodeEsc', '$katEsc', '$tipEsc', $delta, " . (int) $cabang . ')');
	} else {
		mysqli_query($conn, "INSERT INTO laba_kategori (name, kode_akun, kategori, tipe_akun, saldo)
			VALUES ('$namaEsc', '$kodeEsc', '$katEsc', '$tipEsc', $delta)");
	}
	if ($kasOwnerCabang !== null) {
		akun_sync_kas_tunai_mirror_nugrosir($conn, $kode_akun);
	} elseif ($ownerCanonical !== null && function_exists('coa_link_mirror_after_saldo_change')) {
		coa_link_mirror_after_saldo_change($conn, $kode_akun, (int) $ownerCanonical, 0.0);
	}
}

function akun_kode_pembayaran_dari_tipe($tipe_pembayaran, $cabang)
{
	$tipe = (int) $tipe_pembayaran;
	if ($tipe === 0) {
		return akun_kas_tunai_kode($cabang);
	}
	return akun_kas_bank_bri_kode($cabang);
}

function akun_nama_pembayaran_dari_kode($kode, $cabang)
{
	if (akun_is_kas_bank_bri_kode($kode)) {
		$cbBri = akun_cabang_dari_kode_bank_bri($kode, (int) $cabang);
		return akun_kas_bank_bri_nama($cbBri !== null ? $cbBri : (int) $cabang);
	}
	return akun_kas_tunai_nama($cabang);
}

/**
 * Posting saldo akun setelah penjualan disimpan.
 */
function akun_posting_setelah_penjualan($conn, $cabang, $piutang, $tipeTransaksi, $subTotal, $piutangDp = 0)
{
	$cabang = (int) $cabang;
	$piutang = (int) $piutang;
	$tipeTransaksi = (int) $tipeTransaksi;
	$subTotal = (float) $subTotal;
	$piutangDp = (float) $piutangDp;

	if ($piutang === 1) {
		$sisaPiutang = max(0.0, $subTotal - $piutangDp);
		if ($sisaPiutang > 0) {
			// Piutang dagang tercatat di akun pusat (cabang 0), termasuk penjualan piutang cabang lain
			akun_update_saldo_delta(
				$conn,
				akun_piutang_kode(),
				'Piutang Dagang',
				'aktiva',
				'debit',
				$sisaPiutang,
				0
			);
		}
		if ($piutangDp > 0) {
			akun_update_saldo_delta(
				$conn,
				akun_kas_tunai_kode($cabang),
				akun_kas_tunai_nama($cabang),
				'aktiva',
				'debit',
				$piutangDp,
				$cabang
			);
		}
		return;
	}

	if ($tipeTransaksi === 0) {
		akun_update_saldo_delta(
			$conn,
			akun_kas_tunai_kode($cabang),
			akun_kas_tunai_nama($cabang),
			'aktiva',
			'debit',
			$subTotal,
			$cabang
		);
		return;
	}

	// QRIS / Transfer → rekening BRI Nugrosir (566) untuk penjualan toko
	$briCabang = akun_bri_cabang_konsolidasi_toko($cabang);
	akun_update_saldo_bank_bri($conn, $briCabang, $subTotal);
}

/**
 * Posting saldo akun setelah pembelian disimpan.
 * Hutang → 2-1101 (sisa). Tunai/lunas → kurangi kas tunai cabang
 * (1-1101 Nugrosir … 1-1105 Tegalrejo).
 */
function akun_posting_setelah_pembelian($conn, $cabang, $hutang, $total, $hutangDp = 0)
{
	$cabang = akun_link_normalize_cabang_transaksi($cabang);
	$hutang = (bool) $hutang;
	$total = (float) $total;
	$hutangDp = (float) $hutangDp;

	if ($hutang) {
		$sisaHutang = max(0.0, $total - $hutangDp);
		if ($sisaHutang > 0) {
			akun_update_saldo_delta(
				$conn,
				akun_hutang_kode(),
				'Hutang Dagang',
				'pasiva',
				'kredit',
				$sisaHutang,
				$cabang
			);
		}
		// Pembayaran DP/pelunasan → lewat cicilan hutang (tipe kas/bank), bukan otomatis di sini
		return;
	}

	if ($total <= 0) {
		return;
	}

	// Pembelian tunai/lunas: langsung kurangi kas tunai cabang (Yakult & pembelian kas toko).
	akun_update_saldo_delta(
		$conn,
		akun_kas_tunai_kode($cabang),
		akun_kas_tunai_nama($cabang),
		'aktiva',
		'debit',
		-$total,
		$cabang
	);
}

/**
 * Batalkan posting pembelian (saat invoice dihapus).
 */
function akun_posting_batal_pembelian($conn, $cabang, $hutang, $total, $hutangDp = 0)
{
	$cabang = akun_link_normalize_cabang_transaksi($cabang);
	$hutang = (bool) $hutang;
	$total = (float) $total;
	$hutangDp = (float) $hutangDp;

	if ($hutang) {
		$sisaHutang = max(0.0, $total - $hutangDp);
		if ($sisaHutang > 0) {
			akun_update_saldo_delta(
				$conn,
				akun_hutang_kode(),
				'Hutang Dagang',
				'pasiva',
				'kredit',
				-$sisaHutang,
				$cabang
			);
		}
		return;
	}

	if ($total <= 0) {
		return;
	}

	akun_update_saldo_delta(
		$conn,
		akun_kas_tunai_kode($cabang),
		akun_kas_tunai_nama($cabang),
		'aktiva',
		'debit',
		$total,
		$cabang
	);
}

/**
 * Normalisasi id cabang transaksi saat hitung ulang COA.
 * Cabang 4 = BAQNU (nonaktif); penjualan legacy sering salah id — arahkan ke Tegalrejo (5).
 */
function akun_link_normalize_cabang_transaksi($cabang)
{
	$cabang = (int) $cabang;
	if ($cabang === 4) {
		return 5;
	}
	return $cabang;
}

/** Apakah kode akun hutang/piutang dagang (termasuk legacy). */
function akun_link_is_hutang_kode($kode)
{
	$kode = (string) $kode;
	return $kode === akun_hutang_kode() || $kode === '2-1100';
}

function akun_link_is_piutang_kode($kode)
{
	$kode = (string) $kode;
	return $kode === akun_piutang_kode() || $kode === '1-1300';
}

/**
 * Posting pembelian saat hitung ulang saldo (sama dengan live POS).
 * Hutang → 2-1101; lunas/tunai → kurangi kas tunai cabang (1-1101 s/d 1-1105).
 */
function akun_posting_pembelian_saat_recalculate($conn, $cabang, $hutang, $total, $hutangDp = 0)
{
	akun_posting_setelah_pembelian($conn, $cabang, $hutang, $total, $hutangDp);
}

function akun_posting_pelunasan_piutang($conn, $cabang, $nominal, $tipePembayaran)
{
	$nominal = (float) $nominal;
	if ($nominal <= 0) {
		return;
	}
	$cabang = (int) $cabang;
	akun_update_saldo_delta($conn, akun_piutang_kode(), 'Piutang Dagang', 'aktiva', 'debit', -$nominal, 0);
	$kodeBayar = akun_kode_pembayaran_dari_tipe($tipePembayaran, $cabang);
	akun_update_saldo_pembayaran($conn, $cabang, $kodeBayar, $nominal);
}

function akun_posting_pelunasan_hutang($conn, $cabang, $nominal, $tipePembayaran)
{
	$nominal = (float) $nominal;
	if ($nominal <= 0) {
		return;
	}
	$cabang = (int) $cabang;
	akun_update_saldo_delta($conn, akun_hutang_kode(), 'Hutang Dagang', 'pasiva', 'kredit', -$nominal, $cabang);
	$kodeBayar = akun_kode_pembayaran_dari_tipe($tipePembayaran, $cabang);
	akun_update_saldo_pembayaran($conn, $cabang, $kodeBayar, -$nominal);
}

function akun_posting_batal_pelunasan_piutang($conn, $cabang, $nominal, $tipePembayaran)
{
	$nominal = (float) $nominal;
	if ($nominal <= 0) {
		return;
	}
	akun_update_saldo_delta($conn, akun_piutang_kode(), 'Piutang Dagang', 'aktiva', 'debit', $nominal, 0);
	$kodeBayar = akun_kode_pembayaran_dari_tipe($tipePembayaran, $cabang);
	akun_update_saldo_pembayaran($conn, (int) $cabang, $kodeBayar, -$nominal);
}

function akun_posting_batal_pelunasan_hutang($conn, $cabang, $nominal, $tipePembayaran)
{
	$nominal = (float) $nominal;
	if ($nominal <= 0) {
		return;
	}
	akun_update_saldo_delta($conn, akun_hutang_kode(), 'Hutang Dagang', 'pasiva', 'kredit', $nominal, (int) $cabang);
	$kodeBayar = akun_kode_pembayaran_dari_tipe($tipePembayaran, $cabang);
	akun_update_saldo_pembayaran($conn, (int) $cabang, $kodeBayar, $nominal);
}

/** Apakah kode akun termasuk kas tunai cabang (untuk filter laporan pengeluaran). */
function akun_is_kas_tunai_kode($kode)
{
	$kode = (string) $kode;
	foreach (akun_link_kas_tunai_map() as $item) {
		if ($item['kode'] === $kode) {
			return true;
		}
	}
	return in_array($kode, ['1-1100'], true);
}

function akun_sql_kas_tunai_kode_list()
{
	$codes = array_column(akun_link_kas_tunai_map(), 'kode');
	$codes[] = '1-1100';
	return array_values(array_unique($codes));
}

function akun_link_ensure_akun_exists($conn, $kode, $nama, $kategori, $tipeAkun, $cabang, $parentId = null, $level = null)
{
	if (akun_find_laba_kategori_row_exact($conn, $kode, $cabang)) {
		return false;
	}
	$cabangExists = akun_link_cabang_column_exists($conn);
	$kodeEsc = mysqli_real_escape_string($conn, $kode);
	$namaEsc = mysqli_real_escape_string($conn, $nama);
	$katEsc = mysqli_real_escape_string($conn, $kategori);
	$tipEsc = mysqli_real_escape_string($conn, $tipeAkun);
	$cols = 'name, kode_akun, kategori, tipe_akun, saldo';
	$vals = "'$namaEsc', '$kodeEsc', '$katEsc', '$tipEsc', 0";
	if ($cabangExists) {
		$cols .= ', cabang';
		$vals .= ', ' . (int) $cabang;
	}
	if ($parentId !== null && akun_link_column_exists($conn, 'parent_id')) {
		$cols .= ', parent_id';
		$vals .= ', ' . (int) $parentId;
	}
	if ($level !== null && akun_link_column_exists($conn, 'level')) {
		$cols .= ', level';
		$vals .= ', ' . (int) $level;
	}
	mysqli_query($conn, "INSERT INTO laba_kategori ($cols) VALUES ($vals)");
	return true;
}

function akun_link_ensure_bri_cabang($conn, $cabangId, array $info, &$log)
{
	$cabangId = (int) $cabangId;
	$parent = akun_link_find_parent_kas_di_bank($conn, $cabangId);
	$parentId = $parent ? (int) $parent['id'] : null;
	$parentLevel = $parent ? (int) ($parent['level'] ?? 3) : 3;
	$level = min(4, max(2, $parentLevel + 1));

	$created = akun_link_ensure_akun_exists(
		$conn,
		$info['kode'],
		$info['nama'],
		'aktiva',
		'debit',
		$cabangId,
		$parentId,
		$level
	);

	if ($created) {
		$log[] = 'Dibuat akun BRI cabang ' . $cabangId . ': ' . $info['kode'] . ' — ' . $info['nama'];
		return;
	}

	$existing = akun_find_laba_kategori_row_exact($conn, $info['kode'], $cabangId);
	if (!$existing) {
		$log[] = 'Gagal membuat BRI cabang ' . $cabangId . ' (' . $info['kode'] . ') — cek database';
		return;
	}

	if ($parentId !== null) {
		$existingParent = (int) ($existing['parent_id'] ?? 0);
		$existingLevel = (int) ($existing['level'] ?? 0);
		if ($existingParent !== $parentId || ($existingLevel > 0 && $existingLevel !== $level)) {
			akun_link_update_hierarchy($conn, (int) $existing['id'], $parentId, $level);
			$log[] = 'Perbarui hierarki BRI cabang ' . $cabangId . ': ' . $info['kode'];
			return;
		}
	}

	$log[] = 'BRI cabang ' . $cabangId . ' sudah ada: ' . $info['kode'];
}

function akun_link_laba_kategori_id($conn, $kode, $cabang)
{
	$row = akun_find_laba_kategori_row_exact($conn, $kode, $cabang);
	return $row ? (int) $row['id'] : null;
}

function akun_link_find_kas_tunai_row($conn, $cabang)
{
	$cabang = (int) $cabang;
	$row = akun_find_laba_kategori_row_exact($conn, akun_kas_tunai_kode($cabang), $cabang);
	if ($row) {
		return $row;
	}
	if (!akun_link_cabang_column_exists($conn)) {
		return null;
	}
	$q = mysqli_query($conn, "SELECT id, kode_akun, name, cabang FROM laba_kategori
		WHERE cabang = $cabang AND kategori = 'aktiva'
		AND (
			name LIKE '%Kas NU Mart%'
			OR name LIKE '%Kas Tunai%'
			OR name LIKE '%Kas BUMNU%'
			OR kode_akun LIKE '1-110%'
		)
		AND kode_akun NOT LIKE '1-120%'
		AND kode_akun NOT IN ('1-1150', '1-1151', '1-1152', '1-1153')
		ORDER BY kode_akun ASC LIMIT 1");
	if ($q && ($row = mysqli_fetch_assoc($q))) {
		return $row;
	}
	return null;
}

function akun_link_laba_akun_id_rusak($conn, $akunId)
{
	if (!$akunId) {
		return true;
	}
	$id = (int) $akunId;
	if ($id < 1) {
		return true;
	}
	$q = mysqli_query($conn, "SELECT id, kode_akun FROM laba_kategori WHERE id = $id LIMIT 1");
	if (!$q || !($row = mysqli_fetch_assoc($q))) {
		return true;
	}
	return in_array($row['kode_akun'], ['1-1152', '1-1153', '1-1100'], true);
}

function akun_link_is_setor_transfer_row(array $row)
{
	$jenis = strtolower(trim((string) ($row['jenis_transaksi'] ?? '')));
	if ($jenis === 'transfer_uang') {
		return true;
	}
	$ket = strtolower((string) ($row['keterangan'] ?? ''));
	if (strpos($ket, 'setor uang') !== false) {
		return true;
	}
	if (strpos($ket, 'setor tunai') !== false) {
		return true;
	}
	if (strpos($ket, '[transfer_uang]') !== false) {
		return true;
	}
	return false;
}

function akun_link_setor_transfer_perlu_perbaiki_debit(array $row)
{
	$cabang = (int) ($row['cabang'] ?? 0);
	$debitKode = (string) ($row['debit_kode'] ?? '');
	if ((int) ($row['akun_debit'] ?? 0) < 1 || $debitKode === '') {
		return true;
	}
	if (in_array($debitKode, ['1-1152', '1-1153', '1-1100'], true)) {
		return true;
	}
	if (akun_is_kas_bank_bri_kode($debitKode)) {
		$debitCabang = (int) ($row['debit_cabang'] ?? -1);
		$expectedBri = akun_bri_cabang_konsolidasi_toko($cabang);
		if ($cabang > 0 && $debitCabang !== $expectedBri) {
			return true;
		}
	}
	return false;
}

/**
 * Perbaiki akun debit/kredit transaksi setor & transfer_uang yang rusak setelah migrasi COA.
 *
 * @return array{ok: bool, fixed: int, log: list<string>}
 */
function akun_link_perbaiki_laba_setor_bank_bri($conn)
{
	$log = [];
	$chk = mysqli_query($conn, "SHOW COLUMNS FROM laba LIKE 'akun_debit'");
	if (!$chk || mysqli_num_rows($chk) < 1) {
		return ['ok' => false, 'fixed' => 0, 'log' => ['Kolom akun_debit belum ada di tabel laba']];
	}

	$q = mysqli_query($conn, "SELECT l.id, l.cabang, l.akun_debit, l.akun_kredit, l.jenis_transaksi, l.keterangan,
		lk_debit.kode_akun AS debit_kode, lk_debit.cabang AS debit_cabang,
		lk_kredit.kode_akun AS kredit_kode
		FROM laba l
		LEFT JOIN laba_kategori lk_debit ON CAST(l.akun_debit AS UNSIGNED) = lk_debit.id
		LEFT JOIN laba_kategori lk_kredit ON CAST(l.akun_kredit AS UNSIGNED) = lk_kredit.id
		ORDER BY l.date ASC");

	$fixed = 0;
	if (!$q) {
		return ['ok' => false, 'fixed' => 0, 'log' => ['Query laba gagal: ' . mysqli_error($conn)]];
	}

	while ($row = mysqli_fetch_assoc($q)) {
		if (!akun_link_is_setor_transfer_row($row)) {
			continue;
		}

		$cabang = (int) ($row['cabang'] ?? 0);
		$perluDebit = akun_link_setor_transfer_perlu_perbaiki_debit($row);
		$perluKredit = akun_link_laba_akun_id_rusak($conn, $row['akun_kredit'] ?? 0);

		if (!$perluDebit && !$perluKredit) {
			continue;
		}

		$briCabangDebit = akun_bri_cabang_konsolidasi_toko($cabang);
		$briKode = akun_kas_bank_bri_kode($briCabangDebit);
		if ($perluDebit) {
			$briInfo = akun_link_kas_bank_bri_map()[$briCabangDebit] ?? [
				'kode' => $briKode,
				'nama' => akun_kas_bank_bri_nama($briCabangDebit),
			];
			akun_link_ensure_bri_cabang($conn, $briCabangDebit, $briInfo, $log);
		}

		$newDebitId = $perluDebit
			? akun_link_laba_kategori_id($conn, $briKode, $briCabangDebit)
			: (int) ($row['akun_debit'] ?? 0);
		if ($perluDebit && !$newDebitId) {
			$log[] = 'Skip ' . $row['id'] . ': akun BRI ' . $briKode . ' cabang ' . $briCabangDebit . ' belum ada';
			continue;
		}

		$newKreditId = (int) ($row['akun_kredit'] ?? 0);
		if ($perluKredit) {
			$kasRow = akun_link_find_kas_tunai_row($conn, $cabang);
			if ($kasRow) {
				$newKreditId = (int) $kasRow['id'];
			}
		}

		$idEsc = mysqli_real_escape_string($conn, (string) $row['id']);
		$sets = [];
		if ($perluDebit && $newDebitId > 0) {
			$sets[] = "akun_debit = $newDebitId";
			$sets[] = "kategori = '$newDebitId'";
		}
		if ($perluKredit && $newKreditId > 0) {
			$sets[] = "akun_kredit = $newKreditId";
		}
		if (empty($sets)) {
			continue;
		}

		mysqli_query($conn, 'UPDATE laba SET ' . implode(', ', $sets) . " WHERE id = '$idEsc'");
		$fixed++;
		$ketSingkat = substr((string) ($row['keterangan'] ?? ''), 0, 40);
		$log[] = 'Perbaiki setor/transfer cabang ' . $cabang . ' → debit BRI Nugrosir ' . $briKode
			. ($perluKredit ? ' + kredit kas cabang' : '')
			. ' | ' . $ketSingkat;
	}

	$log[] = 'Total transaksi setor/transfer diperbaiki: ' . $fixed
		. '. Jalankan recalculate-laba-kategori untuk sinkron saldo.';

	return ['ok' => true, 'fixed' => $fixed, 'log' => $log];
}

function akun_link_remap_laba_kategori_references($conn, $oldId, $newId, &$log)
{
	$oldId = (int) $oldId;
	$newId = (int) $newId;
	if ($oldId < 1 || $newId < 1 || $oldId === $newId) {
		return;
	}

	mysqli_query($conn, "UPDATE laba SET kategori = '$newId' WHERE CAST(kategori AS UNSIGNED) = $oldId");

	$chkDebit = mysqli_query($conn, "SHOW COLUMNS FROM laba LIKE 'akun_debit'");
	if ($chkDebit && mysqli_num_rows($chkDebit) > 0) {
		mysqli_query($conn, "UPDATE laba SET akun_debit = $newId WHERE CAST(akun_debit AS UNSIGNED) = $oldId");
		mysqli_query($conn, "UPDATE laba SET akun_kredit = $newId WHERE CAST(akun_kredit AS UNSIGNED) = $oldId");
	}

	$log[] = "Remap referensi laba id $oldId → $newId";
}

/**
 * Salin kepala akun level 1–3 dari PCNU (cabang 0) ke cabang toko.
 *
 * @return array{copied: int, skipped: int}
 */
function akun_link_sinkron_hierarki_level123_ke_cabang_toko($conn, &$log)
{
	$copied = 0;
	$skipped = 0;

	if (!akun_link_cabang_column_exists($conn) || !akun_link_column_exists($conn, 'level')) {
		$log[] = 'Skip hierarki: kolom cabang/level belum ada di laba_kategori';
		return ['copied' => 0, 'skipped' => 0];
	}

	$q = mysqli_query($conn, 'SELECT * FROM laba_kategori WHERE cabang = 0 AND level IN (1, 2, 3) ORDER BY level ASC, id ASC');
	if (!$q) {
		$log[] = 'Gagal baca header COA cabang 0: ' . mysqli_error($conn);
		return ['copied' => 0, 'skipped' => 0];
	}

	$headers = [];
	while ($row = mysqli_fetch_assoc($q)) {
		$headers[] = $row;
	}
	if (empty($headers)) {
		$log[] = 'Tidak ada akun level 1–3 di cabang 0 — buat HARTA / HARTA LANCAR / KAS BANK dulu di PCNU';
		return ['copied' => 0, 'skipped' => 0];
	}

	foreach (akun_link_cabang_toko_list() as $targetCabang) {
		$idMap = [];

		foreach ($headers as $header) {
			$sourceId = (int) $header['id'];
			$level = (int) ($header['level'] ?? 1);
			$kode = trim((string) ($header['kode_akun'] ?? ''));
			$name = trim((string) ($header['name'] ?? ''));
			$kategori = trim((string) ($header['kategori'] ?? ''));
			$tipeAkun = trim((string) ($header['tipe_akun'] ?? 'debit'));

			$existing = null;
			if ($kode !== '') {
				$existing = akun_find_laba_kategori_row_exact($conn, $kode, $targetCabang);
			}
			if (!$existing && $name !== '') {
				$nameEsc = mysqli_real_escape_string($conn, $name);
				$katEsc = mysqli_real_escape_string($conn, $kategori);
				$qFind = mysqli_query($conn, "SELECT id, parent_id, level FROM laba_kategori
					WHERE cabang = $targetCabang AND level = $level
					AND name = '$nameEsc' AND kategori = '$katEsc' LIMIT 1");
				if ($qFind && ($r = mysqli_fetch_assoc($qFind))) {
					$existing = $r;
				}
			}

			$sourceParent = (int) ($header['parent_id'] ?? 0);
			$newParent = ($sourceParent > 0 && isset($idMap[$sourceParent])) ? (int) $idMap[$sourceParent] : 0;

			if ($existing) {
				$targetId = (int) $existing['id'];
				$idMap[$sourceId] = $targetId;
				if ($newParent > 0) {
					akun_link_update_hierarchy($conn, $targetId, $newParent, $level);
				}
				$skipped++;
				continue;
			}

			$insertKode = $kode !== '' ? $kode : ('HDR-' . $sourceId);
			akun_link_ensure_akun_exists(
				$conn,
				$insertKode,
				$name !== '' ? $name : $insertKode,
				$kategori !== '' ? $kategori : 'aktiva',
				$tipeAkun !== '' ? $tipeAkun : 'debit',
				$targetCabang,
				$newParent > 0 ? $newParent : null,
				$level
			);

			$newRow = akun_find_laba_kategori_row_exact($conn, $insertKode, $targetCabang);
			if ($newRow) {
				$idMap[$sourceId] = (int) $newRow['id'];
				$log[] = 'Cabang ' . $targetCabang . ': salin L' . $level . ' ' . ($kode !== '' ? $kode : $name);
				$copied++;
			}
		}
	}

	$log[] = 'Sinkron hierarki L1–L3: ' . $copied . ' akun disalin, ' . $skipped . ' sudah ada';
	return ['copied' => $copied, 'skipped' => $skipped];
}

/**
 * Normalisasi BRI cabang toko: gabung kode lama 1-1203..1-1206 → 1-1202 per cabang.
 * PCNU (cabang 0) tidak diubah — 1-1203 Koperasi & 1-1204 Gaji tetap.
 */
function akun_link_normalisasi_bri_cabang_toko($conn, &$log)
{
	$legacyKodes = ['1-1203', '1-1204', '1-1205', '1-1206'];
	$merged = 0;

	foreach (akun_link_cabang_toko_list() as $cabang) {
		$info = akun_link_kas_bank_bri_map()[$cabang] ?? null;
		if (!$info) {
			continue;
		}

		akun_link_ensure_bri_cabang($conn, $cabang, $info, $log);
		$targetRow = akun_find_laba_kategori_row_exact($conn, '1-1202', $cabang);
		if (!$targetRow) {
			$log[] = 'Cabang ' . $cabang . ': akun 1-1202 belum ada setelah ensure — lewati merge';
			continue;
		}
		$targetId = (int) $targetRow['id'];

		foreach ($legacyKodes as $oldKode) {
			$oldRow = akun_find_laba_kategori_row_exact($conn, $oldKode, $cabang);
			if (!$oldRow) {
				continue;
			}
			$oldId = (int) $oldRow['id'];
			if ($oldId === $targetId) {
				continue;
			}

			$saldo = (float) ($oldRow['saldo'] ?? 0);
			if ($saldo != 0.0) {
				akun_update_saldo_delta(
					$conn,
					'1-1202',
					$info['nama'],
					'aktiva',
					'debit',
					$saldo,
					$cabang
				);
				$log[] = 'Cabang ' . $cabang . ': saldo ' . $oldKode . ' Rp '
					. number_format($saldo, 0, ',', '.') . ' → 1-1202';
			}

			akun_link_remap_laba_kategori_references($conn, $oldId, $targetId, $log);
			mysqli_query($conn, 'DELETE FROM laba_kategori WHERE id = ' . $oldId);
			$log[] = 'Cabang ' . $cabang . ': hapus ' . $oldKode . ' (digabung ke 1-1202)';
			$merged++;
		}

		// Sub BRI tanpa kode atau kode salah — set ke 1-1202 + induk 1-1200
		$parent = akun_link_find_parent_kas_di_bank($conn, $cabang);
		if ($parent) {
			akun_link_update_hierarchy($conn, $targetId, (int) $parent['id'], 4);
		}
	}

	$log[] = 'Normalisasi BRI cabang toko selesai (' . $merged . ' akun lama digabung)';
	return ['merged' => $merged];
}

function akun_link_merge_saldo_ke_target($conn, $kodeSumber, $cabangSumber, $kodeTarget, $cabangTarget, $kategori, $tipeAkun, $namaTarget, &$log)
{
	$row = akun_find_laba_kategori_row($conn, $kodeSumber, $cabangSumber);
	if (!$row) {
		return;
	}
	$saldo = (float) ($row['saldo'] ?? 0);
	$oldId = (int) $row['id'];
	$targetRow = akun_find_laba_kategori_row_exact($conn, $kodeTarget, $cabangTarget);
	if ($targetRow && (int) $targetRow['id'] !== $oldId) {
		akun_link_remap_laba_kategori_references($conn, $oldId, (int) $targetRow['id'], $log);
	}
	if ($saldo != 0.0) {
		akun_update_saldo_delta($conn, $kodeTarget, $namaTarget, $kategori, $tipeAkun, $saldo, $cabangTarget);
		$log[] = "Saldo $kodeSumber (cabang $cabangSumber) Rp " . number_format($saldo, 0, ',', '.') . " → $kodeTarget (cabang $cabangTarget)";
	}
	mysqli_query($conn, 'DELETE FROM laba_kategori WHERE id = ' . (int) $row['id']);
}

function akun_link_rename_kode_akun($conn, $kodeLama, $kodeBaru, $namaBaru, $kategori, $tipeAkun, &$log)
{
	$lamaEsc = mysqli_real_escape_string($conn, $kodeLama);
	$baruEsc = mysqli_real_escape_string($conn, $kodeBaru);
	$namaEsc = mysqli_real_escape_string($conn, $namaBaru);
	$q = mysqli_query($conn, "SELECT id, saldo, cabang FROM laba_kategori WHERE kode_akun = '$lamaEsc'");
	if (!$q) {
		return;
	}
	while ($row = mysqli_fetch_assoc($q)) {
		$cabang = (int) ($row['cabang'] ?? 0);
		$existing = akun_find_laba_kategori_row($conn, $kodeBaru, $cabang);
		if ($existing) {
			$saldo = (float) ($row['saldo'] ?? 0);
			if ($saldo != 0.0) {
				akun_update_saldo_delta($conn, $kodeBaru, $namaBaru, $kategori, $tipeAkun, $saldo, $cabang);
			}
			mysqli_query($conn, 'DELETE FROM laba_kategori WHERE id = ' . (int) $row['id']);
			$log[] = "Gabung $kodeLama → $kodeBaru (cabang $cabang)";
		} else {
			mysqli_query($conn, "UPDATE laba_kategori SET kode_akun = '$baruEsc', name = '$namaEsc' WHERE id = " . (int) $row['id']);
			$log[] = "Rename $kodeLama → $kodeBaru (cabang $cabang)";
		}
	}
}

/**
 * Migrasi COA sesuai docs/DAFTAR LINK AKUN.docx — jalankan sekali di live.
 *
 * @return array{ok: bool, log: list<string>}
 */
function akun_link_migrasi_semua($conn)
{
	$log = [];
	$cabangExists = akun_link_cabang_column_exists($conn);

	akun_link_sinkron_hierarki_level123_ke_cabang_toko($conn, $log);
	akun_link_normalisasi_bri_cabang_toko($conn, $log);

	foreach (akun_link_kas_tunai_map() as $cabangId => $info) {
		akun_link_ensure_akun_exists($conn, $info['kode'], $info['nama'], 'aktiva', 'debit', (int) $cabangId);
	}
	// Mirror kas toko di Nugrosir (cabang 0) = saldo pemilik Numart
	$mirrorKas = akun_sync_all_kas_tunai_mirror_nugrosir($conn);
	$log[] = 'Mirror kas tunai Nugrosir disinkron: ' . (int) ($mirrorKas['synced'] ?? 0)
		. ' akun (' . implode(', ', $mirrorKas['codes'] ?? []) . ')';
	foreach (akun_link_kas_bank_bri_map() as $cabangId => $info) {
		akun_link_ensure_bri_cabang($conn, (int) $cabangId, $info, $log);
	}
	akun_link_ensure_akun_exists($conn, akun_piutang_kode(), 'Piutang Dagang', 'aktiva', 'debit', 0);
	akun_link_ensure_akun_exists($conn, akun_hutang_kode(), 'Hutang Dagang', 'pasiva', 'kredit', 0);

	if ($cabangExists) {
		$qKasLama = mysqli_query($conn, "SELECT id, saldo, cabang FROM laba_kategori WHERE kode_akun = '1-1100'");
		if ($qKasLama) {
			while ($row = mysqli_fetch_assoc($qKasLama)) {
				$cb = (int) ($row['cabang'] ?? 0);
				$targetKode = akun_kas_tunai_kode($cb);
				akun_link_merge_saldo_ke_target(
					$conn,
					'1-1100',
					$cb,
					$targetKode,
					$cb,
					'aktiva',
					'debit',
					akun_kas_tunai_nama($cb),
					$log
				);
			}
		}
	} else {
		akun_link_merge_saldo_ke_target($conn, '1-1100', 0, akun_kas_tunai_kode(0), 0, 'aktiva', 'debit', akun_kas_tunai_nama(0), $log);
	}

	$bankSaldo = 0.0;
	foreach (['1-1152', '1-1153'] as $kodeBankLama) {
		$qBank = mysqli_query($conn, "SELECT id, saldo FROM laba_kategori WHERE kode_akun = '$kodeBankLama'");
		if ($qBank) {
			while ($row = mysqli_fetch_assoc($qBank)) {
				$bankSaldo += (float) ($row['saldo'] ?? 0);
				mysqli_query($conn, 'DELETE FROM laba_kategori WHERE id = ' . (int) $row['id']);
			}
		}
	}
	if ($bankSaldo != 0.0) {
		akun_update_saldo_delta(
			$conn,
			akun_kas_bank_bri_kode(0),
			akun_kas_bank_bri_nama(0),
			'aktiva',
			'debit',
			$bankSaldo,
			0
		);
		$log[] = 'Gabung 1-1152 + 1-1153 → 1-1202 (Nugrosir): Rp ' . number_format($bankSaldo, 0, ',', '.');
	}

	akun_link_rename_kode_akun($conn, '1-1300', akun_piutang_kode(), 'Piutang Dagang', 'aktiva', 'debit', $log);
	akun_link_rename_kode_akun($conn, '2-1100', akun_hutang_kode(), 'Hutang Dagang', 'pasiva', 'kredit', $log);

	mysqli_query($conn, "DELETE FROM laba_kategori WHERE kode_akun IN ('1-1152', '1-1153', '1-1100')");

	if ($cabangExists) {
		$qHutangCabang = mysqli_query($conn, "SELECT id, cabang, saldo FROM laba_kategori WHERE kode_akun = '" . akun_hutang_kode() . "'");
		if ($qHutangCabang && mysqli_num_rows($qHutangCabang) > 1) {
			$log[] = 'Catatan: ada beberapa baris hutang per cabang — pastikan saldo sudah benar per cabang.';
		}
	}

	$hasilSetor = akun_link_perbaiki_laba_setor_bank_bri($conn);
	foreach ($hasilSetor['log'] ?? [] as $barisSetor) {
		$log[] = $barisSetor;
	}

	$log[] = 'Migrasi selesai. Jalankan recalculate-laba-kategori jika saldo transaksi perlu disinkronkan ulang.';

	return ['ok' => true, 'log' => $log];
}
