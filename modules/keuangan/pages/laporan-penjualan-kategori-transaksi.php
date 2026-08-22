<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin === "kasir" || $levelLogin === "kurir") {
  echo "<script>document.location.href = 'bo';</script>";
  exit;
}

@set_time_limit(120);
@ini_set('max_execution_time', '120');
@ini_set('memory_limit', '512M');

require_once numart_path('aksi/laporan-penjualan-kategori-lib.php');

$cabEsc = (int) $sessionCabang;
$barangId = (int) ($_GET['barang_id'] ?? $_POST['barang_id'] ?? 0);
$kategoriId = (int) ($_GET['kategori_id'] ?? $_POST['kategori_id'] ?? 0);

[$tanggalAwal, $tanggalAkhir] = laporanKategori_normalisasiPeriode(
  $_GET['tanggal_awal'] ?? $_POST['tanggal_awal'] ?? null,
  $_GET['tanggal_akhir'] ?? $_POST['tanggal_akhir'] ?? null
);

$statusFilter = isset($_GET['status']) ? (string) $_GET['status'] : (isset($_POST['status']) ? (string) $_POST['status'] : 'semua');
if (!in_array($statusFilter, ['semua', 'rugi', 'untung', 'impas'], true)) {
  $statusFilter = 'semua';
}

$hasil = laporanKategori_ambilDataTransaksiBarang(
  $conn,
  $cabEsc,
  $tanggalAwal,
  $tanggalAkhir,
  $barangId,
  $statusFilter
);

$barang = $hasil['barang'];
if (!$barang) {
  echo "<script>alert('Barang tidak ditemukan'); document.location.href='laporan-penjualan-kategori';</script>";
  exit;
}

if ($kategoriId < 1) {
  $kategoriId = (int) ($barang['kategori_id'] ?? 0);
}
$namaKategori = laporanKategori_namaKategori($conn, $kategoriId);

$dataTrx        = $hasil['rows'];
$totalPenjualan = $hasil['penjualan'];
$totalHpp       = $hasil['hpp'];
$totalLaba      = $hasil['laba'];
$totalQty       = $hasil['qty'];
$totalTrx       = $hasil['transaksi'];
$marginTotal    = $hasil['margin'];
$jmlRugi        = $hasil['jml_rugi'];
$jmlUntung      = $hasil['jml_untung'];

function lpktRupiah($n)
{
  return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function lpktPersen($n, $desimal = 2)
{
  return number_format((float) $n, $desimal, ',', '.') . '%';
}

$qsDetail = http_build_query([
  'kategori_id'   => $kategoriId,
  'tanggal_awal'  => $tanggalAwal,
  'tanggal_akhir' => $tanggalAkhir,
]);

$kodeBarang = (string) ($barang['barang_kode'] ?? '');
$kodeQs = urlencode($kodeBarang);
$idBarangEdit = urlencode(base64_encode((string) $barangId));
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1>Detail Transaksi Barang</h1>
          <p class="text-muted mb-0">
            <code><?= htmlspecialchars((string) $barang['barang_kode'], ENT_QUOTES, 'UTF-8'); ?></code>
            —
            <strong><?= htmlspecialchars((string) $barang['barang_nama'], ENT_QUOTES, 'UTF-8'); ?></strong>
            <br>
            Kategori: <?= htmlspecialchars($namaKategori, ENT_QUOTES, 'UTF-8'); ?>
            &middot; <?= htmlspecialchars(date('d/m/Y', strtotime($tanggalAwal)), ENT_QUOTES, 'UTF-8'); ?>
            &ndash; <?= htmlspecialchars(date('d/m/Y', strtotime($tanggalAkhir)), ENT_QUOTES, 'UTF-8'); ?>
          </p>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="laporan-penjualan-kategori">Kategori</a></li>
            <li class="breadcrumb-item"><a href="laporan-penjualan-kategori-detail?<?= htmlspecialchars($qsDetail, ENT_QUOTES, 'UTF-8'); ?>">Barang</a></li>
            <li class="breadcrumb-item active">Transaksi</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">

      <div class="mb-3 d-flex flex-wrap" style="gap:8px;">
        <a href="laporan-penjualan-kategori-detail?<?= htmlspecialchars($qsDetail, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-default">
          <i class="fa fa-arrow-left"></i> Kembali ke List Barang
        </a>
      </div>

      <?php
        $flashHpp = $_SESSION['hpp_perbaikan_flash'] ?? null;
        unset($_SESSION['hpp_perbaikan_flash']);
        $isGudangHpp = ((int) $sessionCabang === 0) || ($levelLogin === 'super admin');
      ?>
      <?php if (is_array($flashHpp)) : ?>
        <div class="alert alert-<?= htmlspecialchars((string) ($flashHpp['tipe'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?>">
          <?= htmlspecialchars((string) ($flashHpp['pesan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <div class="card card-outline card-warning mb-3">
        <div class="card-header py-2">
          <h3 class="card-title mb-0"><i class="fas fa-tools"></i> Perbaikan untuk barang ini</h3>
        </div>
        <div class="card-body py-3">
          <?php if ($isGudangHpp) : ?>
            <p class="mb-2 text-muted">
              Kode <code><?= htmlspecialchars($kodeBarang, ENT_QUOTES, 'UTF-8'); ?></code> sudah diisi otomatis di tool perbaikan.
              Jika HPP/pcs jauh di atas harga jual, mulai dari konversi satuan.
            </p>
            <div class="btn-group flex-wrap">
              <a href="perbaiki-hpp-ganti-satuan?kode=<?= $kodeQs; ?>" target="_blank" class="btn btn-sm btn-warning">
                <i class="fas fa-exchange-alt"></i> HPP ganti satuan
              </a>
              <a href="perbaiki-hpp-barang?kode=<?= $kodeQs; ?>" target="_blank" class="btn btn-sm btn-primary">
                <i class="fas fa-sync"></i> Perbaiki HPP barang
              </a>
              <a href="barang-edit?id=<?= $idBarangEdit; ?>" target="_blank" class="btn btn-sm btn-secondary">
                <i class="fa fa-edit"></i> Edit master barang
              </a>
              <a href="edit-transaksi-pembelian" target="_blank" class="btn btn-sm btn-success">
                <i class="fas fa-shopping-cart"></i> Edit pembelian
              </a>
              <a href="edit-transaksi" target="_blank" class="btn btn-sm btn-info">
                <i class="fas fa-file-invoice"></i> Edit penjualan
              </a>
              <?php
                $qsKoreksi = http_build_query([
                  'kode' => $kodeBarang,
                  'tanggal_awal' => $tanggalAwal,
                  'tanggal_akhir' => $tanggalAkhir,
                  'cabang' => (int) $sessionCabang,
                ]);
              ?>
              <a href="hpp-perbaikan-gudang?<?= htmlspecialchars($qsKoreksi, ENT_QUOTES, 'UTF-8'); ?>#koreksi" class="btn btn-sm btn-danger">
                <i class="fas fa-history"></i> Koreksi histori
              </a>
            </div>
          <?php else : ?>
            <p class="mb-2 text-muted">
              Toko tidak mengedit pembelian / satuan. Kirim permintaan ke gudang untuk barcode
              <code><?= htmlspecialchars($kodeBarang, ENT_QUOTES, 'UTF-8'); ?></code>.
            </p>
            <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#modalMintaHppTrx">
              <i class="fas fa-paper-plane"></i> Minta perbaikan ke gudang
            </button>
            <a href="hpp-perbaikan-toko" class="btn btn-sm btn-default">Status permintaan</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="card card-default">
        <div class="card-header">
          <h3 class="card-title">Filter Transaksi</h3>
        </div>
        <form method="get" action="laporan-penjualan-kategori-transaksi">
          <input type="hidden" name="barang_id" value="<?= $barangId; ?>">
          <input type="hidden" name="kategori_id" value="<?= $kategoriId; ?>">
          <div class="card-body">
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label>Tanggal Awal</label>
                  <input type="date" name="tanggal_awal" class="form-control"
                    value="<?= htmlspecialchars($tanggalAwal, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Tanggal Akhir</label>
                  <input type="date" name="tanggal_akhir" class="form-control"
                    value="<?= htmlspecialchars($tanggalAkhir, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Status Laba</label>
                  <select name="status" class="form-control">
                    <option value="semua" <?= $statusFilter === 'semua' ? 'selected' : ''; ?>>Semua transaksi</option>
                    <option value="rugi" <?= $statusFilter === 'rugi' ? 'selected' : ''; ?>>Hanya rugi</option>
                    <option value="untung" <?= $statusFilter === 'untung' ? 'selected' : ''; ?>>Hanya untung</option>
                    <option value="impas" <?= $statusFilter === 'impas' ? 'selected' : ''; ?>>Impas (0)</option>
                  </select>
                </div>
              </div>
              <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-block">
                  <i class="fa fa-filter"></i> Terapkan
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>

      <div class="row">
        <div class="col-lg-3 col-6">
          <div class="small-box bg-info">
            <div class="inner">
              <h4><?= number_format($totalTrx, 0, ',', '.'); ?></h4>
              <p>Baris transaksi</p>
            </div>
            <div class="icon"><i class="fas fa-receipt"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box <?= $totalLaba >= 0 ? 'bg-success' : 'bg-danger'; ?>">
            <div class="inner">
              <h4><?= lpktRupiah($totalLaba); ?></h4>
              <p>Laba kotor (filter aktif)</p>
            </div>
            <div class="icon"><i class="fas fa-balance-scale"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-danger">
            <div class="inner">
              <h4><?= number_format($jmlRugi, 0, ',', '.'); ?></h4>
              <p>Transaksi rugi</p>
            </div>
            <div class="icon"><i class="fas fa-arrow-down"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-success">
            <div class="inner">
              <h4><?= number_format($jmlUntung, 0, ',', '.'); ?></h4>
              <p>Transaksi untung</p>
            </div>
            <div class="icon"><i class="fas fa-arrow-up"></i></div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">List Transaksi / Nota</h3>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table id="tabel-trx-barang" class="table table-bordered table-striped table-hover">
              <thead>
                <tr>
                  <th style="width:4%;">No</th>
                  <th style="width:70px;" class="text-center">Nota</th>
                  <th>Invoice</th>
                  <th>Tanggal</th>
                  <th class="text-right">QTY</th>
                  <th>Satuan</th>
                  <th class="text-right">Harga Jual</th>
                  <th class="text-right">HPP/pcs</th>
                  <th class="text-right">Penjualan</th>
                  <th class="text-right">HPP</th>
                  <th class="text-right">Laba</th>
                  <th class="text-center">Margin</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($dataTrx)) : ?>
                  <tr>
                    <td colspan="12" class="text-center text-muted py-4">
                      Tidak ada transaksi pada filter/periode ini.
                    </td>
                  </tr>
                <?php else : ?>
                  <?php $no = 1; foreach ($dataTrx as $row) :
                    $laba = (float) $row['laba_kotor'];
                    $margin = (float) $row['margin'];
                    $invoiceId = (int) ($row['invoice_id'] ?? 0);
                  ?>
                    <tr class="<?= $laba < 0 ? 'table-danger' : ''; ?>">
                      <td><?= $no++; ?></td>
                      <td class="text-center">
                        <?php if ($invoiceId > 0) : ?>
                          <a href="penjualan-zoom?no=<?= urlencode(base64_encode((string) $invoiceId)); ?>"
                             class="btn btn-xs btn-info"
                             target="_blank"
                             title="Buka invoice">
                            <i class="fa fa-eye"></i>
                          </a>
                        <?php else : ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td><code><?= htmlspecialchars((string) $row['penjualan_invoice'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                      <td><?= htmlspecialchars(date('d/m/Y', strtotime((string) $row['penjualan_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                      <td class="text-right"><?= number_format((float) $row['barang_qty_keranjang'], 0, ',', '.'); ?></td>
                      <td><?= htmlspecialchars((string) ($row['keranjang_satuan'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                      <td class="text-right"><?= lpktRupiah($row['keranjang_harga']); ?></td>
                      <td class="text-right"><?= lpktRupiah($row['keranjang_harga_beli']); ?></td>
                      <td class="text-right"><?= lpktRupiah($row['penjualan']); ?></td>
                      <td class="text-right"><?= lpktRupiah($row['hpp']); ?></td>
                      <td class="text-right <?= $laba >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <strong><?= lpktRupiah($laba); ?></strong>
                      </td>
                      <td class="text-center">
                        <span class="badge <?= $margin >= 0 ? 'badge-success' : 'badge-danger'; ?>">
                          <?= lpktPersen($margin); ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
              <?php if (!empty($dataTrx)) : ?>
                <tfoot>
                  <tr class="bg-light">
                    <th colspan="4">TOTAL</th>
                    <th class="text-right"><?= number_format($totalQty, 0, ',', '.'); ?></th>
                    <th colspan="3"></th>
                    <th class="text-right"><?= lpktRupiah($totalPenjualan); ?></th>
                    <th class="text-right"><?= lpktRupiah($totalHpp); ?></th>
                    <th class="text-right <?= $totalLaba >= 0 ? 'text-success' : 'text-danger'; ?>">
                      <?= lpktRupiah($totalLaba); ?>
                    </th>
                    <th class="text-center"><?= lpktPersen($marginTotal); ?></th>
                  </tr>
                </tfoot>
              <?php endif; ?>
            </table>
          </div>
          <small class="text-muted">
            Baris merah = transaksi rugi (harga jual di bawah HPP). Filter “Hanya rugi” mempercepat pelacakan penyebab kerugian.
          </small>
        </div>
      </div>

    </div>
  </section>
</div>

<div class="modal fade" id="modalMintaHppTrx" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="aksi/hpp-perbaikan-request.php" class="modal-content">
      <?php
        $redirectTrx = 'laporan-penjualan-kategori-transaksi?' . http_build_query([
          'barang_id' => $barangId,
          'kategori_id' => $kategoriId,
          'tanggal_awal' => $tanggalAwal,
          'tanggal_akhir' => $tanggalAkhir,
          'status' => $statusFilter,
        ]);
      ?>
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTrx, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="barang_id" value="<?= (int) $barangId; ?>">
      <input type="hidden" name="barang_kode" value="<?= htmlspecialchars($kodeBarang, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="barang_nama" value="<?= htmlspecialchars((string) ($barang['barang_nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="tanggal_awal" value="<?= htmlspecialchars($tanggalAwal, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="tanggal_akhir" value="<?= htmlspecialchars($tanggalAkhir, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="ringkas_penjualan" value="<?= (float) $totalPenjualan; ?>">
      <input type="hidden" name="ringkas_hpp" value="<?= (float) $totalHpp; ?>">
      <input type="hidden" name="ringkas_laba" value="<?= (float) $totalLaba; ?>">
      <input type="hidden" name="jml_trx" value="<?= (int) $totalTrx; ?>">
      <input type="hidden" name="jml_trx_rugi" value="<?= (int) $jmlRugi; ?>">
      <div class="modal-header">
        <h5 class="modal-title">Minta perbaikan HPP ke gudang</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p class="mb-2">
          Barang: <strong><?= htmlspecialchars((string) ($barang['barang_nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong><br>
          Kode: <code><?= htmlspecialchars($kodeBarang, ENT_QUOTES, 'UTF-8'); ?></code>
        </p>
        <div class="form-group">
          <label>Catatan untuk gudang</label>
          <textarea name="catatan" class="form-control" rows="3" required
            placeholder="Contoh: banyak nota rugi karena HPP/pcs tidak wajar."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-warning">
          <i class="fas fa-paper-plane"></i> Kirim
        </button>
      </div>
    </form>
  </div>
</div>

<?php include '_footer.php'; ?>
<script>
  $(function () {
    <?php if (!empty($dataTrx)) : ?>
      $('#tabel-trx-barang').DataTable({
        paging: true,
        pageLength: 50,
        searching: true,
        ordering: false,
        info: true,
        language: {
          search: 'Cari invoice:',
          zeroRecords: 'Transaksi tidak ditemukan',
          lengthMenu: 'Tampil _MENU_',
          info: 'Menampilkan _START_–_END_ dari _TOTAL_ transaksi',
          paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
        }
      });
    <?php endif; ?>
  });
</script>
