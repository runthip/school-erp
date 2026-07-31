<?php
/**
 * การตั้งค่าระบบ (โหลดจาก .env ถ้ามี ไม่งั้นใช้ค่า default)
 */
$envPath = dirname(__DIR__) . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $_ENV[$k] = $v;
    }
}
$env = fn(string $k, $default = null) => $_ENV[$k] ?? getenv($k) ?: $default;

return [
    'app' => [
        'name'     => $env('APP_NAME', 'School ERP Enterprise'),
        'env'      => $env('APP_ENV', 'production'),
        'debug'    => filter_var($env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
        'base_url' => rtrim($env('BASE_URL', ''), '/'),
        'timezone' => $env('APP_TZ', 'Asia/Bangkok'),
        // ปุ่ม "เข้าสู่ระบบด่วน" (บัญชีสาธิต) — ใช้ได้เฉพาะ APP_ENV<>production เท่านั้น
        'demo_login' => filter_var($env('DEMO_LOGIN', 'false'), FILTER_VALIDATE_BOOL),
    ],
    'db' => [
        'host'    => $env('DB_HOST', '127.0.0.1'),
        'port'    => $env('DB_PORT', '3306'),
        'name'    => $env('DB_NAME', 'school_erp'),
        'user'    => $env('DB_USER', 'root'),
        'pass'    => $env('DB_PASS', ''),
        'socket'  => $env('DB_SOCKET', ''),
        'charset' => 'utf8mb4',
    ],
    // ---- ระบบหลายโรงเรียน (โค้ดชุดเดียว · แยกฐานข้อมูลต่อโรงเรียน) ----
    // ปิดไว้ = ทำงานแบบโรงเรียนเดียวตามค่า DB_* ข้างบนเหมือนเดิม
    'central' => [
        'enabled' => filter_var($env('MULTI_TENANT', 'false'), FILTER_VALIDATE_BOOL),
        'name'    => $env('CENTRAL_DB_NAME', 'school_erp_central'),
        'host'    => $env('DB_HOST', '127.0.0.1'),
        'port'    => $env('DB_PORT', '3306'),
        'user'    => $env('DB_USER', 'root'),
        'pass'    => $env('DB_PASS', ''),
        'socket'  => $env('DB_SOCKET', ''),
        'charset' => 'utf8mb4',
        // คำนำหน้าชื่อฐานข้อมูลของโรงเรียน เช่น erp_10123456
        'db_prefix' => $env('TENANT_DB_PREFIX', 'erp_'),
        // บัญชี MySQL ที่มีสิทธิ์ CREATE DATABASE (ใช้เฉพาะตอนสร้างโรงเรียนใหม่)
        'provision_user' => $env('PROVISION_DB_USER', ''),
        'provision_pass' => $env('PROVISION_DB_PASS', ''),
    ],
    'security' => [
        'max_login_attempts' => (int) $env('MAX_LOGIN_ATTEMPTS', 5),
        'lockout_minutes'    => (int) $env('LOCKOUT_MINUTES', 15),
        'session_lifetime'   => (int) $env('SESSION_LIFETIME', 7200),
        'reset_ttl_minutes'  => (int) $env('RESET_TTL_MINUTES', 60),
        // รหัสผ่านเริ่มต้นที่แอดมินใช้รีเซตให้ผู้ใช้ที่ลืมรหัส (ผู้ใช้ต้องเปลี่ยนทันทีเมื่อล็อกอิน)
        'default_password'   => $env('DEFAULT_PASSWORD', 'Reset@1234'),
    ],
    'mail' => [
        // ส่งอีเมลจริงผ่าน PHP mail() (ต้องตั้งค่า MTA/SMTP ที่เซิร์ฟเวอร์)
        // ทุกฉบับจะถูกบันทึกสำเนาไว้ที่ storage/mail ด้วย (dev outbox)
        'from_email' => $env('MAIL_FROM', ''),   // ว่าง = no-reply@<host>
        'from_name'  => $env('MAIL_FROM_NAME', $env('APP_NAME', 'School ERP')),
        'enabled'    => filter_var($env('MAIL_ENABLED', 'true'), FILTER_VALIDATE_BOOL),
    ],
];
