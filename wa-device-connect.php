<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
error_reporting(0);

if ($levelLogin !== 'super admin' && $levelLogin !== 'admin') {
    echo "<script>alert('Akses ditolak!'); document.location.href = 'bo';</script>";
    exit;
}

require_once __DIR__ . '/api/wa-send-lib.php';
$waProviderConfigured = wa_provider_configured();
?>
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>WA Device — Engine Mandiri</h1>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <?php if (!$waProviderConfigured) : ?>
      <div class="alert alert-warning">
        Engine belum siap. Jalankan <code>wa-engine</code> dan pastikan <code>api/wa-app.config.php</code> (atau <code>wa-official.config.php</code>) berisi <code>local.api_secret</code> yang benar.
      </div>
      <?php endif; ?>

      <div class="row">
        <div class="col-lg-5">
          <div class="card card-primary card-outline">
            <div class="card-header">
              <h3 class="card-title">Status &amp; QR</h3>
            </div>
            <div class="card-body text-center">
              <div id="wa-status-badge" class="mb-3">
                <span class="badge badge-secondary">Memuat...</span>
              </div>
              <div id="wa-qr-wrap" style="min-height:320px;display:flex;align-items:center;justify-content:center;">
                <p class="text-muted mb-0" id="wa-qr-placeholder">Menunggu data engine...</p>
                <img id="wa-qr-img" src="" alt="QR WhatsApp" style="display:none;max-width:320px;border:1px solid #ddd;border-radius:8px;">
              </div>
              <p class="text-muted small mt-3 mb-0" id="wa-hint"></p>
            </div>
            <div class="card-footer">
              <button type="button" class="btn btn-danger btn-sm" id="btn-wa-logout">Logout Device</button>
              <button type="button" class="btn btn-default btn-sm" id="btn-wa-refresh">Refresh</button>
            </div>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Informasi Device</h3>
            </div>
            <div class="card-body p-0">
              <table class="table table-striped mb-0">
                <tbody id="wa-device-table">
                  <tr><td>Provider</td><td id="wa-info-provider">-</td></tr>
                  <tr><td>Engine online</td><td id="wa-info-online">-</td></tr>
                  <tr><td>Device status</td><td id="wa-info-status">-</td></tr>
                  <tr><td>Nomor</td><td id="wa-info-device">-</td></tr>
                  <tr><td>Nama</td><td id="wa-info-name">-</td></tr>
                  <tr><td>Pesan terkirim</td><td id="wa-info-messages">-</td></tr>
                  <tr><td>Base URL</td><td id="wa-info-url">-</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card card-outline card-info">
            <div class="card-header"><h3 class="card-title">Cara Menjalankan Engine</h3></div>
            <div class="card-body">
              <ol class="mb-0">
                <li>Salin <code>wa-engine/.env.example</code> → <code>wa-engine/.env</code></li>
                <li>Samakan <code>WA_API_SECRET</code> dengan <code>local.api_secret</code> di <code>api/wa-app.config.php</code></li>
                <li>Di terminal server: <code>cd wa-engine &amp;&amp; npm install &amp;&amp; npm start</code></li>
                <li>Scan QR di kartu kiri dengan WhatsApp → Perangkat Tertaut</li>
                <li>API publik: <code>/api/v1/send.php</code>, <code>/api/v1/device.php</code></li>
              </ol>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
(function () {
  var pollMs = 2500;
  var timer = null;

  function badgeFor(status) {
    if (status === 'connect') return '<span class="badge badge-success">Terhubung</span>';
    if (status === 'qr') return '<span class="badge badge-warning">Scan QR</span>';
    if (status === 'disconnect') return '<span class="badge badge-danger">Terputus</span>';
    return '<span class="badge badge-secondary">' + (status || 'unknown') + '</span>';
  }

  function setText(id, val) {
    var el = document.getElementById(id);
    if (el) el.textContent = val != null && val !== '' ? val : '-';
  }

  function loadStatus() {
    fetch('api/wa-engine-admin.php?action=status', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) {
          setText('wa-hint', data.message || 'Gagal memuat status engine');
          document.getElementById('wa-status-badge').innerHTML = badgeFor('error');
          return;
        }

        setText('wa-info-provider', data.provider_label || data.provider);
        setText('wa-info-online', data.engine_online ? 'Ya' : 'Tidak');
        setText('wa-info-url', data.local && data.local.base_url ? data.local.base_url : '-');
        setText('wa-hint', data.hint || '');

        var dev = data.device || {};
        setText('wa-info-status', dev.device_status || '-');
        setText('wa-info-device', dev.device || '-');
        setText('wa-info-name', dev.name || '-');
        setText('wa-info-messages', dev.messages || '0');

        document.getElementById('wa-status-badge').innerHTML = badgeFor(dev.device_status);

        if (dev.device_status === 'connect') {
          document.getElementById('wa-qr-img').style.display = 'none';
          document.getElementById('wa-qr-placeholder').style.display = 'block';
          document.getElementById('wa-qr-placeholder').textContent = 'Device sudah terhubung.';
        } else {
          loadQr();
        }
      })
      .catch(function () {
        document.getElementById('wa-status-badge').innerHTML = badgeFor('error');
      });
  }

  function loadQr() {
    fetch('api/wa-engine-admin.php?action=qr', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var d = data.data || {};
        var img = document.getElementById('wa-qr-img');
        var ph = document.getElementById('wa-qr-placeholder');

        if (d.device_status === 'connect') {
          img.style.display = 'none';
          ph.style.display = 'block';
          ph.textContent = 'Device sudah terhubung.';
          return;
        }

        if (d.qr) {
          img.src = d.qr;
          img.style.display = 'block';
          ph.style.display = 'none';
        } else {
          img.style.display = 'none';
          ph.style.display = 'block';
          ph.textContent = d.device_status === 'qr' ? 'Menunggu QR...' : 'Engine belum siap atau sedang menyambung.';
        }
      });
  }

  function startPoll() {
    loadStatus();
    if (timer) clearInterval(timer);
    timer = setInterval(function () {
      loadStatus();
    }, pollMs);
  }

  document.getElementById('btn-wa-refresh').addEventListener('click', startPoll);
  document.getElementById('btn-wa-logout').addEventListener('click', function () {
    if (!confirm('Logout device WA? Perlu scan QR ulang.')) return;
    fetch('api/wa-engine-admin.php?action=logout', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function () { startPoll(); });
  });

  startPoll();
})();
</script>

<?php include '_footer.php'; ?>
