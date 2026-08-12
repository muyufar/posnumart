<?php
/**
 * Produk terlaris investor — query paginated + render UI (dipakai semua halaman inves/).
 */

if (!function_exists('invesToko_normalizeDisplayName')) {
    /**
     * Normalisasi label toko untuk tampilan halaman investor.
     */
    function invesToko_normalizeDisplayName(int $cabang, string $nama): string
    {
        $nama = trim($nama);
        if ($nama === '') {
            return $cabang === 0 ? 'NUGROSIR' : $nama;
        }

        static $legacyCabang0 = [
            'toko pcnu kab magelang',
            'nugrosir pcnu',
            'numart pcnu kab magelang',
        ];

        if ($cabang === 0 && in_array(strtolower($nama), $legacyCabang0, true)) {
            return 'NUGROSIR';
        }

        if ($cabang === 0 && stripos($nama, 'pcnu') !== false && stripos($nama, 'magelang') !== false) {
            return 'NUGROSIR';
        }

        return $nama;
    }
}

if (!function_exists('invesTerlaris_css')) {
    function invesTerlaris_css(): string
    {
        return <<<'CSS'
        .top-product-item { display: flex; align-items: center; padding: 12px 20px; border-bottom: 1px solid #f3f4f6; }
        .top-product-item:last-child { border-bottom: none; }
        .top-product-rank { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; margin-right: 12px; font-size: .85rem; flex-shrink: 0; }
        .rank-1 { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #fff; }
        .rank-2 { background: #9ca3af; color: #fff; }
        .rank-3 { background: #d97706; color: #fff; }
        .rank-other { background: #e5e7eb; color: #6b7280; }
        .top-product-info { flex: 1; min-width: 0; }
        .top-product-name { font-weight: 600; color: #1f2937; font-size: .9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .top-product-qty { font-size: .8rem; color: #6b7280; }
        .top-product-revenue { font-weight: 700; color: #1e3c72; font-size: .9rem; white-space: nowrap; margin-left: 12px; }
        .top-product-store { display: inline-block; margin-top: 2px; padding: 2px 8px; border-radius: 999px; font-size: .72rem; font-weight: 600; background: #ecfdf5; color: #047857; }
        .top-product-pagination { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px; border-top: 1px solid #e5e7eb; background: #f9fafb; border-radius: 0 0 20px 20px; }
        .top-product-pagination .page-info { font-size: .85rem; color: #374151; }
        .top-product-pagination .page-info strong { color: #047857; }
        .top-product-pagination .page-links { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .top-product-pagination .page-per-form { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: .85rem; color: #6b7280; margin-bottom: 0; }
        .top-product-pagination .page-per-form select { border: 1px solid #d1d5db; border-radius: 8px; padding: 6px 10px; font-size: .85rem; background: #fff; }
        .top-product-pagination .page-link-btn {
            display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px;
            padding: 0 12px; border-radius: 10px; font-size: .85rem; font-weight: 600; text-decoration: none;
            border: 1px solid #d1d5db; background: #fff; color: #374151; transition: all .15s ease;
        }
        .top-product-pagination .page-link-btn:hover { border-color: #10b981; color: #047857; background: #f0fdf4; text-decoration: none; }
        .top-product-pagination .page-link-btn.active { background: linear-gradient(135deg, #047857, #10b981); border-color: transparent; color: #fff; pointer-events: none; }
        .top-product-pagination .page-link-btn.disabled { opacity: .45; pointer-events: none; }
CSS;
    }
}

if (!function_exists('invesTerlaris_pageNumbers')) {
    function invesTerlaris_pageNumbers(int $current, int $total): array
    {
        if ($total <= 1) {
            return $total === 1 ? [1] : [];
        }
        if ($total <= 9) {
            return range(1, $total);
        }
        $pages = [1];
        $start = max(2, $current - 2);
        $end = min($total - 1, $current + 2);
        if ($start > 2) {
            $pages[] = '…';
        }
        for ($p = $start; $p <= $end; $p++) {
            $pages[] = $p;
        }
        if ($end < $total - 1) {
            $pages[] = '…';
        }
        $pages[] = $total;
        return $pages;
    }
}

if (!function_exists('invesTerlaris_pageUrl')) {
    function invesTerlaris_pageUrl(array $base, int $page): string
    {
        $base['produk_page'] = $page;
        return '?' . http_build_query($base);
    }
}

if (!function_exists('invesTerlaris_fetch')) {
    /**
     * @param callable $queryFn function(string $sql): array<int, array<string, mixed>>
     * @param array{
     *   cabang_ids: int[],
     *   start: string,
     *   end: string,
     *   filter_type: string,
     *   selected_month?: string,
     *   custom_start?: string,
     *   custom_end?: string,
     *   group_by_cabang?: bool
     * } $opts
     * @return array<string, mixed>
     */
    function invesTerlaris_fetch(callable $queryFn, array $opts): array
    {
        $cabangIds = array_values(array_unique(array_map('intval', $opts['cabang_ids'] ?? [])));
        if ($cabangIds === []) {
            return [
                'items' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => 20,
                'per_page_options' => [10, 20, 50],
                'total_pages' => 1,
                'rank_start' => 0,
                'query_base' => [],
            ];
        }

        $start = (string) ($opts['start'] ?? date('Y-m-01'));
        $end = (string) ($opts['end'] ?? date('Y-m-d'));
        $groupByCabang = !empty($opts['group_by_cabang']);
        $perPageOptions = [10, 20, 50];
        $perPage = isset($_GET['produk_per_page']) ? (int) $_GET['produk_per_page'] : 20;
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 20;
        }
        $page = isset($_GET['produk_page']) ? (int) $_GET['produk_page'] : 1;
        if ($page < 1) {
            $page = 1;
        }

        $storeIdsSql = implode(',', $cabangIds);
        $startEsc = addslashes($start);
        $endEsc = addslashes($end);

        $groupSql = $groupByCabang
            ? 'p.penjualan_cabang, p.barang_id, b.barang_nama'
            : 'p.barang_id, b.barang_nama';
        $selectCabang = $groupByCabang ? 'p.penjualan_cabang AS cabang,' : '';

        $whereSql = "
            p.penjualan_cabang IN ({$storeIdsSql})
            AND p.penjualan_date BETWEEN '{$startEsc}' AND '{$endEsc}'
        ";

        $countRows = $queryFn("
            SELECT COUNT(*) AS cnt FROM (
                SELECT 1
                FROM penjualan p
                JOIN barang b ON p.barang_id = b.barang_id
                WHERE {$whereSql}
                GROUP BY {$groupSql}
            ) AS grouped
        ");
        $total = (int) ($countRows[0]['cnt'] ?? 0);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $rankStart = $total > 0 ? $offset + 1 : 0;

        $items = $queryFn("
            SELECT {$selectCabang}
                   b.barang_nama,
                   SUM(p.barang_qty) AS qty_terjual,
                   SUM(p.barang_qty * p.keranjang_harga) AS total_penjualan
            FROM penjualan p
            JOIN barang b ON p.barang_id = b.barang_id
            WHERE {$whereSql}
            GROUP BY {$groupSql}
            ORDER BY qty_terjual DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");

        $filterType = (string) ($opts['filter_type'] ?? 'bulan');
        $queryBase = ['filter' => $filterType, 'produk_per_page' => $perPage];
        if ($filterType === 'bulan_pilih' && !empty($opts['selected_month'])) {
            $queryBase['month'] = (string) $opts['selected_month'];
        } elseif ($filterType === 'custom') {
            if (!empty($opts['custom_start'])) {
                $queryBase['start_date'] = (string) $opts['custom_start'];
            }
            if (!empty($opts['custom_end'])) {
                $queryBase['end_date'] = (string) $opts['custom_end'];
            }
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'per_page_options' => $perPageOptions,
            'total_pages' => $totalPages,
            'rank_start' => $rankStart,
            'query_base' => $queryBase,
            'group_by_cabang' => $groupByCabang,
        ];
    }
}

if (!function_exists('invesTerlaris_render')) {
    /**
     * @param array<string, mixed> $state
     * @param array<int, string> $tokoNames
     * @param array<int, array{name: string}> $storeFallback
     */
    function invesTerlaris_render(
        array $state,
        string $periodLabel,
        callable $formatRupiah,
        array $tokoNames = [],
        array $storeFallback = [],
        bool $showStoreBadge = true,
        string $subtitle = ''
    ): void {
        $items = $state['items'] ?? [];
        $total = (int) ($state['total'] ?? 0);
        $page = (int) ($state['page'] ?? 1);
        $perPage = (int) ($state['per_page'] ?? 20);
        $perPageOptions = $state['per_page_options'] ?? [10, 20, 50];
        $totalPages = (int) ($state['total_pages'] ?? 1);
        $rankStart = (int) ($state['rank_start'] ?? 0);
        $queryBase = $state['query_base'] ?? [];
        $groupByCabang = !empty($state['group_by_cabang']);
        ?>
        <div class="chart-card" id="top-products-section">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h5 class="mb-2 mb-md-0"><i class="fas fa-fire mr-2"></i>Produk Terlaris — <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8'); ?></h5>
                <?php if ($total > 0) : ?>
                <small class="text-muted">
                    <?= number_format($total); ?> barang<?= $subtitle !== '' ? ' · ' . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') : ''; ?>
                    · <strong><?= number_format($totalPages); ?> halaman</strong>
                </small>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php
                $rank = $rankStart;
                foreach ($items as $product) :
                    $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other'));
                    $cabangProduct = (int) ($product['cabang'] ?? 0);
                    $namaToko = invesToko_normalizeDisplayName(
                        $cabangProduct,
                        $tokoNames[$cabangProduct]
                            ?? ($storeFallback[$cabangProduct]['name'] ?? ('Cabang ' . $cabangProduct))
                    );
                ?>
                <div class="top-product-item">
                    <div class="top-product-rank <?= $rankClass; ?>"><?= $rank; ?></div>
                    <div class="top-product-info">
                        <div class="top-product-name" title="<?= htmlspecialchars((string) $product['barang_nama'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars((string) $product['barang_nama'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="top-product-qty">
                            <?= number_format((float) $product['qty_terjual']); ?> terjual · <?= $formatRupiah($product['total_penjualan']); ?>
                        </div>
                        <?php if ($showStoreBadge && $groupByCabang) : ?>
                        <span class="top-product-store"><?= htmlspecialchars($namaToko, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="top-product-revenue d-none d-md-block"><?= $formatRupiah($product['total_penjualan']); ?></div>
                </div>
                <?php
                    $rank++;
                endforeach;
                ?>
                <?php if ($total === 0) : ?>
                <div class="text-center text-muted py-4">Belum ada data penjualan pada periode ini</div>
                <?php endif; ?>
            </div>
            <?php if ($total > 0) : ?>
            <div class="top-product-pagination">
                <div class="page-info">
                    Halaman <strong><?= $page; ?></strong> dari <strong><?= $totalPages; ?></strong>
                    · baris <?= $rankStart; ?>–<?= min($rankStart + count($items) - 1, $total); ?>
                    dari <?= number_format($total); ?> barang
                </div>
                <form method="get" class="page-per-form">
                    <?php foreach ($queryBase as $key => $val) : ?>
                        <?php if ($key !== 'produk_per_page' && $key !== 'produk_page') : ?>
                    <input type="hidden" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" value="<?= htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="hidden" name="produk_page" value="1">
                    <label for="produk_per_page">Baris per halaman</label>
                    <select name="produk_per_page" id="produk_per_page" onchange="this.form.submit()">
                        <?php foreach ($perPageOptions as $opt) : ?>
                        <option value="<?= (int) $opt; ?>" <?= $perPage === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if ($totalPages > 1) : ?>
                <div class="page-links">
                    <a href="<?= htmlspecialchars(invesTerlaris_pageUrl($queryBase, max(1, $page - 1)), ENT_QUOTES, 'UTF-8'); ?>#top-products-section"
                       class="page-link-btn <?= $page <= 1 ? 'disabled' : ''; ?>" title="Halaman sebelumnya">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php foreach (invesTerlaris_pageNumbers($page, $totalPages) as $p) : ?>
                        <?php if ($p === '…') : ?>
                    <span class="page-link-btn disabled" style="pointer-events:none;border-style:dashed;">…</span>
                        <?php else : ?>
                    <a href="<?= htmlspecialchars(invesTerlaris_pageUrl($queryBase, (int) $p), ENT_QUOTES, 'UTF-8'); ?>#top-products-section"
                       class="page-link-btn <?= (int) $p === $page ? 'active' : ''; ?>"
                       title="Halaman <?= (int) $p; ?>"><?= (int) $p; ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <a href="<?= htmlspecialchars(invesTerlaris_pageUrl($queryBase, min($totalPages, $page + 1)), ENT_QUOTES, 'UTF-8'); ?>#top-products-section"
                       class="page-link-btn <?= $page >= $totalPages ? 'disabled' : ''; ?>" title="Halaman berikutnya">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
