<?php 
if (defined('NUMART_ROOT')) {
    include_once NUMART_ROOT . '/aksi/koneksi.php';
    include_once NUMART_ROOT . '/aksi/functions.php';
} else {
    include 'aksi/koneksi.php';
    include 'aksi/functions.php';
}

header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cabang = isset($_GET['cabang']) && (int) $_GET['cabang'] > 0 
    ? (int) $_GET['cabang'] 
    : (isset($_SESSION['sessionCabang']) ? (int) $_SESSION['sessionCabang'] : (isset($_SESSION['user_cabang']) ? (int) $_SESSION['user_cabang'] : 0));

$day = date("Y-m") . "-01";

// Ambil info toko untuk WA message
$tokoNama = '';
$tokoKota = '';
if (isset($conn) && $conn instanceof mysqli) {
    $tokoRes = mysqli_query($conn, "SELECT toko_nama, toko_kota FROM toko WHERE toko_cabang = $cabang LIMIT 1");
    if ($tokoRes && $tokoRow = mysqli_fetch_assoc($tokoRes)) {
        $tokoNama = trim((string) ($tokoRow['toko_nama'] ?? ''));
        $tokoKota = trim((string) ($tokoRow['toko_kota'] ?? ''));
    }
}

$dbDetails = array( 
    'host' => isset($servername) ? $servername : 'localhost', 
    'user' => isset($username) ? $username : 'root', 
    'pass' => isset($password) ? $password : '', 
    'db'   => isset($db) ? $db : ''
); 

$table = <<<EOT
 (
    SELECT 
        p.piutang_id,
        p.piutang_invoice,
        p.piutang_date,
        p.piutang_date_time,
        p.piutang_cabang,
        i.invoice_id,
        COALESCE(i.invoice_date, '') AS invoice_date,
        COALESCE(i.invoice_sub_total, 0) AS invoice_sub_total,
        COALESCE(i.invoice_piutang_dp, 0) AS invoice_piutang_dp,
        COALESCE(i.invoice_bayar, 0) AS invoice_bayar,
        COALESCE(i.invoice_kembali, 0) AS invoice_kembali,
        COALESCE(i.invoice_piutang_jatuh_tempo, '') AS invoice_piutang_jatuh_tempo,
        COALESCE(c.customer_nama, 'Umum') AS customer_nama,
        COALESCE(c.customer_tlpn, '') AS customer_tlpn
    FROM (
        SELECT piutang_invoice, MAX(piutang_id) AS max_piutang_id
        FROM piutang
        WHERE piutang_cabang = $cabang
        GROUP BY piutang_invoice
    ) p_sub
    JOIN piutang p ON p.piutang_id = p_sub.max_piutang_id
    JOIN invoice i ON p.piutang_invoice = i.penjualan_invoice AND i.invoice_cabang = $cabang
    LEFT JOIN customer c ON i.invoice_customer = c.customer_id AND c.customer_cabang = $cabang
    WHERE p.piutang_date < '$day'
      AND p.piutang_date IS NOT NULL
      AND p.piutang_date != ''
      AND p.piutang_date != '0000-00-00'
 ) temp
EOT;

$primaryKey = 'piutang_id';

$columns = array( 
    array( 'db' => 'invoice_id',                  'dt' => 0 ),
    array( 'db' => 'piutang_invoice',             'dt' => 1 ), 
    array( 'db' => 'customer_nama',               'dt' => 2 ),
    array( 'db' => 'invoice_date',                'dt' => 3 ), 
    array( 'db' => 'piutang_date_time',           'dt' => 4 ),
    array( 'db' => 'piutang_date',                'dt' => 5 ),
    array( 'db' => 'invoice_piutang_jatuh_tempo', 'dt' => 6 ),
    array( 'db' => 'piutang_id',                  'dt' => 7 ),
    array( 'db' => 'invoice_sub_total',           'dt' => 8 ),
    array( 'db' => 'invoice_piutang_dp',          'dt' => 9 ),
    array( 'db' => 'invoice_bayar',               'dt' => 10 ),
    array( 'db' => 'invoice_kembali',             'dt' => 11 ),
    array( 'db' => 'customer_tlpn',               'dt' => 12 )
); 

if (defined('NUMART_ROOT') && file_exists(NUMART_ROOT . '/aksi/ssp.php')) {
    require_once NUMART_ROOT . '/aksi/ssp.php';
} else {
    include 'aksi/ssp.php';
}

$result = SSP::simple( $_GET, $dbDetails, $table, $primaryKey, $columns );

// Format data rows untuk DataTables UI
$formattedData = array();
if (!empty($result['data'])) {
    foreach ($result['data'] as $row) {
        $invoiceId        = $row[0];
        $piutangInvoice   = $row[1];
        $customerNama     = $row[2];
        $invoiceDate      = $row[3];
        $piutangDateTime  = $row[4];
        $piutangDate      = $row[5];
        $jatuhTempo       = $row[6];
        $piutangId        = $row[7];
        $subTotal         = (float) $row[8];
        $dp               = (float) $row[9];
        $bayar            = (float) $row[10];
        $kembali          = (float) $row[11];
        $customerTlpn     = $row[12];

        // Format Menunggak
        $dateNunggak = '-';
        if (!empty($piutangDate) && $piutangDate !== '0000-00-00') {
            try {
                $tanggal = new DateTime($piutangDate);
                $today   = new DateTime('today');
                $tahun   = $today->diff($tanggal)->y;
                $bulan   = $today->diff($tanggal)->m;
                $hari    = $today->diff($tanggal)->d;

                if ($tahun < 1 && $bulan > 0 && $hari > 0) {
                    $dateNunggak = $bulan . " bulan, " . $hari . " hari ";
                } elseif ($tahun < 1 && $bulan < 1 && $hari > 0) {
                    $dateNunggak = $hari . " hari ";
                } elseif ($tahun < 1 && $bulan > 0 && $hari < 1) {
                    $dateNunggak = $bulan . " bulan ";
                } elseif ($tahun > 0 && $bulan < 1 && $hari > 0) {
                    $dateNunggak = $tahun . " tahun, " . $hari . " hari ";
                } elseif ($tahun > 0 && $bulan < 1 && $hari < 1) {
                    $dateNunggak = $tahun . " tahun ";
                } else {
                    $dateNunggak = $tahun . " tahun, " . $bulan . " bulan, " . $hari . " hari ";
                }
            } catch (Exception $e) {
                $dateNunggak = '-';
            }
        }

        // Action buttons
        $noWa = !empty($customerTlpn) ? substr_replace($customerTlpn, '62', 0, 1) : '';
        $encodedId = base64_encode($invoiceId);

        $waText = "Halo " . $customerNama . ", Kami dari *" . $tokoNama . " " . $tokoKota . "* memberikan informasi bahwa transaksi *No Invoice " . $piutangInvoice . " dengan jumlah transaksi Rp " . number_format($subTotal, 0, ',', '.') . "* Sudah Menunggak Pembayaran Piutang Selama " . $dateNunggak . "dari terakhir melakukan cicilan pada " . $piutangDateTime . ".%0A%0ASub Total: Rp " . number_format($subTotal, 0, ',', '.') . "%2C%0ADP: Rp " . number_format($dp, 0, ',', '.') . "%2C%0ADP ditambah Total Cicilan: Rp " . number_format($bayar, 0, ',', '.') . " %2C%0A*Sisa Piutang: Rp " . number_format($kembali, 0, ',', '.') . "*%2C%0A%0A%0AMohon Segera Dilunasi";

        $btnCicilan = "<a href='piutang-cicilan?no=" . $encodedId . "'><button class='btn btn-primary' title='Cicilan'><i class='fa fa-money'></i></button></a>";
        $btnWa      = "<a href='https://api.whatsapp.com/send?phone=" . $noWa . "&text=" . urlencode($waText) . "' target='_blank'><button class='btn btn-success' title='Cicilan'><i class='fa fa-whatsapp'></i></button></a>";
        $btnCetak   = "<a href='nota-cetak-piutang?no=" . $invoiceId . "' target='_blank'><button class='btn btn-warning' title='Cetak Nota'><i class='fa fa-print'></i></button></a>";

        $aksiHtml = "<div class='orderan-online-button'>" . $btnCicilan . " " . $btnWa . " " . $btnCetak . "</div>";

        $invoiceDateIndo = (!empty($invoiceDate) && $invoiceDate !== '0000-00-00') ? (function_exists('tanggal_indo') ? tanggal_indo($invoiceDate) : $invoiceDate) : '-';
        $jatuhTempoIndo  = (!empty($jatuhTempo) && $jatuhTempo !== '0000-00-00') ? (function_exists('tanggal_indo') ? tanggal_indo($jatuhTempo) : $jatuhTempo) : '-';

        $formattedData[] = array(
            "",
            htmlspecialchars($piutangInvoice),
            htmlspecialchars($customerNama),
            $invoiceDateIndo,
            htmlspecialchars($piutangDateTime),
            $dateNunggak,
            $jatuhTempoIndo,
            $aksiHtml
        );
    }
}

$result['data'] = $formattedData;
echo json_encode($result);
