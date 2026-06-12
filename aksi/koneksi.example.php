<?php
// Salin file ini menjadi koneksi.php lalu sesuaikan per lingkungan.
// koneksi.php TIDAK di-commit ke Git (lihat .gitignore).

$servername = "localhost";
$username   = "root";
$password   = "";
$db         = "nama_database";

$conn = new mysqli($servername, $username, $password, $db);
date_default_timezone_set('Asia/Jakarta');
if ($conn && !$conn->connect_error) {
    mysqli_query($conn, "SET time_zone = '+07:00'");
}
