<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
	echo "<script>document.location.href = 'bo';</script>";
	exit;
}

$pesan = '';
$tipePesan = 'info';
$preview = null;

if ($levelLogin === 'super admin' && isset($_POST['preview_kode'])) {
	$kode = trim((string) ($_POST['barang_kode'] ?? ''));
	if ($kode !== '') {
		$preview = [
			'kode' => $kode,
			'harga_terakhir' => barang_get_harga_beli_terakhir($conn, $kode),
			'hpp' => hitungHppBarangSnapshotAkurat($conn, $kode),
		];
	}
}

if ($levelLogin === 'super admin' && isset($_POST['perbaiki_kode'])) {
	$kode = trim((string) ($_POST['barang_kode'] ?? ''));
	if ($kode === '') {
		$pesan = 'Isi barcode / kode barang.';
		$tipePesan = 'warning';
	} elseif (syncHppBarangByKode($conn, $kode)) {
		$pesan = 'HPP barang ' . $kode . ' berhasil diperbaiki.';
		$tipePesan = 'success';
	} else {
		$pesan = 'Gagal memperbaiki barang ' . $kode . ' (tidak ada data pembelian/stok).';
		$tipePesan = 'warning';
	}
}

if ($levelLogin === 'super admin' && isset($_POST['perbaiki_semua'])) {
	$hasil = syncHppBarangSemua($conn);
	$pesan = 'Selesai: ' . (int) $hasil['ok'] . ' dari ' . (int) $hasil['total'] . ' kode barang diperbaiki.';
	$tipePesan = $hasil['ok'] > 0 ? 'success' : 'warning';
}
?>

<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1>Perbaiki HPP Barang</h1>
				</div>
				<div class="col-sm-6">
					<ol class="breadcrumb float-sm-right">
						<li class="breadcrumb-item"><a href="bo">Home</a></li>
						<li class="breadcrumb-item"><a href="barang">Barang</a></li>
						<li class="breadcrumb-item active">Perbaiki HPP</li>
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

			<div class="card card-primary">
				<div class="card-header">
					<h3 class="card-title">Perbaiki satu barang</h3>
				</div>
				<div class="card-body">
					<p class="text-muted">
						Menggunakan rumus: (stok cabang × HPP lama + qty beli terakhir × harga beli terakhir) ÷ (stok + qty beli).
						Tidak menghitung ulang pembelian lama yang sudah habis terjual.
					</p>
					<form method="post" class="form-inline mb-3">
						<input type="text" name="barang_kode" class="form-control mr-2" placeholder="Barcode / kode barang" value="<?= htmlspecialchars($_POST['barang_kode'] ?? '8992775101421', ENT_QUOTES, 'UTF-8'); ?>" required>
						<button type="submit" name="preview_kode" class="btn btn-info mr-2">Preview</button>
						<button type="submit" name="perbaiki_kode" class="btn btn-primary" onclick="return confirm('Perbaiki HPP barang ini?');">Perbaiki</button>
					</form>

					<?php if ($preview !== null) : ?>
					<table class="table table-bordered" style="max-width:480px;">
						<tr><th>Kode</th><td><?= htmlspecialchars($preview['kode'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
						<tr><th>Harga beli terakhir</th><td><?= format_harga_beli_tampilan($preview['harga_terakhir']); ?></td></tr>
						<tr><th>HPP (Harga beli rata-rata)</th><td><strong><?= format_harga_beli_tampilan($preview['hpp']); ?></strong></td></tr>
					</table>
					<?php endif; ?>
				</div>
			</div>

			<?php if ($levelLogin === 'super admin') : ?>
			<div class="card card-warning">
				<div class="card-header">
					<h3 class="card-title">Perbaiki semua barang</h3>
				</div>
				<div class="card-body">
					<p>Recalculate HPP untuk <strong>semua kode barang aktif</strong>. Proses bisa memakan waktu beberapa menit.</p>
					<form method="post">
						<button type="submit" name="perbaiki_semua" class="btn btn-warning" onclick="return confirm('Perbaiki HPP SEMUA barang?');">
							Perbaiki Semua Barang
						</button>
					</form>
				</div>
			</div>

			<div class="card card-info">
				<div class="card-header">
					<h3 class="card-title">Ganti satuan (PCS ↔ RTG, dll.)</h3>
				</div>
				<div class="card-body">
					<p>Setelah ubah satuan utama di edit barang, konversi HPP & harga beli dengan faktor isi agar tidak salah skala.</p>
					<a href="perbaiki-hpp-ganti-satuan.php" class="btn btn-info">Perbaiki HPP Ganti Satuan</a>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</section>
</div>

<?php include '_footer.php'; ?>
