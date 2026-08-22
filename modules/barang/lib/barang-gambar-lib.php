<?php
/**
 * Gambar barang: link URL atau upload file (kompres maks. 200 KB).
 */

if (!defined('BARANG_GAMBAR_MAX_BYTES')) {
    define('BARANG_GAMBAR_MAX_BYTES', 204800);
}

if (!defined('BARANG_GAMBAR_UPLOAD_DIR')) {
    if (!defined('NUMART_ROOT')) {
        require_once dirname(__DIR__, 3) . '/bootstrap/paths.php';
    }
    define('BARANG_GAMBAR_UPLOAD_DIR', numart_path('uploads/barang'));
}

if (!defined('BARANG_GAMBAR_UPLOAD_REL')) {
    define('BARANG_GAMBAR_UPLOAD_REL', 'uploads/barang');
}

if (!function_exists('barang_gambar_ensure_column')) {
    function barang_gambar_ensure_column($conn)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $res = mysqli_query($conn, "SHOW COLUMNS FROM `barang` LIKE 'barang_gambar'");
        if ($res && mysqli_num_rows($res) === 0) {
            mysqli_query(
                $conn,
                "ALTER TABLE `barang`
                 ADD COLUMN `barang_gambar` VARCHAR(500) NULL DEFAULT NULL
                 COMMENT 'URL atau path uploads/barang'
                 AFTER `kode_suplier`"
            );
        }
    }
}

if (!function_exists('barang_gambar_ensure_upload_dir')) {
    function barang_gambar_ensure_upload_dir()
    {
        if (!is_dir(BARANG_GAMBAR_UPLOAD_DIR)) {
            mkdir(BARANG_GAMBAR_UPLOAD_DIR, 0755, true);
        }
    }
}

if (!function_exists('barang_gambar_is_local_path')) {
    function barang_gambar_is_local_path($path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return false;
        }
        if (preg_match('#^https?://#i', $path)) {
            return false;
        }
        return strpos($path, BARANG_GAMBAR_UPLOAD_REL) === 0
            || strpos($path, 'uploads/barang/') === 0;
    }
}

if (!function_exists('barang_gambar_public_url')) {
    function barang_gambar_public_url($stored)
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $stored)) {
            return $stored;
        }
        $stored = ltrim(str_replace('\\', '/', $stored), '/');
        return $stored;
    }
}

if (!function_exists('barang_gambar_delete_local_file')) {
    function barang_gambar_delete_local_file($stored)
    {
        if (!barang_gambar_is_local_path($stored)) {
            return;
        }
        $rel = ltrim(str_replace('\\', '/', $stored), '/');
        $full = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

if (!function_exists('barang_gambar_load_image_resource')) {
    /**
     * @return array{0: resource|\GdImage|null, 1: string} image + mime hint
     */
    function barang_gambar_load_image_resource($tmpPath, $mime)
    {
        $mime = strtolower((string) $mime);
        if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
            $im = @imagecreatefromjpeg($tmpPath);
            return [$im, 'jpeg'];
        }
        if ($mime === 'image/png') {
            $im = @imagecreatefrompng($tmpPath);
            return [$im, 'png'];
        }
        if ($mime === 'image/gif') {
            $im = @imagecreatefromgif($tmpPath);
            return [$im, 'gif'];
        }
        if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $im = @imagecreatefromwebp($tmpPath);
            return [$im, 'webp'];
        }
        return [null, ''];
    }
}

if (!function_exists('barang_gambar_resize_resource')) {
    /**
     * @param resource|\GdImage $src
     * @return resource|\GdImage|null
     */
    function barang_gambar_resize_resource($src, int $maxW, int $maxH)
    {
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            return null;
        }
        $ratio = min($maxW / $w, $maxH / $h, 1.0);
        $nw = (int) max(1, floor($w * $ratio));
        $nh = (int) max(1, floor($h * $ratio));
        $dst = imagecreatetruecolor($nw, $nh);
        if (!$dst) {
            return null;
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }
}

if (!function_exists('barang_gambar_save_jpeg_under_limit')) {
    /**
     * @param resource|\GdImage $im
     */
    function barang_gambar_save_jpeg_under_limit($im, string $destPath): bool
    {
        $qualities = [85, 78, 72, 65, 58, 50, 42, 35];
        $maxW = 1200;
        $maxH = 1200;
        $work = barang_gambar_resize_resource($im, $maxW, $maxH);
        if ($work === null) {
            $work = $im;
            $ownsWork = false;
        } else {
            $ownsWork = true;
        }

        foreach ($qualities as $q) {
            if (!@imagejpeg($work, $destPath, $q)) {
                if ($ownsWork) {
                    imagedestroy($work);
                }
                return false;
            }
            if (is_file($destPath) && filesize($destPath) <= BARANG_GAMBAR_MAX_BYTES) {
                if ($ownsWork) {
                    imagedestroy($work);
                }
                return true;
            }
        }

        // Masih besar: perkecil dimensi bertahap
        $dims = [[1000, 1000], [800, 800], [640, 640], [480, 480]];
        foreach ($dims as $d) {
            $smaller = barang_gambar_resize_resource($im, $d[0], $d[1]);
            if ($smaller === null) {
                continue;
            }
            for ($q = 75; $q >= 30; $q -= 8) {
                if (!@imagejpeg($smaller, $destPath, $q)) {
                    imagedestroy($smaller);
                    break;
                }
                if (is_file($destPath) && filesize($destPath) <= BARANG_GAMBAR_MAX_BYTES) {
                    imagedestroy($smaller);
                    if ($ownsWork) {
                        imagedestroy($work);
                    }
                    return true;
                }
            }
            imagedestroy($smaller);
        }

        if ($ownsWork) {
            imagedestroy($work);
        }
        return is_file($destPath) && filesize($destPath) <= BARANG_GAMBAR_MAX_BYTES;
    }
}

if (!function_exists('barang_gambar_process_upload')) {
    /**
     * @param array<string, mixed> $fileElem $_FILES['barang_gambar_file']
     * @return array{ok: bool, path: string, message: string}
     */
    function barang_gambar_process_upload(array $fileElem, $cabang = 0)
    {
        if (!extension_loaded('gd')) {
            return ['ok' => false, 'path' => '', 'message' => 'Ekstensi PHP GD belum aktif di server.'];
        }

        $err = (int) ($fileElem['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'path' => '', 'message' => 'Tidak ada file dipilih.'];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'path' => '', 'message' => 'Upload gagal (kode error ' . $err . ').'];
        }

        $tmp = (string) ($fileElem['tmp_name'] ?? '');
        $size = (int) ($fileElem['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'path' => '', 'message' => 'File upload tidak valid.'];
        }
        if ($size > 15 * 1024 * 1024) {
            return ['ok' => false, 'path' => '', 'message' => 'Ukuran file maksimal 15 MB sebelum kompresi.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            return ['ok' => false, 'path' => '', 'message' => 'Format harus JPG, PNG, GIF, atau WebP.'];
        }

        [$im,] = barang_gambar_load_image_resource($tmp, $mime);
        if ($im === null) {
            return ['ok' => false, 'path' => '', 'message' => 'Gambar tidak bisa dibaca.'];
        }

        barang_gambar_ensure_upload_dir();
        $cabang = (int) $cabang;
        $name = 'brg-c' . $cabang . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.jpg';
        $dest = BARANG_GAMBAR_UPLOAD_DIR . DIRECTORY_SEPARATOR . $name;
        $rel = BARANG_GAMBAR_UPLOAD_REL . '/' . $name;

        $ok = barang_gambar_save_jpeg_under_limit($im, $dest);
        imagedestroy($im);

        if (!$ok || !is_file($dest)) {
            @unlink($dest);
            return ['ok' => false, 'path' => '', 'message' => 'Gagal mengompres gambar ke bawah 200 KB.'];
        }

        return ['ok' => true, 'path' => $rel, 'message' => 'OK'];
    }
}

if (!function_exists('barang_gambar_normalize_link')) {
    function barang_gambar_normalize_link($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            return '';
        }
        if (strlen($url) > 500) {
            return '';
        }
        return $url;
    }
}

if (!function_exists('barang_gambar_resolve_from_request')) {
    /**
     * Prioritas: hapus → upload file → link URL → pertahankan lama.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     */
    function barang_gambar_resolve_from_request(array $post, array $files = [], $existingStored = '')
    {
        $existingStored = trim((string) $existingStored);

        if (!empty($post['barang_gambar_hapus']) && (string) $post['barang_gambar_hapus'] === '1') {
            barang_gambar_delete_local_file($existingStored);
            return '';
        }

        $file = $files['barang_gambar_file'] ?? null;
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $cabang = (int) ($post['barang_cabang'] ?? $post['gambar_cabang'] ?? 0);
            $up = barang_gambar_process_upload($file, $cabang);
            if (!$up['ok']) {
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    @session_start();
                }
                $_SESSION['barang_gambar_error'] = $up['message'];
                return $existingStored;
            }
            if ($existingStored !== '' && $up['path'] !== $existingStored) {
                barang_gambar_delete_local_file($existingStored);
            }
            return $up['path'];
        }

        $mode = isset($post['barang_gambar_mode']) ? (string) $post['barang_gambar_mode'] : 'link';
        $link = barang_gambar_normalize_link($post['barang_gambar_link'] ?? '');
        if ($mode === 'link' && $link !== '') {
            if ($existingStored !== '' && barang_gambar_is_local_path($existingStored)) {
                barang_gambar_delete_local_file($existingStored);
            }
            return $link;
        }

        if ($mode === 'link' && trim((string) ($post['barang_gambar_link'] ?? '')) !== '' && $link === '') {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            $_SESSION['barang_gambar_error'] = 'Link gambar tidak valid. Gunakan URL http:// atau https://';
            return $existingStored;
        }

        return $existingStored;
    }
}
