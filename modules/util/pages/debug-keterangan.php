<?php 
include 'aksi/koneksi.php';

// Query untuk test (simple version - no collation issues)
$query = "SELECT 
    a.barang_kode,
    a.barang_nama,
    COUNT(DISTINCT a.barang_cabang) AS jumlah_cabang,
    
    -- Check individual cabang dengan subquery
    (SELECT COUNT(*) FROM barang WHERE barang_kode = a.barang_kode AND barang_cabang = 0 AND barang_status = '1') as has_gudang,
    (SELECT COUNT(*) FROM barang WHERE barang_kode = a.barang_kode AND barang_cabang = 1 AND barang_status = '1') as has_dukun,
    (SELECT COUNT(*) FROM barang WHERE barang_kode = a.barang_kode AND barang_cabang = 2 AND barang_status = '1') as has_pakis,
    (SELECT COUNT(*) FROM barang WHERE barang_kode = a.barang_kode AND barang_cabang = 3 AND barang_status = '1') as has_pp,
    (SELECT COUNT(*) FROM barang WHERE barang_kode = a.barang_kode AND barang_cabang = 5 AND barang_status = '1') as has_tegal

FROM barang a
WHERE a.barang_status = '1'
GROUP BY a.barang_kode
ORDER BY COUNT(DISTINCT a.barang_cabang) ASC, a.barang_kode
LIMIT 50";

$result = mysqli_query($conn, $query);

echo "<link rel='stylesheet' href='https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css'>";
echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css'>";
echo "<div class='container mt-4'>";
echo "<h2>Debug: Test Query Keterangan HTML</h2>";
echo "<p class='text-muted'>Item yang <strong>belum lengkap</strong> akan muncul di atas dengan tombol Duplikasi.</p>";
echo "<hr>";

echo "<table class='table table-bordered table-sm'>";
echo "<thead class='thead-dark'>";
echo "<tr>";
echo "<th>Kode</th>";
echo "<th>Nama</th>";
echo "<th>Cabang ID</th>";
echo "<th>Check Flags</th>";
echo "<th>Keterangan HTML (Output)</th>";
echo "</tr>";
echo "</thead>";
echo "<tbody>";

$count_lengkap = 0;
$count_belum = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $jumlah_cabang = $row['jumlah_cabang'];
    $is_lengkap = ($jumlah_cabang >= 5);
    
    if ($is_lengkap) {
        $count_lengkap++;
        $row_class = 'table-light';
    } else {
        $count_belum++;
        $row_class = 'table-warning';
    }
    
    // Get cabang tersedia untuk item ini
    $kode_escaped = mysqli_real_escape_string($conn, $row['barang_kode']);
    $query_cabang = "SELECT DISTINCT barang_cabang FROM barang WHERE barang_kode = '$kode_escaped' AND barang_status = '1' ORDER BY barang_cabang";
    $result_cabang = mysqli_query($conn, $query_cabang);
    $cabang_list = [];
    while ($r = mysqli_fetch_assoc($result_cabang)) {
        $cabang_list[] = $r['barang_cabang'];
    }
    $cabang_str = implode(',', $cabang_list);
    
    // Generate HTML
    $keterangan_html = '';
    if ($is_lengkap) {
        $keterangan_html = '<span class="badge badge-success">✓ Lengkap</span>';
    } else {
        $barang_kode = htmlspecialchars($row['barang_kode'], ENT_QUOTES);
        $barang_nama = htmlspecialchars($row['barang_nama'], ENT_QUOTES);
        $keterangan_html = '<span class="badge badge-warning">Belum Lengkap</span><br>' .
                          '<button class="btn btn-xs btn-info btn-duplikasi mt-1" ' .
                          'data-kode="' . $barang_kode . '" ' .
                          'data-nama="' . $barang_nama . '" ' .
                          'data-cabang="' . $cabang_str . '">' .
                          '<i class="fas fa-copy"></i> Duplikasi' .
                          '</button>';
    }
    
    echo "<tr class='$row_class'>";
    echo "<td>" . htmlspecialchars($row['barang_kode']) . "</td>";
    echo "<td>" . htmlspecialchars($row['barang_nama']) . "</td>";
    echo "<td><code>" . $cabang_str . "</code> (" . $jumlah_cabang . " cabang)</td>";
    echo "<td>";
    echo "G:" . ($row['has_gudang'] ? '✓' : '✗') . " ";
    echo "D:" . ($row['has_dukun'] ? '✓' : '✗') . " ";
    echo "Pa:" . ($row['has_pakis'] ? '✓' : '✗') . " ";
    echo "PP:" . ($row['has_pp'] ? '✓' : '✗') . " ";
    echo "T:" . ($row['has_tegal'] ? '✓' : '✗');
    echo "</td>";
    echo "<td>" . $keterangan_html . "</td>";
    echo "</tr>";
}

echo "</tbody>";
echo "</table>";

echo "<div class='alert alert-info'>";
echo "<strong>Summary:</strong><br>";
echo "✓ Lengkap: $count_lengkap item<br>";
echo "⚠ Belum Lengkap: $count_belum item (tombol Duplikasi seharusnya muncul)";
echo "</div>";

echo "</div>";

mysqli_close($conn);
?>
