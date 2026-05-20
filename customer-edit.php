<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
?>
<?php  
  if ( $levelLogin === "kurir") {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
  }  
?>
<?php  
// ambil data di URL
$id = abs((int)$_GET['id']);


// query data mahasiswa berdasarkan id
$customer = query("SELECT * FROM customer WHERE customer_id = $id ")[0];

// cek apakah tombol submit sudah ditekan atau belum
if( isset($_POST["submit"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if( editCustomer($_POST) > 0 ) {
    echo "
      <script>
        document.location.href = 'customer';
      </script>
    ";
  } else {
    echo "
      <script>
        alert('Data GAGAL Ditambahkan');
      </script>
    ";
  }
  
}
?>


  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Edit Data Customer</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="bo">Home</a></li>
              <li class="breadcrumb-item active">Data Customer</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <!-- left column -->
          <div class="col-md-12">
            <!-- general form elements -->
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Data Customer</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form role="form" action="" method="post">
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6 col-lg-6">
                      <input type="hidden" name="customer_id" value="<?= $customer['customer_id']; ?>">
                        <div class="form-group">
                          <label for="customer_nama">Nama Lengkap</label>
                          <input type="text" name="customer_nama" class="form-control" id="customer_nama" value="<?= $customer['customer_nama']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="customer_tlpn">No. WhatsApp</label>
                            <input type="text" name="customer_tlpn" class="form-control" id="customer_tlpn" value="<?= $customer['customer_tlpn']; ?>" required onkeypress="return hanyaAngka(event)">
                        </div>
                         <div class="form-group">
                          <label for="customer_email">Email (Tidak Wajib)</label>
                          <input type="email" name="customer_email" class="form-control" id="customer_email" value="<?= $customer['customer_email']; ?>">
                        </div>
                        <div class="form-group ">
                          <label for="customer_category">Kategori</label>
                          <div class="">
                              <?php  
                                  if ( $customer['customer_category'] == 1 ) {
                                    $customer_category = "Member Retail";
                                  } elseif ( $customer['customer_category'] == 2 ) {
                                    $customer_category = "Grosir";
                                  } else {
                                    $customer_category = "Umum";
                                  }
                                ?>
                              <select name="customer_category" required="" class="form-control ">
                                  <option value="<?= $customer['customer_category']; ?>"><?= $customer_category; ?></option>

                                  <?php if ( $customer['customer_category'] == 1 ) : ?>
                                    <option value="0">Umum</option>
                                    <option value="2">Grosir</option>
                                  <?php elseif ( $customer['customer_category'] == 2 ) : ?> 
                                    <option value="0">Umum</option>
                                    <option value="1">Member Retail</option>
                                  <?php else : ?>
                                    <option value="1">Member Retail</option>
                                    <option value="2">Grosir</option>
                                  <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="customer_birthday">Tanggal Lahir (Opsional)</label>
                          <input type="date" name="customer_birthday" class="form-control" id="customer_birthday" value="<?= $customer['customer_birthday']; ?>">
                        </div>
                        <div class="form-group ">
                          <label for="customer_status">Status</label>
                          <div class="">
                                <?php  
                                  if ( $customer['customer_status'] === "1" ) {
                                    $status = "Active";
                                  } else {
                                    $status = "Not Active";
                                  }
                                ?>
                                  <select name="customer_status" required="" class="form-control ">
                                    <option value="<?= $customer['customer_status']; ?>"><?= $status; ?></option>
                                    <?php  
                                      if ( $customer['customer_status'] === "1" ) {
                                        echo '
                                          <option value="0">Not Active</option>
                                        ';
                                      } else {
                                        echo '
                                          <option value="1">Active</option>
                                        ';
                                      }
                                    ?>
                                  </select>
                              </div>
                        </div>
                         <div class="form-group">
                            <label for="customer_kartu">No Kartu</label>
                            <input type="text" name="customer_kartu" class="form-control" id="customer_kartu" value="<?= $customer['customer_kartu']; ?>" >
                        </div>
                    </div>
                  </div>

                  <!-- Alamat Detail Section -->
                  <hr>
                  <h5 class="mb-3"><i class="fas fa-map-marker-alt"></i> Data Alamat</h5>
                  <div class="row">
                    <div class="col-md-12 mb-3">
                       <div class="form-group">
                            <label for="customer_alamat">Alamat Lengkap (RT/RW, Nama Jalan, No. Rumah)</label>
                            <textarea name="customer_alamat" id="customer_alamat" class="form-control" required="required" placeholder="Contoh: RT 01/RW 02, Jl. Merdeka No. 123" style="height:80px;"><?= $customer['customer_alamat']; ?></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="alamat_dusun">Dusun/Kampung (Opsional)</label>
                            <input type="text" name="alamat_dusun" class="form-control" id="alamat_dusun" placeholder="Contoh: Dusun Sukamaju" value="<?= $customer['alamat_dusun']; ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="alamat_provinsi">Provinsi <span class="text-danger">*</span></label>
                            <select name="alamat_provinsi" id="alamat_provinsi" class="form-control select2bs4" required>
                                <option value="">-- Pilih Provinsi --</option>
                                <?php if (!empty($customer['alamat_provinsi'])) : ?>
                                <option value="<?= $customer['alamat_provinsi']; ?>" data-code="<?= $customer['alamat_kode_provinsi']; ?>" selected><?= $customer['alamat_provinsi']; ?></option>
                                <?php endif; ?>
                            </select>
                            <input type="hidden" name="alamat_kode_provinsi" id="alamat_kode_provinsi" value="<?= $customer['alamat_kode_provinsi']; ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="alamat_kabupaten">Kabupaten/Kota <span class="text-danger">*</span></label>
                            <select name="alamat_kabupaten" id="alamat_kabupaten" class="form-control select2bs4" required>
                                <option value="">-- Pilih Kabupaten/Kota --</option>
                                <?php if (!empty($customer['alamat_kabupaten'])) : ?>
                                <option value="<?= $customer['alamat_kabupaten']; ?>" data-code="<?= $customer['alamat_kode_kabupaten']; ?>" selected><?= $customer['alamat_kabupaten']; ?></option>
                                <?php endif; ?>
                            </select>
                            <input type="hidden" name="alamat_kode_kabupaten" id="alamat_kode_kabupaten" value="<?= $customer['alamat_kode_kabupaten']; ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="alamat_kecamatan">Kecamatan <span class="text-danger">*</span></label>
                            <select name="alamat_kecamatan" id="alamat_kecamatan" class="form-control select2bs4" required>
                                <option value="">-- Pilih Kecamatan --</option>
                                <?php if (!empty($customer['alamat_kecamatan'])) : ?>
                                <option value="<?= $customer['alamat_kecamatan']; ?>" data-code="<?= $customer['alamat_kode_kecamatan']; ?>" selected><?= $customer['alamat_kecamatan']; ?></option>
                                <?php endif; ?>
                            </select>
                            <input type="hidden" name="alamat_kode_kecamatan" id="alamat_kode_kecamatan" value="<?= $customer['alamat_kode_kecamatan']; ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="alamat_desa">Desa/Kelurahan <span class="text-danger">*</span></label>
                            <select name="alamat_desa" id="alamat_desa" class="form-control select2bs4" required>
                                <option value="">-- Pilih Desa/Kelurahan --</option>
                                <?php if (!empty($customer['alamat_desa'])) : ?>
                                <option value="<?= $customer['alamat_desa']; ?>" data-code="<?= $customer['alamat_kode_desa']; ?>" selected><?= $customer['alamat_desa']; ?></option>
                                <?php endif; ?>
                            </select>
                            <input type="hidden" name="alamat_kode_desa" id="alamat_kode_desa" value="<?= $customer['alamat_kode_desa']; ?>">
                        </div>
                    </div>
                  </div>
                <!-- /.card-body -->

                <div class="card-footer text-right">
                  <button type="submit" name="submit" class="btn btn-primary">Submit</button>
                </div>
              </form>
            </div>
          </div>
        </div>
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

    // Wilayah.id API Integration
    const wilayahAPI = 'api/wilayah.php';
    
    // Existing data
    const existingProvinsi = '<?= addslashes($customer['alamat_provinsi'] ?? '') ?>';
    const existingKabupaten = '<?= addslashes($customer['alamat_kabupaten'] ?? '') ?>';
    const existingKecamatan = '<?= addslashes($customer['alamat_kecamatan'] ?? '') ?>';
    const existingDesa = '<?= addslashes($customer['alamat_desa'] ?? '') ?>';
    const existingKodeProvinsi = '<?= addslashes($customer['alamat_kode_provinsi'] ?? '') ?>';
    const existingKodeKabupaten = '<?= addslashes($customer['alamat_kode_kabupaten'] ?? '') ?>';
    const existingKodeKecamatan = '<?= addslashes($customer['alamat_kode_kecamatan'] ?? '') ?>';
    const existingKodeDesa = '<?= addslashes($customer['alamat_kode_desa'] ?? '') ?>';

    $(document).ready(function() {
        // Initialize Select2
        $('.select2bs4').select2({
            theme: 'bootstrap4'
        });

        // Load provinces on page load
        loadProvinces();

        // Province change handler - using jQuery for Select2 compatibility
        $('#alamat_provinsi').on('change', async function() {
            const selectedOption = $(this).find('option:selected');
            const code = selectedOption.data('code');
            const value = $(this).val();
            
            $('#alamat_kode_provinsi').val(code || '');
            
            // Reset and disable dependent fields
            $('#alamat_kabupaten').html('<option value="">-- Pilih Kabupaten/Kota --</option>').prop('disabled', true).trigger('change.select2');
            $('#alamat_kecamatan').html('<option value="">-- Pilih Kecamatan --</option>').prop('disabled', true).trigger('change.select2');
            $('#alamat_desa').html('<option value="">-- Pilih Desa/Kelurahan --</option>').prop('disabled', true).trigger('change.select2');
            
            $('#alamat_kode_kabupaten, #alamat_kode_kecamatan, #alamat_kode_desa').val('');
            
            if (code && value) {
                await loadRegencies(code, false);
                $('#alamat_kabupaten').prop('disabled', false).trigger('change.select2');
            }
        });

        // Kabupaten change handler
        $('#alamat_kabupaten').on('change', async function() {
            const selectedOption = $(this).find('option:selected');
            const code = selectedOption.data('code');
            const value = $(this).val();
            
            $('#alamat_kode_kabupaten').val(code || '');
            
            // Reset and disable dependent fields
            $('#alamat_kecamatan').html('<option value="">-- Pilih Kecamatan --</option>').prop('disabled', true).trigger('change.select2');
            $('#alamat_desa').html('<option value="">-- Pilih Desa/Kelurahan --</option>').prop('disabled', true).trigger('change.select2');
            
            $('#alamat_kode_kecamatan, #alamat_kode_desa').val('');
            
            if (code && value) {
                await loadDistricts(code, false);
                $('#alamat_kecamatan').prop('disabled', false).trigger('change.select2');
            }
        });

        // Kecamatan change handler
        $('#alamat_kecamatan').on('change', async function() {
            const selectedOption = $(this).find('option:selected');
            const code = selectedOption.data('code');
            const value = $(this).val();
            
            $('#alamat_kode_kecamatan').val(code || '');
            
            // Reset and disable dependent fields
            $('#alamat_desa').html('<option value="">-- Pilih Desa/Kelurahan --</option>').prop('disabled', true).trigger('change.select2');
            
            $('#alamat_kode_desa').val('');
            
            if (code && value) {
                await loadVillages(code, false);
                $('#alamat_desa').prop('disabled', false).trigger('change.select2');
            }
        });

        // Desa change handler
        $('#alamat_desa').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const code = selectedOption.data('code');
            $('#alamat_kode_desa').val(code || '');
        });
    });

    async function loadProvinces() {
        try {
            const response = await fetch(`${wilayahAPI}?type=provinces`);
            const result = await response.json();
            
            if (result.data) {
                let options = '<option value="">-- Pilih Provinsi --</option>';
                
                result.data.sort((a, b) => a.name.localeCompare(b.name));
                
                result.data.forEach(item => {
                    const selected = item.name === existingProvinsi ? 'selected' : '';
                    options += `<option value="${item.name}" data-code="${item.code}" ${selected}>${item.name}</option>`;
                });
                
                $('#alamat_provinsi').html(options).trigger('change.select2');
                
                // If there's existing data, load regencies
                if (existingKodeProvinsi) {
                    await loadRegencies(existingKodeProvinsi, true);
                }
            }
        } catch (error) {
            console.error('Error loading provinces:', error);
            alert('Gagal memuat data provinsi. Pastikan koneksi internet aktif.');
        }
    }

    async function loadRegencies(provinceCode, keepExisting = false) {
        try {
            // Show loading
            $('#alamat_kabupaten').html('<option value="">Memuat...</option>').trigger('change.select2');
            
            const response = await fetch(`${wilayahAPI}?type=regencies&code=${provinceCode}`);
            const result = await response.json();
            
            if (result.data) {
                let options = '<option value="">-- Pilih Kabupaten/Kota --</option>';
                
                result.data.sort((a, b) => a.name.localeCompare(b.name));
                
                result.data.forEach(item => {
                    const selected = (keepExisting && item.name === existingKabupaten) ? 'selected' : '';
                    options += `<option value="${item.name}" data-code="${item.code}" ${selected}>${item.name}</option>`;
                });
                
                $('#alamat_kabupaten').html(options).prop('disabled', false).trigger('change.select2');
                
                // If there's existing data, load districts
                if (keepExisting && existingKodeKabupaten) {
                    await loadDistricts(existingKodeKabupaten, true);
                }
            }
        } catch (error) {
            console.error('Error loading regencies:', error);
            $('#alamat_kabupaten').html('<option value="">-- Gagal memuat --</option>').trigger('change.select2');
        }
    }

    async function loadDistricts(regencyCode, keepExisting = false) {
        try {
            // Show loading
            $('#alamat_kecamatan').html('<option value="">Memuat...</option>').trigger('change.select2');
            
            const response = await fetch(`${wilayahAPI}?type=districts&code=${regencyCode}`);
            const result = await response.json();
            
            if (result.data) {
                let options = '<option value="">-- Pilih Kecamatan --</option>';
                
                result.data.sort((a, b) => a.name.localeCompare(b.name));
                
                result.data.forEach(item => {
                    const selected = (keepExisting && item.name === existingKecamatan) ? 'selected' : '';
                    options += `<option value="${item.name}" data-code="${item.code}" ${selected}>${item.name}</option>`;
                });
                
                $('#alamat_kecamatan').html(options).prop('disabled', false).trigger('change.select2');
                
                // If there's existing data, load villages
                if (keepExisting && existingKodeKecamatan) {
                    await loadVillages(existingKodeKecamatan, true);
                }
            }
        } catch (error) {
            console.error('Error loading districts:', error);
            $('#alamat_kecamatan').html('<option value="">-- Gagal memuat --</option>').trigger('change.select2');
        }
    }

    async function loadVillages(districtCode, keepExisting = false) {
        try {
            // Show loading
            $('#alamat_desa').html('<option value="">Memuat...</option>').trigger('change.select2');
            
            const response = await fetch(`${wilayahAPI}?type=villages&code=${districtCode}`);
            const result = await response.json();
            
            if (result.data) {
                let options = '<option value="">-- Pilih Desa/Kelurahan --</option>';
                
                result.data.sort((a, b) => a.name.localeCompare(b.name));
                
                result.data.forEach(item => {
                    const selected = (keepExisting && item.name === existingDesa) ? 'selected' : '';
                    options += `<option value="${item.name}" data-code="${item.code}" ${selected}>${item.name}</option>`;
                });
                
                $('#alamat_desa').html(options).prop('disabled', false).trigger('change.select2');
            }
        } catch (error) {
            console.error('Error loading villages:', error);
            $('#alamat_desa').html('<option value="">-- Gagal memuat --</option>').trigger('change.select2');
        }
    }
</script>