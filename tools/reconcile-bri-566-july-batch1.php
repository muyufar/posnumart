<?php

/**
 * Reklasifikasi Batch 1 BRI 566 Juli 2026 (32 pasangan exact).
 *
 * Dry-run: php tools/reconcile-bri-566-july-batch1.php
 * Execute: php tools/reconcile-bri-566-july-batch1.php --execute
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(2);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require __DIR__ . '/../aksi/koneksi.php';
require_once __DIR__ . '/../aksi/laba-accural-neraca-lib.php';

const BATCH_TAG = 'recon-bri566-jul26-b1-exact-v1';
const OFFSET_TAG = BATCH_TAG . ':cutover-offset';
const CUTOVER_TAG = 'cutover-bri-566-20260804-2246';
const CUTOVER_DATE = '2026-08-04 22:46:00';
const EXPECTED_TOTAL = 327641500.00;
const EXPECTED_BRI = 19293137.00;
const EXPECTED_DUKUN = 4654976865.50;
const EXPECTED_TEGALREJO = 2103133335.00;
const EXPECTED_EQUITY = 90346198.00;

// [source journal id, branch, source COA, amount, bank timestamp, ESB reference]
$pairs = [
    ['laba_6a4486e0a17148.32111571', 5, 4647, 7216000, '2026-07-01 10:01:00', '48c8dca6f3a3'],
    ['laba_6a449c18634e78.04820738', 1, 4644, 17959000, '2026-07-01 10:55:12', 'd1f7d117c869'],
    ['laba_6a45e7f87f85d3.88518765', 1, 4644, 18702000, '2026-07-02 10:56:24', '18676067e206'],
    ['laba_6a461b398c8485.40143732', 5, 4647, 7583500, '2026-07-02 10:07:33', 'ab5f0285bc59'],
    ['laba_6a472f8265d645.78807937', 5, 4647, 7180000, '2026-07-03 10:36:45', 'bb44121c2490'],
    ['laba_6a4b32a75bcdd2.04751828', 5, 4647, 8845000, '2026-07-06 11:35:21', '206df11d6c7f'],
    ['laba_6a4c79feb2cfd1.76393302', 5, 4647, 5837000, '2026-07-07 10:55:27', '2024ee16413d'],
    ['laba_6a4dd2bd16f2e4.60018620', 5, 4647, 8721500, '2026-07-08 11:27:06', 'a22a00d02de9'],
    ['laba_6a4e017c90d4f3.98689921', 1, 4644, 17392500, '2026-07-08 14:17:24', '07944113136e'],
    ['laba_6a4f11e51b30a5.91301554', 5, 4647, 7615500, '2026-07-09 10:00:45', 'd4885b136899'],
    ['laba_6a4f4a6bcc49c6.36375035', 1, 4644, 14027000, '2026-07-09 13:29:18', 'f6faa3bf659f'],
    ['laba_6a5063337c26d3.55863081', 5, 4647, 6208000, '2026-07-10 10:07:45', '26e046ba14bf'],
    ['laba_6a5464a77a2676.62443480', 5, 4647, 6926500, '2026-07-13 10:57:38', '135bbbadac58'],
    ['laba_6a548ea9ea3d12.35027081', 1, 4644, 15317000, '2026-07-13 13:17:08', '1bc129ef100a'],
    ['laba_6a55afdd1c3de9.84008987', 5, 4647, 5787500, '2026-07-14 10:31:50', '5316eaf21218'],
    ['laba_6a570d595f5584.41456310', 5, 4647, 7063500, '2026-07-15 11:17:15', '1f4aed2a450c'],
    ['laba_6a585ce01961d5.20226911', 5, 4647, 9089000, '2026-07-16 11:17:45', '14390df12744'],
    ['laba_6a58870e79e091.55407933', 1, 4644, 14519000, '2026-07-16 14:13:45', '4d9c67f834be'],
    ['laba_6a604209180329.11919305', 5, 4647, 9755500, '2026-07-22 11:00:53', '862f34a35606'],
    ['laba_6a60c8190763d5.67044960', 1, 4644, 17790000, '2026-07-22 14:13:59', '0aafad07f0f5'],
    ['laba_6a6190834491f2.01688783', 5, 4647, 5237000, '2026-07-23 10:16:52', '03f5385e2594'],
    ['laba_6a61bf24d78111.70947016', 1, 4644, 13334000, '2026-07-23 13:12:32', '4890a117824e'],
    ['laba_6a6335ca715356.81538395', 5, 4647, 4463500, '2026-07-24 08:45:19', '42aa59820171'],
    ['laba_6a66cfe2e810f1.33672707', 5, 4647, 6833000, '2026-07-27 10:20:19', '9de4b2634301'],
    ['laba_6a66d01f78a0d4.68363364', 5, 4647, 6253000, '2026-07-27 10:21:04', 'cdaa5464fe6a'],
    ['laba_6a671104946216.60465763', 1, 4644, 20336500, '2026-07-27 13:35:08', 'eb9fda9e9466'],
    ['laba_6a697b353d7956.56269093', 5, 4647, 11764000, '2026-07-29 10:50:55', '33c93aa67eb2'],
    ['laba_6a6aeaff6e27b9.15767437', 5, 4647, 4817500, '2026-07-30 13:05:53', '7d61af345b55'],
    ['laba_6a6b00003a2298.71373959', 1, 4644, 13742000, '2026-07-30 14:32:15', 'ecfd5e9f7e19'],
    ['laba_6a6c1d8e8d1d06.85841969', 5, 4647, 8163000, '2026-07-31 10:45:35', 'c76768c6df46'],
    ['laba_6a6c1de6abb668.05545014', 5, 4647, 5412500, '2026-07-31 10:46:17', '433c82557f8d'],
    ['laba_6a6c4b1b697861.62132145', 1, 4644, 13750500, '2026-07-31 13:22:45', '6c268f7ba7bc'],
];

function one(mysqli $conn, string $sql): ?array
{
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row ?: null;
}

function money($value): string
{
    return number_format((float) $value, 2, '.', '');
}

function accountSnapshot(mysqli $conn, string $date): float
{
    $data = labaAccrual_neraca_build($conn, '0', $date);
    foreach ($data['neraca']['aktiva'] as $item) {
        if ((int) ($item['id'] ?? 0) === 29) {
            return (float) $item['saldo_akhir'];
        }
    }
    throw new RuntimeException("BRI id29 tidak ditemukan pada snapshot $date.");
}

$execute = in_array('--execute', $argv ?? [], true);
$sourceIds = array_column($pairs, 0);
$statementRefs = array_column($pairs, 5);
$total = array_sum(array_column($pairs, 3));
if (count($pairs) !== 32 || count(array_unique($sourceIds)) !== 32 || count(array_unique($statementRefs)) !== 32 || money($total) !== money(EXPECTED_TOTAL)) {
    throw new RuntimeException('Static batch assertion gagal.');
}

$conn->begin_transaction();
try {
    $accounts = [];
    foreach ([29, 4644, 4647, 4657] as $accountId) {
        $accounts[$accountId] = one($conn, "SELECT id,cabang,kode_akun,kategori,tipe_akun,saldo FROM laba_kategori WHERE id=$accountId FOR UPDATE");
        if (!$accounts[$accountId]) {
            throw new RuntimeException("Akun $accountId tidak ditemukan.");
        }
    }
    $prefix = $conn->real_escape_string(BATCH_TAG . ':%');
    $duplicate = one($conn, "SELECT COUNT(*) n FROM laba WHERE tag LIKE '$prefix' FOR UPDATE");
    if ((int) ($duplicate['n'] ?? 0) !== 0) {
        throw new RuntimeException('Batch/tag sudah pernah diposting.');
    }
    if ((int) $accounts[29]['cabang'] !== 0 || $accounts[29]['kode_akun'] !== '1-1202' || money($accounts[29]['saldo']) !== money(EXPECTED_BRI)) {
        throw new RuntimeException('Assertion BRI pusat gagal.');
    }
    if ((int) $accounts[4644]['cabang'] !== 1 || money($accounts[4644]['saldo']) !== money(EXPECTED_DUKUN)) {
        throw new RuntimeException('Assertion akun referensi Dukun gagal.');
    }
    if ((int) $accounts[4647]['cabang'] !== 5 || money($accounts[4647]['saldo']) !== money(EXPECTED_TEGALREJO)) {
        throw new RuntimeException('Assertion akun referensi Tegalrejo gagal.');
    }
    if ($accounts[4657]['kode_akun'] !== '3-7001' || money($accounts[4657]['saldo']) !== money(EXPECTED_EQUITY)) {
        throw new RuntimeException('Assertion akun rekonsiliasi historis gagal.');
    }
    if (!one($conn, "SELECT id FROM laba WHERE tag='" . CUTOVER_TAG . "' FOR UPDATE")) {
        throw new RuntimeException('Jurnal cutover asal tidak ditemukan.');
    }
    $sourceTotals = [4644 => 0.0, 4647 => 0.0];
    foreach ($pairs as [$sourceId, $branch, $sourceAccount, $amount, $bankDate, $statementRef]) {
        $sourceEsc = $conn->real_escape_string($sourceId);
        $row = one($conn, "SELECT id,DATE(date) tanggal,cabang,jenis_transaksi,akun_debit,jumlah FROM laba WHERE id='$sourceEsc' FOR UPDATE");
        if (!$row || (int) $row['cabang'] !== $branch || $row['jenis_transaksi'] !== 'transfer_uang' || (int) $row['akun_debit'] !== $sourceAccount || money($row['jumlah']) !== money($amount)) {
            throw new RuntimeException("Assertion source gagal: $sourceId");
        }
        $sourceTotals[$sourceAccount] += $amount;
    }
    if (money($sourceTotals[4644]) !== '176869500.00' || money($sourceTotals[4647]) !== '150772000.00') {
        throw new RuntimeException('Subtotal cabang batch tidak sesuai.');
    }

    $snapshotJulyBefore = accountSnapshot($conn, '2026-07-31');
    $snapshotCutoverBefore = accountSnapshot($conn, '2026-08-04');
    if (money($snapshotJulyBefore) !== '-70960216.00' || money($snapshotCutoverBefore) !== money(EXPECTED_BRI)) {
        throw new RuntimeException('Assertion snapshot sebelum posting gagal.');
    }

    if (!$execute) {
        $conn->rollback();
        echo json_encode([
            'ok' => true,
            'mode' => 'dry-run',
            'pairs' => count($pairs),
            'total' => $total,
            'source_totals' => $sourceTotals,
            'snapshot_2026_07_31' => $snapshotJulyBefore,
            'snapshot_2026_08_04' => $snapshotCutoverBefore,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    $insert = $conn->prepare("INSERT INTO laba (id,tipe,jenis_transaksi,kategori,akun_debit,akun_kredit,nominal,bunga,pajak,total,jumlah,keterangan,tag,cabang,date,name,created_at) VALUES (?,0,'penyesuaian','29',29,?,?,0,0,?,?,?,?,?,?,'Reklasifikasi BRI 566 Batch 1',NOW())");
    foreach ($pairs as [$sourceId, $branch, $sourceAccount, $amount, $bankDate, $statementRef]) {
        $journalId = uniqid('recon_bri_', true);
        $tag = BATCH_TAG . ':src:' . $sourceId . ':esb:' . $statementRef;
        $description = "[BRI566 JUL26 B1 EXACT] Reclass akun referensi toko ke BRI pusat; source=$sourceId; esb=$statementRef";
        $numericAmount = (float) $amount;
        $insert->bind_param('sidddssis', $journalId, $sourceAccount, $numericAmount, $numericAmount, $numericAmount, $description, $tag, $branch, $bankDate);
        $insert->execute();
        if ($insert->affected_rows !== 1) {
            throw new RuntimeException("Insert reclass gagal: $sourceId");
        }
    }

    foreach ($sourceTotals as $sourceAccount => $sourceTotal) {
        $stmt = $conn->prepare('UPDATE laba_kategori SET saldo=saldo-? WHERE id=?');
        $stmt->bind_param('di', $sourceTotal, $sourceAccount);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException("Update akun sumber gagal: $sourceAccount");
        }
    }
    $stmt = $conn->prepare('UPDATE laba_kategori SET saldo=saldo+? WHERE id=29');
    $stmt->bind_param('d', $total);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException('Update debit BRI batch gagal.');
    }

    $offsetId = uniqid('recon_offset_', true);
    $offsetDescription = '[BRI566 JUL26 B1 OFFSET] Kurangi residu rekonsiliasi historis setelah 32 setoran exact direklasifikasi; menjaga saldo fisik cutover Rp19.293.137';
    $offsetTag = OFFSET_TAG;
    $offsetDate = CUTOVER_DATE;
    $offsetName = 'Offset cutover BRI 566 Batch 1';
    $offset = $conn->prepare("INSERT INTO laba (id,tipe,jenis_transaksi,kategori,akun_debit,akun_kredit,nominal,bunga,pajak,total,jumlah,keterangan,tag,cabang,date,name,created_at) VALUES (?,1,'penyesuaian','4657',4657,29,?,0,0,?,?,?,?,0,?,?,NOW())");
    $offset->bind_param('sdddssss', $offsetId, $total, $total, $total, $offsetDescription, $offsetTag, $offsetDate, $offsetName);
    $offset->execute();
    if ($offset->affected_rows !== 1) {
        throw new RuntimeException('Insert offset cutover gagal.');
    }
    $stmt = $conn->prepare('UPDATE laba_kategori SET saldo=saldo-? WHERE id IN (29,4657)');
    $stmt->bind_param('d', $total);
    $stmt->execute();
    if ($stmt->affected_rows !== 2) {
        throw new RuntimeException('Update offset BRI/ekuitas gagal.');
    }

    $expected = [
        29 => EXPECTED_BRI,
        4644 => EXPECTED_DUKUN - $sourceTotals[4644],
        4647 => EXPECTED_TEGALREJO - $sourceTotals[4647],
        4657 => EXPECTED_EQUITY - $total,
    ];
    foreach ($expected as $accountId => $expectedBalance) {
        $row = one($conn, "SELECT saldo FROM laba_kategori WHERE id=$accountId FOR UPDATE");
        if (!$row || money($row['saldo']) !== money($expectedBalance)) {
            throw new RuntimeException("Verifikasi saldo akhir akun $accountId gagal.");
        }
    }
    $journalCount = one($conn, "SELECT COUNT(*) n,COUNT(DISTINCT tag) tags,SUM(CASE WHEN akun_debit=29 THEN total ELSE 0 END) reclass_debit,SUM(CASE WHEN akun_kredit=29 THEN total ELSE 0 END) offset_credit,SUM(CASE WHEN akun_debit=4657 THEN total ELSE 0 END) equity_debit FROM laba WHERE tag LIKE '$prefix' FOR UPDATE");
    if ((int) $journalCount['n'] !== 33 || (int) $journalCount['tags'] !== 33 || money($journalCount['reclass_debit']) !== money($total) || money($journalCount['offset_credit']) !== money($total) || money($journalCount['equity_debit']) !== money($total)) {
        throw new RuntimeException('Verifikasi jumlah/tag/debit-kredit batch gagal.');
    }

    $snapshotJulyAfter = accountSnapshot($conn, '2026-07-31');
    $snapshotCutoverAfter = accountSnapshot($conn, '2026-08-04');
    if (money($snapshotJulyAfter) !== money($snapshotJulyBefore + $total) || money($snapshotCutoverAfter) !== money(EXPECTED_BRI)) {
        throw new RuntimeException('Verifikasi snapshot sesudah posting gagal.');
    }

    $conn->commit();
    echo json_encode([
        'ok' => true,
        'mode' => 'executed',
        'batch_tag' => BATCH_TAG,
        'pairs' => count($pairs),
        'journals' => 33,
        'total' => $total,
        'source_totals' => $sourceTotals,
        'balances' => $expected,
        'snapshot_2026_07_31_before' => $snapshotJulyBefore,
        'snapshot_2026_07_31_after' => $snapshotJulyAfter,
        'snapshot_2026_08_04' => $snapshotCutoverAfter,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, 'BATCH 1 ROLLED BACK: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
