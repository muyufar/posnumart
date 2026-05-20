<?php
include '_header-artibut.php';

if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
	echo "<script>document.location.href='bo';</script>";
	exit;
}

stock_opname_ensure_soh_approved_column();

$id = isset($_GET['id']) ? abs((int) base64_decode($_GET['id'])) : 0;
if ($id < 1) {
	echo "<script>alert('Sesi tidak valid');document.location.href='stock-opname-per-produk';</script>";
	exit;
}

$rows = query("SELECT * FROM stock_opname WHERE stock_opname_id = $id && stock_opname_cabang = $sessionCabang");
if (empty($rows[0])) {
	echo "<script>alert('Data tidak ditemukan');document.location.href='stock-opname-per-produk';</script>";
	exit;
}
$stock_opname = $rows[0];
if ((int) $stock_opname['stock_opname_status'] > 0) {
	echo "<script>alert('Sesi sudah selesai');document.location.href='stock-opname-per-produk';</script>";
	exit;
}
if ((int) $stock_opname['stock_opname_tipe'] !== 0) {
	echo "<script>alert('Gunakan stock opname per produk');document.location.href='stock-opname-per-produk';</script>";
	exit;
}

$idB64 = htmlspecialchars($_GET['id'], ENT_QUOTES, 'UTF-8');

$listQ = mysqli_query(
	$conn,
	"SELECT h.soh_id, h.soh_barang_id, h.soh_barang_kode, h.soh_barang_stock_system, h.soh_stock_fisik, h.soh_selisih,
		h.soh_note, h.soh_datetime, IFNULL(h.soh_approved, 0) AS soh_approved,
		b.barang_nama, b.barang_kode, b.barang_stock AS sistem_sekarang
	 FROM stock_opname_hasil h
	 INNER JOIN barang b ON b.barang_id = h.soh_barang_id AND b.barang_cabang = h.soh_barang_cabang
	 WHERE h.soh_stock_opname_id = " . (int) $id . " AND h.soh_barang_cabang = " . (int) $sessionCabang . " AND h.soh_tipe = 0
	 ORDER BY IFNULL(h.soh_approved, 0) ASC, h.soh_id DESC"
);
$lines = [];
if ($listQ) {
	while ($r = mysqli_fetch_assoc($listQ)) {
		$lines[] = $r;
	}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
	<meta name="theme-color" content="#0f766e">
	<title>Daftar SO — Approve</title>
	<link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
	<style>
		* { box-sizing: border-box; }
		body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #f0f4f8; color: #0f172a; padding-bottom: env(safe-area-inset-bottom); }
		.top { background: linear-gradient(135deg, #0f766e, #0e7490); color: #fff; padding: 14px 16px; padding-top: calc(14px + env(safe-area-inset-top)); }
		.top h1 { margin: 0; font-size: 1.05rem; font-weight: 600; }
		.top a { color: #ccfbf1; text-decoration: none; font-size: 0.85rem; }
		.card { background: #fff; margin: 12px; border-radius: 12px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
		.hint { font-size: 0.82rem; color: #475569; line-height: 1.45; margin: 0 0 10px; }
		table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
		th, td { padding: 8px 6px; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
		th { background: #f1f5f9; font-weight: 600; color: #334155; font-size: 0.72rem; text-transform: uppercase; }
		.badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 0.72rem; font-weight: 600; }
		.badge-wait { background: #fef3c7; color: #92400e; }
		.badge-ok { background: #d1fae5; color: #065f46; }
		.btn {
			display: inline-flex; align-items: center; justify-content: center; gap: 6px;
			padding: 8px 12px; border: none; border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer;
		}
		.btn-apv { background: #059669; color: #fff; }
		.btn-apv:disabled { opacity: 0.5; cursor: not-allowed; }
		.btn-add { background: #1e40af; color: #fff; text-decoration: none; margin-top: 10px; display: inline-flex; }
		.num { font-variant-numeric: tabular-nums; }
		.row-actions { white-space: nowrap; }
		#toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 12px 18px; border-radius: 10px; font-size: 0.9rem; display: none; z-index: 50; max-width: 92vw; }
		#toast.err { background: #991b1b; }
	</style>
</head>
<body>
	<div class="top">
		<h1><i class="fa fa-list-check"></i> Daftar hasil scan</h1>
		<p style="margin:8px 0 0;font-size:.82rem;opacity:.95">Sesi #<?= (int) $id; ?> · <?= (int) $sessionCabang < 1 ? 'Pusat' : 'Cabang ' . (int) $sessionCabang; ?></p>
		<p style="margin:10px 0 0;">
			<a href="stock-opname-mobile?id=<?= $idB64; ?>"><i class="fa fa-barcode"></i> Scan / tambah qty</a>
			&nbsp;·&nbsp;
			<a href="stock-opname-per-produk-proses?id=<?= $idB64; ?>"><i class="fa fa-desktop"></i> Desktop</a>
		</p>
	</div>

	<div class="card">
		<p class="hint">
			<strong>Belum approve:</strong> stok sistem di tabel = nilai <em>sekarang</em> di database; selisih = qty SO pending − sistem. Setelah <strong>Approve</strong>, stok barang diubah ke qty fisik pending dan baris terkunci (tidak bisa scan ubah lagi untuk barang itu).<br>
			<strong>Scan ulang barang yang sama</strong> (mode manual di halaman scan): qty yang Anda ketik <em>ditambahkan</em> ke total pending sampai approve.
		</p>
		<a class="btn btn-add" href="stock-opname-mobile?id=<?= $idB64; ?>"><i class="fa fa-plus"></i> Tambah barang (scan / qty)</a>
	</div>

	<div class="card" style="margin-top:0;padding-top:8px;">
		<?php if (empty($lines)) : ?>
			<p style="text-align:center;color:#64748b;padding:20px;">Belum ada barang. Buka <a href="stock-opname-mobile?id=<?= $idB64; ?>">halaman scan</a>.</p>
		<?php else : ?>
			<div style="overflow-x:auto;">
				<table>
					<thead>
						<tr>
							<th>Barang</th>
							<th class="num">Fisik</th>
							<th class="num">Sistem</th>
							<th class="num">Selisih</th>
							<th>Status</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($lines as $L) :
							$ap = (int) $L['soh_approved'];
							$fisik = (int) $L['soh_stock_fisik'];
							$sistemLive = (int) $L['sistem_sekarang'];
							if ($ap === 1) {
								$sistemT = (int) $L['soh_barang_stock_system'];
								$selT = (int) $L['soh_selisih'];
							} else {
								$sistemT = $sistemLive;
								$selT = $fisik - $sistemLive;
							}
							?>
							<tr data-soh="<?= (int) $L['soh_id'] ?>">
								<td>
									<strong><?= htmlspecialchars($L['barang_nama'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
									<small style="color:#64748b"><?= htmlspecialchars($L['barang_kode'], ENT_QUOTES, 'UTF-8'); ?></small>
								</td>
								<td class="num"><?= $fisik; ?></td>
								<td class="num"><?= $sistemT; ?></td>
								<td class="num"><?= $selT >= 0 ? '+' : '' ?><?= $selT; ?></td>
								<td>
									<?php if ($ap === 1) : ?>
										<span class="badge badge-ok">Disetujui</span>
									<?php else : ?>
										<span class="badge badge-wait">Menunggu</span>
									<?php endif; ?>
								</td>
								<td class="row-actions">
									<?php if ($ap !== 1) : ?>
										<button type="button" class="btn btn-apv btn-do-approve" data-soh-id="<?= (int) $L['soh_id']; ?>">
											<i class="fa fa-check"></i> Approve
										</button>
									<?php else : ?>
										<span style="color:#94a3b8;font-size:.75rem">Terkunci</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<div id="toast"></div>

	<script>
	(function () {
		var toast = document.getElementById('toast');
		function showToast(msg, isErr) {
			toast.textContent = msg;
			toast.className = isErr ? 'err' : '';
			toast.style.display = 'block';
			clearTimeout(showToast._t);
			showToast._t = setTimeout(function () { toast.style.display = 'none'; }, 3200);
		}
		document.querySelectorAll('.btn-do-approve').forEach(function (btn) {
			btn.addEventListener('click', async function () {
				var id = this.getAttribute('data-soh-id');
				if (!id || this.disabled) return;
				if (!confirm('Approve baris ini? Stok sistem akan diset sama dengan stok fisik pending.')) return;
				this.disabled = true;
				try {
					var body = new URLSearchParams({ action: 'approve', soh_id: id });
					var res = await fetch('stock-opname-mobile-api.php', {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						credentials: 'same-origin',
						body: body
					});
					var text = await res.text();
					var j;
					try {
						j = JSON.parse(text);
					} catch (parseErr) {
						var hint = text.replace(/\s+/g, ' ').trim().slice(0, 120);
						showToast('Bukan JSON (HTTP ' + res.status + '): ' + (hint || 'kosong'), true);
						this.disabled = false;
						return;
					}
					if (j.ok) {
						showToast(j.message || 'Berhasil', false);
						location.reload();
					} else {
						showToast(j.message || 'Gagal', true);
						this.disabled = false;
					}
				} catch (e) {
					showToast('Gagal menghubungi server', true);
					this.disabled = false;
				}
			});
		});
	})();
	</script>
</body>
</html>
