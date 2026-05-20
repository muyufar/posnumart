<?php
include '_header-artibut.php';

if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
	echo "<script>document.location.href='bo';</script>";
	exit;
}

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
	<meta name="theme-color" content="#1e3a5f">
	<title>Stock Opname Mobile</title>
	<link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
	<style>
		* { box-sizing: border-box; }
		body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #f0f2f5; color: #1a1a1a; padding-bottom: env(safe-area-inset-bottom); }
		.top { background: linear-gradient(135deg, #1e3a5f, #2c5282); color: #fff; padding: 14px 16px; padding-top: calc(14px + env(safe-area-inset-top)); }
		.top h1 { margin: 0; font-size: 1.1rem; font-weight: 600; }
		.top a { color: #bde0ff; text-decoration: none; font-size: 0.85rem; }
		.card { background: #fff; margin: 12px; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
		label { display: block; font-size: 0.8rem; color: #555; margin-bottom: 6px; font-weight: 500; }
		input[type="text"], input[type="number"], textarea {
			width: 100%; font-size: 1.1rem; padding: 12px 14px; border: 1px solid #ccc; border-radius: 10px;
		}
		input:focus, textarea:focus { outline: none; border-color: #2c5282; }
		.row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 12px; }
		.btn {
			display: inline-flex; align-items: center; justify-content: center; gap: 8px;
			padding: 14px 18px; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer;
			flex: 1; min-width: 120px;
		}
		.btn-primary { background: #2c5282; color: #fff; }
		.btn-primary:disabled { opacity: 0.55; cursor: not-allowed; }
		.btn-scan { background: #0d9488; color: #fff; flex: 1 1 100%; }
		.mode-pick { display: flex; flex-direction: column; gap: 10px; margin: 14px 0; }
		.mode-opt {
			display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 2px solid #e2e8f0;
			border-radius: 10px; cursor: pointer; background: #fafafa;
		}
		.mode-opt.is-on { border-color: #2c5282; background: #eff6ff; }
		.mode-opt input { margin-top: 4px; width: 18px; height: 18px; flex-shrink: 0; }
		.mode-opt .t { font-weight: 600; font-size: 0.95rem; color: #1e293b; }
		.mode-opt .d { font-size: 0.82rem; color: #64748b; margin-top: 4px; line-height: 1.35; }
		#feedback { margin-top: 12px; padding: 12px; border-radius: 8px; font-size: 0.95rem; display: none; }
		#feedback.ok { display: block; background: #d1fae5; color: #065f46; }
		#feedback.err { display: block; background: #fee2e2; color: #991b1b; }
		#lastItem { font-size: 0.9rem; color: #444; margin-top: 8px; }
		.hidden-scan { position: fixed; left: -9999px; opacity: 0; height: 0; width: 0; }
		small.hint { color: #666; font-size: 0.8rem; display: block; margin-top: 6px; }
		#cameraSecureWarn {
			display: none; margin-bottom: 14px; padding: 12px 14px; border-radius: 10px;
			background: #fff7ed; border: 1px solid #fdba74; color: #9a3412; font-size: 0.9rem; line-height: 1.45;
		}
		#cameraSecureWarn strong { display: block; margin-bottom: 6px; }
		.btn-scan[disabled], .btn-scan.is-disabled {
			opacity: 0.55; cursor: not-allowed; background: #94a3b8 !important;
		}
	</style>
</head>
<body>
	<div class="top">
		<h1><i class="fa fa-barcode"></i> Stock Opname (HP)</h1>
		<p style="margin:8px 0 0;font-size:.85rem;opacity:.95">Cabang: <?= (int) $sessionCabang < 1 ? 'Pusat' : 'Cabang ' . (int) $sessionCabang; ?> · Sesi #<?= (int) $id; ?></p>
		<a href="stock-opname-per-produk-proses?id=<?= $idB64; ?>"><i class="fa fa-arrow-left"></i> Kembali ke desktop</a>
		&nbsp;·&nbsp;
		<a href="stock-opname-mobile-list?id=<?= $idB64; ?>"><i class="fa fa-list-ul"></i> Daftar scan &amp; approve</a>
	</div>

	<div class="card">
		<div id="cameraSecureWarn" role="note">
			<strong><i class="fa fa-exclamation-triangle"></i> Kamera tidak bisa dipakai di alamat ini</strong>
			Browser (Chrome/Safari) hanya mengizinkan kamera untuk situs <b>HTTPS</b> atau <b>localhost</b>.
			Akses lewat IP seperti <code>http://10.x.x.x</code> dianggap tidak aman, jadi kamera dipaksa mati — ini normal, bukan rusak.
			<br><br>
			<b>Yang tetap jalan di HP Anda sekarang:</b> ketik kode di kolom bawah, atau gunakan scanner Bluetooth/USB (mode keyboard), lalu Enter / Simpan.
			<br><br>
			<b>Supaya kamera jalan di HP:</b> pasang HTTPS (mis. <b>ngrok</b> / <b>Cloudflare Tunnel</b>) yang mengarah ke Laragon, atau uji kamera hanya dari PC lewat <code>localhost</code>.
		</div>
		<label for="kode">Kode / Barcode</label>
		<input type="text" id="kode" name="kode" autocomplete="off" autocorrect="off" spellcheck="false" placeholder="Scan atau ketik lalu Enter">
		<input type="text" id="scannerSink" class="hidden-scan" autocomplete="off" aria-hidden="true">
		<small class="hint" id="hintScanner">Scanner USB/bluetooth (mode keyboard): setelah scan, ikuti mode di bawah (Enter bisa pindah ke qty atau langsung simpan).</small>

		<div class="mode-pick" role="radiogroup" aria-label="Cara hitung stok fisik">
			<label class="mode-opt">
				<input type="radio" name="countMode" value="manual" checked>
				<span>
					<span class="t">Satu kali scan + qty manual</span>
					<span class="d">Scan atau ketik kode sekali, isi <b>qty yang mau ditambahkan</b> ke hitungan SO (belum approve), lalu Simpan. Scan ulang barang yang sama menambah ke total pending sampai Anda approve di halaman daftar.</span>
				</span>
			</label>
			<label class="mode-opt">
				<input type="radio" name="countMode" value="repeat">
				<span>
					<span class="t">Scan / ketik berulang sesuai qty fisik (+1 tiap simpan)</span>
					<span class="d">Tiap <b>Simpan</b> (atau Enter) untuk barang yang sama menambah stok fisik <b>+1</b>. Ulangi sampai jumlah di rak sesuai — seperti menghitung satu per satu.</span>
				</span>
			</label>
		</div>

		<div id="qtyWrap">
			<label for="stock_fisik">Qty fisik (ditambahkan ke pending jika barang sama belum di-approve)</label>
			<input type="number" id="stock_fisik" min="0" value="1" inputmode="numeric">
		</div>

		<label for="note">Catatan (opsional)</label>
		<textarea id="note" rows="2" placeholder="Opsional"></textarea>

		<div class="row">
			<button type="button" class="btn btn-scan" id="btnCamera"><i class="fa fa-camera"></i> Scan pakai kamera</button>
			<button type="button" class="btn btn-primary" id="btnSave"><i class="fa fa-check"></i> Simpan</button>
		</div>
		<video id="video" playsinline muted style="display:none;width:100%;max-height:220px;border-radius:10px;margin-top:10px;background:#000"></video>
		<div id="feedback" role="status"></div>
		<div id="lastItem"></div>
	</div>

	<script>
	(function () {
		const STOCK_OPNAME_ID = <?= (int) $id; ?>;
		const elKode = document.getElementById('kode');
		const elQty = document.getElementById('stock_fisik');
		const elQtyWrap = document.getElementById('qtyWrap');
		const elHintScan = document.getElementById('hintScanner');
		const elNote = document.getElementById('note');
		const elFb = document.getElementById('feedback');
		const elLast = document.getElementById('lastItem');
		const btnSave = document.getElementById('btnSave');
		const btnCam = document.getElementById('btnCamera');
		const video = document.getElementById('video');
		let stream = null;
		let scanTimer = null;
		let detector = null;

		/** Kamera & getUserMedia: hanya konteks "aman" (HTTPS atau localhost). */
		function canUseCameraSecurely() {
			if (typeof window.isSecureContext === 'boolean') {
				return window.isSecureContext;
			}
			var h = location.hostname;
			return location.protocol === 'https:' || h === 'localhost' || h === '127.0.0.1' || h === '[::1]';
		}

		function isRepeatMode() {
			var r = document.querySelector('input[name="countMode"]:checked');
			return r && r.value === 'repeat';
		}

		function syncModeUi() {
			var rep = isRepeatMode();
			elQtyWrap.style.display = rep ? 'none' : 'block';
			elHintScan.textContent = rep
				? 'Mode berulang: setelah kode terisi, Enter / Simpan menambah +1. Scanner: tiap scan + Enter = +1 per barang yang sama.'
				: 'Mode manual: setelah kode terisi (scan/ketik), Enter memindah ke kolom qty — isi angka lalu Simpan atau Enter di kolom qty.';
			document.querySelectorAll('.mode-opt').forEach(function (lab) {
				var inp = lab.querySelector('input');
				if (inp && inp.checked) lab.classList.add('is-on');
				else lab.classList.remove('is-on');
			});
		}
		document.querySelectorAll('input[name="countMode"]').forEach(function (r) {
			r.addEventListener('change', syncModeUi);
		});
		syncModeUi();

		(function initCameraUi() {
			var warn = document.getElementById('cameraSecureWarn');
			if (!canUseCameraSecurely()) {
				warn.style.display = 'block';
				btnCam.disabled = true;
				btnCam.classList.add('is-disabled');
				btnCam.title = 'Butuh HTTPS atau localhost';
			}
		})();

		function showFb(ok, text) {
			elFb.className = ok ? 'ok' : 'err';
			elFb.textContent = text;
			elFb.style.display = 'block';
		}

		async function saveLine() {
			const kode = elKode.value.trim();
			if (!kode) {
				showFb(false, 'Isi kode / barcode dulu.');
				elKode.focus();
				return;
			}
			const increment = isRepeatMode();
			const stock_fisik = increment ? 0 : parseInt(elQty.value, 10);
			if (!increment && (isNaN(stock_fisik) || stock_fisik < 0)) {
				showFb(false, 'Stok fisik tidak valid.');
				return;
			}
			btnSave.disabled = true;
			const body = new URLSearchParams({
				action: 'save',
				stock_opname_id: String(STOCK_OPNAME_ID),
				kode: kode,
				stock_fisik: String(stock_fisik),
				increment: increment ? '1' : '',
				note: elNote.value.trim()
			});
			try {
				const res = await fetch('stock-opname-mobile-api.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					credentials: 'same-origin',
					body
				});
				const text = await res.text();
				let j;
				try {
					j = JSON.parse(text);
				} catch (parseErr) {
					const hint = text.replace(/\s+/g, ' ').trim().slice(0, 160);
					showFb(false, 'Respon server bukan JSON (HTTP ' + res.status + '). ' + (hint || 'Kosong') + ' — cek login / error PHP.');
					return;
				}
				if (j.ok) {
					showFb(true, (j.message || 'Tersimpan') + ' · ' + (j.barang_nama || ''));
					elLast.innerHTML = '<strong>' + (j.barang_kode || kode) + '</strong> — fisik: <strong>' + j.stock_fisik + '</strong>, sistem: ' + j.stock_sistem + ', selisih: ' + j.selisih;
					elKode.value = '';
					if (!isRepeatMode()) {
						elQty.value = '1';
					}
					elKode.focus();
				} else {
					showFb(false, j.message || 'Gagal menyimpan.');
				}
			} catch (e) {
				showFb(false, 'Gagal menghubungi server (offline / URL salah). Coba lagi.');
			}
			btnSave.disabled = false;
		}

		btnSave.addEventListener('click', saveLine);
		elKode.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter') return;
			e.preventDefault();
			if (isRepeatMode()) {
				saveLine();
			} else {
				if (!elKode.value.trim()) return;
				elQty.focus();
				elQty.select();
			}
		});
		elQty.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				saveLine();
			}
		});

		async function startCameraScan() {
			if (!canUseCameraSecurely()) {
				showFb(false, 'Kamera membutuhkan HTTPS atau localhost. Lewat IP (http://10...) browser memblokir kamera. Pakai ketik kode / scanner Bluetooth, atau tunnel (ngrok) ke HTTPS.');
				return;
			}
			if (!('mediaDevices' in navigator) || !navigator.mediaDevices.getUserMedia) {
				showFb(false, 'Browser ini tidak mengekspos kamera (mediaDevices). Coba Chrome terbaru di Android, atau ketik kode manual.');
				return;
			}
			if (stream) {
				stopCameraScan();
				return;
			}
			try {
				stream = await navigator.mediaDevices.getUserMedia({
					video: { facingMode: { ideal: 'environment' } },
					audio: false
				});
				video.srcObject = stream;
				video.style.display = 'block';
				await video.play();
				btnCam.innerHTML = '<i class="fa fa-stop"></i> Tutup kamera';

				if ('BarcodeDetector' in window) {
					try {
						detector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'upc_a', 'upc_e', 'qr_code'] });
					} catch (e) {
						detector = new BarcodeDetector();
					}
					scanTimer = setInterval(async function () {
						if (!stream || !detector) return;
						try {
							const codes = await detector.detect(video);
							if (codes && codes.length) {
								elKode.value = codes[0].rawValue || '';
								stopCameraScan();
								if (isRepeatMode()) {
									saveLine();
								} else {
									elQty.focus();
									elQty.select();
									showFb(true, 'Kode terisi. Isi qty lalu Simpan.');
								}
							}
						} catch (_) {}
					}, 320);
				} else {
					showFb(false, 'BarcodeDetector tidak tersedia di browser ini. Gunakan Chrome di Android (HTTPS), atau ketik / scanner Bluetooth.');
					stopCameraScan();
				}
			} catch (err) {
				showFb(false, 'Kamera ditolak atau gagal dibuka. Periksa izin kamera di pengaturan browser / App info.');
			}
		}

		function stopCameraScan() {
			if (scanTimer) clearInterval(scanTimer);
			scanTimer = null;
			detector = null;
			if (stream) {
				stream.getTracks().forEach(function (t) { t.stop(); });
				stream = null;
			}
			video.srcObject = null;
			video.style.display = 'none';
			btnCam.innerHTML = '<i class="fa fa-camera"></i> Scan pakai kamera';
		}

		btnCam.addEventListener('click', function () {
			if (stream) stopCameraScan();
			else startCameraScan();
		});

		window.addEventListener('beforeunload', stopCameraScan);
		elKode.focus();
	})();
	</script>
</body>
</html>
