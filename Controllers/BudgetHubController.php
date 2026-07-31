<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Schema;
use App\Models\BudgetMemo;

/**
 * ศูนย์ฝ่ายงบประมาณ/พัสดุ — รวม flow ทั้งฝ่ายไว้หน้าเดียว
 * แผน → โครงการ → กิจกรรม → งป.01 (เบิกจ่าย) → อนุมัติ → ตัดงบ → พัสดุ → จ่าย
 */
class BudgetHubController extends Controller
{
    public function index(): void
    {
        $this->authorize('budget.memo');
        $year = (int)Request::input('year', (int)date('Y')+543);

        $stats = [];
        $pending = [];
        // ดึงสถิติเฉพาะเมื่อ import 28/29 แล้ว (กัน 500)
        if (!Schema::missingFor('budget_memo')) {
            $m = new BudgetMemo();
            $stats = $m->hubStats($year);
            if (Auth::can('budget.memo_approve')) $pending = $m->pendingApprovals();
        }

        $this->view('budget_hub/index', [
            'title' => 'ฝ่ายงบประมาณ/พัสดุ',
            'year' => $year,
            'stats' => $stats,
            'pending' => $pending,
            'canApprove' => Auth::can('budget.memo_approve'),
        ]);
    }
}
