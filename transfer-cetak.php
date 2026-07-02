<?php 
  include '_header.php'; 
?>

<?php  
$id = mysqli_real_escape_string($conn, base64_decode($_GET['no']));

// Query transfer
$transfer = query("SELECT * FROM transfer WHERE transfer_ref = '$id' AND transfer_cabang = '$sessionCabang'")[0];
if (!$transfer) {
  header("Location: transfer-stock-cabang-keluar");
  exit;
}

// Query data kasir dan toko
$kasir = $transfer['transfer_user'];
$kasirData = query("SELECT user_nama FROM user WHERE user_id = '$kasir'")[0]['user_nama'];

$tokoPengirimData = query("SELECT * FROM toko WHERE toko_cabang = '{$transfer['transfer_cabang']}'")[0];
$tokoPenerimaData = query("SELECT * FROM toko WHERE toko_cabang = '{$transfer['transfer_penerima_cabang']}'")[0];
$namaPengirim = $tokoPengirimData['toko_nama'] ?? 'NU GROSIR';
$namaPenerima = $tokoPenerimaData['toko_nama'] ?? 'NU MART';

$jumlahBarisSelisih = 3;
?>
<style>
  .cetak-selisih-bagian {
    margin-top: 28px;
    page-break-inside: avoid;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    color: #000;
  }
  .cetak-selisih-bagian .cetak-selisih-title {
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 12px;
    text-transform: uppercase;
  }
  .cetak-selisih-bagian .cetak-selisih-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
  }
  .cetak-selisih-bagian .cetak-selisih-table th,
  .cetak-selisih-bagian .cetak-selisih-table td {
    border: 1px solid #000;
    padding: 4px 5px;
    vertical-align: middle;
    word-wrap: break-word;
  }
  .cetak-selisih-bagian .cetak-selisih-table th {
    text-align: center;
    font-weight: bold;
    font-size: 11px;
  }
  .cetak-selisih-bagian .col-no { width: 3%; text-align: center; }
  .cetak-selisih-bagian .col-kode { width: 8%; }
  .cetak-selisih-bagian .col-nama { width: 24%; }
  .cetak-selisih-bagian .col-jml { width: 2.5%; text-align: center; padding-left: 1px; padding-right: 1px; font-size: 10px; }
  .cetak-selisih-bagian .col-sat { width: 3.5%; text-align: center; padding-left: 1px; padding-right: 1px; font-size: 10px; }
  .cetak-selisih-bagian .col-ket { width: 24%; }
  .cetak-selisih-bagian .cetak-selisih-keterangan {
    margin-top: 14px;
    font-weight: bold;
  }
  .cetak-selisih-bagian .cetak-selisih-lines {
    margin-top: 6px;
    border-bottom: 1px dotted #000;
    height: 22px;
  }
  .cetak-selisih-bagian .cetak-selisih-ttd {
    margin-top: 28px;
    display: flex;
    justify-content: space-between;
    gap: 12px;
  }
  .cetak-selisih-bagian .cetak-selisih-ttd-block {
    flex: 1;
    min-width: 0;
    text-align: center;
    font-size: 11px;
  }
  .cetak-selisih-bagian .cetak-selisih-ttd-block .org {
    font-weight: bold;
    margin-bottom: 8px;
    min-height: 16px;
  }
  .cetak-selisih-bagian .cetak-selisih-ttd-line {
    border-bottom: 1px dotted #000;
    height: 52px;
    margin: 0 8px 6px;
  }
  .cetak-selisih-bagian .cetak-selisih-ttd-label {
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
  }
</style>
<div class="content">
  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-12">
          <div class="invoice p-3 mb-3">
            <div class="row invoice-info">
              <div class="col-sm-4">
                <h4><b>Dari Pengirim</b></h4>
                <address>
                  <strong><?= $tokoPengirimData['toko_nama']; ?></strong><br>
                  <?= $tokoPengirimData['toko_alamat']; ?><br>
                  Tlpn/Wa: <?= $tokoPengirimData['toko_tlpn']; ?> / <?= $tokoPengirimData['toko_wa']; ?><br>
                  Email: <?= $tokoPengirimData['toko_email']; ?><br>
                  <b>Kasir:</b> <?= $kasirData; ?>
                </address>
              </div>

              <div class="col-sm-4">
                <h4><b>Penerima</b></h4>
                <address>
                  <strong><?= $tokoPenerimaData['toko_nama']; ?></strong><br>
                  <?= $tokoPenerimaData['toko_alamat']; ?><br>
                  Tlpn/Wa: <?= $tokoPenerimaData['toko_tlpn']; ?> / <?= $tokoPenerimaData['toko_wa']; ?><br>
                  Email: <?= $tokoPenerimaData['toko_email']; ?><br>
                </address>
              </div>

              <div class="col-sm-4">
                <h4>
                  <i class="fas fa-globe"></i> No. Ref: <?= $id; ?><br>
                  <small>Tanggal: <?= tanggal_indo($transfer['transfer_date']); ?></small>
                </h4>
              </div>
            </div>

            <div class="row">
              <div class="col-12 table-responsive">
                <div class="table-auto">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th>No</th>
                        <th>Barcode</th>
                        <th>Nama Barang</th>
                        <th style="text-align: center;">Satuan</th>
                        <th style="text-align: center;">Qty</th>
                        <th>Harga Satuan</th>
                        <th>Total Harga</th>
                        <th>Ceklis</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php 
                      $queryProduct = $conn->query("
                        SELECT 
                          tp.tpk_id, tp.tpk_qty, tp.tpk_barang_sn_desc,
                          b.barang_kode, b.barang_nama, b.barang_harga_beli,
                          s.satuan_nama
                        FROM transfer_produk_keluar tp
                        JOIN barang b ON tp.tpk_barang_id = b.barang_id
                        LEFT JOIN satuan s ON b.satuan_id = s.satuan_id AND s.satuan_cabang = 0
                        WHERE tp.tpk_ref = '$id' 
                        ORDER BY tp.tpk_id DESC
                      ");

                      $i = 1; $subtotal = 0;
                      while ($row = mysqli_fetch_array($queryProduct)) {
                        $qty = $row['tpk_qty'];
                        $hargaSatuan = $row['barang_harga_beli'];
                        $totalHarga = $qty * $hargaSatuan;
                        $subtotal += $totalHarga;
                        $satuanNama = isset($row['satuan_nama']) && !empty($row['satuan_nama']) ? $row['satuan_nama'] : '-';
                      ?>
                      <tr>
                        <td><?= $i++; ?></td>
                        <td><?= $row['barang_kode']; ?></td>
                        <td>
                          <?= $row['barang_nama']; ?><br>
                          <?php if (!empty($row['tpk_barang_sn_desc'])): ?>
                            <small>No. SN: <?= $row['tpk_barang_sn_desc']; ?></small>
                          <?php endif; ?>
                        </td>
                        <td style="text-align: center;"><?= $satuanNama; ?></td>
                        <td style="text-align: center;"><?= $qty; ?> <?= $satuanNama; ?></td>
                        <td>Rp. <?= number_format($hargaSatuan, 0, ',', '.'); ?></td>
                        <td>Rp. <?= number_format($totalHarga, 0, ',', '.'); ?></td>
                        <td><input type="checkbox"></td>
                      </tr>
                      <?php } ?>
                    </tbody>
                  </table>
                  
                <div class="text-right">
                     <div class="text-right">
                    <b>SUBTOTAL</br>
                        Rp. <?= number_format($subtotal, 0, ',', '.'); ?></br>
                    </div>
                </div>
                
                
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-12">
                <b>Catatan Pengiriman:</b> 
                <?= $transfer['transfer_note'] ?: '-'; ?>
              </div>
            </div>

            <div class="cetak-selisih-bagian">
              <div class="cetak-selisih-title">Barang yang Tidak Sesuai / Belum Terkirim</div>

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
                  <?php for ($noSelisih = 1; $noSelisih <= $jumlahBarisSelisih; $noSelisih++) : ?>
                  <tr>
                    <td class="col-no"><?= $noSelisih; ?></td>
                    <td class="col-kode">&nbsp;</td>
                    <td class="col-nama">&nbsp;</td>
                    <td class="col-jml">&nbsp;</td>
                    <td class="col-sat">&nbsp;</td>
                    <td class="col-jml">&nbsp;</td>
                    <td class="col-sat">&nbsp;</td>
                    <td class="col-jml">&nbsp;</td>
                    <td class="col-sat">&nbsp;</td>
                    <td class="col-ket">&nbsp;</td>
                  </tr>
                  <?php endfor; ?>
                </tbody>
              </table>

              <div class="cetak-selisih-keterangan">KETERANGAN :</div>
              <div class="cetak-selisih-lines"></div>
              <div class="cetak-selisih-lines"></div>
              <div class="cetak-selisih-lines"></div>

              <div class="cetak-selisih-ttd">
                <div class="cetak-selisih-ttd-block">
                  <div class="org"><?= htmlspecialchars(strtoupper($namaPenerima), ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="cetak-selisih-ttd-line"></div>
                  <div class="cetak-selisih-ttd-label">Penerima</div>
                </div>
                <div class="cetak-selisih-ttd-block">
                  <div class="org"><?= htmlspecialchars(strtoupper($namaPengirim), ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="cetak-selisih-ttd-line"></div>
                  <div class="cetak-selisih-ttd-label">Pengirim</div>
                </div>
                <div class="cetak-selisih-ttd-block">
                  <div class="org"><?= htmlspecialchars(strtoupper($namaPengirim), ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="cetak-selisih-ttd-line"></div>
                  <div class="cetak-selisih-ttd-label">Bagian Gudang</div>
                </div>
                <div class="cetak-selisih-ttd-block">
                  <div class="org"><?= htmlspecialchars(strtoupper($namaPengirim), ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="cetak-selisih-ttd-line"></div>
                  <div class="cetak-selisih-ttd-label">Yang Menyiapkan</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="text-center">
    © <?= date("Y"); ?> Copyright PCNU KAB MAGELANG All rights reserved.
  </div>
</div>
<script>
window.onload = function() {
  // Tambahkan subtotal di halaman terakhir
  const allTables = document.querySelectorAll('.table-auto');
  if (allTables.length > 0) {
    allTables[allTables.length - 1].classList.add('last-page');
  }

  // Tambahkan nomor halaman ke setiap halaman
  const body = document.body;
  const pages = Math.ceil(body.scrollHeight / window.innerHeight);

  window.print();
};
</script>

