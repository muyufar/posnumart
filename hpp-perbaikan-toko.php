<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

require_once __DIR__ . '/aksi/hpp-perbaikan-lib.php';

if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
	echo "<script>document.location.href = 'bo';</script>";
	exit;
}

hpp_perbaikan_ensure_tables($conn);

$flash = $_SESSION['hpp_perbaikan_flash'] ?? null;
unset($_SESSION['hpp_perbaikan_flash']);

$list = hpp_perbaikan_list_request($conn, (int) $sessionCabang, 'semua', 100);
$isGudang = hpp_perbaikan_can_gudang((int) $sessionCabang, (string) $levelLogin);

$kodePrefill = trim((string) ($_GET['kode'] ?? ''));
$namaPrefill = trim((string) ($_GET['nama'] ?? ''));
$barangIdPrefill = (int) ($_GET['barang_id'] ?? 0);
$tAwal = (string) ($_GET['tanggal_awal'] ?? date('Y-m-01'));
$tAkhir = (string) ($_GET['tanggal_akhir'] ?? date('Y-m-d'));

function hpptRupiah($n)
{
	return 'Rp ' . number_format((float) $n, 0, ',', '.');
}
?>

<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1>Minta Perbaikan HPP</h1>
				</div>
				<div class="col-sm-6">
					<ol class="breadcrumb float-sm-right">
						<li class="breadcrumb-item"><a href="bo">Home</a></li>
						<li class="breadcrumb-item active">Permintaan HPP</li>
					</ol>
				</div>
			</div>
		</div>
	</section>

	<section class="content">
		<div class="container-fluid">
			<?php if (is_array($flash)) : ?>
				<div class="alert alert-<?= htmlspecialchars((string) ($flash['tipe'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?>">
					<?= htmlspecialchars((string) ($flash['pesan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
				</div>
			<?php endif; ?>

			<div class="alert alert-light border">
				<strong>Toko tidak mengedit pembelian / satuan.</strong>
				Kirim permintaan ke gudang dengan barcode + periode. Gudang yang memperbaiki master HPP dan (bila perlu) koreksi histori penjualan.
				<?php if ($isGudang) : ?>
					<a href="hpp-perbaikan-gudang" class="btn btn-sm btn-warning ml-2">Buka panel gudang</a>
				<?php endif; ?>
			</div>

			<div class="card card-outline card-warning">
				<div class="card-header">
					<h3 class="card-title">Form permintaan</h3>
				</div>
				<form method="post" action="aksi/hpp-perbaikan-request.php">
					<input type="hidden" name="redirect" value="hpp-perbaikan-toko">
					<input type="hidden" name="barang_id" value="<?= $barangIdPrefill; ?>">
					<div class="card-body">
						<div class="row">
							<div class="col-md-3">
								<div class="form-group">
									<label>Kode / barcode</label>
									<input type="text" name="barang_kode" class="form-control" required
										value="<?= htmlspecialchars($kodePrefill, ENT_QUOTES, 'UTF-8'); ?>">
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-group">
									<label>Nama barang (opsional)</label>
									<input type="text" name="barang_nama" class="form-control"
										value="<?= htmlspecialchars($namaPrefill, ENT_QUOTES, 'UTF-8'); ?>">
								</div>
							</div>
							<div class="col-md-2">
								<div class="form-group">
									<label>Tanggal awal</label>
									<input type="date" name="tanggal_awal" class="form-control" required
										value="<?= htmlspecialchars($tAwal, ENT_QUOTES, 'UTF-8'); ?>">
								</div>
							</div>
							<div class="col-md-2">
								<div class="form-group">
									<label>Tanggal akhir</label>
									<input type="date" name="tanggal_akhir" class="form-control" required
										value="<?= htmlspecialchars($tAkhir, ENT_QUOTES, 'UTF-8'); ?>">
								</div>
							</div>
						</div>
						<div class="form-group">
							<label>Catatan untuk gudang</label>
							<textarea name="catatan" class="form-control" rows="3"
								placeholder="Contoh: HPP jauh di atas harga jual, kemungkinan salah satuan. Mohon dicek pembelian terakhir."></textarea>
						</div>
						<small class="text-muted">Cabang pemohon otomatis: <?= htmlspecialchars(hpp_perbaikan_nama_cabang($conn, (int) $sessionCabang), ENT_QUOTES, 'UTF-8'); ?></small>
					</div>
					<div class="card-footer">
						<button type="submit" class="btn btn-warning">
							<i class="fas fa-paper-plane"></i> Kirim ke gudang
						</button>
					</div>
				</form>
			</div>

			<div class="card">
				<div class="card-header">
					<h3 class="card-title">Status permintaan cabang ini</h3>
				</div>
				<div class="card-body table-responsive p-0">
					<table class="table table-striped mb-0">
						<thead>
							<tr>
								<th>ID</th>
								<th>Status</th>
								<th>Barang</th>
								<th>Periode</th>
								<th class="text-right">Laba ringkas</th>
								<th>Catatan gudang</th>
								<th>Dibuat</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($list)) : ?>
								<tr><td colspan="7" class="text-center text-muted py-4">Belum ada permintaan.</td></tr>
							<?php else : ?>
								<?php foreach ($list as $r) :
									$st = (string) ($r['status'] ?? '');
								?>
									<tr>
										<td>#<?= (int) $r['id']; ?></td>
										<td>
											<span class="badge <?= hpp_perbaikan_status_badge($st); ?>">
												<?= htmlspecialchars(hpp_perbaikan_status_label($st), ENT_QUOTES, 'UTF-8'); ?>
											</span>
										</td>
										<td>
											<code><?= htmlspecialchars((string) $r['barang_kode'], ENT_QUOTES, 'UTF-8'); ?></code><br>
											<?= htmlspecialchars((string) ($r['barang_nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
										</td>
										<td>
											<?= htmlspecialchars(date('d/m/Y', strtotime((string) $r['tanggal_awal'])), ENT_QUOTES, 'UTF-8'); ?>
											–
											<?= htmlspecialchars(date('d/m/Y', strtotime((string) $r['tanggal_akhir'])), ENT_QUOTES, 'UTF-8'); ?>
										</td>
										<td class="text-right <?= ((float) $r['ringkas_laba'] < 0) ? 'text-danger' : ''; ?>">
											<?= hpptRupiah($r['ringkas_laba']); ?>
										</td>
										<td style="max-width:240px;">
											<small><?= nl2br(htmlspecialchars((string) ($r['catatan_gudang'] ?? '-'), ENT_QUOTES, 'UTF-8')); ?></small>
										</td>
										<td>
											<small><?= htmlspecialchars((string) $r['created_at'], ENT_QUOTES, 'UTF-8'); ?></small>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</section>
</div>

<?php include '_footer.php'; ?>
