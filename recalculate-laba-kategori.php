<?php
/**
 * Script untuk menghitung ulang saldo laba_kategori dari transaksi operasional
 * 
 * Script ini akan:
 * 1. Reset semua saldo di laba_kategori menjadi 0
 * 2. Membaca semua transaksi operasional (invoice, invoice_pembelian, piutang, hutang)
 * 3. Menghitung ulang saldo berdasarkan transaksi tersebut
 * 4. Update saldo di laba_kategori
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin != "admin" && $levelLogin != "super admin") {
    echo "
        <script>
            document.location.href = 'bo';
        </script>
    ";
    exit;
}

include 'aksi/koneksi.php';

$message = '';
$success = false;
$details = [];

// Cek apakah kolom cabang ada
$check_cabang_column = "SHOW COLUMNS FROM laba_kategori LIKE 'cabang'";
$cabang_column_result = mysqli_query($conn, $check_cabang_column);
$cabang_column_exists = ($cabang_column_result && mysqli_num_rows($cabang_column_result) > 0);

// Proses perhitungan ulang
if (isset($_POST['recalculate'])) {
    mysqli_autocommit($conn, false);
    
    try {
        // 1. Reset semua saldo menjadi 0 (semua cabang)
        mysqli_query($conn, "UPDATE laba_kategori SET saldo = 0");
        $details[] = "✓ Reset semua saldo menjadi 0 (Semua Cabang)";
        
        // 2. Hitung saldo dari tabel laba (transaksi operasional)
        // Tabel laba menggunakan sistem double-entry dengan akun_debit dan akun_kredit
        
        // Cek apakah kolom baru (akun_debit, akun_kredit) ada
        $check_columns = "SHOW COLUMNS FROM laba LIKE 'akun_debit'";
        $column_result = mysqli_query($conn, $check_columns);
        $has_new_columns = ($column_result && mysqli_num_rows($column_result) > 0);
        
        if ($has_new_columns) {
            // Gunakan sistem double-entry (akun_debit dan akun_kredit)
            // Cek apakah kolom jenis_transaksi ada
            $check_jenis_transaksi = "SHOW COLUMNS FROM laba LIKE 'jenis_transaksi'";
            $jenis_transaksi_result = mysqli_query($conn, $check_jenis_transaksi);
            $has_jenis_transaksi = ($jenis_transaksi_result && mysqli_num_rows($jenis_transaksi_result) > 0);
            
            $query_laba = "SELECT 
                akun_debit,
                akun_kredit,
                total,
                cabang";
            
            if ($has_jenis_transaksi) {
                $query_laba .= ", jenis_transaksi";
            }
            
            $query_laba .= " FROM laba 
            WHERE akun_debit IS NOT NULL 
            AND akun_kredit IS NOT NULL 
            AND total > 0";
            
            $result_laba = mysqli_query($conn, $query_laba);
            $transaksi_count = 0;
            $total_transaksi = 0;
            $transfer_count = 0;
            $transfer_total = 0;
            
            while ($row = mysqli_fetch_assoc($result_laba)) {
                $akun_debit_id = intval($row['akun_debit']);
                $akun_kredit_id = intval($row['akun_kredit']);
                $total = floatval($row['total']);
                $cabang = isset($row['cabang']) ? intval($row['cabang']) : 0;
                $jenis_transaksi = isset($row['jenis_transaksi']) ? $row['jenis_transaksi'] : null;
                
                // Cek apakah ini transaksi transfer uang dari kas tunai ke kas bank BRI
                // Cari akun berdasarkan ID untuk mendapatkan kode_akun
                $akun_debit_info = getAkunInfo($conn, $akun_debit_id);
                $akun_kredit_info = getAkunInfo($conn, $akun_kredit_id);
                
                $is_transfer = false;
                $transfer_to_bank = false;
                
                // Cek jika jenis_transaksi = 'transfer_uang' atau jika akun_debit/kredit adalah kas tunai dan kas bank
                if ($akun_debit_info && $akun_kredit_info) {
                    $kode_debit = $akun_debit_info['kode_akun'] ?? '';
                    $kode_kredit = $akun_kredit_info['kode_akun'] ?? '';
                    
                    if ($jenis_transaksi == 'transfer_uang' || 
                        (($kode_debit == '1-1100' && ($kode_kredit == '1-1152' || $kode_kredit == '1-1153')) ||
                         ($kode_kredit == '1-1100' && ($kode_debit == '1-1152' || $kode_debit == '1-1153')))) {
                        $is_transfer = true;
                        $transfer_to_bank = true;
                    }
                }
                
                if ($is_transfer && $transfer_to_bank) {
                    // Transaksi transfer uang dari kas tunai ke kas bank BRI
                    // Pastikan semua masuk ke 1-1153 (cabang 0), tidak peduli apakah awalnya 1-1152 atau 1-1153
                    
                    $kode_debit = $akun_debit_info['kode_akun'] ?? '';
                    $kode_kredit = $akun_kredit_info['kode_akun'] ?? '';
                    
                    if ($kode_debit == '1-1100') {
                        // Debit dari Kas Tunai (1-1100) - kurangi dari cabang masing-masing
                        updateSaldoAkunByKode($conn, '1-1100', 'Kas Tunai', 'aktiva', 'debit', -$total, $cabang, $cabang_column_exists);
                        // Kredit ke Kas Bank BRI (1-1153) - tambah ke cabang 0 (tidak peduli apakah awalnya 1-1152 atau 1-1153)
                        updateSaldoAkunByKode($conn, '1-1153', 'Kas Bank BRI', 'aktiva', 'debit', $total, 0, $cabang_column_exists);
                    } else if ($kode_kredit == '1-1100') {
                        // Kredit dari Kas Tunai (1-1100) - kurangi dari cabang masing-masing
                        updateSaldoAkunByKode($conn, '1-1100', 'Kas Tunai', 'aktiva', 'debit', -$total, $cabang, $cabang_column_exists);
                        // Debit ke Kas Bank BRI (1-1153) - tambah ke cabang 0 (tidak peduli apakah awalnya 1-1152 atau 1-1153)
                        updateSaldoAkunByKode($conn, '1-1153', 'Kas Bank BRI', 'aktiva', 'debit', $total, 0, $cabang_column_exists);
                    }
                    
                    $transfer_count++;
                    $transfer_total += $total;
                } else {
                    // Transaksi biasa, update saldo menggunakan fungsi yang sama dengan api/laba.php
                    updateSaldoAkunFromLaba($conn, $akun_debit_id, $akun_kredit_id, $total, $cabang, 'debit');
                    updateSaldoAkunFromLaba($conn, $akun_kredit_id, $akun_debit_id, $total, $cabang, 'kredit');
                }
                
                $transaksi_count++;
                $total_transaksi += $total;
            }
            
            $details[] = "✓ Transaksi dari tabel laba (double-entry): $transaksi_count transaksi, Total: Rp " . number_format($total_transaksi, 0, ',', '.');
            if ($transfer_count > 0) {
                $details[] = "✓ Transfer Uang ke Kas Bank BRI: $transfer_count transaksi, Total: Rp " . number_format($transfer_total, 0, ',', '.');
            }
        } else {
            // Backward compatibility: gunakan sistem single-entry (kategori saja)
            $query_laba = "SELECT 
                kategori,
                tipe,
                jumlah,
                cabang
            FROM laba 
            WHERE kategori IS NOT NULL 
            AND jumlah > 0";
            
            $result_laba = mysqli_query($conn, $query_laba);
            $transaksi_count = 0;
            $total_transaksi = 0;
            
            while ($row = mysqli_fetch_assoc($result_laba)) {
                $kategori_id = intval($row['kategori']);
                $jumlah = floatval($row['jumlah']);
                $tipe = intval($row['tipe']);
                $cabang = isset($row['cabang']) ? intval($row['cabang']) : 0;
                
                // Update saldo menggunakan sistem single-entry
                updateSaldoAkunSingleFromLaba($conn, $kategori_id, $jumlah, $tipe, $cabang);
                
                $transaksi_count++;
                $total_transaksi += $jumlah;
            }
            
            $details[] = "✓ Transaksi dari tabel laba (single-entry): $transaksi_count transaksi, Total: Rp " . number_format($total_transaksi, 0, ',', '.');
        }
        
        // 3. Hitung saldo dari transaksi Penjualan (invoice)
        // Cash → akun 1-1100 (masing-masing toko/cabang)
        // Transfer → akun 1-1153 (semua masuk ke cabang 0/PCNU)
        // Piutang → akun 1-1300
        
        $query_penjualan = "SELECT 
            invoice_cabang,
            invoice_piutang,
            invoice_tipe_transaksi,
            invoice_sub_total,
            invoice_piutang_dp
        FROM invoice 
        WHERE invoice_sub_total > 0";
        
        $result_penjualan = mysqli_query($conn, $query_penjualan);
        $penjualan_cash = 0;
        $penjualan_transfer = 0;
        $penjualan_piutang = 0;
        $penjualan_piutang_dp = 0;
        
        while ($row = mysqli_fetch_assoc($result_penjualan)) {
            $cabang = isset($row['invoice_cabang']) ? intval($row['invoice_cabang']) : 0;
            $piutang = intval($row['invoice_piutang']);
            $tipe_transaksi = intval($row['invoice_tipe_transaksi']);
            $sub_total = floatval($row['invoice_sub_total']);
            $piutang_dp = floatval($row['invoice_piutang_dp'] ?? 0);
            
            if ($piutang == 1) {
                // Transaksi Piutang
                $sisa_piutang = $sub_total - $piutang_dp;
                $penjualan_piutang += $sisa_piutang;
                $penjualan_piutang_dp += $piutang_dp;
                
                // Update Piutang Dagang (1-1300) - untuk cabang masing-masing
                updateSaldoAkunByKode($conn, '1-1300', 'Piutang Dagang', 'aktiva', 'debit', $sisa_piutang, $cabang, $cabang_column_exists);
                
                // Jika ada DP, tambahkan ke Kas Tunai (1-1100) untuk cabang masing-masing
                if ($piutang_dp > 0) {
                    updateSaldoAkunByKode($conn, '1-1100', 'Kas Tunai', 'aktiva', 'debit', $piutang_dp, $cabang, $cabang_column_exists);
                }
            } else {
                // Transaksi Cash
                if ($tipe_transaksi == 0) {
                    // Cash → Kas Tunai (1-1100) untuk cabang masing-masing
                    $penjualan_cash += $sub_total;
                    updateSaldoAkunByKode($conn, '1-1100', 'Kas Tunai', 'aktiva', 'debit', $sub_total, $cabang, $cabang_column_exists);
                } else if ($tipe_transaksi == 1) {
                    // Transfer → Kas Bank BRI (1-1153) untuk cabang 0/PCNU
                    $penjualan_transfer += $sub_total;
                    updateSaldoAkunByKode($conn, '1-1153', 'Kas Bank BRI', 'aktiva', 'debit', $sub_total, 0, $cabang_column_exists);
                }
            }
        }
        
        if ($penjualan_cash > 0) {
            $details[] = "✓ Penjualan Cash: Rp " . number_format($penjualan_cash, 0, ',', '.');
        }
        if ($penjualan_transfer > 0) {
            $details[] = "✓ Penjualan Transfer: Rp " . number_format($penjualan_transfer, 0, ',', '.');
        }
        if ($penjualan_piutang > 0) {
            $details[] = "✓ Penjualan Piutang: Rp " . number_format($penjualan_piutang, 0, ',', '.');
        }
        if ($penjualan_piutang_dp > 0) {
            $details[] = "✓ DP Piutang: Rp " . number_format($penjualan_piutang_dp, 0, ',', '.');
        }
        
        // 4. Hitung saldo dari transaksi Pembelian (invoice_pembelian)
        // Hutang → akun 2-1100
        
        $query_pembelian = "SELECT 
            invoice_pembelian_cabang,
            invoice_hutang,
            invoice_total,
            invoice_hutang_dp
        FROM invoice_pembelian 
        WHERE invoice_total > 0";
        
        $result_pembelian = mysqli_query($conn, $query_pembelian);
        $pembelian_cash = 0;
        $pembelian_hutang = 0;
        $pembelian_hutang_dp = 0;
        
        while ($row = mysqli_fetch_assoc($result_pembelian)) {
            $cabang = isset($row['invoice_pembelian_cabang']) ? intval($row['invoice_pembelian_cabang']) : 0;
            $hutang = intval($row['invoice_hutang']);
            $total = floatval($row['invoice_total']);
            $hutang_dp = floatval($row['invoice_hutang_dp'] ?? 0);
            
            if ($hutang == 1) {
                // Transaksi Hutang
                $sisa_hutang = $total - $hutang_dp;
                $pembelian_hutang += $sisa_hutang;
                $pembelian_hutang_dp += $hutang_dp;
                
                // Update Hutang Dagang (2-1100) - untuk cabang masing-masing
                updateSaldoAkunByKode($conn, '2-1100', 'Hutang Dagang', 'pasiva', 'kredit', $sisa_hutang, $cabang, $cabang_column_exists);
                
                // Kurangi DP dari Kas Tunai (1-1100) untuk cabang masing-masing
                if ($hutang_dp > 0) {
                    updateSaldoAkunByKode($conn, '1-1100', 'Kas Tunai', 'aktiva', 'debit', -$hutang_dp, $cabang, $cabang_column_exists);
                }
            } else {
                // Transaksi Cash - kurangi dari Kas Tunai (1-1100) untuk cabang masing-masing
                $pembelian_cash += $total;
                updateSaldoAkunByKode($conn, '1-1100', 'Kas Tunai', 'aktiva', 'debit', -$total, $cabang, $cabang_column_exists);
            }
        }
        
        if ($pembelian_cash > 0) {
            $details[] = "✓ Pembelian Cash: Rp " . number_format($pembelian_cash, 0, ',', '.');
        }
        if ($pembelian_hutang > 0) {
            $details[] = "✓ Pembelian Hutang: Rp " . number_format($pembelian_hutang, 0, ',', '.');
        }
        if ($pembelian_hutang_dp > 0) {
            $details[] = "✓ DP Hutang: Rp " . number_format($pembelian_hutang_dp, 0, ',', '.');
        }
        
        // Commit transaction
        mysqli_commit($conn);
        $success = true;
        $message = "<div class='alert alert-success'><strong>Berhasil!</strong> Saldo berhasil dihitung ulang dari transaksi operasional.</div>";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "<div class='alert alert-danger'><strong>Error!</strong> " . $e->getMessage() . "</div>";
    } finally {
        mysqli_autocommit($conn, true);
    }
}

// Fungsi helper untuk mendapatkan info akun berdasarkan ID
function getAkunInfo($conn, $akun_id) {
    $query = "SELECT id, kode_akun, name, kategori, tipe_akun, cabang FROM laba_kategori WHERE id = " . (int)$akun_id;
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

// Fungsi helper untuk update saldo akun dari tabel laba (double-entry)
function updateSaldoAkunFromLaba($conn, $akun_id, $akun_pasangan, $jumlah, $cabang, $posisi) {
    // Get akun info
    $akun_query = "SELECT id, kategori, tipe_akun, saldo, cabang FROM laba_kategori WHERE id = " . (int)$akun_id;
    $akun_result = mysqli_query($conn, $akun_query);
    
    if (!$akun_result || mysqli_num_rows($akun_result) == 0) {
        return; // Skip jika akun tidak ditemukan
    }
    
    $akun = mysqli_fetch_assoc($akun_result);
    $kategori = strtolower(trim($akun['kategori'] ?? ''));
    $tipe_akun = strtolower(trim($akun['tipe_akun'] ?? ''));
    $saldo_sekarang = floatval($akun['saldo'] ?? 0);
    $akun_cabang = $akun['cabang'] ?? null;
    
    // Pastikan akun sesuai dengan cabang transaksi
    if ($akun_cabang !== null && $akun_cabang != $cabang && $akun_cabang != 0) {
        return; // Skip jika cabang tidak sesuai
    }
    
    // Hitung perubahan saldo berdasarkan kategori dan posisi
    $perubahan_saldo = 0;
    
    if ($kategori == 'aktiva') {
        // Aktiva: normal saldo DEBIT
        if ($posisi == 'debit') {
            $perubahan_saldo = $jumlah;
        } else {
            $perubahan_saldo = -$jumlah;
        }
        if ($tipe_akun == 'kredit') {
            $perubahan_saldo = -$perubahan_saldo;
        }
    } else if ($kategori == 'pasiva') {
        // Pasiva: normal saldo KREDIT
        if ($posisi == 'debit') {
            $perubahan_saldo = -$jumlah;
        } else {
            $perubahan_saldo = $jumlah;
        }
        if ($tipe_akun == 'debit') {
            $perubahan_saldo = -$perubahan_saldo;
        }
    } else if ($kategori == 'modal') {
        // Modal: normal saldo KREDIT
        if ($posisi == 'debit') {
            $perubahan_saldo = -$jumlah;
        } else {
            $perubahan_saldo = $jumlah;
        }
        if ($tipe_akun == 'debit') {
            $perubahan_saldo = -$perubahan_saldo;
        }
    } else {
        // Pendapatan dan beban tidak update saldo neraca
        return;
    }
    
    $saldo_baru = $saldo_sekarang + $perubahan_saldo;
    
    // Update saldo
    $update_query = "UPDATE laba_kategori SET saldo = $saldo_baru WHERE id = " . (int)$akun_id;
    mysqli_query($conn, $update_query);
}

// Fungsi helper untuk update saldo akun dari tabel laba (single-entry)
function updateSaldoAkunSingleFromLaba($conn, $kategori_id, $jumlah, $tipe, $cabang) {
    // Get kategori info
    $kat_query = "SELECT id, kategori, tipe_akun, saldo, cabang FROM laba_kategori WHERE id = " . (int)$kategori_id;
    $kat_result = mysqli_query($conn, $kat_query);
    
    if (!$kat_result || mysqli_num_rows($kat_result) == 0) {
        return;
    }
    
    $kat = mysqli_fetch_assoc($kat_result);
    $kategori = strtolower(trim($kat['kategori'] ?? ''));
    $tipe_akun = strtolower(trim($kat['tipe_akun'] ?? ''));
    $saldo_sekarang = floatval($kat['saldo'] ?? 0);
    $kat_cabang = $kat['cabang'] ?? null;
    
    // Pastikan kategori sesuai dengan cabang
    if ($kat_cabang !== null && $kat_cabang != $cabang && $kat_cabang != 0) {
        return;
    }
    
    // Hitung perubahan saldo
    $perubahan_saldo = 0;
    
    if ($kategori == 'aktiva') {
        // Aktiva: Masuk (tipe=0) = menambah, Keluar (tipe=1) = mengurangi
        if ($tipe == 0) {
            $perubahan_saldo = $jumlah;
        } else {
            $perubahan_saldo = -$jumlah;
        }
        if ($tipe_akun == 'kredit') {
            $perubahan_saldo = -$perubahan_saldo;
        }
    } else if ($kategori == 'pasiva') {
        // Pasiva: Masuk (tipe=0) = mengurangi, Keluar (tipe=1) = menambah
        if ($tipe == 0) {
            $perubahan_saldo = -$jumlah;
        } else {
            $perubahan_saldo = $jumlah;
        }
        if ($tipe_akun == 'debit') {
            $perubahan_saldo = -$perubahan_saldo;
        }
    } else if ($kategori == 'modal') {
        // Modal: Masuk (tipe=0) = menambah, Keluar (tipe=1) = mengurangi
        if ($tipe == 0) {
            $perubahan_saldo = $jumlah;
        } else {
            $perubahan_saldo = -$jumlah;
        }
        if ($tipe_akun == 'debit') {
            $perubahan_saldo = -$perubahan_saldo;
        }
    } else {
        // Pendapatan dan beban tidak update saldo neraca
        return;
    }
    
    $saldo_baru = $saldo_sekarang + $perubahan_saldo;
    
    // Update saldo
    $update_query = "UPDATE laba_kategori SET saldo = $saldo_baru WHERE id = " . (int)$kategori_id;
    mysqli_query($conn, $update_query);
}

// Fungsi helper untuk update saldo akun berdasarkan kode_akun (untuk transaksi invoice/pembelian)
function updateSaldoAkunByKode($conn, $kode_akun, $name, $kategori, $tipe_akun, $jumlah, $cabang, $cabang_column_exists) {
    // Cari akun dengan kode_akun yang sesuai
    // Prioritas: cari akun dengan cabang yang sama, jika tidak ada cari cabang 0 (default), jika tidak ada cari NULL
    if ($cabang_column_exists) {
        // Cari akun dengan cabang yang sama terlebih dahulu
        $query = "SELECT id, saldo FROM laba_kategori WHERE kode_akun = '$kode_akun' AND cabang = $cabang LIMIT 1";
        $result = mysqli_query($conn, $query);
        
        // Jika tidak ditemukan, cari cabang 0 atau NULL
        if (!$result || mysqli_num_rows($result) == 0) {
            $query = "SELECT id, saldo FROM laba_kategori WHERE kode_akun = '$kode_akun' AND (cabang = 0 OR cabang IS NULL) ORDER BY cabang DESC LIMIT 1";
            $result = mysqli_query($conn, $query);
        }
    } else {
        $query = "SELECT id, saldo FROM laba_kategori WHERE kode_akun = '$kode_akun' LIMIT 1";
        $result = mysqli_query($conn, $query);
    }
    
    if ($result && mysqli_num_rows($result) > 0) {
        // Akun sudah ada, update saldo
        $row = mysqli_fetch_assoc($result);
        $saldo_sekarang = floatval($row['saldo']);
        $saldo_baru = $saldo_sekarang + $jumlah;
        
        $update_query = "UPDATE laba_kategori SET saldo = $saldo_baru WHERE id = " . intval($row['id']);
        mysqli_query($conn, $update_query);
    } else {
        // Akun belum ada, buat baru dengan cabang yang sesuai
        if ($cabang_column_exists) {
            $insert_query = "INSERT INTO laba_kategori (name, kode_akun, kategori, tipe_akun, saldo, cabang) VALUES ('$name', '$kode_akun', '$kategori', '$tipe_akun', $jumlah, $cabang)";
        } else {
            $insert_query = "INSERT INTO laba_kategori (name, kode_akun, kategori, tipe_akun, saldo) VALUES ('$name', '$kode_akun', '$kategori', '$tipe_akun', $jumlah)";
        }
        
        mysqli_query($conn, $insert_query);
    }
}

// Fungsi helper untuk update saldo akun (untuk transaksi invoice/pembelian - backup/legacy)
function updateSaldoAkun($conn, $kode_akun, $name, $kategori, $tipe_akun, $jumlah, $cabang, $cabang_column_exists) {
    // Alias untuk backward compatibility
    return updateSaldoAkunByKode($conn, $kode_akun, $name, $kategori, $tipe_akun, $jumlah, $cabang, $cabang_column_exists);
}

// Cek jumlah akun null
$query_null = "SELECT COUNT(*) as jumlah FROM laba_kategori WHERE (kode_akun IS NULL OR kode_akun = '' OR kode_akun = '-') AND (name IS NULL OR name = '' OR name = '-')";
$result_null = mysqli_query($conn, $query_null);
$jumlah_null = 0;
if ($result_null) {
    $row_null = mysqli_fetch_assoc($result_null);
    $jumlah_null = intval($row_null['jumlah']);
}
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Hitung Ulang Saldo Laba Kategori</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="bo">Home</a></li>
                        <li class="breadcrumb-item"><a href="laba-kategori">Laba Kategori</a></li>
                        <li class="breadcrumb-item active">Hitung Ulang Saldo</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($message): ?>
                <?php echo $message; ?>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Hitung Ulang Saldo dari Transaksi Operasional</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5><i class="icon fa fa-info-circle"></i> Informasi</h5>
                        <p>Script ini akan:</p>
                        <ol>
                            <li>Reset semua saldo di <code>laba_kategori</code> menjadi 0 (Semua Cabang)</li>
                            <li>Membaca semua transaksi operasional dari:
                                <ul>
                                    <li><strong>Tabel <code>laba</code></strong> (Data Operasional):
                                        <ul>
                                            <li>Jika menggunakan sistem <strong>double-entry</strong> (akun_debit & akun_kredit): Update saldo berdasarkan posisi debit/kredit</li>
                                            <li>Jika menggunakan sistem <strong>single-entry</strong> (kategori saja): Update saldo berdasarkan tipe (masuk/keluar)</li>
                                            <li><strong>Transfer Uang</strong> (jenis_transaksi = 'transfer_uang' atau transfer dari 1-1100 ke 1-1152/1-1153):
                                                <ul>
                                                    <li>Kurangi dari <strong>1-1100 (Kas Tunai)</strong> untuk cabang masing-masing</li>
                                                    <li>Tambah ke <strong>1-1153 (Kas Bank BRI)</strong> untuk cabang 0/PCNU</li>
                                                    <li><strong>Catatan:</strong> Jika transaksi menggunakan 1-1152 (cabang selain 0), otomatis akan masuk ke 1-1153 (cabang 0)</li>
                                                </ul>
                                            </li>
                                        </ul>
                                    </li>
                                    <li><strong>Tabel <code>invoice</code></strong> (Penjualan):
                                        <ul>
                                            <li>Cash → Akun <strong>1-1100 (Kas Tunai)</strong> untuk masing-masing cabang</li>
                                            <li>Transfer → Akun <strong>1-1153 (Kas Bank BRI)</strong> untuk cabang 0/PCNU</li>
                                            <li>Piutang → Akun <strong>1-1300 (Piutang Dagang)</strong> untuk masing-masing cabang</li>
                                        </ul>
                                    </li>
                                    <li><strong>Tabel <code>invoice_pembelian</code></strong> (Pembelian):
                                        <ul>
                                            <li>Cash → Kurangi dari Akun <strong>1-1100 (Kas Tunai)</strong> untuk masing-masing cabang</li>
                                            <li>Hutang → Akun <strong>2-1100 (Hutang Dagang)</strong> untuk masing-masing cabang</li>
                                        </ul>
                                    </li>
                                </ul>
                            </li>
                            <li>Menghitung ulang saldo berdasarkan semua transaksi tersebut</li>
                            <li>Update saldo di <code>laba_kategori</code></li>
                        </ol>
                        <p><strong>Catatan:</strong> Script ini membaca dari tabel <code>laba</code>, <code>invoice</code>, dan <code>invoice_pembelian</code> untuk memastikan semua transaksi tercatat dengan benar.</p>
                        <p><strong>Peringatan:</strong> 
                            <ul>
                                <li>Proses ini akan menghitung ulang saldo untuk <strong>SEMUA CABANG</strong></li>
                                <li>Pastikan sudah <strong>backup database</strong> sebelum menjalankan proses ini!</li>
                                <li>Proses ini membutuhkan waktu, pastikan tidak ada transaksi aktif saat proses berjalan</li>
                            </ul>
                        </p>
                    </div>

                    <?php if ($jumlah_null > 0): ?>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle"></i> 
                            Ditemukan <strong><?php echo $jumlah_null; ?></strong> akun dengan kode akun dan nama kategori null. 
                            Disarankan untuk menghapus akun null terlebih dahulu sebelum menghitung ulang saldo.
                            <br><br>
                            <a href="hapus-akun-null.php" class="btn btn-warning btn-sm">
                                <i class="fa fa-trash"></i> Hapus Akun Null
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($success && count($details) > 0): ?>
                        <div class="card card-success">
                            <div class="card-header">
                                <h3 class="card-title">Detail Perhitungan</h3>
                            </div>
                            <div class="card-body">
                                <ul>
                                    <?php foreach ($details as $detail): ?>
                                        <li><?php echo $detail; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" onsubmit="return confirm('Yakin ingin menghitung ulang saldo untuk SEMUA CABANG? Pastikan sudah backup database!')">
                        <button type="submit" name="recalculate" class="btn btn-primary btn-lg">
                            <i class="fa fa-calculator"></i> Hitung Ulang Saldo (Semua Cabang)
                        </button>
                        <a href="laba-kategori.php" class="btn btn-default">
                            <i class="fa fa-arrow-left"></i> Kembali
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '_footer.php'; ?>
