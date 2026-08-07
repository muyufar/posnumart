<?php
/**
 * Fitur ubah barcode barang — terisolasi dari alur edit barang biasa.
 *
 * Semua perubahan dijalankan dalam 1 transaksi MySQL.
 * Jika ada error di salah satu tabel terkait → ROLLBACK penuh,
 * sehingga fitur lain / data operasional tidak ikut rusak setengah jalan.
 */

if (!function_exists('barang_kode_to_slug')) {
	/**
	 * Slug harus konsisten dengan create/add barang di functions.php:
	 * str_replace(' ', '-', $barang_kode)
	 */
	function barang_kode_to_slug(string $kode): string
	{
		return str_replace(' ', '-', trim($kode));
	}
}

if (!function_exists('bub_table_exists')) {
	function bub_table_exists(mysqli $conn, string $table): bool
	{
		$esc = mysqli_real_escape_string($conn, $table);
		$res = @mysqli_query($conn, "SHOW TABLES LIKE '$esc'");
		return $res && mysqli_num_rows($res) > 0;
	}
}

if (!function_exists('bub_column_exists')) {
	function bub_column_exists(mysqli $conn, string $table, string $column): bool
	{
		if (!bub_table_exists($conn, $table)) {
			return false;
		}
		$t = mysqli_real_escape_string($conn, $table);
		$c = mysqli_real_escape_string($conn, $column);
		$res = @mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
		return $res && mysqli_num_rows($res) > 0;
	}
}

if (!function_exists('bub_normalize_kode')) {
	/**
	 * Normalisasi input barcode: trim, tolak karakter kontrol.
	 * Mengembalikan string kosong jika tidak valid.
	 */
	function bub_normalize_kode(string $kode): string
	{
		// Tolak karakter kontrol dulu (trim bawaan PHP juga menghapus \0).
		if (strpos($kode, "\0") !== false || preg_match('/[\x01-\x1F\x7F]/', $kode)) {
			return '';
		}
		$kode = trim($kode);
		if ($kode === '') {
			return '';
		}
		if (strlen($kode) > 100) {
			return '';
		}
		return $kode;
	}
}

if (!function_exists('bub_ensure_log_table')) {
	/**
	 * Buat tabel log di luar transaksi rename (CREATE menyebabkan implicit commit).
	 */
	function bub_ensure_log_table(mysqli $conn): bool
	{
		$sql = "CREATE TABLE IF NOT EXISTS barang_barcode_ubah_log (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			kode_lama VARCHAR(100) NOT NULL,
			kode_baru VARCHAR(100) NOT NULL,
			slug_lama VARCHAR(100) NOT NULL,
			slug_baru VARCHAR(100) NOT NULL,
			user_id INT NULL,
			user_nama VARCHAR(150) NULL,
			cabang_count INT NOT NULL DEFAULT 0,
			detail_json MEDIUMTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_bub_kode_lama (kode_lama),
			KEY idx_bub_kode_baru (kode_baru),
			KEY idx_bub_created (created_at)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
		return (bool) @mysqli_query($conn, $sql);
	}
}

if (!function_exists('bub_cascade_targets')) {
	/**
	 * Daftar tabel/kolom yang menyimpan salinan barcode atau slug.
	 * Hanya yang ada di DB yang akan di-update (dicek saat runtime).
	 *
	 * @return list<array{table:string, column:string, match:string}>
	 *   match: 'kode' | 'slug' | 'kode_or_slug'
	 */
	function bub_cascade_targets(): array
	{
		return [
			['table' => 'barang_sn', 'column' => 'barang_kode_slug', 'match' => 'slug'],
			['table' => 'keranjang', 'column' => 'barang_kode_slug', 'match' => 'slug'],
			['table' => 'keranjang_draft', 'column' => 'barang_kode_slug', 'match' => 'slug'],
			['table' => 'keranjang_transfer', 'column' => 'barang_kode_slug', 'match' => 'slug'],
			['table' => 'transfer_produk_masuk', 'column' => 'tpm_kode_slug', 'match' => 'slug'],
			['table' => 'transfer_produk_keluar', 'column' => 'tpk_kode_slug', 'match' => 'slug'],
			['table' => 'stock_opname_hasil', 'column' => 'soh_barang_kode', 'match' => 'kode_or_slug'],
			['table' => 'pengadaan_po_line', 'column' => 'barang_kode', 'match' => 'kode'],
			['table' => 'pengadaan_request', 'column' => 'barang_kode', 'match' => 'kode'],
			['table' => 'marketplace_diskon', 'column' => 'barang_kode', 'match' => 'kode'],
			['table' => 'marketplace_mapping', 'column' => 'kode_barang', 'match' => 'kode'],
			// Legacy / instalasi lama — hanya jika kolom ada.
			['table' => 'penjualan', 'column' => 'penjualan_kode', 'match' => 'kode'],
		];
	}
}

if (!function_exists('barang_ubah_barcode_preview')) {
	/**
	 * Preview dampak sebelum eksekusi (read-only, tanpa transaksi tulis).
	 *
	 * @return array{ok:bool, msg:string, data?:array}
	 */
	function barang_ubah_barcode_preview(mysqli $conn, string $kodeLama): array
	{
		$kodeLama = bub_normalize_kode($kodeLama);
		if ($kodeLama === '') {
			return ['ok' => false, 'msg' => 'Barcode lama tidak valid.'];
		}

		$slugLamaExpected = barang_kode_to_slug($kodeLama);
		$kEsc = mysqli_real_escape_string($conn, $kodeLama);
		$sEsc = mysqli_real_escape_string($conn, $slugLamaExpected);

		$q = mysqli_query(
			$conn,
			"SELECT barang_id, barang_kode, barang_kode_slug, barang_nama, barang_cabang, barang_status, barang_stock
			 FROM barang
			 WHERE barang_kode = '$kEsc' OR barang_kode_slug = '$sEsc' OR barang_kode_slug = '$kEsc'
			 ORDER BY barang_cabang ASC"
		);
		if (!$q) {
			return ['ok' => false, 'msg' => 'Gagal membaca data barang: ' . mysqli_error($conn)];
		}

		$rows = [];
		$slugs = [];
		$kodes = [];
		while ($row = mysqli_fetch_assoc($q)) {
			$rows[] = $row;
			$slugs[(string) ($row['barang_kode_slug'] ?? '')] = true;
			$kodes[(string) ($row['barang_kode'] ?? '')] = true;
		}
		if ($rows === []) {
			return ['ok' => false, 'msg' => 'Barcode / kode lama tidak ditemukan di tabel barang.'];
		}

		$oldSlugs = array_values(array_filter(array_keys($slugs), static function ($s) {
			return $s !== '';
		}));
		if ($oldSlugs === []) {
			$oldSlugs = [$slugLamaExpected];
		}
		$oldKodes = array_values(array_filter(array_keys($kodes), static function ($s) {
			return $s !== '';
		}));
		if ($oldKodes === []) {
			$oldKodes = [$kodeLama];
		} elseif (!in_array($kodeLama, $oldKodes, true)) {
			$oldKodes[] = $kodeLama;
		}

		$impact = [];
		foreach (bub_cascade_targets() as $t) {
			if (!bub_column_exists($conn, $t['table'], $t['column'])) {
				continue;
			}
			$count = bub_count_matches($conn, $t['table'], $t['column'], $t['match'], $oldKodes, $oldSlugs);
			$impact[] = [
				'table' => $t['table'],
				'column' => $t['column'],
				'rows' => $count,
			];
		}

		$nama = (string) ($rows[0]['barang_nama'] ?? '');
		$kodeAktual = (string) ($rows[0]['barang_kode'] ?? $kodeLama);

		return [
			'ok' => true,
			'msg' => 'Data ditemukan.',
			'data' => [
				'kode_lama' => $kodeAktual,
				'slug_lama_list' => $oldSlugs,
				'barang_nama' => $nama,
				'cabang_count' => count($rows),
				'rows' => $rows,
				'impact' => $impact,
			],
		];
	}
}

if (!function_exists('bub_count_matches')) {
	/**
	 * @param list<string> $oldKodes
	 * @param list<string> $oldSlugs
	 */
	function bub_count_matches(mysqli $conn, string $table, string $column, string $match, array $oldKodes, array $oldSlugs): int
	{
		$where = bub_build_where($conn, $column, $match, $oldKodes, $oldSlugs);
		if ($where === '') {
			return 0;
		}
		$t = str_replace('`', '``', $table);
		$res = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM `$t` WHERE $where");
		if (!$res) {
			return 0;
		}
		$row = mysqli_fetch_assoc($res);
		return (int) ($row['c'] ?? 0);
	}
}

if (!function_exists('bub_sql_in_list')) {
	/**
	 * @param list<string> $values
	 */
	function bub_sql_in_list(mysqli $conn, array $values): string
	{
		$parts = [];
		$seen = [];
		foreach ($values as $v) {
			$v = trim((string) $v);
			if ($v === '' || isset($seen[$v])) {
				continue;
			}
			$seen[$v] = true;
			$parts[] = "'" . mysqli_real_escape_string($conn, $v) . "'";
		}
		return $parts === [] ? '' : implode(',', $parts);
	}
}

if (!function_exists('bub_build_where')) {
	/**
	 * @param list<string> $oldKodes
	 * @param list<string> $oldSlugs
	 */
	function bub_build_where(mysqli $conn, string $column, string $match, array $oldKodes, array $oldSlugs): string
	{
		$col = '`' . str_replace('`', '``', $column) . '`';
		$kodeIn = bub_sql_in_list($conn, $oldKodes);
		$slugIn = bub_sql_in_list($conn, $oldSlugs);

		if ($match === 'kode') {
			return $kodeIn === '' ? '' : "$col IN ($kodeIn)";
		}
		if ($match === 'slug') {
			return $slugIn === '' ? '' : "$col IN ($slugIn)";
		}
		// kode_or_slug
		$parts = [];
		if ($kodeIn !== '') {
			$parts[] = "$col IN ($kodeIn)";
		}
		if ($slugIn !== '') {
			$parts[] = "$col IN ($slugIn)";
		}
		return $parts === [] ? '' : '(' . implode(' OR ', $parts) . ')';
	}
}

if (!function_exists('barang_ubah_barcode_run')) {
	/**
	 * Rename barcode secara atomik di semua cabang + tabel terkait.
	 *
	 * @param array{user_id?:int,user_nama?:string} $opts
	 * @return array{ok:bool, msg:string, detail?:array}
	 */
	function barang_ubah_barcode_run(mysqli $conn, string $kodeLama, string $kodeBaru, array $opts = []): array
	{
		$kodeLama = bub_normalize_kode($kodeLama);
		$kodeBaru = bub_normalize_kode($kodeBaru);

		if ($kodeLama === '') {
			return ['ok' => false, 'msg' => 'Barcode lama tidak valid.'];
		}
		if ($kodeBaru === '') {
			return ['ok' => false, 'msg' => 'Barcode baru tidak valid.'];
		}
		if ($kodeLama === $kodeBaru) {
			return ['ok' => false, 'msg' => 'Barcode baru sama dengan barcode lama.'];
		}

		$slugBaru = barang_kode_to_slug($kodeBaru);
		$slugLamaExpected = barang_kode_to_slug($kodeLama);

		// Pastikan tabel log ada SEBELUM transaksi (CREATE = implicit commit).
		if (!bub_ensure_log_table($conn)) {
			return ['ok' => false, 'msg' => 'Gagal menyiapkan tabel log audit. Proses dibatalkan.'];
		}

		if (!mysqli_begin_transaction($conn)) {
			return ['ok' => false, 'msg' => 'Gagal memulai transaksi database. Tidak ada data yang diubah.'];
		}

		$detail = [
			'barang_updated' => 0,
			'cascade' => [],
			'old_slugs' => [],
		];

		try {
			$kEsc = mysqli_real_escape_string($conn, $kodeLama);
			$sEsc = mysqli_real_escape_string($conn, $slugLamaExpected);
			$kBaruEsc = mysqli_real_escape_string($conn, $kodeBaru);
			$sBaruEsc = mysqli_real_escape_string($conn, $slugBaru);

			// Kunci baris barang yang akan diubah.
			$lockQ = mysqli_query(
				$conn,
				"SELECT barang_id, barang_kode, barang_kode_slug, barang_cabang, barang_nama
				 FROM barang
				 WHERE barang_kode = '$kEsc' OR barang_kode_slug = '$sEsc' OR barang_kode_slug = '$kEsc'
				 FOR UPDATE"
			);
			if (!$lockQ) {
				throw new RuntimeException('Gagal mengunci data barang: ' . mysqli_error($conn));
			}

			$ids = [];
			$oldSlugs = [];
			$oldKodesMap = [];
			$kodeAktual = $kodeLama;
			while ($row = mysqli_fetch_assoc($lockQ)) {
				$ids[] = (int) $row['barang_id'];
				$slug = trim((string) ($row['barang_kode_slug'] ?? ''));
				if ($slug !== '') {
					$oldSlugs[$slug] = true;
				}
				$kodeRow = trim((string) ($row['barang_kode'] ?? ''));
				if ($kodeRow !== '') {
					$oldKodesMap[$kodeRow] = true;
					$kodeAktual = $kodeRow;
				}
			}

			if ($ids === []) {
				throw new RuntimeException('Barcode lama tidak ditemukan. Tidak ada data yang diubah.');
			}

			$oldSlugList = array_keys($oldSlugs);
			if ($oldSlugList === []) {
				$oldSlugList = [$slugLamaExpected];
			}
			$oldKodeList = array_keys($oldKodesMap);
			if ($oldKodeList === []) {
				$oldKodeList = [$kodeLama];
			} elseif (!in_array($kodeLama, $oldKodeList, true)) {
				$oldKodeList[] = $kodeLama;
			}
			$detail['old_slugs'] = $oldSlugList;
			$detail['old_kodes'] = $oldKodeList;
			$detail['kode_aktual'] = $kodeAktual;

			// Cek bentrok: kode/slug baru sudah dipakai barang lain.
			$idList = implode(',', array_map('intval', $ids));
			$conflict = mysqli_query(
				$conn,
				"SELECT barang_id, barang_kode, barang_kode_slug, barang_cabang, barang_nama
				 FROM barang
				 WHERE (barang_kode = '$kBaruEsc' OR barang_kode_slug = '$sBaruEsc' OR barang_kode_slug = '$kBaruEsc')
				   AND barang_id NOT IN ($idList)
				 LIMIT 5
				 FOR UPDATE"
			);
			if (!$conflict) {
				throw new RuntimeException('Gagal cek duplikasi barcode baru: ' . mysqli_error($conn));
			}
			if (mysqli_num_rows($conflict) > 0) {
				$c = mysqli_fetch_assoc($conflict);
				throw new RuntimeException(
					'Barcode baru sudah dipakai barang lain: '
					. (string) ($c['barang_nama'] ?? '')
					. ' (cabang ' . (string) ($c['barang_cabang'] ?? '?') . ').'
				);
			}

			// Update master barang (semua cabang yang terkunci).
			$updBarang = mysqli_query(
				$conn,
				"UPDATE barang
				 SET barang_kode = '$kBaruEsc',
				     barang_kode_slug = '$sBaruEsc'
				 WHERE barang_id IN ($idList)"
			);
			if (!$updBarang) {
				throw new RuntimeException('Gagal update tabel barang: ' . mysqli_error($conn));
			}
			$detail['barang_updated'] = mysqli_affected_rows($conn);

			// Cascade ke tabel terkait.
			foreach (bub_cascade_targets() as $t) {
				if (!bub_column_exists($conn, $t['table'], $t['column'])) {
					continue;
				}
				$where = bub_build_where($conn, $t['column'], $t['match'], $oldKodeList, $oldSlugList);
				if ($where === '') {
					continue;
				}
				// Nilai baru: kolom slug → slug baru; kolom kode → kode baru.
				$newVal = ($t['match'] === 'slug') ? $sBaruEsc : $kBaruEsc;
				// stock_opname menyimpan kode (bukan slug) — pakai kode baru.
				if ($t['match'] === 'kode_or_slug') {
					$newVal = $kBaruEsc;
				}
				$tableEsc = str_replace('`', '``', $t['table']);
				$colEsc = str_replace('`', '``', $t['column']);
				$sql = "UPDATE `$tableEsc` SET `$colEsc` = '$newVal' WHERE $where";
				$okUpd = mysqli_query($conn, $sql);
				if (!$okUpd) {
					throw new RuntimeException(
						'Gagal update ' . $t['table'] . '.' . $t['column'] . ': ' . mysqli_error($conn)
						. ' — semua perubahan dibatalkan (rollback).'
					);
				}
				$detail['cascade'][] = [
					'table' => $t['table'],
					'column' => $t['column'],
					'affected' => mysqli_affected_rows($conn),
				];
			}

			// Log audit (masih dalam transaksi yang sama).
			$userId = isset($opts['user_id']) ? (int) $opts['user_id'] : null;
			$userNama = isset($opts['user_nama']) ? (string) $opts['user_nama'] : '';
			$userIdSql = $userId !== null && $userId > 0 ? (string) $userId : 'NULL';
			$userNamaEsc = mysqli_real_escape_string($conn, $userNama);
			$slugLamaJoin = mysqli_real_escape_string($conn, implode(',', $oldSlugList));
			$detailJson = mysqli_real_escape_string($conn, (string) json_encode($detail, JSON_UNESCAPED_UNICODE));
			$cabangCount = count($ids);
			$now = date('Y-m-d H:i:s');
			$logSql = "INSERT INTO barang_barcode_ubah_log
				(kode_lama, kode_baru, slug_lama, slug_baru, user_id, user_nama, cabang_count, detail_json, created_at)
				VALUES
				('$kEsc', '$kBaruEsc', '$slugLamaJoin', '$sBaruEsc', $userIdSql, '$userNamaEsc', $cabangCount, '$detailJson', '$now')";
			if (!mysqli_query($conn, $logSql)) {
				throw new RuntimeException('Gagal menulis log audit: ' . mysqli_error($conn));
			}

			if (!mysqli_commit($conn)) {
				throw new RuntimeException('Gagal commit transaksi: ' . mysqli_error($conn));
			}

			return [
				'ok' => true,
				'msg' => 'Barcode berhasil diubah dari "' . $kodeAktual . '" menjadi "' . $kodeBaru . '" (semua cabang + data terkait).',
				'detail' => $detail,
			];
		} catch (Throwable $e) {
			mysqli_rollback($conn);
			return [
				'ok' => false,
				'msg' => $e->getMessage(),
				'detail' => $detail,
			];
		}
	}
}
