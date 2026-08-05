<?php

/**
 * One-off, idempotent cutover BRI Transaksi 566.
 * Run from CLI only with: php tools/cutover-bri-566.php --execute
 */

if (PHP_SAPI !== 'cli' || !in_array('--execute', $argv ?? [], true)) {
    fwrite(STDERR, "Refusing to run. Use CLI with --execute after backup and review.\n");
    exit(2);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require __DIR__ . '/../aksi/koneksi.php';

const CUTOVER_TAG = 'cutover-bri-566-20260804-2246';
const CUTOVER_DATE = '2026-08-04 22:46:00';
const EXPECTED_OLD_BRI = '-71053061.00';
const VERIFIED_BRI = '19293137.00';
const ADJUSTMENT = '90346198.00';

function cutover_fetch_one(mysqli $conn, string $sql): ?array
{
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row ?: null;
}

$conn->begin_transaction();
try {
    $existing = cutover_fetch_one(
        $conn,
        "SELECT id FROM laba WHERE tag = '" . CUTOVER_TAG . "' LIMIT 1 FOR UPDATE"
    );
    if ($existing) {
        throw new RuntimeException('Tag cutover sudah ada; tidak ada perubahan dilakukan.');
    }

    $bri = cutover_fetch_one(
        $conn,
        "SELECT id, saldo FROM laba_kategori WHERE id = 29 AND cabang = 0 AND kode_akun = '1-1202' FOR UPDATE"
    );
    if (!$bri || number_format((float) $bri['saldo'], 2, '.', '') !== EXPECTED_OLD_BRI) {
        throw new RuntimeException('Assertion saldo awal BRI gagal; expected ' . EXPECTED_OLD_BRI . '.');
    }

    $header = cutover_fetch_one(
        $conn,
        "SELECT id, kategori, tipe_akun, level, parent_id FROM laba_kategori WHERE cabang = 0 AND kode_akun = '3-7000' LIMIT 1 FOR UPDATE"
    );
    if (!$header) {
        $stmt = $conn->prepare("INSERT INTO laba_kategori (parent_id, level, name, kode_akun, kategori, tipe_akun, saldo, cabang) VALUES (90, 3, 'PENYESUAIAN SALDO AWAL', '3-7000', 'modal', 'kredit', 0, 0)");
        $stmt->execute();
        $headerId = (int) $conn->insert_id;
    } else {
        if ($header['kategori'] !== 'modal' || $header['tipe_akun'] !== 'kredit' || (int) $header['level'] !== 3 || (int) $header['parent_id'] !== 90) {
            throw new RuntimeException('Akun 3-7000 sudah ada dengan struktur yang tidak sesuai.');
        }
        $headerId = (int) $header['id'];
    }

    $leaf = cutover_fetch_one(
        $conn,
        "SELECT id, kategori, tipe_akun, level, parent_id, saldo FROM laba_kategori WHERE cabang = 0 AND kode_akun = '3-7001' LIMIT 1 FOR UPDATE"
    );
    if (!$leaf) {
        $stmt = $conn->prepare("INSERT INTO laba_kategori (parent_id, level, name, kode_akun, kategori, tipe_akun, saldo, cabang) VALUES (?, 4, 'Rekonsiliasi Historis BRI 566', '3-7001', 'modal', 'kredit', 0, 0)");
        $stmt->bind_param('i', $headerId);
        $stmt->execute();
        $leafId = (int) $conn->insert_id;
        $oldEquity = 0.0;
    } else {
        if ($leaf['kategori'] !== 'modal' || $leaf['tipe_akun'] !== 'kredit' || (int) $leaf['level'] !== 4 || (int) $leaf['parent_id'] !== $headerId) {
            throw new RuntimeException('Akun 3-7001 sudah ada dengan struktur yang tidak sesuai.');
        }
        $leafId = (int) $leaf['id'];
        $oldEquity = (float) $leaf['saldo'];
    }

    $journalId = uniqid('cutover_', true);
    $description = '[CUTOVER BRI 566 2026-08-04 22:46] Penyesuaian saldo historis ke rekening fisik terverifikasi Rp19.293.137; sementara belum direkonsiliasi per sumber';
    $name = 'Cutover terverifikasi';
    $tag = CUTOVER_TAG;
    $date = CUTOVER_DATE;
    $stmt = $conn->prepare("INSERT INTO laba (id, tipe, jenis_transaksi, kategori, akun_debit, akun_kredit, nominal, bunga, pajak, total, jumlah, keterangan, tag, cabang, date, name, created_at) VALUES (?, 0, 'penyesuaian', '29', 29, ?, ?, 0, 0, ?, ?, ?, ?, 0, ?, ?, NOW())");
    $amount = (float) ADJUSTMENT;
    $stmt->bind_param('sidddssss', $journalId, $leafId, $amount, $amount, $amount, $description, $tag, $date, $name);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException('Insert jurnal cutover tidak menghasilkan tepat satu baris.');
    }

    $stmt = $conn->prepare('UPDATE laba_kategori SET saldo = saldo + ? WHERE id = 29');
    $stmt->bind_param('d', $amount);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException('Update saldo BRI tidak menghasilkan tepat satu baris.');
    }
    $stmt = $conn->prepare('UPDATE laba_kategori SET saldo = saldo + ? WHERE id = ?');
    $stmt->bind_param('di', $amount, $leafId);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException('Update saldo ekuitas tidak menghasilkan tepat satu baris.');
    }

    $verifyBri = cutover_fetch_one($conn, 'SELECT saldo FROM laba_kategori WHERE id = 29 FOR UPDATE');
    $verifyEquity = cutover_fetch_one($conn, "SELECT saldo FROM laba_kategori WHERE id = $leafId FOR UPDATE");
    $verifyJournal = cutover_fetch_one($conn, "SELECT akun_debit, akun_kredit, total, tag FROM laba WHERE id = '" . $conn->real_escape_string($journalId) . "' FOR UPDATE");
    if (!$verifyBri || number_format((float) $verifyBri['saldo'], 2, '.', '') !== VERIFIED_BRI) {
        throw new RuntimeException('Verifikasi saldo akhir BRI gagal.');
    }
    if (!$verifyEquity || abs((float) $verifyEquity['saldo'] - ($oldEquity + $amount)) > 0.005) {
        throw new RuntimeException('Verifikasi saldo akhir ekuitas gagal.');
    }
    if (!$verifyJournal || (int) $verifyJournal['akun_debit'] !== 29 || (int) $verifyJournal['akun_kredit'] !== $leafId || abs((float) $verifyJournal['total'] - $amount) > 0.005 || $verifyJournal['tag'] !== CUTOVER_TAG) {
        throw new RuntimeException('Verifikasi jurnal debit/kredit gagal.');
    }

    $conn->commit();
    echo json_encode([
        'ok' => true,
        'journal_id' => $journalId,
        'equity_account_id' => $leafId,
        'bri_balance' => (float) $verifyBri['saldo'],
        'equity_balance' => (float) $verifyEquity['saldo'],
        'amount' => $amount,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, 'CUTOVER ROLLED BACK: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
