<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
include '_header.php';
include '_nav.php';
include '_sidebar.php';

require_once numart_path('aksi/hpp-perbaikan-lib.php');

if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
	echo "<script>document.location.href = 'bo';</script>";
	exit;
}

if (!hpp_perbaikan_can_gudang((int) $sessionCabang, (string) $levelLogin)) {
	echo "<script>alert('Halaman ini khusus gudang / super admin.'); document.location.href='hpp-perbaikan-toko';</script>";
	exit;
}

hpp_perbaikan_ensure_tables($conn);

$pesan = '';
$tipePesan = 'info';
$preview = null;

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userNama = (string) ($_SESSION['user_nama'] ?? '');

// Prefill dari request
$reqId = (int) ($_GET['request_id'] ?? $_POST['request_id'] ?? 0);
$reqRow = $reqId > 0 ? hpp_perbaikan_get_request($conn, $reqId) : null;

$kodeForm = trim((string) ($_POST['barang_kode'] ?? $_GET['kode'] ?? ($reqRow['barang_kode'] ?? '')));
$tAwalForm = (string) ($_POST['tanggal_awal'] ?? $_GET['tanggal_awal'] ?? ($reqRow['tanggal_awal'] ?? date('Y-m-01')));
$tAkhirForm = (string) ($_POST['tanggal_akhir'] ?? $_GET['tanggal_akhir'] ?? ($reqRow['tanggal_akhir'] ?? date('Y-m-d')));
$cabangForm = isset($_POST['cabang']) ? (int) $_POST['cabang'] : (isset($_GET['cabang']) ? (int) $_GET['cabang'] : (isset($reqRow['cabang_pemohon']) ? (int) $reqRow['cabang_pemohon'] : -1));

$hppMaster = $kodeForm !== '' ? (float) hitungHppBarangSnapshotAkurat($conn, $kodeForm) : 0.0;
$hppForm = isset($_POST['hpp_baru']) ? (float) $_POST['hpp_baru'] : $hppMaster;

if (isset($_POST['update_status'])) {
	$hasil = hpp_perbaikan_update_status(
		$conn,
		(int) ($_POST['id'] ?? 0),
		(string) ($_POST['status'] ?? ''),
		(string) ($_POST['catatan_gudang'] ?? ''),
		$userId,
		$userNama
	);
	$pesan = $hasil['message'];
	$tipePesan = $hasil['ok'] ? 'success' : 'warning';
}

if (isset($_POST['preview_histori'])) {
	$preview = hpp_histori_preview($conn, $kodeForm, $tAwalForm, $tAkhirForm, $hppForm, $cabangForm);
	if (!$preview['ok']) {
		$pesan = $preview['message'];
		$tipePesan = 'warning';
	}
}

if (isset($_POST['apply_histori'])) {
	$hasil = hpp_histori_apply(
		$conn,
		$kodeForm,
		$tAwalForm,
		$tAkhirForm,
		$hppForm,
		$cabangForm,
		$reqId > 0 ? $reqId : null,
		$userId,
		$userNama,
		true
	);
	$pesan = $hasil['message'];
	$tipePesan = $hasil['ok'] ? 'success' : 'danger';
	if ($hasil['ok']) {
		$preview = null;
		$reqRow = $reqId > 0 ? hpp_perbaikan_get_request($conn, $reqId) : $reqRow;
	} else {
		$preview = hpp_histori_preview($conn, $kodeForm, $tAwalForm, $tAkhirForm, $hppForm, $cabangForm);
	}
}

$statusFilter = (string) ($_GET['status'] ?? 'baru');
if (!in_array($statusFilter, ['semua', 'baru', 'diproses', 'selesai', 'ditolak'], true)) {
	$statusFilter = 'baru';
}

$listRequest = hpp_perbaikan_list_request($conn, null, $statusFilter, 200);
$countBaru = hpp_perbaikan_count_baru($conn);
$listLog = hpp_histori_list_log($conn, 30);
$listCabang = query("SELECT * FROM toko WHERE toko_status = '1' ORDER BY toko_cabang");

function hppgRupiah($n)
{
	return 'Rp ' . number_format((float) $n, 0, ',', '.');
}
?>

<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1>Perbaikan HPP Gudang</h1>
				</div>
				<div class="col-sm-6">
					<ol class="breadcrumb float-sm-right">
						<li class="breadcrumb-item"><a href="bo">Home</a></li>
						<li class="breadcrumb-item active">Perbaikan HPP</li>
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

			<div class="row mb-3">
				<div class="col-md-4">
					<div class="info-box">
						<span class="info-box-icon bg-danger"><i class="fas fa-inbox"></i></span>
						<div class="info-box-content">
							<span class="info-box-text">Permintaan baru</span>
							<span class="info-box-number"><?= number_format($countBaru, 0, ',', '.'); ?></span>
						</div>
					</div>
				</div>
				<div class="col-md-8 d-flex align-items-center flex-wrap" style="gap:8px;">
					<a href="perbaiki-hpp-ganti-satuan<?= $kodeForm !== '' ? ('?kode=' . urlencode($kodeForm)) : ''; ?>" class="btn btn-warning btn-sm" target="_blank">
						<i class="fas fa-exchange-alt"></i> HPP ganti satuan
					</a>
					<a href="perbaiki-hpp-barang<?= $kodeForm !== '' ? ('?kode=' . urlencode($kodeForm)) : ''; ?>" class="btn btn-primary btn-sm" target="_blank">
						<i class="fas fa-sync"></i> Perbaiki HPP master
					</a>
					<a href="edit-transaksi-pembelian" class="btn btn-success btn-sm" target="_blank">
						<i class="fas fa-shopping-cart"></i> Edit pembelian
					</a>
				</div>
			</div>

			<div class="card card-outline card-danger">
				<div class="card-header">
					<h3 class="card-title"><i class="fas fa-clipboard-list"></i> Permintaan dari toko</h3>
					<div class="card-tools">
						<form method="get" class="form-inline">
							<select name="status" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
								<option value="baru" <?= $statusFilter === 'baru' ? 'selected' : ''; ?>>Baru</option>
								<option value="diproses" <?= $statusFilter === 'diproses' ? 'selected' : ''; ?>>Diproses</option>
								<option value="selesai" <?= $statusFilter === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
								<option value="ditolak" <?= $statusFilter === 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
								<option value="semua" <?= $statusFilter === 'semua' ? 'selected' : ''; ?>>Semua</option>
							</select>
						</form>
					</div>
				</div>
				<div class="card-body table-responsive p-0">
					<table class="table table-hover table-striped mb-0">
						<thead>
							<tr>
								<th>ID</th>
								<th>Status</th>
								<th>Toko</th>
								<th>Barang</th>
								<th>Periode</th>
								<th class="text-right">Laba ringkas</th>
								<th>Catatan toko</th>
								<th>Aksi</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($listRequest)) : ?>
								<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada permintaan.</td></tr>
							<?php else : ?>
								<?php foreach ($listRequest as $r) :
									$st = (string) ($r['status'] ?? '');
									$qsKoreksi = http_build_query([
										'request_id' => (int) $r['id'],
										'kode' => (string) $r['barang_kode'],
										'tanggal_awal' => (string) $r['tanggal_awal'],
										'tanggal_akhir' => (string) $r['tanggal_akhir'],
										'cabang' => (int) $r['cabang_pemohon'],
										'status' => $statusFilter,
									]);
								?>
									<tr>
										<td>#<?= (int) $r['id']; ?></td>
										<td>
											<span class="badge <?= hpp_perbaikan_status_badge($st); ?>">
												<?= htmlspecialchars(hpp_perbaikan_status_label($st), ENT_QUOTES, 'UTF-8'); ?>
											</span>
										</td>
										<td>
											<?= htmlspecialchars(hpp_perbaikan_nama_cabang($conn, (int) $r['cabang_pemohon']), ENT_QUOTES, 'UTF-8'); ?>
											<br><small class="text-muted"><?= htmlspecialchars((string) ($r['dibuat_nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
										</td>
										<td>
											<code><?= htmlspecialchars((string) $r['barang_kode'], ENT_QUOTES, 'UTF-8'); ?></code><br>
											<?= htmlspecialchars((string) ($r['barang_nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
										</td>
										<td>
											<?= htmlspecialchars(date('d/m/Y', strtotime((string) $r['tanggal_awal'])), ENT_QUOTES, 'UTF-8'); ?>
											–
											<?= htmlspecialchars(date('d/m/Y', strtotime((string) $r['tanggal_akhir'])), ENT_QUOTES, 'UTF-8'); ?>
											<br>
											<small class="text-muted">
												<?= (int) ($r['jml_trx_rugi'] ?? 0); ?> trx rugi / <?= (int) ($r['jml_trx'] ?? 0); ?> trx
											</small>
										</td>
										<td class="text-right <?= ((float) $r['ringkas_laba'] < 0) ? 'text-danger' : 'text-success'; ?>">
											<strong><?= hppgRupiah($r['ringkas_laba']); ?></strong>
										</td>
										<td style="max-width:220px;">
											<small><?= nl2br(htmlspecialchars((string) ($r['catatan'] ?? '-'), ENT_QUOTES, 'UTF-8')); ?></small>
										</td>
										<td class="text-nowrap">
											<a href="hpp-perbaikan-gudang?<?= htmlspecialchars($qsKoreksi, ENT_QUOTES, 'UTF-8'); ?>#koreksi"
											   class="btn btn-xs btn-warning" title="Koreksi histori">
												<i class="fas fa-wrench"></i>
											</a>
											<button type="button"
												class="btn btn-xs btn-info btn-status-hpp"
												title="Update status"
												data-id="<?= (int) $r['id']; ?>"
												data-status="<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>"
												data-catatan="<?= htmlspecialchars((string) ($r['catatan_gudang'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
												<i class="fas fa-flag"></i>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="modal fade" id="modalStatusHpp" tabindex="-1" role="dialog" aria-hidden="true">
				<div class="modal-dialog" role="document">
					<form method="post" class="modal-content">
						<input type="hidden" name="id" id="status_hpp_id" value="">
						<div class="modal-header">
							<h5 class="modal-title">Update status <span id="status_hpp_label"></span></h5>
							<button type="button" class="close" data-dismiss="modal" aria-label="Close">
								<span aria-hidden="true">&times;</span>
							</button>
						</div>
						<div class="modal-body">
							<div class="form-group">
								<label>Status</label>
								<select name="status" id="status_hpp_status" class="form-control" required>
									<option value="baru">Baru</option>
									<option value="diproses">Diproses</option>
									<option value="selesai">Selesai</option>
									<option value="ditolak">Ditolak</option>
								</select>
							</div>
							<div class="form-group">
								<label>Catatan gudang</label>
								<textarea name="catatan_gudang" id="status_hpp_catatan" class="form-control" rows="3"></textarea>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
							<button type="submit" name="update_status" class="btn btn-primary">Simpan</button>
						</div>
					</form>
				</div>
			</div>

			<div class="card card-outline card-warning" id="koreksi">
				<div class="card-header">
					<h3 class="card-title"><i class="fas fa-history"></i> Koreksi HPP histori penjualan</h3>
				</div>
				<form method="post">
					<input type="hidden" name="request_id" value="<?= $reqId > 0 ? $reqId : 0; ?>">
					<div class="card-body">
						<?php if ($reqRow) : ?>
							<div class="alert alert-info py-2">
								Dari permintaan <strong>#<?= (int) $reqRow['id']; ?></strong>
								— <?= htmlspecialchars((string) $reqRow['barang_kode'], ENT_QUOTES, 'UTF-8'); ?>
								(<?= htmlspecialchars(hpp_perbaikan_nama_cabang($conn, (int) $reqRow['cabang_pemohon']), ENT_QUOTES, 'UTF-8'); ?>)
							</div>
						<?php endif; ?>
						<p class="text-muted">
							Mengubah <code>keranjang_harga_beli</code> pada baris penjualan lama, lalu menghitung ulang
							<code>invoice_total_beli</code>. Master HPP juga ikut disinkronkan ke semua cabang.
						</p>
						<div class="row">
							<div class="col-md-3">
								<div class="form-group">
									<label>Kode barang</label>
									<input type="text" name="barang_kode" class="form-control" required
										value="<?= htmlspecialchars($kodeForm, ENT_QUOTES, 'UTF-8'); ?>">
								</div>
							</div>
							<div class="col-md-2">
								<div class="form-group">
									<label>Tanggal awal</label>
									<input type="date" name="tanggal_awal" class="form-control" required
										value="<?= htmlspecialchars($tAwalForm, ENT_QUOTES, 'UTF-8'); ?>">
								</div>
							</div>
							<div class="col-md-2">
								<div class="form-group">
									<label>Tanggal akhir</label>
									<input type="date" name="tanggal_akhir" class="form-control" required
										value="<?= htmlspecialchars($tAkhirForm, ENT_QUOTES, 'UTF-8'); ?>">
								</div>
							</div>
							<div class="col-md-3">
								<div class="form-group">
									<label>Cabang penjualan</label>
									<select name="cabang" class="form-control">
										<option value="-1" <?= $cabangForm < 0 ? 'selected' : ''; ?>>Semua cabang</option>
										<?php foreach ($listCabang as $c) :
											$cid = (int) $c['toko_cabang'];
										?>
											<option value="<?= $cid; ?>" <?= $cabangForm === $cid ? 'selected' : ''; ?>>
												<?= htmlspecialchars($c['toko_nama'] . ' (' . $cid . ')', ENT_QUOTES, 'UTF-8'); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							</div>
							<div class="col-md-2">
								<div class="form-group">
									<label>HPP baru / pcs</label>
									<input type="number" step="0.1" min="0" name="hpp_baru" class="form-control" required
										value="<?= htmlspecialchars((string) $hppForm, ENT_QUOTES, 'UTF-8'); ?>">
									<?php if ($hppMaster > 0) : ?>
										<small class="text-muted">Master skrg: <?= hppgRupiah($hppMaster); ?></small>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
					<div class="card-footer">
						<button type="submit" name="preview_histori" class="btn btn-info">
							<i class="fa fa-eye"></i> Preview
						</button>
						<button type="submit" name="apply_histori" class="btn btn-danger"
							onclick="return confirm('Koreksi HPP histori penjualan sesuai filter? Tindakan ini mengubah data transaksi lama.');">
							<i class="fas fa-check"></i> Terapkan koreksi
						</button>
					</div>
				</form>

				<?php if (is_array($preview) && !empty($preview['ok'])) : ?>
					<div class="card-body border-top">
						<div class="row mb-3">
							<div class="col-md-3"><strong>Baris:</strong> <?= number_format((int) $preview['jml_baris'], 0, ',', '.'); ?></div>
							<div class="col-md-3"><strong>Invoice:</strong> <?= number_format((int) $preview['jml_invoice'], 0, ',', '.'); ?></div>
							<div class="col-md-3"><strong>HPP lama:</strong> <?= hppgRupiah($preview['total_hpp_lama']); ?></div>
							<div class="col-md-3"><strong>HPP baru:</strong> <?= hppgRupiah($preview['total_hpp_baru']); ?></div>
						</div>
						<div class="table-responsive">
							<table class="table table-sm table-bordered">
								<thead>
									<tr>
										<th>Invoice</th>
										<th>Tgl</th>
										<th>Cab</th>
										<th class="text-right">Qty</th>
										<th class="text-right">HPP lama</th>
										<th class="text-right">HPP baru</th>
										<th class="text-right">Penjualan</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach (($preview['sample'] ?? []) as $s) : ?>
										<tr>
											<td><code><?= htmlspecialchars((string) $s['penjualan_invoice'], ENT_QUOTES, 'UTF-8'); ?></code></td>
											<td><?= htmlspecialchars(date('d/m/Y', strtotime((string) $s['penjualan_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?= (int) $s['penjualan_cabang']; ?></td>
											<td class="text-right"><?= number_format((float) $s['barang_qty_keranjang'], 0, ',', '.'); ?></td>
											<td class="text-right text-danger"><?= hppgRupiah($s['hpp_lama']); ?></td>
											<td class="text-right text-success"><?= hppgRupiah($s['hpp_baru']); ?></td>
											<td class="text-right"><?= hppgRupiah($s['penjualan']); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<small class="text-muted">Menampilkan maks. 30 sampel (selisih HPP terbesar dulu).</small>
					</div>
				<?php endif; ?>
			</div>

			<div class="card">
				<div class="card-header">
					<h3 class="card-title">Log koreksi histori (terbaru)</h3>
				</div>
				<div class="card-body table-responsive p-0">
					<table class="table table-sm mb-0">
						<thead>
							<tr>
								<th>ID</th>
								<th>Waktu</th>
								<th>Kode</th>
								<th>Cabang</th>
								<th>Periode</th>
								<th class="text-right">HPP baru</th>
								<th class="text-right">Baris</th>
								<th>Oleh</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($listLog)) : ?>
								<tr><td colspan="8" class="text-center text-muted py-3">Belum ada log.</td></tr>
							<?php else : ?>
								<?php foreach ($listLog as $log) : ?>
									<tr>
										<td>#<?= (int) $log['id']; ?></td>
										<td><?= htmlspecialchars((string) $log['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
										<td><code><?= htmlspecialchars((string) $log['barang_kode'], ENT_QUOTES, 'UTF-8'); ?></code></td>
										<td><?= ((int) $log['cabang'] < 0) ? 'Semua' : (int) $log['cabang']; ?></td>
										<td>
											<?= htmlspecialchars(date('d/m/Y', strtotime((string) $log['tanggal_awal'])), ENT_QUOTES, 'UTF-8'); ?>
											–
											<?= htmlspecialchars(date('d/m/Y', strtotime((string) $log['tanggal_akhir'])), ENT_QUOTES, 'UTF-8'); ?>
										</td>
										<td class="text-right"><?= hppgRupiah($log['hpp_baru']); ?></td>
										<td class="text-right"><?= number_format((int) $log['jml_baris'], 0, ',', '.'); ?></td>
										<td><?= htmlspecialchars((string) ($log['dibuat_nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
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
<script>
  $(function () {
    $('.btn-status-hpp').on('click', function () {
      var $b = $(this);
      var id = $b.data('id');
      var status = $b.data('status') || 'baru';
      var catatan = $b.attr('data-catatan') || '';
      $('#status_hpp_id').val(id);
      $('#status_hpp_label').text('#' + id);
      $('#status_hpp_status').val(status);
      $('#status_hpp_catatan').val(catatan);
      $('#modalStatusHpp').modal('show');
    });
  });
</script>
