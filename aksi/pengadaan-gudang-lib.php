<?php

/**
 * Pusat pengadaan gudang — deteksi otomatis kebutuhan stok cabang.
 */

function pengadaan_gudang_cabang_toko(): array
{
    return [
        1 => 'Dukun',
        2 => 'Pakis',
        3 => 'PP Srumbung',
        5 => 'Tegalrejo',
    ];
}

function pengadaan_gudang_cabang_label(int $cabangId): string
{
    if ($cabangId <= 0) {
        return 'Semua toko';
    }
    $map = pengadaan_gudang_cabang_toko();

    return $map[$cabangId] ?? ('Cabang ' . $cabangId);
}

/**
 * Cabang yang dijumlahkan ke stok akumulasi (gudang + semua toko, tanpa returan).
 *
 * @return list<int>
 */
function pengadaan_gudang_cabang_stok_ids(): array
{
    return [0, 1, 2, 3, 5];
}

/**
 * Stok akumulasi live dari tabel barang — sama sumber dengan Data Stock Keseluruhan
 * (SUM barang_stock gudang + Dukun + Pakis + PP Srumbung + Tegalrejo).
 *
 * @param list<string> $kodes
 * @return array<string,float> map barang_kode => stok_total
 */
function pengadaan_gudang_fetch_stok_akumulasi(mysqli $conn, array $kodes): array
{
    $map = [];
    $kodes = array_values(array_unique(array_filter(array_map(static function ($k) {
        return trim((string) $k);
    }, $kodes))));
    if ($kodes === []) {
        return $map;
    }

    $cabIn = implode(',', array_map('intval', pengadaan_gudang_cabang_stok_ids()));
    foreach (array_chunk($kodes, 200) as $chunk) {
        $in = [];
        foreach ($chunk as $k) {
            $in[] = "'" . mysqli_real_escape_string($conn, $k) . "'";
        }
        $inSql = implode(',', $in);
        $res = mysqli_query($conn, "
            SELECT barang_kode,
                   SUM(COALESCE(CAST(NULLIF(TRIM(barang_stock), '') AS DECIMAL(18,4)), 0)) AS stok_total
            FROM barang
            WHERE barang_status = '1'
              AND barang_cabang IN ($cabIn)
              AND barang_kode IN ($inSql)
            GROUP BY barang_kode
        ");
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $map[(string) $row['barang_kode']] = (float) ($row['stok_total'] ?? 0);
        }
    }

    return $map;
}

function pengadaan_gudang_can_access(int $userCabang, string $levelLogin): bool
{
    if ($levelLogin === 'kasir' || $levelLogin === 'kurir') {
        return false;
    }

    return $userCabang < 1;
}

function pengadaan_gudang_ensure_table(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $sql = @file_get_contents(__DIR__ . '/../db/migration_pengadaan_po.sql');
    if ($sql === false) {
        $sql = @file_get_contents(__DIR__ . '/../db/migration_pengadaan_request.sql');
    }
    if ($sql === false) {
        return;
    }
    foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || stripos($stmt, 'CREATE TABLE') === false) {
            continue;
        }
        @mysqli_query($conn, $stmt);
    }

    // Index untuk list DataTables (penting di live — tanpa ini query bisa sangat lambat)
    $indexes = [
        'idx_pgd_req_status_cabang' => '(`status`, `cabang_id`)',
        'idx_pgd_req_kode_suplier' => '(`kode_suplier`)',
        'idx_pgd_req_barang_kode' => '(`barang_kode`)',
        'idx_pgd_req_list' => '(`cabang_id`, `status`, `prioritas`)',
    ];
    try {
        $tbl = mysqli_query($conn, "SHOW TABLES LIKE 'pengadaan_request'");
        if ($tbl && mysqli_num_rows($tbl) > 0) {
            foreach ($indexes as $name => $cols) {
                $chk = mysqli_query($conn, "SHOW INDEX FROM pengadaan_request WHERE Key_name = '" . mysqli_real_escape_string($conn, $name) . "'");
                if ($chk && mysqli_num_rows($chk) === 0) {
                    mysqli_query($conn, "ALTER TABLE pengadaan_request ADD INDEX `$name` $cols");
                }
            }
        }
    } catch (Throwable $e) {
        // Index gagal dibuat tidak boleh bikin halaman blank
    }
}

/** Format ringan kode supplier + nama sales + perusahaan (tanpa query). */
function pengadaan_gudang_format_supplier_cell(string $kode, string $nama = '', string $company = ''): string
{
    $kode = trim($kode);
    $nama = trim($nama);
    $company = trim($company);
    if ($kode === '') {
        return '<span class="text-muted">-</span>';
    }
    $html = '<strong>' . htmlspecialchars($kode, ENT_QUOTES, 'UTF-8') . '</strong>';
    $parts = [];
    if ($nama !== '') {
        $parts[] = $nama;
    }
    if ($company !== '' && strcasecmp($company, $nama) !== 0) {
        $parts[] = $company;
    }
    if ($parts !== []) {
        $html .= '<br><small class="text-muted">' . htmlspecialchars(implode(' — ', $parts), ENT_QUOTES, 'UTF-8') . '</small>';
    }

    return $html;
}

function pengadaan_gudang_prioritas(float $stokCabang, ?float $coverHari, float $avgHarian, int $targetCover): string
{
    if ($stokCabang <= 0 || ($coverHari !== null && $coverHari < 3)) {
        return 'kritis';
    }
    if ($coverHari !== null && $coverHari < $targetCover) {
        return 'perlu_isi';
    }
    if ($avgHarian > 0 && $stokCabang < ($avgHarian * 3)) {
        return 'perlu_isi';
    }

    return 'perlu_isi';
}

function pengadaan_gudang_prioritas_badge(string $prioritas): string
{
    if ($prioritas === 'kritis') {
        return '<span class="badge badge-danger">KRITIS</span>';
    }

    return '<span class="badge badge-warning">Perlu Isi</span>';
}

function pengadaan_gudang_status_badge(string $status): string
{
    $map = [
        'pending' => '<span class="badge badge-danger">Menunggu</span>',
        'diproses' => '<span class="badge badge-info">Diproses</span>',
        'selesai' => '<span class="badge badge-success">Selesai</span>',
        'ditolak' => '<span class="badge badge-secondary">Ditolak</span>',
    ];

    return $map[$status] ?? htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
}

/**
 * Scan stok seluruh toko & upsert request otomatis (akumulasi per kode barang).
 * cabang_id = 0 artinya permintaan terakumulasi dari semua toko.
 *
 * @return array{scanned:int, created:int, updated:int, resolved:int}
 */
function pengadaan_gudang_sync(mysqli $conn, int $analisisHari = 30, int $targetCover = 14): array
{
    pengadaan_gudang_ensure_table($conn);

    $analisisHari = max(7, min(90, $analisisHari));
    $targetCover = max(7, min(60, $targetCover));

    $to = date('Y-m-d');
    $from = date('Y-m-d', strtotime('-' . ($analisisHari - 1) . ' days'));
    $fromEsc = mysqli_real_escape_string($conn, $from);
    $toEsc = mysqli_real_escape_string($conn, $to);

    $stockPcsExpr = include __DIR__ . '/arus-stock-stock-pcs-expr.php';
    $soldPcsExpr = include __DIR__ . '/arus-stock-sold-pcs-expr.php';

    $stats = ['scanned' => 0, 'created' => 0, 'updated' => 0, 'resolved' => 0];
    $cabangIds = array_map('intval', array_keys(pengadaan_gudang_cabang_toko()));

    // Akumulasi per kode barang dari seluruh toko (stok + penjualan)
    $agg = [];
    foreach ($cabangIds as $cab) {
        $sql = "
            SELECT
                b.barang_id,
                b.barang_kode,
                b.barang_nama,
                b.kode_suplier,
                ($stockPcsExpr) AS stok_cabang,
                COALESCE(g.stok_gudang, 0) AS stok_gudang,
                COALESCE(s.sold_qty, 0) AS sold_qty
            FROM barang b
            LEFT JOIN (
                SELECT b2.barang_kode, SUM($soldPcsExpr) AS sold_qty
                FROM penjualan p
                INNER JOIN barang b2 ON b2.barang_id = p.barang_id
                WHERE b2.barang_cabang = $cab
                  AND p.penjualan_date BETWEEN '$fromEsc' AND '$toEsc'
                GROUP BY b2.barang_kode
            ) s ON s.barang_kode = b.barang_kode
            LEFT JOIN (
                SELECT b.barang_kode, SUM($stockPcsExpr) AS stok_gudang
                FROM barang b
                WHERE b.barang_cabang = 0 AND b.barang_status = '1'
                GROUP BY b.barang_kode
            ) g ON g.barang_kode = b.barang_kode
            WHERE b.barang_cabang = $cab AND b.barang_status = '1'
        ";

        $res = mysqli_query($conn, $sql);
        if (!$res) {
            continue;
        }

        while ($row = mysqli_fetch_assoc($res)) {
            $stats['scanned']++;
            $kode = trim((string) ($row['barang_kode'] ?? ''));
            if ($kode === '') {
                continue;
            }
            if (!isset($agg[$kode])) {
                $agg[$kode] = [
                    'barang_id' => (int) ($row['barang_id'] ?? 0),
                    'barang_nama' => (string) ($row['barang_nama'] ?? ''),
                    'kode_suplier' => (string) ($row['kode_suplier'] ?? ''),
                    'stok_toko' => 0.0,
                    'stok_gudang' => (float) ($row['stok_gudang'] ?? 0),
                    'sold_qty' => 0.0,
                ];
            }
            $agg[$kode]['stok_toko'] += (float) ($row['stok_cabang'] ?? 0);
            $agg[$kode]['sold_qty'] += (float) ($row['sold_qty'] ?? 0);
            $agg[$kode]['stok_gudang'] = max($agg[$kode]['stok_gudang'], (float) ($row['stok_gudang'] ?? 0));
            if ($agg[$kode]['kode_suplier'] === '' && trim((string) ($row['kode_suplier'] ?? '')) !== '') {
                $agg[$kode]['kode_suplier'] = (string) $row['kode_suplier'];
            }
            if ($agg[$kode]['barang_nama'] === '' && trim((string) ($row['barang_nama'] ?? '')) !== '') {
                $agg[$kode]['barang_nama'] = (string) $row['barang_nama'];
            }
        }
    }

    // Prefer barang_id dari master gudang (cabang 0)
    if ($agg !== []) {
        $kodeList = array_keys($agg);
        foreach (array_chunk($kodeList, 200) as $chunk) {
            $in = [];
            foreach ($chunk as $k) {
                $in[] = "'" . mysqli_real_escape_string($conn, $k) . "'";
            }
            $inSql = implode(',', $in);
            $qg = mysqli_query($conn, "
                SELECT barang_id, barang_kode, barang_nama, kode_suplier
                FROM barang
                WHERE barang_cabang = 0 AND barang_status = '1' AND barang_kode IN ($inSql)
            ");
            while ($qg && ($gr = mysqli_fetch_assoc($qg))) {
                $gk = (string) ($gr['barang_kode'] ?? '');
                if (!isset($agg[$gk])) {
                    continue;
                }
                $agg[$gk]['barang_id'] = (int) ($gr['barang_id'] ?? 0);
                if (trim((string) ($gr['barang_nama'] ?? '')) !== '') {
                    $agg[$gk]['barang_nama'] = (string) $gr['barang_nama'];
                }
                if ($agg[$gk]['kode_suplier'] === '' && trim((string) ($gr['kode_suplier'] ?? '')) !== '') {
                    $agg[$gk]['kode_suplier'] = (string) $gr['kode_suplier'];
                }
            }
        }
    }

    // Timpa stok dengan angka live dari master barang (gudang+toko),
    // sama seperti halaman Data Stock Keseluruhan — jangan andalkan stok di request lama.
    $liveStokMap = pengadaan_gudang_fetch_stok_akumulasi($conn, array_keys($agg));

    $activeKodes = [];
    foreach ($agg as $kode => $item) {
        $stokTotal = isset($liveStokMap[$kode])
            ? (float) $liveStokMap[$kode]
            : ((float) $item['stok_toko'] + (float) $item['stok_gudang']);
        $soldQty = (float) $item['sold_qty'];
        $avgHarian = $soldQty / $analisisHari;
        $coverHari = $avgHarian > 0 ? ($stokTotal / $avgHarian) : null;

        $needs = false;
        if ($stokTotal <= 0 && $avgHarian > 0) {
            $needs = true;
        } elseif ($coverHari !== null && $coverHari < $targetCover && $avgHarian > 0) {
            $needs = true;
        } elseif ($avgHarian > 0 && $stokTotal < ($avgHarian * 3)) {
            $needs = true;
        }
        if (!$needs) {
            continue;
        }

        $qtyDisarankan = max(0, (int) ceil(($targetCover * $avgHarian) - $stokTotal));
        if ($qtyDisarankan < 1 && $stokTotal <= 0) {
            $qtyDisarankan = max(1, (int) ceil($avgHarian * 7));
        }

        $prioritas = pengadaan_gudang_prioritas($stokTotal, $coverHari, $avgHarian, $targetCover);
        $activeKodes[$kode] = true;

        $barangId = (int) $item['barang_id'];
        $namaEsc = mysqli_real_escape_string($conn, (string) $item['barang_nama']);
        $kodeEsc = mysqli_real_escape_string($conn, $kode);
        $suplierEsc = mysqli_real_escape_string($conn, (string) $item['kode_suplier']);
        $coverSql = $coverHari === null ? 'NULL' : (string) round($coverHari, 2);

        // Satu baris per kode barang (akumulasi seluruh toko) → cabang_id = 0
        $cek = mysqli_query($conn, "
            SELECT id, status FROM pengadaan_request
            WHERE cabang_id = 0 AND barang_kode = '$kodeEsc' LIMIT 1
        ");
        $exist = $cek ? mysqli_fetch_assoc($cek) : null;

        if ($exist) {
            $status = (string) ($exist['status'] ?? 'pending');
            $id = (int) $exist['id'];
            if (in_array($status, ['selesai', 'ditolak'], true)) {
                mysqli_query($conn, "
                    UPDATE pengadaan_request SET
                        barang_id = $barangId,
                        barang_nama = '$namaEsc',
                        kode_suplier = '$suplierEsc',
                        stok_cabang = $stokTotal,
                        stok_gudang = 0,
                        avg_jual_harian = $avgHarian,
                        cover_hari = $coverSql,
                        qty_disarankan = $qtyDisarankan,
                        qty_diminta = $qtyDisarankan,
                        prioritas = '$prioritas',
                        status = 'pending',
                        sumber = 'auto',
                        catatan = 'Akumulasi seluruh toko + gudang',
                        diproses_by = NULL,
                        diproses_at = NULL,
                        po_id = NULL,
                        updated_at = NOW()
                    WHERE id = $id
                ");
            } else {
                mysqli_query($conn, "
                    UPDATE pengadaan_request SET
                        barang_id = $barangId,
                        barang_nama = '$namaEsc',
                        kode_suplier = '$suplierEsc',
                        stok_cabang = $stokTotal,
                        stok_gudang = 0,
                        avg_jual_harian = $avgHarian,
                        cover_hari = $coverSql,
                        qty_disarankan = $qtyDisarankan,
                        qty_diminta = IF(status = 'pending', $qtyDisarankan, qty_diminta),
                        prioritas = '$prioritas',
                        catatan = 'Akumulasi seluruh toko + gudang',
                        updated_at = NOW()
                    WHERE id = $id
                ");
            }
            $stats['updated']++;
        } else {
            mysqli_query($conn, "
                INSERT INTO pengadaan_request (
                    cabang_id, barang_id, barang_kode, barang_nama, kode_suplier,
                    stok_cabang, stok_gudang, avg_jual_harian, cover_hari,
                    qty_disarankan, qty_diminta, prioritas, status, sumber, catatan
                ) VALUES (
                    0, $barangId, '$kodeEsc', '$namaEsc', '$suplierEsc',
                    $stokTotal, 0, $avgHarian, $coverSql,
                    $qtyDisarankan, $qtyDisarankan, '$prioritas', 'pending', 'auto',
                    'Akumulasi seluruh toko + gudang'
                )
            ");
            $stats['created']++;
        }
    }

    // Tutup request akumulasi (cabang_id=0) yang sudah tidak perlu
    $qOpen = mysqli_query($conn, "
        SELECT id, barang_kode FROM pengadaan_request
        WHERE status IN ('pending','diproses') AND sumber = 'auto' AND cabang_id = 0
          AND (po_id IS NULL OR po_id = 0)
    ");
    if ($qOpen) {
        while ($r = mysqli_fetch_assoc($qOpen)) {
            $kode = (string) ($r['barang_kode'] ?? '');
            if ($kode === '' || isset($activeKodes[$kode])) {
                continue;
            }
            $id = (int) $r['id'];
            mysqli_query($conn, "
                UPDATE pengadaan_request SET
                    status = 'selesai',
                    catatan = 'Stok toko (akumulasi) sudah cukup — penutupan otomatis',
                    updated_at = NOW()
                WHERE id = $id
            ");
            $stats['resolved']++;
        }
    }

    // Tutup sisa request per-toko lama agar list tidak dobel
    $qLegacy = mysqli_query($conn, "
        SELECT id FROM pengadaan_request
        WHERE status IN ('pending','diproses') AND cabang_id > 0
          AND (po_id IS NULL OR po_id = 0)
    ");
    if ($qLegacy) {
        while ($r = mysqli_fetch_assoc($qLegacy)) {
            $id = (int) $r['id'];
            mysqli_query($conn, "
                UPDATE pengadaan_request SET
                    status = 'selesai',
                    catatan = 'Digantikan oleh permintaan akumulasi seluruh toko',
                    updated_at = NOW()
                WHERE id = $id
            ");
            $stats['resolved']++;
        }
    }

    return $stats;
}

/** @return array{pending:int,kritis:int,diproses:int,by_cabang:array<int,int>} */
function pengadaan_gudang_summary(mysqli $conn): array
{
    pengadaan_gudang_ensure_table($conn);

    $summary = [
        'pending' => 0,
        'kritis' => 0,
        'diproses' => 0,
        'by_cabang' => [],
    ];

    foreach (array_keys(pengadaan_gudang_cabang_toko()) as $cab) {
        $summary['by_cabang'][(int) $cab] = 0;
    }

    // Angka utama = permintaan akumulasi (cabang_id = 0)
    $res = mysqli_query($conn, "
        SELECT prioritas, status, COUNT(*) AS c
        FROM pengadaan_request
        WHERE status IN ('pending','diproses') AND cabang_id = 0
        GROUP BY prioritas, status
    ");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $c = (int) ($row['c'] ?? 0);
            $status = (string) ($row['status'] ?? '');
            $prioritas = (string) ($row['prioritas'] ?? '');
            if ($status === 'pending') {
                $summary['pending'] += $c;
                if ($prioritas === 'kritis') {
                    $summary['kritis'] += $c;
                }
            } elseif ($status === 'diproses') {
                $summary['diproses'] += $c;
            }
        }
    }

    // Breakdown legacy per toko (jika masih ada sisa data lama)
    $resCab = mysqli_query($conn, "
        SELECT cabang_id, COUNT(*) AS c
        FROM pengadaan_request
        WHERE status = 'pending' AND cabang_id > 0
        GROUP BY cabang_id
    ");
    if ($resCab) {
        while ($row = mysqli_fetch_assoc($resCab)) {
            $cab = (int) ($row['cabang_id'] ?? 0);
            if (isset($summary['by_cabang'][$cab])) {
                $summary['by_cabang'][$cab] = (int) ($row['c'] ?? 0);
            }
        }
    }

    return $summary;
}

/**
 * Summary dari data per-toko lama yang di-akumulasi on-the-fly (sebelum Scan Ulang).
 *
 * @return array{pending:int,kritis:int,diproses:int,by_cabang:array<int,int>}
 */
function pengadaan_gudang_summary_aggregated(mysqli $conn): array
{
    pengadaan_gudang_ensure_table($conn);
    $summary = [
        'pending' => 0,
        'kritis' => 0,
        'diproses' => 0,
        'by_cabang' => [],
    ];
    foreach (array_keys(pengadaan_gudang_cabang_toko()) as $cab) {
        $summary['by_cabang'][(int) $cab] = 0;
    }

    $res = mysqli_query($conn, "
        SELECT
            barang_kode,
            CASE WHEN SUM(prioritas = 'kritis') > 0 THEN 'kritis' ELSE 'perlu_isi' END AS prioritas,
            CASE
                WHEN SUM(status = 'diproses') > 0 THEN 'diproses'
                WHEN SUM(status = 'pending') > 0 THEN 'pending'
                ELSE 'lain'
            END AS status
        FROM pengadaan_request
        WHERE status IN ('pending','diproses') AND cabang_id > 0
        GROUP BY barang_kode
    ");
    if (!$res) {
        return $summary;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $status = (string) ($row['status'] ?? '');
        $prioritas = (string) ($row['prioritas'] ?? '');
        if ($status === 'pending') {
            $summary['pending']++;
            if ($prioritas === 'kritis') {
                $summary['kritis']++;
            }
        } elseif ($status === 'diproses') {
            $summary['diproses']++;
        }
    }

    return $summary;
}

function pengadaan_gudang_json_out(array $payload): void
{
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
