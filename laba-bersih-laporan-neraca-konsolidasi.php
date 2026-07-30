<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
include 'aksi/koneksi.php';
require_once 'aksi/cabang-arsip-lib.php';
require_once 'aksi/stock-opname-laporan-lib.php';
require_once 'aksi/laba-accural-neraca-lib.php';

if ($levelLogin != "admin" && $levelLogin != "super admin") {
  echo "<script>document.location.href = 'bo';</script>";
  exit;
}

$user_cabang_login = (int) ($_SESSION['user_cabang'] ?? 0);
if ($user_cabang_login !== 0 && $levelLogin !== 'super admin') {
  echo "<script>
    Swal.fire({icon:'warning',title:'Akses terbatas',text:'Neraca Konsolidasi hanya untuk pusat (Nugrosir) / super admin.'})
      .then(function(){ document.location.href = 'laba-bersih-laporan-neraca'; });
  </script>";
  include '_footer.php';
  exit;
}

$tanggal_neraca = $_POST['tanggal_neraca'] ?? ($_GET['tanggal_neraca'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_neraca)) {
  $tanggal_neraca = date('Y-m-d');
}
$bulan_pilih = substr($tanggal_neraca, 0, 7);
$tampilkan_rincian = isset($_POST['tampilkan_rincian']) ? (int) $_POST['tampilkan_rincian'] : 1;

function rupiah($angka)
{
  return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

$cabang_ids = labaAccrual_neraca_cabang_konsolidasi($conn);
$data = labaAccrual_neraca_build_konsolidasi($conn, $tanggal_neraca, $cabang_ids);

$neraca = $data['neraca'];
$total_aktiva = $data['total_aktiva'];
$total_pasiva = $data['total_pasiva'];
$total_modal = $data['total_modal'];
$total_pasiva_modal = $data['total_pasiva_modal'];
$modal_grouped = $data['modal_grouped'];
$total_harta_lancar = $data['total_harta_lancar'];
$total_harta_tetap = $data['total_harta_tetap'];
$total_liabilitas_jp = $data['total_liabilitas_jangka_pendek'];
$total_liabilitas_jpj = $data['total_liabilitas_jangka_panjang'];
$persediaan_total = $data['persediaan_total'];
$persediaan_per_cabang = $data['persediaan_per_cabang'];
$eliminasi = $data['eliminasi'];
$double_count_dicegah = (float) ($data['double_count_dicegah'] ?? 0);
$per_cabang_summary = $data['per_cabang_summary'];
$nama_cabang = $data['nama_cabang'];
$laba_rugi_total = $data['laba_rugi_total'];
$pusat_nama = $data['pusat_nama'];
$jumlah_kategori_ditemukan = $data['jumlah_kategori_ditemukan'];
$selisih_neraca = abs($total_aktiva - $total_pasiva_modal);
$neraca_seimbang = ($selisih_neraca < 1.0);

$daftar_cabang_label = [];
foreach ($cabang_ids as $cid) {
  $daftar_cabang_label[] = ($nama_cabang[$cid] ?? ('Cabang ' . $cid)) . ((int) $cid === 0 ? ' ★' : '');
}
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Laporan Neraca Konsolidasi</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="laba-bersih-laporan-neraca">Neraca</a></li>
            <li class="breadcrumb-item active">Konsolidasi</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">

      <div class="card card-default no-print">
        <div class="card-header">
          <h3 class="card-title">Filter Data</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
          </div>
        </div>
        <form method="POST">
          <div class="card-body">
            <div class="row">
              <div class="col-md-2">
                <div class="form-group">
                  <label for="bulan">Bulan</label>
                  <input type="month" id="bulan" class="form-control" value="<?= htmlspecialchars($bulan_pilih, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="tanggal_neraca">Per Tanggal</label>
                  <input type="date" name="tanggal_neraca" id="tanggal_neraca" class="form-control" value="<?= htmlspecialchars($tanggal_neraca, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="tampilkan_rincian">Rincian per Cabang</label>
                  <select name="tampilkan_rincian" id="tampilkan_rincian" class="form-control">
                    <option value="1" <?= $tampilkan_rincian ? 'selected' : '' ?>>Tampilkan</option>
                    <option value="0" <?= !$tampilkan_rincian ? 'selected' : '' ?>>Sembunyikan</option>
                  </select>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label>&nbsp;</label>
                  <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa fa-filter"></i> Tampilkan
                  </button>
                </div>
              </div>
            </div>
            <small class="text-muted d-block">
              Neraca konsolidasi menyajikan posisi keuangan grup dengan
              <strong><?= htmlspecialchars($pusat_nama) ?></strong> sebagai pusat perusahaan,
              digabung per <code>kode_akun</code>, plus eliminasi antar-cabang dan valuasi persediaan operasional.
              Acuan penyajian: SAK ETAP / praktik neraca modern (aset lancar–tidak lancar, liabilitas JP–JPJ, ekuitas).
            </small>
          </div>
        </form>
        <script>
        (function () {
          var elBulan = document.getElementById('bulan');
          var elTanggal = document.getElementById('tanggal_neraca');
          if (!elBulan || !elTanggal) return;
          function syncTanggalDariBulan() {
            var v = elBulan.value;
            if (!v || v.length < 7) return;
            var p = v.split('-');
            var y = parseInt(p[0], 10);
            var m = parseInt(p[1], 10);
            if (!y || !m) return;
            var lastD = new Date(y, m, 0).getDate();
            elTanggal.value = v + '-' + (lastD < 10 ? '0' : '') + lastD;
          }
          elBulan.addEventListener('change', syncTanggalDariBulan);
          elTanggal.addEventListener('change', function () {
            if (elTanggal.value.length >= 7) elBulan.value = elTanggal.value.substring(0, 7);
          });
        })();
        </script>
      </div>

      <div class="row no-print">
        <div class="col-md-3 col-sm-6">
          <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-landmark"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Total Aset</span>
              <span class="info-box-number" style="font-size:1rem;"><?= rupiah($total_aktiva) ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="info-box">
            <span class="info-box-icon bg-warning"><i class="fas fa-file-invoice-dollar"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Total Liabilitas</span>
              <span class="info-box-number" style="font-size:1rem;"><?= rupiah($total_pasiva) ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-piggy-bank"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Total Ekuitas</span>
              <span class="info-box-number" style="font-size:1rem;"><?= rupiah($total_modal) ?></span>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="info-box">
            <span class="info-box-icon <?= $neraca_seimbang ? 'bg-success' : 'bg-danger' ?>"><i class="fas fa-balance-scale"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Status</span>
              <span class="info-box-number" style="font-size:1rem;">
                <?= $neraca_seimbang ? 'Seimbang' : ('Selisih ' . rupiah($selisih_neraca)) ?>
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="card card-primary">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
          <div>
            <h3 class="card-title mb-0">Neraca Konsolidasi — Grup <?= htmlspecialchars($pusat_nama) ?></h3>
            <small class="d-block text-white-50">
              Per <?= date('d M Y', strtotime($tanggal_neraca)) ?>
              &mdash; Entitas: <?= htmlspecialchars(implode(', ', $daftar_cabang_label)) ?>
            </small>
          </div>
          <div class="card-tools mt-2 mt-md-0">
            <a href="laba-bersih-laporan-neraca" class="btn btn-light btn-sm no-print">Neraca per Cabang</a>
            <button type="button" class="btn btn-light btn-sm no-print" onclick="exportNeracaKonsolidasiExcel()">
              <i class="fas fa-file-excel text-success"></i> Excel
            </button>
            <button type="button" class="btn btn-light btn-sm ml-1 no-print" onclick="exportNeracaKonsolidasiPDF()">
              <i class="fas fa-file-pdf text-danger"></i> PDF
            </button>
            <button type="button" class="btn btn-info btn-sm ml-1 no-print" onclick="window.print()">
              <i class="fas fa-print"></i> Print
            </button>
          </div>
        </div>
        <div class="card-body" id="neraca-konsolidasi-content">

          <?php if ($jumlah_kategori_ditemukan == 0) : ?>
            <div class="alert alert-warning">
              <strong>Peringatan:</strong> Tidak ada kategori neraca pada cabang yang digabung.
            </div>
          <?php endif; ?>

          <?php if ($double_count_dicegah >= 1) : ?>
            <div class="alert alert-info no-print">
              <strong>Penyesuaian double-count:</strong>
              Sistem mengeliminasi <strong><?= rupiah($double_count_dicegah) ?></strong>
              agar akun lokasi / header tidak dihitung dobel (saldo di pusat + cabang, atau induk + anak).
              Rincian ada di catatan eliminasi di bawah.
            </div>
          <?php endif; ?>

          <div class="row">
            <div class="col-md-6">
              <table class="table table-bordered table-sm">
                <thead>
                  <tr class="bg-info">
                    <th colspan="3"><strong>ASET</strong></th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $aktiva_lancar_items = [];
                  $aktiva_tetap_items = [];
                  foreach ($neraca['aktiva'] as $akt) {
                    if (abs((float) $akt['saldo_akhir']) < 0.005) {
                      continue;
                    }
                    $klas = labaAccrual_neraca_klasifikasi_aktiva($akt['prefix_group'] ?? '');
                    if ($klas === 'tidak_lancar') {
                      $aktiva_tetap_items[] = $akt;
                    } else {
                      $aktiva_lancar_items[] = $akt;
                    }
                  }
                  ?>
                  <tr class="bg-light"><td colspan="3"><strong>Aset Lancar</strong></td></tr>
                  <?php if (!empty($aktiva_lancar_items)) : ?>
                    <?php
                    $grp_l = labaAccrual_neraca_group_by_prefix($aktiva_lancar_items);
                    foreach ($grp_l as $prefix => $group) :
                      if (abs($group['total']) < 0.005) {
                        continue;
                      }
                      foreach ($group['items'] as $akt) :
                    ?>
                        <tr>
                          <td style="width:18%;"><?= htmlspecialchars($akt['kode_akun']) ?></td>
                          <td><?= htmlspecialchars($akt['name']) ?></td>
                          <td style="width:28%;" class="text-right"><?= rupiah($akt['saldo_akhir']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                      <tr class="bg-light">
                        <td colspan="2" class="text-right"><strong>Subtotal <?= htmlspecialchars($prefix) ?></strong></td>
                        <td class="text-right"><strong><?= rupiah($group['total']) ?></strong></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                  <?php if ($persediaan_total > 0) : ?>
                    <tr>
                      <td>1-1500</td>
                      <td><strong>Persediaan Barang Dagangan</strong> <small class="text-muted">(valuasi stok)</small></td>
                      <td class="text-right"><?= rupiah($persediaan_total) ?></td>
                    </tr>
                  <?php endif; ?>
                  <?php if (empty($aktiva_lancar_items) && $persediaan_total <= 0) : ?>
                    <tr><td colspan="3" class="text-center text-muted">Tidak ada aset lancar</td></tr>
                  <?php endif; ?>
                  <tr>
                    <td colspan="2"><strong>Jumlah Aset Lancar</strong></td>
                    <td class="text-right"><strong><?= rupiah($total_harta_lancar) ?></strong></td>
                  </tr>

                  <tr class="bg-light"><td colspan="3"><strong>Aset Tidak Lancar</strong></td></tr>
                  <?php if (!empty($aktiva_tetap_items)) : ?>
                    <?php
                    $grp_t = labaAccrual_neraca_group_by_prefix($aktiva_tetap_items);
                    foreach ($grp_t as $prefix => $group) :
                      if (abs($group['total']) < 0.005) {
                        continue;
                      }
                      foreach ($group['items'] as $akt) :
                    ?>
                        <tr>
                          <td><?= htmlspecialchars($akt['kode_akun']) ?></td>
                          <td><?= htmlspecialchars($akt['name']) ?></td>
                          <td class="text-right"><?= rupiah($akt['saldo_akhir']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                      <tr class="bg-light">
                        <td colspan="2" class="text-right"><strong>Subtotal <?= htmlspecialchars($prefix) ?></strong></td>
                        <td class="text-right"><strong><?= rupiah($group['total']) ?></strong></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else : ?>
                    <tr><td colspan="3" class="text-center text-muted">Tidak ada aset tidak lancar</td></tr>
                  <?php endif; ?>
                  <tr>
                    <td colspan="2"><strong>Jumlah Aset Tidak Lancar</strong></td>
                    <td class="text-right"><strong><?= rupiah($total_harta_tetap) ?></strong></td>
                  </tr>

                  <tr class="bg-info">
                    <td colspan="2"><strong>TOTAL ASET</strong></td>
                    <td class="text-right"><strong><?= rupiah($total_aktiva) ?></strong></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="col-md-6">
              <table class="table table-bordered table-sm">
                <thead>
                  <tr class="bg-warning">
                    <th colspan="3"><strong>LIABILITAS DAN EKUITAS</strong></th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $pasiva_jp_items = [];
                  $pasiva_jpj_items = [];
                  foreach ($neraca['pasiva'] as $pas) {
                    if (abs((float) $pas['saldo_akhir']) < 0.005) {
                      continue;
                    }
                    $klas = labaAccrual_neraca_klasifikasi_pasiva($pas['prefix_group'] ?? '', $pas['kode_akun'] ?? '');
                    if ($klas === 'jangka_panjang') {
                      $pasiva_jpj_items[] = $pas;
                    } else {
                      $pasiva_jp_items[] = $pas;
                    }
                  }
                  ?>
                  <tr class="bg-light"><td colspan="3"><strong>Liabilitas Jangka Pendek</strong></td></tr>
                  <?php if (!empty($pasiva_jp_items)) : ?>
                    <?php
                    $grp_jp = labaAccrual_neraca_group_by_prefix($pasiva_jp_items);
                    foreach ($grp_jp as $prefix => $group) :
                      foreach ($group['items'] as $pas) :
                    ?>
                        <tr>
                          <td style="width:18%;"><?= htmlspecialchars($pas['kode_akun']) ?></td>
                          <td><?= htmlspecialchars($pas['name']) ?></td>
                          <td style="width:28%;" class="text-right"><?= rupiah($pas['saldo_akhir']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                      <tr class="bg-light">
                        <td colspan="2" class="text-right"><strong>Subtotal <?= htmlspecialchars($prefix) ?></strong></td>
                        <td class="text-right"><strong><?= rupiah($group['total']) ?></strong></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else : ?>
                    <tr><td colspan="3" class="text-center text-muted">Tidak ada liabilitas jangka pendek</td></tr>
                  <?php endif; ?>
                  <tr>
                    <td colspan="2" class="text-right"><strong>Jumlah Liabilitas Jangka Pendek</strong></td>
                    <td class="text-right"><strong><?= rupiah($total_liabilitas_jp) ?></strong></td>
                  </tr>

                  <tr class="bg-light"><td colspan="3"><strong>Liabilitas Jangka Panjang</strong></td></tr>
                  <?php if (!empty($pasiva_jpj_items)) : ?>
                    <?php
                    $grp_jpj = labaAccrual_neraca_group_by_prefix($pasiva_jpj_items);
                    foreach ($grp_jpj as $prefix => $group) :
                      foreach ($group['items'] as $pas) :
                    ?>
                        <tr>
                          <td><?= htmlspecialchars($pas['kode_akun']) ?></td>
                          <td><?= htmlspecialchars($pas['name']) ?></td>
                          <td class="text-right"><?= rupiah($pas['saldo_akhir']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                      <tr class="bg-light">
                        <td colspan="2" class="text-right"><strong>Subtotal <?= htmlspecialchars($prefix) ?></strong></td>
                        <td class="text-right"><strong><?= rupiah($group['total']) ?></strong></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else : ?>
                    <tr><td colspan="3" class="text-center text-muted">Tidak ada liabilitas jangka panjang</td></tr>
                  <?php endif; ?>
                  <tr>
                    <td colspan="2" class="text-right"><strong>Jumlah Liabilitas Jangka Panjang</strong></td>
                    <td class="text-right"><strong><?= rupiah($total_liabilitas_jpj) ?></strong></td>
                  </tr>
                  <tr>
                    <td colspan="2" class="text-right"><strong>Total Liabilitas</strong></td>
                    <td class="text-right"><strong><?= rupiah($total_pasiva) ?></strong></td>
                  </tr>

                  <tr class="bg-light"><td colspan="3"><strong>Ekuitas</strong></td></tr>
                  <?php if (!empty($modal_grouped)) : ?>
                    <?php foreach ($modal_grouped as $prefix => $group) : ?>
                      <?php if (abs($group['total']) < 0.005) {
                        continue;
                      } ?>
                      <?php foreach ($group['items'] as $mod) : ?>
                        <?php if (abs($mod['saldo_akhir']) < 0.005) {
                          continue;
                        } ?>
                        <tr>
                          <td><?= htmlspecialchars($mod['kode_akun']) ?></td>
                          <td><?= htmlspecialchars($mod['name']) ?></td>
                          <td class="text-right"><?= rupiah($mod['saldo_akhir']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endforeach; ?>
                  <?php else : ?>
                    <tr><td colspan="3" class="text-center text-muted">Tidak ada data ekuitas</td></tr>
                  <?php endif; ?>
                  <tr>
                    <td colspan="2" class="text-right"><strong>Total Ekuitas</strong></td>
                    <td class="text-right"><strong><?= rupiah($total_modal) ?></strong></td>
                  </tr>

                  <tr class="bg-warning">
                    <td colspan="2"><strong>TOTAL LIABILITAS DAN EKUITAS</strong></td>
                    <td class="text-right"><strong><?= rupiah($total_pasiva_modal) ?></strong></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="alert alert-<?= $neraca_seimbang ? 'success' : 'warning' ?> mt-3">
            <strong>Uji Keseimbangan (Persamaan Akuntansi):</strong>
            Aset = Liabilitas + Ekuitas<br>
            Total Aset: <strong><?= rupiah($total_aktiva) ?></strong> &nbsp;|&nbsp;
            Total Liabilitas + Ekuitas: <strong><?= rupiah($total_pasiva_modal) ?></strong><br>
            <?php if ($neraca_seimbang) : ?>
              <span class="text-success"><strong>✓ Neraca konsolidasi seimbang</strong></span>
            <?php else : ?>
              <span class="text-danger"><strong>⚠ Selisih: <?= rupiah($selisih_neraca) ?></strong></span>
              <br><small>Periksa posting Data Operasional, tutup buku laba/rugi, atau sinkronisasi COA antar cabang.</small>
            <?php endif; ?>
            <br><small>Laba/(rugi) grup s.d. tanggal (dari COA pendapatan − beban): <strong><?= rupiah($laba_rugi_total) ?></strong></small>
          </div>

          <?php if (!empty($eliminasi)) : ?>
            <div class="card card-outline card-secondary mt-3">
              <div class="card-header">
                <h3 class="card-title">Catatan Eliminasi Konsolidasi</h3>
              </div>
              <div class="card-body p-0">
                <table class="table table-sm mb-0">
                  <thead>
                    <tr>
                      <th>Kode</th>
                      <th>Akun</th>
                      <th>Alasan</th>
                      <th class="text-right">Jumlah Disesuaikan</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($eliminasi as $elim) : ?>
                      <tr>
                        <td><?= htmlspecialchars($elim['kode_akun'] ?? '') ?></td>
                        <td><?= htmlspecialchars($elim['name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($elim['alasan'] ?? '') ?></td>
                        <td class="text-right"><?= rupiah($elim['jumlah'] ?? 0) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($tampilkan_rincian && !empty($per_cabang_summary)) : ?>
            <div class="card card-outline card-info mt-3">
              <div class="card-header">
                <h3 class="card-title">Ringkasan per Unit Usaha</h3>
              </div>
              <div class="card-body p-0 table-responsive">
                <table class="table table-bordered table-sm mb-0">
                  <thead class="bg-light">
                    <tr>
                      <th>Unit</th>
                      <th class="text-right">Aset (+ Persediaan)</th>
                      <th class="text-right">Liabilitas</th>
                      <th class="text-right">Ekuitas COA</th>
                      <th class="text-right">Persediaan</th>
                      <th class="text-right">Laba/(Rugi) s.d. Tgl</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($per_cabang_summary as $row) : ?>
                      <tr class="<?= !empty($row['is_pusat']) ? 'table-primary' : '' ?>">
                        <td>
                          <?= htmlspecialchars($row['nama']) ?>
                          <?php if (!empty($row['is_pusat'])) : ?>
                            <span class="badge badge-primary">Pusat</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-right"><?= rupiah($row['total_aktiva']) ?></td>
                        <td class="text-right"><?= rupiah($row['total_pasiva']) ?></td>
                        <td class="text-right"><?= rupiah($row['total_modal']) ?></td>
                        <td class="text-right"><?= rupiah($row['persediaan']) ?></td>
                        <td class="text-right"><?= rupiah($row['laba_rugi']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                  <tfoot>
                    <tr class="bg-light">
                      <th>Konsolidasi (setelah eliminasi &amp; penyesuaian)</th>
                      <th class="text-right"><?= rupiah($total_aktiva) ?></th>
                      <th class="text-right"><?= rupiah($total_pasiva) ?></th>
                      <th class="text-right"><?= rupiah($total_modal) ?></th>
                      <th class="text-right"><?= rupiah($persediaan_total) ?></th>
                      <th class="text-right"><?= rupiah($laba_rugi_total) ?></th>
                    </tr>
                  </tfoot>
                </table>
              </div>
              <?php if (!empty($persediaan_per_cabang)) : ?>
                <div class="card-footer">
                  <small class="text-muted">
                    Persediaan per unit:
                    <?php
                    $bits = [];
                    foreach ($persediaan_per_cabang as $cbg => $nil) {
                      if ($nil <= 0) {
                        continue;
                      }
                      $bits[] = ($nama_cabang[$cbg] ?? $cbg) . ': ' . rupiah($nil);
                    }
                    echo htmlspecialchars(implode(' · ', $bits));
                    ?>
                  </small>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </section>
</div>

<style>
@media print {
  .content-header, .card-default, .card-tools, .main-sidebar, .main-header, .main-footer, .breadcrumb, .no-print, .info-box {
    display: none !important;
  }
  .content-wrapper { margin-left: 0 !important; padding: 0 !important; }
  .card { border: none !important; box-shadow: none !important; }
}
</style>

<script>
function exportNeracaKonsolidasiExcel() {
  try {
    const tanggal = '<?= date('d-m-Y', strtotime($tanggal_neraca)) ?>';
    const filename = 'Neraca_Konsolidasi_Nugrosir_' + tanggal;
    let html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    html += '<head><meta charset="UTF-8"></head><body><table border="1">';
    html += '<tr><td colspan="3" style="font-size:16pt;font-weight:bold;text-align:center;">LAPORAN NERACA KONSOLIDASI</td></tr>';
    html += '<tr><td colspan="3" style="text-align:center;"><?= addslashes($pusat_nama) ?> (Pusat Grup)</td></tr>';
    html += '<tr><td colspan="3" style="text-align:center;">Per ' + tanggal + '</td></tr><tr><td colspan="3"></td></tr>';
    document.querySelectorAll('#neraca-konsolidasi-content table').forEach(function (table) {
      table.querySelectorAll('tr').forEach(function (row) {
        html += '<tr>';
        row.querySelectorAll('th, td').forEach(function (cell) {
          const colspan = cell.getAttribute('colspan') || 1;
          const text = cell.innerText.trim().replace(/\n/g, ' ');
          const isBold = cell.querySelector('b, strong') || cell.tagName === 'TH';
          html += '<td colspan="' + colspan + '" style="' + (isBold ? 'font-weight:bold;' : '') + '">' + text + '</td>';
        });
        html += '</tr>';
      });
      html += '<tr><td colspan="3"></td></tr>';
    });
    html += '</table></body></html>';
    const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '.xls';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    if (typeof Swal !== 'undefined') {
      Swal.fire({ icon: 'success', title: 'Export Berhasil', timer: 2000, showConfirmButton: false });
    }
  } catch (err) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({ icon: 'error', title: 'Gagal Export', text: err.message });
    } else {
      alert(err.message);
    }
  }
}

function exportNeracaKonsolidasiPDF() {
  const content = document.getElementById('neraca-konsolidasi-content').innerHTML;
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <!DOCTYPE html><html><head>
    <title>Neraca Konsolidasi - <?= addslashes($pusat_nama) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <style>body{font-size:12px;padding:20px}.table{font-size:11px}</style>
    </head><body>
    <div class="text-center mb-3">
      <h2>LAPORAN NERACA KONSOLIDASI</h2>
      <h4><?= htmlspecialchars($pusat_nama) ?> (Pusat Grup)</h4>
      <p>Per <?= date('d M Y', strtotime($tanggal_neraca)) ?></p>
    </div>${content}
    <script>window.onload=function(){window.print();setTimeout(function(){window.close();},500);};<\/script>
    </body></html>`);
  printWindow.document.close();
}
</script>

<?php include '_footerlaporan.php' ?>
<?php include '_footer.php'; ?>
