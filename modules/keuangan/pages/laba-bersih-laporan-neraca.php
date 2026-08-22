<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
include 'aksi/koneksi.php';
require_once 'aksi/stock-opname-laporan-lib.php';
require_once 'aksi/laba-accural-neraca-lib.php';

if ($levelLogin != "admin" && $levelLogin != "super admin") {
  echo "<script>document.location.href = 'bo';</script>";
  exit;
}

$listCabang = query("SELECT * FROM toko");

$tanggal_neraca = $_POST['tanggal_neraca'] ?? date('Y-m-d');
$user_cabang_login = (int) ($_SESSION['user_cabang'] ?? 0);
$cabang_filter_terkunci = ($user_cabang_login !== 0);
if ($cabang_filter_terkunci) {
  $cabang = (string) $user_cabang_login;
} else {
  $cabang = isset($_POST['cabang']) ? $_POST['cabang'] : ($_SESSION['user_cabang'] ?? '0');
}
$bulan_pilih = substr($tanggal_neraca, 0, 7);

function rupiah($angka)
{
  return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

$toko = query("SELECT * FROM toko WHERE toko_cabang = '$cabang' ")[0];

function hitungPersediaanAkumulasi($conn, int $cabang, string $tanggal): float
{
  return so_laporan_nilai_persediaan_pada_tanggal($conn, $cabang, $tanggal);
}

$neraca_data = labaAccrual_neraca_build($conn, $cabang, $tanggal_neraca);
$neraca = $neraca_data['neraca'];
$total_aktiva = $neraca_data['total_aktiva'];
$total_pasiva = $neraca_data['total_pasiva'];
$total_modal = $neraca_data['total_modal'];
$total_pasiva_modal = $neraca_data['total_pasiva_modal'];
$aktiva_grouped = $neraca_data['aktiva_grouped'];
$pasiva_grouped = $neraca_data['pasiva_grouped'];
$modal_grouped = $neraca_data['modal_grouped'];
$total_harta_lancar = $neraca_data['total_harta_lancar'];
$total_harta_tetap = $neraca_data['total_harta_tetap'];
$jumlah_kategori_ditemukan = $neraca_data['jumlah_kategori_ditemukan'];

$cabang_int = (int) $cabang;
$persediaan_neraca = hitungPersediaanAkumulasi($conn, $cabang_int, $tanggal_neraca);
$persediaan_neraca = max(0.0, $persediaan_neraca);

if ($persediaan_neraca > 0) {
  $total_aktiva += $persediaan_neraca;
  $total_harta_lancar += $persediaan_neraca;
}

$selisih_neraca = abs($total_aktiva - $total_pasiva_modal);
$neraca_seimbang = ($selisih_neraca < 0.01);
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Laporan Neraca</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="laba-bersih-laporan-accural">Laba Rugi Accrual</a></li>
            <li class="breadcrumb-item active">Neraca</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">

      <div class="card card-default">
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
                  <input type="month" id="bulan" class="form-control" value="<?= htmlspecialchars($bulan_pilih, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
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
                  <label for="cabang">Cabang</label>
                  <?php if ($cabang_filter_terkunci) : ?>
                    <input type="hidden" name="cabang" value="<?= htmlspecialchars((string) $cabang, ENT_QUOTES, 'UTF-8') ?>">
                  <?php endif; ?>
                  <select id="cabang" class="form-control"<?= $cabang_filter_terkunci ? ' disabled' : ' name="cabang"' ?>>
                    <?php foreach ($listCabang as $cab) : ?>
                      <?php if ($cabang_filter_terkunci && (int) $cab['toko_cabang'] !== $user_cabang_login) {
                        continue;
                      } ?>
                      <option value="<?= $cab['toko_cabang'] ?>" <?= $cab['toko_cabang'] == $cabang ? 'selected' : '' ?>>
                        <?= $cab['toko_nama'] ?>
                      </option>
                    <?php endforeach; ?>
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
              Neraca menampilkan posisi keuangan <strong>per tanggal</strong> dari saldo COA.
              Laba rugi periode ada di
              <a href="laba-bersih-laporan-accural">Laporan Laba Rugi Accrual</a>.
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

      <div class="card card-success">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h3 class="card-title mb-0">Laporan Neraca</h3>
            <small>Per <?= date('d M Y', strtotime($tanggal_neraca)) ?> &mdash; <?= htmlspecialchars($toko['toko_nama'] ?? '') ?></small>
          </div>
          <div class="card-tools">
            <button type="button" class="btn btn-light btn-sm no-print" onclick="exportNeracaExcel()">
              <i class="fas fa-file-excel text-success"></i> Excel
            </button>
            <button type="button" class="btn btn-light btn-sm ml-1 no-print" onclick="exportNeracaPDF()">
              <i class="fas fa-file-pdf text-danger"></i> PDF
            </button>
            <button type="button" class="btn btn-info btn-sm ml-1 no-print" onclick="window.print()">
              <i class="fas fa-print"></i> Print
            </button>
          </div>
        </div>
        <div class="card-body" id="neraca-content">

          <?php if ($jumlah_kategori_ditemukan == 0) : ?>
            <div class="alert alert-warning">
              <strong>Peringatan:</strong> Tidak ada kategori neraca untuk cabang ini.<br>
              <small>Pastikan <code>laba_kategori</code> memiliki akun dengan kategori <strong>aktiva</strong>, <strong>pasiva</strong>, atau <strong>modal</strong>.</small>
            </div>
          <?php endif; ?>

          <div class="row">
            <div class="col-md-6">
              <table class="table table-bordered">
                <thead>
                  <tr class="bg-info">
                    <th colspan="3"><strong>AKTIVA (Harta)</strong></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($aktiva_grouped)) : ?>
                    <?php foreach ($aktiva_grouped as $prefix => $group) : ?>
                      <?php if ($group['total'] != 0) : ?>
                        <?php foreach ($group['items'] as $akt) : ?>
                          <?php if ($akt['saldo_akhir'] != 0) : ?>
                            <tr>
                              <td style="width: 20%;"><?= htmlspecialchars($akt['kode_akun']) ?></td>
                              <td style="padding-left: 20px;"><?= htmlspecialchars($akt['name']) ?></td>
                              <td style="width: 30%; text-align: right;"><?= rupiah($akt['saldo_akhir']) ?></td>
                            </tr>
                          <?php endif; ?>
                        <?php endforeach; ?>
                        <tr class="bg-light">
                          <td colspan="2" class="text-right"><strong>Total <?= htmlspecialchars($prefix) ?></strong></td>
                          <td class="text-right"><strong><?= rupiah($group['total']) ?></strong></td>
                        </tr>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php elseif (!empty($neraca['aktiva'])) : ?>
                    <?php foreach ($neraca['aktiva'] as $akt) : ?>
                      <tr>
                        <td><?= htmlspecialchars($akt['kode_akun']) ?></td>
                        <td><?= htmlspecialchars($akt['name']) ?></td>
                        <td class="text-right"><?= rupiah($akt['saldo_akhir']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else : ?>
                    <tr><td colspan="3" class="text-center text-muted">Tidak ada data aktiva</td></tr>
                  <?php endif; ?>

                  <?php if ($total_harta_lancar != 0) : ?>
                    <tr>
                      <td colspan="2"><strong>Total Harta Lancar</strong></td>
                      <td class="text-right"><strong><?= rupiah($total_harta_lancar) ?></strong></td>
                    </tr>
                  <?php endif; ?>

                  <?php if ($total_harta_tetap != 0) : ?>
                    <tr>
                      <td colspan="2"><strong>Total Harta Tetap</strong></td>
                      <td class="text-right"><strong><?= rupiah($total_harta_tetap) ?></strong></td>
                    </tr>
                  <?php endif; ?>

                  <?php if ($persediaan_neraca > 0) : ?>
                    <tr>
                      <td>1-103</td>
                      <td style="padding-left: 20px;"><strong>Persediaan Barang</strong></td>
                      <td class="text-right"><?= rupiah($persediaan_neraca) ?></td>
                    </tr>
                  <?php endif; ?>

                  <tr class="bg-info">
                    <td colspan="2"><strong>TOTAL HARTA</strong></td>
                    <td class="text-right"><strong><?= rupiah($total_aktiva) ?></strong></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="col-md-6">
              <table class="table table-bordered">
                <thead>
                  <tr class="bg-warning">
                    <th colspan="3"><strong>KEWAJIBAN DAN MODAL</strong></th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td colspan="3" class="bg-light"><strong>Kewajiban</strong></td></tr>
                  <?php if (!empty($pasiva_grouped)) : ?>
                    <?php foreach ($pasiva_grouped as $prefix => $group) : ?>
                      <?php if ($group['total'] != 0) : ?>
                        <?php foreach ($group['items'] as $pas) : ?>
                          <?php if ($pas['saldo_akhir'] != 0) : ?>
                            <tr>
                              <td style="width: 20%;"><?= htmlspecialchars($pas['kode_akun']) ?></td>
                              <td style="padding-left: 20px;"><?= htmlspecialchars($pas['name']) ?></td>
                              <td class="text-right"><?= rupiah($pas['saldo_akhir']) ?></td>
                            </tr>
                          <?php endif; ?>
                        <?php endforeach; ?>
                        <tr class="bg-light">
                          <td colspan="2" class="text-right"><strong>Total <?= htmlspecialchars($prefix) ?></strong></td>
                          <td class="text-right"><strong><?= rupiah($group['total']) ?></strong></td>
                        </tr>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php elseif (!empty($neraca['pasiva'])) : ?>
                    <?php foreach ($neraca['pasiva'] as $pas) : ?>
                      <tr>
                        <td><?= htmlspecialchars($pas['kode_akun']) ?></td>
                        <td><?= htmlspecialchars($pas['name']) ?></td>
                        <td class="text-right"><?= rupiah($pas['saldo_akhir']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else : ?>
                    <tr><td colspan="3" class="text-center text-muted">Tidak ada data kewajiban</td></tr>
                  <?php endif; ?>
                  <tr>
                    <td colspan="2" class="text-right"><strong>Total Kewajiban</strong></td>
                    <td class="text-right"><strong><?= rupiah($total_pasiva) ?></strong></td>
                  </tr>

                  <tr><td colspan="3" class="bg-light"><strong>Modal</strong></td></tr>
                  <?php if (!empty($modal_grouped)) : ?>
                    <?php foreach ($modal_grouped as $prefix => $group) : ?>
                      <?php if ($group['total'] != 0) : ?>
                        <?php foreach ($group['items'] as $mod) : ?>
                          <?php if ($mod['saldo_akhir'] != 0) : ?>
                            <tr>
                              <td><?= htmlspecialchars($mod['kode_akun']) ?></td>
                              <td><?= htmlspecialchars($mod['name']) ?></td>
                              <td class="text-right"><?= rupiah($mod['saldo_akhir']) ?></td>
                            </tr>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php elseif (!empty($neraca['modal'])) : ?>
                    <?php foreach ($neraca['modal'] as $mod) : ?>
                      <tr>
                        <td><?= htmlspecialchars($mod['kode_akun']) ?></td>
                        <td><?= htmlspecialchars($mod['name']) ?></td>
                        <td class="text-right"><?= rupiah($mod['saldo_akhir']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else : ?>
                    <tr><td colspan="3" class="text-center text-muted">Tidak ada data modal</td></tr>
                  <?php endif; ?>
                  <tr>
                    <td colspan="2" class="text-right"><strong>Total Modal</strong></td>
                    <td class="text-right"><strong><?= rupiah($total_modal) ?></strong></td>
                  </tr>

                  <tr class="bg-warning">
                    <td colspan="2"><strong>TOTAL KEWAJIBAN DAN MODAL</strong></td>
                    <td class="text-right"><strong><?= rupiah($total_pasiva_modal) ?></strong></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="alert alert-info mt-3">
            <strong>Keseimbangan Neraca (per <?= date('d/m/Y', strtotime($tanggal_neraca)) ?>):</strong><br>
            Total Harta: <strong><?= rupiah($total_aktiva) ?></strong><br>
            Total Kewajiban dan Modal: <strong><?= rupiah($total_pasiva_modal) ?></strong><br>
            <?php if ($neraca_seimbang) : ?>
              <span class="text-success"><strong>✓ Neraca Seimbang</strong></span>
            <?php else : ?>
              <span class="text-danger"><strong>⚠ Selisih: <?= rupiah($selisih_neraca) ?></strong></span><br>
              <small>Selisih wajar jika laba/rugi periode belum ditutup ke modal. Lihat <a href="laba-bersih-laporan-accural">Laporan Laba Rugi Accrual</a>.</small>
            <?php endif; ?>
          </div>

        </div>
      </div>
    </div>
  </section>
</div>

<style>
@media print {
  .content-header, .card-default, .card-tools, .main-sidebar, .main-header, .main-footer, .breadcrumb, .no-print {
    display: none !important;
  }
  .content-wrapper { margin-left: 0 !important; padding: 0 !important; }
  .card { border: none !important; box-shadow: none !important; }
}
</style>

<script>
function exportNeracaExcel() {
  try {
    const toko = '<?= addslashes($toko['toko_nama'] ?? 'Laporan') ?>';
    const tanggal = '<?= date('d-m-Y', strtotime($tanggal_neraca)) ?>';
    const filename = 'Neraca_' + toko.replace(/\s+/g, '_') + '_' + tanggal;
    let html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    html += '<head><meta charset="UTF-8"></head><body><table border="1">';
    html += '<tr><td colspan="3" style="font-size:16pt;font-weight:bold;text-align:center;">LAPORAN NERACA</td></tr>';
    html += '<tr><td colspan="3" style="text-align:center;">' + toko + '</td></tr>';
    html += '<tr><td colspan="3" style="text-align:center;">Per ' + tanggal + '</td></tr><tr><td colspan="3"></td></tr>';
    document.querySelectorAll('#neraca-content table').forEach(function (table) {
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
    Swal.fire({ icon: 'success', title: 'Export Berhasil', timer: 2000, showConfirmButton: false });
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Gagal Export', text: err.message });
  }
}

function exportNeracaPDF() {
  const toko = '<?= addslashes($toko['toko_nama'] ?? 'Laporan') ?>';
  const content = document.getElementById('neraca-content').innerHTML;
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <!DOCTYPE html><html><head>
    <title>Neraca - ${toko}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <style>body{font-size:12px;padding:20px}.table{font-size:11px}</style>
    </head><body>
    <div class="text-center mb-3">
      <h2>LAPORAN NERACA</h2><h4>${toko}</h4>
      <p>Per <?= date('d M Y', strtotime($tanggal_neraca)) ?></p>
    </div>${content}
    <script>window.onload=function(){window.print();setTimeout(function(){window.close();},500);};<\/script>
    </body></html>`);
  printWindow.document.close();
}
</script>

<?php include '_footerlaporan.php' ?>
<?php include '_footer.php'; ?>
