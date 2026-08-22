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

$preview = [];

$infoSatuan = null;

$kodeForm = trim((string) ($_POST['barang_kode'] ?? $_GET['kode'] ?? ''));

$metodeForm = (string) ($_POST['metode_konversi'] ?? 'otomatis');

$modeManual = ($metodeForm === 'manual');

$faktorForm = (float) ($_POST['faktor_isi'] ?? 0);

$arahForm = (string) ($_POST['arah_konversi'] ?? 'besar');

$keSatuanBesar = ($arahForm !== 'kecil');

$hppManualForm = (float) ($_POST['hpp_manual'] ?? 0);

$beliManualForm = (float) ($_POST['beli_manual'] ?? 0);

$syncPembelian = !empty($_POST['sync_pembelian']);

$analisisKonversi = null;

$previewJudul = '';



if ($kodeForm !== '') {

	$infoSatuan = barang_info_satuan_by_kode($conn, $kodeForm);

	if (!$modeManual && $faktorForm <= 0 && $infoSatuan) {

		if ((int) ($infoSatuan['satuan_isi_2'] ?? 0) > 0) {

			$faktorForm = (float) $infoSatuan['satuan_isi_2'];

		}

	}

}



if (!$modeManual && $kodeForm !== '' && $faktorForm > 0) {

	$analisisKonversi = barang_cek_konversi_hpp_satuan($conn, $kodeForm, $faktorForm, $keSatuanBesar);

}



if ($levelLogin === 'super admin' && isset($_POST['preview_konversi']) && $kodeForm !== '') {

	if ($modeManual) {

		if ($hppManualForm <= 0) {

			$pesan = 'Isi HPP baru (manual), minimal 0,1.';

			$tipePesan = 'warning';

		} else {

			$beliInput = $beliManualForm > 0 ? $beliManualForm : null;

			$preview = barang_preview_hpp_manual($conn, $kodeForm, $hppManualForm, $beliInput);

			$previewJudul = 'Input manual — HPP Rp ' . format_harga_beli_tampilan($hppManualForm);

			if ($beliManualForm > 0) {

				$previewJudul .= ', harga beli Rp ' . format_harga_beli_tampilan($beliManualForm);

			}

			if ($preview === []) {

				$pesan = 'Barang tidak ditemukan atau tidak aktif.';

				$tipePesan = 'warning';

			}

		}

	} elseif ($faktorForm <= 0) {

		$pesan = 'Isi faktor konversi (isi satuan), minimal 0,0001.';

		$tipePesan = 'warning';

	} else {

		$preview = barang_preview_hpp_ganti_satuan($conn, $kodeForm, $faktorForm, $keSatuanBesar);

		$simbolFaktor = $keSatuanBesar ? '×' : '÷';

		$previewJudul = ($keSatuanBesar ? 'Ke satuan lebih besar (× isi)' : 'Ke satuan lebih kecil (÷ isi)') . " ($simbolFaktor$faktorForm)";

		if ($preview === []) {

			$pesan = 'Barang tidak ditemukan atau tidak aktif.';

			$tipePesan = 'warning';

		}

	}

}



if ($levelLogin === 'super admin' && isset($_POST['apply_konversi']) && $kodeForm !== '') {

	if ($modeManual) {

		if ($hppManualForm <= 0) {

			$pesan = 'Isi HPP baru (manual) terlebih dahulu.';

			$tipePesan = 'warning';

		} else {

			$beliInput = $beliManualForm > 0 ? $beliManualForm : null;

			$hasil = barang_apply_hpp_manual($conn, $kodeForm, $hppManualForm, $beliInput, $syncPembelian);

			$preview = $hasil['preview'] ?? [];

			$previewJudul = 'Input manual — HPP Rp ' . format_harga_beli_tampilan($hppManualForm);

			if (!empty($hasil['ok'])) {

				$pesan = 'HPP manual diterapkan di ' . (int) $hasil['updated'] . ' cabang.';

				if ($syncPembelian) {

					$pesan .= ' Lalu disesuaikan ulang dari pembelian terakhir.';

				}

				$tipePesan = 'success';

				$infoSatuan = barang_info_satuan_by_kode($conn, $kodeForm);

			} else {

				$pesan = 'Gagal menerapkan HPP manual.';

				$tipePesan = 'warning';

			}

		}

	} elseif ($faktorForm <= 0) {

		$pesan = 'Isi faktor konversi terlebih dahulu.';

		$tipePesan = 'warning';

	} else {

		$hasil = barang_apply_hpp_ganti_satuan($conn, $kodeForm, $faktorForm, $keSatuanBesar, $syncPembelian);

		$preview = $hasil['preview'] ?? [];

		$simbolFaktor = $keSatuanBesar ? '×' : '÷';

		$previewJudul = ($keSatuanBesar ? 'Ke satuan lebih besar (× isi)' : 'Ke satuan lebih kecil (÷ isi)') . " ($simbolFaktor$faktorForm)";

		if (!empty($hasil['ok'])) {

			$pesan = 'HPP & harga beli terakhir dikonversi di ' . (int) $hasil['updated'] . ' cabang.';

			if ($syncPembelian) {

				$pesan .= ' Lalu disesuaikan ulang dari pembelian terakhir.';

			}

			$tipePesan = 'success';

			$infoSatuan = barang_info_satuan_by_kode($conn, $kodeForm);

			$analisisKonversi = barang_cek_konversi_hpp_satuan($conn, $kodeForm, $faktorForm, $keSatuanBesar);

		} else {

			$pesan = 'Gagal menerapkan konversi.';

			$tipePesan = 'warning';

		}

	}

}



if ($previewJudul === '' && $preview !== []) {

	$simbolFaktor = $keSatuanBesar ? '×' : '÷';

	$previewJudul = $modeManual

		? 'Input manual'

		: (($keSatuanBesar ? 'Ke satuan lebih besar (× isi)' : 'Ke satuan lebih kecil (÷ isi)') . " ($simbolFaktor$faktorForm)");

}

?>



<div class="content-wrapper">

	<section class="content-header">

		<div class="container-fluid">

			<div class="row mb-2">

				<div class="col-sm-6">

					<h1>Perbaiki HPP Ganti Satuan</h1>

				</div>

				<div class="col-sm-6">

					<ol class="breadcrumb float-sm-right">

						<li class="breadcrumb-item"><a href="bo">Home</a></li>

						<li class="breadcrumb-item"><a href="barang">Barang</a></li>

						<li class="breadcrumb-item"><a href="perbaiki-hpp-barang.php">Perbaiki HPP</a></li>

						<li class="breadcrumb-item active">Ganti Satuan</li>

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



			<div class="card card-info">

				<div class="card-header">

					<h3 class="card-title">Konversi HPP setelah ubah satuan</h3>

				</div>

				<div class="card-body">

					<p class="text-muted mb-2">

						Gunakan setelah mengubah <strong>satuan utama</strong> barang (mis. PCS → RTG).

						Pilih <strong>konversi otomatis</strong> (×/÷ isi) atau <strong>input manual</strong> jika nominal HPP sudah diketahui.

					</p>

					<p class="text-warning mb-0">

						Angka <strong>1.600</strong> = <strong>Rp 1.600</strong> (titik = pemisah ribuan).

						Jangan centang &ldquo;hitung ulang dari pembelian&rdquo; jika belum ada pembelian di satuan baru.

					</p>

				</div>

			</div>



			<div class="card card-primary">

				<div class="card-header"><h3 class="card-title">Form konversi</h3></div>

				<div class="card-body">

					<form method="post" id="form-konversi-hpp">

						<div class="form-row">

							<div class="form-group col-md-4">

								<label>Kode / barcode barang</label>

								<input type="text" name="barang_kode" class="form-control" required

									value="<?= htmlspecialchars($kodeForm, ENT_QUOTES, 'UTF-8'); ?>"

									placeholder="8992775101421">

							</div>

							<div class="form-group col-md-8">

								<label>Metode</label>

								<div>

									<div class="custom-control custom-radio custom-control-inline">

										<input type="radio" id="metode_otomatis" name="metode_konversi" value="otomatis" class="custom-control-input metode-toggle" <?= !$modeManual ? 'checked' : ''; ?>>

										<label class="custom-control-label" for="metode_otomatis">Konversi otomatis (×/÷ isi)</label>

									</div>

									<div class="custom-control custom-radio custom-control-inline">

										<input type="radio" id="metode_manual" name="metode_konversi" value="manual" class="custom-control-input metode-toggle" <?= $modeManual ? 'checked' : ''; ?>>

										<label class="custom-control-label" for="metode_manual">Input nominal manual</label>

									</div>

								</div>

							</div>

						</div>



						<div id="blok-otomatis" class="<?= $modeManual ? 'd-none' : ''; ?>">

							<div class="form-row">

								<div class="form-group col-md-3">

									<label>Faktor isi (1 satuan besar = … satuan kecil)</label>

									<input type="number" name="faktor_isi" id="faktor_isi" class="form-control" step="0.0001" min="0.0001"

										value="<?= $faktorForm > 0 ? htmlspecialchars((string) $faktorForm, ENT_QUOTES, 'UTF-8') : ''; ?>"

										placeholder="10" <?= !$modeManual ? 'required' : ''; ?>>

								</div>

								<div class="form-group col-md-9">

									<label>Arah konversi</label>

									<div>

										<div class="custom-control custom-radio custom-control-inline">

											<input type="radio" id="arah_besar" name="arah_konversi" value="besar" class="custom-control-input" <?= $keSatuanBesar ? 'checked' : ''; ?>>

											<label class="custom-control-label" for="arah_besar">× isi (kecil → besar)</label>

										</div>

										<div class="custom-control custom-radio custom-control-inline">

											<input type="radio" id="arah_kecil" name="arah_konversi" value="kecil" class="custom-control-input" <?= !$keSatuanBesar ? 'checked' : ''; ?>>

											<label class="custom-control-label" for="arah_kecil">÷ isi (besar → kecil)</label>

										</div>

									</div>

								</div>

							</div>

						</div>



						<div id="blok-manual" class="<?= !$modeManual ? 'd-none' : ''; ?>">

							<div class="form-row">

								<div class="form-group col-md-4">

									<label>HPP baru (manual)</label>

									<input type="number" name="hpp_manual" id="hpp_manual" class="form-control" step="0.1" min="0.1"

										value="<?= $hppManualForm > 0 ? htmlspecialchars((string) $hppManualForm, ENT_QUOTES, 'UTF-8') : ''; ?>"

										placeholder="16000" <?= $modeManual ? 'required' : ''; ?>>

									<small class="text-muted">Nominal HPP per satuan utama saat ini</small>

								</div>

								<div class="form-group col-md-4">

									<label>Harga beli terakhir (opsional)</label>

									<input type="number" name="beli_manual" id="beli_manual" class="form-control" step="0.1" min="0.1"

										value="<?= $beliManualForm > 0 ? htmlspecialchars((string) $beliManualForm, ENT_QUOTES, 'UTF-8') : ''; ?>"

										placeholder="Kosongkan = sama dengan HPP">

									<small class="text-muted">Kosongkan jika sama dengan HPP baru</small>

								</div>

							</div>

						</div>



						<?php if ($infoSatuan) : ?>

						<div class="alert alert-secondary py-2">

							<strong><?= htmlspecialchars($infoSatuan['barang_nama'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong><br>

							Satuan 1: <?= htmlspecialchars($infoSatuan['satuan_nama_1'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>

							<?php if ((int) ($infoSatuan['satuan_id_2'] ?? 0) > 0) : ?>

								| Satuan 2: <?= htmlspecialchars($infoSatuan['satuan_nama_2'] ?? '', ENT_QUOTES, 'UTF-8'); ?> (isi <?= (int) $infoSatuan['satuan_isi_2']; ?>)

							<?php endif; ?>

							<br>HPP saat ini: <strong>Rp <?= format_harga_beli_tampilan(barang_hpp_dari_row($infoSatuan)); ?>/<?= htmlspecialchars($infoSatuan['satuan_nama_1'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>

							| Harga beli master: <strong>Rp <?= format_harga_beli_tampilan((float) ($infoSatuan['barang_harga_beli'] ?? 0)); ?></strong>

							<?php

							$beliTrx = barang_get_harga_beli_terakhir($conn, $kodeForm);

							if ($beliTrx > 0) :

							?>

							| Pembelian terakhir: <strong>Rp <?= format_harga_beli_tampilan($beliTrx); ?></strong>

							<small class="text-muted">(harga saat transaksi lama)</small>

							<?php endif; ?>

						</div>

						<?php endif; ?>



						<?php if (!$modeManual && $analisisKonversi && ($analisisKonversi['pesan'] ?? '') !== '') : ?>

						<div class="alert alert-<?= htmlspecialchars($analisisKonversi['level'] ?? 'info', ENT_QUOTES, 'UTF-8'); ?> py-2">

							<?= htmlspecialchars($analisisKonversi['pesan'], ENT_QUOTES, 'UTF-8'); ?>

						</div>

						<?php endif; ?>



						<div class="form-group">

							<div class="custom-control custom-checkbox">

								<input type="checkbox" class="custom-control-input" id="sync_pembelian" name="sync_pembelian" value="1" <?= $syncPembelian ? 'checked' : ''; ?>>

								<label class="custom-control-label" for="sync_pembelian">

									Setelah konversi, hitung ulang HPP dari pembelian terakhir

									<small class="text-danger">(jangan centang jika belum ada pembelian di satuan baru)</small>

								</label>

							</div>

						</div>



						<button type="submit" name="preview_konversi" class="btn btn-info">Preview</button>

						<?php if ($levelLogin === 'super admin') : ?>

						<button type="submit" name="apply_konversi" class="btn btn-primary" onclick="return confirm('Terapkan perubahan HPP ke semua cabang?');">

							Terapkan

						</button>

						<?php endif; ?>

						<a href="perbaiki-hpp-barang.php" class="btn btn-default">Perbaiki HPP biasa</a>

					</form>

				</div>

			</div>



			<?php if ($preview !== []) : ?>

			<div class="card card-success">

				<div class="card-header">

					<h3 class="card-title">Preview — <?= htmlspecialchars($previewJudul, ENT_QUOTES, 'UTF-8'); ?></h3>

				</div>

				<div class="card-body table-responsive p-0">

					<table class="table table-bordered table-sm mb-0">

						<thead>

							<tr>

								<th>Cabang</th>

								<th>Stok</th>

								<th>HPP lama</th>

								<th>HPP baru</th>

								<th>Harga beli lama</th>

								<th>Harga beli baru</th>

							</tr>

						</thead>

						<tbody>

							<?php foreach ($preview as $row) : ?>

							<tr>

								<td><?= (int) $row['cabang']; ?></td>

								<td><?= number_format((float) $row['stock'], 0, ',', '.'); ?></td>

								<td><?= format_harga_beli_tampilan((float) $row['hpp_lama']); ?></td>

								<td><strong><?= format_harga_beli_tampilan((float) $row['hpp_baru']); ?></strong></td>

								<td><?= format_harga_beli_tampilan((float) $row['beli_lama']); ?></td>

								<td><strong><?= format_harga_beli_tampilan((float) $row['beli_baru']); ?></strong></td>

							</tr>

							<?php endforeach; ?>

						</tbody>

					</table>

				</div>

			</div>

			<?php endif; ?>

		</div>

	</section>

</div>



<?php include '_footer.php'; ?>

<script>

(function () {

	function toggleMetode() {

		var manual = document.getElementById('metode_manual').checked;

		document.getElementById('blok-otomatis').classList.toggle('d-none', manual);

		document.getElementById('blok-manual').classList.toggle('d-none', !manual);

		document.getElementById('faktor_isi').required = !manual;

		document.getElementById('hpp_manual').required = manual;

	}

	document.querySelectorAll('.metode-toggle').forEach(function (el) {

		el.addEventListener('change', toggleMetode);

	});

	toggleMetode();

})();

</script>

