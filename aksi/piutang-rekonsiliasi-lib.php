<?php

require_once __DIR__ . '/akun-link-lib.php';
require_once __DIR__ . '/hutang-rekonsiliasi-lib.php';
require_once __DIR__ . '/cabang-arsip-lib.php';

function piutang_rekon_cabang_akun()
{
	return 0;
}

/**
 * Filter cabang untuk agregat "semua" — cabang arsip (BAQNU) dikecualikan.
 */
function piutang_rekon_where_cabang(mysqli $conn, $cabangFilter, string $column = 'invoice_cabang'): string
{
	if ($cabangFilter !== null && $cabangFilter !== '') {
		return ' AND ' . $column . ' = ' . (int) $cabangFilter;
	}
	return cabang_sql_exclude_arsip($conn, $column);
}

/**
 * @return array<string, mixed>
 */
function piutang_rekon_ringkasan(mysqli $conn, $cabangFilter = null)
{
	$cabangAkun = piutang_rekon_cabang_akun();
	$kodePiutang = akun_piutang_kode();

	$saldoAkun = 0.0;
	$rowAkun = akun_find_laba_kategori_row_exact($conn, $kodePiutang, $cabangAkun);
	if ($rowAkun) {
		$saldoAkun = (float) ($rowAkun['saldo'] ?? 0);
	}

	$whereCabang = piutang_rekon_where_cabang($conn, $cabangFilter, 'invoice_cabang');

	$totalPenjualanPiutang = 0.0;
	$qPenjualan = mysqli_query($conn, "
		SELECT COALESCE(SUM(GREATEST(invoice_sub_total - COALESCE(invoice_piutang_dp, 0), 0)), 0) AS total
		FROM invoice
		WHERE invoice_piutang = 1
		$whereCabang
	");
	if ($qPenjualan && ($row = mysqli_fetch_assoc($qPenjualan))) {
		$totalPenjualanPiutang = (float) ($row['total'] ?? 0);
	}

	$totalCicilan = 0.0;
	$jumlahBarisCicilan = 0;
	$wherePiutangCabang = ' WHERE 1=1' . piutang_rekon_where_cabang($conn, $cabangFilter, 'piutang_cabang');
	$qCicilan = mysqli_query($conn, "
		SELECT COALESCE(SUM(piutang_nominal), 0) AS total, COUNT(*) AS jumlah
		FROM piutang
		$wherePiutangCabang
	");
	if ($qCicilan && ($row = mysqli_fetch_assoc($qCicilan))) {
		$totalCicilan = (float) ($row['total'] ?? 0);
		$jumlahBarisCicilan = (int) ($row['jumlah'] ?? 0);
	}

	$sisaSemuaInvoice = 0.0;
	$jumlahInvoicePiutang = 0;
	$qSisa = mysqli_query($conn, "
		SELECT
			COALESCE(SUM(GREATEST(invoice_sub_total - invoice_bayar, 0)), 0) AS sisa,
			COUNT(*) AS jumlah
		FROM invoice
		WHERE invoice_piutang = 1
		$whereCabang
	");
	if ($qSisa && ($row = mysqli_fetch_assoc($qSisa))) {
		$sisaSemuaInvoice = (float) ($row['sisa'] ?? 0);
		$jumlahInvoicePiutang = (int) ($row['jumlah'] ?? 0);
	}

	$cicilanDariInvoice = max(0.0, $totalPenjualanPiutang - $sisaSemuaInvoice);
	$piutangAkuntansiInvoice = $sisaSemuaInvoice;
	$cicilanTabelTidakWajar = ($totalCicilan > ($totalPenjualanPiutang * 2) && $totalPenjualanPiutang > 0)
		|| ($totalCicilan > 0 && abs($totalCicilan - $cicilanDariInvoice) > 1000000 && $cicilanDariInvoice >= 0);

	$piutangAkuntansi = $cicilanTabelTidakWajar
		? $piutangAkuntansiInvoice
		: ($totalPenjualanPiutang - $totalCicilan);

	$piutangBelumLunas = 0.0;
	$jumlahInvoiceBelumLunas = 0;
	$qBelum = mysqli_query($conn, "
		SELECT
			COALESCE(SUM(invoice_sub_total - invoice_bayar), 0) AS total,
			COUNT(*) AS jumlah
		FROM invoice
		WHERE invoice_piutang = 1
		  AND invoice_bayar < invoice_sub_total
		$whereCabang
	");
	if ($qBelum && ($row = mysqli_fetch_assoc($qBelum))) {
		$piutangBelumLunas = (float) ($row['total'] ?? 0);
		$jumlahInvoiceBelumLunas = (int) ($row['jumlah'] ?? 0);
	}

	$ringkasanSemua = piutang_rekon_ringkasan_cabang($conn, null);
	$mutasiOperasional = piutang_rekon_mutasi_operasional($conn);

	return [
		'cabang_filter' => $cabangFilter,
		'cabang_akun' => $cabangAkun,
		'kode_akun' => $kodePiutang,
		'saldo_akun' => $saldoAkun,
		'total_penjualan_piutang' => $totalPenjualanPiutang,
		'total_cicilan' => $totalCicilan,
		'jumlah_baris_cicilan' => $jumlahBarisCicilan,
		'cicilan_dari_invoice' => $cicilanDariInvoice,
		'cicilan_tabel_tidak_wajar' => $cicilanTabelTidakWajar,
		'jumlah_invoice_piutang' => $jumlahInvoicePiutang,
		'piutang_akuntansi' => $piutangAkuntansi,
		'piutang_akuntansi_invoice' => $piutangAkuntansiInvoice,
		'piutang_belum_lunas' => $piutangBelumLunas,
		'jumlah_invoice_belum_lunas' => $jumlahInvoiceBelumLunas,
		'piutang_belum_lunas_semua_cabang' => $ringkasanSemua['piutang_belum_lunas'],
		'jumlah_invoice_belum_lunas_semua' => $ringkasanSemua['jumlah_invoice_belum_lunas'],
		'selisih_akun_vs_semua_cabang' => $saldoAkun - $ringkasanSemua['piutang_belum_lunas'],
		'selisih_akun_vs_invoice' => $saldoAkun - $piutangAkuntansiInvoice,
		'selisih_akun_vs_belum_lunas' => $saldoAkun - $piutangBelumLunas,
		'mutasi_operasional' => $mutasiOperasional,
		'per_cabang' => piutang_rekon_per_cabang($conn),
	];
}

/**
 * @return array{piutang_belum_lunas: float, jumlah_invoice_belum_lunas: int}
 */
function piutang_rekon_ringkasan_cabang(mysqli $conn, $cabang)
{
	$whereCabang = piutang_rekon_where_cabang($conn, $cabang, 'invoice_cabang');
	$total = 0.0;
	$jumlah = 0;
	$q = mysqli_query($conn, "
		SELECT
			COALESCE(SUM(invoice_sub_total - invoice_bayar), 0) AS total,
			COUNT(*) AS jumlah
		FROM invoice
		WHERE invoice_piutang = 1
		  AND invoice_bayar < invoice_sub_total
		$whereCabang
	");
	if ($q && ($row = mysqli_fetch_assoc($q))) {
		$total = (float) ($row['total'] ?? 0);
		$jumlah = (int) ($row['jumlah'] ?? 0);
	}
	return [
		'piutang_belum_lunas' => $total,
		'jumlah_invoice_belum_lunas' => $jumlah,
	];
}

/**
 * @return list<array<string, mixed>>
 */
function piutang_rekon_per_cabang(mysqli $conn)
{
	$out = [];
	$excludeArsip = cabang_sql_exclude_arsip($conn, 'invoice_cabang');
	$q = mysqli_query($conn, "
		SELECT
			invoice_cabang AS cabang,
			COALESCE(SUM(invoice_sub_total - invoice_bayar), 0) AS belum_lunas,
			COUNT(*) AS jumlah_invoice
		FROM invoice
		WHERE invoice_piutang = 1
		  AND invoice_bayar < invoice_sub_total
		  $excludeArsip
		GROUP BY invoice_cabang
		ORDER BY invoice_cabang ASC
	");
	if ($q) {
		while ($row = mysqli_fetch_assoc($q)) {
			$out[] = $row;
		}
	}
	return $out;
}

/**
 * @return array{total_net: float, jumlah: int, rows: list<array<string, mixed>>}
 */
function piutang_rekon_mutasi_operasional(mysqli $conn)
{
	$kodeEsc = mysqli_real_escape_string($conn, akun_piutang_kode());
	$rows = [];
	$totalNet = 0.0;

	$chk = mysqli_query($conn, "SHOW COLUMNS FROM laba LIKE 'akun_debit'");
	if (!$chk || mysqli_num_rows($chk) < 1) {
		return ['total_net' => 0.0, 'jumlah' => 0, 'rows' => []];
	}

	$q = mysqli_query($conn, "
		SELECT
			l.id,
			l.date,
			l.cabang,
			l.keterangan,
			l.jenis_transaksi,
			l.total,
			lk_debit.kode_akun AS kode_debit,
			lk_kredit.kode_akun AS kode_kredit
		FROM laba l
		LEFT JOIN laba_kategori lk_debit ON CAST(l.akun_debit AS UNSIGNED) = lk_debit.id
		LEFT JOIN laba_kategori lk_kredit ON CAST(l.akun_kredit AS UNSIGNED) = lk_kredit.id
		WHERE lk_debit.kode_akun = '$kodeEsc'
		   OR lk_kredit.kode_akun = '$kodeEsc'
		ORDER BY l.date DESC
		LIMIT 200
	");

	if ($q) {
		while ($row = mysqli_fetch_assoc($q)) {
			$amount = (float) ($row['total'] ?? 0);
			$net = 0.0;
			$arah = '';
			if (($row['kode_debit'] ?? '') === akun_piutang_kode()) {
				$net = $amount;
				$arah = 'Debit (+ piutang)';
			} elseif (($row['kode_kredit'] ?? '') === akun_piutang_kode()) {
				$net = -$amount;
				$arah = 'Kredit (− piutang)';
			}
			$totalNet += $net;
			$row['net_piutang'] = $net;
			$row['arah'] = $arah;
			$rows[] = $row;
		}
	}

	return [
		'total_net' => $totalNet,
		'jumlah' => count($rows),
		'rows' => $rows,
	];
}
