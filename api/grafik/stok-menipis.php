<?php
include '../../aksi/functions.php';

// Query untuk mengambil data dari database
$q = "SELECT barang.barang_id, barang.barang_kode, barang.barang_nama, barang.barang_harga, barang.barang_stock, barang.barang_cabang FROM barang WHERE barang_cabang = '0' && barang_stock < 10 ORDER BY barang_stock ASC LIMIT 10;";

// Mengambil data dari query
$get = query($q);

// Format data untuk Chart.js
$data = [
  'labels' => [],
  'datasets' => [
    [
      'label' => 'Barang Terlaris',
      'backgroundColor' => [],
      'borderColor' => [],
      'borderWidth' => 1,
      'data' => []
    ]
  ]
];

// Looping untuk mengisi labels, data, dan warna dinamis
foreach ($get as $row) {
  $data['labels'][] = $row['barang_nama']; // Tambahkan nama barang ke labels
  $data['datasets'][0]['data'][] = (int)$row['barang_stock']; // Tambahkan total penjualan ke data

  // Generate warna secara dinamis
  $color = getRandomColor();
  $data['datasets'][0]['backgroundColor'][] = $color . '0.2)'; // Background dengan opacity 0.2
  // $data['datasets'][0]['borderColor'][] = $color . '1)'; // Border dengan opacity 1
}

// Mengirimkan hasil dalam format JSON
header('Content-Type: application/json');
echo json_encode($data);
