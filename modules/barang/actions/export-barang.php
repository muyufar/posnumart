<?php
require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';

@set_time_limit(300);
@ini_set('memory_limit', '512M');

try {
    require numart_path('aksi/koneksi.php');
    require numart_path('aksi/api-session.php');
    require_once numart_path('aksi/functions.php');

    if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
        http_response_code(403);
        exit('Akses ditolak');
    }

    mysqli_set_charset($conn, 'utf8mb4');
    barang_harga_beli_rata_ensure_column($conn);

    $cabang = (int) $sessionCabang;
    $hppExpr = barang_hpp_sql_expr('a');

    $query = "
        SELECT
            a.barang_kode,
            a.barang_nama,
            COALESCE(b.kategori_nama, '-') AS kategori_nama,
            {$hppExpr} AS hpp_tampil,
            a.barang_harga,
            a.barang_stock,
            IFNULL(a.kode_suplier, '') AS kode_suplier
        FROM barang a
        LEFT JOIN kategori b ON a.kategori_id = b.kategori_id
        WHERE a.barang_status = '1'
          AND a.barang_cabang = {$cabang}
        ORDER BY a.barang_nama ASC, a.barang_kode ASC
    ";

    $result = mysqli_query($conn, $query);
    if (!$result) {
        throw new RuntimeException(mysqli_error($conn));
    }

    $tokoLabel = 'Cabang ' . $cabang;
    $tokoRes = mysqli_query($conn, 'SELECT toko_nama, toko_kota FROM toko WHERE toko_cabang = ' . $cabang . ' LIMIT 1');
    if ($tokoRes && ($tokoRow = mysqli_fetch_assoc($tokoRes))) {
        $tokoLabel = trim(($tokoRow['toko_nama'] ?? '') . ' ' . ($tokoRow['toko_kota'] ?? ''));
    }

    $filename = 'Data_Barang_Aktif_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $tokoLabel) . '_' . date('Ymd_His') . '.xls';

    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<h2>DATA BARANG AKTIF</h2>';
    echo '<p>' . htmlspecialchars($tokoLabel, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p>Dicetak: ' . date('d/m/Y H:i') . ' | Hanya barang status aktif</p>';
    echo '<table border="1" cellspacing="0" cellpadding="4">';
    echo '<thead><tr style="background-color:#1E3A5F;color:#FFFFFF;font-weight:bold;">';
    echo '<th>No</th>';
    echo '<th>Kode Barang</th>';
    echo '<th>Nama Barang</th>';
    echo '<th>Kategori</th>';
    echo '<th>Kode Suplier</th>';
    echo '<th>Harga Beli (HPP)</th>';
    echo '<th>Harga Umum</th>';
    echo '<th>Stock</th>';
    echo '</tr></thead><tbody>';

    $no = 1;
    $totalStock = 0;
    $hasRows = false;

    while ($row = mysqli_fetch_assoc($result)) {
        $hasRows = true;
        $stock = (float) ($row['barang_stock'] ?? 0);
        $totalStock += $stock;

        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td style="mso-number-format:\'\\@\';">' . htmlspecialchars((string) $row['barang_kode'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $row['barang_nama'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $row['kategori_nama'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $row['kode_suplier'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . (float) $row['hpp_tampil'] . '</td>';
        echo '<td>' . (float) $row['barang_harga'] . '</td>';
        echo '<td>' . $stock . '</td>';
        echo '</tr>';
    }

    if (!$hasRows) {
        echo '<tr><td colspan="8" style="text-align:center;">Tidak ada data barang aktif</td></tr>';
    } else {
        echo '<tr style="font-weight:bold;background-color:#E8EEF4;">';
        echo '<td colspan="7" style="text-align:right;">TOTAL STOCK</td>';
        echo '<td>' . $totalStock . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></body></html>';
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:24px">';
    echo '<h2>Export barang gagal</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
}
exit;
