<?php
require __DIR__ . '/../bootstrap/paths.php';
chdir(NUMART_ROOT);
return require numart_path('modules/barang/lib/arus-stock-branches.php');
