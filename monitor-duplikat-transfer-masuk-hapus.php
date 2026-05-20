<?php
session_start();
include '_header-artibut.php';

if ($levelLogin === 'kurir' || $levelLogin === 'kasir') {
	header('Location: bo');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['tpm_id'])) {
	header('Location: monitor-duplikat-transfer-masuk');
	exit;
}

$tpm_id = (int) $_POST['tpm_id'];
$res = hapusSatuTpmDuplikatTransferMasuk($tpm_id, $sessionCabang);

$msg = $res['msg'] ?? ($res['ok'] ? 'Selesai.' : 'Gagal.');
$msgJs = json_encode($msg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Proses</title></head><body>
<script>
alert(' . $msgJs . ');
window.location.href = "monitor-duplikat-transfer-masuk";
</script>
</body></html>';
