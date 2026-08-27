<?php

// koneksi ke database (absolut: aman setelah chdir ke NUMART_ROOT)
include __DIR__ . '/koneksi.php';


function query($query)
{
	global $conn;
	try {
		$result = mysqli_query($conn, $query);
	} catch (mysqli_sql_exception $e) {
		return [];
	}
	$rows = [];
	if ($result && mysqli_num_rows($result) > 0) {
		while ($row = mysqli_fetch_assoc($result)) {
			$rows[] = $row;
		}
	}
	return $rows;
}

if (!function_exists('numart_is_local_dev_host')) {
	function numart_is_local_dev_host(): bool
	{
		$host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
		$host = (string) preg_replace('/:\d+$/', '', $host);
		if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
			return true;
		}
		return preg_match('/\.(test|local|localhost)$/', $host) === 1;
	}
}

if (!function_exists('numart_db_missing_core_tables')) {
	function numart_db_missing_core_tables(): bool
	{
		global $conn;
		if (!($conn instanceof mysqli)) {
			return false;
		}
		foreach (['user', 'toko'] as $table) {
			$res = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table) . "'");
			if (!$res || mysqli_num_rows($res) === 0) {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('numart_db_recovery_redirect')) {
	function numart_db_recovery_redirect(): void
	{
		if (!numart_is_local_dev_host() || !numart_db_missing_core_tables()) {
			return;
		}
		$script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
		$allowed = [
			'sync-database-live.php',
			'login.php',
			'sync-db-export-live.php',
		];
		if (in_array($script, $allowed, true)) {
			return;
		}
		header('Location: sync-database-live.php');
		exit;
	}
}

numart_db_recovery_redirect();

/** Label tipe customer untuk layar konsumen / kasir. */
function pos_display_tipe_label($tipeCustomer)
{
	$tipeCustomer = (int) $tipeCustomer;
	if ($tipeCustomer === 1) {
		return 'Member Retail';
	}
	if ($tipeCustomer === 2) {
		return 'Grosir';
	}
	return 'Umum';
}

/** State layar konsumen per kasir (session). */
function pos_display_state($kasirId)
{
	$kasirId = (int) $kasirId;
	$key = 'pos_display_' . $kasirId;
	$state = $_SESSION[$key] ?? null;
	if (!is_array($state)) {
		return [
			'tipe_customer' => 0,
			'cabang' => 0,
			'revision' => 0,
			'event' => 'active',
			'payment_type' => 0,
			'updated_at' => 0,
		];
	}
	return [
		'tipe_customer' => (int) ($state['tipe_customer'] ?? 0),
		'cabang' => (int) ($state['cabang'] ?? 0),
		'revision' => (int) ($state['revision'] ?? 0),
		'event' => (string) ($state['event'] ?? 'active'),
		'payment_type' => (int) ($state['payment_type'] ?? 0),
		'updated_at' => (int) ($state['updated_at'] ?? 0),
	];
}

/** Label tipe pembayaran untuk layar konsumen. */
function pos_display_payment_label($paymentType)
{
	return (int) $paymentType === 1 ? 'Transfer' : 'Cash';
}

/** URL/path gambar QRIS toko per cabang (0 = Pusat). */
function pos_display_qris_url($conn, $cabang)
{
	$cabang = (int) $cabang;
	if ($cabang < 0) {
		return '';
	}
	$rows = query("SELECT toko_qris FROM toko WHERE toko_cabang = $cabang LIMIT 1");
	$qris = trim((string) ($rows[0]['toko_qris'] ?? ''));
	return $qris;
}

/** Perbarui tipe pembayaran di session layar konsumen (tanpa reload halaman kasir). */
function pos_display_update_payment($kasirId, $paymentType)
{
	$kasirId = (int) $kasirId;
	$paymentType = ((int) $paymentType === 1) ? 1 : 0;
	$key = 'pos_display_' . $kasirId;
	if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
		$_SESSION[$key] = pos_display_state($kasirId);
	}
	$_SESSION[$key]['payment_type'] = $paymentType;
	$_SESSION[$key]['updated_at'] = time();
}

/** Nilai default ringkasan pembayaran layar konsumen. */
function pos_display_totals_default()
{
	return [
		'sub_total' => 0,
		'ongkir' => 0,
		'diskon' => 0,
		'bayar' => 0,
		'kembali' => 0,
	];
}

/** Ambil ringkasan pembayaran dari session layar konsumen. */
function pos_display_totals($kasirId)
{
	$kasirId = (int) $kasirId;
	$key = 'pos_display_' . $kasirId;
	$totals = $_SESSION[$key]['totals'] ?? null;
	if (!is_array($totals)) {
		return pos_display_totals_default();
	}
	return [
		'sub_total' => max(0, (int) ($totals['sub_total'] ?? 0)),
		'ongkir' => max(0, (int) ($totals['ongkir'] ?? 0)),
		'diskon' => max(0, (int) ($totals['diskon'] ?? 0)),
		'bayar' => max(0, (int) ($totals['bayar'] ?? 0)),
		'kembali' => (int) ($totals['kembali'] ?? 0),
	];
}

/** Perbarui ringkasan pembayaran (sub total, bayar, kembali) ke layar konsumen. */
function pos_display_update_totals($kasirId, array $totals)
{
	$kasirId = (int) $kasirId;
	$key = 'pos_display_' . $kasirId;
	if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
		$_SESSION[$key] = pos_display_state($kasirId);
	}
	$_SESSION[$key]['totals'] = [
		'sub_total' => max(0, (int) ($totals['sub_total'] ?? 0)),
		'ongkir' => max(0, (int) ($totals['ongkir'] ?? 0)),
		'diskon' => max(0, (int) ($totals['diskon'] ?? 0)),
		'bayar' => max(0, (int) ($totals['bayar'] ?? 0)),
		'kembali' => (int) ($totals['kembali'] ?? 0),
	];
	$_SESSION[$key]['updated_at'] = time();
}

/**
 * Sinkronkan konteks transaksi aktif kasir ke session layar konsumen.
 * Event: active | checkout | tipe_changed (otomatis saat tipe berubah).
 */
function pos_display_sync($kasirId, $cabang, $tipeCustomer, $event = 'active')
{
	$kasirId = (int) $kasirId;
	$cabang = (int) $cabang;
	$tipeCustomer = (int) $tipeCustomer;
	$key = 'pos_display_' . $kasirId;
	$prev = pos_display_state($kasirId);
	$revision = (int) $prev['revision'];

	if ($event === 'checkout') {
		$revision++;
	} elseif (
		$event === 'active'
		&& isset($_SESSION[$key]['tipe_customer'])
		&& (int) $_SESSION[$key]['tipe_customer'] !== $tipeCustomer
	) {
		$revision++;
		$event = 'tipe_changed';
	}

	$_SESSION[$key] = [
		'tipe_customer' => $tipeCustomer,
		'cabang' => $cabang,
		'revision' => $revision,
		'event' => $event,
		'payment_type' => 0,
		'totals' => pos_display_totals_default(),
		'updated_at' => time(),
	];
}

/** Konteks transaksi kasir (customer & pembayaran) — bertahan saat reload/tambah barang. */
function beli_langsung_ctx_key($kasirId)
{
	return 'beli_langsung_ctx_' . (int) $kasirId;
}

function beli_langsung_ctx_get($kasirId)
{
	$key = beli_langsung_ctx_key($kasirId);
	if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
		return [
			'customer_id' => null,
			'payment_type' => null,
			'locked' => false,
		];
	}
	$ctx = $_SESSION[$key];
	return [
		'customer_id' => array_key_exists('customer_id', $ctx) ? (int) $ctx['customer_id'] : null,
		'payment_type' => array_key_exists('payment_type', $ctx) ? (int) $ctx['payment_type'] : null,
		'locked' => !empty($ctx['locked']),
	];
}

function beli_langsung_ctx_save($kasirId, $customerId, $paymentType, $locked = true)
{
	$kasirId = (int) $kasirId;
	$key = beli_langsung_ctx_key($kasirId);
	$_SESSION[$key] = [
		'customer_id' => (int) $customerId,
		'payment_type' => ((int) $paymentType === 1) ? 1 : 0,
		'locked' => $locked ? 1 : 0,
		'updated_at' => time(),
	];
}

function beli_langsung_ctx_update_customer($kasirId, $customerId)
{
	$kasirId = (int) $kasirId;
	$key = beli_langsung_ctx_key($kasirId);
	$ctx = beli_langsung_ctx_get($kasirId);
	$_SESSION[$key] = [
		'customer_id' => (int) $customerId,
		'payment_type' => $ctx['payment_type'] !== null ? (int) $ctx['payment_type'] : 0,
		'locked' => 1,
		'updated_at' => time(),
	];
}

function beli_langsung_ctx_update_payment($kasirId, $paymentType)
{
	$kasirId = (int) $kasirId;
	$key = beli_langsung_ctx_key($kasirId);
	$ctx = beli_langsung_ctx_get($kasirId);
	$_SESSION[$key] = [
		'customer_id' => $ctx['customer_id'] !== null ? (int) $ctx['customer_id'] : 0,
		'payment_type' => ((int) $paymentType === 1) ? 1 : 0,
		'locked' => $ctx['customer_id'] !== null ? 1 : (int) !empty($ctx['locked']),
		'updated_at' => time(),
	];
}

function beli_langsung_ctx_clear($kasirId)
{
	unset($_SESSION[beli_langsung_ctx_key($kasirId)]);
}

/** Harga barang sesuai tipe customer (0=Umum, 1=Retail, 2=Grosir) — satuan utama. */
function beli_langsung_harga_by_tipe(array $barang, $tipeCustomer)
{
	return beli_langsung_harga_keranjang_item($barang, $tipeCustomer, (int) ($barang['satuan_id'] ?? 0));
}

/**
 * Harga jual per tipe customer & satuan keranjang (sama seperti beli-langsung-edit-qty.php).
 */
function beli_langsung_harga_keranjang_item(array $barang, $tipeCustomer, $keranjangSatuan)
{
	$tipeCustomer = (int) $tipeCustomer;
	$keranjangSatuan = (int) $keranjangSatuan;

	$satuanId = (int) ($barang['satuan_id'] ?? 0);
	$satuanId2 = (int) ($barang['satuan_id_2'] ?? 0);
	$satuanId3 = (int) ($barang['satuan_id_3'] ?? 0);
	$satuanId4 = (int) ($barang['satuan_id_4'] ?? 0);

	// Satuan 1 (satuan_id) harus dicek dulu agar tidak tertimpa tier 2/3/4
	// bila ID satuan yang sama dipakai di lebih dari satu level (mis. import data).
	$tier = 1;
	if ($keranjangSatuan > 0 && $satuanId > 0 && $keranjangSatuan === $satuanId) {
		$tier = 1;
	} elseif ($keranjangSatuan > 0 && $satuanId2 > 0 && $keranjangSatuan === $satuanId2) {
		$tier = 2;
	} elseif ($keranjangSatuan > 0 && $satuanId3 > 0 && $keranjangSatuan === $satuanId3) {
		$tier = 3;
	} elseif ($keranjangSatuan > 0 && $satuanId4 > 0 && $keranjangSatuan === $satuanId4) {
		$tier = 4;
	}

	if ($tipeCustomer === 1) {
		if ($tier === 2) {
			return (float) ($barang['barang_harga_grosir_1_s2'] ?? 0);
		}
		if ($tier === 3) {
			return (float) ($barang['barang_harga_grosir_1_s3'] ?? 0);
		}
		if ($tier === 4) {
			return (float) ($barang['barang_harga_grosir_1_s4'] ?? 0);
		}
		return (float) ($barang['barang_harga_grosir_1'] ?? 0);
	}
	if ($tipeCustomer === 2) {
		if ($tier === 2) {
			return (float) ($barang['barang_harga_grosir_2_s2'] ?? 0);
		}
		if ($tier === 3) {
			return (float) ($barang['barang_harga_grosir_2_s3'] ?? 0);
		}
		if ($tier === 4) {
			return (float) ($barang['barang_harga_grosir_2_s4'] ?? 0);
		}
		return (float) ($barang['barang_harga_grosir_2'] ?? 0);
	}
	if ($tier === 2) {
		return (float) ($barang['barang_harga_s2'] ?? 0);
	}
	if ($tier === 3) {
		return (float) ($barang['barang_harga_s3'] ?? 0);
	}
	if ($tier === 4) {
		return (float) ($barang['barang_harga_s4'] ?? 0);
	}
	return (float) ($barang['barang_harga'] ?? 0);
}

/** Kolom barang untuk kalkulasi harga keranjang. */
function beli_langsung_barang_harga_columns_sql()
{
	return 'barang_harga, barang_harga_grosir_1, barang_harga_grosir_2,
		barang_harga_s2, barang_harga_grosir_1_s2, barang_harga_grosir_2_s2,
		barang_harga_s3, barang_harga_grosir_1_s3, barang_harga_grosir_2_s3,
		barang_harga_s4, barang_harga_grosir_1_s4, barang_harga_grosir_2_s4,
		satuan_id, satuan_id_2, satuan_id_3, satuan_id_4';
}

require_once __DIR__ . '/beli-langsung-member-lib.php';

/**
 * Sesuaikan harga & tipe customer semua item keranjang kasir.
 * Item dengan harga manual (keranjang_harga_edit) tidak diubah harganya.
 */
function keranjang_update_tipe_harga($kasirId, $cabang, $fromTipe, $toTipe)
{
	global $conn;

	$kasirId = (int) $kasirId;
	$cabang = (int) $cabang;
	$toTipe = (int) $toTipe;
	$hargaCols = beli_langsung_barang_harga_columns_sql();

	$items = query(
		"SELECT keranjang_id, barang_id, keranjang_satuan, keranjang_harga_edit
		 FROM keranjang
		 WHERE keranjang_id_kasir = $kasirId
		   AND keranjang_cabang = $cabang"
	);

	$updated = 0;
	foreach ($items as $item) {
		$keranjangId = (int) ($item['keranjang_id'] ?? 0);
		$barangId = (int) ($item['barang_id'] ?? 0);
		if ($keranjangId < 1 || $barangId < 1) {
			continue;
		}

		$hargaEdit = (int) ($item['keranjang_harga_edit'] ?? 0);
		$keranjangSatuan = (int) ($item['keranjang_satuan'] ?? 0);
		$setHarga = '';
		if ($hargaEdit < 1) {
			$barangRows = query(
				"SELECT $hargaCols FROM barang WHERE barang_id = $barangId LIMIT 1"
			);
			if (empty($barangRows[0])) {
				continue;
			}
			$newHarga = beli_langsung_harga_keranjang_item($barangRows[0], $toTipe, $keranjangSatuan);
			$setHarga = "keranjang_harga = '$newHarga', keranjang_harga_parent = '$newHarga', ";
		}

		mysqli_query(
			$conn,
			"UPDATE keranjang SET
				{$setHarga}keranjang_tipe_customer = '$toTipe'
			 WHERE keranjang_id = $keranjangId"
		);
		if (mysqli_affected_rows($conn) > 0) {
			$updated++;
		}
	}

	return $updated;
}

/** ID berikutnya untuk tabel tanpa AUTO_INCREMENT (mis. terlaris). */
function pos_table_next_id($conn, $table, $idColumn)
{
	$res = mysqli_query($conn, 'SELECT IFNULL(MAX(' . $idColumn . '), 0) + 1 AS next_id FROM ' . $table);
	$row = $res ? mysqli_fetch_assoc($res) : null;
	return $row ? (int) $row['next_id'] : 1;
}

/** Total qty stok yang sudah “dipesan” di semua keranjang aktif (semua kasir) untuk satu barang. */
function keranjangGetReservedStockQty($conn, $barang_id, $keranjang_cabang)
{
	$barang_id = intval($barang_id);
	$cabang = intval($keranjang_cabang);

	$meta = mysqli_query($conn, "SELECT barang_kode_slug FROM barang WHERE barang_id = $barang_id LIMIT 1");
	$metaRow = mysqli_fetch_assoc($meta);
	$slug = $metaRow ? trim((string) ($metaRow['barang_kode_slug'] ?? '')) : '';

	if ($slug !== '') {
		$slugEsc = mysqli_real_escape_string($conn, $slug);
		$where = "keranjang_cabang = $cabang AND barang_kode_slug = '$slugEsc'";
	} else {
		$where = "keranjang_cabang = $cabang AND barang_id = $barang_id";
	}

	$sql = mysqli_query(
		$conn,
		"SELECT COALESCE(SUM(keranjang_qty * keranjang_konversi_isi), 0) AS reserved
		 FROM keranjang
		 WHERE $where"
	);
	if (!$sql) {
		return 0.0;
	}
	$row = mysqli_fetch_assoc($sql);
	return floatval($row['reserved'] ?? 0);
}

/** Apakah qty tambahan masih muat dibanding stok master (termasuk keranjang kasir lain). */
function keranjangCanReserveQty($conn, $barang_id, $keranjang_cabang, $barang_stock, $add_qty, $add_konversi_isi)
{
	$reserved = keranjangGetReservedStockQty($conn, $barang_id, $keranjang_cabang);
	$adding = floatval($add_qty) * floatval($add_konversi_isi);
	return ($reserved + $adding) <= floatval($barang_stock);
}

function tanggal_indo($tanggal)
{
	$bulan = array(
		1 =>   'Januari',
		'Februari',
		'Maret',
		'April',
		'Mei',
		'Juni',
		'Juli',
		'Agustus',
		'September',
		'Oktober',
		'November',
		'Desember'
	);
	$split = explode('-', $tanggal);
	return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

function singkat_angka($n, $presisi = 1)
{
	if ($n < 900) {
		$format_angka = number_format($n, $presisi);
		$simbol = '';
	} else if ($n < 900000) {
		$format_angka = number_format($n / 1000, $presisi);
		$simbol = ' rb';
	} else if ($n < 900000000) {
		$format_angka = number_format($n / 1000000, $presisi);
		$simbol = ' jt';
	} else if ($n < 900000000000) {
		$format_angka = number_format($n / 1000000000, $presisi);
		$simbol = ' M';
	} else {
		$format_angka = number_format($n / 1000000000000, $presisi);
		$simbol = ' T';
	}

	if ($presisi > 0) {
		$pisah = '.' . str_repeat('0', $presisi);
		$format_angka = str_replace($pisah, '', $format_angka);
	}

	return $format_angka . $simbol;
}

// ================================================ USER ====================================== //

function tambahUser($data)
{
	global $conn;
	// ambil data dari tiap elemen dalam form
	$user_nama = htmlspecialchars($data["user_nama"]);
	$user_no_hp = htmlspecialchars($data["user_no_hp"]);
	$user_alamat = htmlspecialchars($data["user_alamat"]);
	$user_email = htmlspecialchars($data["user_email"]);
	$user_password = md5(md5(htmlspecialchars($data["user_password"])));
	$user_create = date("d F Y g:i:s a");
	$user_level = htmlspecialchars($data["user_level"]);
	$user_status = htmlspecialchars($data["user_status"]);
	$user_cabang = htmlspecialchars($data["user_cabang"]);

	// Cek Email
	$email_user_cek = mysqli_num_rows(mysqli_query($conn, "select * from user where user_email = '$user_email' "));

	if ($email_user_cek > 0) {
		echo "
			<script>
				alert('Email Sudah Terdaftar');
			</script>
		";
	} else {
		// query insert data
		$query = "INSERT INTO user VALUES ('', '$user_nama', '$user_no_hp', '$user_alamat', '$user_email', '$user_password', '$user_create', '$user_level' , '$user_status', '$user_cabang')";
		mysqli_query($conn, $query);

		return mysqli_affected_rows($conn);
	}
}

function editUser($data)
{
	global $conn;
	$id = $data["user_id"];


	// ambil data dari tiap elemen dalam form
	$user_nama = htmlspecialchars($data["user_nama"]);
	$user_no_hp = htmlspecialchars($data["user_no_hp"]);
	$user_email = htmlspecialchars($data["user_email"]);
	$user_alamat = htmlspecialchars($data["user_alamat"]);
	$user_password = md5(md5(htmlspecialchars($data["user_password"])));
	$user_level = htmlspecialchars($data["user_level"]);
	$user_status = htmlspecialchars($data["user_status"]);

	// query update data
	$query = "UPDATE user SET 
						user_nama      = '$user_nama',
						user_no_hp     = '$user_no_hp',
						user_alamat    = '$user_alamat',
						user_email     = '$user_email',
						user_password  = '$user_password',
						user_level     = '$user_level',
						user_status    = '$user_status'
						WHERE user_id  = $id
				";
	// var_dump($query); die();
	mysqli_query($conn, $query);

	return mysqli_affected_rows($conn);
}

function hapusUser($id)
{
	global $conn;
	mysqli_query($conn, "DELETE FROM user WHERE user_id = $id");

	return mysqli_affected_rows($conn);
}
// ========================================= Toko ======================================== //
function tambahToko($data)
{
	global $conn;
	// ambil data dari tiap elemen dalam form
	$toko_nama      = htmlspecialchars($data["toko_nama"]);
	$toko_kota      = htmlspecialchars($data["toko_kota"]);
	$toko_alamat    = htmlspecialchars($data["toko_alamat"]);
	$toko_tlpn      = htmlspecialchars($data["toko_tlpn"]);
	$toko_wa        = htmlspecialchars($data["toko_wa"]);
	$toko_email     = htmlspecialchars($data["toko_email"]);
	$toko_print     = htmlspecialchars($data["toko_print"]);
	$toko_status    = htmlspecialchars($data["toko_status"]);
	$toko_ongkir    = htmlspecialchars($data["toko_ongkir"]);
	$toko_cabang    = htmlspecialchars($data["toko_cabang"]);


	// query insert data toko
	$query = "INSERT INTO toko VALUES ('', '$toko_nama', '$toko_kota', '$toko_alamat', '$toko_tlpn', '$toko_wa', '$toko_email', '$toko_print' ,'$toko_status', '$toko_ongkir', '$toko_cabang')";
	mysqli_query($conn, $query);

	// query insert data laba bersih
	$query2 = "INSERT INTO laba_bersih VALUES ('', '', '', '', '', '', '', '' ,'', '', '$toko_cabang')";
	mysqli_query($conn, $query2);


	return mysqli_affected_rows($conn);
}

function editToko($data)
{
	global $conn;
	$id = $data["toko_id"];

	// ambil data dari tiap elemen dalam form
	$toko_nama      = htmlspecialchars($data["toko_nama"]);
	$toko_kota      = htmlspecialchars($data["toko_kota"]);
	$toko_alamat    = htmlspecialchars($data["toko_alamat"]);
	$toko_tlpn      = htmlspecialchars($data["toko_tlpn"]);
	$toko_wa        = htmlspecialchars($data["toko_wa"]);
	$toko_email     = htmlspecialchars($data["toko_email"]);
	$toko_print     = htmlspecialchars($data["toko_print"]);
	$toko_status    = htmlspecialchars($data["toko_status"]);
	$toko_ongkir    = htmlspecialchars($data["toko_ongkir"]);

	// query update data
	$query = "UPDATE toko SET 
				toko_nama       = '$toko_nama',
				toko_kota       = '$toko_kota',
				toko_alamat     = '$toko_alamat',
				toko_tlpn       = '$toko_tlpn',
				toko_wa         = '$toko_wa',
				toko_email      = '$toko_email',
				toko_print      = '$toko_print',
				toko_status     = '$toko_status',
				toko_ongkir		= '$toko_ongkir'
				WHERE toko_id   = $id
				";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}
function hapusToko($id)
{
	global $conn;

	$cabang = mysqli_query($conn, "select toko_cabang from toko where toko_id = " . $id . " ");
	$cabang = mysqli_fetch_array($cabang);
	$toko_cabang = $cabang['toko_cabang'];

	mysqli_query($conn, "DELETE FROM toko WHERE toko_id = $id");
	mysqli_query($conn, "DELETE FROM laba_bersih WHERE lb_cabang = $toko_cabang");

	mysqli_query($conn, "DELETE FROM supplier WHERE supplier_cabang = $toko_cabang");
	mysqli_query($conn, "DELETE FROM kategori WHERE kategori_cabang = $toko_cabang");
	mysqli_query($conn, "DELETE FROM satuan WHERE satuan_cabang = $toko_cabang");
	mysqli_query($conn, "DELETE FROM barang WHERE barang_cabang = $toko_cabang");
	mysqli_query($conn, "DELETE FROM barang_sn WHERE barang_sn_cabang = $toko_cabang");
	mysqli_query($conn, "DELETE FROM invoice_pembelian WHERE invoice_pembelian_cabang = $toko_cabang");
	mysqli_query($conn, "DELETE FROM pembelian WHERE pembelian_cabang = $toko_cabang");
	mysqli_query($conn, "DELETE FROM transfer WHERE transfer_cabang = $toko_cabang");
	mysqli_query($conn, "DELETE FROM transfer_produk_keluar WHERE tpk_cabang = $toko_cabang");
	mysqli_query($conn, "DELETE FROM transfer_produk_masuk WHERE tpm_cabang = $toko_cabang");
	mysqli_query($conn, "DELETE FROM user WHERE user_cabang = $toko_cabang");

	return mysqli_affected_rows($conn);
}

// ========================================= Kategori ======================================= //
function tambahKategori($data)
{
	global $conn;
	// ambil data dari tiap elemen dalam form
	$kategori_nama = htmlspecialchars($data['kategori_nama']);
	$kategori_status = $data['kategori_status'];
	$kategori_cabang = $data['kategori_cabang'];

	// query insert data
	$query = "INSERT INTO kategori VALUES ('', '$kategori_nama', '$kategori_status', '$kategori_cabang')";
	mysqli_query($conn, $query);

	return mysqli_affected_rows($conn);
}

function editKategori($data)
{
	global $conn;
	$id = $data["kategori_id"];

	// ambil data dari tiap elemen dalam form
	$kategori_nama = htmlspecialchars($data['kategori_nama']);
	$kategori_status = $data['kategori_status'];

	// query update data
	$query = "UPDATE kategori SET 
				kategori_nama   = '$kategori_nama',
				kategori_status = '$kategori_status'
				WHERE kategori_id = $id
				";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

function hapusKategori($id)
{
	global $conn;
	mysqli_query($conn, "DELETE FROM kategori WHERE kategori_id = $id");

	return mysqli_affected_rows($conn);
}


// ======================================= Satuan ========================================= //
require_once __DIR__ . '/satuan-lib.php';

function tambahSatuan($data)
{
	global $conn;
	// ambil data dari tiap elemen dalam form
	$satuan_nama = htmlspecialchars($data['satuan_nama']);
	$satuan_status = $data['satuan_status'];
	$satuan_cabang = SATUAN_CABANG_PUSAT;
	$nextId = satuan_next_id($conn);

	// satuan_id bukan AUTO_INCREMENT di DB — isi manual MAX+1
	$query = "INSERT INTO satuan (satuan_id, satuan_nama, satuan_status, satuan_cabang) VALUES ($nextId, '$satuan_nama', '$satuan_status', $satuan_cabang)";
	mysqli_query($conn, $query);

	return mysqli_affected_rows($conn);
}

function editSatuan($data)
{
	global $conn;
	$id = $data["satuan_id"];

	// ambil data dari tiap elemen dalam form
	$satuan_nama = htmlspecialchars($data['satuan_nama']);
	$satuan_status = $data['satuan_status'];

	// query update data
	$query = "UPDATE satuan SET 
				satuan_nama   = '$satuan_nama',
				satuan_status = '$satuan_status'
				WHERE satuan_id = $id AND " . satuan_sql_cabang() . "
				";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

function hapusSatuan($id)
{
	global $conn;
	$id = (int) $id;
	mysqli_query($conn, "DELETE FROM satuan WHERE satuan_id = $id AND " . satuan_sql_cabang());

	return mysqli_affected_rows($conn);
}


// ===================================== ekspedisi ========================================= //
function tambahEkspedisi($data)
{
	global $conn;
	// ambil data dari tiap elemen dalam form
	$ekspedisi_nama = htmlspecialchars($data['ekspedisi_nama']);
	$ekspedisi_status = $data['ekspedisi_status'];
	$ekspedisi_cabang = $data['ekspedisi_cabang'];

	// query insert data
	$query = "INSERT INTO ekspedisi VALUES ('', '$ekspedisi_nama', '$ekspedisi_status', '$ekspedisi_cabang')";
	mysqli_query($conn, $query);

	return mysqli_affected_rows($conn);
}

function editEkspedisi($data)
{
	global $conn;
	$id = $data["ekspedisi_id"];

	// ambil data dari tiap elemen dalam form
	$ekspedisi_nama = htmlspecialchars($data['ekspedisi_nama']);
	$ekspedisi_status = $data['ekspedisi_status'];

	// query update data
	$query = "UPDATE ekspedisi SET 
				ekspedisi_nama   = '$ekspedisi_nama',
				ekspedisi_status = '$ekspedisi_status'
				WHERE ekspedisi_id = $id
				";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

function hapusEkspedisi($id)
{
	global $conn;
	mysqli_query($conn, "DELETE FROM ekspedisi WHERE ekspedisi_id = $id");

	return mysqli_affected_rows($conn);
}


// ======================================== Barang =============================== //
require_once __DIR__ . '/barang-gambar-lib.php';
require_once __DIR__ . '/akun-link-lib.php';

function barang_nullable_int($value, $default = 0)
{
	if ($value === null || $value === '') {
		return (int) $default;
	}
	return (int) $value;
}

function tambahBarang($data, $files = [])
{
    global $conn;
    barang_gambar_ensure_column($conn);
    barang_harga_beli_rata_ensure_column($conn);
    // ambil data dari tiap elemen dalam form
    $barang_kode              = htmlspecialchars($data["barang_kode"]);
    $barang_kode_slug         = str_replace(" ", "-", $barang_kode);
    $barang_kode_count        = htmlspecialchars($data["barang_kode_count"]);
    $barang_nama              = htmlspecialchars($data["barang_nama"]);
    $barang_deskripsi         = htmlspecialchars($data["barang_deskripsi"]);

    $barang_harga             = htmlspecialchars($data["barang_harga"]);
    $barang_harga_beli        = '0';
    $barang_harga_beli_rata   = htmlspecialchars((string) ($data["barang_harga_beli_rata"] ?? $data["barang_harga_beli"] ?? '0'));
    $barang_harga_grosir_1    = htmlspecialchars($data["barang_harga_grosir_1"]);
    $barang_harga_grosir_2    = htmlspecialchars($data["barang_harga_grosir_2"]);

    $barang_harga_s2          = htmlspecialchars($data["barang_harga_s2"]);
    $barang_harga_grosir_1_s2 = htmlspecialchars($data["barang_harga_grosir_1_s2"]);
    $barang_harga_grosir_2_s2 = htmlspecialchars($data["barang_harga_grosir_2_s2"]);

    $barang_harga_s3          = htmlspecialchars($data["barang_harga_s3"]);
    $barang_harga_grosir_1_s3 = htmlspecialchars($data["barang_harga_grosir_1_s3"]);
    $barang_harga_grosir_2_s3 = htmlspecialchars($data["barang_harga_grosir_2_s3"]);

    $barang_harga_s4          = isset($data["barang_harga_s4"]) ? htmlspecialchars($data["barang_harga_s4"]) : '0';
    $barang_harga_grosir_1_s4 = isset($data["barang_harga_grosir_1_s4"]) ? htmlspecialchars($data["barang_harga_grosir_1_s4"]) : '0';
    $barang_harga_grosir_2_s4 = isset($data["barang_harga_grosir_2_s4"]) ? htmlspecialchars($data["barang_harga_grosir_2_s4"]) : '0';

    $kategori_id              = $data["kategori_id"];

    $satuan_id                = $data["satuan_id"];
    $satuan_id_2              = barang_nullable_int($data["satuan_id_2"] ?? 0);
    $satuan_id_3              = barang_nullable_int($data["satuan_id_3"] ?? 0);
    $satuan_id_4              = barang_nullable_int($data["satuan_id_4"] ?? 0);

    $satuan_isi_1             = 1;
    $satuan_isi_2             = barang_nullable_int($data["satuan_isi_2"] ?? 0);
    $satuan_isi_3             = barang_nullable_int($data["satuan_isi_3"] ?? 0);
    $satuan_isi_4             = barang_nullable_int($data["satuan_isi_4"] ?? 0);

    $barang_tanggal           = date("d F Y g:i:s a");
    $barang_stock             = htmlspecialchars($data["barang_stock"]);
    $barang_option_sn         = $data["barang_option_sn"];
    $barang_option_konsi      = $data["barang_option_konsi"];
    $barang_status            = '1';
    $kode_suplier             = $data["kode_suplier"];
    $barang_gambar            = barang_gambar_resolve_from_request($data, $files, '');
    $barang_gambar_esc        = mysqli_real_escape_string($conn, $barang_gambar);

    // Cek Email
    $barang_kode_cek = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM barang WHERE barang_kode = '" . $barang_kode . "'"));

    if ($barang_kode_cek > 0) {
        echo "
            <script>
                alert('Kode Barang Sudah Ada Coba Kode yang Lain !!!');
            </script>
        ";
        return 0;
    }

    // Jika tidak ada toko yang dipilih, tambahkan ke semua toko
    if (!isset($data['toko_cabang'])) {
        $toko_ids = query("SELECT toko_cabang FROM toko WHERE toko_status = '1'");
        $toko_ids = array_column($toko_ids, 'toko_cabang');
    } else {
        $toko_ids = $data['toko_cabang'];
    }

    $success = true;

    foreach ($toko_ids as $toko_id) {
        $toko_id = (int) $toko_id;
        $query = "INSERT INTO barang (
            barang_kode,
            barang_kode_slug,
            barang_kode_count,
            barang_nama,
            barang_harga_beli,
            barang_harga,
            barang_harga_grosir_1,
            barang_harga_grosir_2,
            barang_harga_s2,
            barang_harga_grosir_1_s2,
            barang_harga_grosir_2_s2,
            barang_harga_s3,
            barang_harga_grosir_1_s3,
            barang_harga_grosir_2_s3,
            barang_harga_s4,
            barang_harga_grosir_1_s4,
            barang_harga_grosir_2_s4,
            barang_stock,
            barang_tanggal,
            barang_kategori_id,
            kategori_id,
            barang_satuan_id,
            satuan_id,
            satuan_id_2,
            satuan_id_3,
            satuan_id_4,
            satuan_isi_1,
            satuan_isi_2,
            satuan_isi_3,
            satuan_isi_4,
            barang_deskripsi,
            barang_option_sn,
            barang_terjual,
            barang_cabang,
            barang_konsi,
            barang_status,
            kode_suplier,
            barang_gambar,
            barang_harga_beli_rata
        ) VALUES (
            '$barang_kode',
            '$barang_kode_slug',
            '$barang_kode_count',
            '$barang_nama',
            '$barang_harga_beli',
            '$barang_harga',
            '$barang_harga_grosir_1',
            '$barang_harga_grosir_2',
            '$barang_harga_s2',
            '$barang_harga_grosir_1_s2',
            '$barang_harga_grosir_2_s2',
            '$barang_harga_s3',
            '$barang_harga_grosir_1_s3',
            '$barang_harga_grosir_2_s3',
            '$barang_harga_s4',
            '$barang_harga_grosir_1_s4',
            '$barang_harga_grosir_2_s4',
            '$barang_stock',
            '$barang_tanggal',
            '$kategori_id',
            '$kategori_id',
            '$satuan_id',
            '$satuan_id',
            $satuan_id_2,
            $satuan_id_3,
            $satuan_id_4,
            $satuan_isi_1,
            $satuan_isi_2,
            $satuan_isi_3,
            $satuan_isi_4,
            '$barang_deskripsi',
            '$barang_option_sn',
            0,
            $toko_id,
            '$barang_option_konsi',
            '$barang_status',
            '$kode_suplier',
            '$barang_gambar_esc',
            '$barang_harga_beli_rata'
        )";
        if (!mysqli_query($conn, $query)) {
            $success = false;
        }
    }

    return $success ? count($toko_ids) : 0;
}


function editBarang($data, $files = [])
{
    global $conn;
    barang_gambar_ensure_column($conn);

    // Identitas barcode DIKUNCI di jalur edit biasa.
    // Ubah barcode hanya lewat aksi/barang-ubah-barcode-lib.php (transaksi + cascade).
    $barang_id = abs((int) ($data['barang_id'] ?? 0));
    if ($barang_id < 1) {
        return 0;
    }
    $existingRes = mysqli_query($conn, "SELECT barang_kode, barang_kode_slug FROM barang WHERE barang_id = $barang_id LIMIT 1");
    $existing = $existingRes ? mysqli_fetch_assoc($existingRes) : null;
    if (!$existing || trim((string) ($existing['barang_kode'] ?? '')) === '') {
        return 0;
    }
    $barang_kode = mysqli_real_escape_string($conn, (string) $existing['barang_kode']);

    // Ambil data dari form (tanpa mengubah barcode/slug)
    $kode_suplier = mysqli_real_escape_string($conn, $data["kode_suplier"]);
    $barang_nama = mysqli_real_escape_string($conn, $data["barang_nama"]);
    $barang_deskripsi = mysqli_real_escape_string($conn, $data["barang_deskripsi"]);
    barang_harga_beli_rata_ensure_column($conn);
    $barang_harga_beli_rata = mysqli_real_escape_string($conn, (string) ($data["barang_harga_beli_rata"] ?? '0'));
    $barang_harga = mysqli_real_escape_string($conn, $data["barang_harga"]);
    $barang_harga_grosir_1 = mysqli_real_escape_string($conn, $data["barang_harga_grosir_1"]);
    $barang_harga_grosir_2 = mysqli_real_escape_string($conn, $data["barang_harga_grosir_2"]);
    $barang_harga_s2 = mysqli_real_escape_string($conn, $data["barang_harga_s2"]);
    $barang_harga_grosir_1_s2 = mysqli_real_escape_string($conn, $data["barang_harga_grosir_1_s2"]);
    $barang_harga_grosir_2_s2 = mysqli_real_escape_string($conn, $data["barang_harga_grosir_2_s2"]);
    $barang_harga_s3 = mysqli_real_escape_string($conn, $data["barang_harga_s3"]);
    $barang_harga_grosir_1_s3 = mysqli_real_escape_string($conn, $data["barang_harga_grosir_1_s3"]);
    $barang_harga_grosir_2_s3 = mysqli_real_escape_string($conn, $data["barang_harga_grosir_2_s3"]);
    $barang_harga_s4 = isset($data["barang_harga_s4"]) ? mysqli_real_escape_string($conn, $data["barang_harga_s4"]) : '0';
    $barang_harga_grosir_1_s4 = isset($data["barang_harga_grosir_1_s4"]) ? mysqli_real_escape_string($conn, $data["barang_harga_grosir_1_s4"]) : '0';
    $barang_harga_grosir_2_s4 = isset($data["barang_harga_grosir_2_s4"]) ? mysqli_real_escape_string($conn, $data["barang_harga_grosir_2_s4"]) : '0';
    $barang_kategori_id = mysqli_real_escape_string($conn, $data["barang_kategori_id"]);
    $kategori_id = mysqli_real_escape_string($conn, $data["kategori_id"]);
    $satuan_id = mysqli_real_escape_string($conn, $data["satuan_id"]);
    $satuan_id_2 = barang_nullable_int($data["satuan_id_2"] ?? 0);
    $satuan_id_3 = barang_nullable_int($data["satuan_id_3"] ?? 0);
    $satuan_id_4 = barang_nullable_int($data["satuan_id_4"] ?? 0);
    $satuan_isi_2 = barang_nullable_int($data["satuan_isi_2"] ?? 0);
    $satuan_isi_3 = barang_nullable_int($data["satuan_isi_3"] ?? 0);
    $satuan_isi_4 = barang_nullable_int($data["satuan_isi_4"] ?? 0);
    $barang_option_sn = mysqli_real_escape_string($conn, (string) ($data["barang_option_sn"] ?? '0'));
    $barang_option_konsi = mysqli_real_escape_string($conn, (string) ($data["barang_option_konsi"] ?? $data["barang_konsi"] ?? '0'));
    $barang_stock = mysqli_real_escape_string($conn, (string) ($data["barang_stock"] ?? '0'));
    $existingGambar = isset($data['barang_gambar_lama']) ? (string) $data['barang_gambar_lama'] : '';
    $barang_gambar = barang_gambar_resolve_from_request($data, $files, $existingGambar);
    $barang_gambar_esc = mysqli_real_escape_string($conn, $barang_gambar);

    $barang_satuan_id = $satuan_id; 
    // Ambil daftar toko aktif
    $toko_ids = query("SELECT toko_cabang FROM toko WHERE toko_status = '1'");
    $toko_ids = array_column($toko_ids, 'toko_cabang');
    $success = true;

    // Update semua atribut kecuali barang_stock, barang_kode, barang_kode_slug
    foreach ($toko_ids as $toko_id) {
        $query = "UPDATE barang SET 
                    kode_suplier = '$kode_suplier',
                    barang_nama = '$barang_nama',
                    barang_harga = '$barang_harga',
                    barang_harga_beli_rata = '$barang_harga_beli_rata',
                    barang_harga_grosir_1 = '$barang_harga_grosir_1',
                    barang_harga_grosir_2 = '$barang_harga_grosir_2',
                    barang_harga_s2 = '$barang_harga_s2',
                    barang_harga_grosir_1_s2 = '$barang_harga_grosir_1_s2',
                    barang_harga_grosir_2_s2 = '$barang_harga_grosir_2_s2',
                    barang_harga_s3 = '$barang_harga_s3',
                    barang_harga_grosir_1_s3 = '$barang_harga_grosir_1_s3',
                    barang_harga_grosir_2_s3 = '$barang_harga_grosir_2_s3',
                    barang_harga_s4 = '$barang_harga_s4',
                    barang_harga_grosir_1_s4 = '$barang_harga_grosir_1_s4',
                    barang_harga_grosir_2_s4 = '$barang_harga_grosir_2_s4',
                    barang_kategori_id = '$barang_kategori_id',
                    kategori_id = '$kategori_id',
                    satuan_id = '$satuan_id',
                    barang_satuan_id = '$barang_satuan_id',
                    satuan_id_2 = '$satuan_id_2',
                    satuan_id_3 = '$satuan_id_3',
                    satuan_id_4 = '$satuan_id_4',
                    satuan_isi_2 = '$satuan_isi_2',
                    satuan_isi_3 = '$satuan_isi_3',
                    satuan_isi_4 = '$satuan_isi_4',
                    barang_deskripsi = '$barang_deskripsi',
                    barang_option_sn = '$barang_option_sn',
                    barang_konsi = '$barang_option_konsi',
                    barang_gambar = '$barang_gambar_esc'
                  WHERE barang_kode = '$barang_kode' AND barang_cabang = '$toko_id'";
        
        if (!mysqli_query($conn, $query)) {
            $success = false;
        }
    }

    // Update barang_stock hanya untuk toko target
    $query_stock = "UPDATE barang SET 
                    barang_stock = '$barang_stock'
                    WHERE barang_kode = '$barang_kode' AND barang_cabang = '0'";

    if (!mysqli_query($conn, $query_stock)) {
        $success = false;
    }

    return $success ? count($toko_ids) : 0;
}

function editBarangCabang($data)
{
    global $conn;

    // Sanitasi input
    $barang_kode = mysqli_real_escape_string($conn, $data["barang_id"]);
    $barang_stock = mysqli_real_escape_string($conn, $data["barang_stock"]);
    $barang_satuan_id = mysqli_real_escape_string($conn, $data["barang_satuan_id"]);
    $satuan_id = mysqli_real_escape_string($conn, $data["satuan_id"]);
    $satuan_id_2 = barang_nullable_int($data["satuan_id_2"] ?? 0);
    $satuan_id_3 = barang_nullable_int($data["satuan_id_3"] ?? 0);
    $satuan_id_4 = barang_nullable_int($data["satuan_id_4"] ?? 0);
    $barang_kategori_id = mysqli_real_escape_string($conn, $data["barang_kategori_id"]);
    $kategori_id = mysqli_real_escape_string($conn, $data["kategori_id"]);

    // Query update
    $query = "
        UPDATE barang 
        SET 
            barang_stock = '$barang_stock', 
            barang_satuan_id = '$barang_satuan_id', 
            satuan_id = '$satuan_id', 
            satuan_id_2 = '$satuan_id_2', 
            satuan_id_3 = '$satuan_id_3', 
            satuan_id_4 = '$satuan_id_4', 
            barang_kategori_id = '$barang_kategori_id',
            kategori_id = '$kategori_id'
        WHERE 
            barang_id = '$barang_kode'
    ";

    mysqli_query($conn, $query);

    // Mengembalikan jumlah baris yang terpengaruh
    return mysqli_affected_rows($conn);
}


function hapusBarang($id)
{
	global $conn;

	// Ambil ID produk
	$data_id = $id;

	// Mencari No. Invoice
	$sn = mysqli_query($conn, "select barang_option_sn from barang where barang_id = '" . $data_id . "'");
	$sn = mysqli_fetch_array($sn);
	$sn = $sn["barang_option_sn"];

	$barang = mysqli_query($conn, "select barang_kode_slug, barang_cabang, barang_kode from barang where barang_id = " . $data_id . " ");
	$barang = mysqli_fetch_array($barang);
	$barang_kode_slug 	= $barang['barang_kode_slug'];
	$barang_cabang 		= $barang['barang_cabang'];
	$barang_kode 		= $barang['barang_kode'];

	$countBarangSn = mysqli_query($conn, "select * from barang_sn where barang_kode_slug = '" . $barang_kode_slug . "' && barang_sn_status > 0 && barang_sn_cabang = " . $barang_cabang . " ");
	$countBarangSn = mysqli_num_rows($countBarangSn);

	if ($sn < 1) {
		mysqli_query($conn, "UPDATE barang SET barang_status = '0' WHERE barang_kode = '$barang_kode'");
		return mysqli_affected_rows($conn);
	} else {
		mysqli_query($conn, "UPDATE barang SET barang_status = '0' WHERE barang_kode = '$barang_kode'");

		if ($countBarangSn > 0) {
			mysqli_query($conn, "DELETE FROM barang_sn WHERE barang_kode_slug = '" . $barang_kode_slug . "' && barang_sn_status > 0 && barang_sn_cabang = $barang_cabang ");
		}
		return mysqli_affected_rows($conn);
	}
}

function aktifBarang($id)
{
	global $conn;

	// Ambil ID produk
	$data_id = $id;

	// Mencari No. Invoice
	$sn = mysqli_query($conn, "select barang_option_sn from barang where barang_id = '" . $data_id . "'");
	$sn = mysqli_fetch_array($sn);
	$sn = $sn["barang_option_sn"];

	$barang = mysqli_query($conn, "select barang_kode_slug, barang_cabang, barang_kode from barang where barang_id = " . $data_id . " ");
	$barang = mysqli_fetch_array($barang);
	$barang_kode_slug 	= $barang['barang_kode_slug'];
	$barang_cabang 		= $barang['barang_cabang'];
	$barang_kode 		= $barang['barang_kode'];

	$countBarangSn = mysqli_query($conn, "select * from barang_sn where barang_kode_slug = '" . $barang_kode_slug . "' && barang_sn_status > 1 && barang_sn_cabang = " . $barang_cabang . " ");
	$countBarangSn = mysqli_num_rows($countBarangSn);

	if ($sn < 1) {
		mysqli_query($conn, "UPDATE barang SET barang_status = '1' WHERE barang_kode = '$barang_kode'");
		return mysqli_affected_rows($conn);
	} else {
		mysqli_query($conn, "UPDATE barang SET barang_status = '1' WHERE barang_kode = '$barang_kode'");

		if ($countBarangSn > 0) {
			mysqli_query($conn, "DELETE FROM barang_sn WHERE barang_kode_slug = '" . $barang_kode_slug . "' && barang_sn_status > 1 && barang_sn_cabang = $barang_cabang ");
		}
		return mysqli_affected_rows($conn);
	}
}

// function hapusBarang($id)
// {
// 	global $conn;

// 	// Ambil ID produk
// 	$data_id = $id;

// 	// Mencari No. Invoice
// 	$sn = mysqli_query($conn, "select barang_option_sn from barang where barang_id = '" . $data_id . "'");
// 	$sn = mysqli_fetch_array($sn);
// 	$sn = $sn["barang_option_sn"];

// 	$barang = mysqli_query($conn, "select barang_kode_slug, barang_cabang, barang_kode from barang where barang_id = " . $data_id . " ");
// 	$barang = mysqli_fetch_array($barang);
// 	$barang_kode_slug 	= $barang['barang_kode_slug'];
// 	$barang_cabang 		= $barang['barang_cabang'];
// 	$barang_kode 		= $barang['barang_kode'];

// 	$countBarangSn = mysqli_query($conn, "select * from barang_sn where barang_kode_slug = '" . $barang_kode_slug . "' && barang_sn_status > 0 && barang_sn_cabang = " . $barang_cabang . " ");
// 	$countBarangSn = mysqli_num_rows($countBarangSn);

// 	if ($sn < 1) {
// 		mysqli_query($conn, "DELETE FROM barang WHERE barang_id = $id);
// 		return mysqli_affected_rows($conn);
// 	} else {
// 		mysqli_query($conn, "DELETE FROM barang WHERE barang_id = $id");

// 		if ($countBarangSn > 0) {
// 			mysqli_query($conn, "DELETE FROM barang_sn WHERE barang_kode_slug = '" . $barang_kode_slug . "' && barang_sn_status > 0 && barang_sn_cabang = $barang_cabang ");
// 		}
// 		return mysqli_affected_rows($conn);
// 	}
// }

// ===================================== Barang SN ========================================= //
function tambahBarangSn($data)
{
	global $conn;
	// ambil data dari tiap elemen dalam form
	$barang_sn_desc 			= $data['barang_sn_desc'];
	$barang_kode_slug 			= $data['barang_kode_slug'];
	$barang_sn_status 			= $data['barang_sn_status'];
	$barang_sn_cabang 			= $data['barang_sn_cabang'];

	$jumlah = count($barang_kode_slug);

	// query insert data
	for ($x = 0; $x < $jumlah; $x++) {
		$query = "INSERT INTO barang_sn VALUES ('', '$barang_sn_desc[$x]', '$barang_kode_slug[$x]', '$barang_sn_status[$x]', '$barang_sn_cabang[$x]')";

		mysqli_query($conn, $query);
	}

	return mysqli_affected_rows($conn);
}

function editBarangSn($data)
{
	global $conn;
	$id = $data["barang_sn_id"];

	// ambil data dari tiap elemen dalam form
	$barang_sn_desc 	= htmlspecialchars($data['barang_sn_desc']);
	$barang_sn_status 	= $data['barang_sn_status'];

	// query update data
	$query = "UPDATE barang_sn SET 
				barang_sn_desc    = '$barang_sn_desc',
				barang_sn_status  = '$barang_sn_status'
				WHERE barang_sn_id = $id
				";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

function hapusBarangSn($id)
{
	global $conn;
	mysqli_query($conn, "DELETE FROM barang_sn WHERE barang_sn_id = $id");

	return mysqli_affected_rows($conn);
}

// ===================================== Keranjang ========================================= //
function tambahKeranjang(
	$keranjang_cabang,
	$barang_id,
	$barang_kode_slug,
	$keranjang_nama,
	$keranjang_harga_beli,
	$keranjang_harga,
	$keranjang_satuan,
	$keranjang_id_kasir,
	$keranjang_qty,
	$keranjang_konversi_isi,
	$keranjang_barang_sn_id,
	$keranjang_barang_option_sn,
	$keranjang_sn,
	$keranjang_id_cek,
	$customer
) {
	global $conn;

	$barang_id = intval($barang_id);
	$stockRow = mysqli_query($conn, "SELECT barang_stock FROM barang WHERE barang_id = $barang_id LIMIT 1");
	$stockData = mysqli_fetch_assoc($stockRow);
	$barang_stock = $stockData['barang_stock'] ?? 0;

	if (!keranjangCanReserveQty($conn, $barang_id, $keranjang_cabang, $barang_stock, $keranjang_qty, $keranjang_konversi_isi)) {
		return 0;
	}

	// Cek item sudah ada di keranjang kasir ini (bukan kasir lain)
	$barang_id_cek = mysqli_num_rows(mysqli_query($conn, "SELECT keranjang_id FROM keranjang WHERE keranjang_id_cek = " . intval($keranjang_id_cek) . " LIMIT 1"));
	if ($barang_id_cek > 0 && $keranjang_barang_option_sn < 1) {
		$keranjangParent = mysqli_query($conn, "select keranjang_qty, keranjang_qty_view, keranjang_konversi_isi, keranjang_satuan from keranjang where keranjang_id_cek = '" . $keranjang_id_cek . "'");
		$kp = mysqli_fetch_array($keranjangParent);
		// $kp += $keranjang_qty;
		$keranjang_qty_view_keranjang 		= $kp['keranjang_qty_view'];
		$keranjang_qty_keranjang 			= $kp['keranjang_qty'];
		$keranjang_konversi_isi_keranjang 	= $kp['keranjang_konversi_isi'];
		$keranjang_satuan_existing			= (int) ($kp['keranjang_satuan'] ?? $keranjang_satuan);

		$kqvk = $keranjang_qty_view_keranjang + $keranjang_qty;
		$kqkk = $keranjang_qty_keranjang + $keranjang_konversi_isi_keranjang;

		$tipeCustomer = (int) $customer;
		$hargaCols = beli_langsung_barang_harga_columns_sql();
		$barangHargaRows = query("SELECT $hargaCols FROM barang WHERE barang_id = $barang_id LIMIT 1");
		$hargaBaru = $keranjang_harga;
		if (!empty($barangHargaRows[0])) {
			$hargaBaru = beli_langsung_harga_keranjang_item($barangHargaRows[0], $tipeCustomer, $keranjang_satuan_existing);
		}

		$query = "UPDATE keranjang SET 
					keranjang_qty   	= '$kqkk',
					keranjang_qty_view  = '$kqvk',
					keranjang_harga     = '$hargaBaru',
					keranjang_harga_parent = '$hargaBaru',
					keranjang_tipe_customer = '$tipeCustomer'
					WHERE keranjang_id_cek = $keranjang_id_cek
					";
		mysqli_query($conn, $query);
		return mysqli_affected_rows($conn);
	} else {
		// query insert data
		$query = "INSERT INTO keranjang VALUES (null, 
		'$keranjang_nama', 
		'$keranjang_harga_beli', 
		'$keranjang_harga',
		'$keranjang_harga', 
		'0',
		'$keranjang_satuan', 
		'$barang_id', 
		'$barang_kode_slug', 
		'$keranjang_qty', 
		'$keranjang_qty', 
		'$keranjang_konversi_isi', 
		'$keranjang_barang_sn_id', 
		'$keranjang_barang_option_sn', 
		'$keranjang_sn', 
		'$keranjang_id_kasir', 
		'$keranjang_id_cek', 
		'$customer', 
		'$keranjang_cabang')";

		mysqli_query($conn, $query);

		return mysqli_affected_rows($conn);
	}
}

function tambahKeranjangDraft(
	$keranjang_cabang,
	$barang_id,
	$barang_kode_slug,
	$keranjang_nama,
	$keranjang_harga_beli,
	$keranjang_harga,
	$keranjang_satuan,
	$keranjang_id_kasir,
	$keranjang_qty,
	$keranjang_konversi_isi,
	$keranjang_barang_sn_id,
	$keranjang_barang_option_sn,
	$keranjang_sn,
	$keranjang_id_cek,
	$invoice,
	$customer
) {
	global $conn;


	// Cek STOCK
	$barang_id_cek = mysqli_num_rows(mysqli_query($conn, "select * from keranjang_draft where barang_id = " . $barang_id . " && keranjang_invoice = " . $invoice . " && keranjang_cabang = " . $keranjang_cabang . " "));

	if ($barang_id_cek > 0 && $keranjang_barang_option_sn < 1) {
		$keranjangParent = mysqli_query($conn, "select keranjang_qty, keranjang_qty_view, keranjang_konversi_isi from keranjang_draft where keranjang_id_cek = '" . $keranjang_id_cek . "'");
		$kp = mysqli_fetch_array($keranjangParent);
		// $kp += $keranjang_qty;
		$keranjang_qty_view_keranjang 		= $kp['keranjang_qty_view'];
		$keranjang_qty_keranjang 			= $kp['keranjang_qty'];
		$keranjang_konversi_isi_keranjang 	= $kp['keranjang_konversi_isi'];

		$kqvk = $keranjang_qty_view_keranjang + $keranjang_qty;
		$kqkk = $keranjang_qty_keranjang + $keranjang_konversi_isi_keranjang;

		$query = "UPDATE keranjang_draft SET 
					keranjang_qty   	= '$kqkk',
					keranjang_qty_view  = '$kqvk'
					WHERE keranjang_id_cek = $keranjang_id_cek
					";

		mysqli_query($conn, $query);
		return mysqli_affected_rows($conn);
	} else {
		// query insert data
		$query = "INSERT INTO keranjang_draft VALUES (null, 
		'$keranjang_nama', 
		'$keranjang_harga_beli', 
		'$keranjang_harga',
		'$keranjang_harga', 
		'0', 
		'$keranjang_satuan', 
		'$barang_id', 
		'$barang_kode_slug', 
		'$keranjang_qty', 
		'$keranjang_qty', 
		'$keranjang_konversi_isi', 
		'$keranjang_barang_sn_id', 
		'$keranjang_barang_option_sn', 
		'$keranjang_sn', 
		'$keranjang_id_kasir', 
		'$keranjang_id_cek', 
		'$customer', 
		'1',
		'$invoice',
		'$keranjang_cabang')";
		mysqli_query($conn, $query);

		return mysqli_affected_rows($conn);
	}
}

function tambahKeranjangBarcode($data)
{
	global $conn;

	$barang_kode 		= htmlspecialchars($data['inputbarcode']);
	$keranjang_id_kasir = $data['keranjang_id_kasir'];
	$tipe_harga 		= $data['tipe_harga'];
	$keranjang_cabang   = $data['keranjang_cabang'];

	// Ambil Data Barang berdasarkan Kode Barang 
	$barang 	= mysqli_query($conn, "select barang_id, 
		barang_nama, 
		barang_harga_beli, 
		barang_harga_beli_rata,
		barang_harga, 
		barang_harga_grosir_1, 
		barang_harga_grosir_2, 
		barang_stock, 
		barang_kode_slug, 
		satuan_id,
		satuan_isi_1,
		barang_option_sn from barang where barang_kode = '" . $barang_kode . "' && barang_cabang = " . $keranjang_cabang . " && barang_status = '1' ORDER BY barang_id DESC LIMIT 1");
	$br 		= mysqli_fetch_array($barang);

	// Barcode tidak ada / tidak aktif di cabang — cek dulu agar tidak TypeError (HTTP 500)
	if (!$br || empty($br['barang_id'])) {
		echo '
			<script>
				alert("Kode Produk Tidak ada di Data Master Barang dan Coba Cek Kembali !! ");
				document.location.reload();
			</script>
		';
		return 0;
	}

	$barang_id  				= $br["barang_id"];
	$keranjang_nama  			= $br["barang_nama"];
	$keranjang_harga_beli  		= barang_hpp_dari_row($br);
	$keranjang_satuan           = $br["satuan_id"];
	$keranjang_konversi_isi     = $br["satuan_isi_1"];

	if ($tipe_harga == 1) {
		$keranjang_harga  = $br["barang_harga_grosir_1"];
	} elseif ($tipe_harga == 2) {
		$keranjang_harga  = $br["barang_harga_grosir_2"];
	} else {
		$keranjang_harga  = $br["barang_harga"];
	}

	$barang_stock 				= $br["barang_stock"];
	$barang_kode_slug 			= $br["barang_kode_slug"];
	$keranjang_barang_option_sn = $br["barang_option_sn"];
	$keranjang_qty      		= 1;
	$keranjang_konversi_isi     = $br['satuan_isi_1'];
	$keranjang_barang_sn_id     = 0;
	$keranjang_sn       		= 0;
	$keranjang_tipe_customer    = $tipe_harga;
	$keranjang_id_cek   		= $barang_id . $keranjang_id_kasir . $keranjang_cabang;

	// Cek stok: jumlah di semua keranjang (semua kasir) + qty baru tidak boleh melebihi stok master
	if (!keranjangCanReserveQty($conn, $barang_id, $keranjang_cabang, $barang_stock, $keranjang_qty, $keranjang_konversi_isi)) {
		echo '
			<script>
				alert("Produk TIDAK BISA DITAMBAHKAN Karena Jumlah QTY Melebihi Stock yang Ada di Semua Transaksi Kasir & Mohon di Cek Kembali !!!");
				document.location.reload();
			</script>
		';
		return 0;
	}

	$barang_id_cek = mysqli_num_rows(mysqli_query($conn, "SELECT keranjang_id FROM keranjang WHERE keranjang_id_cek = " . intval($keranjang_id_cek) . " LIMIT 1"));

	if ($barang_id_cek > 0 && $keranjang_barang_option_sn < 1) {
		$keranjangParent = mysqli_query($conn, "select keranjang_qty, keranjang_qty_view, keranjang_konversi_isi, keranjang_satuan from keranjang where keranjang_id_cek = '" . $keranjang_id_cek . "'");
		$kp = mysqli_fetch_array($keranjangParent);
		// $kp += $keranjang_qty;
		$keranjang_qty_view_keranjang 		= $kp['keranjang_qty_view'];
		$keranjang_qty_keranjang 			= $kp['keranjang_qty'];
		$keranjang_konversi_isi_keranjang 	= $kp['keranjang_konversi_isi'];
		$keranjang_satuan_existing			= (int) ($kp['keranjang_satuan'] ?? $keranjang_satuan);

		$kqvk = $keranjang_qty_view_keranjang + $keranjang_qty;
		$kqkk = $keranjang_qty_keranjang + $keranjang_konversi_isi_keranjang;

		$tipeCustomer = (int) $keranjang_tipe_customer;
		$hargaCols = beli_langsung_barang_harga_columns_sql();
		$barangHargaRows = query("SELECT $hargaCols FROM barang WHERE barang_id = $barang_id LIMIT 1");
		$hargaBaru = $keranjang_harga;
		if (!empty($barangHargaRows[0])) {
			$hargaBaru = beli_langsung_harga_keranjang_item($barangHargaRows[0], $tipeCustomer, $keranjang_satuan_existing);
		}

		$query = "UPDATE keranjang SET 
					keranjang_qty   	= '$kqkk',
					keranjang_qty_view  = '$kqvk',
					keranjang_harga     = '$hargaBaru',
					keranjang_harga_parent = '$hargaBaru',
					keranjang_tipe_customer = '$tipeCustomer'
					WHERE keranjang_id_cek = $keranjang_id_cek
					";
		mysqli_query($conn, $query);
		return mysqli_affected_rows($conn);
	}

	// query insert data
	$query = "INSERT INTO keranjang VALUES (null, 
	'$keranjang_nama', 
	'$keranjang_harga_beli', 
	'$keranjang_harga',
	'$keranjang_harga', 
	'0',
	'$keranjang_satuan',
	'$barang_id', 
	'$barang_kode_slug', 
	'$keranjang_qty', 
	'$keranjang_qty',
	'$keranjang_konversi_isi',
	'$keranjang_barang_sn_id', 
	'$keranjang_barang_option_sn', 
	'$keranjang_sn', 
	'$keranjang_id_kasir', 
	'$keranjang_id_cek', 
	'$keranjang_tipe_customer',
	'$keranjang_cabang')";
	mysqli_query($conn, $query);

	return mysqli_affected_rows($conn);
}

function tambahKeranjangBarcodeDraft($data)
{
	global $conn;

	$barang_kode 		= htmlspecialchars($data['inputbarcodeDraft']);
	$keranjang_id_kasir = $data['keranjang_id_kasir'];
	$tipe_harga 		= $data['tipe_harga'];
	$keranjang_invoice  = $data['keranjang_invoice'];
	$keranjang_cabang   = $data['keranjang_cabang'];

	// Ambil Data Barang berdasarkan Kode Barang 
	$barang 	= mysqli_query($conn, "select barang_id, 
		barang_nama, 
		barang_harga_beli, 
		barang_harga_beli_rata,
		barang_harga, 
		barang_harga_grosir_1, 
		barang_harga_grosir_2, 
		barang_stock, 
		barang_kode_slug, 
		satuan_id,
		satuan_isi_1,
		barang_option_sn from barang where barang_kode = '" . $barang_kode . "' && barang_cabang = " . $keranjang_cabang . " ");
	$br 		= mysqli_fetch_array($barang);

	// Barcode tidak ada di cabang — cek dulu agar tidak TypeError (HTTP 500)
	if (!$br || empty($br['barang_id'])) {
		echo '
			<script>
				alert("Kode Produk Tidak ada di Data Master Barang dan Coba Cek Kembali !! ");
				document.location.reload();
			</script>
		';
		return 0;
	}

	$barang_id  				= $br["barang_id"];
	$keranjang_nama  			= $br["barang_nama"];
	$keranjang_harga_beli  		= barang_hpp_dari_row($br);
	$keranjang_satuan           = $br["satuan_id"];
	$keranjang_konversi_isi     = $br["satuan_isi_1"];

	if ($tipe_harga == 1) {
		$keranjang_harga  = $br["barang_harga_grosir_1"];
	} elseif ($tipe_harga == 2) {
		$keranjang_harga  = $br["barang_harga_grosir_2"];
	} else {
		$keranjang_harga  = $br["barang_harga"];
	}

	$barang_stock 				= $br["barang_stock"];
	$barang_kode_slug 			= $br["barang_kode_slug"];
	$keranjang_barang_option_sn = $br["barang_option_sn"];
	$keranjang_qty      		= 1;
	$keranjang_konversi_isi     = $br['satuan_isi_1'];
	$keranjang_barang_sn_id     = 0;
	$keranjang_sn       		= 0;
	$keranjang_tipe_customer    = $tipe_harga;
	$keranjang_id_cek   		= $barang_id . $keranjang_id_kasir . $keranjang_cabang;

	// Cek apakah data barang sudah sesuai dengan jumlah stok saat Insert Ke Keranjang dan jika melebihi stok maka akan dikembalikan
	$idBarang = mysqli_query($conn, "select keranjang_qty, keranjang_konversi_isi, keranjang_tipe_customer from keranjang_draft where barang_id = " . $barang_id . " ");
	$idBarang = mysqli_fetch_array($idBarang);
	$keranjang_qty_stock = ($idBarang['keranjang_qty'] ?? 0) + ($idBarang['keranjang_konversi_isi'] ?? 0);

	if ($keranjang_qty_stock >= $barang_stock) {
		echo '
			<script>
				alert("Produk TIDAK BISA DITAMBAHKAN Karena Jumlah QTY Melebihi Stock yang Ada di Semua Transaksi Kasir & Mohon di Cek Kembali !!!");
				document.location.reload();
			</script>
		';
		return 0;
	}

	// Cek STOCK
	$barang_id_cek = mysqli_num_rows(mysqli_query($conn, "select * from keranjang_draft where barang_id = " . $barang_id . " && keranjang_invoice = " . $keranjang_invoice . " && keranjang_cabang = " . $keranjang_cabang . " "));

	if ($barang_id_cek > 0 && $keranjang_barang_option_sn < 1) {
		$keranjangParent = mysqli_query($conn, "select keranjang_qty, keranjang_qty_view, keranjang_konversi_isi from keranjang_draft where keranjang_id_cek = '" . $keranjang_id_cek . "'");
		$kp = mysqli_fetch_array($keranjangParent);
		// $kp += $keranjang_qty;
		$keranjang_qty_view_keranjang 		= $kp['keranjang_qty_view'];
		$keranjang_qty_keranjang 			= $kp['keranjang_qty'];
		$keranjang_konversi_isi_keranjang 	= $kp['keranjang_konversi_isi'];

		$kqvk = $keranjang_qty_view_keranjang + $keranjang_qty;
		$kqkk = $keranjang_qty_keranjang + $keranjang_konversi_isi_keranjang;

		$query = "UPDATE keranjang_draft SET 
					keranjang_qty   	= '$kqkk',
					keranjang_qty_view  = '$kqvk'
					WHERE keranjang_id_cek = $keranjang_id_cek
					";
		mysqli_query($conn, $query);
		return mysqli_affected_rows($conn);
	}

	// query insert data
	$query = "INSERT INTO keranjang_draft VALUES ('', 
	'$keranjang_nama', 
	'$keranjang_harga_beli', 
	'$keranjang_harga', 
	'$keranjang_harga', 
	'0',
	'$keranjang_satuan',
	'$barang_id', 
	'$barang_kode_slug', 
	'$keranjang_qty', 
	'$keranjang_qty',
	'$keranjang_konversi_isi',
	'$keranjang_barang_sn_id', 
	'$keranjang_barang_option_sn', 
	'$keranjang_sn', 
	'$keranjang_id_kasir', 
	'$keranjang_id_cek', 
	'$keranjang_tipe_customer',
	'1',
	'$keranjang_invoice',
	'$keranjang_cabang')";
	mysqli_query($conn, $query);

	return mysqli_affected_rows($conn);
}

function updateSn($data)
{
	global $conn;
	$id = $data["keranjang_id"];


	// ambil data dari tiap elemen dalam form
	$barang_sn_id  = $data["barang_sn_id"];


	$barang_sn_desc = mysqli_query($conn, "select barang_sn_desc from barang_sn where barang_sn_id = '" . $barang_sn_id . "'");
	$barang_sn_desc = mysqli_fetch_array($barang_sn_desc);
	$barang_sn_desc = $barang_sn_desc['barang_sn_desc'];

	// query update data
	$query = "UPDATE keranjang SET 
						keranjang_barang_sn_id  = '$barang_sn_id',
						keranjang_sn            = '$barang_sn_desc'
						WHERE keranjang_id      = $id
				";

	$query2 = "UPDATE barang_sn SET 
						barang_sn_status     = 0
						WHERE barang_sn_id = $barang_sn_id
				";

	mysqli_query($conn, $query);
	mysqli_query($conn, $query2);

	return mysqli_affected_rows($conn);
}

function updateSnDrfat($data)
{
	global $conn;
	$id = $data["keranjang_draf_id"];


	// ambil data dari tiap elemen dalam form
	$barang_sn_id  = $data["barang_sn_id"];


	$barang_sn_desc = mysqli_query($conn, "select barang_sn_desc from barang_sn where barang_sn_id = '" . $barang_sn_id . "'");
	$barang_sn_desc = mysqli_fetch_array($barang_sn_desc);
	$barang_sn_desc = $barang_sn_desc['barang_sn_desc'];

	// query update data
	$query = "UPDATE keranjang_draft SET 
						keranjang_barang_sn_id  = '$barang_sn_id',
						keranjang_sn            = '$barang_sn_desc'
						WHERE keranjang_draf_id      = $id
				";

	$query2 = "UPDATE barang_sn SET 
						barang_sn_status     = 0
						WHERE barang_sn_id = $barang_sn_id
				";

	mysqli_query($conn, $query);
	mysqli_query($conn, $query2);

	return mysqli_affected_rows($conn);
}

// function updateHarga($data){
// 	global $conn;
// 	$id 				= $data["keranjang_id"];
// 	$keranjang_harga 	= htmlspecialchars($data["keranjang_harga"]);

// 	$query = "UPDATE keranjang SET 
// 						keranjang_harga  		= '$keranjang_harga'
// 						WHERE keranjang_id      = $id
// 				";

// 	mysqli_query($conn, $query);
// 	return mysqli_affected_rows($conn);
// }

// function updateQTY($data) {
// 	global $conn;
// 	$id = $data["keranjang_id"];

// 	// ambil data dari tiap elemen dalam form
// 	$keranjang_qty = htmlspecialchars($data['keranjang_qty']);
// 	$stock_brg = $data['stock_brg'];

// 	if ( $keranjang_qty > $stock_brg ) {
// 		echo"
// 			<script>
// 				alert('QTY Melebihi Stock Barang.. Coba Cek Lagi !!!');
// 				document.location.href = 'beli-langsung.php';
// 			</script>
// 		";
// 	} else {
// 		// query update data
// 		$query = "UPDATE keranjang SET 
// 					keranjang_qty   = '$keranjang_qty'
// 					WHERE keranjang_id = $id
// 					";
// 		mysqli_query($conn, $query);
// 		return mysqli_affected_rows($conn);
// 	}
// }

function updateQTYHarga($data)
{
	global $conn;
	$id = $data["keranjang_id"];

	// ambil data dari tiap elemen dalam form
	$keranjang_qty_view 		= htmlspecialchars($data['keranjang_qty_view']);
	$keranjang_barang_option_sn = $data['keranjang_barang_option_sn'];

	$keranjang_satuan_end_isi   = $data['keranjang_satuan_end_isi'] ?? '';
	$pecah_data 				= explode("-", $keranjang_satuan_end_isi);

	if ($keranjang_barang_option_sn < 1) {
		$keranjang_satuan   		= $pecah_data[0] ?? $data['keranjang_satuan'] ?? 0;
		$keranjang_konversi_isi 	= $pecah_data[1] ?? $data['keranjang_konversi_isi'] ?? 1;
		$checkboxHarga              = $data['checkbox-harga'] ?? 0;
		if ($checkboxHarga > 0) {
			$keranjang_harga 		= htmlspecialchars($data["keranjang_harga"]);
		} else {
			$keranjang_harga 	    = $pecah_data[2] ?? 0;
		}
	} else {
		$keranjang_satuan   		= $data['keranjang_satuan'] ?? 0;
		$keranjang_konversi_isi 	= $data['keranjang_konversi_isi'] ?? 1;
		$checkboxHarga              = $data['checkbox-harga'] ?? 0;
		$keranjang_harga 			= htmlspecialchars($data["keranjang_harga"]);
	}

	$stock_brg 			        = $data['stock_brg'];
	$keranjang_qty              = $keranjang_qty_view * $keranjang_konversi_isi;

	if ($keranjang_qty > $stock_brg) {
		echo "
			<script>
				alert('QTY Melebihi Stock Barang.. Coba Cek Lagi !!!');
				document.location.reload();
			</script>
		";
	} else {
		// query update data
		$query = "UPDATE keranjang SET 
					keranjang_harga  		= '$keranjang_harga',
					keranjang_harga_edit  	= '$checkboxHarga',
					keranjang_satuan        = '$keranjang_satuan',
					keranjang_qty   		= '$keranjang_qty',
					keranjang_qty_view   	= '$keranjang_qty_view',
					keranjang_konversi_isi  = '$keranjang_konversi_isi'
					WHERE keranjang_id 		= $id
					";
		mysqli_query($conn, $query);
		return mysqli_affected_rows($conn);
	}
}

function updateQTYHargaDraft($data)
{
	global $conn;
	$id = $data["keranjang_draf_id"];


	// ambil data dari tiap elemen dalam form
	$keranjang_qty_view 		= htmlspecialchars($data['keranjang_qty_view']);
	$keranjang_barang_option_sn = $data['keranjang_barang_option_sn'];

	$keranjang_satuan_end_isi   = $data['keranjang_satuan_end_isi'];
	$pecah_data 				= explode("-", $keranjang_satuan_end_isi);
	$keranjang_satuan   		= $pecah_data[0];
	$keranjang_konversi_isi 	= $pecah_data[1];

	if ($keranjang_barang_option_sn < 1) {
		$keranjang_harga 	        = $pecah_data[2];
	} else {
		$keranjang_harga 			= htmlspecialchars($data["keranjang_harga"]);
	}

	$stock_brg 			        = $data['stock_brg'];
	$keranjang_qty              = $keranjang_qty_view * $keranjang_konversi_isi;

	if ($keranjang_qty > $stock_brg) {
		echo "
			<script>
				alert('QTY Melebihi Stock Barang.. Coba Cek Lagi !!!');
				document.location.reload();
			</script>
		";
	} else {
		// query update data
		$query = "UPDATE keranjang_draft SET 
					keranjang_harga  		= '$keranjang_harga',
					keranjang_satuan        = '$keranjang_satuan',
					keranjang_qty   		= '$keranjang_qty',
					keranjang_qty_view   	= '$keranjang_qty_view',
					keranjang_konversi_isi  = '$keranjang_konversi_isi'
					WHERE keranjang_draf_id 		= $id
					";
		mysqli_query($conn, $query);
		return mysqli_affected_rows($conn);
	}
}

function hapusKeranjang($id)
{
	global $conn;


	// Ambil ID produk
	$data_id = $id;

	// Mencari keranjang_barang_sn_id
	$keranjang_barang_sn_id = mysqli_query($conn, "select keranjang_barang_sn_id from keranjang where keranjang_id = '" . $data_id . "'");
	$keranjang_barang_sn_id = mysqli_fetch_array($keranjang_barang_sn_id);
	$keranjang_barang_sn_id = $keranjang_barang_sn_id["keranjang_barang_sn_id"];



	if ($keranjang_barang_sn_id > 0) {
		$query2 = "UPDATE barang_sn SET 
					barang_sn_status    = 1
					WHERE barang_sn_id  = $keranjang_barang_sn_id
					";
		mysqli_query($conn, $query2);
	}

	mysqli_query($conn, "DELETE FROM keranjang WHERE keranjang_id = $id");

	return mysqli_affected_rows($conn);
}

function hapusKeranjangDraft($id)
{
	global $conn;
	// Ambil ID produk
	$data_id = $id;

	// Mencari keranjang_barang_sn_id
	$keranjang_barang_sn_id = mysqli_query($conn, "select keranjang_barang_sn_id from keranjang_draft where keranjang_draf_id = '" . $data_id . "'");
	$keranjang_barang_sn_id = mysqli_fetch_array($keranjang_barang_sn_id);
	$keranjang_barang_sn_id = $keranjang_barang_sn_id["keranjang_barang_sn_id"];


	if ($keranjang_barang_sn_id > 0) {
		$query2 = "UPDATE barang_sn SET 
					barang_sn_status    = 1
					WHERE barang_sn_id  = $keranjang_barang_sn_id
					";
		mysqli_query($conn, $query2);
	}

	mysqli_query($conn, "DELETE FROM keranjang_draft WHERE keranjang_draf_id = $id");

	return mysqli_affected_rows($conn);
}

/** Hitung total jual & beli dari item keranjang (sumber kebenaran di server). */
function updateStockCalcTotalsFromItems($keranjang_harga, $keranjang_qty_view, $keranjang_harga_beli, $keranjang_qty, $jumlah)
{
	$total = 0.0;
	$total_beli = 0.0;
	for ($x = 0; $x < $jumlah; $x++) {
		$total += floatval($keranjang_harga[$x] ?? 0) * floatval($keranjang_qty_view[$x] ?? 0);
		$total_beli += floatval($keranjang_harga_beli[$x] ?? 0) * floatval($keranjang_qty[$x] ?? 0);
	}
	return ['total' => $total, 'total_beli' => $total_beli];
}

/** Cek invoice sudah tersimpan lengkap (header + detail barang). */
function updateStockInvoiceIsComplete($conn, $penjualan_invoice, $invoice_cabang, $expectedItemCount)
{
	$invEsc = mysqli_real_escape_string($conn, $penjualan_invoice);
	$cabang = intval($invoice_cabang);
	$expected = intval($expectedItemCount);
	$sql = mysqli_query(
		$conn,
		"SELECT COUNT(*) AS cnt FROM penjualan WHERE penjualan_invoice = '$invEsc' AND penjualan_cabang = '$cabang'"
	);
	if (!$sql) {
		return false;
	}
	$row = mysqli_fetch_assoc($sql);
	return $row && intval($row['cnt']) >= $expected && $expected > 0;
}

function updateStock($data)
{
	global $conn;

	try {
		return updateStockProcess($data);
	} catch (Throwable $e) {
		error_log('updateStock exception: ' . $e->getMessage());
		if (empty($_SESSION['beli_langsung_alert'])) {
			$_SESSION['beli_langsung_alert'] = 'Transaksi gagal: ' . $e->getMessage();
		}
		return 0;
	}
}

function updateStockProcess($data)
{
	global $conn;
	
	// Validasi data yang diperlukan
	if (empty($data['barang_ids']) || !is_array($data['barang_ids'])) {
		error_log("Error: barang_ids is empty or not an array");
		return 0;
	}
	
	$id                  		= $data['barang_ids'];
	$keranjang_qty       		= $data['keranjang_qty'] ?? [];
	$keranjang_qty_view       	= $data['keranjang_qty_view'] ?? [];
	$keranjang_konversi_isi     = $data['keranjang_konversi_isi'] ?? [];
	$keranjang_satuan           = $data['keranjang_satuan'] ?? [];
	$keranjang_harga_beli       = $data['keranjang_harga_beli'] ?? [];
	$keranjang_harga			= $data['keranjang_harga'] ?? [];
	$keranjang_harga_parent		= $data['keranjang_harga_parent'] ?? [];
	$keranjang_harga_edit		= $data['keranjang_harga_edit'] ?? [];
	$keranjang_id_kasir  		= $data['keranjang_id_kasir'] ?? [];
	$penjualan_invoice   		= $data['penjualan_invoice'] ?? [];
	$keranjang_barang_option_sn = $data['keranjang_barang_option_sn'] ?? [];
	$keranjang_barang_sn_id     = $data['keranjang_barang_sn_id'] ?? [];
	$keranjang_sn               = $data['keranjang_sn'] ?? [];
	$invoice_customer_category2 = $data['invoice_customer_category2'] ?? [];
	$penjualan_cabang        	= $data['penjualan_cabang'] ?? [];

	$kik                 		= $data['kik'];
	$penjualan_invoice2  		= $data['penjualan_invoice2'];
	$invoice_tgl         		= date("d F Y g:i:s a");
	$invoice_ongkir      		= floatval($data['invoice_ongkir'] ?? 0);
	$invoice_diskon      		= floatval($data['invoice_diskon'] ?? 0);
	if (($data['angka1'] ?? '') === '' || ($data['angka1'] ?? null) === null) {
		$_SESSION['beli_langsung_alert'] = 'Anda Belum Input Nominal BAYAR !!!';
		return 0;
	}

	$invoice_date        		= date("Y-m-d");
	$invoice_date_year_month    = date("Y-m");
	$penjualan_date      		= $data['penjualan_date'];
	$invoice_customer    		= $data['invoice_customer'];
	$invoice_customer_category  = $data['invoice_customer_category'];
	$invoice_kurir    	 		= $data['invoice_kurir'];
	$invoice_tipe_transaksi  	= $data['invoice_tipe_transaksi'];
	$penjualan_invoice_count 	= $data['penjualan_invoice_count'];
	$invoice_piutang			= $data['invoice_piutang'];
	$invoice_piutang_jatuh_tempo = $data['invoice_piutang_jatuh_tempo'];
	$invoice_piutang_lunas		= $data['invoice_piutang_lunas'];
	$invoice_cabang             = $data['invoice_cabang'];

	if (!beli_langsung_assert_customer_transaksi(
		$conn,
		(int) $invoice_customer,
		(int) $invoice_customer_category,
		(int) $invoice_cabang,
		(int) $invoice_piutang
	)) {
		return 0;
	}

	if ($invoice_customer == 1) {
		$invoice_marketplace = htmlspecialchars($data['invoice_marketplace']);
		$invoice_ekspedisi   = htmlspecialchars($data['invoice_ekspedisi']);
		$invoice_no_resi     = htmlspecialchars($data['invoice_no_resi']);
	} else {
		$invoice_marketplace = "";
		$invoice_ekspedisi   = 0;
		$invoice_no_resi     = "-";
	}
	// Pastikan $keranjang_id_kasir adalah array
	if (!is_array($keranjang_id_kasir)) {
		error_log("Error: keranjang_id_kasir is not an array. Value: " . print_r($keranjang_id_kasir, true));
		return 0;
	}
	
	$jumlah = count($keranjang_id_kasir);
	
	// Validasi jumlah item
	if ($jumlah == 0) {
		error_log("Error: No items in cart. keranjang_id_kasir count: " . $jumlah);
		error_log("Data received: " . print_r($data, true));
		return 0;
	}
	
	// Debug: Log jumlah item
	error_log("Processing " . $jumlah . " items for invoice: " . $penjualan_invoice2);

	$calcTotals = updateStockCalcTotalsFromItems(
		$keranjang_harga,
		$keranjang_qty_view,
		$keranjang_harga_beli,
		$keranjang_qty,
		$jumlah
	);
	$invoice_total = $calcTotals['total'];
	$invoice_total_beli = $calcTotals['total_beli'];
	$invoice_sub_total = $invoice_total + $invoice_ongkir - $invoice_diskon;
	$invoice_bayar = floatval(preg_replace('/[^\d.-]/', '', (string) ($data['angka1'] ?? '')));
	$invoice_kembali = $invoice_bayar - $invoice_sub_total;
	if ($invoice_piutang == 1) {
		$invoice_piutang_dp = $invoice_bayar;
	} else {
		$invoice_piutang_dp = 0;
	}

	if ($invoice_piutang == 0 && $invoice_bayar < $invoice_sub_total) {
		$_SESSION['beli_langsung_alert'] = 'Transaksi TIDAK BISA Dilanjutkan! Nominal bayar lebih kecil dari total. Gunakan Piutang jika nominal kurang.';
		return 0;
	} elseif ($invoice_piutang == 1 && $invoice_bayar >= $invoice_sub_total) {
		$_SESSION['beli_langsung_alert'] = 'Transaksi TIDAK BISA Dilanjutkan! Nominal DP lebih besar/sama dengan total piutang. Gunakan Cash jika lunas.';
		return 0;
	} else {
		// Escape semua nilai untuk keamanan
		$penjualan_invoice2 = mysqli_real_escape_string($conn, $penjualan_invoice2);
		$penjualan_invoice_count = mysqli_real_escape_string($conn, $penjualan_invoice_count);
		$invoice_tgl = mysqli_real_escape_string($conn, $invoice_tgl);
		$invoice_customer = mysqli_real_escape_string($conn, $invoice_customer);
		$invoice_customer_category = mysqli_real_escape_string($conn, $invoice_customer_category);
		$invoice_kurir = mysqli_real_escape_string($conn, $invoice_kurir);
		$invoice_tipe_transaksi = mysqli_real_escape_string($conn, $invoice_tipe_transaksi);
		$invoice_total_beli = floatval($invoice_total_beli);
		$invoice_total = floatval($invoice_total);
		$invoice_ongkir = floatval($invoice_ongkir);
		$invoice_diskon = floatval($invoice_diskon);
		$invoice_sub_total = floatval($invoice_sub_total);
		$invoice_bayar = floatval($invoice_bayar);
		$invoice_kembali = floatval($invoice_kembali);
		$kik = intval($kik);
		$invoice_date = mysqli_real_escape_string($conn, $invoice_date);
		$invoice_date_year_month = mysqli_real_escape_string($conn, $invoice_date_year_month);
		$invoice_marketplace = mysqli_real_escape_string($conn, $invoice_marketplace);
		$invoice_ekspedisi = mysqli_real_escape_string($conn, $invoice_ekspedisi);
		$invoice_no_resi = mysqli_real_escape_string($conn, $invoice_no_resi);
		$invoice_piutang = intval($invoice_piutang);
		$invoice_piutang_dp = floatval($invoice_piutang_dp);
		$invoice_piutang_jatuh_tempo = mysqli_real_escape_string($conn, $invoice_piutang_jatuh_tempo);
		$invoice_piutang_lunas = intval($invoice_piutang_lunas);
		$invoice_cabang = intval($invoice_cabang);

		if (!mysqli_begin_transaction($conn)) {
			$_SESSION['beli_langsung_alert'] = 'Transaksi gagal memulai. Silakan coba lagi.';
			return 0;
		}

		// Kunci keranjang kasir (sesuai tipe customer aktif) — cegah proses paralel & double submit
		$tipeCustomerKeranjang = intval($invoice_customer_category);
		$cartLock = mysqli_query(
			$conn,
			"SELECT keranjang_id FROM keranjang WHERE keranjang_id_kasir = $kik AND keranjang_cabang = $invoice_cabang AND keranjang_tipe_customer = $tipeCustomerKeranjang FOR UPDATE"
		);
		if (!$cartLock) {
			mysqli_rollback($conn);
			$_SESSION['beli_langsung_alert'] = 'Transaksi gagal. Silakan muat ulang halaman.';
			return 0;
		}
		$cartCount = mysqli_num_rows($cartLock);
		if ($cartCount < 1 || $cartCount !== $jumlah) {
			mysqli_rollback($conn);
			$_SESSION['beli_langsung_alert'] = 'Keranjang berubah atau sudah diproses. Muat ulang halaman lalu coba lagi.';
			return 0;
		}

		if (updateStockInvoiceIsComplete($conn, $penjualan_invoice2, $invoice_cabang, $jumlah)) {
			mysqli_commit($conn);
			return 1;
		}

		$dupInv = mysqli_query(
			$conn,
			"SELECT invoice_id FROM invoice WHERE penjualan_invoice = '$penjualan_invoice2' AND invoice_cabang = $invoice_cabang LIMIT 1"
		);
		if ($dupInv && mysqli_num_rows($dupInv) > 0) {
			mysqli_rollback($conn);
			$_SESSION['beli_langsung_alert'] = 'Nomor invoice sudah dipakai tapi data tidak lengkap. Muat ulang halaman untuk nomor invoice baru.';
			return 0;
		}
		
		// query insert invoice (invoice_id AUTO_INCREMENT — jangan kirim '')
		$query1 = "INSERT INTO invoice (
			penjualan_invoice, penjualan_invoice_count, invoice_tgl, invoice_customer, invoice_customer_category,
			invoice_kurir, invoice_status_kurir, status, invoice_tipe_transaksi, invoice_total_beli, invoice_total,
			invoice_ongkir, invoice_diskon, invoice_sub_total, invoice_bayar, invoice_kembali, invoice_kasir,
			invoice_date, invoice_date_year_month, invoice_date_edit, invoice_kasir_edit, invoice_total_beli_lama,
			invoice_total_lama, invoice_ongkir_lama, invoice_sub_total_lama, invoice_bayar_lama, invoice_kembali_lama,
			invoice_marketplace, invoice_ekspedisi, invoice_no_resi, invoice_date_selesai_kurir, invoice_piutang,
			invoice_piutang_dp, invoice_piutang_jatuh_tempo, invoice_piutang_lunas, invoice_draft, invoice_cabang
		) VALUES (
			'$penjualan_invoice2', '$penjualan_invoice_count', '$invoice_tgl', '$invoice_customer', '$invoice_customer_category',
			'$invoice_kurir', '1', '2', '$invoice_tipe_transaksi', '$invoice_total_beli', '$invoice_total',
			'$invoice_ongkir', '$invoice_diskon', '$invoice_sub_total', '$invoice_bayar', '$invoice_kembali', '$kik',
			'$invoice_date', '$invoice_date_year_month', ' ', ' ', '$invoice_total_beli', '$invoice_total',
			'$invoice_ongkir', '$invoice_sub_total', '$invoice_bayar', '$invoice_kembali', '$invoice_marketplace',
			'$invoice_ekspedisi', '$invoice_no_resi', '-', '$invoice_piutang', '$invoice_piutang_dp',
			'$invoice_piutang_jatuh_tempo', '$invoice_piutang_lunas', 0, '$invoice_cabang'
		)";
		
		// Debug: Log query sebelum eksekusi
		error_log("Inserting invoice: " . $penjualan_invoice2);
		
		$result_invoice = mysqli_query($conn, $query1);
		if (!$result_invoice) {
			$error_msg = mysqli_error($conn);
			error_log("Error inserting invoice: " . $error_msg);
			error_log("Query: " . $query1);
			error_log("Invoice data: " . print_r([
				'invoice' => $penjualan_invoice2,
				'customer' => $invoice_customer,
				'total' => $invoice_sub_total,
				'bayar' => $invoice_bayar
			], true));
			mysqli_rollback($conn);
			$_SESSION['beli_langsung_alert'] = 'Transaksi gagal menyimpan invoice. Silakan coba lagi.';
			return 0;
		}
		
		// Debug: Log success
		error_log("Invoice inserted successfully: " . $penjualan_invoice2);

		for ($x = 0; $x < $jumlah; $x++) {
			// Escape semua nilai untuk keamanan
			$barang_id = intval($id[$x] ?? 0);
			$qty_view = floatval($keranjang_qty_view[$x] ?? 0);
			$qty = floatval($keranjang_qty[$x] ?? 0);
			$konversi_isi = floatval($keranjang_konversi_isi[$x] ?? 1);
			$satuan = intval($keranjang_satuan[$x] ?? 0);
			$harga_beli = floatval($keranjang_harga_beli[$x] ?? 0);
			$harga = floatval($keranjang_harga[$x] ?? 0);
			$harga_parent = floatval($keranjang_harga_parent[$x] ?? 0);
			$harga_edit = floatval($keranjang_harga_edit[$x] ?? 0);
			$id_kasir = intval($keranjang_id_kasir[$x] ?? 0);
			$penjualan_inv = mysqli_real_escape_string($conn, $penjualan_invoice[$x] ?? $penjualan_invoice2);
			$penjualan_dt = mysqli_real_escape_string($conn, $penjualan_date[$x] ?? date('Y-m-d'));
			$date_ym = mysqli_real_escape_string($conn, $invoice_date_year_month);
			$option_sn = intval($keranjang_barang_option_sn[$x]);
			$sn_id = !empty($keranjang_barang_sn_id[$x]) ? intval($keranjang_barang_sn_id[$x]) : 0;
			$sn = mysqli_real_escape_string($conn, $keranjang_sn[$x] ?? '');
			$customer_cat = intval($invoice_customer_category2[$x]);
			$cabang = intval($penjualan_cabang[$x]);
			
			$query = "INSERT INTO penjualan (
				penjualan_barang_id, barang_id, barang_qty, barang_qty_keranjang, barang_qty_konversi_isi,
				keranjang_satuan, keranjang_harga_beli, keranjang_harga, keranjang_harga_parent, keranjang_harga_edit,
				keranjang_id_kasir, penjualan_invoice, penjualan_date, penjualan_date_year_month, barang_qty_lama,
				barang_qty_lama_parent, barang_option_sn, barang_sn_id, barang_sn_desc, invoice_customer_category, penjualan_cabang
			) VALUES (
				'$barang_id', '$barang_id', '$qty_view', '$qty', '$konversi_isi', '$satuan', '$harga_beli', '$harga',
				'$harga_parent', '$harga_edit', '$id_kasir', '$penjualan_inv', '$penjualan_dt', '$date_ym',
				'$qty_view', '$qty_view', '$option_sn', '$sn_id', '$sn', '$customer_cat', '$cabang'
			)";
			$terlarisId = pos_table_next_id($conn, 'terlaris', 'terlaris_id');
			$query2 = "INSERT INTO terlaris (terlaris_id, barang_id, barang_terjual) VALUES ($terlarisId, '$barang_id', '$qty')";

			$result_penjualan = mysqli_query($conn, $query);
			if (!$result_penjualan) {
				$error_msg = mysqli_error($conn);
				error_log("Error inserting penjualan: " . $error_msg);
				error_log("Query: " . $query);
				mysqli_rollback($conn);
				$_SESSION['beli_langsung_alert'] = 'Transaksi gagal menyimpan detail barang. Silakan coba lagi.';
				return 0;
			}
			
			$result_terlaris = mysqli_query($conn, $query2);
			if (!$result_terlaris) {
				$error_msg = mysqli_error($conn);
				error_log("Error inserting terlaris: " . $error_msg);
				mysqli_rollback($conn);
				$_SESSION['beli_langsung_alert'] = 'Transaksi gagal menyimpan data terlaris. Silakan coba lagi.';
				return 0;
			}
			
			if (!penjualan_stock_after_insert($conn, $barang_id, $qty_view, $konversi_isi)) {
				mysqli_rollback($conn);
				$_SESSION['beli_langsung_alert'] = 'Transaksi gagal memperbarui stok barang. Silakan coba lagi.';
				return 0;
			}

			// Update status barang_sn jika menggunakan SN
			if ($keranjang_barang_option_sn[$x] > 0 && !empty($keranjang_barang_sn_id[$x])) {
				$barang_sn_id = $keranjang_barang_sn_id[$x];
				$query_update_sn = "UPDATE barang_sn SET barang_sn_status = 2 WHERE barang_sn_id = $barang_sn_id";
				if (!mysqli_query($conn, $query_update_sn)) {
					mysqli_rollback($conn);
					$_SESSION['beli_langsung_alert'] = 'Transaksi gagal memperbarui nomor SN. Silakan coba lagi.';
					return 0;
				}
			}
		}

		if (!mysqli_query($conn, "DELETE FROM keranjang WHERE keranjang_id_kasir = $kik AND keranjang_cabang = $invoice_cabang AND keranjang_tipe_customer = $tipeCustomerKeranjang")) {
			mysqli_rollback($conn);
			$_SESSION['beli_langsung_alert'] = 'Transaksi gagal mengosongkan keranjang. Silakan coba lagi.';
			return 0;
		}
		
		akun_posting_setelah_penjualan(
			$conn,
			(int) $invoice_cabang,
			(int) $invoice_piutang,
			(int) $invoice_tipe_transaksi,
			(float) $invoice_sub_total,
			(float) $invoice_piutang_dp
		);

		if (!mysqli_commit($conn)) {
			mysqli_rollback($conn);
			$_SESSION['beli_langsung_alert'] = 'Transaksi gagal menyimpan. Silakan coba lagi.';
			return 0;
		}

		return 1;
	}
	return 0;
}

// Fungsi helper untuk update saldo laba_kategori
function updateSaldoLabaKategori($conn, $kode_akun, $name, $kategori, $tipe_akun, $jumlah, $cabang, $cabang_column_exists) {
	// Kas tunai toko: lewat akun_update_saldo_delta agar auto-mirror ke Nugrosir
	if (function_exists('akun_kas_tunai_perlu_mirror_nugrosir')
		&& function_exists('akun_update_saldo_delta')
		&& akun_kas_tunai_perlu_mirror_nugrosir($kode_akun)
	) {
		akun_update_saldo_delta($conn, $kode_akun, $name, $kategori, $tipe_akun, (float) $jumlah, (int) $cabang);
		return;
	}

	// Cari akun dengan kode_akun dan cabang yang sesuai
	if ($cabang_column_exists) {
		// Cari akun dengan cabang yang sama terlebih dahulu
		$query = "SELECT id, saldo FROM laba_kategori WHERE kode_akun = '$kode_akun' AND cabang = $cabang LIMIT 1";
		$result = mysqli_query($conn, $query);
		
		// Jika tidak ditemukan, cari cabang 0 atau NULL
		if (!$result || mysqli_num_rows($result) == 0) {
			$query = "SELECT id, saldo FROM laba_kategori WHERE kode_akun = '$kode_akun' AND (cabang = 0 OR cabang IS NULL) ORDER BY cabang DESC LIMIT 1";
			$result = mysqli_query($conn, $query);
		}
	} else {
		$query = "SELECT id, saldo FROM laba_kategori WHERE kode_akun = '$kode_akun' LIMIT 1";
		$result = mysqli_query($conn, $query);
	}
	
	if ($result && mysqli_num_rows($result) > 0) {
		// Akun sudah ada, update saldo
		$row = mysqli_fetch_assoc($result);
		$saldo_sekarang = floatval($row['saldo']);
		$saldo_baru = $saldo_sekarang + $jumlah;
		
		$update_query = "UPDATE laba_kategori SET saldo = $saldo_baru WHERE id = " . intval($row['id']);
		mysqli_query($conn, $update_query);
		if (function_exists('akun_link_after_saldo_update_by_id')) {
			akun_link_after_saldo_update_by_id($conn, (int) $row['id'], (float) $jumlah);
		}
	} else {
		// Akun belum ada, buat baru dengan cabang yang sesuai
		// Cari kategori untuk menentukan tipe_akun yang tepat
		$query_kategori = "SELECT kategori, tipe_akun FROM laba_kategori WHERE kategori = '$kategori' LIMIT 1";
		$result_kategori = mysqli_query($conn, $query_kategori);
		
		$tipe_akun_final = $tipe_akun;
		if ($result_kategori && mysqli_num_rows($result_kategori) > 0) {
			$row_kategori = mysqli_fetch_assoc($result_kategori);
			$tipe_akun_final = $row_kategori['tipe_akun'] ?? $tipe_akun;
		}
		
		if ($cabang_column_exists) {
			$insert_query = "INSERT INTO laba_kategori (name, kode_akun, kategori, tipe_akun, saldo, cabang) VALUES ('$name', '$kode_akun', '$kategori', '$tipe_akun_final', $jumlah, $cabang)";
		} else {
			$insert_query = "INSERT INTO laba_kategori (name, kode_akun, kategori, tipe_akun, saldo) VALUES ('$name', '$kode_akun', '$kategori', '$tipe_akun_final', $jumlah)";
		}
		
		mysqli_query($conn, $insert_query);
		if (function_exists('akun_kas_tunai_perlu_mirror_nugrosir') && akun_kas_tunai_perlu_mirror_nugrosir($kode_akun)
			&& function_exists('akun_sync_kas_tunai_mirror_nugrosir')
		) {
			akun_sync_kas_tunai_mirror_nugrosir($conn, $kode_akun);
		}
	}
}

function updateStockDraft($data)
{
	global $conn;
	$id                  		= $data['barang_ids'];
	$keranjang_qty       		= $data['keranjang_qty'];
	$keranjang_qty_view       	= $data['keranjang_qty_view'];
	$keranjang_konversi_isi     = $data['keranjang_konversi_isi'];
	$keranjang_satuan           = $data['keranjang_satuan'];
	$keranjang_harga_beli       = $data['keranjang_harga_beli'];
	$keranjang_harga			= $data['keranjang_harga'];
	$keranjang_harga_parent		= $data['keranjang_harga_parent'];
	$keranjang_harga_edit		= $data['keranjang_harga_edit'];
	$keranjang_id_kasir  		= $data['keranjang_id_kasir'];
	$penjualan_invoice   		= $data['penjualan_invoice'];
	$keranjang_barang_option_sn = $data['keranjang_barang_option_sn'];
	$keranjang_barang_sn_id     = $data['keranjang_barang_sn_id'];
	$keranjang_sn               = $data['keranjang_sn'];
	$invoice_customer_category2 = $data['invoice_customer_category2'];
	$keranjang_nama 			= $data['keranjang_nama'];
	$barang_kode_slug 			= $data['barang_kode_slug'];
	$keranjang_id_cek 			= $data['keranjang_id_cek'];
	$penjualan_cabang        	= $data['penjualan_cabang'];

	$kik                 		= $data['kik'];
	$penjualan_invoice2  		= $data['penjualan_invoice2'];
	$invoice_tgl         		= date("d F Y g:i:s a");
	$invoice_total_beli       	= $data['invoice_total_beli'];
	$invoice_total       		= $data['invoice_total'];
	$invoice_ongkir      		= htmlspecialchars($data['invoice_ongkir']);
	$invoice_diskon      		= htmlspecialchars($data['invoice_diskon']);

	$invoice_sub_total   		= $invoice_total + $invoice_ongkir;
	$invoice_sub_total   		= $invoice_sub_total - $invoice_diskon;
	$invoice_bayar       		= htmlspecialchars($data['angka1']);


	$invoice_kembali     		= $invoice_bayar - $invoice_sub_total;
	$invoice_date        		= date("Y-m-d");
	$invoice_date_year_month    = date("Y-m");
	$penjualan_date      		= $data['penjualan_date'];
	$invoice_customer    		= $data['invoice_customer'];
	$invoice_customer_category  = $data['invoice_customer_category'];
	$invoice_kurir    	 		= $data['invoice_kurir'];
	$invoice_tipe_transaksi  	= $data['invoice_tipe_transaksi'];
	$penjualan_invoice_count 	= $data['penjualan_invoice_count'];
	$invoice_piutang			= $data['invoice_piutang'];
	if ($invoice_piutang == 1) {
		$invoice_piutang_dp = $invoice_bayar;
	} else {
		$invoice_piutang_dp = 0;
	}
	$invoice_piutang_jatuh_tempo = $data['invoice_piutang_jatuh_tempo'];
	$invoice_piutang_lunas		= $data['invoice_piutang_lunas'];
	$invoice_cabang             = $data['invoice_cabang'];

	if (!beli_langsung_assert_customer_transaksi(
		$conn,
		(int) $invoice_customer,
		(int) $invoice_customer_category,
		(int) $invoice_cabang,
		(int) $invoice_piutang
	)) {
		return 0;
	}

	if ($invoice_customer == 1) {
		$invoice_marketplace = htmlspecialchars($data['invoice_marketplace']);
		$invoice_ekspedisi   = htmlspecialchars($data['invoice_ekspedisi']);
		$invoice_no_resi     = htmlspecialchars($data['invoice_no_resi']);
	} else {
		$invoice_marketplace = "";
		$invoice_ekspedisi   = 0;
		$invoice_no_resi     = "-";
	}
	$jumlah = count($keranjang_id_kasir);


	// query insert invoice draft (invoice_id AUTO_INCREMENT — jangan kirim '')
	$query1 = "INSERT INTO invoice (
		penjualan_invoice, penjualan_invoice_count, invoice_tgl, invoice_customer, invoice_customer_category,
		invoice_kurir, invoice_status_kurir, status, invoice_tipe_transaksi, invoice_total_beli, invoice_total,
		invoice_ongkir, invoice_diskon, invoice_sub_total, invoice_bayar, invoice_kembali, invoice_kasir,
		invoice_date, invoice_date_year_month, invoice_date_edit, invoice_kasir_edit, invoice_total_beli_lama,
		invoice_total_lama, invoice_ongkir_lama, invoice_sub_total_lama, invoice_bayar_lama, invoice_kembali_lama,
		invoice_marketplace, invoice_ekspedisi, invoice_no_resi, invoice_date_selesai_kurir, invoice_piutang,
		invoice_piutang_dp, invoice_piutang_jatuh_tempo, invoice_piutang_lunas, invoice_draft, invoice_cabang
	) VALUES (
		'$penjualan_invoice2', '$penjualan_invoice_count', '$invoice_tgl', '$invoice_customer', '$invoice_customer_category',
		'$invoice_kurir', '1', NULL, '$invoice_tipe_transaksi', '$invoice_total_beli', '$invoice_total',
		'$invoice_ongkir', '$invoice_diskon', '$invoice_sub_total', '$invoice_bayar', '$invoice_kembali', '$kik',
		'$invoice_date', '$invoice_date_year_month', ' ', ' ', '$invoice_total_beli', '$invoice_total',
		'$invoice_ongkir', '$invoice_sub_total', '$invoice_bayar', '$invoice_kembali', '$invoice_marketplace',
		'$invoice_ekspedisi', '$invoice_no_resi', '-', '$invoice_piutang', '$invoice_piutang_dp',
		'$invoice_piutang_jatuh_tempo', '$invoice_piutang_lunas', 1, '$invoice_cabang'
	)";
	// var_dump($query1); die();
	mysqli_query($conn, $query1);

	for ($x = 0; $x < $jumlah; $x++) {

		$query = "INSERT INTO keranjang_draft VALUES ('', '$keranjang_nama[$x]', '$keranjang_harga_beli[$x]', '$keranjang_harga[$x]', '$keranjang_harga_parent[$x]', '$keranjang_harga_edit[$x]', '$keranjang_satuan[$x]', '$id[$x]', '$barang_kode_slug[$x]', '$keranjang_qty[$x]', '$keranjang_qty_view[$x]', '$keranjang_konversi_isi[$x]', '$keranjang_barang_sn_id[$x]', '$keranjang_barang_option_sn[$x]', '$keranjang_sn[$x]', '$keranjang_id_kasir[$x]', '$keranjang_id_cek[$x]', '$invoice_customer_category2[$x]', 1, '$penjualan_invoice[$x]', '$penjualan_cabang[$x]')";
		mysqli_query($conn, $query);
	}


	mysqli_query($conn, "DELETE FROM keranjang WHERE keranjang_id_kasir = $kik");
	return mysqli_affected_rows($conn);
}


function updateStockSaveDraft($data)
{
	global $conn;
	$id                  		= $data['barang_ids'];
	$keranjang_qty       		= $data['keranjang_qty'];
	$keranjang_qty_view       	= $data['keranjang_qty_view'];
	$keranjang_konversi_isi     = $data['keranjang_konversi_isi'];
	$keranjang_satuan           = $data['keranjang_satuan'];
	$keranjang_harga_beli       = $data['keranjang_harga_beli'];
	$keranjang_harga			= $data['keranjang_harga'];
	$keranjang_harga_parent		= $data['keranjang_harga_parent'];
	$keranjang_harga_edit		= $data['keranjang_harga_edit'];
	$keranjang_id_kasir  		= $data['keranjang_id_kasir'];
	$penjualan_invoice   		= $data['penjualan_invoice'];
	$keranjang_barang_option_sn = $data['keranjang_barang_option_sn'];
	$keranjang_barang_sn_id     = $data['keranjang_barang_sn_id'];
	$keranjang_sn               = $data['keranjang_sn'];
	$invoice_customer_category2 = $data['invoice_customer_category2'];
	$penjualan_cabang        	= $data['penjualan_cabang'];

	$invoice_id 				= $data['invoice_id'];
	$kik                 		= $data['kik'];
	$penjualan_invoice2  		= $data['penjualan_invoice2'];
	$invoice_tgl         		= date("d F Y g:i:s a");
	$invoice_total_beli       	= $data['invoice_total_beli'];
	$invoice_total       		= $data['invoice_total'];
	$invoice_ongkir      		= htmlspecialchars($data['invoice_ongkir']);
	$invoice_diskon      		= htmlspecialchars($data['invoice_diskon']);

	$invoice_sub_total   		= $invoice_total + $invoice_ongkir;
	$invoice_sub_total   		= $invoice_sub_total - $invoice_diskon;
	$invoice_bayar       		= htmlspecialchars($data['angka1']);


	$invoice_kembali     		= $invoice_bayar - $invoice_sub_total;
	$invoice_date        		= date("Y-m-d");
	$invoice_date_year_month    = date("Y-m");
	$penjualan_date      		= $data['penjualan_date'];
	$invoice_customer    		= $data['invoice_customer'];
	$invoice_customer_category  = $data['invoice_customer_category'];
	$invoice_kurir    	 		= $data['invoice_kurir'];
	$invoice_tipe_transaksi  	= $data['invoice_tipe_transaksi'];
	$penjualan_invoice_count 	= $data['penjualan_invoice_count'];
	$invoice_piutang			= $data['invoice_piutang'];
	if ($invoice_piutang == 1) {
		$invoice_piutang_dp = $invoice_bayar;
	} else {
		$invoice_piutang_dp = 0;
	}
	$invoice_piutang_jatuh_tempo = $data['invoice_piutang_jatuh_tempo'];
	$invoice_piutang_lunas		= $data['invoice_piutang_lunas'];
	$invoice_cabang             = $data['invoice_cabang'];

	if (!beli_langsung_assert_customer_transaksi(
		$conn,
		(int) $invoice_customer,
		(int) $invoice_customer_category,
		(int) $invoice_cabang,
		(int) $invoice_piutang
	)) {
		return 0;
	}

	if ($invoice_customer == 1) {
		$invoice_marketplace = htmlspecialchars($data['invoice_marketplace']);
		$invoice_ekspedisi   = htmlspecialchars($data['invoice_ekspedisi']);
		$invoice_no_resi     = htmlspecialchars($data['invoice_no_resi']);
	} else {
		$invoice_marketplace = "";
		$invoice_ekspedisi   = 0;
		$invoice_no_resi     = "-";
	}
	$jumlah = count($keranjang_id_kasir);


	if ($invoice_bayar == null) {
		echo "
			<script>
				alert('Anda Belum Input Nominal BAYAR !!!');
				document.location.reload();
			</script>
		";
	} else {
		// query Update invoice
		$query1 = "UPDATE invoice SET  
				invoice_tgl 				= '$invoice_tgl', 
				invoice_customer 			= '$invoice_customer', 
				invoice_customer_category 	= '$invoice_customer_category', 
				invoice_tipe_transaksi 		= '$invoice_tipe_transaksi', 
				invoice_total_beli 			= '$invoice_total_beli', 
				invoice_total 				= '$invoice_total', 
				invoice_ongkir 				= '$invoice_ongkir', 
				invoice_diskon 				= '$invoice_diskon', 
				invoice_sub_total 			= '$invoice_sub_total', 
				invoice_bayar 				= '$invoice_bayar', 
				invoice_kembali 			= '$invoice_kembali', 
				invoice_kasir 				= '$kik', 
				invoice_date 				= '$invoice_date', 
				invoice_date_year_month 	= '$invoice_date_year_month', 
				invoice_total_beli_lama 	= '$invoice_total_beli', 
				invoice_total_lama 			= '$invoice_total', 
				invoice_ongkir_lama 		= '$invoice_ongkir', 
				invoice_sub_total_lama 		= '$invoice_sub_total', 
				invoice_bayar_lama 			= '$invoice_bayar', 
				invoice_kembali_lama 		= '$invoice_kembali',  
				invoice_piutang 			= '$invoice_piutang', 
				invoice_piutang_dp 			= '$invoice_piutang_dp', 
				invoice_piutang_jatuh_tempo = '$invoice_piutang_jatuh_tempo', 
				invoice_piutang_lunas 		= '$invoice_piutang_lunas', 
				invoice_draft 				= 0, 
				invoice_cabang 				= '$invoice_cabang'
				WHERE invoice_id 			= $invoice_id
		";
		// var_dump($query1); die();
		mysqli_query($conn, $query1);

		for ($x = 0; $x < $jumlah; $x++) {
			$query = "INSERT INTO penjualan (
				penjualan_barang_id, barang_id, barang_qty, barang_qty_keranjang, barang_qty_konversi_isi,
				keranjang_satuan, keranjang_harga_beli, keranjang_harga, keranjang_harga_parent, keranjang_harga_edit,
				keranjang_id_kasir, penjualan_invoice, penjualan_date, penjualan_date_year_month, barang_qty_lama,
				barang_qty_lama_parent, barang_option_sn, barang_sn_id, barang_sn_desc, invoice_customer_category, penjualan_cabang
			) VALUES (
				'$id[$x]', '$id[$x]', '$keranjang_qty_view[$x]', '$keranjang_qty[$x]', '$keranjang_konversi_isi[$x]',
				'$keranjang_satuan[$x]', '$keranjang_harga_beli[$x]', '$keranjang_harga[$x]', '$keranjang_harga_parent[$x]',
				'$keranjang_harga_edit[$x]', '$keranjang_id_kasir[$x]', '$penjualan_invoice[$x]', '$penjualan_date[$x]',
				'$invoice_date_year_month', '$keranjang_qty_view[$x]', '$keranjang_qty_view[$x]',
				'$keranjang_barang_option_sn[$x]', '$keranjang_barang_sn_id[$x]', '$keranjang_sn[$x]',
				'$invoice_customer_category2[$x]', '$penjualan_cabang[$x]'
			)";
			$terlarisId = pos_table_next_id($conn, 'terlaris', 'terlaris_id');
			$query2 = "INSERT INTO terlaris (terlaris_id, barang_id, barang_terjual) VALUES ($terlarisId, '$id[$x]', '$keranjang_qty[$x]')";
			// var_dump($query); die();
			mysqli_query($conn, $query);
			mysqli_query($conn, $query2);
			penjualan_stock_after_insert(
				$conn,
				(int) $id[$x],
				floatval($keranjang_qty_view[$x] ?? 0),
				floatval($keranjang_konversi_isi[$x] ?? 1)
			);
		}


		mysqli_query($conn, "DELETE FROM keranjang_draft WHERE keranjang_invoice = $penjualan_invoice2 && keranjang_cabang = $invoice_cabang ");
		return mysqli_affected_rows($conn);
	}
}

function hapusDraft($invoice, $cabang)
{
	global $conn;

	$countDraft = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM keranjang_draft WHERE keranjang_invoice = $invoice && keranjang_cabang = $cabang"));
	// var_dump($countDraft); die();
	if ($countDraft > 0) {
		mysqli_query($conn, "DELETE FROM invoice WHERE penjualan_invoice = $invoice && invoice_cabang = $cabang");

		mysqli_query($conn, "DELETE FROM keranjang_draft WHERE keranjang_invoice = $invoice && keranjang_cabang = $cabang");
		return mysqli_affected_rows($conn);
	} else {
		mysqli_query($conn, "DELETE FROM invoice WHERE penjualan_invoice = $invoice && invoice_cabang = $cabang");
		return mysqli_affected_rows($conn);
	}
}

// =========================================== CUSTOMER ====================================== //

function customer_has_column($conn, string $column): bool
{
    static $cache = [];
    $column = preg_replace('/[^a-z0-9_]/i', '', $column) ?? '';
    if ($column === '') {
        return false;
    }
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    $colEsc = mysqli_real_escape_string($conn, $column);
    $res = @mysqli_query($conn, "SHOW COLUMNS FROM customer LIKE '$colEsc'");
    $cache[$column] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$column];
}

function customer_verifikasi_badge(string $status): string
{
    $map = [
        'none' => '<span class="badge badge-secondary">Belum upload</span>',
        'pending' => '<span class="badge badge-warning">Menunggu verifikasi</span>',
        'approved' => '<span class="badge badge-success">Disetujui</span>',
        'rejected' => '<span class="badge badge-danger">Ditolak</span>',
    ];
    $status = trim($status);
    if ($status === '') {
        $status = 'none';
    }
    return $map[$status] ?? ('<span class="badge badge-light">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>');
}

function customer_verifikasi_label(string $status): string
{
    $map = [
        'none' => 'Belum upload',
        'pending' => 'Menunggu verifikasi',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ];
    $status = trim($status);
    if ($status === '') {
        $status = 'none';
    }
    return $map[$status] ?? $status;
}

function tambahCustomer($data)
{
    global $conn;
    // Ambil data dari form dan amankan
    $customer_nama     = mysqli_real_escape_string($conn, htmlspecialchars($data["customer_nama"]));
    $customer_kartu    = mysqli_real_escape_string($conn, htmlspecialchars($data["customer_kartu"]));
    $customer_tlpn     = mysqli_real_escape_string($conn, htmlspecialchars($data["customer_tlpn"]));
    $customer_email    = mysqli_real_escape_string($conn, htmlspecialchars($data["customer_email"]));
    $customer_alamat   = mysqli_real_escape_string($conn, htmlspecialchars($data["customer_alamat"]));
    $customer_create   = date("Y-m-d H:i:s");
    $customer_status   = mysqli_real_escape_string($conn, htmlspecialchars($data["customer_status"]));
    $customer_category = mysqli_real_escape_string($conn, htmlspecialchars($data["customer_category"]));
    $customer_cabang   = mysqli_real_escape_string($conn, htmlspecialchars($data["customer_cabang"]));

    // Cek apakah nomor telepon sudah ada
    $check_query = "SELECT * FROM customer WHERE customer_tlpn = '$customer_tlpn'";
    $result = mysqli_query($conn, $check_query);

    if (!$result) {
        echo "Error in query: " . mysqli_error($conn);
        return false;
    }

    $customer_tlpn_cek = mysqli_num_rows($result);

    if ($customer_tlpn_cek > 0) {
        echo "
            <script>
                alert('Customer dengan nomor telepon ini sudah terdaftar!');
            </script>
        ";
        return 0;
    }

    $hasNewColumns = customer_has_column($conn, 'alamat_provinsi');
    $hasVerifikasi = customer_has_column($conn, 'customer_verifikasi_status');

    $alamat_dusun = $alamat_desa = $alamat_kecamatan = $alamat_kabupaten = $alamat_provinsi = '';
    $alamat_kode_provinsi = $alamat_kode_kabupaten = $alamat_kode_kecamatan = $alamat_kode_desa = '';
    $birthdayValue = 'NULL';
    if ($hasNewColumns) {
        $alamat_dusun          = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_dusun"] ?? ''));
        $alamat_desa           = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_desa"] ?? ''));
        $alamat_kecamatan      = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_kecamatan"] ?? ''));
        $alamat_kabupaten      = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_kabupaten"] ?? ''));
        $alamat_provinsi       = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_provinsi"] ?? ''));
        $alamat_kode_provinsi  = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_kode_provinsi"] ?? ''));
        $alamat_kode_kabupaten = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_kode_kabupaten"] ?? ''));
        $alamat_kode_kecamatan = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_kode_kecamatan"] ?? ''));
        $alamat_kode_desa      = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_kode_desa"] ?? ''));
        $customer_birthday     = !empty($data["customer_birthday"]) ? mysqli_real_escape_string($conn, $data["customer_birthday"]) : null;
        $birthdayValue = $customer_birthday ? "'$customer_birthday'" : "NULL";
    }

    $verifikasiStatus = 'none';
    $verifikasiAtSql = 'NULL';
    $ktpPath = '';
    $fotoWarungPath = '';
    if ($hasVerifikasi) {
        $allowed = ['none', 'pending', 'approved', 'rejected'];
        $rawStatus = trim((string) ($data['customer_verifikasi_status'] ?? 'none'));
        $verifikasiStatus = in_array($rawStatus, $allowed, true) ? $rawStatus : 'none';
        $verifikasiStatus = mysqli_real_escape_string($conn, $verifikasiStatus);
        $ktpPath = mysqli_real_escape_string($conn, trim((string) ($data['customer_ktp_path'] ?? '')));
        $fotoWarungPath = mysqli_real_escape_string($conn, trim((string) ($data['customer_foto_warung_path'] ?? '')));
        if (in_array($verifikasiStatus, ['approved', 'rejected', 'pending'], true)) {
            $verifikasiAtSql = "'" . date('Y-m-d H:i:s') . "'";
        }
    }

    if ($hasNewColumns && $hasVerifikasi) {
        $query = "INSERT INTO customer
                  (customer_nama, customer_kartu, customer_poin, customer_tlpn, customer_email, customer_alamat, customer_create, customer_status, customer_category, customer_cabang, alamat_dusun, alamat_desa, alamat_kecamatan, alamat_kabupaten, alamat_provinsi, alamat_kode_provinsi, alamat_kode_kabupaten, alamat_kode_kecamatan, alamat_kode_desa, customer_birthday, customer_ktp_path, customer_foto_warung_path, customer_verifikasi_status, customer_verifikasi_at)
                  VALUES
                  ('$customer_nama', '$customer_kartu', 0, '$customer_tlpn', '$customer_email', '$customer_alamat', '$customer_create', '$customer_status', '$customer_category', '$customer_cabang', '$alamat_dusun', '$alamat_desa', '$alamat_kecamatan', '$alamat_kabupaten', '$alamat_provinsi', '$alamat_kode_provinsi', '$alamat_kode_kabupaten', '$alamat_kode_kecamatan', '$alamat_kode_desa', $birthdayValue, " . ($ktpPath !== '' ? "'$ktpPath'" : 'NULL') . ", " . ($fotoWarungPath !== '' ? "'$fotoWarungPath'" : 'NULL') . ", '$verifikasiStatus', $verifikasiAtSql)";
    } elseif ($hasNewColumns) {
        $query = "INSERT INTO customer
                  (customer_nama, customer_kartu, customer_tlpn, customer_email, customer_alamat, customer_create, customer_status, customer_category, customer_cabang, alamat_dusun, alamat_desa, alamat_kecamatan, alamat_kabupaten, alamat_provinsi, alamat_kode_provinsi, alamat_kode_kabupaten, alamat_kode_kecamatan, alamat_kode_desa, customer_birthday)
                  VALUES
                  ('$customer_nama', '$customer_kartu', '$customer_tlpn', '$customer_email', '$customer_alamat', '$customer_create', '$customer_status', '$customer_category', '$customer_cabang', '$alamat_dusun', '$alamat_desa', '$alamat_kecamatan', '$alamat_kabupaten', '$alamat_provinsi', '$alamat_kode_provinsi', '$alamat_kode_kabupaten', '$alamat_kode_kecamatan', '$alamat_kode_desa', $birthdayValue)";
    } else {
        $query = "INSERT INTO customer
                  (customer_nama, customer_kartu, customer_tlpn, customer_email, customer_alamat, customer_create, customer_status, customer_category, customer_cabang)
                  VALUES
                  ('$customer_nama', '$customer_kartu', '$customer_tlpn', '$customer_email', '$customer_alamat', '$customer_create', '$customer_status', '$customer_category', '$customer_cabang')";
    }

    if (!mysqli_query($conn, $query)) {
        echo "<script>console.error('SQL Error: " . addslashes(mysqli_error($conn)) . "');</script>";
        return false;
    }

    return mysqli_affected_rows($conn);
}


function editCustomer($data)
{
	global $conn;
	$id = intval($data["customer_id"]);

	// ambil data dari tiap elemen dalam form
	$customer_nama     = mysqli_real_escape_string($conn, htmlspecialchars($data["customer_nama"]));
	$customer_kartu    = mysqli_real_escape_string($conn, htmlspecialchars($data["customer_kartu"]));
	$customer_tlpn     = mysqli_real_escape_string($conn, htmlspecialchars($data["customer_tlpn"]));
	$customer_email    = mysqli_real_escape_string($conn, htmlspecialchars($data["customer_email"]));
	$customer_alamat   = mysqli_real_escape_string($conn, htmlspecialchars($data["customer_alamat"]));
	$customer_status   = mysqli_real_escape_string($conn, htmlspecialchars($data["customer_status"]));
	$customer_category = mysqli_real_escape_string($conn, $data["customer_category"]);

	$hasNewColumns = customer_has_column($conn, 'alamat_provinsi');
	$hasVerifikasi = customer_has_column($conn, 'customer_verifikasi_status');

	$extraSet = '';
	if ($hasNewColumns) {
		$alamat_dusun          = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_dusun"] ?? ''));
		$alamat_desa           = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_desa"] ?? ''));
		$alamat_kecamatan      = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_kecamatan"] ?? ''));
		$alamat_kabupaten      = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_kabupaten"] ?? ''));
		$alamat_provinsi       = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_provinsi"] ?? ''));
		$alamat_kode_provinsi  = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_kode_provinsi"] ?? ''));
		$alamat_kode_kabupaten = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_kode_kabupaten"] ?? ''));
		$alamat_kode_kecamatan = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_kode_kecamatan"] ?? ''));
		$alamat_kode_desa      = mysqli_real_escape_string($conn, htmlspecialchars($data["alamat_kode_desa"] ?? ''));
		$customer_birthday     = !empty($data["customer_birthday"]) ? mysqli_real_escape_string($conn, $data["customer_birthday"]) : null;
		$birthdayValue = $customer_birthday ? "customer_birthday = '$customer_birthday'," : "customer_birthday = NULL,";
		$extraSet .= "
							alamat_dusun      = '$alamat_dusun',
							alamat_desa       = '$alamat_desa',
							alamat_kecamatan  = '$alamat_kecamatan',
							alamat_kabupaten  = '$alamat_kabupaten',
							alamat_provinsi   = '$alamat_provinsi',
							alamat_kode_provinsi  = '$alamat_kode_provinsi',
							alamat_kode_kabupaten = '$alamat_kode_kabupaten',
							alamat_kode_kecamatan = '$alamat_kode_kecamatan',
							alamat_kode_desa  = '$alamat_kode_desa',
							$birthdayValue";
	}

	if ($hasVerifikasi) {
		$allowed = ['none', 'pending', 'approved', 'rejected'];
		$rawStatus = trim((string) ($data['customer_verifikasi_status'] ?? 'none'));
		$verifikasiStatus = in_array($rawStatus, $allowed, true) ? $rawStatus : 'none';
		$verifikasiStatus = mysqli_real_escape_string($conn, $verifikasiStatus);
		$ktpPath = trim((string) ($data['customer_ktp_path'] ?? ''));
		$fotoWarungPath = trim((string) ($data['customer_foto_warung_path'] ?? ''));
		$ktpSql = $ktpPath !== '' ? "'" . mysqli_real_escape_string($conn, $ktpPath) . "'" : 'NULL';
		$warungSql = $fotoWarungPath !== '' ? "'" . mysqli_real_escape_string($conn, $fotoWarungPath) . "'" : 'NULL';

		$oldStatus = '';
		$resOld = mysqli_query($conn, "SELECT customer_verifikasi_status FROM customer WHERE customer_id = $id LIMIT 1");
		if ($resOld && ($ro = mysqli_fetch_assoc($resOld))) {
			$oldStatus = (string) ($ro['customer_verifikasi_status'] ?? '');
		}
		$verifikasiAtSql = 'customer_verifikasi_at';
		if ($verifikasiStatus !== $oldStatus) {
			if (in_array($verifikasiStatus, ['approved', 'rejected', 'pending'], true)) {
				$verifikasiAtSql = "'" . date('Y-m-d H:i:s') . "'";
			} elseif ($verifikasiStatus === 'none') {
				$verifikasiAtSql = 'NULL';
			}
		}

		$extraSet .= "
							customer_ktp_path = $ktpSql,
							customer_foto_warung_path = $warungSql,
							customer_verifikasi_status = '$verifikasiStatus',
							customer_verifikasi_at = $verifikasiAtSql,";
	}

	$query = "UPDATE customer SET
							customer_nama     = '$customer_nama',
							customer_kartu    = '$customer_kartu',
							customer_tlpn     = '$customer_tlpn',
							customer_email    = '$customer_email',
							customer_alamat   = '$customer_alamat',
							customer_status   = '$customer_status',
							customer_category = '$customer_category',
							$extraSet
							customer_id = customer_id
							WHERE customer_id = $id";

	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}


function hapusCustomer($id)
{
	global $conn;
	mysqli_query($conn, "DELETE FROM customer WHERE customer_id = $id");

	return mysqli_affected_rows($conn);
}


// =========================================== Panjualan ===================================== //

/** Qty transaksi dari baris penjualan (satuan yang dipakai saat jual). */
function penjualan_row_qty(array $row)
{
	$qty = (float) ($row['barang_qty'] ?? 0);
	if ($qty <= 0) {
		$qty = (float) ($row['barang_qty_keranjang'] ?? 0);
	}
	return $qty;
}

/** Konversi ke PCS (min 1). */
function penjualan_row_konversi(array $row)
{
	return max(1, (float) ($row['barang_qty_konversi_isi'] ?? 1));
}

/** Qty transaksi → PCS. */
function penjualan_qty_to_pcs($qty, $konversiIsi)
{
	return (float) $qty * max(1, (float) $konversiIsi);
}

/** PCS dari baris penjualan. */
function penjualan_row_pcs(array $row)
{
	return penjualan_qty_to_pcs(penjualan_row_qty($row), penjualan_row_konversi($row));
}

/** Apakah DB punya trigger yang mengubah barang_stock pada tabel penjualan. */
function penjualan_db_has_stock_trigger($conn, $event)
{
	static $cache = [];
	$event = strtoupper(trim((string) $event));
	if ($event === '') {
		return false;
	}
	if (array_key_exists($event, $cache)) {
		return $cache[$event];
	}

	$eventEsc = mysqli_real_escape_string($conn, $event);
	$sql = "
		SELECT COUNT(*) AS n
		FROM information_schema.TRIGGERS
		WHERE TRIGGER_SCHEMA = DATABASE()
		  AND EVENT_OBJECT_TABLE = 'penjualan'
		  AND EVENT_MANIPULATION = '$eventEsc'
		  AND ACTION_STATEMENT LIKE '%barang_stock%'
	";
	$res = mysqli_query($conn, $sql);
	$row = $res ? mysqli_fetch_assoc($res) : null;
	$cache[$event] = ((int) ($row['n'] ?? 0)) > 0;

	return $cache[$event];
}

function penjualan_sql_decimal($n)
{
	$s = sprintf('%.6F', (float) $n);
	$s = rtrim(rtrim($s, '0'), '.');
	if ($s === '' || $s === '-0') {
		return '0';
	}
	return $s;
}

/** Potong stok + tambah terjual (PCS). */
function penjualan_apply_stock_sale($conn, $barangId, $pcs)
{
	$barangId = (int) $barangId;
	$pcs = (float) $pcs;
	if ($barangId < 1 || $pcs <= 0) {
		return false;
	}

	$res = mysqli_query($conn, 'SELECT barang_stock, barang_terjual FROM barang WHERE barang_id = ' . $barangId . ' LIMIT 1');
	$row = $res ? mysqli_fetch_assoc($res) : null;
	if (!$row) {
		return false;
	}

	$stockBaru = penjualan_sql_decimal((float) $row['barang_stock'] - $pcs);
	$terjualBaru = penjualan_sql_decimal((float) $row['barang_terjual'] + $pcs);

	$ok = mysqli_query(
		$conn,
		"UPDATE barang SET barang_stock = '$stockBaru', barang_terjual = '$terjualBaru' WHERE barang_id = $barangId"
	);

	return $ok && mysqli_affected_rows($conn) > 0;
}

/** Potong stok setelah INSERT penjualan, kecuali trigger DB sudah menanganinya. */
function penjualan_stock_after_insert($conn, $barangId, $qtyView, $konversiIsi)
{
	if (penjualan_db_has_stock_trigger($conn, 'INSERT')) {
		return true;
	}

	return penjualan_apply_stock_sale($conn, $barangId, penjualan_qty_to_pcs($qtyView, $konversiIsi));
}

/** Kembalikan stok sebelum DELETE penjualan, kecuali trigger DB sudah menanganinya. */
function penjualan_stock_before_delete($conn, array $row)
{
	if (penjualan_db_has_stock_trigger($conn, 'DELETE')) {
		return true;
	}

	return penjualan_apply_stock_return($conn, (int) ($row['barang_id'] ?? 0), penjualan_row_pcs($row));
}

/** Kembalikan stok + kurangi terjual (PCS). */
function penjualan_apply_stock_return($conn, $barangId, $pcsReturn)
{
	$barangId = (int) $barangId;
	$pcsReturn = (float) $pcsReturn;
	if ($barangId < 1 || $pcsReturn <= 0) {
		return false;
	}

	$res = mysqli_query($conn, 'SELECT barang_stock, barang_terjual FROM barang WHERE barang_id = ' . $barangId . ' LIMIT 1');
	$row = $res ? mysqli_fetch_assoc($res) : null;
	if (!$row) {
		return false;
	}

	$stockBaru = penjualan_sql_decimal((float) $row['barang_stock'] + $pcsReturn);
	$terjualBaru = penjualan_sql_decimal(max(0, (float) $row['barang_terjual'] - $pcsReturn));

	mysqli_query(
		$conn,
		"UPDATE barang SET barang_stock = '$stockBaru', barang_terjual = '$terjualBaru' WHERE barang_id = $barangId"
	);

	return true;
}

/** Baris penjualan piutang yang qty-nya sudah dikurangi tapi belum pernah sync stok. */
function penjualan_piutang_retur_belum_sync($conn, $cabang = null)
{
	$cabFilter = $cabang !== null ? ' AND i.invoice_cabang = ' . (int) $cabang : '';
	$sql = "
		SELECT
			p.penjualan_id,
			p.penjualan_invoice,
			p.penjualan_date,
			p.barang_id,
			p.barang_qty,
			p.barang_qty_keranjang,
			p.barang_qty_lama,
			p.barang_qty_konversi_isi,
			p.penjualan_cabang,
			b.barang_kode,
			b.barang_nama,
			b.barang_stock,
			b.barang_terjual,
			i.invoice_id,
			i.invoice_tgl
		FROM penjualan p
		INNER JOIN invoice i
			ON i.penjualan_invoice = p.penjualan_invoice
			AND i.invoice_cabang = p.penjualan_cabang
		INNER JOIN barang b ON b.barang_id = p.barang_id
		WHERE i.invoice_piutang = 1
			AND p.barang_qty < p.barang_qty_lama
			$cabFilter
		ORDER BY p.penjualan_date DESC, p.penjualan_id DESC
	";

	$rows = [];
	$res = mysqli_query($conn, $sql);
	if ($res) {
		while ($row = mysqli_fetch_assoc($res)) {
			$qtyNow = penjualan_row_qty($row);
			$qtyLama = (float) ($row['barang_qty_lama'] ?? 0);
			$row['pcs_belum_kembali'] = penjualan_qty_to_pcs($qtyLama - $qtyNow, penjualan_row_konversi($row));
			if ($row['pcs_belum_kembali'] > 0) {
				$rows[] = $row;
			}
		}
	}

	return $rows;
}

/** One-time repair: kembalikan stok untuk retur piutang historis. */
function penjualan_perbaiki_stok_retur_piutang($conn, $cabang = null)
{
	$rows = penjualan_piutang_retur_belum_sync($conn, $cabang);
	$totalPcs = 0;
	$baris = 0;

	foreach ($rows as $row) {
		$pcs = (float) ($row['pcs_belum_kembali'] ?? 0);
		if ($pcs <= 0) {
			continue;
		}
		if (penjualan_apply_stock_return($conn, (int) $row['barang_id'], $pcs)) {
			mysqli_query(
				$conn,
				'UPDATE penjualan SET barang_qty_lama = barang_qty WHERE penjualan_id = ' . (int) $row['penjualan_id']
			);
			$totalPcs += $pcs;
			$baris++;
		}
	}

	return ['baris' => $baris, 'total_pcs' => $totalPcs];
}

function hapusPenjualan($id)
{
	global $conn;

	$id = (int) $id;
	$res = mysqli_query($conn, 'SELECT * FROM penjualan WHERE penjualan_id = ' . $id . ' LIMIT 1');
	$row = $res ? mysqli_fetch_assoc($res) : null;
	if (!$row) {
		return 0;
	}

	penjualan_stock_before_delete($conn, $row);

	if ((int) ($row['barang_option_sn'] ?? 0) > 0 && (int) ($row['barang_sn_id'] ?? 0) > 0) {
		$snId = (int) $row['barang_sn_id'];
		mysqli_query($conn, "UPDATE barang_sn SET barang_sn_status = 3 WHERE barang_sn_id = $snId");
	}

	mysqli_query($conn, 'DELETE FROM penjualan WHERE penjualan_id = ' . $id);

	return mysqli_affected_rows($conn);
}

function hapusPenjualanInvoice($id)
{
	global $conn;

	$id = (int) $id;

	// Mencari Invoive Penjualan dan cabang
	$invoiceTbl = mysqli_query($conn, "select penjualan_invoice, invoice_cabang from invoice where invoice_id = '" . $id . "'");

	$ivc = mysqli_fetch_array($invoiceTbl);
	if (!$ivc) {
		return 0;
	}

	$penjualan_invoice  = $ivc["penjualan_invoice"];
	$invoice_cabang  	= $ivc["invoice_cabang"];

	$penjualanRows = query(
		"SELECT * FROM penjualan WHERE penjualan_invoice = $penjualan_invoice AND penjualan_cabang = $invoice_cabang"
	);
	foreach ($penjualanRows as $row) {
		penjualan_stock_before_delete($conn, $row);

		if ((int) ($row['barang_option_sn'] ?? 0) > 0 && (int) ($row['barang_sn_id'] ?? 0) > 0) {
			$snId = (int) $row['barang_sn_id'];
			mysqli_query($conn, "UPDATE barang_sn SET barang_sn_status = 3 WHERE barang_sn_id = $snId");
		}
	}

	// Menghitung data di tabel piutang sesuai No. Invoice
	$piutang = mysqli_query($conn, "select * from piutang where piutang_invoice = '" . $penjualan_invoice . "' && piutang_cabang = '" . $invoice_cabang . "' ");
	$jmlPiutang = mysqli_num_rows($piutang);

	// Kondisi Hapus jika terdapat cicilan di tabel Piutang
	if ($jmlPiutang > 0) {
		mysqli_query($conn, "DELETE FROM piutang WHERE piutang_invoice = $penjualan_invoice && piutang_cabang = $invoice_cabang ");

		mysqli_query($conn, "DELETE FROM penjualan WHERE penjualan_invoice = $penjualan_invoice && penjualan_cabang = $invoice_cabang ");

		mysqli_query($conn, "DELETE FROM invoice WHERE invoice_id = $id");
	} else {
		// Kondisi Hapus jika Tanpa cicilan di tabel Piutang
		mysqli_query($conn, "DELETE FROM penjualan WHERE penjualan_invoice = $penjualan_invoice && penjualan_cabang = $invoice_cabang ");

		mysqli_query($conn, "DELETE FROM invoice WHERE invoice_id = $id");
	}

	return mysqli_affected_rows($conn);
}

function updateQTY2($data)
{
	global $conn;
	$id = (int) $data["penjualan_id"];
	$bid = (int) $data["barang_id"];

	$resPen = mysqli_query($conn, 'SELECT barang_qty, barang_qty_keranjang, barang_qty_konversi_isi FROM penjualan WHERE penjualan_id = ' . $id . ' LIMIT 1');
	$rowPen = $resPen ? mysqli_fetch_assoc($resPen) : null;
	if (!$rowPen) {
		return 0;
	}

	$qtySekarang = penjualan_row_qty($rowPen);
	$barang_qty = (float) htmlspecialchars($data['barang_qty']);
	$barang_qty_konversi_isi = max(1, (float) ($data['barang_qty_konversi_isi'] ?? $rowPen['barang_qty_konversi_isi'] ?? 1));

	// Edit No SN Jika Produk Menggunakan SN
	$barang_option_sn = (int) ($data['barang_option_sn'] ?? 0);
	$barang_sn_id = (int) ($data['barang_sn_id'] ?? 0);

	$pcsReturn = penjualan_qty_to_pcs($qtySekarang - $barang_qty, $barang_qty_konversi_isi);

	if ($barang_qty > $qtySekarang) {
		echo "
			<script>
				alert('Jika Anda Ingin Menambahkan QTY Barang.. Lakukan Transaksi Invoice Baru !!!');
			</script>
		";
		return 0;
	}

	if ($pcsReturn <= 0) {
		return 0;
	}

	$resBrg = mysqli_query($conn, 'SELECT barang_stock, barang_terjual FROM barang WHERE barang_id = ' . $bid . ' LIMIT 1');
	$rowBrg = $resBrg ? mysqli_fetch_assoc($resBrg) : null;
	if (!$rowBrg) {
		return 0;
	}

	$barang_stock_hasil = (float) $rowBrg['barang_stock'] + $pcsReturn;
	$barang_terjual_hasil = max(0, (float) $rowBrg['barang_terjual'] - $pcsReturn);

	$query = "UPDATE penjualan SET 
				barang_qty = '$barang_qty',
				barang_qty_keranjang = '$barang_qty'
				WHERE penjualan_id = $id
				";
	$query1 = "UPDATE barang SET 
				barang_stock = '$barang_stock_hasil',
				barang_terjual = '$barang_terjual_hasil'
				WHERE barang_id = $bid
				";

	if ($barang_option_sn > 0 && $barang_sn_id > 0) {
		mysqli_query($conn, "UPDATE barang_sn SET barang_sn_status = 2 WHERE barang_sn_id = $barang_sn_id");
	}

	mysqli_query($conn, $query);
	mysqli_query($conn, $query1);

	return mysqli_affected_rows($conn);
}

function updateInvoice($data)
{
	global $conn;
	$id = $data["invoice_id"];

	// ambil data dari tiap elemen dalam form
	$invoice_total_beli   = htmlspecialchars($data['invoice_total_beli']);
	$invoice_total        = htmlspecialchars($data['invoice_total']);
	$invoice_ongkir       = $data['invoice_ongkir'];
	$invoice_sub_total    = $data['invoice_sub_total'];
	$invoice_bayar        = htmlspecialchars($data['angka1']);
	$invoice_kembali      = $invoice_bayar - $invoice_sub_total;
	$invoice_kasir_edit   = $data['invoice_kasir_edit'];
	$invoice_date_edit    = date('Y-m-d');

	// query update data
	$query = "UPDATE invoice SET 
					invoice_total_beli = '$invoice_total_beli',
					invoice_total      = '$invoice_total',
					invoice_ongkir     = '$invoice_ongkir',
					invoice_sub_total  = '$invoice_sub_total',
					invoice_bayar      = '$invoice_bayar',
					invoice_kembali    = '$invoice_kembali',
					invoice_date_edit  = '$invoice_date_edit',
					invoice_kasir_edit = '$invoice_kasir_edit'
					WHERE invoice_id = $id
					";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

function editInvoiceEkspedisi($data)
{
	global $conn;
	$id = $data["invoice_id"];

	// ambil data dari tiap elemen dalam form
	$invoice_marketplace        = htmlspecialchars($data['invoice_marketplace']);
	$invoice_ekspedisi          = htmlspecialchars($data['invoice_ekspedisi']);
	$invoice_no_resi            = htmlspecialchars($data['invoice_no_resi']);
	$invoice_total              = $data['invoice_total'];
	$invoice_ongkir             = htmlspecialchars($data['invoice_ongkir']);
	$invoice_sub_total          = $invoice_total + $invoice_ongkir;
	$invoice_bayar              = $data['invoice_bayar'];
	$invoice_kembali            = $invoice_bayar - $invoice_sub_total;

	// query update data
	$query = "UPDATE invoice SET 
					invoice_total          = '$invoice_total',
					invoice_ongkir         = '$invoice_ongkir',
					invoice_sub_total      = '$invoice_sub_total',
					invoice_bayar          = '$invoice_bayar',
					invoice_kembali        = '$invoice_kembali',
					invoice_marketplace    = '$invoice_marketplace',
					invoice_ekspedisi      = '$invoice_ekspedisi',
					invoice_no_resi        = '$invoice_no_resi'
					WHERE invoice_id = $id
					";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

function editInvoiceKurir($data)
{
	global $conn;
	$id = $data["invoice_id"];

	// ambil data dari tiap elemen dalam form
	$invoice_total              = $data['invoice_total'];
	$invoice_ongkir             = htmlspecialchars($data['invoice_ongkir']);
	$invoice_sub_total          = $invoice_total + $invoice_ongkir;
	$invoice_bayar              = $data['invoice_bayar'];
	$invoice_kembali            = $invoice_bayar - $invoice_sub_total;
	$invoice_kurir              = htmlspecialchars($data['invoice_kurir']);
	$invoice_status_kurir       = htmlspecialchars($data['invoice_status_kurir']);

	// query update data
	$query = "UPDATE invoice SET 
					invoice_kurir 		   = '$invoice_kurir',
					invoice_status_kurir   = '$invoice_status_kurir',
					invoice_total          = '$invoice_total',
					invoice_ongkir         = '$invoice_ongkir',
					invoice_sub_total      = '$invoice_sub_total',
					invoice_bayar          = '$invoice_bayar',
					invoice_kembali        = '$invoice_kembali'
					WHERE invoice_id = $id
					";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

// ============================================ Supplier ====================================== //
function supplier_ensure_kode_column(mysqli $conn): void
{
	$chk = mysqli_query($conn, "SHOW COLUMNS FROM supplier LIKE 'kode_suplier'");
	if ($chk && mysqli_num_rows($chk) === 0) {
		@mysqli_query($conn, "ALTER TABLE supplier ADD COLUMN kode_suplier VARCHAR(100) NULL DEFAULT NULL COMMENT 'Kode filter barang' AFTER supplier_cabang");
		@mysqli_query($conn, 'ALTER TABLE supplier ADD INDEX idx_supplier_kode_suplier (kode_suplier)');
	}
}

function tambahSupplier($data)
{
	global $conn;
	supplier_ensure_kode_column($conn);

	// ambil data dari tiap elemen dalam form
	$supplier_nama      = htmlspecialchars($data["supplier_nama"]);
	$supplier_wa 		= htmlspecialchars($data["supplier_wa"]);
	$supplier_alamat    = htmlspecialchars($data["supplier_alamat"]);
	$supplier_company   = htmlspecialchars($data["supplier_company"]);
	$supplier_status    = htmlspecialchars($data["supplier_status"]);
	$supplier_create    = date("d F Y g:i:s a");
	$supplier_cabang    = htmlspecialchars($data["supplier_cabang"]);
	$kode_suplier       = strtoupper(trim((string) ($data["kode_suplier"] ?? '')));
	$kode_sql           = $kode_suplier !== '' ? ("'" . mysqli_real_escape_string($conn, $kode_suplier) . "'") : 'NULL';

	// Cek Email
	$supplier_wa_cek = mysqli_num_rows(mysqli_query($conn, "select * from supplier where supplier_wa = '$supplier_wa' "));

	if ($supplier_wa_cek > 0) {
		echo "
			<script>
				alert('No. WhatsApp Sudah Terdaftar');
			</script>
		";
	} else {
		$query = "INSERT INTO supplier (supplier_nama, supplier_wa, supplier_alamat, supplier_company, supplier_status, supplier_create, supplier_cabang, kode_suplier)
			VALUES ('$supplier_nama', '$supplier_wa', '$supplier_alamat', '$supplier_company', '$supplier_status', '$supplier_create', '$supplier_cabang', $kode_sql)";
		mysqli_query($conn, $query);

		return mysqli_affected_rows($conn);
	}
}

function editSupplier($data)
{
	global $conn;
	supplier_ensure_kode_column($conn);

	$id = $data["supplier_id"];

	// ambil data dari tiap elemen dalam form
	$supplier_nama      = htmlspecialchars($data["supplier_nama"]);
	$supplier_wa 		= htmlspecialchars($data["supplier_wa"]);
	$supplier_alamat    = htmlspecialchars($data["supplier_alamat"]);
	$supplier_company   = htmlspecialchars($data["supplier_company"]);
	$supplier_status    = htmlspecialchars($data["supplier_status"]);
	$kode_suplier       = strtoupper(trim((string) ($data["kode_suplier"] ?? '')));
	$kode_sql           = $kode_suplier !== '' ? ("'" . mysqli_real_escape_string($conn, $kode_suplier) . "'") : 'NULL';

	$query = "UPDATE supplier SET 
						supplier_nama      = '$supplier_nama',
						supplier_wa        = '$supplier_wa',
						supplier_alamat    = '$supplier_alamat',
						supplier_company   = '$supplier_company',
						supplier_status    = '$supplier_status',
						kode_suplier       = $kode_sql
						WHERE supplier_id  = $id
				";
	mysqli_query($conn, $query);

	return mysqli_affected_rows($conn);
}

function hapusSupplier($id)
{
	global $conn;
	mysqli_query($conn, "DELETE FROM supplier WHERE supplier_id = $id");

	return mysqli_affected_rows($conn);
}

// ===================================== Keranjang Pembelian =============================== //
function tambahKeranjangPembelian($barang_id, $keranjang_nama, $keranjang_harga, $keranjang_id_kasir, $keranjang_qty, $keranjang_cabang, $keranjang_id_cek)
{
	global $conn;

	// Cek STOCK
	$barang_id_cek = mysqli_num_rows(mysqli_query($conn, "select * from keranjang_pembelian where keranjang_id_cek = '$keranjang_id_cek' "));

	// Kondisi jika scan Barcode Tidak sesuai
	if ($barang_id != null) {
		if ($barang_id_cek > 0) {
			$keranjangParent = mysqli_query($conn, "select keranjang_qty from keranjang_pembelian where keranjang_id_cek = '" . $keranjang_id_cek . "'");
			$kp = mysqli_fetch_array($keranjangParent);
			$kp = $kp['keranjang_qty'];
			$kp += $keranjang_qty;

			$query = "UPDATE keranjang_pembelian SET 
							keranjang_qty   = '$kp'
							WHERE keranjang_id_cek = $keranjang_id_cek
							";
			mysqli_query($conn, $query);
			return mysqli_affected_rows($conn);
		} else {
			// query insert data
			$query = "INSERT INTO keranjang_pembelian (keranjang_nama, keranjang_harga, barang_id, keranjang_qty, keranjang_id_kasir, keranjang_id_cek, keranjang_cabang) VALUES ('$keranjang_nama', '$keranjang_harga', '$barang_id', '$keranjang_qty', '$keranjang_id_kasir', '$keranjang_id_cek', '$keranjang_cabang')";

			mysqli_query($conn, $query);

			return mysqli_affected_rows($conn);
		}
	} else {
		echo '
			<script>
				alert("Kode Produk Tidak ada di Data Master Barang dan Coba Cek Kembali !! ");
				document.location.href = "transaksi-pembelian";
			</script>
		';
	}
}

function tambahKeranjangPembelianBarcode($data)
{
	global $conn;
	$barang_kode 		= htmlspecialchars($data['inputbarcode']);
	$keranjang_id_kasir = $data['keranjang_id_kasir'];
	$keranjang_cabang   = $data['keranjang_cabang'];

	// Ambil Data Barang berdasarkan Kode Barang 
	$barang 	= mysqli_query($conn, "select barang_id, barang_nama from barang where barang_status = '1' && barang_kode = '" . $barang_kode . "' && barang_cabang = '" . $keranjang_cabang . "' ");
	$br 		= mysqli_fetch_array($barang);

	$barang_id          = $br['barang_id'];
	$keranjang_nama     = $br['barang_nama'];
	$keranjang_harga    = barang_get_harga_beli_untuk_input($conn, (int) $barang_id);
	$keranjang_qty      = 1;
	$keranjang_id_cek   = $barang_id . $keranjang_id_kasir . $keranjang_cabang;

	// Cek STOCK
	$barang_id_cek = mysqli_num_rows(mysqli_query($conn, "select * from keranjang_pembelian where keranjang_id_cek = '$keranjang_id_cek' "));

	// Kondisi jika scan Barcode Tidak sesuai
	if ($barang_id != null) {
		if ($barang_id_cek > 0) {
			$keranjangParent = mysqli_query($conn, "select keranjang_qty from keranjang_pembelian where keranjang_id_cek = '" . $keranjang_id_cek . "'");
			$kp = mysqli_fetch_array($keranjangParent);
			$kp = $kp['keranjang_qty'];
			$kp += $keranjang_qty;

			$query = "UPDATE keranjang_pembelian SET 
							keranjang_qty   = '$kp'
							WHERE keranjang_id_cek = $keranjang_id_cek
							";
			mysqli_query($conn, $query);
			return mysqli_affected_rows($conn);
		} else {
			// query insert data
			$query = "INSERT INTO keranjang_pembelian (keranjang_nama, keranjang_harga, barang_id, keranjang_qty, keranjang_id_kasir, keranjang_id_cek, keranjang_cabang) VALUES ('$keranjang_nama', '$keranjang_harga', '$barang_id', '$keranjang_qty', '$keranjang_id_kasir', '$keranjang_id_cek', '$keranjang_cabang')";

			mysqli_query($conn, $query);

			return mysqli_affected_rows($conn);
		}
	} else {
		echo '
			<script>
				alert("Kode Produk Tidak ada di Data Master Barang dan Coba Cek Kembali !! ");
				document.location.href = "transaksi-pembelian";
			</script>
		';
	}
}

function hapusKeranjangPembelian($id)
{
	global $conn;

	mysqli_query($conn, "DELETE FROM keranjang_pembelian WHERE keranjang_id = $id");

	return mysqli_affected_rows($conn);
}

function updateQTYpembelian($data)
{
	global $conn;
	$id = $data["keranjang_id"];

	// ambil data dari tiap elemen dalam form (decimal 11,1)
	$keranjang_qty = round((float)$data['keranjang_qty'], 1);
	$stock_brg = $data['stock_brg'];

	// query update data
	$query = "UPDATE keranjang_pembelian SET 
				keranjang_qty   = '$keranjang_qty'
				WHERE keranjang_id = $id
			";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

function updateHargaBeliPembelian($data)
{
	global $conn;
	$id = $data["keranjang_id"];
	$keranjang_harga = round((float)$data['keranjang_harga'], 1);

	$query = "UPDATE keranjang_pembelian SET 
				keranjang_harga = '$keranjang_harga'
				WHERE keranjang_id = $id
			";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

function barang_harga_beli_rata_ensure_column($conn)
{
	static $done = false;
	if ($done || !$conn) {
		return;
	}
	$chk = mysqli_query($conn, "SHOW COLUMNS FROM barang LIKE 'barang_harga_beli_rata'");
	if ($chk && mysqli_num_rows($chk) > 0) {
		$done = true;
		return;
	}
	mysqli_query($conn, "ALTER TABLE barang ADD COLUMN barang_harga_beli_rata DECIMAL(15,1) NOT NULL DEFAULT 0");
	$done = true;
}

/**
 * Ekspresi SQL HPP: barang_harga_beli_rata jika ada, fallback barang_harga_beli.
 */
function barang_hpp_sql_expr($alias = 'b')
{
	$alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'b';
	return "CASE WHEN CAST({$alias}.barang_harga_beli_rata AS DECIMAL(18,4)) > 0 THEN CAST({$alias}.barang_harga_beli_rata AS DECIMAL(18,4)) ELSE CAST({$alias}.barang_harga_beli AS DECIMAL(18,4)) END";
}

/**
 * Ambil HPP dari baris barang (array fetch).
 */
function barang_hpp_dari_row(array $row)
{
	$rata = (float) ($row['barang_harga_beli_rata'] ?? 0);
	if ($rata > 0) {
		return $rata;
	}
	return (float) ($row['barang_harga_beli'] ?? 0);
}

function barang_get_kode_by_id($conn, $barang_id)
{
	$barang_id = (int) $barang_id;
	$res = mysqli_query($conn, "SELECT barang_kode FROM barang WHERE barang_id = $barang_id LIMIT 1");
	if ($res && ($row = mysqli_fetch_assoc($res))) {
		return (string) ($row['barang_kode'] ?? '');
	}
	return '';
}

/**
 * HPP rata-rata tertimbang dari stok semua cabang (barang_kode sama).
 * Rumus: (Σ stok × HPP lama) + (qty beli × harga beli) ÷ (Σ stok + qty beli)
 */
function hitungHppBarangWeightedStok($conn, $barang_kode, $qty_beli_baru = 0.0, $harga_beli_baru = 0.0)
{
	if (!$conn || $barang_kode === '') {
		return 0.0;
	}
	barang_harga_beli_rata_ensure_column($conn);

	$kodeEsc = mysqli_real_escape_string($conn, $barang_kode);
	$sql = "
		SELECT
			COALESCE(SUM(CAST(NULLIF(TRIM(barang_stock), '') AS DECIMAL(18,4))), 0) AS total_stok,
			COALESCE(SUM(
				CAST(NULLIF(TRIM(barang_stock), '') AS DECIMAL(18,4))
				* CASE
					WHEN barang_harga_beli_rata > 0 THEN barang_harga_beli_rata
					ELSE barang_harga_beli
				  END
			), 0) AS total_nilai
		FROM barang
		WHERE barang_kode = '$kodeEsc'
		  AND barang_status = '1'
	";
	$res = mysqli_query($conn, $sql);
	if (!$res || !($row = mysqli_fetch_assoc($res))) {
		$harga = max(0.0, (float) $harga_beli_baru);
		return $harga > 0 ? round($harga, 1) : 0.0;
	}

	$stok = max(0.0, (float) ($row['total_stok'] ?? 0));
	$nilai = max(0.0, (float) ($row['total_nilai'] ?? 0));
	$qtyBeli = max(0.0, (float) $qty_beli_baru);
	$harga = max(0.0, (float) $harga_beli_baru);

	$stokBaru = $stok + $qtyBeli;
	if ($stokBaru <= 0) {
		if ($harga > 0) {
			return round($harga, 1);
		}
		return $stok > 0 ? round($nilai / $stok, 1) : 0.0;
	}

	return round(($nilai + ($qtyBeli * $harga)) / $stokBaru, 1);
}

/**
 * Format harga beli / HPP untuk tampilan (pembelian pakai step 0.1).
 */
function format_harga_beli_tampilan($nilai)
{
	$n = round((float) $nilai, 1);
	if ($n <= 0) {
		return '0';
	}
	if (abs($n - (int) $n) >= 0.05) {
		return number_format($n, 1, ',', '.');
	}
	return number_format($n, 0, ',', '.');
}

/**
 * Harga beli dari transaksi pembelian terakhir (semua cabang, barang_kode sama).
 */
function barang_get_harga_beli_terakhir($conn, $barang_kode)
{
	if (!$conn || $barang_kode === '') {
		return 0.0;
	}
	$kodeEsc = mysqli_real_escape_string($conn, $barang_kode);
	$sql = "
		SELECT p.barang_harga_beli
		FROM pembelian p
		INNER JOIN barang b ON p.barang_id = b.barang_id
		WHERE b.barang_kode = '$kodeEsc'
		  AND p.barang_qty > 0
		ORDER BY p.pembelian_date DESC, p.pembelian_id DESC
		LIMIT 1
	";
	$res = mysqli_query($conn, $sql);
	if ($res && ($row = mysqli_fetch_assoc($res))) {
		return round((float) ($row['barang_harga_beli'] ?? 0), 1);
	}
	return 0.0;
}

/**
 * Harga beli untuk input pembelian / edit (harga transaksi terakhir, bukan HPP rata-rata).
 */
function barang_get_harga_beli_untuk_input($conn, $barang_id)
{
	$barang_id = (int) $barang_id;
	if ($barang_id < 1 || !$conn) {
		return 0.0;
	}

	barang_harga_beli_rata_ensure_column($conn);
	$res = mysqli_query($conn, "
		SELECT barang_kode, barang_harga_beli, barang_harga_beli_rata
		FROM barang
		WHERE barang_id = $barang_id
		LIMIT 1
	");
	if (!$res || !($row = mysqli_fetch_assoc($res))) {
		return 0.0;
	}

	$kode = (string) ($row['barang_kode'] ?? '');
	if ($kode !== '') {
		$terakhir = barang_get_harga_beli_terakhir($conn, $kode);
		if ($terakhir > 0) {
			return $terakhir;
		}
	}

	$harga = round((float) ($row['barang_harga_beli'] ?? 0), 1);
	$rata = round((float) ($row['barang_harga_beli_rata'] ?? 0), 1);
	// Master barang_harga_beli kadang masih berisi HPP lama — jangan dipakai sebagai default input
	if ($rata > 0 && abs($harga - $rata) < 0.05) {
		return 0.0;
	}

	return $harga;
}

/**
 * Isi keranjang pembelian yang harga=0 dengan harga beli transaksi terakhir.
 */
function keranjang_pembelian_isi_harga_dari_terakhir($conn, $userId, $cabang)
{
	$userId = (int) $userId;
	$cabang = (int) $cabang;
	if ($userId < 1 || !$conn) {
		return;
	}

	$res = mysqli_query($conn, "
		SELECT kp.keranjang_id, kp.barang_id
		FROM keranjang_pembelian kp
		WHERE (kp.keranjang_harga = 0 OR kp.keranjang_harga IS NULL)
		  AND kp.keranjang_id_kasir = $userId
		  AND kp.keranjang_cabang = $cabang
	");
	if (!$res) {
		return;
	}

	while ($row = mysqli_fetch_assoc($res)) {
		$kid = (int) ($row['keranjang_id'] ?? 0);
		$bid = (int) ($row['barang_id'] ?? 0);
		if ($kid < 1 || $bid < 1) {
			continue;
		}
		$harga = barang_get_harga_beli_untuk_input($conn, $bid);
		if ($harga <= 0) {
			continue;
		}
		mysqli_query($conn, "UPDATE keranjang_pembelian SET keranjang_harga = '$harga' WHERE keranjang_id = $kid");
	}
}

/**
 * Pembelian ke-N dari yang terbaru (offset 0 = terakhir, 1 = sebelum terakhir).
 */
function barang_get_pembelian_ke($conn, $barang_kode, $offset = 0)
{
	if (!$conn || $barang_kode === '') {
		return null;
	}
	$offset = max(0, (int) $offset);
	$kodeEsc = mysqli_real_escape_string($conn, $barang_kode);
	$res = mysqli_query($conn, "
		SELECT p.barang_qty, p.barang_harga_beli, p.pembelian_date, p.pembelian_id
		FROM pembelian p
		INNER JOIN barang b ON p.barang_id = b.barang_id
		WHERE b.barang_kode = '$kodeEsc'
		  AND p.barang_qty > 0
		ORDER BY p.pembelian_date DESC, p.pembelian_id DESC
		LIMIT 1 OFFSET $offset
	");
	if ($res && ($row = mysqli_fetch_assoc($res))) {
		return $row;
	}
	return null;
}

/**
 * HPP per unit stok lama sebelum pembelian terakhir (koreksi data barang_harga_beli yang pernah salah).
 */
function hitungHppBarangBaselineUnit($conn, $barang_kode, $lastHarga = 0.0)
{
	if (!$conn || $barang_kode === '') {
		return 0.0;
	}
	barang_harga_beli_rata_ensure_column($conn);

	$kodeEsc = mysqli_real_escape_string($conn, $barang_kode);
	$stokRes = mysqli_query($conn, "
		SELECT
			COALESCE(SUM(CAST(NULLIF(TRIM(barang_stock), '') AS DECIMAL(18,4))), 0) AS total_stok,
			COALESCE(SUM(
				CAST(NULLIF(TRIM(barang_stock), '') AS DECIMAL(18,4))
				* CASE
					WHEN barang_harga_beli_rata > 0 THEN barang_harga_beli_rata
					ELSE barang_harga_beli
				  END
			), 0) AS total_nilai
		FROM barang
		WHERE barang_kode = '$kodeEsc'
		  AND barang_status = '1'
		  AND CAST(NULLIF(TRIM(barang_stock), '') AS DECIMAL(18,4)) > 0
	");

	$hppDasar = 0.0;
	if ($stokRes && ($stokRow = mysqli_fetch_assoc($stokRes))) {
		$totalStok = max(0.0, (float) ($stokRow['total_stok'] ?? 0));
		$totalNilai = max(0.0, (float) ($stokRow['total_nilai'] ?? 0));
		if ($totalStok > 0) {
			$hppDasar = $totalNilai / $totalStok;
		}
	}

	$prev = barang_get_pembelian_ke($conn, $barang_kode, 1);
	$prevPrice = $prev ? max(0.0, (float) ($prev['barang_harga_beli'] ?? 0)) : 0.0;
	$lastHarga = max(0.0, (float) $lastHarga);

	if ($lastHarga <= 0) {
		if ($hppDasar > 0) {
			return round($hppDasar, 1);
		}
		return $prevPrice > 0 ? round($prevPrice, 1) : 0.0;
	}

	// Pembelian diskon/bonus: harga baru jauh di bawah harga pembelian sebelumnya
	if ($prevPrice > $lastHarga * 1.15) {
		$hppDasar = max($hppDasar, $prevPrice);
	} elseif ($hppDasar <= 0 || $hppDasar < $lastHarga * 0.65) {
		// Baseline korup (sisa bug rerata seluruh history di master barang)
		if ($prevPrice > 0) {
			$hppDasar = $prevPrice;
		}
	}

	return $hppDasar > 0 ? round($hppDasar, 1) : round($lastHarga, 1);
}

/**
 * HPP akurat: stok cabang saat ini + pembelian terakhir (abaikan riwayat lama yang sudah habis).
 */
function hitungHppBarangSnapshotAkurat($conn, $barang_kode)
{
	if (!$conn || $barang_kode === '') {
		return 0.0;
	}
	barang_harga_beli_rata_ensure_column($conn);

	$kodeEsc = mysqli_real_escape_string($conn, $barang_kode);

	$stokRes = mysqli_query($conn, "
		SELECT COALESCE(SUM(CAST(NULLIF(TRIM(barang_stock), '') AS DECIMAL(18,4))), 0) AS total_stok
		FROM barang
		WHERE barang_kode = '$kodeEsc'
		  AND barang_status = '1'
	");
	$stokSekarang = 0.0;
	if ($stokRes && ($stokRow = mysqli_fetch_assoc($stokRes))) {
		$stokSekarang = max(0.0, (float) ($stokRow['total_stok'] ?? 0));
	}

	$last = barang_get_pembelian_ke($conn, $barang_kode, 0);
	if (!$last) {
		$hppDasar = hitungHppBarangBaselineUnit($conn, $barang_kode, 0.0);
		return $hppDasar > 0 ? $hppDasar : 0.0;
	}

	$lastQty = max(0.0, (float) ($last['barang_qty'] ?? 0));
	$lastHarga = max(0.0, (float) ($last['barang_harga_beli'] ?? 0));
	$lastDate = mysqli_real_escape_string($conn, (string) ($last['pembelian_date'] ?? ''));

	if ($lastQty <= 0 || $lastHarga <= 0) {
		return hitungHppBarangBaselineUnit($conn, $barang_kode, $lastHarga);
	}

	$terjualSetelah = 0.0;
	$terjualRes = mysqli_query($conn, "
		SELECT COALESCE(SUM(
			CASE
				WHEN p.barang_qty > 0 THEN p.barang_qty
				ELSE (p.barang_qty_keranjang * IFNULL(NULLIF(p.barang_qty_konversi_isi, 0), 1))
			END
		), 0) AS qty
		FROM penjualan p
		INNER JOIN barang b ON p.barang_id = b.barang_id
		WHERE b.barang_kode = '$kodeEsc'
		  AND p.penjualan_date > '$lastDate'
	");
	if ($terjualRes && ($terjualRow = mysqli_fetch_assoc($terjualRes))) {
		$terjualSetelah = max(0.0, (float) ($terjualRow['qty'] ?? 0));
	}

	$stokSebelumBeli = $stokSekarang + $terjualSetelah;
	if ($stokSebelumBeli <= 0) {
		return round($lastHarga, 1);
	}

	$hppDasar = hitungHppBarangBaselineUnit($conn, $barang_kode, $lastHarga);
	$pembagi = $stokSebelumBeli + $lastQty;
	$hpp = (($hppDasar * $stokSebelumBeli) + ($lastHarga * $lastQty)) / $pembagi;

	return round($hpp, 1);
}

/**
 * Simpan HPP & harga beli terakhir yang benar ke semua cabang (perbaikan data lama).
 */
function syncHppBarangByKode($conn, $barang_kode)
{
	if (!$conn || $barang_kode === '') {
		return false;
	}
	barang_harga_beli_rata_ensure_column($conn);

	$hpp = hitungHppBarangSnapshotAkurat($conn, $barang_kode);
	$hargaTerakhir = barang_get_harga_beli_terakhir($conn, $barang_kode);
	if ($hpp <= 0 && $hargaTerakhir <= 0) {
		return false;
	}

	$kodeEsc = mysqli_real_escape_string($conn, $barang_kode);
	$setParts = [];
	if ($hargaTerakhir > 0) {
		$setParts[] = "barang_harga_beli = '$hargaTerakhir'";
	}
	if ($hpp > 0) {
		$setParts[] = "barang_harga_beli_rata = '$hpp'";
	}
	if (empty($setParts)) {
		return false;
	}

	mysqli_query($conn, 'UPDATE barang SET ' . implode(', ', $setParts) . " WHERE barang_kode = '$kodeEsc'");

	return true;
}

/**
 * Perbaiki HPP semua barang aktif (data lama sebelum rumus baru).
 *
 * @return array{total: int, ok: int}
 */
function syncHppBarangSemua($conn)
{
	$hasil = ['total' => 0, 'ok' => 0];
	if (!$conn) {
		return $hasil;
	}
	barang_harga_beli_rata_ensure_column($conn);

	$res = mysqli_query($conn, "SELECT DISTINCT barang_kode FROM barang WHERE barang_status = '1' AND barang_kode <> '' ORDER BY barang_kode");
	if (!$res) {
		return $hasil;
	}

	while ($row = mysqli_fetch_assoc($res)) {
		$kode = trim((string) ($row['barang_kode'] ?? ''));
		if ($kode === '') {
			continue;
		}
		$hasil['total']++;
		if (syncHppBarangByKode($conn, $kode)) {
			$hasil['ok']++;
		}
	}

	return $hasil;
}

/**
 * Nama satuan dari ID (cabang 0 / master).
 */
function barang_get_satuan_nama($conn, $satuan_id)
{
	$satuan_id = (int) $satuan_id;
	if ($satuan_id < 1 || !$conn) {
		return '';
	}
	$res = mysqli_query($conn, "SELECT satuan_nama FROM satuan WHERE satuan_id = $satuan_id AND satuan_cabang = 0 LIMIT 1");
	if ($res && ($row = mysqli_fetch_assoc($res))) {
		return (string) ($row['satuan_nama'] ?? '');
	}
	return '';
}

/**
 * Info satuan & HPP barang (sample cabang 0) untuk form konversi.
 *
 * @return array<string, mixed>|null
 */
function barang_info_satuan_by_kode($conn, $kode)
{
	$kode = trim((string) $kode);
	if ($kode === '' || !$conn) {
		return null;
	}
	barang_harga_beli_rata_ensure_column($conn);
	$kodeEsc = mysqli_real_escape_string($conn, $kode);
	$res = mysqli_query($conn, "
		SELECT barang_id, barang_nama, satuan_id, satuan_id_2, satuan_id_3, satuan_id_4,
		       satuan_isi_2, satuan_isi_3, satuan_isi_4,
		       barang_harga_beli, barang_harga_beli_rata
		FROM barang
		WHERE barang_kode = '$kodeEsc' AND barang_status = '1'
		ORDER BY barang_cabang ASC
		LIMIT 1
	");
	if (!$res || !($row = mysqli_fetch_assoc($res))) {
		return null;
	}
	$row['satuan_nama_1'] = barang_get_satuan_nama($conn, (int) ($row['satuan_id'] ?? 0));
	$row['satuan_nama_2'] = barang_get_satuan_nama($conn, (int) ($row['satuan_id_2'] ?? 0));
	$row['satuan_nama_3'] = barang_get_satuan_nama($conn, (int) ($row['satuan_id_3'] ?? 0));
	$row['satuan_nama_4'] = barang_get_satuan_nama($conn, (int) ($row['satuan_id_4'] ?? 0));
	return $row;
}

/**
 * Konversi harga/HPP antar satuan.
 * $faktor = isi: 1 satuan besar = $faktor satuan kecil (contoh 1 RTG = 10 PCS → faktor 10).
 * $keSatuanBesar true: harga_kecil × faktor; false: harga_besar ÷ faktor.
 */
function barang_konversi_harga_satuan($nilai, $faktor, $keSatuanBesar = true)
{
	$nilai = (float) $nilai;
	$faktor = max(0.0001, (float) $faktor);
	if ($nilai <= 0) {
		return 0.0;
	}
	if ($keSatuanBesar) {
		return round($nilai * $faktor, 1);
	}
	return round($nilai / $faktor, 1);
}

/**
 * Preview konversi HPP/harga beli semua cabang setelah ganti satuan.
 *
 * @return list<array<string, mixed>>
 */
function barang_preview_hpp_ganti_satuan($conn, $kode, $faktor, $keSatuanBesar = true)
{
	$kode = trim((string) $kode);
	$faktor = max(0.0001, (float) $faktor);
	if ($kode === '' || !$conn) {
		return [];
	}
	barang_harga_beli_rata_ensure_column($conn);
	$kodeEsc = mysqli_real_escape_string($conn, $kode);
	$res = mysqli_query($conn, "
		SELECT barang_id, barang_cabang, barang_harga_beli, barang_harga_beli_rata, barang_stock
		FROM barang
		WHERE barang_kode = '$kodeEsc' AND barang_status = '1'
		ORDER BY barang_cabang ASC
	");
	$hasil = [];
	if (!$res) {
		return $hasil;
	}
	while ($row = mysqli_fetch_assoc($res)) {
		$beliLama = (float) ($row['barang_harga_beli'] ?? 0);
		$hppLama = barang_hpp_dari_row($row);
		$beliBaru = barang_konversi_harga_satuan($beliLama, $faktor, $keSatuanBesar);
		$hppBaru = barang_konversi_harga_satuan($hppLama, $faktor, $keSatuanBesar);
		$hasil[] = [
			'cabang' => (int) ($row['barang_cabang'] ?? 0),
			'stock' => (float) ($row['barang_stock'] ?? 0),
			'beli_lama' => $beliLama,
			'hpp_lama' => $hppLama,
			'beli_baru' => $beliBaru,
			'hpp_baru' => $hppBaru,
		];
	}
	return $hasil;
}

/**
 * Preview set HPP/harga beli manual (tanpa ×/÷ isi) semua cabang.
 *
 * @return list<array<string, mixed>>
 */
function barang_preview_hpp_manual($conn, $kode, $hppBaru, $beliBaru = null)
{
	$kode = trim((string) $kode);
	$hppBaru = round((float) $hppBaru, 1);
	$beliBaru = $beliBaru === null || $beliBaru === '' ? $hppBaru : round((float) $beliBaru, 1);
	if ($kode === '' || !$conn || $hppBaru <= 0) {
		return [];
	}
	barang_harga_beli_rata_ensure_column($conn);
	$kodeEsc = mysqli_real_escape_string($conn, $kode);
	$res = mysqli_query($conn, "
		SELECT barang_id, barang_cabang, barang_harga_beli, barang_harga_beli_rata, barang_stock
		FROM barang
		WHERE barang_kode = '$kodeEsc' AND barang_status = '1'
		ORDER BY barang_cabang ASC
	");
	$hasil = [];
	if (!$res) {
		return $hasil;
	}
	while ($row = mysqli_fetch_assoc($res)) {
		$hasil[] = [
			'cabang' => (int) ($row['barang_cabang'] ?? 0),
			'stock' => (float) ($row['barang_stock'] ?? 0),
			'beli_lama' => (float) ($row['barang_harga_beli'] ?? 0),
			'hpp_lama' => barang_hpp_dari_row($row),
			'beli_baru' => $beliBaru,
			'hpp_baru' => $hppBaru,
		];
	}
	return $hasil;
}

/**
 * Terapkan HPP & harga beli manual ke semua cabang.
 *
 * @return array{ok: bool, updated: int, preview: list<array<string, mixed>>}
 */
function barang_apply_hpp_manual($conn, $kode, $hppBaru, $beliBaru = null, $syncDariPembelian = false)
{
	$preview = barang_preview_hpp_manual($conn, $kode, $hppBaru, $beliBaru);
	if ($preview === []) {
		return ['ok' => false, 'updated' => 0, 'preview' => []];
	}

	$kodeEsc = mysqli_real_escape_string($conn, trim((string) $kode));
	$updated = 0;

	foreach ($preview as $p) {
		$beliBaruVal = (float) ($p['beli_baru'] ?? 0);
		$hppBaruVal = (float) ($p['hpp_baru'] ?? 0);
		$cabang = (int) ($p['cabang'] ?? 0);
		$set = [];
		if ($beliBaruVal > 0) {
			$set[] = "barang_harga_beli = '$beliBaruVal'";
		}
		if ($hppBaruVal > 0) {
			$set[] = "barang_harga_beli_rata = '$hppBaruVal'";
		}
		if ($set === []) {
			continue;
		}
		$q = 'UPDATE barang SET ' . implode(', ', $set) . " WHERE barang_kode = '$kodeEsc' AND barang_cabang = $cabang";
		if (mysqli_query($conn, $q) && mysqli_affected_rows($conn) >= 0) {
			$updated++;
		}
	}

	if ($syncDariPembelian && $updated > 0) {
		syncHppBarangByKode($conn, $kode);
		$preview = barang_preview_hpp_manual($conn, $kode, $hppBaru, $beliBaru);
		foreach ($preview as &$p) {
			$cab = (int) ($p['cabang'] ?? 0);
			$r = mysqli_query($conn, "SELECT barang_harga_beli, barang_harga_beli_rata FROM barang WHERE barang_kode = '$kodeEsc' AND barang_cabang = $cab LIMIT 1");
			if ($r && ($row = mysqli_fetch_assoc($r))) {
				$p['beli_baru'] = (float) ($row['barang_harga_beli'] ?? 0);
				$p['hpp_baru'] = barang_hpp_dari_row($row);
			}
		}
		unset($p);
	}

	return ['ok' => $updated > 0, 'updated' => $updated, 'preview' => $preview];
}

/**
 * Terapkan konversi HPP & harga beli terakhir ke semua cabang (setelah ubah satuan utama).
 *
 * @return array{ok: bool, updated: int, preview: list<array<string, mixed>>}
 */
function barang_apply_hpp_ganti_satuan($conn, $kode, $faktor, $keSatuanBesar = true, $syncDariPembelian = false)
{
	$preview = barang_preview_hpp_ganti_satuan($conn, $kode, $faktor, $keSatuanBesar);
	if ($preview === []) {
		return ['ok' => false, 'updated' => 0, 'preview' => []];
	}

	$kodeEsc = mysqli_real_escape_string($conn, trim((string) $kode));
	$updated = 0;

	foreach ($preview as $p) {
		$beliBaru = (float) ($p['beli_baru'] ?? 0);
		$hppBaru = (float) ($p['hpp_baru'] ?? 0);
		$cabang = (int) ($p['cabang'] ?? 0);
		$set = [];
		if ($beliBaru > 0) {
			$set[] = "barang_harga_beli = '$beliBaru'";
		}
		if ($hppBaru > 0) {
			$set[] = "barang_harga_beli_rata = '$hppBaru'";
		}
		if ($set === []) {
			continue;
		}
		$q = 'UPDATE barang SET ' . implode(', ', $set) . " WHERE barang_kode = '$kodeEsc' AND barang_cabang = $cabang";
		if (mysqli_query($conn, $q) && mysqli_affected_rows($conn) >= 0) {
			$updated++;
		}
	}

	if ($syncDariPembelian && $updated > 0) {
		syncHppBarangByKode($conn, $kode);
		$preview = barang_preview_hpp_ganti_satuan($conn, $kode, 1, true);
		foreach ($preview as &$p) {
			$kodeRow = mysqli_real_escape_string($conn, $kode);
			$cab = (int) ($p['cabang'] ?? 0);
			$r = mysqli_query($conn, "SELECT barang_harga_beli, barang_harga_beli_rata FROM barang WHERE barang_kode = '$kodeRow' AND barang_cabang = $cab LIMIT 1");
			if ($r && ($row = mysqli_fetch_assoc($r))) {
				$p['beli_baru'] = (float) ($row['barang_harga_beli'] ?? 0);
				$p['hpp_baru'] = barang_hpp_dari_row($row);
			}
		}
		unset($p);
	}

	return ['ok' => $updated > 0, 'updated' => $updated, 'preview' => $preview];
}

/**
 * Cek apakah konversi HPP ×/÷ isi masuk akal vs pembelian terakhir.
 *
 * @return array{level: string, pesan: string, hpp: float, beli_pembelian: float, beli_pembelian_lama: float, satuan_utama: string}
 */
function barang_cek_konversi_hpp_satuan($conn, $kode, $faktor, $keSatuanBesar = true)
{
	$hasil = [
		'level' => 'info',
		'pesan' => '',
		'hpp' => 0.0,
		'beli_pembelian' => 0.0,
		'beli_pembelian_lama' => 0.0,
		'satuan_utama' => '',
	];
	$info = barang_info_satuan_by_kode($conn, $kode);
	if (!$info) {
		$hasil['level'] = 'warning';
		$hasil['pesan'] = 'Barang tidak ditemukan.';
		return $hasil;
	}

	$hpp = barang_hpp_dari_row($info);
	$beliPembelian = barang_get_harga_beli_terakhir($conn, $kode);
	$prev = barang_get_pembelian_ke($conn, $kode, 1);
	$beliLama = $prev ? (float) ($prev['barang_harga_beli'] ?? 0) : 0.0;
	$faktor = max(0.0001, (float) $faktor);
	$satuan = (string) ($info['satuan_nama_1'] ?? '');

	$hasil['hpp'] = $hpp;
	$hasil['beli_pembelian'] = $beliPembelian;
	$hasil['beli_pembelian_lama'] = $beliLama;
	$hasil['satuan_utama'] = $satuan;

	if ($beliPembelian > 0 && $hpp > 0 && abs($hpp - $beliPembelian) / $beliPembelian <= 0.1) {
		if (barang_pembelian_sudah_lompat_skala($conn, $kode, $faktor)) {
			$hasil['level'] = 'warning';
			$hasil['pesan'] = 'Pembelian terakhir sudah loncat ~×' . format_harga_beli_tampilan($faktor)
				. ' dari pembelian sebelumnya — kemungkinan harga sudah skala ' . $satuan
				. '. Konversi × isi mungkin tidak perlu; gunakan Perbaiki HPP biasa jika ragu.';
		} else {
			$hasil['level'] = 'info';
			$hasil['pesan'] = 'HPP (' . format_harga_beli_tampilan($hpp) . ') angkanya sama dengan pembelian lama ('
				. format_harga_beli_tampilan($beliPembelian) . ') — wajar setelah ganti satuan ke ' . $satuan
				. ' tanpa pembelian baru. Riwayat pembelian tidak otomatis berubah; konversi × isi tetap bisa dipakai.';
		}
		return $hasil;
	}

	$hppSetelah = barang_konversi_harga_satuan($hpp, $faktor, $keSatuanBesar);
	if ($keSatuanBesar && $beliPembelian > 0 && $hppSetelah > 0 && barang_pembelian_sudah_lompat_skala($conn, $kode, $faktor)) {
		$hasil['level'] = 'warning';
		$hasil['pesan'] = 'Setelah × isi, HPP jadi ' . format_harga_beli_tampilan($hppSetelah)
			. ' — jauh dari pembelian terakhir ' . format_harga_beli_tampilan($beliPembelian)
			. '. Pembelian terakhir mungkin sudah satuan ' . $satuan . '; cek preview sebelum terapkan.';
		return $hasil;
	}

	if ($keSatuanBesar && $beliLama > 0 && $hpp > 0 && $beliPembelian > 0 && $beliPembelian > $beliLama * 1.2) {
		$perkiraanPcs = $beliPembelian / $faktor;
		if (abs($hpp - $perkiraanPcs) / max($perkiraanPcs, 1) <= 0.15) {
			$hasil['level'] = 'warning';
			$hasil['pesan'] = 'Pembelian terakhir sudah di satuan ' . $satuan . ' (' . format_harga_beli_tampilan($beliPembelian)
				. '). HPP master mungkin masih skala satuan kecil — konversi × isi bisa tepat, atau jalankan Perbaiki HPP biasa.';
			return $hasil;
		}
	}

	return $hasil;
}

/**
 * Pembelian terakhir loncat ~×faktor dari sebelumnya (harga sudah skala satuan besar).
 */
function barang_pembelian_sudah_lompat_skala($conn, $kode, $faktor)
{
	$prev = barang_get_pembelian_ke($conn, $kode, 1);
	$last = barang_get_pembelian_ke($conn, $kode, 0);
	if (!$prev || !$last) {
		return false;
	}
	$prevH = (float) ($prev['barang_harga_beli'] ?? 0);
	$lastH = (float) ($last['barang_harga_beli'] ?? 0);
	if ($prevH <= 0 || $lastH <= 0) {
		return false;
	}
	$faktor = max(0.0001, (float) $faktor);
	$ratio = $lastH / $prevH;
	return $ratio >= ($faktor * 0.75) && $ratio <= ($faktor * 1.35);
}

/**
 * Replay moving average pembelian/penjualan semua cabang untuk satu barang_kode.
 * @deprecated Jangan dipakai tampilan HPP — ikut menghitung pembelian lama yang sudah habis terjual.
 */
function hitungHppBarangMovingAverageByKode($conn, $barang_kode)
{
	if (!$conn || $barang_kode === '') {
		return 0.0;
	}

	$kodeEsc = mysqli_real_escape_string($conn, $barang_kode);
	$idRes = mysqli_query($conn, "SELECT barang_id FROM barang WHERE barang_kode = '$kodeEsc'");
	if (!$idRes) {
		return 0.0;
	}

	$ids = [];
	while ($row = mysqli_fetch_assoc($idRes)) {
		$ids[] = (int) $row['barang_id'];
	}
	if (empty($ids)) {
		return 0.0;
	}

	$idList = implode(',', $ids);
	$events = [];

	$sqlPb = "
		SELECT pembelian_id AS eid, pembelian_date AS tgl, barang_qty AS qty, barang_harga_beli AS harga
		FROM pembelian
		WHERE barang_id IN ($idList) AND barang_qty > 0
		ORDER BY pembelian_date ASC, pembelian_id ASC
	";
	$resPb = mysqli_query($conn, $sqlPb);
	if ($resPb) {
		while ($row = mysqli_fetch_assoc($resPb)) {
			$qty = (float) ($row['qty'] ?? 0);
			$harga = (float) ($row['harga'] ?? 0);
			if ($qty > 0 && $harga >= 0) {
				$events[] = [
					'tgl' => (string) ($row['tgl'] ?? ''),
					'ord' => 1,
					'eid' => (int) ($row['eid'] ?? 0),
					'tipe' => 'in',
					'qty' => $qty,
					'harga' => $harga,
				];
			}
		}
	}

	$sqlPj = "
		SELECT
			penjualan_id AS eid,
			penjualan_date AS tgl,
			CASE
				WHEN barang_qty > 0 THEN barang_qty
				ELSE (barang_qty_keranjang * IFNULL(NULLIF(barang_qty_konversi_isi, 0), 1))
			END AS qty
		FROM penjualan
		WHERE barang_id IN ($idList)
		ORDER BY penjualan_date ASC, penjualan_id ASC
	";
	$resPj = mysqli_query($conn, $sqlPj);
	if ($resPj) {
		while ($row = mysqli_fetch_assoc($resPj)) {
			$qty = (float) ($row['qty'] ?? 0);
			if ($qty > 0) {
				$events[] = [
					'tgl' => (string) ($row['tgl'] ?? ''),
					'ord' => 2,
					'eid' => (int) ($row['eid'] ?? 0),
					'tipe' => 'out',
					'qty' => $qty,
					'harga' => 0.0,
				];
			}
		}
	}

	usort($events, static function ($a, $b) {
		$c = strcmp($a['tgl'], $b['tgl']);
		if ($c !== 0) {
			return $c;
		}
		if ($a['ord'] !== $b['ord']) {
			return $a['ord'] <=> $b['ord'];
		}
		return $a['eid'] <=> $b['eid'];
	});

	$hpp = 0.0;
	$stock = 0.0;
	foreach ($events as $ev) {
		$qty = (float) $ev['qty'];
		if ($qty <= 0) {
			continue;
		}
		if ($ev['tipe'] === 'in') {
			$harga = (float) $ev['harga'];
			$pembagi = $stock + $qty;
			if ($pembagi > 0) {
				$hpp = (($hpp * $stock) + ($harga * $qty)) / $pembagi;
				$stock = $pembagi;
			}
		} else {
			$stock = max(0.0, $stock - $qty);
		}
	}

	return $hpp > 0 ? round($hpp, 1) : 0.0;
}

/**
 * Set harga beli terakhir & HPP rata-rata ke semua cabang setelah pembelian.
 * Panggil sebelum INSERT pembelian agar stok belum bertambah.
 */
function applyHargaBeliSetelahPembelian($conn, $barang_id, $qty_beli, $harga_beli_terakhir)
{
	$barang_id = (int) $barang_id;
	$qty_beli = max(0.0, (float) $qty_beli);
	$harga_beli_terakhir = round((float) $harga_beli_terakhir, 1);
	if ($barang_id < 1 || !$conn || $harga_beli_terakhir <= 0) {
		return;
	}
	barang_harga_beli_rata_ensure_column($conn);

	$kode = barang_get_kode_by_id($conn, $barang_id);
	if ($kode === '') {
		return;
	}

	$kodeEsc = mysqli_real_escape_string($conn, $kode);
	$stokRes = mysqli_query($conn, "
		SELECT COALESCE(SUM(CAST(NULLIF(TRIM(barang_stock), '') AS DECIMAL(18,4))), 0) AS total_stok
		FROM barang
		WHERE barang_kode = '$kodeEsc'
		  AND barang_status = '1'
	");
	$stokSekarang = 0.0;
	if ($stokRes && ($stokRow = mysqli_fetch_assoc($stokRes))) {
		$stokSekarang = max(0.0, (float) ($stokRow['total_stok'] ?? 0));
	}

	$hppDasar = hitungHppBarangBaselineUnit($conn, $kode, $harga_beli_terakhir);
	$pembagi = $stokSekarang + $qty_beli;
	if ($pembagi > 0) {
		$hpp = (($hppDasar * $stokSekarang) + ($harga_beli_terakhir * $qty_beli)) / $pembagi;
	} else {
		$hpp = $harga_beli_terakhir;
	}
	$hpp = round($hpp, 1);

	mysqli_query($conn, "
		UPDATE barang SET
			barang_harga_beli = '$harga_beli_terakhir',
			barang_harga_beli_rata = '$hpp'
		WHERE barang_kode = '$kodeEsc'
	");
}

/**
 * @deprecated Legacy: rata-rata seluruh riwayat pembelian per barang_id (tidak dipakai untuk HPP).
 */
function hitungHppBarangDariPembelian($conn, $barang_id)
{
	$barang_id = (int) $barang_id;
	if ($barang_id < 1 || !$conn) {
		return 0.0;
	}

	$kode = barang_get_kode_by_id($conn, $barang_id);
	if ($kode !== '') {
		return hitungHppBarangWeightedStok($conn, $kode, 0.0, 0.0);
	}

	return 0.0;
}

/**
 * Hitung HPP Moving Average dari histori pembelian & pengurangan penjualan.
 * Rumus setiap masuk barang:
 *   HPP = ((HPP_lama × stok_saat_ini) + (harga_beli_baru × qty_baru)) / (stok_saat_ini + qty_baru)
 * Penjualan hanya mengurangi stok; HPP tidak berubah.
 *
 * @return array{hpp: float, stock: float}
 */
function hitungHppBarangMovingAverage($conn, $barang_id, $cabang = null)
{
	$barang_id = (int) $barang_id;
	$hasil = ['hpp' => 0.0, 'stock' => 0.0];
	if ($barang_id < 1 || !$conn) {
		return $hasil;
	}

	$cabFilterPb = '';
	$cabFilterPj = '';
	if ($cabang !== null && $cabang !== '') {
		$cabang = (int) $cabang;
		$cabFilterPb = " AND pembelian_cabang = $cabang ";
		$cabFilterPj = " AND penjualan_cabang = $cabang ";
	}

	$events = [];

	// Pembelian: pakai qty net saat ini (barang_qty), agar retur pembelian ikut mengurangi basis HPP
	$sqlPb = "
		SELECT
			pembelian_id AS eid,
			pembelian_date AS tgl,
			barang_qty AS qty,
			barang_harga_beli AS harga
		FROM pembelian
		WHERE barang_id = $barang_id
		  $cabFilterPb
		ORDER BY pembelian_date ASC, pembelian_id ASC
	";
	$resPb = mysqli_query($conn, $sqlPb);
	if ($resPb) {
		while ($row = mysqli_fetch_assoc($resPb)) {
			$qty = (float) ($row['qty'] ?? 0);
			$harga = (float) ($row['harga'] ?? 0);
			if ($qty > 0 && $harga >= 0) {
				$events[] = [
					'tgl' => (string) ($row['tgl'] ?? ''),
					'ord' => 1,
					'eid' => (int) ($row['eid'] ?? 0),
					'tipe' => 'in',
					'qty' => $qty,
					'harga' => $harga,
				];
			}
		}
	}

	$sqlPj = "
		SELECT
			penjualan_id AS eid,
			penjualan_date AS tgl,
			CASE
				WHEN barang_qty > 0 THEN barang_qty
				ELSE (barang_qty_keranjang * IFNULL(NULLIF(barang_qty_konversi_isi, 0), 1))
			END AS qty
		FROM penjualan
		WHERE barang_id = $barang_id
		  $cabFilterPj
		ORDER BY penjualan_date ASC, penjualan_id ASC
	";
	$resPj = mysqli_query($conn, $sqlPj);
	if ($resPj) {
		while ($row = mysqli_fetch_assoc($resPj)) {
			$qty = (float) ($row['qty'] ?? 0);
			if ($qty > 0) {
				$events[] = [
					'tgl' => (string) ($row['tgl'] ?? ''),
					'ord' => 2,
					'eid' => (int) ($row['eid'] ?? 0),
					'tipe' => 'out',
					'qty' => $qty,
					'harga' => 0.0,
				];
			}
		}
	}

	usort($events, static function ($a, $b) {
		$c = strcmp($a['tgl'], $b['tgl']);
		if ($c !== 0) {
			return $c;
		}
		if ($a['ord'] !== $b['ord']) {
			return $a['ord'] <=> $b['ord'];
		}
		return $a['eid'] <=> $b['eid'];
	});

	$hpp = 0.0;
	$stock = 0.0;
	foreach ($events as $ev) {
		$qty = (float) $ev['qty'];
		if ($qty <= 0) {
			continue;
		}
		if ($ev['tipe'] === 'in') {
			$harga = (float) $ev['harga'];
			$pembagi = $stock + $qty;
			if ($pembagi > 0) {
				$hpp = (($hpp * $stock) + ($harga * $qty)) / $pembagi;
				$stock = $pembagi;
			}
		} else {
			// Keluar: stok berkurang, HPP per unit tetap
			$stock = max(0.0, $stock - $qty);
		}
	}

	$hasil['hpp'] = $hpp > 0 ? $hpp : 0.0;
	$hasil['stock'] = $stock;
	return $hasil;
}

/**
 * HPP untuk ditampilkan di barang-zoom / edit — sama dengan kolom list barang (per cabang).
 */
function hitungHppBarangUntukTampilan($conn, $barang_id, $cabang = null, $master_stock = null, $master_hpp = null)
{
	$barang_id = (int) $barang_id;
	unset($cabang, $master_stock, $master_hpp);
	if ($barang_id < 1 || !$conn) {
		return 0.0;
	}

	$res = mysqli_query($conn, "SELECT barang_harga_beli, barang_harga_beli_rata FROM barang WHERE barang_id = $barang_id LIMIT 1");
	if (!$res || !($row = mysqli_fetch_assoc($res))) {
		return 0.0;
	}

	return barang_hpp_dari_row($row);
}

/**
 * Total nilai persediaan dashboard: Σ (stok × HPP rata-rata) per cabang.
 * Satu query SQL — tidak hitung ulang HPP per barang (agar dashboard cepat).
 */
function dashboard_total_nilai_stok_beli_hpp($conn, $cabang)
{
	barang_harga_beli_rata_ensure_column($conn);
	$cabang = (int) $cabang;
	$res = mysqli_query($conn, "
		SELECT COALESCE(SUM(
			CAST(NULLIF(TRIM(barang_stock), '') AS DECIMAL(18,4))
			* CAST(
				CASE
					WHEN barang_harga_beli_rata > 0 THEN barang_harga_beli_rata
					ELSE barang_harga_beli
				END AS DECIMAL(18,4)
			)
		), 0) AS total
		FROM barang
		WHERE barang_cabang = $cabang
		  AND barang_status = '1'
		  AND barang_stock > 0
	");
	if ($res && ($row = mysqli_fetch_assoc($res))) {
		return (float) ($row['total'] ?? 0);
	}
	return 0.0;
}

// ============================================== Transaksi Pembelian ======================== //
function updateStockPembelian($data)
{
	global $conn;
	
	// Validasi data yang diperlukan
	if (empty($data['barang_ids']) || !is_array($data['barang_ids'])) {
		error_log("Error: barang_ids is empty or not an array");
		return 0;
	}
	
	$id                  = $data["barang_ids"];
	$keranjang_qty       = $data["keranjang_qty"] ?? [];
	$keranjang_id_kasir  = $data['keranjang_id_kasir'] ?? [];
	$pembelian_invoice   = $data['pembelian_invoice'] ?? [];
	$kik                 = $data['kik'];
	$barang_harga_beli   = $data['barang_harga_beli'] ?? [];
	$pembelian_invoice_parent = $data['pembelian_invoice_parent'] ?? [];
	$invoice_pembelian_cabang = $data['invoice_pembelian_cabang'];

	$pembelian_invoice2  = $data['pembelian_invoice2'];
	$invoice_tgl         = date("d F Y - g:i:s a");
	$invoice_supplier    = $data['invoice_supplier'];
	$invoice_total       = $data['invoice_total'];
	$invoice_bayar       = $data['angka1'];
	
	if ($invoice_bayar == null || $invoice_bayar == '') {
		echo "
			<script>
				alert('Anda Belum Input Nominal BAYAR !!!');
				document.location.reload();
			</script>
		";
		exit();
	}
	
	$invoice_kembali     = $invoice_bayar - $invoice_total;
	$invoice_date        = date("Y-m-d");
	$pembelian_date      = $data['pembelian_date'] ?? [];
	$invoice_pembelian_number_delete = $data['invoice_pembelian_number_delete'];
	$pembelian_invoice_parent2       = $data['pembelian_invoice_parent2'];
	$invoice_hutang				 	 = $data['invoice_hutang'];
	$pembelian_hutang                = ($invoice_hutang == 1);
	if ($invoice_hutang == 1) {
		$invoice_hutang_dp = $invoice_bayar;
	} else {
		$invoice_hutang_dp = 0;
	}
	$invoice_hutang_jatuh_tempo	    = $data['invoice_hutang_jatuh_tempo'] ?? '0';
	$invoice_hutang_lunas			= $data['invoice_hutang_lunas'];
	if ($pembelian_hutang && $invoice_bayar >= $invoice_total) {
		$invoice_hutang_lunas = 1;
		$invoice_hutang = 0;
	}
	$pembelian_cabang				= $data['pembelian_cabang'] ?? [];

	// Pastikan $keranjang_id_kasir adalah array
	if (!is_array($keranjang_id_kasir)) {
		error_log("Error: keranjang_id_kasir is not an array. Value: " . print_r($keranjang_id_kasir, true));
		return 0;
	}
	
	$jumlah = count($keranjang_id_kasir);
	
	// Validasi jumlah item
	if ($jumlah == 0) {
		error_log("Error: No items in cart. keranjang_id_kasir count: " . $jumlah);
		error_log("Data received: " . print_r($data, true));
		return 0;
	}

	// Cek No. Invoice
	$invoice_cek = mysqli_num_rows(mysqli_query($conn, "select * from invoice_pembelian where pembelian_invoice = '$pembelian_invoice2' && invoice_pembelian_cabang = '$invoice_pembelian_cabang' "));

	if ($invoice_cek > 0) {
		echo "
			<script>
				alert('No. Invoice Pembelian Sudah Digunakan Sebelumnya !!');
			</script>
		";
	} else {
		// Escape semua nilai untuk keamanan
		$pembelian_invoice2 = mysqli_real_escape_string($conn, $pembelian_invoice2);
		$pembelian_invoice_parent2 = mysqli_real_escape_string($conn, $pembelian_invoice_parent2);
		$invoice_tgl = mysqli_real_escape_string($conn, $invoice_tgl);
		$invoice_supplier = mysqli_real_escape_string($conn, $invoice_supplier);
		$invoice_total = floatval($invoice_total);
		$invoice_bayar = floatval($invoice_bayar);
		$invoice_kembali = floatval($invoice_kembali);
		$kik = intval($kik);
		$invoice_date = mysqli_real_escape_string($conn, $invoice_date);
		$invoice_hutang = intval($invoice_hutang);
		$invoice_hutang_dp = floatval($invoice_hutang_dp);
		$invoice_hutang_jatuh_tempo = mysqli_real_escape_string($conn, $invoice_hutang_jatuh_tempo);
		$invoice_hutang_lunas = intval($invoice_hutang_lunas);
		$invoice_pembelian_cabang = intval($invoice_pembelian_cabang);
		
		// query insert invoice
		$query1 = "INSERT INTO invoice_pembelian (pembelian_invoice, pembelian_invoice_parent, invoice_tgl, invoice_supplier, invoice_total, invoice_bayar, invoice_kembali, invoice_kasir, invoice_date, invoice_date_edit, invoice_kasir_edit, invoice_total_lama, invoice_bayar_lama, invoice_kembali_lama, invoice_hutang, invoice_hutang_dp, invoice_hutang_jatuh_tempo, invoice_hutang_lunas, invoice_pembelian_cabang) VALUES ('$pembelian_invoice2', '$pembelian_invoice_parent2', '$invoice_tgl', '$invoice_supplier', '$invoice_total', '$invoice_bayar', '$invoice_kembali', '$kik', '$invoice_date', ' ', ' ', '$invoice_total', '$invoice_bayar', '$invoice_kembali', '$invoice_hutang', '$invoice_hutang_dp', '$invoice_hutang_jatuh_tempo', '$invoice_hutang_lunas', '$invoice_pembelian_cabang')";
		
		$result_invoice = mysqli_query($conn, $query1);
		if (!$result_invoice) {
			$error_msg = mysqli_error($conn);
			error_log("Error inserting invoice_pembelian: " . $error_msg);
			error_log("Query: " . $query1);
			return 0;
		}


		for ($x = 0; $x < $jumlah; $x++) {
			// Escape semua nilai untuk keamanan
			$barang_id = intval($id[$x]);
			$qty = floatval($keranjang_qty[$x]);
			$id_kasir = intval($keranjang_id_kasir[$x]);
			$pembelian_inv = mysqli_real_escape_string($conn, $pembelian_invoice[$x]);
			$pembelian_inv_parent = mysqli_real_escape_string($conn, $pembelian_invoice_parent[$x]);
			$pembelian_dt = mysqli_real_escape_string($conn, $pembelian_date[$x]);
			$harga_beli = isset($barang_harga_beli[$x]) ? round((float)$barang_harga_beli[$x], 1) : 0;
			$cabang = intval($pembelian_cabang[$x] ?? $invoice_pembelian_cabang);

			if ($harga_beli <= 0 && $barang_id > 0) {
				$res_brg = mysqli_query($conn, "SELECT barang_harga_beli FROM barang WHERE barang_id = $barang_id LIMIT 1");
				if ($res_brg && ($row_brg = mysqli_fetch_assoc($res_brg))) {
					$harga_beli = (float) $row_brg['barang_harga_beli'];
				}
			}

			// Harga beli terakhir + HPP rata-rata (sebelum stok bertambah)
			applyHargaBeliSetelahPembelian($conn, $barang_id, $qty, $harga_beli);

			$query = "INSERT INTO pembelian (pembelian_barang_id, barang_id, barang_qty, keranjang_id_kasir, pembelian_invoice, pembelian_invoice_parent, pembelian_date, barang_qty_lama, barang_qty_lama_parent, barang_harga_beli, pembelian_cabang) VALUES ('$barang_id', '$barang_id', '$qty', '$id_kasir', '$pembelian_inv', '$pembelian_inv_parent', '$pembelian_dt', '$qty', '$qty', '$harga_beli', '$cabang')";
			
			$result_penjualan = mysqli_query($conn, $query);
			if (!$result_penjualan) {
				$error_msg = mysqli_error($conn);
				error_log("Error inserting pembelian: " . $error_msg);
				error_log("Query: " . $query);
				return 0;
			}
		}


		mysqli_query($conn, "DELETE FROM keranjang_pembelian WHERE keranjang_id_kasir = $kik");
		mysqli_query($conn, "DELETE FROM invoice_pembelian_number WHERE invoice_pembelian_number_delete = $invoice_pembelian_number_delete");
		
		akun_posting_setelah_pembelian(
			$conn,
			(int) $invoice_pembelian_cabang,
			$pembelian_hutang,
			(float) $invoice_total,
			(float) $invoice_hutang_dp
		);
		
		return mysqli_affected_rows($conn);
	}
}

// ======================================== Pembelian Edit ================================ //
function updateQTY2pembelian($data)
{
	global $conn;
	$id = $data["pembelian_id"];
	$bid = $data["barang_id"];

	// ambil data dari tiap elemen dalam form
	$barang_qty      = htmlspecialchars($data['barang_qty']);
	$barang_qty_lama = $data['barang_qty_lama'];

	// retur
	$barang_stock           = $data['barang_stock'];
	$barang_stock_kurang    = $barang_qty_lama - $barang_qty;
	$barang_stock_hasil     = $barang_stock - $barang_stock_kurang;
	// var_dump($barang_stock_hasil); die();

	if ($barang_qty > $barang_qty_lama) {
		echo "
			<script>
				alert('Jika Anda Ingin Menambahkan QTY Barang.. Lakukan Transaksi Invoice Baru !!!');
			</script>
		";
	} else {
		// query update data
		$query = "UPDATE pembelian SET 
					barang_qty       = '$barang_qty'
					WHERE pembelian_id = $id
					";
		$query1 = "UPDATE barang SET 
					barang_stock   = '$barang_stock_hasil'
					WHERE barang_id = $bid
					";
		mysqli_query($conn, $query1);
		mysqli_query($conn, $query);
		return mysqli_affected_rows($conn);
		// $query1 = "INSERT INTO retur VALUES ('', '$retur_barang_id', '$retur_invoice', '$retur_admin_id', '$retur_date', ' ', '$barang_stock')";
		// mysqli_query($conn, $query1);

	}
}

/**
 * Hitung ulang total invoice pembelian dari baris pembelian (qty × harga beli).
 */
function pembelian_hitung_total_dari_lines($conn, $invoice_parent, $cabang)
{
	$parentEsc = mysqli_real_escape_string($conn, (string) $invoice_parent);
	$cabang = (int) $cabang;
	$res = mysqli_query($conn, "
		SELECT COALESCE(SUM(barang_qty * barang_harga_beli), 0) AS total
		FROM pembelian
		WHERE pembelian_invoice_parent = '$parentEsc' AND pembelian_cabang = $cabang
	");
	if ($res && ($row = mysqli_fetch_assoc($res))) {
		return round((float) ($row['total'] ?? 0), 1);
	}
	return 0.0;
}

/**
 * Sinkronkan invoice_pembelian.invoice_total & invoice_kembali dari detail pembelian.
 */
function pembelian_sync_invoice_total_dari_lines($conn, $invoice_pembelian_id)
{
	$invoice_pembelian_id = (int) $invoice_pembelian_id;
	if ($invoice_pembelian_id < 1 || !$conn) {
		return false;
	}

	$res = mysqli_query($conn, "
		SELECT pembelian_invoice_parent, invoice_pembelian_cabang, invoice_bayar
		FROM invoice_pembelian
		WHERE invoice_pembelian_id = $invoice_pembelian_id
		LIMIT 1
	");
	if (!$res || !($inv = mysqli_fetch_assoc($res))) {
		return false;
	}

	$total = pembelian_hitung_total_dari_lines(
		$conn,
		$inv['pembelian_invoice_parent'] ?? '',
		(int) ($inv['invoice_pembelian_cabang'] ?? 0)
	);
	$bayar = round((float) ($inv['invoice_bayar'] ?? 0), 1);
	$kembali = round($bayar - $total, 1);

	mysqli_query($conn, "
		UPDATE invoice_pembelian SET
			invoice_total = '$total',
			invoice_kembali = '$kembali'
		WHERE invoice_pembelian_id = $invoice_pembelian_id
	");

	return true;
}

/**
 * Edit harga beli per baris invoice pembelian + recalc total & HPP.
 */
function updateHargaBeli2pembelian($data)
{
	global $conn;

	$id = (int) ($data['pembelian_id'] ?? 0);
	$invoice_pembelian_id = (int) ($data['invoice_pembelian_id'] ?? 0);
	$harga = round((float) ($data['barang_harga_beli'] ?? 0), 1);

	if ($id < 1 || $invoice_pembelian_id < 1 || $harga <= 0) {
		return 0;
	}

	$res = mysqli_query($conn, "
		SELECT b.barang_kode
		FROM pembelian p
		INNER JOIN barang b ON p.barang_id = b.barang_id
		WHERE p.pembelian_id = $id
		LIMIT 1
	");
	if (!$res || !($row = mysqli_fetch_assoc($res))) {
		return 0;
	}
	$barang_kode = trim((string) ($row['barang_kode'] ?? ''));

	mysqli_query($conn, "UPDATE pembelian SET barang_harga_beli = '$harga' WHERE pembelian_id = $id");
	$affected = mysqli_affected_rows($conn);

	pembelian_sync_invoice_total_dari_lines($conn, $invoice_pembelian_id);

	if ($barang_kode !== '') {
		syncHppBarangByKode($conn, $barang_kode);
	}

	return $affected >= 0 ? 1 : 0;
}

function updateInvoicePembelian($data)
{
	global $conn;
	$id = $data["invoice_pembelian_id"];

	// ambil data dari tiap elemen dalam form
	$invoice_total        = htmlspecialchars($data['invoice_total']);
	$invoice_bayar        = htmlspecialchars($data['angka1']);
	$invoice_kembali      = $invoice_bayar - $invoice_total;
	$invoice_kasir_edit   = $data['invoice_kasir_edit'];
	$invoice_date_edit    = date('Y-m-d');

	// query update data
	$query = "UPDATE invoice_pembelian SET 
					invoice_total      = '$invoice_total',
					invoice_bayar      = '$invoice_bayar',
					invoice_kembali    = '$invoice_kembali',
					invoice_date_edit  = '$invoice_date_edit',
					invoice_kasir_edit = '$invoice_kasir_edit'
					WHERE invoice_pembelian_id = $id
					";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

function hapusPembelianInvoice($id)
{
	global $conn;

	$id = intval($id);

	$qInv = mysqli_query($conn, "SELECT pembelian_invoice_parent, invoice_pembelian_cabang, invoice_hutang, invoice_total, invoice_hutang_dp, invoice_hutang_lunas FROM invoice_pembelian WHERE invoice_pembelian_id = $id LIMIT 1");
	$pip = $qInv ? mysqli_fetch_array($qInv) : null;
	if (!$pip) {
		return 0;
	}

	$pembelian_invoice_parent  = $pip["pembelian_invoice_parent"];
	$invoice_pembelian_cabang  = (int) $pip["invoice_pembelian_cabang"];
	$invoice_hutang = (int) ($pip['invoice_hutang'] ?? 0);
	$invoice_total = (float) ($pip['invoice_total'] ?? 0);
	$invoice_hutang_dp = (float) ($pip['invoice_hutang_dp'] ?? 0);
	$parentEsc = mysqli_real_escape_string($conn, (string) $pembelian_invoice_parent);

	// Batalkan posting COA sebelum hapus baris invoice
	akun_posting_batal_pembelian(
		$conn,
		$invoice_pembelian_cabang,
		$invoice_hutang === 1,
		$invoice_total,
		$invoice_hutang_dp
	);

	// Menghitung data di tabel HUtang sesuai No. Invoice Parent
	$hutang = mysqli_query($conn, "SELECT * FROM hutang WHERE hutang_invoice_parent = '$parentEsc' AND hutang_cabang = $invoice_pembelian_cabang");
	$jmlHutang = $hutang ? mysqli_num_rows($hutang) : 0;

	if ($jmlHutang > 0) {
		mysqli_query($conn, "DELETE FROM hutang WHERE hutang_invoice_parent = '$parentEsc' AND hutang_cabang = $invoice_pembelian_cabang");
	}

	mysqli_query($conn, "DELETE FROM pembelian WHERE pembelian_invoice_parent = '$parentEsc' AND pembelian_cabang = $invoice_pembelian_cabang");
	mysqli_query($conn, "DELETE FROM invoice_pembelian WHERE pembelian_invoice_parent = '$parentEsc' AND invoice_pembelian_cabang = $invoice_pembelian_cabang");

	return mysqli_affected_rows($conn);
}

// ===================================== Pindah Cabang ===================================== //
function editLokasiCabang($data)
{
	global $conn;
	$id = $data["user_id"];

	// ambil data dari tiap elemen dalam form
	$user_cabang = htmlspecialchars($data['user_cabang']);

	// query update data
	$query = "UPDATE user SET 
				user_cabang       = '$user_cabang'
				WHERE user_id     = $id
				";
	// var_dump($query); die();
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

// ======================================== Kurir ========================================== //
function editStatusKurir($data)
{
	global $conn;
	$id = $data["invoice_id"];

	// ambil data dari tiap elemen dalam form
	$invoice_status_kurir       = $data['invoice_status_kurir'];
	$invoice_date_selesai_kurir = date("d F Y g:i:s a");

	if ($invoice_status_kurir == 3) {
		// query update data
		$query = "UPDATE invoice SET 
				invoice_status_kurir 		= '$invoice_status_kurir',
				invoice_date_selesai_kurir	= '$invoice_date_selesai_kurir'
				WHERE invoice_id     = $id
		";
	} else {
		// query update data
		$query = "UPDATE invoice SET 
				invoice_status_kurir 		= '$invoice_status_kurir',
				invoice_date_selesai_kurir	= '-'
				WHERE invoice_id     = $id
		";
	}

	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

// ======================================= Piutang ======================================= //
function tambahCicilanPiutang($data)
{
	global $conn;
	$id = $data["invoice_id"];

	// ambil data dari tiap elemen dalam form
	$invoice_bayar_lama			= $data['invoice_bayar'];
	$piutang_nominal			= $data['piutang_nominal'];
	$invoice_bayar         		= $invoice_bayar_lama + $piutang_nominal;
	$invoice_sub_total			= $data['invoice_sub_total'];
	$invoice_kembali            = $invoice_bayar - $invoice_sub_total;

	$piutang_invoice			= $data['piutang_invoice'];
	$piutang_date				= date("Y-m-d");
	$piutang_date_time			= date("d F Y g:i:s a");
	$piutang_kasir				= $data['piutang_kasir'];
	$piutang_tipe_pembayaran	= $data['piutang_tipe_pembayaran'];
	$piutang_cabang				= $data['piutang_cabang'];

	if ($invoice_bayar >= $invoice_sub_total) {
		// query update data
		$query = "UPDATE invoice SET 
					invoice_bayar          = '$invoice_bayar',
					invoice_kembali        = '$invoice_kembali',
					invoice_piutang        = 0,
					invoice_piutang_lunas  = 1
					WHERE invoice_id = $id
				";
		mysqli_query($conn, $query);

		// Insert Tabel kembalian Piutang Cicilan
		$kembalian_piutang = $invoice_bayar - $invoice_sub_total;
		$query3 = "INSERT INTO piutang_kembalian (pl_invoice, pl_date, pl_date_time, pl_nominal, pl_cabang) VALUES ('$piutang_invoice', '$piutang_date', '$piutang_date_time', '$kembalian_piutang', '$piutang_cabang')";
		mysqli_query($conn, $query3);
	} else {
		// query update data
		$query = "UPDATE invoice SET 
					invoice_bayar          = '$invoice_bayar',
					invoice_kembali        = '$invoice_kembali'
					WHERE invoice_id = $id
				";
		mysqli_query($conn, $query);
	}



	// query insert data
	$query2 = "INSERT INTO piutang (piutang_invoice, piutang_date, piutang_date_time, piutang_kasir, piutang_nominal, piutang_tipe_pembayaran, piutang_cabang) VALUES ('$piutang_invoice', '$piutang_date', '$piutang_date_time', '$piutang_kasir', '$piutang_nominal', '$piutang_tipe_pembayaran', '$piutang_cabang')";
	mysqli_query($conn, $query2);

	akun_posting_pelunasan_piutang(
		$conn,
		(int) $piutang_cabang,
		(float) $piutang_nominal,
		(int) $piutang_tipe_pembayaran
	);

	return mysqli_affected_rows($conn);
}

function hapusCicilanPiutang($id)
{
	global $conn;


	// Ambil ID produk
	$data_id = $id;

	// Mencari No. Invoice
	$noInvoice = mysqli_query($conn, "select piutang_invoice, piutang_nominal, piutang_cabang from piutang where piutang_id = '" . $data_id . "'");
	$noInvoice = mysqli_fetch_array($noInvoice);
	$piutangInvoice = $noInvoice["piutang_invoice"];
	$nominal 		= $noInvoice["piutang_nominal"];
	$cabangInvoice 	= $noInvoice["piutang_cabang"];

	// Mencari Nilai Bayar di Tabel Invoive
	$bayarInvoice = mysqli_query($conn, "select invoice_id, invoice_bayar, invoice_sub_total from invoice where penjualan_invoice = '" . $piutangInvoice . "' && invoice_cabang = '" . $cabangInvoice . "' ");
	$bayarInvoice = mysqli_fetch_array($bayarInvoice);
	$invoice_id         = $bayarInvoice['invoice_id'];
	$bayar       		= $bayarInvoice['invoice_bayar'];
	$subTotalInvoice 	= $bayarInvoice['invoice_sub_total'];

	// Proses
	$invoice_bayar         		= $bayar - $nominal;
	$invoice_kembali            = $invoice_bayar - $subTotalInvoice;

	if ($invoice_bayar >= $subTotalInvoice) {
		// query update data
		$query2 = "UPDATE invoice SET 
					invoice_bayar          = '$invoice_bayar',
					invoice_kembali        = '$invoice_kembali',
					invoice_piutang        = 0,
					invoice_piutang_lunas  = 1
					WHERE invoice_id = $invoice_id
				";
	} else {
		// query update data
		$query2 = "UPDATE invoice SET 
					invoice_bayar          = '$invoice_bayar',
					invoice_kembali        = '$invoice_kembali',
					invoice_piutang        = 1,
					invoice_piutang_lunas  = 0
					WHERE invoice_id = $invoice_id
				";
	}
	mysqli_query($conn, $query2);

	// Ambil data piutang yang akan dihapus untuk mengembalikan saldo
	$query_piutang_data = "SELECT piutang_nominal, piutang_tipe_pembayaran, piutang_cabang FROM piutang WHERE piutang_id = $id";
	$result_piutang_data = mysqli_query($conn, $query_piutang_data);
	
	if ($result_piutang_data && mysqli_num_rows($result_piutang_data) > 0) {
		$piutang_data = mysqli_fetch_assoc($result_piutang_data);
		akun_posting_batal_pelunasan_piutang(
			$conn,
			(int) $piutang_data['piutang_cabang'],
			(float) $piutang_data['piutang_nominal'],
			(int) $piutang_data['piutang_tipe_pembayaran']
		);
	}

	mysqli_query($conn, "DELETE FROM piutang WHERE piutang_id = $id");

	return mysqli_affected_rows($conn);
}

function updateInvoicePiutang($data)
{
	global $conn;
	$id = $data["invoice_id"];

	// ambil data dari tiap elemen dalam form
	$invoice_total        = htmlspecialchars($data['invoice_total']);
	$invoice_ongkir       = $data['invoice_ongkir'];
	$invoice_sub_total    = $data['invoice_sub_total'];
	$invoice_bayar        = htmlspecialchars($data['angka1']);
	$invoice_kembali      = $invoice_bayar - $invoice_sub_total;
	$invoice_kasir_edit   = $data['invoice_kasir_edit'];
	$invoice_date_edit    = date('Y-m-d');



	if ($invoice_bayar >= $invoice_sub_total) {
		// query update data
		$query = "UPDATE invoice SET 
					invoice_total      		= '$invoice_total',
					invoice_ongkir     		= '$invoice_ongkir',
					invoice_sub_total  		= '$invoice_sub_total',
					invoice_bayar      		= '$invoice_bayar',
					invoice_kembali    		= '$invoice_kembali',
					invoice_date_edit  		= '$invoice_date_edit',
					invoice_kasir_edit 		= '$invoice_kasir_edit',
					invoice_piutang        	= 0,
					invoice_piutang_lunas 	= 1
					WHERE invoice_id = $id
				";
	} else {
		// query update data
		$query = "UPDATE invoice SET 
					invoice_total      		= '$invoice_total',
					invoice_ongkir     		= '$invoice_ongkir',
					invoice_sub_total  		= '$invoice_sub_total',
					invoice_bayar      		= '$invoice_bayar',
					invoice_kembali    		= '$invoice_kembali',
					invoice_date_edit  		= '$invoice_date_edit',
					invoice_kasir_edit 		= '$invoice_kasir_edit',
					invoice_piutang        	= 1,
					invoice_piutang_lunas 	= 0
					WHERE invoice_id = $id
				";
	}
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

// ======================================= Hutang ======================================= //
function tambahCicilanhutang($data)
{
	global $conn;
	$id = $data["invoice_pembelian_id"];

	// ambil data dari tiap elemen dalam form
	$invoice_bayar_lama			= $data['invoice_bayar'];
	$hutang_nominal				= $data['hutang_nominal'];
	$invoice_bayar         		= $invoice_bayar_lama + $hutang_nominal;
	$invoice_total				= $data['invoice_total'];
	$invoice_kembali            = $invoice_bayar - $invoice_total;

	$hutang_invoice				= $data['hutang_invoice'];
	$hutang_invoice_parent		= $data['hutang_invoice_parent'];
	$hutang_date				= date("Y-m-d");
	$hutang_date_time			= date("d F Y g:i:s a");
	$hutang_kasir				= $data['hutang_kasir'];
	$hutang_tipe_pembayaran		= $data['hutang_tipe_pembayaran'];
	$hutang_cabang				= $data['hutang_cabang'];

	if ($invoice_bayar >= $invoice_total) {
		// query update data
		$query = "UPDATE invoice_pembelian SET 
					invoice_bayar          = '$invoice_bayar',
					invoice_kembali        = '$invoice_kembali',
					invoice_hutang         = 0,
					invoice_hutang_lunas   = 1
					WHERE invoice_pembelian_id = $id
				";
		mysqli_query($conn, $query);

		// Insert Tabel kembalian Hutang Cicilan
		$kembalian_hutang = $invoice_bayar - $invoice_total;
		$query3 = "INSERT INTO hutang_kembalian (hl_invoice, hl_invoice_parent, hl_date, hl_date_time, hl_nominal, hl_cabang) VALUES ('$hutang_invoice', '$hutang_invoice_parent', '$hutang_date', '$hutang_date_time', '$kembalian_hutang', '$hutang_cabang')";
		mysqli_query($conn, $query3);
	} else {
		// query update data
		$query = "UPDATE invoice_pembelian SET 
					invoice_bayar          = '$invoice_bayar',
					invoice_kembali        = '$invoice_kembali'
					WHERE invoice_pembelian_id = $id
				";
		mysqli_query($conn, $query);
	}



	// query insert data
	$query2 = "INSERT INTO hutang (hutang_invoice, hutang_invoice_parent, hutang_date, hutang_date_time, hutang_kasir, hutang_nominal, hutang_tipe_pembayaran, hutang_cabang) VALUES ('$hutang_invoice', '$hutang_invoice_parent', '$hutang_date', '$hutang_date_time', '$hutang_kasir', '$hutang_nominal', '$hutang_tipe_pembayaran', '$hutang_cabang')";
	mysqli_query($conn, $query2);

	akun_posting_pelunasan_hutang(
		$conn,
		(int) $hutang_cabang,
		(float) $hutang_nominal,
		(int) $hutang_tipe_pembayaran
	);

	return mysqli_affected_rows($conn);
}

function hapusCicilanHutang($id)
{
	global $conn;


	// Ambil ID produk
	$data_id = $id;

	// Mencari No. Invoice
	$noInvoice = mysqli_query($conn, "select hutang_invoice_parent, hutang_nominal, hutang_cabang from hutang where hutang_id = '" . $data_id . "'");
	$noInvoice = mysqli_fetch_array($noInvoice);
	$invoiceParent 		 = $noInvoice["hutang_invoice_parent"];
	$nominal 			 = $noInvoice["hutang_nominal"];
	$cabangInvoice 	 	 = $noInvoice["hutang_cabang"];

	// Mencari Nilai Bayar di Tabel Invoive
	$bayarInvoicePembelian = mysqli_query($conn, "select invoice_pembelian_id, invoice_bayar, invoice_total from invoice_pembelian where pembelian_invoice_parent = '" . $invoiceParent . "' && invoice_pembelian_cabang = '" . $cabangInvoice . "' ");
	$bip 				  		  = mysqli_fetch_array($bayarInvoicePembelian);
	$invoice_pembelian_id         = $bip['invoice_pembelian_id'];
	$bayar       				  = $bip['invoice_bayar'];
	$totalInvoice 	              = $bip['invoice_total'];

	// Proses
	$invoice_bayar         		= $bayar - $nominal;
	$invoice_kembali            = $invoice_bayar - $totalInvoice;

	if ($invoice_bayar >= $totalInvoice) {
		// query update data
		$query2 = "UPDATE invoice_pembelian SET 
					invoice_bayar          = '$invoice_bayar',
					invoice_kembali        = '$invoice_kembali',
					invoice_hutang         = 0,
					invoice_hutang_lunas   = 1
					WHERE invoice_pembelian_id = $invoice_pembelian_id
				";
	} else {
		// query update data
		$query2 = "UPDATE invoice_pembelian SET 
					invoice_bayar          = '$invoice_bayar',
					invoice_kembali        = '$invoice_kembali',
					invoice_hutang         = 1,
					invoice_hutang_lunas   = 0
					WHERE invoice_pembelian_id = $invoice_pembelian_id
				";
	}
	mysqli_query($conn, $query2);

	// Ambil data hutang yang akan dihapus untuk mengembalikan saldo
	$query_hutang_data = "SELECT hutang_nominal, hutang_tipe_pembayaran, hutang_cabang FROM hutang WHERE hutang_id = $id";
	$result_hutang_data = mysqli_query($conn, $query_hutang_data);
	
	if ($result_hutang_data && mysqli_num_rows($result_hutang_data) > 0) {
		$hutang_data = mysqli_fetch_assoc($result_hutang_data);
		akun_posting_batal_pelunasan_hutang(
			$conn,
			(int) $hutang_data['hutang_cabang'],
			(float) $hutang_data['hutang_nominal'],
			(int) $hutang_data['hutang_tipe_pembayaran']
		);
	}

	mysqli_query($conn, "DELETE FROM hutang WHERE hutang_id = $id");

	return mysqli_affected_rows($conn);
}

function updateInvoicePembelianHutang($data)
{
	global $conn;
	$id = $data["invoice_pembelian_id"];

	// ambil data dari tiap elemen dalam form
	$invoice_total        = htmlspecialchars($data['invoice_total']);
	$invoice_bayar        = htmlspecialchars($data['angka1']);
	$invoice_kembali      = $invoice_bayar - $invoice_total;
	$invoice_kasir_edit   = $data['invoice_kasir_edit'];
	$invoice_date_edit    = date('Y-m-d');

	if ($invoice_bayar >= $invoice_total) {
		// query update data
		$query = "UPDATE invoice_pembelian SET 
					invoice_total      = '$invoice_total',
					invoice_bayar      = '$invoice_bayar',
					invoice_kembali    = '$invoice_kembali',
					invoice_date_edit  = '$invoice_date_edit',
					invoice_kasir_edit = '$invoice_kasir_edit',
					invoice_hutang        	= 0,
					invoice_hutang_lunas 	= 1
					WHERE invoice_pembelian_id = $id
				";
	} else {
		// query update data
		$query = "UPDATE invoice_pembelian SET 
					invoice_total      = '$invoice_total',
					invoice_bayar      = '$invoice_bayar',
					invoice_kembali    = '$invoice_kembali',
					invoice_date_edit  = '$invoice_date_edit',
					invoice_kasir_edit = '$invoice_kasir_edit',
					invoice_hutang        	= 1,
					invoice_hutang_lunas 	= 0
					WHERE invoice_pembelian_id = $id
				";
	}

	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

// ================================ Tranfer Stock Cabang =================================== //
function tambahTransferSelectCabang($data)
{
	global $conn;

	// ambil data dari tiap elemen dalam form
	$tsc_cabang_pusat 		= htmlspecialchars($data['tsc_cabang_pusat']);
	$tsc_cabang_penerima 	= htmlspecialchars($data['tsc_cabang_penerima']);
	$tsc_user_id 			= htmlspecialchars($data['tsc_user_id']);
	$tsc_cabang 			= htmlspecialchars($data['tsc_cabang']);


	$count = mysqli_query($conn, "select * from transfer_select_cabang where tsc_user_id = " . $tsc_user_id . " && tsc_cabang = " . $tsc_cabang . " ");
	$count = mysqli_num_rows($count);

	if ($count < 1) {
		$tsc_id = pos_table_next_id($conn, 'transfer_select_cabang', 'tsc_id');
		$query = "INSERT INTO transfer_select_cabang (tsc_id, tsc_cabang_pusat, tsc_cabang_penerima, tsc_user_id, tsc_cabang)
			VALUES ('$tsc_id', '$tsc_cabang_pusat', '$tsc_cabang_penerima', '$tsc_user_id', '$tsc_cabang')";
		mysqli_query($conn, $query);
	} else {
		mysqli_query($conn, "UPDATE transfer_select_cabang SET
			tsc_cabang_pusat = '$tsc_cabang_pusat',
			tsc_cabang_penerima = '$tsc_cabang_penerima'
			WHERE tsc_user_id = $tsc_user_id && tsc_cabang = $tsc_cabang");
	}

	return mysqli_affected_rows($conn);
}

function resetTransferSelectCabang($data)
{
	global $conn;

	// ambil data dari tiap elemen dalam form
	$tsc_user_id 			= htmlspecialchars($data['tsc_user_id']);
	$tsc_cabang 			= htmlspecialchars($data['tsc_cabang']);
	$tsc_cabang_pusat		= htmlspecialchars($data['tsc_cabang_pusat']);
	$tsc_cabang_penerima	= htmlspecialchars($data['tsc_cabang_penerima'] ?? '');

	$keranjang = mysqli_query($conn, "select * from keranjang_transfer where keranjang_transfer_id_kasir = " . $tsc_user_id . " && keranjang_transfer_cabang = " . $tsc_cabang_pusat . " ");
	$jmlkeranjang = mysqli_num_rows($keranjang);


	if ($jmlkeranjang > 0) {
		$delKer = "DELETE FROM keranjang_transfer WHERE keranjang_transfer_id_kasir = $tsc_user_id && keranjang_transfer_cabang = $tsc_cabang_pusat";
		if ($tsc_cabang_penerima !== '') {
			$delKer .= " && keranjang_penerima_cabang = $tsc_cabang_penerima";
		}
		mysqli_query($conn, $delKer);
	}

	mysqli_query($conn, "DELETE FROM transfer_select_cabang WHERE tsc_user_id = $tsc_user_id && tsc_cabang = $tsc_cabang");

	return mysqli_affected_rows($conn);
}

function tambahkeranjangtransfer($data)
{
	global $conn;
	// ambil data dari tiap elemen dalam form
	$keranjang_nama     			= $data['keranjang_nama'];
	$barang_id          			= $data['barang_id'];
	$keranjang_qty      			= 1;
	$keranjang_barang_sn_id     	= 0;
	$keranjang_barang_option_sn 	= $data['keranjang_barang_option_sn'];
	$keranjang_sn       			= 0;
	$keranjang_id_kasir 			= $data['keranjang_id_kasir'];
	$keranjang_cabang   			= $data['keranjang_cabang'];
	$keranjang_cabang_pengirim 		= $data['keranjang_cabang_pengirim'];
	$keranjang_cabang_tujuan		= $data['keranjang_cabang_tujuan'];
	$barang_kode_slug				= $data['barang_kode_slug'];
	$barang_kode 					= $data['barang_kode'];
	$cabang_penerima_stock			= $data['cabang_penerima_stock'];

	$keranjang_id_cek   			= $barang_id . $keranjang_id_kasir . $keranjang_cabang . $keranjang_cabang_tujuan;

	// Mencari Data Barang berdasarkan Kode Slug dan cabang (hanya aktif)
	$barangTujuan 		= mysqli_query($conn, "select barang_id from barang where barang_kode_slug = '" . $barang_kode_slug . "' && barang_cabang = " . $keranjang_cabang_tujuan . " && barang_status = '1' LIMIT 1");
	$jmlBarangTujuan 	= mysqli_num_rows($barangTujuan);

	// Kondisi Jika Cabang Penerima tidak memiliki Produk terkait
	if ($jmlBarangTujuan < 1) {
		echo "
  			<script>
  				alert('Maaf Kode Produk " . $barang_kode . " Tidak Ada di Toko " . $cabang_penerima_stock . " dan Coba Cek Kembali !!');
  			</script>
  		";
	} else {
		// Cek STOCK
		$barang_id_cek = mysqli_num_rows(mysqli_query($conn, "select * from keranjang_transfer where keranjang_id_cek = '$keranjang_id_cek' "));

		if ($barang_id_cek > 0 && $keranjang_barang_option_sn < 1) {
			$keranjangParent = mysqli_query($conn, "select keranjang_transfer_qty from keranjang_transfer where keranjang_id_cek = '" . $keranjang_id_cek . "'");
			$kp = mysqli_fetch_array($keranjangParent);
			$kp = $kp['keranjang_transfer_qty'];
			$kp += $keranjang_qty;

			$query = "UPDATE keranjang_transfer SET 
						keranjang_transfer_qty   = '$kp'
						WHERE keranjang_id_cek = $keranjang_id_cek
						";
			mysqli_query($conn, $query);
			return mysqli_affected_rows($conn);
		} else {
			// query insert data
			$query = "INSERT INTO keranjang_transfer (
				keranjang_transfer_nama, barang_id, barang_kode_slug, keranjang_transfer_qty,
				keranjang_barang_sn_id, keranjang_barang_option_sn, keranjang_sn,
				keranjang_transfer_id_kasir, keranjang_id_cek, keranjang_pengirim_cabang,
				keranjang_penerima_cabang, keranjang_transfer_cabang
			) VALUES (
				'$keranjang_nama', '$barang_id', '$barang_kode_slug', '$keranjang_qty',
				'$keranjang_barang_sn_id', '$keranjang_barang_option_sn', '$keranjang_sn',
				'$keranjang_id_kasir', '$keranjang_id_cek', '$keranjang_cabang_pengirim',
				'$keranjang_cabang_tujuan', '$keranjang_cabang'
			)";

			mysqli_query($conn, $query);

			return mysqli_affected_rows($conn);
		}
	}
}

function tambahKeranjangBarcodeTransfer($data)
{
	global $conn;

	$barang_kode 					= htmlspecialchars($data['inputbarcode']);
	$keranjang_cabang_pengirim 		= $data['keranjang_cabang_pengirim'];
	$keranjang_cabang_tujuan		= $data['keranjang_cabang_tujuan'];
	$keranjang_id_kasir 			= $data['keranjang_id_kasir'];
	$keranjang_cabang   			= $data['keranjang_cabang'];
	$cabang_penerima_stock			= htmlspecialchars($data['cabang_penerima_label'] ?? 'cabang penerima');

	// Ambil Data Barang berdasarkan Kode Barang (hanya aktif)
	$barang 	= mysqli_query($conn, "select barang_id, barang_nama, barang_harga, barang_option_sn, barang_kode_slug from barang where barang_kode = '" . $barang_kode . "' && barang_cabang = '" . $keranjang_cabang . "' && barang_status = '1' ORDER BY barang_id DESC LIMIT 1");
	$br 		= mysqli_fetch_array($barang);

	$barang_id  				= $br["barang_id"] ?? null;
	$keranjang_nama  			= $br["barang_nama"] ?? '';
	$keranjang_barang_option_sn = $br["barang_option_sn"] ?? 0;
	$barang_kode_slug			= $br["barang_kode_slug"] ?? '';
	$keranjang_qty      		= 1;
	$keranjang_barang_sn_id     = 0;
	$keranjang_sn       		= 0;
	$keranjang_id_cek   		= $barang_id . $keranjang_id_kasir . $keranjang_cabang . $keranjang_cabang_tujuan;

	// Kondisi jika scan Barcode Tidak sesuai
	if ($barang_id != null) {

		$barangTujuan = mysqli_query($conn, "select barang_id from barang where barang_kode_slug = '" . $barang_kode_slug . "' && barang_cabang = " . intval($keranjang_cabang_tujuan) . " && barang_status = '1' LIMIT 1");
		if (mysqli_num_rows($barangTujuan) < 1) {
			echo "
				<script>
					alert('Maaf Kode Produk " . $barang_kode . " Tidak Ada di Toko " . $cabang_penerima_stock . " dan Coba Cek Kembali !!');
					document.location.href = 'transfer-stock-cabang';
				</script>
			";
			return 0;
		}

		// Cek STOCK
		$barang_id_cek = mysqli_num_rows(mysqli_query($conn, "select * from keranjang_transfer where keranjang_id_cek = '$keranjang_id_cek' "));

		if ($barang_id_cek > 0 && $keranjang_barang_option_sn < 1) {
			$keranjangParent = mysqli_query($conn, "select keranjang_transfer_qty from keranjang_transfer where keranjang_id_cek = '" . $keranjang_id_cek . "'");
			$kp = mysqli_fetch_array($keranjangParent);
			$kp = $kp['keranjang_transfer_qty'];
			$kp += $keranjang_qty;

			$query = "UPDATE keranjang_transfer SET 
						keranjang_transfer_qty   = '$kp'
						WHERE keranjang_id_cek = $keranjang_id_cek
						";
			mysqli_query($conn, $query);
			return mysqli_affected_rows($conn);
		} else {
			// query insert data
			$query = "INSERT INTO keranjang_transfer (
				keranjang_transfer_nama, barang_id, barang_kode_slug, keranjang_transfer_qty,
				keranjang_barang_sn_id, keranjang_barang_option_sn, keranjang_sn,
				keranjang_transfer_id_kasir, keranjang_id_cek, keranjang_pengirim_cabang,
				keranjang_penerima_cabang, keranjang_transfer_cabang
			) VALUES (
				'$keranjang_nama', '$barang_id', '$barang_kode_slug', '$keranjang_qty',
				'$keranjang_barang_sn_id', '$keranjang_barang_option_sn', '$keranjang_sn',
				'$keranjang_id_kasir', '$keranjang_id_cek', '$keranjang_cabang_pengirim',
				'$keranjang_cabang_tujuan', '$keranjang_cabang'
			)";

			mysqli_query($conn, $query);

			return mysqli_affected_rows($conn);
		}
	} else {
		echo '
			<script>
				alert("Kode Produk Tidak ada di Data Master Barang dan Coba Cek Kembali !! ");
				document.location.href = "transfer-stock-cabang";
			</script>
		';
		return 0;
	}
}

function updateSnTransfer($data)
{
	global $conn;
	$id = $data["keranjang_id"];


	// ambil data dari tiap elemen dalam form
	$barang_sn_id  				= $data["barang_sn_id"];
	$keranjang_transfer_cabang 	= $data['keranjang_transfer_cabang'];


	$barang_sn_desc = mysqli_query($conn, "select barang_sn_desc from barang_sn where barang_sn_id = '" . $barang_sn_id . "'");
	$barang_sn_desc = mysqli_fetch_array($barang_sn_desc);
	$barang_sn_desc = $barang_sn_desc['barang_sn_desc'];

	// Menghitung jumlah No SN berdasarkan cabang jika ada maka di tolak
	$barang_sn_count = mysqli_query($conn, "select * from keranjang_transfer where keranjang_sn = '" . $barang_sn_desc . "' && keranjang_transfer_cabang = '" . $keranjang_transfer_cabang . "' ");
	$barang_sn_count = mysqli_num_rows($barang_sn_count);

	if ($barang_sn_count > 0) {
		echo "
    		<script>
    			alert('Data No.SN " . $barang_sn_desc . " Sudah ada di daftar transfer coba pilih yang lain !!');
    			document.location.href = 'transfer-stock-cabang';
    		</script>
    	";
	} else {
		// query update data
		$query = "UPDATE keranjang_transfer SET 
							keranjang_barang_sn_id  			= '$barang_sn_id',
							keranjang_sn            			= '$barang_sn_desc'
							WHERE keranjang_transfer_id      	= $id
					";

		mysqli_query($conn, $query);
	}

	return mysqli_affected_rows($conn);
}


function updateQtyTransfer($data)
{
	global $conn;
	$id = $data["keranjang_id"];

	// ambil data dari tiap elemen dalam form
	$keranjang_qty 		= htmlspecialchars($data['keranjang_qty']);
	$stock_brg 			= $data['stock_brg'];

	if ($keranjang_qty > $stock_brg) {
		echo "
			<script>
				alert('QTY Melebihi Stock Barang.. Coba Cek Lagi !!!');
				document.location.href = 'transfer-stock-cabang';
			</script>
		";
	} else {
		// query update data
		$query = "UPDATE keranjang_transfer SET 
					keranjang_transfer_qty   		= '$keranjang_qty'
					WHERE keranjang_transfer_id 	= $id
					";
		mysqli_query($conn, $query);
		return mysqli_affected_rows($conn);
	}
}

function hapusKeranjangTransfer($id)
{
	global $conn;

	mysqli_query($conn, "DELETE FROM keranjang_transfer WHERE keranjang_transfer_id = $id");

	return mysqli_affected_rows($conn);
}

/** Apakah DB punya trigger yang mengubah barang_stock pada transfer keluar/masuk. */
function transfer_db_has_stock_trigger($conn, $table, $event)
{
	static $cache = [];
	$table = trim((string) $table);
	$event = strtoupper(trim((string) $event));
	$key = $table . '|' . $event;
	if (array_key_exists($key, $cache)) {
		return $cache[$key];
	}
	if (!in_array($table, ['transfer_produk_keluar', 'transfer_produk_masuk'], true)) {
		return $cache[$key] = false;
	}

	$tableEsc = mysqli_real_escape_string($conn, $table);
	$eventEsc = mysqli_real_escape_string($conn, $event);
	$sql = "
		SELECT COUNT(*) AS n
		FROM information_schema.TRIGGERS
		WHERE TRIGGER_SCHEMA = DATABASE()
		  AND EVENT_OBJECT_TABLE = '$tableEsc'
		  AND EVENT_MANIPULATION = '$eventEsc'
		  AND ACTION_STATEMENT LIKE '%barang_stock%'
	";
	$res = mysqli_query($conn, $sql);
	$row = $res ? mysqli_fetch_assoc($res) : null;
	$cache[$key] = ((int) ($row['n'] ?? 0)) > 0;

	return $cache[$key];
}

/** Kurangi stok cabang pengirim saat transfer keluar (PCS). */
function transfer_apply_stock_keluar($conn, $barangId, $cabang, $qty)
{
	$barangId = (int) $barangId;
	$cabang = (int) $cabang;
	$qty = (float) $qty;
	if ($barangId < 1 || $qty <= 0) {
		return false;
	}

	$qtySql = penjualan_sql_decimal($qty);
	$ok = mysqli_query(
		$conn,
		"UPDATE barang SET barang_stock = barang_stock - $qtySql WHERE barang_id = $barangId AND barang_cabang = $cabang LIMIT 1"
	);

	return $ok && mysqli_affected_rows($conn) > 0;
}

/** Tambah stok cabang penerima saat transfer masuk dikonfirmasi (PCS, by slug). */
function transfer_apply_stock_masuk($conn, $slug, $cabangPenerima, $qty)
{
	$slugEsc = mysqli_real_escape_string($conn, (string) $slug);
	$cabangPenerima = (int) $cabangPenerima;
	$qty = (float) $qty;
	if ($slugEsc === '' || $qty <= 0) {
		return false;
	}

	$qtySql = penjualan_sql_decimal($qty);
	$ok = mysqli_query(
		$conn,
		"UPDATE barang SET barang_stock = barang_stock + $qtySql WHERE barang_kode_slug = '$slugEsc' AND barang_cabang = $cabangPenerima LIMIT 1"
	);

	return $ok && mysqli_affected_rows($conn) > 0;
}

/** Potong stok setelah INSERT transfer_produk_keluar, kecuali trigger DB sudah menanganinya. */
function transfer_stock_after_keluar_insert($conn, $barangId, $cabang, $qty)
{
	if (transfer_db_has_stock_trigger($conn, 'transfer_produk_keluar', 'INSERT')) {
		return true;
	}

	return transfer_apply_stock_keluar($conn, $barangId, $cabang, $qty);
}

/** Tambah stok setelah INSERT transfer_produk_masuk, kecuali trigger DB sudah menanganinya. */
function transfer_stock_after_masuk_insert($conn, $slug, $cabangPenerima, $qty)
{
	if (transfer_db_has_stock_trigger($conn, 'transfer_produk_masuk', 'INSERT')) {
		return true;
	}

	return transfer_apply_stock_masuk($conn, $slug, $cabangPenerima, $qty);
}

function prosesTransfer($data)
{
	global $conn;

	// Data Input Tabel Transfer
	$transfer_ref 				= htmlspecialchars($data['transfer_ref']);
	$transfer_count				= htmlspecialchars($data['transfer_count']);
	$transfer_date				= date("Y-m-d");
	$transfer_date_time			= date("d F Y g:i:s a");
	$transfer_note				= htmlspecialchars($data['transfer_note']);
	$transfer_pengirim_cabang   = $data['transfer_pengirim_cabang'];
	$transfer_penerima_cabang   = $data['transfer_penerima_cabang'];
	$transfer_id_tipe_keluar    = $data['transfer_id_tipe_keluar'];
	$transfer_id_tipe_masuk		= $data['transfer_id_tipe_masuk'];
	// Status Trnsfer Stock Antar Cabang
	// 1. Proses Kirim
	// 2. Selesai
	// 3. Dibatalkan 
	$transfer_status			= 1;
	$transfer_user				= htmlspecialchars($data['transfer_user']);
	$transfer_cabang 			= $data['transfer_cabang'];

	// ============================================================================= //
	// Data Input Tabel transfer_produk_keluar
	$tpk_transfer_barang_id		= $data['barang_id'];
	$tpk_barang_id				= $data['barang_id'];
	$tpk_kode_slug				= $data['tpk_kode_slug'];
	$tpk_qty					= $data['keranjang_transfer_qty'];
	$tpk_ref 					= $data['tpk_ref'];
	$tpk_date                   = $data['tpk_date'];
	$tpk_date_time              = $data['tpk_date_time'];
	$tpk_barang_option_sn       = $data['tpk_barang_option_sn'];
	$tpk_barang_sn_id           = $data['tpk_barang_sn_id'];
	$tpk_barang_sn_desc         = $data['tpk_barang_sn_desc'];
	$tpk_user                   = $data['keranjang_transfer_id_kasir'];
	$tpk_pengirim_cabang        = $data['tpk_pengirim_cabang'];
	$tpk_penerima_cabang        = $data['tpk_penerima_cabang'];
	$tpk_cabang                 = $data['tpk_cabang'];


	$jumlah = count($tpk_user);

	$usePhpStockKeluar = !transfer_db_has_stock_trigger($conn, 'transfer_produk_keluar', 'INSERT');
	if ($usePhpStockKeluar) {
		mysqli_begin_transaction($conn);
	}

	// query insert invoice
	$query1 = "INSERT INTO transfer (
		transfer_ref, transfer_count, transfer_date, transfer_date_time,
		transfer_terima_date, transfer_terima_date_time, transfer_note,
		transfer_pengirim_cabang, transfer_penerima_cabang, transfer_id_tipe_keluar,
		transfer_id_tipe_masuk, transfer_status, transfer_user, transfer_user_penerima, transfer_cabang
	) VALUES (
		'$transfer_ref', '$transfer_count', '$transfer_date', '$transfer_date_time',
		'', '', '$transfer_note', '$transfer_pengirim_cabang', '$transfer_penerima_cabang',
		'$transfer_id_tipe_keluar', '$transfer_id_tipe_masuk', '$transfer_status',
		'$transfer_user', 0, '$transfer_cabang'
	)";
	// var_dump($query1); die();
	if (!mysqli_query($conn, $query1)) {
		if ($usePhpStockKeluar) {
			mysqli_rollback($conn);
		}
		return 0;
	}

	for ($x = 0; $x < $jumlah; $x++) {
		$query = "INSERT INTO transfer_produk_keluar (
			tpk_transfer_barang_id, tpk_barang_id, tpk_kode_slug, tpk_qty, tpk_ref,
			tpk_date, tpk_date_time, tpk_barang_option_sn, tpk_barang_sn_id, tpk_barang_sn_desc,
			tpk_user, tpk_pengirim_cabang, tpk_penerima_cabang, tpk_cabang
		) VALUES (
			'$tpk_transfer_barang_id[$x]', '$tpk_barang_id[$x]', '$tpk_kode_slug[$x]', '$tpk_qty[$x]',
			'$tpk_ref[$x]', '$tpk_date[$x]', '$tpk_date_time[$x]', '$tpk_barang_option_sn[$x]',
			'$tpk_barang_sn_id[$x]', '$tpk_barang_sn_desc[$x]', '$tpk_user[$x]',
			'$tpk_pengirim_cabang[$x]', '$tpk_penerima_cabang[$x]', '$tpk_cabang[$x]'
		)";

		if (!mysqli_query($conn, $query)) {
			if ($usePhpStockKeluar) {
				mysqli_rollback($conn);
			}
			return 0;
		}

		if (!transfer_stock_after_keluar_insert(
			$conn,
			(int) $tpk_barang_id[$x],
			(int) $tpk_cabang[$x],
			(float) $tpk_qty[$x]
		)) {
			if ($usePhpStockKeluar) {
				mysqli_rollback($conn);
			}
			return 0;
		}
	}

	// Mencari banyak barang SN
	$barang_option_sn = mysqli_query($conn, "select tpk_barang_option_sn from transfer_produk_keluar where tpk_ref = '" . $transfer_ref . "' && tpk_barang_option_sn > 0 && tpk_cabang = '" . $transfer_cabang . "' ");
	$barang_option_sn = mysqli_num_rows($barang_option_sn);



	// Mencari ID SN
	if ($barang_option_sn > 0) {
		$barang_sn_id = query("SELECT * FROM transfer_produk_keluar WHERE tpk_ref = $transfer_ref && tpk_barang_option_sn > 0 && tpk_cabang = $transfer_cabang ");

		// var_dump($barang_sn_id); die();
		foreach ($barang_sn_id as $row) :
			$barang_sn_id = $row['tpk_barang_sn_id'];

			$barang = count($barang_sn_id);
			for ($i = 0; $i < $barang; $i++) {
				$query5 = "UPDATE barang_sn SET 
						barang_sn_status     = 5
						WHERE barang_sn_id = $barang_sn_id
				";
			}
			mysqli_query($conn, $query5);
		endforeach;
	}

	mysqli_query($conn, "DELETE FROM keranjang_transfer WHERE keranjang_transfer_id_kasir = $transfer_user && keranjang_transfer_cabang = '$transfer_pengirim_cabang' && keranjang_pengirim_cabang = '$transfer_pengirim_cabang' && keranjang_penerima_cabang = '$transfer_penerima_cabang'");
	mysqli_query($conn, "DELETE FROM transfer_select_cabang WHERE tsc_user_id = $transfer_user && tsc_cabang = $transfer_cabang");

	if ($usePhpStockKeluar) {
		if (!mysqli_commit($conn)) {
			mysqli_rollback($conn);
			return 0;
		}
	}

	return max(1, (int) $jumlah);
}

function hapusTransferStockCabang($id)
{
	global $conn;

	mysqli_query($conn, "DELETE FROM transfer WHERE transfer_ref = $id");
	mysqli_query($conn, "DELETE FROM transfer_produk_keluar WHERE tpk_ref = $id");

	return mysqli_affected_rows($conn);
}

function prosesKonfirmasiTransfer($data)
{
	global $conn;

	// Data Input Tabel Transfer
	$transfer_status 					= htmlspecialchars($data['transfer_status']);
	$transfer_terima_date				= date("Y-m-d");
	$transfer_terima_date_time			= date("d F Y g:i:s a");
	$transfer_ref 						= $data['transfer_ref'];
	$refEsc								= mysqli_real_escape_string($conn, (string) $transfer_ref);
	$transfer_user_penerima 			= $data['transfer_user_penerima'];
	$transfer_penerima_cabang			= $data['transfer_penerima_cabang'];
	// Status Trnsfer Stock Antar Cabang
	// 1. Proses Kirim
	// 2. Selesai
	// 3. Dibatalkan 

	// ============================================================================= //
	// Data Input Tabel transfer_produk_masuk
	$tpm_kode_slug			= $data['tpm_kode_slug'];
	$tpm_qty				= $data['tpm_qty'];
	$tpm_ref				= $data['tpm_ref'];
	$tpm_date				= $data['tpm_date'];
	$tpm_date_time 			= $data['tpm_date_time'];
	$tpm_barang_option_sn   = $data['tpm_barang_option_sn'];
	$tpm_barang_sn_id       = $data['tpm_barang_sn_id'];
	$tpm_barang_sn_desc     = $data['tpm_barang_sn_desc'];
	$tpm_user           	= $data['tpm_user'];
	$tpm_pengirim_cabang    = $data['tpm_pengirim_cabang'];
	$tpm_penerima_cabang    = $data['tpm_penerima_cabang'];
	$tpm_cabang        		= $data['tpm_cabang'];


	$jumlah = count($tpm_user);

	// Mencari banyak barang SN di tabel transfer_produk_keluar
	$barang_option_sn_produk_keluar = mysqli_query($conn, "select tpk_barang_option_sn from transfer_produk_keluar where tpk_ref = '" . $transfer_ref . "' && tpk_barang_option_sn > 0 && tpk_penerima_cabang = '" . $transfer_penerima_cabang . "' ");
	$barang_option_sn_produk_keluar = mysqli_num_rows($barang_option_sn_produk_keluar);


	if ($barang_option_sn_produk_keluar > 0) {
		if ($transfer_status > 0) {
			mysqli_begin_transaction($conn);
			// Hanya proses jika masih status kirim (1); cegah double submit / race → duplikat transfer_produk_masuk & stock dobel
			$query = "UPDATE transfer SET 
						transfer_terima_date   		= '$transfer_terima_date',
						transfer_terima_date_time   = '$transfer_terima_date_time',
						transfer_status 			= 2,
						transfer_user_penerima      = '$transfer_user_penerima'
						WHERE transfer_ref 			= '$refEsc' AND transfer_status = 1
						";
			if (!mysqli_query($conn, $query)) {
				mysqli_rollback($conn);
				return 0;
			}
			if (mysqli_affected_rows($conn) < 1) {
				mysqli_rollback($conn);
				$cek = mysqli_query($conn, "SELECT transfer_status FROM transfer WHERE transfer_ref = '$refEsc' LIMIT 1");
				$rowCek = $cek ? mysqli_fetch_assoc($cek) : null;
				if ($rowCek && (int) $rowCek['transfer_status'] === 2) {
					return 1;
				}
				return 0;
			}

			for ($x = 0; $x < $jumlah; $x++) {
				$query1 = "INSERT INTO transfer_produk_masuk (tpm_kode_slug, tpm_qty, tpm_ref, tpm_date, tpm_date_time, tpm_barang_option_sn, tpm_barang_sn_id, tpm_barang_sn_desc, tpm_user, tpm_pengirim_cabang, tpm_penerima_cabang, tpm_cabang) VALUES (
											'$tpm_kode_slug[$x]', 
											'$tpm_qty[$x]', 
											'$tpm_ref[$x]', 
											'$tpm_date[$x]', 
											'$tpm_date_time[$x]', 
											'$tpm_barang_option_sn[$x]', 
											'$tpm_barang_sn_id[$x]', 
											'$tpm_barang_sn_desc[$x]', 
											'$tpm_user[$x]', 
											'$tpm_pengirim_cabang[$x]', 
											'$tpm_penerima_cabang[$x]', 
											'$tpm_cabang[$x]')";
				if (!mysqli_query($conn, $query1)) {
					mysqli_rollback($conn);
					return 0;
				}
				if (!transfer_stock_after_masuk_insert(
					$conn,
					$tpm_kode_slug[$x],
					(int) $tpm_penerima_cabang[$x],
					(float) $tpm_qty[$x]
				)) {
					mysqli_rollback($conn);
					return 0;
				}
			}

			// Mencari banyak barang SN
			$barang_option_sn = mysqli_query($conn, "select tpm_barang_option_sn from transfer_produk_masuk where tpm_ref = '" . $transfer_ref . "' && tpm_barang_option_sn > 0 && tpm_penerima_cabang = '" . $transfer_penerima_cabang . "' ");
			$barang_option_sn = mysqli_num_rows($barang_option_sn);


			// Mencari ID SN
			if ($barang_option_sn > 0) {
				$barang_sn_id = query("SELECT * FROM transfer_produk_masuk WHERE tpm_ref = $transfer_ref && tpm_barang_option_sn > 0 && tpm_penerima_cabang = $transfer_penerima_cabang ");

				// var_dump($barang_sn_id); die();
				foreach ($barang_sn_id as $row) :
					$barang_sn_id = $row['tpm_barang_sn_id'];

					$barang = count($barang_sn_id);
					for ($i = 0; $i < $barang; $i++) {
						$query5 = "UPDATE barang_sn SET 
								barang_sn_status     = 1,
								barang_sn_cabang     = '$transfer_penerima_cabang'
								WHERE barang_sn_id = $barang_sn_id
						";
					}
					if (!mysqli_query($conn, $query5)) {
						mysqli_rollback($conn);
						return 0;
					}
				endforeach;
			}
			if (!mysqli_commit($conn)) {
				mysqli_rollback($conn);
				return 0;
			}
			return 1;
		} else {
			// query update data
			$query = "UPDATE transfer SET 
							transfer_terima_date   		= '$transfer_terima_date',
							transfer_terima_date_time   = '$transfer_terima_date_time',
							transfer_status 			= 0,
							transfer_user_penerima      = '$transfer_user_penerima'
							WHERE transfer_ref 			= '$refEsc'
							";
			mysqli_query($conn, $query);
		}
	} else {
		if ($transfer_status > 0) {
			mysqli_begin_transaction($conn);
			$query = "UPDATE transfer SET 
						transfer_terima_date   		= '$transfer_terima_date',
						transfer_terima_date_time   = '$transfer_terima_date_time',
						transfer_status 			= 2,
						transfer_user_penerima      = '$transfer_user_penerima'
						WHERE transfer_ref 			= '$refEsc' AND transfer_status = 1
						";
			if (!mysqli_query($conn, $query)) {
				mysqli_rollback($conn);
				return 0;
			}
			if (mysqli_affected_rows($conn) < 1) {
				mysqli_rollback($conn);
				$cek = mysqli_query($conn, "SELECT transfer_status FROM transfer WHERE transfer_ref = '$refEsc' LIMIT 1");
				$rowCek = $cek ? mysqli_fetch_assoc($cek) : null;
				if ($rowCek && (int) $rowCek['transfer_status'] === 2) {
					return 1;
				}
				return 0;
			}

			for ($x = 0; $x < $jumlah; $x++) {
				$query1 = "INSERT INTO transfer_produk_masuk (tpm_kode_slug, tpm_qty, tpm_ref, tpm_date, tpm_date_time, tpm_barang_option_sn, tpm_barang_sn_id, tpm_barang_sn_desc, tpm_user, tpm_pengirim_cabang, tpm_penerima_cabang, tpm_cabang) VALUES (
											'$tpm_kode_slug[$x]', 
											'$tpm_qty[$x]', 
											'$tpm_ref[$x]', 
											'$tpm_date[$x]', 
											'$tpm_date_time[$x]', 
											'$tpm_barang_option_sn[$x]', 
											'$tpm_barang_sn_id[$x]', 
											'$tpm_barang_sn_desc[$x]', 
											'$tpm_user[$x]', 
											'$tpm_pengirim_cabang[$x]', 
											'$tpm_penerima_cabang[$x]', 
											'$tpm_cabang[$x]')";
				if (!mysqli_query($conn, $query1)) {
					mysqli_rollback($conn);
					return 0;
				}
				if (!transfer_stock_after_masuk_insert(
					$conn,
					$tpm_kode_slug[$x],
					(int) $tpm_penerima_cabang[$x],
					(float) $tpm_qty[$x]
				)) {
					mysqli_rollback($conn);
					return 0;
				}
			}
			if (!mysqli_commit($conn)) {
				mysqli_rollback($conn);
				return 0;
			}
			return 1;
		} else {
			// query update data
			$query = "UPDATE transfer SET 
							transfer_terima_date   		= '$transfer_terima_date',
							transfer_terima_date_time   = '$transfer_terima_date_time',
							transfer_status 			= 0,
							transfer_user_penerima      = '$transfer_user_penerima'
							WHERE transfer_ref 			= '$refEsc'
							";
			mysqli_query($conn, $query);
		}
	}

	return mysqli_affected_rows($conn);
}

/**
 * Hapus satu baris transfer_produk_masuk yang bagian dari duplikat (kunci grup sama),
 * lalu kurangi stok cabang penerima (kebalikan trigger tambah_stock_cabang).
 * Hanya jika masih ada ≥2 baris dengan kunci yang sama; cabang user harus cocok (pusat boleh semua).
 */
function hapusSatuTpmDuplikatTransferMasuk($tpm_id, $sessionCabang)
{
	global $conn;

	$tpm_id = (int) $tpm_id;
	if ($tpm_id < 1) {
		return ['ok' => false, 'msg' => 'ID tidak valid.'];
	}

	$rows = query("SELECT * FROM transfer_produk_masuk WHERE tpm_id = $tpm_id LIMIT 1");
	if (empty($rows)) {
		return ['ok' => false, 'msg' => 'Baris transfer masuk tidak ditemukan.'];
	}
	$r = $rows[0];
	$penerima = (int) $r['tpm_penerima_cabang'];
	if ($sessionCabang >= 1 && $penerima !== (int) $sessionCabang) {
		return ['ok' => false, 'msg' => 'Tidak boleh menghapus data cabang lain.'];
	}

	$ref = mysqli_real_escape_string($conn, (string) $r['tpm_ref']);
	$slug = mysqli_real_escape_string($conn, (string) $r['tpm_kode_slug']);
	$date = mysqli_real_escape_string($conn, (string) $r['tpm_date']);
	$dtime = mysqli_real_escape_string($conn, (string) $r['tpm_date_time']);
	$qty = (int) $r['tpm_qty'];
	$optSn = (int) $r['tpm_barang_option_sn'];
	$snId = (int) $r['tpm_barang_sn_id'];
	$pengirimCbg = (int) $r['tpm_pengirim_cabang'];

	$sqlCnt = "SELECT COUNT(*) AS cnt FROM transfer_produk_masuk
		WHERE tpm_ref = '$ref' AND tpm_kode_slug = '$slug' AND tpm_qty = $qty
		AND tpm_penerima_cabang = $penerima AND tpm_date = '$date' AND tpm_date_time = '$dtime'";
	$cr = mysqli_query($conn, $sqlCnt);
	if (!$cr) {
		return ['ok' => false, 'msg' => 'Gagal memeriksa duplikat.'];
	}
	$cnt = (int) (mysqli_fetch_assoc($cr)['cnt'] ?? 0);
	if ($cnt < 2) {
		return ['ok' => false, 'msg' => 'Bukan duplikat aktif: hanya satu baris dengan kunci ini. Penghapusan dibatalkan.'];
	}

	mysqli_begin_transaction($conn);

	$del = mysqli_query($conn, "DELETE FROM transfer_produk_masuk WHERE tpm_id = $tpm_id LIMIT 1");
	if (!$del || mysqli_affected_rows($conn) !== 1) {
		mysqli_rollback($conn);
		return ['ok' => false, 'msg' => 'Gagal menghapus baris transfer masuk.'];
	}

	$upd = mysqli_query($conn, "UPDATE barang SET barang_stock = barang_stock - $qty WHERE barang_kode_slug = '$slug' AND barang_cabang = $penerima LIMIT 1");
	if (!$upd || mysqli_affected_rows($conn) < 1) {
		mysqli_rollback($conn);
		return ['ok' => false, 'msg' => 'Gagal menyesuaikan stok (rollback). Periksa data barang.'];
	}

	if ($optSn > 0 && $snId > 0) {
		$updSn = mysqli_query($conn, "UPDATE barang_sn SET barang_sn_status = 5, barang_sn_cabang = $pengirimCbg WHERE barang_sn_id = $snId LIMIT 1");
		if (!$updSn) {
			mysqli_rollback($conn);
			return ['ok' => false, 'msg' => 'Gagal mengembalikan status SN (rollback).'];
		}
	}

	if (!mysqli_commit($conn)) {
		mysqli_rollback($conn);
		return ['ok' => false, 'msg' => 'Gagal menyimpan transaksi.'];
	}

	return ['ok' => true, 'msg' => 'Baris duplikat dihapus. Stok cabang penerima dikurangi ' . $qty . ' untuk SKU terkait.'];
}


// ====================================== Laba Bersih ===================================== //
function editLabaBersih($data)
{
	global $conn;
	$id = $data["lb_id"];

	// ambil data dari tiap elemen dalam form
	$lb_pendapatan_lain      			= $data["lb_pendapatan_lain"];
	$lb_pengeluaran_gaji      			= $data["lb_pengeluaran_gaji"];
	$lb_pengeluaran_listrik 			= $data["lb_pengeluaran_listrik"];
	$lb_pengeluaran_tlpn_internet     	= $data["lb_pengeluaran_tlpn_internet"];
	$lb_pengeluaran_perlengkapan_toko   = $data["lb_pengeluaran_perlengkapan_toko"];
	$lb_pengeluaran_biaya_penyusutan    = $data["lb_pengeluaran_biaya_penyusutan"];
	$lb_pengeluaran_bensin     			= $data["lb_pengeluaran_bensin"];
	$lb_pengeluaran_tak_terduga 		= $data["lb_pengeluaran_tak_terduga"];
	$lb_pengeluaran_lain        		= $data["lb_pengeluaran_lain"];
	$lb_cabang 							= $data["lb_cabang"];

	// query update data
	$query = "UPDATE laba_bersih SET 
				lb_pendapatan_lain       			= '$lb_pendapatan_lain',
				lb_pengeluaran_gaji       			= '$lb_pengeluaran_gaji',
				lb_pengeluaran_listrik      		= '$lb_pengeluaran_listrik',
				lb_pengeluaran_tlpn_internet      	= '$lb_pengeluaran_tlpn_internet',
				lb_pengeluaran_perlengkapan_toko    = '$lb_pengeluaran_perlengkapan_toko',
				lb_pengeluaran_biaya_penyusutan     = '$lb_pengeluaran_biaya_penyusutan',
				lb_pengeluaran_bensin  				= '$lb_pengeluaran_bensin',
				lb_pengeluaran_tak_terduga  		= '$lb_pengeluaran_tak_terduga',
				lb_pengeluaran_lain 				= '$lb_pengeluaran_lain'
				WHERE lb_id   = $id && lb_cabang = $lb_cabang
				";

	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}

// ============================= Stock Opname Keseluruhan ================================= //
function tambahStockOpname($data)
{
	global $conn;
	// ambil data dari tiap elemen dalam form
	$stock_opname_date_create 		= date("Y-m-d");
	$stock_opname_datetime_create 	= date("d F Y g:i:s a");
	$stock_opname_date_proses 		= htmlspecialchars($data['stock_opname_date_proses']);
	$stock_opname_user_create 		= htmlspecialchars($data['stock_opname_user_create']);
	$stock_opname_user_eksekusi 	= htmlspecialchars($data['stock_opname_user_eksekusi']);
	// Status 0 = Proses || 1 = selesai
	$stock_opname_status 			= 0;
	$stock_opname_tipe 				= htmlspecialchars($data['stock_opname_tipe']);
	$stock_opname_cabang 			= htmlspecialchars($data['stock_opname_cabang']);

	$userCreate = (int) $stock_opname_user_create;
	$userEksekusi = (int) $stock_opname_user_eksekusi;
	$tipe = (int) $stock_opname_tipe;
	$cabang = (int) $stock_opname_cabang;
	$d1 = mysqli_real_escape_string($conn, $stock_opname_date_create);
	$d2 = mysqli_real_escape_string($conn, $stock_opname_datetime_create);
	$d3 = mysqli_real_escape_string($conn, $stock_opname_date_proses);

	// Tanpa stock_opname_id (AUTO_INCREMENT); user_upload = 0 (bukan string kosong — strict mode INT)
	$query = "INSERT INTO stock_opname (
			stock_opname_date_create,
			stock_opname_datetime_create,
			stock_opname_date_proses,
			stock_opname_user_create,
			stock_opname_user_eksekusi,
			stock_opname_status,
			stock_opname_user_upload,
			stock_opname_date_upload,
			stock_opname_datetime_upload,
			stock_opname_tipe,
			stock_opname_cabang
		) VALUES (
			'$d1', '$d2', '$d3',
			$userCreate, $userEksekusi, $stock_opname_status,
			0, '', '',
			$tipe, $cabang
		)";
	mysqli_query($conn, $query);

	return mysqli_affected_rows($conn);
}

function hapusStockOpname($id, $sessionCabang)
{
	global $conn;

	$stock_opname_hasil_count = mysqli_query($conn, "SELECT * FROM stock_opname_hasil WHERE soh_stock_opname_id = $id && soh_barang_cabang = $sessionCabang");
	$stock_opname_hasil_count = mysqli_num_rows($stock_opname_hasil_count);


	if ($stock_opname_hasil_count > 0) {
		mysqli_query($conn, "DELETE FROM stock_opname_hasil WHERE soh_stock_opname_id = $id && soh_barang_cabang = $sessionCabang");
	}

	mysqli_query($conn, "DELETE FROM stock_opname WHERE stock_opname_id = $id");

	return mysqli_affected_rows($conn);
}

/**
 * Perbaiki baris soh_id <= 0 (bentrok dengan AUTO_INCREMENT / duplicate PRIMARY '0').
 */
function stock_opname_repair_soh_id_zero_rows()
{
	global $conn;
	for ($i = 0; $i < 5000; $i++) {
		$cq = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM stock_opname_hasil WHERE soh_id <= 0");
		if (!$cq) {
			break;
		}
		$c = (int) (mysqli_fetch_assoc($cq)['c'] ?? 0);
		if ($c < 1) {
			break;
		}
		$mxq = @mysqli_query($conn, "SELECT IFNULL(MAX(soh_id), 0) AS mx FROM stock_opname_hasil WHERE soh_id > 0");
		$mxRow = $mxq ? mysqli_fetch_assoc($mxq) : null;
		$mx = (int) ($mxRow['mx'] ?? 0);
		$newId = $mx + 1;
		if ($newId < 1) {
			$newId = 1;
		}
		$up = @mysqli_query($conn, "UPDATE stock_opname_hasil SET soh_id = " . (int) $newId . " WHERE soh_id <= 0 LIMIT 1");
		if (!$up || mysqli_affected_rows($conn) < 1) {
			break;
		}
	}
}

/**
 * Pastikan soh_id AUTO_INCREMENT (skema dump lama: NOT NULL tanpa default → gagal INSERT di MySQL strict).
 * Selalu selaraskan penghitung AUTO_INCREMENT ke MAX(soh_id)+1 agar tidak duplicate entry '0'.
 */
function stock_opname_ensure_soh_id_autoincrement()
{
	global $conn;
	static $idDone = false;
	if ($idDone) {
		return;
	}
	$idDone = true;

	$col = @mysqli_query($conn, "SHOW COLUMNS FROM stock_opname_hasil WHERE Field = 'soh_id'");
	$colRow = $col ? mysqli_fetch_assoc($col) : null;
	if (!$colRow) {
		return;
	}
	$hasAi = stripos((string) ($colRow['Extra'] ?? ''), 'auto_increment') !== false;

	// Sebelum ADD PRIMARY KEY: hilangkan soh_id duplikat/0 agar ALTER tidak gagal.
	stock_opname_repair_soh_id_zero_rows();

	if (!$hasAi) {
		$pkRows = [];
		$pk = @mysqli_query($conn, "SHOW INDEX FROM stock_opname_hasil WHERE Key_name = 'PRIMARY'");
		if ($pk) {
			while ($r = mysqli_fetch_assoc($pk)) {
				$pkRows[] = $r;
			}
		}
		if (empty($pkRows)) {
			@mysqli_query($conn, "ALTER TABLE stock_opname_hasil ADD PRIMARY KEY (soh_id)");
		}
		@mysqli_query(
			$conn,
			"ALTER TABLE stock_opname_hasil MODIFY COLUMN soh_id int(11) NOT NULL AUTO_INCREMENT"
		);
	}

	stock_opname_repair_soh_id_zero_rows();

	$mxq = @mysqli_query($conn, "SELECT IFNULL(MAX(soh_id), 0) AS mx FROM stock_opname_hasil");
	if ($mxq) {
		$mx = (int) (mysqli_fetch_assoc($mxq)['mx'] ?? 0);
		$next = max(1, $mx + 1);
		@mysqli_query($conn, "ALTER TABLE stock_opname_hasil AUTO_INCREMENT = " . $next);
	}
}

/**
 * Pastikan kolom soh_approved ada (0 = belum apply ke stok, 1 = sudah).
 */
function stock_opname_ensure_soh_approved_column()
{
	global $conn;
	static $done = false;
	if ($done) {
		return;
	}
	$done = true;
	$chk = @mysqli_query($conn, "SHOW COLUMNS FROM stock_opname_hasil LIKE 'soh_approved'");
	if ($chk && mysqli_num_rows($chk) > 0) {
		stock_opname_ensure_soh_id_autoincrement();
		return;
	}
	@mysqli_query(
		$conn,
		"ALTER TABLE stock_opname_hasil ADD COLUMN soh_approved TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=sudah apply ke barang_stock' AFTER soh_barang_cabang"
	);
	@mysqli_query(
		$conn,
		"UPDATE stock_opname_hasil h INNER JOIN stock_opname s ON s.stock_opname_id = h.soh_stock_opname_id SET h.soh_approved = 1 WHERE s.stock_opname_status > 0"
	);
	stock_opname_ensure_soh_id_autoincrement();
}

/** Apakah DB punya trigger yang mengubah barang_stock pada INSERT stock_opname_hasil. */
function stock_opname_db_has_stock_trigger($conn, $event = 'INSERT')
{
	static $cache = [];
	$event = strtoupper(trim((string) $event));
	if (array_key_exists($event, $cache)) {
		return $cache[$event];
	}

	$eventEsc = mysqli_real_escape_string($conn, $event);
	$sql = "
		SELECT COUNT(*) AS n
		FROM information_schema.TRIGGERS
		WHERE TRIGGER_SCHEMA = DATABASE()
		  AND EVENT_OBJECT_TABLE = 'stock_opname_hasil'
		  AND EVENT_MANIPULATION = '$eventEsc'
		  AND ACTION_STATEMENT LIKE '%barang_stock%'
	";
	$res = mysqli_query($conn, $sql);
	$row = $res ? mysqli_fetch_assoc($res) : null;
	$cache[$event] = ((int) ($row['n'] ?? 0)) > 0;

	return $cache[$event];
}

/**
 * Apply satu baris hasil SO ke barang.barang_stock (set = stok fisik).
 *
 * @return array{ok:bool,message:string,skipped?:bool}
 */
function stock_opname_apply_row_to_barang($conn, array $row, $user_id = 0, $markOnlyIfTrigger = false)
{
	$soh_id = (int) ($row['soh_id'] ?? 0);
	$bid = (int) ($row['soh_barang_id'] ?? 0);
	$cabang = (int) ($row['soh_barang_cabang'] ?? -1);
	$fisik = (int) ($row['soh_stock_fisik'] ?? -1);
	$approved = (int) ($row['soh_approved'] ?? $row['ap'] ?? 0);

	if ($soh_id < 1 || $bid < 1 || $cabang < 0) {
		return ['ok' => false, 'message' => 'Baris hasil SO tidak valid.'];
	}
	if ($approved === 1) {
		return ['ok' => true, 'message' => 'Sudah diterapkan.', 'skipped' => true];
	}
	if ($fisik < 0) {
		return ['ok' => false, 'message' => 'Stok fisik tidak valid.'];
	}

	$stok_lama = null;
	if (!$markOnlyIfTrigger) {
		$bq = mysqli_query($conn, "SELECT barang_stock FROM barang WHERE barang_id = $bid AND barang_cabang = $cabang LIMIT 1");
		$br = $bq ? mysqli_fetch_assoc($bq) : null;
		if ($br === null || !isset($br['barang_stock'])) {
			return ['ok' => false, 'message' => 'Barang tidak ditemukan.'];
		}
		$stok_lama = (int) $br['barang_stock'];

		if (!mysqli_query($conn, "UPDATE barang SET barang_stock = $fisik WHERE barang_id = $bid AND barang_cabang = $cabang LIMIT 1")) {
			return ['ok' => false, 'message' => 'Gagal memperbarui stok barang.'];
		}
	} else {
		$stok_lama = (int) ($row['soh_barang_stock_system'] ?? 0);
	}

	$selisih = $fisik - (int) $stok_lama;
	$user_id = (int) $user_id;
	$dt = mysqli_real_escape_string($conn, date('d F Y g:i:s a'));
	$mark = mysqli_query(
		$conn,
		"UPDATE stock_opname_hasil SET soh_approved = 1, soh_barang_stock_system = $stok_lama, soh_selisih = $selisih,
		 soh_datetime = '$dt', soh_user = $user_id WHERE soh_id = $soh_id AND soh_barang_cabang = $cabang AND IFNULL(soh_approved, 0) = 0 LIMIT 1"
	);
	if (!$mark || mysqli_affected_rows($conn) < 1) {
		return ['ok' => false, 'message' => 'Gagal menandai baris hasil SO.'];
	}

	return ['ok' => true, 'message' => 'Stok diterapkan.'];
}

/**
 * Terapkan semua baris hasil SO yang belum approved ke barang_stock.
 *
 * @return array{ok:bool,message:string,applied:int,skipped:int}
 */
function stock_opname_apply_pending_hasil($stock_opname_id, $cabang, $user_id = 0)
{
	global $conn;
	stock_opname_ensure_soh_approved_column();

	$stock_opname_id = (int) $stock_opname_id;
	$cabang = (int) $cabang;
	$user_id = (int) $user_id;
	if ($stock_opname_id < 1 || $cabang < 0) {
		return ['ok' => false, 'message' => 'Sesi tidak valid.', 'applied' => 0, 'skipped' => 0];
	}

	$markOnly = stock_opname_db_has_stock_trigger($conn, 'INSERT');
	$q = mysqli_query(
		$conn,
		"SELECT soh_id, soh_barang_id, soh_barang_cabang, soh_stock_fisik, soh_barang_stock_system, IFNULL(soh_approved, 0) AS soh_approved
		 FROM stock_opname_hasil
		 WHERE soh_stock_opname_id = $stock_opname_id AND soh_barang_cabang = $cabang
		 ORDER BY soh_id ASC"
	);
	if (!$q) {
		return ['ok' => false, 'message' => 'Gagal membaca hasil stock opname.', 'applied' => 0, 'skipped' => 0];
	}

	$applied = 0;
	$skipped = 0;
	while ($row = mysqli_fetch_assoc($q)) {
		$res = stock_opname_apply_row_to_barang($conn, $row, $user_id, $markOnly);
		if (!$res['ok']) {
			return ['ok' => false, 'message' => $res['message'], 'applied' => $applied, 'skipped' => $skipped];
		}
		if (!empty($res['skipped'])) {
			$skipped++;
		} else {
			$applied++;
		}
	}

	return [
		'ok' => true,
		'message' => $markOnly
			? 'Baris ditandai selesai (stok sudah diubah trigger saat input).'
			: "Stok diterapkan untuk $applied baris.",
		'applied' => $applied,
		'skipped' => $skipped,
	];
}

/**
 * Setujui satu baris hasil SO: apply soh_stock_fisik ke barang.barang_stock, kunci baris.
 *
 * @return array{ok:bool,message:string}
 */
function approveStockOpnameHasilBaris($soh_id, $cabang, $user_id)
{
	global $conn;
	stock_opname_ensure_soh_approved_column();

	$soh_id = (int) $soh_id;
	$cabang = (int) $cabang;
	$user_id = (int) $user_id;
	if ($soh_id < 1 || $cabang < 0) {
		return ['ok' => false, 'message' => 'Data tidak valid.'];
	}

	$q = mysqli_query(
		$conn,
		"SELECT h.soh_id, h.soh_stock_opname_id, h.soh_barang_id, h.soh_stock_fisik, h.soh_tipe, IFNULL(h.soh_approved, 0) AS ap,
			s.stock_opname_status, s.stock_opname_cabang
		 FROM stock_opname_hasil h
		 INNER JOIN stock_opname s ON s.stock_opname_id = h.soh_stock_opname_id
		 WHERE h.soh_id = $soh_id AND h.soh_barang_cabang = $cabang LIMIT 1"
	);
	$row = $q ? mysqli_fetch_assoc($q) : null;
	if (empty($row['soh_id'])) {
		return ['ok' => false, 'message' => 'Baris tidak ditemukan.'];
	}
	if ((int) $row['stock_opname_cabang'] !== $cabang) {
		return ['ok' => false, 'message' => 'Cabang tidak cocok.'];
	}
	if ((int) $row['stock_opname_status'] > 0) {
		return ['ok' => false, 'message' => 'Sesi stock opname sudah ditutup.'];
	}
	if ((int) $row['soh_tipe'] !== 0) {
		return ['ok' => false, 'message' => 'Hanya untuk SO per produk.'];
	}
	if ((int) $row['ap'] === 1) {
		return ['ok' => false, 'message' => 'Baris ini sudah disetujui sebelumnya.'];
	}

	$apply = stock_opname_apply_row_to_barang(
		$conn,
		[
			'soh_id' => $row['soh_id'],
			'soh_barang_id' => $row['soh_barang_id'],
			'soh_barang_cabang' => $cabang,
			'soh_stock_fisik' => $row['soh_stock_fisik'],
			'soh_approved' => 0,
		],
		$user_id,
		stock_opname_db_has_stock_trigger($conn, 'INSERT')
	);
	if (!$apply['ok']) {
		return ['ok' => false, 'message' => $apply['message']];
	}
	if (!empty($apply['skipped'])) {
		return ['ok' => false, 'message' => 'Baris ini sudah disetujui sebelumnya.'];
	}

	return ['ok' => true, 'message' => 'Disetujui. Stok sistem disamakan dengan stok fisik.'];
}

function tambahStockOpnamePerProduk($data)
{
	global $conn;
	stock_opname_ensure_soh_approved_column();
	// ambil data dari tiap elemen dalam form

	$soh_stock_opname_id 		= htmlspecialchars($data['soh_stock_opname_id']);
	$soh_barang_kode 			= htmlspecialchars($data['soh_barang_kode']);
	$soh_stock_fisik 			= htmlspecialchars($data['soh_stock_fisik']);
	$soh_note 					= htmlspecialchars($data['soh_note']);
	$soh_date 					= date("Y-m-d");
	$soh_datetime 				= date("d F Y g:i:s a");
	$soh_tipe 					= htmlspecialchars($data['soh_tipe']);
	$soh_user 					= htmlspecialchars($data['soh_user']);
	$soh_barang_cabang 			= htmlspecialchars($data['soh_barang_cabang']);

	$soh_barang_kode_slug       = str_replace(" ", "-", $soh_barang_kode);

	$barang         = mysqli_query($conn, "SELECT barang_id, barang_stock FROM barang WHERE barang_cabang = $soh_barang_cabang && barang_status = 1 && barang_kode_slug = '" . $soh_barang_kode_slug . "' ");
	$barang         = mysqli_fetch_array($barang);
	$barang_id      = $barang['barang_id'];
	$barang_stock   = $barang['barang_stock'];
	$soh_selisih            	= $soh_stock_fisik - $barang_stock;

	if ($barang_id == null) {
		echo '
            <script>
                alert("Kode Barang/Barcode ' . $soh_barang_kode . ' TIDAK ADA di DATA Barang !! Silahkan Sesuaikan & Cek Kembali dari penulisan Huruf Besar, Kecil, Spasi beserta KODE HARUS SESUAI !!");
                  document.location.reload();
            </script>
        ';
		exit();
	}

	$sid = (int) $soh_stock_opname_id;
	$bid = (int) $barang_id;
	$kodeEsc = mysqli_real_escape_string($conn, $soh_barang_kode);
	$noteEsc = mysqli_real_escape_string($conn, $soh_note);
	$dtEsc = mysqli_real_escape_string($conn, $soh_datetime);
	$sf = (int) $soh_stock_fisik;
	$bs = (int) $barang_stock;
	$sel = (int) $soh_selisih;
	$tp = (int) $soh_tipe;
	$usr = (int) $soh_user;
	$bc = (int) $soh_barang_cabang;

	$query = "INSERT INTO stock_opname_hasil (
			soh_stock_opname_id, soh_barang_id, soh_barang_kode, soh_barang_stock_system, soh_stock_fisik,
			soh_selisih, soh_note, soh_date, soh_datetime, soh_tipe, soh_user, soh_barang_cabang, soh_approved
		) VALUES (
			$sid, $bid, '$kodeEsc', $bs, $sf,
			$sel, '$noteEsc', '$soh_date', '$dtEsc', $tp, $usr, $bc, 0
		)";
	mysqli_query($conn, $query);

	return mysqli_affected_rows($conn);
}

/**
 * Simpan hasil stock opname per produk (mobile / API): upsert per barang per sesi,
 * opsi increment (+1) untuk scan berulang.
 *
 * @param array $data soh_stock_opname_id, soh_barang_kode, soh_barang_cabang, soh_user, soh_tipe,
 *                     soh_stock_fisik (absolut jika increment false), soh_note, increment (bool)
 * @return array{ok:bool,message:string,barang_nama?:string,barang_kode?:string,stock_fisik?:int,stock_sistem?:int,selisih?:int,mode?:string}
 */
function simpanStockOpnameHasilMobile($data)
{
	global $conn;
	stock_opname_ensure_soh_approved_column();

	$soh_stock_opname_id = (int) ($data['soh_stock_opname_id'] ?? 0);
	$soh_barang_cabang = (int) ($data['soh_barang_cabang'] ?? 0);
	$soh_user = (int) ($data['soh_user'] ?? 0);
	$soh_tipe = isset($data['soh_tipe']) ? (int) $data['soh_tipe'] : 0;
	$increment = !empty($data['increment']);
	$soh_barang_kode = isset($data['soh_barang_kode']) ? trim((string) $data['soh_barang_kode']) : '';
	$soh_stock_fisik_input = isset($data['soh_stock_fisik']) ? (int) $data['soh_stock_fisik'] : 0;
	$soh_note = isset($data['soh_note']) ? trim((string) $data['soh_note']) : '';

	if ($soh_stock_opname_id < 1 || $soh_barang_kode === '' || $soh_barang_cabang < 0) {
		return ['ok' => false, 'message' => 'Data tidak lengkap.'];
	}

	$cekSo = mysqli_query(
		$conn,
		"SELECT stock_opname_id, stock_opname_status, stock_opname_tipe FROM stock_opname 
		 WHERE stock_opname_id = $soh_stock_opname_id AND stock_opname_cabang = $soh_barang_cabang LIMIT 1"
	);
	$rowSo = mysqli_fetch_assoc($cekSo);
	if (!$rowSo) {
		return ['ok' => false, 'message' => 'Sesi stock opname tidak ditemukan.'];
	}
	if ((int) $rowSo['stock_opname_status'] > 0) {
		return ['ok' => false, 'message' => 'Sesi sudah selesai, tidak bisa menambah data.'];
	}
	if ((int) $rowSo['stock_opname_tipe'] !== 0) {
		return ['ok' => false, 'message' => 'Mode mobile hanya untuk stock opname per produk.'];
	}

	$slug = mysqli_real_escape_string($conn, str_replace(' ', '-', $soh_barang_kode));
	$barangQ = mysqli_query(
		$conn,
		"SELECT barang_id, barang_stock, barang_nama, barang_kode FROM barang 
		 WHERE barang_cabang = $soh_barang_cabang AND barang_status = 1 AND barang_kode_slug = '$slug' LIMIT 1"
	);
	$barang = mysqli_fetch_assoc($barangQ);
	if (empty($barang['barang_id'])) {
		return [
			'ok' => false,
			'message' => 'Kode/Barcode tidak ada di data barang aktif cabang ini. Periksa penulisan kode.',
		];
	}

	$barang_id = (int) $barang['barang_id'];
	$barang_stock = (int) $barang['barang_stock'];
	$soh_date = date('Y-m-d');
	$soh_datetime = date('d F Y g:i:s a');
	$kodeEsc = mysqli_real_escape_string($conn, $soh_barang_kode);
	$noteEsc = mysqli_real_escape_string($conn, $soh_note);

	$lockQ = mysqli_query(
		$conn,
		"SELECT soh_id FROM stock_opname_hasil 
		 WHERE soh_stock_opname_id = $soh_stock_opname_id AND soh_barang_id = $barang_id 
		   AND soh_tipe = $soh_tipe AND soh_barang_cabang = $soh_barang_cabang AND IFNULL(soh_approved, 0) = 1
		 LIMIT 1"
	);
	if ($lockQ && mysqli_num_rows($lockQ) > 0) {
		return [
			'ok' => false,
			'message' => 'Barang ini sudah disetujui (stok sudah diterapkan). Tidak bisa menambah atau mengubah lewat scan.',
		];
	}

	$exQ = mysqli_query(
		$conn,
		"SELECT soh_id, soh_stock_fisik FROM stock_opname_hasil 
		 WHERE soh_stock_opname_id = $soh_stock_opname_id AND soh_barang_id = $barang_id 
		   AND soh_tipe = $soh_tipe AND soh_barang_cabang = $soh_barang_cabang AND IFNULL(soh_approved, 0) = 0
		 ORDER BY soh_id DESC LIMIT 1"
	);
	$existing = mysqli_fetch_assoc($exQ);

	if ($increment) {
		$newFisik = $existing ? ((int) $existing['soh_stock_fisik'] + 1) : 1;
	} else {
		if ($soh_stock_fisik_input < 0) {
			return ['ok' => false, 'message' => 'Stok fisik tidak valid.'];
		}
		// Barang sama sebelum approve: tambahkan qty input ke akumulasi pending (bukan mengganti total).
		$newFisik = $existing ? ((int) $existing['soh_stock_fisik'] + $soh_stock_fisik_input) : $soh_stock_fisik_input;
	}

	$selisih = $newFisik - $barang_stock;

	if ($existing) {
		$soh_id = (int) $existing['soh_id'];
		$upd = "UPDATE stock_opname_hasil SET 
			soh_barang_kode = '$kodeEsc',
			soh_barang_stock_system = $barang_stock,
			soh_stock_fisik = $newFisik,
			soh_selisih = $selisih,
			soh_note = '$noteEsc',
			soh_date = '$soh_date',
			soh_datetime = '" . mysqli_real_escape_string($conn, $soh_datetime) . "',
			soh_user = $soh_user
			WHERE soh_id = $soh_id LIMIT 1";
		if (!mysqli_query($conn, $upd)) {
			return ['ok' => false, 'message' => 'Gagal memperbarui data.'];
		}
		$mode = 'update';
	} else {
		$dtIns = mysqli_real_escape_string($conn, $soh_datetime);
		$ins = "INSERT INTO stock_opname_hasil (
			soh_stock_opname_id, soh_barang_id, soh_barang_kode, soh_barang_stock_system, soh_stock_fisik,
			soh_selisih, soh_note, soh_date, soh_datetime, soh_tipe, soh_user, soh_barang_cabang, soh_approved
		) VALUES (
			$soh_stock_opname_id, $barang_id, '$kodeEsc', $barang_stock, $newFisik,
			$selisih, '$noteEsc', '$soh_date', '$dtIns', $soh_tipe, $soh_user, $soh_barang_cabang, 0)";
		if (!mysqli_query($conn, $ins)) {
			return ['ok' => false, 'message' => 'Gagal menyimpan data.'];
		}
		$mode = 'insert';
	}

	return [
		'ok' => true,
		'message' => 'Tersimpan.',
		'barang_nama' => $barang['barang_nama'],
		'barang_kode' => $barang['barang_kode'],
		'stock_fisik' => $newFisik,
		'stock_sistem' => $barang_stock,
		'selisih' => $selisih,
		'mode' => $mode,
	];
}

function editStockOpname($data)
{
	global $conn;
	$id = (int) $data['stock_opname_id'];

	// ambil data dari tiap elemen dalam form
	$stock_opname_user_upload 		= htmlspecialchars($data['stock_opname_user_upload']);
	$stock_opname_status 			= (int) htmlspecialchars($data['stock_opname_status']);
	$stock_opname_date_upload 		= date('Y-m-d');
	$stock_opname_datetime_upload 	= date('d F Y g:i:s a');
	$stock_opname_cabang			= (int) htmlspecialchars($data['stock_opname_cabang']);

	if ($stock_opname_status > 0) {
		mysqli_begin_transaction($conn);

		$apply = stock_opname_apply_pending_hasil($id, $stock_opname_cabang, (int) $stock_opname_user_upload);
		if (!$apply['ok']) {
			mysqli_rollback($conn);
			return 0;
		}

		$query = "UPDATE stock_opname SET 
            stock_opname_status           = '$stock_opname_status',
            stock_opname_user_upload      = '$stock_opname_user_upload',
            stock_opname_date_upload      = '$stock_opname_date_upload',
            stock_opname_datetime_upload  = '$stock_opname_datetime_upload'
            WHERE stock_opname_id         = $id && stock_opname_cabang = $stock_opname_cabang;
            ";
		if (!mysqli_query($conn, $query)) {
			mysqli_rollback($conn);
			return 0;
		}

		if (!mysqli_commit($conn)) {
			mysqli_rollback($conn);
			return 0;
		}

		return max(1, (int) $apply['applied'] + (int) $apply['skipped']);
	}

	$query = "UPDATE stock_opname SET 
            stock_opname_status           = '$stock_opname_status',
            stock_opname_user_upload      = '$stock_opname_user_upload',
            stock_opname_date_upload      = '$stock_opname_date_upload',
            stock_opname_datetime_upload  = '$stock_opname_datetime_upload'
            WHERE stock_opname_id         = $id && stock_opname_cabang = $stock_opname_cabang;
            ";
	mysqli_query($conn, $query);
	return mysqli_affected_rows($conn);
}


function getRandomColor()
{
	$r = rand(0, 255);
	$g = rand(0, 255);
	$b = rand(0, 255);
	return "rgba($r, $g, $b,";
}

function formatDate($date)
{
	return date('d-m-Y', strtotime($date));
}