<?php
// Get labels data from POST
$labels_json = isset($_POST['labels']) ? $_POST['labels'] : '[]';
$labels = json_decode($labels_json, true);

if (empty($labels)) {
    die('Tidak ada label untuk dicetak');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cetak Label Harga</title>
    <style>
        @page {
            size: 210mm 330mm; /* F4 size */
            margin: 8mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            width: 210mm;
            min-height: 330mm;
            margin: 0 auto;
            padding: 8mm;
            background: white;
        }
        
        .label-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6mm;
            width: 100%;
        }
        
        .label-card {
            border: 1.5px solid #000;
            padding: 4mm;
            text-align: center;
            background: white;
            height: 52mm;
            display: flex;
            flex-direction: column;
            page-break-inside: avoid;
            position: relative;
        }
        
        /* Harga besar di atas */
        .label-card .harga-utama {
            font-size: 32pt;
            font-weight: bold;
            color: #000;
            line-height: 1;
            margin-bottom: 2mm;
        }
        
        .label-card .harga-utama .prefix-rp {
            font-size: 12pt;
            font-weight: normal;
            vertical-align: top;
        }
        
        /* Nama produk */
        .label-card .nama-barang {
            font-weight: bold;
            font-size: 10pt;
            margin-bottom: 2mm;
            line-height: 1.2;
            max-height: 15mm;
            overflow: hidden;
            word-wrap: break-word;
            text-transform: uppercase;
        }
        
        /* Barcode number */
        .label-card .barcode-display {
            font-family: 'Courier New', monospace;
            font-size: 9pt;
            margin-bottom: 2mm;
            letter-spacing: 0.5pt;
        }
        
        /* Garis putus-putus */
        .label-card .separator {
            border-top: 1px dotted #666;
            margin: 2mm 0;
        }
        
        /* Container untuk retail dan grosir */
        .label-card .price-row {
            display: flex;
            justify-content: space-between;
            padding: 0 2mm;
            margin-top: auto;
        }
        
        .label-card .price-col {
            flex: 1;
            text-align: left;
        }
        
        .label-card .price-col:last-child {
            text-align: right;
        }
        
        .label-card .price-label {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 1mm;
        }
        
        .label-card .price-value {
            font-size: 13pt;
            font-weight: bold;
        }
        
        /* Garis hijau tebal di bawah */
        .label-card .green-line {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3mm;
            background: #4CAF50;
        }
        
        /* Page break after every 15 labels (5 rows x 3 columns) */
        .label-card:nth-child(15n) {
            page-break-after: always;
        }
        
        @media print {
            body {
                background: white;
                margin: 0;
                padding: 8mm;
            }
            
            .label-card {
                page-break-inside: avoid;
            }
            
            @page {
                size: 210mm 330mm;
                margin: 8mm;
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
        
        @media print {
            .print-info {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="print-info">
        <strong>Total: <?= count($labels); ?> Label</strong><br>
        <small>Ukuran: F4 (210mm x 330mm)</small>
    </div>
    
    <div class="label-container">
        <?php foreach ($labels as $label): ?>
        <div class="label-card">
            <!-- Harga Utama Besar -->
            <div class="harga-utama">
                <span class="prefix-rp">Rp.</span>
                <?= number_format($label['barang_harga'], 0, ',', '.'); ?>
            </div>
            
            <!-- Nama Produk -->
            <div class="nama-barang">
                <?= strtoupper(htmlspecialchars($label['barang_nama'])); ?>
            </div>
            
            <!-- Barcode -->
            <div class="barcode-display">
                <?= htmlspecialchars($label['barang_kode']); ?>
            </div>
            
            <!-- Separator -->
            <div class="separator"></div>
            
            <!-- Retail dan Grosir -->
            <div class="price-row">
                <div class="price-col">
                    <div class="price-label">Retail:</div>
                    <div class="price-value">Rp <?= number_format(isset($label['barang_harga_retail']) ? $label['barang_harga_retail'] : $label['barang_harga'], 0, ',', '.'); ?></div>
                </div>
                <div class="price-col">
                    <div class="price-label">Grosir:</div>
                    <div class="price-value">Rp <?= number_format(isset($label['barang_harga_grosir']) ? $label['barang_harga_grosir'] : $label['barang_harga'], 0, ',', '.'); ?></div>
                </div>
            </div>
            
            <!-- Green Line -->
            <div class="green-line"></div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <script>
        // Auto print on load
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
        
        // Close window after print or cancel
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 100);
        };
    </script>
</body>
</html>

