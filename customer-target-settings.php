<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
error_reporting(0);

if ($levelLogin !== "super admin" && $levelLogin !== "admin") {
    echo "<script>alert('Akses ditolak!'); document.location.href = 'bo';</script>";
    exit;
}

$message = '';
$messageType = '';
if (isset($_GET['wa_ok']) && $_GET['wa_ok'] === 'hapus') {
    $message = 'Template WhatsApp berhasil dihapus.';
    $messageType = 'success';
} elseif (isset($_GET['wa_err']) && $_GET['wa_err'] === 'hapus') {
    $message = 'Template tidak dapat dihapus (hanya template cabang Anda).';
    $messageType = 'danger';
}

// Handle form submission
if (isset($_POST['save_target'])) {
    $targetHarian = floatval($_POST['target_harian']) ?? 0;
    $targetMingguan = floatval($_POST['target_mingguan']) ?? 0;
    $targetBulanan = floatval($_POST['target_bulanan']) ?? 100000;
    $targetTahunan = floatval($_POST['target_tahunan']) ?? 1200000;
    
    // Check if setting exists
    $existing = query("SELECT id FROM customer_target_settings WHERE cabang = $sessionCabang");
    
    if (!empty($existing)) {
        $query = "UPDATE customer_target_settings SET 
                    target_harian = $targetHarian,
                    target_mingguan = $targetMingguan,
                    target_bulanan = $targetBulanan,
                    target_tahunan = $targetTahunan,
                    updated_at = NOW()
                  WHERE cabang = $sessionCabang";
    } else {
        $query = "INSERT INTO customer_target_settings (cabang, target_harian, target_mingguan, target_bulanan, target_tahunan) 
                  VALUES ($sessionCabang, $targetHarian, $targetMingguan, $targetBulanan, $targetTahunan)";
    }
    
    if (mysqli_query($conn, $query)) {
        $message = 'Target berhasil disimpan!';
        $messageType = 'success';
    } else {
        $message = 'Gagal menyimpan target: ' . mysqli_error($conn);
        $messageType = 'danger';
    }
}

// Handle tag management
if (isset($_POST['add_tag'])) {
    $tagName = mysqli_real_escape_string($conn, $_POST['tag_name']);
    $tagColor = mysqli_real_escape_string($conn, $_POST['tag_color']);
    
    $query = "INSERT INTO customer_tags (cabang, tag_name, tag_color) VALUES ($sessionCabang, '$tagName', '$tagColor')
              ON DUPLICATE KEY UPDATE tag_color = '$tagColor'";
    
    if (mysqli_query($conn, $query)) {
        $message = 'Tag berhasil ditambahkan!';
        $messageType = 'success';
    } else {
        $message = 'Gagal menambahkan tag!';
        $messageType = 'danger';
    }
}

if (isset($_GET['delete_tag'])) {
    $tagId = intval($_GET['delete_tag']);
    mysqli_query($conn, "DELETE FROM customer_tags WHERE id = $tagId AND cabang = $sessionCabang");
    mysqli_query($conn, "DELETE FROM customer_tag_relations WHERE tag_id = $tagId");
    header("Location: customer-target-settings");
    exit;
}

// --- WA template CRUD (hanya template cabang sendiri; cabang 0 = default sistem, tidak dihapus/diubah) ---
if (isset($_POST['add_wa_template'])) {
    $tname = trim((string) ($_POST['wa_tpl_name'] ?? ''));
    $tcontent = (string) ($_POST['wa_tpl_content'] ?? '');
    if ($tname === '' || trim($tcontent) === '') {
        $message = 'Nama dan isi template wajib diisi.';
        $messageType = 'danger';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($tname, 'UTF-8') : strlen($tname)) > 100) {
        $message = 'Nama template maksimal 100 karakter.';
        $messageType = 'danger';
    } else {
        $tnameEsc = mysqli_real_escape_string($conn, $tname);
        $tcontentEsc = mysqli_real_escape_string($conn, $tcontent);
        $q = "INSERT INTO wa_templates (cabang, template_name, template_content, is_active) 
              VALUES ($sessionCabang, '$tnameEsc', '$tcontentEsc', 1)";
        if (mysqli_query($conn, $q)) {
            $message = 'Template WhatsApp berhasil ditambahkan.';
            $messageType = 'success';
        } else {
            $message = 'Gagal menambah template: ' . mysqli_error($conn);
            $messageType = 'danger';
        }
    }
}

if (isset($_POST['update_wa_template'])) {
    $tid = intval($_POST['wa_tpl_id'] ?? 0);
    $tname = trim((string) ($_POST['wa_tpl_name'] ?? ''));
    $tcontent = (string) ($_POST['wa_tpl_content'] ?? '');
    if ($tid <= 0 || $tname === '' || trim($tcontent) === '') {
        $message = 'Data template tidak valid.';
        $messageType = 'danger';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($tname, 'UTF-8') : strlen($tname)) > 100) {
        $message = 'Nama template maksimal 100 karakter.';
        $messageType = 'danger';
    } else {
        $tnameEsc = mysqli_real_escape_string($conn, $tname);
        $tcontentEsc = mysqli_real_escape_string($conn, $tcontent);
        $q = "UPDATE wa_templates SET 
                template_name = '$tnameEsc',
                template_content = '$tcontentEsc',
                updated_at = NOW()
              WHERE id = $tid AND cabang = $sessionCabang";
        if (mysqli_query($conn, $q) && mysqli_affected_rows($conn) > 0) {
            $message = 'Template WhatsApp berhasil diperbarui.';
            $messageType = 'success';
        } else {
            $message = 'Gagal memperbarui template (pastikan ini template cabang Anda).';
            $messageType = 'danger';
        }
    }
}

if (isset($_GET['delete_wa_template'])) {
    $tid = intval($_GET['delete_wa_template']);
    $ok = false;
    if ($tid > 0) {
        mysqli_query($conn, "DELETE FROM wa_templates WHERE id = $tid AND cabang = $sessionCabang");
        $ok = mysqli_affected_rows($conn) > 0;
    }
    header('Location: customer-target-settings?' . ($ok ? 'wa_ok=hapus' : 'wa_err=hapus'));
    exit;
}

require_once __DIR__ . '/api/wa-auto-schema.php';
require_once __DIR__ . '/api/wa-send-settings-lib.php';
require_once __DIR__ . '/api/wa-auto-blast-lib.php';
require_once __DIR__ . '/api/wa-message-lib.php';
wa_auto_below_target_ensure_schema($conn);
wa_send_settings_ensure_schema($conn);
wa_auto_blast_ensure_schema($conn);
wa_templates_seed_organic_defaults($conn);
mysqli_query(
    $conn,
    "INSERT IGNORE INTO wa_auto_target_reminder_settings (cabang, enabled, send_day, message_template) VALUES ($sessionCabang, 0, 26, NULL)"
);

if (isset($_POST['save_wa_auto_reminder'])) {
    $waEn = isset($_POST['wa_auto_enabled']) ? 1 : 0;
    $waDay = max(1, min(28, intval($_POST['wa_auto_send_day'] ?? 26)));
    $waMsg = (string) ($_POST['wa_auto_message'] ?? '');
    $waMsgEsc = mysqli_real_escape_string($conn, $waMsg);
    $waMaxBatch = max(1, min(25, intval($_POST['wa_max_contacts_per_batch'] ?? 10)));
    $waMinInterval = max(120, intval($_POST['wa_min_interval_minutes'] ?? 120));
    $waDelayPerContact = max(30, min(180, intval($_POST['wa_delay_seconds_per_contact'] ?? 45)));
    $waBlastMode = strtolower(trim((string) ($_POST['wa_blast_mode'] ?? 'below_target')));
    if (!in_array($waBlastMode, ['below_target', 'all_valid'], true)) {
        $waBlastMode = 'below_target';
    }
    $waBlastModeEsc = mysqli_real_escape_string($conn, $waBlastMode);
    $waHourMin = intval($_POST['wa_contacts_per_hour_min'] ?? 20);
    $waHourMax = intval($_POST['wa_contacts_per_hour_max'] ?? 30);
    $waDelayMin = intval($_POST['wa_delay_seconds_min'] ?? 90);
    $waDelayMax = intval($_POST['wa_delay_seconds_max'] ?? 180);
    $waDedupDays = intval($_POST['wa_dedup_days'] ?? 3);

    $qins = "INSERT INTO wa_auto_target_reminder_settings (cabang, enabled, send_day, message_template, blast_mode) 
        VALUES ($sessionCabang, $waEn, $waDay, '$waMsgEsc', '$waBlastModeEsc')
        ON DUPLICATE KEY UPDATE enabled = $waEn, send_day = $waDay, message_template = '$waMsgEsc', blast_mode = '$waBlastModeEsc'";
    $ok1 = mysqli_query($conn, $qins);
    wa_send_settings_save_limits($conn, $sessionCabang, $waMaxBatch, $waMinInterval, $waDelayPerContact);
    wa_auto_blast_save_scheduler($conn, $sessionCabang, $waHourMin, $waHourMax, $waDelayMin, $waDelayMax, $waDedupDays);

    if ($ok1) {
        $message = 'Pengaturan WA (otomatis & teknis pengiriman) disimpan.';
        $messageType = 'success';
    } else {
        $message = 'Gagal menyimpan pengingat otomatis: ' . mysqli_error($conn);
        $messageType = 'danger';
    }
}

// Get current settings
$settings = query("SELECT * FROM customer_target_settings WHERE cabang = $sessionCabang");
if (empty($settings)) {
    $settings = query("SELECT * FROM customer_target_settings WHERE cabang = 0");
}
$currentSettings = !empty($settings) ? $settings[0] : [
    'target_harian' => 0,
    'target_mingguan' => 0,
    'target_bulanan' => 100000,
    'target_tahunan' => 1200000
];

// Get tags
$tags = query("SELECT * FROM customer_tags WHERE cabang = $sessionCabang OR cabang = 0 ORDER BY tag_name");

// Get WA templates
$templates = query("SELECT * FROM wa_templates WHERE cabang = $sessionCabang OR cabang = 0 ORDER BY template_name");

$waAutoRows = query("SELECT * FROM wa_auto_target_reminder_settings WHERE cabang = $sessionCabang");
$waAuto = !empty($waAutoRows) ? $waAutoRows[0] : ['enabled' => 0, 'send_day' => 26, 'message_template' => null, 'blast_mode' => 'below_target'];
$waSendLimits = wa_send_settings_get($conn, $sessionCabang);
$waSched = wa_auto_blast_scheduler_get($conn, $sessionCabang);
$waBlastMode = strtolower(trim((string) ($waAuto['blast_mode'] ?? 'below_target')));
if (!in_array($waBlastMode, ['below_target', 'all_valid'], true)) {
    $waBlastMode = 'below_target';
}
?>

<style>
    .settings-card {
        border-radius: 15px;
        border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    .settings-card .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px 15px 0 0;
    }
    .target-input {
        font-size: 1.2rem;
        font-weight: bold;
        text-align: right;
    }
    .tag-badge {
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.9rem;
        margin: 3px;
        display: inline-flex;
        align-items: center;
    }
    .tag-delete {
        margin-left: 8px;
        cursor: pointer;
        opacity: 0.7;
    }
    .tag-delete:hover {
        opacity: 1;
    }
    .template-card {
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
    }
    .template-card:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .input-group-text {
        min-width: 50px;
        justify-content: center;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-cog"></i> Pengaturan Target Customer</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="bo">Home</a></li>
                        <li class="breadcrumb-item"><a href="customer-management">Customer Management</a></li>
                        <li class="breadcrumb-item active">Pengaturan</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($message) : ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?= $message ?>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Target Settings -->
                <div class="col-lg-6 mb-4">
                    <div class="card settings-card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-bullseye"></i> Target Belanja Customer</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <p class="text-muted mb-4">
                                    Atur target belanja minimum untuk customer. Customer yang belanjanya kurang dari target akan muncul di alert.
                                </p>
                                
                                <div class="form-group">
                                    <label>Target Harian</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">Rp</span>
                                        </div>
                                        <input type="number" name="target_harian" class="form-control target-input" 
                                               value="<?= $currentSettings['target_harian'] ?>" min="0">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Target Mingguan</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">Rp</span>
                                        </div>
                                        <input type="number" name="target_mingguan" class="form-control target-input" 
                                               value="<?= $currentSettings['target_mingguan'] ?>" min="0">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Target Bulanan <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">Rp</span>
                                        </div>
                                        <input type="number" name="target_bulanan" class="form-control target-input" 
                                               value="<?= $currentSettings['target_bulanan'] ?>" min="0" required>
                                    </div>
                                    <small class="text-muted">Target utama yang digunakan untuk alert</small>
                                </div>
                                
                                <div class="form-group">
                                    <label>Target Tahunan</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">Rp</span>
                                        </div>
                                        <input type="number" name="target_tahunan" class="form-control target-input" 
                                               value="<?= $currentSettings['target_tahunan'] ?>" min="0">
                                    </div>
                                </div>
                                
                                <button type="submit" name="save_target" class="btn btn-primary btn-block">
                                    <i class="fas fa-save"></i> Simpan Target
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tags Management -->
                <div class="col-lg-6 mb-4">
                    <div class="card settings-card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-tags"></i> Label / Tag Customer</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                Buat label untuk mengkategorikan customer (VIP, Loyal, dll)
                            </p>
                            
                            <form method="POST" action="" class="mb-4">
                                <div class="row">
                                    <div class="col-6">
                                        <input type="text" name="tag_name" class="form-control" placeholder="Nama Tag" required>
                                    </div>
                                    <div class="col-3">
                                        <input type="color" name="tag_color" class="form-control" value="#007bff" style="height: 38px;">
                                    </div>
                                    <div class="col-3">
                                        <button type="submit" name="add_tag" class="btn btn-success btn-block">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <div class="tags-container">
                                <?php foreach ($tags as $tag) : ?>
                                <span class="tag-badge" style="background: <?= $tag['tag_color'] ?>; color: white;">
                                    <?= $tag['tag_name'] ?>
                                    <a href="?delete_tag=<?= $tag['id'] ?>" class="tag-delete" onclick="return confirm('Hapus tag ini?')">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </span>
                                <?php endforeach; ?>
                                <?php if (empty($tags)) : ?>
                                <p class="text-muted">Belum ada tag</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WA blast otomatis (cron) -->
            <div class="card settings-card mb-4">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-robot"></i> WA Blast otomatis (cron)</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Pengiriman otomatis lewat cron: <strong>satu nomor per panggilan</strong>, disebar sepanjang hari
                        (<strong>20–30 kontak per jam</strong> dengan jeda detik acak). Kampanye dimulai pada tanggal yang Anda pilih,
                        lalu <strong>lanjut tiap hari</strong> sampai semua nomor valid terkirim (bisa 2–3 hari atau lebih).
                        Satu nomor maksimal <strong>1× per bulan</strong>; tidak boleh duplikat dalam <strong>2–3 hari</strong> terakhir.
                        Zona waktu: <code>Asia/Jakarta</code>.
                    </p>
                    <form method="POST" action="">
                        <input type="hidden" name="save_wa_auto_reminder" value="1">
                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="wa_auto_enabled" name="wa_auto_enabled" value="1"
                                <?= !empty($waAuto['enabled']) ? 'checked' : '' ?>>
                            <label class="custom-control-label font-weight-bold" for="wa_auto_enabled">Aktifkan blast otomatis</label>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Tanggal mulai tiap bulan</label>
                                <select name="wa_auto_send_day" class="form-control">
                                    <?php for ($d = 1; $d <= 28; $d++) : ?>
                                    <option value="<?= $d ?>" <?= (int) ($waAuto['send_day'] ?? 26) === $d ? 'selected' : '' ?>><?= $d ?></option>
                                    <?php endfor; ?>
                                </select>
                                <small class="text-muted">Setelah tanggal ini, pengiriman lanjut tiap hari sampai antrian habis.</small>
                            </div>
                            <div class="form-group col-md-8">
                                <label>Target penerima</label>
                                <select name="wa_blast_mode" class="form-control">
                                    <option value="below_target" <?= $waBlastMode === 'below_target' ? 'selected' : '' ?>>
                                        Customer aktif — belum capai target bulanan
                                    </option>
                                    <option value="all_valid" <?= $waBlastMode === 'all_valid' ? 'selected' : '' ?>>
                                        Semua customer aktif dengan nomor HP valid
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Template pesan (kosongkan = 3 varian organik default sistem)</label>
                            <textarea name="wa_auto_message" class="form-control" rows="10" placeholder="Satu varian, atau beberapa varian dipisah baris --- (rotasi otomatis per customer).&#10;Variabel: {nama_customer} {total_belanja} {nama_toko} {target} {kurang}"><?= htmlspecialchars((string) ($waAuto['message_template'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            <small class="text-muted">Disarankan: pesan singkat &amp; personal, hindari emoji berlebihan, sertakan <code>Balas STOP</code> di akhir. Pisahkan varian dengan baris <code>---</code> di baris sendiri.</small>
                        </div>
                        <hr>
                        <h6 class="font-weight-bold"><i class="fas fa-tachometer-alt"></i> Scheduler cron (otomatis)</h6>
                        <p class="small text-muted mb-2">Aturan ini dipakai cron. Tiap jam sistem mengacak kuota 20–30, lalu mengirim satu per satu dengan jeda detik acak.
                        <strong>Beberapa cabang aktif + satu engine WA:</strong> sistem mengirim <strong>maks. 1 WA per panggilan cron</strong>, bergiliran antar cabang, dengan <strong>kuota &amp; jeda global</strong> (mencegah dua cabang kirim di detik yang sama).</p>
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Kontak / jam (min)</label>
                                <input type="number" name="wa_contacts_per_hour_min" class="form-control" min="1" max="30"
                                       value="<?= (int) ($waSched['contacts_per_hour_min'] ?? 20) ?>" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Kontak / jam (maks)</label>
                                <input type="number" name="wa_contacts_per_hour_max" class="form-control" min="1" max="30"
                                       value="<?= (int) ($waSched['contacts_per_hour_max'] ?? 30) ?>" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Jeda antar nomor min (detik)</label>
                                <input type="number" name="wa_delay_seconds_min" class="form-control" min="30" max="600"
                                       value="<?= (int) ($waSched['delay_seconds_min'] ?? 90) ?>" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Jeda antar nomor maks (detik)</label>
                                <input type="number" name="wa_delay_seconds_max" class="form-control" min="30" max="600"
                                       value="<?= (int) ($waSched['delay_seconds_max'] ?? 180) ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Hari tanpa duplikat nomor</label>
                                <input type="number" name="wa_dedup_days" class="form-control" min="2" max="7"
                                       value="<?= (int) ($waSched['dedup_days'] ?? 3) ?>" required>
                                <small class="text-muted">2–3 hari disarankan; tetap 1× per bulan.</small>
                            </div>
                            <?php if (!empty($waSched['next_send_at'])) : ?>
                            <div class="form-group col-md-8">
                                <label>Status antrian</label>
                                <p class="form-control-plaintext small mb-0">
                                    Kirim berikutnya: <strong><?= htmlspecialchars($waSched['next_send_at'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if (!empty($waSendLimits['last_send_at'])) : ?>
                                    · Terakhir terkirim: <strong><?= htmlspecialchars($waSendLimits['last_send_at'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <hr>
                        <h6 class="font-weight-bold"><i class="fas fa-sliders-h"></i> Blast manual (halaman WA Blast)</h6>
                        <p class="small text-muted">Pengaturan di bawah hanya untuk kirim manual. <strong>Jam kerja 07:00–21:00</strong>, <strong>global lock</strong> dengan cron (satu engine), jeda antar nomor minimal <strong>45 detik</strong> disarankan.</p>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Maks. kontak per sesi</label>
                                <input type="number" name="wa_max_contacts_per_batch" class="form-control" min="1" max="25"
                                       value="<?= (int) ($waSendLimits['max_contacts_per_batch'] ?? 10) ?>" required>
                                <small class="text-muted">Disarankan 10 atau kurang.</small>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Jeda antar nomor manual (detik)</label>
                                <input type="number" name="wa_delay_seconds_per_contact" class="form-control" min="30" max="180" step="1"
                                       value="<?= (int) ($waSendLimits['delay_seconds_per_contact'] ?? 45) ?>" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Jeda antar sesi manual (menit)</label>
                                <input type="number" name="wa_min_interval_minutes" class="form-control" min="120" step="1"
                                       value="<?= (int) ($waSendLimits['min_interval_minutes'] ?? 120) ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan pengaturan</button>
                    </form>
                    <hr>
                    <h6><i class="fas fa-clock"></i> Penjadwalan server (cron / Task Scheduler)</h6>
                    <p class="small text-muted mb-2">
                        Panggil URL berikut <strong>setiap 2–3 menit</strong> (bukan sekali sehari), agar 20–30 kontak/jam terdistribusi dengan jeda acak.
                        Kunci rahasia: <code>api/wa-cron-key.php</code> (lihat <code>api/wa-cron-key.example.php</code>).
                    </p>
                    <p class="small mb-1"><strong>Uji tanpa kirim WA:</strong></p>
                    <code class="small d-block mb-2 text-break">api/wa-auto-blast-cron.php?key=KUNCI_ANDA&amp;dry_run=1</code>
                    <p class="small mb-1"><strong>Produksi:</strong></p>
                    <code class="small d-block text-break">api/wa-auto-blast-cron.php?key=KUNCI_ANDA</code>
                    <p class="small text-muted mt-2 mb-0">Endpoint lama <code>wa-auto-below-target-cron.php</code> tetap berfungsi (logika sama).</p>
                    <p class="small mb-0 mt-2">
                        Pantau status pengiriman: <a href="customer-wa-cron-monitor"><strong>Monitor Cron WA</strong></a>
                        (sudah/belum terkirim, kuota per jam, kesehatan cron).
                    </p>
                </div>
            </div>

            <!-- WA Templates -->
            <div class="card settings-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                    <h3 class="card-title mb-0"><i class="fab fa-whatsapp"></i> Template Pesan WhatsApp</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Template untuk WA Blast. Variabel: <code>{nama_customer}</code>, <code>{total_belanja}</code>, <code>{nama_toko}</code>.
                        Sertakan <code>Balas STOP</code> di akhir pesan. <strong>Template default</strong> (badge abu) bersama semua cabang.
                    </p>

                    <div class="card border-primary mb-4">
                        <div class="card-header bg-light py-2">
                            <strong><i class="fas fa-plus-circle text-primary"></i> Tambah template baru</strong>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="add_wa_template" value="1">
                                <div class="form-group">
                                    <label>Nama template</label>
                                    <input type="text" name="wa_tpl_name" class="form-control" maxlength="100" placeholder="Contoh: Promo weekend" required>
                                </div>
                                <div class="form-group mb-0">
                                    <label>Isi pesan</label>
                                    <textarea name="wa_tpl_content" class="form-control" rows="5" placeholder="Halo {nama_customer}! ..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-save"></i> Simpan template
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="row">
                        <?php foreach ($templates as $tpl) :
                            $tplId = (int) $tpl['id'];
                            $tplCabang = (int) ($tpl['cabang'] ?? 0);
                            $isGlobal = ($tplCabang === 0);
                            $canMutate = !$isGlobal && $tplCabang === (int) $sessionCabang;
                            ?>
                        <div class="col-md-6 mb-3">
                            <div class="template-card h-100 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="mb-0"><?= htmlspecialchars($tpl['template_name'], ENT_QUOTES, 'UTF-8') ?></h5>
                                    <?php if ($isGlobal) : ?>
                                    <span class="badge badge-secondary">Default</span>
                                    <?php else : ?>
                                    <span class="badge badge-info">Cabang</span>
                                    <?php endif; ?>
                                </div>
                                <pre class="flex-grow-1 mb-3" style="white-space: pre-wrap; background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 0.85rem; max-height: 220px; overflow-y: auto;"><?= htmlspecialchars($tpl['template_content'], ENT_QUOTES, 'UTF-8') ?></pre>
                                <textarea id="wa-tpl-src-<?= $tplId ?>" class="d-none" readonly><?= htmlspecialchars($tpl['template_content'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                <div class="mt-auto d-flex flex-wrap align-items-center" style="gap: 6px;">
                                    <a href="customer-wa-blast?template=<?= $tplId ?>" class="btn btn-sm btn-success">
                                        <i class="fab fa-whatsapp"></i> Gunakan
                                    </a>
                                    <?php if ($canMutate) : ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-wa-tpl"
                                            data-id="<?= $tplId ?>"
                                            data-name="<?= htmlspecialchars($tpl['template_name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fas fa-edit"></i> Ubah
                                    </button>
                                    <a href="?delete_wa_template=<?= $tplId ?>" class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Hapus template ini?');">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (empty($templates)) : ?>
                    <p class="text-muted mb-0">Belum ada template.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal edit template -->
            <div class="modal fade" id="modalEditWaTemplate" tabindex="-1" role="dialog" aria-labelledby="modalEditWaTemplateLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <form method="POST" action="">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalEditWaTemplateLabel"><i class="fas fa-edit"></i> Ubah template</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="update_wa_template" value="1">
                                <input type="hidden" name="wa_tpl_id" id="edit_wa_tpl_id" value="">
                                <div class="form-group">
                                    <label>Nama template</label>
                                    <input type="text" name="wa_tpl_name" id="edit_wa_tpl_name" class="form-control" maxlength="100" required>
                                </div>
                                <div class="form-group mb-0">
                                    <label>Isi pesan</label>
                                    <textarea name="wa_tpl_content" id="edit_wa_tpl_content" class="form-control" rows="8" required></textarea>
                                    <small class="text-muted">Variabel: {nama_customer}, {total_belanja}, {nama_toko}</small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<script>
$(function () {
    $(document).on('click', '.btn-edit-wa-tpl', function () {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var body = '';
        var $src = $('#wa-tpl-src-' + id);
        if ($src.length) {
            body = $src.val();
        }
        $('#edit_wa_tpl_id').val(id);
        $('#edit_wa_tpl_name').val(name);
        $('#edit_wa_tpl_content').val(body);
        $('#modalEditWaTemplate').modal('show');
    });
});
</script>

<?php include '_footer.php'; ?>
</body>
</html>


