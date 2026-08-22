<?php
/**
 * Helper katalog promo (produk-analisa).
 * Kategori filter selalu dari master Nugrosir (kategori_cabang = 0).
 */

if (!function_exists('katalog_promo_require_auth')) {
    function katalog_promo_require_auth()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $level = (string) ($_SESSION['user_level'] ?? '');
        if ($level === '' || $level === 'kasir' || $level === 'kurir') {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        return $level;
    }
}

if (!function_exists('katalog_promo_resolve_cabang')) {
    function katalog_promo_resolve_cabang($requestedCabang)
    {
        $level = (string) ($_SESSION['user_level'] ?? '');
        $sessionCabang = (int) ($_SESSION['user_cabang'] ?? 0);
        $requestedCabang = (int) $requestedCabang;
        if ($level !== 'super admin') {
            return $sessionCabang;
        }
        return $requestedCabang;
    }
}

if (!function_exists('katalog_promo_nugrosir_kategori_ids')) {
    /**
     * ID kategori Nugrosir + ID kategori cabang lain dengan nama yang sama.
     *
     * @return int[]
     */
    function katalog_promo_nugrosir_kategori_ids($conn, $kategoriId)
    {
        $kategoriId = (int) $kategoriId;
        if ($kategoriId < 1 || !$conn) {
            return [];
        }
        $ids = [$kategoriId];
        $res = mysqli_query($conn, "SELECT kategori_nama FROM kategori WHERE kategori_id = {$kategoriId} LIMIT 1");
        $row = $res ? mysqli_fetch_assoc($res) : null;
        $nama = trim((string) ($row['kategori_nama'] ?? ''));
        if ($nama === '') {
            return $ids;
        }
        $namaEsc = mysqli_real_escape_string($conn, $nama);
        $res2 = mysqli_query($conn, "SELECT kategori_id FROM kategori WHERE kategori_nama = '{$namaEsc}'");
        if ($res2) {
            while ($r = mysqli_fetch_assoc($res2)) {
                $ids[] = (int) $r['kategori_id'];
            }
        }
        $ids = array_values(array_unique(array_filter($ids, function ($id) {
            return $id > 0;
        })));
        return $ids;
    }
}

if (!function_exists('katalog_promo_kategori_sql')) {
    function katalog_promo_kategori_sql($conn, $kategoriId, $barangAlias = 'b')
    {
        $ids = katalog_promo_nugrosir_kategori_ids($conn, $kategoriId);
        if (!$ids) {
            return '';
        }
        $in = implode(',', $ids);
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $barangAlias);
        if ($a === '') {
            $a = 'b';
        }
        return " AND ({$a}.kategori_id IN ({$in}) OR {$a}.barang_kategori_id IN ({$in}))";
    }
}

if (!function_exists('katalog_promo_toko_footer')) {
    /**
     * Toko ritel aktif (bukan Nugrosir cabang 0) untuk footer flyer.
     *
     * @return array<int, array{nama:string,kota:string,cabang:int}>
     */
    function katalog_promo_toko_footer($conn)
    {
        $out = [];
        if (!$conn) {
            return $out;
        }
        $res = mysqli_query(
            $conn,
            "SELECT toko_cabang, toko_nama, toko_kota
             FROM toko
             WHERE toko_status = '1' AND toko_cabang > 0
             ORDER BY toko_cabang ASC"
        );
        if (!$res) {
            return $out;
        }
        while ($row = mysqli_fetch_assoc($res)) {
            $nama = trim((string) ($row['toko_nama'] ?? ''));
            $kota = trim((string) ($row['toko_kota'] ?? ''));
            $label = $nama !== '' ? $nama : ('NUMART ' . $kota);
            if ($label === 'NUMART ') {
                $label = 'Cabang ' . (int) $row['toko_cabang'];
            }
            $out[] = [
                'cabang' => (int) $row['toko_cabang'],
                'nama' => $label,
                'kota' => $kota,
            ];
        }
        return $out;
    }
}

if (!function_exists('katalog_promo_gambar_select_sql')) {
    /**
     * Gambar dari baris cabang, fallback ke master Nugrosir (cabang 0) by barcode.
     */
    function katalog_promo_gambar_select_sql($barangAlias = 'b')
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $barangAlias);
        if ($a === '') {
            $a = 'b';
        }
        return "COALESCE(NULLIF(TRIM({$a}.barang_gambar), ''), (
            SELECT bm.barang_gambar
            FROM barang bm
            WHERE bm.barang_kode = {$a}.barang_kode
              AND bm.barang_cabang = 0
              AND IFNULL(TRIM(bm.barang_gambar), '') != ''
            LIMIT 1
        ))";
    }
}

if (!function_exists('katalog_promo_gambar_filter_sql')) {
    function katalog_promo_gambar_filter_sql($barangAlias = 'b')
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $barangAlias);
        if ($a === '') {
            $a = 'b';
        }
        return "(IFNULL(TRIM({$a}.barang_gambar), '') != ''
            OR EXISTS (
                SELECT 1 FROM barang bm
                WHERE bm.barang_kode = {$a}.barang_kode
                  AND bm.barang_cabang = 0
                  AND IFNULL(TRIM(bm.barang_gambar), '') != ''
            ))";
    }
}

if (!function_exists('katalog_promo_item_from_row')) {
    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    function katalog_promo_item_from_row(array $row)
    {
        $gambarStored = trim((string) ($row['barang_gambar'] ?? ''));
        $gambarUrl = '';
        if ($gambarStored !== '' && function_exists('barang_gambar_public_url')) {
            $gambarUrl = barang_gambar_public_url($gambarStored);
        } elseif ($gambarStored !== '') {
            $gambarUrl = $gambarStored;
        }
        $satuan = trim((string) ($row['satuan_nama'] ?? ''));
        if ($satuan === '') {
            $satuan = 'Pcs';
        }
        $harga = (float) ($row['barang_harga'] ?? 0);

        return [
            'id' => (int) ($row['barang_id'] ?? 0),
            'kode' => (string) ($row['barang_kode'] ?? ''),
            'nama' => (string) ($row['barang_nama'] ?? ''),
            'kategori' => (string) ($row['kategori_nama'] ?? '-'),
            'satuan' => $satuan,
            'harga' => $harga,
            'harga_coret' => 0,
            'stok' => (float) ($row['barang_stock'] ?? 0),
            'gambar' => $gambarUrl,
        ];
    }
}
