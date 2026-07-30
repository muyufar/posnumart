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

/**
 * Cabang aktif untuk neraca konsolidasi: Nugrosir (0) + toko aktif (bukan arsip).
 *
 * @return list<int>
 */
function labaAccrual_neraca_cabang_konsolidasi($conn)
{
    if (!function_exists('cabang_arsip_ids')) {
        require_once __DIR__ . '/cabang-arsip-lib.php';
    }

    $arsip = cabang_arsip_ids($conn);
    $ids = [0];
    $q = mysqli_query($conn, "
        SELECT toko_cabang
        FROM toko
        WHERE toko_cabang IS NOT NULL
        ORDER BY toko_cabang ASC
    ");
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $id = (int) ($row['toko_cabang'] ?? -1);
            if ($id < 0 || $id === 0) {
                continue;
            }
            if (in_array($id, $arsip, true)) {
                continue;
            }
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * Klasifikasi pos aktiva sesuai struktur COA modern (SAK ETAP / PSAK-style).
 * Prefix dari labaAccrual_neraca_extract_prefix (mis. 1-110, 1-210).
 */
function labaAccrual_neraca_klasifikasi_aktiva($prefix)
{
    $prefix = (string) $prefix;
    if (preg_match('/^1-(1[0-9]{2}|0?1[0-9]{2})$/', $prefix) || preg_match('/^1-(100|101|102|103|110|120|130|140|150|155|170|180|190|196|197)/', $prefix)) {
        return 'lancar';
    }
    if (preg_match('/^1-(2[0-9]{2}|5[0-9]{2}|107|108|109)/', $prefix)) {
        return 'tidak_lancar';
    }
    if (preg_match('/^1-/', $prefix)) {
        $num = 0;
        if (preg_match('/^1-(\d+)/', $prefix, $m)) {
            $num = (int) $m[1];
        }
        if ($num >= 100 && $num < 200) {
            return 'lancar';
        }
        if ($num >= 200) {
            return 'tidak_lancar';
        }
    }
    return 'lainnya';
}

/**
 * Klasifikasi kewajiban: jangka pendek vs panjang.
 */
function labaAccrual_neraca_klasifikasi_pasiva($prefix, $kode_akun = '')
{
    $prefix = (string) $prefix;
    $kode = (string) $kode_akun;
    if (preg_match('/^2-2/', $prefix) || preg_match('/^2-2/', $kode)) {
        return 'jangka_panjang';
    }
    return 'jangka_pendek';
}

/**
 * Apakah kode akun header agregat (hindari double-count anak + induk).
 */
function labaAccrual_neraca_is_header_kode($kode_akun, $level = null)
{
    if ($level !== null && (int) $level > 0 && (int) $level <= 2) {
        return true;
    }
    $kode = trim((string) $kode_akun);
    if (preg_match('/^\d+-0{3,4}$/', $kode)) {
        return true;
    }
    // 1-1100, 1-1200, 2-1100 — header grup
    if (preg_match('/^\d+-\d{1,2}00$/', $kode) && !preg_match('/^\d+-\d{3}[1-9]\d*$/', $kode)) {
        return true;
    }
    return false;
}

/**
 * Map kode akun → cabang pemilik (akun lokasi, hindari double-count pusat+cabang).
 * Null = akun bersama (boleh dijumlah antar cabang), mis. 1-1202 BRI per outlet.
 *
 * @return array<string, int>
 */
function labaAccrual_neraca_kode_pemilik_map()
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [];

    if (function_exists('akun_link_kas_tunai_map')) {
        foreach (akun_link_kas_tunai_map() as $cbg => $info) {
            $kode = (string) ($info['kode'] ?? '');
            if ($kode !== '') {
                $map[$kode] = (int) $cbg;
            }
        }
    } else {
        $map['1-1101'] = 0;
        $map['1-1102'] = 1;
        $map['1-1103'] = 3;
        $map['1-1104'] = 2;
        $map['1-1105'] = 5;
    }

    // Legacy BRI per cabang (kode unik)
    $legacyBri = [
        '1-1203' => 1,
        '1-1204' => 3,
        '1-1205' => 2,
        '1-1206' => 5,
    ];
    foreach ($legacyBri as $kode => $cbg) {
        $map[$kode] = $cbg;
    }

    // Bank BNU pusat
    $map['1-1201'] = 0;

    // Piutang dagang terpusat
    $map['1-1301'] = 0;
    $map['1-1300'] = 0;

    return $map;
}

/**
 * ID akun yang punya anak (bukan leaf) per cabang.
 *
 * @return array<int, true>
 */
function labaAccrual_neraca_parent_ids_set($conn, $cabang)
{
    $cabang_escaped = mysqli_real_escape_string($conn, (string) $cabang);
    $set = [];
    $chk = @mysqli_query($conn, "SHOW COLUMNS FROM laba_kategori LIKE 'parent_id'");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        return $set;
    }
    $q = mysqli_query($conn, "
        SELECT DISTINCT parent_id
        FROM laba_kategori
        WHERE cabang = '$cabang_escaped'
          AND parent_id IS NOT NULL
          AND parent_id > 0
    ");
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $set[(int) $row['parent_id']] = true;
        }
    }
    return $set;
}

/**
 * Apakah baris COA boleh masuk konsolidasi (leaf + bukan header).
 */
function labaAccrual_neraca_boleh_konsolidasi(array $item, $level, array $parent_ids_set)
{
    $kid = (int) ($item['id'] ?? 0);
    if ($kid > 0 && isset($parent_ids_set[$kid])) {
        return false; // punya anak → saldo induk jangan digabung
    }
    if (labaAccrual_neraca_is_header_kode($item['kode_akun'] ?? '', $level)) {
        return false;
    }
    // Level 3 yang masih punya pola header (xx00) sudah tertangkap; level 1–3 non-leaf sudah tertangkap.
    // Jika level 3 tanpa anak, boleh (akun operasional).
    return true;
}

/**
 * Laba/(rugi) s.d. tanggal dari COA pendapatan & beban (setelah reverse mutasi pasca cut-off).
 * Hanya akun leaf agar tidak double-count header.
 */
function labaAccrual_neraca_laba_rugi_sd_tanggal($conn, $cabang, $tanggal_neraca)
{
    $cabang_escaped = mysqli_real_escape_string($conn, (string) $cabang);
    $parent_ids = labaAccrual_neraca_parent_ids_set($conn, $cabang);
    $has_level = false;
    $chk = @mysqli_query($conn, "SHOW COLUMNS FROM laba_kategori LIKE 'level'");
    if ($chk && mysqli_num_rows($chk) > 0) {
        $has_level = true;
    }

    $level_select = $has_level ? ', lk.level' : '';
    $q = mysqli_query($conn, "
        SELECT
            lk.id,
            lk.kategori,
            lk.tipe_akun,
            lk.kode_akun,
            COALESCE(lk.saldo, 0) AS saldo_coa
            $level_select
        FROM laba_kategori lk
        WHERE lk.cabang = '$cabang_escaped'
          AND LOWER(TRIM(lk.kategori)) IN ('pendapatan', 'beban')
    ");

    $pendapatan = 0.0;
    $beban = 0.0;
    if (!$q) {
        return ['pendapatan' => 0.0, 'beban' => 0.0, 'laba_rugi' => 0.0];
    }

    $pemilik = labaAccrual_neraca_kode_pemilik_map();

    while ($row = mysqli_fetch_assoc($q)) {
        $kid = (int) ($row['id'] ?? 0);
        if ($kid > 0 && isset($parent_ids[$kid])) {
            continue;
        }
        $level = $has_level && isset($row['level']) ? (int) $row['level'] : null;
        if (labaAccrual_neraca_is_header_kode($row['kode_akun'] ?? '', $level)) {
            continue;
        }

        $kode = trim((string) ($row['kode_akun'] ?? ''));
        // Akun lokasi milik cabang lain: jangan hitung di cabang ini
        if ($kode !== '' && isset($pemilik[$kode]) && (int) $pemilik[$kode] !== (int) $cabang) {
            continue;
        }

        $kat = strtolower(trim((string) ($row['kategori'] ?? '')));
        $tipe = (string) ($row['tipe_akun'] ?? '');
        $saldo_coa = (float) ($row['saldo_coa'] ?? 0);
        $mutasi = labaAccrual_neraca_mutasi_setelah_tanggal($conn, $kid, $cabang, $tanggal_neraca);

        if ($kat === 'pendapatan') {
            $delta = labaAccrual_neraca_hitung_saldo_akhir('modal', $tipe !== '' ? $tipe : 'kredit', 0, $mutasi['total_masuk'], $mutasi['total_keluar']);
            $pendapatan += ($saldo_coa - $delta);
        } else {
            $delta = labaAccrual_neraca_hitung_saldo_akhir('aktiva', $tipe !== '' ? $tipe : 'debit', 0, $mutasi['total_masuk'], $mutasi['total_keluar']);
            $beban += ($saldo_coa - $delta);
        }
    }

    return [
        'pendapatan' => $pendapatan,
        'beban' => $beban,
        'laba_rugi' => $pendapatan - $beban,
    ];
}

/**
 * Merge item neraca per kode_akun (satu entitas ekonomi).
 *
 * @param array<string, array> $bucket
 * @param array $item
 * @param int $cabang
 */
function labaAccrual_neraca_merge_item(array &$bucket, array $item, $cabang)
{
    $kode = trim((string) ($item['kode_akun'] ?? '-'));
    if ($kode === '') {
        $kode = '-';
    }
    $saldo = (float) ($item['saldo_akhir'] ?? 0);
    if (!isset($bucket[$kode])) {
        $bucket[$kode] = [
            'id' => (int) ($item['id'] ?? 0),
            'kode_akun' => $kode,
            'prefix_group' => $item['prefix_group'] ?? labaAccrual_neraca_extract_prefix($kode),
            'name' => (string) ($item['name'] ?? '-'),
            'tipe_akun' => (string) ($item['tipe_akun'] ?? '-'),
            'saldo_akhir' => 0.0,
            'per_cabang' => [],
        ];
    }
    if ((int) $cabang === 0 && trim((string) ($item['name'] ?? '')) !== '' && trim((string) ($item['name'] ?? '')) !== '-') {
        $bucket[$kode]['name'] = trim((string) $item['name']);
    } elseif ($bucket[$kode]['name'] === '-' && trim((string) ($item['name'] ?? '')) !== '') {
        $bucket[$kode]['name'] = trim((string) $item['name']);
    }
    // Saldo digabung nanti di eliminasi (pemilik / sum); di sini hanya kumpulkan per cabang
    $bucket[$kode]['per_cabang'][(int) $cabang] = ($bucket[$kode]['per_cabang'][(int) $cabang] ?? 0.0) + $saldo;
    $bucket[$kode]['saldo_akhir'] = array_sum($bucket[$kode]['per_cabang']);
}

/**
 * Hitung saldo konsolidasi satu akun dari sebaran per cabang.
 * - Akun berlokasi: hanya cabang pemilik (fallback pusat jika pemilik kosong — data MYOB).
 * - Akun bersama: jumlah semua cabang.
 *
 * @param array<int, float> $per
 * @return array{saldo: float, per_cabang: array<int, float>, eliminasi_jumlah: float, alasan: string|null}
 */
function labaAccrual_neraca_saldo_dari_per_cabang($kode, array $per)
{
    $pemilik_map = labaAccrual_neraca_kode_pemilik_map();
    $kode = (string) $kode;
    $per = array_map('floatval', $per);

    if (isset($pemilik_map[$kode])) {
        $owner = (int) $pemilik_map[$kode];
        $saldo_owner = (float) ($per[$owner] ?? 0);
        $saldo_pusat = (float) ($per[0] ?? 0);
        $dipakai = $saldo_owner;
        $sumber = $owner;
        if (abs($saldo_owner) < 0.005 && abs($saldo_pusat) >= 0.005 && $owner !== 0) {
            // Saldo operasional cabang kosong, tapi pusat masih pegang buku MYOB-style
            $dipakai = $saldo_pusat;
            $sumber = 0;
        }
        $total_mentah = array_sum($per);
        $elim = $total_mentah - $dipakai;
        $alasan = null;
        if (abs($elim) >= 0.005) {
            $alasan = 'Akun lokasi: diambil dari cabang pemilik'
                . ($sumber === 0 ? ' (fallback pusat)' : '')
                . ', saldo cabang lain dieliminasi agar tidak double-count';
        }
        return [
            'saldo' => $dipakai,
            'per_cabang' => [$sumber => $dipakai],
            'eliminasi_jumlah' => $elim,
            'alasan' => $alasan,
        ];
    }

    // Akun bersama (contoh 1-1202): jumlahkan semua — tiap cabang punya buku sendiri
    return [
        'saldo' => array_sum($per),
        'per_cabang' => $per,
        'eliminasi_jumlah' => 0.0,
        'alasan' => null,
    ];
}

/**
 * Eliminasi antar-cabang untuk penyajian konsolidasi yang valid.
 *
 * @param array{aktiva: array, pasiva: array, modal: array} $neraca
 * @return array{neraca: array, eliminasi: list<array>}
 */
function labaAccrual_neraca_terapkan_eliminasi(array $neraca)
{
    $eliminasi = [];

    foreach (['aktiva', 'pasiva', 'modal'] as $side) {
        $filtered = [];
        foreach ($neraca[$side] as $item) {
            $kode = (string) ($item['kode_akun'] ?? '');
            $per = $item['per_cabang'] ?? [];

            // Persediaan COA diganti valuasi stok
            if (preg_match('/^1-15/', $kode) || $kode === '1-103') {
                $eliminasi[] = [
                    'kode_akun' => $kode,
                    'name' => $item['name'] ?? 'Persediaan',
                    'alasan' => 'Diganti valuasi persediaan operasional per tanggal (sumber stok valid)',
                    'jumlah' => (float) array_sum($per),
                ];
                continue;
            }

            $resolved = labaAccrual_neraca_saldo_dari_per_cabang($kode, $per);
            if ($resolved['alasan'] !== null && abs($resolved['eliminasi_jumlah']) >= 0.005) {
                $eliminasi[] = [
                    'kode_akun' => $kode,
                    'name' => $item['name'] ?? $kode,
                    'alasan' => $resolved['alasan'],
                    'jumlah' => $resolved['eliminasi_jumlah'],
                ];
            }

            $item['saldo_akhir'] = $resolved['saldo'];
            $item['per_cabang'] = $resolved['per_cabang'];
            if (abs($resolved['saldo']) < 0.005) {
                continue;
            }
            $filtered[] = $item;
        }
        $neraca[$side] = $filtered;
    }

    return ['neraca' => $neraca, 'eliminasi' => $eliminasi];
}

/**
 * Total neraca satu cabang setelah filter leaf/header/pemilik (untuk ringkasan valid).
 *
 * @return array{total_aktiva: float, total_pasiva: float, total_modal: float, items: array}
 */
function labaAccrual_neraca_totals_terfilter($conn, $cabang, $tanggal_neraca)
{
    $part = labaAccrual_neraca_build($conn, (string) $cabang, $tanggal_neraca);
    $parent_ids = labaAccrual_neraca_parent_ids_set($conn, $cabang);
    $pemilik = labaAccrual_neraca_kode_pemilik_map();

    $has_level = false;
    $level_by_id = [];
    $chk = @mysqli_query($conn, "SHOW COLUMNS FROM laba_kategori LIKE 'level'");
    if ($chk && mysqli_num_rows($chk) > 0) {
        $has_level = true;
        $cab_esc = mysqli_real_escape_string($conn, (string) $cabang);
        $ql = mysqli_query($conn, "SELECT id, level FROM laba_kategori WHERE cabang = '$cab_esc'");
        if ($ql) {
            while ($lr = mysqli_fetch_assoc($ql)) {
                $level_by_id[(int) $lr['id']] = isset($lr['level']) ? (int) $lr['level'] : null;
            }
        }
    }

    $totals = ['aktiva' => 0.0, 'pasiva' => 0.0, 'modal' => 0.0];
    $items = ['aktiva' => [], 'pasiva' => [], 'modal' => []];

    foreach (['aktiva', 'pasiva', 'modal'] as $side) {
        foreach ($part['neraca'][$side] as $item) {
            $kid = (int) ($item['id'] ?? 0);
            $level = $level_by_id[$kid] ?? null;
            if (!labaAccrual_neraca_boleh_konsolidasi($item, $level, $parent_ids)) {
                continue;
            }
            $kode = trim((string) ($item['kode_akun'] ?? ''));
            if ($kode !== '' && isset($pemilik[$kode]) && (int) $pemilik[$kode] !== (int) $cabang) {
                // Akun milik cabang lain yang nyangkut di buku ini — jangan masuk ringkasan unit
                continue;
            }
            if (preg_match('/^1-15/', $kode) || $kode === '1-103') {
                continue; // persediaan dari stok
            }
            $saldo = (float) ($item['saldo_akhir'] ?? 0);
            if (abs($saldo) < 0.005) {
                continue;
            }
            $totals[$side] += $saldo;
            $items[$side][] = $item;
        }
    }

    return [
        'total_aktiva' => $totals['aktiva'],
        'total_pasiva' => $totals['pasiva'],
        'total_modal' => $totals['modal'],
        'items' => $items,
        'jumlah_kategori' => (int) ($part['jumlah_kategori_ditemukan'] ?? 0),
    ];
}

/**
 * Bangun neraca konsolidasi: Nugrosir sebagai pusat + seluruh cabang aktif.
 *
 * @param list<int>|null $cabang_ids
 * @return array
 */
function labaAccrual_neraca_build_konsolidasi($conn, $tanggal_neraca, $cabang_ids = null)
{
    if (!function_exists('so_laporan_nilai_persediaan_pada_tanggal')) {
        require_once __DIR__ . '/stock-opname-laporan-lib.php';
    }
    if (!function_exists('akun_link_kas_tunai_map')) {
        $akunLink = __DIR__ . '/akun-link-lib.php';
        if (is_file($akunLink)) {
            require_once $akunLink;
        }
    }

    if ($cabang_ids === null) {
        $cabang_ids = labaAccrual_neraca_cabang_konsolidasi($conn);
    }
    $cabang_ids = array_values(array_unique(array_map('intval', $cabang_ids)));
    if ($cabang_ids === []) {
        $cabang_ids = [0];
    }

    $merged = ['aktiva' => [], 'pasiva' => [], 'modal' => []];
    $per_cabang_summary = [];
    $laba_rugi_total = 0.0;
    $laba_rugi_per_cabang = [];
    $jumlah_kategori_ditemukan = 0;
    $nama_cabang = [];
    $double_count_dicegah = 0.0;

    $q_toko = mysqli_query($conn, 'SELECT toko_cabang, toko_nama FROM toko');
    if ($q_toko) {
        while ($t = mysqli_fetch_assoc($q_toko)) {
            $nama_cabang[(int) $t['toko_cabang']] = (string) $t['toko_nama'];
        }
    }
    if (!isset($nama_cabang[0])) {
        $nama_cabang[0] = 'Nugrosir (Pusat)';
    }

    foreach ($cabang_ids as $cabang) {
        $filtered = labaAccrual_neraca_totals_terfilter($conn, $cabang, $tanggal_neraca);
        $jumlah_kategori_ditemukan += (int) ($filtered['jumlah_kategori'] ?? 0);

        foreach (['aktiva', 'pasiva', 'modal'] as $side) {
            foreach ($filtered['items'][$side] as $item) {
                labaAccrual_neraca_merge_item($merged[$side], $item, $cabang);
            }
        }

        $persediaan_cbg = max(0.0, so_laporan_nilai_persediaan_pada_tanggal($conn, (int) $cabang, $tanggal_neraca));
        $lr = labaAccrual_neraca_laba_rugi_sd_tanggal($conn, (string) $cabang, $tanggal_neraca);
        $laba_rugi_per_cabang[(int) $cabang] = $lr;
        $laba_rugi_total += (float) $lr['laba_rugi'];

        $per_cabang_summary[(int) $cabang] = [
            'cabang' => (int) $cabang,
            'nama' => $nama_cabang[(int) $cabang] ?? ('Cabang ' . $cabang),
            'total_aktiva' => (float) $filtered['total_aktiva'] + $persediaan_cbg,
            'total_pasiva' => (float) $filtered['total_pasiva'],
            'total_modal' => (float) $filtered['total_modal'],
            'persediaan' => $persediaan_cbg,
            'laba_rugi' => (float) $lr['laba_rugi'],
            'is_pusat' => ((int) $cabang === 0),
        ];
    }

    $neraca = [
        'aktiva' => array_values($merged['aktiva']),
        'pasiva' => array_values($merged['pasiva']),
        'modal' => array_values($merged['modal']),
    ];

    $elim_result = labaAccrual_neraca_terapkan_eliminasi($neraca);
    $neraca = $elim_result['neraca'];
    $eliminasi = $elim_result['eliminasi'];
    foreach ($eliminasi as $e) {
        $double_count_dicegah += abs((float) ($e['jumlah'] ?? 0));
    }

    $persediaan_total = 0.0;
    $persediaan_per_cabang = [];
    foreach ($cabang_ids as $cabang) {
        $nil = max(0.0, so_laporan_nilai_persediaan_pada_tanggal($conn, (int) $cabang, $tanggal_neraca));
        $persediaan_per_cabang[(int) $cabang] = $nil;
        $persediaan_total += $nil;
    }

    // Laba tahun berjalan ke ekuitas jika belum tercakup di 3-9xxx
    $modal_laba_tercatat = 0.0;
    foreach ($neraca['modal'] as $item) {
        $kode = (string) ($item['kode_akun'] ?? '');
        if ($kode === '3-9000' || $kode === '3-9999' || preg_match('/^3-9/', $kode)) {
            $modal_laba_tercatat += (float) ($item['saldo_akhir'] ?? 0);
        }
    }
    $laba_injeksi = 0.0;
    if (abs($laba_rugi_total - $modal_laba_tercatat) > 1.0) {
        if (abs($modal_laba_tercatat) < 0.005) {
            $laba_injeksi = $laba_rugi_total;
        } else {
            $laba_injeksi = $laba_rugi_total - $modal_laba_tercatat;
        }
        if (abs($laba_injeksi) >= 0.005) {
            $per_cbg_laba = [];
            foreach ($laba_rugi_per_cabang as $cbgId => $lrRow) {
                $per_cbg_laba[(int) $cbgId] = (float) ($lrRow['laba_rugi'] ?? 0);
            }
            $neraca['modal'][] = [
                'id' => 0,
                'kode_akun' => '3-9100',
                'prefix_group' => '3-910',
                'name' => 'Laba/(Rugi) Tahun Berjalan (Konsolidasi)',
                'tipe_akun' => 'kredit',
                'saldo_akhir' => $laba_injeksi,
                'per_cabang' => $per_cbg_laba,
            ];
        }
    }

    $total_aktiva = 0.0;
    $total_pasiva = 0.0;
    $total_modal = 0.0;
    $total_harta_lancar = 0.0;
    $total_harta_tetap = 0.0;
    $total_liabilitas_jp = 0.0;
    $total_liabilitas_jpj = 0.0;

    $aktiva_items = [];
    foreach ($neraca['aktiva'] as $item) {
        if (abs((float) $item['saldo_akhir']) < 0.005) {
            continue;
        }
        $aktiva_items[] = $item;
        $total_aktiva += (float) $item['saldo_akhir'];
        $klas = labaAccrual_neraca_klasifikasi_aktiva($item['prefix_group'] ?? '');
        if ($klas === 'tidak_lancar') {
            $total_harta_tetap += (float) $item['saldo_akhir'];
        } else {
            $total_harta_lancar += (float) $item['saldo_akhir'];
        }
    }
    if ($persediaan_total > 0) {
        $total_aktiva += $persediaan_total;
        $total_harta_lancar += $persediaan_total;
    }

    $pasiva_items = [];
    foreach ($neraca['pasiva'] as $item) {
        if (abs((float) $item['saldo_akhir']) < 0.005) {
            continue;
        }
        $pasiva_items[] = $item;
        $total_pasiva += (float) $item['saldo_akhir'];
        $klas = labaAccrual_neraca_klasifikasi_pasiva($item['prefix_group'] ?? '', $item['kode_akun'] ?? '');
        if ($klas === 'jangka_panjang') {
            $total_liabilitas_jpj += (float) $item['saldo_akhir'];
        } else {
            $total_liabilitas_jp += (float) $item['saldo_akhir'];
        }
    }

    $modal_items = [];
    foreach ($neraca['modal'] as $item) {
        if (abs((float) $item['saldo_akhir']) < 0.005) {
            continue;
        }
        $modal_items[] = $item;
        $total_modal += (float) $item['saldo_akhir'];
    }

    $sort_kode = static function ($a, $b) {
        return strcmp((string) ($a['kode_akun'] ?? ''), (string) ($b['kode_akun'] ?? ''));
    };
    usort($aktiva_items, $sort_kode);
    usort($pasiva_items, $sort_kode);
    usort($modal_items, $sort_kode);

    $neraca_out = [
        'aktiva' => $aktiva_items,
        'pasiva' => $pasiva_items,
        'modal' => $modal_items,
    ];

    return [
        'neraca' => $neraca_out,
        'total_aktiva' => $total_aktiva,
        'total_pasiva' => $total_pasiva,
        'total_modal' => $total_modal,
        'total_pasiva_modal' => $total_pasiva + $total_modal,
        'aktiva_grouped' => labaAccrual_neraca_group_by_prefix($aktiva_items),
        'pasiva_grouped' => labaAccrual_neraca_group_by_prefix($pasiva_items),
        'modal_grouped' => labaAccrual_neraca_group_by_prefix($modal_items),
        'total_harta_lancar' => $total_harta_lancar,
        'total_harta_tetap' => $total_harta_tetap,
        'total_liabilitas_jangka_pendek' => $total_liabilitas_jp,
        'total_liabilitas_jangka_panjang' => $total_liabilitas_jpj,
        'persediaan_total' => $persediaan_total,
        'persediaan_per_cabang' => $persediaan_per_cabang,
        'eliminasi' => $eliminasi,
        'double_count_dicegah' => $double_count_dicegah,
        'per_cabang_summary' => $per_cabang_summary,
        'cabang_ids' => $cabang_ids,
        'nama_cabang' => $nama_cabang,
        'laba_rugi_total' => $laba_rugi_total,
        'laba_rugi_per_cabang' => $laba_rugi_per_cabang,
        'laba_tahun_berjalan_injeksi' => $laba_injeksi,
        'jumlah_kategori_ditemukan' => $jumlah_kategori_ditemukan,
        'pusat_nama' => $nama_cabang[0] ?? 'Nugrosir',
    ];
}
