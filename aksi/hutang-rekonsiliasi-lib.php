<?php

require_once __DIR__ . '/akun-link-lib.php';

function hutang_rekon_fmt_rupiah($amount)
{
	return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function hutang_rekon_fmt_rupiah_dec($amount)
{
	return 'Rp ' . number_format((float) $amount, 2, ',', '.');
}

function hutang_rekon_list_cabang($conn)
{
	require_once __DIR__ . '/cabang-arsip-lib.php';
	$list = [];
	$q = mysqli_query($conn, 'SELECT toko_cabang, toko_nama, toko_kota, toko_status FROM toko ORDER BY toko_cabang');
	if ($q) {
		while ($row = mysqli_fetch_assoc($q)) {
			// Sembunyikan cabang arsip dari dropdown rekonsiliasi aktif
			if (!cabang_is_aktif($conn, (int) ($row['toko_cabang'] ?? -1))) {
				continue;
			}
			$list[] = $row;
		}
	}
	return $list;
}

function hutang_rekon_nama_cabang(array $listCabang, $cabang)
{
	$cabang = (int) $cabang;
	foreach ($listCabang as $row) {
		if ((int) ($row['toko_cabang'] ?? -1) === $cabang) {
			$name = trim((string) ($row['toko_nama'] ?? ''));
			$kota = trim((string) ($row['toko_kota'] ?? ''));
			return $kota !== '' ? $name . ' — ' . $kota : $name;
		}
	}
	return $cabang < 1 ? 'Pusat (Nugrosir)' : 'Cabang ' . $cabang;
}

/**
 * @return array<string, mixed>
 */
function hutang_rekon_ringkasan(mysqli $conn, $cabang)
{
	$cabang = (int) $cabang;
	$kodeHutang = akun_hutang_kode();

	$saldoAkun = 0.0;
	$rowAkun = akun_find_laba_kategori_row_exact($conn, $kodeHutang, $cabang);
	if ($rowAkun) {
		$saldoAkun = (float) ($rowAkun['saldo'] ?? 0);
	}

	$totalPembelianHutang = 0.0;
	$qPembelian = mysqli_query($conn, "
		SELECT COALESCE(SUM(GREATEST(invoice_total - COALESCE(invoice_hutang_dp, 0), 0)), 0) AS total
		FROM invoice_pembelian
		WHERE invoice_pembelian_cabang = $cabang
		  AND invoice_hutang = 1
	");
	if ($qPembelian && ($row = mysqli_fetch_assoc($qPembelian))) {
		$totalPembelianHutang = (float) ($row['total'] ?? 0);
	}

	$totalCicilan = 0.0;
	$jumlahBarisCicilan = 0;
	$qCicilan = mysqli_query($conn, "
		SELECT COALESCE(SUM(hutang_nominal), 0) AS total, COUNT(*) AS jumlah
		FROM hutang
		WHERE hutang_cabang = $cabang
	");
	if ($qCicilan && ($row = mysqli_fetch_assoc($qCicilan))) {
		$totalCicilan = (float) ($row['total'] ?? 0);
		$jumlahBarisCicilan = (int) ($row['jumlah'] ?? 0);
	}

	$sisaSemuaInvoice = 0.0;
	$jumlahInvoiceHutang = 0;
	$qSisa = mysqli_query($conn, "
		SELECT
			COALESCE(SUM(GREATEST(invoice_total - invoice_bayar, 0)), 0) AS sisa,
			COUNT(*) AS jumlah
		FROM invoice_pembelian
		WHERE invoice_pembelian_cabang = $cabang
		  AND invoice_hutang = 1
	");
	if ($qSisa && ($row = mysqli_fetch_assoc($qSisa))) {
		$sisaSemuaInvoice = (float) ($row['sisa'] ?? 0);
		$jumlahInvoiceHutang = (int) ($row['jumlah'] ?? 0);
	}

	$cicilanDariInvoice = max(0.0, $totalPembelianHutang - $sisaSemuaInvoice);
	$hutangAkuntansiInvoice = $sisaSemuaInvoice;
	$cicilanTabelTidakWajar = ($totalCicilan > ($totalPembelianHutang * 2) && $totalPembelianHutang > 0)
		|| ($totalCicilan > 0 && abs($totalCicilan - $cicilanDariInvoice) > 1000000 && $cicilanDariInvoice >= 0);

	// Jangan pakai total tabel hutang jika datanya tidak wajar (sering terjadi duplikasi historis).
	$hutangAkuntansi = $cicilanTabelTidakWajar
		? $hutangAkuntansiInvoice
		: ($totalPembelianHutang - $totalCicilan);

	$hutangBelumLunas = 0.0;
	$jumlahInvoiceBelumLunas = 0;
	$qBelum = mysqli_query($conn, "
		SELECT
			COALESCE(SUM(invoice_total - invoice_bayar), 0) AS total,
			COUNT(*) AS jumlah
		FROM invoice_pembelian
		WHERE invoice_pembelian_cabang = $cabang
		  AND invoice_hutang = 1
		  AND invoice_bayar < invoice_total
	");
	if ($qBelum && ($row = mysqli_fetch_assoc($qBelum))) {
		$hutangBelumLunas = (float) ($row['total'] ?? 0);
		$jumlahInvoiceBelumLunas = (int) ($row['jumlah'] ?? 0);
	}

	$mutasiOperasional = hutang_rekon_mutasi_operasional($conn, $cabang);

	return [
		'cabang' => $cabang,
		'kode_akun' => $kodeHutang,
		'saldo_akun' => $saldoAkun,
		'total_pembelian_hutang' => $totalPembelianHutang,
		'total_cicilan' => $totalCicilan,
		'jumlah_baris_cicilan' => $jumlahBarisCicilan,
		'cicilan_dari_invoice' => $cicilanDariInvoice,
		'cicilan_tabel_tidak_wajar' => $cicilanTabelTidakWajar,
		'jumlah_invoice_hutang' => $jumlahInvoiceHutang,
		'hutang_akuntansi' => $hutangAkuntansi,
		'hutang_akuntansi_invoice' => $hutangAkuntansiInvoice,
		'hutang_belum_lunas' => $hutangBelumLunas,
		'jumlah_invoice_belum_lunas' => $jumlahInvoiceBelumLunas,
		'selisih_akun_vs_akuntansi' => $saldoAkun - $hutangAkuntansi,
		'selisih_akun_vs_belum_lunas' => $saldoAkun - $hutangBelumLunas,
		'selisih_akun_vs_invoice' => $saldoAkun - $hutangAkuntansiInvoice,
		'selisih_akuntansi_vs_belum_lunas' => $hutangAkuntansi - $hutangBelumLunas,
		'mutasi_operasional' => $mutasiOperasional,
	];
}

/**
 * Transaksi data operasional (laba) yang menyentuh akun 2-1101.
 *
 * @return array{total_net: float, jumlah: int, rows: list<array<string, mixed>>}
 */
function hutang_rekon_mutasi_operasional(mysqli $conn, $cabang)
{
	$cabang = (int) $cabang;
	$kodeEsc = mysqli_real_escape_string($conn, akun_hutang_kode());
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
			l.keterangan,
			l.jenis_transaksi,
			l.total,
			l.tipe,
			lk_debit.kode_akun AS kode_debit,
			lk_debit.name AS nama_debit,
			lk_kredit.kode_akun AS kode_kredit,
			lk_kredit.name AS nama_kredit
		FROM laba l
		LEFT JOIN laba_kategori lk_debit ON CAST(l.akun_debit AS UNSIGNED) = lk_debit.id
		LEFT JOIN laba_kategori lk_kredit ON CAST(l.akun_kredit AS UNSIGNED) = lk_kredit.id
		WHERE l.cabang = $cabang
		  AND (
			lk_debit.kode_akun = '$kodeEsc'
			OR lk_kredit.kode_akun = '$kodeEsc'
		  )
		ORDER BY l.date DESC
		LIMIT 200
	");

	if ($q) {
		while ($row = mysqli_fetch_assoc($q)) {
			$amount = (float) ($row['total'] ?? 0);
			$net = 0.0;
			$arah = '';
			if (($row['kode_debit'] ?? '') === akun_hutang_kode()) {
				$net = $amount;
				$arah = 'Debit (+ hutang)';
			} elseif (($row['kode_kredit'] ?? '') === akun_hutang_kode()) {
				$net = -$amount;
				$arah = 'Kredit (− hutang)';
			}
			$totalNet += $net;
			$row['net_hutang'] = $net;
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

/**
 * Invoice hutang dengan selisih antara sisa akuntansi vs sisa invoice.
 *
 * @return list<array<string, mixed>>
 */
function hutang_rekon_invoice_selisih(mysqli $conn, $cabang, $limit = 100)
{
	$cabang = (int) $cabang;
	$limit = max(1, min(500, (int) $limit));
	$out = [];

	$q = mysqli_query($conn, "
		SELECT
			ip.invoice_pembelian_id,
			ip.pembelian_invoice,
			ip.invoice_date,
			ip.invoice_hutang_jatuh_tempo,
			ip.invoice_total,
			ip.invoice_bayar,
			ip.invoice_hutang_dp,
			s.supplier_company,
			COALESCE(h.cicilan_total, 0) AS cicilan_total
		FROM invoice_pembelian ip
		LEFT JOIN supplier s ON ip.invoice_supplier = s.supplier_id
		LEFT JOIN (
			SELECT hutang_invoice_parent, hutang_cabang, SUM(hutang_nominal) AS cicilan_total
			FROM hutang
			GROUP BY hutang_invoice_parent, hutang_cabang
		) h ON h.hutang_invoice_parent = ip.pembelian_invoice_parent
			AND h.hutang_cabang = ip.invoice_pembelian_cabang
		WHERE ip.invoice_pembelian_cabang = $cabang
		  AND ip.invoice_hutang = 1
		ORDER BY ABS(
			(GREATEST(ip.invoice_total - COALESCE(ip.invoice_hutang_dp, 0), 0) - COALESCE(h.cicilan_total, 0))
			- GREATEST(ip.invoice_total - ip.invoice_bayar, 0)
		) DESC,
		ip.invoice_date DESC
		LIMIT $limit
	");

	if (!$q) {
		return $out;
	}

	while ($row = mysqli_fetch_assoc($q)) {
		$total = (float) ($row['invoice_total'] ?? 0);
		$bayar = (float) ($row['invoice_bayar'] ?? 0);
		$dp = (float) ($row['invoice_hutang_dp'] ?? 0);
		$cicilan = (float) ($row['cicilan_total'] ?? 0);
		$posted = max(0.0, $total - $dp);
		$sisaAkuntansi = $posted - $cicilan;
		$sisaInvoice = max(0.0, $total - $bayar);
		$selisih = $sisaAkuntansi - $sisaInvoice;
		$status = 'belum_lunas';
		if ($bayar >= $total && $total > 0) {
			$status = 'lunas';
		}

		if (abs($selisih) < 0.01 && $status === 'belum_lunas') {
			continue;
		}
		if (abs($selisih) < 0.01 && $status === 'lunas' && abs($sisaAkuntansi) < 0.01) {
			continue;
		}

		$row['posted_awal'] = $posted;
		$row['sisa_akuntansi'] = $sisaAkuntansi;
		$row['sisa_invoice'] = $sisaInvoice;
		$row['selisih'] = $selisih;
		$row['status'] = $status;
		$out[] = $row;
	}

	return $out;
}

/**
 * Invoice sudah lunas di sistem tapi sisa akuntansi masih > 0.
 *
 * @return list<array<string, mixed>>
 */
function hutang_rekon_invoice_lunas_sisa_akun(mysqli $conn, $cabang, $limit = 50)
{
	$cabang = (int) $cabang;
	$limit = max(1, min(200, (int) $limit));
	$out = [];

	$q = mysqli_query($conn, "
		SELECT
			ip.invoice_pembelian_id,
			ip.pembelian_invoice,
			ip.invoice_date,
			ip.invoice_total,
			ip.invoice_bayar,
			ip.invoice_hutang_dp,
			s.supplier_company,
			COALESCE(h.cicilan_total, 0) AS cicilan_total
		FROM invoice_pembelian ip
		LEFT JOIN supplier s ON ip.invoice_supplier = s.supplier_id
		LEFT JOIN (
			SELECT hutang_invoice_parent, hutang_cabang, SUM(hutang_nominal) AS cicilan_total
			FROM hutang
			GROUP BY hutang_invoice_parent, hutang_cabang
		) h ON h.hutang_invoice_parent = ip.pembelian_invoice_parent
			AND h.hutang_cabang = ip.invoice_pembelian_cabang
		WHERE ip.invoice_pembelian_cabang = $cabang
		  AND ip.invoice_hutang = 1
		  AND ip.invoice_bayar >= ip.invoice_total
		  AND (
			GREATEST(ip.invoice_total - COALESCE(ip.invoice_hutang_dp, 0), 0) - COALESCE(h.cicilan_total, 0)
		  ) > 0.01
		ORDER BY (
			GREATEST(ip.invoice_total - COALESCE(ip.invoice_hutang_dp, 0), 0) - COALESCE(h.cicilan_total, 0)
		) DESC
		LIMIT $limit
	");

	if (!$q) {
		return $out;
	}

	while ($row = mysqli_fetch_assoc($q)) {
		$total = (float) ($row['invoice_total'] ?? 0);
		$dp = (float) ($row['invoice_hutang_dp'] ?? 0);
		$cicilan = (float) ($row['cicilan_total'] ?? 0);
		$row['sisa_akuntansi'] = max(0.0, $total - $dp) - $cicilan;
		$out[] = $row;
	}

	return $out;
}
