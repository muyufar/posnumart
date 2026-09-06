<?php
/**
 * Pencarian produk kasir (DataTables server-side) — ringan untuk live Hostinger.
 * Hanya cari kode + nama, tanpa SQL_CALC_FOUND_ROWS / COUNT seluruh tabel barang.
 */

if (!function_exists('beli_langsung_search_json')) {
    function beli_langsung_search_json(array $payload, $httpCode = 200)
    {
        if (!headers_sent()) {
            http_response_code((int) $httpCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        echo json_encode($payload, $flags);
        exit;
    }
}

if (!function_exists('beli_langsung_search_data_output')) {
    /**
     * @param mysqli $conn
     * @param int    $cabang
     * @param string $hargaColumn  barang_harga | barang_harga_grosir_1 | barang_harga_grosir_2
     * @param bool   $requireStock true = barang_stock > 0 (umum & grosir 2)
     */
    function beli_langsung_search_data_output($conn, $cabang, $hargaColumn, $requireStock = true)
    {
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (!$conn instanceof mysqli) {
            beli_langsung_search_json([
                'draw' => 0,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Koneksi database gagal',
            ], 500);
        }

        mysqli_set_charset($conn, 'utf8mb4');

        $allowedHarga = [
            'barang_harga' => 'barang_harga',
            'barang_harga_grosir_1' => 'barang_harga_grosir_1',
            'barang_harga_grosir_2' => 'barang_harga_grosir_2',
        ];
        $hargaCol = $allowedHarga[$hargaColumn] ?? 'barang_harga';
        $cabang = (int) $cabang;

        $req = array_merge($_GET, $_POST);
        $draw = (int) ($req['draw'] ?? 1);
        $start = max(0, (int) ($req['start'] ?? 0));
        $length = (int) ($req['length'] ?? 10);
        if ($length < 1 || $length > 50) {
            $length = 10;
        }

        $search = '';
        if (isset($req['search']) && is_array($req['search'])) {
            $search = trim((string) ($req['search']['value'] ?? ''));
        }

        $where = [
            'barang_status = 1',
            "barang_cabang = {$cabang}",
            "{$hargaCol} > 0",
            'barang_stock >= satuan_isi_1',
        ];
        if ($requireStock) {
            $where[] = 'barang_stock > 0';
        }

        if ($search !== '') {
            $esc = mysqli_real_escape_string($conn, $search);
            $where[] = "(barang_kode LIKE '%{$esc}%' OR barang_nama LIKE '%{$esc}%')";
        }

        $whereSql = implode(' AND ', $where);

        $orderMap = [
            1 => 'barang_kode',
            2 => 'barang_nama',
            3 => $hargaCol,
            4 => 'barang_stock',
        ];
        $orderCol = isset($req['order'][0]['column']) ? (int) $req['order'][0]['column'] : 2;
        $orderDir = (isset($req['order'][0]['dir']) && strtolower((string) $req['order'][0]['dir']) === 'desc')
            ? 'DESC'
            : 'ASC';
        $orderBy = $orderMap[$orderCol] ?? 'barang_nama';

        $sqlTotal = "SELECT COUNT(*) AS c FROM barang WHERE barang_status = 1 AND barang_cabang = {$cabang}";
        $resTotal = mysqli_query($conn, $sqlTotal);
        $recordsTotal = ($resTotal && ($row = mysqli_fetch_assoc($resTotal))) ? (int) $row['c'] : 0;

        if ($search === '') {
            $recordsFiltered = $recordsTotal;
            $sqlFiltered = "SELECT COUNT(*) AS c FROM barang WHERE {$whereSql}";
            $resFiltered = mysqli_query($conn, $sqlFiltered);
            if ($resFiltered && ($row = mysqli_fetch_assoc($resFiltered))) {
                $recordsFiltered = (int) $row['c'];
            }
        } else {
            $sqlFiltered = "SELECT COUNT(*) AS c FROM barang WHERE {$whereSql}";
            $resFiltered = mysqli_query($conn, $sqlFiltered);
            if (!$resFiltered) {
                beli_langsung_search_json([
                    'draw' => $draw,
                    'recordsTotal' => $recordsTotal,
                    'recordsFiltered' => 0,
                    'data' => [],
                    'error' => 'Gagal menghitung hasil pencarian',
                ], 500);
            }
            $recordsFiltered = (int) (mysqli_fetch_assoc($resFiltered)['c'] ?? 0);
        }

        $sqlItems = "
            SELECT barang_id, barang_kode, barang_nama, {$hargaCol} AS harga_jual, barang_stock
            FROM barang
            WHERE {$whereSql}
            ORDER BY {$orderBy} {$orderDir}, barang_id DESC
            LIMIT {$start}, {$length}
        ";
        $resItems = mysqli_query($conn, $sqlItems);
        if (!$resItems) {
            beli_langsung_search_json([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => [],
                'error' => 'Gagal memuat data barang',
            ], 500);
        }

        $data = [];
        while ($r = mysqli_fetch_assoc($resItems)) {
            $data[] = [
                (string) ($r['barang_id'] ?? ''),
                (string) ($r['barang_kode'] ?? ''),
                (string) ($r['barang_nama'] ?? ''),
                (string) ($r['harga_jual'] ?? '0'),
                (string) ($r['barang_stock'] ?? '0'),
            ];
        }

        beli_langsung_search_json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }
}
