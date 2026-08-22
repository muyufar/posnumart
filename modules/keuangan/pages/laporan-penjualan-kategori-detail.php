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
$kategoriId = (int) ($_GET['kategori_id'] ?? $_POST['kategori_id'] ?? 0);
$namaFromUrl = trim((string) ($_GET['nama'] ?? $_POST['nama'] ?? ''));

[$tanggalAwal, $tanggalAkhir] = laporanKategori_normalisasiPeriode(
  $_GET['tanggal_awal'] ?? $_POST['tanggal_awal'] ?? null,
  $_GET['tanggal_akhir'] ?? $_POST['tanggal_akhir'] ?? null
);

$statusFilter = isset($_GET['status']) ? (string) $_GET['status'] : (isset($_POST['status']) ? (string) $_POST['status'] : 'semua');
if (!in_array($statusFilter, ['semua', 'rugi', 'untung', 'impas'], true)) {
  $statusFilter = 'semua';
}

$urutkan = isset($_GET['urutkan']) ? (string) $_GET['urutkan'] : (isset($_POST['urutkan']) ? (string) $_POST['urutkan'] : 'laba');
$allowedUrut = ['laba', 'laba_desc', 'penjualan', 'margin', 'qty', 'nama', 'nama_desc'];
if (!in_array($urutkan, $allowedUrut, true)) {
  $urutkan = 'laba';
}

$namaKategori = $namaFromUrl !== '' ? $namaFromUrl : laporanKategori_namaKategori($conn, $kategoriId);
$hasil = laporanKategori_ambilDataBarang(
  $conn,
  $cabEsc,
  $tanggalAwal,
  $tanggalAkhir,
  $kategoriId,
  $statusFilter,
  $urutkan
);

$dataBarang     = $hasil['rows'];
$totalPenjualan = $hasil['penjualan'];
$totalHpp       = $hasil['hpp'];
$totalLaba      = $hasil['laba'];
$totalQty       = $hasil['qty'];
$totalProduk    = $hasil['produk'];
$marginTotal    = $hasil['margin'];
$marginTerbesar = $hasil['margin_terbesar'];
$jmlRugi        = $hasil['jml_rugi'];
$jmlUntung      = $hasil['jml_untung'];

function lpkdRupiah($n)
{
  return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function lpkdPersen($n, $desimal = 2)
{
  return number_format((float) $n, $desimal, ',', '.') . '%';
}
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1>Detail Barang per Kategori</h1>
          <p class="text-muted mb-0">
            Kategori: <strong><?= htmlspecialchars($namaKategori, ENT_QUOTES, 'UTF-8'); ?></strong>
            &middot; <?= htmlspecialchars(date('d/m/Y', strtotime($tanggalAwal)), ENT_QUOTES, 'UTF-8'); ?>
            &ndash; <?= htmlspecialchars(date('d/m/Y', strtotime($tanggalAkhir)), ENT_QUOTES, 'UTF-8'); ?>
          </p>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="laporan-penjualan-kategori">Penjualan Per Kategori</a></li>
            <li class="breadcrumb-item active">Detail Barang</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">

      <div class="mb-3 d-flex flex-wrap" style="gap:8px;">
        <a href="laporan-penjualan-kategori" class="btn btn-default">
          <i class="fa fa-arrow-left"></i> Kembali ke Ringkasan Kategori
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
          <h3 class="card-title mb-0"><i class="fas fa-tools"></i> Opsi Perbaikan (jika ada yang rugi tidak wajar)</h3>
        </div>
        <div class="card-body py-3">
          <?php if ($isGudangHpp) : ?>
            <p class="mb-2 text-muted">
              Pilih sesuai penyebab: salah input pembelian, perubahan satuan, atau HPP master tidak sinkron.
              Tombol per baris memakai barcode barang terkait.
            </p>
            <div class="btn-group flex-wrap mb-2">
              <a href="perbaiki-hpp-ganti-satuan" target="_blank" class="btn btn-sm btn-warning">
                <i class="fas fa-exchange-alt"></i> Perbaiki HPP ganti satuan
              </a>
              <a href="perbaiki-hpp-barang" target="_blank" class="btn btn-sm btn-primary">
                <i class="fas fa-sync"></i> Perbaiki HPP barang
              </a>
              <a href="edit-transaksi-pembelian" target="_blank" class="btn btn-sm btn-success">
                <i class="fas fa-shopping-cart"></i> Edit transaksi pembelian
              </a>
              <a href="edit-transaksi" target="_blank" class="btn btn-sm btn-info">
                <i class="fas fa-edit"></i> Edit transaksi penjualan
              </a>
              <a href="hpp-perbaikan-gudang" class="btn btn-sm btn-danger">
                <i class="fas fa-inbox"></i> Panel permintaan &amp; koreksi histori
              </a>
            </div>
            <small class="text-muted d-block">
              Tips: jika HPP jauh di atas harga jual (mis. ×6 / ×12), kemungkinan besar salah satuan — mulai dari <strong>Perbaiki HPP ganti satuan</strong>.
            </small>
          <?php else : ?>
            <p class="mb-2 text-muted">
              Toko tidak mengedit pembelian / satuan. Kirim permintaan ke gudang (barcode + periode).
              Gunakan tombol <i class="fas fa-paper-plane"></i> di setiap baris barang bermasalah.
            </p>
            <a href="hpp-perbaikan-toko" class="btn btn-sm btn-warning">
              <i class="fas fa-clipboard-list"></i> Lihat status permintaan saya
            </a>
          <?php endif; ?>
        </div>
      </div>

      <div class="card card-default">
        <div class="card-header">
          <h3 class="card-title">Filter Tracking Untung / Rugi</h3>
        </div>
        <form method="get" action="laporan-penjualan-kategori-detail">
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
                    <option value="semua" <?= $statusFilter === 'semua' ? 'selected' : ''; ?>>Semua barang</option>
                    <option value="rugi" <?= $statusFilter === 'rugi' ? 'selected' : ''; ?>>Hanya rugi</option>
                    <option value="untung" <?= $statusFilter === 'untung' ? 'selected' : ''; ?>>Hanya untung</option>
                    <option value="impas" <?= $statusFilter === 'impas' ? 'selected' : ''; ?>>Impas (0)</option>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Urutkan</label>
                  <select name="urutkan" class="form-control">
                    <option value="laba" <?= $urutkan === 'laba' ? 'selected' : ''; ?>>Rugi terbesar dulu</option>
                    <option value="laba_desc" <?= $urutkan === 'laba_desc' ? 'selected' : ''; ?>>Untung terbesar dulu</option>
                    <option value="margin" <?= $urutkan === 'margin' ? 'selected' : ''; ?>>Margin terendah</option>
                    <option value="penjualan" <?= $urutkan === 'penjualan' ? 'selected' : ''; ?>>Penjualan terbesar</option>
                    <option value="qty" <?= $urutkan === 'qty' ? 'selected' : ''; ?>>QTY terbanyak</option>
                    <option value="nama" <?= $urutkan === 'nama' ? 'selected' : ''; ?>>Nama barang (A-Z)</option>
                    <option value="nama_desc" <?= $urutkan === 'nama_desc' ? 'selected' : ''; ?>>Nama barang (Z-A)</option>
                  </select>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary">
              <i class="fa fa-filter"></i> Terapkan
            </button>
          </div>
        </form>
      </div>

      <div class="row">
        <div class="col-lg-3 col-6">
          <div class="small-box bg-info">
            <div class="inner">
              <h4><?= lpkdRupiah($totalPenjualan); ?></h4>
              <p>Penjualan kategori</p>
            </div>
            <div class="icon"><i class="fas fa-cash-register"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box <?= $totalLaba >= 0 ? 'bg-success' : 'bg-danger'; ?>">
            <div class="inner">
              <h4><?= lpkdRupiah($totalLaba); ?></h4>
              <p>Laba kotor (filter aktif)</p>
            </div>
            <div class="icon"><i class="fas fa-balance-scale"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-danger">
            <div class="inner">
              <h4><?= number_format($jmlRugi, 0, ',', '.'); ?></h4>
              <p>Barang rugi</p>
            </div>
            <div class="icon"><i class="fas fa-arrow-down"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-success">
            <div class="inner">
              <h4><?= number_format($jmlUntung, 0, ',', '.'); ?></h4>
              <p>Barang untung</p>
            </div>
            <div class="icon"><i class="fas fa-arrow-up"></i></div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">
            List Barang
            <small class="text-muted">(<?= number_format($totalProduk, 0, ',', '.'); ?> ditampilkan)</small>
          </h3>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table id="tabel-barang-kategori" class="table table-bordered table-striped table-hover">
              <thead>
                <tr>
                    <?php
                      $qsSortBase = [
                        'kategori_id'   => $kategoriId,
                        'tanggal_awal'  => $tanggalAwal,
                        'tanggal_akhir' => $tanggalAkhir,
                        'status'        => $statusFilter,
                      ];
                      // Klik header: A-Z → Z-A → A-Z
                      $nextNamaSort = ($urutkan === 'nama') ? 'nama_desc' : 'nama';
                      $qsSortNama = http_build_query(array_merge($qsSortBase, ['urutkan' => $nextNamaSort]));
                      $iconNama = 'fa-sort';
                      if ($urutkan === 'nama') {
                        $iconNama = 'fa-sort-alpha-down';
                      } elseif ($urutkan === 'nama_desc') {
                        $iconNama = 'fa-sort-alpha-up';
                      }
                    ?>
                    <th style="width:4%;">No</th>
                    <th style="width:70px;" class="text-center">Trx</th>
                    <th style="min-width:160px;">Perbaikan</th>
                    <th>Kode</th>
                    <th>
                      <a href="laporan-penjualan-kategori-detail?<?= htmlspecialchars($qsSortNama, ENT_QUOTES, 'UTF-8'); ?>"
                         class="text-dark"
                         title="Urutkan nama barang A-Z / Z-A"
                         style="text-decoration:none;">
                        Nama Barang
                        <i class="fas <?= $iconNama; ?> ml-1 text-muted"></i>
                      </a>
                    </th>
                    <th>Supplier</th>
                    <th class="text-right">Trx</th>
                    <th class="text-right">QTY</th>
                    <th class="text-right">Penjualan</th>
                    <th class="text-right">HPP</th>
                    <th class="text-right">Laba Kotor</th>
                    <th class="text-center">Margin</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($dataBarang)) : ?>
                  <tr>
                    <td colspan="12" class="text-center text-muted py-4">
                      Tidak ada barang pada filter/periode ini.
                    </td>
                  </tr>
                <?php else : ?>
                  <?php $no = 1; foreach ($dataBarang as $row) :
                    $penjualan = (float) $row['penjualan'];
                    $hpp       = (float) $row['hpp'];
                    $laba      = (float) $row['laba_kotor'];
                    $margin    = (float) $row['margin'];
                    $marginBar = max(2, min(100, (abs($margin) / $marginTerbesar) * 100));
                    if ($margin < 0) {
                      $marginClass = 'bg-danger';
                    } elseif ($margin < 5) {
                      $marginClass = 'bg-warning';
                    } else {
                      $marginClass = 'bg-success';
                    }
                    $kodeBarang = (string) ($row['barang_kode'] ?? '');
                    $kodeQs = urlencode($kodeBarang);
                    $qsTrx = http_build_query([
                      'barang_id'     => (int) $row['barang_id'],
                      'kategori_id'   => $kategoriId,
                      'tanggal_awal'  => $tanggalAwal,
                      'tanggal_akhir' => $tanggalAkhir,
                      'status'        => $laba < 0 ? 'rugi' : 'semua',
                    ]);
                  ?>
                    <tr class="<?= $laba < 0 ? 'table-danger' : ''; ?>">
                      <td><?= $no++; ?></td>
                      <td class="text-center">
                        <a href="laporan-penjualan-kategori-transaksi?<?= htmlspecialchars($qsTrx, ENT_QUOTES, 'UTF-8'); ?>"
                           class="btn btn-xs btn-warning"
                           title="Lihat transaksi barang ini">
                          <i class="fas fa-receipt"></i>
                        </a>
                      </td>
                      <td class="text-nowrap">
                        <div class="btn-group">
                          <?php if ($isGudangHpp) : ?>
                            <a href="perbaiki-hpp-ganti-satuan?kode=<?= $kodeQs; ?>"
                               target="_blank"
                               class="btn btn-xs btn-outline-warning"
                               title="Konversi HPP karena ganti satuan">
                              <i class="fas fa-exchange-alt"></i>
                            </a>
                            <a href="perbaiki-hpp-barang?kode=<?= $kodeQs; ?>"
                               target="_blank"
                               class="btn btn-xs btn-outline-primary"
                               title="Recalculate HPP master barang">
                              <i class="fas fa-sync"></i>
                            </a>
                            <a href="barang-edit?id=<?= urlencode(base64_encode((string) (int) $row['barang_id'])); ?>"
                               target="_blank"
                               class="btn btn-xs btn-outline-secondary"
                               title="Edit data barang">
                              <i class="fa fa-edit"></i>
                            </a>
                          <?php endif; ?>
                          <button type="button"
                                  class="btn btn-xs btn-outline-danger btn-minta-hpp"
                                  title="Minta perbaikan ke gudang"
                                  data-kode="<?= htmlspecialchars($kodeBarang, ENT_QUOTES, 'UTF-8'); ?>"
                                  data-nama="<?= htmlspecialchars((string) $row['barang_nama'], ENT_QUOTES, 'UTF-8'); ?>"
                                  data-barang-id="<?= (int) $row['barang_id']; ?>"
                                  data-penjualan="<?= (float) $penjualan; ?>"
                                  data-hpp="<?= (float) $hpp; ?>"
                                  data-laba="<?= (float) $laba; ?>"
                                  data-trx="<?= (int) $row['jml_transaksi']; ?>">
                            <i class="fas fa-paper-plane"></i>
                          </button>
                        </div>
                      </td>
                      <td><code><?= htmlspecialchars($kodeBarang, ENT_QUOTES, 'UTF-8'); ?></code></td>
                      <td><strong><?= htmlspecialchars((string) $row['barang_nama'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                      <td><?= htmlspecialchars((string) ($row['kode_suplier'] !== '' ? $row['kode_suplier'] : '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                      <td class="text-right"><?= number_format((float) $row['jml_transaksi'], 0, ',', '.'); ?></td>
                      <td class="text-right"><?= number_format((float) $row['qty'], 0, ',', '.'); ?></td>
                      <td class="text-right"><?= lpkdRupiah($penjualan); ?></td>
                      <td class="text-right"><?= lpkdRupiah($hpp); ?></td>
                      <td class="text-right <?= $laba >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <strong><?= lpkdRupiah($laba); ?></strong>
                      </td>
                      <td>
                        <div class="progress progress-xs mb-1">
                          <div class="progress-bar <?= $marginClass; ?>" style="width: <?= $marginBar; ?>%"></div>
                        </div>
                        <span class="badge <?= $margin >= 0 ? 'badge-success' : 'badge-danger'; ?>">
                          <?= lpkdPersen($margin); ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
              <?php if (!empty($dataBarang)) : ?>
                <tfoot>
                  <tr class="bg-light">
                    <th colspan="7">TOTAL</th>
                    <th class="text-right"><?= number_format($totalQty, 0, ',', '.'); ?></th>
                    <th class="text-right"><?= lpkdRupiah($totalPenjualan); ?></th>
                    <th class="text-right"><?= lpkdRupiah($totalHpp); ?></th>
                    <th class="text-right <?= $totalLaba >= 0 ? 'text-success' : 'text-danger'; ?>">
                      <?= lpkdRupiah($totalLaba); ?>
                    </th>
                    <th class="text-center"><?= lpkdPersen($marginTotal); ?></th>
                  </tr>
                </tfoot>
              <?php endif; ?>
            </table>
          </div>
          <small class="text-muted">
            <i class="fas fa-receipt"></i> = transaksi,
            <i class="fas fa-paper-plane"></i> = minta perbaikan ke gudang
            <?php if ($isGudangHpp) : ?>
              ,
              <i class="fas fa-exchange-alt"></i> = HPP ganti satuan,
              <i class="fas fa-sync"></i> = HPP barang,
              <i class="fa fa-edit"></i> = edit master barang
            <?php endif; ?>.
          </small>
        </div>
      </div>

    </div>
  </section>
</div>

<div class="modal fade" id="modalMintaHpp" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="aksi/hpp-perbaikan-request.php" class="modal-content">
      <?php
        $redirectDetail = 'laporan-penjualan-kategori-detail?' . http_build_query([
          'kategori_id' => $kategoriId,
          'tanggal_awal' => $tanggalAwal,
          'tanggal_akhir' => $tanggalAkhir,
          'status' => $statusFilter,
          'urutkan' => $urutkan ?? 'laba',
        ]);
      ?>
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectDetail, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="barang_id" id="minta_barang_id" value="">
      <input type="hidden" name="barang_kode" id="minta_barang_kode" value="">
      <input type="hidden" name="barang_nama" id="minta_barang_nama" value="">
      <input type="hidden" name="tanggal_awal" value="<?= htmlspecialchars($tanggalAwal, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="tanggal_akhir" value="<?= htmlspecialchars($tanggalAkhir, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="ringkas_penjualan" id="minta_penjualan" value="0">
      <input type="hidden" name="ringkas_hpp" id="minta_hpp" value="0">
      <input type="hidden" name="ringkas_laba" id="minta_laba" value="0">
      <input type="hidden" name="jml_trx" id="minta_trx" value="0">
      <input type="hidden" name="jml_trx_rugi" id="minta_trx_rugi" value="0">
      <div class="modal-header">
        <h5 class="modal-title">Minta perbaikan HPP ke gudang</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p class="mb-2">
          Barang: <strong id="minta_label_nama"></strong><br>
          Kode: <code id="minta_label_kode"></code><br>
          Periode: <?= htmlspecialchars(date('d/m/Y', strtotime($tanggalAwal)), ENT_QUOTES, 'UTF-8'); ?>
          –
          <?= htmlspecialchars(date('d/m/Y', strtotime($tanggalAkhir)), ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <div class="form-group">
          <label>Catatan untuk gudang</label>
          <textarea name="catatan" class="form-control" rows="3" required
            placeholder="Jelaskan gejala, mis. HPP ×6 dari harga jual / dugaan salah satuan."></textarea>
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
    $('.btn-minta-hpp').on('click', function () {
      var $b = $(this);
      var laba = parseFloat($b.data('laba')) || 0;
      $('#minta_barang_id').val($b.data('barang-id'));
      $('#minta_barang_kode').val($b.data('kode'));
      $('#minta_barang_nama').val($b.data('nama'));
      $('#minta_penjualan').val($b.data('penjualan'));
      $('#minta_hpp').val($b.data('hpp'));
      $('#minta_laba').val(laba);
      $('#minta_trx').val($b.data('trx'));
      $('#minta_trx_rugi').val(laba < 0 ? 1 : 0);
      $('#minta_label_nama').text($b.data('nama'));
      $('#minta_label_kode').text($b.data('kode'));
      $('#modalMintaHpp').modal('show');
    });

    <?php if (!empty($dataBarang)) : ?>
      $('#tabel-barang-kategori').DataTable({
        paging: true,
        pageLength: 50,
        searching: true,
        ordering: false,
        info: true,
        language: {
          search: 'Cari barang:',
          zeroRecords: 'Barang tidak ditemukan',
          lengthMenu: 'Tampil _MENU_',
          info: 'Menampilkan _START_–_END_ dari _TOTAL_ barang',
          paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
        }
      });
    <?php endif; ?>
  });
</script>
