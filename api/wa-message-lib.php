<?php
/**
 * Template pesan WA — varian organik & rotasi per penerima.
 */

if (!function_exists('wa_message_variant_delimiter')) {
    function wa_message_variant_delimiter(): string
    {
        return "\n---\n";
    }
}

if (!function_exists('wa_message_split_variants')) {
    /**
     * @return list<string>
     */
    function wa_message_split_variants($tpl)
    {
        $tpl = trim((string) $tpl);
        if ($tpl === '') {
            return [];
        }

        $parts = preg_split('/\R---\R/u', $tpl) ?: [];
        $variants = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $variants[] = $part;
            }
        }

        return $variants !== [] ? $variants : [$tpl];
    }
}

if (!function_exists('wa_message_pick_variant')) {
    function wa_message_pick_variant($tpl, $seed)
    {
        $variants = wa_message_split_variants($tpl);
        if ($variants === []) {
            return '';
        }
        $idx = abs((int) $seed) % count($variants);
        return $variants[$idx];
    }
}

if (!function_exists('wa_message_default_variants')) {
    /**
     * Tiga varian organik (singkat, personal, opsi STOP) — pengganti teks promosi panjang.
     *
     * @return list<string>
     */
    function wa_message_default_variants($blastMode = 'all_valid')
    {
        $stop = "\n\nBalas STOP jika tidak ingin menerima pesan ini lagi.";

        if ($blastMode === 'below_target') {
            return [
                "Assalamu'alaikum {nama_customer},\n\n"
                . "Dari {nama_toko}: belanja bulan ini {total_belanja}, masih kurang {kurang} dari target {target}. "
                . "Tidak apa-apa — kami siap bantu kalau mau belanja lagi.\n\n"
                . "Balas pesan ini kalau butuh rekomendasi atau tanya stok.\n\n"
                . "Salam,\n{nama_toko}" . $stop,

                "Halo {nama_customer},\n\n"
                . "Kabar singkat dari {nama_toko}. Total belanja Anda bulan ini {total_belanja}; "
                . "kalau ingin mengejar target {target}, sisa {kurang}.\n\n"
                . "Butuh bantuan cari barang? Chat saja di sini.\n\n"
                . "Salam,\n{nama_toko}\n\n"
                . "Tidak ingin pesan lagi? Balas STOP.",

                "Wa'alaikumsalam {nama_customer},\n\n"
                . "Terima kasih sudah belanja di {nama_toko}. "
                . "Untuk bulan ini totalnya {total_belanja} (target {target}, kurang {kurang}).\n\n"
                . "Kalau ada keperluan rumah tangga, hubungi kami lewat WA ini.\n\n"
                . "Wassalam,\n{nama_toko}" . $stop,
            ];
        }

        return [
            "Assalamu'alaikum {nama_customer},\n\n"
            . "Terima kasih sudah belanja di {nama_toko}. Semoga barangnya bermanfaat untuk kebutuhan harian.\n\n"
            . "Kalau ada yang kurang atau mau tanya stok, silakan balas pesan ini.\n\n"
            . "Salam,\n{nama_toko}" . $stop,

            "Halo {nama_customer},\n\n"
            . "Dari {nama_toko} — terima kasih atas kunjungannya. Senang bisa melayani Anda.\n\n"
            . "Nanti kalau keperluan rumah tangga habis, hubungi kami lewat WA ini.\n\n"
            . "Salam,\n{nama_toko}\n\n"
            . "Tidak ingin pesan lagi? Balas STOP.",

            "Wa'alaikumsalam {nama_customer},\n\n"
            . "Terima kasih sudah jadi pelanggan {nama_toko}. Kepercayaan Anda sangat kami hargai.\n\n"
            . "Untuk belanja berikutnya, chat di sini — kami bantu cek ketersediaan barang.\n\n"
            . "Wassalam,\n{nama_toko}" . $stop,
        ];
    }
}

if (!function_exists('wa_message_default_template_blob')) {
    function wa_message_default_template_blob($blastMode = 'all_valid')
    {
        return implode(wa_message_variant_delimiter(), wa_message_default_variants($blastMode));
    }
}

if (!function_exists('wa_message_apply_placeholders')) {
    function wa_message_apply_placeholders($tpl, array $customer, $tokoNama, $targetBulan)
    {
        $total = (float) ($customer['total_belanja'] ?? 0);
        $kurang = max(0, $targetBulan - $total);

        return str_replace(
            ['{nama_customer}', '{total_belanja}', '{nama_toko}', '{target}', '{kurang}'],
            [
                (string) ($customer['customer_nama'] ?? ''),
                'Rp ' . number_format($total, 0, ',', '.'),
                (string) $tokoNama,
                'Rp ' . number_format($targetBulan, 0, ',', '.'),
                'Rp ' . number_format($kurang, 0, ',', '.'),
            ],
            (string) $tpl
        );
    }
}

if (!function_exists('wa_message_build_for_customer')) {
    function wa_message_build_for_customer($tpl, array $customer, $tokoNama, $targetBulan, $blastMode = 'all_valid')
    {
        $tpl = trim((string) $tpl);
        if ($tpl === '') {
            $tpl = wa_message_default_template_blob($blastMode);
        }

        $seed = (int) ($customer['customer_id'] ?? 0);
        if ($seed <= 0) {
            $phoneKey = (string) ($customer['phone_key'] ?? ($customer['customer_tlpn'] ?? ''));
            $seed = crc32($phoneKey);
        }

        $picked = wa_message_pick_variant($tpl, $seed);
        return wa_message_apply_placeholders($picked, $customer, $tokoNama, $targetBulan);
    }
}

if (!function_exists('wa_templates_seed_organic_defaults')) {
    /**
     * Sisipkan 3 template manual (cabang global) jika belum ada.
     */
    function wa_templates_seed_organic_defaults($conn)
    {
        if (!($conn instanceof mysqli)) {
            return;
        }

        require_once __DIR__ . '/wa-blast-schema.php';
        wa_blast_ensure_schema($conn);

        $variants = wa_message_default_variants('all_valid');
        $labels = ['A — hangat & singkat', 'B — santai', 'C — apresiatif'];
        foreach ($variants as $i => $content) {
            $name = 'Terima kasih pelanggan ' . $labels[$i];
            $nameEsc = mysqli_real_escape_string($conn, $name);
            $contentEsc = mysqli_real_escape_string($conn, $content);
            $chk = mysqli_query(
                $conn,
                "SELECT id FROM wa_templates WHERE cabang = 0 AND template_name = '$nameEsc' LIMIT 1"
            );
            if ($chk && mysqli_num_rows($chk) > 0) {
                continue;
            }
            mysqli_query(
                $conn,
                "INSERT INTO wa_templates (cabang, template_name, template_content, is_active)
                 VALUES (0, '$nameEsc', '$contentEsc', 1)"
            );
        }
    }
}
