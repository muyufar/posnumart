<?php
/**
 * Pemetaan akun sesuai docs/DAFTAR LINK AKUN.docx
 * Kas tunai per cabang, bank BRI 1-1202, piutang 1-1301, hutang 2-1101.
 */

function akun_link_cabang_column_exists($conn)
{
	static $exists = null;
	if ($exists !== null) {
		return $exists;
	}
	$chk = mysqli_query($conn, "SHOW COLUMNS FROM laba_kategori LIKE 'cabang'");
	$exists = ($chk && mysqli_num_rows($chk) > 0);
	return $exists;
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

function akun_kas_bank_bri_kode()
{
	return '1-1202';
}

function akun_kas_bank_bri_nama()
{
	return 'Kas Bank BRI 0251';
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
	return akun_kas_bank_bri_kode();
}

function akun_nama_pembayaran_dari_kode($kode, $cabang)
{
	if ($kode === akun_kas_bank_bri_kode()) {
		return akun_kas_bank_bri_nama();
	}
	return akun_kas_tunai_nama($cabang);
}

/** Cabang bank BRI tingkat 4 (pusat). */
function akun_kas_bank_cabang()
{
	return 0;
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
		akun_update_saldo_delta(
			$conn,
			akun_piutang_kode(),
			'Piutang Dagang',
			'aktiva',
			'debit',
			$sisaPiutang,
			0
		);
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

	// QRIS / Transfer → Kas Bank BRI 1-1202 (cabang pusat)
	akun_update_saldo_delta(
		$conn,
		akun_kas_bank_bri_kode(),
		akun_kas_bank_bri_nama(),
		'aktiva',
		'debit',
		$subTotal,
		akun_kas_bank_cabang()
	);
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
	$cbBayar = ($kodeBayar === akun_kas_bank_bri_kode()) ? akun_kas_bank_cabang() : $cabang;
	akun_update_saldo_delta(
		$conn,
		$kodeBayar,
		akun_nama_pembayaran_dari_kode($kodeBayar, $cabang),
		'aktiva',
		'debit',
		$nominal,
		$cbBayar
	);
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
	$cbBayar = ($kodeBayar === akun_kas_bank_bri_kode()) ? akun_kas_bank_cabang() : $cabang;
	akun_update_saldo_delta(
		$conn,
		$kodeBayar,
		akun_nama_pembayaran_dari_kode($kodeBayar, $cabang),
		'aktiva',
		'debit',
		-$nominal,
		$cbBayar
	);
}

function akun_posting_batal_pelunasan_piutang($conn, $cabang, $nominal, $tipePembayaran)
{
	$nominal = (float) $nominal;
	if ($nominal <= 0) {
		return;
	}
	akun_update_saldo_delta($conn, akun_piutang_kode(), 'Piutang Dagang', 'aktiva', 'debit', $nominal, 0);
	$kodeBayar = akun_kode_pembayaran_dari_tipe($tipePembayaran, $cabang);
	$cbBayar = ($kodeBayar === akun_kas_bank_bri_kode()) ? akun_kas_bank_cabang() : (int) $cabang;
	akun_update_saldo_delta(
		$conn,
		$kodeBayar,
		akun_nama_pembayaran_dari_kode($kodeBayar, $cabang),
		'aktiva',
		'debit',
		-$nominal,
		$cbBayar
	);
}

function akun_posting_batal_pelunasan_hutang($conn, $cabang, $nominal, $tipePembayaran)
{
	$nominal = (float) $nominal;
	if ($nominal <= 0) {
		return;
	}
	akun_update_saldo_delta($conn, akun_hutang_kode(), 'Hutang Dagang', 'pasiva', 'kredit', $nominal, (int) $cabang);
	$kodeBayar = akun_kode_pembayaran_dari_tipe($tipePembayaran, $cabang);
	$cbBayar = ($kodeBayar === akun_kas_bank_bri_kode()) ? akun_kas_bank_cabang() : (int) $cabang;
	akun_update_saldo_delta(
		$conn,
		$kodeBayar,
		akun_nama_pembayaran_dari_kode($kodeBayar, $cabang),
		'aktiva',
		'debit',
		$nominal,
		$cbBayar
	);
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

function akun_link_ensure_akun_exists($conn, $kode, $nama, $kategori, $tipeAkun, $cabang)
{
	if (akun_find_laba_kategori_row($conn, $kode, $cabang)) {
		return;
	}
	$cabangExists = akun_link_cabang_column_exists($conn);
	$kodeEsc = mysqli_real_escape_string($conn, $kode);
	$namaEsc = mysqli_real_escape_string($conn, $nama);
	$katEsc = mysqli_real_escape_string($conn, $kategori);
	$tipEsc = mysqli_real_escape_string($conn, $tipeAkun);
	if ($cabangExists) {
		mysqli_query($conn, "INSERT INTO laba_kategori (name, kode_akun, kategori, tipe_akun, saldo, cabang)
			VALUES ('$namaEsc', '$kodeEsc', '$katEsc', '$tipEsc', 0, " . (int) $cabang . ')');
	} else {
		mysqli_query($conn, "INSERT INTO laba_kategori (name, kode_akun, kategori, tipe_akun, saldo)
			VALUES ('$namaEsc', '$kodeEsc', '$katEsc', '$tipEsc', 0)");
	}
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
	akun_link_ensure_akun_exists(
		$conn,
		akun_kas_bank_bri_kode(),
		akun_kas_bank_bri_nama(),
		'aktiva',
		'debit',
		akun_kas_bank_cabang()
	);
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
			akun_kas_bank_bri_kode(),
			akun_kas_bank_bri_nama(),
			'aktiva',
			'debit',
			$bankSaldo,
			akun_kas_bank_cabang()
		);
		$log[] = 'Gabung 1-1152 + 1-1153 → 1-1202: Rp ' . number_format($bankSaldo, 0, ',', '.');
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
