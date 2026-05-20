<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
include 'db.php';
error_reporting(0);
?>
<?php
if ($levelLogin === "kasir" && $levelLogin === "kurir") {
    echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
}
?>

<!-- Modal Input Nama -->
<div class="modal fade" id="inputAwalModal" data-backdrop="static" data-keyboard="false" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Input Data Audit</h5>
            </div>
            <div class="modal-body">
                <form id="formAwal">
                    <div class="form-group">
                        <label>Nama Auditor</label>
                        <input type="text" class="form-control" id="inputNamaAuditor" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="submitAwal">Mulai Audit</button>
            </div>
        </div>
    </div>
</div>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Audit Barang</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="bo">Home</a></li>
                        <li class="breadcrumb-item active">Audit Barang</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="auditorName">

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <div id="scannerSection" style="display: none;">
                                <h4>Scan Barcode Produk</h4>
                                <div id="reader"></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="auditTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Kode Barang</th>
                                        <th>Nama Barang</th>
                                        <th>Stock Sistem</th>
                                        <th>Stock Fisik</th>
                                        <th>Selisih</th>
                                        <th>Keterangan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Stock -->
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Input Stock Fisik</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="stockForm">
                    <input type="hidden" id="barangId">
                    <div class="form-group">
                        <label>Nama Barang</label>
                        <input type="text" class="form-control" id="barangNama" readonly>
                    </div>
                    <div class="form-group">
                        <label>Stock Sistem</label>
                        <input type="number" class="form-control" id="stockSistem" readonly>
                    </div>
                    <div class="form-group">
                        <label>Stock Fisik</label>
                        <input type="number" class="form-control" id="stockFisik" required>
                    </div>
                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea class="form-control" id="keterangan" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="saveStock">Simpan</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Show input awal modal on page load
    $('#inputAwalModal').modal('show');

    // Handle form submission
    $('#submitAwal').click(function() {
        var nama = $('#inputNamaAuditor').val();

        if (nama.trim() === '') {
            alert('Nama auditor harus diisi!');
            return;
        }

        // Save auditor name
        $('#auditorName').val(nama);

        // Hide modal and show scanner
        $('#inputAwalModal').modal('hide');
        $('#scannerSection').show();

        // Initialize DataTable
        initializeDataTable();
    });

    // Initialize QR Scanner
    function onScanSuccess(decodedText, decodedResult) {
        // Stop scanner
        html5QrcodeScanner.pause();

        // Get barang data
        $.ajax({
            url: 'get-barang.php',
            type: 'POST',
            data: {
                kode: decodedText
            },
            success: function(response) {
                if (response.success) {
                    $('#barangId').val(response.data.barang_id);
                    $('#barangNama').val(response.data.barang_nama);
                    $('#stockSistem').val(response.data.barang_stock);
                    $('#stockModal').modal('show');
                } else {
                    alert('Barang tidak ditemukan!');
                }
                // Resume scanner
                html5QrcodeScanner.resume();
            },
            error: function() {
                alert('Terjadi kesalahan saat mengambil data barang!');
                html5QrcodeScanner.resume();
            }
        });
    }

    var html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", {
            fps: 10,
            qrbox: 250
        });
    html5QrcodeScanner.render(onScanSuccess);

    function initializeDataTable() {
        if ($.fn.DataTable.isDataTable('#auditTable')) {
            $('#auditTable').DataTable().destroy();
        }

        $('#auditTable').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "audit-data.php",
                "type": "GET",
                "error": function(xhr, error, thrown) {
                    console.log('Error:', error);
                    console.log('Detail:', thrown);
                }
            },
            "columns": [{
                    "data": "no"
                },
                {
                    "data": "barang_kode"
                },
                {
                    "data": "barang_nama"
                },
                {
                    "data": "stock_sistem"
                },
                {
                    "data": "stock_fisik"
                },
                {
                    "data": "selisih"
                },
                {
                    "data": "keterangan"
                },
                {
                    "data": null,
                    "render": function(data, type, row) {
                        return '<button class="btn btn-danger btn-sm delete-audit" data-id="' + row.audit_id + '">Hapus</button>';
                    }
                }
            ]
        });
    }

    // Save stock data
    $('#saveStock').click(function() {
        var data = {
            barang_id: $('#barangId').val(),
            stock_fisik: $('#stockFisik').val(),
            keterangan: $('#keterangan').val(),
            auditor: $('#auditorName').val()
        };

        $.ajax({
            url: 'save-audit.php',
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $('#stockModal').modal('hide');
                    $('#auditTable').DataTable().ajax.reload();
                    $('#stockForm')[0].reset();
                } else {
                    alert('Gagal menyimpan data!');
                }
            }
        });
    });

    // Delete audit data
    $('#auditTable').on('click', '.delete-audit', function() {
        if (confirm('Apakah Anda yakin ingin menghapus data ini?')) {
            var id = $(this).data('id');
            $.ajax({
                url: 'delete-audit.php',
                type: 'POST',
                data: {
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        $('#auditTable').DataTable().ajax.reload();
                    } else {
                        alert('Gagal menghapus data!');
                    }
                }
            });
        }
    });
});
</script>

<style>
    #reader {
        width: 100%;
        max-width: 600px;
        margin: 0 auto;
    }
</style>