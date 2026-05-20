<?php

/**
 * Library laporan pergantian shift — agregasi penjualan per kasir.
 */

/** Cabang 1+ memakai pergantian shift; cabang 0 = laporan harian penuh (tanpa shift). */
function shift_laporan_pakai_pergantian_shift(int $cabang): bool
{
    return $cabang >= 1;
}

/** Cabang 0 = NU Grosir / gudang pusat — ada penjualan piutang. */
function shift_laporan_cabang_nugrosir(int $cabang): bool
{
    return $cabang < 1;
}

function shift_laporan_is_harian(string $shift, int $cabang = 1): bool
{
    return $cabang < 1 || shift_laporan_normalize_shift($shift, $cabang) === 'harian';
}

function shift_laporan_table_exists(mysqli $conn): bool
{
    $r = mysqli_query($conn, "SHOW TABLES LIKE 'shift_laporan'");

    return $r && mysqli_num_rows($r) > 0;
}

function shift_laporan_column_exists(mysqli $conn, string $column): bool
{
    $column = preg_replace('/[^a-z0-9_]/', '', $column);
    if ($column === '' || !shift_laporan_table_exists($conn)) {
        return false;
    }
    $r = mysqli_query($conn, "SHOW COLUMNS FROM shift_laporan LIKE '$column'");

    return $r && mysqli_num_rows($r) > 0;
}

/** @throws RuntimeException */
function shift_laporan_db_query(mysqli $conn, string $sql): mysqli_result|bool
{
    $result = mysqli_query($conn, $sql);
    if ($result === false) {
        throw new RuntimeException(mysqli_error($conn) ?: 'Query database gagal');
    }

    return $result;
}

function shift_laporan_db_begin(mysqli $conn): void
{
    if (!mysqli_begin_transaction($conn)) {
        shift_laporan_db_query($conn, 'START TRANSACTION');
    }
}

function shift_laporan_db_commit(mysqli $conn): void
{
    if (!mysqli_commit($conn)) {
        throw new RuntimeException(mysqli_error($conn) ?: 'Commit gagal');
    }
}

function shift_laporan_db_rollback(mysqli $conn): void
{
    mysqli_rollback($conn);
}

function shift_laporan_ensure_tables(mysqli $conn): void
{
    $sql = file_get_contents(__DIR__ . '/../db/migration_shift_laporan.sql');
    if ($sql !== false) {
        foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || stripos($stmt, 'CREATE TABLE') === false) {
                continue;
            }
            @mysqli_query($conn, $stmt);
        }
    }
    shift_laporan_ensure_schema_columns($conn);
}

function shift_laporan_ensure_schema_columns(mysqli $conn): void
{
    if (!shift_laporan_table_exists($conn)) {
        return;
    }
    shift_laporan_ensure_ttd_columns($conn);
    shift_laporan_ensure_jam_columns($conn);
}

function shift_laporan_ensure_jam_columns(mysqli $conn): void
{
    if (!shift_laporan_table_exists($conn)) {
        return;
    }

    $columns = [
        'jam_mulai' => 'TIME NULL',
        'jam_selesai' => 'TIME NULL',
    ];

    foreach ($columns as $name => $definition) {
        if (!shift_laporan_column_exists($conn, $name)) {
            @mysqli_query($conn, "ALTER TABLE shift_laporan ADD COLUMN `$name` $definition");
        }
    }
}

function shift_laporan_ensure_ttd_columns(mysqli $conn): void
{
    if (!shift_laporan_table_exists($conn)) {
        return;
    }

    $columns = [
        'ttd_kp_akt' => 'MEDIUMTEXT NULL',
        'ttd_kasir1' => 'MEDIUMTEXT NULL',
        'ttd_kasir2' => 'MEDIUMTEXT NULL',
        'ttd_kp_akt_nama' => 'VARCHAR(255) NULL',
        'ttd_kasir1_nama' => 'VARCHAR(255) NULL',
        'ttd_kasir2_nama' => 'VARCHAR(255) NULL',
        'ttd_kp_akt_at' => 'DATETIME NULL',
        'ttd_kasir1_at' => 'DATETIME NULL',
        'ttd_kasir2_at' => 'DATETIME NULL',
        'ttd_kp_akt_by' => 'INT(11) NULL',
        'ttd_kasir1_by' => 'INT(11) NULL',
        'ttd_kasir2_by' => 'INT(11) NULL',
    ];

    foreach ($columns as $name => $definition) {
        if (!shift_laporan_column_exists($conn, $name)) {
            @mysqli_query($conn, "ALTER TABLE shift_laporan ADD COLUMN `$name` $definition");
        }
    }
}

/** @throws RuntimeException */
function shift_laporan_require_schema_ready(mysqli $conn): void
{
    shift_laporan_ensure_tables($conn);

    if (!shift_laporan_table_exists($conn)) {
        throw new RuntimeException('Tabel shift_laporan belum ada. Import db/migration_shift_laporan.sql ke database.');
    }

    $qk = mysqli_query($conn, "SHOW TABLES LIKE 'shift_laporan_kasir'");
    if (!$qk || mysqli_num_rows($qk) === 0) {
        throw new RuntimeException('Tabel shift_laporan_kasir belum ada. Import db/migration_shift_laporan.sql ke database.');
    }

    shift_laporan_ensure_schema_columns($conn);

    foreach (['jam_mulai', 'jam_selesai'] as $col) {
        if (!shift_laporan_column_exists($conn, $col)) {
            throw new RuntimeException(
                'Kolom ' . $col . ' belum ada di tabel shift_laporan. Jalankan patch SQL di folder db/migration_shift_laporan_patch.sql pada server live.'
            );
        }
    }
}

function shift_laporan_build_ttd_sql_part(mysqli $conn, array $ttdInput, string $role, string $colPrefix, int $userId, string $now): string
{
    if (!isset($ttdInput[$role]) || !is_array($ttdInput[$role])) {
        return '';
    }
    $slot = $ttdInput[$role];
    $parts = [];
    $image = shift_laporan_sanitize_ttd_image(isset($slot['image']) ? (string) $slot['image'] : null);
    if ($image !== null) {
        $imgEsc = mysqli_real_escape_string($conn, $image);
        $parts[] = "`$colPrefix` = '$imgEsc'";
        $parts[] = "`{$colPrefix}_at` = '$now'";
        $parts[] = "`{$colPrefix}_by` = $userId";
    } elseif (!empty($slot['clear'])) {
        $parts[] = "`$colPrefix` = NULL";
        $parts[] = "`{$colPrefix}_at` = NULL";
        $parts[] = "`{$colPrefix}_by` = NULL";
    }
    if (isset($slot['nama'])) {
        $namaEsc = mysqli_real_escape_string($conn, trim((string) $slot['nama']));
        $parts[] = "`{$colPrefix}_nama` = '$namaEsc'";
    }

    return $parts ? ', ' . implode(', ', $parts) : '';
}

/** @return string|null */
function shift_laporan_sanitize_ttd_image(?string $dataUrl): ?string
{
    if ($dataUrl === null || $dataUrl === '') {
        return null;
    }
    $dataUrl = trim($dataUrl);
    if (!preg_match('#^data:image/(png|jpeg);base64,#i', $dataUrl)) {
        return null;
    }
    if (strlen($dataUrl) > 600000) {
        return null;
    }

    return $dataUrl;
}

/**
 * @return array{kp_akt: array, kasir1: array, kasir2: array}
 */
function shift_laporan_format_ttd_row(?array $row, string $prefix): array
{
    if (!$row) {
        return ['image' => '', 'nama' => '', 'signed_at' => '', 'signed_by' => null];
    }

    $at = $row[$prefix . '_at'] ?? '';
    $signedAt = '';
    if ($at !== '' && $at !== null) {
        $ts = strtotime((string) $at);
        $signedAt = $ts ? date('d/m/Y H:i', $ts) : (string) $at;
    }

    return [
        'image' => (string) ($row[$prefix] ?? ''),
        'nama' => (string) ($row[$prefix . '_nama'] ?? ''),
        'signed_at' => $signedAt,
        'signed_by' => isset($row[$prefix . '_by']) ? (int) $row[$prefix . '_by'] : null,
    ];
}

/** @return array{pagi: array{jam_mulai: string, jam_selesai: string}, sore: array{jam_mulai: string, jam_selesai: string}} */
function shift_laporan_jam_definisi(): array
{
    return [
        'pagi' => ['jam_mulai' => '07:00:00', 'jam_selesai' => '13:59:59'],
        'sore' => ['jam_mulai' => '14:00:00', 'jam_selesai' => '20:59:59'],
    ];
}

/** Normalisasi nilai shift dari request (dukung legacy "malam", cabang 0 = harian). */
function shift_laporan_normalize_shift(string $shift, int $cabang = 1): string
{
    if ($cabang < 1) {
        return 'harian';
    }
    if ($shift === 'sore' || $shift === 'malam') {
        return 'sore';
    }

    return 'pagi';
}

function shift_laporan_label(string $shift, int $cabang = 1): string
{
    if (shift_laporan_is_harian($shift, $cabang)) {
        return '';
    }

    return shift_laporan_normalize_shift($shift, $cabang) === 'sore' ? 'SIANG' : 'PAGI';
}

/** @return string|null Format HH:MM:SS */
function shift_laporan_parse_jam(?string $jam): ?string
{
    $jam = trim((string) $jam);
    if ($jam === '') {
        return null;
    }
    if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $jam, $m)) {
        return null;
    }
    $h = (int) $m[1];
    $i = (int) $m[2];
    $s = isset($m[3]) ? (int) $m[3] : 0;
    if ($h < 0 || $h > 23 || $i < 0 || $i > 59 || $s < 0 || $s > 59) {
        return null;
    }

    return sprintf('%02d:%02d:%02d', $h, $i, $s);
}

/**
 * @return array{jam_mulai: string, jam_selesai: string}
 */
function shift_laporan_resolve_jam(string $shift, ?string $jamMulai = null, ?string $jamSelesai = null, int $cabang = 1): array
{
    if (shift_laporan_is_harian($shift, $cabang)) {
        return ['jam_mulai' => '00:00:00', 'jam_selesai' => '23:59:59'];
    }

    $defs = shift_laporan_jam_definisi();
    $shift = shift_laporan_normalize_shift($shift, $cabang);
    $mulai = shift_laporan_parse_jam($jamMulai) ?? $defs[$shift]['jam_mulai'];
    $selesai = shift_laporan_parse_jam($jamSelesai) ?? $defs[$shift]['jam_selesai'];

    return ['jam_mulai' => $mulai, 'jam_selesai' => $selesai];
}

function shift_laporan_jam_tampil(string $jamMulai, string $jamSelesai): string
{
    $fmt = static function (string $jam): string {
        $jam = shift_laporan_parse_jam($jam) ?? $jam;

        return substr($jam, 0, 5);
    };

    return $fmt($jamMulai) . ' – ' . $fmt($jamSelesai);
}

/**
 * Jam tersimpan per tanggal+shift, atau default shift.
 *
 * @return array{jam_mulai: string, jam_selesai: string, from_saved: bool}
 */
function shift_laporan_jam_dari_simpanan(?array $header, string $shift, int $cabang = 1): array
{
    if (shift_laporan_is_harian($shift, $cabang)) {
        $jam = shift_laporan_resolve_jam($shift, null, null, $cabang);

        return [
            'jam_mulai' => substr($jam['jam_mulai'], 0, 5),
            'jam_selesai' => substr($jam['jam_selesai'], 0, 5),
            'from_saved' => false,
        ];
    }

    if ($header && !empty($header['jam_mulai']) && !empty($header['jam_selesai'])) {
        return [
            'jam_mulai' => substr((string) $header['jam_mulai'], 0, 5),
            'jam_selesai' => substr((string) $header['jam_selesai'], 0, 5),
            'from_saved' => true,
        ];
    }

    $defs = shift_laporan_jam_definisi()[shift_laporan_normalize_shift($shift, $cabang)];

    return [
        'jam_mulai' => substr($defs['jam_mulai'], 0, 5),
        'jam_selesai' => substr($defs['jam_selesai'], 0, 5),
        'from_saved' => false,
    ];
}

/** Nilai shift di DB untuk query simpanan (termasuk data lama "malam"). */
function shift_laporan_shift_db_in_clause(string $shift, int $cabang = 1): string
{
    if ($cabang < 1) {
        return "('harian')";
    }

    $norm = shift_laporan_normalize_shift($shift, $cabang);
    if ($norm === 'sore') {
        return "('sore', 'malam')";
    }

    return "('pagi')";
}

function shift_laporan_parse_tgl_expr(): string
{
    return "STR_TO_DATE(invoice_tgl, '%d %M %Y %h:%i:%s %p')";
}

function shift_laporan_where_waktu(string $shift, ?string $jamMulai = null, ?string $jamSelesai = null, int $cabang = 1): string
{
    if (shift_laporan_is_harian($shift, $cabang)) {
        return '';
    }

    $jam = shift_laporan_resolve_jam($shift, $jamMulai, $jamSelesai, $cabang);
    $mulai = $jam['jam_mulai'];
    $selesai = $jam['jam_selesai'];
    $tglExpr = shift_laporan_parse_tgl_expr();

    return " AND TIME($tglExpr) >= '$mulai' AND TIME($tglExpr) <= '$selesai' ";
}

/** Ekspresi nominal dari kolom laba (sama seperti laba-bersih-laporan). */
function shift_laporan_nominal_laba_expr(string $alias = 'l'): string
{
    $jumlah = "$alias.jumlah";

    return "CAST(COALESCE(
        NULLIF($alias.total, 0),
        REPLACE(REPLACE(REPLACE($jumlah, '.', ''), ',', ''), ' ', '')
    ) AS UNSIGNED)";
}

/**
 * Filter waktu transaksi laba per shift (tanggal + jam).
 * Entri dengan jam 00:00:00 dihitung pada shift pagi.
 */
function shift_laporan_where_laba_tanggal_shift(string $tanggal, string $shift, ?string $jamMulai = null, ?string $jamSelesai = null, int $cabang = 1): string
{
    $tanggalEsc = addslashes($tanggal);

    if (shift_laporan_is_harian($shift, $cabang)) {
        return " AND DATE(l.date) = '$tanggalEsc' ";
    }

    $jam = shift_laporan_resolve_jam($shift, $jamMulai, $jamSelesai, $cabang);
    $shift = shift_laporan_normalize_shift($shift, $cabang);
    $mulai = $jam['jam_mulai'];
    $selesai = $jam['jam_selesai'];

    $rentang = "(l.date >= '$tanggalEsc $mulai' AND l.date <= '$tanggalEsc $selesai')";
    $tengahMalam = "(DATE(l.date) = '$tanggalEsc' AND TIME(l.date) = '00:00:00')";

    if ($shift === 'pagi') {
        return " AND ($rentang OR $tengahMalam) ";
    }

    return " AND $rentang ";
}

/**
 * Pengeluaran operasional dari tabel laba (laba-bersih-data), tipe = pengeluaran.
 *
 * @return list<array{id: string, urutan: int, keterangan: string, jumlah: int, kategori: string, pj: string, sumber: string}>
 */
function shift_laporan_ambil_pengeluaran_laba(mysqli $conn, int $cabang, string $tanggal, string $shift, ?string $jamMulai = null, ?string $jamSelesai = null): array
{
    $cabang = (int) $cabang;
    $tanggalEsc = mysqli_real_escape_string($conn, $tanggal);
    $whereShift = shift_laporan_where_laba_tanggal_shift($tanggalEsc, $shift, $jamMulai, $jamSelesai, $cabang);
    $nominalExpr = shift_laporan_nominal_laba_expr('l');

    $sql = "
        SELECT
            l.id,
            l.keterangan,
            l.name AS pj,
            $nominalExpr AS jumlah_nominal,
            COALESCE(lk.name, '') AS kategori_nama,
            l.date,
            l.created_at
        FROM laba l
        LEFT JOIN laba_kategori lk ON CAST(l.kategori AS UNSIGNED) = lk.id
        WHERE l.cabang = $cabang
          AND l.tipe = 1
          AND (
            l.jenis_transaksi IS NULL
            OR l.jenis_transaksi = ''
            OR l.jenis_transaksi = 'pengeluaran'
          )
          AND $nominalExpr > 0
          $whereShift
        ORDER BY l.date ASC, l.created_at ASC
    ";

    $hasil = [];
    $q = mysqli_query($conn, $sql);
    if (!$q) {
        return $hasil;
    }

    $urutan = 1;
    while ($row = mysqli_fetch_assoc($q)) {
        $ket = trim((string) ($row['keterangan'] ?? ''));
        $kat = trim((string) ($row['kategori_nama'] ?? ''));
        if ($kat !== '' && stripos($ket, $kat) === false) {
            $ket = $kat . ($ket !== '' ? ' — ' . $ket : '');
        }
        if ($ket === '') {
            $ket = $kat !== '' ? $kat : 'Pengeluaran';
        }

        $hasil[] = [
            'id' => (string) $row['id'],
            'urutan' => $urutan++,
            'keterangan' => $ket,
            'jumlah' => (int) $row['jumlah_nominal'],
            'kategori' => $kat,
            'pj' => trim((string) ($row['pj'] ?? '')),
            'sumber' => 'laba',
        ];
    }

    return $hasil;
}

/** @return int */
function shift_laporan_total_pengeluaran_laba(mysqli $conn, int $cabang, string $tanggal, string $shift, ?string $jamMulai = null, ?string $jamSelesai = null): int
{
    $rows = shift_laporan_ambil_pengeluaran_laba($conn, $cabang, $tanggal, $shift, $jamMulai, $jamSelesai);

    return array_sum(array_column($rows, 'jumlah'));
}

/**
 * @return list<array{user_id: int, user_nama: string, penjualan_sistem: int, penjualan_qris_tf: int, penjualan_kas: int, penjualan_piutang: int}>
 */
function shift_laporan_ambil_penjualan_kasir(mysqli $conn, int $cabang, string $tanggal, string $shift, ?string $jamMulai = null, ?string $jamSelesai = null): array
{
    $cabang = (int) $cabang;
    $tanggalEsc = mysqli_real_escape_string($conn, $tanggal);
    $whereWaktu = shift_laporan_where_waktu($shift, $jamMulai, $jamSelesai, $cabang);
    $tglExpr = shift_laporan_parse_tgl_expr();
    $nugrosir = shift_laporan_cabang_nugrosir($cabang);

    if ($nugrosir) {
        $sql = "
        SELECT
            kasir_id AS user_id,
            COALESCE(MAX(u.user_nama), CONCAT('Kasir #', kasir_id)) AS user_nama,
            SUM(i.invoice_sub_total) AS penjualan_sistem,
            SUM(CASE WHEN i.invoice_piutang = 0 AND i.invoice_tipe_transaksi = 1 THEN i.invoice_sub_total ELSE 0 END) AS penjualan_qris_tf,
            SUM(CASE
                WHEN i.invoice_piutang = 0 AND i.invoice_tipe_transaksi = 0 THEN i.invoice_sub_total
                WHEN i.invoice_piutang = 1 THEN COALESCE(i.invoice_piutang_dp, 0)
                ELSE 0
            END) AS penjualan_kas,
            SUM(CASE WHEN i.invoice_piutang = 1 THEN i.invoice_sub_total ELSE 0 END) AS penjualan_piutang
        FROM invoice i
        ";
    } else {
        $sql = "
        SELECT
            kasir_id AS user_id,
            COALESCE(MAX(u.user_nama), CONCAT('Kasir #', kasir_id)) AS user_nama,
            SUM(i.invoice_sub_total) AS penjualan_sistem,
            SUM(CASE WHEN i.invoice_tipe_transaksi = 1 THEN i.invoice_sub_total ELSE 0 END) AS penjualan_qris_tf,
            SUM(CASE WHEN i.invoice_tipe_transaksi = 0 THEN i.invoice_sub_total ELSE 0 END) AS penjualan_kas,
            0 AS penjualan_piutang
        FROM invoice i
        ";
    }

    $sql .= "
        INNER JOIN (
            SELECT
                invoice_id,
                CAST(invoice_kasir AS UNSIGNED) AS kasir_id
            FROM invoice
            WHERE invoice_cabang = $cabang
              AND invoice_draft = 0
              AND invoice_date = '$tanggalEsc'
        ) ik ON ik.invoice_id = i.invoice_id
        LEFT JOIN user u ON u.user_id = ik.kasir_id
        WHERE $tglExpr IS NOT NULL
          $whereWaktu
        GROUP BY kasir_id
        HAVING penjualan_sistem > 0
        ORDER BY user_nama ASC
    ";

    $hasil = [];
    $q = mysqli_query($conn, $sql);
    if (!$q) {
        return $hasil;
    }

    while ($row = mysqli_fetch_assoc($q)) {
        $hasil[] = [
            'user_id' => (int) $row['user_id'],
            'user_nama' => (string) $row['user_nama'],
            'penjualan_sistem' => (int) $row['penjualan_sistem'],
            'penjualan_qris_tf' => (int) $row['penjualan_qris_tf'],
            'penjualan_kas' => (int) $row['penjualan_kas'],
            'penjualan_piutang' => (int) ($row['penjualan_piutang'] ?? 0),
        ];
    }

    return $hasil;
}

/**
 * @return array{header: ?array, kasir: array<int, array>, pengeluaran: list<array>}
 */
function shift_laporan_ambil_simpanan(mysqli $conn, int $cabang, string $tanggal, string $shift): array
{
    $cabang = (int) $cabang;
    $tanggalEsc = mysqli_real_escape_string($conn, $tanggal);
    $shiftIn = shift_laporan_shift_db_in_clause($shift, $cabang);

    $header = null;
    $kasir = [];
    $pengeluaran = [];

    try {
        $qH = mysqli_query($conn, "
            SELECT
                id, setor_ke, tgl_setor, shift, jam_mulai, jam_selesai,
                ttd_kp_akt, ttd_kasir1, ttd_kasir2,
                ttd_kp_akt_nama, ttd_kasir1_nama, ttd_kasir2_nama,
                ttd_kp_akt_at, ttd_kasir1_at, ttd_kasir2_at,
                ttd_kp_akt_by, ttd_kasir1_by, ttd_kasir2_by
            FROM shift_laporan
            WHERE cabang = $cabang AND tanggal = '$tanggalEsc' AND shift IN $shiftIn
            ORDER BY FIELD(shift, 'sore', 'malam', 'pagi')
            LIMIT 1
        ");
    } catch (mysqli_sql_exception $e) {
        return ['header' => null, 'kasir' => [], 'pengeluaran' => []];
    }
    if ($qH && ($rowH = mysqli_fetch_assoc($qH))) {
        $header = [
            'id' => (int) $rowH['id'],
            'setor_ke' => $rowH['setor_ke'],
            'tgl_setor' => $rowH['tgl_setor'],
            'jam_mulai' => $rowH['jam_mulai'] ?? null,
            'jam_selesai' => $rowH['jam_selesai'] ?? null,
            'ttd' => [
                'kp_akt' => shift_laporan_format_ttd_row($rowH, 'ttd_kp_akt'),
                'kasir1' => shift_laporan_format_ttd_row($rowH, 'ttd_kasir1'),
                'kasir2' => shift_laporan_format_ttd_row($rowH, 'ttd_kasir2'),
            ],
        ];
        $laporanId = (int) $rowH['id'];

        $qK = mysqli_query($conn, "
            SELECT user_id, pengeluaran_kas, setoran_kas
            FROM shift_laporan_kasir
            WHERE shift_laporan_id = $laporanId
        ");
        if ($qK) {
            while ($rk = mysqli_fetch_assoc($qK)) {
                $kasir[(int) $rk['user_id']] = [
                    'pengeluaran_kas' => (int) $rk['pengeluaran_kas'],
                    'setoran_kas' => (int) $rk['setoran_kas'],
                ];
            }
        }

        $qP = mysqli_query($conn, "
            SELECT urutan, keterangan, jumlah
            FROM shift_laporan_pengeluaran
            WHERE shift_laporan_id = $laporanId
            ORDER BY urutan ASC
        ");
        if ($qP) {
            while ($rp = mysqli_fetch_assoc($qP)) {
                $pengeluaran[] = [
                    'urutan' => (int) $rp['urutan'],
                    'keterangan' => (string) $rp['keterangan'],
                    'jumlah' => (int) $rp['jumlah'],
                ];
            }
        }
    }

    $ttdDefault = [
        'kp_akt' => shift_laporan_format_ttd_row(null, 'ttd_kp_akt'),
        'kasir1' => shift_laporan_format_ttd_row(null, 'ttd_kasir1'),
        'kasir2' => shift_laporan_format_ttd_row(null, 'ttd_kasir2'),
    ];

    if ($header && isset($header['ttd'])) {
        $ttdDefault = $header['ttd'];
    }

    return ['header' => $header, 'kasir' => $kasir, 'pengeluaran' => $pengeluaran, 'ttd' => $ttdDefault];
}

function shift_laporan_format_rupiah(int $angka): string
{
    return number_format($angka, 0, ',', '.');
}
