<?php
/**
 * Export database khusus cabang BAQNU (default: 4) → SQL siap import ke instance terpisah.
 */
declare(strict_types=1);

if (!function_exists('export_baqnu_default_cabang')) {
    function export_baqnu_default_cabang(): int
    {
        return 4;
    }
}

if (!function_exists('export_baqnu_collation_map')) {
    /** @return array<string,string> */
    function export_baqnu_collation_map(): array
    {
        return [
            'utf8mb4_uca1400_ai_ci' => 'utf8mb4_unicode_ci',
            'utf8mb4_uca1400_as_ci' => 'utf8mb4_unicode_ci',
            'utf8mb4_uca1400_as_cs' => 'utf8mb4_unicode_ci',
            'utf8mb4_0900_ai_ci' => 'utf8mb4_unicode_ci',
            'utf8mb4_0900_as_ci' => 'utf8mb4_unicode_ci',
            'utf8mb4_0900_as_cs' => 'utf8mb4_unicode_ci',
            'utf8mb4_0900_bin' => 'utf8mb4_bin',
        ];
    }
}

if (!function_exists('export_baqnu_sanitize_ddl')) {
    function export_baqnu_sanitize_ddl(string $sql): string
    {
        $map = export_baqnu_collation_map();
        return str_replace(array_keys($map), array_values($map), $sql);
    }
}

if (!function_exists('export_baqnu_list_tables')) {
    /** @return list<string> */
    function export_baqnu_list_tables(mysqli $conn): array
    {
        $tables = [];
        $res = mysqli_query($conn, 'SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        if ($res) {
            while ($row = mysqli_fetch_array($res)) {
                $tables[] = (string) $row[0];
            }
        }
        sort($tables, SORT_STRING);
        return $tables;
    }
}

if (!function_exists('export_baqnu_table_columns')) {
    /** @return list<string> */
    function export_baqnu_table_columns(mysqli $conn, string $table): array
    {
        $cols = [];
        $tEsc = mysqli_real_escape_string($conn, $table);
        $res = mysqli_query($conn, "SHOW COLUMNS FROM `$tEsc`");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $cols[] = (string) $row['Field'];
            }
        }
        return $cols;
    }
}

if (!function_exists('export_baqnu_cabang_columns')) {
    /** @return list<string> */
    function export_baqnu_cabang_columns(mysqli $conn, string $table): array
    {
        $found = [];
        foreach (export_baqnu_table_columns($conn, $table) as $col) {
            if ($col === 'cabang' || preg_match('/cabang/i', $col)) {
                $found[] = $col;
            }
        }
        return $found;
    }
}

if (!function_exists('export_baqnu_sql_in_list')) {
    /**
     * @param list<int|string> $values
     */
    function export_baqnu_sql_in_list(mysqli $conn, array $values, bool $asString = false): string
    {
        if ($values === []) {
            return $asString ? "''" : 'NULL';
        }
        $parts = [];
        foreach ($values as $v) {
            if ($asString) {
                $parts[] = "'" . mysqli_real_escape_string($conn, (string) $v) . "'";
            } else {
                $parts[] = (string) (int) $v;
            }
        }
        return implode(',', $parts);
    }
}

if (!function_exists('export_baqnu_fetch_ids')) {
    /**
     * @return list<int|string>
     */
    function export_baqnu_fetch_ids(mysqli $conn, string $sql, string $col = 'id'): array
    {
        $ids = [];
        $res = @mysqli_query($conn, $sql);
        if (!$res) {
            return $ids;
        }
        while ($row = mysqli_fetch_assoc($res)) {
            if (!array_key_exists($col, $row) || $row[$col] === null || $row[$col] === '') {
                continue;
            }
            $ids[] = $row[$col];
        }
        return $ids;
    }
}

if (!function_exists('export_baqnu_build_context')) {
    /**
     * Kunci induk untuk filter tabel anak tanpa kolom cabang.
     *
     * @return array{
     *   cabang:int,
     *   include_shared_master:bool,
     *   invoice_nos:list<string>,
     *   customer_ids:list<int>,
     *   user_ids:list<int>,
     *   barang_ids:list<int>,
     *   shift_ids:list<int>,
     *   blast_ids:list<int>,
     *   tag_ids:list<int>,
     *   stock_opname_ids:list<int>
     * }
     */
    function export_baqnu_build_context(mysqli $conn, int $cabang, bool $includeSharedMaster = true): array
    {
        $c = (int) $cabang;

        $invoiceNos = export_baqnu_fetch_ids(
            $conn,
            "SELECT DISTINCT penjualan_invoice AS id FROM invoice WHERE invoice_cabang = {$c}",
            'id'
        );
        $invoiceNos = array_values(array_unique(array_map('strval', $invoiceNos)));

        $customerIds = array_map(
            'intval',
            export_baqnu_fetch_ids($conn, "SELECT customer_id AS id FROM customer WHERE customer_cabang = {$c}", 'id')
        );

        $userIds = array_map(
            'intval',
            export_baqnu_fetch_ids($conn, "SELECT user_id AS id FROM `user` WHERE user_cabang = {$c}", 'id')
        );

        $barangIds = array_map(
            'intval',
            export_baqnu_fetch_ids($conn, "SELECT barang_id AS id FROM barang WHERE barang_cabang = {$c}", 'id')
        );

        $shiftIds = [];
        $tables = export_baqnu_list_tables($conn);
        if (in_array('shift_laporan', $tables, true)) {
            $shiftIds = array_map(
                'intval',
                export_baqnu_fetch_ids($conn, "SELECT id FROM shift_laporan WHERE cabang = {$c}", 'id')
            );
        }

        $blastIds = [];
        if (in_array('wa_blast_history', $tables, true)) {
            $blastIds = array_map(
                'intval',
                export_baqnu_fetch_ids($conn, "SELECT id FROM wa_blast_history WHERE cabang = {$c}", 'id')
            );
        }

        $tagIds = [];
        if (in_array('customer_tags', $tables, true)) {
            $tagSql = $includeSharedMaster
                ? "SELECT id FROM customer_tags WHERE cabang IN (0, {$c})"
                : "SELECT id FROM customer_tags WHERE cabang = {$c}";
            $tagIds = array_map('intval', export_baqnu_fetch_ids($conn, $tagSql, 'id'));
        }

        $stockOpnameIds = [];
        if (in_array('stock_opname', $tables, true)) {
            $stockOpnameIds = array_map(
                'intval',
                export_baqnu_fetch_ids(
                    $conn,
                    "SELECT stock_opname_id AS id FROM stock_opname WHERE stock_opname_cabang = {$c}",
                    'id'
                )
            );
        }

        return [
            'cabang' => $c,
            'include_shared_master' => $includeSharedMaster,
            'invoice_nos' => $invoiceNos,
            'customer_ids' => $customerIds,
            'user_ids' => $userIds,
            'barang_ids' => $barangIds,
            'shift_ids' => $shiftIds,
            'blast_ids' => $blastIds,
            'tag_ids' => $tagIds,
            'stock_opname_ids' => $stockOpnameIds,
        ];
    }
}

if (!function_exists('export_baqnu_shared_master_tables')) {
    /** @return list<string> */
    function export_baqnu_shared_master_tables(): array
    {
        // Master yang sering dipakai lintas cabang (cabang 0 = PCNU/shared).
        return ['satuan', 'kategori', 'ekspedisi', 'customer_tags'];
    }
}

if (!function_exists('export_baqnu_child_where')) {
    /**
     * @param array<string,mixed> $ctx
     */
    function export_baqnu_child_where(mysqli $conn, string $table, array $ctx): ?string
    {
        $cols = export_baqnu_table_columns($conn, $table);
        $cabang = (int) $ctx['cabang'];

        switch ($table) {
            case 'customer_notes':
                if (!in_array('customer_id', $cols, true)) {
                    return '1=0';
                }
                return "customer_id IN (SELECT customer_id FROM customer WHERE customer_cabang = {$cabang})";

            case 'customer_tag_relations':
                if (!in_array('customer_id', $cols, true)) {
                    return '1=0';
                }
                $parts = [
                    "customer_id IN (SELECT customer_id FROM customer WHERE customer_cabang = {$cabang})",
                ];
                if (in_array('tag_id', $cols, true)) {
                    if (!empty($ctx['include_shared_master'])) {
                        $parts[] = "tag_id IN (SELECT id FROM customer_tags WHERE cabang IN (0, {$cabang}))";
                    } else {
                        $parts[] = "tag_id IN (SELECT id FROM customer_tags WHERE cabang = {$cabang})";
                    }
                }
                return '(' . implode(' OR ', $parts) . ')';

            case 'midtrans_payment_history':
                if (!in_array('invoice', $cols, true)) {
                    return '1=0';
                }
                return "invoice IN (SELECT penjualan_invoice FROM invoice WHERE invoice_cabang = {$cabang})";

            case 'retur':
                if (!in_array('retur_invoice', $cols, true)) {
                    return '1=0';
                }
                return "retur_invoice IN (SELECT penjualan_invoice FROM invoice WHERE invoice_cabang = {$cabang})";

            case 'shift_laporan_kasir':
            case 'shift_laporan_pengeluaran':
                if (!in_array('shift_laporan_id', $cols, true)) {
                    return '1=0';
                }
                return "shift_laporan_id IN (SELECT id FROM shift_laporan WHERE cabang = {$cabang})";

            case 'wa_blast_recipients':
                if (!in_array('blast_id', $cols, true)) {
                    return '1=0';
                }
                return "blast_id IN (SELECT id FROM wa_blast_history WHERE cabang = {$cabang})";

            case 'terlaris':
                if (!in_array('barang_id', $cols, true)) {
                    return '1=0';
                }
                return "barang_id IN (SELECT barang_id FROM barang WHERE barang_cabang = {$cabang})";

            case 'stock_opname_hasil':
                // Prefer filter by barang cabang if column exists; else by stock_opname parent.
                if (in_array('soh_barang_cabang', $cols, true)) {
                    return null; // handled as cabang column
                }
                if (in_array('stock_opname_id', $cols, true)) {
                    return "stock_opname_id IN (SELECT stock_opname_id FROM stock_opname WHERE stock_opname_cabang = {$cabang})";
                }
                return '1=0';

            default:
                return null;
        }
    }
}

if (!function_exists('export_baqnu_table_where')) {
    /**
     * @param array<string,mixed> $ctx
     * @return array{mode:string,where:?string,note:string}
     */
    function export_baqnu_table_where(mysqli $conn, string $table, array $ctx): array
    {
        $cabang = (int) $ctx['cabang'];
        $cabangCols = export_baqnu_cabang_columns($conn, $table);

        if ($cabangCols !== []) {
            $parts = [];
            foreach ($cabangCols as $col) {
                $cEsc = '`' . str_replace('`', '``', $col) . '`';
                $parts[] = "{$cEsc} = {$cabang}";
            }

            // Master shared (cabang 0) ikut diekspor agar FK satuan/kategori tidak putus.
            if (!empty($ctx['include_shared_master']) && in_array($table, export_baqnu_shared_master_tables(), true)) {
                $primary = $cabangCols[0];
                $pEsc = '`' . str_replace('`', '``', $primary) . '`';
                return [
                    'mode' => 'filtered',
                    'where' => "{$pEsc} IN (0, {$cabang})",
                    'note' => 'cabang ' . $cabang . ' + master shared (0)',
                ];
            }

            // Transfer: libatkan jika pengirim/penerima/cabang = BAQNU.
            if (count($parts) > 1 || preg_match('/transfer/i', $table)) {
                return [
                    'mode' => 'filtered',
                    'where' => '(' . implode(' OR ', $parts) . ')',
                    'note' => 'salah satu kolom cabang = ' . $cabang,
                ];
            }

            return [
                'mode' => 'filtered',
                'where' => $parts[0],
                'note' => $cabangCols[0] . ' = ' . $cabang,
            ];
        }

        $childWhere = export_baqnu_child_where($conn, $table, $ctx);
        if ($childWhere !== null) {
            return [
                'mode' => 'filtered',
                'where' => $childWhere,
                'note' => 'filter via tabel induk cabang ' . $cabang,
            ];
        }

        // Tabel tanpa filter aman → struktur saja (hindari bocor data cabang lain).
        return [
            'mode' => 'structure_only',
            'where' => '1=0',
            'note' => 'struktur saja (tidak ada filter cabang yang aman)',
        ];
    }
}

if (!function_exists('export_baqnu_remap_row')) {
    /**
     * @param array<string,mixed> $row
     * @param list<string> $cabangCols
     * @return array<string,mixed>
     */
    function export_baqnu_remap_row(array $row, array $cabangCols, int $fromCabang, int $toCabang): array
    {
        if ($fromCabang === $toCabang || $cabangCols === []) {
            return $row;
        }
        foreach ($cabangCols as $col) {
            if (!array_key_exists($col, $row) || $row[$col] === null) {
                continue;
            }
            if ((int) $row[$col] === $fromCabang) {
                $row[$col] = $toCabang;
            }
        }
        return $row;
    }
}

if (!function_exists('export_baqnu_sql_value')) {
    function export_baqnu_sql_value(mysqli $conn, $val): string
    {
        if ($val === null) {
            return 'NULL';
        }
        return "'" . mysqli_real_escape_string($conn, (string) $val) . "'";
    }
}

if (!function_exists('export_baqnu_count_rows')) {
    function export_baqnu_count_rows(mysqli $conn, string $table, ?string $where): int
    {
        $tEsc = '`' . str_replace('`', '``', $table) . '`';
        $sql = "SELECT COUNT(*) AS c FROM {$tEsc}";
        if ($where !== null && $where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        $res = @mysqli_query($conn, $sql);
        if (!$res) {
            return 0;
        }
        $row = mysqli_fetch_assoc($res);
        return (int) ($row['c'] ?? 0);
    }
}

if (!function_exists('export_baqnu_preview')) {
    /**
     * @return array{ok:bool,cabang:int,remap_to:int,tables:list<array<string,mixed>>,totals:array<string,int>,message?:string}
     */
    function export_baqnu_preview(
        mysqli $conn,
        int $cabang = 4,
        bool $remapToPusat = true,
        bool $includeSharedMaster = true
    ): array {
        $ctx = export_baqnu_build_context($conn, $cabang, $includeSharedMaster);
        $tables = export_baqnu_list_tables($conn);
        $rows = [];
        $totalExport = 0;
        $filtered = 0;
        $structureOnly = 0;

        foreach ($tables as $table) {
            $plan = export_baqnu_table_where($conn, $table, $ctx);
            $count = export_baqnu_count_rows($conn, $table, $plan['where']);
            if ($plan['mode'] === 'structure_only') {
                $structureOnly++;
            } else {
                $filtered++;
            }
            $totalExport += $count;
            $rows[] = [
                'table' => $table,
                'mode' => $plan['mode'],
                'note' => $plan['note'],
                'rows' => $count,
                'cabang_cols' => export_baqnu_cabang_columns($conn, $table),
            ];
        }

        return [
            'ok' => true,
            'cabang' => $cabang,
            'remap_to' => $remapToPusat ? 0 : $cabang,
            'include_shared_master' => $includeSharedMaster,
            'context' => [
                'invoice' => count($ctx['invoice_nos']),
                'customer' => count($ctx['customer_ids']),
                'user' => count($ctx['user_ids']),
                'barang' => count($ctx['barang_ids']),
            ],
            'tables' => $rows,
            'totals' => [
                'table_count' => count($tables),
                'filtered_tables' => $filtered,
                'structure_only_tables' => $structureOnly,
                'export_rows' => $totalExport,
            ],
        ];
    }
}

if (!function_exists('export_baqnu_stream_sql')) {
    /**
     * Stream dump SQL ke output (php://output atau resource).
     *
     * @param resource|null $out
     * @return array{ok:bool,message:string,table_count:int,row_count:int}
     */
    function export_baqnu_stream_sql(
        mysqli $conn,
        int $cabang = 4,
        bool $remapToPusat = true,
        bool $includeSharedMaster = true,
        $out = null
    ): array {
        @set_time_limit(0);
        @ini_set('memory_limit', '768M');

        if ($out === null) {
            $out = fopen('php://output', 'wb');
        }
        if ($out === false) {
            return ['ok' => false, 'message' => 'Output stream gagal', 'table_count' => 0, 'row_count' => 0];
        }

        $ctx = export_baqnu_build_context($conn, $cabang, $includeSharedMaster);
        $tables = export_baqnu_list_tables($conn);
        $remapTo = $remapToPusat ? 0 : $cabang;
        $tableCount = count($tables);
        $rowCount = 0;
        $batchSize = 80;

        $flushOut = static function () use ($out): void {
            fflush($out);
            if (function_exists('flush')) {
                flush();
            }
        };

        fwrite($out, "-- BAQNU standalone DB export\n");
        fwrite($out, '-- Source cabang: ' . $cabang . "\n");
        fwrite($out, '-- Remap cabang: ' . ($remapToPusat ? "{$cabang} -> 0 (pusat di instance baru)" : 'tetap ' . $cabang) . "\n");
        fwrite($out, '-- Generated: ' . date('c') . "\n");
        fwrite($out, '-- Target: baqnu.numartmagelang.com\n');
        fwrite($out, '-- BAQNU_TABLE_COUNT: ' . $tableCount . "\n");
        fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        fwrite($out, "-- === PHASE 1: STRUCTURE ===\n\n");
        foreach ($tables as $table) {
            $tEsc = mysqli_real_escape_string($conn, $table);
            $createRes = mysqli_query($conn, "SHOW CREATE TABLE `$tEsc`");
            $createRow = $createRes ? mysqli_fetch_assoc($createRes) : null;
            if (!$createRow || empty($createRow['Create Table'])) {
                continue;
            }
            fwrite($out, "-- TABLE_STRUCT: `$table`\n");
            fwrite($out, 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . "`;\n");
            fwrite($out, export_baqnu_sanitize_ddl((string) $createRow['Create Table']) . ";\n\n");
            $flushOut();
        }

        fwrite($out, "-- === PHASE 2: DATA (BAQNU filtered) ===\n\n");
        foreach ($tables as $table) {
            $plan = export_baqnu_table_where($conn, $table, $ctx);
            $cabangCols = export_baqnu_cabang_columns($conn, $table);
            $tEsc = '`' . str_replace('`', '``', $table) . '`';

            fwrite($out, "-- TABLE_DATA: `$table` ({$plan['note']})\n");

            if ($plan['mode'] === 'structure_only') {
                fwrite($out, "-- (no data)\n\n");
                $flushOut();
                continue;
            }

            $sql = "SELECT * FROM {$tEsc} WHERE {$plan['where']}";
            $dataRes = mysqli_query($conn, $sql);
            if (!$dataRes) {
                fwrite($out, '-- ERROR: ' . mysqli_error($conn) . "\n\n");
                $flushOut();
                continue;
            }

            $cols = null;
            $batch = [];

            while ($dataRow = mysqli_fetch_assoc($dataRes)) {
                if ($remapToPusat) {
                    $dataRow = export_baqnu_remap_row($dataRow, $cabangCols, $cabang, $remapTo);
                }

                if ($cols === null) {
                    $cols = [];
                    foreach (array_keys($dataRow) as $col) {
                        $cols[] = '`' . str_replace('`', '``', $col) . '`';
                    }
                }

                $vals = [];
                foreach ($dataRow as $val) {
                    $vals[] = export_baqnu_sql_value($conn, $val);
                }
                $batch[] = '(' . implode(',', $vals) . ')';
                $rowCount++;

                if (count($batch) >= $batchSize) {
                    fwrite(
                        $out,
                        'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(',', $cols) . ') VALUES '
                        . implode(',', $batch) . ";\n"
                    );
                    $batch = [];
                }
            }

            if ($batch !== [] && $cols !== null) {
                fwrite(
                    $out,
                    'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(',', $cols) . ') VALUES '
                    . implode(',', $batch) . ";\n"
                );
            }
            fwrite($out, "\n");
            $flushOut();
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fwrite($out, '-- BAQNU_EXPORT_END tables=' . $tableCount . ' rows=' . $rowCount . "\n");
        fwrite($out, "--\n");
        fwrite($out, "-- Cara pakai di baqnu.numartmagelang.com:\n");
        fwrite($out, "-- 1. Buat database MySQL baru di Hostinger\n");
        fwrite($out, "-- 2. Import file SQL ini (phpMyAdmin / mysql CLI)\n");
        fwrite($out, "-- 3. Deploy copy aplikasi, set aksi/koneksi.php ke DB baru\n");
        if ($remapToPusat) {
            fwrite($out, "-- 4. Login dengan user BAQNU (user_cabang sudah di-remap ke 0 / pusat)\n");
        } else {
            fwrite($out, "-- 4. Login dengan user BAQNU (user_cabang tetap {$cabang})\n");
        }

        return [
            'ok' => true,
            'message' => 'Export BAQNU selesai',
            'table_count' => $tableCount,
            'row_count' => $rowCount,
        ];
    }
}
