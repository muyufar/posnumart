<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>
<?php  
  if ( $levelLogin === "kasir" || $levelLogin === "kurir" ) {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
    exit;
  }

  $namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

  $tahunIni = (int) date('Y');
  $tahun    = isset($_GET['tahun']) ? (int) $_GET['tahun'] : $tahunIni;
  if ( $tahun < 2000 || $tahun > $tahunIni + 1 ) {
    $tahun = $tahunIni;
  }

  $tipe = $_GET['tipe'] ?? 'semua';
  if ( !in_array($tipe, ['semua', '0', '1'], true) ) {
    $tipe = 'semua';
  }

  $status = $_GET['status'] ?? 'selesai';
  if ( !in_array($status, ['selesai', 'semua'], true) ) {
    $status = 'selesai';
  }

  $bulan = isset($_GET['bulan']) ? (int) $_GET['bulan'] : 0;
  if ( $bulan < 1 || $bulan > 12 ) {
    $bulan = 0;
  }

  $cabang = (int) $sessionCabang;
  $where  = "s.stock_opname_cabang = $cabang AND YEAR(s.stock_opname_date_proses) = $tahun";
  if ( $tipe !== 'semua' ) {
    $where .= " AND s.stock_opname_tipe = " . (int) $tipe;
  }
  if ( $status === 'selesai' ) {
    $where .= " AND s.stock_opname_status > 0";
  }

  barang_harga_beli_rata_ensure_column($conn);
  $hppSql = "(" . barang_hpp_sql_expr('b') . ")";
  $agregatSql = "
    COUNT(h.soh_id) AS jumlah_item,
    COALESCE(SUM(h.soh_selisih = 0), 0) AS item_sesuai,
    COALESCE(SUM(h.soh_selisih > 0), 0) AS item_lebih,
    COALESCE(SUM(h.soh_selisih < 0), 0) AS item_kurang,
    COALESCE(SUM(CASE WHEN h.soh_selisih > 0 THEN h.soh_selisih ELSE 0 END), 0) AS qty_lebih,
    COALESCE(SUM(CASE WHEN h.soh_selisih < 0 THEN -h.soh_selisih ELSE 0 END), 0) AS qty_kurang,
    COALESCE(SUM(CASE WHEN h.soh_selisih > 0 THEN h.soh_selisih * COALESCE($hppSql, 0) ELSE 0 END), 0) AS nilai_lebih,
    COALESCE(SUM(CASE WHEN h.soh_selisih < 0 THEN -h.soh_selisih * COALESCE($hppSql, 0) ELSE 0 END), 0) AS nilai_kurang
  ";
  $joinSql = "
    FROM stock_opname s
    LEFT JOIN stock_opname_hasil h
      ON h.soh_stock_opname_id = s.stock_opname_id
     AND h.soh_barang_cabang = s.stock_opname_cabang
     AND h.soh_tipe = s.stock_opname_tipe
    LEFT JOIN barang b ON b.barang_id = h.soh_barang_id
  ";

  $kolomRekap = ['jumlah_sesi', 'jumlah_item', 'item_sesuai', 'item_lebih', 'item_kurang', 'qty_lebih', 'qty_kurang', 'nilai_lebih', 'nilai_kurang'];
  $rekap = [];
  foreach ( $namaBulan as $no => $nama ) {
    $rekap[$no] = array_fill_keys($kolomRekap, 0);
  }
  $rows = query("SELECT MONTH(s.stock_opname_date_proses) AS bulan,
                        COUNT(DISTINCT s.stock_opname_id) AS jumlah_sesi,
                        $agregatSql
                 $joinSql
                 WHERE $where
                 GROUP BY MONTH(s.stock_opname_date_proses)");
  foreach ( $rows as $r ) {
    $rekap[(int) $r['bulan']] = $r;
  }

  $total = array_fill_keys($kolomRekap, 0);
  foreach ( $rekap as $r ) {
    foreach ( $kolomRekap as $k ) {
      $total[$k] += (float) $r[$k];
    }
  }

  $sesiBulan = [];
  if ( $bulan > 0 ) {
    $sesiBulan = query("SELECT s.stock_opname_id, s.stock_opname_date_proses, s.stock_opname_tipe, s.stock_opname_status,
                               u.user_nama AS user_eksekusi_nama,
                               $agregatSql
                        $joinSql
                        LEFT JOIN user u ON u.user_id = s.stock_opname_user_eksekusi
                        WHERE $where AND MONTH(s.stock_opname_date_proses) = $bulan
                        GROUP BY s.stock_opname_id
                        ORDER BY s.stock_opname_date_proses DESC, s.stock_opname_id DESC");
  }

  $filterParams = ['tahun' => $tahun, 'tipe' => $tipe, 'status' => $status];

  function rekapSoLink(array $params) {
    return 'stock-opname-rekap-bulanan?' . http_build_query($params);
  }

  function rekapSoRp($v) {
    return number_format((float) $v, 0, ',', '.');
  }
?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Rekap Bulanan Stock Opname</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Rekap Bulanan</li>
            </ol>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card card-outline card-primary no-print">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-filter"></i> Filter</h3>
          </div>
          <form method="GET" action="stock-opname-rekap-bulanan">
            <div class="card-body">
              <div class="row align-items-end">
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="tahun">Tahun</label>
                    <select name="tahun" id="tahun" class="form-control">
                      <?php for ( $y = $tahunIni; $y >= $tahunIni - 5; $y-- ) : ?>
                        <option value="<?= $y; ?>" <?= $y === $tahun ? 'selected' : ''; ?>><?= $y; ?></option>
                      <?php endfor; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="tipe">Tipe Stock Opname</label>
                    <select name="tipe" id="tipe" class="form-control">
                      <option value="semua" <?= $tipe === 'semua' ? 'selected' : ''; ?>>Semua</option>
                      <option value="0" <?= $tipe === '0' ? 'selected' : ''; ?>>Per Produk</option>
                      <option value="1" <?= $tipe === '1' ? 'selected' : ''; ?>>Keseluruhan</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                      <option value="selesai" <?= $status === 'selesai' ? 'selected' : ''; ?>>Selesai saja</option>
                      <option value="semua" <?= $status === 'semua' ? 'selected' : ''; ?>>Semua (termasuk proses)</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Tampilkan</button>
                    <button type="button" class="btn btn-secondary ml-1" onclick="window.print()"><i class="fa fa-print"></i> Cetak</button>
                  </div>
                </div>
              </div>
              <small class="text-muted">
                Nilai dihitung dari Selisih &times; HPP (harga beli rata-rata, atau harga beli jika rata-rata kosong) saat ini.
              </small>
            </div>
          </form>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Rekap Stock Opname Tahun <?= $tahun; ?></h3>
          </div>
          <div class="card-body table-responsive">
            <table class="table table-bordered table-striped table-sm">
              <thead>
                <tr>
                  <th>Bulan</th>
                  <th class="text-right">Jumlah Sesi</th>
                  <th class="text-right">Jumlah Item</th>
                  <th class="text-right">Sesuai</th>
                  <th class="text-right">Lebih</th>
                  <th class="text-right">Kurang</th>
                  <th class="text-right">Qty Lebih</th>
                  <th class="text-right">Qty Kurang</th>
                  <th class="text-right">Nilai Lebih (Rp)</th>
                  <th class="text-right">Nilai Kurang (Rp)</th>
                  <th class="text-right">Nilai Selisih (Rp)</th>
                  <th class="text-center no-print">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ( $rekap as $no => $r ) : ?>
                  <?php $selisih = (float) $r['nilai_lebih'] - (float) $r['nilai_kurang']; ?>
                  <tr class="<?= $no === $bulan ? 'table-info' : ''; ?>">
                    <td><?= $namaBulan[$no]; ?></td>
                    <td class="text-right"><?= (int) $r['jumlah_sesi']; ?></td>
                    <td class="text-right"><?= (int) $r['jumlah_item']; ?></td>
                    <td class="text-right"><?= (int) $r['item_sesuai']; ?></td>
                    <td class="text-right"><?= (int) $r['item_lebih']; ?></td>
                    <td class="text-right"><?= (int) $r['item_kurang']; ?></td>
                    <td class="text-right"><?= rekapSoRp($r['qty_lebih']); ?></td>
                    <td class="text-right"><?= rekapSoRp($r['qty_kurang']); ?></td>
                    <td class="text-right text-success"><?= rekapSoRp($r['nilai_lebih']); ?></td>
                    <td class="text-right text-danger"><?= rekapSoRp($r['nilai_kurang']); ?></td>
                    <td class="text-right font-weight-bold <?= $selisih < 0 ? 'text-danger' : ''; ?>"><?= rekapSoRp($selisih); ?></td>
                    <td class="text-center no-print">
                      <?php if ( (int) $r['jumlah_sesi'] > 0 ) : ?>
                        <a href="<?= rekapSoLink($filterParams + ['bulan' => $no]); ?>#detail-bulan" class="btn btn-success btn-xs" title="Lihat Sesi">
                          <i class="fa fa-eye"></i>
                        </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <?php $selisihTotal = $total['nilai_lebih'] - $total['nilai_kurang']; ?>
                <tr class="font-weight-bold bg-light">
                  <td>TOTAL</td>
                  <td class="text-right"><?= (int) $total['jumlah_sesi']; ?></td>
                  <td class="text-right"><?= (int) $total['jumlah_item']; ?></td>
                  <td class="text-right"><?= (int) $total['item_sesuai']; ?></td>
                  <td class="text-right"><?= (int) $total['item_lebih']; ?></td>
                  <td class="text-right"><?= (int) $total['item_kurang']; ?></td>
                  <td class="text-right"><?= rekapSoRp($total['qty_lebih']); ?></td>
                  <td class="text-right"><?= rekapSoRp($total['qty_kurang']); ?></td>
                  <td class="text-right text-success"><?= rekapSoRp($total['nilai_lebih']); ?></td>
                  <td class="text-right text-danger"><?= rekapSoRp($total['nilai_kurang']); ?></td>
                  <td class="text-right <?= $selisihTotal < 0 ? 'text-danger' : ''; ?>"><?= rekapSoRp($selisihTotal); ?></td>
                  <td class="no-print"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <?php if ( $bulan > 0 ) : ?>
        <div class="card" id="detail-bulan">
          <div class="card-header">
            <h3 class="card-title">Sesi Stock Opname <?= $namaBulan[$bulan] . ' ' . $tahun; ?></h3>
            <div class="card-tools no-print">
              <a href="<?= rekapSoLink($filterParams); ?>" class="btn btn-tool" title="Tutup"><i class="fas fa-times"></i></a>
            </div>
          </div>
          <div class="card-body table-responsive">
            <table class="table table-bordered table-striped table-sm">
              <thead>
                <tr>
                  <th>No</th>
                  <th>Tanggal Proses</th>
                  <th>Tipe</th>
                  <th>User Eksekusi</th>
                  <th>Status</th>
                  <th class="text-right">Jumlah Item</th>
                  <th class="text-right">Sesuai</th>
                  <th class="text-right">Lebih</th>
                  <th class="text-right">Kurang</th>
                  <th class="text-right">Nilai Lebih (Rp)</th>
                  <th class="text-right">Nilai Kurang (Rp)</th>
                  <th class="text-right">Nilai Selisih (Rp)</th>
                  <th class="text-center no-print">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php $i = 1; ?>
                <?php foreach ( $sesiBulan as $s ) : ?>
                  <?php
                    $selisih  = (float) $s['nilai_lebih'] - (float) $s['nilai_kurang'];
                    $tipeSesi = (int) $s['stock_opname_tipe'];
                  ?>
                  <tr>
                    <td><?= $i++; ?></td>
                    <td><?= tanggal_indo($s['stock_opname_date_proses']); ?></td>
                    <td><?= $tipeSesi > 0 ? 'Keseluruhan' : 'Per Produk'; ?></td>
                    <td><?= htmlspecialchars((string) $s['user_eksekusi_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><b><?= (int) $s['stock_opname_status'] > 0 ? 'Selesai' : 'Proses'; ?></b></td>
                    <td class="text-right"><?= (int) $s['jumlah_item']; ?></td>
                    <td class="text-right"><?= (int) $s['item_sesuai']; ?></td>
                    <td class="text-right"><?= (int) $s['item_lebih']; ?></td>
                    <td class="text-right"><?= (int) $s['item_kurang']; ?></td>
                    <td class="text-right text-success"><?= rekapSoRp($s['nilai_lebih']); ?></td>
                    <td class="text-right text-danger"><?= rekapSoRp($s['nilai_kurang']); ?></td>
                    <td class="text-right font-weight-bold <?= $selisih < 0 ? 'text-danger' : ''; ?>"><?= rekapSoRp($selisih); ?></td>
                    <td class="text-center no-print">
                      <a href="stock-opname-keseluruhan-import-detail?id=<?= base64_encode((string) $s['stock_opname_id']); ?>&tipe=<?= base64_encode((string) $tipeSesi); ?>" class="btn btn-success btn-xs" title="Lihat Data">
                        <i class="fa fa-eye"></i>
                      </a>
                    </td>
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
