<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin === 'kurir' || $levelLogin === 'kasir') {
    echo "<script>document.location.href = 'bo';</script>";
    exit;
}

$jumlahBarisInvoice = 10;
$jumlahBarisSelisih = 3;
?>
<style>
  .bac-toolbar {
    margin-bottom: 16px;
  }
  .bac-form {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    color: #000;
    background: #fff;
    padding: 24px 28px;
    max-width: 210mm;
    margin: 0 auto;
  }
  .bac-form .bac-title {
    text-align: center;
    font-weight: bold;
    font-size: 15px;
    text-decoration: underline;
    text-transform: uppercase;
    margin-bottom: 18px;
  }
  .bac-form .bac-intro {
    line-height: 1.9;
    margin-bottom: 12px;
  }
  .bac-form .bac-blank {
    display: inline-block;
    min-width: 80px;
    border-bottom: 1px dotted #000;
    vertical-align: bottom;
  }
  .bac-form .bac-blank-sm {
    min-width: 36px;
  }
  .bac-form .bac-blank-md {
    min-width: 52px;
  }
  .bac-form .bac-blank-lg {
    min-width: 280px;
  }
  .bac-form table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin-bottom: 14px;
  }
  .bac-form th,
  .bac-form td {
    border: 1px solid #000;
    padding: 4px 5px;
    vertical-align: middle;
    word-wrap: break-word;
  }
  .bac-form th {
    text-align: center;
    font-weight: bold;
    font-size: 11px;
  }
  .bac-form .bac-td-no {
    text-align: center;
    width: 4%;
  }
  .bac-form .bac-td-center {
    text-align: center;
  }
  .bac-form .bac-td-jml,
  .bac-form .bac-td-sat {
    width: 4%;
    text-align: center;
    font-size: 10px;
  }
  .bac-form .bac-section-title {
    font-weight: bold;
    font-size: 12px;
    margin: 10px 0 8px;
    text-transform: uppercase;
  }
  .bac-form .bac-catatan {
    margin-top: 10px;
    font-weight: bold;
  }
  .bac-form .bac-catatan-line {
    margin-top: 8px;
    border-bottom: 1px dotted #000;
    height: 22px;
  }
  .bac-form .bac-ttd {
    margin-top: 28px;
    display: flex;
    justify-content: space-between;
    gap: 16px;
  }
  .bac-form .bac-ttd-block {
    flex: 1;
    text-align: center;
    font-size: 11px;
  }
  .bac-form .bac-ttd-block .bac-ttd-org {
    font-weight: bold;
    margin-bottom: 6px;
    min-height: 16px;
  }
  .bac-form .bac-ttd-line {
    border-bottom: 1px dotted #000;
    height: 52px;
    margin: 0 10px 6px;
  }
  .bac-form .bac-ttd-label {
    font-weight: bold;
    text-transform: uppercase;
    font-size: 10px;
  }
  @media print {
    .main-header,
    .main-sidebar,
    .main-footer,
    .content-header,
    .bac-toolbar,
    .breadcrumb {
      display: none !important;
    }
    .content-wrapper,
    .content {
      margin: 0 !important;
      padding: 0 !important;
    }
    .card {
      border: none !important;
      box-shadow: none !important;
    }
    .card-body {
      padding: 0 !important;
    }
    .bac-form {
      padding: 0;
      max-width: none;
    }
    @page {
      size: A4 portrait;
      margin: 12mm;
    }
  }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Cetak Berita Acara Kirim Barang</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Cetak Berita Acara Kirim Barang</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-body">
          <div class="bac-toolbar text-center">
            <button type="button" class="btn btn-success btn-lg" onclick="window.print();">
              <i class="fa fa-print"></i> Cetak Form Kosong
            </button>
          </div>

          <div class="bac-form" id="bac-form">
            <div class="bac-title">Berita Acara Pengiriman Barang</div>

            <div class="bac-intro">
              Pada hari ini <span class="bac-blank bac-blank-lg">&nbsp;</span>
              TGL <span class="bac-blank bac-blank-sm">&nbsp;</span>
              BL <span class="bac-blank bac-blank-sm">&nbsp;</span>
              THN <span class="bac-blank bac-blank-md">&nbsp;</span>
            </div>
            <div class="bac-intro">
              Telah dikirim barang dari Gudang Ke <span class="bac-blank bac-blank-lg">&nbsp;</span>
            </div>
            <div class="bac-intro">
              Dengan no invoice sebagai berikut :
            </div>

            <table>
              <thead>
                <tr>
                  <th rowspan="2" class="bac-td-no">NO</th>
                  <th rowspan="2">TGL INVOICE</th>
                  <th rowspan="2">NO INVOICE</th>
                  <th rowspan="2">JUMLAH JENIS BARANG</th>
                  <th colspan="2">KESESUAIAN</th>
                  <th rowspan="2">KETERANGAN</th>
                </tr>
                <tr>
                  <th>SESUAI</th>
                  <th>TIDAK SESUAI</th>
                </tr>
              </thead>
              <tbody>
                <?php for ($no = 1; $no <= $jumlahBarisInvoice; $no++) : ?>
                <tr>
                  <td class="bac-td-no"><?= $no; ?></td>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>
                </tr>
                <?php endfor; ?>
              </tbody>
            </table>

            <div class="bac-section-title">Barang yang Tidak Sesuai / Belum Terkirim</div>

            <table>
              <thead>
                <tr>
                  <th rowspan="2" class="bac-td-no">NO</th>
                  <th rowspan="2">NO INVOICE</th>
                  <th rowspan="2">BARKODE</th>
                  <th rowspan="2">NAMA BARANG</th>
                  <th colspan="2">DIKIRIM</th>
                  <th colspan="2">DITERIMA</th>
                  <th colspan="2">BELUM TERKIRIM</th>
                  <th rowspan="2">KETERANGAN</th>
                </tr>
                <tr>
                  <th class="bac-td-jml">JML</th>
                  <th class="bac-td-sat">SAT</th>
                  <th class="bac-td-jml">JML</th>
                  <th class="bac-td-sat">SAT</th>
                  <th class="bac-td-jml">JML</th>
                  <th class="bac-td-sat">SAT</th>
                </tr>
              </thead>
              <tbody>
                <?php for ($no = 1; $no <= $jumlahBarisSelisih; $no++) : ?>
                <tr>
                  <td class="bac-td-no"><?= $no; ?></td>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>
                  <td class="bac-td-jml">&nbsp;</td>
                  <td class="bac-td-sat">&nbsp;</td>
                  <td class="bac-td-jml">&nbsp;</td>
                  <td class="bac-td-sat">&nbsp;</td>
                  <td class="bac-td-jml">&nbsp;</td>
                  <td class="bac-td-sat">&nbsp;</td>
                  <td>&nbsp;</td>
                </tr>
                <?php endfor; ?>
              </tbody>
            </table>

            <div class="bac-catatan">Catatan Tambahan</div>
            <div class="bac-catatan-line"></div>
            <div class="bac-catatan-line"></div>

            <div class="bac-ttd">
              <div class="bac-ttd-block">
                <div class="bac-ttd-org">NU MART .......</div>
                <div class="bac-ttd-line"></div>
                <div class="bac-ttd-label">Penerima</div>
              </div>
              <div class="bac-ttd-block">
                <div class="bac-ttd-org">&nbsp;</div>
                <div class="bac-ttd-line"></div>
                <div class="bac-ttd-label">Pengirim</div>
              </div>
              <div class="bac-ttd-block">
                <div class="bac-ttd-org">NU GROSIR</div>
                <div class="bac-ttd-line"></div>
                <div class="bac-ttd-label">Bagian Gudang</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
</div>

<?php include '_footer.php'; ?>
</body>
</html>
