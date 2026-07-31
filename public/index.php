<?php
/**
 * Front Controller — จุดเข้าเดียวของระบบ
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// ---- Autoloader (PSR-4 อย่างง่าย ไม่ต้องใช้ composer) ----
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $rel = str_replace(['App\\', '\\'], ['', '/'], $class);
    $file = BASE_PATH . '/app/' . $rel . '.php';
    if (is_file($file)) require $file;
});

require BASE_PATH . '/app/Core/helpers.php';

// ---- Config ----
$config = require BASE_PATH . '/config/config.php';
$GLOBALS['config'] = $config;
$GLOBALS['menu'] = require BASE_PATH . '/config/menu.php';

date_default_timezone_set($config['app']['timezone']);

// ตรวจ base path อัตโนมัติ (รองรับวางในโฟลเดอร์ย่อยของ htdocs เช่น /school-erp/public)
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$detected  = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '') ? '' : rtrim($scriptDir, '/');
$basePath  = $config['app']['base_url'] !== '' ? rtrim($config['app']['base_url'], '/') : $detected;
$config['app']['base_url'] = $basePath;
$GLOBALS['config'] = $config;
App\Core\Request::setBasePath($basePath);

// ---- โหมดปิดปรับปรุงระบบ (มีไฟล์ storage/maintenance.flag) ----
// ต้องตรวจก่อนแตะฐานข้อมูล เพราะช่วงอัปเดตอาจกำลังแก้โครงสร้างตารางอยู่
// ผู้ดูแลผ่านเข้าไปทดสอบได้ด้วย ?key=<ข้อความในไฟล์> (จำไว้ในคุกกี้ 2 ชั่วโมง)
$maintFile = BASE_PATH . '/storage/maintenance.flag';
if (is_file($maintFile)) {
    $key   = trim((string) @file_get_contents($maintFile));
    $given = (string) ($_GET['key'] ?? $_COOKIE['erp_maint'] ?? '');
    if ($key !== '' && $given !== '' && hash_equals($key, $given)) {
        setcookie('erp_maint', $key, ['expires' => time() + 7200, 'path' => '/', 'httponly' => true]);
    } else {
        http_response_code(503);
        header('Retry-After: 900');
        require BASE_PATH . '/app/Views/errors/maintenance.php';
        exit;
    }
}

if ($config['app']['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}

// ---- Core services ----
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Core\Router;
use App\Core\Request;
use App\Core\Tenant;

// 1) ศูนย์ควบคุมกลาง (ถ้าเปิดโหมดหลายโรงเรียน) — ล้มเหลวได้โดยไม่กระทบระบบเดิม
if (!empty($config['central']['enabled'])) {
    Database::connectCentral($config['central']);
}

// 2) Session ต้องเริ่มก่อน เพื่ออ่านว่าผู้ใช้กำลังอยู่โรงเรียนไหน
Session::start($config['security']['session_lifetime']);

// 3) เลือกฐานข้อมูลของโรงเรียนจาก session — ถ้าไม่มี ใช้ฐานข้อมูลตาม .env (โหมดโรงเรียนเดียว)
if (!Tenant::boot()) {
    if (Tenant::enabled()) {
        // ยังไม่ได้เลือกโรงเรียน (หรือโรงเรียนถูกระงับ) — ห้ามต่อฐานข้อมูลของใครทั้งสิ้น
        // และล้างการล็อกอินที่ค้างอยู่ กันไม่ให้ user_id ไปตรงกับผู้ใช้ของโรงเรียนอื่น
        Session::forget('user_id');
    } else {
        Database::connect($config['db']);
    }
}

View::setPath(BASE_PATH . '/app/Views');

// ---- Routing ----
$router = new Router();
require BASE_PATH . '/config/routes.php';

try {
    $router->dispatch(Request::method(), Request::uri());
} catch (\Throwable $e) {
    if ($config['app']['debug']) {
        http_response_code(500);
        echo '<pre style="padding:2rem;font-family:monospace">';
        echo e($e->getMessage()) . "\n\n" . e($e->getTraceAsString());
        echo '</pre>';
    } else {
        error_log($e->getMessage());
        http_response_code(500);
        echo View::render('errors/500', ['title' => 'เกิดข้อผิดพลาด'], 'app');
    }
}
