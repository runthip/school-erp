<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    private Notification $m;
    public function __construct(){ $this->m = new Notification(); }

    public function index(): void
    {
        $uid=(int)Auth::id();
        $this->view('notifications/index', [
            'title'=>'การแจ้งเตือน',
            'rows'=>$this->m->forUser($uid, 50),
        ]);
    }

    public function read(string $id): void
    {
        $this->verifyCsrf();
        $this->m->markRead((int)$id, (int)Auth::id());
        $link=trim((string)Request::input('link',''));
        // ไปยังหน้าที่เกี่ยวข้องถ้ามี ไม่งั้นกลับหน้ารายการ
        $this->redirect($link !== '' ? $link : 'notifications');
    }

    public function readAll(): void
    {
        $this->verifyCsrf();
        $this->m->markAllRead((int)Auth::id());
        $this->back('notifications','success','ทำเครื่องหมายอ่านทั้งหมดแล้ว');
    }
}
