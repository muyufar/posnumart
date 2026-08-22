<?php
/**
 * Master satuan terpusat di cabang 0 (pusat/gudang).
 */

if (!defined('SATUAN_CABANG_PUSAT')) {
    define('SATUAN_CABANG_PUSAT', 0);
}

if (!function_exists('satuan_sql_cabang')) {
    /** Klausa SQL: satuan_cabang = 0 (opsional alias tabel). */
    function satuan_sql_cabang($tableAlias = '')
    {
        $col = $tableAlias !== '' ? $tableAlias . '.satuan_cabang' : 'satuan_cabang';
        return $col . ' = ' . SATUAN_CABANG_PUSAT;
    }
}

if (!function_exists('satuan_guard_pusat_only')) {
    /** Redirect jika bukan login cabang pusat (untuk halaman kelola master satuan). */
    function satuan_guard_pusat_only($sessionCabang)
    {
        if ((int) $sessionCabang !== SATUAN_CABANG_PUSAT) {
            echo "<script>alert('Master Satuan hanya dikelola dari Pusat (Gudang).'); document.location.href='bo';</script>";
            exit;
        }
    }
}

if (!function_exists('satuan_next_id')) {
    /** ID berikutnya; kolom satuan_id di DB bukan AUTO_INCREMENT. */
    function satuan_next_id($conn)
    {
        $res = mysqli_query($conn, 'SELECT IFNULL(MAX(satuan_id), 0) + 1 AS next_id FROM satuan');
        $row = $res ? mysqli_fetch_assoc($res) : null;
        return $row ? (int) $row['next_id'] : 1;
    }
}

if (!function_exists('satuan_nama_by_id')) {
    /** Nama satuan; prioritas cabang pusat, fallback data lama per cabang. */
    function satuan_nama_by_id($conn, $satuanId)
    {
        $satuanId = (int) $satuanId;
        if ($satuanId <= 0) {
            return '';
        }
        $sql = 'SELECT satuan_nama FROM satuan WHERE satuan_id = ' . $satuanId
            . ' AND ' . satuan_sql_cabang() . ' LIMIT 1';
        $res = mysqli_query($conn, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if ($row) {
            return (string) $row['satuan_nama'];
        }
        // Fallback: satuan lama per cabang (sebelum terpusat)
        $legacy = mysqli_query($conn, 'SELECT satuan_nama FROM satuan WHERE satuan_id = ' . $satuanId . ' LIMIT 1');
        $legacyRow = $legacy ? mysqli_fetch_assoc($legacy) : null;
        return $legacyRow ? (string) $legacyRow['satuan_nama'] : '';
    }
}

if (!function_exists('satuan_list_active')) {
    function satuan_list_active($order = 'satuan_id DESC')
    {
        return query(
            'SELECT * FROM satuan WHERE satuan_status > 0 AND '
            . satuan_sql_cabang()
            . ' ORDER BY ' . $order
        );
    }
}
