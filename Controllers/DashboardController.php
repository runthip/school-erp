<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;

class DashboardController extends Controller
{
    public function index(): void
    {
        $pdo = Database::pdo();
        $one = fn(string $sql) => (int) ($pdo->query($sql)->fetch()['c'] ?? 0);

        $stats = [
            'students'  => $this->safeCount($pdo, "SELECT COUNT(*) c FROM students WHERE deleted_at IS NULL AND status='studying'"),
            'personnel' => $this->safeCount($pdo, "SELECT COUNT(*) c FROM personnel WHERE deleted_at IS NULL AND status='active'"),
            'users'     => $this->safeCount($pdo, "SELECT COUNT(*) c FROM users WHERE deleted_at IS NULL"),
            'roles'     => $this->safeCount($pdo, "SELECT COUNT(*) c FROM roles"),
        ];

        // การเข้าสู่ระบบ 7 วันล่าสุด (กราฟ)
        $loginTrend = $pdo->query(
            "SELECT DATE(created_at) d, COUNT(*) c
             FROM user_login_history
             WHERE result='success' AND created_at >= (CURRENT_DATE - INTERVAL 6 DAY)
             GROUP BY DATE(created_at) ORDER BY d"
        )->fetchAll();

        $recentAudit = $pdo->query(
            "SELECT a.action, a.entity_type, a.created_at, u.full_name
             FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id
             ORDER BY a.id DESC LIMIT 8"
        )->fetchAll();

        // ---- งานสารบรรณ: แสดงเฉพาะเท่าที่ผู้ใช้เกี่ยวข้อง ----
        // safeCount กลืน error ไว้ จึงไม่พังถ้ายังไม่ได้นำเข้า SQL 23-25
        $u = Auth::user();
        $pid = ($u && ($u['linked_type'] ?? '')==='personnel') ? (int)($u['linked_id'] ?? 0) : 0;
        $doc = [
            'waitDirector' => Auth::can('document.sign')
                ? $this->safeCount($pdo, "SELECT COUNT(*) c FROM documents
                    WHERE doc_type='incoming' AND director_status='pending' AND received_at IS NOT NULL")
                : null,
            'todayRecv' => Auth::can('document.manage')
                ? $this->safeCount($pdo, "SELECT COUNT(*) c FROM documents WHERE DATE(received_at)=CURDATE()")
                : null,
            'myPending' => ($pid && Auth::can('document.inbox'))
                ? $this->safeCount($pdo, "SELECT COUNT(*) c FROM document_recipients
                    WHERE recipient_personnel_id=$pid AND status='pending'")
                : null,
            'myOverdue' => ($pid && Auth::can('document.inbox'))
                ? $this->safeCount($pdo, "SELECT COUNT(*) c FROM document_recipients
                    WHERE recipient_personnel_id=$pid AND status='pending'
                      AND due_date IS NOT NULL AND due_date < CURDATE()")
                : null,
        ];
        // ---- สถิติการมาเรียนหน้าเสาธง (วันนี้) ----
        $att = null;
        if (Auth::can('academic.attendance_report') || Auth::can('academic.attendance')) {
            try {
                $ac = new \App\Models\Academic();
                $today = date('Y-m-d');
                $st = $ac->flagStatsByDate($today);
                $prog = $ac->flagClassProgress($today);
                $att = ['stats'=>$st,'progress'=>$prog,'active'=>$ac->activeStudentCount(),'date'=>$today];
            } catch (\Throwable $e) { $att = null; }
        }

        $docRecent = [];
        if (Auth::can('document.manage') || Auth::can('document.sign')) {
            $docRecent = $this->safeRows($pdo, "SELECT id, title, receive_no, doc_type, urgency, director_status
                FROM documents WHERE doc_type='incoming' AND received_at IS NOT NULL
                ORDER BY received_at DESC LIMIT 5");
        }

        // ---- สรุปยอดนักเรียน: แสดงให้ admin + ผู้บริหาร + ครู (สิทธิ์ student.headcount) ----
        $head = null;
        if (Auth::can('student.headcount')) {
            try { $head = (new \App\Models\StudentHeadcount())->summary(); }
            catch (\Throwable $e) { $head = null; }
        }

        $this->view('dashboard/index', [
            'title'       => 'แดชบอร์ด',
            'stats'       => $stats,
            'doc'         => $doc,
            'att'         => $att,
            'head'        => $head,
            'docRecent'   => $docRecent,
            'loginTrend'  => $loginTrend,
            'recentAudit' => $recentAudit,
        ]);
    }

    /** @return array<array<string,mixed>> */
    private function safeRows($pdo, string $sql): array
    {
        try { return $pdo->query($sql)->fetchAll() ?: []; }
        catch (\Throwable $e) { return []; }
    }

    private function safeCount($pdo, string $sql): int
    {
        try {
            return (int) ($pdo->query($sql)->fetch()['c'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function workflow(): void
    {
        $this->view('workflow/index',['title'=>'ขั้นตอนงานวิชาการ']);
    }
}
