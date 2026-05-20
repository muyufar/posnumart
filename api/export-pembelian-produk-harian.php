<?php
include '../aksi/koneksi.php';
session_start();

// Cek login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get parameters
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$supplier_id = $_GET['supplier_id'] ?? '';
$kategori_id = $_GET['kategori_id'] ?? '';
$status_pembayaran = $_GET['status_pembayaran'] ?? '';

// Get user cabang for filtering
$user_cabang = $_SESSION['user_cabang'] ?? 0;
$is_super_admin = ($_SESSION['user_level'] ?? '') === 'super admin';

try {
    // Build WHERE clause
    $where_conditions = ["ip.invoice_date = ?"];
    $params = [$tanggal];
    $param_types = "s";

    // Add supplier filter
    if (!empty($supplier_id)) {
        $where_conditions[] = "ip.invoice_supplier = ?";
        $params[] = $supplier_id;
        $param_types .= "i";
    }

    // Add status filter
    if (!empty($status_pembayaran)) {
        if ($status_pembayaran === 'lunas') {
            $where_conditions[] = "ip.invoice_hutang < 1";
        } elseif ($status_pembayaran === 'hutang') {
            $where_conditions[] = "ip.invoice_hutang >= 1";
        }
    }

    // Add cabang filter for non-super admin
    if (!$is_super_admin) {
        $where_conditions[] = "ip.invoice_pembelian_cabang = ?";
        $params[] = $user_cabang;
        $param_types .= "i";
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Query to get all purchase product data for export
    $query = "
        SELECT 
            p.pembelian_id,
            p.barang_id,
            b.barang_nama,
            k.kategori_nama,
            s.satuan_nama,
            p.barang_qty,
            p.barang_harga_beli,
            (p.barang_qty * p.barang_harga_beli) as total_harga,
            sup.supplier_nama,
            sup.supplier_company,
            ip.pembelian_invoice,
            ip.invoice_tgl,
            CASE 
                WHEN ip.invoice_hutang < 1 THEN 'Lunas'
                ELSE 'Hutang'
            END as status
        FROM pembelian p
        INNER JOIN invoice_pembelian ip ON p.pembelian_invoice_parent = ip.pembelian_invoice_parent
        LEFT JOIN barang b ON p.barang_id = b.barang_id
        LEFT JOIN kategori k ON b.kategori_id = k.kategori_id
        LEFT JOIN satuan s ON b.barang_satuan_id = s.satuan_id
        LEFT JOIN supplier sup ON ip.invoice_supplier = sup.supplier_id
        WHERE $where_clause
        ORDER BY b.barang_nama, ip.pembelian_invoice
    ";

    // Add kategori filter to WHERE clause if specified
    if (!empty($kategori_id)) {
        $where_conditions[] = "b.kategori_id = ?";
        $params[] = $kategori_id;
        $param_types .= "i";
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Query to get all purchase product data for export
    $query = "
        SELECT 
            p.pembelian_id,
            p.barang_id,
            b.barang_nama,
            k.kategori_nama,
            s.satuan_nama,
            p.barang_qty,
            p.barang_harga_beli,
            (p.barang_qty * p.barang_harga_beli) as total_harga,
            sup.supplier_nama,
            sup.supplier_company,
            ip.pembelian_invoice,
            ip.invoice_tgl,
            CASE 
                WHEN ip.invoice_hutang < 1 THEN 'Lunas'
                ELSE 'Hutang'
            END as status
        FROM pembelian p
        INNER JOIN invoice_pembelian ip ON p.pembelian_invoice_parent = ip.pembelian_invoice_parent
        LEFT JOIN barang b ON p.barang_id = b.barang_id
        LEFT JOIN kategori k ON b.kategori_id = k.kategori_id
        LEFT JOIN satuan s ON b.barang_satuan_id = s.satuan_id
        LEFT JOIN supplier sup ON ip.invoice_supplier = sup.supplier_id
        WHERE $where_clause
        ORDER BY b.barang_nama, ip.pembelian_invoice
    ";

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // Set headers for Excel download
    $filename = "Laporan_Pembelian_Produk_" . date('Y-m-d', strtotime($tanggal)) . ".xls";
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // Start Excel content
    echo "<table border='1'>";
    echo "<tr>";
    echo "<th colspan='10' style='background-color: #4CAF50; color: white; text-align: center; font-weight: bold; font-size: 16px;'>LAPORAN PEMBELIAN PER PRODUK HARIAN</th>";
    echo "</tr>";
    echo "<tr>";
    echo "<th colspan='10' style='background-color: #f2f2f2; text-align: center;'>Tanggal: " . date('d/m/Y', strtotime($tanggal)) . "</th>";
    echo "</tr>";
    echo "<tr>";
    echo "<th colspan='10' style='background-color: #f2f2f2; text-align: center;'>Tanggal Export: " . date('d/m/Y H:i:s') . "</th>";
    echo "</tr>";
    echo "<tr></tr>"; // Empty row

    // Headers
    echo "<tr style='background-color: #2196F3; color: white; font-weight: bold;'>";
    echo "<th>No</th>";
    echo "<th>Nama Barang</th>";
    echo "<th>Kategori</th>";
    echo "<th>Satuan</th>";
    echo "<th>Qty</th>";
    echo "<th>Harga Satuan</th>";
    echo "<th>Total Harga</th>";
    echo "<th>Supplier</th>";
    echo "<th>Invoice</th>";
    echo "<th>Status</th>";
    echo "</tr>";

    $no = 1;
    $total_qty = 0;
    $total_nilai = 0;
    $current_barang = '';
    $barang_count = 0;

    while ($row = $result->fetch_assoc()) {
        $total_qty += intval($row['barang_qty']);
        $total_nilai += floatval($row['total_harga']);

        // Group by barang
        if ($current_barang !== $row['barang_nama']) {
            if ($current_barang !== '') {
                echo "<tr style='background-color: #f9f9f9; font-weight: bold;'>";
                echo "<td colspan='4' style='text-align: center;'>SUB TOTAL " . $current_barang . "</td>";
                echo "<td style='text-align: right;'>" . number_format($barang_qty, 0, ',', '.') . "</td>";
                echo "<td style='text-align: right;'>" . number_format($barang_harga_satuan, 0, ',', '.') . "</td>";
                echo "<td style='text-align: right;'>" . number_format($barang_total, 0, ',', '.') . "</td>";
                echo "<td colspan='3'></td>";
                echo "</tr>";
            }
            $current_barang = $row['barang_nama'];
            $barang_qty = 0;
            $barang_harga_satuan = floatval($row['barang_harga_beli']);
            $barang_total = 0;
        }

        $barang_qty += intval($row['barang_qty']);
        $barang_total += floatval($row['total_harga']);

        echo "<tr>";
        echo "<td>" . $no . "</td>";
        echo "<td>" . $row['barang_nama'] . "</td>";
        echo "<td>" . ($row['kategori_nama'] ?: '-') . "</td>";
        echo "<td>" . ($row['satuan_nama'] ?: '-') . "</td>";
        echo "<td style='text-align: right;'>" . number_format($row['barang_qty'], 0, ',', '.') . "</td>";
        echo "<td style='text-align: right;'>" . number_format($row['barang_harga_beli'], 0, ',', '.') . "</td>";
        echo "<td style='text-align: right;'>" . number_format($row['total_harga'], 0, ',', '.') . "</td>";
        echo "<td>" . ($row['supplier_company'] ?: $row['supplier_nama']) . "</td>";
        echo "<td>" . $row['pembelian_invoice'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "</tr>";
        $no++;
    }

    // Last barang subtotal
    if ($current_barang !== '') {
        echo "<tr style='background-color: #f9f9f9; font-weight: bold;'>";
        echo "<td colspan='4' style='text-align: center;'>SUB TOTAL " . $current_barang . "</td>";
        echo "<td style='text-align: right;'>" . number_format($barang_qty, 0, ',', '.') . "</td>";
        echo "<td style='text-align: right;'>" . number_format($barang_harga_satuan, 0, ',', '.') . "</td>";
        echo "<td style='text-align: right;'>" . number_format($barang_total, 0, ',', '.') . "</td>";
        echo "<td colspan='3'></td>";
        echo "</tr>";
    }

    // Grand total row
    echo "<tr style='background-color: #f2f2f2; font-weight: bold;'>";
    echo "<td colspan='4' style='text-align: center;'>TOTAL KESELURUHAN</td>";
    echo "<td style='text-align: right;'>" . number_format($total_qty, 0, ',', '.') . "</td>";
    echo "<td colspan='2' style='text-align: right;'>" . number_format($total_nilai, 0, ',', '.') . "</td>";
    echo "<td colspan='3'></td>";
    echo "</tr>";

    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
