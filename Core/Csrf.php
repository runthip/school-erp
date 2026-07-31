<?php
namespace App\Core;

/**
 * ป้องกัน CSRF ด้วย token ใน session
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        $t = htmlspecialchars(self::token(), ENT_QUOTES);
        return '<input type="hidden" name="_csrf" value="' . $t . '">';
    }

    public static function check(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }
}
