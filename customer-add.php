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

// cek apakah tombol submit sudah ditekan atau belum
if( isset($_POST["submit"]) ){
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if( tambahCustomer($_POST) > 0 ) {
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
            <h1>Tambah Data Customer</h1>
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
                      <input type="hidden" name="customer_cabang" value="<?= $sessionCabang; ?>">
                        <div class="form-group">
                          <label for="customer_nama">Nama Lengkap</label>
                          <input type="text" name="customer_nama" class="form-control" id="customer_nama" placeholder="Enter Nama Lengkap" required>
                        </div>
                        <div class="form-group">
                            <label for="customer_tlpn">No. WhatsApp</label>
                            <input type="number" name="customer_tlpn" class="form-control" id="customer_tlpn" placeholder="Contoh: 081234567890" required onkeypress="return hanyaAngka(event)">
                        </div>
                         <div class="form-group">
                          <label for="customer_email">Email (Tidak Wajib)</label>
                          <input type="email" name="customer_email" class="form-control" id="customer_email" placeholder="Enter email">
                        </div>
                        <div class="form-group ">
                          <label for="customer_category">Kelas Member</label>
                          <div class="">
                              <select name="customer_category" required="" class="form-control ">
                                  <option value="">-- Pilih --</option>
                                  <option value="0">Member Umum</option>
                                  <option value="1">Member Retail</option>
                                  <option value="2">Member Grosir</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                          <label for="customer_birthday">Tanggal Lahir (Opsional)</label>
                          <input type="date" name="customer_birthday" class="form-control" id="customer_birthday">
                        </div>
                        <div class="form-group ">
                          <label for="customer_status">Status</label>
                          <div class="">
                              <select name="customer_status" required="" class="form-control ">
                                  <option value="">-- Status --</option>
                                  <option value="1">Active</option>
                                  <option value="0">Not Active</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                                <label for="customer_kartu">No Kartu</label>
                                <input type="teks" name="customer_kartu" class="form-control" id="customer_kartu" placeholder="Enter No kartu">
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
                            <textarea name="customer_alamat" id="customer_alamat" class="form-control" required="required" placeholder="Contoh: RT 01/RW 02, Jl. Merdeka No. 123" style="height:80px;"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="alamat_dusun">Dusun/Kampung (Opsional)</label>
                            <input type="text" name="alamat_dusun" class="form-control" id="alamat_dusun" placeholder="Contoh: Dusun Sukamaju">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="alamat_provinsi">Provinsi <span class="text-danger">*</span></label>
                            <select name="alamat_provinsi" id="alamat_provinsi" class="form-control select2bs4" required>
                                <option value="">-- Pilih Provinsi --</option>
                            </select>
                            <input type="hidden" name="alamat_kode_provinsi" id="alamat_kode_provinsi">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="alamat_kabupaten">Kabupaten/Kota <span class="text-danger">*</span></label>
                            <select name="alamat_kabupaten" id="alamat_kabupaten" class="form-control select2bs4" required disabled>
                                <option value="">-- Pilih Kabupaten/Kota --</option>
                            </select>
                            <input type="hidden" name="alamat_kode_kabupaten" id="alamat_kode_kabupaten">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="alamat_kecamatan">Kecamatan <span class="text-danger">*</span></label>
                            <select name="alamat_kecamatan" id="alamat_kecamatan" class="form-control select2bs4" required disabled>
                                <option value="">-- Pilih Kecamatan --</option>
                            </select>
                            <input type="hidden" name="alamat_kode_kecamatan" id="alamat_kode_kecamatan">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="alamat_desa">Desa/Kelurahan <span class="text-danger">*</span></label>
                            <select name="alamat_desa" id="alamat_desa" class="form-control select2bs4" required disabled>
                                <option value="">-- Pilih Desa/Kelurahan --</option>
                            </select>
                            <input type="hidden" name="alamat_kode_desa" id="alamat_kode_desa">
                        </div>
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
                await loadRegencies(code);
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
                await loadDistricts(code);
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
                await loadVillages(code);
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
                    options += `<option value="${item.name}" data-code="${item.code}">${item.name}</option>`;
                });
                
                $('#alamat_provinsi').html(options).trigger('change.select2');
            }
        } catch (error) {
            console.error('Error loading provinces:', error);
            alert('Gagal memuat data provinsi. Pastikan koneksi internet aktif.');
        }
    }

    async function loadRegencies(provinceCode) {
        try {
            // Show loading
            $('#alamat_kabupaten').html('<option value="">Memuat...</option>').trigger('change.select2');
            
            const response = await fetch(`${wilayahAPI}?type=regencies&code=${provinceCode}`);
            const result = await response.json();
            
            if (result.data) {
                let options = '<option value="">-- Pilih Kabupaten/Kota --</option>';
                
                result.data.sort((a, b) => a.name.localeCompare(b.name));
                
                result.data.forEach(item => {
                    options += `<option value="${item.name}" data-code="${item.code}">${item.name}</option>`;
                });
                
                $('#alamat_kabupaten').html(options).trigger('change.select2');
            }
        } catch (error) {
            console.error('Error loading regencies:', error);
            $('#alamat_kabupaten').html('<option value="">-- Gagal memuat --</option>').trigger('change.select2');
        }
    }

    async function loadDistricts(regencyCode) {
        try {
            // Show loading
            $('#alamat_kecamatan').html('<option value="">Memuat...</option>').trigger('change.select2');
            
            const response = await fetch(`${wilayahAPI}?type=districts&code=${regencyCode}`);
            const result = await response.json();
            
            if (result.data) {
                let options = '<option value="">-- Pilih Kecamatan --</option>';
                
                result.data.sort((a, b) => a.name.localeCompare(b.name));
                
                result.data.forEach(item => {
                    options += `<option value="${item.name}" data-code="${item.code}">${item.name}</option>`;
                });
                
                $('#alamat_kecamatan').html(options).trigger('change.select2');
            }
        } catch (error) {
            console.error('Error loading districts:', error);
            $('#alamat_kecamatan').html('<option value="">-- Gagal memuat --</option>').trigger('change.select2');
        }
    }

    async function loadVillages(districtCode) {
        try {
            // Show loading
            $('#alamat_desa').html('<option value="">Memuat...</option>').trigger('change.select2');
            
            const response = await fetch(`${wilayahAPI}?type=villages&code=${districtCode}`);
            const result = await response.json();
            
            if (result.data) {
                let options = '<option value="">-- Pilih Desa/Kelurahan --</option>';
                
                result.data.sort((a, b) => a.name.localeCompare(b.name));
                
                result.data.forEach(item => {
                    options += `<option value="${item.name}" data-code="${item.code}">${item.name}</option>`;
                });
                
                $('#alamat_desa').html(options).trigger('change.select2');
            }
        } catch (error) {
            console.error('Error loading villages:', error);
            $('#alamat_desa').html('<option value="">-- Gagal memuat --</option>').trigger('change.select2');
        }
    }
</script>