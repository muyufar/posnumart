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

// Keyword mapping untuk rekomendasi akun berdasarkan keterangan
// Menggunakan kode_akun untuk pencocokan yang lebih akurat
$keywordMapping = [
  // BEBAN OPERASI - dengan kode akun spesifik
  ['keywords' => ['listrik', 'pln', 'token'], 'kode_akun' => '6-1100', 'nama' => 'Beban Listrik'],
  ['keywords' => ['wifi', 'internet', 'telp', 'telepon', 'indihome', 'speedy', 'pulsa'], 'kode_akun' => '6-1110', 'nama' => 'Beban Wifi Internet Telp'],
  ['keywords' => ['air', 'pdam'], 'kode_akun' => '6-1120', 'nama' => 'Beban Air'],
  ['keywords' => ['atk', 'alat tulis', 'kertas', 'pulpen', 'tinta', 'printer', 'amplop', 'map'], 'kode_akun' => '6-1130', 'nama' => 'Beban Perlengkapan Toko(ATK)'],
  ['keywords' => ['angkut', 'angkutan'], 'kode_akun' => '6-1140', 'nama' => 'Beban Angkut Pembelian'],
  ['keywords' => ['ongkir', 'kirim', 'ekspedisi', 'jne', 'jnt', 'sicepat', 'kurir', 'logistik', 'bensin', 'bbm', 'solar', 'pertamax', 'pertalite', 'mobil', 'motor', 'transportasi'], 'kode_akun' => '6-1150', 'nama' => 'Beban Logistik-Kirim Barang'],
  ['keywords' => ['sampah', 'jimpitan', 'iuran', 'kebersihan', 'rt', 'rw'], 'kode_akun' => '6-1160', 'nama' => 'Beban Iuran Sampah & Jimpitan'],
  ['keywords' => ['keranjang', 'plastik', 'kantong', 'label', 'stiker', 'price tag', 'kebutuhan toko', 'kresek', 'sedotan', 'tissue', 'sabun', 'pembersih'], 'kode_akun' => '6-1170', 'nama' => 'Beban Kebutuhan Toko'],
  ['keywords' => ['gaji', 'upah', 'honor', 'salary', 'thr', 'bonus', 'insentif'], 'kode_akun' => '6-1200', 'nama' => 'Beban Gaji&Upah Pokok'],
  ['keywords' => ['makan', 'snack', 'konsumsi', 'lunch', 'dinner', 'nasi', 'minuman', 'kopi', 'teh'], 'kode_akun' => '6-1170', 'nama' => 'Beban Kebutuhan Toko'],
  ['keywords' => ['parkir', 'tol', 'retribusi'], 'kode_akun' => '6-1150', 'nama' => 'Beban Logistik-Kirim Barang'],
  ['keywords' => ['sewa', 'kontrak', 'rent'], 'kode_akun' => '6-1800', 'nama' => 'Beban Sewa'],
  ['keywords' => ['perbaikan', 'service', 'maintenance', 'reparasi', 'servis'], 'kode_akun' => '6-1170', 'nama' => 'Beban Kebutuhan Toko'],
  
  // KAS - dengan kode akun spesifik
  ['keywords' => ['tunai', 'cash', 'kas tunai'], 'kode_akun' => '1-1100', 'nama' => 'Kas Tunai'],
  ['keywords' => ['bri', 'bank bri'], 'kode_akun' => '1-1152', 'nama' => 'Kas Bank BRI'],
  ['keywords' => ['bca', 'bank bca'], 'kode_akun' => '1-1153', 'nama' => 'Kas Bank BCA'],
  ['keywords' => ['bnu', 'bank bnu'], 'kode_akun' => '1-1151', 'nama' => 'Kas Bank BNU'],
  ['keywords' => ['transfer', 'tf', 'setor'], 'kode_akun' => '1-1150', 'nama' => 'Kas di Bank'],
  
  // HUTANG
  ['keywords' => ['hutang', 'supplier', 'pemasok', 'utang'], 'kode_akun' => '2-1100', 'nama' => 'Hutang Dagang'],
  ['keywords' => ['hutang bank', 'pinjaman bank', 'kredit bank'], 'kode_akun' => '2-2100', 'nama' => 'Hutang Bank'],
  
  // PIUTANG
  ['keywords' => ['piutang', 'tagihan', 'belum bayar', 'bon'], 'kode_akun' => '1-1300', 'nama' => 'Piutang Dagang'],
  
  // PENDAPATAN
  ['keywords' => ['jual', 'penjualan', 'omset', 'pendapatan', 'sales'], 'kode_akun' => '4-1000', 'nama' => 'Penjualan Barang Dagangan'],
  
  // MODAL
  ['keywords' => ['modal', 'investasi', 'setoran modal'], 'kode_akun' => '3-1100', 'nama' => 'MODAL NU MART'],
  
  // PERSEDIAAN
  ['keywords' => ['beli barang', 'stok', 'persediaan', 'pembelian barang'], 'kode_akun' => '1-1500', 'nama' => 'Persediaan Barang Dagangan'],
  
  // PERLENGKAPAN/PERALATAN
  ['keywords' => ['komputer', 'laptop', 'pc', 'printer'], 'kode_akun' => '1-2300', 'nama' => 'Peralatan & Komputer Toko'],
  ['keywords' => ['rak', 'etalase', 'meja', 'kursi', 'furniture', 'lemari'], 'kode_akun' => '1-2100', 'nama' => 'Furniture'],
];

// Fungsi untuk mendapatkan rekomendasi akun berdasarkan keterangan
function getRecommendedAccount($keterangan, $listAkun, $keywordMapping, $tipe) {
  $keterangan = strtolower($keterangan);
  
  foreach ($keywordMapping as $map) {
    foreach ($map['keywords'] as $keyword) {
      if (strpos($keterangan, $keyword) !== false) {
        // Cari akun yang cocok
        foreach ($listAkun as $akun) {
          if (stripos($akun['name'], $map['nama']) !== false || 
              ($map['kategori'] && strtolower($akun['kategori']) == $map['kategori'] && stripos($akun['name'], $map['nama']) !== false)) {
            return $akun;
          }
        }
        // Jika tidak exact match, cari berdasarkan kategori
        foreach ($listAkun as $akun) {
          if (strtolower($akun['kategori']) == $map['kategori']) {
            $namaParts = explode(' ', strtolower($map['nama']));
            foreach ($namaParts as $part) {
              if (strlen($part) > 3 && stripos($akun['name'], $part) !== false) {
                return $akun;
              }
            }
          }
        }
      }
    }
  }
  
  return null;
}

// Fungsi mendapatkan default kas tunai untuk cabang
function getDefaultKas($listAkun, $cabangNama = '') {
  // Prioritas: Kas dengan nama cabang > Kas Tunai umum
  foreach ($listAkun as $akun) {
    if (stripos($akun['name'], 'Kas Tunai') !== false) {
      return $akun;
    }
  }
  foreach ($listAkun as $akun) {
    if (stripos($akun['name'], 'Kas') !== false && strtolower($akun['kategori']) == 'aktiva') {
      return $akun;
    }
  }
  return null;
}

$defaultKas = getDefaultKas($listAkun);
?>

<style>
  .mapping-row:hover {
    background-color: #f8f9fa;
  }
  .mapping-row.edited {
    background-color: #fff3cd;
  }
  .mapping-row.saved {
    background-color: #d4edda;
  }
  .select-akun {
    font-size: 12px;
    padding: 2px 5px;
    height: auto;
  }
  .keterangan-cell {
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .badge-rekomendasi {
    font-size: 10px;
    cursor: pointer;
  }
  .sticky-header {
    position: sticky;
    top: 0;
    z-index: 10;
  }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1><i class="fas fa-magic"></i> Sinkronisasi Akun Transaksi</h1>
          <small class="text-muted">Sistem akan merekomendasikan akun berdasarkan keterangan transaksi</small>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Sinkronisasi Akun</li>
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
          <h3 class="card-title"><i class="fas fa-filter"></i> Filter Data</h3>
        </div>
        <div class="card-body">
          <form method="GET" id="filter-form">
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label>Cabang</label>
                  <select class="form-control" name="cabang" id="filter-cabang" <?= $levelLogin != 'super admin' ? 'disabled' : '' ?>>
                    <?php foreach ($listCabang as $cab) : ?>
                      <option value="<?= $cab['toko_cabang'] ?>" <?= $cab['toko_cabang'] == $selectedCabang ? 'selected' : '' ?>>
                        <?= $cab['toko_nama'] ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label>Tanggal Awal</label>
                  <input type="date" class="form-control" name="tanggal_awal" value="<?= $tanggal_awal ?>">
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label>Tanggal Akhir</label>
                  <input type="date" class="form-control" name="tanggal_akhir" value="<?= $tanggal_akhir ?>">
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label>Filter</label>
                  <select class="form-control" name="filter_status" id="filter-status">
                    <option value="">Semua</option>
                    <option value="belum">Belum Ada Akun</option>
                    <option value="sudah">Sudah Ada Akun</option>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>&nbsp;</label>
                  <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-search"></i> Muat Data
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Info Box -->
      <div class="callout callout-info">
        <h5><i class="fas fa-robot"></i> Cara Kerja Rekomendasi AI</h5>
        <p>
          Sistem akan menganalisis <strong>keterangan</strong> setiap transaksi dan merekomendasikan akun yang sesuai.
          <br>
          <span class="badge badge-success">Hijau</span> = Rekomendasi sistem
          <span class="badge badge-warning">Kuning</span> = Sudah diedit
          <span class="badge badge-secondary">Abu-abu</span> = Belum ada rekomendasi
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="card card-primary">
        <div class="card-body">
          <div class="row">
            <div class="col-md-3">
              <button type="button" class="btn btn-success btn-block" id="btn-apply-all">
                <i class="fas fa-magic"></i> Terapkan Rekomendasi
              </button>
            </div>
            <div class="col-md-3">
              <button type="button" class="btn btn-primary btn-block" id="btn-save-all">
                <i class="fas fa-save"></i> Simpan Perubahan
              </button>
            </div>
            <div class="col-md-3">
              <button type="button" class="btn btn-info btn-block" id="btn-test-save">
                <i class="fas fa-bug"></i> Test Simpan 1 Data
              </button>
            </div>
            <div class="col-md-3">
              <button type="button" class="btn btn-warning btn-block" id="btn-reset-all">
                <i class="fas fa-undo"></i> Reset
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Data Table -->
      <div class="card card-outline card-info">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-table"></i> Data Transaksi Operasional</h3>
          <div class="card-tools">
            <span class="badge badge-info" id="count-total">0</span> Total |
            <span class="badge badge-warning" id="count-edited">0</span> Diedit |
            <span class="badge badge-success" id="count-saved">0</span> Tersimpan
          </div>
        </div>
        <div class="card-body table-responsive p-0" style="max-height: 600px; overflow-y: auto;">
          <table class="table table-bordered table-hover table-sm">
            <thead class="thead-dark sticky-header">
              <tr>
                <th style="width: 40px;">
                  <input type="checkbox" id="check-all" title="Pilih Semua">
                </th>
                <th style="width: 100px;">Tanggal</th>
                <th style="width: 80px;">Jenis</th>
                <th style="width: 250px;">Keterangan</th>
                <th style="width: 100px;">Nominal</th>
                <th style="width: 200px;">Akun Debit</th>
                <th style="width: 200px;">Akun Kredit</th>
                <th style="width: 80px;">Status</th>
              </tr>
            </thead>
            <tbody id="table-body">
              <tr>
                <td colspan="8" class="text-center text-muted">
                  <i class="fas fa-info-circle"></i> Klik "Muat Data" untuk menampilkan transaksi
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </section>
</div>

<script>
const base_url = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '');
const listAkun = <?= json_encode($listAkun) ?>;
const defaultKasId = <?= $defaultKas ? $defaultKas['id'] : 'null' ?>;
let originalData = [];
let editedRows = new Set();

// Keyword mapping untuk rekomendasi
const keywordMapping = <?= json_encode($keywordMapping) ?>;

$(document).ready(function() {
  loadData();
  
  // Check all
  $('#check-all').on('change', function() {
    $('.row-check').prop('checked', $(this).is(':checked'));
  });
  
  // Apply all recommendations
  $('#btn-apply-all').on('click', function() {
    $('.row-check:checked').each(function() {
      const row = $(this).closest('tr');
      const id = row.data('id');
      
      // Get recommendations
      const recDebit = row.find('.rec-debit').data('id');
      const recKredit = row.find('.rec-kredit').data('id');
      
      if (recDebit) {
        row.find('.select-debit').val(recDebit);
        markAsEdited(row);
      }
      if (recKredit) {
        row.find('.select-kredit').val(recKredit);
        markAsEdited(row);
      }
    });
    updateCounts();
    
    Swal.fire({
      icon: 'success',
      title: 'Rekomendasi Diterapkan',
      text: 'Silakan review dan klik "Simpan Semua Perubahan"',
      timer: 2000,
      showConfirmButton: false
    });
  });
  
  // ========== NEW SAVE LOGIC ==========
  
  // Save all changes - RECONSTRUCTED
  $('#btn-save-all').on('click', async function() {
    // Langsung ambil semua row yang memiliki class 'edited'
    const editedRowsElements = $('.mapping-row.edited');
    
    if (editedRowsElements.length === 0) {
      Swal.fire({
        icon: 'info',
        title: 'Tidak Ada Perubahan',
        text: 'Edit data terlebih dahulu dengan memilih akun'
      });
      return;
    }
    
    // Konfirmasi
    const confirm = await Swal.fire({
      title: 'Simpan Perubahan?',
      text: `${editedRowsElements.length} transaksi akan diupdate`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Ya, Simpan!',
      cancelButtonText: 'Batal'
    });
    
    if (!confirm.isConfirmed) return;
    
    // Disable button
    const $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
    
    let saved = 0;
    let failed = 0;
    
    // Process each edited row
    for (let i = 0; i < editedRowsElements.length; i++) {
      const row = $(editedRowsElements[i]);
      const id = row.attr('data-id'); // Gunakan attr() bukan data()
      const debit = row.find('.select-debit').val();
      const kredit = row.find('.select-kredit').val();
      
      if (!debit && !kredit) continue;
      
      try {
        const response = await saveOneTransaction(id, debit, kredit);
        if (response.success) {
          saved++;
          row.removeClass('edited').addClass('saved');
        } else {
          failed++;
          console.error('Save failed:', response.message);
        }
      } catch (err) {
        failed++;
        console.error('Save error:', err);
      }
      
      // Update counter
      $('#count-saved').text(saved);
    }
    
    // Re-enable button
    $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Simpan Perubahan');
    
    // Show result
    Swal.fire({
      icon: failed > 0 ? 'warning' : 'success',
      title: 'Selesai',
      text: `${saved} berhasil disimpan${failed > 0 ? `, ${failed} gagal` : ''}`,
      timer: 2000,
      showConfirmButton: false
    });
    
    // Clear edited tracking
    editedRows.clear();
    updateCounts();
  });
  
  // Helper function untuk save 1 transaksi
  function saveOneTransaction(id, debit, kredit) {
    return new Promise((resolve, reject) => {
      const formData = new FormData();
      formData.append('action', 'update_akun');
      formData.append('id', id);
      formData.append('akun_debit', debit || '');
      formData.append('akun_kredit', kredit || '');
      
      fetch(base_url + '/api/laba.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => resolve(data))
      .catch(err => reject(err));
    });
  }
  
  // Test save one data
  $('#btn-test-save').on('click', async function() {
    let testRow = $('.mapping-row.edited').first();
    if (testRow.length === 0) {
      testRow = $('.mapping-row').first();
    }
    
    if (testRow.length === 0) {
      alert('Tidak ada data untuk ditest');
      return;
    }
    
    const id = testRow.attr('data-id');
    const debit = testRow.find('.select-debit').val();
    const kredit = testRow.find('.select-kredit').val();
    
    if (!debit && !kredit) {
      alert('Pilih akun debit atau kredit dulu pada baris pertama');
      return;
    }
    
    try {
      const response = await saveOneTransaction(id, debit, kredit);
      alert('Response:\n' + JSON.stringify(response, null, 2));
    } catch (err) {
      alert('Error: ' + err.message);
    }
  });

  // Reset all
  $('#btn-reset-all').on('click', function() {
    if (editedRows.size === 0) return;
    
    Swal.fire({
      title: 'Reset Perubahan?',
      text: 'Semua perubahan yang belum disimpan akan hilang',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Ya, Reset!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        loadData();
        editedRows.clear();
        updateCounts();
      }
    });
  });
});

function loadData() {
  const cabang = $('#filter-cabang').val();
  const tanggalAwal = $('input[name="tanggal_awal"]').val();
  const tanggalAkhir = $('input[name="tanggal_akhir"]').val();
  const filterStatus = $('#filter-status').val();
  
  $('#table-body').html('<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Memuat data...</td></tr>');
  
  $.ajax({
    url: base_url + '/api/laba.php',
    method: 'GET',
    data: {
      cabang: cabang,
      date_start: tanggalAwal,
      date_end: tanggalAkhir,
      per_page: 500 // Get more data
    },
    dataType: 'json',
    success: function(response) {
      if (response.success && response.data && response.data.data) {
        originalData = response.data.data;
        renderTable(originalData, filterStatus);
      } else {
        $('#table-body').html('<tr><td colspan="8" class="text-center text-muted">Tidak ada data</td></tr>');
      }
      updateCounts();
    },
    error: function(err) {
      console.error(err);
      $('#table-body').html('<tr><td colspan="8" class="text-center text-danger">Error memuat data</td></tr>');
    }
  });
}

function renderTable(data, filterStatus) {
  let html = '';
  let count = 0;
  
  data.forEach((item, index) => {
    // Filter by status
    const hasAkun = item.akun_debit || item.akun_kredit;
    if (filterStatus === 'belum' && hasAkun) return;
    if (filterStatus === 'sudah' && !hasAkun) return;
    
    count++;
    
    // Get recommendations based on keterangan
    const recDebit = getRecommendation(item.keterangan, item.tipe, 'debit');
    const recKredit = getRecommendation(item.keterangan, item.tipe, 'kredit');
    
    // Current values
    const currentDebit = item.akun_debit || item.kategori?.id || '';
    const currentKredit = item.akun_kredit || '';
    
    html += `
      <tr class="mapping-row" data-id="${item.id}" data-original='${JSON.stringify({debit: currentDebit, kredit: currentKredit})}'>
        <td class="text-center">
          <input type="checkbox" class="row-check">
        </td>
        <td class="small">${item.date || '-'}</td>
        <td>
          <span class="badge badge-${item.tipe == 1 ? 'danger' : 'success'}">
            ${item.tipe == 1 ? 'Keluar' : 'Masuk'}
          </span>
        </td>
        <td class="keterangan-cell small" title="${escapeHtml(item.keterangan || '-')}">
          ${escapeHtml(item.keterangan || '-')}
        </td>
        <td class="text-right small">${formatRupiah(item.jumlah)}</td>
        <td>
          <select class="form-control form-control-sm select-akun select-debit" data-field="akun_debit">
            <option value="">-- Pilih --</option>
            ${renderAkunOptions(currentDebit)}
          </select>
          ${recDebit ? `<span class="badge badge-success badge-rekomendasi rec-debit mt-1" data-id="${recDebit.id}" onclick="applyRec(this, 'debit')" title="Klik untuk terapkan">${recDebit.kode_akun}</span>` : '<span class="badge badge-secondary badge-rekomendasi rec-debit mt-1" data-id="">-</span>'}
        </td>
        <td>
          <select class="form-control form-control-sm select-akun select-kredit" data-field="akun_kredit">
            <option value="">-- Pilih --</option>
            ${renderAkunOptions(currentKredit)}
          </select>
          ${recKredit ? `<span class="badge badge-success badge-rekomendasi rec-kredit mt-1" data-id="${recKredit.id}" onclick="applyRec(this, 'kredit')" title="Klik untuk terapkan">${recKredit.kode_akun}</span>` : '<span class="badge badge-secondary badge-rekomendasi rec-kredit mt-1" data-id="">-</span>'}
        </td>
        <td class="text-center">
          <span class="status-badge badge badge-${hasAkun ? 'success' : 'secondary'}">
            ${hasAkun ? '<i class="fas fa-check"></i>' : '<i class="fas fa-minus"></i>'}
          </span>
        </td>
      </tr>
    `;
  });
  
  if (count === 0) {
    html = '<tr><td colspan="8" class="text-center text-muted">Tidak ada data sesuai filter</td></tr>';
  }
  
  $('#table-body').html(html);
  $('#count-total').text(count);
  
  // Add change listeners
  $('.select-akun').on('change', function() {
    const row = $(this).closest('tr');
    markAsEdited(row);
    updateCounts();
  });
}

function getRecommendation(keterangan, tipe, type) {
  if (!keterangan) return null;
  
  const ket = keterangan.toLowerCase();
  
  // Helper: cari akun berdasarkan kode_akun
  const findAkunByKode = (kodeAkun) => {
    for (let akun of listAkun) {
      if (akun.kode_akun === kodeAkun) {
        return akun;
      }
    }
    // Coba partial match (awalan)
    for (let akun of listAkun) {
      if (akun.kode_akun && akun.kode_akun.startsWith(kodeAkun.split('-')[0])) {
        if (akun.kode_akun.includes(kodeAkun.split('-')[1])) {
          return akun;
        }
      }
    }
    return null;
  };
  
  // Helper: cari Kas Tunai
  const findKasTunai = () => {
    // Prioritas 1: Cari dengan kode 1-1100
    let kas = findAkunByKode('1-1100');
    if (kas) return kas;
    
    // Prioritas 2: Cari yang namanya "Kas Tunai"
    for (let akun of listAkun) {
      if (akun.name && akun.name.toLowerCase().includes('kas tunai')) {
        return akun;
      }
    }
    
    // Prioritas 3: Cari yang kategori aktiva dan nama mengandung "kas"
    for (let akun of listAkun) {
      if (akun.kategori && akun.kategori.toLowerCase() === 'aktiva' && 
          akun.name && akun.name.toLowerCase().includes('kas')) {
        return akun;
      }
    }
    return null;
  };
  
  // =====================================================
  // PENGELUARAN (tipe == 1)
  // =====================================================
  if (tipe == 1) {
    // KREDIT: Biasanya dari Kas Tunai
    if (type === 'kredit') {
      return findKasTunai();
    }
    
    // DEBIT: Cari berdasarkan keyword di keterangan
    if (type === 'debit') {
      // Loop semua mapping dan cari keyword yang cocok
      for (let map of keywordMapping) {
        for (let keyword of map.keywords) {
          if (ket.includes(keyword.toLowerCase())) {
            // Ditemukan keyword! Cari akun dengan kode_akun
            const akun = findAkunByKode(map.kode_akun);
            if (akun) {
              console.log(`Match: "${keyword}" in "${keterangan}" → ${map.kode_akun} ${map.nama}`);
              return akun;
            }
          }
        }
      }
      
      // Fallback: Cari akun beban apapun
      for (let akun of listAkun) {
        if (akun.kategori && akun.kategori.toLowerCase() === 'beban' && 
            akun.kode_akun && akun.kode_akun.startsWith('6-')) {
          return akun;
        }
      }
    }
  }
  
  // =====================================================
  // PEMASUKAN (tipe == 0)
  // =====================================================
  if (tipe == 0) {
    // DEBIT: Biasanya ke Kas Tunai
    if (type === 'debit') {
      return findKasTunai();
    }
    
    // KREDIT: Dari Pendapatan/Penjualan
    if (type === 'kredit') {
      // Prioritas 1: Cari dengan kode 4-1000
      let akun = findAkunByKode('4-1000');
      if (akun) return akun;
      
      // Prioritas 2: Cari kategori pendapatan
      for (let akun of listAkun) {
        if (akun.kategori && akun.kategori.toLowerCase() === 'pendapatan') {
          return akun;
        }
      }
    }
  }
  
  return null;
}

function renderAkunOptions(selectedId) {
  let html = '';
  listAkun.forEach(akun => {
    const selected = akun.id == selectedId ? 'selected' : '';
    html += `<option value="${akun.id}" ${selected}>${akun.kode_akun} - ${akun.name}</option>`;
  });
  return html;
}

function applyRec(el, type) {
  const $el = $(el);
  const id = $el.data('id');
  if (!id) return;
  
  const row = $el.closest('tr');
  if (type === 'debit') {
    row.find('.select-debit').val(id);
  } else {
    row.find('.select-kredit').val(id);
  }
  markAsEdited(row);
  updateCounts();
}

function markAsEdited(row) {
  const id = row.data('id');
  row.addClass('edited');
  editedRows.add(id);
}

function updateCounts() {
  $('#count-edited').text(editedRows.size);
}

// Old saveAllChanges removed - now using async/await in button click handler

function formatRupiah(num) {
  if (!num) return 'Rp 0';
  return 'Rp ' + parseInt(num).toLocaleString('id-ID');
}

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}
</script>

<?php include '_footer.php'; ?>
