<?php

require_once __DIR__ . '/pengadaan-po-lib.php';
require_once __DIR__ . '/pengadaan-gudang-lib.php';

/**
 * Baris PO siap dialokasikan (qty diterima > 0) + meta barang gudang.
 *
 * @return list<array<string,mixed>>
 */
function pengadaan_po_alokasi_lines(mysqli $conn, int $poId): array
{
	$poId = (int) $poId;
	$lines = pengadaan_po_get_lines($conn, $poId);
	$out = [];
	foreach ($lines as $ln) {
		$qty = (float) ($ln['qty_received'] ?? 0);
		if ($qty <= 0) {
			$qty = (float) ($ln['qty_po'] ?? 0);
		}
		if ($qty <= 0) {
			continue;
		}
		$barangId = (int) ($ln['barang_id'] ?? 0);
		$kode = trim((string) ($ln['barang_kode'] ?? ''));
		$meta = null;
		if ($barangId > 0) {
			$res = mysqli_query($conn, "
				SELECT barang_id, barang_kode, barang_kode_slug, barang_nama, barang_option_sn, barang_stock, barang_cabang
				FROM barang
				WHERE barang_id = $barangId AND barang_status = '1'
				LIMIT 1
			");
			$meta = $res ? mysqli_fetch_assoc($res) : null;
		}
		if (!$meta && $kode !== '') {
			$kodeEsc = mysqli_real_escape_string($conn, $kode);
			$res = mysqli_query($conn, "
				SELECT barang_id, barang_kode, barang_kode_slug, barang_nama, barang_option_sn, barang_stock, barang_cabang
				FROM barang
				WHERE barang_kode = '$kodeEsc' AND barang_cabang = 0 AND barang_status = '1'
				LIMIT 1
			");
			$meta = $res ? mysqli_fetch_assoc($res) : null;
		}
		$slug = $meta ? trim((string) ($meta['barang_kode_slug'] ?? '')) : '';
		$out[] = [
			'line_id' => (int) ($ln['id'] ?? 0),
			'barang_id' => $meta ? (int) $meta['barang_id'] : $barangId,
			'barang_kode' => $kode !== '' ? $kode : (string) ($meta['barang_kode'] ?? ''),
			'barang_kode_slug' => $slug,
			'barang_nama' => (string) ($ln['barang_nama'] ?? ($meta['barang_nama'] ?? '')),
			'satuan_nama' => (string) ($ln['satuan_nama'] ?? 'PCS'),
			'qty_tersedia' => $qty,
			'stok_gudang' => $meta ? (float) ($meta['barang_stock'] ?? 0) : 0,
			'option_sn' => $meta ? (int) ($meta['barang_option_sn'] ?? 0) : 0,
		];
	}

	return $out;
}

function pengadaan_po_alokasi_next_ref(mysqli $conn, int $pengirimCabang = 0): array
{
	$pengirimCabang = (int) $pengirimCabang;
	$res = mysqli_query($conn, "
		SELECT transfer_count FROM transfer
		WHERE transfer_pengirim_cabang = $pengirimCabang
		ORDER BY transfer_id DESC LIMIT 1
	");
	$row = $res ? mysqli_fetch_assoc($res) : null;
	$count = $row ? ((int) ($row['transfer_count'] ?? 0) + 1) : 1;
	$today = date('Ymd');
	$ref = $today . $pengirimCabang . $count;

	return ['ref' => $ref, 'count' => $count];
}

/**
 * Buat transfer stock dari gudang ke toko sesuai plot alokasi.
 *
 * $allocations: [ line_id => [ cabang_id => qty, ... ], ... ]
 *
 * @return array{ok:bool,message:string,transfer_refs?:list<string>,errors?:list<string>}
 */
function pengadaan_po_alokasi_submit(
	mysqli $conn,
	int $poId,
	int $userId,
	array $allocations,
	bool $autoConfirm = false
): array {
	pengadaan_po_ensure_tables($conn);
	$poId = (int) $poId;
	$userId = (int) $userId;
	$po = pengadaan_po_get($conn, $poId);
	if (!$po) {
		return ['ok' => false, 'message' => 'PO tidak ditemukan'];
	}
	if (!in_array((string) ($po['status'] ?? ''), ['diterima', 'selesai'], true)) {
		return ['ok' => false, 'message' => 'Selesaikan pembelian PO dulu sebelum alokasi transfer'];
	}

	$lines = pengadaan_po_alokasi_lines($conn, $poId);
	if ($lines === []) {
		return ['ok' => false, 'message' => 'Tidak ada barang PO untuk dialokasikan'];
	}
	$byLine = [];
	foreach ($lines as $ln) {
		$byLine[(int) $ln['line_id']] = $ln;
	}

	$cabangToko = pengadaan_gudang_cabang_toko();
	$byDest = []; // cabang => list of items
	$errors = [];

	foreach ($allocations as $lineId => $perCabang) {
		$lineId = (int) $lineId;
		if (!isset($byLine[$lineId]) || !is_array($perCabang)) {
			continue;
		}
		$ln = $byLine[$lineId];
		if ((int) ($ln['option_sn'] ?? 0) > 0) {
			$errors[] = ($ln['barang_nama'] ?? '') . ': barang SN — alokasi lewat Transfer Stock manual';
			continue;
		}
		$sum = 0.0;
		foreach ($perCabang as $cab => $qty) {
			$cab = (int) $cab;
			$qty = (float) $qty;
			if ($qty <= 0 || !isset($cabangToko[$cab])) {
				continue;
			}
			$sum += $qty;
			$slug = (string) ($ln['barang_kode_slug'] ?? '');
			$kode = (string) ($ln['barang_kode'] ?? '');
			if ($slug === '') {
				$errors[] = ($ln['barang_nama'] ?? $kode) . ': slug barang kosong';
				continue;
			}
			// Pastikan produk ada di cabang tujuan
			$slugEsc = mysqli_real_escape_string($conn, $slug);
			$cekTujuan = mysqli_query($conn, "
				SELECT barang_id FROM barang
				WHERE barang_kode_slug = '$slugEsc' AND barang_cabang = $cab AND barang_status = '1'
				LIMIT 1
			");
			if (!$cekTujuan || mysqli_num_rows($cekTujuan) < 1) {
				$errors[] = ($ln['barang_nama'] ?? $kode) . ' tidak ada di ' . ($cabangToko[$cab] ?? ('Cabang ' . $cab));
				continue;
			}
			$byDest[$cab][] = [
				'barang_id' => (int) $ln['barang_id'],
				'barang_kode_slug' => $slug,
				'barang_nama' => (string) $ln['barang_nama'],
				'qty' => $qty,
			];
		}
		$maxQty = (float) $ln['qty_tersedia'];
		if ($sum > $maxQty + 0.0001) {
			$errors[] = ($ln['barang_nama'] ?? '') . ': total alokasi (' . $sum . ') melebihi qty PO (' . $maxQty . ')';
		}
	}

	if ($byDest === []) {
		return [
			'ok' => false,
			'message' => $errors !== []
				? implode('; ', $errors)
				: 'Isi qty transfer ke minimal 1 toko',
			'errors' => $errors,
		];
	}
	if ($errors !== []) {
		// Hard-fail jika ada over-qty / missing product
		foreach ($errors as $e) {
			if (stripos($e, 'melebihi') !== false || stripos($e, 'tidak ada di') !== false) {
				return ['ok' => false, 'message' => implode('; ', $errors), 'errors' => $errors];
			}
		}
	}

	$poNumber = (string) ($po['po_number'] ?? ('PO-' . $poId));
	$note = 'Alokasi dari ' . $poNumber;
	$noteEsc = mysqli_real_escape_string($conn, $note);
	$date = date('Y-m-d');
	$dateTime = date('d F Y g:i:s a');
	$dateTimeEsc = mysqli_real_escape_string($conn, $dateTime);
	$refs = [];

	mysqli_begin_transaction($conn);
	try {
		foreach ($byDest as $destCabang => $items) {
			$destCabang = (int) $destCabang;
			$metaRef = pengadaan_po_alokasi_next_ref($conn, 0);
			$ref = $metaRef['ref'];
			$count = (int) $metaRef['count'];
			// Pastikan unik
			$refEsc = mysqli_real_escape_string($conn, $ref);
			$dup = mysqli_query($conn, "SELECT transfer_id FROM transfer WHERE transfer_ref = '$refEsc' LIMIT 1");
			if ($dup && mysqli_num_rows($dup) > 0) {
				$metaRef = pengadaan_po_alokasi_next_ref($conn, 0);
				$ref = $metaRef['ref'] . 'A' . $destCabang;
				$count = (int) $metaRef['count'];
				$refEsc = mysqli_real_escape_string($conn, $ref);
			}

			$status = $autoConfirm ? 2 : 1;
			$terimaDate = $autoConfirm ? $date : '';
			$terimaDateTime = $autoConfirm ? $dateTimeEsc : '';
			$userPenerima = $autoConfirm ? $userId : 0;

			$ok = mysqli_query($conn, "
				INSERT INTO transfer (
					transfer_ref, transfer_count, transfer_date, transfer_date_time,
					transfer_terima_date, transfer_terima_date_time, transfer_note,
					transfer_pengirim_cabang, transfer_penerima_cabang,
					transfer_id_tipe_keluar, transfer_id_tipe_masuk,
					transfer_status, transfer_user, transfer_user_penerima, transfer_cabang
				) VALUES (
					'$refEsc', $count, '$date', '$dateTimeEsc',
					'$terimaDate', '$terimaDateTime', '$noteEsc',
					0, $destCabang,
					0, $destCabang,
					$status, $userId, $userPenerima, 0
				)
			");
			if (!$ok) {
				throw new RuntimeException('Gagal buat transfer ke cabang ' . $destCabang . ': ' . mysqli_error($conn));
			}

			foreach ($items as $it) {
				$bid = (int) $it['barang_id'];
				$slugEsc = mysqli_real_escape_string($conn, (string) $it['barang_kode_slug']);
				$qty = (float) $it['qty'];
				$okLine = mysqli_query($conn, "
					INSERT INTO transfer_produk_keluar (
						tpk_transfer_barang_id, tpk_barang_id, tpk_kode_slug, tpk_qty, tpk_ref,
						tpk_date, tpk_date_time, tpk_barang_option_sn, tpk_barang_sn_id, tpk_barang_sn_desc,
						tpk_user, tpk_pengirim_cabang, tpk_penerima_cabang, tpk_cabang
					) VALUES (
						$bid, $bid, '$slugEsc', $qty, '$refEsc',
						'$date', '$dateTimeEsc', 0, 0, '0',
						$userId, 0, $destCabang, 0
					)
				");
				if (!$okLine) {
					throw new RuntimeException('Gagal insert line transfer: ' . mysqli_error($conn));
				}

				if ($autoConfirm) {
					$okMasuk = mysqli_query($conn, "
						INSERT INTO transfer_produk_masuk (
							tpm_kode_slug, tpm_qty, tpm_ref, tpm_date, tpm_date_time,
							tpm_barang_option_sn, tpm_barang_sn_id, tpm_barang_sn_desc,
							tpm_user, tpm_pengirim_cabang, tpm_penerima_cabang, tpm_cabang
						) VALUES (
							'$slugEsc', $qty, '$refEsc', '$date', '$dateTimeEsc',
							0, 0, '0',
							$userId, 0, $destCabang, $destCabang
						)
					");
					if (!$okMasuk) {
						throw new RuntimeException('Gagal konfirmasi masuk: ' . mysqli_error($conn));
					}
				}
			}
			$refs[] = $ref;
		}

		@mysqli_query($conn, "
			UPDATE pengadaan_po SET
				alokasi_at = NOW(),
				alokasi_by = $userId,
				updated_at = NOW()
			WHERE id = $poId
		");

		mysqli_commit($conn);
	} catch (Throwable $e) {
		mysqli_rollback($conn);

		return ['ok' => false, 'message' => $e->getMessage(), 'errors' => $errors];
	}

	$msg = 'Berhasil buat ' . count($refs) . ' transfer: ' . implode(', ', $refs);
	if ($autoConfirm) {
		$msg .= ' (langsung dikonfirmasi — stok toko bertambah)';
	} else {
		$msg .= '. Cabang penerima tinggal konfirmasi di Transfer Stock Masuk.';
	}

	return [
		'ok' => true,
		'message' => $msg,
		'transfer_refs' => $refs,
		'errors' => $errors,
	];
}

function pengadaan_po_transfer_status_badge(int $status): string
{
	$map = [
		1 => '<span class="badge badge-info">Dikirim</span>',
		2 => '<span class="badge badge-success">Selesai</span>',
		3 => '<span class="badge badge-dark">Batal</span>',
		0 => '<span class="badge badge-secondary">Draft/Batal</span>',
	];

	return $map[$status] ?? ('<span class="badge badge-light">' . (int) $status . '</span>');
}

/**
 * Transfer yang terkait PO (dicocokkan dari catatan "Alokasi dari {po_number}").
 *
 * @return list<array<string,mixed>>
 */
function pengadaan_po_transfers_for_po(mysqli $conn, string $poNumber): array
{
	$poNumber = trim($poNumber);
	if ($poNumber === '') {
		return [];
	}
	$like = '%' . mysqli_real_escape_string($conn, $poNumber) . '%';
	$list = [];
	$res = mysqli_query($conn, "
		SELECT t.*,
			(SELECT COUNT(*) FROM transfer_produk_keluar k WHERE k.tpk_ref = t.transfer_ref) AS jml_item,
			(SELECT COALESCE(SUM(k.tpk_qty), 0) FROM transfer_produk_keluar k WHERE k.tpk_ref = t.transfer_ref) AS total_qty
		FROM transfer t
		WHERE t.transfer_note LIKE '$like'
		ORDER BY t.transfer_id DESC
	");
	while ($res && ($row = mysqli_fetch_assoc($res))) {
		$cab = (int) ($row['transfer_penerima_cabang'] ?? 0);
		$row['penerima_label'] = pengadaan_gudang_cabang_label($cab);
		$row['status_badge'] = pengadaan_po_transfer_status_badge((int) ($row['transfer_status'] ?? 0));
		$list[] = $row;
	}

	return $list;
}

/**
 * Ringkas tujuan transfer per PO: "Dukun (12), Pakis (5)".
 */
function pengadaan_po_transfers_summary_html(mysqli $conn, string $poNumber): string
{
	$transfers = pengadaan_po_transfers_for_po($conn, $poNumber);
	if ($transfers === []) {
		return '<span class="text-muted">Belum ada transfer</span>';
	}
	$byCab = [];
	foreach ($transfers as $t) {
		$label = (string) ($t['penerima_label'] ?? 'Toko');
		$qty = (float) ($t['total_qty'] ?? 0);
		if (!isset($byCab[$label])) {
			$byCab[$label] = ['qty' => 0.0, 'refs' => 0, 'status' => (int) ($t['transfer_status'] ?? 0)];
		}
		$byCab[$label]['qty'] += $qty;
		$byCab[$label]['refs']++;
		// status terburuk: jika ada yang belum selesai, tandai info
		if ((int) ($t['transfer_status'] ?? 0) !== 2) {
			$byCab[$label]['status'] = (int) ($t['transfer_status'] ?? 0);
		}
	}
	$parts = [];
	foreach ($byCab as $label => $info) {
		$badgeClass = ((int) $info['status'] === 2) ? 'badge-success' : 'badge-info';
		$parts[] = '<span class="badge ' . $badgeClass . ' mr-1 mb-1">'
			. htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
			. ' · ' . number_format((float) $info['qty'], 0, ',', '.')
			. '</span>';
	}

	return implode(' ', $parts);
}

/**
 * Daftar PO yang sudah transaksi pembelian (riwayat + tujuan transfer).
 *
 * @return array{rows:list<array<string,mixed>>, total:int, filtered:int}
 */
function pengadaan_po_list_riwayat(
	mysqli $conn,
	string $search = '',
	string $filterTransfer = 'semua',
	int $start = 0,
	int $length = 25
): array {
	pengadaan_po_ensure_tables($conn);
	$start = max(0, $start);
	$length = $length < 1 ? 25 : min(100, $length);

	$where = " WHERE (p.status = 'selesai' OR (p.pembelian_invoice_parent IS NOT NULL AND TRIM(p.pembelian_invoice_parent) != '')) ";
	if ($search !== '') {
		$like = mysqli_real_escape_string($conn, $search);
		$where .= " AND (
			p.po_number LIKE '%$like%'
			OR p.kode_suplier LIKE '%$like%'
			OR p.pembelian_invoice_parent LIKE '%$like%'
		) ";
	}

	// Filter punya/belum transfer — pakai subquery note
	if ($filterTransfer === 'sudah') {
		$where .= " AND EXISTS (
			SELECT 1 FROM transfer t
			WHERE t.transfer_note LIKE CONCAT('%', p.po_number, '%')
		) ";
	} elseif ($filterTransfer === 'belum') {
		$where .= " AND NOT EXISTS (
			SELECT 1 FROM transfer t
			WHERE t.transfer_note LIKE CONCAT('%', p.po_number, '%')
		) ";
	}

	$resTotal = mysqli_query($conn, "
		SELECT COUNT(*) AS c FROM pengadaan_po p
		WHERE (p.status = 'selesai' OR (p.pembelian_invoice_parent IS NOT NULL AND TRIM(p.pembelian_invoice_parent) != ''))
	");
	$total = $resTotal ? (int) (mysqli_fetch_assoc($resTotal)['c'] ?? 0) : 0;

	$resFiltered = mysqli_query($conn, "SELECT COUNT(*) AS c FROM pengadaan_po p $where");
	$filtered = $resFiltered ? (int) (mysqli_fetch_assoc($resFiltered)['c'] ?? 0) : 0;

	$sql = "
		SELECT p.*,
			(SELECT COUNT(*) FROM pengadaan_po_line l WHERE l.po_id = p.id) AS jml_item,
			(SELECT COALESCE(SUM(l.qty_received), 0) FROM pengadaan_po_line l WHERE l.po_id = p.id) AS total_qty_received
		FROM pengadaan_po p
		$where
		ORDER BY p.id DESC
		LIMIT $start, $length
	";
	$res = mysqli_query($conn, $sql);
	$rows = [];
	while ($res && ($row = mysqli_fetch_assoc($res))) {
		$poNumber = (string) ($row['po_number'] ?? '');
		$row['transfers'] = pengadaan_po_transfers_for_po($conn, $poNumber);
		$row['transfer_summary_html'] = pengadaan_po_transfers_summary_html($conn, $poNumber);
		$rows[] = $row;
	}

	return ['rows' => $rows, 'total' => $total, 'filtered' => $filtered];
}
