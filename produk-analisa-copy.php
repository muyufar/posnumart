<?php
// Generate copy iklan berbasis data penjualan + stok + margin
include 'aksi/koneksi.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

// Auth: hanya admin/super admin (atau minimal bukan kasir/kurir)
$levelLogin = $_SESSION['user_level'] ?? '';
if ($levelLogin === 'kasir' || $levelLogin === 'kurir' || $levelLogin === '') {
  http_response_code(403);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$cabang = isset($_GET['cabang']) ? intval($_GET['cabang']) : intval($_SESSION['user_cabang'] ?? 0);
$barangId = isset($_GET['barang_id']) ? intval($_GET['barang_id']) : 0;
$goal = isset($_GET['goal']) ? $_GET['goal'] : 'balanced';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$platform = isset($_GET['platform']) ? $_GET['platform'] : 'wa'; // wa | ig_feed | ig_story | fb | marketplace
$tone = isset($_GET['tone']) ? $_GET['tone'] : 'santai'; // santai | formal
$promo = isset($_GET['promo']) ? trim($_GET['promo']) : '';

if ($barangId < 1) {
  http_response_code(400);
  echo json_encode(['error' => 'barang_id required']);
  exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) $endDate = date('Y-m-d');

$daysRange = max(1, (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1);

// Ambil metrik dari penjualan untuk 1 barang
$q = "
  SELECT
    b.barang_id,
    b.barang_kode,
    b.barang_nama,
    b.barang_stock,
    b.kode_suplier,
    COALESCE(k.kategori_nama, '-') AS kategori_nama,
    COALESCE(SUM(p.barang_qty_keranjang), 0) AS qty_pcs,
    COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) AS omzet,
    COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0) AS hpp,
    (COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) - COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0)) AS laba,
    CASE
      WHEN COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) > 0
        THEN ((COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0) - COALESCE(SUM(p.barang_qty_keranjang * p.keranjang_harga_beli), 0)) / COALESCE(SUM(p.barang_qty * p.keranjang_harga), 0)) * 100
      ELSE 0
    END AS margin_persen,
    MAX(p.keranjang_harga) AS harga_jual_akhir
  FROM barang b
  LEFT JOIN kategori k ON b.kategori_id = k.kategori_id
  LEFT JOIN penjualan p ON p.barang_id = b.barang_id
    AND p.penjualan_cabang = $cabang
    AND p.penjualan_date BETWEEN '$startDate' AND '$endDate'
  WHERE b.barang_id = $barangId
  LIMIT 1
";

$res = mysqli_query($conn, $q);
if (!$res) {
  http_response_code(500);
  echo json_encode(['error' => 'Query failed', 'detail' => mysqli_error($conn)]);
  exit;
}
$row = mysqli_fetch_assoc($res);
if (!$row) {
  http_response_code(404);
  echo json_encode(['error' => 'Produk tidak ditemukan']);
  exit;
}

$nama = $row['barang_nama'] ?? '';
$kategori = $row['kategori_nama'] ?? '-';
$stock = (float)($row['barang_stock'] ?? 0);
$qty = (float)($row['qty_pcs'] ?? 0);
$omzet = (float)($row['omzet'] ?? 0);
$laba = (float)($row['laba'] ?? 0);
$margin = (float)($row['margin_persen'] ?? 0);
$hargaJual = (float)($row['harga_jual_akhir'] ?? 0);
$velocity = $qty / $daysRange;
$dos = ($velocity > 0) ? ($stock / $velocity) : null;

function fmt_rp($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function fmt_num($n) { return number_format((float)$n, 0, ',', '.'); }
function safe($s) { return trim((string)$s); }

// Heuristik label untuk tone promo
$labelFast = ($velocity >= 2) ? 'Laris' : (($velocity >= 0.5) ? 'Cukup laku' : 'Niche');
$labelStock = ($stock >= 50) ? 'Stok banyak' : (($stock >= 10) ? 'Stok aman' : 'Stok terbatas');
$labelMargin = ($margin >= 20) ? 'Margin tinggi' : (($margin >= 10) ? 'Margin oke' : 'Margin tipis');

// Saran promo sederhana (tidak mengubah harga, hanya ide copy)
$offerIdea = 'Bonus / bundling lebih menarik';
if ($goal === 'stok' || ($dos !== null && $dos > 60) || $stock >= 80) {
  $offerIdea = 'Promo clearance: diskon terbatas / tebus murah / paket hemat';
} else if ($goal === 'omzet' || $velocity >= 2) {
  $offerIdea = 'Best seller: promo “beli 2 lebih hemat” / bundling';
} else if ($goal === 'margin' || $margin >= 20) {
  $offerIdea = 'Upsell: bundling premium / bonus kecil untuk percepat closing';
}

// Hashtag set
$hashtagsBase = [
  '#PromoNumart',
  '#BelanjaHemat',
  '#HargaMurah',
  '#ProdukPilihan',
  '#Magelang',
];

$hashtagsCat = [];
if ($kategori && $kategori !== '-') {
  $tag = preg_replace('/[^A-Za-z0-9]/', '', ucwords(str_replace(' ', '', $kategori)));
  if ($tag) $hashtagsCat[] = '#' . $tag;
}

$hashtags = implode(" ", array_merge($hashtagsBase, $hashtagsCat));

// Copy templates
$insightLine = "Insight: {$labelFast} • {$labelStock} • {$labelMargin}";
if ($dos !== null) {
  $insightLine .= " • DOS " . number_format($dos, 0, ',', '.') . " hari";
}

$priceLine = $hargaJual > 0 ? ("Harga mulai " . fmt_rp($hargaJual) . ".") : "";
$promoLine = $promo !== '' ? ("Promo: " . safe($promo) . ".") : "";

// Platform rules (length + formatting)
$maxBodyChars = 900;
$useEmojis = true;
if ($platform === 'ig_story') {
  $maxBodyChars = 320;
} elseif ($platform === 'ig_feed') {
  $maxBodyChars = 1000;
} elseif ($platform === 'fb') {
  $maxBodyChars = 1200;
} elseif ($platform === 'marketplace') {
  $maxBodyChars = 700;
}

// Tone rules
if ($tone === 'formal') {
  $useEmojis = false;
}

function trim_len($text, $max) {
  $text = trim($text);
  if (mb_strlen($text) <= $max) return $text;
  return rtrim(mb_substr($text, 0, $max - 3)) . '...';
}

$eBest = $useEmojis ? "🔥" : "";
$eStar = $useEmojis ? "⭐" : "";
$eTarget = $useEmojis ? "🎯" : "";
$eCart = $useEmojis ? "🛒" : "";
$eChat = $useEmojis ? "📩" : "";
$eCheck = $useEmojis ? "✅" : "";

// Auto urgency line
$urgencyLine = '';
if ($stock <= 5 && $stock > 0) {
  $urgencyLine = $useEmojis ? "⚠️ Stok menipis! " : "Stok menipis! ";
} elseif ($dos !== null && $dos > 60) {
  $urgencyLine = $useEmojis ? "📦 Stok banyak—cocok promo hemat! " : "Stok banyak—cocok promo hemat! ";
} elseif ($velocity >= 2 && $stock <= 10) {
  $urgencyLine = $useEmojis ? "⏳ Cepat habis—amankan sekarang! " : "Cepat habis—amankan sekarang! ";
}

// 3 Variasi
$variants = [];

// Marketplace template (judul pendek + bullet)
if ($platform === 'marketplace') {
  $titleBase = $promo !== '' ? "{$nama} | {$promo}" : "{$nama}";
  $title = trim_len($titleBase, 70);
  $bullets = [];
  if ($promoLine) $bullets[] = "• " . rtrim($promoLine, '.');
  if ($hargaJual > 0) $bullets[] = "• Harga: " . fmt_rp($hargaJual);
  $bullets[] = "• Kategori: {$kategori}";
  $bullets[] = "• Stok: " . fmt_num($stock) . " pcs";
  if ($velocity > 0) $bullets[] = "• Terjual: " . fmt_num($qty) . " pcs (" . number_format($velocity, 2, ',', '.') . "/hari)";
  if ($urgencyLine) $bullets[] = "• " . trim($urgencyLine);

  $body = implode("\n", $bullets) . "\n\n" .
          "Catatan:\n" .
          "• Barang dikemas aman\n" .
          "• Bisa tanya stok/promo via chat\n";

  echo json_encode([
    'meta' => [
      'barang_id' => $barangId,
      'barang_nama' => $nama,
      'kategori' => $kategori,
      'cabang' => $cabang,
      'platform' => $platform,
      'tone' => $tone,
      'periode' => ['start' => $startDate, 'end' => $endDate],
    ],
    'variants' => [[
      'headline' => safe($title),
      'body' => trim_len(safe($body), $maxBodyChars),
      'cta' => $tone === 'formal' ? "Silakan chat untuk pemesanan." : "{$eChat} Chat dulu untuk order ya!",
      'hashtags' => $hashtags
    ]]
  ]);
  exit;
}

// Var 1: Profesional & informatif
$variants[] = [
  'headline' => safe(trim("{$eBest} {$nama} — {$offerIdea}")),
  'body' => trim_len(safe(
    ($promoLine ? ($promoLine . "\n") : "") .
    ($priceLine ? ($priceLine . "\n") : "") .
    "Kategori: {$kategori}\n" .
    "{$insightLine}\n\n" .
    ($urgencyLine ? ($urgencyLine . "\n") : "") .
    "Periode {$startDate} s/d {$endDate}. " .
    "Tersedia di cabang {$cabang}. Stok: " . fmt_num($stock) . " pcs."
  ), $maxBodyChars),
  'cta' => $tone === 'formal'
    ? "Silakan hubungi kami untuk pemesanan dan informasi ketersediaan."
    : "{$eCheck} Chat/kunjungi toko sekarang. Siapa cepat dia dapat!",
  'hashtags' => $hashtags,
];

// Var 2: Best seller / social proof
$social = ($qty > 0) ? ("Sudah terjual " . fmt_num($qty) . " pcs di periode ini.") : "Produk pilihan untuk periode ini.";
$variants[] = [
  'headline' => safe(trim("{$eStar} Best Deal: {$nama}")),
  'body' => trim_len(safe(
    ($promoLine ? ($promoLine . "\n") : "") .
    "{$social}\n" .
    ($velocity > 0 ? ("Rata-rata laku " . number_format($velocity, 2, ',', '.') . " pcs/hari.\n") : "") .
    ($priceLine ? ($priceLine . "\n") : "") .
    "Stok sekarang: " . fmt_num($stock) . " pcs.\n\n" .
    ($urgencyLine ? ($urgencyLine . "\n") : "") .
    ($tone === 'formal' ? "Silakan lakukan pemesanan sebelum stok habis." : "Yuk amankan sebelum habis!")
  ), $maxBodyChars),
  'cta' => $tone === 'formal'
    ? "Pemesanan dapat dilakukan melalui chat. Terima kasih."
    : "{$eCart} Order sekarang — bisa pickup/kunjungi toko.",
  'hashtags' => $hashtags,
];

// Var 3: Focus margin / bundling
$marginText = $margin > 0 ? ("Margin " . number_format($margin, 1, ',', '.') . "%") : "Harga bersaing";
$variants[] = [
  'headline' => safe(trim("{$eTarget} Promo Spesial {$nama}")),
  'body' => trim_len(safe(
    ($promoLine ? ($promoLine . "\n") : "") .
    "{$marginText} • {$kategori}\n" .
    ($priceLine ? ($priceLine . "\n") : "") .
    "Rekomendasi promo: {$offerIdea}.\n\n" .
    "Stok: " . fmt_num($stock) . " pcs. " .
    ($dos !== null ? ("Perkiraan stok bertahan: " . number_format($dos, 0, ',', '.') . " hari.") : "")
    . ($urgencyLine ? ("\n" . $urgencyLine) : "")
  ), $maxBodyChars),
  'cta' => $tone === 'formal'
    ? "Silakan DM/WA untuk informasi promo dan pemesanan."
    : "{$eChat} DM/WA untuk info promo & ketersediaan.",
  'hashtags' => $hashtags,
];

echo json_encode([
  'meta' => [
    'barang_id' => $barangId,
    'barang_nama' => $nama,
    'kategori' => $kategori,
    'cabang' => $cabang,
    'periode' => ['start' => $startDate, 'end' => $endDate],
    'metrics' => [
      'stok' => $stock,
      'qty_pcs' => $qty,
      'velocity' => $velocity,
      'dos' => $dos,
      'omzet' => $omzet,
      'laba' => $laba,
      'margin_persen' => $margin,
    ]
  ],
  'variants' => $variants
]);

