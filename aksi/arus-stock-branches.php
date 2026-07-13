<?php
/**
 * Daftar cabang untuk kolom Arus Stock (urutan tampilan UI).
 * sold/stock = alias kolom SQL di barang-data-arus-stock.php
 */
return [
    ['cabang' => 0, 'label' => 'Gudang',       'sold' => 'soldGudang',     'stock' => 'stockGudang'],
    ['cabang' => 1, 'label' => 'Dukun',        'sold' => 'soldDukun',      'stock' => 'stockDukun'],
    ['cabang' => 3, 'label' => 'PP Srumbung',  'sold' => 'soldPPSrumbung', 'stock' => 'stockPPSrumbung'],
    ['cabang' => 2, 'label' => 'Pakis',        'sold' => 'soldPakis',      'stock' => 'stockPakis'],
    ['cabang' => 5, 'label' => 'Tegalrejo',    'sold' => 'soldTegalrejo',  'stock' => 'stockTegalrejo'],
];
