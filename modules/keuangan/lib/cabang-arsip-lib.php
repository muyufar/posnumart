<?php
/**
 * Arsip cabang (soft-disable) agar tidak masuk agregasi Nugrosir / cabang aktif.
 * Data tetap di DB (transfer history jangan dihapus — ada trigger stok).
 */

if (!function_exists('cabang_arsip_baqnu_id')) {
    function cabang_arsip_baqnu_id(): int
    {
        return 4;
    }
}

if (!function_exists('cabang_arsip_ids')) {
    /**
     * Cabang nonaktif (toko_status = 0). Dipakai untuk exclude dari total laporan.
     *
     * @return list<int>
     */
    function cabang_arsip_ids(mysqli $conn, bool $refresh = false): array
    {
        static $cache = null;
        if ($refresh) {
            $cache = null;
        }
        if ($cache !== null) {
            return $cache;
        }

        $ids = [];
        $res = @mysqli_query($conn, 'SELECT toko_cabang FROM toko WHERE toko_status = 0 OR toko_status = \'0\'');
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $ids[] = (int) $row['toko_cabang'];
            }
        }
        $cache = $ids;
        return $cache;
    }
}

if (!function_exists('cabang_is_aktif')) {
    function cabang_is_aktif(mysqli $conn, int $cabang): bool
    {
        return !in_array($cabang, cabang_arsip_ids($conn), true);
    }
}

if (!function_exists('cabang_sql_exclude_arsip')) {
    /**
     * Cuplikan SQL: AND col NOT IN (...arsip...)
     * Kosong jika tidak ada cabang arsip.
     */
    function cabang_sql_exclude_arsip(mysqli $conn, string $column): string
    {
        $column = preg_replace('/[^a-zA-Z0-9_`.]/', '', $column);
        if ($column === '') {
            return '';
        }
        $ids = cabang_arsip_ids($conn);
        if ($ids === []) {
            return '';
        }
        return ' AND ' . $column . ' NOT IN (' . implode(',', array_map('intval', $ids)) . ')';
    }
}

if (!function_exists('cabang_filter_map_aktif')) {
    /**
     * @param array<string,int> $cabangMap
     * @return array<string,int>
     */
    function cabang_filter_map_aktif(mysqli $conn, array $cabangMap): array
    {
        $out = [];
        foreach ($cabangMap as $key => $id) {
            if (cabang_is_aktif($conn, (int) $id)) {
                $out[$key] = (int) $id;
            }
        }
        return $out;
    }
}

if (!function_exists('baqnu_arsip_status')) {
    /**
     * @return array<string,mixed>
     */
    function baqnu_arsip_status(mysqli $conn, int $cabang = 4): array
    {
        $cabang = (int) $cabang;
        $toko = null;
        $qToko = mysqli_query($conn, "SELECT * FROM toko WHERE toko_cabang = {$cabang} LIMIT 1");
        if ($qToko) {
            $toko = mysqli_fetch_assoc($qToko) ?: null;
        }

        $userAktif = 0;
        $userTotal = 0;
        $qUser = mysqli_query($conn, "
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN user_status = '1' OR user_status = 1 THEN 1 ELSE 0 END) AS aktif
            FROM `user`
            WHERE user_cabang = {$cabang}
        ");
        if ($qUser && ($row = mysqli_fetch_assoc($qUser))) {
            $userTotal = (int) ($row['total'] ?? 0);
            $userAktif = (int) ($row['aktif'] ?? 0);
        }

        $barangAktif = 0;
        $barangTotal = 0;
        $qBarang = mysqli_query($conn, "
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN barang_status = '1' OR barang_status = 1 THEN 1 ELSE 0 END) AS aktif
            FROM barang
            WHERE barang_cabang = {$cabang}
        ");
        if ($qBarang && ($row = mysqli_fetch_assoc($qBarang))) {
            $barangTotal = (int) ($row['total'] ?? 0);
            $barangAktif = (int) ($row['aktif'] ?? 0);
        }

        $invoice = 0;
        $qInv = mysqli_query($conn, "SELECT COUNT(*) AS c FROM invoice WHERE invoice_cabang = {$cabang}");
        if ($qInv && ($row = mysqli_fetch_assoc($qInv))) {
            $invoice = (int) ($row['c'] ?? 0);
        }

        $tokoAktif = $toko && ((string) ($toko['toko_status'] ?? '0') === '1' || (int) ($toko['toko_status'] ?? 0) === 1);
        $sudahArsip = !$tokoAktif;

        return [
            'cabang' => $cabang,
            'toko' => $toko,
            'toko_aktif' => $tokoAktif,
            'sudah_arsip' => $sudahArsip,
            'user_total' => $userTotal,
            'user_aktif' => $userAktif,
            'barang_total' => $barangTotal,
            'barang_aktif' => $barangAktif,
            'invoice' => $invoice,
            'dalam_daftar_arsip' => in_array($cabang, cabang_arsip_ids($conn, true), true),
        ];
    }
}

if (!function_exists('baqnu_arsip_jalankan')) {
    /**
     * Soft-disable BAQNU di server utama. TIDAK menghapus data / transfer.
     *
     * @return array{ok:bool,message:string,log:list<string>}
     */
    function baqnu_arsip_jalankan(mysqli $conn, int $cabang = 4, bool $nonaktifkanBarang = true): array
    {
        $cabang = (int) $cabang;
        $log = [];

        $q1 = mysqli_query($conn, "UPDATE toko SET toko_status = 0 WHERE toko_cabang = {$cabang}");
        $log[] = $q1
            ? 'toko: status → 0 (' . mysqli_affected_rows($conn) . ' baris)'
            : 'toko GAGAL: ' . mysqli_error($conn);

        $q2 = mysqli_query($conn, "UPDATE `user` SET user_status = '0' WHERE user_cabang = {$cabang}");
        $log[] = $q2
            ? 'user: status → 0 (' . mysqli_affected_rows($conn) . ' baris)'
            : 'user GAGAL: ' . mysqli_error($conn);

        if ($nonaktifkanBarang) {
            $q3 = mysqli_query($conn, "UPDATE barang SET barang_status = '0' WHERE barang_cabang = {$cabang}");
            $log[] = $q3
                ? 'barang: status → 0 (' . mysqli_affected_rows($conn) . ' baris)'
                : 'barang GAGAL: ' . mysqli_error($conn);
        }

        cabang_arsip_ids($conn, true);
        $ok = (bool) $q1 && (bool) $q2;

        return [
            'ok' => $ok,
            'message' => $ok
                ? 'BAQNU diarsipkan di server utama. Data tetap ada; tidak masuk total cabang aktif.'
                : 'Arsip sebagian gagal — cek log.',
            'log' => $log,
        ];
    }
}

if (!function_exists('baqnu_arsip_batalkan')) {
    /**
     * Aktifkan kembali BAQNU di server utama (rollback soft-disable).
     *
     * @return array{ok:bool,message:string,log:list<string>}
     */
    function baqnu_arsip_batalkan(mysqli $conn, int $cabang = 4, bool $aktifkanBarang = true): array
    {
        $cabang = (int) $cabang;
        $log = [];

        $q1 = mysqli_query($conn, "UPDATE toko SET toko_status = 1 WHERE toko_cabang = {$cabang}");
        $log[] = $q1
            ? 'toko: status → 1 (' . mysqli_affected_rows($conn) . ' baris)'
            : 'toko GAGAL: ' . mysqli_error($conn);

        $q2 = mysqli_query($conn, "UPDATE `user` SET user_status = '1' WHERE user_cabang = {$cabang}");
        $log[] = $q2
            ? 'user: status → 1 (' . mysqli_affected_rows($conn) . ' baris)'
            : 'user GAGAL: ' . mysqli_error($conn);

        if ($aktifkanBarang) {
            $q3 = mysqli_query($conn, "UPDATE barang SET barang_status = '1' WHERE barang_cabang = {$cabang}");
            $log[] = $q3
                ? 'barang: status → 1 (' . mysqli_affected_rows($conn) . ' baris)'
                : 'barang GAGAL: ' . mysqli_error($conn);
        }

        cabang_arsip_ids($conn, true);
        $ok = (bool) $q1 && (bool) $q2;

        return [
            'ok' => $ok,
            'message' => $ok
                ? 'Arsip BAQNU dibatalkan — cabang aktif lagi di server utama.'
                : 'Pembatalan sebagian gagal — cek log.',
            'log' => $log,
        ];
    }
}

if (!function_exists('baqnu_piutang_adjust_tag')) {
    function baqnu_piutang_adjust_tag(): string
    {
        return 'BAQNU_PIUTANG_ADJUST';
    }
}

if (!function_exists('baqnu_piutang_sisa_belum_lunas')) {
    /**
     * Sisa piutang invoice cabang BAQNU (belum lunas) di DB server utama.
     *
     * @return array{nominal:float,jumlah_invoice:int}
     */
    function baqnu_piutang_sisa_belum_lunas(mysqli $conn, int $cabang = 4): array
    {
        $cabang = (int) $cabang;
        $nominal = 0.0;
        $jumlah = 0;
        $q = mysqli_query($conn, "
            SELECT
                COALESCE(SUM(GREATEST(invoice_sub_total - invoice_bayar, 0)), 0) AS total,
                COUNT(*) AS jumlah
            FROM invoice
            WHERE invoice_piutang = 1
              AND invoice_bayar < invoice_sub_total
              AND invoice_cabang = {$cabang}
        ");
        if ($q && ($row = mysqli_fetch_assoc($q))) {
            $nominal = (float) ($row['total'] ?? 0);
            $jumlah = (int) ($row['jumlah'] ?? 0);
        }
        return [
            'nominal' => $nominal,
            'jumlah_invoice' => $jumlah,
        ];
    }
}

if (!function_exists('baqnu_piutang_adjust_find_log')) {
    /**
     * @return array<string,mixed>|null
     */
    function baqnu_piutang_adjust_find_log(mysqli $conn): ?array
    {
        $tag = mysqli_real_escape_string($conn, baqnu_piutang_adjust_tag());
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM laba LIKE 'tag'");
        if (!$chk || mysqli_num_rows($chk) < 1) {
            $q = mysqli_query($conn, "
                SELECT id, jumlah, keterangan, date, name, created_at
                FROM laba
                WHERE keterangan LIKE '[BAQNU-PISAH]%'
                ORDER BY created_at DESC
                LIMIT 1
            ");
        } else {
            $q = mysqli_query($conn, "
                SELECT id, jumlah, keterangan, tag, date, name, created_at
                FROM laba
                WHERE tag = '{$tag}' OR keterangan LIKE '[BAQNU-PISAH]%'
                ORDER BY created_at DESC
                LIMIT 1
            ");
        }
        if (!$q) {
            return null;
        }
        $row = mysqli_fetch_assoc($q);
        return $row ?: null;
    }
}

if (!function_exists('baqnu_piutang_penyesuaian_preview')) {
    /**
     * @return array<string,mixed>
     */
    function baqnu_piutang_penyesuaian_preview(mysqli $conn, int $cabang = 4): array
    {
        require_once __DIR__ . '/akun-link-lib.php';

        $sisa = baqnu_piutang_sisa_belum_lunas($conn, $cabang);
        $kode = akun_piutang_kode();
        $rowAkun = akun_find_laba_kategori_row_exact($conn, $kode, 0);
        if (!$rowAkun) {
            // fallback kode lama
            $rowAkun = akun_find_laba_kategori_row($conn, $kode, 0);
        }
        $saldoSekarang = $rowAkun ? (float) ($rowAkun['saldo'] ?? 0) : 0.0;
        $log = baqnu_piutang_adjust_find_log($conn);
        $sudah = $log !== null;
        $nominalKurang = $sisa['nominal'];
        $saldoSetelah = $saldoSekarang - $nominalKurang;

        return [
            'cabang' => $cabang,
            'kode_akun' => $kode,
            'akun_id' => $rowAkun ? (int) ($rowAkun['id'] ?? 0) : 0,
            'saldo_sekarang' => $saldoSekarang,
            'nominal_baqnu' => $nominalKurang,
            'jumlah_invoice' => $sisa['jumlah_invoice'],
            'saldo_setelah' => $saldoSetelah,
            'sudah_disesuaikan' => $sudah,
            'log' => $log,
        ];
    }
}

if (!function_exists('baqnu_piutang_penyesuaian_jalankan')) {
    /**
     * Kurangi saldo 1-1301 (cabang 0) sebesar sisa piutang BAQNU yang belum lunas.
     *
     * @return array{ok:bool,message:string,log:list<string>,preview?:array<string,mixed>}
     */
    function baqnu_piutang_penyesuaian_jalankan(mysqli $conn, int $cabang = 4, string $userName = ''): array
    {
        require_once __DIR__ . '/akun-link-lib.php';

        $preview = baqnu_piutang_penyesuaian_preview($conn, $cabang);
        $log = [];

        if (!empty($preview['sudah_disesuaikan'])) {
            return [
                'ok' => false,
                'message' => 'Penyesuaian sudah pernah dijalankan. Batalkan dulu jika ingin mengulang.',
                'log' => ['Log: ' . (string) ($preview['log']['keterangan'] ?? '-')],
                'preview' => $preview,
            ];
        }

        $nominal = (float) $preview['nominal_baqnu'];
        if ($nominal <= 0) {
            return [
                'ok' => false,
                'message' => 'Tidak ada sisa piutang BAQNU yang belum lunas — tidak perlu penyesuaian.',
                'log' => [],
                'preview' => $preview,
            ];
        }

        if ((int) ($preview['akun_id'] ?? 0) < 1) {
            return [
                'ok' => false,
                'message' => 'Akun 1-1301 Piutang Dagang (PCNU) tidak ditemukan di laba_kategori.',
                'log' => [],
                'preview' => $preview,
            ];
        }

        // Kurangi saldo piutang pusat (debit turun)
        akun_update_saldo_delta(
            $conn,
            akun_piutang_kode(),
            'Piutang Dagang',
            'aktiva',
            'debit',
            -$nominal,
            0
        );
        $log[] = 'laba_kategori 1-1301 cabang 0: -' . number_format($nominal, 2, ',', '.');

        $ket = sprintf(
            '[BAQNU-PISAH] Kurangi piutang 1-1301 Rp %s (%d invoice cabang %d) — pindah ke baqnu.numartmagelang.com',
            number_format($nominal, 0, ',', '.'),
            (int) $preview['jumlah_invoice'],
            $cabang
        );
        $ketEsc = mysqli_real_escape_string($conn, $ket);
        $nameEsc = mysqli_real_escape_string($conn, $userName !== '' ? $userName : 'SYSTEM');
        $tagEsc = mysqli_real_escape_string($conn, baqnu_piutang_adjust_tag());
        $jumlahEsc = mysqli_real_escape_string($conn, (string) round($nominal, 2));
        $akunId = (int) $preview['akun_id'];
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );

        $hasTag = false;
        $chkTag = @mysqli_query($conn, "SHOW COLUMNS FROM laba LIKE 'tag'");
        if ($chkTag && mysqli_num_rows($chkTag) > 0) {
            $hasTag = true;
        }
        $hasAkunDebit = false;
        $chkDebit = @mysqli_query($conn, "SHOW COLUMNS FROM laba LIKE 'akun_debit'");
        if ($chkDebit && mysqli_num_rows($chkDebit) > 0) {
            $hasAkunDebit = true;
        }

        if ($hasTag && $hasAkunDebit) {
            $sql = "INSERT INTO laba
                (id, tipe, jenis_transaksi, kategori, akun_debit, akun_kredit, nominal, total, jumlah, keterangan, tag, cabang, date, name, created_at)
                VALUES
                ('{$uuid}', 1, 'penyesuaian', '{$akunId}', {$akunId}, NULL, {$nominal}, {$nominal}, '{$jumlahEsc}', '{$ketEsc}', '{$tagEsc}', 0, NOW(), '{$nameEsc}', NOW())";
        } elseif ($hasTag) {
            $sql = "INSERT INTO laba
                (id, tipe, kategori, jumlah, keterangan, tag, cabang, date, name, created_at)
                VALUES
                ('{$uuid}', 1, '{$akunId}', '{$jumlahEsc}', '{$ketEsc}', '{$tagEsc}', 0, NOW(), '{$nameEsc}', NOW())";
        } else {
            $sql = "INSERT INTO laba
                (id, tipe, kategori, jumlah, keterangan, cabang, date, name, created_at)
                VALUES
                ('{$uuid}', 1, '{$akunId}', '{$jumlahEsc}', '{$ketEsc}', 0, NOW(), '{$nameEsc}', NOW())";
        }

        $qLog = mysqli_query($conn, $sql);
        $log[] = $qLog
            ? 'Jurnal audit laba tersimpan (id ' . $uuid . ')'
            : 'Jurnal audit gagal: ' . mysqli_error($conn);

        $after = baqnu_piutang_penyesuaian_preview($conn, $cabang);

        return [
            'ok' => true,
            'message' => 'Saldo 1-1301 dikurangi porsi piutang BAQNU sebesar Rp '
                . number_format($nominal, 0, ',', '.') . '.',
            'log' => $log,
            'preview' => $after,
        ];
    }
}

if (!function_exists('baqnu_piutang_penyesuaian_batalkan')) {
    /**
     * Rollback penyesuaian: kembalikan saldo 1-1301 + hapus log audit.
     *
     * @return array{ok:bool,message:string,log:list<string>}
     */
    function baqnu_piutang_penyesuaian_batalkan(mysqli $conn, int $cabang = 4): array
    {
        require_once __DIR__ . '/akun-link-lib.php';

        $logRow = baqnu_piutang_adjust_find_log($conn);
        if (!$logRow) {
            return [
                'ok' => false,
                'message' => 'Tidak ada penyesuaian BAQNU yang bisa dibatalkan.',
                'log' => [],
            ];
        }

        $nominal = (float) ($logRow['jumlah'] ?? 0);
        $log = [];
        if ($nominal > 0) {
            akun_update_saldo_delta(
                $conn,
                akun_piutang_kode(),
                'Piutang Dagang',
                'aktiva',
                'debit',
                $nominal,
                0
            );
            $log[] = 'laba_kategori 1-1301 cabang 0: +' . number_format($nominal, 2, ',', '.');
        }

        $idEsc = mysqli_real_escape_string($conn, (string) $logRow['id']);
        $qDel = mysqli_query($conn, "DELETE FROM laba WHERE id = '{$idEsc}' LIMIT 1");
        $log[] = $qDel
            ? 'Jurnal audit dihapus'
            : 'Hapus jurnal gagal: ' . mysqli_error($conn);

        return [
            'ok' => (bool) $qDel,
            'message' => $qDel
                ? 'Penyesuaian dibatalkan. Saldo 1-1301 dikembalikan Rp ' . number_format($nominal, 0, ',', '.') . '.'
                : 'Pembatalan gagal sebagian — cek log.',
            'log' => $log,
        ];
    }
}
