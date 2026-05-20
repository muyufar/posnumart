<?php
include '../aksi/koneksi.php';
header('Content-Type: application/json');

$id = $_POST['id'];

$query = "DELETE FROM audit_barang WHERE audit_id = '$id'";

if (mysqli_query($conn, $query)) {
    echo json_encode([
        'success' => true,
        'message' => 'Data audit berhasil dihapus'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menghapus data audit'
    ]);
}
