<?php
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php';
  require_once __DIR__ . '/../../../aksi/marketplace-lib.php';
?>
<?php
  if ($levelLogin !== 'kurir') {
    echo "<script>document.location.href = 'bo';</script>";
    exit;
  }

  $typeKurir = (int) $_SESSION['user_id'];
  $cfg = marketplace_load_config();
  $belanjaPdo = marketplace_belanja_pdo($cfg);
  $flash = null;

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_status') {
      $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
      $status = (int) ($_POST['status'] ?? 0);
      $owned = query(
          "SELECT invoice_id FROM invoice
           WHERE invoice_id = $invoiceId
             AND invoice_kurir = $typeKurir
             AND invoice_cabang = " . (int) $sessionCabang . '
           LIMIT 1'
      );

      if (!in_array($status, [1, 2, 3, 4], true) || $owned === []) {
          $flash = ['success' => false, 'message' => 'Pesanan ini bukan tugas kamu.'];
      } else {
          $saved = editStatusKurir([
              'invoice_id' => $invoiceId,
              'invoice_status_kurir' => $status,
          ]);
          if ($saved < 0) {
              $flash = ['success' => false, 'message' => 'Status invoice gagal disimpan.'];
          } else {
              marketplace_sync_tracking_for_invoice($conn, $belanjaPdo, $invoiceId);
              $labels = [1 => 'Siap diambil', 2 => 'Sedang diantar', 3 => 'Sudah sampai', 4 => 'Pengiriman gagal'];
              echo "<script>document.location.href='kurir-data?ok=" . urlencode($labels[$status]) . "';</script>";
              exit;
          }
      }
  }

  if (isset($_GET['ok'])) {
      $flash = ['success' => true, 'message' => 'Status diperbarui: ' . (string) $_GET['ok']];
  }

  $jobs = marketplace_fetch_kurir_jobs($conn, $belanjaPdo, $typeKurir, (int) $sessionCabang);
  $earn = marketplace_kurir_earnings($conn, $typeKurir, (int) $sessionCabang, $belanjaPdo);
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1>Tugas antar <b><?= htmlspecialchars((string) $_SESSION['user_nama'], ENT_QUOTES, 'UTF-8'); ?></b></h1>
          <p class="text-muted mb-0">Pesanan yang admin tugaskan ke kamu. Status di sini sama dengan invoice POS dan situs belanja.</p>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Kurir</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <?php if (!$belanjaPdo) { ?>
        <div class="alert alert-warning">Koneksi belanja online belum diatur. Alamat pesanan online bisa kosong, dan status pelanggan tidak ikut berubah.</div>
      <?php } ?>
      <?php if ($flash) { ?>
        <div class="alert alert-<?= !empty($flash['success']) ? 'success' : 'danger'; ?>">
          <?= htmlspecialchars($flash['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php } ?>

      <div class="row">
        <div class="col-md-3 col-6">
          <div class="info-box">
            <span class="info-box-icon bg-warning"><i class="fas fa-box"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Siap diambil</span>
              <span class="info-box-number"><?= (int) $earn['packing']; ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-motorcycle"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Sedang diantar</span>
              <span class="info-box-number"><?= (int) $earn['jalan']; ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Selesai hari ini</span>
              <span class="info-box-number"><?= (int) $earn['today_count']; ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fas fa-wallet"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Upah hari ini</span>
              <span class="info-box-number"><?= !empty($earn['unavailable']) ? 'Belum tersedia' : 'Rp '.number_format((int) $earn['today_fee'], 0, ',', '.'); ?></span>
            </div>
          </div>
        </div>
      </div>

      <p class="text-muted">
        Upah pilot Nugrosir dicatat per perjalanan setelah admin menutup perjalanan, tanpa potongan subsidi toko.
        Pesanan lama memakai ongkir invoice selesai. Angka belum tersedia jika koneksi belanja terputus.
        <?php if (empty($earn['unavailable'])) { ?>Bulan ini Rp <?= number_format((int) $earn['month_fee'], 0, ',', '.'); ?>,
        total Rp <?= number_format((int) $earn['total_fee'], 0, ',', '.'); ?>.<?php } ?>
      </p>

      <?php if ($jobs === []) { ?>
        <div class="card">
          <div class="card-body">
            <h5>Belum ada pesanan ditugaskan</h5>
            <p class="mb-2">Login kurir hanya menampilkan invoice yang kolom kurirnya adalah akun ini. Pesanan belanja online yang baru masuk invoice masih tanpa kurir, jadi tidak muncul di sini.</p>
            <ol class="mb-0">
              <li>Pelanggan memesan di belanja online.</li>
              <li>Admin membuka Penjualan → Belanja Online → Pesanan, lalu di kartu Pemantauan pengiriman memilih nama kurir dan menyimpan.</li>
              <li>Tugas muncul di halaman ini. Tekan Antar sekarang, lalu Sudah sampai setelah barang diterima.</li>
            </ol>
          </div>
        </div>
      <?php } else { ?>
        <?php foreach ($jobs as $job) {
            $status = (int) ($job['invoice_status_kurir'] ?? 0);
            $badges = [
                1 => ['Siap diambil', 'badge-warning'],
                2 => ['Sedang diantar', 'badge-info'],
                3 => ['Sudah sampai', 'badge-success'],
                4 => ['Gagal', 'badge-danger'],
            ];
            $badge = $badges[$status] ?? ['Tanpa status', 'badge-secondary'];
            $phone = preg_replace('/\D+/', '', (string) ($job['customer_tlpn'] ?? ''));
            if (str_starts_with($phone, '0')) {
                $phone = '62' . substr($phone, 1);
            } elseif ($phone !== '' && !str_starts_with($phone, '62')) {
                $phone = '62' . $phone;
            }
            $alamat = trim((string) ($job['customer_alamat'] ?? ''));
            $fee = (int) ($job['courier_pay'] ?? $job['invoice_ongkir'] ?? 0);
            $total = (int) ($job['grand_total'] ?? $job['invoice_sub_total'] ?? 0);
            $isCod = strtolower((string) ($job['payment_method'] ?? '')) === 'cod';
            $invoiceToken = base64_encode((string) $job['invoice_id']);
            ?>
          <div class="card">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div>
                  <h5 class="mb-1">
                    <?= htmlspecialchars((string) ($job['order_number'] ?? $job['invoice_marketplace'] ?? 'Pesanan toko'), ENT_QUOTES, 'UTF-8'); ?>
                    <span class="badge <?= $badge[1]; ?>"><?= $badge[0]; ?></span>
                  </h5>
                  <div class="text-muted">Invoice <?= htmlspecialchars((string) $job['penjualan_invoice'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="text-right">
                  <div><strong><?= !$belanjaPdo && !empty($job['invoice_marketplace']) ? 'Upah belum dapat diverifikasi' : 'Upah Rp '.number_format($fee, 0, ',', '.'); ?></strong></div>
                  <?php if (isset($job['shipping_trip_id'])) { ?><div>Alokasi perjalanan #<?= (int) $job['shipping_trip_id'] ?> · <?= $job['trip_settled'] ? 'Sudah dicatat' : 'Menunggu penutupan admin' ?></div><?php } ?>
                  <small class="text-muted"><?= htmlspecialchars((string) ($job['invoice_tgl'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
              </div>

              <p class="mt-3 mb-1"><strong><?= htmlspecialchars((string) ($job['customer_nama'] ?? 'Pelanggan'), ENT_QUOTES, 'UTF-8'); ?></strong></p>
              <p class="mb-2"><?= htmlspecialchars($alamat !== '' ? $alamat : 'Alamat belum diisi', ENT_QUOTES, 'UTF-8'); ?></p>

              <?php if (!empty($job['items'])) { ?>
                <ul class="mb-2 pl-3">
                  <?php foreach ($job['items'] as $item) { ?>
                    <li><?= htmlspecialchars((string) $item['barang_nama'], ENT_QUOTES, 'UTF-8'); ?> × <?= (int) $item['qty']; ?></li>
                  <?php } ?>
                </ul>
              <?php } ?>

              <?php if ($isCod && $status !== 3) { ?>
                <div class="alert alert-warning py-2">Tagih COD ke pelanggan: <strong>Rp <?= number_format($total, 0, ',', '.'); ?></strong></div>
              <?php } elseif (!$isCod && !empty($job['order_number'])) { ?>
                <p class="text-success mb-2">Pembayaran sudah lewat toko. Tidak perlu menagih barang.</p>
              <?php } ?>

              <div class="mt-2">
                <?php if ($phone !== '') { ?>
                  <a class="btn btn-success btn-sm" target="_blank" rel="noopener" href="https://api.whatsapp.com/send?phone=<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                  </a>
                <?php } ?>
                <?php if ($alamat !== '') { ?>
                  <a class="btn btn-info btn-sm" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode($alamat); ?>">
                    <i class="fas fa-map-marker-alt"></i> Maps
                  </a>
                <?php } ?>
                <a class="btn btn-warning btn-sm" target="_blank" href="nota-cetak?no=<?= (int) $job['invoice_id']; ?>-no-invoice-<?= htmlspecialchars((string) $job['penjualan_invoice'], ENT_QUOTES, 'UTF-8'); ?>">
                  <i class="fas fa-print"></i> Nota
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="kurir-data-edit?id=<?= htmlspecialchars($invoiceToken, ENT_QUOTES, 'UTF-8'); ?>">
                  Ubah status
                </a>
              </div>

              <div class="mt-3">
                <?php if ($status === 1) { ?>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="invoice_id" value="<?= (int) $job['invoice_id']; ?>">
                    <input type="hidden" name="status" value="2">
                    <button class="btn btn-primary" type="submit">Antar sekarang</button>
                  </form>
                <?php } elseif ($status === 2) { ?>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="invoice_id" value="<?= (int) $job['invoice_id']; ?>">
                    <input type="hidden" name="status" value="3">
                    <button class="btn btn-success" type="submit">Sudah sampai</button>
                  </form>
                  <form method="post" class="d-inline" onsubmit="return confirm('Tandai pengiriman ini gagal?');">
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="invoice_id" value="<?= (int) $job['invoice_id']; ?>">
                    <input type="hidden" name="status" value="4">
                    <button class="btn btn-danger" type="submit">Gagal antar</button>
                  </form>
                <?php } elseif ($status === 4) { ?>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="invoice_id" value="<?= (int) $job['invoice_id']; ?>">
                    <input type="hidden" name="status" value="1">
                    <button class="btn btn-warning" type="submit">Kembali ke siap diambil</button>
                  </form>
                <?php } else { ?>
                  <span class="text-success">Upah Rp <?= number_format($fee, 0, ',', '.'); ?> masuk ke pendapatan.</span>
                  <?php if (!empty($job['invoice_date_selesai_kurir']) && $job['invoice_date_selesai_kurir'] !== '-') { ?>
                    <div class="text-muted">Terkirim <?= htmlspecialchars((string) $job['invoice_date_selesai_kurir'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php } ?>
                <?php } ?>
              </div>
            </div>
          </div>
        <?php } ?>
      <?php } ?>
    </div>
  </section>
</div>

<?php include '_footer.php'; ?>
