<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>
<?php  
  if ( $levelLogin === "kasir" && $levelLogin === "kurir" ) {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }  
?>
<?php  

// cek apakah tombol submit sudah ditekan atau belum
if (isset($_POST['submit'])) {
    if (tambahBarang($_POST, $_FILES) > 0) {
        echo "<script>document.location.href='barang';</script>";
        exit;
    }
    if (!empty($_SESSION['barang_gambar_error'])) {
        echo "<script>alert(" . json_encode((string) $_SESSION['barang_gambar_error'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) . ");</script>";
        unset($_SESSION['barang_gambar_error']);
    } else {
        echo "<script>alert('Data gagal ditambahkan');</script>";
    }
}
?>
  
  <?php 
      $barang = mysqli_query($conn,"select * from barang where barang_cabang = ".$sessionCabang." ");
      $jmlBarang = mysqli_num_rows($barang); 

      if ( $jmlBarang < 1 ) {
          $barangCount = 1;
      } else {
          $barangCount = query("SELECT * FROM barang ORDER BY barang_id DESC LIMIT 1")[0];
          $barangCount = $barangCount['barang_kode_count'];
          $barangCount += 1;
          
      }
  ?>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Tambah Data Barang</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Data Barang</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content">
      <div class="container-fluid">
        <form role="form" action="" method="post" enctype="multipart/form-data">
        <div class="row">
          <!-- left column -->
            <div class="col-md-12">
              <!-- general form elements -->
              <div class="card card-primary">
                <div class="card-header">
                  <h3 class="card-title">Data Barang</h3>
                </div>
                <!-- /.card-header -->
                <!-- form start -->
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                            <label for="barang_kode">Barcode / Kode Barang</label>
                            <input type="text" name="barang_kode" class="form-control" id="barang_kode" placeholder="Contoh: 878868889080" required autofocus="">
                            <small style="color: red">
                              <b>
                                Barcode / Kode Barang Sifatnya Sekali Input & Pastikan Tidak Terjadi Kesalahan
                              </b>
                            </small>
                        </div>
                      </div>
                      <div class="col-md-6 col-lg-6"></div>
                      <div class="col-md-6 col-lg-6">
                        <input type="hidden" name="barang_cabang" value="<?= $sessionCabang; ?>">
                        <input type="hidden" name="barang_kode_count" value="<?= $barangCount; ?>">
                          <div class="form-group">
                              <label for="barang_nama">Nama Barang</label>
                              <input type="text" name="barang_nama" class="form-control" id="barang_nama" placeholder="Input Nama Barang" required>
                          </div>
                           <div class="form-group">
                            <label for="kode_suplier">Kode Suplier</label>
                            <input type="text" name="kode_suplier" class="form-control" id="kode_suplier"placeholder="Input Kode Suplier" required>
                          </div>
                          <div class="form-group">
                              <label for="barang_deskripsi">Deskripsi</label>
                              <textarea name="barang_deskripsi" id="barang_deskripsi" class="form-control" rows="5" required="required" placeholder="Deskripsi Lengkap"></textarea>
                          </div>
                          <?php
                          require_once __DIR__ . '/aksi/barang-gambar-lib.php';
                          barang_gambar_ensure_column($conn);
                          $barangGambarCurrent = '';
                          $barangGambarReadonly = false;
                          include __DIR__ . '/_barang-gambar-form.php';
                          ?>
                          <div class="form-group ">
                              <label for="kategori_id" class="">Kategori</label>
                              <div class="">
                                <?php $data = query("SELECT * FROM kategori WHERE kategori_cabang = $sessionCabang ORDER BY kategori_id DESC"); ?>
                                <select name="kategori_id" required="" class="form-control ">
                                    <option value="">--Pilih Kategori--</option>
                                    <?php foreach ( $data as $row ) : ?>
                                      <?php if ( $row['kategori_status'] === '1' ) { ?>
                                        <option value="<?= $row['kategori_id']; ?>">
                                          <?= $row['kategori_nama']; ?> 
                                        </option>
                                      <?php } ?>
                                    <?php endforeach; ?>
                                </select>
                              </div>
                          </div>
                      </div>

                      <div class="col-md-6 col-lg-6">
                          <div class="form-group ">
                              <label for="barang_option_sn">Non-SN or SN</label>
                              <div class="">
                                  <select name="barang_option_sn" required="" id="barang_option_sn" class="form-control stock-pilihan" onchange="myFunction()">
                                      <option value="">-- Pilih --</option>
                                          <option value="0">Non-SN</option>
                                          <option value="1">SN</option>
                                    </select>
                                </div>
                                <small style="color: red">
                                    <b>
                                        SN (Serial Number) Hanya dikhususkan Untuk Produk yang memiliki No. SN Seperti Handphone & Laptop 
                                    </b>
                                </small>
                          </div>
                          <div class="form-group">
                            <label for="barang_stock">Stock</label>
                            <input type="number" name="barang_stock" class="form-control" id="barang_stock" placeholder="Input Jumlah Stock" value="0" required>
                          </div>
                          <div class="form-group ">
                              <label for="barang_option_konsi">Barang Konsi (Titipan)</label>
                              <div class="">
                                  <select name="barang_option_konsi" required="" id="barang_option_konsi" class="form-control stock-pilihan" onchange="myFunctionKonsi()">
                                      <option value="">-- Pilih --</option>
                                          <option value="0">Non Konsi</option>
                                          <option value="1">Konsi</option>
                                    </select>
                                </div>
                          </div>
                     </div>
                    </div>
                  </div>
              </div>

              <div class="card card-default">
                <div class="card-header">
                  <h3 class="card-title">Data Satuan</h3>
                </div>
                <!-- /.card-header -->
                <!-- form start -->
                  <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-lg-6">
                          <div class="form-group ">
                              <label for="satuan_id">Satuan 1 (Utama)</label>
                              <div class="">
                                <?php $data2 = query("SELECT * FROM satuan WHERE satuan_cabang = 0 ORDER BY satuan_id DESC"); ?>
                                  <select name="satuan_id" required="" class="form-control ">
                                    <option value="">-- Satuan --</option>
                                    <?php foreach ( $data2 as $row ) : ?>
                                      <?php if ( $row['satuan_status'] === '1' ) { ?>
                                        <option value="<?= $row['satuan_id']; ?>">
                                          <?= $row['satuan_nama']; ?>
                                        </option>
                                      <?php } ?>
                                    <?php endforeach; ?>
                                  </select>
                              </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-6"></div>

                        <div class="col-md-6 col-lg-6">
                          <div class="form-group ">
                              <label for="satuan_id">Satuan 2</label>
                              <div class="">
                                <?php $data2 = query("SELECT * FROM satuan WHERE satuan_cabang = 0 ORDER BY satuan_id DESC"); ?>
                                  <select name="satuan_id_2" class="form-control tipe-non-sn-or-sn satuan_id_2">
                                    <option value="">-- Satuan --</option>
                                    <?php foreach ( $data2 as $row ) : ?>
                                      <?php if ( $row['satuan_status'] === '1' ) { ?>
                                        <option value="<?= $row['satuan_id']; ?>">
                                          <?= $row['satuan_nama']; ?>
                                        </option>
                                      <?php } ?>
                                    <?php endforeach; ?>
                                  </select>
                              </div>
                              <div class="checkbox">
                                <label>
                                  <input type="checkbox" value="" class="checkbox-satuan-2" name="checkbox-satuan-2">
                                  <small style="color: red">
                                    Aktifkan Checklist Agar <b>Harga Satuan 2 Aktif</b>
                                  </small>
                                </label>
                              </div>
                          </div>
                        </div>
                        <div class="col-md-6 col-lg-6">
                          <div class="form-group">
                            <label for="barang_nama">Isi</label>
                            <input type="number" name="satuan_isi_2" class="form-control tipe-non-sn-or-sn satuan_id_2" id="barang_nama" placeholder="Konversi dari satuan utama">
                          </div>
                        </div>

                        <div class="col-md-6 col-lg-6">
                          <div class="form-group ">
                              <label for="satuan_id">Satuan 3</label>
                              <div class="">
                                <?php $data2 = query("SELECT * FROM satuan WHERE satuan_cabang = 0 ORDER BY satuan_id DESC"); ?>
                                  <select name="satuan_id_3" class="form-control tipe-non-sn-or-sn satuan_id_3">
                                    <option value="">-- Satuan --</option>
                                    <?php foreach ( $data2 as $row ) : ?>
                                      <?php if ( $row['satuan_status'] === '1' ) { ?>
                                        <option value="<?= $row['satuan_id']; ?>">
                                          <?= $row['satuan_nama']; ?>
                                        </option>
                                      <?php } ?>
                                    <?php endforeach; ?>
                                  </select>
                              </div>
                              <div class="checkbox">
                                <label>
                                  <input type="checkbox" value="" class="checkbox-satuan-3" name="checkbox-satuan-3">
                                  <small style="color: red">
                                    Aktifkan Checklist Agar <b>Harga Satuan 3 Aktif</b>
                                  </small>
                                </label>
                              </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-6">
                          <div class="form-group">
                            <label for="barang_nama">Isi</label>
                            <input type="number" name="satuan_isi_3" class="form-control tipe-non-sn-or-sn satuan_id_3" id="barang_nama" placeholder="Konversi dari satuan utama">
                          </div>
                        </div>

                        <div class="col-md-6 col-lg-6">
                          <div class="form-group ">
                              <label for="satuan_id">Satuan 4</label>
                              <div class="">
                                <?php $data2 = query("SELECT * FROM satuan WHERE satuan_cabang = 0 ORDER BY satuan_id DESC"); ?>
                                  <select name="satuan_id_4" class="form-control tipe-non-sn-or-sn satuan_id_4">
                                    <option value="">-- Satuan --</option>
                                    <?php foreach ( $data2 as $row ) : ?>
                                      <?php if ( $row['satuan_status'] === '1' ) { ?>
                                        <option value="<?= $row['satuan_id']; ?>">
                                          <?= $row['satuan_nama']; ?>
                                        </option>
                                      <?php } ?>
                                    <?php endforeach; ?>
                                  </select>
                              </div>
                              <div class="checkbox">
                                <label>
                                  <input type="checkbox" value="" class="checkbox-satuan-4" name="checkbox-satuan-4">
                                  <small style="color: red">
                                    Aktifkan Checklist Agar <b>Harga Satuan 4 Aktif</b>
                                  </small>
                                </label>
                              </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-6">
                          <div class="form-group">
                            <label for="barang_nama">Isi</label>
                            <input type="number" name="satuan_isi_4" class="form-control tipe-non-sn-or-sn satuan_id_4" id="barang_nama" placeholder="Konversi dari satuan utama">
                          </div>
                        </div>

                    </div>
                  </div>
                  <!-- /.card-body -->
              </div>

              <div class="card card-default">
                <div class="card-header">
                  <h3 class="card-title">Data Harga</h3>
                </div>
                
  
                <!-- /.card-header -->
                <!-- form start -->
                  <div class="card-body">
                      
                      <!--<div class="card-body">-->
              <div class="col-md-6 col-lg-6">
              <div class="form-group">
                <label for="barang_harga_beli">Harga Beli (HPP awal)</label> 
                <input type="text" name="barang_harga_beli" class="form-control" id="barang_harga_beli" placeholder="Harga pokok penjualan awal" value="0" required="">
                <small class="text-muted">Harga beli terakhir diisi otomatis saat transaksi pembelian.</small>
              </div>
              
                <div class="form-group">
                    <label for="barang_harga_retail">Presentase Harga Retail (%)</label> 
                    <input type="number" name="barang_harga_retail" class="form-control" id="barang_harga_retail" placeholder="Input Harga Retail" value="0" required 
                           min="0" max="99" maxlength="2" oninput="if(this.value.length > 3) this.value = this.value.slice(0,3);">
                </div>
            
                <div class="form-group">
                    <label for="barang_harga_grosir">Presentase Harga Grosir (%)</label> 
                    <input type="number" name="barang_harga_grosir" class="form-control" id="barang_harga_grosir" placeholder="Input Harga Grosir" value="0" required 
                           min="0" max="99" maxlength="2" oninput="if(this.value.length > 3) this.value = this.value.slice(0,3);">
                </div>
              </div>
              <!--</div>-->
              
                    <div class="table-auto">
                      <table class="table table-bordered">
                          <thead>
                            <tr>
                                <th>Level Harga</th>
                                <th>Satuan 1</th>
                                <th>Satuan 2</th>
                                <th>Satuan 3</th>
                                <th>Satuan 4</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                                <!--onkeypress="return hanyaAngka(event)"-->
                                <th>Harga Umum</th>
                                <td>
                                  <input type="text" name="barang_harga" class="form-control" id="barang_harga" placeholder="Input Harga Barang" value="0" oninput="updateGrosir()" required="">
                                </td>
                                <td>
                                  <input type="text" name="barang_harga_s2" class="form-control harga-satuan-2" id="barang_harga_s2" placeholder="Input Harga Barang" value="0" readonly>
                                </td>
                                <td>
                                  <input type="text" name="barang_harga_s3" class="form-control harga-satuan-3" id="barang_harga_s3" placeholder="Input Harga Barang" value="0" readonly>
                                   <!--<input type="text" name="barang_harga_s3" class="form-control harga-satuan-3" id="barang_harga_s3" placeholder="Input Harga Barang" value="0" onkeypress="return hanyaAngka(event)" readonly>-->
                                </td>
                                <td>
                                  <input type="text" name="barang_harga_s4" class="form-control harga-satuan-4" id="barang_harga_s4" placeholder="Input Harga Barang" value="0" readonly>
                                   <!--<input type="text" name="barang_harga_s3" class="form-control harga-satuan-3" id="barang_harga_s3" placeholder="Input Harga Barang" value="0" onkeypress="return hanyaAngka(event)" readonly>-->
                                </td>
                            </tr>
                            <tr>
                                <th>Harga Retail</th>
                                <td>
                                  <input type="text" name="barang_harga_grosir_1" class="form-control" id="barang_harga_grosir_1" placeholder="Input Harga Barang" value="0" >
                                </td>
                                <td>
                                  <input type="text" name="barang_harga_grosir_1_s2" class="form-control harga-satuan-2" id="barang_harga_grosir_1_s2" placeholder="Input Harga Barang" value="0" readonly>
                                </td>
                                <td>
                                  <input type="text" name="barang_harga_grosir_1_s3" class="form-control harga-satuan-3" id="barang_harga_grosir_1_s3" placeholder="Input Harga Barang" value="0" readonly>
                                </td>
                                <td>
                                  <input type="text" name="barang_harga_grosir_1_s4" class="form-control harga-satuan-4" id="barang_harga_grosir_1_s4" placeholder="Input Harga Barang" value="0" readonly>
                                </td>
                            </tr>
                            <tr>
                                <th>Harga Grosir</th>
                                <td>
                                  <input type="text" name="barang_harga_grosir_2" class="form-control" id="barang_harga_grosir_2" placeholder="Input Harga Barang" value="0" >
                                </td>
                                <td>
                                  <input type="text" name="barang_harga_grosir_2_s2" class="form-control harga-satuan-2" id="barang_harga_grosir_2_s2" placeholder="Input Harga Barang" value="0" readonly>
                                </td>
                                <td>
                                  <input type="text" name="barang_harga_grosir_2_s3" class="form-control harga-satuan-3" id="barang_harga_grosir_2_s3" placeholder="Input Harga Barang" value="0" readonly>
                                </td>
                                <td>
                                  <input type="text" name="barang_harga_grosir_2_s4" class="form-control harga-satuan-4" id="barang_harga_grosir_2_s4" placeholder="Input Harga Barang" value="0" readonly>
                                </td>
                            </tr>
                        
                          </tbody>
                      </table>    
                    </div>
                  </div>
                  <!-- /.card-body -->

                  <div class="card-footer text-right">
                    <button type="submit" name="submit" class="btn btn-primary">Submit</button>
                  </div>
              </div>
            </div>
        </div>
        </form>
      </div>
    </section>


  </div>


<?php include '_footer.php'; ?>
<script>
    function hanyaAngka(evt) {
      var charCode = (evt.which) ? evt.which : event.keyCode
       if (charCode > 31 && (charCode < 48 || charCode > 57))
 
        return false;
      return true;
    }


    function myFunction() {
      var x = document.getElementById("barang_option_sn").value;
      if ( x === "0" ) {
        $(".tipe-non-sn-or-sn").removeAttr("readonly");
        $(".checkbox").css("display", "block");

      } else if ( x === "1" ) {
        $(".tipe-non-sn-or-sn").attr("readonly", true);
        $(".checkbox").css("display", "none");

      } else {
        $(".tipe-non-sn-or-sn").removeAttr("readonly");
        $(".checkbox").css("display", "block");
      }
    }
    
    function myFunctionKonsi() {
      var x = document.getElementById("barang_option_konsi").value;
      if ( x === "0" ) {
        $(".tipe-non-sn-or-sn").removeAttr("readonly");
        $(".checkbox").css("display", "block");

      } else if ( x === "1" ) {
        $(".tipe-non-sn-or-sn").attr("readonly", true);
        $(".checkbox").css("display", "none");

      } else {
        $(".tipe-non-sn-or-sn").removeAttr("readonly");
        $(".checkbox").css("display", "block");
      }
    }

    $('.checkbox-satuan-2').change(function() {
        // this will contain a reference to the checkbox   
        if (this.checked) {
            $(".harga-satuan-2").removeAttr("readonly");
            $(".satuan_id_2").attr("required", true);
        } else {
            $(".harga-satuan-2").attr("readonly", true);
            $(".satuan_id_2").removeAttr("required");
        }
    });

    $('.checkbox-satuan-3').change(function() {
        // this will contain a reference to the checkbox   
        if (this.checked) {
            $(".harga-satuan-3").removeAttr("readonly");
            $(".satuan_id_3").attr("required", true);
        } else {
            $(".harga-satuan-3").attr("readonly", true);
            $(".satuan_id_3").removeAttr("required");
        }
    });

    $('.checkbox-satuan-4').change(function() {
        // this will contain a reference to the checkbox   
        if (this.checked) {
            $(".harga-satuan-4").removeAttr("readonly");
            $(".satuan_id_4").attr("required", true);
        } else {
            $(".harga-satuan-4").attr("readonly", true);
            $(".satuan_id_4").removeAttr("required");
        }
    });
</script>
<script>
    function updateGrosir() {
        var barang_harga = parseFloat(document.getElementById('barang_harga').value) || 0;
        var retail_persen = parseFloat(document.getElementById('barang_harga_retail').value) || 0;
        var grosir_persen = parseFloat(document.getElementById('barang_harga_grosir').value) || 0;

        var harga_grosir_1 = barang_harga - (barang_harga * (retail_persen / 100));
        var harga_grosir_2 = barang_harga - (barang_harga * (grosir_persen / 100));

        document.getElementById('barang_harga_grosir_1').value = harga_grosir_1.toFixed(0);
        document.getElementById('barang_harga_grosir_2').value = harga_grosir_2.toFixed(0);
    }
</script>
<script>
        var harga_grosir_2 = barang_harga - (barang_harga * (grosir_persen / 100));

        document.getElementById('barang_harga_grosir_1').value = harga_grosir_1.toFixed(0);
        document.getElementById('barang_harga_grosir_2').value = harga_grosir_2.toFixed(0);
    }
</script>

