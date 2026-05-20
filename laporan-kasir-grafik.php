<?php
function getGrafikData($conn, $tanggal_awal, $tanggal_akhir, $sessionCabang, $whereuser)
{
  $q = "SELECT
        DATE(invoice.invoice_date) AS invoice_date,
        SUM(invoice.invoice_total) AS invoice_total,
        COUNT(invoice.invoice_id) AS total_invoices,
        user.user_nama
        FROM
        invoice
        LEFT JOIN user ON user.user_id = invoice.invoice_kasir
        WHERE
            invoice.invoice_date BETWEEN '" . $tanggal_awal . "' AND '" . $tanggal_akhir . "' 
            AND invoice.invoice_cabang = '" . $sessionCabang . "' 
            $whereuser
        GROUP BY
            invoice.invoice_date, user.user_nama
        ORDER BY
            invoice.invoice_date ASC;";
  $queryes = $conn->query($q);

  $dataGrafik = [
    'labels' => [],
    'datasets' => []
  ];

  // Mengumpulkan semua tanggal unik
  $uniqueDates = [];
  foreach ($queryes as $row) {
    $invoiceDate = $row['invoice_date'];
    if (!in_array($invoiceDate, $uniqueDates)) {
      $uniqueDates[] = $invoiceDate;
    }
  }

  // Mengumpulkan data per kasir dan menambahkan warna dinamis
  $datasets = [];
  $colorMapping = []; // Untuk menyimpan warna unik per kasir 
  
  foreach ($queryes as $row) {
    $userNama = $row['user_nama'];

    // Buat warna dinamis untuk setiap kasir hanya satu kali
    if (!isset($colorMapping[$userNama])) {
      $color = getRandomColor();
      $colorMapping[$userNama] = [
        'bg' => $color . '0.2)', // Background dengan opacity 0.2
        'border' => $color . '1)' // Border dengan opacity 1
      ];
    }

    // Jika dataset untuk kasir ini belum ada, buat dataset
    if (!isset($datasets[$userNama])) {
      $datasets[$userNama] = [
        'label' => $userNama,
        'data' => array_fill(0, count($uniqueDates), 0), // Isi awal dengan 0
        'backgroundColor' => $colorMapping[$userNama]['bg'],
        'borderColor' => $colorMapping[$userNama]['border'],
        'borderWidth' => 1,
      ];
    }

    // Mengisi data pembayaran pada tanggal yang sesuai
    $index = array_search($row['invoice_date'], $uniqueDates); // Cari indeks dari tanggal
    if ($index !== false) {
      $datasets[$userNama]['data'][$index] = (int)$row['invoice_total'];
    }
  }

  // Menambahkan tanggal unik sebagai labels
  $dataGrafik['labels'] = $uniqueDates;

  // Menambahkan dataset ke data utama
  foreach ($datasets as $dataset) {
    $dataGrafik['datasets'][] = $dataset;
  }

  return json_encode($dataGrafik);
}
