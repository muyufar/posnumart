<?php
$labels_json = isset($_POST['labels']) ? $_POST['labels'] : '[]';
$labels = json_decode($labels_json, true);

if (empty($labels) || !is_array($labels)) {
    die('Tidak ada label untuk dicetak');
}

$kolom = isset($_POST['kolom']) ? (int) $_POST['kolom'] : 3;
$baris = isset($_POST['baris']) ? (int) $_POST['baris'] : 5;
if (!in_array($kolom, array(3, 4, 6), true)) {
    $kolom = 3;
}
if ($baris < 1) {
    $baris = 1;
}
if ($baris > 40) {
    $baris = 40;
}

$perHalaman = $kolom * $baris;

// Skala tipografi mengikuti kepadatan kolom.
$fsHarga = 32;
$fsNama = 10;
$fsBarcode = 9;
$fsPriceLabel = 10;
$fsPriceValue = 13;
$cardHeight = 52;
$gap = 6;
$pad = 4;

if ($kolom === 4) {
    $fsHarga = 24;
    $fsNama = 9;
    $fsBarcode = 8;
    $fsPriceLabel = 8;
    $fsPriceValue = 11;
    $cardHeight = 42;
    $gap = 4;
    $pad = 3;
} elseif ($kolom === 6) {
    $fsHarga = 16;
    $fsNama = 7;
    $fsBarcode = 7;
    $fsPriceLabel = 7;
    $fsPriceValue = 9;
    $cardHeight = 28;
    $gap = 3;
    $pad = 2;
}

function clh_rupiah($n)
{
    return number_format((float) $n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cetak Label Harga</title>
    <style>
        @page {
            size: 210mm 330mm;
            margin: 6mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

        body {
            font-family: Arial, sans-serif;
            width: 210mm;
            min-height: 330mm;
            margin: 0 auto;
            padding: 6mm;
            background: white;
        }

        .label-container {
            display: grid;
            grid-template-columns: repeat(<?= (int) $kolom; ?>, 1fr);
            gap: <?= (int) $gap; ?>mm;
            width: 100%;
        }

        .label-card {
            border: 1.5px solid #000;
            border-bottom: none;
            padding: <?= (int) $pad; ?>mm;
            padding-bottom: <?= (int) ($pad + ($kolom >= 6 ? 2 : 3)); ?>mm;
            text-align: center;
            background: white;
            height: <?= (int) $cardHeight; ?>mm;
            display: flex;
            flex-direction: column;
            page-break-inside: avoid;
            position: relative;
            overflow: hidden;
        }

        .label-card .harga-utama {
            font-size: <?= (int) $fsHarga; ?>pt;
            font-weight: bold;
            color: #000;
            line-height: 1;
            margin-bottom: 1mm;
        }

        .label-card .harga-utama .prefix-rp {
            font-size: <?= max(8, (int) ($fsHarga * 0.35)); ?>pt;
            font-weight: normal;
            vertical-align: top;
        }

        .label-card .nama-barang {
            font-weight: bold;
            font-size: <?= (int) $fsNama; ?>pt;
            margin-bottom: 1mm;
            line-height: 1.15;
            max-height: <?= $kolom >= 6 ? 8 : 12; ?>mm;
            overflow: hidden;
            word-wrap: break-word;
            text-transform: uppercase;
        }

        .label-card .barcode-display {
            font-family: 'Courier New', monospace;
            font-size: <?= (int) $fsBarcode; ?>pt;
            margin-bottom: 1mm;
            letter-spacing: 0.3pt;
        }

        .label-card .separator {
            border-top: 1px dotted #666;
            margin: 1mm 0;
        }

        .label-card .price-row {
            display: flex;
            justify-content: space-between;
            padding: 0 1mm;
            margin-top: auto;
        }

        .label-card .price-col {
            flex: 1;
            text-align: center;
        }

        .label-card .price-label {
            font-size: <?= (int) $fsPriceLabel; ?>pt;
            font-weight: bold;
            margin-bottom: 0.5mm;
        }

        .label-card .price-value {
            font-size: <?= (int) $fsPriceValue; ?>pt;
            font-weight: bold;
        }

        .label-card .green-line {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: <?= $kolom >= 6 ? 2 : 3; ?>mm;
            background: #4CAF50 !important;
            border: none;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

        /* Cadangan bila browser mematikan background saat print */
        .label-card::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: <?= $kolom >= 6 ? 2 : 3; ?>mm;
            background: #4CAF50 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .label-card:nth-child(<?= (int) $perHalaman; ?>n) {
            page-break-after: always;
        }

        @media print {
            body {
                background: white;
                margin: 0;
                padding: 6mm;
            }

            .label-card {
                page-break-inside: avoid;
            }

            @page {
                size: 210mm 330mm;
                margin: 6mm;
            }

            .print-info {
                display: none;
            }
        }

        .print-info {
            position: fixed;
            top: 10px;
            right: 10px;
            background: #2196F3;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 14px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div class="print-info">
        <strong>Total: <?= count($labels); ?> Label</strong><br>
        <small><?= (int) $kolom; ?> kolom × <?= (int) $baris; ?> baris / halaman</small><br>
        <small>Ukuran: F4 (210mm × 330mm)</small>
    </div>

    <div class="label-container">
        <?php foreach ($labels as $label):
            $harga = isset($label['barang_harga']) ? $label['barang_harga'] : 0;
            $retail = isset($label['barang_harga_retail']) ? $label['barang_harga_retail'] : $harga;
            ?>
        <div class="label-card">
            <div class="harga-utama">
                <span class="prefix-rp">Rp.</span>
                <?= clh_rupiah($harga); ?>
            </div>
            <div class="nama-barang">
                <?= strtoupper(htmlspecialchars((string) ($label['barang_nama'] ?? ''), ENT_QUOTES, 'UTF-8')); ?>
            </div>
            <div class="barcode-display">
                <?= htmlspecialchars((string) ($label['barang_kode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="separator"></div>
            <div class="price-row">
                <div class="price-col">
                    <div class="price-label">Retail:</div>
                    <div class="price-value">Rp <?= clh_rupiah($retail); ?></div>
                </div>
            </div>
            <div class="green-line"></div>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500);
        };
        window.onafterprint = function() {
            setTimeout(function() { window.close(); }, 100);
        };
    </script>
</body>
</html>
