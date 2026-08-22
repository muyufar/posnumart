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
$logMigrasi = [];

if ($levelLogin === 'super admin' && isset($_POST['migrasi_akun'])) {
	$hasil = akun_link_migrasi_semua($conn);
	$logMigrasi = $hasil['log'] ?? [];
	$pesan = 'Migrasi akun selesai (' . count($logMigrasi) . ' langkah).';
	$tipePesan = !empty($hasil['ok']) ? 'success' : 'warning';
}

if ($levelLogin === 'super admin' && isset($_POST['sinkron_hierarki_coa'])) {
	$logMigrasi = [];
	akun_link_sinkron_hierarki_level123_ke_cabang_toko($conn, $logMigrasi);
	akun_link_normalisasi_bri_cabang_toko($conn, $logMigrasi);
	$pesan = 'Sinkron hierarki COA cabang toko selesai (' . count($logMigrasi) . ' langkah).';
	$tipePesan = 'success';
}

if ($levelLogin === 'super admin' && isset($_POST['perbaiki_setor_laba'])) {
	$hasilSetor = akun_link_perbaiki_laba_setor_bank_bri($conn);
	$logMigrasi = $hasilSetor['log'] ?? [];
	$fixed = (int) ($hasilSetor['fixed'] ?? 0);
	$pesan = 'Perbaikan data operasional setor/transfer selesai (' . $fixed . ' transaksi).';
	$tipePesan = !empty($hasilSetor['ok']) ? 'success' : 'warning';
}
?>

<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1>Perbaiki Link Akun</h1>
				</div>
				<div class="col-sm-6">
					<ol class="breadcrumb float-sm-right">
						<li class="breadcrumb-item"><a href="bo">Home</a></li>
						<li class="breadcrumb-item active">Perbaiki Link Akun</li>
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
					<h3 class="card-title">Migrasi COA (docs/DAFTAR LINK AKUN)</h3>
				</div>
				<div class="card-body">
					<p class="text-muted">
						Chart akun operasional:
						kas tunai per cabang (1-1101 s/d 1-1105),
						<strong>bank BRI operasional kode 1-1202 per cabang</strong> (induk L3 = 1-1200 KAS BANK),
						piutang 1-1301, hutang 2-1101.
						PCNU (cabang 0) tetap punya sub rekening fisik: 1-1201 BNU, 1-1202 Transaksi, 1-1203 Koperasi, 1-1204 Gaji.
					</p>
					<ul>
						<li>Setiap cabang toko: salin kepala akun <strong>Level 1–3</strong> dari PCNU (HARTA → HARTA LANCAR → KAS BANK)</li>
						<li>0 — Nugrosir/PCNU: kas 1-1101, BRI operasional 1-1202 (+ rekening fisik lain di bawah 1-1200)</li>
						<li>1 — Dukun: kas 1-1102, BRI 1-1202 (bukan 1-1203)</li>
						<li>2 — Pakis: kas 1-1104, BRI 1-1202 (bukan 1-1205)</li>
						<li>3 — Srumbung: kas 1-1103, BRI 1-1202 (bukan 1-1204)</li>
						<li>5 — Tegalrejo: kas 1-1105, BRI 1-1202 (bukan 1-1206)</li>
						<li>Penjualan QRIS/TF &amp; setoran shift → <strong>1-1202 cabang transaksi</strong></li>
					</ul>

					<?php if ($levelLogin === 'super admin') : ?>
					<form method="post" class="d-inline">
						<button type="submit" name="sinkron_hierarki_coa" class="btn btn-info" onclick="return confirm('Salin kepala akun L1–L3 dari PCNU ke cabang toko dan normalisasi BRI ke 1-1202? Backup database disarankan.');">
							Sinkron Hierarki COA Cabang
						</button>
					</form>
					<form method="post" class="d-inline ml-2">
						<button type="submit" name="migrasi_akun" class="btn btn-primary" onclick="return confirm('Jalankan migrasi link akun? Backup database disarankan.');">
							Jalankan Migrasi Akun
						</button>
					</form>
					<form method="post" class="d-inline ml-2">
						<button type="submit" name="perbaiki_setor_laba" class="btn btn-warning" onclick="return confirm('Perbaiki akun debit setor/transfer yang hilang? Backup database disarankan.');">
							Perbaiki Setor → BRI Cabang
						</button>
					</form>
					<p class="text-muted small mt-2 mb-0">
						Jika di Data Operasional kolom <strong>ke (Debit)</strong> setor uang kosong setelah migrasi,
						klik <strong>Perbaiki Setor → BRI Cabang</strong> lalu jalankan <strong>Hitung Ulang Saldo</strong>.
					</p>
					<?php else : ?>
					<p class="text-warning mb-0">Hanya super admin yang dapat menjalankan migrasi.</p>
					<?php endif; ?>

					<?php if (!empty($logMigrasi)) : ?>
					<hr>
					<h5>Log migrasi</h5>
					<ul class="mb-0">
						<?php foreach ($logMigrasi as $baris) : ?>
						<li><?= htmlspecialchars($baris, ENT_QUOTES, 'UTF-8'); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>
</div>

<?php include '_footer.php'; ?>
