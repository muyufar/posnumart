<?php
ob_start();
include '_header-artibut.php';

$levelLogin = $_SESSION['user_level'];
$userId = $_SESSION['user_id'];

function beli_langsung_back_url()
{
  $customer = isset($_GET['customer']) ? (string) $_GET['customer'] : '';
  $r = isset($_GET['r']) ? (string) $_GET['r'] : '';
  $url = 'beli-langsung';
  if ($customer !== '') {
    $url .= '?customer=' . urlencode($customer);
    if ($r !== '') {
      $url .= '&r=' . urlencode($r);
    }
  }
  return $url;
}

function beli_langsung_redirect($url)
{
  while (ob_get_level() > 0) {
    ob_end_clean();
  }
  header('Location: ' . $url);
  exit;
}

error_reporting(0);

// Insert Ke keranjang Scan Barcode
if (isset($_POST["inputbarcode"])) {
  if (tambahKeranjangBarcode($_POST) > 0) {
    beli_langsung_redirect(beli_langsung_back_url());
  }
}

// Selesaikan transaksi (bayar)
$inv = $_POST["penjualan_invoice2"] ?? '';
if (isset($_POST["updateStock"]) && !empty($inv)) {
  $invEsc = mysqli_real_escape_string($conn, $inv);
  $sql = mysqli_query($conn, "SELECT * FROM invoice WHERE penjualan_invoice='$invEsc' && invoice_cabang = '$sessionCabang' ") or die(mysqli_error($conn));
  $hasilquery = mysqli_num_rows($sql);

  if ($hasilquery == 0) {
    $result = updateStock($_POST);

    if ($result > 0) {
      $tipeCheckout = (int) base64_decode($_GET['customer'] ?? '0');
      pos_display_sync((int) $userId, (int) $sessionCabang, $tipeCheckout, 'checkout');
      beli_langsung_ctx_clear((int) $userId);
      beli_langsung_redirect('invoice?no=' . urlencode($inv));
    }

    if (empty($_SESSION['beli_langsung_alert'])) {
      $_SESSION['beli_langsung_alert'] = 'Transaksi Gagal !!';
    }
    beli_langsung_redirect(beli_langsung_back_url());
  }

  // Invoice dengan nomor yang sama sudah ada (klik ganda / reload) — arahkan ke nota yang sudah tersimpan
  $tipeCheckout = (int) base64_decode($_GET['customer'] ?? '0');
  pos_display_sync((int) $userId, (int) $sessionCabang, $tipeCheckout, 'checkout');
  beli_langsung_ctx_clear((int) $userId);
  beli_langsung_redirect('invoice?no=' . urlencode($inv));
}

if (isset($_POST["updateStockDraft"])) {
  $invDraft = $_POST["penjualan_invoice2"] ?? $inv;
  $invDraftEsc = mysqli_real_escape_string($conn, $invDraft);
  $sql = mysqli_query($conn, "SELECT * FROM invoice WHERE penjualan_invoice='$invDraftEsc' && invoice_cabang = '$sessionCabang' ") or die(mysqli_error($conn));
  $hasilquery = mysqli_num_rows($sql);

  if ($hasilquery == 0) {
    if (updateStockDraft($_POST) > 0) {
      $_SESSION['beli_langsung_alert'] = 'Transaksi Berhasil Dipending !!';
    } else {
      $_SESSION['beli_langsung_alert'] = 'Transaksi Gagal !!';
    }
  } else {
    $_SESSION['beli_langsung_alert'] = 'Transaksi Berhasil dipending !!';
  }
  beli_langsung_redirect(beli_langsung_back_url());
}

if (isset($_POST["updateSn"])) {
  if (updateSn($_POST) > 0) {
    beli_langsung_redirect(beli_langsung_back_url());
  }
  $_SESSION['beli_langsung_alert'] = 'Data Gagal edit SN';
  beli_langsung_redirect(beli_langsung_back_url());
}

if (isset($_POST["updateQtyPenjualan"])) {
  if (updateQTYHarga($_POST) > 0) {
    beli_langsung_redirect(beli_langsung_back_url());
  }
  $_SESSION['beli_langsung_alert'] = 'Data Gagal edit Qty/Satuan';
  beli_langsung_redirect(beli_langsung_back_url());
}

$posAutoHideSidebar = true;
$posBodyExtraClass = 'bl-pos-page';
include '_header.php';
include '_nav.php';
include '_sidebar.php';

$tipeHarga = (int) base64_decode($_GET['customer'] ?? '0');
if ($tipeHarga == 1) {
  $nameTipeHarga = "Member Retail";
} elseif ($tipeHarga == 2) {
  $nameTipeHarga = "Grosir";
} else {
  $nameTipeHarga = "Umum";
  $tipeHarga = 0;
}

$cartCountRows = query("SELECT COUNT(*) AS jml FROM keranjang WHERE keranjang_id_kasir = $userId && keranjang_cabang = $sessionCabang");
if ((int) ($cartCountRows[0]['jml'] ?? 0) > 0) {
  keranjang_update_tipe_harga((int) $userId, (int) $sessionCabang, (int) $tipeHarga, (int) $tipeHarga);
}
$keranjang = query("SELECT * FROM keranjang WHERE keranjang_id_kasir = $userId && keranjang_tipe_customer = $tipeHarga && keranjang_cabang = $sessionCabang ORDER BY keranjang_id DESC");
$beliLangsungCtx = beli_langsung_ctx_get((int) $userId);
$cartHasItems = count($keranjang) > 0;

$r = 0;
if (!empty($_GET['r'])) {
  $r = abs((int) base64_decode((string) $_GET['r']));
}

$blSavedCustomerId = $beliLangsungCtx['customer_id'];
if ($blSavedCustomerId !== null && !beli_langsung_customer_valid($conn, (int) $blSavedCustomerId, $tipeHarga, (int) $sessionCabang)) {
  $blSavedCustomerId = 0;
  beli_langsung_ctx_update_customer((int) $userId, 0);
}
if ($r == 1 && (int) $blSavedCustomerId > 0 && !beli_langsung_customer_boleh_piutang($conn, (int) $blSavedCustomerId, (int) $sessionCabang)) {
  $_SESSION['beli_langsung_alert'] = 'Piutang hanya untuk member toko asal. Mode diubah ke Cash.';
  $cashUrl = 'beli-langsung';
  if (isset($_GET['customer'])) {
    $cashUrl .= '?customer=' . urlencode((string) $_GET['customer']);
  }
  beli_langsung_redirect($cashUrl);
}
$blSavedPaymentType = $beliLangsungCtx['payment_type'];
if ($blSavedPaymentType === null) {
  $blSavedPaymentType = 0;
}
pos_display_sync((int) $userId, (int) $sessionCabang, (int) $tipeHarga, 'active');

if ($levelLogin === "kurir") {
  echo "
      <script>
        document.location.href = 'bo';
      </script>
    ";
}


if ($dataTokoLogin['toko_status'] < 1) {
  echo "
      <script>
        alert('Status Toko Tidak Aktif Jadi Anda Tidak Bisa melakukan Transaksi !!');
        document.location.href = 'bo';
      </script>
    ";
}

if (!empty($_SESSION['beli_langsung_alert'])) {
  $beliLangsungAlert = $_SESSION['beli_langsung_alert'];
  unset($_SESSION['beli_langsung_alert']);
  echo "<script>alert(" . json_encode($beliLangsungAlert, JSON_UNESCAPED_UNICODE) . ");</script>";
}

?>


<style>
  /* Clean Elegant Professional Design */
  :root {
    --primary-color: #0d9488;
    --primary-light: #14b8a6;
    --primary-dark: #0f766e;
    --accent-color: #fbbf24;
    --success-color: #10b981;
    --danger-color: #ef4444;
    --warning-color: #f59e0b;
    --light-bg: #ffffff;
    --border-color: #e5e7eb;
    --text-muted: #6b7280;
    --text-dark: #111827;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --radius: 8px;
    --radius-lg: 12px;
  }

  body {
    background-color: #f9fafb;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
  }

  /* Header Section - Compact POS toolbar */
  .content-header.bl-page-header-compact {
    background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);
    color: #ffffff;
    padding: 0.5rem 0;
    margin-bottom: 0.75rem;
    border-radius: 0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  }

  .bl-header-toolbar {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem 0.75rem;
  }

  .bl-header-left {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 0.5rem 0.75rem;
    flex-shrink: 0;
  }

  .bl-header-title {
    color: #ffffff;
    font-weight: 600;
    margin: 0;
    font-size: 1.15rem;
    letter-spacing: -0.2px;
    white-space: nowrap;
  }

  .bl-header-badge {
    display: inline-block;
    margin-left: 0;
    padding: 0.15rem 0.55rem;
    font-size: 0.78rem;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    vertical-align: middle;
  }

  .btn-cash-piutang {
    display: inline-flex;
    gap: 0.35rem;
    flex-wrap: nowrap;
    margin-top: 0;
  }

  .btn-cash-piutang .btn {
    border-radius: 999px;
    padding: 0.28rem 0.75rem;
    font-weight: 500;
    font-size: 0.78rem;
    transition: all 0.15s ease;
    border: 1px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
    min-width: auto;
    text-align: center;
    line-height: 1.3;
  }

  .btn-cash-piutang .btn i {
    margin-right: 0.5rem;
    font-size: 0.9rem;
  }

  .btn-cash-piutang .btn-primary {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.4);
    font-weight: 600;
  }

  .btn-cash-piutang .btn-default {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.2);
  }

  .btn-cash-piutang .btn-danger {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(255, 255, 255, 0.3);
    font-weight: 600;
  }

  .btn-cash-piutang .btn:hover {
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-1px);
  }

  .btn-cash-piutang .btn-primary:hover {
    background: rgba(255, 255, 255, 0.3);
  }

  .btn-cash-piutang .btn-default:hover {
    background: rgba(255, 255, 255, 0.15);
  }

  .btn-cash-piutang .btn-danger:hover {
    background: rgba(239, 68, 68, 0.3);
  }

  .bl-btn-layar-konsumen {
    border-radius: 999px !important;
    padding: 0.28rem 0.75rem !important;
    font-weight: 500 !important;
    font-size: 0.78rem !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    background: rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
    line-height: 1.3;
  }

  .bl-btn-layar-konsumen:hover {
    background: rgba(255, 255, 255, 0.22) !important;
    color: #ffffff !important;
  }

  /* Main Card - Clean Design */
  .card {
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    margin-bottom: 1.5rem;
    overflow: hidden;
    background: #ffffff;
  }

  .card-header.bl-card-toolbar {
    background: #ffffff;
    border-bottom: 1px solid var(--border-color);
    padding: 0.55rem 0.85rem;
  }

  .bl-scan-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem 0.75rem;
    width: 100%;
  }

  .bl-scan-toolbar .card-invoice {
    flex: 1 1 220px;
    min-width: 0;
    margin: 0;
  }

  .bl-scan-toolbar .cari-barang-parent {
    flex: 2 1 280px;
    min-width: 200px;
    margin: 0;
  }

  /* Invoice Section - Compact */
  .card-invoice {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #f9fafb;
    padding: 0.4rem 0.65rem;
    border-radius: var(--radius);
    border: 1px solid var(--border-color);
  }

  .card-invoice span {
    font-weight: 500;
    color: var(--text-muted);
    font-size: 0.8rem;
    white-space: nowrap;
  }

  .card-invoice span i {
    margin-right: 0.5rem;
    color: var(--primary-color);
  }

  .card-invoice input {
    border: 1px solid var(--border-color);
    background: #ffffff;
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--text-dark);
    flex: 1;
    min-width: 0;
    padding: 0.3rem 0.5rem;
    border-radius: var(--radius);
    transition: all 0.2s ease;
  }

  .card-invoice input:focus {
    border-color: var(--primary-light);
    box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.1);
    outline: none;
  }

  /* Search Section - Clean Input */
  .cari-barang-parent {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .cari-barang-parent .row {
    margin: 0;
    width: 100%;
  }

  .cari-barang-parent .col-10,
  .cari-barang-parent .col-2 {
    padding-left: 0;
    padding-right: 0;
  }

  .cari-barang-parent .col-10 {
    padding-right: 0.5rem;
  }

  .cari-barang-parent form {
    margin: 0;
  }

  .cari-barang-parent .form-control {
    border-radius: var(--radius);
    border: 1px solid var(--border-color);
    padding: 0.5rem 0.75rem;
    transition: all 0.2s ease;
    background: #ffffff;
    font-size: 1rem;
    font-weight: 400;
  }

  .cari-barang-parent .form-control:focus {
    border-color: var(--primary-light);
    box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.1);
    outline: none;
  }

  .cari-barang-parent .form-control::placeholder {
    color: var(--text-muted);
  }

  .cari-barang-parent .btn {
    border-radius: var(--radius);
    padding: 0.625rem 1rem;
    height: 100%;
    background: var(--primary-color);
    border: none;
    transition: all 0.2s ease;
    color: #ffffff;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .cari-barang-parent .btn:hover {
    background: var(--primary-dark);
  }

  .cari-barang-parent .btn i {
    color: #ffffff !important;
  }

  .bl-scan-input-wrap {
    display: flex;
    align-items: stretch;
    gap: 0.4rem;
    width: 100%;
  }

  .bl-scan-form {
    flex: 1;
    min-width: 0;
    margin: 0;
  }

  .bl-scan-input {
    height: 2rem;
    padding: 0.3rem 0.55rem;
    font-size: 0.85rem;
    border-radius: var(--radius);
    border: 1px solid var(--border-color);
  }

  .bl-scan-search-btn {
    flex: 0 0 auto;
    width: 2.25rem;
    height: 2rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius);
  }

  .bl-scan-search-btn i {
    margin-right: 0 !important;
  }

  /* Table Styling - Clean Professional */
  .table {
    margin-bottom: 0;
  }

  .table thead {
    background: #0d9488;
    color: white;
  }

  .table thead th {
    border: none;
    padding: 0.875rem 1rem;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
  }

  .table tbody tr {
    transition: background-color 0.15s ease;
    border-bottom: 1px solid var(--border-color);
  }

  .table tbody tr:hover {
    background-color: #f9fafb;
  }

  .table tbody td {
    padding: 1rem;
    vertical-align: middle;
    font-size: 0.875rem;
    color: var(--text-dark);
  }

  .orderan-online-button {
    display: flex;
    gap: 0.5rem;
  }

  .orderan-online-button .btn {
    border-radius: var(--radius);
    padding: 0.5rem 0.75rem;
    transition: all 0.2s ease;
    font-weight: 500;
    font-size: 0.875rem;
  }

  .orderan-online-button .btn:hover {
    transform: translateY(-1px);
  }

  /* Form Section - Clean Design */
  .filter-customer {
    background: #ffffff;
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
  }

  .filter-customer .form-group {
    margin-bottom: 1.5rem;
  }

  .filter-customer label {
    font-weight: 500;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .filter-customer label i {
    color: var(--primary-color);
    font-size: 0.9rem;
  }

  .filter-customer .form-control {
    border-radius: var(--radius);
    border: 1px solid var(--border-color);
    transition: all 0.2s ease;
    background: #ffffff;
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    height: auto;
    min-height: 38px;
  }

  .filter-customer .form-control:focus {
    border-color: var(--primary-light);
    box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.1);
    outline: none;
  }

  /* Select2 Styling */
  .filter-customer .select2-container {
    width: 100% !important;
  }

  .filter-customer .select2-container--bootstrap4 .select2-selection {
    border-radius: var(--radius);
    border: 1px solid var(--border-color);
    transition: all 0.2s ease;
    background: #ffffff;
    min-height: 38px;
    height: auto;
  }

  .filter-customer .select2-container--bootstrap4 .select2-selection--single {
    height: 38px;
  }

  .filter-customer .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
    line-height: 36px;
    padding-left: 0.875rem;
    padding-right: 1.5rem;
    font-size: 0.875rem;
    color: var(--text-dark);
  }

  .filter-customer .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
    height: 36px;
    right: 0.5rem;
  }

  .filter-customer .select2-container--focus .select2-selection {
    border-color: var(--primary-light);
    box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.1);
    outline: none;
  }

  .filter-customer small a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }

  .filter-customer small a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
  }

  .filter-customer .bl-field-locked,
  .filter-customer select.bl-field-locked:disabled {
    background-color: #f8fafc;
    cursor: not-allowed;
    opacity: 0.92;
  }

  body.bl-pos-page.bl-dark-mode .filter-customer .bl-field-locked,
  body.bl-pos-page.bl-dark-mode .filter-customer select.bl-field-locked:disabled {
    background-color: #334155;
  }

  /* QRIS Display - Clean Card */
  #qris-display {
    background: #ffffff;
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    border: 1px dashed var(--border-color);
    box-shadow: var(--shadow-sm);
    text-align: center;
  }

  #qris-display img {
    transition: opacity 0.2s ease;
    border-radius: var(--radius);
  }

  #qris-display img:hover {
    opacity: 0.9;
  }

  #qris-display p {
    color: var(--text-muted);
    font-size: 0.875rem;
    margin: 0;
  }

  /* Checkout ringkasan — kartu putih, baris Sub Total sebagai fokus utama */
  .invoice-table {
    background: transparent;
    border-radius: var(--radius-lg);
    padding: 0;
    box-shadow: none;
  }

  .bl-invoice-inner {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
  }

  .bl-invoice-eyebrow {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #64748b;
    padding: 0.85rem 1.25rem 0;
    margin: 0;
    background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
  }

  .bl-checkout-table {
    margin-bottom: 0 !important;
    color: var(--text-dark);
    border-collapse: collapse;
  }

  .bl-checkout-table td {
    padding: 0.65rem 1.25rem;
    border: none;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
  }

  .bl-checkout-table td:first-child {
    font-weight: 600;
    width: 38%;
    font-size: 0.9rem;
    color: #334155;
  }

  .bl-checkout-table .table-nominal {
    text-align: right;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    flex-wrap: nowrap;
    min-width: 0;
  }

  .bl-checkout-table .table-nominal > span:first-child {
    flex: 0 0 auto;
    white-space: nowrap;
    color: #64748b;
    font-weight: 600;
    font-size: 0.8125rem;
    letter-spacing: 0.02em;
  }

  .bl-checkout-table .table-nominal > span:not(:first-child),
  .bl-checkout-table .table-nominal > .ongkir-beli-langsung {
    flex: 1 1 auto;
    min-width: 0;
    display: inline-flex !important;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
  }

  .bl-checkout-table input {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #0f172a;
    border-radius: 10px;
    padding: 0.55rem 0.75rem;
    text-align: right;
    font-weight: 600;
    font-size: 0.95rem;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    min-width: 5.5rem;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
  }

  .bl-checkout-table input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.2);
    outline: none;
    background: #fff;
  }

  .bl-checkout-table input[type="number"] {
    -moz-appearance: textfield;
  }

  .bl-checkout-table input[type="number"]::-webkit-inner-spin-button,
  .bl-checkout-table input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }

  .bl-checkout-table input[readonly],
  .bl-checkout-table input[disabled] {
    background: #f1f5f9;
    color: #475569;
    cursor: default;
  }

  .bl-row-total td {
    background: linear-gradient(135deg, #ecfdf5 0%, #f0fdfa 100%);
    border-bottom: 2px solid #99f6e4 !important;
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
  }

  .bl-row-total td:first-child b {
    color: #0f766e;
    font-size: 1.05rem;
    font-weight: 700;
  }

  .bl-row-total .table-nominal input {
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a !important;
    border-color: #5eead4;
    background: #fff !important;
  }

  /* Baris Sub Total — gradien di <tr> agar satu alur warna (bukan per-sel) */
  .bl-checkout-table tr.bl-row-subtotal {
    background-color: #0f766e;
    background-image: linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 0, rgba(255, 255, 255, 0) 8px),
      linear-gradient(135deg, #0d9488 0%, #0f766e 55%, #115e59 100%);
  }

  .bl-checkout-table tr.bl-row-subtotal td {
    background-color: transparent !important;
    background-image: none !important;
    color: #ecfdf5;
    border-bottom: none !important;
    padding: 1.25rem 1.25rem !important;
    vertical-align: middle;
  }

  .bl-checkout-table tr.bl-row-subtotal td:first-child {
    vertical-align: top;
    width: 42%;
  }

  .bl-checkout-table tr.bl-row-subtotal td:first-child b {
    display: block;
    color: #fff !important;
    font-size: 1.35rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1.2;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
  }

  .bl-pay-hint {
    display: block;
    margin-top: 0.4rem;
    font-size: 0.8125rem;
    font-weight: 500;
    line-height: 1.35;
    color: rgba(236, 253, 245, 0.95) !important;
    max-width: 16rem;
  }

  .bl-checkout-table tr.bl-row-subtotal .table-nominal > span:first-child {
    color: rgba(255, 255, 255, 0.85);
  }

  .bl-checkout-table tr.bl-row-subtotal input {
    background: #ffffff !important;
    color: #0f172a !important;
    border: 3px solid #fbbf24 !important;
    font-size: 1.45rem !important;
    font-weight: 800 !important;
    letter-spacing: -0.02em;
    min-height: 3.1rem !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
  }

  .bl-checkout-table tr.bl-row-subtotal input:focus {
    border-color: #f59e0b !important;
    box-shadow: 0 0 0 4px rgba(251, 191, 36, 0.45), 0 8px 20px rgba(0, 0, 0, 0.12);
  }

  /* Bayar / DP — baris biasa (input tetap sama untuk JS) */
  .bl-checkout-table tr.bl-row-bayar td {
    background: #fff;
    color: var(--text-dark);
    border-bottom: 1px solid #f1f5f9 !important;
    padding: 0.85rem 1.25rem !important;
  }

  .bl-checkout-table tr.bl-row-bayar td:first-child b {
    color: #0f766e !important;
    font-size: 1rem;
    font-weight: 700;
  }

  .bl-row-kembali td {
    background: #f8fafc;
    font-weight: 600;
    border-bottom: none !important;
  }

  .bl-row-kembali td:first-child {
    color: #475569;
  }

  .bl-checkout-table tr.bl-row-kembali input {
    font-size: 1.05rem;
    font-weight: 700;
    color: #059669 !important;
    background: #ecfdf5 !important;
    border-color: #6ee7b7 !important;
  }

  /* Ongkir Icon */
  .bl-checkout-table .fa-close {
    cursor: pointer;
    margin-left: 0.35rem;
    padding: 0.35rem 0.55rem;
    border-radius: 8px;
    background: #fee2e2;
    color: #b91c1c !important;
    transition: all 0.2s ease;
    font-size: 0.8rem;
  }

  .bl-checkout-table .fa-close:hover {
    background: #fecaca;
    transform: scale(1.05);
  }

  /* Payment Buttons — di bawah kartu ringkasan */
  .payment {
    margin-top: 1rem;
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    justify-content: stretch;
  }

  .payment .btn {
    border-radius: 12px;
    padding: 0.85rem 1.5rem;
    font-weight: 700;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    box-shadow: var(--shadow-md);
    border: none;
    flex: 1 1 auto;
    min-width: 200px;
  }

  .payment .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px -8px rgba(13, 148, 136, 0.45);
  }

  .payment .btn-primary {
    background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
    color: white;
  }

  .payment .btn-primary:hover {
    background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
    color: #fff;
  }

  .payment .btn-danger {
    background: var(--danger-color);
    color: white;
  }

  .payment .btn-danger:hover {
    background: #dc2626;
  }

  .payment .btn-default {
    background: #ffffff;
    color: var(--text-dark);
    border: 2px solid #e2e8f0;
  }

  .payment .btn-default:hover {
    background: #f8fafc;
    border-color: var(--primary-light);
  }

  .payment .updateStok {
    margin-left: 0;
  }

  .bl-kbd-shortcuts {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.3rem 0.5rem;
    padding: 0.45rem 0.65rem;
    border-radius: var(--radius);
    font-size: 0.7rem;
    line-height: 1.35;
  }

  .bl-header-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex: 1 1 0;
    min-width: 0;
  }

  .bl-header-right .bl-kbd-shortcuts--inline {
    width: 100%;
  }

  .content-header .bl-kbd-shortcuts--header {
    margin: 0;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.28);
    color: #ffffff;
    justify-content: flex-end;
    max-width: 100%;
  }

  .bl-kbd-shortcuts--inline {
    flex-wrap: nowrap;
    justify-content: flex-start;
    gap: 0.15rem 0.3rem;
    padding: 0.28rem 0.45rem;
    font-size: 0.62rem;
    overflow-x: auto;
    overflow-y: hidden;
    max-width: 100%;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.35) transparent;
    -webkit-overflow-scrolling: touch;
  }

  .bl-kbd-shortcuts--inline::-webkit-scrollbar {
    height: 3px;
  }

  .bl-kbd-shortcuts--inline::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.35);
    border-radius: 999px;
  }

  .bl-kbd-sep {
    display: inline-block;
    width: 1px;
    height: 0.85rem;
    margin: 0 0.1rem;
    background: rgba(255, 255, 255, 0.28);
    align-self: center;
    flex-shrink: 0;
  }

  .content-header .bl-kbd-shortcuts--header .bl-kbd-title,
  .content-header .bl-kbd-shortcuts--header .bl-kbd-help-btn {
    color: #ffffff;
  }

  .content-header .bl-kbd-shortcuts--header .bl-kbd-help-btn {
    margin-left: 0.2rem;
    flex-shrink: 0;
    padding: 0.08rem 0.4rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff;
    font-size: 0.68rem;
    white-space: nowrap;
  }

  .content-header .bl-kbd-shortcuts--header .bl-kbd-help-btn:hover {
    background: rgba(255, 255, 255, 0.22);
  }

  .content-header .bl-kbd-shortcuts--header kbd {
    color: #0f766e;
    font-size: 0.58rem;
    padding: 0.04rem 0.22rem;
  }

  .bl-kbd-shortcuts--inline .bl-kbd-item {
    flex-shrink: 0;
  }

  .bl-kbd-title {
    font-weight: 600;
    margin-right: 0.25rem;
    white-space: nowrap;
  }

  .bl-kbd-item {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    white-space: nowrap;
  }

  .bl-kbd-shortcuts kbd,
  .bl-kbd-help-btn kbd {
    display: inline-block;
    padding: 0.1rem 0.35rem;
    font-size: 0.68rem;
    font-family: inherit;
    line-height: 1.3;
    color: #0f766e;
    background: #fff;
    border: 1px solid #5eead4;
    border-radius: 4px;
    box-shadow: 0 1px 0 #ccfbf1;
  }

  .bl-kbd-help-btn {
    margin-left: auto;
    border: none;
    background: transparent;
    color: #0f766e;
    font-size: 0.75rem;
    cursor: pointer;
    padding: 0.15rem 0.35rem;
    border-radius: 4px;
  }

  .bl-kbd-help-btn:hover {
    background: #ccfbf1;
  }

  #bl-last-cart-row {
    background-color: #ecfdf5 !important;
  }

  #bl-last-cart-row td {
    border-top: 2px solid #14b8a6;
    border-bottom: 2px solid #14b8a6;
  }

  /* Modal Styling - Clean */
  .modal-content {
    border-radius: var(--radius-lg);
    border: none;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
  }

  .modal-header {
    background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);
    color: white;
    border-radius: 0;
    padding: 1.5rem;
    border-bottom: none;
  }

  .modal-header .modal-title {
    font-weight: 600;
    font-size: 1.25rem;
  }

  .modal-header .close {
    color: white;
    opacity: 0.8;
    font-size: 1.5rem;
    transition: opacity 0.2s ease;
  }

  .modal-header .close:hover {
    opacity: 1;
  }

  /* Loading Animation - Match Sidebar Teal Color */
  .container {
    --uib-size: 40px;
    --uib-color: #14b8a6;
    --uib-speed: 2s;
    --uib-bg-opacity: 0;
    height: var(--uib-size);
    width: var(--uib-size);
    transform-origin: center;
    animation: rotate var(--uib-speed) linear infinite;
    will-change: transform;
    overflow: visible;
  }

  .car {
    fill: none;
    stroke: var(--uib-color);
    stroke-dasharray: 1, 200;
    stroke-dashoffset: 0;
    stroke-linecap: round;
    animation: stretch calc(var(--uib-speed) * 0.75) ease-in-out infinite;
    will-change: stroke-dasharray, stroke-dashoffset;
    transition: stroke 0.5s ease;
  }

  .track {
    fill: none;
    stroke: var(--uib-color);
    opacity: var(--uib-bg-opacity);
    transition: stroke 0.5s ease;
  }

  @keyframes rotate {
    100% {
      transform: rotate(360deg);
    }
  }

  @keyframes stretch {
    0% {
      stroke-dasharray: 0, 150;
      stroke-dashoffset: 0;
    }

    50% {
      stroke-dasharray: 75, 150;
      stroke-dashoffset: -25;
    }

    100% {
      stroke-dashoffset: -100;
    }
  }

  /* Responsive */
  @media (max-width: 768px) {
    .content-header.bl-page-header-compact {
      padding: 0.45rem 0;
    }

    .bl-header-toolbar {
      flex-wrap: wrap;
    }

    .bl-header-right {
      width: 100%;
      justify-content: flex-start;
    }

    .btn-cash-piutang {
      flex-wrap: wrap;
    }

    .bl-scan-toolbar {
      flex-direction: column;
      align-items: stretch;
    }

    .payment {
      flex-direction: column;
    }

    .payment .btn {
      width: 100%;
    }
  }

  /* Utility Classes */
  .none {
    display: none !important;
  }

  /* Additional Modern Touches */
  .breadcrumb {
    background: rgba(255,255,255,0.85);
    border-radius: var(--radius);
    padding: 0.6rem 1.2rem;
    border: 2px solid rgba(20, 184, 166, 0.2);
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 4px rgba(13, 148, 136, 0.1);
  }

  .breadcrumb a {
    color: #0d9488;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
  }

  .breadcrumb a:hover {
    color: #0f766e;
    text-decoration: underline;
  }

  .breadcrumb-item.active {
    color: #495057;
    font-weight: 600;
  }

  .breadcrumb-item + .breadcrumb-item::before {
    color: #6c757d;
    font-weight: 600;
  }

  /* Table empty state */
  .table tbody tr:empty::after {
    content: "Tidak ada item dalam keranjang";
    display: block;
    padding: 2rem;
    text-align: center;
    color: var(--text-muted);
  }

  /* Input number styling */
  input[type="number"]::-webkit-inner-spin-button,
  input[type="number"]::-webkit-outer-spin-button {
    opacity: 1;
  }

  /* Select2 Modern Styling - Global */
  .select2-container--bootstrap4 .select2-selection {
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    min-height: 38px;
    transition: all 0.2s ease;
  }

  .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
    line-height: 36px;
    padding-left: 0.875rem;
    padding-right: 1.5rem;
    font-size: 0.875rem;
  }

  .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
    height: 36px;
    right: 0.5rem;
  }

  .select2-container--bootstrap4.select2-container--focus .select2-selection {
    border-color: var(--primary-light);
    box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.1);
  }

  /* Focus states */
  .form-control:focus,
  .select2-container--focus .select2-selection {
    outline: none;
  }

  /* Card body padding */
  .card-body {
    padding: 0.85rem 1rem;
  }

  .bl-page-header-compact + .content {
    padding-top: 0.5rem;
  }

  .bl-checkout-table tr.bl-row-kembali td,
  .bl-checkout-table tr.bl-row-total td {
    border-radius: 0;
  }

  .bl-checkout-table tr.bl-row-hidden-fields td {
    border-bottom: none;
    padding-top: 1rem;
    background: #fafafa;
  }

  /* Button icon spacing */
  .btn i {
    margin-right: 0.5rem;
  }

  .btn-cash-piutang .btn i {
    margin-right: 0.5rem;
  }

  /* Utility Classes */
  .none {
    display: none !important;
  }

  /* Dark mode — halaman transaksi kasir */
  body.bl-pos-page.bl-dark-mode {
    --light-bg: #1e293b;
    --border-color: #334155;
    --text-muted: #94a3b8;
    --text-dark: #e2e8f0;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.35);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.4);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.45);
    background-color: #0f172a;
    color: #e2e8f0;
  }

  body.bl-pos-page.bl-dark-mode .content-wrapper {
    background-color: #0f172a;
  }

  body.bl-pos-page.bl-dark-mode .main-header,
  body.bl-pos-page.bl-dark-mode .main-header .navbar-light {
    background-color: #1e293b !important;
    border-bottom: 1px solid #334155;
  }

  body.bl-pos-page.bl-dark-mode .main-header .nav-link,
  body.bl-pos-page.bl-dark-mode .main-header .navbar-nav .nav-link {
    color: #cbd5e1 !important;
  }

  body.bl-pos-page.bl-dark-mode .main-footer {
    background: #1e293b;
    border-top-color: #334155;
    color: #94a3b8;
  }

  body.bl-pos-page.bl-dark-mode .main-footer a {
    color: #5eead4;
  }

  body.bl-pos-page.bl-dark-mode .content-header.bl-page-header-compact {
    background: linear-gradient(135deg, #134e4a 0%, #115e59 100%);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
  }

  body.bl-pos-page.bl-dark-mode .card,
  body.bl-pos-page.bl-dark-mode .card-header.bl-card-toolbar,
  body.bl-pos-page.bl-dark-mode .filter-customer,
  body.bl-pos-page.bl-dark-mode #qris-display,
  body.bl-pos-page.bl-dark-mode .bl-invoice-inner {
    background: #1e293b;
    border-color: #334155;
  }

  body.bl-pos-page.bl-dark-mode .card-invoice {
    background: #0f172a;
    border-color: #334155;
  }

  body.bl-pos-page.bl-dark-mode .card-invoice input,
  body.bl-pos-page.bl-dark-mode .bl-scan-input,
  body.bl-pos-page.bl-dark-mode .filter-customer .form-control,
  body.bl-pos-page.bl-dark-mode .cari-barang-parent .form-control {
    background: #0f172a;
    border-color: #475569;
    color: #e2e8f0;
  }

  body.bl-pos-page.bl-dark-mode .card-invoice input::placeholder,
  body.bl-pos-page.bl-dark-mode .bl-scan-input::placeholder {
    color: #64748b;
  }

  body.bl-pos-page.bl-dark-mode .table tbody tr:hover {
    background-color: #334155;
  }

  body.bl-pos-page.bl-dark-mode .table tbody td {
    color: #e2e8f0;
    border-bottom-color: #334155;
  }

  body.bl-pos-page.bl-dark-mode .table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(15, 23, 42, 0.55);
  }

  body.bl-pos-page.bl-dark-mode .bl-invoice-eyebrow {
    color: #94a3b8;
    background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
  }

  body.bl-pos-page.bl-dark-mode .bl-checkout-table td {
    border-bottom-color: #334155;
  }

  body.bl-pos-page.bl-dark-mode .bl-checkout-table td:first-child {
    color: #cbd5e1;
  }

  body.bl-pos-page.bl-dark-mode .bl-checkout-table .table-nominal > span:first-child {
    color: #94a3b8;
  }

  body.bl-pos-page.bl-dark-mode .bl-checkout-table input {
    background: #0f172a;
    border-color: #475569;
    color: #f1f5f9;
  }

  body.bl-pos-page.bl-dark-mode .bl-checkout-table input:focus {
    background: #1e293b;
  }

  body.bl-pos-page.bl-dark-mode .bl-checkout-table input[readonly],
  body.bl-pos-page.bl-dark-mode .bl-checkout-table input[disabled] {
    background: #334155;
    color: #cbd5e1;
  }

  body.bl-pos-page.bl-dark-mode .bl-row-total td {
    background: linear-gradient(135deg, #134e4a 0%, #1e293b 100%);
    border-bottom-color: #0d9488 !important;
  }

  body.bl-pos-page.bl-dark-mode .bl-checkout-table tr.bl-row-bayar td {
    background: #1e293b;
    color: #e2e8f0;
    border-bottom-color: #334155 !important;
  }

  body.bl-pos-page.bl-dark-mode .bl-row-kembali td {
    background: #0f172a;
  }

  body.bl-pos-page.bl-dark-mode .bl-row-kembali td:first-child {
    color: #94a3b8;
  }

  body.bl-pos-page.bl-dark-mode .bl-checkout-table tr.bl-row-kembali input {
    background: #134e4a !important;
    color: #6ee7b7 !important;
    border-color: #0d9488 !important;
  }

  body.bl-pos-page.bl-dark-mode .payment .btn-default {
    background: #334155;
    color: #e2e8f0;
    border-color: #475569;
  }

  body.bl-pos-page.bl-dark-mode .payment .btn-default:hover {
    background: #475569;
    border-color: #14b8a6;
  }

  body.bl-pos-page.bl-dark-mode #bl-last-cart-row {
    background-color: #134e4a !important;
  }

  body.bl-pos-page.bl-dark-mode #bl-last-cart-row td {
    border-top-color: #14b8a6;
    border-bottom-color: #14b8a6;
  }

  body.bl-pos-page.bl-dark-mode .modal-content {
    background: #1e293b;
    color: #e2e8f0;
  }

  body.bl-pos-page.bl-dark-mode .modal-body {
    background: #1e293b;
    color: #e2e8f0;
  }

  body.bl-pos-page.bl-dark-mode .modal-footer {
    background: #0f172a;
    border-top-color: #334155;
  }

  body.bl-pos-page.bl-dark-mode .dataTables_wrapper .dataTables_filter input,
  body.bl-pos-page.bl-dark-mode .dataTables_wrapper .dataTables_length select {
    background: #0f172a;
    border-color: #475569;
    color: #e2e8f0;
  }

  body.bl-pos-page.bl-dark-mode .filter-customer .select2-container--bootstrap4 .select2-selection {
    background: #0f172a;
    border-color: #475569;
  }

  body.bl-pos-page.bl-dark-mode .filter-customer .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
    color: #e2e8f0;
  }

  body.bl-pos-page.bl-dark-mode .select2-dropdown {
    background: #1e293b;
    border-color: #475569;
  }

  body.bl-pos-page.bl-dark-mode .select2-container--bootstrap4 .select2-results__option {
    color: #e2e8f0;
  }

  body.bl-pos-page.bl-dark-mode .select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected] {
    background: #0d9488 !important;
    color: #fff !important;
  }

  body.bl-pos-page.bl-dark-mode .text-muted {
    color: #94a3b8 !important;
  }

  body.bl-pos-page.bl-dark-mode .bl-checkout-table tr.bl-row-hidden-fields td {
    background: #0f172a;
  }
</style>



<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header bl-page-header-compact">
    <div class="container-fluid">
      <div class="bl-header-toolbar">
        <div class="bl-header-left">
          <h1 class="bl-header-title"><span class="bl-header-badge">Customer <?= $nameTipeHarga; ?></span></h1>
          <div class="btn-cash-piutang">
            <?php
            // Ambil data dari URL Untuk memberikan kondisi transaksi Cash atau Piutang
            if (empty(abs((int)base64_decode($_GET['r'])))) {
              $r = 0;
            } else {
              $r = abs((int)base64_decode($_GET['r']));
            }
            ?>

            <?php if ($r == 1) : ?>
              <a href="beli-langsung?customer=<?= $_GET['customer']; ?>" class="btn btn-default bl-btn-cash">
                <i class="fa fa-money"></i> Cash
              </a>
              <a href="beli-langsung?customer=<?= $_GET['customer']; ?>&r=MQ==" class="btn btn-primary bl-btn-piutang">
                <i class="fa fa-credit-card"></i> Piutang
              </a>
            <?php else : ?>
              <a href="beli-langsung?customer=<?= $_GET['customer']; ?>" class="btn btn-primary bl-btn-cash">
                <i class="fa fa-money"></i> Cash
              </a>
              <a href="beli-langsung?customer=<?= $_GET['customer']; ?>&r=MQ==" class="btn btn-default bl-btn-piutang">
                <i class="fa fa-credit-card"></i> Piutang
              </a>
            <?php endif; ?>
            <button type="button" class="btn btn-default bl-btn-layar-konsumen" id="bl-open-layar-konsumen" title="Buka layar konsumen di monitor kedua + layar penuh (Alt+K)">
              <i class="fa fa-desktop"></i> Layar Konsumen
            </button>
          </div>
        </div>
        <div class="bl-header-right">
          <div class="bl-kbd-shortcuts bl-kbd-shortcuts--header bl-kbd-shortcuts--inline" id="bl-kbd-shortcuts" aria-label="Pintasan keyboard kasir">
            <span class="bl-kbd-item"><kbd>F1</kbd> Scan</span>
            <span class="bl-kbd-item"><kbd>F2</kbd> Cari</span>
            <span class="bl-kbd-item"><kbd>F3</kbd> Bayar</span>
            <span class="bl-kbd-item"><kbd>F4</kbd> Qty</span>
            <span class="bl-kbd-item"><kbd>F5</kbd> Hapus</span>
            <span class="bl-kbd-item"><kbd>F6</kbd> Simpan</span>
            <span class="bl-kbd-item"><kbd>F7</kbd> Cust.</span>
            <span class="bl-kbd-item"><kbd>F8</kbd> Pemb.</span>
            <span class="bl-kbd-item"><kbd>F9</kbd> Diskon</span>
            <span class="bl-kbd-item"><kbd>F10</kbd> Pas</span>
            <span class="bl-kbd-item"><kbd>F11</kbd> Modal</span>
            <span class="bl-kbd-sep" aria-hidden="true"></span>
            <span class="bl-kbd-item"><kbd>Ctrl+F7</kbd> Pilih</span>
            <span class="bl-kbd-item"><kbd>Shift+F7</kbd> ←</span>
            <span class="bl-kbd-item"><kbd>Alt+1</kbd> Umum</span>
            <span class="bl-kbd-item"><kbd>Alt+2</kbd> Retail</span>
            <span class="bl-kbd-item"><kbd>Alt+3</kbd> Grosir</span>
            <span class="bl-kbd-item"><kbd>Alt+C</kbd> Cash</span>
            <span class="bl-kbd-item"><kbd>Alt+T</kbd> Trf</span>
            <span class="bl-kbd-item"><kbd>Alt+S</kbd> Menu</span>
            <span class="bl-kbd-item"><kbd>Alt+D</kbd> Dark</span>
            <span class="bl-kbd-item"><kbd>Alt+K</kbd> Layar</span>
            <button type="button" class="bl-kbd-help-btn" id="bl-kbd-help-btn" title="Detail pintasan (F12)"><kbd>F12</kbd></button>
          </div>
        </div>
      </div>
    </div><!-- /.container-fluid -->
  </section>

  <div class="modal fade" id="modal-id-draft">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <div class="modal-header">
                    <h4 class="modal-title">Data Transaksi Pending</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                  </div>
                  <div class="modal-body">
                    <?php
                    $draft = query("SELECT * FROM invoice WHERE invoice_draft = 1 && invoice_kasir = $userId && invoice_cabang = $sessionCabang ORDER BY invoice_id DESC");
                    ?>
                    <div class="table-auto">
                      <table id="example7" class="table table-bordered table-striped">
                        <thead>
                          <tr>
                            <th style="width: 5px;">No.</th>
                            <th>Invoice</th>
                            <th style="width: 40% !important;">Tanggal</th>
                            <th>Customer</th>
                            <th class="text-center">Aksi</th>
                          </tr>
                        </thead>
                        <tbody>

                          <?php $i = 1; ?>
                          <?php foreach ($draft as $row) : ?>
                            <tr>
                              <td><?= $i; ?></td>
                              <td><?= $row['penjualan_invoice']; ?></td>
                              <td><?= tanggal_indo($row['invoice_tgl']); ?></td>
                              <td>
                                <?php
                                $customer_id_draft = $row['invoice_customer'];
                                $namaCustomerDraft = mysqli_query($conn, "SELECT customer_nama FROM customer WHERE customer_id = $customer_id_draft");
                                $namaCustomerDraft = mysqli_fetch_array($namaCustomerDraft);
                                $customer_nama_draft = $namaCustomerDraft['customer_nama'];

                                if ($customer_id_draft < 1) {
                                  echo "Customer Umum";
                                } else {
                                  echo $customer_nama_draft;
                                }
                                ?>
                              </td>
                              <td class="orderan-online-button">
                                <a href="beli-langsung-draft?customer=<?= base64_encode($row['invoice_customer_category']); ?>&r=<?= base64_encode($row['invoice_piutang']); ?>&invoice=<?= base64_encode($row['penjualan_invoice']); ?>" title="Edit Data">
                                  <button class="btn btn-primary" type="submit">
                                    <i class="fa fa-edit"></i>
                                  </button>
                                </a>
                                <a href="beli-langsung-draft-delete?invoice=<?= $row['penjualan_invoice']; ?>&customer=<?= $_GET['customer']; ?>&cabang=<?= $sessionCabang; ?>" onclick="return confirm('Yakin dihapus ?')" title="Delete Data">
                                  <button class="btn btn-danger" type="submit">
                                    <i class="fa fa-trash"></i>
                                  </button>
                                </a>
                              </td>
                            </tr>
                            <?php $i++; ?>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                  </div>
                </div>
              </div>
  </div>

  <section class="content">
    <?php
    // Ambil counter invoice terakhir saja — jangan SELECT * semua invoice (bisa OOM / HTTP 500 setelah scan)
    $jmlPenjualan1 = 0;
    $invoiceCountRes = mysqli_query(
      $conn,
      "SELECT penjualan_invoice_count FROM invoice WHERE invoice_cabang = " . (int) $sessionCabang . " ORDER BY invoice_id DESC LIMIT 1"
    );
    if ($invoiceCountRes && ($invoiceCountRow = mysqli_fetch_assoc($invoiceCountRes))) {
      $jmlPenjualan1 = (int) ($invoiceCountRow['penjualan_invoice_count'] ?? 0);
    }
    $jmlPenjualan1 = $jmlPenjualan1 + 1;
    ?>
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header bl-card-toolbar">
          <div class="bl-scan-toolbar">
            <div class="card-invoice">
              <span><i class="fa fa-file-text-o"></i> No. Invoice:</span>
              <?php
              $today = date("Ymdis");
              $di = $today . $jmlPenjualan1 . $userId ;
              ?>
              <input type="text" name="invoicing" id="invoicing" value="<?= $di  ?>" readonly>
            </div>
            <div class="cari-barang-parent bl-scan-input-wrap">
              <form action="" method="post" class="bl-scan-form">
                <input type="hidden" name="keranjang_id_kasir" value="<?= $userId; ?>">
                <input type="hidden" name="keranjang_cabang" value="<?= $sessionCabang; ?>">
                <input type="hidden" name="tipe_harga" value="<?= $tipeHarga; ?>">
                <input type="text" class="form-control bl-scan-input" id="input-barcode" autofocus="" name="inputbarcode" placeholder="Scan / Kode Barang (F1)" required="">
              </form>
              <a class="btn btn-primary bl-scan-search-btn" title="Cari Produk (F2)" data-toggle="modal" id="cari-barang" href='#modal-id'>
                <i class="fa fa-search text-white"></i>
              </a>
            </div>
          </div>
        </div>

        <!-- /.card-header -->
        <div class="card-body">
          <div class="table-auto">
            <table id="" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th style="width: 6%;">No.</th>
                  <th>Nama</th>
                  <th>Harga</th>
                  <th>Satuan</th>
                  <th style="text-align: center;">QTY</th>
                  <th>No. SN</th>
                  <th style="width: 20%;">Sub Total</th>
                  <th style="text-align: center; width: 10%;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $i          = 1;
                $cartRowNum = 0;
                $total_beli = 0;
                $total      = 0;
                ?>
                <?php
                foreach ($keranjang as $row) :

                  $bik = $row['barang_id'];
                  $stockParent = mysqli_query($conn, "select barang_stock, satuan_isi_1, satuan_isi_2, satuan_isi_3 from barang where barang_id = '" . $bik . "'");
                  $brg = mysqli_fetch_array($stockParent);
                  $tb_brg       = $brg['barang_stock'];

                  // $sub_total_beli = ($row['keranjang_harga_beli'] * $row['keranjang_qty_view']) * $row['keranjang_konversi_isi'];
                  $sub_total_beli = $row['keranjang_harga_beli'] * $row['keranjang_qty'];
                  $sub_total      = $row['keranjang_harga'] * $row['keranjang_qty_view'];

                  if ($row['keranjang_id_kasir'] === $_SESSION['user_id']) {
                    $cartRowNum++;
                    $total_beli += $sub_total_beli;
                    $total += $sub_total;
                ?>
                    <tr class="bl-cart-row" data-keranjang-id="<?= $row['keranjang_id']; ?>"<?= $cartRowNum === 1 ? ' id="bl-last-cart-row"' : ''; ?>>
                      <td><?= $i; ?></td>
                      <td><?= $row['keranjang_nama'] ?></td>
                      <td>Rp. <?= number_format($row['keranjang_harga'], 0, ',', '.'); ?></td>
                      <td>
                        <?php
                        echo htmlspecialchars(satuan_nama_by_id($conn, (int) $row['keranjang_satuan']) ?: '-', ENT_QUOTES, 'UTF-8');
                        ?>
                      </td>
                      <td style="text-align: center;"><?= $row['keranjang_qty_view']; ?></td>
                      
                      <td>
                        <?php
                        if ($row['keranjang_barang_option_sn'] < 1) {
                          $sn = "Non-SN";
                        } else {
                          $sn = $row['keranjang_sn'];
                          if ($row['keranjang_sn'] == null) {
                            echo '
                                <span class="keranjang-right">
                                  <button class=" btn-success" name="" class="keranjang-pembelian"    id="keranjang_sn" data-id="' . $row['keranjang_id'] . '">
                                    <i class="fa fa-edit"></i>
                                  </button> 
                                </span>';
                          } elseif ($row['keranjang_sn'] === "0") {
                            echo '
                                <span class="keranjang-right">
                                  <button class=" btn-success" name="" class="keranjang-pembelian"    id="keranjang_sn" data-id="' . $row['keranjang_id'] . '">
                                    <i class="fa fa-edit"></i>
                                  </button> 
                                </span>';
                          }
                        }
                        echo $sn;
                        ?>
                      </td>
                      
                      <td>Rp. <?= number_format($sub_total, 0, ',', '.'); ?></td>
                      <td class="orderan-online-button">
                        <a href="#!" title="Edit Data">
                          <button type="button" class="btn btn-primary keranjang-pembelian bl-btn-edit-qty" id="keranjang-qty" data-id="<?= $row['keranjang_id']; ?>" title="Edit Qty (F4 pada item terakhir)">
                            <i class="fa fa-pencil"></i>
                          </button>
                        </a>
                        <a class="bl-btn-delete" href="beli-langsung-delete?id=<?= $row['keranjang_id']; ?>&customer=<?= $_GET['customer']; ?>&r=<?= $r; ?>" title="Hapus (F5 pada item terakhir)" onclick="return confirm('Yakin dihapus ?')">
                          <button class="btn btn-danger" type="submit" name="hapus">
                            <i class="fa fa-trash-o"></i>
                          </button>
                        </a>
                      </td>
                    </tr>
                    <?php $i++; ?>
                  <?php } ?>
                <?php endforeach; ?>
            </table>
          </div>

          <div class="btn-transaksi">
            <form role="form" action="" id="form-main" method="POST">
              <div class="row">
                <div class="col-md-6 col-lg-7">
                  <div class="filter-customer">
                    <div class="form-group">
                      <label><i class="fa fa-users"></i> Tipe Customer <small class="text-muted">(F7 ganti · Alt+1/2/3)</small></label>
                      <select class="form-control pilihan-marketplace select2bs4" name="tipe_customer" id="tipe_customer">
                        <option value="0" <?= $tipeHarga == 0 ? 'selected' : null ?>>Umum</option>
                        <option value="1" <?= $tipeHarga == 1 ? 'selected' : null ?>>Member Retail</option>
                        <option value="2" <?= $tipeHarga == 2 ? 'selected' : null ?>>Grosir</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label><i class="fa fa-user"></i> Customer <b style="color: #0d9488;"><?= $nameTipeHarga; ?></b></label>
                      <select class="form-control pilihan-marketplace select2bs4" required="" name="invoice_customer" id="invoice_customer">
                        <?php if ($r != 1 && $tipeHarga < 2) { ?>
                          <option value="0" data-cabang="<?= (int) $sessionCabang; ?>" data-boleh-piutang="1"<?= ($blSavedCustomerId === 0 || $blSavedCustomerId === null) ? ' selected' : ''; ?>>Umum</option>
                        <?php } elseif ($blSavedCustomerId === null || (int) $blSavedCustomerId < 1) { ?>
                          <option value="" data-cabang="<?= (int) $sessionCabang; ?>" data-boleh-piutang="1">-- Pilih Customer --</option>
                        <?php } ?>
                        <?php
                        $blSelectedId = (int) ($blSavedCustomerId ?? 0);
                        $blSelectedCustomer = $blSelectedId > 0
                          ? beli_langsung_customer_row($conn, $blSelectedId)
                          : null;
                        if ($blSelectedCustomer) {
                          echo beli_langsung_customer_option_tag($blSelectedCustomer, (int) $sessionCabang, $conn, true);
                        }
                        echo beli_langsung_customer_local_options_html($conn, (int) $tipeHarga, (int) $sessionCabang, $blSelectedId, $r != 1);
                        ?>
                      </select>
                      <small>
                        <a href="customer-add"><i class="fa fa-plus-circle"></i> Tambah Customer Baru</a>
                        <?php if ($r == 1) : ?>
                          <span class="text-muted"> · Piutang hanya untuk member toko ini. Ketik nama / kartu / HP untuk mencari.</span>
                        <?php else : ?>
                          <span class="text-muted"> · Member semua toko (Dukun, Tegalrejo, dll) bisa dipilih. Piutang hanya di toko asal.</span>
                        <?php endif; ?>
                      </small>
                    </div>

                    <!-- View Jika Select Dari Marketplace -->
                    <span id="beli-langsung-marketplace"></span>

                    <div class="form-group">
                      <label><i class="fa fa-credit-card"></i> Tipe Pembayaran <small class="text-muted">(F8 ganti · Alt+C/T)</small></label>
                      <select class="form-control" required="" name="invoice_tipe_transaksi" id="payment-type">
                        <option value="0"<?= (int) $blSavedPaymentType === 0 ? ' selected' : ''; ?>>Cash</option>
                        <option value="1"<?= (int) $blSavedPaymentType === 1 ? ' selected' : ''; ?>>Transfer</option>
                      </select>
                    </div>

                    <!-- QRIS Display untuk Transfer -->
                    <div class="form-group" id="qris-display" style="display: none;">
                      <!-- <label>QRIS Pembayaran</label> -->
                      <?php
                      // Ambil QRIS dari tabel toko berdasarkan toko_cabang
                      $tokoQris = isset($dataTokoLogin['toko_qris']) ? $dataTokoLogin['toko_qris'] : '';
                      if (!empty($tokoQris)) {
                        // Jika QRIS adalah URL gambar
                        if (filter_var($tokoQris, FILTER_VALIDATE_URL) || strpos($tokoQris, 'http') === 0) {
                          echo '<img src="' . htmlspecialchars($tokoQris) . '" alt="QRIS" class="img-fluid" style="max-width: 300px; height: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">';
                        } else {
                          // Jika QRIS adalah path file lokal
                          echo '<img src="' . htmlspecialchars($tokoQris) . '" alt="QRIS" class="img-fluid" style="max-width: 300px; height: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">';
                        }
                      } else {
                        echo '<p class="text-muted">QRIS belum diatur untuk toko ini.</p>';
                      }
                      ?>
                    </div>

                    <div class="form-group">
                      <label><i class="fa fa-truck"></i> Kurir</label>
                      <select class="form-control" required="" name="invoice_kurir">
                        <?php if ($dataTokoLogin['toko_ongkir'] > 0) { ?>
                          <option selected="selected" value="">-- Pilih Kurir --</option>
                        <?php } ?>
                        <option value="0">Tanpa Kurir</option>
                        <?php
                        $kurir = query("SELECT * FROM user WHERE user_level = 'kurir' && user_cabang = $sessionCabang && user_status = '1' ORDER BY user_id DESC ");
                        ?>
                        <?php foreach ($kurir as $row) : ?>
                          <option value="<?= $row['user_id']; ?>">
                            <?= $row['user_nama']; ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <!-- kondisi jika memilih piutang -->
                    <?php if ($r == 1) : ?>
                      <div class="form-group">
                        <label style="color: #f5576c;"><i class="fa fa-calendar"></i> Jatuh Tempo</label>
                        <input type="date" name="invoice_piutang_jatuh_tempo" class="form-control" required="" value="<?= date("Y-m-d"); ?>">
                      </div>
                    <?php else : ?>
                      <input type="hidden" name="invoice_piutang_jatuh_tempo" value="0">
                    <?php endif; ?>

                  </div>
                </div>
                <div class="col-md-6 col-lg-5">
                  <div class="invoice-table">
                    <div class="bl-invoice-inner">
                      <p class="bl-invoice-eyebrow">Ringkasan pembayaran</p>
                      <table class="table bl-checkout-table">
                      <tr class="bl-row-total">
                        <td style="width: 110px;"><b>Total</b></td>
                        <td class="table-nominal">
                          <span>Rp. </span>
                          <span>
                            <input type="text" name="invoice_total" id="angka2" class="a2" value="<?= $total; ?>" onkeyup="return isNumberKey(event)" readonly>
                          </span>
                        </td>
                      </tr>

                      <!-- Ongkir Dinamis untuk Inputan -->
                      <tr class="ongkir-dinamis none bl-row-ongkir">
                        <td>Ongkir</td>
                        <td class="table-nominal tn">
                          <span>Rp.</span>
                          <span class="ongkir-beli-langsung" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                            <input type="number" name="invoice_ongkir" id="" class="b2 ongkir-dinamis-input" autocomplete="off" onkeyup="hitung2();" onkeyup="return isNumberKey(event)" onkeypress="return hanyaAngka1(event)">
                            <i class="fa fa-close fa-ongkir-dinamis"></i>
                          </span>
                        </td>
                      </tr>
                      <tr class="ongkir-dinamis none bl-row-diskon">
                        <td>Diskon</td>
                        <td class="table-nominal tn">
                          <span>Rp.</span>
                          <span>
                            <input type="number" name="invoice_diskon" id="" class="f2 ongkir-dinamis-diskon" autocomplete="off" onkeyup="hitung6();" onkeyup="return isNumberKey(event)" onkeypress="return hanyaAngka1(event)">
                          </span>
                        </td>
                      </tr>

                      <tr class="ongkir-dinamis none bl-row-subtotal">
                        <td>
                          <b>Sub Total</b>
                          <?php if ($r == 1) : ?>
                            <span class="bl-pay-hint">Total tagihan setelah ongkir dan diskon.</span>
                          <?php else : ?>
                            <span class="bl-pay-hint">Total yang harus dibayar pelanggan setelah ongkir dan diskon.</span>
                          <?php endif; ?>
                        </td>

                        <td class="table-nominal c2parent">
                          <span>Rp. </span>
                          <span>
                            <input type="text" name="invoice_sub_total" class="c2" value="<?= $total; ?>" readonly>
                          </span>
                        </td>

                        <td class="table-nominal g2parent" style="display: none;">
                          <span>Rp. </span>
                          <span>
                            <input type="text" name="invoice_sub_total" class="g2" value="<?= $total; ?>" readonly>
                          </span>
                        </td>
                      </tr>

                      <tr class="ongkir-dinamis none bl-row-bayar">
                        <td>
                          <b>
                            <?php
                            // kondisi jika memilih piutang
                            if ($r == 1) {
                              echo "DP";
                            } else {
                              echo "Bayar";
                            }
                            ?>
                          </b>
                        </td>

                        <td class="table-nominal tn d2parent">
                          <span>Rp.</span>
                          <span class="">
                            <input type="text" name="angka1" id="input-bayar-dinamis" class="d2 ongkir-dinamis-bayar" autocomplete="off" onkeyup="hitung3();" onkeypress="return hanyaAngka1(event)" placeholder="F3 / F10 uang pas">
                          </span>
                        </td>

                        <td class="table-nominal tn h2parent" style="display: none;">
                          <span>Rp.</span>
                          <span class="">
                            <input type="text" name="angka1" id="angka1" class="h22 ongkir-dinamis-bayar" autocomplete="off" onkeyup="hitung7();" onkeypress="return hanyaAngka1(event)">
                          </span>
                        </td>
                      </tr>

                      <tr class="ongkir-dinamis none bl-row-kembali">
                        <td>
                          <?php
                          // kondisi jika memilih piutang
                          if ($r == 1) {
                            echo "Sisa Piutang";
                          } else {
                            echo "Kembali";
                          }
                          ?>
                        </td>
                        <td class="table-nominal">
                          <span>Rp.</span>
                          <span>
                            <input type="text" name="hasil" id="hasil" class="e2" readonly disabled>
                          </span>
                        </td>
                      </tr>
                      <!-- End Ongkir Dinamis untuk Inputan -->

                      <!-- Ongkir Statis untuk Inputan -->
                      <tr class="ongkir-statis bl-row-ongkir">
                        <td>Ongkir</td>
                        <td class="table-nominal tn">
                          <span>Rp.</span>
                          <span class="ongkir-beli-langsung" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                            <input type="text" value="<?= number_format($dataTokoLogin['toko_ongkir'], 0, ',', '.'); ?>" name="invoice_ongkir" id="" class="b2 ongkir-statis-input" readonly>
                            <i class="fa fa-close fa-ongkir-statis"></i>
                          </span>
                        </td>
                      </tr>
                      <tr class="ongkir-statis bl-row-diskon">
                        <td>Diskon</td>
                        <td class="table-nominal tn">
                          <span>Rp.</span>
                          <span>
                            <input type="text" name="invoice_diskon" id="" class="f21 ongkir-statis-diskon" value="0" required="" autocomplete="off" onkeyup="hitung5();" onkeypress="return hanyaAngka1(event)">
                          </span>
                        </td>
                      </tr>
                      <tr class="ongkir-statis bl-row-subtotal">
                        <td>
                          <b>Sub Total</b>
                          <?php if ($r == 1) : ?>
                            <span class="bl-pay-hint">Total tagihan setelah ongkir dan diskon.</span>
                          <?php else : ?>
                            <span class="bl-pay-hint">Total yang harus dibayar pelanggan setelah ongkir dan diskon.</span>
                          <?php endif; ?>
                        </td>
                        <td class="table-nominal">
                          <span>Rp. </span>
                          <span>
                            <?php
                            $subTotal = $total + $dataTokoLogin['toko_ongkir'];
                            ?>
                            <input type="hidden" name="" class="g21" value="<?= $subTotal; ?>" readonly>
                            <input type="text" name="invoice_sub_total" class="c21" value="<?= $subTotal; ?>" readonly>
                          </span>

                        </td>
                      </tr>
                      <tr class="ongkir-statis bl-row-bayar">
                        <td>
                          <b>
                            <?php
                            // kondisi jika memilih piutang
                            if ($r == 1) {
                              echo "DP";
                            } else {
                              echo "Bayar";
                            }
                            ?>
                          </b>
                        </td>
                        <td class="table-nominal tn">
                          <span>Rp.</span>
                          <span>
                            <input type="text" name="angka1" id="input-bayar-statis" class="d21 ongkir-statis-bayar" autocomplete="off" onkeyup="hitung4();" onkeypress="return hanyaAngka1(event)" placeholder="F3 / F10 uang pas">
                          </span>
                        </td>
                      </tr>
                      <tr class="ongkir-statis bl-row-kembali">
                        <td>
                          <?php
                          // kondisi jika memilih piutang
                          if ($r == 1) {
                            echo "Sisa Piutang";
                          } else {
                            echo "Kembali";
                          }
                          ?>
                        </td>
                        <td class="table-nominal">
                          <span>Rp.</span>
                          <span>
                            <input type="text" name="hasil" id="hasil" class="e21" readonly disabled>
                          </span>
                        </td>
                      </tr>
                      <!-- End Ongkir Statis untuk Inputan -->


                      <tr class="bl-row-hidden-fields">
                        <td></td>
                        <td>

                          <?php foreach ($keranjang as $stk => $value) : ?>
                            <?php if ($value['keranjang_id_kasir'] === $userId) { ?>
                              <input type="hidden" name="barang_ids[]" value="<?= $value['barang_id']; ?>">
                              <input type="hidden" min="1" name="keranjang_qty[]" value="<?= $value['keranjang_qty']; ?>">
                              <input type="hidden" min="1" name="keranjang_qty_view[]" value="<?= $value['keranjang_qty_view']; ?>">
                              <input type="hidden" name="keranjang_konversi_isi[]" value="<?= $value['keranjang_konversi_isi']; ?>">
                              <input type="hidden" name="keranjang_satuan[]" value="<?= $value['keranjang_satuan']; ?>">
                              <input type="hidden" name="keranjang_harga_beli[]" value="<?= $value['keranjang_harga_beli']; ?>">
                              <input type="hidden" name="keranjang_harga[]" value="<?= $value['keranjang_harga']; ?>">
                              <input type="hidden" name="keranjang_harga_parent[]" value="<?= $value['keranjang_harga_parent']; ?>">
                              <input type="hidden" name="keranjang_harga_edit[]" value="<?= $value['keranjang_harga_edit']; ?>">
                              <input type="hidden" name="keranjang_id_kasir[]" value="<?= $value['keranjang_id_kasir']; ?>">

                              <input type="hidden" name="penjualan_invoice[]" value="<?= $di; ?>">
                              <input type="hidden" name="penjualan_date[]" value="<?= date("Y-m-d") ?>">

                              <input type="hidden" name="keranjang_barang_option_sn[]" value="<?= $value['keranjang_barang_option_sn']; ?>">
                              <input type="hidden" name="keranjang_barang_sn_id[]" value="<?= $value['keranjang_barang_sn_id']; ?>">
                              <input type="hidden" name="keranjang_sn[]" value="<?= $value['keranjang_sn']; ?>">
                              <input type="hidden" name="invoice_customer_category2[]" value="<?= $tipeHarga; ?>">
                              <input type="hidden" name="keranjang_nama[]" value="<?= $value['keranjang_nama']; ?>">
                              <input type="hidden" name="barang_kode_slug[]" value="<?= $value['barang_kode_slug']; ?>">
                              <input type="hidden" name="keranjang_id_cek[]" value="<?= $value['keranjang_id_cek']; ?>">
                              <input type="hidden" name="penjualan_cabang[]" value="<?= $sessionCabang; ?>">
                              <input type="hidden" name="items[]" class="items" value='{"id":"<?= $value['barang_id']; ?>","name":"<?= $value['keranjang_nama']; ?>","quantity":"<?= $value['keranjang_qty_view']; ?>","price":"<?= $value['keranjang_harga']; ?>"}'>
                            <?php } ?>
                          <?php endforeach; ?>
                          <input type="hidden" name="penjualan_invoice2" value="<?= $di; ?>">
                          <input type="hidden" name="invoice_customer_category" value="<?= $tipeHarga; ?>">
                          <input type="hidden" name="kik" value="<?= $userId; ?>">
                          <input type="hidden" name="penjualan_invoice_count" value="<?= $jmlPenjualan1; ?>">
                          <input type="hidden" name="invoice_piutang" value="<?= $r; ?>">
                          <input type="hidden" name="invoice_piutang_lunas" value="0">
                          <input type="hidden" name="invoice_cabang" value="<?= $sessionCabang; ?>">
                          <input type="hidden" name="invoice_total_beli" value="<?= $total_beli; ?>">
                        </td>
                      </tr>
                    </table>
                    </div>
                  </div>
                  <div class="payment">
                    <?php
                    $idKasirKeranjang = $_SESSION['user_id'];
                    $dataSn = mysqli_query($conn, "select * from keranjang where keranjang_barang_option_sn > 0 && keranjang_sn != null && keranjang_cabang = $sessionCabang && keranjang_id_kasir = $idKasirKeranjang");
                    $jmlDataSn = mysqli_num_rows($dataSn);
                    ?>
                    <?php if ($jmlDataSn < 1) { ?>
                      <!-- <button class="btn btn-danger" type="submit" name="updateStockDraft">Transaksi Pending <i class="fa fa-file-o"></i></button> -->
                      <input type="hidden" name="updateStock" value="1">
                      <button class="btn btn-primary updateStok" type="submit" title="Simpan Payment (F6)">Simpan Payment <i class="fa fa-shopping-cart"></i> <small>(F6)</small></button>
                    <?php } else { ?>
                      <!-- <a href="#!" class="btn btn-default jmlDataSn" type="" name="">Transaksi Pending <i class="fa fa-file-o"></i></a> -->
                      <a href="#!" class="btn btn-default jmlDataSn" type="" name="">Simpan Payment <i class="fa fa-shopping-cart"></i></a>
                    <?php } ?>

                    <button type="button" id="create-midtrans" class="btn btn-primary" data-toggle="modal" data-target="#exampleModal" style="display: none">
                      Buat Pesanan
                    </button>

                    <!-- Modal -->
                    <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Midtrans</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            <div class="d-none">
                              <svg
                                class="container"
                                viewBox="0 0 40 40"
                                height="40"
                                width="40">
                                <circle
                                  class="track"
                                  cx="20"
                                  cy="20"
                                  r="17.5"
                                  pathlength="100"
                                  stroke-width="5px"
                                  fill="none" />
                                <circle
                                  class="car"
                                  cx="20"
                                  cy="20"
                                  r="17.5"
                                  pathlength="100"
                                  stroke-width="5px"
                                  fill="none" />
                              </svg>
                            </div>
                            <div id="loaders-midtrans" class="text-center bg-light d-flex justify-content-center align-items-center rounded" style="width:100%;min-height:500px;">
                              <iframe id="snap-midtrans" src="" width="100%" height="500px"></iframe>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                            <button class="btn btn-primary" type="button" id="see-invoice">Lihat Invoice <i class="fa fa-shopping-cart"></i></button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>
        <!-- /.card-body -->
      </div>
    </div>
    <!-- /.col -->
</div>
<!-- /.row -->
</section>
<!-- /.content -->
</div>
</div>


<div class="modal fade" id="modal-id" data-backdrop="static">
  <div class="modal-dialog modal-lg-pop-up">
    <div class="modal-content">
      <div class="modal-body">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Data barang Keseluruhan</h3>
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
          </div>
          <!-- /.card-header -->
          <div class="card-body">
            <div class="table-auto">
              <table id="example1" class="table table-bordered table-striped" style="width: 100%;">
                <thead>
                  <tr>
                    <th style="width: 5%;">No.</th>
                    <th>Kode Barang</th>
                    <th>Nama</th>
                    <th>
                      <?php
                      echo "Harga <b style='color: #007bff;'>" . $nameTipeHarga . "</b>";
                      ?>
                    </th>
                    <th>Stock</th>
                    <th style="text-align: center;">Aksi</th>
                  </tr>
                </thead>
                <tbody>

                </tbody>
              </table>
            </div>
          </div>
          <!-- /.card-body -->
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>



<!-- Modal Update SN -->
<div class="modal fade" id="modal-id-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <form role="form" id="form-edit-no-sn" method="POST" action="">
        <div class="modal-header">
          <h4 class="modal-title">No. SN Produk</h4>
          <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        </div>
        <div class="modal-body" id="data-keranjang-no-sn">

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-primary" name="updateSn">Edit Data</button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- Modal Update QTY Penjualan -->
<div class="modal fade" id="modal-id-2">
  <div class="modal-dialog">
    <div class="modal-content">

      <form role="form" id="form-edit-qty" method="POST" action="">
        <div class="modal-header">
          <h4 class="modal-title">Edit Produk</h4>
          <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        </div>
        <div class="modal-body" id="data-keranjang-qty">

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-primary" name="updateQtyPenjualan">Edit Data</button>
        </div>
      </form>

    </div>
  </div>
</div>

<script>
  $(document).ready(function() {
    var blSearchUrl = <?php
      if ($tipeHarga == 1) {
        echo json_encode('beli-langsung-search-data-grosir-1.php');
      } elseif ($tipeHarga == 2) {
        echo json_encode('beli-langsung-search-data-grosir-2.php');
      } else {
        echo json_encode('beli-langsung-search-data.php');
      }
    ?>;
    var table = $('#example1').DataTable({
      processing: true,
      serverSide: true,
      searching: true,
      ajax: {
        url: blSearchUrl,
        type: 'POST',
        data: function(d) {
          d.cabang = <?= (int) $sessionCabang; ?>;
        }
      },
      columns: [
        { data: 0, searchable: false, orderable: false },
        { data: 1, searchable: true, orderable: true },
        { data: 2, searchable: true, orderable: true },
        { data: 3, searchable: false, orderable: true },
        { data: 4, searchable: false, orderable: true },
        { data: null, searchable: false, orderable: false, defaultContent: "<center><button class='btn btn-primary tblInsert' title='Tambah Keranjang'><i class='fa fa-shopping-cart'></i> Pilih</button></center>" }
      ],
      order: [[2, 'asc']],
      columnDefs: [{
        targets: 3,
        render: $.fn.dataTable.render.number('.', '', '', 'Rp. ')
      }]
    });

    table.on('draw.dt', function() {
      var info = table.page.info();
      table.column(0, {
        search: 'applied',
        order: 'applied',
        page: 'applied'
      }).nodes().each(function(cell, i) {
        cell.innerHTML = i + 1 + info.start;
      });
    });

    $('#example1 tbody').on('click', '.tblInsert', function() {
      var data = table.row($(this).parents('tr')).data();
      var data0 = data[0];
      var data0 = btoa(data0);
      window.location.href = "beli-langsung-add?id=" + data0 + "&customer=<?= $_GET['customer']; ?>&r=<?= $r; ?>";
    });

  });
</script>

<?php include '_footer.php'; ?>

<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<script>
  $(function() {
    if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#example7')) {
      $("#example7").DataTable();
    }
  });
</script>
<script>
  function hanyaAngka(evt) {
    var charCode = (evt.which) ? evt.which : event.keyCode
    if (charCode > 31 && (charCode < 48 || charCode > 57))

      return false;
    return true;
  }

  function hanyaAngka1(evt) {
    var charCode = (evt.which) ? evt.which : event.keyCode
    if (charCode > 31 && (charCode < 48 || charCode > 57))

      return false;
    return true;
  }
</script>
<script>
  function hitung2() {
    var txtFirstNumberValue = document.querySelector('.a2').value;
    var txtSecondNumberValue = document.querySelector('.b2').value;
    var result = parseInt(txtFirstNumberValue) + parseInt(txtSecondNumberValue);
    if (!isNaN(result)) {
      document.querySelector('.c2').value = result;
    }
    blSyncTotalsToCustomerDisplay();
  }

  // Fungsi format ribuan
  function formatRibuan(num) {
    if (!num) return '';
    // Hapus semua karakter non-digit
    var number = num.toString().replace(/[^\d]/g, '');
    // Format dengan titik sebagai separator ribuan
    return number.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  // Fungsi hapus format (kembalikan ke angka saja)
  function hapusFormat(num) {
    if (!num) return 0;
    return parseInt(num.toString().replace(/[^\d]/g, '')) || 0;
  }

  var blSyncTotalsTimer = null;

  function blIsOngkirDinamisPage() {
    return $('.ongkir-dinamis').length && !$('.ongkir-dinamis').first().hasClass('none');
  }

  function blDisplayGetSubTotal() {
    if (blIsOngkirDinamisPage()) {
      var g2vis = $('.g2parent').is(':visible');
      var raw = g2vis ? $('.g2').val() : $('.c2').val();
      return hapusFormat(raw);
    }
    return hapusFormat($('.c21').val());
  }

  function blDisplayGetBayar() {
    if (blIsOngkirDinamisPage()) {
      if ($('.h2parent').is(':visible')) {
        return hapusFormat($('.h22').val());
      }
      return hapusFormat($('.d2').val());
    }
    return hapusFormat($('.d21').val());
  }

  function blDisplayGetKembali() {
    if (blIsOngkirDinamisPage()) {
      return hapusFormat($('.e2').val());
    }
    return hapusFormat($('.e21').val());
  }

  function blDisplayGetOngkir() {
    if (blIsOngkirDinamisPage()) {
      return hapusFormat($('.ongkir-dinamis-input').val());
    }
    return hapusFormat($('.ongkir-statis-input').val());
  }

  function blDisplayGetDiskon() {
    if (blIsOngkirDinamisPage()) {
      return hapusFormat($('.f2').val());
    }
    return hapusFormat($('.f21').val());
  }

  function blSyncTotalsToCustomerDisplay() {
    if (blSyncTotalsTimer) {
      clearTimeout(blSyncTotalsTimer);
    }
    blSyncTotalsTimer = setTimeout(function() {
      var params = new URLSearchParams({
        sub_total: String(blDisplayGetSubTotal()),
        ongkir: String(blDisplayGetOngkir()),
        diskon: String(blDisplayGetDiskon()),
        bayar: String(blDisplayGetBayar()),
        kembali: String(blDisplayGetKembali())
      });
      fetch('api/pos-customer-display-totals.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
      }).catch(function() {});
    }, 120);
  }

  function hitung3() {
    var a = hapusFormat($(".d2").val());
    var b = hapusFormat($(".c2").val());
    c = a - b;
    $(".e2").val(c);
    blSyncTotalsToCustomerDisplay();
  }

  function hitung7() {
    var a = hapusFormat($(".h22").val());
    var b = hapusFormat($(".g2").val());
    c = a - b;
    $(".e2").val(c);
    blSyncTotalsToCustomerDisplay();
  }

  // Diskon
  function hitung6() {
    document.querySelector(".g2parent").style.display = "block";
    document.querySelector(".c2parent").style.display = "none";
    document.querySelector(".h2parent").style.display = "block";
    document.querySelector(".d2parent").style.display = "none";
    var a = $(".c2").val();
    var b = $(".f2").val();
    c = a - b;
    $(".g2").val(c);
    blSyncTotalsToCustomerDisplay();
  }

  // =================================== Statis ================================== //
  // Sub Total - Bayar = kembalian
  function hitung4() {
    var a = hapusFormat($(".d21").val());
    var b = hapusFormat($(".c21").val());
    c = a - b;
    $(".e21").val(c);
    blSyncTotalsToCustomerDisplay();
  }

  // Diskon
  function hitung5() {
    var a = $(".g21").val();
    var b = $(".f21").val();
    c = a - b;
    $(".c21").val(c);
    blSyncTotalsToCustomerDisplay();
  }
  // =================================== End Statis ================================== //

  function isNumberKey(evt) {
    var charCode = (evt.which) ? evt.which : event.keyCode;
    if (charCode != 46 && charCode > 31 && (charCode < 48 || charCode > 57))
      return false;
    return true;
  }
</script>
<script>
  $(function() {

    $('.select2bs4').not('#invoice_customer').select2({
      theme: 'bootstrap4'
    });
  });
</script>
<script>
  window.blMemberConfig = {
    searchUrl: 'api/beli-langsung-customer-search.php',
    tipe: <?= (int) $tipeHarga; ?>,
    piutang: <?= (int) $r === 1 ? 1 : 0; ?>,
    cabangKasir: <?= (int) $sessionCabang; ?>,
    cashUrl: <?= json_encode('beli-langsung' . (isset($_GET['customer']) ? ('?customer=' . $_GET['customer']) : ''), JSON_UNESCAPED_UNICODE); ?>,
    piutangUrl: <?= json_encode('beli-langsung' . (isset($_GET['customer']) ? ('?customer=' . $_GET['customer'] . '&r=MQ==') : '?r=MQ=='), JSON_UNESCAPED_UNICODE); ?>
  };
</script>
<script src="dist/js/beli-langsung-member.js"></script>
<script>
  $(function() {
    if (window.blInitMemberSelect) {
      window.blInitMemberSelect('#invoice_customer');
    }
  });
</script>

<script>
  $(document).ready(function() {

    $(".pilihan-marketplace").change(function() {
      $(this).find("option:selected").each(function() {
        var optionValue = $(this).attr("value");
        if (optionValue) {
          $(".box1").not("." + optionValue).hide();
          $("." + optionValue).show();
        } else {
          $(".box1").hide();
        }
      });
    }).change();

    // Memanggil Pop Up Data Produk SN dan Non SN
    $(document).on('click', '#keranjang_sn', function(e) {
      e.preventDefault();
      $("#modal-id-1").modal('show');
      $.post('beli-langsung-sn.php', {
          id: $(this).attr('data-id')
        },
        function(html) {
          $("#data-keranjang-no-sn").html(html);
        }
      );
    });


    // Memanggil Pop Up Data Edit QTY
    $(document).on('click', '#keranjang-qty', function(e) {
      e.preventDefault();
      $("#modal-id-2").modal('show');
      $.post('beli-langsung-edit-qty.php?customer=<?= $tipeHarga; ?>', {
          id: $(this).attr('data-id')
        },
        function(html) {
          $("#data-keranjang-qty").html(html);
        }
      );
    });

    // Memanggil Pop Up Data Edit Harga
    $(document).on('click', '#keranjang-harga', function(e) {
      e.preventDefault();
      $("#modal-id-2").modal('show');
      $.post('beli-langsung-edit-harga.php?customer=<?= $tipeHarga; ?>', {
          id: $(this).attr('data-id')
        },
        function(html) {
          $("#data-keranjang-harga").html(html);
        }
      );
    });

    $(".jmlDataSn").click(function() {
      alert("Anda Tidak Bisa Melanjutkan Transaksi Karena data No. SN Masih Ada yang Kosong !!");
    });

    // View Hidden Ongkir
    $(".fa-ongkir-statis").click(function() {
      $(".ongkir-statis").addClass("none");
      $(".ongkir-statis-input").attr("name", "");
      $(".ongkir-dinamis-input").attr("name", "invoice_ongkir");

      $(".ongkir-statis-diskon").attr("name", "");
      $(".ongkir-dinamis-diskon").attr("name", "invoice_diskon");

      $(".ongkir-statis-bayar").attr("name", "");
      $(".ongkir-dinamis-bayar").attr("name", "angka1");

      // $(".ongkir-dinamis-bayar").attr("required", true);
      $(".ongkir-statis-bayar").removeAttr("required");
      $(".ongkir-statis-diskon").removeAttr("required");
      $(".ongkir-dinamis-diskon").attr("required", true);
      $(".ongkir-dinamis").removeClass("none");
      blSyncTotalsToCustomerDisplay();
    });

    $(".fa-ongkir-dinamis").click(function() {
      $(".ongkir-dinamis").addClass("none");
      $(".ongkir-dinamis-input").attr("name", "");
      $(".ongkir-statis-input").attr("name", "invoice_ongkir");

      $(".ongkir-dinamis-diskon").attr("name", "");
      $(".ongkir-statis-diskon").attr("name", "invoice_diskon");

      $(".ongkir-dinamis-bayar").attr("name", "");
      $(".ongkir-statis-bayar").attr("name", "angka1");

      // $(".ongkir-dinamis-bayar").removeAttr("required");
      $(".ongkir-dinamis-diskon").removeAttr("required");
      $(".ongkir-statis-diskon").attr("required", true);
      $(".ongkir-statis-bayar").attr("required", true);
      $(".ongkir-statis").removeClass("none");
      blSyncTotalsToCustomerDisplay();
    });
  });

  // load halaman di pilihan select jenis usaha
  $('#beli-langsung-marketplace').load('beli-langsung-marketplace.php');

  // Format ribuan untuk input bayar
  $(document).on('input', '.d2, .d21, .h22', function() {
    var $this = $(this);
    var cursorPos = this.selectionStart;
    var oldValue = $this.val();
    var newValue = formatRibuan(oldValue);
    
    if (oldValue !== newValue) {
      $this.val(newValue);
      // Kembalikan posisi cursor
      var diff = newValue.length - oldValue.length;
      var newCursorPos = Math.max(0, cursorPos + diff);
      this.setSelectionRange(newCursorPos, newCursorPos);
    }
  });

</script>
<script src="dist/js/layar-konsumen-display.js"></script>
<script>
  // ===================== Pintasan keyboard kasir (F1–F12) =====================
  (function() {
    var BL_DARK_MODE_KEY = 'numart_dark_mode';

    function blApplyDarkMode(enabled) {
      if (window.NumartDarkMode) {
        NumartDarkMode.setEnabled(!!enabled);
        return;
      }
      document.body.classList.toggle('bl-dark-mode', !!enabled);
      document.body.classList.toggle('numart-dark-mode', !!enabled);
      try {
        localStorage.setItem(BL_DARK_MODE_KEY, enabled ? '1' : '0');
        localStorage.setItem('numart_pos_dark_mode', enabled ? '1' : '0');
      } catch (err) {}
    }

    function blToggleDarkMode() {
      if (window.NumartDarkMode) {
        NumartDarkMode.toggle();
        return;
      }
      blApplyDarkMode(!document.body.classList.contains('bl-dark-mode'));
    }

    var blCustomerDisplayWindow = null;

    function blGetLayarKonsumenUrl() {
      return 'layar-konsumen.php';
    }

    function blOpenLayarKonsumen() {
      var url = blGetLayarKonsumenUrl();
      if (window.NumartLayarKonsumen && window.NumartLayarKonsumen.openFromKasir) {
        window.NumartLayarKonsumen.openFromKasir(url, blCustomerDisplayWindow).then(function(win) {
          if (win) {
            blCustomerDisplayWindow = win;
          }
        });
        return;
      }
      if (blCustomerDisplayWindow && !blCustomerDisplayWindow.closed) {
        blCustomerDisplayWindow.focus();
        blCustomerDisplayWindow.location.href = url + (url.indexOf('?') >= 0 ? '&' : '?') + 'auto_display=1';
        return;
      }
      blCustomerDisplayWindow = window.open(
        url + '?auto_display=1',
        'numart_layar_konsumen',
        'menubar=no,toolbar=no,location=no,status=no'
      );
    }

    function blIsOngkirDinamis() {
      return $('.ongkir-dinamis').length && !$('.ongkir-dinamis').first().hasClass('none');
    }

    function blGetActiveBayarInput() {
      if (blIsOngkirDinamis()) {
        return $('#input-bayar-dinamis:visible, .ongkir-dinamis-bayar:visible').first();
      }
      return $('#input-bayar-statis:visible, .ongkir-statis-bayar:visible').first();
    }

    function blGetActiveDiskonInput() {
      if (blIsOngkirDinamis()) {
        return $('.f2:visible').first();
      }
      return $('.f21:visible').first();
    }

    function blGetSubTotalNumeric() {
      if (blIsOngkirDinamis()) {
        var g2vis = $('.g2parent').is(':visible');
        var raw = g2vis ? $('.g2').val() : $('.c2').val();
        return hapusFormat(raw);
      }
      return hapusFormat($('.c21').val());
    }

    function blFocusBarcode() {
      var $el = $('#input-barcode');
      if ($el.length) {
        $el.focus().select();
      }
    }

    function blOpenSearchModal() {
      if ($('#modal-id').hasClass('show')) {
        return;
      }
      $('#cari-barang').trigger('click');
      setTimeout(function() {
        $('#modal-id .dataTables_filter input, #example1_filter input').first().focus().select();
      }, 400);
    }

    function blFocusBayar() {
      var $bayar = blGetActiveBayarInput();
      if ($bayar.length) {
        $bayar.focus().select();
      }
    }

    function blFocusDiskon() {
      var $diskon = blGetActiveDiskonInput();
      if ($diskon.length) {
        $diskon.focus().select();
      }
    }

    function blFocusCustomer() {
      var $cust = $('#invoice_customer');
      if ($cust.length && $cust.data('select2')) {
        $cust.select2('open');
      } else if ($cust.length) {
        $cust.focus();
      }
    }

    var blTipeCustomerLabels = ['Umum', 'Member Retail', 'Grosir'];

    function blBuildTipeCustomerUrl(val) {
      var params = new URLSearchParams(window.location.search);
      var r = params.get('r');
      var url = 'beli-langsung?customer=' + btoa(String(val));
      if (r) {
        url += '&r=' + encodeURIComponent(r);
      }
      return url;
    }

    function blRevertTipeCustomerSelect(cur) {
      var $sel = $('#tipe_customer');
      $sel.val(String(cur));
      if ($sel.data('select2')) {
        $sel.trigger('change.select2');
      }
    }

    function blSaveCustomerCtx(customerId) {
      fetch('api/beli-langsung-ctx.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'customer_id=' + encodeURIComponent(String(customerId))
      }).catch(function() {});
    }

    function blSavePaymentCtx(paymentType) {
      fetch('api/beli-langsung-ctx.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'payment_type=' + encodeURIComponent(String(paymentType))
      }).catch(function() {});
    }

    function blRedirectTipeCustomer(val, prevVal) {
      var $sel = $('#tipe_customer');
      if (!$sel.length) {
        return;
      }
      val = parseInt(val, 10);
      if (isNaN(val) || val < 0 || val > 2) {
        return;
      }
      var cur = (prevVal !== undefined && prevVal !== null)
        ? (parseInt(prevVal, 10) || 0)
        : (parseInt($sel.val(), 10) || 0);
      if (val === cur) {
        return;
      }

      var label = blTipeCustomerLabels[val] || 'baru';
      if ($('.bl-cart-row').length) {
        if (!confirm('Ganti tipe customer ke "' + label + '"? Harga semua item di keranjang akan disesuaikan.')) {
          blRevertTipeCustomerSelect(cur);
          return;
        }
        fetch('api/beli-langsung-change-tipe.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'from_tipe=' + encodeURIComponent(String(cur)) + '&to_tipe=' + encodeURIComponent(String(val))
        })
          .then(function(res) { return res.json(); })
          .then(function(data) {
            if (!data || !data.ok) {
              alert((data && data.message) ? data.message : 'Gagal mengubah tipe customer.');
              blRevertTipeCustomerSelect(cur);
              return;
            }
            window.location.href = blBuildTipeCustomerUrl(val);
          })
          .catch(function() {
            alert('Gagal mengubah tipe customer.');
            blRevertTipeCustomerSelect(cur);
          });
        return;
      }
      window.location.href = blBuildTipeCustomerUrl(val);
    }

    function blCycleTipeCustomer(step) {
      step = step || 1;
      var cur = parseInt($('#tipe_customer').val(), 10) || 0;
      var next = (cur + step + 3) % 3;
      blRedirectTipeCustomer(next);
    }

    function blOpenTipeCustomerSelect() {
      var $sel = $('#tipe_customer');
      if ($sel.length && $sel.data('select2')) {
        $sel.select2('open');
      } else if ($sel.length) {
        $sel.focus();
      }
    }

    function blSetPaymentType(val) {
      var $type = $('#payment-type');
      if (!$type.length) {
        return;
      }
      if (String($type.val()) === String(val)) {
        return;
      }
      $type.val(String(val)).trigger('change');
    }

    function blSyncPaymentToCustomerDisplay(paymentType) {
      fetch('api/pos-customer-display-payment.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'payment_type=' + encodeURIComponent(String(paymentType))
      }).catch(function() {});
    }

    function blBindCustomerPaymentHandlers() {
      var blPrevTipeCustomer = parseInt($('#tipe_customer').val(), 10) || 0;
      var blTipeBusy = false;

      function blOnCustomerPicked(customerId) {
        blSaveCustomerCtx(customerId);
      }

      $('#invoice_customer')
        .off('select2:select.blCust change.blCust')
        .on('select2:select.blCust', function(e) {
          if (!e.params || !e.params.data) {
            return;
          }
          blOnCustomerPicked(e.params.data.id);
        })
        .on('change.blCust', function() {
          blOnCustomerPicked($(this).val());
        });

      function blOnTipeCustomerPicked(newVal) {
        if (blTipeBusy) {
          return;
        }
        newVal = parseInt(newVal, 10) || 0;
        if (newVal === blPrevTipeCustomer) {
          return;
        }
        blTipeBusy = true;
        var prev = blPrevTipeCustomer;
        var hasCart = $('.bl-cart-row').length > 0;
        var label = blTipeCustomerLabels[newVal] || 'baru';

        if (hasCart) {
          if (!confirm('Ganti tipe customer ke "' + label + '"? Harga semua item di keranjang akan disesuaikan.')) {
            blRevertTipeCustomerSelect(prev);
            blTipeBusy = false;
            return;
          }
          fetch('api/beli-langsung-change-tipe.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'from_tipe=' + encodeURIComponent(String(prev)) + '&to_tipe=' + encodeURIComponent(String(newVal))
          })
            .then(function(res) { return res.json(); })
            .then(function(data) {
              if (!data || !data.ok) {
                alert((data && data.message) ? data.message : 'Gagal mengubah tipe customer.');
                blRevertTipeCustomerSelect(prev);
                blTipeBusy = false;
                return;
              }
              window.location.href = blBuildTipeCustomerUrl(newVal);
            })
            .catch(function() {
              alert('Gagal mengubah tipe customer.');
              blRevertTipeCustomerSelect(prev);
              blTipeBusy = false;
            });
          return;
        }

        window.location.href = blBuildTipeCustomerUrl(newVal);
      }

      $('#tipe_customer')
        .off('select2:select.blTipe')
        .on('select2:select.blTipe', function(e) {
          if (!e.params || !e.params.data) {
            return;
          }
          blOnTipeCustomerPicked(e.params.data.id);
        });

      $('#payment-type').off('change.blPay').on('change.blPay', function() {
        blSyncPaymentToCustomerDisplay(this.value);
        blSavePaymentCtx(this.value);
        if (this.value == 1) {
          $('#qris-display').show();
          $('.updateStok').prop('disabled', false).show();
          $('#create-midtrans').prop('disabled', true).hide();
        } else {
          $('.updateStok').prop('disabled', false).show();
          $('#create-midtrans').prop('disabled', true).hide();
          $('#qris-display').hide();
        }
      });

      blSyncPaymentToCustomerDisplay($('#payment-type').val() || '0');
      if (($('#payment-type').val() || '0') == 1) {
        $('#qris-display').show();
      }
      setTimeout(function() {
        if (typeof blSyncTotalsToCustomerDisplay === 'function') {
          blSyncTotalsToCustomerDisplay();
        }
      }, 400);
    }

    function blGetLastCartRow() {
      return $('#bl-last-cart-row').length ? $('#bl-last-cart-row') : $('.bl-cart-row').first();
    }

    function blEditLastQty() {
      var $row = blGetLastCartRow();
      if (!$row.length) {
        alert('Keranjang masih kosong.');
        return;
      }
      $row.find('.bl-btn-edit-qty').first().trigger('click');
    }

    function blDeleteLastItem() {
      var $row = blGetLastCartRow();
      if (!$row.length) {
        alert('Keranjang masih kosong.');
        return;
      }
      var $link = $row.find('.bl-btn-delete').first();
      if ($link.length && confirm('Hapus item terakhir dari keranjang?')) {
        window.location.href = $link.attr('href');
      }
    }

    function blFillExactPayment() {
      var subtotal = blGetSubTotalNumeric();
      if (!subtotal || subtotal < 1) {
        alert('Total belum tersedia.');
        return;
      }
      var $bayar = blGetActiveBayarInput();
      if (!$bayar.length) {
        return;
      }
      $bayar.val(formatRibuan(String(subtotal)));
      if (blIsOngkirDinamis()) {
        if ($('.g2parent').is(':visible')) {
          hitung7();
        } else {
          hitung3();
        }
      } else {
        hitung4();
      }
      $bayar.focus().select();
    }

    function blSubmitPayment() {
      if ($('.jmlDataSn').length && $('.updateStok').length < 1) {
        alert('Lengkapi No. SN terlebih dahulu sebelum menyimpan transaksi.');
        return;
      }
      var $btn = $('#form-main button.updateStok[type="submit"]');
      if ($btn.length && !$btn.prop('disabled')) {
        $btn.trigger('click');
      }
    }

    function blTogglePaymentType() {
      blSetPaymentType($('#payment-type').val() === '1' ? '0' : '1');
    }

    function blCloseModals() {
      $('.modal.show').modal('hide');
    }

    function blAnyModalOpen() {
      return $('.modal.show').length > 0;
    }

    function blShowHelp() {
      alert(
        'PINTASAN KEYBOARD KASIR\n\n' +
        'F1  — Fokus scan barcode / kode barang\n' +
        'F2  — Buka pencarian manual (cari produk)\n' +
        'F3  — Fokus input nominal bayar\n' +
        'F4  — Edit qty item terakhir di-scan\n' +
        'F5  — Hapus item terakhir di keranjang\n' +
        'F6  — Simpan payment / selesaikan transaksi\n' +
        'F7  — Ganti tipe customer (Umum → Retail → Grosir)\n' +
        'F8  — Ganti tipe pembayaran (Cash ↔ Transfer)\n' +
        'F9  — Fokus input diskon\n' +
        'F10 — Isi nominal uang pas (sesuai sub total)\n' +
        'F11 — Tutup popup / modal\n' +
        'F12 — Tampilkan bantuan ini\n' +
        'Ctrl+F7 — Pilih customer / pembeli\n' +
        'Shift+F7 — Tipe customer mundur (Grosir → Retail → Umum)\n' +
        'Alt+1 — Tipe customer: Umum\n' +
        'Alt+2 — Tipe customer: Member Retail\n' +
        'Alt+3 — Tipe customer: Grosir\n' +
        'Alt+C — Tipe pembayaran: Cash\n' +
        'Alt+T — Tipe pembayaran: Transfer\n' +
        'Alt+S — Tampilkan / sembunyikan sidebar menu\n' +
        'Alt+D — Dark mode / light mode\n' +
        'Alt+K — Layar konsumen ke monitor 2 + layar penuh\n' +
        'Esc — Tutup modal\n\n' +
        'Baris hijau = item terakhir di-scan (target F4 & F5).'
      );
    }

    var blShortcuts = {
      112: blFocusBarcode,       // F1
      113: blOpenSearchModal,    // F2
      114: blFocusBayar,         // F3
      115: blEditLastQty,        // F4
      116: blDeleteLastItem,     // F5
      117: blSubmitPayment,      // F6
      118: function() { blCycleTipeCustomer(1); },  // F7
      119: blTogglePaymentType,  // F8
      120: blFocusDiskon,        // F9
      121: blFillExactPayment,   // F10
      122: blCloseModals,        // F11
      123: blShowHelp            // F12
    };

    $(document).on('keydown', function(e) {
      var isTyping = $(e.target).is('input, textarea') && !$(e.target).is('#input-barcode, .ongkir-statis-bayar, .ongkir-dinamis-bayar, .f21, .f2');

      // Ctrl+F5 = refresh halaman (jangan ditangkap pintasan kasir)
      if (e.ctrlKey && e.keyCode === 116) {
        return;
      }

      // Ctrl+F lain (kecuali F7) — serahkan ke browser
      if (e.ctrlKey && e.keyCode >= 112 && e.keyCode <= 123 && e.keyCode !== 118) {
        return;
      }

      // Ctrl+F7 = pilih customer/pembeli
      if (e.ctrlKey && !e.altKey && e.keyCode === 118) {
        e.preventDefault();
        if (!blAnyModalOpen()) {
          blFocusCustomer();
        }
        return;
      }

      // Shift+F7 = tipe customer mundur
      if (e.shiftKey && !e.ctrlKey && !e.altKey && e.keyCode === 118) {
        e.preventDefault();
        if (!blAnyModalOpen()) {
          blCycleTipeCustomer(-1);
        }
        return;
      }

      // Alt+1/2/3 = tipe customer langsung
      if (e.altKey && !e.ctrlKey && !e.shiftKey && !blAnyModalOpen()) {
        if (e.keyCode === 49 || e.key === '1') {
          e.preventDefault();
          blRedirectTipeCustomer(0);
          return;
        }
        if (e.keyCode === 50 || e.key === '2') {
          e.preventDefault();
          blRedirectTipeCustomer(1);
          return;
        }
        if (e.keyCode === 51 || e.key === '3') {
          e.preventDefault();
          blRedirectTipeCustomer(2);
          return;
        }
        if (e.keyCode === 67 || e.key === 'c' || e.key === 'C') {
          e.preventDefault();
          blSetPaymentType(0);
          return;
        }
        if (e.keyCode === 84 || e.key === 't' || e.key === 'T') {
          e.preventDefault();
          blSetPaymentType(1);
          return;
        }
        if (e.keyCode === 68 || e.key === 'd' || e.key === 'D') {
          e.preventDefault();
          blToggleDarkMode();
          return;
        }
        if (e.keyCode === 75 || e.key === 'k' || e.key === 'K') {
          e.preventDefault();
          blOpenLayarKonsumen();
          return;
        }
      }

      if (!blShortcuts[e.keyCode]) {
        return;
      }

      if (e.keyCode === 123) {
        e.preventDefault();
        blShowHelp();
        return;
      }

      if (e.keyCode === 122) {
        e.preventDefault();
        blCloseModals();
        return;
      }

      if (blAnyModalOpen() && e.keyCode !== 113 && e.keyCode !== 122 && e.keyCode !== 123) {
        return;
      }

      if (isTyping && e.keyCode >= 112 && e.keyCode <= 123) {
        return;
      }

      e.preventDefault();
      blShortcuts[e.keyCode]();
    });

    $(document).on('keydown', '#input-barcode', function(e) {
      if (e.keyCode === 114) {
        e.preventDefault();
        blFocusBayar();
      }
    });

    $(document).on('keydown', '.ongkir-statis-bayar, .ongkir-dinamis-bayar', function(e) {
      if (e.keyCode === 117) {
        e.preventDefault();
        blSubmitPayment();
      }
      if (e.keyCode === 112) {
        e.preventDefault();
        blFocusBarcode();
      }
    });

    $('#bl-kbd-help-btn').on('click', blShowHelp);
    $('#bl-open-layar-konsumen').on('click', blOpenLayarKonsumen);

    try {
      blApplyDarkMode(window.NumartDarkMode ? NumartDarkMode.isEnabled() : (localStorage.getItem(BL_DARK_MODE_KEY) === '1' || localStorage.getItem('numart_pos_dark_mode') === '1'));
    } catch (err) {}

    $('#modal-id').on('shown.bs.modal', function() {
      setTimeout(function() {
        $('#modal-id .dataTables_filter input').first().focus().select();
      }, 200);
    });

    $('#see-invoice').on('click', function() {
      window.location.href = 'invoice?no=' + encodeURIComponent($('[name=invoicing]').val() || '');
    });

    $(function() {
      blBindCustomerPaymentHandlers();
    });
  })();

  // Pastikan nilai yang dikirim adalah angka tanpa format saat submit + cegah double submit
  $(document).on('submit', '#form-main', function(e) {
    var $form = $(this);
    var $payBtn = $form.find('button.updateStok[type="submit"]');

    $('.d2, .d21, .h22').each(function() {
      var $this = $(this);
      var formattedValue = $this.val();
      var numericValue = hapusFormat(formattedValue);
      $this.val(numericValue);
    });

    if ($payBtn.length) {
      if ($form.data('submitting')) {
        e.preventDefault();
        return false;
      }
      $form.data('submitting', true);
      $payBtn.prop('disabled', true).html('Memproses... <i class="fa fa-spinner fa-spin"></i>');
    }
  });
</script>

</body>

</html>

<script>
  // Aksi Select Status
  function myFunction() {
    var x = document.getElementById("mySelect").value;
    if (x === "1") {
      document.location.href = "beli-langsung?customer=<?= base64_encode(1); ?>";

    } else if (x === "2") {
      document.location.href = "beli-langsung?customer=<?= base64_encode(2); ?>";

    } else {
      document.location.href = "beli-langsung?customer=<?= base64_encode(0); ?>";
    }
  }
</script>