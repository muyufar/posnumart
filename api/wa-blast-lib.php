<?php
/**
 * Helper riwayat & anti-spam WA blast (per cabang, per hari).
 */

if (!function_exists('wa_blast_phone_key')) {
    function wa_blast_phone_key($raw)
    {
        $p = preg_replace('/^0/', '62', (string) $raw);
        return preg_replace('/[^0-9]/', '', $p);
    }
}

if (!function_exists('wa_blast_get_sent_today_rows')) {
    /**
     * @return list<array{phone_key: string, customer_phone: string, customer_id: int, customer_nama: string, last_sent_at: string}>
     */
    function wa_blast_get_sent_today_rows($conn, $cabang)
    {
        $cabang = (int) $cabang;
        $sql = "SELECT 
                    r.customer_phone,
                    r.customer_id,
                    COALESCE(c.customer_nama, '') AS customer_nama,
                    MAX(COALESCE(r.sent_at, r.created_at)) AS last_sent_at
                FROM wa_blast_recipients r
                INNER JOIN wa_blast_history h ON h.id = r.blast_id
                LEFT JOIN customer c ON c.customer_id = r.customer_id
                WHERE h.cabang = $cabang
                  AND r.status = 'sent'
                  AND DATE(COALESCE(r.sent_at, r.created_at)) = CURDATE()
                GROUP BY r.customer_phone, r.customer_id, c.customer_nama
                ORDER BY last_sent_at DESC";

        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return [];
        }

        $rows = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $key = wa_blast_phone_key($row['customer_phone'] ?? '');
            if ($key === '') {
                continue;
            }
            $rows[] = [
                'phone_key' => $key,
                'customer_phone' => (string) $row['customer_phone'],
                'customer_id' => (int) $row['customer_id'],
                'customer_nama' => (string) $row['customer_nama'],
                'last_sent_at' => (string) $row['last_sent_at'],
            ];
        }
        return $rows;
    }
}

if (!function_exists('wa_blast_phones_sent_today_set')) {
    /** @return array<string, true> */
    function wa_blast_phones_sent_today_set($conn, $cabang)
    {
        $set = [];
        foreach (wa_blast_get_sent_today_rows($conn, $cabang) as $row) {
            $set[$row['phone_key']] = true;
        }
        return $set;
    }
}

if (!function_exists('wa_blast_is_sent_today')) {
    function wa_blast_is_sent_today($conn, $cabang, $phone)
    {
        $key = wa_blast_phone_key($phone);
        if ($key === '') {
            return false;
        }
        $set = wa_blast_phones_sent_today_set($conn, $cabang);
        return isset($set[$key]);
    }
}
