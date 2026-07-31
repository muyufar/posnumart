<?php

/**
 * Purchase Order dari Pusat Pengadaan Gudang → WA Supplier → Terima Barang → INV Pembelian.
 */

require_once __DIR__ . '/pengadaan-gudang-lib.php';

function pengadaan_po_ensure_tables(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $sql = @file_get_contents(__DIR__ . '/../db/migration_pengadaan_po.sql');
    if ($sql !== false) {
        foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || strpos($stmt, '--') === 0) {
                // skip pure comment blocks; still allow CREATE/ALTER with trailing comments
            }
            if ($stmt === '') {
                continue;
            }
            // Jalankan CREATE TABLE dan ALTER TABLE (abaikan komentar murni)
            $upper = strtoupper(ltrim($stmt));
            if (strpos($upper, 'CREATE TABLE') === 0 || strpos($upper, 'ALTER TABLE') === 0) {
                @mysqli_query($conn, $stmt);
            }
        }
    }

    // Selalu pastikan kolom/index ekstra ada (meski file migrasi belum ter-deploy)
    pengadaan_po_ensure_columns($conn);
    $done = true;
}

function pengadaan_po_ensure_columns(mysqli $conn): void
{
    $cols = [
        'pengadaan_request' => ['po_id' => 'INT NULL'],
        'pengadaan_po' => [
            'alokasi_at' => 'DATETIME NULL DEFAULT NULL',
            'alokasi_by' => 'INT NULL DEFAULT NULL',
        ],
        'supplier' => ['kode_suplier' => "VARCHAR(100) NULL DEFAULT NULL COMMENT 'Kode filter barang (SUKA002, dll)'"],
    ];
    foreach ($cols as $table => $fields) {
        $tblChk = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        if (!$tblChk || mysqli_num_rows($tblChk) === 0) {
            continue;
        }
        foreach ($fields as $col => $def) {
            $chk = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$col'");
            if ($chk && mysqli_num_rows($chk) === 0) {
                @mysqli_query($conn, "ALTER TABLE `$table` ADD COLUMN `$col` $def");
            }
        }
    }

    $idxReq = @mysqli_query($conn, "SHOW INDEX FROM pengadaan_request WHERE Key_name = 'idx_pengadaan_request_po_id'");
    if ($idxReq && mysqli_num_rows($idxReq) === 0) {
        $colPo = mysqli_query($conn, "SHOW COLUMNS FROM pengadaan_request LIKE 'po_id'");
        if ($colPo && mysqli_num_rows($colPo) > 0) {
            @mysqli_query($conn, 'ALTER TABLE pengadaan_request ADD INDEX idx_pengadaan_request_po_id (po_id)');
        }
    }

    $idx = @mysqli_query($conn, "SHOW INDEX FROM supplier WHERE Key_name = 'idx_supplier_kode_suplier'");
    if ($idx && mysqli_num_rows($idx) === 0) {
        @mysqli_query($conn, 'ALTER TABLE supplier ADD INDEX idx_supplier_kode_suplier (kode_suplier)');
    }
}

function pengadaan_po_status_badge(string $status): string
{
    $map = [
        'draft' => '<span class="badge badge-secondary">Draft</span>',
        'dikirim' => '<span class="badge badge-info">Dikirim WA</span>',
        'dikonfirmasi' => '<span class="badge badge-primary">Dikonfirmasi</span>',
        'diterima' => '<span class="badge badge-warning">Diterima</span>',
        'selesai' => '<span class="badge badge-success">Selesai (INV)</span>',
        'batal' => '<span class="badge badge-dark">Batal</span>',
    ];

    return $map[$status] ?? htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
}

function pengadaan_po_resolve_supplier_from_pembelian(mysqli $conn, string $kodeSuplier, int $cabang = 0): ?array
{
    $kodeSuplier = trim($kodeSuplier);
    if ($kodeSuplier === '') {
        return null;
    }
    $esc = mysqli_real_escape_string($conn, $kodeSuplier);
    $cab = (int) $cabang;
    $cabSql = $cab > 0 ? " AND ip.invoice_pembelian_cabang = $cab " : '';

    $res = mysqli_query($conn, "
        SELECT s.supplier_id, s.supplier_nama, s.supplier_wa, s.supplier_company, s.supplier_cabang, s.kode_suplier
        FROM pembelian p
        INNER JOIN barang b ON b.barang_id = p.barang_id AND b.kode_suplier = '$esc'
        INNER JOIN invoice_pembelian ip ON ip.pembelian_invoice = p.pembelian_invoice
        INNER JOIN supplier s ON s.supplier_id = ip.invoice_supplier AND s.supplier_status = '1'
        WHERE 1=1 $cabSql
        ORDER BY ip.invoice_date DESC, ip.invoice_pembelian_id DESC
        LIMIT 1
    ");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return $row;
    }

    return null;
}

function pengadaan_po_resolve_supplier(mysqli $conn, string $kodeSuplier, int $cabang = 0): ?array
{
    static $cache = [];
    pengadaan_po_ensure_tables($conn);

    $kodeSuplier = trim($kodeSuplier);
    if ($kodeSuplier === '') {
        return null;
    }
    $cab = (int) $cabang;
    $cacheKey = strtoupper($kodeSuplier) . '|' . $cab;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $esc = mysqli_real_escape_string($conn, $kodeSuplier);

    // 1) Mapping eksplisit: supplier.kode_suplier = kode di master barang
    $res = mysqli_query($conn, "
        SELECT supplier_id, supplier_nama, supplier_wa, supplier_company, supplier_cabang, kode_suplier
        FROM supplier
        WHERE supplier_status = '1'
          AND kode_suplier IS NOT NULL
          AND kode_suplier != ''
          AND UPPER(kode_suplier) = UPPER('$esc')
        ORDER BY CASE WHEN supplier_cabang = $cab THEN 0 ELSE 1 END
        LIMIT 1
    ");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $cache[$cacheKey] = $row;
        return $row;
    }

    // 2) Fallback: supplier terakhir dari riwayat pembelian barang dengan kode tersebut
    $fromPembelian = pengadaan_po_resolve_supplier_from_pembelian($conn, $kodeSuplier, $cab);
    if ($fromPembelian) {
        $cache[$cacheKey] = $fromPembelian;
        return $fromPembelian;
    }

    // 3) Coba riwayat pembelian tanpa filter cabang
    if ($cab > 0) {
        $fromAny = pengadaan_po_resolve_supplier_from_pembelian($conn, $kodeSuplier, 0);
        if ($fromAny) {
            $cache[$cacheKey] = $fromAny;
            return $fromAny;
        }
    }

    $cache[$cacheKey] = null;
    return null;
}

function pengadaan_po_barang_satuan(mysqli $conn, int $barangId): string
{
    $res = mysqli_query($conn, "
        SELECT s.satuan_nama
        FROM barang b
        LEFT JOIN satuan s ON s.satuan_id = b.satuan_id AND s.satuan_status > 0
        WHERE b.barang_id = $barangId
        LIMIT 1
    ");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $nama = trim((string) ($row['satuan_nama'] ?? ''));
        if ($nama !== '') {
            return strtoupper($nama);
        }
    }

    return 'PCS';
}

function pengadaan_po_generate_number(mysqli $conn): string
{
    $prefix = 'PO-GUD-' . date('Ymd') . '-';
    $esc = mysqli_real_escape_string($conn, $prefix);
    $res = mysqli_query($conn, "
        SELECT po_number FROM pengadaan_po
        WHERE po_number LIKE '{$esc}%'
        ORDER BY id DESC LIMIT 1
    ");
    $seq = 1;
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $parts = explode('-', (string) $row['po_number']);
        $last = (int) end($parts);
        $seq = $last + 1;
    }

    return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
}

function pengadaan_po_wa_phone(string $wa): string
{
    $num = preg_replace('/[^0-9]/', '', $wa);
    if ($num === '') {
        return '';
    }
    if (substr($num, 0, 2) === '62') {
        return $num;
    }
    if (substr($num, 0, 1) === '0') {
        return '62' . substr($num, 1);
    }

    return '62' . $num;
}

/**
 * @param array<int,array<string,mixed>> $lines
 */
function pengadaan_po_build_wa_message(array $po, array $lines): string
{
    $msg = "*PURCHASE ORDER - NUMART*\n";
    $msg .= "No: *" . ($po['po_number'] ?? '') . "*\n";
    $msg .= 'Tgl: ' . date('d/m/Y', strtotime((string) ($po['created_at'] ?? 'now'))) . "\n\n";
    $msg .= "*Daftar Barang:*\n\n";

    foreach ($lines as $ln) {
        $kode = (string) ($ln['barang_kode'] ?? '');
        $nama = (string) ($ln['barang_nama'] ?? '');
        $qty = number_format((float) ($ln['qty_po'] ?? 0), 0, '.', '');
        $satuan = (string) ($ln['satuan_nama'] ?? 'PCS');
        $msg .= $kode . ' | ' . $nama . ' | ' . $qty . ' | ' . $satuan . "\n";
    }

    $msg .= "\nTotal item: " . count($lines) . " produk\n";
    $msg .= "Mohon konfirmasi ketersediaan & estimasi kirim.\nTerima kasih.";

    return $msg;
}

function pengadaan_po_wa_link(string $phone, string $message): string
{
    if ($phone === '') {
        return '';
    }

    return 'https://api.whatsapp.com/send?phone=' . rawurlencode($phone) . '&text=' . rawurlencode($message);
}

/**
 * Buat PO dari daftar pengadaan_request id, dikelompokkan per kode_suplier.
 *
 * @param int[] $requestIds
 * @return array{created:int, po_ids:int[], errors:string[]}
 */
function pengadaan_po_create_from_requests(mysqli $conn, array $requestIds, int $userId, int $cabangGudang = 0): array
{
    pengadaan_po_ensure_tables($conn);
    pengadaan_gudang_ensure_table($conn);
    require_once __DIR__ . '/functions.php';

    $result = ['created' => 0, 'po_ids' => [], 'errors' => []];
    $requestIds = array_values(array_unique(array_filter(array_map('intval', $requestIds))));
    if ($requestIds === []) {
        $result['errors'][] = 'Tidak ada permintaan dipilih';

        return $result;
    }

    $idsStr = implode(',', $requestIds);
    $res = mysqli_query($conn, "
        SELECT * FROM pengadaan_request
        WHERE id IN ($idsStr)
          AND status IN ('pending','diproses')
          AND (po_id IS NULL OR po_id = 0)
    ");
    if (!$res) {
        $result['errors'][] = mysqli_error($conn);

        return $result;
    }

    $bySupplier = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $ks = trim((string) ($row['kode_suplier'] ?? ''));
        if ($ks === '') {
            $ks = '_TANPA_SUPPLIER_';
        }
        $bySupplier[$ks][] = $row;
    }

    if ($bySupplier === []) {
        $result['errors'][] = 'Permintaan tidak ditemukan atau sudah selesai';

        return $result;
    }

    // Prefetch semua barang gudang (1 query) untuk kode yang dipilih
    $allCodes = [];
    foreach ($bySupplier as $rows) {
        if (!is_array($rows)) {
            continue;
        }
        foreach ($rows as $req) {
            $kode = trim((string) ($req['barang_kode'] ?? ''));
            if ($kode !== '') {
                $allCodes[$kode] = true;
            }
        }
    }
    $gudangMap = pengadaan_po_prefetch_gudang_barang($conn, array_keys($allCodes), $cabangGudang);

    foreach ($bySupplier as $kodeSuplier => $rows) {
        if ($kodeSuplier === '_TANPA_SUPPLIER_') {
            $result['errors'][] = 'Beberapa barang tidak punya kode supplier — lewati';
            continue;
        }

        $supplier = pengadaan_po_resolve_supplier($conn, $kodeSuplier, $cabangGudang);
        $supplierId = $supplier ? (int) ($supplier['supplier_id'] ?? 0) : null;
        $poNumber = pengadaan_po_generate_number($conn);
        $poEsc = mysqli_real_escape_string($conn, $poNumber);
        $ksEsc = mysqli_real_escape_string($conn, $kodeSuplier);
        $supSql = $supplierId ? (string) $supplierId : 'NULL';

        $ok = mysqli_query($conn, "
            INSERT INTO pengadaan_po (po_number, kode_suplier, supplier_id, status, created_by)
            VALUES ('$poEsc', '$ksEsc', $supSql, 'draft', $userId)
        ");
        if (!$ok) {
            $result['errors'][] = 'Gagal buat PO ' . $kodeSuplier . ': ' . mysqli_error($conn);
            continue;
        }
        $poId = (int) mysqli_insert_id($conn);

        $aggregated = [];
        $allReqIds = [];
        foreach ($rows as $req) {
            $kode = (string) ($req['barang_kode'] ?? '');
            if ($kode === '') {
                continue;
            }
            $qty = max(1, (int) ($req['qty_diminta'] ?? $req['qty_disarankan'] ?? 1));
            if (!isset($aggregated[$kode])) {
                $aggregated[$kode] = [
                    'req_ids' => [],
                    'cabang_ids' => [],
                    'barang_id' => (int) ($req['barang_id'] ?? 0),
                    'barang_nama' => (string) ($req['barang_nama'] ?? ''),
                    'qty' => 0,
                ];
            }
            $reqId = (int) ($req['id'] ?? 0);
            $aggregated[$kode]['req_ids'][] = $reqId;
            $aggregated[$kode]['cabang_ids'][] = (int) ($req['cabang_id'] ?? 0);
            $aggregated[$kode]['qty'] += $qty;
            if ($reqId > 0) {
                $allReqIds[] = $reqId;
            }
        }

        // Multi-row INSERT line (lebih cepat daripada 1 query per barang)
        $valueParts = [];
        foreach ($aggregated as $kode => $agg) {
            $meta = $gudangMap[$kode] ?? null;
            $gudangBarangId = $meta ? (int) $meta['barang_id'] : (int) $agg['barang_id'];
            if ($gudangBarangId < 1) {
                $gudangBarangId = (int) $agg['barang_id'];
            }
            $qty = (int) $agg['qty'];
            $satuan = $meta ? (string) $meta['satuan_nama'] : 'PCS';
            if ($satuan === '') {
                $satuan = 'PCS';
            }
            $harga = $meta ? (float) $meta['harga_beli'] : 0.0;
            $kodeEsc = mysqli_real_escape_string($conn, $kode);
            $namaEsc = mysqli_real_escape_string($conn, (string) $agg['barang_nama']);
            $satEsc = mysqli_real_escape_string($conn, $satuan);
            $cabangId = (int) ($agg['cabang_ids'][0] ?? 0);
            $reqId = (int) ($agg['req_ids'][0] ?? 0);
            $reqSql = $reqId > 0 ? (string) $reqId : 'NULL';

            $valueParts[] = "($poId, $reqSql, $gudangBarangId, '$kodeEsc', '$namaEsc', $cabangId, $qty, '$satEsc', $harga)";
        }

        if ($valueParts !== []) {
            // Insert per batch 50 baris agar query tidak terlalu besar
            foreach (array_chunk($valueParts, 50) as $chunk) {
                $valuesSql = implode(",\n", $chunk);
                mysqli_query($conn, "
                    INSERT INTO pengadaan_po_line (
                        po_id, pengadaan_request_id, barang_id, barang_kode, barang_nama,
                        cabang_id, qty_po, satuan_nama, harga_estimasi
                    ) VALUES $valuesSql
                ");
            }
        }

        // Satu UPDATE untuk semua request ID di PO ini
        $allReqIds = array_values(array_unique(array_filter(array_map('intval', $allReqIds))));
        if ($allReqIds !== []) {
            $reqIdsStr = implode(',', $allReqIds);
            mysqli_query($conn, "
                UPDATE pengadaan_request SET
                    status = 'diproses',
                    po_id = $poId,
                    diproses_by = $userId,
                    diproses_at = NOW(),
                    updated_at = NOW()
                WHERE id IN ($reqIdsStr)
            ");
        }

        $result['created']++;
        $result['po_ids'][] = $poId;
    }

    return $result;
}

/**
 * Prefetch barang gudang + satuan + harga beli untuk banyak kode sekaligus.
 *
 * @param string[] $kodes
 * @return array<string, array{barang_id:int, satuan_nama:string, harga_beli:float}>
 */
function pengadaan_po_prefetch_gudang_barang(mysqli $conn, array $kodes, int $cabangGudang = 0): array
{
    $map = [];
    $kodes = array_values(array_unique(array_filter(array_map('strval', $kodes))));
    if ($kodes === []) {
        return $map;
    }

    $cab = (int) $cabangGudang;
    $escaped = [];
    foreach ($kodes as $kode) {
        $escaped[] = "'" . mysqli_real_escape_string($conn, $kode) . "'";
    }

    // Chunk IN list biar aman
    foreach (array_chunk($escaped, 200) as $chunk) {
        $in = implode(',', $chunk);
        $res = mysqli_query($conn, "
            SELECT
                b.barang_kode,
                b.barang_id,
                COALESCE(b.barang_harga_beli, 0) AS harga_beli,
                COALESCE(s.satuan_nama, 'PCS') AS satuan_nama
            FROM barang b
            LEFT JOIN satuan s ON s.satuan_id = b.satuan_id AND s.satuan_status > 0
            WHERE b.barang_cabang = $cab
              AND b.barang_status = '1'
              AND b.barang_kode IN ($in)
        ");
        if (!$res) {
            continue;
        }
        while ($row = mysqli_fetch_assoc($res)) {
            $kode = (string) ($row['barang_kode'] ?? '');
            if ($kode === '') {
                continue;
            }
            $map[$kode] = [
                'barang_id' => (int) ($row['barang_id'] ?? 0),
                'satuan_nama' => strtoupper(trim((string) ($row['satuan_nama'] ?? 'PCS')) ?: 'PCS'),
                'harga_beli' => (float) ($row['harga_beli'] ?? 0),
            ];
        }
    }

    return $map;
}

function pengadaan_po_gudang_barang_id(mysqli $conn, string $barangKode, int $cabangGudang = 0): int
{
    $kodeEsc = mysqli_real_escape_string($conn, $barangKode);
    $cab = (int) $cabangGudang;
    $res = mysqli_query($conn, "
        SELECT barang_id FROM barang
        WHERE barang_kode = '$kodeEsc' AND barang_cabang = $cab AND barang_status = '1'
        LIMIT 1
    ");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return (int) ($row['barang_id'] ?? 0);
    }

    return 0;
}

function pengadaan_po_get(mysqli $conn, int $poId): ?array
{
    pengadaan_po_ensure_tables($conn);
    $res = mysqli_query($conn, "SELECT * FROM pengadaan_po WHERE id = $poId LIMIT 1");
    if (!$res || !($row = mysqli_fetch_assoc($res))) {
        return null;
    }

    return $row;
}

/** @return array<int,array<string,mixed>> */
function pengadaan_po_get_lines(mysqli $conn, int $poId): array
{
    $lines = [];
    $res = mysqli_query($conn, "SELECT * FROM pengadaan_po_line WHERE po_id = $poId ORDER BY barang_nama ASC");
    if (!$res) {
        return $lines;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $lines[] = $row;
    }

    return $lines;
}

function pengadaan_po_mark_sent(mysqli $conn, int $poId, int $userId): bool
{
    return (bool) mysqli_query($conn, "
        UPDATE pengadaan_po SET
            status = IF(status = 'draft', 'dikirim', status),
            wa_sent_at = NOW(), wa_sent_by = $userId, updated_at = NOW()
        WHERE id = $poId AND status IN ('draft','dikirim')
    ");
}

function pengadaan_po_mark_confirmed(mysqli $conn, int $poId, int $userId): bool
{
    return (bool) mysqli_query($conn, "
        UPDATE pengadaan_po SET
            status = 'dikonfirmasi', confirmed_at = NOW(), confirmed_by = $userId, updated_at = NOW()
        WHERE id = $poId AND status IN ('draft','dikirim','dikonfirmasi')
    ");
}

function pengadaan_po_scan_line(mysqli $conn, int $poId, string $barcode): ?array
{
    $codeEsc = mysqli_real_escape_string($conn, trim($barcode));
    if ($codeEsc === '') {
        return null;
    }
    $res = mysqli_query($conn, "
        SELECT * FROM pengadaan_po_line
        WHERE po_id = $poId AND barang_kode = '$codeEsc'
        LIMIT 1
    ");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return $row;
    }

    return null;
}

function pengadaan_po_increment_received(mysqli $conn, int $lineId, float $addQty = 1): bool
{
    $addQty = max(0.1, $addQty);

    return (bool) mysqli_query($conn, "
        UPDATE pengadaan_po_line SET qty_received = qty_received + $addQty WHERE id = $lineId
    ");
}

function pengadaan_po_update_line(mysqli $conn, int $lineId, float $qtyReceived, string $satuan, float $harga): bool
{
    require_once __DIR__ . '/satuan-lib.php';
    $satuan = trim($satuan);
    if ($satuan === '') {
        return false;
    }
    // Samakan ke nama resmi di master satuan (jika ada)
    $satEscCheck = mysqli_real_escape_string($conn, $satuan);
    $chkSat = mysqli_query($conn, "
        SELECT satuan_nama FROM satuan
        WHERE satuan_status > 0
          AND " . satuan_sql_cabang() . "
          AND UPPER(TRIM(satuan_nama)) = UPPER(TRIM('$satEscCheck'))
        LIMIT 1
    ");
    $satRow = $chkSat ? mysqli_fetch_assoc($chkSat) : null;
    if ($satRow) {
        $satuan = trim((string) $satRow['satuan_nama']);
    }
    $satEsc = mysqli_real_escape_string($conn, $satuan);
    $qtyReceived = (float) $qtyReceived;
    $harga = (float) $harga;

    return (bool) mysqli_query($conn, "
        UPDATE pengadaan_po_line SET
            qty_received = $qtyReceived,
            satuan_nama = '$satEsc',
            harga_actual = $harga
        WHERE id = $lineId
    ");
}

/**
 * Update qty_po & satuan_nama pada baris PO (sebelum selesai/batal).
 *
 * @return true|string true jika sukses, string pesan error jika gagal
 */
function pengadaan_po_update_qty_satuan(mysqli $conn, int $lineId, int $poId, float $qtyPo, string $satuanNama)
{
    $lineId = (int) $lineId;
    $poId = (int) $poId;
    if ($lineId < 1 || $poId < 1) {
        return 'Baris PO tidak valid';
    }

    $po = pengadaan_po_get($conn, $poId);
    if (!$po) {
        return 'PO tidak ditemukan';
    }
    $status = (string) ($po['status'] ?? '');
    if (in_array($status, ['selesai', 'batal'], true)) {
        return 'PO status ' . $status . ' tidak bisa diedit';
    }

    $res = mysqli_query($conn, "
        SELECT id, qty_po, qty_received, satuan_nama, barang_nama
        FROM pengadaan_po_line
        WHERE id = $lineId AND po_id = $poId
        LIMIT 1
    ");
    $line = $res ? mysqli_fetch_assoc($res) : null;
    if (!$line) {
        return 'Baris tidak ditemukan di PO ini';
    }

    $qtyReceived = (float) ($line['qty_received'] ?? 0);
    $qtyPo = round($qtyPo, 4);
    if ($qtyPo <= 0) {
        return 'Qty PO harus lebih dari 0 (' . ($line['barang_nama'] ?? '') . ')';
    }
    if ($qtyPo + 0.0001 < $qtyReceived) {
        return 'Qty PO tidak boleh lebih kecil dari qty diterima ('
            . number_format($qtyReceived, 1, '.', '') . ') untuk '
            . ($line['barang_nama'] ?? '');
    }

    $satuanNama = trim($satuanNama);
    if ($satuanNama === '') {
        return 'Satuan wajib dipilih (' . ($line['barang_nama'] ?? '') . ')';
    }

    // Validasi dari master satuan (cabang pusat)
    require_once __DIR__ . '/satuan-lib.php';
    $satEscCheck = mysqli_real_escape_string($conn, $satuanNama);
    $chkSat = mysqli_query($conn, "
        SELECT satuan_nama FROM satuan
        WHERE satuan_status > 0
          AND " . satuan_sql_cabang() . "
          AND UPPER(TRIM(satuan_nama)) = UPPER(TRIM('$satEscCheck'))
        LIMIT 1
    ");
    $satRow = $chkSat ? mysqli_fetch_assoc($chkSat) : null;
    if (!$satRow) {
        return 'Satuan "' . $satuanNama . '" tidak ada di master satuan';
    }
    $satuanNama = trim((string) $satRow['satuan_nama']);
    $satEsc = mysqli_real_escape_string($conn, $satuanNama);

    $ok = mysqli_query($conn, "
        UPDATE pengadaan_po_line SET
            qty_po = $qtyPo,
            satuan_nama = '$satEsc'
        WHERE id = $lineId AND po_id = $poId
    ");
    if (!$ok) {
        return 'Gagal update baris: ' . mysqli_error($conn);
    }

    mysqli_query($conn, "UPDATE pengadaan_po SET updated_at = NOW() WHERE id = $poId");

    return true;
}

/**
 * Update banyak baris qty/satuan sekaligus.
 *
 * @param array<int|string, array{qty_po?:mixed, satuan_nama?:mixed}> $lines
 * @return array{ok:bool, message:string, updated:int}
 */
function pengadaan_po_update_lines_qty_satuan(mysqli $conn, int $poId, array $lines): array
{
    $poId = (int) $poId;
    if ($poId < 1 || $lines === []) {
        return ['ok' => false, 'message' => 'Data baris kosong', 'updated' => 0];
    }

    $updated = 0;
    foreach ($lines as $lineId => $row) {
        if (!is_array($row)) {
            continue;
        }
        $lineId = (int) $lineId;
        $qtyPo = (float) ($row['qty_po'] ?? 0);
        $satuan = (string) ($row['satuan_nama'] ?? '');
        $result = pengadaan_po_update_qty_satuan($conn, $lineId, $poId, $qtyPo, $satuan);
        if ($result !== true) {
            return ['ok' => false, 'message' => (string) $result, 'updated' => $updated];
        }
        $updated++;
    }

    return [
        'ok' => $updated > 0,
        'message' => $updated > 0 ? ('Berhasil update ' . $updated . ' baris') : 'Tidak ada baris yang diupdate',
        'updated' => $updated,
    ];
}

/**
 * Cari barang gudang (cabang 0) untuk ditambah manual ke PO.
 *
 * @return list<array{barang_id:int, barang_kode:string, barang_nama:string, kode_suplier:string, satuan_nama:string, harga_beli:float}>
 */
function pengadaan_po_search_barang(mysqli $conn, string $q, string $preferKodeSuplier = '', int $limit = 20): array
{
    $q = trim($q);
    if (strlen($q) < 2) {
        return [];
    }
    $limit = max(1, min(50, (int) $limit));
    $like = mysqli_real_escape_string($conn, str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q));
    $preferEsc = mysqli_real_escape_string($conn, trim($preferKodeSuplier));

    $orderPrefer = '';
    if ($preferEsc !== '') {
        $orderPrefer = "CASE WHEN UPPER(TRIM(COALESCE(b.kode_suplier,''))) = UPPER(TRIM('$preferEsc')) THEN 0 ELSE 1 END,";
    }

    $sql = "
        SELECT
            b.barang_id,
            b.barang_kode,
            b.barang_nama,
            COALESCE(b.kode_suplier, '') AS kode_suplier,
            COALESCE(s.satuan_nama, 'PCS') AS satuan_nama,
            COALESCE(b.barang_harga_beli, 0) AS harga_beli
        FROM barang b
        LEFT JOIN satuan s ON s.satuan_id = b.satuan_id AND s.satuan_status > 0
        WHERE b.barang_cabang = 0
          AND b.barang_status = '1'
          AND (
            b.barang_kode LIKE '%$like%'
            OR b.barang_nama LIKE '%$like%'
          )
        ORDER BY $orderPrefer b.barang_nama ASC
        LIMIT $limit
    ";

    $hasil = [];
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return [];
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $hasil[] = [
            'barang_id' => (int) ($row['barang_id'] ?? 0),
            'barang_kode' => (string) ($row['barang_kode'] ?? ''),
            'barang_nama' => (string) ($row['barang_nama'] ?? ''),
            'kode_suplier' => (string) ($row['kode_suplier'] ?? ''),
            'satuan_nama' => strtoupper(trim((string) ($row['satuan_nama'] ?? 'PCS')) ?: 'PCS'),
            'harga_beli' => (float) ($row['harga_beli'] ?? 0),
        ];
    }

    return $hasil;
}

/**
 * Hapus 1 baris barang dari PO.
 *
 * @return array{ok:bool, message:string}
 */
function pengadaan_po_delete_line(mysqli $conn, int $poId, int $lineId): array
{
    pengadaan_po_ensure_tables($conn);
    $poId = (int) $poId;
    $lineId = (int) $lineId;
    if ($poId < 1 || $lineId < 1) {
        return ['ok' => false, 'message' => 'Data tidak valid'];
    }

    $po = pengadaan_po_get($conn, $poId);
    if (!$po) {
        return ['ok' => false, 'message' => 'PO tidak ditemukan'];
    }
    $status = (string) ($po['status'] ?? '');
    if (in_array($status, ['selesai', 'batal'], true)) {
        return ['ok' => false, 'message' => 'PO status ' . $status . ' tidak bisa diubah'];
    }

    $res = mysqli_query($conn, "
        SELECT id, barang_nama, qty_received, pengadaan_request_id
        FROM pengadaan_po_line
        WHERE id = $lineId AND po_id = $poId
        LIMIT 1
    ");
    $line = $res ? mysqli_fetch_assoc($res) : null;
    if (!$line) {
        return ['ok' => false, 'message' => 'Baris PO tidak ditemukan'];
    }
    if ((float) ($line['qty_received'] ?? 0) > 0) {
        return ['ok' => false, 'message' => 'Barang sudah ada qty diterima — tidak bisa dihapus'];
    }

    $reqId = (int) ($line['pengadaan_request_id'] ?? 0);
    $ok = (bool) mysqli_query($conn, "DELETE FROM pengadaan_po_line WHERE id = $lineId AND po_id = $poId LIMIT 1");
    if (!$ok) {
        return ['ok' => false, 'message' => 'Gagal hapus: ' . mysqli_error($conn)];
    }

    if ($reqId > 0) {
        mysqli_query($conn, "
            UPDATE pengadaan_request SET
                po_id = NULL,
                status = IF(status = 'diproses', 'pending', status),
                updated_at = NOW()
            WHERE id = $reqId
        ");
    }
    mysqli_query($conn, "UPDATE pengadaan_po SET updated_at = NOW() WHERE id = $poId");

    return [
        'ok' => true,
        'message' => 'Barang dihapus dari PO: ' . trim((string) ($line['barang_nama'] ?? '')),
    ];
}

/**
 * Tambah baris barang manual ke PO.
 *
 * @return array{ok:bool, message:string, line_id?:int}
 */
function pengadaan_po_add_line_manual(
    mysqli $conn,
    int $poId,
    int $barangId,
    float $qtyPo,
    string $satuanNama = '',
    int $cabangId = 0
): array {
    pengadaan_po_ensure_tables($conn);
    require_once __DIR__ . '/functions.php';
    require_once __DIR__ . '/satuan-lib.php';

    $poId = (int) $poId;
    $barangId = (int) $barangId;
    $qtyPo = round((float) $qtyPo, 4);
    $cabangId = (int) $cabangId;

    if ($poId < 1 || $barangId < 1) {
        return ['ok' => false, 'message' => 'Data barang/PO tidak valid'];
    }
    if ($qtyPo <= 0) {
        return ['ok' => false, 'message' => 'Qty harus lebih dari 0'];
    }

    $po = pengadaan_po_get($conn, $poId);
    if (!$po) {
        return ['ok' => false, 'message' => 'PO tidak ditemukan'];
    }
    $status = (string) ($po['status'] ?? '');
    if (in_array($status, ['selesai', 'batal'], true)) {
        return ['ok' => false, 'message' => 'PO status ' . $status . ' tidak bisa ditambah barang'];
    }

    $resBrg = mysqli_query($conn, "
        SELECT barang_id, barang_kode, barang_nama, kode_suplier, satuan_id
        FROM barang
        WHERE barang_id = $barangId AND barang_status = '1'
        LIMIT 1
    ");
    $brg = $resBrg ? mysqli_fetch_assoc($resBrg) : null;
    if (!$brg) {
        return ['ok' => false, 'message' => 'Barang tidak ditemukan'];
    }

    $kode = trim((string) ($brg['barang_kode'] ?? ''));
    $nama = trim((string) ($brg['barang_nama'] ?? ''));
    if ($kode === '') {
        return ['ok' => false, 'message' => 'Barcode barang kosong'];
    }

    $kodeEsc = mysqli_real_escape_string($conn, $kode);
    $dup = mysqli_query($conn, "
        SELECT id, qty_po FROM pengadaan_po_line
        WHERE po_id = $poId AND barang_kode = '$kodeEsc'
        LIMIT 1
    ");
    if ($dup && ($dupRow = mysqli_fetch_assoc($dup))) {
        return [
            'ok' => false,
            'message' => 'Barang sudah ada di PO. Ubah qty pada baris yang ada (saat ini qty '
                . number_format((float) ($dupRow['qty_po'] ?? 0), 0, '.', '') . ').',
            'line_id' => (int) ($dupRow['id'] ?? 0),
        ];
    }

    $satuanNama = trim($satuanNama);
    if ($satuanNama === '') {
        $satuanNama = pengadaan_po_barang_satuan($conn, $barangId);
    }
    $satEscCheck = mysqli_real_escape_string($conn, $satuanNama);
    $chkSat = mysqli_query($conn, "
        SELECT satuan_nama FROM satuan
        WHERE satuan_status > 0
          AND " . satuan_sql_cabang() . "
          AND UPPER(TRIM(satuan_nama)) = UPPER(TRIM('$satEscCheck'))
        LIMIT 1
    ");
    $satRow = $chkSat ? mysqli_fetch_assoc($chkSat) : null;
    if ($satRow) {
        $satuanNama = trim((string) $satRow['satuan_nama']);
    } else {
        // fallback nama satuan dari master barang
        $satuanNama = pengadaan_po_barang_satuan($conn, $barangId);
    }
    $satEsc = mysqli_real_escape_string($conn, $satuanNama);
    $namaEsc = mysqli_real_escape_string($conn, $nama);
    $harga = barang_get_harga_beli_untuk_input($conn, $barangId);

    $ok = mysqli_query($conn, "
        INSERT INTO pengadaan_po_line (
            po_id, pengadaan_request_id, barang_id, barang_kode, barang_nama,
            cabang_id, qty_po, satuan_nama, harga_estimasi, qty_received
        ) VALUES (
            $poId, NULL, $barangId, '$kodeEsc', '$namaEsc',
            $cabangId, $qtyPo, '$satEsc', $harga, 0
        )
    ");
    if (!$ok) {
        return ['ok' => false, 'message' => 'Gagal menambah barang: ' . mysqli_error($conn)];
    }

    $lineId = (int) mysqli_insert_id($conn);
    mysqli_query($conn, "UPDATE pengadaan_po SET updated_at = NOW() WHERE id = $poId");

    return [
        'ok' => true,
        'message' => 'Barang ditambahkan ke PO',
        'line_id' => $lineId,
    ];
}

/**
 * Isi keranjang pembelian dari PO yang diterima, lalu redirect ke transaksi-pembelian.
 *
 * @return array{ok:bool, message:string, redirect?:string}
 */
function pengadaan_po_prepare_invoice_cart(mysqli $conn, int $poId, int $userId, int $cabangGudang = 0): array
{
    pengadaan_po_ensure_tables($conn);
    require_once __DIR__ . '/functions.php';

    $po = pengadaan_po_get($conn, $poId);
    if (!$po) {
        return ['ok' => false, 'message' => 'PO tidak ditemukan'];
    }
    if (!in_array((string) ($po['status'] ?? ''), ['draft', 'dikirim', 'dikonfirmasi', 'diterima'], true)) {
        return ['ok' => false, 'message' => 'PO sudah selesai atau dibatalkan'];
    }

    $lines = pengadaan_po_get_lines($conn, $poId);
    $hasReceived = false;
    foreach ($lines as $ln) {
        if ((float) ($ln['qty_received'] ?? 0) > 0) {
            $hasReceived = true;
            break;
        }
    }
    if (!$hasReceived) {
        return ['ok' => false, 'message' => 'Belum ada barang discan/diterima'];
    }

    mysqli_query($conn, "DELETE FROM keranjang_pembelian WHERE keranjang_id_kasir = $userId AND keranjang_cabang = $cabangGudang");

    $added = 0;
    foreach ($lines as $ln) {
        $qty = (float) ($ln['qty_received'] ?? 0);
        if ($qty <= 0) {
            continue;
        }
        $barangId = (int) ($ln['barang_id'] ?? 0);
        if ($barangId < 1) {
            continue;
        }
        $harga = (float) ($ln['harga_actual'] ?? 0);
        if ($harga <= 0) {
            $harga = (float) ($ln['harga_estimasi'] ?? 0);
        }
        if ($harga <= 0) {
            $harga = barang_get_harga_beli_untuk_input($conn, $barangId);
        }
        $namaEsc = mysqli_real_escape_string($conn, (string) ($ln['barang_nama'] ?? ''));
        $cek = $barangId . $userId . $cabangGudang;

        $exist = mysqli_query($conn, "SELECT keranjang_id FROM keranjang_pembelian WHERE keranjang_id_cek = '$cek' LIMIT 1");
        if ($exist && mysqli_fetch_assoc($exist)) {
            mysqli_query($conn, "UPDATE keranjang_pembelian SET keranjang_qty = $qty, keranjang_harga = $harga WHERE keranjang_id_cek = '$cek'");
        } else {
            mysqli_query($conn, "
                INSERT INTO keranjang_pembelian (keranjang_nama, keranjang_harga, barang_id, keranjang_qty, keranjang_id_kasir, keranjang_id_cek, keranjang_cabang)
                VALUES ('$namaEsc', $harga, $barangId, $qty, $userId, '$cek', $cabangGudang)
            ");
        }
        $added++;
    }

    if ($added === 0) {
        return ['ok' => false, 'message' => 'Tidak ada barang valid untuk invoice'];
    }

    mysqli_query($conn, "
        UPDATE pengadaan_po SET status = 'diterima', received_at = NOW(), received_by = $userId, updated_at = NOW()
        WHERE id = $poId
    ");

    $supplierId = (int) ($po['supplier_id'] ?? 0);
    $redirect = 'transaksi-pembelian?po=' . $poId;
    if ($supplierId > 0) {
        $redirect .= '&supplier=' . $supplierId;
    }

    return ['ok' => true, 'message' => 'Keranjang pembelian siap', 'redirect' => $redirect];
}

function pengadaan_po_mark_selesai(mysqli $conn, int $poId, string $invoiceParent, int $invoicePembelianId = 0): bool
{
    $invEsc = mysqli_real_escape_string($conn, $invoiceParent);
    $invIdSql = $invoicePembelianId > 0 ? (string) $invoicePembelianId : 'NULL';

    $ok = (bool) mysqli_query($conn, "
        UPDATE pengadaan_po SET
            status = 'selesai',
            pembelian_invoice_parent = '$invEsc',
            invoice_pembelian_id = $invIdSql,
            updated_at = NOW()
        WHERE id = $poId
    ");

    if ($ok) {
        mysqli_query($conn, "
            UPDATE pengadaan_request SET status = 'selesai', updated_at = NOW()
            WHERE po_id = $poId AND status = 'diproses'
        ");
    }

    return $ok;
}

/** @return array<int,array<string,mixed>> */
function pengadaan_po_list_active(mysqli $conn, int $limit = 20): array
{
    pengadaan_po_ensure_tables($conn);
    $limit = max(1, min(100, $limit));
    $list = [];
    $res = mysqli_query($conn, "
        SELECT p.*,
               (SELECT COUNT(*) FROM pengadaan_po_line l WHERE l.po_id = p.id) AS jml_item,
               (SELECT SUM(l.qty_po) FROM pengadaan_po_line l WHERE l.po_id = p.id) AS total_qty_po
        FROM pengadaan_po p
        WHERE p.status NOT IN ('selesai','batal')
        ORDER BY p.id DESC
        LIMIT $limit
    ");
    if (!$res) {
        return $list;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $list[] = $row;
    }

    return $list;
}

/**
 * Hapus PO aktif (hard delete draft/batal-able; non-selesai).
 * Melepas pengadaan_request yang terikat.
 *
 * @return array{ok:bool,message:string}
 */
function pengadaan_po_delete(mysqli $conn, int $poId): array
{
    pengadaan_po_ensure_tables($conn);
    $poId = (int) $poId;
    if ($poId < 1) {
        return ['ok' => false, 'message' => 'PO tidak valid'];
    }
    $po = pengadaan_po_get($conn, $poId);
    if (!$po) {
        return ['ok' => false, 'message' => 'PO tidak ditemukan'];
    }
    $status = (string) ($po['status'] ?? '');
    if ($status === 'selesai') {
        return ['ok' => false, 'message' => 'PO yang sudah selesai tidak bisa dihapus'];
    }

    mysqli_query($conn, "
        UPDATE pengadaan_request SET
            po_id = NULL,
            status = IF(status = 'diproses', 'pending', status),
            updated_at = NOW()
        WHERE po_id = $poId
    ");
    mysqli_query($conn, "DELETE FROM pengadaan_po_line WHERE po_id = $poId");
    $ok = (bool) mysqli_query($conn, "DELETE FROM pengadaan_po WHERE id = $poId LIMIT 1");

    return [
        'ok' => $ok,
        'message' => $ok ? 'PO dihapus dari daftar' : ('Gagal hapus: ' . mysqli_error($conn)),
    ];
}

function pengadaan_po_supplier_edit_url(int $supplierId, string $kodeSuplier = ''): string
{
    $kodeSuplier = trim($kodeSuplier);

    if ($supplierId > 0) {
        return 'supplier-edit?id=' . $supplierId . ($kodeSuplier !== '' ? ('&kode_suplier=' . rawurlencode($kodeSuplier)) : '');
    }

    return $kodeSuplier !== '' ? ('supplier-add?kode_suplier=' . rawurlencode($kodeSuplier)) : 'supplier-add';
}

/** @return array{ok:bool,has_wa:bool,supplier_id:int,supplier_nama:string,edit_url:string,message?:string} */
function pengadaan_po_supplier_wa_check(mysqli $conn, string $kodeSuplier, int $cabang = 0): array
{
    $supplier = pengadaan_po_resolve_supplier($conn, $kodeSuplier, $cabang);
    if (!$supplier) {
        return [
            'ok' => false,
            'has_wa' => false,
            'supplier_id' => 0,
            'supplier_nama' => '',
            'edit_url' => pengadaan_po_supplier_edit_url(0, $kodeSuplier),
            'alert_message' => 'Kode supplier "' . $kodeSuplier . '" belum terhubung ke master Supplier. Daftarkan supplier dan isi Kode Supplier (sama dengan kode di master barang).',
            'message' => 'Kode supplier "' . $kodeSuplier . '" belum terhubung ke master Supplier.',
            'kode_suplier' => $kodeSuplier,
        ];
    }

    $supplierId = (int) ($supplier['supplier_id'] ?? 0);
    $phone = pengadaan_po_wa_phone((string) ($supplier['supplier_wa'] ?? ''));

    return [
        'ok' => true,
        'has_wa' => $phone !== '',
        'supplier_id' => $supplierId,
        'supplier_nama' => (string) ($supplier['supplier_nama'] ?? ''),
        'edit_url' => pengadaan_po_supplier_edit_url($supplierId, $kodeSuplier),
        'alert_message' => $phone !== '' ? '' : ('Supplier "' . ($supplier['supplier_nama'] ?? $kodeSuplier) . '" belum punya nomor WhatsApp.'),
        'message' => $phone !== '' ? '' : ('Supplier "' . ($supplier['supplier_nama'] ?? $kodeSuplier) . '" belum punya nomor WhatsApp.'),
        'kode_suplier' => $kodeSuplier,
    ];
}

/**
 * @param int[] $requestIds
 * @return array{ok:bool, missing: array<int,array<string,mixed>>}
 */
function pengadaan_po_validate_requests_supplier_wa(mysqli $conn, array $requestIds, int $cabang = 0): array
{
    $requestIds = array_values(array_unique(array_filter(array_map('intval', $requestIds))));
    if ($requestIds === []) {
        return ['ok' => true, 'missing' => []];
    }

    $idsStr = implode(',', $requestIds);
    $res = mysqli_query($conn, "
        SELECT DISTINCT kode_suplier FROM pengadaan_request
        WHERE id IN ($idsStr) AND kode_suplier != '' AND kode_suplier IS NOT NULL
    ");
    $missing = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $ks = trim((string) ($row['kode_suplier'] ?? ''));
            if ($ks === '') {
                continue;
            }
            $check = pengadaan_po_supplier_wa_check($conn, $ks, $cabang);
            if (!$check['has_wa']) {
                $missing[] = [
                    'kode_suplier' => $ks,
                    'supplier_nama' => $check['supplier_nama'],
                    'edit_url' => $check['edit_url'],
                    'message' => $check['message'] ?? 'WA belum diisi',
                ];
            }
        }
    }

    return ['ok' => $missing === [], 'missing' => $missing];
}

function pengadaan_po_wa_data(mysqli $conn, int $poId): array
{
    $po = pengadaan_po_get($conn, $poId);
    if (!$po) {
        return ['ok' => false, 'message' => 'PO tidak ditemukan'];
    }

    $lines = pengadaan_po_get_lines($conn, $poId);
    $supplier = null;
    $supplierId = (int) ($po['supplier_id'] ?? 0);
    if ($supplierId > 0) {
        $res = mysqli_query($conn, "SELECT * FROM supplier WHERE supplier_id = $supplierId LIMIT 1");
        $supplier = $res ? mysqli_fetch_assoc($res) : null;
    }
    if (!$supplier) {
        $supplier = pengadaan_po_resolve_supplier($conn, (string) ($po['kode_suplier'] ?? ''), 0);
        if ($supplier && empty($po['supplier_id'])) {
            $sid = (int) ($supplier['supplier_id'] ?? 0);
            if ($sid > 0) {
                mysqli_query($conn, "UPDATE pengadaan_po SET supplier_id = $sid WHERE id = $poId");
            }
        }
    }

    $phone = $supplier ? pengadaan_po_wa_phone((string) ($supplier['supplier_wa'] ?? '')) : '';
    $message = pengadaan_po_build_wa_message($po, $lines);
    $link = pengadaan_po_wa_link($phone, $message);
    $supplierId = $supplier ? (int) ($supplier['supplier_id'] ?? 0) : 0;
    $kodeSuplier = (string) ($po['kode_suplier'] ?? '');
    $supplierNama = $supplier ? (string) ($supplier['supplier_nama'] ?? '') : $kodeSuplier;

    if ($supplierId > 0) {
        $editUrl = pengadaan_po_supplier_edit_url($supplierId, $kodeSuplier);
        $alertMessage = 'Supplier "' . $supplierNama . '" belum punya nomor WhatsApp.';
    } else {
        $editUrl = pengadaan_po_supplier_edit_url(0, $kodeSuplier);
        $alertMessage = 'Kode supplier "' . $kodeSuplier . '" belum terhubung. Isi Kode Supplier di master Supplier (sama dengan kode di master barang).';
    }

    return [
        'ok' => true,
        'phone' => $phone,
        'message' => $message,
        'alert_message' => $alertMessage,
        'link' => $link,
        'has_wa' => $phone !== '',
        'supplier_id' => $supplierId,
        'supplier_nama' => $supplierNama,
        'edit_url' => $editUrl,
        'kode_suplier' => $kodeSuplier,
    ];
}
