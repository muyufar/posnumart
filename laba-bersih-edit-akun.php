<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
include 'aksi/koneksi.php';

if ($levelLogin != "admin" && $levelLogin != "super admin") {
  echo "<script>document.location.href = 'bo';</script>";
  exit;
}

$listCabang = query("SELECT * FROM toko ORDER BY toko_cabang");
$selectedCabang = $_GET['cabang'] ?? $_SESSION['user_cabang'];

// Default periode: bulan ini
$tanggal_awal = $_GET['tanggal_awal'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-t');

// Ambil semua kategori akun untuk cabang yang dipilih
$listAkun = query("SELECT * FROM laba_kategori WHERE cabang = '$selectedCabang' ORDER BY kode_akun, name");

// Group akun by kategori
$akunByKategori = [];
foreach ($listAkun as $akun) {
  $kat = ucfirst($akun['kategori']);
  if (!isset($akunByKategori[$kat])) {
    $akunByKategori[$kat] = [];
  }
  $akunByKategori[$kat][] = $akun;
}

// Ambil data transaksi
$dataTransaksi = [];
$query = "SELECT l.*, 
          lkd.name as debit_nama, lkd.kode_akun as debit_kode,
          lkk.name as kredit_nama, lkk.kode_akun as kredit_kode
          FROM laba l
          LEFT JOIN laba_kategori lkd ON l.akun_debit = lkd.id
          LEFT JOIN laba_kategori lkk ON l.akun_kredit = lkk.id
          WHERE l.cabang = '$selectedCabang'
          AND l.date BETWEEN '$tanggal_awal 00:00:00' AND '$tanggal_akhir 23:59:59'
          ORDER BY l.date DESC, l.created_at DESC";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
  $dataTransaksi[] = $row;
}
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
.edit-row:hover { background-color: #f8f9fa; }
.edit-row.modified { background-color: #fff3cd !important; }
.edit-row.saved { background-color: #d4edda !important; }
.select-akun { 
  font-size: 12px; 
  min-width: 220px;
}
.btn-save-row {
  padding: 2px 8px;
  font-size: 11px;
}
.keterangan-text {
  max-width: 300px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.badge-tipe {
  font-size: 10px;
  padding: 3px 6px;
}

/* Select2 Custom Styling */
.select2-container {
  min-width: 220px !important;
}
.select2-container--default .select2-selection--single {
  height: 31px;
  padding: 2px 6px;
  font-size: 12px;
  border: 1px solid #ced4da;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
  line-height: 26px;
  padding-left: 0;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
  height: 29px;
}
.select2-dropdown {
  font-size: 12px;
}
.select2-results__option {
  padding: 4px 8px;
}
.select2-results__group {
  font-weight: bold;
  color: #007bff;
  padding: 6px 8px;
  background: #f8f9fa;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
  background-color: #007bff;
}
.select2-search--dropdown .select2-search__field {
  padding: 6px 8px;
  font-size: 12px;
}
</style>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1><i class="fas fa-edit"></i> Edit Akun Data Operasional</h1>
          <small class="text-muted">Edit akun debit dan kredit untuk setiap transaksi</small>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="laba-bersih-data">Data Operasional</a></li>
            <li class="breadcrumb-item active">Edit Akun</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      
      <!-- Filter -->
      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-filter"></i> Filter</h3>
        </div>
        <div class="card-body">
          <form method="GET">
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label>Cabang</label>
                  <select class="form-control" name="cabang" <?= $levelLogin != 'super admin' ? 'disabled' : '' ?>>
                    <?php foreach ($listCabang as $cab) : ?>
                      <option value="<?= $cab['toko_cabang'] ?>" <?= $cab['toko_cabang'] == $selectedCabang ? 'selected' : '' ?>>
                        <?= $cab['toko_nama'] ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Tanggal Awal</label>
                  <input type="date" class="form-control" name="tanggal_awal" value="<?= $tanggal_awal ?>">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Tanggal Akhir</label>
                  <input type="date" class="form-control" name="tanggal_akhir" value="<?= $tanggal_akhir ?>">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>&nbsp;</label>
                  <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-search"></i> Tampilkan
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Info -->
      <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> 
        <strong>Petunjuk:</strong> Pilih akun debit dan kredit, lalu klik tombol <strong>"Simpan"</strong> pada setiap baris untuk menyimpan perubahan.
        Atau gunakan tombol <strong>"Simpan Semua"</strong> untuk menyimpan semua perubahan sekaligus.
      </div>

      <!-- Bulk Actions -->
      <div class="card card-success mb-3">
        <div class="card-body py-2">
          <div class="row align-items-center">
            <div class="col-md-6">
              <span class="text-muted">
                Total: <strong><?= count($dataTransaksi) ?></strong> transaksi |
                Diubah: <strong id="count-modified">0</strong>
              </span>
            </div>
            <div class="col-md-6 text-right">
              <button type="button" class="btn btn-success" id="btn-save-all" disabled>
                <i class="fas fa-save"></i> Simpan Semua Perubahan
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Data Table -->
      <div class="card">
        <div class="card-body table-responsive p-0">
          <table class="table table-bordered table-hover table-sm">
            <thead class="thead-light">
              <tr>
                <th style="width: 40px;" class="text-center">#</th>
                <th style="width: 100px;">Tanggal</th>
                <th style="width: 70px;">Jenis</th>
                <th>Keterangan</th>
                <th style="width: 110px;" class="text-right">Nominal</th>
                <th style="width: 220px;">Akun Debit</th>
                <th style="width: 220px;">Akun Kredit</th>
                <th style="width: 80px;" class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($dataTransaksi)) : ?>
                <tr>
                  <td colspan="8" class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                    Tidak ada data untuk periode ini
                  </td>
                </tr>
              <?php else : ?>
                <?php $no = 1; foreach ($dataTransaksi as $trx) : ?>
                  <tr class="edit-row" data-id="<?= htmlspecialchars($trx['id']) ?>">
                    <td class="text-center small"><?= $no++ ?></td>
                    <td class="small"><?= date('d/m/Y', strtotime($trx['date'])) ?></td>
                    <td>
                      <?php if ($trx['tipe'] == 1) : ?>
                        <span class="badge badge-danger badge-tipe">Keluar</span>
                      <?php else : ?>
                        <span class="badge badge-success badge-tipe">Masuk</span>
                      <?php endif; ?>
                    </td>
                    <td class="keterangan-text small" title="<?= htmlspecialchars($trx['keterangan']) ?>">
                      <?= htmlspecialchars($trx['keterangan'] ?: '-') ?>
                    </td>
                    <td class="text-right small">
                      <?= number_format(floatval(str_replace(['.', ','], ['', '.'], $trx['jumlah'])), 0, ',', '.') ?>
                    </td>
                    <td>
                      <select class="form-control form-control-sm select-akun select-debit" 
                              data-original="<?= $trx['akun_debit'] ?>">
                        <option value="">-- Pilih Akun Debit --</option>
                        <?php foreach ($akunByKategori as $kat => $akuns) : ?>
                          <optgroup label="<?= $kat ?>">
                            <?php foreach ($akuns as $akun) : ?>
                              <option value="<?= $akun['id'] ?>" 
                                      <?= $trx['akun_debit'] == $akun['id'] ? 'selected' : '' ?>>
                                <?= $akun['kode_akun'] ?> - <?= $akun['name'] ?>
                              </option>
                            <?php endforeach; ?>
                          </optgroup>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td>
                      <select class="form-control form-control-sm select-akun select-kredit"
                              data-original="<?= $trx['akun_kredit'] ?>">
                        <option value="">-- Pilih Akun Kredit --</option>
                        <?php foreach ($akunByKategori as $kat => $akuns) : ?>
                          <optgroup label="<?= $kat ?>">
                            <?php foreach ($akuns as $akun) : ?>
                              <option value="<?= $akun['id'] ?>" 
                                      <?= $trx['akun_kredit'] == $akun['id'] ? 'selected' : '' ?>>
                                <?= $akun['kode_akun'] ?> - <?= $akun['name'] ?>
                              </option>
                            <?php endforeach; ?>
                          </optgroup>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td class="text-center">
                      <button type="button" class="btn btn-primary btn-sm btn-save-row" title="Simpan">
                        <i class="fas fa-save"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </section>
</div>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
const base_url = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '');

$(document).ready(function() {
  
  // Initialize Select2 with search
  initSelect2();
  
  // Track changes - use event delegation for Select2
  $(document).on('change', '.select-akun', function() {
    const row = $(this).closest('tr');
    const debitSelect = row.find('.select-debit');
    const kreditSelect = row.find('.select-kredit');
    
    // Convert to string for comparison
    const debitOriginal = String(debitSelect.data('original') || '');
    const kreditOriginal = String(kreditSelect.data('original') || '');
    const debitCurrent = String(debitSelect.val() || '');
    const kreditCurrent = String(kreditSelect.val() || '');
    
    if (debitCurrent !== debitOriginal || kreditCurrent !== kreditOriginal) {
      row.addClass('modified').removeClass('saved');
    } else {
      row.removeClass('modified');
    }
    
    updateModifiedCount();
  });
  
  // Save single row
  $(document).on('click', '.btn-save-row', async function() {
    const btn = $(this);
    const row = btn.closest('tr');
    
    await saveRow(row, btn);
  });
  
  // Save all modified rows
  $('#btn-save-all').on('click', async function() {
    const btn = $(this);
    const modifiedRows = $('.edit-row.modified');
    
    if (modifiedRows.length === 0) {
      Swal.fire('Info', 'Tidak ada data yang diubah', 'info');
      return;
    }
    
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
    
    let saved = 0;
    let failed = 0;
    
    for (let i = 0; i < modifiedRows.length; i++) {
      const row = $(modifiedRows[i]);
      const success = await saveRow(row, null, false);
      if (success) saved++;
      else failed++;
    }
    
    btn.prop('disabled', false).html('<i class="fas fa-save"></i> Simpan Semua Perubahan');
    
    Swal.fire({
      icon: failed > 0 ? 'warning' : 'success',
      title: 'Selesai',
      text: `${saved} berhasil${failed > 0 ? `, ${failed} gagal` : ''}`,
      timer: 2000,
      showConfirmButton: false
    });
    
    updateModifiedCount();
  });
});

// Initialize Select2 on all select-akun elements
function initSelect2() {
  $('.select-akun').each(function() {
    $(this).select2({
      placeholder: $(this).hasClass('select-debit') ? '🔍 Cari Akun Debit...' : '🔍 Cari Akun Kredit...',
      allowClear: true,
      width: '100%',
      dropdownAutoWidth: true,
      language: {
        noResults: function() {
          return 'Akun tidak ditemukan';
        },
        searching: function() {
          return 'Mencari...';
        }
      },
      matcher: customMatcher
    });
  });
}

// Custom matcher untuk pencarian yang lebih baik (support optgroup)
function customMatcher(params, data) {
  // Jika tidak ada search term, tampilkan semua
  if ($.trim(params.term) === '') {
    return data;
  }
  
  // Jika data tidak punya text, skip
  if (typeof data.text === 'undefined') {
    return null;
  }
  
  const searchTerm = params.term.toLowerCase().trim();
  
  // Jika ini adalah optgroup (memiliki children)
  if (data.children && data.children.length > 0) {
    // Filter children yang cocok
    const filteredChildren = [];
    
    for (let i = 0; i < data.children.length; i++) {
      const child = data.children[i];
      const childText = (child.text || '').toLowerCase();
      
      // Cek apakah text mengandung search term
      if (childText.indexOf(searchTerm) > -1) {
        filteredChildren.push(child);
        continue;
      }
      
      // Cek pencarian per kata (pisahkan dengan spasi)
      const searchWords = searchTerm.split(/\s+/);
      let allWordsMatch = true;
      
      for (let j = 0; j < searchWords.length; j++) {
        if (searchWords[j] && childText.indexOf(searchWords[j]) === -1) {
          allWordsMatch = false;
          break;
        }
      }
      
      if (allWordsMatch && searchWords.length > 1) {
        filteredChildren.push(child);
        continue;
      }
      
      // Cek pencarian kode akun (format: X-XXXX)
      // Misal: "6-11" harus match "6-1100", "6-1150", dll
      if (/^[\d-]+$/.test(searchTerm)) {
        // Extract kode dari text (biasanya format "KODE - NAMA")
        const kodeMatch = childText.match(/^([\d]+-[\d]+)/);
        if (kodeMatch) {
          const kode = kodeMatch[1];
          if (kode.indexOf(searchTerm) === 0 || kode.indexOf(searchTerm) > -1) {
            filteredChildren.push(child);
          }
        }
      }
    }
    
    // Jika ada children yang cocok, return modified data dengan filtered children
    if (filteredChildren.length > 0) {
      const modifiedData = $.extend({}, data, true);
      modifiedData.children = filteredChildren;
      return modifiedData;
    }
    
    return null;
  }
  
  // Untuk item biasa (bukan optgroup)
  const text = data.text.toLowerCase();
  
  // Cek apakah text mengandung search term
  if (text.indexOf(searchTerm) > -1) {
    return data;
  }
  
  // Cek pencarian per kata
  const searchWords = searchTerm.split(/\s+/);
  let allWordsMatch = true;
  
  for (let i = 0; i < searchWords.length; i++) {
    if (searchWords[i] && text.indexOf(searchWords[i]) === -1) {
      allWordsMatch = false;
      break;
    }
  }
  
  if (allWordsMatch && searchWords.length > 1) {
    return data;
  }
  
  // Cek pencarian kode akun
  if (/^[\d-]+$/.test(searchTerm)) {
    const kodeMatch = text.match(/^([\d]+-[\d]+)/);
    if (kodeMatch) {
      const kode = kodeMatch[1];
      if (kode.indexOf(searchTerm) === 0 || kode.indexOf(searchTerm) > -1) {
        return data;
      }
    }
  }
  
  return null;
}

function updateModifiedCount() {
  const count = $('.edit-row.modified').length;
  $('#count-modified').text(count);
  $('#btn-save-all').prop('disabled', count === 0);
}

async function saveRow(row, btn, showAlert = true) {
  const id = row.attr('data-id');
  const debit = row.find('.select-debit').val();
  const kredit = row.find('.select-kredit').val();
  
  if (btn) {
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'update_akun');
    formData.append('id', id);
    formData.append('akun_debit', debit || '');
    formData.append('akun_kredit', kredit || '');
    
    const response = await fetch(base_url + '/api/laba.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
      row.removeClass('modified').addClass('saved');
      
      // Update original values
      row.find('.select-debit').data('original', debit);
      row.find('.select-kredit').data('original', kredit);
      
      if (showAlert) {
        // Show quick toast
        Swal.fire({
          icon: 'success',
          title: 'Tersimpan!',
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 1500
        });
      }
      
      if (btn) {
        btn.prop('disabled', false).html('<i class="fas fa-check text-success"></i>');
        setTimeout(() => btn.html('<i class="fas fa-save"></i>'), 2000);
      }
      
      updateModifiedCount();
      return true;
    } else {
      throw new Error(result.message || 'Gagal menyimpan');
    }
  } catch (err) {
    console.error('Save error:', err);
    
    if (showAlert) {
      Swal.fire('Error', err.message, 'error');
    }
    
    if (btn) {
      btn.prop('disabled', false).html('<i class="fas fa-times text-danger"></i>');
      setTimeout(() => btn.html('<i class="fas fa-save"></i>'), 2000);
    }
    
    return false;
  }
}
</script>

<?php include '_footer.php'; ?>

