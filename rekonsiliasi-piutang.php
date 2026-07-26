<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
require_once 'aksi/piutang-rekonsiliasi-lib.php';

if ($levelLogin !== 'admin' && $levelLogin !== 'super admin') {
	echo "<script>document.location.href = 'bo';</script>";
	exit;
}

$listCabang = hutang_rekon_list_cabang($conn);
$cabangParam = isset($_GET['cabang']) ? $_GET['cabang'] : (string) (int) $sessionCabang;
if ($cabangParam === 'all') {
	$cabangFilter = null;
	$selectedCabangLabel = 'Semua Cabang';
} else {
	$cabangFilter = (int) $cabangParam;
	if ($levelLogin !== 'super admin') {
		$cabangFilter = (int) $sessionCabang;
	}
	$selectedCabangLabel = hutang_rekon_nama_cabang($listCabang, $cabangFilter);
}

$ringkasan = piutang_rekon_ringkasan($conn, $cabangFilter);
$mutasiRows = $ringkasan['mutasi_operasional']['rows'] ?? [];

function piutang_rekon_badge_class($value)
{
	return hutang_rekon_badge_class($value);
}
?>

<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-8">
					<h1>Rekonsiliasi Piutang Dagang</h1>
					<p class="text-muted mb-0">
						Akun <?= htmlspecialchars($ringkasan['kode_akun'], ENT_QUOTES, 'UTF-8'); ?> tercatat di <strong>Pusat (cabang 0)</strong>.
						Metrik utama: saldo akun vs piutang belum lunas <strong>semua cabang</strong>.
					</p>
				</div>
				<div class="col-sm-4">
					<ol class="breadcrumb float-sm-right">
						<li class="breadcrumb-item"><a href="bo">Home</a></li>
						<li class="breadcrumb-item"><a href="piutang">Piutang</a></li>
						<li class="breadcrumb-item active">Rekonsiliasi</li>
					</ol>
				</div>
			</div>
		</div>
	</section>

	<section class="content">
		<div class="container-fluid">
			<?php if (abs((float) $ringkasan['saldo_akun']) < 0.01 && (float) $ringkasan['piutang_belum_lunas_semua_cabang'] > 0.01) : ?>
			<div class="alert alert-danger">
				<strong>Saldo akun Rp 0 padahal masih ada piutang belum lunas.</strong>
				Upload <code>recalculate-laba-kategori.php</code> terbaru lalu jalankan <a href="recalculate-laba-kategori.php">Hitung Ulang Saldo</a> (setelah backup).
				Recalculate akan men-set 1-1301 = total invoice belum lunas semua cabang.
			</div>
			<?php endif; ?>

			<div class="alert alert-warning">
				<strong>Penyebab umum saldo minus:</strong> cicilan cabang lain mengurangi 1-1301, sementara penjualan piutang cabang tersebut dulu tidak menambah akun.
				Perbaikan posting sudah diterapkan — jalankan <a href="recalculate-laba-kategori.php">Hitung Ulang Saldo</a> setelah backup database.
			</div>

			<?php if (!empty($ringkasan['cicilan_tabel_tidak_wajar'])) : ?>
			<div class="alert alert-danger">
				<strong>Data tabel <code>piutang</code> tidak wajar.</strong>
				Total cicilan di tabel: <?= hutang_rekon_fmt_rupiah($ringkasan['total_cicilan']); ?>
				(<?= (int) $ringkasan['jumlah_baris_cicilan']; ?> baris).
				Cicilan masuk akal dari invoice: <?= hutang_rekon_fmt_rupiah($ringkasan['cicilan_dari_invoice']); ?>.
			</div>
			<?php endif; ?>

			<div class="card card-outline card-primary">
				<div class="card-header">
					<h3 class="card-title">Filter Cabang (data invoice)</h3>
				</div>
				<div class="card-body pb-0">
					<form method="get" class="form-inline flex-wrap">
						<label class="mr-2 mb-2">Cabang</label>
						<select name="cabang" class="form-control mr-3 mb-2" <?= $levelLogin !== 'super admin' ? 'disabled' : '' ?>>
							<?php if ($levelLogin === 'super admin') : ?>
							<option value="all" <?= $cabangParam === 'all' ? 'selected' : '' ?>>Semua Cabang</option>
							<?php endif; ?>
							<?php foreach ($listCabang as $c) : ?>
							<option value="<?= (int) $c['toko_cabang']; ?>" <?= (string) (int) $c['toko_cabang'] === (string) $cabangParam ? 'selected' : '' ?>>
								<?= htmlspecialchars($c['toko_nama'] . ($c['toko_kota'] ? ' — ' . $c['toko_kota'] : ''), ENT_QUOTES, 'UTF-8'); ?>
							</option>
							<?php endforeach; ?>
						</select>
						<?php if ($levelLogin !== 'super admin') : ?>
						<input type="hidden" name="cabang" value="<?= (int) $sessionCabang; ?>">
						<?php endif; ?>
						<button type="submit" class="btn btn-primary mb-2"><i class="fa fa-sync"></i> Muat Ulang</button>
					</form>
					<p class="text-muted small mb-0">Filter invoice: <strong><?= htmlspecialchars($selectedCabangLabel, ENT_QUOTES, 'UTF-8'); ?></strong></p>
				</div>
			</div>

			<div class="row">
				<div class="col-lg-4 col-md-6">
					<div class="small-box bg-info">
						<div class="inner">
							<h3 style="font-size:1.35rem;"><?= hutang_rekon_fmt_rupiah_dec($ringkasan['saldo_akun']); ?></h3>
							<p>Saldo Akun <?= htmlspecialchars($ringkasan['kode_akun'], ENT_QUOTES, 'UTF-8'); ?> (Pusat)</p>
						</div>
						<div class="icon"><i class="fas fa-book"></i></div>
					</div>
				</div>
				<div class="col-lg-4 col-md-6">
					<div class="small-box bg-success">
						<div class="inner">
							<h3 style="font-size:1.35rem;"><?= hutang_rekon_fmt_rupiah($ringkasan['piutang_belum_lunas_semua_cabang']); ?></h3>
							<p>Piutang Belum Lunas — Semua Cabang (<?= (int) $ringkasan['jumlah_invoice_belum_lunas_semua']; ?> inv.)</p>
						</div>
						<div class="icon"><i class="fas fa-calculator"></i></div>
					</div>
				</div>
				<div class="col-lg-4 col-md-6">
					<div class="small-box bg-warning">
						<div class="inner">
							<h3 style="font-size:1.35rem;"><?= hutang_rekon_fmt_rupiah($ringkasan['piutang_belum_lunas']); ?></h3>
							<p>Piutang Belum Lunas — Filter (<?= (int) $ringkasan['jumlah_invoice_belum_lunas']; ?> inv.)</p>
						</div>
						<div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-header"><h3 class="card-title">Ringkasan Perbandingan</h3></div>
				<div class="card-body table-responsive p-0">
					<table class="table table-bordered table-sm mb-0">
						<tbody>
							<tr class="table-secondary">
								<th style="width:45%;">Selisih: Saldo akun − Piutang belum lunas (semua cabang)</th>
								<td>
									<span class="badge <?= piutang_rekon_badge_class($ringkasan['selisih_akun_vs_semua_cabang']); ?> p-2">
										<?= hutang_rekon_fmt_rupiah_dec($ringkasan['selisih_akun_vs_semua_cabang']); ?>
									</span>
									<span class="text-muted small ml-1">metrik utama</span>
								</td>
							</tr>
							<tr>
								<th>Total penjualan piutang (filter)</th>
								<td><?= hutang_rekon_fmt_rupiah($ringkasan['total_penjualan_piutang']); ?></td>
							</tr>
							<tr>
								<th>Total cicilan (tabel piutang, filter cabang)</th>
								<td>
									<?= hutang_rekon_fmt_rupiah($ringkasan['total_cicilan']); ?>
									<?php if (!empty($ringkasan['cicilan_tabel_tidak_wajar'])) : ?>
									<span class="badge badge-danger ml-1">tidak wajar</span>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th>Sisa piutang dari invoice (filter)</th>
								<td><strong><?= hutang_rekon_fmt_rupiah($ringkasan['piutang_akuntansi_invoice']); ?></strong></td>
							</tr>
							<tr>
								<th>Mutasi operasional ke 1-1301 (net)</th>
								<td>
									<?= hutang_rekon_fmt_rupiah($ringkasan['mutasi_operasional']['total_net']); ?>
									<span class="badge badge-secondary ml-1"><?= (int) $ringkasan['mutasi_operasional']['jumlah']; ?> baris</span>
								</td>
							</tr>
							<tr>
								<th>Selisih: Saldo akun − Piutang belum lunas (filter)</th>
								<td>
									<span class="badge <?= piutang_rekon_badge_class($ringkasan['selisih_akun_vs_belum_lunas']); ?> p-2">
										<?= hutang_rekon_fmt_rupiah_dec($ringkasan['selisih_akun_vs_belum_lunas']); ?>
									</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="card-footer text-muted small">
					Posting otomatis: <code>aksi/akun-link-lib.php</code> saat penjualan piutang &amp; cicilan.
					Setelah backup, jalankan <a href="recalculate-laba-kategori.php">Hitung Ulang Saldo</a>.
				</div>
			</div>

			<?php if (!empty($ringkasan['per_cabang'])) : ?>
			<div class="card card-secondary">
				<div class="card-header"><h3 class="card-title">Piutang Belum Lunas per Cabang</h3></div>
				<div class="card-body table-responsive p-0">
					<table class="table table-sm table-striped mb-0">
						<thead>
							<tr>
								<th>Cabang</th>
								<th class="text-right">Jumlah Invoice</th>
								<th class="text-right">Total</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($ringkasan['per_cabang'] as $row) : ?>
							<tr>
								<td><?= hutang_rekon_nama_cabang($listCabang, (int) ($row['cabang'] ?? 0)); ?></td>
								<td class="text-right"><?= (int) ($row['jumlah_invoice'] ?? 0); ?></td>
								<td class="text-right"><?= hutang_rekon_fmt_rupiah($row['belum_lunas'] ?? 0); ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php endif; ?>

			<div class="card card-outline card-info">
				<div class="card-header"><h3 class="card-title">Tindakan</h3></div>
				<div class="card-body">
					<a href="piutang?cabang=<?= $cabangFilter !== null ? (int) $cabangFilter : 0; ?>" class="btn btn-outline-primary mr-2 mb-2">
						<i class="fa fa-list"></i> Daftar Piutang Belum Lunas
					</a>
					<a href="recalculate-laba-kategori.php" class="btn btn-outline-warning mr-2 mb-2">
						<i class="fa fa-calculator"></i> Hitung Ulang Saldo COA
					</a>
					<a href="laba-kategori.php" class="btn btn-outline-secondary mb-2">
						<i class="fa fa-book"></i> Laba Kategori
					</a>
				</div>
			</div>
		</div>
	</section>
</div>

<?php include '_footer.php'; ?>
