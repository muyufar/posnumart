<?php

include '_header.php';
include '_nav.php';
include '_sidebar.php';
require_once 'aksi/marketplace-lib.php';

if (!marketplace_can_access((string) $levelLogin)) {
    echo "<script>document.location.href = 'bo';</script>";
    exit;
}

$tableOk = false;
$check = mysqli_query($conn, "SHOW TABLES LIKE 'marketplace_diskon'");
if ($check && mysqli_num_rows($check) > 0) {
    $tableOk = true;
}

if ($tableOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $kode = mysqli_real_escape_string($conn, trim((string) ($_POST['barang_kode'] ?? '')));
        $tipe = in_array($_POST['diskon_tipe'] ?? '', ['persen', 'harga'], true) ? $_POST['diskon_tipe'] : 'persen';
        $nilai = (float) ($_POST['diskon_nilai'] ?? 0);
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        $mulai = trim((string) ($_POST['mulai'] ?? ''));
        $selesai = trim((string) ($_POST['selesai'] ?? ''));
        $ket = mysqli_real_escape_string($conn, trim((string) ($_POST['keterangan'] ?? '')));
        $mulaiSql = $mulai !== '' ? "'".mysqli_real_escape_string($conn, $mulai)."'" : 'NULL';
        $selesaiSql = $selesai !== '' ? "'".mysqli_real_escape_string($conn, $selesai)."'" : 'NULL';
        $id = (int) ($_POST['diskon_id'] ?? 0);

        if ($kode !== '') {
            if ($id > 0) {
                mysqli_query($conn, "UPDATE marketplace_diskon SET
                    barang_kode='$kode', diskon_tipe='$tipe', diskon_nilai=$nilai, aktif=$aktif,
                    mulai=$mulaiSql, selesai=$selesaiSql, keterangan='$ket'
                    WHERE diskon_id=$id");
            } else {
                mysqli_query($conn, "INSERT INTO marketplace_diskon
                    (barang_kode, diskon_tipe, diskon_nilai, aktif, mulai, selesai, keterangan)
                    VALUES ('$kode','$tipe',$nilai,$aktif,$mulaiSql,$selesaiSql,'$ket')");
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['diskon_id'] ?? 0);
        if ($id > 0) {
            mysqli_query($conn, "DELETE FROM marketplace_diskon WHERE diskon_id=$id");
        }
    }
    echo "<script>document.location.href='marketplace-diskon';</script>";
    exit;
}

$rows = [];
if ($tableOk) {
    $q = mysqli_query($conn, "SELECT * FROM marketplace_diskon ORDER BY aktif DESC, diskon_id DESC LIMIT 200");
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $rows[] = $r;
        }
    }
}
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <h1><i class="fas fa-percent"></i> Diskon Belanja Online</h1>
      <p class="text-muted">Produk dengan diskon aktif tampil di beranda <strong>belanja.numart.id</strong>.</p>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <?php if (!$tableOk) { ?>
        <div class="alert alert-warning">
          Tabel <code>marketplace_diskon</code> belum ada. Jalankan SQL:
          <code>db/marketplace_diskon.sql</code>
        </div>
      <?php } else { ?>
        <div class="card card-primary">
          <div class="card-header"><h3 class="card-title">Tambah / ubah diskon</h3></div>
          <form method="post" class="card-body">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="diskon_id" id="diskon_id" value="0">
            <div class="form-row">
              <div class="form-group col-md-3">
                <label>Kode barang</label>
                <input type="text" name="barang_kode" id="barang_kode" class="form-control" required>
              </div>
              <div class="form-group col-md-2">
                <label>Tipe</label>
                <select name="diskon_tipe" id="diskon_tipe" class="form-control">
                  <option value="persen">Persen %</option>
                  <option value="harga">Harga tetap (Rp)</option>
                </select>
              </div>
              <div class="form-group col-md-2">
                <label>Nilai</label>
                <input type="number" step="0.01" name="diskon_nilai" id="diskon_nilai" class="form-control" required>
              </div>
              <div class="form-group col-md-2">
                <label>Mulai</label>
                <input type="date" name="mulai" id="mulai" class="form-control">
              </div>
              <div class="form-group col-md-2">
                <label>Selesai</label>
                <input type="date" name="selesai" id="selesai" class="form-control">
              </div>
              <div class="form-group col-md-1 d-flex align-items-end">
                <div class="form-check">
                  <input type="checkbox" name="aktif" id="aktif" class="form-check-input" checked>
                  <label class="form-check-label" for="aktif">Aktif</label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Keterangan</label>
              <input type="text" name="keterangan" id="keterangan" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Simpan</button>
          </form>
        </div>

        <div class="card">
          <div class="card-body table-responsive">
            <table class="table table-bordered table-sm">
              <thead>
                <tr>
                  <th>Kode</th><th>Tipe</th><th>Nilai</th><th>Periode</th><th>Aktif</th><th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r) { ?>
                  <tr>
                    <td><?= htmlspecialchars($r['barang_kode'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($r['diskon_tipe'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($r['diskon_nilai'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars(($r['mulai'] ?? '-') . ' s/d ' . ($r['selesai'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= (int) $r['aktif'] ? 'Ya' : 'Tidak'; ?></td>
                    <td>
                      <button type="button" class="btn btn-xs btn-info btn-edit" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8'); ?>'>Edit</button>
                      <form method="post" style="display:inline" onsubmit="return confirm('Hapus?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="diskon_id" value="<?= (int) $r['diskon_id']; ?>">
                        <button type="submit" class="btn btn-xs btn-danger">Hapus</button>
                      </form>
                    </td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php } ?>
    </div>
  </section>
</div>

<script>
document.querySelectorAll('.btn-edit').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var r = JSON.parse(this.getAttribute('data-row'));
    document.getElementById('diskon_id').value = r.diskon_id;
    document.getElementById('barang_kode').value = r.barang_kode;
    document.getElementById('diskon_tipe').value = r.diskon_tipe;
    document.getElementById('diskon_nilai').value = r.diskon_nilai;
    document.getElementById('mulai').value = r.mulai || '';
    document.getElementById('selesai').value = r.selesai || '';
    document.getElementById('keterangan').value = r.keterangan || '';
    document.getElementById('aktif').checked = parseInt(r.aktif, 10) === 1;
    window.scrollTo(0, 0);
  });
});
</script>

<?php include '_footer.php'; ?>
