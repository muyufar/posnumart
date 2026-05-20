<?php
header('Content-Type: application/json');
include '../aksi/koneksi.php';
session_start();

// Cek login
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit;
}

try {
    // Get parameters
    $tanggal = $_GET['tanggal'] ?? date('Y-m-d');
    $supplier_id = $_GET['supplier_id'] ?? '';
    $kategori_id = $_GET['kategori_id'] ?? '';
    $status_pembayaran = $_GET['status_pembayaran'] ?? '';

    // Get user cabang for filtering
    $user_cabang = $_SESSION['user_cabang'] ?? 0;
    $is_super_admin = ($_SESSION['user_level'] ?? '') === 'super admin';

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

    // Add kategori filter to WHERE clause if specified
    if (!empty($kategori_id)) {
        $where_conditions[] = "b.kategori_id = ?";
        $params[] = $kategori_id;
        $param_types .= "i";
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Main query to get purchase product data
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

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'pembelian_id' => $row['pembelian_id'],
            'barang_id' => $row['barang_id'],
            'nama_barang' => $row['barang_nama'],
            'kategori' => $row['kategori_nama'] ?: '-',
            'satuan' => $row['satuan_nama'] ?: '-',
            'qty' => intval($row['barang_qty']),
            'harga_satuan' => floatval($row['barang_harga_beli']),
            'total_harga' => floatval($row['total_harga']),
            'supplier' => $row['supplier_company'] ?: $row['supplier_nama'],
            'invoice' => $row['pembelian_invoice'],
            'tanggal' => $row['invoice_tgl'],
            'status' => $row['status']
        ];
    }

    // Get summary data
    $summary_query = "
        SELECT 
            COUNT(DISTINCT ip.pembelian_invoice) as total_invoice,
            COUNT(DISTINCT p.barang_id) as total_produk,
            SUM(p.barang_qty) as total_qty,
            SUM(p.barang_qty * p.barang_harga_beli) as total_nilai
        FROM pembelian p
        INNER JOIN invoice_pembelian ip ON p.pembelian_invoice_parent = ip.pembelian_invoice_parent
        LEFT JOIN barang b ON p.barang_id = b.barang_id
        WHERE $where_clause
    ";

    $summary_stmt = $conn->prepare($summary_query);
    if ($summary_stmt === false) {
        throw new Exception("Summary query preparation failed: " . $conn->error);
    }
    $summary_stmt->bind_param($param_types, ...$params);
    $summary_stmt->execute();
    $summary = $summary_stmt->get_result()->fetch_assoc();

    // Prepare response
    $response = [
        'success' => true,
        'data' => $data,
        'summary' => [
            'total_invoice' => intval($summary['total_invoice']),
            'total_produk' => intval($summary['total_produk']),
            'total_qty' => intval($summary['total_qty']),
            'total_nilai' => floatval($summary['total_nilai'])
        ],
        'filters' => [
            'tanggal' => $tanggal,
            'supplier_id' => $supplier_id,
            'kategori_id' => $kategori_id,
            'status_pembayaran' => $status_pembayaran
        ]
    ];

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
