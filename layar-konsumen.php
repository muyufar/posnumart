<?php
include '_header-artibut.php';

$tokoNama = $dataTokoLogin['toko_nama'] ?? 'NUMART';
if ($tokoNama === '') {
    $tokoNama = 'NUMART';
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$displayState = pos_display_state($userId);
$nameTipeHarga = pos_display_tipe_label((int) $displayState['tipe_customer']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Layar Konsumen — <?= htmlspecialchars($tokoNama, ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="icon" type="img/png" sizes="32x32" href="https://eydcom.com/pos-kasir/dist/img/eyd-com.png">
  <style>
    * { box-sizing: border-box; }
    html, body {
      margin: 0;
      min-height: 100%;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: #0f172a;
      color: #e2e8f0;
    }
    .lk-wrap {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      padding: 1.25rem 1.5rem 1.5rem;
    }
    .lk-header {
      text-align: center;
      padding-bottom: 1rem;
      border-bottom: 2px solid #334155;
      margin-bottom: 1rem;
    }
    .lk-store {
      font-size: clamp(1.5rem, 3vw, 2.2rem);
      font-weight: 800;
      color: #5eead4;
      letter-spacing: -0.02em;
    }
    .lk-subtitle {
      margin-top: 0.35rem;
      font-size: clamp(1rem, 2vw, 1.35rem);
      color: #94a3b8;
      font-weight: 500;
    }
    .lk-meta {
      margin-top: 0.5rem;
      font-size: 0.9rem;
      color: #64748b;
    }
    .lk-meta strong {
      color: #cbd5e1;
    }
    .lk-payment-pill {
      display: inline-block;
      margin-top: 0.45rem;
      padding: 0.2rem 0.65rem;
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 700;
      letter-spacing: 0.02em;
    }
    .lk-payment-pill--cash {
      background: #1e3a5f;
      color: #93c5fd;
      border: 1px solid #3b82f6;
    }
    .lk-payment-pill--transfer {
      background: #134e4a;
      color: #99f6e4;
      border: 1px solid #14b8a6;
    }
    .lk-content-grid {
      flex: 1;
      display: flex;
      gap: 1rem;
      min-height: 0;
      align-items: stretch;
    }
    .lk-cart-col {
      flex: 1 1 55%;
      min-width: 0;
      display: flex;
      flex-direction: column;
    }
    .lk-qris-col {
      flex: 0 0 clamp(220px, 32vw, 360px);
      display: none;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      padding: 1rem;
      border-radius: 16px;
      border: 2px solid #14b8a6;
      background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
      text-align: center;
    }
    .lk-qris-col.is-visible {
      display: flex;
    }
    .lk-qris-title {
      font-size: clamp(1rem, 1.8vw, 1.25rem);
      font-weight: 800;
      color: #5eead4;
      margin: 0;
    }
    .lk-qris-hint {
      margin: 0;
      font-size: 0.85rem;
      color: #94a3b8;
      line-height: 1.4;
    }
    .lk-qris-img-wrap {
      background: #fff;
      padding: 0.65rem;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
      max-width: 100%;
    }
    .lk-qris-img-wrap img {
      display: block;
      max-width: 100%;
      max-height: min(42vh, 320px);
      width: auto;
      height: auto;
      margin: 0 auto;
    }
    .lk-qris-amount {
      font-size: clamp(1.2rem, 2.5vw, 1.75rem);
      font-weight: 800;
      color: #fbbf24;
    }
    .lk-qris-missing {
      color: #94a3b8;
      font-size: 0.9rem;
      padding: 1rem;
    }
    .lk-banner {
      display: none;
      margin-bottom: 0.85rem;
      padding: 0.65rem 1rem;
      border-radius: 10px;
      text-align: center;
      font-size: 0.95rem;
      font-weight: 600;
      animation: lkBannerIn 0.35s ease;
    }
    .lk-banner--tipe {
      background: #134e4a;
      border: 1px solid #14b8a6;
      color: #99f6e4;
    }
    .lk-banner--thanks {
      background: #14532d;
      border: 1px solid #22c55e;
      color: #bbf7d0;
    }
    @keyframes lkBannerIn {
      from { opacity: 0; transform: translateY(-6px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .lk-body {
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 0;
    }
    .lk-empty {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: #64748b;
      font-size: clamp(1.2rem, 2.5vw, 1.8rem);
      padding: 2rem;
      gap: 0.5rem;
    }
    .lk-empty-icon {
      font-size: 3rem;
      line-height: 1;
      opacity: 0.85;
    }
    .lk-empty-sub {
      font-size: 0.55em;
      color: #475569;
      max-width: 28rem;
    }
    .lk-table-wrap {
      flex: 1;
      overflow: auto;
      border-radius: 12px;
      border: 1px solid #334155;
      background: #1e293b;
    }
    table.lk-table {
      width: 100%;
      border-collapse: collapse;
      font-size: clamp(0.95rem, 1.8vw, 1.25rem);
    }
    .lk-table thead th {
      position: sticky;
      top: 0;
      background: #0d9488;
      color: #fff;
      padding: 0.85rem 1rem;
      text-align: left;
      font-size: 0.85em;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .lk-table tbody td {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #334155;
      vertical-align: middle;
    }
    .lk-table tbody tr:nth-child(even) {
      background: rgba(15, 23, 42, 0.45);
    }
    .lk-table tbody tr.lk-row-new {
      animation: lkFlash 0.6s ease;
    }
    @keyframes lkFlash {
      from { background: #134e4a; }
      to { background: transparent; }
    }
    .lk-col-no { width: 4%; text-align: center; color: #94a3b8; }
    .lk-col-qty { width: 10%; text-align: center; font-weight: 700; }
    .lk-col-harga, .lk-col-sub { width: 16%; text-align: right; white-space: nowrap; }
    .lk-footer {
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 2px solid #334155;
    }
    .lk-total-box {
      background: linear-gradient(135deg, #0d9488 0%, #0f766e 55%, #115e59 100%);
      border-radius: 16px;
      padding: 1.25rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
    }
    .lk-total-label {
      font-size: clamp(1.1rem, 2.2vw, 1.6rem);
      font-weight: 700;
      color: #ecfdf5;
    }
    .lk-total-hint {
      display: block;
      margin-top: 0.25rem;
      font-size: 0.75em;
      font-weight: 500;
      color: rgba(236, 253, 245, 0.8);
    }
    .lk-total-value {
      font-size: clamp(1.8rem, 4vw, 3rem);
      font-weight: 800;
      color: #fbbf24;
      letter-spacing: -0.02em;
      white-space: nowrap;
    }
    .lk-status {
      margin-top: 0.65rem;
      text-align: center;
      font-size: 0.8rem;
      color: #475569;
    }
    .lk-status.lk-live { color: #14b8a6; }
    .lk-status.lk-error { color: #f87171; }

    @media (max-width: 900px) {
      .lk-content-grid {
        flex-direction: column;
      }
      .lk-qris-col {
        flex: none;
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="lk-wrap">
    <header class="lk-header">
      <div class="lk-store" id="lk-toko"><?= htmlspecialchars($tokoNama, ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="lk-subtitle">Daftar Belanja Anda</div>
      <div class="lk-meta">Customer <strong id="lk-tipe"><?= htmlspecialchars($nameTipeHarga, ENT_QUOTES, 'UTF-8'); ?></strong> · Kasir <strong id="lk-kasir">—</strong></div>
      <div class="lk-payment-pill lk-payment-pill--cash" id="lk-payment-pill">Pembayaran: Cash</div>
    </header>

    <div class="lk-banner lk-banner--tipe" id="lk-banner-tipe"></div>
    <div class="lk-banner lk-banner--thanks" id="lk-banner-thanks">Terima kasih atas kunjungan Anda!</div>

    <div class="lk-body">
      <div class="lk-content-grid">
        <div class="lk-cart-col">
          <div class="lk-table-wrap" id="lk-table-wrap" style="display:none;">
            <table class="lk-table">
              <thead>
                <tr>
                  <th class="lk-col-no">No</th>
                  <th>Nama Barang</th>
                  <th class="lk-col-qty">Qty</th>
                  <th>Satuan</th>
                  <th class="lk-col-harga">Harga</th>
                  <th class="lk-col-sub">Sub Total</th>
                </tr>
              </thead>
              <tbody id="lk-tbody"></tbody>
            </table>
          </div>
          <div class="lk-empty" id="lk-empty">
            <span class="lk-empty-icon">🛒</span>
            <span id="lk-empty-text">Menunggu barang di-scan kasir…</span>
            <span class="lk-empty-sub" id="lk-empty-sub">Layar ini otomatis mengikuti transaksi kasir.</span>
          </div>
        </div>
        <aside class="lk-qris-col" id="lk-qris-col" aria-label="Pembayaran QRIS">
          <p class="lk-qris-title">Bayar via Transfer</p>
          <p class="lk-qris-hint">Scan QRIS di bawah ini</p>
          <div class="lk-qris-img-wrap" id="lk-qris-img-wrap" style="display:none;">
            <img id="lk-qris-img" src="" alt="QRIS Pembayaran">
          </div>
          <p class="lk-qris-missing" id="lk-qris-missing" style="display:none;">QRIS belum diatur untuk toko ini.</p>
          <div class="lk-qris-amount" id="lk-qris-amount">Rp 0</div>
        </aside>
      </div>
    </div>

    <footer class="lk-footer">
      <div class="lk-total-box">
        <div>
          <span class="lk-total-label">Total Belanja</span>
          <span class="lk-total-hint">Belum termasuk ongkir &amp; diskon</span>
        </div>
        <div class="lk-total-value" id="lk-total">Rp 0</div>
      </div>
      <div class="lk-status lk-live" id="lk-status">Memuat…</div>
    </footer>
  </div>

  <script>
  (function() {
    var API_URL = 'api/pos-customer-display-data.php';
    var pollMs = 1200;
    var lastItemSignature = '';
    var lastRevision = -1;
    var lastTipe = '';
    var lastEvent = '';
    var $tbody = document.getElementById('lk-tbody');
    var $empty = document.getElementById('lk-empty');
    var $emptyText = document.getElementById('lk-empty-text');
    var $emptySub = document.getElementById('lk-empty-sub');
    var $tableWrap = document.getElementById('lk-table-wrap');
    var $total = document.getElementById('lk-total');
    var $status = document.getElementById('lk-status');
    var $kasir = document.getElementById('lk-kasir');
    var $tipe = document.getElementById('lk-tipe');
    var $toko = document.getElementById('lk-toko');
    var $bannerTipe = document.getElementById('lk-banner-tipe');
    var $bannerThanks = document.getElementById('lk-banner-thanks');
    var $paymentPill = document.getElementById('lk-payment-pill');
    var $qrisCol = document.getElementById('lk-qris-col');
    var $qrisImgWrap = document.getElementById('lk-qris-img-wrap');
    var $qrisImg = document.getElementById('lk-qris-img');
    var $qrisMissing = document.getElementById('lk-qris-missing');
    var $qrisAmount = document.getElementById('lk-qris-amount');

    function formatRp(n) {
      return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    }

    function itemSignature(items) {
      return JSON.stringify(items.map(function(it) {
        return [it.nama, it.qty_label, it.subtotal].join('|');
      }));
    }

    function hideBanners() {
      $bannerTipe.style.display = 'none';
      $bannerThanks.style.display = 'none';
    }

    function showTipeBanner(label) {
      $bannerTipe.textContent = 'Tipe customer diubah menjadi: ' + label;
      $bannerTipe.style.display = 'block';
      $bannerThanks.style.display = 'none';
      setTimeout(function() {
        $bannerTipe.style.display = 'none';
      }, 4000);
    }

    function renderEmptyState(data) {
      $tableWrap.style.display = 'none';
      $empty.style.display = 'flex';
      $total.textContent = formatRp(0);

      if (data.event === 'checkout_done') {
        $bannerThanks.style.display = 'block';
        $emptyText.textContent = 'Transaksi selesai';
        $emptySub.textContent = 'Silakan tunggu, kasir akan memulai transaksi berikutnya.';
      } else {
        if (data.event !== 'tipe_changed') {
          hideBanners();
        }
        $emptyText.textContent = 'Menunggu barang di-scan kasir…';
        $emptySub.textContent = 'Customer ' + data.tipe_customer + ' · layar otomatis mengikuti kasir.';
      }
    }

    function renderPayment(data) {
      var label = data.payment_label || 'Cash';
      var isTransfer = Number(data.payment_type) === 1;
      $paymentPill.textContent = 'Pembayaran: ' + label;
      $paymentPill.className = 'lk-payment-pill ' + (isTransfer ? 'lk-payment-pill--transfer' : 'lk-payment-pill--cash');

      if (isTransfer && data.event !== 'checkout_done') {
        $qrisCol.classList.add('is-visible');
        $qrisAmount.textContent = formatRp(data.total);
        if (data.qris_url) {
          $qrisImg.src = data.qris_url;
          $qrisImgWrap.style.display = 'block';
          $qrisMissing.style.display = 'none';
        } else {
          $qrisImgWrap.style.display = 'none';
          $qrisMissing.style.display = 'block';
        }
      } else {
        $qrisCol.classList.remove('is-visible');
      }
    }

    function render(data) {
      if (data.toko_nama) {
        $toko.textContent = data.toko_nama;
      }
      $kasir.textContent = data.kasir_nama || '—';
      $tipe.textContent = data.tipe_customer || 'Umum';
      renderPayment(data);

      if (data.tipe_customer && data.tipe_customer !== lastTipe && lastTipe !== '') {
        showTipeBanner(data.tipe_customer);
      }
      lastTipe = data.tipe_customer || '';

      if (data.revision !== lastRevision) {
        if (data.event === 'tipe_changed') {
          showTipeBanner(data.tipe_customer);
        }
        lastRevision = data.revision;
      }
      lastEvent = data.event || '';

      var items = data.items || [];
      var sig = itemSignature(items);
      var itemsChanged = sig !== lastItemSignature;
      lastItemSignature = sig;

      if (!items.length) {
        renderEmptyState(data);
        return;
      }

      hideBanners();
      $empty.style.display = 'none';
      $tableWrap.style.display = 'block';

      var html = '';
      items.forEach(function(it) {
        html += '<tr' + (itemsChanged ? ' class="lk-row-new"' : '') + '>' +
          '<td class="lk-col-no">' + it.no + '</td>' +
          '<td>' + escapeHtml(it.nama) + '</td>' +
          '<td class="lk-col-qty">' + escapeHtml(it.qty_label) + '</td>' +
          '<td>' + escapeHtml(it.satuan) + '</td>' +
          '<td class="lk-col-harga">' + formatRp(it.harga) + '</td>' +
          '<td class="lk-col-sub">' + formatRp(it.subtotal) + '</td>' +
          '</tr>';
      });
      $tbody.innerHTML = html;
      $total.textContent = formatRp(data.total);
    }

    function escapeHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function fetchData() {
      fetch(API_URL, { credentials: 'same-origin', cache: 'no-store' })
        .then(function(res) {
          if (!res.ok) {
            throw new Error('HTTP ' + res.status);
          }
          return res.json();
        })
        .then(function(data) {
          if (!data.ok) {
            throw new Error('Data tidak valid');
          }
          render(data);
          $status.textContent = 'Otomatis · diperbarui ' + new Date().toLocaleTimeString('id-ID');
          $status.className = 'lk-status lk-live';
        })
        .catch(function() {
          $status.textContent = 'Gagal memuat data. Mencoba lagi…';
          $status.className = 'lk-status lk-error';
        });
    }

    fetchData();
    setInterval(fetchData, pollMs);
  })();
  </script>
</body>
</html>
