<?php
namespace App\Core;

/**
 * ห่อหุ้มข้อมูล HTTP request
 */
final class Request
{
    private static string $base = '';

    /** กำหนด base path เมื่อรันในโฟลเดอร์ย่อย เช่น /school-erp/public */
    public static function setBasePath(string $base): void
    {
        self::$base = rtrim($base, '/');
    }

    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public static function uri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (self::$base !== '' && str_starts_with($uri, self::$base)) {
            $uri = substr($uri, strlen(self::$base));
        }
        return '/' . trim($uri, '/');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public static function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $_POST[$k] ?? $_GET[$k] ?? null;
        }
        return $out;
    }

    public static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function userAgent(): string
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    }
}
