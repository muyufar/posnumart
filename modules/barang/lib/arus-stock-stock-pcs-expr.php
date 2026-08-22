<?php
/**
 * Fragment SQL: `barang_stock` dinormalisasi ke satuan terkecil (satuan_id / PCS).
 * Gunakan dengan alias tabel `b` pada query yang memuat kolom barang terkait.
 */
return <<<'SQL'
(COALESCE(CAST(NULLIF(TRIM(b.barang_stock), '') AS DECIMAL(18,4)), 0) * CASE
  WHEN TRIM(COALESCE(b.barang_satuan_id, '')) = '' OR TRIM(COALESCE(b.satuan_id, '')) = '' THEN 1
  WHEN TRIM(b.barang_satuan_id) = TRIM(b.satuan_id) THEN 1
  WHEN b.satuan_id_2 > 0 AND CAST(TRIM(b.barang_satuan_id) AS UNSIGNED) = b.satuan_id_2 AND b.satuan_isi_2 > 0 THEN CAST(b.satuan_isi_2 AS DECIMAL(18,4))
  WHEN b.satuan_id_3 > 0 AND CAST(TRIM(b.barang_satuan_id) AS UNSIGNED) = b.satuan_id_3 AND b.satuan_isi_3 > 0 THEN CAST(b.satuan_isi_3 AS DECIMAL(18,4))
  WHEN IFNULL(b.satuan_id_4, 0) > 0 AND CAST(TRIM(b.barang_satuan_id) AS UNSIGNED) = b.satuan_id_4 AND IFNULL(b.satuan_isi_4, 0) > 0 THEN CAST(b.satuan_isi_4 AS DECIMAL(18,4))
  ELSE 1
END)
SQL;
