<?php
/**
 * Library laporan pembelian (standar POS retail).
 */

function lp_sanitize_date(string $s, string $fallback): string
{
    $s = trim($s);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) !== 1) {
        return $fallback;
    }
    return $s;
}

function lp_parse_periode($dariRaw, $sampaiRaw): array
{
    $today = date('Y-m-d');
    $defaultDari = date('Y-m-01');
    $dari = lp_sanitize_date((string) $dariRaw, $defaultDari);
    $sampai = lp_sanitize_date((string) $sampaiRaw, $today);
    if (strtotime($dari) > strtotime($sampai)) {
        $tmp = $dari;
        $dari = $sampai;
        $sampai = $tmp;
    }
    return ['dari' => $dari, 'sampai' => $sampai];
}

function lp_get_toko($conn, int $cabang): array
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
 * @return array{dari:string,sampai:string,cabang:int,supplier_id:int,kasir_id:int,status_bayar:string}
 */
function lp_parse_filters($conn, array $input, int $sessionCabang, string $levelLogin): array
{
    $periode = lp_parse_periode($input['dari'] ?? '', $input['sampai'] ?? '');
    $cabang = $sessionCabang;
    if ($levelLogin === 'super admin' && isset($input['cabang']) && $input['cabang'] !== '' && $input['cabang'] !== 'semua') {
        $cabang = (int) $input['cabang'];
    }

    return [
        'dari' => $periode['dari'],
        'sampai' => $periode['sampai'],
        'cabang' => $cabang,
        'supplier_id' => (int) ($input['supplier_id'] ?? 0),
        'kasir_id' => (int) ($input['kasir_id'] ?? 0),
        'status_bayar' => trim((string) ($input['status_bayar'] ?? '')),
    ];
}

function lp_status_bayar_label(array $row): string
{
    $hutang = (int) ($row['invoice_hutang'] ?? 0);
    $lunas = (int) ($row['invoice_hutang_lunas'] ?? 0);
    if ($hutang < 1) {
        return 'Cash';
    }
    if ($lunas >= 1) {
        return 'Hutang Lunas';
    }
    return 'Hutang';
}

function lp_status_bayar_badge(string $label): string
{
    if ($label === 'Cash') {
        return 'success';
    }
    if ($label === 'Hutang Lunas') {
        return 'info';
    }
    return 'warning';
}

function lp_format_rupiah($n): string
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function lp_format_qty($n): string
{
    $v = (float) $n;
    if (abs($v - round($v)) < 0.0001) {
        return number_format($v, 0, ',', '.');
    }
    return number_format($v, 2, ',', '.');
}

/**
 * WHERE clause ter-escape untuk mysqli_query (tanpa get_result / mysqlnd).
 */
function lp_where_sql($conn, array $filters, string $alias = 'ip'): string
{
    $dari = mysqli_real_escape_string($conn, (string) $filters['dari']);
    $sampai = mysqli_real_escape_string($conn, (string) $filters['sampai']);
    $cabang = (int) $filters['cabang'];

    $conds = [
        "{$alias}.invoice_date BETWEEN '{$dari}' AND '{$sampai}'",
        "{$alias}.invoice_pembelian_cabang = {$cabang}",
    ];

    if ((int) ($filters['supplier_id'] ?? 0) > 0) {
        $conds[] = "{$alias}.invoice_supplier = " . (int) $filters['supplier_id'];
    }
    if ((int) ($filters['kasir_id'] ?? 0) > 0) {
        $conds[] = "{$alias}.invoice_kasir = " . (int) $filters['kasir_id'];
    }

    $status = (string) ($filters['status_bayar'] ?? '');
    if ($status === 'cash') {
        $conds[] = "{$alias}.invoice_hutang < 1";
    } elseif ($status === 'hutang') {
        $conds[] = "{$alias}.invoice_hutang >= 1 AND {$alias}.invoice_hutang_lunas < 1";
    } elseif ($status === 'hutang_lunas') {
        $conds[] = "{$alias}.invoice_hutang >= 1 AND {$alias}.invoice_hutang_lunas >= 1";
    }

    return implode(' AND ', $conds);
}

function lp_query($conn, string $sql, string $label = 'Query')
{
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        throw new RuntimeException($label . ' gagal: ' . mysqli_error($conn));
    }
    return $res;
}

function lp_fetch_summary($conn, array $filters): array
{
    $where = lp_where_sql($conn, $filters, 'ip');
    $sql = "
        SELECT
            COUNT(*) AS jumlah_transaksi,
            COALESCE(SUM(ip.invoice_total), 0) AS total_pembelian,
            COALESCE(SUM(CASE WHEN ip.invoice_hutang < 1 THEN ip.invoice_total ELSE 0 END), 0) AS total_cash,
            COALESCE(SUM(CASE WHEN ip.invoice_hutang >= 1 THEN ip.invoice_total ELSE 0 END), 0) AS total_hutang,
            COALESCE(SUM(CASE WHEN ip.invoice_hutang >= 1 AND ip.invoice_hutang_lunas < 1 THEN ip.invoice_total - ip.invoice_bayar ELSE 0 END), 0) AS sisa_hutang,
            COALESCE(SUM(CASE WHEN ip.invoice_hutang < 1 THEN 1 ELSE 0 END), 0) AS trx_cash,
            COALESCE(SUM(CASE WHEN ip.invoice_hutang >= 1 AND ip.invoice_hutang_lunas < 1 THEN 1 ELSE 0 END), 0) AS trx_hutang,
            COALESCE(SUM(CASE WHEN ip.invoice_hutang >= 1 AND ip.invoice_hutang_lunas >= 1 THEN 1 ELSE 0 END), 0) AS trx_hutang_lunas
        FROM invoice_pembelian ip
        WHERE {$where}
    ";
    $row = mysqli_fetch_assoc(lp_query($conn, $sql, 'Summary pembelian')) ?: [];

    $sqlDetail = "
        SELECT
            COUNT(DISTINCT p.barang_id) AS jumlah_produk,
            COALESCE(SUM(p.barang_qty), 0) AS total_qty,
            COALESCE(SUM(p.barang_qty * p.barang_harga_beli), 0) AS total_nilai_item
        FROM pembelian p
        INNER JOIN invoice_pembelian ip ON p.pembelian_invoice_parent = ip.pembelian_invoice_parent
            AND p.pembelian_cabang = ip.invoice_pembelian_cabang
        WHERE {$where}
    ";
    $detailRes = @mysqli_query($conn, $sqlDetail);
    if ($detailRes) {
        $detail = mysqli_fetch_assoc($detailRes) ?: [];
        $row = array_merge($row, $detail);
    }

    return [
        'jumlah_transaksi' => (int) ($row['jumlah_transaksi'] ?? 0),
        'total_pembelian' => (float) ($row['total_pembelian'] ?? 0),
        'total_cash' => (float) ($row['total_cash'] ?? 0),
        'total_hutang' => (float) ($row['total_hutang'] ?? 0),
        'sisa_hutang' => (float) ($row['sisa_hutang'] ?? 0),
        'trx_cash' => (int) ($row['trx_cash'] ?? 0),
        'trx_hutang' => (int) ($row['trx_hutang'] ?? 0),
        'trx_hutang_lunas' => (int) ($row['trx_hutang_lunas'] ?? 0),
        'jumlah_produk' => (int) ($row['jumlah_produk'] ?? 0),
        'total_qty' => (float) ($row['total_qty'] ?? 0),
        'total_nilai_item' => (float) ($row['total_nilai_item'] ?? 0),
    ];
}

function lp_fetch_transaksi($conn, array $filters): array
{
    $where = lp_where_sql($conn, $filters, 'ip');
    $sql = "
        SELECT
            ip.invoice_pembelian_id,
            ip.pembelian_invoice,
            ip.pembelian_invoice_parent,
            ip.invoice_tgl,
            ip.invoice_date,
            ip.invoice_total,
            ip.invoice_bayar,
            ip.invoice_kembali,
            ip.invoice_hutang,
            ip.invoice_hutang_dp,
            ip.invoice_hutang_jatuh_tempo,
            ip.invoice_hutang_lunas,
            ip.invoice_supplier,
            ip.invoice_kasir,
            s.supplier_nama,
            s.supplier_company,
            u.user_nama AS kasir_nama,
            (SELECT COUNT(*) FROM pembelian p
             WHERE p.pembelian_invoice_parent = ip.pembelian_invoice_parent
               AND p.pembelian_cabang = ip.invoice_pembelian_cabang) AS jumlah_item,
            (SELECT COALESCE(SUM(p.barang_qty), 0) FROM pembelian p
             WHERE p.pembelian_invoice_parent = ip.pembelian_invoice_parent
               AND p.pembelian_cabang = ip.invoice_pembelian_cabang) AS total_qty
        FROM invoice_pembelian ip
        LEFT JOIN supplier s ON ip.invoice_supplier = s.supplier_id
        LEFT JOIN `user` u ON ip.invoice_kasir = u.user_id
        WHERE {$where}
        ORDER BY ip.invoice_date DESC, ip.invoice_pembelian_id DESC
        LIMIT 2000
    ";
    $res = lp_query($conn, $sql, 'Transaksi pembelian');
    $rows = [];
    $no = 1;
    while ($r = mysqli_fetch_assoc($res)) {
        $status = lp_status_bayar_label($r);
        $rows[] = [
            'no' => $no++,
            'invoice_pembelian_id' => (int) $r['invoice_pembelian_id'],
            'pembelian_invoice' => $r['pembelian_invoice'],
            'invoice_tgl' => $r['invoice_tgl'],
            'invoice_date' => $r['invoice_date'],
            'supplier_nama' => $r['supplier_nama'] ?? '-',
            'supplier_company' => $r['supplier_company'] ?? '',
            'supplier_label' => trim(($r['supplier_nama'] ?? '') . ($r['supplier_company'] ? ' — ' . $r['supplier_company'] : '')),
            'kasir_nama' => $r['kasir_nama'] ?? '-',
            'jumlah_item' => (int) ($r['jumlah_item'] ?? 0),
            'total_qty' => (float) ($r['total_qty'] ?? 0),
            'invoice_total' => (float) ($r['invoice_total'] ?? 0),
            'invoice_bayar' => (float) ($r['invoice_bayar'] ?? 0),
            'invoice_kembali' => (float) ($r['invoice_kembali'] ?? 0),
            'sisa_hutang' => max(0, (float) ($r['invoice_total'] ?? 0) - (float) ($r['invoice_bayar'] ?? 0)),
            'invoice_hutang_jatuh_tempo' => $r['invoice_hutang_jatuh_tempo'] ?? '',
            'status_bayar' => $status,
            'status_badge' => lp_status_bayar_badge($status),
        ];
    }
    return $rows;
}

function lp_fetch_detail_item($conn, array $filters): array
{
    $where = lp_where_sql($conn, $filters, 'ip');
    $sql = "
        SELECT
            p.pembelian_id,
            p.barang_id,
            b.barang_kode,
            b.barang_nama,
            k.kategori_nama,
            sat.satuan_nama,
            p.barang_qty,
            p.barang_harga_beli,
            (p.barang_qty * p.barang_harga_beli) AS subtotal,
            ip.pembelian_invoice,
            ip.invoice_tgl,
            ip.invoice_date,
            ip.invoice_hutang,
            ip.invoice_hutang_lunas,
            s.supplier_nama,
            s.supplier_company,
            u.user_nama AS kasir_nama
        FROM pembelian p
        INNER JOIN invoice_pembelian ip ON p.pembelian_invoice_parent = ip.pembelian_invoice_parent
            AND p.pembelian_cabang = ip.invoice_pembelian_cabang
        LEFT JOIN barang b ON p.barang_id = b.barang_id
        LEFT JOIN (
            SELECT kategori_id, MAX(kategori_nama) AS kategori_nama
            FROM kategori
            GROUP BY kategori_id
        ) k ON b.kategori_id = k.kategori_id
        LEFT JOIN satuan sat ON b.barang_satuan_id = sat.satuan_id AND sat.satuan_cabang = 0
        LEFT JOIN supplier s ON ip.invoice_supplier = s.supplier_id
        LEFT JOIN `user` u ON ip.invoice_kasir = u.user_id
        WHERE {$where}
        ORDER BY ip.invoice_date DESC, ip.pembelian_invoice, b.barang_nama
        LIMIT 5000
    ";
    $res = lp_query($conn, $sql, 'Detail item pembelian');
    $rows = [];
    $no = 1;
    while ($r = mysqli_fetch_assoc($res)) {
        $status = lp_status_bayar_label($r);
        $rows[] = [
            'no' => $no++,
            'pembelian_id' => (int) $r['pembelian_id'],
            'barang_kode' => $r['barang_kode'] ?? '',
            'barang_nama' => $r['barang_nama'] ?? '-',
            'kategori_nama' => $r['kategori_nama'] ?? '-',
            'satuan_nama' => $r['satuan_nama'] ?? '-',
            'barang_qty' => (float) ($r['barang_qty'] ?? 0),
            'barang_harga_beli' => (float) ($r['barang_harga_beli'] ?? 0),
            'subtotal' => (float) ($r['subtotal'] ?? 0),
            'pembelian_invoice' => $r['pembelian_invoice'],
            'invoice_tgl' => $r['invoice_tgl'],
            'supplier_label' => trim(($r['supplier_nama'] ?? '') . ($r['supplier_company'] ? ' — ' . $r['supplier_company'] : '')),
            'kasir_nama' => $r['kasir_nama'] ?? '-',
            'status_bayar' => $status,
        ];
    }
    return $rows;
}

/**
 * Rekap pembelian per barang (agregat).
 */
function lp_fetch_per_barang($conn, array $filters): array
{
    $where = lp_where_sql($conn, $filters, 'ip');
    $sql = "
        SELECT
            p.barang_id,
            b.barang_kode,
            b.barang_nama,
            k.kategori_nama,
            sat.satuan_nama,
            COUNT(DISTINCT ip.invoice_pembelian_id) AS jumlah_transaksi,
            COALESCE(SUM(p.barang_qty), 0) AS total_qty,
            COALESCE(AVG(p.barang_harga_beli), 0) AS harga_beli_avg,
            COALESCE(SUM(p.barang_qty * p.barang_harga_beli), 0) AS total_pembelian
        FROM pembelian p
        INNER JOIN invoice_pembelian ip ON p.pembelian_invoice_parent = ip.pembelian_invoice_parent
            AND p.pembelian_cabang = ip.invoice_pembelian_cabang
        LEFT JOIN barang b ON p.barang_id = b.barang_id
        LEFT JOIN (
            SELECT kategori_id, MAX(kategori_nama) AS kategori_nama
            FROM kategori
            GROUP BY kategori_id
        ) k ON b.kategori_id = k.kategori_id
        LEFT JOIN satuan sat ON b.barang_satuan_id = sat.satuan_id AND sat.satuan_cabang = 0
        WHERE {$where}
        GROUP BY p.barang_id, b.barang_kode, b.barang_nama, k.kategori_nama, sat.satuan_nama
        ORDER BY total_pembelian DESC, b.barang_nama ASC
        LIMIT 1000
    ";
    $res = lp_query($conn, $sql, 'Rekap barang pembelian');
    $rows = [];
    $no = 1;
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = [
            'no' => $no++,
            'barang_id' => (int) ($r['barang_id'] ?? 0),
            'barang_kode' => $r['barang_kode'] ?? '',
            'barang_nama' => $r['barang_nama'] ?? '-',
            'kategori_nama' => $r['kategori_nama'] ?? '-',
            'satuan_nama' => $r['satuan_nama'] ?? '-',
            'jumlah_transaksi' => (int) ($r['jumlah_transaksi'] ?? 0),
            'total_qty' => (float) ($r['total_qty'] ?? 0),
            'harga_beli_avg' => (float) ($r['harga_beli_avg'] ?? 0),
            'total_pembelian' => (float) ($r['total_pembelian'] ?? 0),
        ];
    }
    return $rows;
}

function lp_fetch_per_supplier($conn, array $filters): array
{
    $where = lp_where_sql($conn, $filters, 'ip');
    $dari = mysqli_real_escape_string($conn, (string) $filters['dari']);
    $sampai = mysqli_real_escape_string($conn, (string) $filters['sampai']);
    $cabang = (int) $filters['cabang'];
    $sql = "
        SELECT
            s.supplier_id,
            s.supplier_nama,
            s.supplier_company,
            COUNT(DISTINCT ip.invoice_pembelian_id) AS jumlah_transaksi,
            COALESCE(SUM(ip.invoice_total), 0) AS total_pembelian,
            COALESCE(SUM(CASE WHEN ip.invoice_hutang < 1 THEN ip.invoice_total ELSE 0 END), 0) AS total_cash,
            COALESCE(SUM(CASE WHEN ip.invoice_hutang >= 1 THEN ip.invoice_total ELSE 0 END), 0) AS total_hutang,
            COALESCE(SUM(CASE WHEN ip.invoice_hutang >= 1 AND ip.invoice_hutang_lunas < 1
                THEN ip.invoice_total - ip.invoice_bayar ELSE 0 END), 0) AS sisa_hutang,
            (SELECT COALESCE(SUM(p.barang_qty), 0)
             FROM pembelian p
             INNER JOIN invoice_pembelian ip2 ON p.pembelian_invoice_parent = ip2.pembelian_invoice_parent
                 AND p.pembelian_cabang = ip2.invoice_pembelian_cabang
             WHERE ip2.invoice_supplier = s.supplier_id
               AND ip2.invoice_date BETWEEN '{$dari}' AND '{$sampai}'
               AND ip2.invoice_pembelian_cabang = {$cabang}
            ) AS total_qty
        FROM invoice_pembelian ip
        LEFT JOIN supplier s ON ip.invoice_supplier = s.supplier_id
        WHERE {$where}
        GROUP BY s.supplier_id, s.supplier_nama, s.supplier_company
        ORDER BY total_pembelian DESC, s.supplier_nama
        LIMIT 500
    ";
    $res = lp_query($conn, $sql, 'Rekap supplier pembelian');
    $rows = [];
    $no = 1;
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = [
            'no' => $no++,
            'supplier_id' => (int) ($r['supplier_id'] ?? 0),
            'supplier_nama' => $r['supplier_nama'] ?? '-',
            'supplier_company' => $r['supplier_company'] ?? '',
            'supplier_label' => trim(($r['supplier_nama'] ?? '-') . ($r['supplier_company'] ? ' — ' . $r['supplier_company'] : '')),
            'jumlah_transaksi' => (int) ($r['jumlah_transaksi'] ?? 0),
            'total_qty' => (float) ($r['total_qty'] ?? 0),
            'total_pembelian' => (float) ($r['total_pembelian'] ?? 0),
            'total_cash' => (float) ($r['total_cash'] ?? 0),
            'total_hutang' => (float) ($r['total_hutang'] ?? 0),
            'sisa_hutang' => (float) ($r['sisa_hutang'] ?? 0),
        ];
    }
    return $rows;
}

function lp_json_out(array $payload): void
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
