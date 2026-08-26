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

/** Margin keuntungan (%) terhadap modal/HPP. */
function lpj_margin_persen(float $laba, float $modal): float
{
    if ($modal <= 0) {
        return 0.0;
    }
    return round(($laba / $modal) * 100, 1);
}

function lpj_invoice_draft_cond($conn, string $alias = 'inv'): string
{
    static $hasDraft = null;
    if ($hasDraft === null) {
        $res = @mysqli_query($conn, "SHOW COLUMNS FROM invoice LIKE 'invoice_draft'");
        $hasDraft = ($res && mysqli_num_rows($res) > 0);
    }
    return $hasDraft ? "$alias.invoice_draft = 0" : '1=1';
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
    global $conn;
    $draftCond = lpj_invoice_draft_cond($conn, $alias);
    $conds = [
        "$alias.invoice_date BETWEEN ? AND ?",
        "$alias.invoice_cabang = ?",
        $draftCond,
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

/**
 * Bangun klausa WHERE dengan nilai sudah di-escape (tanpa prepared statement),
 * agar kompatibel Hostinger tanpa mysqlnd / tanpa mysqli_stmt::get_result().
 */
function lpj_where_sql($conn, array $filters, string $alias = 'inv'): string
{
    $draftCond = lpj_invoice_draft_cond($conn, $alias);
    $dari = mysqli_real_escape_string($conn, (string) $filters['dari']);
    $sampai = mysqli_real_escape_string($conn, (string) $filters['sampai']);
    $cabang = (int) $filters['cabang'];

    $conds = [
        "{$alias}.invoice_date BETWEEN '{$dari}' AND '{$sampai}'",
        "{$alias}.invoice_cabang = {$cabang}",
        $draftCond,
    ];

    if ((int) $filters['customer_id'] > 0) {
        $conds[] = "{$alias}.invoice_customer = " . (int) $filters['customer_id'];
    }
    if ((int) $filters['kasir_id'] > 0) {
        $conds[] = "{$alias}.invoice_kasir = " . (int) $filters['kasir_id'];
    }

    $status = (string) ($filters['status_bayar'] ?? '');
    if ($status === 'lunas') {
        $conds[] = "{$alias}.invoice_piutang < 1";
    } elseif ($status === 'piutang') {
        $conds[] = "{$alias}.invoice_piutang >= 1 AND {$alias}.invoice_piutang_lunas < 1";
    } elseif ($status === 'piutang_lunas') {
        $conds[] = "{$alias}.invoice_piutang >= 1 AND {$alias}.invoice_piutang_lunas >= 1";
    }

    $metode = (string) ($filters['metode_bayar'] ?? '');
    if ($metode === 'tunai') {
        $conds[] = "{$alias}.invoice_tipe_transaksi < 1";
    } elseif ($metode === 'transfer') {
        $conds[] = "{$alias}.invoice_tipe_transaksi >= 1";
    }

    return implode(' AND ', $conds);
}

function lpj_query($conn, string $sql, string $label = 'Query')
{
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        throw new RuntimeException($label . ' gagal: ' . mysqli_error($conn));
    }
    return $res;
}

function lpj_fetch_summary($conn, array $filters, bool $includeItemStats = true): array
{
    $where = lpj_where_sql($conn, $filters, 'inv');
    $sql = "
        SELECT
            COUNT(*) AS jumlah_transaksi,
            COALESCE(SUM(inv.invoice_sub_total), 0) AS total_penjualan,
            COALESCE(SUM(inv.invoice_diskon), 0) AS total_diskon,
            COALESCE(SUM(inv.invoice_ongkir), 0) AS total_ongkir,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang < 1 THEN inv.invoice_sub_total ELSE 0 END), 0) AS total_lunas,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang >= 1 THEN inv.invoice_sub_total ELSE 0 END), 0) AS total_piutang,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang >= 1 AND inv.invoice_piutang_lunas < 1
                THEN IF(inv.invoice_sub_total > inv.invoice_bayar, inv.invoice_sub_total - inv.invoice_bayar, 0) ELSE 0 END), 0) AS sisa_piutang,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang < 1 THEN 1 ELSE 0 END), 0) AS trx_lunas,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang >= 1 AND inv.invoice_piutang_lunas < 1 THEN 1 ELSE 0 END), 0) AS trx_piutang,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang >= 1 AND inv.invoice_piutang_lunas >= 1 THEN 1 ELSE 0 END), 0) AS trx_piutang_lunas,
            COALESCE(SUM(CASE WHEN inv.invoice_tipe_transaksi < 1 THEN inv.invoice_sub_total ELSE 0 END), 0) AS total_tunai,
            COALESCE(SUM(CASE WHEN inv.invoice_tipe_transaksi >= 1 THEN inv.invoice_sub_total ELSE 0 END), 0) AS total_transfer
        FROM invoice inv
        WHERE {$where}
    ";
    $row = mysqli_fetch_assoc(lpj_query($conn, $sql, 'Summary')) ?: [];

    if ($includeItemStats) {
        $sqlDetail = "
            SELECT
                COUNT(DISTINCT p.barang_id) AS jumlah_produk,
                COALESCE(SUM(p.barang_qty), 0) AS total_qty,
                COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) AS total_nilai_item,
                COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS total_modal,
                COALESCE(SUM(p.barang_qty * p.keranjang_harga - p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS total_laba_kotor
            FROM invoice inv
            INNER JOIN penjualan p
                ON p.penjualan_invoice = inv.penjualan_invoice
               AND p.penjualan_cabang = inv.invoice_cabang
            WHERE {$where}
        ";
        $detailRes = @mysqli_query($conn, $sqlDetail);
        if ($detailRes) {
            $detail = mysqli_fetch_assoc($detailRes) ?: [];
            $row = array_merge($row, $detail);
        }
    } else {
        $row['jumlah_produk'] = 0;
        $row['total_qty'] = 0;
        $row['total_nilai_item'] = 0;
        $row['total_modal'] = 0;
        $row['total_laba_kotor'] = 0;
    }

    $totalModal = (float) ($row['total_modal'] ?? 0);
    $totalLaba = (float) ($row['total_laba_kotor'] ?? 0);

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
        'total_modal' => $totalModal,
        'total_laba_kotor' => $totalLaba,
        'margin_persen' => lpj_margin_persen($totalLaba, $totalModal),
    ];
}

/**
 * Hitung item/qty hanya untuk daftar invoice yang sudah ditampilkan (jauh lebih cepat
 * daripada JOIN agregasi penjualan ke seluruh periode).
 *
 * @param list<string> $invoiceNos
 * @return array<string, array{jumlah_item:int, total_qty:float}>
 */
function lpj_batch_item_stats($conn, int $cabang, array $invoiceNos): array
{
    $map = [];
    $invoiceNos = array_values(array_unique(array_filter(array_map('strval', $invoiceNos))));
    if ($invoiceNos === []) {
        return $map;
    }

    $cabang = (int) $cabang;
    foreach (array_chunk($invoiceNos, 400) as $chunk) {
        $escaped = [];
        foreach ($chunk as $inv) {
            $escaped[] = "'" . mysqli_real_escape_string($conn, $inv) . "'";
        }
        $inList = implode(',', $escaped);
        $sql = "
            SELECT
                penjualan_invoice,
                COUNT(*) AS jumlah_item,
                COALESCE(SUM(barang_qty), 0) AS total_qty
            FROM penjualan
            WHERE penjualan_cabang = {$cabang}
              AND penjualan_invoice IN ({$inList})
            GROUP BY penjualan_invoice
        ";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            continue;
        }
        while ($r = mysqli_fetch_assoc($res)) {
            $key = (string) ($r['penjualan_invoice'] ?? '');
            if ($key === '') {
                continue;
            }
            $map[$key] = [
                'jumlah_item' => (int) ($r['jumlah_item'] ?? 0),
                'total_qty' => (float) ($r['total_qty'] ?? 0),
            ];
        }
    }

    return $map;
}

function lpj_fetch_transaksi($conn, array $filters): array
{
    // Ultra-ringan: header invoice saja. Item/Qty diisi 0 (lihat detail via zoom / tab Detail).
    $where = lpj_where_sql($conn, $filters, 'inv');
    $sql = "
        SELECT
            inv.invoice_id,
            inv.penjualan_invoice,
            inv.invoice_tgl,
            inv.invoice_date,
            inv.invoice_sub_total,
            inv.invoice_diskon,
            inv.invoice_ongkir,
            inv.invoice_bayar,
            inv.invoice_kembali,
            inv.invoice_piutang,
            inv.invoice_piutang_jatuh_tempo,
            inv.invoice_piutang_lunas,
            inv.invoice_tipe_transaksi,
            inv.invoice_marketplace,
            c.customer_nama,
            c.customer_category,
            u.user_nama AS kasir_nama
        FROM invoice inv
        LEFT JOIN customer c ON inv.invoice_customer = c.customer_id
        LEFT JOIN `user` u ON inv.invoice_kasir = u.user_id
        WHERE {$where}
        ORDER BY inv.invoice_id DESC
        LIMIT 400
    ";
    $res = lpj_query($conn, $sql, 'Transaksi');

    $rows = [];
    $no = 1;
    while ($r = mysqli_fetch_assoc($res)) {
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
            'jumlah_item' => 0,
            'total_qty' => 0.0,
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

function lpj_fetch_detail_item($conn, array $filters, int $maxInvoices = 200, int $maxItems = 1500): array
{
    $maxInvoices = max(50, min(400, $maxInvoices));
    $maxItems = max(100, min(3000, $maxItems));

    // 2 langkah biar aman di Hostinger: header invoice dulu (ringan), baru item by IN().
    $where = lpj_where_sql($conn, $filters, 'inv');
    $sqlInv = "
        SELECT
            inv.invoice_id,
            inv.penjualan_invoice,
            inv.invoice_cabang,
            inv.invoice_tgl,
            inv.invoice_piutang,
            inv.invoice_piutang_lunas,
            inv.invoice_tipe_transaksi,
            inv.invoice_marketplace,
            c.customer_nama,
            u.user_nama AS kasir_nama
        FROM invoice inv
        LEFT JOIN customer c ON inv.invoice_customer = c.customer_id
        LEFT JOIN `user` u ON inv.invoice_kasir = u.user_id
        WHERE {$where}
        ORDER BY inv.invoice_id DESC
        LIMIT {$maxInvoices}
    ";
    $resInv = lpj_query($conn, $sqlInv, 'Detail invoice');
    $byKey = [];
    $invNos = [];
    while ($inv = mysqli_fetch_assoc($resInv)) {
        $no = (string) ($inv['penjualan_invoice'] ?? '');
        if ($no === '') {
            continue;
        }
        $cab = (int) ($inv['invoice_cabang'] ?? 0);
        $byKey[$no . '|' . $cab] = $inv;
        $invNos[$no] = true;
    }
    if ($byKey === []) {
        return [];
    }

    $escaped = [];
    foreach (array_keys($invNos) as $no) {
        $escaped[] = "'" . mysqli_real_escape_string($conn, $no) . "'";
    }
    $inList = implode(',', $escaped);
    $cabang = (int) $filters['cabang'];

    $sqlItems = "
        SELECT
            p.penjualan_id,
            p.penjualan_invoice,
            p.penjualan_cabang,
            p.barang_qty,
            p.barang_qty_keranjang,
            p.keranjang_harga,
            p.keranjang_harga_beli,
            b.barang_kode,
            b.barang_nama,
            k.kategori_nama,
            sat.satuan_nama
        FROM penjualan p
        LEFT JOIN barang b ON p.barang_id = b.barang_id
        LEFT JOIN (
            SELECT kategori_id, MAX(kategori_nama) AS kategori_nama
            FROM kategori
            GROUP BY kategori_id
        ) k ON b.kategori_id = k.kategori_id
        LEFT JOIN satuan sat ON b.barang_satuan_id = sat.satuan_id AND sat.satuan_cabang = 0
        WHERE p.penjualan_cabang = {$cabang}
          AND p.penjualan_invoice IN ({$inList})
        ORDER BY p.penjualan_id DESC
        LIMIT {$maxItems}
    ";
    $res = lpj_query($conn, $sqlItems, 'Detail item');
    $rows = [];
    $no = 1;
    while ($r = mysqli_fetch_assoc($res)) {
        $key = (string) ($r['penjualan_invoice'] ?? '') . '|' . (int) ($r['penjualan_cabang'] ?? 0);
        $inv = $byKey[$key] ?? null;
        if ($inv === null) {
            continue;
        }
        $qty = (float) ($r['barang_qty'] ?? 0);
        $qtyModal = (float) ($r['barang_qty_keranjang'] ?? 0);
        $harga = (float) ($r['keranjang_harga'] ?? 0);
        $hpp = (float) ($r['keranjang_harga_beli'] ?? 0);
        $subtotal = $qty * $harga;
        $modal = $qtyModal * $hpp;
        $laba = $subtotal - $modal;
        $merged = array_merge($inv, $r);
        $rows[] = [
            'no' => $no++,
            'penjualan_id' => (int) $r['penjualan_id'],
            'barang_kode' => $r['barang_kode'] ?? '',
            'barang_nama' => $r['barang_nama'] ?? '-',
            'kategori_nama' => $r['kategori_nama'] ?? '-',
            'satuan_nama' => $r['satuan_nama'] ?? '-',
            'barang_qty' => $qty,
            'keranjang_harga' => $harga,
            'harga_beli' => $hpp,
            'modal' => $modal,
            'subtotal' => $subtotal,
            'laba_kotor' => $laba,
            'margin_persen' => lpj_margin_persen($laba, $modal),
            'penjualan_invoice' => $r['penjualan_invoice'],
            'invoice_tgl' => $inv['invoice_tgl'] ?? '',
            'customer_nama' => $inv['customer_nama'] ?? '-',
            'kasir_nama' => lpj_kasir_nama($merged),
            'status_bayar' => lpj_status_bayar_label($inv),
            'metode_bayar' => lpj_metode_bayar_label($inv),
        ];
    }
    return $rows;
}

/**
 * Rekap penjualan per barang + margin keuntungan (laba / modal × 100).
 */
function lpj_fetch_per_barang($conn, array $filters): array
{
    $where = lpj_where_sql($conn, $filters, 'inv');
    $sql = "
        SELECT
            p.barang_id,
            b.barang_kode,
            b.barang_nama,
            k.kategori_nama,
            sat.satuan_nama,
            COUNT(DISTINCT inv.invoice_id) AS jumlah_transaksi,
            COALESCE(SUM(p.barang_qty), 0) AS total_qty,
            COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) AS total_penjualan,
            COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS total_modal,
            COALESCE(SUM(p.barang_qty * p.keranjang_harga - p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS total_laba,
            COALESCE(AVG(p.keranjang_harga), 0) AS harga_jual_avg,
            COALESCE(AVG(p.keranjang_harga_beli), 0) AS harga_beli_avg
        FROM (
            SELECT inv.invoice_id, inv.penjualan_invoice, inv.invoice_cabang
            FROM invoice inv
            WHERE {$where}
            ORDER BY inv.invoice_id DESC
            LIMIT 400
        ) inv
        INNER JOIN penjualan p
            ON p.penjualan_invoice = inv.penjualan_invoice
           AND p.penjualan_cabang = inv.invoice_cabang
        LEFT JOIN barang b ON p.barang_id = b.barang_id
        LEFT JOIN (
            SELECT kategori_id, MAX(kategori_nama) AS kategori_nama
            FROM kategori
            GROUP BY kategori_id
        ) k ON b.kategori_id = k.kategori_id
        LEFT JOIN satuan sat ON b.barang_satuan_id = sat.satuan_id AND sat.satuan_cabang = 0
        GROUP BY p.barang_id, b.barang_kode, b.barang_nama, k.kategori_nama, sat.satuan_nama
        ORDER BY total_penjualan DESC, b.barang_nama ASC
        LIMIT 300
    ";
    $res = lpj_query($conn, $sql, 'Rekap barang');
    $rows = [];
    $no = 1;
    while ($r = mysqli_fetch_assoc($res)) {
        $modal = (float) ($r['total_modal'] ?? 0);
        $laba = (float) ($r['total_laba'] ?? 0);
        $jual = (float) ($r['total_penjualan'] ?? 0);
        $rows[] = [
            'no' => $no++,
            'barang_id' => (int) ($r['barang_id'] ?? 0),
            'barang_kode' => $r['barang_kode'] ?? '',
            'barang_nama' => $r['barang_nama'] ?? '-',
            'kategori_nama' => $r['kategori_nama'] ?? '-',
            'satuan_nama' => $r['satuan_nama'] ?? '-',
            'jumlah_transaksi' => (int) ($r['jumlah_transaksi'] ?? 0),
            'total_qty' => (float) ($r['total_qty'] ?? 0),
            'harga_jual_avg' => (float) ($r['harga_jual_avg'] ?? 0),
            'harga_beli_avg' => (float) ($r['harga_beli_avg'] ?? 0),
            'total_penjualan' => $jual,
            'total_modal' => $modal,
            'total_laba' => $laba,
            'margin_persen' => lpj_margin_persen($laba, $modal),
        ];
    }
    return $rows;
}

function lpj_fetch_per_customer($conn, array $filters): array
{
    $where = lpj_where_sql($conn, $filters, 'inv');
    $sql = "
        SELECT
            c.customer_id,
            c.customer_nama,
            c.customer_category,
            COUNT(*) AS jumlah_transaksi,
            COALESCE(SUM(inv.invoice_sub_total), 0) AS total_penjualan,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang < 1 THEN inv.invoice_sub_total ELSE 0 END), 0) AS total_lunas,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang >= 1 THEN inv.invoice_sub_total ELSE 0 END), 0) AS total_piutang,
            COALESCE(SUM(CASE WHEN inv.invoice_piutang >= 1 AND inv.invoice_piutang_lunas < 1
                THEN IF(inv.invoice_sub_total > inv.invoice_bayar, inv.invoice_sub_total - inv.invoice_bayar, 0) ELSE 0 END), 0) AS sisa_piutang
        FROM invoice inv
        LEFT JOIN customer c ON inv.invoice_customer = c.customer_id
        WHERE {$where}
        GROUP BY c.customer_id, c.customer_nama, c.customer_category
        ORDER BY total_penjualan DESC, c.customer_nama
        LIMIT 300
    ";
    $res = lpj_query($conn, $sql, 'Customer');
    $rows = [];
    $no = 1;
    while ($r = mysqli_fetch_assoc($res)) {
        $cid = (int) ($r['customer_id'] ?? 0);
        $catLabel = '';
        $cat = (int) ($r['customer_category'] ?? 0);
        if ($cat === 1) {
            $catLabel = ' (Retail)';
        } elseif ($cat === 2) {
            $catLabel = ' (Grosir)';
        }
        $rows[] = [
            'no' => $no++,
            'customer_id' => $cid,
            'customer_nama' => ($r['customer_nama'] ?? '-') . $catLabel,
            'jumlah_transaksi' => (int) ($r['jumlah_transaksi'] ?? 0),
            'total_qty' => 0.0,
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
