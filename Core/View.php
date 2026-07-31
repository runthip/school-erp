<?php
namespace App\Core;

/**
 * เรนเดอร์ view ด้วย layout
 */
final class View
{
    private static string $viewPath = '';

    public static function setPath(string $path): void
    {
        self::$viewPath = rtrim($path, '/');
    }

    /**
     * แสดง view ภายใน layout
     */
    public static function render(string $view, array $data = [], string $layout = 'app'): string
    {
        $content = self::partial($view, $data);
        $data['content'] = $content;
        return self::partial("layouts/{$layout}", $data);
    }

    /**
     * เรนเดอร์ไฟล์ view เดี่ยว คืนเป็น string
     */
    public static function partial(string $view, array $data = []): string
    {
        $file = self::$viewPath . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("ไม่พบ view: {$view}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
