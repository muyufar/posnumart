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
						Menyesuaikan kode akun operasional:
						kas tunai per cabang (1-1101 s/d 1-1105), bank BRI 0251 per cabang (1-1202 s/d 1-1206, terhubung ke Nugrosir),
						piutang 1-1301, hutang 2-1101, dan menghapus akun ganda 1-1152 / 1-1153.
						<strong>Jalankan sekali</strong> setelah upload file ke live.
						Jika akun BRI cabang belum muncul di Kategori Laba, upload ulang <code>aksi/akun-link-lib.php</code> lalu jalankan migrasi lagi.
					</p>
					<ul>
						<li>0 — Nugrosir: kas 1-1101, BRI 1-1202</li>
						<li>1 — Dukun: kas 1-1102, BRI 1-1203</li>
						<li>3 — Srumbung: kas 1-1103, BRI 1-1204</li>
						<li>2 — Pakis: kas 1-1104, BRI 1-1205</li>
						<li>5 — Tegalrejo: kas 1-1105, BRI 1-1206</li>
						<li>Transaksi QRIS/TF &amp; setoran shift → BRI cabang + mirror otomatis ke 1-1202 Nugrosir</li>
					</ul>

					<?php if ($levelLogin === 'super admin') : ?>
					<form method="post">
						<button type="submit" name="migrasi_akun" class="btn btn-primary" onclick="return confirm('Jalankan migrasi link akun? Backup database disarankan.');">
							Jalankan Migrasi Akun
						</button>
					</form>
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
