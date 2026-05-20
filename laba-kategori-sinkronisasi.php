<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin != "admin" && $levelLogin != "super admin") {
  echo "
    <script>
      document.location.href = 'bo';
    </script>
  ";
}

$listCabang = query("SELECT * FROM toko ORDER BY toko_nama");
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-9">
          <h1>Sinkronisasi Akun Kategori Laba</h1>
        </div>
        <div class="col-sm-3">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="laba-kategori">Kategori Laba</a></li>
            <li class="breadcrumb-item active">Sinkronisasi</li>
          </ol>
        </div>
      </div>
    </div><!-- /.container-fluid -->
  </section>
  <section class="content">
    <div class="container-fluid">
      <!-- Info Card -->
      <div class="alert alert-info">
        <h5><i class="icon fas fa-info"></i> Informasi</h5>
        Halaman ini digunakan untuk mengelola dan mensinkronisasi akun kategori laba antar cabang.
        Anda dapat menyalin akun dari satu cabang ke cabang lain, melihat semua akun di semua cabang,
        dan memastikan tidak ada duplikasi akun.
      </div>

      <!-- Card untuk Sinkronisasi -->
      <div class="card card-primary">
        <div class="card-header">
          <h3 class="card-title">Sinkronisasi Akun Antar Cabang</h3>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-12">
              <div class="form-group">
                <label>Salin Akun Dari Cabang</label>
                <select class="form-control" id="source-cabang">
                  <option value="">Pilih Cabang Sumber</option>
                  <?php foreach ($listCabang as $c) : ?>
                    <option value="<?= $c['toko_cabang']; ?>">
                      <?= $c['toko_nama']; ?> - <?= $c['toko_kota']; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group">
                <label>Ke Cabang</label>
                <select class="form-control" id="target-cabang">
                  <option value="">Pilih Cabang Tujuan</option>
                  <?php foreach ($listCabang as $c) : ?>
                    <option value="<?= $c['toko_cabang']; ?>">
                      <?= $c['toko_nama']; ?> - <?= $c['toko_kota']; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group">
                <label>
                  <input type="checkbox" id="skip-duplicate" checked>
                  Lewati akun yang sudah ada (berdasarkan nama)
                </label>
                <small class="form-text text-muted">
                  Jika dicentang, akun dengan nama yang sama di cabang tujuan akan dilewati
                </small>
              </div>
            </div>
            <div class="col-md-12">
              <button type="button" class="btn btn-success btn-block" id="btn-sinkronisasi">
                <i class="fas fa-sync"></i> Sinkronisasi Akun
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Card untuk View All Akun -->
      <div class="card card-info">
        <div class="card-header">
          <h3 class="card-title">Daftar Akun Semua Cabang</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-sm btn-primary" id="btn-refresh-all">
              <i class="fas fa-sync"></i> Refresh
            </button>
          </div>
        </div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-4">
              <div class="form-group">
                <label>Filter Cabang</label>
                <select class="form-control" id="filter-cabang-all">
                  <option value="">Semua Cabang</option>
                  <?php foreach ($listCabang as $c) : ?>
                    <option value="<?= $c['toko_cabang']; ?>">
                      <?= $c['toko_nama']; ?> - <?= $c['toko_kota']; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Filter Kategori</label>
                <select class="form-control" id="filter-kategori-all">
                  <option value="">Semua Kategori</option>
                  <option value="aktiva">Aktiva</option>
                  <option value="pasiva">Pasiva</option>
                  <option value="modal">Modal</option>
                  <option value="pendapatan">Pendapatan</option>
                  <option value="beban">Beban</option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Cari Akun</label>
                <input type="text" class="form-control" id="search-akun" placeholder="Cari berdasarkan nama atau kode akun">
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
              <thead>
                <tr>
                  <th class="text-center" style="width: 50px;">No</th>
                  <th>Cabang</th>
                  <th>Kode Akun</th>
                  <th>Nama Akun</th>
                  <th>Kategori</th>
                  <th>Tipe Akun</th>
                  <th class="text-right">Saldo Awal</th>
                  <th class="text-center" style="width: 150px;">Aksi</th>
                </tr>
              </thead>
              <tbody id="table-all-akun">
                <tr>
                  <td colspan="8" class="text-center">Pilih filter atau klik Refresh untuk memuat data</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Card untuk Duplikasi Check -->
      <div class="card card-warning">
        <div class="card-header">
          <h3 class="card-title">Cek Duplikasi Akun</h3>
        </div>
        <div class="card-body">
          <p>Klik tombol di bawah untuk mengecek apakah ada akun dengan nama yang sama di cabang yang berbeda.</p>
          <button type="button" class="btn btn-warning" id="btn-check-duplicate">
            <i class="fas fa-search"></i> Cek Duplikasi
          </button>
          <div id="duplicate-results" class="mt-3"></div>
        </div>
      </div>
    </div>
  </section>
</div>

<script src="./dist/js/utils.js"></script>
<script>
  const base_url = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '')

  // Sinkronisasi Akun
  $('#btn-sinkronisasi').on('click', function() {
    const sourceCabang = $('#source-cabang').val()
    const targetCabang = $('#target-cabang').val()
    const skipDuplicate = $('#skip-duplicate').is(':checked')

    // Validate: check if empty string or null (0 is valid for PCNU)
    // Use strict check: only reject if null, undefined, or empty string
    if (sourceCabang === null || sourceCabang === undefined || sourceCabang === '') {
      Swal.fire({
        icon: "error",
        title: "Error!",
        text: "Pilih cabang sumber terlebih dahulu",
      });
      return
    }

    if (targetCabang === null || targetCabang === undefined || targetCabang === '') {
      Swal.fire({
        icon: "error",
        title: "Error!",
        text: "Pilih cabang tujuan terlebih dahulu",
      });
      return
    }

    if (sourceCabang === targetCabang) {
      Swal.fire({
        icon: "error",
        title: "Error!",
        text: "Cabang sumber dan tujuan tidak boleh sama",
      });
      return
    }

    // Convert to integer (0 is valid for PCNU)
    const sourceCabangInt = parseInt(sourceCabang)
    const targetCabangInt = parseInt(targetCabang)

    if (isNaN(sourceCabangInt) || isNaN(targetCabangInt) || sourceCabangInt < 0 || targetCabangInt < 0) {
      Swal.fire({
        icon: "error",
        title: "Error!",
        text: "Nilai cabang tidak valid. Pastikan memilih cabang yang benar.",
      });
      return
    }

    Swal.fire({
      title: 'Konfirmasi Sinkronisasi',
      text: `Apakah Anda yakin ingin menyalin semua akun dari cabang sumber ke cabang tujuan?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Ya, Sinkronisasi!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.value) {
        const requestData = {
          action: 'sinkronisasi',
          source_cabang: sourceCabangInt,
          target_cabang: targetCabangInt,
          skip_duplicate: skipDuplicate
        }

        console.log('Sending data:', requestData)

        $.ajax({
          url: base_url + '/api/laba-kategori-sinkronisasi.php',
          method: 'POST',
          data: JSON.stringify(requestData),
          contentType: 'application/json',
          headers: {
            'Accept': 'application/json',
          },
          dataType: 'json',
          beforeSend: () => {
            $('#btn-sinkronisasi').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Memproses...')
          },
          success: (res) => {
            let messageHtml = res.message || 'Terjadi kesalahan';
            
            // Add verification info if available
            if (res.data && res.data.verification) {
              const verif = res.data.verification;
              messageHtml += '<br><br><strong>Verifikasi Data:</strong><br>';
              messageHtml += '<div style="text-align: left; font-size: 12px; background: #f0f0f0; padding: 10px; border-radius: 5px; margin-top: 10px;">';
              messageHtml += `- Total akun di cabang ${verif.target_cabang} sebelum sinkronisasi: ${res.data.total_target_before || 0}<br>`;
              messageHtml += `- Total akun di cabang ${verif.target_cabang} setelah sinkronisasi: ${res.data.total_target_after || 0}<br>`;
              messageHtml += `- Jumlah nama akun yang sudah ada: ${verif.existing_names_count || 0}<br>`;
              if (verif.existing_accounts_sample && verif.existing_accounts_sample.length > 0) {
                messageHtml += '<br><strong>Sample akun yang ada di cabang tujuan:</strong><br>';
                verif.existing_accounts_sample.forEach((acc, idx) => {
                  messageHtml += (idx + 1) + '. ' + acc + '<br>';
                });
              }
              messageHtml += '</div>';
            }
            
            if (res.success) {
              Swal.fire({
                icon: "success",
                title: "Berhasil!",
                html: messageHtml,
                width: '700px'
              });
              loadAllAkun()
            } else {
              // Show detailed error message
              let errorHtml = messageHtml;
              
              // If there are detailed errors, show them
              if (res.data && res.data.errors && res.data.errors.length > 0) {
                errorHtml += '<br><br><strong>Detail Error:</strong><br>';
                errorHtml += '<div style="max-height: 300px; overflow-y: auto; text-align: left; font-size: 12px; background: #f5f5f5; padding: 10px; border-radius: 5px;">';
                res.data.errors.slice(0, 20).forEach((err, idx) => {
                  errorHtml += (idx + 1) + '. ' + err + '<br>';
                });
                if (res.data.errors.length > 20) {
                  errorHtml += '<br>... dan ' + (res.data.errors.length - 20) + ' error lainnya';
                }
                errorHtml += '</div>';
              }
              
              Swal.fire({
                icon: res.data && res.data.total_errors > 0 ? "warning" : "error",
                title: res.data && res.data.total_errors > 0 ? "Peringatan!" : "Error!",
                html: errorHtml,
                width: '700px'
              });
              
              // Still refresh the list even if there are errors
              if (res.data && res.data.copied > 0) {
                loadAllAkun()
              }
            }
            $('#btn-sinkronisasi').prop('disabled', false).html('<i class="fas fa-sync"></i> Sinkronisasi Akun')
          },
          error: (err) => {
            console.log('Error:', err)
            console.log('Response:', err.responseText)
            let errorMsg = "Terjadi kesalahan";
            let debugInfo = "";
            try {
              const response = JSON.parse(err.responseText);
              errorMsg = response.message || errorMsg;
              if (response.debug) {
                debugInfo = "\n\nDebug: " + JSON.stringify(response.debug, null, 2);
                console.log('Debug info:', response.debug);
              }
            } catch (e) {
              errorMsg = err.responseText || errorMsg;
            }
            Swal.fire({
              icon: "error",
              title: "Error!",
              html: errorMsg + (debugInfo ? '<pre style="text-align:left;font-size:11px;">' + debugInfo + '</pre>' : '')
            });
            $('#btn-sinkronisasi').prop('disabled', false).html('<i class="fas fa-sync"></i> Sinkronisasi Akun')
          }
        });
      }
    })
  })

  // Load All Akun
  const loadAllAkun = () => {
    const filterCabang = $('#filter-cabang-all').val()
    const filterKategori = $('#filter-kategori-all').val()
    const search = $('#search-akun').val()

    $.ajax({
      url: base_url + '/api/laba-kategori-sinkronisasi.php',
      method: 'GET',
      data: {
        action: 'get_all',
        cabang: filterCabang || null,
        kategori: filterKategori || null,
        search: search || null
      },
      headers: {
        'Accept': 'application/json',
      },
      dataType: 'json',
      beforeSend: () => {
        $('#table-all-akun').html('<tr><td colspan="8" class="text-center">Loading...</td></tr>')
      },
      success: (res) => {
        let html = ''
        if (res.success && res.data && res.data.length > 0) {
          res.data.forEach((item, index) => {
            const kategoriBadge = {
              'aktiva': 'badge-info',
              'pasiva': 'badge-warning',
              'modal': 'badge-success',
              'pendapatan': 'badge-primary',
              'beban': 'badge-danger'
            }
            const tipeBadge = {
              'debit': 'badge-primary',
              'kredit': 'badge-success'
            }
            html += `
              <tr>
                <td class="text-center">${index + 1}</td>
                <td>${item.cabang_name || '-'}</td>
                <td>${item.kode_akun || '-'}</td>
                <td>${item.name}</td>
                <td><span class="badge ${kategoriBadge[item.kategori] || 'badge-secondary'}">${item.kategori ? item.kategori.toUpperCase() : '-'}</span></td>
                <td><span class="badge ${tipeBadge[item.tipe_akun] || 'badge-secondary'}">${item.tipe_akun ? item.tipe_akun.toUpperCase() : '-'}</span></td>
                <td class="text-right">${toRupiah(item.saldo || 0, false)}</td>
                <td class="text-center">
                  <button class="btn btn-info btn-sm" onclick="copyToOtherCabang(${item.id}, '${item.name}')" title="Salin ke cabang lain">
                    <i class="fas fa-copy"></i>
                  </button>
                  <button class="btn btn-danger btn-sm" onclick="deleteAkun(${item.id}, '${item.name}')" title="Hapus">
                    <i class="fas fa-trash"></i>
                  </button>
                </td>
              </tr>
            `
          })
        } else {
          html = '<tr><td colspan="8" class="text-center">Tidak ada data</td></tr>'
        }
        $('#table-all-akun').html(html)
      },
      error: (err) => {
        console.log('Error:', err)
        $('#table-all-akun').html('<tr><td colspan="8" class="text-center">Terjadi kesalahan saat memuat data</td></tr>')
      }
    })
  }

  // Check Duplicate
  $('#btn-check-duplicate').on('click', function() {
    $.ajax({
      url: base_url + '/api/laba-kategori-sinkronisasi.php',
      method: 'GET',
      data: {
        action: 'check_duplicate'
      },
      headers: {
        'Accept': 'application/json',
      },
      dataType: 'json',
      beforeSend: () => {
        $('#btn-check-duplicate').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Mengecek...')
        $('#duplicate-results').html('<div class="alert alert-info">Sedang mengecek duplikasi...</div>')
      },
      success: (res) => {
        $('#btn-check-duplicate').prop('disabled', false).html('<i class="fas fa-search"></i> Cek Duplikasi')

        if (res.success && res.data && res.data.length > 0) {
          let html = '<div class="alert alert-warning"><h5>Ditemukan ' + res.data.length + ' akun duplikat:</h5><ul class="mb-0">'
          res.data.forEach((dup) => {
            html += `<li><strong>${dup.name}</strong> ditemukan di: ${dup.cabangs.join(', ')}</li>`
          })
          html += '</ul></div>'
          $('#duplicate-results').html(html)
        } else {
          $('#duplicate-results').html('<div class="alert alert-success">Tidak ada duplikasi akun ditemukan. Semua akun unik per cabang.</div>')
        }
      },
      error: (err) => {
        console.log('Error:', err)
        $('#btn-check-duplicate').prop('disabled', false).html('<i class="fas fa-search"></i> Cek Duplikasi')
        $('#duplicate-results').html('<div class="alert alert-danger">Terjadi kesalahan saat mengecek duplikasi</div>')
      }
    })
  })

  // Copy to Other Cabang
  window.copyToOtherCabang = (akunId, akunName) => {
    Swal.fire({
      title: 'Salin Akun',
      html: `
        <p>Pilih cabang tujuan untuk akun: <strong>${akunName}</strong></p>
        <select id="copy-target-cabang" class="form-control">
          <option value="">Pilih Cabang</option>
          <?php foreach ($listCabang as $c) : ?>
            <option value="<?= $c['toko_cabang']; ?>"><?= $c['toko_nama']; ?> - <?= $c['toko_kota']; ?></option>
          <?php endforeach; ?>
        </select>
      `,
      showCancelButton: true,
      confirmButtonText: 'Salin',
      cancelButtonText: 'Batal',
      preConfirm: () => {
        const targetCabang = document.getElementById('copy-target-cabang').value
        if (!targetCabang) {
          Swal.showValidationMessage('Pilih cabang tujuan')
          return false
        }
        return targetCabang
      }
    }).then((result) => {
      if (result.value) {
        $.ajax({
          url: base_url + '/api/laba-kategori-sinkronisasi.php',
          method: 'POST',
          data: JSON.stringify({
            action: 'copy_single',
            akun_id: akunId,
            target_cabang: result.value
          }),
          contentType: 'application/json',
          headers: {
            'Accept': 'application/json',
          },
          dataType: 'json',
          success: (res) => {
            if (res.success) {
              Swal.fire({
                icon: "success",
                title: "Berhasil!",
                text: res.message || "Akun berhasil disalin",
              });
              loadAllAkun()
            } else {
              Swal.fire({
                icon: "error",
                title: "Error!",
                text: res.message || 'Terjadi kesalahan',
              });
            }
          },
          error: (err) => {
            console.log('Error:', err)
            let errorMsg = "Terjadi kesalahan";
            try {
              const response = JSON.parse(err.responseText);
              errorMsg = response.message || errorMsg;
            } catch (e) {
              errorMsg = err.responseText || errorMsg;
            }
            Swal.fire({
              icon: "error",
              title: "Error!",
              text: errorMsg
            });
          }
        });
      }
    })
  }

  // Delete Akun
  window.deleteAkun = (akunId, akunName) => {
    Swal.fire({
      title: "Hapus Akun?",
      text: `Apakah Anda yakin ingin menghapus akun "${akunName}"?`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Ya, Hapus!",
      cancelButtonText: "Batal"
    }).then((result) => {
      if (result.value) {
        $.ajax({
          url: base_url + '/api/laba-kategori.php?id=' + akunId,
          type: 'DELETE',
          success: function(response) {
            if (response.success) {
              Swal.fire({
                title: "Berhasil!",
                text: response.message || "Akun berhasil dihapus",
                icon: "success"
              });
              loadAllAkun()
            } else {
              Swal.fire({
                title: "Error!",
                text: response.message || "Gagal menghapus akun",
                icon: "error"
              });
            }
          },
          error: function(xhr, status, error) {
            let errorMsg = "Terjadi kesalahan";
            try {
              const response = JSON.parse(xhr.responseText);
              errorMsg = response.message || errorMsg;
            } catch (e) {
              errorMsg = xhr.responseText || errorMsg;
            }
            Swal.fire({
              title: "Error!",
              text: errorMsg,
              icon: "error"
            });
          }
        });
      }
    });
  }

  // Event Listeners
  $('#btn-refresh-all, #filter-cabang-all, #filter-kategori-all').on('change', function() {
    loadAllAkun()
  })

  $('#search-akun').on('keyup', function() {
    clearTimeout(window.searchTimeout)
    window.searchTimeout = setTimeout(() => {
      loadAllAkun()
    }, 500)
  })

  $(document).ready(function() {
    // Load data on page load
    loadAllAkun()
  })
</script>

<?php include '_footer.php'; ?>