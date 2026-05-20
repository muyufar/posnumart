<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
include 'aksi/koneksi.php';

if ($levelLogin != "admin" && $levelLogin != "super admin") {
  echo "<script>document.location.href = 'bo';</script>";
  exit;
}

$listCabang = query("SELECT * FROM toko");

$tanggal_awal = $_POST['tanggal_awal'] ?? date('Y-m-01');
$tanggal_akhir = $_POST['tanggal_akhir'] ?? date('Y-m-t');
$cabang = $_POST['cabang'] ?? $_SESSION['user_cabang'];

function rupiah($angka)
{
  return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Ambil data toko
$toko = query("SELECT * FROM toko WHERE toko_cabang = '$cabang' ")[0];

/* ========================================================
   1. PENJUALAN (CASH BASIS)
======================================================== */
// Penjualan Cash
$q_cash = mysqli_query($conn, "
  SELECT SUM(invoice_sub_total) AS total_cash
  FROM invoice
  WHERE invoice_piutang = 0
  AND invoice_cabang = '$cabang'
  AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
");
$total_cash = mysqli_fetch_assoc($q_cash)['total_cash'] ?? 0;

// Penjualan Kredit (Piutang, hanya ditampilkan)
$q_kredit = mysqli_query($conn, "
  SELECT SUM(invoice_sub_total) AS total_kredit
  FROM invoice
  WHERE invoice_piutang = 1
  AND invoice_cabang = '$cabang'
  AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
");
$total_kredit = mysqli_fetch_assoc($q_kredit)['total_kredit'] ?? 0;

// DP Kredit (kas diterima dari penjualan kredit)
$q_dp = mysqli_query($conn, "
  SELECT 
    SUM(CASE WHEN invoice_piutang = 1 THEN invoice_piutang_dp ELSE 0 END) AS total_dp
  FROM invoice
  WHERE invoice_cabang = '$cabang'
  AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
");
$total_dp = mysqli_fetch_assoc($q_dp)['total_dp'] ?? 0;


// Total Penjualan Cash Basis (hanya cash + dp)
$total_penjualan_cash_basis = $total_cash + $total_dp;
$totaljualan = $total_cash + $total_kredit;

/* ========================================================
   2. HPP
======================================================== */
$q_hpp = mysqli_query($conn, "
  SELECT SUM(invoice_total_beli) AS total_hpp
  FROM invoice
  WHERE invoice_piutang = 0
  AND invoice_cabang = '$cabang'
  AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
");
$hpp = mysqli_fetch_assoc($q_hpp)['total_hpp'] ?? 0;

/* ========================================================
   3. PENDAPATAN LAIN-LAIN
   CATATAN: Menggunakan l.date (tanggal transaksi dilakukan), BUKAN created_at (tanggal dibuat)
======================================================== */
$q_pendapatan_lain = mysqli_query($conn, "
  SELECT 
    COALESCE(lk.name, 'Tanpa Kategori') AS kategori_nama,
    SUM(CAST(REPLACE(REPLACE(l.jumlah, '.', ''), ',', '') AS DECIMAL(18,2))) AS total 
  FROM laba l
  LEFT JOIN laba_kategori lk ON CAST(l.kategori AS UNSIGNED) = lk.id
  WHERE l.tipe = 0
  AND l.cabang = '$cabang'
  AND l.date >= '$tanggal_awal 00:00:00'
  AND l.date <= '$tanggal_akhir 23:59:59'
  GROUP BY lk.name
  ORDER BY lk.name
");
$pendapatan_lain = [];
$total_pendapatan_lain = 0;
while ($row = mysqli_fetch_assoc($q_pendapatan_lain)) {
  $pendapatan_lain[] = $row;
  $total_pendapatan_lain += $row['total'];
}

/* ========================================================
   4. PENGELUARAN
   CATATAN: Menggunakan l.date (tanggal transaksi dilakukan), BUKAN created_at (tanggal dibuat)
======================================================== */
$q_pengeluaran = mysqli_query($conn, "
  SELECT 
    COALESCE(lk.name, 'Tanpa Kategori') AS kategori_nama,
    SUM(CAST(REPLACE(REPLACE(l.jumlah, '.', ''), ',', '') AS DECIMAL(18,2))) AS total 
  FROM laba l
  LEFT JOIN laba_kategori lk ON CAST(l.kategori AS UNSIGNED) = lk.id
  WHERE l.tipe = 1
  AND l.cabang = '$cabang'
  AND l.date >= '$tanggal_awal 00:00:00'
  AND l.date <= '$tanggal_akhir 23:59:59'
  GROUP BY lk.name
  ORDER BY lk.name
");
$pengeluaran = [];
$total_pengeluaran = 0;
while ($row = mysqli_fetch_assoc($q_pengeluaran)) {
  $pengeluaran[] = $row;
  $total_pengeluaran += $row['total'];
}

/* ========================================================
   5. SHARING PROFIT (Cabang Utama)
======================================================== */
$sharing_profit = 0;
$sharing_detail = [];

if ($cabang == 0) {
  // Cabang 1 (45%)
  $q_laba_cbg1 = mysqli_query($conn, "
    SELECT 
      (SUM(invoice_sub_total) - SUM(invoice_total_beli)
       - COALESCE((SELECT SUM(CAST(REPLACE(REPLACE(l2.jumlah, '.', ''), ',', '') AS DECIMAL(18,2))) 
                   FROM laba l2 WHERE l2.tipe = 1 AND l2.cabang = 1 
                   AND l2.date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'),0)
      ) AS laba_bersih_cabang1
    FROM invoice
    WHERE invoice_cabang = 1
    AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
  ");
  $laba_cbg1 = mysqli_fetch_assoc($q_laba_cbg1)['laba_bersih_cabang1'] ?? 0;
  $sharing_cbg1 = $laba_cbg1 * 0.45;
  $sharing_profit += $sharing_cbg1;
  $sharing_detail[] = ['nama' => 'Sharing Profit NUMART DUKUN (45%)', 'nilai' => $sharing_cbg1];

  // Cabang 3 (50%)
  $q_laba_cbg3 = mysqli_query($conn, "
    SELECT 
      (SUM(invoice_sub_total) - SUM(invoice_total_beli)
       - COALESCE((SELECT SUM(CAST(REPLACE(REPLACE(l2.jumlah, '.', ''), ',', '') AS DECIMAL(18,2))) 
                   FROM laba l2 WHERE l2.tipe = 1 AND l2.cabang = 3 
                   AND l2.date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'),0)
      ) AS laba_bersih_cabang3
    FROM invoice
    WHERE invoice_cabang = 3
    AND invoice_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
  ");
  $laba_cbg3 = mysqli_fetch_assoc($q_laba_cbg3)['laba_bersih_cabang3'] ?? 0;
  $sharing_cbg3 = $laba_cbg3 * 0.50;
  $sharing_profit += $sharing_cbg3;
  $sharing_detail[] = ['nama' => 'Sharing Profit NUMART PONDOK SRUMBUNG (50%)', 'nilai' => $sharing_cbg3];
}

/* ========================================================
   6. PERHITUNGAN LABA (CASH BASIS)
======================================================== */
$total_pendapatan_cash_basis = $total_penjualan_cash_basis + $total_pendapatan_lain;
$laba_kotor = $total_pendapatan_cash_basis - $hpp;
$laba_operasional = $laba_kotor - $total_pengeluaran;
$laba_operasi = $laba_kotor - $total_pengeluaran; // Laba Operasi = Laba Kotor - Beban Operasi
$beban_lain = 0; // Beban Lain (bisa ditambahkan nanti jika diperlukan)
$laba_bersih = $laba_kotor + $sharing_profit - $total_pengeluaran;
$persentase = $total_pendapatan_cash_basis > 0 ? round(($laba_bersih / $total_pendapatan_cash_basis) * 100, 2) : 0;

// Untuk ringkasan: Total Pendapatan = Total Penjualan Cash Basis + Pendapatan Lain + Sharing Profit
$total_pendapatan_ringkasan = $total_pendapatan_cash_basis + $sharing_profit;

/* ========================================================
   7. TOTAL TRANSFER STOK (Cabang Utama)
======================================================== */
$total_transfer_stok = 0;
$transfer_detail = [];

if ($cabang == 0) {
  $q_transfer = mysqli_query($conn, "
    SELECT 
      tpk_penerima_cabang,
      COALESCE(SUM(tpk_qty * b.barang_harga_beli), 0) AS total_transfer
    FROM transfer_produk_keluar tpk
    JOIN barang b ON tpk.tpk_barang_id = b.barang_id
    WHERE tpk_penerima_cabang != 0
      AND tpk.tpk_date BETWEEN '$tanggal_awal' AND '$tanggal_akhir'
    GROUP BY tpk_penerima_cabang
  ");

  while ($row = mysqli_fetch_assoc($q_transfer)) {
    $total_transfer_stok += $row['total_transfer'];
    $nama_cabang = '';
    if ($row['tpk_penerima_cabang'] == 1) {
      $nama_cabang = 'NUMART DUKUN';
    } elseif ($row['tpk_penerima_cabang'] == 3) {
      $nama_cabang = 'NUMART PONDOK SRUMBUNG';
    } else {
      $nama_cabang = 'Cabang ' . $row['tpk_penerima_cabang'];
    }
    $transfer_detail[] = [
      'nama' => 'Transfer Stok ke ' . $nama_cabang,
      'nilai' => $row['total_transfer']
    ];
  }
}
/* ========================================================
   2.1 STOK AWAL & STOK AKHIR
======================================================== */
$q_stok_awal = mysqli_query($conn, "
  SELECT SUM(barang_harga_beli * barang_stock) AS total_stok_awal
  FROM barang
  WHERE barang_cabang = '$cabang'
");
$total_stok_awal = mysqli_fetch_assoc($q_stok_awal)['total_stok_awal'] ?? 0;

$q_stok_akhir = mysqli_query($conn, "
  SELECT SUM(barang_harga_beli * barang_stock) AS total_stok_akhir
  FROM barang
  WHERE barang_cabang = '$cabang'
");
$total_stok_akhir = mysqli_fetch_assoc($q_stok_akhir)['total_stok_akhir'] ?? 0;

/* ========================================================
    8. LAPORAN NERACA
======================================================== */
// Escape input untuk keamanan
$cabang_escaped = mysqli_real_escape_string($conn, $cabang);
$tanggal_awal_escaped = mysqli_real_escape_string($conn, $tanggal_awal);
$tanggal_akhir_escaped = mysqli_real_escape_string($conn, $tanggal_akhir);

// Debug: Cek semua kategori yang ada di laba_kategori
$q_debug_kategori = mysqli_query($conn, "SELECT DISTINCT kategori FROM laba_kategori WHERE kategori IS NOT NULL");
$kategori_terdeteksi = [];
if ($q_debug_kategori) {
  while ($row = mysqli_fetch_assoc($q_debug_kategori)) {
    $kategori_terdeteksi[] = $row['kategori'];
  }
}

// Debug: Cek kategori yang digunakan di tabel laba untuk periode ini
$q_debug_laba_kategori = mysqli_query($conn, "
  SELECT DISTINCT l.kategori, lk.name, lk.kategori as laba_kategori, COUNT(*) as jumlah
  FROM laba l
  LEFT JOIN laba_kategori lk ON l.kategori = lk.id
  WHERE l.cabang = '$cabang_escaped'
  AND l.date >= '$tanggal_awal_escaped 00:00:00'
  AND l.date <= '$tanggal_akhir_escaped 23:59:59'
  GROUP BY l.kategori, lk.name, lk.kategori
  ORDER BY jumlah DESC
");
$kategori_laba_terdeteksi = [];
if ($q_debug_laba_kategori) {
  while ($row = mysqli_fetch_assoc($q_debug_laba_kategori)) {
    $kategori_laba_terdeteksi[] = [
      'kategori_id' => $row['kategori'],
      'kategori_name' => $row['name'],
      'laba_kategori' => $row['laba_kategori'],
      'jumlah' => $row['jumlah']
    ];
  }
}

// Ambil semua kategori dari laba_kategori dengan saldo awal
$q_kategori_neraca = mysqli_query($conn, "
  SELECT
    lk.id,
    lk.name,
    lk.kode_akun,
    lk.kategori,
    lk.tipe_akun,
    COALESCE(lk.saldo, 0) AS saldo_awal
  FROM laba_kategori lk
  ORDER BY lk.kategori, lk.name
");

// Check for query errors
if (!$q_kategori_neraca) {
  die("Query error: " . mysqli_error($conn));
}

$neraca = [
  'aktiva' => [],
  'pasiva' => [],
  'modal' => []
];

$total_aktiva = 0;
$total_pasiva = 0;
$total_modal = 0;

$jumlah_kategori_ditemukan = 0;
$debug_detail_kategori = [];

while ($row_kat = mysqli_fetch_assoc($q_kategori_neraca)) {
  $kat_id = mysqli_real_escape_string($conn, $row_kat['id']);
  $kategori_raw = trim($row_kat['kategori'] ?? '');

  // Gunakan data langsung dari database (case-insensitive)
  $kategori_raw_lower = strtolower(trim($kategori_raw));

  // Cek langsung apakah kategori adalah aktiva, pasiva, atau modal
  $kategori = null;
  if (in_array($kategori_raw_lower, ['aktiva', 'pasiva', 'modal'])) {
    $kategori = $kategori_raw_lower;
  }

  // Debug: simpan semua kategori yang diproses (termasuk yang di-skip)
  $debug_kategori_check = [
    'id' => $row_kat['id'],
    'name' => $row_kat['name'],
    'kategori_raw' => $kategori_raw,
    'kategori_normalized' => $kategori_raw_lower,
    'kategori_mapped' => $kategori
  ];

  // Skip jika bukan kategori neraca
  if (!$kategori) {
    $debug_detail_kategori[] = array_merge($debug_kategori_check, ['skipped' => true]);
    continue;
  }

  // Debug: tambahkan info untuk kategori yang TIDAK di-skip
  $debug_kategori_info = array_merge($debug_kategori_check, ['skipped' => false]);

  $jumlah_kategori_ditemukan++;
  $tipe_akun = $row_kat['tipe_akun'] ?? '';
  $saldo_awal = floatval($row_kat['saldo_awal'] ?? 0);

  // Update debug info dengan data lengkap
  $debug_kategori_info['kategori'] = $kategori;
  $debug_kategori_info['saldo_awal'] = $saldo_awal;
  $debug_kategori_info['tipe_akun'] = $tipe_akun;

  // Hitung mutasi dari tabel laba dalam periode
  // Sederhanakan: konversi l.kategori ke integer untuk matching dengan lk.id
  $q_mutasi = mysqli_query($conn, "
    SELECT 
      COALESCE(SUM(CASE 
        WHEN l.tipe = 0 AND l.jumlah IS NOT NULL AND l.jumlah != '' AND l.jumlah != '0'
        THEN CAST(REPLACE(REPLACE(REPLACE(l.jumlah, '.', ''), ',', ''), ' ', '') AS DECIMAL(18,2)) 
        ELSE 0 
      END), 0) AS total_masuk,
      COALESCE(SUM(CASE 
        WHEN l.tipe = 1 AND l.jumlah IS NOT NULL AND l.jumlah != '' AND l.jumlah != '0'
        THEN CAST(REPLACE(REPLACE(REPLACE(l.jumlah, '.', ''), ',', ''), ' ', '') AS DECIMAL(18,2)) 
        ELSE 0 
      END), 0) AS total_keluar
    FROM laba l
    WHERE CAST(l.kategori AS UNSIGNED) = $kat_id
    AND l.cabang = '$cabang_escaped'
    AND l.date >= '$tanggal_awal_escaped 00:00:00'
    AND l.date <= '$tanggal_akhir_escaped 23:59:59'
  ");

  if (!$q_mutasi) {
    error_log("Error query mutasi untuk kategori $kat_id: " . mysqli_error($conn));
    continue;
  }

  $mutasi = mysqli_fetch_assoc($q_mutasi);
  $total_masuk = floatval($mutasi['total_masuk'] ?? 0);
  $total_keluar = floatval($mutasi['total_keluar'] ?? 0);

  // Debug: simpan hasil mutasi
  $debug_kategori_info['mutasi'] = [
    'total_masuk' => $total_masuk,
    'total_keluar' => $total_keluar
  ];

  // Debug: cek jumlah transaksi yang match untuk periode ini
  $q_count = mysqli_query($conn, "
    SELECT COUNT(*) as cnt 
    FROM laba 
    WHERE CAST(kategori AS UNSIGNED) = $kat_id
    AND cabang = '$cabang_escaped'
    AND date >= '$tanggal_awal_escaped 00:00:00'
    AND date <= '$tanggal_akhir_escaped 23:59:59'
  ");
  $count_transaksi = 0;
  if ($q_count) {
    $row_count = mysqli_fetch_assoc($q_count);
    $count_transaksi = $row_count['cnt'] ?? 0;
  }

  $debug_kategori_info['jumlah_transaksi'] = $count_transaksi;

  // Hitung saldo akhir berdasarkan konsep akuntansi sederhana
  // Konsep: Masuk (tipe=0) dan Keluar (tipe=1) mempengaruhi saldo berdasarkan tipe akun
  $saldo_akhir = 0;

  if ($kategori == 'aktiva') {
    // Aktiva: normal saldo DEBIT (bertambah di debit, berkurang di kredit)
    // Masuk (tipe=0) = Debit = Menambah aktiva
    // Keluar (tipe=1) = Kredit = Mengurangi aktiva
    if ($tipe_akun == 'debit') {
      $saldo_akhir = $saldo_awal + $total_masuk - $total_keluar;
    } else {
      // Jika tipe_akun = 'kredit' (kontra aktiva), kebalikannya
      $saldo_akhir = $saldo_awal - $total_masuk + $total_keluar;
    }
  } else if ($kategori == 'pasiva') {
    // Pasiva: normal saldo KREDIT (bertambah di kredit, berkurang di debit)
    // Masuk (tipe=0) = Debit = Mengurangi pasiva (bayar hutang)
    // Keluar (tipe=1) = Kredit = Menambah pasiva (terima hutang baru)
    if ($tipe_akun == 'kredit') {
      $saldo_akhir = $saldo_awal - $total_masuk + $total_keluar;
    } else {
      // Jika tipe_akun = 'debit' (kontra pasiva), kebalikannya
      $saldo_akhir = $saldo_awal + $total_masuk - $total_keluar;
    }
  } else if ($kategori == 'modal') {
    // Modal: normal saldo KREDIT (bertambah di kredit, berkurang di debit)
    // Masuk (tipe=0) = bisa setoran modal atau laba = Menambah modal
    // Keluar (tipe=1) = prive atau rugi = Mengurangi modal
    if ($tipe_akun == 'kredit') {
      $saldo_akhir = $saldo_awal + $total_masuk - $total_keluar;
    } else {
      // Jika tipe_akun = 'debit' (kontra modal), kebalikannya
      $saldo_akhir = $saldo_awal - $total_masuk + $total_keluar;
    }
  }

  // Simpan data - TAMPILKAN SEMUA kategori neraca meskipun saldo 0
  $kode_akun = !empty($row_kat['kode_akun']) ? trim($row_kat['kode_akun']) : '-';

  // Extract prefix untuk grouping (misal: 1-10001 -> 1-100, 1-10705 -> 1-107)
  $prefix_group = '-';
  if ($kode_akun != '-' && preg_match('/^(\d+)-(\d{3})/', $kode_akun, $matches)) {
    $prefix_group = $matches[1] . '-' . $matches[2];
  } elseif ($kode_akun != '-' && preg_match('/^(\d+)-/', $kode_akun, $matches)) {
    $prefix_group = $matches[1] . '-';
  }

  $data_neraca = [
    'id' => $row_kat['id'],
    'kode_akun' => $kode_akun,
    'prefix_group' => $prefix_group,
    'name' => !empty($row_kat['name']) ? $row_kat['name'] : '-',
    'tipe_akun' => !empty($tipe_akun) ? $tipe_akun : '-',
    'saldo_awal' => $saldo_awal,
    'total_masuk' => $total_masuk,
    'total_keluar' => $total_keluar,
    'saldo_akhir' => $saldo_akhir
  ];

  // Debug: tambahkan info ke debug
  $debug_kategori_info['saldo_akhir'] = $saldo_akhir;
  $debug_kategori_info['masuk_neraca'] = $kategori;

  // Masukkan ke array neraca hanya jika saldo akhir != 0
  if ($saldo_akhir != 0) {
    if ($kategori == 'aktiva') {
      $neraca['aktiva'][] = $data_neraca;
      $total_aktiva += $saldo_akhir;
      $debug_kategori_info['masuk_array'] = 'aktiva';
    } else if ($kategori == 'pasiva') {
      $neraca['pasiva'][] = $data_neraca;
      $total_pasiva += $saldo_akhir;
      $debug_kategori_info['masuk_array'] = 'pasiva';
    } else if ($kategori == 'modal') {
      $neraca['modal'][] = $data_neraca;
      $total_modal += $saldo_akhir;
      $debug_kategori_info['masuk_array'] = 'modal';
    } else {
      $debug_kategori_info['masuk_array'] = 'TIDAK MASUK - kategori: ' . $kategori;
    }
  } else {
    $debug_kategori_info['masuk_array'] = 'DI-SKIP - saldo 0';
  }

  // Pastikan debug info masuk ke array
  $debug_detail_kategori[] = $debug_kategori_info;
}

// Total Pasiva & Modal
$total_pasiva_modal = $total_pasiva + $total_modal;

// Grouping berdasarkan prefix kode akun (langsung dari data, tanpa mapping manual)
function groupByPrefix($items)
{
  $grouped = [];
  foreach ($items as $item) {
    $prefix = $item['prefix_group'];
    if (!isset($grouped[$prefix])) {
      $grouped[$prefix] = [
        'name' => $prefix,
        'items' => [],
        'total' => 0
      ];
    }
    $grouped[$prefix]['items'][] = $item;
    $grouped[$prefix]['total'] += $item['saldo_akhir'];
  }
  return $grouped;
}

// Group aktiva, pasiva, dan modal berdasarkan prefix kode akun
$aktiva_grouped = !empty($neraca['aktiva']) ? groupByPrefix($neraca['aktiva']) : [];
$pasiva_grouped = !empty($neraca['pasiva']) ? groupByPrefix($neraca['pasiva']) : [];
$modal_grouped = !empty($neraca['modal']) ? groupByPrefix($neraca['modal']) : [];

// Hitung total harta lancar dan tetap berdasarkan prefix
$total_harta_lancar = 0;
$total_harta_tetap = 0;

foreach ($aktiva_grouped as $prefix => $group) {
  // Tentukan jenis berdasarkan prefix: 1-100, 1-101, 1-102, 1-200 = lancar; 1-107, 1-108, dll = tetap
  if (preg_match('/^1-(100|101|102|200|201|202)/', $prefix)) {
    $total_harta_lancar += $group['total'];
  } elseif (preg_match('/^1-(107|108|109|110)/', $prefix)) {
    $total_harta_tetap += $group['total'];
  }
}

// Debug: Cek apakah ada data laba untuk periode ini
$q_debug_laba = mysqli_query($conn, "
  SELECT COUNT(*) as total_laba, 
         COUNT(DISTINCT kategori) as kategori_berbeda
  FROM laba 
  WHERE cabang = '$cabang_escaped'
  AND date >= '$tanggal_awal_escaped 00:00:00'
  AND date <= '$tanggal_akhir_escaped 23:59:59'
");
$debug_info = [
  'kategori_ditemukan' => $jumlah_kategori_ditemukan,
  'kategori_terdeteksi' => $kategori_terdeteksi,
  'kategori_laba_terdeteksi' => $kategori_laba_terdeteksi,
  'cabang' => $cabang,
  'periode' => $tanggal_awal . ' - ' . $tanggal_akhir,
  'detail_kategori' => $debug_detail_kategori,
  'total_aktiva' => $total_aktiva,
  'total_pasiva' => $total_pasiva,
  'total_modal' => $total_modal
];
if ($q_debug_laba) {
  $debug_laba = mysqli_fetch_assoc($q_debug_laba);
  $debug_info['total_transaksi_laba'] = $debug_laba['total_laba'] ?? 0;
  $debug_info['kategori_berbeda_laba'] = $debug_laba['kategori_berbeda'] ?? 0;
}

?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <h1>Laporan Laba Rugi (Cash Basis)</h1>
      <small>Periode <?= date('d M Y', strtotime($tanggal_awal)) ?> - <?= date('d M Y', strtotime($tanggal_akhir)) ?></small>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">

      <!-- Filter -->
      <div class="card card-default">
        <div class="card-header">
          <h3 class="card-title">Filter Data</h3>
        </div>
        <form method="POST">
          <div class="card-body">
            <div class="row">
              <div class="col-md-3">
                <label>Tanggal Awal</label>
                <input type="date" name="tanggal_awal" class="form-control" value="<?= $tanggal_awal ?>">
              </div>
              <div class="col-md-3">
                <label>Tanggal Akhir</label>
                <input type="date" name="tanggal_akhir" class="form-control" value="<?= $tanggal_akhir ?>">
              </div>
              <div class="col-md-3">
                <label>Cabang</label>
                <select name="cabang" class="form-control" <?= $levelLogin == 'super admin' ? '' : 'disabled' ?>>
                  <?php foreach ($listCabang as $cab) : ?>
                    <option value="<?= $cab['toko_cabang'] ?>" <?= $cab['toko_cabang'] == $cabang ? 'selected' : '' ?>>
                      <?= $cab['toko_nama'] ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label>&nbsp;</label><br>
                <button class="btn btn-primary"><i class="fa fa-filter"></i> Tampilkan</button>
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- Laporan -->
      <div class="card card-primary">
        <div class="card-header">
          <h3 class="card-title">Ringkasan Laporan</h3>
        </div>
        <div class="card-body">

          <!-- Laporan Laba Rugi dalam format yang lebih rapi -->
          <table class="table table-bordered">
            <thead>
              <tr>
                <th colspan="2" class="bg-primary text-white">1. PENDAPATAN</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>a. Penjualan Cash</td>
                <td class="text-right"><?= rupiah($total_penjualan_cash_basis) ?></td>
              </tr>
              <tr>
                <td>b. Penjualan Kredit</td>
                <td class="text-right"><?= rupiah($total_kredit) ?></td>
              </tr>
              <tr>
                <td>c. Total Penjualan</td>
                <td class="text-right"><?= rupiah($totaljualan) ?></td>
              </tr>
              <tr>
                <td>d. Pendapatan Lain</td>
                <td class="text-right"><?= rupiah($total_pendapatan_lain) ?></td>
              </tr>
              <?php if ($cabang == 0 && !empty($sharing_detail)) : ?>
                <tr>
                  <td>e. Sharing Profit</td>
                  <td class="text-right"><?= rupiah($sharing_profit) ?></td>
                </tr>
              <?php endif; ?>
              <tr class="table-info">
                <td><b>Total Penjualan</b></td>
                <td class="text-right"><b><?= rupiah($total_pendapatan_ringkasan) ?></b></td>
              </tr>
            </tbody>
          </table>

          <!-- HPP -->
          <table class="table table-bordered">
            <thead>
              <tr>
                <th colspan="2" class="bg-primary text-white"><?= $cabang == 0 && !empty($transfer_detail) ? '2. HPP' : '2. HPP' ?></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>a. Harga Pokok Penjualan</td>
                <td class="text-right"><?= rupiah($hpp) ?></td>
              </tr>
              <tr class="table-info">
                <td><b>Laba Kotor</b></td>
                <td class="text-right"><b><?= rupiah($laba_kotor) ?></b></td>
              </tr>
              <tr>
                <td>PRESENTASE</td>
                <td class="text-right"><?= $total_penjualan_cash_basis > 0 ? round(($laba_kotor / $total_penjualan_cash_basis) * 100, 2) : 0 ?>%</td>
              </tr>
            </tbody>
          </table>

          <!-- Pengeluaran -->
          <table class="table table-bordered">
            <thead>
              <tr>
                <th colspan="2" class="bg-primary text-white"><?= $cabang == 0 && !empty($transfer_detail) ? '3. BEBAN OPERASI' : '3. BEBAN OPERASI' ?></th>
              </tr>
            </thead>
            <tbody>
              <?php
              $pengeluaran_counter = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o'];
              $counter_index = 0;
              foreach ($pengeluaran as $p) : ?>
                <tr>
                  <td><?= $pengeluaran_counter[$counter_index] ?? '' ?>. <?= $p['kategori_nama'] ?></td>
                  <td class="text-right"><?= rupiah($p['total']) ?></td>
                </tr>
                <?php $counter_index++; ?>
              <?php endforeach; ?>
              <tr class="table-info">
                <td><b>Total Biaya Pengeluaran</b></td>
                <td class="text-right"><b><?= rupiah($total_pengeluaran) ?></b></td>
              </tr>
            </tbody>
          </table>

          <!-- Laba Bersih -->
          <table class="table table-bordered mt-3">
            <thead>
              <tr class="bg-success">
                <th colspan="2" class="text-white">
                  <?php
                  $section_number = 4;
                  if ($cabang == 0 && !empty($transfer_detail)) {
                    $section_number = 5;
                  }
                  echo $section_number . '. Laba Bersih';
                  ?>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Laba Kotor</td>
                <td class="text-right"><?= rupiah($laba_kotor) ?></td>
              </tr>
              <tr>
                <td>Total Biaya Pengeluaran</td>
                <td class="text-right"><?= rupiah($total_pengeluaran) ?></td>
              </tr>
              <tr>
                <td><b>Laba Bersih</b></td>
                <td class="text-right">
                  <?php
                  // Laba Bersih = Laba Kotor - Total Pengeluaran
                  $laba_bersih_section = $laba_kotor - $total_pengeluaran;
                  // Persentase Keuntungan = (Laba Bersih / HPP) * 100
                  $persentase_section = $hpp > 0 ? round(($laba_bersih_section / $hpp) * 100, 2) : 0;
                  ?>
                  <b class="<?= $laba_bersih_section >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= rupiah($laba_bersih_section) ?>
                  </b>
                </td>
              </tr>
              <?php if ($hpp > 0) : ?>
                <tr>
                  <td>Persentase Keuntungan</td>
                  <td class="text-right">
                    <span class="<?= $persentase_section >= 0 ? 'text-success' : 'text-danger' ?>">
                      <b><?= $persentase_section ?>%</b>
                    </span>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>

          <!-- Laba Rugi (Ringkasan) -->
          <?php
          $section_number_ringkasan = 5;
          if ($cabang == 0 && !empty($transfer_detail)) {
            $section_number_ringkasan = 6;
          }
          if ($cabang == 0 && !empty($sharing_detail)) {
            $section_number_ringkasan = 6;
            if (!empty($transfer_detail)) {
              $section_number_ringkasan = 7;
            }
          }
          ?>
          <table class="table table-bordered mt-3">
            <thead>
              <tr>
                <th colspan="2" class="bg-primary text-white"><?= $section_number_ringkasan ?>. LABA RUGI (Ringkasan)</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Total Pendapatan</td>
                <td class="text-right"><?= rupiah($total_pendapatan_ringkasan) ?></td>
              </tr>
              <tr>
                <td>Total HPP</td>
                <td class="text-right"><?= rupiah($hpp) ?></td>
              </tr>
              <tr>
                <td>Laba Kotor</td>
                <td class="text-right"><?= rupiah($laba_kotor) ?></td>
              </tr>
              <tr>
                <td>Beban Operasi</td>
                <td class="text-right"><?= rupiah($total_pengeluaran) ?></td>
              </tr>
              <tr class="table-success">
                <td><b>Laba Operasi</b></td>
                <td class="text-right">
                  <b class="<?= $laba_operasi >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= rupiah($laba_operasi) ?>
                  </b>
                </td>
              </tr>
              <?php if ($cabang == 0 && !empty($sharing_detail)) : ?>
                <tr>
                  <td>Sharing Profit</td>
                  <td class="text-right"><?= rupiah($sharing_profit) ?></td>
                </tr>
              <?php endif; ?>
              <?php if ($beban_lain > 0) : ?>
                <tr>
                  <td>Beban Lain</td>
                  <td class="text-right"><?= rupiah($beban_lain) ?></td>
                </tr>
              <?php endif; ?>
              <tr class="table-success">
                <td><b>Laba Bersih</b></td>
                <td class="text-right">
                  <b class="<?= $laba_bersih >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= rupiah($laba_bersih) ?>
                  </b>
                </td>
              </tr>
            </tbody>
          </table>

          <!--Transfer Stock-->
          <?php if ($cabang == 0 && !empty($transfer_detail)) : ?>
            <table class="table table-bordered mt-3">
              <thead>
                <tr>
                  <th colspan="2" class="bg-primary text-white">
                    <?php
                    $section_number_transfer = 6;
                    if (!empty($sharing_detail)) {
                      $section_number_transfer = 7;
                    }
                    if (!empty($transfer_detail) && !empty($sharing_detail)) {
                      $section_number_transfer = 8;
                    }
                    echo $section_number_transfer . '. Total Transfer Stok (Diterima oleh Cabang)';
                    ?>
                  </th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($transfer_detail as $t) : ?>
                  <tr>
                    <td><?= $t['nama'] ?></td>
                    <td class="text-right"><?= rupiah($t['nilai']) ?></td>
                  </tr>
                <?php endforeach; ?>
                <tr class="table-info">
                  <td><b>Total Transfer Stok</b></td>
                  <td class="text-right"><b><?= rupiah($total_transfer_stok) ?></b></td>
                </tr>
              </tbody>
            </table>
          <?php endif; ?>

        </div>
      </div>

      <!-- Laporan Neraca -->
      <div class="card card-success mt-4">
        <div class="card-header">
          <h3 class="card-title">Laporan Neraca</h3>
          <br>
          <small>Periode <?= date('d M Y', strtotime($tanggal_awal)) ?> - <?= date('d M Y', strtotime($tanggal_akhir)) ?></small>
        </div>
        <div class="card-body">



          <?php if ($jumlah_kategori_ditemukan == 0) : ?>
            <div class="alert alert-warning">
              <strong>Peringatan:</strong> Tidak ada kategori neraca yang ditemukan di database.<br>
              <small>
                Kategori yang terdeteksi: <?= !empty($kategori_terdeteksi) ? implode(', ', $kategori_terdeteksi) : 'Tidak ada' ?><br>
                Pastikan tabel <code>laba_kategori</code> memiliki data dengan field <code>kategori</code> berisi nilai: <strong>aktiva</strong>, <strong>pasiva</strong>, atau <strong>modal</strong>.
              </small>
            </div>
          <?php endif; ?>

          <div class="row">
            <!-- AKTIVA -->
            <div class="col-md-6">
              <table class="table table-bordered">
                <thead>
                  <tr class="bg-info">
                    <th colspan="3"><strong>AKTIVA (Harta)</strong></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($aktiva_grouped)) : ?>
                    <?php foreach ($aktiva_grouped as $prefix => $group) : ?>
                      <?php if ($group['total'] != 0) : ?>
                        <tr>
                          <td colspan="3" class="bg-light"><strong><?= htmlspecialchars($prefix) ?></strong></td>
                        </tr>

                        <?php foreach ($group['items'] as $akt) : ?>
                          <?php if ($akt['saldo_akhir'] != 0) : ?>
                            <tr>
                              <td style="width: 20%;"><?= htmlspecialchars($akt['kode_akun']) ?></td>
                              <td style="padding-left: 20px;"><?= htmlspecialchars($akt['name']) ?></td>
                              <td style="width: 30%; text-align: right;"><?= rupiah($akt['saldo_akhir']) ?></td>
                            </tr>
                          <?php endif; ?>
                        <?php endforeach; ?>

                        <tr class="bg-light">
                          <td colspan="2" class="text-right"><strong>Total <?= htmlspecialchars($prefix) ?></strong></td>
                          <td class="text-right"><strong><?= rupiah($group['total']) ?></strong></td>
                        </tr>
                      <?php endif; ?>
                    <?php endforeach; ?>

                    <!-- Total Harta Lancar -->
                    <?php if ($total_harta_lancar != 0) : ?>
                      <tr>
                        <td colspan="2"><strong>Total Harta Lancar</strong></td>
                        <td class="text-right"><strong><?= rupiah($total_harta_lancar) ?></strong></td>
                      </tr>
                    <?php endif; ?>

                    <!-- Total Harta Tetap -->
                    <?php if ($total_harta_tetap != 0) : ?>
                      <tr>
                        <td colspan="2"><strong>Total Harta Tetap</strong></td>
                        <td class="text-right"><strong><?= rupiah($total_harta_tetap) ?></strong></td>
                      </tr>
                    <?php endif; ?>

                  <?php elseif (!empty($neraca['aktiva'])) : ?>
                    <!-- Fallback jika tidak ada grouping - tampilkan semua data aktiva -->
                    <?php foreach ($neraca['aktiva'] as $akt) : ?>
                      <?php if ($akt['saldo_akhir'] != 0) : ?>
                        <tr>
                          <td style="width: 20%;"><?= htmlspecialchars($akt['kode_akun']) ?></td>
                          <td style="padding-left: 20px;"><?= htmlspecialchars($akt['name']) ?></td>
                          <td style="width: 30%; text-align: right;"><?= rupiah($akt['saldo_akhir']) ?></td>
                        </tr>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php else : ?>
                    <tr>
                      <td colspan="3" class="text-center text-muted">
                        Tidak ada data aktiva
                        <?php if ($jumlah_kategori_ditemukan > 0) : ?>
                          <br><small>Debug: <?= $jumlah_kategori_ditemukan ?> kategori ditemukan, tapi tidak ada yang masuk aktiva.
                            Cek debug info di atas untuk detail.</small>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endif; ?>

                  <tr class="bg-info">
                    <td colspan="2"><strong>TOTAL HARTA</strong></td>
                    <td class="text-right"><strong><?= rupiah($total_aktiva) ?></strong></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- PASIVA & MODAL -->
            <div class="col-md-6">
              <table class="table table-bordered">
                <thead>
                  <tr class="bg-warning">
                    <th colspan="3"><strong>KEWAJIBAN DAN MODAL</strong></th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Pasiva -->
                  <?php if (!empty($pasiva_grouped) || !empty($neraca['pasiva'])) : ?>
                    <tr>
                      <td colspan="3" class="bg-light"><strong>Kewajiban</strong></td>
                    </tr>

                    <?php if (!empty($pasiva_grouped)) : ?>
                      <?php foreach ($pasiva_grouped as $prefix => $group) : ?>
                        <?php if ($group['total'] != 0) : ?>
                          <tr>
                            <td colspan="3" class="bg-light"><strong><?= htmlspecialchars($prefix) ?></strong></td>
                          </tr>

                          <?php foreach ($group['items'] as $pas) : ?>
                            <?php if ($pas['saldo_akhir'] != 0) : ?>
                              <tr>
                                <td style="width: 20%;"><?= htmlspecialchars($pas['kode_akun']) ?></td>
                                <td style="padding-left: 20px;"><?= htmlspecialchars($pas['name']) ?></td>
                                <td style="width: 30%; text-align: right;"><?= rupiah($pas['saldo_akhir']) ?></td>
                              </tr>
                            <?php endif; ?>
                          <?php endforeach; ?>

                          <tr class="bg-light">
                            <td colspan="2" class="text-right"><strong>Total <?= htmlspecialchars($prefix) ?></strong></td>
                            <td class="text-right"><strong><?= rupiah($group['total']) ?></strong></td>
                          </tr>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    <?php else : ?>
                      <?php foreach ($neraca['pasiva'] as $pas) : ?>
                        <?php if ($pas['saldo_akhir'] != 0) : ?>
                          <tr>
                            <td><?= htmlspecialchars($pas['kode_akun']) ?></td>
                            <td><?= htmlspecialchars($pas['name']) ?></td>
                            <td class="text-right"><?= rupiah($pas['saldo_akhir']) ?></td>
                          </tr>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    <?php endif; ?>

                    <tr>
                      <td colspan="2" class="text-right"><strong>Total Kewajiban</strong></td>
                      <td class="text-right"><strong><?= rupiah($total_pasiva) ?></strong></td>
                    </tr>
                  <?php else : ?>
                    <tr>
                      <td colspan="3" class="bg-light"><strong>Kewajiban</strong></td>
                    </tr>
                    <tr>
                      <td colspan="3" class="text-center text-muted">Tidak ada data kewajiban</td>
                    </tr>
                    <tr>
                      <td colspan="2" class="text-right"><strong>Total Kewajiban</strong></td>
                      <td class="text-right"><strong><?= rupiah($total_pasiva) ?></strong></td>
                    </tr>
                  <?php endif; ?>

                  <!-- Modal -->
                  <tr>
                    <td colspan="3" class="bg-light"><strong>Modal</strong></td>
                  </tr>

                  <!-- Laba Rugi dari laporan laba rugi -->
                  <tr>
                    <td></td>
                    <td>Laba Rugi</td>
                    <td class="text-right"><?= rupiah($laba_bersih) ?></td>
                  </tr>

                  <?php
                  // Inisialisasi total modal dengan laba rugi
                  $total_modal_dengan_laba = $total_modal + $laba_bersih;
                  ?>

                  <?php if (!empty($modal_grouped) || !empty($neraca['modal'])) : ?>
                    <?php if (!empty($modal_grouped)) : ?>
                      <?php foreach ($modal_grouped as $prefix => $group) : ?>
                        <?php if ($group['total'] != 0) : ?>
                          <tr>
                            <td colspan="3" class="bg-light"><strong><?= htmlspecialchars($prefix) ?></strong></td>
                          </tr>

                          <?php foreach ($group['items'] as $mod) : ?>
                            <?php if ($mod['saldo_akhir'] != 0) : ?>
                              <tr>
                                <td style="width: 20%;"><?= htmlspecialchars($mod['kode_akun']) ?></td>
                                <td style="padding-left: 20px;"><?= htmlspecialchars($mod['name']) ?></td>
                                <td style="width: 30%; text-align: right;"><?= rupiah($mod['saldo_akhir']) ?></td>
                              </tr>
                            <?php endif; ?>
                          <?php endforeach; ?>

                          <tr class="bg-light">
                            <td colspan="2" class="text-right"><strong>Total <?= htmlspecialchars($prefix) ?></strong></td>
                            <td class="text-right"><strong><?= rupiah($group['total']) ?></strong></td>
                          </tr>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    <?php else : ?>
                      <?php foreach ($neraca['modal'] as $mod) : ?>
                        <?php if ($mod['saldo_akhir'] != 0) : ?>
                          <tr>
                            <td><?= htmlspecialchars($mod['kode_akun']) ?></td>
                            <td><?= htmlspecialchars($mod['name']) ?></td>
                            <td class="text-right"><?= rupiah($mod['saldo_akhir']) ?></td>
                          </tr>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    <?php endif; ?>

                    <tr>
                      <td colspan="2" class="text-right"><strong>Total Modal</strong></td>
                      <td class="text-right"><strong><?= rupiah($total_modal_dengan_laba) ?></strong></td>
                    </tr>
                  <?php else : ?>
                    <tr>
                      <td colspan="3" class="text-center text-muted">Tidak ada data modal</td>
                    </tr>
                    <tr>
                      <td colspan="2" class="text-right"><strong>Total Modal</strong></td>
                      <td class="text-right"><strong><?= rupiah($total_modal_dengan_laba) ?></strong></td>
                    </tr>
                  <?php endif; ?>

                  <?php
                  // Update total pasiva & modal dengan laba rugi
                  $total_pasiva_modal_dengan_laba = $total_pasiva + $total_modal_dengan_laba;
                  ?>
                  <tr class="bg-warning">
                    <td colspan="2"><strong>TOTAL KEWAJIBAN DAN MODAL</strong></td>
                    <td class="text-right"><strong><?= rupiah($total_pasiva_modal_dengan_laba) ?></strong></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Catatan Keseimbangan -->
          <div class="alert alert-info mt-3">
            <strong>Keseimbangan Neraca:</strong><br>
            Total Harta: <strong><?= rupiah($total_aktiva) ?></strong><br>
            Total Kewajiban dan Modal: <strong><?= rupiah($total_pasiva_modal_dengan_laba ?? $total_pasiva_modal) ?></strong><br>
            <?php
            $selisih = abs($total_aktiva - ($total_pasiva_modal_dengan_laba ?? $total_pasiva_modal));
            if ($selisih < 0.01) : ?>
              <span class="text-success"><strong>✓ Neraca Seimbang</strong></span>
            <?php else : ?>
              <span class="text-danger">
                <strong>⚠ Selisih: <?= rupiah($selisih) ?></strong>
              </span>
            <?php endif; ?>
          </div>

        </div>
      </div>
    </div>
  </section>
</div>

<?php include '_footerlaporan.php' ?>
<?php include '_footer.php'; ?>