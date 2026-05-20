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

$listCabang = query("SELECT * FROM toko WHERE toko_status = '1' ORDER BY toko_cabang");
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-9">
          <h1>Sinkronisasi Data Barang</h1>
        </div>
        <div class="col-sm-3">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="barang">Barang</a></li>
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
        Halaman ini digunakan untuk mensinkronisasi data barang antar cabang.
        Sistem akan menyalin data barang dari cabang sumber ke cabang tujuan.
        <strong>Barang yang sudah ada di cabang tujuan (berdasarkan kode barang) akan dilewati untuk menjaga barang_id yang sudah ada.</strong>
      </div>

      <!-- Card untuk Sinkronisasi -->
      <div class="card card-primary">
        <div class="card-header">
          <h3 class="card-title">Sinkronisasi Barang Antar Cabang</h3>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Salin Barang Dari Cabang (Sumber)</label>
                <select class="form-control" id="source-cabang" required>
                  <option value="">Pilih Cabang Sumber</option>
                  <?php foreach ($listCabang as $c) : ?>
                    <option value="<?= $c['toko_cabang']; ?>" <?= $c['toko_cabang'] == 0 ? 'selected' : ''; ?>>
                      <?php 
                        if ($c['toko_cabang'] == 0) {
                          echo "Pusat (Cabang 0)";
                        } else {
                          echo "Cabang " . $c['toko_cabang'] . " - " . $c['toko_nama'];
                        }
                      ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Ke Cabang (Tujuan)</label>
                <select class="form-control" id="target-cabang" required>
                  <option value="">Pilih Cabang Tujuan</option>
                  <?php foreach ($listCabang as $c) : ?>
                    <?php if ($c['toko_cabang'] != 0) : ?>
                      <option value="<?= $c['toko_cabang']; ?>">
                        Cabang <?= $c['toko_cabang']; ?> - <?= $c['toko_nama']; ?>
                      </option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-12">
              <div class="alert alert-warning">
                <strong>Catatan Penting:</strong>
                <ul class="mb-0">
                  <li>Barang yang sudah ada di cabang tujuan (berdasarkan <code>barang_kode</code>) akan <strong>dilewati</strong></li>
                  <li>Hanya barang yang belum ada di cabang tujuan yang akan disalin</li>
                  <li><strong>barang_id</strong> yang sudah ada tidak akan diubah untuk menghindari gangguan pada fitur lain</li>
                  <li>Stock barang yang disalin akan di-set ke 0 (nol)</li>
                </ul>
              </div>
            </div>
            <div class="col-md-12">
              <button type="button" class="btn btn-success btn-block btn-lg" id="btn-sinkronisasi">
                <i class="fas fa-sync"></i> Mulai Sinkronisasi Barang
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Card untuk Progress -->
      <div class="card card-info" id="progress-card" style="display: none;">
        <div class="card-header">
          <h3 class="card-title">
            <i class="fas fa-sync fa-spin" id="loading-icon"></i> Progress Sinkronisasi
          </h3>
        </div>
        <div class="card-body">
          <div class="progress mb-3" style="height: 30px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" 
                 id="progress-bar" style="width: 0%; font-size: 14px; line-height: 30px; font-weight: bold;">0%</div>
          </div>
          <div id="progress-log" class="alert alert-info" style="max-height: 300px; overflow-y: auto;">
            <p class="mb-0">
              <i class="fas fa-spinner fa-spin"></i> Memulai sinkronisasi...
            </p>
          </div>
        </div>
      </div>

      <!-- Card untuk Hasil -->
      <div class="card card-success" id="result-card" style="display: none;">
        <div class="card-header">
          <h3 class="card-title">Hasil Sinkronisasi</h3>
        </div>
        <div class="card-body">
          <div id="result-content"></div>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include '_footer.php'; ?>

<script>
$(document).ready(function() {
  $('#btn-sinkronisasi').click(function() {
    var sourceCabang = $('#source-cabang').val();
    var targetCabang = $('#target-cabang').val();

    if (!sourceCabang || !targetCabang) {
      alert('Silakan pilih cabang sumber dan cabang tujuan!');
      return;
    }

    if (sourceCabang == targetCabang) {
      alert('Cabang sumber dan cabang tujuan tidak boleh sama!');
      return;
    }

    // Konfirmasi
    if (!confirm('Apakah Anda yakin ingin mensinkronisasi data barang dari Cabang ' + sourceCabang + ' ke Cabang ' + targetCabang + '?\n\nBarang yang sudah ada akan dilewati.')) {
      return;
    }

    // Reset UI
    $('#progress-card').show();
    $('#result-card').hide();
    $('#progress-bar').css('width', '0%').text('0%').removeClass('bg-danger').addClass('bg-success');
    $('#progress-log').html('<p class="mb-0"><i class="fas fa-spinner fa-spin"></i> Memulai sinkronisasi...</p>');
    $('#btn-sinkronisasi').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
    $('#loading-icon').addClass('fa-spin');

    // Variabel untuk tracking progress
    var totalInserted = 0;
    var totalSkipped = 0;
    var totalFailed = 0;
    var currentOffset = 0;
    var totalSource = 0;
    var allLogs = [];
    
    // Fungsi untuk proses batch
    function processBatch(offset) {
      $('#progress-log').html('<p class="mb-0"><i class="fas fa-spinner fa-spin"></i> Memproses batch... (Offset: ' + offset + ')</p>');
      
      $.ajax({
        url: 'aksi/barang-sinkronisasi-proses.php',
        type: 'POST',
        data: {
          source_cabang: sourceCabang,
          target_cabang: targetCabang,
          offset: offset,
          limit: 500
        },
        dataType: 'json',
        timeout: 300000, // 5 menit timeout
        success: function(response) {
          if (response && response.success) {
            // Update totals
            totalInserted += response.inserted || 0;
            totalSkipped += response.skipped || 0;
            totalFailed += response.failed || 0;
            totalSource = response.total_source || 0;
            currentOffset = response.next_offset || 0;
            
            // Merge logs
            if (response.logs && response.logs.length > 0) {
              allLogs = allLogs.concat(response.logs);
            }
            
            // Update progress bar
            var progressPercent = response.progress_percent || 0;
            $('#progress-bar').css('width', progressPercent + '%').text(progressPercent.toFixed(1) + '%');
            
            // Update log
            var logMsg = 'Diproses: ' + currentOffset + ' / ' + totalSource + ' | ';
            logMsg += 'Berhasil: ' + totalInserted + ' | ';
            logMsg += 'Dilewati: ' + totalSkipped + ' | ';
            logMsg += 'Gagal: ' + totalFailed;
            $('#progress-log').html('<p class="mb-0 text-info"><i class="fas fa-info-circle"></i> ' + logMsg + '</p>');
            
            // Jika masih ada data, lanjutkan batch berikutnya
            if (response.has_more) {
              setTimeout(function() {
                processBatch(currentOffset);
              }, 500); // Delay 500ms antar batch
            } else {
              // Selesai
              $('#loading-icon').removeClass('fa-spin');
              $('#btn-sinkronisasi').prop('disabled', false).html('<i class="fas fa-sync"></i> Mulai Sinkronisasi Barang');
              $('#progress-bar').css('width', '100%').text('100%');
              
              // Tampilkan hasil
              var resultHtml = '<div class="alert alert-success">';
              resultHtml += '<h5><i class="icon fas fa-check"></i> Sinkronisasi Selesai!</h5>';
              resultHtml += '<ul class="mb-0">';
              resultHtml += '<li><strong>Total barang di cabang sumber:</strong> ' + totalSource + '</li>';
              resultHtml += '<li><strong>Barang yang sudah ada dan aktif di cabang tujuan:</strong> ' + totalSkipped + '</li>';
              resultHtml += '<li><strong>Barang baru yang disalin + barang yang diaktifkan:</strong> <span class="badge badge-success">' + totalInserted + '</span></li>';
              resultHtml += '<li><strong>Barang yang gagal disalin:</strong> <span class="badge badge-danger">' + totalFailed + '</span></li>';
              resultHtml += '</ul>';
              resultHtml += '</div>';

              if (allLogs.length > 0) {
                resultHtml += '<h6><i class="fas fa-list"></i> Detail Log:</h6>';
                resultHtml += '<div style="max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 5px;">';
                resultHtml += '<ul class="list-unstyled mb-0" style="font-size: 12px;">';
                allLogs.slice(-50).forEach(function(log) {
                  var logClass = log.indexOf('Error') !== -1 ? 'text-danger' : (log.indexOf('Success') !== -1 ? 'text-success' : 'text-info');
                  resultHtml += '<li class="' + logClass + '"><i class="fas fa-circle" style="font-size: 6px;"></i> ' + log + '</li>';
                });
                resultHtml += '</ul>';
                resultHtml += '</div>';
              }

              $('#result-content').html(resultHtml);
              $('#result-card').show();
              $('#progress-log').html('<p class="mb-0 text-success"><i class="fas fa-check-circle"></i> <strong>Sinkronisasi selesai!</strong></p>');
            }
          } else {
            // Error dalam batch
            $('#loading-icon').removeClass('fa-spin');
            $('#btn-sinkronisasi').prop('disabled', false).html('<i class="fas fa-sync"></i> Mulai Sinkronisasi Barang');
            $('#progress-bar').removeClass('bg-success').addClass('bg-danger');
            var errorMsg = (response && response.message) ? response.message : 'Terjadi kesalahan tidak diketahui';
            $('#progress-log').html('<p class="mb-0 text-danger"><i class="fas fa-exclamation-circle"></i> <strong>Error:</strong> ' + errorMsg + '</p>');
            alert('Sinkronisasi gagal: ' + errorMsg);
          }
        },
        error: function(xhr, status, error) {
          $('#loading-icon').removeClass('fa-spin');
          $('#btn-sinkronisasi').prop('disabled', false).html('<i class="fas fa-sync"></i> Mulai Sinkronisasi Barang');
          $('#progress-bar').removeClass('bg-success').addClass('bg-danger').css('width', '100%').text('100%');
          
          var errorMsg = 'Terjadi kesalahan saat memproses request';
          var responseText = '';
          
          try {
            if (xhr.responseText) {
              var trimmedResponse = xhr.responseText.trim();
              if (trimmedResponse.startsWith('{') || trimmedResponse.startsWith('[')) {
                var errorResponse = JSON.parse(trimmedResponse);
                errorMsg = errorResponse.message || errorResponse.error || errorMsg;
              } else {
                responseText = xhr.responseText.substring(0, 500);
                errorMsg = 'Server mengembalikan response yang tidak valid (HTTP ' + xhr.status + ').';
                if (xhr.status === 500) {
                  errorMsg += ' Kemungkinan ada error PHP di server.';
                }
              }
            }
          } catch (e) {
            errorMsg = 'Gagal memparse response dari server: ' + error;
            if (xhr.responseText) {
              responseText = xhr.responseText.substring(0, 500);
            }
          }
          
          $('#progress-log').html(
            '<p class="mb-0 text-danger">' +
            '<i class="fas fa-exclamation-triangle"></i> <strong>Error:</strong> ' + errorMsg + 
            '</p>' +
            (responseText ? '<p class="mb-0 mt-2" style="font-size: 11px; color: #666; word-break: break-all;"><strong>Response Server:</strong><br>' + 
            $('<div>').text(responseText).html() + '</p>' : '')
          );
          
          alert('Terjadi kesalahan: ' + errorMsg + '\n\nStatus: ' + status + ' (HTTP ' + xhr.status + ')\nError: ' + error);
        }
      });
    }
    
    // Mulai proses batch pertama
    processBatch(0);
  });
});
</script>

