<?php
include '_header-artibut.php';

$tokoNama = $dataTokoLogin['toko_nama'] ?? 'NUMART';
if ($tokoNama === '') {
    $tokoNama = 'NUMART';
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$displayState = pos_display_state($userId);
$nameTipeHarga = pos_display_tipe_label((int) $displayState['tipe_customer']);
$beliCtx = beli_langsung_ctx_get($userId);
$lkCustomerId = $beliCtx['customer_id'] !== null ? (int) $beliCtx['customer_id'] : 0;
$lkCustomerNama = beli_langsung_customer_nama($conn, $lkCustomerId, (int) $sessionCabang);
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
      height: 100vh;
      display: flex;
      flex-direction: column;
      padding: 0.75rem 1rem 0.5rem;
      overflow: hidden;
    }
    .lk-header {
      text-align: center;
      padding-bottom: 0.55rem;
      border-bottom: 2px solid #334155;
      margin-bottom: 0.55rem;
      flex-shrink: 0;
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
    .lk-summary-bar {
      flex-shrink: 0;
      margin-bottom: 0.65rem;
      z-index: 20;
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0.5rem;
    }
    .lk-sum-card {
      background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
      border: 1px solid #334155;
      border-radius: 12px;
      padding: 0.55rem 0.65rem;
      text-align: center;
      min-width: 0;
    }
    .lk-sum-card--main {
      background: linear-gradient(135deg, #0d9488 0%, #0f766e 55%, #115e59 100%);
      border-color: #14b8a6;
    }
    .lk-sum-card--change {
      background: linear-gradient(135deg, #14532d 0%, #166534 100%);
      border-color: #22c55e;
    }
    .lk-sum-label {
      display: block;
      font-size: 0.68rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: #94a3b8;
      margin-bottom: 0.2rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .lk-sum-card--main .lk-sum-label,
    .lk-sum-card--change .lk-sum-label {
      color: rgba(236, 253, 245, 0.85);
    }
    .lk-sum-value {
      display: block;
      font-size: clamp(0.95rem, 1.8vw, 1.35rem);
      font-weight: 800;
      color: #e2e8f0;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .lk-sum-card--main .lk-sum-value {
      color: #fbbf24;
      font-size: clamp(1.05rem, 2vw, 1.55rem);
    }
    .lk-sum-card--change .lk-sum-value {
      color: #86efac;
    }
    .lk-content-grid {
      flex: 1;
      display: flex;
      gap: 1rem;
      min-height: 0;
      align-items: stretch;
      overflow: hidden;
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
      flex-shrink: 0;
      margin-top: 0.4rem;
      padding-top: 0.35rem;
    }
    .lk-status {
      margin-top: 0;
      text-align: center;
      font-size: 0.72rem;
      color: #475569;
    }
    .lk-status.lk-live { color: #14b8a6; }
    .lk-status.lk-error { color: #f87171; }

    @media (max-width: 900px) {
      .lk-summary-bar {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .lk-sum-card--main {
        grid-column: 1 / -1;
      }
      .lk-content-grid {
        flex-direction: column;
      }
      .lk-qris-col {
        flex: none;
        width: 100%;
      }
    }

    .lk-display-bar {
      position: fixed;
      top: 0.55rem;
      right: 0.55rem;
      z-index: 1000;
      display: flex;
      gap: 0.45rem;
    }
    .lk-btn-display {
      appearance: none;
      border: 1px solid #14b8a6;
      background: rgba(13, 148, 136, 0.92);
      color: #ecfdf5;
      font: inherit;
      font-size: 0.82rem;
      font-weight: 700;
      padding: 0.45rem 0.85rem;
      border-radius: 999px;
      cursor: pointer;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
      transition: background 0.15s ease, transform 0.15s ease;
    }
    .lk-btn-display:hover {
      background: rgba(20, 184, 166, 0.98);
      transform: translateY(-1px);
    }
    .lk-btn-display:active {
      transform: translateY(0);
    }
    .lk-display-hint {
      position: fixed;
      inset: 0;
      z-index: 999;
      background: rgba(15, 23, 42, 0.94);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .lk-display-hint[hidden] {
      display: none !important;
    }
    .lk-display-hint-card {
      max-width: 26rem;
      text-align: center;
      background: #1e293b;
      border: 1px solid #334155;
      border-radius: 16px;
      padding: 1.5rem 1.25rem;
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45);
    }
    .lk-display-hint-title {
      margin: 0 0 0.5rem;
      font-size: 1.15rem;
      font-weight: 800;
      color: #5eead4;
    }
    .lk-display-hint-text {
      margin: 0 0 1rem;
      font-size: 0.92rem;
      color: #94a3b8;
      line-height: 1.45;
    }
    .lk-display-hint-btn {
      appearance: none;
      border: none;
      background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
      color: #fff;
      font: inherit;
      font-size: 1rem;
      font-weight: 700;
      padding: 0.7rem 1.25rem;
      border-radius: 12px;
      cursor: pointer;
      width: 100%;
    }
    html.lk-pseudo-fs,
    html.lk-pseudo-fs body {
      overflow: hidden;
    }
    .lk-pseudo-fs .lk-wrap {
      height: 100vh;
      padding: 0.5rem 0.75rem 0.35rem;
    }
    .lk-pseudo-fs .lk-display-bar {
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.2s ease;
    }
    .lk-pseudo-fs .lk-display-bar:hover {
      opacity: 1;
      pointer-events: auto;
    }
  </style>
</head>
<body>
  <div class="lk-display-bar" id="lk-display-bar">
    <button type="button" class="lk-btn-display" id="lk-btn-second-monitor" title="Pindahkan jendela ke monitor kedua dan layar penuh">
      Monitor 2 + Layar Penuh
    </button>
  </div>

  <div class="lk-display-hint" id="lk-display-hint" hidden>
    <div class="lk-display-hint-card">
      <p class="lk-display-hint-title">Aktifkan layar penuh</p>
      <p class="lk-display-hint-text">Browser membutuhkan satu klik untuk mode layar penuh. Tekan tombol di bawah setelah jendela sudah di monitor pelanggan.</p>
      <button type="button" class="lk-display-hint-btn" id="lk-display-hint-btn">Layar Penuh di Monitor 2</button>
    </div>
  </div>

  <div class="lk-wrap">
    <header class="lk-header">
      <div class="lk-store" id="lk-toko"><?= htmlspecialchars($tokoNama, ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="lk-subtitle">Daftar Belanja Anda</div>
      <div class="lk-meta">Customer <strong id="lk-customer-nama"><?= htmlspecialchars($lkCustomerNama, ENT_QUOTES, 'UTF-8'); ?></strong> · Tipe <strong id="lk-tipe"><?= htmlspecialchars($nameTipeHarga, ENT_QUOTES, 'UTF-8'); ?></strong> · Kasir <strong id="lk-kasir">—</strong></div>
      <div class="lk-payment-pill lk-payment-pill--cash" id="lk-payment-pill">Pembayaran: Cash</div>
    </header>

    <div class="lk-banner lk-banner--tipe" id="lk-banner-tipe"></div>
    <div class="lk-banner lk-banner--thanks" id="lk-banner-thanks">Terima kasih atas kunjungan Anda!</div>

    <div class="lk-summary-bar" aria-live="polite">
      <div class="lk-sum-card">
        <span class="lk-sum-label">Jumlah Item</span>
        <strong class="lk-sum-value" id="lk-item-count">0</strong>
      </div>
      <div class="lk-sum-card">
        <span class="lk-sum-label">Total Belanja</span>
        <strong class="lk-sum-value" id="lk-total-belanja">Rp 0</strong>
      </div>
      <div class="lk-sum-card lk-sum-card--main">
        <span class="lk-sum-label">Total Bayar</span>
        <strong class="lk-sum-value" id="lk-total-bayar">Rp 0</strong>
      </div>
      <div class="lk-sum-card lk-sum-card--change">
        <span class="lk-sum-label">Kembali</span>
        <strong class="lk-sum-value" id="lk-kembali">Rp 0</strong>
      </div>
    </div>

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
      <div class="lk-status lk-live" id="lk-status">Memuat…</div>
    </footer>
  </div>

  <script src="dist/js/layar-konsumen-display.js"></script>
  <script>
  if (window.NumartLayarKonsumen) {
    NumartLayarKonsumen.initDisplayPage();
  }
  </script>
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
    var $totalBelanja = document.getElementById('lk-total-belanja');
    var $itemCount = document.getElementById('lk-item-count');
    var $totalBayar = document.getElementById('lk-total-bayar');
    var $kembali = document.getElementById('lk-kembali');
    var $status = document.getElementById('lk-status');
    var $kasir = document.getElementById('lk-kasir');
    var $customerNama = document.getElementById('lk-customer-nama');
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

    function renderSummary(data) {
      var belanja = Number(data.total_belanja != null ? data.total_belanja : data.total) || 0;
      var uangBayar = Number(data.total_bayar) || 0;
      $totalBelanja.textContent = formatRp(belanja);
      $itemCount.textContent = String(data.item_count || 0);
      $totalBayar.textContent = formatRp(uangBayar);
      $kembali.textContent = formatRp(data.kembali || 0);
    }

    function renderEmptyState(data) {
      $tableWrap.style.display = 'none';
      $empty.style.display = 'flex';
      renderSummary({
        total_belanja: 0,
        item_count: 0,
        total_bayar: 0,
        ongkir: 0,
        diskon: 0,
        kembali: data.kembali || 0
      });

      if (data.event === 'checkout_done') {
        $bannerThanks.style.display = 'block';
        $emptyText.textContent = 'Transaksi selesai';
        $emptySub.textContent = 'Silakan tunggu, kasir akan memulai transaksi berikutnya.';
      } else {
        if (data.event !== 'tipe_changed') {
          hideBanners();
        }
        $emptyText.textContent = 'Menunggu barang di-scan kasir…';
        $emptySub.textContent = (data.customer_nama || 'Umum') + ' · ' + (data.tipe_customer || 'Umum') + ' · layar otomatis mengikuti kasir.';
      }
    }

    function renderPayment(data) {
      var label = data.payment_label || 'Cash';
      var isTransfer = Number(data.payment_type) === 1;
      $paymentPill.textContent = 'Pembayaran: ' + label;
      $paymentPill.className = 'lk-payment-pill ' + (isTransfer ? 'lk-payment-pill--transfer' : 'lk-payment-pill--cash');

      if (isTransfer && data.event !== 'checkout_done') {
        $qrisCol.classList.add('is-visible');
        var qrisTotal = Number(data.tagihan) || Number(data.total_belanja || data.total) || 0;
        $qrisAmount.textContent = formatRp(qrisTotal);
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
      $customerNama.textContent = data.customer_nama || 'Umum';
      $tipe.textContent = data.tipe_customer || 'Umum';
      renderSummary(data);
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
