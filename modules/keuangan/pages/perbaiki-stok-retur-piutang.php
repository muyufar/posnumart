<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
	echo "<script>document.location.href = 'bo';</script>";
	exit;
}

$cabangFilter = ($sessionCabang >= 1) ? (int) $sessionCabang : null;
$pesan = '';
$tipePesan = 'info';

if (isset($_POST['perbaiki_stok']) && $levelLogin === 'super admin') {
	$hasil = penjualan_perbaiki_stok_retur_piutang($conn, $cabangFilter);
	if ($hasil['baris'] > 0) {
		$pesan = 'Berhasil memperbaiki ' . (int) $hasil['baris'] . ' baris penjualan. Total stok dikembalikan: '
			. number_format((float) $hasil['total_pcs'], 0, ',', '.') . ' PCS.';
		$tipePesan = 'success';
	} else {
		$pesan = 'Tidak ada data retur piutang yang perlu diperbaiki.';
		$tipePesan = 'warning';
	}
}

$rows = penjualan_piutang_retur_belum_sync($conn, $cabangFilter);
$totalPcsPreview = 0;
foreach ($rows as $r) {
	$totalPcsPreview += (float) ($r['pcs_belum_kembali'] ?? 0);
}
?>

<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1>Perbaiki Stok Retur Piutang</h1>
				</div>
				<div class="col-sm-6">
					<ol class="breadcrumb float-sm-right">
						<li class="breadcrumb-item"><a href="bo">Home</a></li>
						<li class="breadcrumb-item"><a href="piutang">Piutang</a></li>
						<li class="breadcrumb-item active">Perbaiki Stok Retur</li>
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

			<div class="card card-warning">
				<div class="card-header">
					<h3 class="card-title">Data retur piutang yang stoknya belum kembali</h3>
				</div>
				<div class="card-body">
					<p class="text-muted">
						Menampilkan baris penjualan <strong>piutang</strong> yang QTY-nya sudah dikurangi (retur)
						tetapi stok belum pernah dikembalikan karena bug di halaman edit piutang.
						Perbaikan menghitung selisih <code>(barang_qty_lama − barang_qty) × konversi</code> dalam <strong>PCS</strong>.
					</p>
					<?php if ($cabangFilter !== null) : ?>
					<p><strong>Cabang filter:</strong> <?= (int) $cabangFilter; ?> (sesuai login Anda)</p>
					<?php else : ?>
					<p><strong>Cabang filter:</strong> semua cabang</p>
					<?php endif; ?>

					<div class="table-auto">
						<table class="table table-bordered table-striped">
							<thead>
								<tr>
									<th>No</th>
									<th>Invoice</th>
									<th>Tanggal</th>
									<th>Kode</th>
									<th>Produk</th>
									<th>Qty awal</th>
									<th>Qty sekarang</th>
									<th>Konversi</th>
									<th>PCS belum kembali</th>
								</tr>
							</thead>
							<tbody>
							<?php if ($rows === []) : ?>
								<tr>
									<td colspan="9" class="text-center text-muted">Tidak ada data yang perlu diperbaiki.</td>
								</tr>
							<?php else : ?>
								<?php $no = 1; foreach ($rows as $row) : ?>
								<tr>
									<td><?= $no++; ?></td>
									<td><?= htmlspecialchars((string) $row['penjualan_invoice'], ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?= htmlspecialchars((string) $row['penjualan_date'], ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?= htmlspecialchars((string) $row['barang_kode'], ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?= htmlspecialchars((string) $row['barang_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?= htmlspecialchars((string) $row['barang_qty_lama'], ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?= htmlspecialchars((string) penjualan_row_qty($row), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?= htmlspecialchars((string) penjualan_row_konversi($row), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><strong><?= number_format((float) $row['pcs_belum_kembali'], 0, ',', '.'); ?></strong></td>
								</tr>
								<?php endforeach; ?>
							<?php endif; ?>
							</tbody>
							<?php if ($rows !== []) : ?>
							<tfoot>
								<tr>
									<th colspan="8" class="text-right">Total PCS belum kembali</th>
									<th><?= number_format($totalPcsPreview, 0, ',', '.'); ?></th>
								</tr>
							</tfoot>
							<?php endif; ?>
						</table>
					</div>

					<?php if ($rows !== [] && $levelLogin === 'super admin') : ?>
					<form method="post" onsubmit="return confirm('Yakin jalankan perbaikan stok untuk <?= count($rows); ?> baris di atas? Pastikan sudah dicek preview-nya.');">
						<button type="submit" name="perbaiki_stok" class="btn btn-warning">
							<i class="fa fa-wrench"></i> Jalankan Perbaikan Stok (<?= count($rows); ?> baris)
						</button>
					</form>
					<?php elseif ($rows !== []) : ?>
					<p class="text-danger mb-0"><strong>Hanya Super Admin</strong> yang dapat menjalankan perbaikan otomatis.</p>
					<?php endif; ?>
				</div>
			</div>

			<div class="card card-outline card-info">
				<div class="card-header"><h3 class="card-title">Catatan</h3></div>
				<div class="card-body">
					<ul>
						<li>Jalankan <strong>sekali</strong> setelah deploy perbaikan kode. Setelah diperbaiki, baris tidak muncul lagi di daftar ini.</li>
						<li>Retur piutang ke depan sudah otomatis mengembalikan stok saat edit QTY / hapus invoice.</li>
						<li>Jika ada barang yang stoknya sudah Anda sesuaikan manual, hapus baris tersebut dari daftar dengan menyesuaikan <code>barang_qty_lama</code> di database sebelum menjalankan perbaikan.</li>
					</ul>
				</div>
			</div>
		</div>
	</section>
</div>

<?php include '_footer.php'; ?>
