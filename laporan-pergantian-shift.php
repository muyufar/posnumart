<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin === 'kurir') {
    echo "<script>document.location.href = 'bo';</script>";
    exit;
}

$pakaiPergantianShift = $sessionCabang >= 1;
$tampilPiutang = !$pakaiPergantianShift;
$judulLaporan = $pakaiPergantianShift ? 'Laporan Pergantian Shift' : 'Laporan Penjualan Harian';
$labelMenuBreadcrumb = $pakaiPergantianShift ? 'Pergantian Shift' : 'Penjualan Harian';

$defaultWaToko = isset($dataTokoLogin['toko_wa']) ? trim((string) $dataTokoLogin['toko_wa']) : '';
?>

<div class="content-wrapper">
  <section class="content-header no-print">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1><?= htmlspecialchars($judulLaporan, ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item">Laporan</li>
            <li class="breadcrumb-item">Penjualan</li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($labelMenuBreadcrumb, ENT_QUOTES, 'UTF-8') ?></li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card card-default no-print">
        <div class="card-header">
          <h3 class="card-title">Filter Laporan</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
          </div>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-2">
              <div class="form-group">
                <label for="filter_tanggal">Tanggal</label>
                <input type="date" id="filter_tanggal" class="form-control" value="<?= date('Y-m-d') ?>">
              </div>
            </div>
            <div class="col-md-2 col-filter-shift">
              <div class="form-group">
            <?php if (!$pakaiPergantianShift) { ?>
            <style>.col-filter-shift{display:none!important}</style>
            <input type="hidden" id="filter_shift" value="harian">
            <?php } ?>
                <label for="filter_shift">Shift</label>
                <select id="filter_shift" class="form-control">
                  <option value="pagi">Pagi</option>
                  <option value="sore">Siang</option>
                </select>
              </div>
            </div>
            <div class="col-md-2 col-filter-shift">
              <div class="form-group">
                <label for="filter_jam_mulai">Jam mulai</label>
                <input type="time" id="filter_jam_mulai" class="form-control" value="07:00">
              </div>
            </div>
            <div class="col-md-2 col-filter-shift">
              <div class="form-group">
                <label for="filter_jam_selesai">Jam selesai</label>
                <input type="time" id="filter_jam_selesai" class="form-control" value="13:59">
              </div>
            </div>
            <div class="col-md-4">
              <label>&nbsp;</label>
              <div class="d-flex flex-wrap" style="gap: 8px;">
                <button type="button" class="btn btn-primary" id="btnMuat">
                  <i class="fa fa-sync"></i> Muat Data
                </button>
                <button type="button" class="btn btn-success" id="btnSimpan">
                  <i class="fa fa-save"></i> Simpan
                </button>
                <button type="button" class="btn btn-secondary" id="btnCetak">
                  <i class="fa fa-print"></i> Cetak
                </button>
                <button type="button" class="btn btn-success" id="btnWa" style="background:#25D366;border-color:#1da851;">
                  <i class="fab fa-whatsapp"></i> Kirim WA
                </button>
              </div>
            </div>
          </div>
          <p class="text-muted mb-0 small">
            <?php if ($pakaiPergantianShift) { ?>Pilih shift <strong>Pagi/Siang</strong> lalu isi <strong>jam mulai &amp; selesai</strong> sesuai pergantian shift aktual.<?php } else { ?>Laporan penjualan harian NU Grosir — semua transaksi pada tanggal terpilih, termasuk <strong>penjualan piutang</strong>.<?php } ?>
            Penjualan sistem diambil otomatis dari transaksi POS (non-draft) pada tanggal<?= $pakaiPergantianShift ? ' &amp; rentang jam' : '' ?> terpilih<?= $tampilPiutang ? ' (kas + QRIS/TF + piutang)' : '' ?>.
            Rincian beban/pengeluaran diambil dari <a href="laba-bersih-data">Data Operasional (laba-bersih-data)</a>.
            Isi <strong>pengeluaran kas</strong> dan <strong>setoran kasir</strong> lalu simpan.
          </p>
        </div>
      </div>

      <div id="shiftLaporanWrap" class="shift-laporan-wrap" style="display:none;">
        <div id="shiftLaporanSheet" class="shift-laporan-sheet">
          <table class="shift-laporan-table shift-laporan-header-table">
            <tr>
              <td colspan="4" class="text-center shift-title">LAPORAN PENJUALAN HARIAN</td>
            </tr>
            <tr>
              <td colspan="4" class="text-center shift-subtitle" id="sheetTokoNama">—</td>
            </tr>
            <tr>
              <td colspan="<?= $pakaiPergantianShift ? 2 : 4 ?>"><strong>HARI/TGL :</strong> <span id="sheetHariTgl">—</span></td>
              <?php if ($pakaiPergantianShift) { ?>
              <td colspan="2" class="text-right sheet-shift-col" id="sheetShiftCol"><strong>SHIFT :</strong> <span id="sheetShiftLabel">—</span></td>
              <?php } ?>
            </tr>
          </table>

          <div id="sheetKasirBlocks"></div>

          <table class="shift-laporan-table shift-laporan-total-kasir">
            <tr class="shift-section-head">
              <td colspan="4">TOTAL PENJUALAN KAS KASIR</td>
            </tr>
            <tr>
              <td class="shift-label">TOTAL PENJUALAN SISTEM</td>
              <td class="shift-num shift-bg-yellow" id="totPenjualanSistem">0</td>
              <td class="shift-label">TOTAL PENJUALAN QRIS, TF</td>
              <td class="shift-num shift-bg-yellow" id="totPenjualanQris">0</td>
            </tr>
            <tr>
              <td class="shift-label">TOTAL PENJUALAN KAS</td>
              <td class="shift-num shift-bg-yellow" id="totPenjualanKas">0</td>
              <?php if ($tampilPiutang) { ?>
              <td class="shift-label">TOTAL PENJUALAN PIUTANG</td>
              <td class="shift-num shift-bg-yellow" id="totPenjualanPiutang">0</td>
              <?php } else { ?>
              <td class="shift-label">JUMLAH PENJUALAN</td>
              <td class="shift-num shift-bg-yellow" id="totJumlahPenjualan">0</td>
              <?php } ?>
            </tr>
            <?php if ($tampilPiutang) { ?>
            <tr>
              <td class="shift-label">JUMLAH PENJUALAN</td>
              <td class="shift-num shift-bg-yellow" id="totJumlahPenjualan">0</td>
              <td colspan="2"></td>
            </tr>
            <?php } ?>
          </table>

          <table class="shift-laporan-table shift-laporan-pengeluaran">
            <tr class="shift-section-head shift-bg-green-head">
              <td style="width:8%">No</td>
              <td>RINCIAN BEBAN / PENGELUARAN <small class="d-block font-weight-normal">(dari laba-bersih-data)</small></td>
              <td style="width:28%" class="text-right">JUMLAH</td>
            </tr>
            <tbody id="sheetPengeluaranBody"></tbody>
            <tr>
              <td colspan="2" class="text-right"><strong>JUMLAH PENGELUARAN</strong></td>
              <td class="shift-num shift-bg-yellow text-right" id="totPengeluaranRincian">0</td>
            </tr>
          </table>

          <table class="shift-laporan-table shift-laporan-akhir">
            <tr>
              <td class="shift-label">TOTAL SISA PENJUALAN KAS</td>
              <td class="shift-num shift-bg-yellow" id="totSisaPenjualanKas">0</td>
            </tr>
            <tr>
              <td class="shift-label">TOTAL SETORAN KAS KASIR</td>
              <td class="shift-num shift-bg-yellow" id="totSetoranKasir">0</td>
            </tr>
            <tr>
              <td class="shift-label">SELISIH KAS LEBIH/KURANG</td>
              <td class="shift-num shift-bg-selisih" id="totSelisihAkhir">0</td>
            </tr>
          </table>

          <table class="shift-laporan-table shift-laporan-footer no-print-input">
            <tr>
              <td><strong>SETOR UANG KE :</strong></td>
              <td colspan="3"><input type="text" class="form-control form-control-sm shift-input-inline" id="inputSetorKe" placeholder="Nama penerima setoran"></td>
            </tr>
            <tr>
              <td><strong>HARI/TGL SETOR/TF UANG :</strong></td>
              <td colspan="3"><input type="date" class="form-control form-control-sm shift-input-inline" id="inputTglSetor"></td>
            </tr>
            <tr class="shift-sign-row">
              <td colspan="4" class="p-0">
                <table class="shift-laporan-table shift-ttd-table mb-0">
                  <tr>
                    <td class="shift-ttd-cell" data-ttd-role="kp_akt">
                      <div class="shift-ttd-title">KP/AKT</div>
                      <div class="shift-ttd-nama-print"></div>
                      <input type="text" class="form-control form-control-sm ttd-nama-input mb-1 no-print" placeholder="Nama KP/AKT">
                      <div class="shift-ttd-canvas-wrap">
                        <canvas class="shift-ttd-canvas" width="220" height="90"></canvas>
                        <img class="shift-ttd-print-img" alt="TTD KP/AKT">
                      </div>
                      <small class="shift-ttd-meta text-muted d-block"></small>
                      <div class="shift-ttd-actions no-print mt-1">
                        <button type="button" class="btn btn-xs btn-outline-secondary btn-ttd-clear">Hapus</button>
                      </div>
                    </td>
                    <td class="shift-ttd-cell" data-ttd-role="kasir1">
                      <div class="shift-ttd-title">KASIR 1</div>
                      <div class="shift-ttd-nama-print"></div>
                      <input type="text" class="form-control form-control-sm ttd-nama-input mb-1 no-print" placeholder="Nama kasir 1">
                      <div class="shift-ttd-canvas-wrap">
                        <canvas class="shift-ttd-canvas" width="220" height="90"></canvas>
                        <img class="shift-ttd-print-img" alt="TTD Kasir 1">
                      </div>
                      <small class="shift-ttd-meta text-muted d-block"></small>
                      <div class="shift-ttd-actions no-print mt-1">
                        <button type="button" class="btn btn-xs btn-outline-secondary btn-ttd-clear">Hapus</button>
                      </div>
                    </td>
                    <td class="shift-ttd-cell" data-ttd-role="kasir2">
                      <div class="shift-ttd-title">KASIR 2</div>
                      <div class="shift-ttd-nama-print"></div>
                      <input type="text" class="form-control form-control-sm ttd-nama-input mb-1 no-print" placeholder="Nama kasir 2">
                      <div class="shift-ttd-canvas-wrap">
                        <canvas class="shift-ttd-canvas" width="220" height="90"></canvas>
                        <img class="shift-ttd-print-img" alt="TTD Kasir 2">
                      </div>
                      <small class="shift-ttd-meta text-muted d-block"></small>
                      <div class="shift-ttd-actions no-print mt-1">
                        <button type="button" class="btn btn-xs btn-outline-secondary btn-ttd-clear">Hapus</button>
                      </div>
                    </td>
                  </tr>
                </table>
                <p class="text-muted small mb-0 mt-2 no-print px-2">Tanda tangan: gambar langsung di kotak (mouse / jari), lalu klik <strong>Simpan</strong>.</p>
              </td>
            </tr>
          </table>
        </div>
      </div>

      <div id="shiftLaporanEmpty" class="alert alert-info no-print">
        Pilih tanggal dan shift, lalu klik <strong>Muat Data</strong>.
      </div>
    </div>
  </section>
</div>

<div class="modal fade no-print" id="modalWaShift" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header" style="background:#25D366;color:#fff;">
        <h5 class="modal-title"><i class="fab fa-whatsapp"></i> Kirim Laporan via WhatsApp</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="waGambarLoading" class="text-center py-4" style="display:none;">
          <i class="fa fa-spinner fa-spin fa-2x"></i>
          <p class="mt-2 mb-0 text-muted">Membuat gambar laporan...</p>
        </div>
        <div id="waGambarPreviewWrap" class="form-group mb-2">
          <label>Preview gambar laporan</label>
          <div class="wa-gambar-preview-box border rounded p-2 bg-light text-center">
            <img id="waGambarPreview" alt="Preview laporan" class="img-fluid" style="max-height:420px;">
          </div>
          <small class="text-muted d-block mt-1">Gambar diambil dari tampilan formulir laporan (sama seperti cetak).</small>
        </div>
        <div class="form-group">
          <label for="waNomorTujuan">Nomor WhatsApp tujuan</label>
          <input type="text" class="form-control" id="waNomorTujuan" placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($defaultWaToko, ENT_QUOTES, 'UTF-8') ?>">
          <small class="text-muted">Kosongkan jika ingin memilih kontak sendiri di WhatsApp Web.</small>
        </div>
        <div class="form-group mb-0">
          <label for="waCaption">Caption singkat (opsional)</label>
          <input type="text" class="form-control" id="waCaption" placeholder="Laporan pergantian shift">
          <small class="text-muted d-block mt-1">Gambar disalin otomatis; setelah WhatsApp Web terbuka, tekan <strong>Ctrl+V</strong> di kolom chat untuk menempelkan.</small>
        </div>
      </div>
      <div class="modal-footer flex-wrap">
        <button type="button" class="btn btn-outline-primary" id="btnWaUnduhGambar"><i class="fa fa-download"></i> Unduh Gambar</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success" id="btnWaBuka" style="background:#25D366;border-color:#1da851;">
          <i class="fab fa-whatsapp"></i> Buka WhatsApp Web
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  .shift-laporan-wrap {
    max-width: 720px;
    margin: 0 auto 2rem;
  }

  .shift-laporan-sheet {
    background: #fff;
    border: 2px solid #333;
    padding: 12px;
    font-size: 13px;
  }

  .shift-laporan-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
  }

  .shift-laporan-table td,
  .shift-laporan-table th {
    border: 1px solid #333;
    padding: 6px 8px;
    vertical-align: middle;
  }

  .shift-title {
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 0.5px;
  }

  .shift-subtitle {
    font-size: 14px;
    font-weight: 600;
  }

  .shift-section-head td {
    background: #d9d9d9;
    font-weight: 700;
    text-align: center;
  }

  .shift-bg-green-head td {
    background: #c5e0b4 !important;
    font-weight: 700;
  }

  .shift-label {
    font-weight: 600;
    width: 42%;
  }

  .shift-num {
    text-align: right;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
  }

  .shift-bg-yellow {
    background: #fff2cc !important;
  }

  .shift-bg-selisih {
    background: #e2efda !important;
  }

  .shift-kasir-block {
    margin-bottom: 10px;
  }

  .shift-kasir-title {
    background: #f2f2f2;
    font-weight: 700;
    text-align: center;
  }

  .shift-input-manual {
    width: 100%;
    max-width: 140px;
    text-align: right;
    border: 1px dashed #888;
    padding: 4px 6px;
    font-size: 13px;
  }

  .shift-input-inline {
    border: none;
    border-bottom: 1px solid #333;
    border-radius: 0;
    background: transparent;
  }

  .shift-sign-line {
    display: block;
    margin-top: 48px;
    border-top: 1px solid #333;
  }

  .shift-readonly {
    color: #111;
  }

  .shift-pengeluaran-readonly td {
    vertical-align: top;
  }

  .shift-ttd-table td {
    width: 33.33%;
    vertical-align: top;
    text-align: center;
  }

  .shift-ttd-title {
    font-weight: 700;
    margin-bottom: 4px;
  }

  .shift-ttd-canvas-wrap {
    border: 1px dashed #666;
    background: #fafafa;
    border-radius: 4px;
    overflow: hidden;
    touch-action: none;
  }

  .shift-ttd-canvas {
    display: block;
    width: 100%;
    height: 90px;
    cursor: crosshair;
  }

  .shift-ttd-print-img {
    display: none;
    max-width: 100%;
    max-height: 90px;
    margin: 0 auto;
  }

  .shift-ttd-meta {
    font-size: 10px;
    min-height: 14px;
  }

  .ttd-nama-input {
    font-size: 12px;
    text-align: center;
  }

  .shift-ttd-nama-print {
    display: none;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 4px;
    min-height: 18px;
  }

  /* Ukuran kertas mengikuti lebar formulir di layar (~720px / 190mm) */
  @page {
    size: 190mm auto;
    margin: 6mm 5mm;
  }

  @media print {
    body.shift-laporan-printing .content-wrapper,
    body.shift-laporan-printing .main-footer {
      margin-left: 0 !important;
    }

    html,
    body {
      width: 190mm !important;
      min-width: 190mm !important;
      max-width: 190mm !important;
      margin: 0 auto !important;
      padding: 0 !important;
      background: #fff !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      color-adjust: exact !important;
    }

    .wrapper,
    .content-wrapper,
    .content,
    .container-fluid {
      width: 190mm !important;
      max-width: 190mm !important;
      margin: 0 !important;
      padding: 0 !important;
      background: #fff !important;
    }

    .no-print,
    .main-sidebar,
    .main-header,
    .content-header,
    .main-footer,
    .control-sidebar {
      display: none !important;
    }

    #shiftLaporanEmpty {
      display: none !important;
    }

    #shiftLaporanWrap {
      display: block !important;
    }

    .shift-laporan-wrap {
      width: 190mm !important;
      max-width: 190mm !important;
      margin: 0 auto !important;
      padding: 0 !important;
    }

    .shift-laporan-sheet {
      width: 100% !important;
      max-width: 190mm !important;
      margin: 0 !important;
      padding: 10px !important;
      border: 2px solid #333 !important;
      background: #fff !important;
      font-size: 13px !important;
      box-shadow: none !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }

    .shift-laporan-sheet * {
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }

    .shift-laporan-table {
      width: 100% !important;
      page-break-inside: avoid;
    }

    .shift-kasir-block {
      page-break-inside: avoid;
    }

    .shift-section-head td {
      background: #d9d9d9 !important;
      color: #000 !important;
    }

    .shift-bg-green-head td {
      background: #c5e0b4 !important;
      color: #000 !important;
    }

    .shift-kasir-title {
      background: #f2f2f2 !important;
      color: #000 !important;
    }

    .shift-bg-yellow {
      background: #fff2cc !important;
      color: #000 !important;
    }

    .shift-bg-selisih {
      background: #e2efda !important;
      color: #000 !important;
    }

    .shift-laporan-table td,
    .shift-laporan-table th {
      border: 1px solid #333 !important;
    }

    .shift-input-manual,
    .shift-input-inline {
      border: none !important;
      box-shadow: none !important;
      padding: 0 !important;
      background: transparent !important;
    }

    .no-print-input input {
      border: none !important;
      background: transparent !important;
    }

    .alert {
      border: 1px solid #333 !important;
      background: #fff3cd !important;
      color: #000 !important;
    }

    a[href] {
      color: #000 !important;
      text-decoration: none !important;
    }

    .shift-ttd-canvas-wrap,
    .shift-ttd-actions,
    .ttd-nama-input,
    .shift-sign-row .no-print {
      display: none !important;
    }

    .shift-ttd-print-img {
      display: block !important;
    }

    .shift-ttd-nama-print {
      display: block !important;
    }

    .shift-ttd-meta {
      display: block !important;
      font-size: 9px !important;
    }

    .shift-ttd-cell {
      border: 1px solid #333 !important;
    }
  }

  .wa-gambar-preview-box {
    max-height: 440px;
    overflow: auto;
  }

  /* Tampilan formulir saat dijadikan gambar WA (mirip cetak) */
  .shift-wa-capture .shift-input-manual,
  .shift-wa-capture .shift-input-inline {
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    background: transparent !important;
  }

  .shift-wa-capture .no-print-input input {
    border: none !important;
    background: transparent !important;
  }

  .shift-wa-capture .shift-ttd-canvas-wrap,
  .shift-wa-capture .shift-ttd-actions,
  .shift-wa-capture .ttd-nama-input,
  .shift-wa-capture .shift-sign-row .no-print {
    display: none !important;
  }

  .shift-wa-capture .shift-ttd-print-img {
    display: block !important;
  }

  .shift-wa-capture .shift-ttd-nama-print {
    display: block !important;
  }

  .shift-wa-capture .shift-bg-yellow {
    background: #fff2cc !important;
  }

  .shift-wa-capture .shift-bg-selisih {
    background: #e2efda !important;
  }

  .shift-wa-capture .shift-bg-green-head td {
    background: #c5e0b4 !important;
  }

  .shift-wa-capture .shift-section-head td {
    background: #d9d9d9 !important;
  }
</style>

<?php include '_footer.php'; ?>

<script>
(function () {
  var PAKAI_PERGANTIAN_SHIFT = <?= $pakaiPergantianShift ? 'true' : 'false' ?>;
  var TAMPIL_PIUTANG = <?= $tampilPiutang ? 'true' : 'false' ?>;
  var state = { data: null, pads: {} };

  function createSignaturePad(canvas) {
    var ctx = canvas.getContext('2d');
    var drawing = false;
    var hasInk = false;
    ctx.strokeStyle = '#111';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    function getPos(e) {
      var rect = canvas.getBoundingClientRect();
      var scaleX = canvas.width / rect.width;
      var scaleY = canvas.height / rect.height;
      var clientX = e.touches && e.touches.length ? e.touches[0].clientX : e.clientX;
      var clientY = e.touches && e.touches.length ? e.touches[0].clientY : e.clientY;
      return {
        x: (clientX - rect.left) * scaleX,
        y: (clientY - rect.top) * scaleY
      };
    }

    function start(e) {
      e.preventDefault();
      drawing = true;
      hasInk = true;
      var p = getPos(e);
      ctx.beginPath();
      ctx.moveTo(p.x, p.y);
    }

    function move(e) {
      if (!drawing) return;
      e.preventDefault();
      var p = getPos(e);
      ctx.lineTo(p.x, p.y);
      ctx.stroke();
    }

    function end() {
      drawing = false;
    }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', end);
    canvas.addEventListener('mouseleave', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end);

    return {
      clear: function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasInk = false;
      },
      fromDataURL: function (url, cb) {
        if (!url) {
          if (cb) cb();
          return;
        }
        var img = new Image();
        img.onload = function () {
          ctx.clearRect(0, 0, canvas.width, canvas.height);
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
          hasInk = true;
          if (cb) cb();
        };
        img.onerror = function () { if (cb) cb(); };
        img.src = url;
      },
      toDataURL: function () {
        return hasInk ? canvas.toDataURL('image/png') : '';
      },
      isEmpty: function () {
        return !hasInk;
      },
      markHasInk: function () {
        hasInk = true;
      }
    };
  }

  function initTtdPads(ttd, kasirList) {
    state.pads = {};
    var defaults = {
      kp_akt: { nama: '' },
      kasir1: { nama: (kasirList[0] && kasirList[0].user_nama) ? kasirList[0].user_nama : '' },
      kasir2: { nama: (kasirList[1] && kasirList[1].user_nama) ? kasirList[1].user_nama : '' }
    };
    ttd = ttd || {};

    $('.shift-ttd-cell').each(function () {
      var $cell = $(this);
      var role = $cell.data('ttd-role');
      var canvas = $cell.find('canvas.shift-ttd-canvas')[0];
      if (!canvas || !role) return;

      var pad = createSignaturePad(canvas);
      state.pads[role] = pad;

      var slot = ttd[role] || {};
      var nama = slot.nama || (defaults[role] && defaults[role].nama) || '';
      $cell.find('.ttd-nama-input').val(nama);

      var meta = '';
      if (slot.signed_at) {
        meta = 'Ditandatangani: ' + slot.signed_at;
      }
      $cell.find('.shift-ttd-meta').text(meta);

      pad.fromDataURL(slot.image || '', function () {
        syncTtdPrintImage(role);
      });
    });
  }

  function syncTtdPrintImage(role) {
    var $cell = $('.shift-ttd-cell[data-ttd-role="' + role + '"]');
    var pad = state.pads[role];
    var $img = $cell.find('.shift-ttd-print-img');
    var nama = $cell.find('.ttd-nama-input').val() || '';
    $cell.find('.shift-ttd-nama-print').text(nama);
    if (pad && !pad.isEmpty()) {
      $img.attr('src', pad.toDataURL()).show();
    } else {
      $img.attr('src', '').hide();
    }
  }

  function syncAllTtdPrintImages() {
    Object.keys(state.pads).forEach(function (role) {
      syncTtdPrintImage(role);
    });
  }

  function kumpulkanTtd() {
    var ttd = {};
    $('.shift-ttd-cell').each(function () {
      var role = $(this).data('ttd-role');
      var pad = state.pads[role];
      if (!role) return;
      var payload = {
        nama: $(this).find('.ttd-nama-input').val() || ''
      };
      if (pad) {
        if (!pad.isEmpty()) {
          payload.image = pad.toDataURL();
        } else if ($(this).data('ttd-cleared')) {
          payload.clear = true;
        }
      }
      ttd[role] = payload;
    });
    return ttd;
  }

  function fmt(n) {
    n = parseInt(n, 10) || 0;
    return n.toLocaleString('id-ID');
  }

  function parseNum(val) {
    if (typeof val === 'number') return val;
    var s = String(val || '').replace(/\./g, '').replace(/,/g, '');
    return parseInt(s, 10) || 0;
  }

  var SHIFT_JAM_DEFAULT = {
    pagi: { mulai: '07:00', selesai: '13:59' },
    sore: { mulai: '14:00', selesai: '20:59' }
  };

  function terapkanJamDefaultShift() {
    var d = SHIFT_JAM_DEFAULT[$('#filter_shift').val()] || SHIFT_JAM_DEFAULT.pagi;
    $('#filter_jam_mulai').val(d.mulai);
    $('#filter_jam_selesai').val(d.selesai);
  }

  function resetSheetTampilan() {
    state.data = null;
    $('#shiftLaporanWrap').hide();
    $('#shiftLaporanEmpty').show();
  }

  function getShiftFilter() {
    return PAKAI_PERGANTIAN_SHIFT ? $('#filter_shift').val() : 'harian';
  }

  function syncJamFromSaved() {
    if (!PAKAI_PERGANTIAN_SHIFT) {
      return;
    }
    var tanggal = $('#filter_tanggal').val();
    var shift = getShiftFilter();
    if (!tanggal || !shift) {
      return;
    }
    $.getJSON('api/laporan-pergantian-shift-data.php', {
      tanggal: tanggal,
      shift: shift,
      jam_only: 1
    }).done(function (res) {
      if (res.ok && res.jam) {
        $('#filter_jam_mulai').val(res.jam.jam_mulai);
        $('#filter_jam_selesai').val(res.jam.jam_selesai);
      } else {
        terapkanJamDefaultShift();
      }
    });
  }

  function muatData() {
    var tanggal = $('#filter_tanggal').val();
    var shift = getShiftFilter();
    var params = { tanggal: tanggal, shift: shift };
    if (PAKAI_PERGANTIAN_SHIFT) {
      var jamMulai = $('#filter_jam_mulai').val();
      var jamSelesai = $('#filter_jam_selesai').val();
      if (!jamMulai || !jamSelesai) {
        Swal.fire('Info', 'Isi jam mulai dan jam selesai shift.', 'info');
        return;
      }
      params.jam_mulai = jamMulai;
      params.jam_selesai = jamSelesai;
      params.preview_jam = 1;
    }
    $('#btnMuat').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Memuat...');

    $.getJSON('api/laporan-pergantian-shift-data.php', params)
      .done(function (res) {
        if (!res.ok) {
          Swal.fire('Gagal', res.message || 'Data tidak dapat dimuat.', 'error');
          return;
        }
        state.data = res;
        state.pengeluaranTotal = (res.totals && res.totals.total_pengeluaran_rincian) ? res.totals.total_pengeluaran_rincian : 0;
        renderSheet(res);
        $('#shiftLaporanWrap').show();
        $('#shiftLaporanEmpty').hide();
      })
      .fail(function (xhr) {
        var msg = 'Tidak dapat memuat data laporan.';
        try {
          var err = JSON.parse(xhr.responseText);
          if (err && err.message) msg = err.message;
        } catch (e) {}
        Swal.fire('Gagal', msg, 'error');
      })
      .always(function () {
        $('#btnMuat').prop('disabled', false).html('<i class="fa fa-sync"></i> Muat Data');
      });
  }

  function renderKasirBlock(k, index) {
    var rowPiutang = TAMPIL_PIUTANG
      ? '<tr><td class="shift-label">PENJUALAN PIUTANG</td><td class="shift-num shift-readonly" data-field="penjualan_piutang">' + fmt(k.penjualan_piutang || 0) + '</td><td colspan="2"></td></tr>'
      : '';
    return (
      '<table class="shift-laporan-table shift-kasir-block" data-user-id="' + k.user_id + '">' +
        '<tr><td colspan="4" class="shift-kasir-title">REKAP PENJUALAN PER KASIR — KASIR ' + (index + 1) + '</td></tr>' +
        '<tr><td class="shift-label">NAMA KASIR</td><td colspan="3"><strong>' + escapeHtml(k.user_nama) + '</strong></td></tr>' +
        '<tr><td class="shift-label">PENJUALAN SISTEM</td><td class="shift-num shift-readonly" data-field="penjualan_sistem">' + fmt(k.penjualan_sistem) + '</td><td colspan="2"></td></tr>' +
        '<tr><td class="shift-label">PENJUALAN QRIS, TF</td><td class="shift-num shift-readonly" data-field="penjualan_qris_tf">' + fmt(k.penjualan_qris_tf) + '</td><td colspan="2"></td></tr>' +
        '<tr><td class="shift-label">PENJUALAN KAS</td><td class="shift-num shift-bg-yellow shift-readonly" data-field="penjualan_kas">' + fmt(k.penjualan_kas) + '</td><td colspan="2"></td></tr>' +
        rowPiutang +
        '<tr><td class="shift-label">JUMLAH PENJUALAN</td><td class="shift-num shift-bg-yellow shift-readonly" data-field="jumlah_penjualan">' + fmt(k.jumlah_penjualan) + '</td><td colspan="2"></td></tr>' +
        '<tr><td class="shift-label">PENGELUARAN KAS</td><td><input type="text" class="shift-input-manual no-print-input inp-pengeluaran" value="' + fmt(k.pengeluaran_kas) + '"></td><td colspan="2"></td></tr>' +
        '<tr><td class="shift-label">SISA KAS</td><td class="shift-num shift-bg-yellow shift-readonly out-sisa-kas">' + fmt(k.sisa_kas) + '</td><td colspan="2"></td></tr>' +
        '<tr><td class="shift-label">SETORAN KAS KASIR</td><td><input type="text" class="shift-input-manual no-print-input inp-setoran" value="' + fmt(k.setoran_kas) + '"></td><td colspan="2"></td></tr>' +
        '<tr><td class="shift-label">SELISIH KAS LEBIH/KURANG</td><td class="shift-num shift-bg-selisih shift-readonly out-selisih">' + fmt(k.selisih) + '</td><td colspan="2"></td></tr>' +
      '</table>'
    );
  }

  function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function renderSheet(res) {
    var m = res.meta;
    $('#sheetTokoNama').text(m.toko_nama);
    $('#sheetHariTgl').text(m.hari + ' / ' + m.tanggal_tampil);
    if (PAKAI_PERGANTIAN_SHIFT && m.jam) {
      if (m.jam.jam_mulai) $('#filter_jam_mulai').val(m.jam.jam_mulai);
      if (m.jam.jam_selesai) $('#filter_jam_selesai').val(m.jam.jam_selesai);
    }
    if (PAKAI_PERGANTIAN_SHIFT) {
      var shiftTeks = m.shift_label || '';
      if (m.jam_tampil) {
        shiftTeks += ' (' + m.jam_tampil + ')';
      }
      $('#sheetShiftCol').show();
      $('#sheetShiftLabel').text(shiftTeks);
    } else {
      $('#sheetShiftCol').hide();
    }

    var html = '';
    if (!res.kasir.length) {
      html = '<div class="alert alert-warning">Tidak ada transaksi pada shift ini. Data manual tetap bisa disimpan jika diperlukan.</div>';
    } else {
      res.kasir.forEach(function (k, i) { html += renderKasirBlock(k, i); });
    }
    $('#sheetKasirBlocks').html(html);

    var pHtml = '';
    if (!res.pengeluaran || !res.pengeluaran.length) {
      pHtml = '<tr class="shift-pengeluaran-readonly"><td colspan="3" class="text-center text-muted py-2">' +
        'Belum ada pengeluaran pada shift ini. Input di <a href="laba-bersih-data">Data Operasional</a> (jenis Pengeluaran).' +
        '</td></tr>';
    } else {
      res.pengeluaran.forEach(function (p) {
        var pj = p.pj ? ' <small class="text-muted">(' + escapeHtml(p.pj) + ')</small>' : '';
        pHtml += '<tr class="shift-pengeluaran-readonly">' +
          '<td class="text-center">' + p.urutan + '</td>' +
          '<td>' + escapeHtml(p.keterangan || '-') + pj + '</td>' +
          '<td class="text-right shift-num" data-jumlah="' + (p.jumlah || 0) + '">' + fmt(p.jumlah) + '</td>' +
          '</tr>';
      });
    }
    $('#sheetPengeluaranBody').html(pHtml);

    $('#inputSetorKe').val(res.footer.setor_ke || '');
    $('#inputTglSetor').val(res.footer.tgl_setor || '');

    initTtdPads(res.ttd, res.kasir || []);
    hitungUlang();
  }

  function hitungUlang() {
    var totSistem = 0, totQris = 0, totKas = 0, totPiutang = 0, totJumlah = 0;
    var totPengKasir = 0, totSisa = 0, totSetor = 0;

    $('#sheetKasirBlocks .shift-kasir-block').each(function () {
      var $b = $(this);
      var penjualanKas = parseNum($b.find('[data-field="penjualan_kas"]').text());
      var pengeluaran = parseNum($b.find('.inp-pengeluaran').val());
      var setoran = parseNum($b.find('.inp-setoran').val());
      var sisa = penjualanKas - pengeluaran;
      var selisih = setoran - sisa;

      $b.find('.out-sisa-kas').text(fmt(sisa));
      $b.find('.out-selisih').text(fmt(selisih));

      totSistem += parseNum($b.find('[data-field="penjualan_sistem"]').text());
      totQris += parseNum($b.find('[data-field="penjualan_qris_tf"]').text());
      totKas += penjualanKas;
      if (TAMPIL_PIUTANG) {
        totPiutang += parseNum($b.find('[data-field="penjualan_piutang"]').text());
      }
      totJumlah += parseNum($b.find('[data-field="jumlah_penjualan"]').text());
      totPengKasir += pengeluaran;
      totSisa += sisa;
      totSetor += setoran;
    });

    var totPengRincian = 0;
    $('#sheetPengeluaranBody [data-jumlah]').each(function () {
      totPengRincian += parseNum($(this).data('jumlah'));
    });
    if (!totPengRincian && state.pengeluaranTotal) {
      totPengRincian = state.pengeluaranTotal;
    }

    var totalSisaPenjualanKas = totKas - totPengRincian;
    var selisihAkhir = totSetor - totalSisaPenjualanKas;

    $('#totPenjualanSistem').text(fmt(totSistem));
    $('#totPenjualanQris').text(fmt(totQris));
    $('#totPenjualanKas').text(fmt(totKas));
    if (TAMPIL_PIUTANG) {
      $('#totPenjualanPiutang').text(fmt(totPiutang));
    }
    $('#totJumlahPenjualan').text(fmt(totJumlah));
    $('#totPengeluaranRincian').text(fmt(totPengRincian));
    $('#totSisaPenjualanKas').text(fmt(totalSisaPenjualanKas));
    $('#totSetoranKasir').text(fmt(totSetor));
    $('#totSelisihAkhir').text(fmt(selisihAkhir));
  }

  function kumpulkanPayload() {
    var kasir = [];
    $('#sheetKasirBlocks .shift-kasir-block').each(function () {
      kasir.push({
        user_id: parseInt($(this).data('user-id'), 10),
        pengeluaran_kas: parseNum($(this).find('.inp-pengeluaran').val()),
        setoran_kas: parseNum($(this).find('.inp-setoran').val())
      });
    });

    return {
      tanggal: $('#filter_tanggal').val(),
      shift: getShiftFilter(),
      jam_mulai: PAKAI_PERGANTIAN_SHIFT ? $('#filter_jam_mulai').val() : '',
      jam_selesai: PAKAI_PERGANTIAN_SHIFT ? $('#filter_jam_selesai').val() : '',
      setor_ke: $('#inputSetorKe').val(),
      tgl_setor: $('#inputTglSetor').val(),
      kasir: kasir,
      ttd: kumpulkanTtd()
    };
  }

  $(document).on('click', '.btn-ttd-clear', function () {
    var $cell = $(this).closest('.shift-ttd-cell');
    var role = $cell.data('ttd-role');
    if (state.pads[role]) {
      state.pads[role].clear();
    }
    $cell.data('ttd-cleared', true);
    $cell.find('.shift-ttd-meta').text('');
    $cell.find('.shift-ttd-print-img').attr('src', '').hide();
  });

  $(document).on('pointerdown mousedown touchstart', '.shift-ttd-canvas', function () {
    $(this).closest('.shift-ttd-cell').data('ttd-cleared', false);
  });

  function simpanData() {
    if (!state.data) {
      Swal.fire('Info', 'Muat data terlebih dahulu.', 'info');
      return;
    }
    var tanggalFilter = $('#filter_tanggal').val();
    var shiftFilter = $('#filter_shift').val();
    if (state.data.meta && (state.data.meta.tanggal !== tanggalFilter || state.data.meta.shift !== shiftFilter)) {
      Swal.fire('Info', 'Tanggal atau shift di filter berubah. Klik <strong>Muat Data</strong> dulu untuk tanggal/shift yang dipilih.', 'info');
      return;
    }
    if (PAKAI_PERGANTIAN_SHIFT) {
      var jamMulaiFilter = $('#filter_jam_mulai').val();
      var jamSelesaiFilter = $('#filter_jam_selesai').val();
      if (state.data.meta && state.data.meta.jam &&
          (state.data.meta.jam.jam_mulai !== jamMulaiFilter || state.data.meta.jam.jam_selesai !== jamSelesaiFilter)) {
        Swal.fire('Info', 'Jam shift berubah. Klik <strong>Muat Data</strong> dulu agar penjualan dihitung sesuai rentang jam baru.', 'info');
        return;
      }
    }
    $('#btnSimpan').prop('disabled', true);
    $.ajax({
      url: 'api/laporan-pergantian-shift-save.php',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(kumpulkanPayload())
    })
      .done(function (res) {
        if (res.ok) {
          Swal.fire('Berhasil', res.message, 'success');
          muatData();
        } else {
          Swal.fire('Gagal', res.message || 'Gagal menyimpan.', 'error');
        }
      })
      .fail(function () {
        Swal.fire('Gagal', 'Tidak dapat menyimpan laporan.', 'error');
      })
      .always(function () {
        $('#btnSimpan').prop('disabled', false);
      });
  }

  $(document).on('input', '.inp-pengeluaran, .inp-setoran', hitungUlang);
  $('#btnMuat').on('click', muatData);
  $('#btnSimpan').on('click', simpanData);
  $('#btnCetak').on('click', function () {
    if (!state.data) {
      Swal.fire('Info', 'Muat data terlebih dahulu.', 'info');
      return;
    }
    syncAllTtdPrintImages();
    document.body.classList.add('shift-laporan-printing');
    window.print();
    setTimeout(function () {
      document.body.classList.remove('shift-laporan-printing');
    }, 1000);
  });

  function rupiah(n) {
    return 'Rp ' + fmt(n);
  }

  function normalizeWaPhone(raw) {
    var d = String(raw || '').replace(/\D+/g, '');
    if (!d) return '';
    if (d.indexOf('62') === 0) return d;
    if (d.charAt(0) === '0') return '62' + d.substring(1);
    return '62' + d;
  }

  var waImageState = { dataUrl: '', blob: null, fileName: '' };

  function buildWaCaption() {
    if (!state.data) return 'Laporan pergantian shift';
    var m = state.data.meta || {};
    var parts = ['Laporan pergantian shift'];
    if (m.tanggal_tampil) parts.push(m.tanggal_tampil);
    if (m.shift_label) {
      var shiftInfo = m.shift_label;
      if (m.jam_tampil) shiftInfo += ' ' + m.jam_tampil;
      parts.push('(' + shiftInfo + ')');
    }
    if (m.toko_nama) parts.push('- ' + m.toko_nama);
    return parts.join(' ');
  }

  function getWaFileName() {
    var tgl = $('#filter_tanggal').val() || 'laporan';
    var shift = $('#filter_shift').val() || 'shift';
    return 'laporan-shift-' + tgl + '-' + shift + '.png';
  }

  function formatTglSetorInput(val) {
    if (!val) return '';
    var p = String(val).split('-');
    if (p.length !== 3) return val;
    return p[2] + '/' + p[1] + '/' + p[0];
  }

  function dataUrlToBlob(dataUrl) {
    try {
      var parts = String(dataUrl).split(',');
      if (parts.length < 2) return null;
      var mime = parts[0].match(/:(.*?);/);
      mime = mime ? mime[1] : 'image/png';
      var bin = atob(parts[1]);
      var len = bin.length;
      var arr = new Uint8Array(len);
      for (var i = 0; i < len; i++) {
        arr[i] = bin.charCodeAt(i);
      }
      return new Blob([arr], { type: mime });
    } catch (e) {
      return null;
    }
  }

  function prepareSheetForCapture(clonedDoc) {
    var sheet = clonedDoc.getElementById('shiftLaporanSheet');
    var liveSheet = document.getElementById('shiftLaporanSheet');
    if (!sheet) return;
    sheet.classList.add('shift-wa-capture');

    if (liveSheet) {
      var liveCells = liveSheet.querySelectorAll('.shift-ttd-cell');
      var cloneCells = sheet.querySelectorAll('.shift-ttd-cell');
      for (var i = 0; i < liveCells.length; i++) {
        if (!cloneCells[i]) continue;
        var liveNama = liveCells[i].querySelector('.shift-ttd-nama-print');
        var cloneNama = cloneCells[i].querySelector('.shift-ttd-nama-print');
        var liveImg = liveCells[i].querySelector('.shift-ttd-print-img');
        var cloneImg = cloneCells[i].querySelector('.shift-ttd-print-img');
        if (cloneNama && liveNama) {
          cloneNama.textContent = liveNama.textContent;
        }
        if (cloneImg && liveImg && liveImg.getAttribute('src')) {
          cloneImg.setAttribute('src', liveImg.getAttribute('src'));
          cloneImg.style.display = 'block';
        }
      }
    }

    var liveTgl = document.getElementById('inputTglSetor');
    var cloneTgl = clonedDoc.getElementById('inputTglSetor');
    if (liveTgl && cloneTgl && liveTgl.value) {
      var span = clonedDoc.createElement('span');
      span.textContent = formatTglSetorInput(liveTgl.value);
      span.style.fontWeight = '600';
      if (cloneTgl.parentNode) {
        cloneTgl.parentNode.replaceChild(span, cloneTgl);
      }
    }
  }

  function generateWaImage() {
    return new Promise(function (resolve, reject) {
      if (typeof html2canvas !== 'function') {
        reject('html2canvas tidak tersedia');
        return;
      }
      syncAllTtdPrintImages();
      $('#shiftLaporanWrap').show();

      var el = document.getElementById('shiftLaporanSheet');
      if (!el) {
        reject('Formulir laporan tidak ditemukan');
        return;
      }

      var timedOut = false;
      var timeoutId = setTimeout(function () {
        timedOut = true;
        reject('Membuat gambar terlalu lama. Tutup modal lalu coba lagi.');
      }, 60000);

      function selesai(err, dataUrl) {
        clearTimeout(timeoutId);
        if (timedOut) return;
        if (err) {
          reject(err);
        } else {
          resolve(dataUrl);
        }
      }

      html2canvas(el, {
        scale: 1.5,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        logging: false,
        imageTimeout: 15000,
        onclone: prepareSheetForCapture
      }).then(function (canvas) {
        try {
          var dataUrl = canvas.toDataURL('image/png');
          waImageState.dataUrl = dataUrl;
          waImageState.fileName = getWaFileName();
          waImageState.blob = dataUrlToBlob(dataUrl);
          selesai(null, dataUrl);
        } catch (e) {
          selesai(e.message || 'Gagal mengonversi gambar');
        }
      }).catch(function (err) {
        var msg = (err && err.message) ? err.message : 'Gagal membuat gambar laporan.';
        selesai(msg);
      });
    });
  }

  function selesaiLoadingWa() {
    $('#waGambarLoading').hide();
    $('#waGambarPreviewWrap').show();
  }

  function unduhWaGambar() {
    if (!waImageState.dataUrl) {
      Swal.fire('Info', 'Gambar belum siap. Tunggu proses selesai.', 'info');
      return;
    }
    var a = document.createElement('a');
    a.href = waImageState.dataUrl;
    a.download = waImageState.fileName || getWaFileName();
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  function bukaWaLink() {
    var caption = ($('#waCaption').val() || '').trim() || buildWaCaption();
    var phone = normalizeWaPhone($('#waNomorTujuan').val());
    var url = 'https://web.whatsapp.com/send';
    var params = [];
    if (phone) {
      params.push('phone=' + encodeURIComponent(phone));
    }
    if (caption) {
      params.push('text=' + encodeURIComponent(caption));
    }
    if (params.length) {
      url += '?' + params.join('&');
    }
    window.open(url, '_blank', 'noopener,noreferrer');
  }

  function getWaBlob() {
    if (waImageState.blob) {
      return waImageState.blob;
    }
    if (waImageState.dataUrl) {
      return dataUrlToBlob(waImageState.dataUrl);
    }
    return null;
  }

  function salinGambarKeClipboard() {
    return new Promise(function (resolve, reject) {
      var blob = getWaBlob();
      if (!blob) {
        reject(new Error('Gambar belum siap'));
        return;
      }
      if (!navigator.clipboard || typeof ClipboardItem === 'undefined') {
        reject(new Error('clipboard_unsupported'));
        return;
      }
      var clipData = {};
      clipData[blob.type || 'image/png'] = Promise.resolve(blob);
      navigator.clipboard.write([new ClipboardItem(clipData)]).then(resolve).catch(reject);
    });
  }

  function kirimWaWeb() {
    var caption = ($('#waCaption').val() || '').trim() || buildWaCaption();

    function bukaDanInfo(html) {
      bukaWaLink();
      $('#modalWaShift').modal('hide');
      Swal.fire({
        icon: 'info',
        title: 'Tempel gambar di WhatsApp Web',
        html: html,
        confirmButtonText: 'Mengerti'
      });
    }

    salinGambarKeClipboard()
      .then(function () {
        bukaDanInfo(
          'Gambar laporan sudah <strong>disalin</strong>.<br>' +
          'Setelah chat terbuka, klik kolom pesan lalu tekan <strong>Ctrl+V</strong> untuk menempelkan gambar.<br>' +
          (caption ? '<small class="text-muted">Caption: ' + caption + '</small>' : '')
        );
      })
      .catch(function () {
        unduhWaGambar();
        bukaDanInfo(
          'Browser tidak bisa menyalin gambar otomatis.<br>' +
          'File <strong>' + (waImageState.fileName || 'laporan-shift.png') + '</strong> sudah diunduh.<br>' +
          'Di WhatsApp Web, klik <strong>+</strong> → <strong>Photos &amp; videos</strong> atau <strong>Document</strong>, lalu pilih file tersebut.'
        );
      });
  }

  function bukaModalWa() {
    if (!state.data) {
      Swal.fire('Info', 'Muat data terlebih dahulu.', 'info');
      return;
    }
    hitungUlang();
    var defWa = (state.data.meta && state.data.meta.default_wa) ? state.data.meta.default_wa : '';
    if (!$('#waNomorTujuan').val() && defWa) {
      $('#waNomorTujuan').val(defWa);
    }
    $('#waCaption').val(buildWaCaption());
    waImageState = { dataUrl: '', blob: null, fileName: '' };
    $('#waGambarPreview').attr('src', '');
    $('#waGambarLoading').show();
    $('#waGambarPreviewWrap').hide();
    $('#btnWaUnduhGambar, #btnWaBuka').prop('disabled', true);
    $('#modalWaShift').modal('show');

    generateWaImage()
      .then(function (dataUrl) {
        $('#waGambarPreview').attr('src', dataUrl);
        $('#btnWaUnduhGambar, #btnWaBuka').prop('disabled', false);
      })
      .catch(function (err) {
        var msg = (typeof err === 'string') ? err : 'Gagal membuat gambar laporan.';
        Swal.fire('Gagal', msg, 'error');
      })
      .then(selesaiLoadingWa, selesaiLoadingWa);
  }

  $('#btnWa').on('click', bukaModalWa);

  $('#btnWaUnduhGambar').on('click', function () {
    unduhWaGambar();
  });

  $('#btnWaBuka').on('click', function () {
    if (!waImageState.dataUrl) {
      Swal.fire('Info', 'Gambar belum siap. Tunggu proses selesai.', 'info');
      return;
    }
    kirimWaWeb();
  });

  $('#filter_tanggal').on('change', function () {
    resetSheetTampilan();
    syncJamFromSaved();
  });
  if (PAKAI_PERGANTIAN_SHIFT) {
    $('#filter_shift').on('change', function () {
      resetSheetTampilan();
      terapkanJamDefaultShift();
    });
    var jamSekarang = new Date().getHours();
    if (jamSekarang >= 14) {
      $('#filter_shift').val('sore');
      terapkanJamDefaultShift();
    } else {
      syncJamFromSaved();
    }
  } else {
    $('#sheetShiftCol').hide();
  }
})();
</script>

