<?php
/**
 * Permintaan perbaikan HPP dari toko + koreksi HPP histori penjualan (gudang).
 */

if (!function_exists('hpp_perbaikan_ensure_tables')) {
	function hpp_perbaikan_ensure_tables(mysqli $conn): void
	{
		static $done = false;
		if ($done) {
			return;
		}
		$done = true;

		@mysqli_query($conn, "
			CREATE TABLE IF NOT EXISTS hpp_perbaikan_request (
				id INT NOT NULL AUTO_INCREMENT,
				barang_kode VARCHAR(100) NOT NULL,
				barang_nama VARCHAR(255) NULL,
				barang_id INT NULL,
				cabang_pemohon INT NOT NULL,
				tanggal_awal DATE NOT NULL,
				tanggal_akhir DATE NOT NULL,
				ringkas_penjualan DECIMAL(18,2) NULL DEFAULT 0,
				ringkas_hpp DECIMAL(18,2) NULL DEFAULT 0,
				ringkas_laba DECIMAL(18,2) NULL DEFAULT 0,
				jml_trx_rugi INT NOT NULL DEFAULT 0,
				jml_trx INT NOT NULL DEFAULT 0,
				catatan TEXT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'baru',
				catatan_gudang TEXT NULL,
				dibuat_oleh INT NULL,
				dibuat_nama VARCHAR(100) NULL,
				diproses_oleh INT NULL,
				diproses_nama VARCHAR(100) NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_status (status),
				KEY idx_kode (barang_kode),
				KEY idx_cabang (cabang_pemohon)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");

		@mysqli_query($conn, "
			CREATE TABLE IF NOT EXISTS hpp_histori_koreksi_log (
				id INT NOT NULL AUTO_INCREMENT,
				barang_kode VARCHAR(100) NOT NULL,
				cabang INT NOT NULL DEFAULT -1,
				tanggal_awal DATE NOT NULL,
				tanggal_akhir DATE NOT NULL,
				hpp_baru DECIMAL(18,4) NOT NULL,
				jml_baris INT NOT NULL DEFAULT 0,
				jml_invoice INT NOT NULL DEFAULT 0,
				total_hpp_lama DECIMAL(18,2) NULL DEFAULT 0,
				total_hpp_baru DECIMAL(18,2) NULL DEFAULT 0,
				request_id INT NULL,
				dibuat_oleh INT NULL,
				dibuat_nama VARCHAR(100) NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_kode (barang_kode),
				KEY idx_request (request_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");
	}
}

if (!function_exists('hpp_perbaikan_can_gudang')) {
	function hpp_perbaikan_can_gudang(int $sessionCabang, string $levelLogin): bool
	{
		if (in_array($levelLogin, ['kasir', 'kurir'], true)) {
			return false;
		}
		return $sessionCabang === 0 || $levelLogin === 'super admin';
	}
}

if (!function_exists('hpp_perbaikan_can_request')) {
	function hpp_perbaikan_can_request(string $levelLogin): bool
	{
		return !in_array($levelLogin, ['kasir', 'kurir'], true);
	}
}

if (!function_exists('hpp_perbaikan_nama_cabang')) {
	function hpp_perbaikan_nama_cabang(mysqli $conn, int $cabang): string
	{
		if ($cabang <= 0) {
			return 'Gudang / Nugrosir';
		}
		$res = mysqli_query($conn, 'SELECT toko_nama, toko_kota FROM toko WHERE toko_cabang = ' . (int) $cabang . ' LIMIT 1');
		if ($res && ($row = mysqli_fetch_assoc($res))) {
			$nama = trim((string) ($row['toko_nama'] ?? ''));
			$kota = trim((string) ($row['toko_kota'] ?? ''));
			if ($nama !== '') {
				return $kota !== '' ? ($nama . ' — ' . $kota) : $nama;
			}
		}
		return 'Cabang ' . $cabang;
	}
}

if (!function_exists('hpp_perbaikan_status_label')) {
	function hpp_perbaikan_status_label(string $status): string
	{
		$map = [
			'baru' => 'Baru',
			'diproses' => 'Diproses',
			'selesai' => 'Selesai',
			'ditolak' => 'Ditolak',
		];
		return $map[$status] ?? $status;
	}
}

if (!function_exists('hpp_perbaikan_status_badge')) {
	function hpp_perbaikan_status_badge(string $status): string
	{
		$map = [
			'baru' => 'badge-danger',
			'diproses' => 'badge-warning',
			'selesai' => 'badge-success',
			'ditolak' => 'badge-secondary',
		];
		return $map[$status] ?? 'badge-light';
	}
}

if (!function_exists('hpp_perbaikan_buat_request')) {
	/**
	 * @param array<string, mixed> $data
	 * @return array{ok: bool, message: string, id?: int}
	 */
	function hpp_perbaikan_buat_request(mysqli $conn, array $data): array
	{
		hpp_perbaikan_ensure_tables($conn);

		$kode = trim((string) ($data['barang_kode'] ?? ''));
		if ($kode === '') {
			return ['ok' => false, 'message' => 'Kode barang wajib diisi.'];
		}

		$cabang = (int) ($data['cabang_pemohon'] ?? 0);
		$tAwal = (string) ($data['tanggal_awal'] ?? '');
		$tAkhir = (string) ($data['tanggal_akhir'] ?? '');
		if ($tAwal === '' || $tAkhir === '') {
			return ['ok' => false, 'message' => 'Periode tanggal wajib diisi.'];
		}
		if ($tAwal > $tAkhir) {
			return ['ok' => false, 'message' => 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.'];
		}

		$kodeEsc = mysqli_real_escape_string($conn, $kode);
		$nama = trim((string) ($data['barang_nama'] ?? ''));
		if ($nama === '') {
			$resN = mysqli_query($conn, "
				SELECT barang_nama FROM barang
				WHERE barang_kode = '{$kodeEsc}' AND barang_cabang = {$cabang}
				ORDER BY barang_id DESC LIMIT 1
			");
			if ($resN && ($rn = mysqli_fetch_assoc($resN))) {
				$nama = (string) ($rn['barang_nama'] ?? '');
			}
		}

		// Cegah spam: request "baru" yang sama (kode+cabang+periode) dalam 24 jam.
		$cek = mysqli_query($conn, "
			SELECT id FROM hpp_perbaikan_request
			WHERE barang_kode = '{$kodeEsc}'
			  AND cabang_pemohon = {$cabang}
			  AND tanggal_awal = '" . mysqli_real_escape_string($conn, $tAwal) . "'
			  AND tanggal_akhir = '" . mysqli_real_escape_string($conn, $tAkhir) . "'
			  AND status IN ('baru', 'diproses')
			  AND created_at >= (NOW() - INTERVAL 1 DAY)
			LIMIT 1
		");
		if ($cek && mysqli_num_rows($cek) > 0) {
			$row = mysqli_fetch_assoc($cek);
			return [
				'ok' => false,
				'message' => 'Permintaan serupa masih aktif (#' . (int) ($row['id'] ?? 0) . '). Tunggu diproses gudang.',
			];
		}

		$barangId = (int) ($data['barang_id'] ?? 0);
		$penjualan = (float) ($data['ringkas_penjualan'] ?? 0);
		$hpp = (float) ($data['ringkas_hpp'] ?? 0);
		$laba = (float) ($data['ringkas_laba'] ?? 0);
		$jmlRugi = (int) ($data['jml_trx_rugi'] ?? 0);
		$jmlTrx = (int) ($data['jml_trx'] ?? 0);
		$catatan = trim((string) ($data['catatan'] ?? ''));
		$userId = (int) ($data['dibuat_oleh'] ?? 0);
		$userNama = trim((string) ($data['dibuat_nama'] ?? ''));

		$namaEsc = mysqli_real_escape_string($conn, $nama);
		$catatanEsc = mysqli_real_escape_string($conn, $catatan);
		$userNamaEsc = mysqli_real_escape_string($conn, $userNama);
		$tAwalEsc = mysqli_real_escape_string($conn, $tAwal);
		$tAkhirEsc = mysqli_real_escape_string($conn, $tAkhir);

		$ok = mysqli_query($conn, "
			INSERT INTO hpp_perbaikan_request (
				barang_kode, barang_nama, barang_id, cabang_pemohon,
				tanggal_awal, tanggal_akhir,
				ringkas_penjualan, ringkas_hpp, ringkas_laba,
				jml_trx_rugi, jml_trx, catatan, status,
				dibuat_oleh, dibuat_nama
			) VALUES (
				'{$kodeEsc}', '{$namaEsc}', " . ($barangId > 0 ? $barangId : 'NULL') . ", {$cabang},
				'{$tAwalEsc}', '{$tAkhirEsc}',
				'{$penjualan}', '{$hpp}', '{$laba}',
				{$jmlRugi}, {$jmlTrx}, '{$catatanEsc}', 'baru',
				" . ($userId > 0 ? $userId : 'NULL') . ", '{$userNamaEsc}'
			)
		");

		if (!$ok) {
			return ['ok' => false, 'message' => 'Gagal menyimpan permintaan: ' . mysqli_error($conn)];
		}

		return [
			'ok' => true,
			'message' => 'Permintaan perbaikan #' . (int) mysqli_insert_id($conn) . ' terkirim ke gudang.',
			'id' => (int) mysqli_insert_id($conn),
		];
	}
}

if (!function_exists('hpp_perbaikan_update_status')) {
	/**
	 * @return array{ok: bool, message: string}
	 */
	function hpp_perbaikan_update_status(
		mysqli $conn,
		int $id,
		string $status,
		string $catatanGudang,
		int $userId,
		string $userNama
	): array {
		hpp_perbaikan_ensure_tables($conn);
		if ($id < 1) {
			return ['ok' => false, 'message' => 'ID permintaan tidak valid.'];
		}
		if (!in_array($status, ['baru', 'diproses', 'selesai', 'ditolak'], true)) {
			return ['ok' => false, 'message' => 'Status tidak valid.'];
		}

		$statusEsc = mysqli_real_escape_string($conn, $status);
		$catEsc = mysqli_real_escape_string($conn, trim($catatanGudang));
		$namaEsc = mysqli_real_escape_string($conn, $userNama);

		$ok = mysqli_query($conn, "
			UPDATE hpp_perbaikan_request SET
				status = '{$statusEsc}',
				catatan_gudang = '{$catEsc}',
				diproses_oleh = " . ($userId > 0 ? $userId : 'NULL') . ",
				diproses_nama = '{$namaEsc}'
			WHERE id = {$id}
			LIMIT 1
		");

		if (!$ok) {
			return ['ok' => false, 'message' => 'Gagal update status: ' . mysqli_error($conn)];
		}
		return ['ok' => true, 'message' => 'Status permintaan #' . $id . ' diubah menjadi ' . hpp_perbaikan_status_label($status) . '.'];
	}
}

if (!function_exists('hpp_perbaikan_list_request')) {
	/**
	 * @return array<int, array<string, mixed>>
	 */
	function hpp_perbaikan_list_request(mysqli $conn, ?int $cabangFilter = null, string $statusFilter = 'semua', int $limit = 200): array
	{
		hpp_perbaikan_ensure_tables($conn);
		$where = ['1=1'];
		if ($cabangFilter !== null && $cabangFilter >= 0) {
			$where[] = 'cabang_pemohon = ' . (int) $cabangFilter;
		}
		if ($statusFilter !== 'semua' && $statusFilter !== '') {
			$where[] = "status = '" . mysqli_real_escape_string($conn, $statusFilter) . "'";
		}
		$limit = max(1, min(500, $limit));
		$sql = 'SELECT * FROM hpp_perbaikan_request WHERE ' . implode(' AND ', $where)
			. ' ORDER BY FIELD(status, \'baru\', \'diproses\', \'selesai\', \'ditolak\'), id DESC LIMIT ' . $limit;
		$res = mysqli_query($conn, $sql);
		$rows = [];
		if ($res) {
			while ($row = mysqli_fetch_assoc($res)) {
				$rows[] = $row;
			}
		}
		return $rows;
	}
}

if (!function_exists('hpp_perbaikan_get_request')) {
	/**
	 * @return array<string, mixed>|null
	 */
	function hpp_perbaikan_get_request(mysqli $conn, int $id): ?array
	{
		hpp_perbaikan_ensure_tables($conn);
		if ($id < 1) {
			return null;
		}
		$res = mysqli_query($conn, 'SELECT * FROM hpp_perbaikan_request WHERE id = ' . $id . ' LIMIT 1');
		if ($res && ($row = mysqli_fetch_assoc($res))) {
			return $row;
		}
		return null;
	}
}

if (!function_exists('hpp_perbaikan_count_baru')) {
	function hpp_perbaikan_count_baru(mysqli $conn): int
	{
		hpp_perbaikan_ensure_tables($conn);
		$res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM hpp_perbaikan_request WHERE status = 'baru'");
		if ($res && ($row = mysqli_fetch_assoc($res))) {
			return (int) ($row['c'] ?? 0);
		}
		return 0;
	}
}

if (!function_exists('hpp_histori_where_sql')) {
	function hpp_histori_where_sql(
		mysqli $conn,
		string $barangKode,
		string $tanggalAwal,
		string $tanggalAkhir,
		int $cabang = -1
	): string {
		$kodeEsc = mysqli_real_escape_string($conn, $barangKode);
		$tAwalEsc = mysqli_real_escape_string($conn, $tanggalAwal);
		$tAkhirEsc = mysqli_real_escape_string($conn, $tanggalAkhir);
		$where = "
			b.barang_kode = '{$kodeEsc}'
			AND p.penjualan_date BETWEEN '{$tAwalEsc}' AND '{$tAkhirEsc}'
		";
		if ($cabang >= 0) {
			$where .= ' AND p.penjualan_cabang = ' . (int) $cabang;
		}
		return $where;
	}
}

if (!function_exists('hpp_histori_preview')) {
	/**
	 * @return array{
	 *   ok: bool, message: string,
	 *   jml_baris?: int, jml_invoice?: int,
	 *   total_hpp_lama?: float, total_hpp_baru?: float,
	 *   hpp_baru?: float, sample?: array<int, array<string, mixed>>
	 * }
	 */
	function hpp_histori_preview(
		mysqli $conn,
		string $barangKode,
		string $tanggalAwal,
		string $tanggalAkhir,
		float $hppBaru,
		int $cabang = -1
	): array {
		$barangKode = trim($barangKode);
		if ($barangKode === '') {
			return ['ok' => false, 'message' => 'Kode barang wajib diisi.'];
		}
		if ($tanggalAwal === '' || $tanggalAkhir === '' || $tanggalAwal > $tanggalAkhir) {
			return ['ok' => false, 'message' => 'Periode tanggal tidak valid.'];
		}
		if ($hppBaru < 0) {
			return ['ok' => false, 'message' => 'HPP baru tidak boleh negatif.'];
		}

		$where = hpp_histori_where_sql($conn, $barangKode, $tanggalAwal, $tanggalAkhir, $cabang);

		$sqlAgg = "
			SELECT
				COUNT(*) AS jml_baris,
				COUNT(DISTINCT CONCAT(p.penjualan_invoice, ':', p.penjualan_cabang)) AS jml_invoice,
				COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS total_hpp_lama,
				COALESCE(SUM(p.barang_qty_keranjang * {$hppBaru}), 0) AS total_hpp_baru
			FROM penjualan p
			INNER JOIN barang b ON b.barang_id = p.barang_id
			WHERE {$where}
		";
		$res = mysqli_query($conn, $sqlAgg);
		if (!$res) {
			return ['ok' => false, 'message' => 'Gagal preview: ' . mysqli_error($conn)];
		}
		$agg = mysqli_fetch_assoc($res) ?: [];
		$jml = (int) ($agg['jml_baris'] ?? 0);
		if ($jml < 1) {
			return ['ok' => false, 'message' => 'Tidak ada baris penjualan yang cocok untuk filter ini.'];
		}

		$sample = [];
		$sqlSample = "
			SELECT
				p.penjualan_id,
				p.penjualan_invoice,
				p.penjualan_date,
				p.penjualan_cabang,
				p.barang_qty_keranjang,
				p.keranjang_harga,
				p.keranjang_harga_beli AS hpp_lama,
				{$hppBaru} AS hpp_baru,
				(p.barang_qty * p.keranjang_harga) AS penjualan,
				(p.barang_qty_keranjang * p.keranjang_harga_beli) AS total_hpp_lama,
				(p.barang_qty_keranjang * {$hppBaru}) AS total_hpp_baru
			FROM penjualan p
			INNER JOIN barang b ON b.barang_id = p.barang_id
			WHERE {$where}
			ORDER BY ABS(p.keranjang_harga_beli - {$hppBaru}) DESC, p.penjualan_date DESC
			LIMIT 30
		";
		$resS = mysqli_query($conn, $sqlSample);
		if ($resS) {
			while ($row = mysqli_fetch_assoc($resS)) {
				$sample[] = $row;
			}
		}

		return [
			'ok' => true,
			'message' => 'Preview siap.',
			'jml_baris' => $jml,
			'jml_invoice' => (int) ($agg['jml_invoice'] ?? 0),
			'total_hpp_lama' => (float) ($agg['total_hpp_lama'] ?? 0),
			'total_hpp_baru' => (float) ($agg['total_hpp_baru'] ?? 0),
			'hpp_baru' => $hppBaru,
			'sample' => $sample,
		];
	}
}

if (!function_exists('hpp_histori_apply')) {
	/**
	 * @return array{ok: bool, message: string, log_id?: int, jml_baris?: int}
	 */
	function hpp_histori_apply(
		mysqli $conn,
		string $barangKode,
		string $tanggalAwal,
		string $tanggalAkhir,
		float $hppBaru,
		int $cabang = -1,
		?int $requestId = null,
		int $userId = 0,
		string $userNama = '',
		bool $syncMaster = true
	): array {
		$preview = hpp_histori_preview($conn, $barangKode, $tanggalAwal, $tanggalAkhir, $hppBaru, $cabang);
		if (!$preview['ok']) {
			return ['ok' => false, 'message' => $preview['message']];
		}

		hpp_perbaikan_ensure_tables($conn);
		$where = hpp_histori_where_sql($conn, $barangKode, $tanggalAwal, $tanggalAkhir, $cabang);
		$hppEsc = mysqli_real_escape_string($conn, (string) round($hppBaru, 4));

		mysqli_begin_transaction($conn);
		try {
			// Kumpulkan invoice terdampak dulu.
			$invRes = mysqli_query($conn, "
				SELECT DISTINCT p.penjualan_invoice, p.penjualan_cabang
				FROM penjualan p
				INNER JOIN barang b ON b.barang_id = p.barang_id
				WHERE {$where}
			");
			$invoices = [];
			if ($invRes) {
				while ($inv = mysqli_fetch_assoc($invRes)) {
					$invoices[] = $inv;
				}
			}

			$upd = mysqli_query($conn, "
				UPDATE penjualan p
				INNER JOIN barang b ON b.barang_id = p.barang_id
				SET p.keranjang_harga_beli = '{$hppEsc}'
				WHERE {$where}
			");
			if (!$upd) {
				throw new RuntimeException('Gagal update penjualan: ' . mysqli_error($conn));
			}
			$jmlBaris = (int) mysqli_affected_rows($conn);

			foreach ($invoices as $inv) {
				$invNo = mysqli_real_escape_string($conn, (string) ($inv['penjualan_invoice'] ?? ''));
				$invCab = (int) ($inv['penjualan_cabang'] ?? 0);
				if ($invNo === '') {
					continue;
				}
				$okInv = mysqli_query($conn, "
					UPDATE invoice i
					SET i.invoice_total_beli = (
						SELECT COALESCE(SUM(p2.barang_qty_keranjang * p2.keranjang_harga_beli), 0)
						FROM penjualan p2
						WHERE p2.penjualan_invoice = '{$invNo}'
						  AND p2.penjualan_cabang = {$invCab}
					)
					WHERE i.penjualan_invoice = '{$invNo}'
					  AND i.invoice_cabang = {$invCab}
					LIMIT 1
				");
				if (!$okInv) {
					throw new RuntimeException('Gagal update invoice: ' . mysqli_error($conn));
				}
			}

			$kodeEsc = mysqli_real_escape_string($conn, trim($barangKode));
			$tAwalEsc = mysqli_real_escape_string($conn, $tanggalAwal);
			$tAkhirEsc = mysqli_real_escape_string($conn, $tanggalAkhir);
			$namaEsc = mysqli_real_escape_string($conn, $userNama);
			$totalLama = (float) ($preview['total_hpp_lama'] ?? 0);
			$totalBaru = (float) ($preview['total_hpp_baru'] ?? 0);
			$jmlInv = (int) ($preview['jml_invoice'] ?? 0);
			$reqSql = ($requestId !== null && $requestId > 0) ? (int) $requestId : 'NULL';

			$okLog = mysqli_query($conn, "
				INSERT INTO hpp_histori_koreksi_log (
					barang_kode, cabang, tanggal_awal, tanggal_akhir, hpp_baru,
					jml_baris, jml_invoice, total_hpp_lama, total_hpp_baru,
					request_id, dibuat_oleh, dibuat_nama
				) VALUES (
					'{$kodeEsc}', {$cabang}, '{$tAwalEsc}', '{$tAkhirEsc}', '{$hppEsc}',
					{$jmlBaris}, {$jmlInv}, '{$totalLama}', '{$totalBaru}',
					{$reqSql}, " . ($userId > 0 ? $userId : 'NULL') . ", '{$namaEsc}'
				)
			");
			if (!$okLog) {
				throw new RuntimeException('Gagal simpan log: ' . mysqli_error($conn));
			}
			$logId = (int) mysqli_insert_id($conn);

			if ($requestId !== null && $requestId > 0) {
				hpp_perbaikan_update_status(
					$conn,
					$requestId,
					'selesai',
					'Histori HPP dikoreksi ke ' . $hppEsc . ' (log #' . $logId . ').',
					$userId,
					$userNama
				);
			}

			mysqli_commit($conn);
		} catch (Throwable $e) {
			mysqli_rollback($conn);
			return ['ok' => false, 'message' => $e->getMessage()];
		}

		if ($syncMaster && function_exists('syncHppBarangByKode')) {
			@syncHppBarangByKode($conn, trim($barangKode));
		}

		return [
			'ok' => true,
			'message' => 'Berhasil koreksi ' . (int) ($preview['jml_baris'] ?? 0) . ' baris penjualan'
				. ' / ' . (int) ($preview['jml_invoice'] ?? 0) . ' invoice. Master HPP ikut disinkronkan.',
			'log_id' => $logId ?? 0,
			'jml_baris' => (int) ($preview['jml_baris'] ?? 0),
		];
	}
}

if (!function_exists('hpp_histori_list_log')) {
	/**
	 * @return array<int, array<string, mixed>>
	 */
	function hpp_histori_list_log(mysqli $conn, int $limit = 50): array
	{
		hpp_perbaikan_ensure_tables($conn);
		$limit = max(1, min(200, $limit));
		$res = mysqli_query($conn, 'SELECT * FROM hpp_histori_koreksi_log ORDER BY id DESC LIMIT ' . $limit);
		$rows = [];
		if ($res) {
			while ($row = mysqli_fetch_assoc($res)) {
				$rows[] = $row;
			}
		}
		return $rows;
	}
}
