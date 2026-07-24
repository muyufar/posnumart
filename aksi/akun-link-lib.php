<?php
/**
 * Pemetaan akun sesuai docs/DAFTAR LINK AKUN.docx
 * Kas tunai per cabang, bank BRI 0251 per cabang (terhubung ke 1-1202 Nugrosir),
 * piutang 1-1301, hutang 2-1101.
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
function akun_link_kas_bank_bri_map()
{
	return [
		0 => ['kode' => '1-1202', 'nama' => 'Kas Bank BRI 0251 Nugrosir'],
		1 => ['kode' => '1-1203', 'nama' => 'Kas Bank BRI 0251 Dukun'],
		3 => ['kode' => '1-1204', 'nama' => 'Kas Bank BRI 0251 Srumbung'],
		2 => ['kode' => '1-1205', 'nama' => 'Kas Bank BRI 0251 Pakis'],
		5 => ['kode' => '1-1206', 'nama' => 'Kas Bank BRI 0251 Tegalrejo'],
	];
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
	foreach (akun_link_kas_bank_bri_map() as $item) {
		if ($item['kode'] === $kode) {
			return true;
		}
	}
	return in_array($kode, ['1-1152', '1-1153'], true);
}

function akun_cabang_dari_kode_bank_bri($kode)
{
	$kode = (string) $kode;
	foreach (akun_link_kas_bank_bri_map() as $cabangId => $item) {
		if ($item['kode'] === $kode) {
			return (int) $cabangId;
		}
	}
	return null;
}

function akun_sql_kas_bank_bri_kode_list()
{
	$codes = array_column(akun_link_kas_bank_bri_map(), 'kode');
	$codes[] = '1-1152';
	$codes[] = '1-1153';
	return array_values(array_unique($codes));
}

/**
 * Update saldo BRI cabang + mirror otomatis ke BRI Nugrosir (1-1202 cabang 0).
 */
function akun_update_saldo_bank_bri($conn, $cabang, $delta)
{
	$cabang = (int) $cabang;
	$delta = (float) $delta;
	if ($delta == 0.0) {
		return;
	}

	akun_update_saldo_delta(
		$conn,
		akun_kas_bank_bri_kode($cabang),
		akun_kas_bank_bri_nama($cabang),
		'aktiva',
		'debit',
		$delta,
		akun_kas_bank_bri_cabang($cabang)
	);

	if ($cabang !== 0) {
		akun_update_saldo_delta(
			$conn,
			akun_kas_bank_bri_kode(0),
			akun_kas_bank_bri_nama(0),
			'aktiva',
			'debit',
			$delta,
			0
		);
	}
}

function akun_update_saldo_pembayaran($conn, $cabang, $kodeBayar, $delta)
{
	$cabang = (int) $cabang;
	$delta = (float) $delta;
	if ($delta == 0.0) {
		return;
	}
	if (akun_is_kas_bank_bri_kode($kodeBayar)) {
		$cbBri = akun_cabang_dari_kode_bank_bri($kodeBayar);
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
		'1-1202' => ['1-1152', '1-1153'],
		'1-1203' => ['1-1152', '1-1153'],
		'1-1204' => ['1-1152', '1-1153'],
		'1-1205' => ['1-1152', '1-1153'],
		'1-1206' => ['1-1152', '1-1153'],
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
	$row = akun_find_laba_kategori_row($conn, $kode_akun, $cabang);
	if ($row) {
		$saldoBaru = (float) ($row['saldo'] ?? 0) + $delta;
		mysqli_query($conn, 'UPDATE laba_kategori SET saldo = ' . $saldoBaru . ' WHERE id = ' . (int) $row['id']);
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
		$cbBri = akun_cabang_dari_kode_bank_bri($kode);
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
		if ($cabang !== 0) {
			return;
		}
		$sisaPiutang = max(0.0, $subTotal - $piutangDp);
		if ($sisaPiutang > 0) {
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

	// QRIS / Transfer → Kas Bank BRI cabang (+ mirror ke Nugrosir)
	akun_update_saldo_bank_bri($conn, $cabang, $subTotal);
}

/**
 * Posting saldo akun setelah pembelian disimpan.
 */
function akun_posting_setelah_pembelian($conn, $cabang, $hutang, $total, $hutangDp = 0)
{
	$cabang = (int) $cabang;
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
		if ($hutangDp > 0) {
			akun_update_saldo_delta(
				$conn,
				akun_kas_tunai_kode($cabang),
				akun_kas_tunai_nama($cabang),
				'aktiva',
				'debit',
				-$hutangDp,
				$cabang
			);
		}
		return;
	}

	// Pembelian tunai → kurangi kas tunai cabang (muncul di laporan harian)
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

function akun_link_merge_saldo_ke_target($conn, $kodeSumber, $cabangSumber, $kodeTarget, $cabangTarget, $kategori, $tipeAkun, $namaTarget, &$log)
{
	$row = akun_find_laba_kategori_row($conn, $kodeSumber, $cabangSumber);
	if (!$row) {
		return;
	}
	$saldo = (float) ($row['saldo'] ?? 0);
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

	foreach (akun_link_kas_tunai_map() as $cabangId => $info) {
		akun_link_ensure_akun_exists($conn, $info['kode'], $info['nama'], 'aktiva', 'debit', (int) $cabangId);
	}
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

	$log[] = 'Migrasi selesai. Jalankan recalculate-laba-kategori jika saldo transaksi perlu disinkronkan ulang.';

	return ['ok' => true, 'log' => $log];
}
