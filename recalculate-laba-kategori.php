<?php
/**
 * Script untuk menghitung ulang saldo laba_kategori dari transaksi operasional
 * 
 * Script ini akan:
 * 1. Reset semua saldo di laba_kategori menjadi 0
 * 2. Membaca semua transaksi operasional (laba, invoice, invoice_pembelian, piutang, hutang)
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
require_once 'aksi/akun-link-lib.php';

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
        // 0. Perbaiki akun debit/kredit setor & transfer yang rusak setelah migrasi COA
        $perbaikiSetor = akun_link_perbaiki_laba_setor_bank_bri($conn);
        if (!empty($perbaikiSetor['fixed'])) {
            $details[] = '✓ Perbaiki mapping setor/transfer laba: ' . (int) $perbaikiSetor['fixed'] . ' baris';
        }

        // 1. Reset semua saldo menjadi 0 (semua cabang)
        mysqli_query($conn, "UPDATE laba_kategori SET saldo = 0");
        $details[] = "✓ Reset semua saldo menjadi 0 (Semua Cabang)";

        // 2. Penjualan (invoice) — sumber kas/bank dari omzet
        $piutang_invoice_parents = recalculate_preload_piutang_invoice_keys($conn);
        $result_penjualan = recalculate_query_latest_penjualan($conn);
        $penjualan_cash = 0;
        $penjualan_transfer = 0;
        $penjualan_piutang = 0;
        $penjualan_piutang_dp = 0;

        while ($row = mysqli_fetch_assoc($result_penjualan)) {
            $cabang = akun_link_normalize_cabang_transaksi(isset($row['invoice_cabang']) ? (int) $row['invoice_cabang'] : 0);
            $piutang = intval($row['invoice_piutang']);
            $tipe_transaksi = intval($row['invoice_tipe_transaksi']);
            $sub_total = floatval($row['invoice_sub_total']);
            $piutang_dp = floatval($row['invoice_piutang_dp'] ?? 0);
            $piutang_lunas = intval($row['invoice_piutang_lunas'] ?? 0);
            $was_piutang = recalculate_was_penjualan_piutang(
                $piutang,
                $piutang_lunas,
                (string) ($row['penjualan_invoice'] ?? ''),
                $cabang,
                $piutang_invoice_parents
            );

            akun_posting_setelah_penjualan($conn, $cabang, $was_piutang ? 1 : 0, $tipe_transaksi, $sub_total, $piutang_dp);

            if ($was_piutang) {
                $penjualan_piutang += max(0, $sub_total - $piutang_dp);
                $penjualan_piutang_dp += $piutang_dp;
            } elseif ($tipe_transaksi == 0) {
                $penjualan_cash += $sub_total;
            } else {
                $penjualan_transfer += $sub_total;
            }
        }

        if ($penjualan_cash > 0) {
            $details[] = "✓ Penjualan Cash → Kas: Rp " . number_format($penjualan_cash, 0, ',', '.');
        }
        if ($penjualan_transfer > 0) {
            $details[] = "✓ Penjualan Transfer/QRIS → Bank: Rp " . number_format($penjualan_transfer, 0, ',', '.');
        }
        if ($penjualan_piutang > 0) {
            $details[] = "✓ Penjualan Piutang: Rp " . number_format($penjualan_piutang, 0, ',', '.');
        }
        if ($penjualan_piutang_dp > 0) {
            $details[] = "✓ DP Piutang → Kas: Rp " . number_format($penjualan_piutang_dp, 0, ',', '.');
        }

        // 3. Pembelian (invoice_pembelian) — lunas mengurangi kas/bank
        $hutang_invoice_parents = recalculate_preload_hutang_invoice_keys($conn);
        $result_pembelian = recalculate_query_latest_pembelian($conn);
        $pembelian_cash = 0;
        $pembelian_hutang = 0;
        $pembelian_hutang_dp = 0;

        while ($row = mysqli_fetch_assoc($result_pembelian)) {
            $cabang = akun_link_normalize_cabang_transaksi(isset($row['invoice_pembelian_cabang']) ? (int) $row['invoice_pembelian_cabang'] : 0);
            $hutang = intval($row['invoice_hutang']);
            $total = floatval($row['invoice_total']);
            $hutang_dp = floatval($row['invoice_hutang_dp'] ?? 0);
            $hutang_lunas = intval($row['invoice_hutang_lunas'] ?? 0);
            $parent = (string) ($row['pembelian_invoice_parent'] ?? '');
            $was_hutang = recalculate_was_pembelian_hutang(
                $hutang,
                $hutang_lunas,
                $parent,
                $cabang,
                $hutang_invoice_parents
            );

            akun_posting_pembelian_saat_recalculate($conn, $cabang, $was_hutang, $total, $hutang_dp);

            if ($was_hutang) {
                $pembelian_hutang += max(0, $total - $hutang_dp);
                $pembelian_hutang_dp += $hutang_dp;
            } else {
                $pembelian_cash += $total;
            }
        }

        if ($pembelian_cash > 0) {
            $details[] = "✓ Pembelian tunai → kas cabang (1-1101 s/d 1-1105): Rp " . number_format($pembelian_cash, 0, ',', '.');
        }
        if ($pembelian_hutang > 0) {
            $details[] = "✓ Pembelian Hutang → 2-1101: Rp " . number_format($pembelian_hutang, 0, ',', '.');
        }
        if ($pembelian_hutang_dp > 0) {
            $details[] = "✓ DP Hutang (via cicilan): Rp " . number_format($pembelian_hutang_dp, 0, ',', '.');
        }

        // 4. Hitung saldo dari tabel laba (Data Operasional)
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
                kategori,
                tipe,
                total,
                jumlah,
                cabang";
            
            if ($has_jenis_transaksi) {
                $query_laba .= ", jenis_transaksi, keterangan";
            }
            
            $query_laba .= " FROM laba 
            WHERE (
                (akun_debit IS NOT NULL AND akun_kredit IS NOT NULL
                 AND COALESCE(NULLIF(total, 0), CAST(NULLIF(TRIM(jumlah), '') AS DECIMAL(15,2))) > 0)
                OR (kategori IS NOT NULL AND kategori != ''
                    AND CAST(NULLIF(TRIM(jumlah), '') AS DECIMAL(15,2)) > 0
                    AND (akun_debit IS NULL OR akun_kredit IS NULL))
            )";
            
            $result_laba = mysqli_query($conn, $query_laba);
            $transaksi_count = 0;
            $total_transaksi = 0;
            $legacy_laba_count = 0;
            $skipped_hutang_laba = 0;
            $skipped_piutang_laba = 0;
            
            while ($row = mysqli_fetch_assoc($result_laba)) {
                $cabang = akun_link_normalize_cabang_transaksi(isset($row['cabang']) ? (int) $row['cabang'] : 0);
                $total = recalculate_laba_nominal($row);
                if ($total <= 0) {
                    continue;
                }

                $akun_debit_id = (int) ($row['akun_debit'] ?? 0);
                $akun_kredit_id = (int) ($row['akun_kredit'] ?? 0);

                if ($akun_debit_id < 1 || $akun_kredit_id < 1) {
                    $kategori_id = (int) ($row['kategori'] ?? 0);
                    $tipe = (int) ($row['tipe'] ?? 1);
                    if ($kategori_id > 0) {
                        updateSaldoAkunSingleFromLaba($conn, $kategori_id, $total, $tipe, $cabang);
                        $legacy_laba_count++;
                        $transaksi_count++;
                        $total_transaksi += $total;
                    }
                    continue;
                }

                $akun_debit_info = getAkunInfo($conn, $akun_debit_id);
                $akun_kredit_info = getAkunInfo($conn, $akun_kredit_id);
                if (!$akun_debit_info || !$akun_kredit_info) {
                    continue;
                }

                $kode_debit = (string) ($akun_debit_info['kode_akun'] ?? '');
                $kode_kredit = (string) ($akun_kredit_info['kode_akun'] ?? '');

                if (akun_link_is_hutang_kode($kode_debit) || akun_link_is_hutang_kode($kode_kredit)) {
                    $skipped_hutang_laba++;
                    continue;
                }
                if (akun_link_is_piutang_kode($kode_debit) || akun_link_is_piutang_kode($kode_kredit)) {
                    $skipped_piutang_laba++;
                    continue;
                }

                updateSaldoAkunFromLaba($conn, $akun_debit_id, $akun_kredit_id, $total, $cabang, 'debit');
                updateSaldoAkunFromLaba($conn, $akun_kredit_id, $akun_debit_id, $total, $cabang, 'kredit');

                $transaksi_count++;
                $total_transaksi += $total;
            }
            
            $details[] = "✓ Data Operasional (laba double-entry): $transaksi_count transaksi, Total: Rp " . number_format($total_transaksi, 0, ',', '.');
            if ($legacy_laba_count > 0) {
                $details[] = "✓ Data Operasional legacy (single-entry): $legacy_laba_count baris";
            }
            if ($skipped_hutang_laba > 0) {
                $details[] = "✓ Baris laba ke hutang dagang dilewati: $skipped_hutang_laba (rebuild dari invoice + cicilan)";
            }
            if ($skipped_piutang_laba > 0) {
                $details[] = "✓ Baris laba ke piutang dagang dilewati: $skipped_piutang_laba (rebuild dari invoice + cicilan)";
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
                $jumlah = recalculate_laba_nominal($row);
                $tipe = intval($row['tipe']);
                $cabang = akun_link_normalize_cabang_transaksi(isset($row['cabang']) ? (int) $row['cabang'] : 0);
                
                if ($jumlah <= 0) {
                    continue;
                }
                
                updateSaldoAkunSingleFromLaba($conn, $kategori_id, $jumlah, $tipe, $cabang);
                
                $transaksi_count++;
                $total_transaksi += $jumlah;
            }
            
            $details[] = "✓ Transaksi dari tabel laba (single-entry): $transaksi_count transaksi, Total: Rp " . number_format($total_transaksi, 0, ',', '.');
        }
        
        // 5. Cicilan piutang & hutang
        $piutangCicilanStats = recalculate_post_cicilan_piutang($conn);
        if ($piutangCicilanStats['count'] > 0) {
            $details[] = "✓ Cicilan Piutang: " . $piutangCicilanStats['count'] . " entri, Total: Rp " . number_format($piutangCicilanStats['total'], 0, ',', '.');
        }
        if ($piutangCicilanStats['fallback_count'] > 0) {
            $details[] = "✓ Cicilan Piutang tanpa baris piutang: " . $piutangCicilanStats['fallback_count'] . " invoice, Rp " . number_format($piutangCicilanStats['fallback_total'], 0, ',', '.');
        }
        
        $hutangCicilanStats = recalculate_post_cicilan_hutang($conn);
        if ($hutangCicilanStats['count'] > 0) {
            $details[] = "✓ Cicilan Hutang: " . $hutangCicilanStats['count'] . " entri, Total: Rp " . number_format($hutangCicilanStats['total'], 0, ',', '.');
        }
        if ($hutangCicilanStats['skipped_duplikat'] > 0) {
            $details[] = "⚠ Cicilan hutang dibatasi posting awal: " . $hutangCicilanStats['skipped_duplikat'] . " invoice (bayar > total hutang)";
        }
        if ($hutangCicilanStats['fallback_count'] > 0) {
            $details[] = "✓ Cicilan Hutang tanpa baris tabel hutang: " . $hutangCicilanStats['fallback_count'] . " invoice, Rp " . number_format($hutangCicilanStats['fallback_total'], 0, ',', '.');
        }

        $piutangSync = recalculate_sync_saldo_piutang_dari_invoice($conn);
        if ($piutangSync['synced']) {
            $details[] = "✓ Sinkron Piutang 1-1301: Rp " . number_format($piutangSync['before'], 0, ',', '.')
                . " → Rp " . number_format($piutangSync['after'], 0, ',', '.')
                . " (sesuai invoice belum lunas semua cabang)";
        } else {
            $details[] = "✓ Piutang 1-1301 sudah sesuai invoice belum lunas: Rp " . number_format($piutangSync['target'], 0, ',', '.');
        }

        $hutangSync = recalculate_sync_saldo_hutang_dari_invoice($conn);
        if ($hutangSync['synced_count'] > 0) {
            $details[] = "✓ Sinkron Hutang 2-1101: " . $hutangSync['synced_count'] . " cabang disesuaikan (total target Rp "
                . number_format($hutangSync['target_total'], 0, ',', '.') . ')';
        } else {
            $details[] = "✓ Hutang 2-1101 sudah sesuai invoice belum lunas: Rp "
                . number_format($hutangSync['target_total'], 0, ',', '.');
        }

        recalculate_nolkan_akun_header_bank($conn);
        $details[] = "✓ Akun header 1-1200 (KAS BANK) di-nolkan — bukan rekening operasional";

        if (function_exists('akun_sync_all_kas_tunai_mirror_nugrosir')) {
            $mirrorKas = akun_sync_all_kas_tunai_mirror_nugrosir($conn);
            $details[] = '✓ Mirror kas toko → Nugrosir: ' . (int) ($mirrorKas['synced'] ?? 0)
                . ' akun (' . implode(', ', $mirrorKas['codes'] ?? []) . ')';
        }
        $coaLinkLib = __DIR__ . '/aksi/coa-link-mirror-lib.php';
        if (is_file($coaLinkLib)) {
            require_once $coaLinkLib;
            if (function_exists('coa_link_mirror_sync_all')) {
                $mirrorAll = coa_link_mirror_sync_all($conn);
                $details[] = '✓ Link COA Nugrosir: ' . (int) ($mirrorAll['synced'] ?? 0) . ' akun tersinkron';
            }
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

function recalculate_laba_nominal(array $row)
{
    $total = (float) ($row['total'] ?? 0);
    if ($total > 0) {
        return $total;
    }
    $jumlah = trim((string) ($row['jumlah'] ?? ''));
    if ($jumlah === '' || !is_numeric(str_replace(',', '', $jumlah))) {
        return 0.0;
    }
    return (float) str_replace(',', '', $jumlah);
}

function recalculate_invoice_key($partA, $cabang)
{
    return (string) $partA . '|' . (int) $cabang;
}

function recalculate_query_latest_pembelian(mysqli $conn)
{
    return mysqli_query($conn, "
        SELECT ip.*
        FROM invoice_pembelian ip
        INNER JOIN (
            SELECT pembelian_invoice_parent, invoice_pembelian_cabang, MAX(invoice_pembelian_id) AS max_id
            FROM invoice_pembelian
            WHERE invoice_total > 0
            GROUP BY pembelian_invoice_parent, invoice_pembelian_cabang
        ) latest ON ip.invoice_pembelian_id = latest.max_id
        ORDER BY ip.invoice_pembelian_id ASC
    ");
}

function recalculate_query_latest_penjualan(mysqli $conn)
{
    return mysqli_query($conn, "
        SELECT i.*
        FROM invoice i
        INNER JOIN (
            SELECT penjualan_invoice, invoice_cabang, MAX(invoice_id) AS max_id
            FROM invoice
            WHERE invoice_sub_total > 0
            GROUP BY penjualan_invoice, invoice_cabang
        ) latest ON i.invoice_id = latest.max_id
        ORDER BY i.invoice_id ASC
    ");
}

function recalculate_preload_hutang_invoice_keys(mysqli $conn)
{
    $keys = [];
    $q = mysqli_query($conn, 'SELECT DISTINCT hutang_invoice_parent, hutang_cabang FROM hutang');
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $keys[recalculate_invoice_key($row['hutang_invoice_parent'], $row['hutang_cabang'])] = true;
        }
    }
    return $keys;
}

function recalculate_preload_piutang_invoice_keys(mysqli $conn)
{
    $keys = [];
    $q = mysqli_query($conn, 'SELECT DISTINCT piutang_invoice, piutang_cabang FROM piutang');
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $keys[recalculate_invoice_key($row['piutang_invoice'], $row['piutang_cabang'])] = true;
        }
    }
    return $keys;
}

function recalculate_was_pembelian_hutang($hutang, $hutangLunas, $parent, $cabang, array $hutangParents)
{
    unset($hutangLunas, $parent, $cabang, $hutangParents);
    return (int) $hutang === 1;
}

function recalculate_was_penjualan_piutang($piutang, $piutangLunas, $penjualanInvoice, $cabang, array $piutangParents)
{
    unset($piutangLunas, $penjualanInvoice, $cabang, $piutangParents);
    return (int) $piutang === 1;
}

function recalculate_invoice_perlu_cicilan_hutang($invoiceHutang, $invoiceHutangLunas)
{
    unset($invoiceHutangLunas);
    return (int) $invoiceHutang === 1;
}

function recalculate_invoice_perlu_cicilan_piutang($invoicePiutang, $invoicePiutangLunas)
{
    unset($invoicePiutangLunas);
    return (int) $invoicePiutang === 1;
}

/**
 * Set saldo 1-1301 = total piutang belum lunas semua cabang (sumber kebenaran operasional).
 *
 * @return array{target: float, before: float, after: float, synced: bool}
 */
function recalculate_sync_saldo_piutang_dari_invoice(mysqli $conn)
{
	$target = 0.0;
	$q = mysqli_query($conn, "
		SELECT COALESCE(SUM(invoice_sub_total - invoice_bayar), 0) AS target
		FROM invoice
		WHERE invoice_piutang = 1
		  AND invoice_bayar < invoice_sub_total
	");
	if ($q && ($row = mysqli_fetch_assoc($q))) {
		$target = (float) ($row['target'] ?? 0);
	}

	$rowAkun = akun_find_laba_kategori_row_exact($conn, akun_piutang_kode(), 0);
	$before = $rowAkun ? (float) ($rowAkun['saldo'] ?? 0) : 0.0;

	if ($rowAkun) {
		mysqli_query($conn, 'UPDATE laba_kategori SET saldo = ' . $target . ' WHERE id = ' . (int) $rowAkun['id']);
	} elseif (abs($target) > 0.001) {
		akun_update_saldo_delta(
			$conn,
			akun_piutang_kode(),
			'Piutang Dagang',
			'aktiva',
			'debit',
			$target,
			0
		);
	}

	return [
		'target' => $target,
		'before' => $before,
		'after' => $target,
		'synced' => abs($before - $target) > 0.001,
	];
}

/**
 * Set saldo 2-1101 per cabang = total hutang belum lunas dari invoice_pembelian.
 *
 * @return array{target_total: float, synced_count: int, per_cabang: array<int, array{target: float, before: float, synced: bool}>}
 */
function recalculate_sync_saldo_hutang_dari_invoice(mysqli $conn)
{
	$perCabang = [];
	$q = mysqli_query($conn, "
		SELECT invoice_pembelian_cabang AS cabang,
		       COALESCE(SUM(invoice_total - invoice_bayar), 0) AS target
		FROM invoice_pembelian
		WHERE invoice_hutang = 1
		  AND invoice_bayar < invoice_total
		GROUP BY invoice_pembelian_cabang
	");
	if ($q) {
		while ($row = mysqli_fetch_assoc($q)) {
			$perCabang[(int) ($row['cabang'] ?? 0)] = (float) ($row['target'] ?? 0);
		}
	}

	$syncedCount = 0;
	$targetTotal = 0.0;
	$details = [];

	foreach ($perCabang as $cabang => $target) {
		$targetTotal += $target;
		$rowAkun = akun_find_laba_kategori_row_exact($conn, akun_hutang_kode(), (int) $cabang);
		$before = $rowAkun ? (float) ($rowAkun['saldo'] ?? 0) : 0.0;
		$synced = abs($before - $target) > 0.001;

		if ($rowAkun) {
			mysqli_query($conn, 'UPDATE laba_kategori SET saldo = ' . $target . ' WHERE id = ' . (int) $rowAkun['id']);
		} elseif (abs($target) > 0.001) {
			akun_update_saldo_delta(
				$conn,
				akun_hutang_kode(),
				'Hutang Dagang',
				'pasiva',
				'kredit',
				$target,
				(int) $cabang
			);
			$synced = true;
		}

		if ($synced) {
			$syncedCount++;
		}

		$details[(int) $cabang] = [
			'target' => $target,
			'before' => $before,
			'synced' => $synced,
		];
	}

	return [
		'target_total' => $targetTotal,
		'synced_count' => $syncedCount,
		'per_cabang' => $details,
	];
}

/**
 * Cicilan hutang: satu posting per invoice dari invoice_bayar (bukan SUM tabel hutang).
 * Dibatasi max = posting awal (invoice_total - dp) agar tidak over-kredit.
 *
 * @return array{count: int, total: float, skipped_duplikat: int, fallback_count: int, fallback_total: float}
 */
function recalculate_post_cicilan_hutang(mysqli $conn)
{
    $hutangParents = recalculate_preload_hutang_invoice_keys($conn);
    $result = recalculate_query_latest_pembelian($conn);
    $count = 0;
    $total = 0.0;
    $capped = 0;
    $fallbackCount = 0;
    $fallbackTotal = 0.0;

    if (!$result) {
        return [
            'count' => 0,
            'total' => 0.0,
            'skipped_duplikat' => 0,
            'fallback_count' => 0,
            'fallback_total' => 0.0,
        ];
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $cabang = akun_link_normalize_cabang_transaksi((int) ($row['invoice_pembelian_cabang'] ?? 0));
        $parent = (string) ($row['pembelian_invoice_parent'] ?? '');
        $totalInvoice = (float) ($row['invoice_total'] ?? 0);
        $bayar = (float) ($row['invoice_bayar'] ?? 0);
        $dp = (float) ($row['invoice_hutang_dp'] ?? 0);

        if (!recalculate_invoice_perlu_cicilan_hutang(
            (int) ($row['invoice_hutang'] ?? 0),
            (int) ($row['invoice_hutang_lunas'] ?? 0)
        )) {
            continue;
        }

        $postedAwal = max(0.0, $totalInvoice - $dp);
        if ($postedAwal <= 0.001) {
            continue;
        }

        $cicilanRaw = max(0.0, $bayar - $dp);
        $isLunas = ((int) ($row['invoice_hutang_lunas'] ?? 0) === 1)
            || ((int) ($row['invoice_hutang'] ?? 0) === 0 && $bayar + 0.001 >= $totalInvoice && $totalInvoice > 0);

        if ($isLunas) {
            // Invoice sudah lunas: hapus sisa hutang di COA meski invoice_bayar/dp tidak konsisten
            $cicilan = $postedAwal;
        } else {
            $cicilan = min($cicilanRaw, $postedAwal);
        }
        if ($cicilan <= 0.001) {
            continue;
        }

        if (!$isLunas && $cicilanRaw > $postedAwal + 0.001) {
            $capped++;
        }

        $paymentTotal = min($bayar, $totalInvoice);
        if ($paymentTotal <= 0.001 && !$isLunas) {
            continue;
        }
        recalculate_apply_pelunasan_hutang($conn, $cabang, $parent, $cicilan, $paymentTotal);
        $count++;
        $total += $cicilan;

        $key = recalculate_invoice_key($parent, $cabang);
        if (!isset($hutangParents[$key])) {
            $fallbackCount++;
            $fallbackTotal += $cicilan;
        }
    }

    return [
        'count' => $count,
        'total' => $total,
        'skipped_duplikat' => $capped,
        'fallback_count' => $fallbackCount,
        'fallback_total' => $fallbackTotal,
    ];
}

function recalculate_nolkan_akun_header_bank(mysqli $conn)
{
	mysqli_query($conn, "UPDATE laba_kategori SET saldo = 0 WHERE kode_akun = '1-1200'");
}

function recalculate_apply_pelunasan_hutang(mysqli $conn, $cabang, $parent, $nominalHutang, $nominalBayar)
{
    $nominalHutang = (float) $nominalHutang;
    $nominalBayar = (float) $nominalBayar;
    $cabang = (int) $cabang;

    if ($nominalHutang <= 0.001) {
        return;
    }

    akun_update_saldo_delta(
        $conn,
        akun_hutang_kode(),
        'Hutang Dagang',
        'pasiva',
        'kredit',
        -$nominalHutang,
        $cabang
    );

    if ($nominalBayar <= 0.001) {
        return;
    }

    $chunks = recalculate_bayar_per_tipe_dari_tabel_cicilan(
        $conn,
        'hutang',
        'hutang_invoice_parent',
        $parent,
        $cabang,
        $nominalBayar,
        'hutang_tipe_pembayaran',
        'hutang_nominal',
        1
    );

    foreach ($chunks as $chunk) {
        $kodeBayar = akun_kode_pembayaran_dari_tipe($chunk['tipe'], $cabang);
        akun_update_saldo_pembayaran($conn, $cabang, $kodeBayar, -$chunk['jumlah']);
    }
}

function recalculate_apply_pelunasan_piutang(mysqli $conn, $cabang, $penjualanInvoice, $nominalPiutang, $nominalBayar)
{
    $nominalPiutang = (float) $nominalPiutang;
    $nominalBayar = (float) $nominalBayar;
    $cabang = (int) $cabang;

    if ($nominalPiutang <= 0.001) {
        return;
    }

    akun_update_saldo_delta(
        $conn,
        akun_piutang_kode(),
        'Piutang Dagang',
        'aktiva',
        'debit',
        -$nominalPiutang,
        0
    );

    if ($nominalBayar <= 0.001) {
        return;
    }

    $chunks = recalculate_bayar_per_tipe_dari_tabel_cicilan(
        $conn,
        'piutang',
        'piutang_invoice',
        $penjualanInvoice,
        $cabang,
        $nominalBayar,
        'piutang_tipe_pembayaran',
        'piutang_nominal',
        0
    );

    foreach ($chunks as $chunk) {
        $kodeBayar = akun_kode_pembayaran_dari_tipe($chunk['tipe'], $cabang);
        akun_update_saldo_pembayaran($conn, $cabang, $kodeBayar, $chunk['jumlah']);
    }
}

/**
 * Bagi nominal bayar cicilan per tipe pembayaran dari tabel cicilan (dibatasi maxTotal).
 * Default tipe: hutang=1 (bank), piutang=0 (kas).
 *
 * @return list<array{tipe: int, jumlah: float}>
 */
function recalculate_bayar_per_tipe_dari_tabel_cicilan(
    mysqli $conn,
    $table,
    $parentColumn,
    $parentValue,
    $cabang,
    $maxTotal,
    $tipeColumn,
    $nominalColumn,
    $defaultTipe
) {
    $maxTotal = (float) $maxTotal;
    if ($maxTotal <= 0.001) {
        return [];
    }

    $allowedTables = [
        'hutang' => ['parent' => 'hutang_invoice_parent', 'cabang' => 'hutang_cabang', 'tipe' => 'hutang_tipe_pembayaran', 'nominal' => 'hutang_nominal'],
        'piutang' => ['parent' => 'piutang_invoice', 'cabang' => 'piutang_cabang', 'tipe' => 'piutang_tipe_pembayaran', 'nominal' => 'piutang_nominal'],
    ];

    if (!isset($allowedTables[$table])) {
        return [['tipe' => (int) $defaultTipe, 'jumlah' => $maxTotal]];
    }

    $meta = $allowedTables[$table];
    if ($parentColumn !== $meta['parent'] || $tipeColumn !== $meta['tipe'] || $nominalColumn !== $meta['nominal']) {
        return [['tipe' => (int) $defaultTipe, 'jumlah' => $maxTotal]];
    }

    $parentEsc = mysqli_real_escape_string($conn, (string) $parentValue);
    $cabang = (int) $cabang;
    $q = mysqli_query($conn, "
        SELECT {$meta['tipe']} AS tipe, SUM({$meta['nominal']}) AS jumlah
        FROM {$table}
        WHERE {$meta['parent']} = '$parentEsc'
          AND {$meta['cabang']} = $cabang
        GROUP BY {$meta['tipe']}
    ");

    $chunks = [];
    $sum = 0.0;
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $jumlah = (float) ($row['jumlah'] ?? 0);
            if ($jumlah <= 0.001) {
                continue;
            }
            $chunks[] = [
                'tipe' => (int) ($row['tipe'] ?? $defaultTipe),
                'jumlah' => $jumlah,
            ];
            $sum += $jumlah;
        }
    }

    if ($sum <= 0.001) {
        return [['tipe' => (int) $defaultTipe, 'jumlah' => $maxTotal]];
    }

    if ($sum > $maxTotal + 0.001) {
        $scale = $maxTotal / $sum;
        foreach ($chunks as &$chunk) {
            $chunk['jumlah'] = round($chunk['jumlah'] * $scale, 2);
        }
        unset($chunk);
        return $chunks;
    }

    if ($sum + 0.001 < $maxTotal) {
        $chunks[] = [
            'tipe' => (int) $defaultTipe,
            'jumlah' => round($maxTotal - $sum, 2),
        ];
    }

    return $chunks;
}

function recalculate_last_hutang_tipe_pembayaran(mysqli $conn, $parent, $cabang)
{
    $parentEsc = mysqli_real_escape_string($conn, (string) $parent);
    $cabang = (int) $cabang;
    $q = mysqli_query($conn, "
        SELECT hutang_tipe_pembayaran
        FROM hutang
        WHERE hutang_invoice_parent = '$parentEsc'
          AND hutang_cabang = $cabang
        ORDER BY hutang_id DESC
        LIMIT 1
    ");
    if ($q && ($row = mysqli_fetch_assoc($q))) {
        return (int) ($row['hutang_tipe_pembayaran'] ?? 0);
    }
    return 1;
}

/**
 * @return array{count: int, total: float, skipped_duplikat: int, fallback_count: int, fallback_total: float}
 */
function recalculate_post_cicilan_piutang(mysqli $conn)
{
    $piutangParents = recalculate_preload_piutang_invoice_keys($conn);
    $result = recalculate_query_latest_penjualan($conn);
    $count = 0;
    $total = 0.0;
    $capped = 0;
    $fallbackCount = 0;
    $fallbackTotal = 0.0;

    if (!$result) {
        return [
            'count' => 0,
            'total' => 0.0,
            'skipped_duplikat' => 0,
            'fallback_count' => 0,
            'fallback_total' => 0.0,
        ];
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $cabang = akun_link_normalize_cabang_transaksi((int) ($row['invoice_cabang'] ?? 0));
        $penjualanInvoice = (string) ($row['penjualan_invoice'] ?? '');
        $subTotal = (float) ($row['invoice_sub_total'] ?? 0);
        $bayar = (float) ($row['invoice_bayar'] ?? 0);
        $dp = (float) ($row['invoice_piutang_dp'] ?? 0);

        if (!recalculate_invoice_perlu_cicilan_piutang(
            (int) ($row['invoice_piutang'] ?? 0),
            (int) ($row['invoice_piutang_lunas'] ?? 0)
        )) {
            continue;
        }

        $postedAwal = max(0.0, $subTotal - $dp);
        if ($postedAwal <= 0.001) {
            continue;
        }

        $cicilanRaw = max(0.0, $bayar - $dp);
        $isLunas = ((int) ($row['invoice_piutang_lunas'] ?? 0) === 1)
            || ((int) ($row['invoice_piutang'] ?? 0) === 0 && $bayar + 0.001 >= $subTotal && $subTotal > 0);

        if ($isLunas) {
            $cicilan = $postedAwal;
        } else {
            $cicilan = min($cicilanRaw, $postedAwal);
        }
        if ($cicilan <= 0.001) {
            continue;
        }

        if (!$isLunas && $cicilanRaw > $postedAwal + 0.001) {
            $capped++;
        }

        $bayarCicilan = min($cicilanRaw, $cicilan);
        recalculate_apply_pelunasan_piutang($conn, $cabang, $penjualanInvoice, $cicilan, $bayarCicilan);
        $count++;
        $total += $cicilan;

        $key = recalculate_invoice_key($penjualanInvoice, $cabang);
        if (!isset($piutangParents[$key])) {
            $fallbackCount++;
            $fallbackTotal += $cicilan;
        }
    }

    return [
        'count' => $count,
        'total' => $total,
        'skipped_duplikat' => $capped,
        'fallback_count' => $fallbackCount,
        'fallback_total' => $fallbackTotal,
    ];
}

function recalculate_last_piutang_tipe_pembayaran(mysqli $conn, $penjualanInvoice, $cabang)
{
    $invEsc = mysqli_real_escape_string($conn, (string) $penjualanInvoice);
    $cabang = (int) $cabang;
    $q = mysqli_query($conn, "
        SELECT piutang_tipe_pembayaran
        FROM piutang
        WHERE piutang_invoice = '$invEsc'
          AND piutang_cabang = $cabang
        ORDER BY piutang_id DESC
        LIMIT 1
    ");
    if ($q && ($row = mysqli_fetch_assoc($q))) {
        return (int) ($row['piutang_tipe_pembayaran'] ?? 0);
    }
    return 0;
}

// Fungsi helper untuk mendapatkan info akun berdasarkan ID
function getAkunInfo($conn, $akun_id) {
    $query = "SELECT id, kode_akun, name, kategori, tipe_akun, saldo, cabang FROM laba_kategori WHERE id = " . (int)$akun_id;
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

function recalculate_delta_dari_posisi_laba($kategori, $tipe_akun, $jumlah, $posisi)
{
    $kategori = strtolower(trim((string) $kategori));
    $tipe_akun = strtolower(trim((string) $tipe_akun));
    $jumlah = (float) $jumlah;
    $perubahan_saldo = 0.0;

    if ($kategori === 'aktiva') {
        $perubahan_saldo = ($posisi === 'debit') ? $jumlah : -$jumlah;
        if ($tipe_akun === 'kredit') {
            $perubahan_saldo = -$perubahan_saldo;
        }
    } elseif ($kategori === 'pasiva') {
        $perubahan_saldo = ($posisi === 'debit') ? -$jumlah : $jumlah;
        if ($tipe_akun === 'debit') {
            $perubahan_saldo = -$perubahan_saldo;
        }
    } elseif ($kategori === 'modal') {
        $perubahan_saldo = ($posisi === 'debit') ? -$jumlah : $jumlah;
        if ($tipe_akun === 'debit') {
            $perubahan_saldo = -$perubahan_saldo;
        }
    } else {
        return null;
    }

    return $perubahan_saldo;
}

/**
 * Arahkan kode kas lama ke akun per cabang. Rekening bank legacy (1-1200–1-1204) tidak digabung.
 *
 * @return array{kode: string, nama: string, kategori: string, tipe: string, cabang: int}|null
 */
function recalculate_canonical_kas_bank_target(array $akunInfo, $cabangTransaksi)
{
    $kode = (string) ($akunInfo['kode_akun'] ?? '');
    $cabangTransaksi = akun_link_normalize_cabang_transaksi((int) $cabangTransaksi);

    if ($kode === '1-1100') {
        return [
            'kode' => akun_kas_tunai_kode($cabangTransaksi),
            'nama' => akun_kas_tunai_nama($cabangTransaksi),
            'kategori' => 'aktiva',
            'tipe' => 'debit',
            'cabang' => $cabangTransaksi,
        ];
    }

    if (in_array($kode, ['1-1152', '1-1153'], true)) {
        return [
            'kode' => akun_kas_bank_bri_kode($cabangTransaksi),
            'nama' => akun_kas_bank_bri_nama($cabangTransaksi),
            'kategori' => 'aktiva',
            'tipe' => 'debit',
            'cabang' => $cabangTransaksi,
        ];
    }

    if (akun_is_kas_tunai_kode($kode)) {
        return [
            'kode' => akun_kas_tunai_kode($cabangTransaksi),
            'nama' => akun_kas_tunai_nama($cabangTransaksi),
            'kategori' => 'aktiva',
            'tipe' => 'debit',
            'cabang' => $cabangTransaksi,
        ];
    }

    if ($kode === '1-1202' || akun_is_kas_bank_bri_kode($kode)) {
        return [
            'kode' => akun_kas_bank_bri_kode($cabangTransaksi),
            'nama' => akun_kas_bank_bri_nama($cabangTransaksi),
            'kategori' => 'aktiva',
            'tipe' => 'debit',
            'cabang' => $cabangTransaksi,
        ];
    }

    // 1-1200 header, 1-1201 BNU, 1-1203 koperasi, 1-1204 gaji — tetap di baris asli (biasanya cabang 0)
    return null;
}

// Fungsi helper untuk update saldo akun dari tabel laba (double-entry)
function updateSaldoAkunFromLaba($conn, $akun_id, $akun_pasangan, $jumlah, $cabang, $posisi) {
    $akun_info = getAkunInfo($conn, (int) $akun_id);
    if (!$akun_info) {
        return;
    }

    $cabang = akun_link_normalize_cabang_transaksi((int) $cabang);
    $kategori = $akun_info['kategori'] ?? '';
    $tipe_akun = $akun_info['tipe_akun'] ?? '';
    $delta = recalculate_delta_dari_posisi_laba($kategori, $tipe_akun, $jumlah, $posisi);
    if ($delta === null || abs($delta) < 0.0001) {
        return;
    }

    $canonical = recalculate_canonical_kas_bank_target($akun_info, $cabang);
    if ($canonical !== null) {
        akun_update_saldo_delta(
            $conn,
            $canonical['kode'],
            $canonical['nama'],
            $canonical['kategori'],
            $canonical['tipe'],
            $delta,
            $canonical['cabang']
        );
        return;
    }

    $akun_cabang = $akun_info['cabang'] ?? null;
    if ($akun_cabang !== null && $akun_cabang != $cabang && (int) $akun_cabang !== 0) {
        return;
    }

    $saldo_sekarang = floatval($akun_info['saldo'] ?? 0);
    $saldo_baru = $saldo_sekarang + $delta;
    mysqli_query($conn, 'UPDATE laba_kategori SET saldo = ' . $saldo_baru . ' WHERE id = ' . (int) $akun_id);
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
                        <p>Script ini akan menghitung ulang saldo COA dari nol dengan urutan:</p>
                        <ol>
                            <li>Reset semua saldo <code>laba_kategori</code> → 0</li>
                            <li><strong>Penjualan</strong> — tunai → kas cabang, transfer/QRIS → bank BRI, piutang → 1-1301</li>
                            <li><strong>Pembelian</strong> — lunas mengurangi bank (Nugrosir) atau kas (toko); hutang → 2-1101</li>
                            <li><strong>Data Operasional</strong> (<code>laba</code>) — double-entry termasuk transfer uang/setor tunai</li>
                            <li><strong>Cicilan</strong> piutang & hutang, lalu sinkron saldo piutang/hutang dari invoice</li>
                        </ol>
                        <p><strong>Penyesuaian legacy:</strong> Cabang 4 (BAQNU nonaktif) dinormalisasi ke cabang 5 (Tegalrejo). Transfer/setor dipetakan ke kas &amp; bank cabang transaksi (bukan baris COA salah). Baris laba dengan <code>total</code> kosong memakai kolom <code>jumlah</code>.</p>
                        <p><strong>Penting:</strong> Jalankan saat toko tutup &amp; backup database dulu. Live POS tetap tidak memotong kas saat pembelian lunas — hanya hitung ulang yang lengkap.</p>
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
