<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
require_once 'aksi/produk-analisa-katalog-lib.php';
error_reporting(0);

if ($levelLogin === "kurir" || $levelLogin === "kasir") {
  echo "<script>document.location.href = 'bo';</script>";
}

// Filters
$selectedCabang = $sessionCabang;
if ($levelLogin === "super admin") {
  $selectedCabang = isset($_GET['cabang']) ? intval($_GET['cabang']) : $sessionCabang;
}

$filterPeriode = isset($_GET['periode']) ? $_GET['periode'] : 'bulan';
$goal = isset($_GET['goal']) ? $_GET['goal'] : 'balanced'; // balanced | omzet | margin | stok
$kategoriId = isset($_GET['kategori_id']) ? intval($_GET['kategori_id']) : 0;
$supplier = isset($_GET['supplier']) ? trim($_GET['supplier']) : '';
$minMargin = isset($_GET['min_margin']) ? floatval($_GET['min_margin']) : 0;
$minQty = isset($_GET['min_qty']) ? floatval($_GET['min_qty']) : 0;

// Date calculations
$today = date('Y-m-d');
$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');
$startOfYear = date('Y-01-01');

switch ($filterPeriode) {
  case 'hari':
    $startDate = $today;
    $endDate = $today;
    $periodLabel = 'Hari Ini';
    break;
  case 'minggu':
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
    $periodLabel = 'Minggu Ini';
    break;
  case 'bulan':
    $startDate = $startOfMonth;
    $endDate = $endOfMonth;
    $periodLabel = 'Bulan ' . date('F Y');
    break;
  case 'tahun':
    $startDate = $startOfYear;
    $endDate = date('Y-12-31');
    $periodLabel = 'Tahun ' . date('Y');
    break;
  case 'custom':
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : $startOfMonth;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : $today;
    $periodLabel = date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate));
    break;
  default:
    $startDate = $startOfMonth;
    $endDate = $endOfMonth;
    $periodLabel = 'Bulan ' . date('F Y');
}

$daysRange = max(1, (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1);

// Dropdown data
$kategoriList = query("SELECT kategori_id, kategori_nama FROM kategori WHERE kategori_status = '1' AND kategori_cabang = 0 ORDER BY kategori_nama");
$katalogTokoFooter = katalog_promo_toko_footer($conn);
$cabangList = [];
if ($levelLogin === "super admin") {
  $cabangList = query("SELECT toko_cabang, toko_kota FROM toko ORDER BY toko_cabang ASC");
}

// quick summary (small query, no heavy group by)
$summary = query("
  SELECT
    COUNT(DISTINCT p.barang_id) AS total_produk_terjual,
    COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) AS omzet,
    COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS hpp
  FROM penjualan p
  WHERE p.penjualan_cabang = $selectedCabang
    AND p.penjualan_date BETWEEN '$startDate' AND '$endDate'
");
$totalProdukTerjual = intval($summary[0]['total_produk_terjual'] ?? 0);
$omzet = floatval($summary[0]['omzet'] ?? 0);
$hpp = floatval($summary[0]['hpp'] ?? 0);
$laba = $omzet - $hpp;
$margin = $omzet > 0 ? ($laba / $omzet) * 100 : 0;

?>

<style>
  .filter-card {
    background: linear-gradient(135deg, #f0fdfa 0%, #e0f2fe 100%);
    border-radius: 15px;
  }

  .period-btn {
    border-radius: 20px;
    padding: 8px 20px;
    margin: 2px;
  }

  .period-btn.active {
    background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%);
    border-color: transparent;
    color: white;
  }

  .kpi-card {
    border-radius: 14px;
    overflow: hidden;
    border: none;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
  }

  .kpi-card .kpi-header {
    background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%);
    color: #fff;
    padding: 14px 16px;
    font-weight: 600;
  }

  .kpi-card .kpi-body {
    padding: 16px;
    background: #fff;
  }

  .kpi-value {
    font-size: 1.4rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 0.25rem;
  }

  .kpi-sub {
    color: #64748b;
    font-size: 0.9rem;
  }

  .promo-hint {
    background: #0f172a;
    color: #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
  }

  .promo-hint b {
    color: #fff;
  }

  .badge-score {
    background: #0ea5e9;
    color: #fff;
  }

  .badge-score-strong { background: #22c55e; color: #fff; }
  .badge-score-mid { background: #f59e0b; color: #111827; }
  .badge-score-low { background: #ef4444; color: #fff; }

  .katalog-card {
    border-radius: 14px;
    border: none;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
  }
  .katalog-card .card-header {
    background: linear-gradient(135deg, #15803d 0%, #0d9488 100%);
    color: #fff;
    border: 0;
  }
  .katalog-thumb {
    width: 44px;
    height: 44px;
    object-fit: contain;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
  }
  .katalog-thumb-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 16px;
  }
  .katalog-selected-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 16px;
    padding: 2px 8px 2px 10px;
    margin: 0 6px 6px 0;
    font-size: 12px;
    max-width: 280px;
  }
  .katalog-selected-chip span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .katalog-selected-chip button {
    border: 0;
    background: transparent;
    color: #b91c1c;
    padding: 0 2px;
    line-height: 1;
  }
  #katalogFlyerWrap {
    overflow: auto;
    background: #14532d;
    max-height: 70vh;
  }
  .nm-flyer {
    width: 1200px;
    margin: 0 auto;
    background-color: #2ea043;
    background-image: url('dist/img/katalog-leaf.svg');
    background-repeat: repeat;
    color: #111;
    font-family: Arial, Helvetica, sans-serif;
    padding: 18px 22px 16px;
    box-sizing: border-box;
  }
  .nm-flyer-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    min-height: 78px;
    gap: 12px;
  }
  .nm-brand {
    flex: 0 0 auto;
    background: transparent;
    padding: 0;
    box-shadow: none;
  }
  .nm-brand-logo {
    height: 68px;
    max-width: 240px;
    width: auto;
    object-fit: contain;
    display: block;
  }
  .nm-flyer-title {
    flex: 1;
    text-align: center;
    color: #fff;
    font-size: 48px;
    font-weight: 700;
    font-family: "Segoe Script", "Brush Script MT", "Comic Sans MS", cursive;
    text-shadow: 2px 3px 0 #14532d;
    line-height: 1.1;
    padding: 0 12px;
  }
  .nm-flyer-nu {
    width: 58px;
    height: 58px;
    object-fit: contain;
    background: #fff;
    border-radius: 50%;
    padding: 4px;
    flex: 0 0 auto;
  }
  .nm-flyer-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px 12px;
    padding: 8px 4px 16px;
  }
  .nm-item {
    text-align: center;
    min-width: 0;
  }
  .nm-item-name {
    font-size: 13px;
    font-weight: 700;
    color: #111;
    min-height: 34px;
    line-height: 1.2;
    margin-bottom: 6px;
  }
  .nm-item-img {
    height: 128px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 6px;
    background: #fff;
    border-radius: 8px;
    padding: 6px;
    box-sizing: border-box;
  }
  .nm-item-img img {
    max-width: 100%;
    max-height: 116px;
    object-fit: contain;
    background: #fff;
  }
  .nm-item-ph {
    width: 100%;
    height: 100%;
    border-radius: 6px;
    background: #fff;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
  }
  .nm-price-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #166534;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 2px 2px 0 0;
  }
  .nm-price-coret {
    text-decoration: line-through;
    text-decoration-thickness: 2px;
    opacity: 0.95;
  }
  .nm-price-box {
    display: flex;
    align-items: center;
    background: #facc15;
    border-radius: 0 0 4px 4px;
    overflow: hidden;
    min-height: 36px;
  }
  .nm-price-rp {
    background: transparent;
    color: #166534;
    font-weight: 800;
    font-size: 13px;
    padding: 8px 6px 8px 10px;
  }
  .nm-price-val {
    flex: 1;
    text-align: center;
    color: #dc2626;
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.5px;
    padding: 2px 4px;
  }
  .nm-flyer-foot {
    background: #14532d;
    color: #fff;
    border-radius: 8px;
    padding: 12px 16px 10px;
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 12px;
    font-size: 12px;
  }
  .nm-flyer-foot h4 {
    margin: 0 0 6px;
    font-size: 13px;
    font-weight: 800;
  }
  .nm-flyer-foot ol {
    margin: 0;
    padding-left: 18px;
  }
  .nm-flyer-note {
    opacity: 0.9;
    font-size: 11px;
    margin-top: 8px;
  }
  .nm-jam {
    display: inline-block;
    background: #dc2626;
    color: #fff;
    font-weight: 800;
    padding: 6px 10px;
    border-radius: 6px;
    margin-bottom: 8px;
  }
  #katalogTeksOut {
    white-space: pre-wrap;
    font-family: Consolas, "Courier New", monospace;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px;
    min-height: 180px;
  }

</style>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-7">
          <h1><i class="fas fa-bullhorn"></i> Analisa Produk untuk Iklan & Promo</h1>
        </div>
        <div class="col-sm-5">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="laporan-produk">Laporan Produk</a></li>
            <li class="breadcrumb-item active">Analisa Promo</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card filter-card mb-4">
        <div class="card-body">
          <form method="GET" action="" class="row align-items-end">
            <div class="col-md-12 mb-3">
              <label class="font-weight-bold">Filter Periode:</label>
              <div class="btn-group flex-wrap" role="group">
                <a href="?periode=hari&goal=<?= urlencode($goal) ?>&cabang=<?= (int)$selectedCabang ?>" class="btn btn-outline-primary period-btn <?= $filterPeriode == 'hari' ? 'active' : '' ?>">Hari Ini</a>
                <a href="?periode=minggu&goal=<?= urlencode($goal) ?>&cabang=<?= (int)$selectedCabang ?>" class="btn btn-outline-primary period-btn <?= $filterPeriode == 'minggu' ? 'active' : '' ?>">Minggu Ini</a>
                <a href="?periode=bulan&goal=<?= urlencode($goal) ?>&cabang=<?= (int)$selectedCabang ?>" class="btn btn-outline-primary period-btn <?= $filterPeriode == 'bulan' ? 'active' : '' ?>">Bulan Ini</a>
                <a href="?periode=tahun&goal=<?= urlencode($goal) ?>&cabang=<?= (int)$selectedCabang ?>" class="btn btn-outline-primary period-btn <?= $filterPeriode == 'tahun' ? 'active' : '' ?>">Tahun Ini</a>
              </div>
            </div>

            <div class="col-md-3">
              <label>Dari Tanggal:</label>
              <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
            </div>
            <div class="col-md-3">
              <label>Sampai Tanggal:</label>
              <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
            </div>
            <div class="col-md-3">
              <label>Tujuan Promo:</label>
              <select name="goal" class="form-control">
                <option value="balanced" <?= $goal == 'balanced' ? 'selected' : '' ?>>Seimbang (omzet + margin + stok)</option>
                <option value="omzet" <?= $goal == 'omzet' ? 'selected' : '' ?>>Naikkan Omzet</option>
                <option value="margin" <?= $goal == 'margin' ? 'selected' : '' ?>>Naikkan Margin</option>
                <option value="stok" <?= $goal == 'stok' ? 'selected' : '' ?>>Habiskan Stok (clearance)</option>
              </select>
            </div>
            <div class="col-md-3">
              <label>Kategori Nugrosir:</label>
              <select name="kategori_id" class="form-control select2bs4">
                <option value="0">-- Semua Kategori --</option>
                <?php foreach ($kategoriList as $k) : ?>
                  <option value="<?= $k['kategori_id'] ?>" <?= $kategoriId == $k['kategori_id'] ? 'selected' : '' ?>>
                    <?= $k['kategori_nama'] ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <?php if ($levelLogin === "super admin") : ?>
              <div class="col-md-3 mt-3">
                <label>Cabang (Custom):</label>
                <select name="cabang" class="form-control select2bs4">
                  <?php foreach ($cabangList as $c) :
                    $cabangVal = (int)($c['toko_cabang'] ?? 0);
                    $cabangLabel = $cabangVal < 1 ? 'Pusat' : 'Cabang ' . $cabangVal;
                    $kota = $c['toko_kota'] ?? '';
                  ?>
                    <option value="<?= $cabangVal ?>" <?= $selectedCabang == $cabangVal ? 'selected' : '' ?>>
                      <?= $cabangLabel ?><?= $kota ? ' - ' . $kota : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>

            <div class="col-md-3 mt-3">
              <label>Kode Supplier (opsional):</label>
              <input type="text" name="supplier" class="form-control" value="<?= htmlspecialchars($supplier) ?>" placeholder="mis. SUP-001">
            </div>
            <div class="col-md-3 mt-3">
              <label>Min Margin %:</label>
              <input type="number" step="0.1" name="min_margin" class="form-control" value="<?= $minMargin ?>" placeholder="0">
            </div>
            <div class="col-md-3 mt-3">
              <label>Min Terjual (PCS):</label>
              <input type="number" step="1" name="min_qty" class="form-control" value="<?= $minQty ?>" placeholder="0">
            </div>
            <div class="col-md-3 mt-3">
              <label>Aksi</label>
              <div class="input-group">
                <input type="hidden" name="periode" value="custom">
                <div class="input-group-append w-100">
                  <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Terapkan</button>
                </div>
              </div>
            </div>
          </form>

          <div class="mt-3 d-flex flex-wrap" style="gap:8px;">
            <span class="badge badge-primary" style="font-size: 1rem;"><i class="fas fa-calendar"></i> <?= $periodLabel ?></span>
            <span class="badge badge-info" style="font-size: 1rem;"><i class="fas fa-clock"></i> <?= $daysRange ?> hari</span>
            <span class="badge badge-secondary" style="font-size: 1rem;"><i class="fas fa-store"></i> Cabang: <?= $selectedCabang ?></span>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-3">
          <div class="kpi-card mb-3">
            <div class="kpi-header"><i class="fas fa-box"></i> Produk Terjual</div>
            <div class="kpi-body">
              <div class="kpi-value"><?= number_format($totalProdukTerjual, 0, ',', '.') ?></div>
              <div class="kpi-sub">Jumlah SKU yang ada transaksi</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="kpi-card mb-3">
            <div class="kpi-header"><i class="fas fa-coins"></i> Omzet</div>
            <div class="kpi-body">
              <div class="kpi-value">Rp <?= number_format($omzet, 0, ',', '.') ?></div>
              <div class="kpi-sub">Total penjualan (range dipilih)</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="kpi-card mb-3">
            <div class="kpi-header"><i class="fas fa-chart-line"></i> Laba Kotor</div>
            <div class="kpi-body">
              <div class="kpi-value">Rp <?= number_format($laba, 0, ',', '.') ?></div>
              <div class="kpi-sub">Omzet - HPP</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="kpi-card mb-3">
            <div class="kpi-header"><i class="fas fa-percent"></i> Margin</div>
            <div class="kpi-body">
              <div class="kpi-value"><?= number_format($margin, 2, ',', '.') ?>%</div>
              <div class="kpi-sub">Margin rata-rata (kotor)</div>
            </div>
          </div>
        </div>
      </div>

      <div class="promo-hint mb-4">
        <div class="d-flex flex-wrap justify-content-between" style="gap:10px;">
          <div>
            <b>Rekomendasi Iklan/Promo:</b> urutkan berdasarkan <b>Promo Score</b>. Gunakan “Tujuan Promo” untuk fokus (omzet, margin, atau habiskan stok).
          </div>
          <div>
            <button class="btn btn-success btn-sm" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Export</button>
          </div>
        </div>
      </div>

      <div class="card katalog-card mb-4">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-images"></i> Generate Katalog Promo</h3>
        </div>
        <div class="card-body">
          <p class="text-muted mb-3">
            Filter kategori Nugrosir, centang produk yang mau dimasukkan, lalu hasilkan <b>gambar brosur</b> atau <b>daftar teks</b> (nama + harga retail). Gambar produk ikut jika sudah ada.
          </p>
          <div class="row align-items-end">
            <div class="col-md-3">
              <label>Kategori Nugrosir</label>
              <select id="katalog-kategori" class="form-control select2bs4">
                <option value="0">-- Semua Kategori --</option>
                <?php foreach ($kategoriList as $k) : ?>
                  <option value="<?= (int)$k['kategori_id'] ?>" <?= $kategoriId == $k['kategori_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($k['kategori_nama']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label>Cari nama / kode</label>
              <input type="text" id="katalog-q" class="form-control" placeholder="Contoh: minyak goreng">
            </div>
            <div class="col-md-3">
              <label>Judul brosur</label>
              <input type="text" id="katalog-judul" class="form-control" value="Katalog Produk" placeholder="Katalog Produk">
              <small class="text-muted">Bisa diganti, mis. Promo Hemat</small>
            </div>
            <div class="col-md-2">
              <label class="d-block">Opsi</label>
              <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="katalog-hanya-gambar">
                <label class="custom-control-label" for="katalog-hanya-gambar">Hanya yang ada gambar</label>
              </div>
              <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="katalog-min-stok" checked>
                <label class="custom-control-label" for="katalog-min-stok">Stok &gt; 0</label>
              </div>
            </div>
            <div class="col-md-2">
              <button type="button" id="katalog-btn-muat" class="btn btn-success btn-block">
                <i class="fas fa-sync"></i> Muat Produk
              </button>
            </div>
          </div>

          <div class="mt-3 d-flex flex-wrap align-items-center" style="gap:8px;">
            <button type="button" id="katalog-btn-halaman" class="btn btn-sm btn-outline-secondary">Pilih halaman ini</button>
            <button type="button" id="katalog-btn-all" class="btn btn-sm btn-outline-primary">Pilih semua hasil filter</button>
            <button type="button" id="katalog-btn-clear" class="btn btn-sm btn-outline-danger">Kosongkan pilihan</button>
            <span class="badge badge-success" id="katalog-count" style="font-size:0.95rem;">0 dipilih</span>
            <div class="ml-auto d-flex flex-wrap" style="gap:8px;">
              <button type="button" id="katalog-btn-gambar" class="btn btn-warning">
                <i class="fas fa-image"></i> Generate Gambar
              </button>
              <button type="button" id="katalog-btn-teks" class="btn btn-info">
                <i class="fas fa-list"></i> Generate Teks List
              </button>
            </div>
          </div>

          <div id="katalog-selected-wrap" class="mt-2"></div>

          <div class="table-responsive mt-3">
            <table id="katalogTable" class="table table-hover table-bordered" style="width:100%;">
              <thead>
                <tr>
                  <th style="width:36px;"></th>
                  <th style="width:56px;">Gambar</th>
                  <th>Produk</th>
                  <th>Kategori</th>
                  <th>Satuan</th>
                  <th>Harga Retail</th>
                  <th>Stok</th>
                </tr>
              </thead>
            </table>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-table"></i> Rekomendasi Produk untuk Promo</h3>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table id="produkAnalisaTable" class="table table-hover table-bordered" style="width:100%;">
              <thead>
                <tr>
                  <th>No</th>
                  <th>Produk</th>
                  <th>Kategori</th>
                  <th>Supplier</th>
                  <th>Terjual (PCS)</th>
                  <th>Omzet</th>
                  <th>HPP</th>
                  <th>Laba</th>
                  <th>Margin %</th>
                  <th>Stok</th>
                  <th>Velocity (PCS/Hari)</th>
                  <th>Days of Stock</th>
                  <th>Promo Score</th>
                  <th>Aksi</th>
                </tr>
              </thead>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Modal Generate Copy Iklan -->
<div class="modal fade" id="modal-copy-iklan" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content" style="border-radius:14px; overflow:hidden;">
      <div class="modal-header" style="background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%); color:#fff;">
        <h5 class="modal-title"><i class="fas fa-pen-nib"></i> Generate Copy Iklan</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff; opacity:0.9;">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="mb-2 text-muted" id="copy-meta">Memuat...</div>

        <div class="row mb-3">
          <div class="col-md-4">
            <label class="mb-1">Platform</label>
            <select id="copy-platform" class="form-control">
              <option value="wa">WhatsApp</option>
              <option value="ig_feed">Instagram Feed</option>
              <option value="ig_story">Instagram Story</option>
              <option value="fb">Facebook</option>
              <option value="marketplace">Marketplace</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="mb-1">Gaya Bahasa</label>
            <select id="copy-tone" class="form-control">
              <option value="santai">Santai</option>
              <option value="formal">Formal</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="mb-1">Harga/Promo (opsional)</label>
            <input id="copy-promo" type="text" class="form-control" placeholder="cth: Diskon 10% / Rp 9.900 / Beli 2 gratis 1">
            <div class="mt-2 d-flex flex-wrap" style="gap:6px;">
              <button type="button" class="btn btn-xs btn-outline-primary preset-promo" data-text="Flash Sale hari ini!">Flash Sale</button>
              <button type="button" class="btn btn-xs btn-outline-success preset-promo" data-text="Best Seller! Beli 2 lebih hemat">Best Seller</button>
              <button type="button" class="btn btn-xs btn-outline-info preset-promo" data-text="Bundling hemat (paket) – stok terbatas">Bundling</button>
              <button type="button" class="btn btn-xs btn-outline-danger preset-promo" data-text="Clearance! Diskon terbatas sampai stok habis">Clearance</button>
            </div>
          </div>
        </div>

        <div id="copy-loading" class="text-center py-4">
          <div class="spinner-border text-info" role="status"></div>
          <div class="mt-2">Sedang generate copy iklan...</div>
        </div>
        <div id="copy-content" style="display:none;">
          <ul class="nav nav-pills mb-3" id="copy-tabs" role="tablist"></ul>
          <div class="tab-content" id="copy-tab-content"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Katalog Gambar -->
<div class="modal fade" id="modal-katalog-gambar" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content" style="border-radius:14px; overflow:hidden;">
      <div class="modal-header" style="background: linear-gradient(135deg, #15803d 0%, #0d9488 100%); color:#fff;">
        <h5 class="modal-title"><i class="fas fa-image"></i> Preview Katalog Gambar</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff; opacity:0.9;">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body p-0">
        <div id="katalogFlyerWrap">
          <div id="katalog-flyer" class="nm-flyer"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
        <button type="button" id="katalog-btn-download" class="btn btn-success">
          <i class="fas fa-download"></i> Download PNG
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Katalog Teks -->
<div class="modal fade" id="modal-katalog-teks" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content" style="border-radius:14px; overflow:hidden;">
      <div class="modal-header" style="background: linear-gradient(135deg, #0284c7 0%, #0d9488 100%); color:#fff;">
        <h5 class="modal-title"><i class="fas fa-list"></i> Katalog Teks List</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff; opacity:0.9;">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <textarea id="katalogTeksOut" class="form-control" rows="16" readonly></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
        <button type="button" id="katalog-btn-copy-teks" class="btn btn-primary">
          <i class="fas fa-copy"></i> Salin Teks
        </button>
      </div>
    </div>
  </div>
</div>

<?php include '_footer.php'; ?>

<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>

<script>
  $(function() {
    $('.select2bs4').select2({ theme: 'bootstrap4' });

    const ajaxUrl = `produk-analisa-data.php?cabang=<?= (int)$selectedCabang ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&goal=<?= urlencode($goal) ?>&kategori_id=<?= (int)$kategoriId ?>&supplier=<?= urlencode($supplier) ?>&min_margin=<?= urlencode((string)$minMargin) ?>&min_qty=<?= urlencode((string)$minQty) ?>`;

    const table = $('#produkAnalisaTable').DataTable({
      processing: true,
      serverSide: true,
      ajax: ajaxUrl,
      pageLength: 25,
      order: [[12, "desc"]],
      columnDefs: [
        { targets: 0, orderable: false, searchable: false },
        { targets: 13, orderable: false, searchable: false }
      ]
    });

    table.on('draw.dt', function() {
      var info = table.page.info();
      table.column(0, { page: 'applied' }).nodes().each(function(cell, i) {
        cell.innerHTML = i + 1 + info.start;
      });
    });

    // Generate copy button
    $('#produkAnalisaTable').on('click', '.btn-generate-copy', function() {
      const barangId = $(this).data('id');
      const nama = $(this).data('nama') || '';

      $('#modal-copy-iklan').modal('show');
      $('#copy-meta').text(`Produk: ${nama} (ID: ${barangId}) | Goal: <?= htmlspecialchars($goal) ?> | Cabang: <?= (int)$selectedCabang ?> | Periode: <?= htmlspecialchars($periodLabel) ?>`);
      $('#copy-loading').show();
      $('#copy-content').hide();
      $('#copy-tabs').empty();
      $('#copy-tab-content').empty();

      const platform = $('#copy-platform').val() || 'wa';
      const tone = $('#copy-tone').val() || 'santai';
      const promoText = $('#copy-promo').val() || '';

      $.getJSON('produk-analisa-copy.php', {
        cabang: <?= (int)$selectedCabang ?>,
        start_date: '<?= $startDate ?>',
        end_date: '<?= $endDate ?>',
        goal: '<?= $goal ?>',
        barang_id: barangId,
        platform: platform,
        tone: tone,
        promo: promoText
      }).done(function(res) {
        if (!res || !res.variants || !res.variants.length) {
          $('#copy-loading').hide();
          $('#copy-content').show();
          $('#copy-tab-content').html(`<div class="alert alert-warning">Copy iklan tidak tersedia.</div>`);
          return;
        }

        res.variants.forEach((v, idx) => {
          const tabId = `var-${idx}`;
          const active = idx === 0 ? 'active' : '';
          const ariaSelected = idx === 0 ? 'true' : 'false';
          $('#copy-tabs').append(`
            <li class="nav-item">
              <a class="nav-link ${active}" id="${tabId}-tab" data-toggle="pill" href="#${tabId}" role="tab" aria-selected="${ariaSelected}">
                Var ${idx + 1}
              </a>
            </li>
          `);

          const textBlock = `${v.headline}\n\n${v.body}\n\n${v.cta}\n\n${v.hashtags}`;
          $('#copy-tab-content').append(`
            <div class="tab-pane fade show ${active}" id="${tabId}" role="tabpanel">
              <div class="mb-2"><b>Headline</b><br><div class="p-2 bg-light rounded">${v.headline}</div></div>
              <div class="mb-2"><b>Body</b><br><div class="p-2 bg-light rounded" style="white-space:pre-line;">${v.body}</div></div>
              <div class="mb-2"><b>CTA</b><br><div class="p-2 bg-light rounded">${v.cta}</div></div>
              <div class="mb-3"><b>Hashtag</b><br><div class="p-2 bg-light rounded" style="white-space:pre-line;">${v.hashtags}</div></div>
              <button class="btn btn-primary btn-copy-text" data-text="${encodeURIComponent(textBlock)}">
                <i class="fas fa-copy"></i> Copy Semua
              </button>
            </div>
          `);
        });

        $('#copy-loading').hide();
        $('#copy-content').show();
      }).fail(function() {
        $('#copy-loading').hide();
        $('#copy-content').show();
        $('#copy-tab-content').html(`<div class="alert alert-danger">Gagal generate copy. Coba refresh dan ulangi.</div>`);
      });
    });

    // Regenerate saat opsi berubah (kalau modal sedang terbuka dan ada produk aktif)
    $('#copy-platform, #copy-tone').on('change', function() {
      const $btn = $('#produkAnalisaTable').find('.btn-generate-copy[data-id]').first();
      // no-op; user akan klik Generate lagi untuk kontrol penuh
    });

    // Copy to clipboard
    $(document).on('click', '.btn-copy-text', function() {
      const text = decodeURIComponent($(this).data('text') || '');
      if (!text) return;
      navigator.clipboard.writeText(text).then(() => {
        $(this).text('Tersalin!');
        setTimeout(() => $(this).html('<i class="fas fa-copy"></i> Copy Semua'), 1200);
      });
    });

    // Preset promo helpers
    $(document).on('click', '.preset-promo', function() {
      const t = $(this).data('text') || '';
      if (!t) return;
      $('#copy-promo').val(t).trigger('input');
    });

    const KATALOG_CABANG = <?= (int)$selectedCabang ?>;
    const KATALOG_SUPPLIER = <?= json_encode($supplier, JSON_UNESCAPED_UNICODE) ?>;
    const KATALOG_TOKO = <?= json_encode($katalogTokoFooter, JSON_UNESCAPED_UNICODE) ?>;
    const katalogSelected = new Map();
    let katalogTable = null;

    function katalogFormatRp(n) {
      const v = Math.round(Number(n) || 0);
      return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function katalogEscape(s) {
      return String(s || '').replace(/[&<>"']/g, function(c) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
      });
    }

    function katalogParams() {
      return {
        cabang: KATALOG_CABANG,
        kategori_id: $('#katalog-kategori').val() || 0,
        q: $('#katalog-q').val() || '',
        supplier: KATALOG_SUPPLIER,
        hanya_gambar: $('#katalog-hanya-gambar').is(':checked') ? 1 : 0,
        min_stok: $('#katalog-min-stok').is(':checked') ? 1 : 0
      };
    }

    function katalogSelectedList() {
      return Array.from(katalogSelected.values());
    }

    function katalogRenderSelected() {
      const items = katalogSelectedList();
      $('#katalog-count').text(items.length + ' dipilih');
      const wrap = $('#katalog-selected-wrap');
      if (!items.length) {
        wrap.empty();
        return;
      }
      const maxChip = 18;
      const shown = items.slice(0, maxChip);
      let html = shown.map(function(it) {
        return '<span class="katalog-selected-chip"><span title="' + katalogEscape(it.nama) + '">' +
          katalogEscape(it.nama) + ' · Rp ' + katalogFormatRp(it.harga) +
          '</span><button type="button" data-id="' + it.id + '" title="Hapus">&times;</button></span>';
      }).join('');
      if (items.length > maxChip) {
        html += '<span class="text-muted small">+' + (items.length - maxChip) + ' lainnya</span>';
      }
      wrap.html(html);
    }

    function katalogSyncChecks() {
      $('#katalogTable .katalog-cek').each(function() {
        const id = parseInt($(this).val(), 10);
        $(this).prop('checked', katalogSelected.has(id));
      });
    }

    function katalogResolveImg(src) {
      src = String(src || '').trim();
      if (!src) return '';
      if (/^(https?:|data:|\/\/)/i.test(src)) return src;
      try {
        return new URL(src, window.location.href).href;
      } catch (e) {
        return src;
      }
    }

    function katalogAddItem(item) {
      if (!item || !item.id) return;
      if (item.gambar) item.gambar = katalogResolveImg(item.gambar);
      katalogSelected.set(parseInt(item.id, 10), item);
    }

    function ensureKatalogTable() {
      if (katalogTable) {
        katalogTable.ajax.reload();
        return;
      }
      katalogTable = $('#katalogTable').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 25,
        order: [[2, 'asc']],
        ajax: function(data, callback) {
          $.extend(data, katalogParams());
          $.getJSON('produk-analisa-katalog-data.php', data).done(callback).fail(function() {
            callback({ draw: data.draw || 1, recordsTotal: 0, recordsFiltered: 0, data: [] });
          });
        },
        columnDefs: [
          { targets: [0, 1], orderable: false, searchable: false }
        ]
      });
      katalogTable.on('draw.dt', katalogSyncChecks);
    }

    $('#katalog-btn-muat').on('click', function() {
      ensureKatalogTable();
    });
    $('#katalog-q').on('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        ensureKatalogTable();
      }
    });

    $('#katalogTable').on('change', '.katalog-cek', function() {
      const id = parseInt($(this).val(), 10);
      if ($(this).is(':checked')) {
        let item = {};
        try { item = JSON.parse($(this).attr('data-item') || '{}'); } catch (err) { item = { id: id }; }
        katalogAddItem(item);
      } else {
        katalogSelected.delete(id);
      }
      katalogRenderSelected();
    });

    $('#katalog-selected-wrap').on('click', 'button[data-id]', function() {
      katalogSelected.delete(parseInt($(this).data('id'), 10));
      katalogRenderSelected();
      katalogSyncChecks();
    });

    $('#katalog-btn-halaman').on('click', function() {
      if (!katalogTable) {
        ensureKatalogTable();
        return;
      }
      $('#katalogTable .katalog-cek').each(function() {
        let item = {};
        try { item = JSON.parse($(this).attr('data-item') || '{}'); } catch (err) { return; }
        $(this).prop('checked', true);
        katalogAddItem(item);
      });
      katalogRenderSelected();
    });

    $('#katalog-btn-clear').on('click', function() {
      katalogSelected.clear();
      katalogRenderSelected();
      katalogSyncChecks();
    });

    $('#katalog-btn-all').on('click', function() {
      const params = katalogParams();
      params.action = 'all';
      $.getJSON('produk-analisa-katalog-data.php', params).done(function(res) {
        (res.items || []).forEach(katalogAddItem);
        katalogRenderSelected();
        katalogSyncChecks();
      }).fail(function() {
        alert('Gagal memuat semua produk filter.');
      });
    });

    function katalogBuildTeks(items) {
      const judul = ($('#katalog-judul').val() || 'Katalog Produk').trim();
      const lines = ['*' + judul.toUpperCase() + '*', 'Harga retail NUMART', ''];
      items.forEach(function(it, i) {
        lines.push((i + 1) + '. *' + it.nama + '*');
        const satuan = it.satuan || 'Pcs';
        if (it.harga_coret && Number(it.harga_coret) > Number(it.harga)) {
          lines.push('   ' + satuan + ' ~Rp ' + katalogFormatRp(it.harga_coret) + '~');
        }
        lines.push('   Rp *' + katalogFormatRp(it.harga) + '* / ' + satuan);
        lines.push('');
      });
      if (KATALOG_TOKO && KATALOG_TOKO.length) {
        lines.push('Harga berlaku di:');
        KATALOG_TOKO.forEach(function(t, i) {
          lines.push((i + 1) + '. ' + t.nama);
        });
        lines.push('');
      }
      lines.push('Harga dapat berubah sewaktu-waktu.');
      lines.push('Buka setiap hari 07.00 - 21.00 WIB');
      return lines.join('\n');
    }

    function katalogBuildFlyerHtml(items) {
      const judul = katalogEscape(($('#katalog-judul').val() || 'Katalog Produk').trim());
      const grid = items.map(function(it) {
        const imgSrc = katalogResolveImg(it.gambar);
        const img = imgSrc
          ? '<img src="' + katalogEscape(imgSrc) + '" alt="">'
          : '<div class="nm-item-ph"><i class="fas fa-box-open"></i></div>';
        const coret = (it.harga_coret && Number(it.harga_coret) > Number(it.harga))
          ? '<span class="nm-price-coret">' + katalogFormatRp(it.harga_coret) + '</span>'
          : '<span></span>';
        return '<div class="nm-item">' +
          '<div class="nm-item-name">' + katalogEscape(it.nama) + '</div>' +
          '<div class="nm-item-img">' + img + '</div>' +
          '<div class="nm-price-bar"><span>' + katalogEscape(it.satuan || 'Pcs') + '</span>' + coret + '</div>' +
          '<div class="nm-price-box"><span class="nm-price-rp">Rp</span><span class="nm-price-val">' + katalogFormatRp(it.harga) + '</span></div>' +
          '</div>';
      }).join('');
      const loc = (KATALOG_TOKO || []).map(function(t) {
        return '<li>' + katalogEscape(t.nama) + '</li>';
      }).join('');
      return '' +
        '<div class="nm-flyer-head">' +
          '<div class="nm-brand"><img class="nm-brand-logo" src="dist/img/numart-logo.png" alt="NUMart"></div>' +
          '<div class="nm-flyer-title">' + judul + '</div>' +
          '<img class="nm-flyer-nu" src="dist/img/logobumnupacnu.png" alt="NU">' +
        '</div>' +
        '<div class="nm-flyer-grid">' + grid + '</div>' +
        '<div class="nm-flyer-foot">' +
          '<div><h4>Harga Berlaku di :</h4><ol>' + loc + '</ol>' +
          '<div class="nm-flyer-note">Harga dapat berubah sewaktu-waktu. Syarat &amp; ketentuan berlaku.</div></div>' +
          '<div style="text-align:right;"><div class="nm-jam">Buka Setiap Hari 07.00 - 21.00 WIB</div>' +
          '<div>NUMART · PCNU Kabupaten Magelang</div></div>' +
        '</div>';
    }

    function katalogWaitImages(el) {
      const imgs = Array.prototype.slice.call(el.querySelectorAll('img'));
      return Promise.all(imgs.map(function(img) {
        if (img.complete) return Promise.resolve();
        return new Promise(function(resolve) {
          img.onload = img.onerror = resolve;
          setTimeout(resolve, 5000);
        });
      }));
    }

    $('#katalog-btn-teks').on('click', function() {
      const items = katalogSelectedList();
      if (!items.length) {
        alert('Centang dulu produk yang mau dimasukkan ke katalog.');
        return;
      }
      $('#katalogTeksOut').val(katalogBuildTeks(items));
      $('#modal-katalog-teks').modal('show');
    });

    $('#katalog-btn-copy-teks').on('click', function() {
      const text = $('#katalogTeksOut').val() || '';
      if (!text) return;
      navigator.clipboard.writeText(text).then(() => {
        const $btn = $(this);
        $btn.text('Tersalin!');
        setTimeout(() => $btn.html('<i class="fas fa-copy"></i> Salin Teks'), 1200);
      });
    });

    $('#katalog-btn-gambar').on('click', function() {
      const items = katalogSelectedList();
      if (!items.length) {
        alert('Centang dulu produk yang mau dimasukkan ke katalog.');
        return;
      }
      $('#katalog-flyer').html(katalogBuildFlyerHtml(items));
      $('#modal-katalog-gambar').modal('show');
    });

    $('#katalog-btn-download').on('click', function() {
      const el = document.getElementById('katalog-flyer');
      if (!el || typeof html2canvas !== 'function') {
        alert('html2canvas tidak tersedia.');
        return;
      }
      const $btn = $(this);
      $btn.prop('disabled', true).text('Menyiapkan...');
      katalogWaitImages(el).then(function() {
        return html2canvas(el, {
          useCORS: true,
          allowTaint: false,
          backgroundColor: '#2ea043',
          scale: 2,
          logging: false
        });
      }).then(function(canvas) {
        const a = document.createElement('a');
        a.download = 'katalog-promo-<?= date('Y-m-d') ?>.png';
        a.href = canvas.toDataURL('image/png');
        a.click();
      }).catch(function() {
        alert('Gagal membuat gambar. Coba lagi, atau uncentang produk yang gambarnya dari link luar.');
      }).finally(function() {
        $btn.prop('disabled', false).html('<i class="fas fa-download"></i> Download PNG');
      });
    });

    <?php if ($kategoriId > 0) : ?>
    ensureKatalogTable();
    <?php endif; ?>
  });

  function exportToExcel() {
    let table = document.getElementById('produkAnalisaTable');
    let html = table.outerHTML;
    let url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
    let downloadLink = document.createElement('a');
    downloadLink.href = url;
    downloadLink.download = 'produk_analisa_promo_<?= date('Y-m-d') ?>.xls';
    downloadLink.click();
  }
</script>

