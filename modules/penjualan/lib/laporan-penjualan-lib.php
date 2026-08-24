<?php
/**
 * Library laporan penjualan (standar POS retail).
 */

function lpj_sanitize_date(string $s, string $fallback): string
{
    $s = trim($s);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) !== 1) {
        return $fallback;
    }
    return $s;
}

function lpj_parse_periode($dariRaw, $sampaiRaw): array
{
    $today = date('Y-m-d');
    $defaultDari = date('Y-m-01');
    $dari = lpj_sanitize_date((string) $dariRaw, $defaultDari);
    $sampai = lpj_sanitize_date((string) $sampaiRaw, $today);
    if (strtotime($dari) > strtotime($sampai)) {
        $tmp = $dari;
        $dari = $sampai;
        $sampai = $tmp;
    }
    return ['dari' => $dari, 'sampai' => $sampai];
}

function lpj_get_toko($conn, int $cabang): array
{
    $cabang = (int) $cabang;
    $res = mysqli_query($conn, "SELECT * FROM toko WHERE toko_cabang = $cabang LIMIT 1");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return $row;
    }
    return [
        'toko_nama' => 'Toko',
        'toko_alamat' => '',
        'toko_kota' => '',
        'toko_tlpn' => '',
        'toko_wa' => '',
        'toko_email' => '',
    ];
}

/**
 * @return array{dari:string,sampai:string,cabang:int,customer_id:int,kasir_id:int,status_bayar:string,metode_bayar:string}
 */
function lpj_parse_filters($conn, array $input, int $sessionCabang, string $levelLogin): array
{
    $periode = lpj_parse_periode($input['dari'] ?? '', $input['sampai'] ?? '');
    $cabang = $sessionCabang;
    if ($levelLogin === 'super admin' && isset($input['cabang']) && $input['cabang'] !== '' && $input['cabang'] !== 'semua') {
        $cabang = (int) $input['cabang'];
    }

    return [
        'dari' => $periode['dari'],
        'sampai' => $periode['sampai'],
        'cabang' => $cabang,
        'customer_id' => (int) ($input['customer_id'] ?? 0),
        'kasir_id' => (int) ($input['kasir_id'] ?? 0),
        'status_bayar' => trim((string) ($input['status_bayar'] ?? '')),
        'metode_bayar' => trim((string) ($input['metode_bayar'] ?? '')),
    ];
}

function lpj_status_bayar_label(array $row): string
{
    $piutang = (int) ($row['invoice_piutang'] ?? 0);
    $lunas = (int) ($row['invoice_piutang_lunas'] ?? 0);
    if ($piutang < 1) {
        return 'Lunas';
    }
    if ($lunas >= 1) {
        return 'Piutang Lunas';
    }
    return 'Piutang';
}

function lpj_status_bayar_badge(string $label): string
{
    if ($label === 'Lunas') {
        return 'success';
    }
    if ($label === 'Piutang Lunas') {
        return 'info';
    }
    return 'warning';
}

function lpj_metode_bayar_label(array $row): string
{
    $tipe = (int) ($row['invoice_tipe_transaksi'] ?? 0);
    return $tipe >= 1 ? 'Transfer' : 'Tunai';
}

function lpj_format_rupiah($n): string
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function lpj_format_qty($n): string
{
    $v = (float) $n;
    if (abs($v - round($v)) < 0.0001) {
        return number_format($v, 0, ',', '.');
    }
    return number_format($v, 2, ',', '.');
}

function lpj_kasir_nama(array $row): string
{
    if (trim((string) ($row['invoice_marketplace'] ?? '')) !== '') {
        if (function_exists('marketplace_kasir_label')) {
            return marketplace_kasir_label();
        }
        return 'Marketplace';
    }
    return $row['kasir_nama'] ?? '-';
}

function lpj_build_where(array $filters, string $alias = 'inv'): array
{
    $conds = [
        "$alias.invoice_date BETWEEN ? AND ?",
        "$alias.invoice_cabang = ?",
        "$alias.invoice_draft = 0",
    ];
    $types = 'ssi';
    $params = [$filters['dari'], $filters['sampai'], $filters['cabang']];

    if ($filters['customer_id'] > 0) {
        $conds[] = "$alias.invoice_customer = ?";
        $types .= 'i';
        $params[] = $filters['customer_id'];
    }

    if ($filters['kasir_id'] > 0) {
        $conds[] = "$alias.invoice_kasir = ?";
        $types .= 'i';
        $params[] = $filters['kasir_id'];
    }

    $status = $filters['status_bayar'];
    if ($status === 'lunas') {
        $conds[] = "$alias.invoice_piutang < 1";
    } elseif ($status === 'piutang') {
        $conds[] = "$alias.invoice_piutang >= 1 AND $alias.invoice_piutang_lunas < 1";
    } elseif ($status === 'piutang_lunas') {
        $conds[] = "$alias.invoice_piutang >= 1 AND $alias.invoice_piutang_lunas >= 1";
    }

    $metode = $filters['metode_bayar'];
    if ($metode === 'tunai') {
        $conds[] = "$alias.invoice_tipe_transaksi < 1";
    } elseif ($metode === 'transfer') {
        $conds[] = "$alias.invoice_tipe_transaksi >= 1";
    }

    return [
        'where' => implode(' AND ', $conds),
        'types' => $types,
        'params' => $params,
    ];
}

function lpj_bind_params($stmt, string $types, array $params): void
{
    if ($types === '' || $params === []) {
        return;
    }
    $stmt->bind_param($types, ...$params);
}

function lpj_fetch_summary($conn, array $filters): array
{
    $w = lpj_build_where($filters, 'inv');
    $sql = "
        SELECT
            COUNT(*) AS jumlah_transaksi,
            COALESCE(SUM(inv.invoice_sub_total), 0) AS total_penjualan,
            COALESCE(SUM(inv.invoice_diskon), 0) AS total_diskon,
            COALESCE(SUM(inv.invoice_ongkir), 0) AS total_ongkir,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang < 1 THEN inv.invoice_sub_total ELSE 0 END), 0) AS total_lunas,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang >= 1 THEN inv.invoice_sub_total ELSE 0 END), 0) AS total_piutang,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang >= 1 AND inv.invoice_piutang_lunas < 1
                THEN GREATEST(inv.invoice_sub_total - inv.invoice_bayar, 0) ELSE 0 END), 0) AS sisa_piutang,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang < 1 THEN 1 ELSE 0 END), 0) AS trx_lunas,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang >= 1 AND inv.invoice_piutang_lunas < 1 THEN 1 ELSE 0 END), 0) AS trx_piutang,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang >= 1 AND inv.invoice_piutang_lunas >= 1 THEN 1 ELSE 0 END), 0) AS trx_piutang_lunas,
            COALESCE(SUM(CASE WHEN inv.invoice_tipe_transaksi < 1 THEN inv.invoice_sub_total ELSE 0 END), 0) AS total_tunai,
            COALESCE(SUM(CASE WHEN inv.invoice_tipe_transaksi >= 1 THEN inv.invoice_sub_total ELSE 0 END), 0) AS total_transfer
        FROM invoice inv
        WHERE {$w['where']}
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    lpj_bind_params($stmt, $w['types'], $w['params']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];

    $wDetail = lpj_build_where($filters, 'inv');
    $sqlDetail = "
        SELECT
            COUNT(DISTINCT p.barang_id) AS jumlah_produk,
            COALESCE(SUM(p.barang_qty), 0) AS total_qty,
            COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) AS total_nilai_item,
            COALESCE(SUM(p.barang_qty * p.keranjang_harga - p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS total_laba_kotor
        FROM penjualan p
        INNER JOIN invoice inv ON p.penjualan_invoice = inv.penjualan_invoice
            AND p.penjualan_cabang = inv.invoice_cabang
        WHERE {$wDetail['where']}
    ";
    $stmt2 = $conn->prepare($sqlDetail);
    if ($stmt2) {
        lpj_bind_params($stmt2, $wDetail['types'], $wDetail['params']);
        $stmt2->execute();
        $detail = $stmt2->get_result()->fetch_assoc() ?: [];
        $row = array_merge($row, $detail);
    }

    return [
        'jumlah_transaksi' => (int) ($row['jumlah_transaksi'] ?? 0),
        'total_penjualan' => (float) ($row['total_penjualan'] ?? 0),
        'total_diskon' => (float) ($row['total_diskon'] ?? 0),
        'total_ongkir' => (float) ($row['total_ongkir'] ?? 0),
        'total_lunas' => (float) ($row['total_lunas'] ?? 0),
        'total_piutang' => (float) ($row['total_piutang'] ?? 0),
        'sisa_piutang' => (float) ($row['sisa_piutang'] ?? 0),
        'trx_lunas' => (int) ($row['trx_lunas'] ?? 0),
        'trx_piutang' => (int) ($row['trx_piutang'] ?? 0),
        'trx_piutang_lunas' => (int) ($row['trx_piutang_lunas'] ?? 0),
        'total_tunai' => (float) ($row['total_tunai'] ?? 0),
        'total_transfer' => (float) ($row['total_transfer'] ?? 0),
        'jumlah_produk' => (int) ($row['jumlah_produk'] ?? 0),
        'total_qty' => (float) ($row['total_qty'] ?? 0),
        'total_nilai_item' => (float) ($row['total_nilai_item'] ?? 0),
        'total_laba_kotor' => (float) ($row['total_laba_kotor'] ?? 0),
    ];
}

function lpj_fetch_transaksi($conn, array $filters): array
{
    $w = lpj_build_where($filters, 'inv');
    $sql = "
        SELECT
            inv.invoice_id,
            inv.penjualan_invoice,
            inv.invoice_tgl,
            inv.invoice_date,
            inv.invoice_sub_total,
            inv.invoice_total,
            inv.invoice_diskon,
            inv.invoice_ongkir,
            inv.invoice_bayar,
            inv.invoice_kembali,
            inv.invoice_piutang,
            inv.invoice_piutang_dp,
            inv.invoice_piutang_jatuh_tempo,
            inv.invoice_piutang_lunas,
            inv.invoice_tipe_transaksi,
            inv.invoice_customer,
            inv.invoice_kasir,
            inv.invoice_marketplace,
            c.customer_nama,
            c.customer_category,
            u.user_nama AS kasir_nama,
            (SELECT COUNT(*) FROM penjualan p
             WHERE p.penjualan_invoice = inv.penjualan_invoice
               AND p.penjualan_cabang = inv.invoice_cabang) AS jumlah_item,
            (SELECT COALESCE(SUM(p.barang_qty), 0) FROM penjualan p
             WHERE p.penjualan_invoice = inv.penjualan_invoice
               AND p.penjualan_cabang = inv.invoice_cabang) AS total_qty
        FROM invoice inv
        LEFT JOIN customer c ON inv.invoice_customer = c.customer_id
        LEFT JOIN user u ON inv.invoice_kasir = u.user_id
        WHERE {$w['where']}
        ORDER BY inv.invoice_date DESC, inv.invoice_id DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    lpj_bind_params($stmt, $w['types'], $w['params']);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    $no = 1;
    while ($r = $res->fetch_assoc()) {
        $status = lpj_status_bayar_label($r);
        $sisaPiutang = 0;
        if ((int) ($r['invoice_piutang'] ?? 0) >= 1 && (int) ($r['invoice_piutang_lunas'] ?? 0) < 1) {
            $sisaPiutang = max(0, (float) ($r['invoice_sub_total'] ?? 0) - (float) ($r['invoice_bayar'] ?? 0));
        }
        $rows[] = [
            'no' => $no++,
            'invoice_id' => (int) $r['invoice_id'],
            'penjualan_invoice' => $r['penjualan_invoice'],
            'invoice_tgl' => $r['invoice_tgl'],
            'invoice_date' => $r['invoice_date'],
            'customer_nama' => $r['customer_nama'] ?? '-',
            'customer_category' => (int) ($r['customer_category'] ?? 0),
            'kasir_nama' => lpj_kasir_nama($r),
            'jumlah_item' => (int) ($r['jumlah_item'] ?? 0),
            'total_qty' => (float) ($r['total_qty'] ?? 0),
            'invoice_sub_total' => (float) ($r['invoice_sub_total'] ?? 0),
            'invoice_diskon' => (float) ($r['invoice_diskon'] ?? 0),
            'invoice_ongkir' => (float) ($r['invoice_ongkir'] ?? 0),
            'invoice_bayar' => (float) ($r['invoice_bayar'] ?? 0),
            'invoice_kembali' => (float) ($r['invoice_kembali'] ?? 0),
            'sisa_piutang' => $sisaPiutang,
            'invoice_piutang_jatuh_tempo' => $r['invoice_piutang_jatuh_tempo'] ?? '',
            'status_bayar' => $status,
            'status_badge' => lpj_status_bayar_badge($status),
            'metode_bayar' => lpj_metode_bayar_label($r),
            'invoice_marketplace' => $r['invoice_marketplace'] ?? '',
        ];
    }
    return $rows;
}

function lpj_fetch_detail_item($conn, array $filters): array
{
    $w = lpj_build_where($filters, 'inv');
    $sql = "
        SELECT
            p.penjualan_id,
            p.barang_id,
            b.barang_kode,
            b.barang_nama,
            k.kategori_nama,
            sat.satuan_nama,
            p.barang_qty,
            p.keranjang_harga,
            p.keranjang_harga_beli,
            p.barang_qty_keranjang,
            (p.barang_qty * p.keranjang_harga) AS subtotal,
            (p.barang_qty * p.keranjang_harga - p.barang_qty_keranjang * p.keranjang_harga_beli) AS laba_kotor,
            inv.penjualan_invoice,
            inv.invoice_tgl,
            inv.invoice_date,
            inv.invoice_piutang,
            inv.invoice_piutang_lunas,
            inv.invoice_tipe_transaksi,
            inv.invoice_marketplace,
            c.customer_nama,
            u.user_nama AS kasir_nama
        FROM penjualan p
        INNER JOIN invoice inv ON p.penjualan_invoice = inv.penjualan_invoice
            AND p.penjualan_cabang = inv.invoice_cabang
        LEFT JOIN barang b ON p.barang_id = b.barang_id
        LEFT JOIN kategori k ON b.kategori_id = k.kategori_id
        LEFT JOIN satuan sat ON b.barang_satuan_id = sat.satuan_id AND sat.satuan_cabang = 0
        LEFT JOIN customer c ON inv.invoice_customer = c.customer_id
        LEFT JOIN user u ON inv.invoice_kasir = u.user_id
        WHERE {$w['where']}
        ORDER BY inv.invoice_date DESC, inv.penjualan_invoice, b.barang_nama
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    lpj_bind_params($stmt, $w['types'], $w['params']);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    $no = 1;
    while ($r = $res->fetch_assoc()) {
        $status = lpj_status_bayar_label($r);
        $rows[] = [
            'no' => $no++,
            'penjualan_id' => (int) $r['penjualan_id'],
            'barang_kode' => $r['barang_kode'] ?? '',
            'barang_nama' => $r['barang_nama'] ?? '-',
            'kategori_nama' => $r['kategori_nama'] ?? '-',
            'satuan_nama' => $r['satuan_nama'] ?? '-',
            'barang_qty' => (float) ($r['barang_qty'] ?? 0),
            'keranjang_harga' => (float) ($r['keranjang_harga'] ?? 0),
            'subtotal' => (float) ($r['subtotal'] ?? 0),
            'laba_kotor' => (float) ($r['laba_kotor'] ?? 0),
            'penjualan_invoice' => $r['penjualan_invoice'],
            'invoice_tgl' => $r['invoice_tgl'],
            'customer_nama' => $r['customer_nama'] ?? '-',
            'kasir_nama' => lpj_kasir_nama($r),
            'status_bayar' => $status,
            'metode_bayar' => lpj_metode_bayar_label($r),
        ];
    }
    return $rows;
}

function lpj_fetch_per_customer($conn, array $filters): array
{
    $w = lpj_build_where($filters, 'inv');
    $sql = "
        SELECT
            c.customer_id,
            c.customer_nama,
            c.customer_category,
            COUNT(DISTINCT inv.invoice_id) AS jumlah_transaksi,
            COALESCE(SUM(inv.invoice_sub_total), 0) AS total_penjualan,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang < 1 THEN inv.invoice_sub_total ELSE 0 END), 0) AS total_lunas,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang >= 1 THEN inv.invoice_sub_total ELSE 0 END), 0) AS total_piutang,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang >= 1 AND inv.invoice_piutang_lunas < 1
                THEN GREATEST(inv.invoice_sub_total - inv.invoice_bayar, 0) ELSE 0 END), 0) AS sisa_piutang,
            (SELECT COALESCE(SUM(p.barang_qty), 0)
             FROM penjualan p
             INNER JOIN invoice inv2 ON p.penjualan_invoice = inv2.penjualan_invoice
                 AND p.penjualan_cabang = inv2.invoice_cabang
             WHERE inv2.invoice_customer = c.customer_id
               AND inv2.invoice_date BETWEEN ? AND ?
               AND inv2.invoice_cabang = ?
               AND inv2.invoice_draft = 0
            ) AS total_qty
        FROM invoice inv
        LEFT JOIN customer c ON inv.invoice_customer = c.customer_id
        WHERE {$w['where']}
        GROUP BY c.customer_id, c.customer_nama, c.customer_category
        ORDER BY total_penjualan DESC, c.customer_nama
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $extraTypes = 'ssi';
    $extraParams = [$filters['dari'], $filters['sampai'], $filters['cabang']];
    lpj_bind_params($stmt, $w['types'] . $extraTypes, array_merge($w['params'], $extraParams));
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    $no = 1;
    while ($r = $res->fetch_assoc()) {
        $catLabel = '';
        $cat = (int) ($r['customer_category'] ?? 0);
        if ($cat === 1) {
            $catLabel = ' (Retail)';
        } elseif ($cat === 2) {
            $catLabel = ' (Grosir)';
        }
        $rows[] = [
            'no' => $no++,
            'customer_id' => (int) ($r['customer_id'] ?? 0),
            'customer_nama' => ($r['customer_nama'] ?? '-') . $catLabel,
            'jumlah_transaksi' => (int) ($r['jumlah_transaksi'] ?? 0),
            'total_qty' => (float) ($r['total_qty'] ?? 0),
            'total_penjualan' => (float) ($r['total_penjualan'] ?? 0),
            'total_lunas' => (float) ($r['total_lunas'] ?? 0),
            'total_piutang' => (float) ($r['total_piutang'] ?? 0),
            'sisa_piutang' => (float) ($r['sisa_piutang'] ?? 0),
        ];
    }
    return $rows;
}

function lpj_json_out(array $payload): void
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
