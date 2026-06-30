<?php
$isPrint = isset($_POST['cetak_selisih']);

if ($isPrint) {
    include '_header.php';
} else {
    include '_header.php';
    include '_nav.php';
    include '_sidebar.php';
}

$refRaw = $isPrint
    ? trim((string) ($_POST['transfer_ref'] ?? ''))
    : trim((string) base64_decode((string) ($_GET['no'] ?? ''), true));
if ($refRaw === '' || $refRaw === false) {
    $refRaw = trim((string) base64_decode((string) ($_GET['no'] ?? '')));
}
$id = mysqli_real_escape_string($conn, $refRaw);
if ($id === '') {
    header('Location: transfer-stock-cabang-keluar');
    exit;
}

$cabang = (int) $sessionCabang;
$transferRows = query(
    "SELECT * FROM transfer WHERE transfer_ref = '$id' AND (
        transfer_cabang = $cabang
        OR transfer_pengirim_cabang = $cabang
        OR transfer_penerima_cabang = $cabang
    ) LIMIT 1"
);
if (empty($transferRows) && $cabang === 0) {
    $transferRows = query("SELECT * FROM transfer WHERE transfer_ref = '$id' LIMIT 1");
}
if (empty($transferRows)) {
    header('Location: transfer-stock-cabang-masuk');
    exit;
}
$transfer = $transferRows[0];

$pengirimCabang = (int) ($transfer['transfer_pengirim_cabang'] ?? $transfer['transfer_cabang'] ?? 0);
$penerimaCabang = (int) ($transfer['transfer_penerima_cabang'] ?? 0);

$tokoPengirimRows = query("SELECT toko_nama FROM toko WHERE toko_cabang = $pengirimCabang LIMIT 1");
$tokoPenerimaRows = query("SELECT toko_nama FROM toko WHERE toko_cabang = $penerimaCabang LIMIT 1");
$namaPengirim = $tokoPengirimRows[0]['toko_nama'] ?? 'NU GROSIR';
$namaPenerima = $tokoPenerimaRows[0]['toko_nama'] ?? 'NU MART';

function transfer_selisih_load_produk($conn, $ref)
{
    $rows = [];
    $refEsc = mysqli_real_escape_string($conn, $ref);
    $res = mysqli_query(
        $conn,
        "SELECT
            tp.tpk_qty,
            tp.tpk_barang_sn_desc,
            b.barang_kode,
            b.barang_nama,
            s.satuan_nama
         FROM transfer_produk_keluar tp
         INNER JOIN barang b ON tp.tpk_barang_id = b.barang_id
         LEFT JOIN satuan s ON b.satuan_id = s.satuan_id AND s.satuan_cabang = 0
         WHERE tp.tpk_ref = '$refEsc'
         ORDER BY b.barang_nama ASC, tp.tpk_id ASC"
    );
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $nama = (string) ($r['barang_nama'] ?? '');
            if (!empty($r['tpk_barang_sn_desc'])) {
                $nama .= ' (SN: ' . $r['tpk_barang_sn_desc'] . ')';
            }
            $rows[] = [
                'kode' => (string) ($r['barang_kode'] ?? ''),
                'nama' => $nama,
                'qty_kirim' => (string) ($r['tpk_qty'] ?? ''),
                'sat_kirim' => !empty($r['satuan_nama']) ? (string) $r['satuan_nama'] : '-',
            ];
        }
    }
    return $rows;
}

function transfer_selisih_lines_from_post(array $post)
{
    $kodes = $post['barang_kode'] ?? [];
    $namas = $post['barang_nama'] ?? [];
    $qtyKirim = $post['qty_kirim'] ?? [];
    $satKirim = $post['sat_kirim'] ?? [];
    $qtyTerima = $post['qty_terima'] ?? [];
    $satTerima = $post['sat_terima'] ?? [];
    $qtyBelum = $post['qty_belum'] ?? [];
    $satBelum = $post['sat_belum'] ?? [];
    $ketBaris = $post['ket_baris'] ?? [];
    $lines = [];
    $count = max(
        count($kodes),
        count($namas),
        count($qtyKirim)
    );
    for ($i = 0; $i < $count; $i++) {
        $lines[] = [
            'kode' => trim((string) ($kodes[$i] ?? '')),
            'nama' => trim((string) ($namas[$i] ?? '')),
            'qty_kirim' => trim((string) ($qtyKirim[$i] ?? '')),
            'sat_kirim' => trim((string) ($satKirim[$i] ?? '')),
            'qty_terima' => trim((string) ($qtyTerima[$i] ?? '')),
            'sat_terima' => trim((string) ($satTerima[$i] ?? '')),
            'qty_belum' => trim((string) ($qtyBelum[$i] ?? '')),
            'sat_belum' => trim((string) ($satBelum[$i] ?? '')),
            'ket_baris' => trim((string) ($ketBaris[$i] ?? '')),
        ];
    }
    return $lines;
}

function transfer_selisih_pad_lines(array $lines, $minRows = 5)
{
    while (count($lines) < $minRows) {
        $lines[] = [
            'kode' => '',
            'nama' => '',
            'qty_kirim' => '',
            'sat_kirim' => '',
            'qty_terima' => '',
            'sat_terima' => '',
            'qty_belum' => '',
            'sat_belum' => '',
            'ket_baris' => '',
        ];
    }
    return $lines;
}

function transfer_selisih_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$produkRows = transfer_selisih_load_produk($conn, $id);
$keteranganUmum = '';
$printLines = [];

if ($isPrint) {
    $printLines = transfer_selisih_pad_lines(transfer_selisih_lines_from_post($_POST));
    $keteranganUmum = trim((string) ($_POST['keterangan_umum'] ?? ''));
}
?>
<style>
  .cetak-selisih-wrap {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    color: #000;
    max-width: 1100px;
    margin: 0 auto;
    padding: 16px;
  }
  .cetak-selisih-title {
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 12px;
    text-transform: uppercase;
  }
  .cetak-selisih-meta {
    margin-bottom: 10px;
    font-size: 11px;
  }
  .cetak-selisih-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
  }
  .cetak-selisih-table th,
  .cetak-selisih-table td {
    border: 1px solid #000;
    padding: 4px 5px;
    vertical-align: middle;
    word-wrap: break-word;
  }
  .cetak-selisih-table th {
    text-align: center;
    font-weight: bold;
    font-size: 11px;
  }
  .cetak-selisih-table .col-no { width: 4%; text-align: center; }
  .cetak-selisih-table .col-kode { width: 10%; }
  .cetak-selisih-table .col-nama { width: 18%; }
  .cetak-selisih-table .col-jml { width: 6%; text-align: center; }
  .cetak-selisih-table .col-sat { width: 7%; text-align: center; }
  .cetak-selisih-table .col-ket { width: 13%; }
  .cetak-selisih-keterangan {
    margin-top: 14px;
    font-weight: bold;
  }
  .cetak-selisih-keterangan-isi {
    margin-top: 6px;
    min-height: 48px;
    white-space: pre-wrap;
    border-bottom: 1px dotted #000;
    padding: 4px 0;
  }
  .cetak-selisih-ttd {
    margin-top: 28px;
    display: flex;
    justify-content: space-between;
    gap: 12px;
  }
  .cetak-selisih-ttd-block {
    flex: 1;
    min-width: 0;
    text-align: center;
    font-size: 11px;
  }
  .cetak-selisih-ttd-block .org {
    font-weight: bold;
    margin-bottom: 8px;
    min-height: 16px;
  }
  .cetak-selisih-ttd-line {
    border-bottom: 1px dotted #000;
    height: 52px;
    margin: 0 8px 6px;
  }
  .cetak-selisih-ttd-label {
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
  }
  .form-selisih-table {
    table-layout: fixed;
    width: 100%;
    min-width: 1100px;
  }
  .form-selisih-table .col-no-form { width: 40px; }
  .form-selisih-table .col-kode-form { width: 110px; }
  .form-selisih-table .col-nama-form { width: 200px; }
  .form-selisih-table .col-jml-form { width: 100px; min-width: 100px; }
  .form-selisih-table .col-sat-form { width: 120px; min-width: 120px; }
  .form-selisih-table .col-ket-form { width: 160px; }
  .form-selisih-table input.form-control,
  .form-selisih-table textarea.form-control {
    font-size: 13px;
    padding: 6px 10px;
    width: 100%;
  }
  .form-selisih-table input.col-input-jml {
    min-width: 88px;
    text-align: center;
  }
  .form-selisih-table input.col-input-sat {
    min-width: 108px;
    text-align: center;
  }
  .form-selisih-table .qty-kirim-ro {
    background: #f4f4f4;
    text-align: center;
    font-weight: 600;
  }
  @media print {
    .no-print { display: none !important; }
    .cetak-selisih-wrap { padding: 0; max-width: none; }
    @page { margin: 12mm; }
  }
</style>

<?php if (!$isPrint) : ?>
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1>Form Barang Tidak Sesuai / Belum Terkirim</h1>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Cetak Selisih Transfer</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card card-warning">
        <div class="card-header">
          <h3 class="card-title">Isi data penerimaan sebelum cetak</h3>
        </div>
        <form method="post" action="transfer-cetak-selisih" id="form-selisih-transfer" target="_blank">
          <input type="hidden" name="cetak_selisih" value="1">
          <input type="hidden" name="transfer_ref" value="<?= transfer_selisih_h($id); ?>">
          <div class="card-body">
            <p class="text-muted mb-3">
              No. Ref: <strong><?= transfer_selisih_h($id); ?></strong>
              &nbsp;|&nbsp; Pengirim: <strong><?= transfer_selisih_h($namaPengirim); ?></strong>
              &nbsp;→&nbsp; Penerima: <strong><?= transfer_selisih_h($namaPenerima); ?></strong>
              <br><small>Isi <strong>Diterima</strong> atau <strong>Belum Terkirim</strong>. Jika mengisi jumlah diterima, jumlah belum terkirim dihitung otomatis (bisa diubah manual).</small>
            </p>
            <div class="table-responsive">
              <table class="table table-bordered table-sm form-selisih-table">
                <thead class="thead-light">
                  <tr>
                    <th rowspan="2" class="col-no-form text-center">No</th>
                    <th rowspan="2" class="col-kode-form">Barkode</th>
                    <th rowspan="2" class="col-nama-form">Nama Barang</th>
                    <th colspan="2" class="text-center">Dikirim</th>
                    <th colspan="2" class="text-center">Diterima</th>
                    <th colspan="2" class="text-center">Belum Terkirim</th>
                    <th rowspan="2" class="col-ket-form">Keterangan</th>
                  </tr>
                  <tr>
                    <th class="text-center col-jml-form">Jml</th>
                    <th class="text-center col-sat-form">Sat</th>
                    <th class="text-center col-jml-form">Jml</th>
                    <th class="text-center col-sat-form">Sat</th>
                    <th class="text-center col-jml-form">Jml</th>
                    <th class="text-center col-sat-form">Sat</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; ?>
                  <?php foreach ($produkRows as $row) : ?>
                  <tr class="row-selisih"
                      data-qty-kirim="<?= transfer_selisih_h($row['qty_kirim']); ?>"
                      data-sat-kirim="<?= transfer_selisih_h($row['sat_kirim']); ?>">
                    <td class="text-center"><?= $no++; ?></td>
                    <td>
                      <?= transfer_selisih_h($row['kode']); ?>
                      <input type="hidden" name="barang_kode[]" value="<?= transfer_selisih_h($row['kode']); ?>">
                    </td>
                    <td>
                      <?= transfer_selisih_h($row['nama']); ?>
                      <input type="hidden" name="barang_nama[]" value="<?= transfer_selisih_h($row['nama']); ?>">
                    </td>
                    <td>
                      <input type="text" class="form-control qty-kirim-ro col-input-jml" name="qty_kirim[]" value="<?= transfer_selisih_h($row['qty_kirim']); ?>" readonly>
                    </td>
                    <td>
                      <input type="text" class="form-control qty-kirim-ro col-input-sat" name="sat_kirim[]" value="<?= transfer_selisih_h($row['sat_kirim']); ?>" readonly>
                    </td>
                    <td>
                      <input type="number" step="0.01" min="0" class="form-control qty-terima col-input-jml" name="qty_terima[]" placeholder="0">
                    </td>
                    <td>
                      <input type="text" class="form-control sat-terima col-input-sat" name="sat_terima[]" placeholder="<?= transfer_selisih_h($row['sat_kirim']); ?>">
                    </td>
                    <td>
                      <input type="number" step="0.01" min="0" class="form-control qty-belum col-input-jml" name="qty_belum[]" placeholder="0">
                    </td>
                    <td>
                      <input type="text" class="form-control sat-belum col-input-sat" name="sat_belum[]" placeholder="<?= transfer_selisih_h($row['sat_kirim']); ?>">
                    </td>
                    <td>
                      <input type="text" class="form-control" name="ket_baris[]" placeholder="Keterangan baris">
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="form-group mt-3">
              <label for="keterangan_umum"><strong>Keterangan :</strong></label>
              <textarea class="form-control" id="keterangan_umum" name="keterangan_umum" rows="4" placeholder="Catatan umum selisih / barang belum terkirim"></textarea>
            </div>
          </div>
          <div class="card-footer text-right">
            <button type="button" class="btn btn-default" onclick="window.history.back()">Batal</button>
            <button type="submit" class="btn btn-warning">
              <i class="fas fa-print"></i> Preview &amp; Cetak
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>
</div>

<script>
(function () {
  function parseNum(v) {
    var n = parseFloat(String(v).replace(',', '.'));
    return isNaN(n) ? null : n;
  }

  document.querySelectorAll('.row-selisih').forEach(function (tr) {
    var qtyKirim = parseNum(tr.getAttribute('data-qty-kirim')) || 0;
    var satKirim = tr.getAttribute('data-sat-kirim') || '';
    var qtyTerima = tr.querySelector('.qty-terima');
    var satTerima = tr.querySelector('.sat-terima');
    var qtyBelum = tr.querySelector('.qty-belum');
    var satBelum = tr.querySelector('.sat-belum');

    qtyTerima.addEventListener('input', function () {
      var qt = parseNum(qtyTerima.value);
      if (qt === null) {
        return;
      }
      if (!satTerima.value.trim()) {
        satTerima.value = satKirim;
      }
      var selisih = Math.max(0, qtyKirim - qt);
      qtyBelum.value = selisih % 1 === 0 ? String(selisih) : selisih.toFixed(2);
      if (!satBelum.value.trim() && selisih > 0) {
        satBelum.value = satKirim;
      }
      if (selisih === 0) {
        qtyBelum.value = '';
        if (!satBelum.dataset.manual) {
          satBelum.value = '';
        }
      }
    });

    qtyBelum.addEventListener('input', function () {
      satBelum.dataset.manual = satBelum.value.trim() ? '1' : '';
    });
    satBelum.addEventListener('input', function () {
      satBelum.dataset.manual = satBelum.value.trim() ? '1' : '';
    });
  });
})();
</script>

<?php include '_footer.php'; ?>

<?php else : ?>

<div class="content">
  <section class="content">
    <div class="container-fluid">
      <div class="row no-print mb-2">
        <div class="col-12 text-right">
          <button type="button" class="btn btn-default" onclick="window.close()">Tutup</button>
          <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
        </div>
      </div>
      <div class="cetak-selisih-wrap">
        <div class="cetak-selisih-title">Barang yang Tidak Sesuai / Belum Terkirim</div>
        <div class="cetak-selisih-meta">
          No. Ref: <strong><?= transfer_selisih_h($id); ?></strong>
          &nbsp;|&nbsp;
          Tanggal Kirim: <strong><?= transfer_selisih_h(tanggal_indo($transfer['transfer_date'])); ?></strong>
          &nbsp;|&nbsp;
          Pengirim: <strong><?= transfer_selisih_h($namaPengirim); ?></strong>
          &nbsp;→&nbsp;
          Penerima: <strong><?= transfer_selisih_h($namaPenerima); ?></strong>
        </div>

        <table class="cetak-selisih-table">
          <thead>
            <tr>
              <th rowspan="2" class="col-no">NO</th>
              <th rowspan="2" class="col-kode">BARKODE</th>
              <th rowspan="2" class="col-nama">NAMA BARANG</th>
              <th colspan="2">DIKIRIM</th>
              <th colspan="2">DITERIMA</th>
              <th colspan="2">BELUM TERKIRIM</th>
              <th rowspan="2" class="col-ket">KETERANGAN</th>
            </tr>
            <tr>
              <th class="col-jml">JML</th>
              <th class="col-sat">SAT</th>
              <th class="col-jml">JML</th>
              <th class="col-sat">SAT</th>
              <th class="col-jml">JML</th>
              <th class="col-sat">SAT</th>
            </tr>
          </thead>
          <tbody>
            <?php $no = 1; ?>
            <?php foreach ($printLines as $line) : ?>
              <tr>
                <td class="col-no"><?= $no++; ?></td>
                <td class="col-kode"><?= transfer_selisih_h($line['kode']); ?></td>
                <td class="col-nama"><?= transfer_selisih_h($line['nama']); ?></td>
                <td class="col-jml"><?= transfer_selisih_h($line['qty_kirim']); ?></td>
                <td class="col-sat"><?= transfer_selisih_h($line['sat_kirim']); ?></td>
                <td class="col-jml"><?= transfer_selisih_h($line['qty_terima']); ?></td>
                <td class="col-sat"><?= transfer_selisih_h($line['sat_terima']); ?></td>
                <td class="col-jml"><?= transfer_selisih_h($line['qty_belum']); ?></td>
                <td class="col-sat"><?= transfer_selisih_h($line['sat_belum']); ?></td>
                <td class="col-ket"><?= transfer_selisih_h($line['ket_baris']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="cetak-selisih-keterangan">KETERANGAN :</div>
        <div class="cetak-selisih-keterangan-isi"><?= transfer_selisih_h($keteranganUmum !== '' ? $keteranganUmum : ' '); ?></div>

        <div class="cetak-selisih-ttd">
          <div class="cetak-selisih-ttd-block">
            <div class="org"><?= transfer_selisih_h(strtoupper($namaPenerima)); ?></div>
            <div class="cetak-selisih-ttd-line"></div>
            <div class="cetak-selisih-ttd-label">Penerima</div>
          </div>
          <div class="cetak-selisih-ttd-block">
            <div class="org"><?= transfer_selisih_h(strtoupper($namaPengirim)); ?></div>
            <div class="cetak-selisih-ttd-line"></div>
            <div class="cetak-selisih-ttd-label">Pengirim</div>
          </div>
          <div class="cetak-selisih-ttd-block">
            <div class="org"><?= transfer_selisih_h(strtoupper($namaPengirim)); ?></div>
            <div class="cetak-selisih-ttd-line"></div>
            <div class="cetak-selisih-ttd-label">Bagian Gudang</div>
          </div>
          <div class="cetak-selisih-ttd-block">
            <div class="org"><?= transfer_selisih_h(strtoupper($namaPengirim)); ?></div>
            <div class="cetak-selisih-ttd-line"></div>
            <div class="cetak-selisih-ttd-label">Yang Menyiapkan</div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
window.addEventListener('load', function () {
  window.print();
});
</script>

<?php endif; ?>
