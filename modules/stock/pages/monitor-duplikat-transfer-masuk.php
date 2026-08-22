<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
?>
<?php
if ($levelLogin === 'kurir' || $levelLogin === 'kasir') {
	echo "<script>document.location.href = 'bo';</script>";
	exit;
}

$cabangFilterSql = '';
if ($sessionCabang >= 1) {
	$cabangFilterSql = 'AND m.tpm_penerima_cabang = ' . (int) $sessionCabang;
}

// Agregasi hanya dari transfer_produk_masuk (tanpa JOIN di GROUP BY).
// Duplikat = barcode/SKU sama, dicatat di waktu yang sama (string tpm_date_time + tpm_date identik), untuk ref + qty + cabang yang sama, dengan ≥ 2 tpm_id.
$sqlDuplikat = "
SELECT
  x.tpm_ref,
  x.tpm_kode_slug,
  x.tpm_qty,
  x.tpm_penerima_cabang,
  x.tpm_date,
  x.tpm_date_time,
  x.duplikat_count,
  x.tpm_id_list,
  (SELECT b.barang_nama FROM barang b
   WHERE b.barang_kode_slug = x.tpm_kode_slug AND b.barang_cabang = x.tpm_penerima_cabang
   LIMIT 1) AS barang_nama,
  (SELECT t.toko_nama FROM toko t WHERE t.toko_cabang = x.tpm_penerima_cabang LIMIT 1) AS toko_penerima_nama,
  (SELECT t.toko_kota FROM toko t WHERE t.toko_cabang = x.tpm_penerima_cabang LIMIT 1) AS toko_penerima_kota
FROM (
  SELECT
    m.tpm_ref,
    m.tpm_kode_slug,
    m.tpm_qty,
    m.tpm_penerima_cabang,
    m.tpm_date,
    m.tpm_date_time,
    COUNT(*) AS duplikat_count,
    GROUP_CONCAT(m.tpm_id ORDER BY m.tpm_id SEPARATOR ', ') AS tpm_id_list
  FROM transfer_produk_masuk m
  WHERE 1=1 $cabangFilterSql
  GROUP BY m.tpm_ref, m.tpm_kode_slug, m.tpm_qty, m.tpm_penerima_cabang, m.tpm_date, m.tpm_date_time
  HAVING duplikat_count > 1
) x
ORDER BY x.tpm_ref DESC, x.tpm_date DESC, x.tpm_date_time DESC
";

$dataDuplikat = query($sqlDuplikat);

$jumlahGrup = is_array($dataDuplikat) ? count($dataDuplikat) : 0;
$estimasiBarisLebihan = 0;
if (is_array($dataDuplikat)) {
	foreach ($dataDuplikat as $row) {
		$estimasiBarisLebihan += max(0, (int) $row['duplikat_count'] - 1);
	}
}
?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1>Monitor duplikat transfer masuk</h1>
				</div>
				<div class="col-sm-6">
					<ol class="breadcrumb float-sm-right">
						<li class="breadcrumb-item"><a href="bo">Home</a></li>
						<li class="breadcrumb-item">Transfer Stock</li>
						<li class="breadcrumb-item active">Monitor duplikat</li>
					</ol>
				</div>
			</div>
		</div>
	</section>

	<section class="content">
		<div class="container-fluid">
			<div class="callout callout-warning">
				<h5><i class="fas fa-exclamation-triangle"></i> Tujuan halaman</h5>
				<p class="mb-1">
					Mendeteksi <strong>lebih dari satu</strong> baris <code>transfer_produk_masuk</code> dengan
					<strong>barcode/SKU sama</strong> (<code>tpm_kode_slug</code>) dan <strong>waktu catat persis sama</strong>
					(<code>tpm_date</code> + <code>tpm_date_time</code> identik), untuk kombinasi yang sama:
					no. ref, qty, dan cabang penerima. Pola ini cocok dengan konfirmasi ganda dalam satu detik / satu submit.
				</p>
				<p class="mb-0 text-muted">
					<?php if ($sessionCabang >= 1) : ?>
						Data difilter ke cabang login Anda (penerima).
					<?php else : ?>
						Anda login sebagai pusat / cabang 0: ditampilkan <strong>semua cabang penerima</strong>.
					<?php endif; ?>
				</p>
			</div>

			<div class="row">
				<div class="col-md-4">
					<div class="small-box bg-warning">
						<div class="inner">
							<h3><?= (int) $jumlahGrup; ?></h3>
							<p>Grup duplikat terdeteksi</p>
						</div>
						<div class="icon"><i class="fas fa-copy"></i></div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="small-box bg-danger">
						<div class="inner">
							<h3><?= (int) $estimasiBarisLebihan; ?></h3>
							<p>Perkiraan baris berlebih (total &minus;1 per grup)</p>
						</div>
						<div class="icon"><i class="fas fa-layer-group"></i></div>
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-header">
					<h3 class="card-title">Grup duplikat (barcode + tanggal + waktu catat sama — ≥ 2 tpm_id)</h3>
				</div>
				<div class="card-body">
					<div class="table-auto">
						<table id="tbl-monitor-duplikat" class="table table-bordered table-striped table-sm">
							<thead>
								<tr>
									<th style="width:3%;">No.</th>
									<th>No. ref</th>
									<th>Cabang</th>
									<th>Toko penerima</th>
									<th>SKU / barcode</th>
									<th>Nama barang</th>
									<th class="text-right">Qty</th>
									<th>Tanggal</th>
									<th>Waktu catat</th>
									<th class="text-center">Jumlah tpm</th>
									<th>Daftar tpm_id</th>
									<th class="text-center" style="width:14%;">Hapus salah satu</th>
									<th class="text-center" style="width:6%;">Zoom</th>
								</tr>
							</thead>
							<tbody>
								<?php if (empty($dataDuplikat)) : ?>
									<tr>
										<td colspan="13" class="text-center text-muted">Tidak ada duplikat yang cocok dengan kriteria.</td>
									</tr>
								<?php else : ?>
									<?php $no = 1; ?>
									<?php foreach ($dataDuplikat as $r) : ?>
										<tr>
											<td><?= $no++; ?></td>
											<td><strong><?= htmlspecialchars((string) $r['tpm_ref']); ?></strong></td>
											<td><?= (int) $r['tpm_penerima_cabang']; ?></td>
											<td><?php
												$tn = (string) ($r['toko_penerima_nama'] ?? '');
												$tk = (string) ($r['toko_penerima_kota'] ?? '');
												echo htmlspecialchars($tn . ($tn !== '' && $tk !== '' ? ' — ' : '') . $tk);
												?></td>
											<td><?= htmlspecialchars((string) $r['tpm_kode_slug']); ?></td>
											<td><?= htmlspecialchars((string) ($r['barang_nama'] ?? '-')); ?></td>
											<td class="text-right"><?= (int) $r['tpm_qty']; ?></td>
											<td><?= htmlspecialchars((string) ($r['tpm_date'] ?? '')); ?></td>
											<td><small><?= htmlspecialchars((string) ($r['tpm_date_time'] ?? '')); ?></small></td>
											<td class="text-center"><span class="badge badge-danger"><?= (int) $r['duplikat_count']; ?></span></td>
											<td><small style="font-family:monospace;"><?= htmlspecialchars((string) $r['tpm_id_list']); ?></small></td>
											<td class="text-center">
												<?php
												$tids = array_filter(array_map('intval', array_map('trim', explode(',', (string) $r['tpm_id_list']))));
												foreach ($tids as $tid) :
													if ($tid < 1) {
														continue;
													}
												?>
													<form method="post" action="monitor-duplikat-transfer-masuk-hapus.php" style="display:inline-block;margin:2px;" class="form-hapus-duplikat-tpm">
														<input type="hidden" name="tpm_id" value="<?= $tid; ?>">
														<button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus baris ini dan kurangi stok otomatis">
															Hapus <?= $tid; ?>
														</button>
													</form>
												<?php endforeach; ?>
												<?php if (empty($tids)) : ?>
													<span class="text-muted">—</span>
												<?php endif; ?>
											</td>
											<td class="text-center">
												<?php
												$zoomHref = $sessionCabang >= 1
													? 'transfer-stock-cabang-masuk-zoom?no=' . base64_encode((string) $r['tpm_ref'])
													: 'transfer-stock-cabang-keluar-zoom?no=' . base64_encode((string) $r['tpm_ref']);
												?>
												<a href="<?= htmlspecialchars($zoomHref); ?>" target="_blank" class="btn btn-sm btn-primary" title="Detail transfer (ref)">
													<i class="fa fa-search"></i>
												</a>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
				<div class="card-footer text-muted">
					<small>
						Grup dibentuk jika <code>tpm_ref</code>, <code>tpm_kode_slug</code>, <code>tpm_qty</code>, <code>tpm_penerima_cabang</code>, <code>tpm_date</code>, dan <code>tpm_date_time</code> identik antar baris, dengan jumlah baris &gt; 1.
						Tombol <strong>Hapus …</strong> menghapus satu <code>tpm_id</code> dan mengurangi <code>barang_stock</code> cabang penerima sebesar qty (kebalikan efek trigger saat insert).
						Produk ber-SN: status SN dikembalikan ke mode kirim (status 5, cabang pengirim) untuk SN pada baris yang dihapus.
					</small>
				</div>
			</div>
		</div>
	</section>
</div>

<?php include '_footer.php'; ?>
<script>
	$(function () {
		$(document).on('submit', 'form.form-hapus-duplikat-tpm', function () {
			return confirm('Hapus baris tpm_id ini dan kurangi stok cabang penerima secara otomatis? Pastikan ini memang duplikat, bukan baris sah.');
		});
		if ($('#tbl-monitor-duplikat tbody tr').length && !$('td[colspan]', '#tbl-monitor-duplikat').length) {
			$('#tbl-monitor-duplikat').DataTable({
				order: [[1, 'desc']],
				pageLength: 25
			});
		}
	});
</script>
</body>
</html>
