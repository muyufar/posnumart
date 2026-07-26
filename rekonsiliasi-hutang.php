<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
require_once 'aksi/hutang-rekonsiliasi-lib.php';

if ($levelLogin !== 'admin' && $levelLogin !== 'super admin') {
	echo "<script>document.location.href = 'bo';</script>";
	exit;
}

$listCabang = hutang_rekon_list_cabang($conn);
$selectedCabang = isset($_GET['cabang']) ? (int) $_GET['cabang'] : (int) $sessionCabang;
if ($levelLogin !== 'super admin') {
	$selectedCabang = (int) $sessionCabang;
}

$ringkasan = hutang_rekon_ringkasan($conn, $selectedCabang);
$invoiceSelisih = hutang_rekon_invoice_selisih($conn, $selectedCabang, 80);
$invoiceLunasSisa = hutang_rekon_invoice_lunas_sisa_akun($conn, $selectedCabang, 30);
$mutasiRows = $ringkasan['mutasi_operasional']['rows'] ?? [];
$namaCabang = hutang_rekon_nama_cabang($listCabang, $selectedCabang);

function hutang_rekon_badge_class($value)
{
	$v = (float) $value;
	if (abs($v) < 0.01) {
		return 'badge-success';
	}
	if (abs($v) < 100000) {
		return 'badge-warning';
	}
	return 'badge-danger';
}
?>

<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-8">
					<h1>Rekonsiliasi Hutang Dagang</h1>
					<p class="text-muted mb-0">Bandingkan saldo akun <?= htmlspecialchars($ringkasan['kode_akun'], ENT_QUOTES, 'UTF-8'); ?> dengan data pembelian &amp; cicilan hutang.</p>
				</div>
				<div class="col-sm-4">
					<ol class="breadcrumb float-sm-right">
						<li class="breadcrumb-item"><a href="bo">Home</a></li>
						<li class="breadcrumb-item"><a href="hutang">Hutang</a></li>
						<li class="breadcrumb-item active">Rekonsiliasi</li>
					</ol>
				</div>
			</div>
		</div>
	</section>

	<section class="content">
		<div class="container-fluid">
			<div class="card card-outline card-primary">
				<div class="card-header">
					<h3 class="card-title">Filter Cabang</h3>
				</div>
				<div class="card-body pb-0">
					<form method="get" class="form-inline flex-wrap">
						<label class="mr-2 mb-2">Cabang</label>
						<select name="cabang" class="form-control mr-3 mb-2" <?= $levelLogin !== 'super admin' ? 'disabled' : '' ?>>
							<?php foreach ($listCabang as $c) : ?>
							<option value="<?= (int) $c['toko_cabang']; ?>" <?= (int) $c['toko_cabang'] === $selectedCabang ? 'selected' : '' ?>>
								<?= htmlspecialchars($c['toko_nama'] . ($c['toko_kota'] ? ' — ' . $c['toko_kota'] : ''), ENT_QUOTES, 'UTF-8'); ?>
							</option>
							<?php endforeach; ?>
						</select>
						<?php if ($levelLogin !== 'super admin') : ?>
						<input type="hidden" name="cabang" value="<?= (int) $selectedCabang; ?>">
						<?php endif; ?>
						<button type="submit" class="btn btn-primary mb-2"><i class="fa fa-sync"></i> Muat Ulang</button>
					</form>
					<p class="text-muted small mb-0">Cabang aktif: <strong><?= htmlspecialchars($namaCabang, ENT_QUOTES, 'UTF-8'); ?></strong></p>
				</div>
			</div>

			<?php if (!empty($ringkasan['cicilan_tabel_tidak_wajar'])) : ?>
			<div class="alert alert-danger">
				<strong>Data tabel <code>hutang</code> tidak wajar.</strong>
				Total cicilan di tabel: <?= hutang_rekon_fmt_rupiah($ringkasan['total_cicilan']); ?>
				(<?= (int) $ringkasan['jumlah_baris_cicilan']; ?> baris) — jauh lebih besar dari pembelian hutang.
				Cicilan yang masuk akal dari invoice: <?= hutang_rekon_fmt_rupiah($ringkasan['cicilan_dari_invoice']); ?>.
				Perhitungan di bawah memakai sisa invoice, bukan SUM tabel <code>hutang</code>.
			</div>
			<?php endif; ?>

			<div class="row">
				<div class="col-lg-4 col-md-6">
					<div class="small-box bg-info">
						<div class="inner">
							<h3 style="font-size:1.35rem;"><?= hutang_rekon_fmt_rupiah_dec($ringkasan['saldo_akun']); ?></h3>
							<p>Saldo Akun <?= htmlspecialchars($ringkasan['kode_akun'], ENT_QUOTES, 'UTF-8'); ?> (COA)</p>
						</div>
						<div class="icon"><i class="fas fa-book"></i></div>
					</div>
				</div>
				<div class="col-lg-4 col-md-6">
					<div class="small-box bg-success">
						<div class="inner">
							<h3 style="font-size:1.35rem;"><?= hutang_rekon_fmt_rupiah($ringkasan['hutang_akuntansi_invoice']); ?></h3>
							<p>Sisa Hutang dari Invoice (<?= (int) $ringkasan['jumlah_invoice_hutang']; ?> invoice hutang)</p>
						</div>
						<div class="icon"><i class="fas fa-calculator"></i></div>
					</div>
				</div>
				<div class="col-lg-4 col-md-6">
					<div class="small-box bg-warning">
						<div class="inner">
							<h3 style="font-size:1.35rem;"><?= hutang_rekon_fmt_rupiah($ringkasan['hutang_belum_lunas']); ?></h3>
							<p>Hutang Belum Lunas (<?= (int) $ringkasan['jumlah_invoice_belum_lunas']; ?> invoice)</p>
						</div>
						<div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-header">
					<h3 class="card-title">Ringkasan Perbandingan</h3>
				</div>
				<div class="card-body table-responsive p-0">
					<table class="table table-bordered table-sm mb-0">
						<tbody>
							<tr>
								<th style="width:45%;">Total pembelian hutang (semua waktu)</th>
								<td><?= hutang_rekon_fmt_rupiah($ringkasan['total_pembelian_hutang']); ?></td>
							</tr>
							<tr>
								<th>Total cicilan / pelunasan (tabel hutang)</th>
								<td>
									<?= hutang_rekon_fmt_rupiah($ringkasan['total_cicilan']); ?>
									<span class="badge badge-secondary ml-1"><?= (int) $ringkasan['jumlah_baris_cicilan']; ?> baris</span>
									<?php if (!empty($ringkasan['cicilan_tabel_tidak_wajar'])) : ?>
									<span class="badge badge-danger ml-1">tidak wajar</span>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th>Cicilan dari invoice (pembelian − sisa invoice)</th>
								<td><?= hutang_rekon_fmt_rupiah($ringkasan['cicilan_dari_invoice']); ?></td>
							</tr>
							<tr class="table-secondary">
								<th>= Sisa hutang dari invoice (referensi)</th>
								<td><strong><?= hutang_rekon_fmt_rupiah($ringkasan['hutang_akuntansi_invoice']); ?></strong></td>
							</tr>
							<?php if (empty($ringkasan['cicilan_tabel_tidak_wajar'])) : ?>
							<tr>
								<th>= Perhitungan akuntansi (pembelian − cicilan tabel)</th>
								<td><?= hutang_rekon_fmt_rupiah($ringkasan['hutang_akuntansi']); ?></td>
							</tr>
							<?php endif; ?>
							<tr>
								<th>Hutang belum lunas (invoice_bayar &lt; invoice_total)</th>
								<td><?= hutang_rekon_fmt_rupiah($ringkasan['hutang_belum_lunas']); ?></td>
							</tr>
							<tr>
								<th>Mutasi Data Operasional ke 2-1101 (net)</th>
								<td>
									<?= hutang_rekon_fmt_rupiah($ringkasan['mutasi_operasional']['total_net']); ?>
									<span class="badge badge-secondary ml-1"><?= (int) $ringkasan['mutasi_operasional']['jumlah']; ?> baris</span>
								</td>
							</tr>
							<tr>
								<th>Selisih: Saldo akun − Sisa hutang (invoice)</th>
								<td>
									<span class="badge <?= hutang_rekon_badge_class($ringkasan['selisih_akun_vs_invoice']); ?> p-2">
										<?= hutang_rekon_fmt_rupiah_dec($ringkasan['selisih_akun_vs_invoice']); ?>
									</span>
									<span class="text-muted small ml-1">metrik utama</span>
								</td>
							</tr>
							<tr>
								<th>Selisih: Saldo akun − Hutang belum lunas</th>
								<td>
									<span class="badge <?= hutang_rekon_badge_class($ringkasan['selisih_akun_vs_belum_lunas']); ?> p-2">
										<?= hutang_rekon_fmt_rupiah_dec($ringkasan['selisih_akun_vs_belum_lunas']); ?>
									</span>
								</td>
							</tr>
							<?php if (empty($ringkasan['cicilan_tabel_tidak_wajar'])) : ?>
							<tr>
								<th>Selisih: Saldo akun − Perhitungan akuntansi (tabel)</th>
								<td>
									<span class="badge <?= hutang_rekon_badge_class($ringkasan['selisih_akun_vs_akuntansi']); ?> p-2">
										<?= hutang_rekon_fmt_rupiah_dec($ringkasan['selisih_akun_vs_akuntansi']); ?>
									</span>
								</td>
							</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
				<div class="card-footer text-muted small">
					Posting otomatis: <code>aksi/akun-link-lib.php</code> dipanggil saat simpan pembelian &amp; cicilan hutang.
					Selisih kecil antara saldo akun dan hutang belum lunas biasanya dari migrasi akun 2-1100,
					posting ganda, atau mutasi operasional manual (lihat tabel di bawah).
					Setelah backup, jalankan <a href="recalculate-laba-kategori.php">Hitung Ulang Saldo</a> — cicilan hutang &amp; piutang sudah ikut dihitung.
				</div>
			</div>

			<?php if (!empty($invoiceLunasSisa)) : ?>
			<div class="card card-warning">
				<div class="card-header">
					<h3 class="card-title">Invoice Sudah Lunas tapi Sisa Akuntansi &gt; 0</h3>
				</div>
				<div class="card-body table-responsive p-0">
					<table class="table table-sm table-hover mb-0">
						<thead>
							<tr>
								<th>Invoice</th>
								<th>Tanggal</th>
								<th>Supplier</th>
								<th class="text-right">Total</th>
								<th class="text-right">Cicilan tercatat</th>
								<th class="text-right">Sisa akuntansi</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($invoiceLunasSisa as $row) : ?>
							<tr>
								<td>
									<a href="pembelian-zoom?no=<?= base64_encode((string) $row['invoice_pembelian_id']); ?>" target="_blank">
										<?= htmlspecialchars($row['pembelian_invoice'], ENT_QUOTES, 'UTF-8'); ?>
									</a>
								</td>
								<td><?= htmlspecialchars($row['invoice_date'], ENT_QUOTES, 'UTF-8'); ?></td>
								<td><?= htmlspecialchars($row['supplier_company'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
								<td class="text-right"><?= hutang_rekon_fmt_rupiah($row['invoice_total']); ?></td>
								<td class="text-right"><?= hutang_rekon_fmt_rupiah($row['cicilan_total']); ?></td>
								<td class="text-right text-danger"><strong><?= hutang_rekon_fmt_rupiah($row['sisa_akuntansi']); ?></strong></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php endif; ?>

			<?php if (!empty($invoiceSelisih)) : ?>
			<div class="card card-danger">
				<div class="card-header">
					<h3 class="card-title">Invoice dengan Selisih Posting (max 80)</h3>
				</div>
				<div class="card-body table-responsive p-0">
					<table class="table table-sm table-hover mb-0">
						<thead>
							<tr>
								<th>Invoice</th>
								<th>Status</th>
								<th>Tanggal</th>
								<th>Supplier</th>
								<th class="text-right">Sisa invoice</th>
								<th class="text-right">Sisa akuntansi</th>
								<th class="text-right">Selisih</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($invoiceSelisih as $row) : ?>
							<tr>
								<td>
									<a href="pembelian-zoom?no=<?= base64_encode((string) $row['invoice_pembelian_id']); ?>" target="_blank">
										<?= htmlspecialchars($row['pembelian_invoice'], ENT_QUOTES, 'UTF-8'); ?>
									</a>
								</td>
								<td>
									<span class="badge badge-<?= $row['status'] === 'lunas' ? 'success' : 'warning'; ?>">
										<?= $row['status'] === 'lunas' ? 'Lunas' : 'Belum lunas'; ?>
									</span>
								</td>
								<td><?= htmlspecialchars($row['invoice_date'], ENT_QUOTES, 'UTF-8'); ?></td>
								<td><?= htmlspecialchars($row['supplier_company'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
								<td class="text-right"><?= hutang_rekon_fmt_rupiah($row['sisa_invoice']); ?></td>
								<td class="text-right"><?= hutang_rekon_fmt_rupiah($row['sisa_akuntansi']); ?></td>
								<td class="text-right"><strong><?= hutang_rekon_fmt_rupiah($row['selisih']); ?></strong></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php endif; ?>

			<div class="card card-secondary">
				<div class="card-header">
					<h3 class="card-title">Mutasi Data Operasional ke <?= htmlspecialchars($ringkasan['kode_akun'], ENT_QUOTES, 'UTF-8'); ?></h3>
				</div>
				<div class="card-body table-responsive p-0">
					<?php if (empty($mutasiRows)) : ?>
					<p class="p-3 mb-0 text-muted">Tidak ada transaksi data operasional yang menyentuh akun hutang dagang.</p>
					<?php else : ?>
					<table class="table table-sm table-striped mb-0">
						<thead>
							<tr>
								<th>Tanggal</th>
								<th>Jenis</th>
								<th>Keterangan</th>
								<th>Debit / Kredit</th>
								<th class="text-right">Nominal</th>
								<th class="text-right">Net hutang</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($mutasiRows as $row) : ?>
							<tr>
								<td><?= htmlspecialchars($row['date'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
								<td><?= htmlspecialchars($row['jenis_transaksi'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
								<td><?= htmlspecialchars($row['keterangan'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
								<td class="small">
									<?= htmlspecialchars(($row['kode_debit'] ?? '-') . ' / ' . ($row['kode_kredit'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
									<br><span class="text-muted"><?= htmlspecialchars($row['arah'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
								</td>
								<td class="text-right"><?= hutang_rekon_fmt_rupiah($row['total'] ?? 0); ?></td>
								<td class="text-right"><?= hutang_rekon_fmt_rupiah($row['net_hutang'] ?? 0); ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php endif; ?>
				</div>
			</div>

			<div class="card card-outline card-info">
				<div class="card-header">
					<h3 class="card-title">Tindakan</h3>
				</div>
				<div class="card-body">
					<a href="hutang?cabang=<?= (int) $selectedCabang; ?>" class="btn btn-outline-primary mr-2 mb-2">
						<i class="fa fa-list"></i> Daftar Hutang Belum Lunas
					</a>
					<a href="recalculate-laba-kategori.php" class="btn btn-outline-warning mr-2 mb-2">
						<i class="fa fa-calculator"></i> Hitung Ulang Saldo COA
					</a>
					<?php if ($levelLogin === 'super admin') : ?>
					<a href="perbaiki-akun-link.php" class="btn btn-outline-secondary mb-2">
						<i class="fa fa-link"></i> Perbaiki Link Akun
					</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>
</div>

<?php include '_footer.php'; ?>
