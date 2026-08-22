<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
  error_reporting(0);

  $cabangCetakLabel = (int) ($sessionCabang ?? 0);
  $urlSearchBarang = numart_url('aksi/search-barang.php');
  $urlBarangBarcode = numart_url('aksi/get-barang-by-barcode.php');
  $urlExportPdf = numart_url('cetak-label-pdf');
  $urlExportExcel = numart_url('cetak-label-excel');

  $daftarKategoriLabel = query("
    SELECT kategori_id, kategori_nama
    FROM kategori
    WHERE kategori_status > 0 AND kategori_cabang = 0
    ORDER BY kategori_nama ASC
  ");
  if (!is_array($daftarKategoriLabel)) {
    $daftarKategoriLabel = [];
  }

  $daftarSuplierLabel = [];
  $resSupLabel = mysqli_query($conn, "
    SELECT DISTINCT TRIM(kode_suplier) AS kode_suplier
    FROM barang
    WHERE barang_cabang = {$cabangCetakLabel}
      AND barang_status = '1'
      AND IFNULL(TRIM(kode_suplier), '') != ''
    ORDER BY kode_suplier ASC
  ");
  if ($resSupLabel) {
    while ($rowSupLabel = mysqli_fetch_assoc($resSupLabel)) {
      $kodeSup = trim((string) ($rowSupLabel['kode_suplier'] ?? ''));
      if ($kodeSup !== '') {
        $daftarSuplierLabel[] = $kodeSup;
      }
    }
  }
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

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Cetak Label Harga</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Cetak Label</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="row">
      <!-- Input Barcode -->
      <div class="col-md-4">
        <div class="card">
          <div class="card-header bg-primary">
            <h3 class="card-title">Scan / Input Barcode</h3>
          </div>
          <div class="card-body">
            <div class="form-group">
              <label>Barcode / Kode Barang</label>
              <input type="text" class="form-control form-control-lg" id="input-barcode" 
                     placeholder="Scan atau ketik barcode..." autofocus>
              <small class="text-muted">Tekan Enter setelah scan/input</small>
            </div>
            
            <div class="form-group">
              <label>Jumlah Label</label>
              <input type="number" class="form-control" id="input-jumlah" value="1" min="1" max="100">
            </div>
            
            <button class="btn btn-primary btn-block" id="btn-tambah-manual">
              <i class="fas fa-search"></i> Cari Barang Manual
            </button>
            
            <hr>
            
            <div class="form-group">
              <label>Layout Cetak</label>
              <div class="row">
                <div class="col-6">
                  <label class="small text-muted mb-0">Kolom</label>
                  <select class="form-control" id="input-kolom">
                    <option value="3">3 kolom</option>
                    <option value="4" selected>4 kolom</option>
                    <option value="6">6 kolom</option>
                  </select>
                </div>
                <div class="col-6">
                  <label class="small text-muted mb-0">Baris / halaman</label>
                  <input type="number" class="form-control" id="input-baris" value="10" min="1" max="30">
                </div>
              </div>
              <small class="text-muted">
                Contoh template: 4 kolom × 10 baris, atau 6 kolom × 21 baris.
              </small>
            </div>
            
            <div class="alert alert-info">
              <strong>Tips:</strong><br>
              • Scan barcode dengan scanner<br>
              • Atau ketik kode barang<br>
              • Tekan Enter untuk menambah<br>
              • Set jumlah label per item<br>
              • Atur kolom/baris sebelum export
            </div>
          </div>
        </div>
      </div>
      
      <!-- Daftar Label -->
      <div class="col-md-8">
        <div class="card">
          <div class="card-header bg-success">
            <h3 class="card-title">Daftar Label yang Akan Dicetak</h3>
            <div class="card-tools">
              <button class="btn btn-sm btn-light" id="btn-clear-all">
                <i class="fas fa-trash"></i> Hapus Semua
              </button>
            </div>
          </div>
          <div class="card-body">
            <div id="label-list" class="mb-3">
              <div class="alert alert-warning text-center">
                <i class="fas fa-barcode fa-3x mb-2"></i><br>
                Belum ada label. Mulai scan barcode atau pilih barang.
              </div>
            </div>
            
            <div id="action-buttons" style="display: none;">
              <button class="btn btn-success btn-lg btn-block" id="btn-preview">
                <i class="fas fa-eye"></i> Preview Label
              </button>
              <button class="btn btn-primary btn-lg btn-block" id="btn-export-pdf">
                <i class="fas fa-file-pdf"></i> Export ke PDF (F4)
              </button>
              <button class="btn btn-success btn-lg btn-block" id="btn-export-excel">
                <i class="fas fa-file-excel"></i> Export ke Excel
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Modal Cari Barang -->
<div class="modal fade" id="modalCariBarang" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary">
        <h5 class="modal-title">Cari Barang</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="row mb-2">
          <div class="col-md-6">
            <label for="filter-kategori-label" class="mb-1">Kategori</label>
            <select id="filter-kategori-label" class="form-control" style="width:100%;">
              <option value="">Semua Kategori</option>
              <?php foreach ($daftarKategoriLabel as $kat) :
                  $kid = (int) ($kat['kategori_id'] ?? 0);
                  if ($kid < 1) {
                      continue;
                  }
                  ?>
                <option value="<?= $kid; ?>">
                  <?= htmlspecialchars((string) ($kat['kategori_nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="filter-suplier-label" class="mb-1">Supplier</label>
            <select id="filter-suplier-label" class="form-control" style="width:100%;">
              <option value="">Semua Supplier</option>
              <?php foreach ($daftarSuplierLabel as $kodeSup) : ?>
                <option value="<?= htmlspecialchars($kodeSup, ENT_QUOTES, 'UTF-8'); ?>">
                  <?= htmlspecialchars($kodeSup, ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="row mb-2">
          <div class="col-md-9">
            <label for="search-barang" class="mb-1">Cari nama / kode</label>
            <input type="text" class="form-control" id="search-barang" placeholder="Cari nama atau kode barang...">
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button type="button" class="btn btn-outline-secondary btn-block" id="btn-reset-filter-label">
              <i class="fa fa-undo"></i> Reset
            </button>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <small class="text-muted" id="info-hasil-pencarian">Pilih filter atau ketik minimal 2 karakter</small>
          <button type="button" class="btn btn-sm btn-success" id="btn-tambah-semua-hasil" style="display:none;">
            <i class="fas fa-plus"></i> Tambah semua hasil
          </button>
        </div>
        <div id="hasil-pencarian" style="max-height: 400px; overflow-y: auto;">
          <p class="text-center text-muted">Pilih kategori/supplier atau ketik untuk mencari barang...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Preview -->
<div class="modal fade" id="modalPreview" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-info">
        <h5 class="modal-title">Preview Label Harga</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body" id="preview-content" style="background: #f0f0f0; padding: 20px;">
        <!-- Preview akan diisi via JavaScript -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-success" id="btn-export-excel-from-preview">
          <i class="fas fa-file-excel"></i> Export Excel
        </button>
        <button type="button" class="btn btn-primary" id="btn-export-from-preview">
          <i class="fas fa-file-pdf"></i> Export ke PDF
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.label-item {
  border: 1px solid #ddd;
  padding: 10px;
  margin-bottom: 10px;
  border-radius: 5px;
  background: white;
}

.label-item:hover {
  background: #f8f9fa;
}

.label-preview {
  width: 100%;
  background: white;
  box-shadow: 0 0 10px rgba(0,0,0,0.1);
  page-break-after: always;
}

.label-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  padding: 10px;
}

.label-grid.cols-3 { grid-template-columns: repeat(3, 1fr); }
.label-grid.cols-4 { grid-template-columns: repeat(4, 1fr); }
.label-grid.cols-6 { grid-template-columns: repeat(6, 1fr); }

.label-card {
  border: 1.5px solid #000;
  padding: 6px;
  text-align: center;
  background: white;
  min-height: 100px;
  display: flex;
  flex-direction: column;
  position: relative;
}

.label-grid.cols-6 .label-card {
  min-height: 85px;
  padding: 4px;
}

.label-grid.cols-6 .harga-utama { font-size: 18px; }
.label-grid.cols-6 .nama-barang { font-size: 8px; max-height: 28px; }
.label-grid.cols-6 .barcode { font-size: 7px; }
.label-grid.cols-6 .price-label { font-size: 8px; }
.label-grid.cols-6 .price-value { font-size: 10px; }

.label-grid.cols-4 .harga-utama { font-size: 22px; }
.label-grid.cols-4 .nama-barang { font-size: 9px; }

.label-card .harga-utama {
  font-size: 28px;
  font-weight: bold;
  color: #000;
  line-height: 1;
  margin-bottom: 5px;
}

.label-card .harga-utama .prefix-rp {
  font-size: 10px;
  font-weight: normal;
}

.label-card .nama-barang {
  font-weight: bold;
  font-size: 10px;
  margin-bottom: 5px;
  line-height: 1.2;
  text-transform: uppercase;
  max-height: 35px;
  overflow: hidden;
}

.label-card .barcode {
  font-family: 'Courier New', monospace;
  font-size: 9px;
  margin: 5px 0;
  letter-spacing: 0.5px;
}

.label-card .separator {
  border-top: 1px dotted #666;
  margin: 5px 0;
}

.label-card .price-row {
  display: flex;
  justify-content: space-between;
  padding: 0 5px;
  margin-top: auto;
  font-size: 9px;
}

.label-card .price-col {
  flex: 1;
  text-align: left;
}

.label-card .price-col:last-child {
  text-align: right;
}

.label-card .price-label {
  font-weight: bold;
  margin-bottom: 2px;
  font-size: 11px;
}

.label-card .price-value {
  font-weight: bold;
  font-size: 14px;
}

.label-card .green-line {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 6px;
  background: #4CAF50;
}

.hasil-item {
  padding: 12px;
  border-bottom: 1px solid #eee;
  cursor: pointer;
  transition: background 0.2s;
}

.hasil-item:hover {
  background: #e3f2fd;
  border-left: 3px solid #2196F3;
  padding-left: 9px;
}

.hasil-item strong {
  color: #333;
  font-size: 14px;
}

.hasil-item small {
  display: block;
  margin-top: 3px;
  line-height: 1.4;
}
</style>

<script>
$(document).ready(function() {
    let labelItems = [];
    let cariBarangResults = [];
    const defaultBaris = { 3: 5, 4: 10, 6: 21 };
    const urlSearchBarang = <?= json_encode($urlSearchBarang, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const urlBarangBarcode = <?= json_encode($urlBarangBarcode, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const urlExportPdf = <?= json_encode($urlExportPdf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const urlExportExcel = <?= json_encode($urlExportExcel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const cabangCetakLabel = <?= (int) $cabangCetakLabel; ?>;

    function getLayout() {
        let kolom = parseInt($('#input-kolom').val(), 10) || 4;
        let baris = parseInt($('#input-baris').val(), 10) || (defaultBaris[kolom] || 10);
        if (kolom < 1) kolom = 1;
        if (kolom > 8) kolom = 8;
        if (baris < 1) baris = 1;
        if (baris > 40) baris = 40;
        return { kolom: kolom, baris: baris };
    }

    $('#input-kolom').on('change', function() {
        let k = parseInt($(this).val(), 10) || 4;
        if (defaultBaris[k]) {
            $('#input-baris').val(defaultBaris[k]);
        }
    });
    
    // Focus ke input barcode
    $('#input-barcode').focus();
    
    // Handle input barcode (Enter key)
    $('#input-barcode').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            let barcode = $(this).val().trim();
            let jumlah = parseInt($('#input-jumlah').val()) || 1;
            
            if (barcode) {
                cariBarangByBarcode(barcode, jumlah);
                $(this).val('');
            }
        }
    });
    
    // Cari barang by barcode
    function cariBarangByBarcode(barcode, jumlah) {
        $.ajax({
            url: urlBarangBarcode,
            method: 'POST',
            data: { 
                barcode: barcode,
                cabang: cabangCetakLabel
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    tambahLabel(response.data, jumlah);
                    showToast('Barang ditambahkan: ' + response.data.barang_nama, 'success');
                } else {
                    showToast(response.message || 'Barang tidak ditemukan', 'error');
                }
            },
            error: function(xhr) {
                showToast('Gagal memuat barang (HTTP ' + xhr.status + ')', 'error');
            }
        });
    }
    
    // Tambah label ke list
    function tambahLabel(barang, jumlah) {
        for (let i = 0; i < jumlah; i++) {
            labelItems.push({
                id: Date.now() + i,
                barang_kode: String(barang.barang_kode || ''),
                barang_nama: barang.barang_nama,
                barang_harga: barang.barang_harga, // Umum
                barang_harga_retail: barang.barang_harga_grosir_1, // Retail
                barang_harga_grosir: barang.barang_harga_grosir_2, // Grosir
                timestamp: Date.now()
            });
        }
        updateLabelList();
    }
    
    // Update tampilan list
    function updateLabelList() {
        let html = '';
        
        if (labelItems.length === 0) {
            html = '<div class="alert alert-warning text-center">' +
                   '<i class="fas fa-barcode fa-3x mb-2"></i><br>' +
                   'Belum ada label. Mulai scan barcode atau pilih barang.' +
                   '</div>';
            $('#action-buttons').hide();
        } else {
            // Group by barang_kode
            let grouped = {};
            labelItems.forEach(item => {
                if (!grouped[item.barang_kode]) {
                    grouped[item.barang_kode] = {
                        data: item,
                        count: 0,
                        ids: []
                    };
                }
                grouped[item.barang_kode].count++;
                grouped[item.barang_kode].ids.push(item.id);
            });
            
            Object.keys(grouped).forEach(kode => {
                let g = grouped[kode];
                html += '<div class="label-item" style="border-left: 4px solid #4CAF50; padding: 12px; margin-bottom: 10px; background: #f8f9fa;">' +
                        '<div class="row align-items-center">' +
                        '<div class="col-md-3">' +
                        '<strong style="text-transform: uppercase; font-size: 13px;">' + g.data.barang_nama + '</strong><br>' +
                        '<small class="text-muted" style="font-family: Courier New;">Kode: ' + g.data.barang_kode + '</small>' +
                        '</div>' +
                        '<div class="col-md-4 text-center" style="font-size: 11px;">' +
                        '<div><strong>Umum:</strong> Rp ' + formatRupiah(g.data.barang_harga) + '</div>' +
                        '<div><strong>Retail:</strong> Rp ' + formatRupiah(g.data.barang_harga_retail || g.data.barang_harga) + '</div>' +
                        '<div><strong>Grosir:</strong> Rp ' + formatRupiah(g.data.barang_harga_grosir || g.data.barang_harga) + '</div>' +
                        '</div>' +
                        '<div class="col-md-3 text-center">' +
                        '<span class="badge badge-success badge-lg" style="font-size: 14px; padding: 8px 15px;">' + g.count + ' Label</span>' +
                        '</div>' +
                        '<div class="col-md-2 text-right">' +
                        '<button class="btn btn-sm btn-danger btn-hapus" data-kode="' + kode + '" title="Hapus semua label barang ini">' +
                        '<i class="fas fa-trash"></i>' +
                        '</button>' +
                        '</div>' +
                        '</div>' +
                        '</div>';
            });
            
            $('#action-buttons').show();
        }
        
        $('#label-list').html(html);
        
        // Update total
        $('#total-label').text(labelItems.length);
    }
    
    // Hapus item
    $(document).on('click', '.btn-hapus', function() {
        let kode = $(this).data('kode');
        labelItems = labelItems.filter(item => item.barang_kode !== kode);
        updateLabelList();
        showToast('Label dihapus', 'success');
    });
    
    // Clear all
    $('#btn-clear-all').click(function() {
        if (confirm('Hapus semua label?')) {
            labelItems = [];
            updateLabelList();
            showToast('Semua label dihapus', 'success');
        }
    });
    
    // Modal cari barang
    $('#btn-tambah-manual').click(function() {
        cariBarangResults = [];
        $('#search-barang').val('');
        $('#filter-kategori-label').val('').trigger('change');
        $('#filter-suplier-label').val('').trigger('change');
        $('#btn-tambah-semua-hasil').hide();
        $('#info-hasil-pencarian').text('Pilih filter atau ketik minimal 2 karakter');
        $('#hasil-pencarian').html('<p class="text-center text-muted">Pilih kategori/supplier atau ketik untuk mencari barang...</p>');
        $('#modalCariBarang').modal('show');
        setTimeout(function () {
            if ($.fn.select2) {
                $('#filter-kategori-label, #filter-suplier-label').each(function () {
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2('destroy');
                    }
                });
                $('#filter-kategori-label, #filter-suplier-label').select2({
                    theme: 'bootstrap4',
                    dropdownParent: $('#modalCariBarang'),
                    width: '100%',
                    placeholder: 'Pilih...',
                    allowClear: true
                });
            }
            $('#search-barang').trigger('focus');
        }, 300);
    });

    function getFilterLabel() {
        return {
            kategori_id: $('#filter-kategori-label').val() || '',
            kode_suplier: $('#filter-suplier-label').val() || ''
        };
    }

    function canSearchLabel(keyword) {
        let f = getFilterLabel();
        return keyword.length >= 2 || f.kategori_id !== '' || f.kode_suplier !== '';
    }

    function jalankanPencarianBarang() {
        let keyword = $('#search-barang').val().trim();
        let filter = getFilterLabel();

        if (!canSearchLabel(keyword)) {
            cariBarangResults = [];
            $('#btn-tambah-semua-hasil').hide();
            $('#info-hasil-pencarian').text('Pilih filter atau ketik minimal 2 karakter');
            $('#hasil-pencarian').html('<p class="text-center text-muted">Pilih kategori/supplier atau ketik minimal 2 karakter...</p>');
            return;
        }

        $('#hasil-pencarian').html('<p class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Mencari...</p>');
        $.ajax({
            url: urlSearchBarang,
            method: 'POST',
            data: {
                keyword: keyword,
                cabang: cabangCetakLabel,
                kategori_id: filter.kategori_id,
                kode_suplier: filter.kode_suplier
            },
            dataType: 'json',
            success: function(response) {
                if (response && response.success && response.data && response.data.length > 0) {
                    cariBarangResults = response.data;
                    renderHasilPencarian(cariBarangResults);
                    $('#info-hasil-pencarian').text('Menampilkan ' + cariBarangResults.length + ' barang');
                    $('#btn-tambah-semua-hasil').show();
                } else {
                    cariBarangResults = [];
                    $('#btn-tambah-semua-hasil').hide();
                    $('#info-hasil-pencarian').text('Tidak ada hasil');
                    $('#hasil-pencarian').html('<p class="text-center text-muted">Tidak ada hasil</p>');
                }
            },
            error: function(xhr) {
                cariBarangResults = [];
                $('#btn-tambah-semua-hasil').hide();
                $('#info-hasil-pencarian').text('Gagal memuat');
                $('#hasil-pencarian').html('<p class="text-center text-danger">Gagal memuat barang (HTTP ' + xhr.status + ')</p>');
            }
        });
    }
    
    function renderHasilPencarian(items) {
        if (!items || !items.length) {
            $('#hasil-pencarian').html('<p class="text-center text-muted">Tidak ada hasil</p>');
            return;
        }
        let html = '';
        items.forEach(function (item, idx) {
            html += '<div class="hasil-item" data-idx="' + idx + '">' +
                    '<strong>' + escapeHtml(item.barang_nama) + '</strong><br>' +
                    '<small>Kode: ' + escapeHtml(item.barang_kode) +
                    (item.kode_suplier ? ' · Supplier: ' + escapeHtml(item.kode_suplier) : '') +
                    '</small><br>' +
                    '<small style="color: #666;">' +
                    'Umum: Rp ' + formatRupiah(item.barang_harga) + ' | ' +
                    'Retail: Rp ' + formatRupiah(item.barang_harga_grosir_1 || item.barang_harga) + ' | ' +
                    'Grosir: Rp ' + formatRupiah(item.barang_harga_grosir_2 || item.barang_harga) +
                    '</small>' +
                    '</div>';
        });
        $('#hasil-pencarian').html(html);
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
    
    // Search barang
    let searchTimeout;
    $('#search-barang').on('input keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(jalankanPencarianBarang, 300);
    });

    $('#filter-kategori-label, #filter-suplier-label').on('change', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(jalankanPencarianBarang, 150);
    });

    $('#btn-reset-filter-label').on('click', function() {
        $('#search-barang').val('');
        $('#filter-kategori-label').val('').trigger('change');
        $('#filter-suplier-label').val('').trigger('change');
        cariBarangResults = [];
        $('#btn-tambah-semua-hasil').hide();
        $('#info-hasil-pencarian').text('Pilih filter atau ketik minimal 2 karakter');
        $('#hasil-pencarian').html('<p class="text-center text-muted">Pilih kategori/supplier atau ketik untuk mencari barang...</p>');
    });

    $('#btn-tambah-semua-hasil').on('click', function() {
        if (!cariBarangResults.length) {
            return;
        }
        let jumlah = parseInt($('#input-jumlah').val(), 10) || 1;
        cariBarangResults.forEach(function (barang) {
            tambahLabel(barang, jumlah);
        });
        $('#modalCariBarang').modal('hide');
        showToast(cariBarangResults.length + ' barang ditambahkan', 'success');
    });
    
    // Pilih barang dari hasil pencarian
    $(document).on('click', '.hasil-item', function() {
        let idx = parseInt($(this).attr('data-idx'), 10);
        let barang = cariBarangResults[idx];
        if (!barang) {
            showToast('Data barang tidak valid', 'error');
            return;
        }
        let jumlah = parseInt($('#input-jumlah').val(), 10) || 1;
        tambahLabel(barang, jumlah);
        $('#modalCariBarang').modal('hide');
        showToast('Barang ditambahkan', 'success');
    });
    
    // Preview
    $('#btn-preview').click(function() {
        generatePreview();
        $('#modalPreview').modal('show');
    });
    
    // Generate preview
    function generatePreview() {
        let layout = getLayout();
        let html = '<div class="mb-2 text-center text-muted">' +
                   layout.kolom + ' kolom × ' + layout.baris + ' baris / halaman' +
                   ' · Total ' + labelItems.length + ' label</div>' +
                   '<div class="label-preview" style="width: 210mm; min-height: 330mm; margin: 0 auto;">' +
                   '<div class="label-grid cols-' + layout.kolom + '">';
        
        labelItems.forEach(item => {
            html += '<div class="label-card">' +
                    '<div class="harga-utama">' +
                    '<span class="prefix-rp">Rp.</span>' +
                    formatRupiah(item.barang_harga) +
                    '</div>' +
                    '<div class="nama-barang">' + item.barang_nama.toUpperCase() + '</div>' +
                    '<div class="barcode">' + item.barang_kode + '</div>' +
                    '<div class="separator"></div>' +
                    '<div class="price-row">' +
                    '<div class="price-col">' +
                    '<div class="price-label">Retail:</div>' +
                    '<div class="price-value">Rp ' + formatRupiah(item.barang_harga_retail || item.barang_harga) + '</div>' +
                    '</div>' +
                    '<div class="price-col">' +
                    '<div class="price-label">Grosir:</div>' +
                    '<div class="price-value">Rp ' + formatRupiah(item.barang_harga_grosir || item.barang_harga) + '</div>' +
                    '</div>' +
                    '</div>' +
                    '<div class="green-line"></div>' +
                    '</div>';
        });
        
        html += '</div></div>';
        $('#preview-content').html(html);
    }

    function submitExport(action) {
        if (labelItems.length === 0) {
            showToast('Tidak ada label untuk dicetak', 'error');
            return;
        }
        let layout = getLayout();
        let form = $('<form>', {
            'method': 'POST',
            'action': action,
            'target': '_blank'
        });
        form.append($('<input>', { type: 'hidden', name: 'labels', value: JSON.stringify(labelItems) }));
        form.append($('<input>', { type: 'hidden', name: 'kolom', value: layout.kolom }));
        form.append($('<input>', { type: 'hidden', name: 'baris', value: layout.baris }));
        $('body').append(form);
        form.submit();
        form.remove();
    }
    
    // Export to PDF
    $('#btn-export-pdf, #btn-export-from-preview').click(function() {
        submitExport(urlExportPdf);
    });

    // Export to Excel
    $('#btn-export-excel, #btn-export-excel-from-preview').click(function() {
        submitExport(urlExportExcel);
    });
    
    // Format rupiah
    function formatRupiah(angka) {
        return parseInt(angka || 0, 10).toLocaleString('id-ID');
    }
    
    // Toast notification
    function showToast(message, type) {
        let bgColor = type === 'success' ? '#4CAF50' : '#f44336';
        let toast = $('<div>', {
            'class': 'toast-notification',
            'text': message,
            'css': {
                'position': 'fixed',
                'top': '20px',
                'right': '20px',
                'background': bgColor,
                'color': 'white',
                'padding': '15px 20px',
                'border-radius': '5px',
                'z-index': 9999,
                'box-shadow': '0 2px 5px rgba(0,0,0,0.2)'
            }
        });
        
        $('body').append(toast);
        setTimeout(() => toast.fadeOut(() => toast.remove()), 3000);
    }
});
</script>

<?php include '_footer.php'; ?>
