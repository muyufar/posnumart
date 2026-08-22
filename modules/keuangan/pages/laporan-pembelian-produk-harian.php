<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Laporan Pembelian Per Produk Harian</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="bo.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="laporan-keuangan.php">Laporan Keuangan</a></li>
                        <li class="breadcrumb-item active">Laporan Pembelian Per Produk</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Filter Card -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Filter Laporan Pembelian Per Produk</h3>
                        </div>
                        <div class="card-body">
                            <form id="filterForm">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Tanggal</label>
                                            <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Supplier</label>
                                            <select class="form-control" id="supplier_id" name="supplier_id">
                                                <option value="">Semua Supplier</option>
                                                <?php
                                                $supplier_query = "SELECT supplier_id, supplier_nama FROM supplier ORDER BY supplier_nama";
                                                $supplier_result = $conn->query($supplier_query);
                                                while ($supplier = $supplier_result->fetch_assoc()) {
                                                    echo "<option value='{$supplier['supplier_id']}'>{$supplier['supplier_nama']}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Kategori Barang</label>
                                            <select class="form-control" id="kategori_id" name="kategori_id">
                                                <option value="">Semua Kategori</option>
                                                <?php
                                                $kategori_query = "SELECT kategori_id, kategori_nama FROM kategori ORDER BY kategori_nama";
                                                $kategori_result = $conn->query($kategori_query);
                                                while ($kategori = $kategori_result->fetch_assoc()) {
                                                    echo "<option value='{$kategori['kategori_id']}'>{$kategori['kategori_nama']}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Status Pembayaran</label>
                                            <select class="form-control" id="status_pembayaran" name="status_pembayaran">
                                                <option value="">Semua Status</option>
                                                <option value="lunas">Lunas</option>
                                                <option value="hutang">Hutang</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <button type="button" class="btn btn-primary" onclick="loadData()">
                                            <i class="fa fa-search"></i> Tampilkan Data
                                        </button>
                                        <button type="button" class="btn btn-success" onclick="exportExcel()">
                                            <i class="fa fa-file-excel"></i> Export Excel
                                        </button>
                                        <button type="button" class="btn btn-danger" onclick="exportPDF()">
                                            <i class="fa fa-file-pdf"></i> Export PDF
                                        </button>
                                        <button type="button" class="btn btn-info" onclick="printData()">
                                            <i class="fa fa-print"></i> Print
                                        </button>
                                        <button type="button" class="btn btn-warning" onclick="resetFilter()">
                                            <i class="fa fa-refresh"></i> Reset Filter
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row" id="summaryCards" style="display: none;">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3 id="totalInvoice">0</h3>
                            <p>Total Invoice</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3 id="totalProduk">0</h3>
                            <p>Total Produk</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3 id="totalQty">0</h3>
                            <p>Total Qty</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3 id="totalNilai">Rp 0</h3>
                            <p>Total Nilai</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Data Pembelian Per Produk</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="dataTable">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Barang</th>
                                            <th>Kategori</th>
                                            <th>Satuan</th>
                                            <th>Qty</th>
                                            <th>Harga Satuan</th>
                                            <th>Total Harga</th>
                                            <th>Supplier</th>
                                            <th>Invoice</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dataBody">
                                        <tr>
                                            <td colspan="9" class="text-center">Pilih filter dan klik "Tampilkan Data"</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Pembelian Card -->
            <div class="row" id="totalPembelianCard" style="display: none;">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-calculator"></i> Total Pembelian
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-info">
                                            <i class="fas fa-file-invoice"></i>
                                        </span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Total Invoice</span>
                                            <span class="info-box-number" id="totalInvoiceCard">0</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-success">
                                            <i class="fas fa-boxes"></i>
                                        </span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Total Produk</span>
                                            <span class="info-box-number" id="totalProdukCard">0</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-warning">
                                            <i class="fas fa-shopping-cart"></i>
                                        </span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Total Qty</span>
                                            <span class="info-box-number" id="totalQtyCard">0</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-danger">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Total Nilai</span>
                                            <span class="info-box-number" id="totalNilaiCard">Rp 0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    // Format number function
    function formatNumber(num) {
        return new Intl.NumberFormat('id-ID').format(num);
    }

    function loadData() {
        const tanggal = $('#tanggal').val();
        const supplier_id = $('#supplier_id').val();
        const kategori_id = $('#kategori_id').val();
        const status_pembayaran = $('#status_pembayaran').val();

        // Show loading
        $('#dataBody').html('<tr><td colspan="9" class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>');

        // Load data from API
        $.ajax({
            url: 'api/laporan-pembelian-produk-harian.php',
            type: 'GET',
            data: {
                tanggal: tanggal,
                supplier_id: supplier_id,
                kategori_id: kategori_id,
                status_pembayaran: status_pembayaran
            },
            success: function(response) {
                console.log('API Response:', response);
                if (response.success) {
                    let tableHTML = '';
                    if (response.data.length > 0) {
                        response.data.forEach((item, index) => {
                            tableHTML += `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>${item.nama_barang}</td>
                                    <td>${item.kategori}</td>
                                    <td>${item.satuan}</td>
                                    <td class="text-right">${formatNumber(item.qty)}</td>
                                    <td class="text-right">Rp ${formatNumber(item.harga_satuan)}</td>
                                    <td class="text-right">Rp ${formatNumber(item.total_harga)}</td>
                                    <td>${item.supplier}</td>
                                    <td>${item.invoice}</td>
                                </tr>
                            `;
                        });
                    } else {
                        tableHTML = '<tr><td colspan="9" class="text-center">Tidak ada data pembelian</td></tr>';
                    }
                    $('#dataBody').html(tableHTML);

                    // Show summary cards
                    if (response.summary) {
                        $('#summaryCards').show();
                        $('#totalInvoice').text(response.summary.total_invoice);
                        $('#totalProduk').text(response.summary.total_produk);
                        $('#totalQty').text(formatNumber(response.summary.total_qty));
                        $('#totalNilai').text('Rp ' + formatNumber(response.summary.total_nilai));

                        // Show total pembelian card
                        $('#totalPembelianCard').show();
                        $('#totalInvoiceCard').text(response.summary.total_invoice);
                        $('#totalProdukCard').text(response.summary.total_produk);
                        $('#totalQtyCard').text(formatNumber(response.summary.total_qty));
                        $('#totalNilaiCard').text('Rp ' + formatNumber(response.summary.total_nilai));
                    }
                } else {
                    $('#dataBody').html('<tr><td colspan="9" class="text-center text-danger">Error: ' + response.message + '</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', xhr, status, error);
                $('#dataBody').html('<tr><td colspan="9" class="text-center text-danger">Error loading data: ' + error + '</td></tr>');
            }
        });
    }

    function exportExcel() {
        const tanggal = $('#tanggal').val();
        const supplier_id = $('#supplier_id').val();
        const kategori_id = $('#kategori_id').val();
        const status_pembayaran = $('#status_pembayaran').val();

        const exportUrl = `api/export-pembelian-produk-harian.php?tanggal=${tanggal}&supplier_id=${supplier_id}&kategori_id=${kategori_id}&status_pembayaran=${status_pembayaran}`;
        window.open(exportUrl, '_blank');
    }

    function exportPDF() {
        const tanggal = $('#tanggal').val();
        const supplier_id = $('#supplier_id').val();
        const kategori_id = $('#kategori_id').val();
        const status_pembayaran = $('#status_pembayaran').val();

        const exportUrl = `api/export-pembelian-produk-pdf.php?tanggal=${tanggal}&supplier_id=${supplier_id}&kategori_id=${kategori_id}&status_pembayaran=${status_pembayaran}`;
        window.open(exportUrl, '_blank');
    }

    function printData() {
        window.print();
    }

    function resetFilter() {
        $('#tanggal').val('<?= date('Y-m-d') ?>');
        $('#supplier_id').val('');
        $('#kategori_id').val('');
        $('#status_pembayaran').val('');
        $('#summaryCards').hide();
        $('#totalPembelianCard').hide();
        $('#dataBody').html('<tr><td colspan="9" class="text-center">Pilih filter dan klik "Tampilkan Data"</td></tr>');
    }

    // Initialize on page load
    $(document).ready(function() {
        // Set default date to today
        $('#tanggal').val('<?= date('Y-m-d') ?>');
    });
</script>

<?php include '_footer.php'; ?>