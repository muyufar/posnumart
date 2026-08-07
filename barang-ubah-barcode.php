<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

require_once __DIR__ . '/aksi/barang-ubah-barcode-lib.php';

if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
	echo "<script>document.location.href = 'bo';</script>";
	exit;
}

// Hanya pusat + admin/super admin — ubah barcode berdampak multi-cabang.
$bolehUbah = ((int) $sessionCabang === 0) && ($levelLogin === 'admin' || $levelLogin === 'super admin');

$pesan = '';
$tipePesan = 'info';
$preview = null;
$hasilDetail = null;

$kodeLamaForm = isset($_POST['kode_lama']) ? trim((string) $_POST['kode_lama']) : (isset($_GET['kode']) ? trim((string) $_GET['kode']) : '');
$kodeBaruForm = isset($_POST['kode_baru']) ? trim((string) $_POST['kode_baru']) : '';
$kodeBaruUlangForm = isset($_POST['kode_baru_ulang']) ? trim((string) $_POST['kode_baru_ulang']) : '';

if ($bolehUbah && isset($_POST['preview_barcode'])) {
	$previewRes = barang_ubah_barcode_preview($conn, $kodeLamaForm);
	if ($previewRes['ok']) {
		$preview = $previewRes['data'];
		$pesan = 'Preview siap. Periksa dampak di bawah sebelum mengeksekusi.';
		$tipePesan = 'info';
		$kodeLamaForm = (string) ($preview['kode_lama'] ?? $kodeLamaForm);
	} else {
		$pesan = $previewRes['msg'];
		$tipePesan = 'warning';
	}
}

if ($bolehUbah && isset($_POST['jalankan_ubah'])) {
	if ($kodeBaruForm === '' || $kodeBaruUlangForm === '') {
		$pesan = 'Isi barcode baru dan ulangi untuk konfirmasi.';
		$tipePesan = 'warning';
	} elseif ($kodeBaruForm !== $kodeBaruUlangForm) {
		$pesan = 'Konfirmasi barcode baru tidak sama. Tidak ada data yang diubah.';
		$tipePesan = 'warning';
	} else {
		$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
		$userNama = '';
		if ($userId > 0) {
			$uRows = query('SELECT user_nama FROM user WHERE user_id = ' . $userId . ' LIMIT 1');
			if ($uRows !== []) {
				$userNama = (string) ($uRows[0]['user_nama'] ?? '');
			}
		}
		$hasil = barang_ubah_barcode_run($conn, $kodeLamaForm, $kodeBaruForm, [
			'user_id' => $userId,
			'user_nama' => $userNama !== '' ? $userNama : (string) ($levelLogin ?? ''),
		]);
		if ($hasil['ok']) {
			$pesan = $hasil['msg'];
			$tipePesan = 'success';
			$hasilDetail = $hasil['detail'] ?? null;
			$kodeLamaForm = $kodeBaruForm;
			$kodeBaruForm = '';
			$kodeBaruUlangForm = '';
			// Preview ulang dengan kode baru agar user melihat status terkini.
			$previewRes = barang_ubah_barcode_preview($conn, $kodeLamaForm);
			if ($previewRes['ok']) {
				$preview = $previewRes['data'];
			}
		} else {
			$pesan = $hasil['msg'];
			$tipePesan = 'danger';
			// Preview tetap kode lama jika gagal.
			$previewRes = barang_ubah_barcode_preview($conn, $kodeLamaForm);
			if ($previewRes['ok']) {
				$preview = $previewRes['data'];
			}
		}
	}
}

$riwayat = [];
if ($bolehUbah && bub_table_exists($conn, 'barang_barcode_ubah_log')) {
	$qLog = @mysqli_query($conn, 'SELECT * FROM barang_barcode_ubah_log ORDER BY id DESC LIMIT 20');
	if ($qLog) {
		while ($r = mysqli_fetch_assoc($qLog)) {
			$riwayat[] = $r;
		}
	}
}
?>

<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1>Ubah Barcode Barang</h1>
				</div>
				<div class="col-sm-6">
					<ol class="breadcrumb float-sm-right">
						<li class="breadcrumb-item"><a href="bo">Home</a></li>
						<li class="breadcrumb-item"><a href="barang">Barang</a></li>
						<li class="breadcrumb-item active">Ubah Barcode</li>
					</ol>
				</div>
			</div>
		</div>
	</section>

	<section class="content">
		<div class="container-fluid">
			<?php if ($pesan !== '') : ?>
			<div class="alert alert-<?= htmlspecialchars($tipePesan, ENT_QUOTES, 'UTF-8'); ?>">
				<?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8'); ?>
			</div>
			<?php endif; ?>

			<?php if (!$bolehUbah) : ?>
			<div class="alert alert-warning">
				Fitur ini hanya untuk <strong>admin pusat</strong>. Perubahan barcode diterapkan ke semua cabang sekaligus.
			</div>
			<?php else : ?>

			<div class="alert alert-secondary">
				<ul class="mb-0 pl-3">
					<li>Proses memakai <strong>transaksi database</strong>: jika salah satu update gagal, semua dibatalkan (rollback).</li>
					<li>Yang diubah: <code>barang.barang_kode</code> + <code>barang.barang_kode_slug</code> di semua cabang, lalu cascade ke SN, keranjang, transfer, stock opname, PO, marketplace (jika tabelnya ada).</li>
					<li>Barcode tidak lagi bisa diganti lewat halaman Edit Barang — hanya lewat fitur ini.</li>
					<li>Setelah sukses, cetak ulang label fisik yang masih memakai barcode lama.</li>
				</ul>
			</div>

			<div class="card card-primary">
				<div class="card-header">
					<h3 class="card-title">Form ubah barcode</h3>
				</div>
				<div class="card-body">
					<form method="post" autocomplete="off">
						<div class="form-row">
							<div class="form-group col-md-4">
								<label for="kode_lama">Barcode lama</label>
								<input type="text" name="kode_lama" id="kode_lama" class="form-control"
									value="<?= htmlspecialchars($kodeLamaForm, ENT_QUOTES, 'UTF-8'); ?>" required>
							</div>
							<div class="form-group col-md-4">
								<label for="kode_baru">Barcode baru</label>
								<input type="text" name="kode_baru" id="kode_baru" class="form-control"
									value="<?= htmlspecialchars($kodeBaruForm, ENT_QUOTES, 'UTF-8'); ?>">
							</div>
							<div class="form-group col-md-4">
								<label for="kode_baru_ulang">Ulangi barcode baru</label>
								<input type="text" name="kode_baru_ulang" id="kode_baru_ulang" class="form-control"
									value="<?= htmlspecialchars($kodeBaruUlangForm, ENT_QUOTES, 'UTF-8'); ?>">
							</div>
						</div>
						<button type="submit" name="preview_barcode" class="btn btn-info mr-2">Preview dampak</button>
						<button type="submit" name="jalankan_ubah" class="btn btn-danger"
							onclick="return confirm('Ubah barcode di SEMUA cabang + data terkait? Pastikan kode baru sudah benar. Proses akan di-rollback otomatis jika gagal.');">
							Jalankan ubah barcode
						</button>
					</form>
				</div>
			</div>

			<?php if (is_array($preview)) : ?>
			<div class="card card-outline card-info">
				<div class="card-header">
					<h3 class="card-title">Preview: <?= htmlspecialchars((string) $preview['barang_nama'], ENT_QUOTES, 'UTF-8'); ?></h3>
				</div>
				<div class="card-body">
					<p class="mb-2">
						Kode saat ini: <code><?= htmlspecialchars((string) $preview['kode_lama'], ENT_QUOTES, 'UTF-8'); ?></code>
						&nbsp;|&nbsp; Cabang terdampak: <strong><?= (int) $preview['cabang_count']; ?></strong>
					</p>
					<p class="mb-2 text-muted">
						Slug lama:
						<?php foreach (($preview['slug_lama_list'] ?? []) as $sl) : ?>
							<code><?= htmlspecialchars((string) $sl, ENT_QUOTES, 'UTF-8'); ?></code>
						<?php endforeach; ?>
					</p>

					<div class="table-responsive mb-3">
						<table class="table table-sm table-bordered" style="max-width:720px;">
							<thead>
								<tr>
									<th>Cabang</th>
									<th>ID</th>
									<th>Status</th>
									<th>Stok</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach (($preview['rows'] ?? []) as $br) : ?>
								<tr>
									<td><?= (int) ($br['barang_cabang'] ?? 0); ?></td>
									<td><?= (int) ($br['barang_id'] ?? 0); ?></td>
									<td><?= ((int) ($br['barang_status'] ?? 0) === 1) ? 'Aktif' : 'Nonaktif'; ?></td>
									<td><?= htmlspecialchars((string) ($br['barang_stock'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<h5>Estimasi baris terkait</h5>
					<div class="table-responsive">
						<table class="table table-sm table-striped" style="max-width:640px;">
							<thead>
								<tr>
									<th>Tabel</th>
									<th>Kolom</th>
									<th>Baris cocok</th>
								</tr>
							</thead>
							<tbody>
								<?php if (empty($preview['impact'])) : ?>
								<tr><td colspan="3" class="text-muted">Tidak ada tabel terkait yang terdeteksi (selain master barang).</td></tr>
								<?php else : ?>
									<?php foreach ($preview['impact'] as $imp) : ?>
									<tr>
										<td><code><?= htmlspecialchars((string) $imp['table'], ENT_QUOTES, 'UTF-8'); ?></code></td>
										<td><code><?= htmlspecialchars((string) $imp['column'], ENT_QUOTES, 'UTF-8'); ?></code></td>
										<td><?= (int) $imp['rows']; ?></td>
									</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<?php if (is_array($hasilDetail)) : ?>
			<div class="card card-outline card-success">
				<div class="card-header"><h3 class="card-title">Hasil eksekusi</h3></div>
				<div class="card-body">
					<p>Baris <code>barang</code> terupdate: <strong><?= (int) ($hasilDetail['barang_updated'] ?? 0); ?></strong></p>
					<?php if (!empty($hasilDetail['cascade'])) : ?>
					<ul class="mb-0">
						<?php foreach ($hasilDetail['cascade'] as $c) : ?>
						<li>
							<code><?= htmlspecialchars($c['table'] . '.' . $c['column'], ENT_QUOTES, 'UTF-8'); ?></code>
							→ <?= (int) $c['affected']; ?> baris
						</li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>

			<div class="card">
				<div class="card-header">
					<h3 class="card-title">Riwayat 20 perubahan terakhir</h3>
				</div>
				<div class="card-body table-responsive p-0">
					<table class="table table-hover table-sm mb-0">
						<thead>
							<tr>
								<th>Waktu</th>
								<th>Lama → Baru</th>
								<th>Cabang</th>
								<th>Oleh</th>
							</tr>
						</thead>
						<tbody>
							<?php if ($riwayat === []) : ?>
							<tr><td colspan="4" class="text-muted p-3">Belum ada riwayat.</td></tr>
							<?php else : ?>
								<?php foreach ($riwayat as $log) : ?>
								<tr>
									<td><?= htmlspecialchars((string) ($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<code><?= htmlspecialchars((string) ($log['kode_lama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
										→
										<code><?= htmlspecialchars((string) ($log['kode_baru'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
									</td>
									<td><?= (int) ($log['cabang_count'] ?? 0); ?></td>
									<td><?= htmlspecialchars((string) ($log['user_nama'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
								</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<?php endif; ?>
		</div>
	</section>
</div>

<?php include '_footer.php'; ?>
