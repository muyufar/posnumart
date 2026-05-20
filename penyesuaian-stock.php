<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin === 'kurir' || $levelLogin === 'kasir') {
	echo "<script>document.location.href = 'bo';</script>";
	exit;
}

if ($sessionCabang >= 1) {
	$pCabang = (int) $sessionCabang;
} else {
	$pCabang = isset($_GET['cabang']) ? (int) $_GET['cabang'] : 0;
}

$sampai = isset($_GET['sampai']) ? $_GET['sampai'] : date('Y-m-d');
$dari = isset($_GET['dari']) ? $_GET['dari'] : date('Y-m-d', strtotime('-90 days', strtotime($sampai . ' 23:59:59')));

$dariEsc = mysqli_real_escape_string($conn, $dari);
$sampaiEsc = mysqli_real_escape_string($conn, $sampai);

$bid = isset($_GET['bid']) ? (int) $_GET['bid'] : 0;
$kodeCari = isset($_GET['kode']) ? trim((string) $_GET['kode']) : '';

$barang = null;
if ($kodeCari !== '') {
	$kEsc = mysqli_real_escape_string($conn, $kodeCari);
	$rowsB = query("SELECT * FROM barang WHERE barang_kode = '$kEsc' AND barang_cabang = $pCabang LIMIT 1");
	if (!empty($rowsB)) {
		$barang = $rowsB[0];
		$bid = (int) $barang['barang_id'];
	}
} elseif ($bid > 0) {
	$rowsB = query("SELECT * FROM barang WHERE barang_id = $bid AND barang_cabang = $pCabang LIMIT 1");
	if (!empty($rowsB)) {
		$barang = $rowsB[0];
	}
}

$timeline = [];
$sumDelta = 0.0;

if ($barang !== null) {
	$bid = (int) $barang['barang_id'];
	$slugEsc = mysqli_real_escape_string($conn, (string) $barang['barang_kode_slug']);

	// Penjualan (trigger: stok -= barang_qty_keranjang)
	$qPen = mysqli_query($conn, "
		SELECT 'penjualan' AS sumber, p.penjualan_id AS rid,
			p.penjualan_date AS tgl,
			COALESCE(i.invoice_tgl, CONCAT(p.penjualan_date, ' 12:00:00')) AS waktu_label,
			(-1 * p.barang_qty_keranjang) AS delta_qty,
			CONCAT('Penjualan — Invoice ', p.penjualan_invoice) AS keterangan
		FROM penjualan p
		LEFT JOIN invoice i ON i.penjualan_invoice = p.penjualan_invoice AND i.invoice_cabang = p.penjualan_cabang
		WHERE p.barang_id = $bid AND p.penjualan_cabang = $pCabang
		  AND p.penjualan_date BETWEEN '$dariEsc' AND '$sampaiEsc'
	");
	if ($qPen) {
		while ($r = mysqli_fetch_assoc($qPen)) {
			$t = strtotime((string) $r['tgl'] . ' 00:00:00') + ((int) $r['rid'] % 86400);
			$timeline[] = [
				'sort' => $t,
				'sumber' => 'Penjualan',
				'tgl' => $r['tgl'],
				'waktu' => $r['waktu_label'],
				'delta' => (float) $r['delta_qty'],
				'ket' => $r['keterangan'],
				'rid' => $r['rid'],
			];
		}
	}

	// Pembelian (trigger: stok += barang_qty)
	$qPem = mysqli_query($conn, "
		SELECT 'pembelian' AS sumber, p.pembelian_id AS rid,
			p.pembelian_date AS tgl,
			CONCAT(p.pembelian_date, ' 12:00:00') AS waktu_label,
			p.barang_qty AS delta_qty,
			CONCAT('Pembelian — ', p.pembelian_invoice) AS keterangan
		FROM pembelian p
		WHERE CAST(p.barang_id AS UNSIGNED) = $bid AND p.pembelian_cabang = $pCabang
		  AND p.pembelian_date BETWEEN '$dariEsc' AND '$sampaiEsc'
	");
	if ($qPem) {
		while ($r = mysqli_fetch_assoc($qPem)) {
			$t = strtotime((string) $r['tgl'] . ' 00:00:00') + ((int) $r['rid'] % 86400);
			$timeline[] = [
				'sort' => $t,
				'sumber' => 'Pembelian',
				'tgl' => $r['tgl'],
				'waktu' => $r['waktu_label'],
				'delta' => (float) $r['delta_qty'],
				'ket' => $r['keterangan'],
				'rid' => $r['rid'],
			];
		}
	}

	// Transfer keluar — cabang ini sebagai pengirim (trigger: stok -= tpk_qty)
	$qTk = mysqli_query($conn, "
		SELECT 'tf_keluar' AS sumber, t.tpk_id AS rid,
			t.tpk_date AS tgl,
			t.tpk_date_time AS waktu_label,
			(-1 * t.tpk_qty) AS delta_qty,
			CONCAT('Transfer keluar — ref ', t.tpk_ref) AS keterangan
		FROM transfer_produk_keluar t
		WHERE t.tpk_barang_id = $bid AND t.tpk_cabang = $pCabang
		  AND t.tpk_date BETWEEN '$dariEsc' AND '$sampaiEsc'
	");
	if ($qTk) {
		while ($r = mysqli_fetch_assoc($qTk)) {
			$t = strtotime((string) $r['tgl'] . ' 00:00:00') + ((int) $r['rid'] % 86400);
			$timeline[] = [
				'sort' => $t,
				'sumber' => 'Transfer keluar',
				'tgl' => $r['tgl'],
				'waktu' => $r['waktu_label'],
				'delta' => (float) $r['delta_qty'],
				'ket' => $r['keterangan'],
				'rid' => $r['rid'],
			];
		}
	}

	// Transfer masuk — cabang ini sebagai penerima (trigger: stok += tpm_qty by slug)
	$qTm = mysqli_query($conn, "
		SELECT 'tf_masuk' AS sumber, m.tpm_id AS rid,
			m.tpm_date AS tgl,
			m.tpm_date_time AS waktu_label,
			m.tpm_qty AS delta_qty,
			CONCAT('Transfer masuk — ref ', m.tpm_ref, ' (tpm_id ', m.tpm_id, ')') AS keterangan
		FROM transfer_produk_masuk m
		WHERE m.tpm_kode_slug = '$slugEsc' AND m.tpm_penerima_cabang = $pCabang
		  AND m.tpm_date BETWEEN '$dariEsc' AND '$sampaiEsc'
	");
	if ($qTm) {
		while ($r = mysqli_fetch_assoc($qTm)) {
			$t = strtotime((string) $r['tgl'] . ' 00:00:00') + ((int) $r['rid'] % 86400);
			$timeline[] = [
				'sort' => $t,
				'sumber' => 'Transfer masuk',
				'tgl' => $r['tgl'],
				'waktu' => $r['waktu_label'],
				'delta' => (float) $r['delta_qty'],
				'ket' => $r['keterangan'],
				'rid' => $r['rid'],
			];
		}
	}

	// Stock opname (trigger: stok diset ke fisik — dampak = selisih vs stok sistem saat input)
	$qSo = mysqli_query($conn, "
		SELECT 'opname' AS sumber, h.soh_id AS rid,
			h.soh_date AS tgl,
			h.soh_datetime AS waktu_label,
			h.soh_selisih AS delta_qty,
			CONCAT('Stock opname — sesi #', h.soh_stock_opname_id, ' | fisik ', h.soh_stock_fisik, ' vs sistem ', h.soh_barang_stock_system) AS keterangan
		FROM stock_opname_hasil h
		WHERE h.soh_barang_id = $bid AND h.soh_barang_cabang = $pCabang
		  AND h.soh_date BETWEEN '$dariEsc' AND '$sampaiEsc'
	");
	if ($qSo) {
		while ($r = mysqli_fetch_assoc($qSo)) {
			$t = strtotime((string) $r['tgl'] . ' 00:00:00') + ((int) $r['rid'] % 86400);
			$timeline[] = [
				'sort' => $t,
				'sumber' => 'Stock opname',
				'tgl' => $r['tgl'],
				'waktu' => $r['waktu_label'],
				'delta' => (float) $r['delta_qty'],
				'ket' => $r['keterangan'],
				'rid' => $r['rid'],
			];
		}
	}

	foreach ($timeline as $x) {
		$sumDelta += $x['delta'];
	}

	usort($timeline, static function ($a, $b) {
		if ($a['sort'] === $b['sort']) {
			return strcmp((string) $a['sumber'], (string) $b['sumber']);
		}
		return $a['sort'] <=> $b['sort'];
	});

	$stokMaster = (float) $barang['barang_stock'];
	$stokAwalPeriode = $stokMaster - $sumDelta;
	$run = $stokAwalPeriode;
	foreach ($timeline as $k => $x) {
		$run += $x['delta'];
		$timeline[$k]['saldo_setelah'] = $run;
	}

	$timelineTampil = array_reverse($timeline);
}

$listToko = query("SELECT toko_cabang, toko_nama, toko_kota FROM toko ORDER BY toko_cabang ASC");
?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-8">
					<h1>Penyesuaian stock</h1>
					<p class="text-muted mb-0">Jejak per barang: penjualan, pembelian, transfer, stock opname (sesuai trigger / logika stok di database).</p>
				</div>
				<div class="col-sm-4">
					<ol class="breadcrumb float-sm-right">
						<li class="breadcrumb-item"><a href="bo">Home</a></li>
						<li class="breadcrumb-item active">Penyesuaian stock</li>
					</ol>
				</div>
			</div>
		</div>
	</section>

	<section class="content">
		<div class="container-fluid">
			<div class="callout callout-info">
				<p class="mb-1"><strong>Catatan:</strong> pembatalan invoice (hapus penjualan), hapus pembelian, hapus baris transfer, atau edit stok manual <strong>tidak</strong> muncul di sini karena tidak ada tabel audit terpisah.</p>
				<p class="mb-0">Kolom <em>Saldo setelah (rekonstruksi)</em> menghitung ulang stok dalam rentang tanggal yang dipilih, dimulai dari <code>stok akhir − Σ delta</code> pada periode tersebut.</p>
			</div>

			<div class="card card-primary">
				<div class="card-header">
					<h3 class="card-title">Filter</h3>
				</div>
				<div class="card-body">
					<form method="get" action="" class="form-row align-items-end">
						<?php if ($sessionCabang < 1) : ?>
							<div class="form-group col-md-3">
								<label>Cabang / toko</label>
								<select name="cabang" class="form-control" required>
									<option value="0" <?= $pCabang === 0 ? 'selected' : ''; ?>>Cabang 0 (pusat / gudang)</option>
									<?php foreach ($listToko as $tk) : ?>
										<?php if ((int) $tk['toko_cabang'] === 0) {
											continue;
										} ?>
										<option value="<?= (int) $tk['toko_cabang']; ?>" <?= $pCabang === (int) $tk['toko_cabang'] ? 'selected' : ''; ?>>
											<?= htmlspecialchars($tk['toko_nama'] . ' — ' . $tk['toko_kota'] . ' (' . $tk['toko_cabang'] . ')'); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
						<?php else : ?>
							<input type="hidden" name="cabang" value="<?= (int) $pCabang; ?>">
						<?php endif; ?>
						<div class="form-group col-md-2">
							<label>Dari</label>
							<input type="date" name="dari" class="form-control" value="<?= htmlspecialchars($dari); ?>" required>
						</div>
						<div class="form-group col-md-2">
							<label>Sampai</label>
							<input type="date" name="sampai" class="form-control" value="<?= htmlspecialchars($sampai); ?>" required>
						</div>
						<div class="form-group col-md-2">
							<label>ID barang</label>
							<input type="number" name="bid" class="form-control" value="<?= $bid > 0 ? (int) $bid : ''; ?>" placeholder="barang_id" min="1">
						</div>
						<div class="form-group col-md-2">
							<label>atau kode barang</label>
							<input type="text" name="kode" class="form-control" value="<?= htmlspecialchars($kodeCari); ?>" placeholder="Scan / ketik kode">
						</div>
						<div class="form-group col-md-1">
							<button type="submit" class="btn btn-primary">Tampilkan</button>
						</div>
					</form>
				</div>
			</div>

			<?php if ($barang === null) : ?>
				<div class="alert alert-secondary">Pilih barang dengan mengisi <strong>ID barang</strong> atau <strong>kode barang</strong>, lalu klik Tampilkan.</div>
			<?php else : ?>
				<div class="row">
					<div class="col-md-4">
						<div class="card">
							<div class="card-header"><h3 class="card-title">Ringkasan barang</h3></div>
							<div class="card-body">
								<p><strong><?= htmlspecialchars((string) $barang['barang_nama']); ?></strong></p>
								<p class="mb-1">Kode: <code><?= htmlspecialchars((string) $barang['barang_kode']); ?></code></p>
								<p class="mb-1">SKU slug: <code><?= htmlspecialchars((string) $barang['barang_kode_slug']); ?></code></p>
								<p class="mb-1">Cabang: <?= (int) $pCabang; ?></p>
								<hr>
								<p class="mb-1"><strong>Stok master (barang)</strong>: <span class="badge badge-info" style="font-size:1rem;"><?= htmlspecialchars((string) $barang['barang_stock']); ?></span></p>
								<p class="mb-1 text-muted">Stok awal (rekonstruksi awal periode): <strong><?= $stokAwalPeriode; ?></strong></p>
								<p class="mb-0 text-muted">Σ delta dalam periode: <strong><?= $sumDelta >= 0 ? '+' : ''; ?><?= $sumDelta; ?></strong></p>
							</div>
						</div>
					</div>
					<div class="col-md-8">
						<div class="card">
							<div class="card-header"><h3 class="card-title">Resume per sumber (periode)</h3></div>
							<div class="card-body p-0">
								<table class="table table-sm mb-0">
									<?php
									$agg = ['Penjualan' => 0.0, 'Pembelian' => 0.0, 'Transfer keluar' => 0.0, 'Transfer masuk' => 0.0, 'Stock opname' => 0.0];
									foreach ($timeline as $x) {
										if (!isset($agg[$x['sumber']])) {
											$agg[$x['sumber']] = 0.0;
										}
										$agg[$x['sumber']] += $x['delta'];
									}
									?>
									<tbody>
										<?php foreach ($agg as $label => $v) : ?>
											<tr>
												<td><?= htmlspecialchars($label); ?></td>
												<td class="text-right <?= $v < 0 ? 'text-danger' : ($v > 0 ? 'text-success' : ''); ?>"><?= $v >= 0 ? '+' : ''; ?><?= $v; ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>

				<div class="card">
					<div class="card-header">
						<h3 class="card-title">Riwayat pergerakan stok (global per cabang barang, urut terbaru)</h3>
					</div>
					<div class="card-body">
						<?php if (empty($timelineTampil)) : ?>
							<p class="text-muted">Tidak ada transaksi pada rentang tanggal ini.</p>
						<?php else : ?>
							<div class="table-responsive">
								<table class="table table-bordered table-striped table-sm" id="tbl-penyesuaian-stock">
									<thead>
										<tr>
											<th>Tanggal</th>
											<th>Waktu / catat</th>
											<th>Sumber</th>
											<th class="text-right">Δ stok</th>
											<th class="text-right">Saldo setelah (rekonstruksi)</th>
											<th>Keterangan</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($timelineTampil as $row) : ?>
											<tr>
												<td><?= htmlspecialchars((string) $row['tgl']); ?></td>
												<td><small><?= htmlspecialchars((string) $row['waktu']); ?></small></td>
												<td><?= htmlspecialchars((string) $row['sumber']); ?></td>
												<td class="text-right <?= $row['delta'] < 0 ? 'text-danger' : ($row['delta'] > 0 ? 'text-success' : ''); ?>">
													<?= $row['delta'] >= 0 ? '+' : ''; ?><?= $row['delta']; ?>
												</td>
												<td class="text-right"><strong><?= $row['saldo_setelah']; ?></strong></td>
												<td><small><?= htmlspecialchars((string) $row['ket']); ?></small></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</section>
</div>

<?php include '_footer.php'; ?>
<script>
	$(function () {
		if ($('#tbl-penyesuaian-stock').length) {
			$('#tbl-penyesuaian-stock').DataTable({
				order: [[0, 'desc']],
				pageLength: 25
			});
		}
	});
</script>
</body>
</html>
