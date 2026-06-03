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
    $map = pengadaan_gudang_cabang_toko();

    return $map[$cabangId] ?? ('Cabang ' . $cabangId);
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
    $sql = @file_get_contents(__DIR__ . '/../db/migration_pengadaan_request.sql');
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
 * Scan stok cabang & upsert request otomatis.
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
    $activeKeys = [];

    foreach (array_keys(pengadaan_gudang_cabang_toko()) as $cabangId) {
        $cab = (int) $cabangId;
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
            $stokCabang = (float) ($row['stok_cabang'] ?? 0);
            $stokGudang = (float) ($row['stok_gudang'] ?? 0);
            $soldQty = (float) ($row['sold_qty'] ?? 0);
            $avgHarian = $soldQty / $analisisHari;
            $coverHari = $avgHarian > 0 ? ($stokCabang / $avgHarian) : null;

            $needs = false;
            if ($stokCabang <= 0 && $avgHarian > 0) {
                $needs = true;
            } elseif ($coverHari !== null && $coverHari < $targetCover && $avgHarian > 0) {
                $needs = true;
            } elseif ($avgHarian > 0 && $stokCabang < ($avgHarian * 3)) {
                $needs = true;
            }

            if (!$needs) {
                continue;
            }

            $qtyDisarankan = max(0, (int) ceil(($targetCover * $avgHarian) - $stokCabang));
            if ($qtyDisarankan < 1 && $stokCabang <= 0) {
                $qtyDisarankan = max(1, (int) ceil($avgHarian * 7));
            }

            $prioritas = pengadaan_gudang_prioritas($stokCabang, $coverHari, $avgHarian, $targetCover);
            $kode = (string) ($row['barang_kode'] ?? '');
            $activeKeys[] = $cab . '|' . $kode;

            $barangId = (int) ($row['barang_id'] ?? 0);
            $namaEsc = mysqli_real_escape_string($conn, (string) ($row['barang_nama'] ?? ''));
            $kodeEsc = mysqli_real_escape_string($conn, $kode);
            $suplierEsc = mysqli_real_escape_string($conn, (string) ($row['kode_suplier'] ?? ''));
            $coverSql = $coverHari === null ? 'NULL' : (string) round($coverHari, 2);

            $cek = mysqli_query($conn, "
                SELECT id, status FROM pengadaan_request
                WHERE cabang_id = $cab AND barang_kode = '$kodeEsc' LIMIT 1
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
                            stok_cabang = $stokCabang,
                            stok_gudang = $stokGudang,
                            avg_jual_harian = $avgHarian,
                            cover_hari = $coverSql,
                            qty_disarankan = $qtyDisarankan,
                            qty_diminta = $qtyDisarankan,
                            prioritas = '$prioritas',
                            status = 'pending',
                            sumber = 'auto',
                            catatan = NULL,
                            diproses_by = NULL,
                            diproses_at = NULL,
                            updated_at = NOW()
                        WHERE id = $id
                    ");
                    $stats['updated']++;
                } else {
                    mysqli_query($conn, "
                        UPDATE pengadaan_request SET
                            barang_id = $barangId,
                            barang_nama = '$namaEsc',
                            kode_suplier = '$suplierEsc',
                            stok_cabang = $stokCabang,
                            stok_gudang = $stokGudang,
                            avg_jual_harian = $avgHarian,
                            cover_hari = $coverSql,
                            qty_disarankan = $qtyDisarankan,
                            qty_diminta = IF(status = 'pending', $qtyDisarankan, qty_diminta),
                            prioritas = '$prioritas',
                            updated_at = NOW()
                        WHERE id = $id
                    ");
                    $stats['updated']++;
                }
            } else {
                mysqli_query($conn, "
                    INSERT INTO pengadaan_request (
                        cabang_id, barang_id, barang_kode, barang_nama, kode_suplier,
                        stok_cabang, stok_gudang, avg_jual_harian, cover_hari,
                        qty_disarankan, qty_diminta, prioritas, status, sumber
                    ) VALUES (
                        $cab, $barangId, '$kodeEsc', '$namaEsc', '$suplierEsc',
                        $stokCabang, $stokGudang, $avgHarian, $coverSql,
                        $qtyDisarankan, $qtyDisarankan, '$prioritas', 'pending', 'auto'
                    )
                ");
                $stats['created']++;
            }
        }
    }

    // Tutup request auto yang sudah tidak perlu
    $qOpen = mysqli_query($conn, "
        SELECT id, cabang_id, barang_kode FROM pengadaan_request
        WHERE status IN ('pending','diproses') AND sumber = 'auto'
    ");
    if ($qOpen) {
        while ($r = mysqli_fetch_assoc($qOpen)) {
            $key = (int) $r['cabang_id'] . '|' . (string) $r['barang_kode'];
            if (!in_array($key, $activeKeys, true)) {
                $id = (int) $r['id'];
                mysqli_query($conn, "
                    UPDATE pengadaan_request SET
                        status = 'selesai',
                        catatan = 'Stok cabang sudah cukup (penutupan otomatis)',
                        updated_at = NOW()
                    WHERE id = $id
                ");
                $stats['resolved']++;
            }
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

    $res = mysqli_query($conn, "
        SELECT cabang_id, prioritas, status, COUNT(*) AS c
        FROM pengadaan_request
        WHERE status IN ('pending','diproses')
        GROUP BY cabang_id, prioritas, status
    ");
    if (!$res) {
        return $summary;
    }

    while ($row = mysqli_fetch_assoc($res)) {
        $cab = (int) ($row['cabang_id'] ?? 0);
        $c = (int) ($row['c'] ?? 0);
        $status = (string) ($row['status'] ?? '');
        $prioritas = (string) ($row['prioritas'] ?? '');

        if ($status === 'pending') {
            $summary['pending'] += $c;
            if ($prioritas === 'kritis') {
                $summary['kritis'] += $c;
            }
            if (isset($summary['by_cabang'][$cab])) {
                $summary['by_cabang'][$cab] += $c;
            }
        } elseif ($status === 'diproses') {
            $summary['diproses'] += $c;
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
