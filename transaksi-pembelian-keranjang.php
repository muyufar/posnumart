<?php 
  error_reporting(0);
  include '_header-artibut.php';

  // Ambil data berdasarkan tipe cash atau hutang
  $r = isset($_GET['r']) ? $_GET['r'] : '';
  $poId = (int) ($_GET['po'] ?? 0);
  $preSupplierId = (int) ($_GET['supplier'] ?? 0);
?>

	<?php  
      $userId = $_SESSION['user_id'];
      // Isi harga 0 dari transaksi pembelian terakhir (bukan HPP rata-rata)
      keranjang_pembelian_isi_harga_dari_terakhir($conn, $userId, $sessionCabang);
      $keranjang = query("SELECT * FROM keranjang_pembelian WHERE keranjang_id_kasir = $userId && keranjang_cabang = $sessionCabang ORDER BY keranjang_id ASC");

      $pembelian = mysqli_query($conn,"select * from invoice_pembelian");
      $jmlPembelian = mysqli_num_rows($pembelian);
      $jmlPembelian1 = $jmlPembelian + 1;
    ?>
    <?php  
        $today = date("Ymd");
        $di = $today.$jmlPembelian1;
    ?>

    <!-- Mencari Nilai no Invoice -->
    <?php  
        $userLogin = $_SESSION['user_id'];
        $invoiceNumber = mysqli_query($conn, "select invoice_pembelian_number_id, invoice_pembelian_number_input, invoice_pembelian_number_delete from invoice_pembelian_number where invoice_pembelian_number_parent = ".$di." && invoice_pembelian_number_user = ".$userLogin." && invoice_pembelian_cabang = ".$sessionCabang." ");
        $inParent = mysqli_fetch_array($invoiceNumber);
        $inId     = $inParent['invoice_pembelian_number_id'] ?? 0;
        $in       = $inParent['invoice_pembelian_number_input'] ?? null;
        $inDelete = $inParent['invoice_pembelian_number_delete'] ?? '';
        
        if ( $in == null ) {
          $in = 0;
        } else {
          $in = $in;
        }
    ?>
    <!-- End Mencari Nilai no Invoice -->
   
      <div class="card">
        <div class="card-header">
           <div class="row">
              <div class="col-md-8 col-lg-8">
                <div class="card-invoice">
                 <span>No. Invoice: </span>
                  <input type="" value="<?= $in; ?>" readonly="" style="border: 1px solid #eaeaea;">

                <?php if ( $in == null ) { ?>
                  <span class="" name="" data-toggle="modal" data-target="#modal-tambah-invoice">
                        <i class="fa fa-pencil" style="color: green; cursor: pointer;"></i>
                  </span>
                <?php } ?>

                <?php if ( $in != null ) { ?>
                  <span class="" name="" id="invoice_edit" data-id="<?= $inId; ?>">
                        <i class="fa fa-edit" style="color: blue; cursor: pointer;"></i>
                  </span>
                <?php } ?>

                 </div>
                </div>
                <div class="col-md-4 col-lg-4">
                  <div class="cari-barang-parent">
                    <div class="row align-items-center">
                        <div class="col-7 col-md-6">
                            <form action="" method="post">
                                <input type="hidden" name="keranjang_id_kasir" value="<?= $_SESSION['user_id']; ?>">
                                <input type="hidden" name="keranjang_cabang" value="<?= $sessionCabang; ?>">
                                <input type="text" class="form-control" autofocus="" name="inputbarcode" placeholder="Barcode / Kode Barang" required="">
                            </form>
                        </div>
                        <div class="col-5 col-md-6 text-right">
                            <a class="btn btn-primary btn-sm" title="Cari Produk" data-toggle="modal" id="cari-barang" href='#modal-id'>
                               <i class="fa fa-search"></i>
                            </a>
                            <?php
                              $retParts = [];
                              if ($r !== '') {
                                  $retParts[] = 'r=' . rawurlencode((string) $r);
                              }
                              if ($poId > 0) {
                                  $retParts[] = 'po=' . $poId;
                              }
                              if ($preSupplierId > 0) {
                                  $retParts[] = 'supplier=' . $preSupplierId;
                              }
                              $returnUrl = 'transaksi-pembelian' . ($retParts !== [] ? ('?' . implode('&', $retParts)) : '');
                              $ksHint = '';
                              if ($poId > 0) {
                                  require_once __DIR__ . '/aksi/pengadaan-po-lib.php';
                                  if (function_exists('pengadaan_po_get')) {
                                      $poTmp = @pengadaan_po_get($conn, $poId);
                                      $ksHint = (string) ($poTmp['kode_suplier'] ?? '');
                                  }
                              }
                              $tambahHref = 'barang-add?return=' . rawurlencode($returnUrl);
                              if ($ksHint !== '') {
                                  $tambahHref .= '&kode_suplier=' . rawurlencode($ksHint);
                              }
                            ?>
                            <a class="btn btn-success btn-sm" title="Tambah barang baru ke master (belum ada di sistem)"
                               href="<?= htmlspecialchars($tambahHref, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                               <i class="fa fa-plus"></i> Tambah Barang
                            </a>
                        </div>
                      </div>
                  </div>
                </div>
              </div>
          </div>

			     <div class="card-body">
              <div class="table-auto">
                <table id="keranjang-pembelian-table" class="table table-bordered table-striped">
                <thead>
                <tr>
                  <th style="width: 6%;">No.</th>
                  <th>Nama</th>
                  <th>Harga Beli</th>
                  <th style="text-align: center;">QTY</th>
                  <th style="width: 20%;">Sub Total</th>
                  <th style="text-align: center;">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php 
                  $i=1; 
                  $total = 0;
                ?>
                <?php 
                  foreach($keranjang as $row) : 

                  $bik = $row['barang_id'];
                  $stockParent = mysqli_query( $conn, "select barang_stock from barang where barang_id = '".$bik."'");
                  $brg = mysqli_fetch_array($stockParent); 
                  $tb_brg = $brg['barang_stock'];
                  if (isset($row['keranjang_harga']) && (float)$row['keranjang_harga'] <= 0) {
                      $hargaInput = barang_get_harga_beli_untuk_input($conn, (int) $bik);
                      if ($hargaInput > 0) {
                          $row['keranjang_harga'] = $hargaInput;
                      }
                  }
                 $sub_total = (float)$row['keranjang_harga'] * (float)$row['keranjang_qty'];
        
                  if ( $row['keranjang_id_kasir'] == $_SESSION['user_id'] ) {
                  $total += $sub_total;
                ?>
               <tr class="row-keranjang-pembelian" data-qty="<?= (int)$row['keranjang_qty']; ?>" data-harga="<?= (float)$row['keranjang_harga']; ?>">
    <td><?= $i; ?></td>
    <td><?= $row['keranjang_nama']; ?></td>
    <td style="text-align: center; width: 14%;">
        <form role="form" action="transaksi-pembelian.php<?= isset($r) && $r !== '' ? '?r=' . $r : ''; ?>" method="post" class="form-update-harga">
            <input type="hidden" name="update_harga" value="1">
            <input type="hidden" name="r" value="<?= isset($r) ? htmlspecialchars($r) : ''; ?>">
            <input type="hidden" name="keranjang_id" value="<?= $row['keranjang_id']; ?>">
            <input type="number" min="0" step="0.1" name="keranjang_harga" class="input-harga-beli" value="<?= number_format((float)$row['keranjang_harga'], 1, '.', ''); ?>" style="text-align: center; width: 65%;">
            <button class="btn btn-primary" type="submit" name="update_harga_btn" title="Update Harga">
                <i class="fa fa-refresh"></i>
            </button>
        </form>
    </td>
    <td style="text-align: center; width: 11%;">
        <form role="form" action="transaksi-pembelian.php<?= isset($r) && $r !== '' ? '?r=' . $r : ''; ?>" method="post" class="form-update-qty">
            <input type="hidden" name="keranjang_id" value="<?= $row['keranjang_id']; ?>">
            <input type="number" min="0.1" step="0.1" name="keranjang_qty" class="input-qty-pembelian" value="<?= number_format((float)$row['keranjang_qty'], 1, '.', ''); ?>" style="text-align: center; width: 60%;">
            <button class="btn btn-primary" type="submit" name="update" title="Update QTY">
                <i class="fa fa-refresh"></i>
            </button>
        </form>
    </td>
    <td class="row-subtotal">Rp. <?= number_format($sub_total, 1, ',', '.'); ?></td>
    <td style="text-align: center; width: 6%;">
        <a href="transaksi-pembelian-delete?id=<?= $row['keranjang_id']; ?>&r=<?= $r; ?>" title="Delete Data" onclick="return confirm('Yakin dihapus ?')">
            <button class="btn btn-danger" type="submit" name="hapus">
                <i class="fa fa-trash-o"></i>
            </button>
        </a>
    </td>
</tr>

                <?php $i++; ?>
                <?php } ?>
                <?php endforeach; ?>
              </table>
            </div>
              
       
            <div class="btn-transaksi">
                <form role="form" action="transaksi-pembelian.php" method="POST">
                  <div class="row">
                    <div class="col-md-6 col-lg-7">
                        <div class="filter-customer">
                          
                          <div class="form-group">
                            <label>Supplier <small class="text-muted">(cari kode / nama / perusahaan)</small></label>
                            <select class="form-control select2bs4" required="" name="invoice_supplier">
                              <option selected="selected" value="">-- Pilih Supplier --</option>
                              <?php  
                                supplier_ensure_kode_column($conn);
                                $supplier = query("SELECT * FROM supplier WHERE supplier_cabang = $sessionCabang && supplier_status = '1' ORDER BY supplier_id DESC ");
                              ?>
                              <?php foreach ( $supplier as $ctr ) :
                                $kodeSuplier = trim((string) ($ctr['kode_suplier'] ?? ''));
                                $labelSuplier = $ctr['supplier_nama'] . ' - ' . $ctr['supplier_company'];
                                if ($kodeSuplier !== '') {
                                  $labelSuplier = $kodeSuplier . ' — ' . $labelSuplier;
                                }
                              ?>
                                <option value="<?= $ctr['supplier_id'] ?>" <?= ($preSupplierId > 0 && (int) $ctr['supplier_id'] === $preSupplierId) ? 'selected' : ''; ?>>
                                	<?= htmlspecialchars($labelSuplier, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                            <small>
                              <a href="supplier-add">Tambah Supplier <i class="fa fa-plus"></i></a>
                            </small>
                          </div>

                          <!-- kondisi jika memilih hutang -->
                          <?php if ( $r == 1 ) : ?>
                          <div class="form-group">
                              <label style="color: red;">Jatuh Tempo</label>
                              <input type="date" name="invoice_hutang_jatuh_tempo" class="form-control" raquired value="<?= date("Y-m-d"); ?>">
                          </div>
                         <?php else : ?>
                            <input type="hidden" name="invoice_hutang_jatuh_tempo" value="0">
                         <?php endif; ?>
                      </div>
                    </div>
                    <div class="col-md-6 col-lg-5">
                      <div class="invoice-table">
                        <table class="table">
                          <tr>
                              <td><b>Total</b></td>
                              <td class="table-nominal">
                                 <!-- Rp. <?php echo number_format($total, 0, ',', '.'); ?> -->
                                 <span>Rp. </span>
                                 <span>
                                    <input type="text" name="invoice_total" id="angka2" class="b2" onkeyup="hitung2();" value="<?= number_format($total, 1, '.', ''); ?>" onkeyup="return isNumberKey(event)" size="10" readonly>
                                 </span>
                                 
                              </td>
                          </tr>
                          <tr>
                              <td>
                                  <b style="color: red;">
                                      <?php  
                                        // kondisi jika memilih hutang
                                        if ( $r == 1 ) {
                                          echo "DP";
                                        } else {
                                          echo "Bayar";
                                        }
                                      ?>      
                                  </b>
                              </td>
                              <td class="table-nominal tn">
                                 <span>Rp.</span> 
                                 <span>
                                   <input type="text" name="angka1" id="angka1" class="a2" autocomplete="off" onkeyup="hitung2();" required="" onkeyup="return isNumberKey(event)" onkeypress="return hanyaAngka1(event)" size="10">
                                 </span>
                              </td>
                          </tr>
                          <tr>
                              <td>
                                  <?php  
                                    // kondisi jika memilih hutang
                                    if ( $r == 1 ) {
                                        echo "Sisa Hutang";
                                    } else {
                                        echo "Kembali";
                                    }
                                  ?>  
                              </td>
                              <td class="table-nominal">
                                <span>Rp.</span>
                                 <span>
                                  <input type="text" name="hasil" id="hasil" class="c2" readonly size="10" disabled>
                                </span>
                              </td>
                          </tr>
                          <tr>
                              <td></td>
                              <td>

                                <?php 
                                    foreach ($keranjang as $stk) : 
                                    if ( $stk['keranjang_id_kasir'] == $_SESSION['user_id'] ) {
                                ?>
                                  <input type="hidden" name="barang_ids[]" value="<?= $stk['barang_id']; ?>">
                                  <input type="hidden" min="1" name="keranjang_qty[]" value="<?= $stk['keranjang_qty'] ?>"> 
                                  <input type="hidden" name="keranjang_id_kasir[]" value="<?= $_SESSION['user_id']; ?>">

                                  <input type="hidden" name="kik" value="<?= $_SESSION['user_id']; ?>">
                                  <input type="hidden" name="pembelian_invoice[]" value="<?= $in; ?>">
                                  <input type="hidden" name="pembelian_invoice_parent[]" value="<?= $inDelete; ?>">
                                  <input type="hidden" name="pembelian_date[]" value="<?= date("Y-m-d") ?>">
                                  <input type="hidden" name="barang_harga_beli[]" value="<?= number_format((float)$stk['keranjang_harga'], 1, '.', ''); ?>">
                                  <input type="hidden" name="pembelian_cabang[]" value="<?= $sessionCabang; ?>">
                                <?php } ?>
                                <?php endforeach; ?>  
                                <input type="hidden" name="pembelian_invoice2" value="<?= $in; ?>">
                                <input type="hidden" name="invoice_pembelian_number_delete" value="<?= $inDelete; ?>">
                                <input type="hidden" name="pembelian_invoice_parent2" value="<?= $inDelete; ?>">
                                <input type="hidden" name="invoice_hutang" value="<?= $r; ?>">
                                <input type="hidden" name="invoice_hutang_lunas" value="0">
                                <input type="hidden" name="invoice_pembelian_cabang" value="<?= $sessionCabang; ?>">
                                <?php if ($poId > 0) : ?>
                                <input type="hidden" name="pengadaan_po_id" value="<?= $poId; ?>">
                                <?php endif; ?>
                                <div class="payment">
                                  <?php  
                                  	 $idKasir = $_SESSION['user_id'];
                                  	 $keranjang = mysqli_query($conn,"select keranjang_harga from keranjang_pembelian where keranjang_harga < 1 && keranjang_id_kasir = ".$idKasir." && keranjang_cabang = ".$sessionCabang." ");
    								                  $jmlKeranjang = mysqli_num_rows($keranjang);
                                  ?>

                                <?php if ( $in != null ) { ?>
                                  <?php if ( $jmlKeranjang < 1 ) { ?>
                                  <button class="btn btn-primary" type="submit" name="updateStock">Simpan Pembelian <i class="fa fa-shopping-cart"></i></button>
                                  <?php } ?>

                                  <?php if ( $jmlKeranjang > 0 ) { ?>
                                  <a class="btn btn-default btn-disabled" type="" name="">Simpan Pembelian<i class="fa fa-shopping-cart"></i></a>
                                  <?php } ?>
                                <?php } ?>

                                <?php if ( $in == null ) { ?>
                                  <a class="btn btn-default" type="" name="" disabled>Simpan Pembelian <i class="fa fa-shopping-cart"></i></a>
                                <?php } ?>

                                </div>
                              </td>
                          </tr>
                        </table>
                      </div>
                    </div>
                  </div>
               </form>
              </div>
            </div>
          </div>

        

<script>
  $(function () {
    $("#keranjang-pembelian-table").DataTable();
  });
</script>

<script>
  $(function () {

    //Initialize Select2 Elements
    $('.select2bs4').select2({
      theme: 'bootstrap4',
      placeholder: 'Ketik kode supplier, nama, atau perusahaan',
      allowClear: true
    })
  });

  $(document).ready(function(){
  	 $('.btn-disabled').click(function(){
	  	alert("Harga Beli Masih ada yang bernilai kosong (Rp.0) !! Segera Update Harga Pembelian Barang per Produk ..");
	  });

    // Hitung ulang subtotal per baris dan total (decimal 11,1)
    function formatRp(num) {
      var n = parseFloat(num);
      if (isNaN(n)) n = 0;
      return 'Rp. ' + n.toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    }
    function updateRowSubtotal($row) {
      var qty = parseFloat($row.find('.input-qty-pembelian').val()) || 0;
      var harga = parseFloat($row.find('.input-harga-beli').val()) || 0;
      var sub = Math.round(qty * harga * 10) / 10;
      $row.find('.row-subtotal').text(formatRp(sub));
      updateGrandTotal();
    }
    function updateGrandTotal() {
      var total = 0;
      $('#keranjang-pembelian-table tbody tr.row-keranjang-pembelian').each(function() {
        var qty = parseFloat($(this).find('.input-qty-pembelian').val()) || 0;
        var harga = parseFloat($(this).find('.input-harga-beli').val()) || 0;
        total += qty * harga;
      });
      total = Math.round(total * 10) / 10;
      $('#angka2').val(total.toFixed(1));
      if (typeof hitung2 === 'function') hitung2();
    }
    $('#keranjang-pembelian-table').on('keyup change', '.input-qty-pembelian, .input-harga-beli', function() {
      var $row = $(this).closest('tr');
      updateRowSubtotal($row);
    });
  });
</script>

