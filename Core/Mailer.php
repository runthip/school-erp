<?php
namespace App\Core;

/**
 * ส่งอีเมลอย่างง่ายผ่าน PHP mail() + เก็บสำเนาไว้ที่ storage/mail (dev outbox)
 * โปรดักชันควรตั้งค่า MTA/SMTP ที่เซิร์ฟเวอร์ให้ mail() ส่งออกจริง
 */
class Mailer
{
    /** ส่งอีเมล HTML — คืน true ถ้า mail() รับคำสั่ง (ไม่การันตีถึงปลายทาง) */
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        $cfg = $GLOBALS['config']['mail'] ?? [];
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = preg_replace('/:\d+$/', '', $host);
        $from = ($cfg['from_email'] ?? '') ?: ('no-reply@' . $host);
        $fromName = $cfg['from_name'] ?? 'School ERP';

        // encode ชื่อผู้ส่ง (ภาษาไทย) ตาม RFC 2047
        $fromHeader = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>';
        $subjEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'From: ' . $fromHeader,
            'Reply-To: ' . $from,
            'X-Mailer: SchoolERP',
        ]);
        $body = chunk_split(base64_encode($htmlBody));

        // เก็บสำเนา dev outbox เสมอ (ไว้ตรวจสอบ/สำรองเมื่อ MTA ยังไม่พร้อม)
        self::archive($to, $subject, $htmlBody);

        if (!($cfg['enabled'] ?? true)) return false;
        try {
            return @mail($to, $subjEnc, $body, $headers, '-f' . $from);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function archive(string $to, string $subject, string $html): void
    {
        try {
            $dir = BASE_PATH . '/storage/mail';
            if (!is_dir($dir)) @mkdir($dir, 0777, true);
            $safe = preg_replace('/[^a-zA-Z0-9._@-]/', '_', $to);
            $file = $dir . '/' . date('Ymd_His') . '_' . $safe . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.html';
            $meta = "<!-- To: {$to} | Subject: {$subject} | " . date('c') . " -->\n";
            @file_put_contents($file, $meta . $html);
        } catch (\Throwable $e) { /* ไม่ให้ archive ล้มแล้วกระทบการส่ง */ }
    }
}
