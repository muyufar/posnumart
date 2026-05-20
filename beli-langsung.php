<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

$userId = $_SESSION['user_id'];
$tipeHarga = base64_decode($_GET['customer']);
if ($tipeHarga == 1) {
  $nameTipeHarga = "Member Retail";
} elseif ($tipeHarga == 2) {
  $nameTipeHarga = "Grosir";
} else {
  $nameTipeHarga = "Umum";
}

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

// Insert Ke keranjang Scan Barcode
if (isset($_POST["inputbarcode"])) {
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if (tambahKeranjangBarcode($_POST) > 0) {
    echo "
      <script>
        document.location.href = '';
      </script>
    ";
  }
}

error_reporting(0);
// Insert Ke keranjang
$inv = $_POST["penjualan_invoice2"] ?? '';
if (isset($_POST["updateStock"]) && !empty($inv)) {
  // Debug: Log data yang diterima
  error_log("updateStock called with invoice: " . $inv);
  error_log("POST data keys: " . implode(", ", array_keys($_POST)));
  
  $sql = mysqli_query($conn, "SELECT * FROM invoice WHERE penjualan_invoice='$inv' && invoice_cabang = '$sessionCabang' ") or die(mysqli_error($conn));

  $hasilquery = mysqli_num_rows($sql);

  if ($hasilquery == 0) {
    // cek apakah data berhasil di tambahkan atau tidak
    $result = updateStock($_POST);
    
    // Cek apakah invoice sudah berhasil dibuat (double check)
    $sql_check = mysqli_query($conn, "SELECT * FROM invoice WHERE penjualan_invoice='$inv' && invoice_cabang = '$sessionCabang' ");
    $invoice_exists = mysqli_num_rows($sql_check);
    
    // Debug: Tampilkan error jika ada
    if ($result == 0 && $invoice_exists == 0) {
      $error_msg = mysqli_error($conn);
      if (!empty($error_msg)) {
        echo "<script>alert('Error: " . addslashes($error_msg) . "');</script>";
      }
    }
    
    if ($result > 0 || $invoice_exists > 0) {
      // Redirect ke invoice setelah payment berhasil
      ?>
      <!DOCTYPE html>
      <html>
      <head>
        <meta http-equiv="refresh" content="0;url=invoice?no=<?= $inv ?>">
      </head>
      <body>
        <script>
          window.location.href = 'invoice?no=<?= $inv ?>';
        </script>
        <p>Redirecting... <a href="invoice?no=<?= $inv ?>">Click here if not redirected</a></p>
      </body>
      </html>
      <?php
      exit;
    } else {
      // Debug: Tampilkan detail error
      $error_msg = mysqli_error($conn);
      $debug_info = "Invoice: " . $inv . "\n";
      $debug_info .= "Result: " . $result . "\n";
      $debug_info .= "Invoice exists: " . $invoice_exists . "\n";
      if (!empty($error_msg)) {
        $debug_info .= "Error: " . $error_msg . "\n";
      }
      error_log("Transaction failed: " . $debug_info);
      
      echo "
          <script>
            alert('Transaksi Gagal !!\\n\\n" . addslashes($debug_info) . "');
            window.location.href = '';
          </script>
        ";
      exit;
    }
  } else {
    // Invoice sudah ada, langsung redirect
    ?>
    <!DOCTYPE html>
    <html>
    <head>
      <meta http-equiv="refresh" content="0;url=invoice?no=<?= $inv ?>">
    </head>
    <body>
      <script>
        window.location.href = 'invoice?no=<?= $inv ?>';
      </script>
      <p>Redirecting... <a href="invoice?no=<?= $inv ?>">Click here if not redirected</a></p>
    </body>
    </html>
    <?php
    exit;
  }
}

if (isset($_POST["updateStockDraft"])) {
  // var_dump($_POST);
  $sql = mysqli_query($conn, "SELECT * FROM invoice WHERE penjualan_invoice='$inv' && invoice_cabang = '$sessionCabang' ") or die(mysqli_error($conn));

  $hasilquery = mysqli_num_rows($sql);

  if ($hasilquery == 0) {
    // cek apakah data berhasil di tambahkan atau tidak
    if (updateStockDraft($_POST) > 0) {
      echo "
          <script>
            document.location.href = '';
            alert('Transaksi Berhasil Dipending !!');
          </script>
        ";
    } else {
      echo "
          <script>
            alert('Transaksi Gagal !!');
          </script>
        ";
    }
  } else {
    echo "
        <script>
          document.location.href = '';
          alert('Transaksi Berhasil dipending !!');
        </script>
      ";
  }
}

if (isset($_POST["updateSn"])) {
  if (updateSn($_POST) > 0) {
    echo "
        <script>
          document.location.href = '';
        </script>
      ";
  } else {
    echo "
        <script>
          alert('Data Gagal edit');
        </script>
      ";
  }
}

if (isset($_POST["updateQtyPenjualan"])) {
  if (updateQTYHarga($_POST) > 0) {
    echo "
        <script>
          document.location.href = '';
        </script>
      ";
  } else {
    echo "
        <script>
          alert('Data Gagal edit');
        </script>
      ";
  }
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

  /* Header Section - Clean & Professional */
  .content-header {
    background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);
    color: #ffffff;
    padding: 1.75rem 0;
    margin-bottom: 1.5rem;
    border-radius: 0;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  .content-header h1 {
    color: #ffffff;
    font-weight: 600;
    margin-bottom: 0.75rem;
    font-size: 1.75rem;
    letter-spacing: -0.3px;
  }

  .content-header h1 b {
    background: rgba(255, 255, 255, 0.15);
    color: #ffffff;
    padding: 0.4rem 1rem;
    border-radius: var(--radius);
    font-weight: 600;
    display: inline-block;
    margin-left: 0.5rem;
    font-size: 0.9em;
  }

  .btn-cash-piutang {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 1rem;
  }

  .btn-cash-piutang .btn {
    border-radius: var(--radius);
    padding: 0.625rem 1.5rem;
    font-weight: 500;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    border: 1px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
    min-width: 110px;
    text-align: center;
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

  /* Main Card - Clean Design */
  .card {
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    margin-bottom: 1.5rem;
    overflow: hidden;
    background: #ffffff;
  }

  .card-header {
    background: #ffffff;
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem;
  }

  /* Invoice Section - Clean Design */
  .card-invoice {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: #f9fafb;
    padding: 1rem 1.5rem;
    border-radius: var(--radius);
    border: 1px solid var(--border-color);
  }

  .card-invoice span {
    font-weight: 500;
    color: var(--text-muted);
    font-size: 0.875rem;
  }

  .card-invoice span i {
    margin-right: 0.5rem;
    color: var(--primary-color);
  }

  .card-invoice input {
    border: 1px solid var(--border-color);
    background: #ffffff;
    font-weight: 600;
    font-size: 1rem;
    color: var(--text-dark);
    flex: 1;
    padding: 0.5rem 0.75rem;
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
    .content-header {
      padding: 1.5rem 0;
    }

    .btn-cash-piutang {
      flex-direction: column;
    }

    .card-header .row {
      flex-direction: column;
      gap: 1rem;
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
    padding: 1.5rem;
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
</style>



<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1>Transaksi Kasir <b> Customer <?= $nameTipeHarga; ?></b></h1>
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
              <a href="beli-langsung?customer=<?= $_GET['customer']; ?>" class="btn btn-default">
                <i class="fa fa-money"></i> Cash
              </a>
              <a href="beli-langsung?customer=<?= $_GET['customer']; ?>&r=MQ==" class="btn btn-primary">
                <i class="fa fa-credit-card"></i> Piutang
              </a>
            <?php else : ?>
              <a href="beli-langsung?customer=<?= $_GET['customer']; ?>" class="btn btn-primary">
                <i class="fa fa-money"></i> Cash
              </a>
              <a href="beli-langsung?customer=<?= $_GET['customer']; ?>&r=MQ==" class="btn btn-default">
                <i class="fa fa-credit-card"></i> Piutang
              </a>
            <?php endif; ?>
            <!-- <a class="btn btn-danger" data-toggle="modal" href='#modal-id-draft' data-backdrop="static">Pending</a> -->
            <!-- <a class="btn btn-info" href="beli-langsung-transfer?customer=<?= $_GET['customer']; ?>" data-backdrop="static">Transfer</a> -->
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
          </div>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Barang</li>
          </ol>
        </div>
      </div>
    </div><!-- /.container-fluid -->
  </section>


  <section class="content">
    <?php
    $userId = $_SESSION['user_id'];
    $keranjang = query("SELECT * FROM keranjang WHERE keranjang_id_kasir = $userId && keranjang_tipe_customer = $tipeHarga && keranjang_cabang = $sessionCabang ORDER BY keranjang_id DESC");

    $countInvoice = mysqli_query($conn, "select * from invoice where invoice_cabang = " . $sessionCabang . " ");
    $countInvoice = mysqli_num_rows($countInvoice);
    if ($countInvoice < 1) {
      $jmlPenjualan1 = 0;
    } else {
      $penjualan = query("SELECT * FROM invoice WHERE invoice_cabang = $sessionCabang ORDER BY invoice_id DESC lIMIT 1")[0];
      $jmlPenjualan1 = $penjualan['penjualan_invoice_count'];
    }
    $jmlPenjualan1 = $jmlPenjualan1 + 1;
    ?>
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header">
          <div class="row">
            <div class="col-md-8 col-lg-8">
              <div class="card-invoice">
                <span><i class="fa fa-file-text-o"></i> No. Invoice: </span>
                <?php
                $today = date("Ymdis");
                $di = $today . $jmlPenjualan1 . $userId ;
                ?>
                <input type="text" name="invoicing" id="invoicing" value="<?= $di  ?>" readonly>
              </div>
            </div>
            <div class="col-md-4 col-lg-4">
              <div class="cari-barang-parent">
                <div class="row">
                  <div class="col-10">
                    <form action="" method="post">
                      <input type="hidden" name="keranjang_id_kasir" value="<?= $userId; ?>">
                      <input type="hidden" name="keranjang_cabang" value="<?= $sessionCabang; ?>">
                      <input type="hidden" name="tipe_harga" value="<?= $tipeHarga; ?>">
                      <input type="text" class="form-control" autofocus="" name="inputbarcode" placeholder="🔍 Scan Barcode / Kode Barang" required="">
                    </form>
                  </div>
                  <div class="col-2">
                    <a class="btn btn-primary" title="Cari Produk" data-toggle="modal" id="cari-barang" href='#modal-id' style="width: 100%;">
                      <i class="fa fa-search text-white"></i>
                    </a>
                  </div>
                </div>
              </div>
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
                    $total_beli += $sub_total_beli;
                    $total += $sub_total;
                ?>
                    <tr>
                      <td><?= $i; ?></td>
                      <td><?= $row['keranjang_nama'] ?></td>
                      <td>Rp. <?= number_format($row['keranjang_harga'], 0, ',', '.'); ?></td>
                      <td>
                        <?php
                        $satuan = $row['keranjang_satuan'];
                        $dataSatuan = mysqli_query($conn, "select satuan_nama from satuan where satuan_id = " . $satuan . " ");
                        $dataSatuan = mysqli_fetch_array($dataSatuan);
                        $dataSatuan = $dataSatuan['satuan_nama'];
                        echo $dataSatuan;
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
                          <button class="btn btn-primary" name="" class="keranjang-pembelian" id="keranjang-qty" data-id="<?= $row['keranjang_id']; ?>">
                            <i class="fa fa-pencil"></i>
                          </button>
                        </a>
                        <a href="beli-langsung-delete?id=<?= $row['keranjang_id']; ?>&customer=<?= $_GET['customer']; ?>&r=<?= $r; ?>" title="Delete Data" onclick="return confirm('Yakin dihapus ?')">
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
                      <label><i class="fa fa-users"></i> Tipe Customer</label>
                      <select class="form-control pilihan-marketplace select2bs4" name="tipe_customer" id="tipe_customer">
                        <option value="0" <?= $tipeHarga == 0 ? 'selected' : null ?>>Umum</option>
                        <option value="1" <?= $tipeHarga == 1 ? 'selected' : null ?>>Member Retail</option>
                        <option value="2" <?= $tipeHarga == 2 ? 'selected' : null ?>>Grosir</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label><i class="fa fa-user"></i> Customer <b style="color: #0d9488;"><?= $nameTipeHarga; ?></b></label>
                      <select class="form-control pilihan-marketplace select2bs4" required="" name="invoice_customer">
                        <!-- <option selected="selected" value="">Pilih Customer</option> -->

                        <?php if ($r != 1 && $tipeHarga < 2) { ?>
                          <option value="0">Umum</option>
                        <?php } ?>

                        <?php
                        $customer = query("SELECT * FROM customer WHERE customer_cabang = $sessionCabang && customer_status = 1 && customer_category = $tipeHarga ORDER BY customer_id DESC ");
                        ?>
                       <?php foreach ($customer as $ctr) : ?>
  <?php if ($ctr['customer_id'] > 1 && $ctr['customer_nama'] !== "Customer Umum") { ?>
    <option value="<?= $ctr['customer_id'] ?>">
      <?= $ctr['customer_nama'] ?> 
      <?php if (!empty($ctr['customer_kartu'])): ?>
        (<?= $ctr['customer_kartu'] ?>)
      <?php endif; ?>
    </option>
  <?php } ?>
<?php endforeach; ?>

                      </select>
                      <small>
                        <a href="customer-add"><i class="fa fa-plus-circle"></i> Tambah Customer Baru</a>
                      </small>
                    </div>

                    <!-- View Jika Select Dari Marketplace -->
                    <span id="beli-langsung-marketplace"></span>

                    <div class="form-group">
                      <label><i class="fa fa-credit-card"></i> Tipe Pembayaran</label>
                      <select class="form-control" required="" name="invoice_tipe_transaksi" id="payment-type">
                        <option selected="selected" value="0">Cash</option>
                        <option value="1">Transfer</option>
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
                            <input type="text" name="angka1" id="angka1" class="d2 ongkir-dinamis-bayar" autocomplete="off" onkeyup="hitung3();" onkeypress="return hanyaAngka1(event)">
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
                            <input type="text" name="angka1" id="angka1" class="d21 ongkir-statis-bayar" autocomplete="off" onkeyup="hitung4();" onkeypress="return hanyaAngka1(event)">
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
                              <!-- <input type="hidden" name="barang_ids[]" value="<?= $value['barang_id']; ?>">
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
                              <input type="hidden" name="penjualan_cabang[]" value="<?= $sessionCabang; ?>"> -->
                              <input type="hidden" name="barang_ids[<?= $stk ?>]" value="<?= $value['barang_id']; ?>">
                              <input type="hidden" min="1" name="keranjang_qty[<?= $stk ?>]" value="<?= $value['keranjang_qty']; ?>">
                              <input type="hidden" min="1" name="keranjang_qty_view[<?= $stk ?>]" value="<?= $value['keranjang_qty_view']; ?>">
                              <input type="hidden" name="keranjang_konversi_isi[<?= $stk ?>]" value="<?= $value['keranjang_konversi_isi']; ?>">
                              <input type="hidden" name="keranjang_satuan[<?= $stk ?>]" value="<?= $value['keranjang_satuan']; ?>">
                              <input type="hidden" name="keranjang_harga_beli[<?= $stk ?>]" value="<?= $value['keranjang_harga_beli']; ?>">
                              <input type="hidden" name="keranjang_harga[<?= $stk ?>]" value="<?= $value['keranjang_harga']; ?>">
                              <input type="hidden" name="keranjang_harga_parent[<?= $stk ?>]" value="<?= $value['keranjang_harga_parent']; ?>">
                              <input type="hidden" name="keranjang_harga_edit[<?= $stk ?>]" value="<?= $value['keranjang_harga_edit']; ?>">
                              <input type="hidden" name="keranjang_id_kasir[<?= $stk ?>]" value="<?= $value['keranjang_id_kasir']; ?>">

                              <input type="hidden" name="penjualan_invoice[<?= $stk ?>]" value="<?= $di; ?>">
                              <input type="hidden" name="penjualan_date[<?= $stk ?>]" value="<?= date("Y-m-d") ?>">

                              <input type="hidden" name="keranjang_barang_option_sn[<?= $stk ?>]" value="<?= $value['keranjang_barang_option_sn']; ?>">
                              <input type="hidden" name="keranjang_barang_sn_id[<?= $stk ?>]" value="<?= $value['keranjang_barang_sn_id']; ?>">
                              <input type="hidden" name="keranjang_sn[<?= $stk ?>]" value="<?= $value['keranjang_sn']; ?>">
                              <input type="hidden" name="invoice_customer_category2[<?= $stk ?>]" value="<?= $tipeHarga; ?>">
                              <input type="hidden" name="keranjang_nama[<?= $stk ?>]" value="<?= $value['keranjang_nama']; ?>">
                              <input type="hidden" name="barang_kode_slug[<?= $stk ?>]" value="<?= $value['barang_kode_slug']; ?>">
                              <input type="hidden" name="keranjang_id_cek[<?= $stk ?>]" value="<?= $value['keranjang_id_cek']; ?>">
                              <input type="hidden" name="penjualan_cabang[<?= $stk ?>]" value="<?= $sessionCabang; ?>">
                              <input type="hidden" name="items[<?= $stk ?>]" class="items" value='{"id":"<?= $value['barang_id']; ?>","name":"<?= $value['keranjang_nama']; ?>","quantity":"<?= $value['keranjang_qty_view']; ?>","price":"<?= $value['keranjang_harga']; ?>"}'>
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
                      <button class="btn btn-primary updateStok" type="submit" name="updateStock">Simpan Payment <i class="fa fa-shopping-cart"></i></button>
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
    var table = $('#example1').DataTable({
      "processing": true,
      "serverSide": true,

      <?php if ($tipeHarga == 1) : ?> "ajax": "beli-langsung-search-data-grosir-1.php?cabang=<?= $sessionCabang; ?>",
      <?php elseif ($tipeHarga == 2) : ?> "ajax": "beli-langsung-search-data-grosir-2.php?cabang=<?= $sessionCabang; ?>",
      <?php else : ?> "ajax": "beli-langsung-search-data.php?cabang=<?= $sessionCabang; ?>",
      <?php endif; ?>

      "columnDefs": [{
          "targets": 3,
          "render": $.fn.dataTable.render.number('.', '', '', 'Rp. ')

        },
        {
          "targets": -1,
          "data": null,
          "defaultContent": `<center>

                      <button class='btn btn-primary tblInsert' title="Tambah Keranjang">
                         <i class="fa fa-shopping-cart"></i> Pilih
                      </button>

                  </center>`
        }
      ]
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
    $("#example1").DataTable();
  });
  $(function() {
    $("#example7").DataTable();
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

  function hitung3() {
    var a = hapusFormat($(".d2").val());
    var b = hapusFormat($(".c2").val());
    c = a - b;
    $(".e2").val(c);
  }

  function hitung7() {
    var a = hapusFormat($(".h22").val());
    var b = hapusFormat($(".g2").val());
    c = a - b;
    $(".e2").val(c);
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
  }

  // =================================== Statis ================================== //
  // Sub Total - Bayar = kembalian
  function hitung4() {
    var a = hapusFormat($(".d21").val());
    var b = hapusFormat($(".c21").val());
    c = a - b;
    $(".e21").val(c);
  }

  // Diskon
  function hitung5() {
    var a = $(".g21").val();
    var b = $(".f21").val();
    c = a - b;
    $(".c21").val(c);
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

    //Initialize Select2 Elements
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    })
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

  // Pastikan nilai yang dikirim adalah angka tanpa format saat submit
  $(document).on('submit', '#form-main', function(e) {
    $('.d2, .d21, .h22').each(function() {
      var $this = $(this);
      var formattedValue = $this.val();
      var numericValue = hapusFormat(formattedValue);
      $this.val(numericValue);
    });
  });
</script>

</body>

<script>
  $(document).ready(function() {
    $('#see-invoice').click(function() {
      window.location.href = `invoice?no=${$("[name=invoicing]").val()}`;
    })

    $('#payment-type').change(function() {
      if (this.value == 1) {
        // Transfer: Tampilkan QRIS, tombol Simpan Payment tetap aktif
        $('#qris-display').show(); // Tampilkan QRIS saat Transfer dipilih
        $('.updateStok').prop('disabled', false).show(); // Pastikan tombol Simpan Payment aktif
        $("#create-midtrans").prop('disabled', true).hide(); // Sembunyikan tombol Buat Pesanan
      } else {
        // Cash: Sembunyikan QRIS, tombol Simpan Payment tetap aktif
        $('.updateStok').prop('disabled', false).show();
        $("#create-midtrans").prop('disabled', true).hide();
        $('#qris-display').hide(); // Sembunyikan QRIS saat Cash dipilih
      }
    })
  });
</script>

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

  // Change Customer
  $(function() {
    // bind change event to select
    $('#tipe_customer').on('change', function() {
      var url = $(this).val(); // get selected value
      url = btoa(url)
      if (url) { // require a URL
        document.location.href = "beli-langsung?customer=" + url; // redirect
      }
      return false;
    });
  });
</script>