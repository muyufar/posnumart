<?php

/**
 * Neraca accrual: snapshot per tanggal dari saldo COA (laba_kategori.saldo),
 * dengan penyesuaian entri Data Operasional (tabel laba) setelah tanggal cut-off.
 */

function labaAccrual_neraca_jumlah_sql_expr()
{
    return "CAST(REPLACE(REPLACE(REPLACE(l.jumlah, '.', ''), ',', ''), ' ', '') AS DECIMAL(18,2))";
}

function labaAccrual_neraca_hitung_saldo_akhir($kategori, $tipe_akun, $saldo_awal, $total_masuk, $total_keluar)
{
    $saldo_awal = (float) $saldo_awal;
    $total_masuk = (float) $total_masuk;
    $total_keluar = (float) $total_keluar;
    $tipe_akun = strtolower(trim((string) $tipe_akun));

    if ($kategori === 'aktiva') {
        if ($tipe_akun === 'debit') {
            return $saldo_awal + $total_masuk - $total_keluar;
        }
        return $saldo_awal - $total_masuk + $total_keluar;
    }

    if ($kategori === 'pasiva') {
        if ($tipe_akun === 'kredit') {
            return $saldo_awal - $total_masuk + $total_keluar;
        }
        return $saldo_awal + $total_masuk - $total_keluar;
    }

    if ($kategori === 'modal') {
        if ($tipe_akun === 'kredit') {
            return $saldo_awal + $total_masuk - $total_keluar;
        }
        return $saldo_awal - $total_masuk + $total_keluar;
    }

    return $saldo_awal;
}

function labaAccrual_neraca_mutasi_setelah_tanggal($conn, $kat_id, $cabang, $tanggal_neraca)
{
    $kat_id = (int) $kat_id;
    $cabang = mysqli_real_escape_string($conn, (string) $cabang);
    $tanggal_neraca = mysqli_real_escape_string($conn, (string) $tanggal_neraca);
    $jumlah_expr = labaAccrual_neraca_jumlah_sql_expr();

    $q = mysqli_query($conn, "
        SELECT
            COALESCE(SUM(CASE
                WHEN l.tipe = 0 AND l.jumlah IS NOT NULL AND l.jumlah != '' AND l.jumlah != '0'
                THEN $jumlah_expr
                ELSE 0
            END), 0) AS total_masuk,
            COALESCE(SUM(CASE
                WHEN l.tipe = 1 AND l.jumlah IS NOT NULL AND l.jumlah != '' AND l.jumlah != '0'
                THEN $jumlah_expr
                ELSE 0
            END), 0) AS total_keluar
        FROM laba l
        WHERE CAST(l.kategori AS UNSIGNED) = $kat_id
          AND l.cabang = '$cabang'
          AND l.date > '$tanggal_neraca 23:59:59'
    ");

    if (!$q) {
        return ['total_masuk' => 0.0, 'total_keluar' => 0.0];
    }

    $row = mysqli_fetch_assoc($q);
    return [
        'total_masuk' => (float) ($row['total_masuk'] ?? 0),
        'total_keluar' => (float) ($row['total_keluar'] ?? 0),
    ];
}

function labaAccrual_neraca_extract_prefix($kode_akun)
{
    $kode_akun = trim((string) $kode_akun);
    if ($kode_akun === '' || $kode_akun === '-') {
        return '-';
    }
    if (preg_match('/^(\d+)-(\d{3})/', $kode_akun, $matches)) {
        return $matches[1] . '-' . $matches[2];
    }
    if (preg_match('/^(\d+)-/', $kode_akun, $matches)) {
        return $matches[1] . '-';
    }
    return '-';
}

function labaAccrual_neraca_group_by_prefix(array $items)
{
    $grouped = [];
    foreach ($items as $item) {
        $prefix = $item['prefix_group'] ?? '-';
        if (!isset($grouped[$prefix])) {
            $grouped[$prefix] = [
                'name' => $prefix,
                'items' => [],
                'total' => 0.0,
            ];
        }
        $grouped[$prefix]['items'][] = $item;
        $grouped[$prefix]['total'] += (float) ($item['saldo_akhir'] ?? 0);
    }
    return $grouped;
}

/**
 * @return array{
 *   neraca: array{aktiva: array, pasiva: array, modal: array},
 *   total_aktiva: float,
 *   total_pasiva: float,
 *   total_modal: float,
 *   total_pasiva_modal: float,
 *   aktiva_grouped: array,
 *   pasiva_grouped: array,
 *   modal_grouped: array,
 *   total_harta_lancar: float,
 *   total_harta_tetap: float,
 *   jumlah_kategori_ditemukan: int
 * }
 */
function labaAccrual_neraca_build($conn, $cabang, $tanggal_neraca)
{
    $cabang_escaped = mysqli_real_escape_string($conn, (string) $cabang);

    $q_kategori = mysqli_query($conn, "
        SELECT
            lk.id,
            lk.name,
            lk.kode_akun,
            lk.kategori,
            lk.tipe_akun,
            COALESCE(lk.saldo, 0) AS saldo_coa
        FROM laba_kategori lk
        WHERE lk.cabang = '$cabang_escaped'
        ORDER BY lk.id ASC
    ");

    if (!$q_kategori) {
        throw new RuntimeException('Query neraca gagal: ' . mysqli_error($conn));
    }

    $neraca = ['aktiva' => [], 'pasiva' => [], 'modal' => []];
    $total_aktiva = 0.0;
    $total_pasiva = 0.0;
    $total_modal = 0.0;
    $jumlah_kategori_ditemukan = 0;

    while ($row = mysqli_fetch_assoc($q_kategori)) {
        $kategori_raw_lower = strtolower(trim((string) ($row['kategori'] ?? '')));
        if (!in_array($kategori_raw_lower, ['aktiva', 'pasiva', 'modal'], true)) {
            continue;
        }

        $jumlah_kategori_ditemukan++;
        $kat_id = (int) $row['id'];
        $tipe_akun = (string) ($row['tipe_akun'] ?? '');
        $saldo_coa = (float) ($row['saldo_coa'] ?? 0);

        $mutasi_setelah = labaAccrual_neraca_mutasi_setelah_tanggal($conn, $kat_id, $cabang, $tanggal_neraca);
        $delta_setelah = labaAccrual_neraca_hitung_saldo_akhir(
            $kategori_raw_lower,
            $tipe_akun,
            0,
            $mutasi_setelah['total_masuk'],
            $mutasi_setelah['total_keluar']
        );
        $saldo_akhir = $saldo_coa - $delta_setelah;

        $kode_akun = trim((string) ($row['kode_akun'] ?? ''));
        if ($kode_akun === '') {
            $kode_akun = '-';
        }

        $data = [
            'id' => $kat_id,
            'kode_akun' => $kode_akun,
            'prefix_group' => labaAccrual_neraca_extract_prefix($kode_akun),
            'name' => trim((string) ($row['name'] ?? '')) !== '' ? trim((string) $row['name']) : '-',
            'tipe_akun' => $tipe_akun !== '' ? $tipe_akun : '-',
            'saldo_coa' => $saldo_coa,
            'saldo_akhir' => $saldo_akhir,
        ];

        if (abs($saldo_akhir) < 0.005) {
            continue;
        }

        if ($kategori_raw_lower === 'aktiva') {
            $neraca['aktiva'][] = $data;
            $total_aktiva += $saldo_akhir;
        } elseif ($kategori_raw_lower === 'pasiva') {
            $neraca['pasiva'][] = $data;
            $total_pasiva += $saldo_akhir;
        } else {
            $neraca['modal'][] = $data;
            $total_modal += $saldo_akhir;
        }
    }

    $aktiva_grouped = labaAccrual_neraca_group_by_prefix($neraca['aktiva']);
    $pasiva_grouped = labaAccrual_neraca_group_by_prefix($neraca['pasiva']);
    $modal_grouped = labaAccrual_neraca_group_by_prefix($neraca['modal']);

    $total_harta_lancar = 0.0;
    $total_harta_tetap = 0.0;
    foreach ($aktiva_grouped as $prefix => $group) {
        if (preg_match('/^1-(100|101|102|110|120|130|200|201|202)/', $prefix)) {
            $total_harta_lancar += $group['total'];
        } elseif (preg_match('/^1-(107|108|109)/', $prefix)) {
            $total_harta_tetap += $group['total'];
        }
    }

    return [
        'neraca' => $neraca,
        'total_aktiva' => $total_aktiva,
        'total_pasiva' => $total_pasiva,
        'total_modal' => $total_modal,
        'total_pasiva_modal' => $total_pasiva + $total_modal,
        'aktiva_grouped' => $aktiva_grouped,
        'pasiva_grouped' => $pasiva_grouped,
        'modal_grouped' => $modal_grouped,
        'total_harta_lancar' => $total_harta_lancar,
        'total_harta_tetap' => $total_harta_tetap,
        'jumlah_kategori_ditemukan' => $jumlah_kategori_ditemukan,
    ];
}
