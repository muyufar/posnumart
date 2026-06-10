<?php
$posAutoHideSidebar = true;
include '_header.php';
include '_nav.php';
include '_sidebar.php';
?>

<?php
// ambil data di URL
$id = $_GET['no'] ?? '';

// query data invoice
$invoiceRows = query("SELECT * FROM invoice WHERE penjualan_invoice = '" . mysqli_real_escape_string($conn, $id) . "' && invoice_cabang = '$sessionCabang'");
if (empty($invoiceRows)) {
  header('Location: penjualan');
  exit;
}
$invoice = $invoiceRows[0];
$backCustomerType = base64_encode((string) ($invoice['invoice_customer_category'] ?? 0));
?>

<style>
  .inv-kbd-shortcuts {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem 0.55rem;
    margin-bottom: 0.5rem;
    padding: 0.4rem 0.6rem;
    background: #f0fdfa;
    border: 1px solid #99f6e4;
    border-radius: 8px;
    font-size: 0.72rem;
    color: #115e59;
  }

  .inv-kbd-shortcuts kbd {
    display: inline-block;
    padding: 0.1rem 0.35rem;
    font-size: 0.68rem;
    font-family: inherit;
    color: #0f766e;
    background: #fff;
    border: 1px solid #5eead4;
    border-radius: 4px;
  }

  .inv-kbd-help-btn {
    border: none;
    background: transparent;
    color: #0f766e;
    font-size: 0.72rem;
    cursor: pointer;
    padding: 0.1rem 0.3rem;
    border-radius: 4px;
  }

  .inv-kbd-help-btn:hover {
    background: #ccfbf1;
  }

  .inv-print-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.65rem;
  }

  .inv-print-tab-opt {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    margin: 0;
    padding: 0.35rem 0.65rem;
    font-size: 0.82rem;
    color: #374151;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    user-select: none;
  }

  .inv-print-tab-opt input {
    margin: 0;
    cursor: pointer;
  }
</style>

<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Invoice</h1>
        </div>
        <div class="col-sm-6">
          <div class="inv-kbd-shortcuts float-sm-right" aria-label="Pintasan keyboard invoice">
            <span><i class="fa fa-keyboard-o"></i> <b>Shortcut</b></span>
            <span><kbd>F1</kbd> Print</span>
            <span><kbd>Shift+F1</kbd> Print tab baru</span>
            <span><kbd>F2</kbd> Kembali</span>
            <span><kbd>Alt+S</kbd> Menu</span>
            <button type="button" class="inv-kbd-help-btn" id="inv-kbd-help-btn" title="Bantuan (F12)"><kbd>F12</kbd></button>
          </div>
          <ol class="breadcrumb float-sm-right" style="clear: both; margin-bottom: 0;">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Invoice</li>
          </ol>
        </div>
      </div>
    </div><!-- /.container-fluid -->
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-info"></i> Note:</h5>
            Halaman ini telah ditingkatkan untuk dicetak. Klik tombol cetak di bagian bawah faktur.
          </div>


          <!-- Main content -->
          <div class="invoice p-3 mb-3">
            <!-- title row -->
            <div class="row">
              <div class="col-12">
                <h4>
                  <i class="fas fa-globe"></i> No. Invoice: <?= $id; ?>
                  <small class="float-right">Tanggal: <?= $invoice['invoice_tgl']; ?></small>
                </h4>
              </div>
              <!-- /.col -->
            </div>
            <!-- info row -->
            <?php
            $toko = query("SELECT * FROM toko WHERE toko_cabang = '$sessionCabang'");
            ?>
            <?php foreach ($toko as $row) : ?>
              <?php
              $toko_nama   = $row['toko_nama'];
              $toko_kota   = $row['toko_kota'];
              $toko_tlpn   = $row['toko_tlpn'];
              $toko_wa     = $row['toko_wa'];
              $toko_email  = $row['toko_email'];
              $toko_alamat = $row['toko_alamat'];
              ?>
            <?php endforeach; ?>
            <div class="row invoice-info">
              <div class="col-sm-4 invoice-col">
                <h4><b>Dari</b></h4>
                <address>
                  <strong><?= $toko_nama; ?></strong><br>
                  <?= $toko_alamat; ?><br>
                  Tlpn/wa: <?= $toko_tlpn; ?> / <?= $toko_wa; ?><br>
                  Email: <?= $toko_email; ?><br>

                  <?php
                  $kasir = $invoice['invoice_kasir'];
                  $dataKasir = query("SELECT * FROM user WHERE user_id = $kasir");
                  ?>
                  <?php foreach ($dataKasir as $ksr) : ?>
                    <?php $ksrDetail = $ksr['user_nama']; ?>
                  <?php endforeach; ?>

                  <b>Kasir: </b><?= $ksrDetail; ?>
                </address>
              </div>
              <!-- /.col -->

              <div class="col-sm-4 invoice-col">
                <h4><b>Pembeli</b></h4>
                <address>
                  <?php
                  $customer = $invoice['invoice_customer'];
                  $dataCustomer = query("SELECT * FROM customer WHERE customer_id = $customer");
                  ?>
                  <?php foreach ($dataCustomer as $ctr) : ?>
                    <?php
                    $ctrId     = $ctr['customer_id'];
                    $ctrNama   = $ctr['customer_nama'];
                    $ctrAlamat = $ctr['customer_alamat'];
                    $ctrEmail  = $ctr['customer_email'];
                    $ctrTlpn   = $ctr['customer_tlpn'];
                    ?>
                  <?php endforeach; ?>

                  <strong><?= $ctrNama; ?></strong><br>
                  <?php
                  if ($ctrId == 1) {
                    echo "No. Invoice Marketplace: " . $invoice['invoice_marketplace'];
                  }
                  ?>

                  <?= $ctrAlamat; ?><br>
                  Tlpn/wa:
                  <?php
                  if ($ctrTlpn == null) {
                    echo "-";
                  } else {
                    echo ($ctrTlpn);
                  }
                  ?>

                  <br>
                  Email:
                  <?php
                  if ($ctrEmail == null) {
                    echo "-";
                  } else {
                    echo ($ctrEmail);
                  }
                  ?>

                  <br>
                  <b>Nama Kurir: </b>
                  <?php
                  $kurir = $invoice['invoice_kurir'];

                  if ($kurir > 0) {
                    $dataKurir = query("SELECT * FROM user WHERE user_id = $kurir")[0];
                    echo $dataKurir['user_nama'];
                  } else {
                    echo "-";
                  }

                  ?>

                  <br>
                  <b>Tipe Pembayaran: </b>
                  <?php
                  $tipeTransaksi = $invoice['invoice_tipe_transaksi'];

                  if ($tipeTransaksi > 0) {
                    echo "Transfer";
                  } else {
                    echo "Cash";
                  }
                  ?>
                </address>
              </div>

              <!-- /.col -->
              <div class="col-sm-4 invoice-col">
                <?php if ($ctrId == 1) { ?>
                  <h4><b>Ekspedisi & No. Resi</b></h4>
                  <?php
                  $ekspedisi = $invoice['invoice_ekspedisi'];

                  $ekspedisiData = mysqli_query($conn, "select ekspedisi_nama from ekspedisi where ekspedisi_id = $ekspedisi ");
                  $edRow = mysqli_fetch_array($ekspedisiData);
                  $ed = $edRow ? $edRow['ekspedisi_nama'] : '-';
                  ?>
                  Ekspedisi: <?= $ed; ?><br>
                  No. Resi: <?= $invoice['invoice_no_resi']; ?>
                <?php } ?>

              </div>
              <!-- /.col -->
            </div>
            <!-- /.row -->

            <!-- Table row -->
            <div class="row">
              <div class="col-12 table-responsive">
                <div class="table-auto">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th>No</th>
                        <th>Nama Barang</th>
                        <th>Satuan</th>
                        <th>Harga</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $invoice1 = $id;
                      $i = 1;
                      $queryProduct = $conn->query("SELECT penjualan.penjualan_id, penjualan.barang_qty, penjualan.penjualan_invoice, penjualan.barang_option_sn, penjualan.barang_sn_desc, penjualan.keranjang_harga, penjualan.keranjang_satuan, penjualan.penjualan_cabang, barang.barang_id, barang.barang_nama, satuan.satuan_id, satuan.satuan_nama
  	                             FROM penjualan 
  	                             JOIN barang ON penjualan.barang_id = barang.barang_id
                                 LEFT JOIN satuan ON penjualan.keranjang_satuan = satuan.satuan_id AND satuan.satuan_cabang = 0
  	                             WHERE penjualan_invoice = $invoice1 && penjualan_cabang = '" . $sessionCabang . "'
  	                             ORDER BY penjualan_id DESC
  	                             ");
                      while ($rowProduct = mysqli_fetch_array($queryProduct)) {
                        $satuanNama = $rowProduct['satuan_nama'] ?: satuan_nama_by_id($conn, (int) $rowProduct['keranjang_satuan']) ?: '-';
                      ?>

                        <tr>
                          <td><?= $i; ?></td>
                          <td>
                            <?= $rowProduct['barang_nama']; ?><br>
                            <?php if ($rowProduct['barang_option_sn'] > 0) { ?>
                              <small>No. SN: <?= $rowProduct['barang_sn_desc']; ?></small>
                            <?php } ?>
                          </td>
                          <td><?= htmlspecialchars($satuanNama, ENT_QUOTES, 'UTF-8'); ?></td>
                          <td><?= $rowProduct['keranjang_harga']; ?></td>
                          <td><?= $rowProduct['barang_qty']; ?></td>
                          <td>
                            <?php
                            $subTotal = $rowProduct['barang_qty'] * $rowProduct['keranjang_harga'];
                            echo ($subTotal);
                            ?>
                          </td>
                        </tr>
                        <?php $i++; ?>
                      <?php } ?>
                    </tbody>
                  </table>
                </div>
              </div>
              <!-- /.col -->
            </div>
            <!-- /.row -->

            <div class="row">
              <!-- accepted payments column -->
              <div class="col-4 col-md-6">
              </div>
              <!-- /.col -->
              <div class="col-8 col-md-6">
                <div class="table-responsive">
                  <table class="table">
                    <tr>
                      <th style="width:50%">Sub Total:</th>
                      <td>Rp. <?= number_format($invoice['invoice_total'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                      <th>Ongkir</th>
                      <td>Rp. <?= number_format($invoice['invoice_ongkir'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                      <th>Diskon</th>
                      <td>Rp. <?= number_format($invoice['invoice_diskon'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                      <th>Total</th>
                      <td>Rp. <?= number_format($invoice['invoice_sub_total'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                      <th>
                        <?php
                        $tipeTransaksi = $invoice['invoice_piutang'];
                        if ($tipeTransaksi < 1) {
                          echo "Bayar";
                        } else {
                          echo "DP";
                        }
                        ?>
                      </th>
                      <td>Rp. <?= number_format($invoice['invoice_bayar'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                      <th>
                        <?php
                        $tipeTransaksi = $invoice['invoice_piutang'];
                        if ($tipeTransaksi < 1) {
                          echo "Uang Kembali";
                        } else {
                          echo "Sisa Piutang";
                        }
                        ?>
                      </th>
                      <td>Rp. <?= number_format($invoice['invoice_kembali'], 0, ',', '.'); ?></td>
                    </tr>
                  </table>
                </div>
              </div>
              <!-- /.col -->
            </div>
            <!-- /.row -->

            <!-- this row will not appear when printing -->
            <div class="row no-print">
              <div class="col-12 inv-print-actions">
                <?php if ($invoice['invoice_tipe_transaksi'] == 1) { ?>
                  <button type="button" id="check-midtrans" class="btn btn-info" data-toggle="modal" data-target="#exampleModal">
                    Cek Pembayaran
                  </button>

                  <!-- Modal -->
                  <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="exampleModalLabel">Midtrans</h5>
                          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                          </button>
                        </div>
                        <div class="modal-body">
                          <div class="d-none">
                            <svg
                              class="container"
                              viewBox="0 0 40 40"
                              height="40"
                              width="40">
                              <circle
                                class="track"
                                cx="20"
                                cy="20"
                                r="17.5"
                                pathlength="100"
                                stroke-width="5px"
                                fill="none" />
                              <circle
                                class="car"
                                cx="20"
                                cy="20"
                                r="17.5"
                                pathlength="100"
                                stroke-width="5px"
                                fill="none" />
                            </svg>
                          </div>
                          <div id="loaders-midtrans" class="text-center bg-light d-flex justify-content-center align-items-center rounded" style="width:100%;min-height:500px;">
                            <iframe id="snap-midtrans" src="" width="100%" height="500px"></iframe>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php } ?>
                <label class="inv-print-tab-opt" for="inv-print-new-tab" title="Default: cetak dari halaman ini tanpa tab baru (hemat RAM). Centang hanya jika perlu preview di tab terpisah.">
                  <input type="checkbox" id="inv-print-new-tab">
                  Buka tab baru (preview nota)
                </label>
                <a href="nota-cetak?no=<?= $invoice['invoice_id']; ?>-invoice-<?= $id; ?>" id="btn-print-nota" class="btn btn-success" title="Print Nota (F1)"><i class="fas fa-print"></i> Print Nota <small>(F1)</small></a>
                <a href="beli-langsung?customer=<?= $backCustomerType; ?>" id="btn-kembali-transaksi" class="btn btn-default" title="Kembali Transaksi (F2)"><i class="fa fa-arrow-left"></i> Kembali Transaksi <small>(F2)</small></a>
              </div>
            </div>
          </div>
          <!-- /.invoice -->
        </div><!-- /.col -->
      </div><!-- /.row -->
    </div><!-- /.container-fluid -->
  </section>
  <!-- /.content -->
</div>

<script>
  (function() {
    var INV_PRINT_TAB_KEY = 'numart_inv_print_new_tab_v2';

    function invGetPrintNewTab() {
      var stored = localStorage.getItem(INV_PRINT_TAB_KEY);
      if (stored === null) {
        return false;
      }
      return stored === '1';
    }

    function invSyncPrintTabOption() {
      $('#inv-print-new-tab').prop('checked', invGetPrintNewTab());
    }

    function invRemovePrintIframe(iframe) {
      if (iframe && iframe.parentNode) {
        iframe.parentNode.removeChild(iframe);
      }
    }

    /** Cetak nota tanpa pindah halaman & tanpa tab baru (iframe off-screen, dibuang setelah print). */
    function invPrintViaHiddenIframe(url) {
      var sep = url.indexOf('?') >= 0 ? '&' : '?';
      var embedUrl = url + sep + 'embed=1';

      invRemovePrintIframe(document.getElementById('inv-print-iframe'));

      var iframe = document.createElement('iframe');
      iframe.id = 'inv-print-iframe';
      iframe.setAttribute('title', 'Print nota');
      // Lebar ~80mm agar driver printer termal/nota tidak menerima halaman kosong (width:0 sering blank).
      iframe.setAttribute('style', 'position:fixed;left:-10000px;top:0;width:82mm;min-width:280px;height:100vh;border:0;margin:0;padding:0;');
      iframe.setAttribute('aria-hidden', 'true');

      var cleanupTimer = null;
      var cleanup = function() {
        if (cleanupTimer) {
          clearTimeout(cleanupTimer);
          cleanupTimer = null;
        }
        invRemovePrintIframe(iframe);
      };

      iframe.onload = function() {
        var win;
        try {
          win = iframe.contentWindow;
          if (!win) {
            cleanup();
            return;
          }
          if (win.addEventListener) {
            win.addEventListener('afterprint', function() {
              setTimeout(cleanup, 400);
            }, { once: true });
          }
          // Print dipicu dari nota-cetak.php (embed=1), bukan dari parent — lebih stabil di printer termal.
          cleanupTimer = setTimeout(cleanup, 120000);
        } catch (err) {
          cleanup();
        }
      };

      document.body.appendChild(iframe);
      iframe.src = embedUrl;
    }

    function invPrintNota(forceNewTab) {
      var $btn = $('#btn-print-nota');
      if (!$btn.length) {
        return;
      }
      var url = $btn.attr('href');
      var newTab = (typeof forceNewTab === 'boolean') ? forceNewTab : invGetPrintNewTab();
      if (newTab) {
        window.open(url, '_blank');
      } else {
        invPrintViaHiddenIframe(url);
      }
    }

    function invKembaliTransaksi() {
      var $btn = $('#btn-kembali-transaksi');
      if ($btn.length) {
        window.location.href = $btn.attr('href');
      }
    }

    function invShowHelp() {
      alert(
        'PINTASAN KEYBOARD — INVOICE\n\n' +
        'F1       — Print nota (tetap di halaman invoice, tanpa tab baru)\n' +
        'Shift+F1 — Print nota di tab baru (preview)\n' +
        'F2       — Kembali ke transaksi kasir\n' +
        'F11      — Tutup modal\n' +
        'F12      — Bantuan ini\n' +
        'Alt+S    — Tampilkan / sembunyikan sidebar menu\n' +
        'Ctrl+F5  — Refresh halaman\n\n' +
        'Default: dialog print muncul, halaman invoice tidak pindah & tidak ada tab nota-cetak.\n' +
        'Centang "Buka tab baru" hanya jika perlu melihat preview nota di tab terpisah.'
      );
    }

    $(document).ready(function() {
      invSyncPrintTabOption();

      $('#inv-print-new-tab').on('change', function() {
        localStorage.setItem(INV_PRINT_TAB_KEY, this.checked ? '1' : '0');
      });

      $('#btn-print-nota').on('click', function(e) {
        e.preventDefault();
        invPrintNota();
      });
    });

    $(document).on('keydown', function(e) {
      if (e.ctrlKey && e.keyCode === 116) {
        return;
      }

      if ($('.modal.show').length && e.keyCode === 122) {
        e.preventDefault();
        $('.modal.show').modal('hide');
        return;
      }

      if (e.keyCode === 112 && e.shiftKey) {
        e.preventDefault();
        invPrintNota(!invGetPrintNewTab());
        return;
      }

      if (e.keyCode === 112 && !e.shiftKey && !e.ctrlKey && !e.altKey) {
        e.preventDefault();
        invPrintNota();
        return;
      }

      if (e.keyCode === 113) {
        e.preventDefault();
        invKembaliTransaksi();
        return;
      }

      if (e.keyCode === 123) {
        e.preventDefault();
        invShowHelp();
        return;
      }
    });

    $('#inv-kbd-help-btn').on('click', invShowHelp);
  })();

  $(document).ready(function() {
    $('#check-midtrans').click(function() {
      $.ajax({
        type: 'get',
        url: 'https://api.numartmagelang.com/api/midtrans/payment/check?order_id=' + '<?= $id; ?>',
        data: {
          id: '<?= $id ?>'
        },
        dataType: 'json',
        success: function(response) {
          // if (response?.code == 2) {
          $('#snap-midtrans').attr('src', response?.data?.snap?.redirect_url);
          // }

        }
      })
      // var url = "<?= base64_decode($id); ?>";
    })
  })
</script>

<?php include '_footer.php'; ?>