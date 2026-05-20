<?php
// view_supplier_barang.php
// Versi: diperbaiki (menghilangkan COLLATE error) - responsive form + perbaikan query
// Konfigurasi Database
$host = 'localhost';
$username = 'u700125577_user';
$password = '@u700125577_User';
$database = 'u700125577_numart';

// Koneksi ke database
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
// Optional: set charset (sesuaikan bila tabelmu latin1, tapi ini biasanya aman)
$conn->set_charset('utf8mb4');

// --- Handle Search and Per Page ---
$search_supplier = isset($_GET['supplier']) ? trim($_GET['supplier']) : '';
$search_barang   = isset($_GET['barang']) ? trim($_GET['barang']) : '';
$search_nama     = isset($_GET['nama']) ? trim($_GET['nama']) : '';
$search_kode     = isset($_GET['kode']) ? trim($_GET['kode']) : '';

$per_page_options = [50, 100, 500, 1000];
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $per_page_options) ? (int)$_GET['per_page'] : 50;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $per_page;

// --- Build WHERE clause for search ---
$where_clauses = [];
$where_clauses[] = "s.supplier_status = '1'";
$where_clauses[] = "lt.rn = 1";
if ($search_supplier !== '') {
    $where_clauses[] = "s.supplier_company LIKE '%" . $conn->real_escape_string($search_supplier) . "%'";
}
if ($search_nama !== '') {
    $where_clauses[] = "s.supplier_nama LIKE '%" . $conn->real_escape_string($search_nama) . "%'";
}
if ($search_barang !== '') {
    $where_clauses[] = "b.barang_nama LIKE '%" . $conn->real_escape_string($search_barang) . "%'";
}
if ($search_kode !== '') {
    $where_clauses[] = "b.barang_kode LIKE '%" . $conn->real_escape_string($search_kode) . "%'";
}
$where_sql = implode(' AND ', $where_clauses);
if ($where_sql === '') $where_sql = '1';

// --- Main data query (CTE + SQL_CALC_FOUND_ROWS) ---
// NOTE: saya HAPUS penggunaan COLLATE pada ORDER BY untuk menghindari error charset/collation.
$data_query = "
WITH latest_transactions AS (
    SELECT 
        ip.invoice_supplier,
        p.barang_id,
        p.pembelian_invoice,
        p.barang_qty,
        p.barang_harga_beli,
        ip.invoice_tgl,
        ROW_NUMBER() OVER (
            PARTITION BY ip.invoice_supplier, p.barang_id 
            ORDER BY ip.invoice_tgl DESC
        ) as rn
    FROM invoice_pembelian ip
    INNER JOIN pembelian p ON ip.pembelian_invoice = p.pembelian_invoice
    WHERE p.pembelian_cabang = 0
)
SELECT SQL_CALC_FOUND_ROWS
    s.supplier_company,
    s.supplier_nama,
    s.supplier_wa,
    b.barang_id,
    b.barang_kode,
    b.barang_nama,
    b.barang_harga_beli,
    b.barang_stock,
    lt.invoice_tgl AS tanggal_pembelian,
    lt.barang_qty AS qty_dibeli,
    lt.barang_harga_beli AS harga_beli
FROM supplier s
INNER JOIN latest_transactions lt ON s.supplier_id = lt.invoice_supplier
INNER JOIN barang b ON lt.barang_id = b.barang_id
WHERE $where_sql
ORDER BY s.supplier_company, b.barang_nama
LIMIT $per_page OFFSET $offset
";

$result = $conn->query($data_query);
if (!$result) {
    die("Error dalam query data: " . $conn->error . "\nQuery: " . $data_query);
}

// Ambil total rows (FOUND_ROWS)
$total_barang = 0;
$found_rows_res = $conn->query("SELECT FOUND_ROWS() AS total");
if ($found_rows_res && $fr = $found_rows_res->fetch_assoc()) {
    $total_barang = (int)$fr['total'];
}
$total_pages = $per_page > 0 ? ceil($total_barang / $per_page) : 1;

// Ambil data ke array
$data_barang = [];
$total_nilai = 0.0;
while ($row = $result->fetch_assoc()) {
    $row['barang_id'] = isset($row['barang_id']) ? $row['barang_id'] : '';
    $row['barang_stock'] = isset($row['barang_stock']) ? $row['barang_stock'] : 0;
    $row['harga_beli'] = isset($row['harga_beli']) ? (float)$row['harga_beli'] : 0.0;
    $data_barang[] = $row;
    $total_nilai += $row['harga_beli'];
}
$result->free();

// --- Supplier count (distinct) ---
$supplier_count_query = "
WITH latest_transactions AS (
    SELECT 
        ip.invoice_supplier,
        p.barang_id,
        ROW_NUMBER() OVER (
            PARTITION BY ip.invoice_supplier, p.barang_id 
            ORDER BY ip.invoice_tgl DESC
        ) as rn
    FROM invoice_pembelian ip
    INNER JOIN pembelian p ON ip.pembelian_invoice = p.pembelian_invoice
    WHERE p.pembelian_cabang = 0
)
SELECT COUNT(DISTINCT s.supplier_company) AS total_supplier
FROM supplier s
INNER JOIN latest_transactions lt ON s.supplier_id = lt.invoice_supplier
INNER JOIN barang b ON lt.barang_id = b.barang_id
WHERE $where_sql
";
$supplier_result = $conn->query($supplier_count_query);
$total_supplier = 0;
if ($supplier_result && $r = $supplier_result->fetch_assoc()) {
    $total_supplier = (int)$r['total_supplier'];
}
if ($supplier_result) $supplier_result->free();

function build_query($params, $exclude = []) {
    $query = [];
    foreach ($params as $k => $v) {
        if (in_array($k, $exclude)) continue;
        if ($v !== '' && $v !== null) $query[] = urlencode($k) . '=' . urlencode($v);
    }
    return implode('&', $query);
}

$current_params = [
    'supplier' => $search_supplier,
    'nama'     => $search_nama,
    'barang'   => $search_barang,
    'kode'     => $search_kode,
    'per_page' => $per_page
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Barang per Supplier</title>
    <style>
        /* (sama seperti versi sebelumnya: styling form, tabel, pagination, responsive) */
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background-color: white; padding: 18px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h1 { text-align: center; color: #333; margin-bottom: 18px; }
        .export-buttons { text-align:center; margin-bottom:12px; }
        .btn { display:inline-block; padding:8px 14px; margin:0 6px; background:#007bff; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold; }
        .btn-success { background:#28a745; }
        .stats { display:flex; justify-content:space-between; gap:12px; margin-bottom:14px; padding:12px; background:#f8f9fa; border-radius:6px; }
        .stat-item { text-align:center; flex:1; } .stat-number { font-size:20px; font-weight:bold; color:#007bff; } .stat-label { color:#666; font-size:13px; }

        .search-form { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:16px; background:#fafafa; padding:12px; border-radius:6px; border:1px solid #eee; }
        .search-form .form-group { display:flex; align-items:center; gap:8px; }
        .search-form label { font-weight:bold; color:#333; font-size:13px; }
        .search-form input[type="text"], .search-form select { padding:6px 8px; border-radius:4px; border:1px solid #ccc; font-size:13px; }
        .search-form button { padding:7px 12px; border:none; border-radius:4px; cursor:pointer; font-weight:bold; }
        .search-form button[type="submit"] { background:#007bff; color:#fff; }
        .search-form .reset-btn { background:#6c757d; color:#fff; }

        .search-row { display:flex; flex-wrap:wrap; gap:10px; width:100%; }
        .search-row .form-group { flex:1 1 220px; min-width:160px; }

        .scroll-container { max-height:520px; overflow-y:auto; border:1px solid #e6e6e6; border-radius:6px; }
        table { width:100%; border-collapse:collapse; font-size:13px; min-width:980px; }
        th, td { border-bottom:1px solid #eee; padding:8px 10px; text-align:left; vertical-align:middle; }
        th { position:sticky; top:0; background:#fafafa; z-index:2; font-weight:bold; }
        tr:nth-child(even) td { background:#fcfcfc; } tr:hover td { background:#f2f7ff; }

        .number { text-align:right; } .supplier-name { font-weight:bold; color:#007bff; }

        .pagination { margin:14px 0; text-align:center; }
        .pagination a, .pagination span { display:inline-block; padding:6px 10px; margin:0 3px; border-radius:4px; border:1px solid #007bff; color:#007bff; font-weight:bold; text-decoration:none; background:#fff; }
        .pagination a:hover { background:#007bff; color:#fff; } .pagination .active { background:#007bff; color:#fff; border-color:#007bff; } .pagination .disabled { background:#f5f5f5; color:#aaa; border-color:#ddd; cursor:not-allowed; }

        .footer { margin-top:12px; text-align:center; color:#666; font-size:13px; }

        @media (max-width:900px) { .search-row .form-group { flex:1 1 48%; min-width:140px; } table { min-width:900px; } }
        @media (max-width:640px) { .search-form { padding:8px; } .search-row .form-group { flex:1 1 100%; min-width:0; } table { font-size:12px; min-width:700px; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Data Barang per Supplier</h1>

        <div class="export-buttons">
            <a href="export_supplier_barang.php" class="btn">📥 Export Excel (Simple)</a>
            <a href="export_supplier_barang_simple.php" class="btn btn-success">📊 Export Excel (Advanced)</a>
        </div>

        <div class="stats" aria-hidden="true">
            <div class="stat-item">
                <div class="stat-number"><?php echo number_format($total_barang); ?></div>
                <div class="stat-label">Total Barang</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">Rp <?php echo number_format($total_nilai, 0, ',', '.'); ?></div>
                <div class="stat-label">Total Nilai Pembelian (Halaman Ini)</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo number_format($total_supplier); ?></div>
                <div class="stat-label">Total Supplier</div>
            </div>
        </div>

        <form class="search-form" method="get" action="">
            <div class="search-row">
                <div class="form-group">
                    <label for="supplier">Perusahaan:</label>
                    <input type="text" name="supplier" id="supplier" value="<?php echo htmlspecialchars($search_supplier); ?>" placeholder="Nama Perusahaan">
                </div>

                <div class="form-group">
                    <label for="nama">Nama Supplier:</label>
                    <input type="text" name="nama" id="nama" value="<?php echo htmlspecialchars($search_nama); ?>" placeholder="Nama Supplier">
                </div>

                <div class="form-group">
                    <label for="barang">Nama Barang:</label>
                    <input type="text" name="barang" id="barang" value="<?php echo htmlspecialchars($search_barang); ?>" placeholder="Nama Barang">
                </div>
            </div>

            <div class="search-row">
                <div class="form-group">
                    <label for="kode">Barcode/Kode:</label>
                    <input type="text" name="kode" id="kode" value="<?php echo htmlspecialchars($search_kode); ?>" placeholder="Barcode/Kode">
                </div>

                <div class="form-group">
                    <label for="per_page">Tampil per halaman:</label>
                    <select name="per_page" id="per_page" onchange="this.form.submit()">
                        <?php foreach ($per_page_options as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php if ($per_page == $opt) echo 'selected'; ?>>
                                <?php echo $opt; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="display:flex; align-items:flex-end; gap:8px;">
                    <div style="width:100%;">
                        <button type="submit">Cari</button>
                    </div>
                    <div style="width:100%;">
                        <button type="button" class="reset-btn" onclick="window.location='view_supplier_barang.php'">Reset</button>
                    </div>
                </div>
            </div>
        </form>

        <div class="pagination" aria-label="Pagination Top">
            <?php
            $base_query = build_query($current_params, ['page']);
            if ($page > 1) {
                echo '<a href="?'.$base_query.'&page='.($page-1).'">&laquo; Prev</a>';
            } else {
                echo '<span class="disabled">&laquo; Prev</span>';
            }

            $max_links = 7;
            $start = max(1, $page - intval($max_links/2));
            $end = min($total_pages, $start + $max_links - 1);
            if ($end - $start < $max_links - 1) $start = max(1, $end - $max_links + 1);

            if ($start > 1) {
                echo '<a href="?'.$base_query.'&page=1">1</a>';
                if ($start > 2) echo '<span class="disabled">...</span>';
            }
            for ($i = $start; $i <= $end; $i++) {
                if ($i == $page) {
                    echo '<span class="active">'.$i.'</span>';
                } else {
                    echo '<a href="?'.$base_query.'&page='.$i.'">'.$i.'</a>';
                }
            }
            if ($end < $total_pages) {
                if ($end < $total_pages - 1) echo '<span class="disabled">...</span>';
                echo '<a href="?'.$base_query.'&page='.$total_pages.'">'.$total_pages.'</a>';
            }

            if ($page < $total_pages) {
                echo '<a href="?'.$base_query.'&page='.($page+1).'">Next &raquo;</a>';
            } else {
                echo '<span class="disabled">Next &raquo;</span>';
            }
            ?>
        </div>

        <div class="scroll-container" role="region" aria-labelledby="table-title">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Perusahaan</th>
                        <th>Nama Supplier</th>
                        <th>WhatsApp</th>
                        <th>ID Barang</th>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Harga Beli</th>
                        <th>Stok</th>
                        <th>Tanggal Pembelian</th>
                        <th>Qty Dibeli</th>
                        <th>Harga Beli (Transaksi)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = $offset + 1;
                    $current_supplier = '';
                    if (count($data_barang) === 0) {
                        echo '<tr><td colspan="12" style="text-align:center; padding:18px;">Tidak ada data</td></tr>';
                    } else {
                        foreach ($data_barang as $row):
                            $is_new_supplier = ($current_supplier !== $row['supplier_company']);
                            $current_supplier = $row['supplier_company'];
                    ?>
                        <tr <?php echo $is_new_supplier ? 'style="border-top:2px solid #007bff;"' : ''; ?>>
                            <td class="number"><?php echo $no++; ?></td>
                            <td class="supplier-name"><?php echo htmlspecialchars($row['supplier_company']); ?></td>
                            <td><?php echo htmlspecialchars($row['supplier_nama']); ?></td>
                            <td><?php echo htmlspecialchars($row['supplier_wa']); ?></td>
                            <td class="number"><?php echo htmlspecialchars($row['barang_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['barang_kode']); ?></td>
                            <td><?php echo htmlspecialchars($row['barang_nama']); ?></td>
                            <td class="number">Rp <?php echo number_format($row['barang_harga_beli'], 0, ',', '.'); ?></td>
                            <td class="number"><?php echo number_format($row['barang_stock']); ?></td>
                            <td><?php echo htmlspecialchars($row['tanggal_pembelian']); ?></td>
                            <td class="number"><?php echo number_format($row['qty_dibeli']); ?></td>
                            <td class="number">Rp <?php echo number_format($row['harga_beli'], 0, ',', '.'); ?></td>
                        </tr>
                    <?php
                        endforeach;
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="pagination" aria-label="Pagination Bottom">
            <?php
            $base_query = build_query($current_params, ['page']);
            if ($page > 1) {
                echo '<a href="?'.$base_query.'&page='.($page-1).'">&laquo; Prev</a>';
            } else {
                echo '<span class="disabled">&laquo; Prev</span>';
            }

            $max_links = 7;
            $start = max(1, $page - intval($max_links/2));
            $end = min($total_pages, $start + $max_links - 1);
            if ($end - $start < $max_links - 1) $start = max(1, $end - $max_links + 1);

            if ($start > 1) {
                echo '<a href="?'.$base_query.'&page=1">1</a>';
                if ($start > 2) echo '<span class="disabled">...</span>';
            }
            for ($i = $start; $i <= $end; $i++) {
                if ($i == $page) {
                    echo '<span class="active">'.$i.'</span>';
                } else {
                    echo '<a href="?'.$base_query.'&page='.$i.'">'.$i.'</a>';
                }
            }
            if ($end < $total_pages) {
                if ($end < $total_pages - 1) echo '<span class="disabled">...</span>';
                echo '<a href="?'.$base_query.'&page='.$total_pages.'">'.$total_pages.'</a>';
            }

            if ($page < $total_pages) {
                echo '<a href="?'.$base_query.'&page='.($page+1).'">Next &raquo;</a>';
            } else {
                echo '<span class="disabled">Next &raquo;</span>';
            }
            ?>
        </div>

        <div class="footer">
            <p>
                <strong>Informasi:</strong><br>
                • Data menampilkan transaksi terakhir per barang per supplier untuk cabang 0.<br>
                • Halaman dimuat pada: <?php echo date('d/m/Y H:i:s'); ?>.<br>
                • Menampilkan <?php echo number_format(count($data_barang)); ?> baris pada halaman ini — total <?php echo number_format($total_barang); ?> barang dari <?php echo number_format($total_supplier); ?> supplier.
            </p>
        </div>
    </div>

    <script>
        document.getElementById('per_page')?.addEventListener('change', function(){
            this.form.submit();
        });
    </script>
</body>
</html>

<?php
$conn->close();
