<?php
/**
 * Script untuk menghapus akun laba_kategori yang tidak valid
 * (kode_akun dan name null atau empty)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin != "admin" && $levelLogin != "super admin") {
    echo "
        <script>
            document.location.href = 'bo';
        </script>
    ";
    exit;
}

include 'aksi/koneksi.php';

// Ambil data yang memiliki kode_akun null atau empty dan name null atau empty
$query_null = "SELECT * FROM laba_kategori WHERE (kode_akun IS NULL OR kode_akun = '' OR kode_akun = '-') AND (name IS NULL OR name = '' OR name = '-')";
$result_null = mysqli_query($conn, $query_null);

$data_null = [];
if ($result_null) {
    while ($row = mysqli_fetch_assoc($result_null)) {
        $data_null[] = $row;
    }
}

// Proses penghapusan
$message = '';
$success_count = 0;
$error_count = 0;

if (isset($_POST['hapus_otomatis'])) {
    // Hapus semua akun null secara otomatis
    $delete_query = "DELETE FROM laba_kategori WHERE (kode_akun IS NULL OR kode_akun = '' OR kode_akun = '-') AND (name IS NULL OR name = '' OR name = '-')";
    
    if (mysqli_query($conn, $delete_query)) {
        $success_count = mysqli_affected_rows($conn);
        $message = "<div class='alert alert-success'><strong>Berhasil!</strong> $success_count akun null telah dihapus.</div>";
        
        // Refresh data
        $query_null = "SELECT * FROM laba_kategori WHERE (kode_akun IS NULL OR kode_akun = '' OR kode_akun = '-') AND (name IS NULL OR name = '' OR name = '-')";
        $result_null = mysqli_query($conn, $query_null);
        
        $data_null = [];
        if ($result_null) {
            while ($row = mysqli_fetch_assoc($result_null)) {
                $data_null[] = $row;
            }
        }
    } else {
        $error_count = 1;
        $message = "<div class='alert alert-danger'><strong>Error!</strong> Gagal menghapus: " . mysqli_error($conn) . "</div>";
    }
} else if (isset($_POST['hapus_manual'])) {
    // Hapus akun yang dipilih
    $ids_to_delete = $_POST['ids'] ?? [];
    
    if (count($ids_to_delete) > 0) {
        $ids_string = implode(',', array_map('intval', $ids_to_delete));
        $delete_query = "DELETE FROM laba_kategori WHERE id IN ($ids_string)";
        
        if (mysqli_query($conn, $delete_query)) {
            $success_count = mysqli_affected_rows($conn);
            $message = "<div class='alert alert-success'><strong>Berhasil!</strong> $success_count akun telah dihapus.</div>";
            
            // Refresh data
            $query_null = "SELECT * FROM laba_kategori WHERE (kode_akun IS NULL OR kode_akun = '' OR kode_akun = '-') AND (name IS NULL OR name = '' OR name = '-')";
            $result_null = mysqli_query($conn, $query_null);
            
            $data_null = [];
            if ($result_null) {
                while ($row = mysqli_fetch_assoc($result_null)) {
                    $data_null[] = $row;
                }
            }
        } else {
            $error_count = 1;
            $message = "<div class='alert alert-danger'><strong>Error!</strong> Gagal menghapus: " . mysqli_error($conn) . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'><strong>Peringatan!</strong> Tidak ada akun yang dipilih untuk dihapus.</div>";
    }
}
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Hapus Akun Laba Kategori (Null)</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="bo">Home</a></li>
                        <li class="breadcrumb-item"><a href="laba-kategori">Laba Kategori</a></li>
                        <li class="breadcrumb-item active">Hapus Akun Null</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($message): ?>
                <?php echo $message; ?>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Akun dengan Kode Akun dan Nama Kategori Null</h3>
                </div>
                <div class="card-body">
                    <?php if (count($data_null) > 0): ?>
                        <div class="alert alert-warning">
                            <h5><i class="icon fa fa-exclamation-triangle"></i> Peringatan</h5>
                            <p>Ditemukan <strong><?php echo count($data_null); ?></strong> akun dengan kode akun dan nama kategori null.</p>
                            <p><strong>Apakah aman menghapus akun ini?</strong></p>
                            <ul>
                                <li>Akun ini tidak memiliki identitas yang jelas (tidak ada kode_akun dan name)</li>
                                <li>Saldo di akun ini kemungkinan sudah tercatat di akun yang valid</li>
                                <li>Setelah menghapus, disarankan untuk <strong>menghitung ulang saldo</strong> dari transaksi operasional</li>
                            </ul>
                            <p><strong>Rekomendasi:</strong> Hapus akun null ini, kemudian jalankan <a href="recalculate-laba-kategori.php">Hitung Ulang Saldo</a> untuk memastikan saldo akurat.</p>
                        </div>

                        <form method="POST" action="" id="form-hapus">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th style="width: 5%;">
                                                <input type="checkbox" id="select-all">
                                            </th>
                                            <th>ID</th>
                                            <th>Kode Akun</th>
                                            <th>Nama Kategori</th>
                                            <th>Kategori</th>
                                            <th>Tipe Akun</th>
                                            <th>Cabang</th>
                                            <th>Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_saldo = 0;
                                        foreach ($data_null as $row): 
                                            $total_saldo += floatval($row['saldo']);
                                        ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="ids[]" value="<?php echo $row['id']; ?>">
                                                </td>
                                                <td><?php echo $row['id']; ?></td>
                                                <td><?php echo $row['kode_akun'] ?: '<span class="text-muted">-</span>'; ?></td>
                                                <td><?php echo $row['name'] ?: '<span class="text-muted">-</span>'; ?></td>
                                                <td>
                                                    <?php if ($row['kategori']): ?>
                                                        <span class="badge badge-<?php echo $row['kategori'] == 'aktiva' ? 'success' : 'danger'; ?>">
                                                            <?php echo strtoupper($row['kategori']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($row['tipe_akun']): ?>
                                                        <span class="badge badge-info">
                                                            <?php echo strtoupper($row['tipe_akun']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo isset($row['cabang']) ? $row['cabang'] : '0'; ?></td>
                                                <td>
                                                    <span class="text-<?php echo $row['saldo'] >= 0 ? 'success' : 'danger'; ?>">
                                                        Rp <?php echo number_format($row['saldo'], 0, ',', '.'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="7" class="text-right">Total Saldo:</th>
                                            <th>
                                                <span class="text-<?php echo $total_saldo >= 0 ? 'success' : 'danger'; ?>">
                                                    Rp <?php echo number_format($total_saldo, 0, ',', '.'); ?>
                                                </span>
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" name="hapus_otomatis" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus SEMUA akun null? Pastikan sudah backup database!')">
                                    <i class="fa fa-trash"></i> Hapus Semua Otomatis
                                </button>
                                <button type="submit" name="hapus_manual" class="btn btn-warning" onclick="return confirm('Yakin ingin menghapus akun yang dipilih?')">
                                    <i class="fa fa-trash"></i> Hapus yang Dipilih
                                </button>
                                <a href="laba-kategori.php" class="btn btn-default">
                                    <i class="fa fa-arrow-left"></i> Kembali
                                </a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fa fa-check-circle"></i> Tidak ada akun dengan kode akun dan nama kategori null.
                        </div>
                        <a href="laba-kategori.php" class="btn btn-default">
                            <i class="fa fa-arrow-left"></i> Kembali ke Laba Kategori
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    // Select all checkbox
    document.getElementById('select-all')?.addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('input[name="ids[]"]');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = this.checked;
        }, this);
    });
</script>

<?php include '_footer.php'; ?>
