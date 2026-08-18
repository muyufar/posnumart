<?php
/**
 * Member lintas cabang untuk kasir.
 * Cash/Transfer: member semua toko (termasuk Nugrosir) boleh belanja.
 * Piutang: hanya member toko asal.
 */

if (!function_exists('customer_toko_nama_by_cabang')) {
	function customer_toko_nama_by_cabang($conn, $cabang)
	{
		static $cache = null;
		$cabang = (int) $cabang;

		if ($cache === null) {
			$cache = [];
			if ($conn instanceof mysqli) {
				$res = mysqli_query($conn, 'SELECT toko_cabang, toko_nama FROM toko');
				if ($res) {
					while ($row = mysqli_fetch_assoc($res)) {
						$nama = trim((string) ($row['toko_nama'] ?? ''));
						if ($nama !== '') {
							$cache[(int) $row['toko_cabang']] = $nama;
						}
					}
				}
			}
		}

		if (isset($cache[$cabang]) && $cache[$cabang] !== '') {
			return $cache[$cabang];
		}

		$fallback = [
			0 => 'Nugrosir',
			1 => 'Numart Dukun',
			2 => 'Numart Pakis',
			3 => 'Numart Srumbung',
			4 => 'Baqnu',
			5 => 'Numart Tegalrejo',
			6 => 'Returan',
		];

		return $fallback[$cabang] ?? ('Cabang ' . $cabang);
	}
}

if (!function_exists('beli_langsung_customer_row')) {
	function beli_langsung_customer_row($conn, $customerId)
	{
		$customerId = (int) $customerId;
		if ($customerId < 1 || !($conn instanceof mysqli)) {
			return null;
		}

		$stmt = mysqli_prepare(
			$conn,
			'SELECT * FROM customer WHERE customer_id = ? AND customer_status = 1 LIMIT 1'
		);
		if (!$stmt) {
			return null;
		}
		mysqli_stmt_bind_param($stmt, 'i', $customerId);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);
		$row = $result ? mysqli_fetch_assoc($result) : null;
		mysqli_stmt_close($stmt);

		return $row ?: null;
	}
}

if (!function_exists('beli_langsung_customer_nama')) {
	/** Nama customer untuk tampilan (0 / tidak ditemukan = Umum). $cabang tetap ada agar pemanggil lama tidak rusak. */
	function beli_langsung_customer_nama($conn, $customerId, $cabang = 0)
	{
		$customerId = (int) $customerId;
		if ($customerId < 1) {
			return 'Umum';
		}
		$row = beli_langsung_customer_row($conn, $customerId);
		$nama = trim((string) ($row['customer_nama'] ?? ''));
		return $nama !== '' ? $nama : 'Umum';
	}
}

if (!function_exists('beli_langsung_customer_valid')) {
	/** Customer aktif dan tipenya cocok. Tidak diikat cabang kasir (member lintas toko). */
	function beli_langsung_customer_valid($conn, $customerId, $tipeHarga, $cabang = 0)
	{
		$customerId = (int) $customerId;
		$tipeHarga = (int) $tipeHarga;
		if ($customerId < 1) {
			return true;
		}
		$row = beli_langsung_customer_row($conn, $customerId);
		if (!$row) {
			return false;
		}
		return (int) ($row['customer_category'] ?? -1) === $tipeHarga;
	}
}

if (!function_exists('beli_langsung_customer_boleh_piutang')) {
	function beli_langsung_customer_boleh_piutang($conn, $customerId, $cabangKasir)
	{
		$customerId = (int) $customerId;
		$cabangKasir = (int) $cabangKasir;
		if ($customerId < 1) {
			return true;
		}
		$row = beli_langsung_customer_row($conn, $customerId);
		if (!$row) {
			return false;
		}
		return (int) ($row['customer_cabang'] ?? -1) === $cabangKasir;
	}
}

if (!function_exists('beli_langsung_customer_label')) {
	function beli_langsung_customer_label(array $row, $cabangKasir, $conn = null)
	{
		$nama = trim((string) ($row['customer_nama'] ?? ''));
		$kartu = trim((string) ($row['customer_kartu'] ?? ''));
		$label = $nama !== '' ? $nama : 'Tanpa Nama';
		if ($kartu !== '') {
			$label .= ' (' . $kartu . ')';
		}

		$asal = (int) ($row['customer_cabang'] ?? -1);
		if ($asal !== (int) $cabangKasir) {
			$toko = customer_toko_nama_by_cabang($conn, $asal);
			$label .= ' · ' . $toko;
		}

		return $label;
	}
}

if (!function_exists('beli_langsung_customer_option_tag')) {
	function beli_langsung_customer_option_tag(array $row, $cabangKasir, $conn = null, $selected = false)
	{
		$id = (int) ($row['customer_id'] ?? 0);
		if ($id < 1) {
			return '';
		}
		$asal = (int) ($row['customer_cabang'] ?? -1);
		$boleh = $asal === (int) $cabangKasir ? '1' : '0';
		$label = htmlspecialchars(beli_langsung_customer_label($row, $cabangKasir, $conn), ENT_QUOTES, 'UTF-8');
		$sel = $selected ? ' selected' : '';

		return '<option value="' . $id . '" data-cabang="' . $asal . '" data-boleh-piutang="' . $boleh . '"' . $sel . '>'
			. $label
			. '</option>';
	}
}

if (!function_exists('beli_langsung_assert_customer_transaksi')) {
	/**
	 * Validasi customer saat simpan penjualan/draft.
	 * customer_id 0 (Umum) dan 1 (marketplace) mengikuti perilaku lama.
	 */
	function beli_langsung_assert_customer_transaksi($conn, $customerId, $tipeHarga, $cabangKasir, $piutang)
	{
		$customerId = (int) $customerId;
		$tipeHarga = (int) $tipeHarga;
		$cabangKasir = (int) $cabangKasir;
		$piutang = (int) $piutang;

		if ($customerId <= 1) {
			return true;
		}

		if (!beli_langsung_customer_valid($conn, $customerId, $tipeHarga, $cabangKasir)) {
			$_SESSION['beli_langsung_alert'] = 'Customer tidak valid untuk tipe harga ini.';
			return false;
		}

		if ($piutang === 1 && !beli_langsung_customer_boleh_piutang($conn, $customerId, $cabangKasir)) {
			$tokoAsal = '';
			$row = beli_langsung_customer_row($conn, $customerId);
			if ($row) {
				$tokoAsal = customer_toko_nama_by_cabang($conn, (int) ($row['customer_cabang'] ?? -1));
			}
			$_SESSION['beli_langsung_alert'] = 'Piutang hanya boleh di toko asal member'
				. ($tokoAsal !== '' ? ' (' . $tokoAsal . ')' : '')
				. '. Gunakan Cash/Transfer untuk belanja di toko ini.';
			return false;
		}

		return true;
	}
}

if (!function_exists('beli_langsung_customer_search')) {
	/**
	 * Cari member untuk Select2 kasir.
	 * Query kosong / keyword: semua toko (toko kasir diurut di atas).
	 * Mode piutang: hanya toko kasir.
	 *
	 * @return list<array{id:int,text:string,cabang:int,boleh_piutang:bool}>
	 */
	function beli_langsung_customer_search($conn, $tipeHarga, $cabangKasir, $q, $piutangOnlyLocal, $limit = 40)
	{
		$tipeHarga = (int) $tipeHarga;
		$cabangKasir = (int) $cabangKasir;
		$piutangOnlyLocal = (bool) $piutangOnlyLocal;
		$limit = max(1, min(200, (int) $limit));
		$q = trim((string) $q);
		if ($q === 'Umum' || $q === '-- Pilih Customer --') {
			$q = '';
		}
		$out = [];

		if (!($conn instanceof mysqli)) {
			return $out;
		}

		$sql = "SELECT customer_id, customer_nama, customer_kartu, customer_tlpn, customer_cabang, customer_category
			FROM customer
			WHERE customer_status = 1
			  AND customer_category = ?
			  AND customer_id > 1
			  AND customer_nama <> 'Customer Umum'";
		$types = 'i';
		$params = [$tipeHarga];

		if ($piutangOnlyLocal) {
			$sql .= ' AND customer_cabang = ?';
			$types .= 'i';
			$params[] = $cabangKasir;
		}

		if ($q !== '') {
			$sql .= ' AND (
				customer_nama LIKE ?
				OR customer_kartu LIKE ?
				OR customer_tlpn LIKE ?
			)';
			$like = '%' . $q . '%';
			$types .= 'sss';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$sql .= ' ORDER BY (customer_cabang = ?) DESC, customer_id DESC LIMIT ' . $limit;
		$types .= 'i';
		$params[] = $cabangKasir;

		$stmt = mysqli_prepare($conn, $sql);
		if (!$stmt) {
			return $out;
		}

		mysqli_stmt_bind_param($stmt, $types, ...$params);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);
		if ($result) {
			while ($row = mysqli_fetch_assoc($result)) {
				$asal = (int) ($row['customer_cabang'] ?? -1);
				$out[] = [
					'id' => (int) $row['customer_id'],
					'text' => beli_langsung_customer_label($row, $cabangKasir, $conn),
					'cabang' => $asal,
					'boleh_piutang' => $asal === $cabangKasir,
				];
			}
		}
		mysqli_stmt_close($stmt);

		return $out;
	}
}

if (!function_exists('beli_langsung_customer_local_options_html')) {
	/**
	 * Opsi member untuk dropdown kasir.
	 * Cash/Transfer ($semuaCabang=true): semua toko, toko kasir di atas.
	 * Piutang ($semuaCabang=false): hanya toko kasir.
	 */
	function beli_langsung_customer_local_options_html($conn, $tipeHarga, $cabangKasir, $selectedId, $semuaCabang = false)
	{
		$tipeHarga = (int) $tipeHarga;
		$cabangKasir = (int) $cabangKasir;
		$selectedId = (int) $selectedId;
		$semuaCabang = (bool) $semuaCabang;
		$html = '';
		if (!($conn instanceof mysqli)) {
			return $html;
		}

		$sql = "SELECT customer_id, customer_nama, customer_kartu, customer_tlpn, customer_cabang, customer_category
			 FROM customer
			 WHERE customer_status = 1
			   AND customer_category = ?
			   AND customer_id > 1
			   AND customer_nama <> 'Customer Umum'";
		$types = 'i';
		$params = [$tipeHarga];
		if (!$semuaCabang) {
			$sql .= ' AND customer_cabang = ?';
			$types .= 'i';
			$params[] = $cabangKasir;
		}
		$sql .= ' ORDER BY (customer_cabang = ?) DESC, customer_id DESC';
		$types .= 'i';
		$params[] = $cabangKasir;

		$stmt = mysqli_prepare($conn, $sql);
		if (!$stmt) {
			return $html;
		}
		mysqli_stmt_bind_param($stmt, $types, ...$params);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);
		if ($result) {
			while ($row = mysqli_fetch_assoc($result)) {
				if ((int) ($row['customer_id'] ?? 0) === $selectedId) {
					continue;
				}
				$html .= beli_langsung_customer_option_tag($row, $cabangKasir, $conn, false);
			}
		}
		mysqli_stmt_close($stmt);

		return $html;
	}
}
