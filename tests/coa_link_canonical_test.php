<?php

require_once __DIR__ . '/../aksi/koneksi.php';
require_once __DIR__ . '/../aksi/coa-link-mirror-lib.php';
require_once __DIR__ . '/../aksi/laba-accural-neraca-lib.php';

function test_fail(string $message): void
{
    throw new RuntimeException($message);
}

coa_link_mirror_ensure_table($conn); // schema metadata migration is intentionally outside test rollback

$candidateSql = "
    SELECT kode_akun
    FROM laba_kategori
    WHERE kode_akun IS NOT NULL AND TRIM(kode_akun) NOT IN ('', '-')
    GROUP BY kode_akun
    HAVING SUM(cabang = 0) > 0 AND COUNT(DISTINCT IF(cabang > 0, cabang, NULL)) >= 2
    LIMIT 1
";
$candidateResult = mysqli_query($conn, $candidateSql);
$candidate = $candidateResult ? mysqli_fetch_assoc($candidateResult) : null;
if (!$candidate) {
    test_fail('Tidak ada kode akun yang tersedia di pusat dan sedikitnya dua cabang untuk integration test');
}
$kode = (string) $candidate['kode_akun'];
$kodeEsc = mysqli_real_escape_string($conn, $kode);
$accountsResult = mysqli_query($conn, "SELECT id,cabang,name,saldo FROM laba_kategori WHERE kode_akun='$kodeEsc' ORDER BY (cabang=0) DESC,cabang ASC");
$source = null;
$targets = [];
while ($accountsResult && ($row = mysqli_fetch_assoc($accountsResult))) {
    if ((int) $row['cabang'] === 0 && $source === null) {
        $source = $row;
    } elseif ((int) $row['cabang'] > 0 && count($targets) < 2) {
        $targets[] = $row;
    }
}
if (!$source || count($targets) !== 2) {
    test_fail('Fixture akun pusat/cabang tidak lengkap');
}

mysqli_begin_transaction($conn);
try {
    foreach ($targets as $target) {
        $result = coa_link_mirror_upsert_one(
            $conn,
            $kode,
            0,
            (int) $target['cabang'],
            (string) $source['name'],
            0,
            true,
            (int) $source['id'],
            (int) $target['id']
        );
        if (empty($result['ok'])) {
            test_fail('Gagal membuat link test: ' . ($result['message'] ?? 'unknown'));
        }
    }

    $invalid = coa_link_mirror_upsert_one($conn, $kode, (int) $targets[0]['cabang'], 0, (string) $source['name']);
    if (!empty($invalid['ok'])) {
        test_fail('Arah toko -> Grosir harus ditolak');
    }

    $canonical = 123456.75;
    mysqli_query($conn, 'UPDATE laba_kategori SET saldo=' . $canonical . ' WHERE id=' . (int) $source['id']);
    coa_link_mirror_after_saldo_change($conn, $kode, 0, 0.0);
    foreach ($targets as $target) {
        $q = mysqli_query($conn, 'SELECT saldo FROM laba_kategori WHERE id=' . (int) $target['id']);
        $actual = (float) mysqli_fetch_assoc($q)['saldo'];
        if (abs($actual - $canonical) > 0.001) {
            test_fail('Perubahan canonical tidak tersinkron ke semua follower');
        }
    }

    mysqli_query($conn, 'UPDATE laba_kategori SET saldo=999999 WHERE id=' . (int) $targets[0]['id']);
    coa_link_mirror_after_saldo_change($conn, $kode, (int) $targets[0]['cabang'], 876542.25);
    $sourceNow = (float) mysqli_fetch_assoc(mysqli_query($conn, 'SELECT saldo FROM laba_kategori WHERE id=' . (int) $source['id']))['saldo'];
    $targetNow = (float) mysqli_fetch_assoc(mysqli_query($conn, 'SELECT saldo FROM laba_kategori WHERE id=' . (int) $targets[0]['id']))['saldo'];
    if (abs($sourceNow - $canonical) > 0.001 || abs($targetNow - $canonical) > 0.001) {
        test_fail('Follower mendorong nilai ke canonical atau tidak dipulihkan');
    }

    $validation = coa_link_mirror_validate_mutation_accounts($conn, ['akun_debit' => (int) $targets[0]['id']]);
    if (!empty($validation['ok'])) {
        test_fail('Transaksi manual pada follower harus ditolak');
    }
	$genericRejected = false;
	try {
		akun_update_saldo_delta($conn, $kode, 'Test', 'aktiva', 'debit', 1.0, (int) $targets[0]['cabang']);
	} catch (RuntimeException $e) {
		$genericRejected = true;
	}
	if (!$genericRejected) {
		test_fail('Updater saldo generik harus menolak follower sebelum mutasi');
	}

    $synthetic = [
        'aktiva' => [[
            'kode_akun' => $kode,
            'name' => 'Test mirror',
            'saldo_akhir' => $canonical * 3,
            'per_cabang' => [0 => $canonical, (int) $targets[0]['cabang'] => $canonical, (int) $targets[1]['cabang'] => $canonical],
        ]],
        'pasiva' => [],
        'modal' => [],
    ];
    $followers = [$kode => [(int) $targets[0]['cabang'] => true, (int) $targets[1]['cabang'] => true]];
    $eliminated = labaAccrual_neraca_terapkan_eliminasi($synthetic, $followers);
    $reported = (float) $eliminated['neraca']['aktiva'][0]['saldo_akhir'];
    if (abs($reported - $canonical) > 0.001) {
        test_fail('Konsolidasi masih menghitung follower ganda');
    }

    mysqli_rollback($conn);
    echo "coa_link_canonical_test: OK ($kode)" . PHP_EOL;
} catch (Throwable $e) {
    mysqli_rollback($conn);
    fwrite(STDERR, 'coa_link_canonical_test: FAIL - ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
