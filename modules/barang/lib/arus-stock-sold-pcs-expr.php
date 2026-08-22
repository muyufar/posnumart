<?php
/**
 * Qty terjual per baris `penjualan` dalam PCS (satuan terkecil).
 * `barang_qty` / `barang_qty_keranjang` = qty menurut satuan transaksi;
 * `barang_qty_konversi_isi` = jumlah PCS per 1 satuan transaksi (mis. 10 untuk 1 RTG).
 * Alias tabel penjualan harus `p`.
 */
return <<<'SQL'
((CASE WHEN p.barang_qty > 0 THEN p.barang_qty ELSE p.barang_qty_keranjang END) * GREATEST(1, COALESCE(p.barang_qty_konversi_isi, 1)))
SQL;
