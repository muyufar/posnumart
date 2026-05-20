<?php 
  include '_header.php';
  include '_nav.php';
  include '_sidebar.php'; 
  error_reporting(0);
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
            
            <div class="alert alert-info">
              <strong>Tips:</strong><br>
              • Scan barcode dengan scanner<br>
              • Atau ketik kode barang<br>
              • Tekan Enter untuk menambah<br>
              • Set jumlah label per item
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
        <input type="text" class="form-control mb-3" id="search-barang" placeholder="Cari nama atau kode barang...">
        <div id="hasil-pencarian" style="max-height: 400px; overflow-y: auto;">
          <p class="text-center text-muted">Ketik untuk mencari barang...</p>
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
  gap: 10px;
  padding: 10px;
}

.label-card {
  border: 1.5px solid #000;
  padding: 8px;
  text-align: center;
  background: white;
  min-height: 120px;
  display: flex;
  flex-direction: column;
  position: relative;
}

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
            url: 'aksi/get-barang-by-barcode.php',
            method: 'POST',
            data: { 
                barcode: barcode,
                cabang: <?= $sessionCabang; ?>
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
            error: function() {
                showToast('Terjadi kesalahan saat mencari barang', 'error');
            }
        });
    }
    
    // Tambah label ke list
    function tambahLabel(barang, jumlah) {
        for (let i = 0; i < jumlah; i++) {
            labelItems.push({
                id: Date.now() + i,
                barang_kode: barang.barang_kode,
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
        $('#modalCariBarang').modal('show');
        $('#search-barang').focus();
    });
    
    // Search barang
    let searchTimeout;
    $('#search-barang').on('keyup', function() {
        clearTimeout(searchTimeout);
        let keyword = $(this).val().trim();
        
        if (keyword.length < 2) {
            $('#hasil-pencarian').html('<p class="text-center text-muted">Ketik minimal 2 karakter...</p>');
            return;
        }
        
        searchTimeout = setTimeout(function() {
            $.ajax({
                url: 'aksi/search-barang.php',
                method: 'POST',
                data: { 
                    keyword: keyword,
                    cabang: <?= $sessionCabang; ?>
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        let html = '';
                        response.data.forEach(item => {
                            html += '<div class="hasil-item" data-barang=\'' + JSON.stringify(item) + '\'>' +
                                    '<strong>' + item.barang_nama + '</strong><br>' +
                                    '<small>Kode: ' + item.barang_kode + '</small><br>' +
                                    '<small style="color: #666;">' +
                                    'Umum: Rp ' + formatRupiah(item.barang_harga) + ' | ' +
                                    'Retail: Rp ' + formatRupiah(item.barang_harga_grosir_1 || item.barang_harga) + ' | ' +
                                    'Grosir: Rp ' + formatRupiah(item.barang_harga_grosir_2 || item.barang_harga) +
                                    '</small>' +
                                    '</div>';
                        });
                        $('#hasil-pencarian').html(html);
                    } else {
                        $('#hasil-pencarian').html('<p class="text-center text-muted">Tidak ada hasil</p>');
                    }
                }
            });
        }, 300);
    });
    
    // Pilih barang dari hasil pencarian
    $(document).on('click', '.hasil-item', function() {
        let barang = JSON.parse($(this).attr('data-barang'));
        let jumlah = parseInt($('#input-jumlah').val()) || 1;
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
        let html = '<div class="label-preview" style="width: 210mm; min-height: 330mm; margin: 0 auto;">' +
                   '<div class="label-grid">';
        
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
    
    // Export to PDF
    $('#btn-export-pdf, #btn-export-from-preview').click(function() {
        if (labelItems.length === 0) {
            showToast('Tidak ada label untuk dicetak', 'error');
            return;
        }
        
        // Kirim data ke server untuk generate PDF
        let form = $('<form>', {
            'method': 'POST',
            'action': 'cetak-label-pdf.php',
            'target': '_blank'
        });
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'labels',
            'value': JSON.stringify(labelItems)
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
    });
    
    // Format rupiah
    function formatRupiah(angka) {
        return parseInt(angka).toLocaleString('id-ID');
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
</body>
</html>

