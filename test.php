<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';  // Pastikan path ini benar
use PhpOffice\PhpSpreadsheet\Spreadsheet;

try {
    $spreadsheet = new Spreadsheet();
    echo "PhpSpreadsheet berhasil diakses!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
