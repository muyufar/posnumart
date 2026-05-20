<?php
/**
 * Form gambar barang (include di barang-add / barang-edit).
 * Variabel opsional: $barangGambarCurrent, $barangGambarReadonly (bool)
 */
$barangGambarCurrent = isset($barangGambarCurrent) ? trim((string) $barangGambarCurrent) : '';
$barangGambarReadonly = !empty($barangGambarReadonly);
$barangGambarUrl = '';
if ($barangGambarCurrent !== '' && function_exists('barang_gambar_public_url')) {
    $barangGambarUrl = barang_gambar_public_url($barangGambarCurrent);
} elseif ($barangGambarCurrent !== '') {
    $barangGambarUrl = $barangGambarCurrent;
}
?>
<div class="card card-secondary mb-3">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="fas fa-image"></i> Gambar Barang</h3>
    </div>
    <div class="card-body">
        <?php if ($barangGambarUrl !== '') : ?>
        <div class="mb-3 text-center">
            <img src="<?= htmlspecialchars($barangGambarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Gambar barang" class="img-thumbnail" style="max-height:180px;max-width:100%;object-fit:contain;">
            <input type="hidden" name="barang_gambar_lama" value="<?= htmlspecialchars($barangGambarCurrent, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <?php endif; ?>

        <?php if ($barangGambarReadonly) : ?>
        <p class="text-muted small mb-0">Gambar hanya dapat diubah dari akun pusat (cabang 0).</p>
        <?php else : ?>
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tabGambarLink" role="tab">Link URL</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tabGambarUpload" role="tab">Upload File</a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tabGambarLink" role="tabpanel">
                <input type="hidden" name="barang_gambar_mode" value="link" id="barang_gambar_mode_link">
                <div class="form-group mb-0">
                    <label for="barang_gambar_link">Link gambar (https://...)</label>
                    <input type="url" name="barang_gambar_link" class="form-control" id="barang_gambar_link"
                           placeholder="https://contoh.com/gambar-produk.jpg"
                           value="<?= ($barangGambarCurrent !== '' && preg_match('#^https?://#i', $barangGambarCurrent)) ? htmlspecialchars($barangGambarCurrent, ENT_QUOTES, 'UTF-8') : '' ?>">
                    <small class="text-muted">Kosongkan jika tidak pakai link.</small>
                </div>
            </div>
            <div class="tab-pane fade" id="tabGambarUpload" role="tabpanel">
                <div class="form-group mb-0">
                    <label for="barang_gambar_file">Upload gambar</label>
                    <input type="file" name="barang_gambar_file" class="form-control-file" id="barang_gambar_file" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small class="text-muted">JPG/PNG/GIF/WebP. Sistem otomatis kompres maks. <strong>200 KB</strong>.</small>
                </div>
            </div>
        </div>
        <?php if ($barangGambarUrl !== '') : ?>
        <div class="custom-control custom-checkbox mt-3">
            <input type="checkbox" class="custom-control-input" id="barang_gambar_hapus" name="barang_gambar_hapus" value="1">
            <label class="custom-control-label text-danger" for="barang_gambar_hapus">Hapus gambar saat ini</label>
        </div>
        <?php endif; ?>
        <script>
        (function () {
            var tabLink = document.querySelector('a[href="#tabGambarLink"]');
            var tabUp = document.querySelector('a[href="#tabGambarUpload"]');
            if (!tabLink || !tabUp) return;
            tabLink.addEventListener('shown.bs.tab', function () {
                var h = document.getElementById('barang_gambar_mode_link');
                if (h) h.value = 'link';
            });
            tabUp.addEventListener('shown.bs.tab', function () {
                var h = document.getElementById('barang_gambar_mode_link');
                if (h) h.value = 'upload';
            });
        })();
        </script>
        <?php endif; ?>
    </div>
</div>
