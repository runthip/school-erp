<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Permission;

class PermissionController extends Controller
{
    public function index(): void
    {
        $this->authorize('role.manage');
        $this->view('permissions/index', [
            'title'   => 'สิทธิ์การใช้งานทั้งหมด',
            'grouped' => (new Permission())->grouped(),
        ]);
    }
}
