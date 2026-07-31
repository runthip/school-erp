<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;

/**
 * หน้าโมดูลที่ยังอยู่ระหว่างพัฒนา — กันด้วยสิทธิ์ตามเมนู
 * ใช้เพื่อแสดง "มุมมอง" ของแต่ละบทบาทว่าเข้าถึงส่วนใดได้บ้าง
 */
class ModuleController extends Controller
{
    public function show(string $slug): void
    {
        $item = menu_find_by_url('m/' . $slug);
        if (!$item) {
            http_response_code(404);
            echo View::render('errors/404', ['title' => 'ไม่พบหน้า'], 'app');
            return;
        }
        if (!empty($item['perm'])) {
            $this->authorize($item['perm']);
        }
        $this->view('module/placeholder', [
            'title'   => $item['label'],
            'section' => $item['section'] ?? '',
            'perm'    => $item['perm'] ?? null,
        ]);
    }
}
