<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index(): void
    {
        $this->authorize('audit.view');
        $model  = new AuditLog();
        $search = trim((string) Request::input('q', ''));
        $action = (string) Request::input('action', '');
        $page   = max(1, (int) Request::input('page', 1));
        $limit  = 30;
        $offset = ($page - 1) * $limit;

        $rows  = $model->paginate($search, $action, $limit, $offset);
        $total = $model->countAll($search, $action);

        $this->view('audit/index', [
            'title'   => 'ประวัติการใช้งาน (Audit Log)',
            'rows'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'pages'   => max(1, (int) ceil($total / $limit)),
            'search'  => $search,
            'action'  => $action,
            'actions' => $model->distinctActions(),
        ]);
    }
}
