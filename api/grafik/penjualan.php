<?php
include '../../aksi/functions.php';


$month = date('m');
$year = date('Y');

// Query untuk mengambil data dari database
$q = "SELECT 
    DATE(i.invoice_date) AS invoice_date,
    i.invoice_cabang,
    SUM(i.invoice_sub_total) AS total_bayar_lama,
    t.toko_nama
FROM 
    invoice i
LEFT JOIN 
    toko t
ON
    i.invoice_cabang = t.toko_cabang
WHERE 
    MONTH(i.invoice_date) = '$month'
    AND YEAR(i.invoice_date) = '$year'
GROUP BY 
    DATE(i.invoice_date), i.invoice_cabang, t.toko_nama";

// Mengambil data dari query
$get = query($q);

// Format data untuk Chart.js
$data = [
  'labels' => [],
  'datasets' => []
];

// Mengumpulkan semua tanggal unik
$uniqueDates = [];
foreach ($get as $row) {
  $invoiceDate = $row['invoice_date'];
  if (!in_array($invoiceDate, $uniqueDates)) {
    $uniqueDates[] = $invoiceDate;
  }
}

// Mengumpulkan data per cabang dan menambahkan warna dinamis
$datasets = [];
$colorMapping = []; // Untuk menyimpan warna unik per cabang

foreach ($get as $row) {
  $invoiceCabang = $row['invoice_cabang'];
  $tokoNama = $row['toko_nama'] ?: 'Cabang ' . $invoiceCabang;

  // Buat warna dinamis untuk setiap cabang hanya satu kali
  if (!isset($colorMapping[$tokoNama])) {
    $color = getRandomColor();
    $colorMapping[$tokoNama] = [
      'bg' => $color . '0.2)', // Background dengan opacity 0.2
      'border' => $color . '1)' // Border dengan opacity 1
    ];
  }

  // Jika dataset untuk cabang ini belum ada, buat dataset
  if (!isset($datasets[$tokoNama])) {
    $datasets[$tokoNama] = [
      'label' => $tokoNama,
      'data' => array_fill(0, count($uniqueDates), 0), // Isi awal dengan 0
      'backgroundColor' => $colorMapping[$tokoNama]['bg'],
      'borderColor' => $colorMapping[$tokoNama]['border'],
      'borderWidth' => 1,
    ];
  }

  // Mengisi data pembayaran pada tanggal yang sesuai
  $index = array_search($row['invoice_date'], $uniqueDates); // Cari indeks dari tanggal
  if ($index !== false) {
    $datasets[$tokoNama]['data'][$index] = (int)$row['total_bayar_lama'];
  }
}

// Menambahkan tanggal unik sebagai labels
$data['labels'] = $uniqueDates;

// Menambahkan dataset ke data utama
foreach ($datasets as $dataset) {
  $data['datasets'][] = $dataset;
}

// Mengirimkan hasil dalam format JSON
header('Content-Type: application/json');
echo json_encode($data);
